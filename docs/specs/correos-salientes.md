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
- **Estado**: ✅ aprobada (`#794`: la COPIA 6 meses, las cifras 24); ✅ C1 en `main` (ojo del owner, 29-09); ▶ C2 (§4.3).
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
