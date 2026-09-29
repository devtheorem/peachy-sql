<?php

/**
 * Bulk-inserts rows from a separate process for DbTestCase::testConcurrentInsertIds(),
 * and outputs a JSON list of the IDs returned for each batch.
 */

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\Test\DbTestCase;

require __DIR__ . '/bootstrap.php';

if (!isset($argv) || count($argv) !== 7) {
    throw new Exception('Usage: php insert-worker.php <test class> <table> <worker> <batches> <rows per batch> <start time>');
}

[, $class, $table, $worker, $batches, $rowsPerBatch, $startTime] = $argv;

if (!is_subclass_of($class, DbTestCase::class)) {
    throw new Exception("{$class} is not a DbTestCase");
}

$db = new PeachySql($class::createConnection());
$ids = [];

// wait until every worker has connected, so the inserts run concurrently
if ((float) $startTime > microtime(true)) {
    time_sleep_until((float) $startTime);
}

for ($batch = 0; $batch < (int) $batches; $batch++) {
    $colVals = [];

    for ($seq = 0; $seq < (int) $rowsPerBatch; $seq++) {
        $colVals[] = ['worker' => (int) $worker, 'batch' => $batch, 'seq' => $seq];
    }

    $ids[] = $db->insertRows($table, $colVals, idColumn: 'id')->ids;
}

echo json_encode($ids);
