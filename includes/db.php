<?php
date_default_timezone_set('Asia/Yangon');
$host = "db";
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

$conn = mysqli_connect($host, $user, $pass, $dbname);
mysqli_query($conn, "SET time_zone = '+06:30'");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (isset($_SESSION['user'])) {
    $current_user_email = $_SESSION['user'];
    $update_activity_sql = "UPDATE users SET last_activity = NOW() WHERE email = ?";
    $stmt_activity = mysqli_prepare($conn, $update_activity_sql);
    mysqli_stmt_bind_param($stmt_activity, "s", $current_user_email);
    mysqli_stmt_execute($stmt_activity);
}

?>