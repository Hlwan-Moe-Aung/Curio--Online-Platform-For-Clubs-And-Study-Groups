<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $reporter_email = $_SESSION['user'];
    $item_id = (int)$_POST['item_id'];
    $item_type = mysqli_real_escape_string($conn, $_POST['item_type']);
    $description = trim($_POST['description']);
    $community_id = (int)$_POST['community_id'];

    $reason_category = "General";

    if (empty($item_type)) {
    $item_type = 'unknown'; 
}
    if (isset($_POST['reasons']) && is_array($_POST['reasons'])) {
        $reason_category = implode(", ", $_POST['reasons']);
    }


    // Evidence Upload Logic...
    $evidence_path = null;
    if (isset($_FILES['report_evidence']) && $_FILES['report_evidence']['error'] === 0) {
        $upload_dir = "../uploads/evidence/";
        $file_ext = strtolower(pathinfo($_FILES["report_evidence"]["name"], PATHINFO_EXTENSION));
        $new_filename = "report_" . time() . "_" . uniqid() . "." . $file_ext;
        if (move_uploaded_file($_FILES["report_evidence"]["tmp_name"], $upload_dir . $new_filename)) {
            $evidence_path = $new_filename;
        }
    }

    $report_sql = "INSERT INTO reports (reporter_email, item_type, item_id, reason_category, description, evidence_file, status) 
                   VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = mysqli_prepare($conn, $report_sql);
    mysqli_stmt_bind_param($stmt, "ssisss", $reporter_email, $item_type, $item_id, $reason_category, $description, $evidence_path);

    if (mysqli_stmt_execute($stmt)) {
        // Notify Admins Logic...
        $admin_query = "SELECT email FROM users WHERE role = 'admin'";
        $admin_res = mysqli_query($conn, $admin_query);
        $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type, status) VALUES (?, ?, ?, ?, 'report', 'unread')";
        $n_stmt = mysqli_prepare($conn, $notif_sql);

        while ($admin = mysqli_fetch_assoc($admin_res)) {
            $msg = "New report filed by $reporter_email regarding $item_type #$item_id.";
            mysqli_stmt_bind_param($n_stmt, "ssss", $reporter_email, $admin['email'], $reason_category, $msg);
            mysqli_stmt_execute($n_stmt);
        }

        if ($item_type === 'post') {
            header("Location: ../views/view_post.php?id=$item_id&msg=reported");
        } elseif ($item_type === 'user') {
            header("Location: ../views/members_list.php?id=$community_id&msg=reported");
        } elseif ($item_type === 'community') {
            header("Location: ../views/club_dashboard.php?id=$community_id&msg=reported");
        } else {
            // Default for study materials
            header("Location: ../views/studyMaterials.php?id=$community_id&msg=reported");
        }
        exit();
    }
}

?>