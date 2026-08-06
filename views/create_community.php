<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Capture raw input (No need for mysqli_real_escape_string with Prepared Statements)
    $name = $_POST['community_name'];
    $type = $_POST['type'];
    $category = $_POST['category'];
    $desc = $_POST['description'];
    $purpose = $_POST['purpose'];
    $appeal = $_POST['appeal'];
    
    $l_name = $_SESSION['user_fullname'] ?? 'Community Member';
    $l_email = $_SESSION['user'];

    // 2. Prepare the Insert Statement for the community
    $sql = "INSERT INTO communities (leader_name, leader_email, community_name, type, category, description, purpose, appeal) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssss", $l_name, $l_email, $name, $type, $category, $desc, $purpose, $appeal);

    if (mysqli_stmt_execute($stmt)) {
        $last_id = mysqli_insert_id($conn);
        $title = "Request Sent: " . $name;
        $notif_msg = "You have submitted a request to create the $type '$name'. Please wait for Admin approval.";
        
        // 3. Prepare the Insert Statement for the notification
        $notif_sql = "INSERT INTO notifications (receiver_email, sender_email, title, message, type, status) 
                      VALUES (?, 'system', ?, ?, 'creation', 'unread')";
        
        $notif_stmt = mysqli_prepare($conn, $notif_sql);
        mysqli_stmt_bind_param($notif_stmt, "sss", $l_email, $title, $notif_msg);
        
        mysqli_stmt_execute($notif_stmt);

        if ($type === 'club') {
            $redirect_page = "../views/clubs.php";
        } elseif ($type === 'study_group') {
            $redirect_page = "../views/study_groups.php";
        } else {
            $redirect_page = "../views/dashboard.php"; // Fallback
        }

        $final_url = $redirect_page . "?msg=" . urlencode("success");

        header("Location: " . $final_url);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Community | Curio</title>
    <link rel="stylesheet" href="../assets/css/group_creation_review.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="full-page-container">
    <div class="request-form-container">
        <a href="#" class="back-btn" onclick="history.back()" style="text-decoration: none; color: #888; font-size: 14px;">← Back </a>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>🚀 Request Community Creation</h2>
            <a href="../public/rules.php" class="rules-help-btn" title="View Community Rules">?</a>
        </div>
        
        
        <form method="POST">
            <div class="form-group">
                <label>Community Name</label>
                <input type="text" name="community_name" placeholder="e.g. Science Club" required>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Type</label>
                    <select name="type" id="type" onchange="updateCategories()" required>
                        <option value="">Select Type</option>
                        <option value="club">Club</option>
                        <option value="study_group">Study Group</option>
                    </select>
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>Category</label>
                    <select name="category" id="category" required>
                        <option value="">Select Category</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Public summary..." required></textarea>
            </div>

            <div class="form-group">
                <label>Purpose</label>
                <textarea name="purpose" placeholder="Goals..." required></textarea>
            </div>

            <div class="form-group">
                <label>Appeal to Admin</label>
                <textarea name="appeal"></textarea>
            </div>
            
            <div class="creation-form-btn">
                <button type="submit" class="btn-submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>

</body>
</html>