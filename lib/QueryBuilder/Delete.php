<?php

namespace PeachySQL\QueryBuilder;

/**
 * Class used for delete query generation
 * @author Theodore Brown <https://github.com/theodorejb>
 */
class Delete extends Query
{
    /**
     * Generates a delete query using the specified where clause
     *
     * @param string $table
     * @param array $where An array of columns/values to restrict the delete to
     * @param bool $escapeColumns
     * @return SqlParams
     */
    public function buildQuery($table, array $where, $escapeColumns = true)
    {
        $whereClause = $this->buildWhereClause($where, $escapeColumns);
        $sql = "DELETE FROM {$table}" . $whereClause->getSql();
        return new SqlParams($sql, $whereClause->getParams());
    }
}
