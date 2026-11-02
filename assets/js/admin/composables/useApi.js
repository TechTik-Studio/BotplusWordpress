import { ref } from 'vue';

export function useApi() {
    const loading = ref(false);
    const error = ref(null);

    async function request(endpoint, options = {}) {
        loading.value = true;
        error.value = null;

        const config = window.BotPlusAdminConfig;
        if (!config) {
            throw new Error('BotPlusAdminConfig not available');
        }

        const defaultHeaders = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce,
        };

        const response = await fetch(`${config.apiUrl}${endpoint}`, {
            ...options,
            headers: {
                ...defaultHeaders,
                ...options.headers,
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }

        loading.value = false;
        return data;
    }

    async function postForm(endpoint, formData) {
        loading.value = true;
        error.value = null;

        const config = window.BotPlusAdminConfig;
        if (!config) {
            throw new Error('BotPlusAdminConfig not available');
        }

        // Ensure nonce is included in form body for check_ajax_referer
        if (formData instanceof URLSearchParams && !formData.has('nonce')) {
            formData.append('nonce', config.nonce);
        } else if (formData instanceof FormData && !formData.has('nonce')) {
            formData.append('nonce', config.nonce);
        }

        const response = await fetch(`${config.ajaxUrl}`, {
            method: 'POST',
            body: formData,
        });

        const data = await response.json();

        if (!response.ok || (data && data.success === false)) {
            const message = data?.data?.message || data?.message || 'Request failed';
            throw new Error(message);
        }

        loading.value = false;
        return data;
    }

    return {
        loading,
        error,
        request,
        postForm,
    };
}