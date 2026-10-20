// Informe de theme-network.sh: reúne los problemas de cada paso (result.json), revisa los avisos de PHP de los pasos con
// pantalla (N2, N5) y las URL de esas pantallas contra la lista de proveedores (brand-audit/vendors.json).
'use strict';
const fs = require('fs');
const path = require('path');

const OUT = process.env.OUT;
const ROOT = process.env.ROOT;
const steps = (process.env.STEPS || '').split(/\s+/).filter(Boolean);
const vendorsLib = require(path.join(ROOT, 'tests/playground/brand-audit/vendors.cjs'));
const V = vendorsLib.load(ROOT);
const result = fs.existsSync(path.join(OUT, 'result.json')) ? JSON.parse(fs.readFileSync(path.join(OUT, 'result.json'), 'utf8')) : {};
const readJsonl = (file) => (fs.existsSync(file) ? fs.readFileSync(file, 'utf8').split('\n').filter(Boolean).map((l) => { try { return JSON.parse(l); } catch (e) { return null; } }).filter(Boolean) : []);
const group = (step) => ({ setup: 'Preparación', 'n1-main': 'N1', 'n1-sub': 'N1', 'n2-net': 'N2', 'n2-sub': 'N2', n5: 'N5', 'n6-switch': 'N6', 'n6-check': 'N6', 'n6-restore': 'N6', n3f: 'N3f', 'n3f-check': 'N3f', n4a: 'N4', n4c: 'N4', n4b: 'N4', n3: 'N3', 'n3-check': 'N3' }[step] || step);
const failures = {};
const fail = (step, message) => { const g = group(step); (failures[g] = failures[g] || []).push(`${step}: ${message}`); };

for (const step of steps) {
  if (!result[step]) { fail(step, 'el paso no dejó resultado (¿error fatal? ver playground.log)'); continue; }
  for (const p of result[step].problems || []) fail(step, p);
}

// Avisos de PHP: los de las pantallas (N2, N5) y cualquiera nacido en Tools o en el tema.
const php = readJsonl(path.join(OUT, 'php.jsonl'));
const baseline = new Set(php.filter((r) => ['setup', 'n1-main'].includes(r.step)).map((r) => `${r.kind}|${r.msg}|${r.file}|${r.line}`));
const seen = new Set();
for (const r of php) {
  const sig = `${r.kind}|${r.msg}|${r.file}|${r.line}`;
  const own = /digitalisimo-(tools|theme)/.test(`${r.file} ${r.msg}`);
  const screen = ['n2-net', 'n2-sub', 'n5'].includes(r.step) && !baseline.has(sig);
  if (!(own || screen) || seen.has(sig)) continue;
  seen.add(sig);
  fail(r.step, `${r.kind}: ${String(r.msg).slice(0, 200)} (${String(r.file).replace(/^\/wordpress\//, '')}:${r.line})`);
}

// Ninguna URL de las pantallas va a un plugin de origen.
for (const [file, step] of [['n5-details.html', 'n5'], ['n2-network.html', 'n2-net'], ['n2-site.html', 'n2-sub']]) {
  const full = path.join(OUT, file);
  if (!fs.existsSync(full)) { if (result[step]) fail(step, `no se guardó ${file}`); continue; }
  const html = fs.readFileSync(full, 'utf8');
  for (const m of html.matchAll(/(?:href|src|action|srcset)=["']([^"']+)["']/gi)) {
    const v = vendorsLib.classify(V, m[1].replace(/&#038;|&amp;/g, '&'));
    if (v) fail(step, `URL de origen en ${file}: ${m[1].slice(0, 120)} (${v.why})`);
  }
}

const http = readJsonl(path.join(OUT, 'http.jsonl'));
const hosts = http.reduce((acc, r) => { acc[r.host] = (acc[r.host] || 0) + 1; return acc; }, {});
console.log(`Peticiones HTTP del sitio (todas respondidas por la prueba o bloqueadas): ${JSON.stringify(hosts)}`);
const order = ['Preparación', 'N1', 'N2', 'N3', 'N3f', 'N4', 'N5', 'N6'];
let failed = 0;
const ran = new Set(steps.map(group));
for (const g of order) {
  const list = failures[g] || [];
  if (!ran.has(g) && !list.length) { console.log(`${g.padEnd(12)} (no se ejecutó)`); continue; }
  console.log(`${g.padEnd(12)} ${list.length ? `FALLA (${list.length})` : 'PASA'}`);
  for (const line of list) console.log(`   ${line}`);
  failed += list.length;
}
for (const g of Object.keys(failures)) if (!order.includes(g)) { console.log(`${g} FALLA`); for (const line of failures[g]) console.log(`   ${line}`); failed += failures[g].length; }
const complete = order.every((g) => ran.has(g));
console.log(failed ? `\n${failed} problemas. Detalle en ${path.join(OUT, 'result.json')}` : complete ? '\nRed Multisite: N1-N6 y N3f en verde, sin montajes del repositorio.' : '\nLos pasos ejecutados pasan (corrida parcial con ONLY).');
process.exit(failed ? 1 : 0);
