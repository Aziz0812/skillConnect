<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require "db.php";

        //  Ensure skill_added goes BEFORE hash section
        function add_query_before_hash($url, $key, $value = '1') {
            $url = trim($url);
            $hash = '';
            $base = $url;

            // Split hash (#) if present
            if (strpos($url, '#') !== false) {
                list($base, $hashPart) = explode('#', $url, 2);
                $hash = '#' . $hashPart;
            }

            //  Ensure base starts correctly
            if ($base === '' || $base === '#') {
                $base = 'provider.php';
            }

            //  Add query param correctly
            $sep = (strpos($base, '?') === false) ? '?' : '&';
            return $base . $sep . $key . '=' . rawurlencode($value) . $hash;
        }

if (isset($_COOKIE['remember_token'])) {
    error_log("Remember cookie found: " . $_COOKIE['remember_token']);
} else {
    error_log("No remember cookie found");
}


        /* ---------------------------
        AJAX / API endpoints for charts & availability
        --------------------------- */
        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json; charset=utf-8');

            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
                echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
                exit;
            }

            $pid = intval($_SESSION['user_id']);
            $action = $_GET['action'] ?? '';

            /* ---------------------------
            (1) LIST AVAILABILITY
            --------------------------- */
            if ($action === 'list_availability') {
                $stmt = $conn->prepare("
                    SELECT AvailabilityID, DayOfWeek, StartTime, EndTime
                    FROM provider_availability
                    WHERE ProviderID = ?
                    ORDER BY FIELD(DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), StartTime
                ");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $res = $stmt->get_result();
                $data = [];
                while ($row = $res->fetch_assoc()) {
                    $data[] = $row;
                }
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
            }

            /* ---------------------------
            (2) ADD AVAILABILITY
            --------------------------- */
            if ($action === 'add_availability' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $day = trim($_POST['day'] ?? '');
                $start = trim($_POST['start_time'] ?? '');
                $end = trim($_POST['end_time'] ?? '');

                // Basic validation
                if (!$day || !$start || !$end) {
                    echo json_encode(['ok' => false, 'error' => 'Missing fields.']);
                    exit;
                }

                if ($start === $end) {
                    echo json_encode(['ok' => false, 'error' => 'Start and end time cannot be the same.']);
                    exit;
                }

                // Prevent duplicates / overlapping
                $check = $conn->prepare("
                    SELECT * FROM provider_availability 
                    WHERE ProviderID = ? 
                    AND DayOfWeek = ?
                    AND (
                        (StartTime = ? AND EndTime = ?) 
                        OR (? < EndTime AND ? > StartTime)
                    )
                ");
                $check->bind_param("isssss", $pid, $day, $start, $end, $start, $end);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;

                if ($exists) {
                    echo json_encode(['ok' => false, 'error' => 'Time overlaps with existing availability.']);
                    exit;
                }

                $stmt = $conn->prepare("
                    INSERT INTO provider_availability (ProviderID, DayOfWeek, StartTime, EndTime)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("isss", $pid, $day, $start, $end);
                $ok = $stmt->execute();

                echo json_encode(['ok' => $ok]);
                exit;
            }

            /* ---------------------------
            (3) DELETE AVAILABILITY
            --------------------------- */
            if ($action === 'delete_availability' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
                    exit;
                }
                $stmt = $conn->prepare("DELETE FROM provider_availability WHERE AvailabilityID = ? AND ProviderID = ?");
                $stmt->bind_param("ii", $id, $pid);
                $ok = $stmt->execute();
                echo json_encode(['ok' => $ok]);
                exit;
            }

           // --- REQUESTS OVER TIME ---
            if ($action === 'requests_over_time') {
                $data = [];
                $stmt = $conn->prepare("
                    SELECT DATE_FORMAT(r.CreatedAt, '%b %Y') AS month, COUNT(*) AS count
                    FROM request r
                    INNER JOIN skills s ON r.SkillID = s.SkillID
                    WHERE s.UserID = ? 
                    GROUP BY month
                    ORDER BY MIN(r.CreatedAt) ASC
                ");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
                $stmt->close();
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
            }
    
           // --- STATUS SUMMARY ---
            if ($action === 'status_summary') {
                $data = [];
                $stmt = $conn->prepare("
                    SELECT r.Status, COUNT(*) AS count
                    FROM request r
                    INNER JOIN skills s ON r.SkillID = s.SkillID
                    WHERE s.UserID = ? 
                    GROUP BY r.Status
                ");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $data[$row['Status']] = (int)$row['count'];
                    }
                }
                $stmt->close();
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
            }
                // --- TOP 3 MOST BOOKED SKILLS ---
            if ($action === 'top_skills') {
                $data = [];
                $stmt = $conn->prepare("
                   SELECT 
                        COALESCE(c.CategoryName, s.CustomCategory) AS SkillName,
                        COUNT(r.RequestID) AS TotalBookings
                        FROM skills s
                        LEFT JOIN request r ON s.SkillID = r.SkillID
                        LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
                        WHERE s.UserID = ? 
                        GROUP BY s.SkillID
                        ORDER BY TotalBookings DESC
                        LIMIT 3

                ");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'SkillName' => $row['SkillName'],
                        'TotalBookings' => (int)$row['TotalBookings']
                    ];
                }

                $stmt->close();
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
            }


            // --- FALLBACK ---
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
            exit;
        } // Ã¢Å“â€¦ closes main ajax block
            error_log("==== COOKIE & SESSION DEBUG ====");
            error_log("COOKIES: " . print_r($_COOKIE, true));
            error_log("SESSION: " . print_r($_SESSION, true));

            // ✅ Persistent login via session or cookie
            if (isset($_COOKIE['remember_token'])) {
                error_log("Remember token found: " . $_COOKIE['remember_token']);
            } else {
                error_log("No remember_token cookie detected");
            }

            // ✅ Persistent login via session or cookie
            if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'provider') {
                // already logged in
            } else {
                // Try to restore from remember_token
                if (isset($_COOKIE['remember_token'])) {
                    $token = $_COOKIE['remember_token'];
                    $stmt = $conn->prepare("SELECT ID, Role, FName, LName, remember_expiry FROM users WHERE remember_token = ? LIMIT 1");
                    $stmt->bind_param("s", $token);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($user = $result->fetch_assoc()) {
                        // Check expiry
                        if ((int)$user['remember_expiry'] > time() && $user['Role'] === 'provider') {
                            // ✅ Restore session
                            $_SESSION['user_id'] = $user['ID'];
                            $_SESSION['role'] = $user['Role'];
                            $_SESSION['FName'] = $user['FName'];
                            $_SESSION['LName'] = $user['LName'];
                            $_SESSION['name'] = $user['FName'] . ' ' . $user['LName'];
                        } else {
                            // ❌ Expired or wrong role
                            setcookie('remember_token', '', time() - 3600, '/');
                            header("Location: login.php");
                            exit();
                        }
                    } else {
                        // ❌ Token not found in DB
                        setcookie('remember_token', '', time() - 3600, '/');
                        header("Location: login.php");
                        exit();
                    }
                    $stmt->close();
                } else {
                    // ❌ No session, no cookie
                    header("Location: login.php");
                    exit();
                }
            }

            // ✅ Continue with provider data
            $provider_id = $_SESSION['user_id'];
            $provider_name = $_SESSION['name'] ?? 'Provider';




           
            if (!empty($_SESSION['success_message'])) {
                echo '<script>window.success_message = ' . json_encode($_SESSION['success_message']) . ';</script>';
                unset($_SESSION['success_message']);
            }
            if (!empty($_SESSION['error_message'])) {
                echo '<script>window.error_message = ' . json_encode($_SESSION['error_message']) . ';</script>';
                unset($_SESSION['error_message']);
            }
           


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

        // ✅ Improved redirect helper — keeps messages + hash + skill_added flag
        function redirect_with_message($type, $msg, $return_to = 'provider.php#dashboard-section') {

            // ✅ Flash message
            if ($type === 'success') {
                $_SESSION['success_message'] = $msg;
            } else {
                $_SESSION['error_message'] = $msg;
            }

            // ✅ Clean invalid spaces
            $return_to = trim($return_to);

            // ✅ If it's missing provider.php, add it (safety)
            if (!str_contains($return_to, 'provider.php')) {
                $return_to = 'provider.php' . ltrim($return_to, '.');
            }

            // ✅ Force proper formatting
            if (!str_starts_with($return_to, 'provider.php')) {
                $return_to = 'provider.php' . ltrim($return_to, '.');
            }

            // ✅ Finally redirect to EXACT URL we sent
            header("Location: $return_to");
            exit();
        }


        // -----------------------------
        // ADD NEW SKILL
        // -----------------------------
        if (isset($_POST['add_skill'])) {
            $return_to = isset($_POST['return_to']) && !empty($_POST['return_to'])
            ? $_POST['return_to']
            : 'provider.php#skills-section';

            $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== 'others' ? intval($_POST['category_id']) : 0;
            $custom_category = trim($_POST['custom_category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0;
            $rate_type = $_POST['rate_type'] ?? 'hourly';

           if ($_POST['category_id'] === 'others' && !empty($custom_category)) {
            // User selected "Other (Specify)" — insert with NULL CategoryID
            $stmt = $conn->prepare("
                INSERT INTO skills (UserID, CustomCategory, Description, Rate, RateType, ImagePath)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issdss", $provider_id, $custom_category, $description, $rate, $rate_type, $image_path);

            if ($stmt->execute()) {
                $stmt->close();
                $redirectUrl = add_query_before_hash($return_to, 'skill_added');
                redirect_with_message('success', 'Skill added successfully!', $redirectUrl);
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
                        $stmt = $conn->prepare("INSERT INTO skills (UserID, CategoryID, Description, Rate, RateType) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("iisds", $provider_id, $category_id, $description, $rate, $rate_type);

                    if ($stmt->execute()) {
                        $stmt->close();
                        $check_stmt->close();
                        $redirectUrl = add_query_before_hash($return_to, 'skill_added');
                        redirect_with_message('success', 'Custom skill added successfully!', $redirectUrl);
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
// UPDATE SKILL (with change detection)
// -----------------------------
if (isset($_POST['edit_skill'])) {
    $return_to = isset($_POST['return_to']) && !empty($_POST['return_to'])
        ? $_POST['return_to']
        : 'provider.php#skills-section';

    $skill_id = intval($_POST['skill_id'] ?? 0);
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== 'others' ? intval($_POST['category_id']) : 0;
    $description = trim($_POST['description'] ?? '');
    $rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0;
    $custom_category = trim($_POST['custom_category'] ?? '');
    $rate_type = $_POST['rate_type'] ?? 'hourly';

    if (empty($custom_category) && !empty($_POST['existing_custom_category'])) {
        $custom_category = trim($_POST['existing_custom_category']);
    }

    if (($category_id > 0 || !empty($custom_category)) && !empty($description) && $rate > 0) {

        // ✅ 1. Fetch old values
        $current_stmt = $conn->prepare("SELECT CategoryID, CustomCategory, Description, Rate, RateType FROM skills WHERE SkillID = ? AND UserID = ?");
        $current_stmt->bind_param("ii", $skill_id, $provider_id);
        $current_stmt->execute();
        $current = $current_stmt->get_result()->fetch_assoc();
        $current_stmt->close();

        if (!$current) {
            redirect_with_message('error', 'Skill not found.', $return_to);
            exit;
        }

        // ✅ 2. Detect if no changes
        $no_changes =
            ($current['CategoryID'] == $category_id) &&
            trim($current['CustomCategory']) == trim($custom_category) &&
            trim($current['Description']) == trim($description) &&
            floatval($current['Rate']) == floatval($rate) &&
            $current['RateType'] == $rate_type;

        if ($no_changes) {
            redirect_with_message('info', 'No changes detected.', $return_to);
            exit;
        }

        // ✅ 3. Check only if category/custom changed
        $categoryChanged = ($current['CategoryID'] != $category_id);
        $customChanged = (trim($current['CustomCategory']) != trim($custom_category));

        if ($categoryChanged || $customChanged) {
            if (!empty($custom_category)) {
                $check_stmt = $conn->prepare("
                    SELECT SkillID FROM skills 
                    WHERE UserID = ? 
                    AND CustomCategory = ? 
                    AND SkillID != ?
                ");
                $check_stmt->bind_param("isi", $provider_id, $custom_category, $skill_id);
            } else {
                $check_stmt = $conn->prepare("
                    SELECT SkillID FROM skills 
                    WHERE UserID = ? 
                    AND CategoryID = ? 
                    AND SkillID != ?
                ");
                $check_stmt->bind_param("iii", $provider_id, $category_id, $skill_id);
            }

            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $check_stmt->close();
                redirect_with_message('error', 'You already have this skill.', $return_to);
            }
            $check_stmt->close();
        }

        // ✅ 4. Update skill
        if (!empty($custom_category)) {
            $stmt = $conn->prepare("
                UPDATE skills 
                SET CategoryID = NULL, Description = ?, Rate = ?, RateType = ?, CustomCategory = ?
                WHERE SkillID = ? AND UserID = ?
            ");
            $stmt->bind_param("sdssii", $description, $rate, $rate_type, $custom_category, $skill_id, $provider_id);
        } else {
            $stmt = $conn->prepare("
                UPDATE skills 
                SET CategoryID = ?, Description = ?, Rate = ?, RateType = ?, CustomCategory = NULL
                WHERE SkillID = ? AND UserID = ?
            ");
            $stmt->bind_param("isdssi", $category_id, $description, $rate, $rate_type, $skill_id, $provider_id);
        }

        if ($stmt->execute()) {
            $stmt->close();
            $redirectUrl = add_query_before_hash($return_to, 'updated', '1');
            redirect_with_message('success', 'Skill updated successfully!', $redirectUrl);
        } else {
            $err = $conn->error;
            if ($stmt) $stmt->close();
            redirect_with_message('error', "Error updating skill: $err", $return_to);
        }

    } else {
        redirect_with_message('error', 'Please fill all fields.', $return_to);
    }
} 


// -----------------------------
// DELETE SKILL
// -----------------------------
if (isset($_POST['delete_skill_id'])) {
    $return_to = isset($_POST['return_to']) && !empty($_POST['return_to'])
    ? $_POST['return_to']
    : 'provider.php#skills-section';

    $skill_id = intval($_POST['delete_skill_id']);
    $stmt = $conn->prepare("DELETE FROM skills WHERE SkillID = ? AND UserID = ?");
    $stmt->bind_param("ii", $skill_id, $provider_id);
    if ($stmt->execute()) {
        $stmt->close();
        $redirectUrl = add_query_before_hash($return_to, 'deleted', '1');
        redirect_with_message('success', 'Skill deleted successfully!', $redirectUrl);
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

    // Receive return_to safely
    $return_to = $_POST['return_to'] ?? '#jobs-section';

    // Ensure it starts with #
    if (!str_starts_with($return_to, '#')) {
        $return_to = '#jobs-section';
    }

    // ✅ Build a valid redirect URL
    // provider.php?param=value#section
    $redirect_url = 'provider.php?job_updated=1' . $return_to;

    $request_id = intval($_POST['request_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $valid_statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];

    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE request SET Status = ? WHERE RequestID = ? AND ProviderID = ?");
        $stmt->bind_param("sii", $new_status, $request_id, $provider_id);

        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_message('success', 'Status updated successfully!', $redirect_url);
        } else {
            $err = $conn->error;
            if ($stmt) $stmt->close();
            redirect_with_message('error', "Error updating status: $err", $redirect_url);
        }
    } else {
        redirect_with_message('error', 'Invalid status.', $redirect_url);
    }
}



        // -----------------------------
        // GET PROVIDER'S SKILLS (with search + filter)
        // -----------------------------
        $sort = $_GET['sort'] ?? 'newest';
        $search = trim($_GET['search'] ?? '');
        $filter_category = $_GET['filter_category'] ?? '';

        $order_clause = match($sort) {
            'oldest' => 's.DateAdded ASC',
            'highrate' => 's.Rate DESC',
            'lowrate' => 's.Rate ASC',
            'mostbooked' => 'BookingCount DESC',
            default => 's.DateAdded DESC'
        };

            $skills_query = "
            SELECT 
                s.SkillID,
                s.CategoryID,
                s.CustomCategory,
                COALESCE(c.CategoryName, s.CustomCategory) AS CategoryName,
                s.Description,
                s.Rate,
                s.RateType,
                s.DateAdded,
                COUNT(r.RequestID) AS BookingCount
            FROM skills s
            LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
            LEFT JOIN request r ON s.SkillID = r.SkillID
            WHERE s.UserID = ?
        ";



        if (!empty($search)) {
            $skills_query .= " AND (
                c.CategoryName LIKE CONCAT('%', ?, '%')
                OR s.CustomCategory LIKE CONCAT('%', ?, '%')
                OR s.Description LIKE CONCAT('%', ?, '%')
            )";
        }

        if (!empty($filter_category)) {
            $skills_query .= " AND s.CategoryID = ?";
        }

        $skills_query .= "
            GROUP BY s.SkillID
            ORDER BY $order_clause
        ";

 

        // Bind parameters dynamically based on filter/search
        if (!empty($search) && !empty($filter_category)) {
            $stmt = $conn->prepare($skills_query);
            $stmt->bind_param("isssi", $provider_id, $search, $search, $search, $filter_category);
        } elseif (!empty($search)) {
            $stmt = $conn->prepare($skills_query);
            $stmt->bind_param("isss", $provider_id, $search, $search, $search);
        } elseif (!empty($filter_category)) {
            $stmt = $conn->prepare($skills_query);
            $stmt->bind_param("ii", $provider_id, $filter_category);
        } else {
            $stmt = $conn->prepare($skills_query);
            $stmt->bind_param("i", $provider_id);
        }



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

        <div id="toast-area" class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>
        
        <?php
        // pass PHP messages to JS — one-time only
        if (isset($_SESSION['success_message'])) {
            echo '<script>window.success_message = ' . json_encode($_SESSION['success_message']) . ';</script>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<script>window.error_message = ' . json_encode($_SESSION['error_message']) . ';</script>';
            unset($_SESSION['error_message']);
        }
        ?>
      
        <!-- Dashboard -->
    <section class="dashboard" id="dashboard-section">
        <h2 class="mb-4">Dashboard</h2>

        <!-- ====== TOP 3 MOST BOOKED SKILLS ====== -->
        <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light border-bottom fw-semibold">
        <i class="bi bi-bar-chart-fill me-2 text-primary"></i> Top 3 Most Booked Skills
        </div>

        <div class="card-body">
            <?php
            // Fetch top 3 skills dynamically
            $top_skills = $conn->prepare("
                SELECT 
                COALESCE(s.CustomCategory, c.CategoryName) AS SkillName,
                COUNT(r.RequestID) AS TotalBookings
                FROM skills s
                LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
                LEFT JOIN request r ON r.SkillID = s.SkillID
                WHERE s.UserID = ? 
                GROUP BY s.SkillID
                ORDER BY TotalBookings DESC
                LIMIT 3
            ");
            $top_skills->bind_param("i", $provider_id);
            $top_skills->execute();
            $top_results = $top_skills->get_result();

            if ($top_results->num_rows > 0):
            ?>
            <ul class="list-group list-group-flush">
                <?php while ($row = $top_results->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong><?php echo htmlspecialchars($row['SkillName']); ?></strong>
                    <span class="badge bg-primary rounded-pill">
                    <?php echo $row['TotalBookings']; ?> Bookings
                    </span>
                </li>
                <?php endwhile; ?>
            </ul>
            <?php else: ?>
            <div class="text-muted text-center">No bookings yet.</div>
            <?php endif; ?>
        </div>
        </div>


              
        <hr>
        <h6 class="mt-3 mb-2 text-secondary fw-semibold">Current Schedule</h6>

        <div id="availabilityList" class="mt-2"></div>

            <!-- ====== STATS SUMMARY ====== -->
           <div class="row row-cols-1 row-cols-md-4 g-3 mb-4 text-center">
            <div class="col">
                <div class="card text-center shadow-sm border-0 h-100">
                    
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Skills</h6>
                    <h3 class="text-primary"><?php echo $total_skills; ?></h3>
                </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Active Requests</h6>
                    <h3 class="text-info"><?php echo $active_count; ?></h3>
                </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Completed</h6>
                    <h3 class="text-success"><?php echo $completed_count; ?></h3>
                </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Cancelled</h6>
                    <h3 class="text-danger"><?php echo $cancelled_count; ?></h3>
                </div>
                </div>
            </div>
            </div>

        <div class="d-flex gap-2">
            <a href="#add-skill" class="btn btn-primary">Post New Service</a>
            <a href="#jobs-section" class="btn btn-primary">View Jobs</a>
        </div>
        <canvas id="dashboardChart" class="mt-4" width="400" height="200"></canvas>

      
                <h5 class="mt-4 mb-3">Performance Overview</h5>
        <!-- ====== DASHBOARD ANALYTICS ====== -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card p-3">
                    <h5 class="mb-2">Requests Over Time</h5>
                    <canvas id="requestsOverTimeChart" height="200"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-3">
                    <h5 class="mb-2">Requests Summary</h5>
                    <canvas id="statusSummaryChart" height="200"></canvas>
                </div>
            </div>
        </div>
        

          <!-- ====== AVAILABILITY SECTION ====== -->
        <div class="card p-3">
            <h5>My Availability</h5>
            <form id="availabilityForm" class="row g-2 mb-3">
                <div class="col-12 col-md-4">
                    <div class="days-checkbox-group d-flex flex-wrap gap-2">
                        <label><input type="checkbox" name="days[]" value="Monday"> Mon</label>
                        <label><input type="checkbox" name="days[]" value="Tuesday"> Tue</label>
                        <label><input type="checkbox" name="days[]" value="Wednesday"> Wed</label>
                        <label><input type="checkbox" name="days[]" value="Thursday"> Thu</label>
                        <label><input type="checkbox" name="days[]" value="Friday"> Fri</label>
                        <label><input type="checkbox" name="days[]" value="Saturday"> Sat</label>
                        <label><input type="checkbox" name="days[]" value="Sunday"> Sun</label>
                    </div>
                </div>
                <div class="time-group d-flex flex-column me-2">
                    <label for="availStart" class="form-label mb-1 fw-semibold">Start Time</label>
                    <input type="time" name="start_time" id="availStart" class="form-control" required>
                    <small class="text-muted">Use 12-hour format (e.g. 9:00 AM)</small>
                </div>
                <div class="time-group d-flex flex-column me-2">
                    <label for="availEnd" class="form-label mb-1 fw-semibold">End Time</label>
                    <input type="time" name="end_time" id="availEnd" class="form-control" required>
                    <small class="text-muted">Use 12-hour format (e.g. 5:00 PM)</small>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
            <div id="availabilityList">
                <div class="text-muted"></div>
            </div>
        </div>
    </section>

        <!-- Post Service -->
       <section class="add-skill py-4" id="add-skill" style="display:none;">
        <div class="container">
            <div class="card shadow-lg border-0 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0 fw-bold text-primary">
                <i class="bi bi-plus-circle me-2"></i>Post a New Service
                </h2>
                <span class="text-muted small">
                <i class="bi bi-lightbulb me-1 text-warning"></i>
                Provide clear details to attract clients.
                </span>
            </div>


        <div class="row g-4">
            <!-- Left: Form -->
            <div class="col-lg-6">
                <form method="POST" name="add_skill" class="card p-4 border-0 shadow-sm bg-light">
                <h5 class="fw-semibold text-primary mb-3">Service Details</h5>

                <div class="mb-3">
                    <label for="category" class="form-label fw-semibold">Service Category</label>
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
                    <label for="otherCategory" class="form-label fw-semibold">Specify Other Category</label>
                    <input type="text" id="otherCategory" name="custom_category" class="form-control" placeholder="Enter custom category" pattern="[A-Za-z\s]{2,50}" title="Only letters and spaces allowed (2–50 characters)">
                    <small class="text-muted">Letters only, 2–50 characters.</small>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold">Description</label>
                    <textarea id="description" name="description" class="form-control" required maxlength="500" placeholder="Describe your service..."></textarea>
                    <small id="descCounter" class="text-muted float-end"></small>
                </div>

                <!-- Rate Type Selection -->
                    <label class="form-label fw-semibold">Rate Type</label>
                    <div class="d-flex gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="rate_type" id="rateHourly" value="hourly" checked>
                        <label class="form-check-label" for="rateHourly">Hourly (₱/hour)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="rate_type" id="rateDaily" value="daily">
                        <label class="form-check-label" for="rateDaily">Daily (₱/day)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="rate_type" id="rateFixed" value="fixed">
                        <label class="form-check-label" for="rateFixed">Fixed Price (₱ total)</label>
                    </div>
                    </div>

                    <!-- Rate Input -->
                    <div class="mb-3">
                    <label for="rate" class="form-label fw-semibold">Enter Rate</label>
                    <input type="number" id="rate" name="rate" class="form-control" min="1" step="1" required placeholder="Enter amount (e.g., 500)">                    
                    <small id="ratePreview" class="text-muted mt-1">Example: ₱500/hour</small>
                    </div>


                <div class="mb-3">
                    <label for="serviceImage" class="form-label fw-semibold">Upload Service Image (optional)</label>
                    <input type="file" id="serviceImage" name="service_image" class="form-control" accept="image">
                </div>

                <button type="submit" name="add_skill" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-send me-2"></i>Post My Skill
                </button>
                </form>
            </div>

            <!-- Right: Live Preview -->
            <div class="col-lg-6">
                <div id="servicePreview" class="card border-0 shadow-sm">
                <div class="card-header text-white fw-semibold" style="background: linear-gradient(135deg, #007bff, #6610f2);">
                    <i class="bi bi-eye me-2"></i> Live Preview
                </div>
                <div class="card-body text-center">
                    <div id="previewImage" class="rounded shadow-sm border d-flex align-items-center justify-content-center bg-light mb-3" style="height: 180px; overflow: hidden;">
                    <span class="text-muted">No image selected</span>
                    </div>
                    <h5 class="text-primary mb-2" id="previewCategory">—</h5>
                    <p class="text-muted small mb-3" id="previewDescription">No description yet.</p>
                    <span class="badge bg-light text-success fs-6 p-2 border" id="previewRate">₱0/hr</span>
                </div>
                <div class="card-footer text-center text-muted small">
                    <em>Preview updates live as you type.</em>
                </div>
                </div>
            </div>
            </div>
            </div> <!-- close .card -->
            </div> <!-- close .container -->
            </section>



        <!-- My Skills -->
        <section class="my-skills" id="skills-section" style="display:none;">
            <h2 class="mb-4 d-flex justify-content-between align-items-center">
                <span>My Skills</span>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <label for="sort" class="form-label mb-0">Sort by:</label>
                    <select name="sort" id="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= ($_GET['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="highrate" <?= ($_GET['sort'] ?? '') === 'highrate' ? 'selected' : '' ?>>Highest Rate</option>
                        <option value="lowrate" <?= ($_GET['sort'] ?? '') === 'lowrate' ? 'selected' : '' ?>>Lowest Rate</option>
                        <option value="mostbooked" <?= ($_GET['sort'] ?? '') === 'mostbooked' ? 'selected' : '' ?>>Most Booked</option>
                    </select>
                </form>
            </h2>

            <!-- Search and Filter Controls -->
                <form method="GET" id= "skillsFilterForm" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="searchSkill" class="form-label mb-1">Search Skill</label>
                    <input type="text" name="search" id="searchSkill" class="form-control"
                        placeholder="Search by name or description"
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>

                <div class="col-md-3">
                    <label for="filterCategory" class="form-label mb-1">Filter by Category</label>
                    <select name="filter_category" id="filterCategory" class="form-select">
                    <option value="">All Categories</option>
                    <?php
                        $cats = $conn->query("SELECT CategoryID, CategoryName FROM skill_categories WHERE IsApproved = 1");
                        while ($c = $cats->fetch_assoc()):
                        $selected = ($_GET['filter_category'] ?? '') == $c['CategoryID'] ? 'selected' : '';
                        echo "<option value='{$c['CategoryID']}' $selected>" . htmlspecialchars($c['CategoryName']) . "</option>";
                        endwhile;
                    ?>
                    </select>
                </div>

            

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <button type="button" id="clearFilters" class="btn btn-secondary w-100">Clear</button>
                </div>

                

                </form>

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

                                   <p class="card-text mb-1">
                                        <?php
                                            $rateLabel = '';
                                            switch ($skill['RateType'] ?? 'hourly') {
                                                case 'daily':
                                                    $rateLabel = '/day';
                                                    break;
                                                case 'fixed':
                                                    $rateLabel = ' (fixed)';
                                                    break;
                                                default:
                                                    $rateLabel = '/hour';
                                                    break;
                                            }
                                        ?>
                                        <span class="badge bg-success fs-6 px-3 py-2">
                                            <i class="bi bi-cash-stack me-1"></i>
                                            ₱<?= number_format($skill['Rate'] ?? 0, 2); ?><?= $rateLabel; ?>
                                        </span>

                                    </p>
                                    <p class="card-text small text-muted mb-2">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Added on: <?php echo date('M d, Y', strtotime($skill['DateAdded'])); ?></p>
                                    </p>
                                    <p class="card-text small text-muted mb-2">
                                        <i class="bi bi-people me-1"></i>
                                        Bookings: <?php echo (int)($skill['BookingCount'] ?? 0); ?>
                                    </p>
                                    <p class="card-text">
                                        <strong>Description:</strong>
                                        <?php echo htmlspecialchars($skill['Description'] ?? 'No description'); ?>
                                    </p>

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
                                            <option value="others" <?php echo (empty($skill['CategoryID']) && !empty($skill['CustomCategory'])) ? 'selected' : ''; ?>>
                                                Other (Specify)
                                            </option>
                                        </select>

                                        <!-- Specify input (visible only if custom category exists) -->
                                        <div class="mb-2 otherCategoryGroup" style="<?php echo (!empty($skill['CustomCategory'])) ? '' : 'display:none;'; ?>">
                                            <label class="form-label">Specify Other Category</label>
                                            <input type="text" class="form-control otherCategoryInput" name="custom_category"
                                                value="<?php echo htmlspecialchars($skill['CustomCategory'] ?? ''); ?>"
                                                placeholder="Enter custom category"
                                                pattern="[A-Za-z\s]{2,50}"
                                                title="Only letters and spaces are allowed (2–50 characters)">

                                        </div>

                                        <textarea name="description" class="form-control" required maxlength="500">
                                                <?php echo htmlspecialchars($skill['Description'] ?? ''); ?>
                                            </textarea>

                                            <!-- Rate input -->
                                            <input type="number" name="rate" class="form-control"
                                                value="<?php echo (int)($skill['Rate'] ?? 0); ?>"
                                                required min="1" step="1">

                                            <!-- Rate Type Selector -->
                                            <div class="mb-2">
                                            <label class="form-label fw-semibold">Rate Type</label>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                        name="rate_type"
                                                        id="rate_type_hourly_<?php echo $skill['SkillID']; ?>"
                                                        value="hourly"
                                                        <?php echo (($skill['RateType'] ?? 'hourly') === 'hourly') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="rate_type_hourly_<?php echo $skill['SkillID']; ?>">Hourly</label>
                                                </div>
                                                <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                        name="rate_type"
                                                        id="rate_type_daily_<?php echo $skill['SkillID']; ?>"
                                                        value="daily"
                                                        <?php echo (($skill['RateType'] ?? '') === 'daily') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="rate_type_daily_<?php echo $skill['SkillID']; ?>">Daily</label>
                                                </div>
                                                <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                        name="rate_type"
                                                        id="rate_type_fixed_<?php echo $skill['SkillID']; ?>"
                                                        value="fixed"
                                                        <?php echo (($skill['RateType'] ?? '') === 'fixed') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="rate_type_fixed_<?php echo $skill['SkillID']; ?>">Fixed</label>
                                                </div>
                                            </div>
                                            </div>

                                            <input type="hidden" name="return_to" value="#skills-section">
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
                        <div class="card-header d-flex justify-content-between align-items-center px-3 py-2"
                            style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>; color:#fff;">
                        <strong><?php echo htmlspecialchars($r['Status'] ?? 'Unknown'); ?></strong>
                        <small>
                            <?php
                            $raw = $r['Schedule'] ?? null;
                            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
                                echo htmlspecialchars($dt ? $dt->format('F j, Y • g:i A') : 'Not set');
                            } else {
                                echo htmlspecialchars($raw ?: 'Not set');
                            }
                            ?>
                        </small>
                        </div>

                        <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php if (!empty($r['Avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($r['Avatar']); ?>"
                                alt="Avatar"
                                class="rounded-circle me-2"
                                style="width:40px;height:40px;object-fit:cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2"
                                style="width:40px;height:40px;">
                                <?php echo strtoupper(substr($r['FName'] ?? '?',0,1)); ?>
                            </div>
                            <?php endif; ?>
                            <h6 class="mb-0">
                            <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?>
                            </h6>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title"><?php echo htmlspecialchars($r['SkillName'] ?? 'Unnamed Skill'); ?></h5>
                            <span class="badge"
                                style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>">
                            <?php echo htmlspecialchars($r['Status'] ?? 'Unknown'); ?>
                            </span>
                        </div>

                        <p class="card-text"><strong>Client:</strong>
                            <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?>
                        </p>

                        <p class="card-text"><strong>Location:</strong>
                            <?php echo htmlspecialchars($r['Location'] ?? 'Unknown'); ?>
                        </p>

                        <p class="card-text"><strong>Schedule:</strong>
                            <?php
                            $raw = $r['Schedule'] ?? null;
                            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
                                echo htmlspecialchars($dt ? $dt->format('F j, Y • g:i A') : 'Not set');
                            } else {
                                echo htmlspecialchars($raw ?: 'Not set');
                            }
                            ?>
                        </p>
                        </div>

                        <div class="card-footer bg-light d-flex justify-content-end gap-2">
                        <?php if (strtolower($r['Status'] ?? '') === 'pending'): ?>
                            <form method="POST" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                            <input type="hidden" name="new_status" value="Confirmed">
                            <input type="hidden" name="return_to" value="#jobs-section">
                            <button type="submit" name="update_status" class="btn btn-success me-2">Accept</button>
                            </form>
                            <form method="POST" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                            <input type="hidden" name="new_status" value="Cancelled">
                            <input type="hidden" name="return_to" value="#jobs-section">
                            <button type="submit" name="update_status" class="btn btn-danger">Decline</button>
                            </form>
                        <?php elseif (strtolower($r['Status'] ?? '') === 'confirmed'): ?>
                            <form method="POST" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                            <input type="hidden" name="new_status" value="In Progress">
                            <input type="hidden" name="return_to" value="#jobs-section">
                            <button type="submit" name="update_status" class="btn btn-warning">Start</button>
                            </form>
                        <?php elseif (strtolower($r['Status'] ?? '') === 'in progress'): ?>
                            <form method="POST" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $r['RequestID'] ?? 0; ?>">
                            <input type="hidden" name="new_status" value="Completed">
                            <input type="hidden" name="return_to" value="#jobs-section">
                            <button type="submit" name="update_status" class="btn btn-success">Complete</button>
                            </form>
                        <?php endif; ?>
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
                        <div class="card-header d-flex justify-content-between align-items-center px-3 py-2"
                            style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>; color:#fff;">
                        <strong>✅ <?php echo htmlspecialchars($r['Status'] ?? 'Completed'); ?></strong>
                        <small>
                            <?php
                            $raw = $r['Schedule'] ?? null;
                            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
                                echo htmlspecialchars($dt ? $dt->format('F j, Y • g:i A') : 'Not set');
                            } else {
                                echo htmlspecialchars($raw ?: 'Not set');
                            }
                            ?>
                        </small>
                        </div>

                        <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php if (!empty($r['Avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($r['Avatar']); ?>"
                                alt="Avatar"
                                class="rounded-circle me-2"
                                style="width:40px;height:40px;object-fit:cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2"
                                style="width:40px;height:40px;">
                                <?php echo strtoupper(substr($r['FName'] ?? '?',0,1)); ?>
                            </div>
                            <?php endif; ?>
                            <h6 class="mb-0">
                            <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?>
                            </h6>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title"><?php echo htmlspecialchars($r['SkillName'] ?? 'Unnamed Skill'); ?></h5>
                            <span class="badge"
                                style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>">
                            <?php echo htmlspecialchars($r['Status'] ?? 'Completed'); ?>
                            </span>
                        </div>

                        <p class="card-text"><strong>Client:</strong>
                            <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?>
                        </p>

                        <p class="card-text"><strong>Location:</strong>
                            <?php echo htmlspecialchars($r['Location'] ?? 'Unknown'); ?>
                        </p>

                        <p class="card-text"><strong>Schedule:</strong>
                            <?php
                            $raw = $r['Schedule'] ?? null;
                            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
                                echo htmlspecialchars($dt ? $dt->format('F j, Y • g:i A') : 'Not set');
                            } else {
                                echo htmlspecialchars($raw ?: 'Not set');
                            }
                            ?>
                        </p>
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
                        <div class="card-header d-flex justify-content-between align-items-center px-3 py-2"
                            style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>; color:#fff;">
                        <strong>❌ <?php echo htmlspecialchars($r['Status'] ?? 'Cancelled'); ?></strong>
                        <small>
                            <?php
                            $raw = $r['Schedule'] ?? null;
                            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
                                echo htmlspecialchars($dt ? $dt->format('F j, Y • g:i A') : 'Not set');
                            } else {
                                echo htmlspecialchars($raw ?: 'Not set');
                            }
                            ?>
                        </small>
                        </div>

                        <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php if (!empty($r['Avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($r['Avatar']); ?>"
                                alt="Avatar"
                                class="rounded-circle me-2"
                                style="width:40px;height:40px;object-fit:cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2"
                                style="width:40px;height:40px;">
                                <?php echo strtoupper(substr($r['FName'] ?? '?',0,1)); ?>
                            </div>
                            <?php endif; ?>
                            <h6 class="mb-0">
                            <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?>
                            </h6>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title"><?php echo htmlspecialchars($r['SkillName'] ?? 'Unnamed Skill'); ?></h5>
                            <span class="badge"
                                style="background-color: <?php echo getStatusColor($r['Status'] ?? ''); ?>">
                            <?php echo htmlspecialchars($r['Status'] ?? 'Cancelled'); ?>
                            </span>
                        </div>

                        <p class="card-text"><strong>Client:</strong>
                            <?php echo htmlspecialchars(trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? ''))) ?: 'Unknown Client'; ?>
                        </p>

                        <p class="card-text"><strong>Location:</strong>
                            <?php echo htmlspecialchars($r['Location'] ?? 'Unknown'); ?>
                        </p>

                        <p class="card-text"><strong>Schedule:</strong>
                            <?php
                            $raw = $r['Schedule'] ?? null;
                            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
                                echo htmlspecialchars($dt ? $dt->format('F j, Y • g:i A') : 'Not set');
                            } else {
                                echo htmlspecialchars($raw ?: 'Not set');
                            }
                            ?>
                        </p>
                        </div>
                    </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="alert alert-info" role="alert">No cancelled requests.</div>
                <?php endif; ?>
            </div>
            </section>

                 

                    <!--  Toast Message Container -->
                <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 6000;">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

    </main>

    <!-- Bootstrap JS and Custom JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/provider.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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