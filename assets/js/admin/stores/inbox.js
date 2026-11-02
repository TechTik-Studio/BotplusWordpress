import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
    initAdminFirebase,
    subscribeToConversationMessages,
    subscribeToConversationsSummaries,
    subscribeToAgentRequests,
} from '../services/firebase';

export const useInboxStore = defineStore('inbox', () => {
    // State
    const conversations = ref([]);
    const selectedConvId = ref(null);
    const messages = ref([]);
    const loadingConversations = ref(false);
    const loadingMessages = ref(false);
    const sendingReply = ref(false);
    const claimingHandoff = ref(false);
    const releasingHandoff = ref(false);
    const activeFilter = ref('all');
    const searchQuery = ref('');

    // Firebase state
    const firebaseReady = ref(false);
    const firebaseConfig = ref(null);
    const firebasePaths = ref(null);
    const firebaseToken = ref(null);
    let activeDatabase = null;
    let unsubscribeMessages = null;
    let unsubscribeSummaries = null;
    let unsubscribeRequests = null;

    // Helper: Determine if conversation exhibits lead signals
    function hasLeadSignal(c) {
        if (!c) return false;
        const intent = String(c.intent || c.inbox?.classification || '').toLowerCase();
        const signal = String(c.lead_signal || c.inbox?.lead_signal || '').toLowerCase();
        const action = String(c.recommended_action || c.inbox?.recommended_action || '').toLowerCase();
        const leadStatus = String(c.lead?.status || '').toLowerCase();
        const hasContact = Boolean(
            c.guest_email ||
            c.customerEmail ||
            c.lead?.email ||
            c.guest_phone ||
            c.customerPhone ||
            c.lead?.phone
        );

        return (
            intent === 'lead' ||
            intent === 'sales' ||
            signal === 'warm' ||
            signal === 'hot' ||
            action === 'show_lead_capture_form' ||
            action === 'mark_warm_sales_lead' ||
            leadStatus === 'captured' ||
            hasContact
        );
    }

    // Computed: Filter counts for UI pills
    const filterCounts = computed(() => {
        const all = conversations.value.length;
        const open = conversations.value.filter(c => c.status !== 'resolved' && c.status !== 'closed').length;
        const carts = conversations.value.filter(c => (c.cart_snapshot?.item_count > 0) || Boolean(c.cartTotal)).length;
        const support = conversations.value.filter(c => (c.intent || '').toLowerCase() === 'support' || c.status === 'waiting' || c.is_handoff).length;
        const leads = conversations.value.filter(c => hasLeadSignal(c)).length;
        const resolved = conversations.value.filter(c => c.status === 'resolved' || c.status === 'closed').length;

        return { all, open, carts, support, leads, resolved };
    });

    // Getters
    const filteredConversations = computed(() => {
        let list = conversations.value;

        switch (activeFilter.value) {
            case 'open':
                list = list.filter(c => c.status !== 'resolved' && c.status !== 'closed');
                break;
            case 'carts':
                list = list.filter(c => (c.cart_snapshot?.item_count > 0) || Boolean(c.cartTotal));
                break;
            case 'support':
                list = list.filter(c => (c.intent || '').toLowerCase() === 'support' || c.status === 'waiting' || c.is_handoff);
                break;
            case 'leads':
                list = list.filter(c => hasLeadSignal(c));
                break;
            case 'resolved':
                list = list.filter(c => c.status === 'resolved' || c.status === 'closed');
                break;
            case 'all':
            default:
                break;
        }

        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase().trim();
            list = list.filter(c =>
                (c.customerName || c.guest_name || '').toLowerCase().includes(query) ||
                (c.customerEmail || c.guest_email || '').toLowerCase().includes(query) ||
                (c.lastMessage || c.last_message_preview || '').toLowerCase().includes(query)
            );
        }

        return list;
    });

    const currentConversation = computed(() => {
        return conversations.value.find(c => String(c.id || c.conversation_id) === String(selectedConvId.value)) || conversations.value[0] || null;
    });

    // Helper: Normalize conversation item
    function normalizeConversation(raw) {
        const convId = String(raw.conversation_id || raw.id || '');
        const leadObj = raw.lead && typeof raw.lead === 'object' ? raw.lead : null;
        const inboxObj = raw.inbox && typeof raw.inbox === 'object' ? raw.inbox : null;

        const name = leadObj?.name || raw.guest_name || raw.customerName || 'Guest Customer';
        const email = leadObj?.email || raw.guest_email || raw.customerEmail || '';
        const phone = leadObj?.phone || raw.guest_phone || raw.customerPhone || '';
        const preview = raw.last_message_preview || raw.lastMessage || raw.last_message || raw.message || '';
        const status = raw.status || 'open';
        const cart = raw.cart_snapshot || null;

        const intent = String(inboxObj?.classification || raw.intent || 'inquiry').toLowerCase();
        const confidence = Number(inboxObj?.confidence ?? raw.confidence ?? 0.85);
        const reason = inboxObj?.reason || raw.reason || null;
        const leadSignal = inboxObj?.lead_signal || raw.lead_signal || null;
        const recommendedAction = inboxObj?.recommended_action || raw.recommended_action || null;

        return {
            id: convId,
            conversation_id: convId,
            customerName: name,
            guest_name: name,
            customerEmail: email,
            guest_email: email,
            customerPhone: phone,
            guest_phone: phone,
            status,
            is_handoff: Boolean(raw.is_handoff),
            handoff_priority: Number(raw.handoff_priority || 0),
            intent,
            confidence,
            reason,
            lead_signal: leadSignal,
            recommended_action: recommendedAction,
            inbox: inboxObj || {
                classification: intent,
                confidence,
                reason,
                lead_signal: leadSignal,
                recommended_action: recommendedAction,
                classified_by: 'runtime',
            },
            lead: leadObj || {
                status: (email || phone) ? 'captured' : 'none',
                name: name !== 'Guest Customer' ? name : null,
                email: email || null,
                phone: phone || null,
                source: 'chat',
            },
            lastMessage: preview,
            last_message_preview: preview,
            lastMessageTime: raw.last_message_at ? new Date(raw.last_message_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : (raw.lastMessageTime || 'Recently'),
            last_message_at: raw.last_message_at || Date.now(),
            unread: Boolean(raw.unread_count > 0 || raw.unread),
            cartTotal: cart ? `${cart.currency || '$'}${Number(cart.subtotal_amount || 0).toFixed(2)}` : (raw.cartTotal || null),
            cart_snapshot: cart,
            totalCustomerSpent: raw.totalCustomerSpent || '$0.00',
            customerOrdersCount: raw.customerOrdersCount || 0,
            customerOrders: raw.recent_orders || raw.customerOrders || [],
            helpdesk: raw.helpdesk || null,
        };
    }

    // Helper: Normalize message item
    function normalizeMessage(raw) {
        const sender = raw.sender === 'human' || raw.sender === 'human_operator'
            ? 'human_operator'
            : (raw.sender === 'bot' || raw.sender === 'ai' ? 'bot' : (raw.sender === 'system' ? 'system' : 'user'));

        const metadata = raw.metadata && typeof raw.metadata === 'object' ? raw.metadata : {};
        const uiPayload = raw.ui_payload || raw.uiPayload || metadata.ui_payload || null;
        const toolCall = raw.toolCall || raw.tool_call || metadata.tool_call || metadata.tool || null;
        const productCard = raw.productCard || raw.product_card || metadata.product_card || null;
        const activityStatuses = raw.activity_statuses || metadata.activity_statuses || [];

        return {
            id: String(raw.id || raw.message_id || 'msg-' + Math.random()),
            sender,
            text: raw.text || raw.message || '',
            time: raw.time || (raw.created_at || raw.timestamp ? new Date(raw.created_at || raw.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Just now'),
            created_at: raw.created_at || raw.timestamp || Date.now(),
            uiPayload,
            toolCall,
            productCard,
            activityStatuses,
            metadata: {
                ...metadata,
                ui_payload: uiPayload,
                activity_statuses: activityStatuses,
            },
        };
    }

    // Action: Initialize Inbox
    async function initInbox() {
        loadingConversations.value = true;

        try {
            // 1. Fetch Firebase Config & Custom Token
            await fetchFirebaseAuth();

            // 2. Fetch Initial Conversations List
            await loadConversations();
        } catch (err) {
            console.warn('[BotPlus Inbox] Initialization notice:', err.message);
        } finally {
            loadingConversations.value = false;
        }
    }

    // Action: Fetch Firebase Auth & Mount Listeners
    async function fetchFirebaseAuth() {
        const config = window.BotPlusAdminConfig;
        if (!config || !config.ajaxUrl) return;

        try {
            const formData = new FormData();
            formData.append('action', 'botplus_inbox_firebase_auth');
            formData.append('nonce', config.nonce || '');

            const res = await fetch(config.ajaxUrl, { method: 'POST', body: formData });
            const json = await res.json();

            if (json.success && json.data) {
                firebaseConfig.value = json.data.firebase_config;
                firebasePaths.value = json.data.paths;
                firebaseToken.value = json.data.token;

                // Initialize Firebase SDK
                const { database } = await initAdminFirebase(json.data.firebase_config, json.data.token);
                activeDatabase = database;
                firebaseReady.value = Boolean(database);

                if (database) {
                    // Mount real-time summaries listener
                    if (unsubscribeSummaries) unsubscribeSummaries();
                    unsubscribeSummaries = subscribeToConversationsSummaries(
                        activeDatabase,
                        json.data.paths.chats_root,
                        (liveSummaries) => handleLiveSummaries(liveSummaries)
                    );

                    // Mount real-time agent requests listener
                    if (unsubscribeRequests) unsubscribeRequests();
                    unsubscribeRequests = subscribeToAgentRequests(
                        activeDatabase,
                        json.data.paths.agent_requests_root,
                        (liveRequests) => handleLiveRequests(liveRequests)
                    );
                }
            }
        } catch (err) {
            console.warn('[BotPlus Inbox] Firebase connection notice:', err.message);
        }
    }

    // Action: Load Conversations from WordPress AJAX
    async function loadConversations() {
        const config = window.BotPlusAdminConfig;
        if (!config || !config.ajaxUrl) return;

        try {
            const url = new URL(config.ajaxUrl);
            url.searchParams.append('action', 'botplus_inbox_conversations');
            url.searchParams.append('nonce', config.nonce || '');
            url.searchParams.append('status', activeFilter.value);

            const res = await fetch(url.toString());
            const json = await res.json();

            if (json.success && json.data && Array.isArray(json.data.conversations)) {
                conversations.value = json.data.conversations.map(normalizeConversation);

                if (!selectedConvId.value && conversations.value.length > 0) {
                    selectConversation(conversations.value[0].id);
                }
            }
        } catch (err) {
            console.warn('[BotPlus Inbox] Load conversations notice:', err.message);
        }
    }

    // Action: Select Conversation & Attach Real-Time Messages Listener
    async function selectConversation(convId) {
        if (!convId) return;
        selectedConvId.value = String(convId);
        loadingMessages.value = true;

        const targetConv = conversations.value.find(c => String(c.id) === String(convId));
        if (targetConv) {
            targetConv.unread = false;
        }

        try {
            // 1. Load full message history from WordPress REST / AJAX
            const config = window.BotPlusAdminConfig;
            if (config && config.ajaxUrl) {
                const url = new URL(config.ajaxUrl);
                url.searchParams.append('action', 'botplus_inbox_messages');
                url.searchParams.append('nonce', config.nonce || '');
                url.searchParams.append('conversation_id', convId);

                const res = await fetch(url.toString());
                const json = await res.json();

                if (json.success && json.data && Array.isArray(json.data.messages)) {
                    messages.value = json.data.messages.map(normalizeMessage);
                }
            }

            // 2. Attach Firebase Realtime Listener on active chat node
            if (activeDatabase && firebasePaths.value?.chats_root) {
                if (unsubscribeMessages) unsubscribeMessages();
                unsubscribeMessages = subscribeToConversationMessages(
                    activeDatabase,
                    firebasePaths.value.chats_root,
                    convId,
                    (liveMessagesMap) => handleLiveMessages(liveMessagesMap)
                );
            }
        } catch (err) {
            console.warn('[BotPlus Inbox] Select conversation notice:', err.message);
        } finally {
            loadingMessages.value = false;
        }
    }

    // Action: Handle Live Messages arriving from Firebase
    function handleLiveMessages(liveMap) {
        if (!liveMap || typeof liveMap !== 'object') return;

        const liveList = Object.entries(liveMap).map(([k, v]) => ({
            id: v.id ? String(v.id) : k,
            ...v,
        }));

        const existingMap = new Map(messages.value.map(m => [String(m.id), m]));
        let hasNew = false;

        liveList.forEach(raw => {
            const normalized = normalizeMessage(raw);
            if (!existingMap.has(normalized.id)) {
                messages.value.push(normalized);
                hasNew = true;
            }
        });

        if (hasNew) {
            messages.value.sort((a, b) => (a.created_at || 0) - (b.created_at || 0));
        }
    }

    // Action: Handle Live Summaries arriving from Firebase
    function handleLiveSummaries(liveSummaries) {
        if (!liveSummaries || typeof liveSummaries !== 'object') return;

        Object.entries(liveSummaries).forEach(([convId, raw]) => {
            if (!raw || typeof raw !== 'object') return;

            const strConvId = String(convId);
            const index = conversations.value.findIndex(c => String(c.id || c.conversation_id) === strConvId);
            const preview = raw.last_message_preview || raw.last_message || raw.message || raw.lastMessage;
            const lastAt = raw.last_message_at || raw.updated_at || Date.now();

            if (index !== -1) {
                const target = conversations.value[index];
                if (preview) {
                    target.lastMessage = preview;
                    target.last_message_preview = preview;
                }
                if (raw.status) target.status = raw.status;

                // Sync intelligence fields
                if (raw.inbox?.classification || raw.intent) {
                    target.intent = String(raw.inbox?.classification || raw.intent).toLowerCase();
                }
                if (raw.inbox?.confidence !== undefined || raw.confidence !== undefined) {
                    target.confidence = Number(raw.inbox?.confidence ?? raw.confidence);
                }
                if (raw.inbox?.reason || raw.reason) {
                    target.reason = raw.inbox?.reason || raw.reason;
                }
                if (raw.inbox?.lead_signal || raw.lead_signal) {
                    target.lead_signal = raw.inbox?.lead_signal || raw.lead_signal;
                }
                if (raw.inbox?.recommended_action || raw.recommended_action) {
                    target.recommended_action = raw.inbox?.recommended_action || raw.recommended_action;
                }
                if (raw.inbox) {
                    target.inbox = { ...(target.inbox || {}), ...raw.inbox };
                }

                // Sync lead & customer contact info
                if (raw.lead) {
                    target.lead = { ...(target.lead || {}), ...raw.lead };
                }
                const newEmail = raw.lead?.email || raw.guest_email || raw.customerEmail;
                if (newEmail) {
                    target.guest_email = newEmail;
                    target.customerEmail = newEmail;
                }
                const newName = raw.lead?.name || raw.guest_name || raw.customerName;
                if (newName) {
                    target.guest_name = newName;
                    target.customerName = newName;
                }
                const newPhone = raw.lead?.phone || raw.guest_phone || raw.customerPhone;
                if (newPhone) {
                    target.guest_phone = newPhone;
                    target.customerPhone = newPhone;
                }

                target.last_message_at = lastAt;
                target.lastMessageTime = new Date(lastAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                if (strConvId !== String(selectedConvId.value)) {
                    target.unread = true;
                }
            } else {
                // Ingest new conversation arriving dynamically from storefront
                const normalized = normalizeConversation({
                    conversation_id: strConvId,
                    id: strConvId,
                    ...raw,
                });
                normalized.unread = strConvId !== String(selectedConvId.value);
                conversations.value.unshift(normalized);
            }
        });

        conversations.value.sort((a, b) => (Number(b.last_message_at) || 0) - (Number(a.last_message_at) || 0));
    }

    // Action: Handle Live Agent Requests from Firebase
    function handleLiveRequests(requests) {
        if (!requests || typeof requests !== 'object') return;

        Object.entries(requests).forEach(([convId, req]) => {
            const target = conversations.value.find(c => String(c.id) === String(convId));
            if (target && req.status) {
                target.status = req.status;
                target.is_handoff = true;
                target.handoff_priority = 4;
            }
        });
    }

    // Action: Send Human Operator Reply
    async function sendReply(text) {
        const convId = selectedConvId.value;
        if (!convId || !text || !text.trim() || sendingReply.value) return;

        sendingReply.value = true;
        const cleanText = text.trim();

        // Optimistic UI append
        const tmpId = 'tmp-' + Date.now();
        messages.value.push({
            id: tmpId,
            sender: 'human_operator',
            text: cleanText,
            time: 'Just now',
            created_at: Date.now(),
        });

        const config = window.BotPlusAdminConfig;
        if (config && config.ajaxUrl) {
            try {
                const formData = new FormData();
                formData.append('action', 'botplus_inbox_send_reply');
                formData.append('nonce', config.nonce || '');
                formData.append('conversation_id', convId);
                formData.append('message', cleanText);

                const res = await fetch(config.ajaxUrl, { method: 'POST', body: formData });
                const json = await res.json();

                if (json.success && json.data) {
                    const idx = messages.value.findIndex(m => m.id === tmpId);
                    if (idx !== -1) {
                        messages.value[idx] = normalizeMessage(json.data);
                    }
                } else {
                    // Remove optimistic bubble if rejected by server (e.g. 422 Helpdesk gate)
                    messages.value = messages.value.filter(m => m.id !== tmpId);
                    const errMsg = json.data?.message || json.message || 'Replies are only permitted for active helpdesk cases.';
                    alert(errMsg);
                }
            } catch (err) {
                console.error('[BotPlus Inbox] Send reply error:', err);
                messages.value = messages.value.filter(m => m.id !== tmpId);
            } finally {
                sendingReply.value = false;
            }
        }
    }

    // Action: Claim Conversation Handoff
    async function claimConversation(conversationId) {
        const convId = conversationId || selectedConvId.value;
        if (!convId || claimingHandoff.value) return false;

        claimingHandoff.value = true;
        const config = window.BotPlusAdminConfig;
        if (config && config.ajaxUrl) {
            try {
                const formData = new FormData();
                formData.append('action', 'botplus_inbox_claim');
                formData.append('nonce', config.nonce || '');
                formData.append('conversation_id', String(convId));

                const res = await fetch(config.ajaxUrl, { method: 'POST', body: formData });
                const json = await res.json();

                if (json.success && json.data) {
                    const target = conversations.value.find(c => String(c.id || c.conversation_id) === String(convId));
                    if (target) {
                        target.status = 'active';
                        target.is_handoff = true;
                        if (!target.helpdesk) {
                            target.helpdesk = { case_id: json.data.case_id, status: 'active' };
                        } else {
                            target.helpdesk.case_id = json.data.case_id;
                            target.helpdesk.status = 'active';
                        }
                    }

                    // Append system message locally
                    messages.value.push({
                        id: 'sys-' + Date.now(),
                        sender: 'system',
                        text: 'Store Operator has joined the conversation.',
                        time: 'Just now',
                        created_at: Date.now(),
                    });

                    return true;
                }
            } catch (err) {
                console.error('[BotPlus Inbox] Claim conversation error:', err);
            } finally {
                claimingHandoff.value = false;
            }
        }
        return false;
    }

    // Action: Release Conversation back to AI
    async function releaseConversation(conversationId) {
        const convId = conversationId || selectedConvId.value;
        if (!convId || releasingHandoff.value) return false;

        releasingHandoff.value = true;
        const config = window.BotPlusAdminConfig;
        if (config && config.ajaxUrl) {
            try {
                const formData = new FormData();
                formData.append('action', 'botplus_inbox_release');
                formData.append('nonce', config.nonce || '');
                formData.append('conversation_id', String(convId));

                const res = await fetch(config.ajaxUrl, { method: 'POST', body: formData });
                const json = await res.json();

                if (json.success) {
                    const target = conversations.value.find(c => String(c.id || c.conversation_id) === String(convId));
                    if (target) {
                        target.status = 'resolved';
                        if (target.helpdesk) {
                            target.helpdesk.status = 'resolved';
                        }
                    }

                    messages.value.push({
                        id: 'sys-' + Date.now(),
                        sender: 'system',
                        text: 'Conversation released back to AI agent.',
                        time: 'Just now',
                        created_at: Date.now(),
                    });

                    return true;
                }
            } catch (err) {
                console.error('[BotPlus Inbox] Release conversation error:', err);
            } finally {
                releasingHandoff.value = false;
            }
        }
        return false;
    }

    // Action: Reopen Case
    async function reopenConversation(caseId) {
        if (!caseId) return false;
        const ok = await updateCaseStatus(caseId, 'open');
        if (ok) {
            const target = conversations.value.find(c => c.helpdesk?.case_id === caseId);
            if (target) {
                target.status = 'open';
                if (target.helpdesk) target.helpdesk.status = 'open';
            }
        }
        return ok;
    }

    // Action: Add Internal Note
    async function addCaseNote(caseId, noteText, noteType = 'internal') {
        if (!caseId || !noteText || !noteText.trim()) return false;

        const config = window.BotPlusAdminConfig;
        if (config && config.ajaxUrl) {
            try {
                const formData = new FormData();
                formData.append('action', 'botplus_inbox_add_note');
                formData.append('nonce', config.nonce || '');
                formData.append('case_id', String(caseId));
                formData.append('body', noteText.trim());
                formData.append('note_type', noteType);

                const res = await fetch(config.ajaxUrl, { method: 'POST', body: formData });
                const json = await res.json();
                return Boolean(json.success);
            } catch (err) {
                console.error('[BotPlus Inbox] Add case note error:', err);
                return false;
            }
        }
        return false;
    }

    // Action: Update Status
    async function updateCaseStatus(caseId, status) {
        if (!caseId || !status) return false;

        const config = window.BotPlusAdminConfig;
        if (config && config.ajaxUrl) {
            try {
                const formData = new FormData();
                formData.append('action', 'botplus_inbox_update_status');
                formData.append('nonce', config.nonce || '');
                formData.append('case_id', String(caseId));
                formData.append('status', status);

                const res = await fetch(config.ajaxUrl, { method: 'POST', body: formData });
                const json = await res.json();

                if (json.success) {
                    const target = conversations.value.find(c => c.helpdesk?.case_id === caseId);
                    if (target) {
                        target.status = status;
                    }
                    return true;
                }
            } catch (err) {
                console.error('[BotPlus Inbox] Update status error:', err);
            }
        }
        return false;
    }

    // Teardown
    function teardown() {
        if (unsubscribeMessages) unsubscribeMessages();
        if (unsubscribeSummaries) unsubscribeSummaries();
        if (unsubscribeRequests) unsubscribeRequests();
    }

    return {
        // State
        conversations,
        selectedConvId,
        messages,
        loadingConversations,
        loadingMessages,
        sendingReply,
        claimingHandoff,
        releasingHandoff,
        activeFilter,
        searchQuery,
        firebaseReady,

        // Getters & Helpers
        filterCounts,
        filteredConversations,
        currentConversation,
        hasLeadSignal,
        normalizeConversation,

        // Actions
        initInbox,
        loadConversations,
        selectConversation,
        sendReply,
        claimConversation,
        releaseConversation,
        reopenConversation,
        addCaseNote,
        updateCaseStatus,
        teardown,
    };
});
