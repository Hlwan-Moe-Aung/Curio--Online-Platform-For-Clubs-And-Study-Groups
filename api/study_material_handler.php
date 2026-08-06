<?php
session_start();
include('../includes/db.php');

// Security Check
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_email = $_SESSION['user'];
$action = $_GET['action'] ?? 'create';

// --- ACTION: DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $info_sql = "SELECT sm.*, c.leader_email FROM studyMaterial sm 
                 JOIN communities c ON sm.community_id = c.id WHERE sm.id = ?";
    $stmt = mysqli_prepare($conn, $info_sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($data) {
        $is_author = ($data['uploaded_by'] === $user_email);
        $is_mod = ($data['leader_email'] === $user_email || ($_SESSION['role'] ?? '') === 'admin');

        if ($is_author || $is_mod) {
            mysqli_query($conn, "DELETE FROM studyMaterial WHERE id = $id");
            if ($is_mod && !$is_author) {
                $notif_msg = "A moderator removed your material: " . $data['title'];
                $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, 'Material Deleted', ?, 'alert')";
                $n_stmt = mysqli_prepare($conn, $notif_sql);
                mysqli_stmt_bind_param($n_stmt, "sss", $user_email, $data['uploaded_by'], $notif_msg);
                mysqli_stmt_execute($n_stmt);
            }
            header("Location: ../views/studyMaterials.php?id={$data['community_id']}&msg=deleted");
            exit();
        }
    }
}

// --- ACTION: UPDATE (EDIT) ---
elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $community_id = (int)$_POST['community_id'];
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    
    // 1. Fetch existing data first to keep the old file if no new one is provided
    $current_sql = "SELECT file_path, original_name, type FROM studyMaterial WHERE id = ?";
    $stmt = mysqli_prepare($conn, $current_sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $file_path = $current['file_path'];
    $original_name = $current['original_name'];
    $type = $current['type'];

    // 2. Check if a NEW file is being uploaded
    if (!empty($_FILES['material_file']['name']) && $_FILES['material_file']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES["material_file"]["name"], PATHINFO_EXTENSION));
        $upload_dir = "../uploads/";
        
        $original_name = $_FILES["material_file"]["name"];
        $new_filename = time() . '_' . uniqid() . '.' . $file_ext;
        
        if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $upload_dir . $new_filename)) {
            $file_path = "../uploads/" . $new_filename;
            $type = $file_ext;
        }
    } 
    
    // 3. Or check if a NEW URL is being provided
    elseif (!empty($_POST['material_url']) && $_POST['material_url'] !== $current['file_path']) {
        $file_path = trim($_POST['material_url']);
        $type = 'url';
        $original_name = basename(parse_url($file_path, PHP_URL_PATH)) ?: 'Link';
    }

    // 4. Update Database
    $update_sql = "UPDATE studyMaterial SET title = ?, category = ?, description = ?, file_path = ?, original_name = ?, type = ? WHERE id = ?";
    $up_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($up_stmt, "ssssssi", $title, $category, $description, $file_path, $original_name, $type, $id);
    
    if(mysqli_stmt_execute($up_stmt)) {
        header("Location: ../views/studyMaterials.php?id=$community_id&msg=updated");
        exit();
    }
}

// --- ACTION: CREATE (UPLOAD) ---
elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $community_id = isset($_POST['community_id']) ? (int)$_POST['community_id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $type = $_POST['type'] ?? ''; // This might be empty, we will auto-detect

    // 1. Basic Validation
    if (empty($title)) {
        header("Location: ../views/studyMaterials.php?id=$community_id&error=Please fill title");
        exit();
    }
    if ($community_id == 0) {
        header("Location: ../views/studyMaterials.php?id=$community_id&error=Invalid community");
        exit();
    }

    // 2. Access Check
    $check_sql = "SELECT c.*, (SELECT COUNT(*) FROM members m WHERE m.community_id = c.id AND m.user_email = ?) as is_member 
                  FROM communities c WHERE c.id = ? AND c.status IN ('approved', 'disband_pending')";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "si", $user_email, $community_id);
    mysqli_stmt_execute($stmt);
    $club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$club || ($club['is_member'] == 0 && $club['leader_email'] !== $user_email && ($_SESSION['role'] ?? '') !== 'admin')) {
        header("Location: ../views/view_club.php?id=$community_id&error=not_a_member");
        exit();
    }

    // 3. File/URL Handling & Auto-Type Detection
    $file_path = null;
    $original_name = null;
    $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];

    if (!empty($_FILES['material_file']['name']) && $_FILES['material_file']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES["material_file"]["name"], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_extensions)) {
            $upload_dir = "../uploads/";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $original_name = $_FILES["material_file"]["name"];
            $new_filename = time() . '_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $upload_dir . $new_filename)) {
                $file_path = "../uploads/" . $new_filename;
                $type = $file_ext; // AUTO-DETECT TYPE
            }
        } else {
            header("Location: ../views/studyMaterials.php?id=$community_id&error=Invalid file type");
            exit();
        }
    } elseif (!empty($_POST['material_url'])) {
        $url = trim($_POST['material_url']);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $file_path = $url;
            $original_name = basename(parse_url($url, PHP_URL_PATH)) ?: 'Remote Link';
            $url_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $type = in_array($url_ext, $allowed_extensions) ? $url_ext : 'other'; // AUTO-DETECT TYPE
        } else {
            header("Location: ../views/studyMaterials.php?id=$community_id&error=Invalid URL");
            exit();
        }
    } else {
        header("Location: ../views/studyMaterials.php?id=$community_id&error=Please provide a file or URL");
        exit();
    }

    // Final Type Fallback
    if (empty($type)) $type = 'other';

    // 4. Status Determination
    $for_approval = isset($_POST['for_approval']) && $_POST['for_approval'] == '1';
    $status = ($club['leader_email'] === $user_email || !$for_approval) ? 'approved' : 'pending';

    // 5. Database Insertion
    $insert_sql = "INSERT INTO studyMaterial (community_id, title, description, type, category, original_name, file_path, uploaded_by, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "issssssss", $community_id, $title, $description, $type, $category, $original_name, $file_path, $user_email, $status);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        if ($status === 'pending') {
            // Notification logic to Leader
            $l_email = $club['leader_email'];
            $n_title = "Approval Request: $title";
            $n_msg = "User $user_email uploaded a material to {$club['community_name']}. Review it here: ../views/studyMaterials.php?id=$community_id";
            $notif_sql = "INSERT INTO notifications (sender_email, receiver_email, title, message, type) VALUES (?, ?, ?, ?, 'approval')";
            $n_stmt = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($n_stmt, "ssss", $user_email, $l_email, $n_title, $n_msg);
            mysqli_stmt_execute($n_stmt);
            
            header("Location: ../views/studyMaterials.php?id=$community_id&msg=Material submitted for approval");
        } else {
            header("Location: ../views/studyMaterials.php?id=$community_id&msg=Material uploaded successfully");
        }
        exit();
    }
}
?> 
