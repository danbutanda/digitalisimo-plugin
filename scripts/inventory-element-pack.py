#!/usr/bin/env python3
"""Inventario estático de Element Pack y Prime Slider leído de sus ZIP: nunca extrae ni ejecuta su código.

Uso, desde cualquier carpeta y siempre con -I (nada del directorio actual se importa):

    python3 -I scripts/inventory-element-pack.py ZIP[@VERSIÓN] [ZIP[@VERSIÓN] ...] [--out DIR] [--update-docs]

- Cada ZIP se reconoce por la cabecera de su plugin (Element Pack Pro o Addon, Prime Slider Pro o Lite) y se lee con
  `reference_archive.ReferenceArchive`, que lo rechaza entero ante rutas absolutas o con `..`, enlaces simbólicos o
  tamaños excesivos. La versión sale de la cabecera, salvo que se indique con `@VERSIÓN`. Se aceptan varias versiones
  de un mismo plugin: el inventario es su unión y cada widget o extensión dice en qué versiones está (`present_in`).
- La base de Element Pack es `docs/element-pack-inventory.json` (9.9.1, cuando la referencia aún era una carpeta). Un
  widget o una extensión de la base que falta en el ZIP más reciente de Element Pack se marca `moved_to_addon`: el
  changelog de 10.0.0 dice que los widgets obsoletos pasaron al plugin Element Pack Addon con los mismos nombres. Si
  además se pasa el ZIP del Addon, el traslado se confirma con él.
- `--out DIR` escribe el inventario completo de cada familia (`<familia>-inventory.json` y `.md`). Sin `--out` sólo
  se imprime el resumen.
- `--update-docs` anota `docs/element-pack-inventory.json` y regenera su `.md` sin cambiar su formato: añade
  `present_in` y `moved_to_addon` a cada widget y las extensiones de la versión más reciente, pero nunca quita ni añade
  IDs, porque `docs/migration-map.json` y `tests/elements-legacy-decisions.mjs` exigen exactamente los mismos.
- Ya no escribe `docs/migration-map.json` (sus decisiones se mantienen a mano y con `legacy-decisions-doc.py`) ni los
  inventarios aparte del marco y del grafo de dependencias: ambos van en el JSON de `--out`.
"""

import argparse
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.dont_write_bytecode = True  # no deja __pycache__ en scripts/
sys.path.insert(0, str(HERE))
from reference_archive import ReferenceArchive, UnsafeArchive  # noqa: E402

DOCS = ROOT / 'docs'
DOCS_INVENTORY = DOCS / 'element-pack-inventory.json'
DOCS_MIGRATION_MAP = DOCS / 'migration-map.json'
# Versión de la que salió docs/element-pack-inventory.json; una vez anotado, el propio JSON la guarda.
DEFAULT_BASELINE_VERSION = '9.9.1'
MAX_HEADER_BYTES = 8192

INTEGRATIONS = {
    'acf': 'Advanced Custom Fields',
    'bbpress': 'bbPress',
    'buddypress': 'BuddyPress',
    'charitable': 'Charitable',
    'edd': 'Easy Digital Downloads',
    'events-calendar': 'The Events Calendar',
    'fooevents': 'FooEvents',
    'give': 'GiveWP',
    'download-monitor': 'Download Monitor',
    'layer-slider': 'LayerSlider',
    'learnpress': 'LearnPress',
    'mailchimp': 'Mailchimp',
    'tutor': 'Tutor LMS',
    'revolution-slider': 'Slider Revolution',
    'wc': 'WooCommerce',
    'woocommerce': 'WooCommerce',
    'we-forms': 'weForms',
}

ENGINE_RULES = [
    ('carousel', ('carousel', 'slider', 'slideshow', 'swiper')),
    ('forms', ('form', 'login', 'register', 'checkout', 'subscribe')),
    ('commerce', ('product', 'cart', 'shop', 'category-grid')),
    ('query', ('post-', 'blog-', 'news-', 'grid', 'timeline', 'table')),
    ('navigation', ('menu', 'nav-', 'breadcrumb', 'tabs', 'accordion')),
    ('media', ('image', 'gallery', 'video', 'lightbox', 'iframe')),
    ('interaction', ('modal', 'popup', 'offcanvas', 'tooltip', 'toggle')),
    ('data-visualization', ('chart', 'graph', 'counter', 'progress', 'pie')),
]

PILOT_MIGRATIONS = {
    'bdt-accordion': {
        'target': 'digitalisimo-accordion',
        'status': 'native details widget with published Elementor and optional Anywhere templates plus item and state icons; advanced controls, legacy adapter, visual comparison and multisite runtime pending',
        'same_name_controls': [ 'tabs', 'source', 'tab_title', 'tab_content', 'template_id', 'anywhere_id', 'repeater_icon', 'active_item', 'multiple', 'title_html_tag', 'show_custom_icon', 'accordion_icon', 'accordion_active_icon' ],
    },
    'bdt-advanced-button': {
        'target': 'digitalisimo-advanced-button',
        'status': 'new CSS-only button with safe link, icon, badge and nine lightweight effects; legacy adapter, full style parity and multisite runtime pending',
        'same_name_controls': [ 'text', 'link', 'button_size', 'align', 'button_icon', 'icon_align', 'icon_indent', 'show_button_badge', 'badge_text', 'badge_align', 'button_css_id', 'button_effect', 'advanced_button_text_color', 'advanced_button_hover_text_color', 'advanced_button_padding', 'advanced_button_radius' ],
    },
    'bdt-animated-link': {
        'target': 'digitalisimo-animated-link',
        'status': 'implemented with conditional legacy read adapter; permanent conversion and visual comparison pending',
        'same_name_controls': [
            'link_style', 'link_text', 'link_url', 'link_alignment',
            'link_text_color', 'link_hover_text_color', 'link_style_color',
            'link_padding', 'link_typography',
        ],
    },
    'bdt-fancy-list': {
        'target': 'digitalisimo-fancy-list',
        'status': 'new widget implemented; legacy adapter, full style controls and visual comparison pending',
        'same_name_controls': [
            'layout_style', 'icon_list', 'columns', 'list_item_space_between',
            'show_number_icon', 'title_tags', 'content_position',
        ],
    },
    'bdt-document-viewer': {
        'target': 'digitalisimo-document-viewer',
        'status': 'new independent widget implemented; legacy adapter and visual comparison pending',
        'same_name_controls': [ 'file_source', 'document_height', 'viewer_type' ],
    },
    'bdt-brand-carousel': {
        'target': 'digitalisimo-brand-carousel',
        'status': 'new widget on shared native carousel engine; legacy adapter and visual comparison pending',
        'same_name_controls': [ 'brand_items', 'image_size', 'columns', 'navigation', 'item_match_height', 'show_brand_name', 'brand_html_tag', 'show_website_link' ],
    },
    'bdt-logo-carousel': {
        'target': 'digitalisimo-logo-carousel',
        'status': 'new independent widget on shared native carousel engine; legacy adapter and visual comparison pending',
        'same_name_controls': [ 'logo_items', 'columns', 'item_gap', 'height', 'navigation' ],
    },
    'bdt-advanced-heading': {
        'target': 'digitalisimo-advanced-heading',
        'status': 'new semantic widget implemented; advanced effects, legacy adapter and visual comparison pending',
        'same_name_controls': [ 'sub_heading', 'main_heading', 'split_main_heading', 'split_text', 'link', 'header_size', 'align', 'advanced_heading_visibility', 'advanced_heading' ],
    },
    'bdt-brand-grid': {
        'target': 'digitalisimo-brand-grid',
        'status': 'new semantic CSS grid widget implemented; legacy adapter and visual comparison pending',
        'same_name_controls': [ 'brand_items', 'image_size', 'columns', 'column_gap', 'row_gap', 'brand_event', 'show_brand_name', 'brand_html_tag', 'show_website_link' ],
    },
    'bdt-logo-grid': {
        'target': 'digitalisimo-logo-grid',
        'status': 'new independent CSS grid widget implemented; legacy adapter and visual comparison pending',
        'same_name_controls': [ 'logo_list', 'thumbnail_size', 'layout', 'columns', 'column_gap', 'height', 'logo_size_cover' ],
    },
    'bdt-scroll-button': {
        'target': 'digitalisimo-scroll-button',
        'status': 'new accessible local-anchor widget implemented; advanced effects, legacy adapter and visual comparison pending',
        'same_name_controls': [ 'duration', 'offset', 'scroll_button_text', 'section_id', 'scroll_button_position', 'scroll_button_align', 'button_icon', 'icon_align' ],
    },
}

# Orden indicado por el usuario: estos 53 widgets se completan antes del resto.
# Las 10 funciones adicionales no tienen get_name() y se registran aparte.
USER_PRIORITY_WIDGET_IDS = (
    'bdt-accordion', 'bdt-advanced-button', 'bdt-advanced-divider',
    'bdt-advanced-heading', 'bdt-advanced-icon-box', 'bdt-animated-heading',
    'bdt-brand-grid', 'bdt-brand-carousel', 'bdt-breadcrumbs', 'bdt-dual-button',
    'bdt-call-out', 'bdt-comparison-list', 'bdt-content-switcher',
    'bdt-custom-gallery', 'bdt-creative-button', 'bdt-device-slider',
    'bdt-fancy-card', 'bdt-fancy-list', 'bdt-fancy-icons', 'bdt-fancy-slider',
    'bdt-fancy-tabs', 'bdt-featured-box', 'bdt-google-reviews',
    'bdt-icon-mobile-menu', 'bdt-iconnav', 'bdt-lottie-image', 'bdt-logo-grid',
    'bdt-navbar', 'bdt-notification', 'bdt-offcanvas', 'bdt-price-list',
    'bdt-price-table', 'bdt-product-grid', 'bdt-post-grid', 'bdt-post-list',
    'bdt-profile-card', 'bdt-qrcode', 'bdt-slider',
    'bdt-slinky-vertical-menu', 'bdt-search', 'bdt-single-post',
    'bdt-social-share', 'bdt-sub-menu', 'bdt-switcher', 'bdt-tabs',
    'bdt-table', 'bdt-table-of-content', 'bdt-tags-cloud', 'bdt-total-count',
    'bdt-user-login', 'bdt-user-register', 'bdt-vertical-menu',
    'bdt-video-player',
)
USER_PRIORITY_ORDER = {widget_id: index for index, widget_id in enumerate(USER_PRIORITY_WIDGET_IDS, 1)}
USER_PRIORITY_EXTENSIONS = (
    ('Backdrop Filter', 'modules/backdrop-filter'),
    ('Floating Effects', 'modules/floating-effects'),
    ('Notation', 'modules/notation'),
    ('Shape Builder', 'modules/shape-builder'),
    ('Text Gradient Background', 'modules/text-gradient-background'),
    ('Realistic Image Shadow', 'modules/realistic-image-shadow'),
    ('Visibility Controls', 'modules/visibility-controls'),
    ('Wrapper Link', 'modules/wrapper-link'),
    ('Duplicator', 'includes/class-duplicator.php'),
    ('SVG Support', 'includes/class-svg-support.php'),
)

SECURITY_MARKERS = {
    'ajax': r'wp_ajax|admin-ajax|\bajax\b',
    'rest': r'register_rest_route|/wp-json/|\bREST_API\b',
    'upload': r'wp_handle_upload|\$_FILES|move_uploaded_file',
    'remote_request': r'wp_remote_(?:get|post|request)|curl_',
    'iframe': r'<iframe|\biframe\b',
    'raw_html': r'Controls_Manager::HTML|\bwp_kses\b|\bdo_shortcode\b',
}


def method_body(source, name):
    match = re.search(r'function\s+' + re.escape(name) + r'\s*\([^)]*\)\s*(?::\s*[^\{]+)?\{', source)
    if not match:
        return ''
    depth = 1
    pos = match.end()
    start = pos
    while pos < len(source) and depth:
        if source[pos] == '{':
            depth += 1
        elif source[pos] == '}':
            depth -= 1
        pos += 1
    return source[start:pos - 1]


def quoted(body):
    return sorted(set(re.findall(r"['\"]([a-zA-Z0-9_./-]+)['\"]", body)))


def plugin_dependency(slug):
    for prefix, name in INTEGRATIONS.items():
        if slug == prefix or slug.startswith(prefix + '-'):
            return name
    return None


def engine_for(slug):
    for engine, markers in ENGINE_RULES:
        if any(marker in slug for marker in markers):
            return engine
    return 'content-and-layout'


def prime_slider_dependency(slug):
    if slug.startswith('woo'):
        return 'WooCommerce'
    if slug == 'event-calendar':
        return 'The Events Calendar'
    return None


FAMILIES = {
    'element-pack': {
        'label': 'Element Pack',
        # El orden importa: «Element Pack Addon» también empieza por «Element Pack».
        'editions': (('addon', r'^Element Pack Addon\b'), ('lite', r'^Element Pack Lite\b'), ('pro', r'^Element Pack\b')),
        'namespace': 'ElementPack',
        'asset_stem': 'ep-',
        'dependency': plugin_dependency,
        'default_engine': None,
        'base_marker': ('Module_Base', 'ElementPack Base'),
        # Extensiones que no son módulos: (nombre, slug, archivos de includes/ que declaran sus controles).
        'include_extensions': (('Duplicator', 'duplicator', ('includes/class-duplicator.php',)), ('SVG Support', 'svg-support', ('includes/class-svg-support.php',))),
        'addon_marker': r'Element Pack Addon',
        'addon_claim': r'(\d+)\s+(?:deprecated\s+)?widgets?\s+moved\s+to\s+the\s+new\s+\W?Element Pack Addon',
        'user_priority': True,
        'loader_global_assets': ['bdt-uikit.css', 'ep-helper.css', 'bdt-uikit.min.js'],
        'dependency_decisions': {'ElementPack Base': 'REIMPLEMENT', 'UIkit': 'REMOVE / REIMPLEMENT', 'Swiper': 'KEEP ONLY IF NEEDED', 'Element Pack admin and licensing': 'REMOVE'},
    },
    'prime-slider': {
        'label': 'Prime Slider',
        'editions': (('pro', r'^Prime Slider Pro\b'), ('lite', r'^Prime Slider\b')),
        'namespace': 'PrimeSlider',
        'asset_stem': 'ps-',
        'dependency': prime_slider_dependency,
        'default_engine': 'carousel',
        'base_marker': None,
        'include_extensions': (
            ('Advanced Animation', 'animation-extension', ('includes/class-animation-extension.php', 'includes/class-animation-controls.php')),
            ('Swiper Effects', 'swiper-effects', ('includes/class-swiper-effects.php',)),
        ),
        'addon_marker': None,
        'addon_claim': None,
        'user_priority': False,
        'loader_global_assets': ['bdt-uikit (Prime Slider Lite)'],
        'dependency_decisions': {'UIkit': 'REMOVE / REIMPLEMENT', 'Swiper': 'KEEP ONLY IF NEEDED', 'GSAP, anime and bundled effect demos': 'REMOVE / REIMPLEMENT (check redistribution license)', 'Prime Slider admin and licensing': 'REMOVE'},
    },
}

GET_NAME = re.compile(r"function\s+get_name\s*\(\s*\)\s*(?::\s*[^\{]+)?\{\s*return\s*['\"]([^'\"]+)")
CONTROL_KEY = re.compile(r"add_(?:responsive_)?control\(\s*['\"]([^'\"]+)['\"]")
DYNAMIC_CONTROL_KEY = re.compile(r"add_(?:responsive_)?control\(\s*[^'\"\s)]")
GROUP_CONTROL = re.compile(r"add_group_control\([^;]*?['\"]name['\"]\s*=>\s*['\"]([^'\"]+)", re.S)
ELEMENT_HOOK = re.compile(r"['\"]elementor/element/([a-z0-9_-]+)/[a-z0-9_-]+/(?:before|after)_section_(?:start|end)['\"]")
ANY_ELEMENT_HOOK = re.compile(r"['\"]elementor/element/(?:before|after)_section_(?:start|end)['\"]")
VARIABLE_ELEMENT_HOOK = re.compile(r"['\"]elementor/element/['\"]\s*\.")
FRONTEND_HOOK = re.compile(r"['\"]elementor/frontend/([a-z]+)/(?:before|after)_render['\"]")


def version_key(version):
    return tuple(int(part) for part in re.findall(r'\d+', str(version))) or (0,)


def header(source, field):
    match = re.search(r'^[ \t/*#@]*' + re.escape(field) + r'\s*:\s*(.+?)\s*$', source, re.M)
    return match.group(1).strip() if match else None


def identify(archive, requested_version):
    tops = archive.top_dirs()
    if len(tops) != 1:
        raise SystemExit(f'{archive.path.name}: se esperaba una sola carpeta raíz y hay {len(tops)}')
    top = tops[0]
    for candidate in archive.files(top + '/'):
        if candidate.count('/') != 1 or not candidate.endswith('.php'):
            continue
        source = archive.read_text(candidate)[:MAX_HEADER_BYTES]
        plugin_name = header(source, 'Plugin Name')
        if not plugin_name:
            continue
        for family, config in FAMILIES.items():
            for edition, pattern in config['editions']:
                if re.search(pattern, plugin_name, re.I):
                    return {
                        'archive': archive.path.name,
                        'sha256': archive.sha256,
                        'plugin_dir': top,
                        'plugin_file': candidate,
                        'plugin_name': plugin_name,
                        'version': requested_version or header(source, 'Version') or 'desconocida',
                        'version_from_header': header(source, 'Version'),
                        'family': family,
                        'edition': edition,
                    }
        raise SystemExit(f'{archive.path.name}: «{plugin_name}» no es Element Pack ni Prime Slider')
    raise SystemExit(f'{archive.path.name}: no se encontró la cabecera «Plugin Name» en {top}/*.php')


def label_for(meta):
    return meta['version'] if meta['edition'] == 'pro' else f"{meta['version']} ({meta['edition']})"


def own_widget_ids():
    found = {}
    for own_file in sorted((ROOT / 'digitalisimo-elements/modules').rglob('widgets/*.php')):
        own_source = own_file.read_text(encoding='utf-8', errors='replace')
        own_match = re.search(r"function\s+get_name\s*\([^)]*\)\s*(?::\s*[^\{]+)?\{\s*return\s*['\"]([^'\"]+)", own_source)
        if own_match:
            found[own_match.group(1)] = str(own_file.relative_to(ROOT))
    return found


def direct_children(archive, directory, suffix):
    directory = directory.rstrip('/') + '/'
    return [name for name in archive.files(directory) if '/' not in name[len(directory):] and name.endswith(suffix)]


def widget_entry(archive, meta, config, source_file, source, own_ids):
    top = meta['plugin_dir']
    module_dir = source_file.rsplit('/widgets/', 1)[0]
    slug = module_dir.rsplit('/', 1)[1]
    widget_id = GET_NAME.search(source).group(1)
    class_match = re.search(r'\bclass\s+(\w+)\s+extends\s+([\\\w]+)', source)
    namespace_match = re.search(r'\bnamespace\s+([^;]+);', source)
    title_match = re.search(r"function\s+get_title\s*\([^)]*\).*?__\(\s*['\"]([^'\"]+)", source, re.S)
    category = quoted(method_body(source, 'get_categories'))
    styles = quoted(method_body(source, 'get_style_depends')) + quoted(method_body(source, '_get_style_depends'))
    scripts = quoted(method_body(source, 'get_script_depends'))
    styles = sorted(set(x for x in styles if x not in ('ep_is_edit_mode',)))
    scripts = sorted(set(x for x in scripts if x not in ('ep_is_edit_mode',)))
    module_file = module_dir + '/module.php'
    module_source = archive.read_text(module_file) if archive.exists(module_file) else ''
    stem = config['asset_stem'] + slug
    css = sorted(name[len(top) + 1:] for name in direct_children(archive, f'{top}/assets/css', '.css') if name.rsplit('/', 1)[1].startswith(stem))
    js = sorted(name[len(top) + 1:] for name in direct_children(archive, f'{top}/assets/js/modules', '.js') if name.rsplit('/', 1)[1].startswith(stem))
    skins = sorted(direct_children(archive, module_dir + '/skins', '.php'))
    control_imports = sorted(set(re.findall(re.escape(config['namespace']) + r'\\(?:Includes\\Controls|Traits)\\[\\\w]+', source)))
    direct_uikit = bool(re.search(r'\b(?:bdt-uikit|UIkit|uikit)\b', source, re.I))
    js_uses_uikit = any(re.search(r'\b(?:UIkit|uikit)\b', archive.read_text(f'{top}/{path}'), re.I) for path in js)
    direct_swiper = bool(re.search(r'\bswiper\b|Global_Swiper_Controls', source, re.I))
    dep = config['dependency'](slug)
    engine = config['default_engine'] or engine_for(slug)
    security = [key for key, pattern in SECURITY_MARKERS.items() if re.search(pattern, source + module_source, re.I)]
    has_query = bool(re.search(r'\bWP_Query\b|\bget_posts\b|\bquery_posts\b|Group_Control_Query|query_\w+_posts', source))
    dynamic = bool(re.search(r'Dynamic_Tags|\bdynamic\b|get_post_meta|\bget_field\b|Group_Control_Query', source, re.I))
    responsive = bool(re.search(r'add_responsive_control|add_responsive_group_control', source))
    if dep:
        classification = 'C - INTEGRATION'
    elif engine == 'carousel':
        classification = 'B - ENGINE DERIVATIVE'
    else:
        classification = 'A - CORE CANDIDATE'
    if slug in ('post-content', 'post-title', 'post-excerpt', 'featured-image', 'breadcrumbs'):
        classification = 'E - REVIEW FOR DUPLICATION'
    for prefix in ('bdt-', 'prime-slider-'):
        if widget_id.startswith(prefix):
            overlap = own_ids.get(widget_id[len(prefix):])
            break
    else:
        overlap = own_ids.get(widget_id)
    if overlap:
        classification = 'E - REVIEW FOR DUPLICATION'
    complexity = 'high' if dep or security or skins or len(control_imports) > 2 else 'medium' if engine != 'content-and-layout' or js else 'low'
    priority = 1 if classification.startswith('A') and complexity == 'low' else 2 if classification.startswith('B') else 3
    base_marker = config['base_marker']
    original_dependencies = sorted(set(styles + scripts + ([base_marker[1]] if base_marker and class_match and class_match.group(2) == base_marker[0] else [])))
    user_priority = config['user_priority']
    return {
        'name': title_match.group(1) if title_match else slug.replace('-', ' ').title(),
        'legacy_widget_id': widget_id,
        'directory': module_dir,
        'source_file': source_file,
        'original_class': ((namespace_match.group(1).strip() + '\\') if namespace_match else '') + (class_match.group(1) if class_match else ''),
        'original_base_class': class_match.group(2) if class_match else None,
        'category': category,
        'dependencies': original_dependencies,
        'css': css,
        'js': js,
        'uikit_usage': 'direct or module JS' if direct_uikit or js_uses_uikit else 'indirect via original loader',
        'swiper_usage': direct_swiper,
        'base_classes': [class_match.group(2)] if class_match else [],
        'custom_controls_and_traits': control_imports,
        'skins': skins,
        'ajax': 'ajax' in security,
        'rest': 'rest' in security,
        'wp_query': has_query,
        'dynamic_content': dynamic,
        'woocommerce': dep == 'WooCommerce',
        'acf': dep == 'Advanced Custom Fields',
        'other_plugin_dependency': dep,
        'responsive_controls': responsive,
        'security_considerations': security,
        'migration_complexity': complexity,
        'priority': priority,
        'user_priority_tier': (1 if widget_id in USER_PRIORITY_ORDER else 2) if user_priority else None,
        'user_priority_order': USER_PRIORITY_ORDER.get(widget_id) if user_priority else None,
        'classification': classification,
        'proposed_engine': engine,
        'existing_elements_widget_candidate': overlap,
        'implementation_status': PILOT_MIGRATIONS.get(widget_id, {}).get('status', 'pending'),
        'dependency_decisions': config['dependency_decisions'],
        'review_note': 'Static inference only; verify runtime dependencies, controls, output, license, and visual behavior before migration.',
    }


def extension_entry(archive, name, slug, files, source_path):
    source = ''.join(archive.read_text(path) for path in files if path.endswith('.php'))
    element_types = set(ELEMENT_HOOK.findall(source)) | set(FRONTEND_HOOK.findall(source))
    if ANY_ELEMENT_HOOK.search(source):
        element_types.add('*')
    if VARIABLE_ELEMENT_HOOK.search(source):
        element_types.add('variable')
    return {
        'name': name,
        'slug': slug,
        'kind': 'kit-tab' if 'elementor/kit/register_tabs' in source else 'extension',
        'source': source_path,
        'control_keys': sorted(set(CONTROL_KEY.findall(source))),
        'group_controls': sorted(set(GROUP_CONTROL.findall(source))),
        'dynamic_control_keys': bool(DYNAMIC_CONTROL_KEY.search(source)),
        'element_types': sorted(element_types),
    }


def changelog_facts(archive, meta, config):
    text = ''
    for name in ('changelog.txt', 'readme.txt'):
        path = f"{meta['plugin_dir']}/{name}"
        if archive.exists(path):
            text += archive.read_text(path, limit=4 * 1024 * 1024)
    facts = {'mentions_addon': False, 'claimed_moved_widgets': None}
    if config['addon_marker']:
        facts['mentions_addon'] = bool(re.search(config['addon_marker'], text, re.I))
        claim = re.search(config['addon_claim'], text, re.I)
        facts['claimed_moved_widgets'] = int(claim.group(1)) if claim else None
    return facts


def inventory_source(archive, meta, own_ids):
    config = FAMILIES[meta['family']]
    top = meta['plugin_dir']
    widgets = {}
    unclassified = []
    widget_files = sorted(name for name in archive.files(f'{top}/modules/') if re.fullmatch(re.escape(top) + r'/modules/[^/]+/widgets/[^/]+\.php', name))
    for source_file in widget_files:
        source = archive.read_text(source_file)
        if not GET_NAME.search(source):
            unclassified.append(source_file)
            continue
        entry = widget_entry(archive, meta, config, source_file, source, own_ids)
        if entry['legacy_widget_id'] in widgets:
            raise SystemExit(f"{meta['archive']}: ID de widget duplicado {entry['legacy_widget_id']}")
        widgets[entry['legacy_widget_id']] = entry
    extensions = {}
    module_slugs = sorted({name.split('/')[2] for name in archive.files(f'{top}/modules/') if name.count('/') >= 3})
    for slug in module_slugs:
        if archive.is_dir(f'{top}/modules/{slug}/widgets'):
            continue
        files = archive.files(f'{top}/modules/{slug}/')
        extensions[slug] = extension_entry(archive, slug.replace('-', ' ').title(), slug, files, f'{top}/modules/{slug}')
    for name, slug, relatives in config['include_extensions']:
        paths = [f'{top}/{relative}' for relative in relatives if archive.exists(f'{top}/{relative}')]
        if paths:
            extensions[slug] = extension_entry(archive, name, slug, paths, paths[0])
    framework = {
        'base_classes': direct_children(archive, f'{top}/base', '.php'),
        'custom_control_files': [name for name in archive.files(f'{top}/includes/controls/') if name.endswith('.php')],
        'skin_files': sorted(name for name in archive.files(f'{top}/modules/') if re.fullmatch(re.escape(top) + r'/modules/[^/]+/skins/[^/]+\.php', name)),
        'original_widget_asset_files': sorted(set(path for widget in widgets.values() for path in widget['css'] + widget['js'])),
        'loader_global_assets': config['loader_global_assets'],
        'note': 'Paths describe reference code inside the archive only; no runtime dependency is approved.',
    }
    meta = dict(meta, entries=len(archive.files()), widget_files=len(widget_files), widget_ids=len(widgets), extension_count=len(extensions))
    return {'meta': meta, 'widgets': widgets, 'unclassified': unclassified, 'extensions': extensions, 'framework': framework, 'changelog': changelog_facts(archive, meta, config)}


def baseline_extension_slug(source):
    match = re.search(r'(?:^|/)(modules/[^/]+|includes/[^/]+\.php)$', source.rstrip('/'))
    if not match:
        return None
    relative = match.group(1)
    return relative.split('/')[1] if relative.startswith('modules/') else re.sub(r'^class-|\.php$', '', relative.split('/')[1])


def load_baseline(path, version):
    payload = json.loads(path.read_text(encoding='utf-8'))
    widgets = {widget['legacy_widget_id']: widget for widget in payload['widgets']}
    extensions = {}
    for extension in payload.get('user_priority_extensions', []):
        slug = baseline_extension_slug(extension['source'])
        if slug:
            extensions[slug] = {'name': extension['name'], 'slug': slug, 'source': extension['source']}
    label = f"{version or payload.get('baseline_version') or DEFAULT_BASELINE_VERSION}"
    return {'file': str(path.relative_to(ROOT)) if path.is_relative_to(ROOT) else str(path), 'label': label, 'widgets': widgets, 'extensions': extensions, 'payload': payload}


def presence(item_id, timeline):
    """Versiones en las que está, y desde cuál falta si no está en la más reciente."""
    present_in = [step['label'] for step in timeline if item_id in step['ids']]
    missing_since = None
    if timeline and item_id not in timeline[-1]['ids']:
        last = max(index for index, step in enumerate(timeline) if item_id in step['ids'])
        missing_since = timeline[last + 1]['label']
    return present_in, missing_since


def merge_family(family, parsed, baseline, treated_extensions):
    config = FAMILIES[family]
    main = sorted((item for item in parsed if item['meta']['edition'] != 'addon'), key=lambda item: version_key(item['meta']['version']))
    addons = [item for item in parsed if item['meta']['edition'] == 'addon']
    if not main:
        raise SystemExit(f"{config['label']}: hace falta al menos un ZIP del plugin principal además del Addon")
    seen = set()
    for item in main:
        key = (item['meta']['edition'], item['meta']['version'])
        if key in seen:
            raise SystemExit(f"{config['label']}: dos ZIP con la misma versión {label_for(item['meta'])}")
        seen.add(key)
    steps = [{'label': label_for(item['meta']), 'version': item['meta']['version'], 'origin': 'zip', 'ids': set(item['widgets']), 'extensions': set(item['extensions']), 'item': item} for item in main]
    if baseline:
        steps.append({'label': baseline['label'], 'version': baseline['label'], 'origin': 'baseline', 'ids': set(baseline['widgets']), 'extensions': set(baseline['extensions']), 'item': None})
    timeline = sorted(steps, key=lambda step: (version_key(step['version']), step['origin'] == 'zip'))
    latest = main[-1]
    addon_ids = set().union(*(set(item['widgets']) for item in addons)) if addons else set()
    addon_extensions = set().union(*(set(item['extensions']) for item in addons)) if addons else set()
    changelog = latest['changelog']

    def moved(item_id, missing_since, in_addon):
        if not missing_since:
            return False, None
        if addons:
            return (item_id in in_addon), ('addon_zip' if item_id in in_addon else None)
        if changelog['mentions_addon']:
            return True, 'changelog'
        return False, None

    widgets = []
    all_ids = set().union(*(step['ids'] for step in timeline))
    for widget_id in all_ids:
        source = next((step['item']['widgets'][widget_id] for step in reversed(timeline) if step['item'] and widget_id in step['item']['widgets']), None)
        entry = dict(source if source is not None else baseline['widgets'][widget_id])
        present_in, missing_since = presence(widget_id, timeline)
        is_moved, evidence = moved(widget_id, missing_since, addon_ids)
        entry['present_in'] = present_in
        entry['moved_to_addon'] = is_moved
        if missing_since:
            entry['missing_since'] = missing_since
        if evidence:
            entry['moved_to_addon_evidence'] = evidence
        widgets.append(entry)
    widgets.sort(key=lambda widget: (widget['directory'].rsplit('/', 1)[-1], widget['source_file']))

    extensions = []
    all_extensions = set().union(*(step['extensions'] for step in timeline))
    for slug in sorted(all_extensions):
        source = next((step['item']['extensions'][slug] for step in reversed(timeline) if step['item'] and slug in step['item']['extensions']), None)
        entry = dict(source if source is not None else baseline['extensions'][slug])
        present_in, missing_since = presence(slug, [dict(step, ids=step['extensions']) for step in timeline])
        is_moved, evidence = moved(slug, missing_since, addon_extensions)
        entry['present_in'] = present_in
        entry['moved_to_addon'] = is_moved
        if missing_since:
            entry['missing_since'] = missing_since
        if evidence:
            entry['moved_to_addon_evidence'] = evidence
        if treated_extensions is not None:
            entry['treated_in_migration_map'] = slug in treated_extensions
        extensions.append(entry)

    groups = defaultdict(list)
    for widget in widgets:
        if widget['legacy_widget_id'] in latest['widgets']:
            groups[widget['proposed_engine']].append(widget['legacy_widget_id'])
    moved_ids = sorted(widget['legacy_widget_id'] for widget in widgets if widget['moved_to_addon'])
    gone_ids = sorted(widget['legacy_widget_id'] for widget in widgets if widget.get('missing_since') and not widget['moved_to_addon'])
    baseline_ids = set(baseline['widgets']) if baseline else set()
    return {
        'family': family,
        'label': config['label'],
        'method': 'static source inspection read from ZIP archives; nothing was extracted or executed',
        'sources': [item['meta'] for item in main + addons],
        'baseline': {'file': baseline['file'], 'version': baseline['label'], 'widget_count': len(baseline['widgets'])} if baseline else None,
        'versions': [{'label': step['label'], 'origin': step['origin'], 'widget_count': len(step['ids']), 'extension_count': len(step['extensions'])} for step in timeline],
        'latest_version': label_for(latest['meta']),
        'latest_plugin_name': latest['meta']['plugin_name'],
        'widget_count': len(widgets),
        'latest_widget_count': len(latest['widgets']),
        'moved_to_addon': moved_ids,
        'missing_not_moved': gone_ids,
        'new_since_baseline': sorted(set(latest['widgets']) - baseline_ids) if baseline else [],
        'changelog': changelog,
        'user_priority_moved': sorted(widget_id for widget_id in moved_ids if widget_id in USER_PRIORITY_ORDER) if config['user_priority'] else [],
        'unclassified_widget_files': latest['unclassified'],
        'engine_groups': {engine: sorted(ids) for engine, ids in sorted(groups.items())},
        'extensions': extensions,
        'framework': latest['framework'],
        'dependency_graph': dependency_graph([widget for widget in widgets if widget['legacy_widget_id'] in latest['widgets']]),
        'widgets': widgets,
    }


def dependency_graph(widgets):
    nodes = {}
    edges = []
    for widget in widgets:
        widget_node = 'widget:' + widget['legacy_widget_id']
        nodes[widget_node] = {'type': 'widget', 'label': widget['name']}
        targets = (
            [('engine:' + widget['proposed_engine'], 'proposed_engine')]
            + [('handle:' + handle, 'declared_handle') for handle in widget['dependencies'] if handle != 'ElementPack Base']
            + [('base:' + item, 'extends') for item in widget['base_classes']]
            + [('control:' + item, 'uses') for item in widget['custom_controls_and_traits']]
            + [('skin:' + item, 'skin') for item in widget['skins']]
            + [('asset:' + item, 'original_asset') for item in widget['css'] + widget['js']]
            + ([('plugin:' + widget['other_plugin_dependency'], 'integration_candidate')] if widget['other_plugin_dependency'] else [])
        )
        for target, relation in targets:
            nodes.setdefault(target, {'type': target.split(':', 1)[0], 'label': target.split(':', 1)[1]})
            edges.append({'from': widget_node, 'to': target, 'relation': relation})
    return {'method': 'static source inspection; declared handles include editor-only branches and need runtime verification', 'nodes': nodes, 'edges': edges}


def cell(text):
    return str(text).replace('|', '/')


def id_list(ids):
    return ', '.join(f'`{widget_id}`' for widget_id in ids) or '—'


def moved_paragraph(family_payload):
    moved_ids = family_payload['moved_to_addon']
    claim = family_payload['changelog'].get('claimed_moved_widgets')
    if not moved_ids:
        return 'Ningún widget de la base falta en la versión más reciente.'
    evidence = {widget.get('moved_to_addon_evidence') for widget in family_payload['widgets'] if widget['moved_to_addon']}
    if 'addon_zip' in evidence:
        why = 'el ZIP del Addon los contiene'
    else:
        why = 'el changelog de la versión más reciente dice que los widgets obsoletos pasaron al plugin Element Pack Addon con los mismos nombres'
        if claim and claim != len(moved_ids):
            why += f' (declara {claim}; la diferencia sólo se resuelve con el ZIP del Addon)'
    return f'{len(moved_ids)} IDs faltan en {family_payload["latest_version"]} y se marcan `moved_to_addon` porque {why}.'


def extension_rows(extensions):
    rows = ['| Extensión | Tipo | Presente en | Tratada en `migration-map.json` | Claves de control | Elementos |', '| --- | --- | --- | --- | ---: | --- |']
    for extension in extensions:
        treated = extension.get('treated_in_migration_map')
        keys = len(extension.get('control_keys', [])) + len(extension.get('group_controls', []))
        rows.append(
            f"| {cell(extension['name'])} (`{extension['slug']}`) | {extension.get('kind', 'extension')} | {', '.join(extension['present_in'])}"
            f"{' · Addon' if extension['moved_to_addon'] else ''} | {'—' if treated is None else 'sí' if treated else '**no**'} | "
            f"{keys}{'+' if extension.get('dynamic_control_keys') else ''} | {', '.join(extension.get('element_types', [])) or '—'} |"
        )
    return rows


def render_family_markdown(family_payload):
    latest = family_payload['latest_version']
    lines = [
        f"# Inventario estático de {family_payload['label']} (ZIP)", '',
        'Generado por `scripts/inventory-element-pack.py` leyendo los ZIP sin extraerlos ni ejecutar nada. Las clasificaciones, motores y complejidades son hipótesis de triaje.', '',
        '## Versiones', '', '| Versión | Origen | Widgets | Extensiones |', '| --- | --- | ---: | ---: |',
    ]
    lines += [f"| {step['label']} | {'base (' + family_payload['baseline']['file'] + ')' if step['origin'] == 'baseline' else 'ZIP'} | {step['widget_count']} | {step['extension_count']} |" for step in family_payload['versions']]
    lines += ['', 'Fuentes:', '']
    lines += [f"- {meta['plugin_name']} {meta['version']} ({meta['edition']}): `{meta['archive']}`, sha256 `{meta['sha256']}`, {meta['entries']} archivos, {meta['widget_files']} archivos de widget, {meta['widget_ids']} IDs, {meta['extension_count']} extensiones." for meta in family_payload['sources']]
    if family_payload['baseline']:
        lines += ['', moved_paragraph(family_payload)]
        if family_payload['moved_to_addon']:
            lines += ['', f"### Movidos al Addon ({len(family_payload['moved_to_addon'])})", '', id_list(family_payload['moved_to_addon'])]
        if family_payload['user_priority_moved']:
            lines += ['', f"Del bloque prioritario: {id_list(family_payload['user_priority_moved'])}."]
        if family_payload['missing_not_moved']:
            lines += ['', f"Faltan sin constancia de traslado: {id_list(family_payload['missing_not_moved'])}."]
        lines += ['', f"Nuevos desde la base: {id_list(family_payload['new_since_baseline'])}."]
    lines += ['', f'## Extensiones ({len(family_payload["extensions"])})', '',
              'Módulos sin widgets y extensiones conocidas de `includes/`. «Claves de control» cuenta las claves literales de `add_control`, `add_responsive_control` y `add_group_control`; un «+» indica claves que se arman en tiempo de ejecución. «Presente en» sólo cuenta las versiones inventariadas: una base que no registró una extensión no prueba que faltara.', '']
    lines += extension_rows(family_payload['extensions'])
    lines += ['', '## Matriz de widgets', '', '| Widget | ID | Motor | Clase | Dependencia externa | CSS/JS | Presente en |', '| --- | --- | --- | --- | --- | --- | --- |']
    for widget in family_payload['widgets']:
        lines.append(
            f"| {cell(widget['name'])} | `{widget['legacy_widget_id']}` | {widget['proposed_engine']} | {widget['classification']} | "
            f"{widget['other_plugin_dependency'] or '—'} | {len(widget['css'])}/{len(widget['js'])} | {', '.join(widget['present_in'])}{' · Addon' if widget['moved_to_addon'] else ''} |"
        )
    lines.append('')
    return '\n'.join(lines)


def annotate_docs(family_payload):
    """Añade presencia por versión al inventario publicado sin quitar ni añadir IDs ni cambiar campos existentes."""
    payload = json.loads(DOCS_INVENTORY.read_text(encoding='utf-8'))
    merged = {widget['legacy_widget_id']: widget for widget in family_payload['widgets']}
    for widget in payload['widgets']:
        source = merged[widget['legacy_widget_id']]
        widget['present_in'] = source['present_in']
        widget['moved_to_addon'] = source['moved_to_addon']
        for optional in ('missing_since', 'moved_to_addon_evidence'):
            if source.get(optional):
                widget[optional] = source[optional]
            else:
                widget.pop(optional, None)
    extensions = {extension['slug']: extension for extension in family_payload['extensions']}
    for extension in payload.get('user_priority_extensions', []):
        source = extensions.get(baseline_extension_slug(extension['source']))
        if source:
            extension['present_in'] = source['present_in']
            extension['moved_to_addon'] = source['moved_to_addon']
    latest_label = family_payload['latest_version']
    additions = {
        'baseline_version': family_payload['baseline']['version'],
        'compared_versions': [{'plugin': meta['plugin_name'], 'version': meta['version'], 'edition': meta['edition']} for meta in family_payload['sources']],
        'moved_to_addon_count': len(family_payload['moved_to_addon']),
        'moved_to_addon_note': moved_paragraph(family_payload),
        'new_since_baseline': family_payload['new_since_baseline'],
        'extensions': [
            {key: extension[key] for key in ('name', 'slug', 'kind', 'present_in', 'moved_to_addon', 'treated_in_migration_map', 'control_keys', 'group_controls', 'dynamic_control_keys', 'element_types') if key in extension}
            for extension in family_payload['extensions'] if latest_label in extension['present_in'] or extension['moved_to_addon']
        ],
    }
    ordered = {}
    for key, value in payload.items():
        if key == 'widgets':
            ordered.update(additions)
        if key not in additions:
            ordered[key] = value
    DOCS_INVENTORY.write_text(json.dumps(ordered, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    (DOCS / 'element-pack-inventory.md').write_text(render_docs_markdown(ordered, family_payload), encoding='utf-8')
    return ordered


def render_docs_markdown(payload, family_payload):
    widgets = payload['widgets']
    unclassified = payload['unclassified_widget_files']
    latest = family_payload['latest_version']
    groups = defaultdict(list)
    for widget in widgets:
        groups[widget['proposed_engine']].append(widget['legacy_widget_id'])
    counts = Counter(widget['classification'] for widget in widgets)
    still = sum(1 for widget in widgets if latest in widget['present_in'])
    lines = [
        '# Inventario estático de Element Pack', '',
        f"Fuente local: `{payload['source']}`. Se identificaron **{len(widgets)} IDs Elementor únicos** en {len(widgets) + len(unclassified)} archivos `modules/*/widgets/*.php`.",
        'La carpeta es material de referencia, no una dependencia de ejecución ni un asset publicable. Este inventario se generó sin ejecutar PHP ajeno.', '',
        'Las clasificaciones, complejidades y motores son **hipótesis de triaje**, no una aprobación para migrar. `UIkit` aparece como dependencia indirecta del loader original; cada uso real se debe validar antes de reconstruir. La ausencia de un marcador estático no prueba ausencia de AJAX, REST, dependencias o riesgos.', '',
        '## Versiones', '',
        f"Base: {payload['baseline_version']}, inventariada cuando la referencia era la carpeta `{payload['source']}`, que ya no existe. Comparada con "
        + ', '.join(f"{item['plugin']} {item['version']}" for item in payload['compared_versions'])
        + ', leída de su ZIP sin extraerlo ni ejecutar nada (`scripts/inventory-element-pack.py --update-docs`). '
        f"De los {len(widgets)} IDs, {still} siguen en {latest}. {payload['moved_to_addon_note']} "
        f"Los documentos con esos widgets siguen necesitando su adaptador, así que el inventario y `migration-map.json` los conservan. IDs nuevos en {latest}: {id_list(payload['new_since_baseline'])}.", '',
    ]
    if family_payload['moved_to_addon']:
        lines += [f"### Movidos al Addon ({len(family_payload['moved_to_addon'])})", '', id_list(family_payload['moved_to_addon']), '']
    if family_payload['user_priority_moved']:
        lines += [f"Del bloque prioritario: {id_list(family_payload['user_priority_moved'])}.", '']
    lines += [f"## Extensiones en {latest}", '',
              f"Módulos sin widgets y extensiones de `includes/` de {latest}. «Claves de control» cuenta las claves literales de sus controles (la lista está en el JSON); un «+» indica claves que se arman en tiempo de ejecución. Una extensión no tratada en `migration-map.json` hoy se descarta al convertir. La base {payload['baseline_version']} sólo registró las {len(payload.get('user_priority_extensions', []))} extensiones prioritarias: que las demás figuren sólo en {latest} no significa que sean nuevas.", '']
    lines += extension_rows(payload['extensions'])
    lines += ['', '## Familias provisionales', '', '| Motor | Widgets |', '| --- | ---: |']
    lines += [f'| {engine} | {len(items)} |' for engine, items in sorted(groups.items())]
    lines += ['', '## Clasificación provisional', '', '| Clase | Widgets |', '| --- | ---: |']
    lines += [f'| {classification} | {count} |' for classification, count in sorted(counts.items())]
    lines += ['', '## Matriz de widgets', '', f'| Widget | ID legacy | Motor | Clase | Dependencia externa | CSS/JS | En {latest} |', '| --- | --- | --- | --- | --- | --- | --- |']
    for widget in widgets:
        status = 'sí' if latest in widget['present_in'] else 'Addon' if widget['moved_to_addon'] else 'no'
        lines.append(f"| {widget['name'].replace('|', '/')} | `{widget['legacy_widget_id']}` | {widget['proposed_engine']} | {widget['classification']} | {widget['other_plugin_dependency'] or '—'} | {len(widget['css'])}/{len(widget['js'])} | {status} |")
    lines += ['', 'El JSON incluye clase original, rutas, controles, skins, handles declarados, señales de seguridad, complejidad, estado individual y, por versión, presencia (`present_in`) y traslado al Addon (`moved_to_addon`). Las dependencias transitivas y equivalencias visuales requieren comprobación funcional antes de marcar un widget como completo.', '']
    return '\n'.join(lines)


def parse_archive_argument(argument):
    path = Path(argument)
    if path.exists() or '@' not in argument:
        return path, None
    raw_path, version = argument.rsplit('@', 1)
    if not re.fullmatch(r'[0-9A-Za-z][0-9A-Za-z.+-]{0,31}', version):
        raise SystemExit(f'Versión no válida: {version}')
    return Path(raw_path), version


def main(argv=None):
    parser = argparse.ArgumentParser(description='Inventario estático de Element Pack y Prime Slider leído de sus ZIP, sin extraer ni ejecutar nada.')
    parser.add_argument('archives', nargs='+', metavar='ZIP[@VERSIÓN]', help='ZIP del plugin; @VERSIÓN reemplaza la versión de la cabecera')
    parser.add_argument('--out', type=Path, help='carpeta donde escribir <familia>-inventory.json y .md (no puede ser docs/)')
    parser.add_argument('--baseline', type=Path, default=DOCS_INVENTORY, help='inventario anterior de Element Pack (por defecto docs/element-pack-inventory.json)')
    parser.add_argument('--baseline-version', help=f'versión de la base si el JSON no la guarda (por defecto {DEFAULT_BASELINE_VERSION})')
    parser.add_argument('--no-baseline', action='store_true', help='no comparar con el inventario anterior')
    parser.add_argument('--update-docs', action='store_true', help='anotar docs/element-pack-inventory.json y regenerar su .md sin cambiar sus IDs')
    args = parser.parse_args(argv)

    own_ids = own_widget_ids()
    parsed = defaultdict(list)
    for argument in args.archives:
        path, version = parse_archive_argument(argument)
        try:
            with ReferenceArchive(path) as archive:
                meta = identify(archive, version)
                item = inventory_source(archive, meta, own_ids)
        except UnsafeArchive as error:
            raise SystemExit(f'ZIP rechazado: {error}') from None
        parsed[meta['family']].append(item)
        meta = item['meta']
        print(f"{meta['plugin_name']} {meta['version']} ({meta['archive']}, sha256 {meta['sha256'][:12]}…): {meta['widget_ids']} IDs en {meta['widget_files']} archivos de widget, {meta['extension_count']} extensiones, {len(item['unclassified'])} archivos sin get_name().")

    treated = None
    if DOCS_MIGRATION_MAP.exists():
        treated = set(json.loads(DOCS_MIGRATION_MAP.read_text(encoding='utf-8')).get('extensions', {}))
    families = {}
    for family, items in parsed.items():
        baseline = None
        if family == 'element-pack' and not args.no_baseline and args.baseline.exists():
            baseline = load_baseline(args.baseline.resolve(), args.baseline_version)
        families[family] = merge_family(family, items, baseline, treated if family == 'element-pack' else None)
        payload = families[family]
        summary = f"{payload['label']}: {payload['widget_count']} IDs en total, {payload['latest_widget_count']} en {payload['latest_version']}"
        if payload['baseline']:
            summary += f"; base {payload['baseline']['version']} con {payload['baseline']['widget_count']} IDs. {moved_paragraph(payload)} Nuevos: {len(payload['new_since_baseline'])}."
        untreated = [extension['slug'] for extension in payload['extensions'] if extension.get('treated_in_migration_map') is False and payload['latest_version'] in extension['present_in']]
        if untreated:
            summary += f"{'' if summary.endswith('.') else '.'} Extensiones sin tratar en migration-map.json: {len(untreated)}."
        print(summary)

    if args.out:
        out = args.out.resolve()
        if out == DOCS.resolve():
            raise SystemExit('--out no puede ser docs/: usa --update-docs, que conserva el formato publicado')
        out.mkdir(parents=True, exist_ok=True)
        for family, payload in families.items():
            (out / f'{family}-inventory.json').write_text(json.dumps(payload, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
            (out / f'{family}-inventory.md').write_text(render_family_markdown(payload), encoding='utf-8')
            print(f'Escrito {out / (family + "-inventory.json")} y .md')

    if args.update_docs:
        payload = families.get('element-pack')
        if not payload or not payload['baseline'] or args.baseline.resolve() != DOCS_INVENTORY.resolve():
            raise SystemExit('--update-docs necesita un ZIP de Element Pack y la base docs/element-pack-inventory.json')
        if payload['new_since_baseline']:
            print(f"Aviso: {len(payload['new_since_baseline'])} IDs nuevos no se añaden a docs/: primero necesitan su decisión en migration-map.json.")
        annotated = annotate_docs(payload)
        print(f"Anotado {DOCS_INVENTORY.relative_to(ROOT)}: {len(annotated['widgets'])} IDs, {annotated['moved_to_addon_count']} moved_to_addon, {len(annotated['extensions'])} extensiones.")


if __name__ == '__main__':
    main()
