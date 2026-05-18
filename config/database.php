<?php
define('DB_CHARSET', 'utf8mb4');

$_mysqlUrl = getenv('MYSQL_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: null;
if ($_mysqlUrl) {
    $_p = parse_url($_mysqlUrl);
    define('DB_HOST', $_p['host']);
    define('DB_PORT', (string)($_p['port'] ?? 3306));
    define('DB_NAME', ltrim($_p['path'] ?? '/railway', '/'));
    define('DB_USER', urldecode($_p['user'] ?? '') ?: 'root');
    define('DB_PASS', urldecode($_p['pass'] ?? ''));
    unset($_p);
} else {
    define('DB_HOST', getenv('MYSQLHOST')     ?: 'localhost');
    define('DB_PORT', getenv('MYSQLPORT')     ?: '3306');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: 'rvm_portal');
    define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
}
unset($_mysqlUrl);

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
