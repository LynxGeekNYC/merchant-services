<?php
/* ---------- database ---------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'leadgen');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_pass');

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

/* ---------- HTTPS enforcement ---------- */
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $https = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $https", true, 301);
    exit;
}

session_start();
