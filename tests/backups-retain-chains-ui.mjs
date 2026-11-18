// «Conservar» de una automatización en la pantalla de Backups (assets/retention.js), con el DOM simulado de
// tests/lib/backups-panel-dom.mjs:
//   - la estimación del navegador es exactamente la del servidor (Digitalisimo_Backups_Chain::estimate(), por
//     tests/backups-retain-chains/estimates.php) en miles de combinaciones: unidad, N, modo, un completo cada M o D días,
//     frecuencia y destino, también con valores raros (vacíos, texto, decimales, fuera de rango);
//   - elegir la unidad, cambiar N, M, D, la frecuencia, el destino o el modo actualiza la estimación al momento; con
//     «Completo cada vez» la unidad y la ayuda de las cadenas se ocultan y se ve «respaldos»;
//   - todo se pinta como texto (nada de HTML) y otros campos no se tocan.
// PHP_BIN cambia el PHP (por defecto, php).
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { runInNewContext } from 'node:vm';
import { El } from './lib/backups-panel-dom.mjs';

const script = readFileSync('digitalisimo-backups/assets/retention.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'La estimación debe pintarse como texto, no como HTML');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. La misma cuenta y el mismo texto que el servidor.
const pure = {};
runInNewContext(script, { window: pure });
ok(typeof pure.digitalisimoBackupsRetention?.estimate === 'function', 'retention.js no expone su estimación sin documento');
const schedules = ['disabled', 'hourly', 'digitalisimo_every_6_hours', 'digitalisimo_every_12_hours', 'daily', 'weekly', 'monthly', 'otra'];
const cases = [];
for (const retain_unit of ['points', 'chains']) for (const retain of [1, 2, 3, 4, 8, 14, 15, 33, 94, 95, 100]) for (const full_every of [1, 2, 6, 30, 60]) for (const full_days of [1, 3, 7, 8, 90]) for (const schedule of schedules) for (const destination of ['local', 'sftp']) {
  cases.push({ mode: 'incremental', retain, retain_unit, full_every, full_days, schedule, destination });
}
for (const retain of [1, 4, 100, 150, 0, -3]) cases.push({ mode: 'full', retain, retain_unit: 'chains', schedule: 'daily' }, { retain });
// Lo que puede llegar de un formulario: texto, vacío, decimales, signos, notación científica y unidades o modos raros.
const odd = ['', 'abc', '2.9', ' 3', '+4', '-2', '1e1', '0', '.5', '5.', '0x1A', '999', null];
for (const value of odd) {
  cases.push({ mode: 'incremental', retain: value, retain_unit: 'chains', full_every: 6, full_days: 7, schedule: 'daily', destination: 'local' });
  cases.push({ mode: 'incremental', retain: 4, retain_unit: 'points', full_every: value, full_days: 7, schedule: 'hourly', destination: 'local' });
  cases.push({ mode: 'incremental', retain: 2, retain_unit: 'chains', full_every: 6, full_days: value, schedule: 'daily', destination: 'sftp' });
}
for (const retain_unit of ['', 'cadenas', null, 'CHAINS']) cases.push({ mode: 'incremental', retain: 3, retain_unit, schedule: 'daily' });
for (const mode of ['', 'Incremental', null, 'full']) cases.push({ mode, retain: 3, retain_unit: 'chains', schedule: 'daily' });
for (const destination of ['', null, 'local', 'ftp-0a1b2c3d']) cases.push({ mode: 'incremental', retain: 2, schedule: 'daily', destination });
const clean = (policy) => Object.fromEntries(Object.entries(policy).filter(([, value]) => value !== null));
const dir = mkdtempSync(join(tmpdir(), 'digi-bk-retain-'));
try {
  writeFileSync(join(dir, 'casos.json'), JSON.stringify(cases.map(clean)));
  const php = spawnSync(process.env.PHP_BIN || 'php', ['tests/backups-retain-chains/estimates.php', join(dir, 'casos.json')], { encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 });
  assert.ok(php.status === 0 && !/Fatal error|Uncaught|Warning|Deprecated/.test(php.stdout + php.stderr), 'El servidor no calculó las estimaciones: ' + (php.stdout + php.stderr).slice(-1500));
  const server = JSON.parse(php.stdout);
  assert.equal(server.length, cases.length);
  let mismatches = 0;
  cases.forEach((policy, i) => {
    const browser = pure.digitalisimoBackupsRetention.estimate(clean(policy));
    if (JSON.stringify(browser) !== JSON.stringify(server[i])) {
      if (mismatches++ < 5) console.error('Distinta:', JSON.stringify(policy), '\n  navegador:', JSON.stringify(browser), '\n  servidor: ', JSON.stringify(server[i]));
    }
  });
  ok(mismatches === 0, `La estimación del navegador difiere de la del servidor en ${mismatches} de ${cases.length} casos`);
  ok(cases.length > 8000, 'Muy pocos casos: ' + cases.length);
} finally {
  rmSync(dir, { recursive: true, force: true });
}

// 2. La pantalla: un formulario de automatización como lo pinta el servidor (mode_fields(), retain_field(),
// retain_estimate()), en «Programación» y en las tarjetas.
const N = (key) => `digitalisimo_backup_automation[${key}]`;
function select(name, values, value, attrs = {}) {
  const el = new El('select', { name, ...attrs });
  el.options = values.map((v) => ({ value: v, text: v }));
  el.value = value;
  return el;
}
function automationForm({ mode = 'full', unit = 'points', retain = '4', every = '6', days = '7', schedule = 'weekly', destination = 'local' } = {}) {
  const form = new El('form', { method: 'post' });
  const fields = {
    schedule: select(N('schedule'), schedules.slice(0, 7), schedule),
    destination: select(N('destination'), ['local', 'sftp'], destination),
    mode: select(N('mode'), ['full', 'incremental'], mode),
    every: Object.assign(new El('input', { type: 'number', name: N('full_every') }), { value: every }),
    days: Object.assign(new El('input', { type: 'number', name: N('full_days') }), { value: days }),
    retain: Object.assign(new El('input', { type: 'number', name: N('retain') }), { value: retain }),
    unit: select(N('retain_unit'), ['points', 'chains'], unit, { 'data-retain-unit': '', class: 'digitalisimo-backups-retain-unit' }),
    noun: new El('span', { 'data-retain-noun': '' }),
    other: Object.assign(new El('input', { type: 'time', name: N('time') }), { value: '03:00' }),
  };
  const estimate = new El('p', { class: 'description digitalisimo-backups-retain-estimate', 'data-retain-estimate': '', 'aria-live': 'polite' });
  const help = new El('div', { 'data-retain-chains-help': '' });
  fields.noun.textContent = 'respaldos';
  if (mode === 'incremental') { fields.noun.hidden = true; } else { fields.unit.hidden = true; help.hidden = true; }
  // Como en el navegador: `name` y `form` son propiedades de cada campo.
  for (const el of Object.values(fields)) { form.appendChild(el); if (el.tagName !== 'SPAN') { el.form = form; el.name = el.getAttribute('name'); } }
  form.appendChild(estimate);
  form.appendChild(help);
  return { form, fields, estimate, help };
}
const listeners = {};
const doc = new El('html');
doc.readyState = 'complete';
doc.addEventListener = (type, listener) => { (listeners[type] ||= []).push(listener); };
const schedule = automationForm({ mode: 'incremental', unit: 'chains', retain: '2', schedule: 'daily' });
const card = automationForm();
doc.appendChild(schedule.form);
doc.appendChild(card.form);
// El servidor ya pintó su texto; el script, al cargar, recalcula (y deja el mismo).
schedule.estimate.textContent = 'texto del servidor';
const context = { document: doc, window: null };
context.window = context;
runInNewContext(script, context);
ok(schedule.estimate.textContent === 'Guardarás entre 8 y 14 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.', 'Al cargar, la estimación de «Programación» no es la de «Conservar 2 cadenas»: ' + schedule.estimate.textContent);
ok(card.estimate.textContent === 'Guardarás 4 respaldos completos.' && card.fields.unit.hidden && !card.fields.noun.hidden && card.help.hidden, 'Una automatización completa no oculta la unidad: ' + card.estimate.textContent);
const fire = (type, target) => { for (const listener of listeners[type] || []) listener({ target }); };
const set = (target, value, type = 'change') => { target.value = value; fire(type, target); return target.form.querySelector('[data-retain-estimate]').textContent; };
// Elegir la unidad.
ok(set(schedule.fields.unit, 'points') === 'Guardarás entre 2 y 8 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, no hay otra copia.', 'Elegir «respaldos» no cambió la estimación al momento');
ok(set(schedule.fields.unit, 'chains').startsWith('Guardarás entre 8 y 14 respaldos en 2 cadenas.'), 'Volver a «cadenas» no cambió la estimación');
// Cambiar N al escribir (input) y M y D.
ok(set(schedule.fields.retain, '3', 'input') === 'Guardarás entre 15 y 21 respaldos en 3 cadenas. Siempre quedan al menos 2 cadenas anteriores completas.', 'Escribir N = 3 no cambió la estimación');
ok(set(schedule.fields.every, '2', 'input') === 'Guardarás entre 7 y 9 respaldos en 3 cadenas. Siempre quedan al menos 2 cadenas anteriores completas.', 'Cambiar «un completo cada» no cambió la estimación');
ok(set(schedule.fields.days, '2', 'input') === 'Guardarás entre 5 y 6 respaldos en 3 cadenas. Con esta frecuencia la cadena se corta por días (un completo cada 2 días): 2 respaldos. Siempre quedan al menos 2 cadenas anteriores completas.', 'Cambiar los días no cortó la cadena: ' + schedule.form.querySelector('[data-retain-estimate]').textContent);
ok(set(schedule.fields.schedule, 'hourly') === 'Guardarás entre 7 y 9 respaldos en 3 cadenas. Siempre quedan al menos 2 cadenas anteriores completas.', 'Cambiar la frecuencia no cambió la estimación');
ok(set(schedule.fields.retain, '40', 'input').endsWith('«Conservar» quedará en 33.'), 'Más cadenas de las que caben no avisó el tope');
set(schedule.fields.retain, '1', 'input');
ok(set(schedule.fields.destination, 'sftp').endsWith('Una sola cadena: si su base se daña, sólo queda la copia remota.'), 'Cambiar el destino no cambió el aviso de la copia');
// El modo: «Completo cada vez» oculta la unidad y la ayuda; «Incremental» las muestra.
ok(set(schedule.fields.mode, 'full') === 'Guardarás 1 respaldo completo.' && schedule.fields.unit.hidden && !schedule.fields.noun.hidden && schedule.help.hidden, '«Completo cada vez» no ocultó la unidad');
// Semanal con un completo cada 7 días: cada respaldo sale completo.
ok(set(card.fields.mode, 'incremental') === 'Guardarás 4 respaldos en 4 cadenas. Con esta frecuencia cada respaldo sale completo (un completo cada 7 días). Siempre quedan al menos 3 cadenas anteriores completas.' && !card.fields.unit.hidden && card.fields.noun.hidden && !card.help.hidden, '«Incremental» no mostró la unidad ni la ayuda: ' + card.estimate.textContent);
ok(schedule.estimate.textContent === 'Guardarás 1 respaldo completo.', 'Un formulario cambió la estimación del otro');
// Otros campos y otros formularios no se tocan.
let before = card.estimate.textContent;
card.estimate.textContent = 'sin tocar';
fire('change', card.fields.other);
ok(card.estimate.textContent === before, 'Cambiar la hora no recalculó la misma estimación: ' + card.estimate.textContent);
ok(set(card.fields.schedule, 'daily') === 'Guardarás entre 4 y 10 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, no hay otra copia.', 'Pasar a diario no cambió la estimación: ' + card.estimate.textContent);
before = card.estimate.textContent;
const loose = Object.assign(new El('input', { name: 'otro_campo' }), { value: '9', name: 'otro_campo' });
loose.form = card.form;
fire('input', loose);
fire('input', { name: N('retain') });
ok(card.estimate.textContent === before, 'Un campo ajeno o sin formulario cambió la estimación');
// Un texto raro en un campo se pinta como texto.
ok(!/[<>]/.test(set(card.fields.retain, '<b>9</b>', 'input')), 'Un valor con HTML llegó a la estimación');
console.log(`DIGITALÍSIMO Backups: la estimación de «Conservar» del navegador es la del servidor en ${cases.length} casos y cambia al momento con la unidad, N, M, D, la frecuencia, el destino y el modo (${checks} comprobaciones).`);
