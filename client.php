<?php
session_start();
require "db.php";

        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json; charset=utf-8');
            require_once "db.php";

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

                $dateCondition = "";
                if ($filter === 'month') {
                    $dateCondition = "AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                } elseif ($filter === '3months') {
                    $dateCondition = "AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                } elseif ($filter === 'year') {
                    $dateCondition = "AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                }

                $stmt = $conn->prepare("
                    SELECT DATE_FORMAT(CreatedAt, '%b %Y') AS Month, COUNT(*) AS Count
                    FROM request
                    WHERE ClientID = ? $dateCondition
                    GROUP BY DATE_FORMAT(CreatedAt, '%Y-%m')
                    ORDER BY MIN(CreatedAt)
                ");
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
if (isset($_POST['book_skill_id'])) {
    $skill_id = intval($_POST['book_skill_id']);
    $preferred_schedule = $_POST['preferred_schedule'] ?? 'To be scheduled';
    $stmt = $conn->prepare("SELECT UserID FROM skills WHERE SkillID = ?");
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $skill = $res->fetch_assoc();

    if ($skill) {
        $provider_id = $skill['UserID'];
        $status = "Pending";
        $schedule = $preferred_schedule;

        $insert = $conn->prepare("INSERT INTO request (ClientID, ProviderID, SkillID, Status, Schedule) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iiiss", $client_id, $provider_id, $skill_id, $status, $schedule);
        $insert->execute();

        // 🚀 Redirect to avoid resubmission and go to requests tab
        header("Location: client.php?success=1&section=request");
        exit();
    }
}

// Providers & skills
$query = "
    SELECT s.SkillID, sc.CategoryName AS SkillName, s.Description, s.Rate, u.FName, u.LName, u.Location
    FROM skills s
    JOIN users u ON s.UserID = u.ID
    JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
    WHERE u.Role = 'provider'
    ORDER BY sc.CategoryName, u.FName
";
$providers = $conn->query($query);

// Client requests with enhanced data
// Client requests
$requests_query = "
    SELECT r.RequestID, r.Status, r.Schedule, sc.CategoryName AS SkillName, s.Rate, u.FName, u.LName, u.Location, r.ConfirmedAt
    FROM request r
    JOIN skills s ON r.SkillID = s.SkillID
    JOIN users u ON r.ProviderID = u.ID
    JOIN skill_categories sc ON s.CategoryID = sc.CategoryID
    WHERE r.ClientID = ?
    ORDER BY r.RequestID DESC
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
        </section>


        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-message">✅ Service booked successfully!</div>
        <?php endif; ?>



    <!-- Browse Providers -->
    <section id="browseSection">
        <h2>Browse Services</h2>
        <div class="provider-grid">
            <?php while($p = $providers->fetch_assoc()): ?>
                <div class="provider-card">
                    <div class="provider-header">
                        <h3><?php echo htmlspecialchars($p['FName'] . " " . $p['LName']); ?></h3>
                        <span class="category-badge"><?php echo htmlspecialchars($p['SkillName']); ?></span>
                    </div>
                    
                    <div class="provider-details">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($p['Location']); ?></p>
                        <p class="rate-highlight"><strong>Rate:</strong> PHP <?php echo number_format($p['Rate'], 2); ?>/hour</p>
                    </div>

                    <p class="card-description"><?php echo htmlspecialchars($p['Description']); ?></p>

                    <div class="card-actions">
                        <button class="btn-secondary read-more-btn" 
                                data-description="<?php echo htmlspecialchars($p['Description']); ?>">
                            Read More
                        </button>
                        <form method="POST" class="book-form ajax-book-form" style="display:inline;">
                            <input type="hidden" name="book_skill_id" value="<?php echo $p['SkillID']; ?>">
                            <input type="datetime-local" name="preferred_schedule" required>
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
            <h2>Active Requests</h2>
            <div class="request-grid">
                <?php if (count($active_requests) > 0): ?>
                    <?php foreach($active_requests as $r): ?>
                        <div class="request-card enhanced">
                            <div class="request-header">
                                <h3><?php echo htmlspecialchars($r['SkillName']); ?></h3>
                                <span class="status-badge" style="background-color: <?php echo getStatusColor($r['Status']); ?>">
                                    <?php echo htmlspecialchars($r['Status']); ?>
                            </span>
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         style="width: <?php echo getStatusProgress($r['Status']); ?>%; background-color: <?php echo getStatusColor($r['Status']); ?>">
                                    </div>
                                </div>
                                <span class="progress-text"><?php echo getStatusProgress($r['Status']); ?>% Complete</span>
                            </div>

                            <div class="progress-steps">
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 25 ? 'completed' : ''; ?>">
                                    <div class="step-circle">1</div>
                                    <span>Pending</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 50 ? 'completed' : ''; ?>">
                                    <div class="step-circle">2</div>
                                    <span>Confirmed</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 75 ? 'completed' : ''; ?>">
                                    <div class="step-circle">3</div>
                                    <span>In Progress</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 100 ? 'completed' : ''; ?>">
                                    <div class="step-circle">4</div>
                                    <span>Completed</span>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <p><strong>Provider:</strong> <?php echo htmlspecialchars($r['FName'] . " " . $r['LName']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($r['Location']); ?></p>
                                <p><strong>Rate:</strong> PHP <?php echo number_format($r['Rate'], 2); ?>/hour</p>
                                <p><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule']); ?></p>
                            </div>

                            <div class="request-actions">
                                <?php
                                $can_cancel = false;
                                if (strtolower($r['Status']) === 'pending') {
                                    $can_cancel = true;
                                } elseif (strtolower($r['Status']) === 'confirmed' && !empty($r['ConfirmedAt'])) {
                                    $confirmed_time = strtotime($r['ConfirmedAt']);
                                    if ((time() - $confirmed_time) <= 86400) { // 24 hours
                                        $can_cancel = true;
                                    }
                                }
                                ?>
                                <?php if ($can_cancel): ?>
                                    <button class="btn-secondary cancel-request-btn" data-request-id="<?php echo $r['RequestID']; ?>">Cancel Request</button>
                                <?php endif; ?>
                                <button class="btn-primary contact-provider-btn" 
                                        data-provider="<?php echo htmlspecialchars($r['FName'] . ' ' . $r['LName']); ?>"
                                        data-service="<?php echo htmlspecialchars($r['SkillName']); ?>">
                                    Contact Provider
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No active requests.</div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Completed Requests -->
        <section id="requestSection-completed" class="request-section">
            <h2>Completed Services</h2>
            <div class="request-grid">
                <?php if (count($completed_requests) > 0): ?>
                    <?php foreach($completed_requests as $r): ?>
                        <div class="request-card enhanced">
                            <div class="request-header">
                                <h3><?php echo htmlspecialchars($r['SkillName']); ?></h3>
                                <span class="status-badge" style="background-color: <?php echo getStatusColor($r['Status']); ?>">
                                    <?php echo htmlspecialchars($r['Status']); ?>
                            </span>
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         style="width: <?php echo getStatusProgress($r['Status']); ?>%; background-color: <?php echo getStatusColor($r['Status']); ?>">
                                    </div>
                                </div>
                                <span class="progress-text"><?php echo getStatusProgress($r['Status']); ?>% Complete</span>
                            </div>

                            <div class="progress-steps">
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 25 ? 'completed' : ''; ?>">
                                    <div class="step-circle">1</div>
                                    <span>Pending</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 50 ? 'completed' : ''; ?>">
                                    <div class="step-circle">2</div>
                                    <span>Confirmed</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 75 ? 'completed' : ''; ?>">
                                    <div class="step-circle">3</div>
                                    <span>In Progress</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 100 ? 'completed' : ''; ?>">
                                    <div class="step-circle">4</div>
                                    <span>Completed</span>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <p><strong>Provider:</strong> <?php echo htmlspecialchars($r['FName'] . " " . $r['LName']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($r['Location']); ?></p>
                                <p><strong>Rate:</strong> PHP <?php echo number_format($r['Rate'], 2); ?>/hour</p>
                                <p><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule']); ?></p>
                            </div>

                            <div class="request-actions">
                                <button class="btn-primary contact-provider-btn" 
                                        data-provider="<?php echo htmlspecialchars($r['FName'] . ' ' . $r['LName']); ?>"
                                        data-service="<?php echo htmlspecialchars($r['SkillName']); ?>">
                                    Contact Provider
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No completed services.</div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Cancelled Requests -->
        <section id="requestSection-cancelled" class="request-section">
            <h2>Cancelled Services</h2>
            <div class="request-grid">
                <?php if (count($cancelled_requests) > 0): ?>
                    <?php foreach($cancelled_requests as $r): ?>
                        <div class="request-card enhanced">
                            <div class="request-header">
                                <h3><?php echo htmlspecialchars($r['SkillName']); ?></h3>
                                <span class="status-badge" style="background-color: <?php echo getStatusColor($r['Status']); ?>">
                                    <?php echo htmlspecialchars($r['Status']); ?>
                            </span>
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         style="width: <?php echo getStatusProgress($r['Status']); ?>%; background-color: <?php echo getStatusColor($r['Status']); ?>">
                                    </div>
                                </div>
                                <span class="progress-text"><?php echo getStatusProgress($r['Status']); ?>% Complete</span>
                            </div>

                            <div class="progress-steps">
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 25 ? 'completed' : ''; ?>">
                                    <div class="step-circle">1</div>
                                    <span>Pending</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 50 ? 'completed' : ''; ?>">
                                    <div class="step-circle">2</div>
                                    <span>Confirmed</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 75 ? 'completed' : ''; ?>">
                                    <div class="step-circle">3</div>
                                    <span>In Progress</span>
                                </div>
                                <div class="step <?php echo getStatusProgress($r['Status']) >= 100 ? 'completed' : ''; ?>">
                                    <div class="step-circle">4</div>
                                    <span>Completed</span>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <p><strong>Provider:</strong> <?php echo htmlspecialchars($r['FName'] . " " . $r['LName']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($r['Location']); ?></p>
                                <p><strong>Rate:</strong> PHP <?php echo number_format($r['Rate'], 2); ?>/hour</p>
                                <p><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule']); ?></p>
                            </div>

                            <div class="request-actions">
                                <button class="btn-primary contact-provider-btn" 
                                        data-provider="<?php echo htmlspecialchars($r['FName'] . ' ' . $r['LName']); ?>"
                                        data-service="<?php echo htmlspecialchars($r['SkillName']); ?>">
                                    Contact Provider
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No cancelled services.</div>
                <?php endif; ?>
            </div>
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
<script src="js/requestFilters.js"></script>
<script src="js/alerts.js"></script>


</body>
</html>
