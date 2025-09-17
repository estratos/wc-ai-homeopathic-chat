(function($) {
    'use strict';
    
    const WC_AIChat = {
        init: function() {
            this.createChat();
            this.bindEvents();
            this.sessionId = 'chat_' + Date.now();
        },
        
        createChat: function() {
            const chatHTML = `
                <div id="wc-ai-chat-container">
                    <button id="wc-ai-chat-button" aria-label="Abrir chat">💬</button>
                    <div id="wc-ai-chat-window">
                        <div class="wc-ai-chat-header">
                            <h3>Asistente Homeopático</h3>
                            <button class="wc-ai-chat-close">×</button>
                        </div>
                        <div class="wc-ai-chat-messages">
                            <div class="wc-ai-message ai">
                                <div class="wc-ai-message-bubble">
                                    ¡Hola! Soy tu asistente homeopático. ¿En qué puedo ayudarte hoy?
                                </div>
                            </div>
                        </div>
                        <div class="wc-ai-chat-input">
                            <form class="wc-ai-chat-input-form">
                                <input type="text" class="wc-ai-chat-input-field" 
                                       placeholder="Describe tus síntomas..." 
                                       required>
                                <button type="submit">Enviar</button>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(chatHTML);
        },
        
        bindEvents: function() {
            const self = this;
            
            // Toggle chat
            $('#wc-ai-chat-button').on('click', function() {
                $('#wc-ai-chat-window').toggleClass('active');
                if ($('#wc-ai-chat-window').hasClass('active')) {
                    $('.wc-ai-chat-input-field').focus();
                }
            });
            
            // Close chat
            $('.wc-ai-chat-close').on('click', function() {
                $('#wc-ai-chat-window').removeClass('active');
            });
            
            // Form submit
            $('.wc-ai-chat-input-form').on('submit', function(e) {
                e.preventDefault();
                self.handleSubmit($(this));
            });
        },
        
        handleSubmit: function(form) {
            const input = form.find('.wc-ai-chat-input-field');
            const message = input.val().trim();
            
            if (!message) return;
            
            input.val('');
            this.addMessage(message, 'user');
            this.showLoading();
            this.sendMessage(message);
        },
        
        addMessage: function(message, type) {
            const bubble = `
                <div class="wc-ai-message ${type}">
                    <div class="wc-ai-message-bubble">${this.escapeHtml(message)}</div>
                </div>
            `;
            
            $('.wc-ai-chat-messages').append(bubble);
            this.scrollToBottom();
        },
        
        showLoading: function() {
            const loading = `
                <div class="wc-ai-message ai">
                    <div class="wc-ai-message-bubble">
                        <div class="wc-ai-loading">
                            <span>${wc_ai_chat_params.loading_text}</span>
                            <div class="wc-ai-loading-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('.wc-ai-chat-messages').append(loading);
            this.scrollToBottom();
        },
        
        removeLoading: function() {
            $('.wc-ai-loading').closest('.wc-ai-message').remove();
        },
        
        sendMessage: function(message) {
            const self = this;
            
            $.ajax({
                url: wc_ai_chat_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wc_ai_chat_send_message',
                    message: message,
                    session_id: this.sessionId,
                    nonce: wc_ai_chat_params.nonce
                },
                success: function(response) {
                    self.removeLoading();
                    
                    if (response.success && response.data) {
                        self.handleSuccess(response.data);
                    } else {
                        self.handleError(response.data || {});
                    }
                },
                error: function(xhr, status, error) {
                    self.removeLoading();
                    self.handleAjaxError(xhr, status, error);
                }
            });
        },
        
        handleSuccess: function(data) {
            this.addMessage(data.response, 'ai');
            
            if (data.products && data.products.length > 0) {
                this.showProducts(data.products);
            }
        },
        
        handleError: function(data) {
            const errorMsg = data.message || wc_ai_chat_params.error_text;
            this.addMessage('❌ ' + errorMsg, 'ai');
        },
        
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr);
            
            let errorMsg = wc_ai_chat_params.error_text;
            
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMsg = xhr.responseJSON.data.message;
            }
            
            this.addMessage('❌ ' + errorMsg, 'ai');
        },
        
        showProducts: function(products) {
            if (!products || products.length === 0) return;
            
            let html = `<div class="wc-ai-recommended-products">
                <div class="wc-ai-products-title">Productos recomendados:</div>`;
            
            products.forEach(product => {
                html += `
                    <div class="wc-ai-product-card">
                        <img src="${product.image}" alt="${product.name}" class="wc-ai-product-image">
                        <div class="wc-ai-product-info">
                            <h4>${this.escapeHtml(product.name)}</h4>
                            <div class="wc-ai-product-price">${product.price}</div>
                            <a href="${product.url}" target="_blank" class="wc-ai-product-link">Ver producto</a>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $('.wc-ai-chat-messages').append(html);
            this.scrollToBottom();
        },
        
        scrollToBottom: function() {
            const container = $('.wc-ai-chat-messages');
            container.scrollTop(container[0].scrollHeight);
        },
        
        escapeHtml: function(text) {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WC_AIChat.init();
    });
    
})(jQuery);