<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('mssql')]
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

    public static function createConnection(): PDO
    {
        // set when running tests with GitHub Actions
        $server = getenv('SQLCMDSERVER');
        $username = getenv('SQLCMDUSER');
        $password = getenv('SQLCMDPASSWORD');

        if ($server === false || $username === false || $password === false) {
            $c = App::$config;
            $server = $c->mssqlServer;
            $username = $c->mssqlUsername;
            $password = $c->mssqlPassword;
        }

        // ODBC Driver 18 encrypts connections by default, and test servers generally use a self-signed certificate
        $dsn = "sqlsrv:Server=$server;Database=PeachySQL;TrustServerCertificate=1";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
        ]);
    }

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            self::$db = self::createTestTable(new PeachySql(self::createConnection()));
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
