"""Piezas para armar las plantillas del kit de ventas de DIGITALÍSIMO Elements (formato de exportación 0.4).

Cada página se arma una sola vez con secciones y columnas clásicas y de ahí salen las dos variantes que viajan con
Elements (`digitalisimo-elements/modules/digitalisimo-sales/templates/`):
- `sections/`: secciones y columnas, para los sitios sin el experimento de contenedores (los instalados antes de 3.16);
- `containers/`: contenedores flexibles (`a_contenedores()`), para los sitios con contenedores activos, el normal en
  Elements 4.3. La herramienta MCP `digitalisimo-sales/create-page` elige sola.
Los identificadores se conservan entre variantes (una sección y su contenedor llevan el mismo), así que el manifiesto
nombra los elementos de urgencia una sola vez.

Ninguna pieza fija la familia tipográfica: el kit hereda la tipografía global del sitio (en DIGITALÍSIMO, Geist).
Los colores son los del Manual de identidad v1.0; el verde es acento (botones, cifras, una frase del titular).

Marcas (decisión del operador, 2026-11-13): ningún texto nombra ni enlaza a otras empresas salvo las excepciones
declaradas en `tests/playground/brand-audit/marcas.json`. Los nombres que quedan viven en las constantes de abajo, así
que fijar o quitar uno es cambiar una línea.
"""
import json
import random

AZUL = "#1592DA"
PROFUNDO = "#0A6FCF"
NOCHE = "#0F141A"
NIEBLA = "#F5F8FB"
JADE = "#00A86B"
NEON = "#00FF99"
TX = "#1F252C"
MUT = "#4E5863"
BD = "#E1E6EC"
BLANCO = "#FFFFFF"
CARD_OSC = "#161D25"
TX_OSC = "#E9EEF3"
MUT_OSC = "#9AA6B2"

# WhatsApp de muestra: la herramienta `create-page` lo cambia por el número que recibe (sólo dígitos); a mano, con
# «Reemplazar URL» del constructor. El llamado a la acción por WhatsApp es del manual («Hablar por WhatsApp»).
WHATSAPP = "https://wa.me/52XXXXXXXXXX"

# ---------------------------------------------------------------- marcas (una línea cada una)
# Excepción declarada del operador (2026-11-13): OXXO, SPEI y Mercado Pago quedan fijos en las preguntas frecuentes de
# pago y en la línea «Pago 100 % seguro» (del resumen de la oferta y del carrito): en México es como paga el comprador.
# Cualquier otro medio va como placeholder neutro.
MEDIOS_PAGO = "tarjeta, OXXO, SPEI, Mercado Pago o [otro medio de pago]"
MEDIOS_PAGO_FAQ = "Tarjeta de crédito o débito, OXXO, SPEI, Mercado Pago o [otro medio de pago]"
PASARELA = "Mercado Pago [u otra pasarela de pago]"
PAGO_EFECTIVO = ("OXXO", "SPEI")  # pago en efectivo y transferencia de las preguntas del carrito
# Aviso legal del pie: Meta y Google sólo aquí (lo piden sus políticas de anuncios a las páginas a las que llevan).
AVISO_PLATAFORMAS = ("Este sitio no forma parte de Meta Platforms, Inc. ni de Google, y no está respaldado por "
                     "ellos.")
# Lo demás, neutro: la plataforma de una sesión en vivo, el video y las redes del sitio.
PLATAFORMA_SESION = "[plataforma de la sesión]"
VIDEO_URL = "[enlace del video]"  # el video se pega en el editor; ningún enlace a otro servicio viaja en el kit

# ---------------------------------------------------------------- fecha de cierre
# Sólo con una fecha real (D-0024): `create-page` llena estos placeholders y los contadores con `close_date`; sin ella,
# quita los bloques marcados con `urgente()` (la barra de cierre de la 01 y el contador del carrito, como pide el
# LEEME del kit) y los demás quedan pendientes.
DIA_CIERRE = "[día de cierre]"
MES_CIERRE = "[mes de cierre]"
HORA_CIERRE = "[hora de cierre]"
FECHA_CIERRE = "[fecha y hora de cierre]"

_rng = random.Random(20261112)
_usados = set()


def reiniciar_ids(semilla=20261112):
    """Cada página empieza con la misma semilla: el generador escribe siempre lo mismo."""
    global _rng
    _rng = random.Random(semilla)
    _usados.clear()


def nid():
    while True:
        v = "%07x" % _rng.randrange(16 ** 7)
        if v not in _usados:
            _usados.add(v)
            return v


def urgente(elemento):
    """Marca un elemento que sólo tiene sentido con un cierre real (va al manifiesto, no a la plantilla)."""
    elemento["__urgente"] = True
    return elemento


def px(v):
    return {"unit": "px", "size": v, "sizes": []}


def caja(t, r=None, b=None, l=None, unidad="px"):
    r = t if r is None else r
    b = t if b is None else b
    l = r if l is None else l
    return {"unit": unidad, "top": str(t), "right": str(r), "bottom": str(b), "left": str(l),
            "isLinked": t == r == b == l}


def tipo(prefijo, tam, tam_movil=None, peso=None, alto=None):
    s = {f"{prefijo}_typography": "custom", f"{prefijo}_font_size": px(tam)}
    if tam_movil:
        s[f"{prefijo}_font_size_mobile"] = px(tam_movil)
    if peso:
        s[f"{prefijo}_font_weight"] = str(peso)
    if alto:
        s[f"{prefijo}_line_height"] = {"unit": "em", "size": alto, "sizes": []}
    return s


def icono(valor, lib="fa-solid"):
    return {"value": valor, "library": lib}


# ---------------------------------------------------------------- estructura

def widget(nombre, settings):
    return {"id": nid(), "elType": "widget", "settings": settings, "elements": [], "widgetType": nombre}


def columna(*hijos, tam=100, fondo=None, pad=None, radio=None, sombra=False, centro_v=False, borde=None,
            pad_movil=None, interior=False, ocultar_movil=False):
    s = {"_column_size": tam}
    if ocultar_movil:
        s["hide_mobile"] = "hidden-mobile"
    if tam not in (100, 50, 33, 25, 66, 20):
        s["_inline_size"] = tam
    if fondo:
        s.update({"background_background": "classic", "background_color": fondo})
    if pad is not None:
        s["padding"] = pad
    if pad_movil is not None:
        s["padding_mobile"] = pad_movil
    if radio:
        s["border_radius"] = caja(radio)
        s["margin"] = caja(8)  # separa las tarjetas: el fondo de la columna no llega al borde
        s["margin_mobile"] = caja(6, 0)
    if borde:
        s.update({"border_border": "solid", "border_width": caja(1), "border_color": borde})
    if sombra:
        s.update({"box_shadow_box_shadow_type": "yes",
                  "box_shadow_box_shadow": {"horizontal": 0, "vertical": 10, "blur": 30, "spread": 0,
                                            "color": "rgba(15, 20, 26, 0.10)"}})
    if centro_v:
        s["content_position"] = "center"
    return {"id": nid(), "elType": "column", "settings": s, "elements": list(hijos), "isInner": interior}


def seccion(*columnas, fondo=None, degradado=None, pad=(90, 20), pad_movil=(56, 16), ancho=1140, ident=None,
            interior=False, espacio="extended", nota=None):
    """Una sección. `columnas` son columnas; si se pasan widgets sueltos, van en una sola columna."""
    cols = [c if c.get("elType") == "column" else None for c in columnas]
    if any(c is None for c in cols):
        cols = [columna(*columnas, interior=interior)]
    for c in cols:
        c["isInner"] = interior
    s = {"gap": espacio, "content_width": px(ancho)}
    if not interior:
        s["padding"] = caja(pad[0], pad[1])
        s["padding_mobile"] = caja(pad_movil[0], pad_movil[1])
    if fondo:
        s.update({"background_background": "classic", "background_color": fondo})
    if degradado:
        a, b, ang = degradado
        s.update({"background_background": "gradient", "background_color": a, "background_color_b": b,
                  "background_gradient_angle": {"unit": "deg", "size": ang, "sizes": []}})
    if ident:
        s["_element_id"] = ident
    if nota:
        s["_title"] = nota  # nombre visible en el Navegador del editor
    if len(cols) > 1:
        s["structure"] = str(len(cols)) + "0"
    return {"id": nid(), "elType": "section", "settings": s, "elements": cols, "isInner": interior}


def interior(*columnas, espacio="extended", margen_sup=None):
    sec = seccion(*columnas, interior=True, espacio=espacio)
    if margen_sup is not None:
        sec["settings"]["margin"] = caja(margen_sup, 0, 0, 0)
    return sec


# ---------------------------------------------------------------- widgets

def titulo(texto, nivel="h2", color=TX, alinear="center", tam=None, tam_movil=None, peso=700, alto=1.2, margen=None):
    tamanos = {"h1": (48, 32), "h2": (38, 28), "h3": (24, 21), "h4": (19, 18), "h5": (16, 15), "h6": (14, 13)}
    t, tm = tamanos[nivel]
    s = {"title": texto, "header_size": nivel, "align": alinear, "title_color": color}
    s.update(tipo("typography", tam or t, tam_movil or tm, peso, alto))
    if margen is not None:
        s["_margin"] = caja(0, 0, margen, 0)
    return widget("heading", s)


def etiqueta(texto, color=AZUL, alinear="center"):
    """Antetítulo corto en mayúsculas."""
    s = {"title": texto, "header_size": "p", "align": alinear, "title_color": color}
    s.update(tipo("typography", 14, 13, 700, 1.4))
    s["typography_text_transform"] = "uppercase"
    s["typography_letter_spacing"] = px(1.5)
    return widget("heading", s)


def texto(html, color=MUT, alinear="left", tam=18, tam_movil=16):
    s = {"editor": html, "text_color": color, "align": alinear}
    s.update(tipo("typography", tam, tam_movil, None, 1.6))
    return widget("text-editor", s)


def boton(texto_boton, url="#oferta", fondo=JADE, color=BLANCO, alinear="center", tam="lg", icono_der=True,
          ancho_completo=False, fuente=19, externo=False):
    s = {"text": texto_boton, "link": {"url": url, "is_external": "on" if externo else "", "nofollow": "",
                                         "custom_attributes": ""},
         "align": "justify" if ancho_completo else alinear, "size": tam, "background_color": fondo,
         "button_text_color": color, "border_radius": caja(10), "text_padding": caja(18, 34),
         "button_box_shadow_box_shadow_type": "yes",
         "button_box_shadow_box_shadow": {"horizontal": 0, "vertical": 8, "blur": 22, "spread": 0,
                                          "color": "rgba(0, 168, 107, 0.30)"}}
    s.update(tipo("typography", fuente, 17, 700))
    if icono_der:
        s.update({"selected_icon": icono("fas fa-arrow-right"), "icon_align": "right", "icon_indent": px(10)})
    return widget("button", s)


def boton_secundario(texto_boton, url, alinear="center", oscuro=False):
    """Botón que no es de compra: contorno, sin el verde, para no confundirse con el principal."""
    s = {"text": texto_boton, "link": {"url": url, "is_external": "", "nofollow": "", "custom_attributes": ""},
         "align": alinear, "size": "md", "background_color": "rgba(0,0,0,0)",
         "button_text_color": TX_OSC if oscuro else AZUL, "border_border": "solid", "border_width": caja(2),
         "border_color": AZUL, "border_radius": caja(10), "text_padding": caja(14, 26)}
    s.update(tipo("typography", 16, 15, 600))
    return widget("button", s)


def boton_whatsapp(texto_boton="Hablar por WhatsApp", mensaje="Hola, tengo una pregunta sobre [producto]",
                   alinear="center", oscuro=False):
    url = WHATSAPP + "?text=" + mensaje.replace(" ", "%20")
    s = {"text": texto_boton, "link": {"url": url, "is_external": "on", "nofollow": "", "custom_attributes": ""},
         "align": alinear, "size": "md", "background_color": "rgba(0,0,0,0)",
         "button_text_color": TX_OSC if oscuro else TX, "border_border": "solid", "border_width": caja(2),
         "border_color": MUT_OSC if oscuro else BD, "border_radius": caja(10), "text_padding": caja(14, 26),
         "selected_icon": icono("fab fa-whatsapp", "fa-brands"), "icon_align": "left", "icon_indent": px(8)}
    s.update(tipo("typography", 16, 15, 600))
    return widget("button", s)


def imagen(pie=None, ancho=100, alinear="center", radio=12):
    """Imagen vacía: el constructor muestra su imagen de muestra hasta que se elige una."""
    s = {"image_size": "full", "align": alinear, "width": {"unit": "%", "size": ancho, "sizes": []},
         "image_border_radius": caja(radio)}
    if pie:
        s.update({"caption_source": "custom", "caption": pie, "caption_text_color": MUT,
                  "caption_typography_typography": "custom", "caption_typography_font_size": px(13)})
    return widget("image", s)


def avatar(tam=88, alinear="left"):
    """Foto redonda de una persona, de tamaño fijo: la imagen se recorta al cuadrado."""
    return widget("image", {"image_size": "full", "align": alinear, "width": px(tam), "height": px(tam),
                            "object-fit": "cover", "image_border_radius": caja(50, unidad="%")})


def video(nota="Video: cámbialo por el tuyo", radio=14):
    """Video con la portada de muestra, para que el hueco se vea hasta poner el video real.

    El enlace es un placeholder (`VIDEO_URL`), no un video de otro servicio: sin un enlace válido el reproductor no
    carga nada y la portada de muestra queda a la vista. Se pega en el editor; la herramienta no lo llena."""
    # Sin `image_overlay`: el constructor pone su imagen de muestra como portada, y así el hueco se ve.
    return widget("video", {"youtube_url": VIDEO_URL, "show_image_overlay": "yes", "show_play_icon": "yes",
                            "play_icon_color": BLANCO, "_border_radius": caja(radio), "_title": nota})


def lista(items, color_icono=JADE, color_texto=MUT, ic="fas fa-check-circle", tam=17, entre=10, lib="fa-solid"):
    filas = []
    for it in items:
        if isinstance(it, tuple):
            txt, i, l = (it + ("fa-solid",))[:3]
        else:
            txt, i, l = it, ic, lib
        filas.append({"text": txt, "selected_icon": icono(i, l), "_id": nid()})
    s = {"icon_list": filas, "space_between": px(entre), "icon_color": color_icono, "text_color": color_texto,
         "icon_size": px(18), "icon_self_align": "left"}
    s.update(tipo("icon_typography", tam, 16, None, 1.5))
    return widget("icon-list", s)


def caja_icono(ic, titulo_txt, desc, color=AZUL, alinear="left", posicion="top", oscuro=False, lib="fa-solid",
               color_desc=None, tam_icono=22):
    s = {"selected_icon": icono(ic, lib), "view": "stacked", "shape": "circle", "position": posicion,
         "title_text": titulo_txt, "description_text": desc, "title_size": "h3",
         "primary_color": color, "secondary_color": BLANCO, "icon_size": px(tam_icono),
         "icon_padding": px(max(14, tam_icono // 2)),
         "text_align": alinear, "title_color": TX_OSC if oscuro else TX,
         "description_color": color_desc or (MUT_OSC if oscuro else MUT), "title_bottom_space": px(8)}
    s.update(tipo("title_typography", 21, 19, 700, 1.3))
    s.update(tipo("description_typography", 16, 15, None, 1.6))
    return widget("icon-box", s)


def caja_imagen(titulo_txt, desc, alinear="center"):
    s = {"title_text": titulo_txt, "description_text": desc, "text_align": alinear, "title_size": "h3",
         "title_color": TX, "description_color": MUT, "image_size": {"unit": "%", "size": 60, "sizes": []},
         "image_border_radius": caja(14)}
    s.update(tipo("title_typography", 21, 19, 700))
    s.update(tipo("description_typography", 16, 15, None, 1.6))
    return widget("image-box", s)


def contador(oscuro=False, mensaje="La inscripción ya cerró. Escríbenos por WhatsApp para saber cuándo abre de nuevo.",
             ancho=60):
    """Cuenta regresiva a la fecha de cierre real (`FECHA_CIERRE`, que llena `create-page` con `close_date`)."""
    s = {"countdown_type": "due_date", "due_date": FECHA_CIERRE, "custom_labels": "yes", "label_days": "Días",
         "label_hours": "Horas", "label_minutes": "Minutos", "label_seconds": "Segundos",
         "box_background_color": CARD_OSC if oscuro else BLANCO, "box_border_border": "solid",
         "box_border_width": caja(1), "box_border_color": "#26313C" if oscuro else BD,
         "box_border_radius": caja(10), "box_spacing": px(10), "digits_color": NEON if oscuro else JADE,
         "label_color": MUT_OSC if oscuro else MUT, "container_width": {"unit": "%", "size": ancho, "sizes": []},
         "expire_actions": ["message"], "message_after_expire": mensaje}
    s.update(tipo("digits_typography", 34, 26, 700))
    s.update(tipo("label_typography", 12, 11, 600))
    return widget("countdown", s)


def acordeon(pares):
    tabs = [{"tab_title": q, "tab_content": "<p>" + a + "</p>", "_id": nid()} for q, a in pares]
    s = {"tabs": tabs, "border_color": BD, "title_background": BLANCO, "title_color": TX, "tab_active_color": AZUL,
         "content_background_color": BLANCO, "content_color": MUT, "selected_icon": icono("fas fa-plus"),
         "selected_active_icon": icono("fas fa-minus"), "icon_align": "right", "title_padding": caja(20, 24),
         "content_padding": caja(4, 24, 22, 24)}
    s.update(tipo("title_typography", 18, 16, 600))
    s.update(tipo("content_typography", 16, 15, None, 1.6))
    return widget("accordion", s)


def carrusel_testimonios(slides):
    sl = [{"content": c, "name": n, "title": t, "_id": nid()} for c, n, t in slides]
    s = {"slides": sl, "skin": "bubble", "layout": "image_inline", "alignment": "left", "slides_per_view": "3",
         "slides_per_view_tablet": "2", "slides_per_view_mobile": "1", "space_between": px(24),
         "show_arrows": "yes", "pagination": "bullets", "content_color": TX, "name_color": TX, "title_color": MUT,
         "background_color": BLANCO, "border": "yes", "border_color": BD}
    s.update(tipo("content_typography", 16, 15, None, 1.6))
    return widget("testimonial-carousel", s)


def cita(texto_cita, autor, oscuro=False):
    s = {"blockquote_content": texto_cita, "author_name": autor, "tweet_button": "", "blockquote_skin": "border",
         "content_text_color": TX_OSC if oscuro else TX, "author_text_color": MUT_OSC if oscuro else MUT,
         "border_color": JADE if not oscuro else NEON, "border_width": px(4)}
    s.update(tipo("content_typography", 18, 17, None, 1.5))
    s.update(tipo("author_typography", 15, 14, 600))
    return widget("blockquote", s)


def estrellas(alinear="left"):
    """Cinco estrellas como texto: el widget clásico de calificación deja un hueco vacío en Elements 4.3.5."""
    s = {"title": "★★★★★", "header_size": "p", "align": alinear, "title_color": "#F5B301"}
    s.update(tipo("typography", 20, 18, None, 1))
    s["typography_letter_spacing"] = px(3)
    return widget("heading", s)


def separador(ancho=60, color=BD, alinear="center"):
    return widget("divider", {"width": {"unit": "%", "size": ancho, "sizes": []}, "color": color, "align": alinear,
                              "weight": px(1), "gap": px(18)})


def espacio(alto=24):
    return widget("spacer", {"space": px(alto)})


def redes(oscuro=False):
    """Dónde poner las redes del sitio. Sin iconos ni enlaces de otras empresas: se agregan en el editor."""
    return texto("<p>[Tus redes: agrega aquí los enlaces a tus perfiles con el widget de iconos sociales, o borra este "
                 "texto]</p>", color=MUT_OSC if oscuro else MUT, tam=15, tam_movil=15)


def formulario(campos, boton_txt, redirigir, nombre="Registro", color_boton=JADE):
    """campos: (id, tipo, etiqueta, placeholder, requerido, opciones, ancho[, valor por defecto])."""
    ff = []
    for campo in campos:
        cid, ftipo, label, ph, req, opciones, ancho = campo[:7]
        f = {"custom_id": cid, "field_type": ftipo, "field_label": label, "placeholder": ph,
             "required": "true" if req else "", "width": str(ancho), "_id": nid()}
        if opciones:
            f["field_options"] = opciones
        if len(campo) > 7:
            f["field_value"] = campo[7]
        if ftipo == "acceptance":
            f["acceptance_text"] = label
            f["field_label"] = ""
        ff.append(f)
    s = {"form_name": nombre, "form_fields": ff, "button_text": boton_txt, "button_size": "lg",
         "button_width": "100", "button_align": "stretch", "show_labels": "yes", "mark_required": "",
         "submit_actions": ["save-to-database", "email", "redirect"], "redirect_to": redirigir,
         "success_message": "¡Listo! Te llevamos al siguiente paso.",
         "error_message": "Algo salió mal. Escríbenos por WhatsApp y te ayudamos.",
         "required_field_message": "Este dato es necesario.",
         "invalid_message": "Revisa este dato.",
         "field_text_color": TX, "field_background_color": BLANCO, "field_border_color": BD,
         "field_border_radius": caja(8), "label_color": TX, "column_gap": px(14), "row_gap": px(14),
         "button_background_color": color_boton, "button_text_color": BLANCO, "button_border_radius": caja(10),
         "button_text_padding": caja(16, 24)}
    s.update(tipo("button_typography", 18, 17, 700))
    s.update(tipo("label_typography", 15, 14, 600))
    s.update(tipo("field_typography", 15, 15))
    return widget("form", s)


def plantilla(titulo_plantilla, secciones, lienzo=True):
    ps = {"template": "elementor_canvas"} if lienzo else {}
    return {"content": secciones, "page_settings": ps, "version": "0.4", "title": titulo_plantilla, "type": "page"}


# ---------------------------------------------------------------- contenedores

# Separación entre columnas de una sección (relleno de cada columna por lado) → separación de un contenedor.
SEPARACION = {"no": 0, "narrow": 10, "default": 20, "extended": 30, "wide": 40, "wider": 60}
# Ajustes de una columna que son sólo de tamaño o de posición: sin ellos, una sección de una columna se vuelve un solo
# contenedor (sin el nivel de la columna).
_COLUMNA_NEUTRA = {"_column_size", "_inline_size", "content_position"}
# Ajustes de estilo que la columna pasa tal cual al contenedor.
_COLUMNA_ESTILO = ("background_background", "background_color", "padding", "padding_mobile", "border_radius",
                   "margin", "margin_mobile", "border_border", "border_width", "border_color",
                   "box_shadow_box_shadow_type", "box_shadow_box_shadow", "hide_mobile")


def _flex_gap(espacio):
    v = SEPARACION.get(espacio, 30)
    return {"unit": "px", "size": v, "column": str(v), "row": str(v), "isLinked": True}


def _ancho_columna(s):
    tam = s.get("_inline_size") or s["_column_size"]
    return round(100 / 3, 4) if tam == 33 else (round(200 / 3, 4) if tam == 66 else tam)


def _contenedor_columna(col):
    """Una columna → un contenedor hijo en columna, con su ancho en % (100 % en celular) y su estilo."""
    s = col["settings"]
    c = {"content_width": "full", "width": {"unit": "%", "size": _ancho_columna(s), "sizes": []},
         "width_mobile": {"unit": "%", "size": 100, "sizes": []}, "flex_direction": "column"}
    if s.get("content_position") == "center":
        c["flex_justify_content"] = "center"
    for k in _COLUMNA_ESTILO:
        if k in s:
            c[k] = s[k]
    if "padding" not in c:
        c["padding"] = caja(0)  # la separación ya la da el contenedor padre
    return {"id": col["id"], "elType": "container", "settings": c,
            "elements": [_convertir(e) for e in col["elements"]], "isInner": True}


def _convertir(el):
    if el["elType"] == "widget":
        return el
    if el["elType"] != "section":
        raise ValueError("elemento inesperado " + el["elType"])
    s = el["settings"]
    interior_ = bool(el.get("isInner"))
    cols = el["elements"]
    c = {}
    if interior_:
        c.update({"content_width": "full", "padding": caja(0)})
        if "margin" in s:
            c["margin"] = s["margin"]
    else:
        c.update({"content_width": "boxed", "boxed_width": s["content_width"]})
        for k in ("padding", "padding_mobile"):
            if k in s:
                c[k] = s[k]
    for k in ("background_background", "background_color", "background_color_b", "background_gradient_angle",
              "_element_id", "_title"):
        if k in s:
            c[k] = s[k]
    if len(cols) == 1 and set(cols[0]["settings"]) <= _COLUMNA_NEUTRA:
        # Una sola columna sin estilo: el contenedor es la columna.
        c["flex_direction"] = "column"
        hijos = [_convertir(e) for e in cols[0]["elements"]]
    elif len(cols) == 1 and interior_:
        # Una sola columna con estilo dentro de otra (la tarjeta del resumen): un contenedor con el estilo de la columna.
        col = _contenedor_columna(cols[0])
        col["id"] = el["id"]
        if "margin" in s:
            col["settings"]["margin"] = s["margin"]
        col["settings"]["width"] = {"unit": "%", "size": 100, "sizes": []}
        return col
    else:
        c.update({"flex_direction": "row", "flex_direction_mobile": "column", "flex_gap": _flex_gap(s.get("gap")),
                  "flex_align_items": "stretch"})
        if len(cols) == 1:
            c["flex_direction_mobile"] = "column"
        hijos = [_contenedor_columna(col) for col in cols]
    return {"id": el["id"], "elType": "container", "settings": c, "elements": hijos, "isInner": interior_}


def a_contenedores(datos):
    """La misma plantilla con contenedores: cada sección es un contenedor (en fila si tiene varias columnas, que en
    celular se apilan como las columnas de una sección) y cada columna un contenedor hijo con su ancho."""
    out = dict(datos)
    out["content"] = [_convertir(e) for e in datos["content"]]
    return out


# ---------------------------------------------------------------- salida

def urgentes(elementos):
    """IDs marcados con `urgente()`, en orden."""
    ids = []
    for e in elementos:
        if e.get("__urgente"):
            ids.append(e["id"])
        ids.extend(urgentes(e.get("elements", [])))
    return ids


def limpiar(elementos):
    """Quita las marcas internas antes de escribir la plantilla."""
    for e in elementos:
        e.pop("__urgente", None)
        limpiar(e.get("elements", []))
    return elementos


def volcar(data):
    return json.dumps(data, ensure_ascii=False, separators=(",", ":"))
