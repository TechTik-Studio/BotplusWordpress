<script setup>
defineProps({
    title: { type: String, default: 'No data available yet' },
    description: { type: String, default: 'As customer interactions occur on your store, details will automatically populate here.' },
    icon: { type: String, default: 'inbox' },
    actionLabel: { type: String, default: '' },
    actionVariant: { type: String, default: 'primary' },
});

defineEmits(['action']);
</script>

<template>
    <div class="botplus-empty-state-card">
        <div class="botplus-empty-icon-circle">
            <!-- Inbox Icon -->
            <svg v-if="icon === 'inbox'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
            </svg>
            <!-- Cart Icon -->
            <svg v-else-if="icon === 'cart'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <!-- Users / Leads Icon -->
            <svg v-else-if="icon === 'leads'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <!-- Chart / Analytics Icon -->
            <svg v-else-if="icon === 'chart'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <!-- Sparkles Default Icon -->
            <svg v-else width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path>
            </svg>
        </div>

        <h3 class="botplus-empty-title">{{ title }}</h3>
        <p class="botplus-empty-desc">{{ description }}</p>

        <button
            v-if="actionLabel"
            type="button"
            class="botplus-empty-action-btn"
            :class="actionVariant"
            @click="$emit('action')"
        >
            {{ actionLabel }}
        </button>
    </div>
</template>

<style scoped>
.botplus-empty-state-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 48px 24px;
    margin: 20px 0;
}

.botplus-empty-icon-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 50%;
    margin-bottom: 16px;
}

.botplus-empty-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.botplus-empty-desc {
    margin: 8px 0 0;
    font-size: 13px;
    color: #64748b;
    max-width: 440px;
    line-height: 1.5;
}

.botplus-empty-action-btn {
    margin-top: 20px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 9px 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}

.botplus-empty-action-btn:hover {
    background: #1d4ed8;
}

.botplus-empty-action-btn.secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.botplus-empty-action-btn.secondary:hover {
    background: #e2e8f0;
}
</style>

