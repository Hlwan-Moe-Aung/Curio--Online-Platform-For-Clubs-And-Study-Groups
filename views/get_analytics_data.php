<?php
require_once('../includes/db.php');
header('Content-Type: application/json');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : 'all';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';

// Date Filter Logic
if ($month === 'all') {
    $date_filter = "YEAR(created_at) = '$year'";
} else {
    $m = intval($month);
    $date_filter = "YEAR(created_at) = '$year' AND MONTH(created_at) = '$m'";
}

// 1. KPI CARDS
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE $date_filter"))['c'] ?? 0;
$active_com = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM communities WHERE status = 'approved' AND $date_filter"))['c'] ?? 0;
$resolved_reps = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reports WHERE status IN ('resolved', 'dismissed') AND $date_filter"))['c'] ?? 0;

$posts_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE $date_filter"))['c'] ?? 0;
$comm_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM post_comments WHERE $date_filter"))['c'] ?? 0;
$total_act = $posts_c + $comm_c + $resolved_reps;

// 2. CATEGORY CHART
$cat_where = $date_filter;
if ($filter_type === 'club') $cat_where .= " AND type = 'club'";
if ($filter_type === 'study_group') $cat_where .= " AND type = 'study_group'";

$cat_query = mysqli_query($conn, "SELECT category, COUNT(*) as count FROM communities WHERE $cat_where GROUP BY category ORDER BY count DESC LIMIT 10");
$cat_labels = []; $cat_values = [];
while($row = mysqli_fetch_assoc($cat_query)) {
    $cat_labels[] = ucfirst($row['category']);
    $cat_values[] = (int)$row['count'];
}

// 3. COMMUNITY DONUT
$type_query = mysqli_query($conn, "SELECT type, COUNT(*) as count FROM communities WHERE $date_filter GROUP BY type");
$types = []; $type_counts = [];
while($row = mysqli_fetch_assoc($type_query)) {
    $types[] = ($row['type'] == 'club') ? 'Clubs' : 'Study Groups';
    $type_counts[] = (int)$row['count'];
}

// 4. TRENDS (Updated to include Comments)
$labels = []; $growth = []; $posts = []; $comments = []; $investigations = []; $total_reports_trend = [];

$range = ($month === 'all') ? 12 : cal_days_in_month(CAL_GREGORIAN, (int)$month, $year);

for ($i = 1; $i <= $range; $i++) {
    $labels[] = ($month === 'all') ? date('M', mktime(0, 0, 0, $i, 1)) : "Day $i";
    $dStr = ($month === 'all') ? "$year-" . str_pad($i, 2, "0", STR_PAD_LEFT) : "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($i, 2, "0", STR_PAD_LEFT);
    $fmt = ($month === 'all') ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE(created_at)";

    $growth[] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE $fmt = '$dStr'"))['c'];
    $posts[] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE $fmt = '$dStr'"))['c'];
    
    // NEW: Comments Trend
    $comments[] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM post_comments WHERE $fmt = '$dStr'"))['c'];
    
    // Total Reports
    $total_reports_trend[] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reports WHERE $fmt = '$dStr'"))['c'];
    // Resolved Reports
    $investigations[] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reports WHERE status IN ('resolved', 'dismissed') AND $fmt = '$dStr'"))['c'];
}

echo json_encode([
    'users' => number_format($total_users),
    'communities' => number_format($active_com),
    'reports' => number_format($resolved_reps),
    'activity' => number_format($total_act),
    'cat_labels' => $cat_labels,
    'cat_values' => $cat_values,
    'types' => $types,
    'type_counts' => $type_counts,
    'labels' => $labels,
    'growth' => $growth,
    'posts' => $posts,
    'comments' => $comments, // Added to JSON
    'investigations' => $investigations,
    'total_reports_trend' => $total_reports_trend
]);
?>