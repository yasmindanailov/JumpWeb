# [SPEC] La Puerta nueva — el mostrador del zip (6), sobre las garantías de la de hoy

> Estado: 🟦 **MEDIDA el 2026-10-02 y con las cuatro respuestas del owner (`#817`)**; sin código: sigue el «al detalle»
> de la P1 · Última actualización: 2026-10-02 · Decisión: `#817` · Carril: 🧩 **SPA** (`#861`, el reparto del
> zip (6), `isla-y-landing-nueva.md` §4.27) · Fuente: `instancias/playjump/diseno/playjump-design-system/paginas/puerta/`
> y la sección «La Puerta · el mostrador (27-09)» de su `readme.md` (manda el mockup, `#767`); referencia, el brief
> COMPLETO `uploads/brief-puerta-playjump (1).md` · La pantalla de hoy y sus garantías: `identidad-qr-puerta.md`.

## §0 · Antes de tocar

- **Manda el mockup** (`#767`): `paginas/puerta/` y la sección «La Puerta · el mostrador (27-09)» de su `readme.md`. El
  brief `(1)` es el completo (el otro no trae la reseña del día). Es una pantalla del PANEL, no de la web.
- **Cambia la vista, no las garantías** (§3): la ficha caduca en el servidor (`$profileExpiresAt` `#[Locked]`, `SEC-04`),
  dos limitadores por empleado, auditoría con hash, escanear y buscar acreditan la visita (`#741`, `#756`), sin apellidos
  de menores (el DTO no tiene campo) y un libro que no cuadra nunca dice «nada pendiente» (D-T3·8). Viven en
  `ValidarRegistro` y `GateProfile`, y 13 pruebas las guardan (§6).
- **Empieza por §4.1** (el censo HAY · FALTA · CHOCA, medido el 02-10) y **§4.2** (lo que decidió el owner y D1–D6).
- **Lo de PlayJump es DATO del panel** (§4.3): la rueda de colores de las pulseras y qué complemento se entrega en la
  puerta. El producto no nombra colores ni calcetines (`PaletaNeutraTest` vale también aquí).
- **El owner ya contestó** (`#817`): «Nueva búsqueda» se queda en el pie, los invitados en una línea sin nombres, la
  encuesta pregunta a pregunta y la reseña del día por palabras. Tandas en §4.4: cada una en `wip/…`, con su «al
  detalle» medido aquí ANTES del código, su arnés, su sonda a 1080 × 810 y el ojo del owner en la tablet.
- No toca el `CRITICAL_RE` (medido). Sí RGPD (lo que ve la cola): `INVARIANTES.md` §3 antes de la P1.

## 1. Contexto y problema (medido el 2026-10-02)

- **El encargo**: el zip (6) del 30-09 (instancia `3b0956d`) trae la Puerta rediseñada, y el reparto del owner (`#861`)
  se la da al SPA después de `LinkIsland` y de la imagen de la invitación (`fiesta-sistema-nuevo.md` §4.18 y §4.19, las
  dos en `main`).
- **La pantalla de hoy**: un componente Livewire a página completa FUERA del shell de Filament (`ValidarRegistro`,
  `layouts.puerta`, la ruta `/{panel}/puerta/validar`), con dos capas: el SEMÁFORO (`registrations.validate`) y la FICHA
  (`puerta.profile`), que compone `Identity\Services\GateProfile` (DTO `GateProfileData`; las reservas, de
  `Booking\Services\GateReservationsReader` con el libro `OrderBook`). Unos 130 KB entre el componente (36), la vista
  (41 + 7 del parcial de la reserva), el servicio (26), el DTO y el lector; el estilo, el bloque `gate-*` del tema del panel
  (`resources/css/filament/admin/theme.css`). Decisiones: `#208`, `#212`, `#217`, `#234`, `#236`, `#320`, `#336`, `#741`,
  `#756`; la encuesta interna, `encuestas.md` §4.2.
- **El diseño**: `paginas/puerta/{puerta.jsx, ficha.jsx, datos.js, puerta.css}` (prefijo `ppu-`; piezas `Button`,
  `Icon` y `Badge` del sistema) y dos tarjetas: `puerta.card.html` (el prototipo vivo, con la mesa de escaneos de prueba)
  y `puerta-estados.card.html` (todos los estados). `datos.js` trae 15 fichas (`FICHAS`) y la mesa (`MESA`).
- **Dónde se separa el mockup del brief** (su readme, 27-09 y 29-09): sin «Nueva búsqueda», sin «Volver al panel», sin
  «Cobrado», sin grupos, sin altura ni edad, sin «−» en las pulseras; los cumpleaños, sin la lista de invitados ni lo que
  se cobra; las pulseras por HORA y no por zona; el veredicto por el DESCARGO («Listos para saltar» · «Falta firmar el
  descargo»; fuera «Pasa…» y «Sin reserva» como veredicto); solo modo claro; «Resultado para», enmascarado.

## 2. Objetivo

- La Puerta se ve y se porta como el mockup en la tablet horizontal (1080 × 810; también 1194 × 834 y 1366 × 1024), y
  funciona en vertical y en escritorio sin diseño propio.
- **Medible**: en 1080 × 810, el veredicto y las tareas sin desplazar en las fichas del caso común (las de `datos.js`
  con reserva); el campo con el foco tras cada búsqueda, cada toque y la vuelta desde otra aplicación; todo lo que se toca,
  ≥ 44 px; nunca el correo ni el teléfono completos en pantalla; el color nunca solo; y las garantías de §0, con sus
  pruebas de hoy en verde.
- **Fuera**: [Vale], [Sellos], [Club] y [Bono], que no existen (ningún modelo de lealtad en `app/Domain/*/Models`, medido;
  `lealtad-jumppoints.md` sin código): no se pintan y no dejan hueco. Las tareas de altura y edad y los grupos (el mockup,
  27-09). El modo oscuro (el mockup: «solo modo claro por ahora»). Una API de escaneo (`identidad-qr-puerta.md` §4.11).

## 3. Opciones consideradas

- **A ✅ La vista nueva sobre el componente de hoy.** `validar.blade.php` se reescribe con el marcado del mockup y una hoja
  `ppu-` propia; el DTO gana lo que falta (§4.1); lo del navegador (el sonido, la doble lectura, apagar la ficha al
  escanear, el bote) va en Alpine, dentro del componente. Ninguna garantía se mueve: siguen en el servidor.
- **B ✗ Portar el prototipo de React a una app de Vue contra una API nueva.** Duplicaría en otra superficie la caducidad,
  los limitadores y la auditoría, que hoy viven en el componente con sus pruebas; y esa API no tiene cliente.
- **C ✗ Solo re-vestir las piezas de Filament.** La banda de color del veredicto, las losetas de 52–60 px y las dos
  columnas con su propio desplazamiento no salen de `x-filament::section` ni de `callout`.

## 4. Diseño elegido

### 4.1 El censo: cada pieza del mockup contra el producto (medido el 02-10)

HAY = el producto ya lo sabe (dónde) · FALTA = hay que construirlo · CHOCA = contradice algo decidido (va a §4.2).

| # | Pieza del mockup | El producto hoy | Estado |
|---|---|---|---|
| 1 | Cabecera: logotipo + «Puerta»; sin «Volver al panel» | `layouts.puerta`; la vista tiene `gate-head__back` (enlace al panel) | HAY; fuera el enlace (§4.2·D4) |
| 2 | Campo «Escanea el QR del cliente, o escribe su correo o su teléfono» + «Buscar» / «Buscando…» | El campo con el cursor dentro, vaciado tras una entrada válida y conservado si es inválida (`#234`) | HAY |
| 3 | «Resultado para an•••@correo.es», enmascarado | `$result['query']` lleva lo tecleado ENTERO (eco del empleado) | FALTA: enmascarar en el servidor (§4.2·D5) |
| 4 | Veredicto en banda de color con la palabra: verde «Listos para saltar», ámbar «Falta firmar el descargo»; sin ficha, grande: rojo «No encontrado» (el único rojo), ámbar «QR caducado», gris «QR no reconocido»; los tres avisos en gris | Los estados de `ValidarRegistro`: `registered_with_waiver` · `registered_no_waiver` · `registered` (descargo apagado, `#216`) · `not_registered` · `card_revoked` · `card_unknown` · `invalid_input` · `rate_limited` · `lookup_limited` | HAY 1:1; FALTA que el veredicto mire también a los menores a cargo (hoy `stateFor()` mira solo al titular; el DTO trae `dependents[].waiver`). Con el descargo apagado, verde siempre |
| 5 | El nombre grande + «Abierta por QR / por búsqueda · Visita registrada» | `holder_name`, `via`, `visit_registered_today` | HAY |
| 6 | Sonido: verde dos notas que suben, ámbar dos iguales, rojo una grave; neutro y gris, sin sonido | — | FALTA (navegador; la tablet lo desbloquea con el primer toque) |
| 7 | Doble lectura: el mismo código en menos de 3 s no reabre ni suena | — | FALTA (navegador) |
| 8 | Al llegar un escaneo, la ficha abierta se apaga hasta que abre la nueva; cada ficha entra con bote y pulso | — | FALTA (navegador) |
| 9 | Pulseras: una fila por zona y color; loseta de 60 px del color con la cifra; «2 KIDS pulseras lilas»; debajo, cada reserva: «Cumpleaños» si lo es, la línea («Entrada 1 hora · 2 niños»), quién («Vera · Leo»), «Hoy a las 17:00 · empieza en 5 min», «Pagado 32 € (web)», los complementos y el Nº | Cada reserva de hoy: nombre del producto, `is_entry`, cantidad, franja, complementos («N × nombre»), pagado y método, saldo por clase, código y los menores asignados con nombre | HAY casi todo. FALTA en el DTO la ZONA y la DURACIÓN (están en el catálogo: entradas `kids`/`jump` de 60, 120 o sin duración = ilimitada) y el COLOR (§4.3·1). «Quién», solo los menores asignados (a medir en P1 si hay adultos con nombre) |
| 10 | Cumpleaños: «Cumpleaños Kids 2 horas · 10 niños», todas rojas | Los dos packs de la local están en la zona `cumpleanos` (la sala); «KIDS»/«JUMP» solo está en su NOMBRE (medido) | FALTA la zona de salto de un pack (§4.3·3) |
| 11 | Pago: «Falta pagar 12 €: avisa al encargado.» · «Hay que devolverle 12 €: avisa al encargado.», en ámbar bajo su fila; sin botón; fuera «Dinero en revisión» | `Balance`: `pay_at_park`, `refund_at_park`, `refund_pending`, `settled`, `pay_online`, `expired`, `under_review`; hoy `under_review` se pinta como alerta | HAY; CHOCA «fuera Dinero en revisión» con D-T3·8 (§4.2·D2) |
| 12 | Calcetines: «2 pares de calcetines», loseta gris con el icono de huellas | El complemento «Calcetines antideslizantes» existe (id 110 en la local, icono `socks`); nada dice que se entregue en la puerta | FALTA (§4.3·2) |
| 13 | Firmar: pendiente («Dar por firmado» y su confirmación DENTRO de la ficha: «¿Ana Martínez está delante y acepta el descargo, por ella y por Vera y Leo? Quedará firmado con tu nombre.» · «Sí, firmado» · «Cancelar») · nada · cambiado · hijos | `declareWaiver()` (`#336`), `waiver.pending_acceptance`, `waiver.outdated` (hoy «versión anterior» señala y deja pasar) y el descargo de cada menor | HAY; cambian los textos. Cambiado = verde con la tarea «El descargo ha cambiado…» (deja pasar y lo señala, como hoy). «Hijos sin añadir»: a medir en P1 |
| 14 | «Sus hijos»: los menores a cargo con nombre y edad; quien cumple, el primero, con «su cumple»; el adulto no sale; «Descargo ✓» en verde claro o «sin descargo» en ámbar | `dependents` (nombre de pila, edad, `waiver`), el `honoree` de las fiestas de hoy | HAY; CHOCA «Descargo ✓» con `#320` (§4.2·D1) |
| 15 | Sin reserva hoy: en la primera línea, neutro, «Sin reserva a su nombre hoy» y «Tiene reserva, pero otro día: Sábado 26 a las 17:00 · …» | `today_reservations` vacío y `window` (±`puerta.window_days`, 1 por defecto) | HAY |
| 16 | La encuesta, solo en verde y en la primera visita: «Pregúntale.», la pregunta, sus opciones (un toque guarda), «Ahora no» (no guarda nada), «Guardado.» | La encuesta interna (`encuestas.md` §4.2): se ofrece con la visita acreditada si `SurveyResponses::offerFor()` la da; varias preguntas y «Guardar» (la de la local, `visita-de-hoy`: elección de 4, sí/no y varias de 3) | HAY; la forma, pregunta a pregunta (`#817`), que pide guardar a medias; «solo en verde», nuevo |
| 17 | Pie, solo con ficha: «La ficha se cierra sola a los 5 min.»; sin «Nueva búsqueda» | `gate-expires` y el botón «Nueva búsqueda» (`clear()`), que `GateKioskTest::test_the_privacy_reset_is_never_hidden` fija como control de PRIVACIDAD | HAY: se queda, pequeña, en el pie (`#817`) |
| 18 | Velo a los 60 s, opaco, con la reseña del día; el cierre a los 5 min | El velo (Alpine, 60 000 ms) y el cierre (`$wire.clear()` a los `ttl_minutes`; 5 por defecto), con la caducidad de verdad en el servidor | HAY; FALTA la reseña (§4.3·4) |
| 19 | Dos columnas en horizontal, cada una con su desplazamiento; sin nada a la derecha, una sola | Dos columnas a la misma altura (`#234`, `GateKioskTest`) | FALTA (la vista) |
| 20 | La reseña del día, con el campo vacío y en el velo, nunca en la ficha: «Lo que dicen de vosotros.», la reseña entre comillas (cortada con «…»), «Laura, en Google · hace 3 días»; sin reseñas de 30 días, el icono del lector | Las reseñas copiadas de Google (`google_business_reviews`: 7 en la local; ≥ 4 estrellas y con texto, hasta 12, `GoogleReviewFilter`; la portada lee las más recientes, con el plazo al leer: `withinRetention()`, `publishableAuthor()`) | FALTA: por palabras del panel (`#817`, §4.3·4) |
| 21 | Los cumpleaños sin la lista de invitados, la marca de llegada, el − y el +, el QR de la autorización, lo que se cobra ni el suplemento de fiesta mixta | Hoy la ficha enseña los niños invitados con su estado de entrada y «8 de 12 con justificante» (T6·4, F7: `GateInvitedGuestsTest`, `GateHonoreeTest`) y el suplemento escrito (`MixedPartyParkSurfacesTest`) | La lista se queda en UNA línea sin nombres, «8 de 10 con autorización» (`#817`; el dato ya existe: `guest_minors_count`); lo demás sale, como el mockup |
| 22 | Solo modo claro | `layouts.puerta` sigue el modo del panel (su misma clave `theme`) | Cambia (§4.2·D3) |

### 4.2 Lo que decide el owner, y lo que se decide aquí

**Las cuatro preguntas y lo que contestó el owner** (`#817`, 02-10, con `AskUserQuestion`; la recomendada iba primero):

- **Q1 · «Nueva búsqueda».** El mockup la quita (29-09: «no se usaba»; la ficha la quitan el siguiente escaneo, el velo
  a los 60 s y el cierre a los 5 min). Hoy es un control de PRIVACIDAD con su guarda, y el brief la pide «siempre a la
  vista». ▶ **Se queda, pequeña, en el pie**, junto a «La ficha se cierra sola a los 5 min.» (la recomendada).
  Descartado: fuera, con la ficha a la vista de la cola hasta 60 s.
- **Q2 · Los invitados de un cumpleaños.** El mockup los quita de la Puerta (29-09: «no se llevan en el mostrador») y su
  propio readme deja abierto «dónde ve el empleado la firma». ▶ **Una sola línea en la fila del cumpleaños, «8 de 10 con
  autorización»**, sin nombres (la recomendada). Descartado: nada, como el mockup; la lista entera, como hoy.
- **Q3 · La encuesta de la puerta.** El mockup pinta UNA pregunta y un toque guarda; la encuesta interna de hoy puede tener
  varias y se guarda entera. ▶ **Una pregunta por pantalla: cada toque guarda y pasa a la siguiente; «Ahora no» deja el
  resto.** Pide guardar una respuesta A MEDIAS (hoy `answerSurvey()` la guarda entera y valida las obligatorias): el
  «al detalle» de la P1 mide cómo, contra `encuestas.md` §4.2. Descartado: una sola pregunta (la recomendada) y varias a
  la vez con «Guardar».
- **Q4 · La reseña del día, la que «habla del equipo».** El producto no sabe de qué habla cada reseña. ▶ El owner: **«hay
  que hacerlo profesional y robusto, yo pensaba filtrar por palabras de las reseñas actuales o del widget de google
  cuando esté»**: por PALABRAS de una lista del panel, sobre las reseñas que tenga el producto (§4.3·4). Descartado:
  marcarlas a mano (la recomendada) y cualquiera reciente.

**Decidido aquí, contra los objetivos y con su porqué** (el owner puede darle la vuelta; cada uno es una línea):

- **D1 · «Descargo ✓» en cada hijo: NO, solo la excepción** («sin descargo», «descargo antiguo»). Lo dicen `#320` (el owner:
  «es obvio») y el brief («solo lo que es excepción»), y sirve al objetivo de los tres segundos; el mockup lo pinta en verde
  claro.
- **D2 · «Dinero en revisión» se QUEDA**, en ámbar y con la voz del mockup: «El dinero de esta reserva no cuadra: avisa al
  encargado.». Sin ella, un libro que no cuadra diría «Pagado» en verde, y eso lo prohíbe D-T3·8 (`desglose-libro.md`).
- **D3 · Solo modo claro** (el mockup): la Puerta deja de seguir el modo oscuro del panel.
- **D4 · Sin «Volver al panel»** (el mockup): quien tiene el rol `puerta` (`#320`) entra directamente aquí.
- **D5 · «Resultado para», enmascarado y en el SERVIDOR** («an•••@correo.es», «••• ••• 678»): el eco viaja en el estado
  de Livewire, y enmascararlo en el navegador lo dejaría entero en el DOM.
- **D6 · Fuera, como el mockup**: lo que se cobra en un cumpleaños y su suplemento de fiesta mixta («se cobran al final,
  fuera de la Puerta»), «Cobrado», el «−» de las pulseras, las tareas de altura y edad y los grupos.

### 4.3 Los mecanismos nuevos (lo de PlayJump, como DATO)

1. **El color de las pulseras.** El producto no nombra colores: los pone el parque en su panel.
   - **La rueda** (ajuste nuevo de la Puerta, futuro): una lista ORDENADA de colores (nombre en singular y en plural, y
     su hex), la hora del primero y el paso en minutos. PlayJump: naranja, lila, amarilla, rosa, azul y verde, desde las
     11:00, cada 30 min. Comprobado contra la tabla `HORAS` del mockup: 12:00 amarilla, 17:00 naranja, 21:30 rosa. Las
     11:00 y las 11:30, «por confirmar» en el readme, son DATO del parque.
   - **Un color fijo por producto gana a la rueda**: la columna `ticket_types.wristband_color`, que ya existe y hoy está
     vacía en todos (medido). PlayJump: gris la ilimitada y roja en sus dos packs de cumpleaños.
   - Sin rueda ni color fijo (otra instalación), la loseta neutra con la cifra y sin nombre de color.
2. **Lo que se entrega en la puerta.** Una casilla nueva en el complemento, «Se entrega en la puerta» (futuro). PlayJump la
   marca en «Calcetines antideslizantes». La ficha suma las unidades de los complementos marcados de las reservas de hoy
   y los pinta como tarea (la cifra en loseta gris, el icono del complemento y su rótulo); los demás complementos siguen en
   su línea («+ Tarta de 12 raciones · + Combo para padres»). El rótulo («pares de calcetines»), a medir en la P2.
3. **La zona de salto de un cumpleaños**, para la fila «10 KIDS pulseras rojas»: a medir en la P2 si el aforo ya la sabe
   (si la sabe, se lee de ahí); si no, un campo del pack. Mientras, la fila dice el nombre de su zona.
4. **La reseña del día, por palabras** (`#817`: «profesional y robusto»). Lo que se propone, a medir en el «al detalle» de
   la P3 antes del código:
   - **La fuente** es la tabla de reseñas del producto (`google_business_reviews`): la llenan las reseñas copiadas hoy y la
     pasada de Google cuando llegue, así que el filtro no cambia con la fuente. Las candidatas, las que ya puede enseñar
     la portada (≥ el mínimo de estrellas del panel, con texto, sin ocultar y dentro del plazo, `withinRetention()`), de los
     últimos 30 días contados con el reloj del parque (`DisplayTime`), y sobre el texto ORIGINAL del autor (`comment`).
   - **Las palabras, en el panel** (ajuste de la Puerta, futuro): una por línea («monitor», «equipo», «atentos» o el nombre
     de pila de quien trabaja allí), en cualquier idioma. Casan sin mayúsculas ni tildes (NFD sin marcas), por PALABRA y por
     su principio: «monitor» casa «monitora» y «monitores», y no «desmonitor» ni una subcadena dentro de otra palabra. Una
     entrada de varias palabras casa si van seguidas.
   - **Cuál sale**: las que casan, en orden estable (la más nueva primero y, a igualdad, la de menor id); la de hoy es la
     del puesto «días desde una fecha fija, módulo cuántas hay», así que cada día sale otra y se turnan todas. Sin
     palabras o sin ninguna que case, no sale nada (el icono del lector).
   - **Cómo se pinta**, como el mockup: entre comillas, sin cambiar una palabra y cortada con «…» en un espacio; el autor
     por `publishableAuthor()` (sin nombre si su plazo corto ya pasó, `google-business-profile.md` §4.3·4) y «hace 3 días»
     con `RelativeAge`.
   - **Sus pruebas, cada una con su mutación**: las tildes y las mayúsculas, el principio de palabra (y su negativo), la
     frase de varias palabras, el borde de los 30 días a la medianoche del parque, el turno (determinista y completo), el
     plazo y una reseña oculta que no sale.

### 4.4 Las tandas

- **P1 · La pantalla con lo que HAY.** La vista nueva (filas 1–8, 9 sin color, 11, 13–19, 21 y 22) con las respuestas
  (1)–(3) de `#817`; la encuesta a medias, medida antes contra el anonimato de `#754` (la respuesta no puede quedar atada al
  cliente). Las marcas `data-gate-*` que leen las 13 pruebas se conservan donde la pieza sigue y se re-apuntan a sabiendas
  donde cambia (cada una con su porqué en el commit). `GateKioskTest` se rehace con las reglas del mockup: ≥ 44 px, y el
  veredicto y las tareas sin desplazar en 1080 × 810.
- **P2 · Las pulseras y lo que se entrega**: la rueda, el color fijo, la zona de un cumpleaños y la casilla del complemento
  (§4.3·1–3), con sus campos del panel.
- **P3 · La reseña del día, por palabras** (§4.3·4).

Cada una con su «al detalle» medido aquí antes del código, en `wip/…`, con su arnés (`mutar-puerta-p1.sh`… futuro) y su
sonda (`sonda-puerta.mjs`, futuro) a 1080 × 810, 1194 × 834, 1366 × 1024 y 390 de ancho, y las fichas de `datos.js`
montadas en la BD local (`ojo-puerta.php`, con `OJO=desmontar`).

## 5. Impacto en invariantes

- **RGPD** (`INVARIANTES.md` §3, lo que ve la cola): más estricto, con «Resultado para» enmascarado; sin apellidos de menores
  (estructural, el DTO); el autor de la reseña, con su plazo corto.
- **SEC-04**: sin cambio (la caducidad, los permisos y los limitadores siguen en el servidor).
- **Dinero**: D-T3·8 se conserva (§4.2·D2). Aforo: ninguno.

## 6. Plan de verificación empírica

- Siguen en verde las 13 pruebas de la puerta (`tests/Feature/Admin/Puerta/*`, `tests/Feature/Puerta/*`, `GateKioskTest`,
  `WaiverGateTest`, `MixedPartyParkSurfacesTest`, `PanelAppAuthenticationTest`, `PanelSecretPathTest`), con sus cambios
  declarados.
- Nuevas, cada una con su mutación: el veredicto con los menores, el enmascarado, la rueda (con el paso, la hora del
  primero y el color fijo ganando), la casilla del complemento y la encuesta de un toque.
- La sonda sin intervención en los cuatro tamaños: sin desplazar en el caso común, ≥ 44 px, el foco, ningún correo ni
  teléfono enteros en el HTML.
- El ojo del owner en la tablet, con el LECTOR real (el readme lo deja «por decidir»: en iPad, el cursor abre el teclado
  en pantalla salvo que el lector vaya emparejado como teclado).

## 7. Revisión y decisión

- 2026-10-02 · borrador medido (agente SPA).
- 2026-10-02 · el owner contesta Q1–Q4 (`#817`): las recomendadas en Q1 y Q2; en Q3, pregunta a pregunta; en Q4, por
  palabras, «profesional y robusto». D1–D6 quedan como los decidió el agente. Sigue el «al detalle» de la P1.
