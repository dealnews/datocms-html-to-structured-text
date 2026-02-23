<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Conversion options
 *
 * Configure HTML to DAST conversion behavior including
 * custom handlers, preprocessing, and allowed node types.
 */
class Options {
    /**
     * Whether to preserve newlines in text
     *
     * When true, newlines are kept as-is.
     * When false, they're normalized/removed.
     *
     * @var bool
     */
    public bool $newlines = false;

    /**
     * Custom handler overrides
     *
     * Map of element name to handler callable.
     * Handlers have signature:
     * function(callable $create_node, \DOMNode $node, Context $ctx): mixed
     *
     * @var array<string, callable>
     */
    public array $handlers = [];

    /**
     * Preprocessing function
     *
     * Called with DOMDocument before conversion.
     * Use to modify DOM tree structure.
     *
     * Signature: function(\DOMDocument $doc): void
     *
     * @var callable|null
     */
    public $preprocess = null;

    /**
     * Allowed block node types
     *
     * Only these block types will be included in output.
     * Default: all blocks allowed.
     *
     * @var array<string>
     */
    public array $allowed_blocks = [
        'blockquote',
        'code',
        'heading',
        'link',
        'list',
    ];

    /**
     * Allowed text mark types
     *
     * Only these marks will be applied to text.
     * Default: all standard marks.
     *
     * @var array<string>
     */
    public array $allowed_marks = [
        'strong',
        'code',
        'emphasis',
        'underline',
        'strikethrough',
        'highlight',
    ];

    /**
     * Allowed heading levels
     *
     * Only these heading levels (1-6) will be preserved.
     * Other headings converted to paragraphs.
     *
     * @var array<int>
     */
    public array $allowed_heading_levels = [1, 2, 3, 4, 5, 6];
}
