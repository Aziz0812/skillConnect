<?php
session_start();
require "db.php";

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================
// GET USER PROFILE DATA
// ============================================
if ($action === 'get_profile') {
    $stmt = $conn->prepare("
        SELECT FName, LName, MName, Email, Phone, Location, 
               City, Province, Barangay, DateOfBirth, Bio
        FROM users 
        WHERE ID = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// ============================================
// UPDATE BASIC INFO
// ============================================
if ($action === 'update_basic_info') {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $mname = trim($_POST['mname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    // Validation
    if (empty($fname) || empty($lname)) {
        echo json_encode(['success' => false, 'message' => 'First and last name are required']);
        exit;
    }
    
    // Validate date of birth (must be 18+)
    if (!empty($dob)) {
        $dobDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($dobDate)->y;
        
        if ($age < 13) {
            echo json_encode(['success' => false, 'message' => 'You must be at least 13 years old']);
            exit;
        }
    }
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET FName = ?, LName = ?, MName = ?, Phone = ?, DateOfBirth = ?, Bio = ?
        WHERE ID = ?
    ");
    $stmt->bind_param("ssssssi", $fname, $lname, $mname, $phone, $dob, $bio, $userId);
    
    if ($stmt->execute()) {
        // Update session
        $_SESSION['name'] = $fname . ' ' . $lname;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile updated successfully!',
            'data' => [
                'FName' => $fname,
                'LName' => $lname,
                'MName' => $mname
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
    exit;
}

// ============================================
// UPDATE ADDRESS
// ============================================
if ($action === 'update_address') {
    $location = trim($_POST['location'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET Location = ?, City = ?, Province = ?, Barangay = ?
        WHERE ID = ?
    ");
    $stmt->bind_param("ssssi", $location, $city, $province, $barangay, $userId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Address updated successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update address']);
    }
    exit;
}

// ============================================
// UPDATE EMAIL (with verification)
// ============================================
if ($action === 'update_email') {
    $newEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT ID FROM users WHERE Email = ? AND ID != ?");
    $checkStmt->bind_param("si", $newEmail, $userId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        exit;
    }
    
    // Verify current password
    $stmt = $conn->prepare("SELECT Password FROM users WHERE ID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['Password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
    
    // Update email
    $updateStmt = $conn->prepare("UPDATE users SET Email = ?, GMail = ? WHERE ID = ?");
    $updateStmt->bind_param("ssi", $newEmail, $newEmail, $userId);
    
    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Email updated successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update email']);
    }
    exit;
}

// ============================================
// UPLOAD PROFILE PHOTO
// ============================================
if ($action === 'upload_photo') {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed']);
        exit;
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5MB']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Delete old profile photo
    $stmt = $conn->prepare("SELECT ProfilePhoto FROM users WHERE ID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldData = $result->fetch_assoc();
    if (!empty($oldData['ProfilePhoto']) && file_exists($oldData['ProfilePhoto'])) {
        unlink($oldData['ProfilePhoto']);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $updateStmt = $conn->prepare("UPDATE users SET ProfilePhoto = ? WHERE ID = ?");
        $updateStmt->bind_param("si", $filepath, $userId);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Profile photo updated!',
                'photo_url' => $filepath
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    }
    exit;
}

// ============================================
// REMOVE PROFILE PHOTO
// ============================================
if ($action === 'remove_photo') {
    $stmt = $conn->prepare("SELECT ProfilePhoto FROM users WHERE ID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!empty($user['ProfilePhoto']) && file_exists($user['ProfilePhoto'])) {
        unlink($user['ProfilePhoto']);
    }
    
    $updateStmt = $conn->prepare("UPDATE users SET ProfilePhoto = NULL WHERE ID = ?");
    $updateStmt->bind_param("i", $userId);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile photo removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove photo']);
    }
    exit;
}

// ============================================
// CHANGE PASSWORD
// ============================================
if ($action === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    // Verify current password
    $stmt = $conn->prepare("SELECT Password FROM users WHERE ID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($currentPassword, $user['Password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE users SET Password = ? WHERE ID = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);
    
    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Password changed successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>