<?php
define('ADMIN_PAGE', true);
session_start();
require '../db.php';

// Log logout action
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    $admin_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (AdminID, Action, TargetType, TargetID, Details, IPAddress) VALUES (?, 'logout', 'user', ?, 'Admin logged out', ?)");
    $stmt->bind_param("iis", $admin_id, $admin_id, $ip);
    $stmt->execute();
    $stmt->close();
}

// Clear session
session_destroy();

// Redirect to admin login
header("Location: ../admin_login.php");
exit;
?>
