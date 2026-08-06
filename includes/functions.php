<?php
// includes/functions.php

/**
 * Determines if a user is online based on their last activity timestamp.
 * Matches logic used in manage_users.php
 */

/**
 * Checks if a user is currently banned.
 * Used by both login.php and google-callback.php
 */
function checkUserBan($user) {
    if (!empty($user['ban_until'])) {
        $current_timestamp = new DateTime();
        $ban_timestamp = new DateTime($user['ban_until']);

        if ($ban_timestamp > $current_timestamp) {
            return "Your account is temporarily suspended until: " . date('M d, Y h:i A', strtotime($user['ban_until']));
        }
    }
    return false; // Not banned
}

/**
 * Sets standard session variables for the project.
 */
function establishUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_fullname'] = $user['fullname'];
}

function isUserOnline($last_activity) {
    if (!$last_activity) return false;
    
    try {
        $now = new DateTime();
        $last = new DateTime($last_activity);
        $interval = $now->diff($last);
        
        // Online if active within last 5 minutes
        return ($interval->i < 5 && $interval->h == 0 && $interval->d == 0);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Formats a database timestamp into a human-readable date.
 */
function formatDate($timestamp) {
    return date('M d, Y', strtotime($timestamp));
}

/**
 * A helper to sanitize output to prevent XSS.
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


function getRelativeTime($datetime) {
    // 1. Force the timezone to match your local time
    date_default_timezone_set('Asia/Dhaka'); 

    $now = new DateTime();
    $ago = new DateTime($datetime);
    
    // 2. Use the absolute difference
    $diff = $now->diff($ago);

    $units = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    $parts = [];
    foreach ($units as $key => $label) {
        if ($diff->$key > 0) {
            $parts[] = $diff->$key . ' ' . $label . ($diff->$key > 1 ? 's' : '');
        }
    }

    if (empty($parts)) {
        return 'just now';
    }

    // This will now correctly show "2 days ago" 
    // if the difference is more than 48 hours.
    $output = array_slice($parts, 0, 1); 
    return implode(', ', $output) . ' ago';
}



// includes/functions.php

/**
 * Builds a dynamic SQL query based on filters
 * @param string $base_sql The starting SELECT and FROM statement
 * @param array $filters Key-value pairs of column => value
 * @param string $search Search term
 * @param array $search_cols Columns to search against
 * @param string $sort_sql Pre-validated ORDER BY string
 * @param array $initial_params Existing parameters (like user_email)
 * @param string $initial_types Existing type string (like "s")
 */
function build_filtered_query($conn, $base_sql, $filters = [], $search = '', $search_cols = [], $sort_sql = '', $initial_params = [], $initial_types = "") {
    $params = $initial_params; // Start with the parameters you already have
    $types = $initial_types;   // Start with the types you already have
    $sql = $base_sql;

    // Handle Search
    if (!empty($search) && !empty($search_cols)) {
        $search_parts = [];
        foreach ($search_cols as $col) {
            $search_parts[] = "$col LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }
        $sql .= " AND (" . implode(" OR ", $search_parts) . ")";
    }

    // Handle Dropdown Filters
    foreach ($filters as $column => $value) {
        if (!empty($value) && $value !== 'Category') {
            $sql .= " AND $column = ?";
            $params[] = $value;
            $types .= "s";
        }
    }

    $sql .= " " . $sort_sql;

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


?>


