<?php
/**
 * Session + authentication helpers.
 *
 * Users live in data/users.json as { username, password_hash, created }.
 * Passwords are only ever stored as bcrypt hashes (password_hash) and checked
 * with password_verify.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';

/**
 * Start a hardened session exactly once per request.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);

    // Only send the cookie over HTTPS when the request itself is HTTPS, so the
    // app still works on a plain-HTTP LAN setup while staying secure on TLS.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * @return array<int,array<string,mixed>>
 */
function load_users(): array
{
    return read_json(USERS_FILE);
}

function find_user(string $username): ?array
{
    foreach (load_users() as $user) {
        if (hash_equals((string)($user['username'] ?? ''), $username)) {
            return $user;
        }
    }

    return null;
}

/**
 * Attempt a login. Returns true on success and establishes the session.
 */
function login(string $username, string $password): bool
{
    $user = find_user($username);
    if ($user === null || !isset($user['password_hash'])) {
        // Spend roughly the same time as a real verify to blunt user enumeration.
        password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000');
        return false;
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    // Prevent session fixation.
    session_regenerate_id(true);
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['logged_in_at'] = time();

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function current_user(): ?string
{
    return isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Gate a page: redirect to the login screen if not authenticated.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Create a new user. Returns an error string, or null on success.
 */
function create_user(string $username, string $password): ?string
{
    $username = trim($username);
    if ($username === '') {
        return 'Username is required.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (find_user($username) !== null) {
        return 'That username already exists.';
    }

    $users = load_users();
    $users[] = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created' => date('Y-m-d'),
    ];
    write_json(USERS_FILE, $users);

    return null;
}
