<?php
// Prevent direct access
if (!defined('ADMIN_PAGE')) {
    die('Direct access not permitted');
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_login.php");
    exit;
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_fname = $_SESSION['FName'] ?? 'Admin';

// Function to log admin actions
function logAdminAction($conn, $admin_id, $action, $target_type, $target_id, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (AdminID, Action, TargetType, TargetID, Details, IPAddress) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $admin_id, $action, $target_type, $target_id, $details, $ip);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to get admin stats
function getAdminStats($conn) {
    $stmt = $conn->query("SELECT * FROM admin_stats_view LIMIT 1");
    if ($stmt) {
        return $stmt->fetch_assoc();
    }
    
    // Fallback if view doesn't exist
    return [
        'total_clients' => 0,
        'total_providers' => 0,
        'total_services' => 0,
        'pending_services' => 0,
        'active_requests' => 0,
        'completed_requests' => 0,
        'total_revenue' => 0
    ];
}
?>