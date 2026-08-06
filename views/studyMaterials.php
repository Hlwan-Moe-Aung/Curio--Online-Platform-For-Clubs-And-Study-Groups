<?php
session_start();
include('../includes/db.php');
include('../includes/report_form.php');

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$user_email = $_SESSION['user'];
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Access control: member OR leader OR admin
$check_sql = "SELECT c.*,
             (SELECT COUNT(*) FROM members m WHERE m.community_id = c.id AND m.user_email = ?) as is_member
             FROM communities c
             WHERE c.id = ? AND c.status IN ('approved', 'disband_pending')";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "si", $user_email, $club_id);
mysqli_stmt_execute($stmt);
$check_res = mysqli_stmt_get_result($stmt);
$club = mysqli_fetch_assoc($check_res);

if (!$club || (
    $club['is_member'] == 0 &&
    $club['leader_email'] !== $user_email &&
    ($_SESSION['role'] ?? '') !== 'admin'
)) {
    header("Location: ../views/view_club.php?id=$club_id&error=not_a_member");
    exit();
}

$is_leader = ($club['leader_email'] === $user_email);

// Check if `status` column exists on studymaterial table
$has_status = false;
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM studymaterial LIKE 'status'");
if ($check_col && mysqli_num_rows($check_col) > 0) {
    $has_status = true;
}

// Get filter parameters (using same names as filter_component.php expects)
$type = isset($_GET['type']) ? $_GET['type'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters
$materials_sql = "SELECT sm.*, u.fullname AS uploader_name"
                  . ($has_status ? ", sm.status" : "") . "\n"
                  . "                  FROM studymaterial sm\n"
                  . "                  LEFT JOIN users u ON u.email = sm.uploaded_by\n"
                  . "                  WHERE sm.community_id = ?";
$params = [$club_id];
$types = "i";

if (!empty($search)) {
    $materials_sql .= " AND (sm.title LIKE ? OR sm.description LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($type)) {
    $materials_sql .= " AND sm.type = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($category)) {
    $materials_sql .= " AND sm.category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($date)) {
    $materials_sql .= " AND DATE(sm.uploaded_at) = ?";
    $params[] = $date;
    $types .= "s";
}

$materials_sql .= " ";

// If status column exists, only show approved materials to non-leaders
if ($has_status) {
    $materials_sql .= " AND sm.status = 'approved'";
}

$materials_sql .= " ORDER BY sm.uploaded_at DESC";

// Prepare and execute studyMaterial query
$materials_stmt = mysqli_prepare($conn, $materials_sql);
$materials = false;
if ($materials_stmt) {
    mysqli_stmt_bind_param($materials_stmt, $types, ...$params);
    mysqli_stmt_execute($materials_stmt);
    $materials = mysqli_stmt_get_result($materials_stmt);
} 

// Also fetch legacy community_files (if exists) so all shared files are shown
$legacy_files = false;
$cf_check = mysqli_query($conn, "SHOW TABLES LIKE 'community_files'");
if ($cf_check && mysqli_num_rows($cf_check) > 0) {
    $file_sql = "SELECT f.*, u.fullname AS uploader_name FROM community_files f LEFT JOIN users u ON u.email = f.uploaded_by WHERE f.community_id = ?";
    $file_stmt = mysqli_prepare($conn, $file_sql);
    if ($file_stmt) {
        mysqli_stmt_bind_param($file_stmt, "i", $club_id);
        mysqli_stmt_execute($file_stmt);
        $legacy_files = mysqli_stmt_get_result($file_stmt);
    }
}

// Get search parameter for filter_component

// Setup filter config for filter_component.php
$filter_config = [
    'action' => 'studyMaterials.php?id=' . $club_id,
    'placeholder' => 'Search materials...',
    'search_key' => 'search',
    'dropdowns' => [
        'type' => [
            'pdf' => 'PDF',
            'doc' => 'Document',
            'ppt' => 'Presentation',
            'video' => 'Video',
            'image' => 'Image',
            'other' => 'Other'
        ],
        'category' => [
            'notes' => 'Notes',
            'assignment' => 'Assignment',
            'lecture' => 'Lecture',
            'reference' => 'Reference',
            'exam' => 'Exam',
            'other' => 'Other'
        ]
    ],
    'show_date' => true 
];

renderReportModal($_SESSION['user']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['community_name']); ?> | Study Materials</title>
    <link rel="stylesheet" href="../assets/css/tab_system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/studyMaterials.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/club_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include '../includes/navbar.php' ?>

<div class="main-content" id="main">
    <?php if ($club['status'] === 'disband_pending'): ?>
        <div class="disband-banner">
            <div class="banner-content">
                <span class="banner-icon">⚠️</span>
                <div class="banner-text">
                    <strong>Disbandment Request Pending</strong>
                    <p>According to the request sent by Leader of this community, the administrator has been notified.</p>
                </div>
                <!-- <a href="contact_admin.php" class="banner-btn">Contact Admin</a> -->
            </div>
        </div>
    <?php endif; ?> 

    <div class="club-banner">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
            <div>
                <div class="sm-badge"><?php echo ucfirst($club['category']); ?></div>
                <h1 style="margin: 10px 0;"><?php echo htmlspecialchars($club['community_name']); ?></h1>
                <p style="margin: 0;">📁 Study Materials</p>
            </div>
            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                <?php if($is_leader): ?>
                    <a href="../views/manage_group.php?id=<?php echo $club_id; ?>" class="btn" style="background: white; color: #1f3c88;">Manage ⚙️</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/filter_component.php'; ?>

    <?php 
    $msg = $_GET['msg'] ?? '';
    $display_text = '';

    if ($msg == 'deleted') {
        $display_text = "Material deleted successfully";
    } elseif ($msg == 'updated') {
        $display_text = "Changes saved successfully";
    } elseif ($msg == 'Material uploaded successfully') {
        $display_text = "New material added!";
    } elseif ($msg == 'Material submitted for approval') { 
        $display_text = "Material submitted for approval";
    } elseif ($msg == 'repoted') { 
        $display_text = "Report received. Thank you!";
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

    <div class="dashboard-grid">
        <div class="sm-card">
            <div class="sm-card-header">
                <h3>Study Materials</h3>
            </div>
            <?php             
                // Build combined list from studyMaterial and legacy community_files
                $combined = [];
                if ($materials && mysqli_num_rows($materials) > 0) {
                    while ($row = mysqli_fetch_assoc($materials)) {
                        $row['source'] = 'studymaterial';
                        $combined[] = $row;
                    }
                }

                // Sort combined by uploaded_at desc
                usort($combined, function($a, $b){
                    $ta = strtotime($a['uploaded_at'] ?? 0);
                    $tb = strtotime($b['uploaded_at'] ?? 0);
                    return $tb <=> $ta;
                });

                if (count($combined) > 0):
            ?>
                <div class="sm-card-grid">
                    <?php foreach($combined as $m): 
                        if (empty($m['id'])) continue;
                        $is_author = ($m['uploaded_by'] === $_SESSION['user']);
                        $is_mod = ($is_leader || ($_SESSION['role'] ?? '') === 'admin');
                    ?>
                        <div class="sm-material-card" id="material_<?php echo $m['id']; ?>">
                            <div class="sm-card-body">
                                <div class="sm-card-header">
                                    <div class="sm-options" style="float: right;">
                                        <button class="sm-dots-btn" onclick="toggleMenu(event, <?php echo $m['id']; ?>)">⋮</button>
                                        
                                        <div id="menu-<?php echo $m['id']; ?>" class="sm-dropdown-content">
                                            <?php if ($is_author): ?>
                                                <a href="javascript:void(0)" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($m)); ?>)">Edit</a>
                                            <?php endif; ?>

                                            <?php if ($is_author || $is_mod): ?>
                                                <a href="../api/study_material_handler.php?action=delete&id=<?php echo $m['id']; ?>&community_id=<?php echo $club_id; ?>" 
                                                   class="delete-link" 
                                                   onclick="return confirm('Delete this material permanently?')">Delete</a>
                                            <?php endif; ?>

                                            <?php if (!$is_author): ?>
                                                <a href="javascript:void(0)" 
                                                   style="color: #d9534f;" 
                                                   onclick="openReportModal(<?php echo $m['id']; ?>, 'material')">
                                                    Report
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <h3 class="sm-title"><?php echo htmlspecialchars($m['title']); ?></h3>
                                    <div class="sm-badges">
                                        <span class="sm-badge-type">
                                            <?php echo strtoupper(htmlspecialchars($m['type'] ?? 'other')); ?>
                                        </span>
                                        <span class="sm-badge-category">
                                            <?php echo ucfirst(htmlspecialchars($m['category'] ?? 'other')); ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if (!empty($m['description'])): ?>
                                    <p class="sm-desc">
                                        <?php 
                                            $desc = htmlspecialchars($m['description']);
                                            echo (strlen($desc) > 120) ? substr($desc, 0, 120) . '...' : $desc; 
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <div class="sm-file-section">
                                    <?php if (!empty($m['file_path'])): ?>
                                        <a class="sm-download-btn" href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank">
                                            <span class="icon">📄</span> 
                                            <?php echo htmlspecialchars($m['original_name'] ?: 'View Material'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="sm-muted">No file available</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="sm-card-footer">

                                <div class="sm-uploader">
                                    <span class="user-icon">👤</span> 
                                    <?php echo htmlspecialchars($m['uploader_name'] ?? 'Unknown'); ?>
                                </div>
                                <div class="sm-date">
                                    <?php echo date('M d, Y', strtotime($m['uploaded_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="sm-empty">No study materials shared yet.</p>
            <?php endif; ?>
        </div>
        <div class="club-sidebar">
            <div class="feed-card">
                <h4>Leader</h4>
                <p>👤 <?php echo htmlspecialchars($club['leader_name']); ?> <span class="leader-badge">LEADER</span></p>
            </div>

            <div class="feed-card">
                <h4>Actions</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;"><a href="../views/members_list.php?id=<?php echo $club_id; ?>" style="text-decoration: none; color: #3a86ff;">👥 View Members</a></li>
                    <li style="margin-bottom: 10px;"><a href="../views/club_dashboard.php?id=<?php echo $club_id; ?>" style="text-decoration: none; color: #3a86ff;">💬 Discussions</a></li>
                    <?php if(!$is_leader): ?>
                        <li>
                            <a href="javascript:void(0)" 
                               style="text-decoration: none; color: #dc3545;" 
                               onclick="openLeaveModal()">
                               🚪 Leave Club
                            </a>
                        </li>
                    <?php endif; ?>
                    <li style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee;"><a href="javascript:void(0)" onclick="openReportModal('<?php echo $club_id; ?>', 'community')" style="text-decoration: none; color: #d9534f; font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">🚩 Report Community</a> </li>
                </ul>
            </div>
        </div>
    </div>
<button class="fab" onclick="openPostModal()">+</button>    
</div>


<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal-content" role="dialog" aria-labelledby="uploadTitle">
        <div class="modal-header">
            <h3 id="uploadTitle">Upload Material</h3>
            <button onclick="closePostModal()"> ✕ </button>
        </div>

        <form id="uploadForm" method="POST" action="../api/study_material_handler.php" enctype="multipart/form-data">
            <input type="hidden" name="community_id" value="<?php echo $club_id; ?>">
            <input type="hidden" name="type" id="materialType" value="">
            <input type="hidden" name="for_approval" id="forApproval" value="0">
            <input type="hidden" name="id" id="editMaterialId" value="">

            <div class="upload-form">
                <label>Material Name</label>
                <input type="text" name="title" id="materialTitle" required placeholder="Title of material">

                <label>Category</label>
                <select name="category" id="materialCategory" required>
                    <?php foreach($filter_config['dropdowns']['category'] as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="upload-method-toggle">
                    <button type="button" id="useFileBtn" class="btn" onclick="selectUploadMethod('file')">Upload File</button>
                    <button type="button" id="useUrlBtn" class="btn" onclick="selectUploadMethod('url')">Use URL</button>
                </div>

                <div id="fileUploadArea">
                    <label>Current File:</label>
                    <div class="current-asset-info" id="currentFileDisplay" style="display:none; margin-bottom:10px;">
                        <span id="currentFileName" style="font-weight:bold;"></span>
                        <button type="button" onclick="clearExistingSource('file')" style="color:red; border:none; background:none; cursor:pointer; margin-left:10px;">(Remove)</button>
                    </div>
                    
                    <label id="dropZoneLabel">Drag & Drop file here or click to browse</label>
                    <div id="dropZone" class="drop-zone">
                        <p id="dropText">No file chosen</p>
                        <input type="file" name="material_file" id="materialFile" style="display:none;" />
                    </div>
                </div>

                <div id="urlUploadArea" style="display:none;">
                    <label>File URL</label>
                    <input type="url" name="material_url" id="materialUrl" placeholder="https://example.com/file.pdf">
                </div>

                <div id="filePreview">Preview: <span id="previewText"></span></div>

                <div class="upload-form-btn-div">
                    <button type="button" class="btn" onclick="closePostModal()">Cancel</button>
                    <?php if ($is_leader || ($_SESSION['role'] ?? '') === 'admin'): ?>
                        <button type="button" class="btn" onclick="submitForUpload(0)" style="background:#1f3c88;color:white;border:none;">
                            Upload 
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn" onclick="submitForUpload(1)" style="background:#06b6d4;color:white;border:none;">
                            Submit 
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="leaveClubModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button type="button" class="btn-back" onclick="closeLeaveModal()">← Cancel</button>
        <div class="modal-header">
            <h3>Leave Community</h3>
        </div>
        <div class="form-container">
            <form action="../api/leave_handler.php" method="POST" class="modern-form">
                <input type="hidden" name="community_id" value="<?php echo $club_id; ?>">
                
                <p>Are you sure you want to leave <strong><?php echo htmlspecialchars($club['community_name']); ?></strong>?</p>
                
                <div class="form-group">
                    <label>Optional: Leave a message for the leader</label>
                    <textarea name="leave_message" rows="3" placeholder="Why are you leaving? (Optional)"></textarea>
                </div>

                <button type="submit" name="confirm_leave" class="btn-submit" style="background: #dc3545;">
                    Confirm Leave
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Check if the URL has a hash (e.g., #material_15)
    if(window.location.hash) {
        const targetId = window.location.hash.substring(1); // Removes the '#'
        const element = document.getElementById(targetId);
        
        if(element) {
            // 1. Add the highlight class
            element.classList.add('highlight-report');
            
            // 2. Smoothly scroll to the element
            element.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            
            // 3. Remove the highlight after 3 seconds so it doesn't stay red forever
            setTimeout(() => {
                element.classList.remove('highlight-report');
                element.style.transform = "scale(1)";
            }, 3000);
        }
    }
});
</script>

<script src="../assets/js/myscript.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/studyMaterials.js?v=<?php echo time(); ?>"></script>

</body>
</html>

