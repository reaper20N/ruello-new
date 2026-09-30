<?php
session_start();
require 'db.php';

// Security Guard: must be logged in AND an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. You must be an administrator.");
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$error = "";

// Which user are we editing? (GET on first load, POST on submit)
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, username, password, role FROM Users WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found. <a href='admin.php'>Back to admin</a>");
}

// UPDATE: handle the edit form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';   // blank = keep the current password
    $role     = $_POST['role'] ?? 'user';

    $editing_self = ($user['username'] === $_SESSION['username']);

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } elseif ($username === "") {
        $error = "Username can't be empty!";
    } elseif (!in_array($role, ['user', 'admin'], true)) {
        $error = "Invalid role selected!";
    } elseif ($editing_self && $role !== 'admin') {
        $error = "You can't remove your own admin role!";
    } else {
        // Make sure the new username isn't used by someone else
        $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :username AND id <> :id");
        $stmt->execute(['username' => $username, 'id' => $id]);

        if ($stmt->fetch()) {
            $error = "That username is already taken!";
        } else {
            if ($password === "") {
                $stmt = $pdo->prepare("UPDATE Users SET username = :username, role = :role WHERE id = :id");
                $stmt->execute(['username' => $username, 'role' => $role, 'id' => $id]);
            } else {
                // Plain text to match how login.php checks passwords
                $stmt = $pdo->prepare("UPDATE Users SET username = :username, password = :password, role = :role WHERE id = :id");
                $stmt->execute(['username' => $username, 'password' => $password, 'role' => $role, 'id' => $id]);
            }

            // Keep the session in sync if the admin renamed themselves
            if ($editing_self) {
                $_SESSION['username'] = $username;
            }

            $_SESSION['flash'] = "User '" . $username . "' updated.";
            header("Location: admin.php");
            exit();
        }
    }

    // Re-show what the admin typed if there was an error
    $user['username'] = $username;
    $user['role'] = $role;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Floral Theme</title>
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
            min-height: 100vh;
            margin: 0;
        }

        .edit-card {
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
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            margin-bottom: 16px;
            border: 1px solid #d4e0d0;
            border-radius: 8px;
            background-color: #fafbfc;
            box-sizing: border-box;
        }

        input:focus,
        select:focus {
            border-color: #8fbc8f;
            outline: none;
            background-color: #fff;
        }

        .hint {
            display: block;
            color: #7a8f77;
            font-size: 12px;
            margin-top: -10px;
            margin-bottom: 16px;
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

        .back-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #4a7a4a;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
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

    <div class="edit-card">
        <form method="POST" action="edit_user.php">
            <h2>✏️ Edit User</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">

            <label>Username:</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

            <label>New Password:</label>
            <input type="password" name="password" placeholder="Leave blank to keep current">
            <span class="hint">Leave blank to keep the current password.</span>

            <label>Role:</label>
            <select name="role">
                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>

            <button type="submit">Save Changes</button>
        </form>

        <a href="admin.php" class="back-link">← Back to Admin</a>
    </div>

</body>
</html>