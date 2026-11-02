<?php

namespace BotPlus\Plugin\Widget;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    public function render(array $atts = []): string
    {
        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $chatbotId = $config->getChatbotId();

        if (!$chatbotId || !$config->isConnected()) {
            return '<!-- BotPlus: Not configured -->';
        }

        $atts = shortcode_atts([
            'height' => '600px',
            'width' => '100%',
        ], $atts, 'botplus_chatbot');

        $iframeSrc = esc_url($config->getBotPlusApiUrl() . '/embed/chat?bot=' . (int) $chatbotId);
        $height = esc_attr($atts['height']);
        $width = esc_attr($atts['width']);

        return sprintf(
            '<div class="botplus-chat-inline-container" style="width:%s; min-height:%s; margin:16px 0;">' .
            '<iframe src="%s" width="100%%" height="%s" frameborder="0" allow="clipboard-write" style="border:none; border-radius:12px; width:100%%; height:%s; box-shadow:0 4px 16px rgba(0,0,0,0.08);"></iframe>' .
            '</div>',
            $width,
            $height,
            $iframeSrc,
            $height,
            $height
        );
    }
}
