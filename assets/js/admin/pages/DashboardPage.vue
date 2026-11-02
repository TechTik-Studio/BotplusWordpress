<script setup>
import { computed, onMounted } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useOAuth } from '@/composables/useOAuth';
import { formatCurrencyDisplay, sanitizeCurrencySymbol } from '@/utils/currency';
import BotPlusMetricGridSkeleton from '@/components/skeletons/BotPlusMetricGridSkeleton.vue';
import BotPlusTableSkeleton from '@/components/skeletons/BotPlusTableSkeleton.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';
import BotPlusEmptyState from '@/components/common/BotPlusEmptyState.vue';

const store = useAdminStore();
const { initiateConnect } = useOAuth();

onMounted(() => {
    if (store.connected) {
        store.fetchDashboardOverview(store.dashboardPeriod, true);
    }
});

function changePeriod(p) {
    store.fetchDashboardOverview(p, true);
}

const data = computed(() => {
    if (store.dashboardOverview) {
        const symbol = sanitizeCurrencySymbol(store.dashboardOverview.currency_symbol || '$');
        const ov = store.dashboardOverview.overview || {};
        const health = store.dashboardOverview.agent_health || {};
        const rawAttModels = store.dashboardOverview.attribution_models || [];

        const attributionModels = rawAttModels.map(m => ({
            ...m,
            revenue_formatted: formatCurrencyDisplay(m.revenue, symbol),
        }));

        return {
            overview: {
                attributedRevenue: ov.attributed_revenue_net ?? 0,
                revenueFormatted: formatCurrencyDisplay(ov.attributed_revenue_net, symbol),
                revenueGrowth: ov.revenue_growth_percentage || '0.0%',
                attributedOrders: ov.attributed_orders_count ?? 0,
                ordersGrowth: ov.orders_growth_percentage || '0.0%',
                activeConversations: ov.active_conversations_count ?? 0,
                resolutionRate: ov.autonomous_resolution_rate || '100%',
                recoveredCarts: ov.recovered_carts_count ?? 0,
                recoveredRevenue: formatCurrencyDisplay(ov.recovered_revenue, symbol),
                // Local store baseline & impact metrics
                storeTotalOrders: ov.store_total_orders ?? 0,
                storeTotalRevenue: formatCurrencyDisplay(ov.store_total_revenue, symbol),
                storeBaselineAov: formatCurrencyDisplay(ov.store_baseline_aov, symbol),
                botAov: formatCurrencyDisplay(ov.bot_aov, symbol),
                aovUplift: ov.aov_uplift_percentage || '0.0%',
                assistedShare: ov.assisted_share_percentage || '0.0%',
            },
            funnel: store.dashboardOverview.funnel || {
                chatsStarted: 0,
                productsRecommended: 0,
                cartsCreated: 0,
                ordersCompleted: 0,
            },
            classificationMix: store.dashboardOverview.classification_mix || {
                sales: { label: 'Sales & Product Interest', count: 0, percentage: '0%' },
                lead: { label: 'High-Intent Leads', count: 0, percentage: '0%' },
                support: { label: 'Customer Support', count: 0, percentage: '0%' },
                inquiry: { label: 'General Inquiries', count: 0, percentage: '0%' },
            },
            attributionModels: attributionModels.length ? attributionModels : [],
            recentAttributedOrders: store.dashboardOverview.recent_attributed_orders || [],
            agentHealth: {
                status: health.status || 'Optimal',
                averageResponseTime: health.average_latency || '1.2s',
                catalogCoverage: health.catalog_coverage || 'Ready for sync',
                syncedCategories: health.synced_categories_count || 0,
                lastCatalogSync: health.last_synced_human || 'Recently',
            },
        };
    }

    return {
        overview: {
            attributedRevenue: 0,
            revenueFormatted: '$0.00',
            revenueGrowth: '0.0%',
            attributedOrders: 0,
            ordersGrowth: '0.0%',
            activeConversations: 0,
            resolutionRate: '100%',
            recoveredCarts: 0,
            recoveredRevenue: '$0.00',
            storeTotalOrders: 0,
            storeTotalRevenue: '$0.00',
            storeBaselineAov: '$0.00',
            botAov: '$0.00',
            aovUplift: '0.0%',
            assistedShare: '0.0%',
        },
        funnel: {
            chatsStarted: 0,
            productsRecommended: 0,
            cartsCreated: 0,
            ordersCompleted: 0,
        },
        classificationMix: {
            sales: { label: 'Sales & Product Interest', count: 0, percentage: '0%' },
            lead: { label: 'High-Intent Leads', count: 0, percentage: '0%' },
            support: { label: 'Customer Support', count: 0, percentage: '0%' },
            inquiry: { label: 'General Inquiries', count: 0, percentage: '0%' },
        },
        attributionModels: [],
        recentAttributedOrders: [],
        agentHealth: {
            status: 'Optimal',
            averageResponseTime: '1.2s',
            catalogCoverage: 'Ready for sync',
            syncedCategories: 0,
            lastCatalogSync: 'Recently',
        },
    };
});

const isLive = computed(() => !!store.dashboardOverview);

const intentItems = computed(() => {
    const mix = data.value?.classificationMix || {};
    return {
        sales: mix.sales || { label: 'Sales & Product Interest', count: 0, percentage: '0%' },
        lead: mix.lead || { label: 'High-Intent Leads', count: 0, percentage: '0%' },
        support: mix.support || { label: 'Customer Support', count: 0, percentage: '0%' },
        inquiry: mix.inquiry || { label: 'General Inquiries', count: 0, percentage: '0%' },
    };
});
</script>

<template>
    <div class="botplus-page-dashboard">
        <!-- Control Bar: Period Selection on Right -->
        <div class="botplus-dashboard-controls">
            <div class="botplus-controls-left">
                <h2 class="botplus-section-title">Commerce Overview</h2>
                <p class="botplus-section-subtitle">Real-time conversational commerce attribution and performance telemetry</p>
            </div>

            <div class="botplus-period-group">
                <button 
                    type="button"
                    :class="{ 'is-active': store.dashboardPeriod === '7d' }"
                    @click="changePeriod('7d')"
                    :disabled="store.dashboardLoading"
                >
                    Last 7 Days
                </button>
                <button 
                    type="button"
                    :class="{ 'is-active': store.dashboardPeriod === '30d' }"
                    @click="changePeriod('30d')"
                    :disabled="store.dashboardLoading"
                >
                    Last 30 Days
                </button>
                <button 
                    type="button"
                    :class="{ 'is-active': store.dashboardPeriod === '90d' }"
                    @click="changePeriod('90d')"
                    :disabled="store.dashboardLoading"
                >
                    Last 90 Days
                </button>
            </div>
        </div>

        <!-- Error Banner with Retry -->
        <BotPlusInlineRetry
            v-if="store.dashboardError"
            title="Failed to load dashboard metrics"
            :message="store.dashboardError"
            :busy="store.dashboardLoading"
            @retry="changePeriod(store.dashboardPeriod)"
        />

        <!-- Disconnected Store Empty State -->
        <BotPlusEmptyState
            v-else-if="!store.connected"
            title="BotPlus is not connected"
            description="Connect your WooCommerce store to BotPlus Cloud to unlock conversational sales, cart recovery, and revenue attribution."
            icon="sparkles"
            action-label="Connect Store"
            @action="initiateConnect"
        />

        <!-- Skeleton Loading State -->
        <template v-else-if="store.dashboardLoading && !isLive">
            <BotPlusMetricGridSkeleton :count="4" />
            <BotPlusTableSkeleton :columns="7" :rows="5" />
        </template>

        <template v-else>
            <!-- Top Metrics Cards: Exactly 1 Row of 4 Core Cards -->
            <div class="botplus-metric-grid">
                <div class="botplus-card botplus-metric-card accent-primary">
                    <div class="botplus-metric-header">
                        <span class="botplus-metric-title">Attributed Revenue</span>
                        <span class="botplus-growth-badge positive">{{ data.overview.revenueGrowth }}</span>
                    </div>
                    <div class="botplus-metric-value text-primary">{{ data.overview.revenueFormatted }}</div>
                    <div class="botplus-metric-hint">Direct sales assisted by BotPlus AI</div>
                    <div class="bp-sparkline-wrap" aria-hidden="true">
                        <svg class="bp-sparkline" viewBox="0 0 100 24" preserveAspectRatio="none">
                            <path d="M0,20 Q20,16 40,18 T70,8 T100,4" fill="none" stroke="#2563eb" stroke-width="2" />
                            <path d="M0,20 Q20,16 40,18 T70,8 T100,4 L100,24 L0,24 Z" fill="rgba(37, 99, 235, 0.08)" />
                        </svg>
                    </div>
                </div>

                <div class="botplus-card botplus-metric-card accent-primary">
                    <div class="botplus-metric-header">
                        <span class="botplus-metric-title">Assisted Orders</span>
                        <span class="botplus-growth-badge positive">{{ data.overview.ordersGrowth }}</span>
                    </div>
                    <div class="botplus-metric-value">{{ data.overview.attributedOrders }}</div>
                    <div class="botplus-metric-hint">Store orders matched to chat sessions</div>
                    <div class="bp-sparkline-wrap" aria-hidden="true">
                        <svg class="bp-sparkline" viewBox="0 0 100 24" preserveAspectRatio="none">
                            <path d="M0,18 Q25,19 50,14 T75,9 T100,5" fill="none" stroke="#2563eb" stroke-width="2" />
                            <path d="M0,18 Q25,19 50,14 T75,9 T100,5 L100,24 L0,24 Z" fill="rgba(37, 99, 235, 0.08)" />
                        </svg>
                    </div>
                </div>

                <div class="botplus-card botplus-metric-card accent-primary">
                    <div class="botplus-metric-header">
                        <span class="botplus-metric-title">Active Conversations</span>
                        <span class="bp-metric-tag"><span class="bp-status-dot is-success"></span>{{ data.overview.resolutionRate }} resolved</span>
                    </div>
                    <div class="botplus-metric-value">{{ data.overview.activeConversations }}</div>
                    <div class="botplus-metric-hint">Customer sessions handled by AI</div>
                </div>

                <div class="botplus-card botplus-metric-card accent-primary">
                    <div class="botplus-metric-header">
                        <span class="botplus-metric-title">Recovered Carts</span>
                        <span class="bp-metric-tag"><span class="bp-status-dot is-success"></span>{{ data.overview.recoveredRevenue }}</span>
                    </div>
                    <div class="botplus-metric-value">{{ data.overview.recoveredCarts }}</div>
                    <div class="botplus-metric-hint">Abandoned carts saved via chat</div>
                </div>
            </div>

        <!-- Middle Section: Funnel & Health Status -->
        <div class="botplus-two-col-grid">
            <!-- Conversational Commerce Funnel -->
            <div class="botplus-card botplus-funnel-card">
                <div class="botplus-card-header">
                    <div>
                        <h3 class="botplus-card-title">Conversational Sales Funnel</h3>
                        <p class="botplus-card-desc">Customer progression from first message to checkout completion</p>
                    </div>
                </div>

                <div class="botplus-funnel-stages">
                    <div v-for="(stage, idx) in data.funnel" :key="idx" class="botplus-funnel-stage">
                        <div class="botplus-stage-meta">
                            <span class="botplus-stage-label">{{ stage.label }}</span>
                            <span class="botplus-stage-count">{{ stage.count }} ({{ stage.percentage }})</span>
                        </div>
                        <div class="botplus-funnel-bar-bg">
                            <div 
                                class="botplus-funnel-bar-fill" 
                                :style="{ width: stage.percentage }"
                                :class="'stage-' + idx"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Health & Capabilities -->
            <div class="botplus-card botplus-health-card">
                <div class="botplus-card-header">
                    <div>
                        <h3 class="botplus-card-title">Agent Telemetry & Health</h3>
                        <p class="botplus-card-desc">Real-time status of your store agent runtime</p>
                    </div>
                    <span class="botplus-health-status-badge">
                        <span class="botplus-pulse-dot"></span>
                        {{ data.agentHealth.status }}
                    </span>
                </div>

                <div class="botplus-health-details">
                    <div class="botplus-health-item">
                        <span class="botplus-health-key">Response Latency</span>
                        <span class="botplus-health-val">{{ data.agentHealth.averageResponseTime }}</span>
                    </div>
                    <div class="botplus-health-item">
                        <span class="botplus-health-key">Catalog Knowledge</span>
                        <span class="botplus-health-val">{{ data.agentHealth.catalogCoverage }}</span>
                    </div>
                    <div class="botplus-health-item">
                        <span class="botplus-health-key">Synced Categories</span>
                        <span class="botplus-health-val">{{ data.agentHealth.syncedCategories }} taxonomies</span>
                    </div>
                    <div class="botplus-health-item">
                        <span class="botplus-health-key">Last Catalog Sync</span>
                        <span class="botplus-health-val">{{ data.agentHealth.lastCatalogSync }}</span>
                    </div>
                </div>

                <div class="botplus-health-actions">
                    <a href="admin.php?page=botplus-inbox" class="botplus-link-btn">View Live Inbox &rarr;</a>
                    <a href="admin.php?page=botplus-capabilities" class="botplus-link-btn">Configure Capabilities &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Intent & Classification Telemetry -->
        <div class="botplus-card botplus-intent-card" v-if="data.classificationMix">
            <div class="botplus-card-header">
                <div>
                    <h3 class="botplus-card-title">Intent & Classification Mix</h3>
                    <p class="botplus-card-desc">Conversational intent detected across customer sessions</p>
                </div>
                <span class="botplus-total-classified-badge" v-if="data.classificationMix.total_classified !== undefined">
                    {{ data.classificationMix.total_classified }} Conversations
                </span>
            </div>

            <div class="botplus-intent-grid">
                <div v-for="(item, key) in intentItems" :key="key" class="botplus-intent-card-item">
                    <div class="botplus-stage-meta">
                        <span class="botplus-intent-label-wrap">
                            <span class="botplus-intent-dot" :class="'dot-' + key"></span>
                            <span class="botplus-stage-label">{{ item.label }}</span>
                        </span>
                        <span class="botplus-stage-count">{{ item.count }} ({{ item.percentage }})</span>
                    </div>
                    <div class="botplus-funnel-bar-bg">
                        <div 
                            class="botplus-intent-bar-fill" 
                            :style="{ width: item.percentage }"
                            :class="'intent-' + key"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversational Attribution Models Breakdown -->
        <div class="botplus-card botplus-attribution-card" v-if="data.attributionModels && data.attributionModels.length">
            <div class="botplus-card-header">
                <div>
                    <h3 class="botplus-card-title">Attribution Breakdown</h3>
                    <p class="botplus-card-desc">Revenue distribution by conversational discovery and checkout touchpoint</p>
                </div>
            </div>
            <div class="botplus-attribution-grid">
                <div v-for="model in data.attributionModels" :key="model.model" class="botplus-attribution-item">
                    <div class="botplus-attribution-badge-row">
                        <span class="botplus-model-tag" :class="model.model">{{ model.label }}</span>
                        <span class="botplus-model-pct">{{ model.percentage }} of sales</span>
                    </div>
                    <div class="botplus-model-revenue">{{ model.revenue_formatted || (model.revenue ? '₨ ' + Number(model.revenue).toLocaleString() : '') }}</div>
                    <div class="botplus-model-orders">{{ model.orders }} orders converted</div>
                    <p class="botplus-model-desc">{{ model.description }}</p>
                </div>
            </div>
        </div>

        <!-- Recent Attributed Orders Table -->
        <div class="botplus-card botplus-table-card">
            <div class="botplus-card-header">
                <div>
                    <h3 class="botplus-card-title">Recent Attributed Orders</h3>
                    <p class="botplus-card-desc">Orders placed following AI recommendations or cart assistance</p>
                </div>
                <a href="admin.php?page=botplus-analytics" class="botplus-link-btn">Full Attribution Report &rarr;</a>
            </div>

            <div class="botplus-table-wrapper">
                <table v-if="data.recentAttributedOrders.length" class="botplus-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Date / Time</th>
                            <th>Products</th>
                            <th>Total</th>
                            <th>Attribution Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in data.recentAttributedOrders" :key="order.id">
                            <td>
                                <strong>{{ order.order_number || order.orderNumber }}</strong>
                            </td>
                            <td>
                                <div class="botplus-user-cell">
                                    <span class="botplus-avatar-placeholder">
                                        {{ (order.customer_name || order.customer?.name || 'G').charAt(0) }}
                                    </span>
                                    <div>
                                        <div class="botplus-user-name">{{ order.customer_name || order.customer?.name }}</div>
                                        <div class="botplus-user-email">{{ order.customer_email || order.customer?.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted">{{ order.date_formatted || order.date }}</td>
                            <td>{{ order.items_summary || order.items }}</td>
                            <td><strong>{{ order.total_formatted || order.total }}</strong></td>
                            <td>
                                <span class="botplus-attribution-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                    </svg>
                                    {{ order.attribution_type || order.attribution }}
                                </span>
                            </td>
                            <td>
                                <span 
                                    class="botplus-order-status" 
                                    :class="(order.status || '').toLowerCase()"
                                >
                                    {{ order.status_label || order.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="botplus-empty-state">
                    <p>No attributed orders recorded in this period yet.</p>
                    <small class="text-muted">Orders placed after interacting with your storefront agent will appear here automatically.</small>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>

<style scoped>
/* Controls Bar */
.botplus-dashboard-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.botplus-controls-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.botplus-section-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.botplus-section-subtitle {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.botplus-period-group {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 3px;
    border-radius: 6px;
}

.botplus-period-group button {
    background: transparent;
    border: none;
    padding: 6px 14px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.botplus-period-group button.is-active {
    background: #ffffff;
    color: #2563eb;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    font-weight: 600;
}

/* Metric Grid - Strict Single Row of 4 Cards */
.botplus-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1080px) {
    .botplus-metric-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .botplus-metric-grid {
        grid-template-columns: 1fr;
    }
}

.bp-sparkline-wrap {
    height: 24px;
    margin-top: 10px;
    overflow: hidden;
}

.bp-sparkline {
    width: 100%;
    height: 100%;
}

.bp-metric-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #334155;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 4px;
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
    justify-content: space-between;
}

.botplus-metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.botplus-metric-title {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
}

.botplus-growth-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}

.botplus-growth-badge.positive {
    background: #ecfdf5;
    color: #059669;
}

.botplus-status-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.botplus-status-pill.highlight {
    background: #eff6ff;
    color: #2563eb;
    border-color: #bfdbfe;
    font-weight: 600;
}

.botplus-metric-value {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 4px;
}

.botplus-metric-hint {
    font-size: 12px;
    color: #94a3b8;
}

.botplus-two-col-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 900px) {
    .botplus-two-col-grid {
        grid-template-columns: 1fr;
    }
}

.botplus-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
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

/* Funnel */
.botplus-funnel-stages {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.botplus-stage-meta {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 5px;
}

.botplus-stage-label {
    font-weight: 500;
    color: #334155;
}

.botplus-stage-count {
    color: #64748b;
    font-weight: 600;
}

.botplus-funnel-bar-bg {
    height: 10px;
    background: #f1f5f9;
    border-radius: 5px;
    overflow: hidden;
}

.botplus-funnel-bar-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.5s ease;
}

.botplus-funnel-bar-fill.stage-0 { background: #2563eb; }
.botplus-funnel-bar-fill.stage-1 { background: rgba(37, 99, 235, 0.78); }
.botplus-funnel-bar-fill.stage-2 { background: rgba(37, 99, 235, 0.58); }
.botplus-funnel-bar-fill.stage-3 { background: rgba(37, 99, 235, 0.40); }

/* Health Card */
.botplus-health-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #059669;
    background: #ecfdf5;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid #a7f3d0;
}

.botplus-health-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.botplus-health-item {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f8fafc;
}

.botplus-health-key {
    color: #64748b;
}

.botplus-health-val {
    font-weight: 600;
    color: #1e293b;
}

.botplus-health-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.botplus-link-btn {
    font-size: 13px;
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}

.botplus-link-btn:hover {
    text-decoration: underline;
}

/* Table */
.botplus-table-card {
    padding: 0;
    overflow: hidden;
}

.botplus-table-card .botplus-card-header {
    padding: 20px 20px 0 20px;
}

.botplus-table-wrapper {
    overflow-x: auto;
    width: 100%;
    min-width: 0;
    -webkit-overflow-scrolling: touch;
}

.botplus-table-wrapper::-webkit-scrollbar {
    height: 6px;
}

.botplus-table-wrapper::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

/* Attribution Breakdown Grid */
.botplus-attribution-card {
    margin-bottom: 20px;
}

.botplus-attribution-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.botplus-attribution-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.botplus-attribution-badge-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.botplus-model-tag {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    background: #e2e8f0;
    color: #334155;
}

.botplus-model-tag.direct { background: #dbeafe; color: #1e40af; }
.botplus-model-tag.assisted { background: #fef3c7; color: #92400e; }
.botplus-model-tag.catalog_discovery { background: #f3e8ff; color: #6b21a8; }

.botplus-model-pct {
    font-size: 11px;
    font-weight: 600;
    color: #059669;
}

.botplus-model-revenue {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.botplus-model-orders {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

.botplus-model-desc {
    margin: 4px 0 0 0;
    font-size: 11.5px;
    color: #64748b;
    line-height: 1.4;
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
    border-top: 1px solid #e2e8f0;
}

.botplus-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.botplus-table tr:hover td {
    background: #f8fafc;
}

.botplus-user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.botplus-avatar-placeholder {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.botplus-user-name {
    font-weight: 600;
}

.botplus-user-email {
    font-size: 11px;
    color: #64748b;
}

.botplus-attribution-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 4px;
    background: #eff6ff;
    color: #1d4ed8;
}

.botplus-order-status {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border: 1px solid transparent;
}

.botplus-order-status.processing {
    background: #fef3c7;
    color: #b45309;
}

.botplus-order-status.completed {
    background: #ecfdf5;
    color: #047857;
}

.botplus-empty-state {
    padding: 30px;
    text-align: center;
}

.botplus-empty-state p {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    margin: 0 0 6px 0;
}

.text-muted {
    color: #64748b;
}

/* Intent Telemetry Card */
.botplus-intent-card {
    margin-bottom: 20px;
}

.botplus-total-classified-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 12px;
}

.botplus-intent-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

@media (max-width: 900px) {
    .botplus-intent-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 500px) {
    .botplus-intent-grid {
        grid-template-columns: 1fr;
    }
}

.botplus-intent-card-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
}

.botplus-intent-label-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}

.botplus-intent-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.botplus-intent-dot.dot-sales { background: #2563eb; }
.botplus-intent-dot.dot-lead { background: rgba(37, 99, 235, 0.75); }
.botplus-intent-dot.dot-support { background: #475569; }
.botplus-intent-dot.dot-inquiry { background: #94a3b8; }

.botplus-intent-bar-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.5s ease;
}

.botplus-intent-bar-fill.intent-sales { background: #2563eb; }
.botplus-intent-bar-fill.intent-lead { background: rgba(37, 99, 235, 0.75); }
.botplus-intent-bar-fill.intent-support { background: #475569; }
.botplus-intent-bar-fill.intent-inquiry { background: #94a3b8; }
</style>
