/* Santillana — catálogo + carrito estilo drawer (Belocio) sin emojis, solo iconos SVG */
const fmt = n => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n);
/* Raíz del sitio robusta a subcarpetas: / , /bazar-e.mx/ , /tienda/ ... (antes fallaba con '../' relativo) */
const SITE_ROOT = (() => {
  try {
    const s = document.currentScript && document.currentScript.src
      ? new URL(document.currentScript.src, location.href).pathname : '';
    if (s) return s.slice(0, s.lastIndexOf('/') + 1);
  } catch { /* noop */ }
  const p = location.pathname;
  if (p.includes('/productos/')) return p.slice(0, p.indexOf('/productos/') + 1);
  if (p.endsWith('/index.html') || p.endsWith('/carrito.html')) return p.slice(0, p.lastIndexOf('/') + 1);
  return p.endsWith('/') ? p : p.slice(0, p.lastIndexOf('/') + 1);
})();
const BASE_PREFIX = SITE_ROOT;
const CATS_API = SITE_ROOT + 'api/categories.php';

const ICONS = {
  cart: '<svg class="cart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
  check: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
  lock: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  truck: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
  box: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>',
  search: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>',
  x: '<svg class="ic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
  minus: '<svg class="ic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>',
  plus: '<svg class="ic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  trash: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
  user: '<svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
};

/* ---------- Estado carrito ---------- */
const getCart = () => { try { return JSON.parse(localStorage.getItem('santillana_cart') || '{}'); } catch { return {}; } };
const setCart = c => { localStorage.setItem('santillana_cart', JSON.stringify(c)); paintCount(); renderDrawer(); const box = document.getElementById('cartbox'); if (box) renderCartPage(); scheduleCartSync(); };
const cartQty = id => (getCart()[id] || 0);
const cartCountTotal = () => Object.values(getCart()).reduce((a, b) => a + b, 0);

let ALL = [];
let BYID = {};

/* ¿Remota? http(s)://, //protocolo-relativo o data: se usa tal cual (Shopify CDN). */
function __isRemote(u) {
  const r = (u || '').split('?')[0].trim();
  return r.startsWith('http://') || r.startsWith('https://') || r.startsWith('//') || r.startsWith('data:');
}
/* Thumbs vía el propio CDN Shopify (?width=): 122KB -> ~10KB. Solo hosts Shopify
   (cdn.shopify.com, *.myshopify.com, /cdn/shop/). Si la variante fallara,
   onerror encadena a la original y luego al logo. */
function shopThumb(u, w) {
  w = w || 480;
  u = (u || '').trim();
  if (!u) return u;
  const host = u.split('?')[0];
  const isShop = host.indexOf('cdn.shopify.com') >= 0 || host.indexOf('/cdn/shop/') >= 0 || host.indexOf('myshopify.com') >= 0;
  if (!isShop) return u;
  return u + (u.indexOf('?') < 0 ? '?width=' : '&width=') + w;
}
function resolveImg(p) {
  if (!p) return SITE_ROOT + 'assets/logo-santillana.png';
  const full = (p.img || p.img_large || '').trim();
  if (!full.split('?')[0]) return SITE_ROOT + 'assets/logo-santillana.png';
  if (!__isRemote(full)) return SITE_ROOT + full.replace(/^\/+/, '');
  return shopThumb(full, 480);
}
/* Original sin recortar: eslabón intermedio del fallback (thumb -> original -> logo). */
function resolveOrig(p) {
  if (!p) return '';
  const full = (p.img || p.img_large || '').trim();
  if (!full.split('?')[0]) return '';
  return __isRemote(full) ? full : SITE_ROOT + full.replace(/^\/+/, '');
}
function prodUrl(p) {
  return SITE_ROOT + 'producto/' + (p.slug ? p.slug + '-' + p.id : 'p-' + p.id);
}
function resolveImgLarge(p) {
  const full = ((p && p.img_large) || '').trim();
  if (!full.split('?')[0]) return resolveImg(p);
  return __isRemote(full) ? full : SITE_ROOT + full.replace(/^\/+/, '');
}
function webpOf(p, kind) {
  const k = (kind === 'large' ? (p.img_large_webp || '') : (p.img_webp || '')).trim();
  if (!k.split('?')[0]) return '';
  return __isRemote(k) ? k : SITE_ROOT + k.replace(/^\/+/, '');
}
function hasPhoto(p) {
  return !!(((p && (p.img || p.img_large)) || '').split('?')[0].trim());
}
function picTag(p, kind, w, h, eager) {
  if (!hasPhoto(p)) {
    const alt = escHtml(p.nombre || 'Producto Santillana');
    return `<div class="sin-foto" role="img" aria-label="${alt} — foto pendiente"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg><span>Foto pendiente</span></div>`;
  }
  // Una sola descarga por tarjeta (~10KB): thumb 480 del CDN. Sin srcset fingido.
  const thumb = resolveImg(p), orig = resolveOrig(p);
  const wbp = shopThumb(webpOf(p, 'large') || webpOf(p, 'compact'), 480);
  const fb = (orig && orig !== thumb) ? ` data-large="${orig}"` : '';
  const alt = escHtml(p.nombre || 'Producto Santillana');
  // eager=true en carruseles horizontales: el lazy no dispara en overflow-x hasta interactuar.
  const ld = eager ? 'eager' : 'lazy';
  const fp = eager ? ' fetchpriority="high"' : '';
  return `<picture>${wbp ? `<source srcset="${wbp}" type="image/webp">` : ''}<img loading="${ld}"${fp} decoding="async" width="${w}" height="${h}" src="${thumb}"${fb} alt="${alt}" onerror="__imgFb(this)"></picture>`;
}
/* Fallback en cadena sin bucle ni reintento del mismo 404: compact -> large (si distinto) -> logo */
function imgOnError(el) {
  el.onerror = null;
  const fb = el.dataset.fb || '0';
  const alt = el.dataset.large;
  if (fb === '0' && alt && alt !== el.src) { el.dataset.fb = '1'; el.onerror = () => imgOnError(el); el.src = alt; }
  else { el.dataset.fb = '2'; el.onerror = null; el.src = SITE_ROOT + 'assets/logo-santillana.png'; }
}
window.__imgFb = imgOnError;

function paintCount() {
  const n = cartCountTotal();
  document.querySelectorAll('[data-cart-count]').forEach(e => {
    e.textContent = n;
    e.classList.toggle('hidden', n === 0);
    e.classList.toggle('has-items', n > 0);
  });
}

/* ---------- Shell drawer (auto-inyectado para index + fichas) ---------- */
function ensureCartShell() {
  // 1. Convertir boton header existente a abridor de drawer
  document.querySelectorAll('.cart-btn').forEach(btn => {
    if (btn.dataset.drawerBound || btn.hasAttribute('data-account-btn')) return;
    btn.dataset.drawerBound = '1';
    if (!btn.querySelector('.cart-icon')) {
      btn.insertAdjacentHTML('afterbegin', ICONS.cart + ' ');
    }
    if (!btn.querySelector('[data-cart-count]')) {
      btn.insertAdjacentHTML('beforeend', ' <span class="cart-count" data-cart-count>0</span>');
    }
    btn.addEventListener('click', e => { e.preventDefault(); openCart(); });
  });

  if (document.getElementById('cart-drawer')) {
    if (!document.getElementById('added-popup')) {
      const topActions = document.querySelector('.top-actions');
      const pop = document.createElement('div');
      pop.className = 'added-popup'; pop.id = 'added-popup';
      pop.innerHTML = `<div class="added-header"><img id="added-image" alt="Producto agregado"><div class="added-info"><h4 id="added-title"></h4><p id="added-price"></p></div></div><div class="added-actions"><button class="added-btn-outline" id="added-continue" type="button">Seguir comprando</button><button class="added-btn-primary" id="added-view-cart" type="button">Ver carrito</button></div>`;
      if (topActions) { topActions.style.position = 'relative'; topActions.appendChild(pop); pop.style.position = 'absolute'; }
      else { pop.style.position = 'fixed'; pop.style.top = '76px'; pop.style.right = '16px'; document.body.appendChild(pop); }
    }
    bindCartShell(); paintCount(); return;
  }

  const shell = document.createElement('div');
  shell.id = 'santillana-cart-shell';
  shell.innerHTML = `
  <div class="added-popup" id="added-popup" role="status" aria-live="polite">
    <div class="added-header">
      <img id="added-image" alt="Producto agregado">
      <div class="added-info"><h4 id="added-title"></h4><p id="added-price"></p></div>
    </div>
    <div class="added-actions">
      <button class="added-btn-outline" id="added-continue" type="button">Seguir comprando</button>
      <button class="added-btn-primary" id="added-view-cart" type="button">Ver carrito</button>
    </div>
  </div>
  <div class="cart-overlay" id="cart-overlay"></div>
  <aside class="cart-drawer" id="cart-drawer" aria-label="Carrito de compras">
    <div class="cart-header">
      <h2>${ICONS.cart} Tu carrito</h2>
      <button class="cart-close" id="cart-close" aria-label="Cerrar carrito" type="button">${ICONS.x}</button>
    </div>
    <div class="cart-items" id="cart-items"></div>
    <div class="cart-footer">
      <div class="cart-total"><span>Total</span><strong id="cart-total">$0.00</strong></div>
      <div class="checkout-payment-methods">
        <p class="payment-methods-label">Paga con PayPal o SPEI</p>
        <a class="paypal-btn" id="checkout-btn" href="${BASE_PREFIX}checkout.html" style="text-decoration:none">Finalizar compra</a>
        <p class="paypal-secure-note">${ICONS.lock} Compra protegida</p>
      </div>
    </div>
  </aside>
  `;
  document.body.appendChild(shell);

  // Posicionar popup junto al header si existe .top-actions
  const topActions = document.querySelector('.top-actions');
  const popup = document.getElementById('added-popup');
  if (topActions && popup) {
    topActions.style.position = 'relative';
    topActions.appendChild(popup);
    popup.style.position = 'absolute';
  } else if (popup) {
    popup.style.position = 'fixed';
    popup.style.top = '76px';
    popup.style.right = '16px';
  }
  bindCartShell();
}

let addedTimer = null;
function showAddedPopup(p) {
  const pop = document.getElementById('added-popup');
  if (!pop) return;
  document.getElementById('added-image').src = resolveImg(p);
  document.getElementById('added-title').textContent = p.nombre;
  document.getElementById('added-price').textContent = fmt(p.precio);
  pop.classList.add('show');
  if (addedTimer) clearTimeout(addedTimer);
  addedTimer = setTimeout(hideAddedPopup, 6000);
}
function hideAddedPopup() { document.getElementById('added-popup')?.classList.remove('show'); }
function openCart() {
  renderDrawer();
  document.getElementById('cart-drawer')?.classList.add('open');
  document.getElementById('cart-overlay')?.classList.add('show');
  document.body.style.overflow = 'hidden';
  hideAddedPopup();
}
function closeCart() {
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('cart-overlay')?.classList.remove('show');
  document.body.style.overflow = '';
}
window.openCart = openCart;
window.closeCart = closeCart;

function cartSubtotal() {
  const c = getCart();
  return Object.entries(c).reduce((s, [id, q]) => s + (BYID[id] ? BYID[id].precio * q : 0), 0);
}
function shippingCost(sub) { return 0; } // Envío siempre gratis

function renderDrawer() {
  const itemsEl = document.getElementById('cart-items');
  const totalEl = document.getElementById('cart-total');
  if (!itemsEl) return;
  const cart = getCart();
  const ids = Object.keys(cart).filter(id => BYID[id]);
  // limpiar huerfanos
  const clean = {};
  ids.forEach(id => clean[id] = cart[id]);
  if (ids.length !== Object.keys(cart).length) localStorage.setItem('santillana_cart', JSON.stringify(clean));

  if (!ids.length) {
    itemsEl.innerHTML = '<div class="cart-empty"><p>Tu carrito esta vacio.</p><button class="btn add" type="button" onclick="closeCart()">Seguir comprando</button></div>';
    if (totalEl) totalEl.textContent = fmt(0);
    return;
  }
  itemsEl.innerHTML = ids.map(id => {
    const p = BYID[id], q = clean[id];
    return `<div class="cart-item">
      <img loading="lazy" decoding="async" width="70" height="70" src="${resolveImg(p)}" alt="${escHtml(p.nombre)}" onerror="this.onerror=null;this.src='${BASE_PREFIX}assets/logo-santillana.png'">
      <div class="cart-item-info">
        <p class="cart-item-title">${escHtml(p.nombre)}</p>
        <p class="cart-item-price">${fmt(p.precio)}</p>
        <div class="cart-item-actions">
          <button class="qty-btn" data-dec="${id}" aria-label="Disminuir cantidad">${ICONS.minus}</button>
          <span class="cart-item-qty">${q}</span>
          <button class="qty-btn" data-inc="${id}" aria-label="Aumentar cantidad">${ICONS.plus}</button>
          <button class="remove-btn" data-del="${id}">${ICONS.trash} Eliminar</button>
        </div>
      </div>
    </div>`;
  }).join('');
  const sub = cartSubtotal();
  if (totalEl) totalEl.textContent = fmt(sub);
}
window.changeQty = (id, d) => { const c = getCart(); c[id] = (c[id] || 0) + d; if (c[id] <= 0) delete c[id]; setCart(c); };
window.removeFromCart = id => { const c = getCart(); delete c[id]; setCart(c); };

function renderCartPage() {
  const box = document.getElementById('cartbox');
  if (!box) return;
  const cart = getCart();
  const ids = Object.keys(cart).filter(id => BYID[id]);
  if (!ids.length) {
    box.innerHTML = '<div class="empty"><b>Tu carrito esta vacio.</b><span>Explora el catálogo y agrega productos.</span><br><a class="btn add" href="' + SITE_ROOT + 'index.html">Ver productos</a></div>';
    return;
  }
  const sub = cartSubtotal(), total = sub;
  box.innerHTML = `<div class="cart-lay"><div class="cart-page-list">` + ids.map(id => {
    const p = BYID[id], q = cart[id];
    return `<div class="cart-page-item"><a href="${prodUrl(p)}" tabindex="-1"><img loading="lazy" decoding="async" width="84" height="84" src="${resolveImg(p)}" alt="" onerror="this.onerror=null;this.src='${SITE_ROOT}assets/logo-santillana.png'"></a><div style="flex:1;min-width:0"><p class="cart-item-title"><a href="${prodUrl(p)}">${p.nombre}</a></p><span class="nota">SKU ${p.sku}</span><p class="cart-item-price">${fmt(p.precio * q)}</p><div class="cart-item-actions"><button class="qty-btn" data-dec="${id}" aria-label="Disminuir">−</button><span class="cart-item-qty">${q}</span><button class="qty-btn" data-inc="${id}" aria-label="Aumentar">+</button><button class="remove-btn" data-del="${id}">Eliminar</button></div></div></div>`;
  }).join('') + `</div><div class="cart-page-summary"><div class="order-row"><span>Subtotal:</span><strong>${fmt(sub)}</strong></div><div class="cart-total"><span>Total:</span><strong>${fmt(total)}</strong></div><a class="paypal-btn" href="${SITE_ROOT}checkout.html" style="text-decoration:none;display:block;text-align:center">Finalizar compra</a><p class="paypal-secure-note">${ICONS.lock} Paga con PayPal o SPEI</p><p class="nota" style="margin-top:8px">Pago 100% seguro.</p></div></div>`;
  if (!box.dataset.bound) {
    box.dataset.bound = '1';
    box.addEventListener('click', e => {
      const inc = e.target.closest('[data-inc]'), dec = e.target.closest('[data-dec]'), del = e.target.closest('[data-del]');
      if (inc) window.changeQty(inc.dataset.inc, 1);
      if (dec) window.changeQty(dec.dataset.dec, -1);
      if (del) window.removeFromCart(del.dataset.del);
    });
  }
}
function addToCart(id, qty = 1) {
  id = String(id);
  qty = parseInt(qty || '1', 10) || 1;
  const c = getCart();
  c[id] = (c[id] || 0) + qty;
  setCart(c);
  const p = BYID[id];
  if (p) showAddedPopup(p);
  const b = document.querySelector(`[data-add="${id}"]`);
  if (b && !b.dataset.busy) {
    b.dataset.busy = '1';
    if (b.classList.contains('card-add')) {
      b.classList.add('added');
      setTimeout(() => { b.classList.remove('added'); delete b.dataset.busy; }, 1100);
    } else {
      const t = b.innerHTML;
      b.innerHTML = 'Agregado';
      b.classList.add('added');
      setTimeout(() => { b.innerHTML = t; b.classList.remove('added'); delete b.dataset.busy; }, 1100);
    }
  }
}
window.addToCart = addToCart;

/* ---------- Cuentas de cliente ---------- */
let ME = null;
let IS_ADMIN = false;
async function authPost(data) {
  const r = await fetch(SITE_ROOT + 'api/auth.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
  });
  let j = {};
  try { j = await r.json(); } catch { /* noop */ }
  if (!r.ok) throw new Error(j.error || ('http-' + r.status));
  return j;
}
async function loadMe() {
  try {
    const r = await fetch(SITE_ROOT + 'api/auth.php?action=me');
    const j = await r.json();
    ME = j.logged ? j.customer : null;
    IS_ADMIN = !!j.is_admin;
  } catch { ME = null; IS_ADMIN = false; }
  refreshAuthUI();
  return ME;
}
function refreshAuthUI() {
  document.querySelectorAll('[data-account-btn]').forEach(b => {
    const lbl = b.querySelector('[data-account-label]');
    const tag = ME ? String(ME.nombre).split(' ')[0] : (IS_ADMIN ? 'Admin' : 'Entrar');
    if (lbl) lbl.textContent = tag;
    b.setAttribute('aria-label', ME ? ('Mi cuenta de ' + ME.nombre) : (IS_ADMIN ? 'Sesión de administrador' : 'Entrar o crear cuenta'));
  });
}
function openAdminMenu() {
  if (!document.getElementById('admin-menu')) {
    document.body.insertAdjacentHTML('beforeend', `<div id="admin-menu" class="paypal-data-overlay" aria-hidden="true"><div class="paypal-data-card">
      <button class="order-success-close" id="admin-menu-close" aria-label="Cerrar" type="button">×</button>
      <h3>Sesión de administrador</h3>
      <p class="paypal-data-subtitle">Estás viendo la tienda como admin.</p>
      <a class="btn add" href="${SITE_ROOT}admin/" style="width:100%;text-decoration:none;text-align:center;box-sizing:border-box">Volver al panel admin</a>
      <button class="added-btn-outline" id="admin-menu-logout" type="button" style="width:100%;margin-top:8px">Cerrar sesión</button>
    </div></div>`);
    document.getElementById('admin-menu-close')?.addEventListener('click', closeAdminMenu);
    document.getElementById('admin-menu')?.addEventListener('click', e => { if (e.target.id === 'admin-menu') closeAdminMenu(); });
    document.getElementById('admin-menu-logout')?.addEventListener('click', () => { location.href = SITE_ROOT + 'admin/index.php?salir=1'; });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAdminMenu(); });
  }
  document.getElementById('admin-menu')?.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeAdminMenu() {
  document.getElementById('admin-menu')?.classList.remove('show');
  if (!document.getElementById('cart-drawer')?.classList.contains('open')) document.body.style.overflow = '';
}
function accountModalHTML() {
  const lock = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
  return `<div id="account-modal" class="paypal-data-overlay" aria-hidden="true"><div class="paypal-data-card acct-card">
    <button class="order-success-close" id="account-close" aria-label="Cerrar" type="button">×</button>
    <div id="acct-guest">
      <div class="acct-brand"><img src="${SITE_ROOT}assets/logo-santillana.png" alt="Santillana" width="120" height="88" loading="lazy" decoding="async"><h3>Bienvenido</h3>
      <p class="paypal-data-subtitle">Entra para ver tu carrito en cualquier dispositivo y tus pedidos.</p></div>
      <div class="acct-tabs">
        <button class="acct-tab act" id="tab-login" type="button">Entrar</button>
        <button class="acct-tab" id="tab-register" type="button">Crear cuenta</button>
      </div>
      <form id="acct-login-form" class="acct-pane act" novalidate>
        <div class="form-group"><label for="al-email">Correo o usuario</label><input id="al-email" type="text" inputmode="email" required autocomplete="username" placeholder="tu@correo.com"></div>
        <div class="form-group"><label for="al-pass">Contraseña</label><input id="al-pass" type="password" required minlength="8" autocomplete="current-password" placeholder="••••••••"></div>
        <p id="al-error" class="pp-input-error"></p>
        <button type="submit" class="btn add" style="width:100%">Entrar</button>
        <p class="acct-secure">${lock} Conexión protegida</p>
      </form>
      <form id="acct-register-form" class="acct-pane" novalidate>
        <div class="form-group"><label for="ar-nombre">Nombre completo</label><input id="ar-nombre" type="text" required minlength="3" autocomplete="name" placeholder="Tu nombre"></div>
        <div class="form-group"><label for="ar-email">Correo</label><input id="ar-email" type="email" required autocomplete="email" placeholder="tu@correo.com"></div>
        <div class="form-group"><label for="ar-tel">WhatsApp (10 dígitos, opcional)</label><input id="ar-tel" type="tel" inputmode="numeric" maxlength="10" autocomplete="tel" placeholder="3310642354"></div>
        <div class="form-group"><label for="ar-pass">Contraseña (mínimo 8)</label><input id="ar-pass" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••"></div>
        <p id="ar-error" class="pp-input-error"></p>
        <button type="submit" class="btn add" style="width:100%">Crear cuenta</button>
        <p class="acct-secure">${lock} Al registrarte aceptas el Aviso de Privacidad</p>
      </form>
    </div>
    <div id="acct-me" class="acct-me" style="display:none">
      <div class="acct-avatar" id="acct-avatar">S</div>
      <h3>Hola, <span id="acct-name"></span></h3>
      <p class="paypal-data-subtitle" id="acct-email"></p>
      <a class="btn add" id="acct-orders" href="${SITE_ROOT}cuenta.html">Mis pedidos y mi carrito</a>
      <button class="added-btn-outline" id="acct-logout" type="button">Cerrar sesión</button>
    </div>
  </div></div>`;
}
function ensureAccountUI() {
  document.querySelectorAll('.top-actions').forEach(ta => {
    if (!ta.querySelector('[data-account-btn]')) {
      const a = document.createElement('a');
      a.href = SITE_ROOT + 'cuenta.html';
      a.className = 'cart-btn acct-btn';
      a.setAttribute('data-account-btn', '');
      a.setAttribute('aria-label', 'Entrar o crear cuenta');
      a.innerHTML = ICONS.user + ' <span data-account-label>Entrar</span>';
      ta.prepend(a);
    }
  });
  // enlazar (también los botones fijos del HTML) una sola vez
  document.querySelectorAll('[data-account-btn]').forEach(a => {
    if (a.dataset.acctBound) return;
    a.dataset.acctBound = '1';
    a.addEventListener('click', e => {
      if (IS_ADMIN) { e.preventDefault(); openAdminMenu(); return; }
      if (ME) return; // ir a cuenta.html
      e.preventDefault();
      openAccount();
    });
  });
  if (!document.getElementById('account-modal')) {
    document.body.insertAdjacentHTML('beforeend', accountModalHTML());
    bindAccountModal();
  }
  refreshAuthUI();
}
function openAccount() {
  refreshAccountModal();
  document.getElementById('account-modal')?.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeAccount() {
  document.getElementById('account-modal')?.classList.remove('show');
  if (!document.getElementById('cart-drawer')?.classList.contains('open')) document.body.style.overflow = '';
}
function refreshAccountModal() {
  const g = document.getElementById('acct-guest');
  const m = document.getElementById('acct-me');
  if (ME) {
    if (g) g.style.display = 'none';
    if (m) m.style.display = '';
    const n = document.getElementById('acct-name');
    if (n) n.textContent = String(ME.nombre).split(' ')[0];
    const av = document.getElementById('acct-avatar');
    if (av) av.textContent = String(ME.nombre || 'S').trim().charAt(0).toUpperCase() || 'S';
    const e = document.getElementById('acct-email');
    if (e) e.textContent = ME.email;
  } else {
    if (g) g.style.display = '';
    if (m) m.style.display = 'none';
  }
  refreshAuthUI();
}
function acctError(id, msg) {
  const el = document.getElementById(id);
  if (el) { el.textContent = msg; el.classList.add('show'); }
}
function bindAccountModal() {
  document.getElementById('account-close')?.addEventListener('click', closeAccount);
  const tl = document.getElementById('tab-login'), tr = document.getElementById('tab-register');
  const fl = document.getElementById('acct-login-form'), fr = document.getElementById('acct-register-form');
  tl?.addEventListener('click', () => {
    tl.classList.add('act'); tr?.classList.remove('act');
    fl?.classList.add('act'); fr?.classList.remove('act');
  });
  tr?.addEventListener('click', () => {
    tr.classList.add('act'); tl?.classList.remove('act');
    fr?.classList.add('act'); fl?.classList.remove('act');
  });
  fl?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!fl.checkValidity()) { fl.reportValidity(); return; }
    const btn = fl.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Entrando...'; }
    try {
      const j = await authPost({
        action: 'login',
        email: document.getElementById('al-email').value.trim(),
        pass: document.getElementById('al-pass').value,
        items: cartItemsPayload()
      });
      ME = { nombre: j.nombre, email: j.email };
      if (j.is_admin) { location.href = SITE_ROOT + 'admin/'; return; }
      if (j.cart && j.cart.length) {
        const obj = {};
        j.cart.forEach(it => obj[String(it.id)] = it.qty);
        setCart(obj);
      }
      await loadMe();
      closeAccount();
      toast('Hola de nuevo, ' + String(ME.nombre).split(' ')[0]);
    } catch (err) {
      document.getElementById('al-pass').value = '';
      acctError('al-error', String(err.message) === 'credenciales' ? 'Correo o contraseña incorrectos.' : 'No se pudo entrar.');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Entrar'; }
    }
  });
  fr?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!fr.checkValidity()) { fr.reportValidity(); return; }
    const tel = document.getElementById('ar-tel').value.replace(/\D+/g, '');
    if (tel && !/^\d{10}$/.test(tel)) { acctError('ar-error', 'El WhatsApp debe tener 10 dígitos.'); return; }
    const btn = fr.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Creando...'; }
    try {
      const j = await authPost({
        action: 'register',
        nombre: document.getElementById('ar-nombre').value.trim(),
        email: document.getElementById('ar-email').value.trim(),
        telefono: tel,
        pass: document.getElementById('ar-pass').value,
        items: cartItemsPayload()
      });
      ME = { nombre: j.nombre, email: j.email };
      await loadMe();
      scheduleCartSync();
      closeAccount();
      toast('Cuenta creada. Bienvenido, ' + String(ME.nombre).split(' ')[0]);
    } catch (err) {
      const m = String(err.message);
      acctError('ar-error', m === 'email-registrado' ? 'Ese correo ya tiene cuenta. Entra.' : m === 'pass-min-8' ? 'La contraseña debe tener mínimo 8 caracteres.' : 'No se pudo crear la cuenta.');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Crear cuenta'; }
    }
  });
  document.getElementById('acct-logout')?.addEventListener('click', async () => {
    try { await authPost({ action: 'logout' }); } catch { /* noop */ }
    ME = null;
    refreshAccountModal();
    closeAccount();
    toast('Sesión cerrada. Tu carrito queda en este dispositivo.');
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAccount();
  });
}
let __syncT = null;
function scheduleCartSync() {
  if (!ME) return;
  clearTimeout(__syncT);
  __syncT = setTimeout(() => {
    fetch(SITE_ROOT + 'api/cart-sync.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: cartItemsPayload() })
    }).catch(() => { /* offline: se reintenta al siguiente cambio */ });
  }, 900);
}
window.openAccount = openAccount;

/* ---------- Checkout PayPal ---------- */
function bindCartShell() {
  document.getElementById('cart-close')?.addEventListener('click', closeCart);
  document.getElementById('cart-overlay')?.addEventListener('click', closeCart);
  document.getElementById('added-continue')?.addEventListener('click', hideAddedPopup);
  document.getElementById('added-view-cart')?.addEventListener('click', () => { hideAddedPopup(); openCart(); });
  document.getElementById('cart-items')?.addEventListener('click', e => {
    const inc = e.target.closest('[data-inc]'), dec = e.target.closest('[data-dec]'), del = e.target.closest('[data-del]');
    if (inc) window.changeQty(inc.dataset.inc, 1);
    if (dec) window.changeQty(dec.dataset.dec, -1);
    if (del) window.removeFromCart(del.dataset.del);
  });
  document.addEventListener('click', e => {
    const pop = document.getElementById('added-popup');
    if (pop && pop.classList.contains('show') && !pop.contains(e.target) && !e.target.closest('.cart-btn')) hideAddedPopup();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeCart(); } });
}

function cartItemsPayload() {
  return Object.entries(getCart()).filter(([id]) => BYID[id]).map(([id, qty]) => ({ id, qty }));
}
async function apiPost(path, data) {
  const r = await fetch(SITE_ROOT + 'api/' + path, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
  });
  let j = {};
  try { j = await r.json(); } catch { /* noop */ }
  if (!r.ok) throw new Error(j.error || ('http-' + r.status));
  return j;
}
async function handleCheckout() {
  if (cartCountTotal() === 0) { toast('Tu carrito esta vacio. Agrega productos antes de pagar.'); return; }
  // Compra de invitados: ir directo al checkout (el pedido se rastrea con folio + correo).
  location.href = SITE_ROOT + 'checkout.html';
}
// Lleva todos los "Finalizar compra" (drawer + página carrito) al checkout
document.addEventListener('click', e => {
  const a = e.target && e.target.closest ? e.target.closest('a[href$="checkout.html"]') : null;
  if (!a) return;
  e.preventDefault();
  handleCheckout();
});
function toast(msg) {
  let t = document.getElementById('sant-toast');
  if (!t) { t = document.createElement('div'); t.id = 'sant-toast'; t.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#20292e;color:#fff;padding:10px 16px;border-radius:10px;z-index:4000;font-size:14px'; document.body.appendChild(t); }
  t.textContent = msg; t.style.display = 'block';
  clearTimeout(t._h); t._h = setTimeout(() => t.style.display = 'none', 2600);
}

/* ---------- Catalogo (index) con paginacion ---------- */
let ALL_CATS = [];
let FILTERED = [];
let PAGE = 1;
let PERPAGE = 20;
let CUR = { q: '', c: 'Todo', o: 'rel', marca: '', precio: '', disp: false, oferta: false };
const norm = s => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
/* Caché de catálogo 2h (stale-while-revalidate). v5: revalida precios/stock, no solo conteo. */
const CAT_CACHE_KEY = 'santillana_catalog_v5';
const CAT_CACHE_TTL = 2 * 3600 * 1000;
function readCatCache() {
  try {
    const j = JSON.parse(localStorage.getItem(CAT_CACHE_KEY) || 'null');
    if (j && Array.isArray(j.items) && j.items.length && (Date.now() - (j.t || 0)) < CAT_CACHE_TTL) return { ps: j.items, cs: j.cats || [], cached: true };
  } catch { /* caché inválida: red */ }
  return null;
}
function writeCatCache(items, cats) {
  try { localStorage.setItem(CAT_CACHE_KEY, JSON.stringify({ t: Date.now(), items, cats })); } catch { /* cuota llena: seguir sin caché */ }
}
async function fetchCatalogFresh() {
  // Fuente única: MySQL vía api/catalog.php + api/categories.php. Sin fallback a JSON.
  // Precios/stock siempre frescos: sin caché HTTP + cache-buster.
  const bust = '&_=' + Date.now();
  let ps = [], cs = [];
  let page = 1;
  for (;;) {
    const r = await fetch(SITE_ROOT + 'api/catalog.php?per=500&page=' + page + bust, { cache: 'no-store' });
    if (!r.ok) throw new Error('catalogo-http-' + r.status);
    const j = await r.json();
    if (j.items && j.items.length) ps.push(...j.items);
    if (!j.pages || page >= j.pages) break;
    page++;
    if (page > 20) break;
  }
  // DB vacía = catálogo vacío válido (no se lanza error: así se limpia la caché vieja y se pinta "Sin resultados").
  try {
    const r = await fetch(CATS_API + (CATS_API.includes('?') ? '&' : '?') + '_=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { const j = await r.json(); if (Array.isArray(j) && j.length) cs = j; }
  } catch { /* se deriva abajo */ }
  if (!cs.length) cs = [...new Set(ps.flatMap(p => p.categorias || []))];
  return { ps, cs };
}
const HEADER_CATS = ['Celulares', 'Computación', 'Televisiones', 'Videojuegos', 'Electrónica', 'Tablets', 'Hogar'];
function ensureCatNav() {
  if (document.getElementById('ck-form')) return; // checkout: foco limpio, sin nav
  const header = document.querySelector('.topbar');
  if (!header || header.querySelector('.cat-nav')) return;
  const bar = header.querySelector('.topbar-in');
  const logo = bar?.querySelector('a.logo');
  const logoHref = document.querySelector('a.logo')?.getAttribute('href') || 'index.html';
  const base = logoHref.slice(0, logoHref.lastIndexOf('/') + 1);
  let cur = 'Todo';
  try { cur = new URL(location.href).searchParams.get('cat') || 'Todo'; } catch { /* noop */ }
  const links = HEADER_CATS.map(c => {
    const u = base + 'index.html' + (c === 'Todo' ? '' : '?cat=' + encodeURIComponent(c)) + '#catalogo';
    const cls = (c === cur ? ' act' : '') + (c === 'Ofertas' ? ' nav-offer' : '');
    return `<a class="${cls.trim()}" href="${u}"${c === cur ? ' aria-current="true"' : ''}>${c}</a>`;
  }).join('');
  const navHTML = `<nav class="cat-nav" aria-label="Categorías">${links}</nav>`;
  if (bar && logo) logo.insertAdjacentHTML('afterend', navHTML);
  else header.insertAdjacentHTML('beforeend', navHTML);
}
/* Aviso de cookies/privacidad: localStorage + sesión + PayPal + reCAPTCHA. Sin rastreo propio. */
function ensureCookieBanner() {
  try {
    if (localStorage.getItem('santillana_cookie_ok') === '1') {
      document.getElementById('cookie-banner')?.remove();
      return;
    }
    let b = document.getElementById('cookie-banner');
    if (b) {
      b.hidden = false;
      requestAnimationFrame(() => b.classList.add('show'));
      b.querySelector('#cookie-ok')?.addEventListener('click', () => {
        try { localStorage.setItem('santillana_cookie_ok', '1'); } catch { /* noop */ }
        b.classList.remove('show');
        setTimeout(() => b.remove(), 300);
      }, { once: true });
      return;
    }
    b = document.createElement('div');
    b.id = 'cookie-banner';
    b.setAttribute('role', 'dialog');
    b.setAttribute('aria-label', 'Aviso de privacidad');
    b.innerHTML = `<span>Usamos almacenamiento local para tu carrito y PayPal/reCAPTCHA para pagos seguros. Al seguir navegando aceptas nuestro <a href="${SITE_ROOT}privacidad.html">Aviso de Privacidad</a>.</span><button type="button" id="cookie-ok">Entendido</button>`;
    document.body.appendChild(b);
    requestAnimationFrame(() => b.classList.add('show'));
    b.querySelector('#cookie-ok')?.addEventListener('click', () => {
      try { localStorage.setItem('santillana_cookie_ok', '1'); } catch { /* noop */ }
      b.classList.remove('show');
      setTimeout(() => b.remove(), 300);
    });
  } catch { /* noop */ }
}
async function init() {
  ensureCartShell();
  ensureAccountUI();
  ensureCatNav();
  ensureCookieBanner();
  paintCount();
  try {
    const hit = readCatCache();
    let ps, cs;
    if (hit) {
      ({ ps, cs } = hit);
      // pintar con caché y revalidar en segundo plano sin bloquear.
      // Si cambió conteo, precio, stock o disponibilidad → recargar para mostrarlo.
      const sig = arr => arr.map(p => p.id + ':' + p.precio + ':' + p.stock + ':' + (p.disponible ? 1 : 0)).join('|');
      const before = sig(ps);
      fetchCatalogFresh().then(fresh => {
        writeCatCache(fresh.ps, fresh.cs);
        if (sig(fresh.ps) !== before) location.reload();
      }).catch(() => {});
    } else {
      ({ ps, cs } = await fetchCatalogFresh());
      writeCatCache(ps, cs);
    }
    ALL = ps; BYID = Object.fromEntries(ps.map(p => [String(p.id), p])); ALL_CATS = cs.length ? cs : [...new Set(ps.flatMap(p => p.categorias || []))];
    // conteos vivos: hero + stat animado siguen el catálogo real (fuente única: ALL.length)
    const shown = ALL.length;
    const hc = document.getElementById('hero-count');
    if (hc) hc.textContent = shown;
    document.querySelectorAll('[data-products-count]').forEach(el => el.setAttribute('data-count', String(shown)));
    document.querySelectorAll('[data-products-count-text]').forEach(el => { el.textContent = String(shown); });
  } catch {
    const grid = document.getElementById('grid');
    if (grid) grid.innerHTML = '<div class="empty"><b>Catálogo no disponible</b><span>No se pudo cargar desde la base de datos. Intenta recargar.</span></div>';
    return;
  }
  renderDrawer();
  // sesión + carrito guardado en la cuenta (une cantidades con el local)
  await loadMe();
  // pase de "Ir a la tienda": canjea ?adm= por sesión admin y limpia la URL
  try {
    const admT = new URL(location.href).searchParams.get('adm');
    if (admT && !IS_ADMIN) {
      const r = await fetch(SITE_ROOT + 'api/admin-link.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ t: admT })
      });
      const j = await r.json().catch(() => ({}));
      if (r.ok && j.ok) { IS_ADMIN = true; refreshAuthUI(); }
    }
    if (admT) {
      const u = new URL(location.href);
      u.searchParams.delete('adm');
      history.replaceState(null, '', u);
    }
  } catch { /* sin pase: se sigue como visitante */ }
  if (ME) {
    try {
      const r = await fetch(SITE_ROOT + 'api/cart-sync.php');
      if (r.ok) {
        const j = await r.json();
        if (j.items && j.items.length) {
          const local = getCart();
          let changed = false;
          j.items.forEach(it => {
            const id = String(it.id);
            if (!BYID[id]) return;
            const q = Math.min(99, Math.max(it.qty, local[id] || 0));
            if ((local[id] || 0) !== q) { local[id] = q; changed = true; }
          });
          if (changed) setCart(local);
        }
      }
    } catch { /* sin red: se usa el local */ }
  }
  const url = new URL(location.href);
  const q0 = norm(url.searchParams.get('q') || '');
  const c0 = url.searchParams.get('cat') || 'Todo';
  const o0 = ['rel', 'asc', 'desc', 'az'].includes(url.searchParams.get('orden')) ? url.searchParams.get('orden') : 'rel';
  PAGE = Math.max(1, parseInt(url.searchParams.get('pag') || '1', 10) || 1);
  PERPAGE = parseInt(url.searchParams.get('per') || localStorage.getItem('santillana_perpage') || '20', 10) || 20;
  if (![12, 20, 32, 48].includes(PERPAGE)) PERPAGE = 20;
  CUR = {
    q: q0, c: c0, o: o0,
    marca: url.searchParams.get('marca') || '',
    precio: url.searchParams.get('precio') || '',
    disp: url.searchParams.get('disp') === '1',
    oferta: url.searchParams.get('oferta') === '1'
  };
  if (document.getElementById('q')) document.getElementById('q').value = url.searchParams.get('q') || '';
  initChrome();
  initSearch();
  if (document.getElementById('grid')) {
    paintSkeletons(Math.min(PERPAGE, 12));
    fillMarcaSelect();
    // Sin ofertas en catálogo: ocultar el filtro para no llevar a callejón sin salida.
    if (!ALL.some(p => (p.categorias || []).includes('Ofertas') || p.en_oferta)) {
      document.querySelector('label.f-check[for="f-oferta"]')?.remove();
      if (CUR.oferta) { CUR.oferta = false; syncUrl(); }
    }
    renderActiveFilters();
    const ord = document.getElementById('orden');
    if (ord) { ord.value = o0; ord.onchange = e => { CUR.o = e.target.value; PAGE = 1; syncUrl(); render(); }; }
    const pp = document.getElementById('per-page');
    if (pp) { pp.value = String(PERPAGE); pp.onchange = e => { PERPAGE = parseInt(e.target.value, 10); localStorage.setItem('santillana_perpage', String(PERPAGE)); PAGE = 1; syncUrl(); render(); }; }
    const fm = document.getElementById('f-marca');
    if (fm) { fm.value = CUR.marca; fm.onchange = e => { CUR.marca = e.target.value; PAGE = 1; syncUrl(); render(); renderActiveFilters(); }; }
    const fp = document.getElementById('f-precio');
    if (fp) { fp.value = CUR.precio; fp.onchange = e => { CUR.precio = e.target.value; PAGE = 1; syncUrl(); render(); renderActiveFilters(); }; }
    const fd = document.getElementById('f-disp');
    if (fd) { fd.checked = CUR.disp; fd.onchange = e => { CUR.disp = e.target.checked; PAGE = 1; syncUrl(); render(); renderActiveFilters(); }; }
    const fo = document.getElementById('f-oferta');
    if (fo) { fo.checked = CUR.oferta; fo.onchange = e => { CUR.oferta = e.target.checked; PAGE = 1; syncUrl(); render(); renderActiveFilters(); }; }
    document.getElementById('prev-page')?.addEventListener('click', () => gotoPage(PAGE - 1));
    document.getElementById('next-page')?.addEventListener('click', () => gotoPage(PAGE + 1));
    render();
    renderActiveFilters();
    updateSeoMeta();
    // si se llegó con filtro (categoría o búsqueda), bajar al catálogo
    if (CUR.c !== 'Todo' || CUR.q) {
      setTimeout(() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'auto', block: 'start' }), 120);
    }
    renderHome(ALL_CATS);
    paintDrawerCats();
    paintHero();
    bindCarousels();
  } else {
    // ficha de producto: refrescar drawer + precio/stock en vivo
    renderDrawer();
    refreshFicha();
  }
  if (document.getElementById('cartbox')) renderCartPage();
  const nf = document.getElementById('news-form');
  if (nf) nf.addEventListener('submit', subscribeNews);
}
async function subscribeNews(e) {
  e.preventDefault();
  const nf = e.target;
  // Honeypot anti-bots: si se llenó el campo trampa, fingir éxito sin llamar al API.
  const trap = nf.querySelector('input[name="empresa"]');
  if (trap && trap.value) { nf.innerHTML = '<b>Gracias por suscribirte. Revisa tu correo.</b>'; return; }
  const email = (nf.querySelector('input[type="email"]')?.value || '').trim();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { toast('Ingresa un correo válido.'); return; }
  try {
    const r = await fetch(SITE_ROOT + 'api/newsletter.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email })
    });
    const j = await r.json().catch(() => ({}));
    nf.innerHTML = '<b>' + (j.dup ? 'Ya estabas suscrito. ¡Gracias!' : 'Gracias por suscribirte. Revisa tu correo.') + '</b>';
  } catch {
    nf.innerHTML = '<b>Gracias por suscribirte. Revisa tu correo.</b>';
  }
}
async function refreshFicha() {
  const btn = document.getElementById('addbtn');
  if (!btn || !btn.dataset.add) return;
  const id = btn.dataset.add;
  try {
    const r = await fetch(SITE_ROOT + 'api/product.php?id=' + encodeURIComponent(id));
    if (!r.ok) throw new Error('off');
    const p = await r.json();
    BYID[String(p.id)] = { ...(BYID[String(p.id)] || {}), ...p };
    const pr = document.querySelector('.detail .precio');
    if (pr) pr.textContent = fmt(p.precio);
    const d = document.getElementById('ficha-disp');
    if (d) {
      if (!p.disponible || p.stock <= 0) {
        d.innerHTML = '<b>Agotado.</b> Avísanos por <a href="https://wa.me/523310642354" target="_blank" rel="noopener">WhatsApp</a> y te conseguimos uno.';
        btn.disabled = true; btn.textContent = 'Agotado';
      } else if (p.stock <= 3) {
        d.innerHTML = `<b>¡Solo quedan ${p.stock}!</b> Se actualiza al confirmar tu pago.`;
      } else {
        d.innerHTML = `<b>En stock (${p.stock} disponibles).</b>`;
      }
    }
  } catch {
    const d = document.getElementById('ficha-disp');
    if (d) d.textContent = 'Disponibilidad sujeta a confirmación al pagar.';
  }
}
function fillMarcaSelect() {
  const sel = document.getElementById('f-marca');
  if (!sel) return;
  const marcas = [...new Set(ALL.map(p => p.marca).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
  sel.innerHTML = '<option value="">Todas</option>' + marcas.map(m => `<option value="${m}">${m}</option>`).join('');
  sel.value = CUR.marca;
}
function syncUrl() {
  try {
    const url = new URL(location.href);
    url.searchParams.set('orden', CUR.o);
    if (CUR.q) url.searchParams.set('q', CUR.q); else url.searchParams.delete('q');
    if (CUR.c && CUR.c !== 'Todo') url.searchParams.set('cat', CUR.c); else url.searchParams.delete('cat');
    if (CUR.marca) url.searchParams.set('marca', CUR.marca); else url.searchParams.delete('marca');
    if (CUR.precio) url.searchParams.set('precio', CUR.precio); else url.searchParams.delete('precio');
    if (CUR.disp) url.searchParams.set('disp', '1'); else url.searchParams.delete('disp');
    if (CUR.oferta) url.searchParams.set('oferta', '1'); else url.searchParams.delete('oferta');
    if (PAGE > 1) url.searchParams.set('pag', String(PAGE)); else url.searchParams.delete('pag');
    if (PERPAGE !== 20) url.searchParams.set('per', String(PERPAGE)); else url.searchParams.delete('per');
    history.replaceState(null, '', url);
  } catch { }
  updateSeoMeta();
}
/* SEO: solo la vista limpia (Todo, sin q/filtros, pag 1, orden rel) es indexable.
   Vistas con búsqueda, filtros, orden no-default o paginación -> noindex,follow.
   Además actualiza <title> y meta description según categoría/búsqueda para UX (no para indexar). */
const __BASE_TITLE = document.title || 'Santillana';
function updateSeoMeta() {
  try {
    const filtered = !!(CUR.q || CUR.marca || CUR.precio || CUR.disp || CUR.oferta || PAGE > 1 || CUR.o !== 'rel' || PERPAGE !== 20);
    let m = document.querySelector('meta[name="robots"]');
    if (filtered) {
      if (!m) { m = document.createElement('meta'); m.setAttribute('name', 'robots'); document.head.appendChild(m); }
      m.setAttribute('content', 'noindex,follow');
    } else if (m && m.getAttribute('content') === 'noindex,follow') {
      m.remove();
    }
    // Título dinámico UX: "Celulares — Santillana" / "“iphone” — Santillana"
    const rawQ = (new URL(location.href).searchParams.get('q') || '').trim();
    if (rawQ) document.title = `“${rawQ.slice(0, 40)}” — Santillana`;
    else if (CUR.c && CUR.c !== 'Todo') document.title = `${CUR.c} — Santillana`;
    else document.title = __BASE_TITLE;
  } catch { }
}
function gotoPage(n, scroll = true) {
  const totalPages = Math.max(1, Math.ceil(FILTERED.length / PERPAGE));
  n = Math.min(Math.max(1, n), totalPages);
  if (n === PAGE) { if (scroll) document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' }); return; }
  PAGE = n;
  syncUrl();
  paintPage();
  if (scroll) document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
}
window.gotoPage = gotoPage;
function renderActiveFilters() {
  const box = document.getElementById('active-filters'); if (!box) return;
  const chips = [];
  const xIcon = '<svg class="ic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
  const chip = (label, clear) => {
    const url = new URL(location.href);
    clear.forEach(k => url.searchParams.delete(k)); url.searchParams.delete('pag');
    chips.push(`<span class="filter-chip">${label}<a href="${url}" aria-label="Quitar filtro">${xIcon}</a></span>`);
  };
  if (CUR.c && CUR.c !== 'Todo') chip(CUR.c, ['cat']);
  const rawQ = new URL(location.href).searchParams.get('q') || '';
  if (CUR.q && rawQ) chip('“' + rawQ + '”', ['q']);
  if (CUR.marca) chip(CUR.marca, ['marca']);
  if (CUR.precio) chip('Precio ' + CUR.precio.replace('-', '–'), ['precio']);
  if (CUR.disp) chip('Disponibles', ['disp']);
  if (CUR.oferta) chip('Ofertas', ['oferta']);
  box.innerHTML = chips.join('') || '<span class="nota">Sin filtros activos</span>';
  // badge discreto en el botón + etiqueta del drawer
  const n = chips.length;
  const badge = document.getElementById('filters-count');
  if (badge) { badge.textContent = n; badge.hidden = n === 0; }
  const apply = document.getElementById('filter-apply');
  if (apply) apply.textContent = `Ver ${FILTERED.length} producto${FILTERED.length === 1 ? '' : 's'}`;
  // estado activo de categorías en el drawer
  document.querySelectorAll('#drawer-cats a').forEach(a => {
    a.classList.toggle('act', a.dataset.cat === CUR.c || (CUR.c === 'Todo' && a.dataset.cat === 'Todo'));
  });
}
/* Pills de categoría dentro del drawer */
function paintDrawerCats() {
  const box = document.getElementById('drawer-cats');
  if (!box || !ALL_CATS.length) return;
  const cats = ['Todo', ...ALL_CATS];
  box.innerHTML = cats.map(c => {
    const url = new URL(location.href);
    if (c === 'Todo') url.searchParams.delete('cat');
    else url.searchParams.set('cat', c);
    url.searchParams.delete('pag');
    url.hash = 'catalogo';
    return `<a href="${url}" data-cat="${c}">${c === 'Todo' ? 'Todo' : c}</a>`;
  }).join('');
}
function stockOf(p) {
  const s = parseInt(p.stock ?? '10', 10);
  return Number.isFinite(s) ? s : 10;
}
function card(p, eager) {
  const isOffer = (p.categorias || []).includes('Ofertas') || p.en_oferta;
  const st = stockOf(p);
  const pl = Number(p.precio_lista || 0), pr = Number(p.precio || 0);
  const pct = (isOffer && pl > pr) ? Math.round((1 - pr / pl) * 100) : 0;
  const badges = (isOffer
    ? `<div class="card-badges"><span class="badge badge-offer">Oferta</span>${pct ? `<span class="badge badge-save">-${pct}%</span>` : ''}</div>`
    : `<div class="card-badges"><span class="badge">${p.cat_primary}</span></div>`)
    + (st <= 0 ? `<div class="card-badges"><span class="badge badge-out">Agotado</span></div>`
      : st <= 3 ? `<div class="card-badges"><span class="badge badge-low">¡Últimas ${st}!</span></div>` : '');
  const addBtn = st <= 0
    ? `<button class="card-add" disabled aria-disabled="true" aria-label="Agotado">${ICONS.cart}</button>`
    : `<button class="card-add" data-add="${p.id}" aria-label="Agregar ${escHtml(p.nombre)} al carrito">${ICONS.cart}</button>`;
  const priceRow = pct
    ? `<div class="price-row"><span class="precio">${fmt(p.precio)}</span><span class="old-price">${fmt(p.precio_lista)}</span></div>`
    : `<span class="precio">${fmt(p.precio)}</span>`;
  return `<article class="card"><div class="card-img"><a href="${prodUrl(p)}" aria-label="Ver ${escHtml(p.nombre)}" tabindex="-1">${picTag(p, 'large', 480, 210, eager)}</a>${addBtn}</div>
  <div class="card-b">${badges}
  <h3><a href="${prodUrl(p)}">${escHtml(p.nombre)}</a></h3>
  <span class="sku">SKU ${escHtml(p.sku)}</span>${priceRow}
  </div></article>`;
}
function paintSkeletons(n) {
  const grid = document.getElementById('grid');
  if (!grid) return;
  grid.innerHTML = Array.from({ length: n }, () => `<div class="skel-card" aria-hidden="true"><div class="skel skel-img"></div><div class="skel-body"><div class="skel skel-pill"></div><div class="skel skel-line w80"></div><div class="skel skel-line w60"></div></div></div>`).join('');
}
function render(q, c, o) {
  if (q === undefined) { q = CUR.q; c = CUR.c; o = CUR.o; }
  else CUR = { ...CUR, q, c, o };
  let list = ALL.filter(p => {
    if (c !== 'Todo' && !(p.categorias || []).includes(c)) return false;
    if (q && !norm(p.nombre + ' ' + p.sku + ' ' + (p.marca || '')).includes(q)) return false;
    if (CUR.marca && (p.marca || '') !== CUR.marca) return false;
    if (CUR.precio) {
      const [a, b] = CUR.precio.split('-').map(Number);
      if (!(p.precio >= a && p.precio < b)) return false;
    }
    if (CUR.disp && stockOf(p) <= 0) return false;
    if (CUR.oferta && !((p.categorias || []).includes('Ofertas') || p.en_oferta)) return false;
    return true;
  });
  if (o === 'asc') list = [...list].sort((a, b) => a.precio - b.precio);
  else if (o === 'desc') list = [...list].sort((a, b) => b.precio - a.precio);
  else if (o === 'az') list = [...list].sort((a, b) => a.nombre.localeCompare(b.nombre));
  FILTERED = list;
  const totalPages = Math.max(1, Math.ceil(list.length / PERPAGE));
  if (PAGE > totalPages) PAGE = totalPages;
  paintPage();
}
function paintPage() {
  const grid = document.getElementById('grid');
  if (!grid) return;
  const total = FILTERED.length;
  const totalPages = Math.max(1, Math.ceil(total / PERPAGE));
  const start = (PAGE - 1) * PERPAGE;
  const slice = FILTERED.slice(start, start + PERPAGE);
  const countEl = document.getElementById('count');
  if (countEl) countEl.textContent = total + ' productos';
  const infoEl = document.getElementById('page-info');
  if (infoEl) infoEl.textContent = total ? `Mostrando ${start + 1}–${Math.min(start + PERPAGE, total)} de ${total} · Página ${PAGE} de ${totalPages}` : '';
  grid.innerHTML = slice.map(card).join('') || `<div class="empty"><svg class="ic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35M8 11h6"/></svg><b>Sin resultados</b><span>Prueba con otra búsqueda o quita los filtros.</span><a class="btn ver" href="index.html" style="max-width:220px">Ver todo el catálogo</a></div>`;
  grid.classList.remove('page-enter');
  void grid.offsetWidth;
  grid.classList.add('page-enter');
  paintPagination(totalPages);
}
function paintPagination(totalPages) {
  const nav = document.getElementById('pagination');
  const nums = document.getElementById('page-numbers');
  const prev = document.getElementById('prev-page');
  const next = document.getElementById('next-page');
  if (!nav || !nums) return;
  if (FILTERED.length === 0 || totalPages <= 1) { nav.style.display = 'none'; return; }
  nav.style.display = 'flex';
  if (prev) prev.disabled = PAGE <= 1;
  if (next) next.disabled = PAGE >= totalPages;
  const pages = paginationRange(PAGE, totalPages);
  nums.innerHTML = pages.map(p => p === '…'
    ? '<span class="page-dots">…</span>'
    : `<button class="page-btn${p === PAGE ? ' active' : ''}" data-page="${p}" aria-label="Ir a la página ${p}"${p === PAGE ? ' aria-current="page"' : ''}>${p}</button>`
  ).join('');
  nums.onclick = e => {
    const b = e.target.closest('[data-page]');
    if (b) gotoPage(parseInt(b.dataset.page, 10));
  };
}
function paginationRange(cur, total) {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const set = new Set([1, 2, cur - 1, cur, cur + 1, total - 1, total]);
  const arr = [...set].filter(n => n >= 1 && n <= total).sort((a, b) => a - b);
  const out = [];
  for (let i = 0; i < arr.length; i++) {
    out.push(arr[i]);
    if (i < arr.length - 1 && arr[i + 1] - arr[i] > 1) out.push('…');
  }
  return out;
}
function renderHome(cs) {
  const hc = document.getElementById('home-cats');
  if (hc && cs) {
    hc.innerHTML = cs.map(c => {
      const items = ALL.filter(p => p.categorias.includes(c)); const rep = items[0];
      const im = rep ? picTag(rep, 'compact', 172, 100, true).replace('__imgFb(this)', "this.onerror=null;this.style.display='none'") : '';
      return `<a class="cat-card" href="index.html?cat=${encodeURIComponent(c)}#catalogo"><span class="cat-img">${im}</span><div><b>${c}</b><span>${items.length} productos →</span></div></a>`;
    }).join('');
  }
  const fg = document.getElementById('feat-grid'); if (!fg) return;
  const isFeat = p => p.categorias.includes('Ofertas') || p.categorias.includes('Novedades');
  let feat = [...ALL.filter(isFeat), ...[...ALL].sort((a, b) => b.precio - a.precio)];
  feat = [...new Map(feat.map(p => [p.id, p])).values()].slice(0, 8);
  fg.innerHTML = feat.map(p => card(p, true)).join('');
  updateCarouselNav('home-cats');
  updateCarouselNav('feat-grid');
}
/* ---------- Hero visual: 2 productos aleatorios de la DB, rotan cada 10s ---------- */
let __heroTimer = null;
function heroSub(p) {
  const isOffer = (p.categorias || []).includes('Ofertas') || p.en_oferta;
  if (isOffer) return 'Oferta';
  return '';
}
function heroCardsHTML(pair) {
  return pair.map(p => {
    const thumb = resolveImg(p), orig = resolveOrig(p);
    const fb = (orig && orig !== thumb) ? ` data-large="${orig}"` : '';
    return `<a class="hv-card" href="${prodUrl(p)}" aria-label="Ver ${escHtml(p.nombre)}"><img src="${thumb}"${fb} alt="${escHtml(p.nombre)}" loading="eager" fetchpriority="high" decoding="async" onerror="__imgFb(this)"><div><b>${escHtml(p.nombre)}</b><small>${heroSub(p)}</small></div><span class="hv-price">${fmt(p.precio)}</span></a>`;
  }).join('');
}
function heroRandomPair() {
  const pool = ALL.filter(hasPhoto);
  const src = pool.length > 1 ? pool : ALL;
  if (!src.length) return [];
  if (src.length === 1) return [src[0]];
  const prev = heroRandomPair._prev || '';
  let a = 0, b = 0, guard = 0;
  do {
    a = Math.floor(Math.random() * src.length);
    b = Math.floor(Math.random() * src.length);
    guard++;
  } while ((b === a || (src[a].id + '|' + src[b].id) === prev) && guard < 12);
  heroRandomPair._prev = src[a].id + '|' + src[b].id;
  return [src[a], src[b]];
}
function paintHero() {
  const box = document.querySelector('.hero-visual');
  if (!box) return;
  if (!ALL.length) { box.style.display = 'none'; return; }
  box.style.display = '';
  box.innerHTML = heroCardsHTML(heroRandomPair());
  if (__heroTimer) { clearInterval(__heroTimer); __heroTimer = null; }
  if (ALL.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    __heroTimer = setInterval(() => {
      box.style.transition = 'opacity .3s';
      box.style.opacity = '0';
      setTimeout(() => { box.innerHTML = heroCardsHTML(heroRandomPair()); box.style.opacity = '1'; }, 300);
    }, 10000);
  }
}
/* Carruseles de una sola fila: categorias y destacados */
function bindCarousels() {
  document.querySelectorAll('[data-carousel-prev],[data-carousel-next]').forEach(btn => {
    if (btn.dataset.cBound) return; btn.dataset.cBound = '1';
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-carousel-prev') || btn.getAttribute('data-carousel-next');
      const track = document.getElementById(id);
      if (!track) return;
      const dir = btn.hasAttribute('data-carousel-prev') ? -1 : 1;
      track.scrollBy({ left: dir * Math.max(track.clientWidth * 0.8, 320), behavior: 'smooth' });
    });
  });
  ['home-cats', 'feat-grid'].forEach(id => {
    const track = document.getElementById(id);
    if (!track || track.dataset.sBound) return; track.dataset.sBound = '1';
    track.addEventListener('scroll', () => updateCarouselNav(id), { passive: true });
    window.addEventListener('resize', () => updateCarouselNav(id));
    // arrastre con mouse en escritorio
    let down = false, sx = 0, sl = 0;
    track.addEventListener('pointerdown', e => { down = true; sx = e.clientX; sl = track.scrollLeft; });
    window.addEventListener('pointermove', e => { if (down && track) track.scrollLeft = sl - (e.clientX - sx); });
    window.addEventListener('pointerup', () => down = false);
  });
  updateCarouselNav('home-cats');
  updateCarouselNav('feat-grid');
}
function updateCarouselNav(id) {
  const track = document.getElementById(id); if (!track) return;
  const prev = document.querySelector(`[data-carousel-prev="${id}"]`);
  const next = document.querySelector(`[data-carousel-next="${id}"]`);
  const max = track.scrollWidth - track.clientWidth - 6;
  if (prev) prev.disabled = track.scrollLeft <= 6;
  if (next) next.disabled = max <= 6 || track.scrollLeft >= max;
}
/* ---------- Buscador: autocompletado + filtro en vivo ---------- */
const escHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
function initSearch() {
  document.querySelectorAll('form.search').forEach(form => {
    const input = form.querySelector('input[name="q"],input[type="search"]');
    if (!input || input.dataset.sBound) return;
    input.dataset.sBound = '1';
    const drop = document.createElement('div');
    drop.className = 'search-drop';
    drop.hidden = true;
    form.appendChild(drop);
    let items = [], hi = -1, deb = null;
    const onCatalog = () => !!document.getElementById('grid');
    const close = () => { drop.hidden = true; hi = -1; };
    const paintHi = () => drop.querySelectorAll('.search-item').forEach((a, i) => a.classList.toggle('hi', i === hi));
    const suggest = () => {
      const q = norm(input.value.trim());
      if (q.length < 2 || !ALL.length) { close(); return; }
      const total = ALL.filter(p => norm(p.nombre + ' ' + p.sku + ' ' + (p.marca || '')).includes(q));
      items = total.slice(0, 6);
      if (!items.length) {
        drop.innerHTML = '<div class="search-empty">Sin coincidencias. Pulsa Buscar para intentarlo en el catálogo.</div>';
        drop.hidden = false;
        return;
      }
      drop.innerHTML = items.map((p, i) =>
        `<a class="search-item" href="${prodUrl(p)}" data-i="${i}"><img loading="lazy" decoding="async" width="46" height="46" src="${resolveImg(p)}" alt="" onerror="this.onerror=null;this.src='${BASE_PREFIX}assets/logo-santillana.png'"><span class="search-item-txt"><b>${escHtml(p.nombre.length > 52 ? p.nombre.slice(0, 52) + '…' : p.nombre)}</b><small>${escHtml(p.marca || p.cat_primary || '')} · ${fmt(p.precio)}</small></span></a>`
      ).join('') + `<button class="search-all" type="submit">Ver los ${total.length} resultados</button>`;
      drop.hidden = false;
    };
    input.addEventListener('input', () => {
      clearTimeout(deb);
      deb = setTimeout(() => {
        if (onCatalog()) {
          CUR.q = norm(input.value.trim());
          PAGE = 1;
          syncUrl();
          render();
          renderActiveFilters();
          updateSeoMeta();
        }
        suggest();
      }, 220);
    });
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2) suggest(); });
    input.addEventListener('keydown', e => {
      if (drop.hidden) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        hi = e.key === 'ArrowDown' ? Math.min(items.length - 1, hi + 1) : Math.max(0, hi - 1);
        paintHi();
        drop.querySelectorAll('.search-item')[hi]?.scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter' && hi >= 0 && items[hi]) {
        e.preventDefault();
        location.href = prodUrl(items[hi]);
      } else if (e.key === 'Escape') close();
    });
    drop.addEventListener('click', e => {
      const a = e.target.closest('.search-item');
      if (a && items[+a.dataset.i]) { e.preventDefault(); location.href = prodUrl(items[+a.dataset.i]); }
    });
    document.addEventListener('click', e => {
      if (!form.contains(e.target)) close();
    });
    form.addEventListener('submit', e => {
      if (onCatalog()) {
        e.preventDefault();
        close();
        CUR.q = norm(input.value.trim());
        PAGE = 1;
        syncUrl();
        render();
        renderActiveFilters();
        updateSeoMeta();
        document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });
}
/* ---------- Pulido visual: chrome, reveal, contadores ---------- */
function initChrome() {
  const header = document.querySelector('.topbar');
  // Filter drawer (slim bar + side panel)
  const fOpen = document.getElementById('filters-open');
  const fDrawer = document.getElementById('filter-drawer');
  const fOverlay = document.getElementById('filter-overlay');
  const openFilters = () => {
    closeCart();
    fDrawer?.classList.add('open');
    fOverlay?.classList.add('show');
    document.body.style.overflow = 'hidden';
  };
  const closeFilters = () => {
    fDrawer?.classList.remove('open');
    fOverlay?.classList.remove('show');
    if (!document.getElementById('cart-drawer')?.classList.contains('open')) document.body.style.overflow = '';
  };
  window.openFilters = openFilters;
  window.closeFilters = closeFilters;
  if (fOpen && !fOpen.dataset.bound) {
    fOpen.dataset.bound = '1';
    fOpen.addEventListener('click', openFilters);
  }
  document.getElementById('filter-close')?.addEventListener('click', closeFilters);
  fOverlay?.addEventListener('click', closeFilters);
  document.getElementById('filter-apply')?.addEventListener('click', () => {
    closeFilters();
    document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
  });
  document.getElementById('filter-clear')?.addEventListener('click', () => {
    location.href = SITE_ROOT + 'index.html#catalogo';
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && fDrawer?.classList.contains('open')) closeFilters();
  });
  let toTop = document.getElementById('to-top');
  if (!toTop) {
    toTop = document.createElement('button');
    toTop.id = 'to-top';
    toTop.type = 'button';
    toTop.setAttribute('aria-label', 'Volver arriba');
    toTop.innerHTML = '<svg class="ic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
    document.body.appendChild(toTop);
  }
  const setStickyTop = () => {
    if (header) document.documentElement.style.setProperty('--sticky-top', (header.offsetHeight + 8) + 'px');
  };
  setStickyTop();
  window.addEventListener('resize', setStickyTop);
  setTimeout(setStickyTop, 500);
  window.addEventListener('scroll', () => {
    if (header) header.classList.toggle('scrolled', window.scrollY > 10);
    if (toTop) toTop.classList.toggle('show', window.scrollY > 650);
  }, { passive: true });
  toTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  initReveal();
  initCounters();
}
function initReveal() {
  const els = document.querySelectorAll('main .hero, main .benefits, main #categorias, main #destacados, main .banner-promo, main #catalogo, main .news');
  if (!('IntersectionObserver' in window)) { els.forEach(el => el.classList.add('visible')); return; }
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('visible'); io.unobserve(en.target); } });
  }, { threshold: 0.08 });
  els.forEach(el => { el.classList.add('reveal'); io.observe(el); });
}
function initCounters() {
  const nums = document.querySelectorAll('[data-count]');
  if (!nums.length) return;
  const animate = el => {
    const target = parseInt(el.dataset.count, 10) || 0;
    const pre = el.dataset.prefix || '', suf = el.dataset.suffix || '';
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = pre + target.toLocaleString('es-MX') + suf; return; }
    const t0 = performance.now(), dur = 1100;
    const tick = t => {
      const k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
      el.textContent = pre + Math.round(target * e).toLocaleString('es-MX') + suf;
      if (k < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if (!('IntersectionObserver' in window)) { nums.forEach(animate); return; }
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) { animate(en.target); io.unobserve(en.target); } });
  }, { threshold: 0.4 });
  nums.forEach(el => io.observe(el));
}

document.addEventListener('click', e => {
  const b = e.target.closest('[data-add]');
  if (b) { e.preventDefault(); addToCart(b.dataset.add, parseInt(b.dataset.qty || '1', 10)); }
  const o = e.target.closest('[data-open-cart]');
  if (o) { e.preventDefault(); openCart(); }
});
document.addEventListener('DOMContentLoaded', init);
