#!/usr/bin/env bash
# Migración de los widgets de PRO de punta a punta (C0 de docs/PRO_WIDGETS_OBJECTIVE.md), en Playground con el núcleo
# incluido en DIGITALÍSIMO Elements y DIGITALÍSIMO Migración: un sitio con Blockquote de PRO en una página (uno anidado),
# en una plantilla de cabecera y en una revisión; el inventario de «Analizar», «Migrar» hasta terminar y --revert.
# Comprueba que la página y la cabecera pasan a la Cita (con el CSS personalizado traducido), que la revisión no se
# toca, que queda la copia original y que --revert devuelve el Blockquote de PRO. C1: los cinco carruseles de PRO. C2: los
# catorce widgets de contenido de PRO y una cuenta regresiva de Element Pack (`bdt-countdown`) que F8 lleva a la de PRO y
# la etapa `pro` a la propia en la misma pasada; --revert devuelve los de PRO y el de Element Pack. C4: una plantilla de
# entrada del Theme Builder con los quince elementos de tema de PRO y un `bdt-post-info` de Element Pack (F8 → PRO →
# propio en la misma pasada). C6: una página con el Formulario (correo, webhook y redirección) y el Inicio de sesión de
# PRO y los cuatro formularios de Element Pack que F8 lleva a ellos (contacto, Mailchimp, webhook y acceso), más un
# Inicio de sesión de PRO en la plantilla de cabecera: todos acaban en los propios con el mismo id, las mismas acciones y
# los mismos destinatarios; --revert devuelve los de PRO y los de Element Pack.
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
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 3 && row.documents === 2 && row.revisions === 1 && inv.where && inv.where.page === 4 && inv.where.header === 1, `inventario: 3 Blockquote en 2 documentos (página y cabecera), familia C2, destino la Cita y la revisión aparte; widgets de PRO en 4 páginas (citas, carruseles, contenido y formularios) y una cabecera (${JSON.stringify(row).slice(0, 200)})`);
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
ok(first.state && first.state.counts.pro === 6 && first.state.counts.pro_elements === 46 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 46 widgets en 6 documentos (8 de C0 y C1, los 14 de C2 y la cuenta regresiva que F8 trae de Element Pack, los 15 de C4 y la información de la entrada que F8 trae de Element Pack, y los 3 de C6 y los 4 formularios que F8 trae de Element Pack) y lo informa (${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
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
// C6: el Formulario y el Inicio de sesión de PRO (uno en la cabecera) y los formularios de Element Pack que F8 lleva a
// ellos acaban en los propios con el mismo id, las mismas acciones y los mismos destinatarios.
const c6inv = [['form', 'digitalisimo-form', 1, 1], ['login', 'digitalisimo-login', 2, 2]].map(([pro, own, n, d]) => ({ want: [own, n, d], row: (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro } }));
ok(c6inv.every(({ want, row }) => row.family === 'C6' && row.target === want[0] && row.elements === want[1] && row.documents === want[2]), `inventario: el Formulario (1, en la página) y el Inicio de sesión de PRO (2, en la página y en la cabecera), familia C6, con su widget propio (${JSON.stringify(c6inv.map(({ row }) => [row.widget, row.family, row.target, row.elements, row.documents]))})`);
const C6 = { k1: 'digitalisimo-form', k2: 'digitalisimo-login', b1: 'digitalisimo-form', b2: 'digitalisimo-form', b3: 'digitalisimo-form', b4: 'digitalisimo-login' };
ok(t.forms && Object.entries(C6).every(([id, own]) => (t.forms[id] || '').split(' ')[0] === own) && t.header && t.header.k9 === 'digitalisimo-login', `C6: la página de formularios y el acceso de la cabecera pasan a los propios, también los de Element Pack en la misma pasada (${JSON.stringify(t.forms)}; cabecera: ${t.header && t.header.k9})`);
ok(t.forms && /selector\.elementor-widget-digitalisimo-form\{/.test(t.forms.k1 || ''), `C6: el CSS personalizado del Formulario usa la clase propia (${t.forms && t.forms.k1})`);
const fs6 = last.forms || {};
const k1 = fs6.k1 || {};
ok(JSON.stringify(k1.submit_actions) === JSON.stringify(['save-to-database', 'email', 'webhook', 'redirect']) && k1.email_to === 'equipo@sitio.test' && k1.webhooks === 'https://receptor.test/hook' && k1.redirect_to === 'https://sitio.test/gracias/' && JSON.stringify(k1.fields) === JSON.stringify(['name', 'email', 'message']) && k1._digitalisimo_pro === 'form', `C6: el Formulario conserva sus campos, sus cuatro acciones y sus destinatarios (${JSON.stringify(k1)})`);
const b1 = fs6.b1 || {};
ok((b1.submit_actions || []).includes('email') && (b1.submit_actions || []).includes('redirect') && b1.email_to === 'ventas@sitio.test' && b1.redirect_to === 'https://sitio.test/gracias/' && b1._digitalisimo_pro === 'form', `C6: el contacto de Element Pack llega con el correo al mismo destinatario y la redirección (${JSON.stringify(b1)})`);
ok(((fs6.b2 || {}).submit_actions || []).includes('mailchimp') && (fs6.b2 || {}).mailchimp_list === 'L1', `C6: el Mailchimp de Element Pack llega con su acción y su audiencia (${JSON.stringify(fs6.b2)})`);
ok(((fs6.b3 || {}).submit_actions || []).includes('webhook') && (fs6.b3 || {}).webhooks === 'https://receptor.test/hook', `C6: el formulario con webhook de Element Pack llega con su URL (${JSON.stringify(fs6.b3)})`);
ok((fs6.k2 || {}).button_text === 'Acceder' && (fs6.k2 || {}).user_label === 'Usuario' && (fs6.b4 || {})._digitalisimo_pro === 'login', `C6: los inicios de sesión conservan sus textos (${JSON.stringify([fs6.k2, fs6.b4])})`);
const rv = (by('revert')[0] || {}).types || {};
ok(rv.content && Object.entries(C2).every(([id, w]) => (rv.content[id] || '').split(' ')[0] === w), `C2: --revert devuelve los catorce widgets de PRO (${JSON.stringify(rv.content)})`);
ok(rv.content && (rv.content.ep1 || '').split(' ')[0] === 'bdt-countdown', `C2: --revert devuelve la cuenta regresiva de Element Pack (${rv.content && rv.content.ep1})`);
ok(rv.carousels && Object.entries({ n1: 'nested-carousel', s1: 'slides', m1: 'media-carousel', t1: 'testimonial-carousel', r1: 'reviews' }).every(([id, type]) => (rv.carousels[id] || '').split(' ')[0] === type), `C1: --revert devuelve los carruseles de PRO (${JSON.stringify(rv.carousels)})`);
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
ok(rv.single && Object.entries(C4).every(([id, [pro]]) => (rv.single[id] || '').split(' ')[0] === pro) && rv.single.e1 === 'bdt-post-info', `C4: --revert devuelve los elementos de tema de PRO y el widget de Element Pack (${JSON.stringify(rv.single)})`);
ok(rv.forms && Object.entries({ k1: 'form', k2: 'login', b1: 'bdt-contact-form', b2: 'bdt-mailchimp', b3: 'bdt-webhook-form', b4: 'bdt-user-login' }).every(([id, type]) => (rv.forms[id] || '').split(' ')[0] === type) && rv.header && rv.header.k9 === 'login', `C6: --revert devuelve el Formulario y el Inicio de sesión de PRO y los formularios de Element Pack (${JSON.stringify(rv.forms)}; cabecera: ${rv.header && rv.header.k9})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
