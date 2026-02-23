<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Utility functions for DAST nodes
 *
 * Provides type guards and helper functions for working with
 * DatoCMS Structured Text (DAST) document nodes.
 */
class Utils {
    /**
     * Inline node types
     *
     * @var array<string>
     */
    public const INLINE_NODE_TYPES = [
        'span',
        'link',
        'itemLink',
        'inlineItem',
        'inlineBlock',
    ];

    /**
     * Allowed children for each node type
     *
     * Maps parent node type to array of allowed child types.
     * Special value 'inlineNodes' means any inline node is allowed.
     *
     * @var array<string, string|array<string>>
     */
    public const ALLOWED_CHILDREN = [
        'blockquote'     => ['paragraph'],
        'block'          => [],
        'inlineBlock'    => [],
        'code'           => [],
        'heading'        => 'inlineNodes',
        'inlineItem'     => [],
        'itemLink'       => 'inlineNodes',
        'link'           => 'inlineNodes',
        'listItem'       => ['paragraph', 'list'],
        'list'           => ['listItem'],
        'paragraph'      => 'inlineNodes',
        'root'           => [
            'blockquote',
            'code',
            'list',
            'paragraph',
            'heading',
            'block',
            'thematicBreak',
        ],
        'span'           => [],
        'thematicBreak'  => [],
    ];

    /**
     * Checks if value is a valid DAST node
     *
     * @param mixed $node Value to check
     *
     * @return bool True if value is a DAST node
     */
    public static function isDastNode(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']);
    }

    /**
     * Checks if node is a root node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is a root
     */
    public static function isDastRoot(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'root';
    }

    /**
     * Gets allowed children for a node type
     *
     * Returns array of allowed child node types, or 'inlineNodes'
     * if any inline node is allowed.
     *
     * @param string $node_type Parent node type
     *
     * @return string|array<string> Allowed children
     */
    public static function getAllowedChildren(string $node_type): string|array
    {
        return self::ALLOWED_CHILDREN[$node_type] ?? [];
    }

    /**
     * Checks if a node type is allowed as child of parent
     *
     * @param string $parent_type Parent node type
     * @param string $child_type  Child node type to check
     *
     * @return bool True if child is allowed
     */
    public static function isAllowedChild(
        string $parent_type,
        string $child_type
    ): bool {
        $allowed = self::getAllowedChildren($parent_type);

        if ($allowed === 'inlineNodes') {
            return in_array($child_type, self::INLINE_NODE_TYPES);
        }

        if (is_array($allowed)) {
            return in_array($child_type, $allowed);
        }

        return false;
    }

    /**
     * Checks if node is a heading node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is a heading
     */
    public static function isHeading(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'heading';
    }

    /**
     * Checks if node is a paragraph node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is a paragraph
     */
    public static function isParagraph(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'paragraph';
    }

    /**
     * Checks if node is a span node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is a span
     */
    public static function isSpan(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'span';
    }

    /**
     * Checks if node is a link node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is a link
     */
    public static function isLink(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'link';
    }

    /**
     * Checks if node is a block node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is a block
     */
    public static function isBlock(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'block';
    }

    /**
     * Checks if node is an inline block node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is an inline block
     */
    public static function isInlineBlock(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'inlineBlock';
    }

    /**
     * Checks if node is an inline item node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is an inline item
     */
    public static function isInlineItem(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'inlineItem';
    }

    /**
     * Checks if node is an item link node
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is an item link
     */
    public static function isItemLink(mixed $node): bool {
        if (!is_array($node)) {
            return false;
        }

        return isset($node['type']) && $node['type'] === 'itemLink';
    }
}
