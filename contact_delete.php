<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/xml.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login();

$contacts = read_json(CONTACTS_FILE);

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

// Find the target contact.
$target = null;
foreach ($contacts as $c) {
    if ((int)($c['id'] ?? 0) === $id) {
        $target = $c;
        break;
    }
}

if ($target === null) {
    set_flash('error', 'That contact no longer exists.');
    header('Location: index.php');
    exit;
}

// Perform the delete on POST (CSRF-protected); GET shows a confirmation page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $contacts = array_filter($contacts, static fn(array $c): bool => (int)($c['id'] ?? 0) !== $id);
    write_json(CONTACTS_FILE, $contacts);
    rebuild_phonebook();

    set_flash('success', 'Contact deleted. phonebook.xml updated.');
    header('Location: index.php');
    exit;
}

$name = trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''));

render_header('Delete contact');
?>
        <div class="page-head">
            <h1>Delete contact</h1>
        </div>

        <form class="card confirm" method="post" action="contact_delete.php">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <p>Are you sure you want to delete <strong><?= e($name) ?></strong>
               (<span class="num"><?= e((string)($target['phone'] ?? '')) ?></span>)?
               This will remove them from the phonebook your phones download.</p>
            <div class="form-actions">
                <button class="btn btn-danger" type="submit"><?= icon('trash') ?>Delete contact</button>
                <a class="btn btn-ghost" href="index.php">Cancel</a>
            </div>
        </form>
<?php
render_footer();
