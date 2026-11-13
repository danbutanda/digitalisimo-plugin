# Guía de venta del kit de DIGITALÍSIMO

Cómo se arma una página de venta con el kit de DIGITALÍSIMO Elements: las reglas, qué plantilla usar según el caso, el
orden de una página de venta y las fórmulas para llenar sus textos. Es el método de DIGITALÍSIMO (cerebro,
`oficio/ventas-en-linea.md`, commit `06ff42f`, 2026-11-13, sobre todo «El orden de una página de venta»), con las
plantillas del kit v3 genérico; las cifras y los rangos en pesos son una propuesta, sin aprobar todavía.

## Reglas

Lo que no se rompe nunca, ni al llenar una plantilla ni al pedírselo a un asistente:

- **Se crea en borrador; publica una persona.** La herramienta `create-page` sólo crea borradores. Antes de publicar,
  alguien revisa la página completa con la lista «Antes de publicar».
- **Nada se inventa.** Testimonios, nombres, cifras, casos, capturas, logos de clientes, credenciales y fechas sólo si
  son reales y se tienen con permiso. Si no hay dato, el `[placeholder]` se queda y la persona decide: lo llena o
  borra el bloque. Ninguna imagen generada se hace pasar por real.
- **La urgencia, sólo si es cierta.** Fecha y hora de cierre reales (hora del centro de México) y su motivo, en la
  barra de cierre y junto al precio. Sin una fecha de cierre real no hay contador: `create-page` quita la barra de
  cierre y el contador del carrito, y los textos que nombran el cierre quedan pendientes. Un contador que se reinicia es
  mentira.
- **Se promete trabajo, no resultados.** Nada de promesas de ingresos ni de «libertad financiera»; el pie dice que los
  resultados dependen de cada persona y los casos se pueden verificar.
- **Precios en pesos mexicanos.** La plantilla ya escribe `$` y `MXN`: un precio se llena sólo con la cifra
  (`1,997` o `1997.50`), con su valor tachado real y los meses sin intereses sólo si la pasarela los da.
- **El llamado a la acción es por WhatsApp** («Hablar por WhatsApp»), con el número del sitio en sólo dígitos, con la
  clave del país (México: 52).
- **Los enlaces son del propio sitio.** Los botones llevan a rutas del sitio (`/carrito/`, `/pago/`,
  `/gracias-registro/`, `/gracias-compra/`, `/acceso/` y los avisos legales); la página con esa dirección se crea
  aparte. Videos, grupos y redes se pegan en el editor: ningún enlace a otro sitio viaja en un texto.
- **El orden de los bloques no se cambia.** Se puede quitar un bloque que no aplique (`omit_blocks`), nunca
  reordenarlos ni mezclar los de dos plantillas en otro orden. El pie, con los avisos legales, no se quita.
- **Quién está detrás es una persona real.** La de la marca del sitio, con sus datos reales y verificables: nombre,
  historia, credenciales y foto. Si no se tienen, sus placeholders se quedan.
- **La página primero convence y después vende.** Botones de compra en la promesa, después del temario, en la oferta,
  en el camino recomendado y en el cierre; la opción recomendada siempre lleva botón, y un botón secundario nunca es del
  color del de compra.
- **Cada página mide un paso** con su evento (vista, registro, inicio de pago, compra), sin redirecciones entre el
  anuncio y la página.

## Embudo y plantillas

Se vende en escalones: algo gratis para que te conozcan, algo barato para la primera compra, el producto principal y
un complemento en el momento de la compra. La rentabilidad se mide en todo el embudo, no en el primer producto.

El camino del kit: anuncio → 03 Captación → 04 Gracias por registrarte → clase o contenido → 01 Venta completa o
02 Venta corta → 05 Carrito → pago → 06 Oferta única → 07 Gracias por tu compra. La 08 son bloques de prueba social
para copiar a otra página.

Dos cosas deciden la página: **el precio** (a más caro, más larga y más confianza antes de pedir el pago) y **qué tanto
te conoce quien llega** (si viene frío de un anuncio, primero algo gratis; si ya está en la lista o tomó la clase,
basta una página corta).

## Qué landing armar según el caso

| Caso | Plantillas | Lo que no puede faltar | Lo que sobra |
|---|---|---|---|
| Regalar un recurso para juntar contactos | 03 + 04 | Titular con el resultado, el recurso, tres beneficios y un formulario de dos o tres campos; en la de gracias, el siguiente paso | Testimonios largos y temario: menos campos, más registros |
| Clase en vivo o webinar | 03 + 04 | Fecha y hora del centro de México, foto del presentador, qué aprenderá y una pregunta que filtre el perfil; en la de gracias, «agéndalo» y el grupo | Precio y oferta: se venden en la clase |
| Reto de varios días | 03 + 04 | Qué pasa cada día y dónde se hace | Precio y oferta |
| Producto barato (hasta unos 1 000 MXN) que se vende siempre, con anuncios | 02 + 05 + 06 | Video corto, para quién es, qué incluye, valor tachado, botón repetido, el extra de un clic y la oferta después de pagar | Autoridad larga, objeciones, alternativas |
| Regalo con oferta en la gracias | 03 + 04 | Una oferta barata y relacionada con lo que se descargó | — |
| Curso de precio medio (unos 1 000 a 6 000 MXN), casi siempre después de una clase | 02 ampliada o 01 recortada + 05 | Video, temario, bonus, testimonios, garantía, preguntas frecuentes y meses sin intereses | La gran idea y las alternativas |
| Programa caro o lanzamiento con cierre (más de unos 6 000 MXN) | 03 + 04, luego 01 + 05 | Todo: urgencia real con fecha, mensualidades, objeciones, «para quién no es» y muchas pruebas | Nada |
| Servicio de DIGITALÍSIMO (asesoría, hosting, crecimiento digital) | 02 sin carrito | Beneficios, casos reales, precio o «desde» y los llamados del manual: «Agendar asesoría» o «Hablar por WhatsApp» | Carrito, contador, oferta después de pagar |
| Evento de venta de temporada | 02 | La fecha real del evento, el descuento de temporada y, si hay cierre real, la cuenta regresiva | Escasez inventada |
| Lanzamiento a la lista de correo | Copia de la 01 o la 02 | Un mensaje personal y un descuento sólo para suscriptores | — |
| Página sólo para anuncios | Copia de la 01 o la 02 | Su propio botón de pago, para saber qué ventas vinieron de los anuncios | — |
| Después del cierre | La misma página, cambiada | El precio normal o una lista de espera para la próxima apertura | El contador y la oferta de lanzamiento |
| Paquete de la tienda | 02 junto a la ficha del producto | El paquete (producto, accesorios, envío), un extra en el carrito, entrega y devoluciones | Temario y clase |

## El orden de una página de venta

Las landings de venta del kit van en este orden. **Se puede quitar un bloque que no aplique, pero no se cambia el
orden de los que quedan** (`create-page` lo respeta: `omit_blocks` quita bloques enteros y nunca reordena). Cada bloque
responde la pregunta que el comprador se hace en ese momento: si un bloque no responde ninguna, sobra. Es el orden de
la plantilla 01 (claves de `omit_blocks` entre paréntesis); la 02 lleva los bloques que no faltan nunca, en el mismo
orden.

| # | Bloque | La pregunta que responde | Lo que lleva |
|---|---|---|---|
| 1 | Barra de cierre (`barra-de-cierre`) | ¿Hasta cuándo? | Fecha, hora y contador, y un botón. Sólo si el cierre es real |
| 2 | La promesa (`promesa`) | ¿Qué gano? | Antetítulo que nombra al lector («Si eres…»), resultado con plazo, video, el método con nombre «sin importar tu giro o tu nivel» y un botón |
| 3 | El problema (`problema`) | ¿Me entiendes? | Un dato que duele, los dolores en las palabras del cliente y un giro («ya haces esto y no lo sabías») |
| 4 | Quién está detrás, corto (`quien-esta-detras`) | ¿Quién me lo dice? | La historia de la persona detrás de la marca y la oportunidad: lo que antes era de pocos y hoy está al alcance del lector (aquí van la gran idea y el porqué de ahora) |
| 5 | ¿Te imaginas…? (`te-imaginas`) | ¿Cómo sería? | Cuatro resultados deseados, en concreto, y cuántos ya lo lograron |
| 6 | Por qué es distinto (`por-que-es-distinto`) | ¿Por qué esto y no otra cosa? | La comparación contra otros cursos, una maestría o una agencia |
| 7 | Quién está detrás, la autoridad (`autoridad`) | ¿Por qué creerle? | Credenciales verificables, su ventaja, la objeción de fondo desactivada, el plan, y los logos de clientes reales |
| 8 | Funciona para gente como tú (`funciona-para-ti`) | ¿Le funcionó a alguien como yo? | Tres video-testimonios titulados con su resultado (el primero puede ser el caso insignia con cifras) y un botón secundario a más historias |
| 9 | Para quién es (`para-quien-es`) | ¿Es para mí? | «Es para ti si…», los perfiles, WhatsApp para quien no se ve en la lista y «No es para ti si…» |
| 10 | Qué es (`que-es`) | ¿Qué compro? | La definición, la maqueta y los resultados del método |
| 11 | Temario (`temario`) | ¿Qué voy a aprender? | Los módulos, con lo que se logra en cada uno, y el primer botón de compra después de la promesa |
| 12 | Todo lo que incluye (`todo-lo-que-incluye`) | ¿En qué formato? | El formato en iconos: lecciones, plantillas, plan, acceso, comunidad, sesiones, soporte y actualizaciones |
| 13 | Nunca estarás solo (`nunca-estaras-solo`) | ¿Me van a dejar solo? | Comunidad, sesiones o eventos, soporte y tutores |
| 14 | Lo que te llevas al terminar (`al-terminar`) | ¿Qué me queda? | El certificado o el sello, si existe |
| 15 | Bonus (`bonus`) | ¿Qué más me llevo? | Cada uno con su valor tachado y la duda que resuelve, y el valor total tachado; ninguno vale más que el producto |
| 16 | Nuestro compromiso (`compromiso`) | ¿Qué voy a tener? | El repaso de beneficios: «Tendrás un…» |
| 17 | Cuánto vale para ti (`cuanto-vale`) | ¿Lo vale? | La proyección («¿Cómo sería tu negocio si…?») y el cálculo honesto (un ejemplo, no una promesa) |
| 18 | La oferta (`oferta`) | ¿Cuánto cuesta? | Todo lo que se lleva, el valor total tachado, el precio en la unidad más baja, el botón «¡Sí, quiero… ahora!», los medios de pago y la fecha de cierre junto al precio |
| 19 | Garantía y actualización (`garantia`) | ¿Y si no me funciona? | La garantía con nombre y la política de actualización |
| 20 | Los caminos (`caminos`) | ¿Qué hago ahora? | No hacer nada, hacerlo solo, el producto (con su botón) y una opción premium si existe |
| 21 | ¿Te funcionará? (`objeciones`) | ¿Y en mi caso? | Las objeciones dichas como el comprador y contestadas por alumnos con ese perfil, en audio o video |
| 22 | Lo avala la comunidad (`comunidad`) | ¿Es real? | Capturas reales de mensajes y resultados |
| 23 | Preguntas frecuentes (`preguntas-frecuentes`) | Dudas de logística | Pago, moneda, meses, factura, acceso, dispositivos, actualización y soporte |
| 24 | WhatsApp con una cara (`whatsapp`) | ¿Hay alguien? | La foto y el nombre de quien responde, y el botón |
| 25 | Cierre (`cierre`) | El último empujón | «¿Qué esperas?», tres o cuatro «¿Quieres…?», la oferta repetida y la firma |
| 26 | Pie (`pie`) | Lo legal | Avisos legales y el aviso de que no se prometen resultados. No se quita |

**Lo que no falta nunca**, ni en la página de un producto barato: la promesa, quién está detrás, las pruebas, qué es,
qué incluye, la oferta, la garantía, las preguntas frecuentes, WhatsApp y el pie. Lo demás crece con el precio.

**Los botones de compra** van en la promesa, después del temario, en la oferta, en el camino recomendado y en el cierre.
Los demás botones (ver más historias, WhatsApp) son secundarios y de otro color. Nunca se deja sin botón la opción
recomendada.

## Fórmulas de redacción

Se escriben con la voz de DIGITALÍSIMO, sin promesas de ingresos y con cifras que se puedan respaldar.

| Para | Fórmula |
|---|---|
| El antetítulo | «Si eres [perfil 1], [perfil 2] o [perfil 3]…» |
| La promesa | «[Logra X] y [resultado] sin [obstáculo] en [N días] o menos», con el método con nombre |
| La duda del nicho | «Sirve sin importar [tu giro o tu nivel]» |
| La autoridad | «[Esto] me llevó de [un punto de partida pequeño] a [un resultado con cifra real]» |
| Un beneficio | «Tendrás un [sistema, plan o método] para [resultado]»; en el temario, verbos de acción |
| Un testimonio | «De [antes] a [después] en [tiempo]», titulado con el resultado y no con el nombre |
| Una objeción | «Soy [perfil], ¿me servirá?», en la voz del comprador |
| Un bonus | «[Cantidad] [recurso] para [tu X]», con valor tachado e «incluido» |
| El deseo | «¿Cómo sería tu [vida o negocio] si…?», «¿Te imaginas…?» |
| El precio | «Hoy: $X MXN» o «N meses de $Y MXN», después del cálculo de cuánto vale |
| El botón | «¡Sí, quiero [producto] ahora!», con un subtexto que recuerda lo que se lleva |
| Los caminos | «A · [opción] (arriesgado)», «B · … (costoso)», «C · … (inteligente)» |
| La garantía | «Garantía [nombre]: [N] días», cómo funciona y por qué la damos |
| La urgencia | «Cierra el [día] a las [hora] (centro de México) porque [motivo]», sólo si es real |
| El cierre | «¿Qué esperas?» y tres o cuatro «¿Quieres…?» |

## Las páginas, una por una

- **03 Captación.** Titular que filtre a la audiencia, subtítulo, fecha y hora si es en vivo, foto o video del
  presentador, formulario con una pregunta de perfil, beneficios, quién lo imparte, pruebas y un recordatorio con
  botón.
- **04 Gracias por registrarte.** Felicitación y tres pasos: confirmar el correo, agendar la fecha y unirse al grupo. Si
  no hay doble confirmación, aquí va el enlace de descarga.
- **01 Venta completa.** Los 26 bloques de «El orden de una página de venta», en ese orden (32 secciones), con la
  oferta en el ancla `#oferta` y las pruebas de la comunidad en `#historias`.
- **02 Venta corta.** El mismo orden, con los bloques que no faltan nunca: promesa con video, beneficios, quién está
  detrás, para quién es, qué incluye, testimonios, la oferta, la garantía y pocas preguntas. Aquí sí se repite el
  botón.
- **05 Carrito.** El formulario va arriba y junto a la oferta; el pago único marcado de entrada y más barato que a
  plazos; el extra de un clic antes de pagar; la garantía y los medios de pago junto al botón grande; WhatsApp con una
  cara para la última duda; el contador, sólo si el cierre es real.
- **06 Oferta única.** «¡Espera! Tu pedido casi está listo», un video corto, el complemento con su precio de hoy y un
  sí o un no.
- **07 Gracias por tu compra.** Bienvenida, revisar el correo de acceso, entrar a la plataforma, presentarse en la
  comunidad y soporte. Aquí va el evento de compra.

## Antes de publicar

- Todo lo que está entre `[corchetes]` se reemplazó o se borró su bloque (`create-page` dice cuáles quedan).
- WhatsApp con el número real; videos, grupo y redes pegados en el editor.
- Imágenes reales en cada hueco; ninguna generada que se haga pasar por real.
- Testimonios, cifras y casos reales y con permiso; si no hay, se quita el bloque.
- Contador sólo con una fecha de cierre real y el sitio en la zona horaria de la Ciudad de México.
- Precios en pesos, con su valor tachado real y los meses sin intereses sólo si la pasarela los da; sólo los medios
  de pago que de verdad se aceptan.
- Formularios: guardan cada envío, avisan al correo correcto y llevan a la página siguiente; se hace un envío de
  prueba.
- Existen las páginas a las que llevan los botones y las tres de avisos legales, y el pie dice que no se prometen
  resultados.
- Medición: el evento de cada página.
- Tipografía: el kit toma la global del sitio.
