<?php

namespace DevTheorem\PeachySQL;

/**
 * Object returned when inserting a single row
 */
class InsertResult
{
    /**
     * @param int $id The ID of the inserted row (0 if the row doesn't have an auto-incremented ID)
     * @param int $affected The number of affected rows
     */
    public function __construct(
        public readonly int $id,
        public readonly int $affected,
    ) {}
}
