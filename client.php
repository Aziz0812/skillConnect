<?php
session_start();
require "db.php";

// ✅ FIX: Only inject JS for non-AJAX page loads (prevents JSON corruption)
if (!isset($_GET['ajax']) || $_GET['ajax'] !== '1') {
    // Inject session data for JavaScript
    echo '<script>';
    echo 'window.USER_ID = ' . (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null') . ';';
    echo 'window.USER_ROLE = "' . ($_SESSION['role'] ?? '') . '";';
    echo '</script>';
}



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
                    WHERE u.Role = 'provider' AND s.IsActive = 1 AND s.ApprovalStatus = 'approved'
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

    // Resolve provider from skill
    $stmt = $conn->prepare("SELECT UserID FROM skills WHERE SkillID = ?");
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $skill = $res->fetch_assoc();

    if ($skill) {
        $provider_id = (int)$skill['UserID'];

        // Server-side availability enforcement
        // Compute weekday name and HH:MM from preferred_schedule
        $weekday = date('l', $scheduleTime); // Monday..Sunday
        $hhmm = date('H:i', $scheduleTime);

        // Fetch availability slots for this provider and weekday
        $av = $conn->prepare("SELECT StartTime, EndTime FROM provider_availability WHERE ProviderID = ? AND DayOfWeek = ?");
        $av->bind_param("is", $provider_id, $weekday);
        $av->execute();
        $avRes = $av->get_result();

        $allowed = false;
        while ($row = $avRes->fetch_assoc()) {
            // Compare times as strings HH:MM which works lexicographically
            $start = substr($row['StartTime'], 0, 5); // HH:MM
            $end = substr($row['EndTime'], 0, 5);     // HH:MM
            if ($hhmm >= $start && $hhmm <= $end) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            header("Location: client.php?error=unavailable_slot&section=browse");
            exit();
        }

        $status = "Pending";
        $insert = $conn->prepare("INSERT INTO request (ClientID, ProviderID, SkillID, Status, Schedule, CreatedAt) VALUES (?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("iiiss", $client_id, $provider_id, $skill_id, $status, $preferred_schedule);
        
        if ($insert->execute()) {
            header("Location: client.php?success=1&section=requestSection");
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
            WHERE u.Role = 'provider' AND s.ApprovalStatus = 'approved'
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
        
        // Active requests: Cancel + Message
        if ($type === 'active') {
            if ($canCancel) {
                $html .= '<button class="btn-secondary cancel-request-btn" data-request-id="' . (int)$r['RequestID'] . '">Cancel Request</button>';
            }
            $html .= '<button class="btn-primary" onclick="startMessageFromRequest(' . (int)$r['RequestID'] . ')"> Message Provider</button>';
        }
        
        // Completed requests: Book Again
        if ($type === 'completed') {
            $html .= '<button class="btn-success book-again-simple-btn" data-skill-id="' . (int)($r['SkillID'] ?? 0) . '" data-provider="' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '">📅 Book Again</button>';
        }

        // Cancelled requests: Book Again only
        if ($type === 'cancelled') {
            $html .= '<button class="btn-success book-again-simple-btn" data-skill-id="' . (int)($r['SkillID'] ?? 0) . '" data-provider="' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '">📅 Book Again</button>';
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
  <link rel="stylesheet" href="styles/messaging.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <script src="js/vendor/chart.umd.min.js"></script>
  <link rel="stylesheet" href="styles/vendor/flatpickr.min.css">
  <script src="js/vendor/flatpickr.min.js"></script>
<link rel="stylesheet" href="styles/vendor/flatpickr.min.css">
</head>
<body>

        <header class="top-nav">
            <div class="logo"><img src="imge/logo-.png" alt="">SkillConnect</div>
            <nav class="nav-links">
                <a href="#" id="dashboardLink" class="active"><span>Dashboard</span></a>
                <a href="#" id="browseLink"><span>Browse Services</span></a>
                <a href="#" id="requestsLink"><span>My Requests</span></a>

                    <a href="#" id="messagesLink" style="position:relative;">
                     Messages
                    <span class="message-badge" id="messageBadge" style="display:none;">0</span>
                </a>
            </nav>
        <?php
        // Fetch full user data for profile
        $user_query = "SELECT FName, LName, Email, Avatar, ProfilePhoto FROM users WHERE ID = ?";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param("i", $client_id);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();

        $user_initials = strtoupper(substr($user_data['FName'], 0, 1) . substr($user_data['LName'], 0, 1));
        $profile_photo = $user_data['ProfilePhoto'] ?? $user_data['Avatar'] ?? null;
        ?>

        <div class="profile-dropdown-container">
        <button class="profile-trigger" id="profileTrigger">
            <?php if ($profile_photo && file_exists($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" class="profile-avatar">
            <?php else: ?>
            <div class="profile-avatar-initials"><?= $user_initials ?></div>
            <?php endif; ?>
            <span class="profile-name">Hi, <?= htmlspecialchars($user_data['FName']) ?></span>
            <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none">
            <path d="M2 4L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
        
        <div class="profile-dropdown-menu" id="profileDropdown">
            <div class="dropdown-header">
            <?php if ($profile_photo && file_exists($profile_photo)): ?>
                <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" class="dropdown-avatar">
            <?php else: ?>
                <div class="dropdown-avatar-initials"><?= $user_initials ?></div>
            <?php endif; ?>
            <div class="dropdown-user-info">
                <h4><?= htmlspecialchars($user_data['FName'] . ' ' . $user_data['LName']) ?></h4>
                <p><?= htmlspecialchars($user_data['Email']) ?></p>
            </div>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <a href="#" class="dropdown-item" onclick="openModal('profileModal'); return false;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 8C9.65685 8 11 6.65685 11 5C11 3.34315 9.65685 2 8 2C6.34315 2 5 3.34315 5 5C5 6.65685 6.34315 8 8 8Z" stroke="currentColor" stroke-width="1.5"/>
                <path d="M3 14C3 11.7909 5.23858 10 8 10C10.7614 10 13 11.7909 13 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Edit Profile
            </a>
            
            <a href="#" class="dropdown-item" onclick="openModal('photoModal'); return false;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <rect x="2" y="4" width="12" height="10" rx="1" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="8" cy="9" r="2" stroke="currentColor" stroke-width="1.5"/>
                <path d="M6 4L7 2H9L10 4" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            Change Photo
            </a>
            
            <div class="dropdown-divider"></div>
            
            <a href="logout.php" class="dropdown-item logout-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M11 11L14 8L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Logout
            </a>
        </div>
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
                    // ✅ Get all requests and manually determine latest activity
                    $activity_query = "
                        SELECT 
                            r.requestID,
                            r.Status, 
                            r.CreatedAt,
                            r.ConfirmedAt,
                            r.Schedule,
                            COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
                            u.FName, 
                            u.LName
                        FROM request r
                        JOIN skills s ON r.SkillID = s.SkillID
                        JOIN users u ON r.ProviderID = u.ID
                        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                        WHERE r.ClientID = ?
                        ORDER BY r.CreatedAt DESC
                        LIMIT 20
                    ";
                    $stmt = $conn->prepare($activity_query);
                    $stmt->bind_param("i", $client_id);
                    $stmt->execute();
                    $all_activities = $stmt->get_result();
                    
                    // Build array with estimated activity times
                    $activities_with_time = [];
                    while($act = $all_activities->fetch_assoc()) {
                        // Estimate activity time based on status
                        if ($act['Status'] === 'Confirmed' && !empty($act['ConfirmedAt'])) {
                            $act['ActivityTime'] = $act['ConfirmedAt'];
                        } else {
                            // For cancelled/completed, use CreatedAt as fallback
                            // In reality, this just happened, so show as recent
                            $act['ActivityTime'] = $act['CreatedAt'];
                        }
                        $activities_with_time[] = $act;
                    }
                    
                    // Sort by activity time
                    usort($activities_with_time, function($a, $b) {
                        return strtotime($b['ActivityTime']) - strtotime($a['ActivityTime']);
                    });
                    
                    // Take top 5
                    $activities_with_time = array_slice($activities_with_time, 0, 5);
                    
                    if (count($activities_with_time) > 0):
                        foreach($activities_with_time as $act):
                            $activityTime = strtotime($act['ActivityTime']);
                            $currentTime = time();
                            $timeAgo = $currentTime - $activityTime;
                            
                            // Format time ago
                            if ($timeAgo < 60) {
                                $timeText = 'Just now';
                            } elseif ($timeAgo < 3600) {
                                $minutes = floor($timeAgo / 60);
                                $timeText = $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
                            } elseif ($timeAgo < 86400) {
                                $hours = floor($timeAgo / 3600);
                                $timeText = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                            } elseif ($timeAgo < 604800) {
                                $days = floor($timeAgo / 86400);
                                $timeText = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                            } else {
                                $timeText = date('M j, Y', $activityTime);
                            }
                            
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
                        endforeach;
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
                                <input type="hidden" name="provider_id" value="<?= (int)($p['UserID'] ?? 0); ?>">
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

<!-- Profile Edit Modal -->
<div id="profileModal" class="modal profile-modal" role="dialog" aria-hidden="true">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('profileModal')">&times;</span>
        
        <div class="modal-header-custom">
            <h2>Edit Profile</h2>
            <p>Update your personal information</p>
        </div>
        
        <div class="profile-tabs">
            <button class="profile-tab active" data-tab="basic">Basic Info</button>
            <button class="profile-tab" data-tab="address">Address</button>
            <button class="profile-tab" data-tab="security">Security</button>
        </div>
        
        <div class="profile-tab-content active" id="profileTab-basic">
            <form id="basicInfoForm" class="profile-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="fname">First Name</label>
                        <input type="text" id="fname" name="fname" required>
                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" id="lname" name="lname" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="mname">Middle Name (Optional)</label>
                        <input type="text" id="mname" name="mname">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob">
                    </div>
                </div>
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('profileModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
        
        <div class="profile-tab-content" id="profileTab-address">
            <form id="addressForm" class="profile-form">
                <div class="form-group">
                    <label for="location">Street Address</label>
                    <input type="text" id="location" name="location">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <input type="text" id="barangay" name="barangay">
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city">
                    </div>
                </div>
                <div class="form-group">
                    <label for="province">Province</label>
                    <input type="text" id="province" name="province">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('profileModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
        
        <div class="profile-tab-content" id="profileTab-security">
            <div class="security-notice">
                <svg width="20" height="20" fill="#007bff"><path d="M10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8zm1-13H9v2h2zm0 4H9v4h2z"/></svg>
                <p>For security, you'll need to verify your current password to make changes.</p>
            </div>
            <form id="passwordForm" class="profile-form">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('profileModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo Upload Modal -->
<div id="photoModal" class="modal photo-modal" role="dialog" aria-hidden="true">
    <div class="modal-content photo-modal-content">
        <span class="close-btn" onclick="closeModal('photoModal')">&times;</span>
        
        <div class="modal-header-custom">
            <h2>Change Profile Photo</h2>
            <p>Upload a new photo or remove current one</p>
        </div>
        
        <div class="photo-upload-area">
            <div class="photo-preview" id="photoPreview">
                <svg width="48" height="48" fill="#6c757d"><path d="M24 4a20 20 0 100 40 20 20 0 000-40zm0 36a16 16 0 110-32 16 16 0 010 32zm10-16a2 2 0 11-4 0 2 2 0 014 0zM14 24a2 2 0 11-4 0 2 2 0 014 0zm20 0a2 2 0 11-4 0 2 2 0 014 0zM24 14a2 2 0 11-2 2 2 2 0 012-2zm0 20a2 2 0 11-2 2 2 2 0 012-2z"/></svg>
                <p>Click or drag photo here</p>
                <small>JPG, PNG, GIF, WebP • Max 5MB</small>
            </div>
            <input type="file" id="photoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
            
            <div class="form-actions" style="margin-top:20px;">
                <button type="button" class="btn-danger" id="removePhotoBtn">Remove Photo</button>
                <button type="button" class="btn-secondary" onclick="closeModal('photoModal')">Cancel</button>
                <button type="button" class="btn-primary" id="uploadPhotoBtn" disabled>Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Messaging Modal -->
<div id="messagingModal" class="modal messaging-modal" role="dialog" aria-hidden="true">
  <div class="modal-content messaging-modal-content">
    <div class="messaging-container">
      
      <!-- Sidebar -->
      <div class="conversations-sidebar">
        <div class="conversations-header">
          <h3>💬 Messages</h3>
          <button class="close-btn" onclick="closeModal('messagingModal')">&times;</button>
        </div>
        
        <div id="conversationsList" class="conversations-list">
          <div class="loading-state">
            <div class="spinner"></div>
            <p>Loading conversations...</p>
          </div>
        </div>
      </div>
      
      <!-- Chat Window -->
      <div class="chat-window">
        <div id="chatPlaceholder" class="chat-placeholder active">
          <div class="placeholder-content">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <h3>Select a conversation</h3>
            <p>Choose a conversation from the list to start messaging</p>
          </div>
        </div>
        
        <div id="chatContainer" class="chat-container">
          <div class="chat-header">
            <div class="chat-header-info">
              <div class="contact-avatar"></div>
              <div class="contact-details">
                <h4 id="contactName">Loading...</h4>
                <p id="contactService">Service details</p>
              </div>
            </div>
          </div>
          
          <div id="messagesContainer" class="messages-container">
            <!-- Messages will appear here -->
          </div>
          
          <div class="chat-input-container">
            <form id="messageForm" class="message-form">
              <input 
                type="text" 
                id="messageInput" 
                placeholder="Type your message..." 
                autocomplete="off"
                maxlength="1000"
              />
              <button type="submit" class="btn-send" disabled>
                <span>Send</span>
              </button>
            </form>
          </div>
        </div>
      </div>
      
    </div>
  </div>
</div>


<script src="js/vendor/flatpickr.min.js"></script>
<script src="js/vendor/chart.umd.min.js"></script>
<script src="js/client.js"></script>
<script src="js/browse.js"></script>
<script src="js/requestFilters.js"></script>
<script src="js/alerts.js"></script>
<script type="module" src="js/messaging.js"></script>
<script src="js/messaging-integration.js"></script>

<script>
// Open messaging modal
document.addEventListener('DOMContentLoaded', () => {
  const messagesLink = document.getElementById('messagesLink');
  if (messagesLink) {
    messagesLink.addEventListener('click', (e) => {
      e.preventDefault();
      openModal('messagingModal');
      initMessaging();
    });
  }
});
</script>


</body>
</html>