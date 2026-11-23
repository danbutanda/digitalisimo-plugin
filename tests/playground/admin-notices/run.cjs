// Avisos de DIGITALÍSIMO en Chrome real (tests/playground/admin-notices.sh): queja del propietario del 2026-11-22
// («se están mostrando alertas así y son molestas, no poder quitarlas; mándalas a las alertas globales de WP pero no las
// pongas en el contenido»). Con el aviso real de Tools (Cache_Notice) en pantalla:
//   P  en cada pantalla DIGITALÍSIMO (y en el Escritorio), la pantalla tiene `<hr class="wp-header-end">`, el aviso
//      queda justo después (zona global de avisos de WordPress), antes del contenido, y ningún aviso propio que
//      WordPress mueve queda dentro del contenido;
//   X  el aviso tiene la X; al pulsarla se guarda el cierre (AJAX) y, al recargar, ya no sale ni ahí ni en las demás
//      pantallas; en la red, cerrado en la administración de la red tampoco sale en el sitio;
//   H  si cambia la situación (otro sitio en la caché: otra huella) vuelve a salir;
//   T  cerrado de nuevo, vuelve al pasar su plazo (error: 1 día) si el problema sigue.
// Uso: node run.cjs <puerto> <host> <dir> <fase>
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const [port, host, dir, phase] = process.argv.slice(2);
const base = `http://${host}`;
const ID = 'tools-cache';
const TEXT = 'está devolviendo datos de otro sitio';
const SITE = [
  'index.php',
  'plugins.php',
  'admin.php?page=digitalisimo-tools-elementor-templates&tab=webp',
  'admin.php?page=digitalisimo-tools-elementor-templates&tab=site',
  'admin.php?page=digitalisimo-plugins',
  'admin.php?page=digitalisimo-seo',
  'admin.php?page=digitalisimo-performance',
  'admin.php?page=digitalisimo-ai',
  'admin.php?page=digitalisimo-hosting',
  'admin.php?page=digitalisimo-backups',
  'admin.php?page=digitalisimo-elements-about',
  'admin.php?page=digitalisimo-elements-front',
  'admin.php?page=digitalisimo-elements-queries',
  'tools.php?page=digitalisimo-ep-migration',
];
const NETWORK = [
  'network/index.php',
  'network/admin.php?page=digitalisimo-network-backups',
  'network/admin.php?page=digitalisimo-tools-elementor-templates',
  'network/admin.php?page=digitalisimo-network-performance',
  'network/settings.php?page=digitalisimo-ep-migration',
];
const results = [];
let failed = 0;
const ok = (value, message) => { results.push(`${value ? 'ok  ' : 'FALLA'} · ${message}`); if (!value) failed++; };

// En la página: dónde quedó el aviso respecto de la marca y del contenido.
function inspect(id, text) {
  const isNotice = (el) => el.matches && el.matches('div.notice, div.updated, div.error') && !el.matches('.inline, .below-h2, .hidden');
  const notice = [...document.querySelectorAll('#wpbody-content div.notice, #wpbody-content div.error, #wpbody-content div.updated')].find((n) => n.getAttribute('data-digitalisimo-notice') === id || (n.textContent || '').includes(text)) || null;
  const marks = [...document.querySelectorAll('#wpbody-content hr.wp-header-end')];
  const hr = marks[0] || null;
  let content = null;
  if (hr) for (let el = hr.nextElementSibling; el; el = el.nextElementSibling) { if (isNotice(el) || ['SCRIPT', 'STYLE'].includes(el.tagName) || el.matches('.clear, br, #screen-meta-links, .hidden') || getComputedStyle(el).display === 'none') continue; content = el; break; }
  const after = (a, b) => !!(a && b && (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING));
  const ownInside = [...document.querySelectorAll('#wpbody-content div.notice[data-digitalisimo-notice]')].filter((n) => !after(hr, n) || (content && !after(n, content))).map((n) => n.getAttribute('data-digitalisimo-notice'));
  return {
    title: (document.querySelector('#wpbody-content h1') || {}).textContent || document.title,
    marks: marks.length,
    notice: !!notice,
    dismissible: !!(notice && notice.classList.contains('is-dismissible') && notice.querySelector('button.notice-dismiss')),
    hasId: !!(notice && notice.getAttribute('data-digitalisimo-notice') === id),
    afterMark: after(hr, notice),
    beforeContent: !!notice && (!content || after(notice, content)),
    contentTag: content ? `${content.tagName.toLowerCase()}.${String(content.className).split(/\s+/)[0]}` : '',
    ownInside,
    errors: (window.__digiErrors || []).slice(0, 3),
  };
}

(async () => {
  // Perfil y temporales de Chrome en una ruta corta: con el TMPDIR largo de pc-run.sh, Chrome no arranca («Socket path too long»).
  const profile = fs.mkdtempSync('/tmp/digi-an-');
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', userDataDir: profile, env: { ...process.env, TMPDIR: '/tmp' }, args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', ...(phase === 'network' ? [`--host-resolver-rules=MAP ${host} 127.0.0.1:${port}`] : [])] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  const jsErrors = [];
  page.on('pageerror', (e) => jsErrors.push(String(e.message || e).slice(0, 200)));
  const open = async (path) => { await page.goto(`${base}/wp-admin/${path}`, { waitUntil: 'networkidle0', timeout: 180000 }); await new Promise((r) => setTimeout(r, 300)); return page.evaluate(inspect, ID, TEXT); };
  const probe = async (cmd) => { await page.goto(`${base}/?digi_an=${encodeURIComponent(cmd)}`, { waitUntil: 'networkidle0', timeout: 120000 }); return JSON.parse(await page.evaluate(() => document.body.innerText)); };
  try {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), page.click('#wp-submit')]);
    const screens = phase === 'network' ? [...NETWORK, ...SITE.slice(0, 4)] : SITE;

    // P: posición en cada pantalla.
    for (const path of screens) {
      const r = await open(path);
      await page.screenshot({ path: `${dir}/${phase}-${path.replace(/[^a-z0-9]+/gi, '_')}.png` }).catch(() => {});
      ok(r.notice, `P ${path}: sale el aviso de la caché (${r.title.trim().slice(0, 40)})`);
      ok(r.dismissible && r.hasId, `P ${path}: el aviso tiene la X y su id`);
      // Las pantallas del núcleo (Escritorio, Plugins) son de WordPress; la marca y la posición se exigen en las nuestras.
      if (!/page=digitalisimo/.test(path)) continue;
      ok(r.marks === 1, `P ${path}: una marca <hr class="wp-header-end"> (hay ${r.marks})`);
      ok(r.afterMark && r.beforeContent, `P ${path}: el aviso queda tras la marca y antes del contenido (${r.contentTag || 'sin contenido detrás'})`);
      ok(r.ownInside.length === 0, `P ${path}: ningún aviso propio dentro del contenido${r.ownInside.length ? ': ' + r.ownInside.join(', ') : ''}`);
    }

    // X: pulsar la X en la primera pantalla (la de la red, en la fase de red) y recargar.
    const first = screens[0];
    let r = await open(first);
    if (r.dismissible) {
      const saved = page.waitForResponse((res) => res.url().includes('admin-ajax.php') && res.request().method() === 'POST', { timeout: 60000 });
      await page.click(`div.notice[data-digitalisimo-notice="${ID}"] button.notice-dismiss`);
      const res = await saved.catch(() => null);
      ok(res && res.status() === 200 && /"success":true/.test(await res.text()), `X ${first}: la X guarda el cierre por AJAX`);
    } else ok(false, `X ${first}: no hay X que pulsar`);
    r = await open(first);
    ok(!r.notice, `X ${first}: al recargar, el aviso cerrado ya no sale`);
    for (const path of screens.slice(1)) {
      r = await open(path);
      ok(!r.notice, `X ${path}: cerrado una vez, tampoco sale aquí`);
    }

    // H: otra situación (otro sitio en la caché) → vuelve.
    await probe('host:tercero.test');
    r = await open(first);
    ok(r.notice && r.dismissible, `H ${first}: con otra situación (otra huella), el aviso vuelve`);
    const saved = page.waitForResponse((res) => res.url().includes('admin-ajax.php') && res.request().method() === 'POST', { timeout: 60000 });
    await page.click(`div.notice[data-digitalisimo-notice="${ID}"] button.notice-dismiss`).catch(() => {});
    await saved.catch(() => null);
    r = await open(first);
    ok(!r.notice, `H ${first}: cerrado otra vez, no sale`);

    // T: pasado el plazo de un error (1 día), si sigue el problema, vuelve.
    const aged = await probe(`age:${ID}:${86400 + 120}`);
    ok(aged.aged === true, 'T la sonda envejece el cierre un día');
    r = await open(first);
    ok(r.notice, `T ${first}: pasado un día, el error vuelve si la caché sigue dando datos de otro sitio`);
    ok(jsErrors.length === 0, `Sin errores de JavaScript${jsErrors.length ? ': ' + jsErrors.join(' | ') : ''}`);
  } catch (e) {
    ok(false, `Excepción: ${e && e.stack ? e.stack.split('\n').slice(0, 3).join(' ') : e}`);
  } finally {
    await browser.close();
    fs.rmSync(profile, { recursive: true, force: true });
  }
  fs.writeFileSync(`${dir}/result.txt`, results.join('\n') + '\n');
  console.log(results.join('\n'));
  console.log(failed ? `FALLA admin-notices ${phase}: ${failed} comprobaciones` : `OK admin-notices ${phase}: ${results.length} comprobaciones`);
  process.exit(failed ? 1 : 0);
})();
