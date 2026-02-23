# Copilot Instructions: DatoCMS HTML to Structured Text

PHP library that converts HTML to DatoCMS Structured Text (DAST) format. Companion to `datocms-structured-text-to-html-string` (reverse operation).

## Build & Test Commands

```bash
# Run all tests with coverage
./vendor/bin/phpunit --coverage-text

# Run single test file
./vendor/bin/phpunit tests/ConverterTest.php

# Run single test method
./vendor/bin/phpunit --filter testBasicParagraph tests/ConverterTest.php

# Install dependencies
composer install

# Run examples
php examples/basic.php
php examples/custom_handlers.php
php examples/preprocessing.php
```

**Coverage target:** 85%+ line coverage (currently 86.17%)

## Architecture Overview

### Core Conversion Flow

```
HTML String
    ↓
DOMDocument (via loadHTML)
    ↓
Visitor (traverses DOM tree)
    ↓
Handlers (converts nodes to DAST)
    ↓
Wrapper (normalizes structure)
    ↓
DAST Document
```

### Key Classes

1. **`Converter`** - Entry point; orchestrates conversion
   - `convert(string $html)` - Main API
   - `convertDocument(\DOMDocument $doc)` - For pre-parsed HTML
   - `buildHandlers()` - Assembles handler map

2. **`Visitor`** - DOM tree traversal
   - `visitNode()` - Dispatches to appropriate handler
   - `visitChildren()` - Recursively processes child nodes
   - **Critical:** Checks `isset($result['type'])` to distinguish single DAST nodes from arrays

3. **`Handlers`** - 27 static methods converting HTML elements to DAST
   - Each handler signature: `function(callable $create_node, \DOMNode $node, Context $context): mixed`
   - Returns: DAST node (array), array of nodes, or `null` to skip
   - Examples: `root()`, `heading()`, `paragraph()`, `link()`, `text()`

4. **`Wrapper`** - DAST structure normalization
   - `wrap()` - Wraps inline nodes (span, link) in paragraphs
   - `wrapListItems()` - Ensures list children are listItem nodes
   - `split()` - Handles hybrid nodes (links containing block content)

5. **`Utils`** - DAST validation helpers
   - `ALLOWED_CHILDREN` - Map of valid parent→child relationships
   - `isAllowedChild()` - Validates node structure
   - `isDastNode()`, `isDastRoot()` - Type guards

6. **`Context`** - Conversion state passed to handlers
   - Immutable pattern: handlers clone before modifying
   - Tracks: parent node, marks, handlers, allowed types
   - `$global` - Document-level shared state (base URL)

### Handler Pattern

Handlers are callables that convert DOM nodes to DAST:

```php
function(callable $create_node, \DOMNode $node, Context $context): mixed {
    // Clone context to avoid side effects
    $new_context = clone $context;
    
    // Use $create_node to build DAST nodes
    return $create_node('paragraph', ['children' => [...]]);
}
```

**`$create_node($type, $props)`** automatically adds `'type' => $type` to props and returns the node.

### DAST Structure

All DAST nodes are arrays with required `'type'` key:

```php
// Root document
['schema' => 'dast', 'document' => ['type' => 'root', 'children' => [...]]]

// Block nodes
['type' => 'heading', 'level' => 1, 'children' => [...]]
['type' => 'paragraph', 'children' => [...]]
['type' => 'list', 'style' => 'bulleted', 'children' => [...]]
['type' => 'code', 'language' => 'php', 'code' => '...']

// Inline nodes
['type' => 'span', 'value' => 'text', 'marks' => ['strong', 'emphasis']]
['type' => 'link', 'url' => '...', 'children' => [...], 'meta' => [...]]
```

**Allowed children** are enforced by `Utils::ALLOWED_CHILDREN`. Special value `'inlineNodes'` means any of: `span`, `link`, `itemLink`, `inlineItem`, `inlineBlock`.

### Mark Handling

Marks are strings accumulated in Context as DOM traverses nested inline elements:

```php
<strong><em>Text</em></strong>
// Creates: ['type' => 'span', 'value' => 'Text', 'marks' => ['strong', 'emphasis']]
```

**Available marks:** `'strong'`, `'code'`, `'emphasis'`, `'underline'`, `'strikethrough'`, `'highlight'`

Marks can also be extracted from inline CSS via `Handlers::extractInlineStyles()`:
- `font-weight: bold` → `'strong'`
- `font-style: italic` → `'emphasis'`
- `text-decoration: underline` → `'underline'`

## Critical Implementation Details

### Visitor Array Merge Bug (Fixed)

**Problem:** `visitChildren()` initially used `array_merge($values, $result)` for all array results, which incorrectly merged DAST nodes directly into parent array.

**Solution:** Check `isset($result['type'])` to distinguish single DAST nodes from arrays:

```php
// Correct implementation (src/Visitor.php:79-87)
if (is_array($result)) {
    if (isset($result['type'])) {
        // Single DAST node
        $values[] = $result;
    } else {
        // Array of nodes
        $values = array_merge($values, $result);
    }
}
```

### DOMDocument Platform Quirks

1. **nodeType:** PHP's `DOMDocument` has `nodeType = 13` (not 9). Use `instanceof \DOMDocument` instead of nodeType comparison.

2. **XML Processing Instruction:** DOMDocument adds `<?xml encoding="UTF-8">` which appears as child node. Visitor returns `null` for unhandled types, so it's filtered out.

3. **HTML Parsing:** Use `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` flags and wrap with `<?xml encoding="UTF-8">` prefix for proper UTF-8 handling.

### Context Cloning Pattern

Always clone context before modification to prevent side effects:

```php
public static function withMark(
    callable $create_node,
    \DOMNode $node,
    Context $context,
    string $mark
): mixed {
    $new_context = clone $context;  // Clone first!
    $new_context->marks[] = $mark;
    return Visitor::visitChildren($create_node, $node, $new_context);
}
```

## Coding Standards (DealNews)

This codebase follows strict DealNews PHP standards:

### Braces (1TBS)
- Same line as statement, except multi-line conditionals
- Always use braces (no braceless ifs)

```php
// ✓ Correct
public function foo() {
    if (condition) {
        bar();
    }
}

// Multi-line conditional
if (
    long_condition &&
    another_condition
) {
    bar();
}
```

### Naming & Visibility
- Variables/properties: `snake_case` (not camelCase)
- Visibility: `protected` by default (not `private`)
- Type hints: Required on all methods

```php
// ✓ Correct
protected string $parent_node_type = 'root';

public function convert(string $html, ?Options $options = null): ?array {
    // ...
}
```

### Single Return Point
- Should have single return point
- Early returns acceptable for validation

```php
// ✓ Correct
public function process(int $value): bool {
    if ($value < 0) {
        return false;  // Early validation OK
    }
    
    $result = false;
    // ... logic ...
    if ($condition) {
        $result = true;
    }
    return $result;  // Single main return
}
```

### Arrays & Types
- Short syntax: `[]` not `array()`
- Multi-line: trailing commas
- Should not return mixed types
- Should not use pass-by-reference

```php
// ✓ Correct
public const ALLOWED = [
    'heading'    => ['inlineNodes'],
    'paragraph'  => ['inlineNodes'],
    'list'       => ['listItem'],
];

public function getData(): ValueObject {  // Not array
    // Return value object instead of array
}
```

### PHPDoc
- Required on all classes and public methods
- Include `@param`, `@return`, `@throws`

```php
/**
 * Converts HTML to DAST format.
 *
 * @param string       $html    HTML string to convert
 * @param Options|null $options Conversion options
 *
 * @return array<string, mixed>|null DAST document or null
 *
 * @throws ConversionError If conversion fails
 */
public function convert(string $html, ?Options $options = null): ?array {
```

## Testing Patterns

### Test Organization
- One test class per source class
- Test methods: `testCamelCaseDescription()`
- Use data providers for multiple scenarios

```php
public function testBasicParagraph(): void {
    $converter = new Converter();
    $result = $converter->convert('<p>Hello</p>');
    
    $this->assertIsArray($result);
    $this->assertEquals('dast', $result['schema']);
}
```

### Integration vs Unit
- **Unit tests:** Utils, Wrapper (test methods in isolation)
- **Integration tests:** Converter (test full HTML→DAST conversions)
- Use actual HTML snippets, not mocked DOM

### Running Tests
```bash
# All tests
./vendor/bin/phpunit --coverage-text

# Single test
./vendor/bin/phpunit --filter testHeadingLevels tests/ConverterTest.php

# Specific test file
./vendor/bin/phpunit tests/WrapperTest.php
```

## Custom Handlers

Override default conversion by providing custom handlers in `Options`:

```php
$options = new Options();
$options->handlers['h1'] = function($create_node, $node, $context) {
    // Custom h1 conversion
    return $create_node('heading', [
        'level' => 1,
        'children' => [/* custom logic */]
    ]);
};

$result = $converter->convert($html, $options);
```

Handler signature: `function(callable $create_node, \DOMNode $node, Context $context): mixed`

**Returns:**
- Single DAST node (array with 'type' key)
- Array of DAST nodes
- `null` to skip the node

## DOM Preprocessing

Modify DOM before conversion using `preprocess` callable:

```php
$options = new Options();
$options->preprocess = function(\DOMDocument $doc): void {
    // Remove all images
    $xpath = new \DOMXPath($doc);
    foreach ($xpath->query('//img') as $img) {
        $img->parentNode->removeChild($img);
    }
};

$result = $converter->convert($html, $options);
```

## Related Libraries

**Companion library:** `dealnews/datocms-structured-text-to-html-string` - Reverse operation (DAST→HTML)

Both libraries share:
- Same namespace prefix (`DealNews\`)
- Same coding standards
- Similar architecture (handler pattern, context objects)
- Value objects for DAST nodes

**Upstream:** [datocms/structured-text](https://github.com/datocms/structured-text) - Official JavaScript implementation
