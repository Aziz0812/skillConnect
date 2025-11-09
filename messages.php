<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$userRole = $_SESSION['role'];
$action = $_GET['action'] ?? '';

// ============================================
// GET OR CREATE CONVERSATION
// ============================================
if ($action === 'get_or_create_conversation') {
    $requestId = intval($_POST['request_id'] ?? 0);
    
    if ($requestId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid request ID']);
        exit;
    }
    
    // Verify user is part of this request
    $stmt = $conn->prepare("
        SELECT ClientID, ProviderID 
        FROM request 
        WHERE requestID = ? AND (ClientID = ? OR ProviderID = ?)
    ");
    $stmt->bind_param("iii", $requestId, $userId, $userId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    
    if (!$request) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Check if conversation exists
    $stmt = $conn->prepare("SELECT ConversationID FROM conversations WHERE RequestID = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($existing = $result->fetch_assoc()) {
        echo json_encode([
            'ok' => true, 
            'conversation_id' => $existing['ConversationID'],
            'existing' => true
        ]);
    } else {
        // Create new conversation
        $stmt = $conn->prepare("
            INSERT INTO conversations (RequestID, ClientID, ProviderID, CreatedAt) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iii", $requestId, $request['ClientID'], $request['ProviderID']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'ok' => true,
                'conversation_id' => $conn->insert_id,
                'existing' => false
            ]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Failed to create conversation']);
        }
    }
    exit;
}

// ============================================
// GET CONVERSATIONS LIST
// ============================================
if ($action === 'get_conversations') {
    $roleField = $userRole === 'client' ? 'ClientID' : 'ProviderID';
    $otherRoleField = $userRole === 'client' ? 'ProviderID' : 'ClientID';
    $unreadField = $userRole === 'client' ? 'ClientUnreadCount' : 'ProviderUnreadCount';
    
    $stmt = $conn->prepare("
        SELECT 
            c.ConversationID,
            c.RequestID,
            c.LastMessageAt,
            c.LastMessage,
            c.$unreadField AS UnreadCount,
            u.FName,
            u.LName,
            u.ProfilePhoto,
            r.Status AS RequestStatus,
            COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName
        FROM conversations c
        JOIN users u ON c.$otherRoleField = u.ID
        JOIN request r ON c.RequestID = r.requestID
        JOIN skills s ON r.SkillID = s.SkillID
        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
        WHERE c.$roleField = ?
        ORDER BY c.LastMessageAt DESC, c.CreatedAt DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $conversations]);
    exit;
}

// ============================================
// GET CONVERSATION DETAILS
// ============================================
if ($action === 'get_conversation_details') {
    $convId = intval($_GET['conversation_id'] ?? 0);
    
    if ($convId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid conversation ID']);
        exit;
    }
    
    // Verify access
    $roleField = $userRole === 'client' ? 'ClientID' : 'ProviderID';
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            u.FName,
            u.LName,
            u.ProfilePhoto,
            r.Status AS RequestStatus,
            COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName
        FROM conversations c
        JOIN users u ON c." . ($userRole === 'client' ? 'ProviderID' : 'ClientID') . " = u.ID
        JOIN request r ON c.RequestID = r.requestID
        JOIN skills s ON r.SkillID = s.SkillID
        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
        WHERE c.ConversationID = ? AND c.$roleField = ?
    ");
    $stmt->bind_param("ii", $convId, $userId);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    
    if (!$conversation) {
        echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
        exit;
    }
    
    echo json_encode(['ok' => true, 'data' => $conversation]);
    exit;
}

// ============================================
// SAVE MESSAGE METADATA
// ============================================
if ($action === 'save_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $convId = intval($_POST['conversation_id'] ?? 0);
    $firebaseId = trim($_POST['firebase_message_id'] ?? '');
    $messageText = trim($_POST['message_text'] ?? '');
    
    if ($convId <= 0 || empty($firebaseId) || empty($messageText)) {
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Verify conversation access
    $roleField = $userRole === 'client' ? 'ClientID' : 'ProviderID';
    $otherUnreadField = $userRole === 'client' ? 'ProviderUnreadCount' : 'ClientUnreadCount';
    
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE ConversationID = ? AND $roleField = ?");
    $stmt->bind_param("ii", $convId, $userId);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    
    if (!$conversation) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Save message metadata
    $stmt = $conn->prepare("
        INSERT INTO messages (ConversationID, FirebaseMessageID, SenderID, SenderRole, MessageText, CreatedAt)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isiss", $convId, $firebaseId, $userId, $userRole, $messageText);
    
    if ($stmt->execute()) {
        // Update conversation last message and increment unread count
        $stmt = $conn->prepare("
            UPDATE conversations 
            SET LastMessageAt = NOW(), 
                LastMessage = ?,
                $otherUnreadField = $otherUnreadField + 1
            WHERE ConversationID = ?
        ");
        $stmt->bind_param("si", $messageText, $convId);
        $stmt->execute();
        
        echo json_encode(['ok' => true, 'message_id' => $conn->insert_id]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to save message']);
    }
    exit;
}

// ============================================
// MARK MESSAGES AS READ
// ============================================
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $convId = intval($_POST['conversation_id'] ?? 0);
    
    if ($convId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid conversation ID']);
        exit;
    }
    
    // Verify access
    $roleField = $userRole === 'client' ? 'ClientID' : 'ProviderID';
    $unreadField = $userRole === 'client' ? 'ClientUnreadCount' : 'ProviderUnreadCount';
    
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE ConversationID = ? AND $roleField = ?");
    $stmt->bind_param("ii", $convId, $userId);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    
    if (!$conversation) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Reset unread count
    $stmt = $conn->prepare("UPDATE conversations SET $unreadField = 0 WHERE ConversationID = ?");
    $stmt->bind_param("i", $convId);
    
    if ($stmt->execute()) {
        // Mark individual messages as read
        $otherRole = $userRole === 'client' ? 'provider' : 'client';
        $stmt = $conn->prepare("
            UPDATE messages 
            SET IsRead = 1, ReadAt = NOW() 
            WHERE ConversationID = ? AND SenderRole = ? AND IsRead = 0
        ");
        $stmt->bind_param("is", $convId, $otherRole);
        $stmt->execute();
        
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to mark as read']);
    }
    exit;
}

// ============================================
// GET UNREAD COUNT
// ============================================
if ($action === 'get_unread_count') {
    $roleField = $userRole === 'client' ? 'ClientID' : 'ProviderID';
    $unreadField = $userRole === 'client' ? 'ClientUnreadCount' : 'ProviderUnreadCount';
    
    $stmt = $conn->prepare("
        SELECT SUM($unreadField) AS TotalUnread 
        FROM conversations 
        WHERE $roleField = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'ok' => true, 
        'unread' => intval($result['TotalUnread'] ?? 0)
    ]);
    exit;
}

// ============================================
// DELETE CONVERSATION
// ============================================
if ($action === 'delete_conversation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $convId = intval($_POST['conversation_id'] ?? 0);
    
    if ($convId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid conversation ID']);
        exit;
    }
    
    // Verify access
    $roleField = $userRole === 'client' ? 'ClientID' : 'ProviderID';
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE ConversationID = ? AND $roleField = ?");
    $stmt->bind_param("ii", $convId, $userId);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    
    if (!$conversation) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Delete messages first
    $stmt = $conn->prepare("DELETE FROM messages WHERE ConversationID = ?");
    $stmt->bind_param("i", $convId);
    $stmt->execute();
    
    // Delete conversation
    $stmt = $conn->prepare("DELETE FROM conversations WHERE ConversationID = ?");
    $stmt->bind_param("i", $convId);
    
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'message' => 'Conversation deleted']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to delete conversation']);
    }
    exit;
}

// Invalid action (this should be the LAST thing before exit)
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
exit;
?>