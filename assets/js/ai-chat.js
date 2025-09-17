jQuery(document).ready(function($) {
    'use strict';
    
    const chatButton = $('#wc-ai-chat-button');
    const chatWindow = $('#wc-ai-chat-window');
    const chatMessages = $('.wc-ai-chat-messages');
    const chatForm = $('.wc-ai-chat-input-form');
    const chatInput = $('.wc-ai-chat-input-field');
    const closeButton = $('.wc-ai-chat-close');
    
    chatButton.on('click', function() {
        chatWindow.toggleClass('active');
    });
    
    closeButton.on('click', function() {
        chatWindow.removeClass('active');
    });
    
    chatForm.on('submit', function(e) {
        e.preventDefault();
        
        const message = chatInput.val().trim();
        if (!message) return;
        
        // Add user message
        chatMessages.append('<div class="user-message">' + message + '</div>');
        chatInput.val('');
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
        
        // Send to server
        $.ajax({
            url: wc_ai_chat_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_ai_chat_send_message',
                message: message,
                nonce: wc_ai_chat_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    chatMessages.append('<div class="ai-message">' + response.data.response + '</div>');
                } else {
                    chatMessages.append('<div class="error-message">Error: ' + response.data.message + '</div>');
                }
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            },
            error: function() {
                chatMessages.append('<div class="error-message">Error de conexión</div>');
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            }
        });
    });
});