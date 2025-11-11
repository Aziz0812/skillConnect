<?php
if (!defined('ADMIN_PAGE')) {
    die('Direct access not permitted');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> | SkillConnect Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        /* Top Navigation */
        .admin-topnav {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .admin-badge-top {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: #f8f9fa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .admin-user-info:hover {
            background: #e9ecef;
        }
        
        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
        
        .admin-user-name {
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        /* Sidebar Navigation */
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .admin-sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #e9ecef;
            padding: 30px 0;
        }
        
        .admin-nav {
            list-style: none;
        }
        
        .admin-nav-item {
            margin-bottom: 4px;
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 30px;
            color: #5a6c7d;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .admin-nav-link:hover {
            background: #f8f9fa;
            color: #667eea;
            border-left-color: #667eea;
        }
        
        .admin-nav-link.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1) 0%, transparent 100%);
            color: #667eea;
            border-left-color: #667eea;
        }
        
        .nav-icon {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            margin-left: auto;
        }
        
        /* Main Content Area */
        .admin-content {
            flex: 1;
            padding: 30px;
            max-width: 1400px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Utility Classes */
        .container {
            max-width: 100%;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 30px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .toast {
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .toast.success {
            border-left: 4px solid #28a745;
        }
        
        .toast.error {
            border-left: 4px solid #dc3545;
        }
        
        .toast-icon {
            font-size: 20px;
        }
        
        .toast-message {
            flex: 1;
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="admin-topnav">
        <div class="admin-logo">
            <span>🛡️ SkillConnect</span>
            <span class="admin-badge-top">Admin</span>
        </div>
        
        <div class="admin-user-menu">
            <div class="admin-user-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_fname, 0, 1)); ?>
                </div>
                <span class="admin-user-name"><?php echo htmlspecialchars($admin_name); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </nav>
    
    <!-- Main Layout -->
    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <ul class="admin-nav">
                <li class="admin-nav-item">
                    <a href="index.php" class="admin-nav-link <?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="approvals.php" class="admin-nav-link <?php echo ($current_page ?? '') === 'approvals' ? 'active' : ''; ?>">
                        <span class="nav-icon">✅</span>
                        <span>Pending Approvals</span>
                        <?php
                        // Get pending count
                        $pending_stmt = $conn->query("SELECT COUNT(*) as cnt FROM skills WHERE ApprovalStatus='pending'");
                        $pending_count = $pending_stmt->fetch_assoc()['cnt'];
                        if ($pending_count > 0) {
                            echo '<span class="badge">' . $pending_count . '</span>';
                        }
                        ?>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="users.php" class="admin-nav-link <?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>">
                        <span class="nav-icon">👥</span>
                        <span>Users</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="services.php" class="admin-nav-link <?php echo ($current_page ?? '') === 'services' ? 'active' : ''; ?>">
                        <span class="nav-icon">🛠️</span>
                        <span>Services</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="activity_log.php" class="admin-nav-link <?php echo ($current_page ?? '') === 'logs' ? 'active' : ''; ?>">
                        <span class="nav-icon">📜</span>
                        <span>Activity Log</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="container">