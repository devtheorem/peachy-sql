<?php

namespace DevTheorem\PeachySQL\Test\QueryBuilder;

use DevTheorem\PeachySQL\Options;
use DevTheorem\PeachySQL\QueryBuilder\Delete;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Delete query builder
 */
class DeleteTest extends TestCase
{
    public function testBuildQuery(): void
    {
        $delete = new Delete(new Options());
        $where = ['id' => 5, 'username' => ['tester', 'tester2']];
        $actual = $delete->buildQuery('TestTable', $where);
        $expected = 'DELETE FROM TestTable WHERE "id" = ? AND "username" IN(?,?)';

        $this->assertSame($expected, $actual->sql);
        $this->assertSame([5, 'tester', 'tester2'], $actual->params);
    }
}
