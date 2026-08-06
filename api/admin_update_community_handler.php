<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = intval($_POST['community_id']);

    // --- PRE-FETCH: Get current values to compare with new changes ---
    $info_stmt = $conn->prepare("SELECT leader_email, community_name, category, description FROM communities WHERE id = ?");
    $info_stmt->bind_param("i", $cid);
    $info_stmt->execute();
    $current = $info_stmt->get_result()->fetch_assoc();
    
    if (!$current) die("Community not found.");
    
    $leader_email = $current['leader_email'];
    $old_name = $current['community_name'];
    $old_cat  = ucfirst($current['category']);
    $old_desc = $current['description'];

    $send_notif = function($conn, $email, $title, $msg) {
        $n_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES ('System', ?, ?, ?, 'system')";
        $n_stmt = $conn->prepare($n_sql);
        $n_stmt->bind_param("sss", $email, $title, $msg);
        $n_stmt->execute();
    };

    // --- CASE: Name Update ---
    if (isset($_POST['update_name'])) {
        $new_name = $_POST['community_name'];
        if ($old_name !== $new_name) {
            $stmt = $conn->prepare("UPDATE communities SET community_name = ? WHERE id = ?");
            $stmt->bind_param("si", $new_name, $cid);
            if ($stmt->execute()) {
                $msg = "An admin renamed the community.\nOld Name: $old_name\nNew Name: $new_name";
                $send_notif($conn, $leader_email, "Name Updated: $old_name", $msg);
            }
        }
    }

    // --- CASE: Leader Change ---
    if (isset($_POST['change_leader'])) {
        $new_leader_email = $_POST['new_leader_email'];

        if ($leader_email !== $new_leader_email) {
            $upd_stmt = $conn->prepare("UPDATE communities SET leader_email = ? WHERE id = ?");
            $upd_stmt->bind_param("si", $new_leader_email, $cid);
            $upd_stmt->execute();

            // Demote Old Leader
            $check_mem = $conn->prepare("SELECT 1 FROM members WHERE community_id = ? AND user_email = ?");
            $check_mem->bind_param("is", $cid, $leader_email);
            $check_mem->execute();
            if (!$check_mem->get_result()->fetch_assoc()) {
                $demote_stmt = $conn->prepare("INSERT INTO members (community_id, user_email, joined_at) VALUES (?, ?, NOW())");
                $demote_stmt->bind_param("is", $cid, $leader_email);
                $demote_stmt->execute();
            }

            // Remove New Leader from Member table
            $remove_mem = $conn->prepare("DELETE FROM members WHERE community_id = ? AND user_email = ?");
            $remove_mem->bind_param("is", $cid, $new_leader_email);
            $remove_mem->execute();

            // Notify Old Leader
            $msg_old = "Administrative role swap for '$old_name'.\nPrevious Role: Leader\nCurrent Role: Member";
            $send_notif($conn, $leader_email, "Role Change: $old_name", $msg_old);

            // Notify New Leader
            $msg_new = "You have been promoted for '$old_name'.\nPrevious Role: Member\nCurrent Role: Leader";
            $send_notif($conn, $new_leader_email, "Promoted to Leader: $old_name", $msg_new);
        }
    }

    // --- CASE: Category Update ---
    if (isset($_POST['update_type'])) {
        $new_cat_raw = $_POST['category'];
        $new_cat = ucfirst($new_cat_raw);
        if ($old_cat !== $new_cat) {
            $stmt = $conn->prepare("UPDATE communities SET category = ? WHERE id = ?");
            $stmt->bind_param("si", $new_cat_raw, $cid);
            if ($stmt->execute()) {
                $msg = "An admin changed the category for '$old_name'.\nWas: $old_cat\nNow: $new_cat";
                $send_notif($conn, $leader_email, "Category Changed: $old_name", $msg);
            }
        }
    }

    // --- CASE: Description ---
    if (isset($_POST['update_desc'])) {
        $new_desc = $_POST['description'];
        if ($old_desc !== $new_desc) {
            $stmt = $conn->prepare("UPDATE communities SET description = ? WHERE id = ?");
            $stmt->bind_param("si", $new_desc, $cid);
            if ($stmt->execute()) {
                // Since descriptions can be long, we show a snippet of the old one
                $old_snippet = (strlen($old_desc) > 50) ? substr($old_desc, 0, 47) . "..." : $old_desc;
                $msg = "Admin updated the description for '$old_name'.\n\nOld: \"$old_snippet\"\n\nNew: \"$new_desc\"";
                $send_notif($conn, $leader_email, "Description Updated: $old_name", $msg);
            }
        }
    }

    // --- CASE: Delete Picture ---
    if (isset($_POST['delete_pic'])) {
        $stmt = $conn->prepare("UPDATE communities SET profile_pic = NULL WHERE id = ?");
        $stmt->bind_param("i", $cid);
        if ($stmt->execute()) {
            $send_notif($conn, $leader_email, "Photo Removed: $old_name", "An admin has deleted the profile picture for '$old_name'. Please upload a new one if necessary.");
        }
    }

    header("Location: ../views/view_community_detail.php?id=$cid&msg=updated");
    exit();
}