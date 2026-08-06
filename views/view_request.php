<?php
session_start();
include('../includes/db.php');

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// 2. Handle the Approve/Reject Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // Expected 'approved' or 'rejected'
    $feedback = $_POST['admin_feedback'];
    $l_email = $_POST['leader_email'];
    $c_name = $_POST['community_name'];

    // SECURED: Using Prepared Statement for UPDATE
    $update_sql = "UPDATE communities SET status = ? WHERE id = ?";
    $upd_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($upd_stmt, "si", $action, $request_id);
    
    if (mysqli_stmt_execute($upd_stmt)) {
        // Send Notification to Leader
        $title = ($action == 'approved') ? "Congratulations! $c_name Approved" : "Update regarding your request: $c_name";
        $type = 'creation';
        $sender = 'admin@gmail.com';
        $status = 'unread';
        
        // SECURED: Using Prepared Statement for INSERT
        $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type, status) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $notif_stmt = mysqli_prepare($conn, $notif_sql);
        mysqli_stmt_bind_param($notif_stmt, "ssssss", $sender, $l_email, $title, $feedback, $type, $status);
        mysqli_stmt_execute($notif_stmt);

        header("Location: ../views/admin_dashboard.php?msg=Request_" . ucfirst($action));
        exit();
    }
}
 
// 3. Fetch Request Data for Display
if (!isset($_GET['id'])) {
    header("Location: ../views/admin_dashboard.php");
    exit();
}

$request_id = $_GET['id'];

// SECURED: Using Prepared Statement for SELECT
$query = "SELECT * FROM communities WHERE id = ?";
$get_stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($get_stmt, "i", $request_id);
mysqli_stmt_execute($get_stmt);
$result = mysqli_stmt_get_result($get_stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("Request not found.");
}

// Check if the request is already processed
$is_processed = ($data['status'] !== 'pending');
$current_status = ucfirst($data['status']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Request | Admin</title>
    <link rel="stylesheet" href="../assets/css/group_creation_review.css?v=<?php echo time(); ?>">
</head>
<body>



<div class="full-page-container">
    <div class="request-form-container">
        <a href="../views/admin_dashboard.php" style="text-decoration: none; color: #888; font-size: 14px;">← Back to Admin Dashboard</a>
        
        <h2>Reviewing: <?php echo htmlspecialchars($data['community_name']); ?></h2>
        
        <div class="admin-info-banner">
            <p><strong>Leader:</strong> <?php echo htmlspecialchars($data['leader_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($data['leader_email']); ?></p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="request_id" value="<?php echo $data['id']; ?>">
            <input type="hidden" name="leader_email" value="<?php echo $data['leader_email']; ?>">
            <input type="hidden" name="community_name" value="<?php echo $data['community_name']; ?>">

            <div class="form-group">
                <label>Community Name</label>
                <input type="text" value="<?php echo htmlspecialchars($data['community_name']); ?>" readonly>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Type</label>
                    <input type="text" value="<?php echo ucfirst($data['type']); ?>" readonly>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Category</label>
                    <input type="text" value="<?php echo ucfirst($data['category']); ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea readonly><?php echo htmlspecialchars($data['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Purpose</label>
                <textarea readonly><?php echo htmlspecialchars($data['purpose']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Appeal</label>
                <textarea readonly><?php echo htmlspecialchars($data['appeal']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Admin Feedback / Rejection Reason</label>
                <?php if ($is_processed): ?>
                    <div style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; color: #666;">
                        This request has already been <strong><?php echo $data['status']; ?></strong>. No further actions can be taken.
                    </div>
                <?php else: ?>
                    <textarea name="admin_feedback" placeholder="Write the message that will be sent to the user..." required style="border: 2px solid #3a86ff;"></textarea>
                <?php endif; ?>
            </div>
            
            <?php if (!$is_processed): ?>
                <div class="creation-form-btn" style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" name="action" value="approved" class="btn" style="flex: 1;">
                        Approve
                    </button>
                    <button type="submit" name="action" value="rejected" class="btn" style="flex: 1; background-color: #ff4d4d;">
                        Reject
                    </button>
                </div>
            <?php else: ?>
                <div style="margin-top: 20px; text-align: center;">
                    <p style="color: #888; font-style: italic;">Viewing processed request (Read-Only Mode)</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

</body>
</html>