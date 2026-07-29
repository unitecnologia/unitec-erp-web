<?php
$attempts = [
  "firebird:dbname=C:/Sistema/Dados/dados.fdb;charset=WIN1252",
  "firebird:dbname=C:/Sistema/Dados/dados.fdb;charset=UTF8",
  "firebird:dbname=localhost/3050:C:/Sistema/Dados/dados.fdb;charset=WIN1252",
  "firebird:dbname=localhost:C:/Sistema/Dados/dados.fdb;charset=WIN1252",
];
foreach ($attempts as $dsn) {
  echo "DSN=$dsn\n";
  try {
    $pdo = new PDO($dsn, "SYSDBA", "masterkey");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $n = $pdo->query("SELECT COUNT(*) FROM RDB`$RELATIONS")->fetchColumn();
    echo "OK relations=$n\n";
    break;
  } catch (Throwable $e) {
    echo "ERR ".$e->getMessage()."\n";
  }
}
