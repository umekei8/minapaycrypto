<?php
declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));

date_default_timezone_set('Asia/Tokyo');

// Security: httponly cookie
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

// Load database config if installed
$dbConfig = BASE_DIR . '/config/database.php';
if (file_exists($dbConfig)) {
    require_once $dbConfig;
}

// Autoload: src/<Namespace>/<Class>.php
spl_autoload_register(function (string $class): void {
    $file = BASE_DIR . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// If installer has not been run, redirect to it
if (!file_exists(BASE_DIR . '/config/installed.lock') &&
    strpos($_SERVER['SCRIPT_NAME'] ?? '', 'install.php') === false) {
    header('Location: /install/install.php');
    exit;
}
