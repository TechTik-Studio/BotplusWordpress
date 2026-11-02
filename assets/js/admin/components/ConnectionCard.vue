<script setup>
import { computed } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useOAuth } from '@/composables/useOAuth';

const store = useAdminStore();
const { connecting, error, success, initiateConnect, disconnect, syncKnowledge } = useOAuth();

const i18n = wp.i18n;

const agentInitial = computed(() => {
    const name = store.chatbotName || 'W';
    return name.trim().charAt(0).toUpperCase();
});

const isWooCommerce = computed(() => {
    return !!window.BotPlusAdminConfig?.siteCapabilities?.is_woocommerce;
});

const dashboardUrl = computed(() => {
    if (store.manageUrl) {
        return store.manageUrl;
    }
    const base = store.botplusAppUrl || window.BotPlusAdminConfig?.botplusAppUrl || window.BotPlusAdminInitialState?.botplusAppUrl || '';
    return base ? `${base.replace(/\/+$/, '')}/chatbots-page` : '/chatbots-page';
});

const formattedConnectedAt = computed(() => {
    if (!store.connectedAt) return 'Active';
    try {
        const d = new Date(store.connectedAt);
        if (isNaN(d.getTime())) return store.connectedAt;
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch (e) {
        return store.connectedAt;
    }
});

const formattedLastSyncedAt = computed(() => {
    if (!store.lastSyncedAt) return 'Pending initial sync';
    try {
        const d = new Date(store.lastSyncedAt);
        if (isNaN(d.getTime())) return store.lastSyncedAt;
        return d.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return store.lastSyncedAt;
    }
});

function formatCptLabel(type) {
    if (!type) return 'Items';
    return String(type)
        .replace(/^[_\-]+|[_\-]+$/g, '')
        .split(/[_\-]+/)
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}
</script>

<template>
    <div class="botplus-card">
        <div class="botplus-card-header">
            <h2>{{ i18n.__('Connection Status', 'botplus') }}</h2>
            <div v-if="store.connected" class="botplus-status-badge">
                <span class="botplus-status-dot"></span>
                <span>{{ i18n.__('Connected & Active', 'botplus') }}</span>
            </div>
        </div>

        <!-- Connecting / Exchanging Spinner Overlay -->
        <div v-if="connecting" class="botplus-connecting-state">
            <div class="botplus-spinner"></div>
            <h3>{{ i18n.__('Authorizing Connection...', 'botplus') }}</h3>
            <p>{{ i18n.__('Exchanging credentials and securing connection with BotPlus platform.', 'botplus') }}</p>
        </div>
        
        <!-- Disconnected State (Promo & Connect Button) -->
        <div v-else-if="!store.connected">
            <div class="botplus-connection-promo">
                <div class="botplus-promo-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                </div>
                <h3>{{ i18n.__('Connect to BotPlus', 'botplus') }}</h3>
                <p>{{ i18n.__('Link your WordPress site to BotPlus to deploy your dedicated AI storefront agent for customer support, product discovery, and order help.', 'botplus') }}</p>
                
                <ul class="botplus-feature-list">
                    <li>{{ i18n.__('Product guidance & intelligent catalog answers', 'botplus') }}</li>
                    <li>{{ i18n.__('Order tracking & shipping lookup automation', 'botplus') }}</li>
                    <li>{{ i18n.__('Cart recovery & checkout assistance for WooCommerce', 'botplus') }}</li>
                    <li>{{ i18n.__('Seamless human agent handover escalation', 'botplus') }}</li>
                </ul>

                <button 
                    class="botplus-btn botplus-btn-primary botplus-btn-large" 
                    @click="initiateConnect" 
                    :disabled="connecting"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                    <span>{{ i18n.__('Connect to BotPlus', 'botplus') }}</span>
                </button>
            </div>
        </div>
        
        <!-- Connected State -->
        <div v-else class="botplus-connected-container">
            <!-- Hero Agent Profile Banner -->
            <div class="botplus-agent-banner">
                <div class="botplus-agent-avatar">
                    {{ agentInitial }}
                </div>
                <div class="botplus-agent-meta">
                    <div class="botplus-agent-name">{{ store.chatbotName || 'Storefront Agent' }}</div>
                    <div class="botplus-agent-account">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>{{ store.botplusUserEmail || 'Authenticated workspace' }}</span>
                    </div>
                </div>
                <div class="botplus-banner-actions">
                    <button class="botplus-btn botplus-btn-ghost-danger" @click="disconnect" :disabled="store.disconnecting">
                        {{ store.disconnecting ? i18n.__('Disconnecting...', 'botplus') : i18n.__('Disconnect', 'botplus') }}
                    </button>
                </div>
            </div>

            <!-- Configuration Details Grid -->
            <div class="botplus-details-grid">
                <div class="botplus-detail-card">
                    <span class="botplus-detail-label">{{ i18n.__('Site ID', 'botplus') }}</span>
                    <strong class="botplus-detail-val">#{{ store.siteId }}</strong>
                </div>
                <div class="botplus-detail-card">
                    <span class="botplus-detail-label">{{ i18n.__('Agent ID', 'botplus') }}</span>
                    <strong class="botplus-detail-val">#{{ store.chatbotId }}</strong>
                </div>
                <div class="botplus-detail-card">
                    <span class="botplus-detail-label">{{ i18n.__('Connected Since', 'botplus') }}</span>
                    <strong class="botplus-detail-val">{{ formattedConnectedAt }}</strong>
                </div>
                <div class="botplus-detail-card">
                    <span class="botplus-detail-label">{{ i18n.__('Storefront Embed', 'botplus') }}</span>
                    <div class="botplus-embed-active">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>{{ i18n.__('Active on all pages', 'botplus') }}</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="botplus-dashboard-actions">
                <a 
                    :href="dashboardUrl" 
                    target="_blank" 
                    rel="noopener noreferrer" 
                    class="botplus-btn botplus-btn-primary"
                >
                    <span>{{ i18n.__('Manage Agent & AI Settings on BotPlus ↗', 'botplus') }}</span>
                </a>
            </div>
        </div>
        
        <div v-if="error" class="botplus-alert botplus-alert-error">
            {{ error }}
        </div>
        <div v-if="success" class="botplus-alert botplus-alert-success">
            {{ success }}
        </div>
    </div>
</template>

<style scoped>
.botplus-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f0f2f5;
}

.botplus-card-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 750;
    color: #0f1533;
}

/* Connecting State */
.botplus-connecting-state {
    text-align: center;
    padding: 48px 24px;
}

.botplus-spinner {
    width: 42px;
    height: 42px;
    margin: 0 auto 16px;
    border: 3.5px solid #e0e7ff;
    border-top-color: #5b4fe8;
    border-radius: 50%;
    animation: bp-spin 0.9s linear infinite;
}

@keyframes bp-spin {
    to { transform: rotate(360deg); }
}

.botplus-connecting-state h3 {
    margin: 0 0 8px;
    color: #0f1533;
    font-size: 18px;
    font-weight: 700;
}

.botplus-connecting-state p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

/* Disconnected Promo */
.botplus-connection-promo {
    text-align: center;
    padding: 32px 16px;
}

.botplus-promo-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: #eef0fe;
    color: #5b4fe8;
}

.botplus-connection-promo h3 {
    margin: 0 0 10px;
    font-size: 22px;
    font-weight: 800;
    color: #0f1533;
}

.botplus-connection-promo p {
    margin: 0 auto 24px;
    color: #64748b;
    font-size: 14.5px;
    max-width: 520px;
    line-height: 1.55;
}

.botplus-feature-list {
    list-style: none;
    padding: 0;
    margin: 0 auto 28px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    text-align: left;
    max-width: 640px;
}

.botplus-feature-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 13px;
    color: #334155;
    font-weight: 600;
}

.botplus-feature-list li::before {
    content: '✓';
    color: #10b981;
    font-weight: 800;
}

/* Connected State */
.botplus-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 4px 8px;
    border-radius: 4px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    font-size: 12px;
    font-weight: 700;
}

.botplus-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
}

.botplus-agent-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 18px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.botplus-agent-avatar {
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: linear-gradient(135deg, #22d3ee, #5b4fe8);
    color: #ffffff;
    font-size: 22px;
    font-weight: 900;
    box-shadow: 0 8px 18px rgba(91, 79, 232, 0.25);
    flex-shrink: 0;
}

.botplus-agent-meta {
    flex: 1;
    min-width: 0;
}

.botplus-agent-name {
    color: #0f1533;
    font-size: 16px;
    font-weight: 800;
}

.botplus-agent-account {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    color: #64748b;
    font-size: 12.5px;
}

.botplus-details-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 22px;
}

.botplus-detail-card {
    padding: 12px 14px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e8ecf4;
}

.botplus-detail-label {
    display: block;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}

.botplus-detail-val {
    color: #0f1533;
    font-size: 14px;
    font-weight: 800;
}

.botplus-embed-active {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #059669;
    font-size: 12.5px;
    font-weight: 750;
}

.botplus-dashboard-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.botplus-btn-ghost-danger {
    padding: 7px 14px;
    border-radius: 8px;
    border: 1px solid #fecaca;
    background: #ffffff;
    color: #dc2626;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.botplus-btn-ghost-danger:hover:not(:disabled) {
    background: #fef2f2;
    border-color: #f87171;
}

.botplus-btn-ghost-danger:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* AI Knowledge & Catalog Sync Panel */
.botplus-sync-panel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 20px;
}

.botplus-sync-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.botplus-sync-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.botplus-sync-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
}

.botplus-sync-title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
}

.botplus-sync-subtitle {
    margin: 3px 0 0 0;
    font-size: 12px;
    color: #64748b;
}

.botplus-sync-subtitle strong {
    color: #334155;
}

.botplus-btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border-radius: 9px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.botplus-btn-secondary:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}

.botplus-btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.botplus-spinner-small {
    width: 14px;
    height: 14px;
    border: 2px solid #cbd5e1;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: botplus-spin 0.7s linear infinite;
    display: inline-block;
    margin-right: 6px;
}

.botplus-sync-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #cbd5e1;
}

.botplus-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 11px;
    color: #475569;
}

.botplus-stat-pill strong {
    color: #4f46e5;
    font-weight: 700;
}

.botplus-stat-queue {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}

.botplus-stat-queue strong {
    color: #047857;
}

.botplus-sync-errors {
    margin-top: 12px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    font-size: 12px;
}

.botplus-sync-error-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-weight: 700;
}

.botplus-sync-error-list {
    margin: 0;
    padding-left: 20px;
    list-style-type: disc;
}

.botplus-sync-error-list li {
    margin-bottom: 2px;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .botplus-feature-list {
        grid-template-columns: 1fr;
    }
    .botplus-details-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .botplus-agent-banner {
        flex-direction: column;
        align-items: flex-start;
    }
    .botplus-banner-actions {
        width: 100%;
        margin-top: 10px;
    }
}
</style>