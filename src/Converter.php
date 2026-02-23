<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Converts HTML to DatoCMS Structured Text (DAST)
 *
 * Main entry point for converting HTML strings or DOMDocuments
 * into DAST format compatible with DatoCMS.
 *
 * Usage:
 * ```php
 * $converter = new Converter();
 * $dast = $converter->convert('<h1>Hello</h1><p>World</p>');
 * // Returns: ['schema' => 'dast', 'document' => [...]]
 * ```
 */
class Converter {
    /**
     * Converts HTML string to DAST
     *
     * Parses HTML and converts to DatoCMS Structured Text format.
     *
     * @param string       $html    HTML string to convert
     * @param Options|null $options Conversion options
     *
     * @return array<string, mixed>|null DAST document or null
     *
     * @throws ConversionError If conversion fails
     */
    public function convert(string $html, ?Options $options = null): ?array {
        $doc = new \DOMDocument();

        // Suppress warnings from malformed HTML
        $prev = libxml_use_internal_errors(true);

        // Load HTML (with UTF-8 encoding wrapper)
        $wrapped = '<?xml encoding="UTF-8">' . $html;
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $this->convertDocument($doc, $options);
    }

    /**
     * Converts DOMDocument to DAST
     *
     * For cases where HTML is already parsed.
     *
     * @param \DOMDocument $doc     DOM document to convert
     * @param Options|null $options Conversion options
     *
     * @return array<string, mixed>|null DAST document or null
     *
     * @throws ConversionError If conversion fails
     */
    public function convertDocument(
        \DOMDocument $doc,
        ?Options $options = null
    ): ?array {
        if ($options === null) {
            $options = new Options();
        }

        // Preprocessing hook
        if ($options->preprocess !== null &&
            is_callable($options->preprocess)) {
            call_user_func($options->preprocess, $doc);
        }

        // Build handler map
        $handlers = $this->buildHandlers($options);

        // Build context
        $context = $this->buildContext($options, $handlers);

        // Create node factory
        $create_node = function (string $type, array $props): array {
            $props['type'] = $type;
            return $props;
        };

        // Visit document root
        try {
            $root_node = Visitor::visitNode($create_node, $doc, $context);

            if ($root_node === null) {
                return null;
            }

            return [
                'schema'   => 'dast',
                'document' => $root_node,
            ];
        } catch (\Throwable $e) {
            throw new ConversionError(
                'Conversion failed: ' . $e->getMessage(),
                null,
                0,
                $e
            );
        }
    }

    /**
     * Builds handler map
     *
     * Merges default handlers with custom overrides.
     *
     * @param Options $options Conversion options
     *
     * @return array<string, callable> Handler map
     */
    protected function buildHandlers(Options $options): array {
        $default_handlers = [
            // Root/document
            'root'       => [Handlers::class, 'root'],

            // Block elements
            'p'          => [Handlers::class, 'paragraph'],
            'summary'    => [Handlers::class, 'paragraph'],
            'h1'         => [Handlers::class, 'heading'],
            'h2'         => [Handlers::class, 'heading'],
            'h3'         => [Handlers::class, 'heading'],
            'h4'         => [Handlers::class, 'heading'],
            'h5'         => [Handlers::class, 'heading'],
            'h6'         => [Handlers::class, 'heading'],
            'ul'         => [Handlers::class, 'list'],
            'ol'         => [Handlers::class, 'list'],
            'dir'        => [Handlers::class, 'list'],
            'li'         => [Handlers::class, 'listItem'],
            'dt'         => [Handlers::class, 'listItem'],
            'dd'         => [Handlers::class, 'listItem'],
            'blockquote' => [Handlers::class, 'blockquote'],
            'hr'         => [Handlers::class, 'thematicBreak'],

            // Code blocks
            'listing'    => [Handlers::class, 'code'],
            'plaintext'  => [Handlers::class, 'code'],
            'pre'        => [Handlers::class, 'code'],
            'xmp'        => [Handlers::class, 'code'],
            'code'       => [Handlers::class, 'code'],
            'kbd'        => [Handlers::class, 'code'],
            'samp'       => [Handlers::class, 'code'],
            'tt'         => [Handlers::class, 'code'],
            'var'        => [Handlers::class, 'code'],

            // Inline elements
            'a'          => [Handlers::class, 'link'],
            'strong'     => [Handlers::class, 'strong'],
            'b'          => [Handlers::class, 'strong'],
            'em'         => [Handlers::class, 'italic'],
            'i'          => [Handlers::class, 'italic'],
            'u'          => [Handlers::class, 'underline'],
            'strike'     => [Handlers::class, 'strikethrough'],
            's'          => [Handlers::class, 'strikethrough'],
            'mark'       => [Handlers::class, 'highlight'],
            'span'       => [Handlers::class, 'span'],

            // Text and breaks
            'text'       => [Handlers::class, 'text'],
            'br'         => [Handlers::class, 'br'],

            // Special
            'head'       => [Handlers::class, 'head'],
            'base'       => [Handlers::class, 'base'],

            // Ignored elements
            'comment'    => [Handlers::class, 'noop'],
            'script'     => [Handlers::class, 'noop'],
            'style'      => [Handlers::class, 'noop'],
            'title'      => [Handlers::class, 'noop'],
            'video'      => [Handlers::class, 'noop'],
            'audio'      => [Handlers::class, 'noop'],
            'embed'      => [Handlers::class, 'noop'],
            'iframe'     => [Handlers::class, 'noop'],
        ];

        // Merge custom handlers
        return array_merge($default_handlers, $options->handlers);
    }

    /**
     * Builds conversion context
     *
     * @param Options              $options  Conversion options
     * @param array<string, mixed> $handlers Handler map
     *
     * @return Context Conversion context
     */
    protected function buildContext(
        Options $options,
        array $handlers
    ): Context {
        $context = new Context();
        $context->parent_node_type = 'root';
        $context->parent_node = null;
        $context->handlers = $handlers;
        $context->default_handlers = $handlers;
        $context->wrap_text = true;
        $context->marks = [];
        $context->allowed_blocks = $options->allowed_blocks;
        $context->allowed_marks = $options->allowed_marks;
        $context->allowed_heading_levels = $options->allowed_heading_levels;

        return $context;
    }
}
