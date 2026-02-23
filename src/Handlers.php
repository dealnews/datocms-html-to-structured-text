<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Default HTML element handlers
 *
 * Converts HTML DOM nodes to DAST nodes. Each handler checks
 * parent-child validity and returns appropriate DAST structure.
 */
class Handlers {
    /**
     * Handler for root/document node
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|null Root DAST node
     */
    public static function root(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): ?array {
        $new_context = clone $context;
        $new_context->parent_node_type = 'root';
        $new_context->parent_node = $node;

        $children = Visitor::visitChildren($create_node, $node, $new_context);

        // Wrap inline nodes in paragraphs if needed
        if (!empty($children)) {
            $has_invalid = false;
            $allowed = Utils::ALLOWED_CHILDREN['root'];

            foreach ($children as $child) {
                if (is_array($child) &&
                    isset($child['type']) &&
                    !in_array($child['type'], $allowed)) {
                    $has_invalid = true;
                    break;
                }
            }

            if ($has_invalid) {
                $children = Wrapper::wrap($children);
            }
        }

        if (empty($children)) {
            return null;
        }

        return $create_node('root', ['children' => $children]);
    }

    /**
     * Handler for paragraph (`<p>`) element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function paragraph(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'paragraph'
        );

        $new_context = clone $context;
        $new_context->parent_node_type = $is_allowed ?
            'paragraph' :
            $context->parent_node_type;
        $new_context->parent_node = $node;

        $children = Visitor::visitChildren(
            $create_node,
            $node,
            $new_context
        );

        if (!empty($children)) {
            return $is_allowed ?
                $create_node('paragraph', ['children' => $children]) :
                $children;
        }

        return null;
    }

    /**
     * Handler for thematic break (`<hr>`) element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|null DAST node or null
     */
    public static function thematicBreak(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): ?array {
        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'thematicBreak'
        );

        return $is_allowed ? $create_node('thematicBreak', []) : null;
    }

    /**
     * Handler for heading (`<h1>` through `<h6>`) elements
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function heading(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        // Extract level from tag name (h1 -> 1, h2 -> 2, etc.)
        $tag_name = strtolower($node->nodeName);
        $level    = (int) substr($tag_name, 1, 1);

        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'heading'
        ) &&
            in_array('heading', $context->allowed_blocks) &&
            in_array($level, $context->allowed_heading_levels);

        $new_context = clone $context;
        $new_context->parent_node_type = $is_allowed ?
            'heading' :
            $context->parent_node_type;
        $new_context->parent_node = $node;
        // Headings don't wrap text (newlines become spaces)
        $new_context->wrap_text = $is_allowed ? false : $context->wrap_text;

        $children = Visitor::visitChildren(
            $create_node,
            $node,
            $new_context
        );

        if (!empty($children)) {
            return $is_allowed ?
                $create_node('heading', [
                    'level'    => $level,
                    'children' => $children,
                ]) :
                $children;
        }

        return null;
    }

    /**
     * Handler for code block (`<pre>`, `<code>`) elements
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function code(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'code'
        );

        if (!$is_allowed) {
            return self::inlineCode($create_node, $node, $context);
        }

        if (!in_array('code', $context->allowed_blocks)) {
            return Visitor::visitChildren($create_node, $node, $context);
        }

        // Extract language from class attribute
        $language = [];
        $tag_name = strtolower($node->nodeName);
        $prefix   = $context->code_prefix;

        if ($tag_name === 'pre' && $node->hasChildNodes()) {
            // Check for <code> child with class
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE &&
                    strtolower($child->nodeName) === 'code' &&
                    $child instanceof \DOMElement) {
                    $class_list = self::getClassList($child);
                    $lang = self::extractLanguage($class_list, $prefix);
                    if ($lang !== null) {
                        $language = ['language' => $lang];
                    }
                    break;
                }
            }
        } elseif ($tag_name === 'code' &&
                  $node instanceof \DOMElement) {
            $class_list = self::getClassList($node);
            $lang = self::extractLanguage($class_list, $prefix);
            if ($lang !== null) {
                $language = ['language' => $lang];
            }
        }

        // Get text content
        $code_text = self::wrapText($context, $node->textContent ?? '');
        $code_text = rtrim($code_text, "\n");

        return $create_node('code', array_merge($language, [
            'code' => $code_text,
        ]));
    }

    /**
     * Handler for blockquote (`<blockquote>`) element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function blockquote(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'blockquote'
        ) && in_array('blockquote', $context->allowed_blocks);

        $new_context = clone $context;
        $new_context->parent_node_type = $is_allowed ?
            'blockquote' :
            $context->parent_node_type;
        $new_context->parent_node = $node;

        $children = Visitor::visitChildren(
            $create_node,
            $node,
            $new_context
        );

        if (!empty($children)) {
            return $is_allowed ?
                $create_node('blockquote', [
                    'children' => Wrapper::wrap($children),
                ]) :
                $children;
        }

        return null;
    }

    /**
     * Handler for list (`<ul>`, `<ol>`) elements
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function list(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'list'
        ) && in_array('list', $context->allowed_blocks);

        if (!$is_allowed) {
            return Visitor::visitChildren($create_node, $node, $context);
        }

        $new_context = clone $context;
        $new_context->parent_node_type = 'list';
        $new_context->parent_node = $node;

        $children = Visitor::visitChildren(
            $create_node,
            $node,
            $new_context
        );

        // Wrap non-listItem children
        $children = Wrapper::wrapListItems($children, $create_node);

        if (!empty($children)) {
            $tag_name = strtolower($node->nodeName);
            $style    = $tag_name === 'ol' ? 'numbered' : 'bulleted';

            return $create_node('list', [
                'style'    => $style,
                'children' => $children,
            ]);
        }

        return null;
    }

    /**
     * Handler for list item (`<li>`) element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function listItem(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        $is_allowed = Utils::isAllowedChild(
            $context->parent_node_type,
            'listItem'
        ) && in_array('list', $context->allowed_blocks);

        $new_context = clone $context;
        $new_context->parent_node_type = $is_allowed ?
            'listItem' :
            $context->parent_node_type;
        $new_context->parent_node = $node;

        $children = Visitor::visitChildren(
            $create_node,
            $node,
            $new_context
        );

        if (!empty($children)) {
            return $is_allowed ?
                $create_node('listItem', [
                    'children' => Wrapper::wrap($children),
                ]) :
                $children;
        }

        return null;
    }

    /**
     * Gets class list from DOM element
     *
     * @param \DOMElement $element DOM element
     *
     * @return array<string> Class names
     */
    protected static function getClassList(\DOMElement $element): array {
        $class_attr = $element->getAttribute('class');
        if (empty($class_attr)) {
            return [];
        }

        return preg_split('/\s+/', trim($class_attr));
    }

    /**
     * Extracts language from class list
     *
     * @param array<string> $class_list Class names
     * @param string        $prefix     Language prefix
     *
     * @return string|null Language code or null
     */
    protected static function extractLanguage(
        array $class_list,
        string $prefix
    ): ?string {
        foreach ($class_list as $class_name) {
            if (str_starts_with($class_name, $prefix)) {
                return substr($class_name, strlen($prefix));
            }
        }

        return null;
    }

    /**
     * Wraps text based on context
     *
     * Converts newlines to spaces if wrapText is false.
     *
     * @param Context $context Conversion context
     * @param string  $text    Text to wrap
     *
     * @return string Wrapped text
     */
    protected static function wrapText(Context $context, string $text): string
    {
        if ($context->wrap_text) {
            return $text;
        }

        return preg_replace('/\r?\n|\r/', ' ', $text);
    }

    /**
     * Inline code handler (for code in paragraph context)
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed DAST nodes
     */
    protected static function inlineCode(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::withMark('code', $create_node, $node, $context);
    }

    /**
     * Creates a mark handler
     *
     * Returns handler that adds a mark to context.
     *
     * @param string   $mark         Mark type
     * @param callable $create_node  Function to create DAST nodes
     * @param \DOMNode $node         DOM node
     * @param Context  $context      Conversion context
     *
     * @return mixed DAST nodes
     */
    protected static function withMark(
        string $mark,
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        if (!in_array($mark, $context->allowed_marks)) {
            return Visitor::visitChildren($create_node, $node, $context);
        }

        $new_context = clone $context;
        if (!in_array($mark, $new_context->marks)) {
            $new_context->marks[] = $mark;
        }
        $new_context->parent_node = $node;

        return Visitor::visitChildren($create_node, $node, $new_context);
    }

    /**
     * Handler for link (`<a>`) element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|array<mixed>|null DAST node or children
     */
    public static function link(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array|null {
        if (!in_array('link', $context->allowed_blocks)) {
            return Visitor::visitChildren($create_node, $node, $context);
        }

        $allowed = Utils::getAllowedChildren($context->parent_node_type);
        $is_allowed = false;

        if ($allowed === 'inlineNodes') {
            $is_allowed = in_array('link', Utils::INLINE_NODE_TYPES);
        } elseif (is_array($allowed)) {
            $is_allowed = in_array('link', $allowed);
        }

        // Links can be wrapped in certain contexts
        if (!$is_allowed) {
            $wrappable = ['root', 'list', 'listItem'];
            $is_allowed = in_array($context->parent_node_type, $wrappable);
        }

        // Handle links wrapping headings - invert structure
        if ($node->hasChildNodes()) {
            $wraps_headings = false;
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $tag = strtolower($child->nodeName);
                    if (str_starts_with($tag, 'h')) {
                        $wraps_headings = true;
                        break;
                    }
                }
            }

            if ($wraps_headings) {
                // Restructure: split and invert heading-link relationship
                $is_allowed = false;
            }
        }

        $new_context = clone $context;
        $new_context->parent_node_type = $is_allowed ?
            'link' :
            $context->parent_node_type;
        $new_context->parent_node = $node;

        $children = Visitor::visitChildren(
            $create_node,
            $node,
            $new_context
        );

        if (!empty($children)) {
            if (!$is_allowed) {
                return $children;
            }

            $props = [
                'url'      => self::resolveUrl(
                    $context,
                    self::getAttributeValue($node, 'href')
                ),
                'children' => $children,
            ];

            // Extract meta attributes
            $meta = [];
            foreach (['target', 'rel', 'title'] as $attr) {
                $value = self::getAttributeValue($node, $attr);
                if ($value !== null && $value !== '') {
                    $meta[] = ['id' => $attr, 'value' => $value];
                }
            }

            if (!empty($meta)) {
                $props['meta'] = $meta;
            }

            return $create_node('link', $props);
        }

        return null;
    }

    /**
     * Handler for text nodes
     *
     * Creates span nodes with text value and any active marks.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM text node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed>|null Span node
     */
    public static function text(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): ?array {
        $text = $node->textContent ?? '';

        $props = [
            'value' => self::wrapText($context, $text),
        ];

        // Add marks if present
        if (!empty($context->marks)) {
            $allowed_marks = array_filter(
                $context->marks,
                fn($mark) => in_array($mark, $context->allowed_marks)
            );
            if (!empty($allowed_marks)) {
                $props['marks'] = array_values($allowed_marks);
            }
        }

        return $create_node('span', $props);
    }

    /**
     * Handler for line break (`<br>`) element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return array<string, mixed> Span with newline
     */
    public static function br(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): array {
        return $create_node('span', ['value' => "\n"]);
    }

    /**
     * Handler for strong/bold elements
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Children with strong mark
     */
    public static function strong(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::withMark('strong', $create_node, $node, $context);
    }

    /**
     * Handler for emphasis/italic elements
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Children with emphasis mark
     */
    public static function italic(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::withMark('emphasis', $create_node, $node, $context);
    }

    /**
     * Handler for underline element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Children with underline mark
     */
    public static function underline(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::withMark('underline', $create_node, $node, $context);
    }

    /**
     * Handler for strikethrough element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Children with strikethrough mark
     */
    public static function strikethrough(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::withMark(
            'strikethrough',
            $create_node,
            $node,
            $context
        );
    }

    /**
     * Handler for highlight/mark element
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Children with highlight mark
     */
    public static function highlight(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        return self::withMark('highlight', $create_node, $node, $context);
    }

    /**
     * Handler for span with inline styles
     *
     * Extracts marks from inline CSS styles.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Children with extracted marks
     */
    public static function span(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        $new_context = clone $context;
        $new_marks   = [];

        // Extract marks from style attribute
        if ($node instanceof \DOMElement) {
            $style = $node->getAttribute('style');
            if (!empty($style)) {
                $declarations = explode(';', $style);
                foreach ($declarations as $declaration) {
                    $parts = explode(':', $declaration, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }

                    $prop  = trim($parts[0]);
                    $value = trim($parts[1]);

                    if ($prop === 'font-weight' &&
                        ($value === 'bold' || (int) $value > 400)) {
                        $new_marks[] = 'strong';
                    } elseif ($prop === 'font-style' &&
                              $value === 'italic') {
                        $new_marks[] = 'emphasis';
                    } elseif ($prop === 'text-decoration' &&
                              $value === 'underline') {
                        $new_marks[] = 'underline';
                    }
                }
            }
        }

        // Add new marks to context
        if (!empty($new_marks)) {
            foreach ($new_marks as $mark) {
                if (in_array($mark, $context->allowed_marks) &&
                    !in_array($mark, $new_context->marks)) {
                    $new_context->marks[] = $mark;
                }
            }
        }

        $new_context->parent_node = $node;

        return Visitor::visitChildren($create_node, $node, $new_context);
    }

    /**
     * Handler for head element
     *
     * Looks for base tag for URL resolution.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return mixed Result from base handler or null
     */
    public static function head(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): mixed {
        if (!$node->hasChildNodes()) {
            return null;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE &&
                strtolower($child->nodeName) === 'base') {
                return self::base($create_node, $child, $context);
            }
        }

        return null;
    }

    /**
     * Handler for base element
     *
     * Sets base URL in global context for link resolution.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return null Always returns null
     */
    public static function base(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): null {
        if (!$context->global->base_url_found &&
            $node instanceof \DOMElement) {
            $href = $node->getAttribute('href');
            if (!empty($href)) {
                $context->global->base_url = rtrim($href, '/');
                $context->global->base_url_found = true;
            }
        }

        return null;
    }

    /**
     * No-op handler for ignored elements
     *
     * Returns null to skip these elements.
     *
     * @param callable $create_node Function to create DAST nodes
     * @param \DOMNode $node        DOM node
     * @param Context  $context     Conversion context
     *
     * @return null Always returns null
     */
    public static function noop(
        callable $create_node,
        \DOMNode $node,
        Context $context
    ): null {
        return null;
    }

    /**
     * Gets attribute value from DOM node
     *
     * @param \DOMNode $node DOM node
     * @param string   $name Attribute name
     *
     * @return string|null Attribute value or null
     */
    protected static function getAttributeValue(
        \DOMNode $node,
        string $name
    ): ?string {
        if (!$node instanceof \DOMElement) {
            return null;
        }

        $value = $node->getAttribute($name);

        return $value !== '' ? $value : null;
    }

    /**
     * Resolves URL using base tag context
     *
     * @param Context     $context Conversion context
     * @param string|null $url     URL to resolve
     *
     * @return string Resolved URL
     */
    protected static function resolveUrl(
        Context $context,
        ?string $url
    ): string {
        if ($url === null || $url === '') {
            return '';
        }

        if ($context->global->base_url === null) {
            return $url;
        }

        // Check if relative URL
        $is_relative = preg_match('/^\.?\//', $url);

        try {
            // Parse URL with base
            $base_url = $context->global->base_url;
            $parsed   = parse_url($url);
            $base     = parse_url($base_url);

            if ($is_relative && isset($parsed['path'])) {
                // Combine base path with relative path
                $base_path = $base['path'] ?? '';
                $full_path = $parsed['path'];

                if (!str_starts_with($full_path, $base_path)) {
                    $full_path = $base_path . $full_path;
                }

                // Reconstruct URL
                $scheme = $base['scheme'] ?? 'https';
                $host   = $base['host'] ?? '';
                $result = $scheme . '://' . $host . $full_path;

                if (isset($parsed['query'])) {
                    $result .= '?' . $parsed['query'];
                }
                if (isset($parsed['fragment'])) {
                    $result .= '#' . $parsed['fragment'];
                }

                return $result;
            }

            // Not relative or already absolute
            return $url;
        } catch (\Throwable $e) {
            // Fallback to original URL
            return $url;
        }
    }
}
