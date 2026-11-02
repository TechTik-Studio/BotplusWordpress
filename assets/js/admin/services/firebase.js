import { initializeApp, getApps } from 'firebase/app';
import { getAuth, signInWithCustomToken } from 'firebase/auth';
import { getDatabase, ref, onValue, off } from 'firebase/database';

let appInstance = null;
let authInstance = null;
let databaseInstance = null;

/**
 * Initialize named Firebase App for WordPress Admin and authenticate with custom token.
 *
 * @param {Object} firebaseConfig
 * @param {string} customToken
 * @returns {Promise<{ auth: Object, database: Object }>}
 */
export async function initAdminFirebase(firebaseConfig, customToken) {
    if (!firebaseConfig || !firebaseConfig.apiKey) {
        throw new Error('Firebase configuration is missing or invalid.');
    }

    const existingApps = getApps();
    const appName = 'botplus-wp-admin';
    const foundApp = existingApps.find(a => a.name === appName);

    if (foundApp) {
        appInstance = foundApp;
    } else {
        appInstance = initializeApp(firebaseConfig, appName);
    }

    authInstance = getAuth(appInstance);
    databaseInstance = getDatabase(appInstance);

    if (customToken && (!authInstance.currentUser || authInstance.currentUser.isAnonymous)) {
        try {
            await signInWithCustomToken(authInstance, customToken);
        } catch (err) {
            console.warn('[BotPlus Firebase] Custom token sign-in notice:', err.message);
        }
    }

    const isAuthenticated = Boolean(authInstance.currentUser && !authInstance.currentUser.isAnonymous);

    return {
        auth: authInstance,
        database: isAuthenticated ? databaseInstance : null,
    };
}

/**
 * Subscribe to messages on a specific conversation node.
 *
 * @param {Object} database
 * @param {string} chatsRoot
 * @param {string|number} conversationId
 * @param {Function} onMessagesReceived
 * @returns {Function} Unsubscribe function
 */
export function subscribeToConversationMessages(database, chatsRoot, conversationId, onMessagesReceived) {
    if (!database || !chatsRoot || !conversationId) return () => {};

    const cleanRoot = String(chatsRoot).replace(/^\/+|\/+$/g, '');
    const messagesRef = ref(database, `${cleanRoot}/${conversationId}/messages`);

    onValue(messagesRef, (snapshot) => {
        const val = snapshot.val();
        if (val && typeof onMessagesReceived === 'function') {
            onMessagesReceived(val);
        }
    }, (error) => {
        console.warn('[BotPlus Firebase] Conversation messages subscription notice:', error.message);
    });

    return () => off(messagesRef);
}

/**
 * Subscribe to conversation summaries at the chatbot chats root.
 *
 * @param {Object} database
 * @param {string} chatsRoot
 * @param {Function} onSummariesReceived
 * @returns {Function} Unsubscribe function
 */
export function subscribeToConversationsSummaries(database, chatsRoot, onSummariesReceived) {
    if (!database || !chatsRoot) return () => {};

    const cleanRoot = String(chatsRoot).replace(/^\/+|\/+$/g, '');
    const rootRef = ref(database, cleanRoot);

    onValue(rootRef, (snapshot) => {
        const val = snapshot.val();
        if (val && typeof onSummariesReceived === 'function') {
            onSummariesReceived(val);
        }
    }, (error) => {
        console.warn('[BotPlus Firebase] Summaries subscription notice:', error.message);
    });

    return () => off(rootRef);
}

/**
 * Subscribe to agent handoff requests node.
 *
 * @param {Object} database
 * @param {string} agentRequestsRoot
 * @param {Function} onRequestsReceived
 * @returns {Function} Unsubscribe function
 */
export function subscribeToAgentRequests(database, agentRequestsRoot, onRequestsReceived) {
    if (!database || !agentRequestsRoot) return () => {};

    const cleanRoot = String(agentRequestsRoot).replace(/^\/+|\/+$/g, '');
    const requestsRef = ref(database, cleanRoot);

    onValue(requestsRef, (snapshot) => {
        const val = snapshot.val();
        if (typeof onRequestsReceived === 'function') {
            onRequestsReceived(val || {});
        }
    }, (error) => {
        console.warn('[BotPlus Firebase] Agent requests subscription notice:', error.message);
    });

    return () => off(requestsRef);
}

