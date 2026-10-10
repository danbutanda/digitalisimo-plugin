#!/usr/bin/env python3
"""Genera maps/bdt-*.php de rejillas, listas y carruseles de entradas de Element Pack hacia `posts`.

Cada entrada nombra los ajustes del widget que alimentan la piel «classic» propia
(`Translator::posts_classic()`): ['clave', default] o un valor fijo. La consulta de Element Pack
se traduce con `Translator::posts_query()`. Las clases de sus selectores se reescriben por sufijo
(-item, -title, -excerpt…) hacia el marcado de `posts`.

Uso: python3 scripts/legacy-posts-maps.py [bdt-id …]   (sin argumentos, todos)
"""
import json
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
from legacy_controls import detail, ROOT  # noqa: E402

UI = ('HEADING', 'RAW_HTML', 'DIVIDER', 'NOTICE', 'ALERT', 'DEPRECATED_NOTICE', 'BUTTON', 'POPOVER_TOGGLE')

SPEC = {
    'bdt-post-block': ('post', 5, {'per_page': ['posts_per_page', 5], 'columns': '1', 'title': ['featured_show_title', 'yes'], 'title_tag': 'h3', 'date': ['featured_show_date', 'yes'], 'category': ['featured_show_category', 'yes'], 'tags': ['featured_show_tag', 'yes'], 'excerpt': ['featured_show_excerpt', 'yes'], 'excerpt_length': ['featured_excerpt_length', 15], 'read_more': ['featured_show_read_more', 'yes'], 'read_more_text': ['read_more_text', 'Read More']}),
    'bdt-post-block-modern': ('post', 4, {'per_page': ['posts_per_page', 4], 'columns': '2', 'title': 'yes', 'title_tag': ['title_tags', 'h4'], 'date': ['show_meta', 'yes'], 'category': ['show_meta', 'yes'], 'excerpt': ['show_excerpt', 'yes'], 'excerpt_length': ['excerpt_length', 15], 'read_more': ['show_read_more', 'yes'], 'read_more_text': ['read_more_text', 'Read More']}),
    'bdt-post-card': ('post', 3, {'per_page': ['posts_per_page', 3], 'columns': '3', 'title': 'yes', 'title_tag': ['title_tags', 'h4'], 'date': 'yes', 'category': 'yes', 'tags': 'yes', 'excerpt': ['excerpt', 'yes'], 'excerpt_length': ['excerpt_length', 15], 'read_more': 'yes', 'read_more_text': 'Read More'}),
    'bdt-post-gallery': ('post', 6, {'per_page': ['posts_per_page', 6], 'columns': ['columns', '3'], 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h4'], 'category': ['show_category', ''], 'excerpt': ['show_excerpt', ''], 'excerpt_length': ['excerpt_limit', 10], 'pagination': ['show_pagination', '']}),
    'bdt-post-grid-tab': ('post', 8, {'per_page': ['posts_per_page', 8], 'columns': ['columns', '4'], 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h3'], 'author': ['show_author', 'yes'], 'date': ['show_date', 'yes'], 'comments': ['show_comments', 'yes'], 'category': ['show_category', 'yes'], 'excerpt': ['show_excerpt', 'yes'], 'excerpt_length': ['excerpt_length', 45], 'read_more': ['show_readmore', 'yes'], 'read_more_text': ['readmore_text', 'Read More']}),
    'bdt-post-slider': ('post', 4, {'per_page': ['posts_per_page', 4], 'columns': '1', 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h1'], 'date': ['show_meta', 'yes'], 'tags': ['show_tag', 'yes'], 'excerpt': ['show_text', 'yes'], 'excerpt_length': ['excerpt_length', 35], 'read_more': ['show_button', ''], 'read_more_text': 'Read More'}),
    'bdt-carousel': ('post', 6, {'per_page': ['posts_per_page', 6], 'columns': ['columns', '3'], 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h4'], 'date': 'yes', 'comments': 'yes', 'excerpt': ['show_excerpt', 'yes'], 'excerpt_length': ['excerpt_length', 15], 'read_more': ['show_read_more', 'yes'], 'read_more_text': ['read_more_text', 'Read More']}),
    'bdt-portfolio-gallery': ('portfolio', 9, {'per_page': ['posts_per_page', 9], 'columns': ['columns', '3'], 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h4'], 'category': ['show_category', ''], 'excerpt': ['show_excerpt', ''], 'excerpt_length': ['excerpt_limit', 10], 'pagination': ['show_pagination', '']}),
    'bdt-portfolio-carousel': ('portfolio', 9, {'per_page': ['posts_per_page', 9], 'columns': ['columns', '3'], 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h4'], 'category': ['show_category', ''], 'excerpt': ['show_excerpt', ''], 'excerpt_length': ['excerpt_limit', 10]}),
    'bdt-portfolio-list': ('portfolio', 9, {'per_page': ['posts_per_page', 9], 'columns': ['columns', '2'], 'title': ['show_title', 'yes'], 'title_tag': ['title_tag', 'h4'], 'category': ['show_category', ''], 'excerpt': ['show_excerpt', ''], 'excerpt_length': ['excerpt_limit', 10], 'pagination': ['show_pagination', '']}),
}

SUFFIXES = (
    ('item', '.elementor-post'), ('title', '.elementor-post__title'), ('excerpt', '.elementor-post__excerpt'),
    ('text', '.elementor-post__excerpt'), ('desc', '.elementor-post__text'), ('meta', '.elementor-post__meta-data'),
    ('read-more', '.elementor-post__read-more'), ('readmore', '.elementor-post__read-more'), ('button', '.elementor-post__read-more'),
    ('image', '.elementor-post__thumbnail'), ('img', '.elementor-post__thumbnail'), ('thumbnail', '.elementor-post__thumbnail'),
    ('category', '.elementor-post__terms--category a'), ('tag', '.elementor-post__terms--post_tag a'), ('date', '.elementor-post-date'),
    ('author', '.elementor-post-author'), ('pagination', '.elementor-pagination'),
)


def styles_module():
    import importlib.util
    spec = importlib.util.spec_from_file_location('legacy_styles', HERE / 'legacy-styles.py')
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def classes_for(src, wid):
    base = wid.replace('bdt-', '')
    found = sorted(set(re.findall(r"\.(bdt-[a-z0-9-]+)", src)), key=len, reverse=True)
    out = {}
    for cls in found:
        if cls in ('bdt-' + base, 'bdt-' + base.replace('post-', 'post-')):
            out['.' + cls] = '.elementor-posts-container'
            continue
        for suffix, target in SUFFIXES:
            if cls.endswith('-' + suffix):
                out['.' + cls] = target
                break
    out.setdefault('.bdt-' + base, '.elementor-posts-container')
    return out


def generate(wid):
    source_name, per_page, spec = SPEC[wid]
    skeleton = subprocess.run(['python3', str(HERE / 'legacy-map-skeleton.py'), wid, '--target=posts'], capture_output=True, text=True, check=True).stdout
    defaults = re.search(r"\t'defaults'  => (array\(.*?\n\t\)|array\(\)),\n", skeleton, re.S)
    defaults_php = defaults.group(1) if defaults else 'array()'
    query = "\t\t'posts_source' => '%s',\n\t\t'posts_per_page' => %d,\n\t\t'posts_orderby' => 'date',\n\t\t'posts_order' => 'desc',\n" % (source_name, per_page)
    defaults_php = defaults_php.replace('array(\n', 'array(\n' + query, 1) if defaults_php != 'array()' else 'array(\n' + query + '\t)'
    inventory = json.loads((ROOT / 'docs/element-pack-inventory.json').read_text())
    source = ROOT / next(w['source_file'] for w in inventory['widgets'] if w['legacy_widget_id'] == wid)
    content = []
    for c in detail(source):
        ctype = c.get('type', '').split('::')[-1].strip()
        if c['var'] == '$this' and c['kind'] != 'group' and not c.get('raw_selectors') and ctype not in UI and c['tab'] == 'content' and c['name'] not in content and not c['name'].startswith('posts_'):
            content.append(c['name'])
    styles = styles_module()
    classes = classes_for(source.read_text(errors='ignore'), wid)
    text = '\n'.join([
        '<?php',
        '/** Element Pack Pro 9.9.1 `%s` → `posts` (piel «classic» propia de los adaptadores). */' % wid,
        "defined( 'ABSPATH' ) || exit;",
        '',
        "$placeholder_url = class_exists( '\\Elementor\\Utils' ) ? \\Elementor\\Utils::get_placeholder_image_src() : '';",
        '',
        'return array(',
        "\t'target'    => 'posts',",
        "\t'classes'   => %s," % styles.php(classes, 1),
        "\t'defaults'  => %s," % defaults_php,
        "\t'filter'    => static function ( array $out ) {",
        '\t\treturn Digitalisimo\\Elements\\Legacy\\Translator::posts_classic( $out, %s, %s );' % (styles.php(spec, 2), styles.php(content, 2)),
        '\t},',
        ');',
        '',
    ])
    (ROOT / 'digitalisimo-elements/modules/digitalisimo-legacy/maps' / (wid + '.php')).write_text(text, encoding='utf-8')
    return wid


if __name__ == '__main__':
    for wid in sys.argv[1:] or sorted(SPEC):
        print(generate(wid))
