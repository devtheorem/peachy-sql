<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\{PeachySql, SqlException};
use DevTheorem\PeachySQL\QueryBuilder\SqlParams;
use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Database tests for the PeachySQL library.
 */
abstract class DbTestCase extends TestCase
{
    private string $table = 'Users';

    /**
     * Returns a list of PeachySQL implementation instances.
     */
    abstract public static function dbProvider(): PeachySql;

    abstract public static function createConnection(): PDO;

    abstract protected function getExpectedBadSyntaxCode(): int;

    abstract protected function getExpectedBadSyntaxError(): string;

    abstract protected function getIdentityColumnDefinition(): string;

    /**
     * Returns statements which create a TriggerTest table with an insert trigger
     * that inserts into a TriggerAudit table having IDs starting from 5000.
     * @return list<string>
     */
    abstract protected function getTriggerTestSql(): array;

    protected function getExpectedBadSqlState(): string
    {
        return '42000';
    }

    /**
     * Returns rows with explicit IDs from 1 to $count in a shuffled (but deterministic) order.
     * @return list<array{id: int, name: string}>
     */
    protected static function getShuffledIdRows(int $count): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $id = ($i * 7919) % $count + 1; // a permutation since 7919 is prime
            $rows[] = ['id' => $id, 'name' => "row{$id}"];
        }

        return $rows;
    }

    public function testNoIdentityInsert(): void
    {
        $peachySql = static::dbProvider();
        $peachySql->query("DROP TABLE IF EXISTS Test");
        $peachySql->query("CREATE TABLE Test ( name VARCHAR(50) NOT NULL )");

        if ($peachySql->options->multiRowset) {
            // ensure that row can be selected from second result set
            $sql = "INSERT INTO Test (name) VALUES ('multi'); SELECT name FROM Test";
            $this->assertSame(['name' => 'multi'], $peachySql->query($sql)->getFirst());
        }

        // affected count should be zero if no rows are updated
        $this->assertSame(0, $peachySql->updateRows('Test', ['name' => 'test'], ['name' => 'non existent']));

        $colVals = [
            ['name' => 'name1'],
            ['name' => 'name2'],
        ];

        $result = $peachySql->insertRows('Test', $colVals);
        $this->assertSame(2, $result->affected);
        $this->assertCount(0, $result->ids);
        $this->assertSame($colVals, $peachySql->query("SELECT * FROM Test WHERE name <> 'multi'")->getAll());
    }

    public function testInsertIdsWithTrigger(): void
    {
        $db = static::dbProvider();
        $conn = static::createConnection();

        // without an ID column, PostgreSQL gets the ID from lastval(), which is from the trigger's sequence
        $idColumns = $db->options->driver === 'pgsql' ? ['id'] : [null, 'id'];

        foreach ($idColumns as $idColumn) {
            foreach ($this->getTriggerTestSql() as $sql) {
                $conn->exec($sql);
            }

            $single = $db->insertRow('TriggerTest', ['name' => 'single'], $idColumn);
            $this->assertSame(1, $single->affected);

            $bulk = $db->insertRows('TriggerTest', [['name' => 'a'], ['name' => 'b'], ['name' => 'c']], idColumn: $idColumn);
            $this->assertSame(3, $bulk->affected);
            $this->assertCount(3, $bulk->ids);

            $expected = [
                ['id' => $single->id, 'name' => 'single'],
                ['id' => $bulk->ids[0], 'name' => 'a'],
                ['id' => $bulk->ids[1], 'name' => 'b'],
                ['id' => $bulk->ids[2], 'name' => 'c'],
            ];

            $this->assertSame($expected, $db->query('SELECT id, name FROM TriggerTest ORDER BY id')->getAll());
            $this->assertSame(['audit_count' => 4], $db->query('SELECT COUNT(*) AS audit_count FROM TriggerAudit')->getFirst());
        }
    }

    public function testFailedInsertThrows(): void
    {
        $db = static::dbProvider();
        $row = ['name' => null, 'dob' => '2000-01-01', 'weight' => 1, 'is_disabled' => false];

        try {
            $db->insertRow($this->table, $row);
            $this->fail('insertRow failed to throw exception');
        } catch (SqlException $e) {
            $this->assertStringContainsStringIgnoringCase('null', $e->getMessage());
        }

        $options = $db->options;
        $maxBoundParams = $options->maxBoundParams;
        $colVals = array_fill(0, 4, [...$row, 'name' => 'failed batch']);
        $colVals[] = $row;

        try {
            // None of the rows should be inserted when the last row fails, including when the rows are split
            // into batches (with a max of 8 bound parameters, the 5 rows are inserted 2 at a time).
            foreach ([$maxBoundParams, 8] as $maxParams) {
                $options->maxBoundParams = $maxParams;

                foreach ([null, 'user_id'] as $idColumn) {
                    try {
                        $db->insertRows($this->table, $colVals, idColumn: $idColumn);
                        $this->fail('insertRows failed to throw exception');
                    } catch (SqlException $e) {
                        $this->assertStringContainsStringIgnoringCase('null', $e->getMessage());
                    }
                }
            }
        } finally {
            $options->maxBoundParams = $maxBoundParams;
        }

        $inserted = $db->selectFrom("SELECT COUNT(*) AS inserted FROM {$this->table}")
            ->where(['name' => 'failed batch'])->query()->getFirst();
        $this->assertSame(['inserted' => 0], $inserted);
    }

    /**
     * Bulk inserts rows from multiple processes at the same time with an ID column, and verifies
     * that each returned ID belongs to the row that was inserted by that process.
     */
    public function testConcurrentInsertIds(): void
    {
        $db = static::dbProvider();
        $db->query('DROP TABLE IF EXISTS ConcurrentTest');
        $db->query('CREATE TABLE ConcurrentTest (id ' . $this->getIdentityColumnDefinition()
            . ', worker INT NOT NULL, batch INT NOT NULL, seq INT NOT NULL)');

        $workers = 6;
        $batches = 15;
        $rowsPerBatch = 500;
        $startTime = microtime(true) + 1.5; // allow time for all the processes to start
        $processes = [];

        for ($worker = 0; $worker < $workers; $worker++) {
            $command = [
                PHP_BINARY, __DIR__ . '/insert-worker.php', static::class, 'ConcurrentTest',
                (string) $worker, (string) $batches, (string) $rowsPerBatch, (string) $startTime,
            ];

            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));

            if ($process === false) {
                throw new \Exception('Failed to start insert worker');
            }

            $processes[$worker] = [$process, $pipes];
        }

        $expected = [];

        foreach ($processes as $worker => [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), "Insert worker {$worker} failed: {$output} {$errors}");

            /** @var list<list<int>> $batchIds */
            $batchIds = json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount($batches, $batchIds);

            foreach ($batchIds as $batch => $ids) {
                $this->assertCount($rowsPerBatch, $ids);

                foreach ($ids as $seq => $id) {
                    $expected[$id] = ['id' => $id, 'worker' => $worker, 'batch' => $batch, 'seq' => $seq];
                }
            }
        }

        $actual = $db->query('SELECT id, worker, batch, seq FROM ConcurrentTest')->getAll();
        $this->assertSame($workers * $batches * $rowsPerBatch, count($actual));
        $this->assertSame(count($actual), count($expected), 'Duplicate IDs were returned');

        // compare the rows individually, since diffing large arrays on failure is extremely slow
        $mismatches = [];

        foreach ($actual as $row) {
            /** @var int $id */
            $id = $row['id'];

            if (($expected[$id] ?? null) !== $row) {
                $mismatches[] = ['returned' => $expected[$id] ?? null, 'actual' => $row];
            }
        }

        $message = count($mismatches) . ' returned IDs do not match the inserted row';
        $this->assertSame([], array_slice($mismatches, 0, 3), $message);
    }

    public function testTransactions(): void
    {
        $peachySql = static::dbProvider();
        $peachySql->begin(); // start transaction

        $colVals = [
            'name' => 'Raymond Boyce',
            'dob' => '1946-01-01',
            'weight' => 140,
            'is_disabled' => true,
            'uuid' => $peachySql->makeBinaryParam(Uuid::uuid4()->getBytes()),
        ];

        $id = $peachySql->insertRow($this->table, $colVals)->id;
        $sql = "SELECT user_id, is_disabled FROM {$this->table} WHERE user_id = ?";
        $result = $peachySql->query($sql, [$id]);

        $options = $peachySql->options;
        $this->assertSame($options->affectedIsRowCount ? 1 : -1, $result->getAffected());
        $expected = ['user_id' => $id, 'is_disabled' => $options->nativeBoolColumns ? true : 1];
        $this->assertSame($expected, $result->getFirst()); // the row should be selectable

        $peachySql->rollback(); // cancel the transaction
        $sameRow = $peachySql->query($sql, [$id])->getFirst();
        $this->assertSame(null, $sameRow); // the row should no longer exist

        $peachySql->begin(); // start another transaction
        $newId = $peachySql->insertRow($this->table, $colVals)->id;
        $peachySql->commit(); // complete the transaction
        $newRow = $peachySql->selectFrom("SELECT user_id FROM {$this->table}")
            ->where(['user_id' => $newId])->query()->getFirst();

        $this->assertSame(['user_id' => $newId], $newRow); // the row should exist
    }

    public function testException(): void
    {
        $peachySql = static::dbProvider();
        $badQuery = 'SELECT * FROM nonExistentTable WHERE';

        try {
            $peachySql->query($badQuery); // should throw exception
            $this->fail('Bad query failed to throw exception');
        } catch (SqlException $e) {
            $this->assertSame($this->getExpectedBadSqlState(), $e->getSqlState());
            $this->assertSame($this->getExpectedBadSyntaxCode(), $e->getCode());
            $this->assertStringContainsString($this->getExpectedBadSyntaxError(), $e->getMessage());
        }
    }

    public function testBlob(): void
    {
        $db = static::dbProvider();
        $img = file_get_contents(__DIR__ . '/DevTheorem.png');

        if ($img === false) {
            throw new \Exception('Failed to read image');
        }

        $id = $db->insertRow($this->table, [
            'name' => 'DevTheorem',
            'dob' => '2024-10-24',
            'weight' => 0.0,
            'is_disabled' => false,
            'photo' => $db->makeBinaryParam($img),
        ])->id;

        /** @var array{photo: string|resource} $row */
        $row = $db->selectFrom("SELECT photo FROM {$this->table}")
            ->where(['user_id' => $id])->query()->getFirst();

        if ($db->options->binarySelectedAsStream) {
            /** @phpstan-ignore argument.type */
            $row['photo'] = stream_get_contents($row['photo']);
        }

        $this->assertSame(['photo' => $img], $row);
    }

    public function testIteratorQuery(): void
    {
        $peachySql = static::dbProvider();
        $options = $peachySql->options;

        $colVals = [
            ['name' => 'ElePHPant 🐘', 'dob' => '1995-06-08', 'weight' => 13558.43, 'is_disabled' => true, 'uuid' => Uuid::uuid4()->getBytes()],
            ['name' => 'Tux 🐧', 'dob' => '1991-09-17', 'weight' => 51.8, 'is_disabled' => false, 'uuid' => null],
        ];

        $insertColVals = [];

        foreach ($colVals as $row) {
            $row['uuid'] = $peachySql->makeBinaryParam($row['uuid']);
            $insertColVals[] = $row;
        }

        $ids = $peachySql->insertRows($this->table, $insertColVals)->ids;
        $sql = "SELECT user_id, name, dob, weight, is_disabled, uuid FROM {$this->table}";
        $iterator = $peachySql->selectFrom($sql)->where(['user_id' => $ids])->orderBy(['user_id'])->query()->getIterator();

        $this->assertInstanceOf(\Generator::class, $iterator);
        $colValsCompare = [];

        /** @var array{
         *     user_id: int, name: string, dob: string, weight: string|float,
         *     is_disabled: int|bool, uuid: string|null|resource
         * } $row
         */
        foreach ($iterator as $row) {
            unset($row['user_id']);

            if ($options->floatSelectedAsString) {
                $row['weight'] = (float) $row['weight'];
            }
            if (!$options->nativeBoolColumns) {
                $row['is_disabled'] = (bool) $row['is_disabled'];
            }
            if ($options->binarySelectedAsStream && $row['uuid'] !== null) {
                /** @phpstan-ignore argument.type */
                $row['uuid'] = stream_get_contents($row['uuid']);
            }

            $colValsCompare[] = $row;
        }

        $this->assertSame($colVals, $colValsCompare);

        // use a prepared statement to update both of the rows
        $sql = "UPDATE {$this->table} SET name = ?, uuid = ? WHERE user_id = ?";
        $_id = $_name = null;
        $_uuid = $peachySql->makeBinaryParam(null);
        $stmt = $peachySql->prepare($sql, [&$_name, &$_uuid, &$_id]);

        $realNames = [
            ['user_id' => $ids[0], 'name' => 'Rasmus Lerdorf', 'uuid' => Uuid::uuid4()->getBytes()],
            ['user_id' => $ids[1], 'name' => 'Linus Torvalds', 'uuid' => Uuid::uuid4()->getBytes()],
        ];

        foreach ($realNames as $_row) {
            $_id = $_row['user_id'];
            $_name = $_row['name'];
            $_uuid[0] = $_row['uuid'];
            $stmt->execute();
        }

        $stmt->close();

        $result = $peachySql->selectFrom("SELECT user_id, name, uuid FROM {$this->table}")
            ->where(['user_id' => $ids])->orderBy(['user_id'])->query();
        $updatedNames = $result->getAll();
        $this->assertSame($options->affectedIsRowCount ? 2 : -1, $result->getAffected());

        if ($options->binarySelectedAsStream) {
            /** @var array{uuid: resource} $row */
            foreach ($updatedNames as &$row) {
                $row['uuid'] = stream_get_contents($row['uuid']);
            }
        }

        $this->assertSame($realNames, $updatedNames);
    }

    public function testInsertBulk(): void
    {
        $peachySql = static::dbProvider();
        $rowCount = 525; // the number of rows to insert/update/delete
        $colVals = [];
        $dob = new \DateTime('1901-01-01');
        $oneDay = new \DateInterval('P1D');

        for ($i = 1; $i <= $rowCount; $i++) {
            $dob->add($oneDay);
            $colVals[] = [
                'name' => 'name' . $i,
                'dob' => $dob->format('Y-m-d'),
                'weight' => round(rand(1001, 2899) / 10, 1),
                'is_disabled' => 0,
                'uuid' => Uuid::uuid4()->getBytes(),
            ];
        }

        $insertColVals = [];
        foreach ($colVals as $row) {
            $row['uuid'] = $peachySql->makeBinaryParam($row['uuid']);
            $insertColVals[] = $row;
        }

        $options = $peachySql->options;
        $totalBoundParams = count($insertColVals[0]) * $rowCount;
        $expectedQueries = ($totalBoundParams > $options->maxBoundParams) ? 2 : 1;

        $result = $peachySql->insertRows($this->table, $insertColVals);
        $this->assertSame($expectedQueries, $result->queryCount);
        $this->assertSame($rowCount, $result->affected);
        $ids = $result->ids;
        $this->assertSame($rowCount, count($ids));
        $columns = implode(', ', array_keys($colVals[0]));

        $rows = $peachySql->selectFrom("SELECT {$columns} FROM {$this->table}")
            ->where(['user_id' => $ids])->orderBy(['user_id'])->query()->getAll();

        if ($options->binarySelectedAsStream || $options->nativeBoolColumns || $options->floatSelectedAsString) {
            /** @var array{weight: float|string, is_disabled: int|bool, uuid: string|resource} $row */
            foreach ($rows as &$row) {
                if ($options->floatSelectedAsString) {
                    $row['weight'] = (float) $row['weight'];
                }
                if ($options->nativeBoolColumns) {
                    $row['is_disabled'] = (int) $row['is_disabled'];
                }
                if ($options->binarySelectedAsStream) {
                    /** @phpstan-ignore argument.type */
                    $row['uuid'] = stream_get_contents($row['uuid']);
                }
            }
        }

        $this->assertSame($colVals, $rows);

        // update the inserted rows
        $numUpdated = $peachySql->updateRows($this->table, ['name' => 'updated'], ['user_id' => $ids]);
        $this->assertSame($rowCount, $numUpdated);

        // update a binary column
        $newUuid = Uuid::uuid4()->getBytes();
        $userId = $ids[0];
        $set = ['uuid' => $peachySql->makeBinaryParam($newUuid)];
        $peachySql->updateRows($this->table, $set, ['user_id' => $userId]);
        $updatedRow = $peachySql->selectFrom("SELECT uuid FROM {$this->table}")
            ->where(['user_id' => $userId])->query()->getFirst();

        if ($updatedRow === null) {
            throw new \Exception('Failed to select updated UUID');
        } elseif ($options->binarySelectedAsStream && is_resource($updatedRow['uuid'])) {
            $updatedRow['uuid'] = stream_get_contents($updatedRow['uuid']);
        }

        $this->assertSame($newUuid, $updatedRow['uuid']);

        // delete the inserted rows
        $numDeleted = $peachySql->deleteFrom($this->table, ['user_id' => $ids]);
        $this->assertSame($rowCount, $numDeleted);
    }

    private function countUsersNamed(string $name): int
    {
        /** @var array{c: int} $row */
        $row = static::dbProvider()->selectFrom("SELECT COUNT(*) AS c FROM {$this->table}")
            ->where(['name' => $name])->query()->getFirst();

        return $row['c'];
    }

    /**
     * Updates and deletes with more bound parameters than allowed are split into multiple queries.
     */
    public function testBatchedUpdateAndDelete(): void
    {
        $db = static::dbProvider();
        $options = $db->options;
        $maxBoundParams = $options->maxBoundParams;

        // use SQL Server's limit, so that it's exceeded without a huge number of rows
        $options->maxBoundParams = min($maxBoundParams, 2097);
        $rowCount = $options->maxBoundParams + 1;

        try {
            $colVals = array_fill(0, $rowCount, ['name' => 'unbatched', 'dob' => '2000-01-01', 'weight' => 1, 'is_disabled' => false]);
            $ids = $db->insertRows($this->table, $colVals, idColumn: 'user_id')->ids;

            // with the name being set, there's one more bound parameter than the limit
            $this->assertSame($rowCount, $db->updateRows($this->table, ['name' => 'batched'], ['user_id' => $ids]));
            $batched = $this->countUsersNamed('batched');
            $this->assertSame($rowCount, $batched);

            // a transaction started by the caller isn't committed
            $db->begin();
            $db->updateRows($this->table, ['name' => 'rolled back'], ['user_id' => $ids]);
            $db->rollback();
            $batched = $this->countUsersNamed('batched');
            $this->assertSame($rowCount, $batched);

            // MySQL doesn't fail when comparing an integer column to an invalid string
            if ($options->driver !== 'mysql') {
                try {
                    // the last batch fails, so the updates from the earlier batch should be rolled back
                    $db->updateRows($this->table, ['name' => 'failed'], ['user_id' => [...$ids, 'invalid']]);
                    $this->fail('updateRows failed to throw exception');
                } catch (SqlException $e) {
                    $batched = $this->countUsersNamed('batched');
                    $this->assertSame($rowCount, $batched);
                }
            }

            $this->assertSame($rowCount, $db->deleteFrom($this->table, ['user_id' => $ids, 'name' => 'batched']));
            $this->assertSame(0, $this->countUsersNamed('batched'));
        } finally {
            $options->maxBoundParams = $maxBoundParams;
        }
    }

    /**
     * The list of values for a column being set to null can be split, since null can't match a later batch.
     */
    public function testBatchedUpdateSettingColumnToNull(): void
    {
        $db = static::dbProvider();
        $db->query('DROP TABLE IF EXISTS NullBatchTest');
        $db->query('CREATE TABLE NullBatchTest (id INT PRIMARY KEY, code INT NULL)');
        $codes = range(1, 10);
        $db->insertRows('NullBatchTest', array_map(fn($code) => ['id' => $code, 'code' => $code], $codes));

        $options = $db->options;
        $maxBoundParams = $options->maxBoundParams;
        $options->maxBoundParams = 4; // with the value being set, the 10 codes are split into batches of 3
        $thrown = null;

        try {
            $this->assertSame(10, $db->updateRows('NullBatchTest', ['code' => null], ['code' => $codes]));

            try {
                // a non-null value could match a later batch, so the list can't be split
                $db->updateRows('NullBatchTest', ['code' => 0], ['code' => $codes]);
            } catch (\Exception $e) {
                $thrown = $e;
            }
        } finally {
            $options->maxBoundParams = $maxBoundParams;
        }

        $this->assertInstanceOf(\Exception::class, $thrown);
        $this->assertStringContainsString('can only be run as multiple queries', $thrown->getMessage());

        $expected = array_map(fn($id) => ['id' => $id, 'code' => null], $codes);
        $this->assertSame($expected, $db->query('SELECT id, code FROM NullBatchTest ORDER BY id')->getAll());
    }

    public function testEmptyBulkInsert(): void
    {
        $peachySql = static::dbProvider();
        $result = $peachySql->insertRows($this->table, []);
        $this->assertSame(0, $result->affected);
        $this->assertSame(0, $result->queryCount);
        $this->assertEmpty($result->ids);
    }

    public function testSelectFromBinding(): void
    {
        $peachySql = static::dbProvider();
        $row = ['name' => 'Test User', 'dob' => '2000-01-01', 'weight' => 123, 'is_disabled' => true];
        $id = $peachySql->insertRow($this->table, $row)->id;

        $result = $peachySql->select(new SqlParams("SELECT name, ? AS bound FROM {$this->table}", ['value']))
            ->where(['user_id' => $id])->query()->getFirst();

        $this->assertSame(['name' => 'Test User', 'bound' => 'value'], $result);

        // delete the inserted row
        $numDeleted = $peachySql->deleteFrom($this->table, ['user_id' => $id]);
        $this->assertSame(1, $numDeleted);
    }
}
