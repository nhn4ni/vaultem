<?php
session_start();

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "utem_accommodation");
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

$staff_name = $_SESSION['Staff_Name'] ?? 'Staff';

// ── Search ────────────────────────────────────────────────────────────────────
$search    = trim($_GET['q'] ?? '');
$searchSql = '';
if ($search) {
    $s = $conn->real_escape_string($search);
    $searchSql = "WHERE s.Student_Name LIKE '%$s%' OR s.Student_ID LIKE '%$s%' OR s.Student_Mail LIKE '%$s%'";
}

$students = $conn->query("
    SELECT s.Student_ID, s.Student_Name, s.Student_Mail, s.Student_PhoneNo, s.Gender,
           rc.Residential_Block,
           COUNT(b.Booking_ID) AS TotalBookings
    FROM student s
    LEFT JOIN residential_college rc ON s.Residential_ID = rc.Residential_ID
    LEFT JOIN booking b ON s.Student_ID = b.Student_ID
    $searchSql
    GROUP BY s.Student_ID, s.Student_Name, s.Student_Mail, s.Student_PhoneNo, s.Gender, rc.Residential_Block
    ORDER BY s.Student_Name ASC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM – Student List</title>
    <link rel="stylesheet" href="status.css">
    <link rel="stylesheet" href="mobile.css">
    <style>
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar input {
            flex: 1;
            min-width: 200px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(124,92,252,0.3);
            color: #E8E9DE;
            padding: 10px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
        }
        .search-bar input::placeholder { color: #8b82b5; }
        .search-bar input:focus { border-color: #7c5cfc; }
        .search-bar button {
            background: #241253;
            color: #E8E9DE;
            border: none;
            padding: 10px 22px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            width: auto;
        }
        .search-bar button:hover { background: #37216d; transform: translateY(-1px); }

        .student-card {
            background-color: #f1f0ea;
            color: #1e1b4b;
            border-radius: 18px;
            padding: 18px 22px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .student-avatar {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #7c5cfc, #b084ff);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.1rem; color: #fff;
            flex-shrink: 0;
        }

        .student-info { flex: 1; min-width: 160px; }
        .student-info h4 { margin: 0 0 3px; font-size: 0.95rem; }
        .student-info p  { margin: 0; font-size: 0.78rem; color: #555; }

        .student-meta { text-align: right; font-size: 0.78rem; color: #555; }
        .student-meta strong { display: block; font-size: 1rem; color: #1e1b4b; }

        .view-btn {
            background: #241253;
            color: #E8E9DE;
            border: none;
            padding: 8px 18px;
            border-radius: 16px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: bold;
            width: auto;
            text-decoration: none;
            display: inline-block;
        }
        .view-btn:hover { background: #37216d; transform: translateY(-1px); }

        .result-count { font-size: 0.78rem; color: #8b82b5; margin-bottom: 14px; }

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

        <h1>Students</h1>

        <form method="GET" class="search-bar">
            <input type="text" name="q" placeholder="Search by name, ID, or email…" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
            <?php if ($search): ?>
                <a href="staffStudentList.php" style="color:#8b82b5; font-size:0.82rem; line-height:2.5;">✕ Clear</a>
            <?php endif; ?>
        </form>

        <?php
        $count = $students ? $students->num_rows : 0;
        ?>
        <p class="result-count"><?php echo $count; ?> student<?php echo $count !== 1 ? 's' : ''; ?> found<?php echo $search ? " for \"$search\"" : ''; ?></p>

        <?php if ($students && $students->num_rows > 0):
            while ($st = $students->fetch_assoc()):
                $initial = strtoupper(substr($st['Student_Name'], 0, 1));
        ?>
        <div class="student-card">
            <div class="student-avatar"><?php echo $initial; ?></div>
            <div class="student-info">
                <h4><?php echo htmlspecialchars($st['Student_Name']); ?></h4>
                <p><?php echo htmlspecialchars($st['Student_ID']); ?> · <?php echo htmlspecialchars($st['Student_Mail']); ?></p>
                <p><?php echo htmlspecialchars($st['Residential_Block'] ?? 'N/A'); ?> · <?php echo $st['Gender'] === 'M' ? 'Male' : ($st['Gender'] === 'F' ? 'Female' : 'N/A'); ?></p>
            </div>
            <div class="student-meta">
                <strong><?php echo $st['TotalBookings']; ?></strong>
                Booking<?php echo $st['TotalBookings'] !== '1' ? 's' : ''; ?>
            </div>
            <a class="view-btn" href="staffStudentDetail.php?id=<?php echo urlencode($st['Student_ID']); ?>">View →</a>
        </div>
        <?php endwhile;
        else: ?>
            <p style="color:#8b82b5;">No students found.</p>
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