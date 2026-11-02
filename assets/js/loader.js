/**
 * BotPlus Storefront Iframe Loader & Context Bridge (Vanilla JS)
 * Lightweight, zero-dependency host bridge connecting WooCommerce storefront context to the BotPlus chat iframe.
 */
(function() {
    'use strict';

    if (window.__botplus_loader_initialized) {
        return;
    }
    window.__botplus_loader_initialized = true;

    function init() {
        const config = window.BotPlusConfig || {};
        const chatbotId = config.chatbotId ? String(config.chatbotId) : null;
        const hostOrigin = (config.apiUrl || 'https://botplus.app').replace(/\/+$/, '');
        const apiUrl = hostOrigin;
        const siteUrl = (config.siteUrl || window.location.origin).replace(/\/+$/, '');
        const restUrl = (config.restUrl || (siteUrl + '/wp-json/botplus/v1/')).replace(/\/+$/, '') + '/';
        const isWooCommerce = Boolean(config.isWooCommerce);

        if (!chatbotId) {
            return;
        }

        const siteHost = window.location.host;
        const storageKey = 'botplus_wp_session_' + siteHost.replace(/[^a-z0-9_.-]/gi, '_') + '_' + chatbotId;
        const convKey = 'botplus_wp_conv_' + siteHost.replace(/[^a-z0-9_.-]/gi, '_') + '_' + chatbotId;

        const isMobile = () => window.innerWidth <= 640;
        let isChatOpen = false;
        let widgetPosition = (config.widgetSettings && (config.widgetSettings.position === 'left' || config.widgetSettings.widget_position === 'left')) ? 'left' : 'right';
        let closedSize = { width: '100px', height: '100px' };
        if (config.widgetSettings && config.widgetSettings.widget_text) {
            const text = String(config.widgetSettings.widget_text).trim();
            const estimatedWidth = 120 + Math.max(0, text.length * 7);
            const width = Math.max(240, Math.min(estimatedWidth, 420));
            const height = text.length > 28 ? 164 : 148;
            closedSize = { width: `${width}px`, height: `${height}px` };
        }

        let lastContextHash = null;
        let isReadingContext = false;
        let queuedPush = false;
        let contextPushTimer = null;

        // Session caching helpers
        function readSessionCache() {
            try {
                const raw = window.localStorage.getItem(storageKey);
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                if (parsed.session_expires_at && Date.parse(parsed.session_expires_at) <= Date.now()) {
                    window.localStorage.removeItem(storageKey);
                    return {};
                }
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        }

        function rememberSessionPayload(payload) {
            if (!payload || !payload.session_key) return;
            try {
                window.localStorage.setItem(storageKey, JSON.stringify({
                    sessionKey: payload.session_key,
                    bootstrapToken: payload.bootstrap_token || null,
                    session_expires_at: payload.session_expires_at || null,
                    updatedAt: new Date().toISOString(),
                }));
            } catch (e) {}
        }

        function getOrCreateSessionKey() {
            return readSessionCache().sessionKey || null;
        }

        function getBootstrapToken() {
            return readSessionCache().bootstrapToken || null;
        }

        function getStoredConversationId() {
            try {
                return window.localStorage.getItem(convKey) || null;
            } catch (e) {
                return null;
            }
        }

        function storeConversationId(conversationId) {
            if (!conversationId) return;
            try {
                window.localStorage.setItem(convKey, String(conversationId));
            } catch (e) {}
        }

        function clearStoredConversationId() {
            try {
                window.localStorage.removeItem(convKey);
            } catch (e) {}
        }

        // Create the iframe
        const iframe = document.createElement('iframe');
        iframe.id = 'botplus-chat-iframe';
        iframe.title = 'BotPlus AI Chat';
        iframe.src = hostOrigin + '/embed/chat?bot=' + encodeURIComponent(chatbotId);
        iframe.setAttribute('allow', 'clipboard-write');
        iframe.setAttribute('loading', 'lazy');

        // Initial Styles
        Object.assign(iframe.style, {
            position: 'fixed',
            zIndex: '999999',
            border: 'none',
            background: 'transparent',
            colorScheme: 'none',
            overflow: 'hidden',
            width: closedSize.width,
            height: closedSize.height,
            bottom: '20px',
            right: widgetPosition === 'left' ? 'auto' : '20px',
            left: widgetPosition === 'left' ? '20px' : 'auto',
            top: 'auto',
            maxWidth: '100vw',
            maxHeight: '100vh',
            transition: 'width 0.2s ease, height 0.2s ease, transform 0.2s ease',
            pointerEvents: 'auto',
        });

        function applyPlacement() {
            if (isChatOpen) {
                if (isMobile()) {
                    Object.assign(iframe.style, {
                        width: '100vw',
                        height: '100vh',
                        top: '0',
                        left: '0',
                        right: '0',
                        bottom: '0',
                        maxWidth: '100vw',
                        maxHeight: '100vh',
                        borderRadius: '0',
                    });
                } else {
                    Object.assign(iframe.style, {
                        width: '400px',
                        height: '680px',
                        maxWidth: 'calc(100vw - 32px)',
                        maxHeight: 'calc(100vh - 40px)',
                        top: 'auto',
                        bottom: '20px',
                        borderRadius: '16px',
                        left: widgetPosition === 'left' ? '20px' : 'auto',
                        right: widgetPosition === 'right' ? '20px' : 'auto',
                    });
                }
            } else {
                Object.assign(iframe.style, {
                    width: closedSize.width,
                    height: closedSize.height,
                    top: 'auto',
                    bottom: '20px',
                    borderRadius: '0',
                    left: widgetPosition === 'left' ? '20px' : 'auto',
                    right: widgetPosition === 'right' ? '20px' : 'auto',
                    maxWidth: 'calc(100vw - 20px)',
                    maxHeight: '180px',
                });
            }
        }

        // Cart Data Extraction via dedicated BotPlus REST endpoint
        function readStorefrontCart() {
            if (!isWooCommerce) {
                return Promise.resolve(null);
            }

            const cartEndpoint = restUrl + 'storefront/cart?_=' + Date.now();
            const headers = {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
            };
            if (config.nonce) {
                headers['X-WP-Nonce'] = config.nonce;
            }

            return fetch(cartEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: headers,
            })
            .then(function(res) {
                return res.ok ? res.json() : null;
            })
            .catch(function() {
                return null;
            });
        }

        function buildStorefrontContext() {
            return readStorefrontCart().then(function(cartData) {
                if (cartData && cartData.nonce) {
                    config.nonce = cartData.nonce;
                }
                if (cartData && cartData.user !== undefined) {
                    config.user = cartData.user;
                }
                const user = config.user || {};
                const activeCart = cartData || config.cart || null;
                const cartHash = (activeCart && activeCart.cart_hash) || config.cartToken || null;

                return {
                    type: 'botplus-storefront-context',
                    platform: 'wordpress',
                    shopDomain: siteHost,
                    customerId: user.id ? String(user.id) : null,
                    cartToken: cartHash,
                    cart_hash: cartHash,
                    sessionKey: getOrCreateSessionKey(),
                    bootstrapToken: getBootstrapToken(),
                    conversationId: getStoredConversationId(),
                    landingPage: window.location.href,
                    referrer: document.referrer || null,
                    cart: activeCart ? {
                        token: activeCart.cart_hash || cartHash,
                        currency: activeCart.currency || 'USD',
                        item_count: activeCart.item_count || 0,
                        subtotal_amount: activeCart.subtotal_amount ?? 0,
                        items: Array.isArray(activeCart.items) ? activeCart.items : [],
                    } : null,
                };
            });
        }

        function buildContextHash(context) {
            const cart = context.cart || {};
            const lineFingerprint = Array.isArray(cart.items)
                ? cart.items.map(function(item) {
                    return (item.variant_id || item.id) + ':' + item.quantity;
                }).join('|')
                : '';

            return JSON.stringify({
                host: context.shopDomain,
                customerId: context.customerId,
                cartHash: context.cart_hash,
                sessionKey: context.sessionKey,
                itemCount: cart.item_count || 0,
                subtotal: cart.subtotal_amount ?? 0,
                lines: lineFingerprint,
            });
        }

        function pushStorefrontContext(force) {
            if (isReadingContext) {
                queuedPush = force || queuedPush;
                return;
            }

            isReadingContext = true;

            buildStorefrontContext().then(function(context) {
                const nextHash = buildContextHash(context);
                if (!force && nextHash === lastContextHash) {
                    return;
                }

                lastContextHash = nextHash;

                if (iframe.contentWindow) {
                    iframe.contentWindow.postMessage(context, hostOrigin);
                }
            }).finally(function() {
                isReadingContext = false;
                if (queuedPush) {
                    queuedPush = false;
                    pushStorefrontContext(true);
                }
            });
        }

        function scheduleContextPush(force, delay) {
            if (contextPushTimer) {
                window.clearTimeout(contextPushTimer);
            }
            contextPushTimer = window.setTimeout(function() {
                contextPushTimer = null;
                pushStorefrontContext(Boolean(force));
            }, typeof delay === 'number' ? delay : 250);
        }

        // Install WooCommerce Storefront Observers
        function installWooCommerceObservers() {
            if (!isWooCommerce) {
                return;
            }

            // 1. jQuery event observers (WooCommerce standard in themes)
            if (window.jQuery) {
                window.jQuery(document.body).on(
                    'added_to_cart removed_from_cart updated_cart_totals wc_fragments_refreshed wc_fragments_loaded',
                    function() {
                        scheduleContextPush(true, 200);
                    }
                );
            }

            // 2. Window.fetch observer for cart mutations
            const originalFetch = window.fetch ? window.fetch.bind(window) : null;
            if (originalFetch) {
                window.fetch = function(input, init) {
                    const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                    const method = String(init && init.method ? init.method : 'GET').toUpperCase();
                    const isCartMutation = method !== 'GET' && (
                        url.includes('wc-ajax=add_to_cart') ||
                        url.includes('wc-ajax=update_order_review') ||
                        url.includes('/wp-json/wc/store/v1/cart')
                    );

                    return originalFetch(input, init).then(function(response) {
                        if (isCartMutation && response && response.ok) {
                            scheduleContextPush(true, 300);
                        }
                        return response;
                    });
                };
            }

            // 3. XMLHttpRequest observer
            const originalOpen = XMLHttpRequest.prototype.open;
            const originalSend = XMLHttpRequest.prototype.send;

            XMLHttpRequest.prototype.open = function(method, url) {
                this.__botplusMethod = String(method || 'GET').toUpperCase();
                this.__botplusUrl = String(url || '');
                return originalOpen.apply(this, arguments);
            };

            XMLHttpRequest.prototype.send = function() {
                const isCartMutation = this.__botplusMethod !== 'GET' && (
                    this.__botplusUrl.includes('wc-ajax=add_to_cart') ||
                    this.__botplusUrl.includes('wc-ajax=update_order_review') ||
                    this.__botplusUrl.includes('/wp-json/wc/store/v1/cart')
                );

                if (isCartMutation) {
                    this.addEventListener('loadend', function() {
                        if (this.status >= 200 && this.status < 400) {
                            scheduleContextPush(true, 300);
                        }
                    }, { once: true });
                }

                return originalSend.apply(this, arguments);
            };

            // 4. Native DOM submit / click observer
            document.addEventListener('submit', function(event) {
                const form = event.target;
                const action = form && form.action ? String(form.action) : '';
                if (action.includes('add-to-cart') || (form && form.classList && form.classList.contains('cart'))) {
                    scheduleContextPush(true, 500);
                }
            }, true);

            document.addEventListener('click', function(event) {
                const trigger = event.target && event.target.closest
                    ? event.target.closest('[name="add-to-cart"], .add_to_cart_button, .ajax_add_to_cart')
                    : null;
                if (trigger) {
                    scheduleContextPush(true, 600);
                }
            }, true);
        }

        // Listen for messages from the BotPlus iframe
        window.addEventListener('message', function(event) {
            // Verify origin matches the BotPlus host
            if (!event.origin || event.origin !== hostOrigin) {
                return;
            }

            const data = event.data;
            if (!data || typeof data !== 'object') {
                return;
            }

            // Initialization from iframe (position & text preview dimensions)
            if (data.type === 'botplus-init') {
                widgetPosition = data.position === 'left' ? 'left' : 'right';

                if (data.hasWidgetText) {
                    const text = String(data.widgetText || '').trim();
                    const estimatedWidth = 120 + Math.max(0, text.length * 7);
                    const width = Math.max(240, Math.min(estimatedWidth, 420));
                    const height = text.length > 28 ? 164 : 148;
                    closedSize = { width: `${width}px`, height: `${height}px` };
                } else {
                    closedSize = { width: '100px', height: '100px' };
                }

                applyPlacement();
            }

            // Resizing on chat open / close
            if (data.type === 'botplus-resize') {
                isChatOpen = Boolean(data.isOpen);
                applyPlacement();
                if (isChatOpen) {
                    dismissRecoveryBanner(false);
                    scheduleContextPush(true, 50);
                }
            }

            // Explicit cart refresh request from iframe
            if (data.type === 'botplus-refresh-cart-context') {
                pushStorefrontContext(true);
            }

            // Cart mutation actions from iframe (Add to Cart, Update Quantity, Remove Item)
            if (data.type === 'botplus-cart-action') {
                const requestId = data.requestId;
                const actionRestUrl = restUrl + 'storefront/cart/action';

                fetch(actionRestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce || '',
                    },
                    body: JSON.stringify({
                        action: data.action,
                        productId: data.productId || data.product_id || data.variantId,
                        variantId: data.variantId || data.variant_id,
                        lineKey: data.lineKey || data.line_key,
                        quantity: data.quantity !== undefined ? data.quantity : 1,
                    }),
                    credentials: 'same-origin',
                })
                .then(function(res) {
                    return res.json().then(function(body) {
                        return { ok: res.ok, status: res.status, body: body };
                    });
                })
                .then(function(result) {
                    if (result.ok && result.body && result.body.success) {
                        // 1. Notify iframe of action success
                        if (iframe.contentWindow) {
                            iframe.contentWindow.postMessage({
                                type: 'botplus-cart-action-result',
                                requestId: requestId,
                                success: true,
                            }, hostOrigin);
                        }

                        // 2. Trigger WooCommerce jQuery events to refresh header/drawer mini-carts
                        if (window.jQuery) {
                            const wcBody = window.jQuery(document.body);
                            wcBody.trigger('wc_fragment_refresh');
                            if (data.action === 'remove') {
                                wcBody.trigger('removed_from_cart');
                            } else {
                                wcBody.trigger('added_to_cart');
                            }
                        }

                        // 3. Dispatch fresh storefront context to iframe immediately
                        scheduleContextPush(true, 50);
                    } else {
                        const errMsg = (result.body && result.body.message) || 'Cart action failed.';
                        if (iframe.contentWindow) {
                            iframe.contentWindow.postMessage({
                                type: 'botplus-cart-action-result',
                                requestId: requestId,
                                success: false,
                                message: errMsg,
                            }, hostOrigin);
                        }
                    }
                })
                .catch(function(err) {
                    if (iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            type: 'botplus-cart-action-result',
                            requestId: requestId,
                            success: false,
                            message: err.message || 'Cart request failed.',
                        }, hostOrigin);
                    }
                });
            }

            // Session persistence updates from iframe
            if (data.type === 'botplus-storefront-session') {
                rememberSessionPayload(data);
                if (data.conversation_id) {
                    storeConversationId(data.conversation_id);
                }
            }

            // Reset conversation
            if (data.type === 'botplus-clear-conversation') {
                clearStoredConversationId();
            }
        });

        window.addEventListener('resize', function() {
            if (isChatOpen) {
                applyPlacement();
            }
        });

        window.addEventListener('pageshow', function() {
            pushStorefrontContext(isChatOpen);
        });

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                pushStorefrontContext(isChatOpen);
            }
        });

        // ---------------------------------------------------------------------
        // Smart Recovery Workflow: Non-Intrusive Triggers & UX Guardrails
        // ---------------------------------------------------------------------
        let activeRecoveryBanner = null;
        let recoveryDismissedUntil = 0;
        let hesitationTimer = null;
        const DISMISSAL_COOLDOWN_MS = 24 * 60 * 60 * 1000; // 24 hours
        const HESITATION_DELAY_MS = 75 * 1000; // 75 seconds

        try {
            const storedDismissal = localStorage.getItem('botplus_cart_dismissed_until');
            if (storedDismissal) {
                recoveryDismissedUntil = parseInt(storedDismissal, 10) || 0;
            }
        } catch (_) {}

        function isDismissedOnCooldown() {
            return Date.now() < recoveryDismissedUntil;
        }

        function setDismissalCooldown() {
            recoveryDismissedUntil = Date.now() + DISMISSAL_COOLDOWN_MS;
            try {
                localStorage.setItem('botplus_cart_dismissed_until', String(recoveryDismissedUntil));
            } catch (_) {}
        }

        function dismissRecoveryBanner(permanently) {
            if (activeRecoveryBanner && activeRecoveryBanner.parentNode) {
                activeRecoveryBanner.parentNode.removeChild(activeRecoveryBanner);
                activeRecoveryBanner = null;
            }
            if (permanently) {
                setDismissalCooldown();
                const botId = config.chatbotId;
                const conversationId = getStoredConversationId();
                if (botId && conversationId && apiUrl) {
                    fetch(apiUrl.replace(/\/+$/, '') + '/api/chat/' + botId + '/cart-recovery/dismiss', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            conversation_id: conversationId,
                            session_key: getOrCreateSessionKey(),
                            host: siteHost,
                        }),
                    }).catch(function() {});
                }
            }
        }

        function showRecoveryBanner(cart) {
            if (isChatOpen || activeRecoveryBanner || isDismissedOnCooldown()) {
                return;
            }

            if (!cart || !cart.item_count || cart.item_count <= 0) {
                return;
            }

            const banner = document.createElement('div');
            banner.id = 'botplus-cart-recovery-toast';
            banner.style.cssText = [
                'position: fixed',
                'bottom: 95px',
                'right: 20px',
                'width: min(360px, calc(100vw - 32px))',
                'background: #ffffff',
                'border: 1px solid #e2e8f0',
                'border-radius: 12px',
                'box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)',
                'padding: 16px',
                'z-index: 999998',
                'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                'font-size: 13px',
                'color: #0f172a',
                'transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
                'opacity: 0',
                'transform: translateY(12px)',
            ].join(';');

            banner.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                    <div style="font-weight: 700; font-size: 14px; color: #0f172a;">Need a hand later?</div>
                    <button id="botplus-recovery-close-btn" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #94a3b8; line-height: 1; padding: 2px;">&times;</button>
                </div>
                <p style="margin: 6px 0 12px 0; font-size: 12px; color: #64748b; line-height: 1.45;">
                    Save your cart so you can easily return later or receive quick assistance from our team.
                </p>
                <form id="botplus-recovery-form" style="display: flex; flex-direction: column; gap: 8px; margin: 0;">
                    <input 
                        type="email" 
                        required 
                        placeholder="Enter your email" 
                        id="botplus-recovery-email-input"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 12px; box-sizing: border-box; outline: none;"
                    />
                    <button 
                        type="submit" 
                        id="botplus-recovery-submit-btn"
                        style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer;"
                    >
                        Save My Cart
                    </button>
                </form>
            `;

            document.body.appendChild(banner);
            activeRecoveryBanner = banner;

            requestAnimationFrame(function() {
                banner.style.opacity = '1';
                banner.style.transform = 'translateY(0)';
            });

            const closeBtn = banner.querySelector('#botplus-recovery-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    dismissRecoveryBanner(true);
                });
            }

            const form = banner.querySelector('#botplus-recovery-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const emailInput = banner.querySelector('#botplus-recovery-email-input');
                    const email = emailInput ? emailInput.value.trim() : '';
                    if (!email) return;

                    const submitBtn = banner.querySelector('#botplus-recovery-submit-btn');
                    if (submitBtn) {
                        submitBtn.textContent = 'Saving...';
                        submitBtn.disabled = true;
                    }

                    const botId = config.chatbotId;
                    const conversationId = getStoredConversationId();
                    if (botId && apiUrl) {
                        fetch(apiUrl.replace(/\/+$/, '') + '/api/chat/' + botId + '/cart-recovery/capture', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                conversation_id: conversationId,
                                session_key: getOrCreateSessionKey(),
                                host: siteHost,
                                cart_hash: cart.cart_hash || null,
                                name: email.split('@')[0],
                                email: email,
                            }),
                        }).then(function(res) {
                            return res.ok ? res.json() : null;
                        }).then(function() {
                            setDismissalCooldown();
                            banner.innerHTML = `
                                <div style="text-align: center; padding: 12px 0;">
                                    <div style="font-size: 20px; margin-bottom: 4px; color: #059669;">✓</div>
                                    <div style="font-weight: 700; font-size: 13px; color: #059669;">Cart Saved!</div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">We've saved your items for you.</div>
                                </div>
                            `;
                            setTimeout(function() {
                                dismissRecoveryBanner(false);
                            }, 2500);
                        }).catch(function() {
                            dismissRecoveryBanner(true);
                        });
                    } else {
                        dismissRecoveryBanner(true);
                    }
                });
            }
        }

        // Trigger 1: Exit-Intent Listener (Desktop Mouseleave)
        function installExitIntentListener() {
            document.documentElement.addEventListener('mouseleave', function(e) {
                if (e.clientY <= 0) {
                    readStorefrontCart().then(function(cart) {
                        if (cart && cart.item_count > 0) {
                            showRecoveryBanner(cart);
                        }
                    });
                }
            });
        }

        // Trigger 2: Hesitation / Dwell-Time Inactivity Timer
        function resetHesitationTimer() {
            clearTimeout(hesitationTimer);
            if (isChatOpen || isDismissedOnCooldown()) {
                return;
            }

            hesitationTimer = setTimeout(function() {
                readStorefrontCart().then(function(cart) {
                    if (cart && cart.item_count > 0) {
                        showRecoveryBanner(cart);
                    }
                });
            }, HESITATION_DELAY_MS);
        }

        function installHesitationListeners() {
            const activityEvents = ['scroll', 'mousemove', 'keydown', 'click', 'touchstart'];
            activityEvents.forEach(function(evt) {
                window.addEventListener(evt, resetHesitationTimer, { passive: true });
            });
        }

        // Trigger 3: Silent Pre-Checkout Email Blur Hook
        function installPrecheckoutBlurListener() {
            document.addEventListener('focusout', function(e) {
                if (e.target && (e.target.id === 'billing_email' || e.target.name === 'billing_email')) {
                    const email = e.target.value ? e.target.value.trim() : '';
                    if (email && email.indexOf('@') > 0 && email.indexOf('.') > 0) {
                        const botId = config.chatbotId;
                        const conversationId = getStoredConversationId();
                        if (botId && apiUrl) {
                            fetch(apiUrl.replace(/\/+$/, '') + '/api/chat/' + botId + '/cart-recovery/capture', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify({
                                    conversation_id: conversationId,
                                    session_key: getOrCreateSessionKey(),
                                    host: siteHost,
                                    name: email.split('@')[0],
                                    email: email,
                                }),
                            }).catch(function() {});
                        }
                    }
                }
            });
        }

        // Initialize observers & append iframe to DOM
        installWooCommerceObservers();
        installExitIntentListener();
        installHesitationListeners();
        installPrecheckoutBlurListener();

        iframe.addEventListener('load', function() {
            setTimeout(function() {
                pushStorefrontContext(true);
            }, 300);
        });

        if (document.body) {
            document.body.appendChild(iframe);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.appendChild(iframe);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
