<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useOAuth } from '@/composables/useOAuth';
import BotPlusHeader from '@/components/BotPlusHeader.vue';
import BotPlusConnectionGate from '@/components/common/BotPlusConnectionGate.vue';

// Page Components
import DashboardPage from '@/pages/DashboardPage.vue';
import InboxPage from '@/pages/InboxPage.vue';
import CartsPage from '@/pages/CartsPage.vue';
import LeadsPage from '@/pages/LeadsPage.vue';
import AnalyticsPage from '@/pages/AnalyticsPage.vue';
import AgentSettingsPage from '@/pages/AgentSettingsPage.vue';
import CapabilitiesPage from '@/pages/CapabilitiesPage.vue';
import BillingPage from '@/pages/BillingPage.vue';
import SettingsPage from '@/pages/SettingsPage.vue';

const store = useAdminStore();
const { checkUrlForAuthCode } = useOAuth();

function getInitialPage() {
    const urlParams = new URLSearchParams(window.location.search);
    const pageFromUrl = urlParams.get('page');
    if (pageFromUrl && pageFromUrl.startsWith('botplus')) {
        if (pageFromUrl === 'botplus-widget') return 'botplus-agent';
        if (pageFromUrl === 'botplus-knowledge' || pageFromUrl === 'botplus-sync') return 'botplus';
        return pageFromUrl;
    }
    const current = window.BotPlusAdminInitialState?.currentPage || window.BotPlusAdminConfig?.currentPage || 'botplus';
    if (current === 'botplus-widget') return 'botplus-agent';
    if (current === 'botplus-knowledge' || current === 'botplus-sync') return 'botplus';
    return current;
}

const activeTab = ref(getInitialPage());

const showConnectionGate = computed(() => {
    return !store.connected && activeTab.value !== 'botplus-settings';
});

function onTabChange(tabId) {
    activeTab.value = tabId;
    const url = new URL(window.location.href);
    url.searchParams.set('page', tabId);
    window.history.pushState({ page: tabId }, '', url.toString());
}

function handlePopState(e) {
    if (e.state && e.state.page) {
        activeTab.value = e.state.page;
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        const p = urlParams.get('page');
        if (p) activeTab.value = p;
    }
}

onMounted(() => {
    const initialConfig = window.BotPlusAdminInitialState || window.BotPlusAdminConfig || {};
    store.initializeFromConfig(initialConfig);
    checkUrlForAuthCode();
    window.addEventListener('popstate', handlePopState);
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
});
</script>

<template>
    <div class="botplus-admin-app">
        <BotPlusHeader :active-tab="activeTab" @change-tab="onTabChange" />

        <main class="botplus-content-area">
            <BotPlusConnectionGate v-if="showConnectionGate" @go-settings="onTabChange('botplus-settings')" />
            <template v-else>
                <DashboardPage v-if="activeTab === 'botplus'" />
                <InboxPage v-else-if="activeTab === 'botplus-inbox'" />
                <CartsPage v-else-if="activeTab === 'botplus-carts'" />
                <LeadsPage v-else-if="activeTab === 'botplus-leads'" />
                <AnalyticsPage v-else-if="activeTab === 'botplus-analytics'" />
                <AgentSettingsPage v-else-if="activeTab === 'botplus-agent'" />
                <CapabilitiesPage v-else-if="activeTab === 'botplus-capabilities'" />
                <BillingPage v-else-if="activeTab === 'botplus-billing'" />
                <SettingsPage v-else-if="activeTab === 'botplus-settings'" />
                <DashboardPage v-else />
            </template>
        </main>
    </div>
</template>

<style scoped>
.botplus-admin-app {
    width: 100%;
    max-width: 100%;
    margin: 15px 20px 30px 0;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    color: #1e293b;
}

.botplus-content-area {
    min-height: 480px;
}
</style>
