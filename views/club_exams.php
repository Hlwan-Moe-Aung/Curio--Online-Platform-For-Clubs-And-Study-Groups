<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Access control: member OR leader OR admin
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
    ($_SESSION['role'] ?? '') !== 'admin'
)) {
    header("Location: ../views/view_club.php?id=$club_id&error=not_a_member");
    exit();
}

$is_leader = ($club['leader_email'] === $user_email);

$exam_sql = "SELECT e.*, u.fullname AS creator_name
             FROM community_exams e
             LEFT JOIN users u ON u.email = e.created_by
             WHERE e.community_id = ?
             ORDER BY e.exam_date ASC";
$exam_stmt = mysqli_prepare($conn, $exam_sql);
mysqli_stmt_bind_param($exam_stmt, "i", $club_id);
mysqli_stmt_execute($exam_stmt);
$exams = mysqli_stmt_get_result($exam_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['community_name']); ?> | Exams</title>
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/studyMaterials.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<div class="main-content" id="main">
    <div class="club-banner">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
            <div>
                <div class="sm-badge"><?php echo ucfirst($club['category']); ?></div>
                <h1 style="margin: 10px 0;"><?php echo htmlspecialchars($club['community_name']); ?></h1>
                <p style="margin: 0;">📝 Exams</p>
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
            <div class="feed-card">
                <h3>Upcoming / Scheduled Exams</h3>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                <?php if ($exams && mysqli_num_rows($exams) > 0): ?>
                    <div style="display:flex; flex-direction:column; gap: 12px;">
                        <?php while($exam = mysqli_fetch_assoc($exams)): ?>
                            <div style="border: 1px solid #eee; border-radius: 12px; padding: 14px;">
                                <div style="display:flex; justify-content:space-between; gap: 10px; flex-wrap: wrap;">
                                    <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                                    <small>
                                        📅 <?php echo date('M d, Y', strtotime($exam['exam_date'])); ?>
                                        • ⏰ <?php echo date('h:i A', strtotime($exam['exam_date'])); ?>
                                    </small>
                                </div>
                                <?php if (!empty($exam['location'])): ?>
                                    <div style="margin-top:6px; color:#555;">📍 <?php echo htmlspecialchars($exam['location']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($exam['description'])): ?>
                                    <p style="margin: 10px 0 0 0; color:#444;"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
                                <?php endif; ?>
                                <div style="margin-top:10px; color:#777;">
                                    <small>
                                        Added by: <strong><?php echo htmlspecialchars($exam['creator_name'] ?? 'Unknown'); ?></strong>
                                        • <?php echo date('M d, Y', strtotime($exam['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#888;">No exams added yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="club-sidebar">
            <div class="feed-card">
                <h4>Quick Links</h4>
                <ul style="list-style:none; padding:0;">
                    <li style="margin-bottom:10px;"><a href="../views/studyMaterials.php?id=<?php echo $club_id; ?>" style="text-decoration:none; color:#3a86ff;">📁 Study Materials</a></li>
                    <li style="margin-bottom:10px;"><a href="../views/members_list.php?id=<?php echo $club_id; ?>" style="text-decoration:none; color:#3a86ff;">👥 View Members</a></li>
                    <li style="margin-bottom: 10px;"><a href="../views/club_dashboard.php?id=<?php echo $club_id; ?>" style="text-decoration: none; color: #3a86ff;">💬 Discussions</a></li>

                </ul>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
</body>
</html>

