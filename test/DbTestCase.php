<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\QueryBuilder\SqlParams;
use DevTheorem\PeachySQL\{PeachySql, SqlException};
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

    abstract protected function getExpectedBadSyntaxCode(): int;

    abstract protected function getExpectedBadSyntaxError(): string;

    protected function getExpectedBadSqlState(): string
    {
        return '42000';
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
        $img = file_get_contents('test/DevTheorem.png');

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
        $iterator = $peachySql->selectFrom($sql)->where(['user_id' => $ids])->query()->getIterator();

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
            ->where(['user_id' => $ids])->query();
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
            ->where(['user_id' => $ids])->query()->getAll();

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
