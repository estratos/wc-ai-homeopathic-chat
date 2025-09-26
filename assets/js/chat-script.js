(function($) {
    'use strict';

    class HomeopathicChat {
        constructor() {
            this.isSending = false;
            this.retryCount = 0;
            this.maxRetries = 2;
            this.init();
        }

        init() {
            this.bindEvents();
            this.restoreChatState();
        }

        bindEvents() {
            // Toggle chat
            $(document).on('click', '#wc-ai-homeopathic-chat-toggle', () => {
                this.toggleChat();
            });

            // Close chat
            $(document).on('click', '.wc-ai-homeopathic-chat-close', () => {
                this.closeChat();
            });

            // Send message
            $(document).on('click', '.wc-ai-homeopathic-chat-send', () => {
                this.sendMessage();
            });

            // Enter key to send
            $(document).on('keydown', '.wc-ai-homeopathic-chat-input textarea', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Auto-resize textarea
            $(document).on('input', '.wc-ai-homeopathic-chat-input textarea', (e) => {
                this.autoResizeTextarea(e.target);
            });

            // Handle page refresh
            $(window).on('beforeunload', () => {
                this.saveChatState();
            });
        }

        toggleChat() {
            const $chat = $('#wc-ai-homeopathic-chat');
            const $toggle = $('#wc-ai-homeopathic-chat-toggle');
            
            $chat.slideToggle(300, () => {
                if ($chat.is(':visible')) {
                    $toggle.addClass('active');
                    this.focusTextarea();
                    this.scrollToBottom();
                } else {
                    $toggle.removeClass('active');
                }
            });
        }

        closeChat() {
            $('#wc-ai-homeopathic-chat').slideUp(300);
            $('#wc-ai-homeopathic-chat-toggle').removeClass('active');
        }

        async sendMessage() {
            if (this.isSending) return;

            const $input = $('.wc-ai-homeopathic-chat-input textarea');
            const message = $input.val().trim();

            // Validación del mensaje
            if (!this.validateMessage(message)) return;

            this.isSending = true;
            this.retryCount = 0;

            // Preparar UI para envío
            this.prepareForSending(message, $input);

            try {
                await this.attemptSendMessage(message);
            } catch (error) {
                this.handleSendError(error);
            } finally {
                this.cleanupAfterSend($input);
            }
        }

        validateMessage(message) {
            if (!message) {
                this.showTempMessage('empty');
                return false;
            }

            if (message.length > 500) {
                this.showTempMessage('too_long');
                return false;
            }

            return true;
        }

        prepareForSending(message, $input) {
            // Añadir mensaje del usuario
            this.addMessage('user', message);
            $input.val('');
            this.autoResizeTextarea($input[0]);

            // Deshabilitar entrada
            $('.wc-ai-homeopathic-chat-send').prop('disabled', true).addClass('loading');
            $input.prop('disabled', true);

            // Mostrar indicador de carga
            this.showLoadingIndicator();
        }

        async attemptSendMessage(message) {
            for (let attempt = 1; attempt <= this.maxRetries + 1; attempt++) {
                try {
                    const response = await this.makeApiRequest(message, attempt);
                    
                    if (response.success) {
                        this.handleSuccessResponse(response);
                        return;
                    } else {
                        throw new Error(response.data || 'Error desconocido');
                    }
                } catch (error) {
                    if (attempt > this.maxRetries) {
                        throw error;
                    }
                    
                    // Esperar antes del reintento
                    await this.delay(Math.pow(2, attempt - 1) * 1000);
                }
            }
        }

        makeApiRequest(message, attempt) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wc_ai_homeopathic_chat_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    timeout: 30000, // 30 segundos timeout
                    data: {
                        action: 'wc_ai_homeopathic_chat_send_message',
                        message: message,
                        nonce: wc_ai_homeopathic_chat_params.nonce
                    },
                    success: (response) => {
                        resolve(response);
                    },
                    error: (xhr, status, error) => {
                        let errorMessage = this.getErrorMessage(status, error);
                        reject(new Error(errorMessage));
                    }
                });
            });
        }

        getErrorMessage(status, error) {
            switch (status) {
                case 'timeout':
                    return wc_ai_homeopathic_chat_params.connection_error_text;
                case 'error':
                    if (error === 'Internal Server Error') {
                        return wc_ai_homeopathic_chat_params.api_error_text;
                    }
                    return wc_ai_homeopathic_chat_params.connection_error_text;
                default:
                    return wc_ai_homeopathic_chat_params.error_text;
            }
        }

        handleSuccessResponse(response) {
            // Remover indicador de carga
            this.hideLoadingIndicator();

            // Añadir respuesta del bot
            const cacheIndicator = response.data.from_cache ? 
                ' <small class="cache-indicator">(respuesta desde caché)</small>' : '';
            
            this.addMessage('bot', response.data.response + cacheIndicator);
        }

        handleSendError(error) {
            this.hideLoadingIndicator();
            
            // Mostrar mensaje de error específico
            const errorMessage = error.message || wc_ai_homeopathic_chat_params.error_text;
            this.addMessage('bot', '<em class="error-message">' + errorMessage + '</em>');
            
            console.error('Error en el chat:', error);
        }

        cleanupAfterSend($input) {
            this.isSending = false;
            $('.wc-ai-homeopathic-chat-send').prop('disabled', false).removeClass('loading');
            $input.prop('disabled', false);
            this.focusTextarea();
        }

        showLoadingIndicator() {
            const loadingHtml = `
                <div class="wc-ai-homeopathic-chat-message bot">
                    <div class="wc-ai-homeopathic-chat-loading">
                        <div class="loading-dots">
                            <span></span><span></span><span></span>
                        </div>
                        ${wc_ai_homeopathic_chat_params.loading_text}
                    </div>
                </div>
            `;
            $('.wc-ai-homeopathic-chat-messages').append(loadingHtml);
            this.scrollToBottom();
        }

        hideLoadingIndicator() {
            $('.wc-ai-homeopathic-chat-loading').parent().remove();
        }

        addMessage(sender, message) {
            const messageClass = `wc-ai-homeopathic-chat-message ${sender}`;
            const messageHtml = `<div class="${messageClass}">${this.nl2br(this.escapeHtml(message))}</div>`;
            
            $('.wc-ai-homeopathic-chat-messages').append(messageHtml);
            this.scrollToBottom();
        }

        showTempMessage(type) {
            const messages = {
                empty: wc_ai_homeopathic_chat_params.empty_message_text,
                too_long: 'El mensaje es demasiado largo. Máximo 500 caracteres.'
            };

            const $input = $('.wc-ai-homeopathic-chat-input textarea');
            const originalColor = $input.css('border-color');
            
            // Efecto visual de error
            $input.css('border-color', '#ff4444')
                  .addClass('error-shake');
            
            setTimeout(() => {
                $input.css('border-color', originalColor)
                      .removeClass('error-shake');
            }, 2000);

            // Mostrar mensaje temporal
            this.addMessage('bot', `<em class="warning-message">${messages[type]}</em>`);
        }

        autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            textarea.style.overflowY = textarea.scrollHeight > 120 ? 'auto' : 'hidden';
        }

        focusTextarea() {
            $('.wc-ai-homeopathic-chat-input textarea').focus();
        }

        scrollToBottom() {
            const $messages = $('.wc-ai-homeopathic-chat-messages');
            $messages.stop().animate({
                scrollTop: $messages[0].scrollHeight
            }, 400);
        }

        saveChatState() {
            if ($('#wc-ai-homeopathic-chat').is(':visible')) {
                sessionStorage.setItem('wc_ai_chat_open', 'true');
            }
        }

        restoreChatState() {
            if (sessionStorage.getItem('wc_ai_chat_open') === 'true') {
                setTimeout(() => {
                    $('#wc-ai-homeopathic-chat').show();
                    $('#wc-ai-homeopathic-chat-toggle').addClass('active');
                    sessionStorage.removeItem('wc_ai_chat_open');
                }, 500);
            }
        }

        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        nl2br(str) {
            return str.replace(/\n/g, '<br>');
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        new HomeopathicChat();
    });

})(jQuery);