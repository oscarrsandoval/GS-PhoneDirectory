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

// Determine whether we are editing an existing contact or adding a new one.
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = false;
$contact = ['first_name' => '', 'last_name' => '', 'company' => '', 'phone' => '', 'type' => 'Work'];

if ($id > 0) {
    foreach ($contacts as $c) {
        if ((int)($c['id'] ?? 0) === $id) {
            $contact = $c;
            $editing = true;
            break;
        }
    }
    if (!$editing) {
        set_flash('error', 'That contact no longer exists.');
        header('Location: index.php');
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $first = trim((string)($_POST['first_name'] ?? ''));
    $last = trim((string)($_POST['last_name'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $type = (string)($_POST['type'] ?? 'Work');

    // Validation.
    if ($first === '' && $last === '') {
        $errors['name'] = 'Enter a first name, a last name, or both.';
    }
    if ($phone === '') {
        $errors['phone'] = 'A phone number is required.';
    } elseif (!preg_match('/^[0-9+()\-.\s*#]{1,32}$/', $phone)) {
        $errors['phone'] = 'Use only digits and the symbols + ( ) - . * # and spaces.';
    }
    if (!in_array($type, PHONE_TYPES, true)) {
        $errors['type'] = 'Choose a valid type.';
    }

    // Keep entered values for redisplay on error.
    $contact = ['first_name' => $first, 'last_name' => $last, 'company' => $company,
                'phone' => $phone, 'type' => $type];

    if ($errors === []) {
        // The whole load → modify → save runs under one lock (update_json) so
        // concurrent edits can't clobber each other or reuse an id.
        $found = true;
        update_json(CONTACTS_FILE, function (array $contacts) use (
            $editing, $id, $first, $last, $company, $phone, $type, &$found
        ): array {
            if ($editing) {
                $found = false;
                foreach ($contacts as &$c) {
                    if ((int)($c['id'] ?? 0) === $id) {
                        $c['first_name'] = $first;
                        $c['last_name'] = $last;
                        $c['company'] = $company;
                        $c['phone'] = $phone;
                        $c['type'] = $type;
                        $found = true;
                        break;
                    }
                }
                unset($c);
                return $contacts;
            }

            $contacts[] = [
                'id' => next_id($contacts),
                'first_name' => $first,
                'last_name' => $last,
                'company' => $company,
                'phone' => $phone,
                'type' => $type,
            ];
            return $contacts;
        });

        if ($editing && !$found) {
            set_flash('error', 'That contact no longer exists.');
            header('Location: index.php');
            exit;
        }

        rebuild_phonebook();

        set_flash('success', ($editing ? 'Contact updated.' : 'Contact added.') . ' phonebook.xml updated.');
        header('Location: index.php');
        exit;
    }
}

render_header($editing ? 'Edit contact' : 'Add contact');
?>
        <div class="page-head">
            <h1><?= $editing ? 'Edit contact' : 'Add contact' ?></h1>
        </div>

        <form class="card" method="post"
              action="contact_edit.php<?= $editing ? '?id=' . $id : '' ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field row">
                <div>
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" maxlength="64"
                           value="<?= e($contact['first_name']) ?>" autofocus autocomplete="off">
                </div>
                <div>
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" maxlength="64"
                           value="<?= e($contact['last_name']) ?>" autocomplete="off">
                </div>
            </div>
            <?php if (isset($errors['name'])): ?>
                <p class="hint" style="color:var(--danger)"><?= e($errors['name']) ?></p>
            <?php endif; ?>

            <div class="field">
                <label for="company">Company <span class="hint">(optional)</span></label>
                <input type="text" id="company" name="company" maxlength="128"
                       value="<?= e($contact['company']) ?>" autocomplete="off">
            </div>

            <div class="field row">
                <div>
                    <label for="phone">Phone number</label>
                    <input type="tel" id="phone" name="phone" maxlength="32" required
                           value="<?= e($contact['phone']) ?>"
                           aria-describedby="phone-hint" autocomplete="off">
                    <?php if (isset($errors['phone'])): ?>
                        <p class="hint" style="color:var(--danger)"><?= e($errors['phone']) ?></p>
                    <?php else: ?>
                        <p class="hint" id="phone-hint">Extension or full number, e.g. 1001 or +1 555 0100.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <?php foreach (PHONE_TYPES as $t): ?>
                            <option value="<?= e($t) ?>" <?= $contact['type'] === $t ? 'selected' : '' ?>>
                                <?= e($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit"><?= icon('check') ?><?= $editing ? 'Save changes' : 'Add contact' ?></button>
                <a class="btn btn-ghost" href="index.php">Cancel</a>
            </div>
        </form>
<?php
render_footer();
