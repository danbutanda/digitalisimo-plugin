// F8, frente B: los controles de estilo heredados (`digitalisimo-migration/legacy/maps/*.styles.php`) no apuntan a nada
// que no exista y ningún control vivo se perdió al corregirlos.
// - 0 selectores muertos: cada clase digi-*/elementor-*/ep-* de un selector aparece literalmente en el PHP o el JS de
//   Elements o de Migración, o casa con un patrón de tests/fixtures/legacy-styles-dynamic.json, cuya evidencia
//   (archivo, línea, texto) sigue en su sitio. Ningún `.bdt-`.
// - Guarda frente a la instantánea de 1.0.20 (tests/fixtures/f8-legacy-styles-baseline.json, sin historial de git):
//   un control de los demás mapas sólo cambia si estaba muerto, y el cambio es exactamente la poda de
//   tests/fixtures/f8-legacy-styles-dead.json; en los 6 mapas corregidos y Advanced Icon Box, ninguno vivo se retira.
// - Las clases corregidas de los 6 mapas, los 4 controles hover del distintivo de Advanced Icon Box, el mapa y el
//   adaptador de `bdt-mega-menu`, los IDs del Addon (`moved-to-addon.php`) y los valores por defecto de Prime Slider.
// - Si hay python3, el generador (`legacy-styles.py --check` y `rewrite()`) da lo mismo que esta prueba.
// Uso: (ulimit -v 3000000; timeout 150 node --max-old-space-size=600 tests/migration-legacy-styles.mjs)
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, existsSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  parsePhp, hash, loadStyles, corpus, loadDynamic, selectorParts, deadClasses, classesOf, prune, checked, splitSelector,
} from './fixtures/legacy-styles/styles-lib.mjs';

const root = fileURLToPath(new URL('..', import.meta.url));
const MAPS = join(root, 'digitalisimo-migration/legacy/maps');
const MERGE = ['bdt-fancy-slider', 'bdt-trailer-box', 'bdt-advanced-progress-bar', 'bdt-animated-card', 'bdt-interactive-card', 'bdt-profile-card', 'bdt-advanced-icon-box'];
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const read = (rel) => readFileSync(join(root, rel), 'utf8');

const baseline = JSON.parse(read('tests/fixtures/f8-legacy-styles-baseline.json'));
const dead = JSON.parse(read('tests/fixtures/f8-legacy-styles-dead.json'));
const dynamicFile = JSON.parse(read('tests/fixtures/legacy-styles-dynamic.json'));
const styles = loadStyles(root);
const tokens = corpus(root);
const dynamic = loadDynamic(root);

// --- Instantánea y archivos -----------------------------------------------------------------------------------------
ok(Object.keys(baseline.maps).length === 201 && baseline.control_count === 6423, 'La línea base es la de 1.0.20: 201 mapas y 6 423 controles');
ok(Object.values(baseline.maps).reduce((n, list) => n + list.length, 0) === 6423, 'La línea base lista los 6 423 controles');
ok(Object.values(baseline.maps).every((list) => list.every(([name, sha]) => typeof name === 'string' && /^[0-9a-f]{64}$/.test(sha))), 'Cada control de la línea base es [name, sha256]');
const mapIds = readdirSync(MAPS).filter((f) => f.endsWith('.php') && !f.endsWith('.styles.php')).map((f) => f.slice(0, -4)).sort();
ok(JSON.stringify(Object.keys(styles).sort()) === JSON.stringify(mapIds), 'Cada mapa tiene su .styles.php y cada .styles.php su mapa');
ok(Object.keys(baseline.maps).every((id) => id in styles), 'Ningún .styles.php de la línea base desaparece');
ok(JSON.stringify(Object.keys(styles).filter((id) => !(id in baseline.maps))) === JSON.stringify(['bdt-mega-menu']), 'El único mapa nuevo es bdt-mega-menu');

// --- 0 selectores muertos, ningún .bdt-, siempre acotados al widget ------------------------------------------------
let total = 0;
for (const [id, list] of Object.entries(styles)) {
  const deadHere = [];
  const shapeErrors = [];
  for (const control of list) {
    total++;
    if (!control.name || typeof control.name !== 'string') shapeErrors.push('sin nombre');
    if ('group' in control) {
      if (!String(control.selector || '').startsWith('{{WRAPPER}}')) shapeErrors.push(`${control.name}: grupo sin {{WRAPPER}}`);
    } else if (!control.selectors || !Object.keys(control.selectors).length) {
      shapeErrors.push(`${control.name}: sin selectores`);
    }
    if (JSON.stringify(control).includes('.bdt-')) shapeErrors.push(`${control.name}: nombra .bdt-`);
    for (const { part } of selectorParts(control)) {
      if (!part.includes('{{WRAPPER}}') && !part.includes('{{ID}}')) shapeErrors.push(`${control.name}: ${part} sin {{WRAPPER}}`);
      const d = deadClasses(part, tokens, dynamic);
      if (d.length) deadHere.push(`${control.name}: ${d.join(', ')}`);
    }
  }
  ok(shapeErrors.length === 0, `${id}: ${shapeErrors.slice(0, 3).join(' · ')}`);
  ok(deadHere.length === 0, `${id}: selectores muertos ${deadHere.slice(0, 4).join(' · ')}`);
}
ok(total > 5000, `Quedan ${total} controles de estilo`);

// --- Patrones dinámicos con evidencia -------------------------------------------------------------------------------
ok(dynamicFile.patterns.length >= 30, 'Hay patrones dinámicos para las clases armadas por concatenación');
for (const p of dynamicFile.patterns) {
  ok(/^\^.*\$$/.test(p.pattern) && !/\.\*|\.\+/.test(p.pattern) && p.evidence.length > 0, `${p.pattern}: anclado, sin comodines y con evidencia`);
  const missing = p.evidence.filter((e) => {
    const lines = read(e.file).split('\n');
    return !(lines[e.line - 1] || '').includes(e.contains);
  });
  ok(missing.length === 0, `${p.pattern}: evidencia que ya no está en su línea: ${missing.map((e) => `${e.file}:${e.line}`).join(', ')}`);
}
// Ningún patrón da por viva una clase inventada por el error del generador (sufijo con guion simple sobre el bloque).
ok(!dynamic.some((p) => p.re.test('digi-fancy-card-pre-title') || p.re.test('digi-creative-button--anthe') || p.re.test('digi-brand-grid__list-checkbox')), 'Los patrones no casan con clases inventadas');
ok(dynamic.some((p) => p.re.test('digi-logo-grid--tictactoe')) && dynamic.some((p) => p.re.test('digi-advanced-button--effect-g')) && dynamic.some((p) => p.re.test('digi-advanced-icon-box--bottom')), 'Los modificadores reales sí casan');

// --- Guarda frente a la línea base ----------------------------------------------------------------------------------
const deadMaps = dead.maps;
const keyOf = (name, index) => `${name}#${index}`;
for (const [id, base] of Object.entries(baseline.maps)) {
  const entry = deadMaps[id] || { controls: [], classes: {}, mode: 'none' };
  const rows = new Map(entry.controls.map((row) => [keyOf(row.name, row.index), row]));
  const problems = [];
  // Cada fila describe un control real de la línea base y su clase muerta lo está de verdad.
  const seen = {};
  const baseKeys = new Map(base.map(([name, sha]) => { const n = seen[name] = (seen[name] ?? -1) + 1; return [keyOf(name, n), sha]; }));
  for (const row of entry.controls) {
    if (baseKeys.get(keyOf(row.name, row.index)) !== hash(row.before)) problems.push(`${row.name}: before no es el de la línea base`);
    // En los 6 mapas y Advanced Icon Box también cambian controles vivos (clases corregidas): sólo una baja exige clase muerta.
    const needsDead = entry.mode === 'prune' || (row.after === null && !(row.unmatched || []).length);
    if (entry.mode === 'prune' && row.unmatched) problems.push(`${row.name}: una poda no usa el marcado de destino`);
    if ((needsDead && !row.dead.length) || !row.dead.every((cls) => !tokens.has(cls) && !dynamic.some((p) => p.re.test(cls)) && checked(cls))) problems.push(`${row.name}: clase no muerta en dead`);
    const beforeClasses = new Set(selectorParts(row.before).flatMap(({ part }) => classesOf(part)));
    if (!row.dead.every((cls) => beforeClasses.has(cls))) problems.push(`${row.name}: dead no está en sus selectores`);
  }
  if (MERGE.includes(id)) {
    ok(entry.mode === 'merge', `${id}: se corrigió con --merge`);
    // Nunca se retira un control vivo: lo que falta estaba muerto entero (su poda no deja nada).
    const now = new Set(styles[id].map((c) => c.name));
    for (const key of baseKeys.keys()) {
      const name = key.replace(/#\d+$/, '');
      if (now.has(name)) continue;
      const row = rows.get(key);
      // Muerto por sus clases o, en lo que no casa con el marcado de destino, por estructura (lo mide la prueba en Chrome).
      const structural = new Set(row?.unmatched || []);
      const deadEntirely = row && selectorParts(row.before).every(({ part }) => deadClasses(part, tokens, dynamic).length || structural.has(part));
      if (!row || row.after !== null || !deadEntirely) problems.push(`${name}: retirado sin estar muerto`);
    }
  } else {
    // Mismo orden; un control cambiado o retirado es exactamente su poda.
    const current = styles[id];
    let i = 0;
    const counter = {};
    for (const [name, sha] of base) {
      const n = counter[name] = (counter[name] ?? -1) + 1;
      const row = rows.get(keyOf(name, n));
      if (row && row.after === null) {
        if (prune(row.before, tokens, dynamic) !== null) problems.push(`${name}: retirado con selectores vivos`);
        continue;
      }
      const control = current[i++];
      if (!control || control.name !== name) { problems.push(`${name}: fuera de orden`); break; }
      if (hash(control) === sha) continue;
      const pruned = row && prune(row.before, tokens, dynamic);
      if (!row || !pruned || hash(pruned) !== hash(control) || row.after !== hash(control)) problems.push(`${name}: cambió sin ser la poda de lo muerto`);
    }
    if (i !== current.length) problems.push(`${current.length - i} controles de más`);
    if (entry.controls.length) ok(entry.mode === 'prune', `${id}: sólo se podó`);
  }
  ok(problems.length === 0, `${id}: ${problems.slice(0, 4).join(' · ')}`);
}
const deadClassUnion = new Set(Object.values(deadMaps).flatMap((e) => e.controls.flatMap((c) => c.dead)));
ok(Object.values(deadMaps).every((e) => JSON.stringify(Object.keys(e.classes).sort()) === JSON.stringify([...new Set(e.controls.flatMap((c) => c.dead))].sort())), 'Cada mapa de dead.json lista con evidencia las clases de sus controles');
ok(Object.values(deadMaps).every((e) => Object.values(e.classes).every((ev) => ev.literal === false && ev.dynamic === false && Array.isArray(ev.near))), 'La evidencia de cada clase muerta dice por qué y qué hay cerca');
ok(deadClassUnion.size > 150 && Object.keys(deadMaps).length > 40, `dead.json documenta ${deadClassUnion.size} clases en ${Object.keys(deadMaps).length} mapas`);
ok(!Object.keys(deadMaps).some((id) => !(id in baseline.maps)), 'dead.json sólo habla de mapas de la línea base');

// --- Marcado de destino de los 6 mapas y Advanced Icon Box (tests/fixtures/legacy-styles/*.html) --------------------
const FIXTURES = { 'bdt-fancy-slider': 'digitalisimo-fancy-slider', 'bdt-trailer-box': 'digitalisimo-fancy-card', 'bdt-animated-card': 'digitalisimo-fancy-card', 'bdt-interactive-card': 'digitalisimo-fancy-card', 'bdt-advanced-progress-bar': 'digitalisimo-progress-bars', 'bdt-profile-card': 'author-box', 'bdt-advanced-icon-box': 'digitalisimo-advanced-icon-box' };
for (const fixture of new Set(Object.values(FIXTURES))) {
  const html = read(`tests/fixtures/legacy-styles/${fixture}.html`);
  const sources = [...new Set([...html.matchAll(/data-sources="([^"]+)"/g)].flatMap((m) => m[1].split(' ')))];
  ok(sources.length > 0 && sources.every((f) => existsSync(join(root, f))), `${fixture}.html dice de qué archivos sale su marcado`);
  const own = new Set(sources.flatMap((f) => [...read(f).matchAll(/(?<![A-Za-z0-9_-])((?:digi|elementor)-[A-Za-z0-9_-]+)/g)].map((m) => m[1])));
  const stems = [...own].filter((t) => /[-_]$/.test(t));
  const classes = [...new Set([...html.matchAll(/class="([^"]+)"/g)].flatMap((m) => m[1].split(/\s+/)))].filter((c) => /^(?:digi|elementor)-/.test(c) && !/^elementor-(?:element|widget)(?:-|$)/.test(c));
  const missing = classes.filter((c) => !own.has(c) && !stems.some((t) => c.startsWith(t)) && !dynamic.some((p) => p.re.test(c)));
  ok(missing.length === 0, `${fixture}.html: clases que su PHP no imprime: ${missing.join(', ')}`);
}
for (const [id, fixture] of Object.entries(FIXTURES)) ok(read(`digitalisimo-migration/legacy/maps/${id}.php`).includes(`'target'`) && new RegExp(`'target'\\s*=>\\s*'${fixture}'`).test(read(`digitalisimo-migration/legacy/maps/${id}.php`)), `${id} va a ${fixture}`);

// --- Los 6 mapas: clases corregidas ---------------------------------------------------------------------------------
const mapClasses = (id) => {
  const text = readFileSync(join(MAPS, `${id}.php`), 'utf8');
  const m = /^\t'classes'\s*=>\s*(array\((?:[^\n]*\),[ \t]*$|\n[\s\S]*?\n\t\)))/m.exec(text);
  return m ? parsePhp('return ' + m[1] + ';', id) : {};
};
const expectClasses = {
  'bdt-fancy-slider': { '.bdt-ep-fancy-slider-button a': '.digi-fancy-slider__button', '.bdt-ep-fancy-slider-subtitle': '.digi-fancy-slider__subtitle', '.bdt-ep-fancy-slider-text': '.digi-fancy-slider__description', '.bdt-ep-fancy-slider-content': '.digi-fancy-slider__content', '.bdt-ep-fancy-slider-item': '.digi-fancy-slider__slide', '.bdt-ep-fancy-slider-img': '.digi-fancy-slider__media' },
  'bdt-trailer-box': { '.elementor-widget-bdt-trailer-box': '', '.bdt-trailer-box-desc-inner .bdt-trailer-box-pre-title': '.digi-fancy-card__badge', '.bdt-trailer-box-desc-inner .bdt-trailer-box-title': '.digi-fancy-card__title', '.bdt-trailer-box-pre-title': '.digi-fancy-card__badge', '.bdt-trailer-box-text': '.digi-fancy-card__description', 'a.bdt-trailer-box-button': 'a.digi-fancy-card__link', '.bdt-trailer-box-button': '.digi-fancy-card__link' },
  'bdt-advanced-progress-bar': { '.bdt-ep-advanced-progress-bar-parcentage': '.digi-progress__value', '.bdt-ep-advanced-progress-bar-level': '.digi-progress__track', '.bdt-ep-advanced-progress-bar-content': '.digi-progress__head' },
  'bdt-animated-card': { '.bdt-ep-animated-card-btn': '.digi-fancy-card__link', '.bdt-ep-animated-card-img-wrap': '.digi-fancy-card__visual', '.bdt-ep-animated-card-img': '.digi-fancy-card__image', '.bdt-ep-animated-card-text': '.digi-fancy-card__description' },
  'bdt-interactive-card': { '.bdt-interactive-card-text': '.digi-fancy-card__description', '.bdt-interactive-card-badge span': '.digi-fancy-card__badge' },
  'bdt-profile-card': { '.bdt-profile-card .bdt-profile-card-name-info .bdt-name': '.elementor-author-box__name', '.bdt-profile-card .bdt-profile-card-name-info .bdt-username': '.digi-author-box__username', '.bdt-profile-card .bdt-profile-card-status .bdt-profile-stat': '.digi-author-box__stat', '.bdt-profile-card .bdt-profile-card-status .bdt-profile-label': '.digi-author-box__stat-label', '.bdt-profile-card .bdt-profile-card-share-link a': '.digi-author-box__social-link', '.bdt-profile-card-pro span': '.digi-author-box__badge', '.bdt-profile-card .bdt-profile-card-header': '.digi-author-box__header', '.bdt-profile-card .bdt-dropdown-nav>li>a': '.digi-author-box__menu-link', '.bdt-profile-card .bdt-profile-card-inner': '.elementor-author-box' },
};
const wrongKeys = {
  'bdt-fancy-slider': ['.bdt-ep-fancy-slider-sub-title', '.bdt-ep-fancy-slider-description'],
  'bdt-trailer-box': ['.bdt-trailer-box-sub-title', '.bdt-trailer-box-description', '.bdt-trailer-box-readmore', '.bdt-trailer-box-image', '.bdt-trailer-box-badge'],
  'bdt-advanced-progress-bar': ['.bdt-ep-advanced-progress-bar-bar', '.bdt-ep-advanced-progress-bar-percentage'],
  'bdt-animated-card': ['.bdt-ep-animated-card-readmore', '.bdt-ep-animated-card-image', '.bdt-ep-animated-card-description', '.bdt-ep-animated-card-badge'],
  'bdt-interactive-card': ['.bdt-interactive-card-description'],
  'bdt-profile-card': ['.bdt-profile-card .bdt-profile-card-name'],
};
for (const [id, pairs] of Object.entries(expectClasses)) {
  const classes = mapClasses(id);
  for (const [from, to] of Object.entries(pairs)) ok(classes[from] === to, `${id}: ${from} → ${to}`);
  ok(wrongKeys[id].every((key) => !(key in classes)), `${id}: sin las claves que no existen en el render (${wrongKeys[id].join(', ')})`);
  for (const to of Object.values(classes)) {
    const bad = splitSelector(to).flatMap((part) => deadClasses(part, tokens, dynamic));
    ok(bad.length === 0, `${id}: destino inexistente ${bad.join(', ')}`);
  }
}

// --- Lo que vuelve a funcionar en los 6 mapas y Advanced Icon Box ---------------------------------------------------
const control = (id, name) => styles[id].find((c) => c.name === name);
const sels = (c) => (c ? ('group' in c ? [c.selector] : Object.keys(c.selectors)) : []).join(' | ');
const expectSelector = [
  ['bdt-fancy-slider', 'sub_title_color', '.digi-fancy-slider__subtitle'],
  ['bdt-fancy-slider', 'description_typography', '.digi-fancy-slider__description'],
  ['bdt-fancy-slider', 'button_text_color', '{{WRAPPER}} .digi-fancy-slider__button'],
  ['bdt-fancy-slider', 'item_background', '.digi-fancy-slider__slide'],
  ['bdt-trailer-box', 'pre_title_color', '.digi-fancy-card__badge'],
  ['bdt-trailer-box', 'text_color', '.digi-fancy-card__description'],
  ['bdt-trailer-box', 'button_text_color', '.digi-fancy-card__link'],
  ['bdt-advanced-progress-bar', 'percentage_color', '.digi-progress__value'],
  ['bdt-advanced-progress-bar', 'height', '.digi-progress__track'],
  ['bdt-animated-card', 'readmore_text_color', '.digi-fancy-card__link'],
  ['bdt-animated-card', 'description_color', '.digi-fancy-card__description'],
  ['bdt-interactive-card', 'badge_text_color', '.digi-fancy-card__badge'],
  ['bdt-interactive-card', 'description_color', '.digi-fancy-card__description'],
  ['bdt-profile-card', 'profile_card_username_color', '.digi-author-box__username'],
  ['bdt-profile-card', 'profile_card_name_color', '.elementor-author-box__name'],
  ['bdt-advanced-icon-box', 'icon_space', '.digi-advanced-icon-box--right .digi-advanced-icon-box__visual'],
];
for (const [id, name, needle] of expectSelector) {
  const c = control(id, name);
  ok(c && sels(c).includes(needle) && !/__button a\b|__badge span\b/.test(sels(c)), `${id}: ${name} apunta a ${needle} (hoy: ${sels(c) || 'no está'})`);
}
for (const name of ['badge_text_hover_color', 'badge_background_hover', 'badge_border_hover_color', 'badge_shadow_hover']) {
  const c = control('bdt-advanced-icon-box', name);
  ok(c && sels(c) === '{{WRAPPER}}:hover .digi-advanced-icon-box__badge', `Advanced Icon Box gana ${name} (hover del distintivo de 10.0.1)`);
}
ok(!JSON.stringify(styles['bdt-advanced-icon-box']).includes('elementor-position-'), 'Advanced Icon Box usa el modificador propio de posición, no el prefix_class de Element Pack');

// --- bdt-mega-menu → nav-menu ---------------------------------------------------------------------------------------
const mega = read('digitalisimo-migration/legacy/maps/bdt-mega-menu.php');
ok(/'target'\s*=>\s*'nav-menu'/.test(mega) && /'rename'\s*=>\s*array\(\s*'navbar'\s*=>\s*'menu'/.test(mega), 'bdt-mega-menu va a nav-menu con el mismo menú (navbar → menu)');
ok(/'show_hamburger_menu'\s*=>\s*'dropdown'/.test(mega) && /'ep_megamenu_direction'\s*=>\s*'layout'/.test(mega), 'Dirección y punto de corte del menú móvil se conservan');
const adapters = read('digitalisimo-migration/legacy/class-adapters.php');
ok(/^\t\t'bdt-mega-menu' => Bdt_MegaMenu::class,$/m.test(adapters) && /final class Bdt_MegaMenu extends \\ElementorPro\\Modules\\NavMenu\\Widgets\\Nav_Menu \{ use Legacy_Adapter; const LEGACY_ID = 'bdt-mega-menu'; \}/.test(adapters), 'bdt-mega-menu tiene su clase adaptadora');
ok(styles['bdt-mega-menu'].length >= 30 && sels(control('bdt-mega-menu', 'menu_item_typography')).includes('.elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item'), 'Los estilos del Mega Menu van al menú de PRO');
ok(!JSON.stringify(styles['bdt-mega-menu']).match(/\.(?:ep|bdt)-|offcanvas/), 'El panel lateral y las clases de Element Pack no pasan al Mega Menu');

// La traducción real del Mega Menu (Translator::translate con el mapa), si hay PHP (en CI) o php-wasm (local).
// PHP_BIN manda (el trabajo «PHP 7.4» de la CI la fija); si no, el `php` del sistema.
const phpCmd = (() => {
  if (process.env.PHP_BIN) return process.env.PHP_BIN.split(' ');
  if (!spawnSync('php', ['-v'], { encoding: 'utf8' }).error) return ['php'];
  const wasm = join(homedir(), '.npm/_npx/98db5c2db358871c/node_modules/.bin/php-wasm-cli');
  return existsSync(wasm) ? [process.execPath, wasm] : null;
})();
if (!phpCmd) {
  console.log('AVISO: sin PHP, no se traduce un Mega Menu de ejemplo.');
} else {
  const dir = mkdtempSync(join(process.env.DIGI_TMP || tmpdir(), 'legacy-styles-'));
  const file = join(dir, 'mega.php');
  writeFileSync(file, `<?php
define( 'ABSPATH', __DIR__ );
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
require ${JSON.stringify(join(root, 'digitalisimo-migration/legacy/class-translator.php'))};
use Digitalisimo\\Elements\\Legacy\\Translator;
echo json_encode( array(
  Translator::translate( 'bdt-mega-menu', array( 'navbar' => 'principal', 'ep_megamenu_mode' => 'click', 'offcanvas_overlay' => 'yes', 'template_id' => 9, 'menu_text_color' => '#123456' ) ),
  Translator::translate( 'bdt-mega-menu', array( 'ep_megamenu_direction' => 'vertical', 'show_hamburger_menu' => 'tablet' ) ),
  Translator::translate( 'bdt-mega-menu', array( 'navbar' => '0', 'show_hamburger_menu' => 'a b<' ) ),
  Translator::translate( 'bdt-mega-menu', array( 'navbar' => 'x" onload="y' ) ),
) );
`);
  const run = spawnSync(phpCmd[0], [...phpCmd.slice(1), file], { encoding: 'utf8', timeout: 120000 });
  rmSync(dir, { recursive: true, force: true });
  const json = (run.stdout.match(/\[\{.*\}\]/s) || [])[0];
  const [one, two, three, four] = JSON.parse(json || '[]');
  ok(one && one.menu === 'principal' && one.layout === 'horizontal' && one.dropdown === 'mobile' && one.pointer === 'none' && one.menu_text_color === '#123456', `Mega Menu: mismo menú, horizontal y menú móvil desde «mobile» (${run.stderr.slice(-200)})`);
  ok(one && !('ep_megamenu_mode' in one) && !('offcanvas_overlay' in one) && !('template_id' in one) && !('navbar' in one), 'Mega Menu: sin las opciones de Element Pack que no existen en el destino');
  ok(two && two.layout === 'vertical' && two.dropdown === 'tablet', 'Mega Menu: dirección vertical y punto de corte guardados');
  ok(three && three.menu === '' && three.dropdown === 'mobile' && four && four.menu === '', 'Mega Menu: «Select Menu» (0), un slug no válido o un punto de corte inventado no pasan');
}

// --- IDs del Addon --------------------------------------------------------------------------------------------------
const inventory = JSON.parse(read('docs/element-pack-inventory.json')).widgets;
const moved = inventory.filter((w) => w.moved_to_addon).map((w) => w.legacy_widget_id).sort();
const movedFile = read('digitalisimo-migration/legacy/moved-to-addon.php');
const listed = [...movedFile.matchAll(/^\t'(bdt-[a-z0-9-]+)',$/gm)].map((m) => m[1]).sort();
ok(moved.length === 88 && JSON.stringify(listed) === JSON.stringify(moved), `moved-to-addon.php lista los ${moved.length} IDs movidos al Addon`);
ok(/defined\( 'ABSPATH' \) \|\| exit;/.test(movedFile) && /^return array\($/m.test(movedFile), 'moved-to-addon.php sólo devuelve datos');

// --- Prime Slider: inventario y valores por defecto (C9) ------------------------------------------------------------
const ps = JSON.parse(read('docs/prime-slider-inventory.json'));
const psIds = ps.widgets.map((w) => w.legacy_widget_id);
ok(ps.widgets.length === 57 && new Set(psIds).size === 57 && psIds.every((id) => /^prime-slider-[a-z0-9-]+$/.test(id)), 'El inventario de Prime Slider tiene 57 IDs únicos');
ok(ps.widgets.filter((w) => w.plugin === 'lite').length === 22 && ps.widgets.filter((w) => w.plugin === 'pro').length === 35, '22 de Lite y 35 de Pro');
ok(ps.widgets.every((w) => ['on', 'off'].includes(w.module_default) && Array.isArray(w.skins)), 'Cada widget dice si su módulo viene activo y sus skins');
const skins = Object.fromEntries(ps.widgets.map((w) => [w.legacy_widget_id, w.skins]));
ok(JSON.stringify(skins['prime-slider-general']) === JSON.stringify(['crelly', 'meteor', 'slide']) && JSON.stringify(skins['prime-slider-blog']) === JSON.stringify(['coral', 'folio', 'zinest']) && JSON.stringify(skins['prime-slider-isolate']) === JSON.stringify(['locate', 'slice']), 'Las 8 skins de Lite');
const DEFAULTS = join(root, 'digitalisimo-migration/legacy/prime-slider/defaults');
const defaultFiles = readdirSync(DEFAULTS).filter((f) => f.endsWith('.php')).sort();
ok(JSON.stringify(defaultFiles.map((f) => f.slice(0, -4))) === JSON.stringify([...psIds].sort()), 'Un archivo de valores por defecto por ID');
let withRows = 0;
for (const file of defaultFiles) {
  const id = file.slice(0, -4);
  const text = readFileSync(join(DEFAULTS, file), 'utf8');
  const data = parsePhp(text.replace(/\$placeholder\b/g, "'{{placeholder}}'"), file);
  const meta = ps.widgets.find((w) => w.legacy_widget_id === id);
  ok(data.id === id && data.plugin === meta.plugin && data.version === '4.7.0' && JSON.stringify(data.skins) === JSON.stringify(meta.skins), `${id}: cabecera C9`);
  ok(data.controls && typeof data.controls === 'object' && !Array.isArray(data.controls) && Object.keys(data.controls).length > 0 && (Array.isArray(data.repeaters) ? data.repeaters.length === 0 : typeof data.repeaters === 'object') && (Array.isArray(data.styles) ? data.styles.length === 0 : typeof data.styles === 'object'), `${id}: controls, repeaters y styles`);
  const json = JSON.stringify(data);
  // Los valores del proveedor van tal cual (los limpia la conversión), pero nunca una ruta a la carpeta del plugin ni
  // una constante sin resolver: los recursos van como {{ps_assets}}.
  ok(!/wp-content\/plugins|plugins\/bdthemes|BDTPS_|Utils::/.test(json), `${id}: sin carpeta de plugin ni constantes sin resolver`);
  const bad = Object.values(data.repeaters || {}).filter((r) => !r || typeof r.fields !== 'object' || !Array.isArray(r.rows));
  ok(bad.length === 0, `${id}: cada repetidor con fields y rows`);
  if (Object.values(data.repeaters || {}).some((r) => r.rows.length)) withRows++;
  ok(Object.values(data.styles || {}).every((s) => s && typeof s.type === 'string' && (s.selectors || s.selector)), `${id}: cada estilo con tipo y selectores`);
}
const general = parsePhp(readFileSync(join(DEFAULTS, 'prime-slider-general.php'), 'utf8').replace(/\$placeholder\b/g, "'{{placeholder}}'"));
ok(general.controls.autoplay === 'yes' && general.controls.autoplay_interval === 7000 && general.controls.finite === 'yes' && general.controls.show_title === 'yes', 'general: valores del rasgo Global_Widget_Controls');
ok(general.repeaters.slides && general.repeaters.slides.rows.length > 0 && general.repeaters.slides.fields.slide_button_text && general.repeaters.slides.fields.slide_button_text.__t === 'Read More', 'general: filas por defecto y texto traducible {"__t": "Read More"}');
ok(Object.keys(general.styles).length > 10, 'general: controles de estilo con sus selectores');
const blog = parsePhp(readFileSync(join(DEFAULTS, 'prime-slider-blog.php'), 'utf8').replace(/\$placeholder\b/g, "'{{placeholder}}'"));
ok(blog.controls.posts_limit === 3 && blog.controls.posts_source === 'post', 'blog: update_control de la consulta (posts_limit 3) sobre Group_Control_Query');
const woo = parsePhp(readFileSync(join(DEFAULTS, 'prime-slider-woocommerce.php'), 'utf8').replace(/\$placeholder\b/g, "'{{placeholder}}'"));
ok(woo.controls.posts_source === 'product' && woo.controls.posts_limit === 3, 'woocommerce: posts_source product y posts_limit 3');
ok(withRows >= 25, `${withRows} widgets traen filas por defecto`);
const assets = defaultFiles.filter((f) => readFileSync(join(DEFAULTS, f), 'utf8').includes("'{{ps_assets}}images/"));
ok(assets.length >= 20, `Las imágenes de demostración se marcan con {{ps_assets}} (${assets.length} widgets)`);
ok(defaultFiles.some((f) => /'url' => \$placeholder,/.test(readFileSync(join(DEFAULTS, f), 'utf8'))), 'La imagen de relleno de Elementor va como $placeholder');

// --- El generador da lo mismo (si hay python3) ----------------------------------------------------------------------
const py = spawnSync('python3', ['-I', join(root, 'scripts/legacy-styles.py'), '--check'], { encoding: 'utf8', timeout: 120000 });
if (py.error || py.status === null) {
  console.log('AVISO: sin python3, no se compara con scripts/legacy-styles.py.');
} else {
  ok(py.status === 0 && /^0 controles con selectores muertos/m.test(py.stdout), `legacy-styles.py --check coincide: ${py.stdout.slice(-200)}${py.stderr.slice(-300)}`);
  const unit = spawnSync('python3', ['-I', '-c', `
import importlib.util, json, sys
spec = importlib.util.spec_from_file_location('ls', sys.argv[1]); ls = importlib.util.module_from_spec(spec); spec.loader.exec_module(ls)
c = {'.bdt-trailer-box': '.digi-fancy-card', '.bdt-trailer-box-title': '.digi-fancy-card__title', '.elementor-widget-bdt-trailer-box': '', 'a.bdt-trailer-box-button': 'a.digi-fancy-card__link', '.bdt-x-button a': '.digi-x__button'}
cases = [
  '{{WRAPPER}} .bdt-trailer-box .bdt-trailer-box-pre-title',
  '{{WRAPPER}} .bdt-trailer-box .bdt-trailer-box-title',
  '{{WRAPPER}}.elementor-widget-bdt-trailer-box:hover a.bdt-trailer-box-button',
  '{{WRAPPER}} .elementor-widget-bdt-trailer-box:hover a.bdt-trailer-box-button',
  '{{WRAPPER}} .bdt-x-button a:hover',
  '{{WRAPPER}} .bdt-x-button abbr',
  '.bdt-trailer-box-title',
]
print(json.dumps([ls.rewrite(s, c) for s in cases]))
`, join(root, 'scripts/legacy-styles.py')], { encoding: 'utf8', timeout: 60000 });
  const got = JSON.parse(unit.stdout || 'null');
  ok(Array.isArray(got) && got[0] === null, 'rewrite() respeta el límite de clase: .bdt-trailer-box no convierte .bdt-trailer-box-pre-title');
  ok(got && got[1] === '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title', 'rewrite() traduce las clases con entrada');
  ok(got && got[2] === '{{WRAPPER}}:hover a.digi-fancy-card__link' && got[3] === '{{WRAPPER}}:hover a.digi-fancy-card__link', 'rewrite(): la entrada vacía quita la clase del envoltorio y su espacio');
  ok(got && got[4] === '{{WRAPPER}} .digi-x__button:hover' && got[5] === null, 'rewrite(): «-button a» es el enlace, pero no «-button abbr»');
  ok(got && got[6] === null, 'rewrite(): sin {{WRAPPER}} no se reproduce');
}

// --- Los generadores leen los ZIP sin extraerlos --------------------------------------------------------------------
const controls = read('scripts/legacy_controls.py');
ok(/from reference_archive import ReferenceArchive/.test(controls) && !/reference' \/ 'bdthemes-element-pack'/.test(controls) && /DIGITALISIMO_REFERENCE_DIR/.test(controls), 'legacy_controls.py lee los ZIP con ReferenceArchive (o DIGITALISIMO_REFERENCE_DIR)');
for (const script of ['legacy-styles.py', 'legacy-defaults.py', 'legacy-map-skeleton.py', 'legacy-posts-maps.py', 'legacy-shortcode-maps.py', 'prime-slider-defaults.py']) {
  const text = read(`scripts/${script}`);
  ok(!/ROOT \/ inventory\[|ROOT \/ next\(w\['source_file'\]|'reference' \/ 'bdthemes|subprocess\.run\(\['php'|\/tmp\//.test(text), `${script}: no lee la carpeta extraída ni ejecuta PHP ni usa /tmp`);
}

// --- legacy-compare.mjs: `_known.alt` sólo excusa el texto alternativo, nunca el archivo de la imagen -----------------
{
  const { comparable, altNote } = await import('./playground/legacy-compare.mjs');
  const a = { known: { alt: 'decorativa junto al título' }, signature: { images: ['coco-can.svg | alt=Servicio'] } };
  ok(JSON.stringify(comparable(a, 'images', a.signature.images)) === JSON.stringify(['coco-can.svg']) && JSON.stringify(comparable(a, 'images', ['placeholder.png | alt='])) === JSON.stringify(['placeholder.png']), 'legacy-compare: con _known.alt se compara el archivo de cada imagen (otra imagen sigue siendo diferencia)');
  ok(/texto alternativo distinto/.test(altNote(a, { signature: { images: ['coco-can.svg | alt='] } }, 'C') || '') && null === altNote(a, { signature: { images: ['coco-can.svg | alt=Servicio'] } }, 'C'), 'legacy-compare: el ALT distinto queda como nota declarada');
  const both = { known: { alt: 'x', images: 'y' }, signature: { images: ['a.svg | alt=A'] } };
  ok(comparable(both, 'images', both.signature.images)[0] === 'a.svg | alt=A' && comparable(a, 'text', ['T | alt=x'])[0] === 'T | alt=x', 'legacy-compare: sin efecto con _known.images ni en textos');
}

console.log(`Migración: ${checks} comprobaciones de los mapas de estilo heredados (${total} controles en ${Object.keys(styles).length} mapas, 0 selectores muertos).`);
