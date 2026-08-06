<?php
session_start();

// Guard rail: Ensure user is logged in as a student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require_once 'backend/db_connect.php';
$student_id = $_SESSION['id'] ?? 0;

// ----- 1. DYNAMIC LANGUAGE & POP-UP SELECTOR CONFIGURATION -----
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'hi' ? 'hi' : 'en';
    $_SESSION['lang_popup_shown'] = true; // Turn off the first-time popup flag
}

$lang = $_SESSION['lang'] ?? 'en';
// Show first-time popup only if the language session indicator hasn't been committed yet
$show_lang_popup = !isset($_SESSION['lang_popup_shown']);

// Load separated dynamic external dictionary files based on selection state
$t = require "languages/student_dashboard-lang/{$lang}.php";


// ----- 2. FETCH STUDENT DETAILS & CLASS -----
$stmt = $conn->prepare("SELECT username, full_name, class FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$my_class = $profile['class'] ?? '';


// ----- 3. FETCH ALL SUBJECTS & LESSON MATERIAL STRUCTURE -----
$subjects_list = [];
$lessons_structure = [];
$total_lessons_count = 0;

if (!empty($my_class)) {
    // Fetch active assigned subject syllabus list
    $sub_stmt = $conn->prepare("SELECT DISTINCT subject_name FROM class_subjects WHERE class_name = ? ORDER BY subject_name ASC");
    $sub_stmt->bind_param("s", $my_class);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();
    while ($row = $sub_res->fetch_assoc()) {
        $subjects_list[] = $row['subject_name'];
    }

    // Fetch all lessons published for this specific classroom standard index
    $les_stmt = $conn->prepare("SELECT id, subject_name, chapter_name, file_path, video_file_path, video_url FROM lessons WHERE class_name = ? ORDER BY created_at ASC");
    $les_stmt->bind_param("s", $my_class);
    $les_stmt->execute();
    $les_res = $les_stmt->get_result();
    while ($row = $les_res->fetch_assoc()) {
        $lessons_structure[$row['subject_name']][] = $row;
        $total_lessons_count++;
    }
}


// ----- 4. DETERMINE ACTIVE SELECTED LESSON CONTENT -----
$active_lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
$active_media_type = $_GET['type'] ?? ''; 
$current_lesson = null;

if ($active_lesson_id > 0) {
    foreach ($lessons_structure as $subject => $lessons) {
        foreach ($lessons as $l) {
            if ($l['id'] === $active_lesson_id) {
                $current_lesson = $l;
                break 2;
            }
        }
    }
}

// Fallback: Default to the first lesson entry if none is clicked yet
if (!$current_lesson && !empty($lessons_structure)) {
    $first_subject = array_key_first($lessons_structure);
    $current_lesson = $lessons_structure[$first_subject][0];
    $active_lesson_id = $current_lesson['id'];
    if (!empty($current_lesson['video_file_path'])) $active_media_type = 'local_video';
    elseif (!empty($current_lesson['video_url'])) $active_media_type = 'video';
    else $active_media_type = 'file';
}


// ----- 5. LOG PROGRESS MILESTONE FEED -----
if ($current_lesson && !$show_lang_popup) {
    $check_log = $conn->prepare("SELECT id FROM student_learning_log WHERE student_id = ? AND lesson_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $check_log->bind_param("ii", $student_id, $active_lesson_id);
    $check_log->execute();
    if ($check_log->get_result()->num_rows == 0) {
        $log_stmt = $conn->prepare("INSERT INTO student_learning_log (student_id, lesson_id) VALUES (?, ?)");
        $log_stmt->bind_param("ii", $student_id, $active_lesson_id);
        $log_stmt->execute();
    }
}


// ----- 6. CALCULATE PROGRESS PERCENT VALUE -----
$completed_count = 0;
$progress_percent = 0.00;
if ($total_lessons_count > 0) {
    $prog_stmt = $conn->prepare("SELECT COUNT(DISTINCT lesson_id) AS count FROM student_learning_log WHERE student_id = ?");
    $prog_stmt->bind_param("i", $student_id);
    $prog_stmt->execute();
    $completed_count = $prog_stmt->get_result()->fetch_assoc()['count'];
    $progress_percent = round(($completed_count / $total_lessons_count) * 100, 2);
}


// ----- 7. FETCH MENTOR INFORMATION -----
$mentor_name = $t['not_assigned'];
if ($current_lesson) {
    $mentor_stmt = $conn->prepare("SELECT u.full_name FROM class_subjects cs JOIN users u ON cs.teacher_id = u.id WHERE cs.class_name = ? AND cs.subject_name = ?");
    $mentor_stmt->bind_param("ss", $my_class, $current_lesson['subject_name']);
    $mentor_stmt->execute();
    $m_res = $mentor_stmt->get_result()->fetch_assoc();
    if ($m_res) {
        $mentor_name = $t['prof'] . " " . $m_res['full_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['student_portal']; ?> - Class <?php echo htmlspecialchars($my_class); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body { background-color: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .lms-wrapper { display: grid; grid-template-columns: 320px 1fr; min-height: calc(100vh - 75px); }
        
        /* FIRST-TIME ENTRY MODAL STYLES */
        .lang-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.88); display: flex; justify-content: center; align-items: center; z-index: 99999; }
        .lang-modal-card { background: white; padding: 40px; border-radius: 12px; max-width: 460px; width: 90%; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalReveal 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes modalReveal { from { transform: scale(0.9) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
        .lang-modal-card h3 { margin-top: 0; color: #1e3a8a; font-size: 21px; margin-bottom: 12px; font-weight: bold; }
        .lang-modal-card p { color: #64748b; font-size: 14px; margin: 5px 0; line-height: 1.5; }
        .modal-btn-group { display: flex; gap: 15px; margin-top: 30px; justify-content: center; width: 100%; }
        .modal-link-btn { text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; font-size: 15px; transition: all 0.2s; width: 100%; text-align: center; display: inline-block; box-sizing: border-box; }
        .m-btn-en { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .m-btn-en:hover { background: #e2e8f0; color: #0f172a; }
        .m-btn-hi { background: #2563eb; color: white; border: 1px solid #2563eb; box-shadow: 0 4px 6px -1px rgba(37,99,235,0.2); }
        .m-btn-hi:hover { background: #1d4ed8; transform: translateY(-1px); }

        /* SIDEBAR STYLES */
        .lms-sidebar { background: white; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; overflow-y: auto; position: sticky; top: 75px; height: calc(100vh - 75px); box-sizing: border-box; }
        .sidebar-top { padding: 20px; }
        .course-title { font-size: 18px; color: #1e3a8a; font-weight: bold; margin: 0 0 15px 0; display: flex; justify-content: space-between; align-items: center; }
        .progress-box { background: #f8fafc; border-radius: 6px; padding: 12px; margin-bottom: 20px; border: 1px solid #edf2f7; font-size: 13px; }
        .progress-text { display: flex; justify-content: space-between; margin-bottom: 5px; color: var(--text-muted); font-weight: 500; }
        
        .subject-accordion { border-bottom: 1px solid #f1f5f9; }
        .accordion-trigger { width: 100%; text-align: left; background: none; border: none; padding: 14px 20px; font-size: 14px; font-weight: 600; color: #334155; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .accordion-trigger:hover { background: #f8fafc; }
        .accordion-trigger.active { color: #2563eb; background: #eff6ff; }
        .accordion-content { display: none; background: #fafafa; padding: 5px 0; }
        .accordion-trigger.active + .accordion-content { display: block; }
        
        .lesson-nav-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 25px; font-size: 13px; text-decoration: none; color: #64748b; line-height: 1.4; transition: 0.2s; }
        .lesson-nav-item:hover { background: #f1f5f9; color: #1e293b; }
        .lesson-nav-item.current { color: #2563eb; font-weight: 600; background: #f0fdf4; }
        .circle-indicator { width: 14px; height: 14px; border: 2px solid #cbd5e1; border-radius: 50%; display: inline-block; margin-top: 2px; flex-shrink: 0; position: relative; }
        .lesson-nav-item.current .circle-indicator { border-color: #10b981; background: #10b981; }
        
        .mentor-card { background: #fffbeb; border-top: 1px solid #fef3c7; padding: 15px 20px; font-size: 13px; }
        .mentor-card h5 { margin: 0 0 4px 0; font-size: 12px; text-transform: uppercase; color: #b45309; font-weight: bold; }
        .mentor-card p { margin: 0; color: #78350f; font-weight: 500; }

        /* VIEWPORT CONTENT CONTAINER */
        .lms-content { padding: 30px; box-sizing: border-box; overflow-y: auto; }
        .content-header-badge { display: inline-block; background: #00b4d8; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 12px; }
        .content-heading-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; }
        .content-title { font-size: 20px; color: #0f172a; font-weight: bold; margin: 0; }

        .player-viewport-frame { background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .pdf-zoom-bar { background: #f8fafc; padding: 10px 20px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: center; }
        .zoom-btn { background: white; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; color: #334155; transition: 0.15s; }
        .zoom-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        
        .media-container { width: 100%; aspect-ratio: 16/9; background: #000; position: relative; display: flex; justify-content: center; align-items: center; }
        .pdf-scroll-outer { width: 100%; height: 100%; overflow: auto; background: #525659; display: flex; justify-content: center; align-items: flex-start; }
        .media-container iframe, .media-container video { width: 100%; height: 100%; border: none; }
        .pdf-iframe-render { width: 100%; height: 100%; border: none; transform-origin: top center; transition: transform 0.2s ease, width 0.2s ease, height 0.2s ease; }
        
        .workspace-tabs { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .tab-trigger { background: none; border: none; padding: 12px 25px; font-size: 14px; font-weight: bold; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; }
        .tab-trigger.active { color: #2563eb; border-bottom-color: #2563eb; background: white; }
        .tab-pane { padding: 25px; background: white; min-height: 150px; font-size: 14px; color: #475569; line-height: 1.6; display: none; }
        .tab-pane.active { display: block; }

        @media (max-width: 1024px) {
            .lms-wrapper { grid-template-columns: 1fr; }
            .lms-sidebar { height: auto; position: relative; top: 0; }
        }
    </style>
</head>
<body>

    <?php if ($show_lang_popup): ?>
        <div class="lang-modal-overlay">
            <div class="lang-modal-card">
                <i class="fa-solid fa-language" style="font-size: 45px; color: #2563eb; margin-bottom: 15px;"></i>
                <h3>Select Your Language / भाषा चुनें</h3>
                <p>Please select your preferred instruction language to initialize your digital classroom library space.</p>
                <p style="font-size: 13.5px; font-weight: 500;">अपनी डिजिटल क्लासरूम लाइब्रेरी शुरू करने के लिए कृपया अपनी पसंदीदा शिक्षण भाषा चुनें।</p>
                
                <div class="modal-btn-group">
                    <a href="student_dashboard.php?lang=en<?php echo $active_lesson_id ? '&lesson_id='.$active_lesson_id.'&type='.$active_media_type : ''; ?>" class="modal-link-btn m-btn-en">English</a>
                    <a href="student_dashboard.php?lang=hi<?php echo $active_lesson_id ? '&lesson_id='.$active_lesson_id.'&type='.$active_media_type : ''; ?>" class="modal-link-btn m-btn-hi">हिंदी (Hindi)</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dash-header" style="position: sticky; top:0; z-index: 100; height: 75px; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center;">
        <div class="header-title">
            <i class="fa-solid fa-graduation-cap"></i>
            <span><?php echo $t['student_portal']; ?> - Class <?php echo htmlspecialchars($my_class); ?></span>
        </div>
        
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="color: white; font-size: 14px; font-weight: 500;">
                    <i class="fa-regular fa-user"></i> <?php echo $t['welcome']; ?>, <?php echo htmlspecialchars($profile['full_name']); ?>
                </span>
                <a href="logout.php" class="logout-btn" style="margin:0; padding: 4px 10px; font-size: 12px;">
                    <i class="fa-solid fa-right-from-bracket"></i> <?php echo $t['logout']; ?>
                </a>
            </div>
            
            <form method="GET" style="margin: 0; padding: 0;">
                <?php if ($active_lesson_id > 0): ?>
                    <input type="hidden" name="lesson_id" value="<?php echo $active_lesson_id; ?>">
                    <input type="hidden" name="type" value="<?php echo $active_media_type; ?>">
                <?php endif; ?>
                <select name="lang" onchange="this.form.submit()" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); border-radius: 4px; padding: 2px 6px; font-size: 11px; font-weight: 600; cursor: pointer; outline: none;">
                    <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?> style="color: black;">English</option>
                    <option value="hi" <?php echo $lang === 'hi' ? 'selected' : ''; ?> style="color: black;">हिंदी (Hindi)</option>
                </select>
            </form>
        </div>
    </div>

    <div class="lms-wrapper">
        
        <div class="lms-sidebar">
            <div class="sidebar-top">
                <div class="course-title">
                    <span><?php echo $t['curriculum_vault']; ?></span>
                    <i class="fa-solid fa-folder-open" style="font-size: 15px; color: #94a3b8;"></i>
                </div>
                
                <div class="progress-box">
                    <div class="progress-text">
                        <span><?php echo $t['overall_progress']; ?></span>
                        <strong><?php echo $progress_percent; ?>%</strong>
                    </div>
                    <div style="width: 100%; background: #e2e8f0; height: 6px; border-radius: 10px; overflow: hidden; margin-bottom: 8px;">
                        <div style="width: <?php echo $progress_percent; ?>%; background: #2563eb; height: 100%;"></div>
                    </div>
                    <span style="color: #64748b; font-size: 12px; font-weight: 500;">
                        <?php echo $completed_count; ?> <?php echo $t['of']; ?> <?php echo $total_lessons_count; ?> <?php echo $t['milestones']; ?>
                    </span>
                </div>

                <?php if (!empty($subjects_list)): ?>
                    <?php foreach ($subjects_list as $subject): ?>
                        <?php $is_active_accordion = ($current_lesson && $current_lesson['subject_name'] === $subject); ?>
                        <div class="subject-accordion">
                            <button class="accordion-trigger <?php echo $is_active_accordion ? 'active' : ''; ?>" onclick="toggleAccordion(this)">
                                <span><?php echo htmlspecialchars($subject); ?></span>
                                <i class="fa-solid <?php echo $is_active_accordion ? 'fa-chevron-down' : 'fa-chevron-right'; ?>" style="font-size: 12px;"></i>
                            </button>
                            
                            <div class="accordion-content" style="<?php echo $is_active_accordion ? 'display: block;' : ''; ?>">
                                <?php if (isset($lessons_structure[$subject]) && count($lessons_structure[$subject]) > 0): ?>
                                    <?php foreach ($lessons_structure[$subject] as $lesson): ?>
                                        <?php 
                                            if (!empty($lesson['video_file_path'])) { $type = 'local_video'; $label = $t['video']; }
                                            elseif (!empty($lesson['video_url'])) { $type = 'video'; $label = $t['video']; }
                                            else { $type = 'file'; $label = $t['theory']; }

                                            $is_current_item = ($active_lesson_id === $lesson['id']);
                                        ?>
                                        <a href="student_dashboard.php?lesson_id=<?php echo $lesson['id']; ?>&type=<?php echo $type; ?>" 
                                           class="lesson-nav-item <?php echo $is_current_item ? 'current' : ''; ?>">
                                            <span class="circle-indicator"></span>
                                            <span>
                                                <strong><?php echo $label; ?>:</strong> <?php echo htmlspecialchars($lesson['chapter_name']); ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="padding: 10px 25px; color: #94a3b8; font-size: 12px; font-style: italic;"><?php echo $t['no_topics']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mentor-card">
                <h5><?php echo $t['course_mentor']; ?></h5>
                <p><i class="fa-solid fa-chalkboard-user"></i> <?php echo htmlspecialchars($mentor_name); ?></p>
            </div>
        </div>

        <div class="lms-content">
            <?php if ($current_lesson): ?>
                
                <div class="content-header-badge">
                    <?php echo ($active_media_type === 'file') ? $t['doc_badge'] : $t['video_badge']; ?>
                </div>

                <div class="content-heading-row">
                    <h2 class="content-title"><?php echo htmlspecialchars($current_lesson['chapter_name']); ?></h2>
                </div>

                <div class="player-viewport-frame">
                    
                    <?php if ($active_media_type === 'file' && !empty($current_lesson['file_path'])): ?>
                        <div class="pdf-zoom-bar">
                            <button class="zoom-btn" onclick="zoomPDF(0.1)"><i class="fa-solid fa-magnifying-glass-plus"></i> <?php echo $t['zoom_in']; ?></button>
                            <button class="zoom-btn" onclick="zoomPDF(-0.1)"><i class="fa-solid fa-magnifying-glass-minus"></i> <?php echo $t['zoom_out']; ?></button>
                            <button class="zoom-btn" onclick="resetZoomPDF()"><i class="fa-solid fa-arrow-rotate-left"></i> <?php echo $t['reset']; ?></button>
                            <span id="zoom-percent-display" style="font-size: 13px; font-weight: bold; color: #475569; margin-left: 5px;">100%</span>
                        </div>
                    <?php endif; ?>

                    <div class="media-container">
                        <?php if ($active_media_type === 'video' && !empty($current_lesson['video_url'])): ?>
                            <?php 
                                $url = $current_lesson['video_url'];
                                $embed_url = $url;
                                if (strpos($url, 'youtube.com/watch?v=') !== false) {
                                    $parts = parse_url($url);
                                    parse_str($parts['query'], $query);
                                    if (isset($query['v'])) $embed_url = "https://www.youtube.com/embed/" . $query['v'];
                                } elseif (strpos($url, 'youtu.be/') !== false) {
                                    $path = parse_url($url, PHP_URL_PATH);
                                    $embed_url = "https://www.youtube.com/embed" . $path;
                                }
                            ?>
                            <iframe src="<?php echo htmlspecialchars($embed_url); ?>" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                        
                        <?php elseif ($active_media_type === 'local_video' && !empty($current_lesson['video_file_path'])): ?>
                            <video controls controlsList="nodownload">
                                <source src="<?php echo htmlspecialchars($current_lesson['video_file_path']); ?>" type="video/mp4">
                                Your browser does not support HTML5 video player elements.
                            </video>

                        <?php elseif ($active_media_type === 'file' && !empty($current_lesson['file_path'])): ?>
                            <div class="pdf-scroll-outer">
                                <iframe id="pdf-render-frame" class="pdf-iframe-render" src="<?php echo htmlspecialchars($current_lesson['file_path']); ?>#toolbar=0"></iframe>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="workspace-tabs">
                        <?php if ($active_media_type !== 'file'): ?>
                            <button class="tab-trigger active" onclick="switchTab(this, 'transcript-pane')">Transcript</button>
                            <button class="tab-trigger" onclick="switchTab(this, 'notes-pane')"><?php echo $t['subject_info']; ?></button>
                        <?php else: ?>
                            <button class="tab-trigger active" onclick="switchTab(this, 'notes-pane')"><?php echo $t['subject_info']; ?></button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($active_media_type !== 'file'): ?>
                        <div id="transcript-pane" class="tab-pane active">
                            <p style="margin:0; text-align: center; color: #94a3b8; padding: 15px 0;">
                                <i class="fa-regular fa-comment-video" style="font-size:24px; display:block; margin-bottom:5px;"></i>
                                <?php echo $t['no_transcript']; ?>
                            </p>
                        </div>
                        <div id="notes-pane" class="tab-pane">
                    <?php else: ?>
                        <div id="notes-pane" class="tab-pane active">
                            <p style="margin:0 0 15px 0; font-style: italic; color: #475569; background: #f8fafc; padding: 12px; border-left: 4px solid #00b4d8; border-radius: 4px;">
                                <i class="fa-solid fa-circle-info"></i> <?php echo $t['pdf_msg']; ?>
                            </p>
                    <?php endif; ?>
                        <table style="width:100%; border-collapse:collapse; font-size:14px;">
                            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0; font-weight:bold; width:150px;"><?php echo $t['subject_track']; ?></td><td><?php echo htmlspecialchars($current_lesson['subject_name']); ?></td></tr>
                            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0; font-weight:bold;"><?php echo $t['module_topic']; ?></td><td><?php echo htmlspecialchars($current_lesson['chapter_name']); ?></td></tr>
                            <tr><td style="padding:10px 0; font-weight:bold;"><?php echo $t['course_class']; ?></td><td>Class <?php echo htmlspecialchars($my_class); ?> <?php echo $t['curriculum_str']; ?></td></tr>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div style="background: white; padding: 80px 20px; text-align: center; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 20px;">
                    <i class="fa-solid fa-book-open-reader" style="font-size: 55px; color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3 style="color: #1e3a8a; margin: 0 0 8px 0;"><?php echo $t['classroom_quiet']; ?></h3>
                    <p style="color: #64748b; max-width:400px; margin: 0 auto; font-size:14px; line-height:1.5;"><?php echo $t['quiet_desc']; ?> <?php echo htmlspecialchars($my_class); ?>.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        let currentPdfZoom = 1.0;

        function zoomPDF(amount) {
            const frame = document.getElementById('pdf-render-frame');
            if (!frame) return;
            currentPdfZoom += amount;
            if (currentPdfZoom < 0.6) currentPdfZoom = 0.6;
            if (currentPdfZoom > 2.4) currentPdfZoom = 2.4;

            frame.style.transform = `scale(${currentPdfZoom})`;
            if (currentPdfZoom > 1.0) {
                frame.style.width = (100 / currentPdfZoom) + '%';
                frame.style.height = (100 * currentPdfZoom) + '%';
            } else {
                frame.style.width = '100%';
                frame.style.height = '100%';
            }
            document.getElementById('zoom-percent-display').innerText = Math.round(currentPdfZoom * 100) + '%';
        }

        function resetZoomPDF() {
            const frame = document.getElementById('pdf-render-frame');
            if (!frame) return;
            currentPdfZoom = 1.0;
            frame.style.transform = 'scale(1)';
            frame.style.width = '100%';
            frame.style.height = '100%';
            document.getElementById('zoom-percent-display').innerText = '100%';
        }

        function toggleAccordion(button) {
            const icon = button.querySelector('.fa-solid');
            const content = button.nextElementSibling;
            button.classList.toggle('active');
            
            if (button.classList.contains('active')) {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
                content.style.display = 'block';
            } else {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
                content.style.display = 'none';
            }
        }

        function switchTab(button, paneId) {
            const parent = button.parentElement;
            parent.querySelectorAll('.tab-trigger').forEach(btn => btn.classList.remove('active'));
            
            const grandparent = parent.parentElement;
            grandparent.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            button.classList.add('active');
            document.getElementById(paneId).classList.add('active');
        }
    </script>
</body>
</html>