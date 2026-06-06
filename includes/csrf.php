<?php
/**
 * CSRF protection. A per-session token is embedded in every state-changing
 * form and verified on submit with a constant-time comparison.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden input carrying the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Verify the submitted token. Aborts the request with 400 on mismatch.
 */
function csrf_check(): void
{
    start_secure_session();
    $sent = (string)($_POST['csrf_token'] ?? '');
    $known = (string)($_SESSION['csrf_token'] ?? '');

    if ($known === '' || !hash_equals($known, $sent)) {
        http_response_code(400);
        exit('Invalid or missing CSRF token. Please go back and try again.');
    }
}
