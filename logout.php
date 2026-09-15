<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Unset all session variables
$_SESSION = [];

// Expire session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Redirect to sign in page
$redirectUrl = rtrim(BASE_URL, '/') . '/index.php';
header("Location: {$redirectUrl}");
exit();
