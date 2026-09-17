<?php
// Turn on error reporting to help with debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';

// ----- इनलाइन भाषा अनुवाद ऐरे -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$translations = [
    'en' => [
        'welcome' => 'Welcome', 'logout' => 'Logout', 'admin_panel' => 'Admin Panel', 'manage_desc' => "Manage your school's digital learning platform",
        'overview' => 'Overview', 'm_students' => 'Manage Students', 'm_teachers' => 'Manage Teachers', 'm_classes' => 'Manage Classes', 'm_upload' => 'Upload Materials', 'm_reports' => 'View Reports',
        'edit_stu' => 'Edit Student Details', 'add_stu' => 'Admit New Student', 'fullname' => 'Full Name *', 'dob' => 'Date of Birth *', 'photo' => 'Passport Photo (Max 100 KB)', 'photo_hint' => 'Current photo exists. Upload new to replace.',
        'father' => "Father's Name *", 'mother' => "Mother's Name *", 'contact' => 'Contact Number *', 'gender' => 'Gender *', 'class' => 'Class *', 'address' => 'Residential Address *', 'select' => 'Select', 'male' => 'Male', 'female' => 'Female',
        'btn_update' => 'Update Student', 'btn_admit' => 'Admit Student', 'btn_cancel' => 'Cancel', 'directory' => 'Student Directory', 'filter_lbl' => 'Filter by Class:', 'all_classes' => 'All Classes', 'btn_filter' => 'Apply', 'btn_clear' => 'Clear',
        'th_roll' => 'Roll No.', 'th_name' => 'Full Name', 'th_class' => 'Class', 'th_father' => "Father's Name", 'th_date' => 'Admission Date', 'th_actions' => 'Actions', 'no_records' => 'No students found matching this criteria.',
        'err_required' => 'All fields except photo (if editing) are required.', 'err_size' => 'Error: Passport photo must be less than 100 KB.', 'err_photo_req' => 'Error: Passport photo is required for new admissions.'
    ],
    'hi' => [
        'welcome' => 'स्वागत है', 'logout' => 'लॉगआउट', 'admin_panel' => 'एडमिन पैनल', 'manage_desc' => 'अपने स्कूल के डिजिटल लर्निंग प्लेटफॉर्म का प्रबंधन करें',
        'overview' => 'अवलोकन', 'm_students' => 'छात्र प्रबंधन', 'm_teachers' => 'शिक्षक प्रबंधन', 'm_classes' => 'कक्षा प्रबंधन', 'm_upload' => 'सामग्री अपलोड करें', 'm_reports' => 'रिपोर्ट देखें',
        'edit_stu' => 'छात्र विवरण संपादित करें', 'add_stu' => 'नया छात्र प्रवेश', 'fullname' => 'पूरा नाम *', 'dob' => 'जन्म तिथि *', 'photo' => 'पासपोर्ट फोटो (अधिकतम 100 KB)', 'photo_hint' => 'वर्तमान फोटो मौजूद है। बदलने के लिए नई अपलोड करें।',
        'father' => 'पिता का नाम *', 'mother' => 'माता का नाम *', 'contact' => 'संपर्क नंबर *', 'gender' => 'लिंग *', 'class' => 'कक्षा *', 'address' => 'आवासीय पता *', 'select' => 'चुनें', 'male' => 'पुरुष', 'female' => 'महिला',
        'btn_update' => 'छात्र अपडेट करें', 'btn_admit' => 'छात्र प्रवेश करें', 'btn_cancel' => 'रद्द करें', 'directory' => 'छात्र निर्देशिका', 'filter_lbl' => 'कक्षा अनुसार फ़िल्टर:', 'all_classes' => 'सभी कक्षाएं', 'btn_filter' => 'लागू करें', 'btn_clear' => 'साफ करें',
        'th_roll' => 'रोल नंबर', 'th_name' => 'पूरा नाम', 'th_class' => 'कक्षा', 'th_father' => 'पिता का नाम', 'th_date' => 'प्रवेश तिथि', 'th_actions' => 'कार्रवाई', 'no_records' => 'इस मानदंड से मेल खाने वाले कोई छात्र नहीं मिले।',
        'err_required' => 'फोटो (यदि संपादन कर रहे हों) को छोड़कर सभी फ़ील्ड आवश्यक हैं।', 'err_size' => 'त्रुटि: पासपोर्ट फोटो 100 KB से कम होनी चाहिए।', 'err_photo_req' => 'त्रुटि: नए प्रवेश के लिए पासपोर्ट फोटो आवश्यक है।'
    ]
];
$t = $translations[$lang];

// ----- HANDLE ADD / UPDATE STUDENT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id = $_POST['edit_id'] ?? null;
    $fullname = trim($_POST['fullname']);
    $dob      = trim($_POST['dob']);
    $father   = trim($_POST['father_name']);
    $mother   = trim($_POST['mother_name']);
    $gender   = trim($_POST['gender']);
    $class    = trim($_POST['class']);
    $address  = trim($_POST['address']);
    $contact  = trim($_POST['contact_number']);

    if (empty($fullname) || empty($dob) || empty($father) || empty($mother) || empty($gender) || empty($class) || empty($address) || empty($contact)) {
        $error = $t['err_required'];
    } else {
        $photo_path = $_POST['existing_photo'] ?? '';
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['photo']['size'] > 102400) {
                $error = $t['err_size'];
                goto skip;
            }
            if (!is_dir('uploads/students')) {
                mkdir('uploads/students', 0777, true);
            }
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = 'stu_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $target = 'uploads/students/' . $filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                $photo_path = $target;
            }
        }

        if ($edit_id) {
            $sql = "UPDATE users SET full_name=?, dob=?, father_name=?, mother_name=?, gender=?, class=?, address=?, contact_number=?, photo_path=? WHERE id=? AND role='student'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssi", $fullname, $dob, $father, $mother, $gender, $class, $address, $contact, $photo_path, $edit_id);            
            if ($stmt->execute()) {
                $success = ($lang === 'hi') ? "छात्र प्रोफ़ाइल सफलतापूर्वक अपडेट की गई।" : "Student profile updated successfully.";
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            if (empty($photo_path)) {
                $error = $t['err_photo_req'];
                goto skip;
            }

            $prefix = "MC" . $class;
            $roll_query = "SELECT username FROM users WHERE role='student' AND username LIKE ? ORDER BY username DESC LIMIT 1";
            $roll_stmt = $conn->prepare($roll_query);
            $like_prefix = $prefix . "%";
            $roll_stmt->bind_param("s", $like_prefix);
            $roll_stmt->execute();
            $roll_res = $roll_stmt->get_result();

            if ($roll_res->num_rows > 0) {
                $last_user = $roll_res->fetch_assoc();
                $last_roll = $last_user['username'];
                $last_sequence = (int) substr($last_roll, strlen($prefix));
                $next_sequence = $last_sequence + 1;
            } else {
                $next_sequence = 1;
            }
            
            $generated_username = $prefix . str_pad($next_sequence, 3, "0", STR_PAD_LEFT);
            $dob_obj = new DateTime($dob);
            $raw_password = $dob_obj->format('dmY');
            $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (username, password, role, full_name, dob, father_name, mother_name, gender, class, address, contact_number, photo_path) 
                    VALUES (?, ?, 'student', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssss", $generated_username, $hashed_password, $fullname, $dob, $father, $mother, $gender, $class, $address, $contact, $photo_path);
            
            if ($stmt->execute()) {
                $success = ($lang === 'hi') ? 
                    "छात्र का प्रवेश सफलतापूर्वक हो गया! <br><strong>रोल नंबर (यूज़रनेम):</strong> {$generated_username} <br><strong>पासवर्ड:</strong> {$raw_password}" : 
                    "Student admitted successfully! <br><strong>Roll No (Username):</strong> {$generated_username} <br><strong>Password:</strong> {$raw_password}";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
    skip: ;
}

// ----- HANDLE DELETE -----
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = ($lang === 'hi') ? "छात्र को सफलतापूर्वक हटा दिया गया।" : "Student deleted successfully.";
    } else {
        $error = "Deletion failed.";
    }
}

// ----- LOAD STUDENT DATA FOR EDIT -----
$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// ----- FILTER & FETCH ALL STUDENTS -----
$filter_class = $_GET['filter_class'] ?? '';
$query = "SELECT id, username, full_name, class, father_name, created_at, photo_path FROM users WHERE role='student'";
if ($filter_class !== '') {
    $query .= " AND class = '" . $conn->real_escape_string($filter_class) . "'";
}
$query .= " ORDER BY username ASC";

$students = [];
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['m_students']; ?> - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
        .form-row > div { flex: 1; min-width: 180px; }
        .form-row label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; }
        .dash-table .action-icons a { margin: 0 8px; font-size: 16px; }
        .filter-form { display: flex; align-items: center; gap: 10px; }
        .filter-form select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; }
        .filter-form button { padding: 8px 15px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

<!-- TOP HEADER -->
<div class="dash-header">
    <div class="header-title">
        <button class="menu-toggle-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <i class="fa-solid fa-school"></i>
        <span><?php echo $t['admin_panel']; ?></span>
    </div>
    <div class="header-right">
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> <?php echo $t['logout']; ?></a>
        <form method="GET">
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
    <a href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i> <?php echo $t['overview']; ?></a>
    <a href="manage_students.php" class="active"><i class="fa-solid fa-user-graduate"></i> <?php echo $t['m_students']; ?></a>
    <a href="manage_teachers.php"><i class="fa-solid fa-chalkboard-user"></i> <?php echo $t['m_teachers']; ?></a>
    <a href="manage_classes.php"><i class="fa-solid fa-door-open"></i> <?php echo $t['m_classes']; ?></a>
    <a href="upload_lesson.php"><i class="fa-solid fa-upload"></i> <?php echo $t['m_upload']; ?></a>
    <a href="view_reports.php"><i class="fa-solid fa-file-lines"></i> <?php echo $t['m_reports']; ?></a>
</aside>

<!-- MAIN PANEL -->
<div class="main-panel">
    <div class="page-title">
        <h1><?php echo $t['m_students']; ?></h1>
        <p><?php echo $t['manage_desc']; ?></p>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #fee; color: #b91c1c; padding: 15px; margin-bottom: 20px; border-radius: 5px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 15px; margin-bottom: 20px; border-radius: 5px; line-height: 1.5;"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div style="background: white; padding: 25px; margin-bottom: 30px; border-radius: 8px; border: 1px solid var(--border-color);">
        <h3 style="margin-top: 0; color: var(--primary-color);">
            <i class="fa-solid fa-user-plus"></i> <?php echo $editData ? $t['edit_stu'] : $t['add_stu']; ?>
        </h3>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" value="<?php echo $editData['id'] ?? ''; ?>">
            <input type="hidden" name="existing_photo" value="<?php echo $editData['photo_path'] ?? ''; ?>">

            <div class="form-row">
                <div>
                    <label><?php echo $t['fullname']; ?></label>
                    <input type="text" name="fullname" value="<?php echo $editData['full_name'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['dob']; ?></label>
                    <input type="date" name="dob" value="<?php echo $editData['dob'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['photo']; ?></label>
                    <input type="file" name="photo" accept="image/jpeg, image/png" <?php echo $editData ? '' : 'required'; ?>>
                    <?php if ($editData && !empty($editData['photo_path'])): ?>
                        <small style="color: green;"><?php echo $t['photo_hint']; ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label><?php echo $t['father']; ?></label>
                    <input type="text" name="father_name" value="<?php echo $editData['father_name'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['mother']; ?></label>
                    <input type="text" name="mother_name" value="<?php echo $editData['mother_name'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['contact']; ?></label>
                    <input type="text" name="contact_number" value="<?php echo $editData['contact_number'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label><?php echo $t['gender']; ?></label>
                    <select name="gender" required>
                        <option value=""><?php echo $t['select']; ?></option>
                        <option value="Male" <?php echo (isset($editData['gender']) && $editData['gender'] == 'Male') ? 'selected' : ''; ?>><?php echo $t['male']; ?></option>
                        <option value="Female" <?php echo (isset($editData['gender']) && $editData['gender'] == 'Female') ? 'selected' : ''; ?>><?php echo $t['female']; ?></option>
                    </select>
                </div>
                <div>
                    <label><?php echo $t['class']; ?></label>
                    <select name="class" required>
                        <option value=""><?php echo $t['select']; ?></option>
                        <?php
                        $classes = ['6', '7', '8', '9', '11'];
                        foreach ($classes as $cl) {
                            $sel = (isset($editData['class']) && $editData['class'] == $cl) ? 'selected' : '';
                            echo "<option value='$cl' $sel>Class $cl</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="flex: 2;">
                    <label><?php echo $t['address']; ?></label>
                    <input type="text" name="address" value="<?php echo $editData['address'] ?? ''; ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-dark" style="border: none; cursor: pointer; margin-top: 10px;">
                <i class="fa-solid <?php echo $editData ? 'fa-save' : 'fa-plus'; ?>"></i>
                <?php echo $editData ? $t['btn_update'] : $t['btn_admit']; ?>
            </button>
            <?php if ($editData): ?>
                <a href="manage_students.php" class="btn-dark" style="background: #6b7280; text-decoration:none; display:inline-block; margin-top: 10px;"><?php echo $t['btn_cancel']; ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- STUDENT DIRECTORY -->
    <div style="background: white; padding: 25px; border-radius: 8px; border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <h3 style="margin: 0; color: var(--primary-color);">
                <i class="fa-solid fa-users"></i> <?php echo $t['directory']; ?> (<?php echo count($students); ?>)
            </h3>
            
            <form method="GET" class="filter-form">
                <label style="font-weight: bold;"><?php echo $t['filter_lbl']; ?></label>
                <select name="filter_class">
                    <option value=""><?php echo $t['all_classes']; ?></option>
                    <option value="6" <?php if($filter_class == '6') echo 'selected'; ?>>Class 6</option>
                    <option value="7" <?php if($filter_class == '7') echo 'selected'; ?>>Class 7</option>
                    <option value="8" <?php if($filter_class == '8') echo 'selected'; ?>>Class 8</option>
                    <option value="9" <?php if($filter_class == '9') echo 'selected'; ?>>Class 9</option>
                    <option value="11" <?php if($filter_class == '11') echo 'selected'; ?>>Class 11</option>
                </select>
                <button type="submit"><i class="fa-solid fa-filter"></i> <?php echo $t['btn_filter']; ?></button>
                <?php if($filter_class !== ''): ?>
                    <a href="manage_students.php" style="margin-left: 5px; color: #ef4444; text-decoration: none;"><?php echo $t['btn_clear']; ?></a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($students) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th><?php echo $t['th_roll']; ?></th>
                            <th><?php echo $t['th_name']; ?></th>
                            <th><?php echo $t['th_class']; ?></th>
                            <th><?php echo $t['th_father']; ?></th>
                            <th><?php echo $t['th_date']; ?></th>
                            <th><?php echo $t['th_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><strong class="student-name"><?php echo htmlspecialchars($s['username']); ?></strong></td>
                            <td>
                                <?php if(!empty($s['photo_path'])): ?>
                                    <img src="<?php echo $s['photo_path']; ?>" class="student-photo" alt="Photo">
                                <?php endif; ?>
                                <strong class="student-name"><?php echo htmlspecialchars($s['full_name']); ?></strong>
                            </td>
                            <td>Class <?php echo htmlspecialchars($s['class']); ?></td>
                            <td><?php echo htmlspecialchars($s['father_name']); ?></td>
                            <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                            <td class="action-icons">
                                <a href="manage_students.php?edit_id=<?php echo $s['id']; ?>" class="edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="manage_students.php?delete_id=<?php echo $s['id']; ?>" onclick="return confirm('Delete this student permanently?');" class="delete" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
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