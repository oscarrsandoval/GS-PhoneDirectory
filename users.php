<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login();
require_admin();

$errors = [];
$form = ['username' => '', 'role' => 'editor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'editor');
        $form = ['username' => $username, 'role' => $role];

        $error = create_user($username, $password, $role);
        if ($error !== null) {
            $errors[] = $error;
        } else {
            set_flash('success', 'User “' . $username . '” added.');
            header('Location: users.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $username = (string)($_POST['username'] ?? '');

        if ($username === current_user()) {
            set_flash('error', 'You cannot remove your own account.');
        } else {
            $error = delete_user($username);
            set_flash(
                $error === null ? 'success' : 'error',
                $error ?? 'User “' . $username . '” removed.'
            );
        }
        header('Location: users.php');
        exit;
    }
}

$users = load_users();
usort($users, static fn(array $a, array $b): int =>
    strtolower((string)($a['username'] ?? '')) <=> strtolower((string)($b['username'] ?? '')));

render_header('Users');
?>
        <div class="page-head">
            <h1>Users</h1>
        </div>
        <p class="subtitle">
            <strong>Admins</strong> manage users and contacts.
            <strong>Editors</strong> can add and edit phone numbers only.
        </p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error" role="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div>
        <?php endforeach; ?>

        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-top:0;">Add a user</h3>
            <form method="post" action="users.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="field row">
                    <div>
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" maxlength="64" required
                               autocomplete="off" value="<?= e($form['username']) ?>">
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required
                               autocomplete="new-password">
                        <p class="hint">At least 8 characters.</p>
                    </div>
                    <div>
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <?php foreach (USER_ROLES as $r): ?>
                                <option value="<?= e($r) ?>" <?= $form['role'] === $r ? 'selected' : '' ?>>
                                    <?= ucfirst($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit"><?= icon('plus') ?>Add user</button>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $username = (string)($u['username'] ?? '');
                    $role = role_of($u);
                    $isSelf = $username === current_user();
                ?>
                    <tr>
                        <td>
                            <?= e($username) ?>
                            <?php if ($isSelf): ?><span class="hint">(you)</span><?php endif; ?>
                        </td>
                        <td><span class="badge badge-role-<?= e($role) ?>"><?= e(ucfirst($role)) ?></span></td>
                        <td class="num"><?= e((string)($u['created'] ?? '—')) ?></td>
                        <td class="row-actions">
                            <?php if ($isSelf): ?>
                                <span class="hint">—</span>
                            <?php else: ?>
                                <form method="post" action="users.php"
                                      onsubmit="return confirm('Remove user &quot;<?= e($username) ?>&quot;?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="username" value="<?= e($username) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit"
                                            aria-label="Remove <?= e($username) ?>"><?= icon('trash') ?>Remove</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php
render_footer();
