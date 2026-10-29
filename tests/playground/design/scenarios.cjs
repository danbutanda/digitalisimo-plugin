// Escenarios DS1–DS13 de design.sh (F7a). Cada uno usa sólo la forma pública de lo que comprueba (HTML, CSSOM, estilo
// calculado, elementor_ajax, opciones por la sonda) y nombra sus comprobaciones de forma estable.
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const R = require('./run.cjs');
const { check, note, control, visit, open, context, fingerprint, diff, sleep, MODE, QUICK, WIDTHS, DEVICE, PRESETS, GRADIENTS, OUT, ENV } = R;

const state = { ids: null, media: null, kit: 0, admin: false, nonce: {}, normal: null };
async function ids(base = '') {
  const r = await control('ids', {}, base);
  if (!base) { state.ids = r.ids; state.media = r.media; state.kit = r.kit; }
  return r;
}
async function admin(base = '') {
  if (!state.admin) { await R.login('admin', 'password', 'admin'); state.admin = true; }
  return 'admin';
}
async function nonceFor(jar, id, base = '') {
  const key = `${jar}|${base}`;
  if (!state.nonce[key]) state.nonce[key] = await R.editorNonce(jar, id, base);
  return state.nonce[key];
}
const url = (key, base = '') => `${base}/${key}/`;
/** Editor del documento como administrador (inicia sesión si hace falta). */
async function editor(id, base = '') {
  await admin();
  return R.openEditor('admin', id, base);
}
const sel = (id) => `.elementor-element-${id}`;
/** Errores de JavaScript del editor; los de red (AxiosError: una petición REST cortada o agotada con un solo worker
 *  bajo carga) se anotan aparte, porque no son del código. */
function editorErrors(ed, label) {
  const network = ed.errors.filter((e) => /AxiosError|Network Error|timeout of \d+ms/i.test(e));
  const other = ed.errors.filter((e) => !network.includes(e));
  if (network.length) note(`${label}: ${network.length} error(es) de red del editor (${network.slice(0, 2).join(' | ').slice(0, 200)}); respuestas con error: ${(ed.failed || []).slice(0, 4).join(' | ') || 'ninguna'}`);
  return check(`${label} sin errores de JavaScript`, !other.length, other.slice(0, 3).join(' | '));
}

/** Lista de escenarios por modo. */
function defaults(mode) {
  if (mode.net) return ['DS12'];
  if (mode.v4) return ['DS11'];
  if (mode.core === 'plugin') return mode.elementor_pin && cmp(mode.elementor_pin, '4.3') < 0 ? ['DS10'] : ['DS1', 'DS2', 'DS3', 'DS4', 'DS5', 'DS6', 'DS7', 'DS8', 'DS10'];
  if (mode.cache) return ['DS1', 'DS2', 'DS3', 'DS4', 'DS5', 'DS6', 'DS7', 'DS8', 'DS9'];
  return ['DS1', 'DS2', 'DS3', 'DS4', 'DS5', 'DS6', 'DS7', 'DS8', 'DS13'];
}
function cmp(a, b) {
  const x = String(a).split('.').map(Number);
  const y = String(b).split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) { const d = (x[i] || 0) - (y[i] || 0); if (d) return d; }
  return 0;
}

// ── Utilidades de página ───────────────────────────────────────────────────────────────────────────────────────
/** Estado visible de unos selectores: `{ sel: { present, visible } }`. */
function visibilityIn(sels) {
  const out = {};
  for (const s of sels) {
    const el = document.querySelector(s);
    if (!el) { out[s] = { present: false, visible: false }; continue; }
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    let hidden = false;
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) { const c = getComputedStyle(n); if (c.display === 'none' || c.visibility === 'hidden') { hidden = true; break; } }
    out[s] = { present: true, visible: !hidden && r.width > 0 && r.height > 0 && cs.opacity !== '0' };
  }
  return out;
}
const visibility = (frame, sels) => frame.evaluate(visibilityIn, sels);
async function cookies(page) {
  const client = await page.createCDPSession();
  const { cookies: list } = await client.send('Network.getAllCookies');
  await client.detach().catch(() => {});
  return list;
}
const docTop = (page, s) => page.evaluate((s) => { const el = document.querySelector(s); if (!el) return null; const r = el.getBoundingClientRect(); return { top: r.top + window.scrollY, h: r.height, vtop: r.top }; }, s);

// ── DS1 · presets ──────────────────────────────────────────────────────────────────────────────────────────────
async function DS1() {
  const { ids: I, kit } = await ids();
  const jar = await admin();
  const doc = I['ds1-presets'];
  const nonce = await nonceFor(jar, doc);
  if (!check('DS1 · nonce de elementor_ajax del administrador', !!nonce)) return;

  // CSS del kit antes de crear presets (V.2.3: los presets no añaden nada al kit).
  const kitCss = async () => {
    const v = await visit(url('ds5-untracked'), async (v) => ({ href: (v.html.match(new RegExp(`id=['"]elementor-post-${kit}-css['"][^>]*href=['"]([^'"]+)`)) || [])[1] || '' }), { twice: false });
    if (!v.href) return null;
    const r = await R.request('GET', v.href.replace(/^https?:\/\/[^/]+/, ''));
    return r.status === 200 ? r.text : null;
  };
  const kitBefore = await kitCss();

  // Crear los presets de tests/sources/design/presets.json por elementor_ajax (su `code`, no el estado HTTP: V-m14).
  const actions = {};
  PRESETS.cases.forEach((c, i) => { actions[`p${i}`] = { action: 'digitalisimo_preset_save', data: { slug: c.slug, type: c.type, label: c.slug, settings: c.preset } }; });
  // WOO=1: un widget de WooCommerce (selectores `.woocommerce {{WRAPPER}} …`); su referencia en cases.json lleva lo mismo.
  const WOO_PRESET = { price_color: '#aa0000', typography_typography: 'custom', typography_font_size: { unit: 'px', size: 31, sizes: [] } };
  if (MODE.woo) actions.precio = { action: 'digitalisimo_preset_save', data: { slug: 'precio', type: 'woocommerce-product-price', label: 'precio', settings: WOO_PRESET } };
  const created = await R.eajax(jar, nonce, doc, actions);
  PRESETS.cases.forEach((c, i) => {
    const r = created.responses[`p${i}`];
    check(`DS1 · crear el preset ${c.slug} por elementor_ajax: code 200`, R.codeOf(r) === 200 && r.success, `HTTP ${created.http}, code ${R.codeOf(r)} ${r && !r.success ? JSON.stringify(r.data).slice(0, 200) : ''}`);
  });
  const opt = (await control('state')).presets;
  check('DS1 · la opción digitalisimo_elements_presets guarda los presets, sin carga automática', opt && opt.presets && PRESETS.cases.every((c) => opt.presets[c.slug]), Object.keys((opt && opt.presets) || {}).join(','));
  const autoload = ((await control('state')).autoload || []).find((r) => r.option_name === 'digitalisimo_elements_presets');
  check('DS1 · digitalisimo_elements_presets sin carga automática', autoload && /^(no|off|auto-off)$/.test(autoload.autoload), JSON.stringify(autoload));

  // Documentos vinculados, guardados como lo hace el editor.
  const saved = {};
  for (const key of ['ds1-presets', 'ds1-rtl'].concat(MODE.woo ? ['ds1-woo-template'] : [])) {
    const s = await R.saveDoc(jar, nonce, key, I);
    saved[key] = s.meta;
    check(`DS1 · guardar ${key} por elementor_ajax (save_builder): code 200`, s.code === 200, `HTTP ${s.http}, code ${s.code} ${s.text || ''}`);
  }
  const data = await control('data', { doc: 'ds1-presets' });
  const linkedOk = saved['ds1-presets'].every((m) => { const el = R.findEl(data.elements, m.L); return el && el.settings && el.settings.digitalisimo_preset === m.slug; });
  check('DS1 · el vínculo digitalisimo_preset queda guardado en cada elemento L', linkedOk);

  // Estilo calculado: L (vinculado, con lo local del caso) = R (lo local más lo que aporta el preset) en cada ancho;
  // L ≠ N (sólo lo local) en algún ancho cuando el preset aporta algo.
  const fronts = {};
  const entries = [...saved['ds1-presets'].map((m) => ({ ...m, doc: 'ds1-presets' })), ...saved['ds1-rtl'].map((m) => ({ ...m, doc: 'ds1-rtl' }))];
  const changed = {};
  const before = {};
  for (const width of WIDTHS) {
    for (const docKey of ['ds1-presets', 'ds1-rtl']) {
      const list = entries.filter((e) => e.doc === docKey);
      const result = await visit(url(docKey), async (v) => {
        const out = { probe: v.probe && v.probe.rtl, fp: {} };
        for (const e of list) for (const k of ['L', 'R', 'N', 'P', 'U']) if (e[k]) out.fp[e[k]] = await fingerprint(v.page, sel(e[k]));
        if (docKey === 'ds1-presets') {
          const t = list.find((e) => e.slug === 'tarjeta');
          if (t && !QUICK) before[width] = { U: await R.shot(v.page, sel(t.U), `ds1-u-${width}-antes.png`), P: await R.shot(v.page, sel(t.P)) };
        }
        return out;
      }, { width });
      fronts[`${docKey}@${width}`] = result.fp;
      if (docKey === 'ds1-rtl') check(`DS1 · ds1-rtl se pinta en RTL (${width} px)`, result.probe === true);
      for (const e of list) {
        const d = diff(result.fp[e.L], result.fp[e.R]);
        check(`DS1 · ${e.slug} (${e.type}) @${width}: el vinculado = la referencia (preset aplicado, lo local gana)`, !d.length, d.slice(0, 6).join(' · '));
        if (diff(result.fp[e.L], result.fp[e.N]).length) changed[e.slug] = true;
      }
    }
  }
  if (MODE.woo) {
    check('DS1 · WOO=1: crear el preset precio (woocommerce-product-price): code 200', R.codeOf(created.responses.precio) === 200, `code ${R.codeOf(created.responses.precio)}`);
    let wooChanged = false;
    for (const width of WIDTHS) {
      const w = await visit('/product/digi-ds-producto/', async (v) => ({ L: await fingerprint(v.page, sel('awcl001')), R: await fingerprint(v.page, sel('awcr001')), N: await fingerprint(v.page, sel('awcn001')) }), { width });
      const d = diff(w.L, w.R);
      check(`DS1 · WOO=1 precio @${width}: el vinculado = la referencia`, !!w.L && !d.length, w.L ? d.slice(0, 4).join(' · ') : 'sin el widget en la página del producto');
      if (w.L && diff(w.L, w.N).length) wooChanged = true;
    }
    check('DS1 · WOO=1: el preset cambia el precio frente a sólo lo local', wooChanged);
  }
  for (const e of entries) {
    if (e.changes) check(`DS1 · ${e.slug}: el preset cambia el estilo frente a sólo lo local en algún ancho`, !!changed[e.slug]);
    else check(`DS1 · ${e.slug}: lo local tapa todo el preset (vinculado = sólo lo local)`, !changed[e.slug]);
  }

  // Vista previa del editor: L = R en cada modo de dispositivo, y el L de la vista previa = el L del front (salvo lo
  // que también difiere entre la R de la vista previa y la del front, que es del entorno del editor).
  if (!QUICK) {
    for (const docKey of ['ds1-presets', 'ds1-rtl']) {
      const ed = await editor(I[docKey]);
      const list = entries.filter((e) => e.doc === docKey);
      for (const width of WIDTHS) {
        const iw = await R.deviceMode(ed, DEVICE[width]);
        let masked = 0;
        const maskedProps = new Set();
        for (const e of list) {
          const l = await fingerprint(ed.frame, sel(e.L));
          const r = await fingerprint(ed.frame, sel(e.R));
          const d = diff(l, r);
          check(`DS1 · vista previa ${e.slug} @${DEVICE[width]}: el vinculado = la referencia`, !d.length, `iframe ${iw} px · ${d.slice(0, 6).join(' · ')}`);
          const fr = fronts[`${docKey}@${width}`][e.R];
          const fl = fronts[`${docKey}@${width}`][e.L];
          const mask = r && fr && r.length === fr.length ? r.map((row, i) => new Set(R.STYLE_PROPS.filter((p) => row[p] !== fr[i][p]))) : null;
          if (mask) { masked += mask.reduce((n, s) => n + s.size, 0); mask.forEach((set) => set.forEach((p) => maskedProps.add(p))); }
          const d2 = diff(l, fl, mask);
          check(`DS1 · ${e.slug} @${DEVICE[width]}: vista previa = front`, !d2.length, d2.slice(0, 6).join(' · '));
        }
        if (masked) note(`${docKey} @${DEVICE[width]}: ${masked} valores (${[...maskedProps].join(', ')}) difieren también en la referencia entre la vista previa y el front (entorno del editor); no se comparan`);
      }
      await R.deviceMode(ed, 'desktop');
      editorErrors(ed, `DS1 · editor de ${docKey}`);
      await ed.ctx.close();
    }
  }

  // Borrar un preset con usos: rechazado con 409.
  const del = await R.eajax(jar, nonce, doc, { d: { action: 'digitalisimo_preset_delete', data: { slug: 'tarjeta' } } });
  check('DS1 · borrar un preset con usos: code 409 y el preset sigue', R.codeOf(del.responses.d) === 409 && !!(await control('state')).presets.presets.tarjeta, `code ${R.codeOf(del.responses.d)}`);

  // Actualizar un preset vacía la caché CSS al final de la petición de elementor_ajax y el CSS se regenera.
  const big = PRESETS.cases.find((c) => c.slug === 'titulo-grande');
  const bigMeta = entries.find((e) => e.slug === 'titulo-grande');
  if (big && bigMeta) {
    const up = await R.eajax(jar, nonce, doc, { u: { action: 'digitalisimo_preset_save', data: { slug: big.slug, type: big.type, label: big.slug, settings: { ...big.preset, typography_font_weight: '300' } } } });
    check('DS1 · actualizar un preset: code 200', R.codeOf(up.responses.u) === 200);
    const w = await visit(url('ds1-presets'), (v) => v.page.evaluate((s) => { const el = document.querySelector(s + ' .elementor-heading-title') || document.querySelector(s); return el ? getComputedStyle(el).fontWeight : ''; }, sel(bigMeta.L)), { twice: false });
    check('DS1 · tras actualizar el preset, el CSS del documento se regenera con el valor nuevo', w === '300', `font-weight ${w}`);
    await R.eajax(jar, nonce, doc, { u: { action: 'digitalisimo_preset_save', data: { slug: big.slug, type: big.type, label: big.slug, settings: big.preset } } });
  }

  // Un editor y un rol con la restricción `design`: `code` 403 en las cuatro acciones; su guardado no cambia el vínculo.
  const tarjeta = entries.find((e) => e.slug === 'tarjeta');
  for (const [user, label, restricted] of [['ds-editor', 'editor', false], ['ds-design', 'rol «sólo contenido» (design)', true]]) {
    const j = await R.login(user, 'password', user);
    const n = await R.editorNonce(j, doc);
    if (!check(`DS1 · ${label}: abre el editor y recibe su nonce`, !!n)) continue;
    const four = {
      save: { action: 'digitalisimo_preset_save', data: { slug: 'intruso', type: 'heading', label: 'Intruso', settings: { title_color: '#00ff00' } } },
      rename: { action: 'digitalisimo_preset_rename', data: { slug: 'tarjeta', label: 'Otra' } },
      delete: { action: 'digitalisimo_preset_delete', data: { slug: 'rojo' } },
      uses: { action: 'digitalisimo_preset_uses', data: { slug: 'tarjeta' } },
    };
    const r = await R.eajax(j, n, doc, four);
    for (const k of Object.keys(four)) check(`DS1 · ${label} → ${four[k].action}: code 403`, R.codeOf(r.responses[k]) === 403 && r.responses[k] && r.responses[k].success === false, `HTTP ${r.http}, code ${R.codeOf(r.responses[k])}`);
    const current = (await control('data', { doc: 'ds1-presets' })).elements;
    const elements = JSON.parse(JSON.stringify(current));
    if (restricted) { const el = R.findEl(elements, tarjeta.L); if (el) el.settings.digitalisimo_preset = ''; }
    const s = await R.eajax(j, n, doc, { save: { action: 'save_builder', data: { status: 'publish', elements, settings: { post_status: 'publish' } } } });
    const after = R.findEl((await control('data', { doc: 'ds1-presets' })).elements, tarjeta.L);
    check(`DS1 · ${label}: su guardado${restricted ? ' (con el vínculo quitado)' : ''} conserva el vínculo`, R.codeOf(s.responses.save) === 200 && after && after.settings.digitalisimo_preset === 'tarjeta', `code ${R.codeOf(s.responses.save)} · vínculo ${after && after.settings.digitalisimo_preset}`);
  }
  const st = (await control('state')).presets.presets;
  check('DS1 · nada de lo que pidieron el editor y el rol «sólo contenido» cambió los presets', !st.intruso && st.rojo && st.tarjeta && st.tarjeta.label === 'tarjeta');

  // «Usar sólo el preset» (en L, con lo local) y «Desvincular y copiar estilos» (en U) desde el menú contextual real.
  if (!QUICK && tarjeta) {
    const ed = await editor(doc);
    const res = await ed.page.evaluate((L, U) => {
      const act = (id, name) => {
        const c = window.elementor.getContainer(id);
        if (!c || !c.view) return 'sin contenedor';
        const type = 'widget' === c.model.get('elType') ? 'widget' : 'container';
        const groups = window.elementor.hooks.applyFilters(`elements/${type}/contextMenuGroups`, [{ name: 'delete', actions: [] }], c.view);
        const g = groups.find((x) => x.name === 'digitalisimo-preset');
        if (!g) return 'sin el grupo digitalisimo-preset';
        const a = g.actions.find((x) => x.name === 'digitalisimo-preset-' + name);
        if (!a) return 'sin la acción ' + name;
        if (!a.isEnabled()) return 'acción deshabilitada';
        a.callback();
        return 'ok';
      };
      return { only: act(L, 'only'), unlink: act(U, 'unlink') };
    }, tarjeta.L, tarjeta.U);
    check('DS1 · menú contextual: «Usar sólo el preset» en el elemento con valores locales', res.only === 'ok', res.only);
    check('DS1 · menú contextual: «Desvincular y copiar estilos»', res.unlink === 'ok', res.unlink);
    await ed.page.evaluate(() => window.$e.run('document/save/update', { force: true })).catch((e) => note(`guardar desde el editor: ${e.message}`));
    let after = null;
    for (let i = 0; i < 40; i++) {
      await sleep(1500);
      const d = await control('data', { doc: 'ds1-presets' });
      const l = R.findEl(d.elements, tarjeta.L);
      const u = R.findEl(d.elements, tarjeta.U);
      if (l && u && !(u.settings || {}).digitalisimo_preset && !(l.settings || {}).title_color) { after = { l, u }; break; }
    }
    check('DS1 · el editor guardó las dos acciones (L sin lo local del preset, U sin vínculo)', !!after, after ? `U copia ${Object.keys(after.u.settings).join(',')}` : 'sin guardar en 60 s');
    await ed.ctx.close();
    if (after) {
      for (const width of WIDTHS) {
        await visit(url('ds1-presets'), async (v) => {
          const l = await fingerprint(v.page, sel(tarjeta.L));
          const p = await fingerprint(v.page, sel(tarjeta.P));
          const u = await fingerprint(v.page, sel(tarjeta.U));
          const d1 = diff(l, p);
          check(`DS1 · «Usar sólo el preset» @${width}: el elemento = el preset sin nada local`, !d1.length, d1.slice(0, 4).join(' · '));
          const d2 = diff(u, fronts[`ds1-presets@${width}`][tarjeta.U]);
          check(`DS1 · «Desvincular y copiar estilos» @${width}: mismo estilo que vinculado`, !d2.length, d2.slice(0, 4).join(' · '));
          const shotL = await R.shot(v.page, sel(tarjeta.L), `ds1-l-${width}-solo-preset.png`);
          const shotU = await R.shot(v.page, sel(tarjeta.U), `ds1-u-${width}-despues.png`);
          const pl = await R.pixelDiff(v.page, shotL, before[width] ? before[width].P : shotL);
          const pu = await R.pixelDiff(v.page, shotU, before[width] ? before[width].U : shotU);
          check(`DS1 · «Usar sólo el preset» @${width}: píxeles frente al preset ≤ 0,5 %`, pl.ratio <= 0.005, `${(pl.ratio * 100).toFixed(2)} % (${pl.size})`);
          check(`DS1 · «Desvincular y copiar estilos» @${width}: píxeles frente a antes ≤ 0,5 %`, pu.ratio <= 0.005, `${(pu.ratio * 100).toFixed(2)} % (${pu.size})`);
          return null;
        }, { width, twice: false });
      }
    }
  }

  // El CSS del kit no cambia por los presets (0 B en el kit, sin selector propio ni `url(`).
  // Se regenera desde el front, como «antes»: regenerado por la vista previa del editor, el núcleo ordena distinto las
  // reglas del kit (e-page-transition de PRO), con los mismos bytes y sin presets de por medio.
  await control('clearcss');
  const kitAfter = await kitCss();
  if (kitBefore !== null && kitAfter !== null) {
    const strip = (s) => s.replace(/\/\*[^*]*\*\//g, '').trim();
    fs.writeFileSync(path.join(OUT, 'kit-css-antes.css'), kitBefore);
    fs.writeFileSync(path.join(OUT, 'kit-css-despues.css'), kitAfter);
    const a = strip(kitBefore);
    const b = strip(kitAfter);
    let at = 0;
    while (at < a.length && a[at] === b[at]) at++;
    check('DS1 · el CSS del kit es el mismo antes y después de crear y usar presets', a === b, `${kitBefore.length} B → ${kitAfter.length} B · primera diferencia en ${at}: «${a.slice(Math.max(0, at - 60), at + 60)}» ≠ «${b.slice(Math.max(0, at - 60), at + 60)}» (kit-css-antes.css, kit-css-despues.css)`);
    check('DS1 · el CSS del kit no lleva digi-preset, :where(.elementor-kit- ni url(', !/digi-preset|:where\(\.elementor-kit-|url\(/.test(kitAfter));
  } else note('CSS del kit: no se encontró su hoja externa (método de impresión en línea); se omite la comparación');

  // Estado final canónico (DS13 compara L con N): se vuelve a guardar el árbol de cases.json.
  const reset = await R.saveDoc(jar, nonce, 'ds1-presets', I);
  check('DS1 · el documento vuelve a su árbol de cases.json', reset.code === 200);
}

// ── DS2 · degradados ───────────────────────────────────────────────────────────────────────────────────────────
function readGradientsIn(entries) {
  const out = {};
  for (const e of entries) {
    const el = document.querySelector('.elementor-element-' + e.id);
    if (!el) { out[e.id] = null; continue; }
    if (/overlay/.test(e.prefix)) { out[e.id] = getComputedStyle(el, '::before').backgroundImage; continue; }
    const a = getComputedStyle(el).backgroundImage;
    const inner = el.querySelector(':scope > .elementor-widget-container');
    out[e.id] = a !== 'none' || !inner ? a : getComputedStyle(inner).backgroundImage;
  }
  return out;
}
function normalizeIn(values) {
  const d = document.createElement('div');
  document.body.appendChild(d);
  const out = values.map((v) => { d.style.backgroundImage = ''; d.style.backgroundImage = v; return getComputedStyle(d).backgroundImage; });
  d.remove();
  return out;
}
async function gradientValues(frame, entries, top = frame) {
  const plain = entries.filter((e) => !/hover/.test(e.prefix));
  const values = await frame.evaluate(readGradientsIn, plain);
  for (const e of entries.filter((x) => /hover/.test(x.prefix))) {
    await frame.evaluate((s) => document.querySelector(s) && document.querySelector(s).scrollIntoView({ block: 'center', behavior: 'instant' }), sel(e.id));
    await frame.hover(sel(e.id)).catch(() => {});
    await sleep(900);
    Object.assign(values, await frame.evaluate(readGradientsIn, [e]));
    await top.mouse.move(1, 1);
    await sleep(400);
  }
  return values;
}
// Diferencias declaradas entre las fixtures (datos crudos de las unitarias) y un sitio real:
// - el núcleo rellena con su valor por defecto (50 %) la posición que falta en una fila del repetidor `digi_stops`;
// - en el servidor, `gradient_position_<dispositivo>` vale «center center» por defecto (device_args del grupo Background
//   del núcleo, elementor/includes/controls/groups/background.php:140-148), así que el front del núcleo no hereda la
//   posición de escritorio en tableta y móvil; su editor sí (allí el valor por defecto del dispositivo es ''). F7a
//   sigue a cada lado: la diferencia entre la vista previa y el front es del núcleo y se anota como conocida.
const SITE_EXPECTED = { 'filas no válidas descartadas': { desktop: 'linear-gradient(180deg, #ff0000 0%, #654321 40%, #abcdef 50%, #0000ff 100%)' } };
const RADIAL_KNOWN = 'posición radial por dispositivo: el front del núcleo usa «center center» (valor por defecto del dispositivo) y su editor hereda la de escritorio; F7a sigue a cada lado';
function radialDevicePosition(entry, device) {
  const c = GRADIENTS.cases[entry.index];
  const p = c.prefix;
  return device !== 'desktop' && c.settings[p + 'gradient_type'] === 'radial' && c.settings[p + 'gradient_position'] && !c.settings[`${p}gradient_position_${device}`];
}
function expectedFor(entry, device) {
  const c = GRADIENTS.cases[entry.index];
  const exp = SITE_EXPECTED[c.name] || c.expected || {};
  const order = ['desktop', 'tablet', 'mobile'];
  for (let i = order.indexOf(device); i >= 0; i--) {
    if (!exp[order[i]]) continue;
    return radialDevicePosition(entry, device) ? exp[order[i]].replace(/^((?:repeating-)?radial-gradient\()at [^,]+,/, '$1at center center,') : exp[order[i]];
  }
  return null;
}
/** Recorre la página para que la carga diferida de fondos del núcleo (`e-lazyloaded`) alcance todos los contenedores. */
async function lazyAll(page) {
  const h = await page.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < h; y += 500) { await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), y); await sleep(60); }
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await sleep(300);
}
async function DS2() {
  await ids();
  const tree = await control('tree', { doc: 'ds2-gradients' });
  const entries = tree.meta;
  const front = {};
  for (const width of WIDTHS) {
    front[width] = await visit(url('ds2-gradients'), async (v) => {
      await lazyAll(v.page);
      const values = await gradientValues(v.page, entries, v.page);
      const wanted = entries.map((e) => expectedFor(e, DEVICE[width]));
      const normal = await v.page.evaluate(normalizeIn, wanted.map((x) => x || 'none'));
      return { values, normal };
    }, { width });
    const { values, normal } = front[width];
    entries.forEach((e, i) => {
      const got = values[e.id];
      if (expectedFor(e, DEVICE[width])) check(`DS2 · ${e.name} @${width}: background-image calculado`, got === normal[i], `${got} · esperado ${normal[i]}`);
      else check(`DS2 · ${e.name} @${width}: sin regla propia (sólo lo del núcleo)`, got !== null && !/conic|repeating/.test(got) && (got.match(/(rgb|hsl|transparent|currentcolor)/gi) || []).length <= 2, got);
    });
  }
  if (QUICK) return;
  const ed = await editor(state.ids['ds2-gradients']).catch((e) => { check('DS2 · el editor abre', false, e.message); return null; });
  if (!ed) return;
  for (const width of WIDTHS) {
    const iw = await R.deviceMode(ed, DEVICE[width]);
    const values = await gradientValues(ed.frame, entries, ed.page);
    for (const e of entries) {
      check(`DS2 · ${e.name} @${DEVICE[width]}: vista previa = front`, values[e.id] === front[width].values[e.id], `iframe ${iw} px · vista previa ${values[e.id]} · front ${front[width].values[e.id]}`, { known: radialDevicePosition(e, DEVICE[width]) ? RADIAL_KNOWN : '' });
    }
  }
  editorErrors(ed, 'DS2 · editor');
  await ed.ctx.close();
}

// ── DS3 · máscaras ─────────────────────────────────────────────────────────────────────────────────────────────
const MASKED = { cmask01: 'contenedor (círculo)', cmask02: 'contenedor (estrella, cubrir)', csec001: 'sección (hexágono)', ccol001: 'columna (corazón)', cmed001: 'SVG del sitio' };
function masksIn(ids) {
  const out = {};
  for (const id of ids) {
    const el = document.querySelector('.elementor-element-' + id);
    if (!el) { out[id] = null; continue; }
    const t = el.classList.contains('elementor-column') ? el.querySelector(':scope > .elementor-widget-wrap') : el;
    el.scrollIntoView({ block: 'center', behavior: 'instant' });
    const cs = getComputedStyle(t);
    const r = el.getBoundingClientRect();
    const hit = document.elementFromPoint(r.left + r.width / 2, Math.max(1, Math.min(window.innerHeight - 1, r.top + r.height / 2)));
    out[id] = { mask: cs.maskImage, webkit: cs.webkitMaskImage, w: r.width, h: r.height, hit: !!(hit && el.contains(hit)) };
  }
  return out;
}
function maskVarsIn() {
  const defs = {};
  const walk = (rules) => {
    for (const r of rules) {
      if (r.cssRules) walk(r.cssRules);
      if (!r.style) continue;
      for (let i = 0; i < r.style.length; i++) {
        const p = r.style[i];
        if (/^--digi-(mask|tile)-/.test(p)) (defs[p] = defs[p] || []).push(r.style.getPropertyValue(p).trim());
      }
    }
  };
  for (const s of document.styleSheets) { try { walk(s.cssRules); } catch (e) { /* otra procedencia */ } }
  return defs;
}
const dataPercent = (v) => /url\(["']?data:image\/svg\+xml,/.test(v) && !/;base64|;utf8/.test(v) && !/[{};#]/.test(v.replace(/^url\(["']?data:image\/svg\+xml,/, '').replace(/["']?\)$/, ''));
async function DS3() {
  const { ids: I } = await ids();
  const all = Object.keys(MASKED).concat(['cbad001', 'cnone01']);
  const r = await visit(url('ds3-masks'), async (v) => {
    await v.page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    const m = await v.page.evaluate(masksIn, all);
    const vars = await v.page.evaluate(maskVarsIn);
    const svgReq = v.requests.filter((u) => /\.svg(\?|$)/i.test(u) || /digitalisimo-elements\/masks|modules\/digitalisimo-design/.test(u));
    return { m, vars, svgReq, errors: v.errors };
  });
  for (const [id, label] of Object.entries(MASKED)) {
    const x = r.m[id];
    check(`DS3 · ${label}: mask-image y -webkit-mask-image con data: en porcentaje`, x && dataPercent(x.mask) && dataPercent(x.webkit), x ? `${x.mask.slice(0, 120)}` : 'sin elemento');
  }
  const bad = r.m.cbad001;
  check('DS3 · SVG hostil de Medios: sin regla y el elemento se ve', bad && bad.mask === 'none' && bad.webkit === 'none' && bad.w > 0 && bad.h > 0 && bad.hit, JSON.stringify(bad));
  check('DS3 · sin máscara: none', r.m.cnone01 && r.m.cnone01.mask === 'none');
  const maskVars = Object.entries(r.vars).filter(([k]) => /^--digi-mask-/.test(k));
  check('DS3 · cada variable --digi-mask-* se define una sola vez y en porcentaje', maskVars.length > 0 && maskVars.every(([, list]) => list.length === 1 && dataPercent(list[0])), maskVars.map(([k, l]) => `${k}×${l.length}`).join(', '));
  check('DS3 · ninguna petición a SVG de uploads, a masks/ ni a la carpeta del módulo', !r.svgReq.length, r.svgReq.slice(0, 5).join(' | '));
  const st = await control('state');
  check('DS3 · copia saneada en uploads/digitalisimo-elements/masks/<sha8>.svg', (st.masks || []).some((f) => /\/digitalisimo-elements\/masks\/[0-9a-f]{8}\.svg$/.test(f)), (st.masks || []).join(', '));
  const data = await control('data', { doc: 'ds3-masks' });
  const med = R.findEl(data.elements, 'cmed001');
  const file = med && med.settings ? med.settings.digitalisimo_mask_file : '';
  check('DS3 · el control oculto digitalisimo_mask_file guarda la ruta relativa', /^digitalisimo-elements\/masks\/[0-9a-f]{8}\.svg$/.test(file || ''), String(file));
  if (file) {
    const f = await control('file', { path: file });
    check('DS3 · la copia existe, es SVG y no lleva script', f.exists && /<svg/.test(f.head) && !/<script|href=/i.test(f.head), f.head.slice(0, 120));
  }
  const badEl = R.findEl(data.elements, 'cbad001');
  check('DS3 · el SVG hostil no deja copia en el control oculto', !(badEl && badEl.settings && badEl.settings.digitalisimo_mask_file));
  if (QUICK) return;
  const ed = await editor(I['ds3-masks']).catch((e) => { check('DS3 · el editor abre', false, e.message); return null; });
  if (!ed) return;
  await sleep(1500);
  const pm = await ed.frame.evaluate(masksIn, all);
  const pv = await ed.frame.evaluate(maskVarsIn);
  for (const [id, label] of Object.entries(MASKED)) check(`DS3 · vista previa · ${label}: mask-image con data: en porcentaje`, pm[id] && dataPercent(pm[id].mask), pm[id] ? pm[id].mask.slice(0, 120) : 'sin elemento');
  check('DS3 · vista previa · las variables --digi-mask-* de la biblioteca existen en porcentaje', Object.keys(pv).some((k) => /^--digi-mask-/.test(k)) && Object.entries(pv).filter(([k]) => /^--digi-mask-/.test(k)).every(([, l]) => l.every(dataPercent)));
  check('DS3 · vista previa · SVG hostil sin regla', pm.cbad001 && pm.cbad001.mask === 'none');
  const foreign = ed.requests.filter((u) => /\.svg(\?|$)/i.test(u) && !u.startsWith(R.BASE));
  check('DS3 · vista previa · el SVG de Medios se pide sólo al mismo origen', !foreign.length, foreign.slice(0, 3).join(' | '));
  editorErrors(ed, 'DS3 · editor');
  await ed.ctx.close();
}

// ── DS4 · patrones ─────────────────────────────────────────────────────────────────────────────────────────────
async function dropdownOver(page, nav, next) {
  const trigger = `${sel(nav)} .elementor-nav-menu--main .menu-item-has-children > a`;
  const el = await page.$(trigger);
  if (!el) return { error: 'sin el elemento del menú con submenú' };
  await page.evaluate((s) => document.querySelector(s).scrollIntoView({ block: 'start', behavior: 'instant' }), sel(nav));
  await page.evaluate(() => window.scrollBy({ top: -10, behavior: 'instant' }));
  // SmartMenus sólo pasa al modo de ratón con dos mousemove seguidos a ≤ 4 px y ≤ 300 ms (jquery.smartmenus.js:46):
  // se mueve el cursor hasta el elemento y luego de 2 en 2 px.
  const box = await el.boundingBox();
  const cx = box.x + box.width / 2;
  const cy = box.y + box.height / 2;
  await page.mouse.move(cx - 6, cy);
  for (const dx of [-4, -2, 0, 2]) await page.mouse.move(cx + dx, cy);
  await sleep(900);
  const res = await page.evaluate((nav, next) => {
    const menu = document.querySelector(`.elementor-element-${nav} .elementor-nav-menu--main .sub-menu`);
    if (!menu) return { error: 'sin submenú' };
    const items = [...menu.querySelectorAll(':scope > li > a')].filter((a) => a.getBoundingClientRect().height > 0);
    if (!items.length) return { error: 'el submenú no se abrió', display: getComputedStyle(menu).display };
    const last = items[items.length - 1].getBoundingClientRect();
    const nx = document.querySelector(`.elementor-element-${next}`).getBoundingClientRect();
    const x = last.left + last.width / 2;
    const y = last.top + last.height / 2;
    const hit = document.elementFromPoint(x, y);
    return { overNext: y > nx.top && y < nx.bottom, onTop: !!(hit && menu.contains(hit)), hit: hit ? (hit.className || hit.tagName).toString().slice(0, 80) : '' };
  }, nav, next);
  await page.mouse.move(2, 2);
  await sleep(700);
  return res;
}
async function DS4() {
  const { ids: I } = await ids();
  const r = await visit(url('ds4-patterns'), async (v) => {
    const after = await v.page.evaluate((s) => { const el = document.querySelector(s); const cs = getComputedStyle(el, '::after'); return { content: cs.content, display: cs.display, color: cs.backgroundColor, mask: cs.maskImage, z: cs.zIndex }; }, sel('dpat001'));
    const button = await v.page.evaluate((s) => {
      const b = document.querySelector(s + ' .elementor-button') || document.querySelector(s + ' a');
      if (!b) return { error: 'sin botón' };
      b.scrollIntoView({ block: 'center', behavior: 'instant' });
      const r = b.getBoundingClientRect();
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      return { ok: !!(hit && hit.closest(s)) };
    }, sel('dbtn001'));
    const menus = {};
    for (const [nav, next] of [['dnm0000', 'dnx0000'], ['dnm0001', 'dnx0001'], ['dnm0002', 'dnx0002']]) menus[nav] = await dropdownOver(v.page, nav, next);
    const vars = await v.page.evaluate(maskVarsIn);
    return { after, button, menus, vars };
  });
  check('DS4 · ::after del patrón: content, display block, color y tesela data: en porcentaje', r.after.content !== 'none' && r.after.display === 'block' && r.after.color === 'rgb(255, 0, 0)' && dataPercent(r.after.mask), JSON.stringify({ ...r.after, mask: r.after.mask.slice(0, 80) }));
  check('DS4 · elementFromPoint sobre el botón dentro del contenedor con patrón devuelve el botón', r.button.ok, JSON.stringify(r.button));
  const m0 = r.menus.dnm0000;
  check('DS4 · el desplegable del menú se ve sobre el contenedor siguiente con «Elevar» por defecto', !m0.error && m0.overNext && m0.onTop, JSON.stringify(m0));
  for (const [nav, label] of [['dnm0001', '«Elevar» apagado'], ['dnm0002', '«Elevar» apagado e índice Z propio']]) {
    const m = r.menus[nav];
    note(`desplegable con ${label}: ${m.error ? m.error : m.onTop ? 'se ve sobre el contenedor siguiente' : 'queda debajo del contenedor siguiente'} (${m.hit || ''})`);
  }
  check('DS4 · con índice Z propio el desplegable se ve (el índice del elemento manda)', r.menus.dnm0002 && r.menus.dnm0002.onTop, JSON.stringify(r.menus.dnm0002));
  const tiles = r.vars['--digi-tile-rejilla'] || [];
  check('DS4 · 20 usos de la tesela «rejilla»: una sola definición de --digi-tile-rejilla', tiles.length === 1 && dataPercent(tiles[0]), `${tiles.length} definiciones`);
  const h = await visit(url('ds4-header-page'), (v) => dropdownOver(v.page, 'dhm0001', 'dhn0001'));
  check('DS4 · cabecera con patrón: su desplegable se ve sobre el primer contenedor de la página', !h.error && h.overNext && h.onTop, JSON.stringify(h));
  if (QUICK) return;
  const ed = await editor(I['ds4-patterns']).catch((e) => { check('DS4 · el editor abre', false, e.message); return null; });
  if (!ed) return;
  const pa = await ed.frame.evaluate((s) => { const cs = getComputedStyle(document.querySelector(s), '::after'); return { mask: cs.maskImage, color: cs.backgroundColor }; }, sel('dpat001'));
  check('DS4 · vista previa · ::after con la tesela data: y su color', dataPercent(pa.mask) && pa.color === 'rgb(255, 0, 0)', JSON.stringify({ ...pa, mask: pa.mask.slice(0, 80) }));
  const pv = await ed.frame.evaluate(maskVarsIn);
  check('DS4 · vista previa · las variables --digi-tile-* existen en porcentaje', Object.entries(pv).filter(([k]) => /^--digi-tile-/.test(k)).length > 0 && Object.entries(pv).filter(([k]) => /^--digi-tile-/.test(k)).every(([, l]) => l.every(dataPercent)));
  editorErrors(ed, 'DS4 · editor');
  await ed.ctx.close();
}

// ── DS5 · «Visitó la página» ───────────────────────────────────────────────────────────────────────────────────
async function trackedVisit(key, { cookies: preset = {}, after = null, base = '' } = {}) {
  const ctx = await context(preset);
  const v = await open(ctx, url(key, base));
  const script = await v.page.evaluate(() => { const s = document.getElementById('digitalisimo-page-visit'); return s ? s.textContent.length : 0; });
  if (after) await after(v.page);
  await sleep(300);
  const list = await cookies(v.page);
  await ctx.close();
  return { script, cookies: list, probe: v.probe, html: v.html };
}
const seen = (list, name) => list.find((c) => c.name === name);
async function DS5() {
  const { ids: I } = await ids();
  const b36 = (id) => Number(id).toString(36);
  const st = await control('state');
  const union = (st.tracked && st.tracked.union) || [];
  check('DS5 · el índice existe con carga automática y rastrea la página de la condición publicada', union.includes(I['ds5-tracked']) && st.in_alloptions.tracked, JSON.stringify(st.tracked));
  check('DS5 · un borrador, una revisión y una plantilla en borrador no entran en el índice', !union.includes(I['ds5-draft-target']) && !union.includes(I['ds5-rev-target']) && !union.includes(I['ds5-kit-target']), union.join(','));
  check('DS5 · la revisión de ds5-rev-src sí nombra su destino (la prueba no es trivial)', (st.revisions || []).some((r) => r.mentions_target), JSON.stringify(st.revisions));
  const kit = await control('kitimported');
  check('DS5 · reindexar tras una importación en «agregar» (borrador) no rastrea su destino', !((kit.tracked && kit.tracked.union) || []).includes(I['ds5-kit-target']));
  check('DS5 · la entrada al índice purgó la página (litespeed_purge_post)', (st.purged || []).includes(I['ds5-tracked']), (st.purged || []).join(','));
  check('DS5 · cookie del sitio: digi_seen con la ruta de home_url', st.cookie && st.cookie.name === 'digi_seen' && st.cookie.path === '/', JSON.stringify(st.cookie));

  // Sin la API y con la opción apagada: sin script ni cookie.
  await control('flag', { name: 'consent_api', value: '' });
  await control('front', { key: 'page_visits_no_consent', value: '0' });
  let t = await trackedVisit('ds5-tracked');
  check('DS5 · sin la WP Consent API y con la opción apagada: sin script ni cookie', !t.script && !seen(t.cookies, 'digi_seen'));

  // Con la opción encendida: script (< 400 B) y cookie con el ID en base 36; las demás páginas sin script.
  await control('front', { key: 'page_visits_no_consent', value: '1' });
  t = await trackedVisit('ds5-tracked');
  const c = seen(t.cookies, 'digi_seen');
  check('DS5 · con la opción «Registrar visitas sin gestor de consentimiento»: script de menos de 400 B', t.script > 0 && t.script < 400, `${t.script} B`);
  check('DS5 · la visita escribe digi_seen con el ID en base 36, ruta / y SameSite=Lax', c && c.value.split('.').includes(b36(I['ds5-tracked'])) && c.path === '/' && c.sameSite === 'Lax', JSON.stringify(c));
  check('DS5 · la página rastreada no sale de la caché de página', t.probe && t.probe.donotcache === false);
  for (const key of ['ds5-untracked', 'ds5-draft-target', 'ds5-rev-target', 'ds5-kit-target']) {
    const u = await trackedVisit(key);
    check(`DS5 · ${key}: sin script`, !u.script);
  }
  const condWith = await visit(url('ds5-cond'), (v) => v.page.evaluate(visibilityIn, [sel('evt0001'), sel('envt001'), sel('eckt001'), sel('efrt001')]).then((vis) => ({ vis, donot: v.probe && v.probe.donotcache, leak: v.html.includes('secreto-ds5') })), { cookies: { digi_seen: b36(I['ds5-tracked']) } });
  check('DS5 · con la cookie: «visitó» se ve y «no visitó» no', condWith.vis[sel('evt0001')].visible && !condWith.vis[sel('envt001')].present, JSON.stringify(condWith.vis));
  check('DS5 · la página con la condición sale de la caché de página (DONOTCACHEPAGE)', condWith.donot === true);
  const condWithout = await visit(url('ds5-cond'), (v) => v.page.evaluate(visibilityIn, [sel('evt0001'), sel('envt001'), sel('efrt001')]));
  check('DS5 · sin la cookie: «visitó» no se ve y «no visitó» sí', !condWithout[sel('evt0001')].present && condWithout[sel('envt001')].visible && condWithout[sel('efrt001')].visible, JSON.stringify(condWithout));
  const ck = await visit(url('ds5-cond'), (v) => v.page.evaluate(visibilityIn, [sel('eckt001')]).then((vis) => ({ vis, leak: v.html.includes('secreto-ds5') })), { cookies: { digi_ds_flag: 'secreto-ds5' } });
  const ckBad = await visit(url('ds5-cond'), (v) => v.page.evaluate(visibilityIn, [sel('eckt001')]), { cookies: { digi_ds_flag: 'otro' } });
  check('DS5 · condición de cookie: con el valor se ve y con otro no', ck.vis[sel('eckt001')].visible && !ckBad[sel('eckt001')].present);
  check('DS5 · el valor de la cookie nunca aparece en el HTML', !ck.leak && !condWith.leak);

  // Filtro digitalisimo_elements_page_visit_tracking en false: sin script.
  await control('flag', { name: 'tracking_off', value: '1' });
  t = await trackedVisit('ds5-tracked');
  check('DS5 · con el filtro de rastreo en false: sin script ni cookie', !t.script && !seen(t.cookies, 'digi_seen'));
  await control('flag', { name: 'tracking_off', value: '' });

  // WP Consent API (simulada en la sonda) con la opción apagada: script, pero sin cookie hasta dar `preferences`.
  await control('front', { key: 'page_visits_no_consent', value: '0' });
  await control('flag', { name: 'consent_api', value: '1' });
  let beforeConsent = null;
  t = await trackedVisit('ds5-tracked', {
    after: async (page) => {
      beforeConsent = (await cookies(page)).some((c) => c.name === 'digi_seen');
      await page.evaluate(() => { document.cookie = 'digi_ds_consent_preferences=allow;path=/'; document.dispatchEvent(new CustomEvent('wp_listen_for_consent_change', { detail: { preferences: 'allow' } })); });
    },
  });
  check('DS5 · con la WP Consent API, antes de dar el consentimiento: sin cookie', beforeConsent === false);
  check('DS5 · con la WP Consent API: se imprime el script', t.script > 0);
  check('DS5 · con la WP Consent API, al dar el consentimiento (evento) se escribe la cookie', !!seen(t.cookies, 'digi_seen'));
  const noConsent = await trackedVisit('ds5-tracked');
  check('DS5 · con la WP Consent API y sin consentimiento: sin cookie', !seen(noConsent.cookies, 'digi_seen'));
  const withConsent = await trackedVisit('ds5-tracked', { cookies: { digi_ds_consent_preferences: 'allow' } });
  check('DS5 · con la WP Consent API y con consentimiento: cookie al cargar', !!seen(withConsent.cookies, 'digi_seen'));
  await control('flag', { name: 'consent_api', value: '' });
  await control('front', { key: 'page_visits_no_consent', value: '0' });
}

// ── DS6 · prioridad del Theme Builder ──────────────────────────────────────────────────────────────────────────
async function DS6() {
  const { ids: I } = await ids();
  const titles = { [I['ds6-h1']]: 'Cabecera uno', [I['ds6-h2']]: 'Cabecera dos', [I['ds6-h3']]: 'Cabecera excluida' };
  const look = async (q = '', label = '') => visit(url('ds6-page') + q, (v) => ({
    with: v.probe && v.probe.header ? v.probe.header.with : null,
    without: v.probe && v.probe.header ? v.probe.header.without : null,
    shown: Object.values(titles).filter((t) => v.html.includes(`>${t}<`)),
  }), { twice: MODE.cache && !label });
  const setPrio = async (key, value) => {
    const r = await control('save', { doc: key, only_settings: '1', settings: JSON.stringify({ digitalisimo_tb_priority: value }) });
    return r.ok;
  };
  await setPrio('ds6-h1', null);
  await setPrio('ds6-h2', null);
  let s = await look();
  check('DS6 · sin manuales: el orden es el mismo que sin el filtro (4.3.0.163)', s.with && R.same(s.with, s.without), JSON.stringify(s));
  const auto = s.with ? s.with[0] : 0;
  check('DS6 · sin manuales: se pinta la primera automática y nunca la excluida', s.shown.length === 1 && s.shown[0] === titles[auto], s.shown.join(','));
  const other = auto === I['ds6-h1'] ? I['ds6-h2'] : I['ds6-h1'];
  const otherKey = other === I['ds6-h1'] ? 'ds6-h1' : 'ds6-h2';
  const autoKey = otherKey === 'ds6-h1' ? 'ds6-h2' : 'ds6-h1';
  await setPrio(otherKey, 10);
  s = await look();
  check('DS6 · manual 10 en la otra: gana esa', s.with && s.with[0] === other && s.shown[0] === titles[other], JSON.stringify(s));
  check('DS6 · el índice digitalisimo_elements_tb_priority la recoge, con carga automática', ((await control('state')).in_alloptions || {}).priority === true);
  // Menor número gana, y entre manuales iguales el ID más alto: se parte de la de ID menor ganando para que el cambio
  // se vea aunque la de ID mayor fuera ya la automática.
  const low = Math.min(I['ds6-h1'], I['ds6-h2']);
  const high = Math.max(I['ds6-h1'], I['ds6-h2']);
  const keyOf = (id) => (id === I['ds6-h1'] ? 'ds6-h1' : 'ds6-h2');
  await setPrio(keyOf(low), 5);
  await setPrio(keyOf(high), 10);
  s = await look();
  check('DS6 · dos manuales distintas: gana el número menor', s.with && s.with[0] === low && s.shown[0] === titles[low], JSON.stringify(s));
  await setPrio(keyOf(low), 10);
  s = await look();
  check('DS6 · dos manuales iguales: gana el ID más alto', s.with && s.with[0] === high && s.shown[0] === titles[high], JSON.stringify(s));
  void autoKey;
  check('DS6 · la excluida con manual 1 nunca se pinta (exclude manda)', !s.shown.includes(titles[I['ds6-h3']]));
  s = await look(`?theme_template_id=${Math.min(I['ds6-h1'], I['ds6-h2'])}`, 'forzada');
  check('DS6 · ?theme_template_id= sigue forzando la plantilla', s.shown[0] === titles[Math.min(I['ds6-h1'], I['ds6-h2'])], s.shown.join(','));
  await setPrio('ds6-h1', null);
  await setPrio('ds6-h2', null);
  s = await look();
  check('DS6 · al quitar los manuales vuelve el orden automático', s.with && R.same(s.with, s.without) && s.with[0] === auto);
}

// ── DS7 · controles de documento ───────────────────────────────────────────────────────────────────────────────
async function DS7() {
  const { ids: I } = await ids();
  const has = (html, t) => html.includes(`>${t}<`);
  const page = (key) => visit(url(key), (v) => ({
    header: has(v.html, 'Cabecera DS7'), footer: has(v.html, 'Pie DS7'), siteHeader: /id="site-header"/.test(v.html), siteFooter: /id="site-footer"/.test(v.html),
    hook: v.html.includes('id="digi-ds-before-header"'), og: /<meta property="og:image"/.test(v.html), theme: v.probe && v.probe.theme,
  }));
  const ctl = await control('controls', { doc: 'ds7-tpl' }, '', { admin: true });
  check('DS7 · tema DIGITALÍSIMO: el documento ofrece ocultar cabecera, pie e imagen destacada', ['digitalisimo_hide_header', 'digitalisimo_hide_footer', 'digitalisimo_hide_featured_image'].every((n) => (ctl.document || []).includes(n)), (ctl.document || []).join(','));
  let a = await page('ds7-tpl');
  let b = await page('ds7-tpl-ctrl');
  check('DS7 · con plantillas: ocultar cabecera y pie las quita', !a.header && !a.footer && b.header && b.footer, JSON.stringify({ a, b }));
  check('DS7 · sigue el gancho digitalisimo_before_header', a.hook && b.hook);
  const fb = await page('ds7-fb');
  const fc = await page('ds7-fb-ctrl');
  check('DS7 · sin plantillas: tampoco el respaldo del tema (#site-header, #site-footer)', !fb.siteHeader && !fb.siteFooter && fc.siteHeader && fc.siteFooter && fb.hook, JSON.stringify({ fb, fc }));
  const featured = async (key, idKey) => visit(url(key), (v) => v.page.evaluate((single, loopId) => ({
    single: !!document.querySelector(`.elementor-element-${single} img`),
    loop: !!document.querySelector(`.e-loop-item-${loopId} .elementor-widget-theme-post-featured-image img`),
    loops: document.querySelectorAll('.e-loop-item').length,
  }), 'gfi0001', I[idKey]).then((x) => ({ ...x, og: /<meta property="og:image"/.test(v.html) })));
  const fa = await featured('ds7-post-a', 'ds7-post-a');
  const fbb = await featured('ds7-post-b', 'ds7-post-b');
  check('DS7 · imagen destacada oculta en la plantilla de entrada', !fa.single && fbb.single, JSON.stringify({ fa, fbb }));
  check('DS7 · la imagen destacada sigue en el bucle que incluye la entrada', fa.loop, JSON.stringify(fa));
  check('DS7 · og:image de SEO sigue en la entrada con la imagen oculta', fa.og);
  const arch = await visit('/category/digi-ds-cat/', (v) => v.page.evaluate((id) => !!document.querySelector(`.e-loop-item-${id} .elementor-widget-theme-post-featured-image img`), I['ds7-post-a']));
  check('DS7 · en el archivo (bucle) la entrada conserva su imagen', arch);
  if (ENV.HELLO !== '1') { note('Hello 3.5.1 no está montado (HELLO_ZIP): se omite la parte con Hello'); return; }
  const h = await control('theme', { to: 'hello' });
  check('DS7 · Hello activo', h.theme === 'hello-elementor', JSON.stringify(h));
  const ctlH = await control('controls', { doc: 'ds7-tpl' }, '', { admin: true });
  check('DS7 · con Hello: «Ocultar cabecera» y «Ocultar pie» no aparecen; «Ocultar imagen destacada» sí', !(ctlH.document || []).includes('digitalisimo_hide_header') && !(ctlH.document || []).includes('digitalisimo_hide_footer') && (ctlH.document || []).includes('digitalisimo_hide_featured_image'), (ctlH.document || []).join(','));
  a = await page('ds7-tpl');
  b = await page('ds7-tpl-ctrl');
  check('DS7 · con Hello la página no cambia: cabecera y pie como en la de control', a.header === b.header && a.footer === b.footer && a.header && a.theme === 'hello-elementor', JSON.stringify({ a, b }));
  const faH = await featured('ds7-post-a', 'ds7-post-a');
  check('DS7 · con Hello la imagen destacada también se oculta en la plantilla y sigue en el bucle', !faH.single && faH.loop, JSON.stringify(faH));
  const back = await control('theme', { to: 'digitalisimo' });
  check('DS7 · vuelve el tema DIGITALÍSIMO', back.theme === 'digitalisimo-theme');
  a = await page('ds7-tpl');
  check('DS7 · de vuelta con el tema DIGITALÍSIMO el ajuste guardado vuelve a aplicarse', !a.header && !a.footer);
  const dirty = (() => { try { return execFileSync('git', ['-C', ENV.ROOT, 'status', '--porcelain', '--', 'digitalisimo-theme'], { encoding: 'utf8' }).trim(); } catch (e) { return 'git: ' + e.message; } })();
  check('DS7 · ningún archivo de digitalisimo-theme/ cambia en la rama', dirty === '', dirty);
}

// ── DS8 · sticky «al fijarse» ──────────────────────────────────────────────────────────────────────────────────
/** Instala, antes de cargar, un registro de si algún `.digi-sticky-show` dentro de un fijo se vio sin fijarse. */
async function flashProbe(page) {
  await page.evaluateOnNewDocument(() => {
    window.__digiFlash = { frames: 0, seen: [] };
    const tick = () => {
      window.__digiFlash.frames++;
      document.querySelectorAll('[data-settings*=\'"sticky":"\'] .digi-sticky-show').forEach((el) => {
        const host = el.closest('[data-settings*=\'"sticky":"\']');
        if (host && host.classList.contains('elementor-sticky--active')) return;
        if (host && host.classList.contains('elementor-sticky__spacer')) return;
        const r = el.getBoundingClientRect();
        if (getComputedStyle(el).display !== 'none' && r.width > 0 && r.height > 0) window.__digiFlash.seen.push(el.className.slice(0, 60));
      });
      if (window.__digiFlash.frames < 240) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });
}
const states = (page, ids) => page.evaluate(visibilityIn, ids.map((id) => sel(id)));
/** CLS total y CLS de lo que queda fuera de un elemento fijo (los desplazamientos de sus hijos son el intercambio). */
function clsProbe() {
  window.__digiCls = { all: 0, outside: 0 };
  new PerformanceObserver((l) => {
    for (const e of l.getEntries()) {
      window.__digiCls.all += e.value;
      const inside = (e.sources || []).length && (e.sources || []).every((x) => x.node && x.node.nodeType === 1 && x.node.closest('[data-settings*=\'"sticky":"\'], .elementor-sticky__spacer'));
      if (!inside) window.__digiCls.outside += e.value;
    }
  }).observe({ type: 'layout-shift', buffered: true });
}
async function DS8() {
  const { ids: I } = await ids();
  for (const width of WIDTHS) {
    const ctx = await context();
    const page = await ctx.newPage();
    await page.setViewport({ width, height: 900 });
    await flashProbe(page);
    const layout = [];
    await page.evaluateOnNewDocument(clsProbe);
    await page.goto(R.BASE + url('ds8-sticky'), { waitUntil: 'networkidle0', timeout: 240000 });
    await sleep(800);
    const flash = await page.evaluate(() => window.__digiFlash);
    check(`DS8 · sin destello al cargar @${width}: ningún «show» visible antes de fijarse`, !flash.seen.length, `${flash.frames} cuadros · ${flash.seen.slice(0, 3).join(' | ')}`);
    const shotBefore = await page.screenshot({ encoding: 'base64' });
    fs.writeFileSync(path.join(OUT, 'shots', `ds8-antes-${width}.png`), Buffer.from(shotBefore, 'base64'));
    const top = await docTop(page, sel('hst0001'));
    const nextBefore = await docTop(page, sel('hnx0001'));
    const s0 = await states(page, ['hsw0001', 'hhd0001', 'hse0001', 'hhe0001', 'hal0001', 'hfrt001']);
    check(`DS8 · antes de fijarse @${width}: show y show-effects ocultos, hide y hide-effects visibles`, !s0[sel('hsw0001')].visible && s0[sel('hhd0001')].visible && !s0[sel('hse0001')].visible && s0[sel('hhe0001')].visible && s0[sel('hal0001')].visible, JSON.stringify(s0));
    check(`DS8 · sin ancestro fijo el «show» se ve @${width}`, s0[sel('hfrt001')].visible);
    const t = top ? Math.round(top.top) : 0;
    if (t < 20) note(`@${width}: el contenedor fijo empieza en ${t} px; el estado «antes» puede coincidir con el fijado`);
    await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), t + 120);
    await sleep(900);
    const s1 = await states(page, ['hsw0001', 'hhd0001', 'hse0001', 'hhe0001']);
    const cls1 = await page.evaluate((s) => document.querySelector(s).className, sel('hst0001'));
    check(`DS8 · fijado sin efectos (${t + 120} px) @${width}: show visible, hide oculto, show-effects oculto`, /elementor-sticky--active/.test(cls1) && !/elementor-sticky--effects/.test(cls1) && s1[sel('hsw0001')].visible && !s1[sel('hhd0001')].visible && !s1[sel('hse0001')].visible && s1[sel('hhe0001')].visible, `${cls1.match(/elementor-sticky--\S+/g)} · ${JSON.stringify(s1)}`);
    await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), t + 900);
    await sleep(900);
    const s2 = await states(page, ['hsw0001', 'hhd0001', 'hse0001', 'hhe0001']);
    const cls2 = await page.evaluate((s) => document.querySelector(s).className, sel('hst0001'));
    check(`DS8 · fijado con efectos (${t + 900} px) @${width}: show-effects visible, hide-effects oculto`, /elementor-sticky--effects/.test(cls2) && s2[sel('hsw0001')].visible && !s2[sel('hhd0001')].visible && s2[sel('hse0001')].visible && !s2[sel('hhe0001')].visible, JSON.stringify(s2));
    const nextAfter = await docTop(page, sel('hnx0001'));
    const jump = nextBefore && nextAfter ? Math.abs(nextAfter.top - nextBefore.top) : 999;
    check(`DS8 · la sección siguiente no salta al fijarse @${width} (≤ 1 px)`, jump <= 1, `${jump.toFixed ? jump.toFixed(2) : jump} px`);
    const cls = await page.evaluate(() => window.__digiCls);
    layout.push(cls);
    await ctx.close();
    if (cls.all > cls.outside + 0.00005) note(`@${width}: CLS ${cls.all.toFixed(4)} dentro del contenedor fijo (el intercambio de show y hide, que es la función); fuera, ${cls.outside.toFixed(4)}`);
    // Referencia sin estados: el CLS añadido por los estados debe ser 0.
    const ref = await visit(url('ds8-plain'), async (v) => {
      await v.page.evaluate(clsProbe);
      const tt = await docTop(v.page, sel('hpl0001'));
      await v.page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), (tt ? tt.top : 0) + 900);
      await sleep(900);
      return v.page.evaluate(() => window.__digiCls.outside);
    }, { width, twice: false });
    check(`DS8 · sin CLS añadido fuera del contenedor fijo @${width}`, cls.outside <= ref + 0.001, `con estados ${cls.outside.toFixed(4)} · sin estados ${Number(ref).toFixed(4)}`);
  }

  // sticky: bottom: activo sin desplazar y sin oscilar (menos de 3 cambios de clase en 2 s).
  const bottom = await visit(url('ds8-bottom'), async (v) => {
    await v.page.evaluate((s) => { window.__digiChanges = 0; new MutationObserver(() => window.__digiChanges++).observe(document.querySelector(s), { attributes: true, attributeFilter: ['class'] }); }, sel('hbt0001'));
    await sleep(2000);
    const changes = await v.page.evaluate(() => window.__digiChanges);
    const cls = await v.page.evaluate((s) => document.querySelector(s).className, sel('hbt0001'));
    const s = await states(v.page, ['hbs0001', 'hbh0001']);
    const natural = await v.page.evaluate(() => { const sp = document.querySelector('.elementor-sticky__spacer') || document.querySelector('.elementor-element-hbt0001'); return sp.getBoundingClientRect().top + window.scrollY; });
    await v.page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), Math.max(0, natural - 300));
    await sleep(900);
    const s2 = await states(v.page, ['hbs0001', 'hbh0001']);
    const cls2 = await v.page.evaluate((s) => document.querySelector(s).className, sel('hbt0001'));
    return { changes, active: /elementor-sticky--active/.test(cls), s, s2, active2: /elementor-sticky--active/.test(cls2) };
  }, { twice: false, lazy: false });
  check('DS8 · sticky abajo: fijado sin desplazar, show visible y hide oculto', bottom.active && bottom.s[sel('hbs0001')].visible && !bottom.s[sel('hbh0001')].visible, JSON.stringify(bottom));
  check('DS8 · sticky abajo: sin oscilación (menos de 3 cambios de clase en 2 s)', bottom.changes < 3, `${bottom.changes} cambios`);
  check('DS8 · sticky abajo: al llegar a su sitio se suelta y el show vuelve a ocultarse', !bottom.active2 && !bottom.s2[sel('hbs0001')].visible && bottom.s2[sel('hbh0001')].visible, JSON.stringify(bottom.s2));

  // Dispositivo fuera de sticky_on: no se fija; «show» queda oculto (declarado) y «hide» visible.
  for (const width of QUICK ? [1280] : [360, 1280]) {
    const dv = await visit(url('ds8-device'), async (v) => {
      const tt = await docTop(v.page, sel('hdv0001'));
      await v.page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), (tt ? tt.top : 0) + 900);
      await sleep(900);
      const cls = await v.page.evaluate((s) => document.querySelector(s).className, sel('hdv0001'));
      return { active: /elementor-sticky--active/.test(cls), s: await states(v.page, ['hds0001', 'hdh0001']) };
    }, { width, twice: false, lazy: false });
    if (width === 1280) check('DS8 · sticky_on sólo escritorio @1280: se fija y show se ve', dv.active && dv.s[sel('hds0001')].visible && !dv.s[sel('hdh0001')].visible, JSON.stringify(dv));
    else check('DS8 · fuera de sticky_on @360: no se fija, show oculto (declarado) y hide visible', !dv.active && !dv.s[sel('hds0001')].visible && dv.s[sel('hdh0001')].visible, JSON.stringify(dv));
  }
  check('DS8 · la hoja digitalisimo-sticky-state sólo llega a las páginas que usan el control', await visit(url('ds8-plain'), (v) => !/digitalisimo-sticky-state/.test(v.html), { twice: false }) && await visit(url('ds8-sticky'), (v) => /digitalisimo-sticky-state/.test(v.html), { twice: false }));

  if (QUICK) return;
  const ed = await editor(I['ds8-sticky']).catch((e) => { check('DS8 · el editor abre', false, e.message); return null; });
  if (!ed) return;
  const s = await states(ed.frame, ['hsw0001', 'hhd0001', 'hse0001', 'hhe0001']);
  check('DS8 · en el editor se ven todos los estados', Object.values(s).every((x) => x.visible), JSON.stringify(s));
  await ed.ctx.close();
}

// ── DS9 · caché de elementos a 24 h ────────────────────────────────────────────────────────────────────────────
async function DS9() {
  const { ids: I } = await ids();
  check('DS9 · la caché de elementos está a 24 h (opción sin valor = por defecto)', MODE.cache === true);
  const look = (cookies) => visit(url('ds9-sticky'), async (v) => {
    const before = await states(v.page, ['isl0001', 'ich0001', 'ich0002', 'ihd0001']);
    const tt = await docTop(v.page, sel('ist0001'));
    await v.page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), (tt ? tt.top : 0) + 900);
    await sleep(900);
    const after = await states(v.page, ['isl0001', 'ich0001', 'ich0002', 'ihd0001']);
    const logo = await v.page.evaluate((s) => { const i = document.querySelector(s + ' img'); return i ? i.getAttribute('src') : ''; }, sel('isl0001'));
    return { before, after, logo };
  }, { cookies, twice: false });
  const runs = [];
  for (const [label, cookies] of [['sin cookie', {}], ['con cookie', { digi_ds_vip: '1' }], ['sin cookie otra vez', {}], ['con cookie otra vez', { digi_ds_vip: '1' }]]) runs.push([label, cookies, await look(cookies)]);
  for (const [label, cookies, r] of runs) {
    const vip = !!cookies.digi_ds_vip;
    check(`DS9 · ${label}: el Logotipo dinámico (show) oculto antes y visible al fijarse, con su imagen`, !r.before[sel('isl0001')].visible && r.after[sel('isl0001')].visible && !!r.logo, JSON.stringify({ b: r.before[sel('isl0001')], a: r.after[sel('isl0001')], logo: r.logo }));
    if (vip) check(`DS9 · ${label}: el hijo con condición show/hide aparece y cambia al fijarse`, r.before[sel('ich0001')].present && !r.before[sel('ich0001')].visible && r.after[sel('ich0001')].visible && r.before[sel('ich0002')].visible && !r.after[sel('ich0002')].visible, JSON.stringify({ b: r.before, a: r.after }));
    else check(`DS9 · ${label}: los hijos con condición no se pintan (la caché no congela al anterior)`, !r.before[sel('ich0001')].present && !r.before[sel('ich0002')].present);
    check(`DS9 · ${label}: hide visible antes y oculto al fijarse`, r.before[sel('ihd0001')].visible && !r.after[sel('ihd0001')].visible);
  }
  const img = (key) => visit(url(key), (v) => v.page.evaluate(() => !!document.querySelector('.elementor-element-gfi0001 img')), { twice: false });
  const order = [['ds9-post-a', false], ['ds9-post-b', true], ['ds9-post-a', false], ['ds9-post-b', true], ['ds9-post-b', true], ['ds9-post-a', false]];
  const got = [];
  for (const [key] of order) got.push(await img(key));
  check('DS9 · imagen destacada en la plantilla de entrada compartida: oculta sólo en la entrada que lo pide, en cualquier orden', order.every(([, want], i) => got[i] === want), JSON.stringify(order.map(([k], i) => `${k}:${got[i]}`)));
  void I;
}

// ── DS10 · plugin Elementor original ───────────────────────────────────────────────────────────────────────────
async function DS10() {
  const ping = await control('ping');
  check('DS10 · núcleo del plugin Elementor original (no el incluido)', ping.bundled === false && ping.core === MODE.elementor_pin, `${ping.core} (incluido: ${ping.bundled})`);
  const old = cmp(MODE.elementor_pin, '4.3') < 0;
  if (old) {
    const miss = ping.missing || {};
    check(`DS10 · con ${MODE.elementor_pin} (< 4.3) los submódulos de F7a no registran nada (guardas)`, Object.keys(miss).length >= 7 && Object.values(miss).every((l) => l.some((x) => /ELEMENTOR_VERSION/.test(x))), JSON.stringify(miss).slice(0, 300));
    await ids();
    for (const key of ['ds3-masks', 'ds4-patterns', 'ds5-cond', 'ds8-sticky', 'ds2-gradients']) {
      const v = await visit(url(key), (v) => ({ status: v.status, fatal: /critical error|Fatal error/i.test(v.html) }), { twice: false });
      check(`DS10 · ${key} con ${MODE.elementor_pin}: se sirve sin error fatal`, v.status === 200 && !v.fatal, JSON.stringify(v));
    }
    const m = await visit(url('ds3-masks'), (v) => v.page.evaluate(masksIn, ['cmask01']), { twice: false });
    check(`DS10 · con ${MODE.elementor_pin} la máscara queda inerte`, m.cmask01 && m.cmask01.mask === 'none', JSON.stringify(m));
    const s = await visit(url('ds8-sticky'), (v) => states(v.page, ['hsw0001']), { twice: false });
    check(`DS10 · con ${MODE.elementor_pin} el estado del sticky queda inerte («show» visible)`, s[sel('hsw0001')].visible);
    const ed = await R.login('admin', 'password', 'admin').then(() => editor(state.ids['ds3-masks'])).catch((e) => ({ error: e.message }));
    check(`DS10 · con ${MODE.elementor_pin} el editor abre`, !ed.error, ed.error || '');
    if (ed.ctx) await ed.ctx.close();
    return;
  }
  check('DS10 · F7a sin símbolos que falten con el plugin original', !ping.missing || !Object.keys(ping.missing).length, JSON.stringify(ping.missing));
}

/** CORE=plugin: cada comprobación de DS1–DS8 debe dar lo mismo que en el modo normal (COMPARE_SUMMARY). */
function compareWithNormal() {
  if (MODE.core !== 'plugin' || cmp(MODE.elementor_pin, '4.3') < 0) return;
  const file = process.env.COMPARE_SUMMARY || '/var/tmp/digi-f7a/design-normal/summary.json';
  if (!fs.existsSync(file)) { R.scenario('DS10'); note(`sin ${file}: no se compara con el modo normal`); return; }
  const normal = JSON.parse(fs.readFileSync(file, 'utf8'));
  const map = {};
  for (const sc of Object.values(normal.scenarios || {})) for (const c of sc.checks) map[c.name] = c.ok;
  R.scenario('DS10');
  let n = 0;
  const diffs = [];
  for (const [name, sc] of Object.entries(R.summary.scenarios)) {
    if (name === 'DS10') continue;
    for (const c of sc.checks) if (c.name in map) { n++; if (map[c.name] !== c.ok) diffs.push(`${c.name}: normal ${map[c.name]} · plugin ${c.ok}`); }
  }
  check(`DS10 · ${n} comprobaciones de DS1–DS8 dan lo mismo que con el núcleo incluido`, n > 0 && !diffs.length, diffs.slice(0, 8).join(' | '));
}

// ── DS11 · v4 ──────────────────────────────────────────────────────────────────────────────────────────────────
async function DS11() {
  const { ids: I } = await ids();
  const c = await control('controls', { widgets: 'e-heading,heading', elements: 'e-div-block,container' }, '', { admin: true });
  check('DS11 · los elementos atómicos no muestran controles de F7a', Array.isArray(c['e-heading']) && !c['e-heading'].length && Array.isArray(c['e-div-block']) && !c['e-div-block'].length, JSON.stringify({ h: c['e-heading'], d: c['e-div-block'] }));
  check('DS11 · los v3 sí (encabezado: preset y degradado; contenedor: máscara, patrón y sticky)', (c.heading || []).includes('digitalisimo_preset') && (c.container || []).includes('digitalisimo_mask') && (c.container || []).includes('digitalisimo_pattern') && (c.container || []).includes('digitalisimo_sticky_state'), JSON.stringify({ h: c.heading, c: c.container }));
  const data = await control('data', { doc: 'ds11-v4' });
  const h4 = R.findEl(data.elements, 'k4h0001');
  const raw = JSON.stringify(h4 && h4.settings && h4.settings['display-conditions']);
  check('DS11 · el documento v4 guardó el centinela en el prop display-conditions', /digitalisimo_special_page/.test(raw) && /digitalisimo_f7a/.test(raw), raw.slice(0, 300));
  const look = (cookies) => visit(url('ds11-v4'), (v) => ({ shown: /data-(?:interaction-)?id="k4h0001"/.test(v.html), other: /data-(?:interaction-)?id="k4h0002"/.test(v.html), donot: v.probe && v.probe.donotcache }), { cookies, twice: false });
  const seq = [];
  for (const ck of [{}, { digi_ds_vip: '1' }, {}, { digi_ds_vip: '1' }]) seq.push([!!ck.digi_ds_vip, await look(ck)]);
  check('DS11 · un elemento v4 con condición es dinámico con la caché a 24 h (cada visita decide)', seq.every(([vip, r]) => r.shown === vip && r.other), JSON.stringify(seq));
  check('DS11 · la página con la condición v4 variable sale de la caché de página', seq.every(([, r]) => r.donot === true));
  // Modal de condiciones: el control v4 lo abre sin `conditionsConfig` ni `rootElement` (H); el de v3, con `rootElement`.
  await R.login('admin', 'password', 'admin');
  const ed = await editor(I['ds11-v4']).catch((e) => ({ error: e.message }));
  if (ed.error) { check('DS11 · el editor abre', false, ed.error); return; }
  const modal = await ed.page.evaluate(async () => {
    const out = {};
    const keys = (cfg) => (cfg && cfg.flattenedConditionOptions ? cfg.flattenedConditionOptions.filter((x) => !x.isGroup).map((x) => x.key) : null);
    for (const [label, id, root] of [['v4', 'k4h0001', false], ['v3', 'k3h0001', true]]) {
      try { window.$e.run('document/elements/select', { container: window.elementor.getContainer(id) }); } catch (e) { out[label + 'select'] = e.message; }
      const props = {};
      const detail = { props };
      if (root) detail.rootElement = document.body;
      window.dispatchEvent(new CustomEvent('elementor/display-conditions/open', { detail }));
      out[label] = keys(props.conditionsConfig);
      window.dispatchEvent(new CustomEvent('elementor/display-conditions/close', { detail: {} }));
      await new Promise((r) => setTimeout(r, 300));
    }
    out.all = Object.keys((window.elementor.config.displayConditions || {}).conditions || {});
    return out;
  });
  const F7A = ['digitalisimo_cookie', 'digitalisimo_page_visit', 'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_product_stock_of', 'digitalisimo_search'];
  check('DS11 · el modal abierto desde un elemento v4 no ofrece las condiciones de F7a', Array.isArray(modal.v4) && modal.v4.length > 0 && !modal.v4.some((k) => F7A.includes(k)), JSON.stringify(modal.v4 || modal).slice(0, 300));
  check('DS11 · desde un elemento v3 (administrador) sí se ofrecen', modal.v3 === null ? F7A.filter((k) => k !== 'digitalisimo_product_stock_of').every((k) => modal.all.includes(k)) : F7A.filter((k) => k !== 'digitalisimo_product_stock_of').every((k) => modal.v3.includes(k)), JSON.stringify(modal.v3 || modal.all).slice(0, 300));
  await ed.ctx.close();
}

// ── DS12 · red de subdirectorios ───────────────────────────────────────────────────────────────────────────────
async function DS12() {
  const uno = await ids('/uno');
  const dos = await ids('/dos');
  check('DS12 · los IDs se repiten entre /uno/ y /dos/', uno.ids['ds12-track'] === dos.ids['ds12-track'] && uno.ids['ds12-cond'] === dos.ids['ds12-cond'], JSON.stringify({ uno: uno.ids['ds12-track'], dos: dos.ids['ds12-track'] }));
  // Las sales (auth_key, logged_in_salt…) las guarda WordPress en sitemeta con el primer inicio de sesión (wp_salt()):
  // la sesión y el editor se abren antes de la foto inicial.
  await R.login('admin', 'password', 'admin');
  await R.editorNonce('admin', uno.ids['ds12-presets'], '/uno');
  const meta0 = (await control('sitemeta')).rows;
  // Opción de red: los sitios heredan «Registrar visitas sin gestor de consentimiento».
  await control('front', { key: 'page_visits_no_consent', value: '1', network: '1' });
  const st2 = await control('state', {}, '/uno');
  const st3 = await control('state', {}, '/dos');
  check('DS12 · cookie por sitio: digi_seen_<blog> con la ruta de cada sitio', st2.cookie && st3.cookie && st2.cookie.name === `digi_seen_${st2.blog}` && st2.cookie.path === '/uno/' && st3.cookie.name === `digi_seen_${st3.blog}` && st3.cookie.path === '/dos/', JSON.stringify({ a: st2.cookie, b: st3.cookie }));
  const ctx = await context();
  const v1 = await open(ctx, '/uno/ds12-track/');
  const s1 = await v1.page.evaluate(() => (document.getElementById('digitalisimo-page-visit') || {}).textContent || '');
  await sleep(300);
  const list = await cookies(v1.page);
  const mine = list.filter((c) => /^digi_seen/.test(c.name));
  check('DS12 · la visita a /uno/ escribe sólo la cookie de /uno/', s1 && mine.length === 1 && mine[0].name === st2.cookie.name && mine[0].path === '/uno/', JSON.stringify(mine));
  const c1 = await open(ctx, '/uno/ds12-cond/');
  const c2 = await open(ctx, '/dos/ds12-cond/');
  const vis1 = await c1.page.evaluate(visibilityIn, [sel('mvt0001'), sel('mfrt001')]);
  const vis2 = await c2.page.evaluate(visibilityIn, [sel('mvt0001'), sel('mfrt001')]);
  check('DS12 · /uno/ ve «visitó» y /dos/ no (mismo ID, sin cruce entre sitios)', vis1[sel('mvt0001')].visible && !vis2[sel('mvt0001')].present && vis2[sel('mfrt001')].visible, JSON.stringify({ vis1, vis2 }));
  await ctx.close();
  // Presets sólo en su sitio.
  const n2 = await R.editorNonce('admin', uno.ids['ds12-presets'], '/uno');
  const t = PRESETS.cases.find((c) => c.slug === 'tarjeta');
  const cr = await R.eajax('admin', n2, uno.ids['ds12-presets'], { p: { action: 'digitalisimo_preset_save', data: { slug: 'tarjeta', type: 'heading', label: 'tarjeta', settings: t.preset } } }, '/uno');
  check('DS12 · crear un preset en /uno/: code 200', R.codeOf(cr.responses.p) === 200, `code ${R.codeOf(cr.responses.p)}`);
  for (const [base, idsx] of [['/uno', uno.ids], ['/dos', dos.ids]]) {
    const n = await R.editorNonce('admin', idsx['ds12-presets'], base);
    const s = await R.saveDoc('admin', n, 'ds12-presets', idsx, { base });
    check(`DS12 · guardar ds12-presets en ${base}/: code 200`, s.code === 200, `code ${s.code}`);
  }
  // Prioridad manual sólo en /uno/.
  await control('save', { doc: 'ds12-h1', only_settings: '1', settings: JSON.stringify({ digitalisimo_tb_priority: 5 }) }, '/uno');
  const opts = await control('blogopts');
  const b2 = opts[st2.blog] || {};
  const b3 = opts[st3.blog] || {};
  check('DS12 · los presets viven sólo en el sitio que los creó', b2.presets && b2.presets.presets && b2.presets.presets.tarjeta && !(b3.presets && b3.presets.presets && b3.presets.presets.tarjeta), JSON.stringify({ b2: b2.presets && Object.keys(b2.presets.presets || {}), b3: b3.presets && Object.keys((b3.presets || {}).presets || {}) }));
  const link2 = R.findEl((await control('data', { doc: 'ds12-presets' }, '/uno')).elements, 'mpl0001');
  const link3 = R.findEl((await control('data', { doc: 'ds12-presets' }, '/dos')).elements, 'mpl0001');
  check('DS12 · el vínculo se conserva en /uno/ y se vacía en /dos/ (preset de otro sitio)', link2 && link2.settings.digitalisimo_preset === 'tarjeta' && link3 && !link3.settings.digitalisimo_preset, JSON.stringify({ a: link2 && link2.settings.digitalisimo_preset, b: link3 && link3.settings.digitalisimo_preset }));
  check('DS12 · el índice de prioridad sólo en /uno/', b2.priority && Object.keys(b2.priority).length > 0 && (!b3.priority || !Object.keys(b3.priority).length), JSON.stringify({ b2: b2.priority, b3: b3.priority }));
  check('DS12 · cada sitio tiene su índice de páginas rastreadas', b2.tracked && b3.tracked && (b2.tracked.union || []).includes(uno.ids['ds12-track']) && (b3.tracked.union || []).includes(dos.ids['ds12-track']));
  const h1 = await visit('/uno/ds12-cond/', (v) => v.probe && v.probe.header && v.probe.header.with, { twice: false });
  const h2 = await visit('/dos/ds12-cond/', (v) => v.probe && v.probe.header && v.probe.header, { twice: false });
  check('DS12 · la manual de /uno/ no cambia el orden de /dos/', h1 && h1[0] === uno.ids['ds12-h1'] && h2 && R.same(h2.with, h2.without), JSON.stringify({ h1, h2 }));
  const meta1 = (await control('sitemeta')).rows;
  const changed = Object.keys({ ...meta0, ...meta1 }).filter((k) => meta0[k] !== meta1[k] && !/^_site_transient_/.test(k) && k !== 'digitalisimo_elements_front_defaults');
  check('DS12 · sitemeta idéntica salvo _site_transient_* y el valor de red que se cambió a propósito', !changed.length, changed.join(', '));
  await control('front', { key: 'page_visits_no_consent', value: '0', network: '1' });
}

// ── DS13 · vuelta a 4.3.0.163 instalada sobre el resultado ─────────────────────────────────────────────────────
async function DS13() {
  const { ids: I } = await ids();
  if (!ENV.OLD_ELEMENTS || !fs.existsSync(path.join(ENV.OLD_ELEMENTS, 'pro-elements.php'))) { check('DS13 · el ZIP 4.3.0.163 está extraído', false, ENV.OLD_ELEMENTS); return; }
  // Antes: prioridad manual en la cabecera que pierde automáticamente (con 4.3.0.164 gana; con 4.3.0.163, no).
  const before = await visit(url('ds6-page'), (v) => v.probe.header, { twice: false });
  const auto = before.without[0];
  const otherKey = auto === I['ds6-h1'] ? 'ds6-h2' : 'ds6-h1';
  await control('save', { doc: otherKey, only_settings: '1', settings: JSON.stringify({ digitalisimo_tb_priority: 10 }) });
  const withManual = await visit(url('ds6-page'), (v) => v.probe.header.with[0], { twice: false });
  check('DS13 · antes de volver: la manual manda con F7a', withManual === I[otherKey]);
  const fatal0 = R.logSize('fatal.log');
  const notice0 = R.logSize('php-errors.log');
  // Instalar sobre el resultado: se reemplaza el contenido de la carpeta montada (como una actualización in situ).
  const dst = ENV.SRC_ELEMENTS;
  if (!dst.startsWith(OUT + '/')) { check('DS13 · la carpeta montada es la copia fija de la corrida', false, dst); return; }
  execFileSync('bash', ['-c', `find "${dst}" -mindepth 1 -maxdepth 1 -exec rm -rf {} + && cp -a "${ENV.OLD_ELEMENTS}/." "${dst}/"`]);
  const ping = await control('ping');
  check('DS13 · 4.3.0.163 cargado (sin el módulo de diseño de F7a)', ping.design === false && /4\.3\.0\.163/.test(fs.readFileSync(path.join(dst, 'pro-elements.php'), 'utf8')), JSON.stringify({ design: ping.design, core: ping.core }));
  // Las condiciones se evalúan al pintar: lo condicionado por F7a no se ve (centinela), aunque se cumpla.
  const cond = await visit(url('ds5-cond'), (v) => v.page.evaluate(visibilityIn, [sel('evt0001'), sel('envt001'), sel('eckt001'), sel('efrt001')]), { cookies: { digi_seen: Number(I['ds5-tracked']).toString(36), digi_ds_flag: 'secreto-ds5' }, twice: false });
  check('DS13 · lo condicionado por F7a no se ve (visitó, no visitó y cookie, aunque se cumplan)', !cond[sel('evt0001')].present && !cond[sel('envt001')].present && !cond[sel('eckt001')].present && cond[sel('efrt001')].visible, JSON.stringify(cond));
  const vip = await visit(url('ds9-sticky'), (v) => v.page.evaluate(visibilityIn, [sel('ich0001'), sel('ich0002')]), { cookies: { digi_ds_vip: '1' }, twice: false });
  check('DS13 · el hijo con condición de cookie tampoco se ve con la cookie', !vip[sel('ich0001')].present && !vip[sel('ich0002')].present, JSON.stringify(vip));
  const tb = await visit(url('ds6-page'), (v) => v.probe.header, { twice: false });
  check('DS13 · orden automático del Theme Builder (la prioridad manual queda inerte)', tb.with[0] === auto, JSON.stringify(tb));
  const doc = await visit(url('ds7-tpl'), (v) => ({ header: v.html.includes('>Cabecera DS7<'), footer: v.html.includes('>Pie DS7<') }), { twice: false });
  check('DS13 · cabecera y pie visibles en la página que los ocultaba', doc.header && doc.footer);
  // Hasta que se regenera el CSS, las hojas ya generadas conservan lo de F7a; se anota y se regenera.
  const stale = await visit(url('ds3-masks'), (v) => v.page.evaluate(masksIn, ['cmask01']), { twice: false });
  note(`antes de regenerar el CSS, la máscara del contenedor ${stale.cmask01 && stale.cmask01.mask !== 'none' ? 'sigue en la hoja ya generada' : 'ya no está'}`);
  await control('clearcss');
  const m = await visit(url('ds3-masks'), (v) => v.page.evaluate(masksIn, ['cmask01', 'csec001', 'ccol001', 'cmed001']), { twice: false });
  check('DS13 · tras regenerar el CSS, sin máscaras', Object.values(m).every((x) => x && x.mask === 'none' && x.w > 0), JSON.stringify(m).slice(0, 300));
  const p = await visit(url('ds4-patterns'), (v) => v.page.evaluate((s) => getComputedStyle(document.querySelector(s), '::after').maskImage, sel('dpat001')), { twice: false });
  check('DS13 · tras regenerar el CSS, sin patrones', p === 'none' || p === '', p);
  const gmeta = (await control('tree', { doc: 'ds2-gradients' })).meta.filter((e) => /parada intermedia|cónico con ángulo|ocho paradas/.test(e.name));
  const g = await visit(url('ds2-gradients'), async (v) => { await lazyAll(v.page); return v.page.evaluate(readGradientsIn, gmeta); }, { twice: false });
  check('DS13 · degradados: vuelven a A → B del núcleo (sin paradas intermedias ni cónicos)', Object.values(g).every((x) => x && !/conic|repeating/.test(x) && (x.match(/rgb/g) || []).length <= 2), JSON.stringify(g));
  const s = await visit(url('ds8-sticky'), (v) => states(v.page, ['hsw0001', 'hhd0001']), { twice: false });
  check('DS13 · sticky: «show» visible sin fijarse (la hoja de F7a ya no se carga)', s[sel('hsw0001')].visible && s[sel('hhd0001')].visible, JSON.stringify(s));
  const pr = await control('tree', { doc: 'ds1-presets' }).catch(() => null);
  if (pr) {
    const list = pr.meta.filter((e) => e.slug !== 'rojo');
    // El detalle lleva las diferencias (nodo y propiedad), no sólo cuántas: una sola propiedad por par apunta a un valor que sobrevive a la vuelta.
    const res = await visit(url('ds1-presets'), async (v) => { const out = []; for (const e of list) { const d = diff(await fingerprint(v.page, sel(e.L)), await fingerprint(v.page, sel(e.N))); out.push([e.slug, d.length, d.slice(0, 3)]); } return out; }, { twice: false });
    check('DS13 · presets: los vinculados vuelven a sus valores locales (= sólo lo local)', res.every(([, n]) => n === 0), JSON.stringify(res.filter(([, n]) => n)));
  }
  const ed = await R.login('admin', 'password', 'admin').then(() => editor(I['ds5-cond'])).catch((e) => ({ error: e.message }));
  check('DS13 · el editor abre con 4.3.0.163 sobre los datos de F7a', !ed.error, ed.error || '');
  if (ed.ctx) {
    editorErrors(ed, 'DS13 · editor');
    await ed.ctx.close();
  }
  for (const key of ['ds1-presets', 'ds2-gradients', 'ds3-masks', 'ds4-patterns', 'ds5-cond', 'ds6-page', 'ds7-tpl', 'ds7-post-a', 'ds8-sticky', 'ds9-sticky']) {
    const v = await visit(url(key), (v) => ({ status: v.status, fatal: /critical error|Fatal error/i.test(v.html) }), { twice: false });
    check(`DS13 · ${key} se sirve sin error fatal`, v.status === 200 && !v.fatal, JSON.stringify(v));
  }
  const fatal = R.serverLog('fatal.log', fatal0);
  check('DS13 · sin errores fatales de PHP', !fatal, fatal.slice(0, 400));
  const notices = R.serverLog('php-errors.log', notice0).split('\n').filter(Boolean);
  check('DS13 · sin avisos de PHP de DIGITALÍSIMO Elements', !notices.length, notices.slice(0, 3).join(' | '));
}

module.exports = { defaults, compareWithNormal, DS1, DS2, DS3, DS4, DS5, DS6, DS7, DS8, DS9, DS10, DS11, DS12, DS13 };
