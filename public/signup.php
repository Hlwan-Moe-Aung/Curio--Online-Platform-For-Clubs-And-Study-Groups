<?php

session_start();
include('../includes/db.php'); // Include the database connection
require_once '../includes/google-config.php';

$error = "";
$google_login_url = $client->createAuthUrl();
// values to repopulate form on error (password fields will remain empty)
$preserve_name = '';
$preserve_email = '';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $fullname = mysqli_real_escape_string($conn, $_POST['name']);
    $email_raw = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // preserve user inputs for redisplay if validation fails
    $preserve_name = htmlspecialchars($_POST['name'], ENT_QUOTES);
    $preserve_email = htmlspecialchars($email_raw, ENT_QUOTES);

    function get_password_errors($pwd){
        $errs = [];
    
        if (strlen($pwd) < 8) $errs[] = 'At least 8 characters';
        if (!preg_match('/[a-z]/', $pwd)) $errs[] = 'At least one lowercase letter';
        if (!preg_match('/[A-Z]/', $pwd)) $errs[] = 'At least one uppercase letter';
        if (!preg_match('/[0-9]/', $pwd)) $errs[] = 'At least one number';
        if (!preg_match('/[^A-Za-z0-9]/', $pwd)) $errs[] = 'At least one special character';
        if (preg_match('/\s/', $pwd)) $errs[] = 'No spaces allowed';
        $common = ['123456','password','123456789','qwerty','111111','12345678','abc123','password1','1234567','12345'];
        foreach ($common as $c) {
            if (strcasecmp($pwd, $c) === 0) { $errs[] = 'Password is too common'; break; }
        }
        return $errs;
    }

    // Validate email format first
    if (!filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else {
        // Additional DNS check: ensure domain has MX or A/AAAA record
        $domain = substr(strrchr($email_raw, '@'), 1);
        $dns_ok = false;
        if ($domain) {
            if (function_exists('checkdnsrr')) {
                if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) {
                    $dns_ok = true;
                }
            } elseif (function_exists('dns_get_record')) {
                $mx = @dns_get_record($domain, DNS_MX);
                $a = @dns_get_record($domain, DNS_A);
                $aaaa = @dns_get_record($domain, DNS_AAAA);
                if (!empty($mx) || !empty($a) || !empty($aaaa)) {
                    $dns_ok = true;
                }
            }
        }

        if (!$dns_ok) {
            $error = "Email domain does not appear to accept mail. Please check the address.";
        } else {
            $email = mysqli_real_escape_string($conn, strtolower($email_raw));

        // 1. Check if passwords match and meet security requirements
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            $pw_errors = get_password_errors($password);
            if (!empty($pw_errors)) {
                $error = 'Password requirements not met: ' . implode(', ', $pw_errors);
            } else {
            // 2. Check if email already exists
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($result) > 0){
                $error = "Email is already registered!";
            } else {
                // 3. Hash the password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 4. Insert into database using prepared statement
                $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (fullname, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
                mysqli_stmt_bind_param($insert_stmt, "sss", $fullname, $email, $hashed_password);

                if ($insert_stmt && mysqli_stmt_execute($insert_stmt)) {
                    $new_id = mysqli_insert_id($conn);
                    $_SESSION['user_id'] = $new_id;
                    $_SESSION['user'] = $email;
                    $_SESSION['role'] = 'user';
                    $_SESSION['user_fullname'] = $fullname;
                    header("Location: ../views/clubs.php");
                    exit();
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | Curio</title>
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/login_signup.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="auth-box">
    <h2>Create Account</h2>
    <p>Join the Curio community today</p>
    
    <?php if (isset($error) && $error): ?>
        <div class="error-msg" style="color: #d9534f; background: #f2dede; padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; text-align: left;">
            ⚠️ <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="name" placeholder="Full Name" required value="<?php echo $preserve_name; ?>">
        <input type="email" name="email" placeholder="University Email" required value="<?php echo $preserve_email; ?>">
        
        <div class="pw-toggle">
            <input type="password" id="password" name="password" placeholder="Create Password" required>
            <button type="button" class="toggle-pw-btn" data-target="password" aria-label="Show password"></button>
        </div>
        
        <div class="pw-toggle">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="button" class="toggle-pw-btn" data-target="confirm_password" aria-label="Show confirm password"></button>
        </div>
        
        <button type="submit" class="btn">Create Account</button>
    </form>

    <div class="divider"><span>OR</span></div>

    <a href="<?php echo $google_login_url; ?>" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/><path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" fill="#34A853"/><path d="M3.964 10.706c-.18-.54-.282-1.117-.282-1.706 0-.589.102-1.166.282-1.706V4.963H.957a8.996 8.996 0 000 8.074l3.007-2.331z" fill="#FBBC05"/><path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.391 2.012.957 4.963l3.007 2.331C4.672 5.164 6.656 3.58 9 3.58z" fill="#EA4335"/></svg>
        Sign up with Google
    </a>

    <div class="footer-links">
        <p>Already have an account? <a href="../public/login.php">Login here</a></p>
        <a href="../index.php" style="font-size: 12px; color: #b2bec3; font-weight: 400;">← Back to Home</a>
    </div>
</div>

<script>
    (function(){
        const eyeSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeOffImg = '<?php echo "../assets/img/eyeoff.jpg"; ?>';

        document.querySelectorAll('.toggle-pw-btn').forEach(function(btn){
            btn.innerHTML = eyeSVG;
            btn.addEventListener('click', function(){
                var targetId = btn.getAttribute('data-target');
                var el = document.getElementById(targetId);
                if (!el) return;
                
                if (el.type === 'password'){
                    el.type = 'text';
                    btn.innerHTML = `<img src="${eyeOffImg}" alt="Hide" style="width:20px; height:20px; filter: grayscale(1) opacity(0.5);">`;
                    btn.setAttribute('aria-label','Hide password');
                } else {
                    el.type = 'password';
                    btn.innerHTML = eyeSVG;
                    btn.setAttribute('aria-label','Show password');
                }
            });
        });
    })();
</script>

</body>
</html>
