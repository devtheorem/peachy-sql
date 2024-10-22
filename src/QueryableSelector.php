<?php

declare(strict_types=1);

namespace DevTheorem\PeachySQL;

use DevTheorem\PeachySQL\QueryBuilder\{Selector, SqlParams};

class QueryableSelector extends Selector
{
    private PeachySql $peachySql;

    public function __construct(SqlParams $query, PeachySql $peachySql)
    {
        parent::__construct($query, $peachySql->options);
        $this->peachySql = $peachySql;
    }

    public function query(): Statement
    {
        $sqlParams = $this->getSqlParams();
        return $this->peachySql->query($sqlParams->sql, $sqlParams->params);
    }
}
