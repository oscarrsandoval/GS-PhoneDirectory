<?php
/**
 * First-run setup: create the initial admin user.
 *
 * This page is only active while data/users.json is empty. Once any user
 * exists it refuses to do anything, so it is safe to leave on disk. To add
 * more users later, see the README (or temporarily clear users.json on a
 * trusted machine).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();

// Lock the page once at least one account exists.
if (load_users() !== []) {
    render_header('Setup complete', false);
    ?>
    <div class="auth-card">
        <div class="brand"><?= icon('book', 'brand-icon') ?><span><?= e(APP_NAME) ?></span></div>
        <div class="flash flash-success" role="status">
            <?= icon('check') ?><span>Setup is already complete.</span>
        </div>
        <p class="subtitle">An admin account already exists. This page is now disabled.</p>
        <a class="btn" href="login.php"><?= icon('logout') ?>Go to sign in</a>
    </div>
    <?php
    render_footer(false);
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    } else {
        $error = create_user($username, $password);
        if ($error !== null) {
            $errors[] = $error;
        } else {
            // Log the new admin straight in.
            login($username, $password);
            set_flash('success', 'Admin account created. Welcome!');
            header('Location: index.php');
            exit;
        }
    }
}

render_header('First-time setup', false);
?>
<form class="auth-card" method="post" action="setup.php" novalidate>
    <div class="brand"><?= icon('book', 'brand-icon') ?><span><?= e(APP_NAME) ?></span></div>
    <p class="subtitle">Create your first admin account</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error" role="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div>
    <?php endforeach; ?>

    <?= csrf_field() ?>
    <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus
               autocomplete="username" value="<?= e($username) ?>">
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required
               autocomplete="new-password">
        <p class="hint">At least 8 characters.</p>
    </div>
    <div class="field">
        <label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" required autocomplete="new-password">
    </div>
    <button class="btn" type="submit"><?= icon('check') ?>Create admin &amp; sign in</button>
</form>
<?php
render_footer(false);
