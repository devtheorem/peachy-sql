<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\Test\src\App;
use PDO;

/**
 * @group mysql
 */
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

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;

            $pdo = new PDO($c->mysqlDsn, $c->mysqlUser, $c->mysqlPassword, [
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            self::$db = self::createTestTable(new PeachySql($pdo));
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
