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
roto('OXXO fuera de su lugar', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading').settings.title = 'Paga en OXXO'; }, /«OXXO» sin excepción declarada/);
roto('un enlace a otro sitio', 'sections', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'button').settings.link.url = 'https://otro.test/'; }, /enlace inesperado/);
roto('un video de otro servicio', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'video').settings.youtube_url = 'https://www.youtube.com/watch?v=abcdefghijk'; }, /video con un enlace|YouTube/);
roto('un id repetido', 'sections', corta, (d) => { d.content[1].id = d.content[0].id; }, /id repetido/);
roto('un widget que no existe', 'containers', corta, (d) => { widgets(d.content)[0].widgetType = 'no-existe'; }, /no existe en Elements/);
roto('variantes distintas', 'containers', corta, (d) => { widgets(d.content)[0].settings.title = 'Otro texto'; }, /mismos widgets en el mismo orden/);
roto('una fila que se desborda', 'containers', corta, (d) => { const fila = d.content.find((c) => c.settings.flex_direction === 'row'); fila.elements[0].settings.width.size = 90; }, /anchos que suman/);
roto('una fila que no se apila', 'containers', corta, (d) => { delete d.content.find((c) => c.settings.flex_direction === 'row').settings.flex_direction_mobile; }, /no se apila en celular/);
roto('un contador con fecha fija', 'sections', '05-carrito.json', (d) => { widgets(d.content).find((w) => w.widgetType === 'countdown').settings.due_date = '2026-12-31 23:59'; }, /contador con una fecha fija/);
roto('texto de relleno', 'sections', corta, (d) => { widgets(d.content)[0].settings.title = 'Lorem ipsum'; }, /texto prohibido/);
roto('el nombre del proveedor del núcleo', 'sections', corta, (d) => { widgets(d.content)[0].settings.title = 'Hecho con Elementor'; }, /proveedor del núcleo/);
roto('la persona de la versión para los sitios propios', 'containers', corta, (d) => { widgets(d.content).find((w) => w.widgetType === 'heading' && /Hola, soy/.test(w.settings.title)).settings.title = 'Hola, te saluda Daniel Butanda'; }, /texto prohibido «Daniel»/);
roto('una sección fuera de sus bloques', 'sections', corta, (d) => { d.content.splice(1, 0, d.content.splice(3, 1)[0]); }, /bloques del manifiesto no recorren las secciones en orden/);

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
for (const nombre of ['MEDIOS_PAGO', 'MEDIOS_PAGO_FAQ', 'PASARELA', 'PAGO_EFECTIVO', 'AVISO_PLATAFORMAS', 'PLATAFORMA_SESION', 'VIDEO_URL']) {
  ok(new RegExp(`^${nombre} = `, 'm').test(lib) && lib.match(new RegExp(`^${nombre} = `, 'gm')).length === 1, `kit_lib.py define ${nombre} una sola vez`);
}

// 4. El manifiesto y los archivos coinciden.
ok(manifest.templates.length === 8 && manifest.guide_source.commit === '06ff42f' && manifest.kit_version === '3-generico', 'manifiesto: ocho plantillas del kit v3 genérico y la guía del cerebro en 06ff42f');
// La versión con la persona de DIGITALÍSIMO es sólo para los sitios propios: ni en el generador del repositorio ni en el módulo.
ok(!/Daniel|Butanda|PERSONA_DIGITALISIMO/.test(lib + paginas), 'el generador del repositorio sólo trae la persona genérica');
const modulo = join(templates, '..');
const textos = [join(modulo, 'guide.md'), join(templates, 'manifest.json'), ...['sections', 'containers'].flatMap((v) => readdirSync(join(templates, v)).map((f) => join(templates, v, f)))];
for (const f of textos) ok(!/Daniel|Butanda/i.test(readFileSync(f, 'utf8')), `«Daniel» en ${f.slice(root.length)}`);
ok(textos.length === 18, 'se revisan las 16 plantillas, el manifiesto y la guía');
for (const variante of ['sections', 'containers']) {
  ok(JSON.stringify(readdirSync(join(templates, variante)).sort()) === JSON.stringify(manifest.templates.map((t) => t.file).sort()), `${variante}/: exactamente los archivos del manifiesto`);
}

console.log(`Kit de ventas: generador, validador y marcas en verde (${checks} comprobaciones).`);
