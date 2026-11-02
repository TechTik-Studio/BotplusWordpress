<script setup>
import BotPlusSkeletonBlock from './BotPlusSkeletonBlock.vue';
import BotPlusMetricGridSkeleton from './BotPlusMetricGridSkeleton.vue';
import BotPlusTableSkeleton from './BotPlusTableSkeleton.vue';
</script>

<template>
    <div class="botplus-page-skeleton">
        <!-- Top Control Bar Skeleton -->
        <div class="botplus-page-skeleton-controls">
            <div class="botplus-skeleton-tabs">
                <BotPlusSkeletonBlock width="80px" height="32px" radius="8px" />
                <BotPlusSkeletonBlock width="80px" height="32px" radius="8px" />
                <BotPlusSkeletonBlock width="80px" height="32px" radius="8px" />
            </div>
            <BotPlusSkeletonBlock width="110px" height="32px" radius="8px" />
        </div>

        <!-- 4-Card Metric Grid Skeleton -->
        <BotPlusMetricGridSkeleton :count="4" />

        <!-- Table Card Skeleton -->
        <BotPlusTableSkeleton :columns="5" :rows="5" />
    </div>
</template>

<style scoped>
.botplus-page-skeleton {
    width: 100%;
}

.botplus-page-skeleton-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.botplus-skeleton-tabs {
    display: flex;
    gap: 8px;
}
</style>

