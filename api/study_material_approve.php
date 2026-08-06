<?php
session_start();
include('../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
    header('Location: ../public/login.php');
    exit();
}

$user_email = $_SESSION['user'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) && in_array($_POST['action'], ['approved','rejected']) ? $_POST['action'] : '';

if (!$id || !$action) {
    header("Location: ../views/manage_group.php?error=Invalid request");
    exit();
}

// Fetch material and community
$sql = "SELECT sm.*, c.leader_email, c.community_name FROM studyMaterial sm JOIN communities c ON sm.community_id = c.id WHERE sm.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);

if (!$row) {
    header("Location: ../views/manage_group.php?error=Material not found");
    exit();
}

// Only leader can approve/reject
if ($row['leader_email'] !== $user_email) {
    header("Location: ../views/manage_group.php?id={$row['community_id']}&error=Not authorized");
    exit();
}

// Update status (ensure status column exists)
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM studyMaterial LIKE 'status'");
$has_status = ($col_check && mysqli_num_rows($col_check) > 0);
if (!$has_status) {
    // If no status column, create it (best-effort)
    mysqli_query($conn, "ALTER TABLE studyMaterial ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'approved'");
}

$upd_sql = "UPDATE studyMaterial SET status = ? WHERE id = ?";
$upd_stmt = mysqli_prepare($conn, $upd_sql);
mysqli_stmt_bind_param($upd_stmt, "si", $action, $id);
if (mysqli_stmt_execute($upd_stmt)) {
    // Notify uploader about result
    $title = ($action == 'approved') ? "Material Approved: {$row['title']}" : "Material Declined: {$row['title']}";
    $message = "Your uploaded material '{$row['title']}' for community '{$row['community_name']}' has been {$action} by the leader.";
    $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type, status) VALUES (?, ?, ?, ?, 'approval_result', 'unread')";
    $n_stmt = mysqli_prepare($conn, $notif_sql);
    mysqli_stmt_bind_param($n_stmt, "ssss", $user_email, $row['uploaded_by'], $title, $message);
    mysqli_stmt_execute($n_stmt);

    header("Location: ../views/manage_group.php?id={$row['community_id']}&msg=Material {$action}");
    exit();
} else {
    header("Location: ../views/manage_group.php?id={$row['community_id']}&error=Failed to update status");
    exit();
}

?>
