<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { formatCurrencyDisplay } from '@/utils/currency';
import BotPlusMetricGridSkeleton from '@/components/skeletons/BotPlusMetricGridSkeleton.vue';
import BotPlusTableSkeleton from '@/components/skeletons/BotPlusTableSkeleton.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';
import BotPlusEmptyState from '@/components/common/BotPlusEmptyState.vue';

const data = ref({
    metrics: {
        totalAbandoned: 0,
        totalRecovered: 0,
        recoveredRevenue: '$0.00',
        recoveryRate: '0.0%',
    },
    carts: [],
    pagination: {
        currentPage: 1,
        totalPages: 1,
        totalItems: 0,
    },
});
const statusFilter = ref('all');
const selectedPeriod = ref('30d');
const searchQuery = ref('');
const copiedCartId = ref(null);
const loading = ref(false);
const error = ref('');
const expandedCartId = ref(null);

let searchDebounceTimer = null;

const filteredCarts = computed(() => {
    const list = Array.isArray(data.value?.carts) ? data.value.carts : [];
    if (statusFilter.value === 'all') return list;
    return list.filter(c => c.status === statusFilter.value);
});

function copyCheckoutLink(cart) {
    if (navigator.clipboard && cart.checkoutUrl) {
        navigator.clipboard.writeText(cart.checkoutUrl);
        copiedCartId.value = cart.id;
        setTimeout(() => {
            copiedCartId.value = null;
        }, 2000);
    }
}

function toggleExpandCart(cartId) {
    expandedCartId.value = expandedCartId.value === cartId ? null : cartId;
}

async function fetchCarts() {
    const config = window.BotPlusAdminConfig;
    if (!config || !config.ajaxUrl) {
        // Fallback to mock data if in local dev without WordPress AJAX harness
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const url = new URL(config.ajaxUrl);
        url.searchParams.append('action', 'botplus_get_carts');
        url.searchParams.append('nonce', config.nonce || '');
        url.searchParams.append('status', statusFilter.value);
        url.searchParams.append('period', selectedPeriod.value);
        if (searchQuery.value) {
            url.searchParams.append('search', searchQuery.value.trim());
        }

        const res = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
            },
        });
        const json = await res.json();

        if (json.success && json.data) {
            data.value = {
                metrics: json.data.metrics || data.value.metrics,
                carts: json.data.carts || [],
                pagination: json.data.pagination || {},
            };
        } else {
            error.value = json.data?.message || json.message || 'Failed to load carts.';
        }
    } catch (err) {
        console.warn('[BotPlus Carts] Load carts notice:', err.message);
        error.value = 'Network error loading carts.';
    } finally {
        loading.value = false;
    }
}

watch(statusFilter, () => {
    fetchCarts();
});

watch(selectedPeriod, () => {
    fetchCarts();
});

watch(searchQuery, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        fetchCarts();
    }, 350);
});

onMounted(() => {
    fetchCarts();
});
</script>

<template>
    <div class="botplus-page-carts">
        <!-- Top Metrics Cards -->
        <BotPlusMetricGridSkeleton v-if="loading && !data.carts.length" :count="4" />
        <div v-else class="botplus-metric-grid">
            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Recovered Revenue</span>
                <div class="botplus-metric-value text-primary">{{ formatCurrencyDisplay(data.metrics?.recoveredRevenue || '$0.00') }}</div>
                <div class="botplus-metric-hint">From {{ data.metrics?.recoveredCount || 0 }} recovered customer carts</div>
            </div>

            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Recovery Success Rate</span>
                <div class="botplus-metric-value">{{ data.metrics?.recoveryRate || '0.0%' }}</div>
                <div class="botplus-metric-hint">Industry average: ~10%</div>
            </div>

            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Active In-Flight Carts</span>
                <div class="botplus-metric-value">{{ data.metrics?.activeInFlight || 0 }}</div>
                <div class="botplus-metric-hint">Active storefront sessions (TTL &lt; 60m)</div>
            </div>

            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Total Carts Tracked</span>
                <div class="botplus-metric-value">{{ data.metrics?.totalTracked || 0 }}</div>
                <div class="botplus-metric-hint">Across selected period</div>
            </div>
        </div>

        <!-- Carts Table Card -->
        <div class="botplus-card botplus-table-card">
            <div class="botplus-card-header">
                <div>
                    <h3 class="botplus-card-title">Live & Recovered Abandoned Carts</h3>
                    <p class="botplus-card-desc">Zero-Worker real-time cart intelligence & recovery managed by BotPlus</p>
                </div>

                <div class="botplus-header-actions">
                    <div class="botplus-search-box">
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Filter by customer, cart token, SKU..."
                            class="botplus-search-input"
                        />
                    </div>

                    <select v-model="selectedPeriod" class="botplus-period-select">
                        <option value="24h">Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="all">All Time</option>
                    </select>

                    <div class="botplus-filter-buttons">
                        <button 
                            :class="{ 'is-active': statusFilter === 'all' }" 
                            @click="statusFilter = 'all'"
                        >
                            All
                        </button>
                        <button 
                            :class="{ 'is-active': statusFilter === 'in_flight' }" 
                            @click="statusFilter = 'in_flight'"
                        >
                            In-Flight
                        </button>
                        <button 
                            :class="{ 'is-active': statusFilter === 'abandoned' }" 
                            @click="statusFilter = 'abandoned'"
                        >
                            Abandoned
                        </button>
                        <button 
                            :class="{ 'is-active': statusFilter === 'recovered' }" 
                            @click="statusFilter = 'recovered'"
                        >
                            Recovered
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div v-if="loading && !filteredCarts.length" style="padding: 16px;">
                <BotPlusTableSkeleton :columns="7" :rows="5" />
            </div>

            <!-- Error State -->
            <div v-else-if="error" style="padding: 24px;">
                <BotPlusInlineRetry :message="error" :busy="loading" @retry="fetchCarts" />
            </div>

            <!-- Empty State -->
            <BotPlusEmptyState
                v-else-if="!filteredCarts.length"
                icon="cart"
                title="No carts found"
                description="No active or abandoned carts match your current filter and search criteria."
            />

            <!-- Table -->
            <div v-else class="botplus-table-wrapper">
                <table class="botplus-table">
                    <thead>
                        <tr>
                            <th>Cart Token</th>
                            <th>Customer & Location</th>
                            <th>Items & Quantities</th>
                            <th>Cart Total</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="cart in filteredCarts" :key="cart.id">
                            <tr :class="{ 'is-expanded': expandedCartId === cart.id }">
                                <td>
                                    <code>{{ cart.cartToken }}</code>
                                    <div v-if="cart.conversation_id" class="text-muted text-xs" style="margin-top: 3px;">
                                        Chat #{{ cart.conversation_id }}
                                    </div>
                                </td>
                                <td>
                                    <div class="botplus-customer-meta">
                                        <div class="font-medium">{{ cart.customer?.name || 'Visitor' }}</div>
                                        <div class="text-muted text-xs">{{ cart.customer?.location }}</div>
                                        <div class="text-muted text-xs font-mono">{{ cart.customer?.email }}</div>
                                    </div>
                                </td>
                                <td>
                                    <ul class="botplus-cart-item-list">
                                        <li v-for="item in (cart.items || []).slice(0, 2)" :key="item.id">
                                            {{ item.quantity }}x {{ item.name }} 
                                            <span class="text-muted">({{ item.total }})</span>
                                        </li>
                                        <li v-if="(cart.items || []).length > 2" class="text-muted text-xs">
                                            + {{ (cart.items || []).length - 2 }} more item(s)...
                                        </li>
                                    </ul>
                                </td>
                                <td>
                                    <strong>{{ formatCurrencyDisplay(cart.total) }}</strong>
                                </td>
                                <td class="text-muted text-xs">
                                    {{ cart.lastActivity }}
                                </td>
                                <td>
                                    <span class="bp-status-tag">
                                        <span class="bp-status-dot" :class="{ 'is-success': cart.status === 'recovered', 'is-warning': cart.status === 'in_flight', 'is-danger': cart.status === 'abandoned' }"></span>
                                        {{ cart.statusLabel }}
                                    </span>
                                </td>
                                <td>
                                    <div class="botplus-action-cell">
                                        <button 
                                            v-if="cart.checkoutUrl"
                                            class="botplus-table-action-btn"
                                            @click="copyCheckoutLink(cart)"
                                        >
                                            {{ copiedCartId === cart.id ? 'Copied!' : 'Copy Checkout Link' }}
                                        </button>
                                        <button 
                                            class="botplus-table-toggle-btn"
                                            @click="toggleExpandCart(cart.id)"
                                        >
                                            {{ expandedCartId === cart.id ? 'Hide' : 'Details' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expanded Details Row -->
                            <tr v-if="expandedCartId === cart.id" class="botplus-expanded-row">
                                <td colspan="7">
                                    <div class="botplus-cart-expanded-content">
                                        <div class="botplus-cart-expanded-header">
                                            <strong>Cart Items & Breakdown</strong>
                                            <span class="text-muted text-xs">Last synced: {{ cart.last_synced_at }}</span>
                                        </div>
                                        <div class="botplus-cart-lines-grid">
                                            <div 
                                                v-for="line in cart.items" 
                                                :key="line.id" 
                                                class="botplus-cart-line-card"
                                            >
                                                <img 
                                                    v-if="line.image" 
                                                    :src="line.image" 
                                                    class="botplus-cart-line-thumb" 
                                                    alt="Product thumbnail"
                                                />
                                                <div class="botplus-cart-line-info">
                                                    <div class="font-medium text-xs">{{ line.name }}</div>
                                                    <div class="text-muted text-xs">
                                                        {{ line.variant_title || 'Default' }}
                                                        <span v-if="line.sku"> · SKU: {{ line.sku }}</span>
                                                    </div>
                                                    <div class="text-xs" style="margin-top: 4px;">
                                                        <strong>{{ line.quantity }}x</strong> @ {{ line.price }} = <strong>{{ line.total }}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="cart.checkoutUrl" class="botplus-cart-deep-link-bar">
                                            <span class="text-xs font-medium">1-Click Restoration Link:</span>
                                            <input type="text" readonly :value="cart.checkoutUrl" class="botplus-deep-link-input" />
                                            <button class="botplus-table-action-btn" @click="copyCheckoutLink(cart)">
                                                {{ copiedCartId === cart.id ? 'Copied Link!' : 'Copy Link' }}
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.botplus-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.botplus-metric-card {
    display: flex;
    flex-direction: column;
}

.botplus-metric-title {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    margin-bottom: 8px;
}

.botplus-metric-value {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 4px;
}

.botplus-metric-value.text-success { color: #059669; }
.botplus-metric-value.text-primary { color: #2563eb; }

.botplus-metric-hint {
    font-size: 12px;
    color: #94a3b8;
}

/* Table Card */
.botplus-table-card {
    padding: 0;
    overflow: hidden;
}

.botplus-table-card .botplus-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 14px;
}

.botplus-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.botplus-search-box {
    position: relative;
}

.botplus-search-input {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    height: 36px;
    padding: 0 12px;
    font-size: 13px;
    width: 240px;
    color: #1e293b;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.botplus-search-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    background: #ffffff;
}

.botplus-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.botplus-card-desc {
    margin: 2px 0 0 0;
    font-size: 12px;
    color: #64748b;
}

.botplus-filter-buttons {
    display: flex;
    gap: 4px;
}

.botplus-filter-buttons button {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    height: 36px;
    padding: 0 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease;
}

.botplus-filter-buttons button:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

.botplus-filter-buttons button.is-active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    font-weight: 600;
}

.botplus-loading-state,
.botplus-empty-state,
.botplus-error-state {
    padding: 48px 20px;
    text-align: center;
    color: #64748b;
}

.botplus-spinner {
    width: 28px;
    height: 28px;
    border: 3px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    margin: 0 auto 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.botplus-empty-icon {
    font-size: 32px;
    margin-bottom: 8px;
}

.botplus-retry-btn {
    margin-left: 10px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 11px;
    cursor: pointer;
}

.botplus-table-wrapper {
    overflow-x: auto;
}

.botplus-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

.botplus-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.botplus-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: top;
}

.botplus-table tr:hover td {
    background: #f8fafc;
}

.font-medium {
    font-weight: 600;
}

.text-muted {
    color: #64748b;
}

.text-xs {
    font-size: 11px;
}

.font-mono {
    font-family: monospace;
}

.botplus-cart-item-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.botplus-cart-status {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    white-space: nowrap;
    border: 1px solid transparent;
}

.botplus-cart-status.in_flight {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}

.botplus-cart-status.abandoned {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
}

.botplus-cart-status.recovered {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}

.botplus-cart-status.recovered {
    background: #ecfdf5;
    color: #047857;
}

.botplus-action-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}

.botplus-table-action-btn,
.botplus-table-toggle-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.botplus-table-action-btn:hover,
.botplus-table-toggle-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.botplus-expanded-row td {
    background: #f8fafc;
    padding: 16px 20px;
}

.botplus-cart-expanded-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.botplus-cart-expanded-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.botplus-cart-lines-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
}

.botplus-cart-line-card {
    display: flex;
    gap: 10px;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 10px;
}

.botplus-cart-line-thumb {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.botplus-cart-line-info {
    min-width: 0;
    flex: 1;
}

.botplus-cart-deep-link-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    margin-top: 6px;
}

.botplus-deep-link-input {
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 11px;
    background: #f1f5f9;
    color: #334155;
}
</style>
