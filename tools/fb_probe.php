<?php
echo extension_loaded("pdo_firebird") ? "pdo_ok\n" : "pdo_fail\n";
if (!extension_loaded("pdo_firebird")) { exit(1); }
$dsn = "firebird:dbname=C:/Sistema/Dados/dados.fdb;charset=WIN1252";
try {
  $pdo = new PDO($dsn, "SYSDBA", "masterkey");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "connected\n";
  $sql = "SELECT TRIM(RDB`$RELATION_NAME) AS N FROM RDB`$RELATIONS WHERE RDB`$SYSTEM_FLAG = 0 AND RDB`$VIEW_BLR IS NULL ORDER BY 1";
  $tables = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
  echo "tables=".count($tables)."\n";
  foreach ($tables as $t) { echo $t."\n"; }
} catch (Throwable $e) {
  echo "ERR: ".$e->getMessage()."\n";
  exit(2);
}
