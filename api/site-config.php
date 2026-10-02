<?php
// GET /api/site-config.php → {recaptcha_sitekey} (pública, solo sitekey).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$__clabe = preg_replace('/\D+/', '', (string)cfg('spei_clabe'));
$__spei_ok = (bool)preg_match('/^[1-9]\d{17}$/', $__clabe) && !preg_match('/^0+$/', $__clabe) && trim((string)cfg('spei_banco')) !== '' && trim((string)cfg('spei_banco')) !== 'Banco Ejemplo';
echo json_encode([
  'recaptcha_sitekey' => (string)cfg('recaptcha_sitekey'),
  'spei' => ['banco' => (string)cfg('spei_banco'), 'clabe' => (string)cfg('spei_clabe'), 'titular' => (string)cfg('spei_titular')],
  'spei_configured' => $__spei_ok,
  'contact_email' => (string)cfg('contact_email'),
  'whatsapp' => (string)cfg('whatsapp'),
], JSON_UNESCAPED_UNICODE);
