<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    config: {
        type: Object,
        default: () => ({})
    }
});

const messages = ref([]);
const input = ref('');
const isOpen = ref(false);
const isLoading = ref(false);

const apiUrl = ref('');
const chatbotId = ref(0);
const nonce = ref('');

onMounted(() => {
    apiUrl.value = props.config.apiUrl || '';
    chatbotId.value = props.config.chatbotId || 0;
    nonce.value = props.config.nonce || '';
});

async function sendMessage() {
    if (!input.value.trim() || isLoading.value) return;
    
    const userMessage = input.value;
    input.value = '';
    isLoading.value = true;
    
    messages.value.push({ role: 'user', content: userMessage });
    
    try {
        const response = await fetch(`${apiUrl.value}/chat/${chatbotId.value}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce.value,
            },
            body: JSON.stringify({
                message: userMessage,
                conversation_id: null,
                context: {
                    user: props.config.user,
                    cart: props.config.cart,
                }
            })
        });
        
        const data = await response.json();
        
        if (data.answer) {
            messages.value.push({ role: 'assistant', content: data.answer });
        }
        
        // Handle UI actions
        if (data.ui_action) {
            handleUiAction(data.ui_action, data.ui_payload);
        }
    } catch (error) {
        console.error('Chat error:', error);
        messages.value.push({ role: 'assistant', content: 'Sorry, something went wrong.' });
    } finally {
        isLoading.value = false;
    }
}

function handleUiAction(action, payload) {
    console.log('UI Action:', action, payload);
    // TODO: Implement product cards, lead forms, cart actions, etc.
}

function toggleChat() {
    isOpen.value = !isOpen.value;
}
</script>

<template>
    <div class="botplus-widget" :class="{ open: isOpen }">
        <!-- Chat Button -->
        <button 
            class="botplus-chat-button" 
            @click="toggleChat"
            :aria-label="isOpen ? 'Close chat' : 'Open chat'"
        >
            <svg v-if="!isOpen" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <!-- Chat Window -->
        <div v-show="isOpen" class="botplus-chat-window">
            <div class="botplus-chat-header">
                <h3>BotPlus Agent</h3>
                <button class="botplus-close-btn" @click="toggleChat" aria-label="Close chat">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <div class="botplus-chat-messages">
                <div 
                    v-for="(message, index) in messages" 
                    :key="index" 
                    class="botplus-message" 
                    :class="message.role"
                >
                    <div class="botplus-message-content" v-html="message.content"></div>
                </div>
                <div v-if="isLoading" class="botplus-message assistant loading">
                    <div class="botplus-typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
            
            <div class="botplus-chat-input">
                <input 
                    type="text" 
                    v-model="input" 
                    @keyup.enter="sendMessage"
                    placeholder="Type a message..."
                    :disabled="isLoading"
                />
                <button @click="sendMessage" :disabled="isLoading || !input.trim()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.botplus-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 99999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.botplus-chat-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #3B82F6;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    transition: transform 0.2s, box-shadow 0.2s;
}

.botplus-chat-button:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.5);
}

.botplus-chat-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 380px;
    height: 500px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}

.botplus-chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.botplus-chat-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.botplus-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #6b7280;
    padding: 4px;
    border-radius: 8px;
    transition: background 0.2s;
}

.botplus-close-btn:hover {
    background: #e5e7eb;
}

.botplus-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.botplus-message {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 16px;
    line-height: 1.5;
    font-size: 14px;
}

.botplus-message.user {
    align-self: flex-end;
    background: #3B82F6;
    color: white;
    border-bottom-right-radius: 4px;
}

.botplus-message.assistant {
    align-self: flex-start;
    background: #f3f4f6;
    color: #111827;
    border-bottom-left-radius: 4px;
}

.botplus-typing-indicator {
    display: flex;
    gap: 4px;
    padding: 4px 0;
}

.botplus-typing-indicator span {
    width: 8px;
    height: 8px;
    background: #9ca3af;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out;
}

.botplus-typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.botplus-typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-6px); }
}

.botplus-chat-input {
    display: flex;
    padding: 16px;
    border-top: 1px solid #e5e7eb;
    gap: 8px;
}

.botplus-chat-input input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 24px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}

.botplus-chat-input input:focus {
    border-color: #3B82F6;
}

.botplus-chat-input button {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #3B82F6;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.botplus-chat-input button:hover:not(:disabled) {
    background: #2563eb;
}

.botplus-chat-input button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>