<?php
/**
 * Egyszerű session alapú beléptetés.
 *
 * A projekt shared hosting kompatibilis, ezért itt nincs adatbázis vagy külső
 * identity provider. A cél az, hogy a privát auditfelület és az API-végpontok
 * ne legyenek nyilvánosan használhatók.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('hello_ai_audit_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_is_logged_in(): bool
{
    auth_start_session();
    return ($_SESSION['authenticated'] ?? false) === true;
}

function auth_login(string $username, string $password): bool
{
    auth_start_session();

    $isValid = hash_equals(AUTH_USERNAME, $username) && hash_equals(AUTH_PASSWORD, $password);
    if (!$isValid) {
        $_SESSION['login_error'] = 'Hibás felhasználónév vagy jelszó.';
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = AUTH_USERNAME;
    $_SESSION['login_error'] = '';
    $_SESSION['login_at'] = time();

    return true;
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function auth_login_error(): string
{
    auth_start_session();
    $message = (string) ($_SESSION['login_error'] ?? '');
    $_SESSION['login_error'] = '';
    return $message;
}

function auth_require_json(): void
{
    if (auth_is_logged_in()) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'ok' => false,
        'message' => 'Bejelentkezés szükséges.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function auth_require_download(): void
{
    if (auth_is_logged_in()) {
        return;
    }

    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Bejelentkezés szükséges.';
    exit;
}
