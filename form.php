<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'utem_accommodation';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 1. Get correct student ID from session (matching login.php)
$student_id = $_SESSION['User_ID'] ?? 'S001'; 
$studentIdEsc = mysqli_real_escape_string($conn, $student_id);

// 2. Fetch the logged-in student's gender from the database
$genderQuery = mysqli_query($conn, "SELECT Student_Gender FROM student WHERE Student_ID = '$studentIdEsc'");
$studentGender = 'M'; // Default fallback

if ($genderQuery && mysqli_num_rows($genderQuery) > 0) {
    $genderRow = mysqli_fetch_assoc($genderQuery);
    $studentGender = $genderRow['Student_Gender']; // Will be 'M' or 'F'
}

// 3. Fetch only colleges matching this gender from the database
// Assumes your residential_college table has a 'Gender' column (e.g., 'M', 'F', or 'Mixed')
$collegeQuery = "SELECT * FROM residential_college WHERE Gender = '$studentGender' OR Gender = 'Mixed'";
$collegeResult = mysqli_query($conn, $collegeQuery);


// ==========================================
// PROCESS BOOKING SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get student ID from session or use default
    $student_id = $_SESSION['student_id'] ?? 'S001';
    
    // Get form data
    $residentialCollege = $_POST['residentialCollege'] ?? '';
    $dropOffDate = $_POST['dropOffDate'] ?? '';
    $pickupDate = $_POST['pickupDate'] ?? '';
    $bookingPriority = isset($_POST['emergency']) ? 'Y' : 'N';
    $paymentStatus = 'N';
    
    // Get item quantities
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
    
    // Calculate total items and price
    $totalItems = $bigBagQty + $medBagQty + $smallBagQty + $largeLugQty + $medLugQty
                + $smallLugQty + $bigBoxQty + $medBoxQty + $smallBoxQty + $bucketQty + $otherQty;
    
    if ($totalItems <= 0) {
        die("No items were submitted for this booking.");
    }
    
    $totalPrice = $totalItems * 0.5;
    if ($bookingPriority === 'Y') {
        $totalPrice += 10;
    }
    
    // Escape strings for SQL
    $studentIdEsc = mysqli_real_escape_string($conn, $student_id);
    $residentialCollegeEsc = mysqli_real_escape_string($conn, $residentialCollege);
    $dropOffDateEsc = mysqli_real_escape_string($conn, $dropOffDate);
    $pickupDateEsc = mysqli_real_escape_string($conn, $pickupDate);
    
    // Get student's residential ID
    $studentCheck = mysqli_query($conn, "SELECT Residential_ID FROM student WHERE Student_ID = '$studentIdEsc'");
    if (!$studentCheck || mysqli_num_rows($studentCheck) === 0) {
        die("No student record found. Please log in again.");
    }
    $studentRow = mysqli_fetch_assoc($studentCheck);
    $residential_id = $studentRow['Residential_ID'];
    
    // Get college ID
    $collegeResult = mysqli_query($conn, "SELECT Residential_ID FROM residential_college WHERE Residential_Block = '$residentialCollegeEsc'");
    if ($collegeResult && mysqli_num_rows($collegeResult) > 0) {
        $collegeRow = mysqli_fetch_assoc($collegeResult);
        $residential_id = $collegeRow['Residential_ID'];
    }
    
    // Get storage space
    $spaceResult = mysqli_query($conn, "SELECT Space_ID FROM storespace WHERE Residential_ID = $residential_id LIMIT 1");
    if (!$spaceResult || mysqli_num_rows($spaceResult) === 0) {
        die("No storage space available for this residential college.");
    }
    $spaceRow = mysqli_fetch_assoc($spaceResult);
    $space_id = $spaceRow['Space_ID'];
    
    $staff_id = 1;
    
    // Insert booking
    $sql_booking = "INSERT INTO booking (Booking_Date, DropOff_Date, Pickup_Date, Booking_Status, Booking_Priority, Staff_ID, Student_ID)
                    VALUES (CURDATE(), '$dropOffDateEsc', '$pickupDateEsc', 'Pending', '$bookingPriority', $staff_id, '$studentIdEsc')";
    if (!mysqli_query($conn, $sql_booking)) {
        die("Booking insert failed: " . mysqli_error($conn));
    }
    $booking_id = mysqli_insert_id($conn);
    
    // Insert payment
    mysqli_query($conn, "INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID)
                         VALUES ('Online', '$paymentStatus', CURDATE(), $totalPrice, $booking_id)");
    
    // Insert items
    $items = [
        ['name' => 'Big Bag', 'size' => 'L', 'qty' => $bigBagQty],
        ['name' => 'Medium Bag', 'size' => 'M', 'qty' => $medBagQty],
        ['name' => 'Small Bag', 'size' => 'S', 'qty' => $smallBagQty],
        ['name' => 'Large Luggage', 'size' => 'L', 'qty' => $largeLugQty],
        ['name' => 'Medium Luggage', 'size' => 'M', 'qty' => $medLugQty],
        ['name' => 'Small Luggage', 'size' => 'S', 'qty' => $smallLugQty],
        ['name' => 'Big Box', 'size' => 'L', 'qty' => $bigBoxQty],
        ['name' => 'Medium Box', 'size' => 'M', 'qty' => $medBoxQty],
        ['name' => 'Small Box', 'size' => 'S', 'qty' => $smallBoxQty],
        ['name' => 'Bucket/Pail', 'size' => 'M', 'qty' => $bucketQty],
        ['name' => 'Other', 'size' => 'M', 'qty' => $otherQty],
    ];
    
    foreach ($items as $item) {
        if ($item['qty'] > 0) {
            $itemNameEsc = mysqli_real_escape_string($conn, $item['name']);
            $itemSizeEsc = mysqli_real_escape_string($conn, $item['size']);
            mysqli_query($conn, "INSERT INTO item (Item_Name, Item_Category, Item_Size, Quantity, Price, Space_ID, Booking_ID)
                                 VALUES ('$itemNameEsc', 'Storage', '$itemSizeEsc', " . $item['qty'] . ", 0.50, $space_id, $booking_id)");
        }
    }
    
    mysqli_close($conn);
    
    // Redirect to success page
    echo "<h2>Booking Successful!</h2>";
    echo "<p>Your booking has been confirmed. Redirecting to payment...</p>";
    echo "<meta http-equiv='refresh' content='3;URL=payment.php'>";
    exit();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Booking Form</title>
    <link rel="stylesheet" href="mobile.css" type="text/css">
    <style>
        * {
            font-family: 'Courier New', Courier, monospace;
            box-sizing: border-box;
        }

        body {
            background-color: #E8E9DE;
            margin: 0;
            padding-bottom: 140px;
        }

        .back {
            border-style: none;
            background-color: #E8E9DE;
            font-size: 1.2rem;
            font-weight: bold;
            color: #241253;
            margin: 15px;
            padding-bottom: 20px;
            cursor: pointer;
            position: relative;
            z-index: 10;
        }

        .leftContainer {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0 40px;
            gap: 60px;
            flex-wrap: wrap;
        }

        #formLayout {
            color: #241253;
            flex: 1;
            min-width: 500px;
        }

        h1 {
            margin-top: 0;
        }

        h2 {
            color: #241253;
            font-size: 1.2rem;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .collegeGrid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .collegeCard {
            background-color: #241253;
            color: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            min-height: 140px;
            transition: 0.2s;
        }

        .collegeCard h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: normal;
        }

        .collegeCard p {
            margin: 10px 0;
            font-size: 0.9rem;
            color: #d1cbdc;
        }

        .selectBtn {
            background-color: #f1f0ea;
            color: #241253;
            border: none;
            border-radius: 15px;
            padding: 6px 20px;
            font-weight: bold;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .selectBtn:hover {
            transform: scale(1.05);
            background-color: #e8e6df;
        }

        .selectCollege {
            background-color: #4CAF50;
            color: white;
        }

        .selectCollege:hover {
            background-color: #2e7d32;
        }

        .dateContainer {
            display: flex;
            gap: 40px;
            align-items: flex-start;
            margin-top: 20px;
        }

        .dateSection {
            flex: 1;
        }

        .dropOffRow, .pickupRow {
            display: flex;
            gap: 12px;
        }

        .selectWrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .dropOffRow select, .pickupRow select {
            background-color: #241253;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 10px 20px;
            font-size: 1rem;
            cursor: pointer;
            min-width: 80px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg fill='white' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 35px;
        }

        .selectWrapper span {
            color: #7a6e93;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        #itemDetailsContainer {
            flex: 1;
            min-width: 350px;
            margin-top: 70px;
            color: #241253;
            margin-bottom: 100px;
            margin-left: 50px;
        }

        .detailsHeader {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .itemRow {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .itemLeft {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 70%;
        }

        .chooseBtn {
            background-color: #241253;
            border: none;
            border-radius: 20px;
            color: #f1f0ea;
            font-size: 1rem;
            cursor: pointer;
            padding: 6px 16px;
        }

        .bagDropdown {
            background-color: #241253;
            border-radius: 20px;
            padding: 15px 20px;
            width: 340px;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            transform: translateY(-10px);
        }

        .bagDropdown.show {
            max-height: 500px;
            opacity: 1;
            transform: translateY(0);
        }

        .bagOption {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            margin-bottom: 15px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .bagOption:last-child {
            margin-bottom: 0;
        }

        .bagOption input[type="number"] {
            width: 70px;
            border: none;
            border-radius: 15px;
            padding: 6px 10px;
            text-align: center;
            background-color: #E8E9DE;
            color: #241253;
        }

        .bagOption label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .bagOption input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        #bucketInput {
            border-radius: 20px;
            border-style: none;
            border: 1.5px solid #241253;
            width: 100px;
            padding: 10px;
            background-color: #ffffff;
            color: #241253;
            text-align: center;
        }

        #otherQty {
            border-radius: 20px;
            border-style: none;
            border: 1.5px solid #241253;
            width: 100px;
            padding: 10px;
            background-color: #ffffff;
            color: #241253;
            text-align: center;
        }

        .feeFooter {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #241253;
            color: white;
            border-top-left-radius: 28px;
            border-top-right-radius: 28px;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
            flex-wrap: wrap;
            gap: 15px;
        }

        .feeText {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .priceAmt {
            margin-left: 10px;
        }

        .submitBtn {
            background-color: #E8E9DE;
            color: #241253;
            border: none;
            border-radius: 20px;
            padding: 10px 35px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
        }

        .submitBtn:hover {
            transform: scale(1.02);
            background-color: #d8dacd;
        }

        .emergencySection {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 40px;
        }

        .emergencySection label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: normal;
            font-size: 0.95rem;
        }

        .emergencySection input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .hidden-input {
            display: none;
        }

        @media (max-width: 768px) {
            .leftContainer {
                flex-direction: column;
                padding: 0 20px;
            }
            
            #formLayout, #itemDetailsContainer {
                min-width: unset;
                width: 100%;
                margin-left: 0;
            }
            
            .collegeGrid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .feeFooter {
                flex-direction: column;
                padding: 15px 20px;
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <button class="back" onclick="history.back()">&#60; Back</button>

    <form id="bookingForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return submitBooking()">

        <div class="leftContainer">
            
            <div id="formLayout">

                <h1>Booking Form</h1>
                <h3>Residential College</h3>

                <div class="collegeGrid">

                    <div class="collegeCard">
                        <h3>Satria Jebat</h3>
                        <p>Space :<span id="jebatSize"></span> unit</p>
                        <button type="button" class="selectBtn" onclick="selectCollege(this, 'Satria Jebat')">Select</button>
                    </div>

                    <div class="collegeCard">
                        <h3>Satria Tuah</h3>
                        <p>Space : <span id="tuahSize"></span> unit</p>
                        <button type="button" class="selectBtn" onclick="selectCollege(this, 'Satria Tuah')">Select</button>
                    </div>

                    <div class="collegeCard">
                        <h3>Satria Kasturi</h3>
                        <p>Space : <span id="kasturiSize"></span> unit</p>
                        <button type="button" class="selectBtn" onclick="selectCollege(this, 'Satria Kasturi')">Select</button>
                    </div>

                    <div class="collegeCard">
                        <h3>Satria Lekir</h3>
                        <p>Space : <span id="lekirSize"></span> unit</p>
                        <button type="button" class="selectBtn" onclick="selectCollege(this, 'Satria Lekir')">Select</button>
                    </div>

                    <div class="collegeCard">
                        <h3>Satria Lekiu</h3>
                        <p>Space : <span id="lekiuSize"></span> unit</p>
                        <button type="button" class="selectBtn" onclick="selectCollege(this, 'Satria Lekiu')">Select</button>
                    </div>

                    <div class="collegeCard">
                        <h3>Lestari A1 / A2</h3>
                        <p>Space : <span id="lestariSize"></span> unit</p>
                        <button type="button" class="selectBtn" onclick="selectCollege(this, 'Lestari A1 / A2')">Select</button>
                    </div>  

                </div>
                
                <!-- Hidden input for residential college -->
                <input type="hidden" id="residentialCollege" name="residentialCollege" value="">
                
                <div class="dateContainer">
                    <div class="dateSection">

                        <h2>Drop-off <span id="currentYear"></span></h2>

                        <div class="dropOffRow">
                            <div class="selectWrapper">
                                <select id="dropOffDay" name="dropOffDay">
                                </select>
                                <span>Day of month</span>
                            </div>

                            <div class="selectWrapper">
                                <select id="dropOffMonth" name="dropOffMonth">
                                    <option>January</option>
                                    <option>February</option>
                                    <option>March</option>
                                    <option>April</option>
                                    <option>May</option>
                                    <option>June</option>
                                    <option>July</option>
                                    <option>August</option>
                                    <option>September</option>
                                    <option>October</option>
                                    <option>November</option>
                                    <option>December</option>
                                </select>
                                <span>month</span>
                            </div>
                        </div>

                    </div>

                    <div class="dateSection">

                        <h2>Pickup date</h2>

                        <div class="pickupRow">
                            <div class="selectWrapper">
                                <select id="pickupDay" name="pickupDay">
                                </select>
                                <span>Day of month</span>
                            </div>

                            <div class="selectWrapper">
                                <select id="pickupMonth" name="pickupMonth">
                                    <option>January</option>
                                    <option>February</option>
                                    <option>March</option>
                                    <option>April</option>
                                    <option>May</option>
                                    <option>June</option>
                                    <option>July</option>
                                    <option>August</option>
                                    <option>September</option>
                                    <option>October</option>
                                    <option>November</option>
                                    <option>December</option>
                                </select>
                                <span>month</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="itemDetailsContainer">
                <div class="detailsHeader"><span>Item details</span><span>Quantity / Options</span></div>

                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Bag</span>
                        <div class="bagDropdown" id="bagDropdown">
                            <div class="bagOption">
                                <span>Big Bag </span>
                                <input type="number" id="bigBagQty" name="bigBagQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileBag" data-item="Big Bag"> Fragile?</label>
                            </div>
                            <div class="bagOption">
                                <span>Medium Bag </span>
                                <input type="number" id="medBagQty" name="medBagQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileBag" data-item="Medium Bag"> Fragile?</label>
                            </div>
                            <div class="bagOption">
                                <span>Small Bag</span>
                                <input type="number" id="smallBagQty" name="smallBagQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileBag" data-item="Small Bag"> Fragile?</label>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="bagDropdown">Choose &#11206;</button>
                </div>

                <!-- LUGGAGE SECTION -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Luggage</span>
                        <div class="bagDropdown" id="luggageDropdown">
                            <div class="bagOption">
                                <span>Large Luggage </span>
                                <input type="number" id="largeLugQty" name="largeLugQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileLuggage" data-item="Large Luggage"> Fragile?</label>
                            </div>
                            <div class="bagOption">
                                <span>Medium Luggage </span>
                                <input type="number" id="medLugQty" name="medLugQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileLuggage" data-item="Medium Luggage"> Fragile?</label>
                            </div>
                            <div class="bagOption">
                                <span>Small Luggage </span>
                                <input type="number" id="smallLugQty" name="smallLugQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileLuggage" data-item="Small Luggage"> Fragile?</label>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="luggageDropdown">Choose &#11206;</button>
                </div>

                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Box</span>
                        <div class="bagDropdown" id="boxDropdown">
                            <div class="bagOption">
                                <span>Big Box </span>
                                <input type="number" id="bigBoxQty" name="bigBoxQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileBox" data-item="Big Box"> Fragile?</label>
                            </div>
                            <div class="bagOption">
                                <span>Medium Box </span>
                                <input type="number" id="medBoxQty" name="medBoxQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileBox" data-item="Medium Box"> Fragile?</label>
                            </div>
                            <div class="bagOption">
                                <span>Small Box </span>
                                <input type="number" id="smallBoxQty" name="smallBoxQty" value="0" min="0" oninput="calculateTotal()">
                                <label><input type="checkbox" class="fragileBox" data-item="Small Box"> Fragile?</label>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="boxDropdown">Choose &#11206;</button>
                </div>

                <div class="itemRow">
                    <span>Bucket/Pail</span>
                    <input id="bucketInput" type="number" name="bucketQty" min="0" value="0" oninput="calculateTotal()">
                </div>
                <div class="itemRow">
                    <span>Others</span>
                    <input id="otherQty" type="number" name="otherQty" min="0" value="0" oninput="calculateTotal()">
                </div>

                <!-- Hidden fields for date values -->
                <input type="hidden" id="dropOffDate" name="dropOffDate" value="">
                <input type="hidden" id="pickupDate" name="pickupDate" value="">
                <input type="hidden" id="emergency" name="emergency" value="">

            </div>

        </div>

        <div class="feeFooter">

            <div class="feeText">
                Total Fee RM <span class="priceAmt" id="totalPrice">0.00</span>
            </div>
            
            <div class="emergencySection">
                <label>
                    <input type="checkbox" id="emergencyCheckbox" onchange="calculateTotal()">
                    <span> Emergency</span>
                </label>
            </div>

            <button type="submit" class="submitBtn">Submit</button>
            
        </div>
    </form>

    <script>
        // ==========================================
        // COLLEGE SELECTION AND SPACE MANAGEMENT
        // ==========================================
        const jebat = 150;
        const tuah = 150;
        const kasturi = 150;
        const lestari = 150;
        const lekir = 150;
        const lekiu = 150;
    
        let jebatSpace = jebat;
        let tuahSpace = tuah;
        let kasturiSpace = kasturi;
        let lestariSpace = lestari;
        let lekirSpace = lekir;
        let lekiuSpace = lekiu;
    
        let selectedCollegeName = "";
    
        function updateCollegeSpaces() {
            document.getElementById('jebatSize').innerHTML = jebatSpace;
            document.getElementById('tuahSize').innerHTML = tuahSpace;
            document.getElementById('kasturiSize').innerHTML = kasturiSpace;
            document.getElementById('lekirSize').innerHTML = lekirSpace;
            document.getElementById('lekiuSize').innerHTML = lekiuSpace;
            document.getElementById('lestariSize').innerHTML = lestariSpace;
        }
        
        function getCollegeSpace(collegeName) {
            if(collegeName === 'Satria Jebat') return jebatSpace;
            if(collegeName === 'Satria Tuah') return tuahSpace;
            if(collegeName === 'Satria Kasturi') return kasturiSpace;
            if(collegeName === 'Satria Lekir') return lekirSpace;
            if(collegeName === 'Satria Lekiu') return lekiuSpace;
            if(collegeName === 'Lestari A1 / A2') return lestariSpace;
            return 0;
        }
        
        function reduceCollegeSpace(collegeName, units) {
            if(collegeName === 'Satria Jebat') jebatSpace = jebatSpace - units;
            if(collegeName === 'Satria Tuah') tuahSpace = tuahSpace - units;
            if(collegeName === 'Satria Kasturi') kasturiSpace = kasturiSpace - units;
            if(collegeName === 'Satria Lekir') lekirSpace = lekirSpace - units;
            if(collegeName === 'Satria Lekiu') lekiuSpace = lekiuSpace - units;
            if(collegeName === 'Lestari A1 / A2') lestariSpace = lestariSpace - units;
            updateCollegeSpaces();
            saveSpacesToLocal();
        }
        
        function saveSpacesToLocal() {
            localStorage.setItem('jebatSpace', jebatSpace);
            localStorage.setItem('tuahSpace', tuahSpace);
            localStorage.setItem('kasturiSpace', kasturiSpace);
            localStorage.setItem('lekirSpace', lekirSpace);
            localStorage.setItem('lekiuSpace', lekiuSpace);
            localStorage.setItem('lestariSpace', lestariSpace);
        }
    
        function loadSpacesFromLocal() {
            if(localStorage.getItem('jebatSpace') !== null) jebatSpace = parseInt(localStorage.getItem('jebatSpace'));
            if(localStorage.getItem('tuahSpace') !== null) tuahSpace = parseInt(localStorage.getItem('tuahSpace'));
            if(localStorage.getItem('kasturiSpace') !== null) kasturiSpace = parseInt(localStorage.getItem('kasturiSpace'));
            if(localStorage.getItem('lekirSpace') !== null) lekirSpace = parseInt(localStorage.getItem('lekirSpace'));
            if(localStorage.getItem('lekiuSpace') !== null) lekiuSpace = parseInt(localStorage.getItem('lekiuSpace'));
            if(localStorage.getItem('lestariSpace') !== null) lestariSpace = parseInt(localStorage.getItem('lestariSpace'));
            updateCollegeSpaces();
        }
    
        function selectCollege(button, collegeName) {
            let allButtons = document.querySelectorAll('.selectBtn');
            for(let i = 0; i < allButtons.length; i++) {
                allButtons[i].textContent = "Select";
                allButtons[i].classList.remove('selectCollege');
            }

            button.textContent = "Selected";
            button.classList.add('selectCollege');
            selectedCollegeName = collegeName;
            
            // Set hidden input value
            document.getElementById('residentialCollege').value = collegeName;
        }

        // ==========================================
        // DATE HANDLING
        // ==========================================
        const currentYear = new Date().getFullYear();
        document.getElementById('currentYear').innerHTML = currentYear;
    
        const dropOffDay = document.getElementById('dropOffDay');
        const dropOffMonth = document.getElementById('dropOffMonth');
        const pickupDay = document.getElementById('pickupDay');
        const pickupMonth = document.getElementById('pickupMonth');
    
        function getDaysInMonth(monthIndex) {
            return new Date(currentYear, monthIndex + 1, 0).getDate();
        }
    
        function fillDays(selectElement, monthIndex) {
            let days = getDaysInMonth(monthIndex);
            selectElement.innerHTML = '';
            for(let i = 1; i <= days; i++) {
                let option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                selectElement.appendChild(option);
            }
        }
    
        function setupDates() {
            let currentMonth = new Date().getMonth();
            fillDays(dropOffDay, currentMonth);
            fillDays(pickupDay, currentMonth);
            dropOffMonth.selectedIndex = currentMonth;
            pickupMonth.selectedIndex = currentMonth;
        }
    
        dropOffMonth.addEventListener('change', function() {
            fillDays(dropOffDay, dropOffMonth.selectedIndex);
        });
    
        pickupMonth.addEventListener('change', function() {
            fillDays(pickupDay, pickupMonth.selectedIndex);
        });
    
        setupDates();
    
        function getFullDate(daySelect, monthSelect) {
            let year = currentYear;
            let month = monthSelect.selectedIndex;
            let day = parseInt(daySelect.value);
            return new Date(year, month, day);
        }

        // ==========================================
        // PRICE CALCULATION
        // ==========================================
        function calculateTotal() {
            let total = 0;
            let totalUnits = 0;
            
            let bigBag = parseInt(document.getElementById('bigBagQty').value) || 0;
            let medBag = parseInt(document.getElementById('medBagQty').value) || 0;
            let smallBag = parseInt(document.getElementById('smallBagQty').value) || 0;
            let largeLug = parseInt(document.getElementById('largeLugQty').value) || 0;
            let medLug = parseInt(document.getElementById('medLugQty').value) || 0;
            let smallLug = parseInt(document.getElementById('smallLugQty').value) || 0;
            let bigBox = parseInt(document.getElementById('bigBoxQty').value) || 0;
            let medBox = parseInt(document.getElementById('medBoxQty').value) || 0;
            let smallBox = parseInt(document.getElementById('smallBoxQty').value) || 0;
            let bucket = parseInt(document.getElementById('bucketInput').value) || 0;
            let other = parseInt(document.getElementById('otherQty').value) || 0;
            
            total = (bigBag * 0.5) + (medBag * 0.5) + (smallBag * 0.5);
            total = total + (largeLug * 0.5) + (medLug * 0.5) + (smallLug * 0.5);
            total = total + (bigBox * 0.5) + (medBox * 0.5) + (smallBox * 0.5);
            total = total + (bucket * 0.5);
            total = total + (other * 0.5);
            
            totalUnits = bigBag + medBag + smallBag + largeLug + medLug + smallLug + bigBox + medBox + smallBox + bucket + other;
            
            // Add emergency fee if checked
            if(document.getElementById('emergencyCheckbox').checked) {
                total += 10;
            }
            
            let priceElement = document.getElementById('totalPrice');
            if(priceElement) {
                priceElement.innerHTML = total.toFixed(2);
            }
            return { total: total, totalUnits: totalUnits };
        }

        // ==========================================
        // DROPDOWN TOGGLES
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            let dropdownButtons = document.querySelectorAll('.chooseBtn');
            for(let i = 0; i < dropdownButtons.length; i++) {
                dropdownButtons[i].addEventListener('click', function(e) {
                    e.stopPropagation();
                    let targetId = this.getAttribute('data-target');
                    let targetDiv = document.getElementById(targetId);
                    if(targetDiv) {
                        targetDiv.classList.toggle('show');
                    }
                });
            }
            
            // Load saved spaces
            loadSpacesFromLocal();
            
            // Initial price calculation
            calculateTotal();
        });

        // ==========================================
        // FORM SUBMISSION
        // ==========================================
        function submitBooking() {
            // Get selected college
            if(selectedCollegeName === "") {
                alert("Please select a residential college.");
                return false;
            }
            
            // Get dates
            let dropDate = getFullDate(dropOffDay, dropOffMonth);
            let pickDate = getFullDate(pickupDay, pickupMonth);
            
            // Validate dates
            if(pickDate <= dropDate) {
                alert("Pick-up date must be after drop-off date.");
                return false;
            }
            
            // Check if items are selected
            let calc = calculateTotal();
            let totalUnitsNeeded = calc.totalUnits;
            
            if(totalUnitsNeeded <= 0) {
                alert("Please add at least one item to book.");
                return false;
            }
            
            // Check available space
            let availableSpace = getCollegeSpace(selectedCollegeName);
            if(availableSpace < totalUnitsNeeded) {
                alert("Not enough storage space at " + selectedCollegeName + ". Only " + availableSpace + " units left. Your booking needs " + totalUnitsNeeded + " units. Please reduce items or choose another college.");
                return false;
            }
            
            // Reduce space
            reduceCollegeSpace(selectedCollegeName, totalUnitsNeeded);
            
            // Set hidden date fields
            let year = currentYear;
            let month = dropOffMonth.selectedIndex + 1;
            let day = dropOffDay.value;
            document.getElementById('dropOffDate').value = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            
            month = pickupMonth.selectedIndex + 1;
            day = pickupDay.value;
            document.getElementById('pickupDate').value = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            
            // Set emergency
            if(document.getElementById('emergencyCheckbox').checked) {
                document.getElementById('emergency').value = 'on';
                alert("Emergency booking will be highlighted for staff priority processing.");
            }
            
            // Show confirmation
            let totalPrice = document.getElementById('totalPrice').innerHTML;
            if(confirm("Confirm booking with " + totalUnitsNeeded + " items? Total cost: RM " + totalPrice)) {
                return true;
            }
            
            return false;
        }
    </script>

</body>
</html>