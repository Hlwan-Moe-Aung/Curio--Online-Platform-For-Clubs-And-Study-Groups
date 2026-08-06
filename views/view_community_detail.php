<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Security: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

if (!isset($_GET['id'])) die("Community ID missing.");
$community_id = intval($_GET['id']);

// 1. Fetch Community Info + Leader Name
$stmt = $conn->prepare("SELECT c.*, u.fullname as leader_actual_name, u.id as leader_user_id 
                        FROM communities c 
                        LEFT JOIN users u ON c.leader_email = u.email 
                        WHERE c.id = ?");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$community = $stmt->get_result()->fetch_assoc();

if (!$community) die("Community not found.");

// 2. Fetch Members for the Leader-Change list (Excluding the current leader)
$member_stmt = $conn->prepare("SELECT u.fullname, u.email FROM members m 
                               JOIN users u ON m.user_email = u.email 
                               WHERE m.community_id = ? AND u.email != ?");
$current_leader_email = $community['leader_email']; 
$member_stmt->bind_param("is", $community_id, $current_leader_email);
$member_stmt->execute();
$members = $member_stmt->get_result();

// 3. Fetch Approved Members
$members_sql = "SELECT u.fullname, u.email, m.joined_at, u.id as user_id 
                FROM members m 
                JOIN users u ON m.user_email = u.email 
                WHERE m.community_id = ? 
                ORDER BY m.joined_at DESC";
$m_stmt = $conn->prepare($members_sql);
$m_stmt->bind_param("i", $community_id);
$m_stmt->execute();
$members_list = $m_stmt->get_result();

// 4. Fetch Posts
$posts_sql = "SELECT p.*, u.fullname 
              FROM posts p 
              JOIN users u ON p.author_email = u.email 
              WHERE p.community_id = ? 
              ORDER BY p.created_at DESC";
$p_stmt = $conn->prepare($posts_sql);
$p_stmt->bind_param("i", $community_id);
$p_stmt->execute();
$posts_list = $p_stmt->get_result();

// 5. Fetch Membership Requests
$req_sql = "SELECT r.*, u.fullname, u.id AS user_id
            FROM membership_requests r 
            JOIN users u ON r.user_email = u.email 
            WHERE r.community_id = ? AND r.status = 'pending' 
            ORDER BY r.created_at DESC";
$r_stmt = $conn->prepare($req_sql);
$r_stmt->bind_param("i", $community_id);
$r_stmt->execute();
$requests_list = $r_stmt->get_result();

// 6. Fetch Ban Records
$ban_sql = "SELECT b.*, u.fullname 
            FROM community_bans b 
            JOIN users u ON b.user_email = u.email 
            WHERE b.community_id = ? 
            ORDER BY b.created_at DESC";
$b_stmt = $conn->prepare($ban_sql);
$b_stmt->bind_param("i", $community_id);
$b_stmt->execute();
$bans_list = $b_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Community Details: <?= h($community['community_name']) ?></title>
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">

    <style>
        .detail-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item { border-bottom: 1px solid #f0f0f0; padding: 10px 0; position: relative; }
        .info-label { font-weight: bold; color: #555; display: block; font-size: 0.85rem; }
        .info-value { font-size: 1rem; color: #111; display: block; margin-top: 4px; }
        .sub-text { font-size: 0.8rem; color: #777; }
        .edit-icon { cursor: pointer; color: #1f3c88; font-size: 0.9rem; margin-left: 8px; }
        .edit-icon:hover { color: #e74c3c; }
        
        /* Modal Overlays */
        .modal-overlay { 
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; 
            width: 100%; height: 100%; background: rgba(0,0,0,0.5); 
            justify-content: center; align-items: center; 
        }
        .modal-box { background: white; padding: 25px; border-radius: 8px; width: 400px; max-height: 80vh; overflow-y: auto; }
        .member-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; }
        
        .profile-container { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .big-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #1f3c88; cursor: context-menu; }
        .data-link { text-decoration: none; color: #1f3c88; font-weight: 600; }
        .data-link:hover { text-decoration: underline; }
        .header-actions { display: flex; gap: 10px; align-items: center; margin-top: 10px; }
        .btn-inspect { background: #3498db; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }
        .btn-inspect:hover { background: #2980b9; }
        .btn-disband-trigger { background: #e74c3c; color: white; padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: bold; }
        .btn-disband-trigger:hover { background: #c0392b; }
    </style>
</head>
<body>

<?php include('../includes/navbar.php'); ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'updated') {
    $display_text = "Community data updated successfully!";
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
            <div>
                <h1>Community Management: <?= h($community['community_name']); ?></h1>
                <p>Created At: <?= formatDate($community['created_at']); ?></p>
            </div>
            
            <div class="header-actions">
                <?php if ($community['status'] === 'pending'): ?>
                    <a href="view_request.php?id=<?= $community_id ?>" class="btn-inspect" style="background: #f39c12;">
                        📝 View Creation Request
                    </a>

                <?php elseif ($community['status'] === 'disband_pending'): ?>
                    <a href="../public/view_club.php?id=<?= $community_id ?>" target="_blank" class="btn-inspect">
                        🔍 Inspect
                    </a>
                    <a href="view_disband_request.php?id=<?= $community_id ?>" class="btn-inspect" style="background: #e67e22;">
                        🛑 View Disband Request
                    </a>

                <?php else: ?>
                    <a href="../public/view_club.php?id=<?= $community_id ?>" target="_blank" class="btn-inspect">
                        🔍 Inspect Public View
                    </a>
                    <button type="button" class="btn-disband-trigger" onclick="openCommunityEditModal('forceDisbandModal')">
                        ⚠️ Force Disband
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-table-card">
            <h3>View & Edit Community Profile</h3>
            <div class="profile-container">
                <img src="<?= !empty($community['profile_pic']) ? '../uploads/'.$community['profile_pic'] : '../uploads/default_club.png' ?>" 
                     class="big-pic" id="communityPic" oncontextmenu="showCustomMenu(event)">
                
                <div>
                    <h2 style="margin:0;"><?= h($community['community_name']) ?> 
                        <span class="edit-icon" onclick="openCommunityEditModal('nameModal')">✏️</span>
                    </h2>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">ID</span>
                    <span class="info-value"><?= $community['id'] ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Current Leader
                        <span class="edit-icon" onclick="openCommunityEditModal('leaderModal')">✏️</span></span>
                    <span class="info-value">
                        <?= h($community['leader_actual_name'] ?? 'Unknown') ?> 
                    </span>
                    <span class="sub-text"><?= h($community['leader_email']) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Type & Category
                        <span class="edit-icon" onclick="openCommunityEditModal('typeModal')">✏️</span></span>
                    <span class="info-value">
                        <?= ucfirst($community['type']) ?> | <?= h($community['category']) ?>
                    </span>
                </div>

                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value" style="color: #e67e22; font-weight: bold;"><?= strtoupper($community['status']) ?></span>
                </div>

                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Description
                        <span class="edit-icon" onclick="openCommunityEditModal('descModal')">✏️</span>
                    </span>
                    <span class="info-value">
                        <?= nl2br(h($community['description'])) ?>
                    </span>
                </div>

                <div class="info-item">
                    <span class="info-label">Purpose (Read Only)</span>
                    <span class="info-value"><?= h($community['purpose']) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Appeal (Read Only)</span>
                    <span class="info-value"><?= h($community['appeal'] ?: 'N/A') ?></span>
                </div>

                <?php if($community['status'] == 'disband_pending'): ?>
                <div class="info-item">
                    <span class="info-label">Disband Reason</span>
                    <span class="info-value"><?= h($community['disband_reason']) ?></span>
                </div>
                <?php endif; ?>

                <div class="info-item">
                    <span class="info-label">Pending Leader (Read Only)</span>
                    <span class="info-value"><?= h($community['pending_leader'] ?: 'N/A') ?></span>
                </div>
            </div>
        </div>

        <div class="tab-system" style="margin-top: 30px;">
            <div class="tab-headers">
                <button class="tab-btn active" onclick="openTab(event, 'view_members')">View Members</button>
                <button class="tab-btn" onclick="openTab(event, 'view_posts')">View Posts</button>
                <button class="tab-btn" onclick="openTab(event, 'member_requests')">Member Requests</button>
                <button class="tab-btn" onclick="openTab(event, 'ban_record')">Ban Record</button>
            </div>

            <div id="view_members" class="tab-content active">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Email</th>
                            <th>Joined Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($m = $members_list->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($m['fullname']) ?></td>
                            <td><?= h($m['email']) ?></td>
                            <td><?= date('M d, Y', strtotime($m['joined_at'])) ?></td>
                            <td><a href="view_user_detail.php?id=<?= $m['user_id'] ?>" target="_blank" class="btn-small">View Profile</a></td>
                        </tr>
                        <?php endwhile; if($members_list->num_rows == 0) echo "<tr><td colspan='4' style='text-align:center;'>No Members found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <div id="view_posts" class="tab-content">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Author</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = $posts_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="view_user_detail.php?id=<?= $p['id'] ?>" target="_blank" class="data-link" ><?= h($p['fullname']) ?></a>
                            </td>
                            <td>
                                <?php $display_title = mb_strimwidth($p['title'], 0, 20, "...");?>
                                <strong title="<?= h($p['title']); ?>"> <?= h($display_title); ?>
                                </strong>
                            </td>
                            <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                            <td><a href="view_post.php?id=<?= $p['id'] ?>" target="_blank" class="btn-small">Read Post</a></td>
                        </tr>
                        <?php endwhile; if($posts_list->num_rows == 0) echo "<tr><td colspan='4' style='text-align:center;'>No Posts found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <div id="member_requests" class="tab-content">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Request Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($r = $requests_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="view_user_detail.php?id=<?= $r['user_id'] ?>" target="_blank" class="data-link">
                                    <?= h($r['fullname']) ?>
                                </a>
                            </td>
                            <td><?= h($r['user_email']) ?></td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td><span class="badge" style="background:#f1c40f; color:black;">Pending</span></td>
                        </tr>
                        <?php endwhile; if($requests_list->num_rows == 0) echo "<tr><td colspan='4' style='text-align:center;'>No pending requests.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <div id="ban_record" class="tab-content">
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Banned User</th>
                            <th>Reason</th>
                            <th>Banned At</th>
                            <th>Banned Until</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($b = $bans_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="view_user_detail.php?id=<?= $b['id'] ?>" target="_blank" class="data-link" ><?= h($b['fullname']) ?></a>
                            </td>
                            <td><?= h($b['reason']) ?></td>
                            <td><?= date('M d, Y', strtotime($b['created_at'])) ?></td>
                            <td><?= date('M d, Y', strtotime($b['banned_until'])) ?></td>
                        </tr>
                        <?php endwhile; if($bans_list->num_rows == 0) echo "<tr><td colspan='4' style='text-align:center;'>No ban records found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="nameModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Edit Community Name</h3>
        <form action="../api/admin_update_community_handler.php" method="POST">
            <input type="hidden" name="community_id" value="<?= $community_id ?>">
            <input type="text" name="community_name" class="form-control" value="<?= h($community['community_name']) ?>" required style="min-height: 30px; border-radius: 15px; padding: 10px;">
            <br><br>
            <button type="submit" name="update_name" class="btn-small">Update</button>
            <button type="button" onclick="closeCommunityEditModal('nameModal')" class="btn-small" style="background:#666">Cancel</button>
        </form>
    </div>
</div>

<div id="leaderModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Select New Leader</h3>
        <p class="sub-text">Only current members can be promoted to leader.</p>
        <div style="margin-top:15px;">
            <?php while($m = mysqli_fetch_assoc($members)): ?>
            <div class="member-item">
                <span><?= h($m['fullname']) ?> <br><small><?= h($m['email']) ?></small></span>
                <form action="../api/admin_update_community_handler.php" method="POST">
                    <input type="hidden" name="community_id" value="<?= $community_id ?>">
                    <input type="hidden" name="new_leader_email" value="<?= $m['email'] ?>">
                    <button type="submit" name="change_leader" class="btn-small" style="font-size:11px;">Select</button>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
        <button onclick="closeCommunityEditModal('leaderModal')" class="btn-small" style="margin-top:15px; width:100%;">Close</button>
    </div>
</div>

<div id="typeModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Update Category</h3>
        <p style="font-size: 0.9rem; color: #666; margin-bottom: 15px;">
            Note: The community <strong>Type</strong> cannot be changed once created.
        </p>

        <form action="../api/admin_update_community_handler.php" method="POST">
            <input type="hidden" name="community_id" value="<?= $community_id ?>">
            
            <input type="hidden" name="type" id="adminTypeFixed" value="<?= $community['type'] ?>">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; color: #333;">Community Type:</label>
                <div style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; color: #555;">
                    <?= $community['type'] === 'club' ? '🏅 Club' : '📚 Study Group' ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display:block; font-weight:bold; color: #333;">Category:</label>
                <select name="category" id="adminCategorySelect" style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 4px;">
                    </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" name="update_type" class="btn-small" style="flex:1;">Save Category</button>
                <button type="button" onclick="closeCommunityEditModal('typeModal')" class="btn-small" style="background:#666; color:white; flex:1;">Cancel</button>
            </div>
        </form>
    </div>
</div>


<div id="descModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Edit Description</h3>
        <form action="../api/admin_update_community_handler.php" method="POST">
            <input type="hidden" name="community_id" value="<?= $community_id ?>">
            <textarea name="description" rows="6" style="resize: none;width:100%"><?= h($community['description']) ?></textarea>
            <br><br>
            <button type="submit" name="update_desc" class="btn-small">Update Description</button>
            <button type="button" onclick="closeCommunityEditModal('descModal')" class="btn-small" style="background:#666">Cancel</button>
        </form>
    </div>
</div>

<div id="contextMenu" style="display:none; position:absolute; z-index:10001; background:white; border:1px solid #ccc; box-shadow: 2px 2px 5px rgba(0,0,0,0.2);">
    <ul style="list-style:none; margin:0; padding:5px; cursor:pointer;">
        <li onclick="deleteProfilePic()" style="color:red; padding:8px 15px; font-weight:bold;">Delete Image</li>
    </ul>
</div>

<div id="forceDisbandModal" class="modal-overlay">
    <div class="modal-box" style="border-top: 5px solid #e74c3c;">
        <h3>Admin Force Disband</h3>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
            You are about to permanently delete <strong><?= h($community['community_name']) ?></strong>. 
            This action cannot be undone.
        </p>
        
        <form action="../api/admin_action_handler.php" method="POST">
            <input type="hidden" name="group_id" value="<?= $community['id']; ?>">
            <input type="hidden" name="community_name" value="<?= h($community['community_name']); ?>">
            <input type="hidden" name="leader_email" value="<?= $community['leader_email']; ?>">
            <input type="hidden" name="is_force_disband" value="1">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Reason for Disbanding</label>
                <textarea name="admin_feedback" placeholder="Explain why this community is being disbanded (This will be logged/emailed)..." 
                          style="width: 100%; height: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" name="action" value="approve" class="btn-disband-trigger" 
                        style="flex: 1;" onclick="return confirm('FINAL WARNING: All posts, members, and data will be erased. Proceed?')">
                    Confirm Disband
                </button>
                <button type="button" onclick="closeCommunityEditModal('forceDisbandModal')" 
                        class="btn-small" style="background:#666; color: white; border: none; flex: 1; cursor: pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    // Ensure your modal functions are available if they aren't in myscript.js
    function openCommunityEditModal(id) {
        document.getElementById(id).style.display = 'flex';
    }
    function closeCommunityEditModal(id) {
        document.getElementById(id).style.display = 'none';
    }
    
    // Close modal if clicking outside the box
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    function initCategoryOptions() {
        const catSelect = document.getElementById('adminCategorySelect');
        const fixedType = document.getElementById('adminTypeFixed').value;
        const currentCategory = "<?= $community['category'] ?>";

        const options = {
            'club': ['physical', 'mental', 'creative', 'social', 'business'],
            'study_group': ['math', 'language', 'science', 'cs', 'history']
        };

        catSelect.innerHTML = '';

        if (options[fixedType]) {
            options[fixedType].forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                opt.innerHTML = cat.charAt(0).toUpperCase() + cat.slice(1);
                if (cat === currentCategory) opt.selected = true;
                catSelect.appendChild(opt);
            });
        }
    }

    // Run on page load
    document.addEventListener('DOMContentLoaded', initCategoryOptions);
</script>
<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>