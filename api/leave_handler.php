<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user']) || !isset($_POST['community_id'])) {
    header("Location: ../views/dashboard.php");
    exit();
}

$user_email = $_SESSION['user'];
$community_id = (int)$_POST['community_id'];
$leave_message = trim($_POST['leave_message'] ?? 'No message provided.');

// 1. Get Leader Email and Community Name for the notification
$info_query = "SELECT leader_email, community_name FROM communities WHERE id = ?";
$stmt = mysqli_prepare($conn, $info_query);
mysqli_stmt_bind_param($stmt, "i", $community_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$community = mysqli_fetch_assoc($res);

if ($community) {
    $leader_email = $community['leader_email'];
    $community_name = $community['community_name'];

    // 2. Remove user from members table
    $delete_query = "DELETE FROM members WHERE community_id = ? AND user_email = ?";
    $d_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($d_stmt, "is", $community_id, $user_email);

    if (mysqli_stmt_execute($d_stmt)) {
        // 3. Send notification to the leader
        $notif_title = "Member Left: $community_name";
        $notif_msg = "$user_email has left the group. Message: $leave_message";
        
        $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type, status) 
                      VALUES (?, ?, ?, ?, 'system', 'unread')";
        $n_stmt = mysqli_prepare($conn, $notif_sql);
        mysqli_stmt_bind_param($n_stmt, "ssss", $user_email, $leader_email, $notif_title, $notif_msg);
        mysqli_stmt_execute($n_stmt);

        // Redirect to user dashboard with a success message
        header("Location: ../views/clubs.php?msg=You have left the community.");
        exit();
    }
}

header("Location: ../views/club_dashboard.php?id=$community_id&msg=Error leaving club.");
exit();