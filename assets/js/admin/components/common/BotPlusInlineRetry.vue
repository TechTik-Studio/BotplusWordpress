<script setup>
defineProps({
    title: { type: String, default: 'Unable to load section data' },
    message: { type: String, default: 'A network error occurred while communicating with the BotPlus API.' },
    actionLabel: { type: String, default: 'Try again' },
    busy: { type: Boolean, default: false },
});

defineEmits(['retry']);
</script>

<template>
    <div class="botplus-inline-retry-banner">
        <div class="botplus-inline-retry-left">
            <div class="botplus-retry-icon-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div>
                <h4 class="botplus-retry-title">{{ title }}</h4>
                <p class="botplus-retry-msg">{{ message }}</p>
            </div>
        </div>
        <button
            type="button"
            class="botplus-btn-retry-action"
            :disabled="busy"
            @click="$emit('retry')"
        >
            <svg v-if="busy" class="botplus-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                <path d="M12 2a10 10 0 0 1 10 10"></path>
            </svg>
            <span>{{ busy ? 'Retrying...' : actionLabel }}</span>
        </button>
    </div>
</template>

<style scoped>
.botplus-inline-retry-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fef2f2;
    border: 1px solid #fecdd3;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    gap: 16px;
}

.botplus-inline-retry-left {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.botplus-retry-icon-box {
    color: #e11d48;
    margin-top: 2px;
    flex-shrink: 0;
}

.botplus-retry-title {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #9f1239;
}

.botplus-retry-msg {
    margin: 4px 0 0;
    font-size: 13px;
    color: #be123c;
    line-height: 1.4;
}

.botplus-btn-retry-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e11d48;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s ease;
}

.botplus-btn-retry-action:hover:not(:disabled) {
    background: #be123c;
}

.botplus-btn-retry-action:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.botplus-spin {
    animation: botplus-spin-kf 1s linear infinite;
}

@keyframes botplus-spin-kf {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
    .botplus-inline-retry-banner {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

