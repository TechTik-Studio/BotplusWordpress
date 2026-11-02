<script setup>
import { ref, computed, watch } from 'vue';
import { parseMarkdown } from '../utils/markdown';

const props = defineProps({
    content: {
        type: String,
        default: '',
    },
    sender: {
        type: String,
        default: 'bot', // 'user' | 'bot' | 'human_operator' | 'system'
    },
    uiPayload: {
        type: Object,
        default: null,
    },
    metadata: {
        type: Object,
        default: () => ({}),
    },
});

const activeImageIndex = ref(0);

// Helper: Format price with currency
function formatPrice(price, currency = 'USD') {
    if (price === null || price === undefined || price === '') return '';
    const num = Number(price);
    if (Number.isNaN(num)) return String(price);
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency || 'USD',
        }).format(num);
    } catch (_e) {
        return `${currency || '$'} ${num.toFixed(2)}`;
    }
}

// Helper: Normalize status label
function normalizeStatusLabel(val) {
    if (!val) return 'Pending';
    return String(val)
        .replace(/[_-]/g, ' ')
        .toLowerCase()
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

// Plugin Inbox Fix: Unwrap media-proxy to the direct store upload URL
function resolveSafeImageUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';

    // If it's a media-proxy URL, extract the direct target url parameter
    if (trimmed.includes('media-proxy')) {
        try {
            const parsed = new URL(trimmed, 'http://localhost');
            const targetUrl = parsed.searchParams.get('url');
            if (targetUrl) {
                return decodeURIComponent(targetUrl);
            }
        } catch (_e) {
            const match = trimmed.match(/[?&]url=([^&]+)/);
            if (match && match[1]) {
                return decodeURIComponent(match[1]);
            }
        }
    }

    return trimmed;
}

// Parse markdown and extract standalone images for carousel
const parsedContent = computed(() => {
    const rawHtml = parseMarkdown(props.content);

    if (typeof DOMParser === 'undefined') {
        return { textHtml: rawHtml, images: [] };
    }

    try {
        const doc = new DOMParser().parseFromString(`<div>${rawHtml}</div>`, 'text/html');
        const root = doc.body.firstElementChild || doc.body;

        const imgElements = Array.from(root.querySelectorAll('img'));
        const images = imgElements.map((img) => {
            const parent = img.parentElement;
            const linkHref = parent?.tagName === 'A' ? parent.getAttribute('href') : null;
            const rawSrc = img.getAttribute('src') || '';
            const safeSrc = resolveSafeImageUrl(rawSrc) || rawSrc;

            return {
                src: safeSrc,
                alt: img.getAttribute('alt') || 'Attachment',
                href: linkHref ? resolveSafeImageUrl(linkHref) : safeSrc,
            };
        });

        // Strip standalone images from text body to avoid duplicates
        imgElements.forEach((img) => {
            const parent = img.parentElement;
            if (parent?.tagName === 'A' && parent.childNodes.length === 1) {
                parent.remove();
                return;
            }
            img.remove();
        });

        // Clean empty wrappers
        Array.from(root.querySelectorAll('p, div')).forEach((node) => {
            if (!node.textContent?.trim() && node.children.length === 0) {
                node.remove();
            }
        });

        return {
            textHtml: root.innerHTML.trim(),
            images,
        };
    } catch (_err) {
        return { textHtml: rawHtml, images: [] };
    }
});

const hasMultipleImages = computed(() => parsedContent.value.images.length > 1);
const currentImage = computed(() => parsedContent.value.images[activeImageIndex.value] || null);

watch(
    () => parsedContent.value.images.length,
    (len) => {
        if (!len || activeImageIndex.value >= len) {
            activeImageIndex.value = 0;
        }
    }
);

function previousImage() {
    const len = parsedContent.value.images.length;
    if (len <= 1) return;
    activeImageIndex.value = activeImageIndex.value === 0 ? len - 1 : activeImageIndex.value - 1;
}

function nextImage() {
    const len = parsedContent.value.images.length;
    if (len <= 1) return;
    activeImageIndex.value = activeImageIndex.value === len - 1 ? 0 : activeImageIndex.value + 1;
}

// -------------------------------------------------------------
// WordPress / WooCommerce Structured Payloads
// -------------------------------------------------------------
const payloadType = computed(() => props.uiPayload?.type || '');

// Order Tracking Payload
const trackedOrder = computed(() => {
    if (payloadType.value === 'wordpress_order_tracking' || payloadType.value === 'shopify_order_tracking') {
        return props.uiPayload?.order || null;
    }
    return null;
});

const orderTimeline = [
    { key: 'ordered', label: 'Ordered' },
    { key: 'processing', label: 'Processing' },
    { key: 'shipped', label: 'Shipped' },
    { key: 'delivered', label: 'Delivered' },
];

const orderStageConfig = computed(() => {
    const status = String(trackedOrder.value?.fulfillment_status || trackedOrder.value?.status || '').toLowerCase();

    if (['fulfilled', 'delivered', 'completed'].includes(status)) {
        return { activeIndex: 3, badgeClass: 'is-delivered', label: 'Delivered' };
    }
    if (['partial', 'in_transit', 'open', 'shipped'].includes(status)) {
        return { activeIndex: 2, badgeClass: 'is-shipped', label: 'In Transit' };
    }
    if (['unfulfilled', 'scheduled', 'on_hold', 'on-hold', 'pending_fulfillment', 'processing'].includes(status)) {
        return { activeIndex: 1, badgeClass: 'is-processing', label: 'Processing' };
    }
    if (['cancelled', 'canceled', 'failed', 'refunded'].includes(status)) {
        return { activeIndex: 0, badgeClass: 'is-cancelled', label: 'Cancelled' };
    }
    return { activeIndex: 0, badgeClass: 'is-pending', label: 'Ordered' };
});

const orderProgressWidth = computed(() => {
    return `${Math.min(100, Math.max(0, (orderStageConfig.value.activeIndex / (orderTimeline.length - 1)) * 100))}%`;
});

// Cart Snapshot Payload
const cartData = computed(() => {
    if (payloadType.value === 'wordpress_cart' || payloadType.value === 'shopify_cart') {
        return props.uiPayload?.cart || null;
    }
    return null;
});

// Product Card / Catalog Payloads
const productsList = computed(() => {
    if (payloadType.value === 'wordpress_catalog' || payloadType.value === 'shopify_catalog') {
        return Array.isArray(props.uiPayload?.products) ? props.uiPayload.products : [];
    }
    if ((payloadType.value === 'wordpress_product' || payloadType.value === 'shopify_product') && props.uiPayload?.product) {
        return [props.uiPayload.product];
    }
    if (props.metadata?.product_card) {
        return [props.metadata.product_card];
    }
    return [];
});

// Tool Call Fallback
const toolCall = computed(() => {
    return props.metadata?.tool_call || props.metadata?.tool || null;
});

// Activity Statuses
const activityStatuses = computed(() => {
    const list = props.metadata?.activity_statuses || props.uiPayload?.activity_statuses || [];
    return Array.isArray(list) ? list : [];
});
</script>

<template>
    <div class="botplus-message-content">
        <!-- 1. Formatted Markdown Text Body -->
        <div 
            v-if="parsedContent.textHtml" 
            class="botplus-markdown-body" 
            v-html="parsedContent.textHtml"
        />

        <!-- 2. Single Image Display -->
        <div v-if="parsedContent.images.length === 1" class="botplus-image-single-wrap">
            <a :href="parsedContent.images[0].href" target="_blank" rel="noopener noreferrer">
                <img :src="parsedContent.images[0].src" :alt="parsedContent.images[0].alt" class="botplus-chat-img" />
            </a>
        </div>

        <!-- 2. Multi-Image Interactive Carousel -->
        <div v-else-if="hasMultipleImages" class="botplus-image-carousel">
            <div class="botplus-carousel-slide">
                <a :href="currentImage?.href" target="_blank" rel="noopener noreferrer">
                    <img :src="currentImage?.src" :alt="currentImage?.alt" class="botplus-chat-img" />
                </a>
                <button type="button" @click="previousImage" class="botplus-carousel-btn prev" aria-label="Previous image">‹</button>
                <button type="button" @click="nextImage" class="botplus-carousel-btn next" aria-label="Next image">›</button>
                <div class="botplus-carousel-badge">{{ activeImageIndex + 1 }} / {{ parsedContent.images.length }}</div>
            </div>
            <div class="botplus-carousel-dots">
                <button 
                    v-for="(_, idx) in parsedContent.images" 
                    :key="idx" 
                    type="button" 
                    class="botplus-carousel-dot" 
                    :class="{ 'is-active': idx === activeImageIndex }"
                    @click="activeImageIndex = idx"
                />
            </div>
        </div>

        <!-- 3. WooCommerce Order Tracking Card -->
        <div v-if="trackedOrder" class="botplus-card botplus-order-tracking-card">
            <div class="botplus-card-header">
                <div>
                    <span class="botplus-card-kicker">Order Tracking</span>
                    <h4 class="botplus-order-title">
                        {{ trackedOrder.name || ('#' + (trackedOrder.order_number || trackedOrder.id)) }}
                    </h4>
                </div>
                <div class="botplus-order-total-col">
                    <span class="botplus-order-total-amount">
                        {{ formatPrice(trackedOrder.total_amount, trackedOrder.currency) }}
                    </span>
                    <span v-if="trackedOrder.placed_at || trackedOrder.created_at" class="botplus-order-date">
                        {{ new Date(trackedOrder.placed_at || trackedOrder.created_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) }}
                    </span>
                </div>
            </div>

            <!-- 4-Stage Progress Timeline -->
            <div class="botplus-order-timeline-wrap">
                <div class="botplus-order-timeline-bar">
                    <div class="botplus-order-timeline-progress" :style="{ width: orderProgressWidth }"></div>
                </div>
                <div class="botplus-order-timeline-steps">
                    <div 
                        v-for="(step, idx) in orderTimeline" 
                        :key="step.key"
                        class="botplus-timeline-step"
                        :class="{ 
                            'is-done': idx < orderStageConfig.activeIndex,
                            'is-current': idx === orderStageConfig.activeIndex 
                        }"
                    >
                        <div class="botplus-step-dot">
                            <span v-if="idx <= orderStageConfig.activeIndex">✓</span>
                            <span v-else>{{ idx + 1 }}</span>
                        </div>
                        <span class="botplus-step-label">{{ step.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Badges & Carrier Info -->
            <div class="botplus-order-status-row">
                <span class="botplus-order-pill" :class="orderStageConfig.badgeClass">
                    {{ orderStageConfig.label }}
                </span>
                <span v-if="trackedOrder.financial_status" class="botplus-order-pill is-payment">
                    {{ normalizeStatusLabel(trackedOrder.financial_status) }}
                </span>
                <div v-if="trackedOrder.tracking?.number" class="botplus-order-tracking-chip">
                    <span class="botplus-chip-carrier">{{ trackedOrder.tracking.carrier || trackedOrder.tracking.company || 'Carrier' }}:</span>
                    <span class="botplus-chip-num">{{ trackedOrder.tracking.number }}</span>
                </div>
            </div>

            <!-- Line Items Preview -->
            <div v-if="trackedOrder.line_items?.length" class="botplus-order-items-list">
                <div v-for="(item, idx) in trackedOrder.line_items" :key="idx" class="botplus-order-item-row">
                    <img v-if="item.image_url" :src="resolveSafeImageUrl(item.image_url)" alt="Product" class="botplus-item-thumb" />
                    <div class="botplus-item-info">
                        <div class="botplus-item-title">{{ item.title || item.name || 'Product' }}</div>
                        <div class="botplus-item-meta">
                            Qty: {{ item.quantity }} • {{ formatPrice(item.price || item.total, trackedOrder.currency) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. WooCommerce Active Cart Card -->
        <div v-else-if="cartData" class="botplus-card botplus-cart-card">
            <div class="botplus-card-header">
                <div>
                    <span class="botplus-card-kicker">Shopping Cart</span>
                    <h4 class="botplus-order-title">
                        {{ cartData.item_count }} {{ cartData.item_count === 1 ? 'item' : 'items' }}
                    </h4>
                </div>
                <div class="botplus-cart-total-badge">
                    Subtotal: {{ formatPrice(cartData.subtotal_amount, cartData.currency) }}
                </div>
            </div>

            <div v-if="cartData.lines?.length" class="botplus-cart-lines-list">
                <div v-for="line in cartData.lines" :key="line.line_key || line.product_id" class="botplus-cart-line-row">
                    <img v-if="line.image_url" :src="resolveSafeImageUrl(line.image_url)" alt="Product" class="botplus-item-thumb" />
                    <div class="botplus-cart-line-info">
                        <div class="botplus-item-title">{{ line.title || line.product_title }}</div>
                        <div class="botplus-item-meta">
                            <span v-if="line.variant_title" class="botplus-variant-meta">{{ line.variant_title }} • </span>
                            Qty: {{ line.quantity }} • {{ formatPrice(line.line_total || line.price, cartData.currency) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Product Preview Card(s) -->
        <div v-else-if="productsList.length" class="botplus-product-cards-wrap">
            <div v-for="prod in productsList" :key="prod.id || prod.title" class="botplus-product-item-card">
                <img v-if="prod.image || prod.image_url" :src="resolveSafeImageUrl(prod.image || prod.image_url)" :alt="prod.title || prod.name" class="botplus-product-item-thumb" />
                <div class="botplus-product-item-details">
                    <span class="botplus-product-item-title">{{ prod.title || prod.name }}</span>
                    <span class="botplus-product-item-price">{{ prod.price_formatted || formatPrice(prod.price) }}</span>
                    <a v-if="prod.url || prod.permalink" :href="prod.url || prod.permalink" target="_blank" rel="noopener noreferrer" class="botplus-product-item-link">
                        View Product →
                    </a>
                </div>
            </div>
        </div>

        <!-- 6. Tool Execution Fallback -->
        <div v-else-if="toolCall" class="botplus-tool-fallback-box">
            <div class="botplus-tool-fallback-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Executed Tool: <code>{{ toolCall.name }}</code>
            </div>
            <div v-if="toolCall.result" class="botplus-tool-fallback-body">
                <div v-if="toolCall.result.order_id"><strong>Order:</strong> #{{ toolCall.result.order_id }} ({{ toolCall.result.status }})</div>
                <div v-if="toolCall.result.items_summary"><strong>Items:</strong> {{ toolCall.result.items_summary }}</div>
                <div v-if="toolCall.result.tracking_note"><strong>Tracking:</strong> {{ toolCall.result.tracking_note }}</div>
            </div>
        </div>

        <!-- 7. Activity Status Chips -->
        <div v-if="activityStatuses.length" class="botplus-activity-strip">
            <span v-for="(act, idx) in activityStatuses" :key="idx" class="botplus-activity-pill" :class="act.status">
                <span class="botplus-activity-icon">{{ act.status === 'completed' ? '✓' : (act.status === 'failed' ? '✕' : '•') }}</span>
                {{ act.label }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.botplus-message-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

/* Markdown typography */
.botplus-markdown-body {
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.botplus-markdown-body :deep(p) { margin: 0 0 6px 0; }
.botplus-markdown-body :deep(p:last-child) { margin-bottom: 0; }
.botplus-markdown-body :deep(strong) { font-weight: 700; }
.botplus-markdown-body :deep(em) { font-style: italic; }
.botplus-markdown-body :deep(ul), .botplus-markdown-body :deep(ol) { margin: 4px 0 6px 0; padding-left: 18px; }
.botplus-markdown-body :deep(li) { margin-bottom: 2px; }
.botplus-markdown-body :deep(a) { color: #2563eb; text-decoration: underline; text-underline-offset: 2px; }
.botplus-markdown-body :deep(code) { background: rgba(0, 0, 0, 0.06); padding: 1px 4px; border-radius: 3px; font-size: 11.5px; font-family: ui-monospace, monospace; }
.botplus-markdown-body :deep(pre) { background: #0f172a; color: #f8fafc; padding: 8px 10px; border-radius: 5px; overflow-x: auto; margin: 6px 0; font-size: 12px; }
.botplus-markdown-body :deep(pre code) { background: transparent; padding: 0; color: inherit; }
.botplus-markdown-body :deep(blockquote) { margin: 4px 0; padding-left: 10px; border-left: 3px solid #cbd5e1; color: #64748b; font-style: italic; }

/* Scrollable Tables in Markdown */
.botplus-markdown-body :deep(.botplus-table-scroll-wrap) {
    overflow-x: auto;
    margin: 6px 0;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}
.botplus-markdown-body :deep(table.botplus-table-content) { width: 100%; border-collapse: collapse; font-size: 11.5px; white-space: nowrap; }
.botplus-markdown-body :deep(th), .botplus-markdown-body :deep(td) { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
.botplus-markdown-body :deep(th) { background: #f8fafc; font-weight: 600; }

/* Image Display & Carousel */
.botplus-image-single-wrap { margin-top: 4px; }
.botplus-chat-img { max-width: 100%; max-height: 220px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: block; }
.botplus-image-carousel { display: flex; flex-direction: column; align-items: center; gap: 5px; margin-top: 4px; }
.botplus-carousel-slide { position: relative; max-width: 100%; }
.botplus-carousel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(15, 23, 42, 0.75); color: #fff; border: none; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; line-height: 1; }
.botplus-carousel-btn.prev { left: 5px; }
.botplus-carousel-btn.next { right: 5px; }
.botplus-carousel-badge { position: absolute; bottom: 5px; right: 5px; background: rgba(0, 0, 0, 0.7); color: #fff; font-size: 10px; font-weight: 600; padding: 2px 5px; border-radius: 3px; }
.botplus-carousel-dots { display: flex; gap: 4px; }
.botplus-carousel-dot { width: 6px; height: 6px; border-radius: 50%; background: #cbd5e1; border: none; padding: 0; cursor: pointer; }
.botplus-carousel-dot.is-active { background: #2563eb; width: 12px; border-radius: 3px; }

/* Structured Cards Base */
.botplus-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; color: #1e293b; }
.botplus-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.botplus-card-kicker { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block; }
.botplus-order-title { margin: 2px 0 0 0; font-size: 14px; font-weight: 700; color: #0f172a; }
.botplus-order-total-col { text-align: right; }
.botplus-order-total-amount { font-size: 14px; font-weight: 700; color: #0f172a; display: block; }
.botplus-order-date { font-size: 10.5px; color: #94a3b8; }

/* Order 4-Step Timeline */
.botplus-order-timeline-wrap { margin: 10px 0; position: relative; padding: 0 8px; }
.botplus-order-timeline-bar { position: absolute; top: 9px; left: 16px; right: 16px; height: 2px; background: #e2e8f0; z-index: 1; }
.botplus-order-timeline-progress { height: 100%; background: #2563eb; transition: width 0.3s ease; }
.botplus-order-timeline-steps { display: flex; justify-content: space-between; position: relative; z-index: 2; }
.botplus-timeline-step { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.botplus-step-dot { width: 18px; height: 18px; border-radius: 50%; background: #ffffff; border: 2px solid #cbd5e1; color: #94a3b8; font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.botplus-timeline-step.is-done .botplus-step-dot { background: #2563eb; border-color: #2563eb; color: #ffffff; }
.botplus-timeline-step.is-current .botplus-step-dot { background: #eff6ff; border-color: #2563eb; color: #2563eb; }
.botplus-step-label { font-size: 9.5px; font-weight: 600; color: #64748b; }
.botplus-timeline-step.is-current .botplus-step-label { color: #2563eb; font-weight: 700; }

/* Status Pills */
.botplus-order-status-row { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 6px; }
.botplus-order-pill { font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 3px; text-transform: uppercase; }
.botplus-order-pill.is-delivered { background: #dcfce7; color: #166534; }
.botplus-order-pill.is-shipped { background: #e0f2fe; color: #0369a1; }
.botplus-order-pill.is-processing { background: #fef3c7; color: #92400e; }
.botplus-order-pill.is-cancelled { background: #fee2e2; color: #991b1b; }
.botplus-order-pill.is-pending { background: #f1f5f9; color: #475569; }
.botplus-order-pill.is-payment { background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; }
.botplus-order-tracking-chip { font-size: 10.5px; background: #f1f5f9; padding: 2px 6px; border-radius: 3px; color: #334155; }
.botplus-chip-carrier { font-weight: 600; margin-right: 3px; }
.botplus-chip-num { font-family: ui-monospace, monospace; }

/* Line Items */
.botplus-order-items-list, .botplus-cart-lines-list { margin-top: 8px; border-top: 1px solid #f1f5f9; padding-top: 6px; display: flex; flex-direction: column; gap: 5px; }
.botplus-order-item-row, .botplus-cart-line-row { display: flex; align-items: center; gap: 8px; }
.botplus-item-thumb { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0; }
.botplus-item-info, .botplus-cart-line-info { min-width: 0; flex: 1; }
.botplus-item-title { font-size: 11.5px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.botplus-item-meta { font-size: 10.5px; color: #64748b; }
.botplus-cart-total-badge { background: #ecfdf5; color: #065f46; font-weight: 700; font-size: 11.5px; padding: 3px 7px; border-radius: 4px; }

/* Product Cards */
.botplus-product-cards-wrap { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
.botplus-product-item-card { display: flex; align-items: center; gap: 8px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 8px; }
.botplus-product-item-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0; }
.botplus-product-item-details { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 1px; }
.botplus-product-item-title { font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.botplus-product-item-price { font-size: 11.5px; font-weight: 700; color: #2563eb; }
.botplus-product-item-link { font-size: 10.5px; font-weight: 600; color: #475569; text-decoration: none; }
.botplus-product-item-link:hover { color: #2563eb; }

/* Tool Call & Activity Strip */
.botplus-tool-fallback-box { padding: 6px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 11px; color: #334155; }
.botplus-tool-fallback-title { display: flex; align-items: center; gap: 4px; font-weight: 600; margin-bottom: 2px; }
.botplus-activity-strip { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.botplus-activity-pill { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 500; padding: 1px 5px; border-radius: 3px; background: #f1f5f9; color: #475569; }
.botplus-activity-pill.completed { background: #f0fdf4; color: #166534; }
.botplus-activity-pill.failed { background: #fef2f2; color: #991b1b; }
</style>
