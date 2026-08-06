<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';

$current_role = $_SESSION['role'];
$teacher_id   = $_SESSION['id'] ?? 0;

// ----- इनलाइन भाषा अनुवाद ऐरे (नो बाहरी डिपेंडेंसी) -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$translations = [
    'en' => [
        'welcome' => 'Welcome', 'logout' => 'Logout', 'admin_panel' => 'Admin Panel', 'teacher_portal' => 'Teacher Portal',
        'overview' => 'Overview', 'm_students' => 'Manage Students', 'm_teachers' => 'Manage Teachers', 'm_classes' => 'Manage Classes', 'm_upload' => 'Upload Materials', 'm_reports' => 'View Reports',
        'm_t_classes' => 'My Classes & Students', 'm_t_upload' => 'Upload Class Materials', 'm_t_reports' => 'Learning Reports',
        'admin_desc' => "Manage your school's digital learning platform", 'teacher_desc' => "Monitor weekly subject activity and resource coverage trends",
        'stat_stu_all' => 'Total Enrolled', 'stat_stu_mine' => 'My Total Students', 'stat_files_all' => 'Learning Files', 'stat_files_mine' => 'My Published Files', 'stat_views' => 'Lesson Views (This Week)',
        'card_engagement' => 'Subject Engagement', 'engagement_desc' => 'Lessons read by students in the last 7 days.', 'th_subject' => 'Subject', 'th_views' => 'Views', 'no_views' => 'No views recorded.',
        'card_activity' => 'Recent Learning Activity', 'activity_desc' => 'Live feed of topics students are reading.', 'all_classes' => 'All Classes', 'btn_filter' => 'Filter',
        'th_roll' => 'Roll No', 'th_name' => 'Student Name', 'th_topic' => 'Topic / Chapter Learned', 'th_datetime' => 'Date & Time', 'no_activity' => 'No learning activity recorded yet.'
    ],
    'hi' => [
        'welcome' => 'स्वागत है', 'logout' => 'लॉगआउट', 'admin_panel' => 'एडमिन पैनल', 'teacher_portal' => 'शिक्षक पोर्टल',
        'overview' => 'अवलोकन', 'm_students' => 'छात्र प्रबंधन', 'm_teachers' => 'शिक्षक प्रबंधन', 'm_classes' => 'कक्षा प्रबंधन', 'm_upload' => 'सामग्री अपलोड करें', 'm_reports' => 'रिपोर्ट देखें',
        'm_t_classes' => 'मेरी कक्षाएं और छात्र', 'm_t_upload' => 'क्लास सामग्री अपलोड', 'm_t_reports' => 'लर्निंग रिपोर्ट',
        'admin_desc' => 'अपने स्कूल के डिजिटल लर्निंग प्लेटफॉर्म का प्रबंधन करें', 'teacher_desc' => 'साप्ताहिक विषय गतिविधि और संसाधन उपयोग प्रवृत्तियों की निगरानी करें',
        'stat_stu_all' => 'कुल नामांकित छात्र', 'stat_stu_mine' => 'मेरे कुल छात्र', 'stat_files_all' => 'कुल अध्ययन फ़ाइलें', 'stat_files_mine' => 'मेरी प्रकाशित फ़ाइलें', 'stat_views' => 'पाठ व्यूज (इस सप्ताह)',
        'card_engagement' => 'विषय के प्रति जुड़ाव', 'engagement_desc' => 'पिछले 7 दिनों में छात्रों द्वारा पढ़े गए पाठ।', 'th_subject' => 'विषय', 'th_views' => 'व्यूज', 'no_views' => 'कोई व्यूज रिकॉर्ड नहीं हुआ।',
        'card_activity' => 'हालिया सीखने की गतिविधि', 'activity_desc' => 'छात्र जो अध्याय पढ़ रहे हैं उसका लाइव फीड।', 'all_classes' => 'सभी कक्षाएं', 'btn_filter' => 'फिल्टर करें',
        'th_roll' => 'रोल नंबर', 'th_name' => 'छात्र का नाम', 'th_topic' => 'सीखा गया विषय / अध्याय', 'th_datetime' => 'दिनांक और समय', 'no_activity' => 'अभी तक कोई सीखने की गतिविधि दर्ज नहीं की गई है।'
    ]
];
$t = $translations[$lang];

// ----- 1. FETCH OVERVIEW STATISTICS -----
$stats = ['students' => 0, 'materials' => 0, 'lessons_viewed' => 0];

if ($current_role === 'admin') {
    $res = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='student'");
    if ($res) $stats['students'] = $res->fetch_assoc()['count'];

    $res = $conn->query("SELECT COUNT(*) AS count FROM lessons");
    if ($res) $stats['materials'] = $res->fetch_assoc()['count'];

    $res = $conn->query("SELECT COUNT(*) AS count FROM student_learning_log WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($res) $stats['lessons_viewed'] = $res->fetch_assoc()['count'];
} else {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT u.id) AS count FROM users u JOIN class_subjects cs ON u.class = cs.class_name WHERE u.role='student' AND cs.teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $stats['students'] = $stmt->get_result()->fetch_assoc()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM lessons l JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_name = cs.subject_name WHERE cs.teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $stats['materials'] = $stmt->get_result()->fetch_assoc()['count'];

    $stmt = $conn->prepare("SELECT COUNT(sll.id) AS count FROM student_learning_log sll JOIN lessons l ON sll.lesson_id = l.id JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_name = cs.subject_name WHERE cs.teacher_id = ? AND sll.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $stats['lessons_viewed'] = $stmt->get_result()->fetch_assoc()['count'];
}

// ----- 2. FETCH WEEKLY ENGAGEMENT -----
$engagement = [];
if ($current_role === 'admin') {
    $eng_query = "SELECT l.subject_name, COUNT(sll.id) as views FROM student_learning_log sll JOIN lessons l ON sll.lesson_id = l.id WHERE sll.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY l.subject_name ORDER BY views DESC";
} else {
    $eng_query = "SELECT l.subject_name, COUNT(sll.id) as views FROM student_learning_log sll JOIN lessons l ON sll.lesson_id = l.id JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_name = cs.subject_name WHERE cs.teacher_id = " . intval($teacher_id) . " AND sll.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY l.subject_name ORDER BY views DESC";
}
$res = $conn->query($eng_query);
if ($res) {
    while ($row = $res->fetch_assoc()) { $engagement[] = $row; }
}

// ----- 3. FETCH RECENT ACTIVITY -----
$filter_class = $_GET['filter_class'] ?? '';
$class_options = ['6', '7', '8', '9', '10', '11 Science', '11 Commerce', '11 Arts', '12 Science', '12 Commerce', '12 Arts'];

if ($current_role === 'admin') {
    $query = "SELECT sll.viewed_at, u.full_name, u.username, u.class, l.subject_name, l.chapter_name FROM student_learning_log sll JOIN users u ON sll.student_id = u.id JOIN lessons l ON sll.lesson_id = l.id";
    if ($filter_class !== '') { $query .= " WHERE u.class = '" . $conn->real_escape_string($filter_class) . "'"; }
} else {
    $query = "SELECT sll.viewed_at, u.full_name, u.username, u.class, l.subject_name, l.chapter_name FROM student_learning_log sll JOIN users u ON sll.student_id = u.id JOIN lessons l ON sll.lesson_id = l.id JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_name = cs.subject_name WHERE cs.teacher_id = " . intval($teacher_id);
    if ($filter_class !== '') { $query .= " AND u.class = '" . $conn->real_escape_string($filter_class) . "'"; }
}
$query .= " ORDER BY sll.viewed_at DESC LIMIT 50";

$activity_log = [];
$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) { $activity_log[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['m_reports']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 30px; }
        .stat-box { background: white; padding: 25px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; }
        .stat-icon { font-size: 30px; color: var(--accent-color); background: #eff6ff; width: 60px; height: 60px; display: flex; justify-content: center; align-items: center; border-radius: 50%; }
        .stat-info h3 { margin: 0; font-size: 24px; color: var(--primary-color); }
        .stat-info p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; font-weight: bold; }
        .reports-layout { display: grid; grid-template-columns: 1fr 2.5fr; gap: 20px; margin-top: 30px; }
        .report-card { background: white; padding: 25px; border-radius: 8px; border: 1px solid var(--border-color); }
        .filter-form { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .filter-form select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; }
        .filter-form button { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; }
        @media (max-width: 1024px) { .reports-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="dash-header" style="height: 75px; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
    <div class="header-title">
        <i class="fa-solid <?php echo $current_role === 'admin' ? 'fa-school' : 'fa-chalkboard-user'; ?>"></i>
        <span><?php echo $current_role === 'admin' ? $t['admin_panel'] : $t['teacher_portal']; ?> - <?php echo $t['welcome']; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
        <a href="logout.php" class="logout-btn" style="margin:0; padding: 4px 10px; font-size: 12px;"><i class="fa-solid fa-right-from-bracket"></i> <?php echo $t['logout']; ?></a>
        <form method="GET" style="margin: 0; padding: 0;">
            <?php if(!empty($filter_class)): ?><input type="hidden" name="filter_class" value="<?php echo htmlspecialchars($filter_class); ?>"><?php endif; ?>
            <select name="lang" onchange="this.form.submit()" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); border-radius: 4px; padding: 1px 6px; font-size: 11px; font-weight: 600; cursor: pointer; outline: none;">
                <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?> style="color: black;">English</option>
                <option value="hi" <?php echo $lang === 'hi' ? 'selected' : ''; ?> style="color: black;">हिंदी (Hindi)</option>
            </select>
        </form>
    </div>
</div>

<div class="dash-container">
    <div class="page-title">
        <h1><?php echo $current_role === 'admin' ? $t['admin_panel'] : $t['teacher_portal']; ?></h1>
        <p><?php echo $current_role === 'admin' ? $t['admin_desc'] : $t['teacher_desc']; ?></p>
    </div>

    <div class="text-menu-bar">
        <?php if ($current_role === 'admin'): ?>
            <a href="admin_dashboard.php" class="text-menu-item"><h2><?php echo $t['overview']; ?></h2></a>
            <a href="manage_students.php" class="text-menu-item"><h2><?php echo $t['m_students']; ?></h2></a>
            <a href="manage_teachers.php" class="text-menu-item"><h2><?php echo $t['m_teachers']; ?></h2></a>
            <a href="manage_classes.php" class="text-menu-item"><h2><?php echo $t['m_classes']; ?></h2></a>
            <a href="upload_lesson.php" class="text-menu-item"><h2><?php echo $t['m_upload']; ?></h2></a>
            <a href="view_reports.php" class="text-menu-item active"><h2><?php echo $t['m_reports']; ?></h2></a>
        <?php else: ?>
            <a href="teacher_dashboard.php" class="text-menu-item"><h2><?php echo $t['m_t_classes']; ?></h2></a>
            <a href="upload_lesson.php" class="text-menu-item"><h2><?php echo $t['m_t_upload']; ?></h2></a>
            <a href="view_reports.php" class="text-menu-item active"><h2><?php echo $t['m_t_reports']; ?></h2></a>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['students']; ?></h3>
                <p><?php echo $current_role === 'admin' ? $t['stat_stu_all'] : $t['stat_stu_mine']; ?></p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-cloud-arrow-down"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['materials']; ?></h3>
                <p><?php echo $current_role === 'admin' ? $t['stat_files_all'] : $t['stat_files_mine']; ?></p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['lessons_viewed']; ?></h3>
                <p><?php echo $t['stat_views']; ?></p>
            </div>
        </div>
    </div>

    <div class="reports-layout">
        <div class="report-card">
            <h3 style="margin-top: 0; color: var(--primary-color); font-size: 18px;"><i class="fa-solid fa-fire" style="color: #ea580c;"></i> <?php echo $t['card_engagement']; ?></h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;"><?php echo $t['engagement_desc']; ?></p>
            
            <?php if (count($engagement) > 0): ?>
                <table class="dash-table" style="width: 100%;">
                    <thead>
                        <tr><th><?php echo $t['th_subject']; ?></th><th><?php echo $t['th_views']; ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($engagement as $eng): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($eng['subject_name']); ?></strong></td>
                            <td><span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 13px;"><i class="fa-solid fa-eye"></i> <?php echo $eng['views']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: var(--text-muted); font-size: 14px;"><?php echo $t['no_views']; ?></p>
            <?php endif; ?>
        </div>

        <div class="report-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
                <div>
                    <h3 style="margin-top: 0; color: var(--primary-color);"><i class="fa-solid fa-bars-progress"></i> <?php echo $t['card_activity']; ?></h3>
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15pxStream;"><?php echo $t['activity_desc']; ?></p>
                </div>
                <form method="GET" class="filter-form">
                    <select name="filter_class">
                        <option value=""><?php echo $t['all_classes']; ?></option>
                        <?php foreach ($class_options as $cl): ?>
                            <?php $sel = ($filter_class == $cl) ? 'selected' : ''; ?>
                            <option value="<?php echo $cl; ?>" <?php echo $sel; ?>>Class <?php echo $cl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><?php echo $t['btn_filter']; ?></button>
                </form>
            </div>

            <?php if (count($activity_log) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="dash-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th><?php echo $t['th_roll']; ?></th>
                                <th><?php echo $t['th_name']; ?></th>
                                <th><?php echo $t['th_subject']; ?></th>
                                <th><?php echo $t['th_topic']; ?></th>
                                <th><?php echo $t['th_datetime']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_log as $log): ?>
                            <tr>
                                <td><span style="color: var(--accent-color); font-weight: bold;"><?php echo htmlspecialchars($log['username']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br><small style="color: var(--text-muted);">Class <?php echo htmlspecialchars($log['class']); ?></small></td>
                                <td><?php echo htmlspecialchars($log['subject_name']); ?></td>
                                <td><i class="fa-regular fa-circle-check" style="color: #10b981;"></i> <?php echo htmlspecialchars($log['chapter_name']); ?></td>
                                <td style="font-size: 13px; color: var(--text-muted);"><?php echo date('d M Y, h:i A', strtotime($log['viewed_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;"><?php echo $t['no_activity']; ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>