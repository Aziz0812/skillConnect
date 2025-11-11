<?php
define('ADMIN_PAGE', true);
session_start();
require '../db.php';
require 'includes/auth_check.php';

$page_title = 'Pending Approvals';
$current_page = 'approvals';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $skill_id = intval($_POST['skill_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE skills SET ApprovalStatus = 'approved' WHERE SkillID = ?");
        $stmt->bind_param("i", $skill_id);
        if ($stmt->execute()) {
            logAdminAction($conn, $admin_id, 'approve', 'skill', $skill_id, 'Service approved');
            $_SESSION['admin_success'] = 'Service approved successfully!';
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
    }
    
    header("Location: approvals.php");
    exit;
}

// Get pending skills
$pending_query = "
    SELECT 
        s.SkillID, s.Description, s.Rate, s.RateType, s.DateAdded,
        COALESCE(c.CategoryName, s.CustomCategory, 'Uncategorized') AS CategoryName,
        u.FName, u.LName, u.ID as ProviderID
    FROM skills s
    LEFT JOIN skill_categories c ON s.CategoryID = c.CategoryID
    JOIN users u ON s.UserID = u.ID
    WHERE s.ApprovalStatus = 'pending'
    ORDER BY s.DateAdded DESC
";
$pending_skills = $conn->query($pending_query);

include 'includes/header.php';
?>

<style>
    .approval-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 24px;
    }
    
    .approval-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border-left: 4px solid #ffc107;
    }
    
    .approval-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .skill-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .skill-category {
        font-weight: 700;
        color: #2c3e50;
        font-size: 16px;
    }
    
    .skill-rate {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 6px 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 13px;
    }
    
    .skill-provider {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 12px;
    }
    
    .skill-description {
        font-size: 14px;
        color: #495057;
        line-height: 1.6;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .skill-meta {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
        font-size: 12px;
        color: #6c757d;
    }
    
    .skill-actions {
        display: flex;
        gap: 8px;
    }
    
    .action-btn {
        flex: 1;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .action-btn.approve {
        background: #28a745;
        color: white;
    }
    
    .action-btn.approve:hover {
        background: #218838;
        transform: translateY(-2px);
    }
    
    .action-btn.reject {
        background: #dc3545;
        color: white;
    }
    
    .action-btn.reject:hover {
        background: #c82333;
        transform: translateY(-2px);
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
    <h1 class="page-title">⏳ Pending Approvals</h1>
    <p class="page-subtitle">Review and approve new service submissions</p>
</div>

<div class="approval-grid">
    <?php if ($pending_skills && $pending_skills->num_rows > 0): ?>
        <?php while ($skill = $pending_skills->fetch_assoc()): ?>
            <div class="approval-card">
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
                    👤 <?php echo htmlspecialchars($skill['FName'] . ' ' . $skill['LName']); ?>
                </div>
                
                <div class="skill-description">
                    <?php echo htmlspecialchars($skill['Description']); ?>
                </div>
                
                <div class="skill-meta">
                    📅 Submitted: <?php echo date('M j, Y g:i A', strtotime($skill['DateAdded'])); ?>
                </div>
                
                <div class="skill-actions">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="skill_id" value="<?php echo $skill['SkillID']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="action-btn approve">✅ Approve</button>
                    </form>
                    <button type="button" class="action-btn reject" onclick="openRejectModal(<?php echo $skill['SkillID']; ?>)">
                        ❌ Reject
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <h3>All caught up!</h3>
            <p>No pending service approvals at the moment.</p>
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