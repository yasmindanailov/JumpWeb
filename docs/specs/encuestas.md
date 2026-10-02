# [SPEC] Las encuestas — internas en la puerta y externas por correo, creadas en el panel, medidas en el cuadro

> Estado: ✅ **aprobada por el owner el 24-09** (`#740`: sus cinco respuestas en §7) → **T1→T4 en `main`** (carril
> del SPA) · ✅ **T5, el anonimato** (`[DECIDIDO owner]` 27-09, `#754`, §4.7: sustituye al punto 1 de `#740`; construida
> con los siete ajustes de `#757` y aprobada por el owner) · Última actualización: 2026-09-27 · Decisiones: `#740`, `#754`,
> `#757`. Es la **T7** de `analitica.md` (§4.8 y §4.10) y la T1 de `analitica-para-decidir.md`.

## §0 · Antes de tocar

- **La regla que ordena todo**: una encuesta es DATO del panel (preguntas, tipos, textos en tres idiomas, ventana,
  clase), nunca código; el sistema solo sabe **cuándo preguntar** —al acreditar la VISITA en la puerta, o al día
  siguiente por correo— y **dónde contar**. ▶ **Desde `#754` las respuestas son ANÓNIMAS** (§4.7): la PARTICIPACIÓN
  (a quién se preguntó) y la RESPUESTA (sin cliente, con día, empleado y franja) no comparten clave; «volvió» sale de
  una marca cifrada que caduca a los 90 días. Ninguna respuesta pisa el libro (`RGPD-07`). Lo de §4.1–§4.5 que diga
  «atada al cliente» está corregido por §4.7.
- **Empieza por** §1 (lo medido) → §4.7 (el anonimato) → §4.1 (el modelo) → §4.2 (la puerta) → §4.3 (el correo) → §4.4.
- **Trampas (24-09)**: (1) **la puerta es un KIOSCO de tablet** (`panel-navegacion.md` §8): nada tapa
  «Registrar visita» ni el lector, la ficha CADUCA en servidor, y la encuesta se ofrece DESPUÉS de acreditar la
  visita. (2) **La visita acreditada es idempotente por (cliente, día)** (`GateVisits::register()`,
  `customer_visits`): la externa se dispara desde esa fila. (3) **Todo correo nace por partida doble** sobre
  `BrandedMailMessage` (piezas de bandeja en tres idiomas, `MailInboxLineTest`; `EmailUtm::keys()` lo censa). (4) **Una tarea nueva del planificador sube el recuento de `deploy.sh`** (espera 6,
  hay 9: avisado a plataforma). (5) Un Resource nuevo va a
  `AdminSettingsHub::areas()` (`AdminNavigationTest`). (6) `RGPD-01`: purga y export cubren las tablas nuevas;
  `RGPD-04`: `no-store` en la página del correo.
- **Estado**: `#740` (una por cliente y encuesta · todos los tipos · correo de servicio con baja · una viva por
  clase) y `#754` (anónimas). **T1→T4 en `main`**; el ESCANEO acredita la visita (`#741`). ✅ **T5** (§4.7, `#757`).
- **Invariantes**: `RGPD-01`, `RGPD-04`, `RGPD-07`, `SEC-04`, `SEC-05`, `PAY-14`, `SUITE-01`. Dinero y aforo:
  ninguno. Ningún fichero del `CRITICAL_RE`.

## 1. Contexto y problema — MEDIDO (2026-09-24)

- ⚠️⚠️ **CORRECCIÓN (25-09, al empezar la T2), y va delante del texto que corrige**: el botón «Registrar visita»
  **NO existe en la vista de la puerta desde `#234`** (28-08: la tarjeta «Visita» se retiró hasta JumpPoints;
  `GateKioskTest` lo asevera y `DEUDA.md` lo anota: `customer_visits` dejó de crecer). Lo que el punto siguiente
  midió fue el MÉTODO `registerVisit()` y su maquinaria, que siguen enteros. Consecuencia: con la pantalla tal
  como está, ni la interna se ofrecería nunca ni la externa tendría a quién escribir. ▶ Resuelto en **`#741`**
  (§4.2): el ESCANEO acredita la visita; la T2 se construyó contra «visita de hoy acreditada», que es lo que el
  escaneo deja puesto.
- **La puerta**: `Livewire\Admin\Puerta\ValidarRegistro` (556 líneas) escanea el carné o busca, compone la ficha
  con `GateProfile` (presupuesto constante, caduca en servidor) y **«Registrar visita»** (`registerVisit()`) llama a
  `GateVisits::register()`, que inserta en `customer_visits` (`user_id`, `visited_on`, `registered_by`; UNA fila por
  cliente y día, `insertOrIgnore`), audita `puerta.visit_registered` y anota el hecho `visit_checked_in` con
  `user_id`. La vista (`livewire/admin/puerta/validar.blade.php`, 413 líneas) pinta la insignia «visita
  registrada» en la cabecera de la ficha y dos columnas de secciones (hoy, otros días, exención, menores). Rol
  `puerta` con dos permisos (`registrations.validate`, `puerta.profile`), kiosco de tablet, «Nueva búsqueda» siempre
  visible. En la local hay 1 visita registrada.
- **Los correos**: 26 en la familia, todos sobre `BrandedMailMessage` (`hero(grupo, tono)`, líneas, botón con
  UTM por `EmailUtm`: `utm_medium` = la clave de la clase). Los censos leen la carpeta `app/Notifications/`:
  `MailMoldTest` (molde y las dos partes), `MailInboxLineTest` (adelanto ≤ 85 sin dato variable, chapa, titular y
  asunto en es/en/fr, solape asunto–adelanto < 60 %), `EmailUtmTest` (26). El más reciente y el molde a copiar:
  `AnalyticsLinkNotice` (tres líneas y un botón). **No existe hoy un «no quiero recibir más» para nada que no sea el
  opt-in de comunicaciones** (`users.marketing_opt_in`, `PUT /me/marketing`).
- **El planificador**: `routes/console.php` registra **9** tareas (`schedule:list`), entre ellas `queue:work
  --stop-when-empty` cada minuto (producción y staging van con `QUEUE_CONNECTION=database`, `ENTORNOS.md`), y
  `deploy.sh` comprueba «esperadas 6». Una tarea diaria nueva sube la cifra; un job con retraso de 24 h también
  valdría con esa cola, pero no deja rastro auditable de a quién se mandó ni se puede reejecutar.
- **El panel**: `ExperimentResource` es el molde de un Resource en «Ajustes → Sistema» (`settings.manage`, form
  con `Repeater` y campos bloqueados cuando el registro está vivo). Los esquemas de campos data-driven ya existen
  (`TicketType::normalizeFieldSchema()`: `{key, type, required, label{es,en,fr}}`, editados con un `Repeater` y
  `TextInput::make('label.es')`…): las preguntas de una encuesta son ese mismo molde con dos tipos más.
- **El cuadro**: cuatro pestañas (`AnalyticsPage::TABS`), informes en la capa de entrega con caché de 5 min, CSV
  por `tablesFor()`, la 360 en `CustomerInsights`. El libro admite hoy 21 hechos de servidor; `visit_checked_in`
  viaja con `user_id` (régimen del contrato).
- **Los consentimientos**: `consents.type` ∈ privacy · terms · waiver · marketing · analytics; flags en `users`:
  `marketing_opt_in`, `analytics_opt_out`. El cajón tiene «Privacidad» con dos interruptores (`MePrivacyController`).

## 2. Objetivo

Que el parque pregunte a sus clientes —en persona al acreditar la visita, y por correo al día siguiente— con
encuestas que crea y apaga desde el panel, y lea las respuestas agregadas en «Analítica», sin escribir una línea de
código por encuesta.

Criterios de éxito, medibles:
1. Una encuesta nueva (interna o externa, con sus preguntas en tres idiomas) se crea, se enciende, se le pone plazo y
   se apaga desde el panel, y desde ese momento la puerta o el correo la ofrecen sin despliegue.
2. En la puerta: tras «Registrar visita» aparece la oferta, el empleado contesta en ≤ 3 toques por pregunta y la
   respuesta queda atada a esa visita; el escaneo y la ficha no cambian de tiempo ni de forma (sonda de la puerta).
3. Por correo: a las 10:00 del parque del día siguiente, cada visitante elegible recibe UN correo con la encuesta;
   la página contesta sin sesión, sin cookie de medición y `no-store`; «no quiero más encuestas» funciona con un toque.
4. El cuadro enseña, por encuesta y periodo, la tasa de respuesta (interna y externa) y el reparto de cada pregunta,
   con presupuesto de consultas ≤ 20 y caché; el CSV lleva solo agregados (ningún texto libre).
5. `anonymize()` desata las respuestas de la persona; el export del cliente las lleva.

**Fuera de alcance**: encuestas a invitados sin cuenta (los de la fiesta: ni correo ni visita acreditada); encuestas
en la web pública o en el cajón; lógica de saltos entre preguntas; NPS con seguimiento por persona; envío por SMS o
WhatsApp; recompensas (JumpPoints) por contestar.

## 3. Opciones consideradas

- **A · Encuestas como DATO con dos disparadores fijos (la visita acreditada y el día siguiente) — ELEGIDA.** Las
  preguntas son un esquema JSON del molde que ya existe; el panel las edita con el Repeater de siempre; la puerta
  y el correo solo preguntan «¿hay una encuesta activa de mi clase para este cliente?».
- **B · Una herramienta externa (Typeform, Google Forms) enlazada desde el correo.** Descartada: las respuestas
  saldrían del producto (RGPD, transferencia, otra cuenta más), no se atarían a la visita ni al cliente, la interna
  en tablet no cabe, y el cuadro no vería nada.
- **C · Un job en cola con retraso de 24 h por visita en vez de un comando diario.** Descartado: sin rastro de
  «a quién se mandó hoy», sin reejecución tras un fallo, y con `sync` (local) no retrasa nada. El comando diario es
  idempotente (una fila por envío) y auditable, como `analytics:notify-accounts`.

## 4. Diseño elegido

### 4.1 El modelo (`Platform`, como `Experiment`)

> ⚠️ **Corregido por `#754` (27-09, §4.7)**: `survey_responses` se parte en PARTICIPACIÓN (con cliente, sin respuestas)
> y RESPUESTA (sin cliente, sin hora, clave aleatoria). Lo que sigue describe la T1 tal como se construyó.

- `surveys`: `id`, `key` (`^[a-z][a-z0-9_-]{0,47}$`, única), `name` json es/en/fr, `kind`
  (`internal`|`external`), `active` bool, `starts_at`, `ends_at` (nulos = sin plazo), `intro` json es/en/fr (la
  frase de cabecera del correo o de la tablet), `questions` json, `created_by`, timestamps. **Vivo** = activa y
  dentro de su ventana (la regla de `Experiment::isRunning()`). `[PENDIENTE: owner]` §7·5: **una viva por clase**
  (recomendado; el panel lo valida al encender) o varias.
- `questions`: lista ordenada de `{key, type, required, label{es,en,fr}, options?}` con `type` ∈ `choice` (UNA
  opción de `options: [{key, label{es,en,fr}}]`), `multi` (varias de la lista), `scale` (1 a 5), `yesno` y `text`
  (libre, ≤ 300 caracteres) — `[DECIDIDO owner]` §7·3: todos los formatos. Normalizada por `Platform\Services\Surveys\
  QuestionSchema` con la misma disciplina que `normalizeFieldSchema()` (sin clave → fuera; tipo inválido →
  `choice`; claves únicas). **Con respuestas guardadas, las claves y los tipos se BLOQUEAN** (los textos siguen
  editables): cambiar una pregunta a mitad mezcla lo medido, como los pesos de un experimento.
- `survey_responses`: `id`, `survey_id`, `user_id` (nullable, `SET NULL` al anonimizar), `visited_on`
  (el día de la visita que la originó), `channel` (`internal`|`external`), `answered_by` (el empleado, solo interna),
  `token` (40 caracteres, solo externa: abre la página), `sent_at`, `answered_at`, `declined_at`, `answers` json
  `{clave: valor}`, `locale`. Índices: `(survey_id, user_id)` ÚNICO —**una respuesta por cliente y encuesta**,
  `[DECIDIDO owner]` §7·2— y `(survey_id, answered_at)`. **Atadas al cliente y a su visita** (`[DECIDIDO owner]`
  §7·1, con la recomendación de §7): el cuadro solo enseña agregados; la persona aparece en dos sitios y con permiso
  propio (`customers.insights`): en su ficha 360 y en la lista de «puntuaciones bajas por atender» (§4.4).
- `users.surveys_opt_out` bool: «no quiero recibir más encuestas», con su interruptor en «Privacidad» del
  cajón (`PUT /me/surveys`, el molde de `PUT /me/marketing`) y el enlace firmado del correo.
- El libro: `Contract` gana `survey_sent`, `survey_answered` y `survey_declined` (servidor; props `survey`, `channel`;
  ref `user_id`, régimen del contrato como `visit_checked_in`). Ninguna respuesta entra en el libro.

### 4.2 La interna: en la puerta, con la persona delante

- ⚠️ **Corregido por `#756` (27-09)**: la visita se acredita también al BUSCAR al cliente por correo o móvil (no solo al
  escanear), así que la interna se ofrece también ahí, y la externa del día siguiente llega a quien se buscó así; la visita
  guarda su `source` (`card` · `lookup`). Lo que sigue es la T2 tal como se construyó.
- **Cuándo**: solo tras `registerVisit()` con éxito (o si la visita de hoy ya estaba acreditada), si hay una encuesta
  interna viva y este cliente no tiene fila para ella (ni contestada ni declinada). Una encuesta contestada no
  vuelve a ofrecerse; una declinada tampoco. La baja de los correos (`surveys_opt_out`) NO calla la puerta: el
  interruptor habla del correo, y aquí el cliente dice «no» en persona (`[DECIDIDO owner]` `#742`).
  ▶ `[DECIDIDO #741]` (25-09) **cómo se acredita la visita, porque no hay botón desde `#234`** (§1): **al
  ESCANEAR el carné se acredita sola** (`searchByCard()` → `GateVisits::register()`, idempotente por día, con el
  permiso de la ficha, ANTES de componer la ficha) y la tarjeta sale en el mismo gesto; la búsqueda tecleada no
  acredita (`registerVisit()` sigue para ella). Descartados: reponer el botón (un gesto más; olvidarlo = ni
  encuesta ni correo) y ofrecer al escanear sin acreditar sacando la externa de las reservas de ayer.
- **Dónde**: una tarjeta `gate-survey` en la columna principal de la ficha, DEBAJO de «Hoy», con el
  nombre de la encuesta, «N preguntas» y dos botones: «Preguntar» y «No preguntar» (esta última escribe
  `declined_at`). Nunca un modal sobre el lector ni encima de «Registrar visita» (§0·1).
- **Cómo**: al abrir, las preguntas una a una o en lista corta (según el tipo): `choice` = un botón por opción;
  `scale` = cinco botones grandes; `text` = un campo corto (el empleado teclea si el cliente quiere). Todo con
  los objetivos táctiles de la puerta (≥ 44 px, §8 de la navegación). «Guardar» escribe la fila (`channel =
  internal`, `answered_by` el operador, `visited_on` hoy) y el hecho `survey_answered`; la tarjeta pasa a «Contestada».
  **Como se construyó (T2)**: cada opción es un radio/checkbox oculto con su etiqueta vestida de botón y
  `wire:model` DIFERIDO (cero idas y vueltas hasta «Guardar»); el servidor tipa (`QuestionSchema::fromForm()`) y
  valida (`validate()`) y devuelve el error por pregunta; los rótulos van en el idioma del PANEL —el operador
  pregunta y marca; `QuestionSchema::label()` cae al español— y `locale` guarda ese idioma; la encuesta ofrecida
  viaja bloqueada (`$surveyId`, como el sujeto) y una encuesta apagada entre la oferta y el guardado no se escribe.
- **Permisos**: el de validar (`registrations.validate`): quien acredita la visita pregunta. `SEC-04`: se re-autoriza
  al guardar. `SEC-05`: sin limitador propio (una respuesta por cliente y encuesta ya acota). Auditoría:
  `puerta.survey_answered` y `puerta.survey_declined` (target el cliente).
- **La ficha caduca en servidor**: la encuesta abierta vive dentro de la misma ventana de la ficha; si caduca, se
  cierra sin guardar y se vuelve a ofrecer en la siguiente búsqueda.

### 4.3 La externa: el correo del día siguiente

- **El comando** `surveys:send-external`, programado a las **10:00 del parque** (`DisplayTime`), en
  `routes/console.php` (+1 tarea: `deploy.sh` «esperadas» sube EN EL MISMO commit, §0·4). Por cada encuesta externa
  viva: los clientes con `customer_visits.visited_on` = AYER (día del parque), con correo verificado, no
  anonimizados, sin `surveys_opt_out`, sin fila para esa encuesta, **sin haber contestado la interna en esa visita**
  (`[DECIDIDO owner]` `#742`: a quien dijo «no preguntar» sí se le manda) y **sin ningún correo de encuesta en los últimos
  30 días** (`surveys.cooldown_days`, ajuste de «Ajustes → Puerta», 30 por defecto). Por cada uno: fila
  `survey_responses` (`sent_at`, `token`) y la notificación en cola. `--dry-run` cuenta sin mandar. Idempotente:
  reejecutar no manda dos veces (la fila ya existe).
  **Como se construyó (T3)**: `SendExternalSurveys` corre **cada hora y la hora la decide él** (desde las 10:00 del
  parque, `--force` la salta), por las dos razones de `reservations:eve-notice`: la zona del parque se resuelve en
  la ejecución y una hora de cron caído no se lleva la encuesta. La elegibilidad va entera en la consulta
  (`whereExists`/`whereNotExists`); la fila nace ANTES de encolar (`SurveyResponses::send()`); el plazo es
  `SurveySettings::cooldownDays()`. `deploy.sh` espera **10** tareas (9 registradas + ésta; decía 6 desde antes).
- **La notificación** `SurveyInvitation` sobre `BrandedMailMessage`: `hero('surveys.mail', 'info')`, la
  `intro` de la encuesta en el idioma del cliente, el botón «Contestar» a la página firmada, y al pie el enlace
  «No quiero recibir más encuestas» (firmado, un toque). Piezas de bandeja en tres idiomas en `lang/{es,en,fr}/
  surveys.php`. `[DECIDIDO owner]` §7·4: **correo de servicio a todo el que visitó**, con la baja de un
  toque y SIN una sola línea comercial dentro (ni oferta, ni producto, ni enlace a comprar: eso lo convertiría en
  comunicación comercial y exigiría el opt-in). `[PENDIENTE: asesoría]` (5): confirmar esa lectura.
  **Como se construyó (T3)**: `SurveyInvitation` (`ShouldQueue`, `hero('surveys.mail')`, piezas en
  `lang/{es,en,fr}/surveys.php`); la primera línea es la `intro` de la encuesta si el panel la escribió EN EL IDIOMA
  del cliente (sin caer al español dentro de un correo en inglés: entonces, la frase de la casa); el botón
  abre la página por su **TOKEN** de 40 caracteres, la credencial entera como en la invitación (no hay firma que
  caducar); la baja al pie y en la cabecera `List-Unsubscribe`; el asunto SIN el nombre del parque (regla de la
  bandeja) y **sin la palabra «oferta» ni para negarla** (`SurveySendTest` la busca).
- **La página** `GET /encuesta/{token}` en `focused-layout` (sin banner, sin tracker, sin `visitor_id`,
  como las de la fiesta: el grupo `withoutMiddleware(ResolveVisitor:mint)`), `no-store` (`RGPD-04`), en el idioma
  del cliente; `POST` guarda una sola vez (`answered_at` cierra el token) y el hecho `survey_answered` (`channel =
  external`); después, «Gracias». Un token inexistente, contestado o de una encuesta apagada: el mismo 404 (el
  patrón de la invitación). Limitador por IP en el `POST`.
  **Como se construyó (T3)** —y corrige lo de arriba—: `SurveyPageController` en el grupo enfocado (`no-store`,
  `Referrer-Policy: no-referrer`, el idioma es el de `survey_responses.locale`); `POST` tipa y valida con
  `QuestionSchema` y escribe bajo candado (`answerSent()`); «Gracias» vive en `/encuesta/{token}/gracias`
  (POST → redirect → GET). **La baja NO es un enlace que escribe al abrirse**: es `/encuesta/{token}/baja`, una
  página con UN botón (`POST`), porque los escáneres de enlaces de los gestores de correo abren los GET y darían de
  baja a quien no pidió nada; funciona aunque la encuesta ya se contestara. Lo mismo desde «Privacidad» del cajón:
  el tercer interruptor y `PUT /me/surveys` (`AccountPrivacy::setSurveys()`, contrato **1.28.0**,
  `users.surveys_opt_out` en `GET /me`).

### 4.4 El cuadro y la 360

- `app/Filament/Analytics/SurveysReport.php`, capa de entrega, caché 5 min, por **día de la respuesta**
  (`answered_at` → día del parque) dentro de la ventana: por encuesta viva o con respuestas en el periodo: enviadas
  (externa), contestadas por canal, declinadas, **tasa de respuesta** interna (contestadas / visitas acreditadas con
  la encuesta viva y sin fila previa) y externa (contestadas / enviadas), y por pregunta: `choice` → reparto por
  opción; `scale` → media, n y reparto 1–5; `text` → las últimas 12 respuestas (solo en la pestaña, NUNCA en el CSV:
  un texto libre puede llevar un nombre).
- Widgets en una quinta pestaña `surveys` («Encuestas», icono `ChatBubbleLeftRight`): `SurveysOverviewWidget`
  (tarjetas: contestadas, tasa interna, tasa externa, enviadas, declinadas, media de la primera escala),
  `SurveysAnswersChart` (la primera pregunta con opciones de la primera encuesta; sin selector: las tablas llevan
  todas), `SurveysAttentionWidget` («Por atender», vista propia) y `SurveysBreakdownWidget` (por encuesta y pregunta,
  plegada). `CsvExport::REPORT_SURVEYS`: agregados sin texto libre. **La tasa interna se mide sobre OFRECIDAS**
  (`SurveysReport::offered()`): visitas acreditadas en días con una interna viva y de clientes sin fila previa.
- ⚠️ **Corregido por `#754` (§4.7)**: la 360 PIERDE este bloque y «Por atender» se retira (pasa a «Notas bajas y si
  volvieron», sin persona); lo que sigue es la T4 tal como se construyó.
- La 360 gana el bloque «Encuestas»: contestadas, la última (fecha, canal, su puntuación de escala si la hay y su
  texto libre), bajo `customers.insights`. Y la pestaña lleva **«Por atender»**: las respuestas con una escala ≤ 2 de
  los últimos 30 días, con el día, el canal, la puntuación, el texto y el enlace a la ficha del cliente (solo con
  `customers.insights`; sin él, la fila sale sin persona NI texto, `[DECIDIDO owner]` `#742`). Es el valor de atar la respuesta a la persona (§7): una
  mala visita se puede llamar y arreglar; un agregado no.
- `SurveyResource` en «Ajustes → Sistema» (`settings.manage`): lista con clase, estado, respuestas;
  formulario con clave, nombre e intro en tres idiomas, clase, encendido, ventana, y el Repeater de preguntas (tipo,
  rótulos, opciones); con respuestas, claves y tipos bloqueados; una viva por clase (§7·5). Rastro `surveys.saved` /
  `surveys.deleted` (`AuditLog::ACTIONS`). Borrar una encuesta con respuestas: no; se apaga.

### 4.5 Privacidad y retención

- ⚠️ **Corregido por `#754` (§4.7)**: la respuesta deja de ser un dato de la persona (salvo los 90 días del sello);
  `anonymize()` borra su participación y su sello, y el export lleva la participación y las respuestas aún selladas.
- Base jurídica de la respuesta: la relación de servicio (la visita); `[PENDIENTE: asesoría]` (5) para el correo.
- `anonymize()` pone `user_id` a NULL en `survey_responses` y borra las respuestas `text` de esa persona (los
  agregados de `choice`/`scale` sobreviven sin persona). El export (`/me/export`) lleva sus respuestas. Retención:
  `model:prune` a los 24 meses de `answered_at`/`sent_at` (`RGPD-01`).
- El empleado no ve respuestas de otros clientes en la puerta (solo la tarjeta del cliente que tiene delante).

### 4.6 El orden de las tandas

| | Tanda | Entrega | Verificación (§6) |
|---|---|---|---|
| T1 | **✅ (25-09; visto bueno del owner el 27-09 noche, en la misma pasada que la T5) el modelo y el panel**: la migración `create_surveys_tables` (`surveys`, `survey_responses`, `users.surveys_opt_out`), `Platform\Models\Survey` (`isRunning()`, `runningOfKind()`, `anotherRunning()`, `questionList()`) y `SurveyResponse` (`MassPrunable` a 24 meses, `forgetPerson()`), `Platform\Services\Surveys\QuestionSchema` (cinco tipos, `normalize()`, `accepts()`), `SurveyResource` en «Ajustes → Sistema» (`/admin/encuestas`, `settings.manage`; formulario con pestañas es/en/fr, el Repeater de preguntas con sus opciones; con respuestas la clave, la clase y la estructura van bloqueadas PERO dehidratadas para que los rótulos editados casen con su pregunta —`GuardsSurveyForm`—; una viva por clase al encender; sin respuestas se borra con rastro, con respuestas no), `AuditLog::ACTIONS` +2, alias de morfo, `Contract` +3 (`survey_sent`, `survey_answered`, `survey_declined`), `anonymize()` y `model:prune`. **El export del cliente (`AccountPrivacy::exportFor()`, contrato `PersonalDataExport` 1.27.0) entró en la T2.** **Lo que enseñó**: un campo `disabled()` no viaja al guardar y sin la clave el Repeater no puede casar los rótulos: `disabled()->dehydrated()` y la guarda del servidor decide; el censo de tarjetas de «Ajustes» (`AdminNavigationTest`) se teclea a mano (23 → 24) | `#740` | `SurveyResourceTest` (7), `QuestionSchemaTest` (3), `SurveyPrivacyTest` (4); `MorphMapTest`, `AdminNavigationTest`, `AuditActionCatalogTest`, `AnalyticsContractTest` en verde · el OJO del owner en `/admin/encuestas` |
| T2 | **✅ (25-09; disparador `#741`: el ESCANEO acredita la visita; el owner la contestó EN VIVO desde la puerta y dio su visto bueno el 27-09 noche con la T5) la puerta**: `Platform\Services\Surveys\SurveyResponses` (la oferta —viva y sin fila del cliente—, `answer()`/`decline()` con el índice único como árbitro y el hecho; Platform no mira a Identity: el cliente es un `int`), `QuestionSchema::fromForm()`/`validate()` (tipa y valida en el servidor), la tarjeta `gate-survey` DEBAJO de «Hoy» en `ValidarRegistro` (`$survey` con estados `offer`/`open`/`answered`/`declined`, `$surveyId` bloqueado, radios ocultos con etiqueta-botón ≥ 44 px y `wire:model` diferido; muere con la ficha), `puerta.survey_answered`/`puerta.survey_declined` (target el cliente), el export (`surveys`, contrato 1.27.0), rótulos es/zh_CN. **Lo que enseñó**: el botón de la visita no existe (§1); los errores de validación de Livewire sobreviven al siguiente intento si no se resetean (`resetErrorBag()` antes de juzgar); `PersonalDataExport` es `additionalProperties: false`, así que un campo nuevo en el export es contrato | `#740` | `GateSurveyTest` (9), `SurveyPrivacyTest` +1 (el export), `scripts/mutar-encuestas.sh` **9/9 + 1 control**; `MePrivacyTest`, `ApiContractTest`, `GateKioskTest`, `ModuleBoundariesTest` en verde · `sonda-puerta.mjs` **28/28** (retirada el 02-10 con la P1b de la Puerta nueva, `puerta-nueva.md` §4.4: la encuesta pregunta a pregunta la mide `sonda-puerta-p1.mjs`) (tablet 1080×810 y móvil 390: la tarjeta DEBAJO de «Hoy» y lejos del buscador, el lector con el cursor antes, con la ficha, tras cerrar la encuesta y tras «Nueva búsqueda» —ese último lo arregló la sonda: `clear()` no devolvía el foco—, 11 objetivos ≥ 44 px, sin desborde; dos controles). **Lo que enseñó la sonda**: el cliente de sonda de siempre ya tenía respuesta (la del owner) y la tarjeta no salía: una por cliente funciona; la sonda usa un cliente propio (`sonda-puerta@jumpweb.test`) |
| T3 | **🟦 (25-09; el correo en Mailpit y la página, vistos y aprobados por el owner el 27-09 noche con la T5; queda su ojo en el interruptor de «Privacidad» del cajón) el correo y la página**: `surveys:send-external` (`SendExternalSurveys`, cada hora desde las 10:00 del parque; +1 tarea: `deploy.sh` espera 10), `SurveyInvitation` sobre el molde con `List-Unsubscribe`, `SurveyResponses::send()/openByToken()/answerSent()`, `SurveyPageController` (página, respuesta, gracias, baja con UN botón y su confirmación: 5 rutas en el grupo enfocado), `surveys.cooldown_days` en «Ajustes → Puerta» (`SurveySettings`), `PUT /me/surveys` + `surveys_opt_out` en `GET /me` (contrato 1.28.0) + el tercer interruptor de «Privacidad» (`privacy.js`, `PrivacyZone.vue`), textos es/en/fr (`surveys.php`) y es/zh_CN. **Lo que enseñó**: el asunto no lleva el nombre del parque (`MailInboxLineTest`); la palabra «oferta» no entra ni para negarla; el token es la credencial y no hace falta firmar la URL; la baja de «un toque» es un botón en una página, no un GET que escribe | contrato 1.28.0 | `SurveySendTest` (6), `SurveyPageTest` (5), `MeSurveysTest` (2), `FocusedPagesAreCookieFreeTest` (17 rutas), `EmailUtmTest` (27), `MailMoldTest`, `MailInboxLineTest`, `ApiContractTest` en verde; `mutar-encuestas.sh` +4 · queda el OJO en Mailpit y en la página |
| T4 | **✅ (25-09; la pestaña, vista y aprobada por el owner el 27-09 noche con la T5; la 360 perdió el bloque en la T5) el cuadro**: `SurveysReport` (por DÍA DE LA RESPUESTA; las dos tasas: en la puerta = contestadas entre visitas acreditadas del periodo, por correo = contestadas entre mandadas; por encuesta —la interna viva, la externa viva y las que tengan filas— y por pregunta el reparto de cada tipo, la media de la escala, los últimos 12 textos; «Por atender» = escala ≤ 2 en los últimos 30 días, las peores primero, con `user_id`; seis consultas), la quinta pestaña «Encuestas» (`SurveysOverviewWidget` seis tarjetas, `SurveysAnswersChart` la primera pregunta con opciones de la primera encuesta, `SurveysAttentionWidget` con la persona SOLO con `customers.insights` y su vista propia, `SurveysBreakdownWidget` con los textos solo en la pestaña), `CsvExport::REPORT_SURVEYS` (sin un texto libre), el bloque «Encuestas» de la 360 (`CustomerInsights::surveys()`: cuántas y la última con su nota y su texto), rótulos es/zh_CN, fixture local `probe-ojo-encuestas` (64 filas sobre los 32 anfitriones de la fiesta). **Lo que enseñó**: un ayudante `seed()` en un test es FATAL (otra vez); la sonda del panel censa CUATRO «Por día» y cinco pestañas | `#740` | `SurveysReportTest` (5: cifras y tasas, por encuesta y pregunta, «Por atender», presupuesto ≤ 20, los textos solo en la pestaña), `AnalyticsPageTest` y `UserInsightsInfolistTest` +1 en verde; `sonda-analitica-panel.mjs` con la quinta pestaña y el CSV `surveys` · queda el OJO |
| T5 | **✅ (27-09, con los siete ajustes de `#757`; la puerta, el correo, la página y la pestaña, vistos y aprobados por el owner: «está perfecto, visto bueno») el anonimato** (`#754`, §4.7; lo construido, en su «Cómo se construyó»): participación y respuesta sin clave común, franja en vez de hora, `first_visit` y `visit_kind`, el sello de 90 días y `surveys:resolve-returns`, `ForgetSurveySeals`, el mínimo de 5 por celda, «Notas bajas y si volvieron» en lugar de «Por atender», la 360 sin encuestas, `puerta.survey_closed`, el aviso fijo en la puerta, el correo y la página, el export y su contrato | `#754` | las guardas de §4.7 con su mutación · `curl` a la página (el aviso, `no-store`) · `sonda-puerta.mjs` (el aviso en la tarjeta, sin tapar el lector) · `sonda-analitica-panel.mjs` (ninguna celda < 5) · el OJO del owner en la tablet, el correo y la pestaña |

### 4.7 El anonimato (T5, `#754`, 27-09) — ✅ construida (`#757`) y aprobada por el owner (27-09 noche)

> ▶ **Construida el 27-09 con siete ajustes (`#757`)**, que van al final de esta sección («Cómo se construyó»). Lo que
> sigue es el diseño aprobado; donde el ajuste lo corrige, manda el ajuste.

**Qué significa aquí «anónima»**, y es la vara de todo lo demás: **nadie, desde el panel o sus exportaciones, puede
saber qué contestó una persona; y a los 90 días, tampoco con acceso a la base de datos**. El aviso promete eso, ni más
ni menos.

**Medido (27-09)** antes de diseñar:
- `survey_responses` guarda `user_id`, `answered_by`, `visited_on`, `token`, `sent_at` y `answered_at`/`declined_at` con
  hora; el índice único `(survey_id, user_id)` es el árbitro de «una por cliente».
- «Por atender» (`SurveysReport` + `SurveysAttentionWidget`) une `users` por `user_id` y enseña nombre y texto; la 360
  (`CustomerInsights::surveys()`) enseña la última nota y su texto; el export (`PersonalDataExport.surveys`, contrato
  1.27.0) lleva las respuestas.
- `SurveyResponses` escribe `survey_answered`/`survey_declined` con `user_id` en el libro, y la puerta audita
  `puerta.survey_answered`/`puerta.survey_declined` con el cliente como `target`.
- El panel tiene «Registro de actividad» (`AuditLogs`): hora, acción, empleado y cliente de cada escaneo
  (`puerta.card_scanned`). **Con la hora exacta y el empleado en la respuesta, cualquiera con ese registro la une a su
  persona**: por eso la franja.
- Ningún texto de la puerta, del correo ni de la página habla de anonimato (`grep -i anónim` en `lang/es/surveys.php`
  y en las vistas: cero).

**Las filas, sin clave común**:
- `survey_participations` — **a quién se preguntó**, sin respuestas: `survey_id`, `user_id` (se borra al
  anonimizar), `channel`, `asked_by` (el empleado, solo interna), `sent_at` (solo externa), `token_hash` (solo
  externa: `HMAC(token, 'lookup')`; el token en claro solo viaja en el correo). Índice único `(survey_id, user_id)`: sigue
  siendo el árbitro de «una por cliente» (`#740` §2) y la fuente del plazo entre correos y de la baja. **No guarda el
  desenlace** (contestó o dijo que no): nace al cerrar la tarjeta de la puerta o al mandar el correo y no se vuelve a tocar.
- `survey_responses` (rehecha) — **qué se contestó**, sin persona: clave **UUID v4 aleatoria** (ni autoincremental ni
  ULID: el orden de inserción uniría las dos tablas), `survey_id`, `channel`, `answered_on` (día del parque), `band`
  (`morning` hasta las 13:00, `midday` hasta las 16:00, `afternoon` después, hora del parque), `asked_by` (el empleado, solo
  interna; `[DECIDIDO owner]` 27-09), `first_visit` (¿tenía antes una visita acreditada o una reserva cobrada?),
  `visit_kind` (`entry` · `party` · `group` · `other`, del pedido de la visita), `declined` («No preguntar» también es una
  fila anónima, para la tasa), `answers` json, `locale` y lo de «volvió» (abajo). **Sin `timestamps()`**.
- `survey_spent_tokens` — `hash` = `HMAC(token, 'spent')`, sin fecha ni clave foránea: cierra el token externo
  sin tocar la participación. Unir las dos exige el token en claro, que solo tiene el destinatario.

**«Volvió quien puntuó mal»** (la opción «a» del owner):
- La respuesta guarda `seal` = el id del cliente **cifrado** (`Crypt::encryptString`, IV aleatorio) y `seal_until` = día +
  90. Nunca un cast `encrypted` (descifraría al cargar el modelo en cualquier pantalla): se descifra en UN sitio.
- `surveys:resolve-returns`, diario: si hay una visita acreditada o una reserva cobrada con fecha de visita en
  (día, día + 90] → `returned = true` y `returned_after_days`; si pasó el plazo → `returned = false`. En los dos casos
  **`seal` a null en la misma escritura**. +1 tarea del planificador (`deploy.sh`; aviso a plataforma en el buzón).
- `anonymize()` encola `ForgetSurveySeals` (descartado en `#757`: lo hace `SurveySeals::forget()` DENTRO de la
  transacción): recorre los sellos vivos (≤ 90 días, pocos), descifra y borra el suyo. El export de la persona (art. 15) lleva su participación y las respuestas **aún selladas** (las encuentra el
  mismo recorrido): mientras hay sello es un dato suyo. `[PENDIENTE: asesoría]` los 90 días y su texto en `/privacidad`.

**Lo que enseña el panel**:
- **Ningún cruce de menos de 5 respuestas** (`SurveysReport::MIN_CELL` = 5): por pregunta, franja, empleado, tipo
  de visita, primera visita o «volvió»; la celda dice «menos de 5».
- Los textos libres salen **sin día, franja ni empleado, en orden aleatorio** y solo con 5 o más en el periodo: «el
  último» junto a la participación de ayer lo destaparía.
- «Por atender» **se retira** (el owner: «para llamarle, no»); en su lugar, **«Notas bajas y si volvieron»**: cuántas
  notas ≤ 2 y qué parte volvió en 90 días, frente al resto (con los mínimos de arriba). La 360 **pierde el bloque
  «Encuestas»**. El CSV lleva los mismos mínimos y ningún texto.

**Lo que deja de escribirse**: `survey_answered` y `survey_declined` en el libro (su hora exacta y el cliente unirían el
desenlace; el cuadro cuenta de su tabla), y los dos rastros de la puerta pasan a UNO, `puerta.survey_closed` (target el
cliente: quién preguntó a quién, sin desenlace; `SEC-05`). `survey_sent` sigue con persona: mandar es de la participación.

**El aviso** — texto del PRODUCTO en `lang/{es,en,fr}/surveys.php` (y es/zh_CN en el panel), fijo, que el operador **no
puede editar ni quitar**; el mismo en la tarjeta de la puerta (para leerlo en voz alta), en el correo (tras la intro) y
arriba de la página:
- «Nadie en el parque verá tu nombre junto a tus respuestas, y a los 90 días se separan de ti del todo.»
- Junto a cada pregunta de texto: «Si quieres seguir en el anonimato, no escribas tu nombre ni datos personales.»
- En el formulario de `SurveyResource`, para el operador: «Las encuestas son anónimas: no preguntes el nombre, el
  teléfono ni el correo.»

**Residuos declarados** (lo que el diseño NO cierra, y por qué se acepta): (1) con acceso a la base de datos, en una
franja en la que un empleado preguntó a UNA sola persona, se deduce por eliminación (el panel no lo enseña: mínimo 5);
(2) el registro binario de MariaDB y las copias guardan el orden de escritura; (3) los registros del servidor web guardan
la URL con el token y su hora. Los tres exigen acceso al servidor, no al panel.

**Migración y contrato**: nada está desplegado (espera la v2.0.0, `#670`): una migración hacia delante parte las filas
locales —participación desde `(survey_id, user_id, channel, answered_by, sent_at)`, respuesta sin persona ni hora y sin
sello (no se sella hacia atrás)— y suelta las columnas viejas. `PersonalDataExport.surveys` cambia de forma (subida menor
del contrato; el número, tras el último de plataforma al implementar).

**Guardas**, cada una con su mutación en `scripts/mutar-encuestas.sh`: el censo de columnas de `survey_responses` sin
`user_id`, sin hora y sin clave secuencial · `seal` solo lo leen el comando y el recorrido del export y del anonimizado ·
una celda con 4 respuestas no se pinta · los textos no salen por fecha · el aviso en tres idiomas en la puerta, el correo
y la página (rótulo tecleado a mano, `#734`) · `resolve-returns` borra el sello al resolver y al caducar · `anonymize()`
borra el sello de esa persona y no el de otra (control).

**Cómo se construyó (27-09, `#757`)** — y corrige lo de arriba donde lo dice:
- **Migración** `make_survey_responses_anonymous`: crea `survey_participations` (`asked_on` = el día de la VISITA que la
  originó; `locale` solo en el correo; sin `timestamps()`) y `survey_spent_tokens`, rehace `survey_responses` (clave
  `uuid`, sin persona, sin hora, **sin idioma**) y parte las filas locales hacia delante, sin sello. `down()` recupera la
  forma, no la unión. Modelos `SurveyParticipation` (poda por `asked_on`, `forgetPerson()` suelta `user_id` y el hash) y
  `SurveyResponse` (clave `Str::uuid()` v4 al crear —el `HasUuids` de Laravel da v7, ORDENADO—; `seal` oculto y no
  asignable; `bandAt()` sobre la hora del PARQUE).
- **La costura**: `Platform\Contracts\VisitFacts` (primera vez, tipo de visita, primera vuelta) lo implementa
  `Identity\Services\CustomerVisitFacts` con las visitas de la puerta y `Booking\Contracts\PaidVisits`
  (`PaidVisitsReader`, sobre `paidScheduledPrincipal()`). Tipo: un pack › un producto con tramos por volumen › entrada ›
  `other` (nada cobrado para ese día).
- **`SurveySeals`**, la ÚNICA clase que nombra la columna: `stamp()` (cifra), `resolve()` (la pasada), `forget()` (art. 17,
  DENTRO de `User::anonymize()`), `sealedFor()` (art. 15) y `answeredInPersonOn()`: el correo del día siguiente no se lo
  repite a quien CONTESTÓ en la puerta (`#742`) leyendo los sellos de ayer, sin guardar el desenlace. «Volvió» cuenta hasta
  AYER (días vividos) — y por eso ningún sello de ayer se resuelve antes de que salga el correo—; `seal_until` no existe
  (es el día + 90). `surveys:resolve-returns` a las 04:20 (+1 tarea: `deploy.sh` espera **11**).
- **`SurveyResponses`**: `answerInPerson()`/`declineInPerson()` escriben participación y respuesta en una transacción (la
  participación es el árbitro); `send()` devuelve el token EN CLARO, que solo viaja en `SurveyInvitation`; `answerSent()`
  gasta el token (clave primaria) y responde en la misma transacción. Contestar y declinar ya no dejan hecho (fuera del
  `Contract`); la puerta audita `puerta.survey_closed` con `{survey}` y nada más.
- **El cuadro**: `SurveysReport` lee las dos tablas sin unirlas; `enough()` aplica el mínimo al GRUPO detrás de cada cifra
  (la media, el reparto de una pregunta, los textos, el «volvió» de un grupo); los textos, una muestra en el orden de la
  clave aleatoria. `SurveysLowScoresWidget` («Notas bajas y si volvieron») sustituye a «Por atender»: «ya han vuelto» sobre
  TODAS las del periodo, una cota que crece con los días e igual para los dos grupos. La 360 pierde el bloque. El CSV
  lleva los mismos mínimos. El export pasa a `{participations, sealed_responses}` (contrato **1.46.0**).
- **El aviso**: `surveys.notice` y `surveys.page.text_hint` (es/en/fr) en el correo y la página; en la puerta y en el
  formulario del panel, sus claves de `admin.php` (es/zh_CN).
- **Residuo (4), declarado**: el mínimo se aplica a cada cifra, pero **dos periodos que se solapan** («este mes» y «hasta
  ayer») se pueden RESTAR y aislar un día; si ese día contestó una sola persona y el registro de actividad dice a quién
  se preguntó, se deduce. Exige intención y acceso al registro: el mínimo frena la lectura casual, no un ataque.
- **Tests**: `SurveyAnonymityTest` (censos de columnas tecleados, clave v4, un solo lector del sello, el libro) ·
  `SurveyReturnsTest` · `SurveyVisitFactsTest` · `GateSurveyTest`, `SurveySendTest`, `SurveyPageTest`,
  `SurveyPrivacyTest` y `SurveysReportTest` rehechos a propósito (el borde 4/5, el orden de los textos, las notas bajas)
  · `MePrivacyTest` +1 (el export contra el contrato) · `UserInsightsInfolistTest` (la 360 SIN encuestas). Arnés
  `scripts/mutar-encuestas.sh` rehecho: **31/31 + control**, y UN superviviente DECLARADO: «la interna se ofrece sin
  acreditar la visita» es equivalente desde `#756` (toda ficha abierta ya lleva la visita de hoy; la guarda queda como
  defensa). Sondas: `sonda-puerta.mjs` 32/32 (el aviso dentro de la tarjeta y lejos del lector) y
  `sonda-analitica-panel.mjs` en verde («Por atender» ya no está; «Notas bajas frente al resto» sí).
- **Lo que enseñó**: (1) el `HasUuids` de Laravel da UUID **v7**, ORDENADO por tiempo: habría unido las dos tablas por el
  orden. (2) La guarda del lector del sello cazaba dos métodos AJENOS llamados `seal()`: se busca la PROPIEDAD, no el método.
  (3) Una respuesta sin resolver y sin sello (anonimizada, o de antes de medir) contaba como «aún puede volver» PARA SIEMPRE:
  pasado su plazo es «no se sabe», decidido por la fecha (el informe no lee el sello) y fuera de la tasa. (4) La idea
  «sin desenlace en la participación» chocaba con `#742` (no repetir por correo a quien contestó): lo resuelve el sello
  que ya existía, sin dato nuevo. (5) Tocar la «HOJA ENFOCADA» exige regenerar `public/css/cajon.css` (solo cambió su huella).

## 5. Impacto en invariantes

`RGPD-01` (purga y export cubren `survey_responses` y `surveys_opt_out`; prune) · `RGPD-04` (`no-store` en la
página del correo y en la puerta) · `RGPD-07` (el libro solo lleva `survey`, `channel` y `user_id` de contrato;
ninguna respuesta) · `SEC-04` (re-autorizar al guardar en la puerta) · `SEC-05` (la puerta audita) · `PAY-14` (la
notificación en cola) · `SUITE-01`. Dinero, aforo: ninguno.

## 6. Plan de verificación empírica

- Tests por tanda (§4.6) y un arnés `scripts/mutar-encuestas.sh`: la interna se ofrece antes de
  acreditar (muere), una segunda respuesta del mismo cliente entra (muere), el comando manda dos veces (muere), el
  token contestado vuelve a abrir (muere), el CSV lleva un texto libre (muere), y un CONTROL.
- `curl -D` a `/encuesta/{token}`: 200, `no-store`, sin `visitor_id`, cero `<script>` de terceros.
- Presupuesto de `SurveysReport` (≤ 20, sin crecer con las respuestas) y `EXPLAIN` sobre el fixture.
- Sondas: la puerta en tablet (la tarjeta no tapa el lector; objetivos ≥ 44 px), el panel (quinta pestaña, CSV),
  el correo en Mailpit (las tres lenguas, el botón, la baja).
- El OJO del owner: la tablet de la puerta con un cliente de prueba, el correo, la página, y la pestaña.

## 7. Revisión y decisión

- **24-09, owner**: «Sistema de encuestas: internas —el empleado, al escanear el QR, abre un banner y pregunta en
  persona; él marca con el dedo— y externas —al escanear el QR, al día siguiente un correo amigable—; se crean en el
  panel (interna o externa, activa o desactivada, con fecha límite o sin ella) y salen en las analíticas». Y: «crea
  la spec, procede con rigor, cualquier ambigüedad me preguntas de manera simple».
- **Cinco preguntas y sus respuestas (24-09, `[DECIDIDO owner]`, `#740`)**:
  1. **Las respuestas**: el owner no lo tenía claro («si están atadas al cliente podrían darnos más info, pero no
     estoy seguro: ¿cómo sería hacerlo profesional y que de verdad aporte valor?») y aceptó la recomendación: **atadas
     al cliente y a su visita**. Por qué: (i) una mala puntuación con nombre se puede llamar y arreglar («Por
     atender», §4.4); un agregado anónimo no; (ii) permite cruzar la satisfacción con lo que el negocio ya sabe de esa
     persona —primera visita o habitual, fiesta o entrada, si VOLVIÓ después de puntuar bajo—, que es la información
     de valor; (iii) es lo que hace posible «una vez por cliente» (§7·2) sin preguntar dos veces; (iv) la persona no
     entra en el cuadro: solo en su ficha y en «Por atender», con permiso propio, y se borra con su cuenta. Lo
     profesional no es no atar, sino atar y ENSEÑAR agregados por defecto.
  2. **Cuántas veces**: una respuesta por cliente y encuesta, «una vez nada más».
  3. **Tipos de pregunta**: todos —elección única, elección múltiple, escala de 1 a 5, sí/no y texto libre—.
  4. **El correo externo**: «la opción legal que nos lo permita, priorizando el interés de tener esa información y
     dar valor al cliente» → correo de SERVICIO a todo el que visitó, sin contenido comercial, con baja de un toque;
     `[PENDIENTE: asesoría]` (5) lo confirma.
  5. **Encuestas vivas a la vez**: una interna y una externa como máximo.
- Aprobada: `#740`; la casilla T7 del tracker dice «spec ✅ · T1 ⬜» hasta que la T1 entre en `main`.
- **27-09, owner — ANÓNIMAS (`#754`)**: «quiero que sean siempre anónimas, y se avise al usuario, tanto internas como
  externas». Ante lo que se perdía: «el día, la hora y el empleado vale, pero el cliente no» (la hora pasó a FRANJA
  al medir el registro de actividad, §4.7; vetable) y «es importante saber si una persona puntuó mal y volvió; para
  llamarla, no» → la opción «a», el sello de 90 días. Los dos datos gruesos (primera visita, tipo de visita): «correcto».

## Anexo · fila del enrutador

| Encuestas · internas en la puerta · externas por correo · sus resultados en el cuadro | `docs/specs/encuestas.md` §0 |
