<?php
$host = 'localhost';
$username = 'root'; 
$password = '';
$database = 'vaultemdb';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $dropOffDate = $_POST['dropOffDate'] ?? '';
    $pickupDate = $_POST['pickupDate'] ?? '';

    // Quantities for items
    $bigBagQty = (int)($_POST['bigBagQty'] ?? 0);
    $medBagQty = (int)($_POST['medBagQty'] ?? 0);
    $smallBagQty = (int)($_POST['smallBagQty'] ?? 0);
    $largeLugQty = (int)($_POST['largeLugQty'] ?? 0);
    $medLugQty = (int)($_POST['medLugQty'] ?? 0);
    $smallLugQty = (int)($_POST['smallLugQty'] ?? 0);
    $bigBoxQty = (int)($_POST['bigBoxQty'] ?? 0);
    $medBoxQty = (int)($_POST['medBoxQty'] ?? 0);
    $smallBoxQty = (int)($_POST['smallBoxQty'] ?? 0);
    $bucketQty = (int)($_POST['bucketQty'] ?? 0);
    $otherQty = (int)($_POST['otherQty'] ?? 0);

    // Calculate total number of items
    $totalItems = $bigBagQty + $medBagQty + $smallBagQty + $largeLugQty + $medLugQty + $smallLugQty + $bigBoxQty + $medBoxQty + $smallBoxQty + $bucketQty + $otherQty;

    // Calculate total fee based on total items
    $totalPrice = $totalItems * 0.5;

    // Insert into booking
    mysqli_query($conn, "INSERT INTO booking (Booking_Date, DropOff_Date, Pickup_Date, Booking_Status, Booking_Priority, Staff_ID, Student_ID) VALUES (NOW(), '$dropOffDate', '$pickupDate', 'Pending', 'Normal', 1, 1)");
    $booking_id = mysqli_insert_id($conn);

    // Insert into payment
    mysqli_query($conn, "INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID) VALUES ('Online', 'Pending', NOW(), $totalPrice, $booking_id)");

    // Insert into residential_college (example values)
    mysqli_query($conn, "INSERT INTO residential_college (Residential_Block, Gender_Type) VALUES ('Satria Jebat', 'Male')");
    $residential_id = mysqli_insert_id($conn);

    // Insert into student (example values)
    mysqli_query($conn, "INSERT INTO student (Student_Name, Student_Mail, Student_Password, Student_PhoneNo, Residential_ID) VALUES ('salwasuhaimi', 'salwasuhaimi@example.com', 'babuji123', '0123456789', $residential_id)");
    $student_id = mysqli_insert_id($conn);

    // Insert items based on quantities
    $items = [
        ['name' => 'Big Bag', 'size' => $bigBagQty],
        ['name' => 'Medium Bag', 'size' => $medBagQty],
        ['name' => 'Small Bag', 'size' => $smallBagQty],
        ['name' => 'Large Luggage', 'size' => $largeLugQty],
        ['name' => 'Medium Luggage', 'size' => $medLugQty],
        ['name' => 'Small Luggage', 'size' => $smallLugQty],
        ['name' => 'Big Box', 'size' => $bigBoxQty],
        ['name' => 'Medium Box', 'size' => $medBoxQty],
        ['name' => 'Small Box', 'size' => $smallBoxQty],
    ];

    foreach ($items as $item) {
        if ($item['size'] > 0) {
            mysqli_query($conn, "INSERT INTO item (Item_Name, Item_Category, Item_Size, Quantity, Price, Space_ID, Booking_ID) VALUES ('" . $item['name'] . "', 'Bag', 'Size', " . $item['size'] . ", 0.5, 1, $booking_id)");
        }
    }

    // Insert into storespace for buckets and other items
    if ($bucketQty > 0) {
        mysqli_query($conn, "INSERT INTO storespace (Space_ID, Residential_ID, Size, Status, Booking_ID) VALUES (1, $residential_id, $bucketQty, 'Occupied', $booking_id)");
    }
    if ($otherQty > 0) {
        mysqli_query($conn, "INSERT INTO storespace (Space_ID, Residential_ID, Size, Status, Booking_ID) VALUES (2, $residential_id, $otherQty, 'Occupied', $booking_id)");
    }

    // Redirect or display success message
    echo "<h2>Booking Successful!</h2>";
    echo "<a href='form.html'>Back to form</a>";

} else {
    // Redirect to form if accessed directly
    header('Location: form.html');
    exit;
}

mysqli_close($conn);
?>
