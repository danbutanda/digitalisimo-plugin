// Recorrido de ms-forms.sh con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH): en una red real de Playground,
// guarda cada formulario como un usuario (escribir, pulsar «Guardar»), recarga y comprueba que quedó, en la red, en el
// sitio principal y en un subsitio:
//   Hosting   la red guarda; el subsitio que nunca guardó usa la red («Heredar» marcada); un cambio mientras hereda avisa
//             y no se usa; al desmarcar «Heredar» conserva los valores y la clave de la red, y consulta dominios con ella;
//   Backups   «Avisos» guardado en la red se ve en el sitio principal y al revés; «Migrar desde otro sitio» ofrece
//             «Importar en la red» en las dos; el subsitio sólo enlaza a la red;
//   Migración la red tiene «Migrar toda la red»; el subsitio sin DIGITALÍSIMO Elements dice por qué no hay botón.
// Uso: node check.mjs <puerto> <host> <carpeta de salida>
import { existsSync, mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, host, out] = process.argv.slice(2);
mkdirSync(out, { recursive: true });
const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const base = `http://${host}`;
let failures = 0;
let checks = 0;
const check = (ok, what) => { checks++; if (ok) console.log(`ok · ${what}`); else { failures++; console.log(`FALLA · ${what}`); } };

const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgm-' : `${tmpdir()}/dgm-`);
const browser = await puppeteer.launch({
  userDataDir: profile,
  env: { ...process.env, TMPDIR: existsSync('/tmp') ? '/tmp' : tmpdir() },
  executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
  headless: true,
  protocolTimeout: 600_000,
  args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${host} 127.0.0.1:${port}, MAP * ~NOTFOUND`],
});
const page = await browser.newPage();
page.setDefaultTimeout(300_000);
page.setDefaultNavigationTimeout(300_000);
await page.setViewport({ width: 1280, height: 900 });
const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(String(e.message || e).slice(0, 300)));
page.on('dialog', (d) => d.accept());

let n = 0;
async function open(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  writeFileSync(`${out}/${String(++n).padStart(2, '0')}-${path.replace(/[^a-z0-9]+/gi, '-').slice(0, 80)}.html`, await page.content());
  return res ? res.status() : 0;
}
const value = (selector) => page.$eval(selector, (el) => el.value).catch(() => null);
const isChecked = (selector) => page.$eval(selector, (el) => el.checked).catch(() => null);
const text = () => page.$eval('#wpbody-content', (el) => el.innerText.replace(/\s+/g, ' ')).catch(() => '');
async function fill(selector, text) {
  await page.$eval(selector, (el) => { el.value = ''; });
  await page.type(selector, text);
}
async function press(selector) {
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(selector)]);
  writeFileSync(`${out}/${String(++n).padStart(2, '0')}-tras-guardar.html`, await page.content());
}

// Sesión (un intento más: el primer arranque de Playground en red a veces responde sin la cookie).
for (let attempt = 0; attempt < 2 && !/wp-admin/.test(page.url()); attempt++) {
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
  if (!(await page.$('#user_login'))) continue;
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
}
check(/wp-admin/.test(page.url()), 'sesión iniciada');

try {
  // ── Hosting ─────────────────────────────────────────────────────────────────────────────────────────────────────
  const hostingNet = '/wp-admin/network/admin.php?page=digitalisimo-network-hosting';
  const hostingSub = '/uno/wp-admin/admin.php?page=digitalisimo-hosting';
  check(200 === await open(hostingNet), 'Hosting de red abre');
  check(null === await isChecked('input[type=checkbox][name="hosting[inherit_network]"]'), 'Hosting de red: sin la casilla «Heredar»');
  await fill('input[name="hosting[api_user]"]', 'api-red');
  await fill('input[name="hosting[username]"]', 'cuenta-red');
  await fill('input[name="hosting[api_key]"]', 'CLAVE-RED-PG');
  await fill('input[name="hosting[client_ip]"]', '198.51.100.4');
  await page.$eval('textarea[name="hosting[domain_products]"]', (el) => { el.value = '.com|10\n.mx|11'; });
  await press('input[name="digitalisimo_hosting_network_save"]');
  check((await text()).includes('Configuración de red guardada.'), 'Hosting de red: «guardada»');
  await open(hostingNet);
  check('api-red' === await value('input[name="hosting[api_user]"]') && (await text()).includes('Guardada · escribe una nueva'), 'Hosting de red: tras recargar, el usuario y la clave siguen');
  check('.com|10\n.mx|11' === (await value('textarea[name="hosting[domain_products]"]') || '').replace(/\r/g, ''), 'Hosting de red: tras recargar, los productos siguen');

  check(200 === await open(hostingSub), 'Hosting del subsitio abre');
  check(true === await isChecked('input[type=checkbox][name="hosting[inherit_network]"]') && 'api-red' === await value('input[name="hosting[api_user]"]'), 'Hosting del subsitio que nunca guardó: hereda la red y muestra sus valores');
  let rest = await page.goto(`${base}/uno/?rest_route=/digitalisimo-hosting/v1/check-domain&domain=prueba.com`, { waitUntil: 'load' }).then((r) => r.text());
  check(/"available":true/.test(rest), `el subsitio consulta dominios con la clave de la red (${rest.slice(0, 120)})`);
  await open(hostingSub);
  await fill('input[name="hosting[api_user]"]', 'api-propio');
  await press('input[name="digitalisimo_hosting_save"]');
  check((await text()).includes('no se usan tus cambios en Usuario API Namecheap'), 'Hosting del subsitio: un cambio mientras hereda avisa que no se usa');
  await open(hostingSub);
  check('api-red' === await value('input[name="hosting[api_user]"]'), 'Hosting del subsitio: mientras hereda, manda la red');
  await page.click('input[type=checkbox][name="hosting[inherit_network]"]');
  await press('input[name="digitalisimo_hosting_save"]');
  await open(hostingSub);
  check(false === await isChecked('input[type=checkbox][name="hosting[inherit_network]"]') && 'api-red' === await value('input[name="hosting[api_user]"]') && (await text()).includes('Guardada · escribe una nueva') && (await text()).includes('Valor efectivo: Este sitio'), 'Hosting del subsitio: al dejar de heredar conserva los valores y la clave que veía');
  rest = await page.goto(`${base}/uno/?rest_route=/digitalisimo-hosting/v1/check-domain&domain=prueba.com`, { waitUntil: 'load' }).then((r) => r.text());
  check(/"available":true/.test(rest), `tras dejar de heredar, el subsitio sigue consultando dominios con la clave que veía (${rest.slice(0, 120)})`);
  await open(hostingSub);
  await fill('input[name="hosting[api_user]"]', 'api-propio');
  await press('input[name="digitalisimo_hosting_save"]');
  await open(hostingSub);
  check('api-propio' === await value('input[name="hosting[api_user]"]'), 'Hosting del subsitio: con valores propios, lo guardado queda');
  await open(hostingNet);
  check('api-red' === await value('input[name="hosting[api_user]"]'), 'Hosting: guardar el subsitio no tocó la red');

  // ── Backups ─────────────────────────────────────────────────────────────────────────────────────────────────────
  const bkNet = '/wp-admin/network/admin.php?page=digitalisimo-network-backups&tab=settings&open=notifications';
  const bkMain = '/wp-admin/admin.php?page=digitalisimo-backups&tab=settings&open=notifications';
  check(200 === await open(bkNet), 'Backups de red: «Ajustes» abre');
  await fill('#digitalisimo-backups-notify-email', 'red@example.test');
  await press('button[name="digitalisimo_backups_notify[save]"]');
  check(page.url().includes('/wp-admin/network/') && (await text()).includes('Avisos guardados.'), `Backups de red: «Avisos guardados.» y vuelve a la red (${page.url().replace(base, '')})`);
  await open(bkMain);
  check('red@example.test' === await value('#digitalisimo-backups-notify-email'), 'Backups: el correo guardado en la red se ve en el sitio principal');
  await fill('#digitalisimo-backups-notify-email', 'principal@example.test');
  await press('button[name="digitalisimo_backups_notify[save]"]');
  check(!page.url().includes('/wp-admin/network/') && (await text()).includes('Avisos guardados.'), `Backups del sitio principal: «Avisos guardados.» y vuelve al sitio (${page.url().replace(base, '')})`);
  await open(bkNet);
  check('principal@example.test' === await value('#digitalisimo-backups-notify-email'), 'Backups: el correo guardado en el sitio principal se ve en la red');
  for (const [label, path] of [['red', '/wp-admin/network/admin.php?page=digitalisimo-network-backups&tab=tools&open=migrate'], ['sitio principal', '/wp-admin/admin.php?page=digitalisimo-backups&tab=tools&open=migrate']]) {
    await open(path);
    check(!!(await page.$('#digitalisimo-backups-migrate-pull form input[name="into"]')), `Backups (${label}): «Migrar desde otro sitio» ofrece «Importar en la red»`);
  }
  await open('/uno/wp-admin/admin.php?page=digitalisimo-backups');
  check((await text()).includes('Ir a Backups de la red') && 0 === (await page.$$('#wpbody-content form')).length, 'Backups del subsitio: sólo el enlace a la red, sin formularios');

  // ── Migración ───────────────────────────────────────────────────────────────────────────────────────────────────
  await open('/wp-admin/network/settings.php?page=digitalisimo-ep-migration');
  check(!!(await page.$('.digi-migrate__button')) && (await text()).includes('Migrar toda la red'), 'Migración de red: botón «Migrar toda la red»');
  await open('/uno/wp-admin/tools.php?page=digitalisimo-ep-migration');
  check(!(await page.$('.digi-migrate__button')) && (await text()).includes('DIGITALÍSIMO Elements no está activo en este sitio'), 'Migración del subsitio sin Elements: dice por qué no hay botón');
  check(!!(await page.$('a[href*="network/settings.php?page=digitalisimo-ep-migration"]')), 'Migración del subsitio: enlace a la pantalla de la red');
} catch (e) {
  check(false, `recorrido: ${String(e.message || e).slice(0, 300)}`);
}
check(0 === jsErrors.length, `sin errores de JavaScript ${jsErrors.length ? JSON.stringify(jsErrors.slice(0, 3)) : ''}`);
await browser.close();
console.log(failures ? `FALLO: ${failures} de ${checks}` : `Formularios en una red real: ${checks} comprobaciones correctas.`);
process.exit(failures ? 1 : 0);
