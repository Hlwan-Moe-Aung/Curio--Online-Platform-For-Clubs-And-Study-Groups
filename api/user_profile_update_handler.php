<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    exit("Access Denied");
}

function get_password_errors($pwd){
    $errs = [];
    if (strlen($pwd) < 8) $errs[] = '8+ chars';
    if (!preg_match('/[a-z]/', $pwd)) $errs[] = 'lowercase';
    if (!preg_match('/[A-Z]/', $pwd)) $errs[] = 'uppercase';
    if (!preg_match('/[0-9]/', $pwd)) $errs[] = 'number';
    if (!preg_match('/[^A-Za-z0-9]/', $pwd)) $errs[] = 'special char';
    return $errs;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $current_user_email = $_SESSION['user'];

    // 1. Validation Logic
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            header("Location: ../views/dashboard.php?msg=mismatch");
            exit();
        }

        if (!empty(get_password_errors($new_password))) {
            header("Location: ../views/dashboard.php?msg=weak_pw");
            exit();
        }
    }

    $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND email != ?");
    $email_check->bind_param("ss", $new_email, $current_user_email);
    $email_check->execute();
    if ($email_check->get_result()->num_rows > 0) {
        header("Location: ../views/dashboard.php?msg=email_taken");
        exit();
    }

    // 2. Execution Logic
    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, password = ? WHERE email = ?");
        $stmt->bind_param("ssss", $new_name, $new_email, $hashed, $current_user_email);
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE email = ?");
        $stmt->bind_param("sss", $new_name, $new_email, $current_user_email);
    }

    if ($stmt->execute()) {
        $_SESSION['user'] = $new_email;
        header("Location: ../views/dashboard.php?msg=success");
        exit();
    }
}