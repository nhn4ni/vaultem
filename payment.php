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
// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

// Get booking details from URL
$booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : (isset($_POST['booking_id']) ? $_POST['booking_id'] : '');
$amount = isset($_GET['amount']) ? $_GET['amount'] : (isset($_POST['amount']) ? $_POST['amount'] : 0);

// If no booking ID, redirect back
if (empty($booking_id)) {
    header('Location: mainStatus.php');
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "utem_accommodation");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify booking exists and belongs to the student
$student_id = $_SESSION['student_id'];
$verifyStmt = $conn->prepare("
    SELECT b.Booking_ID, b.Booking_Status, p.Payment_Status, p.Amount 
    FROM booking b 
    LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID 
    WHERE b.Booking_ID = ? AND b.Student_ID = ?
");
$verifyStmt->bind_param("ss", $booking_id, $student_id);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    die("Invalid booking or unauthorized access.");
}

$bookingData = $verifyResult->fetch_assoc();

// Check if already paid
if ($bookingData['Payment_Status'] === 'Paid' || $bookingData['Payment_Status'] === 'P') {
    ?>
    <script>
        alert("This booking has already been paid.");
        window.location.href = "mainStatus.php";
    </script>
    <?php
    exit();
}

// Check if booking is pending
if ($bookingData['Booking_Status'] !== 'Pending') {
    ?>
    <script>
        alert("This booking cannot be paid for. Status: <?php echo $bookingData['Booking_Status']; ?>");
        window.location.href = "mainStatus.php";
    </script>
    <?php
    exit();
}

$verifyStmt->close();

// Process payment if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // YOUR EXISTING PAYMENT CODE
    $method = $_POST['payment_method'];
    $amount = $_POST['amount'];
    $bookingID = $_POST['booking_id'];
    $status = "P";
    $date = date("Y-m-d");

    // Check if payment already exists
    $checkStmt = $conn->prepare("SELECT Payment_ID FROM payment WHERE Booking_ID = ?");
    $checkStmt->bind_param("s", $bookingID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing payment
        $stmt = $conn->prepare("UPDATE payment SET Payment_Method = ?, Payment_Status = ?, Payment_Date = ?, Amount = ? WHERE Booking_ID = ?");
        $stmt->bind_param("sssdi", $method, $status, $date, $amount, $bookingID);
    } else {
        // Insert new payment
        $stmt = $conn->prepare("INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdi", $method, $status, $date, $amount, $bookingID);
    }
    
    if ($stmt->execute()) {
        // Update booking status to "Approved" after successful payment
        $updateBooking = $conn->prepare("UPDATE booking SET Booking_Status = 'Approved' WHERE Booking_ID = ?");
        $updateBooking->bind_param("s", $bookingID);
        $updateBooking->execute();
        $updateBooking->close();
        
        ?>
        <script>
            alert("Payment Successful");
            window.location.href = "mainStatus.php";
        </script>
        <?php
    } else {
        echo "Error: " . $stmt->error;
    }
    
    $stmt->close();
    $checkStmt->close();
    $conn->close();
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Payment</title>
    <style>
        * {
            font-family: 'Courier New', Courier, monospace;
            box-sizing: border-box;
        }

        body {
            background-color: #E8E9DE;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        h1 {
            color: #241253;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #241253;
            padding-bottom: 15px;
        }

        .booking-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .booking-summary p {
            margin: 8px 0;
            color: #333;
        }

        .booking-summary .amount {
            font-size: 2rem;
            font-weight: bold;
            color: #241253;
            text-align: center;
            margin: 15px 0 5px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        select, input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
            background: white;
        }

        select:focus, input[type="text"]:focus {
            border-color: #241253;
            outline: none;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }

        .payment-method {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .payment-method:hover {
            border-color: #241253;
            transform: scale(1.02);
        }

        .payment-method.selected {
            border-color: #241253;
            background: #f0edf6;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method .method-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 5px;
        }

        .payment-method .method-name {
            font-weight: bold;
            color: #333;
        }

        .btn-pay {
            background: #241253;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-pay:hover {
            background: #3a1f7a;
            transform: scale(1.02);
        }

        .btn-pay:active {
            transform: scale(0.98);
        }

        .btn-pay:disabled {
            background: #999;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        .secure-badge {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.9rem;
        }

        .secure-badge span {
            font-size: 1.2rem;
        }

        @media (max-width: 480px) {
            .payment-container {
                padding: 20px;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>Payment</h1>
        
        <div class="booking-summary">
            <p><strong>Booking ID:</strong> #<?php echo htmlspecialchars($booking_id); ?></p>
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>
            <div class="amount">
                RM <?php echo number_format((float)$amount, 2); ?>
            </div>
            <p style="text-align: center; color: #666; font-size: 0.9rem;">Amount to be paid</p>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return validatePayment()">
            <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking_id); ?>">
            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">

            <div class="form-group">
                <label>Select Payment Method</label>
                <div class="payment-methods">
                    <label class="payment-method selected" onclick="selectMethod(this)">
                        <input type="radio" name="payment_method" value="Online Banking" checked>
                        <span class="method-icon">Bank</span>
                        <span class="method-name">Online Banking</span>
                    </label>
                    <label class="payment-method" onclick="selectMethod(this)">
                        <input type="radio" name="payment_method" value="Credit Card">
                        <span class="method-icon">Card</span>
                        <span class="method-name">Credit Card</span>
                    </label>
                    <label class="payment-method" onclick="selectMethod(this)">
                        <input type="radio" name="payment_method" value="E-Wallet">
                        <span class="method-icon">Wallet</span>
                        <span class="method-name">E-Wallet</span>
                    </label>
                    <label class="payment-method" onclick="selectMethod(this)">
                        <input type="radio" name="payment_method" value="Cash">
                        <span class="method-icon">Cash</span>
                        <span class="method-name">Cash</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-pay" id="payBtn">
                Pay Now
            </button>
            
            <button type="button" class="btn-cancel" onclick="window.location.href='mainStatus.php'">
                Cancel
            </button>
        </form>

        <div class="secure-badge">
            <span>Secure</span> Payment
        </div>
    </div>

    <script>
        function selectMethod(element) {
            document.querySelectorAll('.payment-method').forEach(function(method) {
                method.classList.remove('selected');
            });
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
        }

        function validatePayment() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!selectedMethod) {
                alert('Please select a payment method.');
                return false;
            }
            
            const payBtn = document.getElementById('payBtn');
            payBtn.textContent = 'Processing...';
            payBtn.disabled = true;
            
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const firstMethod = document.querySelector('.payment-method');
            if (firstMethod) {
                firstMethod.classList.add('selected');
            }
        });
    </script>
</body>
</html>