<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);
$conn->query("SET time_zone = '+08:00'"); // keep MySQL NOW()/CURDATE() aligned with Malaysia time

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';

// ── Handle inline approve / reject ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Approve — space was already deducted in form.php at booking time,
    // so approval must NOT touch storespace again (that caused double-deduction).
    if (isset($_POST['approve_id'])) {
        $bid = intval($_POST['approve_id']);
        $conn->query("UPDATE booking SET Booking_Status = 'Approved' WHERE Booking_ID = $bid AND LOWER(Booking_Status) = 'pending'");
        $feeRes   = $conn->query("SELECT COALESCE(SUM(Quantity * Price), 0) AS tf FROM item WHERE Booking_ID = $bid");
        $totalFee = (float)$feeRes->fetch_assoc()['tf'];
        $conn->query("INSERT INTO payment (Payment_Method, Payment_Status, Payment_Date, Amount, Booking_ID)
                      VALUES ('Online', 'N', CURDATE(), $totalFee, $bid)
                      ON DUPLICATE KEY UPDATE Payment_Status='N', Amount=$totalFee");
        header("Location: staffMainStatus.php?tab=pending&msg=approved&bid=$bid"); exit();
    }

    if (isset($_POST['reject_id'])) {
        $bid    = intval($_POST['reject_id']);
        $reason = trim($_POST['reject_reason'] ?? '');
        if (!$reason) {
            header("Location: staffMainStatus.php?tab=pending&msg=no_reason"); exit();
        }
        $proofPath = '';
        if (isset($_FILES['reject_photo']) && $_FILES['reject_photo']['error'] === 0) {
            $ext     = strtolower(pathinfo($_FILES['reject_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $dir = 'uploads/rejection/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'reject_' . $bid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['reject_photo']['tmp_name'], $dir . $fname)) {
                    $proofPath = $dir . $fname;
                }
            }
        }
        $rEsc = $conn->real_escape_string($reason);
        $pEsc = $conn->real_escape_string($proofPath);
        $rejectResult = $conn->query("UPDATE booking SET Booking_Status='Rejected', Rejection_Reason='$rEsc', Rejection_Photo='$pEsc'
                      WHERE Booking_ID=$bid AND LOWER(Booking_Status)='pending'");
        if (!$rejectResult) {
            die("Reject UPDATE failed: " . $conn->error);
        }
        if ($conn->affected_rows === 0) {
            die("Reject UPDATE ran but changed 0 rows. Booking_ID=$bid may not exist, or its Booking_Status is not 'pending'.");
        }

        // ── Restore reserved space back to storespace since this booking is no longer active ──
        $spRes = $conn->query("SELECT SUM(Quantity) AS tot, Space_ID FROM item WHERE Booking_ID = $bid GROUP BY Space_ID");
        while ($sr = $spRes->fetch_assoc()) {
            $conn->query("UPDATE storespace SET Size = Size + {$sr['tot']} WHERE Space_ID = {$sr['Space_ID']}");
        }

        header("Location: staffMainStatus.php?tab=pending&msg=rejected&bid=$bid"); exit();
    }
}

// ── Counts ────────────────────────────────────────────────────────────────────
$pending         = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status)='pending'")->fetch_assoc()['c'];
$approved        = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status)='approved'")->fetch_assoc()['c'];
$total           = $conn->query("SELECT COUNT(*) AS c FROM booking")->fetch_assoc()['c'];
$collected       = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE LOWER(Booking_Status)='collected'")->fetch_assoc()['c'];
$pendingStudents = $conn->query("SELECT COUNT(DISTINCT Student_ID) AS c FROM booking WHERE LOWER(Booking_Status)='pending'")->fetch_assoc()['c'];

// ── Notification badge count: everything needing staff action today ──────────
function safeCount(mysqli $conn, string $sql): int {
    $res = $conn->query($sql);
    if (!$res) return 0; // never let a failed query take down the whole dashboard
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

$needUploadCount = safeCount($conn, "
    SELECT COUNT(*) AS c
    FROM booking b
    WHERE LOWER(b.Booking_Status) = 'approved'
      AND b.DropOff_Date = CURDATE()
      AND (b.Dropoff_Photo IS NULL OR b.Dropoff_Photo = '')
      AND EXISTS (
            SELECT 1 FROM payment p
            WHERE p.Booking_ID = b.Booking_ID AND UPPER(p.Payment_Status) = 'Y'
          )
");

$needSendCount = safeCount($conn, "SELECT COUNT(*) AS c FROM booking WHERE Dropoff_Status = 'Uploaded'");

$needReuploadCount = safeCount($conn, "SELECT COUNT(*) AS c FROM booking WHERE Dropoff_Status = 'Rejected'");

$needVerifyCount = safeCount($conn, "
    SELECT COUNT(*) AS c FROM booking
    WHERE LOWER(Booking_Status) = 'approved' AND Dropoff_Status = 'Confirmed' AND Pickup_Date = CURDATE()
");

$overdueCount = safeCount($conn, "
    SELECT COUNT(*) AS c FROM booking
    WHERE LOWER(Booking_Status) = 'approved' AND DATE_ADD(Pickup_Date, INTERVAL 11 HOUR) < NOW()
");

$notifTotal = $needUploadCount + $needSendCount + $needReuploadCount
            + $needVerifyCount + $overdueCount + (int)$pending;

// ── One-time "you have tasks" popup, shown once per login session ────────────
$showTaskPopup = ($notifTotal > 0 && empty($_SESSION['task_popup_shown']));
if ($showTaskPopup) {
    $_SESSION['task_popup_shown'] = true;
}

// ── Tab queries ───────────────────────────────────────────────────────────────
function bookingQuery(mysqli $conn, array $statuses, string $order = "b.Booking_ID DESC"): mysqli_result|false {
    $in = "'" . implode("','", $statuses) . "'";
    return $conn->query("
        SELECT b.Booking_ID, b.Booking_Status, b.DropOff_Date, b.Pickup_Date,
               b.Booking_Priority, b.Booking_Date,
               s.Student_Name, s.Student_ID, rc.Residential_Block,
               (SELECT COALESCE(SUM(i.Quantity), 0) FROM item i WHERE i.Booking_ID = b.Booking_ID)           AS TotalItem,
               (SELECT COALESCE(SUM(i.Quantity * i.Price), 0) FROM item i WHERE i.Booking_ID = b.Booking_ID) AS TotalFee,
               (SELECT Payment_Status FROM payment WHERE Booking_ID = b.Booking_ID LIMIT 1) AS Payment_Status,
               (SELECT Amount FROM payment WHERE Booking_ID = b.Booking_ID LIMIT 1) AS PaymentAmount
        FROM booking b
        LEFT JOIN student s ON b.Student_ID = s.Student_ID
        LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
        WHERE LOWER(b.Booking_Status) IN ($in)
        ORDER BY (b.Booking_Priority='Y') DESC, $order
    ");
}

// Pending — oldest first so longest waiting gets reviewed first
$pendingQ   = bookingQuery($conn, ['pending'], 'b.Booking_ID ASC');
// Approved — newest first
$approvedQ  = bookingQuery($conn, ['approved','verification_sent','confirmed','released'], 'b.Booking_ID DESC');
// Cancelled/rejected — oldest first (first out, stack at bottom)
$cancelledQ = bookingQuery($conn, ['rejected','cancelled','cancelled_unpaid','waived','settled'], 'b.Booking_ID ASC');
// Collected — newest first
$collectedQ = bookingQuery($conn, ['collected'], 'b.Booking_ID DESC');

$conn->close();

$activeTab = $_GET['tab'] ?? 'pending';
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
        /* ── Tabs ── */
        .tab-bar {
            display: flex;
            gap: 2px;
            border-bottom: 1px solid rgba(124,92,252,0.2);
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #8b82b5;
            padding: 10px 16px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: -1px;
            white-space: nowrap;
        }
        .tab-btn:hover { color: #b084ff; }
        .tab-btn.active { color: #b084ff; border-bottom-color: #7c5cfc; }
        .tab-badge {
            display: inline-block;
            font-size: 0.62rem;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 10px;
            margin-left: 4px;
            vertical-align: middle;
        }
        .badge-pending   { background:#fff3cd; border:1px solid #856404; color:#856404; }
        .badge-approved  { background:#d4edda; border:1px solid #155724; color:#155724; }
        .badge-cancelled { background:#f8d7da; border:1px solid #721c24; color:#721c24; }
        .badge-collected { background:#e2e3e5; border:1px solid #495057; color:#495057; }

        /* ── Stats ── */
        .stats-row { display:flex; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
        .stat-pill { flex:1; min-width:90px; background:rgba(124,92,252,0.10); border:1px solid rgba(124,92,252,0.25); border-radius:14px; padding:14px 16px; text-align:center; }
        .stat-pill .s-val   { font-size:1.7rem; font-weight:800; color:#b084ff; line-height:1; }
        .stat-pill .s-label { font-size:0.68rem; color:#8b82b5; text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; }
        .stat-pill.amber { background-color:white; border-color:rgba(245,158,11,0.35); }
        .stat-pill.amber .s-val { color:#f59e0b; }
        .stat-pill.green  { background-color:white; border-color:rgba(34,197,94,0.3); }
        .stat-pill.green .s-val  { color:#22c55e; }
        .stat-pill.grey   { background-color:white; border-color:rgba(73,80,87,0.25); }
        .stat-pill.grey .s-val   { color:#495057; }

        /* ── Action banner ── */
        .action-banner {
            background:#fff3cd; border:1px solid rgba(133,100,4,0.25);
            border-left:4px solid #856404; border-radius:14px;
            padding:14px 18px; margin-bottom:20px; color:#856404;
            font-size:0.88rem; display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:10px;
        }
        .action-banner strong { color:#856404; }
        .review-btn {
            background:#856404; color:#fff3cd; border:none;
            padding:8px 18px; border-radius:20px; font-size:0.82rem;
            font-weight:700; cursor:pointer; font-family:inherit;
            text-decoration:none; white-space:nowrap;
        }
        .review-btn:hover { background:#6b5003; }

        /* ── Queue table (pending tab) ── */
        .queue-wrap { overflow-x:auto; margin-bottom:20px; }
        .queue-table {
            width:100%; border-collapse:collapse; font-size:0.82rem;
            background:#f1f0ea; border-radius:16px; overflow:hidden;
        }
        .queue-table th {
            background:#e8e7df; color:#555; font-size:0.7rem;
            text-transform:uppercase; letter-spacing:0.4px;
            padding:11px 14px; text-align:left; white-space:nowrap;
        }
        .queue-table td {
            padding:11px 14px; border-bottom:1px solid #dddcd6;
            color:#1e1b4b; vertical-align:middle;
        }
        .queue-table tr:last-child td { border-bottom:none; }
        .queue-table tr:hover td { background:rgba(124,92,252,0.04); }
        .wait-days { font-size:0.72rem; color:#888; display:block; margin-top:2px; }

        .btn-approve-sm {
            background:#198754; color:#fff; border:none;
            padding:6px 14px; border-radius:12px; font-size:0.75rem;
            font-weight:700; cursor:pointer; font-family:inherit;
            white-space:nowrap; margin-right:4px;
        }
        .btn-approve-sm:hover { background:#157347; }
        .btn-reject-sm {
            background:#f8d7da; color:#721c24; border:1px solid #f5c2c7;
            padding:6px 14px; border-radius:12px; font-size:0.75rem;
            font-weight:700; cursor:pointer; font-family:inherit; white-space:nowrap;
        }
        .btn-reject-sm:hover { background:#f1aeb5; }
        .view-link { color:#7c5cfc; font-size:0.72rem; text-decoration:none; display:block; margin-top:4px; }
        .view-link:hover { text-decoration:underline; }

        /* ── Status cards ── */
        .status-card { position:relative; }
        .status-text { display:inline-block; padding:4px 12px; border-radius:12px; font-weight:bold; font-size:0.85rem; }
        .status-pending           { background:#fff3cd; color:#856404; }
        .status-approved          { background:#d4edda; color:#155724; }
        .status-rejected          { background:#f8d7da; color:#721c24; }
        .status-verification_sent { background:#cfe2ff; color:#084298; }
        .status-confirmed         { background:#d4edda; color:#155724; }
        .status-collected         { background:#e2e3e5; color:#495057; }
        .status-other             { background:#e2e3e5; color:#495057; }
        .priority-badge { background:#dc3545; color:white; font-size:0.75rem; padding:2px 8px; border-radius:10px; font-weight:bold; }
        .prio-badge     { background:#dc3545; color:#fff; font-size:0.65rem; padding:2px 6px; border-radius:8px; font-weight:700; }
        .header-main    { display:flex; align-items:center; flex-wrap:wrap; gap:10px; }
        .view-booking-btn { background:#241253; color:#E8E9DE; border:none; padding:7px 16px; border-radius:14px; cursor:pointer; font-size:0.8rem; font-weight:bold; text-decoration:none; display:inline-block; }
        .view-booking-btn:hover { background:#37216d; transform:translateY(-1px); }

        /* Collected cards — muted */
        .status-card.muted-card { opacity:0.6; }
        .status-card.muted-card:hover { opacity:0.85; }

        /* Empty state */
        .empty-state { background:#f1f0ea; color:#8b82b5; border-radius:16px; padding:36px; text-align:center; font-size:0.9rem; }

        /* Quick actions */
        .quick-actions { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px; }
        .qa-btn { flex:1; min-width:90px; background:rgba(36,18,83,0.7); border:1px solid rgba(124,92,252,0.25); border-radius:12px; color:#E8E9DE; padding:12px 8px; font-size:0.78rem; font-weight:600; cursor:pointer; text-align:center; text-decoration:none; display:flex; flex-direction:column; align-items:center; gap:5px; transition:all 0.2s; }
        .qa-btn:hover { background:rgba(124,92,252,0.22); border-color:#7c5cfc; transform:translateY(-2px); }

        .section-label { font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#8b82b5; margin-bottom:10px; }

        /* Reject modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:200; justify-content:center; align-items:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:#f1f0ea; color:#1e1b4b; border-radius:20px; padding:28px; width:90%; max-width:420px; }
        .modal-box h3 { margin:0 0 10px; font-size:1rem; }
        .modal-box p  { font-size:0.8rem; color:#555; margin:0 0 12px; }
        .modal-box textarea { width:100%; border-radius:10px; border:1px solid #ccc; padding:10px; font-size:0.88rem; resize:vertical; min-height:80px; font-family:inherit; margin-bottom:12px; box-sizing:border-box; }
        .modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:4px; }
        .btn-cancel-modal { background:none; border:1px solid #ccc; color:#666; padding:8px 16px; border-radius:16px; cursor:pointer; font-family:inherit; }
        .btn-confirm-reject { background:#dc3545; color:#fff; border:none; padding:8px 18px; border-radius:16px; font-weight:bold; cursor:pointer; font-family:inherit; }

        /* Task popup (shown once on first dashboard load after login) */
        .modal-box.task-popup-box { max-width:440px; text-align:left; }
        .task-popup-box h3 { font-size:1.1rem; margin:0 0 10px; color:#241253; }
        .task-popup-box p { font-size:0.88rem; color:#555; margin:0 0 18px; line-height:1.5; }
        .task-popup-box .task-popup-count { color:#7c5cfc; font-weight:800; }
        .btn-review-tasks {
            background:#241253; color:#E8E9DE; border:none;
            padding:10px 22px; border-radius:16px; font-weight:bold;
            cursor:pointer; font-family:inherit; font-size:0.9rem;
        }
        .btn-review-tasks:hover { background:#37216d; }
        .btn-dismiss-popup {
            background:none; border:1px solid #ccc; color:#666;
            padding:9px 18px; border-radius:16px; cursor:pointer; font-family:inherit;
        }

        /* Msg banner */
        .msg-banner { padding:10px 16px; border-radius:12px; font-size:0.85rem; font-weight:600; margin-bottom:16px; }
        .msg-success { background:rgba(34,197,94,0.12); color:#22c55e; border:1px solid rgba(34,197,94,0.3); }
        .msg-info    { background:#fff3cd; color:#856404; border:1px solid rgba(133,100,4,0.25); }
        .msg-error   { background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.3); }

        #profileContainer { position:relative; display:inline-block; cursor:pointer; }
        #userImage { vertical-align:middle; margin-left:5px; }
        #profileSelect { display:none; position:absolute; right:0; top:25px; background-color:#241253; border:1px solid #E8E9DE; border-radius:8px; min-width:130px; z-index:10; }
        #profileSelect.show { display:flex; flex-direction:column; }
        #profileSelect button { background:none; border:none; color:#E8E9DE; padding:10px; text-align:left; width:100%; cursor:pointer; font-size:0.85rem; }
        #profileSelect button:hover { background-color:rgba(232,233,222,0.2); }

        /* ── Notification badge (deliberately eye-catching — this is meant to be noticed) ── */
        .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            min-width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
            box-shadow: 0 0 0 2px #241253;
            animation: notifPulse 2s ease-in-out infinite;
        }
        @keyframes notifPulse {
            0%, 100% { box-shadow: 0 0 0 2px #241253, 0 0 0 0 rgba(239,68,68,0.6); }
            50%      { box-shadow: 0 0 0 2px #241253, 0 0 0 4px rgba(239,68,68,0); }
        }
        .notif-badge-inline {
            background: #ef4444;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 800;
            border-radius: 10px;
            padding: 2px 8px;
            margin-left: 8px;
        }
        #profileSelect button {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .stats-row { flex-direction:column; }
            .quick-actions { flex-direction:column; }
            .header-main { flex-direction:column; align-items:flex-start; }
        }
    </style>
</head>
<body>
<div id="wrapper">

    <div class="leftcontainer">
        <header>
            <h1 onclick="window.location.href='staffMainStatus.php'" style="cursor:pointer;">VaulteM</h1>
        </header>
        <button type="button" id="booking" onclick="window.location.href='staffBookingWindow.php'">
            Manage Booking Form
        </button>
    </div>

    <div class="rightcontainer">

        <div id="userName">
            Welcome,
            <span id="currentName"><?php echo htmlspecialchars($staff_name); ?></span>
            <span id="profileContainer">
                <span style="position:relative; display:inline-block;">
                    <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                    <?php if ($notifTotal > 0): ?>
                        <span class="notif-badge"><?php echo $notifTotal; ?></span>
                    <?php endif; ?>
                </span>
                <div id="profileSelect">
                    <button onclick="showProfile()">Profile</button>
                    <button onclick="window.location.href='staffNotifications.php'">
                        Notifications
                        <?php if ($notifTotal > 0): ?>
                            <span class="notif-badge-inline"><?php echo $notifTotal; ?></span>
                        <?php endif; ?>
                    </button>
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
            <div class="stat-pill grey">
                <div class="s-val"><?php echo $collected; ?></div>
                <div class="s-label">Collected</div>
            </div>
            <div class="stat-pill">
                <div class="s-val"><?php echo $total; ?></div>
                <div class="s-label">Total</div>
            </div>
        </div>

        <!-- Action banner -->
        <?php if ($pending > 0): ?>
        <div class="action-banner">
            <span>
                <strong><?php echo $pending; ?> booking<?php echo $pending !== 1 ? 's' : ''; ?></strong>
                from
                <strong><?php echo $pendingStudents; ?> student<?php echo $pendingStudents !== 1 ? 's' : ''; ?></strong>
                <?php echo $pending !== 1 ? 'are' : 'is'; ?> waiting for your approval.
            </span>
            <a href="staffNotifications.php" class="review-btn">Review Now</a>
        </div>
        <?php endif; ?>

        <!-- Quick actions -->
        <div class="quick-actions">
            <a class="qa-btn" href="staffbookingrecords.php">Bookings</a>
            <a class="qa-btn" href="Staffverifypanel.php">Verification</a>
            <a class="qa-btn" href="staffStudentList.php">Students</a>
            <a class="qa-btn" href="staffpenalty.php">Penalty</a>
            <a class="qa-btn" href="staffreport.php">Reports</a>
        </div>

        <!-- ── 4 Tabs ── -->
        <div class="tab-bar">
            <a href="staffMainStatus.php?tab=pending" class="tab-btn <?php echo $activeTab==='pending' ? 'active' : ''; ?>">
                Pending
                <?php if ($pending > 0): ?>
                <span class="tab-badge badge-pending"><?php echo $pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="staffMainStatus.php?tab=approved" class="tab-btn <?php echo $activeTab==='approved' ? 'active' : ''; ?>">
                Approved
                <?php if ($approved > 0): ?>
                <span class="tab-badge badge-approved"><?php echo $approved; ?></span>
                <?php endif; ?>
            </a>
            <a href="staffMainStatus.php?tab=cancelled" class="tab-btn <?php echo $activeTab==='cancelled' ? 'active' : ''; ?>">
                Cancelled / Rejected
            </a>
            <a href="staffMainStatus.php?tab=collected" class="tab-btn <?php echo $activeTab==='collected' ? 'active' : ''; ?>">
                Collected
                <?php if ($collected > 0): ?>
                <span class="tab-badge badge-collected"><?php echo $collected; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Msg banner -->
        <?php if (isset($_GET['msg'])): ?>
        <div class="msg-banner <?php echo $_GET['msg']==='approved' ? 'msg-success' : ($_GET['msg']==='no_reason' ? 'msg-error' : 'msg-info'); ?>">
            <?php
                $bid_ref = isset($_GET['bid']) ? ' (Booking #'.intval($_GET['bid']).')' : '';
                $msgs = [
                    'approved'  => 'Booking approved'.$bid_ref.'. Student notified to pay.',
                    'rejected'  => 'Booking rejected'.$bid_ref.'. Student notified.',
                    'no_reason' => 'A rejection reason is required before rejecting.',
                ];
                echo $msgs[$_GET['msg']] ?? '';
            ?>
        </div>
        <?php endif; ?>

        <?php
        // ── Helper to render a standard booking card ──────────────────────────
        function renderCard(array $row, string $extraClass = ''): void {
            $bstat  = $row['Booking_Status'];
            $bsl    = strtolower($bstat);
            $isPrio = $row['Booking_Priority'] === 'Y';
            $ps     = strtolower($row['Payment_Status'] ?? '');
            $isPaid = ($ps === 'y' || $ps === 'paid');
            $feeToShow = $row['PaymentAmount'] ?? $row['TotalFee'];

            if ($bsl === 'pending')               $sc = 'status-pending';
            elseif ($bsl === 'approved')          $sc = 'status-approved';
            elseif ($bsl === 'rejected')          $sc = 'status-rejected';
            elseif ($bsl === 'verification_sent') $sc = 'status-verification_sent';
            elseif ($bsl === 'confirmed')         $sc = 'status-confirmed';
            elseif ($bsl === 'released')          $sc = 'status-verification_sent';
            elseif ($bsl === 'collected')         $sc = 'status-collected';
            else                                  $sc = 'status-other';
            ?>
            <div class="status-card <?php echo $extraClass; ?>">
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
                    <p>Drop-off  : <?php echo date('d/m/Y', strtotime($row['DropOff_Date'])); ?></p>
                    <p>Pick-up   : <?php echo date('d/m/Y', strtotime($row['Pickup_Date'])); ?></p>
                    <p>Items     : <?php echo $row['TotalItem']; ?></p>
                    <p>Total fee : RM <?php echo number_format((float)$feeToShow, 2); ?></p>
                    <p>Payment   : <?php echo $isPaid ? '<strong style="color:#22c55e;">Paid</strong>' : '<span style="color:#856404;">Unpaid</span>'; ?></p>
                </div>
                <div class="button-container">
                    <a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="view-booking-btn">View Details</a>
                </div>
            </div>
            <?php
        }
        ?>

        <!-- ── TAB: PENDING ── -->
        <?php if ($activeTab === 'pending'): ?>
        <div class="section-label">
            Pending Approval
            <?php if ($pending > 0): ?>
            — <?php echo $pending; ?> booking<?php echo $pending !== 1 ? 's' : ''; ?> from <?php echo $pendingStudents; ?> student<?php echo $pendingStudents !== 1 ? 's' : ''; ?> waiting
            <?php endif; ?>
        </div>

        <?php if ($pendingQ && $pendingQ->num_rows > 0): ?>
        <div class="queue-wrap">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Student</th>
                        <th>College</th>
                        <th>Drop-off</th>
                        <th>Pick-up</th>
                        <th>Items</th>
                        <th>Fee (RM)</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $pendingQ->fetch_assoc()):
                    $isPrio    = $row['Booking_Priority'] === 'Y';
                    $waitDays  = max(0, (int)floor((time() - strtotime($row['Booking_Date'])) / 86400));
                    $feeToShow = $row['PaymentAmount'] ?? $row['TotalFee'];
                ?>
                <tr>
                    <td>
                        <strong style="color:#7c5cfc;">#<?php echo $row['Booking_ID']; ?></strong>
                        <?php if ($isPrio): ?><br><span class="prio-badge">EMERGENCY</span><?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($row['Student_Name']); ?>
                        <span style="display:block;font-size:0.72rem;color:#888;"><?php echo htmlspecialchars($row['Student_ID']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['DropOff_Date'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['Pickup_Date'])); ?></td>
                    <td><?php echo $row['TotalItem']; ?></td>
                    <td><?php echo number_format((float)$feeToShow, 2); ?></td>
                    <td>
                        <?php echo date('d/m/Y', strtotime($row['Booking_Date'])); ?>
                        <span class="wait-days"><?php echo $waitDays === 0 ? 'Today' : $waitDays.'d ago'; ?></span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="approve_id" value="<?php echo $row['Booking_ID']; ?>">
                            <button type="submit" class="btn-approve-sm"
                                onclick="return confirm('Approve Booking #<?php echo $row['Booking_ID']; ?> for <?php echo htmlspecialchars(addslashes($row['Student_Name'])); ?>?')">
                                Approve
                            </button>
                        </form>
                        <button class="btn-reject-sm"
                            onclick="openReject(<?php echo $row['Booking_ID']; ?>, '<?php echo htmlspecialchars(addslashes($row['Student_Name'])); ?>')">
                            Reject
                        </button>
                        <a href="staffBookingDetail.php?id=<?php echo $row['Booking_ID']; ?>" class="view-link">View full details</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">No pending bookings. All caught up.</div>
        <?php endif; ?>

        <!-- ── TAB: APPROVED ── -->
        <?php elseif ($activeTab === 'approved'): ?>
        <div class="section-label">Approved Bookings</div>
        <?php if ($approvedQ && $approvedQ->num_rows > 0):
            while ($row = $approvedQ->fetch_assoc()):
                renderCard($row);
            endwhile;
        else: ?>
            <div class="empty-state">No approved bookings.</div>
        <?php endif; ?>

        <!-- ── TAB: CANCELLED / REJECTED ── -->
        <?php elseif ($activeTab === 'cancelled'): ?>
        <div class="section-label">Cancelled / Rejected Bookings</div>
        <?php if ($cancelledQ && $cancelledQ->num_rows > 0):
            while ($row = $cancelledQ->fetch_assoc()):
                renderCard($row, 'muted-card');
            endwhile;
        else: ?>
            <div class="empty-state">No cancelled or rejected bookings.</div>
        <?php endif; ?>

        <!-- ── TAB: COLLECTED ── -->
        <?php elseif ($activeTab === 'collected'): ?>
        <div class="section-label">Collected Bookings</div>
        <?php if ($collectedQ && $collectedQ->num_rows > 0):
            while ($row = $collectedQ->fetch_assoc()):
                renderCard($row, 'muted-card');
            endwhile;
        else: ?>
            <div class="empty-state">No collected bookings yet.</div>
        <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<!-- Task popup — shown once on the first dashboard load after login, if there's anything to do -->
<?php if ($showTaskPopup): ?>
<div class="modal-overlay show" id="taskPopup">
    <div class="modal-box task-popup-box">
        <h3>You have tasks waiting</h3>
        <p>
            There <?php echo $notifTotal === 1 ? 'is' : 'are'; ?>
            <span class="task-popup-count"><?php echo $notifTotal; ?></span>
            new task<?php echo $notifTotal !== 1 ? 's' : ''; ?> for you to complete today.
            Including pending approvals, drop-off photo uploads, and pickup verifications.
            Please review them during your working hours.
        </p>
        <div class="modal-btns">
            <button type="button" class="btn-dismiss-popup" onclick="closeTaskPopup()">Later</button>
            <button type="button" class="btn-review-tasks" onclick="window.location.href='staffNotifications.php'">
                Review Tasks
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reject modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3 id="rejectTitle">Reject Booking</h3>
        <p>Provide a clear reason. Upload a photo with a measuring tape beside the item as evidence if there is a mismatch. The reason and photo will be shown to the student.</p>
        <form method="POST" enctype="multipart/form-data" id="rejectForm">
            <input type="hidden" name="reject_id" id="rejectBid">
            <textarea name="reject_reason" placeholder="e.g. Item declared as Small Bag but actual size is Large. See attached photo." required></textarea>
            <label style="font-size:0.78rem;color:#888;display:block;margin-bottom:6px;">Proof photo (recommended)</label>
            <input type="file" name="reject_photo" accept="image/*" style="font-size:0.82rem;margin-bottom:14px;">
            <div class="modal-btns">
                <button type="button" class="btn-cancel-modal" onclick="closeReject()">Cancel</button>
                <button type="submit" class="btn-confirm-reject"
                    onclick="return confirm('Confirm rejection? This cannot be undone.')">Confirm Reject</button>
            </div>
        </form>
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

    function closeTaskPopup() {
        const el = document.getElementById('taskPopup');
        if (el) el.classList.remove('show');
    }

    // ── Intercept browser back button: show logout confirmation instead of leaving ──
    history.pushState(null, '', location.href);
    window.addEventListener('popstate', function () {
        history.pushState(null, '', location.href);
        document.getElementById('logoutPopup').classList.remove('hidden');
    });
    document.addEventListener('click', function(e) {
        const c = document.getElementById('profileContainer');
        if (!c.contains(e.target)) document.getElementById('profileSelect').classList.remove('show');
    });

    function openReject(bid, name) {
        document.getElementById('rejectBid').value = bid;
        document.getElementById('rejectTitle').textContent = 'Reject Booking #' + bid + ' — ' + name;
        document.getElementById('rejectModal').classList.add('show');
    }
    function closeReject() {
        document.getElementById('rejectModal').classList.remove('show');
    }
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) closeReject();
    });
</script>
</body>
</html>