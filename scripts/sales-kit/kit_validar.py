"""Valida sin WordPress el kit de ventas que viaja con DIGITALÍSIMO Elements, en sus dos variantes.

Uso: python3 -I scripts/sales-kit/kit_validar.py [<carpeta templates>] [<carpeta digitalisimo-elements>]
(por defecto, las del repositorio). La CI lo corre desde tests/elements-sales-kit.mjs.

Revisa en cada plantilla de `sections/` y `containers/`:
- el formato (versión 0.4, tipo página, título, lienzo);
- los identificadores (siete hexadecimales, sin repetirse en la página; también los `_id` de las listas);
- la jerarquía: sección → columna → widget o sección interior (secciones), contenedor → contenedor o widget
  (contenedores), y que los anchos de una fila no pasen del 100 %;
- que cada widget exista en Elements (los `get_name()` de sus clases de widget);
- que las dos variantes lleven los mismos widgets, en el mismo orden, con los mismos ajustes e identificadores;
- los enlaces: sólo rutas y anclas del propio sitio, el WhatsApp de muestra y placeholders (ninguna URL de otro sitio);
- los textos prohibidos (relleno, rastros de las fuentes, el proveedor del núcleo) y los nombres de otras empresas,
  que sólo pueden aparecer donde los declara tests/playground/brand-audit/marcas.json (y cada excepción se usa);
- el manifiesto: plantillas, recuentos, urgencia (IDs que existen y contienen el contador), enlaces y casos;
- y, con la guía (`../guide.md`), los mismos nombres de otras empresas.
"""
import json
import os
import re
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.normpath(os.path.join(AQUI, "..", ".."))
args = sys.argv[1:]
plantillas = os.path.abspath(args[0]) if args else os.path.join(
    RAIZ, "digitalisimo-elements", "modules", "digitalisimo-sales", "templates")
elements = os.path.abspath(args[1]) if len(args) > 1 else os.path.join(RAIZ, "digitalisimo-elements")
modulo = os.path.dirname(plantillas)

errores = []


def error(texto):
    errores.append(texto)


# Widgets que registra Elements: los nombres que devuelve get_name() en sus clases de widget.
nombres = set()
for raiz, _, archivos in os.walk(elements):
    if "/widgets" not in raiz and not raiz.endswith("widgets"):
        continue
    for a in archivos:
        if a.endswith(".php"):
            t = open(os.path.join(raiz, a), encoding="utf-8", errors="ignore").read()
            for m in re.finditer(r"function get_name\(\)\s*(?::\s*string\s*)?\{\s*return\s*'([a-z0-9-]+)'", t):
                nombres.add(m.group(1))
if len(nombres) < 100:
    error(f"sólo se encontraron {len(nombres)} widgets en {elements}")

# Relleno, rastros de las fuentes y la persona de la versión del kit para los sitios propios (no entra en Elements).
PROHIBIDO = re.compile(r"lorem|ipsum|fistrum|triunfagram|ecmasmembresia|wpengine|john doe|jonh doe|VIDEO_ID|Daniel|"
                       r"Butanda|National Institute|Torreón|digitalisimo\.(mx|lat)", re.I)
VENDORS = json.load(open(os.path.join(RAIZ, "tests", "playground", "brand-audit", "vendors.json"), encoding="utf-8"))
HOSTS = re.compile(r"(?<![a-z0-9-])(?:[a-z0-9-]+\.)*(?:" + "|".join(re.escape(h) for h in VENDORS["hosts"] +
                                                                  VENDORS.get("future", [])) + r")(?![a-z0-9-])", re.I)
MARCAS = json.load(open(os.path.join(RAIZ, "tests", "playground", "brand-audit", "marcas.json"), encoding="utf-8"))
for n in MARCAS["nombres"]:
    n["re"] = re.compile(n["patron"])
for i, ex in enumerate(MARCAS["excepciones"]):
    for k in ("nombres", "archivos", "motivo", "decision"):
        if not ex.get(k):
            error(f"marcas.json: la excepción {i + 1} no tiene «{k}»")
    ex["_usos"] = 0
WHATSAPP = "https://wa.me/52XXXXXXXXXX"
PLACEHOLDER_URL = re.compile(r"^#?\[[^\[\]]+\]$")
CIERRE = "2000-01-01 00:00"  # FECHA_CIERRE_MUESTRA de kit_lib.py: ya pasada, nunca una futura inventada


def marca(rel, campo, texto):
    """Cada nombre de otra empresa en `texto` debe caber en una excepción declarada."""
    for n in MARCAS["nombres"]:
        if not n["re"].search(texto):
            continue
        ok = False
        for ex in MARCAS["excepciones"]:
            if n["nombre"] not in ex["nombres"] or not re.search(ex["archivos"], rel):
                continue
            if ex.get("campo") and not re.search(ex["campo"], campo):
                continue
            if ex.get("texto") and not re.search(ex["texto"], texto):
                continue
            ex["_usos"] += 1
            ok = True
            break
        if not ok:
            error(f"{rel}: «{n['nombre']}» sin excepción declarada en {campo}: {texto[:120]}")


def cadenas(v, ruta=""):
    """(ruta sin índices, cadena) de todos los valores de texto."""
    if isinstance(v, dict):
        for k, x in v.items():
            yield from cadenas(x, f"{ruta}.{k}" if ruta else k)
    elif isinstance(v, list):
        for x in v:
            yield from cadenas(x, ruta)
    elif isinstance(v, str):
        yield ruta, v


def revisar_texto(rel, campo, s):
    for m in PROHIBIDO.finditer(s):
        error(f"{rel}: texto prohibido «{m.group(0)}» en {campo}")
    for m in HOSTS.finditer(s):
        error(f"{rel}: dominio del proveedor «{m.group(0)}» en {campo}")
    if re.search(r"\bElementor\b", s):
        error(f"{rel}: el nombre del proveedor del núcleo en {campo}")
    marca(rel, campo, s)


def revisar_enlace(rel, campo, u):
    if not u:
        return
    if u.startswith(("/", "#")) and not u.startswith("//"):
        if u.startswith("#[") and not PLACEHOLDER_URL.match(u):
            error(f"{rel}: placeholder de enlace mal formado {u!r} en {campo}")
        return
    if u.startswith(WHATSAPP + "?text=") or u == WHATSAPP:
        return
    if PLACEHOLDER_URL.match(u):
        return
    error(f"{rel}: enlace inesperado {u!r} en {campo}")


def revisar(rel, d, variante):
    ids, rids, cuenta, orden = set(), set(), {}, []
    if not (d.get("version") == "0.4" and d.get("type") == "page" and d.get("title")):
        error(f"{rel}: formato inesperado (versión, tipo o título)")
    if d.get("page_settings") != {"template": "elementor_canvas"}:
        error(f"{rel}: la plantilla debe pedir el lienzo y nada más en sus ajustes de página")

    def repetidores(v):
        if isinstance(v, dict):
            if "_id" in v:
                if not re.fullmatch(r"[0-9a-f]{7}", str(v["_id"])):
                    error(f"{rel}: _id inválido {v['_id']!r}")
                if v["_id"] in rids:
                    error(f"{rel}: _id repetido {v['_id']}")
                rids.add(v["_id"])
            for x in v.values():
                repetidores(x)
        elif isinstance(v, list):
            for x in v:
                repetidores(x)

    def anda(els, padre, fila=False):
        if fila:
            suma = 0.0
            for c in els:
                w = c.get("settings", {}).get("width") if c.get("elType") == "container" else None
                if not (isinstance(w, dict) and w.get("unit") == "%"):
                    error(f"{rel}: hijo de una fila sin ancho en % ({c.get('id')})")
                    continue
                suma += float(w["size"])
            if suma > 100.5:
                error(f"{rel}: fila con anchos que suman {suma}")
        for e in els:
            i = e.get("id", "")
            if not re.fullmatch(r"[0-9a-f]{7}", i):
                error(f"{rel}: id inválido {i!r}")
            if i in ids:
                error(f"{rel}: id repetido {i}")
            ids.add(i)
            t = e.get("elType")
            s = e.get("settings", {})
            repetidores(s)
            campo_base = e.get("widgetType", t)
            for campo, valor in cadenas(s):
                campo_c = f"{campo_base}.{campo}"
                revisar_texto(rel, campo_c, valor)
                if campo.endswith("url") and campo != "youtube_url" or campo == "redirect_to":
                    revisar_enlace(rel, campo_c, valor)
                for h in re.findall(r'href="([^"]*)"', valor):
                    revisar_enlace(rel, campo_c + " (href)", h)
            hijos = e.get("elements", [])
            if variante == "sections":
                if t == "section":
                    if padre not in (None, "column"):
                        error(f"{rel}: sección dentro de {padre}")
                    if (padre == "column") != bool(e.get("isInner")):
                        error(f"{rel}: sección {i} con isInner equivocado")
                    if not hijos or any(c.get("elType") != "column" for c in hijos):
                        error(f"{rel}: sección {i} sin columnas válidas")
                    suma = sum(float(c["settings"].get("_inline_size") or c["settings"]["_column_size"])
                               for c in hijos if c.get("elType") == "column")
                    if suma > 100.5:
                        error(f"{rel}: sección {i} con columnas que suman {suma}")
                elif t == "column":
                    if padre != "section":
                        error(f"{rel}: columna fuera de sección")
                elif t != "widget":
                    error(f"{rel}: elType {t} en la variante de secciones")
            else:
                if t == "container":
                    if (padre is not None) != bool(e.get("isInner")):
                        error(f"{rel}: contenedor {i} con isInner equivocado")
                    if padre not in (None, "container"):
                        error(f"{rel}: contenedor dentro de {padre}")
                    if s.get("flex_direction") not in ("row", "column"):
                        error(f"{rel}: contenedor {i} sin dirección")
                    if s.get("flex_direction") == "row" and s.get("flex_direction_mobile") != "column":
                        error(f"{rel}: fila {i} que no se apila en celular")
                    if padre is None and s.get("content_width") != "boxed":
                        error(f"{rel}: contenedor de primer nivel {i} sin ancho de contenido")
                elif t != "widget":
                    error(f"{rel}: elType {t} en la variante de contenedores")
            if t == "widget":
                w = e.get("widgetType")
                cuenta[w] = cuenta.get(w, 0) + 1
                orden.append((i, w, json.dumps(s, sort_keys=True, ensure_ascii=False)))
                if w not in nombres:
                    error(f"{rel}: el widget {w} no existe en Elements")
                if padre not in ("column", "container"):
                    error(f"{rel}: widget {w} fuera de columna o contenedor")
                if hijos:
                    error(f"{rel}: widget {w} con hijos")
                if w == "video" and s.get("youtube_url") != "[enlace del video]":
                    error(f"{rel}: video con un enlace que no es el placeholder: {s.get('youtube_url')!r}")
                if w == "countdown" and s.get("due_date") != CIERRE:
                    error(f"{rel}: contador con una fecha fija ({s.get('due_date')!r}): la plantilla lleva {CIERRE} "
                          "(ya pasada) y create-page pone la fecha real del cierre")
            anda(hijos, t, fila=(t == "container" and s.get("flex_direction") == "row"))

    anda(d["content"], None)
    revisar_texto(rel, "title", d.get("title", ""))
    return ids, cuenta, orden


manifest_path = os.path.join(plantillas, "manifest.json")
manifest = json.load(open(manifest_path, encoding="utf-8"))
rel_mod = "digitalisimo-elements/modules/digitalisimo-sales/"
for campo, valor in cadenas(manifest):
    revisar_texto(rel_mod + "templates/manifest.json", "manifest." + campo, valor)
ids_validos = {t["id"] for t in manifest["templates"]}
for caso in manifest["cases"]:
    for p in caso["plantillas"]:
        if p not in ids_validos:
            error(f"manifest.json: el caso «{caso['caso']}» nombra la plantilla {p}, que no existe")
if len(manifest["templates"]) != 8:
    error(f"manifest.json: se esperaban 8 plantillas y hay {len(manifest['templates'])}")

for t in manifest["templates"]:
    datos = {}
    for variante in ("sections", "containers"):
        rel = f"{rel_mod}templates/{variante}/{t['file']}"
        ruta = os.path.join(plantillas, variante, t["file"])
        if not os.path.isfile(ruta):
            error(f"falta {rel}")
            continue
        d = json.load(open(ruta, encoding="utf-8"))
        datos[variante] = (d, *revisar(rel, d, variante))
    if len(datos) != 2:
        continue
    (ds, ids_s, cuenta_s, orden_s), (dc, ids_c, cuenta_c, orden_c) = datos["sections"], datos["containers"]
    if orden_s != orden_c:
        error(f"{t['file']}: las dos variantes no llevan los mismos widgets en el mismo orden con los mismos ajustes")
    if [e["id"] for e in ds["content"]] != [e["id"] for e in dc["content"]]:
        error(f"{t['file']}: las secciones y los contenedores de primer nivel no tienen los mismos IDs")
    if cuenta_s != t["widget_types"] or sum(cuenta_s.values()) != t["widgets"]:
        error(f"{t['file']}: el manifiesto no cuenta los widgets de la plantilla")
    if len(ds["content"]) != t["sections"] or ds["title"] != t["library_title"]:
        error(f"{t['file']}: secciones o título distintos de los del manifiesto")
    # Bloques: cada sección de primer nivel en uno solo, en el orden de la plantilla (se quitan, no se reordenan).
    claves = [b["key"] for b in t["blocks"]]
    if [i for b in t["blocks"] for i in b["sections"]] != [e["id"] for e in ds["content"]]:
        error(f"{t['file']}: los bloques del manifiesto no recorren las secciones en orden")
    if len(set(claves)) != len(claves) or any(not re.fullmatch(r"[a-z0-9-]+", c) for c in claves):
        error(f"{t['file']}: claves de bloque repetidas o mal formadas")
    if [b["key"] for b in t["blocks"] if b["required"]] != (["pie"] if "pie" in claves else []):
        error(f"{t['file']}: sólo el pie (avisos legales) es obligatorio")
    if t["has_countdown"] != ("countdown" in cuenta_s) or t["has_form"] != ("form" in cuenta_s):
        error(f"{t['file']}: has_countdown o has_form no coinciden con la plantilla")
    for u in t["urgency"]:
        if u not in ids_s or u not in ids_c:
            error(f"{t['file']}: la urgencia nombra {u}, que no está en las dos variantes")
    if t["has_countdown"]:
        # Sin fecha de cierre, create-page quita los bloques de urgencia: deben llevarse todos los contadores.
        def contadores(els, dentro=False, urg=frozenset(t["urgency"])):
            n = 0
            for e in els:
                d2 = dentro or e["id"] in urg
                if e.get("widgetType") == "countdown" and not d2:
                    n += 1
                n += contadores(e.get("elements", []), d2)
            return n
        if contadores(ds["content"]):
            error(f"{t['file']}: un contador queda fuera de los bloques de urgencia")
    print(f"{t['file']}: {t['sections']} secciones, {t['widgets']} widgets, {len(ids_s)} elementos (secciones) / "
          f"{len(ids_c)} (contenedores), urgencia {len(t['urgency'])}")

guia = os.path.join(modulo, "guide.md")
if os.path.isfile(guia):
    for n_linea, linea in enumerate(open(guia, encoding="utf-8").read().split("\n"), 1):
        revisar_texto(rel_mod + "guide.md", f"guide.línea {n_linea}", linea)
else:
    error("falta la guía guide.md del módulo")

for i, ex in enumerate(MARCAS["excepciones"]):
    if not ex["_usos"]:
        error(f"marcas.json: la excepción {i + 1} ({', '.join(ex['nombres'])}) ya no se usa: quítala")

print(f"\nWidgets que Elements registra: {len(nombres)}")
print("Excepciones de marca usadas: " + ", ".join(f"{'/'.join(ex['nombres'])} ×{ex['_usos']}"
                                                  for ex in MARCAS["excepciones"]))
if errores:
    print("\nERRORES:")
    print("\n".join(errores))
    sys.exit(1)
print("Sin errores.")
