jQuery(document).ready(function($) {
    'use strict';
    
    // Configuración global
    const wcAIChat = {
        init: function() {
            this.createChatInterface();
            this.bindEvents();
            this.sessionId = this.generateSessionId();
        },
        
        createChatInterface: function() {
            const chatHTML = `
                <div id="wc-ai-chat-container">
                    <button id="wc-ai-chat-button" aria-label="Abrir chat de asistente homeopático">💬</button>
                    <div id="wc-ai-chat-window">
                        <div class="wc-ai-chat-header">
                            <h3>Asistente Homeopático</h3>
                            <button class="wc-ai-chat-close" aria-label="Cerrar chat">×</button>
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
                                       placeholder="Describe tus síntomas o malestares..." 
                                       required
                                       aria-label="Escribe tu mensaje">
                                <button type="submit" class="wc-ai-chat-send-button">Enviar</button>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(chatHTML);
        },
        
        bindEvents: function() {
            const self = this;
            
            // Toggle chat window
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
            
            // Form submission
            $('.wc-ai-chat-input-form').on('submit', function(e) {
                e.preventDefault();
                self.handleFormSubmit($(this));
            });
            
            // Close when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#wc-ai-chat-container').length && 
                    $('#wc-ai-chat-window').hasClass('active')) {
                    $('#wc-ai-chat-window').removeClass('active');
                }
            });
        },
        
        handleFormSubmit: function(form) {
            const input = form.find('.wc-ai-chat-input-field');
            const message = input.val().trim();
            
            if (!message) return;
            
            // Clear input
            input.val('');
            
            // Add user message
            this.addMessage(message, 'user');
            
            // Show loading indicator
            this.showLoading();
            
            // Send to server
            this.sendMessageToServer(message);
        },
        
        addMessage: function(message, type) {
            const messageClass = type === 'user' ? 'user' : 'ai';
            const messageBubble = `
                <div class="wc-ai-message ${messageClass}">
                    <div class="wc-ai-message-bubble">${this.escapeHtml(message)}</div>
                </div>
            `;
            
            $('.wc-ai-chat-messages').append(messageBubble);
            this.scrollToBottom();
        },
        
        showLoading: function() {
            const loadingHTML = `
                <div class="wc-ai-message ai">
                    <div class="wc-ai-message-bubble">
                        <div class="wc-ai-loading">
                            <span>Pensando...</span>
                            <div class="wc-ai-loading-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('.wc-ai-chat-messages').append(loadingHTML);
            this.scrollToBottom();
        },
        
        removeLoading: function() {
            $('.wc-ai-loading').closest('.wc-ai-message').remove();
        },
        
        sendMessageToServer: function(message) {
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
                    
                    if (response.success) {
                        self.handleSuccessResponse(response.data);
                    } else {
                        self.handleErrorResponse(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    self.removeLoading();
                    self.handleAjaxError(xhr, status, error);
                }
            });
        },
        
        handleSuccessResponse: function(data) {
            // Add AI response
            this.addMessage(data.response, 'ai');
            
            // Add products if available
            if (data.products && data.products.length > 0) {
                this.showProducts(data.products);
            }
        },
        
        handleErrorResponse: function(data) {
            const errorMessage = data.message || 'Error al procesar tu mensaje.';
            this.addMessage('❌ ' + errorMessage, 'ai');
        },
        
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            
            let errorMessage = 'Error de conexión. ';
            
            if (xhr.status === 400) {
                errorMessage += 'Solicitud incorrecta. ';
            } else if (xhr.status === 403) {
                errorMessage += 'Acceso no autorizado. ';
            } else if (xhr.status === 500) {
                errorMessage += 'Error interno del servidor. ';
            }
            
            errorMessage += 'Por favor, intenta nuevamente.';
            
            this.addMessage('❌ ' + errorMessage, 'ai');
        },
        
        showProducts: function(products) {
            let productsHTML = `
                <div class="wc-ai-recommended-products">
                    <div class="wc-ai-products-title">💊 Productos recomendados:</div>
            `;
            
            products.forEach(product => {
                productsHTML += `
                    <div class="wc-ai-product-card">
                        <img src="${product.image || wc_ai_chat_params.placeholder_image}" 
                             alt="${product.name}" 
                             class="wc-ai-product-image">
                        <div class="wc-ai-product-info">
                            <h4 class="wc-ai-product-name">${this.escapeHtml(product.name)}</h4>
                            <div class="wc-ai-product-price">${product.price}</div>
                            <a href="${product.url}" class="wc-ai-product-link" target="_blank">Ver producto</a>
                        </div>
                    </div>
                `;
            });
            
            productsHTML += '</div>';
            
            $('.wc-ai-chat-messages').append(productsHTML);
            this.scrollToBottom();
        },
        
        scrollToBottom: function() {
            const messagesContainer = $('.wc-ai-chat-messages');
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
        },
        
        generateSessionId: function() {
            return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Inicializar el chat
    wcAIChat.init();
});