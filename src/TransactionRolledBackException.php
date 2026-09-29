<?php

namespace DevTheorem\PeachySQL;

/**
 * Thrown by a nested call to transaction() when an error (e.g. a deadlock) rolled back the entire
 * transaction, so the changes made earlier in the outer transaction were also rolled back.
 * The original exception is available from getPrevious().
 */
class TransactionRolledBackException extends SqlException
{
    public function __construct(\Throwable $previous)
    {
        $sqlState = $previous instanceof SqlException ? $previous->getSqlState() : '';
        parent::__construct('The transaction was rolled back', (int) $previous->getCode(), $previous->getMessage(), $sqlState, $previous);
    }
}
