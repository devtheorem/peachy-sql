<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('mysql')]
class MysqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    protected function getExpectedBadSyntaxCode(): int
    {
        return 1064;
    }

    protected function getExpectedBadSyntaxError(): string
    {
        return 'error in your SQL syntax';
    }

    protected function getIdentityColumnDefinition(): string
    {
        return 'INT AUTO_INCREMENT PRIMARY KEY';
    }

    protected function getTriggerTestSql(): array
    {
        return [
            'DROP TABLE IF EXISTS TriggerTest',
            'DROP TABLE IF EXISTS TriggerAudit',
            'CREATE TABLE TriggerAudit (audit_id INT AUTO_INCREMENT PRIMARY KEY, note INT NOT NULL) AUTO_INCREMENT = 5000',
            'CREATE TABLE TriggerTest (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL)',
            'CREATE TRIGGER TriggerTestAudit AFTER INSERT ON TriggerTest FOR EACH ROW
                INSERT INTO TriggerAudit (note) VALUES (NEW.id)',
        ];
    }

    public static function createConnection(): PDO
    {
        $c = App::$config;

        return new PDO($c->mysqlDsn, $c->mysqlUser, $c->mysqlPassword, [
            PDO::ATTR_EMULATE_PREPARES => false,
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
            CREATE TABLE Users (
                user_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                name VARCHAR(50) NOT NULL,
                dob DATE NOT NULL,
                weight DOUBLE NOT NULL,
                is_disabled BOOLEAN NOT NULL,
                uuid BINARY(16) NULL,
                photo BLOB NULL
            )";

        $db->query("DROP TABLE IF EXISTS Users");
        $db->query($sql);
        return $db;
    }
}
