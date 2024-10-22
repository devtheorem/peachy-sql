<?php

declare(strict_types=1);

namespace DevTheorem\PeachySQL\QueryBuilder;

/**
 * Class used for update query generation
 * @psalm-import-type ColValues from Insert
 * @psalm-import-type WhereClause from Query
 */
class Update extends Query
{
    /**
     * Generates an update query using the specified set/where arrays
     * @param ColValues $set
     * @param WhereClause $where
     * @throws \Exception if the $set or $where arrays are empty
     */
    public function buildQuery(string $table, array $set, array $where): SqlParams
    {
        if (!$set || !$where) {
            throw new \Exception('Set and where arrays cannot be empty');
        }

        $params = [];
        $sql = "UPDATE {$table} SET ";

        /** @psalm-suppress MixedAssignment */
        foreach ($set as $column => $value) {
            $sql .= $this->escapeIdentifier($column) . ' = ?, ';
            $params[] = $value;
        }

        $sql = substr_replace($sql, '', -2); // remove trailing comma
        $whereClause = $this->buildWhereClause($where);
        $sql .= $whereClause->sql;

        return new SqlParams($sql, array_merge($params, $whereClause->params));
    }
}
