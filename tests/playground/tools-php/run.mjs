// Escenarios de «Código PHP» (§11.4) contra un WordPress real de Playground en modo servidor. Hace lo que haría el
// navegador del administrador, con un tarro de cookies: los tres pasos de «Guardar y activar» (prepare → abrir los
// destinos → activate), sin loopback del servidor. Se juzga por los códigos HTTP, las cabeceras y el estado de la sonda.
// Uso: node run.mjs <base local> <URL del sitio> <single|network>   (lo llama tools-php.sh)
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const [, , BASE, SITE, PHASE] = process.argv;
const OUT = process.env.OUT || '/tmp/digi-tools-php';
const PAGE = 'digitalisimo-tools-elementor-templates';
let failed = 0;
const report = [];
const ok = (name, cond, detail = '') => {
  report.push({ phase: PHASE, name, ok: !!cond, detail: cond ? '' : String(detail).slice(0, 600) });
  console.log(`${cond ? 'OK   ' : 'FALLA'}  [${PHASE}] ${name}${cond || !detail ? '' : ` · ${String(detail).slice(0, 300)}`}`);
  if (!cond) failed++;
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class Jar {
  constructor(from) { this.c = new Map(from ? from.c : []); }
  take(res) {
    for (const sc of res.headers.getSetCookie()) {
      const [pair] = sc.split(';');
      const i = pair.indexOf('=');
      const name = pair.slice(0, i).trim();
      const value = pair.slice(i + 1).trim();
      if (value === '' || value === 'deleted' || /expires=[^;]*19[67]\d/i.test(sc) || /max-age=0\b/i.test(sc)) this.c.delete(name);
      else this.c.set(name, value);
    }
  }
  header() { return [...this.c].map(([k, v]) => `${k}=${v}`).join('; '); }
}

const local = (url) => (url.startsWith(SITE) ? BASE + url.slice(SITE.length) : url);
async function req(url, { jar, method = 'GET', body, redirect = 'manual' } = {}) {
  const headers = {};
  if (jar && jar.c.size) headers.cookie = jar.header();
  if (body && typeof body !== 'string') { body = new URLSearchParams(body).toString(); headers['content-type'] = 'application/x-www-form-urlencoded'; }
  const res = await fetch(local(url), { method, body, headers, redirect });
  if (jar) jar.take(res);
  const text = await res.text();
  return { status: res.status, headers: res.headers, text, location: res.headers.get('location') || '', cookies: res.headers.getSetCookie() };
}
const decode = (s) => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
function config(html) {
  const m = html.match(/window\.digitalisimoToolsPhp = (\{.*?\});(?:\n|<\/script>)/s);
  return m ? JSON.parse(m[1]) : null;
}
// Formularios POST del manejador único que hay en la página: { do, id, fields }.
function forms(html) {
  const out = [];
  for (const m of html.matchAll(/<form method="post" action="([^"]+)"[^>]*>(.*?)<\/form>/gs)) {
    const fields = {};
    for (const i of m[2].matchAll(/<input type="hidden" name="([^"]+)" value="([^"]*)">/g)) fields[i[1]] = decode(i[2]);
    if (fields.action === 'digitalisimo_tools_php') out.push({ action: decode(m[1]), do: fields.do, id: fields.id, fields });
  }
  return out;
}
const formFor = (html, act, id = '') => forms(html).find((f) => f.do === act && f.id === id);
function resumeButton(html, act, id) {
  const m = [...html.matchAll(/<button type="button" class="button" data-digitalisimo-php-resume="([a-z_]+)" data-scope="([a-z]+)" data-id="([0-9a-f]{8})" data-prepare="([^"]+)" data-activate="([^"]+)"/g)].find((x) => x[1] === act && x[3] === id);
  return m ? { do: m[1], scope: m[2], id: m[3], prepare: decode(m[4]), activate: decode(m[5]) } : null;
}

async function login(base, jar, loginUrl) {
  jar.c.set('wordpress_test_cookie', 'WP%20Cookie%20check');
  const r = await req(loginUrl, { jar, method: 'POST', body: { log: 'admin', pwd: 'password', 'wp-submit': 'Acceder', redirect_to: `${base}/wp-admin/`, testcookie: '1' } });
  return r;
}
async function ajax(jar, cfg, data) {
  const r = await req(cfg.ajax, { jar, method: 'POST', body: { action: cfg.action, ...data } });
  try { return { http: r.status, ...JSON.parse(r.text) }; } catch { return { http: r.status, success: false, data: { message: r.text.slice(0, 300) } }; }
}
// Paso 2: el escritorio con la sesión y cada portada sin cookies, en paralelo, sin seguir redirecciones.
async function visit(jar, urls) {
  await Promise.all((urls || []).map((u) => (u.kind === 'admin' ? req(u.url, { jar }) : req(u.url))));
}
/** «Guardar y activar» completo desde el editor (o «Reanudar» con $resume). */
async function saveAndActivate(jar, editorUrl, title, code, { reviewed = false, resume = null } = {}) {
  let cfg; let first;
  if (resume) {
    cfg = config((await req(resume.page, { jar })).text);
    first = { do: resume.do, scope: resume.scope, blog_id: cfg.blog, id: resume.id, nonce: resume.prepare };
  } else {
    cfg = config((await req(editorUrl, { jar })).text);
    if (!cfg) return { error: 'sin configuración en el editor' };
    first = { do: 'prepare', scope: cfg.scope, blog_id: cfg.blog, id: cfg.id, nonce: cfg.nonces.prepare, title, code_b64: Buffer.from(code).toString('base64'), reviewed: reviewed ? '1' : '' };
  }
  const r1 = await ajax(jar, cfg, first);
  const id = (r1.data && r1.data.id) || first.id;
  if (!r1.success || !r1.data.sid) return { r1, id, cfg };
  const nonce = resume ? resume.activate : r1.data.nonces.activate;
  await visit(jar, r1.data.urls);
  const step3 = (extra = {}) => ajax(jar, cfg, { do: 'activate', scope: first.scope, blog_id: cfg.blog, id, nonce, sid: r1.data.sid, ...extra });
  let r3 = await step3();
  let retried = false;
  if (r3.success && r3.data.retry) { retried = true; await visit(jar, r3.data.retry); r3 = await step3(); }
  // Como php-snippets.js: mientras falte un resultado, vuelve a preguntar cada 1,5 s hasta 30 s; después, el veredicto final.
  const deadline = Date.now() + 30000;
  while (!r3.success && r3.data && r3.data.pending && Date.now() < deadline) { await sleep(1500); r3 = await step3(); }
  if (!r3.success && r3.data && r3.data.pending) r3 = await step3({ final: '1' });
  return { r1, r3, id, retried, cfg };
}
async function setFlag(siteBase, name, on) { return probe(siteBase, 'flag', `&name=${name}&on=${on ? 1 : 0}`); }
async function probe(siteBase, cmd, extra = '') {
  const r = await req(`${siteBase}/?digi_php_probe=${cmd}${extra}`);
  try { return JSON.parse(r.text); } catch { return { error: r.text.slice(0, 300), status: r.status }; }
}
const find = (rows, title) => (rows || []).find((r) => r.title === title) || {};
const tab = (adminBase, args = '') => `${adminBase}/admin.php?page=${PAGE}&tab=php${args}`;
const mails = async (siteBase) => (await probe(siteBase, 'mails')).mails || [];

async function single() {
  const admin = `${SITE}/wp-admin`;
  const loginUrl = `${SITE}/acceso-equipo/`;
  // 12 · Sin fragmentos, ninguna consulta nombra los fragmentos.
  await setFlag(SITE, 'savequeries', true);
  await req(`${SITE}/?digi_php_queries=s12`);
  await setFlag(SITE, 'savequeries', false);
  const q = JSON.parse(readFileSync(join(OUT, 'queries-s12.json'), 'utf8'));
  ok('12 · sin fragmentos, ninguna consulta menciona digitalisimo_tools_php', q.php.length === 0 && q.total > 0, JSON.stringify(q.php));

  const jar = new Jar();
  const li = await login(SITE, jar, loginUrl);
  ok('acceso por la ruta privada', li.status === 302 && [...jar.c.keys()].some((k) => k.startsWith('wordpress_logged_in_')), `${li.status} ${li.location}`);
  const editorNew = tab(admin, '&edit=new');

  // 1 · Un fragmento válido añade su cabecera en el front.
  const A = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php: ok' ); } );\nadd_action( 'init', static function () { if ( ! headers_sent() ) header( 'X-Digitalisimo-Php-Init: ok' ); } );\n";
  const a = await saveAndActivate(jar, editorNew, 'Cabecera', A);
  ok('1 · «Guardar y activar» pasa la prueba en el escritorio y la portada', a.r3 && a.r3.success && /^Fragmento activado\. Se probó en el escritorio y en la portada sin errores/.test(a.r3.data.message), JSON.stringify(a.r3 || a.r1));
  ok('1 · el enlace de rescate llega una vez en la respuesta', a.r3 && /digitalisimo-php-rescue=1\.[0-9a-f]{32}$/.test(a.r3.data.rescue || ''), a.r3 && a.r3.data.rescue);
  let front = await req(`${SITE}/`);
  ok('1 · la cabecera X-Digitalisimo-Php aparece en el front', front.status === 200 && front.headers.get('x-digitalisimo-php') === 'ok', `${front.status} ${front.headers.get('x-digitalisimo-php')}`);

  // 2 · Un error de sintaxis: rechazado con su línea, el sitio sigue en 200.
  const b = await saveAndActivate(jar, editorNew, 'Sintaxis', "<?php\nfunction ( {\n}\n");
  ok('2 · error de sintaxis rechazado con su línea', b.r1 && !b.r1.success && b.r1.data.line === 2 && /error de sintaxis en la línea 2/.test(b.r1.data.message) && b.r1.data.saved, JSON.stringify(b.r1));
  ok('2 · el sitio responde 200', (await req(`${SITE}/`)).status === 200);

  // 3 · Un fatal al cargarse: la prueba lo rechaza y ningún visitante lo ejecuta.
  const c = await saveAndActivate(jar, editorNew, 'Fatal al cargar', "<?php\ndigi_php_no_existe();\n");
  ok('3 · un fatal al cargarse lo rechaza la prueba', c.r3 && !c.r3.success && /falló en la línea 2: (?:Uncaught )?Error: Call to undefined function digi_php_no_existe\(\)/.test(c.r3.data.message) && c.r3.data.line === 2, JSON.stringify(c.r3 || c.r1));
  let st = await probe(SITE, 'state');
  ok('3 · ningún visitante lo ejecuta (inactivo, sin filas off de la prueba) y el sitio da 200', find(st.site, 'Fatal al cargar').state === 'inactive' && st.off.length === 0 && (await req(`${SITE}/`)).status === 200, JSON.stringify(st));

  // 4 · Un fatal diferido sólo con ?boom=1: la prueba pasa; ?boom=1 da 500; / da 200 sin el fragmento.
  const D = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-D: on' ); } );\nadd_action( 'template_redirect', static function () {\n\tif ( isset( $_GET['boom'] ) ) digi_php_no_existe();\n} );\n";
  const d = await saveAndActivate(jar, editorNew, 'Diferido', D);
  ok('4 · la prueba de un fatal diferido pasa', d.r3 && d.r3.success, JSON.stringify(d.r3 || d.r1));
  const boom = await req(`${SITE}/?boom=1`);
  front = await req(`${SITE}/`);
  ok('4 · ?boom=1 da 500 y / da 200 sin el fragmento', boom.status === 500 && front.status === 200 && !front.headers.get('x-digitalisimo-php-d') && front.headers.get('x-digitalisimo-php') === 'ok', `${boom.status} ${front.status} ${front.headers.get('x-digitalisimo-php-d')}`);
  await probe(SITE, 'clear-mails');
  await req(tab(admin), { jar });
  st = await probe(SITE, 'state');
  const dd = find(st.site, 'Diferido');
  ok('4 · al abrir la pestaña queda «failed» con línea, ruta y mensaje', dd.state === 'failed' && dd.line === 4 && dd.path === '/' && /digi_php_no_existe/.test(dd.message || ''), JSON.stringify(dd));
  ok('4 · el registro anota el fallo y el cron horario está programado', st.log.includes('failed') && st.cron === true && st.off.length === 0, JSON.stringify(st));
  const m4 = await mails(SITE);
  ok('4 · un correo de fallo en texto plano, sin el código', m4.length === 1 && /Se desactivó un fragmento PHP por un error/.test(m4[0].subject) && !m4[0].body.includes('digi_php_no_existe();') && JSON.stringify(m4[0].headers).includes('text/plain'), JSON.stringify(m4));

  // 5 · Salida al cargarse: rechazado.
  const e = await saveAndActivate(jar, editorNew, 'Salida', "<?php\necho 'hola';\n");
  ok('5 · la salida al cargarse se rechaza', e.r3 && !e.r3.success && /imprime 4 caracteres al cargarse/.test(e.r3.data.message), JSON.stringify(e.r3 || e.r1));

  // 6 · Portada que redirige (plugin falso): un reintento y activación.
  await setFlag(SITE, 'redirect', true);
  const F = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-F: ok' ); } );\n";
  const f = await saveAndActivate(jar, editorNew, 'Tras redirección', F);
  await setFlag(SITE, 'redirect', false);
  ok('6 · la portada redirige: un reintento y se activa', f.retried && f.r3 && f.r3.success, JSON.stringify({ retried: f.retried, r3: f.r3 }));
  const rescueLink = f.r3 && f.r3.data.rescue;

  // 7 · La constante: sin cabecera y la pantalla en modo reducido.
  await setFlag(SITE, 'safe-mode', true);
  front = await req(`${SITE}/`);
  let page = await req(tab(admin, '&edit=new'), { jar });
  await setFlag(SITE, 'safe-mode', false);
  ok('7 · con la constante no corre ningún fragmento', front.status === 200 && !front.headers.get('x-digitalisimo-php'), front.headers.get('x-digitalisimo-php'));
  ok('7 · la pantalla entra en modo reducido (lista sin código, sólo desactivar o detener)', page.text.includes('Modo seguro de Código PHP activo por wp-config.php') && !page.text.includes('<textarea') && page.text.includes('Detener todos los fragmentos en este sitio') && !page.text.includes('Agregar fragmento'), page.status);

  // 11 · DISALLOW_FILE_EDIT: el manejador de edición da 403, el editor está oculto, lo activo sigue y «Desactivar» funciona.
  await setFlag(SITE, 'disallow-edit', true);
  page = await req(tab(admin, '&edit=new'), { jar });
  ok('11 · con DISALLOW_FILE_EDIT el editor está oculto', page.text.includes('La edición de código está bloqueada en este servidor') && !page.text.includes('<textarea'), page.status);
  const blockedPost = await req(`${admin}/admin-post.php`, { jar, method: 'POST', body: { action: 'digitalisimo_tools_php', do: 'save', scope: 'site', blog_id: '1', id: 'new', nonce: 'x', code: '<?php\n' } });
  const blockedAjax = await ajax(jar, { ajax: `${admin}/admin-ajax.php`, action: 'digitalisimo_tools_php' }, { do: 'save', scope: 'site', blog_id: '1', id: 'new', nonce: 'x', code_b64: Buffer.from('<?php\n').toString('base64') });
  ok('11 · guardar da 403 (admin-post y AJAX)', blockedPost.status === 403 && blockedAjax.http === 403 && /DISALLOW_FILE_EDIT/.test(blockedAjax.data.message), `${blockedPost.status} ${blockedAjax.http}`);
  front = await req(`${SITE}/`);
  ok('11 · lo activo sigue corriendo', front.headers.get('x-digitalisimo-php') === 'ok' && front.headers.get('x-digitalisimo-php-f') === 'ok');
  const listBlocked = await req(tab(admin), { jar });
  const offF = formFor(listBlocked.text, 'deactivate', f.id);
  const deact = offF ? await req(offF.action, { jar, method: 'POST', body: offF.fields }) : { status: 0 };
  front = await req(`${SITE}/`);
  ok('11 · «Desactivar» funciona', deact.status === 302 && !front.headers.get('x-digitalisimo-php-f'), `${deact.status} ${front.headers.get('x-digitalisimo-php-f')}`);
  await setFlag(SITE, 'disallow-edit', false);

  // 8 · Rescate con la ruta privada activa.
  const j2 = new Jar();
  const rp = await req(rescueLink, { jar: j2 });
  const key = rescueLink.split('=').pop();
  ok('8 · el enlace abre una página con un solo botón POST, sin repetir la clave', rp.status === 200 && rp.text.includes('Entrar sin fragmentos') && !rp.text.includes(key) && rp.headers.get('referrer-policy') === 'no-referrer' && rp.headers.get('x-frame-options') === 'DENY' && /noindex/.test(rp.headers.get('x-robots-tag') || '') && /no-cache|no-store/.test(rp.headers.get('cache-control') || ''), `${rp.status}`);
  const conf = await req(rescueLink, { jar: j2, method: 'POST', body: { digitalisimo_php_rescue_confirm: '1' } });
  const safeCookie = conf.cookies.find((c) => c.startsWith('digitalisimo_tools_php_safe_1='));
  ok('8 · POST → la ruta privada de acceso (no el 404) con redirect_to y la cookie HttpOnly; SameSite=Lax', conf.status === 302 && conf.location.startsWith(`${SITE}/acceso-equipo`) && conf.location.includes('redirect_to=') && /HttpOnly/i.test(safeCookie || '') && /SameSite=Lax/i.test(safeCookie || ''), `${conf.status} ${conf.location} ${safeCookie}`);
  const lp = await req(conf.location, { jar: j2 });
  const lpPlain = await req(loginUrl);
  ok('8 · el acceso responde 200 y con la cookie no corre ningún fragmento', lp.status === 200 && !lp.headers.get('x-digitalisimo-php-init') && lpPlain.headers.get('x-digitalisimo-php-init') === 'ok', `${lp.status} ${lp.headers.get('x-digitalisimo-php-init')} / ${lpPlain.headers.get('x-digitalisimo-php-init')}`);
  await login(SITE, j2, loginUrl);
  page = await req(tab(admin), { jar: j2 });
  ok('8 · la pantalla queda en modo reducido y sin fragmentos', page.text.includes('Modo seguro de Código PHP: aquí no se ejecuta ningún fragmento') && !page.text.includes('<textarea') && !page.headers.get('x-digitalisimo-php-init') && page.text.includes('Salir del modo seguro'), page.status);
  const saveReduced = await req(`${admin}/admin-post.php`, { jar: j2, method: 'POST', body: { action: 'digitalisimo_tools_php', do: 'save', scope: 'site', blog_id: '1', id: a.id, nonce: 'x', code: '<?php\n' } });
  ok('8 · en modo reducido guardar da 403', saveReduced.status === 403 && saveReduced.text.includes('Estás en modo seguro'), saveReduced.status);
  const offA = formFor(page.text, 'deactivate', a.id);
  const deactA = offA ? await req(offA.action, { jar: j2, method: 'POST', body: offA.fields }) : { status: 0 };
  front = await req(`${SITE}/`);
  ok('8 · en modo reducido «Desactivar» funciona', deactA.status === 302 && !front.headers.get('x-digitalisimo-php'), `${deactA.status}`);
  const forged = new Jar(jar);
  forged.c.set('digitalisimo_tools_php_safe_1', encodeURIComponent(`1|${Math.floor(Date.now() / 1000) + 600}|${'0'.repeat(64)}`));
  page = await req(tab(admin), { jar: forged });
  ok('8 · una cookie falsificada no sirve', page.text.includes('Agregar fragmento') && !page.text.includes('aquí no se ejecuta ningún fragmento'), page.status);
  const exit = formFor((await req(tab(admin), { jar: j2 })).text, 'rescue_exit');
  if (exit) await req(exit.action, { jar: j2, method: 'POST', body: exit.fields });
  ok('8 · «Salir del modo seguro» borra la cookie', !!exit && !j2.c.has('digitalisimo_tools_php_safe_1'), [...j2.c.keys()].join(','));
  await probe(SITE, 'clear-mails');
  const j4 = new Jar();
  const rq = await req(`${SITE}/?digitalisimo-php-rescue=request`, { jar: j4 });
  const rq1 = await req(`${SITE}/?digitalisimo-php-rescue=request`, { jar: j4, method: 'POST', body: { digitalisimo_php_rescue_request: '1' } });
  const afterFirst = (await mails(SITE)).length;
  const rq2 = await req(`${SITE}/?digitalisimo-php-rescue=request`, { jar: j4, method: 'POST', body: { digitalisimo_php_rescue_request: '1' } });
  const m8 = await mails(SITE);
  ok('8 · la petición pública envía un correo y la segunda, antes de 15 minutos, no (misma respuesta)', rq.status === 200 && rq.text.includes('Enviar un enlace de rescate') && rq1.status === 200 && rq2.text === rq1.text && afterFirst === 1 && m8.length === 1 && /digitalisimo-php-rescue=0\.[0-9a-f]{32}/.test(m8[0].body), `${afterFirst} ${m8.length}`);

  // 10 · Una alteración directa de code y hash en la BD: no se ejecuta, aviso y sin botón en bloque.
  const G = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-G: ok' ); } );\n";
  const g = await saveAndActivate(jar, editorNew, 'Firmado', G);
  front = await req(`${SITE}/`);
  ok('10 · antes de alterarlo corre', g.r3 && g.r3.success && front.headers.get('x-digitalisimo-php-g') === 'ok', JSON.stringify(g.r3 || g.r1));
  const t = await probe(SITE, 'tamper', `&id=${g.id}`);
  front = await req(`${SITE}/`);
  ok('10 · el código alterado en la BD no se ejecuta', t.tampered && front.status === 200 && !front.headers.get('x-tampered') && !front.headers.get('x-digitalisimo-php-g'), JSON.stringify(t));
  page = await req(tab(admin), { jar });
  ok('10 · «la base de datos se modificó fuera de DIGITALÍSIMO Tools» y ningún botón en bloque', page.text.includes('La base de datos se modificó fuera de DIGITALÍSIMO Tools') && page.text.includes('Detenido · firma no válida') && !/reactivar todos|revisar y reactivar/i.test(page.text), page.status);
  page = await req(tab(admin, `&edit=${g.id}`), { jar });
  ok('10 · reactivarlo exige revisar el código y marcar la casilla', page.text.includes('name="reviewed"') && page.text.includes('Este fragmento cambió fuera de DIGITALÍSIMO Tools'), page.status);
  // 10 · Sólo el borrador alterado en la BD: sigue la versión firmada, el editor lo avisa y activarlo exige la casilla siempre.
  const B = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-B: ok' ); } );\n";
  const bb = await saveAndActivate(jar, editorNew, 'Borrador firmado', B);
  const td = await probe(SITE, 'tamper-draft', `&id=${bb.id}`);
  front = await req(`${SITE}/`);
  page = await req(tab(admin, `&edit=${bb.id}`), { jar });
  ok('10 · borrador alterado: corre la versión firmada y el editor avisa, con la casilla y las diferencias', bb.r3 && bb.r3.success && td.tampered && front.headers.get('x-digitalisimo-php-b') === 'ok' && !front.headers.get('x-draft-tampered') && page.text.includes('El borrador cambió fuera de DIGITALÍSIMO Tools') && page.text.includes('name="reviewed"') && page.text.includes('Cambios respecto de la última versión con firma válida'), JSON.stringify({ td, status: page.status }));
  const t1 = await saveAndActivate(jar, tab(admin, `&edit=${bb.id}`), 'Borrador firmado', td.code);
  const t2 = await saveAndActivate(jar, tab(admin, `&edit=${bb.id}`), 'Borrador firmado', td.code);
  ok('10 · borrador alterado: sin la casilla no se prueba, tampoco al segundo intento', t1.r1 && !t1.r1.success && t1.r1.data.review && /Revisé este código/.test(t1.r1.data.message) && t2.r1 && !t2.r1.success && /Revisé este código/.test(t2.r1.data.message), JSON.stringify([t1.r1 && t1.r1.data, t2.r1 && t2.r1.data]));
  const t3 = await saveAndActivate(jar, tab(admin, `&edit=${bb.id}`), 'Borrador firmado', td.code, { reviewed: true });
  front = await req(`${SITE}/`);
  ok('10 · borrador alterado: con la casilla se prueba y se activa', t3.r3 && t3.r3.success && front.headers.get('x-draft-tampered') === '1', JSON.stringify(t3.r3 || t3.r1));

  // 13 · Una importación con status=active queda inactiva y pide revisión.
  const im = await probe(SITE, 'import');
  st = await probe(SITE, 'state');
  front = await req(`${SITE}/`);
  page = await req(tab(admin), { jar });
  ok('13 · importado: inactivo y con revisión, aunque llegue status=active', im.result && im.result['generatepress:77'] === 'created' && find(st.site, 'Hook importado').state === 'review' && !front.headers.get('x-imported') && page.text.includes('Importado · sin revisar'), JSON.stringify(im));

  // 14 · Un título y un error con <script>: el título se sanea al guardar; el error (que escribe el guardián desde una
  // petición cualquiera) se muestra escapado en el aviso, la lista y el registro.
  const H = "<?php\nadd_action( 'template_redirect', static function () {\n\tif ( isset( $_GET['boom2'] ) ) throw new Exception( '<script>alert(2)</script>\"><img src=x onerror=alert(3)>' );\n} );\n";
  const h = await saveAndActivate(jar, editorNew, 'Hostil <script>alert(1)</script>"><img src=x onerror=alert(1)>', H);
  st = await probe(SITE, 'state');
  const hostile = st.site.find((r) => r.id === h.id) || {};
  ok('14 · se activa y el título llega saneado (sin etiquetas)', h.r3 && h.r3.success && hostile.title === 'Hostil ">', JSON.stringify({ title: hostile.title, ok: h.r3 && h.r3.success }));
  const b2 = await req(`${SITE}/?boom2=1`);
  const dash = await req(`${admin}/index.php`, { jar });
  const clean = (html) => !html.includes('<script>alert(') && !html.includes('<img src=x');
  ok('14 · el aviso del escritorio muestra el error escapado', b2.status === 500 && dash.text.includes('&lt;script&gt;alert(2)&lt;/script&gt;') && dash.text.includes('&lt;img src=x onerror=alert(3)&gt;') && clean(dash.text), `${b2.status} ${dash.status}`);
  page = await req(tab(admin), { jar });
  const log = (page.text.split('Registro</summary>')[1] || '');
  ok('14 · la lista y el registro lo muestran escapado', page.text.includes('&lt;script&gt;alert(2)&lt;/script&gt;') && log.includes('&lt;script&gt;alert(2)&lt;/script&gt;') && page.text.includes('Hostil &quot;&gt;') && page.text.includes('Desactivado por error') && clean(page.text), page.status);

  // 15 · Portada privada (sin sesión redirige al acceso, como Force Login): el escritorio se prueba y la portada pasa con aviso.
  await setFlag(SITE, 'private', true);
  const P = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-P: ok' ); } );\n";
  const pv = await saveAndActivate(jar, editorNew, 'Con portada privada', P);
  const pfront = await req(`${SITE}/`);
  await setFlag(SITE, 'private', false);
  ok('15 · portada privada: se activa con aviso y la portada redirige al acceso privado', pv.r3 && pv.r3.success && /alguna portada no es pública/.test(pv.r3.data.message) && (pv.r3.data.warnings || []).some((w) => /no es pública \(redirige al acceso\)/.test(w)) && pfront.status === 302 && pfront.location.includes('/acceso-equipo'), JSON.stringify({ r3: pv.r3, status: pfront.status, location: pfront.location }));
  front = await req(`${SITE}/`);
  ok('15 · el fragmento corre en la portada pública', front.status === 200 && front.headers.get('x-digitalisimo-php-p') === 'ok', `${front.status} ${front.headers.get('x-digitalisimo-php-p')}`);
}

async function network() {
  const sites = JSON.parse(readFileSync(join(OUT, 'ready.json'), 'utf8')).sites;
  const s2 = `${SITE}/site2`;
  const s3 = `${SITE}/site3`;
  // 12 · En la red, una sola consulta precarga la opción de Ajustes del sitio y la de los fragmentos.
  await req(`${s2}/`); // La primera visita importa una vez los Ajustes del sitio de SEO: no se cuenta.
  await setFlag(s2, 'savequeries', true);
  await req(`${s2}/?digi_php_queries=n12`);
  await setFlag(s2, 'savequeries', false);
  const q = JSON.parse(readFileSync(join(OUT, 'queries-n12.json'), 'utf8'));
  ok('12 · red: una sola consulta, la misma que ya leía digitalisimo_tools_site_defaults (no sube respecto de 1.0.33)', q.php.length === 1 && q.php[0].includes('digitalisimo_tools_site_defaults') && q.tools.filter((x) => x.includes('digitalisimo_tools_site_defaults')).length === 1, JSON.stringify(q.tools));

  const jar = new Jar();
  const li = await login(SITE, jar, `${SITE}/wp-login.php`);
  ok('red · acceso', li.status === 302, li.status);
  const netAdmin = `${SITE}/wp-admin/network`;
  const N = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-N: ' . get_current_blog_id() ); } );\nadd_action( 'template_redirect', static function () {\n\tif ( isset( $_GET['boomn'] ) && 3 === get_current_blog_id() ) digi_php_no_existe();\n} );\n";
  const n = await saveAndActivate(jar, tab(netAdmin, '&edit=new'), 'De red', N);
  ok('9 · el fragmento de red se prueba en el escritorio de la red y la portada', n.r3 && n.r3.success && /escritorio de la red/.test(n.r3.data.message), JSON.stringify(n.r3 || n.r1));
  const S = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Digitalisimo-Php-S2: ok' ); } );\n";
  const s = await saveAndActivate(jar, tab(`${s2}/wp-admin`, '&edit=new'), 'Del sitio 2', S);
  ok('9 · el fragmento del sitio 2 se activa en el sitio 2', s.r3 && s.r3.success, JSON.stringify(s.r3 || s.r1));
  let f2 = await req(`${s2}/`);
  let f3 = await req(`${s3}/`);
  ok('9 · el del sitio 2 no corre en el 3; el de red corre en los dos', f2.headers.get('x-digitalisimo-php-n') === String(sites[2]) && f2.headers.get('x-digitalisimo-php-s2') === 'ok' && f3.headers.get('x-digitalisimo-php-n') === String(sites[3]) && !f3.headers.get('x-digitalisimo-php-s2'), `${f2.headers.get('x-digitalisimo-php-n')} ${f2.headers.get('x-digitalisimo-php-s2')} ${f3.headers.get('x-digitalisimo-php-n')} ${f3.headers.get('x-digitalisimo-php-s2')}`);
  const boom = await req(`${s3}/?boomn=1`);
  f2 = await req(`${s2}/`);
  f3 = await req(`${s3}/`);
  ok('9 · si falla en el sitio 3 se pausa sólo ahí; el 2 lo sigue ejecutando', boom.status === 500 && f3.status === 200 && !f3.headers.get('x-digitalisimo-php-n') && f2.headers.get('x-digitalisimo-php-n') === String(sites[2]), `${boom.status} ${f3.status} ${f3.headers.get('x-digitalisimo-php-n')} ${f2.headers.get('x-digitalisimo-php-n')}`);
  await probe(SITE, 'clear-mails');
  await probe(SITE, 'cron');
  await probe(SITE, 'cron');
  const m9 = (await mails(SITE)).filter((m) => /se pausó en algunos sitios/.test(m.subject));
  ok('9 · sale un solo correo desde el principal, con el sitio pausado', m9.length === 1 && m9[0].blog === 1 && m9[0].body.includes('/site3/'), JSON.stringify(m9));
  let page = await req(tab(netAdmin), { jar });
  ok('9 · la pantalla de red lo muestra «Pausado en 1 sitio por un error»', page.text.includes('Pausado en 1 sitio por un error · 0 a mano'), page.status);
  const btn = resumeButton(page.text, 'resume_all', n.id);
  const ra = btn ? await saveAndActivate(jar, null, '', '', { resume: { ...btn, page: tab(netAdmin) } }) : {};
  f3 = await req(`${s3}/`);
  ok('9 · «Reanudar en todos los sitios» prueba la muestra y lo reanuda', ra.r3 && ra.r3.success && /reanudado en todos los sitios/.test(ra.r3.data.message) && f3.headers.get('x-digitalisimo-php-n') === String(sites[3]), JSON.stringify(ra.r3 || ra.r1 || 'sin botón'));
  // Una cookie de modo seguro de otro sitio no sirve (el nombre y la firma llevan el sitio). Cada activación sustituye el
  // enlace anterior de quien la hace: vale el de «Reanudar en todos los sitios».
  const link = ra.r3 && ra.r3.data.rescue;
  ok('red · la activación anterior ya no vale (el enlace nuevo la sustituye)', n.r3 && (await req(n.r3.data.rescue, { jar: new Jar(), method: 'POST', body: { digitalisimo_php_rescue_confirm: '1' } })).status === 200);
  const jr = new Jar(jar);
  const conf = link ? await req(link, { jar: jr, method: 'POST', body: { digitalisimo_php_rescue_confirm: '1' } }) : { status: 0 };
  page = await req(tab(netAdmin), { jar: jr });
  ok('red · el enlace de rescate abre la pantalla de red en modo reducido', conf.status === 302 && conf.location.includes('/wp-admin/network/admin.php') && page.text.includes('aquí no se ejecuta ningún fragmento'), `${conf.status} ${conf.location}`);
  const other = new Jar(jar);
  other.c.set(`digitalisimo_tools_php_safe_${sites[2]}`, jr.c.get('digitalisimo_tools_php_safe_1') || '');
  page = await req(tab(`${s2}/wp-admin`), { jar: other });
  ok('8 · una cookie de otro sitio no sirve', page.text.includes('Agregar fragmento') && !page.text.includes('aquí no se ejecuta ningún fragmento'), page.status);
}

try {
  if (PHASE === 'network') await network(); else await single();
} catch (e) {
  ok('sin excepciones en el conductor', false, e.stack || e.message);
}
const file = join(OUT, 'result.json');
const prev = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : [];
writeFileSync(file, JSON.stringify([...prev.filter((r) => r.phase !== PHASE), ...report], null, 2));
process.exit(failed ? 1 : 0);
