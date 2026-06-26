<?php
session_start();

// ── Auth guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['Student_ID'])) {
    header("Location: studentMAIN.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// ── Handle Booking Cancellation (Permanent Removal) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $cancelID = intval($_POST['cancel_booking_id']);
    $student_id = $_SESSION['Student_ID'];

    // Updated verification layout: Allows removal of both entirely unpaid or "Pay Later" records
    $verifySql = "SELECT b.Booking_ID FROM booking b 
                  LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID
                  WHERE b.Booking_ID = ? 
                    AND b.Student_ID = ? 
                    AND LOWER(b.Booking_Status) = 'pending' 
                    AND (p.Payment_Status IS NULL OR (LOWER(p.Payment_Status) != 'y' AND LOWER(p.Payment_Status) != 'paid'))";
                    
    $verifyStmt = $conn->prepare($verifySql);
    $verifyStmt->bind_param("is", $cancelID, $student_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows > 0) {
        // Get total items + space being cancelled
$getItems = $conn->prepare("SELECT SUM(Quantity) AS total, Space_ID FROM item WHERE Booking_ID = ? GROUP BY Space_ID");
$getItems->bind_param("i", $cancelID);
$getItems->execute();
$itemsResult = $getItems->get_result();
if ($itemsRow = $itemsResult->fetch_assoc()) {
    $cancelledQty = (int)$itemsRow['total'];
    $spaceID      = (int)$itemsRow['Space_ID'];

    // Restore units back to storespace
    $restoreSpace = $conn->prepare("UPDATE storespace SET Size = Size + ? WHERE Space_ID = ?");
    $restoreSpace->bind_param("ii", $cancelledQty, $spaceID);
    $restoreSpace->execute();
    $restoreSpace->close();
}
$getItems->close();
        // 2. Delete any records inside 'payment' linked to this booking
        $deletePay = $conn->prepare("DELETE FROM payment WHERE Booking_ID = ?");
        $deletePay->bind_param("i", $cancelID);
        $deletePay->execute();
        $deletePay->close();

        // 3. Delete any records inside 'item' linked to this booking
        $deleteItems = $conn->prepare("DELETE FROM item WHERE Booking_ID = ?");
        $deleteItems->bind_param("i", $cancelID);
        $deleteItems->execute();
        $deleteItems->close();

        // 4. Safely delete the parent row from 'booking'
        $deleteBooking = $conn->prepare("DELETE FROM booking WHERE Booking_ID = ?");
        $deleteBooking->bind_param("i", $cancelID);
        $deleteBooking->execute();
        $deleteBooking->close();
        
        header("Location: mainStatus.php" . (isset($_GET['sort']) ? "?sort=" . $_GET['sort'] : ""));
        exit();
    }
    $verifyStmt->close();
}

// ── Sort order ───────────────────────────────────────────────────────────────
$order = (isset($_GET['sort']) && $_GET['sort'] === 'old') ? 'ASC' : 'DESC';

// ── Fetch bookings for the logged-in student only ────────────────────────────
$student_id   = $_SESSION['Student_ID'];
$studentIdEsc = $conn->real_escape_string($student_id);

$sql = "
    SELECT
        b.Booking_ID,
        b.DropOff_Date,
        b.Pickup_Date,
        b.Booking_Status,
        b.Booking_Priority,
        s.Student_Name,
        rc.Residential_Block,
        SUM(i.Quantity)            AS TotalItem,
        SUM(i.Quantity * i.Price)  AS TotalFee
    FROM booking b
    LEFT JOIN student s              ON b.Student_ID     = s.Student_ID
    LEFT JOIN item i                 ON b.Booking_ID     = i.Booking_ID
    LEFT JOIN storespace ss          ON i.Space_ID       = ss.Space_ID
    LEFT JOIN residential_college rc ON ss.Residential_ID = rc.Residential_ID
    WHERE b.Student_ID = '$studentIdEsc'
    GROUP BY
        b.Booking_ID, b.DropOff_Date, b.Pickup_Date,
        b.Booking_Status, b.Booking_Priority,
        s.Student_Name, rc.Residential_Block
    ORDER BY b.Booking_ID $order
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .status-card {
            position: relative;
        }

        /* Minimalist 'X' Cancel Button */
        .small-cancel-btn {
            position: absolute;
            top: 12px;
            right: 15px;
            background: none;
            border: none;
            color: #dc3545;
            font-size: 1.4rem;
            font-weight: bold;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            transition: transform 0.2s ease, color 0.2s ease;
            z-index: 5;
        }
        .small-cancel-btn:hover {
            color: #bd2130;
            transform: scale(1.2);
        }

        .pay-btn {
            background-color: #4CAF50; color: white; border: none; border-radius: 15px;
            padding: 8px 20px; font-weight: bold; cursor: pointer;
            transition: all 0.3s ease; font-size: 0.9rem; margin-left: 10px;
        }
        .pay-btn:hover { background-color: #45a049; transform: scale(1.05); }
        .pay-btn:active { transform: scale(0.95); }
        .pay-btn.paid { background-color: #808080; cursor: not-allowed; opacity: 0.6; }
        .pay-btn.paid:hover { transform: none; }
        
        .status-text { display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 0.85rem; }
        .status-pending  { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .header-main    { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; padding-right: 25px; } 
        .header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .priority-badge {
            background-color: #dc3545; color: white; font-size: 0.75rem;
            padding: 2px 8px; border-radius: 10px; font-weight: bold;
        }
        
        #profileContainer {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        #userImage {
            vertical-align: middle;
            margin-left: 5px;
        }
        #profileSelect {
            display: none;
            position: absolute;
            right: 0;
            top: 25px;
            background-color: #241253;
            border: 1px solid #E8E9DE;
            border-radius: 8px;
            min-width: 130px;
            z-index: 10;
        }
        #profileSelect.show {
            display: flex;
            flex-direction: column;
        }
        #profileSelect button {
            background: none;
            border: none;
            color: #E8E9DE;
            padding: 10px;
            text-align: left;
            width: 100%;
            cursor: pointer;
            font-size: 0.85rem;
        }
        #profileSelect button:hover {
            background-color: rgba(232, 233, 222, 0.2);
        }

        @media (max-width: 768px) {
            .header-main    { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; justify-content: space-between; }
            .pay-btn        { margin-left: 0; }
        }

        .pickup-note {
        margin-top: 5px;
        font-size: 0.85rem;
        color: #dc3545;
        font-weight: bold;
        }
    </style>
</head>
<body>
<div id="wrapper">

    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='mainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='form.php'">Book space</button>
    </div>

    <div class="rightcontainer">
        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo isset($_SESSION['Student_Name']) ? htmlspecialchars($_SESSION['Student_Name']) : 'Guest'; ?></span>

            <span id="profileContainer">
                <img id="userImage" src="/image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showProfile();">Profile</button>
                    <button onclick="window.location.href='settings.html'">Settings</button>
                    <button onclick="window.location.href='settings.html'">Notification</button>
                    <button onclick="showLog();">Logout</button>
                </div>
            </span>
        </div>

        <h1>Status</h1>

        <div class="filter-wrapper">
            <button id="filterBtn" data-order="<?php echo ($order === 'ASC') ? 'old' : 'recent'; ?>">
                <span id="filterLabel"><?php echo ($order === 'ASC') ? 'Old to Recent' : 'Recent to Old'; ?></span>
                <span class="arrow">&#9662;</span>
            </button>
        </div>

        <?php if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $bookingID     = $row['Booking_ID'];
                $bookingStatus = $row['Booking_Status'];
                $isPriority    = ($row['Booking_Priority'] === 'Y');

                // Items
                $itemStmt = $conn->prepare("SELECT Item_Name, Quantity FROM item WHERE Booking_ID = ?");
                $itemStmt->bind_param("i", $bookingID);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();

                // Payment
                $payStmt = $conn->prepare("SELECT Payment_Status, Amount FROM payment WHERE Booking_ID = ?");
                $payStmt->bind_param("i", $bookingID);
                $payStmt->execute();
                $payResult    = $payStmt->get_result();
                $paymentRow   = $payResult->fetch_assoc();
                
                $paymentStatus = $paymentRow['Payment_Status'] ?? 'N';
                $totalAmount   = $paymentRow['Amount'] ?? $row['TotalFee'] ?? 0;
                $payStmt->close();

                // Normalize checks to capture both options ('Y' / 'Paid') vs ('P' / 'Pending' / 'N')
                $isPaid = (strtolower($paymentStatus) === 'y' || strtolower($paymentStatus) === 'paid');
        ?>
        <div class="status-card">
            <?php if (strtolower($bookingStatus) === 'pending' && !$isPaid): ?>
                <button class="small-cancel-btn" title="Cancel Booking" onclick="confirmCancellation(<?php echo $bookingID; ?>)">&times;</button>
            <?php endif; ?>

            <div class="card-header">
                <div class="header-main">
                    <span class="order-id">ID: <?php echo htmlspecialchars($bookingID); ?></span>
                    <?php if ($isPriority): ?>
                        <span class="priority-badge">EMERGENCY</span>
                    <?php endif; ?>
                    <div class="header-actions">
                        <span class="status-text <?php
                            echo strtolower($bookingStatus) === 'pending'  ? 'status-pending'  :
                                (strtolower($bookingStatus) === 'approved' ? 'status-approved' : 'status-rejected');
                        ?>">
                            <?php echo htmlspecialchars($bookingStatus); ?>
                        </span>

                        <?php if (strtolower($bookingStatus) === 'pending' && !$isPaid): ?>
                            <button class="pay-btn"
                                    onclick="redirectToPayment('<?php echo $bookingID; ?>', <?php echo (float)$totalAmount; ?>)">
                                Pay Now
                            </button>
                        <?php elseif ($isPaid): ?>
                            <button class="pay-btn paid" disabled>Paid</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="summary-info">
                <p>Drop-off date : <?php echo date("d-m-Y", strtotime($row['DropOff_Date'])); ?></p>
                <p>Pick-up date  : <?php echo date("d-m-Y", strtotime($row['Pickup_Date'])); ?></p>                <p>Item quantity : <?php echo htmlspecialchars($row['TotalItem'] ?? '0'); ?></p>
                <p>College       : <?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></p>
                <p>Total fee     : RM <?php echo number_format((float)($row['TotalFee'] ?? 0), 2); ?></p>
                <?php if ($paymentRow): ?>
                    <p>Payment status: <?php echo $isPaid ? 'Paid' : (($paymentStatus === 'P' || strtolower($paymentStatus) === 'pending') ? 'Pending Payment (Pay Later)' : 'Unpaid'); ?></p>
                <?php endif; ?>

                <p class="pickup-note">
                Note: Pickup time is 8:00 AM - 11:00 PM only
</p>
            </div>

            <div class="button-container">
                <button class="viewDetailsBtn">
                    <span class="btn-text">View details</span>
                    <span class="btn-arrow">&#9662;</span>
                </button>
            </div>

            <div class="card-details">
                <div class="details-content">
                    <div class="detailsHeader">
                        <span>Item details</span><span>Quantity</span>
                    </div>
                    <?php while ($item = $itemResult->fetch_assoc()): ?>
                    <div class="itemRow">
                        <span><?php echo htmlspecialchars($item['Item_Name']); ?></span>
                        <span class="lineSpacer"></span>
                        <span><?php echo htmlspecialchars($item['Quantity']); ?></span>
                    </div>
                    <?php endwhile; $itemStmt->close(); ?>
                </div>
            </div>
        </div>
        <?php endwhile;
        else: ?>
            <p>No bookings found. <a href="form.php">Make your first booking!</a></p>
        <?php endif; ?>

    </div>
</div>

<form id="cancelForm" method="POST" action="">
    <input type="hidden" id="cancel_booking_id" name="cancel_booking_id" value="">
</form>

<div id="logoutPopup" class="hidden">
    <div id="logoutText">
        <p>Are you sure you want to logout?</p>
        <div id="logoutButton">
            <button id="yesBTN" onclick="window.location.href='studentMAIN.html'">Yes</button>
            <button id="noBTN"  onclick="showLog()">No</button>
        </div>
    </div>
</div>

<div id="profilePopup" class="hidden">
    <div id="profileShortDetails">
        <h3>Profile</h3>
        <p>Name  : <span><?php echo isset($_SESSION['Student_Name']) ? htmlspecialchars($_SESSION['Student_Name']) : ''; ?></span></p>
        <p>Email : <span><?php echo isset($_SESSION['Student_Mail'])     ? htmlspecialchars($_SESSION['Student_Mail'])     : ''; ?></span></p>
        <div id="profileBTN">
            <button id="close" onclick="showProfile()">Close</button>
        </div>
    </div>
</div>

<script>
    function confirmCancellation(bookingId) {
        if (confirm("Are you sure you want to permanently cancel and remove booking ID #" + bookingId + "? This cannot be undone.")) {
            document.getElementById('cancel_booking_id').value = bookingId;
            document.getElementById('cancelForm').submit();
        }
    }

    function profileMenu() {
        document.getElementById('profileSelect').classList.toggle('show');
    }

    function showProfile() { 
        document.getElementById('profilePopup').classList.toggle('hidden'); 
    }

    function showLog() { 
        document.getElementById('logoutPopup').classList.toggle('hidden'); 
    }

    function redirectToPayment(bookingId, amount) {
        window.location.href = 'payment.php?booking_id=' + bookingId + '&amount=' + amount;
    }

    const filterBtn   = document.getElementById('filterBtn');
    const filterLabel = document.getElementById('filterLabel');

    filterBtn.addEventListener('click', function() {
        let current = filterBtn.getAttribute('data-order');
        window.location.href = 'mainStatus.php?sort=' + (current === 'recent' ? 'old' : 'recent');
    });

    document.querySelectorAll('.viewDetailsBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            let card  = btn.closest('.status-card');
            let panel = card.querySelector('.card-details');
            let text  = btn.querySelector('.btn-text');
            if (panel) {
                panel.classList.toggle('open');
                btn.classList.toggle('active');
                text.textContent = panel.classList.contains('open') ? 'Hide details' : 'View details';
            }
        });
    });
</script>
</body>
</html>
<?php $conn->close(); ?>