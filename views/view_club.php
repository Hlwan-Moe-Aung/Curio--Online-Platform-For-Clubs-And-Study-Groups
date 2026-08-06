<?php
session_start();
include('../includes/db.php');

// 1. Validate Access and ID
if (!isset($_GET['id'])) {
    header("Location: ../views/clubs.php");
    exit();
} 

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$club_id = $_GET['id'];
$user_email = $_SESSION['user'];

// 2. Fetch Club Details, Member Count, and Membership Status using Prepared Statements
$query = "SELECT c.*, 
          ((SELECT COUNT(*) FROM members WHERE community_id = c.id) +1 ) as member_count,
          (SELECT COUNT(*) FROM members WHERE community_id = c.id AND user_email = ?) as is_member,
          (SELECT status FROM membership_requests WHERE community_id = c.id AND user_email = ? AND status = 'pending' LIMIT 1) as request_status
          FROM communities c 
          WHERE c.id = ? AND c.status IN ('approved', 'disband_pending')";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sss", $user_email, $user_email, $club_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$club = mysqli_fetch_assoc($result);

if (!$club) {
    die("Club not found or not yet approved.");
}

// 3. Check if user is the leader
$is_leader = ($user_email === $club['leader_email']);

// 4. Fetch All Public Approved Posts using Prepared Statements
$posts_query = "SELECT * FROM posts 
                WHERE community_id = ? 
                AND type = 'public' 
                AND status = 'approved' 
                ORDER BY created_at DESC";

$p_stmt = mysqli_prepare($conn, $posts_query);
mysqli_stmt_bind_param($p_stmt, "s", $club_id);
mysqli_stmt_execute($p_stmt);
$public_posts = mysqli_stmt_get_result($p_stmt);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['community_name']); ?> | Curio</title>
    <link rel="stylesheet" href="../assets/css/community.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'request_sent') {
    $display_text = "Request sent successfully";
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
    <?php if ($club['status'] === 'disband_pending'): ?>
        <div class="disband-banner">
            <div class="banner-content">
                <span class="banner-icon">⚠️</span>
                <div class="banner-text">
                    <strong>Disbandment Request Pending</strong>
                    <p>According to the request sent by Leader of this community, the administrator has been notified.</p>
                </div>
                <a href="contact_admin.php" class="banner-btn">Contact Admin</a>
            </div>
        </div>
    <?php endif; ?>
    <div class="club-view-container">

        <div class="club-hero-card">
            <div class="club-main-info">
                <span class="badge">
                    <?php echo strtoupper($club['type']); ?>
                </span>
                <h1><?php echo htmlspecialchars($club['community_name']); ?></h1>
                <p><?php echo nl2br(htmlspecialchars($club['description'])); ?></p>
                
                <hr>
                
                <h3>🎯 Our Purpose</h3>
                <p><?php echo nl2br(htmlspecialchars($club['purpose'])); ?></p>

                <div class="action-area">
                    <?php if (!$user_email): ?>
                        <p><a href="../public/login.php">Login</a> to join this community.</p>

                    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <div class="success-msg" style="text-align: left; border-left-color: #3a86ff; background: #ebf4ff;">
                        🛡️ <strong>Admin Access Mode</strong>
                        <p style="font-size: 13px; margin: 5px 0;">You have administrative privileges to view and manage all communities.</p>
                        <a href="../views/club_dashboard.php?id=<?php echo $club['id']; ?>" class="btn-small" style="background: #3a86ff;">Enter Club Dashboard</a>
                        <?php if ($club['status'] === 'disband_pending'): ?>
                            <a href="../views/view_disband_request.php?id=<?php echo $club['id']; ?>" class="btn-small" style="background: #e74c3c;">Review Disband Request</a>
                        <?php endif; ?>
                    </div>

                    <?php elseif ($is_leader): ?>
                        <div class="success-msg" style="text-align: left;">
                            👑 You are the Leader of this group.
                            <br><br>
                            <a href="../views/manage_group.php?id=<?php echo $club['id']; ?>" class="btn-small" style="background: #222;">Go to Management Page</a>
                            <a href="../views/club_dashboard.php?id=<?php echo $club['id']; ?>" class="btn-small">Enter Club</a>
                        </div>
                    <?php elseif ($club['is_member'] > 0): ?>
                        <div class="success-msg" style="text-align: left;">
                            ✅ You are a member!
                            <br><br>
                            <a href="../views/club_dashboard.php?id=<?php echo $club['id']; ?>" class="btn-small">Enter Dashboard</a>
                        </div>
                    <?php elseif ($club['request_status'] == 'pending'): ?>
                        <div class="pending-badge">
                            ⏳ Your request to join is pending approval from the leader.
                        </div>
                    <?php else: ?>
                        <div class="join-box">
                            <h3>Join this Community</h3>
                            <p>Send a request to the leader to become a member.</p>
                            <form action="../api/join_handler.php" method="POST">
                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                <textarea name="appeal" class="appeal-box" rows="3" placeholder="Write a short message to the leader why you want to join... (Optional)"></textarea>
                                <button type="submit" class="btn">Send Join Request</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="club-sidebar-info">
                <div class="stat-item">
                    <strong>Leader</strong>
                    <span >👤 <?php echo htmlspecialchars($club['leader_name']); ?></span>
                </div>
                <div class="stat-item">
                    <strong>Category</strong>
                    <span>📁 <?php echo ucfirst(htmlspecialchars($club['category'])); ?></span>
                </div>
                <div class="stat-item">
                    <strong>Members</strong>
                    <span>👥 <?php echo $club['member_count']; ?> students</span>
                </div>
                <div class="stat-item">
                    <strong>Founded</strong>
                    <span>📅 <?php echo date('M d, Y', strtotime($club['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="public-posts">
            <h2> 📢 Public Announcements </h2>

            <?php if(mysqli_num_rows($public_posts) > 0): ?>
                <?php while($post = mysqli_fetch_assoc($public_posts)): ?>
                    <div class="post-card">
                        <?php if(!empty($post['post_image'])): ?>
                            <div class="post-image-aside">
                                <img src="../uploads/<?php echo $post['post_image']; ?>">
                            </div>
                        <?php else: ?>
                            <div class="post-image-aside-empty">
                                📢
                            </div>
                        <?php endif; ?>

                        <div class="post-body-main">
                            <div class="post-header">
                                <h3>
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </h3>
                                <small>
                                    <span>📅</span> <?php echo date('M d, Y', strtotime($post['created_at'])); ?> 
                                    <span style="margin-left: 10px;">⏰</span> <?php echo date('h:i A', strtotime($post['created_at'])); ?>
                                </small>
                            </div>

                            <div class="post-content">
                                <?php 
                                    $text = htmlspecialchars($post['content']);
                                    echo nl2br($text); 
                                ?>
                            </div>
                            
                            <div class="post-footer">
                                <span>Public Announcement</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="post-card-empty">
                    <p>No announcements have been published yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="../assets/js/myscript.js"></script>
<script src="../assets/js/toast.js"></script>

</body>
</html>