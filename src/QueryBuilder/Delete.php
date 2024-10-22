<?php

declare(strict_types=1);

namespace DevTheorem\PeachySQL\QueryBuilder;

/**
 * Class used for delete query generation
 * @psalm-import-type WhereClause from Query
 */
class Delete extends Query
{
    /**
     * Generates a delete query with where clause for the specified table.
     * @param WhereClause $where
     */
    public function buildQuery(string $table, array $where): SqlParams
    {
        $whereClause = $this->buildWhereClause($where);
        $sql = "DELETE FROM {$table}" . $whereClause->sql;
        return new SqlParams($sql, $whereClause->params);
    }
}
