import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useAdminStore = defineStore('admin', () => {
    // State
    const connected = ref(false);
    const chatbotId = ref(null);
    const chatbotName = ref('');
    const siteId = ref(null);
    const botplusUserId = ref(null);
    const botplusUserEmail = ref('');
    const connectedAt = ref('');
    const manageUrl = ref('');
    const botplusAppUrl = ref('');
    const connectUrl = ref('');
    const widgetSettings = ref({});
    const connecting = ref(false);
    const disconnecting = ref(false);
    const syncing = ref(false);
    const savingSettings = ref(false);
    const lastSyncedAt = ref('');
    const lastSyncStats = ref(null);
    const error = ref(null);
    const success = ref(null);

    // Dashboard Live State
    const dashboardOverview = ref(null);
    const dashboardLoading = ref(false);
    const dashboardPeriod = ref('30d');
    const dashboardError = ref(null);
    const dashboardCached = ref(false);

    // Getters
    const isLoading = computed(() => connecting.value || disconnecting.value || savingSettings.value || syncing.value);
    const hasError = computed(() => !!error.value);
    const hasSuccess = computed(() => !!success.value);

    // Actions
    function setConnected(data) {
        connected.value = true;
        chatbotId.value = data.chatbot_id;
        chatbotName.value = data.chatbot_name || '';
        siteId.value = data.site_id;
        botplusUserId.value = data.botplus_user_id;
        botplusUserEmail.value = data.botplus_user_email || '';
        connectedAt.value = data.connected_at || '';
        manageUrl.value = data.manage_url || '';
    }

    function setDisconnected() {
        connected.value = false;
        chatbotId.value = null;
        chatbotName.value = '';
        siteId.value = null;
        botplusUserId.value = null;
        botplusUserEmail.value = '';
        connectedAt.value = '';
        manageUrl.value = '';
        botplusAppUrl.value = '';
        connectUrl.value = '';
    }

    function setWidgetSettings(settings) {
        widgetSettings.value = settings;
    }

    function setError(msg) {
        error.value = msg;
        success.value = null;
    }

    function setSuccess(msg) {
        success.value = msg;
        error.value = null;
    }

    function clearMessages() {
        error.value = null;
        success.value = null;
    }

    function setConnecting(val) {
        connecting.value = val;
    }

    function setDisconnecting(val) {
        disconnecting.value = val;
    }

    function setSavingSettings(val) {
        savingSettings.value = val;
    }

    function setSyncing(val) {
        syncing.value = val;
    }

    function setSyncStats(stats, timestamp) {
        lastSyncStats.value = stats;
        if (timestamp) {
            lastSyncedAt.value = timestamp;
        }
    }

    function initializeFromConfig(config) {
        connected.value = config.connected || false;
        chatbotId.value = config.chatbotId || null;
        chatbotName.value = config.chatbotName || '';
        siteId.value = config.siteId || null;
        botplusUserId.value = config.botplusUserId || null;
        botplusUserEmail.value = config.botplusUserEmail || '';
        connectedAt.value = config.connectedAt || '';
        manageUrl.value = config.manageUrl || '';
        botplusAppUrl.value = config.botplusAppUrl || '';
        connectUrl.value = config.connectUrl || (botplusAppUrl.value ? `${botplusAppUrl.value.replace(/\/+$/, '')}/connect/wordpress` : '');
        widgetSettings.value = config.widgetSettings || {};
        lastSyncedAt.value = config.lastSyncedAt || '';
        lastSyncStats.value = config.lastSyncStats || null;
    }

    async function fetchDashboardOverview(period = null, forceRefresh = false) {
        if (!connected.value) return;

        const targetPeriod = period || dashboardPeriod.value;
        dashboardPeriod.value = targetPeriod;
        dashboardLoading.value = true;
        dashboardError.value = null;

        try {
            const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
            const nonce = window.BotPlusAdminConfig?.nonce || '';

            const params = new URLSearchParams({
                action: 'botplus_get_dashboard_overview',
                nonce: nonce,
                period: targetPeriod,
            });

            if (forceRefresh) {
                params.append('refresh', '1');
            }

            const response = await fetch(`${ajaxUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (data?.success && data?.data?.overview) {
                dashboardOverview.value = data.data.overview;
                dashboardCached.value = !!data.data.cached;
            } else {
                dashboardError.value = data?.data?.message || 'Failed to load dashboard metrics.';
            }
        } catch (err) {
            dashboardError.value = err.message || 'Network error fetching dashboard.';
        } finally {
            dashboardLoading.value = false;
        }
    }

    return {
        // State
        connected,
        chatbotId,
        chatbotName,
        siteId,
        botplusUserId,
        botplusUserEmail,
        connectedAt,
        manageUrl,
        botplusAppUrl,
        connectUrl,
        widgetSettings,
        connecting,
        disconnecting,
        syncing,
        savingSettings,
        lastSyncedAt,
        lastSyncStats,
        error,
        success,
        dashboardOverview,
        dashboardLoading,
        dashboardPeriod,
        dashboardError,
        dashboardCached,
        // Getters
        isLoading,
        hasError,
        hasSuccess,
        // Actions
        setConnected,
        setDisconnected,
        setWidgetSettings,
        setError,
        setSuccess,
        clearMessages,
        setConnecting,
        setDisconnecting,
        setSyncing,
        setSyncStats,
        setSavingSettings,
        initializeFromConfig,
        fetchDashboardOverview,
    };
});