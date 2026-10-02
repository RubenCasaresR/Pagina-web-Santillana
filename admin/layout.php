<?php
// admin/layout.php — armazón compartido (sidebar + estilos). $adm_page: 'index'|'productos'.
declare(strict_types=1);
function adm_head(string $title): void {
  echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . ' — Admin</title><link rel="stylesheet" href="admin.css?v=3"></head><body>';
}
function adm_side(string $page, string $active): void {
  $p = $page === 'index' ? '' : 'index.php';
  $A = fn($k) => $k === $active ? ' class="act"' : '';
  echo '<div class="adm"><aside class="adm-side"><div class="adm-brand"><img src="../assets/logo-santillana.png" alt=""><div>Santillana<small>Panel de administración</small></div></div>'
    . '<nav class="adm-nav">'
    . '<a href="index.php"' . $A('panel') . '><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>Panel</a>'
    . '<a href="productos.php"' . $A('productos') . '><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/></svg>Productos</a>'
    . '<a href="' . $p . '#pedidos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/></svg>Pedidos</a>'
    . '<a href="' . $p . '#mensajes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Mensajes</a>'
    . '<a href="' . $p . '#news"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v12H4z"/><path d="M22 6l-10 7L2 6"/></svg>Newsletter</a>'
    . '<a href="' . $p . '#auditoria"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>Auditoría</a>'
    . '<a href="' . $p . '#tools"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z"/></svg>Herramientas</a>'
    . '</nav><div class="out"><a href="index.php?salir=1">Cerrar sesión</a></div></aside><main class="adm-main">';
}
function adm_foot(): void {
  echo '<script>document.querySelectorAll(\'.adm-nav a[href^="#"]\').forEach(a=>a.addEventListener(\'click\',e=>{const t=document.querySelector(a.getAttribute(\'href\'));if(!t)return;e.preventDefault();t.scrollIntoView({behavior:\'smooth\',block:\'start\'});t.style.transition=\'box-shadow .3s\';t.style.boxShadow=\'0 0 0 3px rgba(216,64,50,.35)\';setTimeout(()=>t.style.boxShadow=\'\',1200);history.replaceState(null,\'\',a.getAttribute(\'href\'));}));</script>';
  echo '</main></div></body></html>';
}
function adm_login(string $err = ''): void {
  echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin — Santillana</title><link rel="stylesheet" href="admin.css?v=3"></head><body>'
    . '<div class="adm-login"><div class="card" style="padding:30px 28px"><img src="../assets/logo-santillana.png" alt="Santillana"><h1 style="margin:12px 0 4px">Admin Santillana</h1><p class="mut">Acceso restringido</p>'
    . ($err !== '' ? '<p class="adm-err">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</p>' : '')
    . '<form method="post" class="frm" style="text-align:left"><label>Correo<input name="u" type="email" required autocomplete="username"></label><label>Contraseña<input name="p" type="password" required autocomplete="current-password"></label><button class="btn btn-primary" style="width:100%;padding:12px">Entrar</button></form>'
    . '</div></div></body></html>';
  exit;
}
function duracion(?int $s): string {
  if ($s === null) return '—';
  if ($s < 60) return $s . ' s';
  if ($s < 3600) return floor($s / 60) . ' min';
  return floor($s / 3600) . ' h ' . floor(($s % 3600) / 60) . ' min';
}
