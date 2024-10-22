<?php

namespace DevTheorem\PeachySQL;

/**
 * Base class for PeachySQL configuration state
 */
class Options
{
    /**
     * The maximum number of parameters which can be bound in a single query.
     * If greater than zero, PeachySQL will batch insert queries to avoid the limit.
     */
    public int $maxBoundParams = 65_535; // MySQL and PostgreSQL use 16-bit int for param count

    /**
     * The maximum number of rows which can be inserted via a single query.
     * If greater than zero, PeachySQL will batch insert queries to avoid the limit.
     */
    public int $maxInsertRows = 0;

    public bool $affectedIsRowCount = true;
    public bool $lastIdIsFirstOfBatch = false;
    public bool $fetchNextSyntax = false;
    public bool $multiRowset = false;
    public bool $sqlsrvBinaryEncoding = false;
    public bool $binarySelectedAsStream = false;
    public bool $nativeBoolColumns = false;
    public bool $floatSelectedAsString = false;

    /**
     * The character used to quote identifiers.
     */
    public string $identifierQuote = '"';
}
