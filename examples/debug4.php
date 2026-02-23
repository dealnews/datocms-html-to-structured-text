#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

$html = '<h1>Test</h1>';

$doc = new \DOMDocument();
$prev = libxml_use_internal_errors(true);
$wrapped = '<?xml encoding="UTF-8">' . $html;
$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($prev);

// Find h1
foreach ($doc->childNodes as $child) {
    if ($child->nodeName === 'h1') {
        echo "H1 children count: " . $child->childNodes->length . "\n";
        foreach ($child->childNodes as $grandchild) {
            echo "  Child: '" . $grandchild->nodeName . "' type=" . $grandchild->nodeType;
            if ($grandchild->nodeType === XML_TEXT_NODE) {
                echo " value='" . $grandchild->textContent . "'";
            }
            echo "\n";
        }
    }
}
