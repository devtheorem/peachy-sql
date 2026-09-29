<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\{Options, SqlException, Statement};
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
    private function getStatement(array $fetchResults, string $errorCode): Statement
    {
        $pdoStmt = $this->createMock(PDOStatement::class);
        $pdoStmt->method('fetch')->willReturnCallback(function () use (&$fetchResults) {
            $result = array_shift($fetchResults);

            if ($result instanceof \Throwable) {
                throw $result;
            }

            return $result ?? false;
        });

        $pdoStmt->method('errorCode')->willReturn($errorCode);
        $pdoStmt->method('errorInfo')->willReturn($errorCode === '00000' ? ['00000', null, null] : self::ERROR_INFO);

        return new Statement($pdoStmt, true, new Options());
    }

    private function assertFetchError(Statement $stmt): void
    {
        try {
            $stmt->getAll();
            $this->fail('Failed to throw exception for fetch error');
        } catch (SqlException $e) {
            $this->assertSame('22018', $e->getSqlState());
            $this->assertSame(245, $e->getCode());
            $this->assertStringContainsString('Conversion failed', $e->getMessage());
        }
    }

    public function testExceptionWhileFetching(): void
    {
        $exception = new PDOException('SQLSTATE[22018]: Conversion failed');
        $this->assertFetchError($this->getStatement([['n' => 1], $exception], '22018'));
    }

    public function testSilentErrorWhileFetching(): void
    {
        // without the exception error mode, fetch() returns false
        $this->assertFetchError($this->getStatement([['n' => 1], false], '22018'));
    }

    public function testNoErrorAfterLastRow(): void
    {
        $this->assertSame([['n' => 1], ['n' => 2]], $this->getStatement([['n' => 1], ['n' => 2], false], '00000')->getAll());

        // warnings aren't errors
        $this->assertSame([['n' => 1]], $this->getStatement([['n' => 1], false], '01000')->getAll());
    }
}
