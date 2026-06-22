<?php
// Start the session at the very top if you are using $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vaultemdb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
 
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

// 1. Check if the form was actually submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 2. Safely grab POST data or default to an empty string to prevent warnings
    $email = $_POST['userEmail'] ?? '';
    $password = $_POST['userPassword'] ?? '';

    $sql = "SELECT * FROM student WHERE Student_Mail='$email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Guna password_verify untuk semak kata laluan
        if ($password == $user['Student_Password']) {
            
            // 3. Use ?? fallback in case any column names are slightly off or null
            $_SESSION['Student_ID'] = $user['Student_ID'] ?? null;
            $_SESSION['Student_Name'] = $user['Student_Name'] ?? null;
            $_SESSION['Residential_ID'] = $user['Residential_ID'] ?? null;
            
            include("mainStatus.html");
        } else {
            echo "Login Fail: Wrong password";
            echo "<meta http-equiv='refresh' content='3;URL=login.html'>";
        }
    } else {
        echo "Login Fail: Username not found";
        echo "<meta http-equiv='refresh' content='3;URL=login.html'>";
    }
} else {
    // If someone tries to access this script directly without posting form data
    header("Location: login.html");
    exit();
}
?>