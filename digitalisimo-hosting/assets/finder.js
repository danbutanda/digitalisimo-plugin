/*
 * Buscador de dominios estilo registrador de DIGITALÍSIMO Hosting (Digitalisimo_Hosting_Finder).
 * - Una búsqueda consulta a la vez todas las extensiones con producto (POST a digitalisimo-hosting/v1/finder con nonce).
 *   Si se lanza otra antes de que termine, la vieja se descarta (número de petición) y el botón queda inactivo mientras.
 * - Agregar usa la Store API de WooCommerce (cart/add-item) sin recargar: el botón pasa a «Agregado ✓», se abre «Se
 *   compran juntos» debajo y aparece la barra fija con artículos, subtotal y «Pagar» (nunca en la página del carrito,
 *   que ya muestra el suyo). El servidor valida el nombre y el precio es el del producto: aquí sólo se manda el ID del
 *   producto y el nombre que devolvió la búsqueda. Después se avisa al resto de la página (fragmentos y bloques).
 * - Sólo el aviso (role=status) se anuncia; los resultados no son una región viva.
 * - El texto sobre el color de acento y el gris se eligen por contraste (al menos 4.5:1) con los colores reales del kit.
 * - Todo lo que viene del visitante o del servidor se pinta con textContent (nunca como HTML).
 */
(() => {
  const c = window.digitalisimoFinder || {};
  const ROOT = '[data-digitalisimo-finder]';
  const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined && text !== null) e.textContent = String(text); return e; };
  const plain = (html) => { try { return new DOMParser().parseFromString(String(html || ''), 'text/html').body.textContent || ''; } catch (e) { return String(html || ''); } };
  let storeNonce = c.storeNonce || '';
  let cart = null;
  let bar = null;

  // ── Contraste ───────────────────────────────────────────────────────────────────────────────────────────────
  const rgb = (s) => {
    const m = String(s || '').match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const p = m[1].split(/[\s,/]+/).filter(Boolean).map(Number);
    return p.length >= 3 ? { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 } : null;
  };
  const hex = (h) => ({ r: parseInt(h.slice(1, 3), 16), g: parseInt(h.slice(3, 5), 16), b: parseInt(h.slice(5, 7), 16), a: 1 });
  const lum = (x) => { const f = (v) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4); }; return 0.2126 * f(x.r) + 0.7152 * f(x.g) + 0.0722 * f(x.b); };
  const ratio = (a, b) => { const x = lum(a); const y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const bgOf = (node) => { for (let n = node; n && n.nodeType === 1; n = n.parentElement) { const col = rgb(getComputedStyle(n).backgroundColor); if (col && col.a > 0.5) return col; } return { r: 255, g: 255, b: 255, a: 1 }; };
  const best = (bg, list) => list.find((h) => ratio(hex(h), bg) >= 4.5) || (lum(bg) > 0.18 ? '#000000' : '#ffffff');
  /** Texto sobre el acento (claro u oscuro, el de más contraste) y grises y estados con 4.5:1 sobre el fondo real. */
  function tune(scope, accentEl) {
    const acc = accentEl ? rgb(getComputedStyle(accentEl).backgroundColor) : null;
    if (acc) scope.style.setProperty('--dhf-on-accent', ratio(acc, hex('#111111')) >= ratio(acc, hex('#ffffff')) ? '#111111' : '#ffffff');
    const bg = bgOf(scope);
    const light = lum(bg) > 0.18;
    scope.style.setProperty('--dhf-muted', best(bg, light ? ['#595959', '#404040'] : ['#b3b3b3', '#d9d9d9']));
    scope.style.setProperty('--dhf-ok', best(bg, light ? ['#1a7f37', '#0f5a26'] : ['#4ade80', '#86efac']));
    scope.style.setProperty('--dhf-no', best(bg, light ? ['#b42318', '#8a1a12'] : ['#f87171', '#fca5a5']));
  }

  // ── Store API ───────────────────────────────────────────────────────────────────────────────────────────────
  const storeUrl = (route) => {
    const u = new URL(c.store || '/wp-json/wc/store/v1/', window.location.href);
    const base = u.searchParams.get('rest_route');
    if (base !== null) u.searchParams.set('rest_route', base.replace(/\/?$/, '/') + route);
    else u.pathname = u.pathname.replace(/\/?$/, '/') + route;
    return u.toString();
  };
  const remember = (r) => { const n = r.headers && r.headers.get && r.headers.get('Nonce'); if (n) storeNonce = n; };
  async function loadCart() {
    try {
      const r = await fetch(storeUrl('cart'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      remember(r);
      if (r.ok) { cart = await r.json(); renderBar(); markAdded(); }
    } catch (e) { /* sin carrito no hay barra */ }
  }
  async function addItem(body, retry = true) {
    const r = await fetch(storeUrl('cart/add-item'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Nonce: storeNonce }, body: JSON.stringify(body) });
    remember(r);
    let d = null;
    try { d = await r.json(); } catch (e) { d = null; }
    // Nonce vencido (página guardada en caché): se pide uno nuevo con el carrito y se reintenta una vez.
    if (!r.ok && retry && d && /nonce/i.test(String(d.code || ''))) { await loadCart(); return addItem(body, false); }
    if (!r.ok) throw new Error(plain((d && d.message) || 'No se pudo agregar. Intenta de nuevo.'));
    return d;
  }
  /** El resto de la página se entera: el minicarrito clásico (fragmentos) y los bloques de WooCommerce. */
  function announceAdded() {
    try { if (window.jQuery) window.jQuery(document.body).trigger('wc_fragment_refresh'); } catch (e) { /* sin jQuery */ }
    document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true, cancelable: true, detail: { preserveCartData: false } }));
  }
  /** ¿Es la página del carrito? Ahí no hay barra: el carrito real ya se ve. */
  const onCartPage = () => !!document.querySelector('.wp-block-woocommerce-cart, .woocommerce-cart-form') || document.body.classList.contains('woocommerce-cart');

  // ── Barra fija del carrito ──────────────────────────────────────────────────────────────────────────────────
  const dataOf = (item, re) => (item.item_data || []).find((x) => re.test(plain(x.name || x.key || '')));
  const domainOf = (item) => { const d = dataOf(item, /dominio/i); return d ? plain(d.value || d.display || '') : ''; };
  const money = (minor, t) => {
    const unit = Number(t.currency_minor_unit || 0);
    const n = Number(minor || 0) / Math.pow(10, unit);
    const txt = n.toLocaleString('es-MX', { minimumFractionDigits: Number.isInteger(n) ? 0 : unit, maximumFractionDigits: unit });
    return `${plain(t.currency_prefix || t.currency_symbol || '$')}${txt}${plain(t.currency_suffix || '')}`;
  };
  function renderBar() {
    if (!cart || !Array.isArray(cart.items)) return;
    if (onCartPage()) { if (bar) bar.hidden = true; document.body.classList.remove('digitalisimo-finder-has-bar'); return; }
    const count = cart.items.reduce((s, i) => s + Number(i.quantity || 0), 0);
    if (!bar) {
      bar = el('div', 'digitalisimo-finder-cartbar');
      bar.setAttribute('role', 'region');
      bar.setAttribute('aria-label', 'Tu carrito');
      bar.hidden = true;
      document.body.appendChild(bar);
    }
    bar.textContent = '';
    if (!count) { bar.hidden = true; document.body.classList.remove('digitalisimo-finder-has-bar'); return; }
    const info = el('div', 'digitalisimo-finder-cartbar__info');
    info.appendChild(el('p', 'digitalisimo-finder-cartbar__count', count === 1 ? '1 artículo' : `${count} artículos`));
    const list = el('ul', 'digitalisimo-finder-cartbar__items');
    cart.items.forEach((i) => {
      const d = domainOf(i);
      list.appendChild(el('li', 'digitalisimo-finder-cartbar__item', d ? `${plain(i.name)}: ${d}` : plain(i.name)));
    });
    info.appendChild(list);
    const t = cart.totals || {};
    const tax = Number(t.total_items_tax || 0);
    // Como en las filas: con la tienda mostrando precios con IVA, el subtotal también lo lleva.
    const sum = c.taxIncluded ? Number(t.total_items || 0) + tax : Number(t.total_items || 0);
    const sub = el('p', 'digitalisimo-finder-cartbar__subtotal');
    sub.appendChild(el('span', 'digitalisimo-finder-cartbar__subtotal-label', 'Subtotal '));
    sub.appendChild(el('strong', null, money(sum, t)));
    if (!c.taxIncluded && tax > 0) sub.appendChild(el('span', 'digitalisimo-finder-cartbar__tax', ' + IVA al pagar'));
    info.appendChild(sub);
    if (cart.items.some((i) => dataOf(i, /^dominio a transferir$/i))) info.appendChild(el('p', 'digitalisimo-finder-cartbar__note', 'Antes de pagar te pediremos el código de transferencia de tu dominio.'));
    bar.appendChild(info);
    const pay = el('a', 'digitalisimo-finder-cartbar__pay', 'Pagar');
    pay.href = c.checkout || c.cart || '/';
    bar.appendChild(pay);
    bar.hidden = false;
    document.body.classList.add('digitalisimo-finder-has-bar');
    tune(bar, pay);
  }
  /** Los botones de lo que ya está en el carrito (por su nombre exacto) quedan como «Agregado ✓»; los demás, no. */
  function markAdded() {
    if (!cart || !Array.isArray(cart.items)) return;
    const inCart = cart.items.map((i) => `${i.id}|${domainOf(i)}`);
    document.querySelectorAll(`${ROOT} [data-add-product]`).forEach((b) => {
      if (inCart.includes(`${b.dataset.addProduct}|${b.dataset.addDomain || ''}`)) setAdded(b);
      else if (b.dataset.added) unsetAdded(b); // ya no está (por ejemplo, otro plan reemplazó a éste)
    });
  }
  const setAdded = (b) => {
    if (b.dataset.added) return;
    b.dataset.label = b.textContent;
    b.dataset.aria = b.getAttribute('aria-label') || '';
    b.textContent = 'Agregado ✓';
    b.setAttribute('aria-label', `${b.dataset.addDomain || b.dataset.name || 'Este artículo'} ya está en tu carrito`);
    b.setAttribute('aria-disabled', 'true');
    b.classList.add('is-added');
    b.dataset.added = '1';
  };
  const unsetAdded = (b) => {
    b.textContent = b.dataset.label || 'Agregar';
    if (b.dataset.aria) b.setAttribute('aria-label', b.dataset.aria); else b.removeAttribute('aria-label');
    b.removeAttribute('aria-disabled');
    b.classList.remove('is-added');
    delete b.dataset.added;
  };

  // ── Resultados ──────────────────────────────────────────────────────────────────────────────────────────────
  const WHY = { taken: 'está ocupado.', premium: 'es premium (precio especial) y no se vende aquí.', unknown: 'no se pudo consultar ahora.' };
  const STATE = { available: 'Disponible', taken: 'Ocupado', premium: 'Premium: precio especial, no se vende aquí', unknown: 'No pudimos consultarlo' };
  const shown = (r) => (r.display ? `${r.display} (${r.domain})` : r.domain);
  function addButton(label, productId, domain, aria, name) {
    const b = el('button', 'digitalisimo-finder__add', label);
    b.type = 'button';
    b.dataset.addProduct = String(productId);
    if (domain) b.dataset.addDomain = domain;
    if (name) b.dataset.name = name;
    if (aria) b.setAttribute('aria-label', aria);
    return b;
  }
  function priceLine(r, big) {
    const p = el('p', 'digitalisimo-finder__price');
    if (!r.price_text) return p;
    p.appendChild(el(big ? 'strong' : 'span', 'digitalisimo-finder__first', r.price_text));
    p.appendChild(el('span', 'digitalisimo-finder__first-label', ' el primer año'));
    if (r.renewal_text) p.appendChild(el('span', 'digitalisimo-finder__renewal', `Renueva a ${r.renewal_text} al año`));
    return p;
  }
  function transferBox(r) {
    if (!r.transfer) return null;
    const box = el('div', 'digitalisimo-finder__transfer');
    box.appendChild(el('p', 'digitalisimo-finder__transfer-text', `¿${r.domain} es tuyo? Transfiérelo con nosotros (${r.transfer.price_text}).`));
    box.appendChild(addButton('Transferirlo', r.transfer.product_id, r.domain, `Transferir ${r.domain}`));
    return box;
  }
  function renderRow(r, skipTransfer) {
    const li = el('li', `digitalisimo-finder__row is-${r.status}`);
    li.appendChild(el('span', 'digitalisimo-finder__domain', shown(r)));
    li.appendChild(el('span', 'digitalisimo-finder__state', STATE[r.status] || STATE.unknown));
    if (r.status === 'available') li.appendChild(priceLine(r, false));
    if (r.requirement) li.appendChild(el('span', 'digitalisimo-finder__req', r.requirement));
    if (r.status === 'available' && r.product_id) li.appendChild(addButton('Agregar', r.product_id, r.domain, `Agregar ${r.domain} al carrito`));
    const t = r.status === 'taken' && !skipTransfer ? transferBox(r) : null;
    if (t) li.appendChild(t);
    return li;
  }
  function list(out, title, rows, taken) {
    out.appendChild(el('h3', 'digitalisimo-finder__list-title', title));
    const ul = el('ul', 'digitalisimo-finder__list');
    rows.forEach((r) => ul.appendChild(renderRow(r, !!taken && r.domain === taken.domain)));
    out.appendChild(ul);
  }
  function render(root, d) {
    const out = root.querySelector('.digitalisimo-finder__results');
    out.textContent = '';
    root._together = Array.isArray(d.together) ? d.together : [];
    if (d.notice) out.appendChild(el('p', 'digitalisimo-finder__notice', d.notice));
    const m = d.main;
    const main = el('div', `digitalisimo-finder__main is-${m.status}`);
    if (d.instead_of) main.appendChild(el('p', 'digitalisimo-finder__instead', `${d.instead_of} ${WHY[d.reason] || WHY.unknown} Te sugerimos:`));
    main.appendChild(el('p', 'digitalisimo-finder__main-domain', shown(m)));
    main.appendChild(el('p', 'digitalisimo-finder__state', m.status === 'available' ? '¡Está disponible!' : (STATE[m.status] || STATE.unknown)));
    if (m.status === 'available') main.appendChild(priceLine(m, true));
    if (m.requirement) main.appendChild(el('p', 'digitalisimo-finder__req', m.requirement));
    if (m.status === 'available' && m.product_id) main.appendChild(addButton('Agregar', m.product_id, m.domain, `Agregar ${m.domain} al carrito`));
    out.appendChild(main);
    // Ocupado: «¿Es tuyo? Transfiérelo» junto al principal (en su fila ya no se repite).
    const taken = d.taken || (m.status === 'taken' ? m : null);
    const t = taken ? transferBox(taken) : null;
    if (t) out.appendChild(t);
    // Todo ocupado: variantes libres del nombre.
    if (Array.isArray(d.suggestions) && d.suggestions.length) list(out, 'Otras ideas libres', d.suggestions, null);
    if (Array.isArray(d.rows) && d.rows.length) list(out, 'Otras extensiones', d.rows, taken);
    if (d.partial) out.appendChild(el('p', 'digitalisimo-finder__notice', 'Algunas extensiones no se pudieron consultar. Intenta de nuevo en un minuto.'));
    markAdded();
  }
  /** «Se compran juntos»: debajo de la fila que se acaba de agregar (uno solo por buscador). */
  function together(root, after) {
    const items = root._together || [];
    const old = root.querySelector('.digitalisimo-finder__together');
    if (old) old.remove();
    if (!items.length) return;
    const box = el('div', 'digitalisimo-finder__together');
    box.setAttribute('role', 'group');
    box.setAttribute('aria-label', 'Se compran juntos');
    box.appendChild(el('p', 'digitalisimo-finder__together-title', 'Se compran juntos'));
    const ul = el('ul', 'digitalisimo-finder__together-list');
    items.forEach((p) => {
      const li = el('li', 'digitalisimo-finder__together-item');
      li.appendChild(el('span', 'digitalisimo-finder__together-name', p.name));
      if (p.description) li.appendChild(el('span', 'digitalisimo-finder__together-desc', p.description));
      li.appendChild(el('span', 'digitalisimo-finder__together-price', p.price_text));
      li.appendChild(addButton('Agregar', p.id, '', `Agregar ${p.name} al carrito`, p.name));
      ul.appendChild(li);
    });
    box.appendChild(ul);
    // Dentro de la fila (una <li>) o justo después del principal o de la transferencia.
    if (after.tagName === 'LI') after.appendChild(box); else after.insertAdjacentElement('afterend', box);
    markAdded();
  }

  // ── Búsqueda ────────────────────────────────────────────────────────────────────────────────────────────────
  async function search(root, q) {
    const status = root.querySelector('.digitalisimo-finder__status');
    const out = root.querySelector('.digitalisimo-finder__results');
    const button = root.querySelector('.digitalisimo-finder__submit');
    const value = String(q || '').trim();
    if (!value) { status.textContent = 'Escribe el nombre que quieres para tu dominio, por ejemplo minegocio.'; return; }
    // Número de petición: si llega una respuesta vieja después de otra búsqueda, se descarta.
    const seq = (root._seq = (root._seq || 0) + 1);
    status.textContent = 'Buscando…';
    out.setAttribute('aria-busy', 'true');
    if (button) button.disabled = true;
    try {
      const r = await fetch(c.endpoint || '/wp-json/digitalisimo-hosting/v1/finder', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Digitalisimo-Finder': '1' }, body: JSON.stringify({ q: value, nonce: c.nonce || '' }) });
      const d = await r.json().catch(() => null);
      if (seq !== root._seq) return;
      if (!r.ok || !d || !d.main) { status.textContent = plain((d && d.message) || 'No pudimos buscar en este momento. Intenta de nuevo.'); out.textContent = ''; return; }
      render(root, d);
      const m = d.main;
      if (d.instead_of) status.textContent = `${d.instead_of} ${WHY[d.reason] || WHY.unknown} ${m.domain} sí está disponible.`;
      else if (m.status === 'available') status.textContent = `${m.domain} está disponible.`;
      else status.textContent = `${m.domain}: ${STATE[m.status] || STATE.unknown}.`;
    } catch (e) {
      if (seq === root._seq) status.textContent = 'No pudimos buscar en este momento. Revisa tu conexión e intenta de nuevo.';
    } finally {
      if (seq === root._seq) { out.setAttribute('aria-busy', 'false'); if (button) button.disabled = false; }
    }
  }

  document.addEventListener('submit', (event) => {
    const form = event.target;
    const root = form && form.closest && form.closest(ROOT);
    if (!root || !form.classList.contains('digitalisimo-finder__form')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    search(root, form.querySelector('input[name="dominio"]').value);
  }, true);

  document.addEventListener('click', async (event) => {
    const b = event.target.closest && event.target.closest(`${ROOT} [data-add-product]`);
    if (!b) return;
    event.preventDefault();
    if (b.dataset.added || b.dataset.busy) return;
    const root = b.closest(ROOT);
    const status = root.querySelector('.digitalisimo-finder__status');
    b.dataset.busy = '1';
    b.setAttribute('aria-busy', 'true');
    const body = { id: Number(b.dataset.addProduct), quantity: 1 };
    if (b.dataset.addDomain) body.digitalisimo_domain = b.dataset.addDomain;
    try {
      cart = await addItem(body);
      setAdded(b);
      status.textContent = b.dataset.addDomain ? `${b.dataset.addDomain} se agregó a tu carrito.` : 'Se agregó a tu carrito.';
      announceAdded();
      renderBar();
      if (b.dataset.addDomain) together(root, b.closest('.digitalisimo-finder__row, .digitalisimo-finder__main, .digitalisimo-finder__transfer') || b);
      else markAdded();
      // Carrito clásico vacío: se recarga para mostrar el carrito real (el de bloques se actualiza solo con el evento).
      if (onCartPage() && !document.querySelector('.wp-block-woocommerce-cart')) window.location.reload();
    } catch (e) {
      status.textContent = e.message || 'No se pudo agregar. Intenta de nuevo.';
    } finally {
      delete b.dataset.busy;
      b.removeAttribute('aria-busy');
    }
  }, true);

  const init = () => {
    const roots = document.querySelectorAll(ROOT);
    if (!roots.length) return;
    roots.forEach((root) => tune(root, root.querySelector('.digitalisimo-finder__submit')));
    // Con artículos en el carrito (cookie de WooCommerce), la barra aparece desde el principio.
    if (/(^|;\s*)woocommerce_items_in_cart=1/.test(document.cookie)) loadCart();
    // ?dominio= (sin JavaScript el formulario recarga con lo escrito; también sirve como enlace compartible).
    let linked = '';
    try { linked = new URL(window.location.href).searchParams.get('dominio') || ''; } catch (e) { linked = ''; }
    roots.forEach((root) => {
      const i = root.querySelector('input[name="dominio"]');
      if (!i) return;
      if (!i.value.trim() && linked) i.value = linked.slice(0, 253); // el elemento atómico no lo rellena en el servidor
      if (i.value.trim()) search(root, i.value);
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
