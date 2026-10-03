#!/usr/bin/env node
// Regenera digitalisimo-seo/includes/schema/schema-vocabulary.php a partir del
// vocabulario oficial publicado por schema.org. Uso:
//   node scripts/schema-vocabulary.mjs [types.csv props.csv]
// Sin argumentos descarga la versión vigente.
import { readFileSync, writeFileSync } from 'node:fs';

const BASE = 'https://schema.org/version/latest/';
const source = async (file, local) => (local ? readFileSync(local, 'utf8') : (await fetch(BASE + file)).text());

/** CSV con comillas dobles y saltos de línea dentro de los campos. */
function parse(text) {
  const rows = [];
  let row = [], field = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') { field += '"'; i++; }
      else if (c === '"') quoted = false;
      else field += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
    else if (c !== '\r') field += c;
  }
  if (field || row.length) { row.push(field); rows.push(row); }
  const [head, ...body] = rows;
  return body.filter((r) => r.length === head.length).map((r) => Object.fromEntries(head.map((h, i) => [h, r[i]])));
}

const names = (value) => value.split(',').map((v) => v.trim().replace('https://schema.org/', '')).filter(Boolean);
const status = (row) => (row.supersededBy ? 'superseded' : /pending|attic/.test(row.isPartOf) ? 'pending' : '');
const php = (value) => "'" + String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

const [typesFile, propsFile] = process.argv.slice(2);
const types = parse(await source('schemaorg-current-https-types.csv', typesFile));
const props = parse(await source('schemaorg-current-https-properties.csv', propsFile));

const lines = [];
lines.push('<?php');
lines.push('// Generado por scripts/schema-vocabulary.mjs desde el vocabulario oficial de schema.org. No editar a mano.');
lines.push("defined( 'ABSPATH' ) || exit;");
lines.push('return array(');
lines.push("\t'types' => array(");
for (const row of types.sort((a, b) => a.label.localeCompare(b.label))) {
  if (!row.label || /[^A-Za-z0-9]/.test(row.label)) continue;
  lines.push(`\t\t${php(row.label)} => array( ${php(names(row.subTypeOf).join(','))}, ${php(status(row))}, ${php(names(row.supersededBy).join(','))} ),`);
}
lines.push('\t),');
lines.push("\t'properties' => array(");
for (const row of props.sort((a, b) => a.label.localeCompare(b.label))) {
  if (!row.label || /[^A-Za-z0-9]/.test(row.label)) continue;
  lines.push(`\t\t${php(row.label)} => array( ${php(names(row.domainIncludes).join(','))}, ${php(status(row))}, ${php(names(row.supersededBy).join(','))} ),`);
}
lines.push('\t),');
lines.push(');');
writeFileSync(new URL('../digitalisimo-seo/includes/schema/schema-vocabulary.php', import.meta.url), lines.join('\n') + '\n');
console.log(`Vocabulario: ${types.length} tipos, ${props.length} propiedades.`);
