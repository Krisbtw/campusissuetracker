<?php
/**
 * FixMyCampus - Authentication Check
 * Include this at the top of every protected page
 */

/**
 * Secret key derivation for HMAC token signing
 */
function getAuthSecret() {
    static $secret = null;
    if ($secret !== null) return $secret;
    $secret = getenv('AUTH_SECRET') ?: ($_ENV['AUTH_SECRET'] ?? ($_SERVER['AUTH_SECRET'] ?? ''));
    if (empty($secret)) {
        $dbPass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? 'fmc_pass'));
        $dbUser = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'fmc_user'));
        $secret = hash('sha256', $dbPass . ':' . $dbUser . ':FixMyCampus_Auth_Salt_2026');
    }
    return $secret;
}

/**
 * Generate a cryptographically signed auth token (HMAC-SHA256)
 */
function generateAuthToken($user) {
    if (!$user) return '';
    $uid = is_array($user) ? ($user['id'] ?? ($user['user_id'] ?? null)) : null;
    if (!$uid) return '';
    $payload = [
        'uid'   => (int)$uid,
        'role'  => $user['role'] ?? 'student',
        'name'  => $user['name'] ?? ($user['full_name'] ?? ($user['user_name'] ?? 'User')),
        'email' => $user['email'] ?? ($user['user_email'] ?? ''),
        'dept'  => $user['department'] ?? '',
        'exp'   => time() + (86400 * 30), // 30 days valid
        'v'     => 1
    ];
    $json = json_encode($payload);
    $data = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($json));
    $sig  = hash_hmac('sha256', $data, getAuthSecret());
    return $data . '.' . $sig;
}

/**
 * Verify HMAC-signed token and return decoded payload if valid and not expired
 */
function verifyAuthToken($token) {
    if (empty($token) || !is_string($token) || strpos($token, '.') === false) {
        return null;
    }
    list($data, $sig) = explode('.', $token, 2);
    if (empty($data) || empty($sig)) {
        return null;
    }
    $expectedSig = hash_hmac('sha256', $data, getAuthSecret());
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }
    $padded = str_pad(str_replace(['-', '_'], ['+', '/'], $data), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT);
    $json = base64_decode($padded);
    if (!$json) return null;
    $payload = json_decode($json, true);
    if (!$payload || empty($payload['uid']) || empty($payload['exp'])) {
        return null;
    }
    if ($payload['exp'] < time()) {
        return null; // Expired
    }
    return $payload;
}

/**
 * Set persistent HttpOnly auth cookie for seamless session across serverless functions
 */
function setAuthCookie($user) {
    if (headers_sent()) return;
    $token = generateAuthToken($user);
    if (empty($token)) return;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'vercel.app') !== false);

    if (PHP_VERSION_ID >= 70300) {
        setcookie('fmc_auth', $token, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('fmc_auth', $token, time() + (86400 * 30), '/; samesite=Lax', '', $isHttps, true);
    }
}

/**
 * Clear auth cookie on logout
 */
function clearAuthCookie() {
    if (headers_sent()) return;
    if (PHP_VERSION_ID >= 70300) {
        setcookie('fmc_auth', '', [
            'expires'  => time() - 86400,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('fmc_auth', '', time() - 86400, '/; samesite=Lax', '', false, true);
    }
}

/**
 * Ensure session is active and restored across serverless functions/lambdas
 */
function ensureSession($customInput = null) {
    if (session_status() === PHP_SESSION_NONE) {
        if (getenv('VERCEL') || (isset($_ENV['VERCEL']) && $_ENV['VERCEL']) || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'vercel.app') !== false)) {
            if (is_dir('/tmp') && is_writable('/tmp')) {
                @session_save_path('/tmp');
            }
        }
        @session_start();
    }

    if (!empty($_SESSION['user_id'])) {
        if (empty($_COOKIE['fmc_auth']) && !headers_sent()) {
            setAuthCookie([
                'id'         => $_SESSION['user_id'],
                'name'       => $_SESSION['user_name'] ?? 'User',
                'email'      => $_SESSION['user_email'] ?? '',
                'role'       => $_SESSION['role'] ?? 'student',
                'department' => $_SESSION['department'] ?? '',
            ]);
        }
        return true;
    }

    // Attempt token recovery from cookie, headers, or payload
    $token = null;
    if (!empty($_COOKIE['fmc_auth'])) {
        $token = $_COOKIE['fmc_auth'];
    } elseif (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $token = $_SERVER['HTTP_X_AUTH_TOKEN'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $token = $matches[1];
        }
    } elseif (is_array($customInput) && !empty($customInput['auth_token'])) {
        $token = $customInput['auth_token'];
    } elseif (!empty($_POST['auth_token'])) {
        $token = $_POST['auth_token'];
    } elseif (!empty($_GET['auth_token'])) {
        $token = $_GET['auth_token'];
    }

    if (!empty($token)) {
        $payload = verifyAuthToken($token);
        if ($payload) {
            $_SESSION['user_id']    = $payload['uid'];
            $_SESSION['user_name']  = $payload['name'];
            $_SESSION['user_email'] = $payload['email'] ?? '';
            $_SESSION['role']       = $payload['role'];
            $_SESSION['department'] = $payload['dept'] ?? '';
            if (empty($_COOKIE['fmc_auth']) && !headers_sent()) {
                setAuthCookie([
                    'id'         => $payload['uid'],
                    'name'       => $payload['name'],
                    'email'      => $payload['email'] ?? '',
                    'role'       => $payload['role'],
                    'department' => $payload['dept'] ?? '',
                ]);
            }
            return true;
        }
    }

    return false;
}

// Initial session check
ensureSession();

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/reporter/admin/maintenance/') .  '/../index.php?error=Please+log+in+to+continue');
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', (array)$roles)) {
        header('Location: ' . BASE_URL . 'index.php?error=Access+denied');
        exit();
    }
}

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
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

