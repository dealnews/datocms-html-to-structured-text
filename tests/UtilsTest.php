<?php

namespace DealNews\HtmlToStructuredText\Tests;

use DealNews\HtmlToStructuredText\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Utils class
 */
class UtilsTest extends TestCase {
    /**
     * Test isDastNode identifies valid DAST nodes
     */
    public function testIsDastNodeWithValidNode(): void {
        $valid_nodes = [
            ['type' => 'root', 'children' => []],
            ['type' => 'paragraph', 'children' => []],
            ['type' => 'span', 'value' => 'text'],
            ['type' => 'heading', 'level' => 1, 'children' => []],
        ];

        foreach ($valid_nodes as $node) {
            $this->assertTrue(
                Utils::isDastNode($node),
                "Failed to identify valid node: " . json_encode($node)
            );
        }
    }

    /**
     * Test isDastNode rejects invalid data
     */
    public function testIsDastNodeWithInvalidData(): void {
        $invalid = [
            null,
            'string',
            123,
            [],
            ['no_type_key' => 'value'],
            (object)['type' => 'root'], // Objects not accepted
        ];

        foreach ($invalid as $data) {
            $this->assertFalse(Utils::isDastNode($data));
        }
    }

    /**
     * Test isDastRoot identifies root nodes
     */
    public function testIsDastRoot(): void {
        $root = ['type' => 'root', 'children' => []];
        $not_root = ['type' => 'paragraph', 'children' => []];

        $this->assertTrue(Utils::isDastRoot($root));
        $this->assertFalse(Utils::isDastRoot($not_root));
        $this->assertFalse(Utils::isDastRoot([]));
        $this->assertFalse(Utils::isDastRoot(null));
    }

    /**
     * Test getAllowedChildren returns correct arrays
     */
    public function testGetAllowedChildren(): void {
        // Root allows specific block types
        $root_allowed = Utils::getAllowedChildren('root');
        $this->assertIsArray($root_allowed);
        $this->assertContains('heading', $root_allowed);
        $this->assertContains('paragraph', $root_allowed);
        $this->assertContains('list', $root_allowed);

        // Heading allows inline nodes
        $heading_allowed = Utils::getAllowedChildren('heading');
        $this->assertEquals('inlineNodes', $heading_allowed);

        // Paragraph allows inline nodes
        $para_allowed = Utils::getAllowedChildren('paragraph');
        $this->assertEquals('inlineNodes', $para_allowed);

        // List allows listItem
        $list_allowed = Utils::getAllowedChildren('list');
        $this->assertIsArray($list_allowed);
        $this->assertContains('listItem', $list_allowed);

        // ListItem allows paragraph and list
        $list_item_allowed = Utils::getAllowedChildren('listItem');
        $this->assertIsArray($list_item_allowed);
        $this->assertContains('paragraph', $list_item_allowed);
        $this->assertContains('list', $list_item_allowed);
    }

    /**
     * Test getAllowedChildren with unknown node type
     */
    public function testGetAllowedChildrenWithUnknownType(): void {
        $result = Utils::getAllowedChildren('nonexistent');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test isAllowedChild with various combinations
     */
    public function testIsAllowedChild(): void {
        // Root allows heading
        $this->assertTrue(Utils::isAllowedChild('root', 'heading'));
        $this->assertTrue(Utils::isAllowedChild('root', 'paragraph'));

        // Root does not allow span directly
        $this->assertFalse(Utils::isAllowedChild('root', 'span'));

        // Heading allows inline nodes (span, link)
        $this->assertTrue(Utils::isAllowedChild('heading', 'span'));
        $this->assertTrue(Utils::isAllowedChild('heading', 'link'));

        // Heading does not allow paragraph
        $this->assertFalse(Utils::isAllowedChild('heading', 'paragraph'));

        // List allows listItem
        $this->assertTrue(Utils::isAllowedChild('list', 'listItem'));
        $this->assertFalse(Utils::isAllowedChild('list', 'paragraph'));

        // ListItem allows paragraph and list (nested lists)
        $this->assertTrue(Utils::isAllowedChild('listItem', 'paragraph'));
        $this->assertTrue(Utils::isAllowedChild('listItem', 'list'));
    }

    /**
     * Test isAllowedChild with inlineNodes
     */
    public function testIsAllowedChildWithInlineNodes(): void {
        $inline_types = ['span', 'link', 'itemLink', 'inlineItem', 'inlineBlock'];

        foreach ($inline_types as $type) {
            $this->assertTrue(
                Utils::isAllowedChild('paragraph', $type),
                "paragraph should allow inline type: $type"
            );
            $this->assertTrue(
                Utils::isAllowedChild('heading', $type),
                "heading should allow inline type: $type"
            );
        }
    }

    /**
     * Test isHeading identifies heading nodes
     */
    public function testIsHeading(): void {
        $heading = ['type' => 'heading', 'level' => 1];
        $not_heading = ['type' => 'paragraph'];

        $this->assertTrue(Utils::isHeading($heading));
        $this->assertFalse(Utils::isHeading($not_heading));
        $this->assertFalse(Utils::isHeading([]));
        $this->assertFalse(Utils::isHeading(null));
    }

    /**
     * Test isParagraph identifies paragraph nodes
     */
    public function testIsParagraph(): void {
        $paragraph = ['type' => 'paragraph', 'children' => []];
        $not_paragraph = ['type' => 'heading'];

        $this->assertTrue(Utils::isParagraph($paragraph));
        $this->assertFalse(Utils::isParagraph($not_paragraph));
        $this->assertFalse(Utils::isParagraph([]));
    }

    /**
     * Test isSpan identifies span nodes
     */
    public function testIsSpan(): void {
        $span = ['type' => 'span', 'value' => 'text'];
        $span_with_marks = [
            'type'  => 'span',
            'value' => 'text',
            'marks' => ['strong'],
        ];
        $not_span = ['type' => 'link'];

        $this->assertTrue(Utils::isSpan($span));
        $this->assertTrue(Utils::isSpan($span_with_marks));
        $this->assertFalse(Utils::isSpan($not_span));
        $this->assertFalse(Utils::isSpan([]));
    }

    /**
     * Test isLink identifies link nodes
     */
    public function testIsLink(): void {
        $link = ['type' => 'link', 'url' => 'https://example.com'];
        $not_link = ['type' => 'itemLink'];

        $this->assertTrue(Utils::isLink($link));
        $this->assertFalse(Utils::isLink($not_link));
        $this->assertFalse(Utils::isLink([]));
    }

    /**
     * Test isBlock identifies block nodes
     */
    public function testIsBlock(): void {
        $block = ['type' => 'block', 'item' => '123'];
        $not_block = ['type' => 'paragraph'];

        $this->assertTrue(Utils::isBlock($block));
        $this->assertFalse(Utils::isBlock($not_block));
        $this->assertFalse(Utils::isBlock([]));
    }

    /**
     * Test isInlineBlock identifies inline block nodes
     */
    public function testIsInlineBlock(): void {
        $inline_block = ['type' => 'inlineBlock', 'item' => '456'];
        $not_inline_block = ['type' => 'block'];

        $this->assertTrue(Utils::isInlineBlock($inline_block));
        $this->assertFalse(Utils::isInlineBlock($not_inline_block));
        $this->assertFalse(Utils::isInlineBlock([]));
    }

    /**
     * Test isInlineItem identifies inline item nodes
     */
    public function testIsInlineItem(): void {
        $inline_item = ['type' => 'inlineItem', 'item' => '789'];
        $not_inline_item = ['type' => 'span'];

        $this->assertTrue(Utils::isInlineItem($inline_item));
        $this->assertFalse(Utils::isInlineItem($not_inline_item));
        $this->assertFalse(Utils::isInlineItem([]));
    }

    /**
     * Test isItemLink identifies item link nodes
     */
    public function testIsItemLink(): void {
        $item_link = ['type' => 'itemLink', 'item' => '999'];
        $regular_link = ['type' => 'link'];

        $this->assertTrue(Utils::isItemLink($item_link));
        $this->assertFalse(Utils::isItemLink($regular_link));
        $this->assertFalse(Utils::isItemLink([]));
    }

    /**
     * Test INLINE_NODE_TYPES constant
     */
    public function testInlineNodeTypesConstant(): void {
        $inline_types = Utils::INLINE_NODE_TYPES;

        $this->assertIsArray($inline_types);
        $this->assertContains('span', $inline_types);
        $this->assertContains('link', $inline_types);
        $this->assertContains('itemLink', $inline_types);
        $this->assertContains('inlineItem', $inline_types);
        $this->assertContains('inlineBlock', $inline_types);
    }

    /**
     * Test ALLOWED_CHILDREN constant structure
     */
    public function testAllowedChildrenConstant(): void {
        $allowed = Utils::ALLOWED_CHILDREN;

        $this->assertIsArray($allowed);

        // Check key node types exist
        $this->assertArrayHasKey('root', $allowed);
        $this->assertArrayHasKey('paragraph', $allowed);
        $this->assertArrayHasKey('heading', $allowed);
        $this->assertArrayHasKey('list', $allowed);
        $this->assertArrayHasKey('listItem', $allowed);

        // Verify structure
        $this->assertIsArray($allowed['root']);
        $this->assertEquals('inlineNodes', $allowed['paragraph']);
        $this->assertEquals('inlineNodes', $allowed['heading']);
    }

    /**
     * Test type guards with non-array input
     */
    public function testTypeGuardsWithNonArrayInput(): void {
        $inputs = [null, 'string', 123, 45.6, true, false];

        foreach ($inputs as $input) {
            $this->assertFalse(Utils::isDastNode($input));
            $this->assertFalse(Utils::isDastRoot($input));
            $this->assertFalse(Utils::isHeading($input));
            $this->assertFalse(Utils::isParagraph($input));
            $this->assertFalse(Utils::isSpan($input));
            $this->assertFalse(Utils::isLink($input));
            $this->assertFalse(Utils::isBlock($input));
            $this->assertFalse(Utils::isInlineBlock($input));
            $this->assertFalse(Utils::isInlineItem($input));
            $this->assertFalse(Utils::isItemLink($input));
        }
    }
}
