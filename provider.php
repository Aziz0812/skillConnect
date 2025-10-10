<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require "db.php";

// If user not logged in or not a provider, redirect to login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header("Location: login.php");
    exit();
}

$provider_id = $_SESSION['user_id'];
$provider_name = $_SESSION['name'] ?? 'Provider';

// Grab any session messages (set after redirects) and then clear them
$success_message = $_SESSION['success_message'] ?? "";
$error_message = $_SESSION['error_message'] ?? "";
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Function to get status color
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'pending': return '#ffc107';
        case 'confirmed': return '#17a2b8';
        case 'in progress': return '#007bff';
        case 'completed': return '#28a745';
        case 'cancelled': return '#dc3545';
        default: return '#6c757d';
    }
}

// Redirect helper that respects an anchor/hash
function redirect_with_message($type, $msg, $hash = '#dashboard-section') {
    if ($type === 'success') {
        $_SESSION['success_message'] = $msg;
    } else {
        $_SESSION['error_message'] = $msg;
    }
    // sanitize hash: allow only # followed by letters, numbers, hyphen and underscore
    if (!preg_match('/^#[A-Za-z0-9\-\_]+$/', $hash)) {
        $hash = '#dashboard-section';
    }
    header("Location: provider.php" . $hash);
    exit();
}

// -----------------------------
// ADD NEW SKILL
// -----------------------------
if (isset($_POST['add_skill'])) {
    $return_to = $_POST['return_to'] ?? '#skills-section';
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== 'others' ? intval($_POST['category_id']) : 0;
    $other_category = trim($_POST['other_category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0;

    if (!empty($_POST['category_id']) && $_POST['category_id'] === 'others' && !empty($other_category)) {
        // Add custom category skill
        $stmt = $conn->prepare("INSERT INTO skills (UserID, CategoryID, Description, Rate, CustomCategory) VALUES (?, NULL, ?, ?, ?)");     
        $stmt->bind_param("isds", $provider_id, $description, $rate, $other_category);

        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_message('success', 'Custom skill added successfully!', $return_to);
        } else {
            $err = $conn->error;
            if ($stmt) $stmt->close();
            redirect_with_message('error', "Error adding custom skill: $err", $return_to);
        }
    } elseif ($category_id > 0 && !empty($description) && $rate > 0) {
        // Add standard skill
        $check_stmt = $conn->prepare("SELECT SkillID FROM skills WHERE UserID = ? AND CategoryID = ? AND CustomCategory IS NULL");
        $check_stmt->bind_param("ii", $provider_id, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            redirect_with_message('error', 'You already posted this skill category.', $return_to);
        } else {
            $stmt = $conn->prepare("INSERT INTO skills (UserID, CategoryID, Description, Rate) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisd", $provider_id, $category_id, $description, $rate);
            if ($stmt->execute()) {
                $stmt->close();
                $check_stmt->close();
                redirect_with_message('success', 'Skill added successfully!', $return_to);
            } else {
                $err = $conn->error;
                if ($stmt) $stmt->close();
                if ($check_stmt) $check_stmt->close();
                redirect_with_message('error', "Error adding skill: $err", $return_to);
            }
        }
    } else {
        // missing fields
        redirect_with_message('error', 'Please select a category and fill all fields.', $return_to);
    }
}

// -----------------------------
// UPDATE SKILL
// -----------------------------
if (isset($_POST['edit_skill'])) {
    $return_to = $_POST['return_to'] ?? '#skills-section';
    $skill_id = intval($_POST['skill_id'] ?? 0);
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== 'others' ? intval($_POST['category_id']) : 0;
    $description = trim($_POST['description'] ?? '');
    $rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0;
    $custom_category = trim($_POST['custom_category'] ?? '');

    if (($category_id > 0 || !empty($custom_category)) && !empty($description) && $rate > 0) {
        $check_stmt = $conn->prepare("SELECT SkillID FROM skills WHERE UserID = ? AND (CategoryID = ? OR CustomCategory = ?) AND SkillID != ?");
        $check_stmt->bind_param("iisi", $provider_id, $category_id, $custom_category, $skill_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            redirect_with_message('error', 'You already have this skill.', $return_to);
        } else {
            $stmt = $conn->prepare("UPDATE skills SET CategoryID=?, Description=?, Rate=?, CustomCategory=? WHERE SkillID=? AND UserID=?");
            $stmt->bind_param("isdssi", $category_id, $description, $rate, $custom_category, $skill_id, $provider_id);
            if ($stmt->execute()) {
                $stmt->close();
                $check_stmt->close();
                redirect_with_message('success', 'Skill updated successfully!', $return_to);
            } else {
                $err = $conn->error;
                if ($stmt) $stmt->close();
                if ($check_stmt) $check_stmt->close();
                redirect_with_message('error', "Error updating skill: $err", $return_to);
            }
        }
    } else {
        redirect_with_message('error', 'Please fill all fields.', $return_to);
    }
}

// -----------------------------
// DELETE SKILL
// -----------------------------
if (isset($_POST['delete_skill_id'])) {
    $return_to = $_POST['return_to'] ?? '#skills-section';
    $skill_id = intval($_POST['delete_skill_id']);
    $stmt = $conn->prepare("DELETE FROM skills WHERE SkillID = ? AND UserID = ?");
    $stmt->bind_param("ii", $skill_id, $provider_id);
    if ($stmt->execute()) {
        $stmt->close();
        redirect_with_message('success', 'Skill deleted successfully!', $return_to);
    } else {
        $err = $conn->error;
        if ($stmt) $stmt->close();
        redirect_with_message('error', "Error deleting skill: $err", $return_to);
    }
}

// -----------------------------
// UPDATE JOB STATUS
// -----------------------------
if (isset($_POST['update_status'])) {
    $return_to = $_POST['return_to'] ?? '#jobs-section';
    $request_id = intval($_POST['request_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $valid_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];

    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE request SET Status = ? WHERE RequestID = ? AND ProviderID = ?");
        $stmt->bind_param("sii", $new_status, $request_id, $provider_id);
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_message('success', 'Status updated successfully!', $return_to);
        } else {
            $err = $conn->error;
            if ($stmt) $stmt->close();
            redirect_with_message('error', "Error updating status: $err", $return_to);
        }
    } else {
        redirect_with_message('error', 'Invalid status.', $return_to);
    }
}

// -----------------------------
// GET PROVIDER'S SKILLS
// -----------------------------
$skills_query = "
    SELECT 
        s.SkillID, 
        s.CategoryID, 
        COALESCE(c.CategoryName, s.CustomCategory) AS CategoryName, 
        s.Description, 
        s.Rate 
    FROM skills s 
    LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID 
    WHERE s.UserID = ? 
    ORDER BY s.SkillID DESC
";

$stmt = $conn->prepare($skills_query);
$stmt->bind_param("i", $provider_id);
if ($stmt->execute()) {
    $my_skills = $stmt->get_result();
} else {
    $error_message = "Error fetching skills: " . $conn->error;
    $my_skills = null;
}

// -----------------------------
// GET DASHBOARD COUNTS
// -----------------------------
$requests_query = "
    SELECT r.Status
    FROM request r
    JOIN skills s ON r.SkillID = s.SkillID
    WHERE r.ProviderID = ?
";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $provider_id);
if ($stmt->execute()) {
    $requests_result = $stmt->get_result();
    $total_skills = $my_skills ? $my_skills->num_rows : 0;
    $active_count = 0;
    $completed_count = 0;
    $cancelled_count = 0;
    while ($r = $requests_result->fetch_assoc()) {
        $status = strtolower($r['Status']);
        if ($status === 'completed') $completed_count++;
        elseif ($status === 'cancelled') $cancelled_count++;
        else $active_count++;
    }
} else {
    $error_message = "Error fetching requests: " . $conn->error;
    $requests_result = null;
    $total_skills = 0;
    $active_count = 0;
    $completed_count = 0;
    $cancelled_count = 0;
}

// -----------------------------
// GET JOB REQUESTS
// -----------------------------
$requests_query = "
    SELECT r.RequestID, r.Status, r.Schedule, COALESCE(c.CategoryName, s.CustomCategory) AS SkillName, u.FName, u.LName, u.Location
    FROM request r
    JOIN skills s ON r.SkillID = s.SkillID
    LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
    LEFT JOIN users u ON r.ClientID = u.ID
    WHERE r.ProviderID = ?
    ORDER BY r.RequestID DESC
";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $provider_id);
if ($stmt->execute()) {
    $my_requests = $stmt->get_result();
} else {
    $error_message = "Error fetching job requests: " . $conn->error;
    $my_requests = null;
}

// Client requests with tabs
$active_requests = [];
$completed_requests = [];
$cancelled_requests = [];
if ($my_requests) {
    while ($r = $my_requests->fetch_assoc()) {
        $status = strtolower($r['Status'] ?? '');
        if ($status === 'completed') {
            $completed_requests[] = $r;
        } elseif ($status === 'cancelled') {
            $cancelled_requests[] = $r;
        } else {
            $active_requests[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Provider Dashboard | SkillConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
      /* Smooth slide up for alerts */
      .alert {
        transition: transform 0.45s ease, opacity 0.45s ease, max-height 0.45s ease, margin 0.45s ease, padding 0.45s ease;
        overflow: hidden;
      }
      .alert.hiding {
        transform: translateY(-10px);
        opacity: 0;
        max-height: 0;
        margin: 0;
        padding-top: 0;
        padding-bottom: 0;
      }
    </style>
</head>
<body>
    <header class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="#dashboard-section">
                <img src="imge/logo-.png" alt="" height="35"> SkillConnect
            </a>
            <div class="navbar-nav">
                <a class="nav-link active" href="#dashboard-section" id="dashboard">Dashboard</a>
                <a class="nav-link" href="#add-skill" id="postServiceLink">Post Service</a>
                <a class="nav-link" href="#jobs-section" id="jobsLink">My Jobs</a>
                <a class="nav-link" href="#skills-section" id="skillsLink">My Skills</a>
            </div>
            <div class="d-flex">
                <span class="navbar-text me-3">Hi, <?php echo htmlspecialchars($provider_name); ?></span>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </header>
    <main class="container mt-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="session-success-alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="session-error-alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Dashboard -->
        <section class="dashboard" id="dashboard-section">
            <h2 class="mb-4">Dashboard</h2>
            <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                <div class="col">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Total Skills Posted</h5>
                            <p class="card-text display-6"><?php echo $total_skills; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Active Requests</h5>
                            <p class="card-text display-6"><?php echo $active_count; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Completed Requests</h5>
                            <p class="card-text display-6"><?php echo $completed_count; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Cancelled Requests</h5>
                            <p class="card-text display-6"><?php echo $cancelled_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="#add-skill" class="btn btn-primary">Post New Service</a>
                <a href="#jobs-section" class="btn btn-primary">View Jobs</a>
            </div>
            <canvas id="dashboardChart" class="mt-4" width="400" height="200"></canvas>
        </section>

        <!-- Post Service -->
        <section class="add-skill" id="add-skill" style="display:none;">
            <h2 class="mb-4">Post a New Service</h2>
            <form method="POST" name="add_skill" class="card p-4">
                <div class="mb-3">
                    <label for="category" class="form-label">Service Category</label>
                    <select id="category" name="category_id" class="form-select" required>
                        <option value="" disabled selected>Select a category</option>
                        <?php
                        $categories = $conn->query("SELECT CategoryID, CategoryName FROM skill_categories WHERE IsApproved = 1");
                        if ($categories) {
                            while ($cat = $categories->fetch_assoc()) {
                                echo "<option value='{$cat['CategoryID']}'>" . htmlspecialchars($cat['CategoryName']) . "</option>";
                            }
                        } else {
                            echo "<option value='' disabled>Database error. Contact admin.</option>";
                        }
                        ?>
                        <option value="others">Other (Specify)</option>
                    </select>
                </div>
                <div class="mb-3" id="otherCategoryGroup" style="display:none;">
                    <label for="otherCategory" class="form-label">Specify Other Category</label>
                    <input type="text" id="otherCategory" name="other_category" class="form-control" placeholder="Enter custom category">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" required maxlength="500" placeholder="Describe your service..."></textarea>
                </div>
                <div class="mb-3">
                    <label for="rate" class="form-label">Rate (₱/hour)</label>
                    <input type="number" id="rate" name="rate" class="form-control" min="0" step="0.01" required placeholder="Enter rate (e.g., 500.00)">
                    <small id="ratePreview" class="text-muted mt-1"></small>
                </div>
                <button type="submit" name="add_skill" class="btn btn-primary">✨ Post My Skill</button>
            </form>
        </section>

        <!-- My Skills -->
        <section class="my-skills" id="skills-section" style="display:none;">
            <h2 class="mb-4">My Skills</h2>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php if ($my_skills && $my_skills->num_rows > 0): ?>
                    <?php while ($skill = $my_skills->fetch_assoc()): ?>
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                            <?php 
                                                echo htmlspecialchars(!empty($skill['CategoryName']) ? $skill['CategoryName'] : 'Unnamed Category'); 
                                            ?>
                                     </h5>

                                    <p class="card-text"><strong>Rate:</strong> ₱<?php echo number_format($skill['Rate'] ?? 0, 2); ?>/hour</p>
                                    <p class="card-text"><strong>Description:</strong> <?php echo htmlspecialchars($skill['Description'] ?? 'No description'); ?></p>
                                    <div class="d-flex gap-2">
                                        <form method="POST" class="d-inline-flex flex-column gap-2">
                                            <input type="hidden" name="skill_id" value="<?php echo $skill['SkillID'] ?? 0; ?>">
                                            <select name="category_id" class="form-select" required>
                                                <?php
                                                $categories = $conn->query("SELECT CategoryID, CategoryName FROM skill_categories WHERE IsApproved = 1");
                                                if ($categories) {
                                                    while ($cat = $categories->fetch_assoc()) {
                                                        $selected = ($cat['CategoryID'] == ($skill['CategoryID'] ?? null)) ? 'selected' : '';
                                                        echo "<option value='{$cat['CategoryID']}' $selected>" . htmlspecialchars($cat['CategoryName']) . "</option>";
                                                    }
                                                }
                                                ?>
                                                    <option value="others" <?php echo (empty($skill['CategoryID']) && !empty($skill['CategoryName'])) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($skill['CategoryName'] ?? 'Other'); ?>
                                                    </option>
                                            </select>
                                            <input type="hidden" name="custom_category" value="<?php echo empty($skill['CategoryID']) ? htmlspecialchars($skill['CategoryName'] ?? '') : ''; ?>">


                                            <textarea name="description" class="form-control" required maxlength="500"><?php echo htmlspecialchars($skill['Description'] ?? ''); ?></textarea>
                                            <input type="number" name="rate" class="form-control" value="<?php echo number_format($skill['Rate'] ?? 0, 2); ?>" required min="0" step="0.01">
                                            <button type="submit" name="edit_skill" class="btn btn-warning">Update</button>
                                        </form>

                                        <form method="POST" class="d-inline-flex flex-column gap-2" onsubmit="return confirm('Delete skill?')">
                                            <input type="hidden" name="delete_skill_id" value="<?php echo $skill['SkillID'] ?? 0; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No skills yet. <a href="#" id="addSkillFirst" class="btn btn-link">Add one</a>.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- My Jobs -->
        <section class="my-jobs" id="jobs-section" style="display:none;">
            <h2 class="mb-4">Client Requests</h2>
            <div class="btn-group mb-4" role="group">
                <button class="btn btn-outline-primary active" data-tab="active">Active</button>
                <button class="btn btn-outline-primary" data-tab="completed">Completed</button>
                <button class="btn btn-outline-primary" data-tab="cancelled">Cancelled</button>
            </div>

            <!-- Active Requests -->
            <section id="requestSection-active" class="request-section active">
                <h2 class="mb-4">Active Requests</h2>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php if (is_array($active_requests) && count($active_requests) > 0): ?>
                        <?php foreach ($active_requests as $r): ?>
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title"><?php echo htmlspecialchars($r['SkillName'] ?? 'Unnamed Skill'); ?></h5>
                                            <span class="badge" style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($r['Status'] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                        <p class="card-text"><strong>Client:</strong> <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?></p>
                                        <p class="card-text"><strong>Location:</strong> <?php echo htmlspecialchars($r['Location'] ?? 'Unknown'); ?></p>
                                        <p class="card-text"><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule'] ?? 'Not set'); ?></p>
                                        <div class="mt-3">
                                            <?php if (strtolower($r['Status'] ?? '') === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                                                    <input type="hidden" name="new_status" value="Confirmed">
                                                    <button type="submit" name="update_status" class="btn btn-success me-2">Accept</button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                                                    <input type="hidden" name="new_status" value="Cancelled">
                                                    <button type="submit" name="update_status" class="btn btn-danger">Decline</button>
                                                </form>
                                            <?php elseif (strtolower($r['Status'] ?? '') === 'confirmed'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                                                    <input type="hidden" name="new_status" value="In Progress">
                                                    <button type="submit" name="update_status" class="btn btn-warning">Start</button>
                                                </form>
                                            <?php elseif (strtolower($r['Status'] ?? '') === 'in progress'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                                                    <input type="hidden" name="new_status" value="Completed">
                                                    <button type="submit" name="update_status" class="btn btn-success">Complete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">No active requests.</div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Completed Requests -->
            <section id="requestSection-completed" class="request-section">
                <h2 class="mb-4">Completed Requests</h2>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php if (is_array($completed_requests) && count($completed_requests) > 0): ?>
                        <?php foreach ($completed_requests as $r): ?>
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title"><?php echo htmlspecialchars($r['SkillName'] ?? 'Unnamed Skill'); ?></h5>
                                            <span class="badge" style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($r['Status'] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                        <p class="card-text"><strong>Client:</strong> <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?></p>
                                        <p class="card-text"><strong>Location:</strong> <?php echo htmlspecialchars($r['Location'] ?? 'Unknown'); ?></p>
                                        <p class="card-text"><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule'] ?? 'Not set'); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">No completed requests.</div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Cancelled Requests -->
            <section id="requestSection-cancelled" class="request-section">
                <h2 class="mb-4">Cancelled Requests</h2>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php if (is_array($cancelled_requests) && count($cancelled_requests) > 0): ?>
                        <?php foreach ($cancelled_requests as $r): ?>
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title"><?php echo htmlspecialchars($r['SkillName'] ?? 'Unnamed Skill'); ?></h5>
                                            <span class="badge" style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($r['Status'] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                        <p class="card-text"><strong>Client:</strong> <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?></p>
                                        <p class="card-text"><strong>Location:</strong> <?php echo htmlspecialchars($r['Location'] ?? 'Unknown'); ?></p>
                                        <p class="card-text"><strong>Schedule:</strong> <?php echo htmlspecialchars($r['Schedule'] ?? 'Not set'); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">No cancelled requests.</div>
                    <?php endif; ?>
                </div>
            </section>
        </section>
    </main>

    <!-- Bootstrap JS and Custom JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/provider.js"></script>

    <script>
    // Tabs functionality (keeps original behavior)
    document.querySelectorAll('.btn-outline-primary[data-tab]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.btn-outline-primary[data-tab]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.request-section').forEach(sec => sec.classList.remove('active'));
            document.getElementById('requestSection-' + this.dataset.tab).classList.add('active');
        });
    });

    // Chart (unchanged)
    const ctx = document.getElementById('dashboardChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Active', 'Completed', 'Cancelled'],
            datasets: [{
                label: 'Requests',
                data: [<?php echo $active_count; ?>, <?php echo $completed_count; ?>, <?php echo $cancelled_count; ?>],
                backgroundColor: ['#007bff', '#28a745', '#dc3545'],
                borderColor: ['#0056b3', '#218838', '#b52a37'],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Number of Requests' } }
            }
        }
    });

    // Attach current hash to all POST forms before submit (so server can redirect back to same section)
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.method.toLowerCase() !== 'post') return;
        let input = form.querySelector('input[name="return_to"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'return_to';
            form.appendChild(input);
        }
        // If current location hash is empty on initial load (like after login), keep dashboard default
        input.value = location.hash ? location.hash : '#dashboard-section';
    });

    // Smooth slide-up auto hide for alerts (5s) and respect manual close
    document.addEventListener('DOMContentLoaded', () => {
        const autoHideDelay = 5000; // 5 seconds
        const alerts = document.querySelectorAll('.alert.alert-dismissible');
        alerts.forEach(alert => {
            // If user manually closes, remove immediately and avoid re-show on reload (server side handles it)
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    alert.classList.add('hiding');
                    setTimeout(() => alert.remove(), 450);
                });
            }
            // Auto slide after delay
            setTimeout(() => {
                alert.classList.add('hiding');
                setTimeout(() => alert.remove(), 450);
            }, autoHideDelay);
        });
    });
    </script>
</body>
</html>
