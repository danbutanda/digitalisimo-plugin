// Compara dos corridas ya hechas de front-budget.sh, como COMPARE_FILE al final de una corrida (F7a, criterios 1 y 2).
//
// Uso: node tests/playground/front-budget/compare.cjs <referencia/front-budget[-modo].json> <actual/front-budget[-modo].json>
//      COMPARE_STRICT=empty,all-own (por defecto) · CASES_FILE=<cases.json> (por defecto el que copió la corrida actual)
// Sale con 1 si algún caso sin `since` cambia. Las huellas se recalculan desde <corrida>/pages con compare-lib.cjs.
const fs = require('fs');
const path = require('path');
const { compareRuns, summaryLines } = require('./compare-lib.cjs');

const [refFile, nowFile] = process.argv.slice(2);
if (!refFile || !nowFile) {
  console.error('Uso: node compare.cjs <referencia.json> <actual.json>');
  process.exit(2);
}
const ref = JSON.parse(fs.readFileSync(refFile, 'utf8'));
const now = JSON.parse(fs.readFileSync(nowFile, 'utf8'));
const casesFile = process.env.CASES_FILE || path.join(path.dirname(nowFile), 'cases.json');
const cases = JSON.parse(fs.readFileSync(casesFile, 'utf8'));
const strict = (process.env.COMPARE_STRICT || 'empty,all-own').split(',').map((s) => s.trim()).filter(Boolean);
const { out, problems } = compareRuns({ ref, refDir: path.dirname(refFile), now: { results: now.results, mode: now.mode, elements: now.elements }, nowDir: path.dirname(nowFile), cases, strict });
for (const p of problems) console.log(`FALLA  ${p}`);
for (const line of summaryLines(refFile, out)) console.log(line);
if (process.env.COMPARE_OUT) fs.writeFileSync(process.env.COMPARE_OUT, JSON.stringify({ ref: refFile, now: nowFile, ...out }, null, 1));
process.exit(problems.length ? 1 : 0);
