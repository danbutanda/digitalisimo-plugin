// Comprobaciones HTTP del ensayo de restaurar en otro dominio (backups-relocate.sh). Fase «origin»: la página de
// Elements carga con su CSS y se crea un respaldo por REST (POST /backups y POST /jobs/tick). Fase «dest»: verify dice
// «Origen → este sitio», la restauración por REST reescribe y después, en el dominio nuevo: la página y su CSS sin el
// dominio de origen, la base de datos sin él (salvo guid), el otro WordPress (prefijo wp_) intacto e inicio de sesión.
// Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …») y termina con TERMINADO.
// Uso: node check.mjs origin <puerto> <host> <dir> | node check.mjs dest <puerto> <host> <dir> <dir-origen> <host-origen>
import http from 'node:http';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [phase, port, host, dir, originDir, originHost] = process.argv.slice(2);
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const token = readFileSync(`${dir}/web/token.txt`, 'utf8').trim();
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 700)}` : ''}`); }
  return ok;
};
function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body));
    const all = { Host: host, ...headers };
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 3_600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const basic = (password) => ({ Authorization: `Basic ${Buffer.from(`admin:${password}`).toString('base64')}` });
const api = (method, route, { headers = {}, body } = {}) => request(method, `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1${route}`)}`, {
  headers: body === undefined ? headers : { 'Content-Type': 'application/json', ...headers },
  body,
});
const pathOf = (url) => { const u = new URL(url, `http://${host}`); return u.pathname + u.search; };
// La página de Elements y su CSS generado: devuelve { html, css, cssUrl }.
const page = async (link) => {
  const res = await request('GET', pathOf(link));
  const match = res.text.match(/href=['"]([^'"]*\/elementor\/css\/post-\d+\.css[^'"]*)['"]/);
  const css = match ? await request('GET', pathOf(match[1].replace(/&#038;/g, '&'))) : null;
  return { status: res.status, html: res.text, cssUrl: match ? match[1] : '', css };
};

try {
  if (setup.versions) log(`· ${phase}: ${setup.versions}`);
  if (phase === 'origin') {
    for (const [name, info] of Object.entries(setup.pages)) {
      const view = await page(info.link);
      check(view.status === 200 && view.html.includes('Hola desde el origen'), `origen: la página de Elements «${name}» carga`, `${view.status}`);
      check(view.css && view.css.status === 200 && view.css.text.includes(`${host}${info.image}`), `origen: su CSS generado lleva la imagen de fondo de ${host}`, view.cssUrl || view.html.slice(0, 300));
    }
    let res = await api('POST', '/backups', { headers: basic(setup.password), body: setup.network ? { network: true } : {} });
    check(res.status === 202 && res.json?.job?.id, 'origen: POST /backups encola el respaldo (202)', `${res.status} ${res.text}`);
    const id = res.json?.job?.id;
    for (let i = 0; i < 300 && id; i++) {
      const job = await api('GET', `/jobs/${id}`, { headers: basic(setup.password) });
      if (job.status !== 200 || job.json?.terminal) { res = job; break; }
      const tick = await api('POST', '/jobs/tick', { headers: basic(setup.password), body: {} });
      if (tick.json?.result !== 'worked') await new Promise((done) => setTimeout(done, 3000));
    }
    check(res.json?.status === 'success', 'origen: el respaldo termina verificado', res.text);
    const list = await api('GET', '/backups', { headers: basic(setup.password) });
    const file = list.json?.[0]?.file;
    check(/^digitalisimo-.*\.zip$/.test(file || ''), 'origen: el respaldo está en el historial', list.text.slice(0, 300));
    writeFileSync(`${dir}/origin.json`, JSON.stringify({ file }));
  } else {
    const origin = JSON.parse(readFileSync(`${originDir}/probe/setup.json`, 'utf8'));
    const { file } = JSON.parse(readFileSync(`${originDir}/origin.json`, 'utf8'));
    const network = setup.network;
    let res = await api('GET', `/backups/${file}/verify`, { headers: basic(setup.password) });
    check(res.status === 200 && res.json?.compatible === true && res.json?.relocation?.needed === true && res.json.relocation.text.includes(`Origen http://${originHost} (prefijo wp_) → este sitio http://${host} (prefijo dst_)`), 'destino: verify dice «Origen → este sitio» y qué se reescribirá', res.text.slice(0, 900));
    log(`· ${res.json?.relocation?.text || ''}`);
    const started = Date.now();
    res = await api('POST', `/backups/${file}/restore`, { headers: basic(setup.password), body: { confirm: 'RESTAURAR SIN FIRMA', network, addresses: 'current' } });
    check(res.status === 200 && res.json?.restored === true, `destino: POST restore restaura (${Math.round((Date.now() - started) / 1000)} s)`, res.text.slice(0, 900));
    const relocation = res.json?.relocation || {};
    check(relocation.check && relocation.check.urls === 0 && relocation.check.paths === 0, 'destino: la comprobación final cuenta 0 URL del origen', JSON.stringify(relocation.check));
    log(`· aviso: ${res.json?.notice || ''}`);
    // Después de restaurar, los usuarios son los del respaldo: vale la contraseña de aplicación del origen.
    res = await api('GET', '/status', { headers: basic(origin.password) });
    check(res.status === 200 && res.json?.ready === true, 'destino: REST responde con la cuenta del respaldo', `${res.status} ${res.text.slice(0, 300)}`);
    for (const [name, info] of Object.entries(origin.pages)) {
      const link = info.link.replace(`//${originHost}`, `//${host}`);
      const view = await page(link);
      check(view.status === 200 && view.html.includes('Hola desde el origen'), `destino: la página de Elements «${name}» carga en ${host}`, `${view.status} ${view.html.slice(0, 300)}`);
      check(!view.html.includes(originHost), `destino: la página «${name}» no menciona ${originHost}`, (view.html.match(new RegExp(`.{0,80}${originHost.replace('.', '\\.')}.{0,80}`)) || [''])[0]);
      check(view.css && view.css.status === 200 && view.css.text.includes(`${host}${info.image}`) && !view.css.text.includes(originHost), `destino: su CSS regenerado carga y lleva la imagen de ${host}`, view.cssUrl ? `${view.css?.status} ${view.css?.text.slice(0, 300)}` : 'sin CSS de Elements en la página');
      check(view.html.includes(`http://${host}/contacto/`) || view.html.includes(`http://${host}/uno/contacto/`), `destino: los enlaces internos de «${name}» van a ${host}`);
    }
    const db = (await request('GET', `/wp-content/digi-web/db.php?token=${token}&needle=${encodeURIComponent(originHost)}`)).json || {};
    check(db.prefix === 'dst_' && db.user_roles === true && Object.keys(db.hits || {}).length === 0, `destino: ninguna tabla dst_ menciona ${originHost} (salvo guid: ${db.guid})`, JSON.stringify(db.hits || db).slice(0, 900));
    check(db.other_options === setup.other_options && !(db.tables || []).some((t) => /^wp_/.test(t) && !(setup.tables || []).includes(t)), 'destino: el otro WordPress (prefijo wp_) de la misma base quedó intacto', `${db.other_options} / ${setup.other_options}`);
    if (network) check(Array.isArray(db.blogs) && db.blogs.length === 2 && db.blogs.every((b) => b.domain === host), 'destino: los sitios de la red tienen el dominio nuevo', JSON.stringify(db.blogs));
    const login = await request('POST', '/wp-login.php', {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
      body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
    });
    const cookie = [].concat(login.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
    check(login.status === 302 && /wordpress_logged_in_/.test(cookie), 'destino: inicio de sesión con la cuenta del respaldo', `${login.status}`);
    const admin = await request('GET', network ? '/wp-admin/network/' : '/wp-admin/', { headers: { Cookie: cookie } });
    check(admin.status === 200 && admin.text.includes('wpadminbar') && !admin.text.includes('Lo siento, no tienes permisos') && !/not allowed to access/i.test(admin.text), `destino: el escritorio${network ? ' de la red' : ''} abre con sus permisos (prefijo de los roles renombrado)`, `${admin.status} ${admin.text.slice(0, 200)}`);
  }
} catch (error) {
  check(false, 'el ensayo terminó con un error', error.stack || error.message);
}
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
