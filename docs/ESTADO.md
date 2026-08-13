# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — LOS 6 PASOS DEL CORTE (§9) CERRADOS + EL
CHECKOUT ORQUESTADO**: 0 cimientos · 1 lectura y catálogo · 2 admisión e ida de pago · 3 auth ·
**4 el dinero** (precio · disponibilidad · pedido y cobro · desenlace) · **5 post-form** ·
**cierre: la secuencia del dinero baja al dominio** (`DECISIONES #37`).
✅ **El ítem que estaba pendiente del owner se resolvió el 2026-08-13**: partió `PaymentProvider` en
dos y aprobó la mitad medida. La orquestación está hecha; **el segundo driver de pasarela viaja a
Fase 6** con la app, su primer lector real (`DEUDA.md`, severidad rebajada). Lo único abierto de la
fase es la emisión de tokens Bearer, también de Fase 6.
- Suite **2500 en verde** (9699 aserciones, `--parallel` ~63 s) · Pint limpio ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- ⚠️ **El `pre-push` corre ahora también `npm run build`, ANTES de la suite** (2026-08-13). Se añadió
  tras un fallo REAL: un build interrumpido dejó `public/build/manifest.json` a 0 bytes y **toda la
  web pública respondió 500**. `public/build` está en `.gitignore`, así que el manifest no viaja en
  el commit y nada lo miraba; `SUITE-05` ya lo pedía por escrito. `PrePushGateTest` vigila los
  cuatro pasos del gate y que el build vaya antes que la suite.
- **Auditorías de dependencias son verificación de CIERRE, no de instalación** (`DECISIONES #25`):
  el árbol npm pasó de 0 a 5 avisos en unas horas sin que el lock cambiara. Correr
  `composer audit` y `npm audit` en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-13, 16 workers, la
  última vez en el cierre del checkout). ⚠️ El `CRITICAL_RE` del `pre-push` cubre `PaymentInitiator`,
  `ReservationAdmissionPolicy`, **`SlotOffer`** (añadido en 4b), **`CheckoutOrchestrator`** (añadido
  en el cierre) y **todo controlador de API `Order*`/`Payment*`/`Checkout*`/`Quote*`/`Availability*`**:
  tocarlos exige `VERIFY_CONC=1` tras correr los dos comandos (`INVARIANTES §6`). Todo el paso 4 los
  dispara, así que cuenta con ellos en cada commit.
  ⚠️ **El gate NO cubre las superficies web** (`Livewire\Tickets\Purchase`, `RetryPaymentController`):
  un commit que solo las toque no lo dispara aunque estén en el camino del dinero. Córrelos igual.
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

## ▶ Qué hay hecho de la API (Fase 3, pasos 0 → 5)
**El inventario NO se repite aquí**: la superficie exacta la declara `openapi/v1.yaml` —que es el
contrato y manda sobre el código— y el porqué de cada paso está en `DECISIONES #24`, `#26`–`#36` y
en `docs/specs/api-v1.md` §9. Lo que sigue es solo lo que **cambia el trabajo del próximo agente**:

- **Nunca reimplementes una regla que ya tiene contrato.** Hay nueve, y los consume también la web,
  así que divergir se nota: `Booking\Contracts\ProductCatalog` (qué se vende),
  **`CartPricing` (cuánto suma y cuánto se cobra ahora)**, **`AvailabilityOffer` (qué días y horas
  quedan, con la cesta descontada)**, `ReservationAdmission` (quién puede reservar),
  **`ReservationCheckout` (EL ORDEN: admitir → crear → abrir cobro)**, **`PaymentInitiation` (cómo se
  abre un cobro)**, `Identity\Services\PasswordLogin` y `SelfSignup`/`PasswordRecovery` (auth).
  `ModuleContractsTest` lo comprueba con dobles: si un consumidor vuelve a decidir por su cuenta,
  cae.
- ⚠️ **`PaymentInitiation` es el contrato raro y conviene saberlo antes de buscarlo**: es un puerto
  **REQUERIDO** —lo que Booking NECESITA de una pasarela—, así que vive en `Booking\Contracts` pero
  lo implementa Payments y **su bind está en `PaymentsServiceProvider`**, no en el de Booking. La
  regla que lo explica: *el puerto vive en el módulo cuyos tipos habla* (`DECISIONES #37`).
- **La AUTORIZACIÓN por firma tiene una sola forma** (paso 5): `Http\Concerns\AuthorizesGuestForm`,
  compartida por la página web y la API. Su escalada **403 → 410 → 404** no es intercambiable —
  autorizar antes de comprobar elegibilidad es lo que impide enumerar reservas por el código de
  estado—, y hay test por mutación de ello. Y ojo al canje: **la firma cubre la URL EXACTA**, así
  que una firma de la web NO vale en la API (403 vs 200, medido); las URLs de API se firman aparte
  con la misma caducidad (`OrderItem::guestFormApiUrls()`).
- **La cesta que viaja por la API tiene una sola forma**: `Http\Api\CartPayload` (reglas de
  validación + traducción `product_id`/`quantity` → `ticket_type_id`/`qty`). La comparten
  `orders/quote`, `availability/{product}/times` y `POST orders`. **No
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
**Fase 4 — la SPA del sidebar, EN CURSO.** Diseño aprobado en `docs/specs/sidebar-spa.md`
(**v2**, tras revisión adversarial ×3 que declaró la v1 INSUFICIENTE). **Léelo antes de tocar
nada**: la v1 tenía tres afirmaciones falsas que la hacían inaplicable, y §7 dice cuáles.

Decisiones del owner ya tomadas (2026-08-13): alcance = **solo el cajón** (`/mi-cuenta` sigue en
Blade) · tema = **tokens + hoja de estilos por instalación** · dependencias = **Vue 3 + Pinia** ·
la cesta se persiste **sin `event_data`** (RGPD: son nombre, edad y alergias de un menor) · la
**tokenización de `site.css` sube a Fase 4** (paso 4.0c).

**Hecho: paso 4.0a, primera mitad.** La landing ya no sabe qué motor mueve el cajón: la intención
se declara con `$store.purchase.openWith({…})` y cada motor registra su adaptador
(`SidebarSeamTest`, verificado por mutación). Escrito el contrato de `mode`/`identifying`, y fuera
`alpinejs` de `package.json` (estaba declarado y no lo importaba nadie).

**Hecho también: 4.0a segunda mitad.** El desenlace del pago (confirmado · denegado · verificando)
tiene un solo dueño: `Http\Sidebar\SidebarEntry`. Antes las tres claves de sesión se nombraban a
mano en cinco ficheros y solo `Purchase::mount()` las olvidaba.
⚠️ **Dato medido que explica su forma**: el componente es `lazy`, así que su `mount()` **NO corre en
la petición del layout** sino en la del `lazy`. Por eso hay dos verbos y no uno: el layout usa
`peek()` (mirar sin consumir, para decidir si el cajón se abre solo) y el MOTOR usa `consume()`.
Cuando el motor sea la SPA —que vive en el mismo documento— consumirá el layout. Guarda ejecutable
de «un solo dueño» en `SidebarEntryTest`, verificada por mutación en PHP y en Blade.

Luego: **4.0b** los cinco huecos de API (§4.4 del spec; el más gordo son los complementos
RESUELTOS) · **4.0c** tokenizar `site.css` · **4.1** cimientos SPA.

**Contexto de la API que sigue vigente.** Antes de
escribir una línea de Vue, lee `docs/specs/api-v1.md` §10 → §10.terdecies: son setenta y tres
puntos MEDIDOS al implementar la API, y varios son trampas que la SPA va a pisar. Los tres que más:
- ⚠️ **`Origin`/`Referer` de un dominio *stateful* hacen falta en TODAS las peticiones**, no solo en
  el login: sin ellos no hay sesión y un `GET /me` da 401 aunque la cookie valga (§10.sexies 28).
- ⚠️ **La disponibilidad LLEVA la cesta** (`AFORO-02`) y publica DOS números: `available` es para
  mostrar y `max_quantity` para acotar el selector — en un pack no coinciden (§10.nonies 46).
- ⚠️ **La firma cubre la URL EXACTA**: las URLs de API se firman aparte (§10.duodecies 64).

**Lo que la fase deja preparado y NO hay que rehacer**: todo el flujo de compra por API —catálogo,
disponibilidad, presupuesto, pedido, cobro, reintento, desenlace—, la auth completa, «mis pedidos»,
«mis reservas» y el post-form. El contrato manda: `openapi/v1.yaml`.

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
- **Ida del pago** → `Booking\Contracts\PaymentInitiation` (`open()`/`reopen()`), implementado por
  `Payments\Services\PaymentInitiator`: crea el `Payment`, firma el formulario y deja el rastro de
  fallo en `audit_logs`. Lanza `PaymentInitiationException` (que vive en `Payments\Contracts`, no en
  un `Exceptions/`: es lo que lanza el puerto).
- **LA SECUENCIA** → `Booking\Contracts\ReservationCheckout` sobre `CheckoutOrchestrator` (cierre de
  Fase 3, `DECISIONES #37`). **Es el sitio ÚNICO donde vive el orden**, que es la regla: admitir
  consumiendo ficha → crear con la ventana de retención (`AFORO-10`) → abrir el cobro sobre el
  pedido persistido → soltarlo **solo** si era el primer intento. Antes estaba copiado en cinco
  puntos de cuatro clases de entrega.
  ⚠️ **No lo reescribas en una superficie nueva**: pide `start()`/`retry()` y traduce el resultado.
  `CheckoutSequenceTest` lo prohíbe ejecutablemente fuera de `app/Domain`, y `CheckoutOrchestratorTest`
  fija cada punto del orden con las 5 mutaciones comprobadas.
  ⚠️ **No envuelvas la secuencia en una transacción**: el rastro de incidencia haría rollback
  (`PAY-05`) y el lock de franjas quedaría sostenido durante la firma (`AFORO-01`). Hay test.
- **Creación y cobro por API** → `POST orders`, `GET orders/{code}` y `POST orders/{code}/payment`
  (4c). Los controladores ya solo traducen HTTP; el orden lo pone el contrato. ⚠️ El `catch` de
  `PaymentInitiationException` que devuelve **502** sí es suyo y es obligatorio: `ApiExceptionRenderer`
  solo conoce `ReservationException`, así que quitarlo degradaría el contrato a un 500 en silencio.
- **Oferta de fechas/horas** → hecha en 4b: `Booking\Contracts\AvailabilityOffer` sobre `SlotOffer`
  (`AFORO-02`), con la cesta descontada. Ojo al par de números que publica: `available` es para
  MOSTRAR y `max_quantity` para ACOTAR el selector — en un pack no coinciden.
- **Desenlace del pago** → hecho en 4d: `GET orders/{code}/payment-status`, con DOS ejes
  (`order_status` de la reserva · `payment_status` del último intento) y el motivo del rechazo como
  código y como texto. `Order::paymentStatus()`/`declinedResponseCode()` son la fuente; el rechazo
  anterior se oculta en cuanto hay otro cobro en curso.
- **Tarificación** → hecha en 4a: `CartPricing`. `RateResolver` y `AddonResolver` siguen siendo las
  reglas, pero ya no se llaman desde fuera del contrato (`Purchase` no importa ninguno de los dos).

**Si tocas dinero, aforo, RGPD o seguridad, lee antes `docs/INVARIANTES.md`** (§1 PAY, §2 AFORO) —
es la regla 2 de `CLAUDE.md`. Y si trabajas sobre la API, `docs/specs/api-v1.md` §10 → §10.terdecies:
setenta y tres puntos MEDIDOS al construirla. Los que más se repiten como causa de error:
- el presupuesto de consultas se mide por PENDIENTE y no por techo (§10.ter 17);
- la validación de contrato hay que PEDIRLA con `assertValidResponse()` (§10.ter 16);
- un recurso que devuelve el controlador necesita `$wrap = null` (§10.octies 43);
- un comentario que declara una equivalencia es una petición de test, no una prueba (§10.octies 38);
- en la API se valida la forma con reglas en vez de sanear en silencio: lo primero es correcto para
  una sesión y pésimo para un cliente (§10.octies 44).

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
- **El push exige `VERIFY_CONC=1`** —tras correr los dos comandos de `INVARIANTES §6`— si tocas el
  núcleo de dinero/aforo. **La lista viva es el `CRITICAL_RE` de `.githooks/pre-push`**; no se copia
  aquí para que no envejezca (ya lo hizo una vez), y `CriticalPathGateTest` vigila que siga
  cubriendo lo que debe.

## Herencia
Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys
en sandbox y suite **2132** verde al importarla.
Recuento VIVO (lo verifica `docs-check` contra el código): 30 modelos · 72 migraciones ·
17 Filament Resources · **2500** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
