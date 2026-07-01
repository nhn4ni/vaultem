<?php
session_start();

// ── PHPMailer credentials ─────────────────────────────────────────────────────
define('MAIL_USER', 'muhdfariospol@gmail.com');
define('MAIL_PASS', 'nbkw rovv xsil rkcj');
define('MAIL_FROM', 'muhdfariospol@gmail.com');
define('CODE_EXPIRY', 600); // 10 minutes

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$error   = '';
$success = false;
$urlStep = $_GET['step'] ?? '1';

// ── Helper: send email ────────────────────────────────────────────────────────
function sendCode(string $toEmail, string $toName, string $code): bool {
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    require_once 'PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(MAIL_FROM, 'VaulteM');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'VaulteM: Password Reset Code';
        $mail->Body    = "
        <div style='font-family:Courier New,monospace;max-width:480px;margin:0 auto;
                    background:#241253;color:#E8E9DE;border-radius:16px;padding:32px;'>
            <h2 style='margin:0 0 4px;color:#b084ff;'>VaulteM</h2>
            <p style='color:rgba(232,233,222,0.5);margin:0 0 28px;font-size:13px;'>UTeM Store Management</p>
            <p style='margin:0 0 8px;'>Hi <strong>{$toName}</strong>,</p>
            <p style='margin:0 0 22px;color:rgba(232,233,222,0.7);'>
                Use the code below to reset your password.<br>
                It expires in <strong>10 minutes</strong>.
            </p>
            <div style='background:rgba(124,92,252,0.18);border:1px solid rgba(124,92,252,0.4);
                        border-radius:12px;padding:24px;text-align:center;margin-bottom:24px;'>
                <span style='font-size:36px;font-weight:800;letter-spacing:12px;color:#b084ff;'>
                    {$code}
                </span>
            </div>
            <p style='font-size:12px;color:rgba(232,233,222,0.35);margin:0;'>
                If you did not request this, ignore this email. Your password will not change.
            </p>
        </div>";
        $mail->AltBody = "Your VaulteM reset code: {$code} (expires in 10 minutes)";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

// ── STEP 1 POST: find account, send code ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '1') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $error = "Please enter your email.";
    } else {
        // Check student table first
        $stmt = $conn->prepare("SELECT Student_ID, Student_Name FROM student WHERE Student_Mail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        $found = false;
        $uid   = '';
        $uname = '';
        $utype = '';

        if ($res->num_rows === 1) {
            $row   = $res->fetch_assoc();
            $found = true;
            $uid   = $row['Student_ID'];
            $uname = $row['Student_Name'];
            $utype = 'student';
        } else {
            // Check staff table
            $stmt2 = $conn->prepare("SELECT Staff_ID, Staff_Name FROM staff WHERE Staff_Mail = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $stmt2->close();
            if ($res2->num_rows === 1) {
                $row   = $res2->fetch_assoc();
                $found = true;
                $uid   = $row['Staff_ID'];
                $uname = $row['Staff_Name'];
                $utype = 'staff';
            }
        }

        if (!$found) {
            // Deliberately vague
            $error = "If that email is registered, a reset code has been sent.";
        } else {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $_SESSION['fp_email']   = $email;
            $_SESSION['fp_uid']     = $uid;
            $_SESSION['fp_uname']   = $uname;
            $_SESSION['fp_utype']   = $utype;
            $_SESSION['fp_code']    = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['fp_expiry']  = time() + CODE_EXPIRY;
            $_SESSION['fp_tries']   = 0;

            if (sendCode($email, $uname, $code)) {
                header("Location: forgotpass.php?step=2");
                exit();
            } else {
                $error = "Failed to send email. Please check your PHPMailer setup.";
                unset($_SESSION['fp_email'], $_SESSION['fp_uid'], $_SESSION['fp_code'],
                      $_SESSION['fp_expiry'], $_SESSION['fp_utype'], $_SESSION['fp_uname']);
            }
        }
    }
}

// ── STEP 2 POST: verify code ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
    if (!isset($_SESSION['fp_code'])) {
        header("Location: forgotpass.php"); exit();
    }
    if (time() > ($_SESSION['fp_expiry'] ?? 0)) {
        unset($_SESSION['fp_code'], $_SESSION['fp_uid'], $_SESSION['fp_email'],
              $_SESSION['fp_utype'], $_SESSION['fp_expiry']);
        header("Location: forgotpass.php?step=expired"); exit();
    }

    $_SESSION['fp_tries'] = ($_SESSION['fp_tries'] ?? 0) + 1;
    if ($_SESSION['fp_tries'] > 5) {
        unset($_SESSION['fp_code'], $_SESSION['fp_uid'], $_SESSION['fp_email'],
              $_SESSION['fp_utype'], $_SESSION['fp_expiry']);
        header("Location: forgotpass.php?step=locked"); exit();
    }

    $code = trim($_POST['code'] ?? '');
    if (!password_verify($code, $_SESSION['fp_code'])) {
        $remaining = max(0, 5 - $_SESSION['fp_tries']);
        $error = "Incorrect code. {$remaining} attempt(s) remaining.";
        $urlStep = '2';
    } else {
        $_SESSION['fp_verified'] = true;
        unset($_SESSION['fp_code']);
        header("Location: forgotpass.php?step=3"); exit();
    }
}

// ── STEP 3 POST: update password ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '3') {
    if (!($_SESSION['fp_verified'] ?? false) || !isset($_SESSION['fp_uid'])) {
        header("Location: forgotpass.php"); exit();
    }

    $newpass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$newpass || !$confirm) {
        $error = "Please fill in both fields.";
        $urlStep = '3';
    } elseif (strlen($newpass) < 6) {
        $error = "Password must be at least 6 characters.";
        $urlStep = '3';
    } elseif ($newpass !== $confirm) {
        $error = "Passwords do not match.";
        $urlStep = '3';
    } else {
        $uid   = $_SESSION['fp_uid'];
        $utype = $_SESSION['fp_utype'];

        if ($utype === 'student') {
            $stmt = $conn->prepare("UPDATE student SET Student_Password = ? WHERE Student_ID = ?");
        } else {
            $stmt = $conn->prepare("UPDATE staff SET Staff_Password = ? WHERE Staff_ID = ?");
        }
        $stmt->bind_param("ss", $newpass, $uid);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION['fp_email'], $_SESSION['fp_uid'], $_SESSION['fp_uname'],
              $_SESSION['fp_utype'], $_SESSION['fp_verified'], $_SESSION['fp_tries'],
              $_SESSION['fp_expiry']);

        $success = true;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Reset Password</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <link rel="stylesheet" href="forgotpass.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .rightcontainer {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .rightcontainer form { width: 100%; }

        .msg-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 14px;
        }
        .msg-info {
            background: rgba(124,92,252,0.1);
            border: 1px solid rgba(124,92,252,0.25);
            color: #b084ff;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .msg-success {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #22c55e;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .msg-warn {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            color: #f59e0b;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Code input */
        .code-input {
            letter-spacing: 10px;
            font-size: 1.6rem;
            text-align: center;
            font-weight: 800;
        }

        /* Strength bar */
        .strength-bar {
            height: 4px;
            border-radius: 4px;
            background: rgba(232,233,222,0.12);
            margin-top: -14px;
            margin-bottom: 6px;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 4px;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }
        .strength-label {
            font-size: 0.7rem;
            color: rgba(232,233,222,0.4);
            margin-bottom: 16px;
            text-align: right;
        }

        .resend-link {
            display: block;
            margin-top: 14px;
            color: rgba(232,233,222,0.4);
            font-size: 0.82rem;
            text-decoration: none;
            text-align: center;
        }
        .resend-link:hover { color: #E8E9DE; }

        .back-link {
            display: block;
            margin-top: 12px;
            color: rgba(232,233,222,0.35);
            font-size: 0.78rem;
            text-decoration: none;
            text-align: center;
        }
        .back-link:hover { color: rgba(232,233,222,0.7); }

        @media (max-width: 768px) {
            .container { display:flex; justify-content:center; align-items:center; min-height:100vh; }
            .rightcontainer { width:100%; margin:0 auto; }
            .leftcontainer  { display:none; }
        }
    </style>
</head>
<body>
<button class="back" onclick="history.back()">&#60; Back</button>

<div class="container">
    <div class="leftcontainer">
        <header>
            <h1>VaulteM</h1>
            <p>UTeM Store Management</p>
        </header>
    </div>

    <div class="rightcontainer">

        <?php if ($success): ?>
        <!-- ── Done ── -->
        <h2>Password Updated</h2>
        <div class="msg-success">Your password has been changed successfully.</div>
        <a href="login.php" style="
            display:block; width:100%; text-align:center; box-sizing:border-box;
            background:#241253; color:#E8E9DE; border-radius:25px;
            padding:15px; font-size:1rem; font-weight:bold; text-decoration:none;
        ">Go to Login</a>

        <?php elseif ($urlStep === 'expired'): ?>
        <!-- ── Code expired ── -->
        <h2>Code Expired</h2>
        <div class="msg-warn">Your reset code has expired. Codes are valid for 10 minutes.</div>
        <a href="forgotpass.php" style="
            display:block; width:100%; text-align:center; box-sizing:border-box;
            background:#241253; color:#E8E9DE; border-radius:25px;
            padding:15px; font-size:1rem; font-weight:bold; text-decoration:none;
        ">Request New Code</a>

        <?php elseif ($urlStep === 'locked'): ?>
        <!-- ── Too many attempts ── -->
        <h2>Too Many Attempts</h2>
        <div class="msg-warn">You have entered the wrong code too many times. Please start over.</div>
        <a href="forgotpass.php" style="
            display:block; width:100%; text-align:center; box-sizing:border-box;
            background:#241253; color:#E8E9DE; border-radius:25px;
            padding:15px; font-size:1rem; font-weight:bold; text-decoration:none;
        ">Start Over</a>

        <?php elseif ($urlStep === '1'): ?>
        <!-- ── Step 1: Enter email ── -->
        <form action="forgotpass.php" method="POST">
            <input type="hidden" name="step" value="1">
            <h2>Reset Password</h2>

            <?php if ($error): ?>
                <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="email" name="email"
                   placeholder="Your registered email"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   required>

            <button type="submit">Send Reset Code</button>
        </form>
        <a href="login.php" class="back-link">Back to Login</a>

        <?php elseif ($urlStep === '2'): ?>
        <!-- ── Step 2: Enter code ── -->
        <form action="forgotpass.php" method="POST">
            <input type="hidden" name="step" value="2">
            <h2>Enter Code</h2>

            <div class="msg-info">
                A 6-digit code was sent to
                <strong><?php echo htmlspecialchars($_SESSION['fp_email'] ?? ''); ?></strong>.
                Check your inbox and enter the code below.
                It expires in 10 minutes.
            </div>

            <?php if ($error): ?>
                <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="text" name="code"
                   class="code-input"
                   placeholder="000000"
                   maxlength="6"
                   pattern="\d{6}"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   required>

            <button type="submit">Verify Code</button>
        </form>
        <a href="forgotpass.php" class="resend-link">Did not receive the code? Start over</a>

        <?php elseif ($urlStep === '3'): ?>
        <!-- ── Step 3: New password ── -->
        <form action="forgotpass.php" method="POST">
            <input type="hidden" name="step" value="3">
            <h2>New Password</h2>

            <?php if ($error): ?>
                <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="password" name="new_password"
                   placeholder="New password (min. 6 characters)"
                   oninput="checkStrength(this.value)"
                   required>

            <div class="strength-bar">
                <div class="strength-fill" id="strengthFill"></div>
            </div>
            <p class="strength-label" id="strengthLabel">Password strength</p>

            <input type="password" name="confirm_password"
                   placeholder="Confirm new password"
                   required>

            <button type="submit">Update Password</button>
        </form>

        <?php endif; ?>

    </div>
</div>

<script>
    function checkStrength(val) {
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        if (!fill) return;
        let score = 0;
        if (val.length >= 6)           score++;
        if (val.length >= 10)          score++;
        if (/[A-Z]/.test(val))         score++;
        if (/[0-9]/.test(val))         score++;
        if (/[^A-Za-z0-9]/.test(val))  score++;
        const widths = ['0%','20%','40%','65%','85%','100%'];
        const colors = ['#ddd','#ef4444','#f59e0b','#f59e0b','#22c55e','#22c55e'];
        const labels = ['','Weak','Fair','Fair','Strong','Very Strong'];
        fill.style.width      = widths[score];
        fill.style.background = colors[score];
        label.textContent     = labels[score] || 'Password strength';
    }
</script>
</body>
</html>