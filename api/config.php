<?php
// Santillana — configuración central. Lee de entorno (.env / variables Neubox) con defaults XAMPP local.
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

function envv(string $k, string $d = ''): string {
  $v = getenv($k);
  return ($v === false || $v === '') ? $d : (string)$v;
}

/** Carga .env (KEY=valor) si existe. Orden: fuera de la raíz pública, raíz del proyecto, api/. */
function load_dotenv(): void {
  static $done = false;
  if ($done) return;
  $done = true;
  $cands = [dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/.env', __DIR__ . '/../.env', __DIR__ . '/.env'];
  foreach ($cands as $f) {
    if (!is_file($f) || !is_readable($f)) continue;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
      $ln = trim($ln);
      if ($ln === '' || $ln[0] === '#') continue;
      [$k, $v] = array_pad(explode('=', $ln, 2), 2, '');
      $k = trim($k);
      if ($k === '' || getenv($k) !== false) continue; // entorno OS manda
      putenv($k . '=' . trim($v, " \t\"'"));
    }
    break;
  }
}
load_dotenv();

const SANTILLANA = [
  'db_host' => null, 'db_name' => null, 'db_user' => null, 'db_pass' => null,
  'paypal_env' => null, 'paypal_client_id' => null, 'paypal_secret' => null,
  'admin_email' => null, 'admin_pass_hash' => null,
  'ship_free_from' => 99900, 'ship_cost' => 14900,
];

function cfg(string $k) {
  static $c = null;
  if ($c === null) {
    $c = [
      'db_host' => envv('SANT_DB_HOST', '127.0.0.1'),
      'db_name' => envv('SANT_DB_NAME', 'santillana'),
      'db_user' => envv('SANT_DB_USER', 'root'),
      'db_pass' => envv('SANT_DB_PASS', ''),
      'paypal_env' => envv('SANT_PAYPAL_ENV', 'sandbox'), // sandbox | live
      'paypal_client_id' => envv('SANT_PAYPAL_CLIENT_ID', ''),
      'paypal_secret' => envv('SANT_PAYPAL_SECRET', ''),
      'paypal_webhook_id' => envv('SANT_PAYPAL_WEBHOOK_ID', ''),
      'admin_email' => strtolower(envv('SANT_ADMIN_EMAIL', 'admin@santillana-mx.com')),
      'recaptcha_secret' => envv('SANT_RECAPTCHA_SECRET', ''),
      'recaptcha_sitekey' => envv('SANT_RECAPTCHA_SITEKEY', ''),
      // hash de 'cambia-esto': password_hash('cambia-esto', PASSWORD_DEFAULT). Cambiar en producción.
      'admin_pass_hash' => envv('SANT_ADMIN_PASS_HASH', ''),
      'ship_free_from' => (int)envv('SANT_SHIP_FREE_FROM', '99900'), // centavos
      'ship_cost' => (int)envv('SANT_SHIP_COST', '14900'),
      'site_url' => rtrim(envv('SANT_SITE_URL', 'https://santillana-mx.com/bazar-e.mx'), '/'),
      // SPEI (EDITAR con datos reales del banco; se muestran al elegir transferencia)
      'spei_banco' => envv('SANT_SPEI_BANCO', 'Banco Ejemplo'),
      'spei_clabe' => envv('SANT_SPEI_CLABE', '000000000000000000'),
      'spei_titular' => envv('SANT_SPEI_TITULAR', 'Santillana'),
      'contact_email' => envv('SANT_CONTACT_EMAIL', 'ventas@santillana-mx.com'),
      'whatsapp' => envv('SANT_WHATSAPP', '523310642354'),
    ];
  }
  return $c[$k] ?? null;
}
