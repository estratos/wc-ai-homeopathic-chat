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
            this.cacheElements();
            this.bindEvents();
            this.applyPosition();
            this.restoreState();
        }

        cacheElements() {
            this.$container = $('#wc-ai-homeopathic-chat-container');
            this.$launcher = $('#wc-ai-chat-launcher');
            this.$window = $('#wc-ai-chat-window');
            this.$messages = $('.wc-ai-chat-messages');
            this.$textarea = $('.wc-ai-chat-input textarea');
            this.$sendBtn = $('.wc-ai-chat-send');
            this.$whatsappBtn = $('.wc-ai-whatsapp-fallback');
            this.$minimizeBtn = $('.wc-ai-chat-minimize');
            this.$closeBtn = $('.wc-ai-chat-close');
        }

        applyPosition() {
            const position = wc_ai_homeopathic_chat_params.position || 'right';
            this.$container.removeClass('wc-ai-chat-position-left wc-ai-chat-position-right')
                          .addClass('wc-ai-chat-position-' + position);
        }

        bindEvents() {
            // Lanzador
            this.$launcher.on('click', (e) => {
                e.stopPropagation();
                this.toggleChat();
            });
            
            // Prevenir que el clic en la ventana cierre el chat
            this.$window.on('click', (e) => {
                e.stopPropagation();
            });
            
            // Botones de ventana
            this.$minimizeBtn.on('click', (e) => {
                e.stopPropagation();
                this.toggleMinimize();
            });
            
            this.$closeBtn.on('click', (e) => {
                e.stopPropagation();
                this.closeChat();
            });
            
            // Envío de mensajes
            this.$sendBtn.on('click', (e) => {
                e.stopPropagation();
                this.sendMessage();
            });
            
            this.$textarea.on('keydown', (e) => {
                this.handleKeydown(e);
            });
            
            this.$textarea.on('input', () => {
                this.autoResize();
            });
            
            // WhatsApp fallback
            if (this.$whatsappBtn.length) {
                this.$whatsappBtn.on('click', (e) => {
                    e.stopPropagation();
                    this.openWhatsApp();
                });
            }
            
            // Cerrar al hacer click fuera
            $(document).on('click', () => {
                if (this.isOpen) {
                    this.closeChat();
                }
            });
        }

        toggleChat() {
            console.log('Toggle chat called, current state:', this.isOpen);
            
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            console.log('Opening chat...');
            
            this.isOpen = true;
            this.isMinimized = false;
            
            this.$window.addClass('active').removeClass('minimized');
            this.$launcher.addClass('active');
            
            this.focusInput();
            this.scrollToBottom();
            this.saveState();
            
            console.log('Chat opened successfully');
        }

        closeChat() {
            console.log('Closing chat...');
            
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
            if (this.isSending) {
                console.log('Already sending, skipping...');
                return;
            }

            const message = this.$textarea.val().trim();
            console.log('Sending message:', message);
            
            if (!this.validateMessage(message)) return;

            this.isSending = true;
            this.disableInput();

            try {
                await this.processMessage(message);
            } catch (error) {
                console.error('Error sending message:', error);
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
            
            if (message.length > 500) {
                this.showNotification('El mensaje es demasiado largo. Máximo 500 caracteres.', 'warning');
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
                    // Usar el nuevo método con efecto de escritura
                    await this.addBotMessageWithTyping(response.response, response.from_cache);
                }
            } catch (error) {
                console.error('API Error:', error);
                
                if (this.hasWhatsAppFallback()) {
                    this.showWhatsAppFallback(error.message, message);
                } else {
                    await this.addBotMessageWithTyping('<em style="color: #d32f2f;">' + error.message + '</em>');
                }
            }
        }

        /**
         * NUEVO MÉTODO: Añade mensaje del bot con efecto de escritura palabra por palabra
         */
        async addBotMessageWithTyping(message, fromCache = false) {
            this.hideLoading();
            
            const time = new Date().toLocaleTimeString('es-MX', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const cacheBadge = fromCache ? ' <small style="opacity:0.7;font-size:0.8em;">(desde caché)</small>' : '';
            
            // Crear elemento del mensaje
            const messageHtml = `
                <div class="wc-ai-chat-message bot">
                    <div class="wc-ai-message-content typing-effect">
                        <span class="wc-ai-typing-cursor"></span>
                    </div>
                    <div class="wc-ai-message-time">🕒 ${time}</div>
                </div>
            `;
            
            this.$messages.append(messageHtml);
            const $messageContent = this.$messages.find('.wc-ai-message-content.typing-effect').last();
            
            // Efecto de escritura palabra por palabra
            await this.typeWriterEffect($messageContent, message + cacheBadge);
            
            this.scrollToBottom();
        }

        /**
         * Efecto de escritura palabra por palabra
         */
        async typeWriterEffect($element, text) {
            return new Promise((resolve) => {
                // Limpiar el cursor inicial
                $element.empty();
                
                // Procesar el texto para mantener el formato HTML
                const words = this.extractWordsWithFormatting(text);
                let currentIndex = 0;
                
                const typeNextWord = () => {
                    if (currentIndex >= words.length) {
                        // Escribir completo, eliminar clase de efecto
                        $element.removeClass('typing-effect');
                        resolve();
                        return;
                    }
                    
                    const word = words[currentIndex];
                    
                    if (word.type === 'tag') {
                        // Es una etiqueta HTML, añadir inmediatamente
                        $element.append(word.content);
                    } else {
                        // Es texto normal, crear span para la palabra
                        const $wordSpan = $('<span class="word"></span>')
                            .html(word.content + (word.space ? ' ' : ''));
                        
                        $element.append($wordSpan);
                        
                        // Animación de aparición
                        setTimeout(() => {
                            $wordSpan.css('opacity', 1);
                        }, 10);
                    }
                    
                    currentIndex++;
                    
                    // Scroll automático mientras se escribe
                    this.scrollToBottom();
                    
                    // Velocidad de escritura (más rápido para tags HTML, normal para texto)
                    const delay = word.type === 'tag' ? 50 : this.calculateTypingDelay(word.content);
                    
                    setTimeout(typeNextWord, delay);
                };
                
                // Iniciar el efecto
                typeNextWord();
            });
        }

        /**
         * Extrae palabras manteniendo el formato HTML
         */
        extractWordsWithFormatting(text) {
            const result = [];
            let currentText = '';
            let inTag = false;
            let tagContent = '';
            
            for (let i = 0; i < text.length; i++) {
                const char = text[i];
                
                if (char === '<') {
                    // Comienza una etiqueta HTML
                    if (currentText.trim()) {
                        // Procesar el texto acumulado antes de la etiqueta
                        const words = currentText.split(/(\s+)/);
                        words.forEach(word => {
                            if (word.trim()) {
                                result.push({
                                    type: 'text',
                                    content: word,
                                    space: /\s$/.test(word)
                                });
                            } else if (word) {
                                // Espacios
                                result.push({
                                    type: 'space',
                                    content: word
                                });
                            }
                        });
                        currentText = '';
                    }
                    inTag = true;
                    tagContent = char;
                } else if (char === '>' && inTag) {
                    // Termina una etiqueta HTML
                    tagContent += char;
                    result.push({
                        type: 'tag',
                        content: tagContent
                    });
                    inTag = false;
                    tagContent = '';
                } else if (inTag) {
                    // Dentro de una etiqueta HTML
                    tagContent += char;
                } else {
                    // Texto normal
                    currentText += char;
                }
            }
            
            // Procesar cualquier texto restante después de las etiquetas
            if (currentText.trim()) {
                const words = currentText.split(/(\s+)/);
                words.forEach(word => {
                    if (word.trim()) {
                        result.push({
                            type: 'text',
                            content: word,
                            space: /\s$/.test(word)
                        });
                    } else if (word) {
                        result.push({
                            type: 'space',
                            content: word
                        });
                    }
                });
            }
            
            return result;
        }

        /**
         * Calcula el delay de escritura basado en la longitud de la palabra
         */
        calculateTypingDelay(word) {
            const baseDelay = 30; // ms por palabra
            const lengthFactor = Math.min(word.length * 2, 100); // máximo 100ms adicionales
            return baseDelay + lengthFactor;
        }

        addUserMessage(message) {
            const time = new Date().toLocaleTimeString('es-MX', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageHtml = `
                <div class="wc-ai-chat-message user">
                    <div class="wc-ai-message-content">${this.escapeHtml(message)}</div>
                    <div class="wc-ai-message-time">🕒 ${time}</div>
                </div>
            `;
            
            this.$messages.append(messageHtml);
            this.scrollToBottom();
        }

        // Mantener el método original para compatibilidad
        addBotMessage(message, fromCache = false) {
            this.hideLoading();
            
            const time = new Date().toLocaleTimeString('es-MX', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const cacheBadge = fromCache ? ' <small style="opacity:0.7;font-size:0.8em;">(desde caché)</small>' : '';
            
            const messageHtml = `
                <div class="wc-ai-chat-message bot">
                    <div class="wc-ai-message-content">${message}${cacheBadge}</div>
                    <div class="wc-ai-message-time">🕒 ${time}</div>
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
            const baseMessage = wc_ai_homeopathic_chat_params.whatsapp_message || 'Hola, me interesa obtener asesoramiento homeopático';
            const fullMessage = message ? 
                `${baseMessage}\n\nMi consulta: ${message}` : 
                baseMessage;
            
            const encodedMessage = encodeURIComponent(fullMessage);
            const phone = (wc_ai_homeopathic_chat_params.whatsapp_number || '').replace(/\D/g, '');
            
            if (!phone) {
                console.error('WhatsApp number not configured');
                return '#';
            }
            
            return `https://wa.me/${phone}?text=${encodedMessage}`;
        }

        hasWhatsAppFallback() {
            return !!(wc_ai_homeopathic_chat_params.whatsapp_number && 
                     wc_ai_homeopathic_chat_params.whatsapp_number.trim() !== '');
        }

        openWhatsApp() {
            const url = this.generateWhatsAppUrl();
            if (url !== '#') {
                window.open(url, '_blank');
            }
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
            this.$messages.find('.wc-ai-chat-loading').closest('.wc-ai-chat-message').remove();
        }

        showNotification(message, type = 'info') {
            // Notificación simple
            console.log(`[${type.toUpperCase()}] ${message}`);
        }

        handleKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        }

        autoResize() {
            const textarea = this.$textarea[0];
            if (textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }
        }

        clearInput() {
            this.$textarea.val('');
            this.autoResize();
        }

        focusInput() {
            if (this.isOpen && !this.isMinimized && this.$textarea.length) {
                setTimeout(() => {
                    this.$textarea.focus();
                }, 100);
            }
        }

        disableInput() {
            this.$sendBtn.prop('disabled', true).addClass('disabled');
            this.$textarea.prop('disabled', true);
        }

        enableInput() {
            this.isSending = false;
            this.$sendBtn.prop('disabled', false).removeClass('disabled');
            this.$textarea.prop('disabled', false);
            this.focusInput();
        }

        scrollToBottom() {
            if (this.$messages.length) {
                this.$messages.stop().animate({
                    scrollTop: this.$messages[0].scrollHeight
                }, 300);
            }
        }

        saveState() {
            try {
                const state = {
                    isOpen: this.isOpen,
                    isMinimized: this.isMinimized,
                    timestamp: Date.now()
                };
                sessionStorage.setItem('wcAiChatState', JSON.stringify(state));
            } catch (e) {
                console.warn('Could not save chat state:', e);
            }
        }

        restoreState() {
            try {
                const saved = sessionStorage.getItem('wcAiChatState');
                if (saved) {
                    const state = JSON.parse(saved);
                    if (state.timestamp && (Date.now() - state.timestamp) < 30 * 60 * 1000) {
                        if (state.isOpen) {
                            setTimeout(() => {
                                this.openChat();
                                if (state.isMinimized) {
                                    this.toggleMinimize();
                                }
                            }, 1000);
                        }
                    }
                }
            } catch (e) {
                console.warn('Could not restore chat state:', e);
            }
        }

        escapeHtml(text) {
            if (typeof text !== 'string') return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        apiRequest(message) {
            return new Promise((resolve, reject) => {
                if (!wc_ai_homeopathic_chat_params.api_configured) {
                    reject(new Error('API no configurada'));
                    return;
                }

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
                        console.log('API Response:', response);
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Error desconocido'));
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX Error:', status, error);
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
    }

    // Inicialización
    $(document).ready(() => {
        console.log('Document ready, initializing chat...');
        
        if (typeof wc_ai_homeopathic_chat_params === 'undefined') {
            console.error('Chat parameters not defined');
            return;
        }
        
        if ($('#wc-ai-homeopathic-chat-container').length === 0) {
            console.error('Chat container not found');
            return;
        }
        
        setTimeout(() => {
            try {
                new FloatingHomeopathicChat();
                console.log('Chat initialized successfully');
            } catch (error) {
                console.error('Error initializing chat:', error);
            }
        }, 100);
    });

})(jQuery);