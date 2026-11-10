// Corre con el `php` del PATH las pruebas PHP del paso «Probar la suite» de un workflow (por defecto validate.yml):
// el trabajo «PHP 7.4» de validate.yml y release.yml la usa para que la versión mínima que declaran los plugins corra
// la misma lista que la CI con PHP 8.2, sin copiarla. Las pruebas Node y de Chrome no dependen de la versión de PHP y
// se quedan en el trabajo principal. Corre todas aunque alguna falle, resume al final y sale 1 si falló alguna.
//
// Con `--pendientes <archivo>`, las pruebas de esa lista (una ruta `tests/….php` por línea; `#` comenta) se corren
// igual pero se espera que fallen: son las que aún no corren con esta versión (la lista dice por qué). Si una ya
// pasa, el resultado es una falla que pide quitarla de la lista, para que la lista sólo se acorte.
//
// Uso: node scripts/ci-php-suite.mjs [.github/workflows/validate.yml] [--pendientes <archivo>] [--listar]
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));

/** Órdenes PHP (`php tests/…` y el bucle `for module in …`) del bloque `run: |` del paso «Probar la suite». */
export function phpSuiteCommands(workflowText) {
  const lines = workflowText.split('\n');
  const step = lines.findIndex((line) => /^\s*- name: Probar la suite\s*$/.test(line));
  if (step < 0) throw new Error('El workflow no tiene el paso «Probar la suite».');
  const stepIndent = lines[step].indexOf('-');
  let run = -1;
  for (let i = step + 1; i < lines.length; i++) {
    if (lines[i].trim() && lines[i].search(/\S/) <= stepIndent) break;
    if (/^\s*run: \|\s*$/.test(lines[i])) { run = i; break; }
  }
  if (run < 0) throw new Error('El paso «Probar la suite» no tiene un bloque «run: |».');
  const keyIndent = lines[run].search(/\S/);
  const commands = [];
  for (let i = run + 1; i < lines.length; i++) {
    const line = lines[i];
    if (!line.trim()) continue;
    if (line.search(/\S/) <= keyIndent) break;
    const command = line.trim();
    if (/^php tests\//.test(command) || (/^for module in /.test(command) && / php tests\//.test(command))) commands.push(command);
  }
  return commands;
}

/** Rutas de una lista de pendientes: una `tests/….php` por línea, sin comentarios ni líneas vacías. */
export function pendingTests(text) {
  return text.split('\n').map((line) => line.replace(/#.*/, '').trim()).filter(Boolean);
}

const isMain = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];
if (isMain) {
  const args = process.argv.slice(2);
  const pendingAt = args.indexOf('--pendientes');
  const pendingFile = pendingAt >= 0 ? args[pendingAt + 1] : '';
  if (pendingAt >= 0 && !pendingFile) throw new Error('Falta el archivo de --pendientes.');
  const workflow = args.find((arg, i) => !arg.startsWith('--') && i !== pendingAt + 1) || '.github/workflows/validate.yml';
  const commands = phpSuiteCommands(readFileSync(join(root, workflow), 'utf8'));
  if (commands.length < 100) throw new Error(`Sólo ${commands.length} pruebas PHP en ${workflow}: la lista no se leyó bien.`);
  const pending = new Set(pendingFile ? pendingTests(readFileSync(join(root, pendingFile), 'utf8')) : []);
  const unknown = [...pending].filter((test) => !commands.includes(`php ${test}`));
  if (unknown.length) throw new Error(`${pendingFile} nombra pruebas que no están en «Probar la suite» de ${workflow}: ${unknown.join(', ')}`);
  if (args.includes('--listar')) {
    console.log(commands.join('\n'));
    process.exit(0);
  }
  const version = spawnSync('php', ['-r', 'echo PHP_VERSION;'], { cwd: root, encoding: 'utf8' });
  if (version.status !== 0) throw new Error('No hay `php` en el PATH.');
  console.log(`PHP ${version.stdout}: ${commands.length} órdenes PHP de «Probar la suite» de ${workflow}${pending.size ? `, ${pending.size} pendientes en ${pendingFile}` : ''}.`);
  const failed = [];
  const nowPassing = [];
  let expected = 0;
  for (const command of commands) {
    const started = Date.now();
    const result = spawnSync('bash', ['-ec', command], { cwd: root, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024, timeout: 1800 * 1000 });
    const seconds = Math.round((Date.now() - started) / 1000);
    const output = `${result.stdout || ''}${result.stderr || ''}`;
    const passed = result.status === 0 && !result.error;
    const isPending = pending.has(command.replace(/^php /, ''));
    if (isPending) {
      if (passed) {
        nowPassing.push(command);
        console.log(`YA PASA (${seconds}s) ${command}: quítala de ${pendingFile}`);
      } else {
        expected++;
        console.log(`pendiente (${seconds}s) ${command} · ${output.trim().split('\n').find((line) => /Fatal|Uncaught|FALL|Warning/.test(line))?.trim().slice(0, 160) || ''}`);
      }
      continue;
    }
    if (passed) {
      console.log(`ok (${seconds}s) ${command}`);
      continue;
    }
    failed.push(command);
    console.log(`FALLA (${result.error ? result.error.code : result.status}, ${seconds}s) ${command}`);
    console.log(output.trim().split('\n').slice(-12).map((line) => `    ${line}`).join('\n'));
  }
  const ran = commands.length - pending.size;
  if (failed.length || nowPassing.length) {
    if (failed.length) console.log(`\n${failed.length} de ${ran} pruebas PHP fallaron con PHP ${version.stdout}:\n${failed.join('\n')}`);
    if (nowPassing.length) console.log(`\n${nowPassing.length} pruebas de ${pendingFile} ya pasan con PHP ${version.stdout}; quítalas de la lista:\n${nowPassing.join('\n')}`);
    process.exit(1);
  }
  console.log(`\nLas ${ran} pruebas PHP pasan con PHP ${version.stdout}${pending.size ? `; las ${expected} pendientes de ${pendingFile} siguen fallando` : ''}.`);
}
