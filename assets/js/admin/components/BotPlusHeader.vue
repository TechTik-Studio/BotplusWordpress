<script setup>
import { computed } from 'vue';
import { useAdminStore } from '@/stores/admin';

const props = defineProps({
    activeTab: {
        type: String,
        default: 'botplus'
    }
});

const emit = defineEmits(['change-tab']);

const store = useAdminStore();

const tabs = [
    { id: 'botplus', label: 'Overview' },
    { id: 'botplus-inbox', label: 'Inbox' },
    { id: 'botplus-carts', label: 'Carts & Recovery' },
    { id: 'botplus-leads', label: 'Opportunities' },
    { id: 'botplus-analytics', label: 'Analytics' },
    { id: 'botplus-agent', label: 'Agent Settings' },
    { id: 'botplus-capabilities', label: 'Capabilities' },
    { id: 'botplus-billing', label: 'Billing & Plans' },
    { id: 'botplus-settings', label: 'Settings' },
];

const siteUrl = computed(() => {
    return window.BotPlusAdminConfig?.siteUrl || '/';
});

const logoUrl = computed(() => {
    return window.BotPlusAdminConfig?.logoUrl || (window.BotPlusAdminConfig?.pluginUrl ? window.BotPlusAdminConfig.pluginUrl + 'assets/images/botplus-logo.png' : '');
});

function handleTabClick(tabId) {
    emit('change-tab', tabId);
}
</script>

<template>
    <header class="botplus-header">
        <div class="botplus-header-top">
            <div class="botplus-header-brand">
                <!-- BotPlus Official Logo Mark -->
                <div class="botplus-logo-mark" aria-hidden="true">
                    <img v-if="logoUrl" :src="logoUrl" alt="BotPlus AI" class="botplus-logo-img" />
                    <svg v-else width="26" height="26" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="32" height="32" rx="8" fill="url(#botplus-logo-grad)" />
                        <!-- Chat Bubble & Spark Monogram -->
                        <path d="M9 16C9 12.134 12.134 9 16 9C19.866 9 23 12.134 23 16C23 19.866 19.866 23 16 23C14.6193 23 13.3283 22.6006 12.2359 21.9113L8.5 23.5L9.67385 19.9882C9.24151 18.7758 9 17.4194 9 16Z" fill="#FFFFFF" fill-opacity="0.95" />
                        <!-- AI Sparkle Core -->
                        <path d="M16 12.5L16.8 14.8L19.2 15.6L16.8 16.4L16 18.8L15.2 16.4L12.8 15.6L15.2 14.8L16 12.5Z" fill="#2563EB" />
                        <defs>
                            <linearGradient id="botplus-logo-grad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#3B82F6" />
                                <stop offset="1" stop-color="#1D4ED8" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <div class="botplus-header-info">
                    <div class="botplus-title-row">
                        <h1 class="botplus-app-title">BotPlus AI Agent</h1>
                    </div>
                </div>
            </div>

            <div class="botplus-header-actions">
                <a :href="siteUrl" target="_blank" rel="noopener noreferrer" class="botplus-btn botplus-btn-secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    Visit Storefront
                </a>
            </div>
        </div>

        <!-- Navigation Tabs Bar -->
        <nav class="botplus-tabs-bar" aria-label="BotPlus Navigation">
            <ul class="botplus-tabs-list">
                <li v-for="tab in tabs" :key="tab.id" class="botplus-tab-item">
                    <button
                        type="button"
                        class="botplus-tab-button"
                        :class="{ 'is-active': activeTab === tab.id }"
                        @click="handleTabClick(tab.id)"
                    >
                        <span>{{ tab.label }}</span>
                        <span v-if="tab.badge" class="botplus-tab-badge">{{ tab.badge }}</span>
                    </button>
                </li>
            </ul>
        </nav>
    </header>
</template>

<style scoped>
.botplus-header {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.botplus-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 16px;
}

.botplus-header-brand {
    display: flex;
    align-items: center;
    gap: 14px;
}

.botplus-logo-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
    flex-shrink: 0;
    overflow: hidden;
}

.botplus-logo-img {
    width: 38px;
    height: 38px;
    object-fit: contain;
    border-radius: 7px;
}

.botplus-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.botplus-app-title {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.botplus-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.botplus-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 36px;
    padding: 0 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
    border: 1px solid transparent;
}

.botplus-btn-secondary {
    background: #ffffff;
    color: #334155;
    border-color: #cbd5e1;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.botplus-btn-secondary:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Tabs Bar */
.botplus-tabs-bar {
    padding: 0 20px;
    overflow-x: auto;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    scrollbar-width: thin;
}

.botplus-tabs-list {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 8px;
}

.botplus-tab-item {
    margin: 0;
}

.botplus-tab-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 14px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.botplus-tab-button:hover {
    color: #0f172a;
}

.botplus-tab-button.is-active {
    color: #2563eb;
    font-weight: 600;
    border-bottom-color: #2563eb;
}

.botplus-tab-badge {
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    background: #ef4444;
    color: #ffffff;
    border-radius: 4px; /* Crisp rectangular badge */
}
</style>
