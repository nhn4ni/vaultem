<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';
$staff_id   = $_SESSION['Staff_ID'];

$pending  = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status) = 'pending'")->fetch_assoc()['c'];
$approved = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status) = 'approved'")->fetch_assoc()['c'];
$total    = $conn->query("SELECT COUNT(*) AS c FROM booking")->fetch_assoc()['c'];

$recentQ = $conn->query("
    SELECT b.Booking_ID, b.Booking_Status, b.DropOff_Date, b.Pickup_Date,
           b.Booking_Priority, s.Student_Name, rc.Residential_Block,
           COALESCE(SUM(i.Quantity), 0) AS TotalItem,
           COALESCE(SUM(i.Quantity * i.Price), 0) AS TotalFee
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    GROUP BY b.Booking_ID, b.Booking_Status, b.DropOff_Date, b.Pickup_Date,
             b.Booking_Priority, s.Student_Name, rc.Residential_Block
    ORDER BY (b.Booking_Priority = 'Y') DESC, b.Booking_ID DESC
    LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Staff Home</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .status-card { position: relative; }

        .status-text { display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 0.85rem; }
        .status-pending  { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }

        .priority-badge { background-color: #dc3545; color: white; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; font-weight: bold; }

        .header-main { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }

        /* Stats row */
        .stats-row { display: flex; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }

        .stat-pill { flex: 1; min-width: 120px; background: rgba(124,92,252,0.10); border: 1px solid rgba(124,92,252,0.25); border-radius: 14px; padding: 16px 18px; text-align: center; }
        .stat-pill .s-val   { font-size: 1.9rem; font-weight: 800; color: #b084ff; line-height: 1; }
        .stat-pill .s-label { font-size: 0.72rem; color: #8b82b5; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        .stat-pill.amber { 
        background-color: white;    
        border-color: rgba(245,158,11,0.35);
        }
        .stat-pill.amber .s-val { color: #f59e0b; }
        .stat-pill.green { 
        background-color: white;    
        border-color: rgba(34,197,94,0.3); }
        .stat-pill.green .s-val { color: #22c55e; }

        /* Quick actions */
        .quick-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }

        .qa-btn {
            flex: 1; min-width: 120px;
            background: rgba(36,18,83,0.7);
            border: 1px solid rgba(124,92,252,0.25);
            border-radius: 12px;
            color: #E8E9DE;
            padding: 13px 10px;
            font-size: 0.82rem; font-weight: 600;
            cursor: pointer; text-align: center; text-decoration: none;
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            transition: all 0.2s;
        }
        .qa-btn:hover { background: rgba(124,92,252,0.22); border-color: #7c5cfc; transform: translateY(-2px); }
        .qa-btn .qa-icon { font-size: 1.4rem; }

        /* Profile menu */
        #profileContainer { position: relative; display: inline-block; cursor: pointer; }
        #userImage { vertical-align: middle; margin-left: 5px; }
        #profileSelect { display: none; position: absolute; right: 0; top: 25px; background-color: #241253; border: 1px solid #E8E9DE; border-radius: 8px; min-width: 130px; z-index: 10; }
        #profileSelect.show { display: flex; flex-direction: column; }
        #profileSelect button { background: none; border: none; color: #E8E9DE; padding: 10px; text-align: left; width: 100%; cursor: pointer; font-size: 0.85rem; }
        #profileSelect button:hover { background-color: rgba(232,233,222,0.2); }

        .section-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #8b82b5; margin-bottom: 10px; }

        /* Sidebar nav links */
        .left-nav { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; width: 90%; }
        .left-nav a { background: rgba(36,18,83,0.6); border: 1px solid rgba(124,92,252,0.2); color: #E8E9DE; text-decoration: none; padding: 10px 16px; border-radius: 14px; font-size: 0.85rem; font-weight: 600; text-align: left; transition: all 0.2s; }
        .left-nav a:hover { background: rgba(124,92,252,0.22); border-color: #7c5cfc; transform: translateY(-1px); }

        /* View booking link */
        .view-booking-btn { background: #241253; color: #E8E9DE; border: none; padding: 7px 16px; border-radius: 14px; cursor: pointer; font-size: 0.8rem; font-weight: bold; text-decoration: none; width: auto; display: inline-block; }
        .view-booking-btn:hover { background: #37216d; transform: translateY(-1px); }

        @media (max-width: 768px) {
            .stats-row { flex-direction: column; }
            .quick-actions { flex-direction: column; }
            .header-main { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div id="wrapper">

    <!-- Left sidebar -->
    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='staffMainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='staffmainstatus.php'">
            Manage Bookings
        </button>
    </div>

    <!-- Right main area -->
    <div class="rightcontainer">

        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo htmlspecialchars($staff_name); ?></span>
            <span id="profileContainer">
                <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showProfile()">Profile</button>
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <h1>Dashboard</h1>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-pill amber">
                <div class="s-val"><?php echo $pending; ?></div>
                <div class="s-label">Pending</div>
            </div>
            <div class="stat-pill green">
                <div class="s-val"><?php echo $approved; ?></div>
                <div class="s-label">Approved</div>
            </div>
            <div class="stat-pill">
                <div class="s-val"><?php echo $total; ?></div>
                <div class="s-label">Total</div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="section-label">Quick Actions</div>
        <div class="quick-actions">
            <a class="qa-btn" href="Staffmainstatus.php">
                <span class="qa-icon"></span>Bookings
            </a>
            <a class="qa-btn" href="Staffverifypanel.php">
                <span class="qa-icon"></span>Verification
            </a>
            <a class="qa-btn" href="staffStudentList.php">
                <span class="qa-icon"></span>Students
            </a>
            <a class="qa-btn" href="staffpenalty.php">
                <span class="qa-icon"></span>Penalty
            </a>
            <a class="qa-btn" href="staffreport.php">
                <span class="qa-icon"></span>Reports
            </a>
        </div>

        <!-- Recent bookings -->
        <div class="section-label">Recent Bookings</div>

        <?php if ($recentQ && $recentQ->num_rows > 0):
            while ($row = $recentQ->fetch_assoc()):
                $bstat  = $row['Booking_Status'];
                $isPrio = $row['Booking_Priority'] === 'Y';
                $sc = strtolower($bstat) === 'pending' ? 'status-pending' : (strtolower($bstat) === 'approved' ? 'status-approved' : 'status-rejected');
        ?>
        <div class="status-card <?php echo $isPrio ? 'emergency-card' : ''; ?>">
            <div class="card-header">
                <div class="header-main">
                    <span class="order-id">ID: <?php echo $row['Booking_ID']; ?></span>
                    <?php if ($isPrio): ?><span class="priority-badge">EMERGENCY</span><?php endif; ?>
                    <span class="status-text <?php echo $sc; ?>"><?php echo htmlspecialchars($bstat); ?></span>
                </div>
            </div>
            <div class="summary-info">
                <p>Student   : <?php echo htmlspecialchars($row['Student_Name']); ?></p>
                <p>College   : <?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></p>
                <p>Drop-off  : <?php echo htmlspecialchars($row['DropOff_Date']); ?></p>
                <p>Pick-up   : <?php echo htmlspecialchars($row['Pickup_Date']); ?></p>
                <p>Items     : <?php echo $row['TotalItem']; ?></p>
                <p>Total fee : RM <?php echo number_format((float)$row['TotalFee'], 2); ?></p>
            </div>
            <div class="button-container">
                <a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="view-booking-btn">View Details →</a>
            </div>
        </div>
        <?php endwhile;
        else: ?>
            <p style="color:#8b82b5;">No bookings yet.</p>
        <?php endif; ?>

    </div>
</div>

<!-- Logout popup -->
<div id="logoutPopup" class="hidden">
    <div id="logoutText">
        <p>Are you sure you want to logout?</p>
        <div id="logoutButton">
            <button id="yesBTN" onclick="window.location.href='logout.php'">Yes</button>
            <button id="noBTN" onclick="showLog()">No</button>
        </div>
    </div>
</div>

<!-- Profile popup -->
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

