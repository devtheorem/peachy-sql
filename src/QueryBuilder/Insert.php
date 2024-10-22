<?php

declare(strict_types=1);

namespace DevTheorem\PeachySQL\QueryBuilder;

/**
 * Class used for insert query generation
 * @psalm-type ColValues = array<string, mixed>
 */
class Insert extends Query
{
    /**
     * Returns the array of columns/values, split into groups containing the largest number of rows possible.
     * @param list<ColValues> $colVals
     * @return list<non-empty-list<ColValues>>
     */
    public static function batchRows(array $colVals, int $maxBoundParams, int $maxRows): array
    {
        $maxRowsPerQuery = count($colVals);

        if ($maxRowsPerQuery === 0) {
            return [];
        }

        if ($maxBoundParams > 0) {
            $maxRowsPerQuery = (int) floor($maxBoundParams / count($colVals[0])); // max bound params divided by params per row
        }

        if ($maxRows > 0 && $maxRowsPerQuery > $maxRows) {
            $maxRowsPerQuery = $maxRows;
        }

        return array_chunk($colVals, $maxRowsPerQuery);
    }

    /**
     * Generates an INSERT query with placeholders for values
     * @param list<ColValues> $colVals
     */
    public function buildQuery(string $table, array $colVals): SqlParams
    {
        self::validateColValsStructure($colVals);

        $columns = $this->escapeColumns(array_keys($colVals[0]));
        $insert = "INSERT INTO {$table} (" . implode(', ', $columns) . ')';

        $valSetStr = ' (' . str_repeat('?,', count($columns) - 1) . '?),';
        $valStr = ' VALUES' . substr_replace(str_repeat($valSetStr, count($colVals)), '', -1); // remove trailing comma
        $params = array_merge(...array_map('array_values', $colVals));

        return new SqlParams($insert . $valStr, $params);
    }

    /**
     * @throws \Exception if the column/values array does not have a valid structure
     */
    private static function validateColValsStructure(array $colVals): void
    {
        if (empty($colVals[0]) || !is_array($colVals[0])) {
            throw new \Exception('A valid array of columns/values to insert must be specified');
        }
    }
}
