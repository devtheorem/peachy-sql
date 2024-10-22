<?php

namespace DevTheorem\PeachySQL;

/**
 * Access the error info returned by the DB driver.
 */
class SqlException extends \RuntimeException
{
    /**
     * The five character alphanumeric identifier defined in the ANSI SQL-92 standard
     */
    private readonly string $sqlState;

    public function __construct(string $message, int $code, string $details, string $sqlState)
    {
        if ($details !== '') {
            $message .= ": $details";
        }

        parent::__construct($message, $code);
        $this->sqlState = $sqlState;
    }

    /**
     * Returns the five character alphanumeric identifier defined in the ANSI SQL-92 standard
     */
    public function getSqlState(): string
    {
        return $this->sqlState;
    }
}
