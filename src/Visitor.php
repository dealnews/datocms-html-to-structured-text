<?php

namespace DealNews\HtmlToStructuredText;

/**
 * DOM tree visitor for HTML to DAST conversion
 *
 * Traverses DOMDocument nodes and calls appropriate handlers
 * to convert HTML elements into DAST nodes.
 */
class Visitor {
    /**
     * Visits a single DOM node
     *
     * Determines the appropriate handler for the node type
     * and calls it to convert the node to DAST.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node to visit
     * @param Context  $context     Conversion context
     *
     * @return mixed DAST node, array of nodes, or null
     */
    public static function visitNode(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        $handlers = $context->handlers;
        $handler  = null;

        // Element nodes: match by tag name
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tag_name = strtolower($node->nodeName);
            if (isset($handlers[$tag_name])) {
                $handler = $handlers[$tag_name];
            } else {
                $handler = [self::class, 'unknownHandler'];
            }
        } elseif ($node instanceof \DOMDocument) {
            // Document root (works for both XML and HTML documents)
            $handler = $handlers['root'] ?? null;
        } elseif ($node->nodeType === XML_TEXT_NODE ||
                  $node->nodeType === XML_CDATA_SECTION_NODE) {
            // Text content
            $handler = $handlers['text'] ?? null;
        }

        if (!is_callable($handler)) {
            return null;
        }

        return $handler($create_node, $node, $context);
    }

    /**
     * Visits all children of a DOM node
     *
     * Iterates through child nodes, calling visitNode on each,
     * and collects the results.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $parent_node Parent DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<mixed> Array of DAST nodes
     */
    public static function visitChildren(
        callable $create_node,
        \DOMNode $parent_node,
        Context $context
    ): array {
        $children = $parent_node->childNodes ?? [];
        $values   = [];

        foreach ($children as $child) {
            $result = self::visitNode($create_node, $child, $context);

            if ($result !== null) {
                // Check if result is a DAST node or array of nodes
                if (is_array($result) && isset($result['type'])) {
                    // Single DAST node
                    $values[] = $result;
                } elseif (is_array($result)) {
                    // Array of nodes (from handler returning multiple)
                    $values = array_merge($values, $result);
                } else {
                    // Scalar or other
                    $values[] = $result;
                }
            }
        }

        return $values;
    }

    /**
     * Default handler for unknown elements
     *
     * Skips the current node and processes its children.
     * This allows unknown tags to be transparent.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed DAST nodes from children
     */
    protected static function unknownHandler(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::visitChildren($create_node, $node, $context);
    }
}
