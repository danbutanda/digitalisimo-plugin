// Carrusel anidado (C1, frente N): la plantilla del editor (`content_template()`, construida con las piezas de
// `Engine::open()`) pinta exactamente el marcado del servidor. Ejecuta tests/elements-pro-nested-carousel.php con
// NC_DUMP (plantilla + ajustes + marcado del servidor de 30 casos: 1, 2 y 5 diapositivas × 10 variantes), evalúa la
// plantilla con un compilador como el de `wp.template` (`<# #>`, `{{ }}` escapado, `{{{ }}}` sin escapar) y compara,
// caso por caso, la raíz, los controles y la apertura de la pista.
//
// PHP: `php` del sistema o el de la variable PHP_BIN (p. ej. el php-wasm-cli local; php-wasm sale con 0 aunque falle:
// se juzga por la salida).
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const tmp = mkdtempSync(join(process.env.TMPDIR && !process.env.TMPDIR.startsWith('/tmp') ? process.env.TMPDIR : '/var/tmp', 'nc-editor-'));
const dumpFile = join(tmp, 'dump.json');
const php = (process.env.PHP_BIN || 'php').split(' ');
const out = execFileSync(php[0], [...php.slice(1), join(root, 'tests/elements-pro-nested-carousel.php')], { env: { ...process.env, NC_DUMP: dumpFile }, encoding: 'utf8' });
assert.ok(!/Fatal error|Uncaught|^FALLA/m.test(out), 'La prueba PHP falla:\n' + out);
const dump = JSON.parse(readFileSync(dumpFile, 'utf8'));
rmSync(tmp, { recursive: true, force: true });

// `_` mínimo y compilador de plantillas como wp.template (mismas expresiones regulares).
const escapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;', '`': '&#x60;' };
const _ = {
	isArray: Array.isArray,
	isString: (v) => 'string' === typeof v,
	isObject: (v) => null !== v && ('object' === typeof v || 'function' === typeof v),
	contains: (list, v) => list.includes(v),
	each: (list, fn) => (Array.isArray(list) ? list : Object.values(list || {})).forEach(fn),
	escape: (v) => String(v ?? '').replace(/[&<>"'`]/g, (c) => escapeMap[c]),
};
const compile = (text) => {
	let source = "let __p='';";
	let index = 0;
	const re = /<#([\s\S]+?)#>|\{\{\{([\s\S]+?)\}\}\}|\{\{([^}]+?)\}\}(?!\})/g;
	text.replace(re, (match, evaluate, interpolate, escape, offset) => {
		source += '__p+=' + JSON.stringify(text.slice(index, offset)) + ';';
		if (evaluate) source += evaluate + '\n';
		if (interpolate) source += '__p+=((__t=(' + interpolate + '))==null?"":__t);';
		if (escape) source += '__p+=_.escape(' + escape + ');';
		index = offset + match.length;
		return match;
	});
	source += '__p+=' + JSON.stringify(text.slice(index)) + ';return __p;';
	return new Function('settings', 'view', 'elementor', '_', 'let __t;' + source);
};
// Icono elegido: el mismo HTML que el Icons_Manager simulado de la prueba PHP.
const elementor = { helpers: { renderIcon: (view, icon) => ({ value: '<i class="' + _.escape(icon.value) + '" aria-hidden="true"></i>' }) } };
const render = compile(dump.template);

assert.ok(dump.cases.length >= 30, 'Faltan casos en el volcado');
let checked = 0;
for (const { settings, html } of dump.cases) {
	const editor = render(settings, {}, elementor, _);
	const start = editor.indexOf('<section');
	const end = editor.indexOf('</div></section>');
	assert.ok(start >= 0 && end > start, 'La plantilla no pinta la raíz del carrusel');
	const painted = editor.slice(start, end);
	assert.ok(painted === html, 'Editor y servidor difieren con ' + JSON.stringify({ items: settings.carousel_items.length, autoplay: settings.autoplay, pagination: settings.pagination, arrows: settings.arrows, direction: settings.direction }) + '\n servidor: ' + html + '\n editor:   ' + painted);
	const list = editor.slice(end).match(/<div class="digi-ncarousel__items"[^>]*hidden>((?:<span><\/span>)*)<\/div>/);
	assert.ok(list && list[1].length / 13 === settings.carousel_items.length, 'La lista oculta del repetidor no tiene un nodo por fila');
	checked++;
}
console.log(`DIGITALÍSIMO Elements: la plantilla del editor del Carrusel anidado pinta el marcado del servidor (${checked} casos).`);
