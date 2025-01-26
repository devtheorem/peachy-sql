<?php

namespace DevTheorem\PeachySQL\QueryBuilder;

use DevTheorem\PeachySQL\Options;

/**
 * @psalm-import-type WhereClause from Query
 */
class Selector
{
    /** @var WhereClause */
    private array $where = [];
    /**
     * @var string[]
     */
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(
        private readonly SqlParams $query,
        private readonly Options $options,
    ) {}

    /**
     * @param WhereClause $filter
     * @throws \Exception if called more than once
     */
    public function where(array $filter): static
    {
        if ($this->where !== []) {
            throw new \Exception('where method can only be called once');
        }

        $this->where = $filter;
        return $this;
    }

    /**
     * @param mixed[] $sort
     * @throws \Exception if called more than once
     */
    public function orderBy(array $sort): static
    {
        if ($this->orderBy !== []) {
            throw new \Exception('orderBy method can only be called once');
        }

        foreach ($sort as $val) {
            if (!is_string($val)) {
                throw new \Exception('Invalid type for sort value: ' . get_debug_type($val));
            }
        }

        /** @var string[] $sort */
        $this->orderBy = $sort;
        return $this;
    }

    /**
     * @throws \Exception if a parameter is invalid
     */
    public function offset(int $offset, int $limit): static
    {
        if ($limit < 1) {
            throw new \Exception('Limit must be greater than zero');
        } elseif ($offset < 0) {
            throw new \Exception('Offset cannot be negative');
        }

        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * @throws \Exception if attempting to paginate unordered rows
     */
    public function getSqlParams(): SqlParams
    {
        $select = new Select($this->options);
        $where = $select->buildWhereClause($this->where);
        $orderBy = $select->buildOrderByClause($this->orderBy);
        $sql = $this->query->sql . $where->sql . $orderBy;

        if ($this->limit !== null && $this->offset !== null) {
            if ($this->orderBy === []) {
                throw new \Exception('Results must be sorted to use an offset');
            }

            $sql .= ' ' . $select->buildPagination($this->limit, $this->offset);
        }

        return new SqlParams($sql, [...$this->query->params, ...$where->params]);
    }
}
