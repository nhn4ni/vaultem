<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';

// ── Handle apply penalty POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_penalty_id'])) {
    $bid    = intval($_POST['apply_penalty_id']);
    $amount = floatval($_POST['penalty_amount']);
    $reason = trim($_POST['penalty_reason'] ?? 'Late collection');

    // Insert into payment as a penalty record (Payment_Method = 'Penalty')
    $stmt = $conn->prepare("
        INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID)
        VALUES ('Penalty', 'N', CURDATE(), ?, ?)
        ON DUPLICATE KEY UPDATE Amount = Amount + ?, Payment_Status = 'N'
    ");
    $stmt->bind_param("didi", $amount, $bid, $amount, $bid);
    $stmt->execute();
    $stmt->close();

    // Log in booking
    $upd = $conn->prepare("UPDATE booking SET Reject_Reason = CONCAT(IFNULL(Reject_Reason,''), ' | PENALTY: ', ?) WHERE Booking_ID = ?");
    $upd->bind_param("si", $reason, $bid);
    $upd->execute();
    $upd->close();

    header("Location: staffPenalty.php?msg=applied");
    exit();
}

$today = date('Y-m-d');

// ── Overdue bookings (pickup date passed, still approved, not collected) ───────
$overdue = $conn->query("
    SELECT b.Booking_ID, b.Pickup_Date, b.DropOff_Date, b.Verification_Status,
           s.Student_Name, s.Student_ID, s.Student_PhoneNo,
           rc.Residential_Block,
           COALESCE(SUM(i.Quantity * i.Price), 0) AS TotalFee,
           p.Payment_Status, p.Amount AS PaidAmount,
           DATEDIFF(CURDATE(), b.Pickup_Date) AS DaysOverdue
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID
    WHERE LOWER(b.Booking_Status) = 'approved'
      AND b.Pickup_Date < CURDATE()
    GROUP BY b.Booking_ID, b.Pickup_Date, b.DropOff_Date, b.Verification_Status,
             s.Student_Name, s.Student_ID, s.Student_PhoneNo, rc.Residential_Block,
             p.Payment_Status, p.Amount
    ORDER BY DaysOverdue DESC
");

$conn->close();

// Penalty rate: RM 2.00 per day overdue
$PENALTY_RATE = 2.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Penalty Management</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .msg-banner { padding: 10px 16px; border-radius: 12px; font-size: 0.88rem; font-weight: 600; margin-bottom: 18px; }
        .msg-success { background: rgba(25,135,84,0.12); color: #198754; border: 1px solid rgba(25,135,84,0.3); }

        .info-box {
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #E8E9DE;
        }
        .info-box strong { color: red; }

        .penalty-card {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 18px;
            padding: 20px 22px;
            margin-bottom: 14px;
            border-left: 4px solid #dc3545;
        }

        .penalty-top { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .overdue-badge { background: #dc3545; color: #fff; font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 10px; }

        .penalty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 6px 16px;
            font-size: 0.82rem;
            margin-bottom: 14px;
        }
        .penalty-grid label { font-size: 0.68rem; color: #888; text-transform: uppercase; display: block; margin-bottom: 1px; }

        .penalty-calc {
            background: rgba(220,53,69,0.08);
            border: 1px solid rgba(220,53,69,0.2);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
        .penalty-calc strong { color: #dc3545; font-size: 1rem; }

        .penalty-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .penalty-form input[type=number] {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.88rem;
            width: 120px;
            font-family: inherit;
        }
        .penalty-form input[type=text] {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.88rem;
            flex: 1;
            min-width: 160px;
            font-family: inherit;
        }
        .btn-penalty { background: #dc3545; color: #fff; border: none; padding: 8px 20px; border-radius: 16px; font-weight: bold; font-size: 0.82rem; cursor: pointer; }
        .btn-penalty:hover { background: #bb2d3b; transform: translateY(-1px); }
        .btn-view { background: #241253; color: #E8E9DE; border: none; padding: 8px 18px; border-radius: 16px; font-size: 0.8rem; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-view:hover { background: #37216d; transform: translateY(-1px); }

        .section-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #8b82b5; margin-bottom: 12px; }
        .empty-box { background: #f1f0ea; color: #8b82b5; border-radius: 20px; padding: 40px; text-align: center; }

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
            <h1 onclick="window.location.href='staffMainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='staffmainstatus.php'">
            Manage Bookings
        </button>
    </div>

    <div class="rightcontainer">

        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo htmlspecialchars($staff_name); ?></span>
            <span id="profileContainer">
                <img id="userImage" src="/image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showProfile()">Profile</button>
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <h1>Penalty Management</h1>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'applied'): ?>
        <div class="msg-banner msg-success">Penalty applied successfully. Student will see the updated payment.</div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Penalty Policy:</strong> Students who fail to collect items by their pick-up date are charged
            <strong>RM <?php echo number_format($PENALTY_RATE, 2); ?> per day</strong> overdue.
            The suggested penalty is auto-calculated below. You may adjust before applying.
        </div>

        <div class="section-label">Overdue Bookings</div>

        <?php if ($overdue && $overdue->num_rows > 0):
            while ($row = $overdue->fetch_assoc()):
                $days = max(0, (int)$row['DaysOverdue']);
                $suggestedPenalty = round($days * $PENALTY_RATE, 2);
        ?>
        <div class="penalty-card">
            <div class="penalty-top">
                <span class="order-id">Booking #<?php echo $row['Booking_ID']; ?></span>
                <span class="overdue-badge">⚠ <?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?> overdue</span>
            </div>

            <div class="penalty-grid">
                <div><label>Student</label><?php echo htmlspecialchars($row['Student_Name']); ?></div>
                <div><label>Student ID</label><?php echo htmlspecialchars($row['Student_ID']); ?></div>
                <div><label>Phone</label><?php echo htmlspecialchars($row['Student_PhoneNo'] ?? 'N/A'); ?></div>
                <div><label>College</label><?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></div>
                <div><label>Drop-off</label><?php echo $row['DropOff_Date']; ?></div>
                <div><label>Pick-up (due)</label><?php echo $row['Pickup_Date']; ?></div>
                <div><label>Storage Fee</label>RM <?php echo number_format((float)$row['TotalFee'], 2); ?></div>
                <div><label>Verify Status</label><?php echo $row['Verification_Status'] ?: 'Not sent'; ?></div>
            </div>

            <div class="penalty-calc">
                Suggested penalty: <?php echo $days; ?> days × RM <?php echo number_format($PENALTY_RATE, 2); ?> =
                <strong>RM <?php echo number_format($suggestedPenalty, 2); ?></strong>
            </div>

            <form method="POST" class="penalty-form">
                <input type="hidden" name="apply_penalty_id" value="<?php echo $row['Booking_ID']; ?>">
                <span style="font-size:0.82rem; font-weight:600;">RM</span>
                <input type="number" name="penalty_amount" step="0.01" min="0"
                       value="<?php echo $suggestedPenalty; ?>" required>
                <input type="text" name="penalty_reason"
                       value="Late collection — <?php echo $days; ?> day(s) overdue" required>
                <button type="submit" class="btn-penalty"
                    onclick="return confirm('Apply penalty of RM <?php echo $suggestedPenalty; ?> to Booking #<?php echo $row['Booking_ID']; ?>?')">
                    Apply Penalty
                </button>
                <a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="btn-view">View</a>
            </form>
        </div>
        <?php endwhile;
        else: ?>
        <div class="empty-box"> No overdue bookings. All students collected on time!</div>
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

<div id="profilePopup" class="hidden">
    <div id="profileShortDetails">
        <h3>Profile</h3>
        <p>Name  : <span><?php echo htmlspecialchars($staff_name); ?></span></p>
        <p>Email : <span><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></span></p>
        <div id="profileBTN">
            <button id="close" onclick="showProfile()">Close</button>
        </div>
    </div>
</div>

<script>
    function profileMenu() { document.getElementById('profileSelect').classList.toggle('show'); }
    function showProfile() { document.getElementById('profilePopup').classList.toggle('hidden'); }
    function showLog()     { document.getElementById('logoutPopup').classList.toggle('hidden'); }
    document.addEventListener('click', function(e) {
        const c = document.getElementById('profileContainer');
        if (!c.contains(e.target)) document.getElementById('profileSelect').classList.remove('show');
    });
</script>
</body>
</html>