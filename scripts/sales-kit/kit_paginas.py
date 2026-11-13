"""Kit de ventas DIGITALÍSIMO para Elements: las ocho plantillas de página, en sus dos variantes, y su manifiesto.

Uso: python3 -I scripts/sales-kit/kit_paginas.py [--check] [<carpeta templates>]
  Sin carpeta escribe en digitalisimo-elements/modules/digitalisimo-sales/templates/ (sections/, containers/ y
  manifest.json). Con --check no escribe: compara lo que generaría con lo que hay y sale con 1 si difiere (la CI lo
  corre en tests/elements-sales-kit.mjs, para que nadie edite a mano una plantilla generada).

Viene del kit de ventas v3 genérico (OneDrive, 2026-11-13: `kit_paginas.py --generico`, con PERSONA_GENERICA),
probado por la sesión del cerebro; el orden de la página y el método están en `oficio/ventas-en-linea.md` del cerebro
(commit 06ff42f, «El orden de una página de venta») y en `docs/ELEMENTS_SALES_KIT.md`.

Lo que va entre [corchetes] se reemplaza. Reglas que el kit trae escritas en sus textos guía:
- testimonios y cifras, solo reales y con permiso (D-0023);
- la urgencia, solo si el cierre es real: fecha y hora del centro de México (`urgente()` y los placeholders de cierre);
- se promete trabajo, no resultados (D-0024): el aviso va en el pie;
- precios en pesos, meses sin intereses y medios de pago de México.
"""
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from kit_lib import *  # noqa: E402,F401,F403
from kit_lib import a_contenedores, limpiar, reiniciar_ids, urgentes, urgente, volcar  # noqa: E402

OSC = dict(oscuro=True)

# =============================================================== Persona detrás de la marca
# El kit que viaja con Elements es el genérico del kit v3 (`--generico`): la persona detrás de la marca queda como
# espacio para llenar con los datos reales de cada sitio. La versión con la persona de DIGITALÍSIMO es sólo para los
# sitios propios y vive en el kit de OneDrive; no entra en Elements (tests/elements-sales-kit.mjs lo comprueba).
PERSONA_GENERICA = {
    "marca": "[Marca]",
    "nombre": "[nombre]",
    "cargo": "[quién es, en una línea]",
    "saludo": "Hola, soy [nombre]",
    "historia": "<p>[Tu historia en tres o cuatro frases, con la fórmula «[esto] me llevó de [un punto de partida "
                "pequeño] a [un resultado con cifra real]». Sin adornos: la gente compra a personas.]</p>",
    "autoridad": "Soy [nombre], [cargo] de [marca]",
    "credenciales": ["[Credencial verificable 1: años, proyectos, clientes]",
                     "[Credencial verificable 2: un logro con cifra real]",
                     "[Credencial verificable 3: dónde te han visto o publicado]"],
    "foto": "Foto de la persona detrás de la marca",
    "firma": "[Tu nombre, tu cargo y una frase personal]",
    "atiende": "[nombre real de quien atiende]",
}
P = dict(PERSONA_GENERICA)


def barra_cierre():
    return urgente(seccion(
        columna(titulo(f"La inscripción cierra el {DIA_CIERRE} de {MES_CIERRE} a las {HORA_CIERRE} (hora del centro de "
                       "México)", "h5",
                       color=TX_OSC, alinear="left", tam=15, tam_movil=14, peso=600), tam=50, centro_v=True),
        columna(contador(oscuro=True, ancho=100), tam=33, centro_v=True, ocultar_movil=True),
        columna(boton("Inscribirme", fondo=NEON, color=NOCHE, tam="sm", fuente=15, alinear="right"), tam=17,
                centro_v=True),
        fondo=NOCHE, pad=(10, 20), pad_movil=(10, 12), nota="Barra de cierre (solo si el cierre es real)"))


def pie(oscuro=True):
    return seccion(
        imagen(ancho=14, radio=0),
        texto(f"<p style=\"text-align:center\">{P['marca']} · ¿Dudas? Escríbenos por WhatsApp o a [correo de soporte]</p>",
              color=MUT_OSC, alinear="center", tam=15),
        texto("<p style=\"text-align:center\"><a href=\"/aviso-de-privacidad/\">Aviso de privacidad</a> · "
              "<a href=\"/terminos-y-condiciones/\">Términos y condiciones</a> · "
              "<a href=\"/politica-de-reembolso/\">Política de reembolso</a></p>",
              color=MUT_OSC, alinear="center", tam=14),
        texto("<p style=\"text-align:center\"><small>Los resultados dependen del trabajo de cada persona y de su "
              "mercado. No prometemos ingresos ni resultados específicos; los casos que mostramos son reales y "
              "excepcionales, no típicos. " + AVISO_PLATAFORMAS + "</small></p>", color=MUT_OSC, alinear="center",
              tam=13),
        texto(f"<p style=\"text-align:center\">© [año] {P['marca']}. Todos los derechos reservados.</p>",
              color=MUT_OSC, alinear="center", tam=13),
        fondo=NOCHE, pad=(48, 20), nota="Pie: contacto, avisos legales y aviso de resultados")


def tarjeta_stack(precio="$[precio] MXN", valor_total="$[valor total] MXN", meses="o [N] meses sin intereses de "
                  "$[mensualidad] MXN"):
    """El resumen de todo lo que se lleva: el corazón de la oferta."""
    return columna(
        imagen(ancho=80),
        titulo("[Nombre del producto]", "h3", color=TX, tam=26),
        lista([
            "[Producto principal]: [qué es, en una línea] <strong>(valor $[X] MXN)</strong>",
            "Acceso inmediato y [duración del acceso]",
            "[N] lecciones en video, con plantillas descargables",
            "[Sesiones en vivo / comunidad / soporte por WhatsApp] <strong>(valor $[X] MXN)</strong>",
            ("BONUS 1: [nombre] <strong>(valor $[X] MXN)</strong>", "fas fa-gift"),
            ("BONUS 2: [nombre] <strong>(valor $[X] MXN)</strong>", "fas fa-gift"),
            ("BONUS 3: [nombre] <strong>(valor $[X] MXN)</strong>", "fas fa-gift"),
        ], color_texto=TX),
        separador(100),
        texto(f"<p style=\"text-align:center\">Valor total: <del>{valor_total}</del></p>", color=MUT,
              alinear="center", tam=18),
        titulo(f"Hoy: <span style=\"color:{JADE}\">{precio}</span>", "h2", color=TX, tam=44, tam_movil=34),
        texto(f"<p style=\"text-align:center\">{meses}</p>", color=MUT, alinear="center", tam=16),
        boton("¡Sí, quiero [producto] ahora!", url="/carrito/", ancho_completo=True, fuente=21),
        texto("<p style=\"text-align:center\">Acceso a [producto] + todos los bonus</p>", color=MUT,
              alinear="center", tam=14),
        texto(f"<p style=\"text-align:center\">⏰ Este precio y los bonus terminan el {DIA_CIERRE} a las {HORA_CIERRE} "
              "(centro de México). Solo si es real.</p>", color=TX, alinear="center", tam=14),
        texto(f"<p style=\"text-align:center\">🔒 Pago 100 % seguro · {MEDIOS_PAGO[:1].upper() + MEDIOS_PAGO[1:]}</p>",
              color=MUT, alinear="center", tam=14),
        imagen(ancho=45, radio=0, pie="Logos de los medios de pago que de verdad aceptas"),
        fondo=BLANCO, pad=caja(36, 32), pad_movil=caja(24, 18), radio=18, sombra=True)


# =============================================================== 01 · Página de venta completa
# El orden es el de la página de lanzamiento de la Academia de Consultores (junio de 2020), la referencia que eligió
# el operador, con sus huecos cubiertos: botón en la promesa, garantía, valor total tachado y urgencia con motivo.

def venta_completa():
    S = []
    S.append(barra_cierre())

    # 1. La promesa.
    S.append(seccion(
        etiqueta("Si eres [perfil 1], [perfil 2] o [perfil 3]…", color=NEON),
        titulo(f"[Logra el resultado] y [construye X] <span style=\"color:{NEON}\">sin [el obstáculo]</span> en "
               "[N días] o menos", "h1", color=BLANCO, tam=50, tam_movil=32),
        espacio(10), video("Video de venta: 3 a 5 minutos"), espacio(10),
        texto("<p style=\"text-align:center\">➜ Descubre [el método con nombre], que funciona sin importar [tu giro "
              "o tu nivel de experiencia].</p>", color=TX_OSC, alinear="center", tam=19),
        boton("¡Quiero empezar!", fondo=NEON, color=NOCHE, fuente=20),
        degradado=(NOCHE, "#0B2A45", 160), pad=(80, 20), ancho=900, nota="1 · La promesa"))

    # 2. El problema.
    S.append(seccion(
        columna(
            etiqueta("¿Sabías que…?"),
            titulo("[Un dato real que duele, con su fuente]"),
            texto("<p style=\"text-align:center\">[Y lo peor: lo que pasa antes de que pase. En las palabras del "
                  "cliente.]</p>", alinear="center"),
            interior(
                columna(titulo("Lo que se siente hoy", "h3", color=TX),
                        lista(["«[Frase de dolor 1, como la diría el cliente]»", "«[Frase de dolor 2]»",
                               "«[Frase de dolor 3]»"], color_icono="#D64545", ic="fas fa-times-circle"),
                        tam=50, fondo=NIEBLA, pad=caja(28), radio=14, borde=BD),
                columna(titulo("La buena noticia", "h3", color=TX),
                        texto("<p><strong>[El giro: ya haces [algo] y no lo sabías / ya tienes lo que hace falta para "
                              "empezar.]</strong></p>", color=TX),
                        lista(["[Lo que sí tiene a su favor 1]", "[Lo que sí tiene a su favor 2]"]),
                        tam=50, fondo=BLANCO, pad=caja(28), radio=14, borde=JADE, sombra=True),
                margen_sup=24)),
        fondo=BLANCO, ancho=1000, nota="2 · El problema y el giro"))

    # 3. Quién está detrás, versión corta: su historia y la oportunidad.
    S.append(seccion(
        columna(imagen(pie=P["foto"]), tam=40, centro_v=True),
        columna(
            etiqueta("Quién está detrás", alinear="left"),
            titulo(P["saludo"], alinear="left"),
            texto(P["historia"]),
            texto("<p><strong>La oportunidad:</strong> [lo que antes solo podían hacer las grandes empresas o los "
                  "expertos, y hoy está al alcance de [perfil]].</p>", color=TX),
            tam=60, centro_v=True),
        fondo=NIEBLA, nota="3 · Quién está detrás (corto) y la oportunidad"))

    # 4. ¿Te imaginas…?
    S.append(seccion(
        etiqueta("Ahora imagina"),
        titulo("¿Te imaginas conseguir esto en [N días] o menos?"),
        interior(*[columna(caja_icono("fas fa-check", f"[Resultado deseado {n}]",
                                      "[Cómo se ve en su día a día, con sus palabras]", alinear="center",
                                      color=JADE), tam=25, fondo=NIEBLA, pad=caja(26), radio=16) for n in (1, 2, 3, 4)],
                 margen_sup=10),
        texto("<p style=\"text-align:center\">[Cuántas personas ya lo lograron: una cifra real.]</p>",
              alinear="center"),
        fondo=BLANCO, nota="4 · ¿Te imaginas…?"))

    # 5. Por qué es distinto.
    filas = [("[9 o 10 meses]", "[90 días]"), ("Casos de libro", "Casos reales, de clientes"),
             ("Acceso por tiempo limitado", "[Acceso de por vida / N meses]"),
             ("Solo teoría", "Plantillas y un plan de acción semana a semana"),
             ("Estudias solo", "[Comunidad, sesiones y soporte]")]
    S.append(seccion(
        etiqueta("Por qué es diferente"),
        titulo("¿Por qué [producto] y no otro curso, una maestría o una agencia?"),
        interior(
            columna(titulo("Otras opciones", "h3", color=MUT),
                    lista([a for a, _ in filas], color_icono="#D64545", ic="fas fa-times-circle"),
                    tam=50, fondo=BLANCO, pad=caja(28), radio=16, borde=BD),
            columna(titulo("[Nombre del producto]", "h3", color=TX),
                    lista([b for _, b in filas]),
                    tam=50, fondo=BLANCO, pad=caja(28), radio=16, borde=JADE, sombra=True),
            margen_sup=10),
        fondo=NIEBLA, ancho=1000, nota="5 · Por qué es distinto"))

    # 6. Quién está detrás, la autoridad completa.
    S.append(seccion(
        columna(imagen(pie=P["foto"]), tam=40, centro_v=True),
        columna(
            etiqueta("Con quién vas a aprender", alinear="left"),
            titulo(P["autoridad"], alinear="left"),
            lista(P["credenciales"], ic="fas fa-award"),
            texto("<p><strong>[Tu ventaja: lo que haces distinto, en una frase.]</strong></p>"
                  "<p>[La objeción de fondo, desactivada: «no importa el tamaño de tu negocio / de tu audiencia…»] "
                  "[Y el plan: qué vas a lograr en [N días].]</p>"),
            tam=60, centro_v=True),
        fondo=BLANCO, nota="6 · Quién está detrás (autoridad completa)"))
    S.append(seccion(
        etiqueta("Han confiado en nosotros"),
        interior(*[columna(imagen(ancho=70, radio=0, pie="Logo de un cliente real"), tam=20) for _ in range(5)]),
        fondo=BLANCO, pad=(10, 20), pad_movil=(10, 16), nota="6b · Logos de clientes reales"))

    # 7. Funciona para gente como tú.
    S.append(seccion(
        etiqueta("Resultados reales"),
        titulo("Esto también puede funcionar para ti"),
        interior(*[columna(
            video("Video-testimonio real"),
            titulo("De [antes] a [después] en [tiempo]", "h4", alinear="left", margen=4),
            texto("<p>[Nombre real] · [qué hace]</p>", tam=15), tam=33) for _ in range(3)]),
        espacio(10),
        boton_secundario("Ver más historias", url="#historias"),
        fondo=NIEBLA, nota="7 · Funciona para gente como tú"))

    # 8. Para quién es.
    S.append(seccion(
        etiqueta("¿Es para ti?"),
        titulo("Este programa es para ti si…"),
        interior(
            columna(lista(["[Situación que encaja 1]", "[Situación que encaja 2]", "[Situación que encaja 3]",
                           "[Situación que encaja 4]", "[Estás dispuesto a dedicarle [N] horas por semana]"]),
                    tam=60, fondo=NIEBLA, pad=caja(28), radio=16),
            columna(titulo("No es para ti si…", "h3", alinear="left"),
                    lista(["Buscas resultados sin trabajar", "Esperas una fórmula mágica"],
                          color_icono="#D64545", ic="fas fa-times-circle"),
                    tam=40, fondo=NIEBLA, pad=caja(28), radio=16),
            margen_sup=10),
        interior(*[columna(caja_icono("fas fa-user", f"[Perfil {n}]", "", alinear="center", color=AZUL), tam=25)
                   for n in (1, 2, 3, 4)], margen_sup=16),
        interior(*[columna(caja_icono("fas fa-user", f"[Perfil {n}]", "", alinear="center", color=AZUL), tam=25)
                   for n in (5, 6, 7, 8)]),
        texto("<p style=\"text-align:center\"><strong>¿No te ves en la lista?</strong> Escríbenos y te decimos con "
              "honestidad si te sirve o no.</p>", alinear="center", color=TX),
        boton_whatsapp("Pregúntanos por WhatsApp", "Hola, quiero saber si [producto] es para mí"),
        fondo=BLANCO, nota="8 · Para quién es, perfiles y WhatsApp"))

    # 9. Qué es.
    S.append(seccion(
        columna(imagen(pie="Maqueta de la plataforma en computadora, laptop y celular"), tam=45, centro_v=True),
        columna(
            etiqueta("Qué es", color=NEON, alinear="left"),
            titulo("[Nombre del producto] es [definición en una frase]", color=BLANCO, alinear="left"),
            texto("<p>Con [el método con nombre] vas a lograr:</p>", color=TX_OSC),
            lista(["[Resultado 1]", "[Resultado 2]", "[Resultado 3]", "[Resultado 4]",
                   "[Lo que logras desde la primera semana]"], color_icono=NEON, color_texto=TX_OSC),
            tam=55, centro_v=True),
        fondo=NOCHE, nota="9 · Qué es"))

    # 10. Temario, con el botón de compra.
    modulos = []
    for fila in range(3):
        modulos.append(interior(*[columna(
            etiqueta(f"Módulo {fila * 2 + k}", alinear="left"),
            titulo(f"[Nombre del módulo {fila * 2 + k}]", "h3", alinear="left"),
            lista(["<strong>[Crear / Encontrar / Lograr…]</strong>: [lo que se logra, en una línea]",
                   "<strong>[Verbo de acción]</strong>: [en una línea]",
                   "<strong>[Lo que te llevas]</strong>: [plantilla, guía o ejercicio]"], tam=16),
            tam=50, fondo=BLANCO, pad=caja(30), radio=16, borde=BD) for k in (1, 2)],
            margen_sup=0 if fila == 0 else 24))
    S.append(seccion(
        etiqueta("Temario"),
        titulo("Esto es lo que vas a aprender, módulo por módulo"),
        *modulos,
        espacio(16),
        boton("¡Quiero [producto] ahora!", url="#oferta", fuente=18),
        fondo=NIEBLA, nota="10 · Temario, con botón de compra"))

    # 11. Todo lo que incluye.
    rasgos = [("fas fa-play-circle", "[N] lecciones"), ("fas fa-file-alt", "[N] plantillas"),
              ("fas fa-calendar-check", "Plan semana a semana"), ("fas fa-infinity", "[Acceso de por vida]"),
              ("fas fa-users", "Comunidad privada"), ("fas fa-video", "Sesiones en vivo"),
              ("fab fa-whatsapp", "Soporte"), ("fas fa-sync-alt", "Actualizaciones")]
    S.append(seccion(
        etiqueta("El formato"),
        titulo("Todo lo que incluye"),
        interior(*[columna(caja_icono(i, t, "", alinear="center", lib="fa-brands" if i.startswith("fab") else
                                      "fa-solid"), tam=25) for i, t in rasgos[:4]]),
        interior(*[columna(caja_icono(i, t, "", alinear="center", lib="fa-brands" if i.startswith("fab") else
                                      "fa-solid"), tam=25) for i, t in rasgos[4:]]),
        fondo=BLANCO, nota="11 · Todo lo que incluye"))

    # 12. Nunca estarás solo.
    S.append(seccion(
        etiqueta("No vas solo"),
        titulo("Un programa donde nunca estarás solo"),
        interior(
            columna(caja_icono("fas fa-users", "[Comunidad]", "[Dónde y para qué: preguntas, avances, red]",
                               alinear="center", color=JADE), tam=33, fondo=BLANCO, pad=caja(28), radio=16),
            columna(caja_icono("fas fa-video", "[Sesiones o eventos]", "[Cuándo y cómo: frecuencia y duración]",
                               alinear="center"), tam=33, fondo=BLANCO, pad=caja(28), radio=16),
            columna(caja_icono("fab fa-whatsapp", "[Soporte y tutores]", "[Por dónde y en cuánto tiempo responden]",
                               alinear="center", lib="fa-brands"), tam=33, fondo=BLANCO, pad=caja(28), radio=16),
            margen_sup=10),
        fondo=NIEBLA, nota="12 · Nunca estarás solo"))

    # 13. Lo que te llevas al terminar.
    S.append(seccion(
        columna(imagen(pie="Certificado o sello con «Tu nombre aquí»"), tam=45, centro_v=True),
        columna(etiqueta("Al terminar", alinear="left"),
                titulo("[Tu certificado / tu sello / tu plan terminado]", alinear="left"),
                lista(["[Qué demuestra y ante quién]", "[Qué hay que cumplir para obtenerlo]",
                       "[Dónde lo puede mostrar]"]),
                texto("<p><em>Si el producto no da algo así, borra este bloque.</em></p>", tam=14),
                tam=55, centro_v=True),
        fondo=BLANCO, nota="13 · Lo que te llevas al terminar (opcional)"))

    # 14. Bonus.
    bonus = [seccion(
        etiqueta("Además, si entras hoy"),
        titulo("Te llevas estos bonus"),
        fondo=NIEBLA, pad=(80, 20, ), nota="14 · Bonus: título")]
    for n in (1, 2, 3, 4):
        foto = columna(imagen(pie=f"Maqueta del bonus {n}"), tam=40, centro_v=True)
        cuerpo = columna(
            etiqueta(f"Bonus #{n}", alinear="left", color=JADE),
            titulo(f"[Nombre del bonus {n}: «[cantidad] [recurso] para [tu X]»]", "h3", alinear="left", tam=26),
            texto("<p>[Qué es y qué duda resuelve: «¿y si no tengo tiempo?», «¿y si no sé de tecnología?»…]</p>"),
            texto(f"<p>Valor: <del>$[X] MXN</del> · <strong style=\"color:{JADE}\">Incluido</strong></p>", tam=17,
                  color=TX),
            tam=60, centro_v=True)
        bonus.append(seccion(*((foto, cuerpo) if n % 2 else (cuerpo, foto)), fondo=NIEBLA, pad=(30, 20),
                             nota=f"14.{n} · Bonus {n}"))
    bonus.append(seccion(
        texto(f"<p style=\"text-align:center\">Valor total de los bonus: <del>$[suma real de los bonus] MXN</del> · "
              f"<strong style=\"color:{JADE}\">hoy, incluidos</strong></p>", alinear="center", tam=20, color=TX),
        fondo=NIEBLA, pad=(10, 20), pad_movil=(10, 16), nota="14.5 · Valor total de los bonus"))
    S.extend(bonus)

    # 15. Nuestro compromiso.
    S.append(seccion(
        etiqueta("Nuestro compromiso"),
        titulo("Esto es lo que vas a tener"),
        interior(
            columna(lista(["Tendrás un [sistema] para [resultado 1]", "Tendrás un [plan] para [resultado 2]",
                           "Tendrás [herramienta] para [resultado 3]"]), tam=50),
            columna(lista(["Tendrás acompañamiento para [resultado 4]", "Tendrás [comunidad] para [resultado 5]",
                           "Tendrás [actualizaciones] para [resultado 6]"]), tam=50)),
        fondo=BLANCO, ancho=1000, nota="15 · Nuestro compromiso"))

    # 16. Cuánto vale para ti.
    S.append(seccion(
        etiqueta("Piénsalo así", color=NEON),
        titulo("¿Qué valor tiene para ti [el resultado]?", color=BLANCO),
        lista(["¿Cómo sería tu [negocio o vida] si ya no tuvieras que [el dolor]?",
               "¿Qué harías con [el tiempo o el dinero] que hoy pierdes en [X]?",
               "Imagina [el resultado] dentro de [N meses]."], color_icono=NEON, color_texto=TX_OSC,
              ic="fas fa-question-circle"),
        interior(*[columna(caja_icono(i, t, d, alinear="center", **OSC), tam=33, fondo=CARD_OSC, pad=caja(28),
                           radio=16)
                   for i, t, d in (("fas fa-user", "Para ti", "[Qué cambia en tu día a día]"),
                                   ("fas fa-handshake", "Para tus clientes", "[Qué reciben ellos]"),
                                   ("fas fa-seedling", "Para tu futuro", "[Qué construyes a largo plazo]"))],
                 margen_sup=16),
        texto("<p style=\"text-align:center\">[El cálculo honesto: si con lo que aprendes consigues [un cliente de "
              "$X al mes], en un año son $[Y]. Es un ejemplo, no una promesa: el resultado depende de cada "
              "persona.]</p>", color="#DCEBFA", alinear="center", tam=17),
        fondo=PROFUNDO, nota="16 · Cuánto vale para ti"))

    # 17. La oferta.
    S.append(seccion(
        columna(
            etiqueta("Este es el precio y lo que obtienes", color=NEON),
            titulo("Hoy tienes la oportunidad de entrar a [producto]", color=BLANCO),
            interior(tarjeta_stack(), espacio="no"),
            espacio(20),
            boton_whatsapp("¿Dudas? Pregúntanos por WhatsApp", oscuro=True)),
        degradado=(NOCHE, "#0B2A45", 180), ancho=760, ident="oferta", nota="17 · La oferta (ancla #oferta)"))

    # 18. Garantía y actualización.
    S.append(seccion(
        columna(caja_icono("fas fa-shield-alt", "", "", color=JADE, alinear="center", tam_icono=56), tam=25,
                centro_v=True),
        columna(
            titulo("Garantía [nombre]: [N] días", alinear="left"),
            texto("<p><strong>Cómo funciona:</strong> [qué tiene que hacer la persona para usarla y cómo la "
                  "pide].</p><p><strong>Por qué la damos:</strong> [por qué confías en el programa].</p>"
                  "<p><strong>Además:</strong> [el contenido se actualiza sin costo durante [tiempo]].</p>"
                  "<p><a href=\"/politica-de-reembolso/\">Lee la política completa</a></p>"),
            tam=75, centro_v=True),
        fondo=BLANCO, ancho=1000, nota="18 · Garantía y actualización"))

    # 19. Los caminos.
    S.append(seccion(
        etiqueta("Llegó el momento de decidir"),
        titulo("Ahora tienes cuatro caminos"),
        interior(
            columna(caja_icono("fas fa-pause", "A · No hacer nada", "Arriesgado: [lo que sigue pasando si no "
                               "cambia nada].", color=MUT, alinear="center"), tam=25, fondo=BLANCO, pad=caja(24),
                    radio=16),
            columna(caja_icono("fas fa-random", "B · Hacerlo por tu cuenta", "Costoso: [el tiempo y los errores que "
                               "cuesta].", color=MUT, alinear="center"), tam=25, fondo=BLANCO, pad=caja(24),
                    radio=16),
            columna(caja_icono("fas fa-star", "C · [Nombre del producto]", "Inteligente: [el camino probado, con "
                               "acompañamiento].", color=JADE, alinear="center"),
                    boton("¡Sí, quiero [producto] ahora!", url="/carrito/", tam="sm", fuente=15),
                    tam=25, fondo=BLANCO, pad=caja(24), radio=16, borde=JADE, sombra=True),
            columna(caja_icono("fas fa-rocket", "D · [Opción premium]", "Más rápido: [acompañamiento uno a uno, "
                               "cupo limitado y real]. Bórrala si no la tienes.", color=AZUL, alinear="center"),
                    boton_whatsapp("Quiero aplicar", "Hola, quiero aplicar a [opción premium]"),
                    tam=25, fondo=BLANCO, pad=caja(24), radio=16),
            margen_sup=10),
        fondo=NIEBLA, nota="19 · Los caminos, con botón en el recomendado"))

    # 20. Objeciones, con la voz de los alumnos.
    obj = ["«Soy [perfil], ¿me servirá?»", "«No tengo tiempo, ¿podré?»", "«Ya tengo [experiencia o negocio], "
           "¿qué me aporta?»", "«No sé de tecnología, ¿lo voy a entender?»"]
    filas_obj = []
    for i in (0, 2):
        filas_obj.append(interior(*[columna(
            titulo(obj[i + k], "h3", alinear="left", color=AZUL),
            video("Audio o video corto de un alumno real con ese perfil"),
            texto("<p>[Nombre real] · [qué hace]</p>", tam=15),
            tam=50, fondo=NIEBLA, pad=caja(24), radio=16) for k in (0, 1)], margen_sup=0 if i == 0 else 20))
    S.append(seccion(
        etiqueta("¿Te funcionará?"),
        titulo("Te contestan quienes ya lo hicieron"),
        *filas_obj,
        fondo=BLANCO, nota="20 · Objeciones con la voz de los alumnos"))

    # 21. Lo avala la comunidad.
    S.append(seccion(
        etiqueta("No lo decimos nosotros"),
        titulo("Lo avala nuestra comunidad"),
        interior(*[columna(imagen(pie="Captura real de un mensaje o resultado"), tam=33) for _ in range(3)]),
        interior(*[columna(imagen(pie="Captura real de un mensaje o resultado"), tam=33) for _ in range(3)],
                 margen_sup=20),
        carrusel_testimonios([("[Testimonio real, con permiso: qué logró y en cuánto tiempo.]", "[Nombre real]",
                               "[Qué hace]")] * 6),
        fondo=NIEBLA, ident="historias", nota="21 · Lo avala la comunidad (ancla #historias)"))

    # 22. Preguntas frecuentes.
    S.append(seccion(
        etiqueta("Preguntas frecuentes"),
        titulo("Solucionamos tus dudas más frecuentes"),
        acordeon([
            ("¿Cuándo empiezo?", "[Al pagar recibes el acceso por correo / el [fecha] empieza el grupo].")
            , ("¿Cuánto tiempo necesito por semana?", "[N] horas. [Cómo está pensado para alguien ocupado].")
            , ("¿Por cuánto tiempo tengo acceso?", "[De por vida / un año / mientras dure la membresía].")
            , ("¿En qué moneda pago y qué medios aceptan?", f"En pesos mexicanos. {MEDIOS_PAGO_FAQ}. [Ajusta a los que "
               "aceptas].")
            , ("¿Puedo pagar a meses?", "[Sí, [N] meses sin intereses con tarjetas participantes / No].")
            , ("¿Me dan factura?", "Sí. Al pagar nos compartes tus datos fiscales y te enviamos tu CFDI.")
            , ("¿Desde qué dispositivo lo veo?", "Computadora, tableta o celular. El acceso es personal.")
            , ("¿Qué pasa si no me funciona?", "[La garantía, en una frase, con el enlace a la política].")
            , ("¿Se actualiza el contenido?", "[Sí, durante [tiempo], sin costo].")
            , ("¿Tendré soporte?", "[Por dónde, en qué horario y en cuánto tiempo responden].")]),
        fondo=BLANCO, ancho=860, nota="22 · Preguntas frecuentes"))

    # 23. WhatsApp con una cara.
    S.append(seccion(
        interior(columna(avatar(tam=96, alinear="center"), tam=20, centro_v=True),
                 columna(texto(f"<p><strong>¿Aún tienes dudas?</strong> Escríbenos por WhatsApp: te responde "
                               f"{P['atiende']}, [horario].</p>", tam=17, color=TX),
                         boton_whatsapp("Escríbenos aquí", alinear="left"), tam=80, centro_v=True)),
        fondo=NIEBLA, ancho=860, pad=(48, 20), nota="23 · WhatsApp con una cara"))

    # 24. Cierre.
    S.append(seccion(
        columna(
            titulo("¿Qué esperas?", color=BLANCO),
            texto("<p style=\"text-align:center\">[Lanza o relanza [lo que el cliente quiere] con [producto]: la "
                  "transformación en una frase.]</p>", color=TX_OSC, alinear="center"),
            lista(["¿Quieres [resultado 1]?", "¿Quieres [resultado 2]?", "¿Quieres [resultado 3]?",
                   "¿Quieres dejar de [el dolor principal]?"], color_icono=NEON, color_texto=TX_OSC,
                  ic="fas fa-question-circle"),
            espacio(16),
            interior(tarjeta_stack(), espacio="no"),
            texto(f"<p style=\"text-align:center\">{P['firma']}</p>", color=MUT_OSC, alinear="center", tam=16)),
        degradado=(NOCHE, "#0B2A45", 160), ancho=760, nota="24 · Cierre"))

    S.append(pie())
    return plantilla("DIGITALÍSIMO · 01 Página de venta completa", S)


# =============================================================== 02 · Página de venta corta

def venta_corta():
    S = []
    S.append(seccion(
        columna(
            etiqueta("[Para quién]", color=NEON, alinear="left"),
            titulo(f"[Resultado concreto] <span style=\"color:{NEON}\">en [tiempo]</span>", "h1", color=BLANCO,
                   alinear="left", tam=46),
            texto("<p>[La promesa en una frase y por qué ahora.]</p>", color=TX_OSC),
            boton("¡Lo quiero!", fondo=NEON, color=NOCHE, alinear="left"),
            tam=50, centro_v=True),
        columna(video("Video corto: 1 a 3 minutos"), tam=50, centro_v=True),
        degradado=(NOCHE, "#0B2A45", 160), pad=(80, 20), nota="1 · Cabecera"))
    S.append(seccion(
        titulo("Lo que vas a lograr"),
        interior(*[columna(caja_icono(i, f"[Beneficio {n}]", "[Qué gana, en una frase]", alinear="center"), tam=33)
                   for n, i in ((1, "fas fa-bullseye"), (2, "fas fa-clock"), (3, "fas fa-chart-line"))]),
        boton("Quiero [producto]"),
        fondo=BLANCO, nota="2 · Beneficios"))
    S.append(seccion(
        columna(imagen(pie=P["foto"]), tam=35, centro_v=True),
        columna(etiqueta("Quién está detrás", alinear="left"), titulo(P["saludo"], "h3", alinear="left"),
                texto(P["historia"]), tam=65, centro_v=True),
        fondo=NIEBLA, nota="2b · Quién está detrás"))
    S.append(seccion(
        columna(titulo("Es para ti si…", "h3", alinear="left"),
                lista(["[Situación 1]", "[Situación 2]", "[Situación 3]"]), tam=50),
        columna(titulo("Incluye", "h3", alinear="left"),
                lista(["[Módulo o pieza 1]", "[Módulo o pieza 2]", "[Módulo o pieza 3]",
                       ("BONUS: [nombre]", "fas fa-gift")]), tam=50),
        fondo=NIEBLA, nota="3 · Para quién e incluye"))
    S.append(seccion(
        titulo("Lo que dicen quienes ya lo tienen"),
        carrusel_testimonios([("[Testimonio real, con permiso.]", "[Nombre real]", "[Qué hace]")] * 3),
        fondo=BLANCO, nota="4 · Testimonios"))
    S.append(seccion(
        interior(tarjeta_stack(), espacio="no"),
        degradado=(NOCHE, "#0B2A45", 180), ancho=760, ident="oferta", nota="5 · Oferta (ancla #oferta)"))
    S.append(seccion(
        titulo("Garantía [nombre]: [N] días", "h3"),
        texto("<p style=\"text-align:center\">[Cómo funciona, en dos frases.]</p>", alinear="center"),
        separador(),
        titulo("Preguntas frecuentes", "h3"),
        acordeon([("¿Cuándo recibo el acceso?", "[Al momento, por correo]."),
                  ("¿En qué moneda pago?", f"En pesos mexicanos: {MEDIOS_PAGO}."),
                  ("¿Me dan factura?", "Sí, te enviamos tu CFDI."),
                  ("¿Y si tengo otra duda?", "Escríbenos por WhatsApp.")]),
        boton_whatsapp(),
        fondo=BLANCO, ancho=860, nota="6 · Garantía y preguntas"))
    S.append(pie())
    return plantilla("DIGITALÍSIMO · 02 Página de venta corta", S)


# =============================================================== 03 · Captación

CAMPOS_REGISTRO = [
    ("nombre", "text", "Tu nombre", "Nombre", True, None, 100),
    ("correo", "email", "Tu correo", "nombre@correo.com", True, None, 100),
    ("whatsapp", "tel", "Tu WhatsApp (para recordarte)", "871 000 0000", False, None, 100),
    ("perfil", "select", "¿Qué te describe mejor?", "", True,
     "Elige una opción\n[Tipo de cliente 1]\n[Tipo de cliente 2]\n[Tipo de cliente 3]", 100),
    ("privacidad", "acceptance", "Acepto el aviso de privacidad", "", True, None, 100),
]


def captacion():
    S = []
    S.append(seccion(
        columna(
            etiqueta("[Clase gratuita en vivo / Recurso gratuito]", color=NEON, alinear="left"),
            titulo(f"[Cómo lograr el resultado] <span style=\"color:{NEON}\">sin [obstáculo]</span>", "h1",
                   color=BLANCO, alinear="left", tam=44),
            texto("<p>[Subtítulo: qué se llevan en una frase.]</p>", color=TX_OSC),
            lista([("[Día] de [mes] · [hora] (hora del centro de México)", "far fa-calendar-alt", "fa-regular"),
                   (f"En vivo por {PLATAFORMA_SESION} · [duración]", "fas fa-video")],
                  color_icono=NEON, color_texto=TX_OSC),
            interior(columna(avatar(), tam=25, centro_v=True),
                     columna(texto(f"<p><strong>Con {P['nombre']}</strong><br>{P['cargo']}</p>", color=TX_OSC,
                                   tam=16), tam=75, centro_v=True), margen_sup=10),
            tam=55, centro_v=True),
        columna(
            titulo("Aparta tu lugar", "h3", color=TX),
            formulario(CAMPOS_REGISTRO, "¡Quiero mi lugar!", "/gracias-registro/", nombre="Registro"),
            texto("<p style=\"text-align:center\">Sin costo. Tus datos están protegidos.</p>", alinear="center",
                  tam=14),
            tam=45, fondo=BLANCO, pad=caja(32), radio=18, sombra=True),
        degradado=(NOCHE, "#0B2A45", 160), pad=(80, 20), ident="registro", nota="1 · Cabecera con formulario"))
    S.append(seccion(
        titulo("En esta clase vas a descubrir"),
        interior(*[columna(caja_icono("fas fa-lightbulb", f"[Aprendizaje {n}]", "[Por qué le importa]",
                                      alinear="center"), tam=33) for n in (1, 2, 3)]),
        fondo=BLANCO, nota="2 · Qué aprenderán"))
    S.append(seccion(
        columna(imagen(), tam=40, centro_v=True),
        columna(etiqueta("Quién la imparte", alinear="left"), titulo(P["saludo"], alinear="left"),
                texto(P["historia"]),
                lista(P["credenciales"][:3], ic="fas fa-award"),
                tam=60, centro_v=True),
        fondo=NIEBLA, nota="3 · Quién la imparte"))
    S.append(seccion(
        titulo("Lo que dicen quienes ya tomaron una clase"),
        interior(*[columna(estrellas(), cita("[Testimonio real, con permiso.]", "[Nombre real], [qué hace]"),
                           tam=33, fondo=NIEBLA, pad=caja(24), radio=16) for _ in range(3)]),
        fondo=BLANCO, nota="4 · Testimonios o logros"))
    S.append(seccion(
        titulo("¿Te apuntas?", color=BLANCO),
        texto("<p style=\"text-align:center\">[Recuerda la fecha y lo que se llevan.]</p>", color=TX_OSC,
              alinear="center"),
        boton("Apartar mi lugar", url="#registro", fondo=NEON, color=NOCHE),
        fondo=PROFUNDO, nota="5 · Recordatorio"))
    S.append(pie())
    return plantilla("DIGITALÍSIMO · 03 Captación", S)


# =============================================================== 04 · Gracias por registrarte

def gracias_registro():
    S = []
    S.append(seccion(
        caja_icono("fas fa-check", "", "", color=JADE, alinear="center"),
        titulo("¡Listo, [nombre]! Ya tienes tu lugar", "h1", color=BLANCO, tam=44),
        texto("<p style=\"text-align:center\">Para no perderte nada, haz estos tres pasos ahora.</p>", color=TX_OSC,
              alinear="center"),
        degradado=(NOCHE, "#0B2A45", 160), ancho=860, nota="1 · Confirmación"))
    S.append(seccion(
        interior(
            columna(caja_icono("fas fa-envelope-open-text", "Paso 1: confirma tu correo",
                               "Te enviamos un correo de [remitente]. Ábrelo y confirma; si no está, revisa "
                               "promociones o spam.", alinear="center"), tam=33),
            columna(caja_icono("far fa-calendar-check", "Paso 2: agéndalo",
                               "[Día] de [mes] a las [hora] (centro de México). Ponlo en tu calendario.",
                               alinear="center", lib="fa-regular"), tam=33),
            columna(caja_icono("fab fa-whatsapp", "Paso 3: únete al grupo",
                               "Ahí mandamos el enlace y el material de la clase.", alinear="center", color=JADE,
                               lib="fa-brands"), tam=33)),
        boton("Unirme al grupo de WhatsApp", url="#[enlace del grupo]"),
        fondo=BLANCO, nota="2 · Tres pasos"))
    S.append(seccion(
        columna(video("Video corto de bienvenida (opcional)"), tam=50, centro_v=True),
        columna(titulo("Mientras tanto", "h3", alinear="left"),
                texto("<p>[Un contenido corto para calentar: un video, un artículo o una plantilla.]</p>"),
                redes(), tam=50, centro_v=True),
        fondo=NIEBLA, nota="3 · Mientras tanto"))
    S.append(pie())
    return plantilla("DIGITALÍSIMO · 04 Gracias por registrarte", S)


# =============================================================== 05 · Carrito

CAMPOS_CARRITO = [
    ("nombre", "text", "Nombre y apellido", "Nombre y apellido", True, None, 100),
    ("correo", "email", "Correo (ahí llega tu acceso)", "nombre@correo.com", True, None, 100),
    ("whatsapp", "tel", "WhatsApp", "871 000 0000", True, None, 100),
    ("pago", "radio", "Elige cómo pagar", "", True,
     "Pago único: $[precio] MXN (ahorras $[X])|unico\n[N] meses sin intereses de $[mensualidad] MXN|meses", 100,
     "unico"),
    ("extra", "checkbox", "Agrégalo a tu pedido", "", False,
     "Sí, agrega [extra: plantilla, sesión 1:1 o guía] por solo $[precio extra] MXN|extra", 100),
    ("factura", "checkbox", "Factura", "", False, "Quiero factura (CFDI)|cfdi", 100),
    ("terminos", "acceptance", "Acepto los términos y el aviso de privacidad", "", True, None, 100),
]


def carrito():
    S = []
    S.append(seccion(
        titulo("Estás a un paso de [resultado]", "h2", color=BLANCO, tam=34),
        texto("<p style=\"text-align:center\">[Nombre del producto] · Pago único de $[precio] MXN o [N] meses sin "
              "intereses</p>", color=TX_OSC, alinear="center", tam=17),
        fondo=PROFUNDO, pad=(36, 20), nota="1 · Encabezado con el precio"))
    S.append(seccion(
        columna(
            titulo("1. Tus datos y tu forma de pago", "h3", alinear="left"),
            formulario(CAMPOS_CARRITO, "Continuar al pago seguro", "/pago/", nombre="Carrito", color_boton=JADE),
            texto(f"<p style=\"text-align:center\">🔒 Pago 100 % seguro. En el siguiente paso pagas con {MEDIOS_PAGO}."
                  "</p>", alinear="center", tam=14),
            imagen(ancho=45, radio=0, pie="Logos de pago seguro y de los medios que aceptas"),
            interior(columna(caja_icono("fas fa-shield-alt", "Garantía [nombre]: [N] días",
                                        "[Cómo funciona, en una frase]", posicion="left", color=JADE), tam=100)),
            tam=55, fondo=BLANCO, pad=caja(32), radio=18, sombra=True),
        columna(
            titulo("Lo que te llevas", "h3", alinear="left"),
            imagen(pie="Maqueta de todo lo que incluye"),
            lista(["[Producto principal] <strong>(valor $[X] MXN)</strong>", "[Acceso y duración]",
                   "[Acompañamiento]",
                   ("BONUS 1: [nombre] <strong>(valor $[X] MXN)</strong>", "fas fa-gift"),
                   ("BONUS 2: [nombre] <strong>(valor $[X] MXN)</strong>", "fas fa-gift")], color_texto=TX),
            separador(100),
            texto("<p>Valor total: <del>$[valor total] MXN</del></p>", tam=17),
            titulo(f"Hoy: <span style=\"color:{JADE}\">$[precio] MXN</span>", "h2", alinear="left", tam=36),
            tam=45, fondo=NIEBLA, pad=caja(32), radio=18),
        fondo=BLANCO, pad=(56, 20), nota="2 · Formulario junto a la oferta"))
    S.append(seccion(
        titulo("Quienes ya lo compraron", "h3"),
        interior(*[columna(estrellas(), cita("[Testimonio real, con permiso.]", "[Nombre real], [qué hace]"),
                           tam=33, fondo=NIEBLA, pad=caja(24), radio=16) for _ in range(3)]),
        fondo=BLANCO, pad=(40, 20), nota="3 · Testimonios"))
    S.append(seccion(
        titulo("Dudas sobre el pago", "h3"),
        acordeon([("¿Es seguro pagar aquí?", f"Sí. El pago lo procesa {PASARELA}; nosotros nunca vemos los datos de "
                   "tu tarjeta."),
                  ("¿Puedo pagar en {} o por {}?".format(*PAGO_EFECTIVO), "Sí. En el siguiente paso eliges cómo pagar."),
                  ("¿Cuándo recibo el acceso?", "[Al confirmarse el pago, por correo]. Con {} o {} puede tardar "
                   "hasta [N] horas.".format(*PAGO_EFECTIVO)),
                  ("¿Me dan factura?", "Sí, marca la casilla y te pediremos tus datos fiscales."),
                  ("¿Y si no me funciona?", "Tienes la garantía [nombre] de [N] días.")]),
        interior(columna(avatar(alinear="center"), tam=20, centro_v=True),
                 columna(texto("<p><strong>¿Te quedó una duda?</strong> Escríbenos y te respondemos en minutos, "
                               "[horario].</p>", tam=16), boton_whatsapp(alinear="left"), tam=80, centro_v=True),
                 margen_sup=24),
        fondo=NIEBLA, ancho=860, pad=(48, 20), nota="4 · Dudas de pago y WhatsApp"))
    S.append(urgente(seccion(
        titulo(f"Este precio termina el {DIA_CIERRE} a las {HORA_CIERRE} (centro de México)", "h3"),
        contador(ancho=70),
        fondo=BLANCO, pad=(40, 20), nota="5 · Urgencia: solo si el cierre es real")))
    S.append(pie())
    return plantilla("DIGITALÍSIMO · 05 Carrito", S)


# =============================================================== 06 · Oferta única después de la compra

def oferta_unica():
    S = []
    S.append(seccion(
        etiqueta("¡Espera! Tu pedido casi está listo", color=NEON),
        titulo("Una oferta que solo verás una vez", "h1", color=BLANCO, tam=42),
        texto("<p style=\"text-align:center\">[Por qué este complemento hace que el producto que compraste funcione "
              "mejor o más rápido.]</p>", color=TX_OSC, alinear="center"),
        video("Video de 2 minutos explicando el complemento"),
        degradado=(NOCHE, "#0B2A45", 160), ancho=860, nota="1 · Aviso y video"))
    S.append(seccion(
        columna(imagen(pie="Maqueta del complemento"), tam=45, centro_v=True),
        columna(titulo("[Nombre del complemento]", "h3", alinear="left"),
                lista(["[Qué incluye 1]", "[Qué incluye 2]", "[Qué incluye 3]"]),
                texto("<p>Precio normal: <del>$[X] MXN</del></p>", tam=17),
                titulo(f"Solo hoy: <span style=\"color:{JADE}\">$[precio] MXN</span>", "h2", alinear="left",
                       tam=36),
                boton("Sí, agrégalo a mi pedido", url="/pago-complemento/", ancho_completo=True),
                texto("<p style=\"text-align:center\"><a href=\"/gracias-compra/\">No, gracias: quiero seguir sin "
                      "el complemento</a></p>", alinear="center", tam=15),
                tam=55, centro_v=True),
        fondo=BLANCO, nota="2 · El complemento, sí o no"))
    S.append(pie())
    return plantilla("DIGITALÍSIMO · 06 Oferta única después de la compra", S)


# =============================================================== 07 · Gracias por tu compra

def gracias_compra():
    S = []
    S.append(seccion(
        caja_icono("fas fa-gift", "", "", color=JADE, alinear="center"),
        titulo("¡Bienvenido a [producto], [nombre]!", "h1", color=BLANCO, tam=44),
        texto("<p style=\"text-align:center\">Tu pago se confirmó. Esto es lo que sigue.</p>", color=TX_OSC,
              alinear="center"),
        degradado=(NOCHE, "#0B2A45", 160), ancho=860, nota="1 · Bienvenida"))
    S.append(seccion(
        interior(
            columna(caja_icono("fas fa-envelope", "1. Revisa tu correo", "Te enviamos tu acceso a [correo]. Si no "
                               "está, revisa promociones o spam.", alinear="center"), tam=33),
            columna(caja_icono("fas fa-sign-in-alt", "2. Entra a la plataforma", "[Enlace y cómo entrar la primera "
                               "vez.]", alinear="center"), tam=33),
            columna(caja_icono("fas fa-users", "3. Preséntate en la comunidad", "[Dónde y qué escribir.]",
                               alinear="center", color=JADE), tam=33)),
        boton("Entrar a la plataforma", url="/acceso/"),
        fondo=BLANCO, nota="2 · Qué sigue"))
    S.append(seccion(
        columna(video("Video de bienvenida"), tam=50, centro_v=True),
        columna(titulo("¿Necesitas ayuda?", "h3", alinear="left"),
                texto("<p>Escríbenos a [correo de soporte] o por WhatsApp, [horario]. Para tu factura, manda tus datos "
                      "fiscales al mismo correo.</p>"),
                boton_whatsapp("Soporte por WhatsApp", "Hola, ya compré [producto] y necesito ayuda", alinear="left"),
                redes(), tam=50, centro_v=True),
        fondo=NIEBLA, nota="3 · Soporte y redes"))
    S.append(pie())
    return plantilla("DIGITALÍSIMO · 07 Gracias por tu compra", S)


# =============================================================== 08 · Bloques de prueba social

def bloques_prueba():
    S = []
    S.append(seccion(
        titulo("Bloques de prueba social", "h2"),
        texto("<p style=\"text-align:center\">Copia el bloque que necesites a tu página. Todos son para testimonios "
              "reales y con permiso: nunca se inventa uno.</p>", alinear="center"),
        fondo=NIEBLA, pad=(48, 20), nota="Instrucciones (no se publica)"))
    # A. Muro de videos titulados por resultado.
    S.append(seccion(
        etiqueta("Bloque A · Muro de resultados"),
        titulo("Personas reales que ya lo lograron"),
        interior(*[columna(video("Video-testimonio real"),
                           titulo("De [antes] a [después] en [tiempo]", "h4", alinear="left", margen=4),
                           texto("<p>[Nombre real] · [qué hace]</p>", tam=15), tam=33) for _ in range(3)]),
        fondo=BLANCO, nota="A · Muro de video-testimonios"))
    # B. Historia de éxito de una persona.
    S.append(seccion(
        columna(etiqueta("Bloque B · Historia de éxito", color=NEON, alinear="left"),
                titulo("La historia de [nombre] con [producto]", color=BLANCO, alinear="left"),
                cita("[La frase que resume su cambio, con sus palabras.]", "[Nombre real], [qué hace]", oscuro=True),
                tam=50, centro_v=True),
        columna(video("Video de su historia"), tam=50, centro_v=True),
        fondo=NOCHE, nota="B.1 · Historia: portada"))
    preguntas = ["¿Cómo estaba tu [negocio / vida] antes de [producto]?",
                 "¿Qué cambió después de aplicar lo que aprendiste?",
                 "¿Cuál fue tu mayor transformación?", "¿Para qué te sirvió [producto]?"]
    S.append(seccion(
        columna(*[w for p in preguntas for w in (titulo(p, "h3", alinear="left", color=AZUL),
                                                  texto("<p>[Su respuesta, con sus palabras.]</p>"))],
                tam=60),
        columna(titulo("Resultados", "h3", alinear="left"),
                lista(["[Resultado con dato real 1]", "[Resultado con dato real 2]", "[Resultado con dato real 3]"]),
                imagen(), tam=40, fondo=NIEBLA, pad=caja(28), radio=16),
        fondo=BLANCO, nota="B.2 · Historia: antes y después"))
    # C. Carrusel.
    S.append(seccion(
        etiqueta("Bloque C · Carrusel"),
        carrusel_testimonios([("[Testimonio real, con permiso.]", "[Nombre real]", "[Qué hace]")] * 6),
        fondo=NIEBLA, nota="C · Carrusel"))
    # D. Capturas.
    S.append(seccion(
        etiqueta("Bloque D · Capturas reales"),
        interior(*[columna(imagen(pie="Captura real de un mensaje o resultado"), tam=33) for _ in range(3)]),
        interior(*[columna(imagen(pie="Captura real de un mensaje o resultado"), tam=33) for _ in range(3)],
                 margen_sup=20),
        fondo=BLANCO, nota="D · Capturas"))
    # E. Calificación con estrellas.
    S.append(seccion(
        etiqueta("Bloque E · Calificación"),
        interior(*[columna(estrellas(), cita("[Reseña real, con permiso.]", "[Nombre real]"), tam=33,
                           fondo=NIEBLA, pad=caja(24), radio=16) for _ in range(3)]),
        fondo=BLANCO, nota="E · Reseñas con estrellas"))
    # F. Lo que se lleva al terminar: algo que se pueda ver.
    S.append(seccion(
        columna(imagen(pie="Certificado o insignia con «Tu nombre aquí»"), tam=45, centro_v=True),
        columna(etiqueta("Bloque F · Al terminar te llevas", alinear="left"),
                titulo("[Tu certificado / tu sello / tu plan terminado]", alinear="left"),
                lista(["[Qué demuestra y ante quién]", "[Qué hay que cumplir para obtenerlo]",
                       "[Dónde lo puede mostrar]"]),
                tam=55, centro_v=True),
        fondo=NIEBLA, nota="F · Resultado que se puede ver"))
    # G. Una objeción contestada por un alumno con ese perfil.
    S.append(seccion(
        etiqueta("Bloque G · Objeción con la voz de un alumno"),
        interior(*[columna(titulo(f"«Soy [perfil {n}], ¿me servirá?»", "h3", color=AZUL),
                           video("Audio o video corto de un alumno real con ese perfil"),
                           texto("<p>[Nombre real] · [qué hace]</p>", tam=15), tam=50, fondo=BLANCO, pad=caja(24),
                           radio=16, borde=BD) for n in (1, 2)]),
        fondo=BLANCO, nota="G · Objeción contestada por un alumno"))
    return plantilla("DIGITALÍSIMO · 08 Bloques de prueba social", S)


# =============================================================== catálogo

# Para qué sirve cada plantilla y cuándo usarla (LEEME del kit y tabla «Qué landing armar según el caso» del cerebro).
CATALOGO = [
    {"id": "venta-completa", "numero": "01", "archivo": "01-pagina-de-venta-completa.json", "fn": venta_completa,
     "nombre": "Página de venta completa",
     "para": "La carta de venta larga, en el orden de la guía (26 bloques): barra de cierre, promesa, problema, quién "
             "está detrás (corto), ¿te imaginas…?, por qué es distinto, autoridad con logos, funciona para gente como "
             "tú, para quién es, qué es, temario con botón, todo lo que incluye, nunca estarás solo, al terminar, bonus "
             "con su total, nuestro compromiso, cuánto vale, la oferta (#oferta), garantía y actualización, los "
             "caminos, objeciones con la voz de alumnos, lo avala la comunidad (#historias), preguntas frecuentes, "
             "WhatsApp con una cara, cierre y pie.",
     "bloques": {"Barra": ("barra-de-cierre", "Barra de cierre"), "1": ("promesa", "La promesa"),
                 "2": ("problema", "El problema"), "3": ("quien-esta-detras", "Quién está detrás, corto"),
                 "4": ("te-imaginas", "¿Te imaginas…?"), "5": ("por-que-es-distinto", "Por qué es distinto"),
                 "6": ("autoridad", "Quién está detrás, la autoridad (con logos)"),
                 "7": ("funciona-para-ti", "Funciona para gente como tú"), "8": ("para-quien-es", "Para quién es"),
                 "9": ("que-es", "Qué es"), "10": ("temario", "Temario"),
                 "11": ("todo-lo-que-incluye", "Todo lo que incluye"), "12": ("nunca-estaras-solo", "Nunca estarás solo"),
                 "13": ("al-terminar", "Lo que te llevas al terminar"), "14": ("bonus", "Bonus"),
                 "15": ("compromiso", "Nuestro compromiso"), "16": ("cuanto-vale", "Cuánto vale para ti"),
                 "17": ("oferta", "La oferta"), "18": ("garantia", "Garantía y actualización"),
                 "19": ("caminos", "Los caminos"), "20": ("objeciones", "¿Te funcionará? (objeciones)"),
                 "21": ("comunidad", "Lo avala la comunidad"), "22": ("preguntas-frecuentes", "Preguntas frecuentes"),
                 "23": ("whatsapp", "WhatsApp con una cara"), "24": ("cierre", "Cierre"), "Pie": ("pie", "Pie")},
     "cuando": "Programa caro o lanzamiento con cierre (más de unos 6 000 MXN): antes una clase o un reto (03 y 04), "
               "después el carrito (05) con pagos a plazos. Recortada, para un curso de precio medio después de una "
               "clase.",
     "mide": "Vista de página"},
    {"id": "venta-corta", "numero": "02", "archivo": "02-pagina-de-venta-corta.json", "fn": venta_corta,
     "nombre": "Página de venta corta",
     "para": "Promesa con video, tres beneficios, quién está detrás, para quién es, qué incluye, testimonios, la oferta, "
             "la garantía y pocas preguntas; el botón se repite.",
     "bloques": {"1": ("promesa", "La promesa"), "2": ("beneficios", "Beneficios"),
                 "2b": ("quien-esta-detras", "Quién está detrás"), "3": ("para-quien-es", "Para quién es e incluye"),
                 "4": ("testimonios", "Testimonios"), "5": ("oferta", "La oferta"),
                 "6": ("garantia-y-preguntas", "Garantía y preguntas frecuentes"), "Pie": ("pie", "Pie")},
     "cuando": "Producto barato (hasta unos 1 000 MXN) con anuncios, con el carrito (05) y la oferta después de pagar "
               "(06); quien ya conoce la marca; un curso de precio medio después de una clase (ampliada); un servicio "
               "(sin carrito, con «Agendar asesoría» o «Hablar por WhatsApp»); un evento de venta de temporada con su "
               "fecha real; o un paquete de la tienda.",
     "mide": "Vista de página"},
    {"id": "captacion", "numero": "03", "archivo": "03-captacion.json", "fn": captacion,
     "nombre": "Captación",
     "para": "Registro a una clase gratuita, un reto o la descarga de un recurso, con un formulario (nombre, correo, "
             "WhatsApp y una pregunta de perfil) que guarda cada envío, avisa por correo y lleva a /gracias-registro/.",
     "cuando": "Para quien llega frío, de un anuncio: regalar un recurso, una clase en vivo o un reto de varios días. "
               "Siempre con la 04. Sin precio ni oferta: se venden en la clase.",
     "mide": "Vista de página",
     "bloques": {"1": ("registro", "Cabecera con formulario"), "2": ("que-aprenderan", "Qué aprenderán"),
                 "3": ("quien-la-imparte", "Quién la imparte"), "4": ("testimonios", "Testimonios o logros"),
                 "5": ("recordatorio", "Recordatorio"), "Pie": ("pie", "Pie")}},
    {"id": "gracias-registro", "numero": "04", "archivo": "04-gracias-por-registrarte.json", "fn": gracias_registro,
     "nombre": "Gracias por registrarte",
     "para": "Confirmación y los tres pasos después de registrarse: confirmar el correo, agendar la fecha y unirse al "
             "grupo.",
     "cuando": "Después de la 03, en /gracias-registro/. Sin doble confirmación, aquí va el enlace de descarga; con "
               "«regalo con oferta en la gracias», un bloque con una oferta barata y relacionada.",
     "mide": "Registro completado (Lead)",
     "bloques": {"1": ("confirmacion", "Confirmación"), "2": ("tres-pasos", "Tres pasos"),
                 "3": ("mientras-tanto", "Mientras tanto"), "Pie": ("pie", "Pie")}},
    {"id": "carrito", "numero": "05", "archivo": "05-carrito.json", "fn": carrito,
     "nombre": "Carrito",
     "para": "Formulario de datos y forma de pago junto a la oferta: pago único marcado de entrada, meses sin "
             "intereses, el extra de un clic, la garantía y los medios de pago junto al botón, WhatsApp con una cara "
             "y, sólo con un cierre real, el contador.",
     "cuando": "Después de la 01 o la 02 cuando se cobra en el sitio, en /carrito/; su formulario lleva a /pago/.",
     "mide": "Iniciar pago (InitiateCheckout)",
     "bloques": {"1": ("encabezado", "Encabezado con el precio"), "2": ("formulario", "Formulario junto a la oferta"),
                 "3": ("testimonios", "Testimonios"), "4": ("dudas-de-pago", "Dudas de pago y WhatsApp"),
                 "5": ("urgencia", "Urgencia (sólo con cierre real)"), "Pie": ("pie", "Pie")}},
    {"id": "oferta-unica", "numero": "06", "archivo": "06-oferta-unica-despues-de-la-compra.json", "fn": oferta_unica,
     "nombre": "Oferta única después de la compra",
     "para": "Un complemento, sí o no, justo después de pagar: aviso, video corto, el complemento con su precio de hoy "
             "y dos botones.",
     "cuando": "Entre el pago y la 07 (el pago lleva aquí): producto barato con anuncios o lanzamiento con complemento. "
               "«Sí» va a /pago-complemento/ y «No» a /gracias-compra/.",
     "mide": "Compra del producto principal (Purchase)",
     "bloques": {"1": ("aviso-y-video", "Aviso y video"), "2": ("complemento", "El complemento, sí o no"),
                 "Pie": ("pie", "Pie")}},
    {"id": "gracias-compra", "numero": "07", "archivo": "07-gracias-por-tu-compra.json", "fn": gracias_compra,
     "nombre": "Gracias por tu compra",
     "para": "Bienvenida, revisar el correo de acceso, entrar a la plataforma, presentarse en la comunidad y soporte.",
     "cuando": "Al final de toda compra, en /gracias-compra/.",
     "mide": "Compra con el total final",
     "bloques": {"1": ("bienvenida", "Bienvenida"), "2": ("que-sigue", "Qué sigue"), "3": ("soporte", "Soporte y redes"),
                 "Pie": ("pie", "Pie")}},
    {"id": "prueba-social", "numero": "08", "archivo": "08-bloques-de-prueba-social.json", "fn": bloques_prueba,
     "nombre": "Bloques de prueba social",
     "para": "Siete bloques para copiar a otra página: muro de videos titulados por resultado, historia de éxito, "
             "carrusel, capturas, reseñas, lo que se lleva al terminar y una objeción contestada por un alumno.",
     "cuando": "Para armar o ampliar las pruebas de otra página; no se publica sola. Sólo con testimonios reales y con "
               "permiso.",
     "mide": "",
     "bloques": {"Instrucciones": ("instrucciones", "Instrucciones"), "A": ("muro", "Muro de video-testimonios"),
                 "B": ("historia", "Historia de éxito"), "C": ("carrusel", "Carrusel"), "D": ("capturas", "Capturas"),
                 "E": ("resenas", "Reseñas con estrellas"), "F": ("al-terminar", "Lo que se lleva al terminar"),
                 "G": ("objecion", "Objeción contestada por un alumno")}},
]

EMBUDO = ("anuncio → 03 Captación → 04 Gracias por registrarte → clase o contenido → 01 o 02 → 05 Carrito → pago → "
          "06 Oferta única → 07 Gracias por tu compra")

# Qué landing armar según el caso (tabla del cerebro, `oficio/ventas-en-linea.md`). Rangos en pesos y las filas del
# servicio, el evento y el paquete de tienda: propuesta sin aprobar (inferido).
CASOS = [
    {"caso": "Regalar un recurso para juntar contactos", "plantillas": ["captacion", "gracias-registro"],
     "no_falta": "Titular con el resultado, el recurso, tres beneficios y un formulario de dos o tres campos; en la "
                 "página de gracias, el siguiente paso.",
     "sobra": "Testimonios largos y temario: menos campos, más registros."},
    {"caso": "Clase en vivo o webinar", "plantillas": ["captacion", "gracias-registro"],
     "no_falta": "Fecha y hora del centro de México, foto del presentador, qué aprenderá y una pregunta que filtre el "
                 "perfil; en la de gracias, «agéndalo» y el grupo.",
     "sobra": "Precio y oferta: se venden en la clase."},
    {"caso": "Reto de varios días", "plantillas": ["captacion", "gracias-registro"],
     "no_falta": "Qué pasa cada día y dónde se hace.", "sobra": "Precio y oferta."},
    {"caso": "Producto barato (hasta unos 1 000 MXN) que se vende siempre, con anuncios",
     "plantillas": ["venta-corta", "carrito", "oferta-unica"],
     "no_falta": "Video corto, para quién es, qué incluye, valor tachado, botón repetido, el extra de un clic y la "
                 "oferta después de pagar.",
     "sobra": "Autoridad larga, objeciones y alternativas."},
    {"caso": "Regalo con oferta en la gracias", "plantillas": ["captacion", "gracias-registro"],
     "no_falta": "Una oferta barata y relacionada con lo que se descargó, en la de gracias.", "sobra": ""},
    {"caso": "Curso de precio medio (unos 1 000 a 6 000 MXN), casi siempre después de una clase",
     "plantillas": ["venta-corta", "venta-completa", "carrito"],
     "no_falta": "La 02 ampliada o la 01 recortada: video, temario, bonus, testimonios, garantía, preguntas frecuentes "
                 "y meses sin intereses.",
     "sobra": "La gran idea y las alternativas."},
    {"caso": "Programa caro o lanzamiento con cierre (más de unos 6 000 MXN)",
     "plantillas": ["captacion", "gracias-registro", "venta-completa", "carrito"],
     "no_falta": "Todo: urgencia real con fecha, mensualidades, objeciones, «para quién no es» y muchas pruebas.",
     "sobra": "Nada."},
    {"caso": "Servicio de DIGITALÍSIMO (asesoría, hosting, crecimiento digital)", "plantillas": ["venta-corta"],
     "no_falta": "Beneficios, casos reales, precio o «desde» y los llamados del manual: «Agendar asesoría» o «Hablar "
                 "por WhatsApp».",
     "sobra": "Carrito, contador y oferta después de pagar."},
    {"caso": "Evento de venta de temporada", "plantillas": ["venta-corta"],
     "no_falta": "La fecha real del evento, el descuento de temporada y, si hay cierre real, la cuenta regresiva.",
     "sobra": "Escasez inventada."},
    {"caso": "Lanzamiento a la lista de correo", "plantillas": ["venta-completa", "venta-corta"],
     "no_falta": "Una copia de la página de venta con un mensaje personal y un descuento sólo para suscriptores.",
     "sobra": ""},
    {"caso": "Página sólo para anuncios", "plantillas": ["venta-completa", "venta-corta"],
     "no_falta": "Una copia de la página de venta con su propio botón de pago, para saber qué ventas vinieron de los "
                 "anuncios.",
     "sobra": ""},
    {"caso": "Después del cierre", "plantillas": ["venta-completa", "venta-corta"],
     "no_falta": "La misma página, cambiada: el precio normal o una lista de espera para la próxima apertura.",
     "sobra": "El contador y la oferta de lanzamiento."},
    {"caso": "Paquete de la tienda", "plantillas": ["venta-corta"],
     "no_falta": "El paquete (producto, accesorios, envío), un extra en el carrito, entrega y devoluciones, junto a la "
                 "ficha del producto.",
     "sobra": "Temario y clase."},
]

# A dónde llevan los enlaces internos de las plantillas: la página con esa dirección se crea aparte (o se cambian los
# enlaces en el editor).
ENLACES = {
    "#oferta": "ancla del bloque de la oferta en la misma página",
    "#registro": "ancla del formulario en la misma página",
    "#historias": "ancla del bloque «Lo avala la comunidad» en la misma página (botón «Ver más historias»)",
    "/gracias-registro/": "04 Gracias por registrarte (a donde lleva el formulario de la 03)",
    "/carrito/": "05 Carrito",
    "/pago/": "el pago: el enlace de la pasarela o el pago de la tienda (a donde lleva el formulario del carrito)",
    "/pago-complemento/": "el pago del complemento (botón «Sí» de la 06)",
    "/gracias-compra/": "07 Gracias por tu compra (botón «No» de la 06)",
    "/acceso/": "la entrada a la plataforma del curso o del servicio",
    "/aviso-de-privacidad/": "aviso de privacidad",
    "/terminos-y-condiciones/": "términos y condiciones",
    "/politica-de-reembolso/": "política de reembolso",
}

_HREF = re.compile(r'href=\\?"([^"\\]*)')


def enlaces_internos(data):
    """Rutas y anclas del propio sitio a las que llevan los botones, los formularios y los textos."""
    vistos = []

    def anda(v, clave=""):
        if isinstance(v, dict):
            for k, x in v.items():
                anda(x, k)
        elif isinstance(v, list):
            for x in v:
                anda(x, clave)
        elif isinstance(v, str):
            urls = [v] if clave in ("url", "redirect_to") else _HREF.findall(v)
            for u in urls:
                if u.startswith(("/", "#")) and "[" not in u and u not in vistos:
                    vistos.append(u)
    anda(data["content"])
    return sorted(vistos)


_PREFIJO = re.compile(r"^(Barra|Pie|Instrucciones|[0-9]+[a-z]?|[A-Z])(?:\.[0-9]+)?\b")


def bloques(meta, contenido):
    """Los bloques de la plantilla, en orden: cada sección de primer nivel cae en el bloque de su nota en el Navegador
    (`2b` en el suyo si lo declara; si no, en el de `2`; `14.3` en el de `14`). Se pueden quitar, nunca reordenar."""
    out = []
    for e in contenido:
        nota = e["settings"].get("_title", "")
        m = _PREFIJO.match(nota)
        if not m:
            raise SystemExit(f"{meta['archivo']}: la sección {e['id']} no tiene una nota de bloque: {nota!r}")
        pref = m.group(1)
        base = re.sub(r"[a-z]$", "", pref)
        clave, nombre = meta["bloques"].get(pref) or meta["bloques"].get(base) or (None, None)
        if not clave:
            raise SystemExit(f"{meta['archivo']}: la nota {nota!r} no tiene bloque en CATALOGO")
        if out and out[-1]["key"] == clave:
            out[-1]["sections"].append(e["id"])
        else:
            if any(b["key"] == clave for b in out):
                raise SystemExit(f"{meta['archivo']}: el bloque {clave} aparece en dos lugares")
            out.append({"key": clave, "name": nombre, "required": clave == "pie", "sections": [e["id"]]})
    usados = {b["key"] for b in out}
    sobran = [c for c, _ in meta["bloques"].values() if c not in usados]
    if sobran:
        raise SystemExit(f"{meta['archivo']}: bloques declarados sin secciones: {sobran}")
    return out


def contar(elementos, cuenta=None):
    cuenta = {} if cuenta is None else cuenta
    for e in elementos:
        if e["elType"] == "widget":
            cuenta[e["widgetType"]] = cuenta.get(e["widgetType"], 0) + 1
        contar(e.get("elements", []), cuenta)
    return cuenta


def generar():
    """{ruta relativa a templates/: contenido} de las 16 plantillas y el manifiesto."""
    archivos, plantillas = {}, []
    for i, meta in enumerate(CATALOGO):
        reiniciar_ids(20261112 + i)
        data = meta["fn"]()
        urg = urgentes(data["content"])
        limpiar(data["content"])
        secciones = volcar(data)
        contenedores = volcar(a_contenedores(json.loads(secciones)))
        archivos["sections/" + meta["archivo"]] = secciones
        archivos["containers/" + meta["archivo"]] = contenedores
        widgets = contar(data["content"])
        enlaces = enlaces_internos(data)
        faltan = [u for u in enlaces if u not in ENLACES]
        if faltan:
            raise SystemExit(f"{meta['archivo']}: enlaces sin explicar en ENLACES: {faltan}")
        plantillas.append({
            "id": meta["id"], "number": meta["numero"], "name": meta["nombre"], "library_title": data["title"],
            "file": meta["archivo"], "purpose": meta["para"], "use_when": meta["cuando"], "measures": meta["mide"],
            "sections": len(data["content"]), "widgets": sum(widgets.values()), "widget_types": widgets,
            "has_countdown": "countdown" in widgets, "has_form": "form" in widgets,
            "internal_links": [{"path": u, "for": ENLACES[u]} for u in enlaces],
            "blocks": bloques(meta, data["content"]),
            "urgency": urg,
        })
    manifest = {
        "_comment": "Generado por scripts/sales-kit/kit_paginas.py: no se edita a mano (tests/elements-sales-kit.mjs).",
        "kit_version": "3-generico",
        "kit_source": "OneDrive: DIGITALISIMO/Plantillas/Landing Page Ventas/Kit de ventas DIGITALISIMO v3/ (generador con "
                      "--generico, PERSONA_GENERICA)",
        "guide_source": {"repository": "digitalisimo-brain", "file": "oficio/ventas-en-linea.md", "commit": "06ff42f",
                         "section": "El orden de una página de venta"},
        "funnel": EMBUDO,
        "templates": plantillas,
        "cases": CASOS,
    }
    archivos["manifest.json"] = json.dumps(manifest, ensure_ascii=False, indent=1) + "\n"
    return archivos


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if a != "--check"]
    destino = args[0] if args else os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..",
                                                "digitalisimo-elements", "modules", "digitalisimo-sales", "templates")
    archivos = generar()
    if "--check" in sys.argv:
        distintos = []
        for rel, contenido in archivos.items():
            ruta = os.path.join(destino, rel)
            if not os.path.isfile(ruta) or open(ruta, encoding="utf-8").read() != contenido:
                distintos.append(rel)
        sobrantes = []
        for sub in ("sections", "containers"):
            d = os.path.join(destino, sub)
            for f in sorted(os.listdir(d)) if os.path.isdir(d) else []:
                if sub + "/" + f not in archivos:
                    sobrantes.append(sub + "/" + f)
        if distintos or sobrantes:
            print("Las plantillas no coinciden con el generador:", ", ".join(distintos + sobrantes))
            sys.exit(1)
        print(f"Las {len(archivos)} salidas del generador coinciden con las del repositorio.")
        sys.exit(0)
    for rel, contenido in archivos.items():
        ruta = os.path.join(destino, rel)
        os.makedirs(os.path.dirname(ruta), exist_ok=True)
        with open(ruta, "w", encoding="utf-8") as fh:
            fh.write(contenido)
        print("ok", rel)
