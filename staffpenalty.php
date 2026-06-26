<?php
// Show errors instead of white screen — remove these 2 lines on production
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';

// Strict penalty rate rule configuration
$PENALTY_RATE = 2.00;

// ── Handle Action Process (Collect Penalty / Waive) ──────────────────────────
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $bookingId = $conn->real_escape_string($_POST['booking_id']);
    
    if ($_POST['action_type'] === 'waive') {
        // Change status to transition out of the active overdue listing
        $updateStmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Waived' WHERE Booking_ID = ?");
        $updateStmt->bind_param("s", $bookingId);
        if ($updateStmt->execute()) {
            $message = "Penalty for Booking #$bookingId has been successfully waived.";
        }
        $updateStmt->close();
        
    } elseif ($_POST['action_type'] === 'collect') {
        $amountPaid = floatval($_POST['penalty_amount']);
        
        // Update status to 'Settled' or 'Collected' so it drops off the active overdue query
        $updateStmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Settled' WHERE Booking_ID = ?");
        $updateStmt->bind_param("s", $bookingId);
        if ($updateStmt->execute()) {
            $message = "Successfully recorded penalty payment of RM " . number_format($amountPaid, 2) . " for Booking #$bookingId.";
        }
        $updateStmt->close();
    }
}

// ── Fetch Overdue Bookings ────────────────────────────────────────────────────
// Filters only 'approved' bookings whose pick-up deadlines have passed
$overdueQuery = $conn->query("
    SELECT 
        b.Booking_ID,
        b.Pickup_Date,
        b.DropOff_Date,
        s.Student_Name,
        s.Student_ID,
        DATEDIFF(CURDATE(), b.Pickup_Date) AS DaysOverdue,
        COALESCE(SUM(i.Quantity), 0) AS TotalItems
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    WHERE LOWER(b.Booking_Status) = 'approved' AND b.Pickup_Date < CURDATE()
    GROUP BY b.Booking_ID, b.Pickup_Date, b.DropOff_Date, s.Student_Name, s.Student_ID
    ORDER BY DaysOverdue DESC
");

// Calculate metrics dynamically
$totalOverdueRecords = 0;
$grandTotalPenalties  = 0.00;

$overdueList = [];
while ($row = $overdueQuery->fetch_assoc()) {
    $days = max(0, (int)$row['DaysOverdue']);
    $fine = round($days * $PENALTY_RATE, 2);
    
    $row['Calculated_Fine'] = $fine;
    $overdueList[] = $row;
    
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
    <title>VaulteM – Manage Penalties</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        /* Panel card layouts */
        .penalty-hero {
            background: #f1f0ea;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 26px;
            border-left: 5px solid #ef4444;
        }
        .hero-title { font-size: 0.78rem; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 6px; }
        .hero-value { font-size: 2.2rem; font-weight: 800; color: #ef4444; margin-bottom: 4px; }
        .hero-sub { font-size: 0.82rem; color: #555; }

        .panel-msg {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Action buttons inside table */
        .action-btn {
            padding: 5px 12px;
            border-radius: 14px;
            border: none;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            margin-right: 4px;
        }
        .btn-collect { background: #22c55e; color: #fff; }
        .btn-waive { background: #e8e7df; color: #555; border: 1px solid #ccc; }
        .action-btn:hover { opacity: 0.85; }

        .report-table th, .report-table td { white-space: nowrap; }
        .badge-alert { background: #ef4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 8px; font-weight: 700; }
        
        #profileContainer { position: relative; display: inline-block; cursor: pointer; }
        #userImage { vertical-align: middle; margin-left: 5px; }
        #profileSelect { display: none; position: absolute; right: 0; top: 25px; background-color: #241253; border: 1px solid #E8E9DE; border-radius: 8px; min-width: 130px; z-index: 10; }
        #profileSelect.show { display: flex; flex-direction: column; }
        #profileSelect button { background: none; border: none; color: #E8E9DE; padding: 10px; text-align: left; width: 100%; cursor: pointer; font-size: 0.85rem; }
        #profileSelect button:hover { background-color: rgba(232,233,222,0.2); }

        /* Custom alignment layout wrapper without standard left side bar tabs */
        .full-content-panel {
            width: 100%;
            padding: 10px 20px;
        }
    </style>
</head>
<body>
<div id="wrapper">
    <button class="back" onclick="window.location.href='Staffmainstatus.php'">&#60; Back</button>

    <!-- Right Area set up as full page panel -->
    <div class="full-content-panel">
        <div id="userName" style="text-align: right; margin-bottom: 20px;">
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

        <!-- Highlight Card -->
        <div class="penalty-hero">
            <div class="hero-title">Total Outstanding Penalties</div>
            <div class="hero-value">RM <?php echo number_format($grandTotalPenalties, 2); ?></div>
            <div class="hero-sub">Accumulated dynamically across <strong><?php echo $totalOverdueRecords; ?></strong> uncollected overdue bookings based on a rate of <strong>RM <?php echo number_format($PENALTY_RATE, 2); ?>/day</strong>.</div>
        </div>

        <h3 style="color:#e8e9de; font-size:0.9rem; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Pending Infractions</h3>

        <!-- Penalty Processing Table -->
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
                        <th style="padding:12px;">Fine Balance (RM)</th>
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
                        <td style="padding:12px;"><?php echo $item['TotalItems']; ?> items</td>
                        <td style="padding:12px; color:#555;"><?php echo htmlspecialchars($item['Pickup_Date']); ?></td>
                        <td style="padding:12px; font-weight:700; color:#dc3545;">
                            <?php echo $item['DaysOverdue']; ?> Days
                        </td>
                        <td style="padding:12px; font-weight:800; color:#ef4444;">
                            RM <?php echo number_format($item['Calculated_Fine'], 2); ?>
                        </td>
                        <td style="padding:12px; text-align:center;">
                            <form method="POST" style="display:inline-block; margin:0;">
                                <input type="hidden" name="booking_id" value="<?php echo $item['Booking_ID']; ?>">
                                <input type="hidden" name="penalty_amount" value="<?php echo $item['Calculated_Fine']; ?>">
                                
                                <button type="submit" name="action_type" value="collect" class="action-btn btn-collect">Collect</button>
                                <button type="submit" name="action_type" value="waive" class="action-btn btn-waive">Waive Fine</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px; color:#666;">
                            Clean record! No overdue items or penalties found in database tracking.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Simple Modals matching UI template setup -->
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