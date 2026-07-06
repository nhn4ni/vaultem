<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);
$conn->query("SET time_zone = '+08:00'"); // keep MySQL NOW()/CURDATE() aligned with Malaysia time
date_default_timezone_set('Asia/Kuala_Lumpur');

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';

// ── Pending count (for the "new bookings" task line) ──────────────────────────
function safeCount(mysqli $conn, string $sql): int {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}
$pending = safeCount($conn, "SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status)='pending'");

// ── Task queries: everything requiring staff action ───────────────────────────

// 1) Paid & approved, drop-off is today, but no photo uploaded yet
$needUploadRes = $conn->query("
    SELECT b.Booking_ID, s.Student_Name
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    WHERE LOWER(b.Booking_Status) = 'approved'
      AND b.DropOff_Date = CURDATE()
      AND (b.Dropoff_Photo IS NULL OR b.Dropoff_Photo = '')
      AND EXISTS (
            SELECT 1 FROM payment p
            WHERE p.Booking_ID = b.Booking_ID AND UPPER(p.Payment_Status) = 'Y'
          )
");
$needUploadList = [];
while ($r = $needUploadRes->fetch_assoc()) $needUploadList[] = $r;

// 2) Photo uploaded by staff but not yet sent to student for confirmation
$needSendRes = $conn->query("
    SELECT b.Booking_ID, s.Student_Name
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    WHERE b.Dropoff_Status = 'Uploaded'
");
$needSendList = [];
while ($r = $needSendRes->fetch_assoc()) $needSendList[] = $r;

// 3) Student rejected the drop-off photo — needs staff to recheck & re-upload
$needReuploadRes = $conn->query("
    SELECT b.Booking_ID, s.Student_Name
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    WHERE b.Dropoff_Status = 'Rejected'
");
$needReuploadList = [];
while ($r = $needReuploadRes->fetch_assoc()) $needReuploadList[] = $r;

// 4) Pickup is today, drop-off photo already confirmed — ready to send pickup verification
$needVerifyRes = $conn->query("
    SELECT b.Booking_ID, s.Student_Name
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    WHERE LOWER(b.Booking_Status) = 'approved'
      AND b.Dropoff_Status = 'Confirmed'
      AND b.Pickup_Date = CURDATE()
");
$needVerifyList = [];
while ($r = $needVerifyRes->fetch_assoc()) $needVerifyList[] = $r;

// 5) Past the 11AM pickup deadline and still not collected — penalty accruing
$overdueRes = $conn->query("
    SELECT b.Booking_ID, s.Student_Name, b.Pickup_Date
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    WHERE LOWER(b.Booking_Status) = 'approved'
      AND DATE_ADD(b.Pickup_Date, INTERVAL 11 HOUR) < NOW()
");
$overdueList = [];
while ($r = $overdueRes->fetch_assoc()) $overdueList[] = $r;

$totalTasks = count($needUploadList) + count($needSendList) + count($needReuploadList)
            + count($needVerifyList) + count($overdueList) + (int)$pending;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Notifications</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .back-btn { background: none; border: none; color: #241253; font-size: 1rem; font-weight: bold; cursor: pointer; padding: 14px 0 4px 0; display: block; }
        .back-btn:hover { text-decoration: underline; }

        /* ── Manifest / ledger style, matching Daily Tasks design ── */
        .tasks-panel {
            background: #f1f0ea;
            border: 1px solid #241253;
            border-radius: 6px;
            margin-bottom: 22px;
            font-family: 'Courier New', Courier, monospace;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(36,18,83,0.12);
        }
        .tasks-panel-header {
            background: #241253;
            color: #E8E9DE;
            padding: 14px 22px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 3px solid #7c5cfc;
        }
        .tasks-panel-header h2 {
            margin: 0;
            font-size: 0.88rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .tasks-date { font-size: 0.72rem; opacity: 0.65; letter-spacing: 0.5px; }

        .task-row {
            display: flex;
            gap: 14px;
            padding: 14px 22px;
            border-bottom: 1px solid #ddd8ca;
            transition: background 0.15s;
        }
        .task-row:last-child { border-bottom: none; }
        .task-row.is-done { opacity: 0.5; }
        .task-row:not(.is-done):hover { background: rgba(124,92,252,0.05); }

        .task-index {
            font-size: 0.75rem;
            color: #a39fc9;
            font-weight: 800;
            min-width: 20px;
            padding-top: 1px;
        }
        .task-body { flex: 1; min-width: 0; }
        .task-row-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }
        .task-row-title {
            font-size: 0.83rem;
            font-weight: 700;
            color: #241253;
        }
        .task-status-count {
            font-size: 0.8rem;
            font-weight: 800;
            color: #7c5cfc;
            white-space: nowrap;
        }
        .task-status-done {
            font-size: 0.72rem;
            font-weight: 600;
            color: #8b8578;
            white-space: nowrap;
        }
        .task-overdue .task-status-count { color: #dc2626; }
        .task-overdue:not(.is-done) { background: rgba(220,38,38,0.03); }

        .task-items { margin-top: 8px; }
        .task-item-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
            padding: 6px 0;
            font-size: 0.79rem;
            color: #241253;
            text-decoration: none;
            border-bottom: 1px dotted #d8d3c5;
        }
        .task-item-row:last-child { border-bottom: none; }
        .task-item-row:hover .tgo { text-decoration: underline; color: #7c5cfc; }
        .task-item-row .tid { color: #7c5cfc; font-weight: 700; margin-right: 8px; }
        .task-item-row .tgo { color: #8b8578; font-size: 0.71rem; flex-shrink: 0; }

        .tasks-all-clear-banner {
            text-align: center;
            color: #22c55e;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 22px 0;
            letter-spacing: 0.5px;
        }

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
        <button type="button" id="booking" onclick="window.location.href='staffMainStatus.php'">
            Back to Dashboard
        </button>
    </div>

    <div class="rightcontainer">

        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo htmlspecialchars($staff_name); ?></span>
            <span id="profileContainer">
                <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="window.location.href='staffMainStatus.php'">Dashboard</button>
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <button class="back-btn" onclick="window.location.href='staffMainStatus.php'">&#60; Back</button>
        <h1>Notifications</h1>

        <div class="tasks-panel">
            <div class="tasks-panel-header">
                <h2>Daily Tasks</h2>
                <span class="tasks-date"><?php echo date('d M Y'); ?></span>
            </div>

            <?php if ($totalTasks === 0): ?>
                <div class="tasks-all-clear-banner">No outstanding tasks today.</div>
            <?php else: ?>

                <?php
                $taskGroups = [
                    [
                        'class' => 'task-upload',
                        'title' => 'Upload drop-off photo — due today',
                        'items' => $needUploadList,
                        'link'  => function($t) { return "staffBookingDetail.php?id={$t['Booking_ID']}"; },
                    ],
                    [
                        'class' => 'task-send',
                        'title' => 'Send reviewed photo to student',
                        'items' => $needSendList,
                        'link'  => function($t) { return "staffBookingDetail.php?id={$t['Booking_ID']}"; },
                    ],
                    [
                        'class' => 'task-reupload',
                        'title' => 'Recheck & re-upload rejected photo',
                        'items' => $needReuploadList,
                        'link'  => function($t) { return "staffBookingDetail.php?id={$t['Booking_ID']}"; },
                    ],
                    [
                        'class' => 'task-verify',
                        'title' => 'Send pickup verification — due today',
                        'items' => $needVerifyList,
                        'link'  => function($t) { return "staffBookingDetail.php?id={$t['Booking_ID']}"; },
                    ],
                    [
                        'class' => 'task-overdue',
                        'title' => 'Overdue pickup — penalty accruing',
                        'items' => $overdueList,
                        'link'  => function($t) { return "staffpenalty.php"; },
                        'extra' => function($t) { return ' — since ' . date('d/m/Y', strtotime($t['Pickup_Date'])); },
                    ],
                    [
                        'class' => 'task-pending',
                        'title' => 'New bookings awaiting approval',
                        'items' => $pending > 0 ? [['Booking_ID' => null, 'Student_Name' => null]] : [],
                        'link'  => function($t) { return "staffMainStatus.php?tab=pending"; },
                        'count_override' => $pending,
                        'single_line' => $pending > 0 ? "Review {$pending} pending booking" . ($pending !== 1 ? 's' : '') . ' &rarr;' : null,
                    ],
                ];

                $idx = 0;
                foreach ($taskGroups as $g):
                    $idx++;
                    $count = $g['count_override'] ?? count($g['items']);
                    $isDone = $count === 0;
                ?>
                <div class="task-row <?php echo $g['class']; ?> <?php echo $isDone ? 'is-done' : ''; ?>">
                    <span class="task-index"><?php echo str_pad($idx, 2, '0', STR_PAD_LEFT); ?></span>
                    <div class="task-body">
                        <div class="task-row-head">
                            <span class="task-row-title"><?php echo htmlspecialchars($g['title']); ?></span>
                            <?php if ($isDone): ?>
                                <span class="task-status-done">Cleared</span>
                            <?php else: ?>
                                <span class="task-status-count">&times;<?php echo $count; ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isDone): ?>
                        <div class="task-items">
                            <?php if (!empty($g['single_line'])): ?>
                                <a href="<?php echo $g['link'](null); ?>" class="task-item-row">
                                    <span><?php echo $g['single_line']; ?></span>
                                </a>
                            <?php else: foreach ($g['items'] as $t): ?>
                                <a href="<?php echo $g['link']($t); ?>" class="task-item-row">
                                    <span><span class="tid">#<?php echo $t['Booking_ID']; ?></span><?php echo htmlspecialchars($t['Student_Name']); ?><?php echo isset($g['extra']) ? $g['extra']($t) : ''; ?></span>
                                    <span class="tgo">view &rarr;</span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php endif; ?>
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