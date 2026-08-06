<?php
session_start();
include('../includes/db.php');

// 1. Determine if this is a GET (Membership/Post) or POST (Disband) request
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
$user_email = $_SESSION['user'];

// ---  HANDLE DISBAND REQUEST (POST) ---
if ($is_post && isset($_POST['request_disband'])) {
    $group_id = $_POST['group_id'];
    $c_name = $_POST['community_name'];
    $disband_reason = $_POST['disband_reason'];

    // Update status to 'disband_pending' and store reason in 'disband_reason' column
    $update_sql = "UPDATE communities SET status = 'disband_pending', disband_reason = ? WHERE id = ? AND leader_email = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "sis", $disband_reason, $group_id, $user_email);

    if (mysqli_stmt_execute($stmt)) {
        // Send Notification to Admin
        $admin_email = 'admin@gmail.com'; 
        $title = "🚨 Disband Request: $c_name";
        $notif_msg = "Leader ($user_email) has requested to disband the community: $c_name.\n\nReason: $disband_reason\n\nPlease review this request in the admin dashboard: admin_dashboard.php#Disband";
        
        $noti_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, ?, ?, 'system')";
        $noti_stmt = mysqli_prepare($conn, $noti_sql);
        mysqli_stmt_bind_param($noti_stmt, "ssss", $user_email, $admin_email, $title, $notif_msg);
        mysqli_stmt_execute($noti_stmt);

        header("Location: ../views/manage_group.php?id=$group_id&msg=disband_sent");
        exit();
    }
}

if (!isset($_SESSION['user']) || !isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: ../views/dashboard.php");
    exit();
}

// Use raw values; bind_param will handle the safety
$request_id = $_GET['id'];
$action = $_GET['action'];
$type = isset($_GET['type']) ? $_GET['type'] : 'membership'; // Default to membership if not set
$leader_email = $_SESSION['user'];
$club_id_from_url = isset($_GET['club_id']) ? $_GET['club_id'] : 0;

if ($type === 'post') {
    // 1. Verify that the logged-in user is indeed the leader of the club this post belongs to
    $verify_stmt = mysqli_prepare($conn, "SELECT leader_email FROM communities WHERE id = ?");
    mysqli_stmt_bind_param($verify_stmt, "i", $club_id_from_url);
    mysqli_stmt_execute($verify_stmt);
    $verify_leader = mysqli_stmt_get_result($verify_stmt);
    $club = mysqli_fetch_assoc($verify_leader);
    
    if (!$club || $club['leader_email'] !== $leader_email) {
        die("Unauthorized access.");
    }

    if ($action === 'approve') {
        $upd_stmt = mysqli_prepare($conn, "UPDATE posts SET status = 'approved' WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "i", $request_id);
        mysqli_stmt_execute($upd_stmt);
        $msg = "post_approved";
    } elseif ($action === 'reject') {
        $del_stmt = mysqli_prepare($conn, "DELETE FROM posts WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, "i", $request_id);
        mysqli_stmt_execute($del_stmt);
        $msg = "post_rejected";
    }
     
    header("Location: ../views/manage_group.php?id=$club_id_from_url&msg=$msg");
    exit();
}

// 1. Fetch request details and verify ownership
$req_query = "SELECT r.*, c.community_name, c.leader_email 
              FROM membership_requests r 
              JOIN communities c ON r.community_id = c.id 
              WHERE r.id = ?";
$stmt = mysqli_prepare($conn, $req_query);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($res);

if (!$request || $request['leader_email'] !== $leader_email) {
    die("Unauthorized or request not found.");
}

$user_email = $request['user_email'];
$group_id = $request['community_id'];
$group_name = $request['community_name'];

if ($action === 'approve') {
    // A. Update request status
    $upd_req = mysqli_prepare($conn, "UPDATE membership_requests SET status = 'approved' WHERE id = ?");
    mysqli_stmt_bind_param($upd_req, "i", $request_id);
    mysqli_stmt_execute($upd_req);

    // B. Add to members table (ignore if already exists)
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM members WHERE community_id = ? AND user_email = ?");
    mysqli_stmt_bind_param($check_stmt, "is", $group_id, $user_email);
    mysqli_stmt_execute($check_stmt);
    $check_member = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_member) == 0) {
        $ins_mem = mysqli_prepare($conn, "INSERT INTO members (community_id, user_email, role) VALUES (?, ?, 'member')");
        mysqli_stmt_bind_param($ins_mem, "is", $group_id, $user_email);
        mysqli_stmt_execute($ins_mem);
    }

    // C. Notify User
    $title = "Request Approved: $group_name";
    $notif_msg = "Congratulations! Your request to join $group_name has been approved by the leader. You can now access the group dashboard.";
    
    $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('System', ?, ?, ?, 'membership')");
    mysqli_stmt_bind_param($notif_stmt, "sss", $user_email, $title, $notif_msg);
    mysqli_stmt_execute($notif_stmt);

    header("Location: ../views/manage_group.php?id=$group_id&msg=approved");

} elseif ($action === 'reject') {
    // A. Update request status
    $rej_stmt = mysqli_prepare($conn, "UPDATE membership_requests SET status = 'rejected' WHERE id = ?");
    mysqli_stmt_bind_param($rej_stmt, "i", $request_id);
    mysqli_stmt_execute($rej_stmt);

    // B. Notify User
    $title = "Update on your request: $group_name";
    $notif_msg = "We regret to inform you that your request to join $group_name was not accepted at this time.";
    
    $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('System', ?, ?, ?, 'membership')");
    mysqli_stmt_bind_param($notif_stmt, "sss", $user_email, $title, $notif_msg);
    mysqli_stmt_execute($notif_stmt);

    header("Location: ../views/manage_group.php?id=$group_id&msg=rejected");
}
?>