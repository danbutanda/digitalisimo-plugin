// F7b, frente J · formulario condicional en Chrome sin interfaz (V.9, C2-2, C2-12 a, V.14).
// Abre `tests/fixtures/f7b-forms.html` (el HTML del Formulario real de PRO que genera tests/elements-f7b-forms/fixture.php,
// con `frontend.min.css` del núcleo, la hoja del formulario, la regla `[hidden]` del CSS del documento y
// `forms-conditions.js`) y comprueba en un navegador de verdad:
// - `getComputedStyle().display === 'none'` en cada grupo oculto, y que sin la regla `[hidden]` el núcleo lo dejaría
//   visible (`.elementor-column{display:flex}`, C2-2);
// - el foco (Tab y `focus()`) nunca entra en un campo oculto;
// - radios (required en cada opción, como PRO), casillas múltiples, `select`, aceptación, área de texto y número;
// - `checkValidity()` no exige los obligatorios ocultos y `FormData` no los envía;
// - el asterisco de obligatorio vuelve con el campo; el reinicio del formulario recupera el estado inicial.
// Sin Chrome (CI), salta con un aviso. Uso: node tests/elements-f7b-forms-chrome.mjs (PUPPETEER_PATH y CHROME_PATH opcionales).
import { existsSync, readdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
function puppeteerDir() {
	if (process.env.PUPPETEER_PATH) return join(process.env.PUPPETEER_PATH, 'puppeteer-core');
	const npx = join(homedir(), '.npm/_npx');
	if (!existsSync(npx)) return '';
	for (const dir of readdirSync(npx)) {
		const candidate = join(npx, dir, 'node_modules/puppeteer-core');
		if (existsSync(join(candidate, 'package.json'))) return candidate;
	}
	return '';
}
const dir = puppeteerDir();
if (!existsSync(chrome) || !dir) {
	console.log('AVISO: sin Chrome o sin puppeteer-core; se omite tests/elements-f7b-forms-chrome.mjs.');
	process.exit(0);
}
const puppeteer = createRequire(import.meta.url)(dir);
const page_url = pathToFileURL(fileURLToPath(new URL('./fixtures/f7b-forms.html', import.meta.url))).href;

let checks = 0;
const ok = (value, message) => {
	checks++;
	if (!value) throw new Error(message);
};

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
try {
	const page = await browser.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	page.on('requestfailed', (r) => errors.push('404 ' + r.url()));
	await page.goto(page_url, { waitUntil: 'load' });
	await page.waitForSelector('form[data-digi-cond-ready]');

	const state = () => page.evaluate(() => {
		const out = {};
		for (const g of document.querySelectorAll('.elementor-field-group')) {
			const id = [...g.classList].find((c) => c.startsWith('elementor-field-group-'))?.slice(22);
			if (!id) continue;
			const controls = [...g.querySelectorAll('input,select,textarea')];
			const label = g.querySelector('.elementor-field-label');
			out[id] = {
				display: getComputedStyle(g).display,
				hidden: g.hidden,
				disabled: controls.map((c) => c.disabled),
				required: controls.map((c) => c.required),
				mark: label ? getComputedStyle(label, '::after').content : null,
			};
		}
		return out;
	});
	const HIDDEN = ['rfc', 'plan', 'nota', 'stack', 'urgente', 'comentario'];

	// Estado al cargar.
	let s = await state();
	for (const id of HIDDEN) {
		ok(s[id].hidden && s[id].display === 'none', `${id}: oculto y con display none al cargar (${s[id].display}).`);
		ok(s[id].disabled.every(Boolean) && s[id].required.every((r) => !r), `${id}: controles disabled y sin required.`);
	}
	for (const id of ['tipo', 'intereses', 'presupuesto', 'pais', 'estado', 'email']) ok(s[id].display === 'flex' && !s[id].hidden, `${id}: visible (${s[id].display}).`);
	ok(s.estado.required[0] && s.estado.mark && s.estado.mark !== 'none', 'estado (visible por el valor por defecto de pais) obligatorio y con asterisco.');

	// C2-2: sin la regla del CSS del documento, `hidden` perdería contra `.elementor-column{display:flex}` del núcleo.
	const without = await page.evaluate(() => {
		const style = document.getElementById('digi-hidden-rule');
		style.disabled = true;
		const display = getComputedStyle(document.querySelector('.elementor-field-group-rfc')).display;
		style.disabled = false;
		return display;
	});
	ok(without !== 'none', `Sin la regla [hidden], el grupo oculto se vería (${without}): la regla es necesaria.`);

	// El foco nunca entra en un campo oculto: ni con Tab ni con focus().
	const focusLeak = await page.evaluate(() => {
		const hidden = [...document.querySelectorAll('[data-digi-cond][hidden] input, [data-digi-cond][hidden] select, [data-digi-cond][hidden] textarea')];
		return hidden.filter((c) => { c.focus(); return document.activeElement === c; }).map((c) => c.name);
	});
	ok(focusLeak.length === 0, 'focus() no llega a ningún control oculto: ' + focusLeak.join(','));
	await page.focus('#form-field-tipo-0');
	const visited = [];
	for (let i = 0; i < 30; i++) {
		await page.keyboard.press('Tab');
		const at = await page.evaluate(() => {
			const a = document.activeElement;
			return a ? { name: a.name || a.tagName, inHidden: !!a.closest('[hidden]') } : null;
		});
		if (!at || at.name === 'BODY') break;
		visited.push(at);
	}
	ok(visited.length > 3 && visited.every((v) => !v.inHidden), 'Con Tab el foco recorre sólo campos visibles: ' + visited.map((v) => v.name).join(' → '));
	ok(!visited.some((v) => /rfc|plan|stack|urgente|comentario/.test(v.name)), 'Ningún campo oculto en el orden de tabulación.');

	// Validación nativa y envío: lo oculto no se exige ni se envía.
	await page.click('#form-field-tipo-0'); // Persona
	s = await state();
	ok(s.plan.display !== 'none' && s.plan.required.length === 2 && s.plan.required.every(Boolean) && s.plan.disabled.every((d) => !d), 'Persona: plan visible; required en cada radio, como lo pinta PRO.');
	ok(s.rfc.display === 'none' && s.nota.display !== 'none', 'Persona: rfc oculto, nota visible.');
	await page.type('#form-field-email', 'ana@example.com');
	await page.type('#form-field-estado', 'Jalisco');
	ok(!(await page.evaluate(() => document.querySelector('form').checkValidity())), 'Sin elegir plan, el formulario no es válido (el radio visible es obligatorio).');
	await page.click('#form-field-plan-1');
	ok(await page.evaluate(() => document.querySelector('form').checkValidity()), 'Con plan elegido es válido aunque rfc, stack, urgente y comentario (obligatorios) estén ocultos y vacíos.');
	const sent = await page.evaluate(() => [...new FormData(document.querySelector('form')).keys()]);
	ok(!sent.some((k) => /\[(rfc|stack|urgente|comentario)\]/.test(k)) && sent.includes('form_fields[plan]') && sent.includes('form_fields[email]'), 'FormData no lleva los campos ocultos: ' + sent.join(','));

	// Empresa: rfc vuelve con su asterisco.
	await page.click('#form-field-tipo-1');
	s = await state();
	ok(s.rfc.display === 'flex' && s.rfc.required[0] && !s.rfc.disabled[0] && s.rfc.mark && s.rfc.mark !== 'none', 'Empresa: rfc visible, obligatorio, activo y con asterisco.');
	ok(s.plan.display === 'none' && s.plan.disabled.every(Boolean) && s.comentario.display === 'flex', 'Empresa: plan oculto otra vez; comentario («alguna») visible.');
	ok(!(await page.evaluate(() => document.querySelector('form').checkValidity())), 'rfc visible y vacío: no es válido.');

	// Casillas múltiples y select obligatorio.
	await page.click('#form-field-intereses-1'); // Código
	s = await state();
	ok(s.stack.display === 'flex' && s.stack.required[0], 'Código: stack visible con su select obligatorio.');
	await page.click('#form-field-intereses-1');
	s = await state();
	ok(s.stack.display === 'none' && s.stack.disabled[0], 'Sin Código: stack oculto.');

	// Número con el teclado: input en cada tecla.
	await page.type('#form-field-presupuesto', '15');
	ok((await state()).urgente.display === 'none', '15 no es mayor que 1000.');
	await page.type('#form-field-presupuesto', '00');
	s = await state();
	ok(s.urgente.display === 'flex' && s.urgente.required[0], '1500 (tecleado): la aceptación obligatoria aparece.');

	// select de un solo valor.
	await page.select('#form-field-pais', 'cl');
	ok((await state()).estado.display === 'none', 'pais = cl: estado oculto.');
	await page.select('#form-field-pais', 'mx');
	ok((await state()).estado.display === 'flex', 'pais = mx: estado visible.');

	// Reinicio del formulario (lo que hace PRO tras un envío correcto).
	await page.evaluate(() => document.querySelector('form').reset());
	await page.waitForFunction(() => document.querySelector('.elementor-field-group-rfc').hidden && document.querySelector('.elementor-field-group-urgente').hidden);
	s = await state();
	for (const id of HIDDEN) ok(s[id].display === 'none', `${id}: oculto otra vez tras reset().`);
	ok(s.estado.display === 'flex', 'estado visible tras reset() (pais vuelve a mx).');

	ok(errors.length === 0, 'Sin errores de JavaScript ni recursos que fallen: ' + errors.join(' | '));
} finally {
	await browser.close();
}
console.log(`DIGITALÍSIMO Elements F7b · formulario condicional en Chrome: ${checks} comprobaciones en verde.`);
