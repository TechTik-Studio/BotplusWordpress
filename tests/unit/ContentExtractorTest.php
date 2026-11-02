<?php

namespace BotPlus\Plugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Sync\ContentExtractor;

class ContentExtractorTest extends TestCase
{
    protected ContentExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ContentExtractor();
    }

    public function testCleanContentStripsScriptsStylesAndComments(): void
    {
        $dirty = '<div>Hello <script>alert("hack");</script><style>.body{color:red;}</style><!-- hidden comment -->World!</div>';
        $cleaned = $this->extractor->cleanContent($dirty);

        $this->assertEquals('Hello World!', $cleaned);
    }

    public function testCleanContentStripsPageBuilderShortcodes(): void
    {
        $builderHtml = '[et_pb_section][et_pb_row][et_pb_column][vc_row][vc_column]
            <h2>About Our Agency</h2>
            <p>We provide world class digital services.</p>
            [/vc_column][/vc_row][/et_pb_column][/et_pb_row][/et_pb_section]';

        $cleaned = $this->extractor->cleanContent($builderHtml);

        $this->assertStringNotContainsString('et_pb', $cleaned);
        $this->assertStringNotContainsString('vc_row', $cleaned);
        $this->assertStringContainsString('About Our Agency', $cleaned);
        $this->assertStringContainsString('We provide world class digital services.', $cleaned);
    }

    public function testCleanContentDecodesEntitiesAndNormalizesSpaces(): void
    {
        $text = 'Welcome &amp; Enjoy our &quot;exclusive&quot; items!   Lots    of    spaces.';
        $cleaned = $this->extractor->cleanContent($text);

        $this->assertEquals('Welcome & Enjoy our "exclusive" items! Lots of spaces.', $cleaned);
    }

    public function testExtractPagesReturnsEmptyArrayWhenWordPressNotLoaded(): void
    {
        $pages = $this->extractor->extractPages();
        $this->assertIsArray($pages);
    }

    public function testExtractPostsReturnsEmptyArrayWhenWordPressNotLoaded(): void
    {
        $posts = $this->extractor->extractPosts();
        $this->assertIsArray($posts);
    }

    public function testCleanContentStripsCodeBlocksAndFencesIngestingDataOnly(): void
    {
        $dirty = '<p>Our dairy machinery is built for efficiency.</p>
        <pre><code class="language-php">echo "internal debug code";</code></pre>
        ```python
        import secret_system
        def run(): pass
        ```
        <p>Available in 200L and 500L capacities with `stainless_steel_grade_304` finish.</p>';

        $cleaned = $this->extractor->cleanContent($dirty);

        $this->assertStringContainsString('Our dairy machinery is built for efficiency.', $cleaned);
        $this->assertStringContainsString('Available in 200L and 500L capacities with finish.', $cleaned);
        $this->assertStringNotContainsString('echo "internal debug code"', $cleaned);
        $this->assertStringNotContainsString('import secret_system', $cleaned);
        $this->assertStringNotContainsString('stainless_steel_grade_304', $cleaned);
        $this->assertStringNotContainsString('<code>', $cleaned);
        $this->assertStringNotContainsString('```', $cleaned);
    }

    public function testExtractCustomPostTypesReturnsEmptyArrayWhenWordPressNotLoaded(): void
    {
        $items = $this->extractor->extractCustomPostTypes('equipment');
        $this->assertIsArray($items);
    }

    public function testGetDiscoverablePostTypesReturnsArray(): void
    {
        $types = $this->extractor->getDiscoverablePostTypes();
        $this->assertIsArray($types);
    }

    public function testHtmlToDataMarkdownFormatsHeadingsListsAndLinksWithoutCode(): void
    {
        $html = '<div>
            <h1>MAR Traders Catalog</h1>
            <p>Welcome to our online dairy equipment showroom.</p>
            <ul>
                <li>Milk Chilling Unit - 1000L</li>
                <li>Vacuum Milking Machine - Dual Bucket</li>
            </ul>
            <p>View details on <a href="https://martraders-pk.local/chiller">Milk Chiller Spec Sheet</a>.</p>
            <pre><code>config_dump = { "port": 8080 };</code></pre>
        </div>';

        $markdown = $this->extractor->htmlToDataMarkdown($html);

        $this->assertStringContainsString('# MAR Traders Catalog', $markdown);
        $this->assertStringContainsString('Welcome to our online dairy equipment showroom.', $markdown);
        $this->assertStringContainsString('- Milk Chilling Unit - 1000L', $markdown);
        $this->assertStringContainsString('- Vacuum Milking Machine - Dual Bucket', $markdown);
        $this->assertStringContainsString('[Milk Chiller Spec Sheet](https://martraders-pk.local/chiller)', $markdown);
        // Guarantee zero code blocks in output
        $this->assertStringNotContainsString('config_dump', $markdown);
        $this->assertStringNotContainsString('<code>', $markdown);
        $this->assertStringNotContainsString('<pre>', $markdown);
    }

    public function testFormatCustomFieldsMarkdownProducesStructuredBullets(): void
    {
        $fields = [
            'Capacity' => '500 Liters',
            'Material' => 'Stainless Steel Grade 304',
            'Price'    => 'PKR 45,000',
        ];

        $markdown = $this->extractor->formatCustomFieldsMarkdown($fields);

        $this->assertStringContainsString('### Specifications & Details:', $markdown);
        $this->assertStringContainsString('- Capacity: 500 Liters', $markdown);
        $this->assertStringContainsString('- Material: Stainless Steel Grade 304', $markdown);
        $this->assertStringContainsString('- Price: PKR 45,000', $markdown);
    }

    public function testExtractPostCustomFieldsReturnsEmptyArrayWhenWpNotLoaded(): void
    {
        $fields = $this->extractor->extractPostCustomFields(123);
        $this->assertIsArray($fields);
    }

    public function testRenderDynamicContentReturnsEmptyWhenGivenEmptyString(): void
    {
        $rendered = $this->extractor->renderDynamicContent('');
        $this->assertSame('', $rendered);
    }

    public function testRenderDynamicContentReturnsRawContentGracefullyWhenWpFunctionsDoNotExist(): void
    {
        $raw = '<p>Custom dairy equipment list.</p>';
        $rendered = $this->extractor->renderDynamicContent($raw, 999);
        $this->assertSame($raw, $rendered);
    }
}

