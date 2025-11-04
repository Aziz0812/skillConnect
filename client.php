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
                $params = [];
                $types = '';

                $sql = "
                    SELECT 
                        s.SkillID,
                        COALESCE(sc.CategoryName, s.CustomCategory, 'Uncategorized') AS SkillName,
                        s.Description,
                        s.Rate,
                        u.FName,
                        u.LName,
                        u.Location
                    FROM skills s
                    JOIN users u ON s.UserID = u.ID
                    LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                    WHERE u.Role = 'provider'
                ";

                // 🔍 Search text across name, skill, location
                if ($search !== '') {
                    $sql .= " AND (
                        sc.CategoryName LIKE CONCAT('%', ?, '%') OR
                        s.CustomCategory LIKE CONCAT('%', ?, '%') OR
                        u.FName LIKE CONCAT('%', ?, '%') OR
                        u.LName LIKE CONCAT('%', ?, '%') OR
                        u.Location LIKE CONCAT('%', ?, '%')
                    )";
                    $params = array_merge($params, array_fill(0, 5, $search));
                    $types .= str_repeat('s', 5);
                }

                // 🎯 Optional category filter
                if ($category !== '' && $category !== 'all') {
                    $sql .= " AND (sc.CategoryID = ? OR s.CustomCategory = ?)";
                    $params[] = $category;
                    $params[] = $category;
                    $types .= 'ss';
                }

                $sql .= " ORDER BY SkillName, u.FName";

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
                s.SkillID,
                COALESCE(sc.CategoryName, s.CustomCategory, 'Uncategorized') AS SkillName,
                s.Description,
                s.Rate,
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
        r.Status, 
        r.Schedule, 
        COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
        s.Rate, 
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
            <h2 class="mb-4">Dashboard</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-center shadow-sm border-0 p-3">
                        <h5 class="text-muted">Pending Requests</h5>
                        <h2 id="pendingCount" class="text-warning fw-bold">0</h2>
                    </div>
                        </div>
                        <div class="col-md-4">
                    <div class="card text-center shadow-sm border-0 p-3">
                        <h5 class="text-muted">In Progress</h5>
                            <h2 id="inProgressCount" class="text-info fw-bold">0</h2>
                        </div>
                        </div>
                    <div class="col-md-4">
                    <div class="card text-center shadow-sm border-0 p-3">
                        <h5 class="text-muted">Completed</h5>
                        <h2 id="completedCount" class="text-success fw-bold">0</h2>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Feed -->
    <div class="card mt-4 shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title mb-3">📋 Recent Activity</h5>
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
                <p class="text-muted">No activity yet. Browse services to get started!</p>
            <?php endif; ?>
        </div>
    </div>
</div>


            <div class="card mt-4 shadow-sm border-0">
                <div class="card-body">
                <h5 class="card-title mb-3">Requests Over Time</h5>
                <div class="filter-controls" style="margin-bottom: 1rem;">
                    <label for="dashboardFilter" style="font-weight:600; margin-right:0.5rem;">Filter:</label>
                        <select id="dashboardFilter" style="padding:0.3rem 0.6rem; border-radius:6px; border:1px solid #ccc;">
                            <option value="all" selected>All Time</option>
                            <option value="month">This Month</option>
                            <option value="3months">Last 3 Months</option>
                            <option value="year">This Year</option>
                        </select>
                </div>

                <canvas id="requestsOverTimeChart" height="120"></canvas>
                </div>
            </div>

                <!-- Quick Actions Panel -->
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">⚡ Quick Actions</h5>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="document.getElementById('browseLink').click()">
                        <span class="action-icon">🔍</span>
                        <span class="action-text">Find Services</span>
                    </button>
                    <button class="quick-action-btn" onclick="document.getElementById('requestsLink').click()">
                        <span class="action-icon">📋</span>
                        <span class="action-text">My Requests</span>
                    </button>
                    <button class="quick-action-btn" onclick="alert('Feature coming soon!')">
                        <span class="action-icon">⭐</span>
                        <span class="action-text">Featured Providers</span>
                    </button>
                    <button class="quick-action-btn" onclick="alert('Feature coming soon!')">
                        <span class="action-icon">❓</span>
                        <span class="action-text">Help & Support</span>
                    </button>
                </div>
            </div>
        </div>

                <!-- Provider Spotlight -->
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">⭐ Your Top Providers</h5>
                <div class="provider-spotlight-grid">
                    <?php
                    $spotlight_query = "
                        SELECT u.FName, u.LName, u.Location,
                               COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
                               COUNT(r.RequestID) AS BookingCount,
                               s.Rate
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
                        <div class="spotlight-card">
                            <div class="spotlight-avatar">
                                <?= strtoupper(substr($prov['FName'], 0, 1) . substr($prov['LName'], 0, 1)) ?>
                            </div>
                            <h6><?= htmlspecialchars($prov['FName'].' '.$prov['LName']) ?></h6>
                            <p class="spotlight-skill"><?= htmlspecialchars($prov['SkillName']) ?></p>
                            <p class="spotlight-location">📍 <?= htmlspecialchars($prov['Location']) ?></p>
                            <div class="spotlight-stats">
                                <span class="badge bg-success"><?= $prov['BookingCount'] ?> booking<?= $prov['BookingCount'] > 1 ? 's' : '' ?></span>
                                <span class="spotlight-rate">PHP <?= number_format($prov['Rate'], 0) ?>/hr</span>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <p class="text-muted">Book services to see your favorite providers here!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">📅 Upcoming Appointments</h5>
                <div class="upcoming-list">
                    <?php
                    $upcoming_query = "
                        SELECT r.Schedule, r.Status,
                               COALESCE(sc.CategoryName, s.CustomCategory, 'Service') AS SkillName,
                               u.FName, u.LName, u.Location
                        FROM request r
                        JOIN skills s ON r.SkillID = s.SkillID
                        JOIN users u ON r.ProviderID = u.ID
                        LEFT JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
                        WHERE r.ClientID = ? 
                          AND r.Status IN ('Confirmed', 'Pending', 'In Progress')
                          AND r.Schedule >= NOW()
                        ORDER BY r.Schedule ASC
                        LIMIT 5
                    ";
                    $stmt = $conn->prepare($upcoming_query);
                    $stmt->bind_param("i", $client_id);
                    $stmt->execute();
                    $upcoming = $stmt->get_result();
                    
                    if ($upcoming->num_rows > 0):
                        while($appt = $upcoming->fetch_assoc()):
                            $scheduleDate = date('M d, Y', strtotime($appt['Schedule']));
                            $scheduleTime = date('g:i A', strtotime($appt['Schedule']));
                    ?>
                        <div class="upcoming-item">
                            <div class="upcoming-date">
                                <div class="date-day"><?= date('d', strtotime($appt['Schedule'])) ?></div>
                                <div class="date-month"><?= date('M', strtotime($appt['Schedule'])) ?></div>
                            </div>
                            <div class="upcoming-details">
                                <h6><?= htmlspecialchars($appt['SkillName']) ?></h6>
                                <p>Provider: <?= htmlspecialchars($appt['FName'].' '.$appt['LName']) ?></p>
                                <p class="upcoming-time">🕐 <?= $scheduleTime ?></p>
                            </div>
                            <span class="upcoming-badge badge-<?= strtolower($appt['Status']) ?>">
                                <?= htmlspecialchars($appt['Status']) ?>
                            </span>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <p class="text-muted">No upcoming appointments. Schedule a service to see it here!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
        <div id="browse-filters" style="margin-bottom: 1rem;">
        <input type="text" id="searchBox" placeholder="Search skills, providers, or location" style="padding:5px; width:60%;">
        <select id="categoryFilter" style="padding:5px;">
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
        </div>

            <div class="provider-grid" id="servicesContainer">
        
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

                            <p class="rate-highlight"><strong>Rate:</strong> PHP <?= number_format((float)($p['Rate'] ?? 0), 2); ?>/hour</p>
                        </div>

                        <p class="card-description"><?= htmlspecialchars($p['Description'] ?? ''); ?></p>

                        <div class="card-actions">
                            <button class="btn-secondary read-more-btn" 
                                    data-description="<?= htmlspecialchars($p['Description'] ?? ''); ?>">
                                Read More
                            </button>
                            <form method="POST" class="book-form ajax-book-form" style="display:inline;">
                                <input type="hidden" name="book_skill_id" value="<?= (int)($p['SkillID'] ?? 0); ?>">
                                <input type="datetime-local" name="preferred_schedule" required 
                                   min="<?php echo date('Y-m-d\TH:i'); ?>">
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

        <div class="filter-bar" id="activeFilters" style="display:none;">
            <input type="text" id="requestSearch" placeholder="Search by provider or service..." />
            <select id="statusFilter">
                <option value="all">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Confirmed">Confirmed</option>
                <option value="In Progress">In Progress</option>
            </select>
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

<div id="contactModal" class="modal" role="dialog" aria-hidden="true">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h3>Contact Provider</h3>
    <p id="contactInfo"></p>
    <div class="contact-note">
        <p><strong>Note:</strong> This feature will be enhanced with direct messaging in future updates.</p>
    </div>
  </div>
</div>

<script src="js/client.js"></script>
<script src="js/browse.js"></script>
<script src="js/requestFilters.js"></script>
<script src="js/alerts.js"></script>


</body>
</html>


<?php
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
                <p><strong>Rate:</strong> PHP ' . number_format($r['Rate'], 2) . '/hour</p>
                <p><strong>Schedule:</strong> ' . htmlspecialchars($r['Schedule'], ENT_QUOTES, 'UTF-8') . '</p>
            </div>

            <div class="request-actions">';
        
        if ($canCancel) {
            $html .= '<button class="btn-secondary cancel-request-btn" data-request-id="' . (int)$r['RequestID'] . '">Cancel Request</button>';
        }
        
        $html .= '
                <button class="btn-primary contact-provider-btn" 
                        data-provider="' . htmlspecialchars($r['FName'] . ' ' . $r['LName'], ENT_QUOTES, 'UTF-8') . '"
                        data-service="' . htmlspecialchars($r['SkillName'], ENT_QUOTES, 'UTF-8') . '">
                    Contact Provider
                </button>
            </div>
        </div>';
    }
    
    $html .= '</div>';
    return $html;
}
?>