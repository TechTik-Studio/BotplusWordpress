<?php

namespace BotPlus\Plugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Utilities\CapabilityDetector;

class CapabilityDetectorTest extends TestCase
{
    public function testDetectReturnsAccurateStructure(): void
    {
        $capabilities = CapabilityDetector::detect();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('site_type', $capabilities);
        $this->assertArrayHasKey('is_woocommerce', $capabilities);
        $this->assertArrayHasKey('counts', $capabilities);
        $this->assertArrayHasKey('has_cart_tracking', $capabilities);
        $this->assertArrayHasKey('has_track_order', $capabilities);
        $this->assertArrayHasKey('has_lead_intelligence', $capabilities);
        $this->assertArrayHasKey('detected_plugins', $capabilities);

        $this->assertIsArray($capabilities['counts']);
        $this->assertArrayHasKey('products', $capabilities['counts']);
        $this->assertArrayHasKey('pages', $capabilities['counts']);
        $this->assertArrayHasKey('posts', $capabilities['counts']);
    }

    public function testNonWooCommerceDefaults(): void
    {
        // Without WC active in PHPUnit environment:
        $capabilities = CapabilityDetector::detect();

        if (!class_exists('WooCommerce')) {
            $this->assertFalse($capabilities['is_woocommerce']);
            $this->assertEquals('wordpress_content', $capabilities['site_type']);
            $this->assertFalse($capabilities['has_cart_tracking']);
            $this->assertFalse($capabilities['has_track_order']);
            $this->assertTrue($capabilities['has_lead_intelligence']);
        }
    }
}
