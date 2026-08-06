<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php');

// 1. Security Check
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
// Validate that ID exists; no need to escape here as we will use a prepared statement
$group_id = isset($_GET['id']) ? $_GET['id'] : 0;
 
// 2. Verify Ownership (Leader Only) using Prepared Statements
$query = "SELECT * FROM communities WHERE id = ? AND leader_email = ? AND status IN ('approved', 'disband_pending')";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "is", $group_id, $user_email); // "i" for integer ID, "s" for email string
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($result);

if (!$group) {
    die("Unauthorized: You are not the leader of this group or it doesn't exist.");
}

// 3. Fetch Specific Data for THIS group only
// Fetch Member Requests (Status: pending) using Prepared Statements
$req_stmt = mysqli_prepare($conn, "SELECT * FROM membership_requests WHERE community_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($req_stmt, "i", $group_id);
mysqli_stmt_execute($req_stmt);
$member_requests = mysqli_stmt_get_result($req_stmt);

// Fetch Private Post Requests using Prepared Statements
$post_stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE community_id = ? AND status = 'pending_approval'");
mysqli_stmt_bind_param($post_stmt, "i", $group_id);
mysqli_stmt_execute($post_stmt);
$post_requests = mysqli_stmt_get_result($post_stmt);

// Fetch Members and Ban status using Prepared Statements
$members_query = "SELECT m.*, u.fullname, u.email, b.reason, b.banned_until 
                  FROM members m 
                  JOIN users u ON m.user_email = u.email 
                  LEFT JOIN community_bans b ON m.user_email = b.user_email AND m.community_id = b.community_id
                  WHERE m.community_id = ?";
$m_stmt = mysqli_prepare($conn, $members_query);
mysqli_stmt_bind_param($m_stmt, "i", $group_id);
mysqli_stmt_execute($m_stmt);
$current_members = mysqli_stmt_get_result($m_stmt);

 // Fetch pending study materials
$material_stmt = mysqli_prepare($conn, "SELECT * FROM studymaterial WHERE community_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($material_stmt, "i", $group_id);
mysqli_stmt_execute($material_stmt);
$material_requests = mysqli_stmt_get_result($material_stmt);

 ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage | <?php echo htmlspecialchars($group['community_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/manage_group.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
     <script src="../assets/js/file_upload_model.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
</head>
<body>

<?php include '../includes/navbar.php' ?>

<div class="main-content" id="main">
    <?php if ($group['status'] === 'disband_pending'): ?>
        <div class="disband-banner">
            <div class="banner-content">
                <span class="banner-icon">⚠️</span>
                <div class="banner-text">
                    <strong>Disbandment Request Pending</strong>
                    <p>According to the request sent by Leader of this community, the administrator has been notified.</p>
                <!-- <a href="contact_admin.php" class="banner-btn">Contact Admin</a> -->
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <a href="../views/club_dashboard.php?id=<?php echo $group_id; ?>" style="text-decoration: none; color: #3a86ff; font-weight: 600;">← Back to Dashboard</a>

        <div class="dashboard-header">
            <h1>⚙️ Managing: <?php echo htmlspecialchars($group['community_name']); ?>
                <?php if ($group['status'] === 'disband_pending'): ?>
                    <button type="button" class="btn" style="background-color: #95a5a6; float: right; cursor: not-allowed;" disabled>
                        ⏳ Disband Requested
                    </button>
                <?php else: ?>
                    <button type="button" class="btn" onclick="openDisbandModal()" style="background-color: #e74c3c; float: right;">
                        🚩 Request Disband
                    </button>
                <?php endif; ?>
            </h1>

            
        </div>

        <div class="feed-card" style="margin: 60px 0;">
            <h3>Club Settings & Profile</h3>
            <div class="settings-grid">
                <div class="profile-upload-section">
                     <label style="display: block; margin-bottom: 15px; font-weight: bold;">Club Profile Picture</label>
                    <div class="current-pic">
                        <?php if(!empty($group['profile_pic'])): ?>
                            <img src="../uploads/<?php echo $group['profile_pic']; ?>" onclick="viewFullImage(this.src)">
                        <?php else: ?>
                            <div class="current-pic-empty">🏫</div>
                        <?php endif; ?>
                    </div>
                    <form action="../api/update_club_handler.php" method="POST" enctype="multipart/form-data" class="modern-form">
                        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                         <div id="fileUploadArea">
                            <label>Choose Profile Picture for the Community</label>
                            <div class="drop-zone" >
                                <p class="drop-text">No file chosen</p>
                                <input type="file" name="profile_pic" accept="image/*" required style="display:none;" />
                            </div>
                        </div>
                         <button type="submit" name="update_pic" class="btn-submit">Update Photo</button>
                    </form>
                </div>

                <div class="profile-details-display">
                    <form method="POST" class="modern-form" action="../api/update_club_handler.php" onsubmit="saveOldData(
                            '<?php echo addslashes($group['community_name']); ?>', 
                            '<?php echo addslashes($group['category']); ?>', 
                            '<?php echo addslashes(str_replace(["\r", "\n"], ' ', $group['description'])); ?>', 
                            '<?php echo addslashes(str_replace(["\r", "\n"], ' ', $group['purpose'])); ?>'
                        )">
                            
                        <input type="hidden" name="community_id" value="<?php echo $group['id']; ?>">
                        <div class="form-group">
                            <label>Club Name</label>
                            <input type="text" name="community_name" value="<?php echo h($group['community_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required class="filter-input">
                                <?php 
                                // Define options based on the community type from the $group data
                                if ($group['type'] === 'club') {
                                    $options = ['physical', 'mental', 'creative', 'social', 'business'];
                                } else {
                                    $options = ['math', 'language', 'science', 'cs', 'history'];
                                }
                                foreach ($options as $opt): 
                                    $selected = ($group['category'] === $opt) ? 'selected' : '';
                                ?>
                                    <option value="<?= $opt ?>" <?= $selected ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="4" required><?php echo h($group['description']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Purpose</label>
                            <textarea name="purpose" rows="4" required><?php echo h($group['purpose']); ?></textarea>
                        </div>
                        <button type="submit" name="update_community" class="btn-submit">Update Club Information</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-system">
            <div class="tab-headers">
                <button class="tab-btn active" onclick="openTab(event, 'requests')">
                    👥 Member Requests <span class="badge-count"><?php echo mysqli_num_rows($member_requests); ?></span>
                </button>
                
                <button class="tab-btn" onclick="openTab(event, 'posts')">
                    📝 Post Approvals <span class="badge-count"><?php echo mysqli_num_rows($post_requests); ?></span>
                </button>
                
                <button class="tab-btn" onclick="openTab(event, 'create')">📣 Announce</button>
                
                <button class="tab-btn" onclick="openTab(event, 'members')">🛠️ Manage Members</button>

                <?php if ($group['type'] !== 'club'): ?>
                    <button class="tab-btn" onclick="openTab(event, 'materials')">
                        📁 Study Materials <span class="badge-count"><?php echo mysqli_num_rows($material_requests); ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <div id="requests" class="tab-content" style="display: block;">
                <h3>Pending Member Requests</h3>
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Appeal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($member_requests) > 0): ?>
                            <?php while($req = mysqli_fetch_assoc($member_requests)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($req['user_email']); ?></td>
                                    <td><em style="color:#666;"><?php echo !empty($req['appeal']) ? '"'.htmlspecialchars($req['appeal']).'"' : 'No message.'; ?></em></td>
                                    <td>
                                        <a href="../api/request_handler.php?id=<?php echo $req['id']; ?>&action=approve" class="btn-approve" >Approve</a>
                                        <a href="../api/request_handler.php?id=<?php echo $req['id']; ?>&action=reject" class="btn-reject" onclick="return confirm('Reject this user?')">Reject</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-standard-table">No pending requests.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="posts" class="tab-content">
                <h3>Member Post Creation Requests</h3>
                <p>Private posts submitted by members that need your review.</p>
                
                <div class="requests-list">
                    <?php if (mysqli_num_rows($post_requests) > 0): ?>
                        <?php while($post = mysqli_fetch_assoc($post_requests)): ?>
                            <div class="action-card">
                                <div>
                                    <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                    <p>
                                            Sent by: <strong><?php echo htmlspecialchars($post['author_email']); ?></strong> on 
                                            <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                    </p>
                                    <div class="action-card-image">
                                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                            <?php if($post['post_image']): ?>
                                                <br><small>🖼️ Image attached: <a href="../uploads/<?php echo $post['post_image']; ?>" target="_blank">View Image</a></small>
                                            <?php endif; ?>
                                    </div>
                                </div>
                                    <div class="action-card-request">
                                        <a href="../api/request_handler.php?type=post&id=<?php echo $post['id']; ?>&action=approve&club_id=<?php echo $group_id; ?>" 
                                           class="btn-approve">Approve</a>
                                        
                                        <a href="../api/request_handler.php?type=post&id=<?php echo $post['id']; ?>&action=reject&club_id=<?php echo $group_id; ?>" 
                                           class="btn-reject"
                                           onclick="return confirm('Are you sure you want to reject this post?')">Reject</a>
                                    </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-msg">No pending post requests.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="create" class="tab-content">
                
                <div class="form-container">
                    <h3>Create New Post</h3>
                    <form action="../api/post_handler.php" method="POST" enctype="multipart/form-data" class="modern-form">
                        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                        
                        <input type="hidden" name="post_visibility" value="public">

                        <div class="form-group">
                            <label>Post Title</label>
                            <input type="text" name="title" required>
                        </div>

                        <div class="form-group">
                            <label>Attach Image (Optional)</label>
                             <div id="fileUploadArea">
                                <div class="drop-zone" onclick="document.querySelector('.filter-input').click()">
                                    <p class="drop-text">No file chosen</p>
                                    <input type="file" name="post_image" accept="image/*" class="filter-input" style="display:none;" onchange="this.previousElementSibling.innerText = this.files[0].name" />
                                </div>
                            </div>
                            <small style="color: #666; display: block;">Supported formats: JPG, PNG, GIF</small>
                         </div>
                        
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Publish Post</button>
                    </form>
                </div>
            </div>
            <div id="members" class="tab-content">
                <h3>Current Members</h3>
                <table class="standard-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role/status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($current_members) > 0): ?>
                            <?php while($member = mysqli_fetch_assoc($current_members)): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($member['fullname']); ?><br>
                                        <small><?php echo htmlspecialchars($member['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if($member['role'] == 'banned'): ?>
                                            <span style="color:red; font-weight:bold;">BANNED</span><br>
                                            <small>Reason: <?php echo htmlspecialchars($member['reason']); ?></small>
                                        <?php else: ?>
                                            <?php echo ucfirst($member['role']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($member['role'] == 'banned'): ?>
                                            <form action="../api/member_actions_handler.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                                <input type="hidden" name="target_email" value="<?php echo $member['email']; ?>">
                                                <input type="hidden" name="action" value="unban">
                                                <button type="submit" class="btn-small" style="background:#27ae60;">Unban</button>
                                            </form>
                                        <?php else: ?>
                                            <button onclick="showBanModal('<?php echo $member['email']; ?>')" class="btn-small" style="background:#e67e22;">Ban</button>
                                            
                                            <form action="../api/member_actions_handler.php" method="POST" style="display:inline;" onsubmit="return confirm('Remove this member?');">
                                                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                                <input type="hidden" name="target_email" value="<?php echo $member['email']; ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <button type="submit" class="btn-small" style="background:#e74c3c;">Remove</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (empty($group['pending_leader'])): ?>
                                            <form action="../api/member_actions_handler.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                                <input type="hidden" name="target_email" value="<?php echo $member['email']; ?>">
                                                <button type="submit" name="action" value="initiate_promote" class="btn-small" style="background:#27ae60" onclick="return confirm('Note: You will be demoted to member status once they accept. Proceed?')">Promote to Leader</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge">Promotion Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-standard-table" style="text-align: center; padding: 20px; color: #666;">
                                    No members found in this community.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

             <?php 
                // Only show this entire section if the group type is NOT a club
                if ($group['type'] !== 'club'): 
                ?>
                    <div id="materials" class="tab-content">
                        <h3>Study Material Approval Requests</h3>
                        <p>Resources and files uploaded by members that require your approval before becoming visible.</p>
                        
                        <div class="requests-list">
                            <?php if (mysqli_num_rows($material_requests) > 0): ?>
                                <?php while($material = mysqli_fetch_assoc($material_requests)): ?>
                                    <div class="action-card">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                                <span class="sm-badge-small" style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                                    <?php echo strtoupper(htmlspecialchars($material['type'])); ?>
                                                </span>
                                                <span class="sm-badge-small" style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 11px;">
                                                    <?php echo ucfirst(htmlspecialchars($material['category'])); ?>
                                                </span>
                                            </div>
                                            <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                            <p>
                                                Uploaded by: <strong><?php echo htmlspecialchars($material['uploaded_by']); ?></strong> on 
                                                <?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?>
                                            </p>
                                            
                                            <div class="action-card-image" style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 10px;">
                                                <?php if(!empty($material['description'])): ?>
                                                    <p style="margin-bottom: 8px;"><?php echo nl2br(htmlspecialchars($material['description'])); ?></p>
                                                <?php endif; ?>
                                                
                                                <?php if($material['file_path']): ?>
                                                    <div style="display: flex; align-items: center; gap: 8px; color: #2563eb; font-weight: 500;">
                                                        <span>📄</span>
                                                        <a href="<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" style="color: inherit; text-decoration: none;">
                                                            View Attachment: <?php echo htmlspecialchars($material['original_name']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="action-card-request" style="display: flex; gap: 8px;">
                                            <form method="POST" action="../api/study_material_approve.php" style="margin: 0;">
                                                <input type="hidden" name="id" value="<?php echo (int)$material['id']; ?>">
                                                <input type="hidden" name="action" value="approved">
                                                <input type="hidden" name="club_id" value="<?php echo $group_id; ?>"> 
                                                <button type="submit" class="btn-approve" style="cursor:pointer; border:none; padding: 8px 16px; border-radius: 4px;">
                                                    Approve
                                                </button>
                                            </form>

                                            <form method="POST" action="../api/study_material_approve.php" style="margin: 0;" 
                                                  onsubmit="return confirm('Reject this material? The file will not be visible to the group.');">
                                                <input type="hidden" name="id" value="<?php echo (int)$material['id']; ?>">
                                                <input type="hidden" name="action" value="rejected">
                                                <input type="hidden" name="club_id" value="<?php echo $group_id; ?>">
                                                <button type="submit" class="btn-reject" style="cursor:pointer; border:none; padding: 8px 16px; border-radius: 4px;">
                                                    Decline
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-msg">No pending material requests.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

             <div id="banModal" class="banmodal">
                <h4>Ban Member</h4>
                <form action="../api/member_actions_handler.php" method="POST">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    <input type="hidden" name="target_email" id="ban_email">
                    <label>Reason:</label><br>
                    <textarea name="reason" required style="width:100%"></textarea><br><br>
                    <label>Duration (Days):</label><br>
                    <input type="number" name="duration" min="1" value="7" required><br><br>
                    <button type="submit" name="action" value="ban" class="btn">Confirm Ban</button>
                    <button type="button" onclick="document.getElementById('banModal').style.display='none'" class="btn-delete">Cancel</button>
                </form>
            </div>
            <div id="disbandModal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <button type="button" class="btn-back" onclick="closeDisbandModal()">← Back</button>
                    <div class="modal-header">
                        <h3 style="color: #e74c3c;">Request Group Disbandment
                            <p style="font-size: 13px; color: #666;">This action will notify the Administrator to remove this community.</p>
                        </h3>
                    </div>

                    <form action="../api/request_handler.php" method="POST">
                        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                        <input type="hidden" name="community_name" value="<?php echo htmlspecialchars($group['community_name']); ?>">

                        <div class="form-group">
                            <label>Reason for Disband</label>
                            <textarea 
                                name="disband_reason" 
                                rows="4" 
                                required 
                                placeholder="Please explain why you want to disband this community..."
                                style="width: 100%; resize: none; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                            ></textarea>
                        </div>

                        <div class="form-group" style="display: flex; align-items: flex-start; gap: 10px; margin-top: 15px;">
                            <input type="checkbox" name="acknowledge" id="ack" required style="margin-top: 4px;">
                            <label for="ack" style="font-size: 13px; font-weight: normal;">
                                I understand that this request is final and I take full responsibility for disbanding this community.
                            </label>
                        </div>

                        <button type="submit" name="request_disband" class="btn-submit" style="background: #e74c3c; margin-top: 20px;">
                            Confirm Request
                        </button>
                    </form>
                </div>
            </div>
        </div> 
    </div>
</div>
<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';
$show_undo = false;

if ($msg == 'success') {
    $display_text = "Club Info updated successfully";
} elseif ($msg == 'Material approved') {
    $display_text = "✅ Material approved successfully";
} elseif ($msg == 'disband_sent') {
    $display_text = "Disband request sent successfully";
} elseif ($msg == 'approved') {
    $display_text = "✅ User has been approved";
} elseif ($msg == 'rejected') {
    $display_text = "❌ Request rejected successfully.";
} elseif ($msg == 'post_approved') {
    $display_text = "✅ Post has been approved";
} elseif ($msg == 'post_rejected') {
    $display_text = "❌ Post rejected successfully.";
} elseif ($msg == 'published') {
    $display_text = "✅ Announcement made successfully";
} elseif ($msg == 'Member_removed_and_notified') {
    $display_text = "Member removed and notified";
} elseif ($msg == 'Member_banned') {
    $display_text = "Member banned successfully";
} elseif ($msg == 'Member_unbanned') {
    $display_text = "Member unbanned successfully";
} elseif ($msg == 'Promotion_sent') {
    $display_text = "Promotion sent successfully";
} elseif ($msg == 'Material rejected') {
    $display_text = "❌ Material rejected successfully";
} elseif ($msg == 'Profile picture updated!') {
    $display_text = "✅ Profile picture updated! successfully";
}
?>



<?php if ($display_text): ?>
    <div id="yt-toast" class="toast">
        <div class="toast-content">
            <?= htmlspecialchars($display_text) ?>
        </div>
        <div class="toast-actions">
            <?php if ($show_undo): ?>
                <button class="toast-action-btn" onclick="undoClubProfileUpdate()">
                    <span id="undo-text">Undo</span>
                    <span id="undo-spinner" style="display:none;" class="spinner"></span>
                </button>
            <?php endif; ?>
            <button class="toast-close" onclick="closeToast()" aria-label="Close">
                <svg class="progress-ring" width="24" height="24">
                    <circle class="progress-ring__circle" stroke="#3ea6ff" stroke-width="2" fill="transparent" r="10" cx="12" cy="12"/>
                </svg>
                <span class="close-icon">✕</span>
            </button>
        </div>
    </div>
<?php endif; ?>

 <script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>