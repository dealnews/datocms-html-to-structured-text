#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DealNews\HtmlToStructuredText\Converter;

$html = '<h1>Test</h1><p>Para</p>';

$converter = new Converter();
$dast = $converter->convert($html);

echo "Document type: " . gettype($dast['document']) . "\n";
echo "Document keys: " . implode(', ', array_keys($dast['document'])) . "\n";
echo "Children type: " . gettype($dast['document']['children']) . "\n";

if (is_array($dast['document']['children'])) {
    echo "Children count: " . count($dast['document']['children']) . "\n";
    foreach ($dast['document']['children'] as $idx => $child) {
        echo "  Child $idx: type=" . ($child['type'] ?? 'unknown') . "\n";
    }
}

print_r($dast);
