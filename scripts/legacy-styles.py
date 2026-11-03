#!/usr/bin/env python3
"""Genera, corrige y poda los controles de estilo heredados (`maps/bdt-*.styles.php`) de Element Pack.

Lee la referencia de Element Pack 10.0.1 desde su ZIP, sin extraerlo ni ejecutarlo
(`legacy_controls`, con `reference_archive.ReferenceArchive`), extrae los controles con selectores
y los reescribe hacia el marcado propio según el mapa `classes` de `maps/bdt-*.php`. El resultado
son datos: nombres de ajustes, tipos y plantillas CSS para que Elementor siga generando el CSS que
el usuario ya configuró.

Reglas (F8, frente B):
- `rewrite()` respeta el límite de la clase: `.bdt-x` no toca `.bdt-x-title`. Una clase sin entrada
  conserva `bdt-` y el selector se omite; la entrada `''` quita la clase (`.elementor-widget-bdt-x`,
  porque `{{WRAPPER}}` ya es el elemento).
- Validación del destino: cada clase `digi-*`/`elementor-*` resultante debe aparecer literalmente,
  con límite de clase, en el PHP o el JS de DIGITALÍSIMO Elements o de Migración (sin los mapas), o
  casar con un patrón de `tests/fixtures/legacy-styles-dynamic.json`, cada uno con su evidencia
  `archivo:línea`. Si no, la parte del selector está muerta: se omite y se anota.
- Nunca se retira un control vivo. Las bajas y los cambios de los mapas existentes se anotan en
  `tests/fixtures/f8-legacy-styles-dead.json` con el control anterior completo (su JSON coincide con
  la huella de `tests/fixtures/f8-legacy-styles-baseline.json`).

Uso (siempre con -I; las referencias en reference/ o DIGITALISIMO_REFERENCE_DIR):
  python3 -I scripts/legacy-styles.py bdt-x [bdt-y …]        mapa nuevo: genera su .styles.php desde 10.0.1
  python3 -I scripts/legacy-styles.py --merge bdt-x …        V.13: vivos del .styles.php actual ∪ 10.0.1 corregido
  python3 -I scripts/legacy-styles.py --prune [bdt-x …]      quita lo muerto de un .styles.php sin leer la fuente
                                                             (sin IDs: los 201)
  python3 -I scripts/legacy-styles.py --check                lista los selectores muertos; sale con 1 si hay
  --ep991=<zip|carpeta>  copia opcional de 9.9.1 para un mapa movido al Addon (nunca necesaria)
"""
import json
import os
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.dont_write_bytecode = True
MAPS = ROOT / 'digitalisimo-migration' / 'legacy' / 'maps'
DYNAMIC = ROOT / 'tests' / 'fixtures' / 'legacy-styles-dynamic.json'
DEAD = ROOT / 'tests' / 'fixtures' / 'f8-legacy-styles-dead.json'
GROUPS = {
    'Group_Control_Typography': 'typography', 'Group_Control_Background': 'background', 'Group_Control_Border': 'border',
    'Group_Control_Box_Shadow': 'box-shadow', 'Group_Control_Text_Shadow': 'text-shadow', 'Group_Control_Text_Stroke': 'text-stroke',
    'Group_Control_Css_Filter': 'css-filter',
}
TYPES = {'COLOR', 'SLIDER', 'DIMENSIONS', 'CHOOSE', 'SELECT', 'NUMBER', 'TEXT', 'SWITCHER', 'HIDDEN'}
# Traducción de las cadenas: con TRANSLATABLE, __( 'x', 'dominio' ) devuelve {'__t': 'x', 'd': 'dominio'} (C9).
TRANSLATABLE = False


class Unparsable(Exception):
    pass


class PhpLiteral:
    """Intérprete mínimo de literales PHP: cadenas, números, arrays, constantes conocidas y llamadas __()/esc_html__()."""

    TOKEN = re.compile(r"""\s*(?:(?P<comment>//[^\n]*|/\*.*?\*/)|(?P<str>'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")|(?P<num>-?\d+(?:\.\d+)?|-?\.\d+)|(?P<arrow>=>)|(?P<punct>[\[\](),.])|(?P<id>[A-Za-z_\\][\w\\]*(?:::\w+)?))""", re.S)
    # Constantes que el llamador sabe resolver (p. ej. la URL de los recursos de Prime Slider).
    CONSTANTS = {}

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
        if self.i < len(self.tokens):
            raise Unparsable('sobra %s' % (self.peek()[1],))
        return value

    def expr(self):
        value = self.atom()
        while self.peek()[1] == '.':
            self.take('.')
            right = self.atom()
            if isinstance(value, dict) or isinstance(right, dict):
                raise Unparsable('concatenación con texto traducible')
            if not isinstance(value, (str, int, float)) or not isinstance(right, (str, int, float)):
                raise Unparsable('concatenación no literal')
            value = str(value) + str(right)
        return value

    def atom(self):
        kind, val = self.take()
        if kind == 'str':
            body = val[1:-1]
            if val[0] == "'":
                return re.sub(r"\\([\\'])", r'\1', body)
            if '$' in body:
                raise Unparsable('cadena con variables')
            return bytes(body, 'utf-8').decode('unicode_escape')
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
            if val.lstrip('\\') in self.CONSTANTS:
                return self.CONSTANTS[val.lstrip('\\')]
            if self.peek()[1] == '(':
                self.take('(')
                args = []
                while self.peek()[1] != ')':
                    args.append(self.expr())
                    if self.peek()[1] == ',':
                        self.take(',')
                self.take(')')
                if low in ('__', 'esc_html__', 'esc_attr__', '_x', 'esc_html_x', 'esc_attr_x') and args:
                    if TRANSLATABLE and isinstance(args[0], str) and len(args) >= 2 and isinstance(args[-1], str):
                        return {'__t': args[0], 'd': args[-1]}
                    return args[0]
                raise Unparsable('llamada %s' % val)
        raise Unparsable('token %s' % val)

    def array(self, close):
        items = []
        keyed = {}
        is_list = True
        while self.peek()[1] != close:
            if self.peek()[1] is None:
                raise Unparsable('array sin cerrar')
            value = self.expr()
            if self.peek()[1] == '=>':
                self.take('=>')
                key = value
                if isinstance(key, dict):
                    raise Unparsable('clave traducible')
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
    return PhpLiteral(text).parse()


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


def normalize(value):
    """Como PHP: un array con claves 0..n-1 en orden es una lista. Así el JSON coincide con el de la prueba de Node."""
    if isinstance(value, dict):
        keys = list(value.keys())
        if keys and all(isinstance(k, int) and k == i for i, k in enumerate(keys)):
            return [normalize(v) for v in value.values()]
        return {str(k): normalize(v) for k, v in value.items()}
    if isinstance(value, list):
        return [normalize(v) for v in value]
    if isinstance(value, float) and value.is_integer():
        return int(value)
    return value


# ---------------------------------------------------------------------------------------------- mapas


def map_block(legacy_id, key):
    """Texto literal de `'key' => array( … )` del mapa, sin ejecutar PHP (los mapas llevan closures)."""
    text = (MAPS / (legacy_id + '.php')).read_text(encoding='utf-8')
    m = re.search(r"^\t'%s'\s*=>\s*" % re.escape(key), text, re.M)
    if not m:
        return None
    i = m.end()
    if not text.startswith('array(', i):
        raise Unparsable('%s: %s no es un array literal' % (legacy_id, key))
    depth, j, quote = 0, i + 5, None
    while j < len(text):
        c = text[j]
        if quote:
            if c == '\\':
                j += 2
                continue
            if c == quote:
                quote = None
        elif c in '\'"':
            quote = c
        elif c == '(':
            depth += 1
        elif c == ')':
            depth -= 1
            if depth == 0:
                return text[i:j + 1]
        j += 1
    raise Unparsable('%s: %s sin cerrar' % (legacy_id, key))


def classes_for(legacy_id):
    block = map_block(legacy_id, 'classes')
    classes = literal(block) if block else {}
    return classes if isinstance(classes, dict) else {}


def target_of(legacy_id):
    m = re.search(r"^\t'target'\s*=>\s*'([^']+)'", (MAPS / (legacy_id + '.php')).read_text(encoding='utf-8'), re.M)
    return m.group(1) if m else None


def read_styles(legacy_id):
    path = MAPS / (legacy_id + '.styles.php')
    if not path.exists():
        return None, None
    text = path.read_text(encoding='utf-8')
    i = text.index('return ')
    return text[:i], literal(text[i + 7:text.rindex(';')])


def write_styles(legacy_id, header, styles):
    (MAPS / (legacy_id + '.styles.php')).write_text(header + 'return ' + php(styles, 0) + ';\n', encoding='utf-8')


# ------------------------------------------------------------------------------------- validación


TOKEN = re.compile(r'(?<![A-Za-z0-9_-])((?:digi|elementor)-[A-Za-z0-9_-]+)')
_CORPUS = None
_DYNAMIC = None


def corpus_tokens():
    """Clases digi-*/elementor-* que aparecen literalmente en el PHP y el JS de Elements y de Migración (sin los mapas).

    Es la misma regla que `corpus()` de tests/fixtures/legacy-styles/styles-lib.mjs: no la cambies en un solo lado.
    """
    global _CORPUS
    if _CORPUS is None:
        tokens = set()
        skip = MAPS.resolve()
        for top in ('digitalisimo-elements', 'digitalisimo-migration'):
            for dirpath, dirnames, filenames in os.walk(ROOT / top):
                here = Path(dirpath)
                dirnames[:] = sorted(d for d in dirnames if d not in ('node_modules', '.git') and not (here / d).is_symlink() and (here / d).resolve() != skip)
                for name in filenames:
                    path = here / name
                    if not name.endswith(('.php', '.js')) or path.is_symlink() or path.stat().st_size > 32 * 1024 * 1024:
                        continue
                    tokens.update(TOKEN.findall(path.read_text(encoding='latin-1')))
        _CORPUS = tokens
    return _CORPUS


def dynamic_patterns():
    global _DYNAMIC
    if _DYNAMIC is None:
        data = json.loads(DYNAMIC.read_text(encoding='utf-8')) if DYNAMIC.exists() else {'patterns': []}
        _DYNAMIC = [re.compile(p['pattern']) for p in data['patterns']]
    return _DYNAMIC


def split_selector(selector):
    parts, depth, cur = [], 0, ''
    for ch in str(selector):
        if ch in '([':
            depth += 1
        elif ch in ')]':
            depth -= 1
        if ch == ',' and depth == 0:
            parts.append(cur.strip())
            cur = ''
            continue
        cur += ch
    if cur.strip():
        parts.append(cur.strip())
    return parts


def classes_of(selector):
    clean = re.sub(r'\{\{[^}]*\}\}', ' ', str(selector))
    return re.findall(r'\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)', clean)


def dead_classes(part):
    tokens, dynamic = corpus_tokens(), dynamic_patterns()
    return [c for c in classes_of(part) if re.match(r'(?:digi|elementor)-', c) and c not in tokens and not any(p.search(c) for p in dynamic)]


def prune(control):
    """Quita las partes de selector muertas; None si no queda ninguna. Igual que prune() de la prueba de Node."""
    if 'group' in control:
        parts = [p for p in split_selector(control['selector']) if not dead_classes(p)]
        return dict(control, selector=', '.join(parts)) if parts else None
    selectors = {}
    for key, css in (control.get('selectors') or {}).items():
        parts = [p for p in split_selector(key) if not dead_classes(p)]
        if parts:
            selectors[', '.join(parts)] = css
    return dict(control, selectors=selectors) if selectors else None


def control_dead(control):
    parts = split_selector(control['selector']) if 'group' in control else [p for key in (control.get('selectors') or {}) for p in split_selector(key)]
    return sorted({c for p in parts for c in dead_classes(p)})


# -------------------------------------------------------------------------------------- reescritura


def rewrite(selector, classes):
    """Sustituye cada clase de Element Pack por su destino respetando el límite de la clase.

    Una clave que empieza por `.` ya tiene su límite izquierdo en el punto (y así casa también en `.a.bdt-x`); otra
    (`a.bdt-x`) no puede ir precedida de un carácter de identificador. Por la derecha nunca puede seguir `[\\w-]`.
    """
    for old, new in sorted(classes.items(), key=lambda item: -len(item[0])):
        left = '' if old[:1] in '.#[' else r'(?<![\w-])'
        selector = re.sub(left + re.escape(old) + r'(?![\w-])', lambda _m: new, selector)
    selector = re.sub(r'\}\}\s+(?=[:\[])', '}}', selector)
    # Un selector sin {{WRAPPER}} ni {{ID}} afectaría a toda la página: no se reproduce.
    if re.search(r'\.bdt-|bdt-[a-z]', selector) or ('{{WRAPPER}}' not in selector and '{{ID}}' not in selector):
        return None
    return re.sub(r'\s+', ' ', selector).strip()


def rewrite_parts(selector, classes, notes):
    out = []
    for part in split_selector(selector):
        new = rewrite(part, classes)
        if new is None:
            notes.append(('sin clase destino', part))
            continue
        dead = dead_classes(new)
        if dead:
            notes.append(('clase inexistente ' + ', '.join(dead), part))
            continue
        out.append(new)
    return out


def from_reference(legacy_id, classes):
    """Controles con selectores del widget de 10.0.1 (o de 9.9.1 si se movió al Addon), reescritos y validados."""
    sys.path.insert(0, str(HERE))
    import legacy_controls  # noqa: E402
    source = legacy_controls.widget_source(legacy_id)
    if source is None:
        raise SystemExit('%s: no está en Element Pack 10.0.1 (pasó al Addon); usa --prune o --ep991=<9.9.1>' % legacy_id)
    styles, skipped, names = [], [], []
    for control in legacy_controls.detail(source):
        if control['var'] == '$this':
            names.append(control['name'])
        if control['var'] != '$this' or (control['kind'] != 'group' and not control.get('raw_selectors')):
            continue
        if control['kind'] == 'group' and not control.get('selector'):
            continue
        notes = []
        try:
            if control['kind'] == 'group':
                group = GROUPS.get(control['group'])
                selector = literal(control['selector']) if control['selector'] else None
                if not group or not isinstance(selector, str):
                    raise Unparsable('grupo')
                new = ', '.join(rewrite_parts(selector, classes, notes))
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
                parts = rewrite_parts(str(selector), classes, notes)
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
            skipped.append({'name': control['name'], 'reason': str(error), 'selectors': [p for _, p in notes][:4]})
            continue
        if notes:
            skipped.append({'name': control['name'], 'reason': 'parte omitida', 'selectors': [p for _, p in notes][:4]})
    return styles, skipped, set(names), str(source)


# ---------------------------------------------------------------------------------------------- registro


def load_dead():
    if DEAD.exists():
        return json.loads(DEAD.read_text(encoding='utf-8'))
    return {'note': '', 'rule': '', 'maps': {}}


def save_dead(data):
    data['note'] = ('Bajas y podas de los maps/*.styles.php de F8 (frente B). Por mapa: `mode` (prune = sólo se quitaron selectores '
                    'muertos; merge = uno de los 6 mapas corregidos o Advanced Icon Box, V.13), `classes` (cada clase muerta con su '
                    'evidencia) y `controls` (cada control cambiado o retirado: `index` es su aparición entre los del mismo nombre en '
                    'la línea base, `before` el control anterior completo, cuya huella es la de f8-legacy-styles-baseline.json, '
                    '`after` la huella nueva o null si se retiró, y `dead` sus clases muertas). En merge, `omitted` lista los '
                    'controles de Element Pack 10.0.1 que no tienen destino (sin clase propia).')
    data['rule'] = ('Una clase digi-*/elementor-* de un selector está muerta si no aparece literalmente, con límite de clase, en el PHP '
                    'ni en el JS de digitalisimo-elements/ ni de digitalisimo-migration/ (sin legacy/maps/) y no casa con ningún '
                    'patrón de tests/fixtures/legacy-styles-dynamic.json. Ningún widget la imprime: el selector no aplica a nada.')
    data['maps'] = dict(sorted(data['maps'].items()))
    DEAD.write_text(json.dumps(data, indent=1, ensure_ascii=False) + '\n', encoding='utf-8')


def evidence(cls):
    return 'sin literal en el PHP/JS de Elements ni de Migración y sin patrón dinámico'


def occurrences(styles):
    seen, out = {}, []
    for control in styles:
        n = seen.get(control['name'], 0)
        seen[control['name']] = n + 1
        out.append((control['name'], n))
    return out


def sha(control):
    import hashlib
    return hashlib.sha256(canonical(normalize(control)).encode('utf-8')).hexdigest()


def canonical(value):
    if isinstance(value, list):
        return '[' + ','.join(canonical(v) for v in value) + ']'
    if isinstance(value, dict):
        return '{' + ','.join(json.dumps(k, ensure_ascii=False) + ':' + canonical(value[k]) for k in sorted(value)) + '}'
    if isinstance(value, bool) or value is None:
        return json.dumps(value)
    if isinstance(value, float):
        return repr(value) if not value.is_integer() else str(int(value))
    if isinstance(value, int):
        return str(value)
    return json.dumps(value, ensure_ascii=False)


def record(dead, legacy_id, mode, changes, omitted=None):
    entry = dead['maps'].get(legacy_id) or {'mode': mode, 'classes': {}, 'controls': []}
    entry['mode'] = mode
    known = {(c['name'], c['index']): c for c in entry['controls']}
    for (name, index), before, after in changes:
        prev = known.get((name, index))
        row = {'name': name, 'index': index, 'before': prev['before'] if prev else normalize(before),
               'after': sha(after) if after is not None else None, 'dead': control_dead(before)}
        if prev:
            prev.update(row)
        else:
            entry['controls'].append(row)
            known[(name, index)] = row
        for cls in row['dead']:
            entry['classes'][cls] = evidence(cls)
    entry['controls'] = [c for c in entry['controls'] if c['after'] is None or c['after'] != sha(c['before'])]
    entry['classes'] = dict(sorted({cls: entry['classes'][cls] for c in entry['controls'] for cls in c['dead']}.items()))
    if omitted is not None:
        entry['omitted'] = omitted
    if entry['controls'] or entry.get('omitted'):
        dead['maps'][legacy_id] = entry
    else:
        dead['maps'].pop(legacy_id, None)


# ---------------------------------------------------------------------------------------------- modos


def generate(legacy_id):
    classes = classes_for(legacy_id)
    styles, skipped, _names, source = from_reference(legacy_id, classes)
    header = ("<?php\n/**\n * Controles de estilo de Element Pack Pro 10.0.1 `%s` con selectores del widget propio.\n"
              " * Generado con scripts/legacy-styles.py; no editar a mano.\n */\ndefined( 'ABSPATH' ) || exit;\n\n") % legacy_id
    write_styles(legacy_id, header, styles)
    print('%s: %d controles de estilo de %s, %d omitidos' % (legacy_id, len(styles), source, len(skipped)))
    for item in skipped:
        print('   omitido', item['name'], '(%s)' % item['reason'], ' | '.join(item['selectors']))


def merge(legacy_id, dead):
    """V.13: parte de los controles vivos del .styles.php actual, los reescribe desde 10.0.1 con las clases corregidas,
    conserva tal cual los vivos que 10.0.1 no tiene o no traduce y añade los que sólo tiene 10.0.1."""
    _header, old = read_styles(legacy_id)
    classes = classes_for(legacy_id)
    fresh, skipped, names, source = from_reference(legacy_id, classes)
    pool = {}
    for key, control in zip(occurrences(fresh), fresh):
        pool[key] = control
    out, changes, kept = [], [], 0
    for key, control in zip(occurrences(old or []), old or []):
        if key in pool:
            new = pool.pop(key)
            out.append(new)
            if sha(new) != sha(control):
                changes.append((key, control, new))
            continue
        alive = prune(control)
        if alive is not None and alive == control:
            out.append(control)
            kept += 1
        elif alive is not None:
            out.append(alive)
            changes.append((key, control, alive))
        else:
            changes.append((key, control, None))
    added = [control for key, control in zip(occurrences(fresh), fresh) if key in pool]
    out.extend(added)
    header = ("<?php\n/**\n * Controles de estilo de Element Pack Pro 10.0.1 `%s` con selectores del widget propio, más los que sólo\n"
              " * tenía 9.9.1 y siguen vivos. Generado con scripts/legacy-styles.py --merge; no editar a mano.\n */\n"
              "defined( 'ABSPATH' ) || exit;\n\n") % legacy_id
    write_styles(legacy_id, header, out)
    omitted = [{'name': s['name'], 'reason': s['reason'], 'selectors': s['selectors']} for s in skipped if s['reason'] != 'parte omitida' and s['name'] not in {c['name'] for c in out}]
    record(dead, legacy_id, 'merge', changes, omitted)
    removed = [k[0] for k, _b, a in changes if a is None]
    print('%s: %d controles (%d de %s, %d vivos sólo de antes, %d nuevos), %d retirados por muertos: %s' % (
        legacy_id, len(out), len(out) - kept - len(added), source, kept, len(added), len(removed), ', '.join(removed) or '—'))
    for item in skipped:
        print('   omitido', item['name'], '(%s)' % item['reason'], ' | '.join(item['selectors']))


def prune_map(legacy_id, dead):
    header, old = read_styles(legacy_id)
    if old is None:
        return 0
    out, changes = [], []
    for key, control in zip(occurrences(old), old):
        alive = prune(control)
        if alive is not None:
            out.append(alive)
        if alive != control:
            changes.append((key, control, alive))
    if changes:
        if 'podados con scripts/legacy-styles.py --prune' not in header:
            header = header.replace(' * Generado con scripts/legacy-styles.py; no editar a mano.',
                                    ' * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune\n'
                                    ' * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.')
        write_styles(legacy_id, header, out)
        record(dead, legacy_id, dead['maps'].get(legacy_id, {}).get('mode', 'prune'), changes)
        print('%s: %d controles cambiados, %d retirados' % (legacy_id, len(changes), sum(1 for c in changes if c[2] is None)))
    return len(changes)


def check():
    bad = 0
    for path in sorted(MAPS.glob('*.styles.php')):
        legacy_id = path.name[:-len('.styles.php')]
        _header, styles = read_styles(legacy_id)
        for control in styles:
            dead = control_dead(control)
            if dead:
                bad += 1
                print('%s %s: %s' % (legacy_id, control['name'], ', '.join(dead)))
    print('%d controles con selectores muertos' % bad)
    return bad


def all_ids():
    return sorted(p.name[:-len('.styles.php')] for p in MAPS.glob('*.styles.php'))


if __name__ == '__main__':
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    flags = [a for a in sys.argv[1:] if a.startswith('--')]
    for flag in flags:
        if flag.startswith('--ep991='):
            sys.path.insert(0, str(HERE))
            import legacy_controls  # noqa: E402
            legacy_controls.use_ep991(flag.split('=', 1)[1])
    for legacy in args:
        if not re.fullmatch(r'bdt-[a-z0-9-]+|[a-z0-9-]+', legacy):
            raise SystemExit('ID no válido: %s' % legacy)
    if '--check' in flags:
        sys.exit(1 if check() else 0)
    elif '--prune' in flags:
        record_data = load_dead()
        for legacy in args or all_ids():
            prune_map(legacy, record_data)
        save_dead(record_data)
    elif '--merge' in flags:
        record_data = load_dead()
        for legacy in args:
            merge(legacy, record_data)
        save_dead(record_data)
    else:
        for legacy in args:
            generate(legacy)
