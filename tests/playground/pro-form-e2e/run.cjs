/**
 * Prueba de punta a punta de envíos de C6 (docs/pro/c6.md): en Chrome, el Form de PRO (A) y el Formulario propio
 * convertido (B) se envían igual (error de validación del servidor, envío correcto, doble envío y un formulario por
 * pasos con un campo condicional de F7b). Después se comparan los envíos guardados (`e_submissions`), los correos
 * (`mails.jsonl`) y las llamadas al webhook del receptor del arnés (`hooks.jsonl`): mismo número, mismos campos, sin
 * duplicados y sin lo oculto. B no carga los paquetes de PRO ni su hoja. Resultado en $OUT/pro-form-e2e.json; sale con 1
 * si algo falla.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const { OUT, PROBE, BASE } = process.env;
const pages = JSON.parse(fs.readFileSync(path.join(PROBE, 'pages.json'), 'utf8'));
const lines = (file) => (fs.existsSync(file) ? fs.readFileSync(file, 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l)) : []);
const fails = [];
const notes = [];
const check = (ok, message) => { if (!ok) fails.push(message); else notes.push('ok · ' + message); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function state() {
  const res = await fetch(BASE + '/?digi-e2e=state');
  return res.json();
}

async function open(browser, url, side) {
  const page = await browser.newPage();
  const requests = [];
  const errors = [];
  page.on('request', (r) => requests.push(r.url()));
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => { if ('error' === m.type()) errors.push(m.text()); });
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto(url, { waitUntil: 'networkidle0', timeout: 120000 });
  // Que el script del formulario haya arrancado: el de PRO (manejador de elementos) o el propio (`digiForm`).
  await page.waitForFunction((s) => {
    const f = document.querySelector('form.elementor-form');
    if (!f) return false;
    if ('b' === s) return !!f.digiForm;
    return !!(window.jQuery && window.jQuery._data && (window.jQuery._data(f, 'events') || {}).submit);
  }, { timeout: 60000 }, side);
  return { page, requests, errors };
}

async function type(page, selector, text) {
  await page.$eval(selector, (el) => { el.value = ''; });
  await page.type(selector, text);
}

async function waitMessage(page, kind) {
  await page.waitForSelector('form.elementor-form .elementor-message-' + kind, { timeout: 60000 });
}

async function basic(browser, side, url) {
  const { page, requests, errors } = await open(browser, url, side);
  const out = { side };
  // 1. Envío incompleto (validación del servidor): ningún envío, correo ni webhook.
  await type(page, '#form-field-name', 'Solo nombre ' + side);
  await page.click('form.elementor-form [type="submit"]');
  await waitMessage(page, 'danger');
  out.error = await page.evaluate(() => {
    const email = document.querySelector('#form-field-email');
    const inline = email.closest('.elementor-field-group').querySelector('.elementor-form-help-inline');
    return { inline: inline ? inline.textContent.trim() : '', role: inline ? inline.getAttribute('role') : '', invalid: email.getAttribute('aria-invalid'), describedby: email.getAttribute('aria-describedby') || '', inlineId: inline ? inline.id : '' };
  });
  await sleep(500);
  // 2. Envío correcto.
  await type(page, '#form-field-name', 'Ana ' + side);
  await type(page, '#form-field-email', 'ana@ejemplo.test');
  await type(page, '#form-field-message', 'Hola desde el e2e');
  await page.click('form.elementor-form [type="submit"]');
  await waitMessage(page, 'success');
  out.success = await page.evaluate(() => ({ message: document.querySelector('.elementor-message-success').textContent.trim(), cleared: '' === document.querySelector('#form-field-name').value, invalid: document.querySelector('#form-field-email').getAttribute('aria-invalid') }));
  await sleep(800);
  // 3. Doble envío: dos clics y dos `requestSubmit()` seguidos; sólo debe llegar uno.
  await page.evaluate(() => document.querySelectorAll('.elementor-message').forEach((m) => m.remove()));
  await type(page, '#form-field-name', 'Doble ' + side);
  await type(page, '#form-field-email', 'doble@ejemplo.test');
  await type(page, '#form-field-message', 'Una sola vez');
  await page.evaluate(() => {
    const f = document.querySelector('form.elementor-form');
    const b = f.querySelector('[type="submit"]');
    b.click();
    b.click();
    f.requestSubmit();
    f.requestSubmit();
  });
  await waitMessage(page, 'success');
  await sleep(2500);
  out.doubleMessages = await page.$$eval('.elementor-message-success', (n) => n.length);
  out.errors = errors;
  out.pro = requests.filter((u) => /elements-handlers|elementor-pro-frontend|pro-elements-handlers|widget-form(\.min)?\.css/.test(u));
  await page.close();
  return out;
}

async function steps(browser, side, url) {
  const { page, requests, errors } = await open(browser, url, side);
  const out = { side };
  await page.waitForFunction(() => document.querySelector('.e-form__buttons__wrapper__button-next'), { timeout: 60000 });
  out.start = await page.evaluate(() => ({
    empresaHidden: !!document.querySelector('.elementor-field-group-empresa[hidden]'),
    visibleSteps: [...document.querySelectorAll('.e-form__step')].filter((s) => s.offsetParent).length,
  }));
  await type(page, '#form-field-name', 'Paso ' + side);
  await page.click('#form-field-tipo-0');
  out.particular = await page.evaluate(() => !!document.querySelector('.elementor-field-group-empresa[hidden]'));
  await page.evaluate(() => { const b = [...document.querySelectorAll('.e-form__buttons__wrapper__button-next')].find((x) => x.offsetParent); b.click(); });
  await page.waitForFunction(() => document.querySelector('#form-field-email') && document.querySelector('#form-field-email').offsetParent, { timeout: 20000 });
  out.focus = await page.evaluate(() => document.activeElement && document.activeElement.id);
  await type(page, '#form-field-email', 'paso@ejemplo.test');
  await type(page, '#form-field-message', 'Por pasos');
  await page.evaluate(() => { const b = [...document.querySelectorAll('form.elementor-form [type="submit"]')].find((x) => x.offsetParent); b.click(); });
  await waitMessage(page, 'success');
  await sleep(1000);
  out.errors = errors;
  out.pro = requests.filter((u) => /elements-handlers|elementor-pro-frontend|pro-elements-handlers|widget-form(\.min)?\.css/.test(u));
  await page.close();
  return out;
}

(async () => {
  const started = Date.now();
  check(pages.registered && pages.registered.form && pages.registered['digitalisimo-form'], 'Los dos widgets están registrados.');
  for (const slug of ['basic', 'steps']) {
    check('form' === pages[slug].a.widget && 'digitalisimo-form' === pages[slug].b.widget && pages[slug].a.element === pages[slug].b.element, `${slug}: A con el Form de PRO, B con el Formulario propio y el mismo id de elemento.`);
    const codes = (pages[slug].log || []).flatMap((e) => e.codes || []).map((c) => c.code + ':' + c.key);
    check(0 === codes.length, `${slug}: conversión sin códigos (${codes.join(', ')}).`);
  }
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  const runs = {};
  try {
    for (const side of ['a', 'b']) {
      runs['basic-' + side] = await basic(browser, side, pages.basic[side].url);
      runs['steps-' + side] = await steps(browser, side, pages.steps[side].url);
    }
  } finally {
    await browser.close();
  }
  const st = await state();
  const mails = lines(path.join(PROBE, 'mails.jsonl'));
  const hooks = lines(path.join(PROBE, 'hooks.jsonl'));
  check(st.table, 'La tabla de envíos guardados existe.');
  const bySide = (slug, side) => {
    const id = pages[slug][side].id;
    const url = pages[slug][side].url;
    return {
      subs: st.submissions.filter((s) => Number(s.post_id) === id),
      mails: mails.filter((m) => m.referer === url),
      hooks: hooks.filter((h) => h.referer === url),
    };
  };
  const strip = (o) => JSON.parse(JSON.stringify(o).split(BASE).join(''));
  const fieldsOf = (h) => { const b = Object.assign({}, h.body || {}); delete b.form_id; return b; };
  const result = { runs, compare: {} };
  for (const slug of ['basic', 'steps']) {
    const A = bySide(slug, 'a');
    const B = bySide(slug, 'b');
    const want = 'basic' === slug ? 2 : 1;
    result.compare[slug] = { a: { subs: A.subs.length, mails: A.mails.length, hooks: A.hooks.length }, b: { subs: B.subs.length, mails: B.mails.length, hooks: B.hooks.length } };
    for (const [k, label] of [['subs', 'envíos guardados'], ['mails', 'correos'], ['hooks', 'llamadas al webhook']]) {
      check(want === A[k].length, `${slug}: A deja ${want} ${label} (hay ${A[k].length}).`);
      check(A[k].length === B[k].length, `${slug}: B deja los mismos ${label} que A (${A[k].length} / ${B[k].length}).`);
    }
    for (let i = 0; i < Math.min(A.subs.length, B.subs.length); i++) {
      const va = Object.assign({}, A.subs[i].values);
      const vb = Object.assign({}, B.subs[i].values);
      check(JSON.stringify(Object.keys(va)) === JSON.stringify(Object.keys(vb)), `${slug} #${i + 1}: los mismos campos guardados (${Object.keys(va)} / ${Object.keys(vb)}).`);
      for (const k of Object.keys(va)) {
        if ('name' === k) check(va[k].replace(/ a$/, '') === vb[k].replace(/ b$/, ''), `${slug} #${i + 1}: ${k} guardado igual.`);
        else check(va[k] === vb[k], `${slug} #${i + 1}: ${k} guardado igual («${va[k]}» / «${vb[k]}»).`);
      }
      check(A.subs[i].element_id === B.subs[i].element_id && A.subs[i].form_name === B.subs[i].form_name, `${slug} #${i + 1}: mismo id de formulario y nombre (${A.subs[i].element_id}, ${A.subs[i].form_name}).`);
    }
    for (let i = 0; i < Math.min(A.mails.length, B.mails.length); i++) {
      const ma = strip(A.mails[i]);
      const mb = strip(B.mails[i]);
      check(ma.to === mb.to && ma.subject.replace(/ a$/, '') === mb.subject.replace(/ b$/, ''), `${slug} correo #${i + 1}: mismo destinatario y asunto (${ma.to} · ${ma.subject} / ${mb.subject}).`);
      const body = (m) => String(m.message).replace(/<br>/g, '\n').split('\n').filter((l) => /^[^:]+: /.test(l) && !/(Fecha|Date|Hora|Time|URL|Page|Agente|User Agent|IP|Remote|Powered|Con tecnología)/i.test(l)).map((l) => l.replace(/ [ab]$/, ''));
      check(JSON.stringify(body(ma)) === JSON.stringify(body(mb)), `${slug} correo #${i + 1}: mismos campos en el cuerpo (${body(ma).join(' | ')} / ${body(mb).join(' | ')}).`);
      if ('steps' === slug) check(!/Empresa/.test(ma.message) && !/Empresa/.test(mb.message), 'steps: el campo condicional oculto no llega al correo.');
    }
    for (let i = 0; i < Math.min(A.hooks.length, B.hooks.length); i++) {
      const ha = fieldsOf(A.hooks[i]);
      const hb = fieldsOf(B.hooks[i]);
      // La página de cada lado (Page URL, e2e-<escenario>-a/-b) es lo único que debe cambiar.
      const norm = (o) => JSON.stringify(Object.fromEntries(Object.entries(o).map(([k, v]) => [k, String(v).replace(/ [ab]$/, '').replace(/\/e2e-(\w+)-[ab]\//, '/e2e-$1-X/')])));
      check(norm(ha) === norm(hb), `${slug} webhook #${i + 1}: el mismo cuerpo (${norm(ha)} / ${norm(hb)}).`);
      check(A.hooks[i].body.form_id === B.hooks[i].body.form_id, `${slug} webhook #${i + 1}: el mismo form_id (${A.hooks[i].body.form_id}).`);
      if ('steps' === slug) check(!('Empresa' in ha) && !('Empresa' in hb) && !('empresa' in ha) && !('empresa' in hb), 'steps: el campo condicional oculto no llega al webhook.');
    }
  }
  for (const side of ['a', 'b']) {
    const r = runs['basic-' + side];
    check(r.success.cleared, `basic-${side}: el formulario se vacía tras el envío.`);
    check(1 === r.doubleMessages, `basic-${side}: un solo mensaje de éxito tras el doble envío (${r.doubleMessages}).`);
    check(!r.errors.length && !runs['steps-' + side].errors.length, `${side}: sin errores de JavaScript (${r.errors.concat(runs['steps-' + side].errors).join(' · ')}).`);
    check(runs['steps-' + side].particular && runs['steps-' + side].start.empresaHidden, `steps-${side}: «Empresa» oculto con «Particular».`);
    check(1 === runs['steps-' + side].start.visibleSteps, `steps-${side}: sólo el primer paso a la vista.`);
  }
  check(runs['basic-a'].error.inline === runs['basic-b'].error.inline && '' !== runs['basic-b'].error.inline, `El error del campo es el mismo (${runs['basic-a'].error.inline}).`);
  check('true' === runs['basic-b'].error.invalid && 'alert' === runs['basic-b'].error.role && runs['basic-b'].error.describedby.split(' ').includes(runs['basic-b'].error.inlineId), 'B: el campo con error queda aria-invalid y descrito por su mensaje (role=alert).');
  check('false' === runs['basic-b'].success.invalid, 'B: tras un envío correcto el campo deja de estar inválido.');
  check(runs['basic-a'].success.message === runs['basic-b'].success.message, `El mismo mensaje de éxito (${runs['basic-b'].success.message}).`);
  check('form-field-email' === runs['steps-b'].focus, `B: al pasar de paso, el foco va al primer campo (${runs['steps-b'].focus}).`);
  check(!runs['basic-b'].pro.length && !runs['steps-b'].pro.length, `B no carga los paquetes de PRO ni su hoja (${runs['basic-b'].pro.concat(runs['steps-b'].pro).join(', ')}).`);
  result.fails = fails;
  result.notes = notes;
  result.seconds = Math.round((Date.now() - started) / 1000);
  fs.writeFileSync(path.join(OUT, 'pro-form-e2e.json'), JSON.stringify(result, null, 1));
  console.log(notes.join('\n'));
  if (fails.length) {
    console.log('\nFALLAS:\n- ' + fails.join('\n- '));
    process.exit(1);
  }
  console.log(`\npro-form-e2e: ${notes.length} comprobaciones en verde.`);
})().catch((e) => { console.error(e); process.exit(1); });
