<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Exception thrown when HTML conversion fails
 *
 * This exception is thrown when the converter encounters errors such as:
 * - Invalid HTML structure
 * - Malformed DOM nodes
 * - Handler errors during conversion
 *
 * Usage:
 * ```php
 * try {
 *     $dast = $converter->convert($html);
 * } catch (ConversionError $e) {
 *     echo "Conversion failed: " . $e->getMessage();
 *     $node = $e->getNode(); // Get the problematic DOM node
 * }
 * ```
 */
class ConversionError extends \RuntimeException {
    /**
     * The DOM node that caused the error
     *
     * @var \DOMNode|null
     */
    protected ?\DOMNode $node = null;

    /**
     * Creates a new ConversionError exception
     *
     * @param string          $message  Error message describing what went
     *                                  wrong
     * @param \DOMNode|null   $node     The DOM node that caused the error
     *                                  (optional)
     * @param int             $code     Exception code (default: 0)
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message,
        ?\DOMNode $node = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->node = $node;
    }

    /**
     * Gets the DOM node that caused the error
     *
     * @return \DOMNode|null The problematic node, or null if not available
     */
    public function getNode(): ?\DOMNode {
        return $this->node;
    }
}
