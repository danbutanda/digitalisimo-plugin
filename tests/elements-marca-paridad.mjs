#!/usr/bin/env node
// Marca propia (docs/MARCA_PROPIA.md): el JS transformado al empaquetar (scripts/marca-transform.mjs) y lo que el
// servidor transforma al vuelo (Digitalisimo\Elements\Marca\Transform: JS en línea, configuración del editor, respuestas
// de AJAX y de la API) tienen que dar EXACTAMENTE lo mismo. Si no, el JS busca un nombre y el HTML o la configuración
// traen otro: el front y el editor se rompen con la marca encendida.
//
// Compara, con PHP (PHP_BIN, por defecto `php`):
//   - cadenas: un juego fijo de casos y TODAS las cadenas con «elementor» de los bundles del front y del editor del
//     núcleo y de PRO, también las URL absolutas (B-4 de la revisión F2: las del proveedor salen intactas en los dos;
//     sólo se dejan fuera las rutas relativas `/…` y los `.php`, que el servidor trata como URL de su sitio);
//   - identificadores;
//   - fragmentos de JS (léxico: cadenas, plantillas, expresiones regulares, comentarios).
// Además, que lo que da el JS no deje «elementor» y que las clases coincidan con las del CSS (`.elementor-x` → `.dg-x`).
import { readFileSync, readdirSync, statSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';
import * as T from '../scripts/marca-transform.mjs';
const { loadRules, transformStr, transformIdent, transformJs, transformCss, lexJs } = T;
// Sin splitVendor (código anterior a la revisión F2), nada cuenta como del proveedor: las comprobaciones B-4 fallan solas.
const splitVendor = T.splitVendor || ((t) => [{ text: t, vendor: false }]);

const ROOT = new URL('..', import.meta.url).pathname;
const EL = join(ROOT, 'digitalisimo-elements');
const PHP = process.env.PHP_BIN || 'php';
const rules = loadRules();
let fails = 0;
let checks = 0;
const check = (ok, what, detail = '') => { checks++; if (!ok) { fails++; console.log(`FALLA ${what}${detail ? ' · ' + String(detail).slice(0, 400) : ''}`); } };

// 1) Casos fijos.
const fixed = [
  '.elementor-widget', 'elementor', 'elementor-pro', 'elementor/frontend/init', 'elementor-pro/motion-fx/recalc',
  'elementor/v1/globals', 'elementor-pro/v1', 'elementor-one/v1', 'elementor_ajax', 'elementor_library',
  '_elementor_edit_mode', 'elementor_canvas', 'tmpl-elementor-panel', '#elementor-panel', 'elementorFrontendConfig',
  'ElementorConfig', 'Elementor', 'Edit with Elementor', 'ELEMENTOR_VERSION', 'e-elementor-x', 'eicon-elementor',
  '#elementor-action:action=lightbox', '%23elementor-action', 'data-elementor-id', '[data-elementor-type="popup"]',
  'elementor-element elementor-element-abc', '.elementor-kit-9 .elementor-button', 'elementor_pro_forms_send_form',
  'admin.php?page=elementor-tools', 'post.php?post=1&action=elementor', 'elementorProFrontend.config', 'elementOrSelector',
  '{{WRAPPER}} .elementor-heading-title', 'elementor-preview', 'elementor_preview_link_v1', 'ElementorProFrontendConfig',
  // B-4: URL y hosts del proveedor (nunca `dg.com`), una URL de medios y una del escritorio.
  'https://go.elementor.com/x', 'https://my.elementor.com/feedback/api/v1', 'https://elementor.com', 'elementor.com',
  '.elementor.com', 'https://angie.elementor.com', 'https:\\/\\/assets.elementor.com\\/a.png', 'https://my.stg.elementor.red/x?a=elementor',
  'Visita https://go.elementor.com/ayuda y usa elementor-widget', 'https://ensayo.test/wp-content/uploads/elementor-banner.jpg',
  'https://ensayo.test/wp-admin/post.php?post=1&action=elementor', 'elementor.common.helpers',
];

// 2) Cadenas reales de los bundles (front y editor del núcleo y de PRO).
const walk = (d, o = []) => { for (const n of readdirSync(d)) { const p = join(d, n); if (statSync(p).isDirectory()) { if (n !== 'node_modules' && n !== 'vendor') walk(p, o); } else o.push(p); } return o; };
const bundles = [...walk(join(EL, 'elementor/assets/js')), ...walk(join(EL, 'assets/js'))].filter((f) => /\.min\.js$/.test(f));
const strings = new Set(fixed);
const ids = new Set(['elementor', 'elementorFrontend', 'ElementorConfig', 'elementorCommon', '__elementorChunks', 'handleElementorBreakpoints', 'elementOrSelector', 'ELEMENTOR_X', 'elementorProEditorConfig']);
for (const f of bundles) {
  const src = readFileSync(f, 'utf8');
  if (!/elementor/i.test(src)) continue;
  lexJs(src, (type, text) => {
    if ((type === 'str' || type === 'tpl') && /elementor/i.test(text) && text.length < 300 && !/^\/(?!\/)/.test(text) && !/\.php(\?|$)/.test(text)) strings.add(text);
    if (type === 'id' && /elementor/i.test(text)) ids.add(text);
    return text;
  });
}
const snippets = [
  "var a='elementor-'+b; elementorFrontend.hooks.addAction('frontend/element_ready/global',fn);",
  'x = `.elementor-element-${id} .elementor-widget`; y = /elementor-(\\d+)/.test(z) ? 1 : 2; // elementor',
  'if(a)/elementor/g.exec(b); var c = d / e / f; /* elementor */ g("elementor_ajax")',
  "jQuery(window).on('elementor/frontend/init',function(){elementorFrontend.hooks.addAction('frontend/element_ready/form.default',h)});",
  "wp.i18n.setLocaleData({'':{}}, 'elementor'); wp.i18n.setLocaleData({'':{}}, 'elementor-pro');",
  'const t = `a${ b ? `elementor-${c}` : "elementor" }z`; window.__elementorChunks = [];',
];

// 3) Lo mismo en PHP.
const dir = mkdtempSync(join(process.env.TMPDIR || tmpdir(), 'marca-paridad-'));
try {
  const input = { strings: [...strings], ids: [...ids], snippets };
  writeFileSync(join(dir, 'in.json'), JSON.stringify(input));
  writeFileSync(join(dir, 'run.php'), `<?php
define( 'ABSPATH', __DIR__ . '/' );
function apply_filters( $h, $v ) { return $v; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
require ${JSON.stringify(join(EL, 'modules/digitalisimo-marca/class-map.php'))};
require ${JSON.stringify(join(EL, 'modules/digitalisimo-marca/class-transform.php'))};
use Digitalisimo\\Elements\\Marca\\Transform;
Transform::set_rules( json_decode( file_get_contents( ${JSON.stringify(join(EL, 'marca/reglas.json'))} ), true ) );
$in = json_decode( file_get_contents( __DIR__ . '/in.json' ), true );
$out = array( 'strings' => array(), 'ids' => array(), 'snippets' => array() );
foreach ( $in['strings'] as $s ) { $out['strings'][] = Transform::str( $s ); }
foreach ( $in['ids'] as $s ) { $out['ids'][] = Transform::ident( $s ); }
foreach ( $in['snippets'] as $s ) { $out['snippets'][] = Transform::lex( $s ); }
echo json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
`);
  const php = JSON.parse(execFileSync(PHP, [join(dir, 'run.php')], { maxBuffer: 256 * 1024 * 1024 }).toString());
  let diffs = 0;
  input.strings.forEach((s, i) => { const js = transformStr(s, rules); if (js !== php.strings[i] && diffs++ < 20) check(false, `cadena «${s}»`, `JS ${js} · PHP ${php.strings[i]}`); });
  check(diffs === 0, `${input.strings.length} cadenas iguales en JS y PHP (${diffs} distintas)`);
  diffs = 0;
  input.ids.forEach((s, i) => { const js = transformIdent(s, rules); if (js !== php.ids[i] && diffs++ < 20) check(false, `identificador ${s}`, `JS ${js} · PHP ${php.ids[i]}`); });
  check(diffs === 0, `${input.ids.length} identificadores iguales (${diffs} distintos)`);
  input.snippets.forEach((s, i) => { const js = transformJs(s, rules); check(js === php.snippets[i], `fragmento ${i}`, `JS ${js} · PHP ${php.snippets[i]}`); });
} finally {
  rmSync(dir, { recursive: true, force: true });
}

// 4) Lo que da el JS: nada de «elementor» fuera de las URL y hosts del proveedor, ningún host `dg.com` (B-4) y las
// clases como en el CSS.
// Fuera de lo que se conserva a propósito: URL y hosts del proveedor, y nombres de campos que relee el servidor (B-5).
const noVendor = (t) => splitVendor(t).filter((x) => !x.vendor).map((x) => x.text).join(' ').replace(T.FIELD_RE || /$^/, ' ');
for (const s of strings) {
  if (/^(?:https?:)?(?:\\?\/){2}/i.test(s) && !splitVendor(s).some((x) => x.vendor)) continue; // URL de otro sitio: igual que en PHP
  const out = transformStr(s, rules);
  check(!/elementor/i.test(noVendor(out)), `sin «elementor»: ${s}`, out);
  check(!/[a-z0-9.-]*\bdg\.(com|cloud|red|io|net)\b/i.test(out), `sin host dg.*: ${s}`, out);
}
check(transformStr('https://go.elementor.com/x', rules) === 'https://go.elementor.com/x' && transformJs('trustedOrigins:[a||`https://angie.elementor.com`]', rules) === 'trustedOrigins:[a||`https://angie.elementor.com`]', 'B-4 los hosts del proveedor no cambian (ni el origen de confianza)');
for (const s of snippets) check(!/elementor/i.test(transformJs(s, rules)), `fragmento sin «elementor»: ${s}`);
const css = transformCss('.elementor-widget .elementor-kit-9 .eicon-elementor{color:red}', rules);
const js = transformJs("q('.elementor-widget .elementor-kit-9 .eicon-elementor')", rules);
check(js === `q('${css.split('{')[0]}')`, 'los selectores del JS son los del CSS', `${js} · ${css}`);
check(transformJs("e.dataset.elementorOpenLightbox", rules) === 'e.dataset.dgOpenLightbox', 'dataset como el atributo data-dg-*');
check(transformStr('elementor/frontend/init', rules) === 'digitalisimo/frontend/init', 'eventos como reglas.json (eventos_regla)');
check(transformStr('elementor', rules) === 'dg' && transformStr('elementor-pro', rules) === 'dg-pro', 'dominios de texto dg y dg-pro');

console.log(`${fails} fallos de ${checks} comprobaciones (${strings.size} cadenas, ${ids.size} identificadores de ${bundles.length} bundles).`);
process.exit(fails ? 1 : 0);
