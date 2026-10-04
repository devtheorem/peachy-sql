<?php

namespace DevTheorem\PeachySQL;

/**
 * Object returned when inserting a single row
 */
class InsertResult
{
    /**
     * @param int $id The ID of the inserted row (only supported for integer ID columns)
     * @param int $affected The number of affected rows
     */
    public function __construct(
        public readonly int $id,
        public readonly int $affected,
    ) {}
}
