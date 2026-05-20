<?php

function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function require_login(): void {
    session_start_once();
    if (empty($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        die('Access denied.');
    }
}

function current_user(): array {
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['user_role'] ?? 'user',
    ];
}

function login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_start_once();
    session_regenerate_id(true);
    $_SESSION['user_id']   = $row['id'];
    $_SESSION['username']  = $username;
    $_SESSION['user_role'] = $row['role'];
    return true;
}

function logout(): void {
    session_start_once();
    session_destroy();
}

function csrf_token(): string {
    session_start_once();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
