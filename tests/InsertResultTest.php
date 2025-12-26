<?php

namespace DevTheorem\PeachySQL\Test;

use DevTheorem\PeachySQL\InsertResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the InsertResult object
 */
class InsertResultTest extends TestCase
{
    public function testCreateRetrieve(): void
    {
        $result = new InsertResult(24, 2);
        $this->assertSame(24, $result->id);
        $this->assertSame(2, $result->affected);
    }
}
