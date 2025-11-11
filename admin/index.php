<?php
define('ADMIN_PAGE', true);
session_start();
require '../db.php';
require 'includes/auth_check.php';

$page_title = 'Dashboard';
$current_page = 'dashboard';

// Get platform statistics
$stats = getAdminStats($conn);

// Get recent activity (last 10 actions)
$activity_query = "
    SELECT 
        l.LogID, l.Action, l.TargetType, l.TargetID, l.Details, l.CreatedAt,
        u.FName, u.LName
    FROM admin_activity_log l
    LEFT JOIN users u ON l.AdminID = u.ID
    ORDER BY l.CreatedAt DESC
    LIMIT 10
";
$recent_activity = $conn->query($activity_query);

// Get pending skills for quick preview
$pending_skills_query = "
    SELECT 
        s.SkillID, s.Description, s.Rate, s.RateType, s.DateAdded,
        COALESCE(c.CategoryName, s.CustomCategory, 'Uncategorized') AS CategoryName,
        u.FName, u.LName, u.ID as ProviderID
    FROM skills s
    LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
    JOIN users u ON s.UserID = u.ID
    WHERE s.ApprovalStatus = 'pending'
    ORDER BY s.DateAdded DESC
    LIMIT 5
";
$pending_skills = $conn->query($pending_skills_query);

// Get user growth data (last 6 months)
$user_growth_query = "
    SELECT 
        DATE_FORMAT(UpdatedAt, '%b %Y') as month,
        DATE_FORMAT(UpdatedAt, '%Y-%m') as month_sort,
        COUNT(*) as count,
        SUM(CASE WHEN Role = 'client' THEN 1 ELSE 0 END) as clients,
        SUM(CASE WHEN Role = 'provider' THEN 1 ELSE 0 END) as providers
    FROM users
    WHERE Role IN ('client', 'provider')
    AND UpdatedAt >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_sort, month
    ORDER BY month_sort ASC
";
$user_growth = $conn->query($user_growth_query);
$growth_data = [];
while ($row = $user_growth->fetch_assoc()) {
    $growth_data[] = $row;
}

// Get booking trends (last 6 months)
$booking_trends_query = "
    SELECT 
        DATE_FORMAT(CreatedAt, '%b %Y') as month,
        DATE_FORMAT(CreatedAt, '%Y-%m') as month_sort,
        COUNT(*) as total,
        SUM(CASE WHEN Status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN Status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM request
    WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_sort, month
    ORDER BY month_sort ASC
";
$booking_trends = $conn->query($booking_trends_query);
$trends_data = [];
while ($row = $booking_trends->fetch_assoc()) {
    $trends_data[] = $row;
}

include 'includes/header.php';
?>

<style>
    /* Dashboard Specific Styles */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }
    
    .stat-card {
        background: white;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .stat-card.primary { border-left-color: #667eea; }
    .stat-card.success { border-left-color: #28a745; }
    .stat-card.warning { border-left-color: #ffc107; }
    .stat-card.info { border-left-color: #17a2b8; }
    .stat-card.danger { border-left-color: #dc3545; }
    
    .stat-icon {
        font-size: 32px;
        margin-bottom: 12px;
        display: block;
    }
    
    .stat-label {
        color: #6c757d;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    
    .stat-value {
        font-size: 36px;
        font-weight: 800;
        color: #2c3e50;
        line-height: 1;
    }
    
    .stat-trend {
        font-size: 13px;
        margin-top: 8px;
        color: #6c757d;
    }
    
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 32px;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
    }
    
    .pending-skill-item {
        padding: 16px;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
        border-left: 4px solid #ffc107;
    }
    
    .pending-skill-item:hover {
        background: #e9ecef;
        transform: translateX(4px);
    }
    
    .skill-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    
    .skill-category {
        font-weight: 700;
        color: #2c3e50;
        font-size: 14px;
    }
    
    .skill-rate {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 12px;
    }
    
    .skill-provider {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 4px;
    }
    
    .skill-description {
        font-size: 13px;
        color: #495057;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .skill-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    
    .btn-sm {
        padding: 6px 16px;
        font-size: 12px;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-approve {
        background: #28a745;
        color: white;
    }
    
    .btn-approve:hover {
        background: #218838;
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: #6c757d;
        color: white;
    }
    
    .btn-view:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    
    .activity-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        border-bottom: 1px solid #f1f3f5;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }
    
    .activity-icon.login { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .activity-icon.logout { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
    .activity-icon.approve { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .activity-icon.reject { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    .activity-icon.suspend { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
    
    .activity-details {
        flex: 1;
    }
    
    .activity-action {
        font-size: 14px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .activity-meta {
        font-size: 12px;
        color: #6c757d;
        margin-top: 2px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    
    .view-all-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: color 0.2s;
    }
    
    .view-all-link:hover {
        color: #764ba2;
    }
    
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 640px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    /* 🎨 DASHBOARD ANIMATIONS & ENHANCEMENTS */
.stats-grid {
    animation: fadeInUp 0.6s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card {
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.stat-card:hover::before {
    opacity: 1;
    animation: ripple 1.5s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0.8);
        opacity: 1;
    }
    100% {
        transform: scale(1.2);
        opacity: 0;
    }
}

.stat-icon {
    animation: bounce 2s infinite ease-in-out;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

.stat-value {
    position: relative;
    overflow: hidden;
}

.stat-value::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.5), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.dashboard-grid {
    animation: fadeIn 0.8s ease 0.3s both;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}

.pending-skill-item {
    animation: slideInLeft 0.4s ease both;
}

.pending-skill-item:nth-child(1) { animation-delay: 0.1s; }
.pending-skill-item:nth-child(2) { animation-delay: 0.2s; }
.pending-skill-item:nth-child(3) { animation-delay: 0.3s; }
.pending-skill-item:nth-child(4) { animation-delay: 0.4s; }
.pending-skill-item:nth-child(5) { animation-delay: 0.5s; }

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.activity-item {
    animation: slideInRight 0.4s ease both;
}

.activity-item:nth-child(1) { animation-delay: 0.1s; }
.activity-item:nth-child(2) { animation-delay: 0.15s; }
.activity-item:nth-child(3) { animation-delay: 0.2s; }
.activity-item:nth-child(4) { animation-delay: 0.25s; }
.activity-item:nth-child(5) { animation-delay: 0.3s; }

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Glowing effect on hover */
.stat-card.primary:hover {
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
}

.stat-card.success:hover {
    box-shadow: 0 8px 32px rgba(40, 167, 69, 0.3);
}

.stat-card.warning:hover {
    box-shadow: 0 8px 32px rgba(255, 193, 7, 0.3);
}

.stat-card.info:hover {
    box-shadow: 0 8px 32px rgba(23, 162, 184, 0.3);
}
/* 🎨 MODERN DASHBOARD CARDS */
.stats-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.stat-card-modern {
    position: relative;
    padding: 28px;
    border-radius: 20px;
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(0,0,0,0.05);
}

.stat-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, transparent, currentColor, transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card-modern:hover::before {
    opacity: 1;
}

.stat-card-modern:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 48px rgba(0,0,0,0.15);
}

.stat-card-modern.primary { color: #667eea; }
.stat-card-modern.success { color: #28a745; }
.stat-card-modern.warning { color: #ffc107; }
.stat-card-modern.info { color: #17a2b8; }

.stat-glow {
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, currentColor 0%, transparent 60%);
    opacity: 0.03;
    pointer-events: none;
}

.stat-content-modern {
    position: relative;
    z-index: 1;
}

.stat-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.stat-icon-modern {
    font-size: 40px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

.stat-change {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
}

.stat-change.positive {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.stat-change.neutral {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

.stat-badge-pulse {
    background: #dc3545;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
}

.stat-value-modern {
    font-size: 48px;
    font-weight: 900;
    color: #2c3e50;
    line-height: 1;
    margin-bottom: 8px;
}

.stat-label-modern {
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 12px;
}

.stat-breakdown-modern {
    font-size: 13px;
    color: #6c757d;
    padding-top: 12px;
    border-top: 1px solid #f1f3f5;
}

.stat-breakdown-modern .separator {
    margin: 0 8px;
    opacity: 0.5;
}

.stat-action-modern {
    display: inline-block;
    margin-top: 12px;
    color: currentColor;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    transition: transform 0.2s ease;
}

.stat-action-modern:hover {
    transform: translateX(4px);
}
</style>

<div class="page-header">
    <h1 class="page-title">Dashboard Overview</h1>
    <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($admin_fname); ?>! Here's what's happening today.</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid-modern">
    <div class="stat-card-modern primary">
        <div class="stat-glow"></div>
        <div class="stat-content-modern">
            <div class="stat-header-modern">
                <span class="stat-icon-modern">👥</span>
                <div class="stat-change positive">+12%</div>
            </div>
            <div class="stat-value-modern">
                <?php echo number_format($stats['total_clients'] + $stats['total_providers']); ?>
            </div>
            <div class="stat-label-modern">Total Users</div>
            <div class="stat-breakdown-modern">
                <span><?php echo number_format($stats['total_clients']); ?> Clients</span>
                <span class="separator">•</span>
                <span><?php echo number_format($stats['total_providers']); ?> Providers</span>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern success">
        <div class="stat-glow"></div>
        <div class="stat-content-modern">
            <div class="stat-header-modern">
                <span class="stat-icon-modern">🛠️</span>
                <div class="stat-change positive">+8%</div>
            </div>
            <div class="stat-value-modern"><?php echo number_format($stats['total_services']); ?></div>
            <div class="stat-label-modern">Active Services</div>
            <div class="stat-breakdown-modern">Approved & Live</div>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-glow"></div>
        <div class="stat-content-modern">
            <div class="stat-header-modern">
                <span class="stat-icon-modern">⏳</span>
                <?php if ($stats['pending_services'] > 0): ?>
                <div class="stat-badge-pulse"><?php echo $stats['pending_services']; ?></div>
                <?php endif; ?>
            </div>
            <div class="stat-value-modern"><?php echo number_format($stats['pending_services']); ?></div>
            <div class="stat-label-modern">Pending Approvals</div>
            <a href="approvals.php" class="stat-action-modern">Review Now →</a>
        </div>
    </div>
    
    <div class="stat-card-modern info">
        <div class="stat-glow"></div>
        <div class="stat-content-modern">
            <div class="stat-header-modern">
                <span class="stat-icon-modern">📋</span>
                <div class="stat-change neutral">—</div>
            </div>
            <div class="stat-value-modern"><?php echo number_format($stats['active_requests']); ?></div>
            <div class="stat-label-modern">Active Requests</div>
            <div class="stat-breakdown-modern">
                <?php echo number_format($stats['completed_requests']); ?> Completed
            </div>
        </div>
    </div>
</div>
    
   

<!-- Charts and Pending Approvals Grid -->
<div class="dashboard-grid">
    <!-- Left Column: Charts -->
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-title">
                <span>📈 User Growth</span>
            </div>
            <div class="chart-container">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>📊 Booking Trends</span>
            </div>
            <div class="chart-container">
                <canvas id="bookingTrendsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Pending Approvals & Activity -->
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-title">
                <span>⏳ Pending Approvals</span>
                <a href="approvals.php" class="view-all-link">View All →</a>
            </div>
            
            <?php if ($pending_skills && $pending_skills->num_rows > 0): ?>
                <?php while ($skill = $pending_skills->fetch_assoc()): ?>
                    <div class="pending-skill-item">
                        <div class="skill-header">
                            <span class="skill-category"><?php echo htmlspecialchars($skill['CategoryName']); ?></span>
                            <span class="skill-rate">
                                ₱<?php echo number_format($skill['Rate'], 0); ?><?php 
                                    echo $skill['RateType'] === 'daily' ? '/day' : 
                                        ($skill['RateType'] === 'fixed' ? '' : '/hr'); 
                                ?>
                            </span>
                        </div>
                        <div class="skill-provider">
                            By <?php echo htmlspecialchars($skill['FName'] . ' ' . $skill['LName']); ?>
                        </div>
                        <div class="skill-description">
                            <?php echo htmlspecialchars($skill['Description']); ?>
                        </div>
                        <div class="skill-actions">
                            <a href="approvals.php?skill=<?php echo $skill['SkillID']; ?>" class="btn-sm btn-view">
                                Review
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <p>No pending approvals!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>📜 Recent Activity</span>
                <a href="activity_log.php" class="view-all-link">View All →</a>
            </div>
            
            <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $activity['Action']; ?>">
                            <?php
                                $icons = [
                                    'login' => '🔓',
                                    'logout' => '🚪',
                                    'approve' => '✅',
                                    'reject' => '❌',
                                    'suspend' => '⚠️',
                                    'activate' => '✅'
                                ];
                                echo $icons[$activity['Action']] ?? '📝';
                            ?>
                        </div>
                        <div class="activity-details">
                            <div class="activity-action">
                                <?php echo ucfirst($activity['Action']); ?> 
                                <?php echo $activity['TargetType']; ?>
                            </div>
                            <div class="activity-meta">
                                <?php echo htmlspecialchars($activity['FName'] ?? 'System'); ?> • 
                                <?php echo date('M j, g:i A', strtotime($activity['CreatedAt'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <p>No activity yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// User Growth Chart
const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
new Chart(userGrowthCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($growth_data, 'month')); ?>,
        datasets: [
            {
                label: 'Clients',
                data: <?php echo json_encode(array_column($growth_data, 'clients')); ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Providers',
                data: <?php echo json_encode(array_column($growth_data, 'providers')); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Booking Trends Chart
const bookingTrendsCtx = document.getElementById('bookingTrendsChart').getContext('2d');
new Chart(bookingTrendsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($trends_data, 'month')); ?>,
        datasets: [
            {
                label: 'Total Bookings',
                data: <?php echo json_encode(array_column($trends_data, 'total')); ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
            },
            {
                label: 'Completed',
                data: <?php echo json_encode(array_column($trends_data, 'completed')); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
            },
            {
                label: 'Cancelled',
                data: <?php echo json_encode(array_column($trends_data, 'cancelled')); ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.8)',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>