"""Extrae por análisis estático los controles Elementor de un widget de Element Pack.

Sigue las llamadas `$this->metodo()` dentro de `register_controls()`, incluidos los traits de la
referencia, y devuelve por control su pestaña, tipo y el texto PHP literal de sus argumentos.
No ejecuta código de la referencia.
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
EP = ROOT / 'digitalisimo-elements' / 'bdthemes-element-pack'
_TRAITS = None


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


def trait_methods():
    global _TRAITS
    if _TRAITS is None:
        _TRAITS = {}
        for f in sorted(list((EP / 'traits').rglob('*.php')) + list((EP / 'includes').rglob('*trait*.php'))):
            for k, v in methods(strip_comments(f.read_text(errors='ignore'))).items():
                _TRAITS.setdefault(k, v)
    return _TRAITS


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
                            'types': top_value(inner, 'types'), 'exclude': top_value(inner, 'exclude'), 'raw_default': top_value(inner, 'default')})
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
                _walk(src, own, out, state, seen | {name}, local)


def detail(path):
    src = strip_comments(Path(path).read_text(encoding='utf-8', errors='ignore'))
    own = methods(src)
    body = own.get('register_controls') or own.get('_register_controls') or src
    out = []
    _walk(body, own, out, {'tab': 'content', 'section': '', 'repeaters': {}}, frozenset({'register_controls'}))
    return out
