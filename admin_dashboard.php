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

// ----- FETCH DASHBOARD STATISTICS -----
$stats = ['students' => 0, 'teachers' => 0, 'classes' => 0, 'materials' => 0];

$res = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='student'");
if ($res) $stats['students'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='teacher'");
if ($res) $stats['teachers'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(DISTINCT class_name) AS count FROM class_subjects");
if ($res) $stats['classes'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(*) AS count FROM lessons");
if ($res) $stats['materials'] = $res->fetch_assoc()['count'];

// ----- FETCH RECENTLY ADDED STUDENTS -----
$recent_students = [];
$query = "SELECT id, username, full_name, class, father_name, created_at, photo_path 
          FROM users WHERE role='student' ORDER BY id DESC LIMIT 5"; 

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($t['admin_panel']); ?> - GSSS Maranga</title>
    
    <!-- Main Dashboard Stylesheet (Points to css folder) -->
    <link rel="stylesheet" href="css/dashboard.css">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js for rendering graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- TOP HEADER -->
    <div class="dash-header">
        <div class="header-title">
            <button class="menu-toggle-btn" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <i class="fa-solid fa-school"></i>
            <span><?php echo htmlspecialchars($t['admin_panel']); ?></span>
        </div>
        <div class="header-right">
            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> <?php echo htmlspecialchars($t['logout']); ?>
            </a>
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

    <!-- SIDEBAR (Hidden by default) -->
    <aside class="sidebar" id="sidebarMenu">
        <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-chart-pie"></i> <?php echo htmlspecialchars($t['menu_overview']); ?></a>
        <a href="manage_students.php"><i class="fa-solid fa-user-graduate"></i> <?php echo htmlspecialchars($t['menu_students']); ?></a>
        <a href="manage_teachers.php"><i class="fa-solid fa-chalkboard-user"></i> <?php echo htmlspecialchars($t['menu_teachers']); ?></a>
        <a href="manage_classes.php"><i class="fa-solid fa-door-open"></i> <?php echo htmlspecialchars($t['menu_classes']); ?></a>
        <a href="upload_lesson.php"><i class="fa-solid fa-upload"></i> <?php echo htmlspecialchars($t['menu_upload']); ?></a>
        <a href="view_reports.php"><i class="fa-solid fa-file-lines"></i> <?php echo htmlspecialchars($t['menu_reports']); ?></a>
    </aside>

    <!-- MAIN PANEL -->
    <div class="main-panel">
        
        <div class="page-title">
            <h1><?php echo htmlspecialchars($t['welcome']); ?>, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?> 👋</h1>
            <p><?php echo htmlspecialchars($t['manage_platform']); ?></p>
        </div>

        <!-- HORIZONTAL QUICK ACTIONS ROW -->
        <div class="quick-actions-horizontal">
            <a href="manage_students.php" class="qa-h-btn student">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add Student</span>
            </a>
            <a href="manage_teachers.php" class="qa-h-btn teacher">
                <i class="fa-solid fa-chalkboard-user"></i>
                <span>Add Teacher</span>
            </a>
            <a href="manage_classes.php" class="qa-h-btn class">
                <i class="fa-solid fa-door-open"></i>
                <span>Add Class</span>
            </a>
            <a href="upload_lesson.php" class="qa-h-btn material">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>Upload Material</span>
            </a>
        </div>

        <!-- DASHBOARD GRID -->
        <div class="dashboard-grid">
            
            <!-- LEFT: Recent Students -->
            <div>
                <div class="dashboard-card">
                    <h3><i class="fa-solid fa-user-clock"></i> <?php echo htmlspecialchars($t['recent_students']); ?></h3>
                    
                    <?php if (count($recent_students) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars($t['roll_no']); ?></th>
                                        <th><?php echo htmlspecialchars($t['full_name']); ?></th>
                                        <th><?php echo htmlspecialchars($t['class']); ?></th>
                                        <th><?php echo htmlspecialchars($t['admission_date']); ?></th>
                                        <th><?php echo htmlspecialchars($t['actions']); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_students as $s): ?>
                                    <tr>
                                        <td><strong class="student-name"><?php echo htmlspecialchars($s['username']); ?></strong></td>
                                        <td>
                                            <?php if(!empty($s['photo_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($s['photo_path']); ?>" class="student-photo" alt="Photo">
                                            <?php endif; ?>
                                            <strong class="student-name"><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                        </td>
                                        <td><span class="class-badge">Class <?php echo htmlspecialchars($s['class']); ?></span></td>
                                        <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                                        <td class="action-icons">
                                            <a href="manage_students.php?edit_id=<?php echo $s['id']; ?>" class="edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                            <a href="manage_students.php?delete_id=<?php echo $s['id']; ?>" onclick="return confirm('Delete this student permanently?');" class="delete" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #64748b; font-size: 14px; text-align:center; padding: 30px;">
                            <?php echo htmlspecialchars($t['no_students']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Chart -->
            <div>
                <div class="dashboard-card">
                    <h3><i class="fa-solid fa-chart-simple"></i> Overview Statistics</h3>
                    <div class="chart-container">
                        <canvas id="overviewChart"></canvas>
                    </div>
                </div>
            </div>

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

        // Chart.js initialization
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('overviewChart').getContext('2d');
            
            const data = [
                <?php echo (int)$stats['students']; ?>, 
                <?php echo (int)$stats['teachers']; ?>, 
                <?php echo (int)$stats['classes']; ?>, 
                <?php echo (int)$stats['materials']; ?>
            ];
            
            const maxVal = Math.max(...data);
            const stepSize = Math.ceil(maxVal / 5) || 1;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Students', 'Teachers', 'Classes', 'Materials'],
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.85)',
                            'rgba(16, 185, 129, 0.85)',
                            'rgba(245, 158, 11, 0.85)',
                            'rgba(139, 92, 246, 0.85)'
                        ],
                        borderColor: [
                            '#2563eb',
                            '#059669',
                            '#d97706',
                            '#7c3aed'
                        ],
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: maxVal * 1.2,
                            ticks: {
                                stepSize: stepSize,
                                precision: 0,
                                font: { size: 12 }
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: { size: 12, weight: '600' }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            cornerRadius: 8
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>