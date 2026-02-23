<?php

namespace DealNews\HtmlToStructuredText;

/**
 * Global state shared across conversion
 *
 * Tracks base URL resolution and other document-level context
 * during HTML to DAST conversion.
 */
class GlobalContext {
    /**
     * Whether a `<base>` tag has been found
     *
     * Prevents processing multiple base tags.
     *
     * @var bool
     */
    public bool $base_url_found = false;

    /**
     * Base URL from `<base>` tag
     *
     * Used for resolving relative URLs in links.
     *
     * @var string|null
     */
    public ?string $base_url = null;
}
