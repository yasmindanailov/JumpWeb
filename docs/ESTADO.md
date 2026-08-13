# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — pasos 0, 1a, 1b, 2, 3 (a+b+c) y **4a**
CERRADOS; toca el paso 4b (disponibilidad con la cesta).**
- Suite **2376 en verde** (9094 aserciones, `--parallel` ~63 s) · Pint limpio ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- **Auditorías de dependencias son verificación de CIERRE, no de instalación** (`DECISIONES #25`):
  el árbol npm pasó de 0 a 5 avisos en unas horas sin que el lock cambiara. Correr
  `composer audit` y `npm audit` en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-13, 16 workers, la
  última vez en el paso 4a). ⚠️ El `CRITICAL_RE` del `pre-push` cubre `PaymentInitiator`,
  `ReservationAdmissionPolicy` y **todo controlador de API `Order*`/`Payment*`/`Checkout*`/`Quote*`/
  `Availability*`**: tocarlos exige `VERIFY_CONC=1` tras correr los dos comandos (`INVARIANTES §6`).
  Todo el paso 4 los dispara, así que cuenta con ellos en cada commit.
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

## ▶ Qué hay hecho de la API (Fase 3, pasos 0 → 4a)
**El inventario NO se repite aquí**: la superficie exacta la declara `openapi/v1.yaml` —que es el
contrato y manda sobre el código— y el porqué de cada paso está en `DECISIONES #24`, `#26`–`#32` y
en `docs/specs/api-v1.md` §9. Lo que sigue es solo lo que **cambia el trabajo del próximo agente**:

- **Nunca reimplementes una regla que ya tiene contrato.** Hay seis, y los seis los consume
  también la web, así que divergir se nota: `Booking\Contracts\ProductCatalog` (qué se vende),
  **`CartPricing` (cuánto suma y cuánto se cobra ahora)**, `ReservationAdmission` (quién puede
  reservar), `Payments\Services\PaymentInitiator` (cómo se abre un cobro),
  `Identity\Services\PasswordLogin` y `SelfSignup`/`PasswordRecovery` (auth).
  `ModuleContractsTest` lo comprueba con dobles: si un consumidor vuelve a decidir por su cuenta,
  cae.
- **La cesta que viaja por la API tiene una sola forma**: `Http\Api\CartPayload` (reglas de
  validación + traducción `product_id`/`quantity` → `ticket_type_id`/`qty`). La comparten
  `orders/quote` y, en cuanto existan, la disponibilidad de 4b y el `POST orders` de 4c. **No
  escribas otras reglas de cuerpo de cesta**: es el equivalente de `Cart::sanitize()` en la capa de
  entrega, y existe justo para que no haya tres.
- **Toda lista usa `ApiCollection`** (`data` + `meta`) y **todo esquema nuevo nace con
  `additionalProperties: false` + `required` completo**, o `ApiContractTest` lo rechaza.
- ⚠️ **`Origin`/`Referer` de un dominio *stateful* hace falta en TODAS las peticiones de la SPA**,
  no solo en el login: sin él no hay sesión y un `GET /me` da 401 aunque la cookie sea válida. Todo
  endpoint que toque `session()` necesita la guarda de `Http\Api\Concerns\RequiresStatefulSession`
  — se olvidó dos veces y las dos las encontró un `curl`, no la suite.
- ⚠️ **Invalidar credenciales tiene UN solo sitio**: `User::revokeAllAccess()`/`revokeOtherAccess()`
  (`INVARIANTES RGPD-06`). `AccessRevocationTest` prohíbe que nadie más escriba en `sessions` o
  `personal_access_tokens`.
- **Sanctum está listo pero SIN emisor de tokens**: la emisión viaja a Fase 6 con la app que los
  consuma (`DECISIONES #29a`). La revocación ya está hecha y probada.

## ▶ Próximo paso
**Fase 3 · paso 4b — DISPONIBILIDAD CON LA CESTA**: `GET availability/{product}/dates` y
`POST availability/{product}/times`. El paso 4 va partido en cuatro (spec §9): **4a ✅ hecho**
(tarificación + `orders/quote`), quedan 4b, 4c (`POST orders` + `payment`) y 4d (`payment-status`
con estados reales + token de retorno).
**Es el paso de más riesgo de la fase**: `VERIFY_CONC=1` y los dos verificadores sobre MySQL son
OBLIGATORIOS en TODAS sus unidades, porque el `pre-push` los exige en cuanto se toque un controlador
`Order*`/`Payment*`/`Checkout*`/`Quote*`/`Availability*` o el núcleo.

**Lo que 4b tiene que resolver** (medido al hacer 4a): la disponibilidad **lleva la cesta** porque
`SlotOffer::offerableTimes()` descuenta los ocupantes provisionales de la propia cesta (`AFORO-02`);
un GET sin cesta ofrecería horas que el checkout rechazaría. Hoy esa derivación —de líneas de cesta
a ocupantes— vive en `Purchase::cartOccupants()`/`cartPackOccupants()`, o sea **otra vez dentro de
una clase de UI**: es la extracción que 4b tiene que hacer antes de exponer nada, igual que 4a hizo
con el precio. El cuerpo de la cesta ya está resuelto: usa `Http\Api\CartPayload`, no escribas otro.

**El terreno ya está preparado — NO reimplementes nada de esto:**
- **Precio** → `Booking\Contracts\CartPricing` (paso 4a): qué suma la cesta y qué se cobra online,
  con la señal por línea. `CartPricerTest` compara sus importes con el pedido REAL, así que es el
  espejo verificado de `OrderCreator`.
- **Admisión** → `Booking\Contracts\ReservationAdmission` (paso 2): pausa, tope de pendientes,
  frecuencia y la extensión atómica del hold. `POST orders` llama a `admitReservation()`, que
  CONSUME ficha; el reintento, a `admitPaymentRetry()`.
- **Creación** → `OrderCreator` (`AFORO-01`: el lock con `zone_id` literal es la PRIMERA sentencia
  de la transacción; no metas ningún SELECT antes).
- **Ida del pago** → `Payments\Services\PaymentInitiator::open()`/`reopen()` (paso 2): crea el
  `Payment`, firma el formulario y deja el rastro de fallo en `audit_logs`. Lanza
  `PaymentInitiationException`; el destino del pedido lo decide el llamante
  (`Order::releaseAfterFailedPaymentStart()` tras un primer cobro fallido, nada tras un reintento).
- **Oferta de fechas/horas** → `SlotOffer`, fuente única web↔panel (`AFORO-02`). La disponibilidad
  **lleva la cesta**: `offerableTimes()` descuenta los ocupantes provisionales de la propia cesta,
  así que un GET sin cesta ofrecería horas que el checkout rechazaría.
- **Tarificación** → hecha en 4a: `CartPricing`. `RateResolver` y `AddonResolver` siguen siendo las
  reglas, pero ya no se llaman desde fuera del contrato.

**Lo que 4c y 4d SÍ tienen que resolver** (spec §4.5, medido en la revisión):
- `payment-status` con **estados reales** derivados de `Order.status` MÁS el último `Payment`
  (`pending`/`authorized`/`paid`/`failed`/`superseded`). Hoy el rechazo solo viaja por sesión, así
  que la API diría «pendiente» 15 min y luego «expirado», nunca «reintenta».
- El **token de retorno**: `HomeController` hace `Cache::pull` ANTES de comprobar la sesión, así
  que lo quema para un cliente sin cookie.
- `redsys_merchant_url` (notificación S2S) es **prerequisito DURO del cliente móvil** (Fase 6): sin
  ella y con terminal data-less, el único camino a `paid` es la vuelta del navegador → el pedido
  caduca con la tarjeta cobrada (`PAY-02`).

**Antes de escribir código, lee `docs/INVARIANTES.md` §1 (PAY) y §2 (AFORO)** —es obligatorio por
la regla 2 de `CLAUDE.md`— **y `docs/specs/api-v1.md` §10 → §10.octies**: cuarenta y cinco puntos
medidos en los pasos anteriores. Los que más pesan en lo que queda del paso 4: el presupuesto de
consultas se mide por PENDIENTE y no por techo (§10.ter 17); la validación de contrato hay que
pedirla con `assertValidResponse()` (16); todo endpoint que toque `session()` necesita la guarda de
`RequiresStatefulSession` y el `curl` sin encabezados es el que encuentra su ausencia (§10.septies
34); un recurso que devuelve el controlador necesita `$wrap = null` (§10.octies 43); un comentario
que declara una equivalencia de importes es una petición de test, no una prueba (38); y en la API se
valida la forma con reglas en vez de sanear en silencio, que es correcto para una sesión y pésimo
para un cliente (44).

Después: paso 5 (post-form migrado, 2.º consumidor). El corte completo está en el spec §9.

**Pendiente del owner** (❗): 2FA del panel (sin plan — `DEUDA.md`) · mecanismo del primer admin de
producción (`INSTALACION-CLIENTE.md` §5) · backlog de producto de Fase 6.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` · MySQL `localhost:3308`
  · Mailpit `localhost:8028`. **Siempre `-u sail` en `exec`** (como root deja ficheros de root en
  `storage/` → 500 por permisos).
- BD dev sembrada con SaltoPark: `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña
  `password`. ⚠️ La BD dev arrastra ADEMÁS el par `…@jumpingjump.test` del import, así que
  `User::first()` devuelve uno del origen — usa el email completo al probar a mano.
- ⚠️ Si clonas de cero, comprueba que **`APP_URL` coincide con `APP_PORT`** en el `.env` (no
  versionado): con el puerto desalineado salen mal los enlaces absolutos de correo, las URLs
  firmadas y la derivación de CORS y de los dominios stateful de Sanctum (corregido el 2026-08-13).
- **El push exige `VERIFY_CONC=1`** —tras correr los dos comandos de `INVARIANTES §6`— si tocas
  `OrderCreator`, `RedsysReturnHandler`, `SlotGenerator`, **`PaymentInitiator`**,
  **`ReservationAdmissionPolicy`** o un controlador de API de pedidos/pagos/checkout/quote/
  disponibilidad. La lista viva es el `CRITICAL_RE` de `.githooks/pre-push`, y
  `CriticalPathGateTest` vigila que siga cubriendo lo que debe.

## Herencia
Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys
en sandbox y suite **2132** verde al importarla.
Recuento VIVO (lo verifica `docs-check` contra el código): 30 modelos · 72 migraciones ·
17 Filament Resources · **2376** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
