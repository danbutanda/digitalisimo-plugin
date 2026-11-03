"""Extrae por análisis estático los controles Elementor de un widget de Element Pack o de Prime Slider.

Sigue las llamadas `$this->metodo()` dentro de `register_controls()`, incluidos los traits de la
referencia, y devuelve por control su pestaña, tipo y el texto PHP literal de sus argumentos.
No ejecuta código de la referencia.

Las referencias se leen de sus ZIP sin extraerlos (`reference_archive.ReferenceArchive`, que rechaza
el archivo entero ante rutas peligrosas o bombas) o, si se indica, de una carpeta ya extraída. La
carpeta de los ZIP es `reference/` del repositorio o `DIGITALISIMO_REFERENCE_DIR`; cada ZIP puede
cambiarse con su variable (`DIGITALISIMO_EP_ZIP`, `DIGITALISIMO_EP_LITE_ZIP`, `DIGITALISIMO_PS_LITE_ZIP`,
`DIGITALISIMO_PS_PRO_ZIP`). Element Pack 9.9.1 es opcional (`use_ep991()` o `DIGITALISIMO_EP991`) y
sólo sirve para comprobar: ningún archivo generado depende de él. Los scripts que lo usan se ejecutan
con `python3 -I`.
"""
import os
import re
import sys
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parent.parent
sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parent))
from reference_archive import ReferenceArchive, UnsafeArchive  # noqa: E402

REFERENCE = Path(os.environ.get('DIGITALISIMO_REFERENCE_DIR') or ROOT / 'reference')
# clave: (variable de entorno, nombre del ZIP en REFERENCE, carpeta raíz del plugin, fuentes de traits)
PLUGINS = {
    'ep': ('DIGITALISIMO_EP_ZIP', 'bdthemes-element-pack-2.zip', 'bdthemes-element-pack', ('ep',)),
    'ep-lite': ('DIGITALISIMO_EP_LITE_ZIP', 'bdthemes-element-pack-lite.10.0.1.zip', 'bdthemes-element-pack-lite', ('ep-lite',)),
    'ep991': ('DIGITALISIMO_EP991', None, 'bdthemes-element-pack', ('ep991',)),
    'ps-lite': ('DIGITALISIMO_PS_LITE_ZIP', 'bdthemes-prime-slider-lite.4.7.0.zip', 'bdthemes-prime-slider-lite', ('ps-lite',)),
    # Pro usa los traits de Lite (Global_Widget_Controls, Group_Control_Query) además de los suyos.
    'ps-pro': ('DIGITALISIMO_PS_PRO_ZIP', 'bdthemes-prime-slider.zip', 'bdthemes-prime-slider', ('ps-pro', 'ps-lite')),
}
TRAIT_GLOBS = {
    'ep': (r'traits/.*\.php$', r'includes/.*trait.*\.php$'),
    'ep-lite': (r'traits/.*\.php$', r'includes/.*trait.*\.php$'),
    'ep991': (r'traits/.*\.php$', r'includes/.*trait.*\.php$'),
    'ps-lite': (r'traits/global-widget-controls\.php$', r'traits/query-controls/group-query/group-control-query\.php$'),
    'ps-pro': (r'traits/.*\.php$', r'includes/class-animation-controls\.php$', r'includes/class-swiper-effects\.php$'),
}
_OPEN = {}
_OVERRIDE = {}


class Plugin:
    """Un plugin de referencia: ZIP (sin extraer) o carpeta, con su carpeta raíz."""

    def __init__(self, key, path):
        self.key = key
        self.path = Path(path)
        self.top = PLUGINS[key][2]
        if self.path.is_dir():
            self.archive = None
            base = self.path / self.top if (self.path / self.top).is_dir() else self.path
            self.base = base.resolve()
        else:
            self.archive = ReferenceArchive(self.path)
            tops = self.archive.top_dirs()
            if len(tops) != 1:
                raise UnsafeArchive(f'{self.path.name}: se esperaba una sola carpeta raíz y hay {len(tops)}')
            self.top = tops[0]
            self.base = None

    def _local(self, rel):
        target = (self.base / rel).resolve()
        if self.base not in target.parents or target.is_symlink():
            raise UnsafeArchive(f'{rel}: fuera de la referencia')
        return target

    def exists(self, rel):
        rel = clean(rel)
        if self.archive:
            return self.archive.exists(f'{self.top}/{rel}')
        return self._local(rel).is_file()

    def read(self, rel):
        rel = clean(rel)
        if self.archive:
            return self.archive.read_text(f'{self.top}/{rel}')
        return self._local(rel).read_text(encoding='utf-8', errors='replace')

    def files(self, prefix=''):
        prefix = clean(prefix) if prefix else ''
        if self.archive:
            start = f'{self.top}/'
            return [name[len(start):] for name in self.archive.files(start + prefix)]
        return sorted(str(p.relative_to(self.base)) for p in (self.base / prefix).rglob('*') if p.is_file() and not p.is_symlink()) if (self.base / prefix).is_dir() else []

    def file(self, rel):
        return RefFile(self, clean(rel))

    def __str__(self):
        return f'{self.path.name}:{self.top}'


def clean(rel):
    rel = str(rel).replace('\\', '/')
    parts = PurePosixPath(rel).parts
    if not parts or rel.startswith('/') or any(part in ('..', '.', '') for part in parts):
        raise UnsafeArchive(f'{rel!r}: ruta no válida')
    return '/'.join(parts)


class RefFile:
    """Archivo dentro de una referencia; se lee como un Path (`read_text`)."""

    def __init__(self, plugin, rel):
        self.plugin = plugin
        self.rel = rel

    def read_text(self, encoding='utf-8', errors='replace'):
        return self.plugin.read(self.rel)

    def exists(self):
        return self.plugin.exists(self.rel)

    @property
    def name(self):
        return self.rel.rsplit('/', 1)[-1]

    def __str__(self):
        return f'{self.plugin.top}/{self.rel}'



def use_ep991(path):
    """Copia opcional de Element Pack 9.9.1 (ZIP o carpeta), sólo para comprobar."""
    _OVERRIDE['ep991'] = Path(path)
    _OPEN.pop('ep991', None)


def plugin(key, required=True):
    if key in _OPEN:
        return _OPEN[key]
    env, name, _top, _traits = PLUGINS[key]
    path = _OVERRIDE.get(key) or (Path(os.environ[env]) if os.environ.get(env) else (REFERENCE / name if name else None))
    if path is None or not path.exists():
        if required:
            raise SystemExit(f'Falta la referencia {key}: {path or env} (DIGITALISIMO_REFERENCE_DIR o {env})')
        return None
    _OPEN[key] = Plugin(key, path)
    return _OPEN[key]


def require_source(legacy_id):
    """Como widget_source(), pero termina con un mensaje si el widget sólo está en 9.9.1 y no hay copia de 9.9.1."""
    source = widget_source(legacy_id)
    if source is None:
        raise SystemExit(f'{legacy_id}: no está en Element Pack 10.0.1 (pasó al Addon); indica una copia de 9.9.1 con DIGITALISIMO_EP991=<zip|carpeta>')
    return source


def widget_source(legacy_id, versions=('ep', 'ep991')):
    """Archivo del widget de Element Pack: primero 10.0.1 y, si se movió al Addon, 9.9.1 si está disponible."""
    import json
    inventory = {w['legacy_widget_id']: w for w in json.loads((ROOT / 'docs/element-pack-inventory.json').read_text())['widgets']}
    rel = inventory[legacy_id]['source_file'].split('/bdthemes-element-pack/', 1)[1]
    for key in versions:
        source = plugin(key, required=(key == 'ep'))
        if source and source.exists(rel):
            return source.file(rel)
    return None


def strip_comments(src):
    """Quita comentarios PHP (`//`, `#`, `/* */`) respetando las cadenas: un default comentado no existe."""
    out, i, n, quote = [], 0, len(src), None
    while i < n:
        c = src[i]
        if quote:
            out.append(c)
            if c == '\\' and i + 1 < n:
                out.append(src[i + 1])
                i += 2
                continue
            if c == quote:
                quote = None
            i += 1
            continue
        if c in '\'"':
            quote = c
            out.append(c)
            i += 1
        elif src.startswith('/*', i):
            end = src.find('*/', i + 2)
            i = n if end < 0 else end + 2
        elif src.startswith('//', i) or (c == '#' and not src.startswith('#[', i)):
            end = src.find('\n', i)
            i = n if end < 0 else end
        else:
            out.append(c)
            i += 1
    return ''.join(out)


def methods(src):
    out = {}
    PARAMS.update(signatures(src))
    for m in re.finditer(r'function\s+(\w+)\s*\([^)]*\)\s*(?::\s*\??\w+\s*)?\{', src):
        i, depth = m.end(), 1
        while i < len(src) and depth:
            if src[i] == '{':
                depth += 1
            elif src[i] == '}':
                depth -= 1
            i += 1
        out.setdefault(m.group(1), src[m.end():i - 1])
    return out


PARAMS = {}


def signatures(src):
    """Nombres de parámetros de cada función, para ligar argumentos literales."""
    found = {}
    for m in re.finditer(r'function\s+(\w+)\s*\(([^)]*)\)', src):
        names = re.findall(r'\$(\w+)', m.group(2))
        defaults = {}
        for name, default in re.findall(r"\$(\w+)\s*=\s*('(?:[^'\\]|\\.)*')", m.group(2)):
            defaults[name] = default[1:-1]
        found.setdefault(m.group(1), (names, defaults))
    return found


STR = r"'(?:[^'\\]|\\.)*'"


def concat(expr, env, own=None):
    """Evalúa cadenas, variables conocidas y llamadas $this->helper(...) concatenadas; None si no es literal."""
    parts = re.split(r"\s*\.\s*(?=(?:[^']*'[^']*')*[^']*$)(?![^(]*\))", expr.strip())
    out = ''
    for part in parts:
        part = part.strip()
        call = re.fullmatch(r'\$this->(\w+)\(([^()]*)\)', part)
        if re.fullmatch(STR, part):
            out += part[1:-1].replace("\\'", "'")
        elif re.fullmatch(r'\$\w+', part) and part[1:] in env:
            out += env[part[1:]]
        elif call:
            helper = (own or {}).get(call.group(1)) or trait_methods().get(call.group(1))
            params, defaults = PARAMS.get(call.group(1), ([], {}))
            local = dict(defaults)
            args = [a.strip() for a in re.split(r",(?=(?:[^']*'[^']*')*[^']*$)", call.group(2))] if call.group(2).strip() else []
            for i, a in enumerate(args):
                value = concat(a, env, own)
                if value is not None and i < len(params):
                    local[params[i]] = value
            ret = re.search(r'return\s+([^;]+);', helper or '')
            value = concat(ret.group(1), local, own) if ret else None
            if value is None:
                return None
            out += value
        else:
            return None
    return out


def resolve(body, env, own):
    """Añade al entorno las asignaciones simples del cuerpo y sustituye las variables conocidas."""
    env = dict(env)
    for m in re.finditer(r'\$(\w+)\s*=\s*([^;]+);', body):
        value = concat(m.group(2).strip(), env, own)
        if value is not None:
            env[m.group(1)] = value
    def sub(m):
        name = m.group(1)
        return "'" + env[name].replace("'", "\\'") + "'" if name in env else m.group(0)
    return re.sub(r'\$(\w+)\b(?!\s*(?:=[^=>]|\[|->))', sub, body), env


_TRAIT_CACHE = {}
_CURRENT = {'traits': ('ep',)}


def _traits_of(key):
    """Métodos y firmas de los traits de una referencia (leídos una vez)."""
    if key not in _TRAIT_CACHE:
        found, params = {}, {}
        source = plugin(key, required=False)
        if source:
            saved = dict(PARAMS)
            PARAMS.clear()
            patterns = [re.compile(p) for p in TRAIT_GLOBS[key]]
            for rel in source.files():
                if rel.endswith('.php') and any(p.search(rel) for p in patterns):
                    for k, v in methods(strip_comments(source.read(rel))).items():
                        found.setdefault(k, v)
            params = dict(PARAMS)
            PARAMS.clear()
            PARAMS.update(saved)
        _TRAIT_CACHE[key] = (found, params)
    return _TRAIT_CACHE[key]


_MERGED = {}


def trait_methods():
    keys = _CURRENT['traits']
    if keys not in _MERGED:
        out = {}
        for key in keys:
            for name, body in _traits_of(key)[0].items():
                out.setdefault(name, body)
        _MERGED[keys] = out
    return _MERGED[keys]


def bracket(src, i):
    depth, j = 0, i
    in_str = None
    while j < len(src):
        c = src[j]
        if in_str:
            if c == '\\':
                j += 2
                continue
            if c == in_str:
                in_str = None
        elif c in '\'"':
            in_str = c
        elif c in '[(':
            depth += 1
        elif c in '])':
            depth -= 1
            if depth == 0:
                return src[i:j + 1]
        j += 1
    return src[i:]


def top_value(args, key):
    """Texto literal del valor de `'key' =>` en el primer nivel del array de argumentos."""
    depth, in_str, j = 0, None, 0
    pattern = re.compile(r"'%s'\s*=>\s*" % re.escape(key))
    while j < len(args):
        c = args[j]
        if in_str:
            if c == '\\':
                j += 2
                continue
            if c == in_str:
                in_str = None
            j += 1
            continue
        if c in '\'"':
            if depth == 1:
                m = pattern.match(args, j)
                if m:
                    k = m.end()
                    if args[k] == '[':
                        return bracket(args, k)
                    if args[k:k + 6] == 'array(':
                        return 'array' + bracket(args, k + 5)
                    end, d2, s2 = k, 0, None
                    while end < len(args):
                        ch = args[end]
                        if s2:
                            if ch == '\\':
                                end += 2
                                continue
                            if ch == s2:
                                s2 = None
                        elif ch in '\'"':
                            s2 = ch
                        elif ch in '([':
                            d2 += 1
                        elif ch in ')]':
                            if d2 == 0:
                                break
                            d2 -= 1
                        elif ch == ',' and d2 == 0:
                            break
                        end += 1
                    return args[k:end].strip()
            in_str = c
        elif c in '[(':
            depth += 1
        elif c in '])':
            depth -= 1
        j += 1
    return None


TOK = re.compile(r"(\$\w+)\s*=\s*new\s+(?:\\?Elementor\\)?Repeater\(|start_controls_section\(\s*'([^']+)'(.*?)\)\s*;|(\$\w+)->(add_control|add_responsive_control|add_group_control)\(\s*|\$this->(\w+)\((?:[^;()]|\([^;()]*\))*\)\s*;", re.S)
KEYS = ('type', 'default', 'desktop_default', 'tablet_default', 'mobile_default', 'options', 'selectors', 'selectors_dictionary', 'condition', 'conditions', 'size_units', 'fields', 'prefix_class')


def _args_after(rest, i):
    if rest[i:i + 6] == 'array(':
        return 'array' + bracket(rest, i + 5)
    return bracket(rest, i) if i < len(rest) and rest[i] in '[(' else ''


def _walk(body, own, out, state, seen, env=None):
    body, env = resolve(body, env or {}, own)
    for m in TOK.finditer(body):
        if m.group(1):
            state['repeaters'][m.group(1)] = state['repeaters'].get(m.group(1), 0) + 1
            continue
        if m.group(2):
            state['section'] = m.group(2)
            head = m.group(3)[:500]
            state['tab'] = 'style' if 'TAB_STYLE' in head else ('advanced' if 'TAB_ADVANCED' in head else 'content')
        elif m.group(4):
            rest = body[m.end():]
            var = m.group(4)
            if m.group(5) == 'add_group_control':
                gm = re.match(r"([^,]+?)::get_type\(\)\s*,\s*", rest)
                if not gm:
                    continue
                args = _args_after(rest, gm.end())
                inner = args[5:] if args.startswith('array') else args
                name = top_value(inner, 'name') or ''
                out.append({'var': var, 'kind': 'group', 'gen': state['repeaters'].get(var, 0), 'group': gm.group(1).split('\\')[-1].strip(), 'name': name.strip('\'"'),
                            'tab': state['tab'], 'section': state['section'], 'selector': top_value(inner, 'selector'),
                            'types': top_value(inner, 'types'), 'exclude': top_value(inner, 'exclude'), 'raw_default': top_value(inner, 'default'),
                            'raw_fields_options': top_value(inner, 'fields_options')})
                continue
            nm = re.match(r"'([^']+)'\s*,\s*", rest)
            if not nm:
                continue
            args = _args_after(rest, nm.end())
            inner = args[5:] if args.startswith('array') else args
            entry = {'var': var, 'gen': state['repeaters'].get(var, 0), 'kind': 'responsive' if m.group(5) == 'add_responsive_control' else 'control', 'name': nm.group(1),
                     'tab': state['tab'], 'section': state['section']}
            for key in KEYS:
                entry['raw_' + key] = top_value(inner, key)
            entry['type'] = (entry['raw_type'] or '').replace('Controls_Manager::', '')
            entry['selectors'] = entry['raw_selectors']
            entry['default'] = entry['raw_default']
            if entry['raw_fields']:
                fvar = entry['raw_fields'].split('->')[0].strip()
                entry['fields_gen'] = (fvar, state['repeaters'].get(fvar, 0))
            out.append(entry)
        elif m.group(6):
            name = m.group(6)
            if name in seen:
                continue
            src = own.get(name) or trait_methods().get(name)
            if src and ('add_control' in src or 'start_controls_section' in src or '$this->' in src):
                call = body[m.start():m.end()]
                inner = call[call.index('(') + 1:call.rindex(')')]
                params, defaults = PARAMS.get(name, ([], {}))
                local = dict(defaults)
                args = [a.strip() for a in re.split(r",(?=(?:[^']*'[^']*')*[^']*$)", inner)] if inner.strip() else []
                for i, a in enumerate(args):
                    value = concat(a, env, own)
                    if value is not None and i < len(params):
                        local[params[i]] = value
                    elif i < len(params) and re.fullmatch(r'\$\w+', a) and a != '$this' and a != '$' + params[i]:
                        # Un repetidor pasado como argumento: dentro del método se llama como su parámetro.
                        src = re.sub(r'\$' + params[i] + r'(?=->)', lambda _m, a=a: a, src)
                _walk(src, own, out, state, seen | {name}, local)


def detail(path, traits=None):
    """Controles de un widget. `path` es un archivo local (p. ej. un widget propio) o un `RefFile` de una referencia."""
    if isinstance(path, RefFile):
        keys = traits or PLUGINS[path.plugin.key][3]
    else:
        keys = traits or ()
    _CURRENT['traits'] = tuple(keys)
    PARAMS.clear()
    for key in reversed(_CURRENT['traits']):
        PARAMS.update(_traits_of(key)[1])
    src = strip_comments(path.read_text(encoding='utf-8', errors='ignore'))
    own = methods(src)
    body = own.get('register_controls') or own.get('_register_controls') or src
    out = []
    _walk(body, own, out, {'tab': 'content', 'section': '', 'repeaters': {}}, frozenset({'register_controls'}))
    return out
