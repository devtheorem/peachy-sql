<?php

namespace DevTheorem\PeachySQL\Test\QueryBuilder;

use DevTheorem\PeachySQL\Options;
use DevTheorem\PeachySQL\QueryBuilder\Insert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Insert query builder
 */
class InsertTest extends TestCase
{
    /**
     * @return list<array{0: list<array<string, string>>, 1: int, 2: int, 3: list<mixed>}>
     */
    public static function batchRowsTestCases(): array
    {
        $colVals = [
            [
                'column1' => 'test',
                'column2' => 'test2',
            ],
            [
                'column1' => 'test3',
                'column2' => 'test4',
            ],
            [
                'column1' => 'test5',
                'column2' => 'test6',
            ],
            [
                'column1' => 'test7',
                'column2' => 'test8',
            ],
        ];

        $firstTwoRows = [$colVals[0], $colVals[1]];
        $lastTwoRows = [$colVals[2], $colVals[3]];
        $firstThreeRows = [$colVals[0], $colVals[1], $colVals[2]];
        $lastRow = [$colVals[3]];

        return [
            [[], 0, 0, []],
            [$colVals, 0, 0, [$colVals]], // one query
            [$colVals, 1000, 1000, [$colVals]], // one query
            [$colVals, 8, 4, [$colVals]], // one query
            [$colVals, 4, 0, [$firstTwoRows, $lastTwoRows]], // max of 4 bound params = 2 rows at a time
            [$colVals, 6, 1000, [$firstThreeRows, $lastRow]], // max of 6 bound params = 3 rows at a time
            [$colVals, 0, 3, [$firstThreeRows, $lastRow]], // 3 rows at a time max
            [$colVals, 6, 2, [$firstTwoRows, $lastTwoRows]], // six bound params max overridden by max of two rows
        ];
    }

    /**
     * @param list<array<string, string>> $colVals
     * @param list<list<array<string, string>>> $expected
     */
    #[DataProvider('batchRowsTestCases')]
    public function testBatchRows(array $colVals, int $maxParams, int $maxRows, array $expected): void
    {
        $result = Insert::batchRows($colVals, $maxParams, $maxRows);
        $this->assertSame($expected, $result);
    }

    public function testBuildQuery(): void
    {
        $colVals = [
            'col1' => 'val1',
            'col2' => 'val2',
            'col3' => 'val3',
        ];

        $actual = (new Insert(new Options()))->buildQuery('TestTable', [$colVals]);
        $expected = 'INSERT INTO TestTable ("col1", "col2", "col3") VALUES (?,?,?)';
        $this->assertSame($expected, $actual->sql);
        $this->assertSame(['val1', 'val2', 'val3'], $actual->params);
    }

    /**
     * Tests building an insert query with SCOPE_IDENTITY to retrieve the insert ID
     */
    public function testBuildQueryWithScopeIdentity(): void
    {
        $colVals = [
            [
                'col1' => 'foo1',
                'col2' => 'foo2',
            ],
            [
                'col1' => 'bar1',
                'col2' => 'bar2',
            ],
        ];

        $actual = (new Insert(new Options()))->buildQuery('TestTable', $colVals);
        $expected = 'INSERT INTO TestTable ("col1", "col2")'
            . ' VALUES (?,?), (?,?)';
        $this->assertSame($expected, $actual->sql);
        $this->assertSame(['foo1', 'foo2', 'bar1', 'bar2'], $actual->params);
    }
}
