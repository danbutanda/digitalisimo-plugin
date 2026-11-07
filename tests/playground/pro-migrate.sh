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
# C6: una página con el Formulario (correo, webhook y redirección) y el Inicio de sesión de PRO y los cuatro formularios
# de Element Pack que F8 lleva a ellos (contacto, Mailchimp, webhook y acceso), más un Inicio de sesión de PRO en la
# plantilla de cabecera: todos acaban en los propios con el mismo id, las mismas acciones y los mismos destinatarios;
# --revert devuelve los de PRO y los de Element Pack.
# C7: con el WooCommerce fijado (tests/playground/woocommerce-pin.sh, montado y activado
# antes de setup.php), los treinta widgets de WooCommerce de PRO en una plantilla de producto, una de archivo de
# productos, una página y una cabecera, y tres de Element Pack (`bdt-wc-add-to-cart`, `bdt-wc-products` y
# `bdt-wc-mini-cart`) que F8 lleva a los de PRO y la etapa `pro` a los propios en la misma pasada.
# C8: un documento de botones flotantes y otro de barra flotante (tipo de entrada `e-floating-buttons`), una página
# Link in Bio y una cabecera con variantes de PRO, que pasan a los widgets propios con su presentación (F8 no lleva
# ningún widget de Element Pack a estas variantes).
# C9: una página con tres instancias del widget global (pasan al widget global propio, vinculadas a su plantilla; la de
# un Blockquote de PRO, cuya plantilla llega a la Cita en la misma pasada, y una sin plantilla, que se queda y se informa),
# la Plantilla de PRO con una sobrescritura de F7b, los cuatro de Facebook, PayPal y Stripe, y una cabecera con otra
# instancia global; F8 no lleva ningún widget de Element Pack a estos de PRO.
# Uso: bash tests/playground/pro-migrate.sh   (OUT, TMPDIR, WOO_ZIP como los demás arneses)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-migrate}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT/probe"
cp "$ROOT/tests/playground/pro-migrate/"*.php "$OUT/probe/"
. "$ROOT/tests/playground/woocommerce-pin.sh"
woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
WOO_MOUNT=()
[ -n "$(woo_mount)" ] && WOO_MOUNT=("$(woo_mount)")
STEPS="$(woo_step true),"'{"step":"runPHP","code":"<?php require \"/wordpress/wp-content/probe/setup.php\";"}'
for s in inventory run run revert; do
  STEPS="$STEPS,{\"step\":\"writeFile\",\"path\":\"/wordpress/wp-content/probe/step.txt\",\"data\":\"$s\"},{\"step\":\"runPHP\",\"code\":\"<?php require \\\"/wordpress/wp-content/probe/check.php\\\";\"}"
done
printf '{"steps":[%s]}\n' "$STEPS" > "$OUT/blueprint.json"
trap 'playground_cleanup' EXIT
$CLI run-blueprint --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT/probe:/wordpress/wp-content/probe" "${WOO_MOUNT[@]}" > "$OUT/cli.log" 2>&1 || { tail -20 "$OUT/cli.log"; exit 1; }
node - "$OUT/probe/steps.jsonl" <<'JS'
const fs = require('fs');
const steps = fs.readFileSync(process.argv[2], 'utf8').trim().split('\n').map((l) => JSON.parse(l));
const by = (s) => steps.filter((x) => x.step === s);
const problems = [];
const ok = (c, m) => { console.log(`${c ? 'OK   ' : 'FALLA'}  ${m}`); if (!c) problems.push(m); };
for (const s of steps) if (s.error) problems.push(`${s.step}: ${s.error}`);
const inv = (by('inventory')[0] || {}).inventory || {};
const row = (inv.widgets || []).find((w) => w.widget === 'blockquote') || {};
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 4 && row.documents === 3 && row.revisions === 1 && inv.where && inv.where.page === 9 && inv.where.header === 5 && inv.where.widget === 1, `inventario: 4 Blockquote en 3 documentos (página, cabecera y la plantilla de widget global de C9), familia C2, destino la Cita y la revisión aparte; widgets de PRO en 9 páginas (citas, carruseles, contenido, navegación, bucles, formularios, carrito de C7, Link in Bio de C8 y servicios de C9), 5 cabeceras (la de la cita, la de navegación, la de la tienda de C7, la de los botones de C8 y la del widget global de C9) y una plantilla de widget global (${JSON.stringify(row).slice(0, 200)} ${JSON.stringify(inv.where)})`);
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
ok(first.state && first.state.counts.pro === 21 && first.state.counts.pro_elements === 117 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 117 widgets en 21 documentos (8 de C0 y C1, los 14 de C2 y la cuenta regresiva que F8 trae de Element Pack, los 6 de C3 de la página —con el Navbar y el Mega Menu de Element Pack— y los 2 de la cabecera de navegación, los 15 de C4 y la información de la entrada que F8 trae de Element Pack, los 7 de C5 más los 3 que F8 trae de Element Pack y Prime Slider, y los 4 elementos de tema de PRO del Elemento de bucle que F8 genera para el Blog de Prime Slider, y los 3 de C6 —uno en la cabecera de la Cita— y los 4 formularios que F8 trae de Element Pack, y los 30 de C7 —el carrito del menú, dos veces— y los tres de WooCommerce que F8 trae de Element Pack, y 5 variantes de C8 en dos documentos de botones flotantes, una página y una cabecera, y 11 de C9: tres instancias globales, que pasan al widget global propio sin copiar su contenido, la Plantilla, los cuatro de Facebook, PayPal, Stripe y el Blockquote de la plantilla de widget global) y lo informa (${JSON.stringify(first.state && first.state.counts)} ${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
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
// C7: los treinta widgets de WooCommerce de PRO (el carrito del menú, en la página y en la cabecera) y tres de Element
// Pack (F8 → PRO → propio en la misma pasada).
const C7 = {
  product: { w1: ['woocommerce-product-title', 'product-title'], w2: ['woocommerce-product-price', 'product-price'], w3: ['woocommerce-product-rating', 'product-rating'], w4: ['woocommerce-product-stock', 'product-stock'], w5: ['woocommerce-product-meta', 'product-meta'], w6: ['woocommerce-product-short-description', 'product-short-description'], w7: ['woocommerce-product-content', 'product-content'], w8: ['woocommerce-product-additional-information', 'product-additional-information'], w9: ['woocommerce-product-data-tabs', 'product-data-tabs'], w10: ['woocommerce-product-images', 'product-images'], w11: ['woocommerce-product-add-to-cart', 'product-add-to-cart'], w12: ['woocommerce-product-related', 'product-related'], w13: ['woocommerce-product-upsell', 'product-upsell'], w14: ['wc-single-elements', 'single-elements'], w15: ['wc-add-to-cart', 'add-to-cart'], ew1: ['bdt-wc-add-to-cart', 'add-to-cart'] },
  shop: { x1: ['wc-archive-products', 'archive-products'], x2: ['woocommerce-archive-products', 'archive-products-classic'], x3: ['woocommerce-archive-description', 'archive-description'], x4: ['woocommerce-breadcrumb', 'breadcrumb'], x5: ['woocommerce-notices', 'notices'], x6: ['wc-categories', 'categories'], x7: ['woocommerce-category-image', 'category-image'], x8: ['woocommerce-products', 'products'], x9: ['wc-products', 'products-classic'], ew2: ['bdt-wc-products', 'products'] },
  cart: { k1: ['woocommerce-cart', 'cart'], k2: ['woocommerce-checkout-page', 'checkout'], k3: ['woocommerce-my-account', 'my-account'], k4: ['woocommerce-purchase-summary', 'purchase-summary'], k5: ['woocommerce-menu-cart', 'menu-cart'], k6: ['wc-elements', 'pages'] },
  shop_header: { m1: ['woocommerce-menu-cart', 'menu-cart'], em1: ['bdt-wc-mini-cart', 'menu-cart'] },
};
const c7pro = [...new Set(Object.values(C7).flatMap((doc) => Object.values(doc).map(([pro]) => pro)).filter((w) => !w.startsWith('bdt-')))];
const c7inv = c7pro.map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c7pro.length === 30 && c7inv.every((r) => r.family === 'C7' && r.elements === (r.widget === 'woocommerce-menu-cart' ? 2 : 1) && /^digitalisimo-woo-/.test(r.target || '')) && inv.where && inv.where.product === 1 && inv.where['product-archive'] === 1, `inventario: los treinta widgets de WooCommerce de PRO, familia C7, cada uno con su widget propio (${JSON.stringify(c7inv.filter((r) => !(r.family === 'C7' && /^digitalisimo-woo-/.test(r.target || ''))).map((r) => [r.widget, r.family, r.target, r.elements]))}; ${JSON.stringify(inv.where)})`);
for (const [doc, ids] of Object.entries(C7)) {
  ok(t[doc] && Object.entries(ids).every(([id, [, own]]) => (t[doc][id] || '').split(' ')[0] === `digitalisimo-woo-${own}`), `C7: ${doc} pasa a los widgets de WooCommerce propios, también lo que F8 trae de Element Pack (${JSON.stringify(t[doc])})`);
}
ok(t.product && /digi-atc__holder/.test(t.product.w11 || '') && !/e-atc-qty-button-holder/.test(t.product.w11 || '') && t.shop && /digi-products-grid/.test(t.shop.x8 || '') && t.shop_header && /digi-mc__tg/.test(t.shop_header.m1 || ''), 'C7: el CSS personalizado pasa a las clases propias («Añadir al carrito», Productos y carrito del menú)');
// C8: documentos de botones flotantes (tipo de entrada `e-floating-buttons`), una página Link in Bio y una cabecera con
// variantes de PRO; cada una pasa al widget propio de su familia con la presentación de la variante.
const C8 = { k1: ['float', 'contact-buttons-var-1', 'digitalisimo-contact-buttons', '1'], k2: ['bar', 'floating-bars-var-3', 'digitalisimo-floating-bar', '3'], l2: ['bio', 'link-in-bio-var-2', 'digitalisimo-link-in-bio', '2'], l7: ['bio', 'link-in-bio-var-7', 'digitalisimo-link-in-bio', '7'], k10: ['header2', 'contact-buttons-var-10', 'digitalisimo-contact-buttons', '10'] };
const c8inv = Object.values(C8).map(([, pro, own]) => (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro, own });
ok(c8inv.every((r, i) => r.family === 'C8' && r.elements === 1 && r.documents === 1 && r.target === Object.values(C8)[i][2]) && inv.where && inv.where['e-floating-buttons'] === 2, `inventario: cinco variantes de PRO de C8, familia C8, cada una con su widget propio, y dos documentos de botones flotantes (${JSON.stringify(c8inv.map((r) => [r.widget, r.family, r.target, r.elements]))}; ${JSON.stringify(inv.where)})`);
ok(Object.entries(C8).every(([id, [doc, , own]]) => ((t[doc] || {})[id] || '').split(' ')[0] === own), `C8: las variantes de PRO pasan a los Botones de contacto, la Barra flotante y el Link in Bio propios, también en los documentos de botones flotantes y en la cabecera (${JSON.stringify([t.float, t.bar, t.bio, t.header2])})`);
ok(Object.entries(C8).every(([id, [, , , n]]) => (last.presentations || {})[id] === n), `C8: cada widget propio lleva la presentación de su variante (${JSON.stringify(last.presentations)})`);
ok(t.float && /digi-cb__chat-button\{/.test(t.float.k1 || '') && !/e-contact-buttons/.test(t.float.k1 || '') && t.bio && /digi-lib__title\{/.test(t.bio.l7 || ''), `C8: el CSS personalizado pasa a las clases propias (${t.float && t.float.k1} · ${t.bio && t.bio.l7})`);
// C9: la página de servicios (tres instancias globales, una sin plantilla; la Plantilla con una sobrescritura; los
// cuatro de Facebook, PayPal y Stripe) y una cabecera con otra instancia global.
const C9 = { t1: ['template', 'digitalisimo-template'], f1: ['facebook-button', 'digitalisimo-facebook-button'], f2: ['facebook-comments', 'digitalisimo-facebook-comments'], f3: ['facebook-embed', 'digitalisimo-facebook-embed'], f4: ['facebook-page', 'digitalisimo-facebook-page'], p1: ['paypal-button', 'digitalisimo-paypal-button'], p2: ['stripe-button', 'digitalisimo-stripe-button'] };
const c9inv = Object.values(C9).map(([pro, own]) => (inv.widgets || []).find((x) => x.widget === pro) || { widget: pro, own });
const ginv = (inv.widgets || []).find((x) => x.widget === 'global') || {};
ok(c9inv.every((r, i) => r.family === 'C9' && r.elements === 1 && r.documents === 1 && r.target === Object.values(C9)[i][1]) && ginv.family === 'C9' && ginv.elements === 4 && ginv.documents === 2 && ginv.target === 'digitalisimo-global', `inventario: los siete de C9 con su widget propio y el widget global (4 instancias en 2 documentos) pasa al widget global propio (${JSON.stringify(c9inv.map((r) => [r.widget, r.family, r.target, r.elements]).concat([[ginv.widget, ginv.elements, ginv.documents, ginv.target]]))})`);
const sv = last.services || {};
ok(t.services && Object.entries(C9).every(([id, [, own]]) => (t.services[id] || '').split(' ')[0] === own), `C9: la Plantilla, los cuatro de Facebook, PayPal y Stripe pasan a los propios (${JSON.stringify(t.services)})`);
ok(sv.t1 && sv.t1.overrides === 1 && /digi-template/.test(t.services.t1 || '') && /digi-fb__plugin/.test(t.services.f4 || ''), 'C9: la Plantilla conserva su sobrescritura de F7b y el CSS personalizado pasa a las clases propias (Plantilla y Página de Facebook)');
ok(sv.x1 && sv.x1.type === 'digitalisimo-global' && sv.x1.templateID > 0 && null === sv.x1.global && sv.x1.title === 'Encabezado global', `C9: la instancia global pasa al widget global propio con su id, su templateID y sus ajustes: sigue vinculada a su plantilla, sin copiar el contenido (${JSON.stringify(sv.x1)})`);
ok(sv.x2 && sv.x2.type === 'digitalisimo-global' && sv.x2.templateID > 0 && t.gw_quote && t.gw_quote.gwq === 'digitalisimo-blockquote', `C9: la instancia de un Blockquote de PRO pasa al widget global propio y su plantilla llega a la Cita en la misma pasada (${JSON.stringify(sv.x2)} · ${JSON.stringify(t.gw_quote)})`);
ok(sv.x3 && sv.x3.type === 'global' && sv.x3.templateID === 999999 && last.codes && last.codes.global === 3 && last.codes.global_missing === 1 && /Instancia del widget global cuya plantilla no existe/.test(last.report || ''), `C9: la instancia sin plantilla se queda y el informe la cuenta (pro.global 3, pro.global_missing 1: ${JSON.stringify(last.codes)})`);
ok(t.header_global && t.header_global.x9 === 'digitalisimo-global', `C9: la instancia global de la cabecera también pasa al widget global propio (${JSON.stringify(t.header_global)})`);
ok(t.gw_heading && t.gw_heading.gwh === 'heading', 'C9: la plantilla de widget global se conserva sin cambios');
const rv = (by('revert')[0] || {}).types || {};
ok(rv.navigation && Object.entries({ v1: 'nav-menu', v2: 'mega-menu', v3: 'off-canvas', v4: 'search', ep2: 'bdt-navbar', ep3: 'bdt-mega-menu' }).every(([id, type]) => (rv.navigation[id] || '').split(' ')[0] === type) && rv.nav_header && (rv.nav_header.v9 || '').split(' ')[0] === 'nav-menu' && (rv.nav_header.v8 || '').split(' ')[0] === 'search', `C3: --revert devuelve los de PRO y los de Element Pack (${JSON.stringify([rv.navigation, rv.nav_header])})`);
ok(rv.loops && Object.entries({ p1: 'posts', p2: 'archive-posts', p3: 'portfolio', p4: 'taxonomy-filter', p5: 'loop-grid', p6: 'loop-carousel', ep2: 'bdt-post-grid', ep3: 'bdt-dynamic-grid', ps1: 'prime-slider-blog' }).every(([id, type]) => (rv.loops[id] || '').split(' ')[0] === type) && rv.header && (rv.header.hp1 || '').split(' ')[0] === 'posts', `C5: --revert devuelve los de PRO y los de Element Pack y Prime Slider (${JSON.stringify(rv.loops)})`);
for (const [doc, ids] of Object.entries(C7)) {
  ok(rv[doc] && Object.entries(ids).every(([id, [pro]]) => (rv[doc][id] || '').split(' ')[0] === pro), `C7: --revert devuelve en ${doc} los widgets de PRO y los de Element Pack (${JSON.stringify(rv[doc])})`);
}
ok(rv.services && Object.entries(C9).every(([id, [pro]]) => (rv.services[id] || '').split(' ')[0] === pro) && ['x1', 'x2', 'x3'].every((id) => rv.services[id] === 'global') && rv.header_global && rv.header_global.x9 === 'global' && rv.gw_quote && rv.gw_quote.gwq === 'blockquote', `C9: --revert devuelve los widgets de PRO, las instancias globales y el Blockquote de la plantilla de widget (${JSON.stringify(rv.services)} · ${JSON.stringify(rv.header_global)})`);
ok(rv.content && Object.entries(C2).every(([id, w]) => (rv.content[id] || '').split(' ')[0] === w), `C2: --revert devuelve los catorce widgets de PRO (${JSON.stringify(rv.content)})`);
ok(rv.content && (rv.content.ep1 || '').split(' ')[0] === 'bdt-countdown', `C2: --revert devuelve la cuenta regresiva de Element Pack (${rv.content && rv.content.ep1})`);
ok(rv.carousels && Object.entries({ n1: 'nested-carousel', s1: 'slides', m1: 'media-carousel', t1: 'testimonial-carousel', r1: 'reviews' }).every(([id, type]) => (rv.carousels[id] || '').split(' ')[0] === type), `C1: --revert devuelve los carruseles de PRO (${JSON.stringify(rv.carousels)})`);
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
ok(rv.single && Object.entries(C4).every(([id, [pro]]) => (rv.single[id] || '').split(' ')[0] === pro) && rv.single.e1 === 'bdt-post-info', `C4: --revert devuelve los elementos de tema de PRO y el widget de Element Pack (${JSON.stringify(rv.single)})`);
ok(rv.forms && Object.entries({ k1: 'form', k2: 'login', b1: 'bdt-contact-form', b2: 'bdt-mailchimp', b3: 'bdt-webhook-form', b4: 'bdt-user-login' }).every(([id, type]) => (rv.forms[id] || '').split(' ')[0] === type) && rv.header && rv.header.k9 === 'login', `C6: --revert devuelve el Formulario y el Inicio de sesión de PRO y los formularios de Element Pack (${JSON.stringify(rv.forms)}; cabecera: ${rv.header && rv.header.k9})`);
ok(Object.entries(C8).every(([id, [doc, pro]]) => ((rv[doc] || {})[id] || '').split(' ')[0] === pro), `C8: --revert devuelve las variantes de PRO (${JSON.stringify([rv.float, rv.bar, rv.bio, rv.header2])})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
