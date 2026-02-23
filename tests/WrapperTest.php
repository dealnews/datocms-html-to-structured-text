<?php

namespace DealNews\HtmlToStructuredText\Tests;

use DealNews\HtmlToStructuredText\Wrapper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Wrapper class
 */
class WrapperTest extends TestCase {
    /**
     * Creates a mock create_node callable
     *
     * @return callable
     */
    protected function createNodeFactory(): callable {
        return function (string $type, array $props): array {
            $props['type'] = $type;
            return $props;
        };
    }

    /**
     * Test wrap() with single inline node
     */
    public function testWrapSingleInlineNode(): void {
        $nodes = [
            ['type' => 'span', 'value' => 'Hello'],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(1, $result);
        $this->assertEquals('paragraph', $result[0]['type']);
        $this->assertCount(1, $result[0]['children']);
        $this->assertEquals('span', $result[0]['children'][0]['type']);
    }

    /**
     * Test wrap() with multiple inline nodes
     */
    public function testWrapMultipleInlineNodes(): void {
        $nodes = [
            ['type' => 'span', 'value' => 'Hello '],
            ['type' => 'span', 'value' => 'world', 'marks' => ['strong']],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(1, $result);
        $this->assertEquals('paragraph', $result[0]['type']);
        $this->assertCount(2, $result[0]['children']);
    }

    /**
     * Test wrap() with block node (passes through unchanged)
     */
    public function testWrapBlockNode(): void {
        $nodes = [
            ['type' => 'heading', 'level' => 1, 'children' => []],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(1, $result);
        $this->assertEquals('heading', $result[0]['type']);
    }

    /**
     * Test wrap() with mixed inline and block nodes
     */
    public function testWrapMixedInlineAndBlockNodes(): void {
        $nodes = [
            ['type' => 'span', 'value' => 'Inline text'],
            ['type' => 'heading', 'level' => 1, 'children' => []],
            ['type' => 'span', 'value' => 'More inline'],
        ];

        $result = Wrapper::wrap($nodes);

        // Should have: paragraph, heading, paragraph
        $this->assertCount(3, $result);
        $this->assertEquals('paragraph', $result[0]['type']);
        $this->assertEquals('heading', $result[1]['type']);
        $this->assertEquals('paragraph', $result[2]['type']);
    }

    /**
     * Test wrap() skips single whitespace-only spans
     */
    public function testWrapSkipsSingleWhitespaceSpan(): void {
        $nodes = [
            ['type' => 'span', 'value' => ' '],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(0, $result);
    }

    /**
     * Test wrap() skips single newline-only spans
     */
    public function testWrapSkipsSingleNewlineSpan(): void {
        $nodes = [
            ['type' => 'span', 'value' => "\n"],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(0, $result);
    }

    /**
     * Test wrap() keeps whitespace spans when mixed with other content
     */
    public function testWrapKeepsWhitespaceWithOtherContent(): void {
        $nodes = [
            ['type' => 'span', 'value' => ' '],
            ['type' => 'span', 'value' => 'text'],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(1, $result);
        $this->assertEquals('paragraph', $result[0]['type']);
        $this->assertCount(2, $result[0]['children']);
    }

    /**
     * Test wrap() with link node (inline)
     */
    public function testWrapWithLinkNode(): void {
        $nodes = [
            [
                'type'     => 'link',
                'url'      => 'https://example.com',
                'children' => [
                    ['type' => 'span', 'value' => 'Click here'],
                ],
            ],
        ];

        $result = Wrapper::wrap($nodes);

        $this->assertCount(1, $result);
        $this->assertEquals('paragraph', $result[0]['type']);
        $this->assertEquals('link', $result[0]['children'][0]['type']);
    }

    /**
     * Test wrap() with empty array
     */
    public function testWrapEmptyArray(): void {
        $result = Wrapper::wrap([]);
        $this->assertCount(0, $result);
    }

    /**
     * Test wrapListItems() with valid listItem nodes
     */
    public function testWrapListItemsWithValidListItems(): void {
        $create_node = $this->createNodeFactory();

        $children = [
            [
                'type'     => 'listItem',
                'children' => [
                    ['type' => 'paragraph', 'children' => []],
                ],
            ],
        ];

        $result = Wrapper::wrapListItems($children, $create_node);

        $this->assertCount(1, $result);
        $this->assertEquals('listItem', $result[0]['type']);
    }

    /**
     * Test wrapListItems() wraps non-listItem nodes
     */
    public function testWrapListItemsWrapsNonListItems(): void {
        $create_node = $this->createNodeFactory();

        $children = [
            ['type' => 'paragraph', 'children' => []],
        ];

        $result = Wrapper::wrapListItems($children, $create_node);

        $this->assertCount(1, $result);
        $this->assertEquals('listItem', $result[0]['type']);
        $this->assertArrayHasKey('children', $result[0]);
        $this->assertCount(1, $result[0]['children']);
        $this->assertEquals('paragraph', $result[0]['children'][0]['type']);
    }

    /**
     * Test wrapListItems() wraps and re-wraps invalid children
     */
    public function testWrapListItemsWrapsInvalidChildren(): void {
        $create_node = $this->createNodeFactory();

        // Span is not allowed in listItem, so it gets wrapped in paragraph
        $children = [
            ['type' => 'span', 'value' => 'text'],
        ];

        $result = Wrapper::wrapListItems($children, $create_node);

        $this->assertCount(1, $result);
        $this->assertEquals('listItem', $result[0]['type']);
        
        // Child should be wrapped in paragraph
        $this->assertEquals('paragraph', $result[0]['children'][0]['type']);
        $this->assertEquals('span', $result[0]['children'][0]['children'][0]['type']);
    }

    /**
     * Test wrapListItems() with mixed valid and invalid
     */
    public function testWrapListItemsMixedValidAndInvalid(): void {
        $create_node = $this->createNodeFactory();

        $children = [
            ['type' => 'listItem', 'children' => []],
            ['type' => 'paragraph', 'children' => []],
            ['type' => 'span', 'value' => 'text'],
        ];

        $result = Wrapper::wrapListItems($children, $create_node);

        $this->assertCount(3, $result);
        
        // First should be unchanged
        $this->assertEquals('listItem', $result[0]['type']);
        
        // Second should be wrapped
        $this->assertEquals('listItem', $result[1]['type']);
        $this->assertEquals('paragraph', $result[1]['children'][0]['type']);
        
        // Third should be double-wrapped (span in paragraph in listItem)
        $this->assertEquals('listItem', $result[2]['type']);
        $this->assertEquals('paragraph', $result[2]['children'][0]['type']);
    }

    /**
     * Test wrap() with link containing block content (hybrid)
     */
    public function testWrapWithLinkContainingBlockContent(): void {
        $nodes = [
            [
                'type'     => 'link',
                'url'      => 'https://example.com',
                'children' => [
                    ['type' => 'span', 'value' => 'Before'],
                    ['type' => 'paragraph', 'children' => []],
                    ['type' => 'span', 'value' => 'After'],
                ],
            ],
        ];

        $result = Wrapper::wrap($nodes);

        // Link with block content should be split
        $this->assertGreaterThan(1, count($result));
    }

    /**
     * Test wrap() with consecutive inline runs
     */
    public function testWrapConsecutiveInlineRuns(): void {
        $nodes = [
            ['type' => 'span', 'value' => 'First'],
            ['type' => 'span', 'value' => 'Second'],
            ['type' => 'heading', 'level' => 1, 'children' => []],
            ['type' => 'span', 'value' => 'Third'],
            ['type' => 'link', 'url' => '#', 'children' => []],
        ];

        $result = Wrapper::wrap($nodes);

        // Should have: paragraph (2 spans), heading, paragraph (span + link)
        $this->assertCount(3, $result);
        $this->assertEquals('paragraph', $result[0]['type']);
        $this->assertCount(2, $result[0]['children']);
        $this->assertEquals('heading', $result[1]['type']);
        $this->assertEquals('paragraph', $result[2]['type']);
        $this->assertCount(2, $result[2]['children']);
    }

    /**
     * Test wrapListItems() with empty array
     */
    public function testWrapListItemsEmptyArray(): void {
        $create_node = $this->createNodeFactory();
        $result = Wrapper::wrapListItems([], $create_node);
        $this->assertCount(0, $result);
    }

    /**
     * Test wrap() handles nodes without type key gracefully
     */
    public function testWrapHandlesMalformedNodes(): void {
        $nodes = [
            ['no_type' => 'invalid'],
            ['type' => 'span', 'value' => 'valid'],
        ];

        // Should not throw exception, just skip invalid nodes
        $result = Wrapper::wrap($nodes);
        
        // Only valid span should be wrapped
        $this->assertIsArray($result);
    }
}
