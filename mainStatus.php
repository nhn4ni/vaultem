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

require_once 'autoCancelExpired.php';
autoCancelExpiredBookings($conn);

// ── Ensure Student_Mail is in session ──────────────────────────────────────
if (!isset($_SESSION['Student_Mail']) || empty($_SESSION['Student_Mail'])) {
    $student_id = $_SESSION['Student_ID'];
    $emailQuery = "SELECT Student_Mail FROM student WHERE Student_ID = ?";
    $emailStmt = $conn->prepare($emailQuery);
    $emailStmt->bind_param("s", $student_id);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    
    if ($emailRow = $emailResult->fetch_assoc()) {
        $_SESSION['Student_Mail'] = $emailRow['Student_Mail'];
    }
    $emailStmt->close();
}



// ── Handle Booking Cancellation (Permanent Removal) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $cancelID = intval($_POST['cancel_booking_id']);
    $student_id = $_SESSION['Student_ID'];

    // Allows removal of both entirely unpaid or "Pay Later" records
    $verifySql = "SELECT b.Booking_ID FROM booking b 
                  LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID
                  WHERE b.Booking_ID = ? 
                    AND b.Student_ID = ? 
                    AND LOWER(b.Booking_Status) = 'pending' 
                    AND (p.Payment_Status IS NULL OR UPPER(p.Payment_Status) != 'Y')";
                    
    $verifyStmt = $conn->prepare($verifySql);
    $verifyStmt->bind_param("is", $cancelID, $student_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows > 0) {

        $getItems = $conn->prepare("SELECT SUM(Quantity) AS total, Space_ID FROM item WHERE Booking_ID = ? GROUP BY Space_ID");
        $getItems->bind_param("i", $cancelID);
        $getItems->execute();
        $itemsResult = $getItems->get_result();
        while ($itemsRow = $itemsResult->fetch_assoc()) {
            $cancelledQty = (int)$itemsRow['total'];
            $spaceID      = (int)$itemsRow['Space_ID'];

            // Restore units back to storespace
            $restoreSpace = $conn->prepare("UPDATE storespace SET Size = Size + ? WHERE Space_ID = ?");
            $restoreSpace->bind_param("ii", $cancelledQty, $spaceID);
            $restoreSpace->execute();
            $restoreSpace->close();
        }
        $getItems->close();

        // Delete records inside 'payment' linked to this booking
        $deletePay = $conn->prepare("DELETE FROM payment WHERE Booking_ID = ?");
        $deletePay->bind_param("i", $cancelID);
        $deletePay->execute();
        $deletePay->close();

        // Delete records inside 'item' linked to this booking
        $deleteItems = $conn->prepare("DELETE FROM item WHERE Booking_ID = ?");
        $deleteItems->bind_param("i", $cancelID);
        $deleteItems->execute();
        $deleteItems->close();

        // Safely delete the parent row from 'booking'
        $deleteBooking = $conn->prepare("DELETE FROM booking WHERE Booking_ID = ?");
        $deleteBooking->bind_param("i", $cancelID);
        $deleteBooking->execute();
        $deleteBooking->close();
        
        header("Location: mainStatus.php" . (isset($_GET['sort']) ? "?sort=" . $_GET['sort'] : ""));
        exit();
    }
    $verifyStmt->close();
}

// ── Handle Drop-off Photo Confirmation (student verifies staff's uploaded photo) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dropoff_confirm_id'])) {
    $confirmID = intval($_POST['dropoff_confirm_id']);
    $decision  = ($_POST['dropoff_decision'] ?? '') === 'yes' ? 'Confirmed' : 'Rejected';
    $student_id = $_SESSION['Student_ID'];

    $stmt = $conn->prepare("UPDATE booking SET Dropoff_Status = ? WHERE Booking_ID = ? AND Student_ID = ? AND Dropoff_Status = 'Pending'");
    $stmt->bind_param("sis", $decision, $confirmID, $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: mainStatus.php" . (isset($_GET['sort']) ? "?sort=" . $_GET['sort'] : ""));
    exit();
}

// ── Handle Final Collection Confirmation (student confirms items were physically collected) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collected_confirm_id'])) {
    $confirmID  = intval($_POST['collected_confirm_id']);
    $student_id = $_SESSION['Student_ID'];

    $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Collected' WHERE Booking_ID = ? AND Student_ID = ? AND Booking_Status = 'Released'");
    $stmt->bind_param("is", $confirmID, $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: mainStatus.php" . (isset($_GET['sort']) ? "?sort=" . $_GET['sort'] : ""));
    exit();
}

// ── Student ID (needed for all queries below) ─────────────────────────────────
$student_id   = $_SESSION['Student_ID'];
$studentIdEsc = $conn->real_escape_string($student_id);

// ── Notification count: things needing the student's attention ───────────────
$notifCountRes = $conn->query("
    SELECT COUNT(*) AS c FROM booking
    WHERE Student_ID = '$studentIdEsc'
      AND (
            LOWER(Booking_Status) = 'verification_sent'
            OR Dropoff_Status = 'Pending'
          )
");
$notifCount = $notifCountRes ? (int)$notifCountRes->fetch_assoc()['c'] : 0;

// ── Count specifically for pickup verification requests (drives the login popup) ──
$verifyNoticeRes = $conn->query("
    SELECT COUNT(*) AS c FROM booking
    WHERE Student_ID = '$studentIdEsc'
      AND LOWER(Booking_Status) = 'verification_sent'
");
$verifyNoticeCount = $verifyNoticeRes ? (int)$verifyNoticeRes->fetch_assoc()['c'] : 0;

// ── Check if student has an active booking ────────────────────────────────────
// Active = any booking not yet fully closed out (not rejected, collected, cancelled, waived, or settled)
// A booking stays "active" even after its pickup date passes, until the student actually collects their items.
$activeCheck = $conn->query("
    SELECT COUNT(*) AS c FROM booking
    WHERE Student_ID = '$studentIdEsc'
      AND LOWER(Booking_Status) NOT IN ('rejected', 'collected', 'cancelled_unpaid', 'cancelled', 'waived', 'settled')
");
$hasActiveBooking = ($activeCheck && $activeCheck->fetch_assoc()['c'] > 0);

// ── Sort order ───────────────────────────────────────────────────────────────
$order = (isset($_GET['sort']) && $_GET['sort'] === 'old') ? 'ASC' : 'DESC';

// ── Fetch bookings for the logged-in student only ────────────────────────────

$sql = "
    SELECT
        b.Booking_ID,
        b.DropOff_Date,
        b.Pickup_Date,
        b.Booking_Status,
        b.Booking_Priority,
        b.Rejection_Reason,
        b.Rejection_Photo,
        b.Dropoff_Photo,
        b.Dropoff_Status,
        s.Student_Name,
        rc.Residential_Block,
        IFNULL((SELECT SUM(Quantity) FROM item WHERE Booking_ID = b.Booking_ID), 0) AS TotalItem,
        IFNULL((SELECT SUM(Quantity * Price) FROM item WHERE Booking_ID = b.Booking_ID), 0) AS TotalFee
    FROM booking b
    LEFT JOIN student s              ON b.Student_ID     = s.Student_ID
    LEFT JOIN item i                 ON b.Booking_ID     = i.Booking_ID
    LEFT JOIN storespace ss          ON i.Space_ID       = ss.Space_ID
    LEFT JOIN residential_college rc ON ss.Residential_ID = rc.Residential_ID
    WHERE b.Student_ID = '$studentIdEsc'
    GROUP BY
        b.Booking_ID, b.DropOff_Date, b.Pickup_Date,
        b.Booking_Status, b.Booking_Priority, b.Rejection_Reason, b.Rejection_Photo,
        b.Dropoff_Photo, b.Dropoff_Status,
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
    <title>VaulteM - Home</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .status-card {
            position: relative;
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
        .status-cancelled { background-color: #e2e3e5; color: #495057; }
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

        .small-cancel-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
            margin-top: 8px;
            display: inline-block;
        }
        .small-cancel-btn:hover { background-color: #bd2130; }
        .small-cancel-btn:active { transform: scale(0.95); }

        .first-booking-link {
            color: #209708;
            font-weight: bold;
            text-decoration: underline;
        }
        .first-booking-link:hover {
            color: #209708;
        }

        /* ── Review / Rejection notes ── */
        .review-note {
            margin-top: 10px;
            background: #fff3cd;
            color: #856404;
            border: 1px solid rgba(133,100,4,0.25);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
        }
        .rejection-note {
            margin-top: 10px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid rgba(114,28,36,0.25);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
        }
        .cancelled-note {
            margin-top: 10px;
            background: #e2e3e5;
            color: #495057;
            border: 1px solid rgba(73,80,87,0.25);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
        }

        /* ── Drop-off photo confirmation ── */
        .dropoff-confirm-box {
            margin-top: 10px;
            background: #fff3cd;
            border: 1px solid rgba(133,100,4,0.25);
            border-radius: 10px;
            padding: 12px 14px;
        }
        .dropoff-confirm-box p {
            margin: 0 0 8px;
            font-weight: 600;
            color: #856404;
            font-size: 0.85rem;
        }
        .dropoff-confirm-box img {
            max-width: 220px;
            border-radius: 10px;
            display: block;
            margin-bottom: 10px;
        }
        .confirm-yes-btn {
            background-color: #4CAF50; color: white; border: none; border-radius: 15px;
            padding: 8px 20px; font-weight: bold; cursor: pointer; font-size: 0.85rem;
        }
        .confirm-yes-btn:hover { background-color: #45a049; }
        .confirm-no-btn {
            background-color: #dc3545; color: white; border: none; border-radius: 15px;
            padding: 8px 20px; font-weight: bold; cursor: pointer; font-size: 0.85rem;
            margin-left: 8px;
        }
        .confirm-no-btn:hover { background-color: #bd2130; }

        /* ── Notification badge (WhatsApp-style unread indicator) ── */
        .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #dc3545;
            color: #fff;
            font-size: 0.62rem;
            font-weight: 800;
            min-width: 15px;
            height: 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
            box-shadow: 0 0 0 2px #241253;
        }
        .notif-badge-inline {
            background: #dc3545;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            border-radius: 10px;
            padding: 1px 7px;
            margin-left: 8px;
        }
        #profileSelect button {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .verif-action-note {
            margin-top: 10px;
            background: #cfe2ff;
            color: #084298;
            border: 1px solid rgba(8,66,152,0.3);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-verification_sent { background-color: #cfe2ff; color: #084298; }

        /* ── Verification notice popup (shown automatically on login) ── */
        #verifyNotifyPopup {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            z-index: 300;
        }
        #verifyNotifyText {
            background-color: #f1f0ea;
            color: #1e1b4b;
            padding: 30px;
            border-radius: 20px;
            width: 320px;
            text-align: center;
        }
        #verifyNotifyButtons {
            margin-top: 18px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        #verifyNotifyButtons button {
            padding: 10px 22px;
            border: none;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
        }
        #verifyGoBTN { background: #084298; color: #fff; }
        #verifyGoBTN:hover { background: #063170; }
        #verifyLaterBTN { background: #e2e3e5; color: #495057; }
        #verifyLaterBTN:hover { background: #d3d4d6; }
        
    </style>
</head>
<body>
<div id="wrapper">

    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='mainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking"
            <?php if ($hasActiveBooking): ?>
                disabled
                title="You already have a booking in progress. You can book again once your items are collected."
                style="opacity:0.45; cursor:not-allowed; transform:none;"
            <?php else: ?>
                onclick="window.location.href='form.php'"
            <?php endif; ?>>
            Book space
        </button>
        <?php if ($hasActiveBooking): ?>
            <p style="font-size:0.72rem; color:rgba(36,18,83,0.6); text-align:center; margin-top:6px; padding: 0 8px;">
                Available after your items are collected
            </p>
        <?php endif; ?>
    </div>

    <div class="rightcontainer">
        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo isset($_SESSION['Student_Name']) ? htmlspecialchars($_SESSION['Student_Name']) : 'Guest'; ?></span>

            <span id="profileContainer">
                <span style="position:relative; display:inline-block;">
                    <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                    <?php if ($notifCount > 0): ?>
                        <span class="notif-badge"><?php echo $notifCount; ?></span>
                    <?php endif; ?>
                </span>
                <div id="profileSelect">
                    <button onclick="showProfile();">Profile</button>
                    <button onclick="window.location.href='settings.php'">Settings</button>
                    <button onclick="window.location.href='studentverify.php'">
                        Notification
                        <?php if ($notifCount > 0): ?>
                            <span class="notif-badge-inline"><?php echo $notifCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <button onclick="showLog();">Logout</button>
                </div>
            </span>
        </div>

        <h1>Status</h1>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'already_booked'): ?>
        <div style="background:#fff3cd; color:#856404; border:1px solid rgba(245,158,11,0.4); border-radius:12px; padding:10px 16px; font-size:0.85rem; font-weight:600; margin-bottom:14px;">
            You already have a booking in progress. You can make a new booking once your items have been collected.
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'booking_submitted'): ?>
        <div style="background:#cfe2ff; color:#084298; border:1px solid rgba(8,66,152,0.3); border-radius:12px; padding:10px 16px; font-size:0.85rem; font-weight:600; margin-bottom:14px;">
            Your booking has been submitted and is now awaiting staff review. You'll be able to pay once it's approved.
        </div>
        <?php endif; ?>

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
                
                $paymentStatus = isset($paymentRow['Payment_Status']) ? trim(strtoupper($paymentRow['Payment_Status'])) : '';
                $totalAmount   = $paymentRow['Amount'] ?? $row['TotalFee'] ?? 0;
                $payStmt->close();

                // Y = paid, N = pay later (unpaid), '' = no payment record yet
                $isPaid     = ($paymentStatus === 'Y');
                $isPayLater = ($paymentStatus === 'N');
                
                // Helper variable to handle case-insensitive checks for button rendering
                $currentStatusLower = strtolower($bookingStatus);

                // Friendly label + badge class (handles the auto-cancelled state separately from staff rejection)
                $displayStatus = $bookingStatus;
                if ($currentStatusLower === 'cancelled_unpaid') {
                    $displayStatus = 'Cancelled (Unpaid)';
                    $statusBadgeClass = 'status-cancelled';
                } elseif ($currentStatusLower === 'pending') {
                    $statusBadgeClass = 'status-pending';
                } elseif ($currentStatusLower === 'approved') {
                    $statusBadgeClass = 'status-approved';
                } elseif ($currentStatusLower === 'verification_sent') {
                    $statusBadgeClass = 'status-verification_sent';
                } elseif ($currentStatusLower === 'confirmed') {
                    $statusBadgeClass = 'status-approved';
                } elseif ($currentStatusLower === 'released') {
                    $statusBadgeClass = 'status-verification_sent';
                } else {
                    $statusBadgeClass = 'status-rejected';
                }
        ?>
        <div class="status-card">
            <div class="card-header">
                <div class="header-main">
                    <span class="order-id">ID: <?php echo htmlspecialchars($bookingID); ?></span>
                    <?php if ($isPriority): ?>
                        <span class="priority-badge">EMERGENCY</span>
                    <?php endif; ?>
                    <div class="header-actions">
                        <span class="status-text <?php echo $statusBadgeClass; ?>">
                            <?php echo htmlspecialchars($displayStatus); ?>
                        </span>

                        <?php if ($isPaid): ?>
                            <button class="pay-btn paid" disabled> Paid</button>
                        <?php elseif ($currentStatusLower === 'approved'): ?>
                            <button class="pay-btn"
                                    onclick="redirectToPayment('<?php echo $bookingID; ?>', <?php echo (float)$totalAmount; ?>)">
                                Pay Now
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="summary-info">
                <p>Drop-off date : <?php echo htmlspecialchars($row['DropOff_Date']); ?></p>
                <p>Pick-up date  : <?php echo htmlspecialchars($row['Pickup_Date']); ?></p>
                <p>Item quantity : <?php echo htmlspecialchars($row['TotalItem'] ?? '0'); ?></p>
                <p>College       : <?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></p>
                <p>Total fee     : RM <?php echo number_format((float)$totalAmount, 2); ?></p>
                <?php if ($paymentRow): ?>
                    <p>Payment status:
                        <?php
                            if ($isPaid)         echo '<strong style="color:#198754;"> Paid</strong>';
                            elseif ($isPayLater)  echo '<span style="color:#856404;">Unpaid (Pay Later)</span>';
                            else                  echo '<span style="color:#856404;">Unpaid</span>';
                        ?>
                    </p>
                <?php else: ?>
                    <p>Payment status: <span style="color:#856404;">Unpaid</span></p>
                <?php endif; ?>

                <?php if ($currentStatusLower === 'pending'): ?>
                <?php elseif ($currentStatusLower === 'rejected'): ?>
                    <div class="rejection-note">
                        <strong>Rejected</strong>
                        <?php if (!empty($row['Rejection_Reason'])): ?>
                            : <?php echo htmlspecialchars($row['Rejection_Reason']); ?>
                        <?php else: ?>
                            . No reason was provided by staff.
                        <?php endif; ?>
                        <?php if (!empty($row['Rejection_Photo'])): ?>
                            <br>
                            <img src="<?php echo htmlspecialchars($row['Rejection_Photo']); ?>" alt="Rejection evidence"
                                 style="max-width:220px; border-radius:10px; display:block; margin-top:8px; border:1px solid rgba(114,28,36,0.25);">
                        <?php endif; ?>
                    </div>
                <?php elseif ($currentStatusLower === 'cancelled_unpaid'): ?>
                    <div class="cancelled-note">
                        <strong>Automatically Cancelled</strong> — payment wasn't completed before your chosen drop-off date, so this booking and its reserved slot were released.
                    </div>
                <?php elseif ($currentStatusLower === 'verification_sent'): ?>
                    <div class="verif-action-note">
                         Staff has sent a pickup verification request. Please check your Notification page to confirm.
                    </div>
                <?php elseif ($currentStatusLower === 'released'): ?>
                    <div class="verif-action-note" style="background:#d4edda; color:#155724; border-color:rgba(21,87,36,0.3);">
                         Staff has released your items. Please confirm once you have physically collected them.
                    </div>
                    <form method="POST" style="margin-top:8px;">
                        <input type="hidden" name="collected_confirm_id" value="<?php echo $bookingID; ?>">
                        <button type="submit" class="confirm-yes-btn"
                            onclick="return confirm('Confirm that you have collected your items for Booking #<?php echo $bookingID; ?>? This cannot be undone.')">
                            I've Collected My Items
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Drop-off photo verification / proof -->
                <?php if ($currentStatusLower !== 'collected' && !empty($row['Dropoff_Photo']) && ($row['Dropoff_Status'] ?? '') === 'Pending'): ?>
                <div class="dropoff-confirm-box">
                    <p>Staff has uploaded a photo of your dropped-off items. Is this yours?</p>
                    <img src="<?php echo htmlspecialchars($row['Dropoff_Photo']); ?>" alt="Drop-off photo">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="dropoff_confirm_id" value="<?php echo $bookingID; ?>">
                        <input type="hidden" name="dropoff_decision" value="yes">
                        <button type="submit" class="confirm-yes-btn">Yes, this is mine</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="dropoff_confirm_id" value="<?php echo $bookingID; ?>">
                        <input type="hidden" name="dropoff_decision" value="no">
                        <button type="submit" class="confirm-no-btn">No, not mine</button>
                    </form>
                </div>
                <?php elseif (!empty($row['Dropoff_Photo']) && ($row['Dropoff_Status'] ?? '') === 'Confirmed'): ?>
                <div class="dropoff-confirm-box" style="background:#d4edda; border-color:rgba(21,87,36,0.25);">
                    <p style="color:#155724;"> You confirmed this is your item. Kept here as proof until pickup.</p>
                    <img src="<?php echo htmlspecialchars($row['Dropoff_Photo']); ?>" alt="Drop-off photo">
                </div>
                <?php elseif (($row['Dropoff_Status'] ?? '') === 'Rejected'): ?>
                <div class="dropoff-confirm-box" style="background:#f8d7da; border-color:rgba(114,28,36,0.25);">
                    <p style="color:#721c24;">You reported a mismatch on the drop-off photo. Staff has been notified and will re-upload.</p>
                </div>
                <?php endif; ?>

                <p class="pickup-note">
                    Note: Drop-off and Pick-up time is 8:00 AM - 11:00 AM only
                </p>
                <?php if ($currentStatusLower === 'pending' && !$isPaid): ?>
                    <button class="small-cancel-btn" onclick="confirmCancellation(<?php echo $bookingID; ?>)">Cancel Booking</button>
                <?php endif; ?>
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
            <p class="no-booking">
                No bookings found. <a href="form.php" class="first-booking-link">Make your first booking!</a>
            </p>
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
            <button id="yesBTN" onclick="window.location.href='index.php'">Yes</button>
            <button id="noBTN"  onclick="showLog()">No</button>
        </div>
    </div>
</div>

<div id="profilePopup" class="hidden">
    <div id="profileShortDetails">
        <h3>Profile</h3>
        <p>Name  : <span><?php echo isset($_SESSION['Student_Name']) ? htmlspecialchars($_SESSION['Student_Name']) : ''; ?></span></p>
        <p>Email : <span><?php echo isset($_SESSION['Email']) ? htmlspecialchars($_SESSION['Email']) : ''; ?></span></p>
        <div id="profileBTN">
            <button id="close" onclick="showProfile()">Close</button>
        </div>
    </div>
</div>

<!-- Verification notice popup: shown automatically when a pickup verification is pending -->
<div id="verifyNotifyPopup" class="hidden">
    <div id="verifyNotifyText">
        <p> Staff has sent a pickup verification request.</p>
        <p style="font-size:0.82rem; color:#555; margin-top:6px;">Please confirm so staff can release your items.</p>
        <div id="verifyNotifyButtons">
            <button id="verifyGoBTN" onclick="window.location.href='studentverify.php'">Go</button>
            <button id="verifyLaterBTN" onclick="closeVerifyNotify()">Later</button>
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

    function closeVerifyNotify() {
        document.getElementById('verifyNotifyPopup').classList.add('hidden');
    }

    // ── Show verification notice popup automatically on login if staff sent a request ──
    <?php if ($verifyNoticeCount > 0): ?>
    document.getElementById('verifyNotifyPopup').classList.remove('hidden');
    <?php endif; ?>

    // ── Intercept browser back button: show logout confirmation instead of leaving ──
    history.pushState(null, '', location.href);
    window.addEventListener('popstate', function () {
        history.pushState(null, '', location.href);
        document.getElementById('logoutPopup').classList.remove('hidden');
    });

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