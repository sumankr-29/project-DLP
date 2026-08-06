<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';

// ----- DYNAMIC LANGUAGE INTEGRATION ENGINE -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$t = require "languages/dashboard-lang/{$lang}.php";

// ----- 1. FETCH DASHBOARD STATISTICS -----
$stats = [
    'students' => 0,
    'teachers' => 0,
    'classes' => 0,
    'materials' => 0
];

$res = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='student'");
if ($res) $stats['students'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='teacher'");
if ($res) $stats['teachers'] = $res->fetch_assoc()['count'];

// Count distinct classes that have subjects assigned
$res = $conn->query("SELECT COUNT(DISTINCT class_name) AS count FROM class_subjects");
if ($res) $stats['classes'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(*) AS count FROM lessons");
if ($res) $stats['materials'] = $res->fetch_assoc()['count'];


// ----- 2. FETCH RECENTLY ADDED STUDENTS -----
$recent_students = [];
$query = "SELECT id, username, full_name, class, father_name, created_at, photo_path 
          FROM users 
          WHERE role='student' 
          ORDER BY id DESC LIMIT 5"; // Gets the 5 newest students

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['admin_panel']; ?> - GSSS Maranga</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        /* Stats Grid Styling */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 30px; }
        .stat-box { background: white; padding: 25px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .stat-icon { font-size: 24px; width: 55px; height: 55px; display: flex; justify-content: center; align-items: center; border-radius: 50%; }
        .stat-info h3 { margin: 0; font-size: 26px; color: var(--primary-color); }
        .stat-info p { margin: 5px 0 0; color: var(--text-muted); font-size: 13px; font-weight: bold; }
        
        /* Table Action Icons */
        .dash-table .action-icons a { margin: 0 8px; font-size: 16px; }
        
        /* Dark Action Buttons */
        .quick-actions { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }
        .btn-dark { background: #0f172a; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s; }
        .btn-dark:hover { transform: translateY(-2px); background: #1e293b; }
    </style>
</head>
<body>

    <div class="dash-header" style="height: 75px; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
        <div class="header-title">
            <i class="fa-solid fa-school"></i>
            <span><?php echo $t['admin_panel']; ?> - <?php echo $t['welcome']; ?>, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
            <a href="logout.php" class="logout-btn" style="margin:0; padding: 4px 10px; font-size: 12px;"><i class="fa-solid fa-right-from-bracket"></i> <?php echo $t['logout']; ?></a>
            <form method="GET" style="margin: 0; padding: 0;">
                <select name="lang" onchange="this.form.submit()" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); border-radius: 4px; padding: 1px 6px; font-size: 11px; font-weight: 600; cursor: pointer; outline: none;">
                    <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?> style="color: black;">English</option>
                    <option value="hi" <?php echo $lang === 'hi' ? 'selected' : ''; ?> style="color: black;">हिंदी (Hindi)</option>
                </select>
            </form>
        </div>
    </div>

    <div class="dash-container">
        <div class="page-title">
            <h1><?php echo $t['admin_panel']; ?></h1>
            <p><?php echo $t['manage_platform']; ?></p>
        </div>
        
        <div class="text-menu-bar">
            <a href="admin_dashboard.php" class="text-menu-item active">
                <h2><?php echo $t['menu_overview']; ?></h2>
            </a>
            <a href="manage_students.php" class="text-menu-item">
                <h2><?php echo $t['menu_students']; ?></h2>
            </a>
            <a href="manage_teachers.php" class="text-menu-item">
                <h2><?php echo $t['menu_teachers']; ?></h2>
            </a>
            <a href="manage_classes.php" class="text-menu-item">
                <h2><?php echo $t['menu_classes']; ?></h2>
            </a>
            <a href="upload_lesson.php" class="text-menu-item">
                <h2><?php echo $t['menu_upload']; ?></h2>
            </a>
            <a href="view_reports.php" class="text-menu-item">
                <h2><?php echo $t['menu_reports']; ?></h2>
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fa-solid fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['students']; ?></h3>
                    <p><?php echo $t['stat_students']; ?></p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background: #dcfce7; color: #16a34a;"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['teachers']; ?></h3>
                    <p><?php echo $t['stat_teachers']; ?></p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background: #fef9c3; color: #ca8a04;"><i class="fa-solid fa-door-open"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['classes']; ?></h3>
                    <p><?php echo $t['stat_classes']; ?></p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background: #fce7f3; color: #db2777;"><i class="fa-solid fa-folder-open"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['materials']; ?></h3>
                    <p><?php echo $t['stat_materials']; ?></p>
                </div>
            </div>
        </div>

        <div style="background: white; padding: 25px; margin-top: 30px; border-radius: 8px; border: 1px solid var(--border-color);">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--primary-color);">
                <i class="fa-solid fa-clock-rotate-left"></i> <?php echo $t['recent_students']; ?>
            </h3>
            
            <?php if (count($recent_students) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="dash-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th><?php echo $t['roll_no']; ?></th>
                                <th><?php echo $t['full_name']; ?></th>
                                <th><?php echo $t['class']; ?></th>
                                <th><?php echo $t['father_name']; ?></th>
                                <th><?php echo $t['admission_date']; ?></th>
                                <th><?php echo $t['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_students as $s): ?>
                            <tr>
                                <td><strong style="color: var(--primary-color);"><?php echo htmlspecialchars($s['username']); ?></strong></td>
                                <td>
                                    <?php if(!empty($s['photo_path'])): ?>
                                        <img src="<?php echo $s['photo_path']; ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px;">
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                </td>
                                <td>Class <?php echo htmlspecialchars($s['class']); ?></td>
                                <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                                <td class="action-icons">
                                    <a href="manage_students.php?edit_id=<?php echo $s['id']; ?>" style="color: #2563eb;" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="manage_students.php?delete_id=<?php echo $s['id']; ?>" 
                                       onclick="return confirm('Delete this student permanently?');" 
                                       style="color: #ef4444;" title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted);"><?php echo $t['no_students']; ?></p>
            <?php endif; ?>
        </div>

        <div class="quick-actions">
            <a href="manage_students.php" class="btn-dark"><i class="fa-solid fa-plus"></i> <?php echo $t['add_student']; ?></a>
            <a href="manage_teachers.php" class="btn-dark"><i class="fa-solid fa-plus"></i> <?php echo $t['add_teacher']; ?></a>
            <a href="manage_classes.php" class="btn-dark"><i class="fa-solid fa-plus"></i> <?php echo $t['add_class']; ?></a>
            <a href="upload_lesson.php" class="btn-dark"><i class="fa-solid fa-upload"></i> <?php echo $t['upload_material']; ?></a>
        </div>

    </div>

</body>
</html>