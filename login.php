<?php
session_start();

$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbname = "utem_accommodation";

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['userEmail']) || !isset($_POST['userPassword']) ||
        empty(trim($_POST['userEmail'])) || empty($_POST['userPassword'])
    ) {
        $error_message = "Please fill in all fields.";
    } else {
        $email    = trim($_POST['userEmail']);
        $password = $_POST['userPassword'];

        // ── Check student table ──────────────────────────────────────────────
        $sql  = "SELECT * FROM student WHERE Student_Mail = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($password === $user['Student_Password']) {
                // FIX: use Student_ID and User_Name consistently across all files
                $_SESSION['role']      = 'student';
                $_SESSION['Student_ID'] = $user['Student_ID'];
                $_SESSION['User_Name'] = $user['Student_Name'];
                $_SESSION['Email']     = $email;

                header("Location: mainStatus.php");
                exit();
            } else {
                $error_message = "Login failed: wrong password.";
            }
        } else {
            // ── Check staff table ────────────────────────────────────────────
            $sql_staff  = "SELECT * FROM staff WHERE Staff_Email = ?";
            $stmt_staff = $conn->prepare($sql_staff);
            if (!$stmt_staff) {
                die("SQL Error: " . $conn->error);
            }
            $stmt_staff->bind_param("s", $email);
            $stmt_staff->execute();
            $result_staff = $stmt_staff->get_result();

            if ($result_staff->num_rows === 1) {
                $user_staff = $result_staff->fetch_assoc();
                if ($password === $user_staff['Staff_Password']) {
                    $_SESSION['role']     = 'staff';
                    $_SESSION['Staff_ID'] = $user_staff['Staff_ID'];
                    $_SESSION['User_Name'] = $user_staff['Staff_Name'];
                    $_SESSION['Email']    = $email;

                    header("Location: main.php");
                    exit();
                } else {
                    $error_message = "Login failed: wrong password.";
                }
            } else {
                if (strpos($email, '@student') !== false) {
                    $error_message = "Student email not found in the system.";
                } elseif (strpos($email, '@staff') !== false) {
                    $error_message = "Staff email not found in the system.";
                } else {
                    $error_message = "Email domain not recognised. Please contact admin.";
                }
            }
            $stmt_staff->close();
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Login</title>
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet" href="mobile.css">
</head>
<body>
<div id="wrapper">
    <button class="back" onclick="history.back()">&#60; Back</button>

    <div class="leftcontainer">
        <header>
            <h1>VaulteM</h1>
            <p>UTeM Store Management</p>
        </header>
    </div>

    <div class="rightcontainer">
        <h1>Log in</h1>

        <?php if (!empty($error_message)): ?>
            <div style="background:#dc3545;color:#fff;padding:10px 15px;border-radius:10px;margin-bottom:15px;max-width:700px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="email"    name="userEmail"    placeholder="Email"    required>
            <input type="password" name="userPassword" placeholder="Password" required>
            <button type="submit">Log in</button>
        </form>

        <p style="margin-top:15px;">
            Don't have an account? <a href="signin.php">Register here</a>
        </p>
        <p>
            <a href="newpass.php">Forgot password?</a>
        </p>
    </div>
</div>
</body>
</html>
