jQuery(document).ready(function($) {
    'use strict';
    
    const chatContainer = $('
        <div class="wc-ai-chat-container">
            <button class="wc-ai-chat-button">💬</button>
            <div class="wc-ai-chat-window">
                <div class="wc-ai-chat-header">
                    <h3>Asistente Homeopático</h3>
                    <button class="wc-ai-chat-close">×</button>
                </div>
                <div class="wc-ai-chat-messages"></div>
                <div class="wc-ai-chat-input">
                    <form class="wc-ai-chat-input-form">
                        <input type="text" class="wc-ai-chat-input-field" placeholder="Describe tus síntomas..." required>
                        <button type="submit" class="wc-ai-chat-send-button">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    ');
    
    $('body').append(chatContainer);
    
    const chatButton = chatContainer.find('.wc-ai-chat-button');
    const chatWindow = chatContainer.find('.wc-ai-chat-window');
    const chatMessages = chatContainer.find('.wc-ai-chat-messages');
    const chatForm = chatContainer.find('.wc-ai-chat-input-form');
    const chatInput = chatContainer.find('.wc-ai-chat-input-field');
    const closeButton = chatContainer.find('.wc-ai-chat-close');
    
    let sessionId = '';
    let isChatOpen = false;
    
    // Toggle chat window
    chatButton.on('click', function() {
        isChatOpen = !isChatOpen;
        chatWindow.toggleClass('active', isChatOpen);
        
        if (isChatOpen) {
            chatInput.focus();
            if (chatMessages.children().length === 0) {
                addWelcomeMessage();
            }
        }
    });
    
    closeButton.on('click', function() {
        isChatOpen = false;
        chatWindow.removeClass('active');
    });
    
    // Handle form submission
    chatForm.on('submit', function(e) {
        e.preventDefault();
        
        const message = chatInput.val().trim();
        if (!message) return;
        
        // Add user message to chat
        addMessage(message, 'user');
        chatInput.val('');
        
        // Show loading indicator
        const loadingIndicator = $('
            <div class="wc-ai-message ai">
                <div class="wc-ai-message-bubble">
                    <div class="wc-ai-loading">
                        <span>' + wc_ai_chat_params.loading_text + '</span>
                        <div class="wc-ai-loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>
        ');
        
        chatMessages.append(loadingIndicator);
        scrollToBottom();
        
        // Send message to server
        $.ajax({
            url: wc_ai_chat_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_ai_chat_send_message',
                message: message,
                session_id: sessionId,
                nonce: wc_ai_chat_params.nonce
            },
            success: function(response) {
                loadingIndicator.remove();
                
                if (response.success) {
                    sessionId = response.data.session_id;
                    
                    // Add AI response
                    addMessage(response.data.ai_response, 'ai');
                    
                    // Add recommended products if any
                    if (response.data.recommended_products && response.data.recommended_products.length > 0) {
                        addProducts(response.data.recommended_products);
                    }
                } else {
                    addMessage(wc_ai_chat_params.error_text, 'ai error');
                }
            },
            error: function() {
                loadingIndicator.remove();
                addMessage(wc_ai_chat_params.error_text, 'ai error');
            }
        });
    });
    
    function addWelcomeMessage() {
        const welcomeMessage = '¡Hola! Soy tu asistente homeopático. Puedo ayudarte a encontrar productos naturales para tus síntomas. Por favor, describe cómo te sientes o qué síntomas experimentas.';
        addMessage(welcomeMessage, 'ai');
    }
    
    function addMessage(text, type) {
        const messageClass = type === 'user' ? 'user' : 'ai';
        const messageBubble = $('
            <div class="wc-ai-message ' + messageClass + '">
                <div class="wc-ai-message-bubble">' + text + '</div>
            </div>
        ');
        
        chatMessages.append(messageBubble);
        scrollToBottom();
    }
    
    function addProducts(products) {
        const productsContainer = $('<div class="wc-ai-recommended-products"></div>');
        const title = $('<h4 style="margin: 0 0 15px 0; font-size: 14px; color: #333;">Productos recomendados:</h4>');
        
        productsContainer.append(title);
        
        products.forEach(product => {
            const productCard = $('
                <div class="wc-ai-product-card">
                    <img src="' + (product.image || wc_ai_chat_params.placeholder_image) + '" alt="' + product.name + '" class="wc-ai-product-image">
                    <div class="wc-ai-product-info">
                        <h5 class="wc-ai-product-name">' + product.name + '</h5>
                        <div class="wc-ai-product-price">' + product.price + '</div>
                        <p class="wc-ai-product-description">' + (product.description || '') + '</p>
                    </div>
                    <a href="' + product.url + '" class="wc-ai-product-link" target="_blank">Ver</a>
                </div>
            ');
            
            productsContainer.append(productCard);
        });
        
        chatMessages.append(productsContainer);
        scrollToBottom();
    }
    
    function scrollToBottom() {
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }
    
    // Close chat when clicking outside
    $(document).on('click', function(e) {
        if (isChatOpen && 
            !chatWindow.is(e.target) && 
            chatWindow.has(e.target).length === 0 &&
            !chatButton.is(e.target)) {
            isChatOpen = false;
            chatWindow.removeClass('active');
        }
    });
});
