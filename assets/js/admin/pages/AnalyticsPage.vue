<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAdminStore } from '@/stores/admin';
import BotPlusMetricGridSkeleton from '@/components/skeletons/BotPlusMetricGridSkeleton.vue';
import BotPlusTableSkeleton from '@/components/skeletons/BotPlusTableSkeleton.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';
import BotPlusEmptyState from '@/components/common/BotPlusEmptyState.vue';

const store = useAdminStore();
const timeRange = ref('30d');
const loading = ref(false);
const error = ref(null);
const analytics = ref(null);

async function fetchAnalytics(targetPeriod = '30d') {
    if (!store.connected) return;

    timeRange.value = targetPeriod;
    loading.value = true;
    error.value = null;

    try {
        const ajaxUrl = window.BotPlusAdminConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
        const nonce = window.BotPlusAdminConfig?.nonce || '';
        const params = new URLSearchParams({
            action: 'botplus_get_analytics',
            nonce: nonce,
            period: targetPeriod,
        });

        const response = await fetch(`${ajaxUrl}?${params.toString()}`);
        const json = await response.json();

        if (json.success && json.data) {
            analytics.value = json.data;
        } else {
            error.value = json.data?.message || json.message || 'Failed to retrieve analytics data.';
        }
    } catch (e) {
        error.value = e.message || 'Network error fetching analytics.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchAnalytics(timeRange.value);
});

watch(timeRange, (newRange) => {
    fetchAnalytics(newRange);
});

// Max hourly volume for scaling the 24h heatmap
const maxHourlyVolume = computed(() => {
    const list = analytics.value?.temporal?.hourly_distribution || [];
    const max = Math.max(...list.map(h => h.conversations || 0), 1);
    return max;
});
</script>

<template>
    <div class="botplus-page-analytics">
        <!-- Control Header: Period on the Right -->
        <div class="bp-analytics-header">
            <div>
                <h2 class="bp-analytics-title">Conversational AI Analytics</h2>
                <p class="bp-analytics-subtitle">Attributed commercial impact, autonomous containment, and catalog intelligence</p>
            </div>

            <div class="bp-period-selector">
                <button
                    type="button"
                    class="bp-period-btn"
                    :class="{ 'is-active': timeRange === '7d' }"
                    :disabled="loading"
                    @click="fetchAnalytics('7d')"
                >
                    Last 7 Days
                </button>
                <button
                    type="button"
                    class="bp-period-btn"
                    :class="{ 'is-active': timeRange === '30d' }"
                    :disabled="loading"
                    @click="fetchAnalytics('30d')"
                >
                    Last 30 Days
                </button>
                <button
                    type="button"
                    class="bp-period-btn"
                    :class="{ 'is-active': timeRange === '90d' }"
                    :disabled="loading"
                    @click="fetchAnalytics('90d')"
                >
                    Last 90 Days
                </button>
            </div>
        </div>

        <!-- Inline Error Banner -->
        <BotPlusInlineRetry
            v-if="error"
            title="Failed to load analytics"
            :message="error"
            :busy="loading"
            @retry="fetchAnalytics(timeRange)"
        />

        <!-- Loading Skeletons -->
        <template v-else-if="loading && !analytics">
            <BotPlusMetricGridSkeleton :count="4" />
            <BotPlusTableSkeleton :columns="5" :rows="4" />
        </template>

        <template v-else-if="analytics">
            <!-- 1. Commercial ROI & Attribution Grid (Single Row of 4 Cards) -->
            <div class="bp-metric-grid">
                <div class="bp-metric-card">
                    <div class="bp-metric-header">
                        <span class="bp-metric-label">Attributed Revenue</span>
                        <span class="bp-metric-trend positive">{{ analytics.commercial_roi.revenue_growth }}</span>
                    </div>
                    <div class="bp-metric-value text-primary">{{ analytics.commercial_roi.total_revenue_formatted }}</div>
                    <div class="bp-metric-subtext">Direct & assisted conversational sales</div>
                    <div class="bp-sparkline-wrap" aria-hidden="true">
                        <svg class="bp-sparkline" viewBox="0 0 100 24" preserveAspectRatio="none">
                            <path d="M0,20 Q20,16 40,18 T70,8 T100,4" fill="none" stroke="#2563eb" stroke-width="2" />
                            <path d="M0,20 Q20,16 40,18 T70,8 T100,4 L100,24 L0,24 Z" fill="rgba(37, 99, 235, 0.08)" />
                        </svg>
                    </div>
                </div>

                <div class="bp-metric-card">
                    <div class="bp-metric-header">
                        <span class="bp-metric-label">Assisted Orders</span>
                        <span class="bp-metric-trend positive">{{ analytics.commercial_roi.orders_growth }}</span>
                    </div>
                    <div class="bp-metric-value">{{ analytics.commercial_roi.total_attributed_orders }}</div>
                    <div class="bp-metric-subtext">Completed after customer chat</div>
                    <div class="bp-sparkline-wrap" aria-hidden="true">
                        <svg class="bp-sparkline" viewBox="0 0 100 24" preserveAspectRatio="none">
                            <path d="M0,18 Q25,19 50,14 T75,9 T100,5" fill="none" stroke="#2563eb" stroke-width="2" />
                            <path d="M0,18 Q25,19 50,14 T75,9 T100,5 L100,24 L0,24 Z" fill="rgba(37, 99, 235, 0.08)" />
                        </svg>
                    </div>
                </div>

                <div class="bp-metric-card">
                    <div class="bp-metric-header">
                        <span class="bp-metric-label">Recovered Cart GMV</span>
                        <span class="bp-metric-tag"><span class="bp-status-dot is-success"></span>Active Recovery</span>
                    </div>
                    <div class="bp-metric-value">{{ analytics.commercial_roi.recovered_revenue_formatted }}</div>
                    <div class="bp-metric-subtext">Saved via proactive cart prompts</div>
                </div>

                <div class="bp-metric-card">
                    <div class="bp-metric-header">
                        <span class="bp-metric-label">Conversational AOV Uplift</span>
                        <span class="bp-metric-trend positive">{{ analytics.commercial_roi.aov_uplift_percentage }}</span>
                    </div>
                    <div class="bp-metric-value">{{ analytics.commercial_roi.bot_aov_formatted }}</div>
                    <div class="bp-metric-subtext">vs store baseline ${{ analytics.commercial_roi.store_baseline_aov }}</div>
                </div>
            </div>

            <!-- 2. Deflection & Containment Section -->
            <div class="bp-card bp-mb-24">
                <div class="bp-card-header">
                    <div>
                        <h3 class="bp-card-title">Autonomous Deflection & Support Efficiency</h3>
                        <p class="bp-card-subtitle">Measure how effectively BotPlus contains support inquiries without human escalation</p>
                    </div>
                    <div class="bp-containment-badge">
                        <span class="bp-status-dot is-success"></span>
                        <span>{{ analytics.deflection.containment_rate }} Containment</span>
                    </div>
                </div>

                <div class="bp-deflection-grid">
                    <div class="bp-deflection-stat">
                        <span class="bp-deflection-stat-label">Total Conversations</span>
                        <span class="bp-deflection-stat-value">{{ analytics.deflection.total_conversations }}</span>
                        <span class="bp-deflection-stat-hint">Customer sessions initiated</span>
                    </div>
                    <div class="bp-deflection-stat">
                        <span class="bp-deflection-stat-label">Autonomous Resolutions</span>
                        <span class="bp-deflection-stat-value text-primary">{{ analytics.deflection.contained_conversations }}</span>
                        <span class="bp-deflection-stat-hint">Resolved without staff</span>
                    </div>
                    <div class="bp-deflection-stat">
                        <span class="bp-deflection-stat-label">Human Escalations</span>
                        <span class="bp-deflection-stat-value">{{ analytics.deflection.escalated_conversations }}</span>
                        <span class="bp-deflection-stat-hint">{{ analytics.deflection.escalation_rate }} of conversations</span>
                    </div>
                    <div class="bp-deflection-stat">
                        <span class="bp-deflection-stat-label">Staff Hours Saved</span>
                        <span class="bp-deflection-stat-value">{{ analytics.deflection.support_hours_saved }} hrs</span>
                        <span class="bp-deflection-stat-hint">At ~6 min / manual ticket</span>
                    </div>
                </div>

                <!-- Escalation Reasons Progress Bars -->
                <div class="bp-escalation-reasons" v-if="analytics.deflection.escalation_reasons.length">
                    <h4 class="bp-subheading">Human Escalation Drivers</h4>
                    <div class="bp-reasons-list">
                        <div v-for="reason in analytics.deflection.escalation_reasons" :key="reason.key" class="bp-reason-item">
                            <div class="bp-reason-header">
                                <span class="bp-reason-label">{{ reason.label }}</span>
                                <span class="bp-reason-count">{{ reason.count }} ({{ reason.percentage }})</span>
                            </div>
                            <div class="bp-reason-bar-bg">
                                <div class="bp-reason-bar-fill" :style="{ width: reason.percentage }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. 24-Hour Peak Customer Engagement Heatmap -->
            <div class="bp-card bp-mb-24">
                <div class="bp-card-header">
                    <div>
                        <h3 class="bp-card-title">Peak Customer Engagement Hours</h3>
                        <p class="bp-card-subtitle">Hourly distribution of incoming customer chats throughout the day (00:00 - 23:00)</p>
                    </div>
                </div>

                <div class="bp-hourly-chart">
                    <div
                        v-for="h in analytics.temporal.hourly_distribution"
                        :key="h.hour"
                        class="bp-hourly-col"
                        :title="`${h.hour}:00 - ${h.conversations} conversations`"
                    >
                        <div class="bp-hourly-bar-track">
                            <div
                                class="bp-hourly-bar-fill"
                                :style="{ height: `${Math.max(6, Math.round((h.conversations / maxHourlyVolume) * 100))}%` }"
                            ></div>
                        </div>
                        <span class="bp-hourly-label">{{ String(h.hour).padStart(2, '0') }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. Top Converting Products & Knowledge Gaps -->
            <div class="bp-two-col-grid">
                <!-- Top Products Table -->
                <div class="bp-card">
                    <div class="bp-card-header">
                        <div>
                            <h3 class="bp-card-title">Top Converting Products</h3>
                            <p class="bp-card-subtitle">Catalog items most converted via agent recommendations</p>
                        </div>
                    </div>

                    <div v-if="analytics.top_converting_products.length" class="bp-table-wrapper">
                        <table class="bp-table">
                            <thead>
                                <tr>
                                    <th>Product Title</th>
                                    <th>Conversions</th>
                                    <th>Attributed GMV</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="prod in analytics.top_converting_products" :key="prod.name">
                                    <td><strong>{{ prod.name }}</strong></td>
                                    <td>{{ prod.conversions }} units</td>
                                    <td class="text-primary font-semibold">{{ prod.revenue_formatted }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <BotPlusEmptyState
                        v-else
                        icon="cart"
                        title="No converted products yet"
                        description="Products recommended by your AI agent will appear here once orders are placed."
                    />
                </div>

                <!-- Knowledge Gaps Module -->
                <div class="bp-card">
                    <div class="bp-card-header">
                        <div>
                            <h3 class="bp-card-title">Catalog & Policy Knowledge Gaps</h3>
                            <p class="bp-card-subtitle">Inquiries where the AI lacked direct answers, indicating missing documentation</p>
                        </div>
                    </div>

                    <div v-if="analytics.knowledge_gaps.length" class="bp-table-wrapper">
                        <table class="bp-table">
                            <thead>
                                <tr>
                                    <th>Inquiry Topic</th>
                                    <th>Frequency</th>
                                    <th>Suggested Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="gap in analytics.knowledge_gaps" :key="gap.topic">
                                    <td>
                                        <div class="bp-gap-topic">
                                            <span class="bp-status-dot is-warning"></span>
                                            <span>{{ gap.topic }}</span>
                                        </div>
                                    </td>
                                    <td>{{ gap.occurrences }} inquiries</td>
                                    <td class="text-secondary">{{ gap.suggested_action }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <BotPlusEmptyState
                        v-else
                        icon="sparkles"
                        title="No knowledge gaps detected"
                        description="Your catalog and store policies currently cover all customer inquiry topics."
                    />
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.botplus-page-analytics {
    width: 100%;
    box-sizing: border-box;
}

.bp-mb-24 {
    margin-bottom: 24px;
}

/* Control Header */
.bp-analytics-header {
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

.bp-analytics-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.bp-analytics-subtitle {
    margin: 3px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

.bp-period-selector {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 3px;
    border-radius: 6px;
}

.bp-period-btn {
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

.bp-period-btn.is-active {
    background: #ffffff;
    color: #2563eb;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    font-weight: 600;
}

/* Single-Row Metric Grid */
.bp-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1080px) {
    .bp-metric-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .bp-metric-grid {
        grid-template-columns: 1fr;
    }
}

.bp-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-top: 3px solid #2563eb;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.bp-metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.bp-metric-label {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
}

.bp-metric-trend {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}

.bp-metric-trend.positive {
    background: #ecfdf5;
    color: #059669;
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

.bp-metric-value {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 4px;
}

.bp-metric-subtext {
    font-size: 12px;
    color: #94a3b8;
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

/* Card General */
.bp-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.bp-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.bp-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.bp-card-subtitle {
    margin: 3px 0 0 0;
    font-size: 12.5px;
    color: #64748b;
}

.bp-containment-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

/* Deflection Grid */
.bp-deflection-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 900px) {
    .bp-deflection-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.bp-deflection-stat {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bp-deflection-stat-label {
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
}

.bp-deflection-stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.bp-deflection-stat-hint {
    font-size: 11px;
    color: #94a3b8;
}

/* Escalation Reasons */
.bp-subheading {
    margin: 0 0 12px 0;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.bp-reasons-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.bp-reason-header {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    margin-bottom: 4px;
}

.bp-reason-label {
    color: #334155;
    font-weight: 500;
}

.bp-reason-count {
    color: #64748b;
    font-weight: 600;
}

.bp-reason-bar-bg {
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
}

.bp-reason-bar-fill {
    height: 100%;
    background: #2563eb;
    border-radius: 4px;
    transition: width 0.4s ease;
}

/* 24-Hour Hourly Chart */
.bp-hourly-chart {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 120px;
    padding-top: 10px;
}

.bp-hourly-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    gap: 6px;
}

.bp-hourly-bar-track {
    width: 100%;
    max-width: 18px;
    height: 90px;
    background: #f1f5f9;
    border-radius: 3px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
}

.bp-hourly-bar-fill {
    width: 100%;
    background: #2563eb;
    border-radius: 3px 3px 0 0;
    transition: height 0.4s ease;
}

.bp-hourly-label {
    font-size: 10.5px;
    color: #94a3b8;
    font-weight: 500;
}

/* Two Column Layout */
.bp-two-col-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .bp-two-col-grid {
        grid-template-columns: 1fr;
    }
}

/* Tables */
.bp-table-wrapper {
    overflow-x: auto;
    width: 100%;
}

.bp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

.bp-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    padding: 10px 14px;
    border-bottom: 1px solid #e2e8f0;
    border-top: 1px solid #e2e8f0;
    font-size: 12px;
}

.bp-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.bp-table tr:hover td {
    background: #f8fafc;
}

.bp-gap-topic {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #0f172a;
}

.text-primary {
    color: #2563eb;
}

.text-secondary {
    color: #64748b;
}

.font-semibold {
    font-weight: 600;
}
</style>
