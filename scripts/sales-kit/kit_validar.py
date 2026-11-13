"""Valida las plantillas del kit sin WordPress: estructura, widgets que existen en Elements, identificadores,
anchos de columna, enlaces y textos prohibidos.
Uso: python3 -I kit_validar.py <carpeta del kit> <carpeta de digitalisimo-elements>"""
import json, os, re, sys

kit, elements = sys.argv[1], sys.argv[2]

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

PROHIBIDO = re.compile(r"conviertemas|convierte m[aá]s|vilma|lorem|ipsum|fistrum|triunfagram|ecmasmembresia|"
                       r"wpengine|john doe|jonh doe", re.I)
errores, avisos = [], []
for archivo in sorted(f for f in os.listdir(kit) if f.endswith(".json")):
    d = json.load(open(os.path.join(kit, archivo), encoding="utf-8"))
    ids, cuenta = set(), {}
    assert d.get("version") == "0.4" and d.get("type") == "page" and d.get("title"), archivo

    def anda(els, padre):
        for e in els:
            i = e.get("id", "")
            if not re.fullmatch(r"[0-9a-f]{7}", i):
                errores.append(f"{archivo}: id inválido {i!r}")
            if i in ids:
                errores.append(f"{archivo}: id repetido {i}")
            ids.add(i)
            t = e.get("elType")
            if t == "section":
                if padre not in (None, "column"):
                    errores.append(f"{archivo}: sección dentro de {padre}")
                if padre == "column" and not e.get("isInner"):
                    errores.append(f"{archivo}: sección interior sin isInner")
                cols = e.get("elements", [])
                if not cols or any(c.get("elType") != "column" for c in cols):
                    errores.append(f"{archivo}: sección {i} sin columnas válidas")
                suma = sum(float(c["settings"].get("_inline_size") or c["settings"]["_column_size"]) for c in cols)
                if suma > 100.5:
                    errores.append(f"{archivo}: sección {i} con columnas que suman {suma}")
            elif t == "column":
                if padre != "section":
                    errores.append(f"{archivo}: columna fuera de sección")
            elif t == "widget":
                w = e.get("widgetType")
                cuenta[w] = cuenta.get(w, 0) + 1
                if w not in nombres:
                    errores.append(f"{archivo}: el widget {w} no existe en Elements")
                if padre != "column":
                    errores.append(f"{archivo}: widget {w} fuera de columna")
            else:
                errores.append(f"{archivo}: elType desconocido {t}")
            anda(e.get("elements", []), t)

    anda(d["content"], None)
    crudo = json.dumps(d, ensure_ascii=False)
    for m in PROHIBIDO.finditer(crudo):
        errores.append(f"{archivo}: texto prohibido «{m.group(0)}»")
    for u in re.findall(r'"url":\s*"([^"]*)"', crudo):
        if u and not (u.startswith(("/", "#", "https://wa.me/52XXXXXXXXXX", "https://chat.whatsapp.com/GRUPO",
                                    "https://facebook.com/", "https://instagram.com/", "https://tiktok.com/",
                                    "https://youtube.com/"))):
            errores.append(f"{archivo}: enlace inesperado {u}")
    secciones = sum(1 for e in d["content"] if e["elType"] == "section")
    print(f"{archivo}: {secciones} secciones, {sum(cuenta.values())} widgets · " +
          ", ".join(f"{k} {v}" for k, v in sorted(cuenta.items())))

print(f"\nWidgets que Elements registra: {len(nombres)}")
if errores:
    print("\nERRORES:"); print("\n".join(errores)); sys.exit(1)
print("Sin errores.")
