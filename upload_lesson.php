<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';

$current_role = $_SESSION['role'];
$teacher_id   = $_SESSION['id'] ?? 0;

// ----- इनलाइन भाषा अनुवाद ऐरे -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$translations = [
    'en' => [
        'welcome' => 'Welcome', 'logout' => 'Logout', 'admin_panel' => 'Admin Panel', 'teacher_portal' => 'Teacher Portal',
        'overview' => 'Overview', 'm_students' => 'Manage Students', 'm_teachers' => 'Manage Teachers', 'm_classes' => 'Manage Classes', 'm_upload' => 'Upload Materials', 'm_reports' => 'View Reports',
        'm_t_classes' => 'My Classes & Students', 'm_t_upload' => 'Upload Class Materials', 'm_t_reports' => 'Learning Reports',
        'pub_title' => 'Material Publisher', 'pub_desc' => 'Publish video and reference documents tied strictly to valid curriculum tracks',
        'edit_m' => 'Modify Allocation Parameters', 'add_m' => 'Publish New Resource Context',
        'lbl_track' => 'Target Class & Subject Allocation Track *', 'choose_track' => '-- Choose Allocation Track --',
        'lbl_chapter' => 'Chapter / Topic Identifier *', 'lbl_pdf' => 'Notes File Attachment (Max 5MB)', 
        'lbl_video' => 'Local Video Stream Attachment (Max 30 Mins / MP4)', 'lbl_drive' => 'Shared Drive Asset / Cloud External Video link URL',
        'btn_commit' => 'Commit Metadata Save', 'btn_publish' => 'Publish Resource Layout', 'btn_cancel' => 'Cancel',
        'library_title' => 'My Uploaded Content Library', 'th_class' => 'Class & Course', 'th_topic' => 'Chapter Title Context', 'th_Attachment' => 'Attachments', 'th_actions' => 'Actions',
        'doc' => 'Document', 'video' => 'Video Stream', 'link' => 'Web Link', 'no_uploads' => 'No uploads found matching configuration parameters.',
        'err_req' => 'Class/Subject selection and Chapter Name are required.', 'err_size' => 'Error: Document size must be less than 5 MB.', 'err_format' => 'Invalid format. Only MP4, WebM, and OGG allowed.', 'err_empty' => 'Provide at least one learning resource context link or Attachment file.'
    ],
    'hi' => [
        'welcome' => 'स्वागत है', 'logout' => 'लॉगआउट', 'admin_panel' => 'एडमिन पैनल', 'teacher_portal' => 'शिक्षक पोर्टल',
        'overview' => 'अवलोकन', 'm_students' => 'छात्र प्रबंधन', 'm_teachers' => 'शिक्षक प्रबंधन', 'm_classes' => 'कक्षा प्रबंधन', 'm_upload' => 'सामग्री अपलोड करें', 'm_reports' => 'रिपोर्ट देखें',
        'm_t_classes' => 'मेरी कक्षाएं और छात्र', 'm_t_upload' => 'क्लास सामग्री अपलोड', 'm_t_reports' => 'लर्निंग रिपोर्ट',
        'pub_title' => 'सामग्री प्रकाशक', 'pub_desc' => 'वैध पाठ्यक्रम ट्रैक से जुड़े वीडियो और संदर्भ दस्तावेज़ प्रकाशित करें',
        'edit_m' => 'अपलोड की गई सामग्री संशोधित करें', 'add_m' => 'नई अध्ययन सामग्री प्रकाशित करें',
        'lbl_track' => 'लक्षित कक्षा और विषय आवंटन ट्रैक *', 'choose_track' => '-- आवंटन ट्रैक चुनें --',
        'lbl_chapter' => 'अध्याय / विषय का नाम *', 'lbl_pdf' => 'नोट्स फ़ाइल पेलोड (अधिकतम 5MB)', 
        'lbl_video' => 'लोकल वीडियो फ़ाइल पेलोड (अधिकतम 30 मिनट / MP4)', 'lbl_drive' => 'साझा ड्राइव एसेट / क्लाउड बाहरी वीडियो लिंक URL',
        'btn_commit' => 'सामग्री विवरण अपडेट करें', 'btn_publish' => 'सामग्री प्रकाशित करें', 'btn_cancel' => 'रद्द करें',
        'library_title' => 'मेरी अपलोड की गई सामग्री लाइब्रेरी', 'th_class' => 'कक्षा और विषय', 'th_topic' => 'अध्याय का शीर्षक संदर्भ', 'th_Attachment' => 'संलग्न दस्तावेज़/वीडियो', 'th_actions' => 'कार्रवाई',
        'doc' => 'दस्तावेज़ नोट्स', 'video' => 'वीडियो क्लास', 'link' => 'वेब लिंक/ड्राइव', 'no_uploads' => 'कॉन्फ़िगरेशन मापदंडों से मेल खाने वाले कोई अपलोड नहीं मिले।',
        'err_req' => 'कक्षा/विषय चयन और अध्याय का नाम आवश्यक हैं।', 'err_size' => 'त्रुटि: दस्तावेज़ का आकार 5 MB से कम होना चाहिए।', 'err_format' => 'अमान्य प्रारूप। केवल MP4, WebM और OGG की अनुमति है।', 'err_empty' => 'कम से कम एक शिक्षण संसाधन लिंक या पेलोड फ़ाइल प्रदान करें।'
    ]
];
$t = $translations[$lang];

// ----- DYNAMICALLY FETCH MAPPED CLASS TRACKS FROM ALLOCATIONS -----
$mapped_tracks = [];
if ($current_role === 'admin') {
    $track_res = $conn->query("SELECT DISTINCT class_name, subject_name FROM class_subjects ORDER BY class_name ASC, subject_name ASC");
} else {
    $track_stmt = $conn->prepare("SELECT DISTINCT class_name, subject_name FROM class_subjects WHERE teacher_id = ? ORDER BY class_name ASC, subject_name ASC");
    $track_stmt->bind_param("i", $teacher_id);
    $track_stmt->execute();
    $track_res = $track_stmt->get_result();
}

while ($row = $track_res->fetch_assoc()) {
    $mapped_tracks[] = $row;
}

// ----- HANDLE ADD / UPDATE LESSON MATERIAL -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id       = $_POST['edit_id'] ?? null;
    $track_index   = $_POST['track_selection'] ?? ''; 
    $chapter_name  = trim($_POST['chapter_name']);
    $video_url     = trim($_POST['video_url']);
    
    if (empty($track_index) || empty($chapter_name)) {
        $error = $t['err_req'];
    } else {
        list($class_name, $subject_name) = explode('|', $track_index);
        
        $file_path       = $_POST['existing_file'] ?? '';
        $video_file_path = $_POST['existing_video_file'] ?? '';
        
        if (!is_dir('uploads/lessons')) {
            mkdir('uploads/lessons', 0777, true);
        }

        // 1. Document Upload Engine
        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['lesson_file']['size'] > 5242880) {
                $error = $t['err_size'];
                goto skip;
            }
            $ext = strtolower(pathinfo($_FILES['lesson_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg'])) {
                $filename = 'doc_' . time() . '_' . rand(100,999) . '.' . $ext;
                $target = 'uploads/lessons/' . $filename;
                if (move_uploaded_file($_FILES['lesson_file']['tmp_name'], $target)) {
                    $file_path = $target;
                }
            }
        }

        // 2. Local Video Engine
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                $filename = 'vid_' . time() . '_' . rand(100,999) . '.' . $ext;
                $target = 'uploads/lessons/' . $filename;
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $target)) {
                    $video_file_path = $target;
                }
            } else {
                $error = $t['err_format'];
                goto skip;
            }
        }

        if (empty($file_path) && empty($video_file_path) && empty($video_url)) {
            $error = $t['err_empty'];
            goto skip;
        }

        if ($edit_id) {
            $sql = "UPDATE lessons SET class_name=?, subject_name=?, chapter_name=?, file_path=?, video_file_path=?, video_url=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $class_name, $subject_name, $chapter_name, $file_path, $video_file_path, $video_url, $edit_id);
            if ($stmt->execute()) {
                $success = ($lang === 'hi') ? "अध्ययन सामग्री सफलतापूर्वक अपडेट की गई।" : "Study content updated completely!";
                header("Refresh: 1; url=upload_lesson.php");
            }
        } else {
            $sql = "INSERT INTO lessons (class_name, subject_name, chapter_name, file_path, video_file_path, video_url) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $class_name, $subject_name, $chapter_name, $file_path, $video_file_path, $video_url);
            if ($stmt->execute()) {
                $success = ($lang === 'hi') ? "सामग्री सफलतापूर्वक प्रकाशित की गई!" : "Study material published successfully!";
            }
        }
    }
    skip: ;
}

// ----- HANDLE DELETE -----
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $find_sql = "SELECT file_path, video_file_path FROM lessons WHERE id = ?";
    $f_stmt = $conn->prepare($find_sql);
    $f_stmt->bind_param("i", $delete_id);
    $f_stmt->execute();
    $lesson = $f_stmt->get_result()->fetch_assoc();

    if ($lesson) {
        $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            if (!empty($lesson['file_path']) && file_exists($lesson['file_path'])) unlink($lesson['file_path']);
            if (!empty($lesson['video_file_path']) && file_exists($lesson['video_file_path'])) unlink($lesson['video_file_path']);
            $success = ($lang === 'hi') ? "सामग्री सफलतापूर्वक हटा दी गई।" : "Material removed successfully.";
        }
    }
}

// ----- EDIT INITIALIZATION -----
$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// ----- GET RECENTLY UPLOADED REVIEWS -----
if ($current_role === 'admin') {
    $query = "SELECT * FROM lessons ORDER BY created_at DESC";
} else {
    $query = "SELECT l.* FROM lessons l 
              JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_name = cs.subject_name 
              WHERE cs.teacher_id = " . intval($teacher_id) . " ORDER BY l.created_at DESC";
}
$lessons = [];
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lessons[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['m_upload']; ?> - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
        .form-row > div { flex: 1; min-width: 240px; }
        .form-row label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .form-row input, .form-row select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; background: white; }
        .dash-table .action-icons a { margin: 0 6px; font-size: 16px; text-decoration: none; }
        .material-link { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 13px; text-decoration: none; font-weight: bold; margin-bottom: 4px; }
        .file-link { background: #dbeafe; color: #1e3a8a; }
        .video-link { background: #fef9c3; color: #713f12; }
        .url-link { background: #fee2e2; color: #991b1b; }
        .resource-card { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 15px; }
    </style>
</head>
<body>

<!-- TOP HEADER -->
<div class="dash-header">
    <div class="header-title">
        <button class="menu-toggle-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <i class="fa-solid <?php echo $current_role === 'admin' ? 'fa-school' : 'fa-chalkboard-user'; ?>"></i>
        <span><?php echo $current_role === 'admin' ? $t['admin_panel'] : $t['teacher_portal']; ?></span>
    </div>
    <div class="header-right">
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> <?php echo $t['logout']; ?></a>
        <form method="GET">
            <?php if(isset($_GET['edit_id'])): ?><input type="hidden" name="edit_id" value="<?php echo intval($_GET['edit_id']); ?>"><?php endif; ?>
            <select name="lang" onchange="this.form.submit()" class="lang-select">
                <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?>>English</option>
                <option value="hi" <?php echo $lang === 'hi' ? 'selected' : ''; ?>>हिंदी (Hindi)</option>
            </select>
        </form>
    </div>
</div>

<!-- OVERLAY FOR MOBILE SIDEBAR -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebarMenu">
    <?php if ($current_role === 'admin'): ?>
        <a href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i> <?php echo $t['overview']; ?></a>
        <a href="manage_students.php"><i class="fa-solid fa-user-graduate"></i> <?php echo $t['m_students']; ?></a>
        <a href="manage_teachers.php"><i class="fa-solid fa-chalkboard-user"></i> <?php echo $t['m_teachers']; ?></a>
        <a href="manage_classes.php"><i class="fa-solid fa-door-open"></i> <?php echo $t['m_classes']; ?></a>
        <a href="upload_lesson.php" class="active"><i class="fa-solid fa-upload"></i> <?php echo $t['m_upload']; ?></a>
        <a href="view_reports.php"><i class="fa-solid fa-file-lines"></i> <?php echo $t['m_reports']; ?></a>
    <?php else: ?>
        <a href="teacher_dashboard.php"><i class="fa-solid fa-chalkboard-user"></i> <?php echo $t['m_t_classes']; ?></a>
        <a href="upload_lesson.php" class="active"><i class="fa-solid fa-upload"></i> <?php echo $t['m_t_upload']; ?></a>
        <a href="view_reports.php"><i class="fa-solid fa-file-lines"></i> <?php echo $t['m_t_reports']; ?></a>
    <?php endif; ?>
</aside>

<!-- MAIN PANEL -->
<div class="main-panel">
    <div class="page-title">
        <h1><?php echo $t['pub_title']; ?></h1>
        <p><?php echo $t['pub_desc']; ?></p>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #fee; color: #b91c1c; padding: 15px; margin-bottom: 20px; border-radius: 5px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 15px; margin-bottom: 20px; border-radius: 5px; line-height: 1.6;"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div style="background: white; padding: 25px; margin-bottom: 30px; border-radius: 8px; border: 1px solid var(--border-color);">
        <h3 style="margin-top: 0; color: var(--primary-color);">
            <i class="fa-solid <?php echo $editData ? 'fa-pen-to-square' : 'fa-cloud-arrow-up'; ?>"></i> 
            <?php echo $editData ? $t['edit_m'] : $t['add_m']; ?>
        </h3>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" value="<?php echo $editData['id'] ?? ''; ?>">
            <input type="hidden" name="existing_file" value="<?php echo $editData['file_path'] ?? ''; ?>">
            <input type="hidden" name="existing_video_file" value="<?php echo $editData['video_file_path'] ?? ''; ?>">

            <div class="form-row">
                <div>
                    <label><?php echo $t['lbl_track']; ?></label>
                    <select name="track_selection" required>
                        <option value=""><?php echo $t['choose_track']; ?></option>
                        <?php foreach ($mapped_tracks as $track): ?>
                            <?php 
                                $val = $track['class_name'] . '|' . $track['subject_name'];
                                $sel = ($editData && $editData['class_name'] == $track['class_name'] && $editData['subject_name'] == $track['subject_name']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $val; ?>" <?php echo $sel; ?>>Class <?php echo $track['class_name'] . ' - ' . $track['subject_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?php echo $t['lbl_chapter']; ?></label>
                    <input type="text" name="chapter_name" value="<?php echo $editData['chapter_name'] ?? ''; ?>" required placeholder="e.g., Chapter 3: Triangles">
                </div>
            </div>

            <div class="resource-card">
                <label><i class="fa-solid fa-file-pdf" style="color: #2563eb;"></i> <?php echo $t['lbl_pdf']; ?></label>
                <input type="file" name="lesson_file" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg">
            </div>

            <div class="resource-card">
                <label><i class="fa-solid fa-file-video" style="color: #ca8a04;"></i> <?php echo $t['lbl_video']; ?></label>
                <input type="file" name="video_file" accept="video/mp4,video/webm">
            </div>

            <div class="resource-card">
                <label><i class="fa-brands fa-google-drive" style="color: #dc2626;"></i> <?php echo $t['lbl_drive']; ?></label>
                <input type="url" name="video_url" value="<?php echo $editData['video_url'] ?? ''; ?>" placeholder="Paste links here...">
            </div>

            <button type="submit" class="btn-dark" style="border: none; cursor: pointer;">
                <i class="fa-solid <?php echo $editData ? 'fa-save' : 'fa-upload'; ?>"></i> 
                <?php echo $editData ? $t['btn_commit'] : $t['btn_publish']; ?>
            </button>
            <?php if ($editData): ?>
                <a href="upload_lesson.php" class="btn-dark" style="background: #6b7280; text-decoration:none; display:inline-block;"><?php echo $t['btn_cancel']; ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LIBRARY -->
    <div style="background: white; padding: 25px; border-radius: 8px; border: 1px solid var(--border-color);">
        <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--primary-color);"><i class="fa-solid fa-folder"></i> <?php echo $t['library_title']; ?></h3>
        <?php if (count($lessons) > 0): ?>
            <table class="dash-table">
                <thead>
                    <tr>
                        <th><?php echo $t['th_class']; ?></th>
                        <th><?php echo $t['th_topic']; ?></th>
                        <th><?php echo $t['th_Attachment']; ?></th>
                        <th><?php echo $t['th_actions']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $les): ?>
                    <tr>
                        <td><strong>Class <?php echo htmlspecialchars($les['class_name']); ?></strong><br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($les['subject_name']); ?></small></td>
                        <td><?php echo htmlspecialchars($les['chapter_name']); ?></td>
                        <td>
                            <?php if(!empty($les['file_path'])): ?><a href="<?php echo $les['file_path']; ?>" target="_blank" class="material-link file-link"><i class="fa-solid fa-file-pdf"></i> <?php echo $t['doc']; ?></a><br><?php endif; ?>
                            <?php if(!empty($les['video_file_path'])): ?><a href="<?php echo $les['video_file_path']; ?>" target="_blank" class="material-link video-link"><i class="fa-solid fa-video"></i> <?php echo $t['video']; ?></a><br><?php endif; ?>
                            <?php if(!empty($les['video_url'])): ?><a href="<?php echo htmlspecialchars($les['video_url']); ?>" target="_blank" class="material-link url-link"><i class="fa-solid fa-link"></i> <?php echo $t['link']; ?></a><?php endif; ?>
                        </td>
                        <td class="action-icons">
                            <a href="upload_lesson.php?edit_id=<?php echo $les['id']; ?>" class="edit" title="Edit details"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="upload_lesson.php?delete_id=<?php echo $les['id']; ?>" onclick="return confirm('Remove material?');" class="delete" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: var(--text-muted);"><?php echo $t['no_uploads']; ?></p>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebarMenu');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    function closeSidebar() {
        document.getElementById('sidebarMenu').classList.remove('show');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }
</script>

</body>
</html>