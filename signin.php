

<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'utem_accommodation';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_signin'])) {

    $name               = trim($_POST['name'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $password           = $_POST['password'] ?? '';
    $confirm            = $_POST['confirm_password'] ?? '';
    $genderRaw          = $_POST['gender'] ?? '';
    $residentialCollege = trim($_POST['residential_college'] ?? '');

    // Convert gender to single char for DB
    $gender = '';
    if ($genderRaw === 'male')   $gender = 'M';
    if ($genderRaw === 'female') $gender = 'F';

    // Basic validation
    if (!$name || !$email || !$phone || !$password || !$confirm || !$gender || !$residentialCollege) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!str_ends_with($email, '@student.utem.edu.my')) {
        $error = "Please use your UTeM student email (@student.utem.edu.my).";
    } else {

        // Check if email already exists
        $emailEsc = mysqli_real_escape_string($conn, $email);
        $checkEmail = mysqli_query($conn, "SELECT Student_ID FROM student WHERE Student_Mail = '$emailEsc'");
        if (mysqli_num_rows($checkEmail) > 0) {
            $error = "This email is already registered.";
        } else {

            // Auto-generate Student_ID from email prefix
            $newId = strtolower(explode('@', $email)[0]);

            // Check if Student_ID already exists
            $idEsc = mysqli_real_escape_string($conn, $newId);
            $checkId = mysqli_query($conn, "SELECT Student_ID FROM student WHERE Student_ID = '$idEsc'");
            if (mysqli_num_rows($checkId) > 0) {
                $error = "An account with this student ID already exists.";
            } else {

                // Escape all inputs
                $nameEsc        = mysqli_real_escape_string($conn, $name);
                $phoneEsc       = mysqli_real_escape_string($conn, $phone);
                $newIdEsc       = mysqli_real_escape_string($conn, $newId);
                $residentialEsc = mysqli_real_escape_string($conn, $residentialCollege);

                // Insert new student
                $sql = "INSERT INTO student 
                            (Student_ID, Student_Name, Student_Mail, Student_Password, Student_PhoneNo, Gender, Residential_ID)
                        VALUES 
                            ('$newIdEsc', '$nameEsc', '$emailEsc', '$password', '$phoneEsc', '$gender', '$residentialEsc')";

                if (mysqli_query($conn, $sql)) {
                    session_unset();
                    session_destroy();
                    session_start();
                    session_regenerate_id(true);
                    $_SESSION['Student_ID']   = $newId;
                    $_SESSION['Student_Name'] = $name;

                    mysqli_close($conn);
                    header("Location: mainStatus.php");
                    exit();
                } else {
                    $error = "Registration failed: " . mysqli_error($conn);
                }
            }
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Sign up</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">

    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="block.css" type="text/css">
    <link rel="stylesheet" href="mobile.css" type="text/css">
    <style>
        /* Tighten right container padding */
        .rightcontainer {
            padding: 20px 70px;
        }

        .rightcontainer form {
            width: 100%;
            max-width: 900px;  
            margin: 0 auto;     
        }

        /* Reduce heading margin */
        .rightcontainer h1 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        /* Tighten spacing between all form fields */
        .rightcontainer form input,
        .rightcontainer form select,
        .rightcontainer form button {
            margin-bottom: 8px;
            margin-right:auto;
            padding: 10px 16px;
        }

        /* Layout for the two-column dropdown row */
        .genderCollegeRow {
            display: flex;
            gap: 12px;
            width: 100%;
            margin-bottom: 8px;
        }

        .genderCollegeRow select {
            flex: 1;
            margin-bottom: 0;
        }

        /* Password field wrapper — positions the eye icon inside the input */
        .password-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 8px;
        }

        .password-wrapper input {
            width: 100%;
            box-sizing: border-box;
            padding-right: 42px; /* leave room for the icon */
            margin-bottom: 0;    /* wrapper handles the gap */
        }

        .password-wrapper .toggle-eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            font-size: 15px;
            user-select: none;
        }

        .password-wrapper .toggle-eye:hover {
            color: #333;
        }

        /* Center the right container when left panel is hidden (mobile/narrow view) */
        @media (max-width: 768px) {
            #wrapper {
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
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <button class="back" onclick="history.back()">&#60; Back</button>

        <div class="leftcontainer">
            <a href="studentMAIN.html"></a>
            <header>
                <h1>VaulteM</h1>
                <p>UTeM Store Management</p>
            </header>
        </div>

        <div class="rightcontainer">
            <h1>Sign Up</h1>

            <?php if ($error): ?>
                <p style="color: red; margin-bottom: 15px;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form action="signin.php" method="POST">

                <!-- 1. Full-width Name field -->
                <input type="text" name="name" placeholder="Name" required>

                <!-- 2. Gender & Residential College side by side -->
                <div class="genderCollegeRow">
                    <select name="gender" id="gender" required onchange="filterColleges(this.value)">
                        <option value="" disabled selected>Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>

                    <select name="residential_college" id="residential_college" required>
                        <option value="" disabled selected>Residential College</option>
                        <!-- Female colleges -->
                        <option value="1" class="female-college">Satria Lekir</option>
                        <option value="2" class="female-college">Satria Lekiu</option>
                        <option value="7" class="female-college">Lestari B</option>
                        <!-- Male colleges -->
                        <option value="3" class="male-college">Satria Kasturi</option>
                        <option value="4" class="male-college">Satria Jebat</option>
                        <option value="5" class="male-college">Satria Tuah</option>
                        <option value="6" class="male-college">Lestari A</option>
                    </select>
                </div>

                <!-- 3. Email & Phone -->
                <input type="email" name="email" placeholder="Student Email" required>
                <input type="text" name="phone" placeholder="Phone Number" required>

                <!-- 4. Password with toggle -->
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fa-solid fa-eye toggle-eye" id="togglePassword"></i>
                </div>

                <!-- 5. Confirm Password with toggle -->
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    <i class="fa-solid fa-eye toggle-eye" id="toggleConfirmPassword"></i>
                </div>

                <button type="submit" name="submit_signin">Sign Up</button>
            </form>
        </div>
    </div>

    <script>
        // ── Filter colleges by gender ──────────────────────────────────────────
        function filterColleges(gender) {
            const collegeSelect = document.getElementById('residential_college');
            const options = collegeSelect.querySelectorAll('option');

            collegeSelect.value = '';

            options.forEach(function (option) {
                if (!option.value) return; // skip placeholder

                if (gender === 'female') {
                    option.hidden = !option.classList.contains('female-college');
                } else if (gender === 'male') {
                    option.hidden = !option.classList.contains('male-college');
                } else {
                    option.hidden = false;
                }
            });
        }

        // ── Password visibility toggle ─────────────────────────────────────────
        function setupToggle(iconId, inputId) {
            const icon  = document.getElementById(iconId);
            const input = document.getElementById(inputId);

            icon.addEventListener('click', function () {
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                icon.classList.toggle('fa-eye',       !isHidden);
                icon.classList.toggle('fa-eye-slash',  isHidden);
            });
        }

        setupToggle('togglePassword',        'password');
        setupToggle('toggleConfirmPassword', 'confirm_password');
    </script>
</body>
</html>