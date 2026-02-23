<?php

namespace DealNews\HtmlToStructuredText\Tests;

use DealNews\HtmlToStructuredText\Converter;
use DealNews\HtmlToStructuredText\Options;
use DealNews\HtmlToStructuredText\ConversionError;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for HTML to DAST conversion
 */
class ConverterTest extends TestCase {
    /**
     * @var Converter
     */
    protected Converter $converter;

    /**
     * Set up test converter
     */
    protected function setUp(): void {
        $this->converter = new Converter();
    }

    /**
     * Test basic heading conversion
     */
    public function testConvertSimpleHeading(): void {
        $html = '<h1>Hello World</h1>';
        $result = $this->converter->convert($html);

        $this->assertIsArray($result);
        $this->assertEquals('dast', $result['schema']);
        $this->assertEquals('root', $result['document']['type']);
        
        $children = $result['document']['children'];
        $this->assertCount(1, $children);
        $this->assertEquals('heading', $children[0]['type']);
        $this->assertEquals(1, $children[0]['level']);
        $this->assertEquals('Hello World', $children[0]['children'][0]['value']);
    }

    /**
     * Test paragraph with text
     */
    public function testConvertSimpleParagraph(): void {
        $html = '<p>This is a paragraph.</p>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'];
        $this->assertCount(1, $children);
        $this->assertEquals('paragraph', $children[0]['type']);
        $this->assertEquals('This is a paragraph.', $children[0]['children'][0]['value']);
    }

    /**
     * Test paragraph with strong mark
     */
    public function testConvertParagraphWithStrong(): void {
        $html = '<p>This is <strong>bold</strong> text.</p>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'][0]['children'];
        $this->assertCount(3, $children); // "This is ", "bold", " text."
        
        $this->assertEquals('This is ', $children[0]['value']);
        $this->assertArrayNotHasKey('marks', $children[0]);
        
        $this->assertEquals('bold', $children[1]['value']);
        $this->assertContains('strong', $children[1]['marks']);
        
        $this->assertEquals(' text.', $children[2]['value']);
    }

    /**
     * Test paragraph with emphasis mark
     */
    public function testConvertParagraphWithEmphasis(): void {
        $html = '<p>Text with <em>emphasis</em> here.</p>';
        $result = $this->converter->convert($html);

        $spans = $result['document']['children'][0]['children'];
        $this->assertEquals('emphasis', $spans[1]['value']);
        $this->assertContains('emphasis', $spans[1]['marks']);
    }

    /**
     * Test paragraph with multiple marks
     */
    public function testConvertParagraphWithMultipleMarks(): void {
        $html = '<p><strong><em>Bold and italic</em></strong></p>';
        $result = $this->converter->convert($html);

        $span = $result['document']['children'][0]['children'][0];
        $this->assertEquals('Bold and italic', $span['value']);
        $this->assertContains('strong', $span['marks']);
        $this->assertContains('emphasis', $span['marks']);
    }

    /**
     * Test unordered list
     */
    public function testConvertUnorderedList(): void {
        $html = '<ul><li>First</li><li>Second</li></ul>';
        $result = $this->converter->convert($html);

        $list = $result['document']['children'][0];
        $this->assertEquals('list', $list['type']);
        $this->assertEquals('bulleted', $list['style']);
        $this->assertCount(2, $list['children']);
        $this->assertEquals('listItem', $list['children'][0]['type']);
        $this->assertEquals('listItem', $list['children'][1]['type']);
    }

    /**
     * Test ordered list
     */
    public function testConvertOrderedList(): void {
        $html = '<ol><li>One</li><li>Two</li></ol>';
        $result = $this->converter->convert($html);

        $list = $result['document']['children'][0];
        $this->assertEquals('list', $list['type']);
        $this->assertEquals('numbered', $list['style']);
    }

    /**
     * Test link
     */
    public function testConvertLink(): void {
        $html = '<p>Visit <a href="https://example.com">our site</a>.</p>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'][0]['children'];
        
        $link = $children[1];
        $this->assertEquals('link', $link['type']);
        $this->assertEquals('https://example.com', $link['url']);
        $this->assertEquals('our site', $link['children'][0]['value']);
    }

    /**
     * Test link with meta attributes
     */
    public function testConvertLinkWithMeta(): void {
        $html = '<a href="https://example.com" target="_blank" rel="noopener" title="Example">Link</a>';
        $result = $this->converter->convert($html);

        // Link at root gets wrapped in paragraph
        $link = $result['document']['children'][0]['children'][0];
        
        $this->assertEquals('link', $link['type']);
        $this->assertArrayHasKey('meta', $link);
        
        $meta_keys = array_column($link['meta'], 'id');
        $this->assertContains('target', $meta_keys);
        $this->assertContains('rel', $meta_keys);
        $this->assertContains('title', $meta_keys);
    }

    /**
     * Test code block
     */
    public function testConvertCodeBlock(): void {
        $html = '<pre><code>const x = 1;</code></pre>';
        $result = $this->converter->convert($html);

        $code = $result['document']['children'][0];
        $this->assertEquals('code', $code['type']);
        $this->assertEquals('const x = 1;', $code['code']);
    }

    /**
     * Test code block with language
     */
    public function testConvertCodeBlockWithLanguage(): void {
        $html = '<pre><code class="language-javascript">const x = 1;</code></pre>';
        $result = $this->converter->convert($html);

        $code = $result['document']['children'][0];
        $this->assertEquals('code', $code['type']);
        $this->assertEquals('javascript', $code['language']);
        $this->assertEquals('const x = 1;', $code['code']);
    }

    /**
     * Test blockquote
     */
    public function testConvertBlockquote(): void {
        $html = '<blockquote><p>Quote text</p></blockquote>';
        $result = $this->converter->convert($html);

        $blockquote = $result['document']['children'][0];
        $this->assertEquals('blockquote', $blockquote['type']);
        $this->assertCount(1, $blockquote['children']);
        $this->assertEquals('paragraph', $blockquote['children'][0]['type']);
    }

    /**
     * Test thematic break
     */
    public function testConvertThematicBreak(): void {
        $html = '<hr>';
        $result = $this->converter->convert($html);

        $this->assertEquals('thematicBreak', $result['document']['children'][0]['type']);
    }

    /**
     * Test multiple headings
     */
    public function testConvertMultipleHeadings(): void {
        $html = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'];
        $this->assertCount(3, $children);
        $this->assertEquals(1, $children[0]['level']);
        $this->assertEquals(2, $children[1]['level']);
        $this->assertEquals(3, $children[2]['level']);
    }

    /**
     * Test mixed content
     */
    public function testConvertMixedContent(): void {
        $html = '<h1>Title</h1><p>Intro text</p><ul><li>Item</li></ul>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'];
        $this->assertCount(3, $children);
        $this->assertEquals('heading', $children[0]['type']);
        $this->assertEquals('paragraph', $children[1]['type']);
        $this->assertEquals('list', $children[2]['type']);
    }

    /**
     * Test empty HTML
     */
    public function testConvertEmptyHTML(): void {
        $result = $this->converter->convert('');
        $this->assertNull($result);
    }

    /**
     * Test whitespace-only HTML
     */
    public function testConvertWhitespaceOnlyHTML(): void {
        $result = $this->converter->convert('   ');
        
        // Whitespace may be wrapped in paragraph or return null
        $this->assertTrue(
            $result === null || 
            (isset($result['schema']) && $result['schema'] === 'dast')
        );
    }

    /**
     * Test br tag creates newline span
     */
    public function testConvertBrTag(): void {
        $html = '<p>Line one<br>Line two</p>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'][0]['children'];
        
        // Should have: "Line one", "\n", "Line two"
        $this->assertGreaterThanOrEqual(2, count($children));
        
        $has_newline = false;
        foreach ($children as $child) {
            if ($child['value'] === "\n") {
                $has_newline = true;
                break;
            }
        }
        $this->assertTrue($has_newline, 'Should contain newline span from <br>');
    }

    /**
     * Test inline code
     */
    public function testConvertInlineCode(): void {
        $html = '<p>Use the <code>function()</code> method.</p>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'][0]['children'];
        
        $code_span = null;
        foreach ($children as $child) {
            if (isset($child['marks']) && in_array('code', $child['marks'])) {
                $code_span = $child;
                break;
            }
        }
        
        $this->assertNotNull($code_span);
        $this->assertContains('code', $code_span['marks']);
    }

    /**
     * Test underline mark
     */
    public function testConvertUnderline(): void {
        $html = '<p><u>Underlined text</u></p>';
        $result = $this->converter->convert($html);

        $span = $result['document']['children'][0]['children'][0];
        $this->assertContains('underline', $span['marks']);
    }

    /**
     * Test strikethrough mark
     */
    public function testConvertStrikethrough(): void {
        $html = '<p><s>Strikethrough</s></p>';
        $result = $this->converter->convert($html);

        $span = $result['document']['children'][0]['children'][0];
        $this->assertContains('strikethrough', $span['marks']);
    }

    /**
     * Test mark/highlight
     */
    public function testConvertHighlight(): void {
        $html = '<p><mark>Highlighted</mark></p>';
        $result = $this->converter->convert($html);

        $span = $result['document']['children'][0]['children'][0];
        $this->assertContains('highlight', $span['marks']);
    }

    /**
     * Test custom options - allowed_blocks
     */
    public function testConvertWithAllowedBlocksOption(): void {
        $options = new Options();
        $options->allowed_blocks = ['paragraph']; // Only paragraphs

        $html = '<h1>Title</h1><p>Para</p>';
        $result = $this->converter->convert($html, $options);

        // Heading should be converted to paragraph
        $children = $result['document']['children'];
        
        foreach ($children as $child) {
            $this->assertEquals('paragraph', $child['type']);
        }
    }

    /**
     * Test custom options - allowed_marks
     */
    public function testConvertWithAllowedMarksOption(): void {
        $options = new Options();
        $options->allowed_marks = ['strong']; // Only strong

        $html = '<p><strong>Bold</strong> and <em>italic</em></p>';
        $result = $this->converter->convert($html, $options);

        $children = $result['document']['children'][0]['children'];
        
        $has_strong = false;
        $has_emphasis = false;
        
        foreach ($children as $child) {
            if (isset($child['marks'])) {
                if (in_array('strong', $child['marks'])) {
                    $has_strong = true;
                }
                if (in_array('emphasis', $child['marks'])) {
                    $has_emphasis = true;
                }
            }
        }
        
        $this->assertTrue($has_strong);
        $this->assertFalse($has_emphasis);
    }

    /**
     * Test custom options - allowed_heading_levels
     */
    public function testConvertWithAllowedHeadingLevelsOption(): void {
        $options = new Options();
        $options->allowed_heading_levels = [1, 2]; // Only h1 and h2

        $html = '<h1>H1</h1><h2>H2</h2><h3>H3</h3>';
        $result = $this->converter->convert($html, $options);

        $children = $result['document']['children'];
        
        // H1 and H2 should be headings, H3 should be paragraph
        $headings = array_filter($children, fn($c) => $c['type'] === 'heading');
        $paragraphs = array_filter($children, fn($c) => $c['type'] === 'paragraph');
        
        $this->assertCount(2, $headings);
        $this->assertCount(1, $paragraphs);
    }

    /**
     * Test ignored elements
     */
    public function testConvertIgnoresScriptAndStyle(): void {
        $html = '<p>Text</p><script>alert("test")</script><style>body{}</style>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'];
        
        // Should only have paragraph, script and style ignored
        $this->assertCount(1, $children);
        $this->assertEquals('paragraph', $children[0]['type']);
    }

    /**
     * Test nested lists
     */
    public function testConvertNestedLists(): void {
        $html = '<ul><li>Item 1<ul><li>Nested</li></ul></li></ul>';
        $result = $this->converter->convert($html);

        $outer_list = $result['document']['children'][0];
        $this->assertEquals('list', $outer_list['type']);
        
        $first_item = $outer_list['children'][0];
        $this->assertEquals('listItem', $first_item['type']);
        
        // Check for nested list
        $has_nested_list = false;
        foreach ($first_item['children'] as $child) {
            if ($child['type'] === 'list') {
                $has_nested_list = true;
                break;
            }
        }
        $this->assertTrue($has_nested_list);
    }

    /**
     * Test extracting marks from inline styles
     */
    public function testConvertExtractsMarksFromInlineStyles(): void {
        $html = '<p><span style="font-weight: bold">Bold via style</span></p>';
        $result = $this->converter->convert($html);

        $children = $result['document']['children'][0]['children'];
        
        $has_strong = false;
        foreach ($children as $child) {
            if (isset($child['marks']) && in_array('strong', $child['marks'])) {
                $has_strong = true;
                break;
            }
        }
        
        $this->assertTrue($has_strong);
    }

    /**
     * Test DOMDocument conversion
     */
    public function testConvertDocument(): void {
        $doc = new \DOMDocument();
        $doc->loadHTML('<h1>Title</h1>');

        $result = $this->converter->convertDocument($doc);

        $this->assertIsArray($result);
        $this->assertEquals('dast', $result['schema']);
        $this->assertEquals('heading', $result['document']['children'][0]['type']);
    }
}
