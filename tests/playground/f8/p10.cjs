// P10 de f8.sh (delegado): lee las tandas de f8/p10.sh ($OUT/P10-parts/<tanda>/) y las convierte en comprobaciones.
//
// - legacy-ab.sh (w1, w2, ext): las fases A–E terminan; Element Pack es la copia oficial de Lite 10.0.1; cada caso de lo
//   que Lite registra coincide (legacy-compare.mjs) o sus diferencias están declaradas aquí (DECLARED, con su motivo);
//   la firma de estilos computados de los mapas corregidos por F8 que Lite registra (Trailer Box, Animated Card e
//   Interactive Card) se midió en A, B, C y E; los casos de F8 cumplen `_expect_css` en A y `_expect_css_converted` en
//   C y E, y la página queda sin claves heredadas.
// - migrate.sh (mg-base, mg-lite): sus comprobaciones, sus fallos y las diferencias declaradas de su manifiesto.
// Una tanda que falta queda «pendiente» con la orden que la corre.
'use strict';
const fs = require('fs');
const path = require('path');

// Diferencias de legacy-compare.mjs que están declaradas (spec de F8 y fixtures), con su motivo. Cada regla:
// `widget` (regex del caso), `problem` (regex de la línea) y `motivo`.
const DECLARED = [
  { widget: /^ext-tooltip$/, problem: /(tras la migración|convertido y sin capa heredada) cambia text/, motivo: 'V.10.5: el globo de la información emergente está en el DOM (y en el árbol de accesibilidad), no en un atributo: su texto aparece en la firma' },
  { widget: /^ext-threed-text$/, problem: /(tras la migración|convertido y sin capa heredada) cambia text/, motivo: '§3.C.2: el origen repite el texto en capas para el relieve; la conversión lo pinta una vez con sombras de texto en capas' },
  // Element Pack 10.x (Lite y Pro, traits/global-controls-functions.php:698 y :704) pone el nombre de la marca como
  // `alt` del logotipo; 9.9.1 no lo hacía (el fixture es de 9.9.1). La Cuadrícula de marcas de Elements deja `alt` vacío
  // porque el nombre ya se lee como texto del elemento, y ningún widget existente cambia su HTML (regla de Elements).
  { widget: /^bdt-brand-grid$/, problem: /^(images (ausente|adicional)|(tras la migración|convertido y sin capa heredada) cambia images)/, motivo: 'Element Pack 10.x usa el nombre de la marca como alt del logotipo; Elements deja alt vacío (el nombre ya es texto visible) y no cambia el HTML de un widget existente' },
];

module.exports = (api) => async (s) => {
  const { ENV, check, pending, note } = api;
  const PARTS = path.join(ENV.OUT, 'P10-parts');
  const read = (file) => { try { return fs.readFileSync(file, 'utf8'); } catch (e) { return null; } };
  const json = (file) => { const t = read(file); try { return t ? JSON.parse(t) : null; } catch (e) { return null; } };
  const ep = json(path.join(PARTS, 'ep.json'));
  s.facts.element_pack = ep;
  check(s, 'P10.0', 'Element Pack es la copia oficial de Lite 10.0.1 con su sha256 comprobada (nunca Pro)', !!ep && ep.source === 'reference' && ep.version === '10.0.1' && ep.slug === 'bdthemes-element-pack-lite' && ep.sha256 === '476b853d8d936787991b8983c2b5407b84796e37fc33a5da5774ad914e6dd9d7', JSON.stringify(ep));
  const how = (part) => `OUT=${ENV.OUT} APPEND=1 P10_PART=${part} SCENARIOS=P10 bash tests/playground/f8.sh`;
  s.facts.parts = {};

  // ── legacy-ab.sh ──────────────────────────────────────────────────────────────────────────────────────────────
  const styleSeen = {};
  for (const part of ['w1', 'w2', 'ext']) {
    const dir = path.join(PARTS, part);
    const done = json(path.join(dir, 'done.json'));
    if (!done) { pending(s, `P10.${part}`, `legacy-ab.sh, tanda ${part}`, `sin correr: ${how(part)}`); continue; }
    const run = json(path.join(dir, 'run.json')) || {};
    const compare = read(path.join(dir, 'compare.txt')) || '';
    s.facts.parts[part] = { duration_s: done.duration_s, exit: done.exit, cases: done.only.length, steps: (run.steps || []).map((x) => `${x.step}:${x.ok ? 'ok' : 'falla'}:${Math.round((x.ms || 0) / 1000)}s`) };
    check(s, `P10.${part}.1`, `legacy-ab.sh ${part}: las fases A, B, C, E y D terminan sin errores fatales (${done.only.length} archivos de casos, ${done.duration_s} s)`, run.ok === true && !read(path.join(dir, 'fatal.log')), JSON.stringify({ problems: run.problems, fatal: (read(path.join(dir, 'fatal.log')) || '').slice(0, 400) }));
    // Bloques de legacy-compare: «ESTADO widget · caso · sitio N» con sus líneas «- problema» y «· nota».
    const blocks = [];
    let cur = null;
    for (const line of compare.split('\n')) {
      const m = line.match(/^(IGUAL|DIFERENCIAS)\s+(\S+) · (.+) · sitio (\d+)$/);
      if (m) { cur = { status: m[1], widget: m[2], name: m[3], blog: m[4], problems: [], notes: [] }; blocks.push(cur); continue; }
      if (cur && /^ {4}- /.test(line)) cur.problems.push(line.slice(6));
      if (cur && /^ {4}· /.test(line)) cur.notes.push(line.slice(6));
    }
    const declared = [];
    const real = [];
    for (const b of blocks) {
      for (const p of b.problems) {
        const rule = DECLARED.find((r) => r.widget.test(b.widget) && r.problem.test(p));
        (rule ? declared : real).push(`${b.widget} · ${b.name} · sitio ${b.blog}: ${p.slice(0, 300)}${rule ? ` (declarada: ${rule.motivo})` : ''}`);
      }
    }
    s.facts.parts[part].combinations = blocks.length;
    s.facts.parts[part].equal = blocks.filter((b) => b.status === 'IGUAL').length;
    s.facts.parts[part].declared = declared.length;
    s.facts.parts[part].differences = real;
    check(s, `P10.${part}.2`, `legacy-ab.sh ${part}: ${blocks.length} combinaciones comparadas, ${blocks.filter((b) => b.status === 'IGUAL').length} iguales y el resto sólo con diferencias declaradas`, blocks.length > 0 && !real.length, JSON.stringify(real.slice(0, 12)));
    if (declared.length) note(s, `${part}: ${declared.length} diferencias declaradas (${[...new Set(declared.map((d) => d.split(' · ')[0]))].join(', ')})`);
    // Fases: casos de F8 (`_expect_css` en A y `_expect_css_converted` en C y E) y lo que Lite no registra.
    const phases = Object.fromEntries(['A', 'B', 'C', 'D', 'E'].map((ph) => [ph, json(path.join(dir, `phase${ph}.json`))]));
    if (phases.A) {
      s.facts.parts[part].skipped = (phases.A.skipped || []).length;
      check(s, `P10.${part}.3`, `fase A con Element Pack Lite cargado (${phases.A.element_pack_version})`, phases.A.element_pack === true && phases.A.element_pack_version === '10.0.1', JSON.stringify({ element_pack: phases.A.element_pack, version: phases.A.element_pack_version }));
      const unregistered = [];
      const cssA = [];
      const cssConverted = [];
      for (const [widget, cases] of Object.entries(phases.A.widgets || {})) {
        for (const [name, blogs] of Object.entries(cases)) {
          for (const [blog, entry] of Object.entries(blogs)) {
            if (!entry.registered) unregistered.push(`${widget} · ${name} · ${blog}`);
            if (entry.css_missing && entry.css_missing.length) cssA.push(`${widget} · ${name} · ${blog}: ${JSON.stringify(entry.css_missing)}`);
            if (entry.css_error && String(blog) === '1') cssA.push(`${widget} · ${name} · ${blog}: ${entry.css_error}`);
            for (const ph of ['C', 'E']) {
              const e = phases[ph] && phases[ph].widgets && phases[ph].widgets[widget] && phases[ph].widgets[widget][name] && phases[ph].widgets[widget][name][blog];
              if (e && e.css_missing && e.css_missing.length) cssConverted.push(`${ph} · ${widget} · ${name} · ${blog}: ${JSON.stringify(e.css_missing)}`);
            }
          }
        }
      }
      check(s, `P10.${part}.4`, 'Lite registra cada widget de los casos elegidos (la lista sale de la copia)', !unregistered.length, unregistered.slice(0, 10).join(', '));
      if (part === 'ext') {
        check(s, `P10.${part}.5`, 'extensiones de F8: Element Pack pinta el CSS de `_expect_css` (fase A) y el convertido el de `_expect_css_converted` (C y E)', !cssA.length && !cssConverted.length, JSON.stringify({ A: cssA.slice(0, 6), convertido: cssConverted.slice(0, 6) }));
        const pageLeft = [];
        for (const [widget, cases] of Object.entries((phases.E && phases.E.widgets) || {})) {
          for (const [name, blogs] of Object.entries(cases)) for (const [blog, e] of Object.entries(blogs)) if ((e.legacy_left || []).length) pageLeft.push(`${widget} · ${name} · ${blog}: ${JSON.stringify(e.legacy_left)}`);
        }
        check(s, `P10.${part}.6`, 'tras convertir no queda ninguna clave heredada en el documento ni en los ajustes de la página', !pageLeft.length, pageLeft.slice(0, 6).join(' | '));
      }
    }
    for (const [key, byPhase] of Object.entries(run.styles || {})) styleSeen[key] = byPhase;
  }
  // Firma de estilos de los mapas corregidos (frente B): medida en las cuatro fases y comparada por legacy-compare.
  const want = ['bdt-trailer-box', 'bdt-animated-card', 'bdt-interactive-card'];
  const missing = want.filter((w) => !Object.keys(styleSeen).some((k) => k.startsWith(`${w}·`) && ['A', 'B', 'C', 'E'].every((ph) => styleSeen[k][ph])));
  s.facts.styles = styleSeen;
  if (['w1', 'w2'].every((p) => fs.existsSync(path.join(PARTS, p, 'done.json')))) {
    check(s, 'P10.styles', 'firma de estilos computados de Trailer Box, Animated Card e Interactive Card medida en A (clases del origen) y en B, C y E (clases propias)', !missing.length, JSON.stringify({ missing, measured: Object.keys(styleSeen) }));
  } else pending(s, 'P10.styles', 'firma de estilos de los mapas corregidos', 'faltan las tandas w1 o w2');

  // ── migrate.sh ────────────────────────────────────────────────────────────────────────────────────────────────
  for (const part of ['mg-base', 'mg-lite']) {
    const dir = path.join(PARTS, part);
    const done = json(path.join(dir, 'done.json'));
    if (!done) { pending(s, `P10.${part}`, `migrate.sh, ${part}`, `sin correr: ${how(part)}`); continue; }
    const res = json(path.join(dir, 'migrate.json')) || { checks: [], failures: ['sin migrate.json'] };
    s.facts.parts[part] = { duration_s: done.duration_s, exit: done.exit, checks: (res.checks || []).length, failures: res.failures || [], known: res.known || [], network_seconds: res.seconds };
    check(s, `P10.${part}.1`, `migrate.sh ${part}: «Migrar toda la red» con Lite, ${(res.checks || []).length} comprobaciones en verde${(res.known || []).length ? ` y ${(res.known || []).length} diferencias declaradas` : ''} (red en ${res.seconds || '?'} s; ${done.duration_s} s en total)`, done.exit === 0 && !(res.failures || []).length && (res.checks || []).length > 0, JSON.stringify((res.failures || []).slice(0, 8)));
    for (const k of res.known || []) note(s, `${part}: ${k.slice(0, 300)}`);
  }
};
