<?php

namespace DevTheorem\PeachySQL\Test\QueryBuilder;

use DevTheorem\PeachySQL\Options;
use DevTheorem\PeachySQL\QueryBuilder\Query;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the base query builder
 */
class QueryTest extends TestCase
{
    public function testEscapeIdentifier(): void
    {
        $options = new Options();
        $query = new Query($options);
        $actual = $query->escapeIdentifier('Test"Identifier');
        $this->assertSame('"Test""Identifier"', $actual);

        try {
            $query->escapeIdentifier(''); // should throw exception
            $this->fail('escapeIdentifier failed to throw expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Identifier cannot be blank', $e->getMessage());
        }

        $options->identifierQuote = '`'; // test syntax for MySQL without ANSI_QUOTES enabled
        $actual = $query->escapeIdentifier('My`Identifier');
        $this->assertSame('`My``Identifier`', $actual);
    }
}
