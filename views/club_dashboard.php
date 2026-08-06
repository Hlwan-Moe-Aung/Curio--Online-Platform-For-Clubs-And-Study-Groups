<?php
session_start();
include('../includes/db.php');
include('../includes/report_form.php');


// 1. Security Check: Is logged in?
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
$club_id = isset($_GET['id']) ? $_GET['id'] : 0;
 
// 2. Security Check: Is the user a member or the leader?
// SECURED: Using placeholders (?) for email and club_id
$check_sql = "SELECT c.*, 
             (SELECT COUNT(*) FROM members m WHERE m.community_id = c.id AND m.user_email = ?) as is_member
             FROM communities c 
             WHERE c.id = ? AND c.status IN ('approved', 'disband_pending')";

$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "si", $user_email, $club_id);
mysqli_stmt_execute($stmt);
$check_res = mysqli_stmt_get_result($stmt);
$club = mysqli_fetch_assoc($check_res);

if (!$club || (
    $club['is_member'] == 0 && 
    $club['leader_email'] !== $user_email && 
    $_SESSION['role'] !== 'admin' 
)) {
    header("Location: ../views/view_club.php?id=$club_id&error=not_a_member");
    exit();
}

// 3. Ban Check
// SECURED: Prepared Statement
$ban_stmt = mysqli_prepare($conn, "SELECT * FROM community_bans WHERE community_id = ? AND user_email = ? AND banned_until > NOW()");
mysqli_stmt_bind_param($ban_stmt, "is", $club_id, $user_email);
mysqli_stmt_execute($ban_stmt);
$ban_check_res = mysqli_stmt_get_result($ban_stmt);

if (mysqli_num_rows($ban_check_res) > 0) {
    $ban_info = mysqli_fetch_assoc($ban_check_res);
    die("You are banned from this group until " . $ban_info['banned_until'] . ". Reason: " . $ban_info['reason']);
}

$is_leader = ($club['leader_email'] === $user_email);

// 4. Fetch Announcements (Public Posts)
// SECURED: Prepared Statement. Fixed: Filtered by 'public' ONLY.
$ann_query = "SELECT * FROM posts WHERE community_id = ? AND status = 'approved' AND type = 'public' ORDER BY created_at DESC";
$ann_stmt = mysqli_prepare($conn, $ann_query);
mysqli_stmt_bind_param($ann_stmt, "i", $club_id);
mysqli_stmt_execute($ann_stmt);
$announcements = mysqli_stmt_get_result($ann_stmt);

// Fixed: post_count now ONLY counts announcements, matching your original logic
$post_count = mysqli_num_rows($announcements);

// 5. Fetch Private Discussions (With Likes and Comments)
// SECURED: Prepared Statement. 
// Fixed: Restored subqueries for like_count, comment_count, and user_liked.
$disc_query = "SELECT p.*, u.fullname,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_email = ?) as user_liked
               FROM posts p
               JOIN users u ON p.author_email = u.email
               WHERE p.community_id = ? 
               AND p.type = 'private' 
               AND p.status = 'approved' 
               ORDER BY p.created_at DESC";

$disc_stmt = mysqli_prepare($conn, $disc_query);
mysqli_stmt_bind_param($disc_stmt, "si", $user_email, $club_id);
mysqli_stmt_execute($disc_stmt);
$discussions = mysqli_stmt_get_result($disc_stmt);


renderReportModal($_SESSION['user']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['community_name']); ?> | Dashboard</title>
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/studyMaterials.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'deleted') {
    $display_text = "Post deleted successfully";
} elseif ($msg == 'reported') {
    $display_text = "Report received. Thank you!";
} elseif ($msg == 'updated') {
    $display_text = "Post updated successfully";
} elseif ($msg == 'pending') {
    $display_text = "Your post is submitted. Wait for Leader's approval";
} elseif ($msg == 'published') {
    $display_text = "Your post is Published  successfully";
} elseif ($msg == 'post_deleted') {
    $display_text = "Your Deleted your post";
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
            </div>
        </div>
    <?php endif; ?> 

    <div class="club-banner">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
            <div>
                <div class="sm-badge"><?php echo ucfirst($club['category']); ?></div>
                <h1 style="margin: 10px 0;"><?php echo htmlspecialchars($club['community_name']); ?></h1>
                <p style="margin: 0;">💬 Discussions</p>
            </div>
            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                <?php if($is_leader): ?>
                    <a href="../views/manage_group.php?id=<?php echo $club_id; ?>" class="btn" style="background: white; color: #1f3c88;">Manage ⚙️</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="main-feed">
            <div class="feed-card announcements-slider-container">
                <h3>📢 Announcements</h3>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
                
                <div class="slider-wrapper">
                    <div class="slider-track" id="sliderTrack">
                        <?php if($post_count > 0): ?>
                            <?php while($post = mysqli_fetch_assoc($announcements)): ?>
                                <div class="slide">
                                    <div class="slide-image-container">
                                        <?php if(!empty($post['post_image'])): ?>
                                            <img src="../uploads/<?php echo $post['post_image']; ?>" class="slide-img" onclick="viewFullImage(this.src)">
                                        <?php else: ?>
                                            <div class="no-img-placeholder">📢</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="slide-content">
                                        <a href="view_post.php?id=<?php echo $post['id']; ?>" style="text-decoration: none; color: inherit;">
                                        <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                        </a>
                                        <small>📅 <?php echo date('M d, Y', strtotime($post['created_at'])); ?></small>
                                        <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="slide no-posts">
                                <p>No announcements yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="slider-dots">
                    <?php for($i=0; $i < $post_count; $i++): ?>
                        <span class="dot <?php echo ($i==0)?'active':''; ?>" onclick="goToSlide(<?php echo $i; ?>)"></span>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="feed-card">
                <h3>💬 Private Discussion</h3>
                <div class="discussion-feed">
                    <?php if(mysqli_num_rows($discussions) > 0): ?>
                        <?php while($post = mysqli_fetch_assoc($discussions)): ?>
                            <div class="discussion-post">
                                <div class="post-header">
                                    <a href="view_post.php?id=<?php echo $post['id']; ?>" style="text-decoration: none; color: inherit;">
                                        <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                        </a>
                                    <small>📅 <?php echo date('M d, Y', strtotime($post['created_at'])); ?> • ⏰ <?php echo date('h:i A', strtotime($post['created_at'])); ?></small>
                                    <div class="post-author">By: <strong><?php echo htmlspecialchars($post['fullname']); ?></strong></div>
                                </div>

                                <div class="post-media">
                                    <?php if(!empty($post['post_image'])): ?>
                                        <img src="../uploads/<?php echo $post['post_image']; ?>" class="discussion-img" onclick="viewFullImage(this.src)">
                                    <?php endif; ?>
                                </div>

                                <div class="post-content">
                                    <div class="text-limit" id="text-<?php echo $post['id']; ?>">
                                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                    </div>
                                    <?php if(strlen($post['content']) > 200): ?>
                                        <button class="see-more-btn" onclick="toggleText(<?php echo $post['id']; ?>)">See more...</button>
                                    <?php endif; ?>
                                </div>

                                <div class="post-actions">
                                    <button class="action-btn like-btn <?php echo ($post['user_liked'] > 0) ? 'liked' : ''; ?>" 
                                            onclick="toggleLike(this, <?php echo $post['id']; ?>)">
                                        👍 <span class="count"><?php echo $post['like_count']; ?></span> Likes
                                    </button>

                                    <button class="action-btn" onclick="window.location.href='../views/view_post.php?id=<?php echo $post['id']; ?>'">
                                        💬 <span><?php echo $post['comment_count']; ?></span> Comments
                                    </button>

                                    <button class="action-btn" onclick="copyPostLink(<?php echo $post['id']; ?>)">
                                        🔗 Share
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align:center; color:#888; padding:20px;">No discussions yet. Start one by clicking the + button!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="club-sidebar">
            <div class="feed-card">
                <h4>Leader</h4>
                <p>👤 <?php echo htmlspecialchars($club['leader_name']); ?> <span class="leader-badge">LEADER</span></p>
            </div>

            <div class="feed-card">
                <h4>Actions</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;"><a href="../views/members_list.php?id=<?php echo $club_id; ?>" style="text-decoration: none; color: #3a86ff;">👥 View Members</a></li>
                    <?php if (isset($club['type']) && $club['type'] === 'study_group'): ?>
                        <li style="margin-bottom: 10px;">
                            <a href="../views/studyMaterials.php?id=<?php echo $club_id; ?>" style="text-decoration: none; color: #3a86ff;">
                                📁 Study Materials
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if(!$is_leader): ?>
                        <li>
                            <a href="javascript:void(0)" 
                               style="text-decoration: none; color: #dc3545;" 
                               onclick="openLeaveModal()">
                               🚪 Leave Club
                            </a>
                        </li>
                    <?php endif; ?>
                    <li style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee;"><a href="javascript:void(0)" onclick="openReportModal('<?php echo $club_id; ?>', 'community')" style="text-decoration: none; color: #d9534f; font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">🚩 Report Community</a> </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<button class="fab" onclick="openPostModal()">+</button>

<div id="postModal" class="modal-overlay">
    <div class="modal-content">
        <button type="button" class="btn-back" onclick="closePostModal()">← Back to Dashboard</button>
        <div class="modal-header">
            <h3>Create Private Discussion</h3>
        </div>
        
        <div class="form-container">
            <form action="../api/post_handler.php" method="POST" enctype="multipart/form-data" class="modern-form">
                <input type="hidden" name="group_id" value="<?php echo $club_id; ?>">
                <input type="hidden" name="post_visibility" value="private">

                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required placeholder="What's on your mind?">
                </div>

                <div class="form-group">
                    <label for="content">Context</label>
                    <textarea id="content" name="content" rows="5" required placeholder="Provide more details..."></textarea>
                </div>

                <div class="form-group">
                    <label for="post_image">Attach Image</label>
                    <div class="file-input-wrapper">
                        <div id="fileUploadArea">
                            <div class="drop-zone" onclick="document.getElementById('post_image').click()">
                                <p class="drop-text" id="postDropText">No file chosen</p>
                                <input type="file" id="post_image" name="post_image" accept="image/*" style="display:none;" 
                                       onchange="document.getElementById('postDropText').innerText = this.files[0].name" />
                            </div>
                        </div>
                        <span class="file-custom"></span>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Submit for Approval</span>
                    <i class="icon-send"></i> 
                </button>
            </form>
        </div>
    </div>
</div>

<div id="leaveClubModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button type="button" class="btn-back" onclick="closeLeaveModal()">← Cancel</button>
        <div class="modal-header">
            <h3>Leave Community</h3>
        </div>
        <div class="form-container">
            <form action="../api/leave_handler.php" method="POST" class="modern-form">
                <input type="hidden" name="community_id" value="<?php echo $club_id; ?>">
                
                <p>Are you sure you want to leave <strong><?php echo htmlspecialchars($club['community_name']); ?></strong>?</p>
                
                <div class="form-group">
                    <label>Optional: Leave a message for the leader</label>
                    <textarea name="leave_message" rows="3" placeholder="Why are you leaving? (Optional)"></textarea>
                </div>

                <button type="submit" name="confirm_leave" class="btn-submit" style="background: #dc3545;">
                    Confirm Leave
                </button>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>