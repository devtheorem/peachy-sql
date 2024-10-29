<?php

namespace DevTheorem\PeachySQL\Test\QueryBuilder;

use DevTheorem\PeachySQL\Options;
use DevTheorem\PeachySQL\QueryBuilder\Update;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Update query builder
 */
class UpdateTest extends TestCase
{
    public function testBuildQuery(): void
    {
        $set = [
            'username' => 'TestUser',
            'othercol' => null,
        ];

        $where = ['id' => 21];

        $update = new Update(new Options());
        $actual = $update->buildQuery('TestTable', $set, $where);
        $expected = 'UPDATE TestTable SET "username" = ?, "othercol" = ? WHERE "id" = ?';

        $this->assertSame($expected, $actual->sql);
        $this->assertSame(['TestUser', null, 21], $actual->params);

        try {
            $update->buildQuery('TestTable', [], $where);
            $this->fail('Failed to throw exception for empty set array');
        } catch (\Exception $e) {
            $this->assertSame('Set and where arrays cannot be empty', $e->getMessage());
        }

        try {
            $update->buildQuery('TestTable', $set, []);
            $this->fail('Failed to throw exception for empty where array');
        } catch (\Exception $e) {
            $this->assertSame('Set and where arrays cannot be empty', $e->getMessage());
        }
    }
}
