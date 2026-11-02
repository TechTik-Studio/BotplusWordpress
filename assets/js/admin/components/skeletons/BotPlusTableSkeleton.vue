<script setup>
import BotPlusSkeletonBlock from './BotPlusSkeletonBlock.vue';

defineProps({
    columns: { type: Number, default: 5 },
    rows: { type: Number, default: 5 },
});
</script>

<template>
    <div class="botplus-card botplus-table-skeleton-card">
        <div class="botplus-table-skeleton-header">
            <BotPlusSkeletonBlock width="35%" height="20px" radius="6px" />
            <BotPlusSkeletonBlock width="120px" height="34px" radius="8px" />
        </div>
        <div class="botplus-table-skeleton-wrap">
            <div class="botplus-table-skeleton-thead">
                <div v-for="c in columns" :key="'th-' + c" class="botplus-th-skeleton">
                    <BotPlusSkeletonBlock width="70%" height="12px" />
                </div>
            </div>
            <div v-for="r in rows" :key="'tr-' + r" class="botplus-table-skeleton-tr">
                <div v-for="c in columns" :key="'td-' + r + '-' + c" class="botplus-td-skeleton">
                    <BotPlusSkeletonBlock :width="c === 1 ? '85%' : (c === columns ? '60%' : '75%')" height="14px" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-table-skeleton-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    margin-bottom: 24px;
}

.botplus-table-skeleton-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.botplus-table-skeleton-wrap {
    width: 100%;
}

.botplus-table-skeleton-thead {
    display: flex;
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 12px;
}

.botplus-th-skeleton {
    flex: 1;
}

.botplus-table-skeleton-tr {
    display: flex;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    align-items: center;
}

.botplus-td-skeleton {
    flex: 1;
}
</style>

