<?php

$conn = new mysqli("localhost","root","","vaultemdb");

if($conn->connect_error)
    {
        die ("Connection Failed");
    }

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

LEFT JOIN student s
ON b.Student_ID = s.Student_ID

LEFT JOIN residential_college r
ON s.Residential_ID = r.Residential_ID

LEFT JOIN item i
ON b.Booking_ID = i.Booking_ID

GROUP BY b.Booking_ID
";

$result = $conn->query($sql);

?>


<!DOCTYPE html>
<html lang="en">
<link rel="stylesheet" href="status.css" type="text/css">

<link rel="stylesheet" href="mobile.css" type="text/css">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM</title>
    <!-- <link rel="stylesheet" href="mainStatus.css" type="text/css"> -->

    
</head>

<body>

    <div id="wrapper">
        <!--<button class="back" onclick="history.back()"> &#60; Back</button>-->
        
        <div class="leftcontainer">
            <header>
                <h1 onclick="window.location.href='mainStatus.html'">VaulteM</h1>
            </header>

            <button type="button" id="booking" onclick="window.location.href='form.html'">
                Book space
            </button>
        </div>

        <div class="rightcontainer">
            <div id="userName">
                    Welcome, <span id="currentName">Guest</span>
            <span id="profileContainer">
                    <img id="userImage" src="/image/user.png" width="20px" height="20px" onclick="profileMenu()">
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

            <?php while($row = $result->fetch_assoc())
            {
                ?>
                <div class = "status-card">
                    <div class ="card-header">
                        <div class = "header-main">
                            <span class = "order-id">

                            ID: 
                            <?php echo $row['Booking_ID'];?>
                            </span>
                        </div>

        <div class="summary-info">

            <p>
                Drop-off date:
                <?php echo $row['DropOff_Date']; ?>
            </p>

            <p>
                Pick-up date:
                <?php echo $row['Pickup_Date']; ?>
            </p>

            <p>
                Item quantity:
                <?php echo $row['TotalItem']; ?>
            </p>

            <p>
                College:
                <?php echo $row['Residential_Block']; ?>
            </p>

            <p>
                Total fee:
                RM <?php echo $row['TotalFee']; ?>
            </p>

        </div>

    </div>

</div>

<?php } ?>



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

        let loggedInUser = "Guest";

        const nameSpan = document.getElementById("currentName");

        //inject user's name when they logged in
        if (loggedInUser) {
            nameSpan.textContent = loggedInUser;
        }


        const filterBtn = document.getElementById("filterBtn");
        const filterLabel = document.getElementById("filterLabel");


        filterBtn.addEventListener("click", function () {

            let currentOrder = filterBtn.getAttribute("data-order");

            if (currentOrder === "recent") {

                filterLabel.textContent = "Old to Recent";
                filterBtn.setAttribute("data-order", "old");
                filterBtn.classList.add("rotated");



            } else {
                // switch back to recent to old
                filterLabel.textContent = "Recent to Old";
                filterBtn.setAttribute("data-order", "recent");
                filterBtn.classList.remove("rotated");


            }
        });

        const viewDetailsBtn = document.getElementById("viewDetailsBtn");
        const detailsPanel = document.getElementById("detailsPanel");
        const btnText = viewDetailsBtn.querySelector(".btn-text");

        viewDetailsBtn.addEventListener("click", function () {
            detailsPanel.classList.toggle("open");
            viewDetailsBtn.classList.toggle("active");
            if (detailsPanel.classList.contains("open")) {
                btnText.textContent = "Hide details";
            } else {
                btnText.textContent = "View details";
            }
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