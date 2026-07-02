<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

require_once 'autoCancelExpired.php';
autoCancelExpiredBookings($conn);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';
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

// ── College list ──────────────────────────────────────────────────────────────
$collegeList = [];
$clRes = $conn->query("SELECT DISTINCT Residential_Block FROM residential_college ORDER BY Residential_Block ASC");
while ($cl = $clRes->fetch_assoc()) $collegeList[] = $cl['Residential_Block'];

// ── Main data ─────────────────────────────────────────────────────────────────
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
    LEFT JOIN student s              ON b.Student_ID      = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID  = rc.Residential_ID
    LEFT JOIN item i                 ON b.Booking_ID      = i.Booking_ID
    LEFT JOIN payment p              ON b.Booking_ID      = p.Booking_ID
    WHERE $whereSQL
    GROUP BY b.Booking_ID, b.Booking_Status, b.Booking_Priority, b.DropOff_Date,
             b.Pickup_Date, s.Student_Name, s.Student_ID, rc.Residential_Block,
             p.Payment_Status, p.Payment_Method, p.Amount
    ORDER BY (b.Booking_Priority = 'Y') DESC, b.Booking_ID DESC
");

// ── Footer totals ─────────────────────────────────────────────────────────────
$tRes = $conn->query("
    SELECT
        COALESCE(SUM(i.Quantity * i.Price), 0) AS tf,
        COALESCE(SUM(CASE WHEN LOWER(b.Booking_Status)='approved' AND b.Pickup_Date < CURDATE()
                     THEN DATEDIFF(CURDATE(), b.Pickup_Date) ELSE 0 END), 0) AS total_overdue_days
    FROM booking b
    LEFT JOIN item i    ON b.Booking_ID  = i.Booking_ID
    LEFT JOIN student s ON b.Student_ID  = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    WHERE $whereSQL
");
$footerTotals       = $tRes->fetch_assoc();
$filteredTotalFee   = $footerTotals['tf'];
$filteredPenaltyEst = round($footerTotals['total_overdue_days'] * $PENALTY_RATE, 2);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Booking Records</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        /* ── Filter bar ── */
        .filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
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
            min-width: 200px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(124,92,252,0.25);
            color: #E8E9DE;
            padding: 8px 14px;
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
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: bold;
            font-family: inherit;
        }
        .search-btn:hover { background: #37216d; }
        .export-btn {
            background: linear-gradient(135deg, #7c5cfc, #b084ff);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }
        .export-btn:hover { opacity: 0.85; }

        .section-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #8b82b5;
            margin-bottom: 12px;
        }
        .divider {
            color: #8b82b5;
            font-size: 0.75rem;
            padding: 0 4px;
        }

        /* ── Table ── */
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
        .report-table tr:hover td { background: rgba(124,92,252,0.04); }

        /* ── Badges ── */
        .badge { display: inline-block; padding: 2px 9px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-approved  { background: #d4edda; color: #155724; }
        .badge-rejected  { background: #f8d7da; color: #721c24; }
        .badge-collected { background: #e2e3e5; color: #495057; }
        .badge-cancelled { background: #e2e3e5; color: #495057; }
        .badge-other     { background: #cfe2ff; color: #084298; }
        .badge-paid      { background: #d4edda; color: #155724; }
        .badge-unpaid    { background: #f8d7da; color: #721c24; }
        .badge-later     { background: #fff3cd; color: #856404; }
        .prio-badge      { background: #dc3545; color: #fff; font-size: 0.65rem; padding: 1px 6px; border-radius: 8px; font-weight: 700; }
        .overdue-text    { color: #dc3545; font-weight: bold; font-size: 0.75rem; display: block; }
        .bid-link { color: #7c5cfc; font-weight: 700; text-decoration: none; }
        .bid-link:hover { text-decoration: underline; }

        /* ── Profile menu ── */
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

        <h1>Booking Records</h1>

        <div class="section-label">Filter and Search</div>

        <form method="GET" style="margin-bottom:0;">
            <!-- Status filters -->
            <div class="filter-bar">
                <?php
                $statuses = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled_unpaid'=>'Cancelled (Unpaid)','collected'=>'Collected'];
                foreach ($statuses as $sv => $sl):
                ?>
                <a href="?status=<?php echo $sv; ?>&date=<?php echo $filterDate; ?>&college=<?php echo urlencode($filterCollege); ?>&q=<?php echo urlencode($search); ?>"
                   class="filter-chip <?php echo $filterStatus === $sv ? 'active' : ''; ?>">
                    <?php echo $sl; ?>
                </a>
                <?php endforeach; ?>

                <span class="divider">|</span>

                <!-- Date filters -->
                <?php
                $dates = ['all'=>'All Time','this_month'=>'This Month','last_month'=>'Last Month'];
                foreach ($dates as $dv => $dl):
                ?>
                <a href="?status=<?php echo $filterStatus; ?>&date=<?php echo $dv; ?>&college=<?php echo urlencode($filterCollege); ?>&q=<?php echo urlencode($search); ?>"
                   class="filter-chip <?php echo $filterDate === $dv ? 'active' : ''; ?>">
                    <?php echo $dl; ?>
                </a>
                <?php endforeach; ?>

                <span class="divider">|</span>

                <!-- College filters -->
                <a href="?status=<?php echo $filterStatus; ?>&date=<?php echo $filterDate; ?>&college=all&q=<?php echo urlencode($search); ?>"
                   class="filter-chip <?php echo $filterCollege === 'all' ? 'active' : ''; ?>">
                    All Colleges
                </a>
                <?php foreach ($collegeList as $col): ?>
                <a href="?status=<?php echo $filterStatus; ?>&date=<?php echo $filterDate; ?>&college=<?php echo urlencode($col); ?>&q=<?php echo urlencode($search); ?>"
                   class="filter-chip <?php echo $filterCollege === $col ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($col); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Search row -->
            <div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                <input class="search-input" type="text" name="q"
                       placeholder="Search by student name, ID, booking ID, or college..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="status"  value="<?php echo $filterStatus; ?>">
                <input type="hidden" name="date"    value="<?php echo $filterDate; ?>">
                <input type="hidden" name="college" value="<?php echo htmlspecialchars($filterCollege); ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if ($search || $filterCollege !== 'all' || $filterStatus !== 'all' || $filterDate !== 'all'): ?>
                <a href="staffBookingRecords.php" style="color:#8b82b5; font-size:0.82rem; line-height:2.4;">Clear All</a>
                <?php endif; ?>
                <button type="button" class="export-btn" onclick="exportCSV()">Export CSV</button>
            </div>
        </form>

        <!-- Table -->
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
                        <th>Storage Fee (RM)</th>
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
                        $isPaid  = in_array($ps, ['y','paid']);
                        $isLater = in_array($ps, ['p','pending']);

                        if ($bs === 'pending')            $bBadge = 'badge-pending';
                        elseif ($bs === 'approved')       $bBadge = 'badge-approved';
                        elseif ($bs === 'rejected')       $bBadge = 'badge-rejected';
                        elseif ($bs === 'cancelled_unpaid') $bBadge = 'badge-cancelled';
                        elseif ($bs === 'collected')      $bBadge = 'badge-collected';
                        else                               $bBadge = 'badge-other';

                        $statusLabel = ($bs === 'cancelled_unpaid') ? 'Cancelled (Unpaid)' : $row['Booking_Status'];

                        $pBadge = $isPaid ? 'badge-paid' : ($isLater ? 'badge-later' : 'badge-unpaid');
                        $pLabel = $isPaid ? 'Paid' : ($isLater ? 'Pay Later' : 'Unpaid');

                        $daysOverdue  = max(0, (int)$row['DaysOverdue']);
                        $isOverdueNow = ($bs === 'approved' && $daysOverdue > 0);
                ?>
                <tr>
                    <td><a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="bid-link">#<?php echo $row['Booking_ID']; ?></a></td>
                    <td><?php echo htmlspecialchars($row['Student_Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Student_ID']); ?></td>
                    <td><?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="badge <?php echo $bBadge; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                        <?php if ($isOverdueNow): ?>
                            <span class="overdue-text"><?php echo $daysOverdue; ?>d Overdue</span>
                        <?php endif; ?>
                    </td>
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
                        <td colspan="8" style="padding:10px 14px; color:#555;">
                            Total (<?php echo $rowCount; ?> records)
                        </td>
                        <td style="padding:10px 14px; color:#1e1b4b;">—</td>
                        <td style="padding:10px 14px; color:#1e1b4b;">
                            RM <?php echo number_format((float)$filteredTotalFee, 2); ?>
                        </td>
                        <td colspan="3" style="padding:10px 14px; text-align:right; color:#dc3545;">
                            Est. Penalty: RM <?php echo number_format($filteredPenaltyEst, 2); ?>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

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
        a.download = 'VaulteM_Bookings_<?php echo date("Y-m-d"); ?>.csv';
        a.click();
    }
</script>
</body>
</html>