<?php

namespace BotPlus\Plugin\Webhooks;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class WebhookHandler
{
    /**
     * In-memory buffer for tracking affected product IDs during batch imports.
     */
    protected static array $bulkImportBuffer = [];

    /**
     * In-memory deduplication buffer for variation saves.
     */
    protected static array $processedVariations = [];

    /**
     * In-memory deduplication cache for dispatched stock updates within the current request.
     * Maps 'productId:variationId' => 'stockQuantity:stockStatus'.
     */
    protected static array $dispatchedStockUpdates = [];

    public function register(): void
    {
        // 1. Universal WordPress Core Hooks (CMS Posts, Pages, Custom Post Types)
        add_action('wp_after_insert_post', [$this, 'handlePostSaved'], 10, 3);
        add_action('transition_post_status', [$this, 'handlePostStatusTransition'], 10, 3);
        add_action('publish_future_post', [$this, 'handleFuturePostPublished'], 10, 1);
        add_action('trashed_post', [$this, 'handlePostTrashed'], 10, 1);
        add_action('untrashed_post', [$this, 'handlePostUntrashed'], 10, 1);
        add_action('before_delete_post', [$this, 'handlePostDeleted'], 10, 1);
        add_action('update_option_blogname', [$this, 'handleSiteTitleChanged'], 10, 2);

        // 2. WooCommerce Hooks (Registered only when WooCommerce is active)
        $isWoo = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (function_exists('WC') && class_exists('WooCommerce'));

        if ($isWoo) {
            add_action('woocommerce_new_product', [$this, 'handleProductSaved'], 10, 1);
            add_action('woocommerce_update_product', [$this, 'handleProductSaved'], 10, 1);
            add_action('woocommerce_delete_product', [$this, 'handleProductDeleted'], 10, 1);
            add_action('woocommerce_trash_product', [$this, 'handleProductDeleted'], 10, 1);

            // Stock status changes
            add_action('woocommerce_product_set_stock', [$this, 'handleStockChanged'], 10, 1);
            add_action('woocommerce_variation_set_stock', [$this, 'handleStockChanged'], 10, 1);
            add_action('woocommerce_product_set_stock_status', [$this, 'handleStockStatusChanged'], 10, 3);
            add_action('woocommerce_variation_set_stock_status', [$this, 'handleStockStatusChanged'], 10, 3);

            // Variation save hooks (AJAX on post.php)
            add_action('woocommerce_update_product_variation', [$this, 'handleVariationSaved'], 20, 1);
            add_action('woocommerce_save_product_variation', [$this, 'handleVariationSaved'], 20, 1);

            // Quick edit, bulk edit, and product meta save hooks
            add_action('woocommerce_product_quick_edit_save', [$this, 'handleProductMetaSaved'], 10, 1);
            add_action('woocommerce_product_bulk_edit_save', [$this, 'handleProductMetaSaved'], 10, 1);
            add_action('woocommerce_process_product_meta', [$this, 'handleProductMetaSaved'], 25, 1);
            add_action('woocommerce_product_duplicate', [$this, 'handleProductSaved'], 10, 1);

            // Coupons & promotions
            add_action('woocommerce_coupon_options_save', [$this, 'handleCouponSaved'], 10, 1);

            // Order lifecycle
            add_action('woocommerce_new_order', [$this, 'handleOrderCreated'], 10, 1);
            add_action('woocommerce_order_status_changed', [$this, 'handleOrderStatusChanged'], 10, 3);
            add_action('woocommerce_order_refunded', [$this, 'handleOrderRefunded'], 10, 2);
            add_action('woocommerce_payment_complete', [$this, 'handlePaymentComplete'], 10, 1);
            add_action('woocommerce_trash_order', [$this, 'handleOrderDeleted'], 10, 1);
            add_action('woocommerce_delete_order', [$this, 'handleOrderDeleted'], 10, 1);

            // Storefront visibility option
            add_action('update_option_woocommerce_coming_soon', [$this, 'handleStoreVisibilityOptionChanged'], 10, 2);

            // Bulk CSV & Feed Completion hooks
            add_action('woocommerce_product_import_inserted_product_object', [$this, 'bufferImportedProduct'], 10, 2);
            add_action('woocommerce_product_import_batch_complete', [$this, 'handleBulkImportComplete'], 10, 1);
            add_action('pmxi_after_xml_import', [$this, 'handleThirdPartyImportComplete'], 10, 1);
            add_action('wc_product_csv_import_suite_import_end', [$this, 'handleThirdPartyImportComplete']);
        }
    }

    /**
     * Handles post/page insert or update.
     */
    public function handlePostSaved(int $postId, $post, bool $update): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }

        // Delegate product post type to WooCommerce product handlers
        if ($post->post_type === 'product') {
            return;
        }

        // Only sync public post types (post, page, or public CPTs)
        $publicTypes = function_exists('get_post_types') ? get_post_types(['public' => true]) : ['post', 'page'];
        if (!in_array($post->post_type, $publicTypes, true)) {
            return;
        }

        if ($post->post_status === 'publish') {
            $payload = $this->extractPostPayload($post);
            if ($payload) {
                $this->dispatchWebhook('content.updated', $postId, $payload, $post->post_type);
            }
        } elseif ($update && in_array($post->post_status, ['draft', 'private', 'trash'], true)) {
            $this->dispatchWebhook('content.deleted', $postId, ['id' => $postId, 'status' => $post->post_status], $post->post_type);
        }
    }

    /**
     * Authoritative post status transition handler.
     * Guarantees vectors are updated when posts are published, scheduled, or unpublished.
     */
    public function handlePostStatusTransition(string $newStatus, string $oldStatus, $post): void
    {
        if (!$post || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post->ID)) {
            return;
        }

        if ($newStatus === $oldStatus) {
            return;
        }

        $postId = (int) $post->ID;
        $type = $post->post_type;

        // Transition to published
        if ($newStatus === 'publish') {
            if ($type === 'product') {
                $this->handleProductSaved($postId);
            } elseif ($type === 'shop_coupon') {
                $this->handleCouponSaved($postId);
            } else {
                $payload = $this->extractPostPayload($post);
                if ($payload) {
                    $this->dispatchWebhook('content.updated', $postId, $payload, $type);
                }
            }
            return;
        }

        // Transition from published to draft, pending, trash, or private (unpublish)
        if ($oldStatus === 'publish' && in_array($newStatus, ['draft', 'pending', 'trash', 'private'], true)) {
            $topic = match ($type) {
                'product'     => 'product.deleted',
                'shop_coupon' => 'coupon.deleted',
                default       => 'content.deleted',
            };
            $this->dispatchWebhook($topic, $postId, [
                'id'         => $postId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ], $type);
        }
    }

    /**
     * Handles scheduled post becoming live.
     */
    public function handleFuturePostPublished(int $postId): void
    {
        $post = function_exists('get_post') ? get_post($postId) : null;
        if ($post) {
            $this->handlePostSaved($postId, $post, true);
        }
    }

    /**
     * Handles post being sent to trash.
     */
    public function handlePostTrashed(int $postId): void
    {
        $post = function_exists('get_post') ? get_post($postId) : null;
        $type = $post ? $post->post_type : 'page';

        $topic = match ($type) {
            'product'     => 'product.deleted',
            'shop_coupon' => 'coupon.deleted',
            default       => 'content.deleted',
        };
        $this->dispatchWebhook($topic, $postId, ['id' => $postId, 'action' => 'trashed'], $type);
    }

    /**
     * Handles post being restored from trash.
     */
    public function handlePostUntrashed(int $postId): void
    {
        $post = function_exists('get_post') ? get_post($postId) : null;
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        if ($post->post_type === 'product') {
            $this->handleProductSaved($postId);
        } elseif ($post->post_type === 'shop_coupon') {
            $this->handleCouponSaved($postId);
        } else {
            $payload = $this->extractPostPayload($post);
            if ($payload) {
                $this->dispatchWebhook('content.restored', $postId, $payload, $post->post_type);
            }
        }
    }

    /**
     * Handles permanent post deletion.
     */
    public function handlePostDeleted(int $postId): void
    {
        $post = function_exists('get_post') ? get_post($postId) : null;
        $type = $post ? $post->post_type : 'page';

        $topic = match ($type) {
            'product'     => 'product.deleted',
            'shop_coupon' => 'coupon.deleted',
            default       => 'content.deleted',
        };
        $this->dispatchWebhook($topic, $postId, ['id' => $postId, 'action' => 'deleted'], $type);
    }

    /**
     * Handles WooCommerce product created or updated.
     */
    public function handleProductSaved(int $productId): void
    {
        $payload = $this->extractProductPayload($productId);
        if ($payload) {
            $this->dispatchWebhook('product.updated', $productId, $payload, 'product');
        }
    }

    /**
     * Handles WooCommerce product metadata save (post.php, quick edit, bulk edit).
     * Synchronizes product details and detects stock level changes on edit screens.
     */
    public function handleProductMetaSaved(int $productId): void
    {
        // 1. Dispatch full product updated webhook
        $this->handleProductSaved($productId);

        if (!$productId || !function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return;
        }

        // 2. Detect if inventory was modified or managed
        $stockStatus = method_exists($product, 'get_stock_status') ? $product->get_stock_status() : 'instock';
        $stockQty = method_exists($product, 'get_stock_quantity') ? $product->get_stock_quantity() : null;

        $dedupeKey = $productId . ':0';
        $dedupeVal = ($stockQty ?? 'null') . ':' . ($stockStatus ?: 'instock');
        if (isset(self::$dispatchedStockUpdates[$dedupeKey]) && self::$dispatchedStockUpdates[$dedupeKey] === $dedupeVal) {
            return;
        }
        self::$dispatchedStockUpdates[$dedupeKey] = $dedupeVal;

        $payload = [
            'id'             => $productId,
            'variation_id'   => null,
            'stock_status'   => $stockStatus ?: 'instock',
            'stock_quantity' => $stockQty,
            'manage_stock'   => method_exists($product, 'get_manage_stock') ? $product->get_manage_stock() : false,
        ];

        $productDetails = $this->extractProductPayload($productId);
        if (is_array($productDetails)) {
            $payload = array_merge($productDetails, $payload);
        }

        $this->dispatchWebhook('product.stock_updated', $productId, $payload, 'product');
    }

    /**
     * Handles WooCommerce product deleted.
     */
    public function handleProductDeleted(int $productId): void
    {
        $this->dispatchWebhook('product.deleted', $productId, ['id' => $productId, 'action' => 'deleted'], 'product');
    }

    /**
     * Handles WooCommerce stock quantity changes.
     */
    public function handleStockChanged($productOrId): void
    {
        $product = null;
        if (is_object($productOrId) && method_exists($productOrId, 'get_id')) {
            $product = $productOrId;
            $productId = $product->get_id();
        } else {
            $productId = (int) $productOrId;
            if ($productId && function_exists('wc_get_product')) {
                $product = wc_get_product($productId);
            }
        }

        if (!$productId) {
            return;
        }

        $targetId = $productId;
        $variationId = null;

        if ($product && method_exists($product, 'get_parent_id') && $product->get_parent_id() > 0) {
            $targetId = $product->get_parent_id();
            $variationId = $productId;
        }

        $stockStatus = 'instock';
        $stockQty = null;

        if ($product) {
            $stockStatus = $product->get_stock_status() ?: 'instock';
            $stockQty = $product->get_stock_quantity();
        }

        $dedupeKey = $targetId . ':' . ($variationId ?? '0');
        $dedupeVal = ($stockQty ?? 'null') . ':' . $stockStatus;
        if (isset(self::$dispatchedStockUpdates[$dedupeKey]) && self::$dispatchedStockUpdates[$dedupeKey] === $dedupeVal) {
            return;
        }
        self::$dispatchedStockUpdates[$dedupeKey] = $dedupeVal;

        $payload = [
            'id'             => $targetId,
            'variation_id'   => $variationId,
            'stock_status'   => $stockStatus,
            'stock_quantity' => $stockQty,
        ];

        $productDetails = $this->extractProductPayload($targetId);
        if (is_array($productDetails)) {
            $payload = array_merge($productDetails, $payload);
        }

        $this->dispatchWebhook('product.stock_updated', $targetId, $payload, 'product');
    }

    /**
     * Handles WooCommerce stock status dropdown changes (instock, outofstock, onbackorder).
     */
    public function handleStockStatusChanged(int $productId, string $stockStatus, $product = null): void
    {
        if (!$productId) {
            return;
        }

        if (!$product && function_exists('wc_get_product')) {
            $product = wc_get_product($productId);
        }

        $targetId = $productId;
        $variationId = null;

        if ($product && method_exists($product, 'get_parent_id') && $product->get_parent_id() > 0) {
            $targetId = $product->get_parent_id();
            $variationId = $productId;
        }

        $stockQty = $product ? $product->get_stock_quantity() : null;

        $dedupeKey = $targetId . ':' . ($variationId ?? '0');
        $dedupeVal = ($stockQty ?? 'null') . ':' . ($stockStatus ?: 'instock');
        if (isset(self::$dispatchedStockUpdates[$dedupeKey]) && self::$dispatchedStockUpdates[$dedupeKey] === $dedupeVal) {
            return;
        }
        self::$dispatchedStockUpdates[$dedupeKey] = $dedupeVal;

        $payload = [
            'id'             => $targetId,
            'variation_id'   => $variationId,
            'stock_status'   => $stockStatus ?: 'instock',
            'stock_quantity' => $stockQty,
        ];

        $productDetails = $this->extractProductPayload($targetId);
        if (is_array($productDetails)) {
            $payload = array_merge($productDetails, $payload);
        }

        $this->dispatchWebhook('product.stock_updated', $targetId, $payload, 'product');
    }

    /**
     * Handles WooCommerce variation saved (via AJAX on post.php or REST API).
     */
    public function handleVariationSaved(int $variationId): void
    {
        if (isset(self::$processedVariations[$variationId])) {
            return;
        }
        self::$processedVariations[$variationId] = true;

        $variation = function_exists('wc_get_product') ? wc_get_product($variationId) : null;
        if (!$variation) {
            return;
        }

        $parentId = method_exists($variation, 'get_parent_id') ? (int) $variation->get_parent_id() : 0;
        $targetId = $parentId > 0 ? $parentId : $variationId;

        $stockStatus = method_exists($variation, 'get_stock_status') ? $variation->get_stock_status() : 'instock';
        $stockQty = method_exists($variation, 'get_stock_quantity') ? $variation->get_stock_quantity() : null;

        $dedupeKey = $targetId . ':' . $variationId;
        $dedupeVal = ($stockQty ?? 'null') . ':' . ($stockStatus ?: 'instock');
        self::$dispatchedStockUpdates[$dedupeKey] = $dedupeVal;

        $payload = [
            'id'             => $targetId,
            'variation_id'   => $variationId,
            'stock_status'   => $stockStatus ?: 'instock',
            'stock_quantity' => $stockQty,
        ];

        $productDetails = $this->extractProductPayload($targetId);
        if (is_array($productDetails)) {
            $payload = array_merge($productDetails, $payload);
        }

        // 1. Dispatch fast stock updated webhook
        $this->dispatchWebhook('product.stock_updated', $targetId, $payload, 'product');

        // 2. Dispatch parent product updated to sync variation matrix with AI backend
        if (is_array($productDetails)) {
            $this->dispatchWebhook('product.updated', $targetId, $productDetails, 'product');
        }
    }

    /**
     * Handles WooCommerce order created.
     */
    public function handleOrderCreated(int $orderId): void
    {
        $payload = $this->extractOrderPayload($orderId);
        if ($payload) {
            $this->dispatchWebhook('order.created', $orderId, $payload, 'order');
        }
    }

    /**
     * Handles WooCommerce order status changes.
     */
    public function handleOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus): void
    {
        $payload = $this->extractOrderPayload($orderId);
        if ($payload) {
            $payload['old_status'] = $oldStatus;
            $payload['new_status'] = $newStatus;
            $this->dispatchWebhook('order.status_updated', $orderId, $payload, 'order');
        }
    }

    /**
     * Handles WooCommerce order refunded.
     */
    public function handleOrderRefunded(int $orderId, int $refundId): void
    {
        $payload = $this->extractOrderPayload($orderId);
        if (!$payload) {
            $payload = ['id' => $orderId, 'order_id' => $orderId];
        }

        $payload['refund_id'] = $refundId;
        if (function_exists('wc_get_order')) {
            $refund = wc_get_order($refundId);
            if ($refund && method_exists($refund, 'get_amount')) {
                $payload['refund_amount'] = (float) $refund->get_amount();
                $payload['reason'] = method_exists($refund, 'get_reason') ? $refund->get_reason() : '';
            }
        }

        $this->dispatchWebhook('order.refunded', $orderId, $payload, 'order');
    }

    /**
     * Handles WooCommerce payment complete.
     */
    public function handlePaymentComplete(int $orderId): void
    {
        $payload = $this->extractOrderPayload($orderId);
        if ($payload) {
            $this->dispatchWebhook('order.payment_completed', $orderId, $payload, 'order');
        }
    }

    /**
     * Handles WooCommerce order trashed or deleted.
     */
    public function handleOrderDeleted(int $orderId): void
    {
        $this->dispatchWebhook('order.deleted', $orderId, [
            'id'       => $orderId,
            'order_id' => $orderId,
            'status'   => 'cancelled',
        ], 'order');
    }

    /**
     * Buffers product ID during native WooCommerce CSV import.
     */
    public function bufferImportedProduct($product, array $data): void
    {
        if (is_object($product) && method_exists($product, 'get_id')) {
            self::$bulkImportBuffer[] = $product->get_id();
        }
    }

    /**
     * Dispatches bulk updated event when WooCommerce CSV import completes.
     */
    public function handleBulkImportComplete($import): void
    {
        $ids = array_values(array_unique(self::$bulkImportBuffer));
        self::$bulkImportBuffer = [];

        if (!empty($ids)) {
            $items = [];
            if (class_exists('\BotPlus\Plugin\Core\Bootstrapper')) {
                $container = \BotPlus\Plugin\Core\Bootstrapper::container();
                if ($container->has(\BotPlus\Plugin\Sync\CatalogExtractor::class)) {
                    $extractor = $container->get(\BotPlus\Plugin\Sync\CatalogExtractor::class);
                    $items = $extractor->extractProducts(['include' => $ids, 'limit' => count($ids)]);
                }
            }

            $this->dispatchWebhook('catalog.bulk_updated', 0, [
                'import_source' => 'woocommerce_csv',
                'product_ids'   => $ids,
                'items'         => $items,
                'count'         => count($ids),
            ], 'catalog');
        }
    }

    /**
     * Replays buffered failed webhooks and prunes successful deliveries.
     */
    public function flushRetryBuffer(): int
    {
        if (!function_exists('get_option')) {
            return 0;
        }

        $queue = get_option('botplus_webhook_retry_buffer', []);
        if (empty($queue) || !is_array($queue)) {
            return 0;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            return 0;
        }

        $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
        $remaining = [];
        $flushed = 0;

        foreach ($queue as $item) {
            $topic = $item['topic'] ?? '';
            $resourceId = (int) ($item['resource_id'] ?? 0);
            $payload = (array) ($item['payload'] ?? []);
            $resourceType = (string) ($item['resource_type'] ?? 'page');

            try {
                $res = $apiClient->forwardWebhook((int) $config->getSiteId(), $topic, $resourceId, $payload, $resourceType, true);
                if (!empty($res['success'])) {
                    $flushed++;
                } else {
                    $remaining[] = $item;
                }
            } catch (\Throwable) {
                $remaining[] = $item;
            }
        }

        if (function_exists('update_option')) {
            update_option('botplus_webhook_retry_buffer', array_slice($remaining, -50));
        }

        return $flushed;
    }

    /**
     * Dispatches bulk updated event when third-party feed import completes.
     */
    public function handleThirdPartyImportComplete($importId = null): void
    {
        $this->dispatchWebhook('catalog.bulk_updated', 0, [
            'import_source' => 'third_party_feed',
            'import_id'     => $importId,
        ], 'catalog');
    }

    /**
     * Dispatches a webhook non-blockingly to the Laravel backend.
     */
    public function dispatchWebhook(string $topic, int $resourceId, array $payload, string $resourceType = 'page'): bool
    {
        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);

        if (!$config->isConnected() || !$config->getSiteId()) {
            return false;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $res = $apiClient->forwardWebhook($config->getSiteId(), $topic, $resourceId, $payload, $resourceType, true);

            if (isset($res['success']) && !$res['success']) {
                $errorMsg = $res['message'] ?? 'Unknown dispatch error';
                if (function_exists('botplus_log')) {
                    botplus_log("BotPlus Webhook Dispatch Failed: {$errorMsg} for topic {$topic}", [], 'error');
                }

                // Fallback buffer for transient network retries
                $queue = function_exists('get_option') ? get_option('botplus_webhook_retry_buffer', []) : [];
                $queue[] = [
                    'topic'         => $topic,
                    'resource_id'   => $resourceId,
                    'resource_type' => $resourceType,
                    'payload'       => $payload,
                    'timestamp'     => time(),
                    'error'         => $errorMsg,
                ];
                if (function_exists('update_option')) {
                    update_option('botplus_webhook_retry_buffer', array_slice($queue, -50));
                }

                return false;
            }

            // Record telemetry
            $telemetry = function_exists('get_option') ? get_option('botplus_webhook_telemetry', []) : [];
            $telemetry['last_dispatched_at'] = time();
            $telemetry['last_topic'] = $topic;
            $telemetry['last_resource_id'] = $resourceId;
            $telemetry['total_dispatched'] = ($telemetry['total_dispatched'] ?? 0) + 1;
            if (function_exists('update_option')) {
                update_option('botplus_webhook_telemetry', $telemetry);
            }

            return true;
        } catch (\Throwable $e) {
            if (function_exists('botplus_log')) {
                botplus_log('BotPlus Webhook Dispatch Error: ' . $e->getMessage(), [], 'error');
            }

            // Fallback buffer for transient network retries
            $queue = function_exists('get_option') ? get_option('botplus_webhook_retry_buffer', []) : [];
            $queue[] = [
                'topic'         => $topic,
                'resource_id'   => $resourceId,
                'resource_type' => $resourceType,
                'payload'       => $payload,
                'timestamp'     => time(),
                'error'         => $e->getMessage(),
            ];
            if (function_exists('update_option')) {
                update_option('botplus_webhook_retry_buffer', array_slice($queue, -50));
            }

            return false;
        }
    }

    /**
     * Extracts structured payload for a post, page, or CPT.
     */
    public function extractPostPayload($post): ?array
    {
        $permalink = function_exists('get_permalink') ? get_permalink($post->ID) : '';
        $categories = [];

        if (function_exists('get_the_category')) {
            $catObjects = get_the_category($post->ID);
            if (is_array($catObjects)) {
                $categories = array_map(fn($cat) => $cat->name, $catObjects);
            }
        }

        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'slug'        => $post->post_name,
            'content'     => $post->post_content,
            'excerpt'     => $post->post_excerpt,
            'post_type'   => $post->post_type,
            'post_status' => $post->post_status,
            'permalink'   => $permalink,
            'categories'  => $categories,
            'updated_at'  => $post->post_modified_gmt,
        ];
    }

    /**
     * Extracts structured payload for a WooCommerce product.
     */
    public function extractProductPayload(int $productId): ?array
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return null;
        }

        $categories = [];
        if (function_exists('wc_get_product_category_list')) {
            $termIds = $product->get_category_ids();
            foreach ($termIds as $termId) {
                $term = function_exists('get_term') ? get_term($termId, 'product_cat') : null;
                if ($term && !is_wp_error($term)) {
                    $categories[] = $term->name;
                }
            }
        }

        $imageUrl = '';
        $imageId = $product->get_image_id();
        if ($imageId && function_exists('wp_get_attachment_image_url')) {
            $imageUrl = (string) wp_get_attachment_image_url($imageId, 'full');
        }

        $attributes = [];
        $wcAttributes = $product->get_attributes();
        if (is_array($wcAttributes)) {
            foreach ($wcAttributes as $attrName => $attrObj) {
                if (is_object($attrObj) && method_exists($attrObj, 'get_options')) {
                    $attributes[$attrName] = $attrObj->get_options();
                }
            }
        }

        return [
            'id'                => $product->get_id(),
            'title'             => $product->get_name(),
            'slug'              => $product->get_slug(),
            'sku'               => $product->get_sku(),
            'price'             => (float) $product->get_price(),
            'regular_price'     => (float) $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price() ? (float) $product->get_sale_price() : null,
            'currency'          => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'stock_status'      => $product->get_stock_status() ?: 'instock',
            'stock_quantity'    => $product->get_stock_quantity(),
            'average_rating'    => method_exists($product, 'get_average_rating') ? (float) $product->get_average_rating() : 0.0,
            'rating_count'      => method_exists($product, 'get_rating_count') ? (int) $product->get_rating_count() : 0,
            'permalink'         => (string) $product->get_permalink(),
            'image_url'         => $imageUrl,
            'short_description' => $product->get_short_description(),
            'description'       => $product->get_description(),
            'categories'        => $categories,
            'attributes'        => $attributes,
            'post_status'       => $product->get_status(),
            'type'              => $product->get_type(),
            'variations'        => ($product instanceof \WC_Product_Variable && method_exists($product, 'get_available_variations'))
                ? array_map(function ($var) {
                    return [
                        'id'             => $var['variation_id'] ?? null,
                        'sku'            => $var['sku'] ?? '',
                        'price'          => $var['display_price'] ?? 0,
                        'regular_price'  => $var['display_regular_price'] ?? 0,
                        'attributes'     => $var['attributes'] ?? [],
                        'stock_status'   => !empty($var['is_in_stock']) ? 'instock' : 'outofstock',
                        'stock_quantity' => $var['max_qty'] ?? null,
                    ];
                }, $product->get_available_variations())
                : [],
        ];
    }

    /**
     * Extracts structured payload for a WooCommerce order.
     */
    public function extractOrderPayload(int $orderId): ?array
    {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return null;
        }

        $lineItems = [];
        foreach ($order->get_items() as $item) {
            $lineItems[] = [
                'product_id' => $item->get_product_id(),
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'subtotal'   => (float) $item->get_subtotal(),
                'total'      => (float) $item->get_total(),
            ];
        }

        $tracking = null;
        $getMeta = function (string $key) use ($order, $orderId) {
            if (method_exists($order, 'get_meta')) {
                $val = $order->get_meta($key, true);
                if ($val !== '' && $val !== null && $val !== false) {
                    return $val;
                }
            }
            if (function_exists('get_post_meta')) {
                return get_post_meta($orderId, $key, true);
            }
            return null;
        };

        $trackingItems = $getMeta('_wc_shipment_tracking_items');
        if (!empty($trackingItems) && is_array($trackingItems)) {
            $latest = end($trackingItems);
            if (!empty($latest) && is_array($latest)) {
                $tracking = [
                    'company' => $latest['custom_tracking_provider'] ?? ($latest['tracking_provider'] ?? 'Carrier'),
                    'number'  => $latest['tracking_number'] ?? '',
                    'url'     => $latest['custom_tracking_link'] ?? ($latest['formatted_tracking_link'] ?? null),
                ];
            }
        }
        if (!$tracking) {
            $genericNum = $getMeta('_tracking_number') ?: $getMeta('_shipping_tracking_number');
            if (!empty($genericNum)) {
                $tracking = [
                    'company' => $getMeta('_tracking_provider') ?: ($getMeta('_shipping_provider') ?: 'Carrier'),
                    'number'  => $genericNum,
                    'url'     => $getMeta('_tracking_url') ?: null,
                ];
            }
        }

        return [
            'order_id'       => $order->get_id(),
            'order_number'   => $order->get_order_number(),
            'status'         => $order->get_status(),
            'currency'       => $order->get_currency(),
            'total'          => (float) $order->get_total(),
            'customer_id'    => $order->get_customer_id(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'date_created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
            'line_items'     => $lineItems,
            'tracking'       => $tracking,
            'cart_hash'      => $order->get_cart_hash(),
        ];
    }

    /**
     * Handles WooCommerce coupon created or updated.
     */
    public function handleCouponSaved(int $couponId): void
    {
        $payload = $this->extractCouponPayload($couponId);
        if ($payload) {
            $this->dispatchWebhook('coupon.updated', $couponId, $payload, 'coupon');
        }
    }

    /**
     * Extracts structured payload for a WooCommerce coupon.
     */
    public function extractCouponPayload(int $couponId): ?array
    {
        if (class_exists('WC_Coupon')) {
            try {
                $coupon = new \WC_Coupon($couponId);
                if ($coupon && $coupon->get_id() > 0) {
                    return [
                        'id'             => $coupon->get_id(),
                        'code'           => $coupon->get_code(),
                        'title'          => $coupon->get_code(),
                        'discount_type'  => $coupon->get_discount_type(),
                        'amount'         => (float) $coupon->get_amount(),
                        'date_expires'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date('Y-m-d') : null,
                        'free_shipping'  => (bool) $coupon->get_free_shipping(),
                        'minimum_amount' => (float) $coupon->get_minimum_amount(),
                        'maximum_amount' => (float) $coupon->get_maximum_amount(),
                        'description'    => (string) $coupon->get_description(),
                        'individual_use' => (bool) $coupon->get_individual_use(),
                    ];
                }
            } catch (\Throwable $e) {
                // Fallback to post extraction below
            }
        }

        $post = function_exists('get_post') ? get_post($couponId) : null;
        if ($post) {
            return [
                'id'    => $post->ID,
                'code'  => $post->post_title,
                'title' => $post->post_title,
            ];
        }

        return null;
    }

    /**
     * Handles changes to WooCommerce store visibility / coming soon mode.
     */
    public function handleStoreVisibilityOptionChanged($oldValue, $newValue): void
    {
        $this->dispatchWebhook('site.settings_updated', 0, [
            'is_coming_soon' => ($newValue === 'yes'),
        ], 'site');
    }

    /**
     * Handles changes to site title.
     */
    public function handleSiteTitleChanged($oldValue, $newValue): void
    {
        $this->dispatchWebhook('site.settings_updated', 0, [
            'site_title' => (string) $newValue,
        ], 'site');
    }
}
