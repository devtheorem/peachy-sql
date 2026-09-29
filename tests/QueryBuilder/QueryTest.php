<?php

namespace DevTheorem\PeachySQL\Test\QueryBuilder;

use DevTheorem\PeachySQL\Options;
use DevTheorem\PeachySQL\QueryBuilder\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the base query builder
 */
class QueryTest extends TestCase
{
    public function testEscapeIdentifier(): void
    {
        $options = new Options();
        $query = new Query($options);
        $actual = $query->escapeIdentifier('Test"Identifier');
        $this->assertSame('"Test""Identifier"', $actual);

        try {
            $query->escapeIdentifier(''); // should throw exception
            $this->fail('escapeIdentifier failed to throw expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Identifier cannot be blank', $e->getMessage());
        }

        $options->identifierQuote = '`'; // test syntax for MySQL without ANSI_QUOTES enabled
        $actual = $query->escapeIdentifier('My`Identifier');
        $this->assertSame('`My``Identifier`', $actual);
    }

    public function testBatchWhere(): void
    {
        // the largest list is split, and the other conditions are included in each batch
        $where = ['a' => [1, 2], 'b' => [1, 2, 3, 4, 5], 'c' => 'x'];
        $expected = [
            ['a' => [1, 2], 'b' => [1, 2], 'c' => 'x'],
            ['a' => [1, 2], 'b' => [3, 4], 'c' => 'x'],
            ['a' => [1, 2], 'b' => [5], 'c' => 'x'],
        ];
        $this->assertSame($expected, Query::batchWhere($where, 8, 5));

        // an eq list can also be specified with an operator
        $where = ['a' => ['eq' => [1, 2, 3], 'ne' => 4]];
        $expected = [['a' => ['eq' => [1, 2], 'ne' => 4]], ['a' => ['eq' => [3], 'ne' => 4]]];
        $this->assertSame($expected, Query::batchWhere($where, 4, 3));

        // columns being updated aren't split
        $expected = [['a' => [1, 2, 3], 'b' => [1, 2]], ['a' => [1, 2, 3], 'b' => [3]]];
        $this->assertSame($expected, Query::batchWhere(['a' => [1, 2, 3], 'b' => [1, 2, 3]], 7, 6, ['a']));
    }

    /**
     * @return list<array{0: array<string, mixed>, 1: int, 2: int, 3?: list<string>}>
     */
    public static function unsplittableWhereCases(): array
    {
        return [
            [['a' => ['ne' => [1, 2, 3]]], 4, 3], // rows must be excluded by the whole list at once
            [['a' => ['lk' => ['x%', '%y', '%z%']]], 4, 3], // rows must match every value
            [['a' => [1, 2, 3]], 4, 3, ['a']], // the only list is for a column being updated
            [['a' => [1, 2], 'b' => ['ne' => [1, 2, 3, 4]]], 6, 3], // the ne list leaves no room to split
        ];
    }

    /**
     * @param array<string, mixed> $where
     * @param list<string> $excludedColumns
     */
    #[DataProvider('unsplittableWhereCases')]
    public function testUnsplittableWhere(array $where, int $paramCount, int $maxParams, array $excludedColumns = []): void
    {
        $this->expectExceptionMessage("The query has {$paramCount} bound parameters, which is more than the maximum of {$maxParams}");
        /** @phpstan-ignore argument.type */
        Query::batchWhere($where, $paramCount, $maxParams, $excludedColumns);
    }
}
