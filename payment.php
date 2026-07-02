<?php
session_start();
// Normalize session variable names
if (isset($_SESSION['Student_ID']) && !isset($_SESSION['student_id'])) {
    $_SESSION['student_id'] = $_SESSION['Student_ID'];
}

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['Student_ID'])) {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['student_id'] ?? $_SESSION['Student_ID'] ?? '';

// ── Determine payment type: 'booking' (default) or 'penalty' ─────────────────
$type = $_GET['type'] ?? $_POST['type'] ?? 'booking';
if (!in_array($type, ['booking', 'penalty'])) {
    $type = 'booking';
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$paymentSuccess  = false;
$payLaterSuccess = false;

if ($type === 'penalty') {
    // ══════════════════════════════════════════════════════════════════════
    // PENALTY PAYMENT (size-mismatch violation)
    // ══════════════════════════════════════════════════════════════════════

    $violation_id = $_GET['violation_id'] ?? $_POST['violation_id'] ?? '';
    if (empty($violation_id)) {
        header('Location: mainStatus.php');
        exit();
    }

    // Verify the violation belongs to this student, via its booking
    $verifyStmt = $conn->prepare("
        SELECT sv.Violation_ID, sv.Booking_ID, sv.Item_Name, sv.Declared_Size, sv.Actual_Size,
               sv.Penalty_Amount, sv.Reason, sv.Status
        FROM size_violation sv
        JOIN booking b ON sv.Booking_ID = b.Booking_ID
        WHERE sv.Violation_ID = ? AND b.Student_ID = ?
    ");
    $verifyStmt->bind_param("is", $violation_id, $student_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows === 0) {
        die("Invalid penalty record or unauthorized access.");
    }
    $violationData = $verifyStmt->get_result() ? $verifyResult->fetch_assoc() : null;
    $verifyStmt->close();

    // Already resolved? Bounce back.
    if ($violationData['Status'] !== 'Unpaid') {
        ?>
        <script>
            alert("This penalty has already been resolved.");
            window.location.href = "mainStatus.php";
        </script>
        <?php
        exit();
    }

    // Amount is always taken from the database record, never trusted from the URL/form
    $amount = (float)$violationData['Penalty_Amount'];
    $booking_id = $violationData['Booking_ID'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
        $updateStmt = $conn->prepare("UPDATE size_violation SET Status = 'Paid', Resolved_At = NOW() WHERE Violation_ID = ?");
        $updateStmt->bind_param("i", $violation_id);
        if ($updateStmt->execute()) {
            $paymentSuccess = true;
        } else {
            echo "Error: " . $updateStmt->error;
        }
        $updateStmt->close();
    }

    $conn->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VaulteM - Penalty Payment</title>
        <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
        <style>
            * { font-family: 'Courier New', Courier, monospace; box-sizing: border-box; }
            body { background-color: #E8E9DE; color: #241253; margin: 0; }
            #body2 { margin: 0; height: 100vh; display: flex; justify-content: center; align-items: center; }
            .back { position: absolute; border-style: none; background-color: #E8E9DE; font-size: 1.2rem; font-weight: bold; color: #241253; margin: 15px; cursor: pointer; }
            h2 { text-align: center; margin-top: 0; }
            .paymentBox { width: 350px; min-height: 55%; background-color: #241253; color: #E8E9DE; border: 1px solid #ccc; border-radius: 20px; padding: 20px; box-sizing: border-box; display: flex; flex-direction: column; align-items: flex-start; }
            .paymentBox h2 { width: 100%; }
            .bookingSummary h3 { margin-top: 10px; margin-bottom: 15px; border-bottom: 1px dashed rgba(232, 233, 222, 0.3); padding-bottom: 5px; }
            .bookingSummary p { font-size: 0.9rem; margin: 8px 0; }
            .penaltyBadge { display: inline-block; background: #bb3d3d; color: #fff; font-size: 0.7rem; font-weight: bold; padding: 3px 10px; border-radius: 10px; margin-bottom: 10px; }
            .reasonBox { background: rgba(255,255,255,0.08); border-radius: 10px; padding: 10px 12px; font-size: 0.82rem; margin-top: 6px; line-height: 1.5; }
            #totalAmount { font-weight: bold; font-size: 1.4rem; }
            #payBtn, #pay, #payLaterBtn { background-color: #E8E9DE; color: #241253; width: 100%; padding: 12px; border-style: none; border-radius: 25px; font-weight: bold; font-size: 0.95rem; transition: all 0.2s ease; }
            #payBtn:hover, #pay:hover, #payLaterBtn:hover { background-color: #d6d7ca; cursor: pointer; transform: translateY(-2px); }
            .totalSection { margin-top: auto; display: flex; flex-direction: column; align-items: flex-start; width: 100%; padding-top: 20px; }
            .totalSection p { margin: 5px 0; }
            #payBtn { margin-top: 10px; }
            #payMethod { max-height: 0; opacity: 0; overflow: hidden; transform: translateY(-10px); transition: all 0.3s ease; width: 100%; }
            #payMethod.show { opacity: 1; max-height: 350px; transform: translateY(0); margin-top: 15px; border-top: 1px dashed rgba(232, 233, 222, 0.3); padding-top: 15px; }
            .radio-group { margin-bottom: 8px; }
            .radio-group label { cursor: pointer; font-size: 0.95rem; padding-left: 5px; }
            .radio-group input[type="radio"] { cursor: pointer; }
            .button-group { display: flex; gap: 10px; margin-top: 15px; width: 100%; }
            .popupOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; align-items: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
            .popupOverlay.show { opacity: 1; visibility: visible; }
            .popupBox { background: white; color: #241253; padding: 30px; border-radius: 20px; width: 320px; text-align: center; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); transform: scale(0.8); transition: all 0.3s ease; }
            .popupOverlay.show .popupBox { transform: scale(1); }
            #home { margin-top: 15px; padding: 10px 20px; border: none; border-radius: 20px; background: #241253; color: white; cursor: pointer; font-weight: bold; }
            #home:hover { background: #391e82; }
        </style>
    </head>
    <body>
        <div id="body2">
            <div class="paymentBox">
                <h2>Penalty Payment</h2>
                <div class="bookingSummary">
                    <span class="penaltyBadge">SIZE MISMATCH PENALTY</span>
                    <h3>Violation Summary</h3>
                    <p>Booking ID: <span>#<?php echo htmlspecialchars($booking_id); ?></span></p>
                    <p>Item: <span><?php echo htmlspecialchars($violationData['Item_Name']); ?></span></p>
                    <p>Declared Size → Actual Size: <span><?php echo htmlspecialchars($violationData['Declared_Size']); ?> &#8594; <?php echo htmlspecialchars($violationData['Actual_Size']); ?></span></p>
                    <p>Reason:</p>
                    <div class="reasonBox"><?php echo nl2br(htmlspecialchars($violationData['Reason'])); ?></div>
                </div>

                <form style="width: 100%; display: contents;" method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" id="paymentForm">
                    <input type="hidden" name="type" value="penalty">
                    <input type="hidden" name="violation_id" value="<?php echo htmlspecialchars($violation_id); ?>">

                    <div class="totalSection">
                        <p>Total:</p>
                        <p><span id="totalAmount">RM <?php echo number_format($amount, 2); ?></span></p>
                        <button type="button" id="payBtn" onclick="toggleMenu()">Select Payment Method</button>
                    </div>

                    <div id="payMethod">
                        <p style="margin-top: 0; margin-bottom: 12px;">Select your payment method:</p>
                        <div class="radio-group">
                            <input type="radio" id="banking" name="payment_method" value="Online Banking" checked>
                            <label for="banking">Online Banking</label>
                        </div>
                        <div class="radio-group">
                            <input type="radio" id="card" name="payment_method" value="Credit/Debit Card">
                            <label for="card">Credit/Debit Card</label>
                        </div>
                        <div class="radio-group">
                            <input type="radio" id="qr" name="payment_method" value="QR">
                            <label for="qr">QR</label>
                        </div>

                        <div class="button-group">
                            <button type="submit" id="pay">Pay Penalty Now</button>
                        </div>
                    </div>
                </form>
            </div>

            <div id="payPopup" class="popupOverlay <?php echo $paymentSuccess ? 'show' : ''; ?>">
                <div class="popupBox">
                    <?php if ($paymentSuccess): ?>
                        <p>Penalty payment of <strong>RM <?php echo number_format($amount, 2); ?></strong> successful!</p>
                        <p style="color:#198754; font-size:0.9rem; margin-top:8px;">This violation has been marked as resolved.</p>
                    <?php endif; ?>
                    <button type="button" id="home" onclick="window.location.href='mainStatus.php'">Go back home</button>
                </div>
            </div>
        </div>

        <script>
            function toggleMenu() {
                document.getElementById("payMethod").classList.toggle("show");
            }
            document.getElementById("paymentForm").addEventListener("submit", function(e) {
                const payButton = document.getElementById('pay');
                payButton.textContent = 'Processing...';
                payButton.disabled = true;
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ══════════════════════════════════════════════════════════════════════════
// BOOKING PAYMENT (original flow — unchanged)
// ══════════════════════════════════════════════════════════════════════════

// Get booking details from URL
$booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : (isset($_POST['booking_id']) ? $_POST['booking_id'] : '');
$amount = isset($_GET['amount']) ? $_GET['amount'] : (isset($_POST['amount']) ? $_POST['amount'] : 0);

// If no booking ID, redirect back
if (empty($booking_id)) {
    header('Location: mainStatus.php');
    exit();
}

// Verify booking exists and belongs to the student
$verifyStmt = $conn->prepare("
    SELECT b.Booking_ID, b.Booking_Status, b.DropOff_Date, b.Pickup_Date, rc.Residential_Block, p.Payment_Status, p.Amount,
    (SELECT SUM(Quantity) FROM item WHERE Booking_ID = b.Booking_ID) AS Total_Items
    FROM booking b 
    LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID 
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    LEFT JOIN storespace ss ON i.Space_ID = ss.Space_ID
    LEFT JOIN residential_college rc ON ss.Residential_ID = rc.Residential_ID
    WHERE b.Booking_ID = ? AND b.Student_ID = ?
    GROUP BY b.Booking_ID
");
$verifyStmt->bind_param("ss", $booking_id, $student_id);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    die("Invalid booking or unauthorized access.");
}

$bookingData = $verifyResult->fetch_assoc();

// Check if already paid
if ($bookingData['Payment_Status'] === 'Y') {
    ?>
    <script>
        alert("This booking has already been paid.");
        window.location.href = "mainStatus.php";
    </script>
    <?php
    exit();
}

// Payment is only allowed once staff has approved the booking
if ($bookingData['Booking_Status'] === 'Pending') {
    ?>
    <script>
        alert("Your booking is still awaiting staff review. You can pay once it has been approved.");
        window.location.href = "mainStatus.php";
    </script>
    <?php
    exit();
}

if ($bookingData['Booking_Status'] === 'Rejected') {
    ?>
    <script>
        alert("This booking was rejected and cannot be paid.");
        window.location.href = "mainStatus.php";
    </script>
    <?php
    exit();
}

if ($bookingData['Booking_Status'] !== 'Approved') {
    ?>
    <script>
        alert("This booking cannot be processed. Status: <?php echo $bookingData['Booking_Status']; ?>");
        window.location.href = "mainStatus.php";
    </script>
    <?php
    exit();
}

$verifyStmt->close();

// Process payment or pay later action if form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingID = $_POST['booking_id'];
    $amount = $_POST['amount'];
    
    // --- CASE 1: USER CHOSE TO PAY LATER ---
    if (isset($_POST['action']) && $_POST['action'] === 'pay_later') {
        $status = "N"; // Pay Later — not yet paid
        $date = null;        // No payment date yet

        $checkStmt = $conn->prepare("SELECT Payment_ID FROM payment WHERE Booking_ID = ?");
        $checkStmt->bind_param("s", $bookingID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE payment SET Payment_Status = ?, Payment_Date = ?, Amount = ? WHERE Booking_ID = ?");
            $stmt->bind_param("ssds", $status, $date, $amount, $bookingID);
        } else {
            $stmt = $conn->prepare("INSERT INTO payment (Payment_Status, Payment_Date, Amount, Booking_ID) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssds", $status, $date, $amount, $bookingID);
        }
        
        if ($stmt->execute()) {
            // Booking stays 'Approved' — this only defers payment, not the approval itself
            $payLaterSuccess = true;
        }
        $stmt->close();
        $checkStmt->close();
    } 
    // --- CASE 2: USER CHOSE TO PAY NOW ---
    elseif (isset($_POST['payment_method'])) {
        $method = $_POST['payment_method'];
        $status = "Y"; // Pay Now — payment completed
        $date = date("Y-m-d");

        $checkStmt = $conn->prepare("SELECT Payment_ID FROM payment WHERE Booking_ID = ?");
        $checkStmt->bind_param("s", $bookingID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE payment SET Payment_Method = ?, Payment_Status = ?, Payment_Date = ?, Amount = ? WHERE Booking_ID = ?");
            $stmt->bind_param("sssds", $method, $status, $date, $amount, $bookingID);
        } else {
            $stmt = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssds", $method, $status, $date, $amount, $bookingID);
        }
        
        if ($stmt->execute()) {
            // Booking was already approved by staff before payment — nothing to recalculate here
            $paymentSuccess = true;
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
        $checkStmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Payment</title>
        <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">

    <style>
        * {
            font-family: 'Courier New', Courier, monospace;
            box-sizing: border-box;
        }

        body {
            background-color: #E8E9DE;
            color: #241253;
            margin: 0;
        }

        #body2 {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .back {
            position: absolute;
            border-style: none;
            background-color: #E8E9DE;
            font-size: 1.2rem;
            font-weight: bold;
            color: #241253;
            margin: 15px;
            cursor: pointer;
        }

        h2 {
            text-align: center;
            margin-top: 0;
        }

        .paymentBox {
            width: 350px;
            min-height: 55%;
            background-color: #241253;
            color: #E8E9DE;
            border: 1px solid #ccc;
            border-radius: 20px;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .paymentBox h2 {
            width: 100%;
        }

        .bookingSummary h3 {
            margin-top: 10px;
            margin-bottom: 15px;
            border-bottom: 1px dashed rgba(232, 233, 222, 0.3);
            padding-bottom: 5px;
        }

        .bookingSummary p {
            font-size: 0.9rem;
            margin: 8px 0;
        }

        #totalAmount {
            font-weight: bold;
            font-size: 1.4rem;
        }

        #payBtn,
        #pay,
        #payLaterBtn {
            background-color: #E8E9DE;
            color: #241253;
            width: 100%;
            padding: 12px;
            border-style: none;
            border-radius: 25px;
            font-weight: bold;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        #payBtn:hover,
        #pay:hover,
        #payLaterBtn:hover {
            background-color: #d6d7ca;
            cursor: pointer;
            transform: translateY(-2px);
        }

        .totalSection {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            padding-top: 20px;
        }

        .totalSection p {
            margin: 5px 0;
        }

        #payBtn {
            margin-top: 10px;
        }

        #payMethod {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            width: 100%;
        }

        #payMethod.show {
            opacity: 1;
            max-height: 350px;
            transform: translateY(0);
            margin-top: 15px;
            border-top: 1px dashed rgba(232, 233, 222, 0.3);
            padding-top: 15px;
        }

        .radio-group {
            margin-bottom: 8px;
        }

        .radio-group label {
            cursor: pointer;
            font-size: 0.95rem;
            padding-left: 5px;
        }

        .radio-group input[type="radio"] {
            cursor: pointer;
        }

        /* Flexbox for Button Alignments */
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            width: 100%;
        }

        #payLaterBtn {
            background-color: transparent;
            color: #E8E9DE;
            border: 2px solid #E8E9DE;
        }

        #payLaterBtn:hover {
            background-color: rgba(232, 233, 222, 0.1);
        }

        .popupOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .popupOverlay.show {
            opacity: 1;
            visibility: visible;
        }

        .popupBox {
            background: white;
            color: #241253;
            padding: 30px;
            border-radius: 20px;
            width: 320px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: scale(0.8);
            transition: all 0.3s ease;
        }

        .popupOverlay.show .popupBox {
            transform: scale(1);
        }

        #home {
            margin-top: 15px;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            background: #241253;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }
        
        #home:hover {
            background: #391e82;
        }
    </style>
</head>

<body>

    <div id="wrapper">
        
    </div>

    <div id="body2">
        <div class="paymentBox">
            <h2>Payment</h2>

            <div class="bookingSummary">
                <h3>Booking Summary</h3>
                <p>
                    Booking ID: 
                    <span>#<?php echo htmlspecialchars($booking_id); ?></span>
                </p>
                <p>
                    Total Item:
                    <span id="totalItem"><?php echo isset($bookingData['Total_Items']) ? (int)$bookingData['Total_Items'] : 0; ?></span>
                </p>
                <p>
                    Drop-off Date:
                    <span id="dropOff_Date"><?php echo htmlspecialchars($bookingData['DropOff_Date'] ?? 'N/A'); ?></span>
                </p>
                <p>
                    Pick-up Date:
                    <span id="pickUp_Date"><?php echo htmlspecialchars($bookingData['Pickup_Date'] ?? 'N/A'); ?></span>
                </p>
                <p>
                    Residential College:
                    <span id="resCollege"><?php echo htmlspecialchars($bookingData['Residential_Block'] ?? 'Unassigned'); ?></span>
                </p>
            </div>

            <form style="width: 100%; display: contents;" method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" id="paymentForm">
                <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking_id); ?>">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                <input type="hidden" name="action" id="formAction" value="pay_now">

                <div class="totalSection">
                    <p>Total:</p>
                    <p><span id="totalAmount">RM <?php echo number_format((float)$amount, 2); ?></span></p>
                    <button type="button" id="payBtn" onclick="toggleMenu()">Select Payment Method</button>
                </div>
                
                <div id="payMethod">
                    <p style="margin-top: 0; margin-bottom: 12px;">Select your payment method:</p>

                    <div class="radio-group">
                        <input type="radio" id="banking" name="payment_method" value="Online Banking" checked>
                        <label for="banking">Online Banking</label>
                    </div>

                    <div class="radio-group">
                        <input type="radio" id="card" name="payment_method" value="Credit/Debit Card">
                        <label for="card">Credit/Debit Card</label>
                    </div>

                    <div class="radio-group">
                        <input type="radio" id="qr" name="payment_method" value="QR">
                        <label for="qr">QR</label>
                    </div>

                    <div class="button-group">
                        <button type="submit" id="pay" onclick="setAction('pay_now')">Pay Now</button>
                        <button type="submit" id="payLaterBtn" onclick="setAction('pay_later')">Pay Later</button>
                    </div>
                </div>
            </form>
        </div>

        <div id="payPopup" class="popupOverlay <?php echo ($paymentSuccess || $payLaterSuccess) ? 'show' : ''; ?>">
            <div class="popupBox">
                <?php if ($paymentSuccess): ?>
                    <p>Payment of <strong>RM <?php echo number_format((float)$amount, 2); ?></strong> successful!</p>
                    <p style="color:#198754; font-size:0.9rem; margin-top:8px;">Thank you! Your storage booking is now fully confirmed.</p>
                <?php else: ?>
                    <p>Booking saved! You can pay or cancel anytime from your dashboard.</p>
                <?php endif; ?>
                <button type="button" id="home" onclick="window.location.href='mainStatus.php'">
                    Go back home
                </button>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            document.getElementById("payMethod").classList.toggle("show");
        }

        // Sets the action input value based on which button is clicked
        function setAction(actionValue) {
            document.getElementById("formAction").value = actionValue;
        }

        document.getElementById("paymentForm").addEventListener("submit", function(e) {
            const currentAction = document.getElementById("formAction").value;
            
            if(currentAction === 'pay_now') {
                const payButton = document.getElementById('pay');
                payButton.textContent = 'Processing...';
                payButton.disabled = true;
            } else {
                const payLaterButton = document.getElementById('payLaterBtn');
                payLaterButton.textContent = 'Saving...';
                payLaterButton.disabled = true;
            }
        });
    </script>
</body>
</html>