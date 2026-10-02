<?php
// admin/index.php — panel: login, KPIs, historial de pedidos (filtros + detalle), stock, mensajes, newsletter, auditoría.
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/auth-lib.php';
require_once __DIR__ . '/layout.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function pill(string $st): string {
  $m = ['PAID' => ['pill-paid', 'Pagado'], 'PENDING' => ['pill-pending', 'Pendiente'],
    'PENDING_TRANSFER' => ['pill-transfer', 'Falta transferencia'], 'CANCELLED' => ['pill-cancel', 'Cancelado'],
    'REFUNDED' => ['pill-gray', 'Reembolsado'], 'EXPIRED' => ['pill-gray', 'Expirado']];
  [$c, $t] = $m[$st] ?? ['pill-gray', $st];
  return '<span class="pill ' . $c . '">' . h($t) . '</span>';
}
function db(): PDO {
  return new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
    (string)cfg('db_user'), (string)cfg('db_pass'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
if (trim((string)cfg('admin_pass_hash')) === '') adm_login('Admin sin configurar: define SANT_ADMIN_EMAIL y SANT_ADMIN_PASS_HASH en .env (genera el hash con password_hash).');
if (!empty($_POST['u']) || !empty($_POST['p'])) {
  $ok = hash_equals((string)cfg('admin_email'), strtolower(trim((string)($_POST['u'] ?? ''))))
    && cfg('admin_pass_hash') !== '' && password_verify((string)$_POST['p'], (string)cfg('admin_pass_hash'));
  if ($ok) {
    $_SESSION['sant_admin'] = 1;
    $_SESSION['audit_id'] = audit_login(db(), 'admin', strtolower(trim((string)$_POST['u'])));
    header('Location: index.php'); exit;
  }
  adm_login('Credenciales inválidas.');
}
if (empty($_SESSION['sant_admin'])) adm_login();
if (isset($_GET['salir'])) {
  try { audit_logout(db()); } catch (Throwable $e) {}
  unset($_SESSION['sant_admin']); // se conserva la sesión de cliente si la hay
  header('Location: ../index.html'); exit;
}
$pdo = db();
if (isset($_POST['stock_id'])) {
  $pdo->prepare('UPDATE products SET stock=?, disponible=? WHERE id=?')
    ->execute([max(0, (int)$_POST['stock']), !empty($_POST['disp']) ? 1 : 0, (string)$_POST['stock_id']]);
  header('Location: index.php'); exit;
}
if (isset($_POST['ord_status'], $_POST['ord_id'])) {
  $pdo->prepare("UPDATE orders SET status=? WHERE id=? AND status IN ('PENDING','PENDING_TRANSFER','PAID')")
    ->execute([in_array($_POST['ord_status'], ['PAID', 'PENDING_TRANSFER', 'CANCELLED', 'REFUNDED', 'EXPIRED'], true) ? $_POST['ord_status'] : 'CANCELLED', (int)$_POST['ord_id']]);
  header('Location: index.php'); exit;
}
// KPIs
$kpi = [
  'hoy' => $pdo->query("SELECT COUNT(*) c, IFNULL(SUM(IF(status='PAID',total,0)),0) t FROM orders WHERE DATE(created_at)=CURDATE()")->fetch(),
  'pend' => $pdo->query("SELECT COUNT(*) c FROM orders WHERE status IN ('PENDING','PENDING_TRANSFER')")->fetch()['c'],
  'low' => $pdo->query("SELECT COUNT(*) c FROM products WHERE stock<5")->fetch()['c'],
  'subs' => 0,
];
try { $kpi['subs'] = $pdo->query("SELECT COUNT(*) c FROM newsletter")->fetch()['c']; } catch (Throwable $e) {}
// Filtros historial
$fest = trim((string)($_GET['estado'] ?? ''));
$fq = trim((string)($_GET['q'] ?? ''));
$where = []; $args = [];
$ESTADOS = ['PENDING', 'PENDING_TRANSFER', 'PAID', 'CANCELLED', 'REFUNDED', 'EXPIRED'];
if (in_array($fest, $ESTADOS, true)) { $where[] = 'status=?'; $args[] = $fest; }
if ($fq !== '') { $where[] = '(folio LIKE ? OR email LIKE ? OR nombre LIKE ?)'; array_push($args, "%$fq%", "%$fq%", "%$fq%"); }
$w = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$st = $pdo->prepare("SELECT * FROM orders $w ORDER BY id DESC LIMIT 100");
$st->execute($args);
$ords = $st->fetchAll();
$low = $pdo->query("SELECT id,sku,nombre,stock FROM products WHERE stock<5 ORDER BY stock LIMIT 50")->fetchAll();
// Detalle pedido
$det = null; $detItems = [];
if (!empty($_GET['pedido'])) {
  $st = $pdo->prepare('SELECT * FROM orders WHERE id=?');
  $st->execute([(int)$_GET['pedido']]);
  $det = $st->fetch() ?: null;
  if ($det) {
    $it = $pdo->prepare('SELECT oi.qty, oi.unit_price, p.nombre, p.sku FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');
    $it->execute([$det['id']]);
    $detItems = $it->fetchAll();
  }
}
adm_head('Admin — Santillana');
adm_side('index', 'panel');
echo '<h1>Panel general</h1><p class="adm-sub">Resumen de tu tienda · ' . date('d/m/Y') . '</p>'
  . '<div class="kpis"><div class="kpi"><b>' . (int)$kpi['hoy']['c'] . '</b><span>Pedidos hoy</span></div>'
  . '<div class="kpi"><b>$' . number_format($kpi['hoy']['t'] / 100, 2) . '</b><span>Cobrado hoy (MXN)</span></div>'
  . '<div class="kpi"><b>' . (int)$kpi['pend'] . '</b><span>Por atender</span></div>'
  . '<div class="kpi"><b>' . (int)$kpi['low'] . '</b><span>Stock bajo</span></div>'
  . '<div class="kpi"><b>' . (int)$kpi['subs'] . '</b><span>Suscriptores</span></div></div>';
if ($det) {
  echo '<section class="card"><div class="card-h"><h2>Pedido ' . h($det['folio']) . ' ' . pill($det['status']) . '</h2><a class="btn btn-ghost btn-sm" href="index.php#pedidos">Volver</a></div>'
    . '<div class="tblwrap"><table class="tbl">'
    . '<tr><th>Producto</th><th>SKU</th><th class="num">Cant</th><th class="num">Unitario</th><th class="num">Importe</th></tr>';
  foreach ($detItems as $l) {
    echo '<tr><td>' . h($l['nombre'] ?? '(eliminado)') . '</td><td>' . h($l['sku'] ?? '') . '</td><td class="num">' . (int)$l['qty'] . '</td><td class="num">$' . number_format($l['unit_price'] / 100, 2) . '</td><td class="num">$' . number_format($l['qty'] * $l['unit_price'] / 100, 2) . '</td></tr>';
  }
  echo '</table></div><div style="padding:14px 18px" class="mut">'
    . 'Cliente: <b>' . h(trim($det['nombre'] . ' ' . ($det['apellidos'] ?? ''))) . '</b> · ' . h($det['email']) . ' · ' . h($det['telefono']) . '<br>'
    . 'Envío: ' . h(trim($det['direccion'] . ' ' . ($det['depto'] ?? '') . ', ' . $det['ciudad'] . ', ' . ($det['estado'] ?? '') . ', ' . ($det['pais'] ?? '') . ' C.P. ' . $det['cp'], ', ')) . '<br>'
    . 'Subtotal $' . number_format($det['subtotal'] / 100, 2) . ' · Envío ' . ($det['envio'] == 0 ? 'gratis' : '$' . number_format($det['envio'] / 100, 2)) . ' · <b>Total $' . number_format($det['total'] / 100, 2) . '</b><br>'
    . 'Pago: ' . h($det['payment_method'] ?? 'paypal') . (!empty($det['spei_ref']) ? ' · Ref. ' . h($det['spei_ref']) : '') . (!empty($det['paypal_capture_id']) ? ' · Capture ' . h($det['paypal_capture_id']) : '') . '<br>'
    . 'Creado: ' . h($det['created_at']) . ($det['paid_at'] ? ' · Pagado: ' . h($det['paid_at']) : '') . '</div></section>';
}
echo '<section class="card" id="pedidos"><div class="card-h"><h2>Historial de pedidos</h2>'
  . '<form method="get" class="toolbar"><select name="estado" style="padding:8px;border:1px solid var(--borde);border-radius:8px"><option value="">Todos los estados</option>'
  . implode('', array_map(fn($s) => '<option value="' . $s . '"' . ($s === $fest ? ' selected' : '') . '>' . $s . '</option>', $ESTADOS))
  . '</select><input name="q" value="' . h($fq) . '" placeholder="Folio, email o nombre" style="padding:8px 12px;border:1px solid var(--borde);border-radius:8px"><button class="btn btn-ghost btn-sm">Filtrar</button> <a class="btn btn-ghost btn-sm" href="index.php#pedidos">Limpiar</a></form></div>'
  . '<div class="tblwrap"><table class="tbl"><tr><th>Folio</th><th>Cliente</th><th class="num">Total</th><th>Estado</th><th>Pago</th><th>Fecha</th><th></th></tr>';
foreach ($ords as $o) {
  $pay = ($o['payment_method'] ?? 'paypal') === 'spei' ? 'SPEI' . (!empty($o['spei_ref']) ? ' · ' . h($o['spei_ref']) : '') : 'PayPal';
  echo '<tr><td><b>' . h($o['folio']) . '</b></td><td>' . h($o['email']) . '</td><td class="num">$' . number_format($o['total'] / 100, 2) . '</td><td>' . pill($o['status']) . '</td><td>' . $pay . '</td><td>' . h($o['created_at']) . '</td>'
    . '<td style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="?pedido=' . (int)$o['id'] . '#pedidos">Ver</a> '
    . '<form method="post" class="inline"><input type="hidden" name="ord_id" value="' . (int)$o['id'] . '"><select name="ord_status" style="padding:6px;border:1px solid var(--borde);border-radius:8px"><option>PAID</option><option>PENDING_TRANSFER</option><option>CANCELLED</option><option>REFUNDED</option><option>EXPIRED</option></select> <button class="btn btn-sm">OK</button></form></td></tr>';
}
echo '</table></div></section>'
  . '<section class="card"><div class="card-h"><h2>Stock bajo (&lt;5)</h2><a class="btn btn-ghost btn-sm" href="productos.php">Gestionar productos</a></div><div class="tblwrap"><table class="tbl"><tr><th>ID</th><th>SKU</th><th>Nombre</th><th class="num">Stock</th><th></th></tr>';
foreach ($low as $p) {
  echo '<tr><td>' . h($p['id']) . '</td><td>' . h($p['sku']) . '</td><td>' . h(mb_substr($p['nombre'], 0, 60)) . '</td><td class="num">' . (int)$p['stock'] . '</td>'
    . '<td><form method="post" class="inline"><input type="hidden" name="stock_id" value="' . h($p['id']) . '"><input name="stock" type="number" min="0" value="' . (int)$p['stock'] . '" style="width:70px;padding:6px;border:1px solid var(--borde);border-radius:8px"><label class="mut"><input type="checkbox" name="disp" checked> disp</label> <button class="btn btn-sm">Guardar</button></form></td></tr>';
}
echo '</table></div></section>'
  . '<section class="card" id="mensajes"><div class="card-h"><h2>Mensajes de contacto</h2><span class="mut">Últimos 20</span></div><div class="tblwrap"><table class="tbl"><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Mensaje</th></tr>';
try {
  $msgs = $pdo->query("SELECT * FROM mensajes ORDER BY id DESC LIMIT 20")->fetchAll();
  foreach ($msgs as $m) echo '<tr><td>' . h($m['created_at']) . '</td><td>' . h($m['nombre']) . '</td><td>' . h($m['email']) . '</td><td>' . h(mb_substr($m['mensaje'], 0, 200)) . '</td></tr>';
} catch (Throwable $e) { echo '<tr><td colspan="4" class="mut">Sin mensajes aún.</td></tr>'; }
echo '</table></div></section>'
  . '<section class="card" id="news"><div class="card-h"><h2>Newsletter</h2><a class="btn btn-ghost btn-sm" href="../tools/subscribers.php">Descargar CSV</a></div><p style="padding:0 18px 16px" class="mut">' . (int)$kpi['subs'] . ' suscriptores.</p></section>'
  . '<section class="card" id="auditoria"><div class="card-h"><h2>Auditoría de accesos</h2><span class="mut">Últimos 50 · quién entró y cuánto duró</span></div><div class="tblwrap"><table class="tbl"><tr><th>Tipo</th><th>Correo</th><th>Entrada</th><th>Salida</th><th>Duración</th><th>IP</th></tr>';
try {
  $au = $pdo->query("SELECT * FROM accesos ORDER BY id DESC LIMIT 50")->fetchAll();
  foreach ($au as $a) {
    echo '<tr><td>' . ($a['tipo'] === 'admin' ? '<span class="pill pill-transfer">Admin</span>' : '<span class="pill pill-gray">Cliente</span>') . '</td><td>' . h($a['email']) . '</td><td>' . h($a['entrada']) . '</td><td>' . h($a['salida'] ?? '—') . '</td><td>' . h(duracion($a['segundos'] !== null ? (int)$a['segundos'] : null)) . '</td><td>' . h($a['ip']) . '</td></tr>';
  }
} catch (Throwable $e) { echo '<tr><td colspan="6" class="mut">Sin registros aún.</td></tr>'; }
echo '</table></div></section>'
  . '<section class="card" id="tools"><div class="card-h"><h2>Herramientas</h2></div><p style="padding:0 18px 16px" class="mut">Importar catálogo: <code>php tools/import.php</code> o <a href="../tools/import.php?confirm=1">clic aquí</a> · <a href="../tools/backup.php">Descargar respaldo .sql</a></p></section>';
echo '<section class="card"><div class="card-h"><h2>Webhooks PayPal</h2><span class="mut">Últimos 10</span></div><div class="tblwrap"><table class="tbl"><tr><th>#</th><th>Evento</th><th>Fecha</th></tr>';
try {
  $wh = $pdo->query("SELECT id,event_type,created_at FROM webhook_log ORDER BY id DESC LIMIT 10")->fetchAll();
  foreach ($wh as $w) echo '<tr><td>' . (int)$w['id'] . '</td><td>' . h($w['event_type']) . '</td><td>' . h($w['created_at']) . '</td></tr>';
} catch (Throwable $e) { /* sin tabla */ }
echo '</table></div></section>';
adm_foot();
