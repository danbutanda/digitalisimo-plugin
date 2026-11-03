// P7 · marca (f8.sh, delegado): lee $OUT/P7-data/<idioma>/ de brand-audit.sh ONLY=f8 (una tanda por idioma). El editor de los rasgos nuevos y su frontal, en
// es_MX y en_US, sin «Elementor» visible ni nada que apunte a un proveedor.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { ENV, check, note } = api;
  for (const locale of ['es_MX', 'en_US']) {
    const dir = path.join(ENV.OUT, 'P7-data', locale);
    const read = (f) => { try { return fs.readFileSync(path.join(dir, f), 'utf8'); } catch (e) { return ''; } };
    let res = null;
    try { res = JSON.parse(read('brand-audit.json')); } catch (e) { /* sin resultado */ }
    if (!res) { api.pending(s, `P7.${locale}`, `${locale}: brand-audit.sh ONLY=f8`, `tanda sin correr o sin resultado: P7_LOCALE=${locale} APPEND=1 SCENARIOS=P7 (${read('out.log').slice(-200)})`); continue; }
    const exit = read('exit.txt').trim();
    const steps = res.visited.filter((v) => /editor-f8|frontal-f8/.test(v));
    check(s, `P7.${locale}.0`, `${locale}: el editor de F8 (cuatro controles nuevos) y su frontal recorridos`, steps.length >= 5, JSON.stringify(steps));
    const f8errors = (res.errors || []).filter((e) => /f8/.test(JSON.stringify(e)));
    check(s, `P7.${locale}.1`, `${locale}: ningún control nuevo ni sección falta en el panel`, !f8errors.length, JSON.stringify(f8errors).slice(0, 600));
    const shown = (res.findings || []).filter((f) => f.shown);
    check(s, `P7.${locale}.2`, `${locale}: nadie ve «Elementor» (texto, atributos, títulos ni imágenes)`, !shown.length, JSON.stringify(shown.slice(0, 5)));
    const out = read('out.log');
    const m = out.match(/Plugins de origen: (\d+) fallas/);
    check(s, `P7.${locale}.3`, `${locale}: nada apunta a un proveedor (0 fallas) y brand-audit.sh sale con 0`, !!m && m[1] === '0' && exit === '0', `${m ? m[0] : 'sin resumen'} · salida ${exit} · ${out.split('\n').filter((l) => /^FALLA|^AVISO/.test(l)).slice(0, 4).join(' | ')}`);
    note(s, `${locale}: pantallas revisadas ${res.visited.length}`);
  }
};
