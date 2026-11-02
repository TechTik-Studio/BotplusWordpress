<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useInboxStore } from '../stores/inbox';
import ChatMessageContent from '../components/ChatMessageContent.vue';
import BotPlusConversationSkeleton from '@/components/skeletons/BotPlusConversationSkeleton.vue';
import BotPlusThreadSkeleton from '@/components/skeletons/BotPlusThreadSkeleton.vue';

const inboxStore = useInboxStore();
const draftReply = ref('');
const messagesContainer = ref(null);
const draftNote = ref('');
const noteType = ref('internal');
const localNotes = ref([]);
const activeSideTab = ref('profile'); // 'profile' | 'cart' | 'notes'
const showSidepanel = ref(typeof window !== 'undefined' ? window.innerWidth >= 1200 : true);

onMounted(() => {
    inboxStore.initInbox();
});

onUnmounted(() => {
    inboxStore.teardown();
});

const currentConv = computed(() => inboxStore.currentConversation);

const hasCase = computed(() => Boolean(currentConv.value?.helpdesk?.case_id));

const isAiAutonomous = computed(() => !hasCase.value);

const isWaitingSupport = computed(() => {
    if (!hasCase.value) return false;
    const s = currentConv.value?.status;
    return s === 'waiting' || s === 'needs_intervention';
});

const isResolvedCase = computed(() => {
    if (!hasCase.value) return false;
    const s = currentConv.value?.status;
    return s === 'resolved' || s === 'closed';
});

const isActiveCase = computed(() => {
    if (!hasCase.value) return false;
    return !isWaitingSupport.value && !isResolvedCase.value;
});

function toggleSidepanel() {
    showSidepanel.value = !showSidepanel.value;
}

function handleBackToList() {
    inboxStore.selectedConvId = null;
}

// Auto-scroll messages stream on new message
watch(() => inboxStore.messages.length, () => {
    scrollToBottom();
});

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function handleSelectConversation(id) {
    inboxStore.selectConversation(id);
    scrollToBottom();
}

async function handleSendReply() {
    if (!draftReply.value.trim() || inboxStore.sendingReply || !currentConv.value) return;
    const text = draftReply.value;
    draftReply.value = '';

    if (isAiAutonomous.value || isWaitingSupport.value) {
        await inboxStore.claimConversation(currentConv.value.id);
    } else if (isResolvedCase.value && currentConv.value?.helpdesk?.case_id) {
        await inboxStore.updateCaseStatus(currentConv.value.helpdesk.case_id, 'active');
    }

    await inboxStore.sendReply(text);
    scrollToBottom();
}

async function handleClaim() {
    if (!currentConv.value) return;
    const ok = await inboxStore.claimConversation(currentConv.value.id);
    if (ok) {
        scrollToBottom();
    }
}

async function handleRelease() {
    if (!currentConv.value) return;
    const ok = await inboxStore.releaseConversation(currentConv.value.id);
    if (ok) {
        scrollToBottom();
    }
}

async function handleReopen() {
    if (!currentConv.value?.helpdesk?.case_id) return;
    const ok = await inboxStore.reopenConversation(currentConv.value.helpdesk.case_id);
    if (ok) {
        scrollToBottom();
    }
}

function handleStatusChange(newStatus) {
    if (!currentConv.value?.helpdesk?.case_id) return;
    inboxStore.updateCaseStatus(currentConv.value.helpdesk.case_id, newStatus);
}

function toggleResolved() {
    if (!currentConv.value?.helpdesk?.case_id) return;
    const isCurrentlyResolved = currentConv.value.status === 'resolved' || currentConv.value.status === 'closed';
    const targetStatus = isCurrentlyResolved ? 'open' : 'resolved';
    inboxStore.updateCaseStatus(currentConv.value.helpdesk.case_id, targetStatus);
}

async function handleAddNote() {
    if (!draftNote.value.trim() || !currentConv.value?.helpdesk?.case_id) return;
    const body = draftNote.value.trim();
    const type = noteType.value;
    const ok = await inboxStore.addCaseNote(currentConv.value.helpdesk.case_id, body, type);
    if (ok) {
        localNotes.value.unshift({
            id: 'note-' + Date.now(),
            body,
            note_type: type,
            created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            author: 'Store Staff',
        });
        draftNote.value = '';
    }
}

function formatOrderTotal(ord) {
    if (!ord) return '$0.00';
    if (typeof ord.total === 'string' && ord.total.trim() && !ord.total.includes('NaN')) {
        return ord.total;
    }
    const currency = ord.currency || '$';
    const amount = Number(ord.total_amount || 0);
    return `${currency}${amount.toFixed(2)}`;
}

function resolveSafeImageUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (trimmed.includes('media-proxy')) {
        try {
            const parsed = new URL(trimmed, 'http://localhost');
            const targetUrl = parsed.searchParams.get('url');
            if (targetUrl) return decodeURIComponent(targetUrl);
        } catch (_e) {
            const match = trimmed.match(/[?&]url=([^&]+)/);
            if (match && match[1]) return decodeURIComponent(match[1]);
        }
    }
    return trimmed;
}
</script>

<template>
    <div class="botplus-inbox-layout" :class="{ 'has-selected-conv': Boolean(inboxStore.selectedConvId) }">
        <!-- Left Pane: Conversation List & Filters -->
        <aside class="botplus-inbox-sidebar">
            <div class="botplus-inbox-search-wrap">
                <div class="botplus-search-input-box">
                    <svg class="botplus-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input 
                        v-model="inboxStore.searchQuery" 
                        type="text" 
                        placeholder="Search by customer, email, text..." 
                        class="botplus-inbox-search-input"
                    />
                </div>
            </div>

            <!-- Enhanced Merchant Filters with Counts -->
            <div class="botplus-inbox-filter-bar">
                <button 
                    :class="{ 'is-active': inboxStore.activeFilter === 'all' }" 
                    @click="inboxStore.activeFilter = 'all'"
                >
                    All <span class="botplus-filter-count">{{ inboxStore.filterCounts.all }}</span>
                </button>
                <button 
                    :class="{ 'is-active': inboxStore.activeFilter === 'open' }" 
                    @click="inboxStore.activeFilter = 'open'"
                >
                    Open <span class="botplus-filter-count">{{ inboxStore.filterCounts.open }}</span>
                </button>
                <button 
                    :class="{ 'is-active': inboxStore.activeFilter === 'carts' }" 
                    @click="inboxStore.activeFilter = 'carts'"
                    title="Customers with active items in their cart"
                >
                    Carts <span class="botplus-filter-count">{{ inboxStore.filterCounts.carts }}</span>
                </button>
                <button 
                    :class="{ 'is-active': inboxStore.activeFilter === 'support' }" 
                    @click="inboxStore.activeFilter = 'support'"
                >
                    Support <span class="botplus-filter-count">{{ inboxStore.filterCounts.support }}</span>
                </button>
                <button 
                    :class="{ 'is-active': inboxStore.activeFilter === 'leads' }" 
                    @click="inboxStore.activeFilter = 'leads'"
                >
                    Leads <span class="botplus-filter-count">{{ inboxStore.filterCounts.leads }}</span>
                </button>
                <button 
                    :class="{ 'is-active': inboxStore.activeFilter === 'resolved' }" 
                    @click="inboxStore.activeFilter = 'resolved'"
                >
                    Resolved <span class="botplus-filter-count">{{ inboxStore.filterCounts.resolved }}</span>
                </button>
            </div>

            <!-- Scrollable Conversation List -->
            <div class="botplus-conversation-list">
                <BotPlusConversationSkeleton v-if="inboxStore.loadingConversations && !inboxStore.conversations.length" :count="5" />
                <div v-else-if="!inboxStore.filteredConversations.length" class="botplus-inbox-empty-state">
                    No conversations found.
                </div>
                <div 
                    v-for="conv in inboxStore.filteredConversations" 
                    :key="conv.id"
                    class="botplus-conversation-item"
                    :class="{ 
                        'is-selected': String(conv.id) === String(inboxStore.selectedConvId),
                        'is-unread': conv.unread 
                    }"
                    @click="handleSelectConversation(conv.id)"
                >
                    <div class="botplus-conv-header">
                        <span class="botplus-conv-customer">{{ conv.customerName }}</span>
                        <span class="botplus-conv-time">{{ conv.lastMessageTime }}</span>
                    </div>
                    <p class="botplus-conv-snippet">{{ conv.lastMessage || 'No messages yet' }}</p>
                    <div class="botplus-conv-footer">
                        <span 
                            class="botplus-status-badge" 
                            :class="conv.status"
                        >
                            {{ conv.status === 'needs_intervention' || conv.status === 'waiting' ? 'Needs Help' : conv.status }}
                        </span>
                        <span v-if="conv.intent" class="botplus-intent-badge" :class="conv.intent">
                            {{ conv.intent }}
                        </span>
                        <span v-if="conv.cartTotal" class="botplus-cart-badge">
                            Cart: {{ conv.cartTotal }}
                        </span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Middle & Right Pane: Conversation Thread & Customer Info -->
        <main class="botplus-inbox-main" v-if="currentConv">
            <!-- Thread Header -->
            <div class="botplus-thread-header">
                <div class="botplus-thread-customer-info">
                    <button class="botplus-mobile-back-btn" @click="handleBackToList" title="Back to conversations">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        <span>Chats</span>
                    </button>
                    <div class="botplus-thread-titles">
                        <div class="botplus-thread-title-row">
                            <h3 class="botplus-thread-name">{{ currentConv.customerName }}</h3>
                            <span 
                                class="botplus-status-pill" 
                                :class="isAiAutonomous ? 'ai-auto' : currentConv.status"
                            >
                                {{ isAiAutonomous ? 'AI Session' : (isWaitingSupport ? 'Needs Help' : currentConv.status) }}
                            </span>
                        </div>
                        <span class="botplus-thread-email">{{ currentConv.customerEmail || 'Guest Customer' }}</span>
                    </div>
                </div>

                <div class="botplus-thread-controls">
                    <!-- Quick action for Customer Waiting for support -->
                    <button 
                        v-if="isWaitingSupport"
                        class="botplus-action-header-btn btn-claim-urgent"
                        @click="handleClaim"
                        :disabled="inboxStore.claimingHandoff"
                        title="Claim this customer support request"
                    >
                        <svg v-if="inboxStore.claimingHandoff" class="botplus-spin" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                        </svg>
                        <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                        <span>{{ inboxStore.claimingHandoff ? 'Claiming...' : 'Claim Chat' }}</span>
                    </button>

                    <!-- Status select dropdown if ticket exists -->
                    <select 
                        v-if="hasCase"
                        :value="currentConv.status" 
                        @change="handleStatusChange($event.target.value)"
                        class="botplus-status-select"
                        title="Change Conversation Status"
                    >
                        <option value="open">Open</option>
                        <option value="waiting">Waiting</option>
                        <option value="active">Active</option>
                        <option value="pending_follow_up">Pending Follow-up</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>

                    <button 
                        v-if="hasCase && !isResolvedCase"
                        class="botplus-resolve-toggle-btn"
                        @click="toggleResolved"
                    >
                        Mark Resolved
                    </button>

                    <button 
                        class="botplus-sidepanel-toggle-btn"
                        :class="{ 'is-active': showSidepanel }"
                        @click="toggleSidepanel"
                        :title="showSidepanel ? 'Hide Customer Details' : 'Show Customer Details'"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="15" y1="3" x2="15" y2="21"></line>
                        </svg>
                        <span class="botplus-panel-btn-label">{{ showSidepanel ? 'Hide Details' : 'Details' }}</span>
                    </button>
                </div>
            </div>

            <!-- Content Split: Chat Viewport + Context Sidepanel -->
            <div class="botplus-thread-content-split" :class="{ 'sidepanel-collapsed': !showSidepanel }">
                <!-- Middle Chat Viewport (Messages Stream + Bottom Lifecycle Footer) -->
                <div class="botplus-chat-viewport">
                    <!-- Scrollable Chat Messages Stream -->
                    <div class="botplus-messages-stream" ref="messagesContainer">
                        <BotPlusThreadSkeleton v-if="inboxStore.loadingMessages && !inboxStore.messages.length" />
                        <div 
                            v-for="msg in inboxStore.messages" 
                            :key="msg.id"
                            class="botplus-message-row"
                            :class="[
                                'sender-' + msg.sender,
                                { 'has-rich-payload': Boolean(msg.uiPayload) }
                            ]"
                        >
                            <div class="botplus-message-bubble">
                                <div class="botplus-bubble-header">
                                    <span class="botplus-bubble-sender">
                                        {{ msg.sender === 'user' ? 'Customer' : (msg.sender === 'bot' ? 'BotPlus AI' : (msg.sender === 'system' ? 'Notice' : 'Store Operator')) }}
                                    </span>
                                    <span class="botplus-bubble-time">{{ msg.time }}</span>
                                </div>

                                <ChatMessageContent 
                                    :content="msg.text" 
                                    :sender="msg.sender"
                                    :ui-payload="msg.uiPayload"
                                    :metadata="msg.metadata"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Streamlined Reply Box Area -->
                    <div class="botplus-reply-box-wrap">
                        <!-- Context Hint Banner (Subtle & Non-blocking) -->
                        <div v-if="isResolvedCase" class="botplus-thread-hint-banner banner-resolved">
                            <span class="bp-status-dot is-success"></span>
                            <span>Support case resolved. Type a reply below to reactivate this conversation.</span>
                        </div>
                        <div v-else-if="isWaitingSupport" class="botplus-thread-hint-banner banner-waiting">
                            <span class="bp-status-dot is-warning"></span>
                            <span>Customer requested human assistance. Replying will claim this chat.</span>
                        </div>
                        <div v-else-if="isAiAutonomous" class="botplus-thread-hint-banner banner-ai">
                            <span class="bp-status-dot is-neutral"></span>
                            <span>AI Agent answering conversationally. Type a reply to intervene as store staff.</span>
                        </div>

                        <div class="botplus-active-reply-container">
                            <div v-if="hasCase && !isResolvedCase" class="botplus-active-reply-bar">
                                <div class="botplus-active-case-meta">
                                    <span class="botplus-case-tag">Case #{{ currentConv.helpdesk.case_id }}</span>
                                    <span class="botplus-agent-assigned" v-if="currentConv.helpdesk.assigned_agent_name">
                                        Agent: <strong>{{ currentConv.helpdesk.assigned_agent_name }}</strong>
                                    </span>
                                </div>
                                <div class="botplus-active-case-actions">
                                    <button 
                                        type="button"
                                        class="botplus-subtle-action-btn"
                                        @click="handleRelease"
                                        :disabled="inboxStore.releasingHandoff"
                                        title="Release conversation back to AI agent"
                                    >
                                        <svg v-if="inboxStore.releasingHandoff" class="botplus-spin" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                                        </svg>
                                        <span>{{ inboxStore.releasingHandoff ? 'Releasing...' : 'Release to AI' }}</span>
                                    </button>
                                    <button 
                                        type="button"
                                        class="botplus-subtle-action-btn"
                                        @click="toggleResolved"
                                        title="Mark this support case resolved"
                                    >
                                        Mark Resolved
                                    </button>
                                </div>
                            </div>
                            <div class="botplus-reply-inner">
                                <textarea 
                                    v-model="draftReply" 
                                    placeholder="Type a message to customer (Enter to send, Shift+Enter for newline)..."
                                    rows="2"
                                    class="botplus-reply-textarea"
                                    @keydown.enter.exact.prevent="handleSendReply"
                                ></textarea>
                                <button 
                                    type="button"
                                    @click="handleSendReply" 
                                    :disabled="inboxStore.sendingReply || !draftReply.trim()" 
                                    class="botplus-reply-send-btn"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                    {{ inboxStore.sendingReply ? 'Sending...' : 'Send Reply' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar: Customer Context (Profile, Cart, Notes) -->
                <aside v-if="showSidepanel" class="botplus-customer-sidepanel">
                    <div class="botplus-sidepanel-nav">
                        <button :class="{ 'is-active': activeSideTab === 'profile' }" @click="activeSideTab = 'profile'">Profile</button>
                        <button :class="{ 'is-active': activeSideTab === 'cart' }" @click="activeSideTab = 'cart'">
                            Cart <span v-if="currentConv.cart_snapshot?.item_count" class="botplus-nav-badge">({{ currentConv.cart_snapshot.item_count }})</span>
                        </button>
                        <button :class="{ 'is-active': activeSideTab === 'notes' }" @click="activeSideTab = 'notes'">Notes</button>
                    </div>

                    <!-- Profile Tab -->
                    <div v-if="activeSideTab === 'profile'" class="botplus-sidepanel-tab-content">
                        <h4 class="botplus-sidepanel-heading">Customer Context</h4>
                        <div class="botplus-sidepanel-section">
                            <div class="botplus-kv-item">
                                <span class="botplus-kv-k">Email</span>
                                <span class="botplus-kv-v">{{ currentConv.customerEmail || 'Not provided' }}</span>
                            </div>
                            <div class="botplus-kv-item">
                                <span class="botplus-kv-k">Phone</span>
                                <span class="botplus-kv-v">
                                    <a v-if="currentConv.customerPhone" :href="'tel:' + currentConv.customerPhone" class="botplus-link-text">{{ currentConv.customerPhone }}</a>
                                    <span v-else>Not provided</span>
                                </span>
                            </div>
                            <div class="botplus-kv-item" v-if="currentConv.intent">
                                <span class="botplus-kv-k">Intent</span>
                                <span class="botplus-kv-v">
                                    <span class="botplus-intent-badge" :class="currentConv.intent">{{ currentConv.intent }}</span>
                                    <small class="botplus-confidence-hint" v-if="currentConv.confidence">({{ Math.round(currentConv.confidence * 100) }}%)</small>
                                </span>
                            </div>
                            <div class="botplus-kv-item">
                                <span class="botplus-kv-k">Lifetime Spent</span>
                                <span class="botplus-kv-v highlight">{{ currentConv.totalCustomerSpent }}</span>
                            </div>
                            <div class="botplus-kv-item">
                                <span class="botplus-kv-k">Orders Placed</span>
                                <span class="botplus-kv-v">{{ currentConv.customerOrdersCount }}</span>
                            </div>
                        </div>

                        <div class="botplus-sidepanel-section" v-if="currentConv.helpdesk">
                            <div class="botplus-kv-item">
                                <span class="botplus-kv-k">Case ID</span>
                                <span class="botplus-kv-v font-bold">#{{ currentConv.helpdesk.case_id }}</span>
                            </div>
                            <div class="botplus-kv-item" v-if="currentConv.helpdesk.assigned_agent_name">
                                <span class="botplus-kv-k">Agent</span>
                                <span class="botplus-kv-v">{{ currentConv.helpdesk.assigned_agent_name }}</span>
                            </div>
                        </div>

                        <h4 class="botplus-sidepanel-heading">Past Orders</h4>
                        <div class="botplus-sidepanel-orders">
                            <div 
                                v-for="ord in currentConv.customerOrders" 
                                :key="ord.id"
                                class="botplus-sidepanel-order-item"
                            >
                                <div class="botplus-order-row-top">
                                    <a v-if="ord.edit_url" :href="ord.edit_url" target="_blank" rel="noopener noreferrer" class="botplus-order-link">
                                        Order #{{ ord.order_number || ord.id }} &nearr;
                                    </a>
                                    <strong v-else>Order #{{ ord.order_number || ord.id }}</strong>
                                    <span class="botplus-order-status-badge">{{ ord.status }}</span>
                                </div>
                                <div class="botplus-order-row-bottom">
                                    <span>{{ ord.ordered_at ? new Date(ord.ordered_at).toLocaleDateString() : (ord.date || 'Recent') }}</span>
                                    <strong>{{ formatOrderTotal(ord) }}</strong>
                                </div>
                            </div>
                            <div v-if="!currentConv.customerOrders.length" class="botplus-text-muted">
                                No past orders recorded for this customer.
                            </div>
                        </div>
                    </div>

                    <!-- Cart Tab -->
                    <div v-else-if="activeSideTab === 'cart'" class="botplus-sidepanel-tab-content">
                        <h4 class="botplus-sidepanel-heading">Live In-Flight Cart</h4>
                        <div v-if="currentConv.cart_snapshot && currentConv.cart_snapshot.item_count > 0" class="botplus-sidepanel-cart">
                            <div class="botplus-cart-header-summary">
                                <span>{{ currentConv.cart_snapshot.item_count }} item(s) in cart</span>
                                <strong>{{ currentConv.cart_snapshot.currency }}{{ Number(currentConv.cart_snapshot.subtotal_amount || 0).toFixed(2) }}</strong>
                            </div>
                            <div class="botplus-cart-lines-list">
                                <div v-for="line in currentConv.cart_snapshot.lines" :key="line.id" class="botplus-cart-line-item">
                                    <img v-if="line.image_url" :src="resolveSafeImageUrl(line.image_url)" class="botplus-cart-line-thumb" />
                                    <div class="botplus-cart-line-info">
                                        <div class="botplus-cart-line-title">{{ line.title }}</div>
                                        <div class="botplus-cart-line-meta">Qty: {{ line.quantity }} &times; {{ currentConv.cart_snapshot.currency }}{{ Number(line.price || 0).toFixed(2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="botplus-text-muted">
                            No active shopping cart session for this customer.
                        </div>
                    </div>

                    <!-- Notes Tab -->
                    <div v-else-if="activeSideTab === 'notes'" class="botplus-sidepanel-tab-content">
                        <h4 class="botplus-sidepanel-heading">Helpdesk Staff Notes</h4>
                        <div class="botplus-notes-composer">
                            <div class="botplus-note-type-toggle">
                                <label><input type="radio" value="internal" v-model="noteType" /> Internal</label>
                                <label><input type="radio" value="resolution" v-model="noteType" /> Resolution</label>
                            </div>
                            <textarea 
                                v-model="draftNote" 
                                :placeholder="noteType === 'resolution' ? 'Add resolution note...' : 'Add private internal note...'" 
                                rows="3"
                                class="botplus-notes-textarea"
                            ></textarea>
                            <button @click="handleAddNote" :disabled="!draftNote.trim()" class="botplus-add-note-btn">
                                Save Note
                            </button>
                        </div>

                        <div class="botplus-notes-list" v-if="localNotes.length">
                            <div v-for="n in localNotes" :key="n.id" class="botplus-note-item" :class="'type-' + n.note_type">
                                <div class="botplus-note-header">
                                    <strong>{{ n.author }}</strong>
                                    <span>{{ n.created_at }}</span>
                                </div>
                                <p class="botplus-note-body">{{ n.body }}</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </main>

        <div v-else class="botplus-inbox-empty-wrap">
            <div class="botplus-empty-graphic">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            <p>Select a conversation from the left to view messages and customer profile.</p>
        </div>
    </div>
</template>

<style scoped>
/* Full container layout */
.botplus-inbox-layout {
    display: grid;
    grid-template-columns: minmax(280px, 320px) 1fr;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    height: calc(100vh - 130px);
    min-height: 560px;
    max-height: calc(100vh - 110px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Left Sidebar */
.botplus-inbox-sidebar {
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    background: #fafafa;
    height: 100%;
    min-height: 0; /* Critical for scroll */
    overflow: hidden;
}

.botplus-inbox-search-wrap {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    flex-shrink: 0;
}

.botplus-search-input-box {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0 8px;
}

.botplus-search-icon {
    color: #94a3b8;
    margin-right: 6px;
    flex-shrink: 0;
}

.botplus-inbox-search-input {
    width: 100%;
    padding: 6px 0;
    font-size: 13px;
    border: none;
    background: transparent;
    outline: none;
}

/* Filter Bar */
.botplus-inbox-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
    flex-shrink: 0;
}

.botplus-inbox-filter-bar button {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 3px 7px;
    font-size: 11px;
    font-weight: 500;
    color: #64748b;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 4px;
}

.botplus-inbox-filter-bar button:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.botplus-inbox-filter-bar button.is-active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
    font-weight: 600;
}

.botplus-filter-count {
    font-size: 10px;
    background: rgba(0, 0, 0, 0.08);
    padding: 1px 4px;
    border-radius: 3px;
}

.botplus-inbox-filter-bar button.is-active .botplus-filter-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Scrollable Conversation List */
.botplus-conversation-list {
    flex: 1;
    min-height: 0; /* Critical for scroll */
    overflow-y: auto;
    overflow-x: hidden;
}

.botplus-conversation-item {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s;
}

.botplus-conversation-item:hover {
    background: #f8fafc;
}

.botplus-conversation-item.is-selected {
    background: #eff6ff;
    border-left: 3px solid #2563eb;
}

.botplus-conv-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.botplus-conv-customer {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}

.botplus-conv-time {
    font-size: 11px;
    color: #94a3b8;
}

.botplus-conv-snippet {
    font-size: 12px;
    color: #64748b;
    margin: 0 0 6px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.botplus-conv-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}

.botplus-status-badge {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 2px 5px;
    border-radius: 3px;
}

.botplus-status-badge.open { background: #dbeafe; color: #1e40af; }
.botplus-status-badge.needs_intervention,
.botplus-status-badge.waiting { background: #fef3c7; color: #92400e; }
.botplus-status-badge.active { background: #dcfce7; color: #166534; }
.botplus-status-badge.resolved,
.botplus-status-badge.closed { background: #f1f5f9; color: #475569; }

.botplus-intent-badge {
    font-size: 10px;
    font-weight: 500;
    text-transform: capitalize;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 5px;
    border-radius: 3px;
}

.botplus-intent-badge.support { background: #fee2e2; color: #991b1b; }
.botplus-intent-badge.sales,
.botplus-intent-badge.lead { background: #fef9c3; color: #854d0e; }

.botplus-cart-badge {
    font-size: 10px;
    font-weight: 600;
    background: #e0e7ff;
    color: #3730a3;
    padding: 2px 5px;
    border-radius: 3px;
}

/* Main Area */
.botplus-inbox-main {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    overflow: hidden;
    background: #ffffff;
}

.botplus-inbox-empty-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 14px;
    height: 100%;
    gap: 8px;
    padding: 40px;
    text-align: center;
}

.botplus-thread-header {
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.botplus-thread-customer-info {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.botplus-thread-titles {
    min-width: 0;
}

.botplus-mobile-back-btn {
    display: none;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}

.botplus-mobile-back-btn:hover {
    background: #e2e8f0;
}

.botplus-thread-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.botplus-thread-name {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.botplus-status-pill {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 4px;
    background: #f1f5f9;
    color: #475569;
}
.botplus-status-pill.open, .botplus-status-pill.active { background: #dcfce7; color: #166534; }
.botplus-status-pill.waiting, .botplus-status-pill.needs_intervention { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.botplus-status-pill.resolved, .botplus-status-pill.closed { background: #f1f5f9; color: #64748b; }
.botplus-status-pill.ai-auto { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

.botplus-thread-email {
    font-size: 12px;
    color: #64748b;
}

.botplus-thread-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Header Quick Action Buttons */
.botplus-action-header-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    border: none;
}

.botplus-action-header-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.botplus-action-header-btn.btn-claim-header {
    background: #0f172a;
    color: #ffffff;
}

.botplus-action-header-btn.btn-claim-header:hover:not(:disabled) {
    background: #1e293b;
}

.botplus-action-header-btn.btn-claim-urgent {
    background: #d97706;
    color: #ffffff;
}

.botplus-action-header-btn.btn-claim-urgent:hover:not(:disabled) {
    background: #b45309;
}

.botplus-status-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    height: 32px;
    padding: 0 28px 0 10px !important;
    font-size: 12px;
    font-weight: 600;
    line-height: 30px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background-color: #ffffff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 12px 12px;
    color: #334155;
    outline: none;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease;
}

.botplus-status-select:hover {
    border-color: #94a3b8;
    background-color: #f8fafc;
}

.botplus-status-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.botplus-resolve-toggle-btn {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}

.botplus-resolve-toggle-btn:hover {
    background: #f1f5f9;
}

.botplus-resolve-toggle-btn.is-resolved {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}

.botplus-sidepanel-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}

.botplus-sidepanel-toggle-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.botplus-sidepanel-toggle-btn.is-active {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

/* Thread Content Split */
.botplus-thread-content-split {
    display: grid;
    grid-template-columns: 1fr 310px;
    flex: 1;
    min-height: 0;
    height: 100%;
    overflow: hidden;
    transition: grid-template-columns 0.2s ease;
}

.botplus-thread-content-split.sidepanel-collapsed {
    grid-template-columns: 1fr;
}

/* Chat Viewport (Messages Stream + Bottom Reply Box) */
.botplus-chat-viewport {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    border-right: 1px solid #e2e8f0;
    background: #ffffff;
}

/* Messages Stream */
.botplus-messages-stream {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.botplus-message-row {
    display: flex;
    flex-direction: column;
    max-width: 80%;
    min-width: 0;
}

.botplus-message-row.has-rich-payload {
    max-width: 95%;
    width: 100%;
}

.botplus-message-row.sender-user { align-self: flex-start; }
.botplus-message-row.sender-bot { align-self: flex-start; }
.botplus-message-row.sender-human_operator { align-self: flex-end; }
.botplus-message-row.sender-system { align-self: center; max-width: 90%; }

.botplus-message-bubble {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.45;
    min-width: 0;
    word-break: break-word;
}

.sender-user .botplus-message-bubble {
    background: #f1f5f9;
    color: #1e293b;
    border-bottom-left-radius: 2px;
}

.sender-bot .botplus-message-bubble {
    background: #eff6ff;
    color: #1e3a8a;
    border: 1px solid #dbeafe;
    border-bottom-left-radius: 2px;
}

.sender-human_operator .botplus-message-bubble {
    background: #2563eb;
    color: #ffffff;
    border-bottom-right-radius: 2px;
}

.sender-human_operator .botplus-message-bubble :deep(a) {
    color: #dbeafe;
    text-decoration: underline;
}

.sender-human_operator .botplus-message-bubble :deep(code) {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.sender-system .botplus-message-bubble {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-size: 12px;
    font-style: italic;
    text-align: center;
}

.botplus-bubble-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    font-size: 10px;
    opacity: 0.8;
}

.botplus-bubble-text {
    white-space: pre-wrap;
    word-break: break-word;
}

/* Tool execution card */
.botplus-tool-result-box {
    margin-top: 8px;
    padding: 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 11px;
    color: #334155;
}

.botplus-tool-title {
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
    margin-bottom: 4px;
}

.botplus-tool-result-details {
    line-height: 1.4;
}

/* Product Card */
.botplus-product-card-preview {
    margin-top: 8px;
    display: flex;
    gap: 8px;
    background: #ffffff;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.botplus-product-thumb {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 4px;
}

.botplus-product-title {
    font-weight: 600;
    font-size: 12px;
    color: #1e293b;
}

.botplus-product-price {
    font-size: 11px;
    color: #2563eb;
    font-weight: 600;
}

.botplus-thread-hint-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    background: #f8fafc;
    border-radius: 6px;
    margin-bottom: 8px;
    border: 1px solid #e2e8f0;
}

.botplus-thread-hint-banner.banner-waiting {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}

.botplus-thread-hint-banner.banner-resolved {
    background: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}

.botplus-thread-hint-banner.banner-ai {
    background: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}

/* Pinned Reply Box Wrap at bottom of chat */
.botplus-reply-box-wrap {
    flex-shrink: 0;
    padding: 10px 16px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.botplus-reply-inner {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.botplus-reply-textarea {
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 13px;
    outline: none;
    resize: none;
    background: #ffffff;
    font-family: inherit;
    line-height: 1.4;
}

.botplus-reply-textarea:focus {
    border-color: #2563eb;
}

.botplus-reply-send-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}

.botplus-reply-send-btn:hover:not(:disabled) {
    background: #1d4ed8;
}

.botplus-reply-send-btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

/* Lifecycle Cards in Footer */
.botplus-lifecycle-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
}

.botplus-lifecycle-card.banner-ai {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.botplus-lifecycle-card.banner-waiting {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.botplus-lifecycle-card.banner-resolved {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
}

.botplus-lifecycle-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.botplus-lifecycle-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 13px;
}

.botplus-lifecycle-desc {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
    opacity: 0.9;
}

.botplus-lifecycle-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    border: none;
    flex-shrink: 0;
}

.botplus-lifecycle-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-ai-claim {
    background: #16a34a;
    color: #ffffff;
}
.btn-ai-claim:hover:not(:disabled) {
    background: #15803d;
}

.btn-claim {
    background: #d97706;
    color: #ffffff;
}
.btn-claim:hover:not(:disabled) {
    background: #b45309;
}

.btn-reopen {
    background: #0f172a;
    color: #ffffff;
}
.btn-reopen:hover:not(:disabled) {
    background: #1e293b;
}

/* Active Reply Container */
.botplus-active-reply-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.botplus-active-reply-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 11px;
    color: #64748b;
    padding-bottom: 4px;
}

.botplus-active-case-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.botplus-case-tag {
    background: #e2e8f0;
    color: #1e293b;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
}

.botplus-agent-assigned strong {
    color: #0f172a;
}

.botplus-active-case-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.botplus-subtle-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.botplus-subtle-action-btn:hover:not(:disabled) {
    background: #e2e8f0;
    color: #0f172a;
}

.botplus-subtle-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.botplus-spin {
    animation: spin 0.6s linear infinite;
}

/* Customer Context Panel */
.botplus-customer-sidepanel {
    height: 100%;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px;
    background: #fafafa;
}

.botplus-sidepanel-nav {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 8px;
    margin-bottom: 14px;
}

.botplus-sidepanel-nav button {
    background: transparent;
    border: none;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
}

.botplus-sidepanel-nav button.is-active {
    background: #e2e8f0;
    color: #0f172a;
}

.botplus-nav-badge {
    font-size: 10px;
    font-weight: 700;
    color: #2563eb;
}

.botplus-sidepanel-heading {
    margin: 0 0 10px 0;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.botplus-sidepanel-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}

.botplus-kv-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.botplus-kv-k { color: #64748b; }
.botplus-kv-v { color: #1e293b; font-weight: 500; }
.botplus-kv-v.highlight { color: #16a34a; font-weight: 600; }

.botplus-sidepanel-orders {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.botplus-sidepanel-order-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 12px;
}

.botplus-order-row-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.botplus-order-link {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
}
.botplus-order-link:hover { text-decoration: underline; }

.botplus-order-status-badge {
    font-size: 10px;
    font-weight: 600;
    background: #f1f5f9;
    padding: 2px 4px;
    border-radius: 3px;
    text-transform: capitalize;
}

.botplus-order-row-bottom {
    display: flex;
    justify-content: space-between;
    color: #64748b;
    font-size: 11px;
}

/* Cart Tab Styles */
.botplus-cart-header-summary {
    display: flex;
    justify-content: space-between;
    padding: 8px 10px;
    background: #f1f5f9;
    border-radius: 4px;
    margin-bottom: 10px;
    font-size: 12px;
}

.botplus-cart-lines-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.botplus-cart-line-item {
    display: flex;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px;
}

.botplus-cart-line-thumb {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 4px;
}

.botplus-cart-line-title {
    font-size: 11px;
    font-weight: 600;
    color: #1e293b;
}

.botplus-cart-line-meta {
    font-size: 10px;
    color: #64748b;
}

/* Notes Composer */
.botplus-notes-composer {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.botplus-note-type-toggle {
    display: flex;
    gap: 12px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
}

.botplus-notes-textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 8px;
    font-size: 12px;
    outline: none;
    resize: vertical;
    background: #ffffff;
}

.botplus-add-note-btn {
    align-self: flex-end;
    background: #0f172a;
    color: #ffffff;
    border: none;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}

.botplus-notes-list {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.botplus-note-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px;
    font-size: 11px;
}

.botplus-note-item.type-resolution { border-left: 3px solid #16a34a; }
.botplus-note-item.type-internal { border-left: 3px solid #6366f1; }

.botplus-note-header {
    display: flex;
    justify-content: space-between;
    color: #64748b;
    margin-bottom: 4px;
}

.botplus-note-body {
    margin: 0;
    color: #1e293b;
    white-space: pre-wrap;
}

.botplus-text-muted {
    color: #94a3b8;
    font-size: 12px;
    padding: 8px 0;
}

/* Custom Sleek Scrollbars */
.botplus-conversation-list::-webkit-scrollbar,
.botplus-messages-stream::-webkit-scrollbar,
.botplus-customer-sidepanel::-webkit-scrollbar {
    width: 6px;
}

.botplus-conversation-list::-webkit-scrollbar-thumb,
.botplus-messages-stream::-webkit-scrollbar-thumb,
.botplus-customer-sidepanel::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.botplus-conversation-list::-webkit-scrollbar-thumb:hover,
.botplus-messages-stream::-webkit-scrollbar-thumb:hover,
.botplus-customer-sidepanel::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.botplus-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-bottom: 6px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive Breakpoints & Viewport Polish */
@media (max-width: 960px) {
    .botplus-inbox-layout {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 120px);
        min-height: 520px;
    }

    .botplus-inbox-sidebar {
        width: 100%;
        height: 100%;
        border-right: none;
    }

    /* Single pane mode on tablet/mobile */
    .botplus-inbox-layout.has-selected-conv .botplus-inbox-sidebar {
        display: none;
    }

    .botplus-inbox-layout:not(.has-selected-conv) .botplus-inbox-main {
        display: none;
    }

    .botplus-inbox-layout:not(.has-selected-conv) .botplus-inbox-empty-wrap {
        display: none;
    }

    .botplus-mobile-back-btn {
        display: inline-flex;
    }

    .botplus-thread-content-split {
        grid-template-columns: 1fr;
    }

    .botplus-customer-sidepanel {
        border-top: 1px solid #e2e8f0;
        border-left: none;
        max-height: 45%;
    }
}

@media (max-width: 640px) {
    .botplus-thread-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 10px 12px;
    }

    .botplus-thread-controls {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .botplus-reply-inner {
        flex-direction: column;
        align-items: stretch;
    }

    .botplus-reply-send-btn {
        align-self: flex-end;
    }

    .botplus-inbox-filter-bar {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 6px;
    }

    .botplus-inbox-filter-bar button {
        white-space: nowrap;
        flex-shrink: 0;
    }
}
</style>
