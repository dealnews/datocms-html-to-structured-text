# AI Agent Guide: DatoCMS HTML to Structured Text

This guide helps AI coding assistants understand the architecture, patterns, and conventions used in this PHP library.

## Project Overview

**What it does:** Converts HTML strings to DatoCMS Structured Text (DAST) format — a JSON-serializable document structure used by the DatoCMS headless CMS.

**Why it exists:** DatoCMS stores rich content as DAST, not HTML. This library enables importing HTML content (from legacy systems, WYSIWYG editors, or web scraping) into DatoCMS.

**Key use cases:**
- Migrating content from WordPress/Drupal to DatoCMS
- Converting user-generated HTML to structured content
- Normalizing messy HTML into clean, validated DAST
- Extracting structured data from web pages

**Companion library:** `dealnews/datocms-structured-text-to-html-string` does the reverse (DAST → HTML). Both share architecture and conventions.

**Upstream reference:** JavaScript implementation at [datocms/structured-text](https://github.com/datocms/structured-text/tree/main/packages/html-to-structured-text)

## Architecture & Design

### High-Level Flow

```
HTML String (dirty, unstructured)
    ↓
DOMDocument (PHP's native parser)
    ↓
Visitor (traverses DOM tree)
    ↓
Handlers (convert nodes to DAST)
    ↓
Wrapper (normalizes structure)
    ↓
DAST Document (clean, validated)
```

### Component Roles

**1. Converter** (`src/Converter.php`)
- Entry point for all conversions
- Orchestrates parsing and handler assembly
- Methods: `convert(string $html)`, `convertDocument(\DOMDocument $doc)`
- Handles libxml error suppression for malformed HTML

**2. Visitor** (`src/Visitor.php`)
- Traverses DOM tree recursively
- Dispatches nodes to appropriate handlers
- Accumulates results from child nodes
- **Critical logic:** Distinguishes single DAST nodes from arrays of nodes via `isset($result['type'])`

**3. Handlers** (`src/Handlers.php`)
- 27 static methods, one per HTML element type
- Each converts a DOM node to DAST structure
- Examples: `root()`, `heading()`, `paragraph()`, `link()`, `text()`
- Handler signature: `function(callable $create_node, \DOMNode $node, Context $context): mixed`

**4. Wrapper** (`src/Wrapper.php`)
- Fixes invalid DAST structure
- Wraps inline nodes (span, link) in paragraphs when needed
- Handles hybrid nodes (links containing block content)
- Methods: `wrap()`, `wrapListItems()`, `split()`

**5. Context** (`src/Context.php`)
- Immutable state object passed through conversion
- Tracks: parent node, accumulated marks, handlers, allowed types
- Handlers clone before modifying (prevents side effects)

**6. GlobalContext** (`src/GlobalContext.php`)
- Document-level shared state
- Tracks: base URL (from `<base>` tag), whether base was found
- Mutable (shared across entire conversion)

**7. Utils** (`src/Utils.php`)
- DAST validation rules (`ALLOWED_CHILDREN` constant)
- Type guards: `isDastNode()`, `isDastRoot()`
- Validation: `isAllowedChild()`

**8. Options** (`src/Options.php`)
- Configuration value object
- Custom handlers, preprocessing function, allowed types

**9. ConversionError** (`src/ConversionError.php`)
- Exception thrown on conversion failures
- Stores problematic `\DOMNode` for debugging

### Data Structures

**DAST nodes** are arrays with required `'type'` key:

```php
// Root document
[
    'schema' => 'dast',
    'document' => [
        'type' => 'root',
        'children' => [...]
    ]
]

// Block nodes
['type' => 'heading', 'level' => 1, 'children' => [...]]
['type' => 'paragraph', 'children' => [...]]
['type' => 'list', 'style' => 'bulleted', 'children' => [...]]
['type' => 'code', 'language' => 'php', 'code' => '<?php']

// Inline nodes  
['type' => 'span', 'value' => 'text', 'marks' => ['strong']]
['type' => 'link', 'url' => 'https://...', 'children' => [...]]
```

**Allowed children** enforced by `Utils::ALLOWED_CHILDREN`:

```php
public const ALLOWED_CHILDREN = [
    'root'       => ['heading', 'paragraph', 'list', 'code', 'blockquote', 'thematicBreak'],
    'heading'    => ['inlineNodes'],  // Special: span, link, etc.
    'paragraph'  => ['inlineNodes'],
    'list'       => ['listItem'],
    'listItem'   => ['paragraph', 'list'],
    'blockquote' => ['paragraph'],
    // ...
];
```

**Marks** are strings that modify text styling:

```php
['strong', 'emphasis', 'underline', 'strikethrough', 'highlight', 'code']
```

Accumulated in `Context::$marks` as DOM traverses nested inline elements like `<strong><em>text</em></strong>`.

## Coding Standards (DealNews)

This codebase follows strict DealNews PHP conventions. **Always follow these rules.**

### Braces: 1TBS Style

Same line as statement, except multi-line conditionals. Always use braces.

```php
// ✓ Correct
public function convert(string $html): ?array {
    if ($html === '') {
        return null;
    }
    
    // Multi-line conditional
    if (
        $condition_one &&
        $condition_two
    ) {
        doSomething();
    }
}

// ✗ Wrong
public function convert(string $html): ?array 
{
    if ($html === '')
        return null;  // Missing braces
}
```

### Naming & Visibility

- **Variables/properties:** `snake_case` (not camelCase)
- **Visibility:** `protected` by default (not `private`)
- **Type hints:** Required on all method signatures

```php
// ✓ Correct
protected string $parent_node_type = 'root';

public function convert(string $html, ?Options $options = null): ?array {
    // ...
}

// ✗ Wrong
private $parentNodeType;  // Wrong visibility, missing type, camelCase

public function convert($html, $options) {  // Missing types
    // ...
}
```

### Single Return Point

Prefer single return; early returns OK for validation.

```php
// ✓ Correct
public function findNode(array $nodes, string $type): ?array {
    if (empty($nodes)) {
        return null;  // Early validation OK
    }
    
    $result = null;
    foreach ($nodes as $node) {
        if ($node['type'] === $type) {
            $result = $node;
            break;
        }
    }
    return $result;  // Single main return
}

// ✗ Wrong
public function findNode(array $nodes, string $type): ?array {
    foreach ($nodes as $node) {
        if ($node['type'] === $type) {
            return $node;  // Multiple returns in main logic
        }
    }
    return null;
}
```

### Arrays & Value Objects

- **Short syntax:** `[]` not `array()`
- **Multi-line:** Trailing commas, align associative keys
- **Should not return arrays** for complex data; use value objects

```php
// ✓ Correct arrays
public const MARKS = [
    'strong',
    'emphasis',
    'underline',
];

protected array $handlers = [
    'h1' => 'heading',
    'p'  => 'paragraph',
];

// ✗ Wrong: Returning complex array
public function getNodeData(): array {
    return ['type' => 'span', 'value' => 'text'];
}

// ✓ Correct: Use value object (or DAST array per this library's design)
// Note: DAST nodes are arrays by design (matching upstream JS library)
// but for non-DAST data, prefer value objects
```

### Pass by Reference: Don't

Should not use pass-by-reference. Return values instead.

```php
// ✗ Wrong
public function modify(\DOMNode &$node): void {
    $node->nodeValue = 'changed';
}

// ✓ Correct
public function modify(\DOMNode $node): \DOMNode {
    $node->nodeValue = 'changed';
    return $node;
}

// ✗ Wrong in loops
foreach ($items as &$item) {
    $item->foo = 'bar';
}

// ✓ Correct
foreach ($items as $key => $item) {
    $item->foo = 'bar';
    $items[$key] = $item;
}
```

### PHPDoc: Complete Coverage

All classes and public methods need PHPDoc with `@param`, `@return`, `@throws`.

```php
/**
 * Converts HTML string to DAST document.
 *
 * Parses HTML using DOMDocument and converts to DatoCMS
 * Structured Text format. Handles malformed HTML gracefully.
 *
 * @param string       $html    HTML string to convert
 * @param Options|null $options Optional conversion options
 *
 * @return array<string, mixed>|null DAST document or null if empty
 *
 * @throws ConversionError If conversion fails
 */
public function convert(string $html, ?Options $options = null): ?array {
    // ...
}
```

### Other Key Rules

- **Exceptions:** Catch `\Throwable` not `\Exception`
- **Namespace:** Everything under `DealNews\HtmlToStructuredText\`
- **Class references:** Use `Foo::class` not string `'Foo'`
- **Line length:** Should be ≤80 chars where reasonable
- **File endings:** Unix `\n` only, single newline at end
- **Comments:** Use `//` or `/* */` (not `#`); explain *why* not *how*

## Build & Test

### Running Tests

```bash
# All tests with coverage
./vendor/bin/phpunit --coverage-text

# Single test file
./vendor/bin/phpunit tests/ConverterTest.php

# Single test method
./vendor/bin/phpunit --filter testBasicParagraph tests/ConverterTest.php

# Target: 85%+ coverage (currently 86.17%)
```

### Running Examples

```bash
php examples/basic.php           # Simple HTML to DAST
php examples/custom_handlers.php # Custom handler override
php examples/preprocessing.php   # DOM manipulation before conversion
```

### Installing Dependencies

```bash
composer install
```

## Common Patterns

### Handler Pattern

All handlers follow this signature and pattern:

```php
public static function handlerName(
    callable $create_node,
    \DOMNode $node,
    Context $context
): mixed {
    // 1. Clone context to avoid side effects
    $new_context = clone $context;
    $new_context->parent_node_type = 'paragraph';
    
    // 2. Process children or extract data
    $children = Visitor::visitChildren($create_node, $node, $new_context);
    
    // 3. Return DAST node using $create_node
    return $create_node('paragraph', ['children' => $children]);
}
```

**`$create_node($type, $props)`** automatically adds `'type' => $type` to props.

**Return types:**
- Single DAST node (array with 'type' key)
- Array of DAST nodes
- `null` to skip the element

### Context Cloning

Always clone `Context` before modification:

```php
// ✓ Correct: Clone prevents side effects
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

// ✗ Wrong: Modifying context directly
public static function withMark(...) {
    $context->marks[] = $mark;  // Side effect!
    return Visitor::visitChildren($create_node, $node, $context);
}
```

### Wrapper Unwrapping

The `Wrapper` class detects and wraps inline nodes:

```php
// HTML: <div>Some <strong>text</strong> here</div>
// Produces inline nodes: [span, span, span]
// Wrapper detects and wraps in paragraph:
// [paragraph => [span, span, span]]

// Use Wrapper::wrap() when you have mixed content
$children = Visitor::visitChildren($create_node, $node, $context);
$wrapped = Wrapper::wrap($children);  // Wraps inline runs
```

### Mark Accumulation

Marks accumulate as DOM traverses nested inline elements:

```php
// HTML: <strong><em>Bold and italic</em></strong>
// 
// 1. <strong> handler adds 'strong' to context.marks
// 2. <em> handler (nested) adds 'emphasis' to context.marks  
// 3. Text handler creates span with marks: ['strong', 'emphasis']

// In handlers:
$new_context = clone $context;
$new_context->marks[] = 'strong';  // Add mark
return Visitor::visitChildren($create_node, $node, $new_context);
```

### Allowed Children Validation

Use `Utils::ALLOWED_CHILDREN` to validate structure:

```php
$allowed = Utils::ALLOWED_CHILDREN['paragraph'];  // ['inlineNodes']

foreach ($children as $child) {
    if (!Utils::isAllowedChild('paragraph', $child['type'], $allowed)) {
        // Child not allowed in paragraph
        // Wrapper::wrap() will fix this
    }
}
```

## Testing Strategy

### Test Organization

- **One test class per source class**: `UtilsTest.php` tests `Utils.php`
- **Integration vs unit**: Utils/Wrapper have unit tests; Converter has integration tests
- **Method naming**: `testCamelCaseDescription()`
- **Coverage target**: 85%+ line coverage

### What Tests Verify

**Unit tests** (Utils, Wrapper):
- Method input/output correctness
- Edge cases (empty arrays, null values)
- Validation logic (allowed children, DAST node detection)

**Integration tests** (Converter):
- End-to-end HTML → DAST conversions
- Real HTML snippets (not mocked DOM)
- All element types (headings, lists, links, marks, code blocks)
- Custom options (handlers, preprocessing, allowed types)

### Test Pattern Example

```php
public function testBasicParagraph(): void {
    $converter = new Converter();
    $result = $converter->convert('<p>Hello world</p>');
    
    $this->assertIsArray($result);
    $this->assertEquals('dast', $result['schema']);
    
    $root = $result['document'];
    $this->assertEquals('root', $root['type']);
    $this->assertCount(1, $root['children']);
    
    $paragraph = $root['children'][0];
    $this->assertEquals('paragraph', $paragraph['type']);
}
```

## Gotchas & Edge Cases

### Critical Bug: Visitor Array Merge

**Problem:** `visitChildren()` must distinguish single DAST nodes from arrays of nodes.

**Symptom:** If you use `array_merge()` blindly, DAST nodes (which are arrays) get merged incorrectly.

```php
// ✗ Wrong: Merges node properties into parent array
$values = array_merge($values, $result);  

// ✓ Correct: Check if $result is a DAST node (has 'type' key)
if (is_array($result)) {
    if (isset($result['type'])) {
        $values[] = $result;  // Single node
    } else {
        $values = array_merge($values, $result);  // Array of nodes
    }
}
```

**Location:** `src/Visitor.php:79-87`

### DOMDocument Platform Quirks

**nodeType mismatch:** PHP's `DOMDocument` has `nodeType = 13` (XML_DOCUMENT_TYPE_NODE), not 9 (XML_DOCUMENT_NODE) like the spec.

```php
// ✗ Wrong
if ($node->nodeType === XML_DOCUMENT_NODE) { ... }

// ✓ Correct
if ($node instanceof \DOMDocument) { ... }
```

**XML Processing Instruction:** DOMDocument adds `<?xml encoding="UTF-8">` as a child node. Visitor returns `null` for unhandled node types, so it gets filtered out automatically.

**UTF-8 Handling:** Wrap HTML with XML encoding declaration for proper UTF-8:

```php
$wrapped = '<?xml encoding="UTF-8">' . $html;
$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
```

### Empty Content Returns Null

If HTML converts to zero DAST nodes, `convert()` returns `null` (not empty array):

```php
$result = $converter->convert('<script>ignored</script>');
// Returns: null (scripts are ignored)

$result = $converter->convert('<p>Text</p>');  
// Returns: ['schema' => 'dast', 'document' => [...]]
```

### Inline Nodes Need Wrapping

DAST has strict structure rules. Inline nodes (span, link) cannot be direct children of root or blockquote — they need paragraph wrappers.

**Wrapper handles this automatically:**

```php
// HTML: <div>Some <a href="#">link</a> text</div>
// Without Wrapper: root → [span, link, span] ← INVALID
// With Wrapper: root → [paragraph → [span, link, span]] ← VALID

// In handlers:
$children = Visitor::visitChildren($create_node, $node, $context);
if ($needs_wrapping) {
    $children = Wrapper::wrap($children);  // Wraps inline runs
}
```

### Handler Return Values

Handlers can return:
- **DAST node** (array with 'type'): Added as child
- **Array of DAST nodes**: All added as children
- **`null`**: Node skipped (e.g., `<script>`, `<style>`)

```php
// Single node
return $create_node('heading', ['level' => 1, 'children' => [...]]);

// Multiple nodes (rare: usually for splitting content)
return [
    $create_node('paragraph', [...]),
    $create_node('paragraph', [...]),
];

// Skip node
return null;  // Used for <script>, <style>, etc.
```

## Making Changes

### Adding a New HTML Element Handler

1. **Add handler method** in `src/Handlers.php`:

```php
/**
 * Handler for <custom> element
 *
 * @param callable $create_node Function to create DAST nodes
 * @param \DOMNode $node        DOM node
 * @param Context  $context     Conversion context
 *
 * @return array<string, mixed>|null DAST node
 */
public static function custom(
    callable $create_node,
    \DOMNode $node,
    Context $context
): ?array {
    $new_context = clone $context;
    $children = Visitor::visitChildren($create_node, $node, $new_context);
    return $create_node('customBlock', ['children' => $children]);
}
```

2. **Register handler** in `src/Converter.php::buildHandlers()`:

```php
protected function buildHandlers(): array {
    return [
        // ...
        'custom' => [Handlers::class, 'custom'],
    ];
}
```

3. **Add DAST type to allowed children** in `src/Utils.php::ALLOWED_CHILDREN`:

```php
public const ALLOWED_CHILDREN = [
    'root' => ['heading', 'paragraph', 'customBlock', /* ... */],
    'customBlock' => ['inlineNodes'],
];
```

4. **Write tests** in `tests/ConverterTest.php`:

```php
public function testCustomElement(): void {
    $converter = new Converter();
    $result = $converter->convert('<custom>Content</custom>');
    
    $custom = $result['document']['children'][0];
    $this->assertEquals('customBlock', $custom['type']);
}
```

### Adding a New Mark Type

1. **Add mark constant** in `src/Utils.php::INLINE_NODE_TYPES` or create `MARK_TYPES` constant

2. **Add handler** for HTML element in `src/Handlers.php`:

```php
public static function customMark(
    callable $create_node,
    \DOMNode $node,
    Context $context
): mixed {
    return self::withMark($create_node, $node, $context, 'customMark');
}
```

3. **Register handler** in `Converter::buildHandlers()`:

```php
'customtag' => [Handlers::class, 'customMark'],
```

4. **Update Options defaults** if needed in `src/Options.php`:

```php
public array $allowed_marks = [
    'strong', 'emphasis', 'customMark', /* ... */
];
```

### Fixing a Bug

1. **Write failing test** that reproduces the bug
2. **Identify the component** (Visitor, Handlers, Wrapper, Utils)
3. **Make minimal fix** following coding standards
4. **Verify test passes** and coverage doesn't drop
5. **Run full suite** to check for regressions

### Workflow Example

```bash
# 1. Write test
vim tests/ConverterTest.php  # Add testNewFeature()

# 2. Run test (should fail)
./vendor/bin/phpunit --filter testNewFeature

# 3. Implement feature
vim src/Handlers.php  # Add handler

# 4. Verify test passes
./vendor/bin/phpunit --filter testNewFeature

# 5. Check coverage
./vendor/bin/phpunit --coverage-text

# 6. Run full suite
./vendor/bin/phpunit
```

## Quick Reference

### File Purposes

- `Converter.php` - Entry point, orchestration
- `Visitor.php` - DOM tree traversal
- `Handlers.php` - Element-to-DAST conversion (27 methods)
- `Wrapper.php` - Structure normalization (wrapping inline nodes)
- `Utils.php` - DAST validation rules and helpers
- `Context.php` - Conversion state (immutable)
- `GlobalContext.php` - Document-level state (mutable)
- `Options.php` - Configuration value object
- `ConversionError.php` - Exception class

### When to Use Each Component

- **Adding element support?** → `Handlers.php` + `Converter::buildHandlers()`
- **Fixing structure issues?** → `Wrapper.php`
- **Changing validation rules?** → `Utils.php::ALLOWED_CHILDREN`
- **Adding configuration?** → `Options.php`
- **Traversal logic?** → `Visitor.php` (rarely needs changes)
- **Pre/post processing?** → `Options::$preprocess` (for preprocessing) or add to `Converter` (for post-processing)

### Key Constants

```php
Utils::INLINE_NODE_TYPES      // ['span', 'link', ...]
Utils::ALLOWED_CHILDREN       // Parent-child validation map
```

### Important Methods

```php
Converter::convert(string $html): ?array
Visitor::visitNode(callable $create_node, \DOMNode $node, Context $context): mixed
Wrapper::wrap(array $nodes): array
Utils::isAllowedChild(string $parent, string $child, array $allowed): bool
```
