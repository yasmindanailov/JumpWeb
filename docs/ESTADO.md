# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-14**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — LOS 6 PASOS DEL CORTE (§9) CERRADOS + EL
CHECKOUT ORQUESTADO**: 0 cimientos · 1 lectura y catálogo · 2 admisión e ida de pago · 3 auth ·
**4 el dinero** (precio · disponibilidad · pedido y cobro · desenlace) · **5 post-form** ·
**cierre: la secuencia del dinero baja al dominio** (`DECISIONES #37`).
✅ **El ítem que estaba pendiente del owner se resolvió el 2026-08-13**: partió `PaymentProvider` en
dos y aprobó la mitad medida. La orquestación está hecha; **el segundo driver de pasarela viaja a
Fase 6** con la app, su primer lector real (`DEUDA.md`, severidad rebajada). Lo único abierto de la
fase es la emisión de tokens Bearer, también de Fase 6.
- Suite **2610 en verde** (14186 aserciones, `--parallel` ~63 s) · **15 tests JS** (`node --test`) · Pint limpio ·
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
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-14, 8+8 workers, la
  última vez en el paso 4.0b·5). ⚠️ **El `CRITICAL_RE` del hook se amplió con `Cart`** en ese paso:
  un controlador de API que DECIDE sobre aforo entra en el gate aunque no escriba nada, igual que su
  hermano `Availability*`. `CriticalPathGateTest` lo vigila con el fichero en `CRITICAL_FILES`. ⚠️ El `CRITICAL_RE` del `pre-push` cubre `PaymentInitiator`,
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

**Fase 4 — la SPA del sidebar, EN CURSO.** Diseño en `docs/specs/sidebar-spa.md` (**v3**) y
decisiones del owner en **`DECISIONES #38`**. **Lee las dos cosas antes de tocar nada**: la v1 del
spec fue declarada INSUFICIENTE por tres revisores y tenía tres afirmaciones falsas que la hacían
inaplicable; §7 dice cuáles.

### Lo que ya está hecho (todo empujado y verificado por mutación)

- **4.0a — la costura, sin una línea de Vue.** (a) La landing declara su INTENCIÓN
  (`$store.purchase.openWith({…})`) en vez de despachar eventos de Livewire: con otro motor esos
  `dispatch` **no fallaban, no hacían nada** (`SidebarSeamTest`). (b) El desenlace del pago tiene un
  solo dueño, `Http\Sidebar\SidebarEntry` (`SidebarEntryTest`, guarda de «un solo dueño»).
  ⚠️ **Dato medido que explica su forma**: el componente es `lazy`, así que su `mount()` **NO corre
  en la petición del layout**. Por eso hay DOS verbos: el layout usa `peek()` (mirar sin consumir) y
  el MOTOR usa `consume()`. Cuando el motor sea la SPA —mismo documento— consumirá el layout.
- **Gates**: el `pre-push` corre `npm run build` **antes** de la suite y, desde 4.1, `npm run test:js`
  (`PrePushGateTest` vigila cada paso). El puerto de Vite se DERIVA de `config('app.vite_dev_port')`.
- **4.2 — CERRADO: los tres pasos** (`DECISIONES #44`–`#46`). Lo importante no son los pasos: es **la
  red**, y son DOS tipos:
  · **Paridad de ÁRBOL** (`SidebarDomContractTest`) — compara lo que emite cada motor.
  · **Paridad de COMPOSICIÓN** (`SidebarCalendarParityTest`) — porque el diff de árbol le pasa a Vue
    el view-model del SERVIDOR, así que no vería una rejilla que el cliente compone mal. Sus dos
    casos frontera salieron de medir: un mes que empieza en domingo, y el huso del navegador
    (`new Date('YYYY-MM-DD')` es UTC). ⚠️ La primera versión del caso de husos **pasaba con el bug
    dentro** — el desfase solo mueve el lunes si el día 1 ya era lunes.
  · **Paridad de DATOS entre fuentes** (`SidebarAddonsParityTest`) — el paso 3 destapó que el
    componente usaba los nombres del view-model de Livewire y el endpoint publica otros: **diff
    verde y cajón real con filas vacías**. Ahora se comparan campo a campo.
  ⚠️ **La regla que deja el paso**: si el cliente recibe un dato de la API o lo compone él, el diff de
  árbol NO lo verifica. Y ojo con los casos frontera: la acotación del selector no se comprobaba
  hasta llevar la cantidad a sus topes (medido: cambiar el techo no ponía el diff en rojo).
  ⚠️ **Declarado y no resuelto**: las cabeceras de día y el nombre del mes los compone el servidor con
  Carbon y el cliente con `Intl`, así que el texto visible puede diferir (§4.5 ya lo avisaba). `SidebarDomContractTest` compara el ÁRBOL renderizado de los dos motores en el gate y
  sin navegador (`@vue/server-renderer` viene con Vue). Tres cosas para el siguiente:
  · **Node no carga `.vue`**: el renderizador se compila con `npm run build:ssr`, que está en el
    `pre-push`. Sin ese paso el test de `CE-2` no corre.
  · **El diff normaliza el andamiaje de cada motor y conserva lo que el CSS mira.** Dentro de un
    `<svg>` no desciende —es geometría—, pero que HAYA un `<svg>` sí se comprueba.
  · ⚠️ **Los iconos del sidebar envuelven su SVG en un `<span class="icon …">` y ese envoltorio es
    CONTRATO** (`.catalog-acc__head span` lo mira). El primer intento emitía el `<svg>` suelto: todas
    las clases correctas y el estilo perdido igual. Lo cazó el diff, no la lectura del Blade.
- **4.1 — cimientos SPA hechos** (`DECISIONES #43`). El motor monta tras el flag `sidebar.engine`
  (default y fallback: `livewire`). Cuatro cosas que condicionan lo que viene:
  · **El entry se trae con `import()` en la PRIMERA apertura**, nunca con la página: el enganche
    cuesta medio kB en la landing y el motor son 69 kB aparte. `SidebarBundleBudgetTest` vigila que
    el chunk siga existiendo — un `import` estático lo fundiría con el entry sin que el diff lo vea.
  · **La lógica va en `resources/js/sidebar/machine.js`, un módulo PLANO sin Vue**, probado con
    `node --test`. Si la lógica baja a un componente o a un store, pierde su red (CE-6).
  · **El cliente HTTP es `api.js`** y lleva ya las trampas medidas: `credentials`, `Accept`, el
    `XSRF-TOKEN` **url-decodificado** y el reintento único ante un 419.
  · ⚠️ **Con la SPA el layout CONSUME el desenlace del pago** (con Livewire solo lo mira): el
    componente es `lazy` y su `mount()` corre después; aquí el motor es el propio documento.
- **4.0c — CERRADO el 2026-08-14, las dos mitades** (`DECISIONES #42`). Tokenización de lo
  TEMATIZABLE **43% → 49% → 75%**; colores crudos **13 → 3**. Nada movió un píxel. Tres cosas para
  quien siga:
  · ⚠️ **«Decidir una escala» era la decisión equivocada**: medido, una escala canónica movería el
    **52-55%** de los tamaños de letra, y el spec §2 declara el rediseño visual FUERA de alcance. La
    salida fue una escala **multiplicativa sobre una unidad** (`--fs-13: calc(var(--fs-unit) * 13)`):
    cero píxeles movidos **y** un punto de control real — cambiar `--sp-unit` airea el cajón entero
    conservando proporciones. La escala canónica queda como decisión de producto abierta, ya medida.
  · **La verificación no fue «a ojo»**: como no se redondea, revertir los tokens a sus literales
    devuelve los dos CSS **byte a byte idénticos**. Es más fuerte que un screenshot.
  · ⚠️ **Destapó un fallo real en producción**: un comentario de `site.css` se cerraba a media frase
    (una pareja asterisco-barra dentro del texto) y el navegador **descartaba la regla siguiente** —
    `.gf-sr-only`, la que oculta el texto para lectores de pantalla en la hoja del post-form—, así
    que el aviso de progreso **se veía**. Corregido, con guarda en `SidebarTokenBudgetTest`.
- **4.0b — CERRADO el 2026-08-14: los SEIS huecos**. `GET /me/reservation-eligibility` ·
  `GET /config` · `GET /booking/status` · el resumen del pedido entero (campos sin PII +
  `GET /orders/{code}/event-data`) · `POST /cart/validate-line` ·
  `POST /catalog/products/{product}/addons`. **La API ya tiene todo lo que la SPA necesita.**
- **4.0b·4b — la PII sale del pedido** (`DECISIONES #39`, spec §4.4.6). Tres cosas que conviene
  saber antes de tocar cualquier endpoint que devuelva datos de un menor:
  · **La guarda vive en los endpoints que NO deben llevar el dato**: `me/orders` y
    `GET orders/{code}` se comprueban sobre el CUERPO ENTERO de la respuesta, no campo a campo —
    quien «ahorre una petición» mañana lo llamará de otra forma y tiene que caer igual.
  · **Solo la fase `booking`**: `event_data` mezcla las dos, y las de post-form ya tienen endpoint
    propio que se abre con firma. ⚠️ Consecuencia aceptada: lo que un operador rellene de post-form
    desde el panel no sale por aquí.
  · **La composición es de `TicketType::eventAnswers()`**, fuente única desde este paso (estaba
    copiada en `Purchase` y en `ReservationSlip`, y el endpoint iba a ser la tercera). La del panel
    NO se unificó —enseña las claves huérfanas, que no tienen fase que filtrar— y está en `DEUDA.md`.

- **4.0b·6 — validar una línea antes de la cesta** (`DECISIONES #40`, spec §4.4.2). Nace
  `Booking\Contracts\CartLineValidation` y **la compra web lo consume**: `addToCart()` ya no decide,
  pide el veredicto y traduce el «no». Tres cosas que valen para el próximo paso:
  · ⚠️ **Extraer una regla y no hacer que el consumidor viejo la use es copiar, no extraer.** Aquí
    la delegación encontró en el primer intento un fallo invisible en el diff: `Cart::sanitize()`
    fuerza `max(1, qty)` —correcto para una cesta guardada— y aplicado a una línea CANDIDATA
    convertía «todavía no he elegido cuántos» en un 1. Lo cazó `PurchasePanelTest`.
  · **Un validador previo valida contra lo que mirará el JUEZ, no contra lo que miraba la pantalla
    de la que salió**: la franja se comprueba contra la OFERTA, porque `maxQuantity()` responde de
    una franja concreta aunque no se ofrezca (ignora día pasado, corte intradía, ventana y
    antelación).
  · **Los `problems` van sin contexto**: el mínimo, el tope y las etiquetas ya los publican
    `catalog/products/{id}` y `config`. El dominio sí los lleva —no sabe quién pregunta—; no
    republicarlos es decisión de la capa de entrega.

- **4.0b·5 — complementos resueltos** (`DECISIONES #41`). Venía marcado como el más arriesgado del
  paso y acabó sin tocar `AddonResolver` ni a sus dos consumidores. La lección, que sirve para el
  resto de la fase:
  · ⚠️ **«Extraer» y «publicar» no son lo mismo.** En 4.0b·6 la regla estaba dentro de un componente
    Livewire y no hacer que la web la consumiera habría sido copiarla; aquí ya vivía en el dominio y
    las dos superficies ya la compartían, así que delegar no arreglaba nada y sí movía la plantilla
    del paso con más clics. **La pregunta que decide es «¿hay dos copias?»**, no «¿debería la web
    usar el contrato?».
  · **El dinero viaja en la misma respuesta** delegando en `CartPricing`, y está MEDIDO: componer
    resolución y tarificación cuesta las mismas consultas que pedirlas por separado, con la mitad de
    viajes y de fichas de `throttle` (60/min compartido). La pendiente la fija `ApiOverheadTest`.
  · **Un test de «cadena» puede pasar sin probar la cadena**: el de la poda de dependencias pasaba
    igual con una poda de un nivel, porque el orden natural ya la resolvía en una pasada. Si se
    prueba un algoritmo iterativo, el caso tiene que forzar el orden que obliga a iterar.

### Por dónde SEGUIR, en este orden

**Cimientos cerrados: 4.0a, 4.0b, 4.0c y 4.1.** El motor SPA monta, tiene red y no pesa en la
landing. Lo que sigue es **transcribir pasos**, y cada uno cierra su paridad al final.

1. **4.3 — la cesta y el presupuesto** (`§4.10`). Lo que ya está resuelto y NO hay que rehacer: el
   dinero sale de `POST orders/quote` (`CartPricing`), y si una línea entra en la cesta lo dice
   `POST cart/validate-line` — con la fusión, el re-tope y los campos obligatorios ya decididos.
   ⚠️ **La cesta de la SPA vive en `localStorage` SIN `event_data`** (`DECISIONES #38(d)`, RGPD): al
   restaurarla, las líneas de pack piden esos campos otra vez. Es la única desviación consciente de
   la paridad de toda la fase. Y la cesta persistida guarda el id de su titular y se purga al
   cambiar de identidad — sin eso, la cesta de Alice sobrevive al login de Bob en la tablet.

   **El método de trabajo, ya rodado**: añade el caso a `SidebarDomContractTest` ANTES de
   transcribir, y transcribe hasta que el diff calle.
   ⚠️ **Y recuerda su límite**: el diff alimenta a Vue con datos del SERVIDOR, así que **no ve** lo
   que el cliente recibe de la API con otros nombres ni lo que compone él. Para eso hacen falta
   paridades de DATOS aparte — ya hay dos de ejemplo (`SidebarCalendarParityTest`,
   `SidebarAddonsParityTest`).
2. **4.3 en adelante** — el corte completo, en §4.10 del spec. ⚠️ Entre 4.5 y 4.6 **no se despliega
   el flag**: quien pague en medio volvería a un cajón mudo.
3. **Lo que queda ABIERTO como decisión de producto, no como tarea**: la escala tipográfica canónica
   (medida: movería el 52-55% de los tamaños) y el formato de importe quemado en español, los dos en
   `DEUDA.md`.

### Tres cosas que conviene saber antes de tocar Fase 4

- **El contrato visual es el ÁRBOL, no las clases** (§4.2): 90 de 292 selectores son estructurales
  o dependen del tipo de elemento. Un `<div>` donde había un `<button>` pierde el estilo con el
  contrato de clases cumplido al 100%.
- **El sidebar no es un nodo**: 11 vistas usan su store Alpine. El paso final retira el puente
  `$wire.step`↔store, **no Alpine** —lo trae Livewire y lo usan cookies, nav y accesibilidad—.
- **i18n sigue SIN canal** (§4.5): 169 claves × 3 locales (más un `zh_CN` parcial) salen hoy de
  `__()` en servidor. La SPA no tiene de dónde sacarlas; el plan es un payload JSON en el montaje.

**Contexto de la API que sigue vigente.** Antes de
escribir una línea de Vue, lee `docs/specs/api-v1.md` §10 → §10.sexdecies: son ochenta y tres
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
es la regla 2 de `CLAUDE.md`. Y si trabajas sobre la API, `docs/specs/api-v1.md` §10 → §10.sexdecies:
ochenta y tres puntos MEDIDOS al construirla y al abrir Fase 4. Los que más se repiten como causa de error:
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
17 Filament Resources · **2610** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
