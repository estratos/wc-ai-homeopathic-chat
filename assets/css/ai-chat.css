jQuery(document).ready(function($) {
    'use strict';
    
    // Crear elementos básicos del chat
    const chatHTML = `
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
    `;
    
    $('body').append(chatHTML);
    
    const chatButton = $('.wc-ai-chat-button');
    const chatWindow = $('.wc-ai-chat-window');
    const chatMessages = $('.wc-ai-chat-messages');
    const chatForm = $('.wc-ai-chat-input-form');
    const chatInput = $('.wc-ai-chat-input-field');
    const closeButton = $('.wc-ai-chat-close');
    
    // Toggle chat window
    chatButton.on('click', function() {
        chatWindow.toggleClass('active');
        if (chatWindow.hasClass('active')) {
            chatInput.focus();
        }
    });
    
    closeButton.on('click', function() {
        chatWindow.removeClass('active');
    });
    
    // Handle form submission
    chatForm.on('submit', function(e) {
        e.preventDefault();
        
        const message = chatInput.val().trim();
        if (!message) return;
        
        // Add user message
        chatMessages.append('<div>Usuario: ' + message + '</div>');
        chatInput.val('');
        
        // Simulate AI response
        setTimeout(function() {
            chatMessages.append('<div>Asistente: He recibido tu mensaje. Configura la API key para respuestas reales.</div>');
        }, 1000);
    });
});
