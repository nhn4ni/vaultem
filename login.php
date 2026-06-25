<?php
session_start();

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['userEmail'] ?? '');
    $password = $_POST['userPassword'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {

        // ── Route by email domain ────────────────────────────────────────────
        $isStudent = strpos($email, '@student.utem.edu.my') !== false;
        $isStaff   = !$isStudent && strpos($email, '@utem.edu.my') !== false;

        if ($isStudent) {
            // ── Student login ────────────────────────────────────────────────
            $stmt = $conn->prepare("SELECT * FROM student WHERE Student_Mail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (trim($password) === trim($user['Student_Password'])) {
                    $_SESSION['role']         = 'student';
                    $_SESSION['Student_ID']   = $user['Student_ID'];
                    $_SESSION['Student_Name'] = $user['Student_Name'];
                    $_SESSION['Email']        = $email;
                    $stmt->close(); $conn->close();
                    header("Location: mainStatus.php");
                    exit();
                } else {
                    $error_message = "Wrong password. Please try again.";
                }
            } else {
                $error_message = "Student email not found in the system.";
            }
            $stmt->close();

        } elseif ($isStaff) {
            // ── Staff login ──────────────────────────────────────────────────
            $stmt = $conn->prepare("SELECT * FROM staff WHERE Staff_Mail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (trim($password) === trim($user['Staff_Password'])) {
                    $_SESSION['role']       = 'staff';
                    $_SESSION['Staff_ID']   = $user['Staff_ID'];
                    $_SESSION['Staff_Name'] = $user['Staff_Name'];
                    $_SESSION['Email']      = $email;
                    $stmt->close(); $conn->close();
                    header("Location: staffMainStatus.php");
                    exit();
                } else {
                    $error_message = "Wrong password. Please try again.";
                }
            } else {
                $error_message = "Staff email not found in the system.";
            }
            $stmt->close();

        } else {
            $error_message = "Unrecognised email domain. Use @student.utem.edu.my or @utem.edu.my.";
        }
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
        <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">

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