<script setup>
import { ref, computed, onMounted } from 'vue';
import BotPlusSkeletonBlock from '@/components/skeletons/BotPlusSkeletonBlock.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';

// Loading & UI States
const loading = ref(true);
const saving = ref(false);
const saveSuccess = ref(false);
const isUploadingAvatar = ref(false);
const avatarUploadError = ref('');
const errorMessage = ref('');
const previewMode = ref('chat'); // 'chat' or 'store'
const fileInputRef = ref(null);

// Interactive Live Preview State
const previewMessages = ref([
    { id: 1, sender: 'bot', text: null, isGreeting: true },
    {
        id: 2,
        sender: 'bot',
        isProduct: true,
        product: {
            title: 'Heritage Leather Weekender',
            price: '$129.00',
            rating: '4.9 (128 reviews)',
            inStock: true
        }
    }
]);
const previewInput = ref('');
const addedToCart = ref(false);

function handlePreviewSend(text) {
    const q = (text || previewInput.value || '').trim();
    if (!q) return;
    previewMessages.value.push({ id: Date.now(), sender: 'user', text: q });
    previewInput.value = '';

    setTimeout(() => {
        let reply = currentTone.value.sample;
        const lower = q.toLowerCase();
        if (lower.includes('track') || lower.includes('order')) {
            reply = 'Please share your 4-digit order number (e.g., #1042) or email address, and I will instantly look up the real-time shipping status for you.';
        } else if (lower.includes('bestseller') || lower.includes('recommend') || lower.includes('product')) {
            reply = 'Our top bestseller is the Heritage Leather Weekender ($129.00). Customers love its handcrafted finish and reinforced water-resistant lining.';
        } else if (lower.includes('return') || lower.includes('policy')) {
            reply = 'We offer a hassle-free 30-day return policy on all items with original tags. Return shipping is completely covered!';
        }
        previewMessages.value.push({ id: Date.now() + 1, sender: 'bot', text: reply });
    }, 350);
}

function handlePreviewAddToCart() {
    addedToCart.value = true;
    setTimeout(() => { addedToCart.value = false; }, 2500);
}

function resetPreview() {
    previewMessages.value = [
        { id: 1, sender: 'bot', text: null, isGreeting: true },
        {
            id: 2,
            sender: 'bot',
            isProduct: true,
            product: {
                title: 'Heritage Leather Weekender',
                price: '$129.00',
                rating: '4.9 (128 reviews)',
                inStock: true
            }
        }
    ];
}

// Form Fields
const displayName = ref('MAR Traders Store Agent');
const botAvatarUrl = ref('');
const selectedTone = ref('friendly');
const customTonePrompt = ref('');
const welcomeGreeting = ref('Assalam-o-Alaikum! Welcome to MAR Traders. How can I help you find products or track your order today?');
const teaserText = ref('Have questions? Ask our agent!');
const primaryColor = ref('#2563eb');
const buttonColor = ref('#2563eb');
const fontFamily = ref('Inter');
const widgetPosition = ref('right');

// Tone Presets (Synchronized with WordpressTonePromptService)
const tonePresets = [
    {
        id: 'friendly',
        label: 'Friendly & Helpful',
        desc: 'Warm, approachable, and proactive for general retail shopping and product recommendations.',
        sample: 'Absolutely! I can help you pick the right option and answer any questions along the way.',
        defaultGreeting: 'Hi! Welcome to our store. How can I help you find the right product or answer your questions today?',
        defaultWidgetText: "Questions? We're here to help."
    },
    {
        id: 'professional',
        label: 'Professional & Concise',
        desc: 'Clear, polished, and authoritative for high-volume commercial and business buyers.',
        sample: 'Certainly. I can guide you through product specifications, order updates, and bulk pricing.',
        defaultGreeting: 'Welcome. I can assist with product specifications, order status updates, and policies.',
        defaultWidgetText: 'Product or order question?'
    },
    {
        id: 'luxury',
        label: 'Luxury Concierge',
        desc: 'Refined, attentive concierge assistance for premium collections and personalized shopping.',
        sample: 'A splendid choice. Allow me to assist you with every specification and bespoke detail.',
        defaultGreeting: 'Welcome. May I assist you with product recommendations or exclusive orders today?',
        defaultWidgetText: 'Ask our store concierge'
    },
    {
        id: 'casual',
        label: 'Casual & Conversational',
        desc: 'Relaxed, conversational, and quick to the point like a knowledgeable teammate.',
        sample: 'Hey there! Looking for something specific in our catalog or need a hand checking an order?',
        defaultGreeting: 'Hey! Welcome to our store. Looking for something specific or need help with an order?',
        defaultWidgetText: 'Ask us anything'
    },
    {
        id: 'premium',
        label: 'Technical & Product Expert',
        desc: 'Confident product expertise highlighting craftsmanship, durability, and technical fit.',
        sample: 'I can help you evaluate technical specifications, durability, and identify the exact fit for your setup.',
        defaultGreeting: 'Welcome. How may I help you explore our technical catalog and equipment today?',
        defaultWidgetText: 'Get product guidance'
    },
    {
        id: 'custom',
        label: 'Custom Brand Voice',
        desc: 'Shape the agent around your bespoke tone instructions and brand guidelines.',
        sample: 'I will strictly follow the custom persona and instructions you set for this agent.',
        defaultGreeting: 'Hi! How can I assist you with our store today?',
        defaultWidgetText: "Questions? We're here to help."
    }
];

const currentTone = computed(() => {
    return tonePresets.find(t => t.id === selectedTone.value) || tonePresets[0];
});

const previewAvatarInitial = computed(() => {
    return (displayName.value || 'B').trim().charAt(0).toUpperCase();
});

function handleToneSelect(toneId) {
    selectedTone.value = toneId;
    const preset = tonePresets.find(t => t.id === toneId);
    if (!preset) return;

    if (toneId !== 'custom') {
        if (!welcomeGreeting.value || tonePresets.some(t => t.defaultGreeting === welcomeGreeting.value)) {
            welcomeGreeting.value = preset.defaultGreeting;
        }
        if (!teaserText.value || tonePresets.some(t => t.defaultWidgetText === teaserText.value)) {
            teaserText.value = preset.defaultWidgetText;
        }
    }
}

// 1. Direct Computer File Upload
function triggerComputerUpload() {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
}

async function handleFileUpload(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        avatarUploadError.value = 'Avatar image must be under 2MB.';
        event.target.value = '';
        return;
    }

    isUploadingAvatar.value = true;
    avatarUploadError.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const res = await fetch(`${ajaxUrl}?action=botplus_upload_avatar&nonce=${encodeURIComponent(nonce)}`, {
            method: 'POST',
            body: formData,
        });

        const json = await res.json();
        if (json.success && json.data?.url) {
            botAvatarUrl.value = json.data.url;
        } else {
            avatarUploadError.value = json.data?.message || 'Failed to upload image. Please try again.';
        }
    } catch (e) {
        avatarUploadError.value = 'Upload error: ' + e.message;
    } finally {
        isUploadingAvatar.value = false;
        event.target.value = '';
    }
}

// 2. WordPress Native Media Library Integration
function openMediaLibrary() {
    if (window.wp && window.wp.media) {
        const frame = window.wp.media({
            title: 'Select or Upload Agent Avatar',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            if (attachment && attachment.url) {
                botAvatarUrl.value = attachment.url;
            }
        });

        frame.open();
    } else {
        const url = prompt('Enter the direct image URL for your agent avatar:', botAvatarUrl.value || '');
        if (url !== null) {
            botAvatarUrl.value = url.trim();
        }
    }
}

function clearAvatar() {
    botAvatarUrl.value = '';
    avatarUploadError.value = '';
}

// Fetch Settings from WordPress AJAX
async function fetchSettings() {
    loading.value = true;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(`${ajaxUrl}?action=botplus_get_agent_settings&nonce=${encodeURIComponent(nonce)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (json.success && json.data) {
            const data = json.data;
            const chatbot = data.chatbot || {};
            const s = chatbot.settings || {};

            if (s.display_name) displayName.value = s.display_name;
            else if (chatbot.name) displayName.value = chatbot.name;

            if (s.bot_avatar_url) botAvatarUrl.value = s.bot_avatar_url;
            if (s.tone_style) selectedTone.value = s.tone_style;
            if (s.custom_tone_prompt) customTonePrompt.value = s.custom_tone_prompt;
            if (s.first_message) welcomeGreeting.value = s.first_message;
            if (s.widget_text) teaserText.value = s.widget_text;
            if (s.primary_color) primaryColor.value = s.primary_color;
            if (s.button_color) buttonColor.value = s.button_color;
            else if (s.primary_color) buttonColor.value = s.primary_color;
            if (s.font_family) fontFamily.value = s.font_family;
            if (s.widget_position) widgetPosition.value = s.widget_position;
        } else if (json.data?.message) {
            errorMessage.value = json.data.message;
        }
    } catch (e) {
        console.warn('Error fetching agent settings from backend:', e);
    } finally {
        loading.value = false;
    }
}

// Save Settings to WordPress AJAX
async function saveSettings() {
    saving.value = true;
    saveSuccess.value = false;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    const payload = {
        tone_style: selectedTone.value,
        custom_tone_prompt: selectedTone.value === 'custom' ? customTonePrompt.value : '',
        first_message: welcomeGreeting.value,
        widget_text: teaserText.value,
        display_name: displayName.value,
        bot_avatar_url: botAvatarUrl.value,
        primary_color: primaryColor.value,
        button_color: buttonColor.value || primaryColor.value,
        font_family: fontFamily.value,
        widget_position: widgetPosition.value,
    };

    try {
        const res = await fetch(`${ajaxUrl}?action=botplus_save_agent_settings&nonce=${encodeURIComponent(nonce)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (json.success) {
            saveSuccess.value = true;
            setTimeout(() => {
                saveSuccess.value = false;
            }, 3500);
        } else {
            errorMessage.value = json.data?.message || 'Failed to save settings. Please try again.';
        }
    } catch (e) {
        errorMessage.value = 'Network error while saving settings: ' + e.message;
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    fetchSettings();
});
</script>

<template>
    <div class="botplus-page-agent-unified">
        <!-- Top Section Header -->
        <div class="botplus-section-header">
            <div>
                <h2 class="botplus-section-title">Agent Settings & Display</h2>
                <p class="botplus-section-desc">Shape how your AI agent speaks with customers, customize greetings, and configure your live storefront widget appearance.</p>
            </div>

            <div class="botplus-header-actions">
                <span v-if="saveSuccess" class="botplus-save-toast">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Settings saved successfully!
                </span>

                <button 
                    @click="saveSettings" 
                    class="botplus-btn botplus-btn-primary"
                    :disabled="saving || loading"
                >
                    <svg v-if="saving" class="botplus-spin-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-linecap="round"></circle>
                    </svg>
                    <span>{{ saving ? 'Saving Changes...' : 'Save Changes' }}</span>
                </button>
            </div>
        </div>

        <!-- Global Error Notice -->
        <div v-if="errorMessage" class="botplus-error-banner">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading" class="botplus-two-col-layout" style="margin-top: 10px;">
            <div class="botplus-settings-column">
                <div v-for="i in 2" :key="i" class="botplus-card botplus-mb-20" style="display: flex; flex-direction: column; gap: 16px; padding: 24px;">
                    <BotPlusSkeletonBlock width="40%" height="24px" radius="6px" />
                    <BotPlusSkeletonBlock width="80%" height="16px" radius="4px" />
                    <BotPlusSkeletonBlock width="100%" height="42px" radius="6px" style="margin-top: 8px;" />
                    <BotPlusSkeletonBlock width="100%" height="80px" radius="6px" />
                </div>
            </div>
            <div class="botplus-preview-column">
                <div class="botplus-card" style="display: flex; flex-direction: column; gap: 16px; padding: 24px; min-height: 480px;">
                    <BotPlusSkeletonBlock width="50%" height="24px" radius="6px" />
                    <BotPlusSkeletonBlock width="100%" height="380px" radius="12px" />
                </div>
            </div>
        </div>

        <div v-else-if="errorMessage && !displayName" style="margin-top: 20px;">
            <BotPlusInlineRetry :message="errorMessage" :busy="loading" @retry="fetchSettings" />
        </div>

        <!-- Main 2-Column Responsive Layout -->
        <div v-else class="botplus-two-col-layout">
            <!-- Left Column: 3 Clean, Focused Configuration Cards -->
            <div class="botplus-settings-column">
                <!-- 1. Agent Identity & Voice Card -->
                <div class="botplus-card botplus-mb-20">
                    <div class="botplus-card-header">
                        <div class="botplus-card-badge-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="7" r="4"></circle>
                                <path d="M5.5 21v-2a6.5 6.5 0 0 1 13 0v2"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="botplus-card-title">Agent Identity & Voice</h3>
                            <p class="botplus-card-subtitle">Define who your agent is, its avatar, and the conversational tone it assumes with customers.</p>
                        </div>
                    </div>

                    <!-- Display Name -->
                    <div class="botplus-form-group">
                        <label class="botplus-label">Agent Display Name</label>
                        <input 
                            v-model="displayName" 
                            type="text" 
                            class="botplus-input" 
                            placeholder="e.g. Store Agent"
                        />
                        <span class="botplus-help-text">Displayed prominently in the storefront chat header and launcher tooltip.</span>
                    </div>

                    <!-- 3-Way Avatar Image Section -->
                    <div class="botplus-form-group">
                        <label class="botplus-label">Bot Avatar Image</label>
                        <div class="botplus-avatar-picker-row">
                            <!-- Avatar Preview Circle -->
                            <div class="botplus-avatar-circle-preview" :style="{ backgroundColor: primaryColor }">
                                <img v-if="botAvatarUrl" :src="botAvatarUrl" alt="Avatar Preview" class="botplus-avatar-img" />
                                <span v-else class="botplus-avatar-initial">{{ previewAvatarInitial }}</span>
                            </div>

                            <div class="botplus-avatar-controls">
                                <!-- Hidden Native Computer File Input -->
                                <input 
                                    ref="fileInputRef" 
                                    type="file" 
                                    accept="image/*" 
                                    class="botplus-hidden-input" 
                                    @change="handleFileUpload" 
                                    :disabled="isUploadingAvatar" 
                                />

                                <div class="botplus-avatar-buttons-row">
                                    <!-- Button 1: Upload from Computer -->
                                    <button 
                                        type="button" 
                                        @click="triggerComputerUpload" 
                                        class="botplus-btn botplus-btn-secondary botplus-btn-sm"
                                        :disabled="isUploadingAvatar"
                                    >
                                        <svg v-if="!isUploadingAvatar" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                        <svg v-else class="botplus-spin-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-linecap="round"></circle>
                                        </svg>
                                        <span>{{ isUploadingAvatar ? 'Uploading...' : 'Upload from Computer' }}</span>
                                    </button>

                                    <!-- Button 2: Choose from Media Library -->
                                    <button 
                                        type="button" 
                                        @click="openMediaLibrary" 
                                        class="botplus-btn botplus-btn-secondary botplus-btn-sm"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <polyline points="21 15 16 10 5 21"></polyline>
                                        </svg>
                                        <span>Media Library</span>
                                    </button>

                                    <!-- Remove Avatar Link -->
                                    <button v-if="botAvatarUrl" type="button" @click="clearAvatar" class="botplus-btn-link-danger">
                                        Remove avatar
                                    </button>
                                </div>

                                <!-- Error Alert if Upload Fails -->
                                <div v-if="avatarUploadError" class="botplus-avatar-error">
                                    {{ avatarUploadError }}
                                </div>

                                <!-- Direct URL Input Fallback -->
                                <input 
                                    v-model="botAvatarUrl" 
                                    type="text" 
                                    class="botplus-input botplus-input-sm" 
                                    placeholder="Or paste direct image URL (https://...)"
                                    style="margin-top: 8px;"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Tone of Voice Grid Cards -->
                    <div class="botplus-form-group">
                        <label class="botplus-label">Tone of Voice</label>
                        <div class="botplus-tone-grid">
                            <div 
                                v-for="t in tonePresets" 
                                :key="t.id"
                                class="botplus-tone-card"
                                :class="{ 'is-selected': selectedTone === t.id }"
                                @click="handleToneSelect(t.id)"
                            >
                                <div class="botplus-tone-header">
                                    <span class="botplus-tone-title">{{ t.label }}</span>
                                    <span v-if="selectedTone === t.id" class="botplus-check-circle">&#10003;</span>
                                </div>
                                <p class="botplus-tone-desc">{{ t.desc }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Tone Instructions Textarea -->
                    <div v-if="selectedTone === 'custom'" class="botplus-form-group botplus-custom-prompt-box">
                        <label class="botplus-label">Custom Brand Voice Instructions</label>
                        <textarea 
                            v-model="customTonePrompt" 
                            rows="3" 
                            class="botplus-textarea" 
                            placeholder="Describe how the agent should speak, guide customers, recommend products, and answer policy inquiries..."
                        ></textarea>
                        <span class="botplus-help-text">Directly guides your agent's persona while retaining all built-in store knowledge and tool capabilities.</span>
                    </div>
                </div>

                <!-- 2. Customer-Facing Messages Card -->
                <div class="botplus-card botplus-mb-20">
                    <div class="botplus-card-header">
                        <div class="botplus-card-badge-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="botplus-card-title">Customer-Facing Messages</h3>
                            <p class="botplus-card-subtitle">Configure the opening greeting and floating launcher teasers customers see on your storefront.</p>
                        </div>
                    </div>

                    <!-- Welcome Greeting -->
                    <div class="botplus-form-group">
                        <label class="botplus-label">Storefront Welcome Greeting</label>
                        <textarea 
                            v-model="welcomeGreeting" 
                            rows="3" 
                            class="botplus-textarea"
                            placeholder="Welcome message sent when customers open chat"
                        ></textarea>
                        <span class="botplus-help-text">The initial greeting bubble delivered to customers as soon as they open the chat window.</span>
                    </div>

                    <!-- Launcher Teaser Text -->
                    <div class="botplus-form-group" style="margin-bottom: 0;">
                        <label class="botplus-label">Floating Launcher Teaser Message</label>
                        <input 
                            v-model="teaserText" 
                            type="text" 
                            class="botplus-input" 
                            placeholder="e.g. Questions? We're here to help."
                        />
                        <span class="botplus-help-text">Floating greeting pill displayed adjacent to the widget icon to invite customer engagement.</span>
                    </div>
                </div>

                <!-- 3. Storefront Widget Appearance & Visual Styling Card -->
                <div class="botplus-card">
                    <div class="botplus-card-header">
                        <div class="botplus-card-badge-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                                <path d="M2 12h20"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="botplus-card-title">Storefront Widget & Visual Styling</h3>
                            <p class="botplus-card-subtitle">Control widget screen position, dual brand theme colors, and typography.</p>
                        </div>
                    </div>

                    <!-- Screen Position -->
                    <div class="botplus-form-group">
                        <label class="botplus-label">Screen Position</label>
                        <div class="botplus-radio-cards">
                            <label class="botplus-radio-card" :class="{ 'is-selected': widgetPosition === 'right' }">
                                <input type="radio" value="right" v-model="widgetPosition" />
                                <div>
                                    <strong>Bottom Right (Recommended)</strong>
                                    <p>Standard ecommerce position; aligns naturally with shopping cart drawers.</p>
                                </div>
                            </label>

                            <label class="botplus-radio-card" :class="{ 'is-selected': widgetPosition === 'left' }">
                                <input type="radio" value="left" v-model="widgetPosition" />
                                <div>
                                    <strong>Bottom Left</strong>
                                    <p>Alternative if other third-party review badges or return widgets occupy bottom-right.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Dual Color Architecture: Primary Color & Button Color -->
                    <div class="botplus-form-group">
                        <div class="botplus-two-colors-grid">
                            <!-- Color 1: Primary Color -->
                            <div class="botplus-color-card">
                                <label class="botplus-label">Primary Color</label>
                                <p class="botplus-color-role-desc">Chat header background & chat accents</p>
                                <div class="botplus-color-picker-row">
                                    <input type="color" v-model="primaryColor" class="botplus-color-swatch" />
                                    <input type="text" v-model="primaryColor" class="botplus-input botplus-color-input" />
                                </div>
                            </div>

                            <!-- Color 2: Button Color -->
                            <div class="botplus-color-card">
                                <label class="botplus-label">Button Color</label>
                                <p class="botplus-color-role-desc">Floating launcher button & send actions</p>
                                <div class="botplus-color-picker-row">
                                    <input type="color" v-model="buttonColor" class="botplus-color-swatch" />
                                    <input type="text" v-model="buttonColor" class="botplus-input botplus-color-input" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Typography Font Family -->
                    <div class="botplus-form-group" style="margin-bottom: 0;">
                        <label class="botplus-label">Font Family</label>
                        <select v-model="fontFamily" class="botplus-input">
                            <option value="Inter">Inter (Clean modern sans-serif)</option>
                            <option value="Roboto">Roboto (Classic geometric)</option>
                            <option value="Open Sans">Open Sans (Neutral & friendly)</option>
                            <option value="Lato">Lato (Warm & corporate)</option>
                            <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif">System Native Font</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sticky Real-Time Dual-Mode Live Preview -->
            <div class="botplus-preview-column">
                <div class="botplus-card botplus-preview-card">
                    <!-- Segmented Mode Control -->
                    <div class="botplus-preview-header">
                        <div>
                            <h3 class="botplus-card-title" style="margin: 0; font-size: 15px;">Live Real-Time Preview</h3>
                            <span class="botplus-card-desc">Interactive simulated storefront AI</span>
                        </div>

                        <div class="botplus-preview-segmented">
                            <button 
                                type="button" 
                                class="botplus-segmented-btn" 
                                :class="{ 'is-active': previewMode === 'chat' }"
                                @click="previewMode = 'chat'"
                            >
                                Chat Window
                            </button>
                            <button 
                                type="button" 
                                class="botplus-segmented-btn" 
                                :class="{ 'is-active': previewMode === 'store' }"
                                @click="previewMode = 'store'"
                            >
                                Storefront View
                            </button>
                        </div>
                    </div>

                    <!-- MODE 1: Chat Window Mockup -->
                    <div v-if="previewMode === 'chat'" class="botplus-widget-mockup" :style="{ fontFamily: fontFamily }">
                        <!-- Chat Header styled dynamically with primaryColor -->
                        <div class="botplus-mockup-header" :style="{ backgroundColor: primaryColor }">
                            <div class="botplus-mockup-avatar-wrap">
                                <img v-if="botAvatarUrl" :src="botAvatarUrl" class="botplus-mockup-avatar-img" />
                                <div v-else class="botplus-mockup-avatar-initial">{{ previewAvatarInitial }}</div>
                            </div>
                            <div class="botplus-mockup-header-text">
                                <div class="botplus-mockup-title">{{ displayName || 'Store Agent' }}</div>
                                <div class="botplus-mockup-status">
                                    <span class="bp-status-dot is-success" style="display:inline-block; margin-right:4px;"></span>Online &bull; Instant Storefront AI
                                </div>
                            </div>
                            <button type="button" @click="resetPreview" title="Reset preview chat" class="botplus-mockup-reset-btn">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                    <path d="M3 3v5h5"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Chat Body with Interactive Stream -->
                        <div class="botplus-mockup-body">
                            <template v-for="msg in previewMessages" :key="msg.id">
                                <!-- Welcome Greeting Bubble -->
                                <div v-if="msg.isGreeting" class="botplus-mockup-bubble bot">
                                    {{ welcomeGreeting || 'Hi! How can I help you today?' }}
                                </div>

                                <!-- Standard User / Bot text bubble -->
                                <div 
                                    v-else-if="msg.text" 
                                    :class="['botplus-mockup-bubble', msg.sender]" 
                                    :style="msg.sender === 'user' ? { backgroundColor: buttonColor || primaryColor, color: '#ffffff' } : {}"
                                >
                                    {{ msg.text }}
                                </div>

                                <!-- Simulated WooCommerce Product Recommendation Card -->
                                <div v-else-if="msg.isProduct" class="botplus-mockup-product-card">
                                    <div class="botplus-product-img-box">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.75">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                            <line x1="3" y1="6" x2="21" y2="6"></line>
                                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                                        </svg>
                                    </div>
                                    <div class="botplus-product-info">
                                        <div class="botplus-product-title">{{ msg.product.title }}</div>
                                        <div class="botplus-product-meta">
                                            <span class="botplus-product-price">{{ msg.product.price }}</span>
                                            <span class="botplus-product-rating">&starf; {{ msg.product.rating }}</span>
                                        </div>
                                        <button 
                                            type="button" 
                                            class="botplus-product-btn" 
                                            :style="{ backgroundColor: buttonColor || primaryColor }"
                                            @click="handlePreviewAddToCart"
                                        >
                                            <svg v-if="addedToCart" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            <span>{{ addedToCart ? 'Added to Cart!' : 'Add to Cart' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Interactive Quick Prompts -->
                            <div class="botplus-mockup-quick-replies">
                                <button type="button" class="botplus-mockup-chip" @click="handlePreviewSend('Track my order')">
                                    📦 Track Order
                                </button>
                                <button type="button" class="botplus-mockup-chip" @click="handlePreviewSend('Recommend top products')">
                                    🔥 Bestsellers
                                </button>
                                <button type="button" class="botplus-mockup-chip" @click="handlePreviewSend('What is your return policy?')">
                                    🔄 Return Policy
                                </button>
                            </div>
                        </div>

                        <!-- Chat Composer Footer with interactive send button styled with buttonColor -->
                        <div class="botplus-mockup-footer">
                            <input 
                                type="text" 
                                v-model="previewInput" 
                                placeholder="Type a test question..." 
                                @keydown.enter.prevent="handlePreviewSend()"
                            />
                            <button 
                                type="button"
                                :style="{ backgroundColor: buttonColor || primaryColor }"
                                @click="handlePreviewSend()"
                                title="Send test message"
                            >
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- MODE 2: Storefront View Mockup -->
                    <div v-else class="botplus-store-stage">
                        <div class="botplus-store-mock-navbar">
                            <div class="botplus-mock-logo">STOREFRONT</div>
                            <div class="botplus-mock-nav-links">
                                <span>Catalog</span>
                                <span>Featured</span>
                                <span>Cart ({{ addedToCart ? 2 : 1 }})</span>
                            </div>
                        </div>

                        <div class="botplus-store-mock-content">
                            <div class="botplus-mock-hero">
                                <div class="botplus-mock-badge" :style="{ color: primaryColor, backgroundColor: primaryColor + '18' }">BESTSELLER</div>
                                <h4>Heritage Leather Weekender</h4>
                                <p>$129.00 &bull; In Stock &bull; Free Express Shipping</p>
                            </div>
                        </div>

                        <!-- Floating Widget Element Anchored Bottom Right or Left using buttonColor -->
                        <div 
                            class="botplus-floating-widget-container" 
                            :class="widgetPosition === 'left' ? 'bottom_left' : 'bottom_right'"
                        >
                            <div class="botplus-mock-teaser-pill" :style="{ borderColor: buttonColor || primaryColor }">
                                {{ teaserText || "Questions? We're here to help." }}
                            </div>
                            <button 
                                class="botplus-mock-floating-btn" 
                                :style="{ backgroundColor: buttonColor || primaryColor }"
                                @click="previewMode = 'chat'"
                                title="Open chat preview"
                            >
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-page-agent-unified {
    width: 100%;
}

.botplus-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.botplus-section-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-section-desc {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

.botplus-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.botplus-save-toast {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #047857;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.botplus-error-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 18px;
    font-size: 13px;
}

.botplus-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    color: #64748b;
    gap: 12px;
}

.botplus-loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.botplus-two-col-layout {
    display: grid;
    grid-template-columns: 1.25fr 1fr;
    gap: 22px;
    align-items: start;
}

@media (max-width: 960px) {
    .botplus-two-col-layout {
        grid-template-columns: 1fr;
    }
}

.botplus-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.botplus-mb-20 {
    margin-bottom: 20px;
}

.botplus-card-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 18px;
}

.botplus-card-badge-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.botplus-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-card-subtitle {
    margin: 2px 0 0 0;
    font-size: 12px;
    color: #64748b;
}

.botplus-form-group {
    margin-bottom: 16px;
}

.botplus-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.botplus-help-text {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}

.botplus-input, .botplus-textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    color: #0f172a;
    box-sizing: border-box;
    background: #ffffff;
    transition: border-color 0.15s ease;
}

.botplus-input:focus, .botplus-textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.botplus-input-sm {
    padding: 6px 10px;
    font-size: 12px;
}

/* 3-Way Avatar Picker Styles */
.botplus-hidden-input {
    display: none;
}

.botplus-avatar-picker-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.botplus-avatar-circle-preview {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-weight: 700;
    font-size: 20px;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.botplus-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.botplus-avatar-controls {
    flex: 1;
}

.botplus-avatar-buttons-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.botplus-avatar-error {
    color: #dc2626;
    font-size: 11px;
    margin-top: 4px;
}

.botplus-btn-link-danger {
    background: none;
    border: none;
    color: #dc2626;
    font-size: 12px;
    cursor: pointer;
    text-decoration: underline;
    padding: 4px 6px;
}

/* Tone Grid */
.botplus-tone-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px;
}

.botplus-tone-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    background: #f8fafc;
    transition: all 0.15s ease;
}

.botplus-tone-card:hover {
    border-color: #94a3b8;
    background: #ffffff;
}

.botplus-tone-card.is-selected {
    border-color: #2563eb;
    background: #eff6ff;
}

.botplus-tone-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.botplus-tone-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-check-circle {
    color: #2563eb;
    font-weight: 800;
    font-size: 14px;
}

.botplus-tone-desc {
    margin: 0;
    font-size: 11px;
    color: #64748b;
    line-height: 1.4;
}

.botplus-custom-prompt-box {
    margin-top: 12px;
    padding: 12px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
}

/* Radio Cards */
.botplus-radio-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.botplus-radio-card {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    background: #f8fafc;
    font-size: 12px;
}

.botplus-radio-card.is-selected {
    border-color: #2563eb;
    background: #eff6ff;
}

.botplus-radio-card p {
    margin: 2px 0 0 0;
    color: #64748b;
}

/* Dual Color Architecture Styles */
.botplus-two-colors-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.botplus-color-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
}

.botplus-color-role-desc {
    margin: 0 0 8px 0;
    font-size: 11px;
    color: #64748b;
}

.botplus-color-picker-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.botplus-color-swatch {
    width: 38px;
    height: 38px;
    padding: 0;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    background: transparent;
}

.botplus-color-input {
    flex: 1;
}

/* Buttons */
.botplus-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s ease;
}

.botplus-btn-primary {
    background: #2563eb;
    color: #ffffff;
}

.botplus-btn-primary:hover:not(:disabled) {
    background: #1d4ed8;
}

.botplus-btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.botplus-btn-secondary {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #334155;
}

.botplus-btn-secondary:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #94a3b8;
}

.botplus-btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.botplus-spin-icon {
    animation: spin 0.8s linear infinite;
}

/* Sticky Preview Column */
.botplus-preview-column {
    position: sticky;
    top: 32px;
}

.botplus-preview-card {
    background: #ffffff;
    padding: 16px;
}

.botplus-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 8px;
}

.botplus-preview-segmented {
    display: inline-flex;
    background: #f1f5f9;
    border-radius: 6px;
    padding: 3px;
}

.botplus-segmented-btn {
    border: none;
    background: none;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.botplus-segmented-btn.is-active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
}

/* Mockup Chat Window */
.botplus-widget-mockup {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
    background: #ffffff;
}

.botplus-mockup-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    color: #ffffff;
}

.botplus-mockup-avatar-wrap {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255,255,255,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.botplus-mockup-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.botplus-mockup-avatar-initial {
    color: #ffffff;
    font-size: 16px;
}

.botplus-mockup-title {
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
}

.botplus-mockup-status {
    font-size: 11px;
    opacity: 0.9;
}

.botplus-mockup-header-text {
    flex: 1;
}

.botplus-mockup-reset-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    border-radius: 4px;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    cursor: pointer;
    transition: background 0.15s ease;
}

.botplus-mockup-reset-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.botplus-mockup-body {
    padding: 16px;
    background: #f8fafc;
    min-height: 260px;
    max-height: 360px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.botplus-mockup-bubble {
    max-width: 85%;
    padding: 9px 13px;
    font-size: 13px;
    line-height: 1.45;
    word-break: break-word;
}

.botplus-mockup-bubble.bot {
    align-self: flex-start;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px 12px 12px 2px;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

.botplus-mockup-bubble.user {
    align-self: flex-end;
    border-radius: 12px 12px 2px 12px;
    color: #ffffff;
}

/* Simulated WooCommerce Product Card */
.botplus-mockup-product-card {
    align-self: flex-start;
    width: 90%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    display: flex;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.botplus-product-img-box {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.botplus-product-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.botplus-product-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.botplus-product-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
}

.botplus-product-price {
    font-weight: 700;
    color: #0f172a;
}

.botplus-product-rating {
    color: #f59e0b;
}

.botplus-product-btn {
    align-self: flex-start;
    margin-top: 4px;
    border: none;
    border-radius: 4px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 600;
    color: #ffffff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: opacity 0.15s ease;
}

.botplus-product-btn:hover {
    opacity: 0.9;
}

.botplus-mockup-quick-replies {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}

.botplus-mockup-chip {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 11px;
    color: #334155;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.botplus-mockup-chip:hover {
    border-color: #2563eb;
    color: #2563eb;
    background: #eff6ff;
}

.botplus-mockup-footer {
    display: flex;
    padding: 10px 12px;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
    gap: 8px;
}

.botplus-mockup-footer input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 12px;
    color: #0f172a;
    outline: none;
}

.botplus-mockup-footer input:focus {
    border-color: #2563eb;
}

.botplus-mockup-footer button {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: opacity 0.15s ease;
    flex-shrink: 0;
}

.botplus-mockup-footer button:hover {
    opacity: 0.9;
}

/* Storefront Stage Mockup */
.botplus-store-stage {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    min-height: 380px;
    display: flex;
    flex-direction: column;
}

.botplus-store-mock-navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}

.botplus-mock-logo {
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.5px;
    color: #0f172a;
}

.botplus-mock-nav-links {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #64748b;
}

.botplus-store-mock-content {
    padding: 20px 16px;
}

.botplus-mock-hero {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 24px 16px;
    text-align: center;
}

.botplus-mock-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    margin-bottom: 6px;
}

.botplus-mock-hero h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    color: #0f172a;
}

.botplus-mock-hero p {
    margin: 0;
    font-size: 12px;
    color: #64748b;
}

/* Floating Launcher in Store Mockup */
.botplus-floating-widget-container {
    position: absolute;
    bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.botplus-floating-widget-container.bottom_right {
    right: 16px;
    flex-direction: row;
}

.botplus-floating-widget-container.bottom_left {
    left: 16px;
    flex-direction: row-reverse;
}

.botplus-mock-teaser-pill {
    background: #ffffff;
    border: 1.5px solid;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #0f172a;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    max-width: 170px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.botplus-mock-floating-btn {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(0,0,0,0.18);
    cursor: pointer;
}
</style>
