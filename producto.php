<?php
// producto.php — plantilla ÚNICA de ficha. Fuentes: DB (api) con fallback a data/productos.json.
// Rutas: /producto.php?id=X · /producto.php?key=slug-id · pretty /producto/slug-id (vía .htaccess).
declare(strict_types=1);
require_once __DIR__ . '/api/config.php';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
// base absoluta del sitio (funciona en /, /bazar-e.mx/, etc.)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
if ($base === '//') $base = '/';
$base = str_replace(' ', '%20', $base);

function db_or_null() {
  try {
    return new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
      (string)cfg('db_user'), (string)cfg('db_pass'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { return null; }
}
function row_from_db(PDO $pdo, string $id): ?array {
  $st = $pdo->prepare('SELECT p.*, GROUP_CONCAT(c.cat) cats FROM products p LEFT JOIN product_cats c ON c.product_id=p.id WHERE p.id=? GROUP BY p.id');
  $st->execute([$id]);
  $r = $st->fetch();
  if (!$r) return null;
  $r['categorias'] = $r['cats'] ? explode(',', $r['cats']) : [];
  $r['precio'] = (int)$r['precio'] / 100;
  $r['precio_lista'] = (int)$r['precio_lista'] / 100;
  $r['stock'] = max(0, (int)$r['stock'] - (int)$r['reservado']);
  foreach (['disponible', 'destacado', 'en_oferta'] as $k) $r[$k] = (bool)$r[$k];
  return $r;
}
function row_from_json(string $id): ?array {
  $f = __DIR__ . '/data/productos.json';
  if (!is_file($f)) return null;
  $all = json_decode(file_get_contents($f), true) ?: [];
  foreach ($all as $p) {
    if ((string)$p['id'] === $id) {
      $p['stock'] = $p['stock'] ?? 10;
      return $p;
    }
  }
  return null;
}
function related(PDO $pdo = null, array $p): array {
  $cat = $p['cat_primary'] ?? '';
  if ($pdo) {
    try {
      $st = $pdo->prepare("SELECT id,nombre,sku,slug,cat_primary,precio,img,img_large,img_webp FROM products WHERE cat_primary=? AND id<>? AND disponible=1 ORDER BY destacado DESC, id DESC LIMIT 4");
      $st->execute([$cat, $p['id']]);
      $rows = $st->fetchAll();
      foreach ($rows as &$rw) $rw['precio'] = (int)$rw['precio'] / 100;
      return $rows;
    } catch (Throwable $e) { /* fallback json */ }
  }
  $all = json_decode(file_get_contents(__DIR__ . '/data/productos.json'), true) ?: [];
  $rel = array_values(array_filter($all, fn($x) => ($x['cat_primary'] ?? '') === $cat && (string)$x['id'] !== (string)$p['id']));
  return array_slice($rel, 0, 4);
}

// ---- resolver id ----
$id = '';
$key = trim((string)($_GET['key'] ?? ''));
$qid = trim((string)($_GET['id'] ?? ''));
if ($qid !== '') $id = preg_replace('/\D+/', '', $qid);
elseif (preg_match('/^p-(\d+)$/', $key, $m)) $id = $m[1];
elseif (preg_match('/-(\d{10,})$/', $key, $m)) $id = $m[1];

$pdo = db_or_null();
$p = $id !== '' ? ($pdo ? row_from_db($pdo, $id) : null) : null;
if (!$p) $p = $id !== '' ? row_from_json($id) : null;
if (!$p || empty($p['disponible'])) {
  http_response_code(404);
  $p = null;
}
if ($p) {
  $canon = $base . 'producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id']);
  // slug incorrecto, ruta no-canónica o ficha vieja -> 301 al canónico
  $want = $p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'];
  if ($key !== '' && ($key !== $want || isset($_GET['legacy']))) {
    header('Location: ' . str_replace(' ', '%20', $canon), true, 301);
    exit;
  }
  $rel = related($pdo, $p);
  $img = $p['img_large'] ?: $p['img'];
  $imgW = ($p['img_large_webp'] ?? '') ?: ($p['img_webp'] ?? '');
  $fmt = fn($n) => '$' . number_format((float)$n, 2);
  $isOffer = !empty($p['en_oferta']) || in_array('Ofertas', $p['categorias'] ?? [], true);
}
$CATS = ['Celulares', 'Computación', 'Televisiones', 'Videojuegos', 'Electrónica', 'Tablets', 'Hogar', 'Herramientas', 'Ofertas', 'Novedades'];
?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($p): ?>
<title><?= $h($p['nombre']) ?> — Santillana</title>
<meta name="description" content="<?= $h($p['nombre']) ?> por <?= $h($fmt($p['precio'])) ?>. SKU <?= $h($p['sku']) ?>.">
<link rel="canonical" href="<?= $h(cfg('site_url') . '/producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'])) ?>">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":<?= json_encode($p['nombre'], JSON_UNESCAPED_UNICODE) ?>,"sku":<?= json_encode($p['sku']) ?>,"brand":{"@type":"Brand","name":<?= json_encode($p['marca'] ?? '', JSON_UNESCAPED_UNICODE) ?>},"offers":{"@type":"Offer","priceCurrency":"MXN","price":<?= json_encode((string)$p['precio']) ?>,"availability":"<?= (!empty($p['disponible']) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock') ?>","url":<?= json_encode(cfg('site_url') . '/producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'])) ?>}}</script>
<?php else: ?>
<title>Producto no encontrado — Santillana</title><meta name="robots" content="noindex">
<?php endif; ?>
<link rel="preload" href="<?= $h($base) ?>estilos.css?v=20261001-4" as="style"><link rel="stylesheet" href="<?= $h($base) ?>estilos.css?v=20261001-4">
<link rel="icon" href="<?= $h($base) ?>assets/logo-santillana.png" type="image/png"></head><body>
<header class="topbar"><div class="wrap topbar-in">
<a class="logo" href="<?= $h($base) ?>index.html"><img class="logo-img" src="<?= $h($base) ?>assets/logo-santillana.png" alt="Santillana" width="466" height="296" fetchpriority="high" decoding="async"></a>
<form class="search" action="<?= $h($base) ?>index.html" method="get" role="search"><input id="q" name="q" type="search" placeholder="Buscar iPhone, Samsung, laptop, pantalla…" autocomplete="off"><button type="submit">Buscar</button></form>
<nav class="top-actions"><a class="cart-btn acct-btn" data-account-btn data-drawer-bound="1" href="<?= $h($base) ?>cuenta.html" aria-label="Entrar o crear cuenta"><svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <span data-account-label>Entrar</span></a><a class="cart-btn" href="<?= $h($base) ?>carrito.html" aria-label="Abrir carrito"><svg class="cart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg> Carrito <span class="cart-count" data-cart-count>0</span></a></nav>
</div><nav class="catnav"><div class="wrap catnav-in"><a href="<?= $h($base) ?>index.html">Todo</a><?php foreach ($CATS as $c): ?><a href="<?= $h($base) ?>index.html?cat=<?= urlencode($c) ?>#catalogo"><?= $h($c) ?></a><?php endforeach; ?></div></nav></header>
<main class="wrap">
<?php if (!$p): ?>
<div class="empty" style="margin:30px 0"><b>Producto no encontrado</b><span>Es posible que se haya agotado o la dirección cambió.</span><br><a class="btn add" href="<?= $h($base) ?>index.html" style="max-width:240px">Ver catálogo</a></div>
<?php else: ?>
<p class="crumb"><a href="<?= $h($base) ?>index.html">Inicio</a> / <a href="<?= $h($base) ?>index.html?cat=<?= urlencode($p['cat_primary']) ?>#catalogo"><?= $h($p['cat_primary']) ?></a> / <?= $h($p['nombre']) ?></p>
<div class="detail"><div><picture><?php if ($imgW): ?><source srcset="<?= $h($base . $imgW) ?>" type="image/webp"><?php endif; ?><img fetchpriority="high" decoding="async" src="<?= $h($base . $img) ?>" alt="<?= $h($p['nombre']) ?>" data-fb="0" data-compact="<?= $h($base . ($p['img'] ?: $img)) ?>" data-logo="<?= $h($base) ?>assets/logo-santillana.png" onerror="var f=this.dataset.fb||'0';if(f==='0'){this.dataset.fb='1';this.src=this.dataset.compact;}else{this.onerror=null;this.src=this.dataset.logo;}"></picture></div>
<div><span class="badge"><?= $h($p['cat_primary']) ?></span><h1 style="font-size:24px"><?= $h($p['nombre']) ?></h1>
<p class="meta">SKU <?= $h($p['sku']) ?> · ID <?= $h($p['id']) ?></p>
<p class="precio" style="font-size:28px"><?= $h($fmt($p['precio'])) ?></p>
<p class="nota" id="ficha-disp">Consultando disponibilidad...</p>
<p><?= $h($p['descripcion_corta'] ?: $p['nombre']) ?></p>
<p class="meta">Marca: <?= $h($p['marca'] ?? '') ?> · Garantía: 12 meses · Devolución: 30 días</p>
<p class="nota">Envío gratis desde $999 · 12 meses de garantía · Devolución 30 días</p>
<div class="qty"><button onclick="c(-1)" aria-label="Disminuir cantidad">−</button><input id="qty" value="1" inputmode="numeric"><button onclick="c(1)" aria-label="Aumentar cantidad">+</button></div>
<div class="card-f"><button class="btn add" data-add="<?= $h($p['id']) ?>" id="addbtn">Agregar al carrito</button>
<a class="btn ver" href="<?= $h($base) ?>carrito.html">Ver carrito</a></div>
<p class="meta">Categorías: <?= $h(implode(', ', $p['categorias'] ?? [])) ?></p></div></div>
<h2>Relacionados</h2><div class="grid"><?php foreach ($rel as $r):
  $ru = $base . 'producto/' . (($r['slug'] ?? '') ? $r['slug'] . '-' . $r['id'] : 'p-' . $r['id']);
  $rimg = $r['img'] ?? ''; $rimgW = $r['img_webp'] ?? ($r['img_large_webp'] ?? '');
  if ($rimgW === '' && $rimg !== '') $rimgW = preg_replace('/\.(png|jpe?g)$/i', '.webp', $rimg);
?><article class="card"><a class="card-img" href="<?= $h($ru) ?>" aria-label="Ver <?= $h($r['nombre']) ?>" tabindex="-1"><picture><?php if ($rimgW): ?><source srcset="<?= $h($base . $rimgW) ?>" type="image/webp"><?php endif; ?><img loading="lazy" decoding="async" width="400" height="180" src="<?= $h($base . $rimg) ?>" alt="" onerror="this.onerror=null;this.src='<?= $h($base) ?>assets/logo-santillana.png'"></picture></a><div class="card-b"><span class="badge"><?= $h($r['cat_primary']) ?></span><h3><a href="<?= $h($ru) ?>"><?= $h($r['nombre']) ?></a></h3><span class="precio"><?= $h($fmt($r['precio'])) ?></span><div class="card-f"><a class="btn ver" href="<?= $h($ru) ?>">Ver</a><button class="btn add" data-add="<?= $h($r['id']) ?>">Agregar</button></div></div></article><?php endforeach; ?></div>
<?php endif; ?>
</main>
<footer class="footer"><div class="wrap foot-grid">
<div class="f-brand"><a class="logo f-logo" href="<?= $h($base) ?>index.html"><img class="logo-img" src="<?= $h($base) ?>assets/logo-santillana.png" alt="Santillana"></a><p>Tienda mexicana. Precios en MXN, garantía real de 12 meses.</p></div>
<div><h4>Tienda</h4><ul><li><a href="<?= $h($base) ?>index.html?cat=Celulares#catalogo">Celulares</a></li><li><a href="<?= $h($base) ?>index.html?cat=Computación#catalogo">Computación</a></li><li><a href="<?= $h($base) ?>index.html?cat=Televisiones#catalogo">Televisiones</a></li><li><a href="<?= $h($base) ?>index.html?cat=Videojuegos#catalogo">Videojuegos</a></li><li><a href="<?= $h($base) ?>index.html?cat=Ofertas#catalogo">Ofertas</a></li></ul></div>
<div><h4>Ayuda</h4><ul><li><a href="<?= $h($base) ?>carrito.html">Mi carrito</a></li><li><a href="<?= $h($base) ?>rastreo.html">Rastrear pedido</a></li><li><a href="<?= $h($base) ?>contacto.html">Contacto</a></li><li><a href="<?= $h($base) ?>privacidad.html">Aviso de Privacidad</a></li><li><a href="<?= $h($base) ?>terminos.html">Términos</a></li></ul></div>
<div><h4>Contacto</h4><ul class="f-contact-list"><li><a href="mailto:ventas@santillana-mx.com">ventas@santillana-mx.com</a></li><li><a href="tel:+523310642354">33 1064 2354</a></li><li>Lun–Sáb · 9:00–18:00</li><li>Calle Colón #180, Zona Centro, Ciudad de México, MX</li></ul><p class="pay-label">Métodos de pago</p><div class="pay"><span class="pay-logo" title="PayPal">PayPal</span><span class="pay-logo" title="SPEI">SPEI</span></div></div>
</div><div class="foot-base"><div class="wrap base-in"><span>© 2026 Santillana · Precios en MXN</span><nav><a href="<?= $h($base) ?>privacidad.html">Privacidad</a><a href="<?= $h($base) ?>terminos.html">Términos</a></nav></div></div></footer>
<a class="wa-float" href="https://wa.me/523310642354?text=Hola%20Santillana%2C%20necesito%20ayuda" target="_blank" rel="noopener" aria-label="WhatsApp Santillana"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg></a>
<script src="<?= $h($base) ?>app.js?v=20261001-4" defer></script>
<script>function c(d){const i=document.getElementById('qty');i.value=Math.max(1,parseInt(i.value||'1')+d)}
document.getElementById('addbtn').dataset.qty='1';
document.getElementById('qty').addEventListener('input',e=>document.getElementById('addbtn').dataset.qty=e.target.value);</script>
</body></html>
