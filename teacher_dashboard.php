<?php
session_start();

// Guard rail: Verify user is logged in and is a teacher
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
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

// Get the logged-in teacher's ID from the session
$teacher_id = $_SESSION['id'] ?? 0; 

// ----- 1. FETCH ALL CLASSES ASSIGNED TO THIS TEACHER -----
$assigned_classes = [];
$class_stmt = $conn->prepare("SELECT DISTINCT class_name FROM class_subjects WHERE teacher_id = ? ORDER BY class_name ASC");
$class_stmt->bind_param("i", $teacher_id);
$class_stmt->execute();
$class_res = $class_stmt->get_result();
while ($row = $class_res->fetch_assoc()) {
    $assigned_classes[] = $row['class_name'];
}

// ----- 2. HANDLE CLASS FILTER SELECTION -----
// Default to the first assigned class if none is selected by the teacher
$selected_class = $_GET['class_view'] ?? ($assigned_classes[0] ?? '');

// ----- 3. FETCH STUDENTS BELONGING TO THE SELECTED CLASS -----
$students = [];
if (!empty($selected_class)) {
    $student_stmt = $conn->prepare("SELECT username, full_name, gender, father_name, contact_number, photo_path FROM users WHERE role='student' AND class=? ORDER BY full_name ASC");
    $student_stmt->bind_param("s", $selected_class);
    $student_stmt->execute();
    $student_res = $student_stmt->get_result();
    while ($row = $student_res->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['teacher_portal']; ?> - Digital Learning Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .class-tabs { display: flex; gap: 10px; margin-top: 25px; flex-wrap: wrap; }
        .class-tab-btn { text-decoration: none; padding: 10px 20px; background: #e2e8f0; color: var(--text-main); border-radius: 6px; font-weight: bold; font-size: 14px; transition: all 0.2s ease; border: 1px solid transparent; }
        .class-tab-btn:hover { background: #cbd5e1; }
        .class-tab-btn.active { background: var(--accent-color); color: white; border-color: var(--accent-color); }
        .dash-table img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px; }
    </style>
</head>
<body>

    <div class="dash-header" style="height: 75px; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
        <div class="header-title">
            <i class="fa-solid fa-chalkboard-user"></i>
            <span><?php echo $t['teacher_portal']; ?> - <?php echo $t['welcome']; ?>, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Instructor'); ?></span>
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
            <a href="logout.php" class="logout-btn" style="margin:0; padding: 4px 10px; font-size: 12px;"><i class="fa-solid fa-right-from-bracket"></i> <?php echo $t['logout']; ?></a>
            <form method="GET" style="margin: 0; padding: 0;">
                <?php if(!empty($selected_class)): ?>
                    <input type="hidden" name="class_view" value="<?php echo htmlspecialchars($selected_class); ?>">
                <?php endif; ?>
                <select name="lang" onchange="this.form.submit()" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); border-radius: 4px; padding: 1px 6px; font-size: 11px; font-weight: 600; cursor: pointer; outline: none;">
                    <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?> style="color: black;">English</option>
                    <option value="hi" <?php echo $lang === 'hi' ? 'selected' : ''; ?> style="color: black;">हिंदी (Hindi)</option>
                </select>
            </form>
        </div>
    </div>

<div class="dash-container">
    <div class="page-title">
        <h1><?php echo $t['teacher_portal']; ?></h1>
        <p><?php echo $t['track_lessons']; ?></p>
    </div>

    <div class="text-menu-bar">
        <a href="teacher_dashboard.php" class="text-menu-item active"><h2><?php echo $t['menu_t_classes']; ?></h2></a>
        <a href="upload_lesson.php" class="text-menu-item"><h2><?php echo $t['menu_t_upload']; ?></h2></a>
        <a href="view_reports.php" class="text-menu-item"><h2><?php echo $t['menu_t_reports']; ?></h2></a>
    </div>

    <h3 style="margin-top: 35px; color: var(--primary-color); margin-bottom: 5px;">
        <i class="fa-solid fa-layer-group"></i> <?php echo $t['assigned_classes']; ?>
    </h3>
    <p style="font-size: 13px; color: var(--text-muted); margin: 0;"><?php echo $t['select_tab_desc']; ?></p>

    <div class="class-tabs">
        <?php if (!empty($assigned_classes)): ?>
            <?php foreach ($assigned_classes as $class_tab): ?>
                <a href="teacher_dashboard.php?class_view=<?php echo urlencode($class_tab); ?>" 
                   class="class-tab-btn <?php echo ($selected_class === $class_tab) ? 'active' : ''; ?>">
                    Class <?php echo htmlspecialchars($class_tab); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #b91c1c; background: #fee; padding: 12px; border-radius: 6px; width: 100%;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $t['no_classes_mapped']; ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!empty($selected_class)): ?>
        <div style="background: white; padding: 25px; margin-top: 25px; border-radius: 8px; border: 1px solid var(--border-color);">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--primary-color);">
                <i class="fa-solid fa-users"></i> Class <?php echo htmlspecialchars($selected_class); ?> 
                <span style="font-size: 14px; color: var(--text-muted); font-weight: normal;">(<?php echo count($students); ?>)</span>
            </h3>

            <?php if (count($students) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="dash-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th><?php echo $t['roll_no']; ?></th>
                                <th><?php echo $t['full_name']; ?></th>
                                <th>Gender</th>
                                <th><?php echo $t['father_name']; ?></th>
                                <th>Contact Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td><strong style="color: var(--primary-color);"><?php echo htmlspecialchars($s['username']); ?></strong></td>
                                <td>
                                    <?php if (!empty($s['photo_path']) && file_exists($s['photo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($s['photo_path']); ?>" alt="Profile">
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-user" style="font-size: 30px; color: #cbd5e1; vertical-align: middle; margin-right: 8px;"></i>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($s['gender']); ?></td>
                                <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                                <td><i class="fa-solid fa-phone" style="font-size: 12px; color: var(--text-muted);"></i> <?php echo htmlspecialchars($s['contact_number']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px 0; margin: 0;"><?php echo $t['no_students']; ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>