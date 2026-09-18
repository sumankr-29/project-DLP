<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';

// ----- INLINE BILINGUAL TRANSLATION DICTIONARY -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$translations = [
    'en' => [
        'welcome' => 'Welcome', 'logout' => 'Logout', 'admin_panel' => 'Admin Panel', 'manage_desc' => "Manage your school's digital learning platform",
        'overview' => 'Overview', 'm_students' => 'Manage Students', 'm_teachers' => 'Manage Teachers', 'm_classes' => 'Manage Classes', 'm_upload' => 'Upload Materials', 'm_reports' => 'View Reports',
        'edit_tch' => 'Edit Teacher Details', 'add_tch' => 'Register New Teacher', 'fullname' => 'Full Name *', 'father' => "Father's Name *", 'dob' => 'Date of Birth *',
        'email' => 'Email Address (For Login) *', 'phone' => 'Phone Number *', 'gender' => 'Gender *', 'address' => 'Residential Address *', 'photo' => 'Passport Photo (Max 100 KB)', 'select' => 'Select', 'male' => 'Male', 'female' => 'Female',
        'btn_update' => 'Update Teacher', 'btn_add' => 'Add Teacher', 'btn_cancel' => 'Cancel', 'directory' => 'Staff Directory', 's_no' => 'S.No', 'staff' => 'Staff Member', 'th_email' => 'Email (Username)', 'th_phone' => 'Phone', 'th_added' => 'Added On', 'th_actions' => 'Actions',
        'no_teachers' => 'No teachers registered yet.', 'photo_hint' => 'Current photo exists. Upload new to replace.',
        'err_req' => 'All fields except photo and password (if editing) are required.', 'err_dup' => 'This Email Address is already registered to another user.', 'err_size' => 'Error: Passport photo must be less than 100 KB.', 'err_photo_req' => 'Error: Passport photo is required for new teachers.',
        'lbl_password' => 'New Password (Leave blank to keep current)'
    ],
    'hi' => [
        'welcome' => 'स्वागत है', 'logout' => 'लॉगआउट', 'admin_panel' => 'एडमिन पैनल', 'manage_desc' => 'अपने स्कूल के डिजिटल लर्निंग प्लेटफॉर्म का प्रबंधन करें',
        'overview' => 'अवलोकन', 'm_students' => 'छात्र प्रबंधन', 'm_teachers' => 'शिक्षक प्रबंधन', 'm_classes' => 'कक्षा प्रबंधन', 'm_upload' => 'सामग्री अपलोड करें', 'm_reports' => 'रिपोर्ट देखें',
        'edit_tch' => 'शिक्षक विवरण संपादित करें', 'add_tch' => 'नया शिक्षक पंजीकरण', 'fullname' => 'पूरा नाम *', 'father' => 'पिता का नाम *', 'dob' => 'जन्म तिथि *',
        'email' => 'ईमेल पता (लॉगिन के लिए) *', 'phone' => 'फ़ोन नंबर *', 'gender' => 'लिंग *', 'address' => 'आवासीय पता *', 'photo' => 'पासपोर्ट फोटो (अधिकतम 100 KB)', 'select' => 'चुनें', 'male' => 'पुरुष', 'female' => 'महिला',
        'btn_update' => 'शिक्षक अपडेट करें', 'btn_add' => 'शिक्षक जोड़ें', 'btn_cancel' => 'रद्द करें', 'directory' => 'स्टाफ निर्देशिका', 's_no' => 'क्र.सं.', 'staff' => 'स्टाफ सदस्य', 'th_email' => 'ईमेल (यूज़रनेम)', 'th_phone' => 'फ़ोन', 'th_added' => 'जोड़ा गया तिथि', 'th_actions' => 'कार्रवाई',
        'no_teachers' => 'अभी तक कोई शिक्षक पंजीकृत नहीं है।', 'photo_hint' => 'वर्तमान फोटो मौजूद है। बदलने के लिए नई अपलोड करें।',
        'err_req' => 'फोटो और पासवर्ड (यदि संपादन कर रहे हों) को छोड़कर सभी फ़ील्ड आवश्यक हैं।', 'err_dup' => 'यह ईमेल पता पहले से ही किसी अन्य उपयोगकर्ता के लिए पंजीकृत है।', 'err_size' => 'त्रुटि: पासपोर्ट फोटो 100 KB से कम होनी चाहिए।', 'err_photo_req' => 'त्रुटि: नए शिक्षकों के लिए पासपोर्ट फोटो आवश्यक है।',
        'lbl_password' => 'नया पासवर्ड (वर्तमान रखने के लिए खाली छोड़ें)'
    ]
];
$t = $translations[$lang];

// ----- HANDLE ADD / UPDATE TEACHER -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id      = $_POST['edit_id'] ?? null;
    $fullname     = trim($_POST['fullname']);
    $father       = trim($_POST['father_name']);
    $dob          = trim($_POST['dob']);
    $gender       = trim($_POST['gender']);
    $email        = trim($_POST['email']);
    $phone        = trim($_POST['phone']);
    $address      = trim($_POST['address']);
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($fullname) || empty($father) || empty($dob) || empty($gender) || empty($email) || empty($phone) || empty($address)) {
        $error = $t['err_req'];
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $email, $edit_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = $t['err_dup'];
        } else {
            $photo_path = $_POST['existing_photo'] ?? '';
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['photo']['size'] > 102400) {
                    $error = $t['err_size'];
                    goto skip;
                }
                if (!is_dir('uploads/teachers')) {
                    mkdir('uploads/teachers', 0777, true);
                }
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'tch_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $target = 'uploads/teachers/' . $filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    $photo_path = $target;
                }
            }

            if ($edit_id) {
                // SCENARIO A: UPDATE EXISTING TEACHER PROFILE
                if (!empty($new_password)) {
                    $hashed_pass = password_hash($new_password, PASSWORD_BCRYPT);
                    $sql = "UPDATE users SET full_name=?, father_name=?, dob=?, gender=?, username=?, contact_number=?, address=?, photo_path=?, password=? WHERE id=? AND role='teacher'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssssssi", $fullname, $father, $dob, $gender, $email, $phone, $address, $photo_path, $hashed_pass, $edit_id);
                } else {
                    $sql = "UPDATE users SET full_name=?, father_name=?, dob=?, gender=?, username=?, contact_number=?, address=?, photo_path=? WHERE id=? AND role='teacher'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssssi", $fullname, $father, $dob, $gender, $email, $phone, $address, $photo_path, $edit_id);
                }
                
                if ($stmt->execute()) {
                    $success = ($lang === 'hi') ? "शिक्षक प्रोफ़ाइल सफलतापूर्वक अपडेट की गई।" : "Teacher profile updated successfully.";
                    header("Refresh: 1; url=manage_teachers.php");
                } else {
                    $error = "Database error: " . $conn->error;
                }
            } else {
                // SCENARIO B: REGISTER A BRAND NEW TEACHER
                if (empty($photo_path)) {
                    $error = $t['err_photo_req'];
                    goto skip;
                }

                $name_clean = str_replace(' ', '', $fullname);
                $pass_part1 = strtolower(substr($name_clean, 0, 2));
                $pass_part2 = date('Y', strtotime($dob));
                $pass_part3 = chr(rand(65, 90)) . chr(rand(65, 90));
                
                $raw_password = $pass_part1 . $pass_part2 . $pass_part3;
                $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

                $sql = "INSERT INTO users (username, password, role, full_name, father_name, dob, gender, contact_number, address, photo_path) 
                        VALUES (?, ?, 'teacher', ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssss", $email, $hashed_password, $fullname, $father, $dob, $gender, $phone, $address, $photo_path);
                
                if ($stmt->execute()) {
                    $success = ($lang === 'hi') ? 
                        "शिक्षक का पंजीकरण सफलतापूर्वक हो गया! <br><br><strong>लॉगिन ईमेल:</strong> {$email} <br><strong>पासवर्ड:</strong> {$raw_password}" : 
                        "Teacher registered successfully! <br><br><strong>Login Email:</strong> {$email} <br><strong>Generated Password:</strong> {$raw_password}";
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }
    skip: ;
}

// ----- HANDLE DELETE TEACHER -----
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = ($lang === 'hi') ? "शिक्षक को सफलतापूर्वक हटा दिया गया।" : "Teacher deleted successfully.";
    } else {
        $error = "Deletion failed.";
    }
}

// ----- LOAD DATA FOR EDIT -----
$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// ----- FETCH ALL TEACHERS -----
$teachers = [];
$result = $conn->query("SELECT id, username, full_name, contact_number, photo_path, created_at FROM users WHERE role='teacher' ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) { $teachers[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['m_teachers']; ?> - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
        .form-row > div { flex: 1; min-width: 180px; }
        .form-row label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; }
        .dash-table .action-icons a { margin: 0 8px; font-size: 16px; }
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
    <a href="manage_students.php"><i class="fa-solid fa-user-graduate"></i> <?php echo $t['m_students']; ?></a>
    <a href="manage_teachers.php" class="active"><i class="fa-solid fa-chalkboard-user"></i> <?php echo $t['m_teachers']; ?></a>
    <a href="manage_classes.php"><i class="fa-solid fa-door-open"></i> <?php echo $t['m_classes']; ?></a>
    <a href="upload_lesson.php"><i class="fa-solid fa-upload"></i> <?php echo $t['m_upload']; ?></a>
    <a href="view_reports.php"><i class="fa-solid fa-file-lines"></i> <?php echo $t['m_reports']; ?></a>
</aside>

<!-- MAIN PANEL -->
<div class="main-panel">
    <div class="page-title">
        <h1><?php echo $t['m_teachers']; ?></h1>
        <p><?php echo $t['manage_desc']; ?></p>
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
            <i class="fa-solid fa-chalkboard-user"></i> <?php echo $editData ? $t['edit_tch'] : $t['add_tch']; ?>
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
                    <label><?php echo $t['father']; ?></label>
                    <input type="text" name="father_name" value="<?php echo $editData['father_name'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['dob']; ?></label>
                    <input type="date" name="dob" value="<?php echo $editData['dob'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label><?php echo $t['email']; ?></label>
                    <input type="email" name="email" value="<?php echo $editData['username'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['phone']; ?></label>
                    <input type="text" name="phone" value="<?php echo $editData['contact_number'] ?? ''; ?>" required>
                </div>
                <div>
                    <label><?php echo $t['gender']; ?></label>
                    <select name="gender" required>
                        <option value=""><?php echo $t['select']; ?></option>
                        <option value="Male" <?php echo (isset($editData['gender']) && $editData['gender'] == 'Male') ? 'selected' : ''; ?>><?php echo $t['male']; ?></option>
                        <option value="Female" <?php echo (isset($editData['gender']) && $editData['gender'] == 'Female') ? 'selected' : ''; ?>><?php echo $t['female']; ?></option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div style="flex: 2;">
                    <label><?php echo $t['address']; ?></label>
                    <input type="text" name="address" value="<?php echo $editData['address'] ?? ''; ?>" required>
                </div>
                <div style="flex: 1;">
                    <label><?php echo $t['photo']; ?></label>
                    <input type="file" name="photo" accept="image/jpeg, image/png" <?php echo $editData ? '' : 'required'; ?>>
                    <?php if ($editData && !empty($editData['photo_path'])): ?>
                        <small style="color: green;"><?php echo $t['photo_hint']; ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($editData): ?>
            <div class="form-row" style="margin-top: 10px;">
                <div>
                    <label style="color: #b91c1c;"><i class="fa-solid fa-key"></i> <?php echo $t['lbl_password']; ?></label>
                    <input type="text" name="new_password" placeholder="Enter custom strong password here...">
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-dark" style="border: none; cursor: pointer; margin-top: 15px;">
                <i class="fa-solid <?php echo $editData ? 'fa-save' : 'fa-plus'; ?>"></i>
                <?php echo $editData ? $t['btn_update'] : $t['btn_add']; ?>
            </button>
            <?php if ($editData): ?>
                <a href="manage_teachers.php" class="btn-dark" style="background: #6b7280; text-decoration:none; display:inline-block; margin-top: 15px;"><?php echo $t['btn_cancel']; ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TEACHER DIRECTORY -->
    <div style="background: white; padding: 25px; border-radius: 8px; border: 1px solid var(--border-color);">
        <h3 style="margin-top: 0; color: var(--primary-color);">
            <i class="fa-solid fa-person-chalkboard"></i> <?php echo $t['directory']; ?> (<?php echo count($teachers); ?>)
        </h3>
        
        <?php if (count($teachers) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th><?php echo $t['s_no']; ?></th>
                            <th><?php echo $t['staff']; ?></th>
                            <th><?php echo $t['th_email']; ?></th>
                            <th><?php echo $t['th_phone']; ?></th>
                            <th><?php echo $t['th_added']; ?></th>
                            <th><?php echo $t['th_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; foreach ($teachers as $tch): ?>
                        <tr>
                            <td><?php echo $serial++; ?></td>
                            <td>
                                <?php if(!empty($tch['photo_path'])): ?>
                                    <img src="<?php echo $tch['photo_path']; ?>" class="student-photo" alt="Photo">
                                <?php endif; ?>
                                <strong class="student-name"><?php echo htmlspecialchars($tch['full_name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($tch['username']); ?></td>
                            <td><?php echo htmlspecialchars($tch['contact_number']); ?></td>
                            <td><?php echo date('d M Y', strtotime($tch['created_at'])); ?></td>
                            <td class="action-icons">
                                <a href="manage_teachers.php?edit_id=<?php echo $tch['id']; ?>" class="edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="manage_teachers.php?delete_id=<?php echo $tch['id']; ?>" onclick="return confirm('Delete permanently?');" class="delete" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted);"><?php echo $t['no_teachers']; ?></p>
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