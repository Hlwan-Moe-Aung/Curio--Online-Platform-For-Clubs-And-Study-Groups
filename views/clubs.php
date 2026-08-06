<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

include('../includes/db.php');
include('../includes/functions.php');

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'Newest First';

// 1. Define the complex base query (including the joins for posts)
$user_email = $_SESSION['user'];

$base_sql = "SELECT c.*, 
            ((SELECT COUNT(*) FROM members WHERE community_id = c.id) + 1) as member_count,
            p.title AS post_title,
            p.content AS post_content,
            p.post_image AS latest_post_img,
            /* Membership Check Placeholders */
            (CASE WHEN c.leader_email = ? THEN 'leader' ELSE NULL END) as is_leader,
            (SELECT 'member' FROM members WHERE community_id = c.id AND user_email = ? LIMIT 1) as is_member
            FROM communities c 
            LEFT JOIN posts p ON p.id = (
                SELECT id FROM posts 
                WHERE community_id = c.id 
                AND type = 'public' 
                AND status = 'approved' 
                ORDER BY created_at DESC LIMIT 1
            )
            WHERE c.type = 'club' 
            AND c.status IN ('approved', 'disband_pending')";

// 2. Define Sorting logic
// To show Alphabetical A-Z AND Newest first:
if ($sort === 'Alphabetical (A-Z)') {
    $sort_sql = "ORDER BY c.community_name ASC, c.created_at DESC";
} elseif ($sort === 'Oldest First') {
    $sort_sql = "ORDER BY c.created_at ASC, c.community_name ASC";
} else {
    $sort_sql = "ORDER BY c.created_at DESC, c.community_name ASC";
}


$initial_params = [$user_email, $user_email];
$initial_types = "ss";

// 3. RUN THE FILTERED QUERY
// This replaces all your manual mysqli_prepare code
$result = build_filtered_query(
    $conn, 
    $base_sql, 
    ['c.category' => $category], 
    $search, 
    ['c.community_name', 'c.description'], // Using table alias 'c' to avoid ambiguity
    $sort_sql,
    $initial_params,
    $initial_types
);

$filter_config = [
    'action' => 'clubs.php',
    'placeholder' => 'Search clubs...',
    'dropdowns' => [
        'category' => [
            'physical' => 'Physical',
            'mental' => 'Mental', 
            'creative' => 'Creative', 
            'social' => 'Social', 
            'business' => 'Business'
        ],
        'sort' => [
            'Alphabetical (A-Z)' => 'Alphabetical (A-Z)',
            'Newest First' => 'Newest First',
            'Oldest First' => 'Oldest First'
        ]
    ]
];

$isLoggedIn = isset($_SESSION['user']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Curio | Clubs</title>
    <link rel="stylesheet" href="../assets/css/community.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/studyMaterials.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
</head>

<body>

<?php include '../includes/navbar.php'?>
<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'success') {
    $display_text = "Request submitted successfully!";
} elseif ($msg == 'You have left the community.') {
    $display_text = "You have left the community!";
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

        <div class="dashboard-header">
            <h1>Explore Student Clubs</h1>
        </div>

        <?php include '../includes/filter_component.php'; ?>

        <div class="club-list">
            <?php if(mysqli_num_rows($result) == 0): ?>
                <div class="empty-state" style="grid-column: span 2;"> 
                    <h2>No clubs available yet</h2>
                    <p>Wait for the admin to approve pending requests.</p>
                    <a href="../views/create_community.php" class="btn">Create Club</a>
                </div>
            <?php else: ?>
                <?php while($club = mysqli_fetch_assoc($result)): 
                    $role = $club['is_leader'] ?? $club['is_member'] ?? null;
                    
                    // Redirect logic: Dashboard for members/leaders, View for public
                    $target_url = ($role) ? "../views/club_dashboard.php?id=" : "../views/view_club.php?id=";
                ?>
                    <div class="club-card" onclick="window.location.href='<?php echo $target_url . $club['id']; ?>'">
                        
                        <div class="club-profile-wrapper">
                            <?php if(!empty($club['profile_pic'])): ?>
                                <img src="../uploads/<?php echo $club['profile_pic']; ?>" class="club-profile-img">
                            <?php else: ?>
                                <div class="club-profile-default">🏫</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($club['status'] === 'disband_pending'): ?>
                            <span class="badge" style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; position: absolute; top: 10px; right: 10px;">
                                ⚠️ DISBAND PENDING
                            </span>
                        <?php endif; ?>

                        <h3><?php echo htmlspecialchars($club['community_name']); ?></h3>
                        <div>
                            <p class="category-badge"><?php echo ucfirst(htmlspecialchars($club['category'])); ?></p>
                            
                            <?php if($role): ?>
                                <span class="sm-badge-small">
                                    <?php echo ($role === 'leader') ? '👑 Leader' : '👤 Member'; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="club-meta">
                            <span>👥 <?php echo $club['member_count'] ; ?> Members</span>
                            <span>📅 Created At: <?php echo date('M Y', strtotime($club['created_at'])); ?></span>
                        </div>

                        <p class="club-desc">
                            <?php 
                                $desc = htmlspecialchars($club['description']);
                                echo (strlen($desc) > 100) ? substr($desc, 0, 100) . '...' : $desc; 
                            ?>
                        </p>

                        <div class="pinned-post-section">
                            <div class="pinned-label" >
                                <span class="pin-icon">📌</span> Latest Public Post
                            </div>
                            <div class="pinned-content">
                                
                                <?php if(!empty($club['post_title'])): ?>
                                    <?php if(!empty($club['latest_post_img'])): ?>
                                        <img src="../uploads/<?php echo $club['latest_post_img']; ?>">
                                    <?php endif; ?>

                                    <div class="post-text-wrapper">
                                        <strong>
                                            <?php echo htmlspecialchars($club['post_title']); ?>
                                        </strong>
                                        <p>
                                            <?php 
                                                $p_content = htmlspecialchars($club['post_content']);
                                                // Increased character limit slightly for the larger area
                                                echo (strlen($p_content) > 90) ? substr($p_content, 0, 90) . '...' : $p_content; 
                                            ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <p style="color: #999; font-style: italic; font-size: 0.85rem;">No public announcements yet.</p>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div> 
<a href="../views/create_community.php"> <button class="fab" onclick="">+</button> </a>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>