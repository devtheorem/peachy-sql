<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\BulkInsertResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the BulkInsertResult object
 */
class BulkInsertResultTest extends TestCase
{
    public function testCreateRetrieve(): void
    {
        $result = new BulkInsertResult([48, 49, 50], 6, 2);
        $this->assertSame([48, 49, 50], $result->ids);
        $this->assertSame(6, $result->affected);
        $this->assertSame(2, $result->queryCount);
    }
}
