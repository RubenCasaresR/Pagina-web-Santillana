<?php
// Santillana — configuración central. Lee de entorno (.env / variables Neubox) con defaults XAMPP local.
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

const DOTENV_KEYS = ['SANT_DB_HOST','SANT_DB_NAME','SANT_DB_USER','SANT_DB_PASS',
  'SANT_PAYPAL_ENV','SANT_PAYPAL_CLIENT_ID','SANT_PAYPAL_SECRET','SANT_PAYPAL_WEBHOOK_ID',
  'SANT_ADMIN_EMAIL','SANT_ADMIN_PASS_HASH','SANT_RECAPTCHA_SECRET','SANT_RECAPTCHA_SITEKEY',
  'SANT_SPEI_BANCO','SANT_SPEI_CLABE','SANT_SPEI_TITULAR','SANT_CONTACT_EMAIL','SANT_WHATSAPP',
  'SANT_SITE_URL','SANT_SHIP_FREE_FROM','SANT_SHIP_COST',
  'SANT_SMTP_HOST','SANT_SMTP_PORT','SANT_SMTP_USER','SANT_SMTP_PASS','SANT_SMTP_FROM','SANT_SMTP_FROM_NAME','SANT_SMTP_SECURE'];

/** Lee entorno a memoria del request (sin putenv: no es seguro entre hilos en Apache).
 *  Prioridad: $_SERVER/$_ENV del OS (Neubox) > .env.local > .env > defaults. */
function load_dotenv(): array {
  $env = [];
  foreach (DOTENV_KEYS as $k) {
    if (isset($_SERVER[$k]) && $_SERVER[$k] !== '') $env[$k] = (string)$_SERVER[$k];
    elseif (isset($_ENV[$k]) && $_ENV[$k] !== '') $env[$k] = (string)$_ENV[$k];
  }
  $bases = [dirname($_SERVER['DOCUMENT_ROOT'] ?? ''), __DIR__ . '/..', __DIR__];
  foreach ($bases as $base) {
    foreach (['/.env.local', '/.env'] as $suf) {
      $f = $base . $suf;
      if (!is_file($f) || !is_readable($f)) continue;
      foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $ln, 2), 2, '');
        $k = trim($k);
        if ($k === '' || isset($env[$k])) continue;
        $env[$k] = trim($v, " \t\"'");
      }
      break 2; // primer archivo encontrado manda (.env.local antes que .env)
    }
  }
  return $env;
}

function envv(string $k, string $d = ''): string {
  static $env = null;
  if ($env === null) $env = load_dotenv();
  return ($env[$k] ?? '') !== '' ? (string)$env[$k] : $d;
}

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
      'site_url' => rtrim(envv('SANT_SITE_URL', 'https://santillana-mx.com'), '/'),
      // SPEI: vacío = transferencia desactivada (checkout la oculta solo). Llenar en producción vía env.
      'spei_banco' => envv('SANT_SPEI_BANCO', ''),
      'spei_clabe' => envv('SANT_SPEI_CLABE', ''),
      'spei_titular' => envv('SANT_SPEI_TITULAR', ''),
      'contact_email' => envv('SANT_CONTACT_EMAIL', 'ventas@santillana-mx.com'),
      'whatsapp' => envv('SANT_WHATSAPP', '523310642354'),
      // SMTP (PHPMailer). Vacío = correo desactivado (se registra en log, el pedido igual se confirma).
      'smtp_host' => envv('SANT_SMTP_HOST', ''),
      'smtp_port' => (int)envv('SANT_SMTP_PORT', '587'),
      'smtp_user' => envv('SANT_SMTP_USER', ''),
      'smtp_pass' => envv('SANT_SMTP_PASS', ''),
      'smtp_from' => envv('SANT_SMTP_FROM', ''),
      'smtp_from_name' => envv('SANT_SMTP_FROM_NAME', 'Santillana'),
      'smtp_secure' => envv('SANT_SMTP_SECURE', 'tls'), // tls | ssl | '' (puerto 587=tls, 465=ssl)
    ];
  }
  return $c[$k] ?? null;
}
