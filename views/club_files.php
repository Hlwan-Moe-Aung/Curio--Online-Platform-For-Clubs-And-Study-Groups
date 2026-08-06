<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Backward-compatible route: redirect old page to new Study Materials page
header("Location: ../views/studyMaterials.php?id=$club_id");
exit();

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

$file_sql = "SELECT f.*, u.fullname AS uploader_name
             FROM community_files f
             LEFT JOIN users u ON u.email = f.uploaded_by
             WHERE f.community_id = ?
             ORDER BY f.uploaded_at DESC";
$file_stmt = mysqli_prepare($conn, $file_sql);
mysqli_stmt_bind_param($file_stmt, "i", $club_id);
mysqli_stmt_execute($file_stmt);
$files = mysqli_stmt_get_result($file_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['community_name']); ?> | Study Materials</title>
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<div class="main-content" id="main">
    <div class="club-banner">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
            <div>
                <span class="category-badge" style="background: rgba(255,255,255,0.2); color: white;">
                    <?php echo ucfirst($club['category']); ?>
                </span>
                <h1 style="margin: 10px 0;"><?php echo htmlspecialchars($club['community_name']); ?></h1>
                <p style="margin: 0;">📁 Study Materials</p>
            </div>
            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                <a href="../views/club_dashboard.php?id=<?php echo $club_id; ?>" class="btn" style="background: white; color: #1f3c88;">← Back</a>
                <?php if($is_leader): ?>
                    <a href="../views/manage_group.php?id=<?php echo $club_id; ?>" class="btn" style="background: white; color: #1f3c88;">Settings ⚙️</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="main-feed">
            <div class="feed-card">
                <h3>Shared Study Materials</h3>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                <?php if ($files && mysqli_num_rows($files) > 0): ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse: collapse;">
                            <thead>
                                <tr style="text-align:left;">
                                    <th style="padding:10px; border-bottom:1px solid #eee;">Title</th>
                                    <th style="padding:10px; border-bottom:1px solid #eee;">File</th>
                                    <th style="padding:10px; border-bottom:1px solid #eee;">Uploaded By</th>
                                    <th style="padding:10px; border-bottom:1px solid #eee;">Uploaded At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($f = mysqli_fetch_assoc($files)): ?>
                                    <tr>
                                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;">
                                            <strong><?php echo htmlspecialchars($f['title']); ?></strong>
                                            <?php if (!empty($f['description'])): ?>
                                                <div style="color:#666; margin-top:6px;"><?php echo nl2br(htmlspecialchars($f['description'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;">
                                            <?php if (!empty($f['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" style="text-decoration:none; color:#3a86ff;">
                                                    <?php echo htmlspecialchars($f['original_name'] ?: 'Download'); ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#999;">(no link)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;">
                                            <?php echo htmlspecialchars($f['uploader_name'] ?? 'Unknown'); ?>
                                        </td>
                                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;">
                                            <?php echo date('M d, Y', strtotime($f['uploaded_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color:#888;">No files shared yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="club-sidebar">
            <div class="feed-card">
                <h4>Quick Links</h4>
                <ul style="list-style:none; padding:0;">
                    <li style="margin-bottom:10px;"><a href="../views/club_exams.php?id=<?php echo $club_id; ?>" style="text-decoration:none; color:#3a86ff;">📝 Exams</a></li>
                    <li style="margin-bottom:10px;"><a href="../views/members_list.php?id=<?php echo $club_id; ?>" style="text-decoration:none; color:#3a86ff;">👥 View Members</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
</body>
</html>

