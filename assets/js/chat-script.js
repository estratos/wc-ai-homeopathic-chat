(function($) {
    'use strict';

    class FloatingHomeopathicChat {
        constructor() {
            this.isOpen = false;
            this.isMinimized = false;
            this.isSending = false;
            this.init();
        }

        init() {
            this.createChat();
            this.bindEvents();
            this.restoreState();
        }

        createChat() {
            // El HTML ya está incluido en el PHP, solo inicializamos
            this.$container = $('#wc-ai-homeopathic-chat-container');
            this.$launcher = $('#wc-ai-chat-launcher');
            this.$window = $('#wc-ai-chat-window');
            this.$messages = $('.wc-ai-chat-messages');
            this.$textarea = $('.wc-ai-chat-input textarea');
            this.$sendBtn = $('.wc-ai-chat-send');
            this.$whatsappBtn = $('.wc-ai-whatsapp-fallback');
            
            // Aplicar posición
            this.applyPosition();
        }

        applyPosition() {
            const position = wc_ai_homeopathic_chat_params.position;
            this.$container.removeClass('wc-ai-chat-position-left wc-ai-chat-position-right')
                          .addClass('wc-ai-chat-position-' + position);
        }

        bindEvents() {
            // Lanzador
            this.$launcher.on('click', () => this.toggleChat());
            
            // Ventana del chat
            this.$window.on('click', (e) => e.stopPropagation());
            
            // Botones de ventana
            $('.wc-ai-chat-minimize').on('click', () => this.toggleMinimize());
            $('.wc-ai-chat-close').on('click', () => this.closeChat());
            
            // Envío de mensajes
            this.$sendBtn.on('click', () => this.sendMessage());
            this.$textarea.on('keydown', (e) => this.handleKeydown(e));
            this.$textarea.on('input', () => this.autoResize());
            
            // WhatsApp fallback
            this.$whatsappBtn.on('click', () => this.openWhatsApp());
            
            // Cerrar al hacer click fuera
            $(document).on('click', () => this.closeChat());
        }

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            this.isOpen = true;
            this.isMinimized = false;
            
            this.$window.addClass('active').removeClass('minimized');
            this.$launcher.addClass('active');
            
            this.focusInput();
            this.scrollToBottom();
            this.saveState();
        }

        closeChat() {
            this.isOpen = false;
            this.isMinimized = false;
            
            this.$window.removeClass('active minimized');
            this.$launcher.removeClass('active');
            
            this.saveState();
        }

        toggleMinimize() {
            this.isMinimized = !this.isMinimized;
            
            if (this.isMinimized) {
                this.$window.addClass('minimized');
            } else {
                this.$window.removeClass('minimized');
                this.focusInput();
            }
            
            this.saveState();
        }

        async sendMessage() {
            if (this.isSending) return;

            const message = this.$textarea.val().trim();
            if (!this.validateMessage(message)) return;

            this.isSending = true;
            this.disableInput();

            try {
                await this.processMessage(message);
            } catch (error) {
                this.handleError(error);
            } finally {
                this.enableInput();
            }
        }

        validateMessage(message) {
            if (!message) {
                this.showNotification(wc_ai_homeopathic_chat_params.empty_message_text, 'warning');
                return false;
            }
            return true;
        }

        async processMessage(message) {
            // Añadir mensaje del usuario
            this.addUserMessage(message);
            this.clearInput();

            // Mostrar loading
            this.showLoading();

            try {
                const response = await this.apiRequest(message);
                
                if (response.whatsapp_fallback) {
                    this.showWhatsAppFallback(response.response, message);
                } else {
                    this.addBotMessage(response.response, response.from_cache);
                }
            } catch (error) {
                if (this.hasWhatsAppFallback()) {
                    this.showWhatsAppFallback(error.message, message);
                } else {
                    throw error;
                }
            }
        }

        async apiRequest(message) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wc_ai_homeopathic_chat_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    timeout: 25000,
                    data: {
                        action: 'wc_ai_homeopathic_chat_send_message',
                        message: message,
                        nonce: wc_ai_homeopathic_chat_params.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(this.getErrorMessage(status)));
                    }
                });
            });
        }

        getErrorMessage(status) {
            const messages = {
                'timeout': 'El servicio está tardando más de lo esperado.',
                'error': 'Error de conexión con el servicio.',
                'parsererror': 'Error procesando la respuesta.'
            };
            return messages[status] || wc_ai_homeopathic_chat_params.error_text;
        }

        addUserMessage(message) {
            const time = new Date().toLocaleTimeString('es-MX', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageHtml = `
                <div class="wc-ai-chat-message user">
                    <div class="wc-ai-message-content">${this.escapeHtml(message)}</div>
                    <div class="wc-ai-message-time">${time}</div>
                </div>
            `;
            
            this.$messages.append(messageHtml);
            this.scrollToBottom();
        }

        addBotMessage(message, fromCache = false) {
            this.hideLoading();
            
            const time = new Date().toLocaleTimeString('es-MX', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const cacheBadge = fromCache ? '<small style="opacity:0.7;font-size:0.8em;">(desde caché)</small>' : '';
            
            const messageHtml = `
                <div class="wc-ai-chat-message bot">
                    <div class="wc-ai-message-content">${message} ${cacheBadge}</div>
                    <div class="wc-ai-message-time">${time}</div>
                </div>
            `;
            
            this.$messages.append(messageHtml);
            this.scrollToBottom();
        }

        showWhatsAppFallback(errorMessage, userMessage) {
            this.hideLoading();
            
            const whatsappUrl = this.generateWhatsAppUrl(userMessage);
            const message = `
                ${errorMessage}<br><br>
                <a href="${whatsappUrl}" target="_blank" class="wc-ai-whatsapp-link">
                    💬 ${wc_ai_homeopathic_chat_params.whatsapp_btn}
                </a>
            `;
            
            this.addBotMessage(message);
        }

        generateWhatsAppUrl(message = '') {
            const baseMessage = wc_ai_homeopathic_chat_params.whatsapp_message;
            const fullMessage = message ? 
                `${baseMessage}\n\nMi consulta: ${message}` : 
                baseMessage;
            
            const encodedMessage = encodeURIComponent(fullMessage);
            const phone = wc_ai_homeopathic_chat_params.whatsapp_number.replace(/\D/g, '');
            
            return `https://wa.me/${phone}?text=${encodedMessage}`;
        }

        hasWhatsAppFallback() {
            return !!wc_ai_homeopathic_chat_params.whatsapp_number;
        }

        openWhatsApp() {
            const url = this.generateWhatsAppUrl();
            window.open(url, '_blank');
        }

        showLoading() {
            const loadingHtml = `
                <div class="wc-ai-chat-message bot">
                    <div class="wc-ai-message-content">
                        <div class="wc-ai-chat-loading">
                            <div class="loading-dots">
                                <span></span><span></span><span></span>
                            </div>
                            ${wc_ai_homeopathic_chat_params.loading_text}
                        </div>
                    </div>
                </div>
            `;
            this.$messages.append(loadingHtml);
            this.scrollToBottom();
        }

        hideLoading() {
            this.$messages.find('.wc-ai-chat-loading').parent().parent().remove();
        }

        showNotification(message, type = 'info') {
            // Implementar notificación toast si es necesario
            console.log(`${type}: ${message}`);
        }

        handleKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        }

        autoResize() {
            const textarea = this.$textarea[0];
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        clearInput() {
            this.$textarea.val('');
            this.autoResize();
        }

        focusInput() {
            if (this.isOpen && !this.isMinimized) {
                setTimeout(() => this.$textarea.focus(), 100);
            }
        }

        disableInput() {
            this.$sendBtn.prop('disabled', true);
            this.$textarea.prop('disabled', true);
        }

        enableInput() {
            this.isSending = false;
            this.$sendBtn.prop('disabled', false);
            this.$textarea.prop('disabled', false);
            this.focusInput();
        }

        scrollToBottom() {
            this.$messages.stop().animate({
                scrollTop: this.$messages[0].scrollHeight
            }, 300);
        }

        saveState() {
            const state = {
                isOpen: this.isOpen,
                isMinimized: this.isMinimized
            };
            sessionStorage.setItem('wcAiChatState', JSON.stringify(state));
        }

        restoreState() {
            try {
                const saved = sessionStorage.getItem('wcAiChatState');
                if (saved) {
                    const state = JSON.parse(saved);
                    if (state.isOpen) {
                        setTimeout(() => this.openChat(), 1000);
                        if (state.isMinimized) {
                            this.toggleMinimize();
                        }
                    }
                }
            } catch (e) {
                // Ignorar errores de restauración
            }
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
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        // Solo inicializar si los parámetros están disponibles
        if (typeof wc_ai_homeopathic_chat_params !== 'undefined') {
            new FloatingHomeopathicChat();
        }
    });

})(jQuery);