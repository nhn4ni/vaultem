<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Main Page</title>
</head>
<body>
    <?php if ($role == 'student'): ?>
        <!-- Student Interface -->
        <h1>Welcome, Student!</h1>
        <p>This is your dashboard.</p>
        <!-- You can add student-specific content here -->

    <?php elseif ($role == 'staff'): ?>
        <!-- Staff Interface -->
        <h1>Welcome, Staff!</h1>
        <p>This is your dashboard.</p>
        <!-- You can add staff-specific content here -->

    <?php else: ?>
        <p>Unknown role. Please login again.</p>
    <?php endif; ?>

    <!-- Common logout button -->
    <form method="post" action="logout.php">
        <button type="submit">Logout</button>
    </form>
</body>
</html>