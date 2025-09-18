jQuery(document).ready(function($) {
    // Toggle chat visibility
    $(document).on('click', '#wc-ai-homeopathic-chat-toggle', function() {
        $('#wc-ai-homeopathic-chat').slideToggle();
    });
    
    // Close chat
    $(document).on('click', '.wc-ai-homeopathic-chat-close', function() {
        $('#wc-ai-homeopathic-chat').slideUp();
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
    
    function sendMessage() {
        var $input = $('.wc-ai-homeopathic-chat-input textarea');
        var message = $input.val().trim();
        
        if (message === '') {
            return;
        }
        
        // Add user message to chat
        addMessage('user', message);
        $input.val('');
        
        // Disable send button during request
        $('.wc-ai-homeopathic-chat-send').prop('disabled', true);
        
        // Show loading indicator
        var loadingHtml = '<div class="wc-ai-homeopathic-chat-message bot">' + 
                          '<div class="wc-ai-homeopathic-chat-loading">' + wc_ai_homeopathic_chat_params.loading_text + '</div>' +
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
                    addMessage('bot', response.data.response);
                } else {
                    addMessage('bot', '<em>' + wc_ai_homeopathic_chat_params.error_text + '</em>');
                    console.error(response.data);
                }
            },
            error: function(xhr, status, error) {
                // Remove loading indicator
                $('.wc-ai-homeopathic-chat-loading').parent().remove();
                
                addMessage('bot', '<em>' + wc_ai_homeopathic_chat_params.error_text + '</em>');
                console.error(error);
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
        $messages.scrollTop($messages[0].scrollHeight);
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
        return str.replace(/([^>])\n/g, '$1<br/>');
    }
});