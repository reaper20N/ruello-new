<?php
session_start();
session_destroy(); // Rips off the wristband
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - Floral Theme</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9f6;
            background-image: radial-gradient(#e2ebd8 15%, transparent 16%), radial-gradient(#e2ebd8 15%, transparent 16%);
            background-size: 60px 60px;
            background-position: 0 0, 30px 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .logout-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(74, 93, 78, 0.1);
            width: 100%;
            max-width: 380px;
            text-align: center;
            border-top: 5px solid #8fbc8f;
        }

        h2 {
            color: #2c4a3e;
            margin-bottom: 12px;
            font-weight: 600;
        }

        p {
            color: #556b52;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .btn-login {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background-color: #8fbc8f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            box-sizing: border-box;
            transition: background-color 0.3s;
        }

        .btn-login:hover {
            background-color: #78a378;
        }
    </style>
</head>
<body>

    <div class="logout-card">
        <h2>🌿 Logged Out</h2>
        <p>You have been successfully signed out of the system. Have a wonderful day!</p>
        <a href="login.php" class="btn-login">Log In Again</a>
    </div>

</body>
</html>