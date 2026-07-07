<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';
$msg        = '';
$msgType    = '';

// ── Handle POST actions ───────────────────────────────────────────────────────

// Add new window
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $label      = trim($_POST['label']      ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date']   ?? '');

    if (!$label || !$start_date || !$end_date) {
        $msg     = "All fields are required.";
        $msgType = 'error';
    } elseif ($end_date < $start_date) {
        $msg     = "End date cannot be before start date.";
        $msgType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO booking_window (label, start_date, end_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $label, $start_date, $end_date);
        if ($stmt->execute()) {
            $msg     = "Booking window added successfully.";
            $msgType = 'success';
        } else {
            $msg     = "Failed to add booking period.";
            $msgType = 'error';
        }
        $stmt->close();
    }
}

// Edit window — extend-only (cannot shrink the range once created)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $wid        = intval($_POST['window_id']);
    $label      = trim($_POST['label']      ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date']   ?? '');

    // Fetch the existing window so we can enforce "extend only"
    $origStmt = $conn->prepare("SELECT start_date, end_date FROM booking_window WHERE window_id = ?");
    $origStmt->bind_param("i", $wid);
    $origStmt->execute();
    $origRow = $origStmt->get_result()->fetch_assoc();
    $origStmt->close();

    if (!$label || !$start_date || !$end_date) {
        $msg     = "All fields are required.";
        $msgType = 'error';
    } elseif ($end_date < $start_date) {
        $msg     = "End date cannot be before start date.";
        $msgType = 'error';
    } elseif (!$origRow) {
        $msg     = "Booking window not found.";
        $msgType = 'error';
    } elseif ($start_date > $origRow['start_date'] || $end_date < $origRow['end_date']) {
        $msg     = "You can only extend a booking window, not shrink it. The new range must still cover "
                 . date('d M Y', strtotime($origRow['start_date'])) . " – " . date('d M Y', strtotime($origRow['end_date'])) . ".";
        $msgType = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE booking_window SET label=?, start_date=?, end_date=? WHERE window_id=?");
        $stmt->bind_param("sssi", $label, $start_date, $end_date, $wid);
        if ($stmt->execute()) {
            $msg     = "Booking window updated successfully.";
            $msgType = 'success';
        }
        $stmt->close();
    }
}

// Delete window — only allowed if no booking inside this window has been approved yet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $wid = intval($_POST['window_id']);

    $winStmt = $conn->prepare("SELECT start_date, end_date, label FROM booking_window WHERE window_id = ?");
    $winStmt->bind_param("i", $wid);
    $winStmt->execute();
    $winRow = $winStmt->get_result()->fetch_assoc();
    $winStmt->close();

    if (!$winRow) {
        $msg     = "Booking window not found.";
        $msgType = 'error';
    } else {
        // Any booking whose drop-off date falls in this window AND has moved past pending/rejected
        // (i.e. staff has already approved / acted on it) blocks the delete.
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) AS c FROM booking
            WHERE DropOff_Date BETWEEN ? AND ?
              AND LOWER(Booking_Status) NOT IN ('pending', 'rejected')
        ");
        $checkStmt->bind_param("ss", $winRow['start_date'], $winRow['end_date']);
        $checkStmt->execute();
        $approvedCount = (int)$checkStmt->get_result()->fetch_assoc()['c'];
        $checkStmt->close();

        if ($approvedCount > 0) {
            $msg     = "Cannot delete \"" . htmlspecialchars($winRow['label']) . "\" — " . $approvedCount
                     . " booking(s) inside this window have already been approved by staff. "
                     . "Deleting is only allowed before any booking in the window is approved.";
            $msgType = 'error';
        } else {
            $delStmt = $conn->prepare("DELETE FROM booking_window WHERE window_id = ?");
            $delStmt->bind_param("i", $wid);
            $delStmt->execute();
            $delStmt->close();
            $msg     = "Booking window deleted.";
            $msgType = 'success';
        }
    }
}

// ── Fetch all windows ─────────────────────────────────────────────────────────
$windows = [];
$res = $conn->query("SELECT * FROM booking_window ORDER BY start_date DESC");
while ($row = $res->fetch_assoc()) $windows[] = $row;

// For each window, precompute whether it's deletable (no approved bookings inside it)
foreach ($windows as &$w) {
    $chk = $conn->prepare("
        SELECT COUNT(*) AS c FROM booking
        WHERE DropOff_Date BETWEEN ? AND ?
          AND LOWER(Booking_Status) NOT IN ('pending', 'rejected')
    ");
    $chk->bind_param("ss", $w['start_date'], $w['end_date']);
    $chk->execute();
    $w['approvedCount'] = (int)$chk->get_result()->fetch_assoc()['c'];
    $chk->close();
}
unset($w);

// Active window count
$activeCount = $conn->query("SELECT COUNT(*) AS c FROM booking_window WHERE start_date <= CURDATE() AND end_date >= CURDATE()")->fetch_assoc()['c'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Booking Window</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .section-label {
            font-size: 0.8rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.6px; color: #8b82b5; margin-bottom: 12px;
        }

        /* Add form */
        .add-form {
            background: #f1f0ea;
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 28px;
            color: #1e1b4b;
        }
        .add-form h3 { margin: 0 0 16px; font-size: 0.95rem; color: #241253; }
        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 0;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 140px;
        }
        .form-group label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #888;
            font-weight: 700;
        }
        .form-group input[type="text"],
        .form-group input[type="date"] {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 0.88rem;
            font-family: inherit;
            color: #1e1b4b;
            background: #fff;
            outline: none;
        }
        .form-group input:focus { border-color: #7c5cfc; }

        .btn-add {
            background: #241253;
            color: #E8E9DE;
            border: none;
            padding: 10px 22px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
            align-self: flex-end;
        }
        .btn-add:hover { background: #37216d; }

        .form-note {
            font-size: 0.76rem;
            color: #8b82b5;
            margin-top: 10px;
        }

        /* Status banner */
        .status-banner {
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 0.88rem;
            font-weight: 600;
            border-left: 4px solid;
        }
        .banner-active   { background: rgba(34,197,94,0.10); color: #155724; border-color: #22c55e; }
        .banner-inactive { background: rgba(239,68,68,0.08); color: #721c24; border-color: #ef4444; }
        .banner-success  { background: rgba(34,197,94,0.10); color: #155724; border-color: #22c55e; }
        .banner-error    { background: rgba(239,68,68,0.08); color: #721c24; border-color: #ef4444; }

        /* Windows table */
        .window-table-wrap { overflow-x: auto; margin-bottom: 10px; }
        .window-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            background: #f1f0ea;
            border-radius: 16px;
            overflow: hidden;
        }
        .window-table th {
            background: #e8e7df;
            color: #555;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 11px 16px;
            text-align: left;
            white-space: nowrap;
        }
        .window-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #dddcd6;
            color: #1e1b4b;
            vertical-align: middle;
        }
        .window-table tr:last-child td { border-bottom: none; }
        .window-table tr:hover td { background: rgba(124,92,252,0.04); }

        .status-active   { background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; }

        .btn-edit   { background: #cfe2ff; color: #084298; border: 1px solid #b6d4fe; padding: 5px 12px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; cursor: pointer; font-family: inherit; margin-right: 4px; }
        .btn-delete { background: #f8d7da; color: #721c24; border: 1px solid #f5c2c7; padding: 5px 12px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-delete:disabled { background: #e8e7df; color: #aaa; border-color: #ddd; cursor: not-allowed; }
        .btn-edit:hover, .btn-delete:not(:disabled):hover { opacity: 0.8; }

        .locked-note { font-size: 0.68rem; color: #aaa; display: block; margin-top: 3px; }

        /* Edit modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:200; justify-content:center; align-items:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:#f1f0ea; color:#1e1b4b; border-radius:20px; padding:28px; width:90%; max-width:480px; }
        .modal-box h3 { margin:0 0 8px; font-size:1rem; color:#241253; }
        .modal-box .modal-subnote { font-size:0.78rem; color:#8b82b5; margin-bottom:16px; }
        .modal-form-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
        .modal-form-group label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.4px; color:#888; font-weight:700; }
        .modal-form-group input { border:1px solid #ccc; border-radius:10px; padding:9px 12px; font-size:0.88rem; font-family:inherit; color:#1e1b4b; background:#fff; outline:none; }
        .modal-form-group input:focus { border-color:#7c5cfc; }
        .modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:6px; }
        .btn-cancel-modal  { background:none; border:1px solid #ccc; color:#666; padding:8px 16px; border-radius:16px; cursor:pointer; font-family:inherit; }
        .btn-confirm-edit  { background:#241253; color:#E8E9DE; border:none; padding:8px 18px; border-radius:16px; font-weight:bold; cursor:pointer; font-family:inherit; }

        .today-badge { background: #cfe2ff; color: #084298; padding: 2px 8px; border-radius: 8px; font-size: 0.7rem; font-weight: 700; margin-left: 6px; }

        /* Profile menu */
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
            Welcome, <span><?php echo htmlspecialchars($staff_name); ?></span>
            <span id="profileContainer">
                <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showProfile()">Profile</button>
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <h1>Booking Form Settings</h1>

        <!-- Current status banner -->
        <?php if ($activeCount > 0): ?>
        <div class="status-banner banner-active">
            Booking is currently <strong>OPEN</strong> — <?php echo $activeCount; ?> active window<?php echo $activeCount !== 1 ? 's' : ''; ?> available. Students can submit bookings within the active date ranges.
        </div>
        <?php else: ?>
        <div class="status-banner banner-inactive">
            Booking is currently <strong>CLOSED</strong> — no active booking windows. Students cannot submit any bookings until you activate or add a window.
        </div>
        <?php endif; ?>

        <!-- Message banner -->
        <?php if ($msg): ?>
        <div class="status-banner <?php echo $msgType === 'success' ? 'banner-success' : 'banner-error'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php endif; ?>

        <!-- Add new window form -->
        <div class="section-label">Add Booking Period</div>
        <div class="add-form">
            <h3>Set a new date range when students can drop off and pick up items</h3>
            <form method="POST" onsubmit="return confirm('You can delete this window anytime before any booking inside it is approved. Once a booking is approved, deletion locks automatically and the window can only be extended. Are you sure you want to add this window?');">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group" style="flex:2; min-width:200px;">
                        <label>Label (e.g. Semester Break June 2026)</label>
                        <input type="text" name="label" placeholder="e.g. Mid-Semester Break July 2026" required>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" class="btn-add">Add Window</button>
                </div>
            </form>
            <p class="form-note">Note: a booking window can only be deleted while none of the bookings inside it have been approved yet. Once staff approves at least one booking in the window, it becomes permanent and can only be extended, never shrunk or removed — this protects students who already committed to those dates.</p>
        </div>

        <!-- Existing windows table -->
        <div class="section-label">
            Existing Booking Period (<?php echo count($windows); ?>)
        </div>

        <?php if (!empty($windows)): ?>
        <div class="window-table-wrap">
            <table class="window-table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $today = date('Y-m-d');
                foreach ($windows as $w):
                    $days      = (int)((strtotime($w['end_date']) - strtotime($w['start_date'])) / 86400) + 1;
                    $isCurrent = ($today >= $w['start_date'] && $today <= $w['end_date']);
                    $canDelete = ($w['approvedCount'] === 0);
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($w['label']); ?></strong>
                        <?php if ($isCurrent): ?>
                            <span class="today-badge">Active Now</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d M Y', strtotime($w['start_date'])); ?></td>
                    <td><?php echo date('d M Y', strtotime($w['end_date'])); ?></td>
                    <td><?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?></td>
                    <td>
                        <button class="btn-edit"
                            onclick="openEdit(<?php echo $w['window_id']; ?>, '<?php echo addslashes($w['label']); ?>', '<?php echo $w['start_date']; ?>', '<?php echo $w['end_date']; ?>')">
                            Edit
                        </button>
                        <?php if ($canDelete): ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete this booking window? This cannot be undone.')">
                            <input type="hidden" name="action"    value="delete">
                            <input type="hidden" name="window_id" value="<?php echo $w['window_id']; ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn-delete" disabled
                            title="Cannot delete — <?php echo $w['approvedCount']; ?> booking(s) in this window have already been approved.">
                            Delete
                        </button>
                        <span class="locked-note"><?php echo $w['approvedCount']; ?> booking(s) approved — locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background:#f1f0ea; color:#8b82b5; border-radius:16px; padding:36px; text-align:center; font-size:0.9rem;">
            No booking windows set. Add one above to open bookings for students.
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3>Edit Booking Window</h3>
        <p class="modal-subnote">You can only extend this window's range — the new dates must still fully cover the original range.</p>
        <form method="POST">
            <input type="hidden" name="action"    value="edit">
            <input type="hidden" name="window_id" id="editWindowId">
            <div class="modal-form-group">
                <label>Label</label>
                <input type="text" name="label" id="editLabel" required>
            </div>
            <div class="modal-form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" id="editStartDate" required>
            </div>
            <div class="modal-form-group">
                <label>End Date</label>
                <input type="date" name="end_date" id="editEndDate" required>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel-modal" onclick="closeEdit()">Cancel</button>
                <button type="submit" class="btn-confirm-edit">Save Changes</button>
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
    document.addEventListener('click', function(e) {
        const c = document.getElementById('profileContainer');
        if (c && !c.contains(e.target)) document.getElementById('profileSelect').classList.remove('show');
    });

    let editOriginalStart = '';
    let editOriginalEnd   = '';

    function openEdit(id, label, start, end) {
        document.getElementById('editWindowId').value  = id;
        document.getElementById('editLabel').value     = label;
        document.getElementById('editStartDate').value = start;
        document.getElementById('editEndDate').value   = end;

        editOriginalStart = start;
        editOriginalEnd   = end;

        // Extend-only: new start date can only move earlier (or stay the same),
        // new end date can only move later (or stay the same).
        document.getElementById('editStartDate').max = start;
        document.getElementById('editEndDate').min    = end;

        document.getElementById('editModal').classList.add('show');
    }
    function closeEdit() {
        document.getElementById('editModal').classList.remove('show');
    }
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });

    // Keep end date's minimum respecting BOTH the original end date and the (possibly earlier) new start date
    document.getElementById('editStartDate').addEventListener('change', function() {
        const minEnd = this.value > editOriginalEnd ? this.value : editOriginalEnd;
        document.getElementById('editEndDate').min = minEnd;
    });

    document.querySelector('input[name="start_date"]').addEventListener('change', function() {
        document.querySelector('input[name="end_date"]').min = this.value;
    });
</script>
</body>
</html>