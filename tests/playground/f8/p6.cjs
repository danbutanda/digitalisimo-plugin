// P6 · interacción (f8.sh, delegado): lee $RUN/ix/interaction.json de interaction.sh. Los tres casos de F8 (información
// emergente con teclado y Escape, distintivo en RTL y contador con autoplay y pausa) y, además, que ningún caso
// existente se haya roto.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { ENV, check, note } = api;
  let res = null;
  try { res = JSON.parse(fs.readFileSync(path.join(ENV.RUN, 'ix', 'interaction.json'), 'utf8')); } catch (e) { /* sin resultado */ }
  check(s, 'P6.0', 'interaction.sh terminó y dejó su resultado', !!res, path.join(ENV.RUN, 'ix'));
  if (!res) return;
  for (const [key, what] of [['f8-tooltip', 'información emergente: se abre con el foco y Escape la cierra'], ['f8-badge-rtl', 'distintivo en LTR y RTL'], ['f8-nested-counter', 'contador del slider anidado con autoplay y pausa con el foco']]) {
    const r = res[key];
    check(s, `P6.${key}`, what, !!r && r.ok, JSON.stringify(r || null).slice(0, 600));
    if (r && r.note) note(s, `${key}: ${r.note}`);
  }
  const others = Object.entries(res).filter(([k, v]) => v && typeof v === 'object' && 'ok' in v && !/^f8-/.test(k));
  const broken = others.filter(([, v]) => !v.ok);
  check(s, 'P6.rest', `los ${others.length} casos existentes siguen en verde`, others.length > 0 && !broken.length, JSON.stringify(broken.map(([k, v]) => `${k}: ${String(v.error).slice(0, 200)}`)));
};
