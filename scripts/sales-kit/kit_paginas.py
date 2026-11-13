"""Kit de ventas DIGITALÍSIMO para Elements: ocho plantillas de página.

Uso: python3 -I kit_paginas.py <carpeta de salida>

Lo que va entre [corchetes] se reemplaza. Reglas que el kit trae escritas en sus textos guía:
- testimonios y cifras, solo reales y con permiso (D-0023);
- la urgencia, solo si el cierre es real: fecha y hora del centro de México;
- se promete trabajo, no resultados (D-0024): el aviso va en el pie;
- precios en pesos, meses sin intereses y medios de pago de México.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from kit_lib import *  # noqa: E402,F401,F403

OSC = dict(oscuro=True)


def barra_cierre():
    return seccion(
        columna(titulo("La inscripción cierra el [día] de [mes] a las [hora] (hora del centro de México)", "h5",
                       color=TX_OSC, alinear="left", tam=15, tam_movil=14, peso=600), tam=50, centro_v=True),
        columna(contador(oscuro=True, ancho=100), tam=33, centro_v=True, ocultar_movil=True),
        columna(boton("Inscribirme", fondo=NEON, color=NOCHE, tam="sm", fuente=15, alinear="right"), tam=17,
                centro_v=True),
        fondo=NOCHE, pad=(10, 20), pad_movil=(10, 12), nota="Barra de cierre (solo si el cierre es real)")


def pie(oscuro=True):
    return seccion(
        imagen(ancho=14, radio=0),
        texto("<p style=\"text-align:center\">[Marca] · ¿Dudas? Escríbenos por WhatsApp o a [correo de soporte]</p>",
              color=MUT_OSC, alinear="center", tam=15),
        texto("<p style=\"text-align:center\"><a href=\"/aviso-de-privacidad/\">Aviso de privacidad</a> · "
              "<a href=\"/terminos-y-condiciones/\">Términos y condiciones</a> · "
              "<a href=\"/politica-de-reembolso/\">Política de reembolso</a></p>",
              color=MUT_OSC, alinear="center", tam=14),
        texto("<p style=\"text-align:center\"><small>Los resultados dependen del trabajo de cada persona y de su "
              "mercado. No prometemos ingresos ni resultados específicos; los casos que mostramos son reales y "
              "excepcionales, no típicos. Este sitio no forma parte de Meta Platforms, Inc. ni de Google, y no está "
              "respaldado por ellos.</small></p>", color=MUT_OSC, alinear="center", tam=13),
        texto("<p style=\"text-align:center\">© [año] [Marca]. Todos los derechos reservados.</p>",
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
        texto("<p style=\"text-align:center\">⏰ Este precio y los bonus terminan el [día] a las [hora] (centro de "
              "México). Solo si es real.</p>", color=TX, alinear="center", tam=14),
        texto("<p style=\"text-align:center\">🔒 Pago 100 % seguro · Tarjeta, OXXO, SPEI, Mercado Pago o PayPal</p>",
              color=MUT, alinear="center", tam=14),
        imagen(ancho=45, radio=0, pie="Logos de los medios de pago que de verdad aceptas"),
        fondo=BLANCO, pad=caja(36, 32), pad_movil=caja(24, 18), radio=18, sombra=True)


# =============================================================== 01 · Página de venta completa

def venta_completa():
    S = []
    S.append(barra_cierre())

    # 1. Cabecera: promesa, video y un solo llamado.
    S.append(seccion(
        etiqueta("Para [tipo de cliente 1], [tipo 2] y [tipo 3]", color=NEON),
        titulo(f"[Logra el resultado concreto] <span style=\"color:{NEON}\">sin [el obstáculo principal]</span> en "
               "[N días] o menos", "h1", color=BLANCO, tam=52, tam_movil=32),
        texto("<p style=\"text-align:center\">[La promesa en una frase, con el nombre de tu método: qué cambia, en "
              "cuánto tiempo y por qué esta vez sí funciona.] Sirve sin importar [tu giro o tu nivel de "
              "experiencia].</p>", color=TX_OSC, alinear="center", tam=20),
        espacio(10), video("Video de venta: 3 a 5 minutos"), espacio(10),
        boton("¡Quiero empezar!", fondo=NEON, color=NOCHE, fuente=20),
        texto("<p style=\"text-align:center\">Solo abrimos [una vez al año / hasta el día X]: la fecha está en la "
              "barra de arriba.</p>", color=MUT_OSC, alinear="center", tam=15),
        degradado=(NOCHE, "#0B2A45", 160), pad=(80, 20), ancho=900, nota="1 · Cabecera: promesa, video y llamado"))

    # 2. El problema: una advertencia y el contraste viejo/nuevo.
    S.append(seccion(
        columna(
            etiqueta("Antes de seguir, una advertencia"),
            titulo("[El problema que nadie les dice: por qué lo que hacen hoy ya no alcanza]"),
            texto("<p>[Describe la situación del cliente con sus palabras: qué intenta, qué le sale mal y qué le "
                  "cuesta. Que se reconozca.]</p>", alinear="center"),
            interior(
                columna(titulo("Lo que hace casi todos", "h3", color=TX),
                        lista(["[Práctica vieja 1]", "[Práctica vieja 2]", "[Práctica vieja 3]"],
                              color_icono="#D64545", ic="fas fa-times-circle"),
                        tam=50, fondo=NIEBLA, pad=caja(28), radio=14, borde=BD),
                columna(titulo("Lo que funciona hoy", "h3", color=TX),
                        lista(["[Práctica nueva 1]", "[Práctica nueva 2]", "[Práctica nueva 3]"]),
                        tam=50, fondo=BLANCO, pad=caja(28), radio=14, borde=JADE, sombra=True),
                margen_sup=24),
            texto("<p style=\"text-align:center\"><strong>[Frase puente: por eso existe este programa.]</strong></p>",
                  color=TX, alinear="center")),
        fondo=BLANCO, ancho=1000, nota="2 · El problema y el contraste"))

    # 2b. El resultado deseado, en concreto («¿Te imaginas…?»).
    S.append(seccion(
        etiqueta("Ahora imagina lo contrario"),
        titulo("¿Te imaginas lograr esto en [N días] o menos?"),
        interior(*[columna(caja_icono("fas fa-check", f"[Resultado deseado {n}]",
                                      "[Cómo se ve en su día a día, con sus palabras]", alinear="center",
                                      color=JADE), tam=25, fondo=BLANCO, pad=caja(26), radio=16) for n in (1, 2, 3, 4)],
                 margen_sup=10),
        texto("<p style=\"text-align:center\">[Cuántas personas ya lo lograron con el programa: una cifra real.]</p>",
              alinear="center"),
        fondo=NIEBLA, nota="2b · ¿Te imaginas…? El resultado deseado"))

    # 3. Quién lo enseña.
    S.append(seccion(
        columna(imagen(), tam=40, centro_v=True),
        columna(
            etiqueta("Quién te acompaña", alinear="left"),
            titulo("Hola, soy [nombre]", alinear="left"),
            texto("<p>[Tu historia en tres o cuatro frases, con la fórmula «[esto] me llevó de [un punto de partida "
                  "pequeño] a [un resultado con cifra real]». Sin adornos: la gente compra a personas.]</p>"),
            lista(["[Credencial verificable 1: años, proyectos, clientes]",
                   "[Credencial verificable 2: un logro con cifra real]",
                   "[Credencial verificable 3: dónde te han visto o publicado]"], ic="fas fa-award"),
            tam=60, centro_v=True),
        fondo=BLANCO, nota="3 · Quién lo enseña"))

    # 4. Autoridad: quiénes ya confiaron.
    S.append(seccion(
        columna(
            etiqueta("Han confiado en nosotros"),
            titulo("[Empresas, marcas o personas reconocidas que ya trabajaron contigo]", "h3"),
            interior(*[columna(imagen(ancho=70, radio=0), tam=20) for _ in range(5)], margen_sup=10),
            interior(*[columna(cita("[Lo que dijo, con sus palabras y con su permiso.]",
                                    "[Nombre real], [cargo o empresa]"), tam=33, fondo=BLANCO, pad=caja(24),
                               radio=14, borde=BD) for _ in range(3)], margen_sup=24)),
        fondo=NIEBLA, nota="4 · Autoridad: logos y citas reales"))

    # 5. La gran idea, con su enemigo.
    S.append(seccion(
        columna(
            etiqueta("La gran idea", color=NEON),
            titulo("[Tu idea central en una frase: la creencia nueva que lo cambia todo]", color=BLANCO),
            texto("<p style=\"text-align:center\">[Explícala con un ejemplo que el lector ya conoce: una marca o una "
                  "industria que pasó de lo viejo a lo nuevo y lo que les pasó a quienes no cambiaron.]</p>",
                  color=TX_OSC, alinear="center"),
            interior(
                columna(caja_icono("fas fa-history", "Antes", "[Cómo se hacía y su costo]", color=NOCHE,
                                   color_desc="#DCEBFA", **OSC), tam=33),
                columna(caja_icono("fas fa-bolt", "Ahora", "[Qué cambió en el mercado]", color=AZUL,
                                   color_desc="#DCEBFA", **OSC), tam=33),
                columna(caja_icono("fas fa-route", "Por eso", "[Qué significa para el lector]", color=JADE,
                                   color_desc="#DCEBFA", **OSC), tam=33),
                margen_sup=24)),
        fondo=PROFUNDO, nota="5 · La gran idea"))

    # 6. Por qué ahora.
    S.append(seccion(
        columna(
            etiqueta("Por qué ahora", alinear="left"),
            titulo("[Por qué este es el mejor momento para [tipo de cliente]]", alinear="left"),
            texto("<p>[Un dato real del mercado, con su fuente, y lo que abre para el lector. Si no hay dato, cuenta "
                  "un cambio que el lector ya vive.]</p>"),
            tam=55, centro_v=True),
        columna(imagen(pie="Gráfica o captura con la fuente del dato"), tam=45, centro_v=True),
        fondo=BLANCO, nota="6 · Por qué ahora"))

    # 7. Un argumento para cada tipo de cliente.
    avatares = []
    for n in (1, 2, 3):
        cuerpo = columna(
            etiqueta(f"Si eres [tipo de cliente {n}]", alinear="left"),
            titulo(f"[Por qué esto es para el cliente {n}: su situación y lo que gana]", "h3", alinear="left",
                   tam=28),
            texto(f"<p>[Su dolor concreto y cómo lo resuelve el programa, en sus palabras.]</p>"),
            lista([f"[Beneficio para el cliente {n}]", f"[Otro beneficio para el cliente {n}]",
                   f"[Un caso real parecido al cliente {n}]"]),
            cita("[Testimonio real de alguien como él o ella.]", "[Nombre real], [qué hace]"),
            tam=60, centro_v=True)
        foto = columna(imagen(), tam=40, centro_v=True)
        avatares.append(seccion(*((foto, cuerpo) if n % 2 else (cuerpo, foto)),
                                fondo=NIEBLA if n % 2 else BLANCO, pad=(60, 20),
                                nota=f"7.{n} · Argumento para el cliente {n}"))
    S.append(seccion(
        etiqueta("¿Esto es para ti?"),
        titulo("Esto funciona si eres uno de estos tres"),
        interior(*[columna(caja_imagen(f"[Tipo de cliente {n}]", f"[Su situación en una frase]"), tam=33)
                   for n in (1, 2, 3)], margen_sup=10),
        fondo=BLANCO, pad=(80, 20, ), nota="7 · Los tres tipos de cliente"))
    S.extend(avatares)

    # 8. El obstáculo y el descubrimiento.
    S.append(seccion(
        columna(
            etiqueta("¿Cuál es la trampa?"),
            titulo("[El obstáculo que hace fracasar a la mayoría]"),
            interior(
                columna(caja_icono("fas fa-exclamation-triangle", "[Obstáculo 1]", "[Por qué pasa y qué cuesta]",
                                   color="#D9822B"), tam=50),
                columna(caja_icono("fas fa-exclamation-triangle", "[Obstáculo 2]", "[Por qué pasa y qué cuesta]",
                                   color="#D9822B"), tam=50), margen_sup=10),
            espacio(28),
            titulo("Lo que descubrimos", "h3"),
            texto("<p style=\"text-align:center\">[La diferencia entre quienes fracasan y quienes lo logran. Este es "
                  "el puente hacia tu método.]</p>", alinear="center")),
        fondo=BLANCO, ancho=1000, nota="8 · Obstáculo y descubrimiento"))

    # 9. La prueba del método: el caso insignia, con cifras reales.
    S.append(seccion(
        columna(
            etiqueta("Lo probamos y funcionó", color=NEON, alinear="left"),
            titulo("[El caso insignia: quién, qué hizo y qué logró]", color=BLANCO, alinear="left"),
            texto("<p>[Cuéntalo como historia: de dónde partió, qué aplicó y qué pasó. Cifras reales y "
                  "comprobables.]</p>", color=TX_OSC),
            interior(*[columna(titulo(f"<span style=\"color:{NEON}\">[cifra {n}]</span>", "h2", alinear="left",
                                      color=BLANCO, tam=40),
                               texto(f"<p>[qué mide la cifra {n}]</p>", color=MUT_OSC, tam=15), tam=33)
                       for n in (1, 2, 3)]),
            tam=55, centro_v=True),
        columna(video("Video del caso insignia"), tam=45, centro_v=True),
        fondo=NOCHE, nota="9 · El caso insignia"))

    # 10. Resultados: video-testimonios titulados por su resultado.
    tarjetas = []
    for fila in range(2):
        tarjetas.append(interior(*[columna(
            video("Video-testimonio real"),
            titulo(f"De [antes] a [después] en [tiempo]", "h4", alinear="left", margen=4),
            texto("<p>[Nombre real] · [qué hace]</p>", tam=15), tam=33) for _ in range(3)],
            margen_sup=0 if fila == 0 else 24))
    S.append(seccion(
        etiqueta("Resultados reales"),
        titulo("Personas reales que ya lo lograron"),
        texto("<p style=\"text-align:center\">Cada título dice el resultado, no el nombre: es lo que el lector busca. "
              "Solo testimonios reales y con permiso.</p>", alinear="center", tam=15),
        *tarjetas,
        fondo=NIEBLA, nota="10 · Muro de resultados"))

    # 11. El método.
    S.append(seccion(
        columna(
            etiqueta("El método", alinear="left"),
            titulo("Te presento [nombre del método]", alinear="left"),
            texto("<p>[Qué es en una frase y por qué es distinto.]</p>"),
            *[caja_icono("fas fa-check", f"Paso {n}: [nombre]", "[Qué logras en este paso]", posicion="left",
                         color=AZUL) for n in (1, 2, 3, 4)],
            tam=55, centro_v=True),
        columna(imagen(pie="Esquema del método o maqueta en laptop"), tam=45, centro_v=True),
        fondo=BLANCO, nota="11 · El método"))

    # 12. Qué es exactamente.
    S.append(seccion(
        columna(imagen(pie="Maqueta de todo lo que incluye"), tam=45, centro_v=True),
        columna(
            etiqueta("Qué es exactamente", color=NEON, alinear="left"),
            titulo("[Nombre del producto] es [definición en una frase]", color=BLANCO, alinear="left"),
            lista(["[Formato: lecciones en video cortas, con transcripción]",
                   "[Ritmo: un módulo por semana / acceso a todo desde el día 1]",
                   "[Acompañamiento: sesiones en vivo, comunidad, soporte]",
                   "[Desde dónde: computadora o celular, a tu ritmo]"], color_icono=NEON, color_texto=TX_OSC),
            tam=55, centro_v=True),
        fondo=NOCHE, nota="12 · Qué es exactamente"))

    # 12b. Por qué este y no otro: la comparación.
    filas = [("[9 o 10 meses]", "[90 días]"), ("Casos de libro", "Casos reales, de clientes"),
             ("Acceso por tiempo limitado", "[Acceso de por vida / N meses]"),
             ("Solo teoría", "Plantillas y un plan de acción semana a semana"),
             ("Estudias solo", "[Comunidad, sesiones y soporte]")]
    S.append(seccion(
        etiqueta("Por qué es diferente"),
        titulo("¿Por qué [producto] y no otro curso o una maestría?"),
        interior(
            columna(titulo("Otros cursos o una maestría", "h3", color=MUT),
                    lista([a for a, _ in filas], color_icono="#D64545", ic="fas fa-times-circle"),
                    tam=50, fondo=NIEBLA, pad=caja(28), radio=16),
            columna(titulo("[Nombre del producto]", "h3", color=TX),
                    lista([b for _, b in filas]),
                    tam=50, fondo=BLANCO, pad=caja(28), radio=16, borde=JADE, sombra=True),
            margen_sup=10),
        fondo=BLANCO, ancho=1000, nota="12b · Comparación contra otras opciones"))

    # 13. Temario.
    modulos = []
    for fila in range(3):
        modulos.append(interior(*[columna(
            etiqueta(f"Módulo {fila * 2 + k}", alinear="left"),
            titulo(f"[Nombre del módulo {fila * 2 + k}]", "h3", alinear="left"),
            lista(["<strong>[Lo que lograrás]</strong>: [en una línea]", "<strong>[Lo que aprenderás]</strong>: "
                   "[en una línea]", "<strong>[Lo que te llevas]</strong>: [plantilla, guía o ejercicio]"], tam=16),
            texto(f"<p><em>[Frase que abre el módulo {fila * 2 + k + 1}]</em></p>" if fila * 2 + k < 6 else
                  "<p><em>[Frase que lleva a los bonus]</em></p>", color=AZUL, tam=15),
            tam=50, fondo=BLANCO, pad=caja(30), radio=16, borde=BD) for k in (1, 2)],
            margen_sup=0 if fila == 0 else 24))
    S.append(seccion(
        etiqueta("Temario completo"),
        titulo("Esto es lo que vas a aprender, módulo por módulo"),
        texto("<p style=\"text-align:center\">[Una frase de por qué el orden importa.]</p>", alinear="center"),
        *modulos,
        espacio(16),
        boton("Quiero ver todo lo que incluye", url="#oferta", fuente=17, tam="md"),
        fondo=NIEBLA, nota="13 · Temario (con un botón a la oferta)"))

    # 14. Bonus, en zigzag, cada uno con su valor y la duda que resuelve.
    bonus = [seccion(
        etiqueta("Además, te llevas"),
        titulo("Bonus que resuelven lo que normalmente frena"),
        fondo=BLANCO, pad=(80, 20, ), nota="14 · Bonus: título")]
    for n in (1, 2, 3, 4):
        foto = columna(imagen(pie=f"Maqueta del bonus {n}"), tam=40, centro_v=True)
        cuerpo = columna(
            etiqueta(f"Bonus #{n}", alinear="left", color=JADE),
            titulo(f"[Nombre del bonus {n}: «[cantidad] [recurso] para [tu X]»]", "h3", alinear="left", tam=28),
            texto(f"<p>[Qué es y qué duda resuelve: «¿y si no tengo tiempo?», «¿y si no sé de tecnología?»…]</p>"),
            texto(f"<p>Valor: <del>$[X] MXN</del> · <strong style=\"color:{JADE}\">Incluido</strong></p>", tam=17,
                  color=TX),
            tam=60, centro_v=True)
        bonus.append(seccion(*((foto, cuerpo) if n % 2 else (cuerpo, foto)), fondo=BLANCO, pad=(30, 20),
                             nota=f"14.{n} · Bonus {n}"))
    bonus.append(seccion(
        texto(f"<p style=\"text-align:center\">Valor total de los bonus: <del>$[suma real de los bonus] MXN</del> · "
              f"<strong style=\"color:{JADE}\">hoy, incluidos</strong></p>", alinear="center", tam=20, color=TX),
        fondo=BLANCO, pad=(10, 20), pad_movil=(10, 16), nota="14.5 · Valor total de los bonus"))
    S.extend(bonus)

    # 15. Acompañamiento.
    S.append(seccion(
        etiqueta("No vas solo"),
        titulo("Te acompañamos todo el camino"),
        interior(
            columna(caja_icono("fas fa-video", "[Sesiones en vivo]", "[Cuándo y cómo: frecuencia y duración]",
                               alinear="center"), tam=33, fondo=BLANCO, pad=caja(28), radio=16),
            columna(caja_icono("fas fa-users", "[Comunidad]", "[Dónde y para qué: preguntas, avances, red]",
                               alinear="center", color=JADE), tam=33, fondo=BLANCO, pad=caja(28), radio=16),
            columna(caja_icono("fab fa-whatsapp", "[Soporte]", "[Por dónde y en cuánto tiempo responden]",
                               alinear="center", lib="fa-brands"), tam=33, fondo=BLANCO, pad=caja(28), radio=16),
            margen_sup=10),
        fondo=NIEBLA, nota="15 · Acompañamiento"))

    # 16. Para quién es y para quién no.
    S.append(seccion(
        columna(titulo("Es para ti si…", "h3", alinear="left"),
                lista(["[Situación que encaja 1]", "[Situación que encaja 2]", "[Situación que encaja 3]",
                       "[Estás dispuesto a dedicarle [N] horas por semana]"]),
                tam=50, fondo=NIEBLA, pad=caja(32), radio=16),
        columna(titulo("No es para ti si…", "h3", alinear="left"),
                lista(["Buscas resultados sin trabajar", "[Situación que no encaja 2]", "[Situación que no encaja 3]",
                       "Esperas una fórmula mágica"], color_icono="#D64545", ic="fas fa-times-circle"),
                tam=50, fondo=NIEBLA, pad=caja(32), radio=16),
        fondo=BLANCO, nota="16 · Para quién es y para quién no"))
    S.append(seccion(
        texto("<p style=\"text-align:center\"><strong>¿No estás seguro de que sea para ti?</strong> Escríbenos y te "
              "decimos con honestidad si te sirve o no.</p>", alinear="center", color=TX),
        boton_whatsapp("Pregúntanos por WhatsApp", "Hola, quiero saber si [producto] es para mí"),
        fondo=BLANCO, pad=(0, 20, ), pad_movil=(0, 16), nota="16b · ¿Dudas si es para ti? WhatsApp"))

    # 17. Objeciones.
    objeciones = ["«No tengo tiempo»", "«Soy [perfil], ¿me servirá?»", "«No sé de tecnología»",
                  "«Ya tengo [experiencia o negocio], ¿qué me aporta?»", "«Puedo aprenderlo gratis en internet»",
                  "«¿Vale lo que cuesta?»"]
    filas_obj = []
    for i in range(0, 6, 2):
        filas_obj.append(interior(*[columna(
            titulo(objeciones[i + k], "h3", alinear="left", color=AZUL),
            texto("<p>[La respuesta honesta, con una prueba. Mejor si la da un alumno con ese perfil, en un audio o "
                  "video corto (bloque G de la plantilla 08).]</p>"),
            tam=50, fondo=BLANCO, pad=caja(28), radio=16) for k in (0, 1)], margen_sup=0 if i == 0 else 20))
    S.append(seccion(
        etiqueta("¿Funcionará para mí?"),
        titulo("Las dudas que seguro tienes, respondidas"),
        *filas_obj,
        fondo=NIEBLA, nota="17 · Objeciones"))

    # 18. Más resultados: carrusel y capturas.
    S.append(seccion(
        etiqueta("Más resultados"),
        titulo("Lo que dicen quienes ya están dentro"),
        carrusel_testimonios([("[Testimonio real, con permiso: qué logró y en cuánto tiempo.]", "[Nombre real]",
                               "[Qué hace]")] * 6),
        interior(*[columna(imagen(pie="Captura real de un mensaje o resultado"), tam=33) for _ in range(3)],
                 margen_sup=24),
        fondo=BLANCO, nota="18 · Carrusel y capturas"))

    # 19. Cuánto vale para ti.
    S.append(seccion(
        etiqueta("Piénsalo así", color=NEON),
        titulo("¿Cuánto vale para ti [el resultado]?", color=BLANCO),
        interior(*[columna(caja_icono(i, t, d, alinear="center", **OSC), tam=33, fondo=CARD_OSC, pad=caja(28),
                           radio=16)
                   for i, t, d in (("fas fa-user", "Para ti", "[Qué cambia en tu día a día]"),
                                   ("fas fa-handshake", "Para tus clientes", "[Qué reciben ellos]"),
                                   ("fas fa-seedling", "Para tu futuro", "[Qué construyes a largo plazo]"))],
                 margen_sup=10),
        texto("<p style=\"text-align:center\">[El cálculo honesto: si con lo que aprendes consigues [un cliente de "
              "$X al mes], en un año son $[Y]. Es un ejemplo, no una promesa: el resultado depende de cada "
              "persona.]</p>", color="#DCEBFA", alinear="center", tam=17),
        fondo=PROFUNDO, nota="19 · Cuánto vale (con el cálculo)"))

    # 20. Las alternativas.
    S.append(seccion(
        etiqueta("Tienes opciones"),
        titulo("Pero solo una tiene sentido"),
        interior(
            columna(caja_icono("fas fa-university", "[Alternativa cara]", "[Cuánto cuesta y cuánto tarda: un "
                               "diplomado, una maestría]", color=MUT, alinear="center"), tam=25, fondo=NIEBLA,
                    pad=caja(24), radio=16),
            columna(caja_icono("fas fa-user-tie", "[Contratar a alguien]", "[Cuánto cuesta y qué no aprendes]",
                               color=MUT, alinear="center"), tam=25, fondo=NIEBLA, pad=caja(24), radio=16),
            columna(caja_icono("fas fa-random", "[Hacerlo solo]", "[Cuánto tiempo y errores cuesta]", color=MUT,
                               alinear="center"), tam=25, fondo=NIEBLA, pad=caja(24), radio=16),
            columna(caja_icono("fas fa-star", "[Nombre del producto]", "[Por qué es la mejor relación de costo, "
                               "tiempo y acompañamiento]", color=JADE, alinear="center"), tam=25, fondo=BLANCO,
                    pad=caja(24), radio=16, borde=JADE, sombra=True),
            margen_sup=10),
        fondo=BLANCO, nota="20 · Alternativas"))

    # 21. La oferta: el resumen de todo y el precio.
    S.append(seccion(
        columna(
            etiqueta("Inscríbete hoy", color=NEON),
            titulo("Esto es todo lo que te llevas", color=BLANCO),
            interior(tarjeta_stack(), espacio="no"),
            espacio(20),
            boton_whatsapp("¿Dudas? Pregúntanos por WhatsApp", oscuro=True)),
        degradado=(NOCHE, "#0B2A45", 180), ancho=760, ident="oferta",
        nota="21 · Oferta: resumen y precio (ancla #oferta)"))

    # 22. Garantía.
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
        fondo=BLANCO, ancho=1000, nota="22 · Garantía"))

    # 22b. La decisión enmarcada: los caminos que tiene, con botón en el recomendado.
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
        fondo=NIEBLA, nota="22b · Los caminos, con botón en el recomendado"))

    # 23. Urgencia real.
    S.append(seccion(
        etiqueta("Aviso"),
        titulo("La inscripción cierra el [día] de [mes] a las [hora] (centro de México)"),
        texto("<p style=\"text-align:center\">[Por qué cierra: grupos, fechas de inicio, cupo del "
              "acompañamiento.] Después de esa hora el botón deja de funcionar. Sin excepciones.</p>",
              alinear="center"),
        contador(ancho=70),
        fondo=BLANCO, ancho=900, nota="23 · Urgencia: solo si el cierre es real"))

    # 24. Cierre.
    S.append(seccion(
        columna(
            titulo("Este es tu momento", color=BLANCO),
            texto("<p style=\"text-align:center\">[Resume la transformación en dos frases y recuerda lo que pierde si "
                  "no actúa.]</p>", color=TX_OSC, alinear="center"),
            lista(["¿Quieres [resultado 1]?", "¿Quieres [resultado 2]?", "¿Quieres [resultado 3]?",
                   "¿Quieres dejar de [el dolor principal]?"], color_icono=NEON, color_texto=TX_OSC,
                  ic="fas fa-question-circle"),
            espacio(16),
            interior(tarjeta_stack(), espacio="no"),
            texto("<p style=\"text-align:center\">[Firma: tu nombre y una frase personal]</p>", color=MUT_OSC,
                  alinear="center", tam=16)),
        fondo=NOCHE, ancho=760, nota="24 · Cierre con el resumen repetido"))

    # 25. Preguntas frecuentes del producto y del pago.
    S.append(seccion(
        etiqueta("Preguntas frecuentes"),
        titulo("¿Tienes dudas?"),
        acordeon([
            ("¿Cuándo empiezo?", "[Al pagar recibes el acceso por correo / el [fecha] empieza el grupo].")
            , ("¿Cuánto tiempo necesito por semana?", "[N] horas. [Cómo está pensado para alguien ocupado].")
            , ("¿Por cuánto tiempo tengo acceso?", "[De por vida / un año / mientras dure la membresía].")
            , ("¿En qué moneda pago y qué medios aceptan?", "En pesos mexicanos. Tarjeta de crédito o débito, OXXO, "
               "SPEI, Mercado Pago o PayPal. [Ajusta a los que aceptas].")
            , ("¿Puedo pagar a meses?", "[Sí, [N] meses sin intereses con tarjetas participantes / No].")
            , ("¿Me dan factura?", "Sí. Al pagar nos compartes tus datos fiscales y te enviamos tu CFDI.")
            , ("¿Desde qué dispositivo lo veo?", "Computadora, tableta o celular. El acceso es personal.")
            , ("¿Qué pasa si no me funciona?", "[La garantía, en una frase, con el enlace a la política].")
            , ("¿Tendré soporte?", "[Por dónde, en qué horario y en cuánto tiempo responden].")
            , ("¿Y si tengo otra duda?", "Escríbenos por WhatsApp y te respondemos.")]),
        interior(columna(avatar(alinear="center"), tam=20, centro_v=True),
                 columna(texto("<p><strong>¿Te quedó una duda?</strong> Te responde [nombre real de quien atiende], "
                               "[horario].</p>", tam=16), boton_whatsapp(alinear="left"), tam=80, centro_v=True),
                 margen_sup=24),
        fondo=BLANCO, ancho=860, nota="25 · Preguntas frecuentes y WhatsApp con una cara"))

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
                  ("¿En qué moneda pago?", "En pesos mexicanos: tarjeta, OXXO, SPEI, Mercado Pago o PayPal."),
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
                   ("En vivo por [Zoom / YouTube] · [duración]", "fas fa-video")],
                  color_icono=NEON, color_texto=TX_OSC),
            interior(columna(avatar(), tam=25, centro_v=True),
                     columna(texto("<p><strong>Con [nombre]</strong><br>[quién es, en una línea]</p>", color=TX_OSC,
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
        columna(etiqueta("Quién la imparte", alinear="left"), titulo("[Nombre]", alinear="left"),
                texto("<p>[Su historia en tres frases y por qué sabe de esto.]</p>"),
                lista(["[Credencial verificable 1]", "[Credencial verificable 2]"], ic="fas fa-award"),
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
        boton("Unirme al grupo de WhatsApp", url="https://chat.whatsapp.com/GRUPO", externo=True),
        fondo=BLANCO, nota="2 · Tres pasos"))
    S.append(seccion(
        columna(video("Video corto de bienvenida (opcional)"), tam=50, centro_v=True),
        columna(titulo("Mientras tanto", "h3", alinear="left"),
                texto("<p>[Un contenido corto para calentar: un video, un artículo o una plantilla.]</p>"),
                iconos_sociales(), tam=50, centro_v=True),
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
            texto("<p style=\"text-align:center\">🔒 Pago 100 % seguro. En el siguiente paso pagas con tarjeta, OXXO, "
                  "SPEI, Mercado Pago o PayPal.</p>", alinear="center", tam=14),
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
        acordeon([("¿Es seguro pagar aquí?", "Sí. El pago lo procesa [Mercado Pago / Stripe]; nosotros nunca vemos "
                   "los datos de tu tarjeta."),
                  ("¿Puedo pagar en OXXO o por SPEI?", "Sí. En el siguiente paso eliges cómo pagar."),
                  ("¿Cuándo recibo el acceso?", "[Al confirmarse el pago, por correo]. Con OXXO o SPEI puede tardar "
                   "hasta [N] horas."),
                  ("¿Me dan factura?", "Sí, marca la casilla y te pediremos tus datos fiscales."),
                  ("¿Y si no me funciona?", "Tienes la garantía [nombre] de [N] días.")]),
        interior(columna(avatar(alinear="center"), tam=20, centro_v=True),
                 columna(texto("<p><strong>¿Te quedó una duda?</strong> Escríbenos y te respondemos en minutos, "
                               "[horario].</p>", tam=16), boton_whatsapp(alinear="left"), tam=80, centro_v=True),
                 margen_sup=24),
        fondo=NIEBLA, ancho=860, pad=(48, 20), nota="4 · Dudas de pago y WhatsApp"))
    S.append(seccion(
        titulo("Este precio termina el [día] a las [hora] (centro de México)", "h3"),
        contador(ancho=70),
        fondo=BLANCO, pad=(40, 20), nota="5 · Urgencia: solo si el cierre es real"))
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
                iconos_sociales(), tam=50, centro_v=True),
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


PAGINAS = [
    ("01-pagina-de-venta-completa.json", venta_completa),
    ("02-pagina-de-venta-corta.json", venta_corta),
    ("03-captacion.json", captacion),
    ("04-gracias-por-registrarte.json", gracias_registro),
    ("05-carrito.json", carrito),
    ("06-oferta-unica-despues-de-la-compra.json", oferta_unica),
    ("07-gracias-por-tu-compra.json", gracias_compra),
    ("08-bloques-de-prueba-social.json", bloques_prueba),
]

if __name__ == "__main__":
    salida = sys.argv[1]
    os.makedirs(salida, exist_ok=True)
    for nombre, fn in PAGINAS:
        guardar(os.path.join(salida, nombre), fn())
        print("ok", nombre)
