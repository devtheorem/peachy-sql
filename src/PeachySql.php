<?php

namespace DevTheorem\PeachySQL;

use Closure;
use DevTheorem\PeachySQL\QueryBuilder\{Delete, Insert, Query, SqlParams, Update};
use PDO;

/**
 * Simplifies building and running common queries.
 * @psalm-import-type WhereClause from QueryBuilder\Query
 * @psalm-import-type ColValues from Insert
 */
class PeachySql
{
    private const SAVEPOINT_PREFIX = 'peachy_savepoint_';

    public Options $options;
    private PDO $conn;
    private bool $usedPrepare = true;

    /** The number of nested transactions (savepoints) in the current transaction */
    private int $nestingLevel = 0;

    public function __construct(PDO $connection, ?Options $options = null)
    {
        $this->conn = $connection;

        if ($options === null) {
            /** @var string $driver */
            $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
            $options = new Options($driver);
        }

        $this->options = $options;
    }

    /**
     * Begins a transaction. If a transaction has already been started, a savepoint is created instead,
     * so that the matching commit() or rollback() only applies to the changes made after this call.
     * @throws SqlException if an error occurs
     */
    public function begin(): void
    {
        if ($this->conn->inTransaction()) {
            $savepoint = self::SAVEPOINT_PREFIX . ($this->nestingLevel + 1);
            $sql = $this->options->driver === 'sqlsrv' ? "SAVE TRANSACTION {$savepoint}" : "SAVEPOINT {$savepoint}";
            $this->execTransactionStatement($sql);
            $this->nestingLevel++;
            return;
        }

        // any savepoints from a transaction that wasn't ended with commit() or rollback() no longer exist
        $this->nestingLevel = 0;

        if (!$this->conn->beginTransaction()) {
            /** @phpstan-ignore argument.type */
            throw $this->getError('Failed to begin transaction', $this->conn->errorInfo());
        }
    }

    /**
     * Commits a transaction begun with begin(), or releases its savepoint if it was nested in another transaction.
     * @throws TransactionRolledBackException if the savepoint no longer exists, since an error rolled back
     *                                        the entire transaction (e.g. a deadlock with SQL Server or MySQL)
     * @throws SqlException if an error occurs
     */
    public function commit(): void
    {
        if ($this->nestingLevel > 0) {
            $savepoint = self::SAVEPOINT_PREFIX . $this->nestingLevel--;

            // SQL Server doesn't support releasing a savepoint
            if ($this->options->driver !== 'sqlsrv') {
                $this->endSavepoint("RELEASE SAVEPOINT {$savepoint}");
            }

            return;
        }

        if (!$this->conn->commit()) {
            /** @phpstan-ignore argument.type */
            throw $this->getError('Failed to commit transaction', $this->conn->errorInfo());
        }
    }

    /**
     * Rolls back a transaction begun with begin(), or only the changes made since then if it was nested
     * in another transaction.
     * @throws TransactionRolledBackException if the savepoint no longer exists, since an error rolled back
     *                                        the entire transaction (e.g. a deadlock with SQL Server or MySQL)
     * @throws SqlException if an error occurs
     */
    public function rollback(): void
    {
        if ($this->nestingLevel > 0) {
            $savepoint = self::SAVEPOINT_PREFIX . $this->nestingLevel--;
            $sqlsrv = $this->options->driver === 'sqlsrv';
            $this->endSavepoint($sqlsrv ? "ROLLBACK TRANSACTION {$savepoint}" : "ROLLBACK TO SAVEPOINT {$savepoint}");
            return;
        }

        if (!$this->conn->rollback()) {
            /** @phpstan-ignore argument.type */
            throw $this->getError('Failed to roll back transaction', $this->conn->errorInfo());
        }
    }

    /**
     * Runs the function in a transaction, which is committed if the function returns, or rolled back
     * if it throws an exception (which is then rethrown). If a transaction has already been started,
     * a savepoint is used instead, so only the changes made by the function are rolled back.
     * @template T
     * @param Closure(self): T $fn
     * @throws TransactionRolledBackException if the function is nested in another transaction, and throws an error
     *                                        which rolled back the entire transaction (e.g. due to a deadlock)
     * @throws SqlException if an error occurs when starting, committing, or rolling back the transaction
     * @return T The value returned by the function
     */
    public function transaction(Closure $fn): mixed
    {
        $nested = $this->conn->inTransaction();
        $this->begin();

        try {
            $result = $fn($this);
        } catch (\Throwable $e) {
            try {
                $this->rollback();
            } catch (TransactionRolledBackException) {
                // the outer transaction can't continue as if its earlier changes weren't also rolled back
                throw $e instanceof TransactionRolledBackException ? $e : new TransactionRolledBackException($e);
            } catch (\Throwable) {
                // the original exception is more useful (e.g. if the connection was lost)
            }

            throw $e;
        }

        try {
            $this->commit();
        } catch (\Throwable $e) {
            if (!$nested && $this->conn->inTransaction()) {
                try {
                    $this->rollback();
                } catch (\Throwable) {
                    // the original exception is more useful
                }
            }

            throw $e;
        }

        return $result;
    }

    /**
     * Releases or rolls back to a savepoint.
     * @throws TransactionRolledBackException if this fails, since the savepoint no longer exists
     */
    private function endSavepoint(string $sql): void
    {
        try {
            $this->execTransactionStatement($sql);
        } catch (SqlException $e) {
            throw new TransactionRolledBackException($e);
        }
    }

    /**
     * Savepoint statements are executed directly, since MySQL doesn't support them in prepared statements.
     * @throws SqlException if an error occurs
     */
    private function execTransactionStatement(string $sql): void
    {
        try {
            $success = $this->conn->exec($sql) !== false;
        } catch (\PDOException $e) {
            $success = false;
        }

        if (!$success) {
            /** @phpstan-ignore argument.type */
            throw $this->getError("Failed to execute {$sql}", $this->conn->errorInfo());
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

    /**
     * @param array{0: string, 1: int|null, 2: string|null} $error
     * @internal
     */
    public static function getError(string $message, array $error): SqlException
    {
        $code = $error[1] ?? 0;
        $details = $error[2] ?? '';
        $sqlState = $error[0];

        return new SqlException($message, $code, $details, $sqlState);
    }

    /**
     * Returns a prepared statement which can be executed multiple times.
     * @param list<mixed> $params
     * @throws SqlException if an error occurs
     */
    public function prepare(string $sql, array $params = []): Statement
    {
        try {
            if (!$stmt = $this->conn->prepare($sql)) {
                /** @phpstan-ignore argument.type */
                throw $this->getError('Failed to prepare statement', $this->conn->errorInfo());
            }

            $i = 0;
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
            /** @phpstan-ignore argument.type */
            throw $this->getError('Failed to prepare statement', $this->conn->errorInfo());
        }

        return new Statement($stmt, $this->usedPrepare, $this->options);
    }

    /**
     * Prepares and executes a single query with bound parameters.
     * @param list<mixed> $params
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
    private function insertBatch(string $table, array $colVals, int $identityIncrement, ?string $idColumn): BulkInsertResult
    {
        $insert = new Insert($this->options);
        $driver = $this->options->driver;

        // Unlike computing IDs from the last insert ID, returning the ID of each row from the
        // insert query itself is correct if other sessions insert into the same table concurrently.
        // With SQL Server, SCOPE_IDENTITY() is already correct for a single row.
        if ($idColumn !== null && $driver === 'sqlsrv' && count($colVals) > 1) {
            return $this->insertMergeOutput($insert->buildMergeOutputQuery($table, $colVals, $idColumn));
        } elseif ($idColumn !== null && $driver === 'pgsql') {
            return $this->insertReturningIds($insert->buildReturningQuery($table, $colVals, $idColumn));
        }

        if ($driver === 'sqlsrv') {
            // unlike lastInsertId() (which uses @@IDENTITY), SCOPE_IDENTITY() excludes IDs generated by triggers
            $sqlParams = $insert->buildScopeIdentityQuery($table, $colVals);
            /** @var array{id: int|string|null, affected: int|string} $row */
            $row = $this->query($sqlParams->sql, $sqlParams->params)->getFirst();
            $lastId = (int) $row['id'];
            $affected = (int) $row['affected'];
        } else {
            $sqlParams = $insert->buildQuery($table, $colVals);
            $affected = $this->query($sqlParams->sql, $sqlParams->params)->getAffected();

            try {
                $lastId = (int) $this->conn->lastInsertId();
            } catch (\PDOException $e) {
                $lastId = 0;
            }
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

        return new BulkInsertResult($ids, $affected);
    }

    private function insertReturningIds(SqlParams $sqlParams): BulkInsertResult
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = $this->query($sqlParams->sql, $sqlParams->params)->getAll();
        $ids = array_map(fn(array $row) => (int) $row['id'], $rows);
        return new BulkInsertResult($ids, count($ids));
    }

    private function insertMergeOutput(SqlParams $sqlParams): BulkInsertResult
    {
        /** @var array{ids: string|null} $row */
        $row = $this->query($sqlParams->sql, $sqlParams->params)->getFirst();
        /** @var list<array{id: int}> $idRows */
        $idRows = $row['ids'] === null ? [] : json_decode($row['ids'], true, flags: JSON_THROW_ON_ERROR);
        $ids = array_column($idRows, 'id');
        return new BulkInsertResult($ids, count($ids));
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
     * @param string|null $idColumn The identity column, to return the ID from the insert query with PostgreSQL
     *                              (the ID otherwise comes from lastval(), which may be for a different sequence)
     */
    public function insertRow(string $table, array $colVals, ?string $idColumn = null): InsertResult
    {
        $result = $this->insertBatch($table, [$colVals], 1, $idColumn);
        $ids = $result->ids;
        return new InsertResult($ids ? $ids[0] : 0, $result->affected);
    }

    /**
     * Insert multiple rows
     * @param list<ColValues> $colVals
     * @param int $identityIncrement Used to compute the IDs when they aren't returned by the insert query
     * @param string|null $idColumn The identity column, to return the IDs from the insert query with SQL Server
     *                              and PostgreSQL. Unlike computing the IDs from the last insert ID, this is
     *                              correct if other sessions insert concurrently.
     */
    public function insertRows(string $table, array $colVals, int $identityIncrement = 1, ?string $idColumn = null): BulkInsertResult
    {
        // check whether the query needs to be split into multiple batches
        $batches = Insert::batchRows($colVals, $this->options->maxBoundParams, $this->options->maxInsertRows);

        $insertBatches = function () use ($table, $batches, $identityIncrement, $idColumn): BulkInsertResult {
            $ids = [];
            $affected = 0;

            foreach ($batches as $batch) {
                $result = $this->insertBatch($table, $batch, $identityIncrement, $idColumn);
                $ids = array_merge($ids, $result->ids);
                $affected += $result->affected;
            }

            return new BulkInsertResult($ids, $affected, count($batches));
        };

        return count($batches) > 1 ? $this->transaction($insertBatches) : $insertBatches();
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

        // A column being set to a non-null value can't be split, since the rows updated by one batch could then match
        // a later batch. Null is safe, since it can't match a list of values (an IN condition is never true for null).
        $excludedColumns = array_keys(array_filter($set, fn($value) => $value !== null));
        return $this->runInBatches($where, fn(array $where) => $update->buildQuery($table, $set, $where), $excludedColumns);
    }

    /**
     * Deletes rows from the table matching the where clause
     * Returns the number of affected rows
     * @param WhereClause $where
     */
    public function deleteFrom(string $table, array $where): int
    {
        $delete = new Delete($this->options);
        return $this->runInBatches($where, fn(array $where) => $delete->buildQuery($table, $where));
    }

    /**
     * Runs the query built for the where clause, and returns the number of affected rows. If the query has
     * more bound parameters than allowed, it's split into multiple queries which are run with transaction().
     * @param WhereClause $where
     * @param Closure(WhereClause): SqlParams $buildQuery
     * @param string[] $excludedColumns Columns which can't be used to split the query
     */
    private function runInBatches(array $where, Closure $buildQuery, array $excludedColumns = []): int
    {
        $sqlParams = $buildQuery($where);
        $paramCount = count($sqlParams->params);
        $maxParams = $this->options->maxBoundParams;

        if ($maxParams <= 0 || $paramCount <= $maxParams) {
            return $this->runAffectingRows($sqlParams);
        }

        $batches = Query::batchWhere($where, $paramCount, $maxParams, $excludedColumns);

        return $this->transaction(function () use ($batches, $buildQuery): int {
            $affected = 0;

            foreach ($batches as $batchWhere) {
                $affected += $this->runAffectingRows($buildQuery($batchWhere));
            }

            return $affected;
        });
    }

    /**
     * Runs an update or delete query, and returns the number of affected rows. With SQL Server, the count is
     * selected from @@ROWCOUNT, since the query's row count would otherwise include rows changed by triggers.
     */
    private function runAffectingRows(SqlParams $sqlParams): int
    {
        if ($this->options->driver === 'sqlsrv') {
            /** @var array{affected: int|string} $row */
            $row = $this->query($sqlParams->sql . '; SELECT @@ROWCOUNT AS affected', $sqlParams->params)->getFirst();
            return (int) $row['affected'];
        }

        return $this->query($sqlParams->sql, $sqlParams->params)->getAffected();
    }
}
