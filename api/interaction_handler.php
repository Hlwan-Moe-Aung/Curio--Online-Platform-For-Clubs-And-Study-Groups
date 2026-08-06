<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$user_email = $_SESSION['user'];

// --- HANDLE LIKE (AJAX) ---
if (isset($_POST['action']) && $_POST['action'] === 'like') {
    // SECURED: No need for manual escape; prepared statements handle it
    $post_id = $_POST['post_id'];
    
    // Check if like exists using Prepared Statement
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM post_likes WHERE post_id = ? AND user_email = ?");
    mysqli_stmt_bind_param($check_stmt, "is", $post_id, $user_email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        // DELETE Like
        $del_stmt = mysqli_prepare($conn, "DELETE FROM post_likes WHERE post_id = ? AND user_email = ?");
        mysqli_stmt_bind_param($del_stmt, "is", $post_id, $user_email);
        mysqli_stmt_execute($del_stmt);
        echo json_encode(['status' => 'unliked']);
    } else {
        // INSERT Like
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO post_likes (post_id, user_email) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins_stmt, "is", $post_id, $user_email);
        mysqli_stmt_execute($ins_stmt);
        echo json_encode(['status' => 'liked']);
    }
    exit();
}

// --- HANDLE COMMENT (FORM SUBMISSION) ---
if (isset($_POST['comment_text'])) {
    $post_id = $_POST['post_id'];
    $comment_text = $_POST['comment_text'];

    if (!empty($comment_text)) {
        // SECURED: Prepared Statement for comment insertion
        $com_stmt = mysqli_prepare($conn, "INSERT INTO post_comments (post_id, user_email, comment_text) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($com_stmt, "iss", $post_id, $user_email, $comment_text);
        mysqli_stmt_execute($com_stmt);
        
        header("Location: ../views/view_post.php?id=$post_id&msg=comment_added");
    } else {
        header("Location: ../views/view_post.php?id=$post_id");
    }
    exit();
}