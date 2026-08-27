<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 300; // 5 menit

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    startSession();
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function isLockedOut(): bool
{
    startSession();
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastFail = $_SESSION['login_last_fail'] ?? 0;
    if ($attempts >= MAX_LOGIN_ATTEMPTS && (time() - $lastFail) < LOCKOUT_SECONDS) {
        return true;
    }
    if ($attempts >= MAX_LOGIN_ATTEMPTS && (time() - $lastFail) >= LOCKOUT_SECONDS) {
        // lockout window sudah lewat, reset
        $_SESSION['login_attempts'] = 0;
    }
    return false;
}

function recordLoginFailure(): void
{
    startSession();
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_fail'] = time();
}

function resetLoginAttempts(): void
{
    startSession();
    unset($_SESSION['login_attempts'], $_SESSION['login_last_fail']);
}

function attemptLogin(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        resetLoginAttempts();
        return true;
    }

    recordLoginFailure();
    return false;
}

function logout(): void
{
    startSession();
    $_SESSION = [];
    session_destroy();
}
