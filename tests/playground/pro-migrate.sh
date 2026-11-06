#!/usr/bin/env bash
# Migración de los widgets de PRO de punta a punta (C0 de docs/PRO_WIDGETS_OBJECTIVE.md), en Playground con el núcleo
# incluido en DIGITALÍSIMO Elements y DIGITALÍSIMO Migración: un sitio con Blockquote de PRO en una página (uno anidado),
# en una plantilla de cabecera y en una revisión; el inventario de «Analizar», «Migrar» hasta terminar y --revert.
# Comprueba que la página y la cabecera pasan a la Cita (con el CSS personalizado traducido), que la revisión no se
# toca, que queda la copia original y que --revert devuelve el Blockquote de PRO. C1: los cinco carruseles de PRO. C2: los
# catorce widgets de contenido de PRO y una cuenta regresiva de Element Pack (`bdt-countdown`) que F8 lleva a la de PRO y
# la etapa `pro` a la propia en la misma pasada; --revert devuelve los de PRO y el de Element Pack. C3: los cuatro de
# navegación de PRO en una página (Mega Menu y Off-Canvas con sus contenedores hijos) y en una cabecera (Menú y
# Búsqueda), y el Navbar y el Mega Menu de Element Pack, que F8 lleva al Menú de PRO y la etapa `pro` al propio. C4: una
# plantilla de entrada del Theme Builder con los quince elementos de tema de PRO y un `bdt-post-info` de Element Pack
# (F8 → PRO → propio en la misma pasada).
# C5: los seis widgets de entradas y bucles de PRO (con una plantilla de Elemento de bucle y el filtro apuntando a la
# cuadrícula), unas Entradas de PRO en la cabecera y una rejilla de entradas y un Dynamic Grid de Element Pack y un Blog
# de Prime Slider que F8 lleva a Posts, Loop Grid y Loop Carousel de PRO y la etapa `pro` a los propios en la misma
# pasada; --revert los devuelve.
# C8: un documento de botones flotantes y otro de barra flotante (tipo de entrada `e-floating-buttons`), una página
# Link in Bio y una cabecera con variantes de PRO, que pasan a los widgets propios con su presentación (F8 no lleva
# ningún widget de Element Pack a estas variantes).
# Uso: bash tests/playground/pro-migrate.sh   (OUT, TMPDIR como los demás arneses)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-migrate}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT/probe"
cp "$ROOT/tests/playground/pro-migrate/"*.php "$OUT/probe/"
STEPS='{"step":"runPHP","code":"<?php require \"/wordpress/wp-content/probe/setup.php\";"}'
for s in inventory run run revert; do
  STEPS="$STEPS,{\"step\":\"writeFile\",\"path\":\"/wordpress/wp-content/probe/step.txt\",\"data\":\"$s\"},{\"step\":\"runPHP\",\"code\":\"<?php require \\\"/wordpress/wp-content/probe/check.php\\\";\"}"
done
printf '{"steps":[%s]}\n' "$STEPS" > "$OUT/blueprint.json"
trap 'playground_cleanup' EXIT
$CLI run-blueprint --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT/probe:/wordpress/wp-content/probe" > "$OUT/cli.log" 2>&1 || { tail -20 "$OUT/cli.log"; exit 1; }
node - "$OUT/probe/steps.jsonl" <<'JS'
const fs = require('fs');
const steps = fs.readFileSync(process.argv[2], 'utf8').trim().split('\n').map((l) => JSON.parse(l));
const by = (s) => steps.filter((x) => x.step === s);
const problems = [];
const ok = (c, m) => { console.log(`${c ? 'OK   ' : 'FALLA'}  ${m}`); if (!c) problems.push(m); };
for (const s of steps) if (s.error) problems.push(`${s.step}: ${s.error}`);
const inv = (by('inventory')[0] || {}).inventory || {};
const row = (inv.widgets || []).find((w) => w.widget === 'blockquote') || {};
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 3 && row.documents === 2 && row.revisions === 1 && inv.where && inv.where.page === 6 && inv.where.header === 3, `inventario: 3 Blockquote en 2 documentos (página y cabecera), familia C2, destino la Cita y la revisión aparte; widgets de PRO en 6 páginas (citas, carruseles, contenido, navegación, bucles y Link in Bio) y 3 cabeceras (C8: la de los botones) (${JSON.stringify(row).slice(0, 200)} ${JSON.stringify(inv.where)})`);
const c1 = ['nested-carousel', 'slides', 'media-carousel', 'testimonial-carousel', 'reviews'].map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c1.every((r) => r.family === 'C1' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los cinco carruseles de PRO, familia C1, cada uno con su widget propio de destino (${JSON.stringify(c1.map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const C2 = { e1: 'animated-headline', e2: 'code-highlight', e3: 'table-of-contents', e4: 'share-buttons', e5: 'call-to-action', e6: 'flip-box', e7: 'price-list', e8: 'price-table', e9: 'gallery', e10: 'hotspot', e11: 'lottie', e12: 'video-playlist', e13: 'countdown', e14: 'progress-tracker' };
const c2 = Object.values(C2).map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c2.every((r) => r.family === 'C2' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los catorce widgets de contenido de PRO, familia C2, cada uno con su widget propio de destino (${JSON.stringify(c2.filter((r) => !(r.family === 'C2' && r.elements === 1 && r.target === `digitalisimo-${r.widget}`)).map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const C3 = { 'nav-menu': ['digitalisimo-nav-menu', 2, 2], 'mega-menu': ['digitalisimo-mega-menu', 1, 1], 'off-canvas': ['digitalisimo-nested-off-canvas', 1, 1], search: ['digitalisimo-search', 2, 2] };
const c3 = Object.keys(C3).map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c3.every((r) => r.family === 'C3' && r.target === C3[r.widget][0] && r.elements === C3[r.widget][1] && r.documents === C3[r.widget][2]), `inventario: los cuatro widgets de navegación de PRO, familia C3, con su widget propio de destino; el Menú y la Búsqueda también en la cabecera (${JSON.stringify(c3.map((r) => [r.widget, r.family, r.target, r.elements, r.documents]))})`);
const C5 = { p1: ['posts', 'C5'], p2: ['archive-posts', 'C4'], p3: ['portfolio', 'C5'], p4: ['taxonomy-filter', 'C5'], p5: ['loop-grid', 'C5'], p6: ['loop-carousel', 'C5'] };
const c5 = Object.values(C5).map(([w, fam]) => ({ fam, ...((inv.widgets || []).find((x) => x.widget === w) || { widget: w }) }));
ok(c5.every((r) => r.family === r.fam && r.elements === (r.widget === 'posts' ? 2 : 1) && r.documents === (r.widget === 'posts' ? 2 : 1) && r.target === `digitalisimo-${r.widget}`), `inventario: los seis de entradas y bucles de PRO (Entradas del archivo, en la familia C4 del catálogo; las Entradas, también en la cabecera), cada uno con su widget propio de destino (${JSON.stringify(c5.map((r) => [r.widget, r.family, r.target, r.elements, r.documents]))})`);
const runs = by('run');
const last = runs[runs.length - 1] || {};
ok(runs.some((r) => r.state && r.state.done), `Migrar termina (${runs.map((r) => JSON.stringify(r.state)).join(' | ').slice(0, 300)})`);
const t = last.types || {};
ok(t.page && t.page.q1 && t.page.q1.startsWith('digitalisimo-blockquote') && t.page.q2 === 'digitalisimo-blockquote' && t.page.h1 === 'heading', `la página pasa a la Cita, también la anidada, y el encabezado no cambia (${JSON.stringify(t.page)})`);
ok(t.page && /digi-blockquote__content/.test(t.page.q1) && !/elementor-blockquote/.test(t.page.q1), 'el CSS personalizado del elemento usa las clases de la Cita');
ok(t.header && t.header.q9 === 'digitalisimo-blockquote', 'la plantilla de cabecera también se convierte');
ok(t.revision && t.revision.q1 && t.revision.q1.startsWith('blockquote') && t.revision.q2 === 'blockquote', 'la revisión se queda con el Blockquote de PRO');
ok(last.backup === true, 'queda la copia original de la página');
const first = runs.find((r) => r.state && r.state.counts && r.state.counts.pro) || {};
ok(first.state && first.state.counts.pro === 13 && first.state.counts.pro_elements === 66 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 66 widgets en 13 documentos (8 de C0 y C1, los 14 de C2 y la cuenta regresiva que F8 trae de Element Pack, los 6 de C3 de la página —con el Navbar y el Mega Menu de Element Pack— y los 2 de la cabecera de navegación, los 15 de C4 y la información de la entrada que F8 trae de Element Pack, los 7 de C5 más los 3 que F8 trae de Element Pack y Prime Slider, y los 4 elementos de tema de PRO del Elemento de bucle que F8 genera para el Blog de Prime Slider, y 5 variantes de C8 en dos documentos de botones flotantes, una página y una cabecera) y lo informa (${JSON.stringify(first.state && first.state.counts)} ${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
ok(runs.length < 2 || /No había nada que migrar/.test(last.report || '') || (last.state && last.state.counts && !last.state.counts.pro), 'una segunda pasada no vuelve a convertir nada');
const own = { n1: 'digitalisimo-nested-carousel', s1: 'digitalisimo-slides', m1: 'digitalisimo-media-carousel', t1: 'digitalisimo-testimonial-carousel', r1: 'digitalisimo-reviews' };
ok(t.carousels && Object.entries(own).every(([id, type]) => (t.carousels[id] || '').startsWith(type)) && t.carousels.n1ah === 'heading' && t.carousels.n1bh === 'heading', `C1: los cinco carruseles pasan a los propios y el contenido de las diapositivas anidadas no cambia (${JSON.stringify(t.carousels)})`);
ok(JSON.stringify(last.nested_children) === JSON.stringify(['n1a:container', 'n1b:container']), `C1: el Carrusel anidado conserva sus contenedores hijos con su id (${JSON.stringify(last.nested_children)})`);
ok(t.carousels && /digi-crs__slide/.test(t.carousels.n1 || '') && !/swiper-slide/.test(t.carousels.n1 || ''), 'C1: el CSS personalizado del carrusel anidado usa las clases del motor propio');
ok(t.content && Object.entries(C2).every(([id, w]) => (t.content[id] || '').split(' ')[0] === `digitalisimo-${w}`), `C2: los catorce widgets de contenido pasan a los propios (${JSON.stringify(t.content)})`);
ok(t.content && (t.content.ep1 || '').split(' ')[0] === 'digitalisimo-countdown', `C2: la cuenta regresiva de Element Pack llega a la propia en la misma pasada (F8 → PRO → propia: ${t.content && t.content.ep1})`);
ok(t.content && /digi-flip-box__front/.test(t.content.e6 || '') && !/elementor-flip-box/.test(t.content.e6 || '') && /digi-price-table__heading/.test(t.content.e8 || ''), 'C2: el CSS personalizado de la tarjeta giratoria y de la tabla de precios usa las clases propias');
const NAV = { v1: 'digitalisimo-nav-menu', v2: 'digitalisimo-mega-menu', v3: 'digitalisimo-nested-off-canvas', v4: 'digitalisimo-search', ep2: 'digitalisimo-nav-menu', ep3: 'digitalisimo-nav-menu' };
ok(t.navigation && Object.entries(NAV).every(([id, type]) => (t.navigation[id] || '').split(' ')[0] === type), `C3: los cuatro de navegación pasan a los propios, y el Navbar y el Mega Menu de Element Pack, al Menú propio en la misma pasada (${JSON.stringify(t.navigation)})`);
ok(t.nav_header && (t.nav_header.v9 || '').split(' ')[0] === 'digitalisimo-nav-menu' && (t.nav_header.v8 || '').split(' ')[0] === 'digitalisimo-search', `C3: la plantilla de cabecera pasa al Menú y a la Búsqueda propios (${JSON.stringify(t.nav_header)})`);
ok(JSON.stringify(last.nav_children) === JSON.stringify({ v2: ['v2a:container', 'v2b:container'], v3: ['v3a:container'] }), `C3: el Mega Menu y el Off-Canvas conservan sus contenedores hijos con su id (${JSON.stringify(last.nav_children)})`);
ok(t.navigation && /\.digi-nav__main \.digi-nav__link/.test(t.navigation.v1 || '') && !/elementor-nav-menu|elementor-item/.test(t.navigation.v1 || ''), `C3: el CSS personalizado del Menú usa las clases propias (${t.navigation && t.navigation.v1})`);
// C4: la plantilla de entrada del Theme Builder con los quince elementos de tema de PRO y un `bdt-post-info` de
// Element Pack (F8 → PRO → propio en la misma pasada).
const C4 = { g1: ['theme-site-logo', 'digitalisimo-site-logo'], g2: ['theme-site-title', 'digitalisimo-site-title'], g3: ['theme-page-title', 'digitalisimo-page-title'], g4: ['theme-post-title', 'digitalisimo-post-title'], g5: ['theme-archive-title', 'digitalisimo-archive-title'], g6: ['theme-post-excerpt', 'digitalisimo-post-excerpt'], g7: ['theme-post-content', 'digitalisimo-post-content'], g8: ['theme-post-featured-image', 'digitalisimo-featured-image'], h1: ['post-info', 'digitalisimo-post-info'], h2: ['post-navigation', 'digitalisimo-post-navigation'], h3: ['author-box', 'digitalisimo-author-box'], h4: ['post-comments', 'digitalisimo-post-comments'], h5: ['search-form', 'digitalisimo-search-form'], h6: ['sitemap', 'digitalisimo-sitemap'], h7: ['breadcrumbs', 'digitalisimo-yoast-breadcrumbs'] };
const c4inv = Object.values(C4).map(([pro, own]) => (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro, own });
ok(c4inv.every((r, i) => r.family === 'C4' && r.elements === 1 && r.documents === 1 && r.target === Object.values(C4)[i][1]) && inv.where && inv.where['single-post'] === 1, `inventario: los quince elementos de tema de PRO, familia C4, en la plantilla de entrada, cada uno con su widget propio (${JSON.stringify(c4inv.map((r) => [r.widget, r.family, r.target, r.elements]))})`);
ok(t.single && Object.entries(C4).every(([id, [, own]]) => (t.single[id] || '').split(' ')[0] === own), `C4: la plantilla de entrada pasa a los quince elementos de tema propios (${JSON.stringify(t.single)})`);
ok(t.single && (t.single.e1 || '') === 'digitalisimo-post-info', `C4: el bdt-post-info de Element Pack acaba en la Información de la entrada propia en la misma pasada (${t.single && t.single.e1})`);
ok(t.single && /selector \.digi-post-info__item\{/.test(t.single.h1 || '') && /elementor-widget-digitalisimo-post-title/.test(t.single.g4 || ''), 'C4: el CSS personalizado pasa a las clases propias (Información de la entrada y Título de la entrada)');
const L = last.loops || {};
const ls = L.settings || {};
const own5 = { p1: 'digitalisimo-posts', p2: 'digitalisimo-archive-posts', p3: 'digitalisimo-portfolio', p4: 'digitalisimo-taxonomy-filter', p5: 'digitalisimo-loop-grid', p6: 'digitalisimo-loop-carousel', ep2: 'digitalisimo-posts', ep3: 'digitalisimo-loop-grid', ps1: 'digitalisimo-loop-carousel' };
ok(t.loops && Object.entries(own5).every(([id, type]) => (t.loops[id] || '').split(' ')[0] === type), `C5: los seis de PRO y lo que F8 trae de Element Pack y Prime Slider pasan a los propios en la misma pasada (${JSON.stringify(t.loops)})`);
ok(t.header && (t.header.hp1 || '').split(' ')[0] === 'digitalisimo-posts', `C5: las Entradas de la plantilla de cabecera también (${JSON.stringify(t.header)})`);
ok(String((ls.p5 || {}).template_id) === String(L.loop_item) && String((ls.p6 || {}).template_id) === String(L.loop_item) && (ls.p4 || {}).selected_element === 'p5', `C5: la cuadrícula y el carrusel conservan su plantilla de Elemento de bucle y el filtro sigue apuntando a la cuadrícula (${JSON.stringify(ls)})`);
ok(L.card_copy > 0 && String((ls.ep3 || {}).template_id) === String(L.card_copy) && (ls.ep3 || {})._skin === 'post', `C5: el Dynamic Grid de Element Pack llega a la Cuadrícula propia con la copia Loop Item de F8 (${JSON.stringify(ls.ep3)} copia ${L.card_copy})`);
ok(t.loops && /digi-posts__title/.test(t.loops.p1 || '') && /digi-tfilter__item/.test(t.loops.p4 || '') && /digi-loop-grid__items/.test(t.loops.p5 || '') && !/elementor-post__title|e-filter-item|elementor-loop-container/.test([t.loops.p1, t.loops.p4, t.loops.p5].join(' ')), 'C5: el CSS personalizado de Entradas, del filtro y de la cuadrícula usa las clases propias');
// C8: documentos de botones flotantes (tipo de entrada `e-floating-buttons`), una página Link in Bio y una cabecera con
// variantes de PRO; cada una pasa al widget propio de su familia con la presentación de la variante.
const C8 = { k1: ['float', 'contact-buttons-var-1', 'digitalisimo-contact-buttons', '1'], k2: ['bar', 'floating-bars-var-3', 'digitalisimo-floating-bar', '3'], l2: ['bio', 'link-in-bio-var-2', 'digitalisimo-link-in-bio', '2'], l7: ['bio', 'link-in-bio-var-7', 'digitalisimo-link-in-bio', '7'], k10: ['header2', 'contact-buttons-var-10', 'digitalisimo-contact-buttons', '10'] };
const c8inv = Object.values(C8).map(([, pro, own]) => (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro, own });
ok(c8inv.every((r, i) => r.family === 'C8' && r.elements === 1 && r.documents === 1 && r.target === Object.values(C8)[i][2]) && inv.where && inv.where['e-floating-buttons'] === 2, `inventario: cinco variantes de PRO de C8, familia C8, cada una con su widget propio, y dos documentos de botones flotantes (${JSON.stringify(c8inv.map((r) => [r.widget, r.family, r.target, r.elements]))}; ${JSON.stringify(inv.where)})`);
ok(Object.entries(C8).every(([id, [doc, , own]]) => ((t[doc] || {})[id] || '').split(' ')[0] === own), `C8: las variantes de PRO pasan a los Botones de contacto, la Barra flotante y el Link in Bio propios, también en los documentos de botones flotantes y en la cabecera (${JSON.stringify([t.float, t.bar, t.bio, t.header2])})`);
ok(Object.entries(C8).every(([id, [, , , n]]) => (last.presentations || {})[id] === n), `C8: cada widget propio lleva la presentación de su variante (${JSON.stringify(last.presentations)})`);
ok(t.float && /digi-cb__chat-button\{/.test(t.float.k1 || '') && !/e-contact-buttons/.test(t.float.k1 || '') && t.bio && /digi-lib__title\{/.test(t.bio.l7 || ''), `C8: el CSS personalizado pasa a las clases propias (${t.float && t.float.k1} · ${t.bio && t.bio.l7})`);
const rv = (by('revert')[0] || {}).types || {};
ok(rv.navigation && Object.entries({ v1: 'nav-menu', v2: 'mega-menu', v3: 'off-canvas', v4: 'search', ep2: 'bdt-navbar', ep3: 'bdt-mega-menu' }).every(([id, type]) => (rv.navigation[id] || '').split(' ')[0] === type) && rv.nav_header && (rv.nav_header.v9 || '').split(' ')[0] === 'nav-menu' && (rv.nav_header.v8 || '').split(' ')[0] === 'search', `C3: --revert devuelve los de PRO y los de Element Pack (${JSON.stringify([rv.navigation, rv.nav_header])})`);
ok(rv.loops && Object.entries({ p1: 'posts', p2: 'archive-posts', p3: 'portfolio', p4: 'taxonomy-filter', p5: 'loop-grid', p6: 'loop-carousel', ep2: 'bdt-post-grid', ep3: 'bdt-dynamic-grid', ps1: 'prime-slider-blog' }).every(([id, type]) => (rv.loops[id] || '').split(' ')[0] === type) && rv.header && (rv.header.hp1 || '').split(' ')[0] === 'posts', `C5: --revert devuelve los de PRO y los de Element Pack y Prime Slider (${JSON.stringify(rv.loops)})`);
ok(rv.content && Object.entries(C2).every(([id, w]) => (rv.content[id] || '').split(' ')[0] === w), `C2: --revert devuelve los catorce widgets de PRO (${JSON.stringify(rv.content)})`);
ok(rv.content && (rv.content.ep1 || '').split(' ')[0] === 'bdt-countdown', `C2: --revert devuelve la cuenta regresiva de Element Pack (${rv.content && rv.content.ep1})`);
ok(rv.carousels && Object.entries({ n1: 'nested-carousel', s1: 'slides', m1: 'media-carousel', t1: 'testimonial-carousel', r1: 'reviews' }).every(([id, type]) => (rv.carousels[id] || '').split(' ')[0] === type), `C1: --revert devuelve los carruseles de PRO (${JSON.stringify(rv.carousels)})`);
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
ok(rv.single && Object.entries(C4).every(([id, [pro]]) => (rv.single[id] || '').split(' ')[0] === pro) && rv.single.e1 === 'bdt-post-info', `C4: --revert devuelve los elementos de tema de PRO y el widget de Element Pack (${JSON.stringify(rv.single)})`);
ok(Object.entries(C8).every(([id, [doc, pro]]) => ((rv[doc] || {})[id] || '').split(' ')[0] === pro), `C8: --revert devuelve las variantes de PRO (${JSON.stringify([rv.float, rv.bar, rv.bio, rv.header2])})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
