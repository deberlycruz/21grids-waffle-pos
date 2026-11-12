<?php
// db.php  (included by every page)
// Philippine time for every date PHP prints
date_default_timezone_set('Asia/Manila');

// 1. Autoloading: load the classes in src/App automatically (PSR-4, Week 9)
$composerAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($composerAutoload)) {
    // Composer's autoloader (created by:  composer dump-autoload)
    require $composerAutoload;
} else {
    // The same idea written by hand with spl_autoload_register
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/src/App/';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// 2. The database connection used by the pages
use App\Core\Database;
use App\Core\DatabaseException;

try {
    $pdo = Database::connect();
} catch (DatabaseException $exception) {
    die($exception->getMessage());
}
