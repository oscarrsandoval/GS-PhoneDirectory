<?php
/**
 * Shared HTML chrome for the authenticated admin pages.
 *
 * Styling follows the UI/UX Pro Max "data-dense admin" design system:
 * Fira Sans (UI) + Fira Code (numbers), blue/amber palette, SVG icons (never
 * emoji), visible focus states, responsive table wrappers.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

/** Escape helper for templates. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Inline SVG icons (Lucide-style strokes). Using inline SVG keeps the app
 * dependency-free and avoids emoji-as-icon anti-patterns.
 */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
        'trash' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'alert' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
    ];
    $body = $paths[$name] ?? '';

    return '<svg class="' . e($class) . '" xmlns="http://www.w3.org/2000/svg" '
        . 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $body . '</svg>';
}

/**
 * One-shot flash message via the session (survives a redirect).
 */
function set_flash(string $type, string $message): void
{
    start_secure_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    start_secure_session();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    return null;
}

/**
 * Render the page header. $showNav controls whether the top bar / logout shows
 * (login and setup pages pass false).
 */
function render_header(string $title, bool $showNav = true): void
{
    $flash = take_flash();
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> &middot; <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Fira+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body<?= $showNav ? '' : ' class="centered"' ?>>
<?php if ($showNav): ?>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php">
                <?= icon('book', 'brand-icon') ?>
                <span><?= e(APP_NAME) ?></span>
            </a>
            <nav class="topnav">
                <span class="who"><?= icon('user') ?><?= e(current_user() ?? '') ?></span>
                <a class="btn btn-ghost" href="logout.php"><?= icon('logout') ?>Sign out</a>
            </nav>
        </div>
    </header>
    <main class="container">
<?php endif; ?>
<?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="status">
            <?= $flash['type'] === 'success' ? icon('check') : icon('alert') ?>
            <span><?= e($flash['message']) ?></span>
        </div>
<?php endif;
}

function render_footer(bool $showNav = true): void
{
    if ($showNav) {
        echo '    </main>' . "\n";
    }
    ?>
</body>
</html>
<?php
}
