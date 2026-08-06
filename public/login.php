<?php
session_start();
include('../includes/db.php');
require_once '../includes/google-config.php';

$error = "";
$google_login_url = $client->createAuthUrl();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            
            // 1. Initialize access flag
            $login_allowed = false; 

            // 2. Check Ban Status
            if (!empty($user['ban_until'])) {
                $current_timestamp = new DateTime();
                $ban_timestamp = new DateTime($user['ban_until']);

                if ($ban_timestamp > $current_timestamp) {
                    $error = "Your account is temporarily suspended until: " . date('M d, Y h:i A', strtotime($user['ban_until']));
                    $login_allowed = false; // Explicitly deny
                } else {
                    $login_allowed = true; // Ban expired
                }
            } else {
                $login_allowed = true; // No ban exists
            }

            // 3. ONLY set Session variables if login is allowed
            if ($login_allowed) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_fullname'] = $user['fullname'];

                if ($user['role'] === 'admin') {
                    header("Location: ../views/statistics.php");
                } else {
                    header("Location: ../views/clubs.php");
                }
                exit();
            }
            // If $login_allowed is false, the code will just continue 
            // to the HTML below and display the $error message.
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with that email!";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Curio</title>
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/login_signup.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="auth-box">
    <h2>Welcome Back</h2>
    <p>Enter your details to access your account</p>

    <?php if (isset($error) && $error): ?>
        <div class="error-msg">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="email" name="email" placeholder="Email address" required>
        
        <div class="pw-toggle">
            <input type="password" id="password" name="password" placeholder="Password" required>
            <button type="button" class="toggle-pw-btn" data-target="password" aria-label="Show password"></button>
        </div>
        
        <button type="submit" class="btn">Login to Curio</button>
    </form>

    <div class="divider"><span>OR</span></div>

    <a href="<?php echo $google_login_url; ?>" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/><path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" fill="#34A853"/><path d="M3.964 10.706c-.18-.54-.282-1.117-.282-1.706 0-.589.102-1.166.282-1.706V4.963H.957a8.996 8.996 0 000 8.074l3.007-2.331z" fill="#FBBC05"/><path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.391 2.012.957 4.963l3.007 2.331C4.672 5.164 6.656 3.58 9 3.58z" fill="#EA4335"/></svg>
        Continue with Google
    </a>
    
    <div class="footer-links">
        <p>No account? <a href="../public/signup.php">Create one</a></p>
        <a href="../index.php" style="font-size: 12px; color: #b2bec3; font-weight: 400;">← Back to Home</a>
    </div>
</div>

<script>
(function() {
    // 1. Define the icons
    const eyeSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffImg = '<?php echo "../assets/img/eyeoff.jpg"; ?>';

    // 2. Initialize the buttons
    document.querySelectorAll('.toggle-pw-btn').forEach(function(btn) {
        // Set the initial icon
        btn.innerHTML = eyeSVG;
        
        btn.addEventListener('click', function() {
            // Find the input: either via data-target or just looking for the previous input element
            const targetId = btn.getAttribute('data-target') || 'password'; 
            const passwordInput = document.getElementById(targetId);
            
            if (!passwordInput) return;

            if (passwordInput.type === 'password') {
                // Switch to Visible
                passwordInput.type = 'text';
                btn.innerHTML = `<img src="${eyeOffImg}" alt="Hide" style="width:20px; height:20px; filter: grayscale(1) opacity(0.6);">`;
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                // Switch to Hidden
                passwordInput.type = 'password';
                btn.innerHTML = eyeSVG;
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });
})();
</script>

</body>
</html>