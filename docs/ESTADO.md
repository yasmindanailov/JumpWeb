# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — pasos 0 a 4 CERRADOS (el **paso 4, el del
dinero, está COMPLETO**: 4a precio · 4b disponibilidad · 4c pedido y cobro · 4d desenlace); queda el
**paso 5** (post-form migrado, 2.º consumidor) y con él termina la fase.**
- Suite **2438 en verde** (9419 aserciones, `--parallel` ~63 s) · Pint limpio ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- **Auditorías de dependencias son verificación de CIERRE, no de instalación** (`DECISIONES #25`):
  el árbol npm pasó de 0 a 5 avisos en unas horas sin que el lock cambiara. Correr
  `composer audit` y `npm audit` en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-13, 16 workers, la
  última vez en el paso 4d). ⚠️ El `CRITICAL_RE` del `pre-push` cubre `PaymentInitiator`,
  `ReservationAdmissionPolicy`, **`SlotOffer`** (añadido en 4b) y **todo controlador de API
  `Order*`/`Payment*`/`Checkout*`/`Quote*`/`Availability*`**: tocarlos exige `VERIFY_CONC=1` tras
  correr los dos comandos (`INVARIANTES §6`). Todo el paso 4 los dispara, así que cuenta con ellos
  en cada commit.
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

## ▶ Qué hay hecho de la API (Fase 3, pasos 0 → 4)
**El inventario NO se repite aquí**: la superficie exacta la declara `openapi/v1.yaml` —que es el
contrato y manda sobre el código— y el porqué de cada paso está en `DECISIONES #24`, `#26`–`#35` y
en `docs/specs/api-v1.md` §9. Lo que sigue es solo lo que **cambia el trabajo del próximo agente**:

- **Nunca reimplementes una regla que ya tiene contrato.** Hay siete, y los siete los consume
  también la web, así que divergir se nota: `Booking\Contracts\ProductCatalog` (qué se vende),
  **`CartPricing` (cuánto suma y cuánto se cobra ahora)**, **`AvailabilityOffer` (qué días y horas
  quedan, con la cesta descontada)**, `ReservationAdmission` (quién puede
  reservar), `Payments\Services\PaymentInitiator` (cómo se abre un cobro),
  `Identity\Services\PasswordLogin` y `SelfSignup`/`PasswordRecovery` (auth).
  `ModuleContractsTest` lo comprueba con dobles: si un consumidor vuelve a decidir por su cuenta,
  cae.
- **La cesta que viaja por la API tiene una sola forma**: `Http\Api\CartPayload` (reglas de
  validación + traducción `product_id`/`quantity` → `ticket_type_id`/`qty`). La comparten
  `orders/quote` y `availability/{product}/times`, y la usará el `POST orders` de 4c. **No
  escribas otras reglas de cuerpo de cesta**: es el equivalente de `Cart::sanitize()` en la capa de
  entrega, y existe justo para que no haya tres.
- **Toda lista usa `ApiCollection`** (`data` + `meta`) y **todo esquema nuevo nace con
  `additionalProperties: false` + `required` completo**, o `ApiContractTest` lo rechaza.
- **Los errores de NEGOCIO tienen código propio y mapa exhaustivo** (paso 4c):
  `Http\Api\ReservationErrorMap` traduce cada `ReservationException` del dominio a un
  `ApiErrorCode` estable, y `ReservationErrorMapTest` lee el dominio con el tokenizador — si añades
  un motivo de rechazo y no lo mapeas, el test lo nombra. **No agrupes códigos**: añadir uno es
  evolutivo, partir uno existente rompe a todo cliente ramificado sobre él.
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
**Fase 3 · paso 5 — POST-FORM MIGRADO** (`GET/PUT reservations/{id}/guest-form`), el SEGUNDO
consumidor de la API y lo único que queda de la fase. Su dificultad NO es el endpoint: es **cómo
autentica la API a un portador de firma**. La vía de hoy es una URL firmada de una ruta web
concreta, y la firma de Laravel cubre la URL exacta — **no autoriza un `PUT /api/v1/...`** (spec §3c
y §4.6.5). Hay que decidir y construir ese canje antes de exponer nada.
⚠️ Lo que NO se puede regresar al migrarlo: el enlace CADUCA (`RGPD-03`, fecha del evento + 14 días,
fuente única `Order::guestFormLinkExpiresAt`), devuelve **410 si el titular está anonimizado** (GET y
POST), la URL no lleva PII, y el orden 403/410/404 está escalonado a propósito para no filtrar la
existencia de una reserva (spec §6.6). Lee `docs/sistemas/POSTFORM-INVITADOS.md` y `INVARIANTES` §3.

**El paso 4 (el dinero) está COMPLETO.** `VERIFY_CONC=1` y los dos verificadores sobre MySQL siguen
siendo OBLIGATORIOS en cuanto se toque un controlador
`Order*`/`Payment*`/`Checkout*`/`Quote*`/`Availability*` o el núcleo (que desde 4b incluye
`SlotOffer`) — el post-form no debería tocarlos, pero el gate manda.
⚠️ `PAY-01` sigue intacto: `RedsysReturnHandler` es el ÚNICO que pasa una Order a `paid`; sondear el
estado no transiciona nada, y hay test de ello.

**Pendiente que hereda Fase 6** (declarado en el contrato, `DECISIONES #35`): un cliente NATIVO
averigua el desenlace del pago **solo sondeando** `payment-status` —la vuelta de la pasarela es una
redirección de navegador y `DS_MERCHANT_URLOK` no admite parámetros para un deep link—, y eso exige
`redsys_merchant_url` (notificación S2S) configurada: sin ella y con terminal data-less, el pedido
caducaría con la tarjeta ya cobrada (`PAY-02`).

**El terreno del DINERO ya está entero — NO reimplementes nada de esto:**
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
- **Creación y cobro por API** → hechos en 4c: `POST orders`, `GET orders/{code}` y
  `POST orders/{code}/payment`. ⚠️ **La SECUENCIA es la regla** y no la vigila ninguna guarda de
  arquitectura: si tocas esos controladores, los cuatro tests de orden de `Api\V1\OrdersTest` son
  la red (se verificaron por mutación).
- **Oferta de fechas/horas** → hecha en 4b: `Booking\Contracts\AvailabilityOffer` sobre `SlotOffer`
  (`AFORO-02`), con la cesta descontada. Ojo al par de números que publica: `available` es para
  MOSTRAR y `max_quantity` para ACOTAR el selector — en un pack no coinciden.
- **Desenlace del pago** → hecho en 4d: `GET orders/{code}/payment-status`, con DOS ejes
  (`order_status` de la reserva · `payment_status` del último intento) y el motivo del rechazo como
  código y como texto. `Order::paymentStatus()`/`declinedResponseCode()` son la fuente; el rechazo
  anterior se oculta en cuanto hay otro cobro en curso.
- **Tarificación** → hecha en 4a: `CartPricing`. `RateResolver` y `AddonResolver` siguen siendo las
  reglas, pero ya no se llaman desde fuera del contrato (`Purchase` no importa ninguno de los dos).

**Antes de escribir código, lee `docs/INVARIANTES.md` §1 (PAY) y §2 (AFORO)** —es obligatorio por
la regla 2 de `CLAUDE.md`— **y `docs/specs/api-v1.md` §10 → §10.undecies**: sesenta y tres puntos
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
17 Filament Resources · **2438** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
