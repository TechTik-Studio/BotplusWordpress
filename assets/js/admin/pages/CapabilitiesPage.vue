<script setup>
import { ref, onMounted } from 'vue';
import BotPlusSkeletonBlock from '@/components/skeletons/BotPlusSkeletonBlock.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';
import BotPlusEmptyState from '@/components/common/BotPlusEmptyState.vue';

const loading = ref(true);
const errorMessage = ref('');
const saveSuccess = ref(false);
const saveMessage = ref('');
const processingKey = ref(null);

const siteData = ref({});
const chatbotData = ref({});
const telemetryData = ref({});
const capabilityGroups = ref([]);

function getStatusDotClass(tool) {
    if (tool.disabledReason) {
        return 'is-warning';
    }
    return tool.enabled ? 'is-success' : 'is-neutral';
}

async function fetchCapabilities() {
    loading.value = true;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(`${ajaxUrl}?action=botplus_get_capabilities&nonce=${encodeURIComponent(nonce)}`, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        if (json.success && json.data) {
            const data = json.data;
            siteData.value = data.site || {};
            chatbotData.value = data.chatbot || {};
            telemetryData.value = data.telemetry || {};
            capabilityGroups.value = data.groups || [];
        } else {
            errorMessage.value = json.data?.message || 'Failed to load store capabilities.';
        }
    } catch (e) {
        console.error('Error fetching store capabilities:', e);
        errorMessage.value = 'Network error while loading capabilities: ' + e.message;
    } finally {
        loading.value = false;
    }
}

async function toggleFeature(tool) {
    if (processingKey.value || tool.disabledReason || tool.alwaysActiveWhenWoo) {
        return;
    }

    const previousState = tool.enabled;
    const targetState = !tool.enabled;

    // Optimistic UI update
    tool.enabled = targetState;
    processingKey.value = tool.key;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(`${ajaxUrl}?action=botplus_toggle_capability&nonce=${encodeURIComponent(nonce)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                feature: tool.key,
                enabled: targetState,
            }),
        });

        const json = await res.json();

        if (json.success && json.data) {
            saveMessage.value = `${tool.name} ${targetState ? 'activated' : 'paused'} successfully.`;
            saveSuccess.value = true;
            setTimeout(() => {
                saveSuccess.value = false;
            }, 3000);

            // Re-sync all dependent states from backend
            await fetchCapabilities();
        } else {
            // Revert on failure
            tool.enabled = previousState;
            errorMessage.value = json.data?.message || json.message || `Failed to update ${tool.name}.`;
        }
    } catch (e) {
        tool.enabled = previousState;
        errorMessage.value = `Network error updating ${tool.name}: ${e.message}`;
    } finally {
        processingKey.value = null;
    }
}

onMounted(() => {
    fetchCapabilities();
});
</script>

<template>
    <div class="botplus-page-capabilities">
        <!-- Header -->
        <div class="botplus-capabilities-header">
            <div>
                <h2 class="botplus-section-title">Agent Store Capabilities</h2>
                <p class="botplus-section-desc">Manage what actions your AI shopping agent is permitted to execute on your store.</p>
            </div>

            <!-- Toast Success Notice -->
            <transition name="fade">
                <div v-if="saveSuccess" class="botplus-save-toast">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    {{ saveMessage }}
                </div>
            </transition>
        </div>

        <!-- Error Alert / Inline Retry -->
        <div v-if="errorMessage" class="botplus-error-banner">
            <div class="botplus-error-text">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="fetchCapabilities" class="botplus-btn-retry">
                Retry
            </button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading && !capabilityGroups.length" style="display: flex; flex-direction: column; gap: 24px;">
            <div v-for="g in 3" :key="g" style="display: flex; flex-direction: column; gap: 14px;">
                <BotPlusSkeletonBlock width="180px" height="22px" radius="4px" />
                <BotPlusSkeletonBlock width="340px" height="14px" radius="4px" />
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 16px;">
                    <div v-for="c in 2" :key="c" class="botplus-card" style="display: flex; flex-direction: column; gap: 12px; padding: 20px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <BotPlusSkeletonBlock width="40px" height="40px" radius="8px" />
                            <div style="display: flex; flex-direction: column; gap: 6px; flex: 1;">
                                <BotPlusSkeletonBlock width="50%" height="18px" radius="4px" />
                                <BotPlusSkeletonBlock width="80%" height="14px" radius="4px" />
                            </div>
                        </div>
                        <BotPlusSkeletonBlock width="100%" height="32px" radius="4px" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <BotPlusEmptyState
            v-else-if="!capabilityGroups.length"
            icon="settings"
            title="No store capabilities found"
            description="Unable to load agent tools. Ensure your store is connected to BotPlus."
        />

        <!-- Grouped Capabilities List -->
        <div v-else class="botplus-groups-container">
            <section 
                v-for="group in capabilityGroups" 
                :key="group.title" 
                class="botplus-group-section"
            >
                <div class="botplus-group-header">
                    <h3 class="botplus-group-title">{{ group.title }}</h3>
                    <p class="botplus-group-desc">{{ group.description }}</p>
                </div>

                <div class="botplus-capabilities-grid">
                    <div 
                        v-for="tool in group.tools" 
                        :key="tool.key"
                        class="botplus-card botplus-capability-card"
                        :class="{ 
                            'is-disabled': !tool.enabled,
                            'is-blocked': !!tool.disabledReason
                        }"
                    >
                        <div class="botplus-cap-main">
                            <!-- Monochromatic SVG Icon Badge -->
                            <div class="botplus-cap-icon-box">
                                <svg v-if="tool.icon === 'WM'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    <path d="M12 7v2m0 4h.01"></path>
                                </svg>
                                <svg v-else-if="tool.icon === 'LI'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <line x1="19" y1="8" x2="19" y2="14"></line>
                                    <line x1="22" y1="11" x2="16" y2="11"></line>
                                </svg>
                                <svg v-else-if="tool.icon === 'PD'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    <path d="M11 8v6M8 11h6"></path>
                                </svg>
                                <svg v-else-if="tool.icon === 'CT'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                                <svg v-else-if="tool.icon === 'CR'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                    <path d="M3 3v5h5"></path>
                                </svg>
                                <svg v-else-if="tool.icon === 'OT'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                                <svg v-else-if="tool.icon === 'HH'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                </svg>
                                <svg v-else-if="tool.icon === 'RS'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10"></polyline>
                                    <polyline points="1 20 1 14 7 14"></polyline>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </div>

                            <!-- Content Info -->
                            <div class="botplus-cap-info">
                                <div class="botplus-cap-title-row">
                                    <h4 class="botplus-cap-name">{{ tool.name }}</h4>
                                    <span v-if="tool.recommended" class="botplus-rec-label">Recommended</span>
                                    <span class="bp-status-tag">
                                        <span :class="['bp-status-dot', getStatusDotClass(tool)]"></span>
                                        {{ tool.status }}
                                    </span>
                                </div>

                                <p class="botplus-cap-desc">{{ tool.description }}</p>

                                <!-- Dynamic Contextual Detail -->
                                <div class="botplus-cap-dynamic-detail">
                                    <span class="botplus-bullet">&bull;</span>
                                    <span>{{ tool.detail }}</span>
                                </div>

                                <!-- Conversational Utterance Example -->
                                <div v-if="tool.example" class="botplus-cap-example-box">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <span>{{ tool.example }}</span>
                                </div>

                                <!-- Dependency Block Warning -->
                                <div v-if="tool.disabledReason" class="botplus-dependency-alert">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                        <line x1="12" y1="9" x2="12" y2="13"></line>
                                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                    </svg>
                                    <span>{{ tool.disabledReason }}</span>
                                </div>
                            </div>

                            <!-- Toggle Switch / Action -->
                            <div class="botplus-cap-toggle-wrapper">
                                <label 
                                    class="botplus-toggle"
                                    :class="{ 'is-toggle-disabled': !!tool.disabledReason || tool.alwaysActiveWhenWoo }"
                                >
                                    <input 
                                        type="checkbox" 
                                        :checked="tool.enabled" 
                                        :disabled="!!tool.disabledReason || tool.alwaysActiveWhenWoo || processingKey === tool.key"
                                        @change="toggleFeature(tool)"
                                    />
                                    <span class="botplus-slider round"></span>
                                </label>
                                
                                <span v-if="processingKey === tool.key" class="botplus-spinner-sm"></span>
                                <span v-else class="botplus-toggle-status-text">
                                    {{ tool.enabled ? 'Active' : 'Paused' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.botplus-page-capabilities {
    padding: 6px 0 24px 0;
}

.botplus-capabilities-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}

.botplus-section-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-section-desc {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

.botplus-save-toast {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(4, 120, 87, 0.06);
}

.botplus-error-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #9f1239;
}

.botplus-error-text {
    display: flex;
    align-items: center;
    gap: 8px;
}

.botplus-btn-retry {
    background: #be123c;
    color: #ffffff;
    border: none;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}

.botplus-btn-retry:hover {
    background: #9f1239;
}

/* Skeletons */
.botplus-skeleton-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.botplus-skeleton-group {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.botplus-skeleton-line.short {
    width: 180px;
    height: 16px;
    background: #e2e8f0;
    border-radius: 4px;
    animation: pulse 1.5s infinite;
}

.botplus-skeleton-card {
    width: 100%;
    height: 90px;
    background: #f1f5f9;
    border-radius: 8px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Groups */
.botplus-groups-container {
    display: flex;
    flex-direction: column;
    gap: 28px;
}

.botplus-group-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.botplus-group-header {
    margin-bottom: 18px;
}

.botplus-group-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-group-desc {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

.botplus-capabilities-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* Capability Card */
.botplus-capability-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.botplus-capability-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

.botplus-capability-card.is-disabled {
    background: #fafafa;
}

.botplus-capability-card.is-blocked {
    background: #fdfdfe;
    opacity: 0.85;
}

.botplus-cap-main {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

/* Monochromatic Icon Box */
.botplus-cap-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.botplus-capability-card:hover .botplus-cap-icon-box {
    border-color: #cbd5e1;
    color: #2563eb;
    background: #eff6ff;
}

.botplus-cap-info {
    flex: 1;
    min-width: 0;
}

.botplus-cap-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}

.botplus-cap-name {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-rec-label {
    font-size: 11px;
    font-weight: 600;
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    padding: 2px 7px;
    border-radius: 4px;
}

.botplus-cap-desc {
    margin: 0 0 6px 0;
    font-size: 13px;
    color: #475569;
    line-height: 1.45;
}

.botplus-cap-dynamic-detail {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 6px;
}

.botplus-bullet {
    font-weight: 900;
    color: #94a3b8;
}

.botplus-cap-example-box {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    color: #334155;
    font-style: italic;
    margin-top: 2px;
}

.botplus-dependency-alert {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    padding: 4px 10px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    color: #b45309;
}

/* Toggle Switch */
.botplus-cap-toggle-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    flex-shrink: 0;
}

.botplus-toggle {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.botplus-toggle.is-toggle-disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.botplus-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.botplus-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: .2s;
    border-radius: 24px;
}

.botplus-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .2s;
    border-radius: 50%;
}

input:checked + .botplus-slider {
    background-color: #2563eb;
}

input:checked + .botplus-slider:before {
    transform: translateX(20px);
}

.botplus-toggle-status-text {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.botplus-spinner-sm {
    width: 14px;
    height: 14px;
    border: 2px solid #cbd5e1;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
