<?php
session_start();
require "db.php";

// If user not logged in or not a provider, send to login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header("Location: login.php");
    exit();
}

$provider_id = $_SESSION['user_id'];
$provider_name = $_SESSION['name'] ?? 'Provider';

$success_message = "";
$error_message = "";

    // Function to get status color (same as client.php)
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

// ADD new skill
if (isset($_POST['add_skill'])) {
    $skill_name = trim($_POST['skill_name']);
    $description = trim($_POST['description']);
    $rate = floatval($_POST['rate']);

    // Handle "others"
    if ($skill_name === 'others') {
        $other_category = trim($_POST['other_category'] ?? '');
        if (!empty($other_category)) {
            $skill_name = $other_category;
        } else {
            $error_message = "Please specify the other category.";
            $skill_name = '';
        }
    }

    if (!empty($skill_name) && !empty($description) && $rate > 0) {
        $stmt = $conn->prepare("INSERT INTO skills (UserID, SkillName, Description, Rate) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $provider_id, $skill_name, $description, $rate);
        if ($stmt->execute()) {
            $success_message = "Skill added successfully!";
        } else {
            $error_message = "Error adding skill: " . $stmt->error;
        }
        $stmt->close();
    } else {
        if (empty($error_message)) {
            $error_message = "Please fill all fields with valid data.";
        }
    }
}

// UPDATE skill
if (isset($_POST['edit_skill'])) {
    $skill_id = intval($_POST['skill_id']);
    $skill_name = trim($_POST['skill_name']);
    $description = trim($_POST['description']);
    $rate = floatval($_POST['rate']);

    if (!empty($skill_name) && !empty($description) && $rate > 0) {
        $stmt = $conn->prepare("UPDATE skills SET SkillName=?, Description=?, Rate=? WHERE SkillID=? AND UserID=?");
        $stmt->bind_param("ssdii", $skill_name, $description, $rate, $skill_id, $provider_id);
        if ($stmt->execute()) {
            $success_message = "Skill updated successfully!";
        } else {
            $error_message = "Error updating skill.";
        }
        $stmt->close();
    } else {
        $error_message = "Please fill all fields with valid data.";
    }
}

// DELETE skill
if (isset($_POST['delete_skill_id'])) {
    $skill_id = intval($_POST['delete_skill_id']);
    $stmt = $conn->prepare("DELETE FROM skills WHERE SkillID = ? AND UserID = ?");
    $stmt->bind_param("ii", $skill_id, $provider_id);
    if ($stmt->execute()) {
        $success_message = "Skill deleted successfully!";
    } else {
        $error_message = "Error deleting skill.";
    }
    $stmt->close();
}

// Handle status updates
if (isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['new_status'];
    $valid_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
    
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE request SET Status = ? WHERE RequestID = ? AND ProviderID = ?");
        $stmt->bind_param("sii", $new_status, $request_id, $provider_id);
        if ($stmt->execute()) {
            $success_message = "Status updated successfully!";
        } else {
            $error_message = "Error updating status.";
        }
        $stmt->close();
    }
}

// Get provider's skills
$skills_query = "SELECT SkillID, SkillName, Description, Rate 
                 FROM skills 
                 WHERE UserID = ? 
                 ORDER BY SkillID DESC"; // 👈 ensures newest is first

$stmt = $conn->prepare($skills_query);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$my_skills = $stmt->get_result();

// Get client requests
$requests_query = "
    SELECT r.RequestID, r.Status, r.Schedule, s.SkillName, u.FName, u.LName, u.Location
    FROM request r
    JOIN skills s ON r.SkillID = s.SkillID
    JOIN users u ON r.ClientID = u.ID
    WHERE r.ProviderID = ?
    ORDER BY r.RequestID DESC
";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$my_requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Provider Dashboard | SkillConnect</title>
    <link rel="stylesheet" href="styles/provider.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <header class="top-nav">
        <div class="logo"><img src="imge/logo-.png" alt="">SkillConnect</div>
        <nav class="nav-links">
            <a href="#" id="dashboard">Dashboard</a>
            <a href="#" id="postServiceLink">Post Service</a>
            <a href="#" id="jobsLink">My Jobs</a>
            <a href="#" id="skillsLink">My Skills</a>
        </nav>
        <div class="profile-dropdown">
            <span class="user-name">Hi, <?php echo htmlspecialchars($provider_name); ?></span>
            <a href="logout.php" style="margin-left:10px; color:red;">Logout</a>
        </div>
    </header>

    <!-- Main -->
    <main class="dashboard-container">

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Add Skill -->
        <!-- Add Skill -->
            <section class="add-skill" id="add-skill">
            <h2>🚀 Post a New Service</h2>
            <form method="POST" id="skillForm">
                
                <!-- Category -->
                <div class="form-group category-wrapper">
                <label for="category">Category</label>
                <div class="input-with-icon">
                    <span class="icon">🔧</span>
                    <select id="category" name="skill_name" required>
                    <option value="">-- Select Category --</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Electrical">Electrical</option>
                    <option value="House Cleaning">House Cleaning</option>
                    <option value="Tech Support">Tech Support</option>
                    <option value="General Repairs">General Repairs</option>
                    <option value="Moving Help">Moving Help</option>
                    <option value="Pet Care">Pet Care</option>
                    <option value="Errands">Errands</option>
                    <option value="others">Others</option>
                    </select>
                </div>
                </div>

                <!-- Other category -->
                <div class="form-group" id="otherCategoryGroup" style="display:none;">
                <label for="otherCategory">Specify</label>
                <input type="text" id="otherCategory" name="other_category" placeholder="e.g., Gardening 🌱">
                </div>

                <!-- Description -->
                <div class="form-group">
                <label for="description">Service Description</label>
                <div class="input-with-icon">
                    <span class="icon">📝</span>
                    <textarea id="description" name="description" rows="3" maxlength="250" required></textarea>
                </div>
                <small id="descCounter" class="char-counter">0 / 250</small>
                </div>

                <!-- Rate -->
                <div class="form-group">
                <label for="rate">Hourly Rate (PHP)</label>
                <div class="input-with-icon">
                    <span class="icon">💰</span>
                    <input type="text" id="rate" name="rate" min="0" required>
                </div>
                <small id="ratePreview" class="rate-preview"></small>
                </div>

                <button type="submit" name="add_skill" class="btn-primary">✨ Post My Skill</button>
            </form>
            </section>
           


        <!-- My Skills -->
        <section class="posted-skills" id="skills-section">
            <h2>My Posted Services</h2>
            <div class="skills-container">
                <?php if ($my_skills->num_rows > 0): ?>
                    <?php while($skill = $my_skills->fetch_assoc()): ?>
                        <div class="skill-card">
                            <h3><?php echo htmlspecialchars($skill['SkillName']); ?></h3>
                            <p><strong>Rate:</strong> PHP <?php echo number_format($skill['Rate'], 2); ?>/hour</p>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($skill['Description']); ?></p>
                            <div class="card-actions">
                              <!-- Edit form -->
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="skill_id" value="<?php echo $skill['SkillID']; ?>">
                                    <input type="text" name="skill_name" value="<?php echo htmlspecialchars($skill['SkillName']); ?>" required>
                                    <input type="text" name="description" value="<?php echo htmlspecialchars($skill['Description']); ?>" required>
                                    <input type="text" name="rate" value="<?php echo number_format($skill['Rate'], 2); ?>" required>
                                    <button type="submit" name="edit_skill" class="edit-btn">Update</button>
                                </form>

                                <!-- Delete form -->
                                <form method="POST" style="display:inline;" 
                                    onsubmit="return confirm('Are you sure you want to delete this skill?')">
                                    <input type="hidden" name="delete_skill_id" value="<?php echo $skill['SkillID']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No skills posted yet. <a href="#" id="addSkillFirst">Add your first skill</a>.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- My Jobs -->
        <<!-- My Jobs -->
<section class="my-jobs" id="jobs-section" style="display:none;">
    <h2>Client Requests</h2>
    <div class="jobs-container">
        <?php if ($my_requests->num_rows > 0): ?>
            <?php while($request = $my_requests->fetch_assoc()): ?>
                <div class="job-card">
                    <h3><?php echo htmlspecialchars($request['SkillName']); ?> Request</h3>
                    <p><strong>Client:</strong> <?php echo htmlspecialchars($request['FName'] . ' ' . $request['LName']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($request['Location']); ?></p>
                    <p><strong>Current Status:</strong> 
                        <span class="status-badge" style="background-color: <?php echo getStatusColor($request['Status']); ?>">
                            <?php echo htmlspecialchars($request['Status']); ?>
                        </span>
                    </p>
                    <p><strong>Schedule:</strong> <?php echo htmlspecialchars($request['Schedule']); ?></p>
                    
                    <!-- Status Update Buttons -->
                    <div class="status-actions">
                        <?php if ($request['Status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['RequestID']; ?>">
                                <input type="hidden" name="new_status" value="Confirmed">
                                <button type="submit" name="update_status" class="btn-accept">Accept Request</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['RequestID']; ?>">
                                <input type="hidden" name="new_status" value="Cancelled">
                                <button type="submit" name="update_status" class="btn-reject">Decline</button>
                            </form>
                        <?php elseif ($request['Status'] === 'Confirmed'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['RequestID']; ?>">
                                <input type="hidden" name="new_status" value="In Progress">
                                <button type="submit" name="update_status" class="btn-start">Start Work</button>
                            </form>
                        <?php elseif ($request['Status'] === 'In Progress'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['RequestID']; ?>">
                                <input type="hidden" name="new_status" value="Completed">
                                <button type="submit" name="update_status" class="btn-complete">Mark Complete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No client requests yet.</p>
        <?php endif; ?>
    </div>
</section>

    </main>

    <script src="js/provider.js"></script>
</body>
</html>