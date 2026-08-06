<?php
ob_start();
session_start();
include('../includes/google-config.php');
include('../includes/db.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        header("Location: signup.php?error=access_denied");
        exit();
    }

    $client->setAccessToken($token);
    $google_oauth = new Google\Service\Oauth2($client);
    $google_info = $google_oauth->userinfo->get();

    $email     = $google_info->email;
    $fullname  = $google_info->name;
    $google_id = $google_info->id;

    $stmt = mysqli_prepare($conn, "SELECT id, role, fullname FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        $update = mysqli_prepare($conn, "UPDATE users SET google_id = ?, last_activity = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update, "si", $google_id, $user['id']);
        mysqli_stmt_execute($update);
    } else {
        $insert = mysqli_prepare($conn, "INSERT INTO users (fullname, email, google_id, role) VALUES (?, ?, ?, 'user')");
        mysqli_stmt_bind_param($insert, "sss", $fullname, $email, $google_id);
        mysqli_stmt_execute($insert);
        
        $new_id = mysqli_insert_id($conn);
        $user = ['id' => $new_id, 'role' => 'user', 'fullname' => $fullname];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $email;
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_fullname'] = $user['fullname'];

    ob_end_clean();
    header("Location: ../index.php");
    exit();
}
?>