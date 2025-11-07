<?php
session_start();
require "db.php";

        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json; charset=utf-8');

            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
                echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
                exit;
            }

            $clientId = $_SESSION['user_id'];

            // === Dashboard Summary ===
            if (isset($_GET['action']) && $_GET['action'] === 'booking_summary') {
                $stmt = $conn->prepare("
                    SELECT Status, COUNT(*) AS Count
                    FROM request
                    WHERE ClientID = ?
                    GROUP BY Status
                ");
                $stmt->bind_param("i", $clientId);
                $stmt->execute();
                $result = $stmt->get_result();

                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[$row['Status']] = (int)$row['Count'];
                }

                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
            }

           // === Requests Over Time (monthly) ===
            if (isset($_GET['action']) && $_GET['action'] === 'requests_over_time') {
                $filter = $_GET['filter'] ?? 'all';

                // ✅ Whitelist validation - only allow specific values
                $allowedFilters = ['all', 'month', '3months', 'year'];
                if (!in_array($filter, $allowedFilters, true)) {
                    echo json_encode(['ok' => false, 'error' => 'Invalid filter']);
                    exit;
                }

                $dateCondition = "";
                if ($filter === 'month') {
                    $dateCondition = "AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                } elseif ($filter === '3months') {
                    $dateCondition = "AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                } elseif ($filter === 'year') {
                    $dateCondition = "AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                }

               $sql = "
                    SELECT DATE_FORMAT(CreatedAt, '%b %Y') AS Month, COUNT(*) AS Count
                    FROM request
                    WHERE ClientID = ?";

                if ($dateCondition) {
                    $sql .= " " . $dateCondition;
                }

                $sql .= "
                    GROUP BY DATE_FORMAT(CreatedAt, '%Y-%m')
                    ORDER BY MIN(CreatedAt)
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $clientId);
                $stmt->execute();
                $result = $stmt->get_result();

                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = ['Month' => $row['Month'], 'Count' => (int)$row['Count']];
                }

                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
            }

           // ===== START: AJAX Search Skills =====
            if (isset($_GET['action']) && $_GET['action'] === 'search_skills') {
                $search = trim($_GET['q'] ?? '');
                $category = $_GET['category'] ?? '';
                $city = $_GET['city'] ?? '';
                $rateType = $_GET['rate_type'] ?? 'all';
                $rateRange = $_GET['rate_range'] ?? 'all';
                $params = [];
                $types = '';

                $sql = "
                    SELECT 
                        s.SkillID, s.UserID,
                        COALESCE(sc.CategoryName, s.CustomCategory, 'Uncategorized') AS SkillName,
                        s.Description,
                        s.Rate,
                        s.RateType,
                        u.FName,
                        u.LName,
                        u.Location,
                        u.City,
                        u.Province,
                        u.Barangay
                    FROM skills s
                    JOIN users u ON s.UserID = u.ID
                    LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                    WHERE u.Role = 'provider' AND s.IsActive = 1
                ";

                // 🔍 Search text across name, skill, location
                if ($search !== '') {
                    $sql .= " AND (
                        sc.CategoryName LIKE CONCAT('%', ?, '%') OR
                        s.CustomCategory LIKE CONCAT('%', ?, '%') OR
                        u.FName LIKE CONCAT('%', ?, '%') OR
                        u.LName LIKE CONCAT('%', ?, '%') OR
                        u.Location LIKE CONCAT('%', ?, '%') OR
                        u.City LIKE CONCAT('%', ?, '%')
                    )";
                    $params = array_merge($params, array_fill(0, 6, $search));
                    $types .= str_repeat('s', 6);
                }

                // 🎯 Category filter
                if ($category !== '' && $category !== 'all') {
                    $sql .= " AND (sc.CategoryName = ? OR s.CustomCategory = ?)";
                    $params[] = $category;
                    $params[] = $category;
                    $types .= 'ss';
                }

                // 📍 City filter
                if ($city !== '' && $city !== 'all') {
                    $sql .= " AND u.City = ?";
                    $params[] = $city;
                    $types .= 's';
                }

               // === RATE TYPE FILTER ===
                if ($rateType !== 'all') {
                    $sql .= " AND s.RateType = ?";
                    $params[] = $rateType;
                    $types .= 's';
                }

                // === PRICE RANGE FILTER (ALWAYS APPLY IF NOT 'all') ===
                if ($rateRange !== 'all') {
                    $min = 0;
                    $max = PHP_INT_MAX;

                    if ($rateRange === '2000+') {
                        $min = 2000;
                    } else {
                        $rangeParts = explode('-', $rateRange);
                        if (count($rangeParts) === 2) {
                            $min = (float)$rangeParts[0];
                            $max = (float)$rangeParts[1];
                        }
                    }

                    $sql .= " AND s.Rate >= ? AND s.Rate <= ?";
                    $params[] = $min;
                    $params[] = $max;
                    $types .= 'dd';
                }

                $sql .= " ORDER BY s.Rate ASC, SkillName, u.FName LIMIT 50";

                $stmt = $conn->prepare($sql);
                if ($params) $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();

                $skills = [];
                while ($row = $res->fetch_assoc()) {
                    $skills[] = $row;
                }

                echo json_encode(['ok' => true, 'data' => $skills]);
                exit;
            }
            

            // === Cancel Request ===
            if (isset($_GET['action']) && $_GET['action'] === 'cancel_request') {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['ok' => false, 'error' => 'Invalid method']);
                    exit;
                }
                
                $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
                
                if ($requestId <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'Invalid request ID']);
                    exit;
                }

                // Verify ownership
                $stmt = $conn->prepare("SELECT Status, ConfirmedAt FROM request WHERE RequestID = ? AND ClientID = ?");
                $stmt->bind_param("ii", $requestId, $clientId);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();

                if (!$request) {
                    echo json_encode(['ok' => false, 'error' => 'Request not found']);
                    exit;
                }

                // Check if cancellable
                $canCancel = false;
                $status = strtolower($request['Status']);
                
                if ($status === 'pending') {
                    $canCancel = true;
                } elseif ($status === 'confirmed' && !empty($request['ConfirmedAt'])) {
                    $confirmedTime = strtotime($request['ConfirmedAt']);
                    if ((time() - $confirmedTime) <= 86400) { // 24 hours
                        $canCancel = true;
                    }
                }

                if (!$canCancel) {
                    echo json_encode(['ok' => false, 'error' => 'Cannot cancel this request']);
                    exit;
                }

                // Update status
                $stmt = $conn->prepare("UPDATE request SET Status = 'Cancelled' WHERE RequestID = ?");
                $stmt->bind_param("i", $requestId);
                
                if ($stmt->execute()) {
                    echo json_encode(['ok' => true, 'message' => 'Request cancelled successfully']);
                } else {
                    echo json_encode(['ok' => false, 'error' => 'Database error']);
                }
                exit;
            }
            // If no recognized AJAX action
            echo json_encode(['ok' => false, 'error' => 'Invalid action']);
            exit;

        } // ✅ ← THIS closes the AJAX block properly!



        // Redirect if not client
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
            header("Location: login.php");
            exit();
        }

$client_id = $_SESSION['user_id'];
$client_name = $_SESSION['name'] ?? 'Client';

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_skill_id'])) {
    $skill_id = intval($_POST['book_skill_id']);
    $preferred_schedule = trim($_POST['preferred_schedule'] ?? '');

    // Validate inputs
    if ($skill_id <= 0 || empty($preferred_schedule)) {
        header("Location: client.php?error=invalid_booking&section=browse");
        exit();
    }

    // Validate date is not in the past
    $scheduleTime = strtotime($preferred_schedule);
    if ($scheduleTime < time()) {
        header("Location: client.php?error=past_date&section=browse");
        exit();
    }

    $stmt = $conn->prepare("SELECT UserID FROM skills WHERE SkillID = ?");
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $skill = $res->fetch_assoc();

    if ($skill) {
        $provider_id = (int)$skill['UserID'];
        $status = "Pending";

        $insert = $conn->prepare("INSERT INTO request (ClientID, ProviderID, SkillID, Status, Schedule, CreatedAt) VALUES (?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("iiiss", $client_id, $provider_id, $skill_id, $status, $preferred_schedule);
        
        if ($insert->execute()) {
            header("Location: client.php?success=1&section=request");
            exit();
        } else {
            header("Location: client.php?error=booking_failed&section=browse");
            exit();
        }
    } else {
        header("Location: client.php?error=skill_not_found&section=browse");
        exit();
    }
}

    // Providers & skills (including uncategorized / other)
        $query = "
            SELECT 
                s.SkillID, s.UserID,
                COALESCE(sc.CategoryName, s.CustomCategory, 'Uncategorized') AS SkillName,
                s.Description,
                s.Rate,
                s.RateType,
                u.FName,
                u.LName,
                u.Location,
                u.City,
                u.Province,
                u.Barangay
            FROM skills s
            JOIN users u ON s.UserID = u.ID
            LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
            WHERE u.Role = 'provider'
            ORDER BY SkillName, u.FName
            LIMIT 100
        ";
        $providers = $conn->query($query);
        
        if (!$providers) {
            die("Error loading providers: " . $conn->error);
        }



// Client requests with enhanced data
$requests_query = "
    SELECT 
        r.requestID AS RequestID,
        r.ProviderID,
        r.Status, 
        r.Schedule, 
        COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
        s.SkillID,
        s.Rate, 
        s.RateType,
        u.FName, 
        u.LName, 
        u.Location, 
        r.ConfirmedAt,
        r.CreatedAt
    FROM request r
    JOIN skills s ON r.SkillID = s.SkillID
    JOIN users u ON r.ProviderID = u.ID
    LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
    WHERE r.ClientID = ?
    ORDER BY r.CreatedAt DESC
";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$my_requests = $stmt->get_result();

$active_requests = [];
$completed_requests = [];
$cancelled_requests = [];

while ($r = $my_requests->fetch_assoc()) {
    $status = strtolower($r['Status']);
    if ($status === 'completed') {
        $completed_requests[] = $r;
    } elseif ($status === 'cancelled') {
        $cancelled_requests[] = $r;
    } else {
        $active_requests[] = $r;
    }
}

// Progress helpers
function getStatusProgress($status) {
    switch(strtolower($status)) {
        case 'pending': return 25;
        case 'confirmed': return 50;
        case 'in progress': return 75;
        case 'completed': return 100;
        case 'cancelled': return 0;
        default: return 25;
    }
}
    function formatSchedulePH($schedule) {
        if (empty($schedule) || $schedule === 'To be scheduled') {
            return 'To be scheduled';
        }
        
        $timestamp = strtotime($schedule);
        if ($timestamp === false) {
            return $schedule; // Return as-is if invalid
        }
        
        return date('M j, Y g:i A', $timestamp);
    }

function getStatusColor($status) {
    switch(strtolower($status)) {
        case 'pending': return '#ffc107';
        case 'confirmed': return '#17a2b8';
        case 'in progress': return '#007bff';
        case 'completed': return '#28a745';
        case 'cancelled': return '#dc3545';
        default: return '#6c757d';
    }
}

// ============================================
// HELPER FUNCTION: Render Request Cards
// ============================================
function renderRequestCards($requests, $type) {
    if (empty($requests)) {
        $typeLabel = ucfirst($type);
        return '<div class="empty-state">No ' . htmlspecialchars($typeLabel) . ' requests.</div>';
    }

    $html = '<div class="request-grid">';
    
    foreach ($requests as $r) {
        // Determine if cancel button should show
        $canCancel = false;
        
        if ($type === 'active') {
            $status = strtolower($r['Status']);
            if ($status === 'pending') {
                $canCancel = true;
            } elseif ($status === 'confirmed' && !empty($r['ConfirmedAt'])) {
                $confirmedTime = strtotime($r['ConfirmedAt']);
                if ((time() - $confirmedTime) <= 86400) {
                    $canCancel = true;
                }
            }
        }

        // Build card HTML
        $html .= '
        <div class="request-card enhanced">
            <div class="request-header">
                <h3>' . htmlspecialchars($r['SkillName'], ENT_QUOTES, 'UTF-8') . '</h3>
                <span class="status-badge" style="background-color: ' . getStatusColor($r['Status']) . '">
                    ' . htmlspecialchars($r['Status'], ENT_QUOTES, 'UTF-8') . '
                </span>
            </div>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" 
                         style="width: ' . getStatusProgress($r['Status']) . '%; background-color: ' . getStatusColor($r['Status']) . '">
                    </div>
                </div>
                <span class="progress-text">' . getStatusProgress($r['Status']) . '% Complete</span>
            </div>

            <div class="progress-steps">
                <div class="step ' . (getStatusProgress($r['Status']) >= 25 ? 'completed' : '') . '">
                    <div class="step-circle">1</div>
                    <span>Pending</span>
                </div>
                <div class="step ' . (getStatusProgress($r['Status']) >= 50 ? 'completed' : '') . '">
                    <div class="step-circle">2</div>
                    <span>Confirmed</span>
                </div>
                <div class="step ' . (getStatusProgress($r['Status']) >= 75 ? 'completed' : '') . '">
                    <div class="step-circle">3</div>
                    <span>In Progress</span>
                </div>
                <div class="step ' . (getStatusProgress($r['Status']) >= 100 ? 'completed' : '') . '">
                    <div class="step-circle">4</div>
                    <span>Completed</span>
                </div>
            </div>
            
            <div class="request-details">
                <p><strong>Provider:</strong> ' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '</p>
                <p><strong>Location:</strong> ' . htmlspecialchars($r['Location'], ENT_QUOTES, 'UTF-8') . '</p>
                <p><strong>Rate:</strong> PHP ' . number_format($r['Rate'], 2) . 
                    (($r['RateType'] ?? 'hourly') === 'daily' ? '/day' : 
                    (($r['RateType'] ?? 'hourly') === 'fixed' ? ' (fixed)' : '/hour')) . '</p>
                <p><strong>Schedule:</strong> ' . htmlspecialchars(formatSchedulePH($r['Schedule']), ENT_QUOTES, 'UTF-8') . '</p>
            </div>

            <div class="request-actions">';
        
        // Active requests: Cancel + Contact
        if ($type === 'active') {
            if ($canCancel) {
                $html .= '<button class="btn-secondary cancel-request-btn" data-request-id="' . (int)$r['RequestID'] . '">Cancel Request</button>';
            }
            $html .= '
                <button class="btn-primary contact-provider-btn" 
                        data-provider="' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '"
                        data-service="' . htmlspecialchars($r['SkillName'], ENT_QUOTES, 'UTF-8') . '">
                    Contact Provider
                </button>';
        }
        
        // Completed requests: Book Again + Rate
        if ($type === 'completed') {
            $html .= '
                <button class="btn-success book-again-btn" 
                data-skill-id="' . (int)($r['SkillID'] ?? 0) . '"
                data-provider-id="' . (int)($r['ProviderID'] ?? 0) . '"
                data-provider="' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '">
                    📅 Book Again
                </button>
                ';
        }
        
        // Cancelled requests: Book Again only
        if ($type === 'cancelled') {
            $html .= '
                <button class="btn-success book-again-btn" 
                data-skill-id="' . (int)($r['SkillID'] ?? 0) . '"
                data-provider-id="' . (int)($r['ProviderID'] ?? 0) . '"
                data-provider="' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '">
                    📅 Book Again
                </button>';
        }
        
        $html .= '
            </div>
        </div>';
    }
    
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Client Dashboard | SkillConnect</title>
  <link rel="stylesheet" href="styles/client.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>

<header class="top-nav">
    <div class="logo"><img src="imge/logo-.png" alt="">SkillConnect</div>
    <nav class="nav-links">
         <a href="#" id="dashboardLink" class="active">Dashboard</a>
            <a href="#" id="browseLink">Browse Services</a>
            <a href="#" id="requestsLink">My Requests</a>
    </nav>
    <div class="profile-dropdown">
      <span class="user-name">Hi, <?php echo htmlspecialchars($client_name); ?></span>
      <a href="logout.php" style="margin-left:10px; color:red;">Logout</a>
    </div>
</header>

        <main class="dashboard-container">
           <section id="dashboardSection" class="active">
    <h2 class="dashboard-title">Dashboard</h2>

    <!-- 🔥 NEW: 2-Column Grid Container -->
    <div class="dashboard-grid">
        
        <!-- LEFT COLUMN -->
        <div class="dashboard-left">
            
            <!-- Stats Cards Row -->
            <div class="stats-row">
                <div class="stat-card pending">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-content">
                        <h3 id="pendingCount">0</h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                
                <div class="stat-card progress">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-content">
                        <h3 id="inProgressCount">0</h3>
                        <p>In Progress</p>
                    </div>
                </div>
                
                <div class="stat-card completed">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <h3 id="completedCount">0</h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>

            <!-- Chart Card -->
            <div class="card chart-card">
                <div class="card-header">
                    <h5>📊 Requests Over Time</h5>
                    <select id="dashboardFilter" class="filter-select">
                        <option value="all">All Time</option>
                        <option value="month">This Month</option>
                        <option value="3months">Last 3 Months</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <canvas id="requestsOverTimeChart" height="100"></canvas>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5>⚡ Quick Actions</h5>
                </div>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn primary" onclick="document.getElementById('browseLink').click()">
                        <span class="action-icon">🔍</span>
                        <span class="action-text">Find Services</span>
                    </button>
                    <button class="quick-action-btn secondary" onclick="document.getElementById('requestsLink').click()">
                        <span class="action-icon">📋</span>
                        <span class="action-text">My Requests</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="dashboard-right">
            
            <!-- Recent Activity -->
            <div class="card activity-card">
                <div class="card-header">
                    <h5>📋 Recent Activity</h5>
                </div>
                <div class="activity-feed">
                    <?php
                    $activity_query = "
                        SELECT r.Status, r.CreatedAt, r.Schedule,
                               COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
                               u.FName, u.LName
                        FROM request r
                        JOIN skills s ON r.SkillID = s.SkillID
                        JOIN users u ON r.ProviderID = u.ID
                        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                        WHERE r.ClientID = ?
                        ORDER BY r.CreatedAt DESC
                        LIMIT 5
                    ";
                    $stmt = $conn->prepare($activity_query);
                    $stmt->bind_param("i", $client_id);
                    $stmt->execute();
                    $activities = $stmt->get_result();
                    
                    if ($activities->num_rows > 0):
                        while($act = $activities->fetch_assoc()):
                            $timeAgo = time() - strtotime($act['CreatedAt']);
                            $timeText = $timeAgo < 3600 ? floor($timeAgo/60).' min ago' : 
                                       ($timeAgo < 86400 ? floor($timeAgo/3600).' hours ago' : 
                                       floor($timeAgo/86400).' days ago');
                            
                            $icon = match(strtolower($act['Status'])) {
                                'pending' => '⏳',
                                'confirmed' => '✅',
                                'in progress' => '🔄',
                                'completed' => '✔️',
                                'cancelled' => '❌',
                                default => '📌'
                            };
                    ?>
                        <div class="activity-item">
                            <span class="activity-icon"><?= $icon ?></span>
                            <div class="activity-details">
                                <p class="activity-text">
                                    <strong><?= htmlspecialchars($act['SkillName']) ?></strong> 
                                    with <?= htmlspecialchars($act['FName'].' '.$act['LName']) ?>
                                </p>
                                <small class="activity-time"><?= $timeText ?> • <?= htmlspecialchars($act['Status']) ?></small>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="empty-state-mini">
                            <p>No activity yet</p>
                            <small>Browse services to get started!</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <h5>📅 Upcoming Appointments</h5>
                </div>
                <div class="upcoming-list">
                    <?php
                    $upcoming_query = "
                        SELECT r.Schedule, r.Status,
                               COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
                               u.FName, u.LName
                        FROM request r
                        JOIN skills s ON r.SkillID = s.SkillID
                        JOIN users u ON r.ProviderID = u.ID
                        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                        WHERE r.ClientID = ? 
                          AND r.Status IN ('Confirmed', 'Pending', 'In Progress')
                          AND r.Schedule >= NOW()
                        ORDER BY r.Schedule ASC
                        LIMIT 3
                    ";
                    $stmt = $conn->prepare($upcoming_query);
                    $stmt->bind_param("i", $client_id);
                    $stmt->execute();
                    $upcoming = $stmt->get_result();
                    
                    if ($upcoming->num_rows > 0):
                        while($appt = $upcoming->fetch_assoc()):
                    ?>
                        <div class="upcoming-item">
                            <div class="upcoming-date">
                                <div class="date-day"><?= date('d', strtotime($appt['Schedule'])) ?></div>
                                <div class="date-month"><?= date('M', strtotime($appt['Schedule'])) ?></div>
                            </div>
                            <div class="upcoming-details">
                                <h6><?= htmlspecialchars($appt['SkillName']) ?></h6>
                                <p><?= htmlspecialchars($appt['FName'].' '.$appt['LName']) ?></p>
                                <small>🕐 <?= date('g:i A', strtotime($appt['Schedule'])) ?></small>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="empty-state-mini">
                            <p>No upcoming appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Provider Spotlight -->
            <div class="card">
                <div class="card-header">
                    <h5>⭐ Top Providers</h5>
                </div>
                <div class="provider-spotlight-list">
                    <?php
                  $spotlight_query = "
                        SELECT u.ID AS ProviderID,
                            u.FName, u.LName,
                            COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
                            COUNT(r.RequestID) AS BookingCount,
                            MAX(r.CreatedAt) AS LastBooked,
                            s.SkillID,
                            s.Rate,
                            s.RateType
                        FROM request r
                        JOIN skills s ON r.SkillID = s.SkillID
                        JOIN users u ON r.ProviderID = u.ID
                        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                        WHERE r.ClientID = ? AND r.Status IN ('Completed', 'In Progress', 'Confirmed')
                        GROUP BY u.ID
                        ORDER BY BookingCount DESC
                        LIMIT 3
                    ";
                    $stmt = $conn->prepare($spotlight_query);
                    $stmt->bind_param("i", $client_id);
                    $stmt->execute();
                    $spotlight = $stmt->get_result();
                    
                    if ($spotlight->num_rows > 0):
                        while($prov = $spotlight->fetch_assoc()):
                    ?>
                        <div class="provider-mini-enhanced">
                <div class="provider-avatar">
                    <?= strtoupper(substr($prov['FName'], 0, 1) . substr($prov['LName'], 0, 1)) ?>
                </div>
                <div class="provider-info">
                    <h6><?= htmlspecialchars($prov['FName'].' '.$prov['LName']) ?></h6>
                    <p class="provider-skill"><?= htmlspecialchars($prov['SkillName']) ?></p>
                    <div class="provider-meta">
                        <small>📦 <?= $prov['BookingCount'] ?> booking<?= $prov['BookingCount'] > 1 ? 's' : '' ?></small>
                        <small class="last-booked">
                            🕐 
                            <?php
                            $lastBookedTime = strtotime($prov['LastBooked']);
                            $daysSince = floor((time() - $lastBookedTime) / 86400);
                            if ($daysSince === 0) echo 'Today';
                            elseif ($daysSince === 1) echo 'Yesterday';
                            else echo $daysSince . ' days ago';
                            ?>
                        </small>
                    </div>
                    <div class="provider-rate">PHP <?= number_format($prov['Rate'], 0) ?>
                        <?php
                        $rt = strtolower($prov['RateType'] ?? 'hourly');
                        echo $rt === 'daily' ? '/day' : ($rt === 'fixed' ? ' (fixed)' : '/hr');
                        ?>
                    </div>
                </div>
                <div class="provider-actions">
                    <button class="btn-book-again" 
                            onclick="bookAgain(<?= $prov['SkillID'] ?>, '<?= htmlspecialchars($prov['FName'].' '.$prov['LName'], ENT_QUOTES) ?>')">
                        📅 Book Again
                    </button>
                    <button class="btn-view-profile" 
                            onclick="viewProviderProfile(<?= $prov['ProviderID'] ?>, '<?= htmlspecialchars($prov['FName'].' '.$prov['LName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($prov['SkillName'], ENT_QUOTES) ?>', <?= $prov['Rate'] ?>, <?= $prov['BookingCount'] ?>)">
                        👤 Profile
                    </button>
                </div>
            </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="empty-state-mini">
                            <p>No providers yet</p>
                            <small>Book a service to see your top providers!</small>
                        </div>
                    <?php endif; ?>
                </div> <!-- closes provider-spotlight-list -->
            </div> <!-- closes card -->

        </div> <!-- closes dashboard-right -->
    </div> <!-- closes dashboard-grid -->
</section>


      <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="success-message">✅ Service booked successfully!</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                ❌ 
                <?php 
                    $error = $_GET['error'];
                    switch($error) {
                        case 'invalid_booking': echo 'Invalid booking information.'; break;
                        case 'past_date': echo 'Cannot book appointments in the past.'; break;
                        case 'booking_failed': echo 'Booking failed. Please try again.'; break;
                        case 'skill_not_found': echo 'Service not found.'; break;
                        default: echo 'An error occurred. Please try again.';
                    }
                ?>
            </div>
        <?php endif; ?>





    <!-- Browse Providers -->
    <section id="browseSection">
        <h2>Browse Services</h2>

<!-- ===== START: Browse Services Controls ===== -->
        <div id="browse-filters" style="margin-bottom: 1rem; display: flex; gap: 10px; flex-wrap: wrap;">
        <input type="text" id="searchBox" placeholder="Search skills, providers..." style="padding:8px; flex:1; min-width:200px;">
        
        <select id="categoryFilter" style="padding:8px;">
            <option value="all">All Categories</option>
            <?php
            $cats = $conn->query("SELECT DISTINCT CategoryName FROM skill_categories ORDER BY CategoryName");
            if ($cats && $cats->num_rows > 0) {
                while ($c = $cats->fetch_assoc()) {
                    echo '<option value="'.htmlspecialchars($c['CategoryName'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($c['CategoryName'], ENT_QUOTES, 'UTF-8').'</option>';
                }
            }
            ?>
            <option value="Other">Other / Custom</option>
        </select>
        
        <select id="cityFilter" style="padding:8px;">
            <option value="all">All Cities</option>
            <?php
            $cities = $conn->query("SELECT DISTINCT City FROM users WHERE Role = 'provider' AND City IS NOT NULL AND City != '' ORDER BY City");
            if ($cities) {
                while ($city = $cities->fetch_assoc()) {
                    echo '<option value="'.htmlspecialchars($city['City'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($city['City'], ENT_QUOTES, 'UTF-8').'</option>';
                }
            }
            ?>
        </select>
        
       <div class="filter-group">
        <select id="rateTypeFilter">
            <option value="all">Any Rate Type</option>
            <option value="hourly">Hourly</option>
            <option value="daily">Daily</option>
            <option value="fixed">Fixed</option>
        </select>

        <select id="rateRangeFilter">
            <option value="all">Any Price</option>
            <option value="0-100">₱0 - ₱100</option>
            <option value="100-500">₱100 - ₱500</option>
            <option value="500-2000">₱500 - ₱2,000</option>
            <option value="2000+">₱2,000+</option>
        </select>
    </div>
        
        <button id="clearFilters" style="padding:8px 15px; background:#dc3545; color:white; border:none; border-radius:5px; cursor:pointer;">Clear</button>
        </div>

            <div class="provider-grid" id="servicesContainer">
                <?php while($p = $providers->fetch_assoc()): ?>
                    <div class="provider-card"
                        data-city="<?= htmlspecialchars($p['City'] ?? '') ?>"
                        data-province="<?= htmlspecialchars($p['Province'] ?? '') ?>"
                        data-barangay="<?= htmlspecialchars($p['Barangay'] ?? '') ?>">

                        <div class="provider-header">
                            <h3><?= htmlspecialchars(($p['FName'] ?? '') . ' ' . ($p['LName'] ?? '')); ?></h3>
                            <span class="category-badge"><?= htmlspecialchars($p['SkillName'] ?? 'Other'); ?></span>
                        </div>
                        
                        <div class="provider-details">
                            <p><strong>Location:</strong>
                                <?= htmlspecialchars(trim(sprintf('%s%s%s',
                                    ($p['Barangay'] ?? '') ? ($p['Barangay'] . ', ') : '',
                                    ($p['City'] ?? '') ? ($p['City'] . ', ') : '',
                                    ($p['Province'] ?? '') ? $p['Province'] : ''
                                )) ?: ($p['Location'] ?? 'Unknown')); ?>
                            </p>

                            <p class="rate-highlight"><strong>Rate:</strong> 
                                PHP <?= number_format((float)($p['Rate'] ?? 0), 2); ?>
                                <?php
                                $rateType = strtolower($p['RateType'] ?? 'hourly');
                                if ($rateType === 'daily') echo '/day';
                                elseif ($rateType === 'fixed') echo ' (fixed)';
                                else echo '/hour';
                                ?>
                            </p>
                        </div>

                        <p class="card-description"><?= htmlspecialchars($p['Description'] ?? ''); ?></p>

                        <div class="card-actions">
                            <button class="btn-secondary read-more-btn" 
                                    data-description="<?= htmlspecialchars($p['Description'] ?? ''); ?>">
                                Read More
                            </button>
                            <form method="POST" class="book-form ajax-book-form" style="display:inline;">
                                <input type="hidden" name="book_skill_id" value="<?= (int)($p['SkillID'] ?? 0); ?>">
                                <input type="text" class="flatpickr-input" placeholder="Pick date & time" required readonly>
                                <input type="hidden" name="preferred_schedule" value="">
                                <button type="submit" class="btn-primary book-btn">Book Now</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
    </section>

    <!-- My Requests -->
    <section id="requestSection">
        <h2>My Service Requests</h2>

        <div class="filter-bar" id="activeFilters">
            <input type="text" id="requestSearch" placeholder="Search by service..." />

            <!-- Hidden real select -->
            <select id="statusFilter" style="display:none;">
                <option value="all">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Confirmed">Confirmed</option>
                <option value="In Progress">In Progress</option>
            </select>

            <!-- Custom Styled Dropdown -->
            <div class="custom-select-wrapper">
                <div class="custom-select">
                    <div class="custom-select__trigger">
                        <span>All Statuses</span>
                        <div class="arrow"></div>
                    </div>
                    <div class="custom-options">
                        <span class="custom-option" data-value="all">All Statuses</span>
                        <span class="custom-option" data-value="Pending">Pending</span>
                        <span class="custom-option" data-value="Confirmed">Confirmed</span>
                        <span class="custom-option" data-value="In Progress">In Progress</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add above the requests section -->
        <div class="request-tabs">
            <button class="tab-btn active" data-tab="active">Active</button>
            <button class="tab-btn" data-tab="completed">Completed</button>
            <button class="tab-btn" data-tab="cancelled">Cancelled</button>
        </div>

        <!-- Active Requests -->
        <section id="requestSection-active" class="request-section active">
            <?php echo renderRequestCards($active_requests, 'active'); ?>
        </section>

        <!-- Completed Requests -->
        <section id="requestSection-completed" class="request-section">
            <?php echo renderRequestCards($completed_requests, 'completed'); ?>
        </section>

        <!-- Cancelled Requests -->
        <section id="requestSection-cancelled" class="request-section">
            <?php echo renderRequestCards($cancelled_requests, 'cancelled'); ?>
        </section>
    </section>
</main>

<!-- Modals -->
<div id="descModal" class="modal" role="dialog" aria-hidden="true">
  <div class="modal-content">
    <span class="close-btn" id="closeDesc">&times;</span>
    <h3>Service Description</h3>
    <p id="fullDescription"></p>
  </div>
</div>

<!-- Book Again Modal -->
<div id="bookAgainModal" class="modal" role="dialog" aria-hidden="true">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h3 id="bookAgainTitle">Book Service Again</h3>
    <div id="bookAgainContent">
      <form id="bookAgainForm" method="POST" action="client.php">
        <input type="hidden" name="book_skill_id" id="rebookSkillId">
        <input type="hidden" name="preferred_schedule" id="rebookScheduleHidden">
        
        <div class="form-group" style="margin-bottom: 1rem;">
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
            📅 Select New Date & Time
          </label>
          <input type="text" 
                 id="rebookDatePicker" 
                 class="flatpickr-input" 
                 placeholder="Pick date & time" 
                 required 
                 readonly
                 style="width: 100%; padding: 10px; border: 2px solid #007bff; border-radius: 8px; font-size: 1rem;">
        </div>
        
        <div class="modal-actions" style="display: flex; gap: 10px; margin-top: 1.5rem;">
          <button type="button" class="btn-secondary" onclick="closeModal('bookAgainModal')" style="flex: 1;">
            Cancel
          </button>
          <button type="submit" class="btn-primary" style="flex: 1;">
            📅 Confirm Booking
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="js/client.js"></script>
<script src="js/browse.js"></script>
<script src="js/requestFilters.js"></script>
<script src="js/alerts.js"></script>


</body>
</html>