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

// Penalty rate defined here to match staffpenalty.php
$PENALTY_RATE = 2.00;

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus  = $_GET['status']  ?? 'all';
$filterDate    = $_GET['date']    ?? 'all';
$filterCollege = $_GET['college'] ?? 'all';
$search        = trim($_GET['q']  ?? '');

$where = ["1=1"];

if ($filterStatus !== 'all') {
    $fs = $conn->real_escape_string($filterStatus);
    $where[] = "LOWER(b.Booking_Status) = '$fs'";
}

if ($filterDate === 'this_month') {
    $where[] = "MONTH(b.DropOff_Date) = MONTH(CURDATE()) AND YEAR(b.DropOff_Date) = YEAR(CURDATE())";
} elseif ($filterDate === 'last_month') {
    $where[] = "MONTH(b.DropOff_Date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(b.DropOff_Date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
}

if ($filterCollege !== 'all') {
    $fc = $conn->real_escape_string($filterCollege);
    $where[] = "rc.Residential_Block = '$fc'";
}

if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(s.Student_Name LIKE '%$s%' OR s.Student_ID LIKE '%$s%' OR b.Booking_ID LIKE '%$s%' OR rc.Residential_Block LIKE '%$s%')";
}

$whereSQL = implode(" AND ", $where);

// ── College list for filter dropdown ─────────────────────────────────────────
$collegeList = [];
$clRes = $conn->query("SELECT DISTINCT Residential_Block FROM residential_college ORDER BY Residential_Block ASC");
while ($cl = $clRes->fetch_assoc()) $collegeList[] = $cl['Residential_Block'];

// ── Summary stats ─────────────────────────────────────────────────────────────
$totalBookings  = $conn->query("SELECT COUNT(*) AS c FROM booking")->fetch_assoc()['c'];
$totalRevenue   = $conn->query("SELECT COALESCE(SUM(Amount),0) AS r FROM payment WHERE LOWER(Payment_Status) IN ('y','paid')")->fetch_assoc()['r'];
$totalItems     = $conn->query("SELECT COALESCE(SUM(Quantity),0) AS c FROM item")->fetch_assoc()['c'];

// Overdue bookings penalty snapshot matching the rule from staffpenalty.php
$overdueRes     = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(DATEDIFF(CURDATE(), Pickup_Date)), 0) AS total_days FROM booking WHERE LOWER(Booking_Status)='approved' AND Pickup_Date < CURDATE()");
$overdueRow     = $overdueRes->fetch_assoc();
$overdueCount   = $overdueRow['c'];
$accumulatedPenalties = round($overdueRow['total_days'] * $PENALTY_RATE, 2);

// ── Booking status breakdown ──────────────────────────────────────────────────
$statusBreakdown = [];
$sbRes = $conn->query("SELECT Booking_Status, COUNT(*) AS c FROM booking GROUP BY Booking_Status ORDER BY c DESC");
while ($sb = $sbRes->fetch_assoc()) $statusBreakdown[] = $sb;

// ── Bookings per college (for bar chart) ──────────────────────────────────────
$collegeChart = [];
$ccRes = $conn->query("
    SELECT rc.Residential_Block, COUNT(b.Booking_ID) AS total,
           SUM(CASE WHEN LOWER(b.Booking_Status) = 'approved' THEN 1 ELSE 0 END) AS approved,
           SUM(CASE WHEN LOWER(b.Booking_Status) = 'pending'  THEN 1 ELSE 0 END) AS pending
    FROM booking b
    JOIN student s ON b.Student_ID = s.Student_ID
    JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    GROUP BY rc.Residential_Block
    ORDER BY total DESC
");
while ($cc = $ccRes->fetch_assoc()) $collegeChart[] = $cc;

// ── Monthly bookings trend (last 6 months) ────────────────────────────────────
$monthlyTrend = [];
$mtRes = $conn->query("
    SELECT DATE_FORMAT(Booking_Date, '%b %Y') AS month_label,
           DATE_FORMAT(Booking_Date, '%Y-%m') AS month_key,
           COUNT(*) AS total
    FROM booking
    WHERE Booking_Date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");
while ($mt = $mtRes->fetch_assoc()) $monthlyTrend[] = $mt;

// ── Storage usage per college ─────────────────────────────────────────────────
$storagePerCollege = [];
$spRes = $conn->query("
    SELECT rc.Residential_Block,
           COALESCE(SUM(ss.Size), 0) AS TotalCapacity,
           COALESCE((
               SELECT SUM(i2.Quantity) FROM item i2
               JOIN booking b2 ON i2.Booking_ID = b2.Booking_ID
               JOIN student s2 ON b2.Student_ID = s2.Student_ID
               WHERE s2.Residential_ID = rc.Residential_ID
                 AND LOWER(b2.Booking_Status) = 'approved'
           ), 0) AS UsedCapacity
    FROM residential_college rc
    JOIN storespace ss ON ss.Residential_ID = rc.Residential_ID
    GROUP BY rc.Residential_ID, rc.Residential_Block
    ORDER BY rc.Residential_Block
");
while ($sp = $spRes->fetch_assoc()) $storagePerCollege[] = $sp;

// ── Main report data ──────────────────────────────────────────────────────────
$reportData = $conn->query("
    SELECT
        b.Booking_ID,
        b.Booking_Status,
        b.Booking_Priority,
        b.DropOff_Date,
        b.Pickup_Date,
        s.Student_Name,
        s.Student_ID,
        rc.Residential_Block,
        DATEDIFF(CURDATE(), b.Pickup_Date) AS DaysOverdue,
        COALESCE(SUM(i.Quantity), 0)           AS TotalItem,
        COALESCE(SUM(i.Quantity * i.Price), 0) AS TotalFee,
        p.Payment_Status,
        p.Payment_Method,
        COALESCE(p.Amount, 0)                  AS PaidAmount
    FROM booking b
    LEFT JOIN student s             ON b.Student_ID      = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN item i                ON b.Booking_ID      = i.Booking_ID
    LEFT JOIN payment p             ON b.Booking_ID      = p.Booking_ID
    WHERE $whereSQL
    GROUP BY b.Booking_ID, b.Booking_Status, b.Booking_Priority, b.DropOff_Date,
             b.Pickup_Date, s.Student_Name, s.Student_ID, rc.Residential_Block,
             p.Payment_Status, p.Payment_Method, p.Amount
    ORDER BY b.Booking_ID DESC
");

// ── Storage usage ─────────────────────────────────────────────────────────────
$storageUsed  = $conn->query("SELECT COALESCE(SUM(i.Quantity),0) AS u FROM item i JOIN booking b ON i.Booking_ID = b.Booking_ID WHERE LOWER(b.Booking_Status) = 'approved'")->fetch_assoc()['u'];
$storageTotal = 500;
$storagePct   = min(100, round(($storageUsed / $storageTotal) * 100));

// Calculated filter totals for footer block before connection close
$tRes = $conn->query("
    SELECT 
        COALESCE(SUM(i.Quantity * i.Price), 0) AS tf,
        COALESCE(SUM(CASE WHEN LOWER(b.Booking_Status)='approved' AND b.Pickup_Date < CURDATE() THEN DATEDIFF(CURDATE(), b.Pickup_Date) ELSE 0 END), 0) AS total_overdue_days
    FROM booking b
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    WHERE $whereSQL
");
$footerTotals = $tRes->fetch_assoc();
$filteredTotalFee = $footerTotals['tf'];
$filteredPenaltyEst = round($footerTotals['total_overdue_days'] * $PENALTY_RATE, 2);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Reports & Penalties</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        /* ── Summary cards ── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 26px;
        }

        .sum-card {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 16px;
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
        }

        .sum-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 4px 4px 0 0;
        }

        .sum-card.purple::before { background: linear-gradient(90deg, #7c5cfc, #b084ff); }
        .sum-card.green::before  { background: #22c55e; }
        .sum-card.amber::before  { background: #f59e0b; }
        .sum-card.red::before    { background: #ef4444; }

        .sum-label { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .sum-value { font-size: 1.8rem; font-weight: 800; line-height: 1; color: #1e1b4b; }
        .sum-sub   { font-size: 0.72rem; color: #888; margin-top: 4px; }
        .sum-link { display: block; font-size: 0.72rem; color: #ef4444; margin-top: 4px; font-weight: bold; text-decoration: none; }
        .sum-link:hover { text-decoration: underline; }

        /* ── Storage bar ── */
        .storage-card {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 26px;
        }
        .storage-card h4 { font-size: 0.78rem; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 10px; }
        .storage-bar-wrap { background: #ddd; border-radius: 6px; height: 10px; overflow: hidden; margin-bottom: 6px; }
        .storage-bar-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, #7c5cfc, #b084ff); transition: width 0.5s; }
        .storage-bar-fill.warn { background: #f59e0b; }
        .storage-bar-fill.crit { background: #ef4444; }
        .storage-info { display: flex; justify-content: space-between; font-size: 0.78rem; color: #888; }

        /* ── Status breakdown ── */
        .breakdown-card {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 16px;
            padding: 18px 20px;
        }
        .breakdown-card h4 { font-size: 0.78rem; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 14px; }
        .breakdown-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #1e1b4b;
        }
        .breakdown-bar { flex: 1; background: #ddd; border-radius: 4px; height: 8px; overflow: hidden; }
        .breakdown-fill { height: 100%; border-radius: 4px; }
        .fill-pending   { background: #f59e0b; }
        .fill-approved  { background: #22c55e; }
        .fill-rejected  { background: #ef4444; }
        .fill-other     { background: #8b82b5; }
        .breakdown-count { min-width: 30px; text-align: right; font-weight: 700; color: #1e1b4b; }

        /* ── Section label ── */
        .section-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #8b82b5; margin-bottom: 12px; }

        /* ── Filters ── */
        .filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            align-items: center;
        }

        .filter-chip {
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid rgba(124,92,252,0.25);
            background: none;
            color: #8b82b5;
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .filter-chip:hover, .filter-chip.active {
            background: rgba(124,92,252,0.18);
            color: #b084ff;
            border-color: #7c5cfc;
        }

        .search-input {
            flex: 1;
            min-width: 180px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(124,92,252,0.25);
            color: #E8E9DE;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-family: inherit;
            outline: none;
        }
        .search-input::placeholder { color: #8b82b5; }
        .search-input:focus { border-color: #7c5cfc; }

        .search-btn {
            background: #241253;
            color: #E8E9DE;
            border: none;
            padding: 7px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: bold;
            font-family: inherit;
        }
        .search-btn:hover { background: #37216d; }

        /* ── Export button ── */
        .export-btn {
            background: linear-gradient(135deg, #7c5cfc, #b084ff);
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: opacity 0.2s;
        }
        .export-btn:hover { opacity: 0.85; }

        /* ── Report table ── */
        .table-wrap { overflow-x: auto; margin-bottom: 30px; }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            background: #f1f0ea;
            border-radius: 16px;
            overflow: hidden;
        }

        .report-table th {
            background: #e8e7df;
            color: #555;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 11px 14px;
            text-align: left;
            white-space: nowrap;
        }

        .report-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #dddcd6;
            color: #1e1b4b;
            white-space: nowrap;
        }

        .report-table tr:last-child td { border-bottom: none; }
        .report-table tr:hover td { background: rgba(124,92,252,0.05); }

        .badge { display: inline-block; padding: 2px 9px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-approved  { background: #d4edda; color: #155724; }
        .badge-rejected  { background: #f8d7da; color: #721c24; }
        .badge-collected { background: #e2e3e5; color: #495057; }
        .badge-other     { background: #cfe2ff; color: #084298; }
        .badge-paid      { background: #d4edda; color: #155724; }
        .badge-unpaid    { background: #f8d7da; color: #721c24; }
        .badge-later     { background: #fff3cd; color: #856404; }
        .prio-badge      { background: #dc3545; color: #fff; font-size: 0.65rem; padding: 1px 6px; border-radius: 8px; font-weight: 700; }
        .overdue-text    { color: #dc3545; font-weight: bold; font-size: 0.75rem; display: block; }

        .bid-link { color: #7c5cfc; font-weight: 700; text-decoration: none; }
        .bid-link:hover { text-decoration: underline; }

        /* ── Two-column layout for summary section ── */
        .summary-top {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 26px;
        }

        #profileContainer { position: relative; display: inline-block; cursor: pointer; }
        #userImage { vertical-align: middle; margin-left: 5px; }
        #profileSelect { display: none; position: absolute; right: 0; top: 25px; background-color: #241253; border: 1px solid #E8E9DE; border-radius: 8px; min-width: 130px; z-index: 10; }
        #profileSelect.show { display: flex; flex-direction: column; }
        #profileSelect button { background: none; border: none; color: #E8E9DE; padding: 10px; text-align: left; width: 100%; cursor: pointer; font-size: 0.85rem; }
        #profileSelect button:hover { background-color: rgba(232,233,222,0.2); }

        @media (max-width: 768px) {
            .summary-top { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div id="wrapper">
    <button class="back" onclick="history.back()">&#60; Back</button>

    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='staffMainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='staffMainStatus.php'">
            Dashboard
        </button>
    </div>

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

        <h1>Reports & Penalties</h1>

        <div class="summary-grid">
            <div class="sum-card purple">
                <div class="sum-label">Total Bookings</div>
                <div class="sum-value"><?php echo $totalBookings; ?></div>
                <div class="sum-sub">All time</div>
            </div>
            <div class="sum-card green">
                <div class="sum-label">Total Revenue</div>
                <div class="sum-value">RM <?php echo number_format((float)$totalRevenue, 2); ?></div>
                <div class="sum-sub">Paid payments</div>
            </div>
            <div class="sum-card amber">
                <div class="sum-label">Items in Storage</div>
                <div class="sum-value"><?php echo $totalItems; ?></div>
                <div class="sum-sub">Across all bookings</div>
            </div>
            <div class="sum-card red">
                <div class="sum-label">Overdue Bookings</div>
                <div class="sum-value"><?php echo $overdueCount; ?></div>
                <a href="staffpenalty.php" class="sum-link">Manage RM <?php echo number_format($accumulatedPenalties, 2); ?></a>
            </div>
        </div>

        <!-- ── Charts section ── -->
        <div class="section-label">Analytics</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:26px; flex-wrap:wrap;">

            <!-- Bookings per College bar chart -->
            <div style="background:#f1f0ea; border-radius:16px; padding:18px 20px; color:#1e1b4b;">
                <h4 style="font-size:0.78rem; text-transform:uppercase; color:#888; letter-spacing:0.5px; margin-bottom:14px;">Bookings per College</h4>
                <canvas id="collegeChart" height="200"></canvas>
            </div>

            <!-- Booking status doughnut chart -->
            <div style="background:#f1f0ea; border-radius:16px; padding:18px 20px; color:#1e1b4b;">
                <h4 style="font-size:0.78rem; text-transform:uppercase; color:#888; letter-spacing:0.5px; margin-bottom:14px;">Booking Status Distribution</h4>
                <canvas id="statusChart" height="200"></canvas>
            </div>

            <!-- Monthly trend line chart -->
            <div style="background:#f1f0ea; border-radius:16px; padding:18px 20px; color:#1e1b4b;">
                <h4 style="font-size:0.78rem; text-transform:uppercase; color:#888; letter-spacing:0.5px; margin-bottom:14px;">Booking Trend (Last 6 Months)</h4>
                <canvas id="trendChart" height="200"></canvas>
            </div>

            <!-- Storage usage per college bar chart -->
            <div style="background:#f1f0ea; border-radius:16px; padding:18px 20px; color:#1e1b4b;">
                <h4 style="font-size:0.78rem; text-transform:uppercase; color:#888; letter-spacing:0.5px; margin-bottom:14px;">Storage Usage per College (%)</h4>
                <canvas id="storageChart" height="200"></canvas>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
        // ── Bookings per college bar chart ────────────────────────────────────
        new Chart(document.getElementById('collegeChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($collegeChart, 'Residential_Block')); ?>,
                datasets: [
                    {
                        label: 'Approved',
                        data: <?php echo json_encode(array_column($collegeChart, 'approved')); ?>,
                        backgroundColor: '#22c55e'
                    },
                    {
                        label: 'Pending',
                        data: <?php echo json_encode(array_column($collegeChart, 'pending')); ?>,
                        backgroundColor: '#f59e0b'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Courier New' }, boxWidth: 12 } } },
                scales: {
                    x: { stacked: true, ticks: { font: { family: 'Courier New', size: 10 } } },
                    y: { stacked: true, beginAtZero: true, ticks: { font: { family: 'Courier New', size: 10 }, stepSize: 1 } }
                }
            }
        });

        // ── Status doughnut chart ─────────────────────────────────────────────
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($statusBreakdown, 'Booking_Status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($statusBreakdown, 'c')); ?>,
                    backgroundColor: ['#f59e0b','#22c55e','#ef4444','#8b82b5','#3b5bdb','#198754']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Courier New' }, boxWidth: 12 } } }
            }
        });

        // ── Monthly trend line chart ──────────────────────────────────────────
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyTrend, 'month_label')); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode(array_column($monthlyTrend, 'total')); ?>,
                    borderColor: '#7c5cfc',
                    backgroundColor: 'rgba(124,92,252,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#7c5cfc'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { font: { family: 'Courier New', size: 10 } } },
                    y: { beginAtZero: true, ticks: { font: { family: 'Courier New', size: 10 }, stepSize: 1 } }
                }
            }
        });

        // ── Storage usage per college horizontal bar chart ────────────────────
        new Chart(document.getElementById('storageChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($storagePerCollege, 'Residential_Block')); ?>,
                datasets: [{
                    label: 'Used (%)',
                    data: <?php
                        $usagePcts = array_map(function($r) {
                            return $r['TotalCapacity'] > 0 ? round(($r['UsedCapacity'] / $r['TotalCapacity']) * 100) : 0;
                        }, $storagePerCollege);
                        echo json_encode($usagePcts);
                    ?>,
                    backgroundColor: <?php
                        $colors = array_map(function($p) {
                            return $p >= 90 ? '#ef4444' : ($p >= 60 ? '#f59e0b' : '#22c55e');
                        }, $usagePcts);
                        echo json_encode($colors);
                    ?>
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, max: 100, ticks: { font: { family: 'Courier New', size: 10 }, callback: v => v + '%' } },
                    y: { ticks: { font: { family: 'Courier New', size: 10 } } }
                }
            }
        });
        </script>

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

    function exportCSV() {
        const table = document.getElementById('reportTable');
        const rows  = [];
        for (let row of table.rows) {
            const cols = [];
            for (let cell of row.cells) {
                const tmp = document.createElement('div');
                tmp.innerHTML = cell.innerHTML;
                cols.push('"' + (tmp.textContent || tmp.innerText || '').replace(/"/g, '""').trim() + '"');
            }
            rows.push(cols.join(','));
        }
        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const a    = document.createElement('a');
        a.href     = URL.createObjectURL(blob);
        a.download = 'VaulteM_Report_<?php echo date("Y-m-d"); ?>.csv';
        a.click();
    }
</script>
</body>
</html>