<?php
// POST /api/webhook-paypal.php — eventos PayPal. Verifica firma, idempotente, registra y actualiza.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/paypal.php';
try {
  $raw = file_get_contents('php://input') ?: '';
  $ev = json_decode($raw, true) ?? [];
  $type = (string)($ev['event_type'] ?? '');
  $pdo = db();
  $pdo->prepare('INSERT INTO webhook_log(source,event_type,payload) VALUES(?,?,?)')
    ->execute(['paypal', $type, mb_substr($raw, 0, 60000)]);
  // Verificación de firma PayPal (headers de transmisión)
  $h = function ($k) {
    $kk = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
    return $_SERVER[$kk] ?? '';
  };
  $verify = null;
  try {
    [$vhttp, $verify] = pp_call('POST', '/v1/notifications/verify-webhook-signature', [
      'transmission_id' => $h('paypal-transmission-id'),
      'transmission_time' => $h('paypal-transmission-time'),
      'cert_url' => $h('paypal-cert-url'),
      'auth_algo' => $h('paypal-auth-algo'),
      'transmission_sig' => $h('paypal-transmission-sig'),
      'webhook_id' => (string)cfg('paypal_webhook_id'),
      'webhook_event' => $ev,
    ]);
  } catch (Throwable $e) { $verify = null; }
  if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
    jexit(200, ['ok' => false, 'motivo' => 'firma-no-verificada-registrada']);
  }
  $res = $ev['resource'] ?? [];
  $capId = (string)($res['id'] ?? '');
  $orderId = (string)($res['supplementary_data']['related_ids']['order_id'] ?? ($res['custom_id'] ?? ''));
  if ($type === 'PAYMENT.CAPTURE.COMPLETED' && $capId !== '') {
    $st = $pdo->prepare("SELECT * FROM orders WHERE paypal_capture_id=? OR paypal_order_id=?");
    $st->execute([$capId, $orderId]);
    if ($o = $st->fetch()) {
      if ($o['status'] === 'PENDING') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE orders SET status='PAID', paypal_capture_id=?, paid_at=NOW() WHERE id=?")->execute([$capId, $o['id']]);
        $it = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id=?');
        $it->execute([$o['id']]);
        $up = $pdo->prepare('UPDATE products SET stock=stock-?, reservado=GREATEST(reservado-?,0) WHERE id=?');
        foreach ($it->fetchAll() as $l) $up->execute([$l['qty'], $l['qty'], $l['product_id']]);
        $pdo->commit();
      }
    }
  } elseif (in_array($type, ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.REFUNDED'], true) && $capId !== '') {
    $pdo->prepare("UPDATE orders SET status=? WHERE paypal_capture_id=? AND status='PAID'")
      ->execute([$type === 'PAYMENT.CAPTURE.REFUNDED' ? 'REFUNDED' : 'CANCELLED', $capId]);
  }
  jexit(200, ['ok' => true]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(200, ['ok' => false]);
}
