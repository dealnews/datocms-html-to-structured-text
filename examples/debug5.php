#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DealNews\HtmlToStructuredText\Visitor;
use DealNews\HtmlToStructuredText\Context;

$html = '<h1>Test</h1>';

$doc = new \DOMDocument();
$prev = libxml_use_internal_errors(true);
$wrapped = '<?xml encoding="UTF-8">' . $html;
$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($prev);

$create_node = function (string $type, array $props): array {
    $props['type'] = $type;
    return $props;
};

$context = new Context();
$context->parent_node_type = 'heading';
$context->allowed_marks = ['strong'];
$context->handlers = [
    'text' => [\DealNews\HtmlToStructuredText\Handlers::class, 'text'],
];
$context->default_handlers = $context->handlers;

// Find h1
foreach ($doc->childNodes as $child) {
    if ($child->nodeName === 'h1') {
        echo "Visiting H1 children...\n";
        $result = Visitor::visitChildren($create_node, $child, $context);
        echo "Result count: " . count($result) . "\n";
        foreach ($result as $idx => $item) {
            echo "  Item $idx: ";
            print_r($item);
        }
    }
}
