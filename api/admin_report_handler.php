<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'])) {
    $report_id = (int)$_POST['report_id'];
    $new_status = $_POST['status'];
    $admin_note = trim($_POST['admin_note']);
    $user_message = trim($_POST['user_message']);

    // --- STEP 1: FETCH ORIGINAL REPORT DATA FIRST ---
    $info_sql = "SELECT item_type, item_id, reporter_email FROM reports WHERE id = ?";
    $i_stmt = mysqli_prepare($conn, $info_sql);
    mysqli_stmt_bind_param($i_stmt, "i", $report_id);
    mysqli_stmt_execute($i_stmt);
    $report_data = mysqli_fetch_assoc(mysqli_stmt_get_result($i_stmt));

    if (!$report_data) {
        exit("Report not found.");
    }

    $reporter_email = $report_data['reporter_email'];
    $item_type = $report_data['item_type'];
    $item_id = $report_data['item_id'];

    // --- STEP 2: UPDATE THE REPORT STATUS ---
    $update_sql = "UPDATE reports SET status = ?, admin_note = ? WHERE id = ?";
    $upd_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($upd_stmt, "ssi", $new_status, $admin_note, $report_id);
    
    if (mysqli_stmt_execute($upd_stmt)) {
        
        // --- PART A: NOTIFY REPORTER (This is working for you) ---
        $rep_title = "Update on your report (#$report_id)";
        $rep_body = "Moderators have reviewed your report regarding a $item_type.\nStatus: " . ucfirst($new_status) . "\nNote: " . ($admin_note ?: "Action taken.");

        $notif_rep = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('system@admin.com', ?, ?, ?, 'feedback')";
        $nr_stmt = mysqli_prepare($conn, $notif_rep);
        mysqli_stmt_bind_param($nr_stmt, "sss", $reporter_email, $rep_title, $rep_body);
        mysqli_stmt_execute($nr_stmt);

        // --- PART B: NOTIFY CONTENT OWNER (The fix) ---
        if ($new_status === 'resolved') {
            $owner_email = null;
            $item_name = $item_type;

            if ($item_type === 'material') {
                // Verified table: studymaterial | column: uploaded_by
                $o_sql = "SELECT uploaded_by, title FROM studymaterial WHERE id = ?";
                $o_stmt = mysqli_prepare($conn, $o_sql);
                mysqli_stmt_bind_param($o_stmt, "i", $item_id);
                mysqli_stmt_execute($o_stmt);
                $res = mysqli_fetch_assoc(mysqli_stmt_get_result($o_stmt));
                if ($res) {
                    $owner_email = $res['uploaded_by'];
                    $item_name = "material: " . $res['title'];
                }
            }
            elseif ($item_type === 'post') {
                $owner_sql = "SELECT author_email, title FROM posts WHERE id = ?";
                $o_stmt = mysqli_prepare($conn, $owner_sql);
                mysqli_stmt_bind_param($o_stmt, "i", $report_data['item_id']);
                mysqli_stmt_execute($o_stmt);
                $owner_data = mysqli_fetch_assoc(mysqli_stmt_get_result($o_stmt));
                if($owner_data) {
                    $owner_email = $owner_data['author_email'];
                    $item_name = "post: " . $owner_data['title'];
                }
            }
            elseif ($item_type === 'user') {
                // If the user themselves is the target, they are the owner
                $owner_sql = "SELECT email FROM users WHERE id = ?";
                $o_stmt = mysqli_prepare($conn, $owner_sql);
                mysqli_stmt_bind_param($o_stmt, "i", $report_data['item_id']);
                mysqli_stmt_execute($o_stmt);
                $owner_data = mysqli_fetch_assoc(mysqli_stmt_get_result($o_stmt));
                if($owner_data) {
                    $owner_email = $owner_data['email'];
                    $item_name = "account profile";
                }
            }
            elseif ($item_type === 'community') {
                $owner_sql = "SELECT leader_email, community_name FROM communities WHERE id = ?";
                $o_stmt = mysqli_prepare($conn, $owner_sql);
                mysqli_stmt_bind_param($o_stmt, "i", $report_data['item_id']);
                mysqli_stmt_execute($o_stmt);
                $owner_data = mysqli_fetch_assoc(mysqli_stmt_get_result($o_stmt));
                if($owner_data) {
                    $owner_email = $owner_data['leader_email'];
                    $item_name = "community: " . $owner_data['community_name'];
                }
            }


            // SEND WARNING NOTIFICATION
            if (!empty($owner_email)) {
                $warn_title = "⚠️ SEVERE WARNING: Content Violation";
                $warn_body = "Moderators have taken action against your $item_name.\n\n";
                $warn_body .= "Admin Message: " . ($user_message ?: "Your content was found to be in violation of guidelines.");
                
                $notif_warn = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('system@admin.com', ?, ?, ?, 'report')";
                $nw_stmt = mysqli_prepare($conn, $notif_warn);
                mysqli_stmt_bind_param($nw_stmt, "sss", $owner_email, $warn_title, $warn_body);
                mysqli_stmt_execute($nw_stmt);
            }
        }

        header("Location: ../views/manage_reports.php?msg=report_updated");
        exit();
    }
}
?>