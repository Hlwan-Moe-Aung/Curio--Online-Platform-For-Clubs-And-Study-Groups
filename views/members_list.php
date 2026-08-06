<?php
session_start();
include('../includes/db.php');
include('../includes/report_form.php');


// 1. Security Check: Must be logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search = $_GET['search'] ?? '';

// 2. Access Control: Verify the user has access to this club
$check_sql = "SELECT c.*, 
             (SELECT COUNT(*) FROM members m WHERE m.community_id = c.id AND m.user_email = ?) as is_member
             FROM communities c 
             WHERE c.id = ? AND c.status IN ('approved', 'disband_pending')";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "si", $user_email, $club_id);
mysqli_stmt_execute($stmt);
$club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$club || ($club['is_member'] == 0 && $club['leader_email'] !== $user_email && ($_SESSION['role'] ?? '') !== 'admin')) {
    header("Location: ../views/view_club.php?id=$club_id&error=not_a_member");
    exit();
}

$is_leader = ($club['leader_email'] === $user_email);

// 3. Member Fetching Logic with Filter
$member_sql = "
    (SELECT 0 as id, c.id as community_id, c.leader_email as user_email, 'Leader' as role, c.created_at as joined_at, u.fullname, u.email, u.id as user_id
     FROM communities c
     JOIN users u ON c.leader_email = u.email
     WHERE c.id = ?)
    UNION
    (SELECT m.id, m.community_id, m.user_email, m.role, m.joined_at, u.fullname, u.email, u.id as user_id
     FROM members m
     JOIN users u ON m.user_email = u.email
     WHERE m.community_id = ?)
";

$params = [$club_id, $club_id];
$types = "ii";

if (!empty($search)) {
    $search_param = "%$search%";
    $member_sql = "SELECT * FROM ($member_sql) AS combined_list WHERE fullname LIKE ? OR email LIKE ?";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
} else {
    $member_sql .= " ORDER BY joined_at ASC";
}

$m_stmt = mysqli_prepare($conn, $member_sql);
mysqli_stmt_bind_param($m_stmt, $types, ...$params);
mysqli_stmt_execute($m_stmt);
$members = mysqli_stmt_get_result($m_stmt);

// Filter Config for the search component
// In members_list.php
$filter_config = [
    'action' => 'members_list.php', // It will pick up the ID from the hidden input automatically
    'placeholder' => 'Search members by name or email...',
    'search_key' => 'search',
    'show_date' => false,
    'dropdowns' => [] // Explicitly tell the component there are no dropdowns
];

renderReportModal($_SESSION['user']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Members | <?php echo htmlspecialchars($club['community_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/tab_system.css">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css">
    <link rel="stylesheet" href="../assets/css/studyMaterials.css">
    <link rel="stylesheet" href="../assets/css/toast.css">
    <style>
        .feed-card hr { border: 0; border-top: 1px solid #eee; margin: 15px 0; }
        .member-list-container { display:flex; flex-direction:column; gap: 12px; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'reported') {
    $display_text = "Report received. Thank you!";
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

<div class="main-content">
    <?php if ($club['status'] === 'disband_pending'): ?>
        <div class="disband-banner">
            <div class="banner-content">
                <span class="banner-icon">⚠️</span>
                <div class="banner-text">
                    <strong>Disbandment Request Pending</strong>
                    <p>According to the request sent by Leader of this community, the administrator has been notified.</p>
                </div>
                <!-- <a href="contact_admin.php" class="banner-btn">Contact Admin</a> -->
            </div>
        </div>
    <?php endif; ?> 

    <div class="club-banner">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
            <div>
                <div class="sm-badge"><?php echo ucfirst($club['category']); ?></div>
                <h1 style="margin: 10px 0;"><?php echo htmlspecialchars($club['community_name']); ?></h1>
                <p style="margin: 0;">👥 Community Members</p>
            </div>
            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                <?php if($is_leader): ?>
                    <a href="../views/manage_group.php?id=<?php echo $club_id; ?>" class="btn" style="background: white; color: #1f3c88;">Manage ⚙️</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/filter_component.php'; ?>

    <div class="dashboard-grid">
        <div class="main-feed">
            <div class="feed-card">
                <h3>Member List</h3>
                <hr>

                <?php if ($members && mysqli_num_rows($members) > 0): ?>
                    <div class="member-list-container">
                        <?php while($m = mysqli_fetch_assoc($members)): 
                            $m_role = ($m['role'] === 'Leader') ? 'Leader' : ucfirst($m['role']);
                            $is_self = ($m['email'] === $user_email);
                            // Use a safe ID for the JavaScript toggle (using user_id since members.id is 0 for leaders)
                            $unique_id = $m['user_id'];
                        ?>
                            <div class="member-card" style="border: 1px solid #eee; border-radius: 12px; padding: 14px; background: <?php echo $is_self ? '#f8faff' : '#fff'; ?>;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap: 10px; flex-wrap: wrap;">
                                <div style="display:flex; align-items:center; gap: 10px;">
                                    <div style="width:40px; height:40px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; color:#64748b;">
                                        <?php echo strtoupper(substr($m['fullname'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong style="display:block;"><?php echo htmlspecialchars($m['fullname']); ?> <?php echo $is_self ? '(You)' : ''; ?></strong>
                                        <small style="color:#667085;"><?php echo htmlspecialchars($m['email']); ?></small>
                                    </div>
                                </div>

                                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="sm-badge-category" style="background: <?php echo ($m_role === 'Leader') ? '#1f3c88' : '#e2e8f0'; ?>; color: <?php echo ($m_role === 'Leader') ? '#fff' : '#475569'; ?>; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold;">
                                            <?php echo strtoupper($m_role); ?>
                                        </span>

                                        <?php if (!$is_self): ?>
                                            <div class="sm-options">
                                                <button class="sm-dots-btn" onclick="toggleMenu(event, <?php echo $unique_id; ?>)">⋮</button>
                                                <div id="menu-<?php echo $unique_id; ?>" style="text-align: left;" class="sm-dropdown-content">
                                                    <a href="javascript:void(0)" 
                                                       style="color: #d9534f;" 
                                                       onclick="openReportModal('<?php echo $m['user_id']; ?>', 'user')">
                                                       🚩 Report User
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="margin-top: 5px;">
                                        <small style="color:#94a3b8;">Joined: <?php echo date('M d, Y', strtotime($m['joined_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#888; text-align: center; padding: 20px;">No members found matching your search.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="club-sidebar">
            <div class="feed-card">
                <h4>Leader</h4>
                <p>👤 <?php echo htmlspecialchars($club['leader_name'] ?? 'The Leader'); ?> <span class="leader-badge">LEADER</span></p>
            </div>
            <div class="feed-card">
                <h4>Quick Links</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;"><a href="club_dashboard.php?id=<?php echo $club_id; ?>" style="text-decoration: none; color: #3a86ff;">💬 Discussions</a></li>
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