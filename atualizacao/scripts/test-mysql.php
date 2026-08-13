<?php

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 3306);
$user = $argv[3] ?? 'root';
$pass = $argv[4] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port}",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    echo "OK: connected as {$user}@{$host}:{$port}\n";
    exit(0);
} catch (Throwable $e) {
    echo "FAIL: {$e->getMessage()}\n";
    exit(1);
}
