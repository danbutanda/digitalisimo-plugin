#!/usr/bin/env python3
"""Genera los valores por defecto estáticos de los 57 widgets de Prime Slider (F8, contrato C9).

Lee Prime Slider Lite 4.7.0 y Pro 4.7.0 desde sus ZIP de reference/ (o DIGITALISIMO_REFERENCE_DIR), sin extraerlos ni
ejecutarlos (`legacy_controls`), y escribe `digitalisimo-migration/legacy/prime-slider/defaults/<id>.php`:

    array(
        'id' => …, 'plugin' => 'lite'|'pro', 'version' => '4.7.0', 'skins' => array( … ),
        'controls'  => array( '<clave>' => <valor por defecto>, … ),  // también rasgos, grupos y update_control
        'repeaters' => array( '<clave>' => array( 'fields' => array( … ), 'rows' => array( … ) ) ),
        'styles'    => array( '<clave>' => array( 'type' => …, 'selectors' => array( … ) | 'selector' => … ) ),
    )

Elementor no guarda lo que coincide con el valor por defecto, tampoco dentro de los repetidores, así que la conversión
rellena con esto lo que el documento no guardó cuando Prime Slider no está activo (con él activo, Migración lee los del
widget registrado). Los valores van tal cual, también los del proveedor: los limpia la conversión. Marcadores:
- `{{ps_assets}}`: la URL de la carpeta `assets/` de Prime Slider (BDTPS_CORE_ASSETS_URL); la conversión la sustituye
  por la del plugin instalado o la vacía.
- `{"__t": "Read More", "d": "<dominio>"}`: texto traducible de Prime Slider (__(), esc_html__()…).
- `$placeholder`: la imagen de relleno de Elementor (Utils::get_placeholder_image_src()), como en los mapas de EP.
Lo que no es literal (opciones calculadas, llamadas) no se escribe y se anota al final del archivo.

Uso: python3 -I scripts/prime-slider-defaults.py [prime-slider-id …]   (sin argumentos, los 57)
"""
import importlib.util
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.dont_write_bytecode = True
sys.path.insert(0, str(HERE))
import legacy_controls as lc  # noqa: E402

spec = importlib.util.spec_from_file_location('legacy_styles', HERE / 'legacy-styles.py')
styles = importlib.util.module_from_spec(spec)
spec.loader.exec_module(styles)

OUT = ROOT / 'digitalisimo-migration' / 'legacy' / 'prime-slider' / 'defaults'
INVENTORY = ROOT / 'docs' / 'prime-slider-inventory.json'
UI = ('HEADING', 'RAW_HTML', 'DIVIDER', 'NOTICE', 'ALERT', 'DEPRECATED_NOTICE', 'BUTTON', 'POPOVER_TOGGLE')
PLACEHOLDER = '\x00placeholder'
DEVICES = (('raw_default', ''), ('raw_desktop_default', ''), ('raw_tablet_default', '_tablet'), ('raw_mobile_default', '_mobile'))


def literal(raw, notes, what):
    if raw is None:
        return None, False
    raw = re.sub(r'(?:\\?Elementor\\)?Utils::get_placeholder_image_src\(\)', "'%s'" % PLACEHOLDER, raw)
    styles.TRANSLATABLE = True
    styles.PhpLiteral.CONSTANTS = {'BDTPS_CORE_ASSETS_URL': '{{ps_assets}}'}
    try:
        return styles.literal(raw), True
    except Exception as error:  # noqa: BLE001 - lo calculado en PHP no se inventa
        notes.append('%s: %s' % (what, ' '.join(raw.split())[:90]))
        return None, False
    finally:
        styles.TRANSLATABLE = False
        styles.PhpLiteral.CONSTANTS = {}


def php(value, indent=1):
    """Como legacy-styles.php(), con el marcador de la imagen de relleno como variable."""
    pad = '\t' * indent
    if isinstance(value, dict):
        if not value:
            return 'array()'
        inner = ',\n'.join('%s\t%s => %s' % (pad, php(k), php(v, indent + 1)) for k, v in value.items())
        return 'array(\n%s,\n%s)' % (inner, pad)
    if isinstance(value, list):
        if not value:
            return 'array()'
        return 'array( %s )' % ', '.join(php(v, indent + 1) for v in value)
    if isinstance(value, str) and PLACEHOLDER in value:
        if value != PLACEHOLDER:
            return "$placeholder . " + styles.php(value.replace(PLACEHOLDER, ''), indent)
        return '$placeholder'
    return styles.php(value, indent)


def group_defaults(control, notes):
    """Valores por defecto que un grupo guarda en `{name}_{campo}`: el tamaño de imagen y `fields_options`."""
    out = {}
    name = control['name']
    if control['group'] == 'Group_Control_Image_Size' and control.get('raw_default'):
        value, ok = literal(control['raw_default'], notes, name + '_size')
        if ok:
            out[name + '_size'] = value
    if control.get('raw_fields_options'):
        # Sólo los `default` de cada campo: etiquetas y opciones pueden no ser literales y no hacen falta.
        text = control['raw_fields_options']
        inner = text[6:-1] if text.startswith('array(') else text[1:-1]
        for field in top_keys(inner):
            body = lc.top_value('[' + inner + ']', field)
            if not body:
                continue
            body = body[5:] if body.startswith('array') else body
            for key, suffix in (('default', ''), ('desktop_default', ''), ('tablet_default', '_tablet'), ('mobile_default', '_mobile')):
                raw = lc.top_value(body, key)
                if raw is not None:
                    value, ok = literal(raw, notes, '%s_%s%s' % (name, field, suffix))
                    if ok:
                        out['%s_%s%s' % (name, field, suffix)] = value
    return out


def top_keys(inner):
    """Claves `'x' =>` del primer nivel de un array PHP (sin su envoltorio)."""
    keys, depth, quote, i = [], 0, None, 0
    while i < len(inner):
        c = inner[i]
        if quote:
            if c == '\\':
                i += 2
                continue
            if c == quote:
                quote = None
        elif c in '\'"':
            if depth == 0:
                m = re.match(r"'([A-Za-z0-9_]+)'\s*=>", inner[i:])
                if m:
                    keys.append(m.group(1))
            quote = c
        elif c in '[(':
            depth += 1
        elif c in '])':
            depth -= 1
        i += 1
    return keys


def updates(source_text, notes):
    """`update_control( 'clave', array( 'default' => … ) )` del widget: cambian el valor por defecto del rasgo."""
    out = {}
    text = lc.strip_comments(source_text)
    for m in re.finditer(r"->update_(?:responsive_)?control\(\s*'([^']+)'\s*,\s*", text):
        args = lc._args_after(text, m.end())
        inner = args[5:] if args.startswith('array') else args
        for key, suffix in (('default', ''), ('desktop_default', ''), ('tablet_default', '_tablet'), ('mobile_default', '_mobile')):
            raw = lc.top_value(inner, key)
            if raw is not None:
                value, ok = literal(raw, notes, 'update_control ' + m.group(1) + suffix)
                if ok:
                    out[m.group(1) + suffix] = value
    return out


def extract(widget):
    key = 'ps-lite' if widget['plugin'] == 'lite' else 'ps-pro'
    plugin = lc.plugin(key)
    rel = widget['source_file'].split('/', 1)[1]
    source = plugin.file(rel)
    notes = []
    controls, repeaters, fields, style = {}, {}, {}, {}
    for c in lc.detail(source):
        ctype = (c.get('type') or '').split('::')[-1].strip()
        if c['kind'] == 'group':
            if c['var'] == '$this':
                controls.update(group_defaults(c, notes))
                if c.get('selector'):
                    sel, ok = literal(c['selector'], notes, c['name'] + ' selector')
                    if ok:
                        style[c['name']] = {'type': styles.GROUPS.get(c['group'], c['group']), 'group': True, 'selector': sel}
            continue
        if ctype in UI:
            continue
        if c['var'] != '$this':
            bucket = fields.setdefault((c['var'], c.get('gen', 0)), {})
            for raw_key, suffix in DEVICES:
                if c.get(raw_key) is not None:
                    value, ok = literal(c[raw_key], notes, '%s (repetidor)%s' % (c['name'], suffix))
                    if ok:
                        bucket[c['name'] + suffix] = value
            continue
        if ctype.endswith('REPEATER'):
            rows, ok = literal(c.get('raw_default'), notes, c['name'] + ' (filas)') if c.get('raw_default') else ([], True)
            repeaters[c['name']] = (tuple(c['fields_gen']) if c.get('fields_gen') else None, rows if ok and isinstance(rows, list) else [])
            continue
        for raw_key, suffix in DEVICES:
            if c.get(raw_key) is not None:
                value, ok = literal(c[raw_key], notes, c['name'] + suffix)
                if ok:
                    controls[c['name'] + suffix] = value
        if c.get('raw_selectors'):
            sels, ok = literal(c['raw_selectors'], notes, c['name'] + ' selectors')
            if ok and isinstance(sels, dict):
                style[c['name']] = {'type': ctype.lower(), 'responsive': c['kind'] == 'responsive', 'selectors': sels}
    controls.update(updates(source.read_text(), notes))
    out_repeaters = {name: {'fields': fields.get(gen, {}) if gen else {}, 'rows': rows} for name, (gen, rows) in repeaters.items()}
    return {
        'id': widget['legacy_widget_id'], 'plugin': widget['plugin'], 'version': widget['version'], 'skins': widget['skins'],
        'controls': controls, 'repeaters': out_repeaters, 'styles': style,
    }, notes, str(source)


def write(widget):
    data, notes, source = extract(widget)
    head = [
        '<?php',
        '/**',
        ' * Valores por defecto de Prime Slider %s %s `%s` (%s), leídos de su ZIP sin ejecutarlo.' % ('Lite' if data['plugin'] == 'lite' else 'Pro', data['version'], data['id'], source),
        ' * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.',
        ' * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.',
        ' * Generado con scripts/prime-slider-defaults.py; no editar a mano.',
        ' */',
        "defined( 'ABSPATH' ) || exit;",
        '',
        "$placeholder = class_exists( '\\Elementor\\Utils' ) ? \\Elementor\\Utils::get_placeholder_image_src() : '';",
        '',
        '',
    ]
    body = 'return ' + php(data, 0) + ';\n'
    tail = ''.join('// Valor no literal, sin escribir: %s\n' % note.replace('*/', '* /') for note in notes)
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / (data['id'] + '.php')).write_text('\n'.join(head) + body + tail, encoding='utf-8')
    rows = sum(len(r['rows']) for r in data['repeaters'].values())
    print('%s: %d controles, %d repetidores (%d filas), %d estilos, %d sin escribir' % (data['id'], len(data['controls']), len(data['repeaters']), rows, len(data['styles']), len(notes)))


if __name__ == '__main__':
    inventory = json.loads(INVENTORY.read_text(encoding='utf-8'))['widgets']
    wanted = set(sys.argv[1:])
    for arg in wanted:
        if not re.fullmatch(r'prime-slider-[a-z0-9-]+', arg):
            raise SystemExit('ID no válido: %s' % arg)
    for widget in inventory:
        if not wanted or widget['legacy_widget_id'] in wanted:
            write(widget)
