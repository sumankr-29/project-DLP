<?php
session_start();
session_destroy();           // remove all session data
header("Location: index.php"); // go to main page
exit;

?>