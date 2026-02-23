#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DealNews\HtmlToStructuredText\Converter;
use DealNews\HtmlToStructuredText\Options;

echo "=== Custom Handler Example ===\n\n";

// Create converter
$converter = new Converter();

// Create options with custom handler
$options = new Options();

// Custom handler for h1 tags - adds "★ " prefix to all h1 headings
$options->handlers['h1'] = function (
    callable $create_node,
    \DOMNode $node,
    $context
) {
    // Use default heading handler first
    $result = \DealNews\HtmlToStructuredText\Handlers::heading(
        $create_node,
        $node,
        $context
    );

    // Modify the first span's value to add star prefix
    if (isset($result['children'][0]['value'])) {
        $result['children'][0]['value'] = '★ ' . $result['children'][0]['value'];
    }

    return $result;
};

$html = <<<HTML
<h1>Important Title</h1>
<p>This heading will have a star prefix.</p>
<h2>Regular Subtitle</h2>
<p>This h2 won't be affected.</p>
HTML;

$dast = $converter->convert($html, $options);

echo "HTML:\n";
echo $html . "\n\n";

echo "DAST Output:\n";
echo json_encode($dast, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Verify the modification worked
$h1_text = $dast['document']['children'][0]['children'][0]['value'];
echo "H1 text value: \"$h1_text\"\n";
echo "✓ Custom handler successfully added star prefix!\n";
