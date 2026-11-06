// Cita (`digitalisimo-blockquote`) convertida desde el Blockquote de PRO, en Chrome real (sin interfaz) y sin WordPress:
// se pinta igual que PRO, píxel a píxel, con las hojas reales de los dos (`assets/css/widget-blockquote.min.css` de PRO y
// `assets/css/blockquote.css` de la Cita).
// - Alineación: PRO pintaba en el envoltorio, a la vez y en todos los anchos, la clase de la alineación de escritorio y
//   la de cada dispositivo (`elementor-blockquote--align-<valor>`), y su hoja resolvía la unión. El mapa
//   (`maps/blockquote.php`) la pasa a `pro_align` (lo comprueba tests/elements-blockquote.php, con la misma tabla que
//   UNION aquí). Para cada unión posible, cada piel del botón (también la burbuja con color propio, que mueve el pico) y
//   dos anchos, la Cita con el paquete de variables de `Blockquote_Widget::ALIGN` que le toca coincide con PRO. Con la
//   burbuja se admiten hasta BUBBLE píxeles: el suavizado del borde del pico (PRO lo dibuja con bordes y la Cita con
//   `clip-path`); un pico del otro lado, o cualquier otra diferencia de alineación, da muchos más (se comprueba). El botón
//   va a BUTTON px (como «Tamaño» 3 de su control) para que el pico tenga área suficiente frente a ese suavizado.
// - Icono: lo convertido pinta `Blockquote_Widget::ICON_LEGACY`, que coincide con el SVG que pinta PRO (`fab fa-twitter`
//   de Font Awesome del núcleo, `elementor/assets/lib/font-awesome/json/brands.json`); una Cita nueva conserva el propio
//   (`Blockquote_Widget::ICON`), que no coincide.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 180 node tests/elements-blockquote-align-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la alineación de la Cita (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const root = new URL('../digitalisimo-elements/', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

const proCss = read('assets/css/widget-blockquote.min.css');
const ownCss = read('assets/css/blockquote.css');
const source = read('modules/digitalisimo-widgets/class-blockquote.php');
const brands = JSON.parse(read('elementor/assets/lib/font-awesome/json/brands.json'));

// Paquetes de variables de la Cita (const ALIGN) y sus dos iconos, tal como están en el código.
const alignBlock = (source.match(/const ALIGN = array\(([\s\S]*?)\n\t\);/) || [])[1] || '';
const ALIGN = Object.fromEntries([...alignBlock.matchAll(/'([a-z-]+)'\s*=>\s*'([^']+)'/g)].map((m) => [m[1], m[2]]));
ok(['left', 'center', 'right', 'center-left', 'center-right'].every((k) => typeof ALIGN[k] === 'string'), `Blockquote_Widget::ALIGN tiene las cinco alineaciones que podían verse en PRO (${Object.keys(ALIGN).join(', ') || 'ninguna'}).`);
const ICON_LEGACY = (source.match(/const ICON_LEGACY = '([^']+)';/) || [])[1] || '';
const ICON = (source.match(/const ICON = '([^']+)';/) || [])[1] || '';
ok(ICON_LEGACY.startsWith('<svg') && ICON.startsWith('<svg'), 'Blockquote_Widget::ICON_LEGACY e ICON están en el código.');
const bird = brands.icons.twitter;
// El SVG que pinta PRO con los iconos en línea (Icons_Manager::render_icon de `fab fa-twitter`).
const PRO_ICON = `<svg aria-hidden="true" class="e-font-icon-svg e-fab-twitter" viewBox="0 0 ${bird[0]} ${bird[1]}" xmlns="http://www.w3.org/2000/svg"><path d="${bird[4]}"></path></svg>`;

// Unión de las clases de PRO → valor de `pro_align` (o de la alineación de escritorio, si coincide).
const UNION = [
  [[], ''],
  [['left'], 'left'],
  [['center'], 'center'],
  [['right'], 'right'],
  [['left', 'right'], 'right'],
  [['left', 'center'], 'center-left'],
  [['center', 'right'], 'center-right'],
  [['left', 'center', 'right'], 'center-right'],
];
const SKINS = [
  { name: 'clásica', skin: 'classic' },
  { name: 'burbuja', skin: 'bubble' },
  { name: 'burbuja con color propio', skin: 'bubble', color: '#0B6E4F' },
  { name: 'enlace', skin: 'link' },
];
const BUBBLE = 20;
const BUTTON = 30;
const TEXT = 'Una cita con dos alineaciones a la vez también es un diseño que hay que respetar.';

// PRO: su marcado (render() de modules/blockquote/widgets/blockquote.php) y, con color propio, las reglas de su control
// `button_background_color`, que también colorean el pico.
const proWidget = (id, union, s) => {
  const classes = [...union.map((v) => `elementor-blockquote--align-${v}`), 'elementor-blockquote--skin-border', 'elementor-blockquote--button-view-icon-text', `elementor-blockquote--button-skin-${s.skin}`, 'elementor-widget', 'elementor-widget-blockquote'];
  const css = s.color ? `#${id} .elementor-blockquote__tweet-button{background-color:${s.color}}body:not(.rtl) #${id} .elementor-blockquote__tweet-button:before, body #${id}.elementor-blockquote--align-left .elementor-blockquote__tweet-button:before{border-right-color:${s.color};border-left-color:transparent}body.rtl #${id} .elementor-blockquote__tweet-button:before, body #${id}.elementor-blockquote--align-right .elementor-blockquote__tweet-button:before{border-left-color:${s.color};border-right-color:transparent}` : '';
  return `<style>${css}</style><div id="${id}" class="${classes.join(' ')}"><blockquote class="elementor-blockquote"><p class="elementor-blockquote__content">${TEXT}</p><div class="e-q-footer"><cite class="elementor-blockquote__author">Ana Rivera</cite><a href="#" class="elementor-blockquote__tweet-button" target="_blank">${PRO_ICON}<span class="elementor-blockquote__tweet-label">Tweet</span></a></div></blockquote></div>`;
};
// La Cita convertida: su marcado (render() de class-blockquote.php) y la regla que la hoja del documento deja para
// `.digi-blockquote` (las variables de `alignment` sustituidas por las de `pro_align`, que va después).
const ownWidget = (id, align, s, icon = ICON_LEGACY, rules = null) => {
  const classes = ['elementor-widget', 'elementor-widget-digitalisimo-blockquote', 'digi-blockquote--skin-border', 'digi-blockquote--view-icon-text', `digi-blockquote--button-${s.skin}`];
  const css = (align || rules ? `#${id} .digi-blockquote{${rules || ALIGN[align]}}` : '') + (s.color ? `#${id} .digi-blockquote__button{background-color:${s.color}}` : '');
  return `<style>${css}</style><div id="${id}" class="${classes.join(' ')}"><blockquote class="digi-blockquote"><p class="digi-blockquote__content">${TEXT}</p><footer class="digi-blockquote__footer"><cite class="digi-blockquote__author">Ana Rivera</cite><a class="digi-blockquote__button" href="#" target="_blank" rel="noopener noreferrer">${icon}<span class="digi-blockquote__label">Tweet</span></a></footer></blockquote></div>`;
};

const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu', '--font-render-hinting=none'] });
const errors = [];
const outside = [];
try {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setRequestInterception(true);
  page.on('request', (r) => { if (/^(about|data):/.test(r.url())) r.continue().catch(() => {}); else { outside.push(r.url()); r.abort().catch(() => {}); } });
  await page.setViewport({ width: 900, height: 900, deviceScaleFactor: 1 });
  // Píxeles distintos entre dos capturas (más de 48 en algún canal, como tests/playground/pro-ab.sh).
  const diff = async (a, b) => {
    const [sa, sb] = [await a.screenshot({ encoding: 'base64' }), await b.screenshot({ encoding: 'base64' })];
    return page.evaluate(async (x, y) => {
      const load = (src) => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = 'data:image/png;base64,' + src; });
      const [ia, ib] = [await load(x), await load(y)];
      if (ia.width !== ib.width || ia.height !== ib.height) return { size: `${ia.width}×${ia.height} / ${ib.width}×${ib.height}`, count: -1 };
      const pixels = (img) => { const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const g = c.getContext('2d'); g.drawImage(img, 0, 0); return g.getImageData(0, 0, img.width, img.height).data; };
      const [da, db] = [pixels(ia), pixels(ib)];
      let count = 0;
      for (let i = 0; i < da.length; i += 4) if (Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2])) > 48) count++;
      return { size: `${ia.width}×${ia.height}`, count };
    }, sa, sb);
  };
  const render = async (width, a, b) => {
    await page.setContent(`<!doctype html><html lang="es"><head><meta charset="utf-8"><style>${proCss}</style><style>${ownCss}</style><style>body{margin:0;font:16px/1.5 sans-serif;background:#fff}.box{width:${width}px;padding:12px}.box .elementor-blockquote__tweet-button,.box .digi-blockquote__button{font-size:${BUTTON}px}</style></head><body><div class="box">${a}</div><div class="box">${b}</div></body></html>`, { waitUntil: 'load' });
    return [await page.$('#a'), await page.$('#b')];
  };

  for (const width of [600, 280]) {
    for (const s of SKINS) {
      for (const [union, align] of UNION) {
        const [a, b] = await render(width, proWidget('a', union, s), ownWidget('b', align, s));
        const d = await diff(a, b);
        const max = 'bubble' === s.skin ? BUBBLE : 0;
        ok(d.count >= 0 && d.count <= max, `${width}px · ${s.name} · PRO con [${union.join(', ') || 'sin clases'}] y la Cita con «${align || 'sin alineación'}»: ${d.count < 0 ? 'distinto tamaño ' + d.size : d.count + ' píxeles distintos de ' + d.size}.`);
      }
    }
  }
  // Prueba del arnés: con la alineación de escritorio en lugar de la unión, la Cita no coincide (centro en tableta y
  // derecha en móvil, el caso bq-responsive del A/B).
  {
    const [a, b] = await render(600, proWidget('a', ['center', 'right'], SKINS[1]), ownWidget('b', '', SKINS[1]));
    const d = await diff(a, b);
    ok(d.count < 0 || d.count > 10 * BUBBLE, `Sin pro_align la Cita no se ve como PRO con dos clases (${d.count < 0 ? 'otro tamaño, ' + d.size : d.count + ' píxeles distintos'}): la prueba distingue.`);
  }
  // Y distingue el lado del pico de la burbuja (izquierda en PRO, derecha en la Cita, con todo lo demás igual).
  {
    const [a, b] = await render(600, proWidget('a', ['left'], SKINS[1]), ownWidget('b', '', SKINS[1], ICON_LEGACY, ALIGN.left.replace('--digi-bq-s:1', '--digi-bq-s:-1')));
    const d = await diff(a, b);
    ok(d.count > 2 * BUBBLE, `Un pico de la burbuja del otro lado no pasa por suavizado (${d.count} píxeles distintos, tope ${BUBBLE}).`);
  }
  // Icono: el de PRO coincide; el propio no.
  {
    const [a, b] = await render(600, proWidget('a', [], SKINS[0]), ownWidget('b', '', SKINS[0], ICON));
    const d = await diff(a, b);
    ok(d.count > BUBBLE, `Una Cita nueva conserva su icono propio, distinto del de PRO (${d.count} píxeles distintos).`);
  }
  ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
  ok(outside.length === 0, 'Ninguna petición fuera de la página: ' + outside.join(', '));
  console.log(`DIGITALÍSIMO Elements: la Cita convertida se pinta como el Blockquote de PRO en Chrome (${UNION.length} uniones de alineación, ${SKINS.length} pieles del botón, 2 anchos, icono de PRO): ${checks} comprobaciones.`);
} finally {
  await browser.close();
}
