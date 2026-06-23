<?php
// logout.php – destroys the session and returns user to login page
session_start();
session_unset();    // clear all session variables
session_destroy();  // destroy the session
header("Location: login.php");
exit();
?>
