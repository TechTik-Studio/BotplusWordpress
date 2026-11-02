<?php

namespace BotPlus\Plugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Sync\SyncDispatcher;
use BotPlus\Plugin\Sync\CatalogExtractor;
use BotPlus\Plugin\Sync\ContentExtractor;
use BotPlus\Plugin\Api\LaravelApiClient;
use BotPlus\Plugin\Core\Config;

class SyncDispatcherTest extends TestCase
{
    public function testDispatchReturnsErrorWhenSiteIdInvalid(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(0);

        $dispatcher = new SyncDispatcher($configMock);
        $result = $dispatcher->dispatchFullSync(0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function testDispatchCompletesWhenNoItemsFound(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(42);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(false);

        $contentMock = $this->createMock(ContentExtractor::class);
        $contentMock->method('extractPages')->willReturn([]);
        $contentMock->method('extractPosts')->willReturn([]);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->never())->method('sendSyncBatch');

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock, $contentMock);
        $result = $dispatcher->dispatchFullSync(42);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['products_synced']);
        $this->assertEquals(0, $result['pages_synced']);
        $this->assertEquals(0, $result['posts_synced']);
        $this->assertEquals(0, $result['batches_sent']);
    }

    public function testDispatchHandlesAsyncQueuedBatches(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(99);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(true);
        $catalogMock->method('extractPolicies')->willReturn([
            ['id' => 1, 'title' => 'Refund Policy', 'policy_type' => 'refund']
        ]);
        $catalogMock->method('extractProducts')->willReturnOnConsecutiveCalls(
            [['id' => 10, 'title' => 'Sneakers', 'price' => 50]],
            []
        );

        $contentMock = $this->createMock(ContentExtractor::class);
        $contentMock->method('extractPages')->willReturn([]);
        $contentMock->method('extractPosts')->willReturn([]);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->exactly(2))
            ->method('sendSyncBatch')
            ->willReturn([
                'success'      => true,
                'status'       => 'queued',
                'queue'        => 'knowledge-training',
                'queued_items' => 1,
            ]);

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock, $contentMock);
        $result = $dispatcher->dispatchFullSync(99);

        $this->assertTrue($result['success']);
        $this->assertEquals('queued', $result['status']);
        $this->assertEquals('knowledge-training', $result['queue']);
        $this->assertEquals(1, $result['policies_synced']);
        $this->assertEquals(1, $result['products_synced']);
        $this->assertEquals(2, $result['batches_sent']);
        $this->assertEmpty($result['errors']);
    }

    public function testDispatchExtractsAndSendsDiscoveredCpts(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(88);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(false);

        $contentMock = $this->createMock(ContentExtractor::class);
        $contentMock->method('extractPages')->willReturn([]);
        $contentMock->method('extractPosts')->willReturn([]);
        $contentMock->method('getDiscoverablePostTypes')->willReturn([
            'equipment' => ['name' => 'equipment', 'label' => 'Equipment', 'plural' => 'Equipment']
        ]);
        $contentMock->method('extractCustomPostTypes')->willReturnOnConsecutiveCalls(
            [['id' => 101, 'title' => 'Milk Pasteurizer 500L', 'post_type' => 'equipment']],
            []
        );

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('sendSyncBatch')
            ->with(88, 'equipment', $this->anything(), $this->anything())
            ->willReturn([
                'success'      => true,
                'status'       => 'queued',
                'queue'        => 'knowledge-training',
                'queued_items' => 1,
            ]);

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock, $contentMock);
        $result = $dispatcher->dispatchFullSync(88);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['batches_sent']);
        $this->assertEquals(['equipment' => 1], $result['cpt_synced']);
    }

    public function testDispatchCollectsErrorsWhenApiRejectsBatches(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(55);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(false);

        $contentMock = $this->createMock(ContentExtractor::class);
        $contentMock->method('extractPages')->willReturn([
            ['id' => 12, 'title' => 'Catalog', 'content' => 'Catalog content']
        ]);
        $contentMock->method('extractPosts')->willReturn([]);
        $contentMock->method('getDiscoverablePostTypes')->willReturn([]);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('sendSyncBatch')
            ->willReturn([
                'success' => false,
                'message' => 'Unauthorized site token',
            ]);

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock, $contentMock);
        $result = $dispatcher->dispatchFullSync(55);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Unauthorized site token', $result['errors'][0]);
        $this->assertEquals(0, $result['pages_synced']);
    }

    public function testDispatchSyncChunkIncrementalPaging(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(55);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(true);
        $catalogMock->method('extractProducts')->willReturn([
            ['id' => 1, 'title' => 'Product 1'],
            ['id' => 2, 'title' => 'Product 2'],
        ]);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('sendSyncBatch')
            ->willReturn(['success' => true]);

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock);
        $result = $dispatcher->dispatchSyncChunk(55, 'products', 0, 2, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('products', $result['type']);
        $this->assertEquals(2, $result['count']);
        $this->assertFalse($result['is_last']);
        $this->assertEquals(2, $result['next_offset']);
        $this->assertEquals('products', $result['next_type']);
        $this->assertFalse($result['finished']);
    }

    public function testDispatchSyncChunkTransitionsToNextType(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(55);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(true);
        $catalogMock->method('extractProducts')->willReturn([
            ['id' => 3, 'title' => 'Product 3'],
        ]);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('sendSyncBatch')
            ->willReturn(['success' => true]);

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock);
        // limit is 2, but only 1 item returned -> is_last is true, transitions to 'pages'
        $result = $dispatcher->dispatchSyncChunk(55, 'products', 2, 2, 2);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_last']);
        $this->assertEquals(0, $result['next_offset']);
        $this->assertEquals('pages', $result['next_type']);
    }
}

