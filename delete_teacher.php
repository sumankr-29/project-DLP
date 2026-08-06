<?php
session_start();
require_once 'backend/db_connect.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM users WHERE id = $id AND role = 'teacher'");
}
header("Location: manage_teachers.php");
exit;
?>