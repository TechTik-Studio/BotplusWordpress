<script setup>
import { ref } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useApi } from '@/composables/useApi';

const store = useAdminStore();
const { postForm } = useApi();

const i18n = wp.i18n;

const savingSettings = ref(false);
const error = ref(null);
const success = ref(null);

async function saveWidgetSettings() {
    savingSettings.value = true;
    error.value = null;
    success.value = null;
    
    try {
        const formData = new URLSearchParams({
            action: 'botplus_save_widget_settings',
            settings: JSON.stringify(store.widgetSettings),
        });
        
        await postForm('', formData);
        
        success.value = i18n.__('Widget settings saved!', 'botplus');
    } catch (err) {
        error.value = i18n.__('Save error: ', 'botplus') + err.message;
    } finally {
        savingSettings.value = false;
    }
}
</script>

<template>
    <div class="botplus-card">
        <h2>{{ i18n.__('Widget Settings', 'botplus') }}</h2>
        
        <div class="botplus-form-group">
            <label>{{ i18n.__('Theme', 'botplus') }}</label>
            <select v-model="store.widgetSettings.theme" class="botplus-input">
                <option value="glass">{{ i18n.__('Glass', 'botplus') }}</option>
                <option value="timeless">{{ i18n.__('Timeless', 'botplus') }}</option>
                <option value="messages">{{ i18n.__('Messages', 'botplus') }}</option>
                <option value="foundation">{{ i18n.__('Foundation', 'botplus') }}</option>
            </select>
        </div>
        
        <div class="botplus-form-group">
            <label>{{ i18n.__('Position', 'botplus') }}</label>
            <select v-model="store.widgetSettings.position" class="botplus-input">
                <option value="bottom-right">{{ i18n.__('Bottom Right', 'botplus') }}</option>
                <option value="bottom-left">{{ i18n.__('Bottom Left', 'botplus') }}</option>
            </select>
        </div>
        
        <div class="botplus-form-group">
            <label>{{ i18n.__('Primary Color', 'botplus') }}</label>
            <input type="color" v-model="store.widgetSettings.primary_color" class="botplus-input botplus-color-input" />
        </div>
        
        <div class="botplus-form-group">
            <label>{{ i18n.__('Welcome Message', 'botplus') }}</label>
            <textarea v-model="store.widgetSettings.welcome_message" class="botplus-input" rows="3"></textarea>
        </div>
        
        <div class="botplus-form-group">
            <label>{{ i18n.__('Offline Message', 'botplus') }}</label>
            <textarea v-model="store.widgetSettings.offline_message" class="botplus-input" rows="3"></textarea>
        </div>
        
        <button class="botplus-btn botplus-btn-primary" @click="saveWidgetSettings" :disabled="savingSettings">
            <span v-if="!savingSettings">{{ i18n.__('Save Widget Settings', 'botplus') }}</span>
            <span v-else>{{ i18n.__('Saving...', 'botplus') }}</span>
        </button>
        
        <div v-if="error" class="botplus-alert botplus-alert-error">
            {{ error }}
        </div>
        <div v-if="success" class="botplus-alert botplus-alert-success">
            {{ success }}
        </div>
    </div>
</template>

<style scoped>
.botplus-form-group {
    margin-bottom: 20px;
}

.botplus-form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
}

.botplus-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.botplus-input:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.botplus-color-input {
    height: 44px;
    padding: 4px;
    cursor: pointer;
}

.botplus-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.botplus-btn-primary {
    background: #3B82F6;
    color: white;
}

.botplus-btn-primary:hover:not(:disabled) {
    background: #2563eb;
}

.botplus-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.botplus-alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 16px;
    font-size: 14px;
}

.botplus-alert-error {
    background: #fef2f2;
    border: 1px solid #ef4444;
    color: #991b1b;
}

.botplus-alert-success {
    background: #f0fdf4;
    border: 1px solid #10b981;
    color: #065f46;
}
</style>
