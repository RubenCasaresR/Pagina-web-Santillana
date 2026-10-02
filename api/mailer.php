<?php
// api/mailer.php — envío SMTP con PHPMailer. Si no hay credenciales, no rompe el checkout.
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function smtp_configurado(): bool {
  return trim((string)cfg('smtp_host')) !== '' && trim((string)cfg('smtp_user')) !== '';
}

function mailer_base(): ?PHPMailer {
  if (!smtp_configurado()) return null;
  $autoload = __DIR__ . '/../vendor/autoload.php';
  if (!is_file($autoload)) return null;
  require_once $autoload;
  $m = new PHPMailer(true);
  $m->isSMTP();
  $m->Host = (string)cfg('smtp_host');
  $m->Port = (int)cfg('smtp_port') ?: 587;
  $m->SMTPAuth = true;
  $m->Username = (string)cfg('smtp_user');
  $m->Password = (string)cfg('smtp_pass');
  $sec = strtolower(trim((string)cfg('smtp_secure')));
  if ($sec === 'ssl') $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  elseif ($sec === 'tls') $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  else $m->SMTPSecure = false;
  $from = trim((string)cfg('smtp_from')) !== '' ? (string)cfg('smtp_from') : (string)cfg('smtp_user');
  $m->setFrom($from, (string)cfg('smtp_from_name') ?: 'Santillana');
  $m->CharSet = 'UTF-8';
  $m->isHTML(true);
  $m->Timeout = 15;
  return $m;
}

function h_mail($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Arma el HTML del pedido con sus líneas. $o = fila orders, $items = [[nombre,sku,qty,unit_price]] */
function pedido_html(array $o, array $items, string $extra = ''): string {
  $rows = '';
  foreach ($items as $l) {
    $imp = (int)$l['qty'] * (int)$l['unit_price'];
    $rows .= '<tr><td>' . h_mail($l['nombre'] ?? '(producto)') . '<br><small>' . h_mail($l['sku'] ?? '') . '</small></td>'
      . '<td align="center">' . (int)$l['qty'] . '</td>'
      . '<td align="right">$' . money_fmt((int)$l['unit_price']) . '</td>'
      . '<td align="right">$' . money_fmt($imp) . '</td></tr>';
  }
  $metodo = (($o['payment_method'] ?? 'paypal') === 'spei') ? 'Transferencia SPEI' : 'PayPal';
  $fecha = '';
  if (!empty($o['created_at'])) {
    try { $fecha = (new DateTime((string)$o['created_at'], new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i'); }
    catch (Throwable $e) { $fecha = (string)$o['created_at']; }
  } else { $fecha = date('d/m/Y H:i'); }
  $site = rtrim((string)cfg('site_url'), '/');
  $logo = $site . '/assets/logo-santillana.png';
  $track = $site . '/rastreo.html';
  return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#0F172A">'
    . '<div style="text-align:center;padding:12px 0"><img src="' . h_mail($logo) . '" alt="Santillana" width="140" style="max-width:140px;height:auto"></div>'
    . '<h2 style="text-align:center">¡Gracias por tu compra, ' . h_mail($o['nombre']) . '!</h2>'
    . '<p style="text-align:center">Tu pedido <strong>' . h_mail($o['folio']) . '</strong> está confirmado.</p>'
    . '<p>Fecha: <strong>' . h_mail($fecha) . '</strong><br>Método de pago: <strong>' . h_mail($metodo) . '</strong></p>'
    . '<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#E2E8F0">'
    . '<tr style="background:#F1F5F9"><th align="left">Producto</th><th>Cant</th><th>Unitario</th><th>Importe</th></tr>'
    . $rows . '</table>'
    . '<p>Subtotal: <strong>$' . money_fmt((int)$o['subtotal']) . '</strong><br>'
    . 'Total: <strong>$' . money_fmt((int)$o['total']) . ' MXN</strong></p>'
    . '<p>Dirección de entrega: ' . h_mail(trim($o['direccion'] . ' ' . ($o['depto'] ?? '') . ', ' . $o['ciudad'] . ', ' . ($o['estado'] ?? '') . ' C.P. ' . $o['cp'], ', ')) . '</p>'
    . $extra
    . '<p style="text-align:center"><a href="' . h_mail($track) . '" style="display:inline-block;background:#0F172A;color:#fff;text-decoration:none;padding:10px 22px;border-radius:8px">Rastrear mi pedido</a></p>'
    . '<p><small>Para rastrear usa tu folio <strong>' . h_mail($o['folio']) . '</strong> y este correo.</small></p>'
    . '<p>Te avisaremos cuando se envíe. Dudas: ' . h_mail((string)cfg('contact_email')) . ' · WhatsApp ' . h_mail((string)cfg('whatsapp')) . '</p>'
    . '</div>';
}

/** Envía confirmación PayPal. Devuelve true/false (nunca lanza). */
function enviar_confirmacion_paypal(array $o, ?string $captureId): bool {
  try {
    $m = mailer_base();
    if (!$m) { error_log('smtp-no-configurado paypal ' . ($o['folio'] ?? '')); return false; }
    $pdo = db();
    $it = $pdo->prepare('SELECT oi.qty, oi.unit_price, p.nombre, p.sku FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');
    $it->execute([$o['id']]);
    $items = $it->fetchAll();
    $m->addAddress($o['email'], $o['nombre'] ?? '');
    $m->Subject = 'Santillana — pedido ' . $o['folio'] . ' confirmado';
    $m->Body = pedido_html($o, $items, '<p>Referencia PayPal: <strong>' . h_mail($captureId ?? '—') . '</strong></p>');
    $m->AltBody = 'Gracias por tu compra. Folio ' . $o['folio'] . '. Total $' . money_fmt((int)$o['total']) . ' MXN. Metodo PayPal. Ref ' . ($captureId ?? '—') . '. Rastrea en ' . rtrim((string)cfg('site_url'), '/') . '/rastreo.html con tu folio y este correo.';
    return $m->send();
  } catch (Throwable $e) {
    error_log('smtp-paypal-fallo ' . ($o['folio'] ?? '') . ': ' . $e->getMessage());
    return false;
  }
}

/** Envía instrucciones SPEI (pedido pendiente de transferencia). Devuelve true/false. */
function enviar_instrucciones_spei(array $o, string $ref): bool {
  try {
    $m = mailer_base();
    if (!$m) { error_log('smtp-no-configurado spei ' . ($o['folio'] ?? '')); return false; }
    $pdo = db();
    $it = $pdo->prepare('SELECT oi.qty, oi.unit_price, p.nombre, p.sku FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');
    $it->execute([$o['id']]);
    $items = $it->fetchAll();
    $m->addAddress($o['email'], $o['nombre'] ?? '');
    $m->Subject = 'Santillana — pedido ' . $o['folio'] . ' pendiente de transferencia';
    $extra = '<div style="background:#FFFBEB;border:1px solid #FDE68A;padding:12px;border-radius:8px">'
      . '<p style="margin:0">Transfiere <strong>$' . money_fmt((int)$o['total']) . ' MXN</strong> a:<br>'
      . 'Banco: <strong>' . h_mail((string)cfg('spei_banco')) . '</strong><br>'
      . 'CLABE: <strong>' . h_mail((string)cfg('spei_clabe')) . '</strong><br>'
      . 'Titular: <strong>' . h_mail((string)cfg('spei_titular')) . '</strong><br>'
      . 'Referencia: <strong>' . h_mail($ref) . '</strong></p></div>';
    $m->Body = pedido_html($o, $items, $extra);
    $m->AltBody = 'Folio ' . $o['folio'] . '. Transfiere $' . money_fmt((int)$o['total']) . ' MXN a ' . cfg('spei_banco') . ' CLABE ' . cfg('spei_clabe') . ' ref ' . $ref . '. Rastrea en ' . rtrim((string)cfg('site_url'), '/') . '/rastreo.html.';
    return $m->send();
  } catch (Throwable $e) {
    error_log('smtp-spei-fallo ' . ($o['folio'] ?? '') . ': ' . $e->getMessage());
    return false;
  }
}
