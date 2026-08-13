# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — paso 1 COMPLETO (0, 1a y 1b cerrados);
toca el paso 2 (extraer política de admisión e ida de pago).**
- Suite **2266 en verde** (8541 aserciones, `--parallel` ~63 s) · Pint limpio ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- **Aviso para el próximo cierre** (`DECISIONES #25`): el árbol npm pasó de 0 a **5 avisos (2
  críticas, 3 altas)** en unas horas SIN que `package.json` ni el lock cambiaran — avisos
  publicados en el intervalo, todos en herramientas de build. Se sanearon con `npm audit fix` sin
  `--force` (Vite 8.2.1). La lección: `composer audit`/`npm audit` son verificación de CIERRE, no
  un trámite de instalación.
- **Los verificadores de concurrencia NO se han corrido en esta sesión y no hacía falta**: los pasos
  0, 1a y 1b no tocan `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator` ni ningún controlador de
  checkout —son cimientos y lectura—, y el gate del `pre-push` lo confirma. Su último verde sobre
  MySQL real es del 2026-08-13 (`DECISIONES #22`). **El paso 2 SÍ los exige**: toca la política de
  admisión (`VERIFY_CONC=1` + los dos comandos de `INVARIANTES §6`).
- **Fase 2 (modularización) CERRADA** en 7 pasos, 2026-08-12: el dominio vive en
  `app/Domain/<Contexto>/` con 5 módulos (Platform · Content · Identity · Payments · Booking) y
  frontera EJECUTABLE. **No repitas esa lectura**: el detalle está en `00-REFACTOR.md`,
  `DECISIONES #13`–`#20` y `docs/specs/modulos-dominio.md`. Lo que sí necesitas al tocar código hoy:
  · **Herramienta**: `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES
    (llamadas a clases del mismo namespace, sin `use`). Fueron la única causa real de rotura.
  · **Criterio de frontera**: *recibir* una entidad de otro módulo es costura de BD; *consultar*
    sus datos o *repetir* sus reglas exige contrato.
  · **Baselines del arch-test**: `LEGACY`/`PENDING`/`DEFERRED` vacías; `SEAM` solo con costura
    documentada. Todas **solo encogen**: añadir una entrada es señal de que algo está mal hecho.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones deben correr **ANTES** de servir tráfico
  (la conversión del morphMap de Fase 2 es requisito, no comodidad: sin ella, leer un
  `payable`/`priceable`/`target` antiguo revienta) y hay que **drenar la cola + `queue:restart`**
  (los payloads serializados llevaban los FQCN viejos).
- Entorno local: web `8081` · MySQL `3308` · Mailpit `8028`; BD dev sembrada con SaltoPark
  (usuarios dev `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña `password`; ojo: la BD
  dev arrastra ADEMÁS el par `…@jumpingjump.test` del import, así que `User::first()` devuelve uno
  del origen — usa el email completo al probar a mano).
  ⚠️ Corregido el 2026-08-13 en el `.env` local (no versionado): tenía `APP_URL=…:8080` con
  `APP_PORT=8081` — enlaces absolutos de correo, URLs firmadas y la derivación de CORS y de los
  dominios stateful de Sanctum salían con el puerto equivocado. **Si clonas de cero, comprueba que
  `APP_URL` coincide con `APP_PORT`.**

## ▶ Qué hay hecho de la API (pasos 0, 1a y 1b — `DECISIONES #24`, `#26` y `#27`)
Cimientos + la lectura de la cuenta. Lo que existe y funciona (verificado con `curl`, con la suite y
**contra MySQL real** ejerciendo el pipeline HTTP completo):
- `routes/api.php` bajo `/api/v1`, con **fuente única del prefijo** (`ApiSurface::PREFIX`).
- **Grupo `api` declarado pieza a pieza** en `bootstrap/app.php` — el orden ES el diseño y está
  comentado allí: `SecurityHeaders` → Sanctum stateful → `ApiLocale` → `EnsureSiteAvailable`
  (503 en JSON) → `no-store` autenticado → `throttle:api` → `SubstituteBindings`.
- **Sobre de error único** `{error:{code,message,params?,fields?}}` con códigos estables
  (`ApiErrorCode`) desacoplados de las claves i18n, y mensajes en `lang/<idioma>/api.php` (es/en/fr).
- `GET /api/v1/me` + **OpenAPI escrito a mano** en `openapi/v1.yaml`, con validación de la
  respuesta REAL (Spectator) y guardas de contrato en las dos direcciones.
- Sanctum instalado con caducidad de token (30 días) y poda semanal, **sin emisor todavía**.
- Guardas nuevas, las tres **verificadas por mutación**: `ApiBoundariesTest` (nada de negocio en un
  controlador), `ApiContractTest` (el documento manda) y `CriticalPathGateTest` (el `CRITICAL_RE`
  del `pre-push`, ampliado a los controladores de checkout de API).
- **Paso 1a**: `GET me/reservations` (sobre el contrato `CustomerReservations`, sin reimplementar su
  filtrado) y `GET me/orders` (paginado, **todos los estados**, líneas y complementos anidados).
  Nace `ApiCollection`: la forma única de lista `data` + `meta`.
- **Paso 1b**: el CATÁLOGO, público y de solo lectura — `GET catalog/zones`, `catalog/products`
  (filtro `?type=entry|pack`) y `catalog/products/{id}` (mínimos/máximos, campos del evento de la
  etapa `booking` y complementos ofrecibles). No es código solo para la API: nace el contrato
  `Booking\Contracts\ProductCatalog` (+5 DTOs) con `CatalogReader` detrás, y **la web
  (`Tickets\Purchase`) lo consume desde el mismo commit**, así que hay UNA definición de «qué se
  vende». `search` y `zone_anchor` se quedaron en la web: son índice de búsqueda en cliente y ancla
  de scroll, y se derivan de lo que da el contrato.
- El contrato ya ha ganado su sueldo tres veces: destapó que apoyarse en `ResourceCollection`
  producía `data.data` con dos `meta`, que el campo `online_due_cents` mentía en su nombre (es el
  importe que se cobra online, no lo pendiente → `online_amount_cents`), y que un `$ref` con
  `nullable` no valida **en ninguna de las dos direcciones** (por eso la zona anidada va inline, con
  una guarda que impide que diverja del componente).
- Y el presupuesto de consultas ganó el suyo: medido por PENDIENTE (mismo coste con 1 elemento que
  con N) destapó un **N+1 real** en el propio read-model del catálogo —`RateResolver::priceCents()`
  consulta por llamada—, corregido resolviendo la tarifa una vez.

## ▶ Próximo paso
**Fase 3 · paso 2 — REFACTOR SIN ENDPOINTS: extraer la política de admisión y la ida del pago**
(spec §4.6.1–2 y §9). No añade superficie: mueve al dominio dos reglas que hoy viven fuera de él,
con la web y el panel de testigos. **Aquí vuelven `VERIFY_CONC=1` y los dos verificadores de
concurrencia** (`INVARIANTES §6`): el push los exige en cuanto se toque `OrderCreator`.

**Lo medido el 2026-08-13, para no redescubrirlo:**
- **Política de admisión** — vive en `Livewire\Tickets\Purchase`, no en Booking:
  `blockedByReservationPause()` (pausa de reservas del panel, #218) y `withinReservationLimits()`
  con `MAX_PENDING_PER_USER = 5` y `RESERVATIONS_PER_MINUTE = 3` (líneas ~56–65 y ~760–850). Cierran
  el hallazgo E del origen: sin ellas, un usuario autenticado agota el aforo del día iterando
  `confirmReservation` sin pagar. `OrderCreator` **no contiene ninguna**, así que un `POST /orders`
  «delgado sobre `OrderCreator`» las reabriría — es el motivo de que este paso vaya ANTES del 4.
- **Ida del pago** — duplicada hoy entre `Purchase::retryPayment()` y
  `App\Http\Controllers\Payments\RetryPaymentController` (136 líneas): comparten el UPDATE atómico
  de `expires_at` (check+extensión en una sola sentencia, hallazgo L2), `Payment::STATUS_SUPERSEDED`,
  `Redsys::nextGatewayOrder()` y el audit. La API sería la TERCERA copia. `PAY-04`/`PAY-11` exigen
  que siga siendo **CAS atómico**: separarlo en check+save resucita un hold vencido sin recontar
  aforo.
- El paso 1b dejó el patrón de extracción probado y repetible: contrato + DTOs en
  `Booking\Contracts`, implementación en `Booking\Services`, bind en `BookingServiceProvider`, y
  **el consumidor viejo migrado en el mismo commit** con un doble en `ModuleContractsTest` que
  demuestra que ya no hace el trabajo por su cuenta.

**Antes de escribir código, lee `docs/specs/api-v1.md` §10, §10.bis y §10.ter** («lo que el código
enseñó»): dieciocho puntos medidos en los pasos 0, 1a y 1b. Los que más ahorran tiempo en el paso 2:
la validación de contrato **hay que pedirla** con `assertValidResponse()` (§10.ter 16), el
presupuesto de consultas se mide por PENDIENTE y no por techo (17), y `DB::listen` no se
desregistra (17).

Después: paso 3 (auth + revocación de tokens) · paso 4 (el dinero: quote, disponibilidad con cesta,
`POST orders`, reintento y `payment-status` con estados reales) · paso 5 (post-form). El corte
completo está en el spec §9.

**Pendiente del owner** (❗): 2FA del panel (sin plan — `DEUDA.md`) · mecanismo del primer admin de
producción (`INSTALACION-CLIENTE.md` §5) · backlog de producto de Fase 6.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- Si tocas `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator` **o un controlador de API de
  pedidos/pagos/checkout/quote/disponibilidad**: el push exige `VERIFY_CONC=1` tras correr los
  comandos de INVARIANTES §6.

## Herencia
Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys
en sandbox y suite **2132** verde al importarla.
Recuento VIVO (lo verifica `docs-check` contra el código): 30 modelos · 72 migraciones ·
17 Filament Resources · **2266** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
