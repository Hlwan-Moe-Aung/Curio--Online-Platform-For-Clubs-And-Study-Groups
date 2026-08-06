<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user'])) {
    http_response_code(403); // Forbidden
    exit("Access Denied");
}

$email = $_SESSION['user'];
// Update the last activity timestamp to 'now'
$query = "UPDATE users SET last_activity = NOW() WHERE email = '$email'";
if (mysqli_query($conn, $query)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error']);
}

?>