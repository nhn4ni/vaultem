<?php
session_start();

// ── Auth guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role     = $_SESSION['role'];
// FIX: login.php sets User_Name for both students and staff
$userName = $_SESSION['User_Name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Dashboard</title>
        <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">

    <link rel="stylesheet" href="mobile.css">
    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        body { background-color: #E8E9DE; margin: 0; padding: 40px; }
        h1   { color: #241253; }
        .btn {
            display: inline-block; background-color: #241253; color: white;
            border: none; border-radius: 20px; padding: 12px 30px;
            font-size: 1rem; cursor: pointer; margin: 8px 4px; text-decoration: none;
        }
        .btn:hover { opacity: 0.85; }
        .logout-form { margin-top: 30px; }
    </style>
</head>
<body>

<?php if ($role === 'student'): ?>

    <h1>Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
    <p>Student dashboard</p>
    <a class="btn" href="mainStatus.php">View Booking Status</a>
    <a class="btn" href="form.php">New Booking</a>

<?php elseif ($role === 'staff'): ?>

    <h1>Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
    <p>Staff dashboard</p>
    <a class="btn" href="staffBookings.php">Manage Bookings</a>

<?php else: ?>

    <p>Unknown role. Please <a href="login.php">log in</a> again.</p>

<?php endif; ?>

<!-- FIX: logout form correctly POSTs to logout.php which destroys the session -->
<div class="logout-form">
    <form method="POST" action="logout.php">
        <button type="submit" class="btn">Logout</button>
    </form>
</div>

</body>
</html>
