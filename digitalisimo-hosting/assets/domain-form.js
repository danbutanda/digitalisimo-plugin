/*
 * Buscador de dominios de DIGITALÍSIMO Hosting.
 * - Formulario de DIGITALÍSIMO Elements, el clásico (.elementor-form) y el nuevo del editor atómico (e-form): un campo
 *   «dominio» (el configurado o uno llamado dominio/domain) y, si lo hay, «extensión». Se toma el formulario si en la
 *   página está [resultado_dominio] o si lleva la clase digitalisimo-domain-form (o data-digitalisimo-domain-search).
 *   El envío se intercepta en la fase de captura, antes que el manejador del formulario (jQuery o Alpine): así no se
 *   envía como un formulario de contacto.
 * - [digitalisimo_domain_search]: un bloque <div> (no un <form>: funciona también dentro de un formulario atómico).
 * Las extensiones son SIEMPRE las de «Productos por extensión» (window.digitalisimoHosting.products): una lista fija del
 * formulario se sustituye. Todo lo que viene del visitante o del servidor se pinta con textContent.
 */
(() => {
  const c = window.digitalisimoHosting || {};
  const products = c.products && typeof c.products === 'object' ? c.products : {};
  const exts = Object.keys(products);
  const DOMAIN_NAMES = ['dominio', 'domain', 'domain_name', 'digitalisimo_domain'];
  const EXT_NAMES = ['extension', 'extensión', 'ext', 'tld'];
  const RESULT = '.digitalisimo-hosting-result';
  const MARK = '.digitalisimo-domain-form, [data-digitalisimo-domain-search]';

  const key = (name) => { const n = String(name || '').toLowerCase(); const m = n.match(/\[([^\]]+)\]$/); return m ? m[1] : n; };
  const named = (el, configured, list) => !!(el && el.name && (el.name === configured || list.includes(key(el.name))));
  // Los campos del bloque [digitalisimo_domain_search] son suyos, aunque esté dentro de otro formulario.
  const fields = (root) => Array.from(root.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')).filter((el) => !el.closest('.digitalisimo-domain-search'));
  const domainField = (root) => fields(root).find((el) => named(el, c.domainField, DOMAIN_NAMES));
  const extField = (root) => fields(root).find((el) => named(el, c.extensionField, EXT_NAMES));
  const matches = (el, sel) => { try { return !!sel && el.matches(sel); } catch (e) { return false; } };

  /** ¿Es un formulario del buscador? */
  const isSearchForm = (form) => {
    if (!form || form.tagName !== 'FORM' || form.closest('.digitalisimo-domain-search') || !domainField(form)) return false;
    return matches(form, MARK) || !!document.querySelector(RESULT) && (matches(form, c.selector) || matches(form, '.elementor-form, form[data-element_type="e-form"], form.e-con') || !!form.querySelector(RESULT));
  };

  /** Dónde se pinta el resultado: el de dentro, el más cercano hacia arriba o uno nuevo tras el formulario. */
  const resultFor = (form) => {
    const inside = form.querySelector(RESULT);
    if (inside) return inside;
    // Hacia arriba, mientras no se llegue a una parte de la página con otro buscador (su resultado es suyo).
    for (let el = form.parentElement; el && el !== document.documentElement; el = el.parentElement) {
      if (Array.from(el.querySelectorAll('form')).some((f) => f !== form && isSearchForm(f))) break;
      const r = el.querySelector(RESULT);
      if (r) return r;
    }
    const div = document.createElement('div');
    div.className = RESULT.slice(1);
    div.setAttribute('aria-live', 'polite');
    form.insertAdjacentElement('afterend', div);
    return div;
  };

  /** Las opciones de extensión son las configuradas (sin listas fijas). */
  const fillExtensions = (select) => {
    if (!select || select.tagName !== 'SELECT' || select.dataset.digitalisimoExt) return;
    const current = String(select.value || '').toLowerCase();
    select.textContent = '';
    exts.forEach((ext) => { const o = document.createElement('option'); o.value = ext; o.textContent = ext; if (ext === (current.startsWith('.') ? current : `.${current}`)) o.selected = true; select.appendChild(o); });
    select.dataset.digitalisimoExt = '1';
  };

  const clean = (v) => String(v || '').trim().toLowerCase().replace(/^[a-z][a-z0-9+.-]*:\/\//, '').replace(/[/?#].*$/, '').replace(/^www\./, '').replace(/\.$/, '');
  /** Dominios a consultar: el escrito con su extensión, el nombre con la elegida o con todas las configuradas. */
  const candidates = (name, ext) => {
    const n = clean(name);
    if (!n) return [];
    const typed = exts.filter((e) => n.endsWith(e) && n.length > e.length).sort((a, b) => b.length - a.length)[0];
    if (typed) return [n];
    const e = clean(ext);
    const chosen = e ? (e.startsWith('.') ? e : `.${e}`) : '';
    if (chosen && exts.includes(chosen)) return [n + chosen];
    return exts.map((x) => n.replace(/\..*$/, '') + x);
  };
  const extOf = (domain) => exts.filter((e) => domain.endsWith(e)).sort((a, b) => b.length - a.length)[0] || '';

  /** Ruta de la REST de Hosting, también con enlaces permanentes simples (`?rest_route=/digitalisimo-hosting/v1/`). */
  const restUrl = (route, params) => {
    const u = new URL(c.endpoint || '/wp-json/digitalisimo-hosting/v1/', window.location.href);
    const base = u.searchParams.get('rest_route');
    if (base !== null) u.searchParams.set('rest_route', base.replace(/\/?$/, '/') + route);
    else u.pathname = u.pathname.replace(/\/?$/, '/') + route;
    Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
    return u.toString();
  };
  const cartUrl = (id, domain) => {
    const u = new URL(c.cart || '/', window.location.href);
    u.searchParams.set('add-to-cart', String(id));
    u.searchParams.set('domain_name', domain);
    return u.toString();
  };
  const row = (out, domain, text, link) => {
    const p = document.createElement('p');
    p.className = 'digitalisimo-hosting-option';
    const s = document.createElement('strong'); s.textContent = domain; p.appendChild(s);
    p.appendChild(document.createTextNode(` ${text}`));
    if (link) { const a = document.createElement('a'); a.className = 'button'; a.href = link; a.textContent = 'Registrar'; p.appendChild(document.createTextNode(' ')); p.appendChild(a); }
    out.appendChild(p);
  };

  async function search(out, name, ext) {
    const list = candidates(name, ext);
    out.textContent = '';
    if (!exts.length) { out.textContent = 'Por ahora no hay dominios a la venta.'; return; }
    if (!list.length || !/^[a-z0-9-]+(\.[a-z0-9-]+)*$/.test(clean(name))) { out.textContent = 'Escribe un dominio sin espacios, acentos ni caracteres especiales.'; return; }
    out.textContent = list.length > 1 ? 'Verificando…' : `Verificando ${list[0]}…`;
    const results = await Promise.all(list.map(async (domain) => {
      try {
        const r = await fetch(restUrl('check-domain', { domain }), { credentials: 'same-origin' });
        const d = await r.json();
        if (!r.ok) return { domain, error: (d && d.message) || 'No fue posible consultar el dominio.' };
        return { domain, ...d };
      } catch (e) { return { domain, error: 'No fue posible consultar el dominio.' }; }
    }));
    out.textContent = '';
    results.forEach((r) => {
      const id = r.product_id || products[extOf(r.domain)];
      if (r.error) row(out, r.domain, `· ${r.error}`);
      else if (!r.available) row(out, r.domain, 'no está disponible.');
      else if (r.premium) row(out, r.domain, 'es premium y no se vende aquí.');
      else if (!id) row(out, r.domain, 'está disponible, pero su extensión no se vende aquí.');
      else row(out, r.domain, `está disponible${r.price ? ` · ${r.currency || '$'}${r.price}` : ''}`, cartUrl(id, r.domain));
    });
  }

  // Formularios de Elements (clásico y atómico): captura, antes que su propio manejador.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!isSearchForm(form)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const ext = extField(form);
    search(resultFor(form), domainField(form).value, ext ? ext.value : '');
  }, true);

  // [digitalisimo_domain_search]: botón o Enter en el campo.
  const widget = (el) => el && el.closest && el.closest('.digitalisimo-domain-search');
  const runWidget = (w) => {
    const input = w.querySelector('[name="domain"]');
    const select = w.querySelector('[name="extension"]');
    search(w.querySelector('.digitalisimo-domain-result'), input ? input.value : '', select ? select.value : '');
  };
  document.addEventListener('click', (event) => {
    const button = event.target.closest && event.target.closest('.digitalisimo-domain-search button');
    if (!button) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    runWidget(widget(button));
  }, true);
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || !widget(event.target) || event.target.tagName !== 'INPUT') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    runWidget(widget(event.target));
  }, true);
  // Un <form class="digitalisimo-domain-search"> de una página guardada antes (caché): mismo comportamiento.
  document.addEventListener('submit', (event) => {
    if (!event.target.matches || !event.target.matches('form.digitalisimo-domain-search')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    runWidget(event.target);
  }, true);

  const init = () => {
    document.querySelectorAll('form').forEach((form) => { if (isSearchForm(form)) fillExtensions(extField(form)); });
    document.querySelectorAll('.digitalisimo-domain-search [name="extension"]').forEach(fillExtensions);
    // Panel «Tu dominio»: el campo del dominio propio sólo con «Uno que ya tengo».
    document.querySelectorAll('.digitalisimo-hosting-domain-mode').forEach((set) => {
      const own = set.querySelector('.digitalisimo-hosting-own');
      const sync = () => { const v = set.querySelector('input[type=radio]:checked'); if (own) own.hidden = !v || v.value !== 'own'; };
      set.addEventListener('change', sync);
      sync();
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
