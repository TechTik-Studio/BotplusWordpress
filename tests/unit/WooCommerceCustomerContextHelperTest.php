<?php

namespace BotPlus\Plugin\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Utilities\WooCommerceCustomerContextHelper;

class WooCommerceCustomerContextHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_enrich_conversation_without_email_returns_unchanged(): void
    {
        $input = [
            'conversation_id' => 'conv_1',
            'guest_name' => 'Unknown Guest',
        ];

        $output = WooCommerceCustomerContextHelper::enrichConversation($input);
        $this->assertEquals($input, $output);
    }

    public function test_enrich_conversation_with_customer_stats(): void
    {
        Functions\when('wc_get_orders')->justReturn([]);
        Functions\when('wc_get_customer_id_by_email')->justReturn(42);
        Functions\when('get_woocommerce_currency_symbol')->justReturn('$');
        Functions\when('wc_get_customer_total_spent')->justReturn(450.75);
        Functions\when('wc_get_customer_order_count')->justReturn(3);

        $input = [
            'conversation_id' => 'conv_2',
            'guest_name' => 'Sarah Connor',
            'guest_email' => 'sarah@example.com',
        ];

        $output = WooCommerceCustomerContextHelper::enrichConversation($input);

        $this->assertEquals('$450.75', $output['totalCustomerSpent']);
        $this->assertEquals(3, $output['customerOrdersCount']);
    }

    public function test_enrich_conversations_bulk(): void
    {
        Functions\when('wc_get_orders')->justReturn([]);
        Functions\when('wc_get_customer_id_by_email')->justReturn(10);
        Functions\when('get_woocommerce_currency_symbol')->justReturn('$');
        Functions\when('wc_get_customer_total_spent')->justReturn(100.00);
        Functions\when('wc_get_customer_order_count')->justReturn(1);

        $input = [
            ['conversation_id' => 'c1', 'guest_email' => 'c1@example.com'],
            ['conversation_id' => 'c2', 'guest_email' => 'invalid-email'],
        ];

        $output = WooCommerceCustomerContextHelper::enrichConversations($input);

        $this->assertCount(2, $output);
        $this->assertEquals('$100.00', $output[0]['totalCustomerSpent']);
        $this->assertArrayNotHasKey('totalCustomerSpent', $output[1]);
    }
}

