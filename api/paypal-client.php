<?php
// GET /api/paypal-client.php → {env, client_id} (solo la clave PÚBLICA, el secret jamás sale del servidor).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
echo json_encode(['env' => cfg('paypal_env'), 'client_id' => (string)cfg('paypal_client_id')], JSON_UNESCAPED_UNICODE);
