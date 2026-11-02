import { ref } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useApi } from './useApi';

const connecting = ref(false);
const error = ref(null);
const success = ref(null);

export function useOAuth() {
    const store = useAdminStore();
    const { request, postForm, loading, error: apiError } = useApi();

    function resolveConnectUrl(config = {}) {
        if (config.connectUrl) {
            return config.connectUrl;
        }
        if (store.connectUrl) {
            return store.connectUrl;
        }
        const base = (
            config.botplusAppUrl ||
            store.botplusAppUrl ||
            window.BotPlusAdminConfig?.botplusAppUrl ||
            window.BotPlusAdminInitialState?.botplusAppUrl ||
            ''
        ).replace(/\/+$/, '');

        return base ? `${base}/connect/wordpress` : '/connect/wordpress';
    }

    const connectUrl = resolveConnectUrl(window.BotPlusAdminConfig || window.BotPlusAdminInitialState || {});

    function initiateConnect() {
        const config = window.BotPlusAdminConfig || window.BotPlusAdminInitialState || {};
        const stateParam = config.oauthState || '';
        const connectEndpoint = resolveConnectUrl(config);

        const redirectUri = config.redirectUri || (window.location.origin + '/wp-admin/admin.php?page=botplus-billing');
        const caps = config.siteCapabilities || {};

        const params = new URLSearchParams({
            client_id: 'wp_botplus_v1',
            redirect_uri: redirectUri,
            site_url: config.siteUrl || window.location.origin,
            site_name: config.siteName || document.title.split('‹')[0].trim() || 'WordPress Site',
            response_type: 'code',
            scope: 'sites:write chat:write',
            state: stateParam,
            is_woocommerce: caps.is_woocommerce ? '1' : '0',
            site_type: caps.site_type || (caps.is_woocommerce ? 'woocommerce_store' : 'wordpress_content'),
            currency: caps.currency || '',
            currency_symbol: caps.currency_symbol || '',
            products_count: String(caps.counts?.products || 0),
            pages_count: String(caps.counts?.pages || 0),
            posts_count: String(caps.counts?.posts || 0),
        });

        window.location.href = `${connectEndpoint}?${params.toString()}`;
    }

    async function exchangeCodeForToken(code) {
        connecting.value = true;
        error.value = null;
        success.value = null;

        try {
            const formData = new URLSearchParams({
                action: 'botplus_exchange_code',
                code: code,
            });

            const response = await postForm('', formData);

            if (response.success && response.data) {
                store.setConnected(response.data);
                if (response.data.sync_stats) {
                    store.setSyncStats(response.data.sync_stats, response.data.synced_at || new Date().toISOString());
                }
                success.value = 'Connected successfully!';
            } else {
                error.value = response.data?.message || 'Connection failed';
            }
        } catch (err) {
            error.value = 'Connection error: ' + err.message;
        } finally {
            connecting.value = false;
        }
    }

    async function saveConnection(data) {
        const formData = new URLSearchParams({
            action: 'botplus_save_connection',
            jwt_token: data.jwt_token,
            site_id: data.site_id,
            chatbot_id: data.chatbot_id,
            botplus_user_id: data.botplus_user_id,
            botplus_user_email: data.botplus_user_email,
        });

        await postForm('', formData);
    }

    async function disconnect() {
        if (!confirm('Disconnect from BotPlus? This will remove the connection but keep your widget settings.')) {
            return;
        }

        const store = useAdminStore();
        store.setDisconnecting(true);
        error.value = null;
        success.value = null;

        try {
            await postForm('', new URLSearchParams({
                action: 'botplus_disconnect',
            }));

            store.setDisconnected();
            success.value = 'Disconnected successfully';
        } catch (err) {
            error.value = 'Disconnect error: ' + err.message;
        } finally {
            store.setDisconnecting(false);
        }
    }

    function checkUrlForAuthCode() {
        const urlParams = new URLSearchParams(window.location.search);
        const isConnectedParam = urlParams.get('botplus_connected');
        const code = urlParams.get('botplus_code') || urlParams.get('code');
        const errorParam = urlParams.get('botplus_error') || urlParams.get('error_description') || urlParams.get('error');

        function cleanAddressBar() {
            try {
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('botplus_code');
                cleanUrl.searchParams.delete('botplus_error');
                cleanUrl.searchParams.delete('botplus_connected');
                cleanUrl.searchParams.delete('code');
                cleanUrl.searchParams.delete('state');
                cleanUrl.searchParams.delete('error');
                cleanUrl.searchParams.delete('error_description');
                window.history.replaceState({}, document.title, cleanUrl.toString());
            } catch (e) {
                // Fallback
            }
        }

        if (errorParam) {
            try {
                error.value = decodeURIComponent(errorParam.replace(/\+/g, ' '));
            } catch (e) {
                error.value = errorParam;
            }
            cleanAddressBar();
            return;
        }

        if (code) {
            cleanAddressBar();
            exchangeCodeForToken(code);
            return;
        }

        if (isConnectedParam) {
            success.value = 'Connected successfully!';
            cleanAddressBar();
            return;
        }
    }

    async function syncKnowledge() {
        const store = useAdminStore();
        store.setSyncing(true);
        error.value = null;
        success.value = null;

        try {
            const formData = new URLSearchParams({
                action: 'botplus_sync_knowledge',
            });
            const response = await postForm('', formData);

            if (response.success && response.data) {
                store.setSyncStats(response.data.stats, response.data.synced_at);
                success.value = response.data.message || 'Knowledge synced successfully with BotPlus!';
                return response.data;
            } else {
                error.value = response.data?.message || 'Knowledge sync failed.';
                return null;
            }
        } catch (err) {
            error.value = 'Sync error: ' + err.message;
            return null;
        } finally {
            store.setSyncing(false);
        }
    }

    return {
        connecting,
        error,
        success,
        initiateConnect,
        exchangeCodeForToken,
        syncKnowledge,
        disconnect,
        checkUrlForAuthCode,
    };
}