// F7a: los dos scripts del navegador de las condiciones nuevas, ejecutados con `vm` tal como salen del PHP.
//
// 1. Frente I · «Visitó una página» (`Page_Visits::SCRIPT` de class-page-visits.php) con un `document.cookie` falso:
//    añade el ID, no lo repite, no pasa de 50 (salen los más antiguos), base 36, ruta, `max-age` de 30 días,
//    `SameSite=Lax`, `Secure` sólo si el servidor lo pide (HTTPS), una cookie dañada se reescribe limpia, no toca las
//    cookies de otros sitios de la red y no pide nada a la red. Consentimiento: con la WP Consent API sólo escribe con
//    `preferences` y al recibir `wp_listen_for_consent_change`; sin ella, sólo con la opción del sitio.
// 2. Frente H · editor (`Conditions_Guard::EDITOR_JS` de class-conditions-guard.php): el control v4 no ofrece las
//    condiciones de F7a (las ya guardadas siguen pintándose), la de cookie no se ofrece a quien no puede guardarla, y al
//    cerrar el modal avisa una vez del aviso común y de una condición variable en una cabecera o un pie.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const root = new URL('../digitalisimo-elements/modules/digitalisimo-conditions/', import.meta.url);
const visitsPhp = readFileSync(new URL('class-page-visits.php', root), 'utf8');
const guardPhp = readFileSync(new URL('class-conditions-guard.php', root), 'utf8');

// ---------------------------------------------------------------------------------------------------------------------
// 1. Visitas.

const literal = visitsPhp.match(/const SCRIPT\s*=\s*'([^']*)';/);
assert.ok(literal, 'Page_Visits::SCRIPT es un literal de comillas simples');
const SCRIPT = literal[1];
assert.ok(!SCRIPT.includes('\\'), 'Sin barras invertidas: el literal PHP es el JS tal cual');
for (const net of ['fetch', 'XMLHttpRequest', 'sendBeacon', 'Image', 'http', 'import(', 'src']) {
  assert.ok(!SCRIPT.includes(net), `El script no pide nada a la red (${net})`);
}

const ATTRS = ';path=/;max-age=2592000;SameSite=Lax';
const call = (name, id, attrs = ATTRS, option = true) =>
  `${SCRIPT}(document,${JSON.stringify(`${name}=`)},${JSON.stringify(id)},${JSON.stringify(attrs)},${option ? '!0' : '!1'})`;

for (const [name, id, attrs] of [
  ['digi_seen', 'zzzzzz', `${ATTRS};Secure`],
  ['digi_seen_12', 'zzzzz', ';path=/sub/;max-age=2592000;SameSite=Lax;Secure'],
  ['digi_seen_123', 'zzzzzz', ';path=/sitio-prueba/;max-age=2592000;SameSite=Lax;Secure'],
]) {
  const bytes = Buffer.byteLength(call(name, id, attrs, false));
  assert.ok(bytes < 400, `El script con sus argumentos ocupa menos de 400 B (${bytes} B, ${name})`);
}

/** Navegador mínimo: un tarro de cookies con atributos, eventos en `document` y la WP Consent API opcional. */
function browser({ cookies = {}, consent } = {}) {
  const jar = new Map(Object.entries(cookies).map(([k, v]) => [k, { value: v, attrs: '' }]));
  const writes = [];
  const listeners = {};
  const document = {
    get cookie() {
      return [...jar].map(([k, v]) => `${k}=${v.value}`).join('; ');
    },
    set cookie(raw) {
      writes.push(raw);
      const [pair, ...attrs] = raw.split(';');
      const eq = pair.indexOf('=');
      jar.set(pair.slice(0, eq), { value: pair.slice(eq + 1), attrs: attrs.join(';') });
    },
    addEventListener(type, fn) {
      (listeners[type] ||= []).push(fn);
    },
  };
  const context = { document };
  context.self = context;
  if (consent) {
    context.wp_has_consent = (category) => {
      context.asked = category;
      return consent.state === 'allow';
    };
  }
  vm.createContext(context);
  return {
    context,
    jar,
    writes,
    run(code) {
      vm.runInContext(code, context);
    },
    dispatch(type, detail) {
      for (const fn of listeners[type] || []) fn({ type, detail });
    },
    listeners,
  };
}

const b36 = (n) => n.toString(36);

{
  const b = browser();
  b.run(call('digi_seen', b36(12)));
  assert.equal(b.jar.get('digi_seen').value, 'c', 'Sin cookie previa: escribe el ID en base 36');
  assert.equal(b.writes.length, 1, 'Una sola escritura');
  assert.equal(b.writes[0], `digi_seen=c${ATTRS}`, 'Nombre, ruta, 30 días y SameSite=Lax, sin Secure');
  assert.ok(!b.writes[0].includes('Secure'), 'Sin HTTPS no hay Secure');
}
{
  const b = browser({ cookies: { digi_seen: 'a.c.b' } });
  b.run(call('digi_seen', 'c'));
  assert.equal(b.jar.get('digi_seen').value, 'a.b.c', 'No repite el ID: lo pasa al final');
}
{
  const ids = Array.from({ length: 50 }, (_, i) => b36(i + 1));
  const b = browser({ cookies: { digi_seen: ids.join('.') } });
  b.run(call('digi_seen', b36(999)));
  const kept = b.jar.get('digi_seen').value.split('.');
  assert.equal(kept.length, 50, 'Nunca pasa de 50 IDs');
  assert.equal(kept[0], '2', 'Sale el más antiguo');
  assert.equal(kept.at(-1), b36(999), 'Entra el actual al final');
  assert.match(b.jar.get('digi_seen').value, /^[0-9a-z]{1,8}(\.[0-9a-z]{1,8}){0,49}$/, 'El resultado cumple el patrón estricto que lee PHP');
}
{
  const b = browser({ cookies: { digi_seen: 'A.<x>..zz.toolongvalue.3' } });
  b.run(call('digi_seen', 'k'));
  assert.equal(b.jar.get('digi_seen').value, 'zz.3.k', 'Una cookie dañada se reescribe limpia');
}
{
  const b = browser({ cookies: { otra: 'x.y', digi_seen_2: 'a.b', digi_seen_21: 'q' } });
  b.run(call('digi_seen_2', 'c', ';path=/dos/;max-age=2592000;SameSite=Lax;Secure'));
  assert.equal(b.jar.get('digi_seen_2').value, 'a.b.c', 'Lee y escribe sólo la cookie de su sitio');
  assert.equal(b.jar.get('digi_seen_21').value, 'q', 'No confunde digi_seen_2 con digi_seen_21');
  assert.equal(b.jar.get('otra').value, 'x.y', 'Las demás cookies quedan igual');
  assert.ok(b.writes[0].endsWith(';path=/dos/;max-age=2592000;SameSite=Lax;Secure'), 'Ruta del sitio y Secure en HTTPS');
}
{
  const b = browser({ cookies: { digi_seen_3: 'z' } });
  b.run(call('digi_seen', 'c'));
  assert.equal(b.jar.get('digi_seen').value, 'c', 'Un sitio único no lee la lista de un sitio de la red');
  assert.equal(b.jar.get('digi_seen_3').value, 'z', 'Ni la cambia');
}

// Consentimiento.
{
  const b = browser();
  b.run(call('digi_seen', 'c', ATTRS, false));
  assert.equal(b.writes.length, 0, 'Sin la WP Consent API y con la opción apagada no escribe');
  b.dispatch('wp_listen_for_consent_change', { preferences: 'allow' });
  assert.equal(b.writes.length, 0, 'Sin la API, un evento ajeno no cambia la opción');
}
{
  const consent = { state: 'deny' };
  const b = browser({ consent });
  b.run(call('digi_seen', 'c', ATTRS, true));
  assert.equal(b.context.asked, 'preferences', 'Pregunta por la categoría preferences');
  assert.equal(b.writes.length, 0, 'Con la API y sin consentimiento no escribe, aunque la opción esté encendida');
  assert.equal(b.listeners.wp_listen_for_consent_change?.length, 1, 'Escucha wp_listen_for_consent_change');
  b.dispatch('wp_listen_for_consent_change', { statistics: 'allow' });
  assert.equal(b.writes.length, 0, 'Otra categoría no basta');
  consent.state = 'allow';
  b.dispatch('wp_listen_for_consent_change', { preferences: 'allow' });
  assert.equal(b.jar.get('digi_seen').value, 'c', 'Al aceptar las preferencias, escribe');
}
{
  const b = browser({ consent: { state: 'allow' } });
  b.run(call('digi_seen', 'c', ATTRS, false));
  assert.equal(b.jar.get('digi_seen').value, 'c', 'Con la API y consentimiento escribe, con la opción apagada');
}

// ---------------------------------------------------------------------------------------------------------------------
// 2. Editor de condiciones (frente H).

const nowdoc = guardPhp.match(/const EDITOR_JS = <<<'JS'\n([\s\S]*?)\nJS;/);
assert.ok(nowdoc, 'Conditions_Guard::EDITOR_JS es un nowdoc');
const EDITOR_JS = nowdoc[1];
assert.ok(Buffer.byteLength(EDITOR_JS) < 4096, 'El script del editor ocupa menos de 4 KB');
for (const net of ['fetch(', 'XMLHttpRequest', 'http']) {
  assert.ok(!EDITOR_JS.includes(net), `El script del editor no pide nada a la red (${net})`);
}

const MORE = ['digitalisimo_cookie', 'digitalisimo_page_visit', 'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_product_stock_of', 'digitalisimo_search'];
const CACHEABLE = ['login_status', 'digitalisimo_special_page', 'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_search'];

function editor({ hide3 = [], type = 'wp-page', container = null, privacy = '' } = {}) {
  const listeners = {};
  const toasts = [];
  const buttons = [];
  const opened = [];
  const conditions = {
    login_status: { label: 'Estado de la sesión', group: 'user', controls: {} },
    digitalisimo_country: { label: 'País', group: 'digitalisimo_visitor', controls: {} },
    digitalisimo_cookie: { label: 'Cookie del navegador', group: 'digitalisimo_visitor', controls: {} },
    digitalisimo_page_visit: { label: 'Visitó una página', group: 'digitalisimo_visitor', controls: {} },
    digitalisimo_field: { label: 'Campo personalizado', group: 'page', controls: {} },
    digitalisimo_special_page: { label: 'Página especial', group: 'page', controls: {} },
    digitalisimo_user_field: { label: 'Campo del usuario', group: 'user', controls: {} },
    digitalisimo_author: { label: 'Autor del contenido', group: 'page', controls: {} },
    digitalisimo_search: { label: 'Búsqueda del visitante', group: 'page', controls: {} },
    digitalisimo_product_stock_of: { label: 'Existencias de productos concretos', group: 'digitalisimo_woocommerce', controls: {} },
  };
  const groups = { page: { label: 'Página' }, user: { label: 'Usuario' }, digitalisimo_visitor: { label: 'Visitante' }, other: { label: 'Otros' } };
  const context = {
    setTimeout: (fn) => fn(),
    digitalisimoConditions: { more: MORE, hide3, cacheable: CACHEABLE, locations: ['header', 'footer'], notice: 'AVISO', location: 'CABECERA', visit: 'VISITAS', privacy, open: 'Abrir' },
    open: (url, target) => opened.push([url, target]),
    addEventListener(name, fn, capture) {
      (listeners[name] ||= []).push({ fn, capture: !!capture });
    },
    elementor: {
      config: { displayConditions: { conditions, groups, show_cache_notice: true } },
      selection: { getElements: () => (container ? [container] : []) },
      helpers: { isAtomicWidget: (model) => !!model?.atomic },
      documents: { getCurrent: () => ({ config: { type } }) },
      notifications: {
        showToast: (options) => {
          toasts.push(options.message);
          buttons.push(options.buttons || null);
        },
      },
    },
  };
  context.window = context;
  vm.createContext(context);
  vm.runInContext(EDITOR_JS, context);
  return {
    context,
    toasts,
    buttons,
    opened,
    listeners,
    dispatch(name, detail) {
      const ordered = [...(listeners[name] || [])].sort((a, b) => Number(b.capture) - Number(a.capture));
      for (const { fn } of ordered) fn({ detail });
    },
  };
}
const settingsOf = (values) => ({ get: (key) => values[key] });
const v3 = (groups) => ({ e_display_conditions: [JSON.stringify(groups)] });
const v4 = (groups) => ({
  'display-conditions': {
    $$type: 'display-conditions',
    value: groups.map((group) => ({ $$type: 'condition-group', value: group.map((c) => ({ $$type: 'string', value: JSON.stringify(c) })) })),
  },
});

{
  const e = editor({ container: { id: 'a1', model: { atomic: true }, settings: settingsOf({}) } });
  assert.equal(e.listeners['elementor/display-conditions/open'][0].capture, true, 'Escucha la apertura en captura, antes que el modal');
  const props = {};
  e.dispatch('elementor/display-conditions/open', { props });
  const config = props.conditionsConfig;
  assert.ok(config, 'v4 (sin rootElement): recibe una configuración propia');
  const offered = Object.values(config.conditionsByGroup).flat();
  for (const id of MORE) assert.ok(!offered.includes(id), `v4 no ofrece ${id}`);
  assert.ok(offered.includes('login_status') && offered.includes('digitalisimo_country') && offered.includes('digitalisimo_special_page'), 'v4 sigue ofreciendo las demás');
  assert.ok(!config.flattenedConditionOptions.some((o) => MORE.includes(o.key)), 'Tampoco en la lista plana del selector');
  assert.deepEqual(JSON.parse(JSON.stringify(config.flattenedConditionOptions[0])), { key: 'page', label: 'Página', isGroup: true }, 'Misma forma que la lista de PRO (otro contexto de vm: se compara su JSON)');
  assert.ok(MORE.every((id) => config.conditions[id]), 'Las condiciones guardadas siguen en `conditions`: un elemento que ya las tiene se pinta');
  assert.equal(config.show_cache_notice, true, 'Conserva el resto de la configuración');
  assert.ok(!config.conditionsByGroup.digitalisimo_woocommerce, 'Un grupo no registrado (sin WooCommerce) no se ofrece');
}
{
  const e = editor({ container: { id: 'a1', model: { atomic: true }, settings: settingsOf({}) } });
  const props = {};
  e.dispatch('elementor/display-conditions/open', { rootElement: {}, props });
  assert.ok(props.conditionsConfig, 'Un elemento atómico seleccionado cuenta como v4 aunque llegue rootElement');
}
{
  const e = editor({ container: { id: 'b1', model: {}, settings: settingsOf({}) } });
  const props = {};
  e.dispatch('elementor/display-conditions/open', { rootElement: {}, props });
  assert.equal(props.conditionsConfig, undefined, 'v3 con permiso: el modal usa la configuración de siempre');
  const preset = { conditions: {} };
  const given = { conditionsConfig: preset };
  e.dispatch('elementor/display-conditions/open', { props: given });
  assert.equal(given.conditionsConfig, preset, 'Una configuración que ya viene no se toca');
}
{
  const e = editor({ hide3: ['digitalisimo_cookie'], container: { id: 'b1', model: {}, settings: settingsOf({}) } });
  const props = {};
  e.dispatch('elementor/display-conditions/open', { rootElement: {}, props });
  const offered = Object.values(props.conditionsConfig.conditionsByGroup).flat();
  assert.ok(!offered.includes('digitalisimo_cookie') && offered.includes('digitalisimo_page_visit'), 'v3 sin unfiltered_html: no se ofrece la cookie, sí las demás');
}

// Avisos al cerrar.
const cookieGroups = [[{ condition: 'digitalisimo_cookie', name: 'vip', comparator: 'is_not_empty' }]];
{
  const e = editor({ type: 'header', container: { id: 'h1', model: {}, settings: settingsOf(v3(cookieGroups)) } });
  e.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(e.toasts, ['AVISO CABECERA'], 'Cookie en una cabecera: aviso común y aviso de caché');
  e.dispatch('elementor/display-conditions/close', {});
  assert.equal(e.toasts.length, 1, 'Una sola vez por elemento y aviso');
}
{
  const e = editor({ type: 'wp-page', container: { id: 'p1', model: { atomic: true }, settings: settingsOf(v4([[{ condition: 'digitalisimo_field', key: 'color', comparator: 'is', value: 'x' }]])) } });
  e.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(e.toasts, ['AVISO'], 'v4 en una página: sólo el aviso común');
}
{
  const e = editor({ type: 'footer', container: { id: 'f1', model: {}, settings: settingsOf(v3([[{ condition: 'digitalisimo_country' }]])) } });
  e.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(e.toasts, ['CABECERA'], 'Una condición variable propia en un pie: aviso de caché');
}
{
  const e = editor({ type: 'header', container: { id: 'h2', model: {}, settings: settingsOf(v3([[{ condition: 'login_status' }, { condition: 'digitalisimo_search', comparator: 'is', value: 'x' }]])) } });
  e.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(e.toasts, ['AVISO'], 'Cacheables en una cabecera: sólo el aviso común de F7a');
  const n = editor({ type: 'header', container: { id: 'h3', model: {}, settings: settingsOf(v3([[{ condition: 'login_status' }]])) } });
  n.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(n.toasts, [], 'Sin F7a ni variables: ningún aviso');
  const none = editor({ type: 'header' });
  none.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(none.toasts, [], 'Sin elemento seleccionado: nada');
}

{
  const visit = [[{ condition: 'digitalisimo_page_visit', comparator: 'is_none_of', pages: [{ id: 12 }] }]];
  const e = editor({ type: 'wp-page', privacy: 'https://a.test/wp-admin/admin.php?page=digitalisimo-elements-front#digitalisimo-front-privacy', container: { id: 'v1', model: {}, settings: settingsOf(v3(visit)) } });
  e.dispatch('elementor/display-conditions/close', {});
  assert.deepEqual(e.toasts, ['AVISO VISITAS'], '«Visitó una página»: el aviso común y la ayuda del consentimiento');
  assert.equal(e.buttons[0]?.length, 1, 'Con un botón a «Rendimiento» → «Privacidad»');
  e.buttons[0][0].callback();
  assert.deepEqual(JSON.parse(JSON.stringify(e.opened)), [['https://a.test/wp-admin/admin.php?page=digitalisimo-elements-front#digitalisimo-front-privacy', '_blank']], 'El botón abre la sección «Privacidad» del sitio');
  const n = editor({ type: 'wp-page', container: { id: 'v2', model: {}, settings: settingsOf(v3(visit)) } });
  n.dispatch('elementor/display-conditions/close', {});
  assert.equal(n.buttons[0], null, 'Sin manage_options no hay enlace');
}

console.log('F7a · scripts de condiciones: visitas (cookie por sitio, 50 IDs, base 36, Secure, consentimiento, sin red, < 400 B) y editor (v4 sin las de F7a, cookie según permiso, avisos) en verde.');
