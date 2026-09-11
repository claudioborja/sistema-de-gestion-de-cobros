<?php
declare(strict_types=1);
// Local-only bootstrap: a private MySQL instance, never the system server.
if (PHP_SAPI !== 'cli') { exit(1); }
umask(0077);
$root = dirname(__DIR__);
$state = $root . '/writable/local-stack.json';
if (is_file($state)) {
    $runtime = json_decode(file_get_contents($state), true, flags: JSON_THROW_ON_ERROR);
} else {
    if (is_file($root . '/.env')) { fwrite(STDERR, "Ya existe .env; configura la base manualmente.\n"); exit(1); }
    $runtime = ['directory' => sys_get_temp_dir() . '/cobros-db-' . bin2hex(random_bytes(6)), 'password' => bin2hex(random_bytes(24))];
    mkdir($runtime['directory'], 0700);
    file_put_contents($state, json_encode($runtime, JSON_PRETTY_PRINT));
    chmod($state, 0600);
}
$dir = $runtime['directory'];
if (!is_dir($dir)) { fwrite(STDERR, "La carpeta de MySQL local no existe. Restaura una copia; no se recreará silenciosamente.\n"); exit(1); }
$run = static function (string $command): void { passthru($command, $code); if ($code !== 0) { exit($code); } };
if (!is_dir($dir . '/data/mysql')) {
    $run('mysqld --no-defaults --initialize-insecure --datadir=' . escapeshellarg($dir . '/data') . ' --log-error=' . escapeshellarg($dir . '/mysql.log'));
}
$socket = $dir . '/mysql.sock';
try { $db = @new mysqli('localhost', 'root', '', '', 0, $socket); } catch (Throwable) {
    $run('mysqld --no-defaults --datadir=' . escapeshellarg($dir . '/data') . ' --socket=' . escapeshellarg($socket) . ' --pid-file=' . escapeshellarg($dir . '/mysql.pid') . ' --log-error=' . escapeshellarg($dir . '/mysql.log') . ' --skip-networking --mysqlx=OFF --daemonize');
    $db = new mysqli('localhost', 'root', '', '', 0, $socket);
}
$db->query('CREATE DATABASE IF NOT EXISTS cobros_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$db->query('CREATE DATABASE IF NOT EXISTS cobros_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$password = $db->real_escape_string($runtime['password']);
$db->query("CREATE USER IF NOT EXISTS 'cobros_app'@'localhost' IDENTIFIED BY '$password'");
foreach (['cobros_dev', 'cobros_test'] as $database) {
    $db->query("GRANT ALL PRIVILEGES ON `$database`.* TO 'cobros_app'@'localhost'");
}
if (!is_file($root . '/.env')) {
    $env = "CI_ENVIRONMENT = development\napp.baseURL = 'http://127.0.0.1:8080/'\n";
    foreach (['default' => 'cobros_dev', 'tests' => 'cobros_test'] as $group => $database) {
        $env .= "database.$group.hostname = '$socket'\ndatabase.$group.database = '$database'\ndatabase.$group.username = 'cobros_app'\ndatabase.$group.password = '$password'\n";
    }
    file_put_contents($root . '/.env', $env);
    chmod($root . '/.env', 0600);
}
echo "MySQL local aislado disponible. Bases: cobros_dev y cobros_test. Credenciales privadas en .env.\n";
