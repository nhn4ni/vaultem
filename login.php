<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vaultemdb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname );
 
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

$email=$_POST['userEmail'];
$password=$_POST['userPassword'];
$sql = "SELECT * FROM student WHERE Student_Mail='$email'";
    $result = $conn->query(query: $sql);

 if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Guna password_verify untuk semak kata laluan
        if ($password == $user['Student_Password']) {
            include("mainStatus.html");
        } else {
            echo "Login Fail: Password salah";
            echo "<meta http-equiv='refresh' content='3;URL=login.html'>";
        }
    }
     else {
        echo "Login Fail: Username tidak wujud";
        echo "<meta http-equiv='refresh' content='3;URL=login.html'>";
    }
?>

