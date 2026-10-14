<?php
// Additive migration for an existing database. Run with a migration/admin account.
if (PHP_SAPI !== 'cli') exit(1);
try {
    $host = getenv('DB_HOST') ?: 'host.docker.internal';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'board';
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    if (!$user || $password === false) throw new RuntimeException('Set DB_USER and DB_PASSWORD.');
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    $columns = $pdo->query('SHOW COLUMNS FROM php_board')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('user_id', $columns, true)) {
        $pdo->exec('ALTER TABLE php_board ADD COLUMN user_id INT NULL, ADD INDEX idx_php_board_user_id (user_id)');
    }
    echo "Login schema is ready. Existing posts are preserved without an assigned owner.\n";
} catch (Throwable $error) {
    file_put_contents('php://stderr', "Database migration failed. Check administrator credentials and schema permissions.\n");
    exit(1);
}
