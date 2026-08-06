<?php
session_start();
include('../includes/db.php');

// 1. Basic Login Check
if (!isset($_SESSION['user'])) {
    die("Unauthorized access.");
}

$current_user = $_SESSION['user'];
$user_role = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CASE A: DISBAND ACTION ---
    if (isset($_POST['action'])) {
        $group_id = $_POST['group_id'] ?? null;
        $action = $_POST['action'];
        $feedback = $_POST['admin_feedback'] ?? 'No feedback';
        $leader_email = $_POST['leader_email'] ?? '';
        $c_name = $_POST['community_name'] ?? 'Community';
        $is_force = isset($_POST['is_force_disband']); // Check the flag

        if ($action === 'approve' && $group_id) {
            
            // 1. Prepare Notification Data
            if ($is_force) {
                $notif_title = "Community Terminated: $c_name";
                $notif_msg = "Notice: The community '$c_name' has been disbanded by the Site Administrator.\nReason: $feedback";
                
                // Get all member emails to notify everyone
                $member_query = mysqli_prepare($conn, "SELECT user_email FROM members WHERE community_id = ?");
                mysqli_stmt_bind_param($member_query, "i", $group_id);
                mysqli_stmt_execute($member_query);
                $member_res = mysqli_stmt_get_result($member_query);
                
                $recipients = [];
                while($row = mysqli_fetch_assoc($member_res)) {
                    $recipients[] = $row['user_email'];
                }
                // Ensure leader is in the list
                if(!in_array($leader_email, $recipients)) $recipients[] = $leader_email;

            } else {
                // Standard Leader-Requested Disband
                $notif_title = "Disband Request Approved: $c_name";
                $notif_msg = "The admin has approved your request to disband '$c_name'.\nFeedback: $feedback";
                $recipients = [$leader_email];
            }

            // 2. Send Notifications
            $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('System', ?, ?, ?, 'system')";
            $n_stmt = mysqli_prepare($conn, $notif_sql);
            
            foreach ($recipients as $email) {
                mysqli_stmt_bind_param($n_stmt, "sss", $email, $notif_title, $notif_msg);
                mysqli_stmt_execute($n_stmt);
            }

            // 3. Finally, Delete the Community
            $stmt = mysqli_prepare($conn, "DELETE FROM communities WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $group_id);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: ../views/manage_communities.php?msg=disbanded");
                exit();
            }
        } 
        elseif ($action === 'decline' && $group_id) {
            $upd_sql = "UPDATE communities SET status = 'approved', disband_reason = NULL WHERE id = ?";
            $stmt = mysqli_prepare($conn, $upd_sql);
            mysqli_stmt_bind_param($stmt, "i", $group_id);

            if (mysqli_stmt_execute($stmt)) {
                $title = "Disband Request Declined: $c_name";
                $msg = "The admin declined the disband request for '$c_name'.\n\nAdmin Feedback: $feedback";
                
                $noti_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('System', ?, ?, ?, 'system')";
                $n_stmt = mysqli_prepare($conn, $noti_sql);
                mysqli_stmt_bind_param($n_stmt, "sss", $leader_email, $title, $msg);
                mysqli_stmt_execute($n_stmt);

                header("Location: ../views/admin_dashboard.php?msg=restored#Disband");
                exit();
            }
        }
    }

    // --- CASE B: DELETE POST ACTION (Admin OR Leader) ---
    elseif (isset($_POST['delete_post'])) {
        $post_id = $_POST['post_id'] ?? null;
        $club_id = $_POST['club_id'] ?? null;

        // VERIFICATION: Check if user is Admin OR the Leader of this specific club
        $verify_sql = "SELECT leader_email FROM communities WHERE id = ?";
        $v_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($v_stmt, "i", $club_id);
        mysqli_stmt_execute($v_stmt);
        $v_res = mysqli_fetch_assoc(mysqli_stmt_get_result($v_stmt));
        
        $is_leader = ($v_res && $v_res['leader_email'] === $current_user);
        $is_admin = ($user_role === 'admin');

        if (!$is_admin && !$is_leader) {
            die("You do not have permission to moderate this community.");
        }

        if ($post_id) {
            // Get author and title for notification
            $get_post = mysqli_prepare($conn, "SELECT author_email, title FROM posts WHERE id = ?");
            mysqli_stmt_bind_param($get_post, "i", $post_id);
            mysqli_stmt_execute($get_post);
            $post_data = mysqli_fetch_assoc(mysqli_stmt_get_result($get_post));

            if ($post_data) {
                $author = trim($post_data['author_email']);
                $p_title = str_replace(["\r", "\n"], '', trim($post_data['title']));

                // Delete the post
                $del_stmt = mysqli_prepare($conn, "DELETE FROM posts WHERE id = ?");
                mysqli_stmt_bind_param($del_stmt, "i", $post_id);
                
                if (mysqli_stmt_execute($del_stmt)) {
                    // Notify the author
                    $mod_name = $is_admin ? "an Administrator" : "the Group Leader";
                    $notif_msg = "Your post '$p_title' was removed by $mod_name.";
                    
                    $n_stmt = mysqli_prepare($conn, "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('System', ?, 'Post Removed', ?, 'system')");
                    mysqli_stmt_bind_param($n_stmt, "ss", $author, $notif_msg);
                    mysqli_stmt_execute($n_stmt);

                    header("Location: ../views/club_dashboard.php?id=$club_id&msg=deleted");
                    exit();
                }
            }
        }
    }
 
    // --- CASE C: DELETE COMMENT ACTION ---
    elseif (isset($_POST['delete_comment'])) {
        $comment_id = $_POST['comment_id'] ?? null;
        $post_id = $_POST['post_id'] ?? null;
        $club_id = $_POST['club_id'] ?? null;

        // Security: Check if user is Admin or Club Leader
        $verify_sql = "SELECT leader_email FROM communities WHERE id = ?";
        $v_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($v_stmt, "i", $club_id);
        mysqli_stmt_execute($v_stmt);
        $v_res = mysqli_fetch_assoc(mysqli_stmt_get_result($v_stmt));
        
        $is_leader = ($v_res && $v_res['leader_email'] === $_SESSION['user']);
        $is_admin = ($_SESSION['role'] === 'admin');

        if ($is_admin || $is_leader) {
            $del_comment = mysqli_prepare($conn, "DELETE FROM post_comments WHERE id = ?");
            mysqli_stmt_bind_param($del_comment, "i", $comment_id);
            
            if (mysqli_stmt_execute($del_comment)) {
                header("Location: ../views/view_post.php?id=$post_id&msg=comment_deleted");
                exit();
            }
        } else {
            die("Unauthorized moderation attempt.");
        }
    }
}