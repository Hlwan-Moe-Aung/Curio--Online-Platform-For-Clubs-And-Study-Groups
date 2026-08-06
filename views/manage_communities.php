<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php'); // Required for build_filtered_query

// 1. Security Check: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

// 2. Initialize Filter Variables
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$category_filter = $_GET['category_filter'] ?? '';
$sort = $_GET['sort'] ?? 'Newest First';

// 3. Setup Sorting
if ($sort === 'Alphabetical (A-Z)') {
    $sort_sql = "ORDER BY community_name ASC";
} elseif ($sort === 'Oldest First') {
    $sort_sql = "ORDER BY created_at ASC";
} else {
    $sort_sql = "ORDER BY created_at DESC";
}
 
// 4. Base Query (Joins with users to get Leader Name)
$base_sql = "SELECT c.*, u.fullname as leader_name, u.id as leader_user_id 
             FROM communities c 
             JOIN users u ON c.leader_email = u.email 
             WHERE 1=1";

// 5. Execute Filtered Query
$result = build_filtered_query(
    $conn, 
    $base_sql, 
    ['status' => $status_filter, 'category' => $category_filter], 
    $search, 
    ['community_name', 'leader_email', 'leader_name' , 'c.id'], 
    $sort_sql
);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Communities | Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>"> 

    <style>
        .comm-pic {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ccc;
        }
        /* Status Borders */
        .border-approved { border-color: #2ecc71; } /* Green */
        .border-pending { border-color: #2c3e50; }  /* Black */
        .border-disband_pending { border-color: #e74c3c; } /* Red */

        .data-link { text-decoration: none; color: #1f3c88; font-weight: 600; }
        .data-link:hover { text-decoration: underline; }
        .sub-text { font-size: 12px; color: #666; margin-top: 2px; display: block; }
    </style>
</head>
<body>

<?php include'../includes/navbar.php'; ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'disbanded') {
    $display_text = "Community is disbanded successfully.";
} elseif ($msg == 'restored#Disband') {
    $display_text = "Declined disbandment successfully.";
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
            <h1>Manage Communities</h1>
            <p>View and manage all communities of StudyConnect.</p>
        </header>

        <?php 
        $filter_config = [
            'action' => 'manage_communities.php',
            'placeholder' => 'Search name, email or ID...',
            'dropdowns' => [
                'status_filter' => [
                    'pending' => 'pending',
                    'approved' => 'approved',
                    'disband_pending' => 'disband_pending'
                ],
                'category_filter' => [
                    'physical' => 'Physical',
                    'mental' => 'Mental', 
                    'creative' => 'Creative', 
                    'social' => 'Social', 
                    'business' => 'Business',
                    'math' => 'Math', 
                    'language' => 'Language', 
                    'science' => 'Science', 
                    'cs' => 'CS', 
                    'history' => 'History'
                ],
                'sort' => [
                    'Newest First' => 'Newest First',
                    'Oldest First' => 'Oldest First',
                    'Alphabetical (A-Z)' => 'Alphabetical (A-Z)'
                ]
            ]
        ];
        include '../includes/filter_component.php'; 
        ?>

        <section class="table-container">
            <table class="standard-table">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>ID</th>
                        <th>Community Name</th>
                        <th>Leader</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): 
                        $status_class = "border-" . $row['status'];
                        $profile_pic = !empty($row['profile_pic']) ? "../uploads/".$row['profile_pic'] : "../uploads/default_club.png";
                    ?>
                    <tr>
                        <td style="text-align: center;">
                            <img src="<?= $profile_pic ?>" class="comm-pic <?= $status_class ?>" alt="pic">
                        </td>
                        <td><?= $row['id'] ?></td>
                        <td>
                            <a href="<?= ($row['status'] === 'pending' ? 'view_request.php' : 'view_club.php') ?>?id=<?= $row['id'] ?>" target="_blank" class="data-link">
                                <?= h($row['community_name']) ?>
                            </a>
                            <span class="sub-text">Created: <?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                        </td>
                        <td>
                            <a href="view_user_detail.php?id=<?= $row['leader_user_id'] ?>" target="_blank" class="data-link">
                                <?= h($row['leader_name']) ?>
                            </a>
                            <span class="sub-text"><?= h($row['leader_email']) ?></span>
                        </td>
                        <td><span class="badge"><?= ucfirst(h($row['type'])) ?></span></td>
                        <td><?= h($row['category']) ?></td>
                        <td>
                            <a href="../views/view_community_detail.php?id=<?php echo $row['id']; ?>"   class="btn-small">Details</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>