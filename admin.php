<?php
session_start();
require 'db.php';

// Security Guard: Check for wristband AND admin status
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. You must be an administrator.");
}

// CSRF token (same key used by edit_user.php)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$error = "";
$success = "";

// Message left by edit_user.php or by a previous action here
if (!empty($_SESSION['flash'])) {
    $success = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ---------------------------------------------------------------
// Handle POST actions: add / delete / set_role
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = "Invalid request. Please try again.";

    // ---- ADD USER ----
    } elseif ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';

        if ($username === "" || $password === "") {
            $error = "Username and password are required!";
        } elseif (!in_array($role, ['user', 'admin'], true)) {
            $error = "Invalid role selected!";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :username");
            $stmt->execute(['username' => $username]);

            if ($stmt->fetch()) {
                $error = "That username is already taken!";
            } else {
                // Plain text to match how login.php checks passwords
                $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES (:username, :password, :role)");
                $stmt->execute(['username' => $username, 'password' => $password, 'role' => $role]);

                $_SESSION['flash'] = "User '" . $username . "' added.";
                header("Location: admin.php");
                exit();
            }
        }

    // ---- DELETE USER ----
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT username FROM Users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = "User not found.";
        } elseif ($target['username'] === $_SESSION['username']) {
            $error = "You can't delete your own account!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Users WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $_SESSION['flash'] = "User '" . $target['username'] . "' deleted.";
            header("Location: admin.php");
            exit();
        }

    // ---- CHANGE ROLE ----
    } elseif ($action === 'set_role') {
        $id   = (int) ($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? '';

        $stmt = $pdo->prepare("SELECT username FROM Users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = "User not found.";
        } elseif (!in_array($role, ['user', 'admin'], true)) {
            $error = "Invalid role selected!";
        } elseif ($target['username'] === $_SESSION['username'] && $role !== 'admin') {
            $error = "You can't remove your own admin role!";
        } else {
            $stmt = $pdo->prepare("UPDATE Users SET role = :role WHERE id = :id");
            $stmt->execute(['role' => $role, 'id' => $id]);

            $_SESSION['flash'] = "Role of '" . $target['username'] . "' changed to " . $role . ".";
            header("Location: admin.php");
            exit();
        }
    }
}

// Fetch every registered user
$stmt = $pdo->query("SELECT id, username, password, role FROM Users ORDER BY id");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Area - Floral Theme</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9f6;
            background-image: radial-gradient(#e2ebd8 15%, transparent 16%), radial-gradient(#e2ebd8 15%, transparent 16%);
            background-size: 60px 60px;
            background-position: 0 0, 30px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 24px 12px;
            box-sizing: border-box;
        }

        .admin-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(74, 93, 78, 0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border-top: 5px solid #8fbc8f;
        }

        h1 {
            color: #2c4a3e;
            font-size: 22px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        p {
            color: #556b52;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .btn-logout {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background-color: #d4e0d0;
            color: #2c4a3e;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            box-sizing: border-box;
            transition: background-color 0.3s;
        }

        .btn-logout:hover {
            background-color: #c2d4bc;
        }

        .users-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(74, 93, 78, 0.1);
            width: 100%;
            max-width: 760px;
            margin-top: 24px;
            border-top: 5px solid #8fbc8f;
            box-sizing: border-box;
        }

        .users-card h2 {
            color: #2c4a3e;
            font-size: 18px;
            margin: 0 0 16px 0;
            font-weight: 600;
            text-align: left;
        }

        /* Add user form */
        .add-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .add-form input[type="text"],
        .add-form input[type="password"],
        .add-form select {
            flex: 1 1 140px;
            padding: 10px 12px;
            border: 1px solid #d4e0d0;
            border-radius: 8px;
            background-color: #fafbfc;
            box-sizing: border-box;
            font-size: 14px;
        }

        .add-form input:focus,
        .add-form select:focus {
            border-color: #8fbc8f;
            outline: none;
            background-color: #fff;
        }

        .btn-add {
            padding: 10px 20px;
            background-color: #8fbc8f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-add:hover {
            background-color: #78a378;
        }

        /* Messages */
        .error-message {
            color: #b94a48;
            background-color: #f2dede;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 16px;
        }

        .success-message {
            color: #3c763d;
            background-color: #dff0d8;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 16px;
        }

        /* Users table */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background-color: #e2ebd8;
            color: #2c4a3e;
            text-align: left;
            padding: 10px 12px;
            font-weight: 600;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eef2ec;
            color: #556b52;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .role-admin {
            color: #b94a48;
            font-weight: 600;
        }

        .role-user {
            color: #4a7a4a;
            font-weight: 600;
        }

        .inline-form {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            margin: 0;
        }

        .inline-form select {
            padding: 6px 8px;
            border: 1px solid #d4e0d0;
            border-radius: 6px;
            background-color: #fafbfc;
            font-size: 13px;
        }

        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-role {
            background-color: #d4e0d0;
            color: #2c4a3e;
        }

        .btn-role:hover {
            background-color: #c2d4bc;
        }

        .btn-delete {
            background-color: #f2dede;
            color: #b94a48;
        }

        .btn-delete:hover {
            background-color: #e8c4c4;
        }

        .btn-small:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .you-tag {
            font-size: 11px;
            color: #7a8f77;
            font-weight: 400;
        }
    </style>
</head>
<body>

    <div class="admin-card">
        <h1>🌿 Admin Control Center</h1>
        <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</p>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>

    <!-- ADD NEW USER -->
    <div class="users-card">
        <h2>🌷 Add New User</h2>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="admin.php" class="add-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="add">

            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" class="btn-add">Add User</button>
        </form>
    </div>

    <!-- ALL USERS -->
    <div class="users-card">
        <h2>🌸 Registered Users</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Role</th>
                        <th>Change Role</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <?php $is_self = ($u['username'] === $_SESSION['username']); ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($u['username']); ?>
                            <?php if ($is_self): ?><span class="you-tag">(you)</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($u['password']); ?></td>
                        <td class="<?php echo $u['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                            <?php echo htmlspecialchars($u['role']); ?>
                        </td>

                        <!-- Change role -->
                        <td>
                            <form method="POST" action="admin.php" class="inline-form">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                <select name="role" <?php echo $is_self ? 'disabled' : ''; ?>>
                                    <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <button type="submit" class="btn-small btn-role" <?php echo $is_self ? 'disabled' : ''; ?>>Save</button>
                            </form>
                        </td>

                        <!-- Delete -->
                        <td>
                            <form method="POST" action="admin.php" class="inline-form"
                                  onsubmit="return confirm('Delete user <?php echo htmlspecialchars(addslashes($u['username']), ENT_QUOTES); ?>?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="btn-small btn-delete" <?php echo $is_self ? 'disabled' : ''; ?>>Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>