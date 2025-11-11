<?php
define('ADMIN_PAGE', true);
session_start();
require '../db.php';
require 'includes/auth_check.php';

$page_title = 'Services Management';
$current_page = 'services';

// Handle service actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $skill_id = intval($_POST['skill_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE skills SET ApprovalStatus = 'approved' WHERE SkillID = ?");
        $stmt->bind_param("i", $skill_id);
        if ($stmt->execute()) {
            logAdminAction($conn, $admin_id, 'approve', 'skill', $skill_id, 'Service approved');
            $_SESSION['admin_success'] = 'Service approved successfully';
        }
        $stmt->close();
    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'Does not meet guidelines';
        $stmt = $conn->prepare("UPDATE skills SET ApprovalStatus = 'rejected', RejectionReason = ? WHERE SkillID = ?");
        $stmt->bind_param("si", $reason, $skill_id);
        if ($stmt->execute()) {
            logAdminAction($conn, $admin_id, 'reject', 'skill', $skill_id, "Service rejected: $reason");
            $_SESSION['admin_success'] = 'Service rejected';
        }
        $stmt->close();
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE skills SET IsActive = 0 WHERE SkillID = ?");
        $stmt->bind_param("i", $skill_id);
        if ($stmt->execute()) {
            logAdminAction($conn, $admin_id, 'deactivate', 'skill', $skill_id, 'Service deactivated');
            $_SESSION['admin_success'] = 'Service deactivated';
        }
        $stmt->close();
    } elseif ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE skills SET IsActive = 1 WHERE SkillID = ?");
        $stmt->bind_param("i", $skill_id);
        if ($stmt->execute()) {
            logAdminAction($conn, $admin_id, 'activate', 'skill', $skill_id, 'Service activated');
            $_SESSION['admin_success'] = 'Service activated';
        }
        $stmt->close();
    }
    
    header("Location: services.php");
    exit;
}

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_clauses[] = "s.ApprovalStatus = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($category_filter !== 'all') {
    $where_clauses[] = "s.CategoryID = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = "(s.Description LIKE ? OR u.FName LIKE ? OR u.LName LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "
    SELECT 
        s.SkillID, s.Description, s.Rate, s.RateType, s.IsActive, 
        s.ApprovalStatus, s.DateAdded, s.RejectionReason,
        COALESCE(c.CategoryName, s.CustomCategory, 'Uncategorized') AS CategoryName,
        u.FName, u.LName, u.ID as ProviderID
    FROM skills s
    LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
    JOIN users u ON s.UserID = u.ID
    $where_sql
    ORDER BY s.DateAdded DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$services = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT CategoryID, CategoryName FROM skill_categories ORDER BY CategoryName");

include 'includes/header.php';
?>

<style>
    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-btn {
        padding: 10px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        align-self: flex-end;
        transition: all 0.3s ease;
    }
    
    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 24px;
    }
    
    .service-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border-left: 4px solid #e9ecef;
    }
    
    .service-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .service-card.pending { border-left-color: #ffc107; }
    .service-card.approved { border-left-color: #28a745; }
    .service-card.rejected { border-left-color: #dc3545; }
    
    .service-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .service-category {
        font-weight: 700;
        color: #2c3e50;
        font-size: 16px;
    }
    
    .service-rate {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 6px 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 13px;
    }
    
    .service-provider {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 12px;
    }
    
    .service-description {
        font-size: 14px;
        color: #495057;
        line-height: 1.6;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .service-meta {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    
    .meta-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .meta-badge.pending {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    
    .meta-badge.approved {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .meta-badge.rejected {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .meta-badge.active {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .meta-badge.inactive {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }
    
    .service-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .action-btn {
        flex: 1;
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer
        transition: all 0.3s ease;
        min-width: 100px;
    }
    
    .action-btn.approve {
        background: #28a745;
        color: white;
    }
    
    .action-btn.reject {
        background: #dc3545;
        color: white;
    }
    
    .action-btn.deactivate {
        background: #6c757d;
        color: white;
    }
    
    .action-btn.activate {
        background: #28a745;
        color: white;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .rejection-reason {
        background: #fee;
        border-left: 3px solid #dc3545;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 12px;
        font-size: 12px;
        color: #721c24;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        grid-column: 1 / -1;
    }
    
    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.5;
    }
    
    /* Reject Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        font-size: 20px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
    }
    
    .modal-body textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        resize: vertical;
        min-height: 100px;
        font-family: inherit;
    }
    
    .modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }
    
    .modal-btn {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .modal-btn.cancel {
        background: #e9ecef;
        color: #495057;
    }
    
    .modal-btn.submit {
        background: #dc3545;
        color: white;
    }
    
    .modal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>

<div class="page-header">
    <h1 class="page-title">🛠️ Services Management</h1>
    <p class="page-subtitle">Review and manage all service listings</p>
</div>

<form class="filter-bar" method="GET">
    <div class="filter-group">
        <label>Status</label>
        <select name="status">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Category</label>
        <select name="category">
            <option value="all">All Categories</option>
            <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?php echo $cat['CategoryID']; ?>" <?php echo $category_filter == $cat['CategoryID'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['CategoryName']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search services..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <button type="submit" class="filter-btn">🔍 Filter</button>
</form>

<div class="services-grid">
    <?php if ($services && $services->num_rows > 0): ?>
        <?php while ($service = $services->fetch_assoc()): ?>
            <div class="service-card <?php echo $service['ApprovalStatus']; ?>">
                <div class="service-header">
                    <span class="service-category"><?php echo htmlspecialchars($service['CategoryName']); ?></span>
                    <span class="service-rate">
                        ₱<?php echo number_format($service['Rate'], 0); ?><?php 
                            echo $service['RateType'] === 'daily' ? '/day' : 
                                ($service['RateType'] === 'fixed' ? '' : '/hr'); 
                        ?>
                    </span>
                </div>
                
                <div class="service-provider">
                    👤 <?php echo htmlspecialchars($service['FName'] . ' ' . $service['LName']); ?>
                </div>
                
                <div class="service-description">
                    <?php echo htmlspecialchars($service['Description']); ?>
                </div>
                
                <?php if ($service['ApprovalStatus'] === 'rejected' && $service['RejectionReason']): ?>
                    <div class="rejection-reason">
                        <strong>❌ Rejected:</strong> <?php echo htmlspecialchars($service['RejectionReason']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="service-meta">
                    <span class="meta-badge <?php echo $service['ApprovalStatus']; ?>">
                        <?php 
                            $statusIcons = ['pending' => '⏳', 'approved' => '✅', 'rejected' => '❌'];
                            echo $statusIcons[$service['ApprovalStatus']] . ' ' . ucfirst($service['ApprovalStatus']); 
                        ?>
                    </span>
                    <span class="meta-badge <?php echo $service['IsActive'] ? 'active' : 'inactive'; ?>">
                        <?php echo $service['IsActive'] ? '🟢 Active' : '🔴 Inactive'; ?>
                    </span>
                    <span class="meta-badge" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                        📅 <?php echo date('M j, Y', strtotime($service['DateAdded'])); ?>
                    </span>
                </div>
                
                <div class="service-actions">
                    <?php if ($service['ApprovalStatus'] === 'pending'): ?>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="skill_id" value="<?php echo $service['SkillID']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="action-btn approve">✅ Approve</button>
                        </form>
                        <button type="button" class="action-btn reject" onclick="openRejectModal(<?php echo $service['SkillID']; ?>)">
                            ❌ Reject
                        </button>
                    <?php elseif ($service['ApprovalStatus'] === 'approved'): ?>
                        <?php if ($service['IsActive']): ?>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="skill_id" value="<?php echo $service['SkillID']; ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="action-btn deactivate" onclick="return confirm('Deactivate this service?')">
                                    🔴 Deactivate
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="skill_id" value="<?php echo $service['SkillID']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="action-btn activate" onclick="return confirm('Activate this service?')">
                                    🟢 Activate
                                </button>
                            </form>
                        <?php endif; ?>
                        <button type="button" class="action-btn reject" onclick="openRejectModal(<?php echo $service['SkillID']; ?>)">
                            ❌ Reject
                        </button>
                    <?php elseif ($service['ApprovalStatus'] === 'rejected'): ?>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="skill_id" value="<?php echo $service['SkillID']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="action-btn approve">✅ Re-approve</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <p>No services found matching your criteria</p>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">❌ Reject Service</div>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="skill_id" id="rejectSkillId">
            <input type="hidden" name="action" value="reject">
            <div class="modal-body">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    Reason for rejection:
                </label>
                <textarea name="reason" required placeholder="Enter reason for rejection..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="modal-btn submit">Reject Service</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(skillId) {
        document.getElementById('rejectSkillId').value = skillId;
        document.getElementById('rejectModal').classList.add('active');
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>