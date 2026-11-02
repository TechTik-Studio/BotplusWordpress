<script setup>
import ConnectionCard from '@/components/ConnectionCard.vue';
import { useAdminStore } from '@/stores/admin';

const store = useAdminStore();
</script>

<template>
    <div class="botplus-page botplus-settings-page">
        <!-- Primary Cloud Connection Card -->
        <ConnectionCard />
    </div>
</template>

<style scoped>
.botplus-settings-page {
    max-width: 900px;
}
</style>
