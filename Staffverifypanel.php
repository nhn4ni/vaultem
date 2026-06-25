<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';

// ── Status values used (no new columns — all stored in Booking_Status) ─────────
// Approved           → approved, not yet sent
// Verification_Sent  → staff sent request to student
// Confirmed          → student confirmed, ready for release
// Collected          → staff released items

// ── Handle send verification ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_verify_id'])) {
    $bid = intval($_POST['send_verify_id']);
    $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Verification_Sent' WHERE Booking_ID = ? AND LOWER(Booking_Status) = 'approved'");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $stmt->close();
    header("Location: staffVerifyPanel.php?msg=sent");
    exit();
}

// ── Handle resend ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verify_id'])) {
    $bid = intval($_POST['resend_verify_id']);
    $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Verification_Sent' WHERE Booking_ID = ? AND Booking_Status = 'Verification_Sent'");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $stmt->close();
    header("Location: staffVerifyPanel.php?msg=sent");
    exit();
}

// ── Handle release ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_id'])) {
    $bid = intval($_POST['release_id']);
    $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Collected' WHERE Booking_ID = ? AND Booking_Status = 'Confirmed'");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $stmt->close();
    header("Location: staffVerifyPanel.php?msg=released");
    exit();
}

// ── Filter ────────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';

$filterWhere = "AND b.Booking_Status IN ('Approved','Verification_Sent','Confirmed')";
if ($filter === 'notsent')   $filterWhere = "AND LOWER(b.Booking_Status) = 'approved'";
if ($filter === 'pending')   $filterWhere = "AND b.Booking_Status = 'Verification_Sent'";
if ($filter === 'confirmed') $filterWhere = "AND b.Booking_Status = 'Confirmed'";

// ── Fetch bookings ────────────────────────────────────────────────────────────
$bookings = $conn->query("
    SELECT b.Booking_ID, b.Pickup_Date, b.DropOff_Date, b.Booking_Status,
           s.Student_Name, s.Student_ID, s.Student_PhoneNo,
           rc.Residential_Block,
           COALESCE(SUM(i.Quantity), 0) AS TotalItem
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    WHERE 1=1 $filterWhere
    GROUP BY b.Booking_ID, b.Pickup_Date, b.DropOff_Date, b.Booking_Status,
             s.Student_Name, s.Student_ID, s.Student_PhoneNo, rc.Residential_Block
    ORDER BY b.Pickup_Date ASC
");
if (!$bookings) $bookings = null;

// ── Stats ─────────────────────────────────────────────────────────────────────
$r1 = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status) = 'approved'");
$statNotSent = $r1 ? $r1->fetch_assoc()['c'] : 0;

$r2 = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE Booking_Status = 'Verification_Sent'");
$statPending = $r2 ? $r2->fetch_assoc()['c'] : 0;

$r3 = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE Booking_Status = 'Confirmed'");
$statConfirmed = $r3 ? $r3->fetch_assoc()['c'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Verification Panel</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .stats-row { display: flex; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
        .stat-pill { flex: 1; min-width: 110px; border-radius: 14px; padding: 14px 16px; text-align: center; border: 1px solid rgba(124,92,252,0.25); }
        .stat-pill .s-val   { font-size: 1.8rem; font-weight: 800; line-height: 1; }
        .stat-pill .s-label { font-size: 0.7rem; color: #8b82b5; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        .stat-pill.grey  { background: rgba(139,130,181,0.1); }
        .stat-pill.grey  .s-val { color: #8b82b5; }
        .stat-pill.amber { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.3); }
        .stat-pill.amber .s-val { color: #f59e0b; }
        .stat-pill.green { background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.3); }
        .stat-pill.green .s-val { color: #22c55e; }

        .filter-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
        .filter-chip { padding: 6px 16px; border-radius: 20px; border: 1px solid rgba(124,92,252,0.25); background: none; color: #8b82b5; font-size: 0.78rem; cursor: pointer; text-decoration: none; transition: all 0.2s; font-family: inherit; }
        .filter-chip:hover, .filter-chip.active { background: rgba(124,92,252,0.18); color: #b084ff; border-color: #7c5cfc; }

        .verify-card { background: #f1f0ea; color: #1e1b4b; border-radius: 18px; padding: 18px 22px; margin-bottom: 14px; }
        .verify-top  { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }

        .vs-badge { font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 10px; }
        .vs-notsent   { background: #e2e3e5; color: #6c757d; }
        .vs-pending   { background: #fff3cd; color: #856404; }
        .vs-confirmed { background: #d4edda; color: #155724; }

        .verify-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 6px 16px; font-size: 0.82rem; margin-bottom: 14px; }
        .verify-grid label { font-size: 0.68rem; color: #888; text-transform: uppercase; display: block; margin-bottom: 1px; }

        .btn-send    { background: #6f42c1; color: #fff; border: none; padding: 8px 20px; border-radius: 16px; font-weight: bold; font-size: 0.82rem; cursor: pointer; }
        .btn-send:hover    { background: #59359a; transform: translateY(-1px); }
        .btn-release { background: #198754; color: #fff; border: none; padding: 8px 20px; border-radius: 16px; font-weight: bold; font-size: 0.82rem; cursor: pointer; }
        .btn-release:hover { background: #157347; transform: translateY(-1px); }
        .btn-view    { background: #241253; color: #E8E9DE; border: none; padding: 8px 18px; border-radius: 16px; font-size: 0.8rem; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-view:hover { background: #37216d; transform: translateY(-1px); }

        .action-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .overdue-flag { font-size: 0.75rem; color: #dc3545; font-weight: bold; }

        .msg-banner { padding: 10px 16px; border-radius: 12px; font-size: 0.88rem; font-weight: 600; margin-bottom: 18px; }
        .msg-success { background: rgba(25,135,84,0.12); color: #198754; border: 1px solid rgba(25,135,84,0.3); }
        .msg-info    { background: rgba(124,92,252,0.10); color: #b084ff; border: 1px solid rgba(124,92,252,0.25); }

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
<button class="back" onclick="history.back()">&#60; Back</button>

    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='staffMainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='Staffmainstatus.php'">
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

        <h1>Verification Panel</h1>

        <?php if (isset($_GET['msg'])): ?>
        <div class="msg-banner <?php echo $_GET['msg'] === 'released' ? 'msg-success' : 'msg-info'; ?>">
            <?php
                $msgs = [
                    'sent'     => ' Verification request sent to student.',
                    'released' => ' Items marked as collected successfully.',
                ];
                echo $msgs[$_GET['msg']] ?? '';
            ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-pill grey">
                <div class="s-val"><?php echo $statNotSent; ?></div>
                <div class="s-label">Not Sent</div>
            </div>
            <div class="stat-pill amber">
                <div class="s-val"><?php echo $statPending; ?></div>
                <div class="s-label">Awaiting Student</div>
            </div>
            <div class="stat-pill green">
                <div class="s-val"><?php echo $statConfirmed; ?></div>
                <div class="s-label">Student Confirmed</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-row">
            <a href="staffVerifyPanel.php?filter=all"       class="filter-chip <?php echo $filter==='all'       ? 'active' : ''; ?>">All</a>
            <a href="staffVerifyPanel.php?filter=notsent"   class="filter-chip <?php echo $filter==='notsent'   ? 'active' : ''; ?>">Not Sent</a>
            <a href="staffVerifyPanel.php?filter=pending"   class="filter-chip <?php echo $filter==='pending'   ? 'active' : ''; ?>">Awaiting Student</a>
            <a href="staffVerifyPanel.php?filter=confirmed" class="filter-chip <?php echo $filter==='confirmed' ? 'active' : ''; ?>">Student Confirmed</a>
        </div>

        <div class="section-label">Bookings</div>

        <?php
        $today = date('Y-m-d');
        if ($bookings && $bookings->num_rows > 0):
            while ($row = $bookings->fetch_assoc()):
                $bs      = $row['Booking_Status'];
                $overdue = $row['Pickup_Date'] < $today;
        ?>
        <div class="verify-card">
            <div class="verify-top">
                <span class="order-id">Booking #<?php echo $row['Booking_ID']; ?></span>
                <?php if ($bs === 'Confirmed'): ?>
                    <span class="vs-badge vs-confirmed"> Student Confirmed</span>
                <?php elseif ($bs === 'Verification_Sent'): ?>
                    <span class="vs-badge vs-pending"> Awaiting Student</span>
                <?php else: ?>
                    <span class="vs-badge vs-notsent">Not Sent</span>
                <?php endif; ?>
                <?php if ($overdue): ?>
                    <span class="overdue-flag"> Pick-up date passed</span>
                <?php endif; ?>
            </div>

            <div class="verify-grid">
                <div><label>Student</label><?php echo htmlspecialchars($row['Student_Name']); ?></div>
                <div><label>Student ID</label><?php echo htmlspecialchars($row['Student_ID']); ?></div>
                <div><label>Phone</label><?php echo htmlspecialchars($row['Student_PhoneNo'] ?? 'N/A'); ?></div>
                <div><label>College</label><?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></div>
                <div><label>Drop-off</label><?php echo htmlspecialchars($row['DropOff_Date']); ?></div>
                <div><label>Pick-up</label><?php echo htmlspecialchars($row['Pickup_Date']); ?></div>
                <div><label>Items</label><?php echo $row['TotalItem']; ?></div>
            </div>

            <div class="action-row">
                <?php if ($bs === 'Confirmed'): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="release_id" value="<?php echo $row['Booking_ID']; ?>">
                        <button type="submit" class="btn-release"
                            onclick="return confirm('Mark Booking #<?php echo $row['Booking_ID']; ?> as collected?')">
                             Release Items
                        </button>
                    </form>
                <?php elseif ($bs === 'Verification_Sent'): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="resend_verify_id" value="<?php echo $row['Booking_ID']; ?>">
                        <button type="submit" class="btn-send">↺ Resend Request</button>
                    </form>
                <?php else: ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="send_verify_id" value="<?php echo $row['Booking_ID']; ?>">
                        <button type="submit" class="btn-send">Send Verification →</button>
                    </form>
                <?php endif; ?>
                <a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="btn-view">View Booking</a>
            </div>
        </div>
        <?php endwhile;
        else: ?>
        <div class="empty-box"> No bookings match this filter.</div>
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