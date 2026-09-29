# [SPEC] El rediseño de los correos — la plantilla nueva, los de la reserva al brief y los comerciales

> Estado: 🟦 **medida el 29-09; el orden, el 7 y los textos editables, decididos por el owner (`#801`, `#802`)** ·
> Última actualización: 2026-09-29 ·
> Decisiones: `#800` (ahora los correos), `#801` (el 7, las ocasiones y el orden), `#802` (los textos, editables desde el
> panel), `#789` (los correos, del carril del SPA),
> `#793` (las felicitaciones, sin vender) ·
> Carril: **SPA** (banda 790–819). Amplía `correos-desde-canvas.md` (el molde de septiembre sigue siendo suyo).

## §0 · Antes de tocar

- **La regla**: manda el diseño de la instancia (la carpeta `paginas/correos` del zip y su brief `brief-correos-playjump`) y
  el contenido es el del brief, TAL CUAL: «no inventes correos, bloques, textos ni ofertas». Lo del parque (marca, datos,
  precios) va por Blade desde el panel y el catálogo, nunca en el producto (`correos-desde-canvas.md` §2).
- **Empieza por** §1 (el censo HAY/FALTA) → §4 (las tandas) → §7 (lo que espera al owner).
- **Trampas**: (1) el molde es `BrandedMailMessage` y todo componente nace dos veces, html y texto (`#500`→`#508`); (2) el
  modo oscuro, solo en el `<style>` del layout y por color; (3) un comercial lleva su porqué y la baja de un toque, también
  en `List-Unsubscribe` (LSSI 22.1, `#750`), y sale solo con su consentimiento; (4) la marca `jw_e` y el píxel siguen sus
  reglas (`#795`, `#797`); (5) los de la reserva, sin ofertas; (6) «nada que ya no sea verdad»: con la cuenta con contraseña
  (23-09), los textos del 8 del brief están desfasados, y el diseño lo dice.
- **Estado**: 🟦 el orden y el 7, decididos (`#801`); ▶ la R1, la plantilla, con su «al detalle» en §4.1 (roles de color
  que pone la instancia, iconos teñidos en el servidor) → **R1·T los textos editables** desde el panel, en es/en/fr y con
  permiso propio (§4.2, `#802`) → la R2. Nada escrito en código aún.
- **Invariantes**: `RGPD-01` (lo enviado), `RGPD-07`, el consentimiento de marketing; `PAY-14` (se encolan).

## 1. Contexto — medido el 29-09

- **Hoy**: 27 clases de correo (25 notificaciones y 2 `Mailable`), vestidas con el molde del canvas de septiembre
  (`correos-desde-canvas.md`, `#500`→`#508`; el aviso de la víspera, `#717`).
- **El diseño nuevo** (el zip del 24-09 por la tarde, en la carpeta `paginas/correos` de la instancia —la plantilla, los de
  la reserva, los comerciales, el visor y su hoja— y el brief en `uploads`): **nueve de la reserva y siete comerciales**, y
  tres grupos cuyo texto el propio diseño deja abierto (8, 9 y 10). El zip del 27-09 no los tocó (su diferencia, medida).
- **El censo**:

| Nº | En el diseño | Hoy | Estado |
|---|---|---|---|
| 1 | Reservado, entradas | `OrderConfirmation` | HAY, cambia: el QR dentro (hoy, adjunto), «Antes de venir», «Si cambian los planes», calendario y «Cómo llegar» |
| 1b | Reservada, excursión | `OrderConfirmation` | HAY, cambia como el 1 |
| 2 | Fiesta reservada | `OrderConfirmation` y `GuestFormRequest` | HAY, cambia: «Ahora, dos cosas» con sus dos botones |
| 2b | Tres días antes de la fiesta | — | FALTA |
| 3 | Mañana, entradas | `VisitEveNotice` (solo si queda algo) | A MEDIAS: el diseño lo manda siempre, con el QR |
| 4 | Mañana es la fiesta | `VisitEveNotice` | HAY, cambia |
| 5 | El pago no ha salido | `OrderPaymentDeclined` | HAY, cambia |
| 6 | La reserva ha caducado | `OrderExpiredWithoutPayment` | HAY, cambia |
| 6b | Tienes una reserva a medias | — | FALTA (comercial) |
| 7 | ¿Qué tal ayer? | `SurveyInvitation` (la encuesta anónima, `#754`) | CHOCA: el diseño pide la reseña en Google y «Reservar otra vez» |
| 8 | Cuenta y acceso | seis clases y las dos del framework | HAY; su texto nuevo, PENDIENTE en el diseño |
| 9 | Cambios hechos por el parque | siete clases | HAY; el patrón dado, sus textos PENDIENTES |
| 10 | Invitados, extras y autorizaciones | cuatro clases | HAY; los textos, con la iteración de Invitados |
| 11 | El año que viene | — | FALTA (comercial) |
| 12 | El cumple se acerca | `BirthdayComingNotice` (a quien firmó por un invitado, `#750`) | A MEDIAS: el diseño lo amplía a los hijos declarados de quien no ha celebrado aquí |
| 13a · 13b | Hace tiempo que no venís (con niños · a saltar) | — | FALTA (comercial) |
| · | Por ocasiones | — | FALTA, y sin mecanismo: un correo a un público |
| — | Las felicitaciones (TP·3c, `#793`) | — | FALTA, fuera del diseño: su copy, con el owner |
| — | Los del equipo (el enlace de la analítica, Google, la incidencia de pago, el mensaje de contacto) | cuatro clases | fuera del diseño: toman la plantilla |

- **Lo que el brief pide y hoy no hay**: el QR DENTRO del correo (imagen y código dictable; hoy va adjunto y solo en la
  confirmación), «Añadir al calendario» (ningún `.ics`), «Cómo llegar», WhatsApp (solo en la autorización de un menor) y que
  responder llegue al parque (solo el mensaje de contacto lleva `replyTo`).

## 2. Objetivo

- La plantilla nueva en TODOS los correos (también los del equipo). Los de la reserva, con el contenido del brief. Los
  comerciales nuevos, cada uno con su disparador, su consentimiento, su «una vez» y su baja. Las felicitaciones.
- **Éxito**: cada correo contra su mockup (Mailpit y una sonda), su versión de texto con lo mismo, las guardas del molde
  verdes, y un comercial solo a quien consintió (con su prueba y su mutación).
- **Fuera**: el marketing con coste (`#800`); inventar textos; los grupos 8–10 hasta que el diseño tenga sus textos.

## 3. Opciones

- **A (elegida): vestir el molde** (`BrandedMailMessage`) con la plantilla nueva y rehacer el contenido correo a correo. El
  molde ya resuelve lo difícil —tablas, el tema en línea, el modo oscuro, la versión de texto, el remitente, las marcas—.
- **B (descartada): empezar de cero** (otra plantilla u otro lenguaje de correo): una dependencia nueva y rehacer lo resuelto.

## 4. Las tandas (propuesta del agente, vetable)

- **R1 · la plantilla**: la cabecera, el resguardo, el cuerpo, el botón y el pie del diseño, en el molde; los 27 la heredan
  sin cambiar su contenido.
- **R2 · la reserva**: 1, 1b, 2, 3, 4, 5 y 6 con el brief —el QR dentro, el calendario, «Cómo llegar», WhatsApp, responder al
  parque—. **R3**: el 2b.
- **C1 · los comerciales automáticos**: 11, el 12 ampliado, 13a y 13b, 6b; cada uno con su comando, su consentimiento
  (`marketing_opt_in`), su «una vez por persona y periodo» y su baja.
- **C2 · las felicitaciones** (TP·3c): el copy, con el owner antes de construir.
- **C3 · por ocasiones**: mandar un correo a un público —la mayor de todas—: al owner.
- **El 7** y **8–10**: al owner y al diseño.

## 5. Impacto en invariantes

`RGPD-01` (cada envío queda en `email_sends`), el consentimiento de marketing (un comercial sin él no sale; una cuenta
anonimizada, nunca), `RGPD-07` (la analítica de los correos, sin persona), LSSI 22.1 (la baja); `PAY-14` (se encolan).

## 6. Plan de verificación

Las guardas del molde (la versión de texto y el contraste en claro y en oscuro); cada correo en Mailpit contra su mockup y
una sonda por tanda; los comerciales con su prueba de consentimiento y de «una vez», con su mutación; el ojo del owner.

### 4.1 La R1 al detalle — medida el 29-09, antes de codificar (del agente contra el objetivo, vetable al ojo)

- **Medido**: la plantilla del diseño es un motor entero —un documento con `color-scheme`, el adelanto con su relleno, el
  oscuro por CLASE (`prefers-color-scheme` y los `data-ogsc`/`data-ogsb` de Outlook.com) y quince bloques que se pintan dos
  veces, en tablas y en texto: cabecera (una franja de cuatro colores, el logotipo, la foto de las ocasiones, una chapa con
  el hecho y el titular), resguardo (la hoja del calendario, la hora, qué, el importe, el número, sus filas y dos enlaces
  claros; con la fecha de antes tachada cuando cambia), QR, texto, lista (con iconos en su círculo), pasos, sección,
  botón, botones, línea, aviso, motivo, resguardo corto, hueco y pie (la ayuda, dónde, el horario de HOY, teléfono,
  WhatsApp y correo; y en los comerciales, por qué lo recibes y la baja). El aire entre bloques lo pone la plantilla
  (16 px dentro de una zona, 28 entre zonas, 32 antes de un filete): quitar un bloque no deja hueco.
- **El nudo: los colores y las fuentes son de PlayJump** (su tinta, su cian, su naranja, la franja; Archivo, Figtree y DM
  Mono). El molde de hoy toma del panel el logotipo, el nombre y el color del botón (`ThemeSettings::brand()`), y el resto son
  neutros del producto en `brand.css`, porque el correo no lee `var()`. **Propuesta**: ROLES de correo —fondo, texto fuerte,
  texto, apagado, filete, sutil, enlace, acción y su letra, la hoja, los cuatro tonos, la franja, las tres familias—, con su
  valor NEUTRO en el producto y su pareja oscura, que la instancia redefine en una hoja propia de su paquete (la receta de
  `#769`, como la isla con sus `--isla-*`). El producto la lee EN EL SERVIDOR (un lector de propiedades de una sola
  indirección, en caché) y la escribe en línea. Sin hoja, el correo sale con los neutros: ninguna instalación se rompe.
- **Los iconos**: el diseño los pide como PNG del color de su rol. Se guarda cada icono de Lucide (licencia ISC) UNA vez como
  máscara PNG en el producto, generada del SVG, y se tiñe con GD al pedirse (en caché); sin imagen, queda su círculo, como
  pide el diseño. GD está en el contenedor; en producción, no verificado.
- **Por partes**: **R1a** el documento y los bloques con sus roles (los que usan los 27 de hoy); **R1b** los iconos; **R1c**
  los 27 pasan a la plantilla nueva sin cambiar su contenido. Cada una, al ojo en Mailpit, en claro y en oscuro.
- **Compartido (aviso por buzón a plataforma)**: la hoja de correo en el paquete de la instancia y su clave en el manifiesto.

### 4.2 Los textos, editables desde el panel — pregunta del owner (29-09), análisis sin código

> Owner, 29-09: «¿y si hacemos una plantilla general y poder editar los textos con variables desde el panel? ¿cómo lo ves?
> no escribas código». La plantilla general ya es la R1; lo nuevo es EDITAR los textos.

- **Medido**: 254 textos de correo por idioma (762 en es, en y fr), todos en el producto (`lang/*/emails.php`): cambiar una
  coma pide un despliegue. Y el copy del brief es de PlayJump (los calcetines, el polígono, «Kids desde 8 €»): con la receta
  de hoy acabaría DENTRO del producto, contra el principio white-label.
- **Opciones**: (A) como hoy, los textos en el código: el cliente dentro del producto y un despliegue por cada coma; (B)
  plantillas LIBRES, con bloques que se añaden, quitan y ordenan en un editor: la más cara y la que rompe las reglas del
  brief (una acción, el QR, sin ofertas en la reserva, la baja); (C, **la recomendada**) la ESTRUCTURA fija por correo —la
  del diseño: sus bloques, su orden, lo que sale solo si hace falta, lo legal— y los TEXTOS de cada bloque editables, por
  correo e idioma, con las variables que ese correo conoce, su vista previa y «volver al texto de fábrica».
- **Cómo sería la C**: un almacén de textos por correo, bloque e idioma; el de fábrica, neutro y del producto; el del parque,
  en su base de datos (el copy del brief entra ahí, no en el código). Variables entre llaves (`{nombre}`, `{dia}`, `{hora}`,
  `{codigo}`…): cada correo declara las suyas con un ejemplo, y al guardar se rechaza una que ese correo no conoce. Sin HTML
  (a lo sumo negrita y un enlace, con su marca). La vista previa con datos de ejemplo (la de `correos-salientes.md` C1), en
  claro y en oscuro. Rastro de cada cambio, permiso propio y «sin traducir» cuando falta un idioma.
- **Lo que NO se edita**: lo legal (por qué lo recibes y la baja de un comercial); los DATOS (el resguardo, el QR, las listas
  calculadas); y los HECHOS que cambian —plazos, precios, horarios—, que van por variable: un «hasta el viernes» escrito a
  mano deja de ser verdad («nada que ya no sea verdad», regla 5 del brief).
- **Coste y momento**: el almacén, la pantalla y la vista previa, una tanda (como la C1 de los salientes). Cada correo se
  escribe ya contra sus claves en la R2 y la C1, que se iban a rehacer igual: AHORA es el momento más barato; después sería
  reescribirlos dos veces. Y «por ocasiones» sale casi sola: la misma plantilla, textos libres y un público.
- ✅ **`[DECIDIDO owner]` 29-09 (`#802`)**: la C, con **permiso propio** y **los tres idiomas** desde el panel; «de manera
  profesional y robusta, sin chapuzas». Va como tanda propia tras la R1 (**R1·T**: el almacén, la pantalla, la vista previa),
  y la R2 y la C1 se escriben contra sus claves. Empieza en la sesión siguiente.

## 7. Revisión y decisión

- ✅ **`[DECIDIDO owner]` 29-09 (`#801`)**: (1) **el 7**: el correo sigue siendo la encuesta anónima y, al terminarla, la
  página de gracias invita a todos a la reseña en Google, sin filtrar por la nota; **si no hay encuesta activa, el correo
  pide solo la reseña**. (2) **Por ocasiones, después** de lo demás. (3) **El orden, el de §4**: la plantilla → la reserva →
  los comerciales automáticos → las felicitaciones → lo demás.
- ✅ «Alguna otra cosa» (29-09): nada más —los comerciales, la plantilla y editar algunos correos—, y la pregunta de §4.2.
