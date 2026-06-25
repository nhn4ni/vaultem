<?php
session_start();

if (!isset($_SESSION['Student_ID'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$student_id   = $_SESSION['Student_ID'];
$student_name = $_SESSION['Student_Name'] ?? 'Student';

// ── Handle student confirmation ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking_id'])) {
    $bid = intval($_POST['confirm_booking_id']);
    $stmt = $conn->prepare("
        UPDATE booking SET Booking_Status = 'Confirmed'
        WHERE Booking_ID = ? AND Student_ID = ? AND Booking_Status = 'Verification_Sent'
    ");
    $stmt->bind_param("is", $bid, $student_id);
    $stmt->execute();
    $stmt->close();
    header("Location: studentVerify.php?msg=confirmed");
    exit();
}

// ── Fetch bookings where verification was sent or already confirmed ───────────
$verifyQ = $conn->query("
    SELECT b.Booking_ID, b.Pickup_Date, b.DropOff_Date, b.Booking_Status,
           rc.Residential_Block,
           COALESCE(SUM(i.Quantity), 0) AS TotalItem,
           COALESCE(SUM(i.Quantity * i.Price), 0) AS TotalFee
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    WHERE b.Student_ID = '$student_id'
      AND b.Booking_Status IN ('Verification_Sent', 'Confirmed')
    GROUP BY b.Booking_ID, b.Pickup_Date, b.DropOff_Date, b.Booking_Status, rc.Residential_Block
    ORDER BY b.Pickup_Date ASC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Item Collection</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .verify-card { background: #f1f0ea; color: #1e1b4b; border-radius: 20px; padding: 22px 24px; margin-bottom: 16px; }
        .verify-header { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }

        .verify-badge { font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 10px; }
        .badge-waiting   { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d4edda; color: #155724; }

        .verify-info p { margin: 4px 0; font-size: 0.88rem; }

        .confirm-btn { background: #198754; color: #fff; border: none; padding: 10px 28px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; cursor: pointer; margin-top: 14px; transition: all 0.2s; }
        .confirm-btn:hover { background: #157347; transform: translateY(-1px); }

        .confirmed-msg { display: flex; align-items: center; gap: 8px; color: #155724; font-weight: bold; font-size: 0.9rem; margin-top: 14px; background: #d4edda; padding: 10px 16px; border-radius: 12px; }

        .msg-banner { padding: 10px 16px; border-radius: 12px; font-size: 0.88rem; font-weight: 600; margin-bottom: 18px; }
        .msg-success { background: rgba(25,135,84,0.12); color: #198754; border: 1px solid rgba(25,135,84,0.3); }

        .empty-box { background: #f1f0ea; color: #8b82b5; border-radius: 20px; padding: 40px; text-align: center; font-size: 0.9rem; }

        .step-guide { background: rgba(124,92,252,0.10); border: 1px solid rgba(124,92,252,0.25); border-radius: 16px; padding: 18px 22px; margin-bottom: 22px; font-size: 0.85rem; color: #E8E9DE; }
        .step-guide h4 { margin: 0 0 10px; font-size: 0.88rem; color: red; }
        .step-guide ol { margin: 0; padding-left: 18px; }
        .step-guide li { margin-bottom: 6px; }

        #profileContainer { position: relative; display: inline-block; cursor: pointer; }
        #userImage { vertical-align: middle; margin-left: 5px; }
        #profileSelect { display: none; position: absolute; right: 0; top: 25px; background-color: #241253; border: 1px solid #E8E9DE; border-radius: 8px; min-width: 130px; z-index: 10; }
        #profileSelect.show { display: flex; flex-direction: column; }
        #profileSelect button { background: none; border: none; color: #E8E9DE; padding: 10px; text-align: left; width: 100%; cursor: pointer; font-size: 0.85rem; }
        #profileSelect button:hover { background-color: rgba(232,233,222,0.2); }
    </style>
</head>
<body>
<div id="wrapper">

    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='mainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='mainStatus.php'">
            My Bookings
        </button>
    </div>

    <div class="rightcontainer">

        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo htmlspecialchars($student_name); ?></span>
            <span id="profileContainer">
                <img id="userImage" src="/image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <h1>Item Collection</h1>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'confirmed'): ?>
        <div class="msg-banner msg-success"> Confirmed! Please proceed to collect your items from the storage room.</div>
        <?php endif; ?>

        <div class="step-guide">
            <h4> How to collect your items</h4>
            <ol>
                <li>Staff sends you a verification request when your pick-up date is near.</li>
                <li>Click <strong>Confirm Collection</strong> below to let staff know you're coming.</li>
                <li>Bring your student ID to the storage room on your pick-up date.</li>
                <li>Staff will release your items after verifying your identity.</li>
            </ol>
        </div>

        <?php if ($verifyQ && $verifyQ->num_rows > 0):
            while ($row = $verifyQ->fetch_assoc()):
                $bs = $row['Booking_Status'];
        ?>
        <div class="verify-card">
            <div class="verify-header">
                <span class="order-id">Booking #<?php echo $row['Booking_ID']; ?></span>
                <?php if ($bs === 'Confirmed'): ?>
                    <span class="verify-badge badge-confirmed">You Confirmed</span>
                <?php else: ?>
                    <span class="verify-badge badge-waiting"> Awaiting Your Confirmation</span>
                <?php endif; ?>
            </div>

            <div class="verify-info">
                <p>College   : <?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></p>
                <p>Drop-off  : <?php echo htmlspecialchars($row['DropOff_Date']); ?></p>
                <p>Pick-up   : <?php echo htmlspecialchars($row['Pickup_Date']); ?></p>
                <p>Items     : <?php echo $row['TotalItem']; ?></p>
                <p>Total fee : RM <?php echo number_format((float)$row['TotalFee'], 2); ?></p>
            </div>

            <?php if ($bs === 'Confirmed'): ?>
                <div class="confirmed-msg"> Confirmed. Please go to the storage room to collect your items.</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="confirm_booking_id" value="<?php echo $row['Booking_ID']; ?>">
                    <button type="submit" class="confirm-btn"
                        onclick="return confirm('Confirm collection for Booking #<?php echo $row['Booking_ID']; ?>? Make sure you are ready to collect.')">
                         Confirm Collection
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php endwhile;
        else: ?>
        <div class="empty-box">
            <p> No pending collection requests.</p>
            <p style="margin-top:8px; font-size:0.8rem;">Staff will send you a request when your pick-up date is approaching.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="logoutPopup" class="hidden">
    <div id="logoutText">
        <p>Are you sure you want to logout?</p>
        <div id="logoutButton">
            <button id="yesBTN" onclick="window.location.href='logout.php'">Yes</button>
            <button id="noBTN" onclick="showLog()">No</button>
        </div>
    </div>
</div>

<script>
    function profileMenu() { document.getElementById('profileSelect').classList.toggle('show'); }
    function showLog()     { document.getElementById('logoutPopup').classList.toggle('hidden'); }
    document.addEventListener('click', function(e) {
        const c = document.getElementById('profileContainer');
        if (!c.contains(e.target)) document.getElementById('profileSelect').classList.remove('show');
    });
</script>
</body>
</html>