<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user']) || !isset($_POST['club_id'])) {
    header("Location: ../views/clubs.php");
    exit();
}

// 1. Capture data (Prepared statements will handle the safety)
$club_id = $_POST['club_id'];
$user_email = $_SESSION['user'];
$user_name = $_SESSION['user_fullname'] ?? 'Student';
$appeal = $_POST['appeal'] ?? '';

// 1. Fetch Club/Group details (to get leader email and name)
$group_stmt = mysqli_prepare($conn, "SELECT community_name, leader_email, type FROM communities WHERE id = ?");
mysqli_stmt_bind_param($group_stmt, "i", $club_id); // Using "i" assuming ID is an integer
mysqli_stmt_execute($group_stmt);
$group_query = mysqli_stmt_get_result($group_stmt);
$group = mysqli_fetch_assoc($group_query);

$leader_email = $group['leader_email'];
$group_name = $group['community_name'];
$type_label = ($group['type'] == 'club') ? 'Club' : 'Study Group';

// 2. Check if already requested or already a member
$check_stmt = mysqli_prepare($conn, "SELECT id FROM membership_requests WHERE community_id = ? AND user_email = ? AND status = 'pending'");
mysqli_stmt_bind_param($check_stmt, "is", $club_id, $user_email);
mysqli_stmt_execute($check_stmt);
$check = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check) > 0) {
    header("Location: ../views/view_club.php?id=$club_id&msg=already_pending");
    exit();
}

// 3. Insert Membership Request
$insert_req = "INSERT INTO membership_requests (community_id, user_email, user_name, appeal, status) 
               VALUES (?, ?, ?, ?, 'pending')";

$ins_stmt = mysqli_prepare($conn, $insert_req);
mysqli_stmt_bind_param($ins_stmt, "isss", $club_id, $user_email, $user_name, $appeal);

if (mysqli_stmt_execute($ins_stmt)) {
    
    // 4. Send Notification to Leader (Receiver: Leader, Sender: User)
    // This appears in User's SENT and Leader's INBOX
    $title_leader = "New Join Request: $group_name";
    $msg_leader = "User $user_name has requested to join your $type_label. \n\nAppeal: $appeal \n\nClick here to manage requests: ../views/manage_group.php?id=$club_id";

    $notif_leader_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) 
                         VALUES ('System', ?, ?, ?, 'membership')";
    $nl_stmt = mysqli_prepare($conn, $notif_leader_sql);
    mysqli_stmt_bind_param($nl_stmt, "sss", $leader_email, $title_leader, $msg_leader);
    mysqli_stmt_execute($nl_stmt);

    // 5. Send Confirmation to User (Receiver: User, Sender: System)
    // This appears in User's INBOX and System's SENT
    $title_user = "Request Sent: $group_name";
    $msg_user = "Your request to join $group_name has been submitted to the leader ($leader_email). Please wait for approval.";

    // If you'd prefer the confirmation to come from the system:
    $notif_user_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) 
                       VALUES ('System', ?, ?, ?, 'system')";
    $nu_stmt = mysqli_prepare($conn, $notif_user_sql);
    mysqli_stmt_bind_param($nu_stmt, "sss", $user_email, $title_user, $msg_user);
    mysqli_stmt_execute($nu_stmt);

    header("Location: ../views/view_club.php?id=$club_id&msg=request_sent");
}
?>