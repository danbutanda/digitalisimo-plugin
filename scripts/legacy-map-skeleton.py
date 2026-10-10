#!/usr/bin/env python3
"""Genera el esqueleto de maps/bdt-*.php para un widget de Element Pack.

Incluye todos los valores por defecto de la referencia (también de repetidores y filas por
defecto), resuelve las imágenes de ejemplo con la de Elementor y lista como comentario las
claves sin control equivalente en el widget propio y las clases CSS a mapear. El resultado se
revisa a mano: renombres, valores, descartes y filtros dependen del render de ambos widgets.

Uso: python3 scripts/legacy-map-skeleton.py bdt-fancy-card [--write]
"""
import importlib.util
import json
import re
import sys
from collections import Counter
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
from legacy_controls import detail, ROOT  # noqa: E402

spec = importlib.util.spec_from_file_location('legacy_styles', HERE / 'legacy-styles.py')
styles = importlib.util.module_from_spec(spec)
spec.loader.exec_module(styles)

UI = ('HEADING', 'RAW_HTML', 'DIVIDER', 'NOTICE', 'ALERT', 'DEPRECATED_NOTICE', 'BUTTON', 'POPOVER_TOGGLE')
PLACEHOLDER = '__PLACEHOLDER__'


def value(raw):
    if raw is None:
        return None, False
    raw = re.sub(r'(?:\\?Elementor\\)?Utils::get_placeholder_image_src\(\)', "'%s'" % PLACEHOLDER, raw)
    raw = re.sub(r"BDTEP_ASSETS_URL\s*\.\s*'[^']*'(?:\s*\.\s*[^,\]\)]+?\s*\.\s*'[^']*')?", "'%s'" % PLACEHOLDER, raw)
    try:
        return styles.literal(raw), True
    except Exception:  # noqa: BLE001
        return ' '.join(raw.split())[:90], False


def php(v, indent):
    return styles.php(v, indent).replace("'%s'" % PLACEHOLDER, '$placeholder_url')


def main(legacy_id, write):
    inventory = {w['legacy_widget_id']: w for w in json.loads((ROOT / 'docs/element-pack-inventory.json').read_text())['widgets']}
    mm = json.loads((ROOT / 'docs/migration-map.json').read_text())['entries']
    target = TARGET or mm[legacy_id]['target']
    reg = (ROOT / 'digitalisimo-elements/modules/digitalisimo-widgets/class-registry.php').read_text()
    files = dict(re.findall(r"'(digitalisimo-[a-z-]+)' => array\(\s*'file'\s*=> '([^']+)'", reg))
    candidate = mm[legacy_id].get('existing_elements_widget_candidate') or ''
    if target in files:
        own_file = ROOT / 'digitalisimo-elements/modules/digitalisimo-widgets' / files[target]
    elif candidate.endswith('.php'):
        own_file = ROOT / candidate
    else:
        own_file = None
    own = {c['name'] for c in detail(own_file)} if own_file and own_file.exists() else set()
    controls = detail(ROOT / inventory[legacy_id]['source_file'])
    top, unparsed, missing, repeaters, fields = {}, [], [], {}, {}
    for c in controls:
        ctype = c.get('type', '').split('::')[-1].strip()
        if c['kind'] == 'group':
            if c['group'] == 'Group_Control_Image_Size' and c.get('raw_default') and c['var'] == '$this':
                v, ok = value(c['raw_default'])
                top[c['name'] + '_size'] = v
            continue
        if ctype in UI:
            continue
        if c['var'] != '$this':
            key = (c['var'], c.get('gen', 0))
            fields.setdefault(key, {})
            if c.get('raw_default') is not None:
                v, ok = value(c['raw_default'])
                if ok:
                    fields[key][c['name']] = v
                else:
                    unparsed.append('%s (repetidor): %s' % (c['name'], v))
            continue
        if ctype.endswith('REPEATER'):
            rows, ok = value(c.get('raw_default'))
            repeaters[c['name']] = (c.get('fields_gen'), rows if ok and isinstance(rows, list) else [])
            if c.get('raw_default') and not ok:
                unparsed.append('%s (filas): %s' % (c['name'], rows))
            continue
        for device, key in (('', 'raw_default'), ('_tablet', 'raw_tablet_default'), ('_mobile', 'raw_mobile_default')):
            if c.get(key) is not None:
                v, ok = value(c[key])
                if ok:
                    top[c['name'] + device] = v
                else:
                    unparsed.append('%s%s: %s' % (c['name'], device, v))
        if c['tab'] == 'content' and not c.get('raw_selectors') and c['name'] not in own:
            missing.append(c['name'])
    classes = Counter()
    for c in controls:
        for raw in (c.get('raw_selectors'), c.get('selector')):
            for sel in re.findall(r"\.bdt-[a-z0-9_-]+", raw or ''):
                classes[sel] += 1
    out = ["<?php", "/** Element Pack Pro 9.9.1 `%s` → `%s`. */" % (legacy_id, target), "defined( 'ABSPATH' ) || exit;", '',
           "$placeholder_url = class_exists( '\\Elementor\\Utils' ) ? \\Elementor\\Utils::get_placeholder_image_src() : '';", '',
           'return array(', "\t'target'    => '%s'," % target, "\t// Clases de Element Pack más usadas en sus selectores: " + ', '.join(k for k, _ in classes.most_common(14)),
           "\t'classes'   => array(),", "\t'defaults'  => " + php(top, 1) + ',']
    if repeaters:
        out.append("\t'repeaters' => array(")
        for name, (gen, rows) in repeaters.items():
            out.append("\t\t'%s' => array(" % name)
            out.append("\t\t\t'defaults'     => " + php(fields.get(tuple(gen) if gen else None, {}), 3) + ',')
            out.append("\t\t\t'default_rows' => " + php(rows, 3) + ',')
            out.append('\t\t),')
        out.append('\t),')
    if missing:
        out.append("\t// Sin control propio con el mismo nombre (renombrar, convertir o descartar): " + ', '.join(missing))
    for item in unparsed:
        out.append('\t// Default no literal, revisar: ' + item)
    out.append(');')
    text = '\n'.join(out) + '\n'
    if write:
        (ROOT / 'digitalisimo-elements/modules/digitalisimo-legacy/maps' / (legacy_id + '.php')).write_text(text, encoding='utf-8')
    print(text)


TARGET = None

if __name__ == '__main__':
    for arg in sys.argv:
        if arg.startswith('--target='):
            TARGET = arg.split('=', 1)[1]
    main(sys.argv[1], '--write' in sys.argv)
