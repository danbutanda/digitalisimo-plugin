// F7b, frente J · `modules/digitalisimo-f7b/assets/js/forms-conditions.js` con `vm` y el DOM mínimo de
// `tests/elements-woo-cd/dom.mjs` (V.9, C2-12, C2-23, V.13, V.14):
// - el archivo: compila, ≤ 3 KB (objetivo 2,5 KB), sin jQuery, sin URLs, `toLocaleLowerCase('und')`;
// - los vectores compartidos de `tests/fixtures/f7b-form-rules.json` (los mismos que `Rules` en PHP), con las funciones
//   internas del script expuestas sólo en la prueba, y también a través del DOM;
// - el marcado real del Formulario de PRO (`tests/fixtures/f7b-forms.html`, que genera el Formulario real): estado al
//   arrancar, `hidden` + `disabled` en todos los controles del grupo oculto, `required` y las clases de obligatorio
//   guardados en `data-digi-required` y restaurados exactamente (en cada radio, como los pinta PRO), reevaluación en
//   `input`/`change`, tras el reinicio del formulario (después del `reset`), cascada con campos ocultos, reglas rotas que
//   fallan cerradas, y el arranque en `elementor/frontend/init` y `frontend/element_ready/form.default` sin duplicar nada.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';
import { createDOM } from './elements-woo-cd/dom.mjs';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/forms-conditions.js', import.meta.url);
const source = readFileSync(file, 'utf8');
const size = statSync(file).size;
const vectors = JSON.parse(readFileSync(new URL('./fixtures/f7b-form-rules.json', import.meta.url), 'utf8'));
const page = readFileSync(new URL('./fixtures/f7b-forms.html', import.meta.url), 'utf8');
const FORM = page.slice(page.indexOf('<form'), page.indexOf('</form>') + 7);
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));
let checks = 0;
const ok = (value, message) => { checks++; assert.ok(value, message); };

// ---------------------------------------------------------------- El archivo.
assert.doesNotThrow(() => new vm.Script(source, { filename: 'forms-conditions.js' }), 'El script compila.');
ok(size <= 3072, `forms-conditions.js cabe en 3 KB (V.13): ${size} B.`);
ok(size <= 2560, `forms-conditions.js cumple el objetivo de 2,5 KB: ${size} B.`);
ok(!/jQuery|\$\(/.test(source), 'Sin jQuery.');
ok(!/https?:\/\//.test(source), 'Sin URLs.');
ok(!/innerHTML|eval\(|new Function/.test(source), 'Sin innerHTML ni código dinámico.');
ok(source.includes("toLocaleLowerCase('und')") && source.includes('.trim()'), 'La normalización de V.9: trim y toLocaleLowerCase("und").');
ok(source.includes('frontend/element_ready/form.default') && source.includes('elementor/frontend/init'), 'Arranca en elementor/frontend/init y en frontend/element_ready/form.default (C2-23).');
ok(FORM.startsWith('<form class="elementor-form"') && FORM.includes('data-digi-cond'), 'La fixture tiene el formulario real con reglas.');

// ---------------------------------------------------------------- Arranque en un DOM.
// `expose`: la prueba añade al final del script una línea que publica sus funciones internas (sólo aquí).
function boot({ html = FORM, elementor = null, expose = false } = {}) {
	const dom = createDOM();
	const { window: w, document: d } = dom;
	d.body.innerHTML = html;
	Object.assign(w, { console, setTimeout, clearTimeout, Promise, JSON, Date, Array, Object, String, parseFloat });
	if (elementor) w.elementorFrontend = elementor;
	vm.createContext(w);
	let code = source;
	if (expose) {
		assert.ok(/\nscan\(d\);hook\(\);\n/.test(code), 'El script termina con scan(d);hook(); (la prueba engancha ahí la exposición).');
		code = code.replace(/\nscan\(d\);hook\(\);\n/, '\nw.__digiCond={T,N,num,solve};scan(d);hook();\n');
	}
	vm.runInContext(code, w);
	const group = (id) => d.querySelector('.elementor-field-group-' + id);
	const controls = (id) => group(id).querySelectorAll('input,select,textarea');
	return { w, d, dom, form: d.querySelector('form'), group, controls };
}
const isHidden = (g) => g.hidden === true && g.hasAttribute('hidden');
const fire = (w, el, type) => el.dispatchEvent(new w.Event(type, { bubbles: true }));
const setValue = (t, id, value, type = 'input') => { const el = t.d.querySelector(`[name="form_fields[${id}]"]`); el.value = value; fire(t.w, el, type); };
const choose = (t, id, value, on = true) => {
	const el = [...t.d.querySelectorAll('input')].find((e) => (e.name === `form_fields[${id}]` || e.name === `form_fields[${id}][]`) && e.value === value);
	el.checked = on;
	fire(t.w, el, 'change');
};

// ---------------------------------------------------------------- Vectores compartidos (funciones internas).
{
	const t = boot({ expose: true });
	const { T, N, num, solve } = t.w.__digiCond;
	const list = (v) => [].concat(v == null ? [] : v).map(N).filter((x) => x !== '');
	for (const [i, c] of vectors.normalize.entries()) ok(JSON.stringify(list(c.in)) === JSON.stringify(c.out), `normalize #${i}: ${JSON.stringify(c.in)} → ${JSON.stringify(list(c.in))}`);
	for (const [i, c] of vectors.number.entries()) {
		const got = num(c.in);
		ok(c.out === null ? got === null : (got !== null && Math.abs(got - c.out) < 1e-12), `number #${i}: ${c.in} → ${got}`);
	}
	for (const [i, c] of vectors.rules.entries()) ok(T(c.rule, list(c.value)) === c.expect, `rules #${i}: ${JSON.stringify(c)}`);
	for (const c of vectors.forms) {
		const V = Object.create(null);
		for (const [k, v] of Object.entries(c.values)) V[k] = list(v);
		const h = solve(c.defs.map((d) => ({ i: d.i, l: d.l, r: d.r })), V);
		const got = Object.keys(h).filter((k) => h[k]).sort();
		ok(JSON.stringify(got) === JSON.stringify(c.hidden), `forms «${c.name}»: ${JSON.stringify(got)}`);
	}
}

// ---------------------------------------------------------------- Vectores a través del DOM (valores leídos de los controles).
function ruleForm(rule, value) {
	const values = value == null ? [] : [].concat(value);
	const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
	const field = Array.isArray(value)
		? values.map((v, i) => `<input type="checkbox" name="form_fields[f][]" value="${esc(v)}" checked="checked" id="c${i}">`).join('')
		: `<input type="text" name="form_fields[f]" value="${esc(values[0] ?? '')}">`;
	const cond = esc(JSON.stringify({ i: 'g', l: 'all', r: [rule] }));
	return `<form class="elementor-form"><div class="elementor-field-group elementor-column elementor-field-group-f">${field}</div><div class="elementor-field-group elementor-column elementor-field-group-g" data-digi-cond="${cond}"><input type="text" name="form_fields[g]"></div></form>`;
}
for (const [i, c] of vectors.rules.entries()) {
	if (c.rule[0] !== 'f' && c.rule[0] !== '') continue;
	const t = boot({ html: ruleForm(c.rule, c.value) });
	ok(isHidden(t.group('g')) === !c.expect, `rules #${i} en el DOM: ${JSON.stringify(c)}`);
}

// ---------------------------------------------------------------- Marcado real: estado al arrancar.
{
	const t = boot();
	const hidden = ['rfc', 'plan', 'nota', 'stack', 'urgente', 'comentario'];
	for (const id of hidden) {
		const g = t.group(id);
		ok(isHidden(g), `${id} oculto al arrancar (como lo pintó el servidor).`);
		for (const c of t.controls(id)) {
			ok(c.disabled === true, `${id}: ${c.name} disabled (no se envía, no se valida, sin foco).`);
			ok(c.required === false, `${id}: ${c.name} sin required mientras está oculto.`);
		}
		if (id !== 'nota') {
			ok(g.getAttribute('data-digi-required') === 'elementor-field-required elementor-mark-required', `${id}: las clases de obligatorio guardadas en data-digi-required.`);
			ok(!g.classList.contains('elementor-field-required') && !g.classList.contains('elementor-mark-required'), `${id}: sin el asterisco mientras está oculto.`);
		}
	}
	ok([...t.controls('plan')].every((c) => c.hasAttribute('data-digi-required')), 'Cada radio de plan (PRO pone required en todos, form-base.php:180-182) queda marcado.');
	ok(!isHidden(t.group('estado')) && t.controls('estado')[0].required && !t.controls('estado')[0].disabled && t.group('estado').classList.contains('elementor-field-required'), 'estado visible al arrancar (pais = mx por defecto), obligatorio y activo.');
	ok(!t.group('estado').hasAttribute('data-digi-required'), 'Un grupo visible no guarda nada.');
	ok(t.form.hasAttribute('data-digi-cond-ready'), 'El formulario queda iniciado.');
	ok((t.form.listeners.input || []).length === 1 && (t.form.listeners.change || []).length === 1 && (t.form.listeners.reset || []).length === 1, 'Un oyente de input, change y reset.');
	ok(!t.group('tipo').hasAttribute('data-digi-required') && t.controls('tipo')[0].required, 'Los campos sin reglas no se tocan.');

	// tipo = empresa.
	choose(t, 'tipo', 'empresa');
	ok(!isHidden(t.group('rfc')), 'tipo = empresa: rfc visible (regla «es igual a Empresa», sin distinguir mayúsculas).');
	ok(t.controls('rfc')[0].required === true && t.controls('rfc')[0].disabled === false && !t.controls('rfc')[0].hasAttribute('data-digi-required'), 'rfc: required restaurado y activo.');
	ok(t.group('rfc').classList.contains('elementor-field-required') && t.group('rfc').classList.contains('elementor-mark-required') && !t.group('rfc').hasAttribute('data-digi-required'), 'rfc: clases de obligatorio restauradas.');
	ok(isHidden(t.group('plan')) && !isHidden(t.group('nota')) && !isHidden(t.group('comentario')), 'plan sigue oculto; nota («no está vacío») y comentario («alguna»: empresa) visibles.');
	ok(t.controls('nota').length === 0, 'Un campo HTML no tiene controles.');

	// tipo = persona.
	choose(t, 'tipo', 'persona');
	ok(isHidden(t.group('rfc')) && t.controls('rfc')[0].disabled && !t.controls('rfc')[0].required && t.controls('rfc')[0].hasAttribute('data-digi-required'), 'tipo = persona: rfc vuelve a ocultarse, deshabilitado y sin required.');
	const plan = [...t.controls('plan')];
	ok(!isHidden(t.group('plan')) && plan.length === 2 && plan.every((c) => c.required && !c.disabled && !c.hasAttribute('data-digi-required')), 'plan visible: required restaurado en cada radio, exactamente como lo pintó PRO.');
	ok(isHidden(t.group('comentario')), 'comentario oculto: ni empresa ni presupuesto > 5000.');

	// Casillas múltiples.
	choose(t, 'intereses', 'codigo');
	ok(!isHidden(t.group('stack')) && t.controls('stack')[0].required, 'Casillas múltiples: «contiene codigo» muestra stack, con su select obligatorio.');
	choose(t, 'intereses', 'diseno');
	ok(!isHidden(t.group('stack')), 'Otra casilla más: sigue visible.');
	choose(t, 'intereses', 'codigo', false);
	ok(isHidden(t.group('stack')) && t.controls('stack')[0].disabled, 'Sin «codigo»: oculto otra vez.');

	// Números con coma o punto; lo no numérico nunca cumple.
	setValue(t, 'presupuesto', '1500');
	ok(!isHidden(t.group('urgente')) && t.controls('urgente')[0].required, 'presupuesto 1500 > 1000: urgente (aceptación obligatoria) visible.');
	setValue(t, 'presupuesto', '999,99');
	ok(isHidden(t.group('urgente')), '999,99: oculto.');
	setValue(t, 'presupuesto', ' 1000,5 ');
	ok(!isHidden(t.group('urgente')), '1000,5 con espacios: visible.');
	setValue(t, 'presupuesto', 'mil');
	ok(isHidden(t.group('urgente')), 'Lo no numérico nunca cumple «mayor que».');
	setValue(t, 'presupuesto', '6000', 'change');
	ok(!isHidden(t.group('comentario')), 'comentario («alguna»): presupuesto > 5000 basta, también en change.');

	// select.
	const pais = t.d.querySelector('[name="form_fields[pais]"]');
	pais.value = 'cl';
	fire(t.w, pais, 'change');
	ok(isHidden(t.group('estado')) && t.controls('estado')[0].disabled && !t.controls('estado')[0].required, 'pais = cl: estado oculto.');
	pais.value = 'mx';
	fire(t.w, pais, 'change');
	ok(!isHidden(t.group('estado')) && t.controls('estado')[0].required, 'pais = mx: estado visible y obligatorio otra vez.');

	// Reinicio de PRO tras un envío correcto: se reevalúa después de que el navegador restablezca los valores.
	t.d.querySelectorAll('input').forEach((e) => { if (e.type === 'radio' || e.type === 'checkbox') e.checked = false; });
	const reset = new t.w.Event('reset', { bubbles: true });
	t.form.dispatchEvent(reset);
	ok(!isHidden(t.group('plan')), 'Durante el evento reset todavía no se reevalúa (los valores aún no se restablecieron).');
	await tick();
	ok(isHidden(t.group('plan')) && isHidden(t.group('nota')), 'Tras el reset, el estado vuelve al inicial.');
}

// ---------------------------------------------------------------- Cascada: un campo oculto cuenta como vacío.
{
	const cond = (o) => JSON.stringify(o).replace(/"/g, '&quot;');
	const html = '<form class="elementor-form">'
		+ '<div class="elementor-field-group elementor-field-group-a"><input type="text" name="form_fields[a]" value="x"></div>'
		+ `<div class="elementor-field-group elementor-field-group-c" data-digi-cond="${cond({ i: 'c', l: 'all', r: [['b', 'not_empty', '']] })}"><input type="text" name="form_fields[c]"></div>`
		+ `<div class="elementor-field-group elementor-field-group-b" data-digi-cond="${cond({ i: 'b', l: 'all', r: [['a', 'is', 'x']] })}"><input type="text" name="form_fields[b]"></div>`
		+ '</form>';
	const t = boot({ html });
	ok(!isHidden(t.group('b')) && isHidden(t.group('c')), 'b visible (a = x); c («b no está vacío», regla hacia un campo posterior) oculto con b vacío.');
	setValue(t, 'b', 'algo');
	ok(!isHidden(t.group('c')), 'b con valor: c visible.');
	setValue(t, 'a', 'y');
	ok(isHidden(t.group('b')) && isHidden(t.group('c')), 'a = y: b se oculta y, aunque su control conserva «algo», cuenta como vacío: c también (como Rules::hidden()).');
	ok(t.d.querySelector('[name="form_fields[b]"]').value === 'algo', 'El valor del campo oculto no se borra (vuelve si se muestra).');
	setValue(t, 'a', 'X ');
	ok(!isHidden(t.group('b')) && !isHidden(t.group('c')), 'a = «X »: los dos vuelven.');
}

// ---------------------------------------------------------------- Reglas rotas: fallan cerradas, sin excepciones.
{
	const html = '<form class="elementor-form"><div class="elementor-field-group elementor-field-group-a"><input type="text" name="form_fields[a]" value="x"></div>'
		+ '<div class="elementor-field-group elementor-field-group-r" data-digi-cond="{no es json"><input type="text" name="form_fields[r]" required="required"></div>'
		+ '<div class="elementor-field-group elementor-field-group-s" data-digi-cond="{&quot;i&quot;:&quot;s&quot;,&quot;l&quot;:&quot;all&quot;,&quot;r&quot;:[[&quot;a&quot;,&quot;between&quot;,&quot;x&quot;]]}"><input type="text" name="form_fields[s]"></div>'
		+ '<div class="elementor-field-group elementor-field-group-u" data-digi-cond="{&quot;i&quot;:&quot;u&quot;,&quot;l&quot;:&quot;all&quot;,&quot;r&quot;:[5,&quot;x&quot;]}"><input type="text" name="form_fields[u]"></div>'
		+ '<div class="elementor-field-group elementor-field-group-v" data-digi-cond="{&quot;i&quot;:&quot;v&quot;,&quot;l&quot;:&quot;any&quot;,&quot;r&quot;:[[&quot;a&quot;,&quot;is&quot;,&quot;x&quot;],[&quot;&quot;,&quot;&quot;,&quot;&quot;]]}"><input type="text" name="form_fields[v]"></div>'
		+ '</form><form id="otro"><input type="text" name="form_fields[z]" required="required"></form>';
	let t;
	assert.doesNotThrow(() => { t = boot({ html }); }, 'Un data-digi-cond roto no lanza.');
	ok(isHidden(t.group('r')) && t.controls('r')[0].disabled, 'JSON roto: oculto (falla cerrado).');
	ok(isHidden(t.group('s')), 'Operador desconocido: nunca se cumple.');
	ok(isHidden(t.group('u')), 'Reglas que no son listas: nunca se cumplen.');
	ok(!isHidden(t.group('v')), '«alguna» con una regla válida que se cumple: visible.');
	const other = t.d.getElementById('otro');
	ok(!other.hasAttribute('data-digi-cond-ready') && other.querySelector('input').required, 'Un formulario sin reglas no se toca.');
}

// ---------------------------------------------------------------- Arranque: elementor/frontend/init y element_ready.
{
	const actions = [];
	const t = boot({ html: '<div id="caja"></div>' });
	ok(!('elementorFrontend' in t.w), 'Sin el constructor al cargar, el script espera su evento.');
	t.w.elementorFrontend = { hooks: { addAction: (name, fn) => actions.push([name, fn]) } };
	t.w.dispatchEvent(new t.w.CustomEvent('elementor/frontend/init'));
	ok(actions.length === 1 && actions[0][0] === 'frontend/element_ready/form.default', 'En elementor/frontend/init se engancha a frontend/element_ready/form.default.');
	t.w.dispatchEvent(new t.w.CustomEvent('elementor/frontend/init'));
	ok(actions.length === 1, 'Un segundo init no duplica el gancho.');
	const box = t.d.getElementById('caja');
	box.innerHTML = FORM; // un popup o un bucle que llega después
	const form = box.querySelector('form');
	ok(!form.hasAttribute('data-digi-cond-ready'), 'El formulario que llega después aún no está iniciado.');
	actions[0][1]([box]);
	ok(form.hasAttribute('data-digi-cond-ready') && box.querySelector('.elementor-field-group-rfc').querySelector('input').required === false, 'element_ready (con el $scope del constructor) inicia el formulario nuevo.');
	actions[0][1]([box]);
	actions[0][1](box);
	ok((form.listeners.input || []).length === 1, 'Llamarlo otra vez no duplica los oyentes.');
}
{
	const actions = [];
	const t = boot({ elementor: { hooks: { addAction: (name, fn) => actions.push([name, fn]) } } });
	ok(actions.length === 1, 'Con el constructor ya iniciado, se engancha al cargar.');
	t.w.dispatchEvent(new t.w.CustomEvent('elementor/frontend/init'));
	ok(actions.length === 1 && (t.form.listeners.input || []).length === 1, 'Y el init posterior no duplica nada.');
}

// ---------------------------------------------------------------- Avisos del editor (forms-conditions-editor.js).
{
	const editorFile = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/forms/assets/forms-conditions-editor.js', import.meta.url);
	const editorSource = readFileSync(editorFile, 'utf8');
	ok(statSync(editorFile).size <= 4096 && !/https?:\/\//.test(editorSource) && !/ajax|fetch\(|XMLHttpRequest/i.test(editorSource), 'El script del editor es pequeño, sin URLs ni peticiones.');
	const added = [];
	const w = { addEventListener() {}, elementor: { hooks: { addAction: (name, fn) => added.push(name) }, channels: { editor: { on() {} } } }, digitalisimoFormConditions: { i18n: { none: 'NINGUNA', invalid: 'SIN-ID', missing: 'FALTA %s', self: 'MISMO', later: 'DESPUES %s' } } };
	w.window = w;
	vm.createContext(w);
	vm.runInContext(editorSource, w);
	ok(added.length === 1 && added[0] === 'panel/open_editor/widget', 'Se engancha al abrir el panel de un widget.');
	const { warnings } = w.digitalisimoFormConditionsEditor;
	const rows = [
		{ custom_id: 'a' },
		{ custom_id: 'b', digitalisimo_conditional: 'yes', digitalisimo_rules: [{ field: 'a', operator: 'is' }, { field: 'c', operator: 'is' }, { field: 'b' }, { field: 'zz' }, { field: '' }, { field: 'c' }] },
		{ custom_id: 'c', digitalisimo_conditional: 'yes', digitalisimo_rules: [] },
		{ custom_id: 'd', digitalisimo_rules: [{ field: 'zz' }] },
	];
	ok(JSON.stringify(warnings(rows, 1)) === JSON.stringify(['DESPUES c', 'MISMO', 'FALTA zz', 'SIN-ID']), 'Avisos: campo posterior (C2-12 e), el propio campo, un ID que no existe, una regla sin ID; sin repetir: ' + JSON.stringify(warnings(rows, 1)));
	ok(JSON.stringify(warnings(rows, 2)) === JSON.stringify(['NINGUNA']), 'Sin reglas: el campo no se mostrará.');
	ok(warnings(rows, 0).length === 0 && warnings(rows, 3).length === 0, 'Sin el interruptor, ningún aviso.');
}

console.log(`DIGITALÍSIMO Elements F7b · forms-conditions.js (${size} B): ${checks} comprobaciones en verde (vectores, marcado real, cascada, reglas rotas y arranque).`);
