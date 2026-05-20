<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

session_start_once();

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password required.';
    } elseif (!login($username, $password)) {
        $error = 'Invalid username or password.';
    } else {
        header('Location: /dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?> — Login</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="login-body">

<div class="login-wrap">
    <div class="window">
        <div class="window-titlebar">
            <?= h(APP_NAME) ?>
        </div>
        <div class="window-body">
            <p class="login-subtitle">Sign in to your account</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/index.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

                <div class="form-row">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= h($_POST['username'] ?? '') ?>"
                           autofocus autocomplete="username">
                </div>
                <div class="form-row">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password">
                </div>
                <div class="form-row form-actions">
                    <button type="submit" class="btn btn-primary">Log In</button>
                </div>
            </form>
        </div>
    </div>
    <div class="login-version"><?= h(APP_NAME) ?> v<?= h(APP_VERSION) ?></div>
</div>

</body>
</html>
