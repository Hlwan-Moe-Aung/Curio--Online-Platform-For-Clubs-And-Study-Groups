<?php
session_start();
include('../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user'])) {
    $id = intval($_POST['request_id']);
    $type = $_POST['request_type'];
    $email = $_SESSION['user'];

    switch ($type) {
        case 'Creation':
            $sql = "DELETE FROM communities WHERE id = ? AND leader_email = ? AND status = 'pending'";
            break;
        case 'Disband':
            $sql = "UPDATE communities SET status = 'approved', disband_reason = NULL WHERE id = ? AND leader_email = ?";
            break;
        case 'Membership':
            $sql = "DELETE FROM membership_requests WHERE id = ? AND user_email = ?";
            break;
        case 'Private Post':
            $sql = "DELETE FROM posts WHERE id = ? AND author_email = ?";
            break;
        case 'Material Upload':
            $sql = "DELETE FROM studymaterial WHERE id = ? AND uploaded_by = ?";
            break;
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $id, $email);
    mysqli_stmt_execute($stmt);

    header("Location: ../views/dashboard.php?msg=deleted");
    exit();
}