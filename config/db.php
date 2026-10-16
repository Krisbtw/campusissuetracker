<?php
/**
 * FixMyCampus - Universal Database Configuration (PostgreSQL / MySQL)
 * Supports Supabase, Vercel, Neon, Docker, and local XAMPP/MySQL
 */

// Set default timezone to Indian Standard Time (IST, UTC+5:30)
date_default_timezone_set('Asia/Kolkata');

// Suppress deprecation warnings on PHP 8.5+ to keep HTML/JSON clean
if (defined('E_DEPRECATED')) {
    error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

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
            $hasServerVal = array_key_exists($key, $_SERVER) && is_string($_SERVER[$key]) && strlen(trim($_SERVER[$key])) > 0;
            $hasEnvVal = array_key_exists($key, $_ENV) && is_string($_ENV[$key]) && strlen(trim($_ENV[$key])) > 0;
            if (!$hasServerVal && !$hasEnvVal) {
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

$explicitDriver = getenv('DB_DRIVER') ?: ($_ENV['DB_DRIVER'] ?? ($_SERVER['DB_DRIVER'] ?? ''));
$driver = !empty($explicitDriver) ? strtolower($explicitDriver) : (((getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '')) == '3306') ? 'mysql' : 'pgsql');
$host   = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? '127.0.0.1'));
$port   = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? ($driver === 'pgsql' ? '5432' : '3306')));
$db     = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'fixmycampus'));
$user   = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? ($driver === 'pgsql' ? 'postgres' : 'root')));
$pass   = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? ''));

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
}

if (!defined('DB_DRIVER')) define('DB_DRIVER', $driver);
if (!defined('DB_HOST'))   define('DB_HOST', $host);
if (!defined('DB_PORT'))   define('DB_PORT', $port);
if (!defined('DB_USER'))   define('DB_USER', $user);
if (!defined('DB_PASS'))   define('DB_PASS', $pass);
if (!defined('DB_NAME'))   define('DB_NAME', $db);

// Dynamic BASE_URL detection
$isVercel = !empty(getenv('VERCEL')) || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL'])
    || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'vercel.app') !== false)
    || !empty(getenv('VERCEL_URL')) || !empty($_SERVER['VERCEL_URL']);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appSubdir = (strpos($scriptDir, '/fixmycampus') !== false) ? '/fixmycampus/' : '/';

$domainHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['VERCEL_URL'] ?? (getenv('VERCEL_URL') ?: '')));

if ($isVercel || (!empty($domainHost) && strpos($domainHost, 'vercel.app') !== false)) {
    // On Vercel, a root path '/' ensures browser stays on deployment domain
    $detectedBaseUrl = '/';
} elseif (!empty($domainHost)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $proto = $isHttps ? 'https' : 'http';
    $detectedBaseUrl = $proto . '://' . $domainHost . $appSubdir;
} else {
    $detectedBaseUrl = $appSubdir;
}

$rawBaseUrlEnv = getenv('BASE_URL') ?: ($_ENV['BASE_URL'] ?? ($_SERVER['BASE_URL'] ?? ''));
if ($isVercel && (empty($rawBaseUrlEnv) || strpos($rawBaseUrlEnv, 'localhost') !== false)) {
    $rawBaseUrlEnv = '/';
}

if (!file_exists(__DIR__ . '/../uploads/')) {
    @mkdir(__DIR__ . '/../uploads/', 0777, true);
}
if (!file_exists(__DIR__ . '/../uploads/issues/')) {
    @mkdir(__DIR__ . '/../uploads/issues/', 0777, true);
}
if (!defined('BASE_URL')) define('BASE_URL', !empty($rawBaseUrlEnv) ? rtrim($rawBaseUrlEnv, '/') . '/' : $detectedBaseUrl);
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../uploads/issues/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', BASE_URL . 'uploads/issues/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

if (!defined('CLOUDINARY_CLOUD_NAME'))   define('CLOUDINARY_CLOUD_NAME',   getenv('CLOUDINARY_CLOUD_NAME')   ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ($_SERVER['CLOUDINARY_CLOUD_NAME'] ?? '')));
if (!defined('CLOUDINARY_API_KEY'))      define('CLOUDINARY_API_KEY',      getenv('CLOUDINARY_API_KEY')      ?: ($_ENV['CLOUDINARY_API_KEY'] ?? ($_SERVER['CLOUDINARY_API_KEY'] ?? '')));
if (!defined('CLOUDINARY_API_SECRET'))   define('CLOUDINARY_API_SECRET',   getenv('CLOUDINARY_API_SECRET')   ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? ($_SERVER['CLOUDINARY_API_SECRET'] ?? '')));
if (!defined('CLOUDINARY_UPLOAD_PRESET')) define('CLOUDINARY_UPLOAD_PRESET', getenv('CLOUDINARY_UPLOAD_PRESET') ?: ($_ENV['CLOUDINARY_UPLOAD_PRESET'] ?? ($_SERVER['CLOUDINARY_UPLOAD_PRESET'] ?? '')));

/**
 * Upload image file to Cloudinary REST API with authentication
 */
if (!function_exists('uploadToCloudinary')) {
    function uploadToCloudinary($fileTmpPath) {
        if (empty($fileTmpPath) || !file_exists($fileTmpPath)) {
            return null;
        }

        $cloudName    = defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : (getenv('CLOUDINARY_CLOUD_NAME') ?: '');
        $apiKey       = defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : (getenv('CLOUDINARY_API_KEY') ?: '');
        $apiSecret    = defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : (getenv('CLOUDINARY_API_SECRET') ?: '');
        $uploadPreset = defined('CLOUDINARY_UPLOAD_PRESET') ? CLOUDINARY_UPLOAD_PRESET : (getenv('CLOUDINARY_UPLOAD_PRESET') ?: '');

        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            error_log('Cloudinary credentials are not configured.');
            return null;
        }

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
        if (function_exists('curl_close') && PHP_VERSION_ID < 80000 && is_resource($ch)) {
            curl_close($ch);
        }

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
        if (function_exists('curl_close') && PHP_VERSION_ID < 80000 && is_resource($ch)) {
            curl_close($ch);
        }

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
        if (function_exists('curl_close') && PHP_VERSION_ID < 80000 && is_resource($ch)) {
            curl_close($ch);
        }

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
    if ($driver === 'sqlite') {
        $sqliteFile = __DIR__ . '/../database.sqlite';
        $dsn = "sqlite:" . $sqliteFile;
        $pdo = new CampusPDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } elseif ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
        $pdo = new CampusPDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET TIME ZONE 'Asia/Kolkata'");
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new CampusPDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+05:30'");
    }

    // Check if initial schema setup is needed
    if ($driver === 'sqlite') {
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$tableCheck || empty($tableCheck->fetch())) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    full_name TEXT NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'student',
                    status TEXT NOT NULL DEFAULT 'active',
                    department TEXT DEFAULT NULL,
                    phone TEXT DEFAULT NULL,
                    avatar TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS categories (
                    category_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    category_name TEXT UNIQUE NOT NULL,
                    icon TEXT DEFAULT 'bi-tools',
                    description TEXT DEFAULT NULL
                );
                CREATE TABLE IF NOT EXISTS issues (
                    issue_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    description TEXT NOT NULL,
                    category_id INTEGER DEFAULT NULL,
                    location TEXT NOT NULL,
                    priority TEXT NOT NULL DEFAULT 'medium',
                    status TEXT NOT NULL DEFAULT 'pending',
                    reported_by INTEGER NOT NULL,
                    assigned_to INTEGER DEFAULT NULL,
                    parent_id INTEGER DEFAULT NULL,
                    is_parent INTEGER DEFAULT 0,
                    affected_count INTEGER DEFAULT 1,
                    reopen_count INTEGER DEFAULT 0,
                    admin_remark TEXT DEFAULT NULL,
                    rating INTEGER DEFAULT NULL,
                    feedback TEXT DEFAULT NULL,
                    feedback_at DATETIME DEFAULT NULL,
                    resolution_image TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS announcements (
                    announcement_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    message TEXT NOT NULL,
                    urgency TEXT DEFAULT 'info',
                    created_by INTEGER NOT NULL,
                    is_active INTEGER DEFAULT 1,
                    expires_at DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS issue_images (
                    image_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    issue_id INTEGER NOT NULL,
                    image_path TEXT NOT NULL,
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS status_history (
                    history_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    issue_id INTEGER NOT NULL,
                    changed_by INTEGER NOT NULL,
                    old_status TEXT NOT NULL,
                    new_status TEXT NOT NULL,
                    remarks TEXT DEFAULT NULL,
                    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS notifications (
                    notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    issue_id INTEGER DEFAULT NULL,
                    message TEXT NOT NULL,
                    is_read INTEGER DEFAULT 0,
                    notif_type TEXT DEFAULT 'info',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                INSERT INTO categories (category_name, icon, description) VALUES
                ('Electrical Fault', 'bi-lightning-charge', 'Issues related to electrical wiring, power outages, or lighting failures'),
                ('Plumbing Issue', 'bi-droplet', 'Leaking pipes, blocked drains, or water supply problems'),
                ('Cleanliness / Waste', 'bi-trash', 'Overflowing bins, dirty areas, or sanitation concerns'),
                ('Infrastructure Damage', 'bi-building', 'Damaged walls, broken glass, structural concerns'),
                ('IT / Network Issue', 'bi-wifi', 'Internet outages, broken computers, or network problems'),
                ('Furniture Damage', 'bi-columns-gap', 'Broken chairs, desks, or classroom furniture'),
                ('Safety & Security', 'bi-shield-exclamation', 'Safety hazards, CCTV faults, broken locks'),
                ('Others', 'bi-three-dots', 'Any other campus issue not listed above');

                INSERT INTO users (full_name, email, password, role, department, phone) VALUES
                ('Admin User', 'admin@fixmycampus.com', '$2y$10\$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'admin', 'Administration', '9876543210'),
                ('John Student', 'student@fixmycampus.com', '$2y$10\$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'student', 'Computer Science', '9876543211'),
                ('Jane Staff', 'staff@fixmycampus.com', '$2y$10\$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'staff', 'Library', '9876543212'),
                ('Mike Maintenance', 'maintenance@fixmycampus.com', '$2y$10\$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'maintenance', 'Maintenance Dept', '9876543213'),
                ('Sarah Techie', 'tech@fixmycampus.com', '$2y$10\$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'maintenance', 'IT Department', '9876543214');
            ");
        }
    } elseif ($driver === 'pgsql') {
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

    // Auto-migrate user status column for staff verification
    try {
        if ($driver === 'pgsql') {
            $uColsStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'users'");
            $uCols = $uColsStmt ? $uColsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('status', $uCols)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
            }
        } else {
            $uCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('status', $uCols)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role");
            }
        }
    } catch (Exception $ex) {
        // Continue if column exists or ALTER restricted
    }

    // Auto-migrate issue feedback, rating, and resolution proof
    try {
        if ($driver === 'pgsql') {
            $issColsStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'issues'");
            $issCols = $issColsStmt ? $issColsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('rating', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN rating SMALLINT DEFAULT NULL");
            }
            if (!in_array('feedback', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN feedback TEXT DEFAULT NULL");
            }
            if (!in_array('feedback_at', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN feedback_at TIMESTAMPTZ DEFAULT NULL");
            }
            if (!in_array('resolution_image', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN resolution_image VARCHAR(500) DEFAULT NULL");
            }

            // Create announcements table if missing
            $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
                announcement_id SERIAL PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                urgency VARCHAR(20) DEFAULT 'info',
                created_by INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
                is_active SMALLINT DEFAULT 1,
                expires_at TIMESTAMPTZ DEFAULT NULL,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )");

            $annColsStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'announcements'");
            $annCols = $annColsStmt ? $annColsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('expires_at', $annCols)) {
                $pdo->exec("ALTER TABLE announcements ADD COLUMN expires_at TIMESTAMPTZ DEFAULT NULL");
            }
        } else {
            $issCols = $pdo->query("SHOW COLUMNS FROM issues")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('rating', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN rating TINYINT(1) DEFAULT NULL");
            }
            if (!in_array('feedback', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN feedback TEXT DEFAULT NULL");
            }
            if (!in_array('feedback_at', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN feedback_at DATETIME DEFAULT NULL");
            }
            if (!in_array('resolution_image', $issCols)) {
                $pdo->exec("ALTER TABLE issues ADD COLUMN resolution_image VARCHAR(500) DEFAULT NULL");
            }

            // Create announcements table if missing
            $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
                announcement_id INT(11) NOT NULL AUTO_INCREMENT,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                urgency ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
                created_by INT(11) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (announcement_id),
                KEY fk_ann_creator (created_by),
                CONSTRAINT fk_ann_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $annCols = $pdo->query("SHOW COLUMNS FROM announcements")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('expires_at', $annCols)) {
                $pdo->exec("ALTER TABLE announcements ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER is_active");
            }
        }
    } catch (Exception $ex) {
        // Continue if columns exist or ALTER restricted
    }

} catch (PDOException $e) {
    die(json_encode(["error" => "Database connection failed: " . $e->getMessage()]));
}