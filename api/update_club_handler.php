<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../views/clubs.php");
    exit();
}

$user_email = $_SESSION['user'];
// Get raw ID; prepared statements will handle the safety
$group_id = $_POST['community_id'] ?? $_POST['group_id'] ?? 0;

// 1. Verify Ownership again for security
// SECURED: Using placeholders to prevent unauthorized access via ID manipulation
$check_sql = "SELECT id FROM communities WHERE id = ? AND leader_email = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "is", $group_id, $user_email);
mysqli_stmt_execute($stmt);
$check_res = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($check_res) == 0) {
    die("Unauthorized access.");
}

// 2. Handle File Upload
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
    $target_dir = "../uploads/";
    
    // Create unique filename to prevent overwriting
    $file_ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
    $new_filename = time() . "_" . uniqid() . "." . $file_ext;
    $target_file = $target_dir . $new_filename;

    // Validate if it is an image
    $check_img = getimagesize($_FILES["profile_pic"]["tmp_name"]);
    if($check_img !== false) {
        // Extra Security: Restrict allowed extensions
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'jfif'];
        if (in_array($file_ext, $allowed)) {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                
                // 3. Update Database with new filename
                // SECURED: Prepared Statement for update
                $update_sql = "UPDATE communities SET profile_pic = ? WHERE id = ?";
                $upd_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($upd_stmt, "si", $new_filename, $group_id);

                if (mysqli_stmt_execute($upd_stmt)) {
                    header("Location: ../views/manage_group.php?id=$group_id&msg=Profile_picture_updated!");
                } else {
                    echo "Error updating record: " . mysqli_error($conn);
                }
            } else {
                echo "Error moving file to uploads folder. Check folder permissions.";
            }
        } else {
            echo "Invalid file extension. Only JPG, PNG, and GIF are allowed.";
        }
    } else {
        echo "File is not an image.";
    }
} else {
    header("Location: ../views/manage_group.php?id=$group_id&msg=No file selected.");
}

// --- LOGIC 2: Handle Profile Info Update (Name, Category, etc.) ---
if (isset($_POST['update_community'])) {
    $name = trim($_POST['community_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $purpose = trim($_POST['purpose']);

    // SECURED: Prepared Statement for data update
    $update_sql = "UPDATE communities SET community_name = ?, category = ?, description = ?, purpose = ? WHERE id = ?";
    $upd_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($upd_stmt, "ssssi", $name, $category, $description, $purpose, $group_id);

    if (mysqli_stmt_execute($upd_stmt)) {
        // Redirect with success for the Toast notification
        header("Location: ../views/manage_group.php?id=$group_id&msg=success");
        exit();
    } else {
        die("Error updating record: " . mysqli_error($conn));
    }
}

// If no specific action was triggered, go back
header("Location: ../views/manage_group.php?id=$group_id");
exit();
?>