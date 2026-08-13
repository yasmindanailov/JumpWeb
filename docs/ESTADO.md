# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — pasos 0, 1a, 1b, 2 y 3a CERRADOS;
toca el paso 3b (sesión por API).**
- Suite **2302 en verde** (8664 aserciones, `--parallel` ~62 s) · Pint limpio ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- **Aviso para el próximo cierre** (`DECISIONES #25`): el árbol npm pasó de 0 a **5 avisos (2
  críticas, 3 altas)** en unas horas SIN que `package.json` ni el lock cambiaran — avisos
  publicados en el intervalo, todos en herramientas de build. Se sanearon con `npm audit fix` sin
  `--force` (Vite 8.2.1). La lección: `composer audit`/`npm audit` son verificación de CIERRE, no
  un trámite de instalación.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-13, paso 2, 16
  workers): `purchase:verify-oversell` → 1 compra + 15 `sold_out` con asientos == aforo;
  `redsys:verify-concurrency` → 1 `authorized` + 15 `idempotent_paid`, 1 pago y 1 ticket. Se
  corrieron porque el paso 2 mudó el código de `PAY-04`; los pasos 0, 1a y 1b no los necesitaron
  (cimientos y lectura).
- ⚠️ **El `CRITICAL_RE` del `pre-push` cubre ahora también `PaymentInitiator` y
  `ReservationAdmissionPolicy`**: tocarlos exige `VERIFY_CONC=1` tras correr los dos comandos
  (`INVARIANTES §6`). Antes ese código vivía en un componente Livewire y en un controlador web, o
  sea, fuera del alcance del gate.
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

## ▶ Qué hay hecho de la API (pasos 0, 1a, 1b, 2 y 3a — `DECISIONES #24`, `#26`–`#29`)
Cimientos + la lectura de la cuenta + el catálogo + el dominio preparado para vender por API. Lo
que existe y funciona (verificado con `curl`, con la suite y **contra MySQL real** ejerciendo el
pipeline HTTP completo):
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
- **Paso 2 (sin endpoints nuevos)**: el dominio ya sabe *quién puede reservar* y *cómo se abre un
  cobro*, que es lo que faltaba para que el `POST /orders` del paso 4 no reabra el hallazgo E.
  · `Booking\Contracts\ReservationAdmission` — pausa (#218), tope de pendientes, frecuencia y la
    extensión ATÓMICA del hold (`PAY-04`). `mayReserve()` consulta sin consumir ficha;
    `admitReservation()` consume; `admitPaymentRetry()` no aplica el tope de pendientes (un
    reintento no crea aforo) pero sí el limitador.
  · `Payments\Services\PaymentInitiator` — `open()`/`reopen()`: crea el `Payment`, firma el
    formulario, marca `SUPERSEDED` los intentos previos y deja el rastro de fallo en `audit_logs`.
    Lanza `PaymentInitiationException`; qué le pasa al pedido lo decide el llamante.
  · Los consumen el sidebar y «Mis pedidos», y lo comprueba `ModuleContractsTest` con un doble que
    deniega: si alguno siguiera decidiendo por su cuenta, crearía el pedido igual.
- **Lo que destapó juntar las dos copias**: aplicaban políticas DISTINTAS sin decisión previa ni
  test que las fijara (el reintento de «Mis pedidos» no pasaba por ningún límite por titular), y el
  limitador contaba pantallas en vez de reservas —la 2.ª compra del mismo minuto se bloqueaba con
  el tope en 3—. Las tres asimetrías las resolvió el owner (`DECISIONES #28b–d`).

## ▶ Próximo paso
**Fase 3 · paso 3b — SESIÓN POR API** (spec §4.2 y §9): extraer `Livewire\Auth\Login` a un
servicio de Identity y publicar `POST auth/login` y `POST auth/logout` sobre la sesión stateful de
Sanctum (cookie + CSRF), que es lo que consumirá la SPA de Fase 4.

**Lo medido el 2026-08-13, para no redescubrirlo:**
- **Los DOS limitadores de `SEC-06` son `private const` de `Login`**: `MAX_ATTEMPTS = 5` (clave
  `email|ip`) y `MAX_ATTEMPTS_PER_IP = 30` (solo-IP, anti-spraying distribuido). **Los dos** tienen
  que viajar al servicio: una API que reimplemente solo el primero reabre el credential-stuffing
  distribuido que el segundo cubre —1 intento por (cuenta, IP) nunca acumula 5 en ninguna clave—.
  Ojo al detalle: la clave compuesta se limpia al acertar la contraseña, la de IP **no** (es
  compartida entre usuarios tras el mismo NAT).
- **Qué NO debe viajar al servicio**: el anti-cesta-cruzada (`purchase.user_id` en sesión, hallazgo
  D) y el `Session::regenerate()` son efectos de la sesión WEB. Criterio del spec §4.6.3, ya
  estrenado en el paso 2: el servicio devuelve un veredicto y los efectos los pone el llamante.
- `Login` hace además `last_login_at` y dos `Log::info` (`auth.login`, `auth.login_failed`) que sí
  son del servicio, y lanza `ValidationException` con la clave `_global` para el lockout —eso es
  presentación: en la API será un `429` con su código.
- El registro (`Register`, 338 líneas: honeypot, 3 limitadores, Turnstile, anti-enumeración con
  decisión de la clienta, transacción con consents+rol, rama pay-first) y la recuperación de
  contraseña quedan para el **paso 3c**.
- ⚠️ **La EMISIÓN de tokens Bearer NO entra aquí**: viaja a Fase 6 con la app que los consuma
  (`DECISIONES #29`). La revocación ya está hecha y probada (paso 3a), así que cuando llegue el
  emisor no hay que acordarse de nada.
- `DEUDA.md` tiene una entrada emparentada: el bypass de mantenimiento para staff no funciona con
  Bearer (`EnsureSiteAvailable` resuelve el bypass con el guard de sesión, porque corre antes del
  `auth:` de ruta). Con la emisión aplazada, sigue sin morder.

**Antes de escribir código, lee `docs/specs/api-v1.md` §10 → §10.quinquies** («lo que el código
enseñó»): veintisiete puntos medidos en los pasos 0, 1a, 1b, 2 y 3a. Los que más ahorran tiempo en
el 3b: la validación de contrato **hay que pedirla** con `assertValidResponse()` (§10.ter 16); todo
«esto puede ser nulo» en OpenAPI 3.0 hay que ejercerlo con un test (15); y antes de unificar dos
copias, ponerlas lado a lado y listar sus diferencias, porque cada una es una decisión de producto
(19).

Después: 3c (registro y contraseña por API) · paso 4 (el dinero: quote, disponibilidad con cesta,
`POST orders`, reintento y `payment-status` con estados reales) · paso 5 (post-form). El corte
completo está en el spec §9. **El paso 4 ya tiene el terreno preparado**: consume
`ReservationAdmission` + `OrderCreator` + `PaymentInitiator`, sin reimplementar ninguna regla.

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
17 Filament Resources · **2302** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
