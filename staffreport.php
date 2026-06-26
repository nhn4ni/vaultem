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

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$filterDate   = $_GET['date']   ?? 'all';   // this_month, last_month, all
$search       = trim($_GET['q'] ?? '');

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

if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(s.Student_Name LIKE '%$s%' OR s.Student_ID LIKE '%$s%' OR b.Booking_ID LIKE '%$s%')";
}

$whereSQL = implode(" AND ", $where);

// ── Summary stats ─────────────────────────────────────────────────────────────
$totalBookings  = $conn->query("SELECT COUNT(*) AS c FROM booking")->fetch_assoc()['c'];
$totalRevenue   = $conn->query("SELECT COALESCE(SUM(Amount),0) AS r FROM payment WHERE LOWER(Payment_Status) IN ('y','paid')")->fetch_assoc()['r'];
$totalItems     = $conn->query("SELECT COALESCE(SUM(Quantity),0) AS c FROM item")->fetch_assoc()['c'];
$overdueCount   = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status)='approved' AND Pickup_Date < CURDATE()")->fetch_assoc()['c'];

// ── Booking status breakdown ──────────────────────────────────────────────────
$statusBreakdown = [];
$sbRes = $conn->query("SELECT Booking_Status, COUNT(*) AS c FROM booking GROUP BY Booking_Status ORDER BY c DESC");
while ($sb = $sbRes->fetch_assoc()) $statusBreakdown[] = $sb;

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Reports</title>
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

        .bid-link { color: #7c5cfc; font-weight: 700; text-decoration: none; }
        .bid-link:hover { text-decoration: underline; }

        /* ── Two-column layout for summary section ── */
        .summary-top {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 26px;
        }
        .breakdown-card {
            background: #f1f0ea;
            color: #1e1b4b;
            border-radius: 16px;
            padding: 18px 20px;
        }
        .breakdown-card h4 { font-size: 0.78rem; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 14px; }

        /* Profile menu */
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

    <!-- Left sidebar -->
    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='staffMainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='Staffmainstatus.php'">
            Dashboard
        </button>
    </div>

    <!-- Right content -->
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

        <h1>Reports</h1>

        <!-- ── Summary stat cards ── -->
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
                <div class="sum-label">Overdue</div>
                <div class="sum-value"><?php echo $overdueCount; ?></div>
                <div class="sum-sub">Past pick-up date</div>
            </div>
        </div>

        <!-- ── Storage + Breakdown row ── -->
        <div class="summary-top">

            <!-- Storage capacity -->
            <div class="storage-card">
                <h4>Storage Capacity</h4>
                <div class="storage-bar-wrap">
                    <div class="storage-bar-fill <?php echo $storagePct >= 90 ? 'crit' : ($storagePct >= 80 ? 'warn' : ''); ?>"
                         style="width:<?php echo $storagePct; ?>%"></div>
                </div>
                <div class="storage-info">
                    <span><?php echo $storageUsed; ?> / <?php echo $storageTotal; ?> units used</span>
                    <span style="font-weight:700; color:<?php echo $storagePct >= 90 ? '#ef4444' : ($storagePct >= 80 ? '#f59e0b' : '#22c55e'); ?>">
                        <?php echo $storagePct; ?>%
                    </span>
                </div>
                <?php if ($storagePct >= 80): ?>
                <p style="color:#f59e0b; font-size:0.75rem; margin-top:8px; font-weight:600;">⚠ Manual approval required for new bookings.</p>
                <?php endif; ?>
            </div>

            <!-- Status breakdown -->
            <div class="breakdown-card">
                <h4>Booking Status Breakdown</h4>
                <?php
                $colorMap = [
                    'pending'  => 'fill-pending',
                    'approved' => 'fill-approved',
                    'rejected' => 'fill-rejected',
                ];
                foreach ($statusBreakdown as $sb):
                    $pct = $totalBookings > 0 ? round(($sb['c'] / $totalBookings) * 100) : 0;
                    $cls = $colorMap[strtolower($sb['Booking_Status'])] ?? 'fill-other';
                ?>
                <div class="breakdown-row">
                    <span style="min-width:110px; font-size:0.8rem;"><?php echo htmlspecialchars($sb['Booking_Status']); ?></span>
                    <div class="breakdown-bar">
                        <div class="breakdown-fill <?php echo $cls; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <span class="breakdown-count"><?php echo $sb['c']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Filters + Export ── -->
        <div class="section-label">Booking Records</div>

        <form method="GET" style="margin-bottom:0;">
            <div class="filter-bar">
                <!-- Status filters -->
                <?php
                $statuses = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'collected' => 'Collected'];
                foreach ($statuses as $sv => $sl):
                ?>
                <a href="?status=<?php echo $sv; ?>&date=<?php echo $filterDate; ?>&q=<?php echo urlencode($search); ?>"
                   class="filter-chip <?php echo $filterStatus === $sv ? 'active' : ''; ?>">
                    <?php echo $sl; ?>
                </a>
                <?php endforeach; ?>

                <span style="color:#8b82b5; font-size:0.75rem; padding: 0 4px;">|</span>

                <!-- Date filters -->
                <?php
                $dates = ['all' => 'All Time', 'this_month' => 'This Month', 'last_month' => 'Last Month'];
                foreach ($dates as $dv => $dl):
                ?>
                <a href="?status=<?php echo $filterStatus; ?>&date=<?php echo $dv; ?>&q=<?php echo urlencode($search); ?>"
                   class="filter-chip <?php echo $filterDate === $dv ? 'active' : ''; ?>">
                    <?php echo $dl; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Search + export row -->
            <div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                <input class="search-input" type="text" name="q" placeholder="Search by student name, ID, or booking ID…"
                       value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="status" value="<?php echo $filterStatus; ?>">
                <input type="hidden" name="date"   value="<?php echo $filterDate; ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if ($search): ?>
                <a href="?status=<?php echo $filterStatus; ?>&date=<?php echo $filterDate; ?>"
                   style="color:#8b82b5; font-size:0.82rem; line-height:2.4;">✕ Clear</a>
                <?php endif; ?>
                <button type="button" class="export-btn" onclick="exportCSV()"> Export CSV</button>
            </div>
        </form>

        <!-- ── Report table ── -->
        <div class="table-wrap">
            <table class="report-table" id="reportTable">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>College</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Drop-off</th>
                        <th>Pick-up</th>
                        <th>Items</th>
                        <th>Total Fee (RM)</th>
                        <th>Payment</th>
                        <th>Method</th>
                        <th>Paid (RM)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rowCount = 0;
                if ($reportData && $reportData->num_rows > 0):
                    while ($row = $reportData->fetch_assoc()):
                        $rowCount++;
                        $bs  = strtolower($row['Booking_Status']);
                        $ps  = strtolower($row['Payment_Status'] ?? '');
                        $isPaid = in_array($ps, ['y','paid']);
                        $isLater = in_array($ps, ['p','pending']);

                        if ($bs === 'pending')        $bBadge = 'badge-pending';
                        elseif ($bs === 'approved')   $bBadge = 'badge-approved';
                        elseif ($bs === 'rejected')   $bBadge = 'badge-rejected';
                        elseif ($bs === 'collected')  $bBadge = 'badge-collected';
                        else                          $bBadge = 'badge-other';

                        $pBadge = $isPaid ? 'badge-paid' : ($isLater ? 'badge-later' : 'badge-unpaid');
                        $pLabel = $isPaid ? 'Paid' : ($isLater ? 'Pay Later' : 'Unpaid');
                ?>
                <tr>
                    <td><a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="bid-link">#<?php echo $row['Booking_ID']; ?></a></td>
                    <td><?php echo htmlspecialchars($row['Student_Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Student_ID']); ?></td>
                    <td><?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></td>
                    <td><span class="badge <?php echo $bBadge; ?>"><?php echo htmlspecialchars($row['Booking_Status']); ?></span></td>
                    <td><?php echo $row['Booking_Priority'] === 'Y' ? '<span class="prio-badge">EMERGENCY</span>' : 'Normal'; ?></td>
                    <td><?php echo htmlspecialchars($row['DropOff_Date']); ?></td>
                    <td><?php echo htmlspecialchars($row['Pickup_Date']); ?></td>
                    <td><?php echo $row['TotalItem']; ?></td>
                    <td><?php echo number_format((float)$row['TotalFee'], 2); ?></td>
                    <td><span class="badge <?php echo $pBadge; ?>"><?php echo $pLabel; ?></span></td>
                    <td><?php echo htmlspecialchars($row['Payment_Method'] ?? '-'); ?></td>
                    <td><?php echo number_format((float)$row['PaidAmount'], 2); ?></td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="13" style="text-align:center; color:#888; padding:30px;">No records found.</td></tr>
                <?php endif; ?>
                </tbody>
                <?php if ($rowCount > 0): ?>
                <tfoot>
                    <tr style="background:#e8e7df; font-weight:700; font-size:0.82rem;">
                        <td colspan="8" style="padding:10px 14px; color:#555;">Total (<?php echo $rowCount; ?> records)</td>
                        <td style="padding:10px 14px; color:#1e1b4b;">—</td>
                        <td style="padding:10px 14px; color:#1e1b4b;">
                            <?php
                            // Re-query totals based on current filter
                            $totConn = new mysqli("localhost","root","","utem_accommodation");
                            $tRes = $totConn->query("
                                SELECT COALESCE(SUM(i.Quantity * i.Price),0) AS tf
                                FROM booking b
                                LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
                                LEFT JOIN student s ON b.Student_ID = s.Student_ID
                                WHERE $whereSQL
                            ");
                            $tf = $tRes ? $tRes->fetch_assoc()['tf'] : 0;
                            $totConn->close();
                            echo number_format((float)$tf, 2);
                            ?>
                        </td>
                        <td colspan="3" style="padding:10px 14px;"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

    </div><!-- /rightcontainer -->
</div><!-- /wrapper -->

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

    function exportCSV() {
        const table = document.getElementById('reportTable');
        const rows  = [];
        for (let row of table.rows) {
            const cols = [];
            for (let cell of row.cells) {
                // Strip HTML tags from cell content
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