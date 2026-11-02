<script setup>
import BotPlusSkeletonBlock from './BotPlusSkeletonBlock.vue';
</script>

<template>
    <div class="botplus-thread-skeleton">
        <!-- Message 1: Customer Inquiry (Left) -->
        <div class="botplus-msg-skeleton-row is-user">
            <div class="botplus-msg-skeleton-bubble is-user">
                <div class="botplus-bubble-skeleton-header">
                    <BotPlusSkeletonBlock width="70px" height="12px" radius="4px" />
                    <BotPlusSkeletonBlock width="45px" height="11px" radius="4px" />
                </div>
                <div class="botplus-bubble-skeleton-text">
                    <BotPlusSkeletonBlock width="85%" height="13px" radius="4px" />
                    <BotPlusSkeletonBlock width="55%" height="13px" radius="4px" style="margin-top: 6px;" />
                </div>
            </div>
        </div>

        <!-- Message 2: Bot Reply with Recommendation (Right) -->
        <div class="botplus-msg-skeleton-row is-bot">
            <div class="botplus-msg-skeleton-bubble is-bot">
                <div class="botplus-bubble-skeleton-header">
                    <BotPlusSkeletonBlock width="80px" height="12px" radius="4px" />
                    <BotPlusSkeletonBlock width="45px" height="11px" radius="4px" />
                </div>
                <div class="botplus-bubble-skeleton-text">
                    <BotPlusSkeletonBlock width="95%" height="13px" radius="4px" />
                    <BotPlusSkeletonBlock width="75%" height="13px" radius="4px" style="margin-top: 6px;" />
                </div>
                <!-- Embedded Product Card Skeleton -->
                <div class="botplus-bubble-product-skeleton">
                    <BotPlusSkeletonBlock width="52px" height="52px" radius="6px" />
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                        <BotPlusSkeletonBlock width="65%" height="13px" radius="4px" />
                        <BotPlusSkeletonBlock width="40%" height="11px" radius="4px" />
                        <BotPlusSkeletonBlock width="30%" height="14px" radius="4px" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Message 3: Customer Follow-up (Left) -->
        <div class="botplus-msg-skeleton-row is-user">
            <div class="botplus-msg-skeleton-bubble is-user">
                <div class="botplus-bubble-skeleton-header">
                    <BotPlusSkeletonBlock width="70px" height="12px" radius="4px" />
                    <BotPlusSkeletonBlock width="45px" height="11px" radius="4px" />
                </div>
                <div class="botplus-bubble-skeleton-text">
                    <BotPlusSkeletonBlock width="70%" height="13px" radius="4px" />
                </div>
            </div>
        </div>

        <!-- Message 4: Staff or AI Action Confirmation (Right) -->
        <div class="botplus-msg-skeleton-row is-bot">
            <div class="botplus-msg-skeleton-bubble is-bot">
                <div class="botplus-bubble-skeleton-header">
                    <BotPlusSkeletonBlock width="80px" height="12px" radius="4px" />
                    <BotPlusSkeletonBlock width="45px" height="11px" radius="4px" />
                </div>
                <div class="botplus-bubble-skeleton-text">
                    <BotPlusSkeletonBlock width="90%" height="13px" radius="4px" />
                    <BotPlusSkeletonBlock width="60%" height="13px" radius="4px" style="margin-top: 6px;" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-thread-skeleton {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 20px;
}

.botplus-msg-skeleton-row {
    display: flex;
    width: 100%;
}

.botplus-msg-skeleton-row.is-user {
    justify-content: flex-start;
}

.botplus-msg-skeleton-row.is-bot {
    justify-content: flex-end;
}

.botplus-msg-skeleton-bubble {
    width: 70%;
    max-width: 520px;
    padding: 14px 16px;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.botplus-msg-skeleton-bubble.is-user {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 2px;
}

.botplus-msg-skeleton-bubble.is-bot {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-bottom-right-radius: 2px;
}

.botplus-bubble-skeleton-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.botplus-bubble-skeleton-text {
    display: flex;
    flex-direction: column;
}

.botplus-bubble-product-skeleton {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    margin-top: 4px;
}
</style>

