<?php

namespace {
    if (!class_exists('WC_Product')) {
        class WC_Product {
            public int $id = 101;
            public string $name = 'Premium Shirt';
            public string $sku = 'SHIRT-01';
            public float $price = 45.00;
            public array $attributes = [];
            public function get_id(): int { return $this->id; }
            public function get_name(): string { return $this->name; }
            public function get_sku(): string { return $this->sku; }
            public function get_type(): string { return 'simple'; }
            public function get_permalink(): string { return 'https://example.com/product/shirt'; }
            public function get_image_id(): int { return 0; }
            public function get_price(): float { return $this->price; }
            public function get_regular_price(): float { return $this->price; }
            public function get_sale_price(): string { return ''; }
            public function is_on_sale(): bool { return false; }
            public function get_stock_status(): string { return 'instock'; }
            public function get_stock_quantity(): ?int { return 10; }
            public function get_category_ids(): array { return []; }
            public function get_tag_ids(): array { return []; }
            public function get_attributes(): array { return $this->attributes; }
            public function get_short_description(): string { return 'Nice shirt'; }
            public function get_description(): string { return 'Full description'; }
        }
    }
    if (!class_exists('WC_Product_Attribute')) {
        class WC_Product_Attribute {
            public string $name = 'pa_color';
            public array $options = [];
            public bool $is_taxonomy = true;
            public function get_name(): string { return $this->name; }
            public function get_options(): array { return $this->options; }
            public function is_taxonomy(): bool { return $this->is_taxonomy; }
        }
    }
}

namespace BotPlus\Plugin\Tests\Unit {

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Sync\CatalogExtractor;
use BotPlus\Plugin\Sync\ContentExtractor;

class CatalogExtractorTest extends TestCase
{
    protected CatalogExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new CatalogExtractor();
    }

    public function testIsWooCommerceActiveReturnsBoolean(): void
    {
        $isActive = $this->extractor->isWooCommerceActive();
        $this->assertIsBool($isActive);
    }

    public function testExtractProductsReturnsEmptyWhenWooCommerceInactive(): void
    {
        if (!$this->extractor->isWooCommerceActive()) {
            $products = $this->extractor->extractProducts();
            $this->assertIsArray($products);
            $this->assertEmpty($products);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testExtractPoliciesHandlesGracefully(): void
    {
        $policies = $this->extractor->extractPolicies();
        $this->assertIsArray($policies);
    }

    public function testExtractProductsSavesBothHumanReadableNamesAndTermIdsForTaxonomies(): void
    {
        Monkey\setUp();
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('get_woocommerce_currency')->justReturn('USD');
        Functions\when('wc_attribute_label')->justReturn('Color');

        $attr = new \WC_Product_Attribute();
        $attr->name = 'pa_color';
        $attr->options = [12, 15];
        $attr->is_taxonomy = true;

        $product = new \WC_Product();
        $product->attributes = ['pa_color' => $attr];

        Functions\when('wc_get_products')->justReturn([$product]);

        Functions\when('get_term_by')->alias(function ($field, $value, $taxonomy) {
            if ($field === 'id' && $value === 12) {
                return (object) ['term_id' => 12, 'name' => 'Red', 'slug' => 'red'];
            }
            if ($field === 'id' && $value === 15) {
                return (object) ['term_id' => 15, 'name' => 'Blue', 'slug' => 'blue'];
            }
            return null;
        });

        Functions\when('is_wp_error')->justReturn(false);

        $products = $this->extractor->extractProducts(10);
        $this->assertCount(1, $products);
        $item = $products[0];

        // 1. Human readable term names saved in attributes (for AI RAG prompts)
        $this->assertEquals(['Red', 'Blue'], $item['attributes']['Color']);

        // 2. Term IDs saved in attribute_ids (for e-commerce/variation logic)
        $this->assertEquals([12, 15], $item['attribute_ids']['Color']);

        // 3. Full term details saved in attribute_terms
        $this->assertCount(2, $item['attribute_terms']['Color']);
        $this->assertEquals('Red', $item['attribute_terms']['Color'][0]['name']);
        $this->assertEquals(12, $item['attribute_terms']['Color'][0]['id']);

        Monkey\tearDown();
    }
}
}

