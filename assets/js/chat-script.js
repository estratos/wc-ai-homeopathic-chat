jQuery(document).ready(function($) {
    // Toggle chat visibility
    $(document).on('click', '#wc-ai-homeopathic-chat-toggle', function() {
        $('#wc-ai-homeopathic-chat').slideToggle(300);
        $(this).toggleClass('active');
    });
    
    // Close chat
    $(document).on('click', '.wc-ai-homeopathic-chat-close', function() {
        $('#wc-ai-homeopathic-chat').slideUp(300);
        $('#wc-ai-homeopathic-chat-toggle').removeClass('active');
    });
    
    // Send message
    $(document).on('click', '.wc-ai-homeopathic-chat-send', function() {
        sendMessage();
    });
    
    // Send message on Enter key (but allow Shift+Enter for new line)
    $(document).on('keydown', '.wc-ai-homeopathic-chat-input textarea', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Auto-resize textarea
    $(document).on('input', '.wc-ai-homeopathic-chat-input textarea', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        
        // Limit maximum height
        if (this.scrollHeight > 120) {
            this.style.overflowY = 'auto';
        } else {
            this.style.overflowY = 'hidden';
        }
    });
    
    function sendMessage() {
        var $input = $('.wc-ai-homeopathic-chat-input textarea');
        var message = $input.val().trim();
        
        if (message === '') {
            return;
        }
        
        // Add user message to chat
        addMessage('user', message);
        $input.val('');
        $input.height('auto'); // Reset textarea height
        $input.css('overflowY', 'hidden');
        
        // Disable send button during request
        $('.wc-ai-homeopathic-chat-send').prop('disabled', true);
        
        // Show loading indicator
        var loadingHtml = '<div class="wc-ai-homeopathic-chat-message bot">' + 
                          '<div class="wc-ai-homeopathic-chat-loading">' + 
                          '<div class="loading-dots"><span></span><span></span><span></span></div>' +
                          wc_ai_homeopathic_chat_params.loading_text + 
                          '</div>' +
                          '</div>';
        $('.wc-ai-homeopathic-chat-messages').append(loadingHtml);
        scrollToBottom();
        
        // Send AJAX request
        $.ajax({
            url: wc_ai_homeopathic_chat_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_ai_homeopathic_chat_send_message',
                message: message,
                nonce: wc_ai_homeopathic_chat_params.nonce
            },
            success: function(response) {
                // Remove loading indicator
                $('.wc-ai-homeopathic-chat-loading').parent().remove();
                
                if (response.success) {
                    // Mostrar indicador si la respuesta viene del caché
                    var cacheIndicator = response.data.from_cache ? 
                        ' <small style="color: #666; font-style: italic;">(respuesta desde caché)</small>' : '';
                    addMessage('bot', response.data.response + cacheIndicator);
                } else {
                    addMessage('bot', '<em style="color: #d32f2f;">' + wc_ai_homeopathic_chat_params.error_text + '</em>');
                    console.error('Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                // Remove loading indicator
                $('.wc-ai-homeopathic-chat-loading').parent().remove();
                
                addMessage('bot', '<em style="color: #d32f2f;">' + wc_ai_homeopathic_chat_params.error_text + '</em>');
                console.error('AJAX Error:', error, xhr);
            },
            complete: function() {
                // Re-enable send button
                $('.wc-ai-homeopathic-chat-send').prop('disabled', false);
                $input.focus();
            }
        });
    }
    
    function addMessage(sender, message) {
        var messageClass = 'wc-ai-homeopathic-chat-message ' + sender;
        var messageHtml = '<div class="' + messageClass + '">' + nl2br(escapeHtml(message)) + '</div>';
        $('.wc-ai-homeopathic-chat-messages').append(messageHtml);
        scrollToBottom();
    }
    
    function scrollToBottom() {
        var $messages = $('.wc-ai-homeopathic-chat-messages');
        $messages.stop().animate({
            scrollTop: $messages[0].scrollHeight
        }, 300);
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function nl2br(str) {
        return str.replace(/\n/g, '<br>');
    }
    
    // Add loading dots animation
    $('<style>')
        .prop('type', 'text/css')
        .html('\
            .loading-dots {\
                display: inline-block;\
                margin-right: 8px;\
            }\
            .loading-dots span {\
                display: inline-block;\
                width: 6px;\
                height: 6px;\
                border-radius: 50%;\
                background-color: #666;\
                margin: 0 2px;\
                animation: loading-dots 1.4s infinite ease-in-out both;\
            }\
            .loading-dots span:nth-child(1) { animation-delay: -0.32s; }\
            .loading-dots span:nth-child(2) { animation-delay: -0.16s; }\
            @keyframes loading-dots {\
                0%, 80%, 100% { transform: scale(0); }\
                40% { transform: scale(1); }\
            }\
        ')
        .appendTo('head');
});

// Handle page refresh - preserve chat state
jQuery(window).on('beforeunload', function() {
    if (jQuery('#wc-ai-homeopathic-chat').is(':visible')) {
        sessionStorage.setItem('wc_ai_chat_open', 'true');
    }
});

jQuery(document).ready(function($) {
    // Restore chat state if it was open
    if (sessionStorage.getItem('wc_ai_chat_open') === 'true') {
        $('#wc-ai-homeopathic-chat').show();
        $('#wc-ai-homeopathic-chat-toggle').addClass('active');
        sessionStorage.removeItem('wc_ai_chat_open');
    }
});