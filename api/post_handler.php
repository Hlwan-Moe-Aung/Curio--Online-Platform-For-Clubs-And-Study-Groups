<?php
session_start();
include('../includes/db.php');

// 1. Check if user is logged in and the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user'])) {
    
    $user_email = $_SESSION['user'];
    $action = $_POST['action'] ?? 'create'; 
    $group_id = $_POST['group_id'] ?? null;

    if (!$group_id) {
        die("Error: Group ID is missing.");
    }

    // 2. Get Club/Leader Information for Permission Checks
    $leader_stmt = mysqli_prepare($conn, "SELECT leader_email FROM communities WHERE id = ?");
    mysqli_stmt_bind_param($leader_stmt, "i", $group_id);
    mysqli_stmt_execute($leader_stmt);
    $club_result = mysqli_stmt_get_result($leader_stmt);
    $club_data = mysqli_fetch_assoc($club_result);
    
    if (!$club_data) {
        die("Error: Community not found.");
    }

    $is_leader = ($club_data['leader_email'] === $user_email);
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

    // --- CASE: EDIT POST ---
    if ($action === 'edit' && isset($_POST['post_id'])) {
        $post_id = $_POST['post_id'];
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';

        // Security: Fetch existing post to check authorship
        $post_check_stmt = mysqli_prepare($conn, "SELECT author_email FROM posts WHERE id = ?");
        mysqli_stmt_bind_param($post_check_stmt, "i", $post_id);
        mysqli_stmt_execute($post_check_stmt);
        $post_data = mysqli_fetch_assoc(mysqli_stmt_get_result($post_check_stmt));

        if (!$post_data) {
            die("Error: Post not found.");
        }

        $is_author = ($post_data['author_email'] === $user_email);

        // Permission check
        if ($is_author || $is_leader || $is_admin) {
            
            if (!empty($_FILES['post_image']['name'])) {
                $image_name = time() . '_' . basename($_FILES['post_image']['name']);
                move_uploaded_file($_FILES['post_image']['tmp_name'], "../uploads/" . $image_name);
                
                $update_query = "UPDATE posts SET title = ?, content = ?, post_image = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssi", $title, $content, $image_name, $post_id);
            } else {
                $update_query = "UPDATE posts SET title = ?, content = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $post_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                header("Location: ../views/club_dashboard.php?id=$group_id&msg=updated");
                exit();
            }
        } else {
            die("Unauthorized to edit this post.");
        }
    } 

    // --- CASE: DELETE POST ---
    elseif ($action === 'delete' && isset($_POST['post_id'])) {
        $post_id = (int)$_POST['post_id'];

        $check_stmt = mysqli_prepare($conn, "SELECT author_email, post_image FROM posts WHERE id = ?");
        mysqli_stmt_bind_param($check_stmt, "i", $post_id);
        mysqli_stmt_execute($check_stmt);
        $post_data = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

        if (!$post_data) {
            die("Error: Post not found.");
        }

        $is_author = ($post_data['author_email'] === $user_email);

        if ($is_author || $is_leader || $is_admin) {
            // Delete physical file
            if (!empty($post_data['post_image'])) {
                $file_path = "../uploads/" . $post_data['post_image'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            $delete_query = "DELETE FROM posts WHERE id = ?";
            $del_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($del_stmt, "i", $post_id);

            if (mysqli_stmt_execute($del_stmt)) {
                header("Location: ../views/club_dashboard.php?id=$group_id&msg=deleted");
                exit();
            }
        } else {
            die("Unauthorized to delete this post.");
        }
    }

    // --- CASE: CREATE POST (Default) ---
    else {
        // Use Null Coalescing to prevent "Undefined Index" warnings
        $title = $_POST['title'] ?? null;
        $content = $_POST['content'] ?? null;
        $visibility = $_POST['post_visibility'] ?? 'public'; 
        
        if (!$title || !$content) {
            die("Error: Title and Content are required for new posts.");
        }

        $status = ($is_leader || $is_admin) ? 'approved' : 'pending_approval';

        $image_name = null;
        if (!empty($_FILES['post_image']['name'])) {
            $image_name = time() . '_' . basename($_FILES['post_image']['name']);
            move_uploaded_file($_FILES['post_image']['tmp_name'], "../uploads/" . $image_name);
        }

        $query = "INSERT INTO posts (community_id, author_email, title, content, post_image, type, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "issssss", $group_id, $user_email, $title, $content, $image_name, $visibility, $status);
        
        if (mysqli_stmt_execute($stmt)) {
            $msg = ($status === 'approved') ? "published" : "pending";
            header("Location: ../views/club_dashboard.php?id=$group_id&msg=$msg");
            exit();
        } else {
            die("Database Error: " . mysqli_error($conn));
        }
    }
} else {
    // Redirect if someone tries to access the file directly via URL
    header("Location: ../index.php");
    exit();
}
?>