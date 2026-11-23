// Valida el cuerpo que el Asistente IA mandó a cada proveedor (ia-provider-body.sh): reglas de JSON Schema 2020-12 y
// las propias de cada API. Uso: node validate.mjs <OUT>. Sale ≠ 0 con el primer problema de cada herramienta listado.
import fs from 'node:fs';
import path from 'node:path';

const out = process.argv[2];
const TYPES = new Set(['object', 'array', 'string', 'number', 'integer', 'boolean', 'null']);
const SCHEMA_MAPS = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];
const SCHEMA_ONE = ['items', 'additionalProperties', 'not', 'if', 'then', 'else', 'contains', 'propertyNames', 'unevaluatedItems', 'unevaluatedProperties', 'additionalItems'];
const SCHEMA_LIST = ['anyOf', 'oneOf', 'allOf', 'prefixItems'];
const NON_NEG = ['minLength', 'maxLength', 'minItems', 'maxItems', 'minProperties', 'maxProperties', 'minContains', 'maxContains'];
const NUMS = ['minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf'];
const WP_ONLY = ['context', 'readonly', 'arg_options', 'sanitize_callback', 'validate_callback'];

const isObj = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);

/** Problemas de un esquema (2020-12 + Anthropic). `p` es la ruta para el mensaje. */
export function schemaProblems(s, p = '', strictProps = true) {
  const errs = [];
  if (typeof s === 'boolean') return errs;
  if (!isObj(s)) return [`${p || '(raíz)'}: el esquema es ${Array.isArray(s) ? 'una lista' : typeof s}, no un objeto ni un booleano`];
  if ('type' in s) {
    const t = s.type;
    const list = Array.isArray(t) ? t : [t];
    if (!list.length || list.some((x) => typeof x !== 'string' || !TYPES.has(x)) || new Set(list).size !== list.length) errs.push(`${p}.type inválido: ${JSON.stringify(t)}`);
  }
  if ('required' in s && !(Array.isArray(s.required) && s.required.every((x) => typeof x === 'string') && new Set(s.required).size === s.required.length)) errs.push(`${p}.required no es una lista de nombres: ${JSON.stringify(s.required)}`);
  if ('enum' in s && !(Array.isArray(s.enum) && s.enum.length)) errs.push(`${p}.enum vacío o no es lista: ${JSON.stringify(s.enum)}`);
  for (const k of NON_NEG) if (k in s && !(Number.isInteger(s[k]) && s[k] >= 0)) errs.push(`${p}.${k} no es un entero ≥ 0: ${JSON.stringify(s[k])}`);
  for (const k of NUMS) if (k in s && typeof s[k] !== 'number') errs.push(`${p}.${k} no es un número: ${JSON.stringify(s[k])}`);
  if ('pattern' in s) { try { new RegExp(s.pattern, 'u'); } catch (e) { errs.push(`${p}.pattern inválido: ${JSON.stringify(s.pattern)}`); } }
  if ('description' in s && typeof s.description !== 'string') errs.push(`${p}.description no es texto`);
  if ('$ref' in s) errs.push(`${p}.$ref sin resolver: ${JSON.stringify(s.$ref)}`);
  for (const k of WP_ONLY) if (k in s) errs.push(`${p}.${k}: clave de esquema REST de WordPress (no es JSON Schema)`);
  for (const k of SCHEMA_MAPS) {
    if (!(k in s)) continue;
    if (!isObj(s[k])) { errs.push(`${p}.${k} no es un objeto: ${JSON.stringify(s[k]).slice(0, 80)}`); continue; }
    for (const [name, sub] of Object.entries(s[k])) {
      if (k === 'properties' && strictProps && !/^[a-zA-Z0-9_.-]{1,64}$/.test(name)) errs.push(`${p}.properties: nombre de propiedad no admitido ${JSON.stringify(name)}`);
      errs.push(...schemaProblems(sub, `${p}.${k}.${name}`, strictProps));
    }
  }
  for (const k of SCHEMA_ONE) if (k in s) errs.push(...schemaProblems(s[k], `${p}.${k}`, strictProps));
  for (const k of SCHEMA_LIST) {
    if (!(k in s)) continue;
    if (!Array.isArray(s[k]) || !s[k].length) { errs.push(`${p}.${k} no es una lista no vacía`); continue; }
    s[k].forEach((sub, i) => errs.push(...schemaProblems(sub, `${p}.${k}[${i}]`, strictProps)));
  }
  return errs;
}

export function anthropicProblems(body) {
  const errs = [];
  const seen = new Set();
  (body.tools || []).forEach((t, i) => {
    const where = `tools.${i} (${t.name})`;
    if (!/^[a-zA-Z0-9_-]{1,64}$/.test(t.name || '')) errs.push(`${where}: nombre no admitido`);
    if (seen.has(t.name)) errs.push(`${where}: nombre repetido`);
    seen.add(t.name);
    if (typeof t.description !== 'string') errs.push(`${where}: description no es texto`);
    const s = t.input_schema;
    if (!isObj(s) || s.type !== 'object') { errs.push(`${where}: input_schema.type debe ser "object"`); return; }
    for (const k of ['oneOf', 'anyOf', 'allOf']) if (k in s) errs.push(`${where}: input_schema.${k} en la raíz no se admite`);
    schemaProblems(s, 'input_schema').forEach((e) => errs.push(`${where}: ${e}`));
  });
  if (!Array.isArray(body.messages) || !body.messages.length || body.messages[0].role !== 'user') errs.push('messages: el primero debe ser del usuario');
  return errs;
}

export function openaiProblems(body) {
  const errs = [];
  const seen = new Set();
  (body.tools || []).forEach((t, i) => {
    const f = t.function || {};
    const where = `tools[${i}] (${f.name})`;
    if (seen.has(f.name)) errs.push(`${where}: nombre repetido`);
    seen.add(f.name);
    const s = f.parameters;
    if (!isObj(s) || s.type !== 'object') { errs.push(`${where}: parameters.type debe ser "object"`); return; }
    for (const k of ['oneOf', 'anyOf', 'allOf']) if (k in s) errs.push(`${where}: parameters.${k} en la raíz no se admite`);
    schemaProblems(s, 'parameters').forEach((e) => errs.push(`${where}: ${e}`));
    // OpenAI exige items en todo arreglo.
    const walk = (x, p) => { if (!isObj(x)) return; const ty = Array.isArray(x.type) ? x.type : [x.type]; if (ty.includes('array') && !('items' in x)) errs.push(`${where}: ${p} es array sin items`); for (const k of SCHEMA_MAPS) if (isObj(x[k])) for (const [n, v] of Object.entries(x[k])) walk(v, `${p}.${k}.${n}`); for (const k of SCHEMA_ONE) walk(x[k], `${p}.${k}`); for (const k of SCHEMA_LIST) if (Array.isArray(x[k])) x[k].forEach((v, j) => walk(v, `${p}.${k}[${j}]`)); };
    walk(s, 'parameters');
  });
  return errs;
}

export function googleProblems(body) {
  const errs = [];
  const decl = body.tools?.[0]?.functionDeclarations || [];
  const ok = new Set(['type', 'description', 'enum', 'properties', 'required', 'items', 'minItems', 'maxItems', 'minimum', 'maximum', 'nullable']);
  decl.forEach((d, i) => {
    const walk = (x, p) => {
      if (!isObj(x)) { errs.push(`functionDeclarations[${i}] (${d.name}): ${p} no es objeto`); return; }
      for (const k of Object.keys(x)) if (!ok.has(k)) errs.push(`functionDeclarations[${i}] (${d.name}): ${p}.${k} no lo admite Gemini`);
      if (x.properties) for (const [n, v] of Object.entries(x.properties)) walk(v, `${p}.properties.${n}`);
      if (x.items) walk(x.items, `${p}.items`);
    };
    if (d.parameters) walk(d.parameters, 'parameters');
  });
  return errs;
}

if (out) {
  let fail = 0;
  const check = (label, ok, detail = '') => { console.log(`${ok ? 'OK   ' : 'FALLA'} ${label}${detail ? ' — ' + detail : ''}`); if (!ok) fail++; };
  const load = (f) => { try { return JSON.parse(fs.readFileSync(path.join(out, f), 'utf8')); } catch (e) { return null; } };
  const rules = { anthropic: anthropicProblems, openai: openaiProblems, google: googleProblems };
  for (const [provider, fn] of Object.entries(rules)) {
    const r = load(`${provider}.json`);
    if (!r || !Array.isArray(r.sent) || !r.sent.length) { check(`${provider}: se capturó el cuerpo`, false, JSON.stringify(r).slice(0, 300)); continue; }
    const body = r.sent[0].body;
    const n = provider === 'google' ? (body.tools?.[0]?.functionDeclarations || []).length : (body.tools || []).length;
    check(`${provider}: el cuerpo lleva las herramientas del MCP`, n > 5 && n === Object.keys(r.schemas || {}).length, `${n} herramientas, catálogo ${Object.keys(r.schemas || {}).length}`);
    const errs = fn(body);
    check(`${provider}: cuerpo válido para la API`, !errs.length, errs.length ? `${errs.length} problema(s):\n      ` + errs.slice(0, 40).join('\n      ') : '');
    const text = JSON.stringify(r.events || {});
    check(`${provider}: el Asistente respondió`, text.includes('Respuesta simulada.'), text.slice(0, 200));
  }
  const retry = load('anthropic-retry.json');
  if (retry && Array.isArray(retry.sent)) {
    const text = JSON.stringify(retry.events || {});
    check('reintento: dos peticiones', retry.sent.length === 2, `${retry.sent.length}`);
    const a = retry.sent[0]?.body?.tools || [], b = retry.sent[1]?.body?.tools || [];
    check('reintento: la segunda sin la herramienta rechazada', b.length === a.length - 1 && !b.some((t) => t.name === a[0]?.name), `${a.length} → ${b.length}`);
    check('reintento: el Asistente respondió', text.includes('Respuesta simulada.'), text.slice(0, 200));
  } else check('reintento: se capturó', false);
  console.log(fail ? `${fail} falla(s)` : 'Todo en orden');
  process.exit(fail ? 1 : 0);
}
