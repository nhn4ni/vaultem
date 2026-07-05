<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name   = $_SESSION['Staff_Name'] ?? 'Staff';
$PENALTY_RATE = 2.00;

// ── Handle Actions ────────────────────────────────────────────────────────────
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $bookingId = $conn->real_escape_string($_POST['booking_id']);

    if ($_POST['action_type'] === 'waive') {
        $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Waived' WHERE Booking_ID = ?");
        $stmt->bind_param("s", $bookingId);
        if ($stmt->execute()) {
            $message = "Penalty for Booking #$bookingId has been successfully waived.";
        }
        $stmt->close();

    } elseif ($_POST['action_type'] === 'collect') {
        $amountPaid = floatval($_POST['penalty_amount']);
        $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Settled' WHERE Booking_ID = ?");
        $stmt->bind_param("s", $bookingId);
        if ($stmt->execute()) {
            $message = "Successfully recorded penalty payment of RM " . number_format($amountPaid, 2) . " for Booking #$bookingId.";
        }
        $stmt->close();
    }
}

// ── Fetch Overdue Bookings ────────────────────────────────────────────────────
$overdueQuery = $conn->query("
    SELECT
        b.Booking_ID,
        b.Pickup_Date,
        b.DropOff_Date,
        s.Student_Name,
        s.Student_ID,
        GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, DATE_ADD(b.Pickup_Date, INTERVAL 11 HOUR), NOW()) / 86400)) AS DaysOverdue,
        COALESCE(SUM(i.Quantity), 0) AS TotalItems
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN item i    ON b.Booking_ID = i.Booking_ID
    WHERE LOWER(b.Booking_Status) = 'approved'
      AND DATE_ADD(b.Pickup_Date, INTERVAL 11 HOUR) < NOW()
    GROUP BY b.Booking_ID, b.Pickup_Date, b.DropOff_Date, s.Student_Name, s.Student_ID
    ORDER BY DaysOverdue DESC
");

$totalOverdueRecords = 0;
$grandTotalPenalties = 0.00;
$overdueList         = [];

while ($row = $overdueQuery->fetch_assoc()) {
    $days = max(0, (int)$row['DaysOverdue']);
    $fine = round($days * $PENALTY_RATE, 2);
    $row['Calculated_Fine'] = $fine;
    $overdueList[]          = $row;
    $totalOverdueRecords++;
    $grandTotalPenalties += $fine;
}

$conn->close();
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
        .penalty-hero {
            background: #f1f0ea;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 26px;
            border-left: 5px solid #ef4444;
        }
        .hero-title { font-size: 0.78rem; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 6px; }
        .hero-value { font-size: 2.2rem; font-weight: 800; color: #ef4444; margin-bottom: 4px; }
        .hero-sub   { font-size: 0.82rem; color: #555; }

        .panel-msg {
            background: #d4edda; color: #155724;
            padding: 12px 16px; border-radius: 8px;
            margin-bottom: 20px; font-size: 0.85rem; font-weight: 600;
        }

        .action-btn {
            padding: 5px 12px; border-radius: 14px; border: none;
            font-size: 0.75rem; font-weight: 700; cursor: pointer;
            font-family: inherit; margin-right: 4px;
        }
        .btn-collect { background: #22c55e; color: #fff; }
        .btn-waive   { background: #e8e7df; color: #555; border: 1px solid #ccc; }
        .action-btn:hover { opacity: 0.85; }

        .report-table th, .report-table td { white-space: nowrap; }

        .full-content-panel { width: 100%; padding: 10px 20px; }

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
    <button class="back" onclick="window.location.href='staffMainStatus.php'">&#60; Back</button>

    <div class="full-content-panel">

        <div id="userName" style="text-align:right; margin-bottom:20px;">
            Welcome, <span id="currentName"><?php echo htmlspecialchars($staff_name); ?></span>
            <span id="profileContainer">
                <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showProfile()">Profile</button>
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <h1>Penalty Administration</h1>

        <?php if ($message): ?>
            <div class="panel-msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Summary card -->
        <div class="penalty-hero">
            <div class="hero-title">Total Outstanding Penalties</div>
            <div class="hero-value">RM <?php echo number_format($grandTotalPenalties, 2); ?></div>
            <div class="hero-sub">
                Accumulated across <strong><?php echo $totalOverdueRecords; ?></strong>
                uncollected overdue booking<?php echo $totalOverdueRecords !== 1 ? 's' : ''; ?>
                at a rate of <strong>RM <?php echo number_format($PENALTY_RATE, 2); ?> / day</strong>.
            </div>
        </div>

        <!-- Overdue table -->
        <div style="overflow-x:auto;">
            <table class="report-table" style="width:100%; border-collapse:collapse; background:#f1f0ea; border-radius:16px; overflow:hidden; font-size:0.82rem;">
                <thead>
                    <tr style="background:#e8e7df; color:#555; font-size:0.7rem; text-transform:uppercase; text-align:left;">
                        <th style="padding:12px;">Booking ID</th>
                        <th style="padding:12px;">Student Name</th>
                        <th style="padding:12px;">Student ID</th>
                        <th style="padding:12px;">Items Stored</th>
                        <th style="padding:12px;">Deadline Date</th>
                        <th style="padding:12px;">Days Overdue</th>
                        <th style="padding:12px;">Fine (RM)</th>
                        <th style="padding:12px; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($overdueList)): ?>
                    <?php foreach ($overdueList as $item): ?>
                    <tr style="color:#1e1b4b; border-bottom:1px solid #dddcd6;">
                        <td style="padding:12px; font-weight:700;">#<?php echo $item['Booking_ID']; ?></td>
                        <td style="padding:12px;"><?php echo htmlspecialchars($item['Student_Name']); ?></td>
                        <td style="padding:12px;"><?php echo htmlspecialchars($item['Student_ID']); ?></td>
                        <td style="padding:12px;"><?php echo $item['TotalItems']; ?> item<?php echo $item['TotalItems'] != 1 ? 's' : ''; ?></td>
                        <td style="padding:12px; color:#555;"><?php echo htmlspecialchars($item['Pickup_Date']); ?></td>
                        <td style="padding:12px; font-weight:700; color:#dc3545;"><?php echo $item['DaysOverdue']; ?> day<?php echo $item['DaysOverdue'] != 1 ? 's' : ''; ?></td>
                        <td style="padding:12px; font-weight:800; color:#ef4444;">RM <?php echo number_format($item['Calculated_Fine'], 2); ?></td>
                        <td style="padding:12px; text-align:center;">
                            <form method="POST" style="display:inline-block; margin:0;">
                                <input type="hidden" name="booking_id"     value="<?php echo $item['Booking_ID']; ?>">
                                <input type="hidden" name="penalty_amount" value="<?php echo $item['Calculated_Fine']; ?>">
                                <button type="submit" name="action_type" value="collect" class="action-btn btn-collect"
                                    onclick="return confirm('Collect penalty of RM <?php echo $item['Calculated_Fine']; ?> for Booking #<?php echo $item['Booking_ID']; ?>?')">
                                    Collect
                                </button>
                                <button type="submit" name="action_type" value="waive" class="action-btn btn-waive"
                                    onclick="return confirm('Waive penalty for Booking #<?php echo $item['Booking_ID']; ?>?')">
                                    Waive Fine
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px; color:#666;">
                            No overdue bookings. All students collected on time.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

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
        <div id="profileBTN"><button id="close" onclick="showProfile()">Close</button></div>
    </div>
</div>

<script>
    function profileMenu() { document.getElementById('profileSelect').classList.toggle('show'); }
    function showProfile() { document.getElementById('profilePopup').classList.toggle('hidden'); }
    function showLog()     { document.getElementById('logoutPopup').classList.toggle('hidden'); }
    document.addEventListener('click', function(e) {
        const c = document.getElementById('profileContainer');
        if (c && !c.contains(e.target)) document.getElementById('profileSelect').classList.remove('show');
    });
</script>
</body>
</html>