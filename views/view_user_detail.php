<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/functions.php';

// Admin-only Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("User ID not provided.");
}

$user_id = intval($_GET['id']);

/* 1. FETCH BASIC USER INFO */
$stmt = $conn->prepare("SELECT id, fullname, email, role, last_activity, ban_until, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) die("User not found.");
$target_email = $user['email'];
$is_online = isUserOnline($user['last_activity']);

/* 2. FETCH ALL AFFILIATIONS (Where Leader OR Member) */
$stmt = $conn->prepare("
    SELECT 
        id, 
        community_name, 
        'leader' AS member_role, 
        created_at AS joined_at 
    FROM communities 
    WHERE leader_email = ? AND status = 'approved'

    UNION ALL

    -- Get groups where the user is a MEMBER
    SELECT 
        c.id, 
        c.community_name, 
        m.role AS member_role, 
        m.joined_at 
    FROM members m 
    JOIN communities c ON m.community_id = c.id 
    WHERE m.user_email = ?
    
    ORDER BY joined_at DESC
");

// Note: We bind the email twice because it's used in both parts of the UNION
$stmt->bind_param("ss", $target_email, $target_email);
$stmt->execute();
$communities = $stmt->get_result();

// 1. Community Creation Requests
$stmt_creation = $conn->prepare("SELECT id,community_name, status, created_at FROM communities WHERE leader_email = ?");
$stmt_creation->bind_param("s", $target_email);
$stmt_creation->execute();
$creation_requests = $stmt_creation->get_result();

// 2. Member Join Requests
// Note: In your SQL, membership_requests uses 'user_email'
$stmt_join = $conn->prepare("SELECT c.community_name, mr.status, mr.created_at 
                             FROM membership_requests mr 
                             JOIN communities c ON mr.community_id = c.id 
                             WHERE mr.user_email = ?");
$stmt_join->bind_param("s", $target_email);
$stmt_join->execute();
$join_requests = $stmt_join->get_result();

// 3. Post Creation Requests
$stmt_posts = $conn->prepare("SELECT id, title, type, status, created_at FROM posts WHERE author_email = ?");
$stmt_posts->bind_param("s", $target_email);
$stmt_posts->execute();
$post_requests = $stmt_posts->get_result();



?>

<link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'User_updated_successfully') {
    $display_text = "User updated successfully";
} elseif ($msg == 'User_removed_from_group') {
    $display_text = "User removed from group";
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
            <h1>User Management: <?= h($user['fullname']); ?></h1>
            <p>
                Account Created: <?= formatDate($user['created_at']); ?> | 
                Status: <?= $is_online ? '<span class="status-badge online">● Online</span>' : '<span class="status-badge offline">Offline</span>' ?>
            </p>
        </div>

        <div class="admin-table-card mb-4">
            <h3>Edit User & Platform Access</h3>
            <form id="adminUpdateForm" action="../api/member_actions_handler.php" method="POST">
                <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                <input type="hidden" name="action" value="admin_update_user_profile">
                
                <div class="row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 1;">
                        <label>Full Name</label>
                        <input type="text" name="new_name" class="filter-input" style="width:95%; margin-top: 10px"  value="<?= h($user['fullname']); ?>">
                    </div>
                    <div style="flex: 1;">
                        <label>Email Address (Read Only)</label>
                        <input type="email" class="filter-input" style="width:95%; background: #f0f0f0; cursor: not-allowed; margin-top: 10px" value="<?= h($user['email']); ?>" readonly>
                    </div>
                </div>

                <div class="row" style="display: flex; gap: 20px; margin-top: 15px;">
                    <div style="width: 49%;">
                        <label>Reset Password (Leave blank to keep current)</label>
                        <input type="password" name="reset_password" class="filter-input" style="width:95%;margin-top: 10px" placeholder="Enter new password">
                    </div>
                    
                </div>

                <div class="row" style="margin-top: 15px;">
                    <label>Ban Login Until</label><br>
                    <input type="datetime-local" name="ban_until" class="filter-input" style="width:46%;margin-top: 10px" value="<?= $user['ban_until'] ? date('Y-m-d\TH:i', strtotime($user['ban_until'])) : ''; ?>">
                </div>
                
                <button type="button" onclick="confirmUpdate()" class="btn" style="margin-top:20px;">Save Changes</button>
            </form>
        </div>

        <div class="admin-table-card mb-4">
            <h3>Affiliations (Clubs & Study Groups)</h3>
            <table class="standard-table">
                <thead>
                    <tr>
                        <th>Community Name</th>
                        <th>Role</th>
                        <th>Joined Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $communities->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <a href="../views/view_club.php?id=<?= $row['id']; ?>" target="_blank" style="text-decoration: none; color: #1f3c88; font-weight: 600;">
                                <?= h($row['community_name']); ?>
                            </a>
                        </td>
                        <td><span class="status-badge"><?= ucfirst($row['member_role']); ?></span></td>
                        <td><?= date('M d, Y h:i A', strtotime($row['joined_at'])); ?></td>
                        <td>
                            <?php if ($row['member_role'] !== 'leader'): ?>
                            <form action="../api/member_actions_handler.php" method="POST" style="display:inline;" onsubmit="return confirmRemoval('<?= h($row['community_name']); ?>')">
                                <input type="hidden" name="action" value="admin_remove_user">
                                <input type="hidden" name="target_email" value="<?= $target_email; ?>">
                                <input type="hidden" name="group_id" value="<?= $row['id']; ?>">
                                <input type="hidden" name="user_id" value="<?= $user_id; ?>">
                                <button type="submit" class="btn-danger btn ">Remove</button>
                            </form>
                            <?php else: ?>
                                <small>Leader cannot be removed</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-system">
            <div class="tab-headers">
                <button class="tab-btn active" onclick="openTab(event, 'Creation')">Community Creation</button>
                <button class="tab-btn" onclick="openTab(event, 'Join')">Member Join</button>
                <button class="tab-btn" onclick="openTab(event, 'Posts')">Post Creation</button>
            </div>

            <div id="Creation" class="tab-content active">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Community Name</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($req = $creation_requests->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= h($req['community_name']); ?></strong></td>
                                <td><span class="status-badge"><?= h($req['status']); ?></span></td>
                                <td><?= date('M d, Y h:i A', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <a href="view_request.php?id=<?= $req['id']; ?>" class="btn-small" target="_blank">View</a>
                                </td>
                            </tr>
                        <?php endwhile; if($creation_requests->num_rows == 0) echo "<tr><td colspan='4' style='text-align:center;'>No community creation requests found for this user.</td></tr>"; ?>
                        </tbody>
                    </table>
            </div>

            <div id="Join" class="tab-content">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Community Name</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($req = $join_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?= h($req['community_name']); ?></td>
                                <td><span class="status-badge"><?= h($req['status']); ?></span></td>
                                <td><?= date('M d, Y h:i A', strtotime($req['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; if($join_requests->num_rows == 0) echo "<tr><td colspan='3' style='text-align:center;'>No membership requests found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <div id="Posts" class="tab-content">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Post Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($req = $post_requests->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php $display_title = mb_strimwidth($req['title'], 0, 20, "...");?>
                                    <strong title="<?= h($req['title']); ?>"> <?= h($display_title); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="status-badge <?= h($req['type']); ?>">
                                        <?= ucfirst(h($req['type'])); ?>
                                    </span>
                                </td>
                                <td><span class="status-badge"><?= h($req['status']); ?></span></td>
                                <td><?= date('M d, Y h:i A', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <a href="view_post.php?id=<?= $req['id']; ?>" class="btn-small" target="_blank">View</a>
                                </td>
                            </tr>
                            <?php endwhile; if($post_requests->num_rows == 0) echo "<tr><td colspan='5' style='text-align:center;'>No post creation history found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="confirmModal" style="display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:white; width:350px; margin:15% auto; padding:20px; border-radius:10px; text-align:center;">
            <h4>Confirm Update</h4>
            <p>Please wait <span id="timer">5</span> seconds to confirm changes.</p>
            <div style="display:flex; justify-content:center; gap:10px;">
                <button id="yesBtn" disabled class="btn-small" style="background:#ccc; cursor:not-allowed;">Yes</button>
                <button onclick="closeModal()" class="btn-small" style="background:#ff4d4d; color:white;">No</button>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>
