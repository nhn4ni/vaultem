<?php
session_start();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();`
}

// Check if required fields exist
if (!isset($_POST['userEmail']) || !isset($_POST['userPassword'])) {
    echo "Please fill in all fields.";
    echo "<meta http-equiv='refresh' content='3;URL=login1.html'>";
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "utem_accommodation";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

$email = $_POST['userEmail'];
$password = $_POST['userPassword'];

// Prepare statement to check user existence in student table
$sql = "SELECT * FROM student WHERE Student_Mail = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Check if user is a student
if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    // Verify password
    if ($password == $user['Student_Password']) {
        // Set session variables
        $_SESSION['role'] = 'student';
        $_SESSION['User_ID'] = $user['Student_ID'];
        $_SESSION['User_Name'] = $user['Student_Name'];
        $_SESSION['Email'] = $email;
        // Redirect to main.php
        header("Location: main.php");
        exit();
    } else {
        echo "Login Fail: Wrong password<br>";
        echo "<meta http-equiv='refresh' content='5;URL=login.html'>";
        exit();
    }
} else {
    // Not a student, check if staff
    // You can add similar logic here for staff if you have a staff table
    // For example:
    $sql_staff = "SELECT * FROM staff WHERE Staff_Email = ?";
    $stmt_staff = $conn->prepare($sql_staff);
    if (!$stmt_staff) {
        die("SQL Error: " . $conn->error);
    }
    $stmt_staff->bind_param("s", $email);
    $stmt_staff->execute();
    $result_staff = $stmt_staff->get_result();

    if ($result_staff->num_rows == 1) {
        $user_staff = $result_staff->fetch_assoc();
        // Verify password for staff
        if ($password == $user_staff['Staff_Password']) {
            // Set session variables
            $_SESSION['role'] = 'staff';
            $_SESSION['User_ID'] = $user_staff['Staff_ID'];
            $_SESSION['User_Name'] = $user_staff['Staff_Name'];
            $_SESSION['Email'] = $email;
            // Redirect to main.php
            header("Location: main.php");
            exit();
        } else {
            echo "Login Fail: Wrong password<br>";
            echo "<meta http-equiv='refresh' content='5;URL=login.html'>";
            exit();
        }
    } else {
        // If not found in either table, check email domain for automatic role assignment
        if (strpos($email, '@student') !== false) {
            echo "Email recognized as student but not found in database.";
        } elseif (strpos($email, '@staff') !== false) {
            echo "Email recognized as staff but not found in database.";
        } else {
            echo "Email domain not recognized. Please contact admin.";
        }
        echo "<meta http-equiv='refresh' content='5;URL=login.html'>";
        exit();
    }
    $stmt_staff->close();
}

$stmt->close();
$conn->close();
?>