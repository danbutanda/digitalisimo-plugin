// Inventario de los paquetes de PRO (F-front, frente A, D1, D10 y §6.1).
// La puerta decide qué páginas cargan `elementor-pro-frontend` y `pro-elements-handlers` por los 52 nombres con
// controlador y por los ajustes, acciones y documentos que los paquetes atienden. Esta prueba extrae de los dos
// paquetes todas las formas de engancharse y las compara con tablas fijas: una forma, un nombre, una acción o un
// módulo nuevos hacen fallar, para revisar la puerta antes de publicar otra versión de los paquetes.
// - Las cuatro formas de 1.2: `attachHandler("…")` literal (elements-handlers.js y frontend.js), el arreglo recorrido
//   con variable y `frontend/element_ready/…`; los tipos de elemento; `elementsHandler.*`; acciones de URL; la clase
//   de documento `popup`; ganchos y eventos; `theme_template_id`; las claves de los dos mapas de módulos.
// - Lo mismo en los `.min.js` que se sirven.
// - `Pro_Gate::HANDLED`, `ACTIONS` y `MOTION_KEYS` coinciden con lo extraído.
// - sha256 de los cuatro paquetes igual a 4.3.0.162 (D10: no se editan).
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const E = join(ROOT, 'digitalisimo-elements');
const read = (path) => readFileSync(join(E, path), 'utf8');

const SHA256 = {
  'assets/js/elements-handlers.js': '55953ccfef7c961a680e65cd0bb48922bcc51dcbce74f4926443834f9ae450c4',
  'assets/js/elements-handlers.min.js': 'a853c44787abd99bd51331bd93d432c9fcb7733badc95a9c27ef26b537a7cbee',
  'assets/js/frontend.js': 'c61839076c3a85acff2ae6221366c6da98e8f75d0daf37aa13206a7e212796fc',
  'assets/js/frontend.min.js': 'e910bdea87df8ce5cdb012aedc126b486e1b092f4b07bc69f721d5e1d5d3f6ab',
};

// Tablas fijas (4.3.0.162).
const EH_LITERAL = [
  'animated-headline', 'archive-posts', 'contact-buttons-var-10', 'countdown', 'facebook-button', 'facebook-comments',
  'facebook-embed', 'facebook-page', 'floating-bars-var-2', 'floating-bars-var-3', 'form', 'gallery', 'hotspot',
  'loop-carousel', 'loop-grid', 'lottie', 'media-carousel', 'mega-menu', 'nav-menu', 'nested-carousel', 'off-canvas',
  'portfolio', 'posts', 'reviews', 'search', 'search-form', 'share-buttons', 'slides', 'subscribe', 'table-of-contents',
  'taxonomy-filter', 'testimonial-carousel', 'woocommerce-cart', 'woocommerce-checkout-page', 'woocommerce-menu-cart',
  'woocommerce-my-account', 'woocommerce-notices', 'woocommerce-product-add-to-cart', 'woocommerce-purchase-summary',
];
const EH_ARRAY = ['contact-buttons-var-1', 'contact-buttons-var-3', 'contact-buttons-var-4', 'contact-buttons-var-5', 'contact-buttons-var-6', 'contact-buttons-var-7', 'contact-buttons-var-8', 'contact-buttons-var-9'];
const FE_LITERAL = ['code-highlight', 'paypal-button', 'progress-tracker', 'stripe-button'];
const FE_ELEMENT_READY = ['video-playlist.default'];
const ELEMENT_TYPES = ['container', 'global', 'section', 'widget'];
const EH_CALLS = { attachHandler: 50, runReadyTrigger: 3 };
const FE_CALLS = { addHandler: 1, attachHandler: 8 };
const EH_URL_ACTIONS = ['off_canvas:close', 'off_canvas:open', 'off_canvas:toggle', 'popup:close', 'popup:open', 'reload-page'];
const EH_DOCUMENT_CLASSES = ['popup'];
const EH_HOOK_ACTIONS = ['elementor/frontend/documents-manager/init-classes', 'search:results-displayed', 'search:results-updated'];
const FE_HOOK_ACTIONS = ['frontend/element_ready/video-playlist.default'];
const EH_HOOK_FILTERS = ['elementor-pro/frontend/handlers'];
const FE_APPLY_FILTERS = ['elementor-pro/frontend/handlers'];
// Los tres de `window` viven dentro de controladores (tiempos de un popup y el Mega menú en el editor).
const EH_EVENTS = ['elementorFrontend:components:init', 'elementorProFrontend:elementor-pro/modules/init/before', 'window:elementor/editor/element-destroyed', 'window:elementor/editor/element-rendered', 'window:elementor/popup/hide'];
const FE_EVENTS = ['window:elementor/frontend/init'];
// `elementor-pro/modules/init/before` es el aviso con el que filters.js (F4-Woo) se engancha antes de que frontend.js
// cree los módulos: `hooks` del núcleo nace en init(), así que no puede engancharse al evaluarse.
const FE_TRIGGERS = ['elementor-pro/modules/init/before', 'elementor-video-playList', 'elementorPlaylistWidget:setVideoData', 'focus', 'resize'];
const EH_LOCATION = ['theme_template_id'];
const EH_MAP = ['animatedText', 'carousel', 'contactButtons', 'countdown', 'dynamicTags', 'form', 'gallery', 'hotspot', 'loopBuilder', 'lottie', 'megaMenu', 'nav_menu', 'nestedCarousel', 'offCanvas', 'popup', 'posts', 'search', 'share_buttons', 'slides', 'social', 'tableOfContents', 'taxonomyFilter', 'themeBuilder', 'themeElements', 'woocommerce'];
const FE_MAP = ['codeHighlight', 'motionFX', 'payments', 'progressTracker', 'sticky', 'videoPlaylist'];
const MOTION_SETTINGS = ['background_motion_fx_motion_fx_mouse', 'background_motion_fx_motion_fx_scrolling', 'motion_fx_motion_fx_mouse', 'motion_fx_motion_fx_scrolling'];

const sorted = (list) => [...list].sort();
const unique = (list) => sorted(new Set(list));
const counts = (list) => Object.fromEntries(Object.entries(list.reduce((acc, key) => ({ ...acc, [key]: (acc[key] || 0) + 1 }), {})).sort(([a], [b]) => (a < b ? -1 : 1)));
const problems = [];
const same = (actual, expected, label) => {
  const a = JSON.stringify(actual);
  const b = JSON.stringify(expected);
  if (a !== b) problems.push(`${label}:\n    hallado  ${a}\n    esperado ${b}`);
};

// D10: los paquetes no se editan.
for (const [path, hash] of Object.entries(SHA256)) {
  same(createHash('sha256').update(readFileSync(join(E, path))).digest('hex'), hash, `sha256 de ${path}`);
}

/** Todas las formas de engancharse de un paquete (sirve para el legible y para el .min.js). */
function inventory(source) {
  const attach = [...source.matchAll(/elementsHandler\.attachHandler\(\s*([^,)\s]+)/g)].map((m) => m[1]);
  const literal = attach.filter((arg) => /^"[^"]+"$/.test(arg)).map((arg) => arg.slice(1, -1));
  const variables = attach.filter((arg) => !/^"[^"]+"$/.test(arg));
  // El arreglo de nombres recorrido con una variable que se pasa a attachHandler.
  const arrays = [...source.matchAll(/\[\s*((?:"[^"]+"\s*,?\s*)+)\]\.forEach\(\(?\s*([A-Za-z_$][\w$]*)\s*\)?\s*=>\s*\{?\s*elementorFrontend\.elementsHandler\.attachHandler\(\s*\2\b/g)]
    .map((m) => ({ variable: m[2], names: [...m[1].matchAll(/"([^"]+)"/g)].map((n) => n[1]) }));
  return {
    literal,
    variables,
    arrays,
    calls: counts([...source.matchAll(/elementsHandler\.([A-Za-z_$][\w$]*)\(/g)].map((m) => m[1])),
    elementReady: [...source.matchAll(/frontend\/element_ready\/([^"'`]+)/g)].map((m) => m[1]),
    urlActions: [...source.matchAll(/urlActions\.addAction\(\s*"([^"]+)"/g)].map((m) => m[1]),
    documentClasses: [...source.matchAll(/addDocumentClass\(\s*"([^"]+)"/g)].map((m) => m[1]),
    hookActions: [...source.matchAll(/hooks\.addAction\(\s*"([^"]+)"/g)].map((m) => m[1]),
    hookFilters: [...source.matchAll(/hooks\.addFilter\(\s*"([^"]+)"/g)].map((m) => m[1]),
    applyFilters: [...source.matchAll(/hooks\.applyFilters\(\s*"([^"]+)"/g)].map((m) => m[1]),
    events: [
      ...[...source.matchAll(/\b(elementorFrontend|elementorProFrontend)\.on\(\s*"([^"]+)"/g)].map((m) => `${m[1]}:${m[2]}`),
      ...[...source.matchAll(/jQuery\(window\)\.on\(\s*"([^"]+)"/g)].map((m) => `window:${m[1]}`),
      ...[...source.matchAll(/window\.addEventListener\(\s*"(elementor[^"]*)"/g)].map((m) => `window:${m[1]}`),
    ],
    location: [...source.matchAll(/location\.search\.match\(\s*\/([a-z_]+)=/g)].map((m) => m[1]),
    triggers: [...source.matchAll(/\.trigger\(\s*"([^"]+)"/g)].map((m) => m[1]),
  };
}

/** Claves de un mapa de módulos: `{ clave: valor, … }` tras la cabecera dada. */
function mapKeys(source, header) {
  const start = source.indexOf(header);
  if (start === -1) return [`(sin ${header})`];
  const open = source.indexOf('{', start + header.length - 1);
  const close = source.indexOf('}', open);
  return sorted([...source.slice(open + 1, close).matchAll(/([A-Za-z_$][\w$]*)\s*:/g)].map((m) => m[1]));
}

const EH = read('assets/js/elements-handlers.js');
const FE = read('assets/js/frontend.js');
const EHmin = read('assets/js/elements-handlers.min.js');
const FEmin = read('assets/js/frontend.min.js');
const eh = inventory(EH);
const fe = inventory(FE);

// Las cuatro formas de 1.2.
same(unique(eh.literal), sorted(EH_LITERAL), 'attachHandler("…") literal en elements-handlers.js');
same(eh.arrays.map((a) => ({ variable: a.variable, names: sorted(a.names) })), [{ variable: 'handler', names: sorted(EH_ARRAY) }], 'Arreglo recorrido con variable en elements-handlers.js');
same(eh.variables, ['handler'], 'attachHandler con un argumento que no es literal en elements-handlers.js (sólo el del arreglo)');
same(unique(fe.literal), sorted([...ELEMENT_TYPES, ...FE_LITERAL]), 'attachHandler("…") literal en frontend.js (tipos de elemento y widgets)');
same(fe.variables, [], 'attachHandler con un argumento que no es literal en frontend.js');
same(sorted(fe.elementReady), FE_ELEMENT_READY, 'frontend/element_ready/… en frontend.js');
same(eh.elementReady, [], 'frontend/element_ready/… en elements-handlers.js');
same(eh.calls, EH_CALLS, 'Métodos de elementsHandler en elements-handlers.js');
same(fe.calls, FE_CALLS, 'Métodos de elementsHandler en frontend.js');
// Acciones de URL, documentos, ganchos, eventos y parámetros.
same(sorted(eh.urlActions), EH_URL_ACTIONS, 'Acciones de URL en elements-handlers.js');
same(fe.urlActions, [], 'Acciones de URL en frontend.js');
same(eh.documentClasses, EH_DOCUMENT_CLASSES, 'Clases de documento en elements-handlers.js');
same(fe.documentClasses, [], 'Clases de documento en frontend.js');
same(sorted(eh.hookActions), EH_HOOK_ACTIONS, 'hooks.addAction en elements-handlers.js');
same(sorted(fe.hookActions), FE_HOOK_ACTIONS, 'hooks.addAction en frontend.js');
same(eh.hookFilters, EH_HOOK_FILTERS, 'hooks.addFilter en elements-handlers.js');
same(fe.hookFilters, [], 'hooks.addFilter en frontend.js');
same(fe.applyFilters, FE_APPLY_FILTERS, 'hooks.applyFilters en frontend.js');
same(eh.applyFilters, [], 'hooks.applyFilters en elements-handlers.js');
same(unique(eh.events), sorted(EH_EVENTS), 'Eventos que escucha elements-handlers.js');
same(unique(fe.events), sorted(FE_EVENTS), 'Eventos que escucha frontend.js');
same(eh.location, EH_LOCATION, 'Parámetros de URL que lee elements-handlers.js');
same(fe.location, [], 'Parámetros de URL que lee frontend.js');
same(unique(fe.triggers), sorted(FE_TRIGGERS), 'Avisos que lanza frontend.js (filters.js arranca con elementor-pro/modules/init/before)');
// Mapas de módulos: un módulo nuevo puede engancharse de otra forma.
same(mapKeys(EH, 'var extendDefaultHandlers = (defaultHandlers) => {\n\t\tconst handlers = {'), sorted(EH_MAP), 'Mapa de módulos de elements-handlers.js');
same(mapKeys(FE, 'initModules() {\n\t\t\tlet handlers = {'), sorted(FE_MAP), 'Mapa de módulos de frontend.js');
// Los ajustes de los controladores por tipo (efectos de movimiento y sticky).
same(unique([...FE.matchAll(/\["(motion_fx)", "(background_motion_fx)"\]/g)].flatMap((m) => [m[1], m[2]]).flatMap((group) => [`${group}_motion_fx_scrolling`, `${group}_motion_fx_mouse`])), MOTION_SETTINGS, 'Grupos de efectos de movimiento en frontend.js');
assert.ok(/elementSettings\[name \+ "_motion_fx_scrolling"\] \|\| elementSettings\[name \+ "_motion_fx_mouse"\]/.test(FE), 'Los efectos de movimiento se activan con _motion_fx_scrolling o _motion_fx_mouse');
assert.ok(/if \(!this\.getElementSettings\("sticky"\)\)/.test(FE), 'Sticky se activa con el ajuste sticky');

// Los .min.js que se sirven tienen las mismas formas.
const ehMin = inventory(EHmin);
const feMin = inventory(FEmin);
same(unique(ehMin.literal), sorted(EH_LITERAL), 'attachHandler("…") literal en elements-handlers.min.js');
same(ehMin.arrays.map((a) => sorted(a.names)), [sorted(EH_ARRAY)], 'Arreglo recorrido con variable en elements-handlers.min.js');
same(ehMin.variables.length, 1, 'Un solo attachHandler con variable en elements-handlers.min.js');
same(unique(feMin.literal), sorted([...ELEMENT_TYPES, ...FE_LITERAL]), 'attachHandler("…") literal en frontend.min.js');
same(feMin.variables, [], 'attachHandler con variable en frontend.min.js');
same(sorted(feMin.elementReady), FE_ELEMENT_READY, 'frontend/element_ready/… en frontend.min.js');
same(ehMin.calls, EH_CALLS, 'Métodos de elementsHandler en elements-handlers.min.js');
same(feMin.calls, FE_CALLS, 'Métodos de elementsHandler en frontend.min.js');
same(sorted(ehMin.urlActions), EH_URL_ACTIONS, 'Acciones de URL en elements-handlers.min.js');
same(ehMin.documentClasses, EH_DOCUMENT_CLASSES, 'Clases de documento en elements-handlers.min.js');
same(sorted(ehMin.hookActions), EH_HOOK_ACTIONS, 'hooks.addAction en elements-handlers.min.js');
same(sorted(feMin.hookActions), FE_HOOK_ACTIONS, 'hooks.addAction en frontend.min.js');
same(ehMin.location, EH_LOCATION, 'Parámetros de URL en elements-handlers.min.js');
for (const name of [...EH_LITERAL, ...EH_ARRAY]) if (!EHmin.includes(`"${name}"`)) problems.push(`${name} no aparece en elements-handlers.min.js`);
for (const name of FE_LITERAL) if (!FEmin.includes(`"${name}"`)) problems.push(`${name} no aparece en frontend.min.js`);
for (const name of FE_ELEMENT_READY) if (!FEmin.includes(`element_ready/${name}`)) problems.push(`element_ready/${name} no aparece en frontend.min.js`);

// Coordinador de filtros de F4-Woo (V.8.3): `assets/js/filters.js` hereda del filtro de taxonomía de PRO por la
// clave `taxonomyFilter` del filtro `elementor-pro/frontend/handlers` y sólo si existen estos métodos; lee el marcado
// `.e-filter-item[data-filter][aria-pressed]`. Si el paquete los pierde, el coordinador no sustituye nada.
const coordinator = read('assets/js/filters.js');
for (const method of ['refreshLoopWidget', 'getQueryStringInObjectForm', 'setFilterDataForLoopWidget', 'removeFilterFromLoopWidget']) {
  if (!coordinator.includes(`'${method}'`)) problems.push(`filters.js ya no comprueba ${method}: revisar esta lista`);
  for (const [name, source] of [['elements-handlers.js', EH], ['elements-handlers.min.js', EHmin]]) {
    if (!new RegExp(`\\b${method}\\(`).test(source)) problems.push(`${method}() no aparece en ${name} (coordinador de F4-Woo)`);
  }
}
for (const [name, source] of [['elements-handlers.js', EH], ['elements-handlers.min.js', EHmin]]) {
  for (const token of ['e-filter-item', 'data-filter', 'aria-pressed', 'taxonomyFilter']) if (!source.includes(token)) problems.push(`${token} no aparece en ${name} (coordinador de F4-Woo)`);
}

// La puerta coincide con el inventario.
const gate = read('modules/digitalisimo-front/class-pro-gate.php');
const constant = (name) => {
  const m = gate.match(new RegExp(`const ${name}\\s*=\\s*array\\(([\\s\\S]*?)\\);`));
  assert.ok(m, `Pro_Gate::${name} no encontrado`);
  return [...m[1].replace(/\/\/[^\n]*/g, '').matchAll(/'([^']+)'/g)].map((x) => x[1]);
};
const handled = constant('HANDLED');
const names = [...EH_LITERAL, ...EH_ARRAY, ...FE_LITERAL, ...FE_ELEMENT_READY.map((n) => n.split('.')[0])];
same(names.length, 52, 'Nombres con controlador en los paquetes');
same(handled.length, 52, 'Nombres en Pro_Gate::HANDLED');
same(sorted(handled), sorted(names), 'Pro_Gate::HANDLED frente a los paquetes');
same(sorted(constant('ACTIONS')), unique(EH_URL_ACTIONS.map((action) => action.split(':')[0])), 'Pro_Gate::ACTIONS frente a las acciones de URL de los paquetes');
same(sorted(constant('MOTION_KEYS')), MOTION_SETTINGS, 'Pro_Gate::MOTION_KEYS frente a frontend.js');
assert.ok(gate.includes("'ElementorPro\\\\Modules\\\\Popup\\\\Document'") || gate.includes("'popup' === $document->get_name()"), 'La puerta reconoce la clase de documento popup');
assert.ok(gate.includes("isset( $_GET['theme_template_id'] )"), 'La puerta no actúa con ?theme_template_id=');

if (problems.length) {
  console.error(`Inventario de los paquetes de PRO: ${problems.length} diferencias. Una forma nueva de engancharse exige revisar Pro_Gate (docs/ELEMENTS_FRONT_OBJECTIVE.md, Fase 2):\n - ${problems.join('\n - ')}`);
  process.exit(1);
}
console.log(`DIGITALÍSIMO Elements: inventario de los paquetes de PRO en verde (${handled.length} nombres con controlador, sha256 sin cambios).`);
