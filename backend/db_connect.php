<?php
// backend/db_connect.php
$host = 'localhost';
$user = 'root';
$pass = '';          // default XAMPP root password is empty
$db   = 'dlp_db';

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>