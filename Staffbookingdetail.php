<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';
$bid = intval($_GET['id'] ?? 0);

if (!$bid) { header("Location: staffMainStatus.php"); exit(); }

// ── Storage capacity based on storespace table ────────────────────────────────
// Get the student's residential college for this booking
$collegeRes = $conn->query("
    SELECT s.Residential_ID,
           COALESCE(SUM(ss.Size), 0) AS TotalCapacity
    FROM booking b
    JOIN student s ON b.Student_ID = s.Student_ID
    JOIN storespace ss ON ss.Residential_ID = s.Residential_ID
    WHERE b.Booking_ID = $bid
    GROUP BY s.Residential_ID
");
$storageTotal = 0;
$residentialID = null;
if ($collegeRes && $collegeRes->num_rows > 0) {
    $cr = $collegeRes->fetch_assoc();
    $storageTotal  = (int)$cr['TotalCapacity'];
    $residentialID = $cr['Residential_ID'];
}

// Items currently stored (approved bookings) in the same residential college
$storageUsed = 0;
if ($residentialID) {
    $usedRes = $conn->query("
        SELECT COALESCE(SUM(i.Quantity), 0) AS used
        FROM item i
        JOIN booking b  ON i.Booking_ID  = b.Booking_ID
        JOIN student s  ON b.Student_ID  = s.Student_ID
        WHERE LOWER(b.Booking_Status) = 'approved'
          AND s.Residential_ID = '$residentialID'
    ");
    if ($usedRes) $storageUsed = (int)$usedRes->fetch_assoc()['used'];
}

$storagePct          = ($storageTotal > 0) ? round(($storageUsed / $storageTotal) * 100) : 0;
$needsManualApproval = ($storagePct >= 60);

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Approve — only allowed when storage >= 60% (manual mode)
    if (isset($_POST['approve']) && $needsManualApproval) {
        $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Approved' WHERE Booking_ID = ? AND LOWER(Booking_Status) = 'pending'");
        $stmt->bind_param("i", $bid); $stmt->execute(); $stmt->close();
        header("Location: staffBookingDetail.php?id=$bid&msg=approved"); exit();
    }
    // Reject — only allowed when storage >= 60% (manual mode)
    if (isset($_POST['reject']) && $needsManualApproval) {
        $reason = trim($_POST['reject_reason'] ?? '');
        $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Rejected' WHERE Booking_ID = ? AND LOWER(Booking_Status) = 'pending'");
        $stmt->bind_param("i", $bid); $stmt->execute(); $stmt->close();
        header("Location: staffBookingDetail.php?id=$bid&msg=rejected"); exit();
    }
    // Verify (send to student)
    if (isset($_POST['verify'])) {
        $stmt = $conn->prepare("UPDATE booking SET Booking_Status = 'Verification_Sent' WHERE Booking_ID = ? AND LOWER(Booking_Status) = 'approved'");
        $stmt->bind_param("i", $bid); $stmt->execute(); $stmt->close();
        header("Location: staffBookingDetail.php?id=$bid&msg=verified"); exit();
    }
    // Drop-off photo upload
    if (isset($_POST['upload_dropoff'])) {
        if (isset($_FILES['dropoff_photo']) && $_FILES['dropoff_photo']['error'] === 0) {
            $ext     = strtolower(pathinfo($_FILES['dropoff_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = 'uploads/dropoff/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = 'dropoff_' . $bid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['dropoff_photo']['tmp_name'], $uploadDir . $filename)) {
                    $_SESSION['dropoff_photo_' . $bid] = $uploadDir . $filename;
                }
            }
        }
        header("Location: staffBookingDetail.php?id=$bid&msg=uploaded"); exit();
    }
}

// ── Fetch booking ─────────────────────────────────────────────────────────────
$bStmt = $conn->prepare("
    SELECT b.*, s.Student_Name, s.Student_Mail, s.Student_PhoneNo,
           rc.Residential_Block,
           COALESCE(SUM(i.Quantity), 0)           AS TotalItem,
           COALESCE(SUM(i.Quantity * i.Price), 0) AS TotalFee,
           p.Payment_Status, p.Payment_Method, p.Payment_Date, p.Amount
    FROM booking b
    LEFT JOIN student s  ON b.Student_ID      = s.Student_ID
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN item i     ON b.Booking_ID      = i.Booking_ID
    LEFT JOIN payment p  ON b.Booking_ID      = p.Booking_ID
    WHERE b.Booking_ID = ?
    GROUP BY b.Booking_ID, s.Student_Name, s.Student_Mail, s.Student_PhoneNo,
             rc.Residential_Block, p.Payment_Status, p.Payment_Method, p.Payment_Date, p.Amount
");
$bStmt->bind_param("i", $bid);
$bStmt->execute();
$bRes = $bStmt->get_result();
if ($bRes->num_rows === 0) { header("Location: staffMainStatus.php"); exit(); }
$b = $bRes->fetch_assoc();
$bStmt->close();

// ── Fetch items ───────────────────────────────────────────────────────────────
$iRes = $conn->query("SELECT Item_Name, Quantity, Price FROM item WHERE Booking_ID = $bid");

$bstat  = strtolower($b['Booking_Status']);
$isPrio = $b['Booking_Priority'] === 'Y';
$isPaid = in_array(strtolower($b['Payment_Status'] ?? ''), ['y','paid']);

// ── Fix 0000-00-00 dates ──────────────────────────────────────────────────────
$bookingDate = (!empty($b['Booking_Date']) && $b['Booking_Date'] !== '0000-00-00')
               ? $b['Booking_Date'] : 'N/A';
$paymentDate = (!empty($b['Payment_Date']) && $b['Payment_Date'] !== '0000-00-00')
               ? $b['Payment_Date'] : 'N/A';

// ── Verification state ────────────────────────────────────────────────────────
$bs               = $b['Booking_Status'];
$studentConfirmed = in_array($bs, ['Confirmed','Collected']);
$verifSent        = ($bs === 'Verification_Sent');

// ── Uploaded photo (session) ──────────────────────────────────────────────────
$uploadedPhoto = $_SESSION['dropoff_photo_' . $bid] ?? null;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Booking #<?php echo $bid; ?></title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .back-btn { background: none; border: none; color: #241253; font-size: 1rem; font-weight: bold; cursor: pointer; padding: 14px 0 4px 0; display: block; }
        .back-btn:hover { text-decoration: underline; transform: none; }

        .booking-header { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .booking-id-label { font-size: 1.1rem; font-weight: 800; color: #E8E9DE; }

        .status-badge { display: inline-block; padding: 4px 14px; border-radius: 12px; font-weight: bold; font-size: 0.82rem; }
        .status-pending           { background: #fff3cd; color: #856404; }
        .status-approved          { background: #d4edda; color: #155724; }
        .status-rejected          { background: #f8d7da; color: #721c24; }
        .status-verification_sent { background: #cfe2ff; color: #084298; }
        .status-confirmed         { background: #d4edda; color: #155724; }
        .status-collected         { background: #e2e3e5; color: #495057; }
        .priority-badge { background: #dc3545; color: #fff; font-size: 0.72rem; padding: 3px 10px; border-radius: 10px; font-weight: bold; }

        /* ── Accordion ── */
        .accordion { margin-bottom: 16px; width: 97%; }

        .accordion-toggle {
            width: 100%;
            background: #084298;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 13px 18px;
            font-size: 0.95rem;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: inherit;
            transition: background 0.2s;
        }
        .accordion-toggle:hover { background: #0a58ca; }
        .accordion-toggle.open  { border-radius: 12px 12px 0 0; }

        .accordion-arrow { font-size: 1rem; transition: transform 0.3s; display: inline-block; }
        .accordion-toggle.open .accordion-arrow { transform: rotate(180deg); }

        .accordion-body {
            display: none;
            background: #1e1b4b;
            border: 2px solid #3b5bdb;
            border-top: none;
            border-radius: 0 0 12px 12px;
            padding: 18px 20px;
        }
        .accordion-body.open { display: block; }

        .field-row { display: flex; align-items: baseline; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.9rem; }
        .field-row:last-child { border-bottom: none; }
        .field-label { color: #a0a8d0; min-width: 140px; font-size: 0.85rem; }
        .field-value { color: #E8E9DE; font-weight: 500; }

        .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 0.85rem; }
        .items-table th { text-align: left; color: #a0a8d0; font-size: 0.72rem; text-transform: uppercase; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.12); }
        .items-table td { padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.07); color: #E8E9DE; }
        .items-table tr:last-child td { border-bottom: none; }

        /* ── Drop-off / Pickup verification rows ── */
        .verif-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .verif-row:last-child { border-bottom: none; }

        .verif-left h5 { margin: 0 0 5px; font-size: 0.92rem; font-weight: 700; color: #E8E9DE; }
        .verif-left p  { margin: 3px 0; font-size: 0.8rem; color: #a0a8d0; }
        .verif-left .note { font-size: 0.75rem; color: #8b82b5; font-style: italic; margin-top: 6px; }

        .verif-right { display: flex; flex-direction: column; gap: 10px; align-items: flex-end; }

        /* Photo preview */
        .photo-preview {
            width: 130px; height: 95px;
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            background: rgba(255,255,255,0.05);
            font-size: 0.72rem; color: #8b82b5; text-align: center;
            line-height: 1.4;
        }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }

        /* Upload label */
        .upload-label {
            background: #241253;
            color: #E8E9DE;
            padding: 8px 18px;
            border-radius: 16px;
            font-size: 0.82rem;
            font-weight: bold;
            cursor: pointer;
            display: inline-block;
            transition: all 0.2s;
            font-family: inherit;
        }
        .upload-label:hover { background: #37216d; transform: translateY(-1px); }

        /* Verify button */
        .verify-btn {
            background: #198754;
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 16px;
            font-weight: bold;
            font-size: 0.85rem;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }
        .verify-btn:hover    { background: #157347; transform: translateY(-1px); }
        .verify-btn:disabled { background: #555; cursor: not-allowed; opacity: 0.55; transform: none; }

        .confirmed-tag { background: #d4edda; color: #155724; font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 10px; }
        .sent-tag      { background: #cfe2ff; color: #084298; font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 10px; }

        /* Action buttons */
        .action-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 22px; width: 97%; }
        .btn-approve { background: #198754; color: #fff; border: none; padding: 11px 26px; border-radius: 20px; font-weight: bold; cursor: pointer; font-size: 0.9rem; font-family: inherit; }
        .btn-approve:hover { background: #157347; transform: translateY(-1px); }
        .btn-reject  { background: #dc3545; color: #fff; border: none; padding: 11px 26px; border-radius: 20px; font-weight: bold; cursor: pointer; font-size: 0.9rem; font-family: inherit; }
        .btn-reject:hover  { background: #bb2d3b; transform: translateY(-1px); }
        .btn-verify  { background: #084298; color: #fff; border: none; padding: 11px 26px; border-radius: 20px; font-weight: bold; cursor: pointer; font-size: 0.9rem; font-family: inherit; }
        .btn-verify:hover  { background: #0a58ca; transform: translateY(-1px); }

        .msg-banner { padding: 10px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; margin-bottom: 16px; }
        .msg-success { background: rgba(34,197,94,0.15); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
        .msg-info    { background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }

        /* Reject modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 200; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #f1f0ea; color: #1e1b4b; border-radius: 20px; padding: 28px; width: 90%; max-width: 400px; }
        .modal-box h3 { margin-bottom: 12px; }
        .modal-box textarea { width: 100%; border-radius: 10px; border: 1px solid #ccc; padding: 10px; font-size: 0.9rem; resize: vertical; min-height: 80px; font-family: inherit; }
        .modal-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 14px; }
        .btn-cancel-modal { background: none; border: 1px solid #ccc; color: #666; padding: 8px 18px; border-radius: 20px; cursor: pointer; font-family: inherit; }

        #profileContainer { position: relative; display: inline-block; cursor: pointer; }
        #userImage { vertical-align: middle; margin-left: 5px; }
        #profileSelect { display: none; position: absolute; right: 0; top: 25px; background-color: #241253; border: 1px solid #E8E9DE; border-radius: 8px; min-width: 130px; z-index: 10; }
        #profileSelect.show { display: flex; flex-direction: column; }
        #profileSelect button { background: none; border: none; color: #E8E9DE; padding: 10px; text-align: left; width: 100%; cursor: pointer; font-size: 0.85rem; }
        #profileSelect button:hover { background-color: rgba(232,233,222,0.2); }

        @media (max-width: 600px) {
            .verif-row { flex-direction: column; }
            .verif-right { align-items: flex-start; }
        }
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
                <img id="userImage" src="image/user.png" width="20px" height="20px" onclick="profileMenu()">
                <div id="profileSelect">
                    <button onclick="showProfile()">Profile</button>
                    <button onclick="showLog()">Logout</button>
                </div>
            </span>
        </div>

        <button class="back-btn" onclick="history.back()">&#60; Back</button>
        <h1>Booking Details</h1>

        <?php if (isset($_GET['msg'])): ?>
        <div class="msg-banner <?php echo in_array($_GET['msg'], ['approved','verified','uploaded']) ? 'msg-success' : 'msg-info'; ?>">
            <?php
                $msgs = [
                    'approved' => ' Booking approved.',
                    'rejected' => ' Booking rejected.',
                    'verified' => ' Verification request sent to student.',
                    'uploaded' => ' Drop-off photo uploaded successfully.',
                ];
                echo $msgs[$_GET['msg']] ?? '';
            ?>
        </div>
        <?php endif; ?>

        <!-- Booking ID + status -->
        <div class="booking-header">
            <span class="booking-id-label">Booking #<?php echo $bid; ?></span>
            <?php if ($isPrio): ?><span class="priority-badge">EMERGENCY</span><?php endif; ?>
            <span class="status-badge status-<?php echo $bstat; ?>"><?php echo htmlspecialchars($b['Booking_Status']); ?></span>
        </div>

        <!-- ── 1. Student Details ── -->
        <div class="accordion">
            <button class="accordion-toggle open" onclick="toggleSection(this)">
                Student Details <span class="accordion-arrow">▼</span>
            </button>
            <div class="accordion-body open">
                <div class="field-row">
                    <span class="field-label">Student Name</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Student_Name']); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Student ID</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Student_ID']); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Email</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Student_Mail']); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Phone Number</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Student_PhoneNo'] ?? 'N/A'); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">College</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Residential_Block'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- ── 2. Booking Details ── -->
        <div class="accordion">
            <button class="accordion-toggle open" onclick="toggleSection(this)">
                Booking Details <span class="accordion-arrow">▼</span>
            </button>
            <div class="accordion-body open">
                <div class="field-row">
                    <span class="field-label">Booked On</span>
                    <span class="field-value"><?php echo htmlspecialchars($bookingDate); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Drop-off Date</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['DropOff_Date']); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Pick-up Date</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Pickup_Date']); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Total Items</span>
                    <span class="field-value"><?php echo $b['TotalItem']; ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Total Fee</span>
                    <span class="field-value">RM <?php echo number_format((float)$b['TotalFee'], 2); ?></span>
                </div>
                <?php if ($iRes && $iRes->num_rows > 0): ?>
                <table class="items-table" style="margin-top:14px;">
                    <thead><tr><th>Item</th><th>Qty</th><th>Price (RM)</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php while ($it = $iRes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($it['Item_Name']); ?></td>
                        <td><?php echo $it['Quantity']; ?></td>
                        <td><?php echo number_format((float)$it['Price'], 2); ?></td>
                        <td>RM <?php echo number_format((float)($it['Quantity'] * $it['Price']), 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── 3. Payment Details ── -->
        <div class="accordion">
            <button class="accordion-toggle open" onclick="toggleSection(this)">
                Payment Details <span class="accordion-arrow">▼</span>
            </button>
            <div class="accordion-body open">
                <div class="field-row">
                    <span class="field-label">Payment Status</span>
                    <span class="field-value">
                        <?php
                            $ps = strtolower($b['Payment_Status'] ?? '');
                            echo $isPaid ? ' Paid' : (($ps === 'p' || $ps === 'pending') ? 'Pay Later' : 'Unpaid');
                        ?>
                    </span>
                </div>
                <div class="field-row">
                    <span class="field-label">Payment Method</span>
                    <span class="field-value"><?php echo htmlspecialchars($b['Payment_Method'] ?? 'N/A'); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Payment Date</span>
                    <span class="field-value"><?php echo htmlspecialchars($paymentDate); ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Amount Paid</span>
                    <span class="field-value">RM <?php echo number_format((float)($b['Amount'] ?? 0), 2); ?></span>
                </div>
            </div>
        </div>

        <!-- ── 4. Drop-off / Verification ── -->
        <div class="accordion">
            <button class="accordion-toggle open" onclick="toggleSection(this)">
                Drop-off / Verification <span class="accordion-arrow">▼</span>
            </button>
            <div class="accordion-body open">

                <!-- DROP OFF row -->
                <div class="verif-row">
                    <div class="verif-left">
                        <h5>Drop off</h5>
                        <p>Staff must upload student's items photo as proof.</p>
                        <?php if ($uploadedPhoto): ?>
                            <p style="color:#22c55e; font-size:0.78rem; margin-top:6px; font-weight:600;"> Photo uploaded</p>
                        <?php endif; ?>
                    </div>
                    <div class="verif-right">
                        <!-- Preview box -->
                        <div class="photo-preview" id="photo-preview-<?php echo $bid; ?>">
                            <?php if ($uploadedPhoto): ?>
                                <img src="<?php echo htmlspecialchars($uploadedPhoto); ?>" alt="Drop-off proof">
                            <?php else: ?>
                                <br>No photo yet
                            <?php endif; ?>
                        </div>
                        <!-- Upload form -->
                        <form method="POST" enctype="multipart/form-data" style="display:inline;">
                            <input type="hidden" name="upload_dropoff" value="1">
                            <input type="file" name="dropoff_photo"
                                   id="dropoff-file-<?php echo $bid; ?>"
                                   accept="image/*" style="display:none;"
                                   onchange="previewPhoto(this, <?php echo $bid; ?>); this.form.submit();">
                            <label for="dropoff-file-<?php echo $bid; ?>" class="upload-label">
                                 Upload Photo
                            </label>
                        </form>
                    </div>
                </div>

                <!-- PICKUP / VERIFY row -->
                <div class="verif-row">
                    <div class="verif-left">
                        <h5>Pickup</h5>
                        <?php if ($studentConfirmed): ?>
                            <p style="color:#22c55e; font-weight:600;"> Student has confirmed collection.</p>
                        <?php elseif ($verifSent): ?>
                            <p style="color:#cfe2ff;"> Verification sent — awaiting student confirmation.</p>
                        <?php else: ?>
                            <p>Send verification request to student before releasing items.</p>
                            <p class="note">* Verify button only active when booking is Approved.</p>
                        <?php endif; ?>
                    </div>
                    <div class="verif-right">
                        <?php if ($studentConfirmed): ?>
                            <span class="confirmed-tag"> Student Confirmed</span>
                        <?php elseif ($verifSent): ?>
                            <span class="sent-tag"> Awaiting Student</span>
                        <?php elseif ($bstat === 'approved'): ?>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="verify" class="verify-btn"
                                    onclick="return confirm('Send verification request to student for Booking #<?php echo $bid; ?>?')">
                                    Verify
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="verify-btn" disabled title="Booking must be Approved first">
                                Verify
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Action buttons ── -->
        <div class="action-row">
            <?php if ($bstat === 'pending' && $needsManualApproval): ?>
                <!-- Storage >= 60%: staff must manually approve or reject -->
                <div style="width:97%; background:rgba(245,158,11,0.10); border:1px solid rgba(245,158,11,0.35); border-radius:14px; padding:14px 18px; margin-bottom:14px; font-size:0.85rem; color:#E8E9DE;">
                     Storage is at <strong style="color:#f59e0b;"><?php echo $storagePct; ?>%</strong> capacity (≥60%).
                    Manual approval is required for this booking.
                </div>
                <form method="POST" style="display:inline">
                    <button type="submit" name="approve" class="btn-approve"> Approve</button>
                </form>
                <button class="btn-reject" onclick="document.getElementById('rejectModal').classList.add('show')"> Reject</button>

            <?php elseif ($bstat === 'pending' && !$needsManualApproval): ?>
                <!-- Storage < 60%: auto-approved on payment, just show info -->
                <div style="width:97%; background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.25); border-radius:14px; padding:14px 18px; font-size:0.85rem; color:#E8E9DE;">
                    ℹ Storage is at <strong style="color:#22c55e;"><?php echo $storagePct; ?>%</strong> capacity.
                    This booking will be <strong>auto-approved</strong> once the student pays.
                    No action needed from staff.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Reject modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3>Reject Booking #<?php echo $bid; ?></h3>
        <form method="POST">
            <textarea name="reject_reason" placeholder="Reason (optional, shown to student)"></textarea>
            <div class="modal-btns">
                <button type="button" class="btn-cancel-modal"
                    onclick="document.getElementById('rejectModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="reject" class="btn-reject">Confirm Reject</button>
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
    function toggleSection(btn) {
        btn.classList.toggle('open');
        btn.nextElementSibling.classList.toggle('open');
    }

    function previewPhoto(input, bid) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const box = document.getElementById('photo-preview-' + bid);
                box.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function profileMenu() { document.getElementById('profileSelect').classList.toggle('show'); }
    function showProfile() { document.getElementById('profilePopup').classList.toggle('hidden'); }
    function showLog()     { document.getElementById('logoutPopup').classList.toggle('hidden'); }

    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
    document.addEventListener('click', function(e) {
        const c = document.getElementById('profileContainer');
        if (!c.contains(e.target)) document.getElementById('profileSelect').classList.remove('show');
    });
</script>
</body>
</html>