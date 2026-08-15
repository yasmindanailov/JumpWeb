# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-15**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) ✅ · Fase 4 (sidebar SPA) 🟦 — EN CURSO.**

**Fase 4, al detalle**: 4.0a ✅ (la costura) · 4.0b ✅ (los SEIS huecos de API) · 4.0c ✅ (tokenizar
`site.css`) · 4.1 ✅ (cimientos SPA) · 4.2 ✅ (pasos 1–3 transcritos con paridad demostrada) ·
**4.3 ✅ COMPLETO** (·1 armazón · ·2 pie y cesta · ·3 pausa · ·4 persistencia) · **4.4a ✅ COMPLETO**
(·1 elegibilidad · ·2 identificación) · **4.4b·1 ✅** (el alta desde el cajón, y el paso 7) ·
**4.5 ✅ COMPLETO** (·1 la cesta pide lo que le falta · ·2 **pagar y salir a la pasarela**) ·
**4.6 ✅ COMPLETO** (·1 la reserva creada · ·2 denegado y verificando) → **los ONCE pasos están
transcritos**, y el **extremo a extremo con navegador y pasarela real ya se ha hecho** (`#59`: encontró
que el motor NO funcionaba en producción, y se arregló). Lo que queda de la fase: **4.7** (la retirada
de `Purchase.php` y del puente, EN CURSO) y **4.4b·2** (el widget de Turnstile, aplazado a propósito
porque no se puede verificar sin claves de Cloudflare).
El corte está en `docs/specs/sidebar-spa.md` §4.10.
⚠️ **Los pasos se parten al implementarlos, y el criterio es siempre la DEPENDENCIA**, no la pantalla:
4.2 en tres, 4.3 en cuatro (`DECISIONES #47`–`#50`), 4.4a en dos (`#51`, `#52`), 4.4b en dos (`#53`),
4.5 en dos (`#54`, `#55`) y 4.6 en dos (`#56`, `#57`) — el sondeo del paso 11 aterriza en el paso 6,
así que el 6 fue primero. En 4.3 el pie no se podía
separar de la cesta porque el CTA del paso 3 es «Añadir al carrito» y `disabled` es un atributo que el
diff compara; en 4.4a, de las cinco salidas de `checkout()` solo dos tienen pantalla transcrita, así
que el tramo ·1 transcribe la DECISIÓN sin navegar.

**Fase 3 quedó cerrada** con los 6 pasos del corte más el checkout orquestado (`DECISIONES #37`). Lo
único que hereda Fase 6 es la emisión de tokens Bearer y el segundo driver de pasarela.
- Suite **2715 en verde** (15.538 aserciones, `--parallel` ~70 s) · **247 tests JS** (`node --test`) · Pint limpio ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- ⚠️ **La suite NO está auditada contra la FECHA, y ya mordió una vez** (`DECISIONES #64`,
  2026-08-15): tres casos amanecieron rojos sin que nadie tocara nada —dos porque la foto congelada
  del calendario caduca cada día, uno porque su fixture tiene tarifa de fin de semana—. Los tres
  están arreglados congelando el reloj, pero **nadie ha barrido el resto**: si te encuentras un rojo
  que no viene de tu cambio, **antes de tocar nada guarda el árbol y prueba en el commit anterior**
  —es lo que separó el diagnóstico en minutos de una sesión perdida—. Ficha en `DEUDA.md`.
- ⚠️ **El `pre-push` corre ahora también `npm run build`, ANTES de la suite** (2026-08-13). Se añadió
  tras un fallo REAL: un build interrumpido dejó `public/build/manifest.json` a 0 bytes y **toda la
  web pública respondió 500**. `public/build` está en `.gitignore`, así que el manifest no viaja en
  el commit y nada lo miraba; `SUITE-05` ya lo pedía por escrito.
  **El gate son hoy SEIS pasos** —docs-check · Pint · `npm run build` · `npm run build:ssr` ·
  `npm run test:js` · suite—, y `PrePushGateTest` los vigila uno a uno y comprueba que el build va
  antes que la suite. Los dos últimos entraron con la SPA: el bundle SSR hace falta para el diff de
  árbol (Node no carga `.vue` sin compilar) y `test:js` corre la máquina de estados del cajón.
- **Auditorías de dependencias son verificación de CIERRE, no de instalación** (`DECISIONES #25`):
  el árbol npm pasó de 0 a 5 avisos en unas horas sin que el lock cambiara. Correr
  `composer audit` y `npm audit` en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-14, 8+8 workers; la
  última vez en el paso 4.0b·5).
  **La lista viva de lo que exige `VERIFY_CONC=1` es el `CRITICAL_RE` de `.githooks/pre-push`** — no
  se copia aquí para que no envejezca (ya pasó una vez), y `CriticalPathGateTest` vigila que siga
  cubriendo lo que debe. En grandes trazos: el núcleo de dinero/aforo por nombre de clase, y **todo
  controlador de API `Order*`/`Payment*`/`Checkout*`/`Quote*`/`Availability*`/`Cart*`**.
  ⚠️ `Cart` entró en 4.0b·6 con este criterio: **un controlador de API que DECIDE sobre aforo entra
  en el gate aunque no escriba nada**, igual que su hermano `Availability*`.
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

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, 2026-08-15, owner).
Toda la gestión del cliente vivirá dentro del cajón: entrar y darse de alta, sus entradas y reservas, y
las gestiones de cuenta. Hoy está repartido en tres sitios —el cajón, el modal de auth de la cabecera y
las páginas `/mi-cuenta/…`— y el destino es UNO.
- **El orden es dependencia, no preferencia**: **4.7** (retirar el motor Livewire del cajón) →
  **Turnstile** (necesita claves y hostname de Cloudflare, los pone el owner) → **área de cliente**.
  El modal de la cabecera no se puede retirar antes de Turnstile porque es el único que monta el widget
  y el alta del cajón **delega en él** cuando el anti-bot está activo.
- ⚠️ **Lo que obliga a NO hacer desde hoy**: `machine.js` modela once pasos NUMERADOS de un embudo con
  sus transiciones. Un área de cliente no es un embudo —son zonas a las que se entra desde fuera—, así
  que **no se puede estrechar más la máquina** ni añadir supuestos de «siempre se viene del paso
  anterior». Rediseñar los estados es el primer trabajo de esa fase.
- **El servidor ya está** (medido contra `openapi/v1.yaml`): `/auth/*`, `/me`, `/me/orders`,
  `/me/reservations`, `/me/reservation-eligibility` y el post-form por firma existen y están probados
  desde Fase 3. El área de cliente es cliente nuevo de contratos ya pagados: hay que pintar, no abrir
  dominio.

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

**LA TRANSCRIPCIÓN ESTÁ COMPLETA: los once pasos existen en los dos motores** (4.0a–4.0c y 4.1 de
cimientos, 4.2 → 4.5 el embudo, 4.6 los tres desenlaces). Lo que falta NO es marcado.

⚠️ **HASTA DÓNDE LLEGA HOY EL MOTOR SPA, dicho sin optimismo**: con `sidebar.engine = spa` el cajón
abre con su armazón (velo, banda con «Volver», zona scrollable y pie), pide catálogo, recorre
producto → día → hora → cantidad → complementos → **añadir al carrito → carrito con su total**, y con
las reservas pausadas **sustituye el flujo entero por el aviso de mantenimiento**, como la web. Al
pulsar «Ir a pagar» **pregunta quién eres y si puedes reservar** (4.4a·1) y avisa cuando el servidor va
a decir que no —tope de pendientes, frecuencia— o pinta el cartel si las reservas se acaban de pausar.
Si eres invitado, **te lleva a la pantalla de identificación y puedes ENTRAR o CREAR CUENTA desde el
propio cajón** (4.4a·2 y 4.4b·1), con los mismos textos, el mismo limitador y las mismas defensas
anti-bot que la web. Y desde 4.5 **paga**: la pantalla de pago con su banda de desglose, «Pagar con
tarjeta» que crea la reserva firme —admitir, crear con su hold y abrir el cobro, en una sola petición—
y el auto-POST firmado hacia Redsys. **El cajón SPA recorre el embudo entero.** Y desde 4.6·1 **también
VUELVE**: quien paga bien aterriza en la pantalla de reserva creada, con su resumen pedido al servidor
—líneas, respuestas del pack, desglose de la señal, aviso de post-form y enlace de registro—, su
confeti y su «hacer otra reserva». Y desde 4.6·2 vuelve **de los tres desenlaces**: con la tarjeta
rechazada enseña el motivo concreto y **reintenta** —reabriendo el cobro sobre el mismo pedido, sin
consumir aforo nuevo—, y con un terminal que vuelve sin datos firmados **sondea cada 5 s** hasta que la
notificación de la pasarela confirma o la reserva caduca. La cesta **sí sobrevive a la recarga** desde
4.3·4, sin `event_data` y con su dueño dentro.
⚠️ **El flag NO se activa en producción y NO se despliega todavía**, pero el motivo ha CAMBIADO: ya no
falta pantalla, y el extremo a extremo **ya se hizo** (`#59`, con los arreglos que destapó). Lo que
queda antes de activarlo: el **widget de Turnstile** (4.4b·2, necesita claves de Cloudflare) y los tres
caminos que el e2e dejó declarados sin cubrir —el terminal *data-less* con notificación S2S (necesita
túnel), el 3DS con challenge y el móvil real—.
Su default es `livewire` y ese es el motor que vende. Sirve para comparar los dos motores en vivo
(`CE-1`).

✅ **La precondición del paso de PAGO está CERRADA** (4.5·1, `DECISIONES #54`): una línea de PACK
restaurada vuelve sin sus respuestas —y seguirá volviendo así, es `#38(d)`—, pero ahora **el carrito
las vuelve a pedir** antes de dejar avanzar. Medido: el presupuesto tarificaba esa línea sin avisar y
solo `POST /orders` la rechazaba, así que el fallo aparecía en el botón de pagar. ⚠️ La regla que deja:
**el cliente ENUMERA qué falta, el servidor DECIDE si vale** — copiar el saneo sería `#38(f)` otra vez.

⚠️ **Residual de la pausa, ya ENCOGIDO**: el estado se relee al cargar la página, **en cada apertura
del cajón** y —desde 4.4a·1— **al pulsar «Ir a pagar»**, que es el único punto donde vender de más
tendría consecuencias. Lo que sigue abierto es lo demás: un cajón ABIERTO y quieto en el catálogo o en
el calendario no se entera del interruptor hasta que alguien lo cierre, lo reabra o intente pagar.
Livewire sí, porque reevalúa su guarda en cada render. ✅ **Lo del 409 `reservations_paused` ya está
cerrado** (4.5·2): confirmar el pedido con las reservas pausadas relee el estado en vez de componer un
aviso, que es lo que el contrato pedía.

⚠️ **Lo que sigue YA NO es transcribir pasos.** Los once están, cada uno con su paridad cerrada. Lo
que queda es de otra naturaleza —un widget que necesita claves de terceros, una validación que necesita
navegador y una retirada— y por eso el orden de abajo cambia respecto al de toda la fase.

1. **4.4b·2 — el widget de Turnstile**, lo único que le falta al ALTA. Lo que hay que saber:
   · ⚠️ **Se aplazó porque NO SE PUEDE VERIFICAR sin claves de Cloudflare y un navegador** (`#53(a)`),
     no por tamaño. Cuando se haga, hace falta un entorno con claves de prueba: sin verificación
     empírica no puede marcarse ✅.
   · **Hoy hay una GUARDA, no una nota**: `GET /config` publica `turnstile_site_key` **solo si el
     anti-bot está activo de verdad** (las dos claves), y el cajón lo lee con
     `signupRequiresCaptcha()`. Con captcha, la pestaña «Crear cuenta» **delega en el modal de auth de
     Livewire**, que sí monta el widget. Hay caso de punta a punta con los tres estados.
   · ⚠️ **El Blade documenta el fallo que ya costó una vez**: un `<script>` plano inyectado por un
     morph de Livewire **no lo ejecuta el navegador**, así que api.js nunca cargaba, el widget no se
     dibujaba y el token llegaba vacío → «no eres un robot» sin correo ni log. En Vue el modo de fallo
     es otro, pero el síntoma sería el mismo.
   · **Lo que hay que mirar antes**: la CSP del sitio (¿permite `challenges.cloudflare.com`?), y que el
     token viaje en `turnstile_token` —el campo ya existe en el contrato y acepta la cadena vacía—.
   · **El presupuesto del bundle**: 150 KiB de techo, con **4,15 KiB libres** (145,85 medidos). Debería
     bastar: el widget es un contenedor y un script EXTERNO, que no viaja en el bundle. Ver el aviso de
     unidades de `SidebarBundleBudgetTest` antes de restar — Vite imprime en base 1000 y el test mide en
     base 1024, y confundirlos encoge el margen aparente.
2. ✅ **El EXTREMO A EXTREMO ya se ha hecho** (2026-08-14, `DECISIONES #59`) con navegador headless y la
   pasarela REAL, en los dos motores. ⚠️ **Y encontró que el motor SPA NO FUNCIONABA en producción**,
   con la fase entera transcrita y el gate en verde: el desenlace del pago no llegaba al store (quien
   volvía de pagar veía el catálogo), el motor no montaba con el cajón nacido abierto y el catálogo no
   enseñaba ni un producto. Los tres arreglados y verificados. El guion y las trampas de la pasarela
   están en `docs/VERIFICACION-E2E-CAJON.md`. **Lo que sigue pendiente de navegador**: el bloque B (el
   terminal *data-less* con notificación S2S, que necesita túnel), el 3DS con challenge y el móvil real.
   Lo que hay que saber:
   · **Es la última pieza de verificación que la fase declaró y que NADIE ha hecho todavía**: recorrer
     la compra con los DOS motores contra Redsys en sandbox y **comparar el pedido en BD**. Todo lo
     demás está cubierto por paridades, pero ninguna de ellas ejecuta un navegador ni la pasarela real.
   · **Lo que solo se puede ver ahí**: el auto-envío del paso 9 (`onMounted` no corre en SSR, así que el
     gate compara el marcado sin dispararlo), el confeti del paso 6, el sondeo del 11 vivo, y que el
     puente hacia Livewire sigue hablando (`account-context`, los modales de auth).
   · ⚠️ **Dos datos que ahorran una hora, medidos al escribir el guion**: el sandbox de Redsys **YA está
     configurado** en dev (FUC 999008881, terminal 001, clave puesta, entorno test) y los dos packs de
     cumpleaños traen señal, campos de evento, post-form y complementos — comprar un pack ejercita todo
     el resumen de una vez. Lo único que falta es `redsys_merchant_url`, que es la notificación S2S y
     necesita túnel.
   · ⚠️ **El flag NO es editable desde el panel**, al contrario de lo que dice el spec §4.9: hoy es solo
     una fila de `settings`. El guion trae los dos comandos.
   · ✅ **La accesibilidad de §6 ya está cerrada salvo un punto** (`DECISIONES #58`): `no-scroll` tiene
     dueño único con guarda ejecutable, y los `role`/`aria-*` los compara el diff. ⚠️ **Lo único que
     sigue abierto es el foco al cambiar de paso, que NINGUNO de los dos motores hace** — hueco
     heredado, ficha en `DEUDA.md`.
3. **4.7 — la retirada, EN CURSO** (`DECISIONES #60`, `#61`, `#63`). **·1 el manifiesto congelado ✅** ·
   **·2a el inventario deja de crecer ✅** · **·2b·1 el contador dice la verdad ✅** (2026-08-15) ·
   **·2b·2 re-apuntar lo que sobrevive** · **·2b·3 borrar componente + vistas + puente** ·
   **·3 retirar el flag**.
   · ⚠️ **El contador MEDÍA MENOS DE LA MITAD DE LAS FORMAS y se arregló** (`#63`): declaraba **25**
     dependientes y el acoplamiento real eran **32**. No veía a los cuatro que conducen con
     `Livewire::actingAs($u)->test(…)` —21 llamadas—, al que lee `Purchase::MAX_LINES_PER_CART` ni a los
     dos que dependen de `purchase.blade.php`. **Llegar a 0 con siete invisibles no habría levantado el
     bloqueo: habría roto la suite al borrar.** Hoy el escaneo tokeniza (descarta comentarios: sin eso
     entran dos falsos positivos que solo lo mencionan como historia) y mira TRES formas —conduce ·
     nombra la clase · depende de sus vistas—, con guarda **una por forma**.
   · ▶ **EMPIEZA AQUÍ: el marcador es `PurchaseRetirementTest`.** Los dependientes solo pueden encoger
     y van **28** (de 32 corregidos). Cuando llegue a 0, `Purchase.php` se borra sin pensar. El trabajo
     es reclasificar uno a uno, **con evidencia**, no de golpe:
     · **Si el fichero se re-apunta** (su sujeto es el dominio o el servidor): condúcelo por `/api/v1`
       y quítalo de `DEPENDENTS`. Patrón hecho: `SlotOfferTest` → `POST availability/{p}/times`.
     · **Si muere con el componente**: ANTES hay que enseñar dónde vive su cobertura. Patrón hecho y
       medido: los dos casos de `AddonDependencyTest` mueren sin pérdida porque `CatalogAddonsTest`
       cubre la poda de dependencias **mejor** (la cadena entera). ⚠️ Eso se comprueba, no se supone.
     · ▶ **ANTES de tocar ninguna paridad, decide cómo se alimenta el diff de árbol** (medido el
       2026-08-15, detalle en `00-REFACTOR.md`): `SidebarDomContractTest` le pasa a Vue props que salen
       **todas del componente Livewire** (13 helpers que leen `viewData()`), así que **sin componente no
       hay props** y el test no sobrevive tal cual. Las dos salidas: (A) congelar también las props
       —foto contra foto, no ve un cambio del servidor— o **(B) alimentar a Vue con las respuestas
       REALES de la API**, que además cierra el punto ciego por el que existen `SidebarAddonsParityTest`
       y `SidebarCalendarParityTest`. **Recomendación: (B), y primero**, porque decide cuáles de las
       ocho paridades restantes siguen teniendo pregunta que responder.
     · **De las TRECE paridades, cinco ya no tocan Livewire** y sobreviven intactas (login, registro,
       dinero, textos, campos pendientes: comparan contra el diccionario y el contrato). Las que quedan
       son ocho más el diff de árbol.
     · **Las nueve PARIDADES** son el grupo grande y el último: o comparan contra el manifiesto
       congelado (`tests/Fixtures/sidebar-dom-manifest.json`, ya hecho para el árbol) o contra el
       contrato del servidor (`lang/`, `openapi/v1.yaml`), que es contra quien de verdad comparan sus
       textos e importes. ⚠️ Y varias dejan de tener sentido al quedar UN solo consumidor: la paridad de
       `AvailabilityTest` existía para probar que dos implementaciones coincidían, y se retiró en ·2b·1.
       Eso no es perder cobertura; es que la pregunta desaparece.
     · ⚠️ **La regla que dejó ·2b·1 al retirar la primera paridad**: separa **lo que comparaba** de **lo
       que además afirmaba**. Lo primero se va con el segundo motor; lo segundo hay que buscarlo en el
       fichero ANTES de borrar, y si no está, el caso se re-apunta en vez de morir. Así sobrevivió el
       único caso que ejerce la suma de una cesta mixta (`QuoteTest`) y así murieron sin hueco los de
       `AvailabilityTest`, cuyos controles ya estaban fijados por dos casos que existían.
     · ⚠️ **Y la regla que dejó ·2b·2** (`#65`): **al re-apuntar un caso a otra superficie hay que
       VOLVER A MUTARLO.** Verde antes y verde después no demuestra que siga probando lo mismo — el
       caso del idioma de la pasarela pasó a ser inerte porque `ApiLocale` ya resolvía por su cuenta el
       valor que el caso creía estar verificando. Lo cazó la mutación, no la lectura.
     · **Y el otro patrón de ·2b·2**: el caso que prueba la VISTA no se borra, **se muda al fichero que
       muere con el componente** (`PurchasePanelTest`). Así ·2b·3 borra ficheros enteros en vez de
       operar dentro de ficheros que sobreviven, que es mucho más fácil de revisar.
   · **Clasificación ya MEDIDA de cuatro ficheros** (no la repitas; el detalle en `00-REFACTOR.md`):
     `Ui/SpinnerTest` **muere** (el velo del cajón SPA lo cubre el caso del armazón de
     `SidebarDomContractTest`, anclado en `.jj-loading` con hermanos) · `Auth/DuplicateEmailEdgeCaseTest`
     **muere** (su caso prueba `Purchase::onSwitchToLoginTab`, y ese escape **no es alcanzable embebido
     en ninguno de los dos motores**: el paso 5 deja de renderizarse en cuanto se salta al 7) ·
     `Maintenance/ReservationPauseGuardTest` **muere** (sus tres casos son guards de SERVIDOR y
     `Api/V1/OrdersTest` ya los cubre en la superficie que sobrevive) ·
     `Sales/CatalogVisibilityAndCartPruneTest` (la poda de la cesta en `mount`, que en la SPA es
     `cart.js` con sus casos en `cart.test.js`) — este último, pendiente de comprobar caso a caso.
   · ⚠️ **Lo que hace grande a 4.7·2, medido**: **32 ficheros** de acoplamiento real (hoy **28**), en
     tres familias — los que mueren con él (prueban SU interfaz), los que solo lo usan como conductor
     de dominio y deben re-apuntarse a la API, y las nueve paridades. Borrar el fichero sin
     reclasificarlos es pérdida neta de cobertura.
   · ⚠️ **Y arrastra el modo `embedded` de la auth**: `purchase.blade.php` es el ÚNICO sitio que monta
     `<livewire:auth.login|register :embedded="true">`. Al borrarlo, ese modo se queda sin usuario —y
     con él el evento `purchase:switch-to-login`—. Decidir si se retira en ·2b·3 o se deja para Fase 5
     es parte del tramo, no un descubrimiento del final.
   · ✅ **El manifiesto ya está congelado** (30 entradas, 696 nodos): el contrato visual sobrevive a la
     retirada. Se regenera con `MANIFEST_REFRESH=1` y hay que decirlo en el commit.
     ⚠️ **Y CADUCABA A LAS 24 H hasta el 2026-08-15** (`DECISIONES #64`): dos entradas son el calendario
     y su rejilla depende de HOY. Ahora `SidebarDomContractTest` congela el reloj en `setUp()`
     (`FROZEN_NOW = 2026-08-12`); si cambias esa fecha, hay que regenerar el manifiesto. **No lo
     regeneres sin mirar el diff**: al hacerlo bien cambian 2 entradas de 30, y eso es lo que demuestra
     que no se ha tapado nada más.
   · ⚠️ **NO esperes a «curtir el motor en producción»: esa condición se planteó y se RETIRÓ el
     2026-08-15 por vacía** (`DECISIONES #62`). Este repo es el PRODUCTO —`CLAUDE.md`, primera
     línea—, **no hay canal de despliegue** (ni `.github` ni script; el de `INSTALACION-CLIENTE.md` §1
     sigue `[DECISION-PENDIENTE]`) y las menciones a «producción» de este documento son del cliente
     ORIGEN, que vive en otro repo. **No hay tráfico con el que curtir nada.** Lo que aquel margen
     protegía era tener interruptor de vuelta, y el interruptor lo mata el propio 4.7·2b: esperar no
     lo conserva, solo aplaza.
   · ⚠️ **El techo del bundle debería BAJAR aquí**: se va el motor Livewire, y con él los dos pasos que
     hoy conviven.
4. **Lo que queda ABIERTO como decisión de producto, no como tarea**: la escala tipográfica canónica
   (medida: movería el 52-55% de los tamaños) y el formato de importe quemado en español, los dos en
   `DEUDA.md`.

- **4.3·1 — el armazón y los cimientos de texto** (2026-08-14, `DECISIONES #47`). No transcribe ningún
  paso: cierra lo que 4.2 dejó abierto **sin que el gate pudiera verlo**. Cinco cosas que condicionan
  todo lo que viene:
  · ⚠️ **Los nueve casos del gate salían verdes con el motor SPA sin emitir NADA del armazón** —ni velo
    de carga, ni banda, ni zona scrollable—, porque **todos anclan DENTRO** (`catalog-acc`,
    `wiz__title`). Ahora hay un caso anclado en `.jj-loading` **con hermanos**: es la única forma de
    comparar el ORDEN entre ellos, del que dependen selectores de adyacencia.
  · ⚠️ **La banda de progreso estaba escrita, verde en el gate y no se pintaba**: `Sidebar.vue` le
    pasaba `progress: null`. El cajón vivo iba sin «Volver». Es `#46(a)` otra vez.
  · ⚠️ **`#sidecart-spa` no tenía ni una regla CSS** y partía la cadena flex del panel (Vue monta
    DENTRO del hueco). Ningún diff de árbol puede verlo: lleva guarda propia en `SidebarTokenBudgetTest`.
  · **El dinero y los textos tienen ahora módulo único con paridad**: `money.js` espeja `number_format`
    —`toFixed` no agrupa e `Intl.NumberFormat('es-ES')` **no agrupa entre 1.000 y 9.999**, así que las
    dos salidas obvias fallan— e `i18n.js` lee por CAMINO (cuatro claves del payload son subarrays y se
    pintaban **vacías**), sustituye todos los marcadores y resuelve el plural de Laravel (en francés el
    CERO es singular).
  · **`modeOf(8)` decía `result` y el servidor dice `cart`**: la clase se pinta FUERA del cajón, así que
    ningún árbol la alcanzaba. Se fija recorriendo el mapa entero, que es como apareció.

- **4.3·2 — el pie y la cesta en memoria** (2026-08-14, `DECISIONES #48`). Cinco cosas que condicionan
  lo que viene:
  · **El corte no fue por pantalla sino por DEPENDENCIA**: el pie no se podía separar de la cesta,
    porque el CTA del paso 3 es «Añadir al carrito» y `disabled` **es un atributo que el diff compara**
    — inactivo ponía el gate en rojo, activo sin cesta era un botón mudo.
  · **El séptimo hueco de API era otro**: el endpoint de complementos construía un `CartQuote` completo
    y **tiraba sus totales**. Publica `line.total_cents` desde 4.3·2, y por eso el cliente **no suma**
    `subtotal_cents` + `addons_total_cents` (dos recorridos distintos del servidor).
  · ⚠️ **Lo que el cliente NO compone**: el desglose viene ya partido (`deposit_cents` +
    `gate_remainder_cents`), porque restarlo sería reimplementar la Opción A de #225. La ÚNICA resta
    permitida es `park = total − online` en la cesta: dos agregados de la misma fuente, y es lo que
    hace el servidor.
  · ⚠️ **Dos rótulos que el diff da por buenos**: «Pagas ahora (señal)» en el paso 3 y «Pagas ahora»
    NEUTRO en la cesta (#225). El normalizador descarta el texto; los fija `SidebarCartParityTest`.
  · **La cesta ya viaja en la consulta de horas** (`AFORO-02`): iba `items: []` desde 4.2.

- **4.3·3 — el aviso de reservas en pausa** (2026-08-14, `DECISIONES #49`). Cierra la divergencia que
  4.3·2 dejó declarada. Cinco cosas que condicionan lo que viene:
  · **La pausa apaga CUATRO bloques**, no solo el contenido: la banda, el pie y la banda de desglose
    del pago. ⚠️ Y la guarda va en la VISTA: el servidor **sigue** componiendo `bookingProgress()` y
    `footer()` no nulos durante la pausa, así que anularlos en los módulos rompería sus paridades.
  · ⚠️ **Tapa SEIS pasos** (`[1,2,3,4,5,8]`) y no se puede derivar: los de RESULTADO rinden normales
    porque son acciones ya iniciadas.
  · ⚠️ **El título y el mensaje son ajustes del PANEL por idioma**, no literales de i18n — y sin
    override coinciden EXACTAMENTE, así que el fallo es invisible en desarrollo.
  · **El estado se relee en cada apertura del cajón**: el motor se monta una sola vez por carga de
    página, así que leerlo solo al montar habría sido el snapshot que el endpoint existe para evitar.
  · ⚠️ **Lo que el diff de árbol NO ve de este bloque**: `href`, `target` y `rel` no son atributos de
    contrato, así que el enlace de WhatsApp y el de `/contacto` producen árboles **idénticos**. La
    paridad de enlaces es lo único que distingue mandar al WhatsApp de mandar a contacto.

- **4.3·4 — la cesta sobrevive a la recarga** (2026-08-14, `DECISIONES #50`). Cinco cosas que
  condicionan lo que viene:
  · ⚠️ **La purga por titular tiene CINCO casillas y una NO existe en el servidor**: (X → anónimo) →
    purgar. En sesión el logout vacía cesta y marcador a la vez; `localStorage` no tiene `invalidate`.
    Es la fuga que introduce la persistencia. Y **una cesta de invitado SOBREVIVE al login**: es el
    flujo principal, no un descuido.
  · **La identidad sale del servidor por DOS canales**: `userId` en el `data-boot` (el logout es una
    navegación completa) y `GET /me` al abrir el cajón y al oír `logged-in`. ⚠️ Un fallo de red **no**
    es un logout: solo el 401.
  · ⚠️ **El saneador espeja `CartPayload`, no `Cart::sanitize()`**: descarta la línea mala en vez de
    corregirla —`qty: 0` → 1 sería una compra que nadie pidió— y valida el FORMATO, porque una sola
    línea corrupta hace que los tres endpoints de cesta devuelvan 422 y dejen el cajón inservible.
  · ⚠️ **Reconciliar y re-presupuestar son UNA operación**: podar desplaza los índices y las filas se
    emparejan por el `index` del presupuesto. Se poda por dos criterios: el hueco de `index` **y**
    `unit_price_cents: null`.
  · **`npm run test:js` solo alcanza UN nivel de carpeta** (el patrón lo expande `sh`): un test en una
    subcarpeta no se ejecuta nunca y la suite dice «pass». Ya hay guarda en `PrePushGateTest`.

- **4.4a·1 — el CTA de pagar pregunta quién eres y si puedes reservar** (2026-08-14, `DECISIONES #51`).
  Cinco cosas que condicionan lo que viene:
  · ⚠️ **Se transcribe la DECISIÓN, no la navegación.** De las CINCO salidas de `checkout()` —medidas
    una a una— solo dos tienen pantalla hoy: el aviso de admisión denegada y el cartel de pausa, los
    dos en el paso 4. Las otras tres van a los pasos 5 y 8, y navegar a un paso sin transcribir deja
    el cajón **en blanco**, que es peor que un CTA mudo. El destino se decide igualmente y se compara
    con el de Livewire, así que 4.4a·2 y 4.5 **cablean, no vuelven a decidir**.
  · ⚠️ **Son DOS peticiones en paralelo y la segunda no es la obvia**: `GET /me` no es para saludar —es
    el único momento en que el cajón puede enterarse de que la sesión cambió en OTRA pestaña, y con la
    cesta en `localStorage` eso es lo que sostiene la defensa anti-cesta-cruzada—. La identidad se
    aplica **antes** del veredicto: si el titular cambió, no hay compra que continuar.
  · ⚠️ **La PAUSA no se pinta como error de carrito, y está MEDIDO**: Livewire escribe
    `errors.reservations_paused` en su bag y **el HTML no lo contiene** —`showPausedNotice()` tapa el
    paso entero—. Pintarlo sería enseñar un texto que no existe en ninguna instalación, y el diff de
    árbol lo daría por bueno porque descarta los nodos de texto.
  · **La SECUENCIA vive en el módulo plano, no en el `.vue`** (`CE-6`), con `api` y `applyIdentity`
    inyectados como `cart.js` recibe el almacén. Un árbol no dice a quién se preguntó ni en qué orden:
    dentro del componente esa lógica no tendría red, que es el fallo que 4.3·1 ya pagó.
  · **El texto de los avisos solo lo compara `SidebarAdmissionParityTest`**, palabra por palabra y en
    los tres idiomas, con las respuestas REALES de la API. El `:max` del tope sale de dos sitios
    distintos —el `context` del veredicto en Livewire y `max_pending_orders` en el sobre—, así que un
    desajuste entre ellos no se ve en ningún otro lado.

- **4.4a·2 — la pantalla de identificación** (2026-08-14, `DECISIONES #52`). Cinco cosas que
  condicionan lo que viene:
  · ⚠️ **El árbol del paso 5 son 31 nodos, y llegar por el camino equivocado enseña 9.** Con
    `->set('authMode', …)` Livewire deja el hijo `<div wire:name="auth.login"></div>` **VACÍO** —los
    componentes hijos se hidratan en una petición posterior—, así que el diff compararía armazón contra
    armazón y un motor SPA **sin formulario** pasaría en verde. Se llega **pulsando la pestaña**, y hay
    un caso que fija ese hecho para que nadie lo simplifique de vuelta.
  · ⚠️ **Los dos motores decían cosas DISTINTAS para el mismo rechazo** (`auth.failed` vs
    `invalid_credentials`, y lo mismo con el limitador). El cajón ramifica sobre el **código** del sobre
    y pinta el **literal del diccionario**: pintar el `message` habría cambiado la copia del cajón en
    las tres lenguas sin que ningún gate lo dijera. La validación sí se pinta tal cual —los dos motores
    usan las mismas reglas y sus textos ya coinciden, comprobado—.
  · **El reparto de los avisos es contrato** (L-02): el del limitador al banner `_global` y el de
    credenciales **bajo el campo email**. Juntarlos mezcla un mensaje genérico —que no revela si el
    correo existe— con uno que sí dice algo del sistema.
  · **El montaje lleva dos grupos nuevos y PODADOS** (`account.login` + el `cta` de `register`, y
    `auth`): 538 bytes medidos en vivo. El grupo `account` entero son 9,6 kB **en cada página pública**,
    y una clave que falte se pinta VACÍA sin que nada avise — por eso hay guarda de las dos cosas.
  · **Al entrar pasan TRES cosas**: se avisa a Livewire (`logged-in`, que es lo que hace repintar
    `account-context` fuera del cajón), se aplica la identidad con la respuesta del **propio login**
    —trae el perfil con la forma de `GET /me`— y se continúa el checkout. Verificado en vivo que con el
    motor SPA la página **sigue cargando Livewire**: sin eso, el puente no tendría con quién hablar.

- **4.4b·1 — el alta desde el cajón** (2026-08-14, `DECISIONES #53`). Cinco cosas que condicionan lo
  que viene:
  · ⚠️ **El primer cliente real de un endpoint encuentra lo que ningún test suyo encontró.** Tres bugs
    de SERVIDOR, los tres con su regresión: `/config` anunciaba el anti-bot con **media configuración**
    —clave pública sin secreta, estado en que la web no pinta el widget y el servidor no verifica—; el
    **señuelo VACÍO**, que es lo que manda todo cliente legítimo, provocaba un **422 sobre un campo que
    el usuario no ve** (`ConvertEmptyStringsToNull` + `sometimes|string`); y las **dos puertas del alta
    decían cosas distintas** porque el componente declara `validationAttributes()`/`messages()` y el
    controlador no.
  · ⚠️ **El 201 del alta no dice si hubo cuenta, a propósito**: si lo dijera, un bot distinguiría un
    alta buena de un señuelo de un vistazo. El cajón pregunta `GET /me` después — con sesión sigue la
    compra, sin ella va a «revisa tu correo». Un fallo de red al preguntar **no** cuenta como sesión.
  · ⚠️ **Turnstile NO está montado y hay una guarda para que eso no muerda**: con el anti-bot activo,
    la pestaña de alta delega en el modal de Livewire. Sin ella, el registro del cajón habría rechazado
    a **todo el mundo** con «no eres un robot», sin correo y sin log.
  · **Tres nodos invisibles que solo vigila el diff de árbol**: el honeypot (`.hp`), la fila
    `.form__row` de email+teléfono y el `<small class="form__hint">` de la contraseña. Y el **banner**
    de errores se compara con el formulario VACÍO: con un solo campo en rojo, un `<li>` de más o de
    menos no se vería.
  · **Los literales del alta se pintan tal cual, al revés que en el login**: aquí el servidor publica
    en `fields.email` los mismos que pinta el Blade; en el login tiene un `message` propio que no
    coincide con `auth.failed`. Las dos conductas son correctas y las dos tienen su caso.

- **§6 · el extremo a extremo con navegador** (2026-08-14, `DECISIONES #59`). ⚠️ **La lección más cara
  de la fase, y hay que leerla entera antes de dar nada por hecho:**
  · ⚠️ **VERDE NO ES FUNCIONA.** La Fase 4 estaba «transcrita», con once paridades, mutaciones y
    centinelas de bundle, y **el motor SPA no vendía**: el desenlace del pago no llegaba al store —el
    store COPIA la máquina, no la observa, y `index.js` la movía después de arrancarlo—, el motor no
    montaba cuando el cajón nace abierto, y el catálogo no enseñaba ni un producto. Tres fallos de
    CABLEADO, ninguno de marcado. **Ningún test podía verlos porque ninguno ejecuta el montaje real.**
  · **La red que faltaba era barata**: `store.test.js` reproduce la secuencia de `index.js` con Pinia en
    `node --test`. Se podía tener desde 4.1. Si algo se monta, hay que probar el montaje.
  · ⚠️ **Un `:class` de Alpine esconde una clase del diff de árbol**, y esta vez tapaba el paso 1 entero.
    El arreglo bueno no es parchear el cliente: es sacar la clase del binding para que el gate la vea.
  · **Y arreglar eso movió otro gate**: `SidebarTokenBudgetTest` deduce el CSS del sidebar leyendo los
    `class="…"` del Blade, así que una clase de ESTADO compartida (`is-open`) le ensancha el escaneo.
  · ✅ Lo que sí quedó demostrado: embudo entero en los dos motores contra la pasarela real, cobro de la
    **señal** (30 € de 165 €), y **pedidos idénticos en BD**.

- **§6 · el bloqueo de scroll tiene un solo dueño** (2026-08-14, `DECISIONES #58`). Cierra el último
  ítem del plan de verificación de la fase. Tres cosas que condicionan lo que viene:
  · ⚠️ **Eran SEIS escritores de `body.no-scroll` y el fallo se alcanza con dos clics**: con el cajón
    abierto, cerrar el modal de auth —al que se llega desde su propio bloque de cuenta— desbloqueaba el
    scroll con el panel delante. Ahora hay cerrojo con llaves (`resources/js/ui/scroll-lock.js`) y
    guarda ejecutable. **Nadie más puede tocar esa clase.**
  · ⚠️ **Ampliar el contrato de árbol NO basta**: `aria-current` entró en la lista de atributos y por
    mutación resultó INERTE —el caso del calendario no elegía día, así que ningún motor lo emitía—. Un
    atributo solo lo cubre el caso que lo hace aparecer.
  · ⚠️ **`aria-expanded` no puede compararse en el diff** (binding de Alpine descartado como andamiaje
    vs. atributo renderizado por el SSR de Vue), y al darle caso propio apareció que el cajón SPA **no
    anunciaba** si el desglose de la señal estaba abierto. Arreglado.

- **4.6·2 — los otros dos desenlaces: denegado y verificando** (2026-08-14, `DECISIONES #57`). Cinco
  cosas que condicionan lo que viene:
  · ⚠️ **LA MÁQUINA DE ESTADOS LLEVABA DESDE 4.1 CON UNA TRANSICIÓN INVENTADA.**
    `TRANSITIONS[DECLINED]` decía `[CATALOG, PAY]` y las dos mitades estaban mal, medido contra
    `retryPayment()`: el reintento sale **DIRECTO a la pasarela** (paso 9) —reabre el cobro sobre un
    pedido que ya existe, no hay nada que volver a confirmar— y faltaba la salida a IDENTIFICARSE
    (`$user ? 1 : 5`). Sin `DECLINED → REDIRECTING`, el reintento habría compuesto su formulario firmado
    y el cajón **se habría quedado quieto**, porque `go()` rechaza en silencio. **Lección para 4.7: lo
    que la máquina afirma de un paso NO transcrito es una suposición hasta que alguien lo mide.**
  · **El motivo del rechazo no necesita tabla**: `declined_reason` ES la clave de
    `tickets.payment_failed.reasons.*`, y ese grupo ya viaja entero en el montaje. Lo que sí hace falta
    es la caída a `default`, porque `i18n.js` pinta VACÍO una clave que no existe.
  · ⚠️ **El sondeo solo mira `paid` y `expired`**: un intento `failed` con el pedido todavía `pending`
    **no mueve nada**, porque la notificación de la pasarela puede estar en vuelo. Ampliar esa
    acotación diría «no has pagado» a quien sí pagó.
  · ⚠️ **El centinela obvio del bundle NO discriminaba**: `/payment-status` lo piden los DOS pasos, así
    que desconectar el bucle del 11 dejaba el gate en verde con el cliente mirando «verificando» para
    siempre. Se midió que `setInterval` aparece una sola vez en el chunk y desaparece con él.
  · ⚠️ **Deuda de PRODUCTO destapada, no creada**: un reintento denegado —pausa, frecuencia o 502— deja
    el botón **mudo** en los DOS motores; `errors.cart` no se pinta en el paso 10. El cajón lo
    transcribe fiel, que es lo que pide la paridad, y la fila está en `DEUDA.md`.

- **4.6·1 — la vuelta de la pasarela pinta la reserva creada** (2026-08-14, `DECISIONES #56`). Cinco
  cosas que condicionan lo que viene:
  · ⚠️ **UN TEST DE CADENA VOLVIÓ A PASAR SIN PROBAR LA CADENA.** El caso que afirmaba «cada respuesta
    del pack cae bajo SU reserva» estaba en verde **con el cliente emparejando por POSICIÓN**: hoy
    `GET orders/{code}/event-data` devuelve las reservas en el mismo orden que las líneas, así que llave
    y posición coinciden por casualidad. El caso bueno **le da la vuelta al sobre** y exige el mismo
    resumen. Y la PRIMERA mutación tampoco valía: `Object.values(mapa)[i]` pasa, porque en JS las claves
    que parecen enteros se ordenan ascendentemente y el mapa se recoloca solo.
  · ⚠️ **El desenlace MANDA sobre la cesta restaurada, y el orden natural de la SPA era el contrario.**
    `Purchase::mount()` pone el paso 4 si hay cesta y **después** deja que la vuelta lo pise; el motor
    SPA restauraba al final. Con la cesta en `localStorage`, otra pestaña puede haberla llenado mientras
    se pagaba en ésta. La precedencia se escribe en `machine.js` (`isOutcome()`), no en el `.vue`.
  · **La fila del resumen es COMPARTIDA** (`SummaryLine.vue`): los pasos 6 y 8 emiten el mismo árbol
    hasta el `<span>` sin clase del precio. La pregunta que decidió extraerla es «¿hay dos copias?»
    (4.0b·5), y tocarla deja en rojo los dos pasos a la vez — que es la prueba de que la red la cubre.
  · **`confirmation: null` es un estado LEGÍTIMO**: el Blade pinta la pantalla igual sin resumen —código
    del pedido, aviso del correo y CTA— y es lo que ve quien perdió la sesión entre la pasarela y la
    vuelta. Tratarlo como error sería enseñarle «ha fallado algo» a quien acaba de pagar.
  · **Dos DIVERGENCIAS declaradas, cada una con su caso**: la API acota el resumen a la fase `booking`
    (§4.4.6, lo del post-form no sale) y publica el estado EFECTIVO, así que un pedido pendiente con el
    hold vencido sale `expired` donde el Blade dice `pending` — y el cajón entonces **no pinta nota**.

- **4.5·2 — pagar y salir a la pasarela** (2026-08-14, `DECISIONES #55`). Cinco cosas que condicionan
  lo que viene:
  · ⚠️ **El diff de árbol NO puede verificar el paso 9, y se demostró por mutación**: `action`, `method`
    y los `name` de los campos **no son atributos de contrato**, así que renombrar los campos firmados
    —lo que rompe el cobro con SIS0042, con el pedido ya creado y el aforo retenido— **pasa el gate en
    VERDE**. Lo cubre `SidebarPayParityTest`, campo a campo contra la respuesta real.
  · **`payment.fields` es un mapa OPACO**: el cajón itera y emite sin conocer los nombres. Impide
    «normalizar» un valor que la firma cubre **y** deja el paso listo para el segundo driver de Fase 6.
  · ⚠️ **A partir del 201 el pedido EXISTE y retiene aforo.** Un formulario mal formado no se trata
    como «no ha pasado nada»: se avisa y **se conserva el código**. Y la cesta se vacía y se persiste
    vacía en ese momento, para que una recarga no la resucite.
  · ⚠️ **El paso 8 se parece al carrito lo justo para equivocarse**: `cart--summary`, sin botón de
    quitar, el precio en un `<span>` SIN clase y el pie de aviso sin «añadir otra reserva».
  · **Los doce motivos de rechazo se traducen desde el CÓDIGO**, no desde la clave —eso es lo que
    recibe un cliente de API—, y `SidebarPayParityTest` recorre el enum ENTERO del servidor: un motivo
    nuevo sin mapear lo nombra el test en vez de salir como aviso genérico en la pantalla de pagar.

### El MAPA del cajón SPA (para no buscarlo a ciegas)

`resources/js/sidebar/` — **la lógica vive en módulos PLANOS sin Vue** (`CE-6`), y los componentes solo
pintan. Esa separación es lo que hace que todo lo de abajo se pruebe con `node --test` y se compare
contra el servidor desde PHP ejecutándolo en Node.

| Módulo | De qué responde | Su paridad |
|---|---|---|
| `machine.js` | En qué paso está el cajón y a cuál puede ir. Los `STEPS` son los de Livewire | `SidebarProgressParityTest` (el mapa de «modo») |
| `api.js` | El cliente HTTP y sus cuatro trampas medidas (cookie, `Accept`, CSRF url-decodificado, reintento del 419) | — |
| `i18n.js` · `money.js` | Textos por CAMINO con plural de Laravel · importes que espejan `number_format` | `SidebarTextParityTest` · `SidebarMoneyParityTest` |
| `calendar.js` · `progress.js` | La rejilla del mes · la banda de fases y su contexto | `SidebarCalendarParityTest` · `SidebarProgressParityTest` |
| `cart.js` | Cesta: saneado, persistencia con su dueño, reconciliación y **qué respuestas faltan** | `SidebarCartParityTest` · `SidebarPendingFieldsParityTest` |
| `foot.js` | El pie de cada paso (CTA, importes, desglose) | `SidebarCartParityTest` |
| `paused.js` | El aviso de reservas en pausa y en qué pasos tapa | `SidebarPausedParityTest` |
| `admission.js` | El paso del carrito al pago: identidad + elegibilidad + destino | `SidebarAdmissionParityTest` |
| `login.js` · `register.js` | Identificarse y darse de alta desde el cajón | `SidebarLoginParityTest` · `SidebarRegisterParityTest` |
| `pay.js` | Crear el pedido y componer el formulario firmado de la pasarela | `SidebarPayParityTest` |
| `outcome.js` | La VUELTA entera: el resumen del paso 6, el motivo del rechazo del 10 con su reintento, y el sondeo del 11 | `SidebarOutcomeParityTest` |

Fuera de `sidebar/` hay un módulo compartido que el cajón también usa: **`resources/js/ui/scroll-lock.js`**
—el dueño ÚNICO de `body.no-scroll`, con llaves por superpuesto—. Lo vigila `ScrollLockOwnerTest`; nadie
más puede tocar esa clase (`DECISIONES #58`).

⚠️ **Y la regla que las tres últimas paridades enseñaron**: cuando algo NO es un atributo de contrato
del normalizador —`href`, `action`, `method`, los `name` de un formulario, el texto— **el diff de árbol
lo da por bueno**. Si transcribes algo de esa clase, necesita paridad propia; si no, pasará el gate en
verde estando roto. Está demostrado por mutación en el paso 9.

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
17 Filament Resources · **2715** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
