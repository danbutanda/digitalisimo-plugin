// Marca propia (docs/MARCA_PROPIA.md): `digitalisimo-elements/marca/reglas.json` cubre todo lo que los bundles REALES
// del front (núcleo incluido, derivado de PRO y módulos propios) llaman «elementor» —clases, atributos y su forma
// `dataset`, globales y propiedades JS, eventos, rutas REST y acciones AJAX del front, acciones de URL— y sus listas se
// derivaron de esos mismos bundles (huella). Falla si una versión nueva del núcleo o de PRO trae algo sin regla: se
// repasa y se regenera con `node scripts/marca-reglas.mjs --escribir`.
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const script = fileURLToPath(new URL('../scripts/marca-reglas.mjs', import.meta.url));
const run = spawnSync(process.execPath, [script], { encoding: 'utf8' });
process.stdout.write(run.stdout || '');
process.stderr.write(run.stderr || '');
if (run.status !== 0) {
  console.log('FALLA las reglas de la marca propia no cubren los bundles del front');
  process.exit(1);
}
console.log('Reglas de la marca propia al día con los bundles del front.');
