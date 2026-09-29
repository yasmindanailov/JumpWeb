# [SPEC] Los correos que recibe el cliente — cada envío a la vista, su previsualización, y si lo abrió y pulsó

> Estado: ✅ **aprobada por el owner** (28-09, `#794`) → C1 en curso · Última actualización: 2026-09-28 · Decisión: `#794` ·
> Carril: **SPA** (los correos son suyos desde `#789`; banda 790–819). Adelanta la T5 «correos por cliente» de
> `analitica-para-decidir.md` §4.9; el gasto en anuncios, aplazado; las felicitaciones (TP·3c), con el rediseño de la plantilla.

## §0 · Antes de tocar

- **La regla**: el panel dice qué correos recibió cada persona, los enseña TAL COMO SALIERON, y cuántas veces los abrió
  (solo si consintió) y pulsó un enlace. Es una herramienta de OPERACIÓN con permiso propio; a la analítica solo llegan
  agregados (`#793`: el público es anónimo, nada exporta personas).
- **Empieza por** §1 (lo medido) → §4.1 (el registro) → §4.3 (clics y aperturas) → §4.5 (las tandas).
- **Trampas**: (1) los 25 correos al cliente pasan por `BrandedMailMessage` y sus enlaces por `EmailUtm::tag()`: la marca del
  envío va AHÍ, no en 25 `toMail()`; (2) un enlace FIRMADO se rompe si cambia su query: la marca se declara ignorada, como la UTM
  (`EmailUtm::IGNORED_QUERY`); (3) `sendmail` no avisa de entregas ni rebotes: «entregado» no se puede saber, «falló al
  enviar» sí; (4) el píxel de apertura exige consentimiento (LSSI 22.2) y Apple Mail abre solo: las aperturas son
  APROXIMADAS; (5) los escáneres de enlaces de Outlook y Gmail pulsan solos: un clic en el primer segundo tras el envío no cuenta.
- **Estado**: ✅ aprobada (`#794`: la COPIA 6 meses, las cifras 24); ✅ C1, C2 y C2b en `main` (ojo del owner, 29-09); 🟦 C3 aperturas (§4.12–§4.13).
- **C2**: `jw_e` va en `EmailUtm::IGNORED_QUERY` y NUNCA en `RouteNormalizer::QUERY_ALLOWLIST` (la analítica es anónima);
  la encuesta no lleva marca (`#754`); el escáner se reconoce por la RÁFAGA, no por el reloj (§4.8).
- **Apuntar un envío NUNCA rompe el envío** (`#794`): un fallo del registro reintentaría el trabajo y duplicaría el correo.
  Y se apunta TRAS el commit (`afterCommit`, como `Recorder`): con la cola `sync`, un deadlock desharía la transacción de quien envía.
- **Invariantes**: `RGPD-01` (la supresión borra el HTML y la dirección), `RGPD-02`, `RGPD-04` (`no-store` en la vista previa),
  `RGPD-07` (la analítica, agregada), `SEC-04`. Ningún fichero del `CRITICAL_RE`.

## 1. Contexto y problema — MEDIDO (2026-09-28)

- **Qué sale**: 25 notificaciones con `toMail()`, **todas** construidas con `App\Notifications\Support\BrandedMailMessage`
  (medido con `grep -L`: ninguna fuera). Los 2 `Mailable` (`ContactMessageMail`, `PaymentIncidentMail`) van al NEGOCIO, no al
  cliente. En producción salen por `sendmail` del hosting (`ENTORNOS.md` §6) desde la cola `database` que procesa el cron.
- **Qué se sabe hoy**: `RecordEmailSent` deja el hecho `email_sent {key}` en el libro al salir cada correo al cliente (con
  `user_id` si es una cuenta) y `RecordEmailClick` deja `email_clicked {key}` cuando alguien llega con la UTM del correo,
  **una vez por sesión y clave**. No hay registro por ENVÍO: no se sabe qué correo concreto se pulsó, ni cuántas veces, ni
  se puede ver qué recibió alguien. No hay píxel.
- **Cuánto pesa**: el HTML de un correo real, 13–19 KB (medido en Mailpit: 16.978 B la encuesta, 16.468 B el de la
  contraseña, 19.326 B «Mañana es tu fiesta»); los adjuntos (el justificante en PDF, 1,5 MB) no hacen falta para verlo.
- **Quién consintió el análisis**: `cookie_consent_logs` guarda `user_id` y `categories`; su última fila de una cuenta dice si
  aceptó «análisis». La oposición de la cuenta es `users.analytics_opt_out` (`PUT /me/analytics`).

## 2. Objetivo

- Desde el panel: la lista de correos enviados (quién, cuál, cuándo), su vista previa EXACTA, y por cada envío: abierto (N
  veces, o «no se mide»), clics (N) y si falló al salir. Desde la ficha del cliente, los suyos.
- Después, en «Marketing» y solo en conjunto: por tipo de correo, enviados, % abiertos (aprox.), % con clic y compras en los
  7 días tras el clic.
- **Fuera**: el gasto en anuncios; rebotes y entregas (exigen un proveedor con avisos: coste y decisión del owner); exportar
  nada; las felicitaciones (TP·3c, con el rediseño de la plantilla); los correos al NEGOCIO.

## 3. Opciones consideradas

- **Dónde se ve** (el owner no lo tenía claro): (a) solo en la ficha de cada cliente; (b) solo una página con todos; **(c)
  elegida: una página «Correos enviados» y, en la ficha, los últimos diez del cliente con «ver todos» a esa página filtrada**.
  Responde a las dos preguntas —«¿qué recibió este cliente?» y «¿qué está saliendo y qué se lee?»— con UNA fuente y una tabla.
  Incluye a quien no tiene cuenta (el padre que firmó una autorización): «¿le llegó el justificante?» también es operación.
- **La vista previa**: (a) volver a pintar el correo al abrirlo —no es lo que se envió: la plantilla y los datos cambian—;
  **(b) elegida: guardar el HTML que salió** (≈16 KB, sin adjuntos), con plazo y borrado en la supresión.
- **La marca por envío**: (a) una ruta de redirección propia para cada enlace —cambia todas las URLs, rompe las firmadas y
  añade un salto—; **(b) elegida: un parámetro `jw_e` pegado junto a la UTM** e ignorado al validar la firma.

## 4. Diseño elegido

### 4.1 El registro: `email_sends` (futuro)
Una fila por correo AL CLIENTE que sale: `id` (ULID: es también la marca `jw_e`), `user_id` (nulo si el destinatario no
tiene cuenta), `recipient` (la dirección), `key` (`EmailUtm::keyOf()`: qué correo es), `subject`, `html` (el cuerpo que
salió, sin adjuntos), `attachments` (solo sus nombres), `tracked_opens` (si llevó píxel), `sent_at`, `failed_at`,
`opens`/`first_opened_at`/`last_opened_at`, `clicks`/`first_clicked_at`/`last_clicked_at`, timestamps.
- Nace en `BrandedMailMessage` (el `id` y una cabecera `X-JumpWeb-Send`) y se completa al salir con el `MessageSent` del
  framework (asunto, destinatario y HTML); `NotificationFailed` pone `failed_at`. Un solo sitio, no 25.
- **Plazo** (`#794`, `[DECIDIDO owner]`): la COPIA —`html`, `subject`, `attachments`— se borra a los **6 meses**; la fila con
  sus cifras vive **24 meses** (`model:prune`). Tres correos llevan el nombre de un menor (el justificante firmado, la víspera
  y «El cumple se acerca»; medido: ninguno lleva alergias). **`RGPD-01`**: `anonymize()` borra `html`, `recipient` y `subject` de las del titular
  y suelta `user_id` (las cifras quedan, sin nadie). El export del art. 20 lleva sus envíos (qué, cuándo, abierto, clics).

### 4.2 Dónde se ve
- **Página «Correos enviados»** (futuro) en el panel, enlazada desde «Clientes» (sin tocar el menú plano de `#223`): tabla
  con fecha, destinatario (nombre de la cuenta o dirección), correo, asunto, abierto, clics y estado; filtros por cliente,
  correo, fechas y «abiertos / con clic / fallidos».
- **En la ficha del cliente** (`UserInfolist`): «Correos», los diez últimos y «Ver todos».
- **La vista previa**: un modal con el HTML en un `iframe` aislado (`srcdoc` + `sandbox`, sin scripts), `no-store`, y cada
  vista deja rastro sin PII (`emails.previewed`, `RGPD-02`). **Permiso propio** `emails.view` (futuro): leer un correo es leer
  datos de una persona (a veces de un menor), y no va al staff por defecto.

### 4.3 Clics y aperturas
- **Clics, por envío**: `EmailUtm::tag()` pega `jw_e=<id>` junto a la UTM; `RecordEmailClick` suma `clicks` a ese envío en
  CADA clic (el hecho `email_clicked` del libro sigue igual, una vez por sesión). Con `analytics_opt_out` el correo sale
  sin `jw_e` y el clic solo cuenta en conjunto. Un clic en los primeros segundos tras el envío es de un escáner: no cuenta.
- **Aperturas, solo con consentimiento**: un píxel propio (futuro) en el pie del molde SOLO si la cuenta, al mandarlo, tenía
  «análisis» aceptado (su última fila de `cookie_consent_logs`) y no se opuso; sin cuenta, nunca. Rotuladas «aproximadas».
  La ruta del píxel no abre sesión ni cookie, responde `no-store` y tiene limitador.
- `[PENDIENTE: asesoría]` los clics por persona con interés legítimo y oposición, y el píxel con consentimiento; `/privacidad`
  y `/cookies` los nombran (buzón a la web).

### 4.4 A la analítica (lo que el owner pidió: «después con esos datos los implementamos en las analíticas»)
En «Marketing», por correo: enviados, fallidos, % abiertos entre los medibles (aprox.), % con clic, clics por envío y
compras en los 7 días tras el clic (la atribución de `EmailUtm`). Solo agregados, con los mínimos de `RGPD-07`.
**Datos que propongo añadir** (el owner preguntó): qué ENLACE se pulsó (la ruta normalizada: qué botón funciona), las BAJAS
desde cada correo (para las felicitaciones) y el tiempo hasta el primer clic. Los rebotes, no: exigen un proveedor con avisos.

### 4.5 Las tandas
- **C1 El registro y la vista previa**: `email_sends`, `BrandedMailMessage` + `MessageSent` + `NotificationFailed`, la página,
  la sección de la ficha, el permiso, el plazo y `anonymize()`.
- **C2 Los clics por envío**: `jw_e`, la firma que lo ignora, el contador y el escáner.
- **C3 Las aperturas**: el píxel con su condición de consentimiento.
- **C4 A la analítica**: «Marketing» por correo (§4.4).

### 4.6 La C1 al detalle — medido el 28-09, antes de codificar
- **La clave del envío es el id de la notificación**: `NotificationSender` fija un UUID por destinatario (`sendNow` y
  `queueNotification`) y el trabajo de la cola lo conserva entre reintentos. Los 25 correos construyen `new
  BrandedMailMessage($this)` (30 construcciones, todas con `$this`): ahí se añade la cabecera `X-JumpWeb-Send` con ese id,
  solo en los correos AL CLIENTE (`EmailUtm::isCustomerKey()`).
- **Un oyente, dos eventos** (`RecordEmailSend`, futuro): `NotificationSent` (la respuesta es un `SentMessage`: de su mensaje
  original salen el asunto, el destinatario, el HTML y los nombres de los adjuntos) y `NotificationFailed` (el envío falló;
  el framework reintenta). Upsert por la clave: un reintento que acaba bien completa la MISMA fila. **Todo dentro de un
  try/catch**: una excepción en el oyente subiría hasta el trabajo, que se reintentaría y duplicaría el correo. **Y tras el
  commit** (`DB::afterCommit`, añadido al cerrar la C1: ver §4.7 (4)).
- **Los datos**: `email_sends` (futuro) sin FK a `users` (como el libro: la poda borra por edad y la fila de `users` no se
  borra nunca); alias de morfo; la copia se borra a los 6 meses (`email-sends:trim` (futuro), diario: una tarea más en
  `scripts/deploy.sh`) y la fila a los 24 (`model:prune`).
- **El panel**: `EmailSendResource` (futuro) —lista con filtros y la vista previa en un modal con el HTML en un `iframe`
  `srcdoc` aislado (`sandbox` sin permisos: ni scripts ni enlaces que salgan)—, fuera del menú plano (`#223`): se llega desde
  «Clientes» y desde la ficha. Permiso `emails.view` (catálogo y seeder, de gestión); `emails.previewed` al rastro.
- **El export del art. 20** lleva los envíos del titular (qué correo, cuándo, si salió): contrato **1.51.0** (la 1.50.0 es de
  plataforma). `anonymize()` borra `html`, `subject`, `recipient` y `attachments` de los suyos y suelta `user_id`.

### 4.7 La C1, lo construido (28-09; ✅ ojo del owner el 29-09: «buen trabajo, visto bueno ok»)
`email_sends` (migración y `Platform\Models\EmailSend`), la cabecera en `BrandedMailMessage`, `RecordEmailSend` (los dos
eventos, blindado), `email-sends:trim` (04:40) y la poda, `anonymize()` y el export (contrato 1.51.0), el permiso `emails.view`
y `emails.previewed` al rastro, `EmailSendResource` (en «Ajustes → Sistema», en «Clientes» y en la ficha) con la vista previa
aislada, y los rótulos de los 27 correos en es y zh_CN. Pruebas: `Mail\EmailSendsRecordTest` y `Admin\EmailSendsPanelTest`;
arnés `scripts/mutar-correos-salientes.sh`. **Lo que enseñó**: (1) la prueba de Livewire NO pinta el contenido de un modal de
Filament 4 (la acción se monta y el HTML no lo trae): el contenido se le pide a la acción de verdad y el navegador lo ve en la
sonda; (2) un superviviente del arnés era una aserción por SUBCADENA (`email-sends:trim-no` contiene `email-sends:trim`): el
nombre exacto; (3) Larastan declara `Notification::$id` como texto aunque es NULO hasta que el framework lo fija; (4) ⚠️⚠️
**el `try/catch` solo no bastaba**: en producción la cola es `database` y el worker envía fuera de toda transacción, pero con
`QUEUE_CONNECTION=sync` (el `.env.example`, el local) el correo sale DENTRO de la transacción de quien lo dispara; un deadlock
en el INSERT desharía en InnoDB la transacción ENTERA y, tragado el error, el código de fuera seguiría escribiendo sin ella.
Se apunta en `DB::afterCommit` como `Recorder` (prueba `…record_waits_for_its_commit`, roja antes del arreglo; su mutación en
el arnés). El deadlock NO se reprodujo: es el comportamiento documentado de InnoDB. Coste: con `sync`, si la transacción de
fuera se deshace, el correo salió y no queda apuntado; (5) la copia guardada y la que recibió Mailpit difieren SOLO en los
finales de línea: el SMTP lleva CRLF (RFC 5322) y la copia se toma antes del transporte, con LF. Es el mismo correo.

### 4.8 La C2 al detalle — medido el 29-09, antes de codificar · `[DECIDIDO]` 29-09, `#795`
**Medido**:
- (a) Llevan UTM el botón (`BrandedMailMessage::action()`), el logotipo y los cuatro enlaces del pie (la vista, con `viewData['utm']`).
  Solo dos correos llevan un enlace propio en el cuerpo y los dos son de BAJA (`BirthdayComingNotice`, `SurveyInvitation`), sin UTM.
- (b) `RecordEmailClick` (grupo `web`) cuenta hoy `email_clicked` con CUALQUIER GET: los escáneres ya lo inflan.
- (c) `EmailUtm::IGNORED_QUERY` ES `RouteNormalizer::QUERY_ALLOWLIST`: la lista que ignora la firma y la que la analítica
  anónima admite en una ruta son la misma constante. La ingesta guarda solo la ruta y esa lista.
- (d) La encuesta es anónima (`#754`): la participación NO guarda si contestó y la respuesta lleva día y franja.
- (e) `toMail()` no recibe a quién va en el molde; `NotificationSending` (con el destinatario) se dispara justo antes, con el
  MISMO objeto de notificación que llega a `toMail()`.
- (f) Escáneres, con fuentes ([Mautic #16263](https://github.com/mautic/mautic/issues/16263),
  [Mailchimp](https://mailchimp.com/resources/bot-clicks-in-email-marketing/)): pulsan al ENTREGARSE (a menudo decenas de
  segundos tras el envío), TODOS los enlaces (medido allí: 23 visitas desde 10 IP en 15 s) y con agente de navegador falso.

**Decidido** (el owner dio el objetivo: «si ha hecho clic y cuántas veces»):
1. **La marca**: `jw_e=<send_key>` (el UUID del envío, no el id autoincremental: no se puede recorrer), pegada por
   `EmailUtm::tag()` junto a la UTM en los mismos enlaces. Las bajas, sin marca.
2. **Quién la lleva**: el correo a una CUENTA sin `analytics_opt_out`, con el interruptor `emails.track_clicks` encendido,
   y nunca la encuesta (por (d): «abrió la encuesta a las 10:03» junto al día y la franja de la respuesta la destaparía).
   El interruptor, APAGADO por defecto: el producto es de marca blanca y §7 pide encenderlo tras la asesoría. En local, encendido.
   Se decide al ENVIAR (un oyente de `NotificationSending` lo anota en un `WeakMap` por la notificación; sin el evento —un
   `toMail()` a mano— no hay marca) y se RE-COMPRUEBA al pulsar. Descartado: `$notifiable` en los 25 `toMail()` (la trampa
   (1) del §0), y decidir solo al pulsar (la marca viajaría a quien se opuso).
3. **La firma**: `IGNORED_QUERY` = la lista de la analítica + `jw_e`; `jw_e` NUNCA entra en `QUERY_ALLOWLIST`.
4. **El clic**: un GET con `jw_e` apunta la visita si procede y responde **302 a la misma URL sin `jw_e`**. Así, recargar no
   cuenta, y la barra de direcciones o un enlace compartido no llevan la marca. El navegador conserva el `#fragmento` y la
   firma sigue valiendo. Si apuntar falla, la página se abre igual.
5. **Los datos**: `email_clicks`, con `email_send_id` (se borra con su envío), la `route` normalizada (qué enlace:
   §4.4), `clicked_at` y el `verdict`. Sin IP ni agente de usuario. Sustituye a los contadores de §4.1, porque la ráfaga
   necesita la hora de cada visita.
6. **El escáner**: `early` (< 10 s tras el envío), `sweep` (≥ 3 visitas al envío en 30 s: todas las de esa ventana) y
   `repeat` (el mismo enlace en 30 s: el doble paso de Safe Links al pulsar, o un doble clic). Cuenta lo que no lleva
   veredicto. Cada envío se serializa con un bloqueo de su fila, porque los escáneres llegan en paralelo. Descartado: contar
   IP distintas (obliga a guardarlas) y el agente de usuario (falso).
7. **Se ve**: «Clics» en la lista, con «+N de escáner», y el filtro «con clic»; en la ficha. El export lleva `clicks`
   (contrato 1.52.0).

### 4.9 La C2, lo construido (29-09; ✅ ojo del owner el 29-09: «procede, visto bueno»)
Construido: `email_clicks` y `email_sends.tracks_clicks` (una migración); `EmailClickMarks` (la regla, el interruptor
`emails.track_clicks` y el oyente de `NotificationSending` con su `WeakMap`); `EmailClicks` (el veredicto, con el envío
bloqueado); la marca en `EmailUtm::tag()` (botón, logotipo y pie); `IGNORED_QUERY` separado de la lista de la analítica; el 302
de `RecordEmailClick`; la columna y el filtro «Clics», también en la ficha; la sección «Correos a los clientes» en Ajustes →
Avanzado; y el export (contrato 1.52.0). Pruebas: `Mail\EmailClicksTest` (15) y `Admin\EmailSendsPanelTest` (+2). Arnés
`SOLO=C2` 24/24, con control.

**Lo que enseñó**:
1. ⚠️⚠️ **La sonda del bloqueo tuvo que ampliarse para medir algo.** Con 6 visitas en paralelo sale bien CON y SIN bloqueo:
   la ráfaga reescribe hacia atrás su ventana y cura la carrera, y la ventana entre leer y escribir dura milisegundos. Con 3
   visitas y 300 ms de pausa en los DOS brazos: sin bloqueo cuentan las 3 (dos rondas); con él, ninguna.
2. La primera sonda exigía «ninguna petición fuera de la casa», y eso es falso en la web pública (carga sus fuentes de
   `THEME_FONTS`). La propiedad buena es que ningún tercero reciba la marca: el Referer que reciben es solo el origen.
3. Pint lee `{@see SETTING}` como la clase `Setting` importada y lo reescribe. Se escribe `self::SETTING`.
4. Un borrado en bloque de `settings` no vacía la memoria de `Setting::value()`; guardar desde el panel sí. ⚠️ En producción
   el worker de la cola conserva esa memoria mientras vive (≤ 50 s con el cron): encender o apagar tarda hasta un minuto en
   notarse en los correos. Al pulsar, la regla se re-comprueba en cada petición.
5. PHP lee `?jw.e=` como `jw_e`, pero en la query cruda no se llama así. Por eso se quita solo lo que de verdad está, y si no
   hay nada que quitar no hay 302 (sería un bucle).

### 4.10 CUÁNDO: la hora de cada apertura y de cada clic — medido el 29-09, antes de codificar · `#796`
El owner (29-09): «medir a qué hora se abre el correo, a qué hora es cada clic, si se vuelve a abrir después… para saber a qué
hora sería correcto enviarles los correos de marketing. Si valoras añadir más detalles, procedemos».

**Medido**:
- (a) La hora de cada clic YA se guarda (`email_clicks.clicked_at`, la C2); el panel solo enseñaba la cuenta.
- (b) Las aperturas, con fuentes ([Apple, SocketLabs](https://help.socketlabs.com/docs/identifying-apple-mpp-opens-in-notification-api-events);
  [Gmail, Suped](https://www.suped.com/learn/email-deliverability/how-does-gmails-image-proxy-affect-email-open-tracking-and-what-could-cause-very-fast-opens)):
  - Apple Mail descarga las imágenes AL ENTREGAR, desde sus servidores y con el agente `Mozilla/5.0` a secas. La apertura de
    verdad sale de la memoria del aparato: su hora NO se puede saber.
  - Gmail pide la imagen al abrir, así que la hora de la primera apertura es real. Luego la guarda: las reaperturas no se ven.
  - Outlook bloquea las imágenes a menudo.
  - ⇒ Para la hora, la señal fiable es el CLIC; la apertura es un complemento, con su origen dicho.
- (c) Los correos programados salen a hora FIJA (la víspera a las 18:00, «El cumple se acerca» a las 10:00, hora del parque):
  a qué hora se abren depende de a qué hora salen.
- (d) De los 27 correos al cliente, 16 los PROVOCA él en ese momento (su cuenta, sus contraseñas, su compra) y se abren al
  instante: no dicen nada de sus horarios.

**Decidido** (el objetivo es del owner; el diseño, mío):
1. **La línea de tiempo de cada envío** (C2b): «Actividad» en «Correos enviados», con cuándo salió y cada apertura y cada clic.
   De cada uno: la hora del parque, cuánto después del envío, qué enlace y desde qué dispositivo. Lo de un escáner o de Apple,
   aparte y sin contar. Deja rastro (`emails.activity_viewed`), como la vista previa.
2. **El dispositivo, a grandes rasgos**: `mobile`, `tablet` o `desktop`, sacado del agente AL PULSAR y sin guardar el agente.
   `null` si no se sabe (un proxy).
3. **Las aperturas** (C3): cada una con su hora, también las reaperturas, y su ORIGEN: `apple` (de máquina, no cuenta),
   `gmail` o `direct`. Solo con el consentimiento de §4.3, y nunca en la encuesta. La vista previa del panel no la cuenta: el
   píxel se quita al pintarla.
4. **«Cuándo» en Marketing** (C4), SOLO en conjunto (`#793`, `RGPD-07`, celdas ≥ 5): el mapa día × hora de los clics y de las
   aperturas de verdad, y la mediana de cuánto se tarda en abrir y en pulsar. Solo con los correos que le LLEGAN: los que
   provoca él quedan fuera, por censo (un correo nuevo sin clasificar pone la suite en rojo).
5. **Descartado**: la «hora de cada cliente» para mandarle a él (perfilar a cada persona: `#793` y la asesoría) y la IP con su
   ubicación. **Para después**: la MEJOR hora exige mandar a horas distintas y comparar (un experimento, `analitica.md` §4.5).
   Se propone cuando haya correos de marketing.
6. **Los robots que SE ANUNCIAN** (`Device::isBot()`: la vista previa de WhatsApp, Slack o Telegram cuando alguien pega el
   enlace en un chat) son `bot`: no cuentan y no entran en la cuenta de la ráfaga. Los escáneres de correo se disfrazan y se
   siguen viendo por el ritmo (§4.8).

Tandas: C2b va en la rama de la C2, y siguen C3 (aperturas) y C4 («cuándo» en Marketing).

### 4.11 La C2b, lo construido (29-09; ✅ ojo del owner el 29-09: «procede, visto bueno»)
Construido: «Actividad» en «Correos enviados» (`EmailSendTable::activity()`, con el rastro `emails.activity_viewed`),
`email_clicks.device`, el veredicto `bot` y la vista previa DESACTIVADA (`EmailSendTable::inert()`). Pruebas en
`Mail\EmailClicksTest` y `Admin\EmailSendsPanelTest`. Arnés `SOLO=C2`, con sus diez mutaciones.

**Lo que enseñó**:
1. ⚠️⚠️ **Un defecto de la C2, cazado al leer la vista previa**: un `iframe` con `sandbox` vacío no abre ventanas, pero SÍ
   navega dentro de sí mismo. Un enlace pulsado en la vista previa habría cargado la web con la marca, y eso contaría como un
   clic del cliente. El comentario de la C1 afirmaba lo contrario sin haberlo probado. Ahora el HTML se pinta con
   `<base target="_blank">` (bloqueado sin `allow-popups`) y sin la marca. La sonda pulsa dentro de la vista previa de verdad.
   **El control tardó tres vueltas en distinguir algo**, y cada una enseñó una trampa:
   - El BOTÓN del molde ya lleva `target="_blank"` (el sandbox lo bloquea de por sí): los que navegan son el pie y el logotipo.
   - Con el panel en `:80` y los enlaces en `:8081`, la CSP del panel (`frame-src 'self'`) cortaba la navegación por ser OTRO
     origen, y la vista previa PARECÍA segura. En el navegador del owner los dos son `:8081`. La sonda va con
     `BASE=http://localhost:8081` y el puente `socat`.
   - Así, sin la protección, pulsar el pie navegó el marco y el servidor apuntó una visita al envío del cliente. Con ella:
     ni navega, ni sale la marca, ni se apunta nada.
2. Las sondas van con el agente de un iPhone: `HeadlessChrome` es un robot para `Device::isBot()` y su clic sería `bot`.

### 4.12 La C3 al detalle: las aperturas — medido el 29-09, antes de codificar · `[DECIDIDO]` `#797`
**Medido**:
- (a) El consentimiento de cookies de una CUENTA vive en `cookie_consent_logs` (`user_id`, `categories`): la última fila
  manda. Platform lo pregunta por el contrato `ConsentLedger`, que hoy solo sabe de VISITANTES: le falta preguntar por cuenta.
- (b) El grupo `web` abre sesión, pone cookies y acuña la del visitante (`ResolveVisitor:mint`); `SetLocale` lee la sesión.
  Un píxel que deja cookies en un gestor de correo es justo lo que no se quiere.
- (c) Gmail pide las imágenes de MILES de personas desde las mismas IP (su proxy): un limitador por IP perdería aperturas de
  verdad tras un envío masivo.
- (d) La copia guardada lleva el píxel: la vista previa del panel lo pediría al pintarse (el riesgo que §7 ya nombraba).

**Decidido** (dentro de `#794`, que aprobó «las aperturas solo con consentimiento»):
1. **Su propio interruptor**, `emails.track_opens`, APAGADO de fábrica. Su base legal no es la de los clics (consentimiento,
   no interés legítimo), y `/cookies` tiene que nombrarlo antes (`[PENDIENTE: asesoría]`).
2. **Quién lleva el píxel**: la regla de persona de los clics (una cuenta, sin oposición, nunca la encuesta) y además su
   ÚLTIMA decisión de cookies con «análisis» aceptado (`ConsentLedger::accountConsentedNow()`). Se re-comprueba al abrir.
3. **El píxel**: `GET /e/{send}.gif` fuera de la sesión, de las cookies y del visitante. Sin limitador por IP, pero con un
   tope por ENVÍO (lo de más en un minuto no se apunta). Responde siempre un GIF `no-store`, y apuntar nunca lo impide.
4. **El ORIGEN de cada apertura** (`email_opens.source`), y cuándo no cuenta (`verdict`):
   - `apple`: agente `Mozilla/5.0` a secas. Es de máquina y nunca cuenta.
   - `gmail`: `GoogleImageProxy`. La primera hora es real; las reaperturas no se ven.
   - `direct`: el resto. Guarda la clase del aparato.
   - `early`: antes de 10 s del envío. `repeat`: otra apertura dentro de 60 s de una que contó (la misma lectura).
5. **La vista previa quita el píxel** al pintarse (`EmailSendTable::inert()`), como ya quita la marca.
6. **Se ve**: la columna «Aperturas», con «+N automáticas» aparte y «No se mide» sin el píxel; las aperturas en «Actividad»;
   `opens` en el export (contrato 1.54.0: la 1.53.0 es de plataforma).

### 4.13 La C3, lo construido (29-09; 🟦 en `wip/correos-c3`, falta el ojo del owner)
Construido: `email_opens` y `email_sends.tracks_opens` (una migración); `EmailOpenMarks` (la regla y su oyente);
`ConsentLedger::accountConsentedNow()`; el píxel en el molde (`layout.blade.php`); `EmailOpenController` y la ruta
`emails.open`; `EmailOpens` (el origen, el veredicto, el tope); la columna, el filtro, la ficha y «Actividad»; el segundo
interruptor; la vista previa sin píxel; y el export (1.54.0). Pruebas: `Mail\EmailOpensTest` (13) y
`Admin\EmailSendsPanelTest` (+3). Arnés `SOLO=C3`.

**Lo que enseñó**:
1. ⚠️ **En Laravel 13 el CSRF del grupo `web` es `PreventRequestForgery`**, no `ValidateCsrfToken`. Excluir el nombre viejo no
   excluye nada: sin sesión, intentó poner su cookie y el píxel respondía 500.
2. **Un superviviente del arnés (24/25)**: con la columna nueva, una fila sin píxel dice «No se mide» también en la de clics,
   y el texto de la página ya no distingue una columna de otra. Le pasaba lo mismo a la prueba gemela de la C2. Las dos
   preguntan ahora a su columna, y las dos mutaciones muerden (comprobadas a mano, fichero restaurado byte a byte).
3. El GIF mide **42 bytes**. El comentario y la primera sonda decían 43 sin haberlo medido.
4. La sonda pide el píxel como Apple (`Mozilla/5.0`), abre el correo REAL en un iPhone (el navegador pide la imagen al
   pintarlo) y como Gmail. En la base: `apple:apple`, `direct:-` (móvil) y `gmail:repeat`. Su control, sin quitar el píxel
   de la vista previa: abrir la vista previa lo pidió y apuntó una apertura desde el ordenador del panel.

## 5. Impacto en invariantes

`RGPD-01` (la supresión borra lo personal de los envíos del titular; no hay columna nueva en `users`) · `RGPD-02` (el rastro
de la vista previa sin PII) · `RGPD-04` (la vista previa y el píxel, `no-store`) · `RGPD-06` (un enlace firmado sigue
validando con `jw_e`) · `RGPD-07` (la analítica, en conjunto) · `SEC-04` (el permiso se re-exige al abrir cada vista previa).

## 6. Plan de verificación empírica

- Tests por tanda con su mutación en un arnés propio: una fila por correo que sale (los 25, por censo), ninguna por los del
  negocio; la vista previa igual al HTML enviado; el permiso; `anonymize()` borra el HTML y conserva las cifras; un enlace
  firmado sigue abriendo con `jw_e`; el clic suma al envío correcto y no a otro; sin consentimiento no hay píxel; el escáner.
- **Mailpit** (`:8028`): el HTML guardado byte a byte igual al recibido. Sonda del panel. El ojo del owner en cada tanda.

## 7. Revisión y decisión

- **28-09, owner**: «ver los correos que recibe el cliente, poder previsualizarlos y saber si lo ha abierto y ha hecho clic y
  cuántas veces. Después con esos datos los implementamos en las analíticas; si valoras que necesitamos más datos en relación a
  los correos, dímelo. No sé si poner los correos individual en cada página de cliente, o una página para ver todos». El gasto
  en anuncios, «no ahora». → este borrador (§3: las dos superficies; §4.4: los datos que propongo).
- **28-09, owner — APROBADA**: «perfecto, lo acepto. Vamos a ello… de la manera más profesional; si hay riesgo me lo dices».
  Con los riesgos delante (las copias con nombres de menores; que apuntar rompa el envío; la vista previa contando como
  apertura; la asesoría), eligió **la copia 6 meses** (recomendada) → `#794`.
- **Pendiente**: `[PENDIENTE: asesoría]` los clics por persona y el píxel ANTES de encenderlos en producción (§4.3).
