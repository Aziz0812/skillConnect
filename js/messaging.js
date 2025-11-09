// js/messaging.js - Firebase Real-time Messaging

import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { 
  getFirestore, 
  collection, 
  addDoc, 
  query, 
  orderBy, 
  onSnapshot,
  serverTimestamp
} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-firestore.js";

// Firebase config (YOUR existing config)
const firebaseConfig = {
  apiKey: "AIzaSyBmgnCsRgeGambbsfBxHy91JV4BsB1wxeM",
  authDomain: "skillconnect-6e03b.firebaseapp.com",
  projectId: "skillconnect-6e03b",
  storageBucket: "skillconnect-6e03b.firebasestorage.app",
  messagingSenderId: "697479842985",
  appId: "1:697479842985:web:b631e05b13a6f91cea2db8"
};

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

// ============================================
// MESSAGING CLASS
// ============================================
class MessagingSystem {
  constructor(userId, userRole) {
    this.userId = userId;
    this.userRole = userRole;
    this.currentConversationId = null;
    this.unsubscribe = null;
  }

  // Get or create conversation
  async getOrCreateConversation(requestId) {
    try {
      const formData = new FormData();
      formData.append('request_id', requestId);
      
      const res = await fetch('messages.php?action=get_or_create_conversation', {
        method: 'POST',
        body: formData
      });
      
      const data = await res.json();
      if (data.ok) {
        this.currentConversationId = data.conversation_id;
        return data.conversation_id;
      }
      throw new Error(data.error || 'Failed to create conversation');
    } catch (err) {
      console.error('Error creating conversation:', err);
      throw err;
    }
  }

  // Load conversations list
  async loadConversations() {
    try {
      const res = await fetch('messages.php?action=get_conversations');
      const data = await res.json();
      
      if (data.ok) {
        return data.data;
      }
      throw new Error(data.error || 'Failed to load conversations');
    } catch (err) {
      console.error('Error loading conversations:', err);
      return [];
    }
  }

  // Get conversation details
  async getConversationDetails(conversationId) {
    try {
      const res = await fetch(`messages.php?action=get_conversation_details&conversation_id=${conversationId}`);
      const data = await res.json();
      
      if (data.ok) {
        return data.data;
      }
      throw new Error(data.error || 'Failed to load conversation');
    } catch (err) {
      console.error('Error loading conversation:', err);
      throw err;
    }
  }

  
  // Send message
async sendMessage(conversationId, messageText) {
  try {
    // ✅ CRITICAL: Trim but don't truncate
    const cleanText = messageText.trim();
    
    if (!cleanText || cleanText.length === 0) {
      console.warn('❌ Empty message, not sending');
      return;
    }
    
    // Add to Firestore
    const messagesRef = collection(db, 'conversations', conversationId.toString(), 'messages');
    const docRef = await addDoc(messagesRef, {
      senderId: this.userId,
      senderRole: this.userRole,
      text: cleanText, // ✅ Full text here
      timestamp: serverTimestamp(),
      read: false
    });

    // Save metadata to MySQL
    const formData = new FormData();
    formData.append('conversation_id', conversationId);
    formData.append('firebase_message_id', docRef.id);
    formData.append('message_text', cleanText); // ✅ Full text here too
    
    const res = await fetch('messages.php?action=save_message', {
      method: 'POST',
      body: formData
    });
    
    const data = await res.json();
    if (!data.ok) {
      console.warn('Failed to save message metadata:', data.error);
    }
    
    return docRef.id;
  } catch (err) {
    console.error('Error sending message:', err);
    throw err;
  }
}

  // Listen to messages in real-time
  listenToMessages(conversationId, callback) {
    // Unsubscribe from previous listener
    if (this.unsubscribe) {
      this.unsubscribe();
    }

    const messagesRef = collection(db, 'conversations', conversationId.toString(), 'messages');
    const q = query(messagesRef, orderBy('timestamp', 'asc'));
    
    this.unsubscribe = onSnapshot(q, (snapshot) => {
      const messages = [];
      snapshot.forEach((doc) => {
        messages.push({
          id: doc.id,
          ...doc.data()
        });
      });
      callback(messages);
    }, (error) => {
      console.error('Error listening to messages:', error);
    });
  }

  // Mark conversation as read
  async markAsRead(conversationId) {
    try {
      const formData = new FormData();
      formData.append('conversation_id', conversationId);
      
      const res = await fetch('messages.php?action=mark_read', {
        method: 'POST',
        body: formData
      });
      
      const data = await res.json();
      return data.ok;
    } catch (err) {
      console.error('Error marking as read:', err);
      return false;
    }
  }

  // Get unread count
  async getUnreadCount() {
    try {
      const res = await fetch('messages.php?action=get_unread_count');
      const data = await res.json();
      
      if (data.ok) {
        return data.unread;
      }
      return 0;
    } catch (err) {
      console.error('Error getting unread count:', err);
      return 0;
    }
  }

  // Stop listening
  stopListening() {
    if (this.unsubscribe) {
      this.unsubscribe();
      this.unsubscribe = null;
    }
  }
}

// ============================================
// FORMAT TIMESTAMP (FIXED - FACEBOOK-STYLE)
// ============================================
function formatTimestamp(timestamp) {
  if (!timestamp) return '';
  
  // Handle Firebase Timestamp objects
  let date;
  if (timestamp.toDate && typeof timestamp.toDate === 'function') {
    date = timestamp.toDate();
  } else if (timestamp.seconds) {
    // Firebase Timestamp format: { seconds, nanoseconds }
    date = new Date(timestamp.seconds * 1000);
  } else {
    date = new Date(timestamp);
  }
  
  // Check if date is valid
  if (isNaN(date.getTime())) {
    console.warn('Invalid timestamp:', timestamp);
    return '';
  }
  
  const now = new Date();
  const diffMs = now - date;
  const diffSeconds = Math.floor(diffMs / 1000);
  const diffMinutes = Math.floor(diffSeconds / 60);
  const diffHours = Math.floor(diffMinutes / 60);
  const diffDays = Math.floor(diffHours / 24);
  const diffWeeks = Math.floor(diffDays / 7);
  const diffMonths = Math.floor(diffDays / 30);
  const diffYears = Math.floor(diffDays / 365);
  
  // Facebook-style formatting
  if (diffSeconds < 60) return 'Just now';
  if (diffMinutes === 1) return '1 min ago';
  if (diffMinutes < 60) return `${diffMinutes} mins ago`;
  if (diffHours === 1) return '1 hour ago';
  if (diffHours < 24) return `${diffHours} hours ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays} days ago`;
  if (diffWeeks === 1) return '1 week ago';
  if (diffWeeks < 4) return `${diffWeeks} weeks ago`;
  if (diffMonths === 1) return '1 month ago';
  if (diffMonths < 12) return `${diffMonths} months ago`;
  if (diffYears === 1) return '1 year ago';
  return `${diffYears} years ago`;
}

function createMessageBubble(message, isOwn) {
  const bubble = document.createElement('div');
  bubble.className = `message-bubble ${isOwn ? 'own' : 'other'}`;
  
  bubble.innerHTML = `
    <div class="message-content">
      <p>${escapeHtml(message.text)}</p>
      <span class="message-time">${formatTimestamp(message.timestamp)}</span>
    </div>
  `;
  
  return bubble;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Export for use in other files
window.MessagingSystem = MessagingSystem;
window.formatTimestamp = formatTimestamp;
window.createMessageBubble = createMessageBubble;