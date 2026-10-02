<?php
// tools/backup.php — respaldo .sql generado en PHP (requiere sesión admin). Sin mysqldump.
declare(strict_types=1);
session_start();
if (empty($_SESSION['sant_admin'])) { http_response_code(403); echo 'Acceso denegado. Entra a /admin/ primero.'; exit; }
require_once __DIR__ . '/../api/config.php';
$pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4', (string)cfg('db_user'), (string)cfg('db_pass'));
$tables = ['products', 'product_cats', 'orders', 'order_items', 'newsletter', 'mensajes', 'webhook_log'];
$sql = "-- Santillana backup " . date('Y-m-d H:i:s') . "\n";
foreach ($tables as $t) {
  try {
    $c = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
    $sql .= ($c['Create Table'] ?? '') . ";\n";
    foreach ($pdo->query("SELECT * FROM `$t`") as $r) {
      $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($r));
      $sql .= "INSERT INTO `$t` VALUES(" . implode(',', $vals) . ");\n";
    }
  } catch (Throwable $e) { $sql .= "-- tabla $t no existe aún\n"; }
}
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="santillana-' . date('Ymd-His') . '.sql"');
echo $sql;
