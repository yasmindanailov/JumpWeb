# [SPEC] API v1 (Fase 3)

> Estado: 🟦 **v2, reescrito tras revisión adversarial** (2026-08-13) · **SIN bloqueantes**:
> dependencias decididas (`#21`), árbol saneado (`#22`) y el anti-bot resuelto sin relajar nada
> (`#23`) · Listo para implementar por el corte de §9 · Última actualización: 2026-08-13 ·
> Decisión asociada: `DECISIONES #21` (dependencias) + «#N» al aprobarse el diseño.
> Antecedentes: `DECISIONES #3` (el sidebar se rehace como SPA contra la API) y `#4` (API-first).
> Qué cambió respecto a la v1 y por qué: **§8**.

## 1. Contexto y problema

**Medido el 2026-08-13** (los recuentos de la v1 eran erróneos; corregidos aquí — §8):
- **No existe API.** `composer.json` declara **6 paquetes** (más `php`) y ninguno es Sanctum; no
  hay fichero de rutas de API —`routes/api.php` (futuro)— ni `api:` en `withRouting()` de
  `bootstrap/app.php`. Las **37** declaraciones `Route::` de `routes/web.php` son server-rendered.
- **El sistema de reservas vive dentro de una clase de UI.** `app/Livewire/Tickets/Purchase.php`
  (2.049 líneas, **46** métodos públicos). La v1 afirmó que «solo cuatro son operaciones de
  servidor»: **falso**, y era la premisa de la que colgaba medio diseño. El carrito de hoy no es
  cliente puro, es **cliente con validación de servidor en cada paso**: `addToCart()` re-topa la
  cantidad contra el aforo y sanea `event_data`; `selectDate()`/`selectTime()` validan contra
  listas calculadas en servidor; `checkout()` aplica la pausa de reservas y los topes anti-abuso.
- **Reglas de servidor que NO están en el dominio, sino en la clase de UI** (hallazgo convergente
  de los tres revisores; es el problema central de esta fase):
  · `Purchase::blockedByReservationPause()` — pausa de reservas del panel (#218);
  · `Purchase::withinReservationLimits()` — `MAX_PENDING_PER_USER = 5` y
    `RESERVATIONS_PER_MINUTE = 3`, que cierran el hallazgo E del origen: *un usuario autenticado
    podría iterar `confirmReservation` y agotar el aforo del día sin pagar*.
  `OrderCreator` no contiene ninguna. Exponer `POST /orders` «delgado sobre `OrderCreator`»
  reabriría las dos. Es el mismo patrón que Fase 2 ya rescató con `MAX_LINES_PER_CART`.
- **La ida del pago ya está DUPLICADA hoy**: `Purchase::retryPayment()` y
  `RetryPaymentController` comparten casi línea a línea el UPDATE atómico de `expires_at`, el
  `STATUS_SUPERSEDED`, `nextGatewayOrder()` y el audit. La API sería la **tercera** copia.
- **El desenlace del pago está atado a la sesión web**: `RedsysReturnController` guarda el
  resultado en caché y redirige a la home con un token de un solo uso que `HomeController`
  **consume (`Cache::pull`) ANTES** de comprobar la sesión; el rechazo de tarjeta viaja por
  sesión (`purchase.failed_code`), no por estado del pedido. Un cliente Bearer no recibe nada.
- **Lo que SÍ está listo**: los contratos de Fase 2 devuelven DTOs serializables,
  `Cart::sanitize()` define la forma de una línea de cesta y `SlotOffer` es la fuente única de
  oferta (`AFORO-02`).

**El problema**, entonces, es doble: la única puerta al dominio es HTML **y** parte de las reglas
de servidor viven fuera del dominio.

## 2. Objetivo

**Criterios de éxito, medibles y falsables:**
1. Toda operación del sidebar es alcanzable por `/api/v1`, con test de contrato que ejerce el
   dominio real (no mocks).
2. **OpenAPI versionada** + validación de la **respuesta real** contra el esquema en cada test de
   contrato — no solo comparar listas de rutas. Se demuestra **por mutación**: renombrar un campo
   deja el test en rojo (estándar de Fase 2).
3. **Cero regresión**: suite verde y los dos verificadores de concurrencia verdes sobre MySQL.
4. **Ninguna regla de negocio nace en un controlador de API.** Sustituye al criterio «`git grep`
   → cero» de la v1, que no era auditable (una regla duplicada no es una cadena buscable). Se
   verifica con una guarda nueva y comprobada por mutación (§6.5).

**FUERA de alcance:** la SPA y la retirada de `Purchase.php` (Fase 4) · el panel Filament · la
landing (sigue SSR por SEO) · endpoints de administración · **contenido por API, que el tracker
asigna a Fase 5** · `venue/calendar` (su contrato se extrajo para la landing, que no cambia).

## 3. Opciones consideradas

**(a) Autenticación → Sanctum.** Ver `DECISIONES #21`: verificado que resuelve a v4.3.3 contra
nuestras restricciones, y la doc oficial de Laravel 13 desaconseja expresamente usar tokens para
una SPA de primera parte (que es lo que aquí se diseña). Passport (OAuth2 para dos clientes
propios) y tokens a mano: descartados.

**(b) OpenAPI → especificación a mano + `hotmeteor/spectator` (dev).** Ver `DECISIONES #21`.
Scramble (generación desde el código) descartado: invierte la relación: el documento pasa a ser un
reflejo del código, así que el contrato contra el que se construye la app móvil cambiaría en
silencio con cualquier refactor. Nota de la revisión: parsear YAML requiere `symfony/yaml`, hoy
presente solo como transitiva de `packages-dev`; se declarará explícitamente.

**(c) Primer consumidor real — CAMBIADO en la v2.** `DECISIONES #4` descartó «API sin consumidor».
| Opción | Veredicto |
|---|---|
| Post-form de invitados (elección de la v1) | **DESCARTADA por la revisión**: su vía sin sesión es una **URL firmada de una ruta web concreta**, y la firma de Laravel cubre la URL exacta — no autoriza `PUT /api/v1/...`. Además no ejercita Sanctum, ni dinero, ni paginación, ni el sobre de error: probaba lo que ya está probado. |
| Re-cablear `Purchase.php` | DESCARTADA: muere en Fase 4. |
| **«Mi cuenta» + «Mis pedidos»** | **ELEGIDA**: ejercita sesión stateful de Sanctum, titularidad, PII, paginación, sobre de error **y el camino del dinero** (reintento de pago). Y no es trabajo tirado: es literalmente la «gestión de usuario» que `DECISIONES #3` asigna a la SPA de Fase 4, hecha antes. El post-form queda como SEGUNDO consumidor, donde sí aporta (obliga a decidir cómo autentica la API a un portador de firma). |

## 4. Diseño elegido

### 4.1 Enrutado y versión
`routes/api.php` (futuro) con prefijo **`/api/v1`**, registrado en `withRouting(api: …)`. La
versión va en la URL. `v1` es evolutiva hasta que exista el primer cliente móvil (Fase 6); ahí se
congela.

### 4.2 Autenticación, sesión y revocación
- **SPA (mismo dominio)**: cookie de sesión de Sanctum (`statefulApi`) + CSRF. Sin token en
  `localStorage`.
- **Móvil**: Bearer emitido por `POST auth/tokens`, con `abilities`, caducidad configurada y
  `sanctum:prune-expired` en el scheduler.
- **`SEC-06` se hereda de verdad, no de palabra**: login y `auth/tokens` usan los DOS limitadores
  (`email|ip` **y** solo-IP anti-spraying) y el reset conserva el mensaje genérico no-enumerable.
  Hoy son `private const` dentro de `Livewire\Auth\Login`: **hay que extraerlos** (paso 3).
- **⚠️ Revocación — hueco que la revisión destapó**: toda la invalidación del repo es de SESIÓN
  (`User::anonymize()`, `UpdatePassword`, `LogoutOtherDevices`, `ResetPassword` borran filas de
  `sessions`). Un Bearer sobreviviría a las cuatro. **Sanctum obliga a añadir revocación de
  `personal_access_tokens` en `anonymize()` (RGPD art. 17), en el cambio de contraseña, en el
  reset y en «cerrar otras sesiones»**, con test propio en cada uno.
- Reconfirmación de contraseña en las acciones sensibles de `me/*` (SEGURIDAD §3), incluida la
  definición de qué significa con Bearer.
- El cambio de email sigue siendo doble opt-in por enlace firmado web; la API expone iniciar,
  cancelar y reenviar, nunca escribir `email` directamente (`PATCH me` con allowlist explícita:
  `email`, `email_verified_at` y los `*_accepted_at` quedan FUERA).

### 4.3 Respuesta y errores
- Éxito: recurso en la raíz; listas bajo `data` + `meta`.
- Error: `{ "error": { "code", "message", "params", "fields" } }`. `params` es nuevo respecto a la
  v1: `ReservationException` transporta `product`/`when` para interpolar, y sin él el cliente
  mostraría «El producto — no está disponible».
- **Los `code` NO son las claves i18n.** La v1 dijo que «ya existen»: lo que existen son claves de
  traducción, y atar el contrato público a los ficheros de idioma significa que renombrar una
  clave rompe la API. Se define un **mapa clave-i18n → `code` estable**, con test que falla si un
  `throw ReservationException` no está mapeado.
- **Saneado en el SERIALIZADOR, no en el cliente**: `SEC-07` es defensa *de render* y hoy vive en
  el composer de vistas (`safeExternalUrl`, `MapsEmbed`/`SocialEmbed::clean`, `ThemeSettings::hex`
  — «el sink de máximo valor»). Cualquier campo CMS que salga por la API se sanea aquí.

### 4.4 Inventario de endpoints

| Superficie | Endpoints (futuro) | Autorización | Se apoya en |
|---|---|---|---|
| Auth | `POST auth/login` · `register` · `logout` · `password/forgot` · `password/reset` · `email/resend` · `auth/tokens` | pública, con limitadores de `SEC-06` | **`Livewire\Auth\Register`** (no `CustomerRegistrar`, ver §8) |
| Cuenta | `GET/PATCH me` · `PATCH me/password` · `DELETE me` · `POST me/logout-others` · `GET me/export` (RGPD art. 20) · email: iniciar/cancelar/reenviar | dueño; reconfirmación de contraseña | `Livewire\Account\*`, `User::anonymize()` |
| **Mis pedidos** | `GET me/orders` (paginado, TODOS los estados) | dueño | `AccountController::index` (hoy pagina 3 con items/payments/adjustments) |
| Mis reservas | `GET me/reservations` | dueño | `Booking\Contracts\CustomerReservations` |
| Catálogo | `GET catalog/zones` · `catalog/products` · `catalog/products/{id}` | pública | **read-model NUEVO en Booking**, extraído de `Purchase::render()` — incluye esquema `event_fields` y config de complementos |
| Disponibilidad | `GET availability/{product}/dates` · **`POST availability/{product}/times`** (lleva la cesta) | pública | `SlotOffer` + `SlotAvailability`/`PackAvailability` para `maxQty` |
| **Presupuesto** | **`POST orders/quote`** — sin estado, sin bloquear aforo | pública | `Purchase::cartLines()` extraído a Booking |
| Pedido | `POST orders` · `GET orders/{code}` · `POST orders/{code}/payment` · `GET orders/{code}/payment-status` | dueño (scoping por `user_id`, anti-IDOR) | política de admisión + `OrderCreator` + `PaymentInitiator` |
| Post-form | `GET/PUT reservations/{id}/guest-form` | firma canjeada (§4.6) | `GuestFormController` (2.º consumidor) |

**Sigue sin haber endpoints de carrito** —el carrito es estado del cliente— **pero la v1 se
equivocaba al deducir de ahí que el servidor no participa**. Participa en tres momentos, y la v2
los expone sin guardar estado:
- **precio** → `POST orders/quote` (si no, el cliente reimplementaría `RateResolver` +
  `AddonResolver` + `depositCents()`: la segunda fuente de verdad que queríamos evitar);
- **disponibilidad** → `POST availability/{product}/times` **con la cesta**, porque
  `SlotOffer::offerableTimes()` descuenta los ocupantes provisionales de tu propia cesta. Un GET
  sin cesta ofrecería horas que la web no ofrece y que el checkout rechazaría (`AFORO-02` roto en
  la práctica);
- **admisión y creación** → `POST orders`.

### 4.5 Pago
- `POST orders` devuelve los campos del formulario de la pasarela; el cliente los auto-POSTea.
- **Retorno y notificación siguen siendo rutas WEB** (`PAY-01`: `RedsysReturnHandler` es el único
  que pasa una Order a `paid`). Pero la v2 añade lo que faltaba:
  · arreglar el consumo del token de retorno (**`Cache::pull` antes de validar la sesión** lo
    quema para un cliente sin cookie);
  · **estados reales** en `payment-status`, derivados de `Order.status` **más el último
    `Payment`** (`pending`/`authorized`/`paid`/`failed`/`superseded`) — hoy el rechazo solo viaja
    por sesión, así que la API diría «pendiente» 15 min y luego «expirado», nunca «reintenta»;
  · **`redsys_merchant_url` (notificación S2S) es prerequisito DURO del cliente móvil**: sin ella
    y con terminal data-less, el único camino a `paid` es la vuelta del navegador, que en móvil no
    existe → el pedido caduca con la tarjeta cobrada (entra por `PAY-02` como incidencia).
- **Retorno móvil**: URL de retorno parametrizada + página puente que dispare el deep link, o
  declaración explícita de que el móvil depende solo del polling. Hoy `DS_MERCHANT_URLOK` no lleva
  parámetros, así que no hay dónde colgar un `state`.

### 4.6 Extracciones que Fase 3 debe hacer ANTES de exponer nada
No son refactors opcionales: sin ellas la API duplica reglas o las pierde.
1. **Política de admisión** (pausa de reservas + tope de pending + rate por usuario) → servicio de
   Booking consumido por `Purchase`, `RetryPaymentController` y la API.
2. **Ida del pago** → `PaymentInitiator`, hoy duplicada entre `Purchase` y `RetryPaymentController`.
3. **Auth**: limitadores, política de email existente, honeypot/Turnstile y consents del registro →
   servicios de Identity que devuelvan resultado y dejen los efectos de sesión al llamante (el
   anti-cesta-cruzada de `Login` es estado de sesión web y NO debe viajar al servicio).
4. **Tarificación de cesta** (`cartLines`) y **read-model de catálogo** → Booking.
5. **Canje del enlace firmado del post-form** por credencial de API de vida corta (2.º consumidor).

### 4.7 Transversales
- **Grupo de middleware de API declarado pieza a pieza** (hoy `SetLocale`, `SecurityHeaders` y
  `EnsureSiteAvailable` están SOLO en `web`): `SecurityHeaders` sí (`SEC-01` nació de una
  superficie sensible que se quedó fuera); locale **stateless** por `Accept-Language` —
  `SetLocale` escribe en sesión y NO es reutilizable tal cual—; `no-store` **por defecto en todo
  `/api/v1` autenticado** (`RGPD-04` habla de «toda superficie no-Livewire con PII», no solo
  invitados); y **decisión escrita sobre el kill-switch de mantenimiento** (#218): si la API entra
  en `EnsureSiteAvailable` devolvería HTML 503 a un cliente JSON; si no entra, queda fuera del
  mantenimiento. Se elige: entra, con render JSON.
- **Rate limiting**: `RateLimiter::for()` no existe hoy en `app/`. El reintento de pago del cliente
  conserva **`throttle:6,1`** (la v1 citó `PAY-15`/120 por minuto, que es el throttle de las
  **callbacks de Redsys**, 20× más laxo).
- **La frontera de la API NO la vigila hoy `ModuleBoundariesTest`**: su lista `DELIVERY` incluye
  `Http` y sale por `continue`. La v1 afirmaba lo contrario. Se añade una guarda propia (§6.5).

## 5. Impacto en invariantes

Ninguno se relaja sin decisión del owner. Filas que la v1 omitía y la revisión añadió:

| ID | Cómo le afecta |
|---|---|
| `PAY-01` | La API no abre una segunda vía a `paid`. |
| `PAY-02` | Sin notificación S2S el móvil puede dejar pedidos cobrados sin `paid` → incidencia. |
| `PAY-04` `PAY-11` | El reintento por API debe ser **UPDATE atómico CAS**, no check+save: separarlos resucita un hold vencido sin recontar aforo (hallazgo L2 del origen). |
| `PAY-12` `PAY-13` | Precio y topes en servidor; `POST orders` pasa por la política de admisión **y** `OrderCreator`. |
| `PAY-14` `PAY-15` | Emails en cola; el throttle de callbacks no se confunde con el del cliente. |
| `AFORO-01` | `POST orders` no reordena nada de `lockSlots`. |
| `AFORO-02` | Disponibilidad solo por `SlotOffer`, **con la cesta**. |
| `AFORO-10` | `createPendingOrder(…, ?Carbon $hold = null)`: el default es pedido FIRME que no caduca. La API debe pasar el hold siempre, con test. |
| `SEC-01` | `SecurityHeaders` también en el grupo API. |
| `SEC-06` | Dos limitadores + no-enumeración, extraídos. **Turnstile en cliente nativo: ver §7.** |
| `SEC-07` | Saneado en el serializador. |
| `SEC-10` | `PATCH me` con allowlist: `User` no está entre los modelos con `$fillable` cerrado. |
| `RGPD-01` | `anonymize()` debe revocar tokens. |
| `RGPD-03` `RGPD-04` | Caducidad del enlace del post-form; `no-store` en todo `/api/v1` autenticado. |
| `RGPD-05` | El bloqueo previo de iframes hasta consentir es defensa de Blade: si la API sirve embeds, el cliente los cargaría sin consentimiento. |
| `PERF-02` | `catalog/*` y `availability/*` son públicos y de alta frecuencia: presupuesto de queries, como el de `HomePageTest`. |
| `SUITE-01` | Tests de API sin red real. |

## 6. Plan de verificación empírica

1. **Contrato por endpoint** ejerciendo el dominio real.
2. **OpenAPI**: rutas ↔ paths **y** validación de la respuesta real contra el esquema en cada test;
   demostrado **por mutación** (renombrar un campo → rojo).
3. **Paridad web↔API con cesta NO vacía** (una línea de entrada y una de pack en la misma zona y
   día) comparada contra `Purchase` con esa misma cesta, y **mutación**: cambiar la cesta debe
   cambiar el resultado. El test de la v1, con cesta vacía, pasaba por construcción.
4. **No-regresión**: suite + `redsys:verify-concurrency` + `purchase:verify-oversell`. ⚠️ La v1 los
   presentaba como aval de la API: no lo son (`purchase:verify-oversell` llama a `OrderCreator`
   dentro del fork, sin HTTP). O se añade un modo que golpee el endpoint, o se dice la verdad:
   son control de no-regresión del dominio.
5. **Guarda de frontera propia**, verificada por mutación: los controladores de `/api/v1` no
   pueden contener `DB::transaction`, `Payment::create`, `RateLimiter::hit` ni consultas Eloquent
   de escritura. Es la versión falsable del criterio 4 de §2.
6. **Negativos de autorización (IDOR)** por endpoint, y el orden 403/410/404 del post-form, que
   está deliberadamente escalonado para no filtrar la existencia de una reserva.
7. **Revocación de tokens**: tres tests (borrado RGPD, cambio de contraseña, cerrar otras sesiones).
8. `Accept-Language` explícito en los tests: `TestCase::setUp()` lo vacía en cada test, así que un
   test de negociación que no lo ponga prueba el fallback.

## 7. Revisión y decisión

**Revisión adversarial hecha (2026-08-13)**: 3 revisores independientes (invariantes/seguridad ·
arquitectura · riesgo de implementación). Veredictos: sólida-con-cambios · insuficiente ·
insuficiente. **15 hallazgos GRAVE**, varios convergentes. Todos incorporados en esta v2 (§8).

**Dependencias: DECIDIDAS** (el owner delegó la elección) → `DECISIONES #21`.

✅ **RESUELTO — anti-bot del registro en cliente nativo (`DECISIONES #23`).** La revisión lo marcó
como bloqueante por relajar un invariante, pero al comprobarlo: el Turnstile del REGISTRO no es
`SEC-06` (que cubre el 2.º limitador del login, la no-enumeración del reset y `/contacto`) sino
`SEGURIDAD` regla 5, y es data-driven. Y sobre todo: **el consumidor de Fase 3/4 es la SPA, que ES
un navegador** — `POST auth/register` exigirá el token igual que la web. La app nativa es Fase 6;
decidir hoy su anti-bot sería diseñar seguridad para un lector que no existe. **No se relaja nada
y el paso 3 queda desbloqueado**; la pregunta viaja a Fase 6 con sus tres salidas ya escritas.

✅ **HECHO — árbol saneado antes de instalar nada (`DECISIONES #22`).** Los 26 avisos de seguridad
están cerrados (`composer audit` y `npm audit` → 0), con suite, Pint, docs-check, los dos
verificadores sobre MySQL, superficies y generación real de PDF verificados.

## 8. Qué cambió de la v1 a la v2 (y por qué)

Se conserva de la v1: versión en URL, sobre de error único, retorno/notificación de Redsys sin
tocar, sin endpoints de carrito, y `PaymentProvider` extraído de llamadas reales.

Se corrige:
1. **Mediciones erróneas**: 37 rutas (no 29), 6 paquetes (no 7), 46 métodos públicos (no ~40), y
   sobre todo «solo 4 operaciones de servidor», que era la premisa de la que colgaba el diseño.
2. **Faltaba el endpoint de presupuesto**: sin él, el cliente no puede mostrar total ni señal sin
   crear un pedido (que bloquea aforo), o reimplementa el precio.
3. **Disponibilidad sin cesta**: rompía `AFORO-02` en la práctica, y el test de paridad estaba
   construido para no verlo.
4. **Faltaba «Mis pedidos»**: un cliente que pierde el estado no podía recuperar un `pending` a
   medio pagar, con la retención de aforo viva.
5. **`CustomerRegistrar` es alta de back-office** (auto-verifica el email, sin anti-bot, solo
   consent de privacidad): mapearlo a `POST auth/register` habría destruido la prueba de
   titularidad del buzón.
6. **Reglas de servidor fuera del dominio**: pausa de reservas y topes anti-abuso; ida de pago ya
   duplicada. Ahora son extracciones explícitas (§4.6).
7. **Sanctum sin revocación** habría dejado tokens vivos tras el borrado RGPD.
8. **Primer consumidor cambiado**: el post-form no puede autenticarse contra la API con su firma.
9. **Afirmaciones falsas retiradas**: que `ModuleBoundariesTest` vigilaría los controladores de
   API; que los `code` de error «ya existen»; que los verificadores de concurrencia avalan la API;
   que `PAY-15` (120/min) es el throttle del cliente.
10. **Fase 3 partida en pasos** (§9): la v1 la planteaba como una unidad, cuando Fase 2 —que era
    mudanza sin lógica— necesitó 7 pasos.

## 9. Corte en pasos (un paso = una unidad committeable, suite verde + gates)

0. **Cimientos, sin negocio**: `api:` en `withRouting`, grupo de middleware (§4.7), Sanctum, sobre
   de error, `GET me`, esqueleto OpenAPI + su test con mutación. Cierra la deuda de dependencias.
1. **Solo lectura**: `me/reservations`, `me/orders`, catálogo (read-model nuevo). Sin escritura.
2. **Refactor sin endpoints**: extraer política de admisión e ida de pago (§4.6.1–2). Aquí tocan
   `VERIFY_CONC=1` y los dos verificadores; web y panel quedan de testigo.
3. **Auth por API**: extracción de `Login`/`Register`/`ForgotPassword`/`ResetPassword` + revocación
   de tokens. **Bloqueado por la decisión de §7.**
4. **El dinero**: quote, disponibilidad con cesta, `POST orders`, reintento y `payment-status` con
   estados reales + historia de retorno móvil.
5. **Post-form migrado** (2.º consumidor), una vez resuelto su canje de credencial.

⚠️ El gate `pre-push` ancla `VERIFY_CONC` a `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator`:
los controladores de checkout de API **no lo dispararían**. Ampliar `CRITICAL_RE` es parte del
paso 0.
