<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';

// ----- DYNAMIC LANGUAGE TRANSLATION DICTIONARY -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$translations = [
    'en' => [
        'welcome' => 'Welcome', 'logout' => 'Logout', 'admin_panel' => 'Admin Panel', 'manage_desc' => "Establish school syllabus tracks and staff allocations",
        'overview' => 'Overview', 'm_students' => 'Manage Students', 'm_teachers' => 'Manage Teachers', 'm_classes' => 'Manage Classes', 'm_upload' => 'Upload Materials', 'm_reports' => 'View Reports',
        'edit_track' => 'Edit Subject Assignment', 'add_track' => 'Allocate Subject Track & Teacher', 'lbl_class' => 'Select Class *', 'lbl_subject' => 'Select Subject Track *', 'lbl_teacher' => 'Assign Accountable Teacher',
        'opt_choose_cl' => '-- Choose Class --', 'opt_choose_sub' => '-- Choose Predefined Subject --', 'opt_unassigned' => '-- Leave Unassigned --',
        'btn_update' => 'Update Track Details', 'btn_create' => 'Create Track Mapping', 'btn_cancel' => 'Cancel', 'directory' => 'Class & Subject Overview Directory', 'filter_lbl' => 'Filter by Class:', 'all_classes' => 'All Classes', 'btn_filter' => 'Apply',
        'th_sno' => 'S.No', 'th_class' => 'Class Target', 'th_sub' => 'Syllabus Course / Track', 'th_teacher' => 'Assigned Educator Profile', 'th_actions' => 'Actions', 'unassigned' => 'Unassigned', 'no_records' => 'No course tracks active yet.',
        'err_req' => 'Class and Subject selections are required.'
    ],
    'hi' => [
        'welcome' => 'स्वागत है', 'logout' => 'लॉगआउट', 'admin_panel' => 'एडमिन पैनल', 'manage_desc' => 'स्कूल पाठ्यक्रम ट्रैक और स्टाफ आवंटन स्थापित करें',
        'overview' => 'अवलोकन', 'm_students' => 'छात्र प्रबंधन', 'm_teachers' => 'शिक्षक प्रबंधन', 'm_classes' => 'कक्षा प्रबंधन', 'm_upload' => 'सामग्री अपलोड करें', 'm_reports' => 'रिपोर्ट देखें',
        'edit_track' => 'विषय आवंटन संपादित करें', 'add_track' => 'विषय ट्रैक और शिक्षक आवंटित करें', 'lbl_class' => 'कक्षा चुनें *', 'lbl_subject' => 'विषय ट्रैक चुनें *', 'lbl_teacher' => 'जिम्मेदार शिक्षक नियुक्त करें',
        'opt_choose_cl' => '-- कक्षा चुनें --', 'opt_choose_sub' => '-- पूर्व निर्धारित विषय चुनें --', 'opt_unassigned' => '-- अनावंटित छोड़ें --',
        'btn_update' => 'ट्रैक विवरण अपडेट करें', 'btn_create' => 'ट्रैक मैपिंग बनाएं', 'btn_cancel' => 'रद्द करें', 'directory' => 'कक्षा और विषय अवलोकन निर्देशिका', 'filter_lbl' => 'कक्षा अनुसार फ़िल्टर:', 'all_classes' => 'सभी कक्षाएं', 'btn_filter' => 'लागू करें',
        'th_sno' => 'क्र.सं.', 'th_class' => 'लक्षित कक्षा', 'th_sub' => 'पाठ्यक्रम / विषय ट्रैक', 'th_teacher' => 'आवंटित शिक्षक प्रोफ़ाइल', 'th_actions' => 'कार्रवाई', 'unassigned' => 'अनावंटित', 'no_records' => 'अभी तक कोई कोर्स ट्रैक सक्रिय नहीं है।',
        'err_req' => 'कक्षा और विषय का चयन आवश्यक है।'
    ]
];
$t = $translations[$lang];

// ----- FETCH ALL TEACHERS -----
$teachers = [];
$t_query = "SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name ASC";
$t_result = $conn->query($t_query);
if ($t_result) {
    while ($row = $t_result->fetch_assoc()) { $teachers[] = $row; }
}

$class_options = ['6', '7', '8', '9', '10', '11 Science', '11 Commerce', '11 Arts', '12 Science', '12 Commerce', '12 Arts'];

// ----- HANDLE ADD / UPDATE CLASS SUBJECT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id = $_POST['edit_id'] ?? null;
    $class_name = trim($_POST['class_name']);
    $subject_name = trim($_POST['subject_name']);
    $teacher_id = $_POST['teacher_id'];

    if (empty($class_name) || empty($subject_name)) {
        $error = $t['err_req'];
    } else {
        $t_id = !empty($teacher_id) ? intval($teacher_id) : null;

        if ($edit_id) {
            $sql = "UPDATE class_subjects SET class_name=?, subject_name=?, teacher_id=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $class_name, $subject_name, $t_id, $edit_id);
            if ($stmt->execute()) {
                $success = ($lang === 'hi') ? "विषय आवंटन सफलतापूर्वक अपडेट किया गया।" : "Subject allocation updated successfully.";
                header("Refresh: 1; url=manage_classes.php");
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            $check = $conn->prepare("SELECT id FROM class_subjects WHERE class_name=? AND subject_name=?");
            $check->bind_param("ss", $class_name, $subject_name);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = ($lang === 'hi') ? "यह विषय मैपिंग कक्षा " . $class_name . " के लिए पहले से मौजूद है।" : "This subject mapping already exists for Class " . $class_name;
            } else {
                $sql = "INSERT INTO class_subjects (class_name, subject_name, teacher_id) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $class_name, $subject_name, $t_id);
                if ($stmt->execute()) {
                    $success = ($lang === 'hi') ? "विषय जोड़ दिया गया और शिक्षक सफलतापूर्वक नियुक्त कर दिए गए!" : "Subject added and teacher assigned successfully!";
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }
}

// ----- HANDLE DELETE -----
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM class_subjects WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = ($lang === 'hi') ? "विषय ट्रैक सफलतापूर्वक हटा दिया गया।" : "Subject track removed successfully.";
    } else {
        $error = "Deletion failed.";
    }
}

// ----- LOAD DATA FOR EDIT -----
$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM class_subjects WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// ----- FETCH ALL SUBJECT MAPPINGS -----
$filter_class = $_GET['filter_class'] ?? '';
$query = "SELECT cs.id, cs.class_name, cs.subject_name, u.full_name AS teacher_name FROM class_subjects cs LEFT JOIN users u ON cs.teacher_id = u.id ";
if ($filter_class !== '') {
    $query .= " WHERE cs.class_name = '" . $conn->real_escape_string($filter_class) . "'";
}
$query .= " ORDER BY cs.class_name ASC, cs.subject_name ASC";

$class_mappings = [];
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) { $class_mappings[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['m_classes']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
        .form-row > div { flex: 1; min-width: 200px; }
        .form-row label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .form-row select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; background: white; }
        .dash-table .action-icons a { margin: 0 8px; font-size: 16px; }
        .filter-form { display: flex; align-items: center; gap: 10px; }
        .filter-form select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; }
        .filter-form button { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

<div class="dash-header" style="height: 75px; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
    <div class="header-title">
        <i class="fa-solid fa-school"></i>
        <span><?php echo $t['admin_panel']; ?> - <?php echo $t['welcome']; ?>, <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
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
        <h1><?php echo $t['admin_panel']; ?></h1>
        <p><?php echo $t['manage_desc']; ?></p>
    </div>

    <div class="text-menu-bar">
        <a href="admin_dashboard.php" class="text-menu-item"><h2><?php echo $t['overview']; ?></h2></a>
        <a href="manage_students.php" class="text-menu-item"><h2><?php echo $t['m_students']; ?></h2></a>
        <a href="manage_teachers.php" class="text-menu-item"><h2><?php echo $t['m_teachers']; ?></h2></a>
        <a href="manage_classes.php" class="text-menu-item active"><h2><?php echo $t['m_classes']; ?></h2></a>
        <a href="upload_lesson.php" class="text-menu-item"><h2><?php echo $t['m_upload']; ?></h2></a>
        <a href="view_reports.php" class="text-menu-item"><h2><?php echo $t['m_reports']; ?></h2></a>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #fee; color: #b91c1c; padding: 15px; margin-top: 20px; border-radius: 5px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 15px; margin-top: 20px; border-radius: 5px; line-height: 1.6;"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <div style="background: white; padding: 25px; margin-top: 30px; border-radius: 8px; border: 1px solid var(--border-color);">
        <h3 style="margin-top: 0; color: var(--primary-color);">
            <i class="fa-solid fa-book-open"></i> <?php echo $editData ? $t['edit_track'] : $t['add_track']; ?>
        </h3>
        
        <form method="POST">
            <input type="hidden" name="edit_id" value="<?php echo $editData['id'] ?? ''; ?>">

            <div class="form-row">
                <div>
                    <label><?php echo $t['lbl_class']; ?></label>
                    <select name="class_name" id="classSelect" onchange="syncDynamicSubjects()" required>
                        <option value=""><?php echo $t['opt_choose_cl']; ?></option>
                        <?php foreach ($class_options as $cl): ?>
                            <?php $sel = (isset($editData['class_name']) && $editData['class_name'] == $cl) ? 'selected' : ''; ?>
                            <option value="<?php echo $cl; ?>" <?php echo $sel; ?>>Class <?php echo $cl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?php echo $t['lbl_subject']; ?></label>
                    <select name="subject_name" id="subjectSelect" required>
                        <option value=""><?php echo $t['opt_choose_sub']; ?></option>
                        </select>
                </div>
                <div>
                    <label><?php echo $t['lbl_teacher']; ?></label>
                    <select name="teacher_id">
                        <option value=""><?php echo $t['opt_unassigned']; ?></option>
                        <?php foreach ($teachers as $t_row): ?>
                            <?php $sel = (isset($editData['teacher_id']) && $editData['teacher_id'] == $t_row['id']) ? 'selected' : ''; ?>
                            <option value="<?php echo $t_row['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($t_row['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="action-btn" style="background: var(--accent-color); border: none; cursor: pointer;">
                <i class="fa-solid <?php echo $editData ? 'fa-save' : 'fa-link'; ?>"></i>
                <?php echo $editData ? $t['btn_update'] : $t['btn_create']; ?>
            </button>
            <?php if ($editData): ?>
                <a href="manage_classes.php" class="action-btn" style="background: #6b7280; text-decoration: none; padding: 10px 20px; display: inline-block; border-radius: 6px;"><?php echo $t['btn_cancel']; ?></a>
            <?php endif; ?>
        </form>
    </div>

    <div style="background: white; padding: 25px; margin-top: 30px; border-radius: 8px; border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <h3 style="margin: 0; color: var(--primary-color);">
                <i class="fa-solid fa-layer-group"></i> <?php echo $t['directory']; ?>
            </h3>
            
            <form method="GET" class="filter-form">
                <label style="font-weight: bold;"><?php echo $t['filter_lbl']; ?></label>
                <select name="filter_class">
                    <option value=""><?php echo $t['all_classes']; ?></option>
                    <?php foreach ($class_options as $cl): ?>
                        <?php $sel = ($filter_class == $cl) ? 'selected' : ''; ?>
                        <option value="<?php echo $cl; ?>" <?php echo $sel; ?>>Class <?php echo $cl; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fa-solid fa-filter"></i> <?php echo $t['btn_filter']; ?></button>
            </form>
        </div>
        
        <?php if (count($class_mappings) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="dash-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th><?php echo $t['th_sno']; ?></th>
                            <th><?php echo $t['th_class']; ?></th>
                            <th><?php echo $t['th_sub']; ?></th>
                            <th><?php echo $t['th_teacher']; ?></th>
                            <th><?php echo $t['th_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; foreach ($class_mappings as $cm): ?>
                        <tr>
                            <td><?php echo $serial++; ?></td>
                            <td><strong>Class <?php echo htmlspecialchars($cm['class_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cm['subject_name']); ?></td>
                            <td>
                                <?php if($cm['teacher_name']): ?>
                                    <span style="color: #065f46; font-weight: bold;"><i class="fa-solid fa-user-check"></i> <?php echo htmlspecialchars($cm['teacher_name']); ?></span>
                                <?php else: ?>
                                    <span style="color: #ef4444;"><i class="fa-solid fa-user-xmark"></i> <?php echo $t['unassigned']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="action-icons">
                                <a href="manage_classes.php?edit_id=<?php echo $cm['id']; ?>" style="color: #2563eb;"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="manage_classes.php?delete_id=<?php echo $cm['id']; ?>" onclick="return confirm('Remove allocation?');" style="color: #ef4444;"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px;"><?php echo $t['no_records']; ?></p>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
    const subjectMap = {
        // Core Lower/Secondary School Allocations
        'default': ['Hindi', 'Urdu', 'Sanskrit', 'Science', 'Social Science', 'Maths', 'English'],
        
        // Higher Secondary Streams (Classes 11 & 12)
        'science': ['Physics', 'Chemistry', 'Biology', 'Mathematics', 'English', 'Hindi', 'Physical Education'],
        'commerce': ['Accountancy', 'Business Studies', 'Economics', 'Mathematics', 'Electives', 'English', 'Hindi'],
        'arts': ['Psychology', 'Sociology', 'Home Science', 'Physical Education', 'History', 'Political Science', 'Geography', 'English', 'Hindi']
    };

    function syncDynamicSubjects(selectedVal = '') {
        const classSelect = document.getElementById('classSelect');
        const subjectSelect = document.getElementById('subjectSelect');
        const selectedClass = classSelect.value;

        // Clear previous options except placeholder
        subjectSelect.innerHTML = `<option value="">${classSelect.options[0].text === '-- Choose Class --' ? '-- Choose Predefined Subject --' : '-- पूर्व निर्धारित विषय चुनें --'}</option>`;

        if (!selectedClass) return;

        let targetStream = 'default';
        if (selectedClass.includes('Science')) {
            targetStream = 'science';
        } else if (selectedClass.includes('Commerce')) {
            targetStream = 'commerce';
        } else if (selectedClass.includes('Arts')) {
            targetStream = 'arts';
        }

        // Generate options dynamically
        subjectMap[targetStream].forEach(subject => {
            const isSelected = (subject === selectedVal) ? 'selected' : '';
            const optionTag = `<option value="${subject}" ${isSelected}>${subject}</option>`;
            subjectSelect.insertAdjacentHTML('beforeend', optionTag);
        });
    }

    // Handles edit mode loading state parameters natively
    document.addEventListener("DOMContentLoaded", function() {
        <?php if($editData): ?>
            syncDynamicSubjects("<?php echo htmlspecialchars($editData['subject_name']); ?>");
        <?php else: ?>
            syncDynamicSubjects();
        <?php endif; ?>
    });
</script>

</body>
</html>