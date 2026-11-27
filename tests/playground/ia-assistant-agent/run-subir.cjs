// Conductor del ensayo «subir archivos al Asistente» con Chrome y la API REAL (ia-assistant-subir.sh; pedido del
// propietario del 2026-11-26: «agrégale la función de agregar las imágenes y todas las debe comprimir a .webp al
// subirse, ya debemos tener un plugin que hace eso, solo úsalo, solo permite que el asistente cargue archivos
// arrastrándolos y los agregue a la biblioteca»). En la pantalla real del Asistente:
//   1 arrastra dos JPG y un PNG (fotos de 1600×1200 y un logo, generados por setup-subir.php) y los suelta en el chat;
//   2 espera el resumen «Subí 3 imágenes a tu biblioteca (de … a … en total).» con sus miniaturas;
//   3 escribe «Crea una página nueva en borrador que se llame "Visítanos" y pon como imagen principal la foto de la
//     fachada que acabo de subir.» y espera la respuesta de Claude.
// Mide:
//   U1–U4  la zona para soltar aparece; el resumen llano con 3 miniaturas; ninguna palabra técnica; la bandeja lista;
//   L1–L5  en la biblioteca: 3 adjuntos nuevos, todos image/webp con cabecera RIFF/WEBP y tamaños .webp, más ligeros
//          que el original, marcados por el Asistente y por Tools;
//   A1–A5  el modelo recibió el bloque de datos; un borrador nuevo usa la foto de la fachada; nada se publicó; no subió
//          ni pidió el archivo otra vez; la respuesta tiene enlaces con texto y sin jerga.
// Deja en <dir>: evaluacion.json, respuestas.md, result.txt y capturas (subir-soltar.png, subir-bandeja.png,
// subir-respuesta.png). Uso: node run-subir.cjs <puerto> <host> <dir> real
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');

const [port, host, dir, mode] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const ready = JSON.parse(fs.readFileSync(`${dir}/probe/ready.json`, 'utf8'));
const base = `http://${host}`;
const results = [];
let failures = 0;
const log = (line) => { fs.appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (id, ok, what, detail = '') => {
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 1500) });
  if (ok) log(`ok · ${id} · ${what}`); else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 1500)}` : ''}`); }
  return !!ok;
};
const cookies = {};
function request(method, p) {
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path: p, headers: { Host: host, Cookie: Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; ') }, timeout: 120000 }, (res) => {
      for (const c of res.headers['set-cookie'] || []) { const [pair] = c.split(';'); const i = pair.indexOf('='); cookies[pair.slice(0, i)] = pair.slice(i + 1); }
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch { /* no JSON */ } resolve({ status: res.statusCode, text, json }); });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${p}`)));
    req.end();
  });
}
const probe = async (what) => (await request('GET', `/?digi_ias=${what}&token=${token}`)).json;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const JARGON = /\bID\b|\bid\s*\d|post_id|attachment|\bMCP\b|\bREST\b|\bAPI\b|\bJSON\b|mime|image\/|elementor\/|digitalisimo-|\?page_id=|preview=true|widget|placeholder|\bslug\b/i;
const withoutLinks = (text) => String(text).replace(/\[([^\]\n]{1,100})\]\((https?:\/\/[^\s)]+)\)/g, '$1');
const MESSAGE = 'Crea una página nueva en borrador que se llame "Visítanos" y pon como imagen principal la foto de la fachada que acabo de subir.';

(async () => {
  let browser = null;
  const evaluation = { mode, ready, started: new Date().toISOString() };
  try {
    check('S0', ready.tools && ready.gd_webp, `Tools activo y el PHP del sitio escribe WebP (GD WebP: ${ready.gd_webp}, Imagick: ${ready.imagick})`, JSON.stringify(ready));
    await probe('login');
    const puppeteer = require('puppeteer-core');
    browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox'] });
    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 900 });
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: decodeURIComponent(value), url: base })));
    await page.goto(`${base}/wp-admin/admin.php?page=digitalisimo-assistant&new=1`, { waitUntil: 'networkidle2', timeout: 180000 });
    await page.waitForSelector('.dga-root .dga-composer', { timeout: 120000 });
    check('S1', !!(await page.$('.dga-attach')), 'la pantalla ofrece el clip para subir archivos');

    // 1. Arrastrar y soltar los tres archivos, como lo hace el navegador (DragEvent con DataTransfer y File reales).
    const files = ready.samples.map((s) => ({ name: s.name, type: /\.png$/.test(s.name) ? 'image/png' : 'image/jpeg', data: fs.readFileSync(path.join(dir, 'probe', 'samples', s.name)).toString('base64') }));
    await page.evaluate((list) => {
      const bytes = (b64) => Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
      window.__digiDrop = new DataTransfer();
      for (const f of list) window.__digiDrop.items.add(new File([bytes(f.data)], f.name, { type: f.type }));
      const target = document.querySelector('.dga-thread-area');
      target.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: window.__digiDrop }));
      target.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: window.__digiDrop }));
    }, files);
    await sleep(500);
    const dropText = await page.$eval('.dga-drop', (n) => n.textContent).catch(() => '');
    check('U1', /Suelta aquí tus archivos/.test(dropText), `al arrastrar aparece «Suelta aquí tus archivos»: «${dropText}»`);
    await page.screenshot({ path: `${dir}/subir-soltar.png` });
    const prevented = await page.evaluate(() => {
      const target = document.querySelector('.dga-textarea');
      const drop = new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: window.__digiDrop });
      target.dispatchEvent(drop);
      return drop.defaultPrevented;
    });
    check('U1b', prevented, 'al soltar sobre el cuadro de texto, el navegador no abre el archivo');
    let summary = '';
    for (let i = 0; i < 120 && !summary; i++) {
      await sleep(2000);
      summary = await page.evaluate(() => { const n = [...document.querySelectorAll('.dga-system')].find((x) => /^Subí/.test(x.firstChild && x.firstChild.textContent)); return n ? n.firstChild.textContent : ''; });
    }
    const thumbs = await page.$$eval('.dga-system .dga-files img', (imgs) => imgs.map((i) => ({ src: i.getAttribute('src'), loaded: i.complete && i.naturalWidth > 0 })));
    await page.screenshot({ path: `${dir}/subir-bandeja.png` });
    evaluation.summary = summary;
    check('U2', /^Subí 3 imágenes a tu biblioteca \(de [\d.]+ (KB|MB) a [\d.]+ (KB|MB|bytes) en total\)\.$/.test(summary), `el resumen llano: «${summary}»`);
    check('U3', thumbs.length === 3 && thumbs.every((t) => /\.webp$/.test(t.src) && t.src.startsWith(base)), `con 3 miniaturas WebP del sitio (${thumbs.map((t) => t.src).join(', ')})`);
    check('U4', !JARGON.test(summary) && !/webp/i.test(summary), 'sin jerga en el resumen');
    const tray = await page.$$eval('.dga-tray-item', (items) => items.map((i) => i.getAttribute('data-status')));
    check('U5', tray.length === 3 && tray.every((s) => s === 'done'), `la bandeja con los 3 listos para el mensaje (${tray})`);

    // 2. La biblioteca.
    const lib = await probe('media');
    evaluation.media = lib.media;
    const media = lib.media || [];
    check('L1', media.length === 3, `3 adjuntos nuevos en la biblioteca (${media.length})`, JSON.stringify(media));
    check('L2', media.every((m) => m.mime === 'image/webp' && /\.webp$/.test(m.file) && m.exists && /^52494646........57454250/i.test(m.head)), 'todos en WebP: tipo, archivo y cabecera RIFF/WEBP', JSON.stringify(media.map((m) => [m.file, m.mime, m.head])));
    check('L3', media.every((m) => m.sizes.length > 0 && m.sizes.every((s) => /\.webp$/.test(s))), 'con sus tamaños reducidos también en WebP', JSON.stringify(media.map((m) => m.sizes)));
    const before = media.reduce((n, m) => n + m.before, 0);
    const after = media.reduce((n, m) => n + m.bytes, 0);
    const original = ready.samples.reduce((n, s) => n + s.bytes, 0);
    check('L4', before === original && media.every((m) => m.bytes < m.before), `más ligeros que los originales (${original} → ${after} bytes)`, JSON.stringify(media.map((m) => [m.before, m.bytes])));
    check('L5', media.every((m) => m.uploader === ready.admin && m.tools && /\.webp$/.test(m.tools)), 'marcados por el Asistente (quién subió) y por Tools («Optimizada · WebP»)');
    const fachada = media.find((m) => /fachada/.test(m.file));

    // 3. Pedirle al Asistente (Claude, API real) que la use en un borrador.
    await page.click('#dga-input');
    await page.type('#dga-input', MESSAGE);
    await page.keyboard.press('Enter');
    const started = Date.now();
    let state = null;
    let idle = 0;
    for (;;) {
      await sleep(10000);
      const busy = await page.evaluate(() => !!document.querySelector('.dga-pending'));
      idle = busy ? 0 : idle + 1;
      if (idle >= 2) break;
      if (Date.now() - started > 20 * 60 * 1000) { log('== 20 minutos sin terminar'); break; }
    }
    state = await probe('media');
    evaluation.seconds = Math.round((Date.now() - started) / 1000);
    await page.screenshot({ path: `${dir}/subir-respuesta.png`, fullPage: false });
    const visible = await page.$$eval('.dga-message.is-assistant .dga-bubble', (b) => b.map((x) => x.textContent));
    const cards = await page.$$eval('.dga-confirm', (c) => c.map((x) => x.textContent.slice(0, 300)));
    const mine = await page.$$eval('.dga-message.is-me', (m) => m.map((x) => ({ text: x.querySelector('.dga-bubble').firstChild.textContent, thumbs: x.querySelectorAll('.dga-files li').length })));
    const answer = visible[visible.length - 1] || '';
    evaluation.answer = answer; evaluation.cards = cards; evaluation.posts = state.posts; evaluation.seen = (state.seen.requests || []).slice(-5); evaluation.log = state.log;
    log(`\n» ${MESSAGE}\n  (${evaluation.seconds} s)\n  ${answer.slice(0, 2000)}\n`);
    check('A0', mine.length === 1 && mine[0].thumbs === 3, 'la burbuja de la persona lleva las 3 miniaturas', JSON.stringify(mine));
    check('A1', (state.seen.requests || []).some((r) => r.files_block), 'el modelo recibió los archivos como bloque de datos');
    const drafts = (state.posts || []).filter((p) => p.status === 'draft');
    const used = drafts.find((p) => fachada && p.uses.includes(fachada.id));
    check('A2', !!used, `un borrador nuevo usa la foto de la fachada (${drafts.map((p) => `${p.title}: ${p.uses}`).join(' | ') || 'ningún borrador'})`, JSON.stringify({ posts: state.posts, fachada }));
    check('A3', used && /vis[ií]tanos/i.test(used.title), `el borrador se llama «Visítanos» (${used ? used.title : '—'})`);
    check('A4', !(state.posts || []).some((p) => p.status === 'publish') && !cards.length, `nada publicado ni pendiente de confirmar (${cards.length} tarjetas)`, cards.join(' | '));
    check('A5', (state.media || []).length === 3, `no volvió a subir archivos (${(state.media || []).length} adjuntos)`);
    check('A6', !/(s[uú]bela|env[ií]ame|adjunta|comparte)\s+(la|el)\s+(foto|imagen|archivo)/i.test(answer), 'no pidió el archivo otra vez', answer);
    check('A7', answer && !JARGON.test(withoutLinks(answer)) && /\[|Ver|Editar/i.test(answer + visible.join(' ')), 'la respuesta: corta, sin jerga y con enlaces con texto', answer);

    const md = [`# Subir archivos al Asistente con la API real (${new Date().toISOString()})`, '', `Sitio: WordPress ${ready.wp}, PHP ${ready.php}, GD WebP ${ready.gd_webp}.`, '', '## Al soltar', '', summary, '', '| Archivo | Antes | Ahora |', '|---|---|---|'];
    for (const m of media) md.push(`| ${m.file} | ${m.before} bytes | ${m.bytes} bytes |`);
    md.push('', `## » ${MESSAGE}`, '', `_${evaluation.seconds} s_`, '', ...visible.map((v) => `${v}\n`), '## Comprobaciones', '', ...results.map((r) => `- ${r.ok ? 'ok' : '**FALLA**'} · ${r.id} · ${r.what}`));
    fs.writeFileSync(`${dir}/respuestas.md`, md.join('\n'));
  } catch (e) {
    check('X', false, 'el ensayo terminó con un error', e.stack || String(e));
  } finally {
    if (browser) await browser.close();
    fs.writeFileSync(`${dir}/evaluacion.json`, JSON.stringify({ ...evaluation, results, failures }, null, 1));
  }
  log(failures ? `Ensayo «subir»: ${failures} falla(s).` : 'Ensayo «subir»: todo correcto.');
  process.exit(failures ? 1 : 0);
})();
