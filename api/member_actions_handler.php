<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$current_user_email = $_SESSION['user'];
$current_user_role = $_SESSION['role'] ?? 'user';
$action = $_POST['action'] ?? '';

// --- LOGIC FOR ADMIN ONLY ACTIONS ---
if ($current_user_role === 'admin') {
    
    // ACTION: UPDATE GLOBAL USER PROFILE (from view_user_detail.php)
    if ($action == 'admin_update_user_profile') {
        $user_id = intval($_POST['user_id']);
        $new_name = $_POST['new_name'];
        $new_email = $_POST['new_email'];
        $ban_until = !empty($_POST['ban_until']) ? $_POST['ban_until'] : NULL;
        $new_password = $_POST['reset_password'] ?? '';

        if (!empty($new_password)) {
            // REMOVED 'role = ?' from the query and one 's' from bind_param
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, ban_until = ?, password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $new_name, $ban_until, $hashed_password, $user_id);
        } else {
            // REMOVED 'role = ?' from the query and one 's' from bind_param
            $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, ban_until = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $new_name, $ban_until, $user_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            header("Location: ../views/view_user_detail.php?id=$user_id&msg=User_updated_successfully");
            exit();
        }
    }

    // ACTION: ADMIN FORCED REMOVAL (from view_user_detail.php)
    elseif($action == 'admin_remove_user') {
        $group_id = intval($_POST['group_id']);
        $target_email = $_POST['target_email'];

        // Verify target is not a leader before removing
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM communities WHERE id = ? AND leader_email = ?");
        mysqli_stmt_bind_param($check_stmt, "is", $group_id, $target_email);
        mysqli_stmt_execute($check_stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
            die("Cannot remove a leader from their own community.");
        }

        $del_stmt = mysqli_prepare($conn, "DELETE FROM members WHERE community_id = ? AND user_email = ?");
        mysqli_stmt_bind_param($del_stmt, "is", $group_id, $target_email);
        mysqli_stmt_execute($del_stmt);

        // Notify user
        $notif_title = "Removed from Community";
        $notif_msg = "An administrator has removed you from a group.";
        $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('system', ?, ?, ?, 'system')");
        mysqli_stmt_bind_param($notif_stmt, "sss", $target_email, $notif_title, $notif_msg);
        mysqli_stmt_execute($notif_stmt);

        header("Location: " . $_SERVER['HTTP_REFERER'] . "&msg=User_removed_from_group");
    }
}


// --- LOGIC FOR LEADER ACTIONS (manage_group.php) ---
if (isset($_POST['group_id']) && isset($_POST['target_email'])) {
    $group_id = $_POST['group_id'];
    $target_email = $_POST['target_email'];

    // Security: Verify current user is actually the leader of this group
    $check_sql = "SELECT community_name FROM communities WHERE id = ? AND leader_email = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "is", $group_id, $current_user_email);
    mysqli_stmt_execute($stmt);
    $check_res = mysqli_stmt_get_result($stmt);
    $group_data = mysqli_fetch_assoc($check_res);

    if (!$group_data) {
        die("Unauthorized: You are not the leader of this group.");
    }

    $group_name = $group_data['community_name'];

    if ($action == 'remove') {
        // 1. Remove the member safely
        $del_stmt = mysqli_prepare($conn, "DELETE FROM members WHERE community_id = ? AND user_email = ?");
        mysqli_stmt_bind_param($del_stmt, "is", $group_id, $target_email);
        mysqli_stmt_execute($del_stmt);

        // 2. Send notification
        $notif_title = "Removed from $group_name";
        $notif_msg = "The leader has removed you from the group: $group_name. You are no longer a member of this community.";
        
        $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, ?, ?, 'membership')");
        mysqli_stmt_bind_param($notif_stmt, "ssss", $current_user_email, $target_email, $notif_title, $notif_msg);
        mysqli_stmt_execute($notif_stmt);

        header("Location: ../views/manage_group.php?id=$group_id&msg=Member_removed_and_notified");
        exit();
    }
    elseif ($action == 'ban') {
        $reason = $_POST['reason'];
        $days = (int)$_POST['duration'];
        $until = date('Y-m-d H:i:s', strtotime("+$days days"));

        // 1. Log the ban details
        $ban_stmt = mysqli_prepare($conn, "INSERT INTO community_bans (community_id, user_email, reason, banned_until) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ban_stmt, "isss", $group_id, $target_email, $reason, $until);
        mysqli_stmt_execute($ban_stmt);
        
        // 2. Update status to 'banned'
        $upd_stmt = mysqli_prepare($conn, "UPDATE members SET role = 'banned' WHERE community_id = ? AND user_email = ?");
        mysqli_stmt_bind_param($upd_stmt, "is", $group_id, $target_email);
        mysqli_stmt_execute($upd_stmt);
        
        // 3. Notify the user
        $msg = "You have been banned from $group_name until $until. Reason: $reason";
        $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, ?, ?, 'system')");
        $title = "Banned from $group_name";
        mysqli_stmt_bind_param($notif_stmt, "ssss", $leader_email, $target_email, $title, $msg);
        mysqli_stmt_execute($notif_stmt);
        
        header("Location: ../views/manage_group.php?id=$group_id&msg=Member_banned");
    }
    elseif ($action == 'unban') {
        // 1. Remove from bans table
        $unban_stmt = mysqli_prepare($conn, "DELETE FROM community_bans WHERE community_id = ? AND user_email = ?");
        mysqli_stmt_bind_param($unban_stmt, "is", $group_id, $target_email);
        mysqli_stmt_execute($unban_stmt);
        
        // 2. Restore role
        $res_stmt = mysqli_prepare($conn, "UPDATE members SET role = 'member' WHERE community_id = ? AND user_email = ?");
        mysqli_stmt_bind_param($res_stmt, "is", $group_id, $target_email);
        mysqli_stmt_execute($res_stmt);

        // 3. Notify
        $msg = "You have been unbanned from $group_name";
        $title = "Unbanned from $group_name";
        $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, ?, ?, 'system')");
        mysqli_stmt_bind_param($notif_stmt, "ssss", $leader_email, $target_email, $title, $msg);
        mysqli_stmt_execute($notif_stmt);
        
        header("Location: ../views/manage_group.php?id=$group_id&msg=Member_unbanned");
    }
    elseif ($action == 'initiate_promote') {
        $prom_stmt = mysqli_prepare($conn, "UPDATE communities SET pending_leader = ? WHERE id = ?");
        mysqli_stmt_bind_param($prom_stmt, "si", $target_email, $group_id);
        mysqli_stmt_execute($prom_stmt);
        
        $msg = "The leader of $group_name has offered you the Leadership position. If you accept, you will become the Leader and the current leader will be demoted to Member. \n\n Accept: ../views/leadership_handler.php?id=$group_id&decision=accept \n Reject: leadership_handler.php?id=$group_id&decision=reject";
        $title = "Leadership Promotion Offer";
        
        $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, ?, ?, 'membership')");
        mysqli_stmt_bind_param($notif_stmt, "ssss", $leader_email, $target_email, $title, $msg);
        mysqli_stmt_execute($notif_stmt);
        
        header("Location: ../views/manage_group.php?id=$group_id&msg=Promotion sent");
    }
} else {
    die("Invalid request: Missing parameters.");
}

?>