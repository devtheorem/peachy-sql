<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('pgsql')]
class PgsqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    protected function getExpectedBadSyntaxCode(): int
    {
        return 7;
    }

    protected function getExpectedBadSyntaxError(): string
    {
        return 'syntax error';
    }

    protected function getExpectedBadSqlState(): string
    {
        return '42601';
    }

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;
            $dbName = getenv('POSTGRES_HOST') !== false ? 'postgres' : 'PeachySQL';

            $pdo = new PDO($c->getPgsqlDsn($dbName), $c->pgsqlUser, $c->pgsqlPassword, [
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
                user_id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                dob DATE NOT NULL,
                weight REAL NOT NULL,
                is_disabled BOOLEAN NOT NULL,
                uuid bytea NULL,
                photo bytea NULL
            )";

        $db->query("DROP TABLE IF EXISTS Users");
        $db->query($sql);
        return $db;
    }
}
