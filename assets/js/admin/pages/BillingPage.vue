<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAdminStore } from '@/stores/admin';
import BotPlusSkeletonBlock from '@/components/skeletons/BotPlusSkeletonBlock.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';
import BotPlusEmptyState from '@/components/common/BotPlusEmptyState.vue';

const store = useAdminStore();
const data = ref({
    currentSubscription: {
        planId: 'free',
        planName: 'Free Plan',
        status: 'active',
        billingInterval: 'monthly',
        renewalDate: 'Ongoing',
        connectedEmail: '',
        cloudPortalUrl: '',
        isComped: false,
        cancelAtPeriodEnd: false,
        usage: {
            conversations: {
                used: 0,
                limit: 100,
                percentage: 0,
                label: 'AI Units (1 unit = 10 msgs)',
                hint: '0 of 100 units used this cycle',
            },
            cartRecovery: {
                used: 0,
                limit: 0,
                percentage: 0,
                label: 'Cart Recovery Prompts',
                hint: '0 prompts sent',
                enabled: false,
            },
            humanHandoff: {
                used: 0,
                limit: 0,
                percentage: 0,
                label: 'Human Handoff Tickets',
                hint: '0 tickets claimed',
                enabled: false,
            },
            leadCapture: {
                used: 0,
                limit: 25,
                percentage: 0,
                label: 'Lead & Opportunity Captures',
                hint: '0 leads captured',
            },
            knowledgeDocs: {
                used: 0,
                limit: 1,
                percentage: 0,
                label: 'Knowledge Documents',
                hint: '0 documents uploaded',
            },
            syncedCatalog: {
                unmetered: true,
                label: 'Product Catalog Sync',
                hint: 'Unmetered (Sync all your WooCommerce products)',
                used: 0,
            },
        },
    },
    plans: [],
});
const billingInterval = ref('monthly'); // 'monthly' | 'annual'
const selectedPlanForModal = ref(null);
const showUpgradeModal = ref(false);

const loading = ref(false);
const checkoutLoading = ref(false);
const portalLoading = ref(false);
const showCancelModal = ref(false);
const cancelLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const isLiveState = ref(false);

const connectedEmail = computed(() => {
    return store.botplusUserEmail || data.value.currentSubscription.connectedEmail;
});

const cloudBillingUrl = computed(() => {
    if (store.manageUrl) return store.manageUrl;
    if (window.BotPlusAdminConfig?.manageUrl) return window.BotPlusAdminConfig.manageUrl;
    const base = store.botplusAppUrl || window.BotPlusAdminConfig?.botplusAppUrl || window.BotPlusAdminInitialState?.botplusAppUrl || '';
    return base ? `${base.replace(/\/+$/, '')}/billing` : (data.value.currentSubscription.cloudPortalUrl || '/billing');
});

async function fetchBillingState() {
    loading.value = true;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(`${ajaxUrl}?action=botplus_get_billing_state&nonce=${encodeURIComponent(nonce)}`, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        if (json.success && json.data) {
            isLiveState.value = true;
            const live = json.data;
            const subState = live.billing_state || {};
            const curPkg = live.current_package || {};
            const usageData = live.usage || {};

            data.value.currentSubscription.planId = subState.package_slug || 'free';
            data.value.currentSubscription.planName = curPkg.name || (subState.package_slug ? subState.package_slug.toUpperCase() : 'Free Plan');
            data.value.currentSubscription.status = subState.billing_status || 'active';
            data.value.currentSubscription.billingInterval = subState.billing_interval || 'monthly';
            data.value.currentSubscription.isComped = Boolean(subState.is_comped);
            data.value.currentSubscription.cancelAtPeriodEnd = Boolean(subState.cancel_at_period_end);
            data.value.currentSubscription.renewalDate = subState.current_period_end 
                ? new Date(subState.current_period_end).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
                : 'Ongoing';

            // Quota Usage Mapping
            if (usageData.ai_conversation_units) {
                const u = usageData.ai_conversation_units;
                data.value.currentSubscription.usage.conversations = {
                    used: u.used,
                    limit: u.unlimited ? 'Unlimited' : u.cap,
                    percentage: u.cap > 0 ? Math.min(100, Math.round((u.used / u.cap) * 100)) : 0,
                    label: 'AI Units (1 unit = 10 msgs)',
                    hint: u.unlimited ? 'Unlimited AI units' : `${u.used} of ${u.cap} units used this cycle`,
                };
            }

            if (usageData.cart_recovery_prompts) {
                const c = usageData.cart_recovery_prompts;
                data.value.currentSubscription.usage.cartRecovery = {
                    used: c.used,
                    limit: c.unlimited ? 'Unlimited' : c.cap,
                    percentage: c.cap > 0 ? Math.min(100, Math.round((c.used / c.cap) * 100)) : 0,
                    label: 'Cart Recovery Prompts',
                    hint: c.enabled ? `${c.used} of ${c.cap} prompts sent` : 'Disabled on current plan',
                    enabled: c.enabled,
                };
            }

            if (usageData.human_handoff_tickets) {
                const h = usageData.human_handoff_tickets;
                data.value.currentSubscription.usage.humanHandoff = {
                    used: h.used,
                    limit: h.unlimited ? 'Unlimited' : h.cap,
                    percentage: h.cap > 0 ? Math.min(100, Math.round((h.used / h.cap) * 100)) : 0,
                    label: 'Human Handoff Tickets',
                    hint: h.enabled ? `${h.used} of ${h.cap} tickets claimed` : 'Disabled on current plan',
                    enabled: h.enabled,
                };
            }

            if (usageData.lead_capture_records) {
                const l = usageData.lead_capture_records;
                data.value.currentSubscription.usage.leadCapture = {
                    used: l.used,
                    limit: l.unlimited ? 'Unlimited' : l.cap,
                    percentage: l.cap > 0 ? Math.min(100, Math.round((l.used / l.cap) * 100)) : 0,
                    label: 'Lead & Opportunity Captures',
                    hint: l.unlimited ? 'Unlimited lead captures' : `${l.used} of ${l.cap === -1 ? 'Unlimited' : l.cap} leads captured`,
                };
            }

            if (usageData.knowledge_documents) {
                const k = usageData.knowledge_documents;
                data.value.currentSubscription.usage.knowledgeDocs = {
                    used: k.used,
                    limit: k.unlimited ? 'Unlimited' : k.cap,
                    percentage: k.cap > 0 ? Math.min(100, Math.round((k.used / k.cap) * 100)) : 0,
                    label: k.label || 'Custom Knowledge Documents (PDFs)',
                    hint: `${k.used} of ${k.cap === -1 ? 'Unlimited' : k.cap} PDF documents uploaded`,
                };
            }

            // Available Packages from backend
            if (Array.isArray(live.available_packages) && live.available_packages.length > 0) {
                data.value.plans = live.available_packages.map(pkg => {
                    const isCurr = pkg.slug === subState.package_slug;
                    const caps = pkg.caps || {};
                    return {
                        id: pkg.slug,
                        name: pkg.name,
                        tagline: pkg.description,
                        priceMonthly: pkg.price_monthly_usd,
                        priceAnnualMonthly: pkg.effective_monthly_usd,
                        annualSavings: pkg.annual_savings_percentage,
                        isCurrent: isCurr,
                        isPopular: pkg.slug.includes('growth') || pkg.slug.includes('business'),
                        badge: pkg.metadata?.badge || (pkg.slug.includes('growth') ? 'Most Popular' : null),
                        buttonText: isCurr ? 'Current Plan' : (pkg.price_monthly_cents === 0 ? 'Switch to Free' : `Upgrade to ${pkg.name.replace('WooCommerce ', '').replace('WordPress ', '')}`),
                        buttonVariant: pkg.slug.includes('growth') ? 'highlight' : (pkg.price_monthly_cents === 0 ? 'secondary' : 'primary'),
                        features: [
                            `${(caps.ai_conversation_units_per_month || 100).toLocaleString()} AI Units / mo (${((caps.ai_conversation_units_per_month || 100) * 10).toLocaleString()} msgs)`,
                            pkg.target_site_type === 'woocommerce' ? `${(caps.cart_recovery_prompts_per_month || 0) > 0 ? `${caps.cart_recovery_prompts_per_month} Cart Recovery Prompts / mo` : 'Cart Recovery (Starter+)'}` : null,
                            `${(caps.human_handoff_tickets_per_month || 0) > 0 ? `${caps.human_handoff_tickets_per_month} Human Handoff Tickets / mo` : 'Human Handoff (Starter+)'}`,
                            pkg.target_site_type === 'woocommerce' ? 'WooCommerce Catalog Sync (Unmetered)' : 'Posts & Pages Sync (Unmetered)',
                            `${caps.knowledge_documents_limit === -1 ? 'Unlimited' : (caps.knowledge_documents_limit || 1)} Knowledge Documents`,
                            pkg.feature_flags?.remove_botplus_branding ? 'Remove BotPlus Branding' : null,
                        ].filter(Boolean),
                        limitsSummary: {
                            conversations: `${(caps.ai_conversation_units_per_month || 100).toLocaleString()} / mo`,
                            aiUnits: `${(caps.ai_conversation_units_per_month || 100).toLocaleString()} / mo`,
                            cartRecovery: (caps.cart_recovery_prompts_per_month || 0) > 0 ? `${caps.cart_recovery_prompts_per_month} / mo` : 'None',
                            humanHandoff: (caps.human_handoff_tickets_per_month || 0) > 0 ? `${caps.human_handoff_tickets_per_month} / mo` : 'None',
                            knowledge: caps.knowledge_documents_limit === -1 ? 'Unlimited' : `${caps.knowledge_documents_limit || 1} Docs`,
                        },
                    };
                });
            }
        } else {
            errorMessage.value = json.data?.message || json.message || 'Unable to load billing state from BotPlus platform.';
        }
    } catch (e) {
        console.warn('Could not fetch live billing state:', e);
        errorMessage.value = 'Network error while connecting to BotPlus billing server.';
    } finally {
        loading.value = false;
    }
}

async function executeCheckout(plan) {
    if (!plan || checkoutLoading.value) return;
    checkoutLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: new URLSearchParams({
                action: 'botplus_create_billing_checkout',
                nonce: nonce,
                package_slug: plan.id,
                interval: billingInterval.value,
            }),
        });

        const json = await res.json();
        if (json.success && json.data) {
            if (json.data.is_free) {
                closeUpgradeModal();
                successMessage.value = 'Successfully switched to Free Plan.';
                await fetchBillingState();
                return;
            }
            if (json.data.direct_switch) {
                closeUpgradeModal();
                successMessage.value = 'Plan upgraded instantly! Prorated credits have been applied.';
                await fetchBillingState();
                return;
            }
            if (json.data.checkout_url) {
                window.location.href = json.data.checkout_url;
                return;
            }
        }
        errorMessage.value = json.data?.message || 'Failed to start checkout session.';
    } catch (e) {
        console.error('Checkout error:', e);
        errorMessage.value = 'Network error while initiating checkout: ' + e.message;
    } finally {
        checkoutLoading.value = false;
    }
}

async function openCustomerPortal() {
    portalLoading.value = true;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: new URLSearchParams({
                action: 'botplus_create_billing_customer_session',
                nonce: nonce,
            }),
        });

        const json = await res.json();
        if (json.success && json.data?.customer_portal_url) {
            window.open(json.data.customer_portal_url, '_blank', 'noopener,noreferrer');
            return;
        }

        errorMessage.value = json.data?.message || json.message || 'Unable to open Customer Portal. Please try again or contact support.';
    } catch (e) {
        console.error('Customer Portal error:', e);
        errorMessage.value = 'Network error while opening Customer Portal: ' + e.message;
    } finally {
        portalLoading.value = false;
    }
}

async function confirmCancelSubscription() {
    cancelLoading.value = true;
    errorMessage.value = '';

    const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.BotPlusAdminConfig?.nonce || '';

    try {
        const res = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: new URLSearchParams({
                action: 'botplus_cancel_billing',
                nonce: nonce,
            }),
        });

        const json = await res.json();
        if (json.success) {
            showCancelModal.value = false;
            successMessage.value = 'Subscription cancelled. You will retain access until the end of your billing cycle.';
            await fetchBillingState();
            return;
        }

        errorMessage.value = json.data?.message || json.message || 'Failed to cancel subscription.';
    } catch (e) {
        console.error('Cancel error:', e);
        errorMessage.value = 'Network error while cancelling subscription: ' + e.message;
    } finally {
        cancelLoading.value = false;
    }
}

function openUpgradeModal(plan) {
    selectedPlanForModal.value = plan;
    showUpgradeModal.value = true;
}

function closeUpgradeModal() {
    showUpgradeModal.value = false;
    selectedPlanForModal.value = null;
}

function getPlanPrice(plan) {
    if (plan.priceMonthly === 0) return '$0';
    if (billingInterval.value === 'annual') {
        return `$${plan.priceAnnualMonthly}`;
    }
    return `$${plan.priceMonthly}`;
}

function getPlanBillingPeriod(plan) {
    if (plan.priceMonthly === 0) return 'forever';
    return billingInterval.value === 'annual' ? '/mo (billed annually)' : '/month';
}

onMounted(() => {
    // Detect return parameters from centralized payment gateway handshake
    const params = new URLSearchParams(window.location.search);
    const status = params.get('billing_status');

    if (status === 'success') {
        successMessage.value = 'Payment successful! Your new plan features and quota are now active.';
        const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]billing_status=success/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
    } else if (status === 'cancelled') {
        errorMessage.value = 'Plan checkout was cancelled. Your current subscription remains unchanged.';
        const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]billing_status=cancelled/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
    }

    fetchBillingState();
});
</script>

<template>
    <div class="botplus-page-billing">
        <!-- Header & Top Summary -->
        <div class="botplus-billing-header">
            <div>
                <h2 class="botplus-section-title">Plans & Subscription Management</h2>
                <p class="botplus-section-desc">
                    Scale your conversational sales, cart recovery, and customer support capabilities.
                </p>
            </div>

            <div class="botplus-header-actions">
                <button 
                    type="button" 
                    class="botplus-btn-cloud-portal"
                    :disabled="portalLoading"
                    @click="openCustomerPortal"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    <span v-if="portalLoading">Opening Portal...</span>
                    <span v-else>Invoices & Payment Cards</span>
                </button>
            </div>
        </div>

        <!-- Feedback Alerts -->
        <div v-if="errorMessage && isLiveState" style="margin-bottom: 16px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; font-size: 13px;">
            {{ errorMessage }}
        </div>
        <div v-if="successMessage" style="margin-bottom: 16px; padding: 12px 16px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; color: #047857; font-size: 13px;">
            {{ successMessage }}
        </div>

        <!-- Offline / Load Error Retry Banner -->
        <div v-if="errorMessage && !isLiveState" style="margin-bottom: 24px;">
            <BotPlusInlineRetry :message="errorMessage" :busy="loading" @retry="fetchBillingState" />
        </div>

        <!-- Current Subscription & Usage Card -->
        <div v-if="loading && !isLiveState" class="botplus-card botplus-subscription-banner" style="display: flex; flex-direction: column; gap: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px;">
                <div style="display: flex; flex-direction: column; gap: 10px; width: 60%;">
                    <BotPlusSkeletonBlock width="140px" height="24px" radius="9999px" />
                    <BotPlusSkeletonBlock width="260px" height="32px" radius="6px" />
                    <BotPlusSkeletonBlock width="380px" height="18px" radius="4px" />
                </div>
                <BotPlusSkeletonBlock width="300px" height="60px" radius="8px" />
            </div>
            <div class="botplus-usage-grid">
                <div v-for="i in 4" :key="i" class="botplus-usage-item" style="display: flex; flex-direction: column; gap: 8px;">
                    <BotPlusSkeletonBlock width="60%" height="16px" radius="4px" />
                    <BotPlusSkeletonBlock width="100%" height="8px" radius="9999px" />
                    <BotPlusSkeletonBlock width="40%" height="12px" radius="4px" />
                </div>
            </div>
        </div>
        <div v-else class="botplus-card botplus-subscription-banner">
            <div class="botplus-subscription-overview">
                <div class="botplus-tier-badge-group">
                    <span v-if="data.currentSubscription?.isComped" class="botplus-status-pill" style="background: #fef3c7; color: #92400e;">
                        <span class="botplus-pulse-dot" style="background: #f59e0b;"></span>
                        VIP Comped (Unlimited)
                    </span>
                    <span v-else class="botplus-status-pill is-active">
                        <span class="botplus-pulse-dot"></span>
                        Active Subscription
                    </span>
                    <h3 class="botplus-current-plan-title">{{ data.currentSubscription?.planName || 'Free Plan' }}</h3>
                    <p class="botplus-account-meta">
                        Connected Account: <strong>{{ connectedEmail || 'Store Owner' }}</strong>
                        <span class="botplus-separator">&bull;</span>
                        <template v-if="data.currentSubscription?.cancelAtPeriodEnd">
                            <span style="color: #dc2626; font-weight: 600;">Cancels on: {{ data.currentSubscription?.renewalDate }}</span>
                        </template>
                        <template v-else>
                            Renews on: <strong>{{ data.currentSubscription?.renewalDate || 'Ongoing' }}</strong>
                        </template>
                        <template v-if="!data.currentSubscription?.isComped && data.currentSubscription?.planId !== 'free' && data.currentSubscription?.planId !== 'wc-free' && !data.currentSubscription?.cancelAtPeriodEnd">
                            <span class="botplus-separator">&bull;</span>
                            <button 
                                type="button" 
                                style="background: none; border: none; padding: 0; color: #dc2626; font-size: 13px; text-decoration: underline; cursor: pointer;"
                                @click="showCancelModal = true"
                            >
                                Cancel subscription
                            </button>
                        </template>
                    </p>
                </div>

                <div class="botplus-zero-fee-callout">
                    <div class="botplus-callout-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="botplus-callout-title">0% Transaction Fee Guarantee</div>
                        <div class="botplus-callout-sub">BotPlus never takes a cut of your WooCommerce sales or recovered carts.</div>
                    </div>
                </div>
            </div>

            <!-- Usage Quota Progress Bars -->
            <div class="botplus-usage-grid">
                <!-- 1. AI Units -->
                <div class="botplus-usage-item">
                    <div class="botplus-usage-top">
                        <span class="botplus-usage-label">{{ data.currentSubscription?.usage?.conversations?.label || 'AI Units (1 unit = 10 msgs)' }}</span>
                        <span class="botplus-usage-metric">
                            <strong>{{ data.currentSubscription?.usage?.conversations?.used ?? 0 }}</strong> / {{ data.currentSubscription?.usage?.conversations?.limit ?? 100 }}
                        </span>
                    </div>
                    <div class="botplus-progress-track">
                        <div 
                            class="botplus-progress-fill" 
                            :style="{ 
                                width: (data.currentSubscription?.usage?.conversations?.percentage ?? 0) + '%',
                                backgroundColor: (data.currentSubscription?.usage?.conversations?.percentage >= 90) ? '#f59e0b' : '#2563eb' 
                            }"
                        ></div>
                    </div>
                    <span class="botplus-usage-hint">{{ data.currentSubscription?.usage?.conversations?.hint || 'Usage quota' }}</span>
                </div>

                <!-- 2. Cart Recovery Prompts -->
                <div class="botplus-usage-item">
                    <div class="botplus-usage-top">
                        <span class="botplus-usage-label">{{ data.currentSubscription?.usage?.cartRecovery?.label || 'Cart Recovery Prompts' }}</span>
                        <span class="botplus-usage-metric">
                            <strong>{{ data.currentSubscription?.usage?.cartRecovery?.used ?? 0 }}</strong> / {{ data.currentSubscription?.usage?.cartRecovery?.limit ?? 0 }}
                        </span>
                    </div>
                    <div class="botplus-progress-track">
                        <div 
                            class="botplus-progress-fill" 
                            :style="{ 
                                width: (data.currentSubscription?.usage?.cartRecovery?.percentage ?? 0) + '%',
                                backgroundColor: (data.currentSubscription?.usage?.cartRecovery?.percentage >= 90) ? '#f59e0b' : '#2563eb' 
                            }"
                        ></div>
                    </div>
                    <span class="botplus-usage-hint">{{ data.currentSubscription?.usage?.cartRecovery?.hint || '0 prompts sent' }}</span>
                </div>

                <!-- 3. Human Support Handoff -->
                <div class="botplus-usage-item">
                    <div class="botplus-usage-top">
                        <span class="botplus-usage-label">{{ data.currentSubscription?.usage?.humanHandoff?.label || 'Human Handoff Tickets' }}</span>
                        <span class="botplus-usage-metric">
                            <strong>{{ data.currentSubscription?.usage?.humanHandoff?.used ?? 0 }}</strong> / {{ data.currentSubscription?.usage?.humanHandoff?.limit ?? 0 }}
                        </span>
                    </div>
                    <div class="botplus-progress-track">
                        <div 
                            class="botplus-progress-fill" 
                            :style="{ 
                                width: (data.currentSubscription?.usage?.humanHandoff?.percentage ?? 0) + '%',
                                backgroundColor: (data.currentSubscription?.usage?.humanHandoff?.percentage >= 90) ? '#f59e0b' : '#2563eb' 
                            }"
                        ></div>
                    </div>
                    <span class="botplus-usage-hint">{{ data.currentSubscription?.usage?.humanHandoff?.hint || '0 tickets claimed' }}</span>
                </div>

                <!-- 4. Lead & Opportunity Captures -->
                <div class="botplus-usage-item">
                    <div class="botplus-usage-top">
                        <span class="botplus-usage-label">{{ data.currentSubscription?.usage?.leadCapture?.label || 'Lead & Opportunity Captures' }}</span>
                        <span class="botplus-usage-metric">
                            <strong>{{ data.currentSubscription?.usage?.leadCapture?.used ?? 0 }}</strong> / {{ data.currentSubscription?.usage?.leadCapture?.limit ?? 25 }}
                        </span>
                    </div>
                    <div class="botplus-progress-track">
                        <div 
                            class="botplus-progress-fill" 
                            :style="{ 
                                width: (data.currentSubscription?.usage?.leadCapture?.percentage ?? 0) + '%',
                                backgroundColor: (data.currentSubscription?.usage?.leadCapture?.percentage >= 90) ? '#f59e0b' : '#2563eb' 
                            }"
                        ></div>
                    </div>
                    <span class="botplus-usage-hint">{{ data.currentSubscription?.usage?.leadCapture?.hint || '0 leads captured' }}</span>
                </div>

                <!-- 5. Knowledge Documents (PDFs) -->
                <div class="botplus-usage-item">
                    <div class="botplus-usage-top">
                        <span class="botplus-usage-label">{{ data.currentSubscription?.usage?.knowledgeDocs?.label || 'Custom Knowledge Documents (PDFs)' }}</span>
                        <span class="botplus-usage-metric">
                            <strong>{{ data.currentSubscription?.usage?.knowledgeDocs?.used ?? 0 }}</strong> / {{ data.currentSubscription?.usage?.knowledgeDocs?.limit ?? 1 }}
                        </span>
                    </div>
                    <div class="botplus-progress-track">
                        <div 
                            class="botplus-progress-fill" 
                            :style="{ 
                                width: (data.currentSubscription?.usage?.knowledgeDocs?.percentage ?? 0) + '%',
                                backgroundColor: (data.currentSubscription?.usage?.knowledgeDocs?.percentage >= 90) ? '#f59e0b' : '#2563eb' 
                            }"
                        ></div>
                    </div>
                    <span class="botplus-usage-hint">{{ data.currentSubscription?.usage?.knowledgeDocs?.hint || '0 PDF documents uploaded' }}</span>
                </div>

                <!-- 6. WooCommerce Synced Catalog (Unmetered) -->
                <div class="botplus-usage-item">
                    <div class="botplus-usage-top">
                        <span class="botplus-usage-label">{{ data.currentSubscription?.usage?.syncedCatalog?.label || 'Product Catalog Sync' }}</span>
                        <span class="botplus-usage-metric">
                            <strong>{{ data.currentSubscription?.usage?.syncedCatalog?.used ?? 0 }}</strong> items
                        </span>
                    </div>
                    <div class="botplus-progress-track">
                        <div class="botplus-progress-fill" style="width: 100%; background-color: #2563eb;"></div>
                    </div>
                    <span class="botplus-usage-hint">{{ data.currentSubscription?.usage?.syncedCatalog?.hint || 'Unmetered (Sync all your products)' }}</span>
                </div>
            </div>
        </div>

        <!-- Billing Interval Switcher -->
        <div class="botplus-interval-switcher-row">
            <div class="botplus-interval-switcher">
                <button 
                    type="button" 
                    class="botplus-interval-btn" 
                    :class="{ 'is-active': billingInterval === 'monthly' }"
                    @click="billingInterval = 'monthly'"
                >
                    Monthly Billing
                </button>
                <button 
                    type="button" 
                    class="botplus-interval-btn" 
                    :class="{ 'is-active': billingInterval === 'annual' }"
                    @click="billingInterval = 'annual'"
                >
                    Annual Billing
                    <span class="botplus-discount-badge">Save 20%</span>
                </button>
            </div>
        </div>

        <!-- Pricing Tier Cards Grid -->
        <div v-if="loading && !data.plans.length" class="botplus-plans-grid">
            <div v-for="i in 3" :key="i" class="botplus-plan-card" style="display: flex; flex-direction: column; gap: 16px; padding: 24px;">
                <BotPlusSkeletonBlock width="40%" height="24px" radius="4px" />
                <BotPlusSkeletonBlock width="80%" height="16px" radius="4px" />
                <BotPlusSkeletonBlock width="50%" height="32px" radius="4px" />
                <div style="border-top: 1px solid #e2e8f0; margin: 8px 0;"></div>
                <BotPlusSkeletonBlock v-for="j in 4" :key="j" width="90%" height="16px" radius="4px" />
                <BotPlusSkeletonBlock width="100%" height="40px" radius="6px" style="margin-top: auto;" />
            </div>
        </div>

        <BotPlusEmptyState
            v-else-if="!data.plans.length && !loading"
            icon="cart"
            title="No subscription packages available"
            description="Unable to load subscription packages at this time. Please check your connection or retry."
        />

        <div v-else class="botplus-plans-grid">
            <div 
                v-for="plan in data.plans" 
                :key="plan.id" 
                class="botplus-plan-card" 
                :class="{
                    'is-popular': plan.isPopular,
                    'is-current': plan.isCurrent
                }"
            >
                <div v-if="plan.badge" class="botplus-popular-badge">{{ plan.badge }}</div>
                <div v-else-if="plan.isCurrent" class="botplus-current-badge">Your Active Plan</div>

                <div class="botplus-plan-header">
                    <h4 class="botplus-plan-name">{{ plan.name }}</h4>
                    <p class="botplus-plan-tagline">{{ plan.tagline }}</p>
                    
                    <div class="botplus-price-row">
                        <span class="botplus-price-number">{{ getPlanPrice(plan) }}</span>
                        <span class="botplus-price-period">{{ getPlanBillingPeriod(plan) }}</span>
                    </div>
                </div>

                <div class="botplus-plan-divider"></div>

                <!-- Limits Quick View -->
                <div class="botplus-plan-limits">
                    <div class="botplus-limit-row">
                        <span class="botplus-limit-label">Conversations:</span>
                        <span class="botplus-limit-val">{{ plan.limitsSummary?.conversations || plan.limitsSummary?.aiUnits || 'Standard' }}</span>
                    </div>
                    <div class="botplus-limit-row">
                        <span class="botplus-limit-label">Cart Recovery:</span>
                        <span class="botplus-limit-val">{{ plan.limitsSummary?.cartRecovery || 'None' }}</span>
                    </div>
                    <div class="botplus-limit-row">
                        <span class="botplus-limit-label">Knowledge Docs:</span>
                        <span class="botplus-limit-val">{{ plan.limitsSummary?.knowledge || '1 Doc' }}</span>
                    </div>
                </div>

                <div class="botplus-plan-divider"></div>

                <!-- Features Checklist -->
                <ul class="botplus-features-list">
                    <li v-for="(feat, idx) in plan.features" :key="idx" class="botplus-feature-item">
                        <svg class="botplus-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>{{ feat }}</span>
                    </li>
                </ul>

                <!-- Action Button -->
                <div class="botplus-plan-footer">
                    <button 
                        v-if="plan.isCurrent"
                        type="button" 
                        class="botplus-btn-plan is-active"
                        disabled
                    >
                        Current Plan
                    </button>
                    <button 
                        v-else
                        type="button" 
                        class="botplus-btn-plan"
                        :class="{
                            'is-primary-highlight': plan.buttonVariant === 'highlight',
                            'is-primary': plan.buttonVariant === 'primary',
                            'is-outline': plan.buttonVariant === 'outline'
                        }"
                        @click="openUpgradeModal(plan)"
                    >
                        {{ plan.buttonText }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Upgrade Modal -->
        <div v-if="showUpgradeModal && selectedPlanForModal" class="botplus-modal-backdrop" @click.self="closeUpgradeModal">
            <div class="botplus-modal-dialog">
                <div class="botplus-modal-header">
                    <div>
                        <h4 class="botplus-modal-title">Upgrade to {{ selectedPlanForModal.name }}</h4>
                        <p class="botplus-modal-sub">Confirm plan switch and billing authorization</p>
                    </div>
                    <button type="button" class="botplus-modal-close" @click="closeUpgradeModal">&times;</button>
                </div>

                <div class="botplus-modal-body">
                    <div class="botplus-modal-plan-summary">
                        <div class="botplus-modal-price-box">
                            <span class="botplus-modal-price">{{ getPlanPrice(selectedPlanForModal) }}</span>
                            <span class="botplus-modal-interval">{{ getPlanBillingPeriod(selectedPlanForModal) }}</span>
                        </div>
                        <p class="botplus-modal-tagline">{{ selectedPlanForModal.tagline }}</p>
                    </div>

                    <div class="botplus-modal-features-preview">
                        <h5 class="botplus-preview-title">Included in this plan:</h5>
                        <ul class="botplus-preview-list">
                            <li v-for="(f, i) in selectedPlanForModal.features" :key="i">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>{{ f }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="botplus-modal-notice">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span>You will be redirected securely to the BotPlus Cloud billing terminal to finalize your checkout.</span>
                    </div>
                </div>

                <div class="botplus-modal-footer">
                    <button type="button" class="botplus-btn-cancel" @click="closeUpgradeModal">Cancel</button>
                    <button 
                        type="button" 
                        class="botplus-btn-confirm"
                        :disabled="checkoutLoading"
                        @click="executeCheckout(selectedPlanForModal)"
                    >
                        <span v-if="checkoutLoading">Starting Checkout...</span>
                        <span v-else-if="selectedPlanForModal.priceMonthly === 0">Confirm Plan Switch</span>
                        <span v-else>Proceed to Hosted Checkout &rarr;</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel Subscription Modal -->
        <div v-if="showCancelModal" class="botplus-modal-backdrop" @click.self="showCancelModal = false">
            <div class="botplus-modal-dialog">
                <div class="botplus-modal-header">
                    <div>
                        <h4 class="botplus-modal-title" style="color: #dc2626;">Cancel Subscription</h4>
                        <p class="botplus-modal-sub">Are you sure you want to cancel your recurring subscription?</p>
                    </div>
                    <button type="button" class="botplus-modal-close" @click="showCancelModal = false">&times;</button>
                </div>

                <div class="botplus-modal-body">
                    <p style="font-size: 14px; line-height: 1.5; color: #4b5563; margin-bottom: 16px;">
                        Your current subscription features and quotas will remain active until the end of your billing cycle on <strong>{{ data.currentSubscription.renewalDate }}</strong>.
                    </p>
                    <p style="font-size: 14px; line-height: 1.5; color: #4b5563;">
                        After this date, your store will automatically be switched to the Free Plan. You won't be charged again.
                    </p>
                </div>

                <div class="botplus-modal-footer">
                    <button type="button" class="botplus-btn-cancel" @click="showCancelModal = false">Keep Subscription</button>
                    <button 
                        type="button" 
                        class="botplus-btn-confirm"
                        style="background-color: #dc2626; border-color: #dc2626;"
                        :disabled="cancelLoading"
                        @click="confirmCancelSubscription"
                    >
                        <span v-if="cancelLoading">Cancelling...</span>
                        <span v-else>Confirm Cancellation</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-page-billing {
    max-width: 1180px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    color: #1e293b;
}

/* Header */
.botplus-billing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.botplus-section-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-section-desc {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

.botplus-btn-cloud-portal {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.botplus-btn-cloud-portal:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

/* Generic Card */
.botplus-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* Subscription Banner */
.botplus-subscription-banner {
    border-left: 4px solid #3b82f6;
}

.botplus-subscription-overview {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 20px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 16px;
}

.botplus-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-bottom: 8px;
}

.botplus-status-pill.is-active {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.botplus-pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #10b981;
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .4; }
}

.botplus-current-plan-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-account-meta {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

.botplus-separator {
    margin: 0 6px;
    color: #cbd5e1;
}

.botplus-zero-fee-callout {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 10px 14px;
    max-width: 400px;
}

.botplus-callout-icon {
    color: #16a34a;
    display: flex;
    align-items: center;
}

.botplus-callout-title {
    font-size: 13px;
    font-weight: 600;
    color: #15803d;
}

.botplus-callout-sub {
    font-size: 12px;
    color: #166534;
    margin-top: 1px;
}

/* Usage Meters */
.botplus-usage-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    padding-top: 20px;
}

@media (max-width: 900px) {
    .botplus-usage-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .botplus-usage-grid {
        grid-template-columns: 1fr;
    }
}

.botplus-usage-item {
    display: flex;
    flex-direction: column;
}

.botplus-usage-top {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 6px;
}

.botplus-usage-label {
    font-weight: 600;
    color: #475569;
}

.botplus-usage-metric {
    color: #0f172a;
}

.botplus-progress-track {
    width: 100%;
    height: 6px;
    background: #f1f5f9;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 6px;
}

.botplus-progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.botplus-progress-fill.is-primary { background: #3b82f6; }
.botplus-progress-fill.is-warning { background: #f59e0b; }
.botplus-progress-fill.is-success { background: #10b981; }
.botplus-progress-fill.is-indigo { background: #6366f1; }
.botplus-progress-fill.is-purple { background: #8b5cf6; }

.botplus-usage-hint {
    font-size: 11px;
    color: #94a3b8;
}

/* Interval Switcher */
.botplus-interval-switcher-row {
    display: flex;
    justify-content: center;
    margin-bottom: 28px;
}

.botplus-interval-switcher {
    display: inline-flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.botplus-interval-btn {
    border: none;
    background: transparent;
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.botplus-interval-btn.is-active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.botplus-discount-badge {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-size: 11px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
}

/* Plans Grid */
.botplus-plans-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
    align-items: stretch;
}

@media (max-width: 1024px) {
    .botplus-plans-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .botplus-plans-grid {
        grid-template-columns: 1fr;
    }
}

.botplus-plan-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 24px 20px;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.botplus-plan-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.06);
}

.botplus-plan-card.is-popular {
    border: 2px solid #3b82f6;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.15);
}

.botplus-plan-card.is-current {
    border-color: #cbd5e1;
    background: #fafaf9;
}

.botplus-popular-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: #2563eb;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 10px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);
}

.botplus-current-badge {
    position: absolute;
    top: -11px;
    left: 50%;
    transform: translateX(-50%);
    background: #475569;
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 8px;
    border-radius: 4px;
}

.botplus-plan-header {
    margin-bottom: 12px;
}

.botplus-plan-name {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-plan-tagline {
    margin: 6px 0 12px 0;
    font-size: 12px;
    color: #64748b;
    min-height: 32px;
    line-height: 1.4;
}

.botplus-price-row {
    display: flex;
    align-items: baseline;
    gap: 4px;
    margin-top: 8px;
}

.botplus-price-number {
    font-size: 32px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}

.botplus-price-period {
    font-size: 12px;
    color: #64748b;
}

.botplus-plan-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 14px 0;
}

.botplus-plan-limits {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.botplus-limit-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.botplus-limit-label {
    color: #64748b;
}

.botplus-limit-val {
    font-weight: 600;
    color: #0f172a;
}

.botplus-features-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
    display: flex;
    flex-direction: column;
    gap: 9px;
    flex: 1;
}

.botplus-feature-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 12px;
    color: #334155;
    line-height: 1.4;
}

.botplus-check-icon {
    color: #10b981;
    flex-shrink: 0;
    margin-top: 2px;
}

.botplus-plan-footer {
    margin-top: auto;
}

.botplus-btn-plan {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    text-align: center;
}

.botplus-btn-plan.is-active {
    background: #e2e8f0;
    color: #64748b;
    cursor: default;
}

.botplus-btn-plan.is-primary-highlight {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
}

.botplus-btn-plan.is-primary-highlight:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.4);
}

.botplus-btn-plan.is-primary {
    background: #0f172a;
    color: #ffffff;
}

.botplus-btn-plan.is-primary:hover {
    background: #1e293b;
}

.botplus-btn-plan.is-outline {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
}

.botplus-btn-plan.is-outline:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

/* Feature Comparison Table */
.botplus-comparison-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.botplus-toggle-matrix-btn {
    background: none;
    border: none;
    color: #3b82f6;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.botplus-arrow-icon {
    transition: transform 0.2s ease;
}

.botplus-arrow-icon.is-flipped {
    transform: rotate(180deg);
}

.botplus-table-responsive {
    overflow-x: auto;
    margin-top: 16px;
}

.botplus-comparison-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.botplus-comparison-table th {
    background: #f8fafc;
    padding: 12px 14px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}

.botplus-comparison-table th.col-feature {
    width: 40%;
}

.botplus-comparison-table th.col-plan {
    width: 15%;
    text-align: center;
}

.botplus-comparison-table th.is-highlighted {
    background: #eff6ff;
    color: #1d4ed8;
    border-bottom-color: #3b82f6;
}

.category-header-row td {
    background: #f1f5f9;
    font-weight: 700;
    color: #1e293b;
    padding: 10px 14px;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
}

.feature-data-row td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
}

.feature-name-cell .feature-title {
    font-weight: 600;
    color: #0f172a;
}

.feature-name-cell .feature-desc {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}

.feature-val-cell {
    text-align: center;
}

.feature-val-cell.is-highlighted-cell {
    background: #f8faff;
}

.val-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.val-cross {
    color: #94a3b8;
    font-size: 16px;
}

.val-text {
    font-size: 12px;
    font-weight: 500;
    color: #334155;
}

/* FAQs */
.botplus-faq-section {
    margin-top: 24px;
}

.botplus-card-header {
    margin-bottom: 16px;
}

.botplus-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-card-desc {
    margin: 4px 0 0 0;
    font-size: 12px;
    color: #64748b;
}

.botplus-faq-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.botplus-faq-item {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    transition: border-color 0.2s ease;
}

.botplus-faq-item.is-expanded {
    border-color: #cbd5e1;
}

.botplus-faq-question {
    width: 100%;
    padding: 14px 16px;
    background: #f8fafc;
    border: none;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.botplus-faq-icon {
    transition: transform 0.2s ease;
    color: #64748b;
}

.botplus-faq-icon.is-flipped {
    transform: rotate(180deg);
}

.botplus-faq-answer {
    padding: 14px 16px;
    background: #ffffff;
    font-size: 13px;
    color: #475569;
    line-height: 1.5;
    border-top: 1px solid #f1f5f9;
}

.botplus-faq-answer p {
    margin: 0;
}

/* Concierge Banner */
.botplus-concierge-banner {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 12px;
    padding: 24px 28px;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 18px;
}

.botplus-concierge-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.12);
    color: #93c5fd;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
}

.botplus-concierge-title {
    margin: 0 0 6px 0;
    font-size: 17px;
    font-weight: 700;
}

.botplus-concierge-desc {
    margin: 0;
    font-size: 13px;
    color: #94a3b8;
    max-width: 650px;
    line-height: 1.4;
}

.botplus-btn-concierge {
    display: inline-block;
    background: #ffffff;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.2s ease;
    white-space: nowrap;
}

.botplus-btn-concierge:hover {
    background: #f1f5f9;
}

/* Modal */
.botplus-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

.botplus-modal-dialog {
    background: #ffffff;
    width: 90%;
    max-width: 480px;
    border-radius: 14px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    animation: modalSlide 0.2s ease-out;
}

@keyframes modalSlide {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.botplus-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
}

.botplus-modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-modal-sub {
    margin: 3px 0 0 0;
    font-size: 12px;
    color: #64748b;
}

.botplus-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    line-height: 1;
    color: #94a3b8;
    cursor: pointer;
}

.botplus-modal-body {
    padding: 20px 24px;
}

.botplus-modal-plan-summary {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 16px;
    text-align: center;
}

.botplus-modal-price-box {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 4px;
    margin-bottom: 6px;
}

.botplus-modal-price {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
}

.botplus-modal-interval {
    font-size: 12px;
    color: #64748b;
}

.botplus-modal-tagline {
    margin: 0;
    font-size: 12px;
    color: #64748b;
}

.botplus-preview-title {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.botplus-preview-list {
    list-style: none;
    padding: 0;
    margin: 0 0 16px 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.botplus-preview-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #334155;
}

.botplus-modal-notice {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    color: #1e40af;
    line-height: 1.4;
}

.botplus-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
}

.botplus-btn-cancel {
    padding: 8px 16px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}

.botplus-btn-confirm {
    padding: 8px 16px;
    border: none;
    background: #2563eb;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #ffffff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.botplus-btn-confirm:hover {
    background: #1d4ed8;
}
</style>

