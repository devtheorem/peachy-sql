<?php

declare(strict_types=1);

namespace DevTheorem\PeachySQL;

use DevTheorem\PeachySQL\QueryBuilder\{Delete, Insert, SqlParams, Update};
use PDO;

/**
 * Simplifies building and running common queries.
 * @psalm-import-type WhereClause from QueryBuilder\Query
 * @psalm-import-type ColValues from Insert
 */
class PeachySql
{
    public Options $options;
    private PDO $conn;
    private bool $usedPrepare;

    public function __construct(PDO $connection, ?Options $options = null)
    {
        $this->conn = $connection;
        $this->usedPrepare = true;

        if ($options === null) {
            $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
            $options = new Options();

            if ($driver === 'sqlsrv') {
                // https://learn.microsoft.com/en-us/sql/sql-server/maximum-capacity-specifications-for-sql-server
                $options->maxBoundParams = 2100 - 1;
                $options->maxInsertRows = 1000;
                $options->affectedIsRowCount = false;
                $options->fetchNextSyntax = true;
                $options->sqlsrvBinaryEncoding = true;
                $options->multiRowset = true;
            } elseif ($driver === 'mysql') {
                $options->lastIdIsFirstOfBatch = true;
                $options->identifierQuote = '`'; // needed since not everyone uses ANSI mode
            } elseif ($driver === 'pgsql') {
                $options->binarySelectedAsStream = true;
                $options->nativeBoolColumns = true;
                $options->floatSelectedAsString = true;
            }
        }

        $this->options = $options;
    }

    /**
     * Begins a transaction
     * @throws SqlException if an error occurs
     */
    public function begin(): void
    {
        if (!$this->conn->beginTransaction()) {
            throw $this->getError('Failed to begin transaction', $this->conn->errorInfo());
        }
    }

    /**
     * Commits a transaction begun with begin()
     * @throws SqlException if an error occurs
     */
    public function commit(): void
    {
        if (!$this->conn->commit()) {
            throw $this->getError('Failed to commit transaction', $this->conn->errorInfo());
        }
    }

    /**
     * Rolls back a transaction begun with begin()
     * @throws SqlException if an error occurs
     */
    public function rollback(): void
    {
        if (!$this->conn->rollback()) {
            throw $this->getError('Failed to roll back transaction', $this->conn->errorInfo());
        }
    }

    /**
     * Takes a binary string and returns a value that can be bound to an insert/update statement
     * @return array{0: string|null, 1: int, 2: int, 3: mixed}
     */
    final public function makeBinaryParam(?string $binaryStr): array
    {
        $driverOptions = $this->options->sqlsrvBinaryEncoding ? PDO::SQLSRV_ENCODING_BINARY : null;
        return [$binaryStr, PDO::PARAM_LOB, 0, $driverOptions];
    }

    /** @internal */
    public static function getError(string $message, array $error): SqlException
    {
        /** @var array{0: string, 1: int|null, 2: string|null} $error */
        $code = $error[1] ?? 0;
        $details = $error[2] ?? '';
        $sqlState = $error[0];

        return new SqlException($message, $code, $details, $sqlState);
    }

    /**
     * Returns a prepared statement which can be executed multiple times
     * @throws SqlException if an error occurs
     */
    public function prepare(string $sql, array $params = []): Statement
    {
        try {
            if (!$stmt = $this->conn->prepare($sql)) {
                throw $this->getError('Failed to prepare statement', $this->conn->errorInfo());
            }

            $i = 0;
            /** @psalm-suppress MixedAssignment */
            foreach ($params as &$param) {
                $i++;

                if (is_bool($param)) {
                    $stmt->bindParam($i, $param, PDO::PARAM_BOOL);
                } elseif (is_int($param)) {
                    $stmt->bindParam($i, $param, PDO::PARAM_INT);
                } elseif (is_array($param)) {
                    /** @var array{0: mixed, 1: int, 2?: int, 3?: mixed} $param */
                    $stmt->bindParam($i, $param[0], $param[1], $param[2] ?? 0, $param[3] ?? null);
                } else {
                    $stmt->bindParam($i, $param, PDO::PARAM_STR);
                }
            }
        } catch (\PDOException $e) {
            throw $this->getError('Failed to prepare statement', $this->conn->errorInfo());
        }

        return new Statement($stmt, $this->usedPrepare, $this->options);
    }

    /**
     * Prepares and executes a single query with bound parameters
     */
    public function query(string $sql, array $params = []): Statement
    {
        $this->usedPrepare = false;
        $stmt = $this->prepare($sql, $params);
        $this->usedPrepare = true;
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param list<ColValues> $colVals
     */
    private function insertBatch(string $table, array $colVals, int $identityIncrement = 1): BulkInsertResult
    {
        $sqlParams = (new Insert($this->options))->buildQuery($table, $colVals);
        $result = $this->query($sqlParams->sql, $sqlParams->params);

        try {
            $lastId = (int) $this->conn->lastInsertId();
        } catch (\PDOException $e) {
            $lastId = 0;
        }

        if ($lastId) {
            if ($this->options->lastIdIsFirstOfBatch) {
                $firstId = $lastId;
                $lastId = $firstId + $identityIncrement * (count($colVals) - 1);
            } else {
                $firstId = $lastId - $identityIncrement * (count($colVals) - 1);
            }

            $ids = range($firstId, $lastId, $identityIncrement);
        } else {
            $ids = [];
        }

        return new BulkInsertResult($ids, $result->getAffected());
    }

    public function selectFrom(string $query): QueryableSelector
    {
        return new QueryableSelector(new SqlParams($query, []), $this);
    }

    public function select(SqlParams $query): QueryableSelector
    {
        return new QueryableSelector($query, $this);
    }

    /**
     * Inserts one row
     * @param ColValues $colVals
     */
    public function insertRow(string $table, array $colVals): InsertResult
    {
        $result = $this->insertBatch($table, [$colVals]);
        $ids = $result->ids;
        return new InsertResult($ids ? $ids[0] : 0, $result->affected);
    }

    /**
     * Insert multiple rows
     * @param list<ColValues> $colVals
     */
    public function insertRows(string $table, array $colVals, int $identityIncrement = 1): BulkInsertResult
    {
        // check whether the query needs to be split into multiple batches
        $batches = Insert::batchRows($colVals, $this->options->maxBoundParams, $this->options->maxInsertRows);
        $ids = [];
        $affected = 0;

        foreach ($batches as $batch) {
            $result = $this->insertBatch($table, $batch, $identityIncrement);
            $ids = array_merge($ids, $result->ids);
            $affected += $result->affected;
        }

        return new BulkInsertResult($ids, $affected, count($batches));
    }

    /**
     * Updates the specified columns and values in rows matching the where clause
     * Returns the number of affected rows
     * @param ColValues $set
     * @param WhereClause $where
     */
    public function updateRows(string $table, array $set, array $where): int
    {
        $update = new Update($this->options);
        $sqlParams = $update->buildQuery($table, $set, $where);
        return $this->query($sqlParams->sql, $sqlParams->params)->getAffected();
    }

    /**
     * Deletes rows from the table matching the where clause
     * Returns the number of affected rows
     * @param WhereClause $where
     */
    public function deleteFrom(string $table, array $where): int
    {
        $delete = new Delete($this->options);
        $sqlParams = $delete->buildQuery($table, $where);
        return $this->query($sqlParams->sql, $sqlParams->params)->getAffected();
    }
}
