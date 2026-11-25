// Vídeos de fondo de DIGITALÍSIMO Elements en Chrome real (tests/playground/elements-background-video.sh). Auditoría de
// digitalisimo.lat (2026-11-24): la cabecera bajaba un MP4 de 8,1 MB también en el celular. Con un MP4 de 3 MB:
//   D  predeterminado («lo decide cada elemento»): en el celular (390 px), el vídeo sin «Reproducir en móvil» no se pide
//      (sin src, contenedor oculto y la imagen de respaldo de fondo) y en escritorio sí; con «Reproducir en móvil»
//      encendido se respeta y se pide;
//   N  «Nunca en el celular»: el que lo pide tampoco se descarga en el celular (sin src ni petición), con su respaldo; en
//      escritorio sigue;
//   A  «También en el celular»: el que no lo pedía se descarga en el celular;
//   E  editor: al abrir el fondo del contenedor, el aviso dice que pesa 3 MB, más que el límite de 2 MB, si se descarga
//      en el celular y si falta la imagen de respaldo.
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

    // D: predeterminado.
    let p = await policy('element');
    ok(p.fix === true && p.mobile === 'element', 'D0: la política del sitio está en «lo decide cada elemento»', JSON.stringify(p));
    let s = await visit('no', MOBILE);
    ok(s.mode === 'mobile', 'D0: Chrome a 390 px está en el punto de corte del celular', JSON.stringify(s));
    ok(quiet(s), 'D1: celular, sin «Reproducir en móvil»: no se pide el MP4 (sin src, contenedor oculto)', JSON.stringify(s));
    ok(fallback(s), 'D2: y se ve la imagen de respaldo de fondo', JSON.stringify(s));
    s = await visit('no', DESKTOP);
    ok(s.requests > 0 && s.src !== '', 'D3: escritorio: el vídeo se pide y se reproduce', JSON.stringify(s));
    s = await visit('si', MOBILE);
    ok(s.requests > 0 && s.src !== '', 'D4: celular, con «Reproducir en móvil» encendido en el elemento: se respeta y se pide', JSON.stringify(s));

    // N: nunca en el celular.
    p = await policy('never');
    ok(p.fix === true && p.mobile === 'never', 'N0: la política del sitio está en «nunca en el celular»', JSON.stringify(p));
    s = await visit('si', MOBILE);
    ok(quiet(s), 'N1: celular, aunque el elemento lo pida: no se pide el MP4 (sin src ni descarga, contenedor oculto)', JSON.stringify(s));
    ok(fallback(s), 'N2: y se ve su imagen de respaldo', JSON.stringify(s));
    s = await visit('si', DESKTOP);
    ok(s.requests > 0 && s.src !== '', 'N3: escritorio: el vídeo sigue', JSON.stringify(s));

    // A: también en el celular.
    p = await policy('always');
    ok(p.fix === true && p.mobile === 'always', 'A0: la política del sitio está en «también en el celular»', JSON.stringify(p));
    s = await visit('no', MOBILE);
    ok(s.requests > 0 && s.src !== '' && !s.hiddenClass, 'A1: celular, aunque el elemento no lo pida: se pide y se reproduce', JSON.stringify(s));

    // E: aviso de peso en el editor.
    await policy('element');
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
          $e.route('panel/editor/style');
          const view = elementor.getPanelView().getCurrentPageView();
          view.activateSection('section_background');
          view._renderChildren();
          return true;
        } catch (e) { return String(e); }
      });
      await wait(1000);
      if (!(await admin.$('.elementor-control-background_background'))) {
        await admin.click('.elementor-tab-control-style').catch(() => {});
        await wait(1000);
        await admin.click('.elementor-control-section_background').catch(() => {});
        await wait(1000);
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
    ok(/También se descarga en el celular/.test(text || ''), 'E2: y que se descarga en el celular (tiene «Reproducir en móvil»)', text);
    text = await editor('sin-respaldo');
    ok(/En el celular no se descarga/.test(text || '') && /Falta la «Imagen de respaldo»/.test(text || ''), 'E3: sin «Reproducir en móvil» y sin respaldo: no se descarga en el celular y falta la imagen', text);
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
