<?php
define('ADMIN_PAGE', true);
session_start();
require '../db.php';
require 'includes/auth_check.php';

$page_title = 'Activity Log';
$current_page = 'logs';

// Get filters
$action_filter = $_GET['action_filter'] ?? 'all';
$date_filter = $_GET['date_filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($action_filter !== 'all') {
    $where_clauses[] = "l.Action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if ($date_filter !== 'all') {
    switch($date_filter) {
        case 'today':
            $where_clauses[] = "DATE(l.CreatedAt) = CURDATE()";
            break;
        case 'week':
            $where_clauses[] = "l.CreatedAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_clauses[] = "l.CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if (!empty($search)) {
    $where_clauses[] = "(u.FName LIKE ? OR u.LName LIKE ? OR l.Details LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "
    SELECT 
        l.LogID, l.Action, l.TargetType, l.TargetID, l.Details, l.CreatedAt, l.IPAddress,
        u.FName, u.LName
    FROM admin_activity_log l
    LEFT JOIN users u ON l.AdminID = u.ID
    $where_sql
    ORDER BY l.CreatedAt DESC
    LIMIT 100
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

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
    
    .logs-table {
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
    
    .action-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .action-badge.login { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .action-badge.logout { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
    .action-badge.approve { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .action-badge.reject { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    .action-badge.suspend { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
    .action-badge.activate { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .action-badge.ban { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    
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
    <h1 class="page-title">📜 Activity Log</h1>
    <p class="page-subtitle">Monitor all administrative actions</p>
</div>

<form class="filter-bar" method="GET">
    <div class="filter-group">
        <label>Action</label>
        <select name="action_filter">
            <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>All Actions</option>
            <option value="login" <?php echo $action_filter === 'login' ? 'selected' : ''; ?>>Login</option>
            <option value="logout" <?php echo $action_filter === 'logout' ? 'selected' : ''; ?>>Logout</option>
            <option value="approve" <?php echo $action_filter === 'approve' ? 'selected' : ''; ?>>Approve</option>
            <option value="reject" <?php echo $action_filter === 'reject' ? 'selected' : ''; ?>>Reject</option>
            <option value="suspend" <?php echo $action_filter === 'suspend' ? 'selected' : ''; ?>>Suspend</option>
            <option value="activate" <?php echo $action_filter === 'activate' ? 'selected' : ''; ?>>Activate</option>
            <option value="ban" <?php echo $action_filter === 'ban' ? 'selected' : ''; ?>>Ban</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Date</label>
        <select name="date_filter">
            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Admin name or details..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <button type="submit" class="filter-btn">🔍 Filter</button>
</form>

<div class="logs-table">
    <?php if ($logs && $logs->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($log['CreatedAt'])); ?></td>
                        <td><?php echo htmlspecialchars($log['FName'] . ' ' . $log['LName']); ?></td>
                        <td>
                            <span class="action-badge <?php echo $log['Action']; ?>">
                                <?php echo ucfirst($log['Action']); ?>
                            </span>
                        </td>
                        <td><?php echo ucfirst($log['TargetType']); ?> #<?php echo $log['TargetID']; ?></td>
                        <td><?php echo htmlspecialchars($log['Details'] ?? 'No details'); ?></td>
                        <td><?php echo htmlspecialchars($log['IPAddress'] ?? 'Unknown'); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <p>No activity logs found</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>