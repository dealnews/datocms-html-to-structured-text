#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

$html = '<h1>Test</h1><p>Para</p>';

$doc = new \DOMDocument();
$prev = libxml_use_internal_errors(true);
$wrapped = '<?xml encoding="UTF-8">' . $html;
$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();
libxml_use_internal_errors($prev);

echo "Node type: " . $doc->nodeType . " (XML_DOCUMENT_NODE=" . XML_DOCUMENT_NODE . ")\n";
echo "Node name: " . $doc->nodeName . "\n";
echo "Children count: " . $doc->childNodes->length . "\n";

foreach ($doc->childNodes as $child) {
    echo "  Child: " . $child->nodeName . " (type=" . $child->nodeType . ")\n";
    if ($child->hasChildNodes()) {
        foreach ($child->childNodes as $grandchild) {
            echo "    Grandchild: " . $grandchild->nodeName . " (type=" . $grandchild->nodeType . ")\n";
        }
    }
}
