<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
// Using raw ID; prepared statements will handle the safety
$group_id = isset($_GET['id']) ? $_GET['id'] : 0;
$decision = $_GET['decision'] ?? '';

// 1. Verify the user is the one invited to lead
// SECURED: Using placeholders (?) to prevent ID manipulation
$query_str = "SELECT community_name, leader_email, pending_leader FROM communities WHERE id = ?";
$stmt = mysqli_prepare($conn, $query_str);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$query_res = mysqli_stmt_get_result($stmt);
$club = mysqli_fetch_assoc($query_res);

if (!$club || $club['pending_leader'] !== $user_email) {
    die("Invalid request or unauthorized.");
}

$group_name = $club['community_name'];
$old_leader = $club['leader_email'];

if ($decision === 'accept') {
    // 1. Demote old leader to member status
    $demote_stmt = mysqli_prepare($conn, "INSERT INTO members (community_id, user_email, role) VALUES (?, ?, 'member')");
    mysqli_stmt_bind_param($demote_stmt, "is", $group_id, $old_leader);
    mysqli_stmt_execute($demote_stmt);
    
    // 2. Update community: set new leader, clear pending field
    $update_stmt = mysqli_prepare($conn, "UPDATE communities SET leader_email = ?, pending_leader = NULL WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "si", $user_email, $group_id);
    mysqli_stmt_execute($update_stmt);
    
    // 3. Remove new leader from members table (they are now the leader in communities table)
    $cleanup_stmt = mysqli_prepare($conn, "DELETE FROM members WHERE community_id = ? AND user_email = ?");
    mysqli_stmt_bind_param($cleanup_stmt, "is", $group_id, $user_email);
    mysqli_stmt_execute($cleanup_stmt);

    header("Location: ../views/club_dashboard.php?id=$group_id&msg=Success! You are now the leader of $group_name.");
} 
elseif ($decision === 'reject') {
    // Just clear the pending leader field
    $reject_stmt = mysqli_prepare($conn, "UPDATE communities SET pending_leader = NULL WHERE id = ?");
    mysqli_stmt_bind_param($reject_stmt, "i", $group_id);
    mysqli_stmt_execute($reject_stmt);
    
    // Optional: Notify the old leader that the offer was declined
    $notif_msg = "The user $user_email has declined your offer to take over leadership of $group_name.";
    $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('system', ?, 'Leadership Offer Declined', ?, 'membership')");
    mysqli_stmt_bind_param($notif_stmt, "ss", $old_leader, $notif_msg);
    mysqli_stmt_execute($notif_stmt);

    header("Location: ../views/notifications.php?msg=Leadership offer declined.");
}
?>