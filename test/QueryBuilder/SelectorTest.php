<?php

namespace DevTheorem\PeachySQL\Test\QueryBuilder;

use DevTheorem\PeachySQL\Options;
use DevTheorem\PeachySQL\QueryBuilder\{Query, Select, Selector, SqlParams};
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Select query builder
 */
class SelectorTest extends TestCase
{
    public function testBoundSelect(): void
    {
        $query = 'SELECT column, ? FROM TestTable';
        $selector = new Selector(new SqlParams($query, ['value']), new Options());
        $actual = $selector->where(['column' => 10])->getSqlParams();
        $this->assertSame($query . ' WHERE "column" = ?', $actual->sql);
        $this->assertSame(['value', 10], $actual->params);
    }

    public function testWhere(): void
    {
        $query = 'SELECT "username", "password" FROM TestTable';
        $selector = new Selector(new SqlParams($query, []), new Options());

        $where = [
            'username' => 'TestUser',
            'lastname' => ['ne' => 'Brown'],
            'password' => ['eq' => ['TestPassword', 'Password123'], 'ne' => ['Password123']],
            'othercol' => ['nu' => ''],
            'escape"col' => ['nn' => ''],
            'type' => ['lk' => 'Admin%', 'nl' => 'Low%'],
            'firstname' => ['lk' => ['%nnet%', '%zabet%'], 'nl' => ['%dore', '%rah']],
            'datecol' => ['ge' => '2016-05-01', 'lt' => '2016-06-01'],
            'numcol' => ['gt' => 10, 'le' => 20],
        ];

        $actual = $selector->where($where)->getSqlParams();
        $expected = 'SELECT "username", "password" FROM TestTable WHERE '
            . '"username" = ? AND "lastname" <> ?'
            . ' AND "password" IN(?,?) AND "password" NOT IN(?)'
            . ' AND "othercol" IS NULL'
            . ' AND "escape""col" IS NOT NULL'
            . ' AND "type" LIKE ? AND "type" NOT LIKE ?'
            . ' AND "firstname" LIKE ? AND "firstname" LIKE ?'
            . ' AND "firstname" NOT LIKE ? AND "firstname" NOT LIKE ?'
            . ' AND "datecol" >= ? AND "datecol" < ?'
            . ' AND "numcol" > ? AND "numcol" <= ?';
        $this->assertSame($expected, $actual->sql);

        $params = ['TestUser', 'Brown', 'TestPassword', 'Password123', 'Password123', 'Admin%',
            'Low%', '%nnet%', '%zabet%', '%dore', '%rah', '2016-05-01', '2016-06-01', 10, 20];
        $this->assertSame($params, $actual->params);
    }

    public function testEmptyWhere(): void
    {
        $query = new Query(new Options());
        $result = $query->buildWhereClause([]);
        $this->assertSame('', $result->sql);
        $this->assertSame([], $result->params);
    }

    public function testInvalidWhere(): void
    {
        $query = new Query(new Options());

        try {
            $where = ['testcol' => []];
            $query->buildWhereClause($where);
            $this->fail('Failed to throw exception for empty filter conditions');
        } catch (\Exception $e) {
            $this->assertSame('Filter conditions cannot be empty for "testcol" column', $e->getMessage());
        }

        try {
            $where = ['testcol' => ['foo' => 'bar']];
            $query->buildWhereClause($where);
            $this->fail('Failed to throw exception for invalid comparison operator');
        } catch (\Exception $e) {
            $this->assertSame('foo is not a valid operator', $e->getMessage());
        }

        try {
            $query->buildWhereClause(['testcol' => null]);
            $this->fail('Failed to throw exception when using null value in where clause');
        } catch (\Exception $e) {
            $this->assertSame('Filter values cannot be null', $e->getMessage());
        }

        try {
            $where = ['testcol' => ['gt' => [3, 4]]];
            $query->buildWhereClause($where);
            $this->fail('Failed to throw exception when using array with an incompatible comparison operator');
        } catch (\Exception $e) {
            $this->assertSame('gt operator cannot be used with an array', $e->getMessage());
        }
    }

    public function testOrderBy(): void
    {
        $query = 'SELECT "username", "lastname" FROM TestTable';
        $select = new Selector(new SqlParams($query, []), new Options());
        $actual = $select->orderBy(['lastname', 'firstname'])->getSqlParams();

        $expected = $query . ' ORDER BY "lastname", "firstname"';
        $this->assertSame($expected, $actual->sql);
        $this->assertSame([], $actual->params);

        $orderBy = ['lastname' => 'asc', 'firstname' => 'asc', 'age' => 'desc'];
        $actual = (new Selector(new SqlParams($query, []), new Options()))->orderBy($orderBy)->getSqlParams();
        $this->assertSame($query . ' ORDER BY "lastname" ASC, "firstname" ASC, "age" DESC', $actual->sql);
    }

    public function testInvalidOrderBy(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('nonsense is not a valid sort direction for column "testcol". Use asc or desc.');
        $select = new Select(new Options());
        $select->buildOrderByClause(['testcol' => 'nonsense']);
    }

    public function testBuildPagination(): void
    {
        $select = new Select(new Options());
        $page1 = $select->buildPagination(25, 0);
        $page3 = $select->buildPagination(100, 200);
        $this->assertSame('LIMIT 25 OFFSET 0', $page1);
        $this->assertSame('LIMIT 100 OFFSET 200', $page3);

        $fetchNextOptions = new Options();
        $fetchNextOptions->fetchNextSyntax = true; // test syntax for SQL Server
        $select = new Select($fetchNextOptions);

        $page1 = $select->buildPagination(25, 0);
        $page3 = $select->buildPagination(100, 200);
        $this->assertSame('OFFSET 0 ROWS FETCH NEXT 25 ROWS ONLY', $page1);
        $this->assertSame('OFFSET 200 ROWS FETCH NEXT 100 ROWS ONLY', $page3);
    }

    public function testGetSqlParams(): void
    {
        $query = "SELECT * FROM MyTable a INNER JOIN AnotherTable b ON b.id = a.id";
        $selector = new Selector(new SqlParams($query, []), new Options());
        $result = $selector
            ->where(['a.id' => 1])
            ->orderBy(['a.username'])
            ->offset(10, 25)
            ->getSqlParams();

        $expected = $query . ' WHERE "a"."id" = ? ORDER BY "a"."username" LIMIT 25 OFFSET 10';
        $this->assertSame($expected, $result->sql);
        $this->assertSame([1], $result->params);
    }

    public function testInvalidPagination(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Results must be sorted to use an offset');
        $query = "SELECT * FROM MyTable";
        $selector = new Selector(new SqlParams($query, []), new Options());
        $selector->offset(0, 50)->getSqlParams();
    }
}
