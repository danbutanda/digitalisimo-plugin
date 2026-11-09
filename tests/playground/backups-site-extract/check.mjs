// Comprobaciones HTTP del sitio restaurado por backups-site-extract.sh: el sitio 2 de la red, extraído y restaurado en
// un WordPress individual con otra URL y otro prefijo. Compara con el inventario que dejó la fase «red» (contenido,
// usuarios con sus roles, medios, plugins) e inicia sesión de verdad por wp-login.php. Escribe cada resultado en
// <dir>/result.txt («ok · …» o «FALLA · …») y termina con TERMINADO.
// Uso: node check.mjs <puerto> <dir>
import http from 'node:http';
import { createHash } from 'node:crypto';
import { appendFileSync, readFileSync } from 'node:fs';

const [port, dir] = process.argv.slice(2);
const host = `127.0.0.1:${port}`;
const origin = `http://${host}`;
const state = JSON.parse(readFileSync(`${dir}/probe/state.json`, 'utf8'));
const inventory = JSON.parse(readFileSync(`${dir}/probe/inventory.json`, 'utf8'));
const restore = JSON.parse(readFileSync(`${dir}/probe/restore.json`, 'utf8'));
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
    const all = { Host: host, ...headers };
    if (body !== undefined) all['Content-Length'] = Buffer.byteLength(body);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const buffer = Buffer.concat(chunks);
        const text = buffer.toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, buffer, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
const local = (url) => String(url).replace(origin, '') || '/';
const login = async (user, password) => {
  const res = await request('POST', '/wp-login.php', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
    body: `log=${encodeURIComponent(user)}&pwd=${encodeURIComponent(password)}&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
  });
  const cookie = [].concat(res.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
  return { ok: res.status === 302 && /wordpress_logged_in_/.test(cookie), status: res.status, cookie };
};

try {
  const r = restore.result || {};
  log(`· restaurado en ${restore.seconds} s; antes: ${restore.before.home} (prefijo ${restore.before.prefix}) · ${r.notice || ''}`);
  check(restore.verify && restore.verify.compatible === true && restore.verify.scope === 'extracted-site', 'verify en el destino: sitio extraído compatible con este WordPress', JSON.stringify(restore.verify).slice(0, 400));
  check(/red\.test\/dos/.test(restore.verify?.relocation?.text || '') && /wp_2_ → wp_/.test(restore.verify?.relocation?.text || ''), 'verify dice «Origen → este sitio» con la URL y el prefijo que cambian', restore.verify?.relocation?.text);
  check(r.restored === true && /contraseña/.test(r.notice || ''), 'restaurado, con el aviso de con qué cuentas se entra', JSON.stringify(r).slice(0, 400));

  const info = (await request('GET', `/wp-content/digi-web/check.php?token=${token}`)).json;
  if (!check(info && typeof info === 'object', 'el sitio restaurado responde')) throw new Error('sin datos del sitio');
  check(info.home === origin && info.siteurl === origin, 'direcciones del destino', `${info.home} ${info.siteurl}`);
  check(info.prefix === 'wp_' && !info.tables.some((t) => /^wp_2_/.test(t)) && info.tables.includes('wp_users'), 'tablas con el prefijo del destino (wp_2_ → wp_)', info.tables.join(', '));
  check(info.blogname === inventory.blogname, 'el sitio es el sitio 2 de la red', info.blogname);
  const pluginsOk = inventory.plugins.every((p) => info.plugins.includes(p)) && info.plugins.includes('digitalisimo-backups/digitalisimo-backups.php');
  check(pluginsOk, 'plugins activos: los del sitio y los de la red', info.plugins.join(', '));
  // Tools no está activo en el sitio (ni instalado): no recibe los ajustes de red de Tools (B3).
  check(!info.tools, 'un plugin que el sitio no tiene activo no recibe ajustes de la red (Tools)', JSON.stringify(info.tools));

  // Usuarios: los del sitio con sus roles y su contraseña de la red; ni los de otros sitios ni la cuenta del destino. El
  // superadministrador «admin» no es miembro y sólo escribió en el sitio: viaja como autor, sin rol ni contraseña.
  const expected = { ...inventory.users };
  for (const [user, roles] of Object.entries(expected)) {
    const got = info.users[user];
    check(got && JSON.stringify(got.roles.sort()) === JSON.stringify([...roles].sort()), `usuario ${user} con su rol (${roles.join(', ')})`, JSON.stringify(got));
    check(got && got.password === true, `la contraseña de ${user} en la red abre en el destino`);
  }
  check(!info.users.solo3 && !info.users.otro, 'sin usuarios de otros sitios ni superadministradores que no escribieron en él', Object.keys(info.users).join(', '));
  check(info.users.admin && info.users.admin.roles.length === 0 && info.users.admin.password === false, 'el superadministrador que sólo escribió en el sitio viaja como autor, sin rol ni contraseña', JSON.stringify(info.users.admin));

  // Contenido: el mismo, con las direcciones del destino.
  // Las dos URL del medio en la red (la del principal y la del propio sitio) pasan a la del destino.
  const strip = (text) => String(text).split(state.media_url).join('MEDIO').split(state.media_url_site || '\u0000').join('MEDIO').replace(/http:\/\/red\.test\/dos/g, 'SITIO');
  for (const [id, post] of Object.entries(inventory.posts)) {
    const got = info.posts[id];
    if (!check(got && got.title === post.title && got.type === post.type && got.author === post.author, `contenido ${post.type} «${post.title}» con su autor`, JSON.stringify(got).slice(0, 200))) continue;
    const mine = String(got.content).split(info.media_url).join('MEDIO').split(origin).join('SITIO');
    check(mine === strip(post.content), `«${post.title}»: mismo contenido con las URL del destino`, `${mine.slice(0, 200)} ≠ ${strip(post.content).slice(0, 200)}`);
  }
  check(Object.keys(info.posts).length === Object.keys(inventory.posts).length, 'ni una entrada de más ni de menos', `${Object.keys(info.posts).length} ≠ ${Object.keys(inventory.posts).length}`);

  // Medios: los mismos archivos en uploads/ (no en uploads/sites/2), servidos por el destino.
  const files = Object.entries(inventory.files);
  check(files.length > 0 && files.every(([name, sha]) => info.uploads.files[name] === sha), 'medios iguales en uploads/ del destino', JSON.stringify(info.uploads.files).slice(0, 300));
  check(!Object.keys(info.uploads.files).some((name) => name.startsWith('sites/')), 'sin carpeta sites/ en los medios del destino');
  check(info.media_url.startsWith(`${origin}/wp-content/uploads/`) && !info.media_url.includes('/sites/'), 'la URL del medio es la del destino', info.media_url);
  const media = await request('GET', local(info.media_url));
  check(media.status === 200 && createHash('sha256').update(media.buffer).digest('hex') === Object.values(inventory.files).find((sha, i) => Object.keys(inventory.files)[i].endsWith('foto-dos.png')), 'el medio se sirve igual', `${media.status}`);

  // La página de Elements: carga con la imagen del destino y su CSS (regenerado con la URL del destino).
  const page = await request('GET', local(info.page_url));
  check(page.status === 200 && page.text.includes(info.media_url) && !page.text.includes('red.test/dos') && !page.text.includes('/sites/2/'), 'la página de Elements carga con el medio del destino', `${page.status} ${page.text.slice(0, 300)}`);
  const cssHref = (page.text.match(/href=['"]([^'"]*elementor\/css\/post-\d+\.css[^'"]*)['"]/) || [])[1];
  if (check(Boolean(cssHref), 'la página enlaza su CSS de Elements', page.text.match(/<link[^>]+elementor[^>]+>/g)?.slice(0, 3).join(' '))) {
    const css = await request('GET', local(cssHref.replace(/&amp;/g, '&')));
    check(css.status === 200 && css.text.includes(info.media_url) && !css.text.includes('/sites/2/') && !css.text.includes('red.test'), 'el CSS de Elements carga con el fondo del destino', `${css.status} ${css.text.slice(0, 300)}`);
  }
  const contact = Object.values(info.posts).find((p) => p.title === 'Contacto');
  check(contact && (await request('GET', local(contact.url))).status === 200, 'el enlace interno (Contacto) responde en el destino', contact?.url);

  // Inicio de sesión real por wp-login.php y lo que cada rol puede abrir.
  const sessions = {};
  for (const user of Object.keys(expected)) {
    sessions[user] = await login(user, state.passwords[user]);
    check(sessions[user].ok, `inicio de sesión de ${user} con su contraseña de la red`, `${sessions[user].status}`);
  }
  const can = async (user, path) => (await request('GET', path, { headers: { Cookie: sessions[user].cookie } })).status;
  if (sessions.editora?.ok) check(await can('editora', '/wp-admin/edit.php?post_type=page') === 200, 'la editora edita páginas');
  if (sessions.editora?.ok) check(await can('editora', '/wp-admin/plugins.php') === 403, 'la editora no gestiona plugins');
  if (sessions.duena?.ok) check(await can('duena', '/wp-admin/plugins.php') === 200, 'la dueña (administradora) gestiona plugins');
  check(!(await login('admin', state.passwords.admin)).ok, 'admin no puede entrar con su contraseña de la red (no viaja)');
  if (sessions.solo2?.ok) check(await can('solo2', '/wp-admin/edit.php') === 403, 'el usuario sólo de este sitio (suscriptor) no edita');
  for (const user of ['solo3', 'otro']) check(!(await login(user, state.passwords[user])).ok, `${user} no puede entrar (no es del sitio)`);
  // Los drop-ins de la red no viajan: el destino conserva los suyos (en Playground, el db.php de SQLite).
  check(info.dropin_db === true, 'el destino conserva su db.php', String(info.dropin_db));

  // Backups: la configuración del destino se conserva y el historial guarda la copia previa.
  check(info.backups.settings && info.backups.settings.retention === '7', 'la configuración de Backups del destino se conserva', JSON.stringify(info.backups.settings));
  check(info.backups.history.includes('pre-restore') && info.backups.relocation, 'copia previa en el historial e informe de la reubicación');
  // Ningún rastro del sitio en la red (su dirección ni sus medios) salvo guid; lo declarado (enlaces a la portada de la red
  // y correos @red.test) se cuenta.
  check(info.leftovers.length === 0, 'ningún rastro de la dirección del sitio en la red ni de sus medios en la base de datos', info.leftovers.slice(0, 5).join(' | '));
  log(`· declarado (enlaces a la portada de la red y correos): ${info.declared.length} · ${info.declared.slice(0, 6).join(' | ')}`);
  check(info.declared.every((d) => /red\.test\/?(?:["\\&]|$)|@red\.test/.test(d)), 'lo que queda de red.test es sólo la portada de la red o un correo', info.declared.join(' | '));
} catch (error) {
  failures++;
  log(`FALLA · ${error.stack || error}`);
}
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
