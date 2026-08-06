<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];

// 1. Initialize variables (The component needs these variable names to match the dropdown keys)
$search = $_GET['search'] ?? ''; // Component expects this
$folder = $_GET['folder'] ?? 'received';
$period = $_GET['period'] ?? 'all';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$category = $type; // Map type to category so "Clear All" link shows up correctly

// 2. Define Filter Configuration
$filter_config = [
    'action' => 'notifications.php',
    'placeholder' => 'Search messages...',
    'dropdowns' => [
        'folder' => ['received' => '📥 Received', 'sent' => '📤 Sent'],
        'period' => ['all' => 'All Time', 'today' => 'Today', 'week' => 'This Week'],
        'type'   => ['creation' => 'Creation', 'membership' => 'Membership', 'report' => 'Reports'],
        'status' => ['unread' => 'Unread', 'read' => 'Read']
    ]
];

// 3. Build Query using your existing logic
$params = [];
$types = "";

if ($folder === 'sent') {
    $base_sql = "SELECT * FROM notifications WHERE sender_email = ?";
} else {
    $base_sql = "SELECT * FROM notifications WHERE receiver_email = ?";
}

$params[] = $user_email;
$types .= "s";

// Reusing your time filter logic
if ($period === 'today') {
    $base_sql .= " AND DATE(created_at) = CURDATE()";
} elseif ($period === 'week') {
    $base_sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// 4. Use the helper function for the remaining dynamic filters (Search, Type, Status)
$result = build_filtered_query(
    $conn, 
    $base_sql, 
    ['type' => $type, 'status' => $status], 
    $search, 
    ['title', 'message'], 
    "ORDER BY created_at DESC",
    $params, // Pass your existing email parameter
    $types   // Pass your existing "s" type
);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inbox | Curio</title>
    <link rel="stylesheet" href="../assets/css/notifications.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<div class="main-content" id="main">

    <div class="dashboard-container">
        <div class="dashboard-header" style="display:flex; justify-content: space-between;">
            <h1><?php echo ucfirst($folder); ?></h1>
        </div>

        <?php include '../includes/filter_component.php'; ?>
        <div class="noti-list">
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <a href="../views/view_notification.php?id=<?php echo $row['id']; ?>" class="noti-item-link">
                        <div class="noti-item <?php echo ($row['status'] == 'unread') ? 'unread' : 'read'; ?>">
                            <div class="noti-icon">
                                <?php 
                                    if($row['type'] == 'creation') echo '🏗️';
                                    elseif($row['type'] == 'membership') echo '🤝';
                                    elseif($row['type'] == 'report') echo '🚩';
                                    else echo '📧';
                                ?>
                            </div>
                            <div class="noti-content">
                                <div class="noti-title-row">
                                    <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                                    <span class="noti-time"><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></span>
                                </div>
                                <p class="noti-preview">
                                    <?php echo substr(htmlspecialchars($row['message']), 0, 100); ?>...
                                </p>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="padding-left: 20px">
                    <p>No messages in your <?php echo $folder; ?> folder.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>