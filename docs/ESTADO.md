# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — pasos 0, 1a, 1b, 2, 3a y 3b CERRADOS;
toca el paso 3c (registro y contraseña por API).**
- Suite **2320 en verde** (8808 aserciones, `--parallel` ~61 s) · Pint limpio ·
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

## ▶ Qué hay hecho de la API (pasos 0, 1a, 1b, 2, 3a y 3b — `DECISIONES #24`, `#26`–`#30`)
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

- **Paso 3a — REVOCACIÓN de credenciales**: `User::revokeAllAccess()` / `revokeOtherAccess()` es el
  punto ÚNICO que invalida sesiones **y** tokens de API. El hueco era de CINCO sitios, no de cuatro:
  el quinto era la limpieza de go-live, que dejaba tokens **huérfanos** (`personal_access_tokens` es
  morph y no tiene FK). `AccessRevocationTest` impide una sexta copia. `INVARIANTES RGPD-06`.
- **Paso 3b — SESIÓN por API**: `POST auth/login` (200 con el perfil) y `POST auth/logout` (204)
  sobre la sesión stateful de Sanctum. La regla vive en `Identity\Services\PasswordLogin`, que
  consume también el modal de la web: los DOS limitadores de `SEC-06` no tienen dos copias, y eso
  está **verificado por mutación** en las tres capas.
  ⚠️ **Para la SPA de Fase 4**: `Origin`/`Referer` de un dominio *stateful* hace falta en TODAS las
  peticiones, no solo en el login — sin él no hay sesión y un `GET /me` da 401 aunque la cookie sea
  válida (verificado con `curl`).

## ▶ Próximo paso
**Fase 3 · paso 3c — REGISTRO y CONTRASEÑA por API** (spec §4.2 y §9): `POST auth/register`,
`password/forgot`, `password/reset` y `email/resend`, extrayendo antes lo que sea dominio de
`Livewire\Auth\Register`, `ForgotPassword` y `ResetPassword`.

**Lo medido el 2026-08-13, para no redescubrirlo:**
- **`Register` es el más denso de los cuatro** (338 líneas) y lleva decisiones de PRODUCTO, no solo
  reglas: honeypot → pantalla de éxito falsa; **tres** limitadores (por IP 5/min, por email
  hasheado 3/hora y el propio Turnstile); y una **decisión explícita de la clienta** que rompe la
  anti-enumeración a propósito —si el correo ya existe se le dice, para priorizar conversión sobre
  ocultar qué correos hay—. Eso NO es un descuido que el refactor deba «arreglar»: si la API lo
  cambiara por su cuenta, las dos superficies dirían cosas distintas.
- La rama **pay-first** de `Register` (`embedded`) inicia sesión sin verificar el correo y avisa al
  sidebar; es estado de sesión web y se queda fuera del servicio, como el anti-cesta-cruzada del 3b.
- El alta crea cuenta + rol + consents en UNA transacción, con los emails FUERA de ella (un fallo de
  SMTP no revierte el alta). Ese reparto hay que conservarlo tal cual.
- `ResetPassword` ya llama a `User::revokeAllAccess()` (paso 3a) y aplica no-enumeración: un correo
  inexistente y un token inválido dan el MISMO mensaje. El limitador propio (6/min por IP) está ahí
  porque la acción Livewire NO pasa por el `throttle` de la ruta GET; en la API sí habrá ruta POST,
  así que hay que decidir si se conserva el limitador del servicio, el de la ruta, o ambos.
- El anti-bot **Turnstile es data-driven y no-op sin claves** (`DECISIONES #23`): la SPA es un
  navegador y lo exigirá igual que la web. No hay nada que relajar.

**Antes de escribir código, lee `docs/specs/api-v1.md` §10 → §10.sexies** («lo que el código
enseñó»): treinta y dos puntos medidos en los pasos 0, 1a, 1b, 2, 3a y 3b. Los que más ahorran
tiempo en el 3c: la validación de contrato **hay que pedirla** con `assertValidResponse()`
(§10.ter 16); un esquema de PETICIÓN no se puede exigir como uno de respuesta (32); antes de
unificar dos copias, ponerlas lado a lado y listar sus diferencias (19); y cuando una guarda de
arquitectura protesta por código nuevo, la primera hipótesis es que el sitio correcto ya está
decidido (31).

Después: paso 4 (el dinero: quote, disponibilidad con cesta, `POST orders`, reintento y
`payment-status` con estados reales) · paso 5 (post-form). El corte completo está en el spec §9.
**El paso 4 ya tiene el terreno preparado**: consume `ReservationAdmission` + `OrderCreator` +
`PaymentInitiator`, sin reimplementar ninguna regla.

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
17 Filament Resources · **2320** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
