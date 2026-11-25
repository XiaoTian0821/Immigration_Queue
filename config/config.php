<?php
/**
 * IQMS - Global configuration
 * Place this file in /config/config.php
 */
declare(strict_types=1);

define('APP_NAME', 'Immigration Queue Management System');
define('APP_URL', '/Immigration_Queue/'); // base path inside htdocs

// Database (XAMPP default: root with no password on MariaDB.
// This host runs MySQL 8.0 service on 127.0.0.1:3306 with root/123456.)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'iqms');
define('DB_USER', 'root');
define('DB_PASS', '123456');

date_default_timezone_set('Asia/Shanghai');

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/QueueManager.php';
