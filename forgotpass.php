<?php
session_start();

$host     = 'localhost';
$username = 'root';
$password = '';
$database = 'utem_accommodation';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error           = '';
$currentPassword = '';
$showToast       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    $email    = trim($_POST['email'] ?? '');
    $emailEsc = mysqli_real_escape_string($conn, $email);

    if (!$email) {
        $error = "Please enter your student email.";
    } elseif (!str_ends_with($email, '@student.utem.edu.my')) {
        $error = "Please use your UTeM student email (@student.utem.edu.my).";
    } else {
        $result = mysqli_query($conn, "SELECT Student_Password FROM student WHERE Student_Mail = '$emailEsc'");
        if (mysqli_num_rows($result) === 0) {
            $error = "No account found with that email.";
        } else {
            $row             = mysqli_fetch_assoc($result);
            $currentPassword = $row['Student_Password'];
            $showToast       = true;
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Forgot Password</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="newpasss.css"/>
    <link rel="stylesheet" href="mobile.css" type="text/css">
    <style>
        /* Center form content */
        .rightcontainer {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .rightcontainer form {
            width: 100%;
        }

        /* ── Toast notification ── */
        .toast {
            position: fixed;
            bottom: 32px;
            right: 32px;
            background: #2c2c54;
            color: #fff;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.3);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 320px;
            z-index: 9999;
            animation: slideIn 0.35s ease forwards;
        }

        .toast.hide {
            animation: slideOut 0.35s ease forwards;
        }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0);    opacity: 1; }
            to   { transform: translateX(120%); opacity: 0; }
        }

        .toast-icon {
            font-size: 20px;
            color: #a29bfe;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .toast-body {
            flex: 1;
        }

        .toast-title {
            font-size: 13px;
            font-weight: 600;
            color: #a29bfe;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }

        .toast-password {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            word-break: break-all;
        }

        .toast-close {
            background: none;
            border: none;
            color: #aaa;
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            flex-shrink: 0;
        }

        .toast-close:hover {
            color: #fff;
        }

        .error-msg {
            color: red;
            margin-bottom: 12px;
        }

        @media (max-width: 768px) {
            .container {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .rightcontainer {
                width: 90%;
                margin: 0 auto;
            }

            .leftcontainer {
                display: none;
            }

            .toast {
                bottom: 20px;
                right: 16px;
                left: 16px;
                max-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="back" onclick="history.back()">&#60; Back</button>

        <div class="leftcontainer">
            <header>
                <h1>VaulteM</h1>
                <p>UTeM Store Management</p>
            </header>
        </div>

        <div class="rightcontainer">
            <form action="forgotpass.php" method="POST">
                <h2>Forgot Password</h2>

                <?php if ($error): ?>
                    <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <input
                    type="email"
                    name="email"
                    placeholder="Student Email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required
                >
                <button type="submit" name="verify_email">Show My Password</button>
            </form>
        </div>
    </div>

    <!-- ── Toast ── -->
    <?php if ($showToast): ?>
    <div class="toast" id="toast">
        <i class="fa-solid fa-key toast-icon"></i>
        <div class="toast-body">
            <div class="toast-title">Your current password</div>
            <div class="toast-password"><?php echo htmlspecialchars($currentPassword); ?></div>
        </div>
        <button class="toast-close" onclick="dismissToast()" title="Dismiss">&times;</button>
    </div>

    <script>
        // Auto-dismiss after 8 seconds
        const toast = document.getElementById('toast');

        function dismissToast() {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 350);
        }

        setTimeout(dismissToast, 8000);
    </script>
    <?php endif; ?>

</body>
</html>