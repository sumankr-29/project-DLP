<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php';

$role     = $_POST['role'] ?? '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? ''; // This acts as the DOB for students

// Added 'dob' to the SELECT query so we can verify it for students
$stmt = $conn->prepare("SELECT id, username, password, role, full_name, dob FROM users WHERE username = ? AND role = ?");
$stmt->bind_param("ss", $username, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $is_authenticated = false;

    if ($role === 'student') {
        // STUDENT LOGIN: Convert whatever format they typed (2009-01-01) 
        // into standard database YYYY-MM-DD format
        $input_dob = date('Y-m-d', strtotime($password));
        
        if ($input_dob === $user['dob']) {
            $is_authenticated = true;
        } else {
            $error = "Invalid Date of Birth. Try using YYYY-MM-DD format.";
        }
    } else {
        // ADMIN & TEACHER LOGIN: Use normal hashed password verification
        if (password_verify($password, $user['password'])) {
            $is_authenticated = true;
        } else {
            $error = "Invalid password.";
        }
    }

    // If verification succeeded (either via DOB or Password)
    if ($is_authenticated) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['id']        = $user['id']; // Kept synced for dashboards
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

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
} else {
    $error = "User not found with that role and credentials.";
}

// If login fails
echo "<h2>Login Failed</h2>";
echo "<p>$error</p>";
echo '<a href="../index.php">Try again</a>';
?>