#!/usr/bin/env python3
"""Genera los controles de estilo heredados de un widget de Element Pack.

Lee la copia local de referencia (sin ejecutarla), extrae los controles de la pestaña Estilo con
sus selectores y los reescribe hacia el marcado propio según el mapa de clases `classes` del
archivo maps/bdt-*.php. El resultado (maps/bdt-*.styles.php) son datos: nombres de ajustes,
tipos y plantillas CSS necesarios para que Elementor siga generando el CSS que el usuario ya
configuró. Un selector que no puede traducirse se omite.

Uso: python3 scripts/legacy-styles.py bdt-accordion [bdt-otro …]
"""
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ELEMENTS = ROOT / 'digitalisimo-elements'
EP = ELEMENTS / 'bdthemes-element-pack'
MAPS = ELEMENTS / 'modules' / 'digitalisimo-legacy' / 'maps'
GROUPS = {
    'Group_Control_Typography': 'typography', 'Group_Control_Background': 'background', 'Group_Control_Border': 'border',
    'Group_Control_Box_Shadow': 'box-shadow', 'Group_Control_Text_Shadow': 'text-shadow', 'Group_Control_Text_Stroke': 'text-stroke',
    'Group_Control_Css_Filter': 'css-filter',
}
TYPES = {'COLOR', 'SLIDER', 'DIMENSIONS', 'CHOOSE', 'SELECT', 'NUMBER', 'TEXT', 'SWITCHER', 'HIDDEN'}


class Unparsable(Exception):
    pass


class PhpLiteral:
    """Intérprete mínimo de literales PHP: cadenas, números, arrays y llamadas __()/esc_html__()."""

    TOKEN = re.compile(r"""\s*(?:(?P<comment>//[^\n]*|/\*.*?\*/)|(?P<str>'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")|(?P<num>-?\d+(?:\.\d+)?)|(?P<arrow>=>)|(?P<punct>[\[\](),.])|(?P<id>[A-Za-z_\\][\w\\]*(?:::\w+)?))""", re.S)

    def __init__(self, text):
        self.tokens = []
        pos = 0
        while pos < len(text):
            m = self.TOKEN.match(text, pos)
            if not m or m.end() == pos:
                if text[pos:].strip() == '':
                    break
                raise Unparsable(text[pos:pos + 40])
            kind = m.lastgroup
            if kind != 'comment':
                self.tokens.append((kind, m.group(kind)))
            pos = m.end()
        self.i = 0

    def peek(self):
        return self.tokens[self.i] if self.i < len(self.tokens) else (None, None)

    def take(self, value=None):
        tok = self.peek()
        if value is not None and tok[1] != value:
            raise Unparsable('esperaba %s y encontró %s' % (value, tok[1]))
        self.i += 1
        return tok

    def parse(self):
        value = self.expr()
        return value

    def expr(self):
        value = self.atom()
        while self.peek()[1] == '.':
            self.take('.')
            right = self.atom()
            if not isinstance(value, (str, int, float)) or not isinstance(right, (str, int, float)):
                raise Unparsable('concatenación no literal')
            value = str(value) + str(right)
        return value

    def atom(self):
        kind, val = self.take()
        if kind == 'str':
            body = val[1:-1]
            return body.replace("\\'", "'") if val[0] == "'" else bytes(body, 'utf-8').decode('unicode_escape')
        if kind == 'num':
            return float(val) if '.' in val else int(val)
        if val == '[':
            return self.array(']')
        if kind == 'id':
            low = val.lower()
            if low == 'array' and self.peek()[1] == '(':
                self.take('(')
                return self.array(')')
            if low in ('true', 'false'):
                return low == 'true'
            if low == 'null':
                return None
            if val.startswith('Controls_Manager::'):
                return val.split('::')[1].lower()
            if self.peek()[1] == '(':
                self.take('(')
                args = []
                while self.peek()[1] != ')':
                    args.append(self.expr())
                    if self.peek()[1] == ',':
                        self.take(',')
                self.take(')')
                if low in ('__', 'esc_html__', 'esc_attr__', '_x', 'esc_html_x') and args:
                    return args[0]
                raise Unparsable('llamada %s' % val)
        raise Unparsable('token %s' % val)

    def array(self, close):
        items = []
        keyed = {}
        is_list = True
        while self.peek()[1] != close:
            value = self.expr()
            if self.peek()[1] == '=>':
                self.take('=>')
                key = value
                value = self.expr()
                keyed[key] = value
                is_list = False
            else:
                items.append(value)
            if self.peek()[1] == ',':
                self.take(',')
        self.take(close)
        if is_list:
            return items
        for index, value in enumerate(items):
            keyed[index] = value
        return keyed


def literal(text):
    if text is None:
        return None
    parser = PhpLiteral(text)
    value = parser.parse()
    return value


def rewrite(selector, classes):
    for old, new in sorted(classes.items(), key=lambda item: -len(item[0])):
        selector = selector.replace(old, new)
    return None if re.search(r'\.bdt-|bdt-[a-z]', selector) else selector


def php(value, indent=1):
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
    if isinstance(value, bool):
        return 'true' if value else 'false'
    if value is None:
        return 'null'
    if isinstance(value, (int, float)):
        return repr(value)
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"


def classes_for(legacy_id):
    code = "$m = require '%s'; echo json_encode( $m['classes'] ?? array() );" % (MAPS / (legacy_id + '.php'))
    cli = ['node', str(Path.home() / '.npm/_npx/98db5c2db358871c/node_modules/.bin/php-wasm-cli')]
    try:
        out = subprocess.run(['php', '-r', "define('ABSPATH',1);" + code], capture_output=True, text=True, check=True).stdout
    except FileNotFoundError:
        tmp = Path('/tmp/digi-legacy-classes.php')
        tmp.write_text("<?php define('ABSPATH',1); " + code)
        out = subprocess.run(cli + [str(tmp)], capture_output=True, text=True, check=True).stdout
    return json.loads(out or '{}')


def generate(legacy_id):
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from legacy_controls import detail  # noqa: E402
    inventory = {w['legacy_widget_id']: w for w in json.loads((ROOT / 'docs/element-pack-inventory.json').read_text())['widgets']}
    source = ROOT / inventory[legacy_id]['source_file']
    classes = classes_for(legacy_id)
    styles, skipped = [], []
    for control in detail(source):
        # Cualquier control propio del widget que genere CSS: también hay posiciones y anchos en Contenido.
        if control['var'] != '$this' or (control['kind'] != 'group' and not control.get('raw_selectors')):
            continue
        if control['kind'] == 'group' and not control.get('selector'):
            continue
        try:
            if control['kind'] == 'group':
                group = GROUPS.get(control['group'])
                selector = literal(control['selector']) if control['selector'] else None
                if not group or not isinstance(selector, str):
                    raise Unparsable('grupo')
                new = ', '.join(filter(None, (rewrite(part.strip(), classes) for part in selector.split(','))))
                if not new:
                    raise Unparsable('selector')
                entry = {'name': control['name'], 'group': group, 'selector': new}
                for key in ('types', 'exclude'):
                    if control.get(key):
                        entry[key] = literal(control[key])
                styles.append(entry)
                continue
            ctype = control['type'].split('::')[-1].strip()
            selectors = literal(control['selectors']) if control['selectors'] else None
            if ctype not in TYPES or not isinstance(selectors, dict):
                raise Unparsable('sin selectores')
            new_selectors = {}
            for selector, css in selectors.items():
                parts = [rewrite(part.strip(), classes) for part in str(selector).split(',')]
                parts = [p for p in parts if p]
                if parts and isinstance(css, str):
                    new_selectors[', '.join(parts)] = css
            if not new_selectors:
                raise Unparsable('selector')
            entry = {'name': control['name'], 'type': ctype.lower(), 'responsive': control['kind'] == 'responsive', 'selectors': new_selectors}
            for key in ('default', 'desktop_default', 'tablet_default', 'mobile_default', 'selectors_dictionary', 'condition', 'size_units', 'options'):
                raw = control.get('raw_' + key)
                if raw:
                    entry[key] = literal(raw)
            styles.append(entry)
        except Unparsable as error:
            skipped.append('%s (%s)' % (control['name'], error))
    target = MAPS / (legacy_id + '.styles.php')
    header = "<?php\n/**\n * Controles de estilo de Element Pack Pro 9.9.1 `%s` con selectores del widget propio.\n * Generado con scripts/legacy-styles.py; no editar a mano.\n */\ndefined( 'ABSPATH' ) || exit;\n\n" % legacy_id
    target.write_text(header + 'return ' + php(styles, 0) + ';\n', encoding='utf-8')
    print('%s: %d controles de estilo, %d omitidos' % (legacy_id, len(styles), len(skipped)))
    for item in skipped:
        print('   omitido', item)


if __name__ == '__main__':
    for legacy in sys.argv[1:]:
        generate(legacy)
