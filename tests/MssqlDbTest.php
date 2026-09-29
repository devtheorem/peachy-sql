<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\{PeachySql, SqlException};
use DevTheorem\PeachySQL\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('mssql')]
class MssqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;
    private static ?PDO $conn = null;

    protected function getExpectedBadSyntaxCode(): int
    {
        return 102;
    }

    protected function getExpectedBadSyntaxError(): string
    {
        return 'Incorrect syntax';
    }

    protected function getIdentityColumnDefinition(): string
    {
        return 'INT IDENTITY PRIMARY KEY';
    }

    protected function getTriggerTestSql(): array
    {
        return [
            'DROP TABLE IF EXISTS TriggerTest; DROP TABLE IF EXISTS TriggerAudit',
            'CREATE TABLE TriggerAudit (audit_id INT IDENTITY(5000, 1) PRIMARY KEY, note INT NOT NULL)',
            'CREATE TABLE TriggerTest (id INT IDENTITY PRIMARY KEY, name NVARCHAR(50) NOT NULL)',
            'CREATE TRIGGER TriggerTestAudit ON TriggerTest AFTER INSERT AS
                INSERT INTO TriggerAudit (note) SELECT id FROM inserted',
        ];
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
            self::$conn = self::createConnection();
            self::$db = self::createTestTable(new PeachySql(self::$conn));
        }

        return self::$db;
    }

    public function testExplicitIdentityValues(): void
    {
        $db = static::dbProvider();

        if (self::$conn === null) {
            throw new \Exception('Missing connection');
        }

        $db->query('DROP TABLE IF EXISTS ExplicitIdentity');
        $db->query('CREATE TABLE ExplicitIdentity (id INT IDENTITY PRIMARY KEY, name NVARCHAR(50) NOT NULL)');

        // IDENTITY_INSERT set in a prepared statement would only last until the statement completes
        self::$conn->exec('SET IDENTITY_INSERT ExplicitIdentity ON');

        try {
            // With this many rows in one statement, SQL Server sorts them by the clustered key before
            // inserting, so the OUTPUT rows are in ID order rather than the order of the inserted rows.
            $colVals = self::getShuffledIdRows(1000);
            $result = $db->insertRows('ExplicitIdentity', $colVals, idColumn: 'id');
            $this->assertSame(1, $result->queryCount);
            $this->assertSame(array_column($colVals, 'id'), $result->ids);
            $this->assertSame(1001, $db->insertRow('ExplicitIdentity', ['id' => 1001, 'name' => 'row1001'])->id);
        } finally {
            self::$conn->exec('SET IDENTITY_INSERT ExplicitIdentity OFF');
        }
    }

    public function testFetchErrorThrowsSqlException(): void
    {
        // without ORDER BY, rows are sent as they're computed, so the error on the last row occurs while fetching
        $sql = "SELECT CAST(CASE WHEN n = 20000 THEN 'x' ELSE CAST(n AS varchar(10)) END AS int) AS n
            FROM (SELECT TOP (20000) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
                FROM sys.all_objects a CROSS JOIN sys.all_objects b) t";

        $stmt = static::dbProvider()->query($sql);
        $fetched = 0;

        try {
            foreach ($stmt->getIterator() as $row) {
                $fetched++;
            }

            $this->fail('Failed to throw exception for fetch error');
        } catch (SqlException $e) {
            $this->assertGreaterThan(0, $fetched); // the error wasn't from executing the query
            $this->assertSame(245, $e->getCode());
            $this->assertStringContainsString('Conversion failed', $e->getMessage());
        }
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
