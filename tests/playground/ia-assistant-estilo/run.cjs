// Capturas del Asistente IA junto a otras pantallas DIGITALÍSIMO, para comparar el estilo (ia-assistant-estilo.sh).
// En modo claro (el de fábrica): el Asistente con una conversación, su bienvenida, en pantalla angosta (390 px) y con el
// menú de WordPress plegado; y, para comparar, «Herramientas» de Tools y los ajustes de IA Tools. También mide que nada
// se salga de la pantalla (desplazamiento horizontal) y deja medidas.json con los colores que pinta cada pieza.
// Uso: node run.cjs <puerto> <host> <dir> <prefijo>
const fs = require('node:fs');
const http = require('node:http');
const [port, host, dir, prefix] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const ready = JSON.parse(fs.readFileSync(`${dir}/probe/ready.json`, 'utf8'));
const base = `http://${host}`;
const cookies = {};
const get = (p) => new Promise((resolve, reject) => {
  http.get({ host: '127.0.0.1', port: Number(port), path: p, headers: { Host: host } }, (res) => {
    for (const c of res.headers['set-cookie'] || []) { const [pair] = c.split(';'); const i = pair.indexOf('='); cookies[pair.slice(0, i)] = pair.slice(i + 1); }
    res.resume(); res.on('end', resolve);
  }).on('error', reject);
});
(async () => {
  await get(`/?digi_st=login&token=${token}`);
  const puppeteer = require('puppeteer-core');
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--force-color-profile=srgb'] });
  const out = {};
  let failures = 0;
  try {
    const page = await browser.newPage();
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: decodeURIComponent(value), url: base })));
    const shots = [
      ['asistente-ancho', `/wp-admin/admin.php?page=digitalisimo-assistant&conversation=${ready.conversation}`, 1366, 900, false],
      ['asistente-bienvenida', '/wp-admin/admin.php?page=digitalisimo-assistant&new=1', 1366, 900, false],
      ['asistente-plegado', `/wp-admin/admin.php?page=digitalisimo-assistant&conversation=${ready.conversation}`, 1280, 860, true],
      ['asistente-angosto', `/wp-admin/admin.php?page=digitalisimo-assistant&conversation=${ready.conversation}`, 390, 844, false],
      ['tools', '/wp-admin/admin.php?page=digitalisimo-tools-elementor-templates&tab=webp', 1366, 900, false],
      ['ia-tools-ajustes', '/wp-admin/admin.php?page=digitalisimo-ai', 1366, 900, false],
    ];
    for (const [name, url, width, height, folded] of shots) {
      await page.setViewport({ width, height });
      await page.goto(`${base}${url}`, { waitUntil: 'networkidle2', timeout: 180000 });
      if (/assistant/.test(url)) {
        await page.waitForSelector('.dga-root .dga-composer', { timeout: 60000 });
        // Playground con un solo proceso es lento: se espera a que lleguen el historial y, si se pidió, la conversación.
        await page.waitForSelector('.dga-row', { timeout: 120000 });
        if (/conversation=/.test(url)) await page.waitForSelector('.dga-confirm', { timeout: 120000 });
      }
      if (folded) await page.evaluate(() => document.body.classList.add('folded'));
      await new Promise((r) => setTimeout(r, 800));
      await page.screenshot({ path: `${dir}/${prefix}-${name}.png` });
      out[name] = await page.evaluate(() => {
        const css = (sel, props) => { const n = document.querySelector(sel); if (!n) return null; const s = getComputedStyle(n); return Object.fromEntries(props.map((p) => [p, s[p]])); };
        return {
          overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
          body: css('body', ['backgroundColor', 'fontFamily']),
          shell: css('.dga-root', ['backgroundColor', 'backgroundImage', 'borderRadius', 'boxShadow', 'borderTopColor']) || css('.wrap', ['backgroundColor', 'backgroundImage', 'borderRadius', 'boxShadow', 'borderTopColor']),
          title: css('.dga-title', ['color', 'fontSize', 'fontWeight']) || css('.wrap h1', ['color', 'fontSize', 'fontWeight']),
          primary: css('.dga-send', ['backgroundImage', 'backgroundColor', 'borderRadius', 'boxShadow']) || css('.wrap .button-primary', ['backgroundImage', 'backgroundColor', 'borderRadius', 'boxShadow']),
          mine: css('.dga-message.is-me .dga-bubble', ['backgroundImage', 'backgroundColor', 'color']),
          card: css('.dga-confirm', ['borderRadius', 'boxShadow', 'borderTopColor']),
        };
      });
      if (/assistant/.test(url) && out[name].overflowX) { failures++; console.log(`FALLA · ${name}: la pantalla se desplaza a lo ancho`); }
      console.log(`ok · ${prefix}-${name}.png`);
    }
  } finally {
    await browser.close();
  }
  fs.writeFileSync(`${dir}/${prefix}-medidas.json`, JSON.stringify(out, null, 1));
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
