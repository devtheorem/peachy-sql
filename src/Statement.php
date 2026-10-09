<?php

namespace DevTheorem\PeachySQL;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Get rows and affected count for a completed SQL query
 */
class Statement
{
    protected int $affected = 0;
    private ?PDOStatement $stmt;

    /**
     * @param bool $usedPrepare True if the statement was created using `prepare()`
     */
    public function __construct(
        PDOStatement $stmt,
        private readonly bool $usedPrepare,
        private readonly Options $options,
    ) {
        $this->stmt = $stmt;
    }

    /**
     * Executes the prepared statement
     */
    public function execute(): void
    {
        if ($this->stmt === null) {
            throw new \Exception('Cannot execute closed statement');
        }

        try {
            if (!$this->stmt->execute()) {
                throw PeachySql::getError('Failed to execute prepared statement', $this->stmt->errorInfo());
            }
        } catch (PDOException $e) {
            throw PeachySql::getError('Failed to execute prepared statement', $this->stmt->errorInfo());
        }

        $this->affected = 0;
        $multiRowset = $this->options->multiRowset;

        do {
            $this->affected += $this->stmt->rowCount();
            $hasResultSet = $this->stmt->columnCount() !== 0;

            if ($hasResultSet) {
                break; // so that getIterator will be able to select the rows
            }
        } while ($multiRowset && $this->stmt->nextRowset());

        if (!$this->usedPrepare && !$hasResultSet) {
            $this->close(); // no results, so statement can be closed
        }
    }

    /**
     * Returns an iterator which can be used to loop through each row in the result
     * @return \Generator<int, mixed[]>
     */
    public function getIterator(): \Generator
    {
        $stmt = $this->stmt;

        if ($stmt !== null) {
            // rows may be streamed as they're computed, so an error can occur while fetching them
            try {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    yield $row;
                }
            } catch (PDOException $e) {
                throw PeachySql::getError('Failed to fetch row', $stmt->errorInfo());
            }

            $this->finishFetch($stmt);
        }
    }

    /**
     * Throws if fetching rows failed without an exception, and closes the statement if it wasn't prepared.
     */
    private function finishFetch(PDOStatement $stmt): void
    {
        // Without the exception error mode, fetching returns false on error just like when there are no more rows.
        // SQLSTATE classes 00, 01, and 02 indicate success, a warning, and no data, respectively.
        $sqlState = (string) $stmt->errorCode();

        if (!in_array(substr($sqlState, 0, 2), ['', '00', '01', '02'], true)) {
            throw PeachySql::getError('Failed to fetch row', $stmt->errorInfo());
        }

        if (!$this->usedPrepare) {
            $this->close();
        }
    }

    /**
     * Closes the prepared statement and deallocates the statement handle.
     * @throws \Exception if the statement has already been closed
     */
    public function close(): void
    {
        if ($this->stmt === null) {
            throw new \Exception('Statement has already been closed');
        }

        $this->stmt->closeCursor();
        $this->stmt = null;
    }

    /**
     * Returns all rows selected by the query.
     * @return list<mixed[]>
     */
    public function getAll(): array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the first selected row, or null if zero rows were returned.
     * @return mixed[]|null
     */
    public function getFirst(): ?array
    {
        $row = $this->getIterator()->current();

        /** @phpstan-ignore notIdentical.alwaysTrue */
        if ($row !== null) {
            $this->close(); // don't leave the SQL statement open
        }

        return $row;
    }

    /**
     * Returns the values of the first selected column from every row. This is faster and uses
     * less memory than calling `array_column()` on the result of `getAll()`.
     * @return list<mixed>
     */
    final public function getColumn(): array
    {
        return $this->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Fetches all remaining rows in a single call, which is faster than fetching them one at a time.
     * @return ($mode is PDO::FETCH_ASSOC ? list<mixed[]> : list<mixed>)
     */
    private function fetchAll(int $mode, mixed ...$args): array
    {
        $stmt = $this->stmt;

        if ($stmt === null) {
            return [];
        }

        try {
            /** @var list<mixed> $rows */
            $rows = $stmt->fetchAll($mode, ...$args);
        } catch (PDOException $e) {
            throw PeachySql::getError('Failed to fetch row', $stmt->errorInfo());
        }

        $this->finishFetch($stmt);
        return $rows;
    }

    /**
     * Returns the number of rows affected by the query
     */
    public function getAffected(): int
    {
        return $this->affected;
    }
}
