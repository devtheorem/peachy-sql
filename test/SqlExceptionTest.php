<?php

namespace PeachySQL;

/**
 * Tests for the SqlException object
 * @author Theodore Brown <https://github.com/theodorejb>
 */
class SqlExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function exceptionProvider()
    {
        $errCode = 1193;
        $sqlState = 'HY000';
        $message = "Unknown system variable 'a'";
        $expectedMsg = "Error: $message";

        $mysql = [
            [
                'errno' => $errCode,
                'sqlstate' => $sqlState,
                'error' => $message,
            ]
        ];

        $sqlServer = [
            [
                'code' => $errCode,
                'SQLSTATE' => $sqlState,
                'message' => $message,
            ]
        ];

        return [
            [new SqlException('Error', $mysql), $expectedMsg, $sqlState, $errCode],
            [new SqlException('Error', $sqlServer), $expectedMsg, $sqlState, $errCode],
        ];
    }

    /**
     * @dataProvider exceptionProvider
     */
    public function testDataAccess(SqlException $e, $expectedMsg, $sqlState, $errCode)
    {
        $this->assertSame($expectedMsg, $e->getMessage());
        $this->assertSame($sqlState, $e->getSqlState());
        $this->assertSame($errCode, $e->getCode());
    }
}