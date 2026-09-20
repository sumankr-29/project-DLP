<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php';

// ======================================================
//  Only allow POST requests
// ======================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../secure_login.php");
    exit;
}

// ======================================================
//  CSRF TOKEN VERIFICATION
//  Blocks bots that POST directly to this endpoint
// ======================================================
$posted_token  = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (empty($posted_token) || empty($session_token) || !hash_equals($session_token, $posted_token)) {
    // Forged or missing token → treat as bot
    header("Location: ../secure_login.php?error=session_expired");
    exit;
}

// ======================================================
//  SERVER-SIDE WAIT TIME ENFORCEMENT
//  core fix — the server itself checks the clock
// ======================================================
$has_logged_in = isset($_COOKIE['has_logged_in']);
$wait_time     = $has_logged_in ? 5 : 30;

$login_start = $_SESSION['login_start_time'] ?? 0;
$elapsed     = time() - $login_start;

if ($elapsed < $wait_time) {
    // User (or bot) submitted the form before the timer finished.
    // Do NOT reset the timer — make them wait the remaining seconds.
    header("Location: ../secure_login.php?error=too_fast");
    exit;
}

// ======================================================
//  RATE LIMITING / BRUTE-FORCE LOCKOUT
//  Max 5 failed attempts, then a 15-minute lockout
// ======================================================
$lockout_until = $_SESSION['lockout_until'] ?? 0;
if ($lockout_until > time()) {
    header("Location: ../secure_login.php?error=locked");
    exit;
}

// Clean up stale lockout once it has expired
if ($lockout_until > 0 && $lockout_until <= time()) {
    unset($_SESSION['lockout_until']);
    $_SESSION['attempts'] = 0;
}

$_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;

// On every 6th attempt, lock out for 15 minutes
if ($_SESSION['attempts'] > 5) {
    $_SESSION['lockout_until'] = time() + 900; // 15 minutes
    $_SESSION['attempts']      = 0;
    unset($_SESSION['login_start_time']);
    unset($_SESSION['csrf_token']);
    unset($_SESSION['math_answer']);
    header("Location: ../secure_login.php?error=locked");
    exit;
}

// ======================================================
//  MATH CHALLENGE VERIFICATION
// ======================================================
$math_answer = intval($_POST['math_answer'] ?? -1);
if ($math_answer !== ($_SESSION['math_answer'] ?? -1)) {
    // Wrong answer → reset timer so they must wait the full duration again
    $_SESSION['login_start_time'] = time();
    unset($_SESSION['math_answer']);
    unset($_SESSION['csrf_token']);
    header("Location: ../secure_login.php?error=wrong_math");
    exit;
}

// ======================================================
//  NORMAL LOGIN LOGIC
// ======================================================
$role     = $_POST['role'] ?? '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($role) || empty($username) || empty($password)) {
    $_SESSION['login_start_time'] = time();
    unset($_SESSION['csrf_token']);
    unset($_SESSION['math_answer']);
    header("Location: ../secure_login.php?error=invalid");
    exit;
}

$stmt = $conn->prepare("SELECT id, username, password, role, full_name, dob FROM users WHERE username = ? AND role = ?");
$stmt->bind_param("ss", $username, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $is_authenticated = false;

    if ($role === 'student') {
        // Student login: password is DOB in YYYY-MM-DD format
        $input_dob = date('Y-m-d', strtotime($password));
        if (!empty($user['dob']) && $input_dob === $user['dob']) {
            $is_authenticated = true;
        }
    } else {
        // Admin & Teacher: verify hashed password
        if (password_verify($password, $user['password'])) {
            $is_authenticated = true;
        }
    }

    if ($is_authenticated) {
        // ==========================================
        //  SESSION HARDENING ON SUCCESS
        // ==========================================
        session_regenerate_id(true); // Prevents session fixation attacks

        // Clear all bot-protection & login state
        unset($_SESSION['attempts']);
        unset($_SESSION['lockout_until']);
        unset($_SESSION['login_start_time']);
        unset($_SESSION['math_answer']);
        unset($_SESSION['csrf_token']);

        // Set authenticated session variables
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['id']        = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // Mark this device as a "returning user" → 5-second wait next time
        // httpOnly = true (6th arg) prevents JS access; secure = false (set true on HTTPS)
        setcookie('has_logged_in', '1', [
            'expires'  => time() + (86400 * 30), // 30 days
            'path'     => '/',
            'secure'   => false,   // ← set to true when you have HTTPS
            'httponly' => true,    // ← prevents JavaScript from reading it
            'samesite' => 'Lax'    // ← mitigates CSRF via cross-site navigation
        ]);

        // Redirect to appropriate dashboard
        switch ($user['role']) {
            case 'admin':
                header("Location: ../admin_dashboard.php");
                break;
            case 'teacher':
                header("Location: ../teacher_dashboard.php");
                break;
            case 'student':
                header("Location: ../student_dashboard.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit;
    }
}

// ======================================================
//  FAILED LOGIN — reset timer & send back
// ======================================================
$_SESSION['login_start_time'] = time();
unset($_SESSION['math_answer']);
unset($_SESSION['csrf_token']);
header("Location: ../secure_login.php?error=invalid");
exit;