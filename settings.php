<?php
session_start();

if (!isset($_SESSION['Student_ID']) && !isset($_SESSION['Staff_ID'])) {
    header("Location: login.php");
    exit();
}

$homeUrl = isset($_SESSION['Staff_ID']) ? 'staffMainStatus.php' : 'mainStatus.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Settings</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <link rel="stylesheet" href="settings.css" type="text/css">
    <link rel="stylesheet" href="mobile.css" type="text/css">
    <style>
        .leftcontainer {
            align-items: flex-start;
            justify-content: flex-start;
            padding-top: 10px;
        }
        #back {
            text-align: left;
            padding: 0 0 0 15px;
        }
        .leftcontainer header {
            width: 100%;
            text-align: center;
            padding-top: 20px;
        }
    </style>
</head>
<body>
<div id="wrapper">

    <div class="leftcontainer">
        <button id="back" onclick="history.back()">&#60; Back</button>
        <header>
            <h1 onclick="window.location.href='<?php echo $homeUrl; ?>'" style="cursor:pointer;">VaulteM</h1>
        </header>
    </div>

    <div class="rightcontainer">
        <h1>Settings</h1>
        <div id="categorySettings">

            <div onclick="window.location.href='forgotpass.php'" class="settingSelect">
                <h3>Security</h3>
                <p>Change password</p>
            </div>

            <div onclick="window.location.href='index.php'" class="settingSelect">
                <h3>Log out</h3>
                <p>Log out of your account</p>
            </div>

        </div>
    </div>
</div>
</body>
</html>