<?php

namespace DevTheorem\PeachySQL;

/**
 * Object returned when performing bulk insert queries
 */
class BulkInsertResult
{
    /**
     * @param list<int> $ids The IDs of the inserted rows
     * @param int $affected The number of affected rows
     * @param int $queryCount The number of individual queries used to perform the bulk insert
     */
    public function __construct(
        public readonly array $ids,
        public readonly int $affected,
        public readonly int $queryCount = 1,
    ) {}
}
