<script setup>
defineProps({
    width: { type: String, default: '100%' },
    height: { type: String, default: '14px' },
    radius: { type: String, default: '6px' },
    tone: { type: String, default: 'light' },
});
</script>

<template>
    <span
        class="botplus-skeleton-block"
        :class="{ 'is-dark': tone === 'dark' }"
        :style="{
            width,
            height,
            borderRadius: radius,
        }"
    ></span>
</template>

<style scoped>
.botplus-skeleton-block {
    display: inline-block;
    background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
    background-size: 200% 100%;
    animation: botplus-shimmer 1.5s infinite ease-in-out;
    vertical-align: middle;
}

.botplus-skeleton-block.is-dark {
    background: linear-gradient(90deg, rgba(255,255,255,0.08) 25%, rgba(255,255,255,0.18) 50%, rgba(255,255,255,0.08) 75%);
    background-size: 200% 100%;
}

@keyframes botplus-shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}
</style>

