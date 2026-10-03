<?php
declare(strict_types=1);

$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = preg_replace('#/(owner|manager|worker|customer|admin|api)(/.*)?$#', '', $scriptDirectory) ?? $scriptDirectory;
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}
define('BASE_URL', rtrim($basePath, '/'));

$dbHost = getenv('BEAUTY_CABIN_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('BEAUTY_CABIN_DB_NAME') ?: 'beauty_cabin_v2';
$dbUser = getenv('BEAUTY_CABIN_DB_USER') ?: 'root';
$dbPass = getenv('BEAUTY_CABIN_DB_PASS');
if ($dbPass === false) {
    $dbPass = '';
}

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $ex) {
    error_log('Beauty Cabin database connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('The salon system is temporarily unavailable. Check the database configuration.');
}

