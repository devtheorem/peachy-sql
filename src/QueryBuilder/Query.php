<?php

namespace DevTheorem\PeachySQL\QueryBuilder;

use DevTheorem\PeachySQL\Options;

/**
 * Base class used for query generation and validation
 * @psalm-type WhereVal = int|float|bool|string|null
 * @psalm-type OptWhereList = WhereVal | list<WhereVal>
 * @psalm-type WhereClause = array<string, OptWhereList | array<string, OptWhereList>>
 */
class Query
{
    private const OPERATOR_MAP = [
        'eq' => '=',
        'ne' => '<>',
        'lt' => '<',
        'le' => '<=',
        'gt' => '>',
        'ge' => '>=',
        'lk' => 'LIKE',
        'nl' => 'NOT LIKE',
        'nu' => 'IS NULL',
        'nn' => 'IS NOT NULL',
    ];

    protected Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    protected function escapeColumns(array $columns): array
    {
        return array_map($this->escapeIdentifier(...), $columns);
    }

    /**
     * Escapes a table or column name, and validates that it isn't blank
     */
    public function escapeIdentifier(string $identifier): string
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException('Identifier cannot be blank');
        }

        $qualifiedIdentifiers = array_map($this->quoteIdentifier(...), explode('.', $identifier));
        return implode('.', $qualifiedIdentifiers);
    }

    /**
     * Quotes a single identifier (which may contain periods)
     */
    protected function quoteIdentifier(string $identifier): string
    {
        $c = $this->options->identifierQuote;
        return $c . str_replace($c, $c . $c, $identifier) . $c;
    }

    /**
     * Splits the largest list of values for an eq condition into batches, so that a query with more
     * bound parameters than allowed can be run as multiple queries. Only eq lists can be split, since
     * each row matches at most one batch of values for a column (whereas ne, lk, and nl lists must
     * all be matched by the same query).
     * @param WhereClause $where
     * @param string[] $excludedColumns Columns which can't be split, e.g. ones being updated to a non-null value
     *                                  (since an updated row could then match a later batch)
     * @throws \Exception if there's no list which can be split to fit within the limit
     * @return list<WhereClause>
     */
    public static function batchWhere(array $where, int $paramCount, int $maxParams, array $excludedColumns = []): array
    {
        $splitColumn = null;
        $splitList = [];

        foreach ($where as $column => $value) {
            if (in_array($column, $excludedColumns, true) || !is_array($value)) {
                continue;
            }

            $list = isset($value[0]) ? $value : ($value['eq'] ?? null);

            if (is_array($list) && count($list) > count($splitList)) {
                /** @var list<WhereVal> $list */
                $splitColumn = $column;
                $splitList = $list;
            }
        }

        // the other bound parameters are included in every batch
        $batchSize = $maxParams - ($paramCount - count($splitList));

        if ($splitColumn === null || $batchSize < 1) {
            throw new \Exception("The query has {$paramCount} bound parameters, which is more than the maximum of {$maxParams}."
                . ' It can only be run as multiple queries if the where clause has a list of values to match'
                . ' (for a column which isn\'t being set to a non-null value) that is long enough to be split within the limit.');
        }

        $batches = [];

        foreach (array_chunk($splitList, $batchSize) as $chunk) {
            $batchWhere = $where;

            if (isset($where[$splitColumn][0])) {
                $batchWhere[$splitColumn] = $chunk;
            } else {
                /** @var array<string, OptWhereList> $conditions */
                $conditions = $where[$splitColumn];
                $conditions['eq'] = $chunk;
                $batchWhere[$splitColumn] = $conditions;
            }

            $batches[] = $batchWhere;
        }

        return $batches;
    }

    /**
     * @param WhereClause $columnVals
     * @throws \Exception if a column filter is empty
     */
    public function buildWhereClause(array $columnVals): SqlParams
    {
        if (!$columnVals) {
            return new SqlParams('', []);
        }

        $conditions = [];
        $params = [];

        foreach ($columnVals as $column => $value) {
            $column = $this->escapeIdentifier($column);

            if (is_array($value) && count($value) === 0) {
                throw new \Exception("Filter conditions cannot be empty for {$column} column");
            } elseif (!is_array($value) || isset($value[0])) {
                // same as eq operator - handle below
                /** @var array<string, OptWhereList> $value */
                $value = ['eq' => $value];
            }

            foreach ($value as $shorthand => $val) {
                if (!isset(self::OPERATOR_MAP[$shorthand])) {
                    throw new \Exception("{$shorthand} is not a valid operator");
                }

                if ($val === null) {
                    throw new \Exception('Filter values cannot be null');
                } elseif ($shorthand === 'nu' || $shorthand === 'nn') {
                    if ($val !== '') {
                        throw new \Exception("{$shorthand} operator can only be used with a blank value");
                    }

                    $conditions[] = $column . ' ' . self::OPERATOR_MAP[$shorthand];
                } elseif (!is_array($val)) {
                    $comparison = self::OPERATOR_MAP[$shorthand];
                    $conditions[] = "{$column} {$comparison} ?";
                    $params[] = $val;
                } elseif ($shorthand === 'eq' || $shorthand === 'ne') {
                    // use IN(...) syntax
                    $conditions[] = $column . ($shorthand === 'ne' ? ' NOT IN(' : ' IN(')
                        . str_repeat('?,', count($val) - 1) . '?)';
                    $params = [...$params, ...$val];
                } elseif ($shorthand === 'lk' || $shorthand === 'nl') {
                    foreach ($val as $condition) {
                        $conditions[] = $column . ' ' . self::OPERATOR_MAP[$shorthand] . ' ?';
                        $params[] = $condition;
                    }
                } else {
                    // it doesn't make sense to use greater than or less than operators with multiple values
                    throw new \Exception("{$shorthand} operator cannot be used with an array");
                }
            }
        }

        return new SqlParams(' WHERE ' . implode(' AND ', $conditions), $params);
    }
}
