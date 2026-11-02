<script setup>
import BotPlusSkeletonBlock from './BotPlusSkeletonBlock.vue';

defineProps({
    count: {
        type: Number,
        default: 5
    }
});
</script>

<template>
    <div class="botplus-conversation-skeleton-list">
        <div v-for="i in count" :key="i" class="botplus-conv-skeleton-item">
            <div class="botplus-conv-skeleton-top">
                <div class="botplus-conv-skeleton-user">
                    <BotPlusSkeletonBlock width="32px" height="32px" radius="50%" />
                    <div class="botplus-conv-skeleton-meta">
                        <BotPlusSkeletonBlock :width="i % 2 === 0 ? '110px' : '90px'" height="14px" radius="4px" />
                        <BotPlusSkeletonBlock width="60px" height="11px" radius="4px" style="margin-top: 4px;" />
                    </div>
                </div>
                <BotPlusSkeletonBlock width="36px" height="11px" radius="4px" />
            </div>

            <div class="botplus-conv-skeleton-body">
                <BotPlusSkeletonBlock :width="i % 3 === 0 ? '95%' : '80%'" height="12px" radius="4px" />
                <BotPlusSkeletonBlock :width="i % 2 === 0 ? '65%' : '45%'" height="12px" radius="4px" style="margin-top: 5px;" />
            </div>

            <div class="botplus-conv-skeleton-footer">
                <BotPlusSkeletonBlock width="64px" height="18px" radius="4px" />
                <BotPlusSkeletonBlock v-if="i % 2 === 0" width="55px" height="18px" radius="4px" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-conversation-skeleton-list {
    display: flex;
    flex-direction: column;
}

.botplus-conv-skeleton-item {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.botplus-conv-skeleton-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.botplus-conv-skeleton-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.botplus-conv-skeleton-meta {
    display: flex;
    flex-direction: column;
}

.botplus-conv-skeleton-body {
    display: flex;
    flex-direction: column;
    margin-top: 2px;
}

.botplus-conv-skeleton-footer {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}
</style>

