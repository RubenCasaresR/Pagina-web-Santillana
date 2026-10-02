<?php
// tools/subscribers.php — descarga CSV de newsletter (requiere sesión admin).
declare(strict_types=1);
session_start();
if (empty($_SESSION['sant_admin'])) { http_response_code(403); echo 'Acceso denegado. Entra a /admin/ primero.'; exit; }
require_once __DIR__ . '/../api/config.php';
$pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4', (string)cfg('db_user'), (string)cfg('db_pass'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="suscriptores.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['email', 'fecha']);
foreach ($pdo->query('SELECT email,created_at FROM newsletter ORDER BY id') as $r) fputcsv($out, [$r['email'], $r['created_at']]);
