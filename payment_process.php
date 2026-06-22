<?php

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "vaultemdb"
);

if ($conn->connect_error)
{
    die("Connection failed: " . $conn->connect_error);
}

$method = $_POST['payment_method'];

$amount = $_POST['amount'];

$bookingID = $_POST['booking_id'];

$status = "P";

$date = date("Y-m-d");

$stmt = $conn->prepare ("INSERT INTO payment
(
    Payment_Method,
    Payment_Status,
    Payment_Date,
    Amount,
    Booking_ID
)

VALUES

(?, ?, ?, ?, ?)");

$stmt->bind_param("sssdi",$method,$status,$date,$amount,$bookingID);

if ($stmt->execute())
    {
        ?>
        <script>
            alert("Payment Succesful");
            window.location.href = "main.Status.html";
        </script>
        <?php
    }
    else
        {
            echo "Error: " . $stmt->error;
        }

$stmt->close();
$conn->close();

?>