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

$student_id = $_SESSION['Student_ID'] ?? 'S001';
$studentIdEsc = mysqli_real_escape_string($conn, $student_id);

$genderQuery = mysqli_query($conn, "SELECT Gender FROM student WHERE Student_ID = '$studentIdEsc'");
$studentGender = 'M'; 

if ($genderQuery && mysqli_num_rows($genderQuery) > 0) {
    $genderRow = mysqli_fetch_assoc($genderQuery);
    $studentGender = $genderRow['Gender']; 
} else {
    die("No student record found. Please log in again.");
}

// Updated Query: Set unit space capacity value to 150
$collegeQuery = "
    SELECT rc.Residential_ID,
           rc.Residential_Block,
           rc.Gender_Type,
           COALESCE(ss.Size, 0) AS Available_Space
    FROM residential_college rc
    LEFT JOIN storespace ss ON rc.Residential_ID = ss.Residential_ID
    WHERE rc.Gender_Type = '$studentGender'
    ";
$collegeResult = mysqli_query($conn, $collegeQuery);

if (!$collegeResult) {
    die("College query failed: " . mysqli_error($conn));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['Student_ID'] ?? 'S001';

    $residentialCollege = $_POST['residentialCollege'] ?? '';
    $dropOffDate = $_POST['dropOffDate'] ?? '';
    $pickupDate = $_POST['pickupDate'] ?? '';
    $bookingPriority = isset($_POST['emergency']) ? 'Y' : 'N';
    $paymentStatus = 'N';

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

    $totalItems = $bigBagQty + $medBagQty + $smallBagQty + $largeLugQty + $medLugQty
                + $smallLugQty + $bigBoxQty + $medBoxQty + $smallBoxQty + $bucketQty + $otherQty;

    if ($totalItems <= 0) {
        die("No items were submitted for this booking.");
    }

    if ($totalItems > 3) {
        die("Booking limit exceeded. You may only book a maximum of 3 items.");
    }

    $totalPrice =
    ($bigBagQty * 7.00) +
    ($medBagQty * 5.00) +
    ($smallBagQty * 3.00) +

    ($largeLugQty * 10.00) +
    ($medLugQty * 8.00) +
    ($smallLugQty * 6.00) +

    ($bigBoxQty * 5.00) +
    ($medBoxQty * 3.00) +
    ($smallBoxQty * 2.00) +

    ($bucketQty * 3.00) +
    ($otherQty * 5.00);

if ($bookingPriority === 'Y') {
    $totalPrice += 10.00;
}
    $studentIdEsc = mysqli_real_escape_string($conn, $student_id);
    $residentialCollegeEsc = mysqli_real_escape_string($conn, $residentialCollege);
    $dropOffDateEsc = mysqli_real_escape_string($conn, $dropOffDate);
    $pickupDateEsc = mysqli_real_escape_string($conn, $pickupDate);

    // Grab the ID integer directly from the selected card form submission
    $residential_id = (int)($_POST['residentialCollege'] ?? 0);

    if ($residential_id <= 0) {
        die("Invalid residential college selection. Please go back and select a block.");
    }

    $spaceResult = mysqli_query($conn, "SELECT Space_ID FROM storespace WHERE Residential_ID = $residential_id LIMIT 1");
    if (!$spaceResult || mysqli_num_rows($spaceResult) === 0) {
        die("No storage space available for this residential college.");
    }
    $spaceRow = mysqli_fetch_assoc($spaceResult);
    $space_id = $spaceRow['Space_ID'];

    $staff_id = 1;

    $sql_booking = "INSERT INTO booking (Booking_Date, DropOff_Date, Pickup_Date, Booking_Status, Booking_Priority, Staff_ID, Student_ID)
                    VALUES (CURDATE(), '$dropOffDateEsc', '$pickupDateEsc', 'Pending', '$bookingPriority', $staff_id, '$studentIdEsc')";
    if (!mysqli_query($conn, $sql_booking)) {
        die("Booking insert failed: " . mysqli_error($conn));
    }
    $booking_id = mysqli_insert_id($conn);

    // Deduct booked items from storespace
    $deductSpace = mysqli_prepare($conn, "UPDATE storespace SET Size = Size - ? WHERE Space_ID = ?");
    mysqli_stmt_bind_param($deductSpace, "ii", $totalItems, $space_id);
    mysqli_stmt_execute($deductSpace);
    mysqli_stmt_close($deductSpace);

    if (!mysqli_query($conn, "INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID)
                             VALUES ('Online', '$paymentStatus', CURDATE(), $totalPrice, $booking_id)")) {
        die("Payment insert failed: " . mysqli_error($conn));
    }

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

    echo "<h2>Booking Successful!</h2>";
    echo "<p>Your booking has been confirmed. Redirecting to payment...</p>";
    echo "<meta http-equiv='refresh' content='3;URL=payment.php?booking_id=" . $booking_id . "&amount=" . $totalPrice . "'>";
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
            padding: 20px 40px 160px 40px;
        }

        .leftContainer {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 60px;
            flex-wrap: wrap;
        }

        #formLayout {
            color: #241253;
            flex: 1.3;
            min-width: 500px;
        }

        h1 {
            font-size: 2.2rem;
            margin-top: 0;
            margin-bottom: 25px;
            font-weight: bold;
        }

        h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        h2 {
            color: #241253;
            font-size: 1.2rem;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .collegeGrid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .collegeCard {
            background-color: #241253;
            color: white;
            border-radius: 20px;
            padding: 20px 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            min-height: 160px;
        }

        .collegeCard h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: normal;
            line-height: 1.3;
        }

        .collegeCard p {
            margin: 10px 0;
            font-size: 0.85rem;
            color: #d1cbdc;
        }

        .selectBtn {
            background-color: #ffffff;
            color: #241253;
            border: none;
            border-radius: 25px;
            padding: 8px 25px;
            font-weight: bold;
            cursor: pointer;
            font-size: 0.9rem;
            width: 80%;
        }

        .selectBtn:disabled,
        .selectBtn.full {
            background-color: #888888 !important;
            color: #cccccc !important;
            cursor: not-allowed !important;
            transform: none !important;
        }

        .selectCollege {
            background-color: #4CAF50;
            color: white;
        }

        .dateContainer {
            display: flex;
            gap: 60px;
            align-items: flex-start;
            margin-top: 20px;
        }

        .dateSection {
            flex: 1;
        }

        .dropOffRow, .pickupRow {
            display: flex;
            gap: 15px;
        }

        .selectWrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .dropOffRow select, .pickupRow select {
            background-color: #241253;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 12px 25px;
            font-size: 1rem;
            cursor: pointer;
            min-width: 100px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg fill='white' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .selectWrapper span {
            color: #7a6e93;
            font-size: 0.75rem;
            margin-top: 6px;
        }

        #itemDetailsContainer {
            flex: 1;
            min-width: 400px;
            color: #241253;
            margin-top: 75px;
        }

        .detailsHeader {
            display: flex;
            justify-content: space-between;
            margin-bottom: 35px;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .itemRow {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            font-size: 1.1rem;
        }

        .itemLeft {
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 65%;
        }

        /* ── NEW: price tag shown beside / below each item label ── */
        .itemPrice {
            font-size: 1rem;
            color: #7a6e93;
            font-weight: normal;
        }
        
        /* price inside dark dropdown panels */
        .bagOption .itemPrice {
            color: #c9c3d8;
            font-size: 0.74rem;
            margin-left: 6px;
        }

        .chooseBtn {
            background-color: #241253;
            border: none;
            border-radius: 25px;
            color: #f1f0ea;
            font-size: 1rem;
            cursor: pointer;
            padding: 10px 25px;
            min-width: 110px;
            text-align: center;
        }

        .bagDropdown {
            background-color: #241253;
            border-radius: 20px;
            padding: 0px 20px;
            width: 100%;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .bagDropdown.show {
            padding: 15px 20px;
            max-height: 500px;
            opacity: 1;
            margin-top: 10px;
        }

        .bagOption {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            margin-bottom: 15px;
            gap: 12px;
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

        #bucketInput, #otherQty {
            border-radius: 25px;
            border: 1px solid #241253;
            width: 110px;
            padding: 10px;
            background-color: #ffffff;
            color: #241253;
            text-align: center;
            font-size: 1rem;
        }

        .feeFooter {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #241253;
            color: white;
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
            padding: 25px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
        }

        .feeText {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .priceAmt {
            margin-left: 15px;
        }

        .rightFooterSection {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .emergencySection {
            display: flex;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.12);
            padding: 10px 25px;
            border-radius: 25px;
        }

        .emergencySection label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 1.05rem;
        }

        .emergencySection input {
            width: 18px;
            height: 18px;
            cursor: pointer;
}
 .emergencyNote {
        margin-left: 10px;
        font-size: 0.9rem;
        color: #ff0000e0;
        font-weight: bold;
        }
        
        .submitBtn {
            background-color: #E8E9DE;
            color: #241253;
            border: none;
            border-radius: 25px;
            padding: 12px 45px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .leftContainer {
                flex-direction: column;
            }
            #formLayout, #itemDetailsContainer {
                width: 100%;
                min-width: unset;
            }
            .collegeGrid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <form id="bookingForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return submitBooking()">

        <div class="leftContainer">

            <div id="formLayout">
                <h1>Booking Form</h1>
                <h3>Residential College</h3>

                <div class="collegeGrid">
                    <?php
                    if ($collegeResult && mysqli_num_rows($collegeResult) > 0) {
                        while ($college = mysqli_fetch_assoc($collegeResult)) {
                            $collegeName = $college['Residential_Block'];
                            $availableSpace = $college['Available_Space'];
                            ?>
                            <div class="collegeCard" data-space="<?php echo (int)$availableSpace; ?>">
                                <h3><?php echo htmlspecialchars($collegeName); ?></h3>
                                <p>Space : <span><?php echo (int)$availableSpace; ?></span> unit</p>
                                <button type="button" class="selectBtn" onclick="selectCollege(this, '<?php echo htmlspecialchars($collegeName); ?>', <?php echo (int)$availableSpace; ?>, <?php echo $college['Residential_ID']; ?>)">Select</button>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<p style='color: red;'>No residential colleges available found for your gendergit add.</p>";
                    }
                    ?>
                </div>

                <input type="hidden" id="residentialCollege" name="residentialCollege" value="">

                <div class="dateContainer">
                    <div class="dateSection">
                        <h2>Drop-off <span id="currentYear"></span></h2>
                        <div class="dropOffRow">
                            <div class="selectWrapper">
                                <select id="dropOffDay" name="dropOffDay"></select>
                                <span>Day of month</span>
                            </div>
                            <div class="selectWrapper">
                                <select id="dropOffMonth" name="dropOffMonth"></select>
                                <span>Month</span>
                            </div>
                        </div>
                    </div>

                    <div class="dateSection">
                        <h2>Pickup date</h2>
                        <div class="pickupRow">
                            <div class="selectWrapper">
                                <select id="pickupDay" name="pickupDay"></select>
                                <span>Day of month</span>
                            </div>
                            <div class="selectWrapper">
                                <select id="pickupMonth" name="pickupMonth"></select>
                                <span>Month</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="itemDetailsContainer">
                <div class="detailsHeader">
                    <span>Item details</span>
                    <span>Quantity / Options</span>
                </div>
                <p id="itemLimitReminder" style="color: #e74c3c; font-size: 0.85rem; margin: -20px 0 20px 0;">
                     Maximum 3 items per booking.
                </p>

                <!-- BAG -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Bag</span>
                    <span class="itemPrice">Big RM7 | Medium RM5 | Small RM3</span>
                        <div class="bagDropdown" id="bagDropdown">
                            <div class="bagOption">
                                <span>Big Bag <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="bigBagQty" name="bigBagQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Medium Bag <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="medBagQty" name="medBagQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Small Bag <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="smallBagQty" name="smallBagQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="bagDropdown">Choose &#9660;</button>
                </div>

                <!-- LUGGAGE -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Luggage</span>
                        <span class="itemPrice">Large RM10 | Medium RM8 | Small RM6</span>
                        <div class="bagDropdown" id="luggageDropdown">
                            <div class="bagOption">
                                <span>Large Luggage <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="largeLugQty" name="largeLugQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit();">
                            </div>
                            <div class="bagOption">
                                <span>Medium Luggage <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="medLugQty" name="medLugQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Small Luggage <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="smallLugQty" name="smallLugQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="luggageDropdown">Choose &#9660;</button>
                </div>

                <!-- BOX -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Box</span>
                        <span class="itemPrice">RM 0.50 / item</span>
                        <div class="bagDropdown" id="boxDropdown">
                            <div class="bagOption">
                                <span>Big Box <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="bigBoxQty" name="bigBoxQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Medium Box <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="medBoxQty" name="medBoxQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Small Box <span class="itemPrice">RM 0.50</span></span>
                                <input type="number" id="smallBoxQty" name="smallBoxQty" value="0" min="0" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="boxDropdown">Choose &#9660;</button>
                </div>

                <!-- BUCKET/PAIL -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Bucket/Pail</span>
                        <span class="itemPrice">RM 0.50 / item</span>
                    </div>
                    <input id="bucketInput" type="number" name="bucketQty" min="0" value="0" oninput="calculateTotal(); checkItemLimit()">
                </div>

                <!-- OTHERS -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Others</span>
                        <span class="itemPrice">RM 0.50 / item</span>
                    </div>
                    <input id="otherQty" type="number" name="otherQty" min="0" value="0" oninput="calculateTotal(); checkItemLimit()">
                </div>

                <input type="hidden" id="dropOffDate" name="dropOffDate" value="">
                <input type="hidden" id="pickupDate" name="pickupDate" value="">
                <input type="hidden" id="emergency" name="emergency" value="">
            </div>

        </div>

        <div class="feeFooter">
            <div class="feeText">
                Total Fee RM <span class="priceAmt" id="totalPrice">0.00</span>
            </div>

            <div class="rightFooterSection">

                <div class="emergencySection">
                    <label>
                        <input type="checkbox" id="emergencyCheckbox" onchange="calculateTotal(); checkItemLimit()">
                        <span>Emergency</span>
                    </label>

                    <span class ="emergencyNote">(+ RM5 for Emergency Booking)</span>
                </div>
                <button type="submit" class="submitBtn">Submit</button>
            </div>
        </div>
    </form>

    <script>
        localStorage.removeItem('collegeSpaces');
        let collegeSpaces = {};
        let selectedCollegeName = "";

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.collegeCard').forEach(function(card) {
                let name  = card.querySelector('h3').textContent.trim();
                let space = parseInt(card.getAttribute('data-space')) || 0;
                collegeSpaces[name] = space;

                if (space <= 0) {
                    let btn = card.querySelector('.selectBtn');
                    btn.textContent = "Full";
                    btn.disabled = true;
                    btn.classList.add('full');
                }
            });

            initDates();
            initDropdowns();
        });

        function getCollegeSpace(collegeName) {
            return collegeSpaces[collegeName] ?? 0;
        }

        function reduceCollegeSpace(collegeName, units) {
            if (collegeSpaces.hasOwnProperty(collegeName)) {
                collegeSpaces[collegeName] -= units;
                document.querySelectorAll('.collegeCard').forEach(function(card) {
                    let name = card.querySelector('h3').textContent.trim();
                    let btn  = card.querySelector('.selectBtn');
                    if (name === collegeName) {
                        card.querySelector('p span').textContent = collegeSpaces[collegeName];
                        if (collegeSpaces[collegeName] <= 0) {
                            btn.textContent = "Full";
                            btn.disabled = true;
                            btn.classList.add('full');
                        }
                    }
                });
            }
        }

        function checkItemLimit() {
            const qtyInputs = [
                'bigBagQty', 'medBagQty', 'smallBagQty',
                'largeLugQty', 'medLugQty', 'smallLugQty',
                'bigBoxQty', 'medBoxQty', 'smallBoxQty',
                'bucketInput', 'otherQty'
            ];

            let totalItems = 0;
            qtyInputs.forEach(id => {
                totalItems += parseInt(document.getElementById(id).value) || 0;
            });

            const limitReached = totalItems >= 3;
            const reminder = document.getElementById('itemLimitReminder');

            reminder.style.color = limitReached ? '#c0392b' : '#e74c3c';
            reminder.textContent = limitReached
                ? '* Limit reached! Reduce items to add more.'
                : '* Maximum 3 items per booking.';

            // Disable/enable Choose buttons
            document.querySelectorAll('.chooseBtn').forEach(btn => {
                if (limitReached) {
                    btn.disabled = true;
                    btn.style.backgroundColor = '#888888';
                    btn.style.cursor = 'not-allowed';
                } else {
                    btn.disabled = false;
                    btn.style.backgroundColor = '#241253';
                    btn.style.cursor = 'pointer';
                }
            });

                const dropdownInputs = [
                    'bigBagQty', 'medBagQty', 'smallBagQty',
                    'largeLugQty', 'medLugQty', 'smallLugQty',
                    'bigBoxQty', 'medBoxQty', 'smallBoxQty'
                ];

                dropdownInputs.forEach(id => {
                    const input = document.getElementById(id);
                    const val = parseInt(input.value) || 0;

                if (limitReached && val === 0) {
                    input.disabled = true;
                    input.style.backgroundColor = '#aaaaaa';
                    input.style.color = '#666666';
                } else {
                    input.disabled = false;
                    input.style.backgroundColor = '#E8E9DE';
                    input.style.color = '#241253';
                }
            });

            const bucketInput = document.getElementById('bucketInput');
            const otherInput = document.getElementById('otherQty');

            bucketInput.disabled = limitReached && (parseInt(bucketInput.value) || 0) === 0;
            otherInput.disabled  = limitReached && (parseInt(otherInput.value) || 0) === 0;

            bucketInput.style.backgroundColor = bucketInput.disabled ? '#cccccc' : '#ffffff';
            otherInput.style.backgroundColor  = otherInput.disabled  ? '#cccccc' : '#ffffff';
        }

        function selectCollege(button, collegeName, availableSpace, collegeId) {
            let currentSpace = getCollegeSpace(collegeName);
            if (currentSpace <= 0) {
                return;
            }

            let allButtons = document.querySelectorAll('.selectBtn');
            for (let i = 0; i < allButtons.length; i++) {
                if (!allButtons[i].classList.contains('full')) {
                    allButtons[i].textContent = "Select";
                    allButtons[i].classList.remove('selectCollege');
                }
            }

            button.textContent = "Selected";
            button.classList.add('selectCollege');
            selectedCollegeName = collegeName;
            document.getElementById('residentialCollege').value = collegeId;
        }

        const currentYear = new Date().getFullYear();
        document.getElementById('currentYear').innerHTML = currentYear;

        const dropOffDay = document.getElementById('dropOffDay');
        const dropOffMonth = document.getElementById('dropOffMonth');
        const pickupDay = document.getElementById('pickupDay');
        const pickupMonth = document.getElementById('pickupMonth');

        function getDaysInMonth(monthIndex) {
            return new Date(currentYear, monthIndex + 1, 0).getDate();
        }

        function initDates() {
            const today = new Date();
            const todayMonth = today.getMonth();
            const todayDay = today.getDate();

            dropOffMonth.innerHTML = '';
            const allMonths = ['January','February','March','April','May','June',
                               'July','August','September','October','November','December'];

            for (let m = todayMonth; m < 12; m++) {
                let opt = document.createElement('option');
                opt.value = m;
                opt.textContent = allMonths[m];
                dropOffMonth.appendChild(opt);
            }
            dropOffMonth.selectedIndex = 0;

            pickupMonth.innerHTML = '';
            for (let m = todayMonth; m < 12; m++) {
                let opt = document.createElement('option');
                opt.value = m;
                opt.textContent = allMonths[m];
                pickupMonth.appendChild(opt);
            }
            pickupMonth.selectedIndex = 0;

            function fillDaysFiltered(selectElement, monthIndex, restrictToToday, isPickup) {
                let totalDays = getDaysInMonth(monthIndex);
                let startDay = 1;

                if (restrictToToday && monthIndex === todayMonth) {
                    startDay = todayDay;
                    if (isPickup) {
                        startDay = todayDay + 1;
                    }
                }

                selectElement.innerHTML = '';
                for (let i = startDay; i <= totalDays; i++) {
                    let opt = document.createElement('option');
                    opt.value = i < 10 ? '0' + i : '' + i;
                    opt.textContent = i;
                    selectElement.appendChild(opt);
                }
            }

            fillDaysFiltered(dropOffDay, todayMonth, true);
            fillDaysFiltered(pickupDay, todayMonth, true, true);

            dropOffMonth.addEventListener('change', function() {
                let selectedMonth = parseInt(dropOffMonth.value);
                fillDaysFiltered(dropOffDay, selectedMonth, selectedMonth === todayMonth);
            });

            pickupMonth.addEventListener('change', function() {
                let selectedMonth = parseInt(pickupMonth.value);
                fillDaysFiltered(pickupDay, selectedMonth, selectedMonth === todayMonth);
            });
        }

        function initDropdowns() {
            document.querySelectorAll('.chooseBtn').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetDropdown = document.getElementById(targetId);

                    if (targetDropdown.classList.contains('show')) {
                        targetDropdown.classList.remove('show');
                        this.innerHTML = 'Choose &#9660;';
                    } else {
                        targetDropdown.classList.add('show');
                        this.innerHTML = 'Close &#9650;';
                    }
                });
            });
        }

        function calculateTotal() {
            const qtyInputs = [
                'bigBagQty', 'medBagQty', 'smallBagQty',
                'largeLugQty', 'medLugQty', 'smallLugQty',
                'bigBoxQty', 'medBoxQty', 'smallBoxQty',
                'bucketInput', 'otherQty'
            ];

            let totalItems = 0;
            qtyInputs.forEach(id => {
                let val = parseInt(document.getElementById(id).value) || 0;
                if (val < 0) {
                    val = 0;
                    document.getElementById(id).value = 0;
                }
                totalItems += val;
            });

            let basePrice =
    (parseInt(document.getElementById('bigBagQty').value) || 0) * 7 +
    (parseInt(document.getElementById('medBagQty').value) || 0) * 5 +
    (parseInt(document.getElementById('smallBagQty').value) || 0) * 3 +

    (parseInt(document.getElementById('largeLugQty').value) || 0) * 10 +
    (parseInt(document.getElementById('medLugQty').value) || 0) * 8 +
    (parseInt(document.getElementById('smallLugQty').value) || 0) * 6 +

    (parseInt(document.getElementById('bigBoxQty').value) || 0) * 5 +
    (parseInt(document.getElementById('medBoxQty').value) || 0) * 3 +
    (parseInt(document.getElementById('smallBoxQty').value) || 0) * 2 +

    (parseInt(document.getElementById('bucketInput').value) || 0) * 3 +
    (parseInt(document.getElementById('otherQty').value) || 0) * 5;

const isEmergency = document.getElementById('emergencyCheckbox').checked;
if (isEmergency) {
    basePrice += 5;
}

            document.getElementById('totalPrice').textContent = basePrice.toFixed(2);
        }

        function submitBooking() {
            if (!selectedCollegeName) {
                alert("Please select a Residential College first.");
                return false;
            }

            let totalItems = 0;
            const qtyInputs = [
                'bigBagQty', 'medBagQty', 'smallBagQty',
                'largeLugQty', 'medLugQty', 'smallLugQty',
                'bigBoxQty', 'medBoxQty', 'smallBoxQty',
                'bucketInput', 'otherQty'
            ];

            qtyInputs.forEach(id => {
                totalItems += parseInt(document.getElementById(id).value) || 0;
            });

            if (totalItems <= 0) {
                alert("Please select at least 1 item to book storage.");
                return false;
            }

            if (totalItems > 3) {
                alert("You can only book a maximum of 3 items per booking.");
                return false;
            }


            let spaceAvailable = getCollegeSpace(selectedCollegeName);
            if (totalItems > spaceAvailable) {
                alert(`Insufficient storage capacity in ${selectedCollegeName}. Only ${spaceAvailable} units remaining.`);
                return false;
            }

            let dropMonthNum = parseInt(dropOffMonth.value) + 1;
            let formattedDropMonth = dropMonthNum < 10 ? '0' + dropMonthNum : dropMonthNum;
            let fullDropOffDate = `${currentYear}-${formattedDropMonth}-${dropOffDay.value}`;

            let pickMonthNum = parseInt(pickupMonth.value) + 1;
            let formattedPickMonth = pickMonthNum < 10 ? '0' + pickMonthNum : pickMonthNum;
            let fullPickupDate = `${currentYear}-${formattedPickMonth}-${pickupDay.value}`;

            if (new Date(fullPickupDate) <= new Date(fullDropOffDate)) {
                alert("Invalid configuration: Pickup date must occur after the drop-off date.");
                return false;
            }

            document.getElementById('dropOffDate').value = fullDropOffDate;
            document.getElementById('pickupDate').value = fullPickupDate;

            if (document.getElementById('emergencyCheckbox').checked) {
                document.getElementById('emergency').name = "emergency";
                document.getElementById('emergency').value = "Y";
            } else {
                document.getElementById('emergency').removeAttribute('name');
            }

            reduceCollegeSpace(selectedCollegeName, totalItems);
            return true;
        }
    </script>
</body>
</html>