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
- los títulos: sin atributos (el núcleo se los quita a quien no es administrador) y el color de su resalte (`<span>`)
  en el CSS propio del widget;
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
            # `frases`: cada aparición del nombre debe estar dentro de una de esas frases.
            if ex.get("frases") and n["re"].search(re.sub(ex["frases"], "", texto)):
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
                    movil = [float(c["settings"]["_inline_size_mobile"]) for c in hijos
                             if c.get("elType") == "column" and "_inline_size_mobile" in c["settings"]
                             and c["settings"].get("hide_mobile") != "hidden-mobile"]
                    if sum(movil) > 100.5:
                        error(f"{rel}: sección {i} con columnas que en celular suman {sum(movil)}")
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
                        # Sólo sigue en fila en celular la que da a cada hijo visible su ancho en celular (`tam_movil`,
                        # la barra de cierre), sin saltar de línea y sin pasar del 100 %.
                        visibles = [c.get("settings", {}) for c in hijos
                                    if c.get("settings", {}).get("hide_mobile") != "hidden-mobile"]
                        anchos = [(v.get("width_mobile") or {}).get("size") for v in visibles]
                        if (s.get("flex_direction_mobile") != "row" or s.get("flex_wrap_mobile") != "nowrap"
                                or not anchos or any(not isinstance(a, (int, float)) or a >= 100 for a in anchos)
                                or sum(anchos) > 100.5):
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
                if w == "heading":
                    # El núcleo le quita todos los atributos al título que guarda quien no es administrador
                    # (content-sanitizer y Widget_Heading::sanitize()): el color de un resalte va en el CSS propio.
                    for tag in re.findall(r"<[a-z][a-z0-9]*\s[^>]*>", s.get("title", ""), re.I):
                        error(f"{rel}: título con atributos {tag!r} ({i}): el núcleo se los quita a quien no es "
                              "administrador; el color de un resalte va en custom_css (selector span{color:…})")
                    if "<span>" in s.get("title", "") and not re.fullmatch(r"selector span\{color:#[0-9A-Fa-f]{6};\}",
                                                                          s.get("custom_css", "")):
                        error(f"{rel}: título con <span> sin su color en el CSS propio del widget ({i})")
                if w == "video" and s.get("youtube_url") != "[enlace del video]":
                    error(f"{rel}: video con un enlace que no es el placeholder: {s.get('youtube_url')!r}")
                if w == "countdown" and s.get("due_date") != CIERRE:
                    error(f"{rel}: contador con una fecha fija ({s.get('due_date')!r}): la plantilla lleva {CIERRE} "
                          "(ya pasada) y create-page pone la fecha real del cierre")
            anda(hijos, t, fila=(t == "container" and s.get("flex_direction") == "row"))

    anda(d["content"], None)
    revisar_texto(rel, "title", d.get("title", ""))
    return ids, cuenta, orden


# ---------------------------------------------------------------- reglas de la v4 del kit (2026-11-13)
# Un precio: una cifra o un placeholder detrás de `$`, o delante de `MXN`.
PRECIO = re.compile(r"\$\s?[\[\d]|[\d\]]\s?MXN\b")
# La portada de 01, 02 y 03: logo, la promesa grande (h1), el antetítulo chico debajo y un botón. Nada más.
PORTADA = ["image", "heading:h1", "heading:p", "button"]
# Los iconos de redes del kit (excepción acotada en marcas.json).
ICONOS_REDES = ["fab fa-facebook-f", "fab fa-instagram", "fab fa-tiktok", "fab fa-youtube", "fab fa-whatsapp"]


def widgets_de(e, out=None):
    out = [] if out is None else out
    if e.get("elType") == "widget":
        out.append(e)
    for h in e.get("elements", []):
        widgets_de(h, out)
    return out


def revisar_v4(t, d, variante):
    """La portada en una pantalla y sin video, el precio sólo en la oferta, los bonus sin precio y la barra fija con
    el logo del evento (v4 del kit), en las dos variantes."""
    rel = f"{t['file']} ({variante})"
    # Los iconos de las redes (decisión 2 del operador): sólo en la 04 y la 07, con su logo y sin enlazar a las redes.
    sociales = [w for e in d["content"] for w in widgets_de(e) if w.get("widgetType") == "social-icons"]
    if len(sociales) != (1 if t["id"] in ("gracias-registro", "gracias-compra") else 0):
        error(f"{rel}: iconos de redes donde no van ({len(sociales)}): sólo uno en la 04 y uno en la 07")
    for w in sociales:
        items = w["settings"].get("social_icon_list", [])
        if sorted(it.get("social_icon", {}).get("value") for it in items) != sorted(ICONOS_REDES):
            error(f"{rel}: iconos de redes distintos de los del kit: {[it.get('social_icon') for it in items]}")
        for it in items:
            v, u = it.get("social_icon", {}).get("value"), it.get("link", {}).get("url", "")
            if v != "fab fa-whatsapp" and it.get("link", {}).get("is_external"):
                error(f"{rel}: el icono {v} con el enlace de muestra abre otra pestaña (is_external): si se publica sin "
                      "cambiarlo, abre la misma página")
            if (u != WHATSAPP) if v == "fab fa-whatsapp" else not (u.startswith("#[") and PLACEHOLDER_URL.match(u)):
                error(f"{rel}: el icono {v} enlaza a {u!r}: las redes llevan un placeholder del sitio (se pone en el "
                      "editor) y WhatsApp el número del sitio, nunca la red")
    por_id = {e["id"]: e for e in d["content"]}
    anclas = {s.get("_element_id") for e in d["content"] for s in [e.get("settings", {})]}
    for b in t["blocks"]:
        secciones = [por_id[i] for i in b["sections"] if i in por_id]
        textos = [(c, v) for e in secciones for c, v in cadenas(e)]
        if b.get("price") and not b.get("price_values"):
            error(f"{rel}: el bloque {b['key']} lleva precio en la plantilla pero create-page no dejaría llenarlo")
        # El precio se revela tarde: sólo en la oferta (y en el cierre de la 01, que la repite).
        if not b.get("price"):
            for campo, valor in textos:
                m = PRECIO.search(valor)
                if m:
                    error(f"{rel}: precio fuera de la oferta en el bloque «{b['key']}» ({campo}): …{valor[max(0, m.start() - 40):m.end() + 20]}…")
        # Los bonus, sin precio ni valor: su valor va en el resumen de la oferta.
        if b["key"] == "bonus":
            for campo, valor in textos:
                if re.search(r"\b[Vv]alor\b|<del>", valor):
                    error(f"{rel}: bonus con precio o valor ({campo}): {valor[:80]}")
        # La portada de 01, 02 y 03.
        if b["key"] == "promesa" and t["id"] in ("venta-completa", "venta-corta", "captacion"):
            ws = [w for e in secciones for w in widgets_de(e)]
            forma = [w["widgetType"] + (":" + w["settings"].get("header_size", "h2") if w["widgetType"] == "heading"
                                        else "") for w in ws]
            if len(secciones) != 1 or forma != PORTADA:
                error(f"{rel}: la portada debe llevar logo, la promesa (h1), el antetítulo chico debajo y un botón, sin "
                      f"video: {forma}")
            botones = [w for w in ws if w["widgetType"] == "button"]
            url = botones[0]["settings"]["link"]["url"] if botones else ""
            if not url.startswith("#") or url[1:] not in anclas:
                error(f"{rel}: el botón de la portada debe llevar a un bloque de la página: {url!r}")
            pos = [x["key"] for x in t["blocks"]].index("promesa")
            if any(x["key"] != "barra-de-cierre" for x in t["blocks"][:pos]):
                error(f"{rel}: la portada no es lo primero de la página (sólo la barra de cierre va antes)")
        # La barra de cierre: fija arriba, con el logo del evento (oculto en celular), el contador y el botón.
        if b["key"] == "barra-de-cierre":
            s = secciones[0]["settings"] if len(secciones) == 1 else {}
            cols = secciones[0].get("elements", []) if len(secciones) == 1 else []
            ws = [w["widgetType"] for e in secciones for w in widgets_de(e)]
            if (s.get("sticky") != "top" or not {"desktop", "tablet", "mobile"} <= set(s.get("sticky_on") or [])
                    or not s.get("z_index")):
                error(f"{rel}: la barra de cierre no es fija arriba (sticky top en escritorio, tableta y celular)")
            # Las anclas a las que llevan sus botones (#oferta…) quedan debajo de la barra, no tapadas por ella: el
            # núcleo sólo da `scroll-margin-top` a las de un elemento fijo con `sticky_anchor_link_offset`.
            if not isinstance(s.get("sticky_anchor_link_offset"), int) or s["sticky_anchor_link_offset"] < 100:
                error(f"{rel}: la barra de cierre fija sin el margen de las anclas (sticky_anchor_link_offset ≥ 100): "
                      "tapa el título del bloque al que llevan los botones")
            # Mide lo mismo en las dos variantes: sin relleno arriba ni abajo en sus columnas (la separación de una
            # sección es relleno de la columna en los cuatro lados; la de un contenedor, no).
            sep = s.get("gap") == "no" if variante == "sections" else (s.get("flex_gap") or {}).get("size") == 0
            if not sep or any((c.get("settings", {}).get("padding") or {}).get("top") != "0"
                               or (c.get("settings", {}).get("padding") or {}).get("bottom") != "0" for c in cols):
                error(f"{rel}: la barra de cierre lleva separación o relleno arriba y abajo en sus columnas: no mide "
                      "lo mismo con secciones que con contenedores")
            if (not cols or cols[0].get("settings", {}).get("hide_mobile") != "hidden-mobile" or not ws
                    or ws[0] != "image"):
                error(f"{rel}: la barra de cierre no empieza con el logo del evento (oculto en celular)")
            if "countdown" not in ws or "button" not in ws or secciones[0]["id"] not in t["urgency"]:
                error(f"{rel}: la barra de cierre lleva el contador y el botón, y sólo va con un cierre real (urgencia)")


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
    # Bloques que omit_blocks no quita (decisión 5 del operador): los que llevan la promesa, la oferta, la garantía, las
    # preguntas frecuentes, WhatsApp o el pie; y, por la revisión (B1), el formulario. Las pruebas sociales nunca son
    # obligatorias.
    tipos = manifest.get("required_kinds", {})
    if set(tipos) != {"promesa", "oferta", "garantia", "preguntas-frecuentes", "whatsapp", "pie", "formulario"}:
        error(f"manifest.json: lo que no se quita es la promesa, la oferta, la garantía, las preguntas frecuentes, "
              f"WhatsApp, el pie y el formulario: {sorted(tipos)}")
    por_seccion = {e["id"]: e for e in ds["content"]}
    for b in t["blocks"]:
        lleva = b.get("required_for", [])
        if b["required"] != bool(lleva) or not set(lleva) <= set(tipos):
            error(f"{t['file']}: el bloque {b['key']} es obligatorio sin motivo, o con uno desconocido: {lleva}")
        cuerpo = json.dumps([por_seccion[i] for i in b["sections"] if i in por_seccion], ensure_ascii=False,
                            separators=(",", ":"))
        debe = set()
        if "Los resultados dependen del trabajo de cada persona" in cuerpo:
            debe.add("pie")
        if any(por_seccion[i]["settings"].get("_element_id") == "oferta" for i in b["sections"] if i in por_seccion):
            debe.add("oferta")
        if '"widgetType":"accordion"' in cuerpo and t["id"] != "prueba-social":
            debe.add("preguntas-frecuentes")
        if b["key"] in ("promesa", "oferta", "whatsapp", "pie", "garantia"):
            debe.add(b["key"])
        # Un formulario (la captación de la 03, los datos del carrito) no se quita: sin él la página no capta.
        if '"widgetType":"form"' in cuerpo:
            debe.add("formulario")
        # WhatsApp se reconoce por sus enlaces (`wa.me`), no por la clave del bloque: un bloque marcado «whatsapp»
        # debe llevar uno.
        if "whatsapp" in lleva and "https://wa.me/" not in cuerpo:
            error(f"{t['file']}: el bloque {b['key']} se declara de WhatsApp y no lleva ningún enlace a WhatsApp")
        if not debe <= set(lleva):
            error(f"{t['file']}: el bloque {b['key']} lleva {sorted(debe - set(lleva))} y omit_blocks lo dejaría quitar")
        social = ('"widgetType":"testimonial-carousel"' in cuerpo or '"widgetType":"blockquote"' in cuerpo
                  or "[Nombre real]" in cuerpo)
        if social and b["required"]:
            error(f"{t['file']}: el bloque {b['key']} lleva pruebas sociales, que siempre se pueden quitar")
    # Si la plantilla lleva un enlace a WhatsApp, al menos uno queda siempre: lo lleva un bloque obligatorio por WhatsApp
    # (revisión B2: la 04 se quedaba sin ninguno quitando «Mientras tanto»).
    con_wa = [b for b in t["blocks"] if "https://wa.me/" in json.dumps([por_seccion[i] for i in b["sections"]
                                                                          if i in por_seccion])]
    if con_wa and not any(b["required"] and "whatsapp" in b.get("required_for", []) for b in con_wa):
        error(f"{t['file']}: omit_blocks puede dejar la página sin ningún enlace a WhatsApp (los llevan "
              f"{[b['key'] for b in con_wa]} y ninguno es obligatorio por WhatsApp)")
    if t["id"] in ("venta-completa", "venta-corta"):
        cubiertos = {k for b in t["blocks"] for k in b.get("required_for", [])}
        faltan = set(tipos) - cubiertos - {"formulario"}
        if faltan:
            error(f"{t['file']}: a la página de venta le falta un bloque obligatorio: {sorted(faltan)}")
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
    for variante, (dv, *_resto) in datos.items():
        revisar_v4(t, dv, variante)
    print(f"{t['file']}: {t['sections']} secciones, {t['widgets']} widgets, {len(ids_s)} elementos (secciones) / "
          f"{len(ids_c)} (contenedores), urgencia {len(t['urgency'])}")

guia = os.path.join(modulo, "guide.md")
if os.path.isfile(guia):
    for n_linea, linea in enumerate(open(guia, encoding="utf-8").read().split("\n"), 1):
        revisar_texto(rel_mod + "guide.md", f"guide.línea {n_linea}", linea)
else:
    error("falta la guía guide.md del módulo")

for i, ex in enumerate(MARCAS["excepciones"]):
    if not ex["_usos"] and not ex.get("comprueba"):  # las de `comprueba` las aplica esa prueba
        error(f"marcas.json: la excepción {i + 1} ({', '.join(ex['nombres'])}) ya no se usa: quítala")

print(f"\nWidgets que Elements registra: {len(nombres)}")
print("Excepciones de marca usadas: " + ", ".join(f"{'/'.join(ex['nombres'])} ×{ex['_usos']}"
                                                  for ex in MARCAS["excepciones"]))
if errores:
    print("\nERRORES:")
    print("\n".join(errores))
    sys.exit(1)
print("Sin errores.")
