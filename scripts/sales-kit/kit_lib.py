"""Piezas para armar plantillas de Elementor (secciones y columnas clásicas, formato de exportación 0.4).

Se usan secciones y columnas, no contenedores: el experimento de contenedores viene apagado en los sitios instalados
antes de Elementor 3.16, y una plantilla de contenedores no se vería ahí. Las secciones se ven en cualquier sitio.

Ninguna pieza fija la familia tipográfica: el kit hereda la tipografía global del sitio (en DIGITALÍSIMO, Geist).
Los colores son los del Manual de identidad v1.0; el verde es acento (botones, cifras, una frase del titular).
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

WHATSAPP = "https://wa.me/52XXXXXXXXXX"  # se cambia por el número real con Herramientas → Reemplazar URL

_rng = random.Random(20261112)
_usados = set()


def nid():
    while True:
        v = "%07x" % _rng.randrange(16 ** 7)
        if v not in _usados:
            _usados.add(v)
            return v


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
    """Imagen vacía: Elementor muestra su imagen de muestra hasta que se elige una."""
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
    """Video con imagen de portada de muestra, para que el hueco se vea hasta poner el video real."""
    # Sin `image_overlay`: Elementor pone su imagen de muestra como portada, y así el hueco se ve.
    return widget("video", {"youtube_url": "https://www.youtube.com/watch?v=VIDEO_ID", "show_image_overlay": "yes",
                            "show_play_icon": "yes", "play_icon_color": BLANCO, "_border_radius": caja(radio),
                            "_title": nota})


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


def contador(fecha="2026-12-31 23:59", oscuro=False, mensaje="La inscripción ya cerró. Escríbenos por WhatsApp para "
             "saber cuándo abre de nuevo.", ancho=60):
    """Cuenta regresiva a una fecha fija. Solo se usa si esa fecha de cierre es real."""
    s = {"countdown_type": "due_date", "due_date": fecha, "custom_labels": "yes", "label_days": "Días",
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


def iconos_sociales(oscuro=False):
    redes = [("fab fa-facebook-f", "https://facebook.com/"), ("fab fa-instagram", "https://instagram.com/"),
             ("fab fa-tiktok", "https://tiktok.com/"), ("fab fa-youtube", "https://youtube.com/"),
             ("fab fa-whatsapp", WHATSAPP)]
    lista_r = [{"social_icon": icono(i, "fa-brands"), "link": {"url": u, "is_external": "on", "nofollow": ""},
                "_id": nid()} for i, u in redes]
    return widget("social-icons", {"social_icon_list": lista_r, "align": "center", "icon_color": "custom",
                                   "icon_primary_color": AZUL if not oscuro else CARD_OSC,
                                   "icon_secondary_color": BLANCO, "icon_size": px(18), "icon_spacing": px(10)})


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


def guardar(ruta, data):
    with open(ruta, "w", encoding="utf-8") as fh:
        json.dump(data, fh, ensure_ascii=False, separators=(",", ":"))
