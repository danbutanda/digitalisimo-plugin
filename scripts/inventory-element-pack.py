#!/usr/bin/env python3
"""Static inventory of the local Element Pack reference; never imports its PHP."""

import json
import re
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REFERENCE = ROOT / 'digitalisimo-elements/bdthemes-element-pack'
MODULES = REFERENCE / 'modules'
OUTPUT = ROOT / 'docs'

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
}

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


def main():
    if not MODULES.is_dir():
        raise SystemExit(f'Missing reference: {MODULES}')

    own_widget_ids = {}
    for own_file in sorted((ROOT / 'digitalisimo-elements/modules').rglob('widgets/*.php')):
        own_source = own_file.read_text(encoding='utf-8', errors='replace')
        own_match = re.search(r"function\s+get_name\s*\([^)]*\)\s*(?::\s*[^\{]+)?\{\s*return\s*['\"]([^'\"]+)", own_source)
        if own_match:
            own_widget_ids[own_match.group(1)] = str(own_file.relative_to(ROOT))
    widgets = []
    unclassified = []
    for source_file in sorted(MODULES.glob('*/widgets/*.php')):
        source = source_file.read_text(encoding='utf-8', errors='replace')
        slug = source_file.parent.parent.name
        name_match = re.search(r"function\s+get_name\s*\(\s*\)\s*\{\s*return\s*['\"]([^'\"]+)", source)
        if not name_match:
            unclassified.append(str(source_file.relative_to(ROOT)))
            continue
        widget_id = name_match.group(1)
        class_match = re.search(r'\bclass\s+(\w+)\s+extends\s+([\\\w]+)', source)
        namespace_match = re.search(r'\bnamespace\s+([^;]+);', source)
        title_match = re.search(r"function\s+get_title\s*\([^)]*\).*?__\(\s*['\"]([^'\"]+)", source, re.S)
        category = quoted(method_body(source, 'get_categories'))
        styles = quoted(method_body(source, 'get_style_depends')) + quoted(method_body(source, '_get_style_depends'))
        scripts = quoted(method_body(source, 'get_script_depends'))
        styles = sorted(set(x for x in styles if x not in ('ep_is_edit_mode',)))
        scripts = sorted(set(x for x in scripts if x not in ('ep_is_edit_mode',)))
        module_dir = source_file.parent.parent
        module_source = (module_dir / 'module.php').read_text(encoding='utf-8', errors='replace') if (module_dir / 'module.php').exists() else ''
        asset_stem = f'ep-{slug}'
        css = sorted(str(p.relative_to(REFERENCE)) for p in (REFERENCE / 'assets/css').glob(asset_stem + '*.css'))
        js = sorted(str(p.relative_to(REFERENCE)) for p in (REFERENCE / 'assets/js/modules').glob(asset_stem + '*.js'))
        skins = sorted(str(p.relative_to(REFERENCE)) for p in (module_dir / 'skins').glob('*.php'))
        control_imports = sorted(set(re.findall(r'ElementPack\\(?:Includes\\Controls|Traits)\\[\\\w]+', source)))
        direct_uikit = bool(re.search(r'\b(?:bdt-uikit|UIkit|uikit)\b', source, re.I))
        js_uses_uikit = any(re.search(r'\b(?:UIkit|uikit)\b', (REFERENCE / path).read_text(encoding='utf-8', errors='replace'), re.I) for path in js)
        direct_swiper = bool(re.search(r'\bswiper\b|Global_Swiper_Controls', source, re.I))
        dep = plugin_dependency(slug)
        engine = engine_for(slug)
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
        overlap = own_widget_ids.get(widget_id.removeprefix('bdt-'))
        if overlap:
            classification = 'E - REVIEW FOR DUPLICATION'
        complexity = 'high' if dep or security or skins or len(control_imports) > 2 else 'medium' if engine != 'content-and-layout' or js else 'low'
        priority = 1 if classification.startswith('A') and complexity == 'low' else 2 if classification.startswith('B') else 3
        original_dependencies = sorted(set(styles + scripts + (['ElementPack Base'] if class_match and class_match.group(2) == 'Module_Base' else [])))
        widgets.append({
            'name': title_match.group(1) if title_match else slug.replace('-', ' ').title(),
            'legacy_widget_id': widget_id,
            'directory': str(module_dir.relative_to(ROOT)),
            'source_file': str(source_file.relative_to(ROOT)),
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
            'classification': classification,
            'proposed_engine': engine,
            'existing_elements_widget_candidate': overlap,
            'implementation_status': PILOT_MIGRATIONS.get(widget_id, {}).get('status', 'pending'),
            'dependency_decisions': {'ElementPack Base': 'REIMPLEMENT', 'UIkit': 'REMOVE / REIMPLEMENT', 'Swiper': 'KEEP ONLY IF NEEDED', 'Element Pack admin and licensing': 'REMOVE'},
            'review_note': 'Static inference only; verify runtime dependencies, controls, output, license, and visual behavior before migration.'
        })

    ids = [widget['legacy_widget_id'] for widget in widgets]
    if len(ids) != len(set(ids)):
        raise SystemExit('Duplicate legacy Elementor widget IDs found')
    groups = defaultdict(list)
    for widget in widgets:
        groups[widget['proposed_engine']].append(widget['legacy_widget_id'])
    payload = {
        'source': str(REFERENCE.relative_to(ROOT)),
        'method': 'static source inspection; the reference PHP was not executed',
        'widget_count': len(widgets),
        'unclassified_widget_files': unclassified,
        'engine_groups': dict(sorted(groups.items())),
        'widgets': widgets,
    }
    (OUTPUT / 'element-pack-inventory.json').write_text(json.dumps(payload, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')

    migration_map = {
        'status': 'four widgets implemented; first has conditional legacy read adapter; remaining widgets pending',
        'entries': {
            widget['legacy_widget_id']: {
                'target': PILOT_MIGRATIONS.get(widget['legacy_widget_id'], {}).get('target'),
                'engine': widget['proposed_engine'],
                'classification': widget['classification'],
                'existing_elements_widget_candidate': widget['existing_elements_widget_candidate'],
                'setting_mappings': {name: name for name in PILOT_MIGRATIONS.get(widget['legacy_widget_id'], {}).get('same_name_controls', [])},
                'responsive_conversions': {},
                'skin_conversions': {},
                'status': PILOT_MIGRATIONS.get(widget['legacy_widget_id'], {}).get('status', 'pending'),
            }
            for widget in widgets
        },
    }
    (OUTPUT / 'migration-map.json').write_text(json.dumps(migration_map, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')

    counts = Counter(widget['classification'] for widget in widgets)
    lines = [
        '# Inventario estático de Element Pack', '',
        f'Fuente local: `{REFERENCE.relative_to(ROOT)}`. Se identificaron **{len(widgets)} IDs Elementor únicos** en {len(widgets) + len(unclassified)} archivos `modules/*/widgets/*.php`.',
        'La carpeta es material de referencia, no una dependencia de ejecución ni un asset publicable. Este inventario se generó sin ejecutar PHP ajeno.', '',
        'Las clasificaciones, complejidades y motores son **hipótesis de triaje**, no una aprobación para migrar. `UIkit` aparece como dependencia indirecta del loader original; cada uso real se debe validar antes de reconstruir. La ausencia de un marcador estático no prueba ausencia de AJAX, REST, dependencias o riesgos.', '',
        '## Familias provisionales', '',
        '| Motor | Widgets |', '| --- | ---: |',
    ]
    lines += [f'| {engine} | {len(items)} |' for engine, items in sorted(groups.items())]
    lines += ['', '## Clasificación provisional', '', '| Clase | Widgets |', '| --- | ---: |']
    lines += [f'| {classification} | {count} |' for classification, count in sorted(counts.items())]
    lines += ['', '## Matriz de widgets', '', '| Widget | ID legacy | Motor | Clase | Dependencia externa | CSS/JS |', '| --- | --- | --- | --- | --- | --- |']
    for widget in widgets:
        lines.append(f"| {widget['name'].replace('|', '/')} | `{widget['legacy_widget_id']}` | {widget['proposed_engine']} | {widget['classification']} | {widget['other_plugin_dependency'] or '—'} | {len(widget['css'])}/{len(widget['js'])} |")
    lines += ['', 'El JSON incluye clase original, rutas, controles, skins, handles declarados, señales de seguridad, complejidad y estado individual. Las dependencias transitivas y equivalencias visuales requieren comprobación funcional antes de marcar un widget como completo.', '']
    (OUTPUT / 'element-pack-inventory.md').write_text('\n'.join(lines), encoding='utf-8')

    framework = {
        'base_classes': sorted(str(path.relative_to(REFERENCE)) for path in (REFERENCE / 'base').glob('*.php')),
        'custom_control_files': sorted(str(path.relative_to(REFERENCE)) for path in (REFERENCE / 'includes/controls').rglob('*.php')),
        'skin_files': sorted(str(path.relative_to(REFERENCE)) for path in MODULES.glob('*/skins/*.php')),
        'original_widget_asset_files': sorted(set(path for widget in widgets for path in widget['css'] + widget['js'])),
        'loader_global_assets': ['bdt-uikit.css', 'ep-helper.css', 'bdt-uikit.min.js'],
        'note': 'Paths describe reference code only; no runtime dependency is approved.',
    }
    (OUTPUT / 'element-pack-framework-inventory.json').write_text(json.dumps(framework, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    graph_nodes = {}
    graph_edges = []
    for widget in widgets:
        widget_node = 'widget:' + widget['legacy_widget_id']
        graph_nodes[widget_node] = {'type': 'widget', 'label': widget['name']}
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
            graph_nodes.setdefault(target, {'type': target.split(':', 1)[0], 'label': target.split(':', 1)[1]})
            graph_edges.append({'from': widget_node, 'to': target, 'relation': relation})
    graph_payload = {
        'method': 'static source inspection; declared handles include editor-only branches and need runtime verification',
        'nodes': graph_nodes,
        'edges': graph_edges,
    }
    (OUTPUT / 'element-pack-dependency-graph.json').write_text(json.dumps(graph_payload, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    graph_lines = [
        '# Mapa inicial de dependencias', '',
        'Derivado de análisis estático; los handles de Elementor y las dependencias transitivas deben comprobarse en runtime. Ninguna ruta del plugin de referencia debe ejecutarse desde Elements.', '',
        f"- Clases base de referencia: {len(framework['base_classes'])}",
        f"- Archivos de controles personalizados: {len(framework['custom_control_files'])}",
        f"- Archivos de skins: {len(framework['skin_files'])}",
        f"- Archivos CSS/JS de módulos asociados: {len(framework['original_widget_asset_files'])}", '',
        '| Familia | Widgets | Dependencias o motores candidatos |', '| --- | ---: | --- |',
    ]
    for engine, items in sorted(groups.items()):
        candidate = {
            'carousel': 'motor compartido de carrusel; Swiper sólo si la funcionalidad lo exige',
            'commerce': 'consulta/componente WooCommerce por sitio',
            'content-and-layout': 'HTML semántico + CSS aislado del widget',
            'data-visualization': 'datos + presentación accesible; librería sólo si es imprescindible',
            'forms': 'motor de formularios existente + validación y protección del endpoint',
            'interaction': 'APIs del navegador y JS nativo aislado',
            'media': 'medios de WordPress + tamaños y carga diferida seguros',
            'navigation': 'estado accesible + controles nativos de Elementor',
            'query': 'motor de consultas existente + caché y contexto del sitio',
        }[engine]
        graph_lines.append(f'| {engine} | {len(items)} | {candidate} |')
    graph_lines += ['', '## Primeras decisiones de dependencia', '',
                    '- `ElementPack\\Base\\Module_Base` y clases relacionadas: **REIMPLEMENT**, sin herencia del paquete original.',
                    '- UIkit global: **REMOVE / REIMPLEMENT** para cada función necesaria.',
                    '- Swiper: **KEEP** sólo en widgets que lo requieran y mediante un único motor compartido.',
                    '- Administración, sistema de activación y licencia originales: **REMOVE**.',
                    '- Integraciones de terceros: registrar únicamente cuando esté presente el plugin correspondiente.', '',
                    '## Control de duplicados', '',
                    f"El escaneo encontró {sum(bool(widget['existing_elements_widget_candidate']) for widget in widgets)} coincidencias exactas de nombre (sin prefijo `bdt-`) con widgets ya existentes en Elements. Es una señal para revisar, no prueba equivalencia funcional. Los candidatos figuran en el JSON y no deben registrarse dos veces sin decidir compatibilidad de datos.", '',
                    '## Ejemplo: Testimonial Slider', '',
                    '`bdt-testimonial-slider` → clases base, controles de consulta, skins y Swiper originales. Propuesta: compartir motor de carrusel y consulta de Elements; adaptar controles y skins; validar HTML, accesibilidad y assets en editor/frontend. Todavía no hay implementación ni medición comparativa.', '']
    (OUTPUT / 'element-pack-dependency-graph.md').write_text('\n'.join(graph_lines), encoding='utf-8')
    print(f'{len(widgets)} widgets; {len(unclassified)} widget files without get_name(); {len(groups)} provisional engines')


if __name__ == '__main__':
    main()
