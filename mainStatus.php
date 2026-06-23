<?php
session_start();

$conn = new mysqli("localhost", "root", "", "utem_accommodation");

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// 1. Determine sort order safely
$order = "DESC";
if (isset($_GET['sort']) && $_GET['sort'] == "old") {
    $order = "ASC";
}

// 2. Base Query to fetch bookings
$sql = "
    SELECT 
        b.Booking_ID,
        b.DropOff_Date,
        b.Pickup_Date,
        b.Booking_Status,
        s.Student_Name,
        r.Residential_Block,
        SUM(i.Quantity) AS TotalItem,
        SUM(i.Quantity * i.Price) AS TotalFee
    FROM booking b
    LEFT JOIN student s ON b.Student_ID = s.Student_ID
    LEFT JOIN residential_college r ON s.Residential_ID = r.Residential_ID
    LEFT JOIN item i ON b.Booking_ID = i.Booking_ID
    GROUP BY
        b.Booking_ID,
        b.DropOff_Date,
        b.Pickup_Date,
        b.Booking_Status,
        s.Student_Name,
        r.Residential_Block
    ORDER BY b.Pickup_Date $order
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM</title>
    <link rel="stylesheet" href="status.css" type="text/css">
    <link rel="stylesheet" href="mobile.css" type="text/css">
    <style>
        /* Additional styles for Pay button */
        .pay-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 8px 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .pay-btn:hover {
            background-color: #45a049;
            transform: scale(1.05);
        }
        
        .pay-btn:active {
            transform: scale(0.95);
        }
        
        .pay-btn.paid {
            background-color: #808080;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .pay-btn.paid:hover {
            transform: none;
        }
        
        .status-text {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .header-main {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .header-main {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .pay-btn {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <div id="wrapper">
        
        <div class="leftcontainer">
            <header>
                <h1 onclick="window.location.href='mainStatus.php'" style="cursor: pointer;">VaulteM</h1>
            </header>

            <button type="button" id="booking" onclick="window.location.href='form.php'">
                Book space
            </button>
        </div>

        <div class="rightcontainer">
            <div id="userName">
                Welcome,
                <span id="currentName">
                    <?php echo isset($_SESSION['Student_Name']) ? htmlspecialchars($_SESSION['Student_Name']) : "Guest"; ?>
                </span>

                <span id="profileContainer">
                    <div id="profileSelect">
                        <button onclick="showProfile();">Profile</button>
                        <button onclick="window.location.href='settings.html'">Settings</button>
                        <button onclick="window.location.href='settings.html'">Notification</button>
                        <button onclick="showLog();">Logout</button>
                    </div>
                </span>
            </div>

            <h1>Status</h1>

            <div class="filter-wrapper">
                <button id="filterBtn" data-order="recent">
                    <span id="filterLabel">Recent to Old</span>
                    <span class="arrow">&#9662;</span>
                </button>
            </div>

            <?php 
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) { 
                    $bookingID = $row['Booking_ID'];
                    $bookingStatus = $row['Booking_Status'];
                    
                    // Fetch items dynamic per booking using a safe prepared statement
                    $itemStmt = $conn->prepare("SELECT Item_Name, Quantity FROM item WHERE Booking_ID = ?");
                    $itemStmt->bind_param("s", $bookingID);
                    $itemStmt->execute();
                    $itemResult = $itemStmt->get_result();
                    
                    // Get payment status for this booking
                    $paymentStmt = $conn->prepare("SELECT Payment_Status, Amount FROM payment WHERE Booking_ID = ?");
                    $paymentStmt->bind_param("s", $bookingID);
                    $paymentStmt->execute();
                    $paymentResult = $paymentStmt->get_result();
                    $paymentRow = $paymentResult->fetch_assoc();
                    $paymentStatus = $paymentRow['Payment_Status'] ?? 'Pending';
                    $totalAmount = $paymentRow['Amount'] ?? $row['TotalFee'] ?? 0;
                    $paymentStmt->close();
            ?>
                <div class="status-card">
                    <div class="card-header">
                        <div class="header-main">
                            <span class="order-id">ID: <?php echo htmlspecialchars($row['Booking_ID']); ?></span>
                            <div class="header-actions">
                                <span class="status-text <?php 
                                    echo strtolower($bookingStatus) === 'pending' ? 'status-pending' : 
                                        (strtolower($bookingStatus) === 'approved' ? 'status-approved' : 'status-rejected'); 
                                ?>">
                                    <?php echo htmlspecialchars($bookingStatus); ?>
                                </span>
                                
                                <?php if (strtolower($bookingStatus) === 'pending' && strtolower($paymentStatus) !== 'paid'): ?>
                                    <button class="pay-btn" onclick="redirectToPayment('<?php echo $bookingID; ?>', <?php echo $totalAmount; ?>)">
                                        Pay Now
                                    </button>
                                <?php elseif (strtolower($paymentStatus) === 'paid'): ?>
                                    <button class="pay-btn paid" disabled>
                                        Paid
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="summary-info">
                        <p>Drop-off date: <?php echo htmlspecialchars($row['DropOff_Date']); ?></p>
                        <p>Pick-up date: <?php echo htmlspecialchars($row['Pickup_Date']); ?></p>
                        <p>Item quantity: <?php echo htmlspecialchars($row['TotalItem'] ?? '0'); ?></p>
                        <p>College: <?php echo htmlspecialchars($row['Residential_Block'] ?? 'N/A'); ?></p>
                        <p>Total fee: RM <?php echo htmlspecialchars(number_format((float)$row['TotalFee'], 2)); ?></p>
                        <?php if ($paymentRow): ?>
                            <p>Payment Status: <?php echo htmlspecialchars($paymentStatus); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="button-container">
                        <button class="viewDetailsBtn">
                            <span class="btn-text">View details</span>
                            <span class="btn-arrow">&#9662;</span>
                        </button>
                    </div>

                    <div class="card-details">
                        <div class="details-content">
                            <div class="detailsHeader">
                                <span>Item details</span>
                                <span>Quantity</span>
                            </div>

                            <?php 
                            while($item = $itemResult->fetch_assoc()) { 
                            ?>
                                <div class="itemRow">
                                    <span><?php echo htmlspecialchars($item['Item_Name']); ?></span>
                                    <span class="lineSpacer"></span>
                                    <span><?php echo htmlspecialchars($item['Quantity']); ?></span>
                                </div>
                            <?php 
                            } 
                            $itemStmt->close();
                            ?>
                        </div>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo "<p>No booking logs found.</p>";
            }
            ?>
        </div> 
    </div> 
    
    <div id="logoutPopup" class="hidden">
        <div id="logoutText">
            <p>Are you sure you want to logout?</p>
            <div id="logoutButton">
                <button id="yesBTN" onclick="logout();">Yes</button>
                <button id="noBTN" onclick="showLog();">No</button>
            </div>
        </div>
    </div>

    <div id="profilePopup" class="hidden">
        <div id="profileShortDetails">
            <h3>Profile</h3>
            <p>Name: <span id="profileName"></span></p>
            <p>Gender: <span id="profileGender"></span></p>
            <p>Email: <span id="profileEmail"></span></p>

            <div id="profileBTN">
                <button id="close" onclick="showProfile()">Close Profile</button>
                <button onclick="window.location.href='profile.html'">More Details</button>
            </div>
        </div>
    </div>

    <script>
        // Redirect to payment page with booking details
        function redirectToPayment(bookingId, amount) {
            // You can pass data via URL parameters or use session
            window.location.href = 'payment.php?booking_id=' + bookingId + '&amount=' + amount;
        }

        const filterBtn = document.getElementById("filterBtn");
        const filterLabel = document.getElementById("filterLabel");
        const params = new URLSearchParams(window.location.search);

        if (params.get("sort") === "old") {
            filterLabel.textContent = "Old to Recent";
            filterBtn.setAttribute("data-order", "old");
            filterBtn.classList.add("rotated");
        } else {
            filterLabel.textContent = "Recent to Old";
            filterBtn.setAttribute("data-order", "recent");
        }

        filterBtn.addEventListener("click", function () {
            let currentOrder = filterBtn.getAttribute("data-order");
            if (currentOrder === "recent") {
                window.location.href = "mainStatus.php?sort=old";
            } else {
                window.location.href = "mainStatus.php?sort=recent";
            }
        });

        document.querySelectorAll(".viewDetailsBtn").forEach(function(button) {
            button.addEventListener("click", function() {
                const card = button.closest(".status-card");
                const panel = card.querySelector(".card-details");
                const text = button.querySelector(".btn-text");

                if (panel) {
                    panel.classList.toggle("open");
                    button.classList.toggle("active");
                    
                    if (panel.classList.contains("open")) {
                        text.textContent = "Hide details";
                    } else {
                        text.textContent = "View details";
                    }
                }
            });
        });

        function profileMenu(){
            document.getElementById('profileSelect').classList.toggle('show');
        }

        function showLog(){
            document.getElementById('logoutPopup').classList.toggle('hidden');
        }

        function logout(){
            window.location.href='studentMAIN.html';
        }

        function showProfile(){
            document.getElementById('profilePopup').classList.toggle('hidden');
        }
    </script>
</body>
</html>