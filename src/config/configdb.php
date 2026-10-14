<?php
require_once __DIR__ . '/bootstrap.php';
$host = getenv('DB_HOST') ?: 'host.docker.internal';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'board';
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
if (!$username || $password === false || $password === ''
    || !preg_match('/\A[a-zA-Z0-9_.:-]+\z/', $host)
    || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
    || !preg_match('/\A[a-zA-Z0-9_]+\z/', $dbname)) {
    throw new RuntimeException('Database environment configuration is missing or invalid.');
}
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 5,
]);