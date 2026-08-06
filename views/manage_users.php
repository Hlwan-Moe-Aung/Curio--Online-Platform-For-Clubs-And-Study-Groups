<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php'); // Required for build_filtered_query

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

// 2. Initialize Filter Variables
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? ''; // Renamed to avoid 'role' conflict
$sort = $_GET['sort'] ?? 'Newest First';
 
// 3. Setup Sorting
if ($sort === 'Alphabetical (A-Z)') {
    $sort_sql = "ORDER BY fullname ASC";
} elseif ($sort === 'Oldest First') {
    $sort_sql = "ORDER BY created_at ASC";
} else {
    $sort_sql = "ORDER BY created_at DESC";
}

// 4. Base Query
$base_sql = "SELECT id, fullname, email, role, created_at, last_activity FROM users WHERE role = 'user'";
$result = build_filtered_query(
    $conn, 
    $base_sql, 
    [], // Empty array because we are hardcoding the 'user' role filter in $base_sql
    $search, 
    ['fullname', 'email', 'id'], 
    $sort_sql
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>"> 
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content" id="main">
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>User Management</h1>
            <p>View and manage all registered members of StudyConnect.</p>
        </header>

        <?php 
        $filter_config = [
            'action' => 'manage_users.php',
            'placeholder' => 'Search name, email or ID...',
            'dropdowns' => [
                'sort' => [
                    'Newest First' => 'Newest First',
                    'Oldest First' => 'Oldest First',
                    'Alphabetical (A-Z)' => 'Alphabetical (A-Z)'
                ]
            ]
        ];
        include '../includes/filter_component.php'; 
        ?>

        <section class="admin-table-card">
            <table class="standard-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Joined Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>
                            <?php if (isUserOnline($user['last_activity'])): ?>
                                <span class="status-badge online">● Online</span>
                            <?php else: ?>
                                <span class="status-badge offline">Offline</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <div style="font-size: 12px; color: #666;">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <a href="../views/view_user_detail.php?id=<?php echo $user['id']; ?>" class="btn-small">Details</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
</body>
</html>