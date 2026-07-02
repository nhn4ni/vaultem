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

$student_id   = $_SESSION['Student_ID'] ?? '';
$studentIdEsc = mysqli_real_escape_string($conn, $student_id);

// ── Block if student already has an active booking ────────────────────────────
$activeChk = mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM booking
    WHERE Student_ID = '$studentIdEsc'
      AND LOWER(Booking_Status) NOT IN ('rejected', 'collected')
      AND Pickup_Date >= CURDATE()
");
$activeRow = mysqli_fetch_assoc($activeChk);
if ($activeRow['c'] > 0) {
    header("Location: mainStatus.php?msg=already_booked");
    exit();
}

// ── Check if booking window is open ───────────────────────────────────────────
$windowRes = mysqli_query($conn, "
    SELECT window_id, label, start_date, end_date
    FROM booking_window
    WHERE start_date <= CURDATE()
      AND end_date   >= CURDATE()
    ORDER BY start_date ASC
");
$activeWindows = [];
while ($wRow = mysqli_fetch_assoc($windowRes)) {
    $activeWindows[] = $wRow;
}

// Also fetch all future active windows so student knows upcoming dates
$upcomingRes = mysqli_query($conn, "
    SELECT label, start_date, end_date
    FROM booking_window
    WHERE start_date > CURDATE()
    ORDER BY start_date ASC
    LIMIT 3
");
$upcomingWindows = [];
while ($uRow = mysqli_fetch_assoc($upcomingRes)) {
    $upcomingWindows[] = $uRow;
}

$bookingOpen = !empty($activeWindows);

$genderQuery = mysqli_query($conn, "SELECT Gender FROM student WHERE Student_ID = '$studentIdEsc'");
$studentGender = 'M';

if ($genderQuery && mysqli_num_rows($genderQuery) > 0) {
    $genderRow = mysqli_fetch_assoc($genderQuery);
    $studentGender = $genderRow['Gender'];
} else {
    die("No student record found. Please log in again.");
}

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
    $dropOffDate        = $_POST['dropOffDate'] ?? '';
    $pickupDate         = $_POST['pickupDate'] ?? '';
    $bookingPriority    = isset($_POST['emergency']) ? 'Y' : 'N';
    $paymentStatus      = 'N';

    $bigBagQty   = (int)($_POST['bigBagQty']   ?? 0);
    $medBagQty   = (int)($_POST['medBagQty']   ?? 0);
    $smallBagQty = (int)($_POST['smallBagQty'] ?? 0);
    $largeLugQty = (int)($_POST['largeLugQty'] ?? 0);
    $medLugQty   = (int)($_POST['medLugQty']   ?? 0);
    $smallLugQty = (int)($_POST['smallLugQty'] ?? 0);
    $bigBoxQty   = (int)($_POST['bigBoxQty']   ?? 0);
    $medBoxQty   = (int)($_POST['medBoxQty']   ?? 0);
    $smallBoxQty = (int)($_POST['smallBoxQty'] ?? 0);
    $bucketQty   = (int)($_POST['bucketQty']   ?? 0);
    $otherQty    = (int)($_POST['otherQty']    ?? 0);

    $totalItems = $bigBagQty + $medBagQty + $smallBagQty + $largeLugQty + $medLugQty
                + $smallLugQty + $bigBoxQty + $medBoxQty + $smallBoxQty + $bucketQty + $otherQty;

    if ($totalItems <= 0) {
        die("No items were submitted for this booking.");
    }

    if ($totalItems > 3) {
        die("Booking limit exceeded. You may only book a maximum of 3 items.");
    }

    $totalPrice =
        ($bigBagQty   * 7.00) +
        ($medBagQty   * 5.00) +
        ($smallBagQty * 3.00) +
        ($largeLugQty * 10.00) +
        ($medLugQty   * 8.00) +
        ($smallLugQty * 6.00) +
        ($bigBoxQty   * 5.00) +
        ($medBoxQty   * 3.00) +
        ($smallBoxQty * 2.00) +
        ($bucketQty   * 3.00) +
        ($otherQty    * 5.00);

    if ($bookingPriority === 'Y') {
        $totalPrice += 10.00;
    }

    $studentIdEsc       = mysqli_real_escape_string($conn, $student_id);
    $dropOffDateEsc     = mysqli_real_escape_string($conn, $dropOffDate);
    $pickupDateEsc      = mysqli_real_escape_string($conn, $pickupDate);

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

    $deductSpace = mysqli_prepare($conn, "UPDATE storespace SET Size = Size - ? WHERE Space_ID = ?");
    mysqli_stmt_bind_param($deductSpace, "ii", $totalItems, $space_id);
    mysqli_stmt_execute($deductSpace);
    mysqli_stmt_close($deductSpace);

    if (!mysqli_query($conn, "INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID)
                              VALUES ('Online', '$paymentStatus', CURDATE(), $totalPrice, $booking_id)")) {
        die("Payment insert failed: " . mysqli_error($conn));
    }

    $items = [
        ['name' => 'Big Bag',        'size' => 'L', 'qty' => $bigBagQty],
        ['name' => 'Medium Bag',     'size' => 'M', 'qty' => $medBagQty],
        ['name' => 'Small Bag',      'size' => 'S', 'qty' => $smallBagQty],
        ['name' => 'Large Luggage',  'size' => 'L', 'qty' => $largeLugQty],
        ['name' => 'Medium Luggage', 'size' => 'M', 'qty' => $medLugQty],
        ['name' => 'Small Luggage',  'size' => 'S', 'qty' => $smallLugQty],
        ['name' => 'Big Box',        'size' => 'L', 'qty' => $bigBoxQty],
        ['name' => 'Medium Box',     'size' => 'M', 'qty' => $medBoxQty],
        ['name' => 'Small Box',      'size' => 'S', 'qty' => $smallBoxQty],
        ['name' => 'Bucket/Pail',    'size' => 'M', 'qty' => $bucketQty],
        ['name' => 'Other',          'size' => 'M', 'qty' => $otherQty],
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
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
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

        .itemPrice {
            font-size: 1rem;
            color: #7a6e93;
            font-weight: normal;
        }

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

        .submitBtn:hover {
            background-color: #969696;
            cursor: pointer;
            transform: translateY(-2px);
        }

        #backBtn {
            background-color: #bb3d3d;
            color: #E8E9DE;
            border: none;
            border-radius: 25px;
            padding: 12px 45px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
        }

        #backBtn:hover {
            background-color: #a03333;
            cursor: pointer;
            transform: translateY(-2px);
        }

        .selectBtn:hover {
            background-color: #969696;
            cursor: pointer;
            transform: translateY(-2px);
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

<?php if (!$bookingOpen): ?>
<!-- ── Booking Closed Screen ── -->
<div style="display:flex; height:100vh; width:100vw; overflow:hidden;">
    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='mainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
    </div>
    <div class="rightcontainer" style="justify-content:center; padding:50px;">
        <div style="max-width:500px;">
            <h2 style="color:#E8E9DE; margin-bottom:10px;">Booking Unavailable</h2>
            <p style="color:rgba(232,233,222,0.7); margin-bottom:24px; line-height:1.6;">
                Student bookings are currently closed. The booking form is only available during scheduled periods set by staff.
            </p>

            <?php if (!empty($upcomingWindows)): ?>
            <div style="background:rgba(124,92,252,0.12); border:1px solid rgba(124,92,252,0.3); border-radius:14px; padding:16px 18px; margin-bottom:20px;">
                <p style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:#b084ff; margin-bottom:10px; font-weight:700;">Upcoming Booking Periods</p>
                <?php foreach ($upcomingWindows as $uw): ?>
                <p style="font-size:0.88rem; color:#E8E9DE; margin:6px 0;">
                    <strong><?php echo htmlspecialchars($uw['label']); ?></strong><br>
                    <span style="color:rgba(232,233,222,0.6); font-size:0.82rem;">
                        <?php echo date('d M Y', strtotime($uw['start_date'])); ?> — <?php echo date('d M Y', strtotime($uw['end_date'])); ?>
                    </span>
                </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="mainStatus.php" style="display:inline-block; background:#E8E9DE; color:#241253; padding:12px 28px; border-radius:20px; font-weight:bold; text-decoration:none; font-size:0.9rem;">
                Back to Dashboard
            </a>
        </div>
    </div>
</div>
<?php else: ?>

    <form id="bookingForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return submitBooking()">

        <div class="leftContainer">

            <div id="formLayout">
                <h1>Booking Form</h1>
                <h3>Residential College</h3>

                <div class="collegeGrid">
                    <?php
                    if ($collegeResult && mysqli_num_rows($collegeResult) > 0) {
                        while ($college = mysqli_fetch_assoc($collegeResult)) {
                            $collegeName    = $college['Residential_Block'];
                            $availableSpace = $college['Available_Space'];
                            ?>
                            <div class="collegeCard" data-space="<?php echo (int)$availableSpace; ?>">
                                <h3><?php echo htmlspecialchars($collegeName); ?></h3>
                                <p>Space : <span><?php echo (int)$availableSpace; ?></span> unit</p>
                                <button type="button" class="selectBtn"
                                    onclick="selectCollege(this, '<?php echo htmlspecialchars($collegeName); ?>', <?php echo (int)$availableSpace; ?>, <?php echo $college['Residential_ID']; ?>)">
                                    Select
                                </button>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<p style='color: red;'>No residential colleges available for your gender.</p>";
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
                    * Maximum 3 items per booking.
                </p>

                <!-- Bag -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Bag</span>
                        <span class="itemPrice">Big RM7 | Medium RM5 | Small RM3</span>
                        <div class="bagDropdown" id="bagDropdown">
                            <div class="bagOption">
                                <span>Big Bag <span class="itemPrice">RM 7.00</span></span>
                                <input type="number" id="bigBagQty" name="bigBagQty" value="0" min="0" max="3" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Medium Bag <span class="itemPrice">RM 5.00</span></span>
                                <input type="number" id="medBagQty" name="medBagQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Small Bag <span class="itemPrice">RM 3.00</span></span>
                                <input type="number" id="smallBagQty" name="smallBagQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="bagDropdown">Choose &#9660;</button>
                </div>

                <!-- Luggage -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Luggage</span>
                        <span class="itemPrice">Large RM10 | Medium RM8 | Small RM6</span>
                        <div class="bagDropdown" id="luggageDropdown">
                            <div class="bagOption">
                                <span>Large Luggage <span class="itemPrice">RM 10.00</span></span>
                                <input type="number" id="largeLugQty" name="largeLugQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Medium Luggage <span class="itemPrice">RM 8.00</span></span>
                                <input type="number" id="medLugQty" name="medLugQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Small Luggage <span class="itemPrice">RM 6.00</span></span>
                                <input type="number" id="smallLugQty" name="smallLugQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="luggageDropdown">Choose &#9660;</button>
                </div>

                <!-- Box -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Box</span>
                        <span class="itemPrice">Big RM5 | Medium RM3 | Small RM2</span>
                        <div class="bagDropdown" id="boxDropdown">
                            <div class="bagOption">
                                <span>Big Box <span class="itemPrice">RM 5.00</span></span>
                                <input type="number" id="bigBoxQty" name="bigBoxQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Medium Box <span class="itemPrice">RM 3.00</span></span>
                                <input type="number" id="medBoxQty" name="medBoxQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                            <div class="bagOption">
                                <span>Small Box <span class="itemPrice">RM 2.00</span></span>
                                <input type="number" id="smallBoxQty" name="smallBoxQty" value="0" min="0" max="3" oninput="calculateTotal(); checkItemLimit()">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="chooseBtn" data-target="boxDropdown">Choose &#9660;</button>
                </div>

                <!-- Bucket/Pail -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Bucket/Pail</span>
                        <span class="itemPrice">RM 3.00 / item</span>
                    </div>
                    <input id="bucketInput" type="number" name="bucketQty" min="0" max="3" value="0" oninput="calculateTotal(); checkItemLimit()">
                </div>

                <!-- Others -->
                <div class="itemRow">
                    <div class="itemLeft">
                        <span>Others</span>
                        <span class="itemPrice">RM 5.00 / item</span>
                    </div>
                    <input id="otherQty" type="number" name="otherQty" min="0" max="3" value="0" oninput="calculateTotal(); checkItemLimit()">
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
                    <span class="emergencyNote">(+ RM10 for Emergency Booking)</span>
                </div>
                <button type="button" id="backBtn" onclick="window.location.href='mainStatus.php'">Cancel</button>
                <button type="submit" class="submitBtn">Submit</button>
            </div>
        </div>
    </form>

    <script>
        localStorage.removeItem('collegeSpaces');
        let collegeSpaces = {};
        let selectedCollegeName = "";

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.collegeCard').forEach(function (card) {
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
                document.querySelectorAll('.collegeCard').forEach(function (card) {
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
            // Clamp any input that pushes total over 3
            qtyInputs.forEach(id => {
                const el = document.getElementById(id);
                const val = parseInt(el.value) || 0;
                const others = qtyInputs.reduce((s, oid) => s + (oid !== id ? (parseInt(document.getElementById(oid).value) || 0) : 0), 0);
                if (others + val > 3) { el.value = Math.max(0, 3 - others); }
            });
            const reminder = document.getElementById('itemLimitReminder');

            reminder.style.color = limitReached ? '#c0392b' : '#e74c3c';
            reminder.textContent = limitReached
                ? '* Limit reached! Reduce items to add more.'
                : '* Maximum 3 items per booking.';

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
                const val   = parseInt(input.value) || 0;

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
            const otherInput  = document.getElementById('otherQty');

            bucketInput.disabled = limitReached && (parseInt(bucketInput.value) || 0) === 0;
            otherInput.disabled  = limitReached && (parseInt(otherInput.value)  || 0) === 0;

            bucketInput.style.backgroundColor = bucketInput.disabled ? '#cccccc' : '#ffffff';
            otherInput.style.backgroundColor  = otherInput.disabled  ? '#cccccc' : '#ffffff';
        }

        function selectCollege(button, collegeName, availableSpace, collegeId) {
            let currentSpace = getCollegeSpace(collegeName);
            if (currentSpace <= 0) return;

            document.querySelectorAll('.selectBtn').forEach(btn => {
                if (!btn.classList.contains('full')) {
                    btn.textContent = "Select";
                    btn.classList.remove('selectCollege');
                }
            });

            button.textContent = "Selected";
            button.classList.add('selectCollege');
            selectedCollegeName = collegeName;
            document.getElementById('residentialCollege').value = collegeId;
        }

        const currentYear = new Date().getFullYear();
        document.getElementById('currentYear').innerHTML = currentYear;

        const dropOffDay   = document.getElementById('dropOffDay');
        const dropOffMonth = document.getElementById('dropOffMonth');
        const pickupDay    = document.getElementById('pickupDay');
        const pickupMonth  = document.getElementById('pickupMonth');

        function getDaysInMonth(monthIndex) {
            return new Date(currentYear, monthIndex + 1, 0).getDate();
        }

        // ── Booking windows from PHP ──────────────────────────────────────────
        const bookingWindows = <?php
            echo json_encode(array_map(function($w) {
                return ['start' => $w['start_date'], 'end' => $w['end_date']];
            }, $activeWindows));
        ?>;

        // Get min and max allowed dates across all windows
        function getWindowBounds() {
            let minDate = null, maxDate = null;
            bookingWindows.forEach(function(w) {
                if (!minDate || w.start < minDate) minDate = w.start;
                if (!maxDate || w.end   > maxDate) maxDate = w.end;
            });
            return { minDate, maxDate };
        }

        // Check if a given YYYY-MM-DD date falls in any window
        function isDateAllowed(dateStr) {
            return bookingWindows.some(w => dateStr >= w.start && dateStr <= w.end);
        }

        function padTwo(n) { return n < 10 ? '0' + n : '' + n; }

        function initDates() {
            const today      = new Date();
            const todayMonth = today.getMonth();
            const todayDay   = today.getDate();
            const bounds     = getWindowBounds();

            const allMonths = ['January','February','March','April','May','June',
                               'July','August','September','October','November','December'];

            // Parse window bounds
            const minParts   = bounds.minDate.split('-');
            const maxParts   = bounds.maxDate.split('-');
            const winMinMonth = parseInt(minParts[1]) - 1; // 0-indexed
            const winMinDay   = parseInt(minParts[2]);
            const winMaxMonth = parseInt(maxParts[1]) - 1;
            const winMaxDay   = parseInt(maxParts[2]);

            // Only show months within the window
            function fillMonths(selectEl, isPickup) {
                selectEl.innerHTML = '';
                for (let m = winMinMonth; m <= winMaxMonth; m++) {
                    // For drop-off: skip months fully before today
                    const lastDayOfMonth = getDaysInMonth(m);
                    const dateStr = `${currentYear}-${padTwo(m+1)}-${padTwo(lastDayOfMonth)}`;
                    if (dateStr < today.toISOString().slice(0,10)) continue;

                    let opt = document.createElement('option');
                    opt.value       = m;
                    opt.textContent = allMonths[m];
                    selectEl.appendChild(opt);
                }
                selectEl.selectedIndex = 0;
            }

            function fillDaysFiltered(selectElement, monthIndex, isPickup) {
                selectElement.innerHTML = '';
                const totalDays = getDaysInMonth(monthIndex);

                // Start day: today or tomorrow (for pickup), but not before window start
                let startDay = 1;
                if (monthIndex === todayMonth) {
                    startDay = isPickup ? todayDay + 1 : todayDay;
                }
                // Don't go before window start day if in window's first month
                if (monthIndex === winMinMonth && winMinDay > startDay) {
                    startDay = winMinDay;
                }

                // End day: don't exceed window end day if in window's last month
                let endDay = totalDays;
                if (monthIndex === winMaxMonth && winMaxDay < endDay) {
                    endDay = winMaxDay;
                }

                for (let i = startDay; i <= endDay; i++) {
                    const dateStr = `${currentYear}-${padTwo(monthIndex+1)}-${padTwo(i)}`;
                    if (!isDateAllowed(dateStr)) continue;
                    let opt = document.createElement('option');
                    opt.value       = padTwo(i);
                    opt.textContent = i;
                    selectElement.appendChild(opt);
                }
            }

            fillMonths(dropOffMonth, false);
            fillMonths(pickupMonth,  true);

            fillDaysFiltered(dropOffDay, parseInt(dropOffMonth.value || winMinMonth), false);
            fillDaysFiltered(pickupDay,  parseInt(pickupMonth.value  || winMinMonth), true);

            dropOffMonth.addEventListener('change', function () {
                fillDaysFiltered(dropOffDay, parseInt(this.value), false);
            });

            pickupMonth.addEventListener('change', function () {
                fillDaysFiltered(pickupDay, parseInt(this.value), true);
            });
        }

        function initDropdowns() {
            document.querySelectorAll('.chooseBtn').forEach(button => {
                button.addEventListener('click', function () {
                    const targetId       = this.getAttribute('data-target');
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

            qtyInputs.forEach(id => {
                let val = parseInt(document.getElementById(id).value) || 0;
                if (val < 0) document.getElementById(id).value = 0;
            });

            let basePrice =
                (parseInt(document.getElementById('bigBagQty').value)   || 0) * 7  +
                (parseInt(document.getElementById('medBagQty').value)   || 0) * 5  +
                (parseInt(document.getElementById('smallBagQty').value) || 0) * 3  +
                (parseInt(document.getElementById('largeLugQty').value) || 0) * 10 +
                (parseInt(document.getElementById('medLugQty').value)   || 0) * 8  +
                (parseInt(document.getElementById('smallLugQty').value) || 0) * 6  +
                (parseInt(document.getElementById('bigBoxQty').value)   || 0) * 5  +
                (parseInt(document.getElementById('medBoxQty').value)   || 0) * 3  +
                (parseInt(document.getElementById('smallBoxQty').value) || 0) * 2  +
                (parseInt(document.getElementById('bucketInput').value) || 0) * 3  +
                (parseInt(document.getElementById('otherQty').value)    || 0) * 5;

            if (document.getElementById('emergencyCheckbox').checked) {
                basePrice += 10;
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

            let dropMonthNum      = parseInt(dropOffMonth.value) + 1;
            let formattedDropMonth = dropMonthNum < 10 ? '0' + dropMonthNum : dropMonthNum;
            let fullDropOffDate    = `${currentYear}-${formattedDropMonth}-${dropOffDay.value}`;

            let pickMonthNum      = parseInt(pickupMonth.value) + 1;
            let formattedPickMonth = pickMonthNum < 10 ? '0' + pickMonthNum : pickMonthNum;
            let fullPickupDate     = `${currentYear}-${formattedPickMonth}-${pickupDay.value}`;

            if (new Date(fullPickupDate) <= new Date(fullDropOffDate)) {
                alert("Invalid configuration: Pickup date must occur after the drop-off date.");
                return false;
            }

            document.getElementById('dropOffDate').value = fullDropOffDate;
            document.getElementById('pickupDate').value  = fullPickupDate;

            if (document.getElementById('emergencyCheckbox').checked) {
                document.getElementById('emergency').name  = "emergency";
                document.getElementById('emergency').value = "Y";
            } else {
                document.getElementById('emergency').removeAttribute('name');
            }

            reduceCollegeSpace(selectedCollegeName, totalItems);
            return true;
        }
    </script>

    <script>
        // ── Restrict dates to active booking windows ──────────────────────────
        // Window date validation is now handled inside initDates() above.
        // This block only handles final submit validation.

        const origSubmit = window.submitBooking;
        window.submitBooking = function() {
            const dropOff = document.getElementById('dropOffDate')?.value;
            const pickup  = document.getElementById('pickupDate')?.value;
            if (dropOff && !isDateAllowed(dropOff)) {
                alert('Selected drop-off date is outside the allowed booking period.');
                return false;
            }
            if (pickup && !isDateAllowed(pickup)) {
                alert('Selected pick-up date is outside the allowed booking period.');
                return false;
            }
            return origSubmit ? origSubmit() : true;
        };
    </script>

<?php endif; ?>
</body>
</html>