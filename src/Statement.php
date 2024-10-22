<?php

declare(strict_types=1);

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
     * @return \Generator<int, array>
     */
    public function getIterator(): \Generator
    {
        if ($this->stmt !== null) {
            while (
                /** @var array|false $row */
                $row = $this->stmt->fetch(PDO::FETCH_ASSOC)
            ) {
                yield $row;
            }

            if (!$this->usedPrepare) {
                $this->close();
            }
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
     * Returns all rows selected by the query
     */
    public function getAll(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * Returns the first selected row, or null if zero rows were returned
     */
    public function getFirst(): ?array
    {
        $row = $this->getIterator()->current();

        if ($row !== null) {
            $this->close(); // don't leave the SQL statement open
        }

        return $row;
    }

    /**
     * Returns the number of rows affected by the query
     */
    public function getAffected(): int
    {
        return $this->affected;
    }
}
