# [SPEC] Las encuestas — internas en la puerta y externas por correo, creadas en el panel, medidas en el cuadro

> Estado: ⬜ borrador (24-09, carril del SPA) · Última actualización: 2026-09-24 · Decisión asociada: `#740` al
> aprobarse (banda del SPA). Es la **T7** de `analitica.md` (§4.8 y §4.10, las palabras del owner). **Cinco preguntas
> al owner en §7**; el diseño de abajo asume la opción recomendada de cada una y se corrige con su respuesta.

## §0 · Antes de tocar

- **La regla que ordena todo**: una encuesta es DATO del panel (preguntas, tipos, textos en tres idiomas, ventana,
  clase), nunca código; el sistema solo sabe **cuándo preguntar** —al acreditar la VISITA en la puerta, o al día
  siguiente por correo— y **dónde contar**. Las RESPUESTAS viven en su tabla, atadas a la visita y al cliente
  (régimen del contrato de servicio), y en el libro de eventos entra solo el HECHO (`survey_sent`, `survey_answered`,
  `survey_declined` con la clave de la encuesta y el canal): ninguna respuesta pisa el libro (`RGPD-07`).
- **Empieza por** §1 (lo medido) → §4.1 (el modelo) → §4.2 (la puerta) → §4.3 (el correo) → §4.4 (el cuadro) → §4.6.
- **Trampas medidas (24-09)**: (1) **la puerta es un KIOSCO de tablet** (`panel-navegacion.md` §8): nada tapa
  «Registrar visita» ni el lector, la ficha CADUCA en servidor, y la encuesta se ofrece DESPUÉS de acreditar la
  visita. (2) **La visita acreditada es idempotente por (cliente, día)** (`GateVisits::register()`,
  `customer_visits`): la externa se dispara desde esa fila. (3) **Todo correo nace por partida doble** sobre
  `BrandedMailMessage`, con sus piezas de bandeja en tres idiomas (`MailInboxLineTest`); `EmailUtm::keys()` lo censa
  por estar en `app/Notifications/`. (4) **Una tarea nueva del planificador sube el recuento de `deploy.sh`** (hoy
  espera 6 y `schedule:list` registra 9: avisado a plataforma). (5) Un Resource nuevo va a
  `AdminSettingsHub::areas()` o `AdminNavigationTest` se pone rojo. (6) `RGPD-01`: purga y export cubren las
  tablas nuevas; `RGPD-04`: la página del correo va `no-store`.
- **Estado**: ⬜ borrador con cinco `[PENDIENTE: owner]` (§7). Queda: sus respuestas → `#740` → T1→T4 (§4.6).
- **Invariantes**: `RGPD-01`, `RGPD-04`, `RGPD-07`, `SEC-04`, `SEC-05`, `PAY-14`, `SUITE-01`. Dinero y aforo:
  ninguno. Ningún fichero del `CRITICAL_RE`.

## 1. Contexto y problema — MEDIDO (2026-09-24)

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

- `surveys` `(futuro)`: `id`, `key` (`^[a-z][a-z0-9_-]{0,47}$`, única), `name` json es/en/fr, `kind`
  (`internal`|`external`), `active` bool, `starts_at`, `ends_at` (nulos = sin plazo), `intro` json es/en/fr (la
  frase de cabecera del correo o de la tablet), `questions` json, `created_by`, timestamps. **Vivo** = activa y
  dentro de su ventana (la regla de `Experiment::isRunning()`). `[PENDIENTE: owner]` §7·5: **una viva por clase**
  (recomendado; el panel lo valida al encender) o varias.
- `questions`: lista ordenada de `{key, type, required, label{es,en,fr}, options?}` con `type` ∈ `choice` (una
  opción de una lista `options: [{key, label{es,en,fr}}]`), `scale` (1 a 5, con rótulos de extremos opcionales) y
  `text` (libre, ≤ 300 caracteres) — `[PENDIENTE: owner]` §7·3. Normalizada por `Platform\Services\Surveys\
  QuestionSchema` `(futuro)` con la misma disciplina que `normalizeFieldSchema()` (sin clave → fuera; tipo inválido →
  `choice`; claves únicas). **Con respuestas guardadas, las claves y los tipos se BLOQUEAN** (los textos siguen
  editables): cambiar una pregunta a mitad mezcla lo medido, como los pesos de un experimento.
- `survey_responses` `(futuro)`: `id`, `survey_id`, `user_id` (nullable, `SET NULL` al anonimizar), `visited_on`
  (el día de la visita que la originó), `channel` (`internal`|`external`), `answered_by` (el empleado, solo interna),
  `token` (40 caracteres, solo externa: abre la página), `sent_at`, `answered_at`, `declined_at`, `answers` json
  `{clave: valor}`, `locale`. Índices: `(survey_id, user_id)` ÚNICO —**una respuesta por cliente y encuesta**,
  `[PENDIENTE: owner]` §7·2— y `(survey_id, answered_at)`. `[PENDIENTE: owner]` §7·1: atadas al cliente (recomendado)
  o anónimas (entonces `user_id` no se guarda y la unicidad va por `visited_on` + un hash).
- `users.surveys_opt_out` `(futuro)` bool: «no quiero recibir más encuestas», con su interruptor en «Privacidad» del
  cajón (`PUT /me/surveys` `(futuro)`, el molde de `PUT /me/marketing`) y el enlace firmado del correo.
- El libro: `Contract` gana `survey_sent`, `survey_answered` y `survey_declined` (servidor; props `survey`, `channel`;
  ref `user_id`, régimen del contrato como `visit_checked_in`). Ninguna respuesta entra en el libro.

### 4.2 La interna: en la puerta, con la persona delante

- **Cuándo**: solo tras `registerVisit()` con éxito (o si la visita de hoy ya estaba acreditada), si hay una encuesta
  interna viva y este cliente no tiene fila para ella (ni contestada ni declinada). Una encuesta contestada no
  vuelve a ofrecerse; una declinada tampoco.
- **Dónde**: una tarjeta `gate-survey` `(futuro)` en la columna principal de la ficha, DEBAJO de «Hoy», con el
  nombre de la encuesta, «N preguntas» y dos botones: «Preguntar» y «No preguntar» (esta última escribe
  `declined_at`). Nunca un modal sobre el lector ni encima de «Registrar visita» (§0·1).
- **Cómo**: al abrir, las preguntas una a una o en lista corta (según el tipo): `choice` = un botón por opción;
  `scale` = cinco botones grandes; `text` = un campo corto (el empleado teclea si el cliente quiere). Todo con
  los objetivos táctiles de la puerta (≥ 44 px, §8 de la navegación). «Guardar» escribe la fila (`channel =
  internal`, `answered_by` el operador, `visited_on` hoy) y el hecho `survey_answered`; la tarjeta pasa a «Contestada».
- **Permisos**: el de validar (`registrations.validate`): quien acredita la visita pregunta. `SEC-04`: se re-autoriza
  al guardar. `SEC-05`: sin limitador propio (una respuesta por cliente y encuesta ya acota). Auditoría:
  `puerta.survey_answered` y `puerta.survey_declined` (target el cliente).
- **La ficha caduca en servidor**: la encuesta abierta vive dentro de la misma ventana de la ficha; si caduca, se
  cierra sin guardar y se vuelve a ofrecer en la siguiente búsqueda.

### 4.3 La externa: el correo del día siguiente

- **El comando** `surveys:send-external` `(futuro)`, programado a las **10:00 del parque** (`DisplayTime`), en
  `routes/console.php` (+1 tarea: `deploy.sh` «esperadas» sube EN EL MISMO commit, §0·4). Por cada encuesta externa
  viva: los clientes con `customer_visits.visited_on` = AYER (día del parque), con correo verificado, no
  anonimizados, sin `surveys_opt_out`, sin fila para esa encuesta y **sin ningún correo de encuesta en los últimos
  30 días** (`surveys.cooldown_days` `(futuro)`, ajuste de «Ajustes → Puerta», 30 por defecto). Por cada uno: fila
  `survey_responses` (`sent_at`, `token`) y la notificación en cola. `--dry-run` cuenta sin mandar. Idempotente:
  reejecutar no manda dos veces (la fila ya existe).
- **La notificación** `SurveyInvitation` `(futuro)` sobre `BrandedMailMessage`: `hero('surveys.mail', 'info')`, la
  `intro` de la encuesta en el idioma del cliente, el botón «Contestar» a la página firmada, y al pie el enlace
  «No quiero recibir más encuestas» (firmado, un toque). Piezas de bandeja en tres idiomas en `lang/{es,en,fr}/
  surveys.php` `(futuro)`. `[PENDIENTE: owner]` §7·4: correo de servicio a todo visitante con opt-out (recomendado)
  o solo con `marketing_opt_in`. `[PENDIENTE: asesoría]` (5): la naturaleza del correo (servicio, no comercial).
- **La página** `GET /encuesta/{token}` `(futuro)` en `focused-layout` (sin banner, sin tracker, sin `visitor_id`,
  como las de la fiesta: el grupo `withoutMiddleware(ResolveVisitor:mint)`), `no-store` (`RGPD-04`), en el idioma
  del cliente; `POST` guarda una sola vez (`answered_at` cierra el token) y el hecho `survey_answered` (`channel =
  external`); después, «Gracias». Un token inexistente, contestado o de una encuesta apagada: el mismo 404 (el
  patrón de la invitación). Limitador por IP en el `POST`. `GET /encuesta/baja/{token}` `(futuro)` pone
  `surveys_opt_out` y confirma.

### 4.4 El cuadro y la 360

- `app/Filament/Analytics/SurveysReport.php` `(futuro)`, capa de entrega, caché 5 min, por **día de la respuesta**
  (`answered_at` → día del parque) dentro de la ventana: por encuesta viva o con respuestas en el periodo: enviadas
  (externa), contestadas por canal, declinadas, **tasa de respuesta** interna (contestadas / visitas acreditadas con
  la encuesta viva y sin fila previa) y externa (contestadas / enviadas), y por pregunta: `choice` → reparto por
  opción; `scale` → media, n y reparto 1–5; `text` → las últimas 12 respuestas (solo en la pestaña, NUNCA en el CSV:
  un texto libre puede llevar un nombre).
- Widgets en una quinta pestaña `surveys` («Encuestas», icono `ChatBubbleLeftRight`): `SurveysOverviewWidget`
  `(futuro)` (tarjetas: contestadas, tasa interna, tasa externa, enviadas, declinadas, media de la primera escala),
  `SurveyQuestionsChart` `(futuro)` (una barra por opción de la encuesta elegida), `SurveysBreakdownWidget` `(futuro)`
  (por encuesta y pregunta, plegada). `CsvExport::REPORT_SURVEYS` `(futuro)`: agregados sin texto libre.
- La 360 gana «Encuestas contestadas: n · última: fecha» (contrato; sin las respuestas, que se ven en la fila).
- `SurveyResource` `(futuro)` en «Ajustes → Sistema» (`settings.manage`): lista con clase, estado, respuestas;
  formulario con clave, nombre e intro en tres idiomas, clase, encendido, ventana, y el Repeater de preguntas (tipo,
  rótulos, opciones); con respuestas, claves y tipos bloqueados; una viva por clase (§7·5). Rastro `surveys.saved` /
  `surveys.deleted` (`AuditLog::ACTIONS`). Borrar una encuesta con respuestas: no; se apaga.

### 4.5 Privacidad y retención

- Base jurídica de la respuesta: la relación de servicio (la visita); `[PENDIENTE: asesoría]` (5) para el correo.
- `anonymize()` pone `user_id` a NULL en `survey_responses` y borra las respuestas `text` de esa persona (los
  agregados de `choice`/`scale` sobreviven sin persona). El export (`/me/export`) lleva sus respuestas. Retención:
  `model:prune` a los 24 meses de `answered_at`/`sent_at` (`RGPD-01`).
- El empleado no ve respuestas de otros clientes en la puerta (solo la tarjeta del cliente que tiene delante).

### 4.6 El orden de las tandas

| | Tanda | Entrega | Verificación (§6) |
|---|---|---|---|
| T1 | **el modelo y el panel**: migraciones, `Survey` y `SurveyResponse`, `QuestionSchema`, `SurveyResource` con su formulario y bloqueos, `AuditLog::ACTIONS` +2, `AdminSettingsHub`, `Contract` +3, `anonymize()`/export/prune | `#740` | `SurveyResourceTest`, `QuestionSchemaTest`, `SurveyPrivacyTest` `(futuro)` |
| T2 | **la puerta**: la tarjeta y el formulario táctil en `ValidarRegistro`, la oferta tras la visita, «No preguntar», hechos y auditoría | | `GateSurveyTest` `(futuro)`; la sonda de la puerta (kiosco, ≥ 44 px, el lector sigue libre); el OJO en la tablet |
| T3 | **el correo y la página**: `surveys:send-external` (+1 tarea, `deploy.sh`), `SurveyInvitation`, `/encuesta/{token}` y la baja, `surveys_opt_out` con su interruptor y `PUT /me/surveys` (contrato de la API +1) | contrato de la API | `SurveySendTest`, `SurveyPageTest` `(futuro)`; los censos de correos; `curl -D` sin `visitor_id`; el OJO en Mailpit |
| T4 | **el cuadro**: `SurveysReport`, la pestaña, el CSV, la 360; fixture del ojo | | `SurveysReportTest` `(futuro)` (igualdad con las tablas), presupuesto, sonda del panel, el OJO |

## 5. Impacto en invariantes

`RGPD-01` (purga y export cubren `survey_responses` y `surveys_opt_out`; prune) · `RGPD-04` (`no-store` en la
página del correo y en la puerta) · `RGPD-07` (el libro solo lleva `survey`, `channel` y `user_id` de contrato;
ninguna respuesta) · `SEC-04` (re-autorizar al guardar en la puerta) · `SEC-05` (la puerta audita) · `PAY-14` (la
notificación en cola) · `SUITE-01`. Dinero, aforo: ninguno.

## 6. Plan de verificación empírica

- Tests por tanda (§4.6) y un arnés `scripts/mutar-encuestas.sh` `(futuro)`: la interna se ofrece antes de
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
- **Cinco preguntas, la recomendada primero** (`[PENDIENTE: owner]`; el diseño asume la (a) de cada una):
  1. **Las respuestas**: (a) atadas al cliente y a su visita, visibles en su ficha y anonimizadas con su cuenta ·
     (b) anónimas: solo agregados, sin ficha.
  2. **Cuántas veces**: (a) una respuesta por cliente y encuesta, aunque vuelva · (b) una por visita.
  3. **Tipos de pregunta**: (a) elección única, escala de 1 a 5 y texto libre opcional · (b) sin texto libre.
  4. **El correo externo**: (a) a todo el que visitó, como correo de servicio, con «no quiero más encuestas» de un
     toque · (b) solo a quien dio el opt-in de comunicaciones comerciales.
  5. **Encuestas vivas a la vez**: (a) una interna y una externa como máximo · (b) varias por clase.
- `[PENDIENTE: asesoría]` (5): el correo de encuesta como comunicación de servicio.
- Al aprobarse: estado ✅, `#740`, y la casilla T7 del tracker.

## Anexo · fila del enrutador

| Encuestas · internas en la puerta · externas por correo · sus resultados en el cuadro | `docs/specs/encuestas.md` §0 |
