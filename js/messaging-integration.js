// messaging-integration.js - Global integration code for messaging system

let messagingSystem = null;
let currentConversationId = null;

// ============================================
// INITIALIZE MESSAGING SYSTEM
// ============================================
window.initMessaging = async function() {
  console.log('🚀 Initializing messaging system...');
  
  // Check if user is authenticated
  if (typeof window.USER_ID === 'undefined' || typeof window.USER_ROLE === 'undefined') {
    console.error('❌ User not authenticated');
    showToast?.('Please log in to access messages', 'error');
    return;
  }
  
  console.log('✅ User authenticated:', { userId: window.USER_ID, role: window.USER_ROLE });
  
  // Initialize messaging system
  if (!messagingSystem) {
    if (typeof window.MessagingSystem === 'undefined') {
      console.error('❌ MessagingSystem class not loaded. Check if messaging.js is loaded correctly.');
      showToast?.('Messaging system failed to load', 'error');
      return;
    }
    
    messagingSystem = new window.MessagingSystem(window.USER_ID, window.USER_ROLE);
    console.log('✅ MessagingSystem created');
  }
  
  // Load conversations
  await loadConversations();
  
  // Setup message form
  setupMessageForm();
  
  // Update unread badge
  updateUnreadBadge();
};

// ============================================
// LOAD CONVERSATIONS LIST
// ============================================
async function loadConversations() {
  const listEl = document.getElementById('conversationsList');
  if (!listEl) {
    console.error('❌ conversationsList element not found');
    return;
  }
  
  console.log('📋 Loading conversations...');
  
  listEl.innerHTML = `
    <div class="loading-state">
      <div class="spinner"></div>
      <p>Loading conversations...</p>
    </div>
  `;
  
  try {
    const conversations = await messagingSystem.loadConversations();
    console.log('✅ Loaded conversations:', conversations);
    
    if (conversations.length === 0) {
      listEl.innerHTML = `
        <div class="empty-state">
          <p>No conversations yet</p>
          <small>Start a conversation by booking a service</small>
        </div>
      `;
      return;
    }
    
   // Render conversations with delete buttons
listEl.innerHTML = conversations.map(conv => `
  <div class="conversation-item ${conv.UnreadCount > 0 ? 'unread' : ''}" 
       data-conversation-id="${conv.ConversationID}"
       style="display: flex; align-items: center; gap: 12px; padding: 15px; border-bottom: 1px solid #f1f3f5; cursor: pointer; position: relative;">
    
    <!-- Main clickable conversation area -->
    <div onclick="openConversation(${conv.ConversationID}); event.stopPropagation();" 
         style="display: flex; gap: 12px; flex: 1; align-items: center;">
      
      <div class="conversation-avatar">
        ${conv.ProfilePhoto 
          ? `<img src="${conv.ProfilePhoto}" alt="${conv.FName}">`
          : `<div class="avatar-initials">${conv.FName.charAt(0)}${conv.LName.charAt(0)}</div>`
        }
      </div>
      
      <div class="conversation-info">
        <div class="conversation-header">
          <h4>${conv.FName} ${conv.LName}</h4>
          ${conv.UnreadCount > 0 ? `<span class="unread-badge">${conv.UnreadCount}</span>` : ''}
        </div>
        <p class="conversation-service">${conv.SkillName}</p>
        <p class="conversation-preview">${conv.LastMessage || 'No messages yet'}</p>
        <small class="conversation-time">${formatTimestamp(conv.LastMessageAt)}</small>
      </div>
    </div>
    
    <!-- Delete button (separate, visible on hover) -->
    <button class="delete-conversation-btn" 
            onclick="deleteConversation(${conv.ConversationID}); event.stopPropagation();"
            style="background: none; border: none; font-size: 20px; cursor: pointer; opacity: 0; transition: opacity 0.2s, background 0.2s, transform 0.2s; padding: 8px; border-radius: 4px; color: #dc3545;"
            onmouseover="this.style.opacity='1'; this.style.background='rgba(220,53,69,0.1)'; this.style.transform='scale(1.1)';"
            onmouseout="this.style.opacity='0.6'; this.style.background='none'; this.style.transform='scale(1)';"
            title="Delete conversation">
      🗑️
    </button>
  </div>
`).join('');
    
  } catch (err) {
    console.error('❌ Error loading conversations:', err);
    listEl.innerHTML = `
      <div class="error-state">
        <p>Failed to load conversations</p>
        <button onclick="loadConversations()" class="btn-primary">Retry</button>
      </div>
    `;
  }
}

// ============================================
// OPEN CONVERSATION - FIXED AVATAR VERSION
// ============================================
window.openConversation = async function(conversationId) {
  console.log('💬 Opening conversation:', conversationId);
  
  currentConversationId = conversationId;
  
  // Update UI
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.classList.remove('active');
  });
  document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.add('active');
  
  // Show chat container, hide placeholder
  document.getElementById('chatPlaceholder')?.classList.remove('active');
  document.getElementById('chatContainer')?.classList.add('active');
  
  // Load conversation details
  try {
    const details = await messagingSystem.getConversationDetails(conversationId);
    console.log('✅ Conversation details:', details);
    
    // ✅ FIX: Update header with FULL NAME AND AVATAR
    const contactName = document.getElementById('contactName');
    const contactService = document.getElementById('contactService');
    const contactAvatar = document.querySelector('.chat-header .contact-avatar');
    
    if (contactName) {
      contactName.textContent = `${details.FName} ${details.LName}`;
    }
    
    if (contactService) {
      contactService.textContent = details.SkillName;
    }
    
    // ✅ NEW: Render avatar in chat header
    if (contactAvatar) {
      if (details.ProfilePhoto && details.ProfilePhoto.trim() !== '') {
        contactAvatar.innerHTML = `<img src="${details.ProfilePhoto}" alt="Avatar">`;
      } else {
        const initials = (details.FName.charAt(0) + details.LName.charAt(0)).toUpperCase();
        contactAvatar.innerHTML = initials;
      }
    }
    
    // Load messages
    messagingSystem.listenToMessages(conversationId, (messages) => {
      renderMessages(messages);
    });
    
    // Mark as read
    await messagingSystem.markAsRead(conversationId);
    updateUnreadBadge();
    
    // Remove unread indicator
    document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.remove('unread');
    
  } catch (err) {
    console.error('❌ Error opening conversation:', err);
    showToast?.('Failed to load conversation', 'error');
  }
};

// ============================================
// RENDER MESSAGES
// ============================================
function renderMessages(messages) {
  const container = document.getElementById('messagesContainer');
  if (!container) return;
  
  console.log('📝 Rendering messages:', messages.length);
  
  const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
  
  container.innerHTML = messages.map(msg => {
    const isOwn = msg.senderId === window.USER_ID;
    return window.createMessageBubble(msg, isOwn).outerHTML;
  }).join('');
  
  // Auto-scroll to bottom if user was already at bottom
  if (wasAtBottom || messages.length === 1) {
    container.scrollTop = container.scrollHeight;
  }
}

// ============================================
// SETUP MESSAGE FORM (FIXED - NO DUPLICATE SENDS)
// ============================================
function setupMessageForm() {
  const form = document.getElementById('messageForm');
  const input = document.getElementById('messageInput');
  const sendBtn = form?.querySelector('.btn-send');
  
  if (!form || !input || !sendBtn) {
    console.warn('⚠️ Message form elements not found');
    return;
  }
  
  console.log('✅ Message form setup complete');
  
  // ✅ CRITICAL: Remove any existing event listeners to prevent duplicates
  const newForm = form.cloneNode(true);
  form.parentNode.replaceChild(newForm, form);
  
  // Re-select elements after cloning
  const freshForm = document.getElementById('messageForm');
  const freshInput = document.getElementById('messageInput');
  const freshSendBtn = freshForm.querySelector('.btn-send');
  
  // Enable/disable send button based on input
  freshInput.addEventListener('input', () => {
    freshSendBtn.disabled = freshInput.value.trim().length === 0;
  });
  
  // Handle form submission (ONLY ONCE)
  freshForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const message = freshInput.value.trim();
    if (!message || !currentConversationId) return;
    
    // ✅ PREVENT DOUBLE-SUBMIT
    if (freshSendBtn.disabled) return;
    freshSendBtn.disabled = true;
    
    const originalText = freshSendBtn.innerHTML;
    freshSendBtn.innerHTML = '⏳ Sending...';
    
    try {
      await messagingSystem.sendMessage(currentConversationId, message);
      console.log('✅ Message sent');
      
      freshInput.value = '';
      freshInput.focus();
      
      // Reload conversations to update last message
      await loadConversations();
      
    } catch (err) {
      console.error('❌ Error sending message:', err);
      showToast?.('Failed to send message', 'error');
    } finally {
      freshSendBtn.disabled = false;
      freshSendBtn.innerHTML = originalText;
    }
  });
}

// ============================================
// UPDATE UNREAD BADGE
// ============================================
async function updateUnreadBadge() {
  try {
    const unread = await messagingSystem.getUnreadCount();
    console.log('📬 Unread count:', unread);
    
    const badge = document.getElementById('messageBadge');
    if (badge) {
      if (unread > 0) {
        badge.textContent = unread;
        badge.style.display = 'inline-block';
      } else {
        badge.style.display = 'none';
      }
    }
  } catch (err) {
    console.error('❌ Error updating unread badge:', err);
  }
}

// ============================================
// DELETE CONVERSATION
// ============================================
window.deleteConversation = async function(conversationId) {
  if (!confirm('Delete this conversation? This cannot be undone.')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('conversation_id', conversationId);
    
    const res = await fetch('messages.php?action=delete_conversation', {
      method: 'POST',
      body: formData
    });
    
    const data = await res.json();
    
    if (data.ok) {
      showToast?.('Conversation deleted', 'success');
      
      // Close chat if it's open
      if (currentConversationId === conversationId) {
        document.getElementById('chatContainer')?.classList.remove('active');
        document.getElementById('chatPlaceholder')?.classList.add('active');
        currentConversationId = null;
      }
      
      // Reload conversations list
      await loadConversations();
    } else {
      showToast?.(data.error || 'Failed to delete conversation', 'error');
    }
  } catch (err) {
    console.error('Delete error:', err);
    showToast?.('Error deleting conversation', 'error');
  }
};

// ============================================
// START MESSAGE FROM REQUEST
// ============================================
window.startMessageFromRequest = async function(requestId) {
  console.log('🚀 Starting message from request:', requestId);
  
  if (!messagingSystem) {
    console.error('❌ Messaging system not initialized');
    await initMessaging();
  }
  
  try {
    // Get or create conversation
    const conversationId = await messagingSystem.getOrCreateConversation(requestId);
    console.log('✅ Got conversation:', conversationId);
    
    // Open messaging modal
    if (typeof openModal === 'function') {
      openModal('messagingModal');
    } else {
      document.getElementById('messagingModal')?.classList.add('show');
    }
    
    // Wait for modal to open, then initialize and open conversation
    setTimeout(async () => {
      await initMessaging();
      await openConversation(conversationId);
    }, 300);
    
  } catch (err) {
    console.error('❌ Error starting conversation:', err);
    showToast?.('Failed to start conversation', 'error');
  }
};

// ============================================
// HELPER FUNCTIONS
// ============================================
function formatTimestamp(timestamp) {
  if (!timestamp) return '';
  
  const date = new Date(timestamp);
  const now = new Date();
  const diff = now - date;
  const hours = Math.floor(diff / 3600000);
  const days = Math.floor(diff / 86400000);
  
  if (hours < 1) return 'Just now';
  if (hours < 24) return `${hours}h ago`;
  if (days < 7) return `${days}d ago`;
  return date.toLocaleDateString();
}

// Poll for unread messages every 30 seconds
setInterval(() => {
  if (messagingSystem) {
    updateUnreadBadge();
  }
}, 30000);

console.log('✅ Messaging integration loaded');