<?php

namespace PeachySQL;

/**
 * Tests for the PeachySQL library.
 * @author Theodore Brown <https://github.com/theodorejb>
 */
class PeachySQLTest extends \PHPUnit_Framework_TestCase {

    public function testBuildSelectQueryAllRows() {
        $actual = PeachySQL::buildSelectQuery("TestTable");
        $expected = "SELECT * FROM TestTable";
        $this->assertSame($expected, $actual["sql"]);
    }

    public function testBuildSelectQueryWhere() {
        $cols = ["username", "password"];

        $where = [
            "username" => "TestUser",
            "password" => "TestPassword",
            "othercol" => NULL
        ];

        $actual = PeachySQL::buildSelectQuery("TestTable", $cols, $where);
        $expected = "SELECT username, password FROM TestTable WHERE "
                  . "username = ? AND password = ? AND othercol IS NULL";
        $this->assertSame($expected, $actual["sql"]);
        $this->assertSame(['TestUser', 'TestPassword'], $actual["params"]);
    }

    public function testBuildUpdateQuery() {
        $set = [
            "username" => "TestUser",
            "othercol" => NULL
        ];

        $where = ["id" => 21];

        $actual = PeachySQL::buildUpdateQuery("TestTable", $set, $where);
        $expected = "UPDATE TestTable SET username = ?, othercol = ? "
                  . "WHERE id = ?";
        $this->assertSame($expected, $actual["sql"]);
        $this->assertSame(['TestUser', NULL, 21], $actual["params"]);
    }

    public function testBuildDeleteQuery() {
        $where = ["id" => 5, "username" => ["tester", "tester2"]];
        $actual = PeachySQL::buildDeleteQuery("TestTable", $where);
        $expected = "DELETE FROM TestTable WHERE id = ? "
                  . "AND username IN(?,?)";

        $this->assertSame($expected, $actual["sql"]);
        $this->assertSame([5, "tester", "tester2"], $actual["params"]);
    }

}
