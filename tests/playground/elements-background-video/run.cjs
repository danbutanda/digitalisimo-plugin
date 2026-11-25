// Vídeos de fondo de DIGITALÍSIMO Elements en Chrome real (tests/playground/elements-background-video.sh). Auditoría de
// digitalisimo.lat (2026-11-24): la cabecera bajaba un MP4 de 8,1 MB también en el celular. Con MP4 de 3 MB y de 1 MB:
//   U  «automático» (predeterminado): en el celular (390 px) no se pide (sin src, contenedor oculto, imagen de respaldo de
//      fondo) el vídeo sin «Reproducir en móvil» ni el de 3 MB que lo pide pero tiene imagen de respaldo (el caso de
//      digitalisimo.lat); sí el de 1 MB que lo pide y el de 3 MB sin imagen; en escritorio, todos;
//   D  «lo decide cada elemento»: se respeta «Reproducir en móvil»;
//   N  «nunca en el celular»: tampoco el ligero que lo pide, con su respaldo; el que no tiene imagen sigue; escritorio igual;
//   A  «también en el celular»: el que no lo pedía se descarga en el celular;
//   E  editor: el peso (3 MB > 2 MB), si se descarga en el celular y la falta de imagen de respaldo, también en uno ligero.
// Uso: node run.cjs <puerto> <host> <dir>
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const [port, host, dir] = process.argv.slice(2);
const base = `http://${host}`;
const ready = JSON.parse(fs.readFileSync(`${dir}/ready.json`, 'utf8'));
const results = [];
let failed = 0;
const ok = (value, message, detail = '') => { results.push(`${value ? 'ok  ' : 'FALLA'} · ${message}${value || !detail ? '' : ` · ${String(detail).slice(0, 600)}`}`); console.log(results[results.length - 1]); if (!value) failed++; };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const MOBILE = { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const DESKTOP = { width: 1280, height: 900 };

(async () => {
  const profile = fs.mkdtempSync('/tmp/digi-bv-');
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', userDataDir: profile, env: { ...process.env, TMPDIR: '/tmp' }, args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--autoplay-policy=no-user-gesture-required'] });
  const admin = await browser.newPage();
  const errors = [];
  try {
    await admin.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
    await admin.type('#user_login', 'admin');
    await admin.type('#user_pass', 'password');
    await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), admin.click('#wp-submit')]);
    const policy = async (mode) => { await admin.goto(`${base}/?digi_bv=${mode}`, { waitUntil: 'networkidle0', timeout: 120000 }); return JSON.parse(await admin.evaluate(() => document.body.innerText)); };

    // Un visitante (contexto sin sesión) abre la página en el celular o en escritorio; se cuentan las peticiones al MP4.
    const visit = async (slug, device) => {
      const context = await browser.createBrowserContext();
      const page = await context.newPage();
      page.on('pageerror', (e) => errors.push(`${slug}: ${String(e.message || e).slice(0, 160)}`));
      await page.setViewport(device);
      if (device.isMobile) await page.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
      const mp4 = [];
      page.on('request', (r) => { if (/\.mp4(\?|#|$)/.test(r.url())) mp4.push(r.url()); });
      await page.goto(`${base}/?page_id=${ready.pages[slug]}`, { waitUntil: 'networkidle0', timeout: 180000 });
      await wait(2500);
      const state = await page.evaluate(() => {
        const video = document.querySelector('video.elementor-background-video-hosted');
        const box = document.querySelector('.elementor-background-video-container');
        const wrapper = box && box.closest('.e-con, .elementor-section');
        return {
          mode: window.elementorFrontend && elementorFrontend.getCurrentDeviceMode ? elementorFrontend.getCurrentDeviceMode() : '',
          video: !!video,
          src: video ? video.getAttribute('src') || '' : '',
          preload: video ? video.getAttribute('preload') : null,
          hiddenClass: !!(box && box.classList.contains('elementor-hidden-mobile')),
          display: box ? getComputedStyle(box).display : '',
          poster: wrapper ? getComputedStyle(wrapper).backgroundImage : '',
        };
      });
      await page.screenshot({ path: `${dir}/${slug}-${device.isMobile ? 'celular' : 'escritorio'}.png` }).catch(() => {});
      await context.close();
      return { ...state, requests: mp4.length };
    };
    const quiet = (s) => s.requests === 0 && s.src === '' && s.display === 'none';
    const fallback = (s) => /respaldo-del-ensayo\.png/.test(s.poster);

    const loads = (s) => s.requests > 0 && s.src !== '';

    // U: automático (predeterminado, sin guardar nada): fuera del celular sólo el vídeo pesado con imagen de respaldo.
    let p = await policy('auto');
    ok(p.fix === true && p.mobile === 'auto', 'U0: la política del sitio está en «automático»', JSON.stringify(p));
    let s = await visit('no', MOBILE);
    ok(s.mode === 'mobile', 'U0: Chrome a 390 px está en el punto de corte del celular', JSON.stringify(s));
    ok(quiet(s) && fallback(s), 'U1: celular, sin «Reproducir en móvil»: no se pide el MP4 y se ve la imagen de respaldo', JSON.stringify(s));
    s = await visit('no', DESKTOP);
    ok(loads(s), 'U2: escritorio: el vídeo se pide y se reproduce', JSON.stringify(s));
    s = await visit('si', MOBILE);
    ok(quiet(s) && fallback(s), 'U3: celular, 3 MB con «Reproducir en móvil» e imagen de respaldo: no se descarga y se ve la imagen (el caso de digitalisimo.lat)', JSON.stringify(s));
    s = await visit('si', DESKTOP);
    ok(loads(s), 'U4: y en escritorio sí', JSON.stringify(s));
    s = await visit('si-ligero', MOBILE);
    ok(loads(s), 'U5: celular, 1 MB (bajo el límite) con «Reproducir en móvil»: se respeta y se pide', JSON.stringify(s));
    s = await visit('si-sin-respaldo', MOBILE);
    ok(loads(s), 'U6: celular, 3 MB con «Reproducir en móvil» sin imagen de respaldo: se respeta (no queda vacío)', JSON.stringify(s));

    // D: lo decide cada elemento (como antes de esta versión).
    p = await policy('element');
    ok(p.fix === true && p.mobile === 'element', 'D0: la política del sitio está en «lo decide cada elemento»', JSON.stringify(p));
    s = await visit('si', MOBILE);
    ok(loads(s), 'D1: celular, con «Reproducir en móvil» encendido en el elemento: se respeta y se pide', JSON.stringify(s));
    s = await visit('no', MOBILE);
    ok(quiet(s), 'D2: celular, sin «Reproducir en móvil»: no se pide', JSON.stringify(s));

    // N: nunca en el celular.
    p = await policy('never');
    ok(p.fix === true && p.mobile === 'never', 'N0: la política del sitio está en «nunca en el celular»', JSON.stringify(p));
    s = await visit('si-ligero', MOBILE);
    ok(quiet(s) && fallback(s), 'N1: celular, aunque el elemento lo pida y pese poco: no se pide el MP4 y se ve su imagen de respaldo', JSON.stringify(s));
    s = await visit('si-sin-respaldo', MOBILE);
    ok(loads(s), 'N2: pero uno sin imagen de respaldo no se quita (no queda vacío)', JSON.stringify(s));
    s = await visit('si', DESKTOP);
    ok(loads(s), 'N3: escritorio: el vídeo sigue', JSON.stringify(s));

    // A: también en el celular.
    p = await policy('always');
    ok(p.fix === true && p.mobile === 'always', 'A0: la política del sitio está en «también en el celular»', JSON.stringify(p));
    s = await visit('no', MOBILE);
    ok(loads(s) && !s.hiddenClass, 'A1: celular, aunque el elemento no lo pida: se pide y se reproduce', JSON.stringify(s));

    // E: aviso de peso en el editor, con la política predeterminada.
    await policy('auto');
    const editor = async (slug) => {
      await admin.goto(`${base}/wp-admin/post.php?post=${ready.pages[slug]}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 300000 });
      await admin.waitForSelector('#elementor-panel', { timeout: 300000 });
      const frame = await (await admin.waitForSelector('#elementor-preview-iframe', { timeout: 300000 })).contentFrame();
      await frame.waitForSelector('.e-con', { timeout: 300000 });
      await wait(1500);
      // Abre el contenedor en el panel: primero como el editor (seleccionar), y si no, con un clic en su parte vacía.
      await admin.evaluate(() => { try { $e.run('document/elements/select', { container: elementor.getPreviewContainer().children[0] }); } catch (e) { /* clic abajo */ } });
      if (!(await admin.waitForSelector('.elementor-tab-control-style', { timeout: 15000 }).catch(() => null))) {
        const box = await (await frame.$('.e-con')).boundingBox();
        await admin.mouse.click(box.x + box.width / 2, box.y + box.height - 15);
        await admin.waitForSelector('.elementor-tab-control-style', { timeout: 60000 });
      }
      // Pestaña «Estilo» y sección «Fondo», como lo hace el propio editor ($e.route + activateSection), y si no, con clics.
      const opened = await admin.evaluate(() => {
        try {
          const c = elementor.getPreviewContainer().children[0];
          $e.route('panel/editor/style', { model: c.model, view: c.view });
          return true;
        } catch (e) { return String(e); }
      });
      await wait(1000);
      // Si no, clics nativos en la pestaña y en la sección (el panel ya está abierto en el contenedor).
      for (let i = 0; i < 3 && !(await admin.$('.elementor-control-background_background')); i++) {
        await admin.evaluate(() => {
          const tab = document.querySelector('#elementor-panel .elementor-tab-control-style');
          if (tab && !tab.classList.contains('elementor-active')) tab.click();
        });
        await wait(1200);
        await admin.evaluate(() => {
          const section = document.querySelector('#elementor-panel .elementor-control-section_background');
          if (section && !section.classList.contains('e-open')) section.click();
        });
        await wait(1200);
      }
      if (!(await admin.$('.elementor-control-background_background'))) {
        const sections = await admin.evaluate(() => [...document.querySelectorAll('#elementor-panel .elementor-control-type-section')].map((n) => n.className.replace(/\s+/g, ' ')).join(' | '));
        await admin.screenshot({ path: `${dir}/editor-${slug}.png` }).catch(() => {});
        throw new Error(`no se abrió el fondo del contenedor en el panel (route: ${opened}; secciones: ${sections})`);
      }
      await admin.waitForFunction(() => { const b = document.querySelector('.digitalisimo-bg-video-weight'); return b && b.textContent.length > 0; }, { timeout: 60000 }).catch(() => {});
      await admin.screenshot({ path: `${dir}/editor-${slug}.png` }).catch(() => {});
      return admin.evaluate(() => { const b = document.querySelector('.digitalisimo-bg-video-weight'); return b ? b.textContent : null; });
    };
    let text = await editor('si');
    ok(typeof text === 'string' && /pesa 3 MB/.test(text) && /límite del sitio \(2 MB\)/.test(text), 'E1: el editor avisa que el vídeo pesa 3 MB, más que el límite de 2 MB', text);
    ok(/En el celular no se descarga: el sitio lo quita del celular/.test(text || '') && !/Falta/.test(text || ''), 'E2: y que el sitio lo quita del celular (automático, con imagen de respaldo)', text);
    text = await editor('si-sin-respaldo');
    ok(/pesa 3 MB/.test(text || '') && /También se descarga en el celular/.test(text || '') && /Falta la «Imagen de respaldo»/.test(text || ''), 'E3: sin imagen de respaldo: se descarga en el celular y falta la imagen', text);
    text = await editor('ligero-sin-respaldo');
    ok(/Falta la «Imagen de respaldo»/.test(text || '') && !/pesa/.test(text || ''), 'E4: uno ligero sin imagen de respaldo: avisa de la imagen aunque no pase del peso', text);
    ok(errors.length === 0, 'sin errores de JavaScript en las páginas', errors.join(' | '));
  } catch (e) {
    ok(false, 'el ensayo terminó con una excepción', e.stack || String(e));
  } finally {
    await browser.close().catch(() => {});
    fs.rmSync(profile, { recursive: true, force: true });
  }
  fs.writeFileSync(`${dir}/result.txt`, `${results.join('\n')}\n`);
  console.log(failed ? `== ${failed} fallas de ${results.length}` : `== todo bien (${results.length})`);
  process.exit(failed ? 1 : 0);
})();
