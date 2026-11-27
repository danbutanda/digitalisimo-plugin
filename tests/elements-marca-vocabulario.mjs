#!/usr/bin/env node
// Marca propia (D-12 de docs/MARCA_PROPIA.md): digitalisimo-elements/marca/vocabulario.json tiene que estar al día con el
// JS del paquete (scripts/marca-vocabulario.mjs lo deriva). Si una versión nueva del núcleo o de PRO trae cadenas
// nuevas, el editor con la marca encendida mandaría al servidor nombres que no sabe deshacer.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
try {
  process.stdout.write(execFileSync(process.execPath, [fileURLToPath(new URL('../scripts/marca-vocabulario.mjs', import.meta.url))]).toString());
} catch (e) {
  process.stdout.write(String(e.stdout || ''));
  process.exit(1);
}
