<script setup>
import BotPlusSkeletonBlock from './BotPlusSkeletonBlock.vue';

defineProps({
    count: { type: Number, default: 4 },
});
</script>

<template>
    <div class="botplus-metric-grid-skeleton">
        <div v-for="i in count" :key="i" class="botplus-card botplus-metric-card-skeleton">
            <div class="botplus-card-header-skeleton">
                <BotPlusSkeletonBlock width="40%" height="13px" />
                <BotPlusSkeletonBlock width="32px" height="32px" radius="8px" />
            </div>
            <div class="botplus-card-value-skeleton">
                <BotPlusSkeletonBlock width="65%" height="28px" radius="8px" />
            </div>
            <div class="botplus-card-hint-skeleton">
                <BotPlusSkeletonBlock width="80%" height="11px" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-metric-grid-skeleton {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.botplus-metric-card-skeleton {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}

.botplus-card-header-skeleton {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.botplus-card-value-skeleton {
    margin-bottom: 12px;
}

.botplus-card-hint-skeleton {
    margin-top: 8px;
}
</style>

