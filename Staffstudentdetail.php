<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';
$sid = $conn->real_escape_string($_GET['id'] ?? '');

if (!$sid) { header("Location: staffStudentList.php"); exit(); }

// ── Fetch student ─────────────────────────────────────────────────────────────
$stRes = $conn->query("
    SELECT s.*, rc.Residential_Block
    FROM student s
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    WHERE s.Student_ID = '$sid'
");
if ($stRes->num_rows === 0) { header("Location: staffStudentList.php"); exit(); }
$st = $stRes->fetch_assoc();

// ── Fetch bookings for this student ──────────────────────────────────────────
$bRes = $conn->query("
    SELECT b.Booking_ID, b.Booking_Status, b.DropOff_Date, b.Pickup_Date,
           b.Booking_Priority,
           COALESCE(SUM(i.Quantity), 0) AS TotalItem,
           COALESCE(SUM(i.Quantity * i.Price), 0) AS TotalFee,
           p.Payment_Status
    FROM booking b
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID
    WHERE b.Student_ID = '$sid'
    GROUP BY b.Booking_ID, b.Booking_Status, b.DropOff_Date, b.Pickup_Date,
             b.Booking_Priority, p.Payment_Status
    ORDER BY b.Booking_ID DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – <?php echo htmlspecialchars($st['Student_Name']); ?></title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .back-btn { background: none; border: none; color: #241253; font-size: 1rem; font-weight: bold; cursor: pointer; padding: 14px 0 0 20px; display: block; }
        .back-btn:hover { text-decoration: underline; transform: none; }

        .profile-card {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #7c5cfc, #b084ff);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .profile-info h3 { margin: 0 0 4px; font-size: 1.1rem; }
        .profile-info p  { margin: 2px 0; font-size: 0.82rem; color: #555; }

        .status-text { display: inline-block; padding: 3px 10px; border-radius: 10px; font-weight: bold; font-size: 0.78rem; }
        .status-pending  { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .priority-badge  { background: #dc3545; color: #fff; font-size: 0.72rem; padding: 2px 7px; border-radius: 8px; font-weight: bold; }

        .booking-row {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .booking-row:hover { background: #e8e7e0; }
        .bid { font-weight: 800; font-size: 0.9rem; min-width: 60px; }
        .binfo { flex: 1; font-size: 0.82rem; color: #555; }
        .bfee  { font-size: 0.82rem; color: #1e1b4b; font-weight: 600; }
        .view-btn { background: #241253; color: #E8E9DE; border: none; padding: 7px 16px; border-radius: 14px; cursor: pointer; font-size: 0.8rem; font-weight: bold; text-decoration: none; width: auto; }
        .view-btn:hover { background: #37216d; transform: translateY(-1px); }

        .section-label { font-size: 0.75rem; color: #8b82b5; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }

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

        <button class="back-btn" onclick="history.back()">&#60; Back</button>
        <h1>Student Profile</h1>

        <div class="profile-card">
            <div class="profile-avatar"><?php echo strtoupper(substr($st['Student_Name'], 0, 1)); ?></div>
            <div class="profile-info">
                <h3><?php echo htmlspecialchars($st['Student_Name']); ?></h3>
                <p>ID: <?php echo htmlspecialchars($st['Student_ID']); ?></p>
                <p>Email: <?php echo htmlspecialchars($st['Student_Mail']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($st['Student_PhoneNo'] ?? 'N/A'); ?></p>
                <p>College: <?php echo htmlspecialchars($st['Residential_Block'] ?? 'N/A'); ?></p>
                <p>Gender: <?php echo $st['Gender'] === 'M' ? 'Male' : ($st['Gender'] === 'F' ? 'Female' : 'N/A'); ?></p>
            </div>
        </div>

        <div class="section-label">Booking History</div>

        <?php if ($bRes && $bRes->num_rows > 0):
            while ($row = $bRes->fetch_assoc()):
                $bstat  = strtolower($row['Booking_Status']);
                $isPrio = $row['Booking_Priority'] === 'Y';
                $isPaid = in_array(strtolower($row['Payment_Status'] ?? ''), ['y','paid']);
        ?>
        <div class="booking-row">
            <span class="bid">#<?php echo $row['Booking_ID']; ?></span>
            <?php if ($isPrio): ?><span class="priority-badge">EMERGENCY</span><?php endif; ?>
            <span class="status-text status-<?php echo $bstat; ?>"><?php echo htmlspecialchars($row['Booking_Status']); ?></span>
            <span class="binfo">
                Drop-off: <?php echo $row['DropOff_Date']; ?> · Pick-up: <?php echo $row['Pickup_Date']; ?>
                · Items: <?php echo $row['TotalItem']; ?>
                · <?php echo $isPaid ? '✓ Paid' : 'Unpaid'; ?>
            </span>
            <span class="bfee">RM <?php echo number_format((float)$row['TotalFee'], 2); ?></span>
            <a class="view-btn" href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>">View →</a>
        </div>
        <?php endwhile;
        else: ?>
            <p style="color:#8b82b5;">No bookings from this student.</p>
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