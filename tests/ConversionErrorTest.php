<?php

namespace DealNews\HtmlToStructuredText\Tests;

use DealNews\HtmlToStructuredText\ConversionError;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConversionError exception
 */
class ConversionErrorTest extends TestCase {
    /**
     * Test basic exception creation
     */
    public function testCreateConversionError(): void {
        $error = new ConversionError('Test error');

        $this->assertEquals('Test error', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getNode());
    }

    /**
     * Test exception with DOMNode
     */
    public function testCreateConversionErrorWithNode(): void {
        $doc = new \DOMDocument();
        $node = $doc->createElement('div');

        $error = new ConversionError('Node error', $node);

        $this->assertEquals('Node error', $error->getMessage());
        $this->assertSame($node, $error->getNode());
    }

    /**
     * Test exception with code
     */
    public function testCreateConversionErrorWithCode(): void {
        $error = new ConversionError('Error', null, 42);

        $this->assertEquals(42, $error->getCode());
    }

    /**
     * Test exception with previous exception
     */
    public function testCreateConversionErrorWithPrevious(): void {
        $previous = new \Exception('Previous error');
        $error = new ConversionError('Current error', null, 0, $previous);

        $this->assertSame($previous, $error->getPrevious());
    }

    /**
     * Test exception is instanceof RuntimeException
     */
    public function testConversionErrorIsRuntimeException(): void {
        $error = new ConversionError('Test');

        $this->assertInstanceOf(\RuntimeException::class, $error);
    }
}
