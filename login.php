<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();

// If there are no users yet, push the operator to first-time setup.
if (load_users() === []) {
    header('Location: setup.php');
    exit;
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (login($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}

render_header('Sign in', false);
?>
<form class="auth-card" method="post" action="login.php" autocomplete="on">
    <div class="brand"><?= icon('book', 'brand-icon') ?><span><?= e(APP_NAME) ?></span></div>
    <p class="subtitle">Sign in to manage the directory</p>

    <?php if ($error): ?>
        <div class="flash flash-error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <?= csrf_field() ?>
    <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus
               autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required
               autocomplete="current-password">
    </div>
    <button class="btn" type="submit"><?= icon('logout') ?>Sign in</button>
</form>
<?php
render_footer(false);
