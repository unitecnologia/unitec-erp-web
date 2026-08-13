<?php

/**
 * Cria banco/usuário MySQL para o Unitec ERP (GrandChef MySQL porta 6033).
 * Uso: php scripts/setup-mysql.php [admin_user] [admin_pass]
 */

$adminUser = $argv[1] ?? 'grandweb';
$adminPass = $argv[2] ?? '';
$host = '127.0.0.1';
$port = 6033;
$dbName = 'unitec_erp';
$appUser = 'unitec';
$appPass = $argv[3] ?? 'UnitecErp2026!';

if ($adminPass === '') {
    fwrite(STDERR, "Informe a senha admin: php scripts/setup-mysql.php grandweb \"senha\" [app_pass]\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $adminUser,
        $adminPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER IF NOT EXISTS '{$appUser}'@'%' IDENTIFIED BY '{$appPass}'");
    $pdo->exec("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$appUser}'@'%'");
    $pdo->exec("FLUSH PRIVILEGES");

    echo "OK database={$dbName} user={$appUser} port={$port}\n";
    echo "APP_DB_PASSWORD={$appPass}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
