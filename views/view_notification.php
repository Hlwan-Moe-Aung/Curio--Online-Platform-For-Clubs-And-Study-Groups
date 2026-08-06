<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

// Use raw ID; prepared statements handle the safety
$id = isset($_GET['id']) ? $_GET['id'] : 0;
$user_email = $_SESSION['user'];

// --- HANDLE DELETE ACTION ---
if (isset($_POST['delete_noti'])) {
    $delete_id = $_POST['noti_id'];
    
    // SECURED: Prepared Statement ensures users can only delete their own notifications
    $delete_sql = "DELETE FROM notifications WHERE id = ? AND (receiver_email = ? OR sender_email = ?)";
    $del_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($del_stmt, "iss", $delete_id, $user_email, $user_email);
    
    if (mysqli_stmt_execute($del_stmt)) {
        header("Location: ../views/notifications.php?msg=deleted");
        exit();
    }
}

// --- MARK AS READ ---
// SECURED: Mark as read immediately if the user is the receiver
$update_sql = "UPDATE notifications SET status = 'read' WHERE id = ? AND receiver_email = ?";
$upd_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($upd_stmt, "is", $id, $user_email);
mysqli_stmt_execute($upd_stmt);

// --- FETCH NOTIFICATION DETAILS ---
// SECURED: Fetch using Prepared Statement
$fetch_sql = "SELECT * FROM notifications WHERE id = ?";
$fetch_stmt = mysqli_prepare($conn, $fetch_sql);
mysqli_stmt_bind_param($fetch_stmt, "i", $id);
mysqli_stmt_execute($fetch_stmt);
$res = mysqli_stmt_get_result($fetch_stmt);
$noti = mysqli_fetch_assoc($res);

if (!$noti) {
    header("Location: ../views/notifications.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($noti['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/notifications.css?v=<?php echo time(); ?>">
</head>
<body>
<?php include '../includes/navbar.php' ?>

<div class="main-content" id="main">
    <div class="notifications-container">
        <a href="../views/notifications.php" style="text-decoration: none; color: #3a86ff; font-weight: 600;">← Back to Inbox</a>
         
        <div class="message-card">
            <div class="message-header">
                <h1><?php echo htmlspecialchars($noti['title']); ?></h1>
                <div class="message-meta">
                    <strong>From:</strong> <?php echo htmlspecialchars($noti['sender_email']); ?> <br>
                    <strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($noti['created_at'])); ?>
                </div>
            </div>

            <div class="message-body">
                <?php 
                    $message = htmlspecialchars($noti['message']);
                    
                    if ($noti['receiver_email'] === $user_email) {
                        // Existing logic for Management Page links
                        $message = preg_replace(
                            '/manage_group\.php\\?id=(\\d+)/', 
                            '<a href="../views/manage_group.php?id=$1" class="btn-small" style="display:inline-block; margin-top:10px;">Go to Management Page</a>', 
                            $message
                        );

                        // New logic for Leadership Accept link
                        $message = preg_replace(
                            '/accept_lead\.php\?id=(\d+)/', 
                            '<a href="../api/leadership_handler.php?id=$1&decision=accept" class="btn-small" style="display:inline-block; margin-top:10px; background:#27ae60;">Accept Leadership</a>', 
                            $message
                        );

                        // New logic for Leadership Reject link
                        $message = preg_replace(
                            '/reject_lead\.php\?id=(\d+)/', 
                            '<a href="../api/leadership_handler.php?id=$1&decision=reject" class="btn-small" style="display:inline-block; margin-top:10px; background:#e74c3c; margin-left:10px;">Reject</a>', 
                            $message
                        );

                        // New logic for Disband Request link
                        $message = preg_replace(
                            '/admin_dashboard\.php#Disband/', 
                            '<a href="../views/admin_dashboard.php#Disband" class="btn-small" style="display:inline-block; margin-top:10px; background:#3498db;">Go to Admin Dashboard</a>', 
                            $message
                        );

                        // New logic for Admin Report Management link
                        $message = preg_replace(
                            '/admin_dashboard\.php#Reports/', 
                            '<a href="../views/admin_dashboard.php#Reports" class="btn-small" style="display:inline-block; margin-top:10px; background:#e67e22;">Manage Reports</a>', 
                            $message
                        );
                    }
                    echo nl2br($message); 
                ?>
            </div>

            <div class="message-actions">
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this notification? This cannot be undone.');">
                    <input type="hidden" name="noti_id" value="<?php echo $noti['id']; ?>">
                    <button type="submit" name="delete_noti" class="btn-delete">
                        🗑️ Delete Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>