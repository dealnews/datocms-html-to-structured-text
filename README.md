# DatoCMS HTML to Structured Text (PHP)

Convert HTML to DatoCMS Structured Text (DAST format). PHP port of the [official JavaScript library](https://github.com/datocms/structured-text/tree/main/packages/html-to-structured-text).

## Requirements

- **PHP 8.2+**
- DOM extension
- libxml extension
- Composer

## Installation

```bash
composer require dealnews/datocms-html-to-structured-text
```

## Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use DealNews\HtmlToStructuredText\Converter;

// Create converter instance
$converter = new Converter();

// Simple HTML
$html = '<h1>DatoCMS</h1><p>The best <strong>headless CMS</strong>.</p>';

$dast = $converter->convert($html);
// Returns:
// [
//     'schema' => 'dast',
//     'document' => [
//         'type' => 'root',
//         'children' => [...]
//     ]
// ]
```

## Features

- ✅ Converts HTML to valid DAST documents
- ✅ Supports all standard HTML elements
- ✅ Custom handlers for specialized conversions
- ✅ DOM preprocessing hooks
- ✅ Configurable allowed blocks, marks, and heading levels
- ✅ Mark extraction from inline CSS styles
- ✅ URL resolution with `<base>` tag support
- ✅ Type-safe with comprehensive PHPDoc

## Supported Elements

### Block Elements

| HTML | DAST Node | Notes |
|------|-----------|-------|
| `<h1>` - `<h6>` | `heading` | Level extracted from tag |
| `<p>` | `paragraph` | |
| `<ul>`, `<ol>` | `list` | Style: bulleted/numbered |
| `<li>` | `listItem` | |
| `<blockquote>` | `blockquote` | |
| `<pre>`, `<code>` | `code` | Language from class attribute |
| `<hr>` | `thematicBreak` | |

### Inline Elements

| HTML | Mark | Notes |
|------|------|-------|
| `<strong>`, `<b>` | `strong` | |
| `<em>`, `<i>` | `emphasis` | |
| `<u>` | `underline` | |
| `<s>`, `<strike>` | `strikethrough` | |
| `<mark>` | `highlight` | |
| `<code>` (inline) | `code` | In paragraph context |
| `<a>` | `link` | With URL and optional meta |
| `<br>` | `span` with `\n` | |

### Ignored Elements

Scripts, styles, and media elements are ignored: `<script>`, `<style>`, `<video>`, `<audio>`, `<iframe>`, `<embed>`

## Advanced Usage

### Custom Handlers

Override default conversion for specific elements:

```php
use DealNews\HtmlToStructuredText\Converter;
use DealNews\HtmlToStructuredText\Options;
use DealNews\HtmlToStructuredText\Handlers;

$converter = new Converter();
$options = new Options();

// Custom h1 handler - adds prefix to all h1 headings
$options->handlers['h1'] = function (
    callable $create_node,
    \DOMNode $node,
    $context
) {
    // Use default handler
    $result = Handlers::heading($create_node, $node, $context);
    
    // Modify result
    if (isset($result['children'][0]['value'])) {
        $result['children'][0]['value'] = '★ ' . $result['children'][0]['value'];
    }
    
    return $result;
};

$html = '<h1>Important</h1>';
$dast = $converter->convert($html, $options);
// H1 will have "★ Important" as text
```

### Preprocessing

Modify the DOM before conversion:

```php
$options = new Options();

// Convert all <div> tags to <p> tags
$options->preprocess = function (\DOMDocument $doc): void {
    $divs = [];
    foreach ($doc->getElementsByTagName('div') as $div) {
        $divs[] = $div;
    }
    
    foreach ($divs as $div) {
        $p = $doc->createElement('p');
        while ($div->firstChild) {
            $p->appendChild($div->firstChild);
        }
        $div->parentNode->replaceChild($p, $div);
    }
};

$html = '<div>Content</div>';
$dast = $converter->convert($html, $options);
// Div becomes paragraph in DAST
```

### Configuring Allowed Blocks

Control which block types are allowed:

```php
$options = new Options();
$options->allowed_blocks = ['paragraph', 'list']; // Only paragraphs and lists

$html = '<h1>Title</h1><p>Text</p>';
$dast = $converter->convert($html, $options);
// H1 will be converted to paragraph
```

### Configuring Allowed Marks

Control which text marks are allowed:

```php
$options = new Options();
$options->allowed_marks = ['strong']; // Only bold

$html = '<p><strong>Bold</strong> and <em>italic</em></p>';
$dast = $converter->convert($html, $options);
// Only strong mark will be applied, emphasis ignored
```

### Configuring Heading Levels

Control which heading levels are preserved:

```php
$options = new Options();
$options->allowed_heading_levels = [1, 2]; // Only H1 and H2

$html = '<h1>H1</h1><h3>H3</h3>';
$dast = $converter->convert($html, $options);
// H3 will be converted to paragraph
```

## Options Reference

### `Options` Class

```php
class Options {
    // Whether to preserve newlines in text
    public bool $newlines = false;
    
    // Custom handler overrides
    public array $handlers = [];
    
    // Preprocessing function
    public $preprocess = null;
    
    // Allowed block types
    public array $allowed_blocks = [
        'blockquote', 'code', 'heading', 'link', 'list'
    ];
    
    // Allowed mark types
    public array $allowed_marks = [
        'strong', 'code', 'emphasis', 'underline', 
        'strikethrough', 'highlight'
    ];
    
    // Allowed heading levels (1-6)
    public array $allowed_heading_levels = [1, 2, 3, 4, 5, 6];
}
```

## API Reference

### `Converter::convert(string $html, ?Options $options = null): ?array`

Converts HTML string to DAST document.

**Parameters:**
- `$html` - HTML string to convert
- `$options` - Optional conversion options

**Returns:** DAST document array or `null` if empty

**Throws:** `ConversionError` if conversion fails

### `Converter::convertDocument(\DOMDocument $doc, ?Options $options = null): ?array`

Converts a DOMDocument to DAST (for pre-parsed HTML).

**Parameters:**
- `$doc` - DOMDocument to convert
- `$options` - Optional conversion options

**Returns:** DAST document array or `null` if empty

**Throws:** `ConversionError` if conversion fails

## Special Features

### Code Block Language Detection

The library extracts programming language from code block class names:

```php
$html = '<pre><code class="language-javascript">const x = 1;</code></pre>';
$dast = $converter->convert($html);
// Result will have: ['type' => 'code', 'language' => 'javascript', 'code' => 'const x = 1;']
```

Default prefix is `language-` but can be customized in context.

### Link Meta Extraction

Link meta attributes (`target`, `rel`, `title`) are extracted:

```php
$html = '<a href="https://example.com" target="_blank" rel="noopener">Link</a>';
$dast = $converter->convert($html);
// Result will have meta array: [['id' => 'target', 'value' => '_blank'], ...]
```

### Inline Style Mark Extraction

The library can extract marks from inline CSS styles:

```php
$html = '<span style="font-weight: bold">Bold via style</span>';
$dast = $converter->convert($html);
// Creates span with strong mark
```

Supported style properties:
- `font-weight: bold` or `font-weight > 400` → `strong`
- `font-style: italic` → `emphasis`
- `text-decoration: underline` → `underline`

### URL Resolution with Base Tag

The `<base>` tag is respected for relative URL resolution:

```php
$html = '<base href="https://example.com/"><a href="/page">Link</a>';
$dast = $converter->convert($html);
// Link URL will be resolved to: https://example.com/page
```

## Error Handling

The library throws `ConversionError` exceptions when conversion fails:

```php
use DealNews\HtmlToStructuredText\ConversionError;

try {
    $dast = $converter->convert($html);
} catch (ConversionError $e) {
    echo "Conversion failed: " . $e->getMessage();
    $node = $e->getNode(); // Get problematic DOM node if available
}
```

## Edge Cases

### Whitespace Handling

- Single whitespace-only spans are removed when wrapped
- Newlines in text are preserved if `$options->newlines = true`
- In headings, newlines are converted to spaces (headings can't have line breaks)

### Nested Lists

Nested lists are fully supported:

```php
$html = '<ul><li>Item<ul><li>Nested</li></ul></li></ul>';
// Converts correctly to nested list structure
```

### Mixed Inline/Block Content

Links and other hybrid elements are handled correctly:

```php
$html = '<a href="#"><span>Inline</span><p>Block</p></a>';
// Properly splits into separate nodes
```

## Differences from JavaScript Version

1. **No Promises**: PHP handlers return directly (synchronous)
2. **No Hast**: Works directly with PHP DOMDocument instead of intermediate tree
3. **Array Structure**: DAST nodes are arrays (not objects)
4. **Error Handling**: Uses exceptions instead of rejection

## Development

### Running Tests

```bash
composer install
./vendor/bin/phpunit
```

Current test coverage: **86%+**

### Running Examples

```bash
php examples/basic.php
php examples/custom_handlers.php
php examples/preprocessing.php
```

## License

BSD 3-Clause License - see LICENSE file for details

## Credits

This is a PHP port of the official [DatoCMS HTML to Structured Text](https://github.com/datocms/structured-text/tree/main/packages/html-to-structured-text) JavaScript library.

Ported and maintained by [DealNews](https://github.com/dealnews).

## Related Projects

- [datocms-structured-text-to-html-string](https://github.com/dealnews/datocms-structured-text-to-html-string) - Convert DAST to HTML (the inverse operation)

