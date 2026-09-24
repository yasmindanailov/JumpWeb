# [SPEC] La analítica de la fiesta — la lista de invitados, el justificante y la invitación, sin cookies para el invitado

> Estado: ✅ **aprobada por el owner el 24-09** (`#739`: las tres recomendaciones de §7) → **a implementar, T1 en
> curso** (carril del SPA) · Última actualización: 2026-09-24 · Decisión asociada: `#739`. Es la **T6** de
> `analitica.md` (§4.8): vive aparte porque cambia el RÉGIMEN de tres páginas y porque la spec madre ya no cabe en su §0.

## §0 · Antes de tocar

- **La regla que ordena todo: el invitado NO es un visitante.** Ni cookie, ni banner, ni script en las tres
  páginas enfocadas (`focused-layout`: el post-form, el justificante y la invitación). Sus hechos son **hechos de
  la RESERVA** —régimen del contrato, como `order_*`—: `order_id` y sin `visitor_id` ni `user_id`. La conversión
  se mide **por reserva y por invitación, nunca por persona**; «volvió como cliente» se cruza por correo (T4b).
- **Empieza por** §1 (lo medido) → §4.1 (el régimen) → §4.2 (los hechos) → §4.3 (el cuadro) → §4.6 (tandas).
- **Trampas (medidas el 24-09)**: (1) `ResolveVisitor::MINT` corre en TODO el grupo `web` y acuña `visitor_id`
  también en las enfocadas (`/autorizacion/{id}` firmada → `Set-Cookie: visitor_id`, sin un solo `<script>`): hoy el
  invitado recibe una cookie que no mide nada. (2) `guest_form_opened`, `guest_form_submitted` e
  `invitation_replied` están en el `Contract` desde la T1 y **nadie los emite**. (3) `PostFormAddons`,
  `GuestCountAdjuster`, `WaiverSigner` y `MixedPartySurcharge` son del `CRITICAL_RE`: ningún hecho se emite
  dentro; el `Recorder` se llama desde el CONTROLADOR tras el éxito y nunca tumba una firma ni una compra.
  (4) El dinero del post-form se paga EN EL PARQUE (`#244`): «vendido» sale del libro (`order_adjustments`) y
  «cobrado» de `payments` por `provider` (`cash`|`datafono`); `MoneyReport` no ve lo vendido sin cobrar.
  (5) La sesión y el XSRF (técnicas) se quedan; solo se retira la de medición. (6) En la local la invitación solo
  está encendida en los packs 105/106 (plataforma; no deshacer sin el owner).
- **Estado**: ✅ `#739` (día de la FIESTA · pestaña «Fiestas» · exportar con opt-in). **T1 ✅**, arnés 9/9. **T2 ✅
  (24-09, ✅ del owner en vivo: «validado»)**. Sigue **T3** (§4.4, §4.6).
- **Invariantes**: `RGPD-01`, `RGPD-02`, `RGPD-05`, `RGPD-07`, `SEC-01`, `SUITE-01`. Dinero y aforo: **ninguno
  cambia** (solo se LEE el libro). Ningún fichero del `CRITICAL_RE` se toca.

## 1. Contexto y problema — MEDIDO (2026-09-24)

- **Las tres páginas del invitado** —`reservation.guests` (el post-form del anfitrión), `reservation.authorization`
  (el justificante) e `invitation.*` (`show`, `reply`, `calendar`, `receipt`)— montan `focused-layout`: sin banner,
  sin `app.js`, sin tracker, `noindex`, `<body class="gf">`. Medido con `curl -D` sobre `/autorizacion/925?signature=…`:
  **200, cero `<script src>`, cero `data-cookie-*`, y `Set-Cookie: visitor_id`**. La cookie exenta se acuña igual
  porque `ResolveVisitor::MINT` va en el grupo `web` entero y solo excluye `admin/*`. Sin JS no hay `page_viewed`:
  ni sesión ni hecho. **Hoy el invitado recibe una cookie de 13 meses que no mide nada.**
- **El contrato declara tres hechos de la fiesta y ninguno se emite**: `grep -rn "fact('" app/` lista 14 hechos de
  servidor (`order_*`, `user_*`, `contact_received`, `email_*`, `visit_checked_in`); `guest_form_opened`,
  `guest_form_submitted` e `invitation_replied` solo existen en `Contract`.
- **La verdad del contrato YA está en las tablas de negocio**: `order_items.guest_form_completed_at` (0 en la
  local), `invitation_replies` (15: `attending`, `child_key`, `companion`, `adopted_at`, `dismissed_at`; `data` =
  `notes`/`allergy`/`special_menu`), `guardian_authorizations` (8; `guardian_email` en 5/8; `invitation_reply_id`
  cuando la firma nace de la invitación), `party_invitations` (6; `reminded_at`, `reminded_count`; **sin contador de
  vistas**). Lo que NO existe en ninguna tabla es lo de arriba del embudo: cuántos abren el formulario y no lo
  completan, cuántos abren la invitación, cuántos bajan el `.ics`, cuántos abren el justificante y no firman, y
  cuánto tardan.
- **El dinero de después de reservar** vive en el libro: `order_adjustments` con `type = edit` y `reason` ∈
  `postform_addon` (2 filas, 4.389 c), `guest_count_decrease` (1, −1.500 c) y `null` (8 ediciones del PANEL, no del
  post-form); y el suplemento del cumple mixto con `MixedPartySurcharge::REASON_GUEST_FORM`. Se cobra en el
  parque: `payments.provider` ∈ `cash` (2) | `datafono` (7), contra 72 de `redsys`. `MoneyReport` lee solo
  `payments`/`payment_refunds`: **lo vendido después de reservar y aún no cobrado no sale en ningún cuadro**.
- **El cruce con el cliente ya existe**: `SegmentsReport::GUEST_NO_PURCHASE` (T4b) cruza `guardian_email` con
  cuentas y pedidos; los invitados no se exportan (sin opt-in, «no hay a quién exportar»).
- **Terceros en esas páginas**: Turnstile (exento, `COOKIES.md` §1) en la respuesta y en la firma; la invitación
  ENLAZA el mapa, no lo embebe. `page_viewed` no existe ahí: ni el driver ni los píxeles cargan sin `<body>` con datos.
- **Volumen**: 6 invitaciones, 15 respuestas, 8 justificantes en la local; en producción, por medir tras la v2.0.0.

## 2. Objetivo

Medir el circuito de la fiesta de punta a punta dentro de «Analítica»: **cuánto dinero genera después de reservar**,
**dónde se cae cada paso** (formulario, invitación, justificante) y **qué invitados vuelven como clientes**; y hacerlo
**sin ponerle al invitado ni una cookie ni un script**.

Criterios de éxito, medibles:
1. Un `curl -D` a cualquiera de las tres páginas enfocadas devuelve **cero `Set-Cookie: visitor_id`** y cero
   `<script>` de terceros no exentos; la sonda lo repite en navegador con el registro de red vacío.
2. Los hechos del contrato del cuadro **coinciden con las tablas de negocio** al céntimo y a la unidad (un test
   siembra y compara los dos caminos).
3. Presupuesto de consultas del informe ≤ 20 (como `FunnelReport`), caché 5 min, `EXPLAIN` sin recorrido en
   `analytics_events` por `(name, order_id)`.
4. El CSV del informe sale por el mismo botón y con la misma línea de comparación que los otros tres.

**Fuera de alcance**: medir al invitado en el driver (PostHog) o en los píxeles; atar al invitado por cookie a
una compra posterior (se hace por correo); un correo al invitado (no existe: la invitación se comparte por
enlace); la landing y la app; cualquier dato personal en el libro (`RGPD-02`); la T2e del roll-up diario.

## 3. Opciones consideradas

- **A · El invitado sin cookie, hechos de la reserva desde el servidor — ELEGIDA.** Las rutas enfocadas salen
  del acuñado (`ResolveVisitor` no lee ni acuña ahí); cada controlador emite el hecho tras el éxito con `order_id`;
  la unidad del embudo es la reserva. Se pierde la vista ÚNICA (una recarga cuenta dos aperturas; se cuentan
  «aperturas» y «reservas con al menos una») y el enlace por cookie del invitado que luego compra (lo da el correo).
- **B · La cookie exenta también al invitado (lo de hoy) más los hechos.** Daría vistas únicas y el enlace por
  cookie. Descartada: el invitado no vino a la web, recibe una cookie sin una sola línea que se lo diga (la
  enfocada no tiene pie legal), la exención de la AEPD es para la medición de audiencia DEL EDITOR y una página
  firmada de un tercero es su borde, y el owner lo dijo en una frase («no les quiero spamear con cookies»). Además
  mezclaría invitados con visitantes e inflaría el embudo de la web.
- **C · Solo tablas de negocio, sin hechos nuevos.** Descartada: no hay arriba del embudo (aperturas, `.ics`,
  abandono) ni tiempos; se vería cuánto se firmó, nunca dónde se cayó quien no firmó.

## 4. Diseño elegido

### 4.1 El régimen del invitado

- **Rutas enfocadas** = las DOCE de los tres controladores: `reservation.guests` (GET y POST y sus tres POST de
  la invitación), `reservation.authorization` (GET y POST), `invitation.show`, `invitation.reply`,
  `invitation.calendar`, `invitation.receipt` y `invitation.receipt.save`. **T1 (24-09): van en un grupo
  `Route::withoutMiddleware([ResolveVisitor:mint])`** en `routes/web.php` —se eligió la exclusión de ruta y no el
  middleware `visitor.silent` que esta spec proponía: una pieza menos y el mismo efecto—: `ResolveVisitor` no corre
  ahí, así que **ni lee ni acuña**, y `Recorder::factOfOrder()` no mira el contexto de atribución a propósito: el
  hecho nace sin `visitor_id` aunque el navegador traiga una cookie de otra visita (uniforme: todo invitado igual).
  La sesión y el XSRF (técnicas) siguen: los formularios las necesitan.
- **Guarda**: `FocusedPagesAreCookieFreeTest` en `tests/Feature/Analytics/` recorre las cinco páginas con GET
  (post-form, justificante, invitación, calendario, recibo) y asevera cero `visitor_id` en `Set-Cookie`, cero
  `<script src>` no exento y cero `data-cookie-*`/`data-analytics-*`; censa por estructura que las DOCE rutas de los
  tres controladores llevan la exclusión (una ruta nueva no puede olvidarse); prueba que una cookie de otra visita
  no llega al hecho; y su CONTROL: la portada SIGUE acuñando.
- **Sin dato personal**: ningún hecho lleva nombres, correos, `child_key` ni `minor_key`; las props son enteros y
  enumerados. El `device` y el `locale` del invitado salen del `User-Agent` y del `Accept-Language` en la petición
  (agregado, como `SessionResolver` hace con la primera vista) y un UA de robot no emite (`is_bot` de la T1).

### 4.2 Los hechos (todos de SERVIDOR, `refs: order_id`; el contrato de la API no cambia)

| Hecho | Dónde se emite | Props |
|---|---|---|
| *todos* | el trait `RecordsPartyFacts` | `reservation` (el id de la línea del pack; T2: el embudo cuenta por reserva y un pedido puede tener dos) |
| `guest_form_opened` (ya declarado) | `GuestFormController::show` | `days_before` (días hasta la fiesta), `device`, `locale` |
| `guest_form_submitted` (ya declarado) | `GuestFormController::store`, tras el éxito | `days_before`, `guests_delta`, `extras_cents` (Σ del libro en esa petición), `replies_adopted` |
| `invitation_viewed` `(futuro)` | `InvitationPageController::show` | `days_before`, `device`, `locale` |
| `invitation_replied` (ya declarado) | `InvitationPageController::reply`, tras el éxito | `attending` (`yes`\|`no`), `companion` (bool), `days_before` |
| `invitation_calendar_downloaded` `(futuro)` | `InvitationPageController::calendar` | `days_before` |
| `authorization_opened` `(futuro)` | `GuardianAuthorizationController::show` | `via` (`invitation`\|`link`), `days_before`, `device` |
| `authorization_signed` `(futuro)` | `GuardianAuthorizationController::store`, tras el éxito | `via`, `days_before`, `hours_since_open` (si hubo apertura en la misma sesión técnica) |

`Contract` gana las cuatro filas nuevas; `Recorder::fact()` ya difiere con `DB::afterCommit` y traga con `Log`. Los
hechos salen del CONTROLADOR, nunca de `WaiverSigner`, `PostFormAddons` ni `GuestCountAdjuster` (§0). El anfitrión
del post-form es cliente, pero su hecho tampoco lleva `user_id`: es un hecho del pedido y la 360 llega por `order_id`.

### 4.3 El informe y la pestaña «Fiestas»

- `app/Filament/Analytics/PartiesReport.php` `(futuro)`, capa de entrega como `FunnelReport`, `Window`, caché 5 min,
  `for()` + `tablesFor()` para el CSV. **La unidad de tiempo es el DÍA DE LA FIESTA** (`slots.date` de la reserva),
  no el del cobro: el circuito se cierra ese día y así el embudo de un periodo compara fiestas comparables (§7·1).
- **Dinero, del libro y de los cobros**: vendido después de reservar (Σ `order_adjustments` `type = edit` con `reason`
  ∈ `postform_addon` · `guest_count_increase` · `guest_count_decrease` · `guest_form`, con signo), por complemento
  (`order_item_id` → `ticket_type`), invitados añadidos y quitados (unidades y céntimos), **cobrado en el parque**
  (`payments` `paid` con `provider` ∈ `cash`|`datafono` en el periodo), reserva media con extras y % de reservas
  con extras. Las ediciones con `reason = null` son del panel y quedan fuera, en su propia línea.
- **El embudo, por reserva** (pedidos cobrados con pack que `acceptsGuestForm()` y fiesta en el periodo): con
  formulario → abrieron (≥ 1 `guest_form_opened`) → completaron (`guest_form_completed_at`) → con extras (libro) →
  con invitación (`party_invitations`) → vistas (aperturas y reservas con ≥ 1) → respuestas sí | no →
  adoptadas (`adopted_at`) → `.ics` → justificante ofrecido (`guardian_authorization` ∈ `optional`|`required`) →
  abiertos → firmados (`guardian_authorizations`) → firmados desde la invitación (`invitation_reply_id`).
- **Tiempos**: mediana de `days_before` al completar el formulario y al firmar; % de formularios completados
  dentro del plazo de corte (`packs.guest_count_cutoff_hours`); mediana de `hours_since_open`.
- **Los invitados** (agregado): dispositivo e idioma de las aperturas de invitación y justificante.
- **Widgets** en una cuarta pestaña `parties` («Fiestas», `?pestana=parties`) de `AnalyticsPage`, en el orden de
  lectura de la T2f: `PartiesOverviewWidget` `(futuro)` (tarjetas), `PartiesFunnelChart` `(futuro)`,
  `PartiesMoneyChart` `(futuro)` (por complemento), `PartiesTimingChart` `(futuro)` y, plegada al pie,
  `PartiesBreakdownWidget` `(futuro)` (por día o semana de fiesta). `CsvExport::REPORT_PARTIES` `(futuro)` con la
  línea «Comparado con». `AnalyticsPageTest` censa la lista EXACTA (trampa de la T4b).

### 4.4 Los segmentos y la 360 del anfitrión (T3)

- `SegmentsReport::GUEST_BECAME_CUSTOMER` `(futuro)`: un `guardian_email` (o el correo de una respuesta adoptada,
  si algún día lo lleva) que DESPUÉS de la fiesta tiene cuenta y pedido cobrado. Son clientes con cuenta: se
  exportan si dieron opt-in, como los demás (§7·3). Sigue vivo `GUEST_NO_PURCHASE`.
- `CustomerInsights` gana un bloque «fiestas» `(futuro)`: fiestas, formularios completados, invitados, respuestas,
  extras vendidos; solo desde los pedidos del cliente (régimen del contrato).

### 4.5 Transparencia, retención y rendimiento

- La política de cookies no cambia (`visitor_id` sigue siendo exenta en la web); en `/privacidad` se AÑADE que la
  invitación y el justificante dejan hechos de la reserva sin identidad del invitado, por migración quirúrgica como
  las de la T3a·1. `[PENDIENTE: asesoría]` (4): que un hecho de la reserva sin `visitor_id` cabe donde caben `order_*`.
- `analytics_events` de la fiesta se podan con las demás (`model:prune` ya en el scheduler) y `anonymize()` no tiene
  nada que desatar: no hay `user_id`. El export del cliente no cambia.
- Índice `analytics_events (name, order_id)` `(futuro)` si `EXPLAIN` lo pide; el resto usa `(name, received_at)`.

### 4.6 El orden de las tandas

| | Tanda | Entrega | Verificación (§6) |
|---|---|---|---|
| T1 | **✅ (24-09) el régimen y los hechos**: las DOCE rutas de los tres controladores en un grupo `withoutMiddleware(ResolveVisitor:mint)` (§4.1); `Recorder::factOfOrder()` (sin visitante, sesión ni titular, y solo nombres de servidor); `Contract` +4 y props en los 3 que ya existían; `PartyFacts::daysBefore()` en días del parque; el trait `Http\Concerns\RecordsPartyFacts` (robots fuera, `device`/`locale` de la petición) y los siete hechos desde `GuestFormController`, `InvitationPageController` y `GuardianAuthorizationController` tras el éxito (`authorization_signed` solo con `created`; `hours_since_open` desde la sesión técnica). **Lo que enseñó**: el reenvío del mismo padre es IDEMPOTENTE («signed», no «already») y por eso el hecho mira `created`; eran doce rutas y no ocho; el arnés cazó que ningún caso llamaba a `factOfOrder()` con un nombre que no fuera de servidor (caso añadido); `order_id` nunca es nulo (Larastan) | ningún fichero del `CRITICAL_RE`; el contrato de la API no cambia (los nombres de servidor no van en su `enum`) | `FocusedPagesAreCookieFreeTest` (4) + `PartyFactsTest` (13) · `scripts/mutar-analitica-fiesta.sh` **9/9 + 1 control** · en vivo con `curl -A Chrome`: `/autorizacion/925` firmada → 200, cookies solo XSRF y sesión, cero `<script>`, hecho `authorization_opened` en el pedido 755 sin nadie y `days_before` 14; `/invitacion/{token}` como iPhone → `invitation_viewed` (`mobile`, 24 días) y como vista previa de WhatsApp → nada. La sonda de navegador llega con la T2 (el ojo del owner) |
| T2 | **✅ (24-09; el owner la vio en vivo en escritorio y móvil: «muy bien, validado») el informe y la pestaña «Fiestas»**: `app/Filament/Analytics/PartiesReport.php` (capa de entrega, caché 5 min, **14 consultas y 28 ms** con 32 fiestas en MySQL; la población por `slots.date`, los hechos por `order_id` + `props.reservation`, el libro por `COALESCE(parent_item_id, id)`), los cinco widgets (`PartiesOverviewWidget` 9 tarjetas · `PartiesFunnelChart` · `PartiesMoneyChart` · `PartiesTimingChart` · `PartiesBreakdownWidget` con siete tablas), la cuarta pestaña `?pestana=parties` (icono tarta), `CsvExport::REPORT_PARTIES` con la línea «Comparado con», rótulos en es y zh_CN. **Retoque de la T1**: los siete hechos llevan `reservation` (el id de la línea del pack) porque un pedido puede tener dos fiestas y el embudo cuenta por reserva. **Lo que enseñó**: los invitados añadidos NO son «extras» (no entran en «reservas con extras» ni en su media); una edición sin `reason` es del panel y va aparte; un ayudante privado `seed()` en un test es FATAL otra vez (→ `seedJune()`); un parámetro nombrado que se llama como el primero revienta la llamada; la sonda censaba dos «Por día» y ahora hay tres; el rótulo largo de «invitados añadidos y quitados» se recortaba en el eje del gráfico (rótulo corto propio); el `EXPLAIN` no pide índice nuevo (los de `order_id`, `order_item_id` y `parent_item_id` ya existen). Fixture del ojo: `probe-ojo-fiesta.php` en la carpeta de almacenamiento, fuera de git (32 fiestas, 323 hechos; `OJO=desmontar`) | | `PartiesReportTest` (10: igualdad con las tablas de negocio, embudo, tiempos en días del parque, serie, periodo anterior, vacío, presupuesto ≤ 20 sin crecer, caché) · `AnalyticsPageTest`, `AnalyticsExportTest` y `PartyFactsTest` ampliados · `scripts/sonda-analitica-panel.mjs` **31/31** (cuatro pestañas, 9 tarjetas, 3 gráficos, las tablas, el CSV, móvil sin scroll horizontal) · ✅ el OJO del owner en `/admin/analitica?pestana=parties` (24-09) |
| T3 | **los segmentos y la 360**: `GUEST_BECAME_CUSTOMER`, el bloque «fiestas» de la 360 | | `SegmentsReportTest` +1, `UserInsightsInfolistTest` +1, el OJO |

## 5. Impacto en invariantes

`RGPD-01` (purga y export: nada nuevo que exportar; la poda cubre los hechos) · `RGPD-02` (cero PII en props: lo
asevera `PartyFactsTest`) · `RGPD-05` (ningún tercero nuevo; menos cookies que hoy) · `RGPD-07` (el libro sigue en
dos regímenes; estos hechos son del contrato) · `SEC-01` (los enlaces firmados y el token no cambian) · `SUITE-01`.
`PAY-*` y `AFORO-*`: **ninguno** (lectura del libro; `order_adjustments` no se escribe desde aquí).

## 6. Plan de verificación empírica

- `curl -s -D - -o /dev/null "<enlace firmado>"` sobre las tres páginas: sin `visitor_id`; y con la sonda
  `scripts/sonda-fiesta.mjs` `(futuro)` en escritorio y móvil: red sin terceros, cero cookies nuevas, y el hecho en
  `analytics_events` con `order_id` y `visitor_id IS NULL`.
- `php artisan test --filter='FocusedPagesAreCookieFree|PartyFacts|PartiesReport|AnalyticsPage|Segments'`; el arnés
  `scripts/mutar-analitica-fiesta.sh` `(futuro)` con al menos: quitar el grupo silencioso (la guarda muere), emitir
  dentro de una transacción que falla (no queda hecho), meter un correo en una prop (el test de PII muere), y
  un CONTROL.
- Igualdad: un fixture con N fiestas, extras y firmas; el informe da los mismos totales que `SUM()` directo sobre
  las tablas de negocio, al céntimo.
- `DB::enableQueryLog()` por informe (≤ 20, con el memo de `Setting` caliente) y `EXPLAIN` sobre el fixture del ojo
  ampliado con fiestas.
- El OJO del owner en `/admin/analitica?pestana=parties`, escritorio y móvil, con el fixture montado.

## 7. Revisión y decisión

- **24-09, owner**: «quiero analíticas de dinero, conversión y segmentos en la lista de invitados, justificante
  digital, invitación digital… todo. Pero para la invitación y el justificante vendrían invitados, no usuarios del
  parque: hay que llevar cuidado porque no les quiero spamear con cookies». Es la regla del §0.
- **Tres preguntas para el owner antes del ✅** (la recomendada, primero):
  1. **La unidad de tiempo del embudo**: (a) el día de la FIESTA, recomendado (compara fiestas comparables; el
     dinero de la pestaña «Dinero» sigue por día del cobro) · (b) el día del cobro, como el resto del cuadro.
  2. **Dónde vive**: (a) pestaña propia «Fiestas», recomendado (cuatro pestañas; su CSV) · (b) dentro de «Conversión».
  3. **Los invitados que vuelven**: (a) contarlos y exportarlos con opt-in como cualquier cliente, recomendado ·
     (b) solo contarlos.
- **24-09, owner**: «acepto tu recomendación profesional sobre las tres preguntas; todo lo demás está validado y
  OK»: **1(a) el día de la fiesta · 2(a) pestaña «Fiestas» · 3(a) contar y exportar con opt-in** → `#739`, estado ✅.
- `[PENDIENTE: asesoría]` (4): el hecho de la reserva sin identidad del invitado, junto a los tres de `analitica.md` §7.
- Aprobada: `#739`; la casilla T6 del tracker dice «spec ✅ · T1 ⬜» hasta que la T1 entre en `main`.

## Anexo · fila del enrutador

| La fiesta en la analítica · invitados · justificante · invitación · sin cookies al invitado | `docs/specs/analitica-fiesta.md` §0 |
