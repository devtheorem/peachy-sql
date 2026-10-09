<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\{Options, SqlException, Statement};
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests for errors that occur while fetching rows from a Statement
 */
class StatementTest extends TestCase
{
    private const ERROR_INFO = ['22018', 245, 'Conversion failed when converting the varchar value to data type int.'];

    /**
     * @param list<mixed[]|false|\Throwable> $fetchResults
     */
    private function getStatement(array $fetchResults, string $errorCode, bool $usedPrepare = true): Statement
    {
        $pdoStmt = $this->createMock(PDOStatement::class);
        $fetch = function () use (&$fetchResults) {
            $result = array_shift($fetchResults);

            if ($result instanceof \Throwable) {
                throw $result;
            }

            return $result ?? false;
        };

        $pdoStmt->method('fetch')->willReturnCallback($fetch);
        $pdoStmt->method('fetchAll')->willReturnCallback(function (int $mode, mixed ...$args) use ($fetch) {
            $rows = [];

            while ($row = $fetch()) {
                $rows[] = match ([$mode, ...$args]) {
                    [PDO::FETCH_ASSOC] => $row,
                    [PDO::FETCH_COLUMN, 0] => array_values($row)[0],
                    default => $this->fail('Unexpected fetch mode'),
                };
            }

            return $rows;
        });

        $pdoStmt->method('errorCode')->willReturn($errorCode);
        $pdoStmt->method('errorInfo')->willReturn($errorCode === '00000' ? ['00000', null, null] : self::ERROR_INFO);
        $pdoStmt->expects($usedPrepare ? $this->never() : $this->once())->method('closeCursor');

        return new Statement($pdoStmt, $usedPrepare, new Options());
    }

    /**
     * Returns functions which fetch every row from a statement in each supported way.
     * @return array<string, \Closure(Statement): list<mixed>>
     */
    private static function getFetchMethods(): array
    {
        return [
            'getIterator' => fn(Statement $s) => iterator_to_array($s->getIterator(), false),
            'getAll' => fn(Statement $s) => $s->getAll(),
            'getColumn' => fn(Statement $s) => $s->getColumn(),
        ];
    }

    private function assertFetchError(Statement $stmt, \Closure $fetchRows, string $method): void
    {
        try {
            $fetchRows($stmt);
            $this->fail("{$method} failed to throw exception for fetch error");
        } catch (SqlException $e) {
            $this->assertSame('22018', $e->getSqlState());
            $this->assertSame(245, $e->getCode());
            $this->assertStringContainsString('Conversion failed', $e->getMessage());
        }
    }

    public function testExceptionWhileFetching(): void
    {
        $exception = new PDOException('SQLSTATE[22018]: Conversion failed');

        foreach (self::getFetchMethods() as $method => $fetchRows) {
            $this->assertFetchError($this->getStatement([['n' => 1], $exception], '22018'), $fetchRows, $method);
        }
    }

    public function testSilentErrorWhileFetching(): void
    {
        // without the exception error mode, fetch() returns false
        foreach (self::getFetchMethods() as $method => $fetchRows) {
            $this->assertFetchError($this->getStatement([['n' => 1], false], '22018'), $fetchRows, $method);
        }
    }

    public function testNoErrorAfterLastRow(): void
    {
        $rows = [['n' => 1, 's' => 'a'], ['n' => null, 's' => 'b'], ['n' => 3, 's' => 'c']];
        $expected = [
            'getIterator' => [$rows, [['n' => 1, 's' => 'a']], []],
            'getAll' => [$rows, [['n' => 1, 's' => 'a']], []],
            'getColumn' => [[1, null, 3], [1], []],
        ];

        foreach (self::getFetchMethods() as $method => $fetchRows) {
            [$all, $first, $none] = $expected[$method];
            $this->assertSame($all, $fetchRows($this->getStatement([...$rows, false], '00000')), $method);
            $this->assertSame($none, $fetchRows($this->getStatement([false], '00000')), $method);

            // warnings aren't errors
            $this->assertSame($first, $fetchRows($this->getStatement([$rows[0], false], '01000')), $method);
        }
    }

    public function testFetchingClosesUnpreparedStatement(): void
    {
        foreach (self::getFetchMethods() as $method => $fetchRows) {
            $stmt = $this->getStatement([['n' => 1], ['n' => 2], false], '00000', false);
            $this->assertCount(2, $fetchRows($stmt), $method);
            $this->assertSame([], $fetchRows($stmt), $method); // already closed
        }
    }
}
