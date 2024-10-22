<?php

declare(strict_types=1);

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
            $server = $c->getSqlsrvServer();
            $connStr = getenv('MSSQL_CONNECTION_STRING');
            $username = '';
            $password = '';

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

            $pdo = new PDO("sqlsrv:server=$server", $username, $password, [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
                'Database' => 'PeachySQL',
            ]);

            self::$db = new PeachySql($pdo);
            self::createTestTable(self::$db);
        }

        return self::$db;
    }

    private static function createTestTable(PeachySql $db): void
    {
        $sql = "
            DROP TABLE IF EXISTS Users;
            CREATE TABLE Users (
                user_id INT PRIMARY KEY IDENTITY(1,1) NOT NULL,
                name NVARCHAR(50) NOT NULL,
                dob DATE NOT NULL,
                weight FLOAT NOT NULL,
                is_disabled BIT NOT NULL,
                uuid BINARY(16) NULL
            )";

        $db->query($sql);
    }
}
