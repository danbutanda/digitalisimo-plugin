// Informe de front-network.sh: N1-N7 a partir de result.json (un registro por paso, con sus problemas y lo observado).
const fs = require('fs');
const path = require('path');
const OUT = process.env.OUT;
const result = JSON.parse(fs.readFileSync(path.join(OUT, 'result.json'), 'utf8'));
const HOOK = 'digitalisimo_elements_front_transition';
const problems = {};
const add = (n, ok, message) => { (problems[n] = problems[n] || []); if (!ok) problems[n].push(message); };
const step = (name) => { const s = result[name]; if (!s) add('pasos', false, `falta el paso ${name}`); return s || { problems: [], data: {} }; };
// Problemas propios de cada paso.
for (const [name, s] of Object.entries(result)) for (const p of s.problems || []) add(name.startsWith('post-') ? 'N4' : name.startsWith('screen') || name === 'single' ? 'N5/N7' : 'pasos', false, `${name}: ${p}`);
const applied = (name) => ((step(name).data || {}).state || {}).applied || {};
const scheduled = (name) => ((step(name).data || {}).state || {}).scheduled;
const clears = (name) => (step(name).data || {}).clears || {};
const delta = (from, to, blog) => (clears(to)[blog] || 0) - (clears(from)[blog] || 0);

// Estado inicial: los dos sitios transitan con los valores neutros.
add('pasos', applied('cron-1a').pro_conditional === true && applied('cron-2a').pro_conditional === true, `la transición inicial debe aplicar pro_conditional en los sitios 1 y 2 (${JSON.stringify(applied('cron-1a'))} / ${JSON.stringify(applied('cron-2a'))})`);
// N1: el sitio 2 hereda el apagado de la red y lo aplica en su siguiente petición, con el evento en su blog.
add('N1', !!scheduled('visit-2b'), `tras cambiar la red, la visita al sitio 2 debe agendar ${HOOK} en ese blog`);
add('N1', ((step('cron-2b').data || {}).ran || []).includes(HOOK), `el wp-cron del sitio 2 debe ejecutar ${HOOK}`);
add('N1', applied('cron-2b').pro_conditional === false, `el sitio 2 debe aplicar pro_conditional=false (${JSON.stringify(applied('cron-2b'))})`);
add('N1', !scheduled('visit-1b'), 'el sitio 1 (que personalizó) no debe agendar una transición');
// N2: el sitio 1 conserva su valor propio.
const s1 = (step('post-network-off').data || {}).site1 || {};
add('N2', s1.inherits === false && s1.wanted === true, `el sitio 1 debe personalizar pro_conditional (${JSON.stringify(s1)})`);
add('N2', applied('cron-1b').pro_conditional === true, `el sitio 1 conserva pro_conditional aplicado tras el cambio de red (${JSON.stringify(applied('cron-1b'))})`);
// N3: el sitio 3, creado después, hereda y transita.
add('N3', !!scheduled('visit-3a'), 'la primera visita al sitio 3 debe agendar su transición');
add('N3', applied('cron-3a').pro_conditional === false && !!applied('cron-3a').hash, `el sitio 3 debe aplicar el valor de la red (${JSON.stringify(applied('cron-3a'))})`);
// N4: rechazos y redirecciones (los problemas de cada paso post-*), y la red sólo escribe su opción.
const net = (step('post-network-off').data || {}).network || {};
add('N4', net.pro_conditional === false, `la red debe guardar pro_conditional=false (${JSON.stringify(net)})`);
for (const name of ['post-editor', 'post-siteadmin-net', 'post-bad-nonce']) add('N4', ((step(name).data || {}).result || {}).die?.status === 403, `${name} debe terminar en 403`);
// N6: un vaciado por sitio que cambia, ninguno en los demás.
add('N6', delta('setup', 'cron-1a', '1') === 1 && delta('setup', 'cron-2a', '2') === 1, `la transición inicial vacía una vez cada sitio (${JSON.stringify([clears('setup'), clears('cron-2a')])})`);
add('N6', delta('cron-2a', 'post-site1-own', '1') === 0, 'guardar el sitio 1 sin cambiar lo efectivo no debe vaciar');
add('N6', delta('post-site1-own', 'post-network-off', '1') === 0 && delta('post-site1-own', 'post-network-off', '2') === 0, 'guardar la red no vacía ningún sitio en esa petición');
add('N6', delta('visit-2b', 'cron-2b', '2') === 1 && delta('visit-2b', 'cron-2b', '1') === 0, `la transición del sitio 2 vacía sólo el sitio 2, una vez (${JSON.stringify([clears('visit-2b'), clears('cron-2b')])})`);
add('N6', delta('visit-1b', 'cron-1b', '1') === 0, 'el sitio 1, que no cambia, no se vacía');
add('N6', delta('visit-3a', 'cron-3a', '3') === 1 && delta('visit-3a', 'cron-3a', '2') === 0, `la transición del sitio 3 vacía sólo el sitio 3 (${JSON.stringify([clears('visit-3a'), clears('cron-3a')])})`);
// F7a: «Registrar visitas sin gestor de consentimiento» (page_visits_no_consent), fuera de la transición.
const st = (name, blog) => (((step(name).data || {}).sites || {})[blog]) || ((step(name).data || {}).state) || {};
const own1 = (step('post-site1-own').data || {}).site1 || {};
add('N2', own1.visits === false && own1.visits_inherits === false, `el sitio 1 personaliza page_visits_no_consent apagada (${JSON.stringify(own1)})`);
const netPrivacy = (step('post-network-privacy').data || {}).network || {};
add('N4', netPrivacy.page_visits_no_consent === true && netPrivacy.pro_conditional === false, `la red guarda page_visits_no_consent=true sin cambiar lo demás (${JSON.stringify(netPrivacy)})`);
add('N1', st('post-network-off', 2).visits === false, `antes de encenderla en la red, el sitio 2 la tiene apagada (${JSON.stringify(st('post-network-off', 2))})`);
const v2 = (step('visit-2c').data || {}).state || {};
add('N1', v2.visits === true && v2.visits_inherits === true, `el sitio 2 hereda page_visits_no_consent=true de la red (${JSON.stringify({ visits: v2.visits, inherits: v2.visits_inherits })})`);
const v1 = (step('visit-1c').data || {}).state || {};
add('N2', v1.visits === false, `el sitio 1 conserva page_visits_no_consent apagada tras encenderla la red (${JSON.stringify({ visits: v1.visits, inherits: v1.visits_inherits })})`);
const v3 = (step('visit-3b').data || {}).state || {};
add('N3', v3.visits === true && v3.visits_inherits === true, `el sitio 3 hereda page_visits_no_consent=true (${JSON.stringify({ visits: v3.visits, inherits: v3.visits_inherits })})`);
for (const [visit, cron, blog] of [['visit-2c', 'cron-2c', '2'], ['visit-3b', 'cron-3b', '3']]) {
  add('N6', !scheduled(visit), `page_visits_no_consent no transita: ${visit} no agenda ${HOOK}`);
  add('N6', !((step(cron).data || {}).ran || []).includes(HOOK), `page_visits_no_consent no transita: ${cron} no ejecuta ${HOOK}`);
}
add('N6', !scheduled('visit-1c'), 'el sitio 1 no agenda nada al cambiar la privacidad de la red');
for (const blog of ['1', '2', '3']) add('N6', delta('cron-3a', 'cron-3b', blog) === 0, `cambiar page_visits_no_consent no vacía el sitio ${blog} (${JSON.stringify([clears('cron-3a'), clears('cron-3b')])})`);
for (const n of ['N5/N7']) add(n, true, '');

let failed = 0;
for (const n of ['pasos', 'N1', 'N2', 'N3', 'N4', 'N5/N7', 'N6']) {
  const list = (problems[n] || []).filter(Boolean);
  if (list.length) failed++;
  console.log(`${list.length ? 'FALLA' : 'ok   '} ${n}${list.length ? '\n  - ' + list.join('\n  - ') : ''}`);
}
console.log(`Vaciados por sitio al final: ${JSON.stringify(clears('screen-network'))}`);
process.exit(failed ? 1 : 0);
