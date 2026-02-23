<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Conversion context passed to handlers
 *
 * Tracks parent nodes, available handlers, text wrapping rules,
 * marks, and global state during tree traversal.
 */
class Context {
    /**
     * Current parent DAST node type
     *
     * @var string
     */
    public string $parent_node_type;

    /**
     * Parent DOM node
     *
     * @var \DOMNode|null
     */
    public ?\DOMNode $parent_node = null;

    /**
     * Available handlers (merged default + custom)
     *
     * Map of element name/type to handler callable.
     *
     * @var array<string, callable>
     */
    public array $handlers = [];

    /**
     * Default handlers
     *
     * Reference to original handler map before custom overrides.
     *
     * @var array<string, callable>
     */
    public array $default_handlers = [];

    /**
     * Whether text can include newlines
     *
     * When false (e.g., in headings), newlines converted to spaces.
     *
     * @var bool
     */
    public bool $wrap_text = true;

    /**
     * Current text marks
     *
     * Array of mark strings like 'strong', 'emphasis', etc.
     *
     * @var array<string>
     */
    public array $marks = [];

    /**
     * Language detection prefix for code blocks
     *
     * Used to extract language from class names like "language-php".
     *
     * @var string
     */
    public string $code_prefix = 'language-';

    /**
     * Allowed block node types
     *
     * @var array<string>
     */
    public array $allowed_blocks = [];

    /**
     * Allowed mark types
     *
     * @var array<string>
     */
    public array $allowed_marks = [];

    /**
     * Allowed heading levels
     *
     * @var array<int>
     */
    public array $allowed_heading_levels = [];

    /**
     * Global context
     *
     * @var GlobalContext
     */
    public GlobalContext $global;

    /**
     * Constructor
     */
    public function __construct() {
        $this->global = new GlobalContext();
    }
}
