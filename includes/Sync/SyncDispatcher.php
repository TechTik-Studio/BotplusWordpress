<?php

namespace BotPlus\Plugin\Sync;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

use BotPlus\Plugin\Api\LaravelApiClient;
use BotPlus\Plugin\Core\Config;

class SyncDispatcher
{
    protected Config $config;
    protected ?LaravelApiClient $apiClient;
    protected CatalogExtractor $catalogExtractor;
    protected ContentExtractor $contentExtractor;

    public function __construct(
        ?Config $config = null,
        ?LaravelApiClient $apiClient = null,
        ?CatalogExtractor $catalogExtractor = null,
        ?ContentExtractor $contentExtractor = null
    ) {
        $this->config = $config ?? new Config();
        $this->apiClient = $apiClient;
        $this->contentExtractor = $contentExtractor ?? new ContentExtractor();
        $this->catalogExtractor = $catalogExtractor ?? new CatalogExtractor($this->contentExtractor);
    }

    protected function getApiClient(): LaravelApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new LaravelApiClient($this->config);
        }
        return $this->apiClient;
    }

    /**
     * Run full sync for the given site ID.
     *
     * @param int|null $siteId If null, retrieves from config
     * @return array Sync statistics
     */
    public function dispatchFullSync(?int $siteId = null): array
    {
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required to prevent execution timeout during full catalog extraction.
            @set_time_limit(300);
        }

        $targetSiteId = $siteId ?? (int) $this->config->getSiteId();
        if ($targetSiteId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid or missing Site ID.',
            ];
        }

        $results = [
            'success'          => true,
            'status'           => 'queued',
            'queue'            => 'knowledge-training',
            'site_id'          => $targetSiteId,
            'products_synced'  => 0,
            'pages_synced'     => 0,
            'posts_synced'     => 0,
            'policies_synced'  => 0,
            'cpt_synced'       => [],
            'batches_sent'     => 0,
            'errors'           => [],
        ];

        // 1. Sync Policies (if WooCommerce is active)
        if ($this->catalogExtractor->isWooCommerceActive()) {
            try {
                $policies = $this->catalogExtractor->extractPolicies();
                if (!empty($policies)) {
                    $response = $this->getApiClient()->sendSyncBatch($targetSiteId, 'policies', $policies, [
                        'batch_index' => 1,
                        'is_last_batch' => true,
                    ]);
                    $results['batches_sent']++;
                    if (!empty($response['queue'])) {
                        $results['queue'] = $response['queue'];
                    }
                    if (!empty($response['success']) || ($response['status'] ?? '') === 'queued') {
                        $results['policies_synced'] += count($policies);
                    } else {
                        $results['errors'][] = 'Policy sync error: ' . ($response['message'] ?? 'Unknown error');
                        if (!$this->checkEnvironmentMismatch($response, $results)) {
                            return $results;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $results['errors'][] = 'Policy sync exception: ' . $e->getMessage();
            }

            // 2. Sync Products in batches of 25
            $limit = 25;
            $offset = 0;
            $hasMore = true;
            $batchIndex = 1;

            while ($hasMore) {
                try {
                    $products = $this->catalogExtractor->extractProducts($limit, $offset);
                    if (empty($products)) {
                        $hasMore = false;
                        break;
                    }

                    $isLast = count($products) < $limit;
                    $response = $this->getApiClient()->sendSyncBatch($targetSiteId, 'products', $products, [
                        'batch_index'   => $batchIndex,
                        'offset'        => $offset,
                        'is_last_batch' => $isLast,
                    ]);

                    $results['batches_sent']++;
                    if (!empty($response['queue'])) {
                        $results['queue'] = $response['queue'];
                    }
                    if (!empty($response['success']) || ($response['status'] ?? '') === 'queued') {
                        $results['products_synced'] += count($products);
                    } else {
                        $results['errors'][] = "Product batch {$batchIndex} error: " . ($response['message'] ?? 'Unknown error');
                        if (!$this->checkEnvironmentMismatch($response, $results)) {
                            return $results;
                        }
                    }

                    if ($isLast) {
                        $hasMore = false;
                    } else {
                        $offset += $limit;
                        $batchIndex++;
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = "Product batch {$batchIndex} exception: " . $e->getMessage();
                    $hasMore = false;
                }
            }
        }

        // 3. Sync Pages in batches of 20
        $limit = 20;
        $offset = 0;
        $hasMore = true;
        $batchIndex = 1;

        while ($hasMore) {
            try {
                $pages = $this->contentExtractor->extractPages($limit, $offset);
                if (empty($pages)) {
                    $hasMore = false;
                    break;
                }

                $isLast = count($pages) < $limit;
                $response = $this->getApiClient()->sendSyncBatch($targetSiteId, 'pages', $pages, [
                    'batch_index'   => $batchIndex,
                    'offset'        => $offset,
                    'is_last_batch' => $isLast,
                ]);

                $results['batches_sent']++;
                if (!empty($response['queue'])) {
                    $results['queue'] = $response['queue'];
                }
                if (!empty($response['success']) || ($response['status'] ?? '') === 'queued') {
                    $results['pages_synced'] += count($pages);
                } else {
                    $results['errors'][] = "Pages batch {$batchIndex} error: " . ($response['message'] ?? 'Unknown error');
                    if (!$this->checkEnvironmentMismatch($response, $results)) {
                        return $results;
                    }
                }

                if ($isLast) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                    $batchIndex++;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = "Pages batch {$batchIndex} exception: " . $e->getMessage();
                $hasMore = false;
            }
        }

        // 4. Sync Posts/Articles in batches of 20
        $limit = 20;
        $offset = 0;
        $hasMore = true;
        $batchIndex = 1;

        while ($hasMore) {
            try {
                $posts = $this->contentExtractor->extractPosts($limit, $offset);
                if (empty($posts)) {
                    $hasMore = false;
                    break;
                }

                $isLast = count($posts) < $limit;
                $response = $this->getApiClient()->sendSyncBatch($targetSiteId, 'posts', $posts, [
                    'batch_index'   => $batchIndex,
                    'offset'        => $offset,
                    'is_last_batch' => $isLast,
                ]);

                $results['batches_sent']++;
                if (!empty($response['queue'])) {
                    $results['queue'] = $response['queue'];
                }
                if (!empty($response['success']) || ($response['status'] ?? '') === 'queued') {
                    $results['posts_synced'] += count($posts);
                } else {
                    $results['errors'][] = "Posts batch {$batchIndex} error: " . ($response['message'] ?? 'Unknown error');
                    if (!$this->checkEnvironmentMismatch($response, $results)) {
                        return $results;
                    }
                }

                if ($isLast) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                    $batchIndex++;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = "Posts batch {$batchIndex} exception: " . $e->getMessage();
                $hasMore = false;
            }
        }

        // 5. Sync Autodiscovered Public Custom Post Types (e.g. catalog, equipment, portfolio, custom products)
        $discoverableTypes = $this->contentExtractor->getDiscoverablePostTypes();
        if (!empty($discoverableTypes) && is_array($discoverableTypes)) {
            foreach ($discoverableTypes as $postType => $typeMeta) {
                $limit = 20;
                $offset = 0;
                $hasMore = true;
                $batchIndex = 1;
                $cptCount = 0;

                while ($hasMore) {
                    try {
                        $cptItems = $this->contentExtractor->extractCustomPostTypes($postType, $limit, $offset);
                        if (empty($cptItems)) {
                            $hasMore = false;
                            break;
                        }

                        $isLast = count($cptItems) < $limit;
                        $response = $this->getApiClient()->sendSyncBatch($targetSiteId, $postType, $cptItems, [
                            'batch_index'   => $batchIndex,
                            'offset'        => $offset,
                            'is_last_batch' => $isLast,
                        ]);

                        $results['batches_sent']++;
                        if (!empty($response['queue'])) {
                            $results['queue'] = $response['queue'];
                        }
                        if (!empty($response['success']) || ($response['status'] ?? '') === 'queued') {
                            $cptCount += count($cptItems);
                        } else {
                            $results['errors'][] = "CPT {$postType} batch {$batchIndex} error: " . ($response['message'] ?? 'Unknown error');
                            if (!$this->checkEnvironmentMismatch($response, $results)) {
                                return $results;
                            }
                        }

                        if ($isLast) {
                            $hasMore = false;
                        } else {
                            $offset += $limit;
                            $batchIndex++;
                        }
                    } catch (\Throwable $e) {
                        $results['errors'][] = "CPT {$postType} batch {$batchIndex} exception: " . $e->getMessage();
                        $hasMore = false;
                    }
                }

                if ($cptCount > 0) {
                    $results['cpt_synced'][$postType] = $cptCount;
                }
            }
        }

        if (!empty($results['errors']) && $results['batches_sent'] === 0) {
            $results['success'] = false;
        }

        return $results;
    }

    /**
     * Dispatches a single incremental sync chunk for a specific data type.
     * Prevents long execution timeouts on large catalogs by processing one batch at a time.
     *
     * @param int|null $siteId
     * @param string $type 'policies', 'products', 'pages', 'posts', or a custom post type slug
     * @param int $offset
     * @param int $limit
     * @param int $batchIndex
     * @return array
     */
    public function dispatchSyncChunk(
        ?int $siteId = null,
        string $type = 'products',
        int $offset = 0,
        int $limit = 25,
        int $batchIndex = 1
    ): array {
        $targetSiteId = $siteId ?? (int) $this->config->getSiteId();
        if ($targetSiteId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid or missing Site ID.',
            ];
        }

        $items = [];
        $isLast = true;
        $nextOffset = 0;
        $nextType = null;

        try {
            if ($type === 'policies') {
                if ($this->catalogExtractor->isWooCommerceActive()) {
                    $items = $this->catalogExtractor->extractPolicies();
                }
                $isLast = true;
                $nextOffset = 0;
                $nextType = $this->catalogExtractor->isWooCommerceActive() ? 'products' : 'pages';
            } elseif ($type === 'products') {
                if ($this->catalogExtractor->isWooCommerceActive()) {
                    $items = $this->catalogExtractor->extractProducts($limit, $offset);
                    $isLast = count($items) < $limit;
                    $nextOffset = $isLast ? 0 : $offset + $limit;
                    $nextType = $isLast ? 'pages' : 'products';
                } else {
                    $items = [];
                    $isLast = true;
                    $nextOffset = 0;
                    $nextType = 'pages';
                }
            } elseif ($type === 'pages') {
                $items = $this->contentExtractor->extractPages($limit, $offset);
                $isLast = count($items) < $limit;
                $nextOffset = $isLast ? 0 : $offset + $limit;
                $nextType = $isLast ? 'posts' : 'pages';
            } elseif ($type === 'posts') {
                $items = $this->contentExtractor->extractPosts($limit, $offset);
                $isLast = count($items) < $limit;
                $nextOffset = $isLast ? 0 : $offset + $limit;

                if ($isLast) {
                    $cptList = array_keys($this->contentExtractor->getDiscoverablePostTypes() ?: []);
                    $nextType = !empty($cptList) ? 'cpt:' . $cptList[0] : 'done';
                } else {
                    $nextType = 'posts';
                }
            } elseif (str_starts_with($type, 'cpt:')) {
                $postType = substr($type, 4);
                $items = $this->contentExtractor->extractCustomPostTypes($postType, $limit, $offset);
                $isLast = count($items) < $limit;
                $nextOffset = $isLast ? 0 : $offset + $limit;

                if ($isLast) {
                    $cptList = array_keys($this->contentExtractor->getDiscoverablePostTypes() ?: []);
                    $currIdx = array_search($postType, $cptList, true);
                    if ($currIdx !== false && isset($cptList[$currIdx + 1])) {
                        $nextType = 'cpt:' . $cptList[$currIdx + 1];
                    } else {
                        $nextType = 'done';
                    }
                } else {
                    $nextType = $type;
                }
            } else {
                return [
                    'success' => false,
                    'message' => "Unsupported sync type: {$type}",
                ];
            }

            if (!empty($items)) {
                $cleanSyncType = str_starts_with($type, 'cpt:') ? substr($type, 4) : $type;
                $response = $this->getApiClient()->sendSyncBatch($targetSiteId, $cleanSyncType, $items, [
                    'batch_index'   => $batchIndex,
                    'offset'        => $offset,
                    'is_last_batch' => $isLast,
                ]);

                $res = [];
                if (!$this->checkEnvironmentMismatch($response, $res)) {
                    return $res;
                }

                if (empty($response['success']) && ($response['status'] ?? '') !== 'queued') {
                    return [
                        'success' => false,
                        'message' => $response['message'] ?? 'Failed to send sync batch to BotPlus.',
                    ];
                }
            }

            return [
                'success'      => true,
                'type'         => $type,
                'count'        => count($items),
                'is_last'      => $isLast,
                'offset'       => $offset,
                'next_offset'  => $nextOffset,
                'next_type'    => $nextType,
                'batch_index'  => $batchIndex,
                'finished'     => ($nextType === 'done'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Chunk sync error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Checks if response indicates an origin / environment shift mismatch.
     */
    protected function checkEnvironmentMismatch(array $response, array &$results): bool
    {
        $isHostMismatch = (!empty($response['error']) && $response['error'] === 'host_mismatch')
            || (!empty($response['message']) && str_contains($response['message'], 'origin does not match'));

        if ($isHostMismatch) {
            $results['success'] = false;
            $results['error_code'] = 'host_mismatch';
            $results['message'] = $response['message'] ?? 'The request origin does not match the connected site host. Please reconnect the BotPlus plugin.';
            if (function_exists('update_option')) {
                update_option('botplus_environment_mismatch', [
                    'detected_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
                    'message'     => $results['message'],
                ]);
            }
            return false;
        }

        return true;
    }
}

