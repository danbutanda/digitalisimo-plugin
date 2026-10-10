#!/usr/bin/env python3
"""Imprime los valores por defecto de los controles de contenido de un widget de Element Pack.

Elementor no guarda los valores que coinciden con el default, así que el traductor necesita los
de la referencia. Salida: fragmento PHP con 'defaults' y, por repetidor, 'defaults' y
'default_rows', para pegar y revisar en maps/bdt-*.php.

Uso: python3 scripts/legacy-defaults.py bdt-accordion
"""
import importlib.util
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
from legacy_controls import detail, ROOT  # noqa: E402

spec = importlib.util.spec_from_file_location('legacy_styles', HERE / 'legacy-styles.py')
styles = importlib.util.module_from_spec(spec)
spec.loader.exec_module(styles)


def value(raw):
    if raw is None:
        return None
    try:
        return styles.literal(raw)
    except Exception:  # noqa: BLE001 - valores calculados en PHP se revisan a mano
        return '«' + ' '.join(raw.split())[:80] + '»'


def main(legacy_id):
    inventory = {w['legacy_widget_id']: w for w in json.loads((ROOT / 'docs/element-pack-inventory.json').read_text())['widgets']}
    controls = detail(ROOT / inventory[legacy_id]['source_file'])
    top, repeaters, fields = {}, {}, {}
    for c in controls:
        if c['kind'] == 'group':
            # Image_Size guarda el tamaño elegido en {name}_size.
            if c['group'] == 'Group_Control_Image_Size' and c.get('raw_default') and c['var'] == '$this':
                top[c['name'] + '_size'] = value(c['raw_default'])
            continue
        if c['type'].split('::')[-1].strip() in ('HEADING', 'RAW_HTML', 'DIVIDER', 'NOTICE', 'ALERT', 'DEPRECATED_NOTICE', 'BUTTON', 'POPOVER_TOGGLE'):
            continue
        if c['var'] != '$this':
            fields.setdefault(c['var'], {})
            if c.get('raw_default') is not None:
                fields[c['var']][c['name']] = value(c['raw_default'])
            continue
        if c['type'].endswith('REPEATER') and c.get('raw_fields'):
            var = c['raw_fields'].split('->')[0].strip()
            repeaters[c['name']] = (var, value(c.get('raw_default')))
            continue
        if c.get('raw_default') is not None:
            top[c['name']] = value(c['raw_default'])
        for device in ('desktop', 'tablet', 'mobile'):
            if c.get('raw_%s_default' % device) is not None:
                top[c['name'] + ('' if device == 'desktop' else '_' + device)] = value(c['raw_%s_default' % device])
    print("\t'defaults'  => " + styles.php(top, 1) + ',')
    if repeaters:
        print("\t'repeaters' => array(")
        for name, (var, rows) in repeaters.items():
            print("\t\t'%s' => array(" % name)
            print("\t\t\t'defaults'     => " + styles.php(fields.get(var, {}), 3) + ',')
            print("\t\t\t'default_rows' => " + styles.php(rows if isinstance(rows, list) else [], 3) + ',')
            print('\t\t),')
        print('\t),')


if __name__ == '__main__':
    main(sys.argv[1])
