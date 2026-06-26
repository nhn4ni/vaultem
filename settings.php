<!DOCTYPE html>
<html lang="en">
<link rel="stylesheet" href="settings.css" type="text/css">
<link rel="stylesheet" href="mobile.css" type="text/css">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Settings</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
</head>
<body> 
<button id="back" onclick="history.back()">&#60; Back</button>

    <div id="wrapper">
    
        <div class="leftcontainer">
            <header>
            <h1 onclick="window.location.href='mainStatus.html'">VaulteM</h1>
        </header>
        </div>

        <div class="rightcontainer">
          <h1>Settings</h1>
            <div id="categorySettings">
                    <div onclick="window.location.href='forgotpass.php'"class="settingSelect">
                        <h3>Security</h3>
                        <p >Change password</p>
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