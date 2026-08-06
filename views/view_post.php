<?php
session_start();
include('../includes/db.php');
include('../includes/report_form.php');

if (!isset($_SESSION['user']) || !isset($_GET['id'])) {
    header("Location: ../views/dashboard.php");
    exit();
}

// We use the raw ID; the prepared statement will handle the safety
$post_id = $_GET['id'];
$user_email = $_SESSION['user'];

// 1. Fetch Post Details (including like status for the button)
// SECURED: Using placeholders (?) to prevent SQL injection in the main query and subqueries
$post_query = "SELECT p.*, u.fullname, c.community_name, c.leader_email,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_email = ?) as user_liked
               FROM posts p 
               JOIN users u ON p.author_email = u.email 
               JOIN communities c ON p.community_id = c.id
               WHERE p.id = ?";

$stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($stmt, "si", $user_email, $post_id);
mysqli_stmt_execute($stmt);
$post_res = mysqli_stmt_get_result($stmt);
$post = mysqli_fetch_assoc($post_res);

if (!$post) { die("Post not found."); }

// 2. Fetch Comments
// SECURED: Prepared Statement for the comments list
$comments_query = "SELECT pc.*, u.fullname FROM post_comments pc JOIN users u ON pc.user_email = u.email WHERE pc.post_id = ? ORDER BY pc.created_at ASC";
$c_stmt = mysqli_prepare($conn, $comments_query);
mysqli_stmt_bind_param($c_stmt, "i", $post_id);
mysqli_stmt_execute($c_stmt);
$comments = mysqli_stmt_get_result($c_stmt);

$club_id = $post['community_id'];
$club = [
    'leader_email' => $post['leader_email'],
    'community_id' => $post['community_id']
];

$is_author = ($post['author_email'] === $_SESSION['user']);
$is_leader_or_admin = (($_SESSION['role'] ?? '') === 'admin' || $post['leader_email'] === $_SESSION['user']);

renderReportModal($_SESSION['user'], $club_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/studyMaterials.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">

</head>
<body>
    <div class="post-view-container">
        <a href="../views/club_dashboard.php?id=<?php echo $post['community_id']; ?>" class="btn-back">← Back</a>
        
        <div class="feed-card" style="margin-top: 20px;">
            <div class="sm-options" style="float: right;">
                <button class="sm-dots-btn" onclick="toggleMenu(event, <?php echo $post['id']; ?>)">⋮</button>
                
                <div id="menu-<?php echo $post['id']; ?>" class="sm-dropdown-content">
                    
                    <?php if ($is_author): ?>
                        <a href="javascript:void(0)" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($post)); ?>)">
                            ✏️ Edit Post
                        </a>
                    <?php endif; ?>

                    <?php if ($is_author || $is_leader_or_admin): ?>
                        <a href="javascript:void(0)" 
                           class="delete-link" 
                           style="color: #dc3545; text-decoration: none;"
                           onclick="if(confirm('Delete this post permanently?')) { document.getElementById('delete-post-form').submit(); }">
                           🗑️ Delete Post
                        </a>
                        
                        <form id="delete-post-form" action="../api/post_handler.php" method="POST" style="display:none;">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <input type="hidden" name="group_id" value="<?php echo $club_id; ?>">
                            <input type="hidden" name="action" value="delete"> </form>
                    <?php endif; ?>

                    <?php if (!$is_author): ?>
                        <a href="javascript:void(0)" 
                           style="color: #d9534f;" 
                           onclick="openReportModal(<?php echo $post['id']; ?>, 'post')">
                            🚩 Report Post
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <h2><?php echo htmlspecialchars($post['title']); ?></h2>
            <p>By <strong><?php echo htmlspecialchars($post['fullname']); ?></strong> on <?php echo date('M d, Y', strtotime($post['created_at'])); ?></p>
            
            <?php if($post['post_image']): ?>
                <div style="text-align: center; margin: 20px 0;">
                    <img src="../uploads/<?php echo $post['post_image']; ?>" style="max-width: 100%; border-radius: 10px;" onclick="viewFullImage(this.src)">
                </div>
            <?php endif; ?>

            <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>

            <div class="post-actions">
                <button class="action-btn like-btn <?php echo ($post['user_liked'] > 0) ? 'liked' : ''; ?>" 
                        onclick="toggleLike(this, <?php echo $post['id']; ?>)">
                    👍 <span class="count"><?php echo $post['like_count']; ?></span> Likes
                </button>

                <button class="action-btn" onclick="toggleComments()">
                    💬 <span><?php echo $post['comment_count']; ?></span> Comments
                </button>

                <button class="action-btn" onclick="copyPostLink(<?php echo $post['id']; ?>)">
                    🔗 Share
                </button>
            </div>

            <div id="commentSection" class="comment-section">
                <hr>
                <h3>Comments</h3>
                <div class="comment-list">
                    <?php while($c = mysqli_fetch_assoc($comments)): 
                        $comment_body = htmlspecialchars($c['comment_text']);
                        $limit = 150; // Character limit
                        $is_long = strlen($comment_body) > $limit;
                        $unique_id = $c['id']; // Using the comment ID
                    ?>
                        <div class="comment-item" style="padding: 10px 0; border-bottom: 1px solid #eee; position: relative;">
                            <small><strong><?php echo htmlspecialchars($c['fullname']); ?></strong> • <?php echo date('M d, H:i', strtotime($c['created_at'])); ?></small>

                            <?php 
                            // Debugging logic: Check both Admin role and Leader email
                            $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
                            $is_leader = (trim($club['leader_email']) === trim($_SESSION['user']));

                            if ($is_admin || $is_leader): ?>
                                <form action="../api/admin_action_handler.php" method="POST" style="position: absolute; right: 10px; top: 10px; display: inline;">
                                    <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                    
                                    <button type="submit" name="delete_comment" 
                                            onclick="return confirm('Delete this comment?')" 
                                            style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 16px; padding: 5px;" 
                                            title="Delete Comment">
                                        🗑️
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <div id="text-<?php echo $unique_id; ?>" class="comment-text-wrapper">
                                <?php echo nl2br($comment_body); ?>
                            </div>

                            <?php if ($is_long): ?>
                                <span class="see-more-btn" onclick="toggleText(<?php echo $unique_id; ?>)">See more...</span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <form action="../api/interaction_handler.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                    <textarea name="comment_text" rows="3" style="width:100%;" placeholder="Add a comment..." required></textarea>
                    <button type="submit" class="btn-submit">Post Comment</button>
                </form>
            </div>
        </div>
    </div>

<div id="postModal" class="modal-overlay">
    <div class="modal-content">
        <button type="button" class="btn-back" onclick="closePostModal()">← Back</button>
        <div class="modal-header">
            <h3 id="modalTitle">Edit Post</h3>
        </div>
        <div class="form-container">
            <form action="../api/post_handler.php" method="POST" enctype="multipart/form-data" class="modern-form">
                <input type="hidden" name="group_id" value="<?php echo $club_id; ?>">
                <input type="hidden" name="post_id" id="edit_post_id" value="">
                <input type="hidden" name="action" id="post_action" value="create">

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>

                <div class="form-group">
                    <label>Context</label>
                    <textarea name="content" id="edit_content" rows="5" required></textarea>
                </div>

                <div class="form-group">
                    <label>Update Image (Optional)</label>
                    <input type="file" name="post_image" accept="image/*">
                    <small id="imageNote" style="display:none; color: gray;">Leave empty to keep current image.</small>
                </div>

                <button type="submit" id="submitBtn" class="btn-submit">Submit for Approval</button>
            </form>
        </div>
    </div>
</div>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'reported') {
    $display_text = "Report received. Thank you!";
} elseif ($msg == 'updated') {
    $display_text = "Post Updated successfully";
} elseif ($msg == 'comment_deleted') {
    $display_text = "comment_deleted successfully";
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

<script>
    function toggleMenu(event, id) {
    event.stopPropagation();
    document.querySelectorAll('.sm-dropdown-content').forEach(el => {
        if(el.id !== 'menu-'+id) el.classList.remove('show');
    });
    document.getElementById('menu-' + id).classList.toggle('show');
}
</script>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>