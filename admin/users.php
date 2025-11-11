<?php
define('ADMIN_PAGE', true);
session_start();
require '../db.php';
require 'includes/auth_check.php';

$page_title = 'User Management';
$current_page = 'users';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    // ✅ Verify ownership before action
    $check = $conn->prepare("SELECT Role FROM users WHERE ID = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $result = $check->get_result();
    $user = $result->fetch_assoc();
    $check->close();
    
    if (!$user || $user['Role'] === 'admin') {
        $_SESSION['admin_error'] = 'Cannot modify this user';
        header("Location: users.php");
        exit;
    }
    
    if ($action === 'suspend') {
        $stmt = $conn->prepare("UPDATE users SET AccountStatus = 'suspended' WHERE ID = ? AND Role != 'admin'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logAdminAction($conn, $admin_id, 'suspend', 'user', $user_id, 'User suspended');
            $_SESSION['admin_success'] = 'User suspended successfully';
        } else {
            $_SESSION['admin_error'] = 'Failed to suspend user';
        }
        $stmt->close();
    } elseif ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET AccountStatus = 'active' WHERE ID = ? AND Role != 'admin'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logAdminAction($conn, $admin_id, 'activate', 'user', $user_id, 'User activated');
            $_SESSION['admin_success'] = 'User activated successfully';
        } else {
            $_SESSION['admin_error'] = 'Failed to activate user';
        }
        $stmt->close();
    } elseif ($action === 'ban') {
        $stmt = $conn->prepare("UPDATE users SET AccountStatus = 'banned' WHERE ID = ? AND Role != 'admin'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logAdminAction($conn, $admin_id, 'ban', 'user', $user_id, 'User banned');
            $_SESSION['admin_success'] = 'User banned successfully';
        } else {
            $_SESSION['admin_error'] = 'Failed to ban user';
        }
        $stmt->close();

    }
    // ✅ EMAIL NOTIFICATION FOR SUSPEND/BAN ACTIONS
if (($action === 'suspend' || $action === 'ban') && isset($_SESSION['admin_success'])) {
    // Fetch user email and name
    $email_stmt = $conn->prepare("SELECT GMail, FName FROM users WHERE ID = ?");
    $email_stmt->bind_param("i", $user_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    
    if ($email_user = $email_result->fetch_assoc()) {
        $user_email = $email_user['GMail'];
        $user_fname = $email_user['FName'];
        
        // Prepare email content
        if ($action === 'suspend') {
            $subject = 'SkillConnect Account Suspended';
            $message = "Dear {$user_fname},\n\n";
            $message .= "Your SkillConnect account has been temporarily suspended.\n\n";
            $message .= "Reason: Possible inactivity or policy violation.\n\n";
            $message .= "To request reactivation, please contact: admin@skillconnect.com\n\n";
            $message .= "Best regards,\nSkillConnect Admin Team";
        } else {
            $subject = 'SkillConnect Account Banned';
            $message = "Dear {$user_fname},\n\n";
            $message .= "Your SkillConnect account has been permanently banned.\n\n";
            $message .= "If you believe this action was taken in error, please contact: admin@skillconnect.com\n\n";
            $message .= "Best regards,\nSkillConnect Admin Team";
        }
        
        // Send email
        $headers = "From: noreply@skillconnect.com\r\n";
        $headers .= "Reply-To: admin@skillconnect.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($user_email, $subject, $message, $headers);
    }
    
    $email_stmt->close();
}
    
    header("Location: users.php");
    exit;
}
// Get filter
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = ["Role != 'admin'"];
$params = [];
$types = '';

if ($role_filter !== 'all') {
    $where_clauses[] = "Role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($status_filter !== 'all') {
    $where_clauses[] = "AccountStatus = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(FName LIKE ? OR LName LIKE ? OR GMail LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_sql = implode(' AND ', $where_clauses);
$query = "SELECT ID, FName, LName, GMail, Role, AccountStatus, Location, UpdatedAt FROM users WHERE $where_sql ORDER BY UpdatedAt DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

include 'includes/header.php';
?>

<style>
    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-btn {
        padding: 10px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        align-self: flex-end;
        transition: all 0.3s ease;
    }
    
    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .users-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    th {
        padding: 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    td {
        padding: 16px;
        border-bottom: 1px solid #f1f3f5;
        font-size: 14px;
    }
    
    tr:last-child td {
        border-bottom: none;
    }
    
    tbody tr:hover {
        background: #f8f9fa;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 14px;
    }
    
    .user-details {
        flex: 1;
    }
    
    .user-name {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .user-email {
        font-size: 12px;
        color: #6c757d;
    }
    
    .role-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .role-badge.client {
        background: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
    }
    
    .role-badge.provider {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge.active {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .status-badge.suspended {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    
    .status-badge.banned {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .action-btns {
        display: flex;
        gap: 8px;
    }
    
    .action-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .action-btn.suspend {
        background: #ffc107;
        color: white;
    }
    
    .action-btn.activate {
        background: #28a745;
        color: white;
    }
    
    .action-btn.ban {
        background: #dc3545;
        color: white;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }
    
    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.5;
    }
</style>

<div class="page-header">
    <h1 class="page-title">👥 User Management</h1>
    <p class="page-subtitle">Manage all clients and service providers</p>
</div>

<form class="filter-bar" method="GET">
    <div class="filter-group">
        <label>Role</label>
        <select name="role">
            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
            <option value="client" <?php echo $role_filter === 'client' ? 'selected' : ''; ?>>Clients</option>
            <option value="provider" <?php echo $role_filter === 'provider' ? 'selected' : ''; ?>>Providers</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Status</label>
        <select name="status">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <button type="submit" class="filter-btn">🔍 Filter</button>
</form>

<div class="users-table">
    <?php if ($users && $users->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['FName'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($user['FName'] . ' ' . $user['LName']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['GMail']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge <?php echo $user['Role']; ?>">
                                <?php echo $user['Role'] === 'client' ? '👤 Client' : '🛠️ Provider'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $user['AccountStatus']; ?>">
                                <?php echo ucfirst($user['AccountStatus']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['Location'] ?: 'Not set'); ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['UpdatedAt'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <?php if ($user['AccountStatus'] === 'active'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="action-btn suspend" onclick="return confirm('Suspend this user?')">⚠️ Suspend</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                        <input type="hidden" name="action" value="ban">
                                        <button type="submit" class="action-btn ban" onclick="return confirm('Ban this user permanently?')">🚫 Ban</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="action-btn activate" onclick="return confirm('Activate this user?')">✅ Activate</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <p>No users found matching your criteria</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>