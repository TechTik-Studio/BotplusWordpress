<?php

namespace BotPlus\Plugin\Sync;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class CatalogExtractor
{
    protected ContentExtractor $contentExtractor;

    public function __construct(?ContentExtractor $contentExtractor = null)
    {
        $this->contentExtractor = $contentExtractor ?? new ContentExtractor();
    }

    /**
     * Check if WooCommerce is installed and active.
     */
    public function isWooCommerceActive(): bool
    {
        return function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (function_exists('WC') && class_exists('WooCommerce'));
    }

    /**
     * Extract published WooCommerce products with pricing, stock, attributes, and images.
     */
    public function extractProducts(int|array $limitOrArgs = 25, int $offset = 0): array
    {
        if (!$this->isWooCommerceActive() || !function_exists('wc_get_products')) {
            return [];
        }

        if (is_array($limitOrArgs)) {
            $queryArgs = array_merge([
                'status'  => 'publish',
                'orderby' => 'id',
                'order'   => 'ASC',
            ], $limitOrArgs);
        } else {
            $queryArgs = [
                'status'  => 'publish',
                'limit'   => $limitOrArgs,
                'offset'  => $offset,
                'orderby' => 'id',
                'order'   => 'ASC',
            ];
        }

        $products = wc_get_products($queryArgs);

        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
        $items = [];

        foreach ($products as $product) {
            if (!$product instanceof \WC_Product) {
                continue;
            }

            // Categories
            $categories = [];
            $catIds = $product->get_category_ids();
            if (function_exists('get_term_by') && !empty($catIds)) {
                foreach ($catIds as $catId) {
                    $term = get_term_by('id', $catId, 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        $categories[] = $term->name;
                    }
                }
            }

            // Tags
            $tags = [];
            $tagIds = $product->get_tag_ids();
            if (function_exists('get_term_by') && !empty($tagIds)) {
                foreach ($tagIds as $tagId) {
                    $term = get_term_by('id', $tagId, 'product_tag');
                    if ($term && !is_wp_error($term)) {
                        $tags[] = $term->name;
                    }
                }
            }

            // Attributes (saving BOTH human-readable term names and numeric term IDs for taxonomies)
            $attributes = [];
            $attributeIds = [];
            $attributeTerms = [];

            foreach ($product->get_attributes() as $attrName => $attr) {
                if ($attr instanceof \WC_Product_Attribute) {
                    $options = $attr->get_options();
                    $label = function_exists('wc_attribute_label') ? wc_attribute_label($attrName) : $attrName;
                    $isTaxonomy = method_exists($attr, 'is_taxonomy') ? $attr->is_taxonomy() : false;

                    $names = [];
                    $ids = [];
                    $terms = [];

                    if ($isTaxonomy && function_exists('get_term_by')) {
                        $rawOptions = is_array($options) ? $options : [$options];
                        foreach ($rawOptions as $opt) {
                            $term = null;
                            if (is_numeric($opt)) {
                                $ids[] = (int) $opt;
                                $term = get_term_by('id', (int) $opt, $attrName);
                            } elseif (is_string($opt)) {
                                $term = get_term_by('slug', $opt, $attrName) ?: get_term_by('name', $opt, $attrName);
                                if ($term && !is_wp_error($term)) {
                                    $ids[] = (int) $term->term_id;
                                }
                            }

                            if ($term && !is_wp_error($term)) {
                                $names[] = $term->name;
                                $terms[] = [
                                    'id'   => (int) $term->term_id,
                                    'name' => $term->name,
                                    'slug' => $term->slug,
                                ];
                            } else {
                                $names[] = (string) $opt;
                            }
                        }
                    } else {
                        // Custom product attribute (non-taxonomy)
                        $names = is_array($options) ? array_map('strval', array_values($options)) : [(string) $options];
                        $ids = [];
                        $terms = array_map(fn($n) => ['name' => $n], $names);
                    }

                    $attributes[$label] = $names;
                    if (!empty($ids)) {
                        $attributeIds[$label] = $ids;
                    }
                    if (!empty($terms)) {
                        $attributeTerms[$label] = $terms;
                    }
                }
            }

            // Featured Image
            $imageUrl = '';
            $imageId = $product->get_image_id();
            if ($imageId && function_exists('wp_get_attachment_url')) {
                $imageUrl = (string) wp_get_attachment_url($imageId);
            }

            // Descriptions cleaned of HTML tags
            $shortDesc = $this->contentExtractor->cleanContent($product->get_short_description());
            $fullDesc = $this->contentExtractor->cleanContent($product->get_description());

            $items[] = [
                'id'                => $product->get_id(),
                'sku'               => (string) $product->get_sku(),
                'title'             => $product->get_name(),
                'type'              => $product->get_type(),
                'permalink'         => (string) $product->get_permalink(),
                'image_url'         => $imageUrl,
                'price'             => (float) $product->get_price(),
                'regular_price'     => (float) $product->get_regular_price(),
                'sale_price'        => $product->get_sale_price() !== '' ? (float) $product->get_sale_price() : null,
                'on_sale'           => $product->is_on_sale(),
                'currency'          => $currency,
                'stock_status'      => $product->get_stock_status(),
                'stock_quantity'    => $product->get_stock_quantity(),
                'average_rating'    => method_exists($product, 'get_average_rating') ? (float) $product->get_average_rating() : 0.0,
                'rating_count'      => method_exists($product, 'get_rating_count') ? (int) $product->get_rating_count() : 0,
                'categories'        => $categories,
                'tags'              => $tags,
                'attributes'        => $attributes,
                'attribute_ids'     => $attributeIds,
                'attribute_terms'   => $attributeTerms,
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
                'short_description' => $shortDesc,
                'description'       => $fullDesc,
            ];
        }

        return $items;
    }

    /**
     * Extract dedicated WooCommerce store policy pages (Terms, Privacy, Refund, Shipping).
     */
    public function extractPolicies(): array
    {
        $policyPageIds = [];

        // 1. Terms and conditions
        if (function_exists('wc_terms_and_conditions_page_id')) {
            $termsId = wc_terms_and_conditions_page_id();
            if ($termsId > 0) $policyPageIds['terms'] = $termsId;
        }

        // 2. Privacy policy
        $privacyId = function_exists('get_option') ? (int) get_option('wp_page_for_privacy_policy', 0) : 0;
        if ($privacyId > 0) $policyPageIds['privacy'] = $privacyId;

        // 3. Search for Refund and Shipping pages
        if (function_exists('get_posts')) {
            $candidatePages = get_posts([
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
            ]);

            if (is_array($candidatePages)) {
                foreach ($candidatePages as $p) {
                    $slug = strtolower($p->post_name ?? '');
                    $title = strtolower($p->post_title ?? '');

                    if (str_contains($slug, 'refund') || str_contains($slug, 'return') || str_contains($title, 'refund')) {
                        $policyPageIds['refund'] = $p->ID;
                    } elseif (str_contains($slug, 'shipping') || str_contains($title, 'shipping')) {
                        $policyPageIds['shipping'] = $p->ID;
                    }
                }
            }
        }

        $policies = [];
        foreach ($policyPageIds as $policyType => $pageId) {
            $page = function_exists('get_post') ? get_post($pageId) : null;
            if ($page && isset($page->post_status) && $page->post_status === 'publish') {
                $cleanContent = $this->contentExtractor->cleanContent($page->post_content ?? '');
                if (!empty($cleanContent)) {
                    $policies[] = [
                        'id'            => $page->ID,
                        'policy_type'   => $policyType,
                        'title'         => $page->post_title,
                        'permalink'     => function_exists('get_permalink') ? get_permalink($page->ID) : '',
                        'clean_content' => $cleanContent,
                    ];
                }
            }
        }

        return $policies;
    }
}
