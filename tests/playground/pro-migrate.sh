#!/usr/bin/env bash
# Migración de los widgets de PRO de punta a punta (C0 de docs/PRO_WIDGETS_OBJECTIVE.md), en Playground con el núcleo
# incluido en DIGITALÍSIMO Elements y DIGITALÍSIMO Migración: un sitio con Blockquote de PRO en una página (uno anidado),
# en una plantilla de cabecera y en una revisión; el inventario de «Analizar», «Migrar» hasta terminar y --revert.
# Comprueba que la página y la cabecera pasan a la Cita (con el CSS personalizado traducido), que la revisión no se
# toca, que queda la copia original y que --revert devuelve el Blockquote de PRO. C1: los cinco carruseles de PRO. C2: los
# catorce widgets de contenido de PRO y una cuenta regresiva de Element Pack (`bdt-countdown`) que F8 lleva a la de PRO y
# la etapa `pro` a la propia en la misma pasada; --revert devuelve los de PRO y el de Element Pack. C4: una plantilla de
# entrada del Theme Builder con los quince elementos de tema de PRO y un `bdt-post-info` de Element Pack (F8 → PRO →
# propio en la misma pasada). C9: una página con tres instancias del widget global (una con un Blockquote de PRO, que
# llega a la Cita en la misma pasada, y una sin plantilla, que se queda y se informa), la Plantilla de PRO con una
# sobrescritura de F7b, los cuatro de Facebook, PayPal y Stripe, y una cabecera con otra instancia global; F8 no lleva
# ningún widget de Element Pack a estos de PRO.
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
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 4 && row.documents === 3 && row.revisions === 1 && inv.where && inv.where.page === 4 && inv.where.header === 2 && inv.where.widget === 1, `inventario: 4 Blockquote en 3 documentos (página, cabecera y la plantilla de widget global de C9), familia C2, destino la Cita y la revisión aparte; widgets de PRO en 4 páginas (citas, carruseles, contenido y servicios), dos cabeceras y una plantilla de widget global (${JSON.stringify(row).slice(0, 200)} · ${JSON.stringify(inv.where)})`);
const c1 = ['nested-carousel', 'slides', 'media-carousel', 'testimonial-carousel', 'reviews'].map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c1.every((r) => r.family === 'C1' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los cinco carruseles de PRO, familia C1, cada uno con su widget propio de destino (${JSON.stringify(c1.map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const C2 = { e1: 'animated-headline', e2: 'code-highlight', e3: 'table-of-contents', e4: 'share-buttons', e5: 'call-to-action', e6: 'flip-box', e7: 'price-list', e8: 'price-table', e9: 'gallery', e10: 'hotspot', e11: 'lottie', e12: 'video-playlist', e13: 'countdown', e14: 'progress-tracker' };
const c2 = Object.values(C2).map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c2.every((r) => r.family === 'C2' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los catorce widgets de contenido de PRO, familia C2, cada uno con su widget propio de destino (${JSON.stringify(c2.filter((r) => !(r.family === 'C2' && r.elements === 1 && r.target === `digitalisimo-${r.widget}`)).map((r) => [r.widget, r.family, r.target, r.elements]))})`);
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
ok(first.state && first.state.counts.pro === 8 && first.state.counts.pro_elements === 50 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 50 widgets en 8 documentos (8 de C0 y C1, los 14 de C2 y la cuenta regresiva que F8 trae de Element Pack, los 15 de C4 y la información de la entrada que F8 trae de Element Pack, y 11 de C9: tres instancias globales, la Plantilla, los cuatro de Facebook, PayPal, Stripe y el Blockquote de la plantilla de widget global) y lo informa (${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
ok(runs.length < 2 || /No había nada que migrar/.test(last.report || '') || (last.state && last.state.counts && !last.state.counts.pro), 'una segunda pasada no vuelve a convertir nada');
const own = { n1: 'digitalisimo-nested-carousel', s1: 'digitalisimo-slides', m1: 'digitalisimo-media-carousel', t1: 'digitalisimo-testimonial-carousel', r1: 'digitalisimo-reviews' };
ok(t.carousels && Object.entries(own).every(([id, type]) => (t.carousels[id] || '').startsWith(type)) && t.carousels.n1ah === 'heading' && t.carousels.n1bh === 'heading', `C1: los cinco carruseles pasan a los propios y el contenido de las diapositivas anidadas no cambia (${JSON.stringify(t.carousels)})`);
ok(JSON.stringify(last.nested_children) === JSON.stringify(['n1a:container', 'n1b:container']), `C1: el Carrusel anidado conserva sus contenedores hijos con su id (${JSON.stringify(last.nested_children)})`);
ok(t.carousels && /digi-crs__slide/.test(t.carousels.n1 || '') && !/swiper-slide/.test(t.carousels.n1 || ''), 'C1: el CSS personalizado del carrusel anidado usa las clases del motor propio');
ok(t.content && Object.entries(C2).every(([id, w]) => (t.content[id] || '').split(' ')[0] === `digitalisimo-${w}`), `C2: los catorce widgets de contenido pasan a los propios (${JSON.stringify(t.content)})`);
ok(t.content && (t.content.ep1 || '').split(' ')[0] === 'digitalisimo-countdown', `C2: la cuenta regresiva de Element Pack llega a la propia en la misma pasada (F8 → PRO → propia: ${t.content && t.content.ep1})`);
ok(t.content && /digi-flip-box__front/.test(t.content.e6 || '') && !/elementor-flip-box/.test(t.content.e6 || '') && /digi-price-table__heading/.test(t.content.e8 || ''), 'C2: el CSS personalizado de la tarjeta giratoria y de la tabla de precios usa las clases propias');
// C4: la plantilla de entrada del Theme Builder con los quince elementos de tema de PRO y un `bdt-post-info` de
// Element Pack (F8 → PRO → propio en la misma pasada).
const C4 = { g1: ['theme-site-logo', 'digitalisimo-site-logo'], g2: ['theme-site-title', 'digitalisimo-site-title'], g3: ['theme-page-title', 'digitalisimo-page-title'], g4: ['theme-post-title', 'digitalisimo-post-title'], g5: ['theme-archive-title', 'digitalisimo-archive-title'], g6: ['theme-post-excerpt', 'digitalisimo-post-excerpt'], g7: ['theme-post-content', 'digitalisimo-post-content'], g8: ['theme-post-featured-image', 'digitalisimo-featured-image'], h1: ['post-info', 'digitalisimo-post-info'], h2: ['post-navigation', 'digitalisimo-post-navigation'], h3: ['author-box', 'digitalisimo-author-box'], h4: ['post-comments', 'digitalisimo-post-comments'], h5: ['search-form', 'digitalisimo-search-form'], h6: ['sitemap', 'digitalisimo-sitemap'], h7: ['breadcrumbs', 'digitalisimo-yoast-breadcrumbs'] };
const c4inv = Object.values(C4).map(([pro, own]) => (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro, own });
ok(c4inv.every((r, i) => r.family === 'C4' && r.elements === 1 && r.documents === 1 && r.target === Object.values(C4)[i][1]) && inv.where && inv.where['single-post'] === 1, `inventario: los quince elementos de tema de PRO, familia C4, en la plantilla de entrada, cada uno con su widget propio (${JSON.stringify(c4inv.map((r) => [r.widget, r.family, r.target, r.elements]))})`);
ok(t.single && Object.entries(C4).every(([id, [, own]]) => (t.single[id] || '').split(' ')[0] === own), `C4: la plantilla de entrada pasa a los quince elementos de tema propios (${JSON.stringify(t.single)})`);
ok(t.single && (t.single.e1 || '') === 'digitalisimo-post-info', `C4: el bdt-post-info de Element Pack acaba en la Información de la entrada propia en la misma pasada (${t.single && t.single.e1})`);
ok(t.single && /selector \.digi-post-info__item\{/.test(t.single.h1 || '') && /elementor-widget-digitalisimo-post-title/.test(t.single.g4 || ''), 'C4: el CSS personalizado pasa a las clases propias (Información de la entrada y Título de la entrada)');
// C9: la página de servicios (tres instancias globales, una sin plantilla; la Plantilla con una sobrescritura; los
// cuatro de Facebook, PayPal y Stripe) y una cabecera con otra instancia global.
const C9 = { t1: ['template', 'digitalisimo-template'], f1: ['facebook-button', 'digitalisimo-facebook-button'], f2: ['facebook-comments', 'digitalisimo-facebook-comments'], f3: ['facebook-embed', 'digitalisimo-facebook-embed'], f4: ['facebook-page', 'digitalisimo-facebook-page'], p1: ['paypal-button', 'digitalisimo-paypal-button'], p2: ['stripe-button', 'digitalisimo-stripe-button'] };
const c9inv = Object.values(C9).map(([pro, own]) => (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro, own });
const ginv = (inv.widgets || []).find((x) => x.widget === 'global') || {};
ok(c9inv.every((r, i) => r.family === 'C9' && r.elements === 1 && r.documents === 1 && r.target === Object.values(C9)[i][1]) && ginv.family === 'C9' && ginv.elements === 4 && ginv.documents === 2 && ginv.target === 'su contenido', `inventario: los siete de C9 con su widget propio y el widget global (4 instancias en 2 documentos) «se convierte en su contenido» (${JSON.stringify(c9inv.map((r) => [r.widget, r.family, r.target, r.elements]).concat([[ginv.widget, ginv.elements, ginv.documents, ginv.target]]))})`);
const sv = last.services || {};
ok(t.services && Object.entries(C9).every(([id, [, own]]) => (t.services[id] || '').split(' ')[0] === own), `C9: la Plantilla, los cuatro de Facebook, PayPal y Stripe pasan a los propios (${JSON.stringify(t.services)})`);
ok(sv.t1 && sv.t1.overrides === 1 && /digi-template/.test(t.services.t1 || '') && /digi-fb__plugin/.test(t.services.f4 || ''), 'C9: la Plantilla conserva su sobrescritura de F7b y el CSS personalizado pasa a las clases propias (Plantilla y Página de Facebook)');
ok(sv.x1 && sv.x1.type === 'heading' && sv.x1.global > 0 && sv.x1.classes === `elementor-global-${sv.x1.global}` && sv.x1.title === 'Encabezado global', `C9: la instancia global pasa al Encabezado de su plantilla, con su id, la plantilla de origen y la clase de PRO (${JSON.stringify(sv.x1)})`);
ok(sv.x2 && sv.x2.type === 'digitalisimo-blockquote' && sv.x2.global > 0, `C9: una instancia global de un Blockquote de PRO llega a la Cita en la misma pasada (global → contenido → propio: ${JSON.stringify(sv.x2)})`);
ok(sv.x3 && sv.x3.type === 'global' && sv.x3.templateID === 999999 && last.codes && last.codes.global === 3 && last.codes.global_missing === 1 && /Instancia del widget global cuya plantilla no existe/.test(last.report || ''), `C9: la instancia sin plantilla se queda y el informe la cuenta (pro.global 3, pro.global_missing 1: ${JSON.stringify(last.codes)})`);
ok(t.header_global && t.header_global.x9 === 'heading', `C9: la cabecera con una instancia global también se sustituye (${JSON.stringify(t.header_global)})`);
ok(t.gw_heading && t.gw_heading.gwh === 'heading', 'C9: la plantilla de widget global se conserva sin cambios');
const rv = (by('revert')[0] || {}).types || {};
ok(rv.services && Object.entries(C9).every(([id, [pro]]) => (rv.services[id] || '').split(' ')[0] === pro) && ['x1', 'x2', 'x3'].every((id) => rv.services[id] === 'global') && rv.header_global && rv.header_global.x9 === 'global' && rv.gw_quote && rv.gw_quote.gwq === 'blockquote', `C9: --revert devuelve los widgets de PRO, las instancias globales y el Blockquote de la plantilla de widget (${JSON.stringify(rv.services)} · ${JSON.stringify(rv.header_global)})`);
ok(rv.content && Object.entries(C2).every(([id, w]) => (rv.content[id] || '').split(' ')[0] === w), `C2: --revert devuelve los catorce widgets de PRO (${JSON.stringify(rv.content)})`);
ok(rv.content && (rv.content.ep1 || '').split(' ')[0] === 'bdt-countdown', `C2: --revert devuelve la cuenta regresiva de Element Pack (${rv.content && rv.content.ep1})`);
ok(rv.carousels && Object.entries({ n1: 'nested-carousel', s1: 'slides', m1: 'media-carousel', t1: 'testimonial-carousel', r1: 'reviews' }).every(([id, type]) => (rv.carousels[id] || '').split(' ')[0] === type), `C1: --revert devuelve los carruseles de PRO (${JSON.stringify(rv.carousels)})`);
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
ok(rv.single && Object.entries(C4).every(([id, [pro]]) => (rv.single[id] || '').split(' ')[0] === pro) && rv.single.e1 === 'bdt-post-info', `C4: --revert devuelve los elementos de tema de PRO y el widget de Element Pack (${JSON.stringify(rv.single)})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
