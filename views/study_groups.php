<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

include('../includes/db.php');
include('../includes/functions.php'); 

// 1. Initialize variables
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'Newest First';
// 2. Updated SQL for Study Groups with Membership Check
$user_email = $_SESSION['user']; 

$base_sql = "SELECT c.*, 
            ((SELECT COUNT(*) FROM members WHERE community_id = c.id) + 1) as member_count,
            p.title AS post_title,
            p.content AS post_content,
            p.post_image AS latest_post_img,
            /* Check if the user is the leader */
            (CASE WHEN c.leader_email = ? THEN 'leader' ELSE NULL END) as is_leader,
            /* Check if the user is a member */
            (SELECT 'member' FROM members WHERE community_id = c.id AND user_email = ? LIMIT 1) as is_member
            FROM communities c 
            LEFT JOIN posts p ON p.id = (
                SELECT id FROM posts 
                WHERE community_id = c.id 
                AND type = 'public' 
                AND status = 'approved' 
                ORDER BY created_at DESC LIMIT 1
            )
            WHERE c.type = 'study_group' AND c.status = 'approved'";

// 3. Apply Reusable Sorting Logic (Alphabetical + Newest)
if ($sort === 'Alphabetical (A-Z)') {
    $sort_sql = "ORDER BY c.community_name ASC, c.created_at DESC";
} elseif ($sort === 'Oldest First') {
    $sort_sql = "ORDER BY c.created_at ASC, c.community_name ASC";
} else {
    $sort_sql = "ORDER BY c.created_at DESC, c.community_name ASC";
}

$initial_params = [$user_email, $user_email]; // Supply data for the 2 '?' in $base_sql
$initial_types = "ss";

// 4. Run the Reusable Filtered Query
$result = build_filtered_query(
    $conn, 
    $base_sql, 
    ['c.category' => $category], // Using c.category specifies the table column
    $search, 
    ['c.community_name', 'c.description'], 
    $sort_sql,
    $initial_params, // Pass the user email here
    $initial_types   // Pass the types here
);

$filter_config = [
    'action' => 'study_groups.php',
    'placeholder' => 'Search study groups...',
    'dropdowns' => [
        'category' => [
            'math' => 'Math', 
            'language' => 'Language', 
            'science' => 'Science', 
            'cs' => 'CS', 
            'history' => 'History'
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
    <title>Curio | Study Groups</title>
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
            <h1>Find Study Groups</h1>
        </div>

        <?php include '../includes/filter_component.php'; ?>
        
        <div class="club-list">
            <?php if(mysqli_num_rows($result) == 0): ?>
                <div class="empty-state" style="grid-column: span 2;"> 
                    <h2>No study groups found</h2>
                    <p>Try adjusting your filters or create your own group!</p>
                    <a href="../views/create_community.php" class="btn">Create Study Group</a>
                </div>
            <?php else: ?>
                <?php while($group = mysqli_fetch_assoc($result)): 
                    // Determine the user's role
                    $role = $group['is_leader'] ?? $group['is_member'] ?? null;
                    
                    // Redirect logic: if they have a role, go to club_dashboard, otherwise view_club
                    $target_url = ($role) ? "../views/club_dashboard.php?id=" : "../views/view_club.php?id=";
                ?>
                    <div class="club-card" onclick="window.location.href='<?php echo $target_url . $group['id']; ?>'">
                        
                        <div class="club-profile-wrapper">
                            <?php if(!empty($group['profile_pic'])): ?>
                                <img src="../uploads/<?php echo $group['profile_pic']; ?>" class="club-profile-img">
                            <?php else: ?>
                                <div class="club-profile-default">📚</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($group['status'] === 'disband_pending'): ?>
                            <span class="badge" style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; position: absolute; top: 10px; right: 10px;">
                                ⚠️ DISBAND PENDING
                            </span>
                        <?php endif; ?>

                        <h3><?php echo htmlspecialchars($group['community_name']); ?></h3>

                        <div>
                            <p class="category-badge"><?php echo ucfirst(htmlspecialchars($group['category'])); ?></p>
                            
                            <?php if($role): ?>
                                <span class="sm-badge-small">
                                    <?php echo ($role === 'leader') ? '👑 Leader' : '👤 Member'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="club-meta">
                            <span>👥 <?php echo $group['member_count']; ?> Members</span>
                            <span>📅 Created at: <?php echo date('M Y', strtotime($group['created_at'])); ?></span>
                        </div>

                        <p class="club-desc">
                            <?php 
                                $desc = htmlspecialchars($group['description']);
                                echo (strlen($desc) > 100) ? substr($desc, 0, 100) . '...' : $desc; 
                            ?>
                        </p>

                        <div class="pinned-post-section">
                            <div class="pinned-label">
                                <span class="pin-icon">📌</span> Latest Announcement
                            </div>
                            <div class="pinned-content">
                                
                                <?php if(!empty($group['post_title'])): ?>
                                    <?php if(!empty($group['latest_post_img'])): ?>
                                        <img src="../uploads/<?php echo $group['latest_post_img']; ?>">
                                    <?php endif; ?>

                                    <div class="post-text-wrapper">
                                        <strong>
                                            <?php echo htmlspecialchars($group['post_title']); ?>
                                        </strong>
                                        <p>
                                            <?php 
                                                $p_content = htmlspecialchars($group['post_content']);
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

<a href="../views/create_community.php">
<button class="fab" onclick="">+</button>
</a>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>