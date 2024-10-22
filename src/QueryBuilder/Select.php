<?php

declare(strict_types=1);

namespace DevTheorem\PeachySQL\QueryBuilder;

/**
 * Class used for select query generation
 */
class Select extends Query
{
    /**
     * @throws \Exception if there is an invalid sort direction
     */
    public function buildOrderByClause(array $orderBy): string
    {
        if (!$orderBy) {
            return '';
        }

        $sql = ' ORDER BY ';

        // [column1, column2, ...]
        if (isset($orderBy[0])) {
            /** @var array<int, string> $orderBy */
            return $sql . implode(', ', $this->escapeColumns($orderBy));
        }

        /** @var array<string, string> $orderBy */
        // [column1 => direction, column2 => direction, ...]
        foreach ($orderBy as $column => $direction) {
            $column = $this->escapeIdentifier($column);
            $sql .= $column;

            if ($direction === 'asc') {
                $sql .= ' ASC, ';
            } elseif ($direction === 'desc') {
                $sql .= ' DESC, ';
            } else {
                throw new \Exception("{$direction} is not a valid sort direction for column {$column}. Use asc or desc.");
            }
        }

        return substr_replace($sql, '', -2); // remove trailing comma and space
    }

    public function buildPagination(int $limit, int $offset): string
    {
        if ($this->options->fetchNextSyntax) {
            return "OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
        } else {
            return "LIMIT {$limit} OFFSET {$offset}";
        }
    }
}
