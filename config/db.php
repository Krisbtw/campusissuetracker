<?php
/**
 * FixMyCampus - Universal Database Configuration (PostgreSQL / MySQL)
 * Supports Supabase, Vercel, Neon, Render, Docker, and local XAMPP/MySQL
 */

// 1. Auto-load .env file if present in project root
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            $val = trim($val, "\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Safe serverless session storage for Vercel
if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || (isset($_ENV['VERCEL']) && $_ENV['VERCEL'])) {
        if (is_dir('/tmp') && is_writable('/tmp')) {
            session_save_path('/tmp');
        }
    }
}

// 3. Resolve connection parameters (URL or discrete variables)
$rawDbUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? (getenv('POSTGRES_URL') ?: ($_ENV['POSTGRES_URL'] ?? ''))));

$driver = 'pgsql';
$host   = 'aws-0-ap-south-1.pooler.supabase.com';
$port   = '5432';
$db     = 'postgres';
$user   = 'postgres.kwzuqhcxkghqohosmqrr';
$pass   = 'Krishalali69420';

if (!empty($rawDbUrl)) {
    $parsed = parse_url($rawDbUrl);
    if (!empty($parsed['scheme'])) {
        $scheme = strtolower($parsed['scheme']);
        $driver = ($scheme === 'mysql') ? 'mysql' : 'pgsql';
    }
    if (!empty($parsed['host'])) $host = $parsed['host'];
    if (!empty($parsed['port'])) $port = (string)$parsed['port'];
    if (!empty($parsed['user'])) $user = urldecode($parsed['user']);
    if (isset($parsed['pass']))  $pass = urldecode($parsed['pass']);
    if (!empty($parsed['path'])) {
        $cleanPath = ltrim($parsed['path'], '/');
        $db = rtrim(trim($cleanPath), '_');
    }
} else {
    $explicitDriver = getenv('DB_DRIVER') ?: ($_ENV['DB_DRIVER'] ?? ($_SERVER['DB_DRIVER'] ?? ''));
    if (!empty($explicitDriver)) {
        $driver = strtolower($explicitDriver);
    } elseif ((getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '')) == '3306') {
        $driver = 'mysql';
    }
    
    $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? $host));
    $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? $port));
    $db   = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? $db));
    $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? $user));
    $pass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? $pass));
}

if (!defined('DB_DRIVER')) define('DB_DRIVER', $driver);
if (!defined('DB_HOST'))   define('DB_HOST', $host);
if (!defined('DB_PORT'))   define('DB_PORT', $port);
if (!defined('DB_USER'))   define('DB_USER', $user);
if (!defined('DB_PASS'))   define('DB_PASS', $pass);
if (!defined('DB_NAME'))   define('DB_NAME', $db);

// Dynamic BASE_URL detection
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appSubdir = (strpos($scriptDir, '/fixmycampus') !== false) ? '/fixmycampus/' : '/';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$proto = $isHttps ? 'https' : 'http';
$detectedBaseUrl = (isset($_SERVER['HTTP_HOST']) ? ($proto . '://' . $_SERVER['HTTP_HOST'] . $appSubdir) : 'http://localhost/fixmycampus/');

if (!file_exists(__DIR__ . '/../uploads/')) {
    @mkdir(__DIR__ . '/../uploads/', 0777, true);
}
if (!file_exists(__DIR__ . '/../uploads/issues/')) {
    @mkdir(__DIR__ . '/../uploads/issues/', 0777, true);
}
if (!defined('BASE_URL')) define('BASE_URL', getenv('BASE_URL') ?: $detectedBaseUrl);
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../uploads/issues/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', BASE_URL . 'uploads/issues/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

if (!defined('CLOUDINARY_CLOUD_NAME'))   define('CLOUDINARY_CLOUD_NAME',   getenv('CLOUDINARY_CLOUD_NAME')   ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ($_SERVER['CLOUDINARY_CLOUD_NAME'] ?? 'djhubcw7')));
if (!defined('CLOUDINARY_API_KEY'))      define('CLOUDINARY_API_KEY',      getenv('CLOUDINARY_API_KEY')      ?: ($_ENV['CLOUDINARY_API_KEY'] ?? ($_SERVER['CLOUDINARY_API_KEY'] ?? '267922663126137')));
if (!defined('CLOUDINARY_API_SECRET'))   define('CLOUDINARY_API_SECRET',   getenv('CLOUDINARY_API_SECRET')   ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? ($_SERVER['CLOUDINARY_API_SECRET'] ?? 'KdFVfve-8K6sKgiYnxTHqprT1zU')));
if (!defined('CLOUDINARY_UPLOAD_PRESET')) define('CLOUDINARY_UPLOAD_PRESET', getenv('CLOUDINARY_UPLOAD_PRESET') ?: ($_ENV['CLOUDINARY_UPLOAD_PRESET'] ?? ($_SERVER['CLOUDINARY_UPLOAD_PRESET'] ?? 'fixmycampus_preset')));

/**
 * Upload image file to Cloudinary REST API with authentication
 */
if (!function_exists('uploadToCloudinary')) {
    function uploadToCloudinary($fileTmpPath) {
        if (empty($fileTmpPath) || !file_exists($fileTmpPath)) {
            return null;
        }

        $cloudName    = defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : 'djhubcw7';
        $apiKey       = defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : '267922663126137';
        $apiSecret    = defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : 'KdFVfve-8K6sKgiYnxTHqprT1zU';
        $uploadPreset = defined('CLOUDINARY_UPLOAD_PRESET') ? CLOUDINARY_UPLOAD_PRESET : 'fixmycampus_preset';

        $url = "https://api.cloudinary.com/v1_1/" . $cloudName . "/image/upload";
        $timestamp = time();
        $cfile = new CURLFile($fileTmpPath);

        // 1. Signed request with upload_preset
        $signatureStr = "timestamp=" . $timestamp . "&upload_preset=" . $uploadPreset . $apiSecret;
        $signature = sha1($signatureStr);

        $postData = [
            'file'          => $cfile,
            'api_key'       => $apiKey,
            'timestamp'     => $timestamp,
            'signature'     => $signature,
            'upload_preset' => $uploadPreset,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $jsonResponse = json_decode($response, true);
            if (isset($jsonResponse['secure_url'])) {
                return $jsonResponse['secure_url'];
            }
        }

        // 2. Signed request without upload_preset
        $signatureStrSimple = "timestamp=" . $timestamp . $apiSecret;
        $signatureSimple = sha1($signatureStrSimple);
        $postDataFallback = [
            'file'      => $cfile,
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signatureSimple,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataFallback);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $jsonResponse = json_decode($response, true);
            if (isset($jsonResponse['secure_url'])) {
                return $jsonResponse['secure_url'];
            }
        }

        // 3. Unsigned request with upload_preset
        $postDataUnsigned = [
            'file'          => $cfile,
            'upload_preset' => $uploadPreset,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataUnsigned);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $jsonResponse = json_decode($response, true);
            if (isset($jsonResponse['secure_url'])) {
                return $jsonResponse['secure_url'];
            }
        }

        return null;
    }
}

/**
 * Custom PDO wrapper ensuring transparent lastInsertId() support for PostgreSQL
 */
class CampusPDO extends PDO {
    public function lastInsertId(?string $name = null): string|false {
        $driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            if ($name !== null) {
                return parent::lastInsertId($name);
            }
            try {
                $stmt = $this->query("SELECT LASTVAL() AS id");
                if ($stmt) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['id'])) {
                        return (string)$row['id'];
                    }
                }
            } catch (Exception $e) {
                // Return parent fallback if LASTVAL is unavailable
            }
        }
        return parent::lastInsertId($name);
    }
}

try {
    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    }

    $pdo = new CampusPDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Check if initial schema setup is needed
    if ($driver === 'pgsql') {
        $tableCheck = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
        $hasUsers = ($tableCheck && (int)$tableCheck->fetchColumn() > 0);
        if (!$hasUsers) {
            $sqlPath = __DIR__ . '/../database_pg.sql';
            if (file_exists($sqlPath)) {
                $pdo->exec(file_get_contents($sqlPath));
            }
        }
    } else {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($tableCheck && $tableCheck->rowCount() == 0) {
            $sqlPath = __DIR__ . '/../database.sql';
            if (file_exists($sqlPath)) {
                $sql = file_get_contents($sqlPath);
                $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS [^;]+;/i', '', $sql);
                $sql = preg_replace('/USE [^;]+;/i', '', $sql);
                $pdo->exec($sql);
            }
        }
    }

    // Auto-migrate Duplicate Complaint Clustering columns if missing
    try {
        if ($driver === 'pgsql') {
            $colsStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'issues'");
            $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('parent_id', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN parent_id INT DEFAULT NULL REFERENCES issues(issue_id) ON DELETE SET NULL");
            }
            if (!in_array('is_parent', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN is_parent SMALLINT DEFAULT 0");
            }
            if (!in_array('affected_count', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN affected_count INT DEFAULT 1");
            }
            if (!in_array('reopen_count', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN reopen_count INT DEFAULT 0");
            }
        } else {
            $cols = $pdo->query("SHOW COLUMNS FROM issues")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('parent_id', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN parent_id INT(11) DEFAULT NULL AFTER assigned_to, ADD KEY fk_parent_issue (parent_id), ADD CONSTRAINT fk_parent_issue FOREIGN KEY (parent_id) REFERENCES issues(issue_id) ON DELETE SET NULL");
            }
            if (!in_array('is_parent', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN is_parent TINYINT(1) DEFAULT 0 AFTER parent_id");
            }
            if (!in_array('affected_count', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN affected_count INT(11) DEFAULT 1 AFTER is_parent");
            }
            if (!in_array('reopen_count', $cols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN reopen_count INT(11) DEFAULT 0 AFTER affected_count");
            }
        }
    } catch (Exception $ex) {
        // Continue if columns exist or ALTER is restricted
    }

} catch (PDOException $e) {
    die(json_encode(["error" => "Database connection failed: " . $e->getMessage()]));
}