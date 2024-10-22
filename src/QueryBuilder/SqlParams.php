<?php

namespace DevTheorem\PeachySQL\QueryBuilder;

/**
 * Represents a SQL query and its corresponding parameters
 */
class SqlParams
{
    /**
     * @param list<mixed> $params
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $params,
    ) {}
}
