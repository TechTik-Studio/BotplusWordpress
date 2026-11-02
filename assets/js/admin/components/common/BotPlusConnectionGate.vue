<script setup>
import { computed } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useOAuth } from '@/composables/useOAuth';

const props = defineProps({
    title: {
        type: String,
        default: 'Connect Your Store to BotPlus AI'
    },
    subtitle: {
        type: String,
        default: 'Activate AI customer support, instant product recommendations, WISMO order tracking, and abandoned cart recovery for WooCommerce.'
    }
});

const emit = defineEmits(['go-settings']);

const store = useAdminStore();
const { initiateConnect, connecting } = useOAuth();

const logoUrl = computed(() => {
    return window.BotPlusAdminConfig?.logoUrl || (window.BotPlusAdminConfig?.pluginUrl ? window.BotPlusAdminConfig.pluginUrl + 'assets/images/botplus-logo.png' : '');
});

function handleConnect() {
    initiateConnect();
}

function handleGoSettings() {
    emit('go-settings');
}
</script>

<template>
    <div class="bp-connection-gate">
        <div class="bp-gate-card">
            <div class="bp-gate-icon-wrapper">
                <img v-if="logoUrl" :src="logoUrl" alt="BotPlus Logo" class="bp-gate-logo-img" />
                <div v-else class="bp-gate-fallback-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                </div>
            </div>

            <h2 class="bp-gate-title">{{ title }}</h2>
            <p class="bp-gate-subtitle">{{ subtitle }}</p>

            <div class="bp-gate-features">
                <div class="bp-gate-feature-item">
                    <svg class="bp-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>AI shopping agent & 24/7 product answers</span>
                </div>
                <div class="bp-gate-feature-item">
                    <svg class="bp-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Instant real-time order tracking & status lookup (WISMO)</span>
                </div>
                <div class="bp-gate-feature-item">
                    <svg class="bp-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Proactive abandoned cart recovery & opportunity detection</span>
                </div>
            </div>

            <div class="bp-gate-actions">
                <button
                    type="button"
                    class="bp-btn bp-btn-primary"
                    :disabled="connecting"
                    @click="handleConnect"
                >
                    <svg v-if="connecting" class="bp-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                    </svg>
                    <span v-if="connecting">Connecting to BotPlus...</span>
                    <span v-else>Connect Store to BotPlus</span>
                </button>

                <button
                    type="button"
                    class="bp-btn bp-btn-secondary"
                    @click="handleGoSettings"
                >
                    Settings & Credentials
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bp-connection-gate {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 48px 16px;
    width: 100%;
    box-sizing: border-box;
}

.bp-gate-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 40px 32px;
    max-width: 540px;
    width: 100%;
    text-align: center;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.03);
}

.bp-gate-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.bp-gate-logo-img {
    width: 44px;
    height: 44px;
    object-fit: contain;
    border-radius: 8px;
}

.bp-gate-fallback-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.bp-gate-title {
    margin: 0 0 10px 0;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.bp-gate-subtitle {
    margin: 0 auto 24px auto;
    font-size: 14px;
    line-height: 1.5;
    color: #64748b;
    max-width: 440px;
}

.bp-gate-features {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 28px;
    text-align: left;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    padding: 16px 18px;
}

.bp-gate-feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
}

.bp-check-icon {
    flex-shrink: 0;
}

.bp-gate-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.bp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 40px;
    padding: 0 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
}

.bp-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.bp-btn-primary {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
}

.bp-btn-primary:hover:not(:disabled) {
    background: #1d4ed8;
}

.bp-btn-secondary {
    background: #ffffff;
    color: #475569;
    border-color: #cbd5e1;
}

.bp-btn-secondary:hover:not(:disabled) {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
}

.bp-spinner {
    animation: bp-spin 1s linear infinite;
}

@keyframes bp-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

