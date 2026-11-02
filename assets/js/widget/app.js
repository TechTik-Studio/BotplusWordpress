// BotPlus Widget Entry Point
import { createApp } from 'vue';
import App from './App.vue';
import '../../css/widget.css';

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

function init() {
    const mountPoint = document.getElementById('botplus-chat-widget');
    if (!mountPoint) {
        return;
    }

    // Get config from localized script
    const config = window.BotPlusConfig || {};
    
    const app = createApp(App, { config });
    app.mount('#botplus-chat-widget');
}