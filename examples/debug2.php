#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DealNews\HtmlToStructuredText\Converter;
use DealNews\HtmlToStructuredText\Visitor;
use DealNews\HtmlToStructuredText\Context;

// Simple HTML
$html = '<h1>Test</h1>';

$doc = new \DOMDocument();
$prev = libxml_use_internal_errors(true);
$wrapped = '<?xml encoding="UTF-8">' . $html;
$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($prev);

$create_node = function (string $type, array $props): array {
    $props['type'] = $type;
    echo "Creating node: type=$type, keys=" . implode(',', array_keys($props)) . "\n";
    if (isset($props['children'])) {
        echo "  Children type: " . gettype($props['children']) . ", count: ";
        if (is_array($props['children'])) {
            echo count($props['children']);
        } else {
            echo "N/A";
        }
        echo "\n";
    }
    return $props;
};

$context = new Context();
$context->parent_node_type = 'root';
$context->allowed_blocks = ['heading'];
$context->allowed_marks = ['strong'];
$context->allowed_heading_levels = [1, 2, 3, 4, 5, 6];
$context->handlers = [
    'root' => [\DealNews\HtmlToStructuredText\Handlers::class, 'root'],
    'h1' => [\DealNews\HtmlToStructuredText\Handlers::class, 'heading'],
    'text' => [\DealNews\HtmlToStructuredText\Handlers::class, 'text'],
];
$context->default_handlers = $context->handlers;

$result = Visitor::visitNode($create_node, $doc, $context);

echo "\nFinal result:\n";
print_r($result);
