<?php

namespace BotPlus\Plugin\Sync;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class ContentExtractor
{
    protected BuilderExtractor $builderExtractor;

    public function __construct(?BuilderExtractor $builderExtractor = null)
    {
        $this->builderExtractor = $builderExtractor ?? new BuilderExtractor();
    }

    /**
     * System page IDs that should not be indexed as knowledge.
     */
    protected function getExcludedPageIds(): array
    {
        $excluded = [];

        if (function_exists('wc_get_page_id')) {
            $cartId = wc_get_page_id('cart');
            $checkoutId = wc_get_page_id('checkout');
            $myAccountId = wc_get_page_id('myaccount');

            if ($cartId > 0) $excluded[] = $cartId;
            if ($checkoutId > 0) $excluded[] = $checkoutId;
            if ($myAccountId > 0) $excluded[] = $myAccountId;
        }

        return $excluded;
    }

    /**
     * Discover public Custom Post Types (excluding internal WordPress and builder types).
     */
    public function getDiscoverablePostTypes(): array
    {
        if (!function_exists('get_post_types')) {
            return [];
        }

        $builtinExcluded = [
            'attachment', 'revision', 'nav_menu_item', 'custom_css', 
            'customize_changeset', 'oembed_cache', 'user_request', 
            'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation',
            'elementor_library', 'elementor_font', 'elementor_icons',
            'page', 'post',
        ];

        $publicTypes = get_post_types(['public' => true], 'objects');
        $allowed = [];

        $isWooCommerce = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (function_exists('WC') && class_exists('WooCommerce'));

        foreach ($publicTypes as $type => $object) {
            if (in_array($type, $builtinExcluded, true)) {
                continue;
            }

            // If WooCommerce is active, 'product' is handled separately by CatalogExtractor
            if ($type === 'product' && $isWooCommerce) {
                continue;
            }

            $allowed[$type] = [
                'name'   => $type,
                'label'  => isset($object->labels->singular_name) ? $object->labels->singular_name : ucfirst($type),
                'plural' => isset($object->labels->name) ? $object->labels->name : ucfirst($type),
            ];
        }

        return $allowed;
    }

    /**
     * Extract published WordPress pages with zero-drop fallback for dynamic/builder pages.
     */
    public function extractPages(int $limit = 20, int $offset = 0): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $excludedIds = $this->getExcludedPageIds();
        $args = [
            'post_type'        => 'page',
            'post_status'      => 'publish',
            'numberposts'      => $limit,
            'posts_per_page'   => $limit,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ];

        if (!empty($excludedIds)) {
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Excludes only core WooCommerce system pages (Cart, Checkout, Account).
            $args['exclude'] = $excludedIds;
        }

        $posts = get_posts($args);
        $items = [];

        foreach ($posts as $post) {
            $cleanText = $this->cleanContent($post->post_content ?? '', $post->ID);
            
            // Builder Fallback: If page uses Elementor, extract its visual text data
            $builderText = $this->builderExtractor->extractElementorContent($post->ID);
            if (!empty($builderText)) {
                $cleanBuilderText = $this->htmlToDataMarkdown($builderText);
                if (!empty($cleanBuilderText)) {
                    $cleanText = !empty($cleanText) ? $cleanText . "\n\n" . $cleanBuilderText : $cleanBuilderText;
                }
            }

            // Zero-drop safety guard: If page body is empty (e.g. dynamic grid / shortcode / builder),
            // use title and excerpt so pages like /catalog/ are never silently dropped.
            if (empty(trim($cleanText))) {
                $cleanText = $post->post_title;
                if (!empty($post->post_excerpt)) {
                    $cleanText .= "\n\n" . $this->cleanContent($post->post_excerpt, $post->ID);
                }
            }

            if (empty(trim($cleanText))) {
                continue;
            }

            // Custom Fields & ACF Data Ingestion (Human Data Only)
            $customFields = $this->extractPostCustomFields($post->ID);
            $fieldsMarkdown = $this->formatCustomFieldsMarkdown($customFields);
            if (!empty($fieldsMarkdown)) {
                $cleanText .= "\n\n" . $fieldsMarkdown;
            }

            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'permalink'     => function_exists('get_permalink') ? get_permalink($post->ID) : '',
                'post_type'     => 'page',
                'modified_at'   => $post->post_modified ?? '',
                'excerpt'       => !empty($post->post_excerpt) 
                    ? $this->cleanContent($post->post_excerpt) 
                    : (function_exists('wp_trim_words') ? wp_trim_words($cleanText, 30) : (mb_substr($cleanText, 0, 160) . '...')),
                'clean_content' => $cleanText,
            ];
        }

        return $items;
    }

    /**
     * Extract published WordPress blog posts / articles.
     */
    public function extractPosts(int $limit = 20, int $offset = 0): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $args = [
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'numberposts'      => $limit,
            'posts_per_page'   => $limit,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ];

        $posts = get_posts($args);
        $items = [];

        foreach ($posts as $post) {
            $cleanText = $this->cleanContent($post->post_content ?? '');
            if (empty(trim($cleanText))) {
                $cleanText = $post->post_title;
            }

            $categories = [];
            if (function_exists('get_the_category')) {
                $cats = get_the_category($post->ID);
                if (is_array($cats)) {
                    foreach ($cats as $cat) {
                        $categories[] = $cat->name;
                    }
                }
            }

            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'permalink'     => function_exists('get_permalink') ? get_permalink($post->ID) : '',
                'post_type'     => 'post',
                'categories'    => $categories,
                'modified_at'   => $post->post_modified ?? '',
                'excerpt'       => !empty($post->post_excerpt) 
                    ? $this->cleanContent($post->post_excerpt) 
                    : (function_exists('wp_trim_words') ? wp_trim_words($cleanText, 30) : (mb_substr($cleanText, 0, 160) . '...')),
                'clean_content' => $cleanText,
            ];
        }

        return $items;
    }

    /**
     * Extract published Custom Post Type items (e.g., custom products, catalog, portfolio, equipment).
     */
    public function extractCustomPostTypes(string $postType, int $limit = 20, int $offset = 0): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $args = [
            'post_type'        => $postType,
            'post_status'      => 'publish',
            'numberposts'      => $limit,
            'posts_per_page'   => $limit,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ];

        $posts = get_posts($args);
        $items = [];

        foreach ($posts as $post) {
            $cleanText = $this->cleanContent($post->post_content ?? '', $post->ID);

            // Builder Fallback for CPTs
            $builderText = $this->builderExtractor->extractElementorContent($post->ID);
            if (!empty($builderText)) {
                $cleanBuilderText = $this->htmlToDataMarkdown($builderText);
                if (!empty($cleanBuilderText)) {
                    $cleanText = !empty($cleanText) ? $cleanText . "\n\n" . $cleanBuilderText : $cleanBuilderText;
                }
            }

            if (empty(trim($cleanText))) {
                $cleanText = $post->post_title;
            }

            // Custom Fields & ACF Data Ingestion for CPTs (Human Data Only)
            $customFields = $this->extractPostCustomFields($post->ID);
            $fieldsMarkdown = $this->formatCustomFieldsMarkdown($customFields);
            if (!empty($fieldsMarkdown)) {
                $cleanText .= "\n\n" . $fieldsMarkdown;
            }

            $terms = [];
            if (function_exists('get_object_taxonomies') && function_exists('wp_get_post_terms')) {
                $taxonomies = get_object_taxonomies($postType);
                if (is_array($taxonomies)) {
                    foreach ($taxonomies as $taxonomy) {
                        $foundTerms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'names']);
                        if (is_array($foundTerms) && !empty($foundTerms)) {
                            $terms = array_merge($terms, $foundTerms);
                        }
                    }
                    $terms = array_values(array_unique($terms));
                }
            }

            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'permalink'     => function_exists('get_permalink') ? get_permalink($post->ID) : '',
                'post_type'     => $postType,
                'categories'    => $terms,
                'modified_at'   => $post->post_modified ?? '',
                'excerpt'       => !empty($post->post_excerpt) 
                    ? $this->cleanContent($post->post_excerpt) 
                    : (function_exists('wp_trim_words') ? wp_trim_words($cleanText, 30) : (mb_substr($cleanText, 0, 160) . '...')),
                'clean_content' => $cleanText,
            ];
        }

        return $items;
    }

    /**
     * Ingest clean human data only — completely strip code blocks, raw scripts, builder shortcodes, and HTML markup.
     */
    /**
     * Pre-render dynamic blocks and shortcodes inside an isolated execution environment.
     */
    public function renderDynamicContent(string $rawContent, int $postId = 0): string
    {
        if (empty($rawContent)) {
            return '';
        }

        $switchedPost = false;
        if ($postId > 0 && function_exists('get_post')) {
            $postObj = get_post($postId);
            if ($postObj) {
                $GLOBALS['post'] = $postObj;
                if (function_exists('setup_postdata')) {
                    setup_postdata($postObj);
                }
                $switchedPost = true;
            }
        }

        try {
            // Expand Gutenberg dynamic blocks if function exists
            if (function_exists('do_blocks')) {
                $rawContent = do_blocks($rawContent);
            }

            // Expand standard shortcodes (e.g. [products], [catalog], [display-posts])
            if (function_exists('do_shortcode')) {
                $rawContent = do_shortcode($rawContent);
            }
        } catch (\Throwable $e) {
            // Safe fallback to raw content if block execution fails
        } finally {
            if ($switchedPost && function_exists('wp_reset_postdata')) {
                wp_reset_postdata();
            }
        }

        return $rawContent;
    }

    /**
     * Convert rendered HTML into pure human-readable text data with markdown formatting.
     * Strictly eliminates all code blocks (<pre>, <code>), script tags, and developer markup.
     */
    public function htmlToDataMarkdown(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Remove scripts, styles, pre, and code blocks completely (NO CODE BLOCKS)
        $html = preg_replace('/<(script|style|pre|code)\b[^>]*>(.*?)<\/\1>/is', '', $html);

        // 2. Remove markdown code fences and backtick code snippets
        $html = preg_replace('/```[\s\S]*?```/s', '', $html);
        $html = preg_replace('/`[^`\n]+`/', '', $html);

        // 3. Remove HTML comments
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        // 4. Strip any residual builder shortcode wrappers
        $html = preg_replace('/\[\/?(elementor-template|vc_[^\]]*|et_pb_[^\]]*|fusion_[^\]]*)[^\]]*\]/i', '', $html);

        // 5. Convert semantic elements to clean text formatting
        // Headings
        $html = preg_replace('/<h1\b[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $html);
        $html = preg_replace('/<h2\b[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $html);
        $html = preg_replace('/<h[3-6]\b[^>]*>(.*?)<\/h[3-6]>/is', "\n### $1\n", $html);

        // List items
        $html = preg_replace('/<li\b[^>]*>(.*?)<\/li>/is', "\n- $1", $html);

        // Paragraphs & Line Breaks
        $html = preg_replace('/<(p|div|tr)\b[^>]*>/is', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/is', "\n", $html);

        // Links: [Text](URL)
        $html = preg_replace_callback('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', function ($matches) {
            $url = trim($matches[1]);
            $text = trim(\wp_strip_all_tags($matches[2]));
            if (empty($text) || empty($url) || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')) {
                return $text;
            }
            return "[{$text}]({$url})";
        }, $html);

        // Strip remaining HTML tags
        $html = \wp_strip_all_tags($html, false);

        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean and normalize lines
        $lines = explode("\n", $html);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $trimmed = trim(preg_replace('/[ \t]+/', ' ', $line));
            if (!empty($trimmed)) {
                $cleanedLines[] = $trimmed;
            }
        }

        return implode("\n\n", $cleanedLines);
    }

    /**
     * Ingest clean human data only — pre-renders dynamic content, then strips code blocks and tags.
     */
    public function cleanContent(string $rawContent, int $postId = 0): string
    {
        if (empty($rawContent)) {
            return '';
        }

        // 1. Pre-render dynamic blocks and shortcodes
        $rendered = $this->renderDynamicContent($rawContent, $postId);

        // 2. Convert rendered HTML to pure human data markdown (no code blocks)
        return $this->htmlToDataMarkdown($rendered);
    }

    /**
     * Extract business and product custom fields (postmeta & ACF) as human-readable key-value pairs.
     * Strictly avoids internal WordPress keys, serialized blobs, and code dumps.
     */
    public function extractPostCustomFields(int $postId): array
    {
        if (!function_exists('get_post_meta') || $postId <= 0) {
            return [];
        }

        $fields = [];

        // 1. If ACF is active, extract structured ACF fields first
        if (function_exists('get_field_objects')) {
            $acfFields = get_field_objects($postId);
            if (is_array($acfFields)) {
                foreach ($acfFields as $field) {
                    if (empty($field['name']) || !isset($field['value'])) {
                        continue;
                    }
                    $label = !empty($field['label']) ? $field['label'] : ucfirst(str_replace(['_', '-'], ' ', $field['name']));
                    $val = $field['value'];

                    if (is_string($val) || is_numeric($val) || is_bool($val)) {
                        $strVal = trim((string) $val);
                        if (!empty($strVal)) {
                            $fields[$label] = $strVal;
                        }
                    } elseif (is_array($val)) {
                        // Repeater or multi-select: flatten scalar values only
                        $flat = [];
                        foreach ($val as $subVal) {
                            if (is_scalar($subVal)) {
                                $flat[] = (string) $subVal;
                            } elseif (is_array($subVal)) {
                                foreach ($subVal as $subK => $subV) {
                                    if (is_scalar($subV) && !empty($subV)) {
                                        $subLabel = ucfirst(str_replace(['_', '-'], ' ', $subK));
                                        $flat[] = "{$subLabel}: {$subV}";
                                    }
                                }
                            }
                        }
                        if (!empty($flat)) {
                            $fields[$label] = implode(' | ', $flat);
                        }
                    }
                }
            }
        }

        // 2. Extract standard postmeta
        $allMeta = get_post_meta($postId);
        if (is_array($allMeta)) {
            // Whitelisted standard e-commerce/catalog keys starting with underscore
            $allowedUnderscoreKeys = ['_sku', '_price', '_regular_price', '_sale_price', '_stock_status'];

            foreach ($allMeta as $key => $values) {
                // Skip internal WordPress keys unless explicitly whitelisted
                if (str_starts_with($key, '_') && !in_array($key, $allowedUnderscoreKeys, true)) {
                    continue;
                }

                // If already captured by ACF with a cleaner label, skip
                $cleanLabel = ucfirst(trim(str_replace(['_', '-'], ' ', ltrim($key, '_'))));
                if (isset($fields[$cleanLabel])) {
                    continue;
                }

                $firstVal = is_array($values) ? ($values[0] ?? '') : $values;
                // Exclude serialized objects, binary data, or JSON
                if (!is_scalar($firstVal) || (function_exists('is_serialized') && is_serialized($firstVal)) || str_starts_with(trim($firstVal), 'a:') || str_starts_with(trim($firstVal), 'O:')) {
                    continue;
                }

                $cleanVal = trim((string) $firstVal);
                if (!empty($cleanVal) && strlen($cleanVal) < 500) {
                    $fields[$cleanLabel] = $cleanVal;
                }
            }
        }

        return $fields;
    }

    /**
     * Format custom fields into human-readable Markdown specification bullets.
     */
    public function formatCustomFieldsMarkdown(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $lines = ["### Specifications & Details:"];
        foreach ($fields as $label => $value) {
            $lines[] = "- {$label}: {$value}";
        }

        return implode("\n", $lines);
    }
}
