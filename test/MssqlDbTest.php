<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\Test\src\App;
use PDO;

/**
 * @group mssql
 */
class MssqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    protected function getExpectedBadSyntaxCode(): int
    {
        return 102;
    }

    protected function getExpectedBadSyntaxError(): string
    {
        return 'Incorrect syntax';
    }

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;
            $server = $c->mssqlServer;
            $connStr = getenv('MSSQL_CONNECTION_STRING');
            $username = $c->mssqlUsername;
            $password = $c->mssqlPassword;

            if ($connStr !== false) {
                // running tests with GitHub Actions
                $server = getenv('SQLCMDSERVER');
                if ($server === false) {
                    throw new \Exception('SQLCMDSERVER not set');
                }
                $envUsername = getenv('SQLCMDUSER');
                $envPassword = getenv('SQLCMDPASSWORD');

                if (is_string($envUsername) && is_string($envPassword)) {
                    $username = $envUsername;
                    $password = $envPassword;
                }
            }

            $pdo = new PDO("sqlsrv:Server=$server;Database=PeachySQL", $username, $password, [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
            ]);

            self::$db = self::createTestTable(new PeachySql($pdo));
        }

        return self::$db;
    }

    private static function createTestTable(PeachySql $db): PeachySql
    {
        $sql = "
            DROP TABLE IF EXISTS Users;
            CREATE TABLE Users (
                user_id INT PRIMARY KEY IDENTITY NOT NULL,
                name NVARCHAR(50) NOT NULL,
                dob DATE NOT NULL,
                weight FLOAT NOT NULL,
                is_disabled BIT NOT NULL,
                uuid BINARY(16) NULL,
                photo VARBINARY(max) NULL
            )";

        $db->query($sql);
        return $db;
    }
}
