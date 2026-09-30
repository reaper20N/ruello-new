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

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

$error = "";
$success = "";

if (!empty($_SESSION['flash'])) {
    $success = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ---------------------------------------------------------------
// Handle POST actions: add / toggle / delete
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = "Invalid request. Please try again.";

    // ---- ADD TASK ----
    } elseif ($action === 'add') {
        $title   = trim($_POST['title'] ?? '');
        $user_id = (int) ($_POST['user_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT id FROM Users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);

        if ($title === "") {
            $error = "Task title is required!";
        } elseif (mb_strlen($title) > 255) {
            $error = "Task title is too long (max 255 characters).";
        } elseif (!$stmt->fetch()) {
            $error = "Please choose a valid user.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO Tasks (user_id, title) VALUES (:user_id, :title)");
            $stmt->execute(['user_id' => $user_id, 'title' => $title]);

            $_SESSION['flash'] = "Task added.";
            header("Location: task.php");
            exit();
        }

    // ---- TOGGLE DONE / PENDING ----
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE Tasks SET status = IF(status = 'done', 'pending', 'done') WHERE id = :id");
        $stmt->execute(['id' => $id]);

        header("Location: task.php");
        exit();

    // ---- DELETE TASK ----
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM Tasks WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $_SESSION['flash'] = "Task deleted.";
        header("Location: task.php");
        exit();
    }
}

// Users for the dropdown
$users = $pdo->query("SELECT id, username FROM Users ORDER BY username")->fetchAll();

// All tasks with owner names
$tasks = $pdo->query(
    "SELECT t.id, t.title, t.status, t.created_at, u.username
     FROM Tasks t
     JOIN Users u ON u.id = t.user_id
     ORDER BY t.status ASC, t.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - Floral Theme</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9f6;
            background-image: radial-gradient(#e2ebd8 15%, transparent 16%), radial-gradient(#e2ebd8 15%, transparent 16%);
            background-size: 60px 60px;
            background-position: 0 0, 30px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 24px 12px;
            box-sizing: border-box;
        }
        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(74, 93, 78, 0.1);
            width: 100%;
            max-width: 760px;
            margin-bottom: 24px;
            border-top: 5px solid #8fbc8f;
            box-sizing: border-box;
        }
        h1, h2 { color: #2c4a3e; margin: 0 0 16px 0; font-weight: 600; }
        h1 { font-size: 22px; }
        h2 { font-size: 18px; }
        .nav a {
            display: inline-block;
            padding: 8px 16px;
            background-color: #d4e0d0;
            color: #2c4a3e;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            margin-right: 8px;
        }
        .nav a:hover { background-color: #c2d4bc; }
        .add-form { display: flex; flex-wrap: wrap; gap: 10px; }
        .add-form input[type="text"], .add-form select {
            flex: 1 1 160px;
            padding: 10px 12px;
            border: 1px solid #d4e0d0;
            border-radius: 8px;
            background-color: #fafbfc;
            box-sizing: border-box;
            font-size: 14px;
        }
        .add-form input:focus, .add-form select:focus {
            border-color: #8fbc8f; outline: none; background-color: #fff;
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
        }
        .btn-add:hover { background-color: #78a378; }
        .error-message, .success-message {
            padding: 10px; border-radius: 6px; font-size: 13px;
            text-align: center; margin-bottom: 16px;
        }
        .error-message { color: #b94a48; background-color: #f2dede; }
        .success-message { color: #3c763d; background-color: #dff0d8; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #e2ebd8; color: #2c4a3e; text-align: left; padding: 10px 12px; font-weight: 600; }
        td { padding: 10px 12px; border-bottom: 1px solid #eef2ec; color: #556b52; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .done-title { text-decoration: line-through; opacity: 0.6; }
        .badge { font-weight: 600; }
        .badge-done { color: #4a7a4a; }
        .badge-pending { color: #b8860b; }
        .inline-form { display: inline-block; margin: 0; }
        .btn-small {
            padding: 6px 12px; border: none; border-radius: 6px;
            font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .btn-toggle { background-color: #d4e0d0; color: #2c4a3e; }
        .btn-toggle:hover { background-color: #c2d4bc; }
        .btn-delete { background-color: #f2dede; color: #b94a48; }
        .btn-delete:hover { background-color: #e8c4c4; }
        .empty { text-align: center; color: #7a8f77; padding: 20px; }
    </style>
</head>
<body>

    <div class="card">
        <h1>📋 Task Manager</h1>
        <div class="nav">
            <a href="admin.php">← Admin Center</a>
            <a href="logout.php">Log Out</a>
        </div>
    </div>

    <div class="card">
        <h2>🌷 Assign New Task</h2>

        <?php if ($error !== ""): ?>
            <div class="error-message"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ""): ?>
            <div class="success-message"><?php echo h($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="task.php" class="add-form">
            <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="add">

            <input type="text" name="title" placeholder="Task title" maxlength="255" required>
            <select name="user_id" required>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo h($u['username']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-add">Add Task</button>
        </form>
    </div>

    <div class="card">
        <h2>🌸 All Tasks</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$tasks): ?>
                        <tr><td colspan="5" class="empty">No tasks yet. Add one above!</td></tr>
                    <?php endif; ?>

                    <?php foreach ($tasks as $t): ?>
                    <?php $done = ($t['status'] === 'done'); ?>
                    <tr>
                        <td class="<?php echo $done ? 'done-title' : ''; ?>"><?php echo h($t['title']); ?></td>
                        <td><?php echo h($t['username']); ?></td>
                        <td>
                            <span class="badge <?php echo $done ? 'badge-done' : 'badge-pending'; ?>">
                                <?php echo $done ? 'Done' : 'Pending'; ?>
                            </span>
                        </td>
                        <td><?php echo h(date('M j, Y', strtotime($t['created_at']))); ?></td>
                        <td>
                            <form method="POST" action="task.php" class="inline-form">
                                <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" class="btn-small btn-toggle"><?php echo $done ? 'Reopen' : 'Mark Done'; ?></button>
                            </form>
                            <form method="POST" action="task.php" class="inline-form"
                                  onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" class="btn-small btn-delete">Delete</button>
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