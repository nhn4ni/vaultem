<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM</title>
    <link rel="stylesheet" href="block.css" type="text/css">
    <link rel="stylesheet" href="mobile.css" type="text/css">
</head>
<body>
    <div id="wrapper">
        <button class="back" onclick="history.back()"> &#60; Back</button> <div class="leftcontainer">
            <a href="studentMAIN.html"></a>
            <header>
                <h1>VaulteM</h1>
                <p>UTeM Store Management</p>
            </header>
        </div>

        <div class="rightcontainer">
            <h1>Sign in</h1>
            <form action="mainStatus.php" method="POST">
                <div class="nameGenderRow">
                    <input type="text" name="name" placeholder="Name" required>
                    <select name="gender" required>
                        <option value="" disabled selected>Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <input type="email" name="email" placeholder="Student Email" required> <input type="password" name="password" placeholder="Password" required> <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                
                <button type="submit" name="submit_signin">Sign in</button>
            </form>
        
        </div>
    </div>
</body>
</html>