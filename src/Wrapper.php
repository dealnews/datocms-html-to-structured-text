<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Wraps inline nodes in paragraphs
 *
 * Ensures DAST structure is valid by wrapping sequences of
 * inline nodes (span, link) in paragraph nodes when needed.
 */
class Wrapper {
    /**
     * Wraps inline nodes in paragraphs
     *
     * Takes an array of DAST nodes and wraps consecutive
     * inline nodes (phrasing content) in paragraph nodes.
     * Block nodes pass through unchanged.
     *
     * @param array<mixed> $nodes Array of DAST nodes
     *
     * @return array<mixed> Nodes with inline runs wrapped
     */
    public static function wrap(array $nodes): array {
        $flattened = self::flatten($nodes);
        $result    = [];
        $queue     = null;

        foreach ($flattened as $node) {
            if (self::isPhrasing($node)) {
                // Accumulate inline nodes
                if ($queue === null) {
                    $queue = [];
                }
                $queue[] = $node;
            } else {
                // Flush accumulated inline nodes as paragraph
                if ($queue !== null) {
                    $wrapped = self::onPhrasing($queue);
                    if ($wrapped !== null) {
                        $result[] = $wrapped;
                    }
                    $queue = null;
                }
                // Add block node as-is
                $result[] = $node;
            }
        }

        // Flush remaining inline nodes
        if ($queue !== null) {
            $wrapped = self::onPhrasing($queue);
            if ($wrapped !== null) {
                $result[] = $wrapped;
            }
        }

        return $result;
    }

    /**
     * Ensures list children are listItem nodes
     *
     * Wraps non-listItem children in listItem nodes.
     *
     * @param array<mixed> $children    Child nodes
     * @param callable     $create_node Function to create DAST nodes
     *
     * @return array<mixed> Wrapped nodes
     */
    public static function wrapListItems(
        array $children,
        callable $create_node
    ): array {
        $result = [];

        foreach ($children as $child) {
            if (!isset($child['type']) || $child['type'] !== 'listItem') {
                // Wrap non-listItem in listItem
                $wrapped_child = $child;

                // If child isn't allowed in listItem, wrap in paragraph
                if (isset($child['type']) &&
                    !in_array(
                        $child['type'],
                        Utils::ALLOWED_CHILDREN['listItem']
                    )) {
                    $wrapped_child = $create_node('paragraph', [
                        'children' => [$child],
                    ]);
                }

                $result[] = $create_node('listItem', [
                    'children' => [$wrapped_child],
                ]);
            } else {
                $result[] = $child;
            }
        }

        return $result;
    }

    /**
     * Wraps a run of inline nodes in a paragraph
     *
     * @param array<mixed> $nodes Inline nodes
     *
     * @return array<string, mixed>|null Paragraph node or null if empty
     */
    protected static function onPhrasing(array $nodes): ?array {
        // Skip single whitespace-only spans
        if (count($nodes) === 1) {
            $head = $nodes[0];
            if (isset($head['type']) &&
                $head['type'] === 'span' &&
                isset($head['value']) &&
                ($head['value'] === ' ' || $head['value'] === "\n")) {
                return null;
            }
        }

        return [
            'type'     => 'paragraph',
            'children' => $nodes,
        ];
    }

    /**
     * Checks if node is inline (phrasing content)
     *
     * @param mixed $node Node to check
     *
     * @return bool True if node is inline
     */
    protected static function isPhrasing(mixed $node): bool {
        if (!is_array($node) || !isset($node['type'])) {
            return false;
        }

        return in_array($node['type'], ['span', 'link']);
    }

    /**
     * Flattens nodes, splitting hybrid elements
     *
     * Some elements like `delete` and `link` can contain both
     * inline and block content. This splits them appropriately.
     *
     * @param array<mixed> $nodes Nodes to flatten
     *
     * @return array<mixed> Flattened nodes
     */
    protected static function flatten(array $nodes): array {
        $flattened = [];

        foreach ($nodes as $node) {
            // Skip non-nodes
            if (!is_array($node)) {
                continue;
            }

            // Split hybrid nodes if they contain block content
            if (isset($node['type']) &&
                isset($node['children']) &&
                ($node['type'] === 'link') &&
                self::needed($node['children'])) {
                $flattened = array_merge(
                    $flattened,
                    self::split($node)
                );
            } else {
                $flattened[] = $node;
            }
        }

        return $flattened;
    }

    /**
     * Checks if nodes contain block content
     *
     * @param array<mixed> $nodes Nodes to check
     *
     * @return bool True if block content found
     */
    protected static function needed(array $nodes): bool {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (!self::isPhrasing($node)) {
                return true;
            }

            if (isset($node['children']) &&
                self::needed($node['children'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Splits a hybrid node over its children
     *
     * @param array<string, mixed> $node Node to split
     *
     * @return array<mixed> Split nodes
     */
    protected static function split(array $node): array {
        if (!isset($node['children'])) {
            return [$node];
        }

        $result = [];
        $queue  = null;

        foreach ($node['children'] as $child) {
            if (self::isPhrasing($child)) {
                if ($queue === null) {
                    $queue = [];
                }
                $queue[] = $child;
            } else {
                // Flush phrasing nodes
                if ($queue !== null) {
                    $parent = self::shallowCopy($node);
                    $parent['children'] = $queue;
                    $result[] = $parent;
                    $queue = null;
                }

                // Wrap block child
                $parent = self::shallowCopy($node);
                $copy = self::shallowCopy($child);
                $copy['children'] = [$parent];
                $parent['children'] = $child['children'] ?? [];
                $result[] = $copy;
            }
        }

        // Flush remaining phrasing nodes
        if ($queue !== null) {
            $parent = self::shallowCopy($node);
            $parent['children'] = $queue;
            $result[] = $parent;
        }

        return $result;
    }

    /**
     * Creates shallow copy of node (excludes children)
     *
     * @param array<string, mixed> $node Node to copy
     *
     * @return array<string, mixed> Shallow copy
     */
    protected static function shallowCopy(array $node): array {
        $copy = [];

        foreach ($node as $key => $value) {
            if ($key !== 'children') {
                $copy[$key] = $value;
            }
        }

        return $copy;
    }
}
