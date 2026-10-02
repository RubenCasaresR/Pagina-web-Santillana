<?php
// PayPal REST helper (cURL). sandbox | live.
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function pp_base(): string {
  return cfg('paypal_env') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}
function pp_token(): string {
  $id = (string)cfg('paypal_client_id');
  $sec = (string)cfg('paypal_secret');
  if ($id === '' || $sec === '') throw new RuntimeException('paypal-sin-credenciales');
  $ch = curl_init(pp_base() . '/v1/oauth2/token');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => "$id:$sec",
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'grant_type=client_credentials', CURLOPT_TIMEOUT => 20]);
  $res = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $d = json_decode((string)$res, true);
  if ($http !== 200 || empty($d['access_token'])) throw new RuntimeException('paypal-auth');
  return $d['access_token'];
}
function pp_call(string $method, string $path, array|object|null $body = null): array {
  $tok = pp_token();
  $ch = curl_init(pp_base() . $path);
  $h = ['Content-Type: application/json', "Authorization: Bearer $tok", 'PayPal-Request-Id: ' . uuid4_safe()];
  $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 25];
  if ($body !== null) $opt[CURLOPT_POSTFIELDS] = json_encode($body);
  curl_setopt_array($ch, $opt);
  $res = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$http, json_decode((string)$res, true) ?? []];
}
function uuid4_safe(): string {
  $b = random_bytes(16);
  $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
  $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}
