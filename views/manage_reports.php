<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php');

// Security: Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// --- Logic for Reports ---
$search = $_GET['search_r'] ?? '';
$sort = $_GET['sort'] ?? 'Newest First';
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'pending';

// Ensure the order by uses the alias r
$sort_sql = ($sort === 'Oldest First') ? "ORDER BY r.created_at ASC" : "ORDER BY r.created_at DESC";

// 1. Base SQL MUST NOT end with WHERE 1=1 if your build_filtered_query 
// adds "AND" immediately. However, since your function adds "AND", 
// keeping "WHERE 1=1" is actually safer.
$base_sql = "SELECT r.*, u.fullname as reporter_name 
             FROM reports r 
             JOIN users u ON r.reporter_email = u.email 
             WHERE 1=1";

// 2. Add the Status and Type filters to the $filters array parameter 
// instead of concatenating them manually to prevent SQL injection 
// and string errors.
$active_filters = [];
if ($status_filter !== 'all') {
    $active_filters['r.status'] = $status_filter;
}
if ($type_filter !== 'all') {
    $active_filters['r.item_type'] = $type_filter;
}

// 3. Updated function call
$reports_result = build_filtered_query(
    $conn, 
    $base_sql, 
    $active_filters, // Pass status/type here instead of concatenating to $base_sql
    $search, 
    ['u.fullname', 'r.reporter_email', 'r.reason_category'], 
    $sort_sql
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reports | Admin</title>
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'report_updated') {
    $display_text = "Report Status Updated Successfully!";
} 
?>

<?php if ($display_text): ?>
    <div id="yt-toast" class="toast">
        <div class="toast-content">
            <?= htmlspecialchars($display_text) ?>
        </div>
        <div class="toast-actions">
            <button class="toast-close" onclick="closeToast()" aria-label="Close">
                <svg class="progress-ring" width="24" height="24">
                    <circle class="progress-ring__circle" stroke="#3ea6ff" stroke-width="2" fill="transparent" r="10" cx="12" cy="12"/>
                </svg>
                <span class="close-icon">✕</span>
            </button>
        </div>
    </div>
<?php endif; ?>

<div class="main-content" id="main">
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Content Moderation</h1>
            <p>Review and act on user-reported content.</p>
        </header>

        <section class="admin-table-card">
            <h3>User Reports</h3>
            <?php 
            $filter_config = [
                'action' => 'manage_reports.php',
                'search_key' => 'search_r',
                'placeholder' => 'Search name, email, or reason...',
                'dropdowns' => [
                    'status' => [
                        'all' => 'All Statuses',
                        'pending' => 'Pending',
                        'under_review' => 'Under Review',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed'
                    ],
                    'type' => [
                        'all' => 'All Types',
                        'material' => 'Materials',
                        'post' => 'Posts',
                        'user' => 'Users',
                        'community' => 'Communities'
                    ],
                    'sort' => [
                        'Newest First' => 'Newest',
                        'Oldest First' => 'Oldest'
                    ],
                ]
            ];
            include '../includes/filter_component.php'; 
            ?>

            <table class="standard-table">
                <thead>
                    <tr>
                        <th>Reporter</th>
                        <th>Target Entity</th>
                        <th>Reason Category</th>
                        <th>Evidence</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($reports_result)): ?>
                    <tr>
                        <td>
                            <div class="user-info-cell">
                                <strong><?php echo htmlspecialchars($row['reporter_name']); ?></strong>
                                <div style="font-size: 12px; color: #555;"><?php echo htmlspecialchars($row['reporter_email']); ?></div>
                                <div style="font-size: 11px; color: #888; margin-top: 2px;">
                                    <?php echo date('M d, Y | h:i A', strtotime($row['created_at'])); ?>
                                </div>
                            </div>
                        </td>
                        
                        <td>
                            <span class="badge-type" style="background: #f0f0f0; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                <?php echo strtoupper($row['item_type']); ?>
                            </span>
                            <div style="font-size: 12px; color: #3a86ff; margin-top: 4px;">ID: #<?php echo $row['item_id']; ?></div>
                        </td>

                        <td>
                            <div style="max-width: 180px;">
                                <span style="color: #d9534f; font-weight: 500; font-size: 13px;">
                                    <?php echo htmlspecialchars($row['reason_category']); ?>
                                </span>
                            </div>
                        </td>

                        <td>
                            <?php if($row['evidence_file']): ?>
                                <a href="../uploads/evidence/<?php echo $row['evidence_file']; ?>" target="_blank" class="btn-small" style="background: #34495e; padding: 5px 10px;">
                                    View File
                                </a>
                            <?php else: ?>
                                <span style="color: #bbb; font-style: italic; font-size: 12px;">No file</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <a href="view_report_detail.php?id=<?php echo $row['id']; ?>" class="btn-small">Investigate</a>
                                <span class="status-indicator status-<?php echo $row['status']; ?>" style="font-size: 10px; text-align: center;">
                                    ● <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($reports_result) == 0): ?>
                        <tr><td colspan="5" class="empty-msg">No reports found matching these filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>