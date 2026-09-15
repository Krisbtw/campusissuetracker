<?php
/**
 * FixMyCampus - Authentication Check
 * Include this at the top of every protected page
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/reporter/admin/maintenance/') .  '/../index.php?error=Please+log+in+to+continue');
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header('Location: ' . BASE_URL . 'index.php?error=Access+denied');
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'name'       => $_SESSION['user_name'] ?? 'Guest',
        'email'      => $_SESSION['user_email'] ?? '',
        'role'       => $_SESSION['role'] ?? 'guest',
        'department' => $_SESSION['department'] ?? '',
    ];
}

function redirectToDashboard() {
    $role = $_SESSION['role'] ?? '';
    $base = BASE_URL;
    switch ($role) {
        case 'admin':       header("Location: {$base}admin/dashboard.php"); break;
        case 'maintenance': header("Location: {$base}maintenance/dashboard.php"); break;
        default:            header("Location: {$base}reporter/dashboard.php"); break;
    }
    exit();
}
