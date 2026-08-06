<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php');

// Security: Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// --- TAB 1: Creation Requests Logic ---
$search_c = $_GET['search_c'] ?? '';
$sort_c = $_GET['sort'] ?? 'Newest First';

$sort_sql_c = ($sort_c === 'Oldest First') ? "ORDER BY created_at ASC" : "ORDER BY created_at DESC";
$base_sql_c = "SELECT * FROM communities WHERE status = 'pending'";

$creation_result = build_filtered_query(
    $conn, $base_sql_c, [], $search_c, 
    ['community_name', 'leader_email', 'leader_name'], 
    $sort_sql_c
);

// --- TAB 2: Disband Requests Logic ---
$search_d = $_GET['search_d'] ?? '';
$sort_d = $_GET['sort_d'] ?? 'Newest First';

$sort_sql_d = ($sort_d === 'Oldest First') ? "ORDER BY created_at ASC" : "ORDER BY created_at DESC";
$base_sql_d = "SELECT * FROM communities WHERE status = 'disband_pending'";

$disband_result = build_filtered_query(
    $conn, $base_sql_d, [], $search_d, 
    ['community_name', 'leader_email'], 
    $sort_sql_d
);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | SC</title>
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'Request_Approved') {
    $display_text = "Request approved successfully!";
} elseif ($msg == 'Request_Rejected') {
    $display_text = "Request rejected successfully!";
} elseif ($msg == 'disbanded') {
    $display_text = "You accepted the community disbandment.";
} elseif ($msg == 'restored#Disband') {
    $display_text = "You declined the community disbandment.";
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
            <h1>Admin Control Panel</h1>
            <p>Manage community lifecycles and requests.</p>
        </header>

        <div class="tab-system">
            <div class="tab-headers">
                <button class="tab-btn active" onclick="openTab(event, 'creation')">
                    Creation Requests 
                    <?php if(mysqli_num_rows($creation_result) > 0): ?>
                        <span class="badge-count"><?php echo mysqli_num_rows($creation_result); ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'disband')">
                    Disband Requests
                    <?php if(mysqli_num_rows($disband_result) > 0): ?>
                        <span class="badge-count" style="background: #333;"><?php echo mysqli_num_rows($disband_result); ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <div id="creation" class="tab-content active">
                <section class="admin-table-card">
                    <h3>New Community Applications</h3>
                    <?php 
                    // Prepare variables for the reusable component
                    $search = $search_c; 
                    $sort = $sort_c;
                    $filter_config = [
                        'action' => 'admin_dashboard.php#Creation',
                        'search_key' => 'search_c',
                        'placeholder' => 'Search name or email...',
                        'dropdowns' => [
                            'sort' => ['Newest First' => 'Newest',
                                         'Oldest First' => 'Oldest'
                            ],
                        ]
                    ];
                    include '../includes/filter_component.php'; 
                    ?>
                    <table class="standard-table">
                        <thead>
                            <tr>
                                <th>Community Name</th>
                                <th>Leader</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($creation_result)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['community_name']); ?></strong>
                                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                        <span style="color: #3a86ff; font-weight: 600;">
                                            <?php echo getRelativeTime($row['created_at']); ?>
                                        </span><br>
                                        <?php echo date('M d, Y | h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['leader_name']); ?></td>
                                <td><?php echo ucfirst($row['type']); ?></td>
                                <td><?php echo ucfirst($row['category']); ?></td>
                                <td>
                                    <a href="../views/view_request.php?id=<?php echo $row['id']; ?>" class="btn-small">Review</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($creation_result) == 0): ?>
                                <tr><td colspan="5" class="empty-msg">No pending creation requests.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </div>

            <div id="disband" class="tab-content">
                <section class="admin-table-card">
                    <h3 style="border-left-color: #ff4d4d;">Disbandment Requests</h3>
                    <?php 
                    $search = $search_d; 
                    $sort = $sort_d;
                    $filter_config = [
                        'action' => 'admin_dashboard.php#Disband',
                        'search_key' => 'search_d',
                        'placeholder' => 'Search community name...',
                        'dropdowns' => [
                            'sort' => ['Newest First' => 'Newest',
                                         'Oldest First' => 'Oldest'
                                        ]
                        ]
                    ];
                    include '../includes/filter_component.php'; 
                    ?>
                    <table class="standard-table">
                        <thead>
                            <tr>
                                <th>Community Name</th>
                                <th>Leader Email</th>
                                <th>Reasoning</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($disband_result)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['community_name']); ?></strong>
                                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                        <span style="color: #3a86ff; font-weight: 600;">
                                            <?php echo getRelativeTime($row['created_at']); ?>
                                        </span><br>
                                        <?php echo date('M d, Y | h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['leader_email']); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['disband_reason'], 0, 50)) . '...'; ?></td>
                                <td>
                                    <a href="../views/view_disband_request.php?id=<?php echo $row['id']; ?>" class="btn-small" style="background: #ff4d4d;">Review Disband</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($disband_result) == 0): ?>
                                <tr><td colspan="4" class="empty-msg">No disband requests at this time.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>
</body>
</html>