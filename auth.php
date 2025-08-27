<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Forbidden');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf_or_die(?string $token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        http_response_code(400);
        die('Bad request (CSRF).');
    }
}
