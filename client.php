<?php
session_start();
require "db.php";

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
    $stmt = $conn->prepare("SELECT UserID FROM skills WHERE SkillID = ?");
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $skill = $res->fetch_assoc();

    if ($skill) {
        $provider_id = $skill['UserID'];
        $status = "In Progress";
        $schedule = "To be scheduled";

        $insert = $conn->prepare("INSERT INTO request (ClientID, ProviderID, SkillID, Status, Schedule) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iiiss", $client_id, $provider_id, $skill_id, $status, $schedule);
        $insert->execute();
        $success_message = "Service booked successfully!";
    }
}

// Providers & skills
$query = "
    SELECT s.SkillID, s.SkillName, s.Description, s.Rate, u.FName, u.LName, u.Location
    FROM skills s
    JOIN users u ON s.UserID = u.ID
    WHERE u.Role = 'provider'
";
$providers = $conn->query($query);

// Client requests
$requests_query = "
    SELECT r.RequestID, r.Status, r.Schedule, s.SkillName, u.FName, u.LName
    FROM request r
    JOIN skills s ON r.SkillID = s.SkillID
    JOIN users u ON r.ProviderID = u.ID
    WHERE r.ClientID = ?
";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$my_requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Client Dashboard | SkillConnect</title>
  <link rel="stylesheet" href="styles/client.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<header class="top-nav">
    <div class="logo"><img src="imge/logo-.png" alt="">SkillConnect</div>
    <nav class="nav-links">
        <a href="#" id="browseLink" class="active">Browse Services</a>
        <a href="#" id="requestsLink">My Requests</a>
    </nav>
    <div class="profile-dropdown">
      <span class="user-name">Hi, <?php echo htmlspecialchars($client_name); ?></span>
      <a href="logout.php" style="margin-left:10px; color:red;">Logout</a>
    </div>
</header>

<main class="dashboard-container">

    <?php if (!empty($success_message)): ?>
        <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <!-- Browse Providers -->
    <section id="browseSection" class="active">
        <h2>Browse Services</h2>
        <div class="provider-grid">
            <?php while($p = $providers->fetch_assoc()): ?>
                <div class="provider-card">
                    <h3><?php echo htmlspecialchars($p['FName'] . " " . $p['LName']); ?></h3>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($p['SkillName']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($p['Location']); ?></p>
                    <p><strong>Rate:</strong> PHP <?php echo number_format($p['Rate'], 2); ?></p>

                    <!-- Description with clamp -->
                    <p class="card-description"><?php echo htmlspecialchars($p['Description']); ?></p>

                    <!-- Buttons aligned -->
                    <div class="card-actions">
                        <button class="btn-secondary read-more-btn" 
                                data-description="<?php echo htmlspecialchars($p['Description']); ?>">
                            Read More
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="book_skill_id" value="<?php echo $p['SkillID']; ?>">
                            <button type="submit" class="btn-primary">Book Now</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- My Requests -->
    <section id="requestSection">
        <h2>My Requests</h2>
        <div class="request-grid">
            <?php if ($my_requests->num_rows > 0): ?>
                <?php while($r = $my_requests->fetch_assoc()): ?>
                    <div class="request-card">
                        <h3><?php echo htmlspecialchars($r['SkillName']); ?></h3>
                        <p><strong>Provider:</strong> <?php echo htmlspecialchars($r['FName'] . " " . $r['LName']); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($r['Status']); ?></p>
                        <p><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule']); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No requests yet.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Description Modal -->
<div id="descModal" class="modal" role="dialog" aria-hidden="true">
  <div class="modal-content">
    <span class="close-btn" id="closeDesc">&times;</span>
    <h3>Service Description</h3>
    <p id="fullDescription"></p>
  </div>
</div>

<script src="js/client.js"></script>
</body>
</html>
