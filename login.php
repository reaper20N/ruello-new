<?php
session_start(); // Must be the very first line!
require 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 1. Fetch user safely using PDO
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // 2. Verify and hand out the wristband
    if ($user && $password === $user['password']) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Crucial for authorization!

        // 3. The Traffic Cop (Redirection)
        if ($user['role'] === 'admin') {
            header("Location: admin.php");
            exit();
        } else {
            header("Location: user.php");
            exit();
        }
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Login - Floral Theme</title>
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

        .login-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(74, 93, 78, 0.1);
            width: 100%;
            max-width: 380px;
            border-top: 5px solid #8fbc8f;
        }

        h2 {
            color: #2c4a3e;
            text-align: center;
            margin-bottom: 24px;
            font-weight: 600;
        }

        label {
            color: #556b52;
            font-size: 14px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            margin-bottom: 16px;
            border: 1px solid #d4e0d0;
            border-radius: 8px;
            background-color: #fafbfc;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #8fbc8f;
            outline: none;
            background-color: #fff;
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #8fbc8f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button[type="submit"]:hover {
            background-color: #78a378;
        }

        .error-message {
            color: #b94a48;
            background-color: #f2dede;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    
    <div class="login-card">
        <form method="POST">
            <h2>🌸 Welcome Back</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <button type="submit">Log In</button>
        </form>
    </div>

</body>
</html>