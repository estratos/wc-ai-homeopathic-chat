jQuery(document).ready(function($) {
    'use strict';
    
    // Configuración básica
    const chatContainer = $('#wc-ai-chat-container');
    const chatButton = $('#wc-ai-chat-button');
    const chatWindow = $('#wc-ai-chat-window');
    const chatMessages = $('.wc-ai-chat-messages');
    const chatForm = $('.wc-ai-chat-input-form');
    const chatInput = $('.wc-ai-chat-input-field');
    
    let sessionId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // Inicializar chat
    function initChat() {
        console.log('WC AI Chat initialized');
        console.log('Nonce:', wc_ai_chat_params.nonce);
        console.log('AJAX URL:', wc_ai_chat_params.ajax_url);
        
        bindEvents();
    }
    
    // Vincular eventos
    function bindEvents() {
        chatButton.on('click', function() {
            chatWindow.toggleClass('active');
            if (chatWindow.hasClass('active')) {
                chatInput.focus();
            }
        });
        
        $('.wc-ai-chat-close').on('click', function() {
            chatWindow.removeClass('active');
        });
        
        chatForm.on('submit', function(e) {
            e.preventDefault();
            handleSubmit();
        });
    }
    
    // Manejar envío de mensaje
    function handleSubmit() {
        const message = chatInput.val().trim();
        if (!message) return;
        
        chatInput.val('');
        addMessage(message, 'user');
        showLoading();
        sendMessage(message);
    }
    
    // Añadir mensaje al chat
    function addMessage(message, type) {
        const messageClass = type === 'user' ? 'user' : 'ai';
        const messageBubble = `
            <div class="wc-ai-message ${messageClass}">
                <div class="wc-ai-message-bubble">${escapeHtml(message)}</div>
            </div>
        `;
        
        chatMessages.append(messageBubble);
        scrollToBottom();
    }
    
    // Mostrar indicador de carga
    function showLoading() {
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
        
        chatMessages.append(loading);
        scrollToBottom();
    }
    
    // Remover indicador de carga
    function removeLoading() {
        $('.wc-ai-loading').closest('.wc-ai-message').remove();
    }
    
    // Enviar mensaje al servidor
    function sendMessage(message) {
        console.log('Sending message:', message);
        console.log('With nonce:', wc_ai_chat_params.nonce);
        console.log('Session ID:', sessionId);
        
        const postData = {
            action: 'wc_ai_chat_send_message',
            message: message,
            session_id: sessionId,
            nonce: wc_ai_chat_params.nonce
        };
        
        console.log('POST data:', postData);
        
        $.ajax({
            url: wc_ai_chat_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: postData,
            success: function(response) {
                console.log('AJAX Success response:', response);
                removeLoading();
                
                if (response.success) {
                    handleSuccess(response.data);
                } else {
                    handleError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('XHR object:', xhr);
                console.error('Response text:', xhr.responseText);
                
                removeLoading();
                handleAjaxError(xhr, status, error);
            }
        });
    }
    
    // Manejar respuesta exitosa
    function handleSuccess(data) {
        addMessage(data.response, 'ai');
        
        if (data.products && data.products.length > 0) {
            showProducts(data.products);
        }
        
        if (data.session_id) {
            sessionId = data.session_id;
        }
    }
    
    // Manejar error
    function handleError(data) {
        const errorMsg = data.message || wc_ai_chat_params.error_text;
        addMessage('❌ ' + errorMsg, 'ai');
    }
    
    // Manejar error AJAX
    function handleAjaxError(xhr, status, error) {
        let errorMsg = wc_ai_chat_params.error_text;
        
        // Intentar parsear la respuesta de error
        try {
            if (xhr.responseText) {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.data && errorResponse.data.message) {
                    errorMsg = errorResponse.data.message;
                }
            }
        } catch (e) {
            console.error('Error parsing response:', e);
        }
        
        if (xhr.status === 400) {
            errorMsg = 'Error en la solicitud. Por favor recarga la página.';
        } else if (xhr.status === 403) {
            errorMsg = 'Error de seguridad. Recarga la página.';
        } else if (xhr.status === 500) {
            errorMsg = 'Error del servidor. Intenta más tarde.';
        }
        
        addMessage('❌ ' + errorMsg, 'ai');
    }
    
    // Mostrar productos
    function showProducts(products) {
        if (!products || products.length === 0) return;
        
        let html = `<div class="wc-ai-recommended-products">
            <div class="wc-ai-products-title">💊 Productos recomendados:</div>`;
        
        products.forEach(product => {
            html += `
                <div class="wc-ai-product-card">
                    <img src="${product.image}" alt="${product.name}" class="wc-ai-product-image">
                    <div class="wc-ai-product-info">
                        <h4 class="wc-ai-product-name">${escapeHtml(product.name)}</h4>
                        <div class="wc-ai-product-price">${product.price}</div>
                        <a href="${product.url}" target="_blank" class="wc-ai-product-link">Ver producto</a>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        chatMessages.append(html);
        scrollToBottom();
    }
    
    // Scroll al final
    function scrollToBottom() {
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }
    
    // Escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Test function for debugging
    window.testChatAjax = function() {
        console.log('Testing AJAX connection...');
        
        $.ajax({
            url: wc_ai_chat_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wc_ai_chat_send_message',
                message: 'test message',
                session_id: 'test_session',
                nonce: wc_ai_chat_params.nonce
            },
            success: function(response) {
                console.log('Test AJAX Success:', response);
            },
            error: function(xhr, status, error) {
                console.error('Test AJAX Error:', status, error);
                console.error('XHR:', xhr);
                console.error('Response:', xhr.responseText);
            }
        });
    };
    
    // Inicializar
    initChat();
});