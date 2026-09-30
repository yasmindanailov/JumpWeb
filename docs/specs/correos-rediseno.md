# [SPEC] El rediseño de los correos — la plantilla nueva, los de la reserva al brief y los comerciales

> Estado: 🟦 **medida el 29-09; el orden, el 7 y los textos editables, decididos por el owner (`#801`, `#802`); la R1·T,
> en `main` el 30-09 con su visto bueno** · Última actualización: 2026-09-30 ·
> Decisiones: `#800` (ahora los correos), `#801` (el 7, las ocasiones y el orden), `#802` (los textos, editables desde el
> panel), `#803` (el botón principal, como el diseño), `#804` (los enlaces legales se quedan en el pie), `#789` (los
> correos, del carril del SPA),
> `#793` (las felicitaciones, sin vender), `#809` (la vista previa enseña también lo que sale solo a veces; tras la A4) ·
> Carril: **SPA** (banda 790–819). Amplía `correos-desde-canvas.md` (el molde de septiembre sigue siendo suyo).

## §0 · Antes de tocar

- **La regla**: manda el diseño de la instancia (la carpeta `paginas/correos` del zip y su brief `brief-correos-playjump`) y
  el contenido es el del brief, TAL CUAL: «no inventes correos, bloques, textos ni ofertas». Lo del parque (marca, datos,
  precios) va por Blade desde el panel y el catálogo, nunca en el producto (`correos-desde-canvas.md` §2).
- **Empieza por** §1 (el censo HAY/FALTA) → §4 (las tandas) → §7 (lo que espera al owner).
- **Trampas**: (1) el molde es `BrandedMailMessage`, que pinta `correo/html` y `correo/texto` (R1a); cada bloque nace dos
  veces y su gemela no escapa; (2) colores y radios, roles de `MailTheme` (nada a mano); el oscuro, por CLASE y con la de
  Outlook.com (`MailThemeTest`); (3) un comercial lleva su porqué y la baja de un toque, también
  en `List-Unsubscribe` (LSSI 22.1, `#750`), y sale solo con su consentimiento; (4) la marca `jw_e` y el píxel siguen sus
  reglas (`#795`, `#797`); (5) los de la reserva, sin ofertas; (6) «nada que ya no sea verdad»: con la cuenta con contraseña
  (23-09), los textos del 8 del brief están desfasados, y el diseño lo dice; (7) todo texto de un correo al cliente es
  editable (R1·T): un bloque nuevo sale en el panel, la negrita solo en párrafos y avisos, y `MailPreviewsTest` lo pinta.
- **Estado**: 🟦 ✅ **R1a la plantilla y R1b los iconos** (29-09, §4.1.2 y §4.1.3; `#803`, `#804`) y ✅ **R1·T los textos
  editables** (30-09, §4.2, `#802`; revisada antes de `main`, §4.2.2), en `main` y aprobadas → R1c → la R2.
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
  máscara PNG en el producto, generada del SVG; sin imagen, queda su círculo, como pide el diseño. ▶ Teñirla con GD quedó
  SUSTITUIDO en §4.1.3 (29-09): máscaras de PALETA que se tiñen reescribiendo su `PLTE`, sin GD en producción.
- **Por partes**: **R1a** el documento y los bloques con sus roles (los que usan los 27 de hoy); **R1b** los iconos; **R1c**
  los 27 pasan a la plantilla nueva sin cambiar su contenido. Cada una, al ojo en Mailpit, en claro y en oscuro.
- **Compartido (aviso por buzón a plataforma)**: la hoja de correo en el paquete de la instancia y su clave en el manifiesto.
  ✅ Contestado (29-09, `28dfdf15`): `hojas.correo` en `instancia.json`, leída con `InstanceViews::hojas('correo')` (ya valida
  ruta y extensión; sin paquete, `[]`), sin contrato nuevo. Plataforma pide del lector: validar cada valor antes de escribirlo
  en línea, caché con clave ruta + `filemtime`, y el oscuro con nombres planos (`--correo-fondo-oscuro`) en un solo `:root`.

#### 4.1.1 La R1a al detalle — medida el 29-09 (del agente contra el objetivo; vetable)

- **Medido, el molde de hoy**: el Markdown de Laravel (`notifications::email` → `x-mail::message` → siete componentes html y
  siete de texto), una hoja (`themes/brand.css`, 11,7 kB) que `CssToInlineStyles` pega en cada etiqueta, y el oscuro en el
  `<style>` del layout **por el COLOR escrito** (`[style*="color:#101418"]`) con `!important`. Los 28 correos del molde le
  hablan por cinco verbos: `hero()` 28 · `line()` 28 · `action()` 21 · `notice()` 6 · `outro()` 3 (dos con un `HtmlString`:
  el libro del pedido y la ficha de producto, marcado propio con colores en línea) · `level('sell')` 2 · `salutation()` 1.
  El resguardo de hoy (`EmailSlip`) son filas rótulo → valor, no los campos del diseño (día, hora, qué, código).
- **Por qué no se re-viste el Markdown**: el aire del diseño depende del bloque SIGUIENTE (16/28/32 px) y su oscuro va por
  CLASE (`.pjm-*` y los `data-ogsc`/`data-ogsb` de Outlook.com); un parser de Markdown no conoce al vecino, y el inliner
  descarta lo que no puede pegar (ya pasó con la `@media`, `#503`).
- **La forma**: `BrandedMailMessage` pinta con **vistas propias** (`->view(['html' => …, 'text' => …])`, que el framework
  ya admite) sobre los MISMOS datos (`introLines`, `hero`, `notice`, `actionText`, `outroLines`…): los 28 heredan sin tocar
  ni uno (eso es la R1c, que luego les da sus bloques). Dentro, un **documento** (el `<head>` del diseño, el adelanto con su
  relleno, el ancho de 600 y lo de Outlook) y una **lista de bloques**, cada uno una pieza Blade con su gemela de texto y el
  aire calculado por el vecino (el `aire()` del diseño). Los bloques de la R1a son **los que usan los 28 hoy**: cabecera
  (chapa y titular), resguardo **de filas** (el de campos llega con la R2, cuando `EmailSlip` los dé), texto, aviso, botón
  y pie. QR, pasos, lista, sección, motivo, resguardo corto, botones y hueco nacen con su primer consumidor (R2, C1): una
  pieza no se declara antes que su consumidor (`#503`).
- **Los roles**: un lector (`MailTheme`, futuro) devuelve los ~30 roles del `tema()` del diseño —fondo, fuerte, cuerpo,
  apagado, filete, sutil, callado y su borde, enlace, punto, acción y su letra, la hoja y su letra, los cuatro tonos (fondo y
  letra), la franja, los tres del icono, las tres familias y los radios—, en claro y en oscuro. Neutros del producto (sin
  franja) y encima los de la hoja `hojas.correo`, leídos con `InstanceViews::hojas('correo')`: solo `:root`, nombres planos
  (`--correo-fuerte`, `--correo-fuerte-oscuro`), UNA indirección `var()` dentro de las mismas hojas, y cada valor validado
  (un color `#rgb`/`#rrggbb`, una familia de caracteres seguros, un radio en px); lo demás fuera con aviso en el log. Caché
  con clave ruta + `filemtime`. Sin hoja, el correo sale neutro y entero.
- **El libro y la ficha de producto**: su marcado propio gana las clases de rol (`pjm-strong`, `pjm-body`, `pjm-muted`,
  `pjm-line`) y sus colores salen del lector: sin eso, en oscuro, quedarían a 1,12 : 1 como ya pasó (`#503`).
- **Decidido aquí, contra el objetivo** (vetable): (1) **ninguna fuente web** —el diseño la trae con `fuentes`; el molde
  vigila que no viaje (`MailThemeTest`): cada apertura avisaría a Google de la IP del cliente, y Gmail la ignora—: las
  familias de la instancia van primero en la pila y detrás las de sistema del diseño; (2) **sin firma ni «si el botón no
  funciona…»**: el diseño no los tiene; el pie nombra al parque y la versión de texto lleva cada enlace; la firma propia
  del único que la escribe (`GuardianAuthorizationSigned`) pasa a una línea.
- ✅ **El botón principal, como el diseño** (`[DECIDIDO owner]` 29-09, `#803`, §7): en el rol de acción, uno por correo;
  `MailMoldTest` pasa de «exactamente dos venden» a «un solo principal por correo».
- **Las guardas**: `MailThemeTest` se reescribe por PROPIEDADES sobre el documento nuevo (ninguna fuente web, la marca del
  negocio y nunca la del producto, radios de la hoja, el oscuro en el HTML enviado, cada texto AA en los dos modos por la
  aritmética, cada bloque con su gemela de texto); `MailMoldTest`, `MailInboxLineTest`, `EmailUtmTest`, `EmailClicksTest`
  y `EmailOpensTest`, sin tocar y en verde; el lector, con su arnés de mutación. En `wip/correos-r1a`, al ojo del owner en
  Mailpit (claro y oscuro, 390 y 1280) con los 28 enviados de golpe.

#### 4.1.2 La R1a, lo construido (29-09; ✅ ojo del owner en Mailpit: «visto bueno, buen trabajo»; falta que plataforma declare la hoja)

- **Piezas**: `MailTheme` (los roles: neutros de las familias del producto —`fiesta.css` y el molde de septiembre—, la hoja
  `hojas.correo` encima, cada valor validado y la memoria por ruta + `filemtime`), `MailDocument` (los bloques desde
  `data()`, el aire por el vecino, la negrita escapada, el marcado con su rol de enlace, el texto de un HTML y el CSS del
  oscuro), `MailPie` (del panel y de `OperatingCalendar::windowFor()`); vistas `correo/html` y `correo/texto` y sus ocho
  bloques, cada uno con su gemela (`{!! !!}`: la versión de texto no se escapa). `BrandedMailMessage` asigna las vistas en
  el constructor (⚠️ `view()` vaciaría `viewData`: la UTM, la marca y el píxel) y redefine `data()`.
- **Decidido al construir**: la ACCIÓN es `ThemeSettings::action() ?? brand()` (el color de acción de la instalación, y si no
  lo declara, su marca); la letra de los tonos lleva clase propia (`pjm-tono-*-t`) —⚠️ en el diseño no la tiene y en el
  oscuro AUTOMÁTICO la chapa conservaba su letra clara sobre el fondo oscuro; su visor no lo enseña porque fuerza el oscuro
  con la paleta—; el `alt` del logotipo, con clase (con imágenes bloqueadas, tinta sobre tinta); el punto de la chapa, en
  píldora (4 px no es de la escala); sin la fila de iconos del pie hasta la R1b. **Los enlaces legales del pie se quedan**,
  discretos, al final (`[DECIDIDO owner]` `#804`). El libro y la ficha de producto, con los roles y sus clases.
- **Guardas**: `MailThemeTest` reescrita (las nueve propiedades de septiembre y tres nuevas: ningún color a mano en las
  plantillas, con una hoja cada color sale de ella, todo texto con color con su clase de oscuro), `MailThemeRolesTest`,
  `MailDocumentTest`, `MailPieTest`. ⚠️⚠️ **Cuatro guardas medían el camino MUERTO** y seguían verdes: pintaban a mano
  `notifications::email` con Markdown (`ThemeColorTest` ×2, `MailInboxLineTest` del texto plano) o buscaban la clase vieja
  de la cabecera; re-apuntadas al camino que se envía (`render()` y `BrandedMailMessage::VISTAS`). Y tres negaciones de la
  ficha (`class="product-card"`) habrían pasado SIEMPRE con la clase de rol añadida: acotadas al comienzo del atributo, con
  su control. `MailMoldTest::test_exactly_two_mails_carry_the_selling_button` se retira (`#803`) por
  `test_no_mail_declares_a_level_the_template_does_not_paint`; `level('sell')`, fuera de los dos correos.
- **Arnés**: `scripts/mutar-correo-r1a.sh`, **39/39 muerden** (~7 min; cada mutante con el filtro de la guarda que lo
  mata) y el árbol, byte a byte como estaba (huella por fichero). Tres trampas de instrumento, pagadas: un filtro que no
  ejecutaba nada (sale ≠ 0 y contaba como «muerde»), un mutante equivalente por un valor trivial (la pareja oscura de prueba
  era el neutro) y ⚠️⚠️ **la guarda estática no veía NINGUNA etiqueta estilada con `ty()`**: el `>` de `$correo->ty(…)`
  cortaba los atributos en `[^>]*`. Lo destapó un superviviente (el `alt` del logotipo sin clase, 37/38): ahora neutraliza
  las expresiones de Blade antes de partir etiquetas, con control (> 15 etiquetas con `ty()`), y el recorrido dinámico
  corre también con el logotipo.
- **Verificado**: suite 6556 / 43847 · Larastan sin errores y sin tocar la línea base · `scripts/banco-correos.php` manda
  los 28 a Mailpit con datos de la base local (28/28) · sonda `storage/app/audit/sonda-correos-r1a.mjs` 28/28 sin desborde a
  390, en claro y en oscuro, con la hoja de PlayJump (las capturas, en `storage/app/audit/correos-r1a/`). ⚠️ En la sonda el
  logotipo sale roto: apunta a `localhost:8081` y el Chromium del contenedor no llega (en Mailpit, desde el navegador, sí).
  Visto así: el libro llevaba su margen de septiembre y el aire se duplicaba (46 px donde van 28); fuera.
- **Falta**: la declaración de la hoja de PlayJump (`publico/instancia/css/correo.css`, empujada al repo de la instancia) en
  su manifiesto (plataforma, por buzón; en local, a mano y sin commitear); la R1c retira `vendor/mail/**` y `vendor/notifications` cuando los dos avisos internos (`Mail/`) pasen a la
  plantilla.

#### 4.1.3 La R1b al detalle — los iconos, medida el 29-09 antes de codificar (del agente contra el objetivo; vetable)

- **Medido, el diseño**: iconos Lucide como PNG del color de su rol (`o.icono(nombre, color)` → `…/correo/i/<color>/<nombre>.png`)
  en el PIE (`map-pin` y `clock` a 18 px en su columna de 28; `phone`, `message-circle` y `mail` a 16 en línea), en la lista
  (en su círculo), en los enlaces claros del resguardo (`map-pin`, `calendar-plus`), en «Responde a este correo» (`reply`) y,
  opcional, en el aviso. Sin imagen, queda la columna o el círculo. Hoy el consumidor es el PIE; lo demás llega con la R2.
- **La forma (sustituye el «teñir con GD» de §4.1)**: máscaras PNG de **PALETA** —256 entradas del mismo color, cada una con
  su alfa en `tRNS`, el índice de cada píxel = su alfa—, generadas UNA vez del SVG de `resources/icons/lucide` (0.544.0, ISC,
  integridad de npm, `#686`) con el Chromium del contenedor y versionadas en `resources/correo/iconos/` con su manifiesto
  (nombre, lado 72 = 3× de 24, sha256). **Teñir = reescribir el trozo `PLTE` y su CRC**: PHP puro, sin GD ni Imagick en
  producción (el «GD sin verificar» desaparece), sin escribir en disco. Medido: `map-pin` a 72 px, 2.190 B y 193 niveles de
  alfa; teñida por `PLTE`, GD la abre y pinta `rgb(14,143,196)`, y el navegador la enseña.
- **La ruta** `GET /correo/i/{v}/{color}/{nombre}.png` (futuro), como el píxel (`#797`): fuera de sesión, cookies y visitante,
  sin limitador (el proxy de Gmail); `color` seis hex y `nombre` del manifiesto, o 404; `v` la versión del manifiesto (rompe
  las cachés cuando cambian las máscaras; una `v` vieja se sirve igual: un correo ya enviado no se rompe). `image/png`. No
  lleva nada de la persona: pedir un icono no es una apertura. ⚠️ **Medido al construir: sale con `no-store`**, porque
  `NoStoreWebResponses` es GLOBAL por `RGPD-04`; no se le abre excepción por unos iconos de 2 kB (el criterio de las fotos de
  las reseñas, `#524`) y el coste va con `PERF-02` —con la `v` en la URL, cachearlos el día que se decida es quitar la cabecera—.
- **El rol** `icono`: UNO para los dos modos (una imagen no cambia con el oscuro), ≥ 3:1 (WCAG 1.4.11) contra el fondo y el
  sutil en claro y en oscuro. Medido: el apagado `#626A72` da 2,84 sobre el sutil oscuro; **`#737B83`**, el más parejo (≥ 3,63
  en los cuatro). PlayJump, su `--icon-accent` `#0E8FC4` (≥ 3,1 en los cuatro). Los del círculo, con la R2.
- **El pie**: la columna de 28 px y los iconos en línea del diseño (`filaI` y `enLinea`), con `alt=""` (el dato va en el texto).
- **Guardas**: las máscaras cuadran con su manifiesto (sha256, lado, paleta con `tRNS`); el teñido cambia SOLO el `PLTE` y GD
  la abre con el color pedido; la ruta da 404 fuera del manifiesto o del formato, sin `Set-Cookie` y con su caché; cada icono
  que pinta la plantilla está en el manifiesto; el rol, a 3:1 en los cuatro fondos. Con su arnés.
- **Compartido**: `routes/web.php` fuera del grupo de la fiesta (aviso previo a plataforma, en el buzón).
- **Lo construido (29-09; ✅ ojo del owner en Mailpit: «buen trabajo, acepto los correos»)**: `scripts/correo-mascaras.mjs` (determinista: dos
  pasadas, el mismo manifiesto byte a byte) y cinco máscaras (`map-pin`, `clock`, `phone`, `message-circle`, `mail`, de 1,7 a
  2,2 kB), `MailIcons` (manifiesto, URL, teñido), `EmailIconController` y `correo.icono`, el rol `icono` (`#737B83`;
  PlayJump `#0E8FC4` en su hoja) y el pie con su columna y sus iconos en línea. `MailIconsTest` (6) y la guarda «con una hoja,
  cada color sale de ella», ampliada al color de la URL de cada icono. Arnés `scripts/mutar-correo-r1b.sh` **12/12**, el
  árbol byte a byte; suite 6562 / 45044; Larastan limpio; sonda 28/28 con sus iconos CARGADOS (0 rotos) en claro y oscuro
  (la sonda reescribe `localhost:8081` al `:80` del contenedor, que es donde la alcanza su Chromium).

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

#### 4.2.1 La R1·T al detalle — medida el 30-09 antes de codificar (del agente contra `#802`; vetable al ojo)

- **¿Se puede configurar ya? NO** (medido, regla `#808`): ningún texto de correo se edita hoy desde el panel —los 279 de
  `emails.php` (y 27 de otros ficheros) viven en `lang/`— ni hay textos en base de datos que reutilizar. Sí hay piezas: las
  pestañas es/en/fr de Mantenimiento, la vista previa INERTE de lo enviado (`EmailSendTable::previewAction`) y el rastro.
- **Medido**: 32 correos (30 notificaciones y 2 `Mailable` del equipo); 27 leen `emails.<correo>.*` y 5 al cliente leen
  `account.*`, `fiesta.*` o `surveys.*` (26 de sus 27 claves, solo de su correo). TODO texto pasa por el traductor (`__()`, y
  la cabecera deriva `.badge`/`.headline`/`.preheader` de su grupo): un ÚNICO punto de enganche. Las líneas se escapan y ya
  admiten `**negrita**` (`MailDocument::rico()`); ningún texto lleva HTML; 2 claves son plurales. Construir un correo a mano
  no deja marcas (`markSend()` solo con el id del envío), pero algún `toMail()` puede escribir: la vista previa, en una
  transacción que se deshace. Cada correo compone con condiciones y datos reales: una vista previa fiel EJECUTA el correo.
- **Diseño** (sin tocar ninguna notificación):
  1. **El dato**: tabla `mail_texts` (`key`, `locale`, `text`, quién y cuándo), única por clave e idioma; modelo
     `Content\Models\MailText`. Ausente = el texto de fábrica del producto (neutro); «volver al de fábrica» borra la fila.
  2. **El enganche**: un cargador que envuelve el de ficheros (`translation.loader`) y superpone las filas a su grupo —
     `{variable}` pasa a `:variable`—, con caché por idioma y grupo que el guardado invalida; sin tabla (migrando), el de
     siempre. Proveedor propio del módulo, `ContentServiceProvider`.
  3. **Qué se edita**: `MailTextCatalog` (junto a los correos) dice por correo su grupo, su categoría y lo que NO se edita:
     lo legal y los datos (`emails.slip`, `emails.pie`, la baja), los plurales y una clave compartida con la web (una guarda
     comprueba que ninguna editable se usa fuera de su correo). El cargador solo superpone claves del catálogo.
  4. **Las reglas al guardar** (`MailTexts::guardar`, con su motivo por idioma): las `{variables}` del texto de fábrica son
     OBLIGATORIAS y no se admiten otras (un plazo o un código no puede desaparecer: «nada que ya no sea verdad»); sin HTML
     (solo `**negrita**`, balanceada, y solo donde el molde la pinta: párrafos y avisos, §4.2.2); llaves balanceadas; ni
     vacío ni más largo que su tope (asunto y chapa, 150; el resto, 1.000). Rastro por cambio (`emails.text_updated` /
     `emails.text_restored`, con el texto: no es de una persona).
  5. **La pantalla**: Ajustes → Sistema → «Textos de los correos» (`MailTexts`), permiso propio `emails.edit_texts` (gestión,
     no del staff por defecto). La lista por categoría con su estado (de fábrica · personalizado · «sin traducir» si un idioma
     cambió y otro no); dentro, un bloque por texto con su nombre humano (Asunto, Chapa, Titular, Adelanto…), el de fábrica
     al lado, las variables con lo que significan, pestañas es/en/fr y «Volver al de fábrica».
  6. **La vista previa**: `MailPreviews` (el banco de la R1a, llevado al producto: el caso real más reciente de cada correo, o
     su motivo si no hay) pinta el correo DE VERDAD con el borrador aplicado solo a ese pintado, en el idioma de la pestaña,
     inerte y en claro/oscuro, dentro de una transacción que se deshace; se audita (`emails.text_previewed`, sin contenido).
     `scripts/banco-correos.php` pasa a usarla (una sola fuente).
- **No cambia**: las 32 notificaciones, el molde, el envío, la cola, las marcas, la API. **Datos del parque**: el copy del
  brief lo escribe el parque en SU panel (white-label: nunca en el producto).
- **Guardas**: el cargador (superpone, respeta el de fábrica, no toca claves fuera del catálogo, caché invalidada), las reglas
  (una prueba por regla, con su control), la pantalla (permiso, guardar, restaurar, rastro, «sin traducir»), la vista previa
  (cada correo del catálogo pinta o dice su motivo; el borrador no se guarda ni se filtra a otro pintado), la guarda de claves
  compartidas y `AdminNavigationTest`; arnés `scripts/mutar-correo-r1t.sh`; sonda en el panel; `MODELO-DATOS` y `PANEL-ADMIN`.

#### 4.2.2 La R1·T, lo construido (30-09; ✅ revisada antes de `main` y con el visto bueno del owner, «visto bueno, a main»)

- **Piezas**: la tabla `mail_texts` y `Content\Models\MailText` (alias `mail_text`); `MailTextRules` (lógica pura: variables
  de fábrica `:code` ↔ del parque `{code}`, topes 150/200/1.000, siete motivos en su orden); `MailTexts` (la ÚNICA puerta de
  escritura, con rastro; caché por VERSIÓN; `conBorrador()`); `MailTextLoader` y `ContentServiceProvider` (`extend` de
  `translation.loader`); `MailTextCatalog` (**29 correos al cliente** por su clave de «Correos enviados», en 5 tipos; **278
  textos por idioma**, de los grupos `emails`, `fiesta`, `account` y `surveys`; el de la ficha de Google, del EQUIPO, fuera y
  declarado); `MailPreviews` (29 constructores); la página `EmailTexts` (`/admin/email-texts`, `?correo=`) y su vista.
- **Decidido al construir** (del agente contra `#802`; vetable al ojo): (1) guardar es **TODO o NADA** en los tres idiomas y
  solo lo que cambió: con un bloque roto no se guarda ninguno y cada uno dice el suyo en su campo; (2) **igual que el de
  fábrica = de fábrica**: se BORRA la fila, o congelaría el correo y el siguiente arreglo del producto no llegaría; (3) una
  fila que HOY no pasaría las reglas de guardar (sus variables ya no casan con el texto de fábrica, o cambió una regla)
  **no se pinta** (sale el de fábrica) y la lista la marca «desfasado»: el cargador y la página usan el mismo filtro,
  `MailTextRules::problema`; (4) con la caché caída, la BASE, nunca el de fábrica en silencio; (5) la vista previa EJECUTA el correo con el
  caso real más reciente a nombre de quien mira; un bloque del borrador que no pasa sus reglas no se pinta; (6) «Volver al de
  fábrica» solo en el bloque que difiere —en todos era ruido, visto en la sonda— y el campo avisa a la página al salir
  (`live(onBlur)`, 160–180 ms medidos) para ese botón y la chapa de su pestaña; (7) quedan fuera el saludo (ya no se pinta),
  lo legal de un comercial, `providers.*`/`actions.*` y los 2 plurales; (8) el siguiente guardar CIERRA el aviso de error
  anterior (`close-notification`): quien arreglaba y guardaba enseguida veía «No se ha guardado nada» encima de «Guardado»
  (visto en la sonda a 390).
- **El permiso en cada acción** (`SEC-04`) lo pone Filament: `CanAuthorizeAccess::hydrateCanAuthorizeAccess()` corre
  `canAccess()` en CADA petición. La página llevaba una copia que el arnés midió EQUIVALENTE (sobrevivía): se retiró, y
  `test_the_permission_is_asked_again_on_every_action` fija la propiedad venga de donde venga.
- **Revisado antes de `main`** (30-09 tarde, con el owner: «arreglar antes»). Medido pintando los 29 correos con una MARCA
  en cada bloque, en es/en/fr (el medidor `medir-r1t-bloques.php`, en la carpeta de auditoría, fuera de git; `CARRIL-SPA`
  §8 (26)), salieron cuatro defectos:
  (1) **la negrita donde el molde no la pinta**: el panel la ofrecía en todos los bloques y en 141 de 278 (asunto, adelanto,
  chapa, titular, botones, títulos y etiquetas) llegaba con sus asteriscos → `MailTextRules::admiteNegrita` (por el último
  tramo, como el tope) y el motivo `sin_negrita`; (2) **tres fragmentos sueltos editables**: el nombre de reserva de un
  producto borrado (`product_fallback`, que va también al ASUNTO) y los importes rotulados del suplemento → fuera, como
  `actions.*`; (3) **«Confirmar un correo nuevo» se previsualizaba sin código**, la versión que ya no sale (`#856`): los 6
  bloques de la real no se veían y el panel repetía cinco nombres → la vista previa con código, y un tramo del catálogo
  puede ser UN texto (del de antes, solo el botón y «si no fuiste tú»); (4) **la copia de una autorización en EN/FR pintaba
  la firma española** (sale en el idioma de la versión firmada) → el caso, la última firma en el idioma de la pestaña; sin
  ella, el motivo lo dice. Y lo que pidió plataforma (un correo de código sin `{code}`) ya lo impedían guardar y el
  cargador: ahora el cargador aplica las reglas ENTERAS de guardar, con prueba sobre las claves reales. Añadido (visto
  bueno): encima de la vista previa, **lo que se lee en la bandeja** (asunto y adelanto), que el cuerpo no enseña.
- **Guardas**: `MailTextsTest` (14), `MailTextCatalogTest` (6), `MailPreviewsTest` (7: la del MOLDE pinta cada correo con
  su marca por bloque —todo bloque sale, y la negrita solo en `<strong>`— con 34 condicionales declarados, cada uno con su
  condición), `EmailTextsPageTest` (15) y `AdminNavigationTest` (28 tarjetas). Arnés `scripts/mutar-correo-r1t.sh`
  (`SOLO=` por nombre), **63/63 muerden** (~20 min; cada mutante con las pruebas de su capa; dos de la negrita, solo con la
  del molde). En la primera pasada (48) hubo tres supervivientes, los tres preguntas al test: una etiqueta que solo abre
  (`<br>`), un borrador con el grupo ya en memoria del traductor y el permiso de arriba.
- **Verificado**: sonda `storage/app/audit/sonda-r1t.mjs` a 1280, 820 y 390 (sin desborde ni errores de consola): la lista
  (29), un correo (45 bloques), la vista previa en claro y oscuro con el borrador dentro e inerte, un texto roto que no se
  guarda y lo dice en su campo, guardar («Personalizado», y en pantalla solo ese aviso) y volver al de fábrica (la fila,
  borrada). La revisión, `sonda-r1t-revision.mjs` a 1280 y 390: la negrita en el asunto, la bandeja, el correo nuevo (8
  bloques, el código) y la firma por idioma. El fixture del ojo, `ojo-r1t.php` (`CARRIL-SPA` §8 (26)).
- ✅ **`[DECIDIDO owner]` 2026-09-30 (`#809`), la R1·T2 — DESPUÉS de la A4**: 33 textos en 12 correos solo salen en
  ciertas situaciones y la vista previa del último caso no los pinta (medido con el medidor, es/en/fr; eran 38 antes de
  retirar los fragmentos): el de la reserva cambiada sale SIN ningún cambio, un correo que no existe (el de verdad siempre
  lleva uno: `OrderItemEditor`). Las DOS piezas: un aviso gris «Solo sale si…» bajo cada uno de esos campos y un desplegable
  «Situación» en la vista previa (el último caso real, como hoy, o una situación del correo con el caso real y un cambio de
  ejemplo, como el «Cubo de refrescos» de los extras; inerte, sin guardar). Descartado: pintarlos todos a la vez (se excluyen
  entre sí) y solo el aviso. Su guarda: `MailPreviewsTest`, cada situación hace salir sus textos (hoy los declara
  `CONDICIONALES_DEL_CASO`). El copy del brief lo escribe el parque en SU panel al desplegar (white-label). La R2 y la C1 se
  escriben contra estas claves.

## 7. Revisión y decisión

- ✅ **`[DECIDIDO owner]` 29-09 (`#801`)**: (1) **el 7**: el correo sigue siendo la encuesta anónima y, al terminarla, la
  página de gracias invita a todos a la reseña en Google, sin filtrar por la nota; **si no hay encuesta activa, el correo
  pide solo la reseña**. (2) **Por ocasiones, después** de lo demás. (3) **El orden, el de §4**: la plantilla → la reserva →
  los comerciales automáticos → las felicitaciones → lo demás.
- ✅ «Alguna otra cosa» (29-09): nada más —los comerciales, la plantilla y editar algunos correos—, y la pregunta de §4.2.
- ✅ **`[DECIDIDO owner]` 29-09 (`#803`): el botón principal, como el diseño.** Uno por correo, en el color de acción (el
  naranja de PlayJump) y la secundaria clara; sustituye en los correos el «naranja solo vende» de `#503` (el cajón y Mi
  cuenta no cambian). Descartado: acción solo en los dos que venden y el resto en el color fuerte.
