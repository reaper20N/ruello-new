<?php
session_start();
require 'db.php';

// Security Guard: Just check for the wristband
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

// The session stores the username, so look up this user's id
$stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :username");
$stmt->execute(['username' => $_SESSION['username']]);
$me = $stmt->fetch();

if (!$me) {
    // Account was deleted or renamed while logged in
    session_destroy();
    header("Location: login.php");
    exit();
}
$my_id = (int) $me['id'];

$error = "";
$success = "";

if (!empty($_SESSION['flash'])) {
    $success = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ---------------------------------------------------------------
// Handle POST actions: add / toggle / delete (only MY tasks)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = "Invalid request. Please try again.";

    } elseif ($action === 'add') {
        $title = trim($_POST['title'] ?? '');

        if ($title === "") {
            $error = "Task title is required!";
        } elseif (mb_strlen($title) > 255) {
            $error = "Task title is too long (max 255 characters).";
        } else {
            $stmt = $pdo->prepare("INSERT INTO Tasks (user_id, title) VALUES (:user_id, :title)");
            $stmt->execute(['user_id' => $my_id, 'title' => $title]);

            $_SESSION['flash'] = "Task added.";
            header("Location: user.php");
            exit();
        }

    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        // "AND user_id" makes sure users can only touch their own tasks
        $stmt = $pdo->prepare("UPDATE Tasks SET status = IF(status = 'done', 'pending', 'done') WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $my_id]);

        header("Location: user.php");
        exit();

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM Tasks WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $my_id]);

        $_SESSION['flash'] = "Task deleted.";
        header("Location: user.php");
        exit();
    }
}

// My tasks
$stmt = $pdo->prepare("SELECT id, title, status, created_at FROM Tasks WHERE user_id = :user_id ORDER BY status ASC, created_at DESC");
$stmt->execute(['user_id' => $my_id]);
$tasks = $stmt->fetchAll();

$pending_count = 0;
foreach ($tasks as $t) {
    if ($t['status'] === 'pending') $pending_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Floral Theme</title>
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
            max-width: 560px;
            margin-bottom: 24px;
            border-top: 5px solid #8fbc8f;
            box-sizing: border-box;
        }
        h1, h2 { color: #2c4a3e; margin: 0 0 12px 0; font-weight: 600; }
        h1 { font-size: 22px; }
        h2 { font-size: 18px; }
        p { color: #556b52; font-size: 14px; margin: 0 0 16px 0; }
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
        .add-form { display: flex; gap: 10px; }
        .add-form input[type="text"] {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #d4e0d0;
            border-radius: 8px;
            background-color: #fafbfc;
            box-sizing: border-box;
            font-size: 14px;
        }
        .add-form input:focus { border-color: #8fbc8f; outline: none; background-color: #fff; }
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
        .task {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid #eef2ec;
        }
        .task:last-child { border-bottom: none; }
        .task-title { color: #2c4a3e; font-size: 15px; word-break: break-word; }
        .task-date { color: #7a8f77; font-size: 12px; margin-top: 2px; }
        .task.done .task-title { text-decoration: line-through; opacity: 0.55; }
        .actions { display: flex; gap: 6px; flex-shrink: 0; }
        .actions form { margin: 0; }
        .btn-small {
            padding: 6px 12px; border: none; border-radius: 6px;
            font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .btn-toggle { background-color: #d4e0d0; color: #2c4a3e; }
        .btn-toggle:hover { background-color: #c2d4bc; }
        .btn-delete { background-color: #f2dede; color: #b94a48; }
        .btn-delete:hover { background-color: #e8c4c4; }
        .empty { text-align: center; color: #7a8f77; padding: 20px 0; font-size: 14px; }
    </style>
</head>
<body>

    <div class="card">
        <h1>🌿 Welcome, <?php echo h($_SESSION['username']); ?>!</h1>
        <p>You have <strong><?php echo $pending_count; ?></strong> pending task<?php echo $pending_count === 1 ? '' : 's'; ?>.</p>
        <div class="nav">
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="admin.php">Admin Center</a>
            <?php endif; ?>
            <a href="logout.php">Log Out</a>
        </div>
    </div>

    <div class="card">
        <h2>🌷 Add a Task</h2>

        <?php if ($error !== ""): ?>
            <div class="error-message"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ""): ?>
            <div class="success-message"><?php echo h($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="user.php" class="add-form">
            <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
            <input type="hidden" name="action" value="add">
            <input type="text" name="title" placeholder="What do you need to do?" maxlength="255" required>
            <button type="submit" class="btn-add">Add</button>
        </form>
    </div>

    <div class="card">
        <h2>🌸 My Tasks</h2>

        <?php if (!$tasks): ?>
            <div class="empty">No tasks yet. Enjoy the calm! 🌼</div>
        <?php endif; ?>

        <?php foreach ($tasks as $t): ?>
        <?php $done = ($t['status'] === 'done'); ?>
        <div class="task <?php echo $done ? 'done' : ''; ?>">
            <div>
                <div class="task-title"><?php echo h($t['title']); ?></div>
                <div class="task-date"><?php echo h(date('M j, Y', strtotime($t['created_at']))); ?></div>
            </div>
            <div class="actions">
                <form method="POST" action="user.php">
                    <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                    <button type="submit" class="btn-small btn-toggle"><?php echo $done ? 'Reopen' : 'Done'; ?></button>
                </form>
                <form method="POST" action="user.php" onsubmit="return confirm('Delete this task?');">
                    <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                    <button type="submit" class="btn-small btn-delete">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</body>
</html>