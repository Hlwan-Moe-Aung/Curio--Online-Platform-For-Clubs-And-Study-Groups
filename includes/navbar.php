<?php 
// 1. Logic to identify the active page
include ('db.php');
$current_page = basename($_SERVER['PHP_SELF']); 
$isLoggedIn = isset($_SESSION['user']); 
$role = $_SESSION['role'] ?? 'user'; 
$email = $_SESSION['user'] ?? null; 

// 2. Notification logic
$unread_count = 0; 
if ($isLoggedIn) { 
    $unread_query = "SELECT COUNT(*) as total FROM notifications WHERE receiver_email = '$email' AND status = 'unread'"; 
    $unread_result = mysqli_query($conn, $unread_query); 
    if ($unread_result) { 
        $unread_data = mysqli_fetch_assoc($unread_result); 
        $unread_count = $unread_data['total']; 
    } 
} 

$user_clubs = [];
$user_study_groups = [];

if ($isLoggedIn && $role !== 'admin') {
    $comm_query = "SELECT DISTINCT c.id, c.community_name, c.type 
                   FROM communities c
                   LEFT JOIN members m ON c.id = m.community_id
                   WHERE (m.user_email = '$email' OR c.leader_email = '$email') 
                   AND c.status = 'approved'
                   ORDER BY c.community_name ASC";
    
    $comm_result = mysqli_query($conn, $comm_query);
    
    if ($comm_result) {
        while ($row = mysqli_fetch_assoc($comm_result)) {
            // Ensure 'id' exists before assignment
            if (isset($row['id'])) {
                if ($row['type'] === 'club') {
                    $user_clubs[] = $row;
                } else {
                    $user_study_groups[] = $row;
                }
            }
        }
    } else {
        // Log error for debugging
        error_log("Navbar Query Failed: " . mysqli_error($conn));
    }
}

// 3. Fetch User Details for the floating profile
$fullname = "User";
if ($isLoggedIn) {
    $user_info_query = "SELECT fullname FROM users WHERE email = '$email'";
    $user_info_result = mysqli_query($conn, $user_info_query);
    if ($user_info_result && $row = mysqli_fetch_assoc($user_info_result)) {
        $fullname = $row['fullname'];
    }
}

function renderCommunityList($list, $type_label) {
    $limit = 5;
    $count = count($list);
    
    if ($count == 0) return "";

    echo '<div class="community-group" id="' . strtolower($type_label) . '-list">';
    
    foreach ($list as $index => $comm) {
        $hidden_class = ($index >= $limit) ? 'hidden-item' : '';
        $active_class = (isset($_GET['id']) && $_GET['id'] == $comm['id']) ? 'active' : '';
        
        echo '<a href="../views/club_dashboard.php?id=' . $comm['id'] . '" class="nav-item sub-item ' . $hidden_class . ' ' . $active_class . '" title="' . htmlspecialchars($comm['community_name']) . '">
                <span class="comm-icon">' . ($type_label == "Club" ? "🏫" : "📚") . '</span>
                <span class="comm-name">' . htmlspecialchars($comm['community_name']) . '</span>
              </a>';
    }

    if ($count > $limit) {
        echo '<button class="see-more-btn" onclick="toggleSeeMore(this)">See more...</button>';
    }
    echo '</div><hr class="sidebar-divider">';
}

?>

<!DOCTYPE html> 
<html lang="en"> 
<head> 
    <meta charset="UTF-8"> 
    <title>SC | Student Collaboration</title> 
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>"> 
</head> 
<body> 

<div class="sidebar" id="sidebar"> 

    <?php if ($isLoggedIn && $current_page !== 'dashboard.php'): ?>
        <div class="floating-profile-container">
            <a href="../views/dashboard.php" class="floating-profile-link">
                <div class="profile-tooltip">
                    <span class="tooltip-name"><?php echo htmlspecialchars($fullname); ?></span>
                    <span class="tooltip-email"><?php echo htmlspecialchars($email); ?></span>
                </div>
                <div class="profile-icon-circle">
                    👤
                </div>
            </a>
        </div>
    <?php endif; ?>

    <button class="collapse-btn" title="Toggle Sidebar"> 
        ☰ <span>Minimize</span> 
    </button> 

    <?php if($isLoggedIn): ?> 
        <?php if($role === 'admin'): ?>
            <a href="../views/statistics.php" class="nav-item <?php echo ($current_page == 'statistics.php') ? 'active' : ''; ?>">
                📈 <span>Insights</span>
            </a> 

            <a href="../views/admin_dashboard.php" class="nav-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>"> 
                📊 <span>Manage Requests</span> 
            </a> 

            <a href="../views/manage_users.php" class="nav-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"> 
                👥 <span>Manage Users</span> 
            </a> 

            <a href="../views/manage_communities.php" class="nav-item <?php echo ($current_page == 'manage_communities.php') ? 'active' : ''; ?>"> 
                🏗️ <span>Manage Communities</span> 
            </a> 

            <a href="../views/manage_reports.php" class="nav-item <?php echo ($current_page == 'manage_reports.php') ? 'active' : ''; ?>"> 
                🚩 <span>Manage Reports</span> 
            </a> 

        <?php else: ?> 
            <div class="nav-tab-header">
                <a href="../views/clubs.php" class="nav-item <?php echo ($current_page == 'clubs.php') ? 'active' : ''; ?>"style="width: 150px;"> 
                    🏫 <span>Clubs</span> 
                </a> 
                <button class="section-toggle-btn" onclick="toggleSection('club-list', this)">▲</button>
            </div>

            <div id="club-list">
                <?php renderCommunityList($user_clubs, "Club"); ?>
            </div>

            <div class="nav-tab-header">
                <a href="../views/study_groups.php" class="nav-item <?php echo ($current_page == 'study_groups.php') ? 'active' : ''; ?>" style="width: 150px;"> 
                    📚 <span>Study Groups</span> 
                </a> 
                <button class="section-toggle-btn" onclick="toggleSection('study-group-list', this)">▲</button>
            </div>

            <div id="study-group-list">
                <?php renderCommunityList($user_study_groups, "Study Group"); ?>
            </div>
        <?php endif; ?> 
        <a href="../views/dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"> 
            👤 <span>Profile</span> 
        </a> 

        <a href="../views/notifications.php" class="nav-item <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>"> 
            <div class="nav-icon <?php echo ($unread_count > 0) ? 'noti-glow' : ''; ?>"> 
                🔔 
            </div> 
            <span>Notifications (<?php echo $unread_count; ?>)</span> 
        </a> 

        <a href="../public/logout.php" class="nav-item"> 
            🚪 <span>Logout</span> 
        </a> 

    <?php else: ?> 
        <a href="../public/login.php" class="nav-item <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>"> 
            🔑 <span>Login</span> 
        </a> 
        <a href="../public/signup.php" class="nav-item <?php echo ($current_page == 'signup.php') ? 'active' : ''; ?>"> 
            ✍️ <span>Sign Up</span> 
        </a> 
    <?php endif; ?> 

</div> 

<script src="../assets/js/navbar.js?v=<?php echo time(); ?>"></script>

</body> 
</html>