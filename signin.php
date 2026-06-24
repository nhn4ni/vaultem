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

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $genderRaw = $_POST['gender'] ?? '';

    // Convert gender to single char for DB
    $gender = '';
    if ($genderRaw === 'male')   $gender = 'M';
    if ($genderRaw === 'female') $gender = 'F';

    // Basic validation
    if (!$name || !$email || !$phone || !$password || !$confirm || !$gender) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        // Check if email already exists
        $emailEsc = mysqli_real_escape_string($conn, $email);
        $checkEmail = mysqli_query($conn, "SELECT Student_ID FROM student WHERE Student_Mail = '$emailEsc'");
        if (mysqli_num_rows($checkEmail) > 0) {
            $error = "This email is already registered.";
        } else {

            // Auto-generate Student_ID (e.g. S001, S002...)
            $newId = strtolower(explode('@', $email)[0]); // → "d032410034"

            // Check if Student_ID already exists (duplicate registration)
            $idEsc = mysqli_real_escape_string($conn, $newId);
            $checkId = mysqli_query($conn, "SELECT Student_ID FROM student WHERE Student_ID = '$idEsc'");
            if (mysqli_num_rows($checkId) > 0) {
                $error = "An account with this student ID already exists.";
            }

            

            // Escape all inputs
            $nameEsc  = mysqli_real_escape_string($conn, $name);
            $phoneEsc = mysqli_real_escape_string($conn, $phone);
            $newIdEsc = mysqli_real_escape_string($conn, $newId);

            // Insert new student
            // Residential_ID set to NULL for now since student hasn't booked yet
            $sql = "INSERT INTO student 
                        (Student_ID, Student_Name, Student_Mail, Student_Password, Student_PhoneNo, Gender, Residential_ID)
                    VALUES 
                        ('$newIdEsc', '$nameEsc', '$emailEsc', '$password', '$phoneEsc', '$gender', '01')";

            if (mysqli_query($conn, $sql)) {
                // Set session for the new user
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

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM</title>
    <link rel="stylesheet" href="block.css" type="text/css">
    <link rel="stylesheet" href="mobile.css" type="text/css">
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
                <div class="nameGenderRow">
                    <input type="text" name="name" placeholder="Name" required>
                    <select name="gender" required>
                        <option value="" disabled selected>Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <input type="email" name="email" placeholder="Student Email" required>
                <input type="text" name="phone" placeholder="Phone Number" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>

                <button type="submit" name="submit_signin">Sign Up</button>
            </form>
        </div>
    </div>
</body>
</html>