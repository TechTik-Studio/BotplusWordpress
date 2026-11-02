<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { formatCurrencyDisplay } from '@/utils/currency';
import BotPlusMetricGridSkeleton from '@/components/skeletons/BotPlusMetricGridSkeleton.vue';
import BotPlusTableSkeleton from '@/components/skeletons/BotPlusTableSkeleton.vue';
import BotPlusInlineRetry from '@/components/common/BotPlusInlineRetry.vue';
import BotPlusEmptyState from '@/components/common/BotPlusEmptyState.vue';

const data = ref({
    metrics: {
        pipelineValue: '$0.00',
        totalLeads: 0,
        conversionRate: '0.0%',
        wholesaleRequests: 0,
    },
    leads: [],
});
const selectedFilter = ref('all');
const loading = ref(false);
const error = ref('');

const filteredLeads = computed(() => {
    if (selectedFilter.value === 'all') return data.value.leads || [];
    return (data.value.leads || []).filter(l => l.category === selectedFilter.value);
});

function exportLeadsCsv() {
    const leads = filteredLeads.value || [];
    if (!leads.length) return;
    const headers = ['Prospect / Customer', 'Category', 'Location', 'Email', 'Phone', 'Interest Summary', 'Est. Value', 'Intent Score', 'Status', 'Date'];
    const rows = leads.map(l => [
        `"${(l.customerName || '').replace(/"/g, '""')}"`,
        `"${(l.category || '').replace(/"/g, '""')}"`,
        `"${(l.location || '').replace(/"/g, '""')}"`,
        `"${(l.contactEmail || '').replace(/"/g, '""')}"`,
        `"${(l.contactPhone || '').replace(/"/g, '""')}"`,
        `"${(l.interestSummary || '').replace(/"/g, '""')}"`,
        `"${(l.estimatedValue || '').replace(/"/g, '""')}"`,
        `"${(l.intentScore || '').replace(/"/g, '""')}"`,
        `"${(l.status || '').replace(/"/g, '""')}"`,
        `"${(l.date || '').replace(/"/g, '""')}"`,
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `botplus_leads_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

async function fetchLeads() {
    const config = window.BotPlusAdminConfig;
    if (!config || !config.ajaxUrl) return;

    loading.value = true;
    error.value = '';
    try {
        const url = new URL(config.ajaxUrl);
        url.searchParams.append('action', 'botplus_get_leads');
        url.searchParams.append('nonce', config.nonce || '');
        url.searchParams.append('category', selectedFilter.value);

        const res = await fetch(url.toString());
        const json = await res.json();

        if (json.success && json.data) {
            data.value = json.data;
        } else {
            error.value = json.data?.message || json.message || 'Failed to load commercial leads.';
        }
    } catch (err) {
        console.warn('[BotPlus Leads] Load leads notice:', err.message);
        error.value = 'Network error loading commercial leads.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchLeads();
});

watch(selectedFilter, () => {
    fetchLeads();
});
</script>

<template>
    <div class="botplus-page-leads">
        <!-- Top Metrics Cards -->
        <BotPlusMetricGridSkeleton v-if="loading && !data.leads.length" :count="4" />
        <div v-else class="botplus-metric-grid">
            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Pipeline Deal Value</span>
                <div class="botplus-metric-value text-primary">{{ formatCurrencyDisplay(data.metrics.pipelineValue) }}</div>
                <div class="botplus-metric-hint">Estimated intent value from chat</div>
            </div>

            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Captured Inquiries</span>
                <div class="botplus-metric-value">{{ data.metrics.totalLeads }}</div>
                <div class="botplus-metric-hint">High-intent visitors identified by AI</div>
            </div>

            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Conversion Rate</span>
                <div class="botplus-metric-value">{{ data.metrics.conversionRate }}</div>
                <div class="botplus-metric-hint">Leads converted to completed orders</div>
            </div>

            <div class="botplus-card botplus-metric-card accent-primary">
                <span class="botplus-metric-title">Wholesale & B2B Requests</span>
                <div class="botplus-metric-value">{{ data.metrics.wholesaleRequests }}</div>
                <div class="botplus-metric-hint">Bulk supply quotations requested</div>
            </div>
        </div>

        <!-- Leads Table Card -->
        <div class="botplus-card botplus-table-card">
            <div class="botplus-card-header">
                <div>
                    <h3 class="botplus-card-title">Commercial Leads & Opportunities</h3>
                    <p class="botplus-card-desc">High-value purchase inquiries and wholesale opportunities surfaced by BotPlus</p>
                </div>

                <div class="botplus-header-actions" style="display: flex; align-items: center; gap: 12px;">
                    <div class="botplus-filter-buttons">
                        <button 
                            :class="{ 'is-active': selectedFilter === 'all' }" 
                            @click="selectedFilter = 'all'"
                        >
                            All
                        </button>
                        <button 
                            :class="{ 'is-active': selectedFilter === 'wholesale' }" 
                            @click="selectedFilter = 'wholesale'"
                        >
                            Wholesale
                        </button>
                        <button 
                            :class="{ 'is-active': selectedFilter === 'commercial' }" 
                            @click="selectedFilter = 'commercial'"
                        >
                            Commercial
                        </button>
                        <button 
                            :class="{ 'is-active': selectedFilter === 'bulk_order' }" 
                            @click="selectedFilter = 'bulk_order'"
                        >
                            Bulk
                        </button>
                        <button 
                            :class="{ 'is-active': selectedFilter === 'quote' }" 
                            @click="selectedFilter = 'quote'"
                        >
                            Quotes
                        </button>
                    </div>

                    <button
                        type="button"
                        class="botplus-table-action-btn"
                        style="padding: 6px 14px; font-weight: 600;"
                        :disabled="!filteredLeads.length"
                        @click="exportLeadsCsv"
                    >
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div v-if="loading && !filteredLeads.length" style="padding: 16px;">
                <BotPlusTableSkeleton :columns="8" :rows="5" />
            </div>

            <!-- Error State -->
            <div v-else-if="error" style="padding: 24px;">
                <BotPlusInlineRetry :message="error" :busy="loading" @retry="fetchLeads" />
            </div>

            <!-- Empty State -->
            <BotPlusEmptyState
                v-else-if="!filteredLeads.length"
                icon="inbox"
                title="No leads found"
                description="No commercial or wholesale inquiries recorded yet for this filter."
            />

            <div v-else class="botplus-table-wrapper">
                <table class="botplus-table">
                    <thead>
                        <tr>
                            <th>Prospect / Customer</th>
                            <th>Location & Contact</th>
                            <th>Inquiry & Products of Interest</th>
                            <th>Est. Value</th>
                            <th>Intent</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in filteredLeads" :key="lead.id">
                            <td>
                                <strong>{{ lead.customerName }}</strong>
                                <div class="botplus-category-tag">{{ lead.category }}</div>
                            </td>
                            <td>
                                <div class="botplus-contact-info">
                                    <span class="font-medium text-xs">{{ lead.location }}</span>
                                    <span class="text-muted text-xs">{{ lead.contactEmail }}</span>
                                    <span class="text-muted text-xs">{{ lead.contactPhone }}</span>
                                </div>
                            </td>
                            <td class="botplus-interest-col">
                                {{ lead.interestSummary }}
                            </td>
                            <td>
                                <strong>{{ formatCurrencyDisplay(lead.estimatedValue) }}</strong>
                            </td>
                            <td>
                                <span class="bp-status-tag">
                                    <span class="bp-status-dot" :class="lead.intentScore.toLowerCase() === 'high' ? 'is-success' : 'is-warning'"></span>
                                    {{ lead.intentScore }} Intent
                                </span>
                            </td>
                            <td>
                                <span class="bp-status-tag">
                                    <span class="bp-status-dot is-neutral"></span>
                                    {{ lead.status }}
                                </span>
                            </td>
                            <td class="text-muted text-xs">{{ lead.date }}</td>
                            <td>
                                <a 
                                    :href="'mailto:' + lead.contactEmail" 
                                    class="botplus-table-action-btn"
                                >
                                    Email Lead
                                </a>
                            </td>
                        </tr>
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

.botplus-metric-value.text-primary { color: #2563eb; }
.botplus-metric-value.text-success { color: #059669; }

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
    gap: 12px;
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
    vertical-align: middle;
}

.botplus-table tr:hover td {
    background: #f8fafc;
}

.botplus-category-tag {
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

.botplus-contact-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.font-medium { font-weight: 600; }
.text-muted { color: #64748b; }
.text-xs { font-size: 11px; }

.botplus-interest-col {
    max-width: 280px;
    line-height: 1.4;
}

.botplus-intent-badge {
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border: 1px solid transparent;
}

.botplus-intent-badge.high {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}

.botplus-intent-badge.medium {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}

.botplus-status-pill {
    padding: 2px 7px;
    background: #f1f5f9;
    color: #334155;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border: 1px solid #e2e8f0;
}

.botplus-table-action-btn {
    display: inline-block;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 500;
    color: #334155;
    text-decoration: none;
    transition: all 0.15s ease;
}

.botplus-table-action-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
</style>

