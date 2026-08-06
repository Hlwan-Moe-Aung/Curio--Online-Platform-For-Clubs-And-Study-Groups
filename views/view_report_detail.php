<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/dashboard.php");
    exit();
}

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Fetch report details
$sql = "SELECT r.*, u.fullname as reporter_name 
        FROM reports r 
        JOIN users u ON r.reporter_email = u.email 
        WHERE r.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $report_id);
mysqli_stmt_execute($stmt);
$report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$report) {
    header("Location: manage_reports.php?error=not_found");
    exit();
}

// 2. Check if Entity Exists & Determine Link
$target_exists = false;
$target_link = "#";
$item_id = $report['item_id'];
$item_type = $report['item_type'];

switch ($item_type) {
    case 'material':
        // Check materials table and get the community/club ID it belongs to
        $check_sql = "SELECT id, community_id FROM studymaterial WHERE id = ?";
        $c_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($c_stmt, "i", $item_id);
        mysqli_stmt_execute($c_stmt);
        $res = mysqli_stmt_get_result($c_stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $target_exists = true;
            // Link to the materials page with an anchor to the specific ID
            $target_link = "studyMaterials.php?id=" . $row['community_id'] . "#material_" . $item_id;
        }
        break;

    case 'post':
        $check_sql = "SELECT id FROM posts WHERE id = ?";
        $c_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($c_stmt, "i", $item_id);
        mysqli_stmt_execute($c_stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($c_stmt)) > 0) {
            $target_exists = true;
            $target_link = "view_post.php?id=" . $item_id;
        }
        break;

    case 'user':
        $check_sql = "SELECT email FROM users WHERE email = (SELECT email FROM users WHERE id = ? LIMIT 1)"; 
        // Note: adjust 'id = ?' if your reports table stores user ID or email as item_id
        $c_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
        mysqli_stmt_bind_param($c_stmt, "i", $item_id);
        mysqli_stmt_execute($c_stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($c_stmt)) > 0) {
            $target_exists = true;
            $target_link = "view_user_detail.php?id=" . $item_id;
        }
        break;

    case 'community':
        $check_sql = "SELECT id FROM communities WHERE id = ?";
        $c_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($c_stmt, "i", $item_id);
        mysqli_stmt_execute($c_stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($c_stmt)) > 0) {
            $target_exists = true;
            $target_link = "view_community_detail.php?id=" . $item_id;
        }
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Investigate Report #<?php echo $report_id; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
    <style>
        .report-detail-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        .detail-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .info-label { color: #888; font-size: 12px; text-transform: uppercase; font-weight: bold; margin-bottom: 5px; display: block; }
        .info-value { font-size: 16px; margin-bottom: 20px; display: block; color: #333; }
        .evidence-preview { max-width: 100%; border-radius: 5px; margin-top: 10px; border: 1px solid #ddd; }
        .status-select { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="main-content" id="main">
    <div class="dashboard-container">
        <a href="manage_reports.php" class="btn-back">← Back to Reports</a>
        
        <header class="dashboard-header" style="margin-top: 20px;">
            <h1>Investigation: Report #<?php echo $report_id; ?></h1>
            <span class="status-indicator status-<?php echo $report['status']; ?>">
                Status: <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
            </span>
        </header>

        <div class="report-detail-grid">
            <div class="detail-card">
                <h3>Report Details</h3>
                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
                
                <span class="info-label">Reporter</span>
                <span class="info-value">
                    <strong><?php echo htmlspecialchars($report['reporter_name']); ?></strong> 
                    (<?php echo htmlspecialchars($report['reporter_email']); ?>)
                </span>

                <span class="info-label">Reason Category</span>
                <span class="info-value" style="color: #d9534f; font-weight: 600;">
                    <?php echo htmlspecialchars($report['reason_category']); ?>
                </span>

                <span class="info-label">Description / Additional Links</span>
                <div class="info-value" style="background: #f9f9f9; padding: 15px; border-radius: 5px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                </div>

                <span class="info-label">Evidence Screenshot</span>
                <div class="info-value">
                    <?php if($report['evidence_file']): ?>
                        <a href="../uploads/evidence/<?php echo $report['evidence_file']; ?>" target="_blank">
                            <img src="../uploads/evidence/<?php echo $report['evidence_file']; ?>" class="evidence-preview" alt="Evidence">
                        </a>
                    <?php else: ?>
                        <p style="color: #bbb;">No image evidence provided.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-card">
                <h3>Take Action</h3>
                <form method="POST" action="../api/admin_report_handler.php">
                    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                    <div class="form-group">
                        <label class="info-label">Target Entity Status</label>
                        <div style="padding: 15px; border-radius: 6px; background: <?php echo $target_exists ? '#f0f9ff' : '#fff1f1'; ?>; border: 1px solid <?php echo $target_exists ? '#bee3f8' : '#fed7d7'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="color: #333;"><?php echo ucfirst($item_type); ?> #<?php echo $item_id; ?></strong><br>
                                    <?php if ($target_exists): ?>
                                        <span style="color: #2f855a; font-size: 13px;">● Active / Exists</span>
                                    <?php else: ?>
                                        <span style="color: #c53030; font-size: 13px;">● Deleted / Not Found</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($target_exists): ?>
                                    <a href="<?php echo $target_link; ?>" target="_blank" class="btn-small" style="background: #3a86ff;">View Content</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!$target_exists): ?>
                            <p style="font-size: 12px; color: #666; margin-top: 8px;">
                                <em>Note: The reported content was likely deleted by the user or another moderator already. You can dismiss this report.</em>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="status" class="info-label">Update Status</label>
                        <select name="status" id="statusSelect" class="status-select" onchange="autoFillMessage()">
                            <option value="pending" <?php if($report['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="under_review" <?php if($report['status'] == 'under_review') echo 'selected'; ?>>Under Review</option>
                            <option value="resolved" <?php if($report['status'] == 'resolved') echo 'selected'; ?>>Resolved (Content Handled)</option>
                            <option value="dismissed" <?php if($report['status'] == 'dismissed') echo 'selected'; ?>>Dismissed (No violation)</option>
                        </select>
                    </div>

                    <div class="form-group" id="userMsgGroup">
                        <label for="user_message" class="info-label">Message to Reported Member (Notification)</label>
                        <textarea name="user_message" id="userMessage" rows="4" class="status-select" 
                                  placeholder="This message will be sent to the owner of the reported content..."></textarea>
                        <p style="font-size: 11px; color: #e67e22;">
                            Note: If 'Resolved', the author will receive this as a severe warning.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="admin_note" class="info-label">Internal Admin Note</label>
                        <textarea name="admin_note" rows="5" class="status-select" placeholder="Explain the action taken or findings..."><?php echo htmlspecialchars($report['admin_note'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="update_status" class="btn-submit" style="width: 100%; background: #3a86ff; color: white;">
                        Save Investigation
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function autoFillMessage() {
    const status = document.getElementById('statusSelect').value;
    const msgBox = document.getElementById('userMessage');
    const type = "<?php echo $report['item_type']; ?>";
    
    if (status === 'resolved') {
        msgBox.value = `SEVERE WARNING: Your ${type} has been flagged and removed for violating community guidelines regarding: <?php echo $report['reason_category']; ?>. Repeated violations will lead to a permanent ban.`;
    } else if (status === 'dismissed') {
        msgBox.value = `Regarding the report on your ${type}: After review, we found no violation of our terms. No action has been taken.`;
    }
}
</script>
</body>
</html>