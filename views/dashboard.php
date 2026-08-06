<?php
session_start();
include('../includes/db.php');


if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

// 1. Determine which user to display
// Check GET first (for admin links), then POST, then fallback to current session
if (isset($_GET['user_email']) && $_SESSION['role'] === 'admin') {
    $email = $_GET['user_email'];
} else {
    $email = $_POST['current_session_email'] ?? $_SESSION['user'];
}
// 2. Fetch User Details safely
$query = "SELECT * FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $userData = mysqli_fetch_assoc($result);
} else {
    // Better debugging: show which email failed
    die("Error: User profile not found for: " . htmlspecialchars($email));
}

// 2. Fetch Communities the user leads/joined

// Fetch Clubs (Owned OR Joined)
// SECURED: Prepared Statement with GROUP BY handled safely
$clubs_query = "
    SELECT c.* FROM communities c
    LEFT JOIN members m ON c.id = m.community_id
    WHERE (c.leader_email = ? OR m.user_email = ?) 
    AND c.type = 'club' AND c.status = 'approved'
    GROUP BY c.id";

$stmt_clubs = mysqli_prepare($conn, $clubs_query);
mysqli_stmt_bind_param($stmt_clubs, "ss", $email, $email);
mysqli_stmt_execute($stmt_clubs);
$joinedClubs = mysqli_stmt_get_result($stmt_clubs);

// Fetch Study Groups (Owned OR Joined)
// SECURED: Prepared Statement
$groups_query = "
    SELECT c.* FROM communities c
    LEFT JOIN members m ON c.id = m.community_id
    WHERE (c.leader_email = ? OR m.user_email = ?) 
    AND c.type = 'study_group' AND c.status = 'approved'
    GROUP BY c.id";

$stmt_groups = mysqli_prepare($conn, $groups_query);
mysqli_stmt_bind_param($stmt_groups, "ss", $email, $email);
mysqli_stmt_execute($stmt_groups);
$joinedStudyGroups = mysqli_stmt_get_result($stmt_groups);

// 3. Fetch Ongoing Requests
$user_email = $userData['email'];

// Creation & Disband Requests (from communities table)
$creation_query = "SELECT id, community_name, status, type FROM communities 
                   WHERE leader_email = ? AND status IN ('pending', 'disband_pending')";
$stmt_c = mysqli_prepare($conn, $creation_query);
mysqli_stmt_bind_param($stmt_c, "s", $user_email);
mysqli_stmt_execute($stmt_c);
$pending_comms = mysqli_stmt_get_result($stmt_c);

// Membership Requests
$member_query = "SELECT r.id, c.community_name, r.status FROM membership_requests r 
                 JOIN communities c ON r.community_id = c.id 
                 WHERE r.user_email = ? AND r.status = 'pending'";
$stmt_m = mysqli_prepare($conn, $member_query);
mysqli_stmt_bind_param($stmt_m, "s", $user_email);
mysqli_stmt_execute($stmt_m);
$pending_memberships = mysqli_stmt_get_result($stmt_m);

// Private Post Requests
$post_query = "SELECT p.id, p.title, c.community_name FROM posts p 
               JOIN communities c ON p.community_id = c.id 
               WHERE p.author_email = ? AND p.status = 'pending_approval'";
$stmt_p = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($stmt_p, "s", $user_email);
mysqli_stmt_execute($stmt_p);
$pending_posts = mysqli_stmt_get_result($stmt_p);

// Study Material Requests
$material_query = "SELECT s.id, s.title, c.community_name FROM studymaterial s 
                   JOIN communities c ON s.community_id = c.id 
                   WHERE s.uploaded_by = ? AND s.status = 'pending'";
$stmt_s = mysqli_prepare($conn, $material_query);
mysqli_stmt_bind_param($stmt_s, "s", $user_email);
mysqli_stmt_execute($stmt_s);
$pending_materials = mysqli_stmt_get_result($stmt_s);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile | Curio</title>
    <link rel="stylesheet" href="../assets/css/dashboards.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<?php 
$msg = $_GET['msg'] ?? '';
$display_text = '';

if ($msg == 'success') {
    $display_text = "Profile updated successfully";
} elseif ($msg == 'mismatch') {
    $display_text = "Error: Passwords do not match";
} elseif ($msg == 'weak_pw') {
    // Detailed text for the user
    $display_text = "Password must be 8+ characters and include uppercase, lowercase, numbers, and special characters.";
} elseif ($msg == 'email_taken') {
    $display_text = "Error: This email is already in use";
} elseif ($msg == 'deleted') {
    $display_text = "You have successfully deleted your requests";
}
?>

<?php if ($display_text): ?>
    <div id="yt-toast" class="toast">
        <div class="toast-content">
            <?= htmlspecialchars($display_text) ?>
        </div>
        <div class="toast-actions">
            <button class="toast-close" onclick="closeToast()" aria-label="Close">
                <svg class="progress-ring" width="24" height="24">
                    <circle class="progress-ring__circle" stroke="#3ea6ff" stroke-width="2" fill="transparent" r="10" cx="12" cy="12"/>
                </svg>
                <span class="close-icon">✕</span>
            </button>
        </div>
    </div>
<?php endif; ?>

<div class="main-content" id="main">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($userData['fullname']); ?>!</h1>
            <p>Status: <span class="badge"><?php echo strtoupper($userData['role']); ?></span></p>
        </div>

        <div class="form-container">
            <form method="POST" class="modern-form" action="../api/user_profile_update_handler.php">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($userData['fullname']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address (Cannot be changed)</label>
                    <input type="email" name="email" 
                           value="<?php echo htmlspecialchars($userData['email']); ?>" 
                           readonly 
                           style="background-color: #f0f0f0; cursor: not-allowed;" 
                           required>
                </div>

                <div class="form-group pw-toggle" style="position: relative;">
                    <label>New Password (Leave blank to keep current)</label>
                    <input type="password" id="password" name="password" placeholder="********" style="width:100%; padding-right:45px;">
                    <button type="button" class="toggle-pw-btn" data-target="password" style="position:absolute; right:8px; top:32px; background:none; border:none; cursor:pointer;">Show</button>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Must be 8+ chars with uppercase, lowercase, numbers, and symbols.
                    </small>
                </div>

                <div class="form-group pw-toggle" style="position: relative;">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="********" style="width:100%; padding-right:45px;">
                    <button type="button" class="toggle-pw-btn" data-target="confirm_password" style="position:absolute; right:8px; top:32px; background:none; border:none; cursor:pointer;">Show</button>
                </div>

                <button type="submit" name="update_profile" class="btn-submit">Update Information</button>
            </form>
        </div>

        <?php if ($role !== 'admin'): ?>
        <div class="requests-container" style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px;">Ongoing Requests</h3>
            
            <?php 
            $has_requests = false;
            
            // Helper function to render a request row
            function renderRequestRow($title, $info, $type, $id) {
                global $has_requests;
                $has_requests = true;
                echo "
                <div class='request-item' style='display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 8px; background: #fff;'>
                    <div>
                        <span style='font-size: 0.75rem; font-weight: bold; text-transform: uppercase; color: #3ea6ff; display: block;'>$type</span>
                        <span style='font-weight: 500;'>$title</span>
                        <span style='color: #666; font-size: 0.9rem;'> in $info</span>
                    </div>
                    <form action='../api/cancel_request_handler.php' method='POST' onsubmit='return confirm(\"Cancel this request?\");'>
                        <input type='hidden' name='request_id' value='$id'>
                        <input type='hidden' name='request_type' value='$type'>
                        <button type='submit' style='background: #ffebee; color: #d32f2f; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem;'>Delete</button>
                    </form>
                </div>";
            }

            // Loop through all results
            while($row = mysqli_fetch_assoc($pending_comms)) {
                $label = ($row['status'] == 'pending') ? "Creation" : "Disband";
                renderRequestRow($row['community_name'], ucfirst($row['type']), $label, $row['id']);
            }
            while($row = mysqli_fetch_assoc($pending_memberships)) {
                renderRequestRow("Join Request", $row['community_name'], "Membership", $row['id']);
            }
            while($row = mysqli_fetch_assoc($pending_posts)) {
                renderRequestRow($row['title'], $row['community_name'], "Private Post", $row['id']);
            }
            while($row = mysqli_fetch_assoc($pending_materials)) {
                renderRequestRow($row['title'], $row['community_name'], "Material Upload", $row['id']);
            }

            if (!$has_requests) echo "<p style='color: #888; font-style: italic;'>No active requests.</p>";
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    const eyeSVG = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffImg = '../assets/img/eyeoff.jpg'; 
    document.querySelectorAll('.toggle-pw-btn').forEach(function(btn){
        btn.innerHTML = eyeSVG;
        btn.addEventListener('click', function(){
            var targetId = btn.getAttribute('data-target');
            var el = document.getElementById(targetId);
            if (el.type === 'password'){
                el.type = 'text';
                btn.innerHTML = '<img src="' + eyeOffImg + '" width="20" height="20" alt="Hide">';
            } else {
                el.type = 'password';
                btn.innerHTML = eyeSVG;
            }
        });
    });
})();
</script>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>



</body>
</html>