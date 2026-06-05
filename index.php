<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/xml.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login();

// Manual "Rebuild XML" action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rebuild') {
    csrf_check();
    try {
        rebuild_phonebook();
        set_flash('success', 'phonebook.xml rebuilt successfully.');
    } catch (Throwable $e) {
        set_flash('error', 'Could not rebuild phonebook.xml: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

$contacts = read_json(CONTACTS_FILE);

// Search filter (name or number).
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $needle = mb_strtolower($q);
    $contacts = array_filter($contacts, static function (array $c) use ($needle): bool {
        $hay = mb_strtolower(
            ($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '') . ' ' . ($c['phone'] ?? '')
        );
        return str_contains($hay, $needle);
    });
}

// Sort by last name, then first name.
usort($contacts, static function (array $a, array $b): int {
    return [strtolower((string)($a['last_name'] ?? '')), strtolower((string)($a['first_name'] ?? ''))]
        <=> [strtolower((string)($b['last_name'] ?? '')), strtolower((string)($b['first_name'] ?? ''))];
});

// Build the public phonebook.xml URL to display for phone configuration.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'your-server';
$baseDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$publicUrl = $scheme . '://' . $host . $baseDir . '/phonebook.xml';

render_header('Directory');
?>
        <div class="page-head">
            <div>
                <h1>Phone Directory</h1>
            </div>
            <a class="btn" href="contact_edit.php"><?= icon('plus') ?>Add contact</a>
        </div>

        <div class="pburl">
            <?= icon('download') ?>
            <span>Point your Grandstream phones at:</span>
            <code><?= e($publicUrl) ?></code>
        </div>

        <div class="toolbar">
            <form class="search" method="get" action="index.php">
                <?= icon('search') ?>
                <input type="text" name="q" placeholder="Search name or number"
                       value="<?= e($q) ?>" aria-label="Search contacts">
            </form>
            <?php if ($q !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="index.php">Clear</a>
            <?php endif; ?>
            <span class="spacer"></span>
            <form method="post" action="index.php"
                  title="Regenerate phonebook.xml from the current contacts">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rebuild">
                <button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?>Rebuild XML</button>
            </form>
        </div>

        <?php if ($contacts === []): ?>
            <div class="card empty">
                <?= icon('book') ?>
                <h3><?= $q !== '' ? 'No matches' : 'No contacts yet' ?></h3>
                <p>
                    <?= $q !== ''
                        ? 'No contacts match “' . e($q) . '”.'
                        : 'Add your first contact to start building the phonebook.' ?>
                </p>
                <?php if ($q === ''): ?>
                    <a class="btn" href="contact_edit.php"><?= icon('plus') ?>Add contact</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Number</th>
                            <th>Type</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contacts as $c):
                        $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                        $type = (string)($c['type'] ?? 'Work');
                    ?>
                        <tr>
                            <td><?= e($name) ?></td>
                            <td class="num"><?= e((string)($c['phone'] ?? '')) ?></td>
                            <td><span class="badge badge-<?= e($type) ?>"><?= e($type) ?></span></td>
                            <td class="row-actions">
                                <a class="btn btn-ghost btn-sm" href="contact_edit.php?id=<?= (int)$c['id'] ?>"
                                   aria-label="Edit <?= e($name) ?>"><?= icon('edit') ?>Edit</a>
                                <a class="btn btn-danger btn-sm" href="contact_delete.php?id=<?= (int)$c['id'] ?>"
                                   aria-label="Delete <?= e($name) ?>"><?= icon('trash') ?>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
<?php
render_footer();
