/**
 * Chat functionality - SecondVoice
 * Gère l'auto-resize du textarea, l'envoi des messages et les animations
 */

 document.addEventListener('DOMContentLoaded', function() {
    const chatInput = document.getElementById('chatInput');
    const chatForm = document.getElementById('chatForm');
    const chatMessages = document.getElementById('chatMessages');
    const chatInputContainer = document.getElementById('chatInputContainer');

    if (!chatInput || !chatForm || !chatMessages) return;

    // Auto-resize du textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        const newHeight = Math.min(this.scrollHeight, 120);
        this.style.height = newHeight + 'px';
    });

    // Envoi avec Entrée (Shift+Entrée pour nouvelle ligne)
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });

    // Gestion de l'envoi du formulaire
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = chatInput.value.trim();
        if (!message) return;

        // Ajouter le message utilisateur
        addMessage(message, 'user');
        
        // Reset input
        chatInput.value = '';
        chatInput.style.height = 'auto';
        
        // Simuler une réponse de l'assistant (prototype)
        showTypingIndicator();
        
        setTimeout(() => {
            removeTypingIndicator();
            addMessage('Je traite votre demande et reviens vers vous rapidement...', 'assistant');
        }, 1500);
    });

    /**
     * Ajoute un message au chat
     */
    function addMessage(content, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message--${type}`;
        
        const time = new Date().toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });

        let avatarHtml = '';
        if (type === 'assistant') {
            avatarHtml = `<div class="message__avatar"><div class="assistant-avatar">SV</div></div>`;
        }

        const userAvatarHtml = type === 'user' ? `<div class="message__avatar"><div class="user-avatar">Vous</div></div>` : '';

        messageDiv.innerHTML = `
            ${avatarHtml}
            <div class="message__content-wrapper">
                <div class="message__bubble">
                    <div class="message__text">${escapeHtml(content)}</div>
                </div>
                <span class="message__time">${time}</span>
            </div>
            ${userAvatarHtml}
        `;
        
        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    /**
     * Affiche l'indicateur "en train d'écrire"
     */
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message message--assistant typing-indicator';
        typingDiv.id = 'typingIndicator';
        
        typingDiv.innerHTML = `
            <div class="message__avatar">
                <div class="assistant-avatar">SV</div>
            </div>
            <div class="message__content-wrapper">
                <div class="message__bubble">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(typingDiv);
        scrollToBottom();
    }

    /**
     * Supprime l'indicateur de typing
     */
    function removeTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.remove();
        }
    }

    /**
     * Scroll vers le dernier message
     */
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /**
     * Échappe le HTML pour la sécurité
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Scroll initial vers le bas
    scrollToBottom();
});