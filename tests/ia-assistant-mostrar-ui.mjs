// IA Tools · la pantalla del Asistente IA no abruma (caso plomerito 2 del 2026-11-24: 19 líneas «Consultó: …»,
// «Acción destructiva: Actualizar producto — resultado desconocido», «Auto · IA Tools elige la IA», «Herramientas del
// MCP» y URLs crudas con ?page_id=). Sin navegador: assistant.js corre en un contexto con un wp.element mínimo y entrega
// sus piezas puras por window.digitalisimoAssistantExpose.
//   U1  el trabajo de un turno (consultas, borradores y notas de avance) va en UNA línea plegable: «Revisé tu tienda y
//       tus páginas (19)», «Preparé el borrador y revisé tu tienda (2)»; la respuesta queda a la vista;
//   U2  los enlaces van con texto y sólo al propio sitio; una URL de otro sitio o javascript: nunca es enlace;
//   U3  ni jerga ni rótulos técnicos en la pantalla: «Acción destructiva», «resultado desconocido», «MCP», «IA Tools
//       elige la IA», «Consultó», «/ayuda» en la bienvenida.
// Falla con el código de 1.0.72 (2d851937). Uso: node tests/ia-assistant-mostrar-ui.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const ui = readFileSync(new URL('../digitalisimo-ia-tools/assets/assistant/assistant.js', import.meta.url), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. assistant.js con lo mínimo para cargarse (sin pintar la App) y sus piezas puras.
let parts = null;
const element = {
  createElement: (type, props, ...children) => ({ type, props: props || {}, children }),
  Fragment: 'Fragment',
  useState: () => [null, () => {}], useEffect: () => {}, useRef: () => ({}), useMemo: (f) => f(), useCallback: (f) => f,
  createRoot: () => ({ render: () => {} }),
  render: () => {},
};
const window = {
  wp: { element },
  digitalisimoAssistantConfig: { site: { name: 'Tienda', home: 'https://tienda.test/' }, pageUrl: 'https://tienda.test/wp-admin/admin.php?page=digitalisimo-assistant', providers: [] },
  digitalisimoAssistantAdapters: { create: () => ({}) },
  digitalisimoAssistantExpose: (p) => { parts = p; },
  location: { href: 'https://tienda.test/wp-admin/admin.php?page=digitalisimo-assistant', host: 'tienda.test' },
  URL,
  localStorage: { getItem: () => null, setItem: () => {} },
  matchMedia: () => ({ matches: false, addEventListener() {}, removeEventListener() {} }),
};
const document = { getElementById: () => ({}) };
vm.runInNewContext(ui, { window, document, console });
ok(parts && typeof parts.groupMessages === 'function' && typeof parts.richParts === 'function', 'assistant.js entrega sus piezas puras a la prueba');

// U1 · El trabajo plegado.
const tool = (i, ability, kind = 'read', status = 'ok') => ({ id: `t${i}`, role: 'tool', ability, kind, status, label: ability, summary: '{}', detail: '' });
const caso = [
  { id: 'u1', role: 'me', text: 'créala, la oferta es de hoy a fin de mes' },
  { id: 'a1', role: 'assistant', text: 'Reviso el sitio: contacto, políticas, producto y la guía.', work: true },
  ...Array.from({ length: 19 }, (_, i) => tool(i, i % 2 ? 'digitalisimo-woo/products' : 'elementor/get-page-structure')),
  { id: 'a2', role: 'assistant', text: 'Listo: [Ver la landing](https://tienda.test/?page_id=12&preview=true).', work: false },
];
let items = parts.groupMessages(caso);
ok(items.length === 3 && items[1].work && items[1].work.length === 20 && items[2].message.id === 'a2', `U1: las 19 consultas y su nota van en un solo grupo; la respuesta, a la vista (${items.length} piezas)`);
ok(parts.workTitle(items[1].work) === 'Revisé tus páginas y tu tienda (19)', `U1: el grupo se llama en palabras llanas: «${parts.workTitle(items[1].work)}»`);
const borrador = [
  { id: 'u2', role: 'me', text: 'créame una landing' },
  tool(30, 'digitalisimo-woo/products'),
  tool(31, 'digitalisimo-sales/create-page', 'draft'),
  tool(32, 'digitalisimo-woo/products', 'write', 'skipped'),
  { id: 'a3', role: 'assistant', text: 'Lista tu landing.', work: false },
];
items = parts.groupMessages(borrador);
ok(items.length === 3 && parts.workTitle(items[1].work) === 'Preparé el borrador y revisé tu tienda (3)', `U1: con el borrador: «${parts.workTitle(items[1].work)}»`);
// Un mensaje con llamadas al que ya no le sigue ninguna herramienta (p. ej. el que deja una tarjeta) queda a la vista.
items = parts.groupMessages([{ id: 'u3', role: 'me', text: 'pon el precio' }, { id: 'a4', role: 'assistant', text: 'Pongo $7,500 en la tienda.', work: true }]);
ok(items.length === 2 && items[1].message && items[1].message.id === 'a4', 'U1: el texto que acompaña a una tarjeta pendiente no se pliega');
// Caso plomerito 4 (corrida real): el turno se detiene en una tarjeta y una consulta quedó después del texto: el texto es
// la respuesta y se ve.
items = parts.groupMessages([{ id: 'u5', role: 'me', text: 'revisa skydropx' }, tool(50, 'digitalisimo-woo/skydropx'), { id: 'a5', role: 'assistant', text: '3 productos no tienen peso. Le pongo el peso a la bomba:', work: true }, tool(51, 'digitalisimo-woo/skydropx', 'destructive', 'blocked')]);
ok(items.some((x) => x.message && x.message.id === 'a5'), 'U1: el texto que acompaña a la tarjeta con la que termina el turno queda a la vista aunque le siga un paso');
items = parts.groupMessages([{ id: 'u6', role: 'me', text: 'revisa skydropx' }, tool(60, 'digitalisimo-woo/skydropx'), { id: 'a6', role: 'assistant', text: 'Skydropx no está vinculada. 3 productos no tienen peso; se lo pongo a la bomba.', work: true }, tool(61, 'digitalisimo-woo/skydropx', 'destructive', 'blocked'), { id: 'a7', role: 'assistant', text: 'Lo hago por la ficha del producto:', work: true }]);
ok(items.some((x) => x.message && x.message.id === 'a6') && items.some((x) => x.message && x.message.id === 'a7'), 'U1: si el turno termina sin respuesta final (se detuvo en una tarjeta), lo que escribió junto a sus pasos queda a la vista');
// Turnos distintos no se juntan.
items = parts.groupMessages([...caso, { id: 'u4', role: 'me', text: 'otra' }, tool(40, 'digitalisimo-ia/site-contact')]);
ok(items.filter((x) => x.work).length === 2 && parts.workTitle(items[items.length - 1].work) === 'Revisé tus datos de contacto (1)', 'U1: cada turno tiene su grupo');

// U2 · Enlaces con texto, sólo del sitio.
let p = parts.richParts('Lista: [Ver la landing](https://tienda.test/?page_id=12&preview=true) y [Editarla](https://tienda.test/wp-admin/post.php?post=12&action=elementor).');
ok(p.filter((x) => x.href).map((x) => x.label).join('|') === 'Ver la landing|Editarla' && p.every((x) => !x.href || x.href.startsWith('https://tienda.test/')), 'U2: «[Ver la landing](URL)» y «[Editarla](URL)» son enlaces con su texto');
ok(!p.some((x) => (x.text || '').includes('page_id')), 'U2: la URL cruda no queda a la vista');
p = parts.richParts('Mira https://otro.test/x y [esto](https://otro.test/y) y [aquí](javascript:alert(1)) y https://tienda.test/?page_id=3');
ok(!p.some((x) => x.href && !x.href.startsWith('https://tienda.test/')), 'U2: nunca un enlace a otro sitio ni a javascript:');
ok(p.some((x) => x.href === 'https://tienda.test/?page_id=3' && x.label === 'Abrir'), 'U2: una URL suelta del sitio se muestra como «Abrir»');
p = parts.richParts('Precio **$7,500** hasta el domingo.');
ok(p.length === 3 && p[1].bold === '$7,500', 'U2: «**así**» en negritas, sin asteriscos');

// U3 · Sin jerga en la pantalla.
const code = ui.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const strings = code.match(/'(?:[^'\\\n]|\\.)*'/g) || [];
for (const bad of ['Acción destructiva', 'resultado desconocido', 'IA Tools elige', 'Herramientas del MCP', 'Consultó', 'acción destructiva']) {
  ok(!strings.some((s) => s.includes(bad)), `U3: ningún texto de la pantalla dice «${bad}»`);
}
ok(!strings.some((s) => /\bMCP\b/.test(s)), 'U3: ningún texto de la pantalla dice «MCP»');
ok(parts.STATES.unknown === 'por revisar' && parts.STATES.skipped === 'no se hizo' && parts.STATES.ok === 'listo', 'U3: los estados en palabras llanas');
ok(parts.VERBS.destructive === 'Cambio importante' && parts.VERBS.read === 'Consulté', 'U3: los rótulos de cada paso en palabras llanas');
ok(/Primero lo preparo en borrador para que lo veas\. Nada se publica ni cambia en tu sitio sin que tú lo confirmes, y si un borrador tiene cambios tuyos, te pregunto antes de rehacerlo\./.test(ui) && !/Con \/ayuda usas una herramienta/.test(ui), 'U3: la bienvenida dice que primero se ve en borrador y lo que se confirma (también rehacer un borrador con cambios tuyos), sin atajos técnicos');
ok(/action\.note \? h\( 'p', \{ className: 'dga-warn dga-note' \}, action\.note \)/.test(ui), 'U3: la tarjeta muestra su nota («Este borrador tiene cambios tuyos: ¿lo rehago encima?»)');
ok(parts.SUGGESTIONS.some((s) => /landing/i.test(s[0])) && parts.SUGGESTIONS.some((s) => /Google/.test(s[0])) && parts.SUGGESTIONS.some((s) => /ventas/.test(s[0])), 'U3: las sugerencias son las del dueño (landing, Google, ventas)');
ok(/'Automática', 'La mejor disponible'/.test(ui) && /'Funciones'/.test(ui), 'U3: el selector de IA y el de funciones en palabras llanas');
ok(!/'Este cambio no tiene vuelta atrás: confírmalo'/.test(ui) && !/action\.description \?/.test(ui) && /'Sí, hazlo'/.test(ui), 'U3: la tarjeta no dice «Acción destructiva» ni «no tiene vuelta atrás» ni pega la descripción técnica de la herramienta (caso plomerito 4)');

console.log(`Asistente IA, pantalla que no abruma: ${checks} comprobaciones correctas.`);
