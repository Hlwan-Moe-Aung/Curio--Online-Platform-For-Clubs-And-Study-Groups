<?php
session_start();
include('../includes/db.php');

// Security: Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$group_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch community details along with leader info
$query = "SELECT c.*, u.fullname as leader_name 
          FROM communities c 
          JOIN users u ON c.leader_email = u.email 
          WHERE c.id = ? AND c.status = 'disband_pending'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($result);

if (!$group) {
    die("Request not found or already processed.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8 Kohl">
    <title>Review Disband | Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/group_creation_review.css">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="request-form-container">
        <a href="admin_dashboard.php#Disband" style="text-decoration: none; color: #666; font-size: 14px;">← Back to Admin Dashboard</a>
        
        <h2 style="color: #e74c3c; margin-top: 20px;">Review Disband Request</h2>

        <div style="text-align: center; margin-bottom: 20px;">
            <img src="../uploads/<?php echo $group['profile_pic'] ?: 'default_community.png'; ?>" 
                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #eee;">
        </div>

        <div class="form-group">
            <label>Community Name</label>
            <input type="text" value="<?php echo htmlspecialchars($group['community_name']); ?>" readonly>
        </div>

        <div style="display: flex; gap: 20px;">
            <div class="form-group" style="flex: 1;">
                <label>Leader Name</label>
                <input type="text" value="<?php echo htmlspecialchars($group['leader_name']); ?>" readonly>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Leader Email</label>
                <input type="text" value="<?php echo htmlspecialchars($group['leader_email']); ?>" readonly>
            </div>
        </div>

        <div class="form-group">
            <label>Created On</label>
            <input type="text" value="<?php echo date('M d, Y', strtotime($group['created_at'])); ?>" readonly>
        </div>

        <div class="form-group">
            <label style="color: #e74c3c; font-weight: bold;">Leader's Reason for Disbanding</label>
            <textarea readonly style="background: #fff5f5; border-color: #feb2b2;"><?php echo htmlspecialchars($group['disband_reason']); ?></textarea>
        </div>

        <div style="margin-bottom: 30px;">
            <a href="view_club.php?id=<?php echo $group['id']; ?>" target="_blank" class="btn" style="display: block; text-align: center; background: #3a86ff; text-decoration: none; color: white; padding: 10px; border-radius: 8px;">
                🔍 View Community Page
            </a>
        </div>

        <form action="../api/admin_action_handler.php" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
            <input type="hidden" name="community_name" value="<?php echo htmlspecialchars($group['community_name']); ?>">
            <input type="hidden" name="leader_email" value="<?php echo $group['leader_email']; ?>">

            <div class="form-group">
                <label>Admin Feedback (Sent to Leader)</label>
                <textarea name="admin_feedback" placeholder="Provide a reason for approving or declining this request..." required></textarea>
            </div>

            <div class="creation-form-btn">
                <button type="submit" name="action" value="approve" class="btn" style="background: #e74c3c; color: white;" onclick="return confirm('WARNING: This will permanently DELETE the community and all its data. Proceed?')">
                    Approve & Delete
                </button>
                <button type="submit" name="action" value="decline" class="btn" style="background: #27ae60; color: white;">
                    Decline & Restore
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>