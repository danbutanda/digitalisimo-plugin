// Auditoría de marca en Chrome: recorre administración, editor y frontal con el núcleo incluido y anota:
// 1. Cada aparición de «Elementor» que vea un usuario (texto, atributos accesibles, títulos, imágenes y logotipos) y los
//    colores de su paleta. Las clases CSS, los identificadores, las URL internas y los datos no cuentan.
// 2. Todo lo que apunte a un plugin de origen (vendors.json): peticiones del navegador, navegaciones y redirecciones,
//    ventanas nuevas, URL en el DOM (atributos, data-*, estilos, iframes, vídeos, valores de campos, texto), contenido
//    latente (scripts en línea, plantillas y datos localizados), peticiones del servidor, redirecciones y correos
//    (http.jsonl de probe-http.php) y, como red de seguridad, el netlog de Chrome.
// La clasificación es por host (vendors.cjs): los identificadores internos nunca cuentan. Chrome resuelve todo host
// externo a NOTFOUND, así que nada sale a internet; cada intento queda anotado con su pantalla.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { load, classify, candidateHosts } = require('./vendors.cjs');

const base = process.argv[2] || 'http://127.0.0.1';
const OUT = process.env.OUT || '/tmp';
const LOCALES = (process.env.LOCALES || 'es_MX,en_US').split(',');
const SCOPE = process.env.SCOPE === 'quick' ? 'quick' : 'full';
const ORIGINAL = process.env.ORIGINAL === '1';
const LATENT_FAIL = process.env.LATENT === 'fail';
const BRAND = /elementor/i;
const origin = new URL(base).origin;
const V = fs.existsSync(`${OUT}/vendors.json`) ? JSON.parse(fs.readFileSync(`${OUT}/vendors.json`, 'utf8')) : load(path.resolve(__dirname, '../../..'));
const ALLOW = (JSON.parse(fs.readFileSync(path.join(__dirname, 'allow.json'), 'utf8')).excepciones || []).map((a) => ({
  ...a, used: 0, reUrl: new RegExp(a.url), reScreen: a.pantalla ? new RegExp(a.pantalla) : null, reSource: a.origen ? new RegExp(a.origen) : null,
}));
const IDS = fs.existsSync(`${OUT}/ids.json`) ? JSON.parse(fs.readFileSync(`${OUT}/ids.json`, 'utf8')) : {};
// STATE=kit (etapa K): la sesión de «wp digitalisimo-elements kit import» y lo que importó (setup.php, fase kit).
const STATE = process.env.STATE || 'nuevo';
const KIT = STATE === 'kit' && fs.existsSync(`${OUT}/kit.json`) ? JSON.parse(fs.readFileSync(`${OUT}/kit.json`, 'utf8')) : null;
// Pantallas donde nombrar (sin enlace) los plugins de origen es la función: Migración dice cuáles desactiva.
const TEXT_EXEMPT = /[?&]page=digitalisimo-ep-migration\b/;
const CFG = {
  cand: candidateHosts(V),
  textHosts: [...V.hosts, ...(V.future || [])].map((h) => h.replace(/\./g, '\\.')).join('|'),
  originalPlugins: V.originalPlugins || [],
  originalSlugs: V.originalSlugs || [],
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const short = (u) => String(u || '').replace(origin, '');
const isContent = (u) => {
  try { const x = new URL(u); return x.origin === origin && !/^\/wp-(admin|login)/.test(x.pathname); } catch (e) { return false; }
};

const state = { locale: '', screen: 'inicio de sesión' };
const hits = [];
const hitKeys = new Set();
const notes = [];
function check(source, raw, ctx = {}) {
  const c = classify(V, raw, { content: !!ctx.content, caller: ctx.caller || '' });
  if (!c) return null;
  const h = {
    source, kind: ctx.kind || '', url: String(raw).replace(/\\\//g, '/').slice(0, 400), host: c.host, domain: c.domain, why: c.why,
    locale: ctx.locale !== undefined ? ctx.locale : state.locale, screen: ctx.screen || state.screen, where: ctx.where || '', frame: ctx.frame || '',
    shown: ctx.shown !== false, excluded: ctx.excluded || '', tag: ctx.tag || '', latent: !!ctx.latent, extra: ctx.extra || '',
  };
  const key = [h.source, h.kind, h.url, h.locale, h.screen, h.where, h.excluded].join('|');
  if (hitKeys.has(key)) return h;
  hitKeys.add(key);
  hits.push(h);
  return h;
}

// Se ejecuta dentro de cada documento (y de sus iframes del mismo origen).
function collect(cfg) {
  const re = /elementor/i;
  const out = [];
  // Filas del plugin original instalado: sus cabeceras se muestran tal cual (regla del proyecto). La nota propia no.
  const excl = [];
  for (const p of cfg.originalPlugins) {
    document.querySelectorAll(`tr[data-plugin="${p}"]:not(.digitalisimo-plugin-note)`).forEach((e) => excl.push(e));
    document.querySelectorAll(`#update-plugins-table input[value="${p}"]`).forEach((i) => i.closest('tr') && excl.push(i.closest('tr')));
  }
  document.querySelectorAll('#TB_window').forEach((w) => { const f = w.querySelector('iframe'); if (f && new RegExp(`[?&]plugin=(${cfg.originalSlugs.join('|')})(&|$)`).test(f.src)) excl.push(w); });
  const isExcl = (el) => excl.some((x) => x.contains(el));
  // Texto que nombra los plugins de origen como función (sin enlaces): la fila de DIGITALÍSIMO Migración en Plugins y
  // la nota propia bajo el plugin original instalado. Sus URL sí se revisan (collectUrls no los exime).
  const exempt = [...document.querySelectorAll('tr[data-plugin^="digitalisimo-migration/"], tr.digitalisimo-plugin-note')];
  const isExempt = (el) => exempt.some((x) => x.contains(el));
  const shown = (el) => {
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
    }
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };
  const where = (el) => {
    const parts = [];
    for (let n = el; n && n.nodeType === 1 && parts.length < 4; n = n.parentElement) {
      parts.unshift(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + (typeof n.className === 'string' && n.className.trim() ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    }
    return parts.join(' > ');
  };
  if (re.test(document.title)) out.push({ kind: 'título', text: document.title, shown: true, where: 'title' });
  const walker = document.createTreeWalker(document.body || document.documentElement, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode;
    const text = (node.nodeValue || '').replace(/\s+/g, ' ').trim();
    const parent = node.parentElement;
    if (!text || !re.test(text) || !parent || ['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'TEXTAREA', 'CODE', 'PRE'].includes(parent.tagName) || isExcl(parent)) continue;
    // Contenido técnico de diagnóstico (registro de errores con rutas y clases PHP): son identificadores, no marca.
    if (/\/wp-content\/|\bElementor\\[A-Z]|\[(notice|error|warning|deprecated|info) X \d+\]/.test(text)) continue;
    out.push({ kind: 'texto', text: text.slice(0, 200), shown: shown(parent), where: where(parent), exempt: isExempt(parent) });
  }
  document.querySelectorAll('[title],[alt],[aria-label],[placeholder],[data-tooltip],input[type=submit],input[type=button],option').forEach((el) => {
    if (isExcl(el)) return;
    for (const attr of ['title', 'alt', 'aria-label', 'placeholder', 'data-tooltip', 'value']) {
      const value = el.getAttribute(attr);
      if (!value || !re.test(value)) continue;
      if (attr === 'value' && !(el.tagName === 'INPUT' && ['submit', 'button'].includes(el.type))) continue;
      out.push({ kind: 'atributo ' + attr, text: value.slice(0, 200), shown: shown(el), where: where(el) });
    }
  });
  // Logotipos: imágenes de los recursos de marca y el glifo del logotipo en la fuente de iconos.
  document.querySelectorAll('img, [style*="background-image"]').forEach((el) => {
    if (isExcl(el)) return;
    const src = el.tagName === 'IMG' ? el.currentSrc || el.src : getComputedStyle(el).backgroundImage;
    if (src && /elementor[^/]*logo|\/elementor\/assets\/images\/logo|(^|[^a-z])e-logo|elementor-icon/i.test(src)) out.push({ kind: 'imagen', text: src.slice(0, 200), shown: shown(el), where: where(el) });
  });
  // El glifo del logotipo (,  de la fuente eicons) cuenta sólo si se sigue dibujando.
  document.querySelectorAll('[class*="eicon-elementor"], #adminmenu .wp-menu-image, i[class*="eicon"]').forEach((el) => {
    const before = getComputedStyle(el, '::before');
    if (/eicons/i.test(before.fontFamily) && /[]/.test(before.content || '')) out.push({ kind: 'logotipo', text: (typeof el.className === 'string' ? el.className : el.tagName).slice(0, 160), shown: shown(el), where: where(el) });
  });
  document.querySelectorAll('.elementor-loader-boxes, [class*="elementor-logo"], svg[class*="logo" i], [data-testid*="logo" i]').forEach((el) => {
    out.push({ kind: 'logotipo', text: (typeof el.className === 'string' ? el.className : el.getAttribute('class') || el.tagName).slice(0, 160), shown: shown(el), where: where(el) });
  });
  // Icono del menú: el núcleo lo dibuja con una máscara de su logotipo sobre el pseudo-elemento.
  document.querySelectorAll('#adminmenu li[id^="toplevel_page_elementor"] > a .wp-menu-image').forEach((el) => {
    const before = getComputedStyle(el, '::before');
    const mask = before.webkitMaskImage || before.maskImage || 'none';
    if (mask !== 'none' || !/icono\.png/.test(before.backgroundImage)) out.push({ kind: 'logotipo', text: 'icono del menú sin símbolo propio', shown: shown(el), where: where(el) });
  });
  // Logotipos SVG de las barras superiores: deben llevar el símbolo propio (si la estructura cambia, se avisa).
  for (const sel of ['#editor-one-top-bar header svg', '#elementor-editor-wrapper-v2 header button svg']) {
    const svg = document.querySelector(sel);
    if (svg && !/icono\.png/.test(getComputedStyle(svg).backgroundImage)) out.push({ kind: 'logotipo', text: 'SVG sin símbolo propio: ' + sel, shown: shown(svg), where: where(svg) });
  }
  // Paleta: ningún elemento visible de la administración o del editor se pinta con un color de Elementor (texto, fondo,
  // degradados, bordes, contorno, sombras, relleno y trazo de SVG, también en ::before y ::after). El frontal no cuenta:
  // sus colores son el diseño del usuario, también en la vista previa de cualquier documento (página, popup, cabecera).
  if (/^\/wp-admin\//.test(location.pathname)) {
    const palette = [[208, 4, 212], [243, 186, 253], [192, 11, 185], [147, 0, 63], [240, 171, 252], [146, 0, 59], [147, 0, 60], [230, 10, 234], [231, 60, 246], [29, 221, 191], [94, 234, 212], [88, 208, 245]];
    const near = (r, g, b) => palette.some(([x, y, z]) => Math.hypot(r - x, g - y, b - z) < 40);
    const colors = (value) => [...String(value || '').matchAll(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/g)].filter((m) => m[4] === undefined || parseFloat(m[4]) > 0.05).map((m) => [+m[1], +m[2], +m[3]]);
    const props = ['color', 'background-color', 'background-image', 'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color', 'outline-color', 'box-shadow', 'fill', 'stroke', 'text-decoration-color'];
    const seen = new Set();
    document.querySelectorAll('body *').forEach((el) => {
      for (const pseudo of [null, '::before', '::after']) {
        const s = getComputedStyle(el, pseudo);
        if (pseudo && (s.content === 'none' || s.content === 'normal')) continue;
        for (const prop of props) {
          // Un borde de 0 o un contorno inexistente no se ven.
          if (/^border-(\w+)-color$/.test(prop) && parseFloat(s.getPropertyValue(prop.replace('-color', '-width'))) === 0) continue;
          if (prop === 'outline-color' && (s.outlineStyle === 'none' || parseFloat(s.outlineWidth) === 0)) continue;
          if ((prop === 'fill' || prop === 'stroke') && !(el instanceof SVGElement)) continue;
          for (const [r, g, b] of colors(s.getPropertyValue(prop))) {
            if (!near(r, g, b)) continue;
            const key = where(el) + (pseudo || '') + ' ' + prop;
            if (seen.has(key)) continue;
            seen.add(key);
            out.push({ kind: 'color de Elementor', text: `${prop}: rgb(${r}, ${g}, ${b})${pseudo ? ' en ' + pseudo : ''}`, shown: shown(el), where: where(el) });
          }
        }
      }
    });
  }
  return out;
}

// URL hacia hosts candidatos en el documento: atributos, data-*, estilos (también calculados, con ::before/::after),
// valores de campos, texto, shadow DOM abierto, <template>, scripts en línea y datos globales del constructor.
// Devuelve URL externas sin clasificar: la clasificación exacta se hace en Node con vendors.cjs.
function collectUrls(cfg) {
  const cand = new RegExp(cfg.cand, 'i');
  const urlRe = /(?:https?:|wss?:)?\/\/[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(?::\d+)?(?:[/?#][^\s"'<>()\\`]*)?/gi;
  const textRe = new RegExp(`(?:^|[^a-z0-9.-])((?:[a-z0-9-]+\\.)*(?:${cfg.textHosts}))(?![a-z0-9-])`, 'gi');
  const out = [];
  const seen = new Set();
  const excl = [];
  for (const p of cfg.originalPlugins) {
    document.querySelectorAll(`tr[data-plugin="${p}"]:not(.digitalisimo-plugin-note)`).forEach((e) => excl.push(e));
    document.querySelectorAll(`#update-plugins-table input[value="${p}"]`).forEach((i) => i.closest('tr') && excl.push(i.closest('tr')));
  }
  document.querySelectorAll('#TB_window').forEach((w) => { const f = w.querySelector('iframe'); if (f && new RegExp(`[?&]plugin=(${cfg.originalSlugs.join('|')})(&|$)`).test(f.src)) excl.push(w); });
  const isExcl = (el) => excl.some((x) => x.contains(el));
  const shownCache = new Map();
  const shown = (el) => {
    if (!el || el.nodeType !== 1 || !el.isConnected) return false;
    if (shownCache.has(el)) return shownCache.get(el);
    let v = true;
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') { v = false; break; }
    }
    if (v) { const r = el.getBoundingClientRect(); v = r.width > 0 && r.height > 0; }
    shownCache.set(el, v);
    return v;
  };
  const where = (el) => {
    const parts = [];
    for (let n = el; n && n.nodeType === 1 && parts.length < 4; n = n.parentElement) {
      parts.unshift(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + (typeof n.className === 'string' && n.className.trim() ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    }
    return parts.join(' > ');
  };
  const add = (raw, kind, el, extra = {}) => {
    let s = String(raw || '').replace(/\\\//g, '/').replace(/&amp;/g, '&').trim();
    if (!s || /^(data|blob|javascript|mailto|tel|about|#)/i.test(s)) return;
    let u;
    try { u = new URL(s.startsWith('//') ? 'https:' + s : s, document.baseURI); } catch (e) { return; }
    if (!/^(https?|wss?):$/.test(u.protocol) || u.origin === location.origin || !cand.test(u.hostname)) return;
    const key = kind + ' ' + u.href + ' ' + (extra.script || '');
    if (seen.has(key)) return;
    seen.add(key);
    const elem = el && el.nodeType === 1 ? el : null;
    out.push({ kind, url: u.href.slice(0, 400), where: elem ? where(elem) : extra.script || '', tag: elem ? elem.tagName.toLowerCase() : '', shown: extra.latent ? false : shown(elem), excluded: elem && isExcl(elem) ? 'fila del plugin original' : '', latent: !!extra.latent, script: extra.script || '' });
  };
  const scan = (text, kind, el, extra) => { for (const m of String(text || '').replace(/\\\//g, '/').matchAll(urlRe)) add(m[0], kind, el, extra); };
  const URL_ATTRS = ['href', 'src', 'action', 'formaction', 'poster', 'data', 'cite', 'ping', 'background', 'xlink:href', 'manifest', 'codebase', 'longdesc'];
  const STYLE_PROPS = ['background-image', 'mask-image', '-webkit-mask-image', 'list-style-image', 'border-image-source', 'content', 'cursor'];
  const visit = (root, inTemplate) => {
    const all = root.querySelectorAll('*');
    for (const el of all) {
      if (el.tagName === 'SCRIPT' && !el.src) continue;
      const kindOf = (n) => (inTemplate ? 'plantilla · ' : '') + 'atributo ' + n;
      for (const a of el.attributes) {
        const n = a.name.toLowerCase();
        const v = a.value;
        if (!v) continue;
        if (URL_ATTRS.includes(n)) (n === 'ping' ? v.split(/\s+/) : [v]).forEach((x) => add(x, kindOf(n), el));
        else if (n === 'srcset' || n === 'imagesrcset') v.split(/,\s+/).forEach((x) => add(x.trim().split(/\s+/)[0], kindOf(n), el));
        else if (/^(data-|on)|^(style|content|value|srcdoc|title|alt|aria-label|placeholder)$/.test(n)) scan(v, kindOf(n), el);
      }
      if (!inTemplate && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.value && el.value !== el.getAttribute('value')) scan(el.value, 'valor del campo', el);
      if (!inTemplate && el.isConnected) {
        for (const pseudo of [null, '::before', '::after']) {
          const s = getComputedStyle(el, pseudo);
          if (pseudo && (s.content === 'none' || s.content === 'normal')) continue;
          for (const prop of STYLE_PROPS) {
            const value = s.getPropertyValue(prop);
            if (value && value.includes('url(')) for (const m of value.matchAll(/url\(\s*(["']?)(.*?)\1\s*\)/g)) add(m[2], 'estilo ' + prop + (pseudo || ''), el);
          }
        }
      }
      if (el.shadowRoot) visit(el.shadowRoot, inTemplate);
      if (el.tagName === 'TEMPLATE' && el.content) visit(el.content, true);
    }
  };
  visit(document, false);
  // Texto con un dominio del proveedor (visible u oculto), fuera de scripts y estilos.
  const walker = document.createTreeWalker(document.body || document.documentElement, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode;
    const parent = node.parentElement;
    if (!parent || ['SCRIPT', 'STYLE', 'NOSCRIPT'].includes(parent.tagName)) continue;
    for (const m of String(node.nodeValue || '').matchAll(textRe)) add('https://' + m[1] + '/', 'texto', parent);
  }
  // Contenido latente: scripts en línea (datos localizados: {handle}-js-extra/-before/-after) y plantillas.
  document.querySelectorAll('script:not([src])').forEach((s) => {
    const type = (s.type || '').toLowerCase();
    const kind = !type || /javascript|module/.test(type) ? 'script en línea' : 'plantilla ' + type;
    scan(s.textContent, kind, null, { latent: true, script: 'script#' + (s.id || '(sin id)') });
  });
  // Datos globales del constructor creados en tiempo de ejecución (sólo objetos planos: no se recorren instancias).
  let budget = 150000;
  const walk = (value, trail, depth, seenObj) => {
    if (budget-- <= 0 || depth > 14 || value === null) return;
    if (typeof value === 'string') { if (value.includes('//')) scan(value, 'dato global', null, { latent: true, script: trail }); return; }
    if (typeof value !== 'object' || seenObj.has(value)) return;
    const proto = Object.getPrototypeOf(value);
    if (!Array.isArray(value) && proto !== Object.prototype && proto !== null) return;
    seenObj.add(value);
    for (const k of Object.keys(value).slice(0, 5000)) {
      let v;
      try { v = value[k]; } catch (e) { continue; }
      walk(v, trail + (Array.isArray(value) ? `[${k}]` : '.' + k), depth + 1, seenObj);
    }
  };
  const seenObj = new WeakSet();
  for (const k of Object.getOwnPropertyNames(window)) {
    if (!/elementor|^e[A-Z]\w*(Config|Data|Settings)$|editor.?one|^wpOne/i.test(k)) continue;
    let v;
    try { v = window[k]; } catch (e) { continue; }
    if (v && typeof v === 'object') walk(v, 'window.' + k, 0, seenObj);
  }
  return out;
}

const brand = [];
async function audit(page, label) {
  const { locale } = state;
  for (const frame of page.frames()) {
    const fu = frame.url();
    const parent = frame.parentFrame();
    const own = fu.startsWith(origin);
    const blank = /^about:(blank|srcdoc)/.test(fu) && parent && parent.url().startsWith(origin);
    const sub = frame !== page.mainFrame();
    if (own) {
      let items = [];
      try { items = await frame.evaluate(collect, CFG); } catch (e) { /* marco que se fue */ }
      // La pantalla de Migración nombra, sin enlace, los plugins que desactiva (AGENTS.md): su texto queda exento también
      // en brand-audit.json, no sólo en el recuento (TEXT_EXEMPT).
      for (const item of items) brand.push({ locale, screen: label, frame: sub ? 'iframe' : '', ...item, ...(item.kind === 'texto' && TEXT_EXEMPT.test(label) ? { exempt: true } : {}) });
    }
    if (own || blank) {
      let urls = [];
      try { urls = await frame.evaluate(collectUrls, CFG); } catch (e) { continue; }
      const content = isContent(own ? fu : parent.url());
      const inScript = new Set(urls.filter((u) => u.latent && u.kind !== 'dato global').map((u) => u.url));
      for (const u of urls) {
        // Un dato global que ya está en un script en línea es el mismo dato: se anota una vez, con el id del script.
        if (u.kind === 'dato global' && inScript.has(u.url)) continue;
        // En la administración sólo cuenta un vídeo que se carga (iframe, video, embed); una URL de YouTube o Vimeo en
        // una plantilla, un dato o un atributo es la función de los widgets de vídeo y sólo falla si es un vídeo del
        // proveedor.
        const media = /^(iframe|video|source|track|embed|object)$/.test(u.tag) && !u.latent;
        check(u.latent ? 'latente' : 'dom', u.url, { content: content || !media, kind: u.kind, where: u.where, tag: u.tag, shown: u.shown, excluded: u.excluded, latent: u.latent, frame: sub ? short(fu) : '', screen: label });
      }
    } else if (sub && fu && !/^(about|chrome-error|data|blob|chrome):/.test(fu)) {
      check('dom', fu, { content: isContent(parent ? parent.url() : ''), kind: 'iframe (documento cargado)', tag: 'iframe', screen: label, frame: short(fu) });
    }
  }
  const opened = await page.evaluate(() => (window.__digiOpened || []).splice(0)).catch(() => []);
  for (const u of opened) check('ventana', u, { kind: 'window.open', screen: label });
  const file = `${OUT}/${locale}-${label.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').slice(0, 80)}.png`;
  await page.screenshot({ path: file, fullPage: false }).catch(() => {});
  // Marcado de las barras superiores (administración y editor), donde el logotipo es un SVG: sirve para revisar.
  const bars = await page.evaluate(() => ['#editor-one-top-bar', '#elementor-editor-wrapper-v2 header', '.eps-app__header'].map((s) => document.querySelector(s)).filter(Boolean).map((el) => el.outerHTML.slice(0, 6000)).join('\n\n')).catch(() => '');
  if (bars) fs.writeFileSync(file.replace(/\.png$/, '.bars.html'), bars);
}

// Navegador: cada petición (también de iframes y ventanas nuevas) se anota con su pantalla. Ninguna sale: Chrome
// resuelve todo host externo a NOTFOUND (--host-resolver-rules).
const watched = new WeakSet();
function initiatorOf(r) {
  try {
    const i = r.initiator();
    if (!i) return '';
    const urls = [i.url, ...((i.stack && i.stack.callFrames) || []).map((f) => f.url)].filter(Boolean);
    return [...new Set(urls.map(short))].slice(0, 3).join(' ← ');
  } catch (e) { return ''; }
}
async function watch(p) {
  if (!p || watched.has(p)) return;
  watched.add(p);
  await p.evaluateOnNewDocument(() => {
    try {
      window.__digiOpened = [];
      const open = window.open;
      window.open = function (u, ...rest) { try { window.__digiOpened.push(String(u || '')); } catch (e) { /* nada */ } return open.call(this, u, ...rest); };
    } catch (e) { /* nada */ }
  }).catch(() => {});
  p.on('request', (r) => {
    const url = r.url();
    if (url.startsWith(origin) || !/^(https?|wss?):/i.test(url)) return;
    try {
      const nav = r.isNavigationRequest();
      const f = r.frame();
      const ctxFrame = nav && f && f.parentFrame() ? f.parentFrame() : f;
      const fu = ctxFrame ? ctxFrame.url() : '';
      const chain = r.redirectChain();
      const from = chain.length ? chain[chain.length - 1].url() : '';
      const top = nav && f && !f.parentFrame();
      const source = nav && from.startsWith(origin) ? 'redirección' : top ? 'navegación' : 'red';
      const caller = initiatorOf(r);
      check(source, url, { content: isContent(fu), caller, kind: r.resourceType() + (nav && !top ? ' (iframe)' : ''), where: from ? 'desde ' + short(from) : caller ? 'iniciador ' + caller : '', frame: short(fu) });
    } catch (e) { /* petición de un marco que ya no existe */ }
  });
  p.on('popup', (w) => { if (w) { check('ventana', w.url(), { kind: 'ventana nueva' }); watch(w).catch(() => {}); } });
}

async function cookieHeader(page, screen) {
  let cookies = [];
  try { cookies = await page.cookies(`${base}/wp-admin/`); } catch (e) { cookies = []; }
  return cookies.filter((c) => c.name !== 'digi_screen').map((c) => `${c.name}=${c.value}`).concat(`digi_screen=${encodeURIComponent(screen)}`).join('; ');
}

// Enlaces propios que redirigen al proveedor (go_*, upgrade, connect, authorize) y páginas admin.php?page= no
// visitadas: se piden sin seguir la redirección y se clasifica el Location. Nunca admin-post.php ni acciones.
const probed = new Set();
async function probeLinks(page, label, visitedUrls, known) {
  const links = known || await page.$$eval('a[href]', (as) => [...new Set(as.map((a) => a.href))]).catch(() => []);
  const risky = /[?&]page=(go_[a-z_]+|elementor-one-upgrade|elementor-connect)\b|[?&]action=authorize\b/;
  const targets = links.filter((h) => h.startsWith(origin + '/wp-admin/') && !/admin-post\.php|admin-ajax\.php/.test(h) && (risky.test(h) || (/admin\.php\?page=/.test(h) && !/[?&](action|_wpnonce)=/.test(h) && !visitedUrls.has(h.split('#')[0]))));
  for (const link of targets) {
    const key = link.split('#')[0];
    if (probed.has(key)) continue;
    probed.add(key);
    let url = key;
    for (let hop = 0; hop < 4; hop++) {
      let res;
      try { res = await fetch(url, { redirect: 'manual', headers: { cookie: await cookieHeader(page, `sondeo ${short(key)}`) }, signal: AbortSignal.timeout(90000) }); } catch (e) { break; }
      const loc = res.headers.get('location');
      if (!loc || res.status < 300 || res.status >= 400) break;
      const next = new URL(loc, url).href;
      if (!check('sondeo', next, { kind: `enlace que redirige (${res.status})`, where: `enlace ${short(key)}${hop ? ' tras ' + hop + ' saltos' : ''}`, screen: label }) && next.startsWith(origin)) { url = next; continue; }
      break;
    }
  }
}

module.exports = { collect, collectUrls, CFG };
if (require.main === module) (async () => {
  const browser = await puppeteer.launch({
    executablePath: '/usr/bin/google-chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
      // Iframes de otro origen en el mismo proceso: sus peticiones pasan por page.on('request').
      '--disable-features=IsolateOrigins,site-per-process', '--disable-site-isolation-trials',
      // Nada sale a internet: todo host externo (el proveedor incluido, también desde workers) falla al resolverse.
      '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1, EXCLUDE localhost',
      `--log-net-log=${OUT}/netlog.json`, '--net-log-capture-mode=Default'],
  });
  const page = await browser.newPage();
  // Pestañas o ventanas nuevas (la página principal no: sus navegaciones ya se anotan como petición).
  const ownTargets = new Set([page.target()]);
  browser.on('targetcreated', (t) => { if (!ownTargets.has(t) && (t.type() === 'page' || t.type() === 'other')) check('ventana', t.url(), { kind: 'destino nuevo (' + t.type() + ')' }); });
  browser.on('targetchanged', (t) => { if (!ownTargets.has(t) && t.type() === 'page') check('ventana', t.url(), { kind: 'destino cambiado' }); });
  await page.setViewport({ width: 1440, height: 900 });
  await watch(page);
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  const closeOthers = async () => { for (const p of await browser.pages()) if (p !== page) await p.close().catch(() => {}); };
  const enter = async (label) => { state.screen = label; await page.setCookie({ name: 'digi_screen', value: encodeURIComponent(label), url: base }); };
  await enter('inicio de sesión');
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 120000 });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ timeout: 120000 }), page.click('#wp-submit')]);
  const [entry] = await page.evaluate(async (url) => (await fetch(url)).json(), `${base}/wp-json/wp/v2/pages?slug=digi-marca`);
  const pageId = IDS.page || entry.id;
  const visited = [];
  const visitedUrls = new Set();
  // Una pantalla: navegar, auditar y sondear sus enlaces. Si el servidor redirige al proveedor, Chrome no lo resuelve:
  // la redirección queda anotada (fuente «redirección») y la pantalla no se cuenta como error de carga.
  const screenAt = async (url, label = short(url), { wait = 1500, after } = {}) => {
    await enter(label);
    try {
      await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
      await sleep(wait);
      // El editor de bloques puede abrir su guía de bienvenida: se cierra para ver la barra superior.
      await page.$$eval('.components-modal__screen-overlay button[aria-label]', (b) => b.forEach((x) => x.click())).catch(() => {});
      await audit(page, label);
      visited.push(`${state.locale} ${label}`);
      visitedUrls.add(url.split('#')[0]);
      if (after) await after();
      if (url.includes('/wp-admin/')) await probeLinks(page, label, visitedUrls);
    } catch (e) {
      const msg = String(e.message || e);
      const redirected = hits.some((h) => h.screen === label && h.locale === state.locale && ['redirección', 'navegación'].includes(h.source));
      if (!(redirected && /ERR_NAME_NOT_RESOLVED|ERR_BLOCKED|ERR_ABORTED/.test(msg))) brand.push({ locale: state.locale, screen: label, kind: 'error', text: msg, shown: true, where: '' });
    }
    await closeOthers();
  };
  // Clic en cada control de un grupo (por índice, se vuelve a buscar tras cada clic), auditoría y Escape.
  const clickEach = async (selector, label, { skip = /publi|guard|save|update|actualiz|log ?out|cerrar sesi|desconect|disconnect/i, max = 14, frame } = {}) => {
    const target = frame || page;
    const count = await target.$$eval(selector, (els) => els.length).catch(() => 0);
    if (!count) { notes.push(`${state.locale} ${label}: no se encontró ${selector}`); return; }
    const home = page.url();
    for (let i = 0; i < Math.min(count, max); i++) {
      const name = await target.$$eval(selector, (els, i, skip) => {
        const el = els[i];
        if (!el) return null;
        const n = (el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || el.getAttribute('data-testid') || el.className || '').toString().replace(/\s+/g, ' ').trim().slice(0, 40);
        if (new RegExp(skip, 'i').test(n)) return '';
        el.scrollIntoView({ block: 'center' });
        el.click();
        return n || `#${i + 1}`;
      }, i, skip.source).catch(() => null);
      if (!name) continue;
      const step = `${label} · ${name}`;
      await enter(step);
      await sleep(1500);
      await audit(page, step);
      visited.push(`${state.locale} ${step}`);
      await page.keyboard.press('Escape').catch(() => {});
      await sleep(300);
      await closeOthers();
      if (page.url().split('#')[0] !== home.split('#')[0]) {
        await page.goto(home, { waitUntil: 'networkidle2', timeout: 120000 }).catch(() => {});
        await sleep(1000);
      }
    }
  };

  // F7a (SCOPE=full): un paso del panel es una lista de acciones $e/DOM en la página del editor (cada una devuelve ''
  // o el motivo del fallo), una espera y la auditoría; `need` son selectores que deben existir al auditar.
  const f7aError = (label, what) => brand.push({ locale: state.locale, screen: label, kind: 'error', text: `F7a: ${what}`, shown: true, where: '' });
  const f7aStep = async (label, actions, { need = [], wait = 1500, target = page } = {}) => {
    await enter(label);
    for (const [fn, arg] of actions) {
      const res = await target.evaluate(fn, arg).catch((e) => 'error: ' + e.message);
      // La espera (`settle`) no es una comprobación: lo que no se asienta va a las notas; los `need` deciden.
      if (res && fn === settle) notes.push(`${state.locale} ${label}: espera del editor: ${String(res).replace(/^aviso: /, '')}`);
      else if (res) f7aError(label, res);
      await sleep(900);
    }
    await sleep(wait);
    for (const sel of need) if (!(await target.$(sel).catch(() => null))) f7aError(label, `no aparece ${sel}`);
    await audit(page, label);
    visited.push(`${state.locale} ${label}`);
  };
  const pick = (id) => { const c = elementor.getContainer(id); if (!c) return `sin el elemento ${id}`; $e.run('document/elements/select', { container: c }); return ''; };
  const tab = (name) => { const t = document.querySelector(`#elementor-panel .elementor-tab-control-${name} a, #elementor-panel .elementor-tab-control-${name}`); if (!t) return `sin la pestaña ${name}`; t.click(); return ''; };
  const section = (name) => {
    const v = elementor.getPanelView().getCurrentPageView();
    if (!v || typeof v.activateSection !== 'function') return 'el panel no tiene secciones';
    v.activateSection(name);
    v._renderChildren();
    return document.querySelector(`#elementor-panel .elementor-control-${name}`) ? '' : `sin la sección ${name}`;
  };
  const popover = (name) => { const t = document.querySelector(`#elementor-panel .elementor-control-${name} .elementor-control-popover-toggle-toggle-label`); if (!t) return `sin el popover ${name}`; t.click(); return ''; };
  const route = (r) => { $e.route(r); return ''; };
  const run = (r) => { $e.run(r); return ''; };
  const click = (sel) => { const b = document.querySelector(sel); if (!b) return `sin ${sel}`; b.click(); return ''; };
  const closeDialog = () => { const b = [...document.querySelectorAll('.dialog-widget .dialog-cancel, .dialog-widget .dialog-button.dialog-cancel')].find((x) => x.offsetParent); if (b) b.click(); return ''; };
  // Estado limpio antes de cada paso de F7a: los pasos anteriores del editor (Escape tras cada uno, notas, buscador,
  // lista de pasos) pueden dejar abierto el diálogo «Salir», un panel lateral o el modo vista previa, sin panel.
  const reset = () => {
    for (const d of document.querySelectorAll('.dialog-widget, [role="dialog"]')) {
      if (!d.offsetParent && getComputedStyle(d).position !== 'fixed') continue;
      const cancel = d.querySelector('.dialog-cancel, .dialog-button.dialog-cancel') || [...d.querySelectorAll('button')].find((b) => /^(cancel|cancelar)$/i.test(b.textContent.trim()));
      if (cancel) cancel.click();
    }
    // Sólo lo que está abierto: cerrar «Ajustes del sitio» sin abrirlo cambia de documento.
    for (const name of ['notes', 'finder', 'shortcuts', 'navigator', 'library', 'panel/global']) { try { const c = $e.components.get(name); if (c && c.isOpen) $e.run(`${name}/close`); } catch (e) { /* no estaba abierto */ } }
    try { if (elementor.channels.dataEditMode.request('activeMode') !== 'edit') elementor.changeEditMode('edit'); } catch (e) { /* ya en edición */ }
    try { $e.run('panel/open'); } catch (e) { /* ya abierto */ }
    return '';
  };
  // Espera a que el editor se asiente: hay documento actual y ningún elemento de la vista previa espera su render
  // remoto (`elementor-loading`). El núcleo (`onModelRemoteRender`) lee `elementor.documents.getCurrent().id`: si un
  // render remoto (el del widget de vídeo, que no tiene plantilla JS) vuelve mientras se cambia de documento, lanza
  // «Cannot read properties of null (reading 'id')» y el cambio a «Ajustes del sitio» queda a medias, sin documento,
  // para el resto de los pasos. Con un solo worker y carga alta ese render tarda varios segundos.
  // El núcleo sólo quita `elementor-loading` cuando `render_widget` responde con éxito (`onRemoteGetHtml`): si la
  // petición se aborta (`renderRemoteServer` aborta la activa al repetirse) o falla, la clase queda para siempre sin
  // nada que esperar. Esa clase huérfana (sin petición activa en su modelo) se reintenta una vez a los 5 s y, si a los
  // 20 s sigue, deja de bloquear. El motivo nombra cada elemento; empieza por «aviso:» cuando no impide seguir.
  const settle = () => new Promise((resolve) => {
    const start = Date.now();
    const seen = new Map();
    const retried = new Set();
    const tick = () => {
      let busy = '';
      const orphans = [];
      try {
        if (!elementor.documents.getCurrent()) busy = 'sin documento actual';
        else {
          const active = [];
          for (const el of (elementor.$previewContents ? elementor.$previewContents.find('.elementor-loading').toArray() : [])) {
            const id = el.getAttribute('data-id') || '';
            let model = null;
            try { const c = id ? elementor.getContainer(id) : null; model = c ? (c.view && c.view.getEditModel ? c.view.getEditModel() : c.model) : null; } catch (e) { /* sin contenedor */ }
            const name = `${id || '?'}${model ? ` ${model.get('widgetType') || model.get('elType') || ''}` : ''}`.trim();
            if (model && typeof model.isRemoteRequestActive === 'function' && !model.isRemoteRequestActive()) orphans.push({ id: id || name, name, model });
            else active.push(name);
          }
          if (active.length) busy = `${active.length} elementos con render remoto pendiente: ${active.join(', ')}`;
        }
      } catch (e) { busy = String(e.message || e); }
      const now = Date.now();
      for (const o of orphans) {
        if (!seen.has(o.id)) seen.set(o.id, now);
        else if (now - seen.get(o.id) > 5000 && !retried.has(o.id)) { retried.add(o.id); try { o.model.renderRemoteServer(); } catch (e) { /* sin render remoto */ } }
      }
      const waiting = orphans.filter((o) => now - seen.get(o.id) <= 20000);
      if (!busy && !waiting.length) {
        resolve(orphans.length ? `aviso: ${orphans.length} elementos conservan elementor-loading sin petición activa tras reintentar su render (${orphans.map((o) => o.name).join(', ')}); se sigue` : '');
        return;
      }
      if (now - start > 90000) { resolve(`el editor no se asentó en 90 s (${busy || `${waiting.length} elementos sin petición activa con elementor-loading`})`); return; }
      setTimeout(tick, 500);
    };
    tick();
  });
  // La espera fuera de un paso: lo que no se asienta queda en las notas (AVISO). No es marca: lo que el paso siguiente
  // tenga que ver lo comprueban sus `need`.
  const settleNow = async (label) => {
    const res = await page.evaluate(settle).catch((e) => 'error: ' + e.message);
    if (res) notes.push(`${state.locale} ${label}: espera del editor: ${String(res).replace(/^aviso: /, '')}`);
  };
  const f7aEditor = async (frame) => {
    // Presets: «Preset de estilo» en Avanzado del encabezado vinculado, su menú contextual y el diálogo «Guardar estilo
    // como preset…» (se cancela), y la pestaña «Presets de estilo» de Ajustes del sitio con el diálogo de borrar.
    await f7aStep('editor · F7a · preset de estilo', [[settle, null], [reset, null], [settle, null], [pick, 'mw00001'], [tab, 'advanced'], [section, 'digitalisimo_section_preset']], { need: ['#elementor-panel .elementor-control-digitalisimo_preset'] });
    await enter('editor · F7a · menú contextual de presets');
    await settleNow('editor · F7a · menú contextual de presets');
    await page.evaluate(reset).catch(() => {});
    await sleep(900);
    await settleNow('editor · F7a · menú contextual de presets');
    const heading = await frame.$('.elementor-element-mw00001');
    if (heading) {
      // El evento `contextmenu` en el elemento de la vista previa (el que escucha el comportamiento del núcleo); el clic
      // derecho del ratón queda de respaldo: con la vista previa ocupada no siempre llega.
      const group = '.elementor-context-menu-list__group-digitalisimo-preset';
      await frame.evaluate(() => {
        const el = document.querySelector('.elementor-element-mw00001');
        const r = el.getBoundingClientRect();
        el.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, view: window, button: 2, clientX: r.left + 12, clientY: r.top + 12 }));
      }).catch(() => {});
      if (!(await page.waitForSelector(group, { visible: true, timeout: 8000 }).catch(() => null))) {
        await heading.click({ button: 'right' }).catch(() => {});
        await page.waitForSelector(group, { visible: true, timeout: 8000 }).catch(() => null);
      }
      await sleep(800);
      if (!(await page.$(group))) f7aError('editor · F7a · menú contextual de presets', 'el menú contextual no tiene el grupo de presets');
      await audit(page, 'editor · F7a · menú contextual de presets');
      visited.push(`${state.locale} editor · F7a · menú contextual de presets`);
      const item = await page.$('.elementor-context-menu-list__item-digitalisimo-preset-save:not(.elementor-context-menu-list__item--disabled)');
      if (item) {
        await item.click().catch(() => {});
        await f7aStep('editor · F7a · guardar estilo como preset (diálogo)', [], { need: ['.dialog-widget .dialog-confirm-ok, .dialog-widget .dialog-ok, .dialog-widget input'] });
        await page.evaluate(closeDialog).catch(() => {});
      } else f7aError('editor · F7a · menú contextual de presets', 'sin la acción «Guardar estilo como preset…» habilitada');
      await page.keyboard.press('Escape').catch(() => {});
    } else f7aError('editor · F7a · menú contextual de presets', 'la vista previa no tiene mw00001');
    await f7aStep('editor · F7a · ajustes del sitio · presets de estilo', [[settle, null], [reset, null], [settle, null], [run, 'panel/global/open'], [settle, null], [route, 'panel/global/settings-digitalisimo-presets']], { need: ['#elementor-panel [data-digi-preset-row="marca-titulo"]'], wait: 2500 });
    await f7aStep('editor · F7a · presets de estilo · borrar (diálogo)', [[click, '#elementor-panel [data-digi-preset-action="delete"][data-digi-preset="marca-titulo"]']], { need: ['.dialog-widget .dialog-cancel'] });
    await page.evaluate(closeDialog).catch(() => {});
    // Cerrar «Ajustes del sitio» vuelve al documento de la página: antes y después, sin render remoto pendiente.
    await settleNow('editor · F7a · cerrar ajustes del sitio');
    await page.evaluate(() => { try { $e.run('panel/global/close'); } catch (e) { /* ya cerrado */ } }).catch(() => {});
    await sleep(1500);
    await settleNow('editor · F7a · cerrar ajustes del sitio');
    // Fondo del contenedor: degradado avanzado (forma cónica, paradas) y el popover «Patrón».
    await f7aStep('editor · F7a · degradado avanzado', [[settle, null], [reset, null], [settle, null], [pick, 'mc00003'], [tab, 'style'], [section, 'section_background']], { need: ['#elementor-panel .elementor-control-background_digi_kind', '#elementor-panel .elementor-control-background_digi_stops', '#elementor-panel .elementor-control-background_digi_conic_from'] });
    await f7aStep('editor · F7a · patrón (popover)', [[popover, 'digitalisimo_pattern']], { need: ['#elementor-panel .elementor-control-digitalisimo_pattern_tile', '#elementor-panel .elementor-control-digitalisimo_pattern_raise'] });
    await page.keyboard.press('Escape').catch(() => {});
    // Avanzado del contenedor: la sección «Máscara» y, en Efectos de movimiento, el aviso del sticky en el propio
    // elemento fijo; en su hijo, el estado «al fijarse».
    await f7aStep('editor · F7a · máscara', [[settle, null], [reset, null], [settle, null], [pick, 'mc00003'], [tab, 'advanced'], [section, 'digitalisimo_section_mask']], { need: ['#elementor-panel .elementor-control-digitalisimo_mask_shape'] });
    await f7aStep('editor · F7a · sticky · aviso en el elemento fijo', [[section, 'section_effects']], { need: ['#elementor-panel .elementor-control-sticky'] });
    await f7aStep('editor · F7a · sticky · estado al fijarse', [[settle, null], [reset, null], [settle, null], [pick, 'mw00006'], [tab, 'advanced'], [section, 'section_effects']], { need: ['#elementor-panel .elementor-control-digitalisimo_sticky_state'] });
    // Ajustes de la página: «Ocultar imagen destacada» (cualquier tema) y la nota de cabecera y pie con otro tema.
    await f7aStep('editor · F7a · controles de documento', [[settle, null], [reset, null], [settle, null], [route, 'panel/page-settings/settings']], { need: ['#elementor-panel .elementor-control-digitalisimo_hide_featured_image'] });
  };
  for (const locale of LOCALES) {
    state.locale = locale;
    await page.setCookie({ name: 'digi_locale', value: locale, url: base });
    await enter('escritorio');
    await page.goto(`${base}/wp-admin/index.php`, { waitUntil: 'networkidle2', timeout: 120000 });
    // Todas las pantallas del menú de administración que pertenecen al constructor.
    const menu = await page.$$eval('#adminmenu a[href]', (links) => [...new Set(links.map((a) => a.href))]);
    const own = await page.$$eval('#toplevel_page_elementor-home a[href], #toplevel_page_elementor a[href], #menu-posts-elementor_library a[href]', (links) => links.map((a) => a.href)).catch(() => []);
    const screens = [...menu.filter((href) => /elementor|e-landing-page|e-floating|page=e-|digitalisimo-elements/i.test(href)), ...own].filter((h) => h.startsWith(origin));
    const fixed = [
      'wp-admin/index.php',
      'wp-admin/plugins.php',
      'wp-admin/edit.php?post_type=page',
      `wp-admin/post.php?post=${pageId}&action=edit`,
      'wp-admin/admin.php?page=elementor-app#/kit-library',
      'wp-admin/admin.php?page=elementor-app#/import',
      'wp-admin/admin.php?page=elementor-app#/export',
      'wp-admin/admin.php?page=elementor-app#/site-editor',
    ];
    const extra = SCOPE === 'full' ? [
      'wp-admin/admin.php?page=elementor-connect', 'wp-admin/admin.php?page=elementor-apps', 'wp-admin/admin.php?page=elementor-element-manager',
      'wp-admin/admin.php?page=elementor-role-manager', 'wp-admin/admin.php?page=elementor-system-info', 'wp-admin/admin.php?page=elementor-mcp',
      'wp-admin/admin.php?page=e-form-submissions', 'wp-admin/edit.php?post_type=elementor_library&tabs_group=popup',
      'wp-admin/edit.php?post_type=elementor_snippet', 'wp-admin/edit.php?post_type=elementor_font', 'wp-admin/edit.php?post_type=elementor_icons',
      'wp-admin/edit.php?post_type=e-floating-buttons', 'wp-admin/edit.php?post_type=e-landing-page',
      'wp-admin/themes.php', 'wp-admin/update-core.php', 'wp-admin/upload.php', 'wp-admin/post-new.php', 'wp-admin/tools.php',
      'wp-admin/tools.php?page=digitalisimo-ep-migration',
      // F7a: «Rendimiento» con su sección «Privacidad» (también llega por el menú; aquí, aunque el menú cambie).
      'wp-admin/admin.php?page=digitalisimo-elements-front',
      'wp-admin/admin.php?page=elementor-app#/kit-library/cloud', 'wp-admin/admin.php?page=elementor-app#/kit-library/favorites',
      'wp-admin/admin.php?page=elementor-app#/onboarding', 'wp-admin/admin.php?page=elementor-app#/import-customization',
      'wp-admin/admin.php?page=elementor-app#/export-customization', 'wp-admin/admin.php?page=elementor-app#/site-editor/templates',
      'wp-admin/admin.php?page=elementor-app#/site-editor/add-new', 'wp-admin/admin.php?page=elementor-app#/site-editor/import',
    ] : [];
    // STATE=kit, con cualquier SCOPE (CV-28): la App de importación en sus tres pantallas y la pantalla «Kits» con el
    // informe de la sesión importada.
    const kitScreens = STATE === 'kit' ? [
      'wp-admin/admin.php?page=elementor-app#/import-customization', 'wp-admin/admin.php?page=elementor-app#/import-customization/content',
      'wp-admin/admin.php?page=elementor-app#/import-customization/complete', 'wp-admin/admin.php?page=digitalisimo-elements-kits',
    ] : [];
    if (STATE === 'kit' && !KIT) brand.push({ locale, screen: 'kit', kind: 'error', text: 'STATE=kit sin kit.json: la importación del kit no terminó', shown: true, where: '' });
    const planned = [...new Set([...fixed, ...extra, ...kitScreens].map((p) => `${base}/${p}`).concat(screens).concat(KIT && KIT.screen ? [KIT.screen.replace(/^https?:\/\/[^/]+/, base)] : []))];
    // Lo que se va a visitar no se sondea aparte: el sondeo es para los enlaces que el recorrido no abre.
    for (const url of planned) visitedUrls.add(url.split('#')[0]);
    for (const url of planned) {
      // Las rutas de la app cambian sólo el hash: se pasa por about:blank para cargar cada una desde cero.
      if (url.includes('#')) await page.goto('about:blank').catch(() => {});
      const after = SCOPE !== 'full' ? undefined : async () => {
        const label = short(url);
        // Pestañas de Ajustes y Herramientas (en la misma página) y el diálogo «Añadir plantilla».
        if (/page=elementor-(settings|tools)\b/.test(url)) await clickEach('.nav-tab-wrapper a.nav-tab, [role="tablist"] [role="tab"]', label, { max: 12 });
        if (/\/edit\.php\?post_type=elementor_library(&|$)/.test(url)) await clickEach('.page-title-action', label, { max: 1 });
        // F7a: la sección «Privacidad» de «Rendimiento» («Registrar visitas sin gestor de consentimiento»).
        if (/[?&]page=digitalisimo-elements-front(&|$)/.test(url)) {
          const at = `${label} · Privacidad`;
          await enter(at);
          const found = await page.evaluate(() => { const h = document.getElementById('digitalisimo-front-privacy'); if (!h) return false; h.scrollIntoView({ block: 'start' }); return !!document.querySelector('input[name*="page_visits_no_consent"]'); }).catch(() => false);
          if (!found) f7aError(at, 'sin la sección «Privacidad» o sin la casilla page_visits_no_consent');
          await sleep(800);
          await audit(page, at);
          visited.push(`${state.locale} ${at}`);
        }
      };
      await screenAt(url, short(url), { after });
    }
    // Rutas del núcleo que redirigen al proveedor aunque ningún enlace visible las nombre (una regresión de la guarda
    // las reabriría): se sondean siempre.
    await probeLinks(page, 'sondeo · rutas conocidas', visitedUrls, ['go_elementor_pro', 'elementor-one-upgrade', 'elementor-connect&app=library&action=authorize', 'elementor-connect&app=activate&action=authorize'].map((p) => `${base}/wp-admin/admin.php?page=${p}`));
    if (SCOPE === 'full') {
      // Barra superior de Editor One en la pantalla de inicio del constructor: cada botón (ayuda, novedades, cuenta…).
      await screenAt(`${base}/wp-admin/admin.php?page=elementor-home`, 'barra superior', {
        after: () => clickEach('#editor-one-top-bar button, #editor-one-top-bar a[href], #editor-one-top-bar [role="button"]', 'barra superior', { max: 16 }),
      });
    }
    // Editor: panel, barra superior y su menú, y la biblioteca de plantillas.
    const editorAt = async (id, prefix) => {
      await enter(`${prefix}-cargando`);
      await page.goto(`${base}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 180000 });
      await sleep(800);
      await audit(page, `${prefix}-cargando`);
      await page.waitForSelector('#elementor-panel', { timeout: 180000 });
      const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
      try {
        await frame.waitForSelector('.elementor-widget', { timeout: id === pageId ? 180000 : 60000 });
      } catch (e) {
        if (id === pageId) throw e;
        notes.push(`${state.locale} ${prefix}: la vista previa no pintó el documento; se audita el panel y la barra`);
      }
      await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return !l || getComputedStyle(l).display === 'none' || l.getBoundingClientRect().width === 0; }, { timeout: 120000 }).catch(() => {});
      await sleep(4000);
      await enter(prefix);
      await audit(page, prefix);
      visited.push(`${state.locale} ${prefix}`);
      return frame;
    };
    // Un estado del editor abierto con un comando $e (no por textos, que dependen del idioma).
    const editorStep = async (label, fn, arg, wait = 2000) => {
      await enter(label);
      // Sin cambiar de documento con un render remoto pendiente (ver `settle`); si no se asienta, el paso sigue igual.
      await settleNow(label);
      const res = await page.evaluate(fn, arg).catch((e) => 'error: ' + e.message);
      // Un error del editor al abrir el estado se anota, pero lo que haya quedado en pantalla se audita igual.
      if (typeof res === 'string' && res) notes.push(`${state.locale} ${label}: ${res}`);
      await sleep(wait);
      await audit(page, label);
      visited.push(`${state.locale} ${label}`);
    };
    try {
      const frame = await editorAt(pageId, 'editor');
      // Menú principal del editor (botón con el logotipo en la barra superior).
      await enter('editor-menu');
      const opened = await page.evaluate(() => {
        const button = document.querySelector('#elementor-editor-wrapper-v2 header button, #elementor-panel-header-menu-button, [aria-label*="menu" i]');
        if (button) { button.click(); return true; }
        return false;
      });
      if (opened) { await sleep(1500); await audit(page, 'editor-menu'); await page.keyboard.press('Escape'); }
      await enter('editor-biblioteca');
      const library = await frame.evaluate(() => { const b = document.querySelector('.elementor-add-template-button'); if (b) { b.click(); return true; } return false; });
      if (library) {
        await sleep(1000);
        await audit(page, 'editor-biblioteca-cargando');
        await sleep(8000);
        await audit(page, 'editor-biblioteca');
      }
      visited.push(`${state.locale} editor`);
      if (SCOPE === 'full') {
        // Cada pestaña de la biblioteca (bloques, páginas, mis plantillas, nube…).
        const routes = await page.evaluate(() => {
          try {
            const c = $e.components.get('library');
            const keys = [...Object.keys(c.tabs || {}), ...Object.keys(c.getRoutes ? c.getRoutes() : c.routes || {})];
            return [...new Set(keys.map((k) => (k.startsWith('library/') ? k : 'library/' + k)).filter((k) => /^library\/templates\//.test(k)))];
          } catch (e) { return []; }
        });
        if (!routes.length) notes.push(`${state.locale} editor-biblioteca: no se pudieron enumerar sus rutas`);
        for (const route of routes) await editorStep(`editor-biblioteca ${route.replace('library/', '')}`, (r) => { try { if (!$e.components.get('library').isOpen) $e.run('library/open'); $e.route(r); return ''; } catch (e) { return 'error: ' + e.message; } }, route, 6000);
        await page.evaluate(() => { try { $e.run('library/close'); } catch (e) { /* ya cerrada */ } }).catch(() => {});
        const steps = [
          ['editor · elementos', () => { $e.route('panel/elements/categories'); return ''; }],
          ['editor · título seleccionado', () => { $e.run('document/elements/select', { container: elementor.getContainer('mw00001') }); return ''; }],
          ['editor · título · estilo', () => { const t = document.querySelector('.elementor-tab-control-style a, .elementor-tab-control-style'); if (t) { t.click(); return ''; } $e.route('panel/editor/style'); return ''; }],
          ['editor · título · avanzado', () => { const t = document.querySelector('.elementor-tab-control-advanced a, .elementor-tab-control-advanced'); if (t) { t.click(); return ''; } $e.route('panel/editor/advanced'); return ''; }],
          ['editor · vídeo seleccionado', () => { $e.run('document/elements/select', { container: elementor.getContainer('mw00003') }); return ''; }],
          ['editor · youtube v4 seleccionado', () => { const c = elementor.getContainer('mw00004'); if (!c) return 'sin e-youtube'; $e.run('document/elements/select', { container: c }); return ''; }],
          ['editor · fondo de vídeo seleccionado', () => { $e.run('document/elements/select', { container: elementor.getContainer('mc00002') }); const t = document.querySelector('.elementor-tab-control-style a, .elementor-tab-control-style'); if (t) t.click(); return ''; }],
          ['editor · ajustes del sitio', () => { $e.run('panel/global/open'); return ''; }],
          ['editor · ajustes de la página', () => { try { $e.run('panel/global/close'); } catch (e) { /* nada */ } $e.route('panel/page-settings/settings'); return ''; }],
          ['editor · revisiones', () => { $e.route('panel/history/actions'); $e.route('panel/history/revisions'); return ''; }],
          ['editor · navegador', () => { $e.run('navigator/open'); return ''; }],
          ['editor · atajos', () => { try { $e.run('navigator/close'); } catch (e) { /* nada */ } $e.run('shortcuts/open'); return ''; }],
          ['editor · buscador', () => { try { $e.run('shortcuts/close'); } catch (e) { /* nada */ } $e.route('finder'); return ''; }],
          ['editor · notas', () => { try { $e.run('finder/close'); } catch (e) { /* nada */ } $e.route('notes'); return ''; }],
          ['editor · lista de pasos', () => { try { $e.run('checklist/toggle-popup'); return ''; } catch (e) { return 'sin lista de pasos'; } }],
        ];
        for (const [label, fn] of steps) {
          await editorStep(label, (src) => { try { return new Function(`return (${src})()`)(); } catch (e) { return 'error: ' + e.message; } }, fn.toString());
          await page.keyboard.press('Escape').catch(() => {});
        }
        // Menú contextual de un elemento en la vista previa.
        await enter('editor · menú contextual');
        const el = await frame.$('.elementor-element-mw00001');
        if (el) { await el.click({ button: 'right' }).catch(() => {}); await sleep(1500); await audit(page, 'editor · menú contextual'); await page.keyboard.press('Escape').catch(() => {}); }
        // F7a: pantallas nuevas del editor. Un control o una sección que falta es un error: no se habría auditado.
        await f7aEditor(frame);
        // Cada botón de la barra superior del editor, salvo publicar o guardar.
        await clickEach('#elementor-editor-wrapper-v2 header button', 'editor · barra superior', { max: 14 });
      }
    } catch (e) {
      brand.push({ locale, screen: 'editor', kind: 'error', text: String(e.message || e), shown: true, where: '' });
    }
    if (SCOPE === 'full') {
      for (const [key, prefix] of [['popup', 'editor popup'], ['header', 'editor cabecera']]) {
        if (!IDS[key]) continue;
        try {
          await editorAt(IDS[key], prefix);
          // F7a: la prioridad manual de la plantilla, en los ajustes del documento de la cabecera.
          // F7a: la prioridad manual de la plantilla, en los ajustes del documento de la cabecera. En este sitio (tema de
          // bloques, sin ubicaciones) la vista previa de la cabecera nunca se adjunta, desde antes de F7a, y sin ella el
          // panel no tiene «Ajustes de la página»: entonces se comprueba el control en la configuración del documento y
          // se revisan su etiqueta y su ayuda.
          if (key === 'header') {
            const at = `${prefix} · F7a · prioridad de la plantilla`;
            const ready = await page.evaluate(() => { try { $e.route('panel/page-settings/settings'); return ''; } catch (e) { return String(e.message || e); } }).catch((e) => String(e.message || e));
            if (!ready) await f7aStep(at, [[settle, null], [reset, null], [settle, null], [route, 'panel/page-settings/settings']], { need: ['#elementor-panel .elementor-control-digitalisimo_tb_priority'] });
            else {
              await enter(at);
              const info = await page.evaluate(() => { const c = ((elementor.config.document || {}).settings || {}).controls || {}; const p = c.digitalisimo_tb_priority; return p ? { label: String(p.label || ''), description: String(p.description || ''), type: p.type } : null; }).catch(() => null);
              if (!info) f7aError(at, 'el documento de la cabecera no declara digitalisimo_tb_priority');
              else {
                if (/\belementor\b/i.test(`${info.label} ${info.description}`)) brand.push({ locale: state.locale, screen: at, kind: 'texto', text: `${info.label} · ${info.description}`.slice(0, 200), shown: true, where: 'controls.digitalisimo_tb_priority' });
                notes.push(`${state.locale} ${at}: sin vista previa de la cabecera (${ready}); control comprobado en la configuración del documento: «${info.label}» (${info.type})`);
              }
              await audit(page, at);
              visited.push(`${state.locale} ${at}`);
            }
          }
        } catch (e) { notes.push(`${locale} ${prefix}: ${String(e.message || e).slice(0, 160)}`); }
        await closeOthers();
      }
    }
    // WOO=1 (F4-Woo): el editor de la página con los widgets de WooCommerce, con cada widget seleccionado (su panel de
    // controles y los textos del editor), y su frontal como administrador. El visitante la ve más abajo.
    if (IDS.woo) {
      try {
        const wooFrame = await editorAt(IDS.woo, 'editor-woo');
        const wooWidgets = await wooFrame.$$eval('.elementor-widget[data-widget_type]', (els) => els.map((e) => e.getAttribute('data-widget_type'))).catch(() => []);
        for (const [i, type] of wooWidgets.slice(0, 12).entries()) {
          const label = `editor-woo · ${String(type).replace(/\.default$/, '')}`;
          await enter(label);
          const clicked = await wooFrame.evaluate((n) => { const w = document.querySelectorAll('.elementor-widget[data-widget_type]')[n]; if (!w) return false; w.scrollIntoView({ block: 'center' }); w.click(); return true; }, i).catch(() => false);
          if (!clicked) continue;
          await sleep(2500);
          await audit(page, label);
          visited.push(`${state.locale} ${label}`);
        }
      } catch (e) {
        notes.push(`${state.locale} editor-woo: ${e.message}`);
      }
      await screenAt(`${base}/digi-marca-woo/`, 'frontal-woo-admin');
    }
    // Frontal como administrador (barra de administración, con cada menú desplegado en full).
    await screenAt(`${base}/digi-marca/`, 'frontal-admin', {
      after: SCOPE !== 'full' ? undefined : async () => {
        const items = await page.$$eval('#wpadminbar li.menupop > .ab-item', (els) => els.map((e, i) => i)).catch(() => []);
        for (const i of items.slice(0, 8)) {
          const handle = (await page.$$('#wpadminbar li.menupop > .ab-item'))[i];
          if (!handle) continue;
          const name = await handle.evaluate((e) => (e.closest('li').id || '').replace('wp-admin-bar-', ''));
          await handle.hover().catch(() => {});
          await sleep(600);
          await enter(`frontal-admin · ${name}`);
          await audit(page, `frontal-admin · ${name}`);
        }
      },
    });
    // STATE=kit: cada documento importado como administrador (vista previa de los borradores de «agregar»).
    if (KIT) {
      for (const item of (KIT.pages || []).slice(0, SCOPE === 'full' ? 40 : 12)) {
        await screenAt(String(item.url).replace(/^https?:\/\/[^/]+/, base), `kit · ${item.type} ${item.id}${item.status === 'publish' ? '' : ' (vista previa)'}`);
      }
    }
    // Frontal como visitante: el documento completo en una sesión sin cookies (red, DOM, scripts) y la meta generator.
    const ctx = await browser.createBrowserContext();
    const visitor = await ctx.newPage();
    ownTargets.add(visitor.target());
    await watch(visitor);
    await enter('frontal-visitante');
    await visitor.setCookie({ name: 'digi_screen', value: encodeURIComponent('frontal-visitante'), url: base }, { name: 'digi_locale', value: locale, url: base });
    const response = await visitor.goto(`${base}/digi-marca/`, { waitUntil: 'networkidle2', timeout: 120000 }).catch(() => null);
    if (response) for (const [k, v] of Object.entries(response.headers())) check('dom', (String(v).match(/https?:\/\/[^\s<>",;]+/) || [''])[0], { kind: `cabecera HTTP ${k}`, content: true });
    await audit(visitor, 'frontal-visitante');
    visited.push(`${locale} frontal-visitante`);
    const html = await visitor.content().catch(() => '');
    for (const match of html.match(/<meta[^>]+generator[^>]*>/gi) || []) {
      if (BRAND.test(match)) brand.push({ locale, screen: 'frontal-código', kind: 'meta generator', text: match, shown: true, where: 'head' });
    }
    await ctx.close().catch(() => {});
    // Eventos programados (cron) con el registro del servidor activo.
    await enter('cron');
    try {
      const res = await fetch(`${base}/wp-content/probe/cron.php`, { headers: { cookie: `digi_locale=${locale}; digi_screen=cron` }, signal: AbortSignal.timeout(300000) });
      fs.writeFileSync(`${OUT}/cron-${locale}.json`, await res.text());
    } catch (e) { notes.push(`${locale} cron: ${e.message}`); }
  }
  await browser.close();

  // Servidor: http.jsonl de probe-http.php.
  const serverKinds = { 'salió': 'falla', 'redirección': 'falla', 'correo': 'falla', 'bloqueada': 'informe', 'permitida': 'informe', 'host permitido': 'informe', 'respuesta': 'latente' };
  if (fs.existsSync(`${OUT}/http.jsonl`)) {
    for (const line of fs.readFileSync(`${OUT}/http.jsonl`, 'utf8').split('\n').filter(Boolean)) {
      let e;
      try { e = JSON.parse(line); } catch (err) { continue; }
      const url = e.kind === 'host permitido' ? `https://${e.url}/` : e.url;
      const c = classify(V, url, { caller: e.caller || '' }) || { host: e.url, domain: e.url, why: e.why };
      const h = { source: 'servidor', kind: e.kind, url: e.url, host: c.host, domain: c.domain, why: e.why || c.why, locale: e.locale || '', screen: e.screen || e.context, where: `${e.context}${e.caller ? ' · ' + e.caller : e.core ? ' · ' + e.core : ''}`, frame: '', shown: true, excluded: '', tag: '', latent: e.kind === 'respuesta', extra: e.subject || '' };
      h.severityHint = serverKinds[e.kind] || 'falla';
      hits.push(h);
    }
  }
  // Red de seguridad: el netlog de Chrome (workers, preconexiones) con hosts del proveedor que nadie anotó.
  const known = new Set(hits.map((h) => h.host));
  const netlogHosts = new Map();
  if (fs.existsSync(`${OUT}/netlog.json`)) {
    const rl = readline.createInterface({ input: fs.createReadStream(`${OUT}/netlog.json`, { encoding: 'utf8' }), crlfDelay: Infinity });
    for await (const line of rl) {
      if (!/"(url|host)"/.test(line)) continue;
      for (const m of line.matchAll(/"(?:url|host|original_url)":"([^"]+)"/g)) {
        const raw = /^[a-z]+:\/\//i.test(m[1]) ? m[1] : `https://${m[1].replace(/:\d+$/, '')}/`;
        const c = classify(V, raw, { content: true });
        if (c && !known.has(c.host) && !netlogHosts.has(c.host)) netlogHosts.set(c.host, { c, url: raw });
      }
    }
  } else notes.push('Chrome no dejó netlog.json: no hay red de seguridad para lo que no pasó por page.on(\'request\')');
  for (const { c, url } of netlogHosts.values()) hits.push({ source: 'netlog', kind: 'host sin atribuir', url: url.slice(0, 400), host: c.host, domain: c.domain, why: c.why, locale: '', screen: '(sin atribuir)', where: '', frame: '', shown: true, excluded: '', tag: '', latent: false, extra: '' });

  // Las peticiones de las imágenes de una fila excluida también quedan excluidas.
  const excludedUrls = new Set(hits.filter((h) => h.excluded).map((h) => `${h.locale}|${h.screen}|${h.url}`));
  for (const h of hits) if (!h.excluded && h.source === 'red' && excludedUrls.has(`${h.locale}|${h.screen}|${h.url}`)) h.excluded = 'recurso de la fila del plugin original';
  // Severidad: red, redirecciones, ventanas, servidor («salió»), correos y DOM (visible u oculto) fallan; lo latente
  // (datos localizados, plantillas, respuestas AJAX/REST) se informa; LATENT=fail lo hace fallar.
  for (const h of hits) {
    const allow = ALLOW.find((a) => a.reUrl.test(h.url) && (!a.reScreen || a.reScreen.test(h.screen)) && (!a.reSource || a.reSource.test(h.source)));
    if (h.excluded) h.severity = 'excluida';
    else if (allow) { allow.used++; h.severity = 'permitida'; h.allow = allow.motivo; }
    else if (h.latent || h.severityHint === 'latente') h.severity = LATENT_FAIL ? 'falla' : 'latente';
    else h.severity = h.severityHint || 'falla';
  }
  const category = (h) => {
    if (h.source === 'servidor') return `servidor ${h.kind}`;
    if (h.source === 'red') return /video|media/.test(h.kind) || /vídeo/.test(h.why) ? 'vídeo (petición)' : /iframe|sub_frame|document/.test(h.kind) ? 'iframe (petición)' : 'petición';
    if (h.source === 'dom') {
      if (h.tag === 'iframe' || /iframe/.test(h.kind)) return 'iframe';
      if (['video', 'source', 'track'].includes(h.tag) || /vídeo/.test(h.why)) return 'vídeo';
      if (/href/.test(h.kind) && ['a', 'area'].includes(h.tag)) return 'enlace';
      if (h.tag === 'link') return 'link (preconexión/recurso)';
      if (h.kind === 'texto') return 'texto';
      return 'recurso o atributo';
    }
    return h.source;
  };
  for (const h of hits) h.category = category(h);

  // Informe: agrupado por severidad, pantalla y dominio.
  const fails = hits.filter((h) => h.severity === 'falla');
  const group = (list) => {
    const byScreen = new Map();
    for (const h of list) {
      const s = `${h.locale ? h.locale + ' ' : ''}${h.screen}`;
      if (!byScreen.has(s)) byScreen.set(s, new Map());
      const m = byScreen.get(s);
      if (!m.has(h.host)) m.set(h.host, []);
      m.get(h.host).push(h);
    }
    return byScreen;
  };
  const print = (title, list, examples = 2) => {
    if (!list.length) return;
    console.log(`\n${title} (${list.length})`);
    for (const [s, hostsMap] of group(list)) {
      console.log(`  ${s}`);
      for (const [host, hs] of hostsMap) {
        const cats = {};
        for (const h of hs) cats[h.category] = (cats[h.category] || 0) + 1;
        console.log(`    ${host} · ${Object.entries(cats).map(([k, v]) => `${k} ×${v}`).join(', ')} · ${hs[0].why}`);
        for (const h of hs.slice(0, examples)) console.log(`        ${h.url}${h.where ? `  [${h.kind}${h.shown === false ? ', oculto' : ''} · ${h.where.slice(0, 140)}]` : `  [${h.kind}]`}`);
        if (hs.length > examples) console.log(`        (+${hs.length - examples})`);
      }
    }
  };
  // Marca visible (texto, logotipos, paleta), como antes.
  const shownBrand = brand.filter((f) => f.shown && !(f.kind === 'texto' && (f.exempt || TEXT_EXEMPT.test(f.screen))));
  const groups = new Map();
  for (const f of shownBrand) {
    const key = f.kind === 'color de Elementor' ? `${f.kind} · ${f.text} · ${f.where}` : `${f.kind} · ${f.text}`;
    if (!groups.has(key)) groups.set(key, new Set());
    groups.get(key).add(`${f.locale} ${f.screen}`);
  }
  for (const [key, where] of groups) console.log(`VISIBLE  ${key}\n         en: ${[...where].slice(0, 4).join(' | ')}${where.size > 4 ? ` (+${where.size - 4})` : ''}`);
  // Lo que está en el DOM sin verse (diálogos, menús cerrados) aparece al interactuar: se lista aparte.
  const hidden = new Map();
  for (const f of brand.filter((x) => !x.shown)) {
    const key = `${f.kind} · ${f.text}`;
    if (!hidden.has(key)) hidden.set(key, `${f.locale} ${f.screen}`);
  }
  for (const [key, where] of hidden) console.log(`OCULTO   ${key}  (${where})`);

  console.log('\n=== Referencias a plugins de origen ===');
  const sources = [['navegación', 'FALLA navegación del navegador hacia el proveedor'], ['redirección', 'FALLA redirección de una pantalla propia al proveedor'], ['sondeo', 'FALLA enlace propio que redirige al proveedor'], ['ventana', 'FALLA ventana nueva hacia el proveedor'], ['red', 'FALLA petición del navegador'], ['dom', 'FALLA URL en el DOM (enlace, iframe, vídeo, recurso, atributo o texto)'], ['servidor', 'FALLA servidor'], ['netlog', 'FALLA netlog (sin atribuir a una pantalla)'], ['latente', 'FALLA latente']];
  for (const [src, title] of sources) print(title, fails.filter((h) => h.source === src));
  const latent = hits.filter((h) => h.severity === 'latente');
  const info = hits.filter((h) => h.severity === 'informe');
  const excluded = hits.filter((h) => h.severity === 'excluida');
  const allowed = hits.filter((h) => h.severity === 'permitida');
  // Lo latente se resume por pantalla, dominio y script (el id lleva a la llamada PHP que hay que filtrar).
  if (latent.length) {
    console.log(`\nINFORME latente: URL del proveedor en datos localizados, plantillas o respuestas AJAX/REST (${latent.length}; LATENT=fail las hace fallar)`);
    const m = new Map();
    for (const h of latent) {
      const k = `${h.host} · ${h.source === 'servidor' ? 'respuesta ' + h.where.split(' · ')[0] : h.where.replace(/\[\d+\]/g, '[]').split('.').slice(0, 4).join('.')}`;
      if (!m.has(k)) m.set(k, { n: 0, screens: new Set(), ex: h.url });
      const g = m.get(k);
      g.n++;
      g.screens.add(`${h.locale} ${h.screen}`);
    }
    for (const [k, g] of [...m].sort((a, b) => b[1].n - a[1].n).slice(0, 60)) console.log(`    ${k} ×${g.n} en ${g.screens.size} pantallas · ej.: ${g.ex.slice(0, 120)}`);
    if (m.size > 60) console.log(`    (+${m.size - 60} grupos más en vendor-urls.json)`);
  }
  print('INFORME servidor sin salida (bloqueada por otra guarda, WordPress para el plugin original o host permitido)', info, 1);
  if (excluded.length) console.log(`\nEXCLUIDA fila del plugin original instalado: ${excluded.length} URL (${[...new Set(excluded.map((h) => h.host))].join(', ')})`);
  if (allowed.length) console.log(`\nPERMITIDA por allow.json: ${allowed.length}`);

  // Resumen por dominio y por pantalla (lo que falta bloquear).
  const table = {};
  const byScreen = {};
  for (const h of hits.filter((x) => ['falla', 'latente'].includes(x.severity))) {
    const d = h.host;
    table[d] = table[d] || {};
    table[d][h.category] = (table[d][h.category] || 0) + 1;
    const s = `${h.locale ? h.locale + ' ' : ''}${h.screen}`;
    byScreen[s] = byScreen[s] || {};
    byScreen[s][d] = (byScreen[s][d] || 0) + 1;
  }
  console.log('\n=== Resumen por dominio (fallas y latentes) ===');
  for (const [d, cats] of Object.entries(table).sort((a, b) => Object.values(b[1]).reduce((x, y) => x + y) - Object.values(a[1]).reduce((x, y) => x + y))) {
    console.log(`  ${d.padEnd(40)} ${Object.entries(cats).map(([k, v]) => `${k} ${v}`).join(' · ')}`);
  }
  const unused = ALLOW.filter((a) => !a.used);
  for (const a of unused) console.log(`FALLA  excepción de allow.json sin usar: ${a.url} (${a.motivo})`);
  let originalProblem = '';
  if (ORIGINAL) {
    const applied = excluded.filter((h) => /plugins\.php/.test(h.screen)).length;
    if (!applied) originalProblem = 'ORIGINAL=1: la exclusión de la fila del plugin original no se aplicó en plugins.php (¿cambió su marcado?)';
    if (originalProblem) console.log(`FALLA  ${originalProblem}`);
  }
  for (const n of notes) console.log(`AVISO  ${n}`);
  fs.writeFileSync(`${OUT}/brand-audit.json`, JSON.stringify({ visited, findings: brand, errors, notes }, null, 2));
  fs.writeFileSync(`${OUT}/vendor-urls.json`, JSON.stringify({ scope: SCOPE, locales: LOCALES, summary: { byDomain: table, byScreen }, hits }, null, 1));
  console.log(`\nPantallas revisadas: ${visited.length}. Marca visible: ${shownBrand.length} (${groups.size} distintas); en el DOM pero oculta: ${brand.length - brand.filter((f) => f.shown).length}.`);
  console.log(`Plugins de origen: ${fails.length} fallas, ${latent.length} latentes, ${info.length} informes, ${excluded.length} excluidas. Detalle: ${OUT}/vendor-urls.json`);
  process.exit(shownBrand.length || fails.length || unused.length || originalProblem ? 1 : 0);
})();
