<script setup>
const i18n = wp.i18n;
</script>

<template>
    <div class="botplus-card">
        <h2>{{ i18n.__('Embed Options', 'botplus') }}</h2>
        <p>{{ i18n.__('Add the chat widget to your site:', 'botplus') }}</p>
        <ul class="botplus-embed-list">
            <li><strong>{{ i18n.__('Auto:', 'botplus') }}</strong> {{ i18n.__('Widget appears automatically on all pages (default)', 'botplus') }}</li>
            <li><strong>{{ i18n.__('Shortcode:', 'botplus') }}</strong> <code>[botplus_chatbot]</code> - {{ i18n.__('Place anywhere', 'botplus') }}</li>
            <li><strong>{{ i18n.__('Block:', 'botplus') }}</strong> {{ i18n.__('"BotPlus AI Agent" block in Gutenberg editor', 'botplus') }}</li>
        </ul>
    </div>
</template>

<style scoped>
.botplus-embed-list {
    margin: 0;
    padding-left: 20px;
    line-height: 2;
    color: #374151;
}

.botplus-embed-list code {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
}
</style>