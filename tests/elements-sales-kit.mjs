// Kit de ventas de DIGITALÍSIMO Elements: las plantillas que viajan con Elements salen del generador y pasan su
// validador, en las dos variantes (contenedores y secciones), en la CI.
// - scripts/sales-kit/kit_paginas.py --check: las 16 plantillas y el manifiesto son exactamente lo que escribe el
//   generador (nadie edita a mano una plantilla generada);
// - scripts/sales-kit/kit_validar.py: formato, IDs, jerarquía, widgets que existen en Elements, paridad de variantes,
//   enlaces, textos prohibidos, nombres de otras empresas (sólo con excepción declarada en
//   tests/playground/brand-audit/marcas.json) y el manifiesto;
// - el validador falla con una plantilla rota (cada caso de abajo), para que una regla no se apague sin que se note;
// - los nombres de otras empresas que quedan en las plantillas (excepciones del operador) viven sólo en las constantes
//   de scripts/sales-kit/kit_lib.py: fijar o quitar uno es cambiar una línea.
// Necesita python3 (la CI lo trae).
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { cpSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const kit = join(root, 'scripts/sales-kit');
const templates = join(root, 'digitalisimo-elements/modules/digitalisimo-sales/templates');
const py = (args, opts = {}) => spawnSync('python3', ['-I', ...args], { cwd: root, encoding: 'utf8', timeout: 300000, ...opts });
let checks = 0;
const ok = (cond, msg) => { checks++; assert.ok(cond, msg); };

// 1. Generador y validador sobre lo que hay en el repositorio.
let r = py([join(kit, 'kit_paginas.py'), '--check']);
ok(r.status === 0, `el generador no coincide con las plantillas del repositorio:\n${r.stdout}${r.stderr}`);
r = py([join(kit, 'kit_validar.py')]);
ok(r.status === 0, `el validador del kit falla:\n${r.stdout}${r.stderr}`);
ok(/Sin errores\./.test(r.stdout) && (r.stdout.match(/\.json: /g) || []).length === 8, 'el validador revisa las ocho plantillas');

// 2. El validador falla con cada tipo de plantilla rota (sobre una copia).
const manifest = JSON.parse(readFileSync(join(templates, 'manifest.json'), 'utf8'));
const roto = (nombre, variante, archivo, cambio, esperado) => {
  const dir = mkdtempSync(join(tmpdir(), 'digi-sales-kit-'));
  try {
    cpSync(templates, join(dir, 'templates'), { recursive: true });
    cpSync(join(templates, '..', 'guide.md'), join(dir, 'guide.md'));
    const file = join(dir, 'templates', variante, archivo);
    const data = JSON.parse(readFileSync(file, 'utf8'));
    cambio(data);
    writeFileSync(file, JSON.stringify(data));
    const out = py([join(kit, 'kit_validar.py'), join(dir, 'templates'), join(root, 'digitalisimo-elements')]);
    ok(out.status === 1 && esperado.test(out.stdout), `el validador debía fallar con ${nombre} (${esperado}):\n${out.stdout.slice(-1500)}`);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
};
const widgets = (els, out = []) => { for (const e of els) { if (e.elType === 'widget') out.push(e); widgets(e.elements || [], out); } return out; };
const corta = '02-pagina-de-venta-corta.json';
roto('una marca sin excepción', 'sections', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'text-editor').settings.editor = '<p>Paga con PayPal</p>'; }, /«PayPal» sin excepción declarada/);
roto('una marca de la lista ampliada (Visa)', 'sections', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'text-editor').settings.editor = '<p>Paga con Visa o en Coppel</p>'; }, /«Visa» sin excepción declarada/);
roto('una red de la lista ampliada (Discord)', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading').settings.title = 'Únete a nuestro Discord'; }, /«Discord» sin excepción declarada/);
roto('WhatsApp fuera del llamado a la acción', 'sections', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading').settings.title = 'Síguenos en WhatsApp'; }, /«WhatsApp» sin excepción declarada/);
roto('WhatsApp en un botón, pero con otro texto', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'button' && /WhatsApp/.test(w.settings.text)).settings.text = 'Hablar por WhatsApp y síguenos en WhatsApp Business'; }, /«WhatsApp» sin excepción declarada/);
roto('OXXO fuera de su lugar', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading').settings.title = 'Paga en OXXO'; }, /«OXXO» sin excepción declarada/);
roto('un enlace a otro sitio', 'sections', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'button').settings.link.url = 'https://otro.test/'; }, /enlace inesperado/);
roto('un video de otro servicio', 'containers', '06-oferta-unica-despues-de-la-compra.json', (d) => { widgets(d.content).find((w) => w.widgetType === 'video').settings.youtube_url = 'https://www.youtube.com/watch?v=abcdefghijk'; }, /video con un enlace|YouTube/);
roto('un id repetido', 'sections', corta, (d) => { d.content[1].id = d.content[0].id; }, /id repetido/);
roto('un widget que no existe', 'containers', corta, (d) => { widgets(d.content)[0].widgetType = 'no-existe'; }, /no existe en Elements/);
roto('variantes distintas', 'containers', corta, (d) => { widgets(d.content)[0].settings.title = 'Otro texto'; }, /mismos widgets en el mismo orden/);
roto('una fila que se desborda', 'containers', corta, (d) => { const fila = d.content.find((c) => c.settings.flex_direction === 'row'); fila.elements[0].settings.width.size = 90; }, /anchos que suman/);
roto('una fila que no se apila', 'containers', corta, (d) => { delete d.content.find((c) => c.settings.flex_direction === 'row').settings.flex_direction_mobile; }, /no se apila en celular/);
roto('un contador con fecha fija', 'sections', '05-carrito.json', (d) => { widgets(d.content).find((w) => w.widgetType === 'countdown').settings.due_date = '2026-12-31 23:59'; }, /contador con una fecha fija/);
roto('texto de relleno', 'sections', corta, (d) => { widgets(d.content)[0].settings.title = 'Lorem ipsum'; }, /texto prohibido/);
roto('el nombre del proveedor del núcleo', 'sections', corta, (d) => { widgets(d.content)[0].settings.title = 'Hecho con Elementor'; }, /proveedor del núcleo/);
roto('la persona de la versión para los sitios propios', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading' && /Hola, soy/.test(w.settings.title)).settings.title = 'Hola, te saluda Daniel Butanda'; }, /texto prohibido «Daniel»/);
roto('un título con atributos (el núcleo se los quita a una editora)', 'sections', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading' && /<span[\s>]/.test(w.settings.title)).settings.title = 'Hoy: <span style="color:#00A86B">$[precio] MXN</span>'; }, /título con atributos/);
roto('un resalte sin su color en el CSS propio', 'containers', corta, (d) => { delete widgets(d.content).find((w) => w.widgetType === 'heading' && /<span[\s>]/.test(w.settings.title)).settings.custom_css; }, /título con <span> sin su color/);
roto('una sección fuera de sus bloques', 'sections', corta, (d) => { d.content.splice(1, 0, d.content.splice(3, 1)[0]); }, /bloques del manifiesto no recorren las secciones en orden/);
// La v4 del kit (2026-11-13): la portada en una pantalla y sin video, el precio sólo en la oferta, los bonus sin precio
// y la barra de cierre fija con el logo del evento.
const completa = '01-pagina-de-venta-completa.json';
const seccionDe = (d, archivo, clave) => { const ids = manifest.templates.find((t) => t.file === archivo).blocks.find((b) => b.key === clave).sections; return d.content.filter((s) => ids.includes(s.id)); };
const video = { id: 'f00dcaf', elType: 'widget', widgetType: 'video', settings: { youtube_url: '[enlace del video]' }, elements: [] };
roto('una portada con video', 'sections', completa, (d) => { seccionDe(d, completa, 'promesa')[0].elements[0].elements.splice(2, 0, video); }, /la portada debe llevar logo, la promesa/);
roto('una portada con video (contenedores)', 'containers', '03-captacion.json', (d) => { seccionDe(d, '03-captacion.json', 'promesa')[0].elements.push(video); }, /la portada debe llevar logo, la promesa/);
roto('una portada sin botón', 'containers', corta, (d) => { const s = seccionDe(d, corta, 'promesa')[0]; s.elements = s.elements.filter((w) => w.widgetType !== 'button'); }, /la portada debe llevar logo, la promesa/);
roto('una portada sin el antetítulo chico', 'sections', corta, (d) => { const c = seccionDe(d, corta, 'promesa')[0].elements[0]; c.elements = c.elements.filter((w) => w.settings.header_size !== 'p'); }, /la portada debe llevar logo, la promesa/);
roto('el botón de la portada sin destino', 'sections', '03-captacion.json', (d) => { widgets(seccionDe(d, '03-captacion.json', 'promesa')).find((w) => w.widgetType === 'button').settings.link.url = '#nada'; }, /botón de la portada debe llevar a un bloque/);
roto('un precio en la portada', 'containers', completa, (d) => { widgets(seccionDe(d, completa, 'promesa')).find((w) => w.settings.header_size === 'p').settings.title = 'Hoy, por $1,997 MXN'; }, /precio fuera de la oferta en el bloque «promesa»/);
roto('un precio en los beneficios', 'sections', corta, (d) => { widgets(seccionDe(d, corta, 'beneficios')).find((w) => w.widgetType === 'icon-box').settings.description_text = 'Desde $[precio]'; }, /precio fuera de la oferta en el bloque «beneficios»/);
roto('un precio en MXN en el problema', 'sections', completa, (d) => { widgets(seccionDe(d, completa, 'problema')).find((w) => w.widgetType === 'text-editor').settings.editor = '<p>Cuesta [precio] MXN</p>'; }, /precio fuera de la oferta en el bloque «problema»/);
roto('un precio en la captación', 'containers', '03-captacion.json', (d) => { widgets(seccionDe(d, '03-captacion.json', 'recordatorio')).find((w) => w.widgetType === 'text-editor').settings.editor = '<p>Valor: $497</p>'; }, /precio fuera de la oferta/);
roto('un bonus con su valor', 'sections', completa, (d) => { widgets(seccionDe(d, completa, 'bonus')).find((w) => /Incluido/.test(w.settings.editor || '')).settings.editor = '<p>Valor: <del>[valor del bonus]</del> · Incluido</p>'; }, /bonus con precio o valor/);
roto('una barra de cierre que no es fija', 'containers', completa, (d) => { delete seccionDe(d, completa, 'barra-de-cierre')[0].settings.sticky; }, /la barra de cierre no es fija arriba/);
roto('una barra de cierre fija sólo en escritorio', 'sections', completa, (d) => { seccionDe(d, completa, 'barra-de-cierre')[0].settings.sticky_on = ['desktop']; }, /la barra de cierre no es fija arriba/);
roto('una barra de cierre sin el logo del evento', 'sections', completa, (d) => { seccionDe(d, completa, 'barra-de-cierre')[0].elements[0].elements = []; }, /la barra de cierre no empieza con el logo/);
// Revisión B1, B2 y M2, en el manifiesto: el formulario y el último enlace a WhatsApp no se quitan, y un bloque con
// precio en la plantilla deja llenarlo.
const rotoManifest = (nombre, cambio, esperado) => {
  const dir = mkdtempSync(join(tmpdir(), 'digi-sales-kit-'));
  try {
    cpSync(templates, join(dir, 'templates'), { recursive: true });
    cpSync(join(templates, '..', 'guide.md'), join(dir, 'guide.md'));
    const file = join(dir, 'templates', 'manifest.json');
    const data = JSON.parse(readFileSync(file, 'utf8'));
    cambio(data);
    writeFileSync(file, JSON.stringify(data));
    const out = py([join(kit, 'kit_validar.py'), join(dir, 'templates'), join(root, 'digitalisimo-elements')]);
    ok(out.status === 1 && esperado.test(out.stdout), `el validador debía fallar con ${nombre} (${esperado}):\n${out.stdout.slice(-1500)}`);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
};
const bloqueM = (d, id, clave) => d.templates.find((x) => x.id === id).blocks.find((b) => b.key === clave);
rotoManifest('el formulario de la captación que se puede quitar', (d) => { Object.assign(bloqueM(d, 'captacion', 'registro'), { required: false, required_for: [] }); }, /registro lleva \['formulario'\] y omit_blocks lo dejaría quitar/);
rotoManifest('el formulario del carrito sin su motivo', (d) => { bloqueM(d, 'carrito', 'formulario').required_for = ['oferta', 'garantia']; }, /formulario lleva \['formulario'\]/);
rotoManifest('la 04 sin ningún enlace a WhatsApp que no se quite', (d) => { Object.assign(bloqueM(d, 'gracias-registro', 'mientras-tanto'), { required: false, required_for: [] }); }, /sin ningún enlace a WhatsApp/);
rotoManifest('un bloque de WhatsApp sin enlace a WhatsApp', (d) => { Object.assign(bloqueM(d, 'gracias-registro', 'tres-pasos'), { required: true, required_for: ['whatsapp'] }); }, /se declara de WhatsApp y no lleva ningún enlace/);
rotoManifest('la oferta donde create-page no deja poner el precio', (d) => { bloqueM(d, 'venta-corta', 'oferta').price_values = false; }, /create-page no dejaría llenarlo/);
// Los iconos de redes (decisión 2): con su logo, sólo en la 04 y la 07 y sin enlazar a las redes.
const compra = '07-gracias-por-tu-compra.json';
const iconos = (d) => widgets(d.content).find((w) => w.widgetType === 'social-icons');
roto('iconos de redes en otra plantilla', 'sections', corta, (d) => { const fuente = JSON.parse(readFileSync(join(templates, 'sections', compra), 'utf8')); widgets(seccionDe(d, corta, 'beneficios'))[0].settings.title = 'Síguenos'; seccionDe(d, corta, 'beneficios')[0].elements[0].elements.push({ ...iconos(fuente), id: 'abc1234' }); }, /iconos de redes donde no van/);
roto('un icono de red que enlaza a la red', 'containers', compra, (d) => { iconos(d).settings.social_icon_list[0].link.url = 'https://facebook.com/marca'; }, /el icono fab fa-facebook-f enlaza a/);
roto('el icono de WhatsApp a otro número que el del sitio', 'sections', '04-gracias-por-registrarte.json', (d) => { iconos(d).settings.social_icon_list[4].link.url = '#[enlace de tu perfil]'; }, /el icono fab fa-whatsapp enlaza a/);
roto('un icono de perfil de muestra que abre otra pestaña', 'containers', compra, (d) => { iconos(d).settings.social_icon_list[2].link.is_external = 'on'; }, /abre otra pestaña/);
roto('el icono de otra red', 'sections', '04-gracias-por-registrarte.json', (d) => { iconos(d).settings.social_icon_list[1].social_icon.value = 'fab fa-linkedin'; }, /iconos de redes distintos de los del kit|«LinkedIn» sin excepción/);
roto('el nombre de una red fuera de los iconos', 'containers', compra, (d) => { widgets(d.content).find((w) => w.widgetType === 'text-editor').settings.editor = '<p>Síguenos en Instagram</p>'; }, /«Instagram» sin excepción declarada/);
// Revisión M1: la barra lleva el margen de las anclas y mide lo mismo en las dos variantes.
roto('una barra de cierre sin el margen de las anclas', 'containers', completa, (d) => { delete seccionDe(d, completa, 'barra-de-cierre')[0].settings.sticky_anchor_link_offset; }, /sin el margen de las anclas/);
roto('una barra de cierre con un margen de anclas menor que ella', 'sections', completa, (d) => { seccionDe(d, completa, 'barra-de-cierre')[0].settings.sticky_anchor_link_offset = 40; }, /sin el margen de las anclas/);
roto('una barra de cierre con la separación de columnas', 'sections', completa, (d) => { seccionDe(d, completa, 'barra-de-cierre')[0].settings.gap = 'extended'; }, /no mide lo mismo con secciones que con contenedores/);
roto('una barra de cierre con relleno arriba en un contenedor hijo', 'containers', completa, (d) => { seccionDe(d, completa, 'barra-de-cierre')[0].elements[1].settings.padding.top = '15'; }, /no mide lo mismo con secciones que con contenedores/);
roto('una barra de cierre que no se acomoda en celular', 'containers', completa, (d) => { delete seccionDe(d, completa, 'barra-de-cierre')[0].settings.flex_wrap_mobile; }, /no se apila en celular/);

// 3. Las marcas que quedan viven en las constantes del generador (una línea cada una).
const lib = readFileSync(join(kit, 'kit_lib.py'), 'utf8');
const paginas = readFileSync(join(kit, 'kit_paginas.py'), 'utf8');
const marcas = JSON.parse(readFileSync(join(root, 'tests/playground/brand-audit/marcas.json'), 'utf8'));
const conExcepcion = new Set(marcas.excepciones.flatMap((e) => e.nombres));
for (const n of marcas.nombres) {
  const re = new RegExp(n.patron);
  if (n.nombre === 'WhatsApp') continue; // llamado a la acción en todo el kit (excepción declarada propia)
  const enPaginas = paginas.split('\n').filter((l) => re.test(l) && !/^\s*#/.test(l));
  ok(enPaginas.length === 0, `kit_paginas.py nombra «${n.nombre}» fuera de una constante: ${enPaginas.join(' | ')}`);
  const enLib = lib.split('\n').map((l) => l.replace(/youtube_url/g, '')).filter((l) => re.test(l) && !/^\s*#/.test(l) && !/^\s*"""|^[A-Z_]+ = |^\s+"[^"]*"\)?$/.test(l));
  ok(enLib.length === 0, `kit_lib.py nombra «${n.nombre}» fuera de sus constantes: ${enLib.join(' | ')}`);
  if (!conExcepcion.has(n.nombre)) {
    const constantes = lib.split('\n').filter((l) => /^[A-Z_]+ = /.test(l) && re.test(l));
    ok(constantes.length === 0, `«${n.nombre}» no tiene excepción y aun así está en una constante: ${constantes.join(' | ')}`);
  }
}
// La lista vigilada incluye estas marcas (cada una con un ejemplo que su patrón reconoce) y ninguna excepción abre el
// módulo entero: cada una se acota a un campo (o la aplica otra prueba, `comprueba`). WhatsApp es excepción declarada
// del operador (2026-11-13, decisión 1), acotada a sus frases como las demás.
const vigiladas = { Visa: 'Paga con Visa', Mastercard: 'Mastercard', 'American Express': 'American Express', Clip: 'terminal Clip', Kueski: 'Kueski Pay', Coppel: 'en Coppel', Hotmart: 'Hotmart', Discord: 'nuestro Discord', Telegram: 'Telegram', Instagram: 'Instagram', Facebook: 'Facebook', TikTok: 'TikTok', 'X (Twitter)': 'x.com/marca', LinkedIn: 'LinkedIn', Spotify: 'Spotify', Calendly: 'Calendly', Shopify: 'Shopify', Amazon: 'Amazon' };
for (const [nombre, ejemplo] of Object.entries(vigiladas)) {
  const n = marcas.nombres.find((x) => x.nombre === nombre);
  ok(n && new RegExp(n.patron).test(ejemplo), `marcas.json vigila «${nombre}» (y su patrón reconoce «${ejemplo}»)`);
}
for (const ex of marcas.excepciones) ok(ex.campo || ex.comprueba, `marcas.json: la excepción de ${ex.nombres.join('/')} en ${ex.archivos} no se acota a un campo`);
const exWhatsApp = marcas.excepciones.filter((e) => e.nombres.includes('WhatsApp'));
ok(exWhatsApp.length > 0 && exWhatsApp.every((e) => e.frases && /Excepción explícita del operador, 2026-11-13/.test(e.decision) && !/pendiente/i.test(e.decision) && !/^\^digitalisimo-elements\/modules\/digitalisimo-sales\/\$?$/.test(e.archivos)), 'WhatsApp: excepción declarada del operador, acotada a sus frases, nunca a todo el módulo');
// Los logos de las redes (decisión 2): sólo el valor del icono del widget de iconos sociales, sólo en la 04 y la 07.
const exRedes = marcas.excepciones.filter((e) => e.nombres.includes('Facebook'));
ok(exRedes.length === 1 && /0\[47\]-/.test(exRedes[0].archivos) && exRedes[0].campo === '^social-icons\\.social_icon_list\\.social_icon\\.value$' && /decisión 2/.test(exRedes[0].decision) && ['Facebook', 'Instagram', 'TikTok', 'YouTube', 'WhatsApp'].every((n) => exRedes[0].nombres.includes(n)), 'marcas.json: los logos de las redes, sólo en el icono del widget de iconos sociales de la 04 y la 07');
const agents = readFileSync(join(root, 'AGENTS.md'), 'utf8');
ok(/WhatsApp en el llamado a la acción/.test(agents) && /logos de las redes/.test(agents) && !/pendiente de confirmar/i.test(agents), 'AGENTS.md dice que WhatsApp y los logos de las redes son excepciones declaradas del operador');
for (const nombre of ['MEDIOS_PAGO', 'MEDIOS_PAGO_FAQ', 'PASARELA', 'PAGO_EFECTIVO', 'AVISO_PLATAFORMAS', 'PLATAFORMA_SESION', 'VIDEO_URL']) {
  ok(new RegExp(`^${nombre} = `, 'm').test(lib) && lib.match(new RegExp(`^${nombre} = `, 'gm')).length === 1, `kit_lib.py define ${nombre} una sola vez`);
}

// 4. El manifiesto y los archivos coinciden.
ok(manifest.templates.length === 8 && manifest.guide_source.commit === '06ff42f' && /v4/.test(manifest.kit_source) && manifest.kit_version === '4-generico', 'manifiesto: ocho plantillas del kit v4 genérico y la guía del cerebro en 06ff42f (con la v4 de la copia de trabajo)');
// La versión con la persona de DIGITALÍSIMO es sólo para los sitios propios: ni en el generador del repositorio ni en el módulo.
ok(!/Daniel|Butanda|PERSONA_DIGITALISIMO/.test(lib + paginas), 'el generador del repositorio sólo trae la persona genérica');
const modulo = join(templates, '..');
const textos = [join(modulo, 'guide.md'), join(templates, 'manifest.json'), ...['sections', 'containers'].flatMap((v) => readdirSync(join(templates, v)).map((f) => join(templates, v, f)))];
for (const f of textos) ok(!/Daniel|Butanda/i.test(readFileSync(f, 'utf8')), `«Daniel» en ${f.slice(root.length)}`);
ok(textos.length === 18, 'se revisan las 16 plantillas, el manifiesto y la guía');
for (const variante of ['sections', 'containers']) {
  ok(JSON.stringify(readdirSync(join(templates, variante)).sort()) === JSON.stringify(manifest.templates.map((t) => t.file).sort()), `${variante}/: exactamente los archivos del manifiesto`);
}

// 5. El ensayo de Playground (tests/playground/sales-kit/run.mjs) no caduca: la fecha de cierre se calcula desde hoy y
//    las fechas escritas a mano son sólo de las que ya pasaron (para probar que se rechazan).
const ensayo = readFileSync(join(root, 'tests/playground/sales-kit/run.mjs'), 'utf8');
ok(/^const CIERRE = .*Date\.now\(\)/m.test(ensayo), 'run.mjs calcula la fecha de cierre desde hoy (const CIERRE con Date.now())');
const fijas = [...ensayo.matchAll(/'(\d{4})-\d{2}-\d{2} \d{2}:\d{2}'/g)].filter((m) => Number(m[1]) >= 2026).map((m) => m[0]);
ok(fijas.length === 0, `run.mjs lleva fechas de cierre fijas que caducan: ${fijas.join(', ')}`);

console.log(`Kit de ventas: generador, validador y marcas en verde (${checks} comprobaciones).`);
