<?php

namespace BotPlus\Plugin\Sync;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class BuilderExtractor
{
    /**
     * Recursively extract clean human-readable text strings from an Elementor JSON elements tree.
     *
     * @param array $elements
     * @return array List of extracted human text strings
     */
    public function parseElementorTree(array $elements): array
    {
        $texts = [];

        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            // Extract text from widget settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                $settings = $element['settings'];

                // Common text fields in Elementor widgets (headings, editors, descriptions)
                $textKeys = [
                    'title',
                    'heading',
                    'title_text',
                    'editor',
                    'description',
                    'description_text',
                    'sub_title',
                    'caption',
                    'text',
                    'html',
                ];

                foreach ($textKeys as $key) {
                    if (!empty($settings[$key]) && is_string($settings[$key])) {
                        $raw = trim($settings[$key]);
                        // Filter out empty, CSS color values, or raw URLs
                        if (!empty($raw) && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $raw) && !str_starts_with($raw, 'http')) {
                            $texts[] = $raw;
                        }
                    }
                }

                // Repeater items (e.g. icon lists, features, tabs, slides, pricing tables)
                $repeaterKeys = [
                    'icon_list',
                    'tabs',
                    'slides',
                    'features_list',
                    'items',
                ];

                foreach ($repeaterKeys as $repKey) {
                    if (!empty($settings[$repKey]) && is_array($settings[$repKey])) {
                        foreach ($settings[$repKey] as $item) {
                            if (!is_array($item)) {
                                continue;
                            }
                            foreach (['text', 'title', 'tab_title', 'tab_content', 'item_title', 'item_description'] as $subKey) {
                                if (!empty($item[$subKey]) && is_string($item[$subKey])) {
                                    $rawSub = trim($item[$subKey]);
                                    if (!empty($rawSub)) {
                                        $texts[] = $rawSub;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Recurse into child elements (sections -> columns -> inner sections -> widgets)
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $texts = array_merge($texts, $this->parseElementorTree($element['elements']));
            }
        }

        return $texts;
    }

    /**
     * Extract and assemble all human-readable text from an Elementor page.
     *
     * @param int $postId
     * @return string Extracted human-readable text
     */
    public function extractElementorContent(int $postId): string
    {
        if (!function_exists('get_post_meta')) {
            return '';
        }

        $elementorData = get_post_meta($postId, '_elementor_data', true);
        if (empty($elementorData)) {
            return '';
        }

        $elements = is_string($elementorData) ? json_decode($elementorData, true) : $elementorData;
        if (!is_array($elements)) {
            return '';
        }

        $rawTexts = $this->parseElementorTree($elements);
        if (empty($rawTexts)) {
            return '';
        }

        return implode("\n\n", $rawTexts);
    }
}

