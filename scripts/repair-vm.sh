#!/usr/bin/env bash
# Run as root on the confirmed Debian VM; uses local MariaDB socket authentication.
set -euo pipefail
cd /var/www/html/Deploy
umask 077
mariadb -uroot -e 'SELECT 1' >/dev/null
mkdir -p .local-backups
backup=".local-backups/vm-$(date +%Y%m%d-%H%M%S)"
mariadb-dump -uroot --single-transaction board > "$backup.sql"
cp -p .env "$backup.env"
# The SQL file contains only schema changes and a fresh, restricted app account.
docker run --rm --network none -i --entrypoint php -v "$PWD:/work" deploy-php <<'PHP'
<?php
umask(0077);
$user = 'board_app_' . bin2hex(random_bytes(4));
$password = 'Aa9!' . bin2hex(random_bytes(24));
$sql = file_get_contents('/work/database/schema.sql') . "\n";
$sql .= "ALTER TABLE php_board ADD COLUMN IF NOT EXISTS user_id INT NULL;\n";
$sql .= "CREATE INDEX IF NOT EXISTS idx_php_board_user_id ON php_board(user_id);\n";
$sql .= "CREATE USER '$user'@'172.18.%' IDENTIFIED BY '$password';\n";
$sql .= "GRANT SELECT, INSERT ON board.board_users TO '$user'@'172.18.%';\n";
$sql .= "GRANT SELECT, INSERT, UPDATE, DELETE ON board.php_board TO '$user'@'172.18.%';\n";
$env = "DB_HOST=host.docker.internal\nDB_PORT=3306\nDB_NAME=board\nDB_USER=$user\nDB_PASSWORD=$password\nHTTP_BIND=0.0.0.0\nHTTP_PORT=8082\n";
if (file_put_contents('/work/.local-backups/repair.sql', $sql) === false
    || file_put_contents('/work/.local-backups/repair.env', $env) === false) {
    throw new RuntimeException('Could not prepare repair files.');
}
PHP
mariadb -uroot board < .local-backups/repair.sql
mv .local-backups/repair.env .env
chmod 600 .env
rm .local-backups/repair.sql
mkdir -p src/uploads
chown -R 33:33 src/uploads
find src/uploads -type d -exec chmod 755 {} +
find src/uploads -type f -exec chmod 644 {} +
docker compose up --build -d --force-recreate
docker exec -i --user www-data board-app-container php <<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require '/var/www/html/index.php';
    ob_end_clean();
    echo "PHP page: OK\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo $e->getMessage(), PHP_EOL;
    exit(1);
}
PHP
curl --fail --silent --show-error --retry 10 --retry-delay 1 --retry-all-errors \
    --output /dev/null --write-out 'HTTP %{http_code}\n' http://127.0.0.1:8082/
echo 'Open http://192.168.2.128:8082'
