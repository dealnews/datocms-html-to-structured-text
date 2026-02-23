#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DealNews\HtmlToStructuredText\Converter;

// Simple HTML
$html = <<<HTML
<h1>Welcome to DatoCMS</h1>
<p>This is a <strong>simple</strong> example of HTML to DAST conversion.</p>
<ul>
    <li>First item</li>
    <li>Second item</li>
</ul>
HTML;

$converter = new Converter();
$dast = $converter->convert($html);

echo "Converted DAST:\n";
echo json_encode($dast, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";
