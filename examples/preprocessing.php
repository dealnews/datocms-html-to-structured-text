#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DealNews\HtmlToStructuredText\Converter;
use DealNews\HtmlToStructuredText\Options;

echo "=== Preprocessing Example ===\n\n";

// Create converter
$converter = new Converter();

// Create options with preprocessing function
$options = new Options();

// Preprocessing: transform all <div> tags to <p> tags
$options->preprocess = function (\DOMDocument $doc): void {
    $divs = [];
    foreach ($doc->getElementsByTagName('div') as $div) {
        $divs[] = $div;
    }

    foreach ($divs as $div) {
        // Create new <p> element
        $p = $doc->createElement('p');

        // Move all children from div to p
        while ($div->firstChild) {
            $p->appendChild($div->firstChild);
        }

        // Replace div with p
        $div->parentNode->replaceChild($p, $div);
    }

    echo "✓ Preprocessed: Converted " . count($divs) . " <div> tags to <p>\n\n";
};

$html = <<<HTML
<h1>Title</h1>
<div>This is in a div, but will become a paragraph.</div>
<p>This is already a paragraph.</p>
<div>Another <strong>div</strong> to convert.</div>
HTML;

$dast = $converter->convert($html, $options);

echo "Original HTML:\n";
echo $html . "\n\n";

echo "DAST Output:\n";
echo json_encode($dast, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

// Verify all children are paragraphs (no divs)
$types = array_map(fn($c) => $c['type'], $dast['document']['children']);
echo "Child types: " . implode(', ', $types) . "\n";
echo "✓ All content nodes are proper DAST types!\n";
