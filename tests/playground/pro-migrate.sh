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
# (F8 → PRO → propio en la misma pasada). C7: con el WooCommerce fijado (tests/playground/woocommerce-pin.sh, montado y
# activado antes de setup.php), los treinta widgets de WooCommerce de PRO en una plantilla de producto, una de archivo de
# productos, una página y una cabecera, y tres de Element Pack (`bdt-wc-add-to-cart`, `bdt-wc-products` y
# `bdt-wc-mini-cart`) que F8 lleva a los de PRO y la etapa `pro` a los propios en la misma pasada.
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
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 3 && row.documents === 2 && row.revisions === 1 && inv.where && inv.where.page === 5 && inv.where.header === 3, `inventario: 3 Blockquote en 2 documentos (página y cabecera), familia C2, destino la Cita y la revisión aparte; widgets de PRO en 5 páginas (citas, carruseles, contenido, navegación y carrito de C7) y 3 cabeceras (la de la cita, la de navegación y la de la tienda) (${JSON.stringify(row).slice(0, 200)})`);
const c1 = ['nested-carousel', 'slides', 'media-carousel', 'testimonial-carousel', 'reviews'].map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c1.every((r) => r.family === 'C1' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los cinco carruseles de PRO, familia C1, cada uno con su widget propio de destino (${JSON.stringify(c1.map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const C2 = { e1: 'animated-headline', e2: 'code-highlight', e3: 'table-of-contents', e4: 'share-buttons', e5: 'call-to-action', e6: 'flip-box', e7: 'price-list', e8: 'price-table', e9: 'gallery', e10: 'hotspot', e11: 'lottie', e12: 'video-playlist', e13: 'countdown', e14: 'progress-tracker' };
const c2 = Object.values(C2).map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c2.every((r) => r.family === 'C2' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los catorce widgets de contenido de PRO, familia C2, cada uno con su widget propio de destino (${JSON.stringify(c2.filter((r) => !(r.family === 'C2' && r.elements === 1 && r.target === `digitalisimo-${r.widget}`)).map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const C3 = { 'nav-menu': ['digitalisimo-nav-menu', 2, 2], 'mega-menu': ['digitalisimo-mega-menu', 1, 1], 'off-canvas': ['digitalisimo-nested-off-canvas', 1, 1], search: ['digitalisimo-search', 2, 2] };
const c3 = Object.keys(C3).map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c3.every((r) => r.family === 'C3' && r.target === C3[r.widget][0] && r.elements === C3[r.widget][1] && r.documents === C3[r.widget][2]), `inventario: los cuatro widgets de navegación de PRO, familia C3, con su widget propio de destino; el Menú y la Búsqueda también en la cabecera (${JSON.stringify(c3.map((r) => [r.widget, r.family, r.target, r.elements, r.documents]))})`);
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
ok(first.state && first.state.counts.pro === 11 && first.state.counts.pro_elements === 81 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 81 widgets en 11 documentos (8 de C0 y C1, los 14 de C2 y la cuenta regresiva que F8 trae de Element Pack, y los 6 de C3 de la página —con el Navbar y el Mega Menu de Element Pack— y los 2 de la cabecera de navegación, los 15 de C4 y la información de la entrada que F8 trae de Element Pack, y los 30 de C7 —el carrito del menú, dos veces— y los tres de WooCommerce que F8 trae de Element Pack) y lo informa (${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
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
const rv = (by('revert')[0] || {}).types || {};
ok(rv.navigation && Object.entries({ v1: 'nav-menu', v2: 'mega-menu', v3: 'off-canvas', v4: 'search', ep2: 'bdt-navbar', ep3: 'bdt-mega-menu' }).every(([id, type]) => (rv.navigation[id] || '').split(' ')[0] === type) && rv.nav_header && (rv.nav_header.v9 || '').split(' ')[0] === 'nav-menu' && (rv.nav_header.v8 || '').split(' ')[0] === 'search', `C3: --revert devuelve los de PRO y los de Element Pack (${JSON.stringify([rv.navigation, rv.nav_header])})`);
for (const [doc, ids] of Object.entries(C7)) {
  ok(rv[doc] && Object.entries(ids).every(([id, [pro]]) => (rv[doc][id] || '').split(' ')[0] === pro), `C7: --revert devuelve en ${doc} los widgets de PRO y los de Element Pack (${JSON.stringify(rv[doc])})`);
}
ok(rv.content && Object.entries(C2).every(([id, w]) => (rv.content[id] || '').split(' ')[0] === w), `C2: --revert devuelve los catorce widgets de PRO (${JSON.stringify(rv.content)})`);
ok(rv.content && (rv.content.ep1 || '').split(' ')[0] === 'bdt-countdown', `C2: --revert devuelve la cuenta regresiva de Element Pack (${rv.content && rv.content.ep1})`);
ok(rv.carousels && Object.entries({ n1: 'nested-carousel', s1: 'slides', m1: 'media-carousel', t1: 'testimonial-carousel', r1: 'reviews' }).every(([id, type]) => (rv.carousels[id] || '').split(' ')[0] === type), `C1: --revert devuelve los carruseles de PRO (${JSON.stringify(rv.carousels)})`);
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
ok(rv.single && Object.entries(C4).every(([id, [pro]]) => (rv.single[id] || '').split(' ')[0] === pro) && rv.single.e1 === 'bdt-post-info', `C4: --revert devuelve los elementos de tema de PRO y el widget de Element Pack (${JSON.stringify(rv.single)})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
