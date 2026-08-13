# [SPEC] API v1 (Fase 3)

> Estado: 🟦 **v2 · EN EJECUCIÓN — pasos 0, 1a, 1b, 2 y 3 TERMINADOS** (2026-08-13) ·
> Última actualización: 2026-08-13 · Decisiones asociadas: `DECISIONES #21` (dependencias),
> `#22` (árbol saneado), `#23` (anti-bot), **`#24`** (paso 0), **`#26`** (paso 1a), **`#27`**
> (paso 1b), **`#28`** (paso 2) y **`#29`**–**`#31`** (paso 3).
> Antecedentes: `DECISIONES #3` (el sidebar se rehace como SPA contra la API) y `#4` (API-first).
> Qué cambió respecto a la v1 y por qué: **§8** · Corte en pasos y su avance: **§9** ·
> **Lo que el código enseñó al implementar: §10 → §10.septies** — léelos antes de seguir por el
> paso 4.
> ⚠️ **§1 es el diagnóstico PREVIO** (2026-08-13, antes de tocar nada): describe un repo sin API y
> se conserva como registro del análisis, no como foto del código de hoy.

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
`routes/api.php` con prefijo **`/api/v1`**, registrado en `withRouting(api: …)`. La versión va en
la URL. `v1` es evolutiva hasta que exista el primer cliente móvil (Fase 6); ahí se congela.
✅ Hecho en el paso 0: el prefijo tiene **fuente única** (`App\Http\Api\ApiSurface::PREFIX`), que
usan a la vez el registro de rutas, el sobre de error y el 503 de mantenimiento — las tres cosas
que dejarían de coincidir si cada una llevara su copia.

### 4.2 Autenticación, sesión y revocación
- **SPA (mismo dominio)**: cookie de sesión de Sanctum (`statefulApi`) + CSRF. Sin token en
  `localStorage`.
- **Móvil**: Bearer con `abilities`, caducidad configurada y `sanctum:prune-expired` en el
  scheduler. ⚠️ **La EMISIÓN (`POST auth/tokens`) se aplaza a Fase 6, con la app** (decisión del
  owner, 2026-08-13, `DECISIONES #29`): la v2 de este spec la ponía en el paso 3, pero el
  consumidor de Fase 3 y 4 es la SPA, que usa cookie de sesión. Un emisor de Bearers de 30 días sin
  ningún cliente que los use durante dos fases es superficie de ataque sin contrapartida, y
  `DECISIONES #4` ya descartó «API sin consumidor». La infraestructura (trait, caducidad, poda)
  queda lista y la REVOCACIÓN se hizo igualmente — ver el punto siguiente.
- **`SEC-06` se hereda de verdad, no de palabra**: el login usa los DOS limitadores (`email|ip`
  **y** solo-IP anti-spraying) y el reset conserva el mensaje genérico no-enumerable. Hoy son
  `private const` dentro de `Livewire\Auth\Login`: **hay que extraerlos** (paso 3b). Cuando llegue
  el emisor de tokens en Fase 6, hereda los mismos.
- ✅ **Revocación — RESUELTO** (2026-08-13, paso 3a, `DECISIONES #29`). El hueco era real y algo
  peor de lo que la revisión describió: la purga de sesiones estaba copiada en CUATRO ficheros,
  ninguno tocaba `personal_access_tokens`, y además el comando de limpieza de go-live dejaba tokens
  HUÉRFANOS —la tabla es morph y no tiene FK, así que borrar el usuario no los borra—. Ahora la
  invalidación tiene un punto único (`User::revokeAllAccess()` / `revokeOtherAccess()`), alcanza a
  las dos credenciales, y una guarda de arquitectura impide que aparezca una quinta copia
  (`INVARIANTES RGPD-06`). Se hizo **antes** de que exista un solo token emitido, que es cuando se
  puede hacer sin prisa.
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
| Auth | `POST auth/login` · `register` · `logout` · `password/forgot` · `password/reset` · `email/resend` · ~~`auth/tokens`~~ (emisión aplazada a Fase 6, `DECISIONES #29`) | pública, con limitadores de `SEC-06` | **`Livewire\Auth\Register`** (no `CustomerRegistrar`, ver §8) |
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

0. ✅ **Cimientos, sin negocio** (2026-08-13, `DECISIONES #24`): `api:` en `withRouting`, grupo de
   middleware (§4.7), Sanctum, sobre de error, `GET me`, esqueleto OpenAPI + su test con mutación.
   Cierra la deuda de dependencias. **Lo que el código enseñó: §10.**
1. **Solo lectura**, partido en dos por dificultad desigual:
   - ✅ **1a** (2026-08-13, `DECISIONES #26`): `me/reservations` y `me/orders`. Lectura sobre
     contratos y modelos que ya existen.
   - ✅ **1b** (2026-08-13, `DECISIONES #27`): catálogo (read-model nuevo en Booking). Se extrajo
     de `Purchase::render()` separando dominio de presentación: `search` y `zone_anchor` se
     quedaron en la web —son índice de búsqueda en cliente y ancla de scroll—, y el resto pasó al
     contrato `ProductCatalog`, que la web consume desde el mismo commit. **Lo que el código
     enseñó: §10.ter.**
2. ✅ **Refactor sin endpoints** (2026-08-13, `DECISIONES #28`): política de admisión
   (`Booking\Contracts\ReservationAdmission`) e ida de pago (`Payments\PaymentInitiator`)
   extraídas de `Purchase` y de `RetryPaymentController` (§4.6.1–2), con los dos verificadores de
   concurrencia verdes sobre MySQL. Las dos superficies aplicaban políticas DISTINTAS sin que
   nadie lo hubiera decidido; el owner resolvió las tres asimetrías. **Lo que el código enseñó:
   §10.quater.**
3. **Auth por API**, partido en tres por el mismo motivo que el paso 1 (trabajos de naturaleza
   distinta en un solo diff son irrevisables):
   - ✅ **3a** (2026-08-13, `DECISIONES #29`): **revocación de credenciales**. Sin endpoints:
     cierra el hueco de §4.2 antes de que exista un token emitido. Punto único
     (`User::revokeAllAccess()`/`revokeOtherAccess()`) + guarda de arquitectura + los tokens
     huérfanos de la limpieza de go-live. **Lo que el código enseñó: §10.quinquies.**
   - ✅ **3b** (2026-08-13, `DECISIONES #30`): `Identity\Services\PasswordLogin` (los DOS
     limitadores de `SEC-06`, credenciales, `last_login_at`) + `POST auth/login` y `auth/logout`
     sobre la sesión stateful. La web lo consume desde el mismo commit. **Lo que el código enseñó:
     §10.sexies.**
   - ✅ **3c** (2026-08-13, `DECISIONES #31`): `Identity\Services\SelfSignup` (honeypot, tres
     limitadores, Turnstile, cuenta+rol+consents atómicos) y `PasswordRecovery`, más
     `POST auth/register`, `auth/email/resend`, `auth/password/forgot` y `auth/password/reset`.
     La web consume los dos servicios desde el mismo commit. **Lo que el código enseñó:
     §10.septies.**
   Desbloqueado desde `DECISIONES #23`; la EMISIÓN de tokens viaja a Fase 6 (`#29`).
4. **El dinero**: quote, disponibilidad con cesta, `POST orders`, reintento y `payment-status` con
   estados reales + historia de retorno móvil.
5. **Post-form migrado** (2.º consumidor), una vez resuelto su canje de credencial.

⚠️ El gate `pre-push` ancla `VERIFY_CONC` a `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator`:
los controladores de checkout de API **no lo dispararían**. Ampliar `CRITICAL_RE` es parte del
paso 0.

## 10. Lo que el código enseñó — paso 0 (2026-08-13)

Sección viva: cada paso añade la suya, como hizo `modulos-dominio.md` §4.bis–§4.octies. Solo lo que
**cambia el trabajo del siguiente paso**; el porqué completo está en `DECISIONES #24`.

**1. Abrir la API abrió CORS sin que nadie lo decidiera.** `HandleCors` es middleware GLOBAL de
Laravel y su config por defecto (la del framework, hasta que se publica) trae `paths: ['api/*']` con
`allowed_origins: ['*']`. `curl -I` lo confirmó en la primera petición que funcionó. Cerrado con
`config/cors.php` de orígenes exactos derivados de `APP_URL`. **Lección general que el paso 1 debe
aplicar**: al abrir una superficie nueva, no basta con revisar qué middleware se HEREDA —el §4.7 ya
lo hizo—, hay que revisar también qué middleware GLOBAL se despierta con ella.

**2. El limitador no ve las peticiones sin credencial.** `$middlewarePriority` de Laravel pone
`AuthenticatesRequests` antes que `ThrottleRequests`: en una ruta con `auth:`, el 401 nunca llega a
`throttle:api`. Se conserva el estándar (invertirlo empeoraría los `throttle` de la web) y está
fijado por test. **Consecuencia para el paso 3**: los limitadores de `SEC-06` van en rutas PÚBLICAS
(login, registro, reset), así que funcionan; pero cualquier futuro techo pensado para proteger un
endpoint AUTENTICADO no puede confiar en el suelo genérico y necesita su propio mecanismo.

**3. El coste del grupo de middleware es 1 consulta**, no las que se temían. `SecurityHeaders`
calcula la CSP leyendo `settings`, y en una superficie de alta frecuencia eso preocupaba; el memo
por petición de `PERF-02` ya lo cubría. Medido y fijado en `ApiOverheadTest`. **El paso 1 hereda el
presupuesto** y le añadirá el suyo para `catalog/*` y `availability/*`.

**4. Sanctum no carga sus migraciones** (solo `publishesMigrations`), así que hay que publicarlas o
la tabla no existe. Publicadas al repo también por criterio propio: el esquema de un producto que se
instala cliente a cliente se versiona aquí.

**5. La estrictez del esquema ES la prueba por mutación.** Que renombrar un campo ponga el test en
rojo depende de que el documento declare `additionalProperties: false` y liste el campo en
`required`. Como eso puede relajarse sin que nadie lo note, `ApiContractTest` lo comprueba, con las
excepciones declaradas por nombre (hoy: `params` y `fields` del sobre). **Todo esquema nuevo del
paso 1 nace con esa forma** o el test lo rechaza.

**6. `ApiBoundariesTest` y `CRITICAL_RE` no se solapan, se complementan.** El primero prohíbe que la
lógica NAZCA en el controlador; el segundo obliga a correr los verificadores de concurrencia cuando
un controlador de checkout cambia. Un controlador que llama al `OrderCreator` correcto pasa el
primero y aun así puede reordenar sus llamadas y romper `AFORO-01`. **El paso 4 necesita los dos.**

**7. Detalle operativo para quien escriba tests de API**: heredar de `Tests\Feature\Api\ApiTestCase`
activa la validación contra el contrato en TODA petición del test. Para probar cimientos con rutas
sintéticas (validación, fallos, límites) hay que heredar de `Tests\TestCase` y montar la ruta con
`Route::middleware('api')`, o Spectator falla por «path no declarado» en vez de probar lo que toca.

### 10.bis Lo que el código enseñó — paso 1a (2026-08-13)

**8. Una lista no se delega en `ResourceCollection`.** Con un paginador, Laravel responde por
`PaginatedResourceResponse`, que ignora `$wrap` y añade sus propios `links` y `meta`: la respuesta
salía con `data.data` y **dos** `meta` distintos. Ahora la forma la construye `ApiCollection`
(implementa `Responsable`) y es idéntica pagine o no el endpoint. **Todo endpoint de lista del paso
1b usa esa clase**; no hay una segunda forma de lista en el contrato.

**9. Un nombre de campo puede mentir, y el contrato lo caza.** `online_due_cents` se diseñó como «lo
que falta por pagar»; `Order::onlineDueCents()` es en realidad el importe que se cobra online —la
señal, si el producto la usa— y **no baja a 0 al pagar**. Renombrado a `online_amount_cents`.
Lección para el 1b: **leer el método antes de nombrar el campo**, sobre todo en el catálogo, donde
`fromPriceCents` es «precio desde» y no un precio real.

**10. Gotcha de OpenAPI 3.0**: un `enum` rechaza `null` aunque el campo se declare `nullable`, salvo
que `null` figure dentro del propio `enum`. Aparecerá otra vez en el 1b (los campos opcionales del
esquema de producto).

**11. Los métodos de estado del dominio ya devuelven CLAVES, no etiquetas** (`paid`/`expired`,
`active`/`finished`, `ok`/`pending`): son aptos como contrato público tal cual, sin mapa
intermedio. Comprobado leyéndolos, no supuesto — conviene repetir la comprobación en el 1b antes de
exponer cualquier estado del catálogo.

**12. El gate de concurrencia condiciona cómo se NOMBRAN los controladores.** `MeOrdersController`
existe con ese nombre porque `Order*` dispara `VERIFY_CONC`, y un endpoint de solo lectura no debe
exigir dos verificadores de 16 workers. La separación no es un truco: `CriticalPathGateTest` falla
si un controlador fuera del patrón alcanza el núcleo. El paso 1b tiene el mismo dilema con
`Availability*` — un catálogo de solo lectura no debería disparar el gate, pero la disponibilidad
del paso 4 sí.

### 10.ter Lo que el código enseñó — paso 1b (2026-08-13)

**13. Extraer un read-model no es mover código: es decidir qué era dominio.** De los diez campos
que `catalogSection()` producía, ocho lo eran y dos no. `search` (nombre + ventajas en minúsculas)
es el índice del buscador que la web filtra EN CLIENTE, y `zone_anchor` (el slug de la zona solo en
el primer producto de cada una) es el ancla de scroll del deep-link. Los dos se **derivan** de los
datos del contrato (`name`, `features`, `zone`), así que quedarse en la vista no les cuesta nada, y
subirlos al dominio habría obligado a todo cliente futuro a cargar con el índice de búsqueda de
otro. **El criterio que funcionó**: si otro cliente con otra interfaz lo necesitaría igual, es
dominio; si solo lo necesita el que lo pintó así, es presentación.

**14. `AddonResolver::viewModel()` NO servía como fuente del catálogo, y el spec creía que sí.**
Su firma pide el estado de la selección (`qtyMap`, `groupChoices`, invitados) porque su trabajo es
decir qué está activo y cuánto suma; un catálogo no tiene ese estado. Lo que sí se reutiliza —y es
lo que importaba— son las REGLAS: qué complementos llegan a ofrecerse (un extra de pago sin precio
para la tarifa no se ofrece) y cuál es la selección por defecto (`defaultSelection()`, que sí es
estática y sí es dominio). El read-model las llama; no las reescribe.

**15. Un `$ref` con `nullable` no funciona, y falla en las DOS direcciones.** Para anidar un objeto
opcional, OpenAPI 3.0 obliga a `allOf: [$ref]` + `nullable: true` — un `$ref` ignora sus hermanos.
El validador de Spectator ignora ese `nullable`: con él, una zona nula falla («The data (null) must
match the type: object») y una zona presente también («The data (object) must match the type:
null»). La única forma que valida ambas es escribir el objeto entero inline con su `nullable`. Se
paga con una copia del esquema, y esa copia la vigila `ApiContractTest` campo a campo. Es el
hermano del gotcha del `enum` (punto 10): **en 3.0, todo lo que sea «esto puede ser nulo» hay que
ejercerlo con un test, no darlo por escrito.**

**16. La validación de contrato hay que PEDIRLA.** Heredar de `ApiTestCase` deja Spectator
cableado, pero solo compara cuando el test llama a `assertValidRequest()`/`assertValidResponse()`.
Los primeros tests del catálogo pasaban en verde sin validar nada contra el documento: la mutación
de comprobación (renombrar un campo del Resource) solo tumbó el test que asertaba ese campo a mano.
**Sin un `assertValidResponse()` explícito por endpoint y por status, el contrato no muerde.**

**17. El presupuesto de consultas se mide por PENDIENTE, no por techo.** Un techo fijo envejece y se
acaba subiendo; comparar el coste con 1 elemento y con N lo convierte en una prueba de N+1 que no
hay que recalibrar. Así se destapó uno REAL en el primer borrador del read-model:
`RateResolver::priceCents()` hace su propia consulta, y llamarlo dentro del bucle de complementos
costaba dos consultas por complemento. Se arregló resolviendo la tarifa una vez y leyendo el precio
de la relación ya cargada (`TicketType::priceCentsForRate()`), con resultado idéntico.
⚠️ Dos trampas al medir, las dos vividas: `DB::listen` **no se puede desregistrar** —medir dos veces
en el mismo test contaba cada consulta el doble y fingía un N+1 inexistente; se usa el query log—, y
**la primera petición de un proceso paga el `select` de `settings`** que `PERF-02` memoiza, así que
hay que calentar antes de comparar.

**18. Dos definiciones de «precio de referencia» conviven en el producto**, y son distintas:
`displayPriceCents()` (la de la landing) prefiere la tarifa `normal` y cae al mínimo; el catálogo
de compra usa el mínimo a secas. El read-model conserva la del catálogo —`fromPriceCents`— porque
cambiarla habría cambiado el importe anunciado en el flujo de compra. Con la misma lógica se
conservó que el mínimo NO filtra por tarifa activa: una instalación con precios colgando de una
tarifa desactivada anunciaría un «desde» que no se puede comprar. Es un riesgo latente (no observado
en datos reales), y tocar lo que se le anuncia al cliente exige decisión del owner — queda en
`DEUDA.md`, no resuelto de tapadillo dentro de una extracción.

### 10.quater Lo que el código enseñó — paso 2 (2026-08-13)

**19. Duplicar código duplica la POLÍTICA, y eso no se ve leyendo una de las copias.** El spec
describía la ida del pago como «duplicada casi línea a línea», y lo estaba; lo que no decía —porque
solo aparece al poner las dos copias en paralelo— es que **no hacían lo mismo**. El reintento del
sidebar aplicaba el tope de pedidos pendientes y el limitador por titular; el de «Mis pedidos», ni
uno ni otro (solo el `throttle:6,1` por IP de su ruta). Ninguna de las dos conductas estaba fijada
por un test: eran un accidente de la historia, no una decisión. **Lección para las extracciones que
quedan** (§4.6.3, auth): antes de unificar, poner las copias lado a lado y listar en qué se
diferencian; cada diferencia es una decisión de producto que alguien tiene que tomar, no un detalle
de implementación que el refactor pueda resolver solo.

**20. Un tope antiabuso puede estar contando lo que no cree contar.** El limitador de reservas
(3/min) se consumía DOS veces por compra: al pasar del carrito a la pantalla de pago —que no crea
nada— y al confirmar. Medido: la segunda compra del mismo minuto se bloqueaba, con el tope nominal
en tres. La regla ahora es explícita en el contrato: `mayReserve()` consulta sin consumir y
`admitReservation()` consume, y el chequeo temprano se conserva porque avisar antes es mejor UX que
llevar al cliente hasta la pasarela para decirle allí que no. **La forma de encontrarlo fue contar
las llamadas del flujo REAL**, no leer el método: los tests que existían saltaban directamente al
paso de confirmación y por eso pasaban.

**21. Al mudar código endurecido, el gate que lo protege se muda con él.** El UPDATE atómico de
`PAY-04` y el `SUPERSEDED` de los intentos previos vivían en un componente Livewire y en un
controlador web: dos sitios que el `CRITICAL_RE` del `pre-push` **nunca miró**, porque el patrón
apunta al núcleo de dominio. Al darles nombre propio (`ReservationAdmissionPolicy`,
`PaymentInitiator`) fue posible —y obligatorio— meterlos en el patrón, con su entrada en
`CriticalPathGateTest` y su prueba por mutación. El refactor no solo quitó copias: puso bajo
vigilancia código de dinero que llevaba tiempo fuera de ella.

**22. Extraer una regla la vuelve testeable por sí misma, y eso cambia lo que se puede probar.**
Las tres reglas de admisión solo se podían ejercitar montando un carrito, un catálogo y un usuario,
y lo que se probaba era la pantalla. Con el servicio aislado aparecieron casos que antes no
compensaba escribir: que un reintento rechazado por frecuencia **no** extienda el hold (si lo
extendiera, martillear el botón mantendría la plaza retenida sin pagar), que un veredicto denegado
no toque el pedido de otro titular, o que la pausa no gaste fichas del limitador. Ninguno era
alcanzable a golpe de Livewire.

**23. Un veredicto se traduce distinto en cada superficie, y ahí es donde estaba el mensaje que
mentía.** Al unificar, «Mis pedidos» heredó el límite de frecuencia y su primer borrador reutilizó
el flash existente de «no se puede reintentar», cuyo texto dice *«la reserva ha caducado y la plaza
se ha liberado»*. Le habría dicho a quien pulsó dos veces seguidas que había perdido su reserva. El
dominio devuelve `reason` y cada superficie decide el mensaje: por eso el DTO lleva una clave
estable y no un texto ni una clave de `lang/`.

### 10.quinquies Lo que el código enseñó — paso 3a (2026-08-13)

**24. El hueco era peor de lo que la revisión describió, y la diferencia la dio contar los sitios.**
§4.2 hablaba de cuatro lugares donde un Bearer sobreviviría. Al buscarlos aparecieron **cinco**: los
cuatro previstos más `PurgeCustomerData`, el comando de limpieza de go-live, que borra la fila de
`users` y —como `personal_access_tokens` es una tabla MORPH **sin clave foránea**, verificado con
`Schema::getForeignKeys()` → 0— dejaría los tokens huérfanos apuntando a un id que ya no existe. El
comando ya borraba a mano `sessions` y `password_reset_tokens` por ese mismo motivo; los tokens de
API son la tercera tabla sin FK y llegaron después de escribirlo. **Lección**: cuando un fichero
enumera «adyacentes sin FK», esa lista envejece cada vez que se instala un paquete que crea tablas.

**25. Contra la duplicación que se repite sola, no sirve recordarlo: hay que hacerlo imposible.** La
purga de sesiones estaba copiada en cuatro ficheros y las cuatro copias se olvidaron de los tokens
—no por descuido, sino porque se escribieron antes de que Sanctum existiera—. Arreglar las cuatro
habría dejado el mismo terreno para la quinta. Ahora solo `User` sabe escribir en esas tablas y una
guarda de arquitectura lo comprueba con el tokenizador, con dos excepciones declaradas por nombre y
con su motivo. El coste de la guarda es una clase de test; el de no tenerla, un agujero silencioso
cada vez que aparezca una credencial nueva.

**26. «Conservar la credencial actual» significa dos cosas distintas según por dónde entres.** En
`revokeOtherAccess()`, si la petición llega por sesión, `currentAccessToken()` no devuelve un token
persistido sino un `TransientToken` sin identificador — así que **caen todos los tokens de API**.
No es un efecto colateral que haya que corregir: es lo correcto, porque quien cambia su contraseña
desde el navegador espera que cualquier app conectada deje de estarlo. La rama simétrica (entrar por
token y conservar solo ese) tiene su propio test, y para escribirlo hay que atar el token real con
`withAccessToken()`: el que monta `Sanctum::actingAs` es de mentira y no ejerce la comparación.

**27. Arreglar seguridad ANTES de que exista la superficie es más barato y más honesto.** Hoy no hay
emisor de tokens, así que ninguno de estos cinco huecos es explotable: es exactamente el momento de
cerrarlos, sin urgencia, con tests y sin tocar nada en producción. Hacerlo junto con la emisión
—como planteaba la v2 del spec— habría metido en el mismo commit la puerta y su cerradura, y
cualquier prisa se habría llevado por delante la segunda.

### 10.sexies Lo que el código enseñó — paso 3b (2026-08-13)

**28. La sesión de la SPA depende de un encabezado, y sin él el endpoint reventaba con 500.**
`EnsureFrontendRequestsAreStateful` solo monta `StartSession` cuando la petición viene de un origen
declarado *stateful* — lo mira en `Origin`/`Referer`—. Un navegador los envía siempre; un `curl`,
una app mal configurada o un test, no. Sin sesión, `$request->session()` lanza «Session store not
set on request» y el login respondía **500** a un problema de integración perfectamente
diagnosticable. Ahora hay una guarda explícita que devuelve 400 antes de validar nada y antes de
tocar el limitador. **Consecuencia para quien integre la SPA (Fase 4)**: el encabezado hace falta
en TODAS las peticiones, no solo en el login — se comprobó con `curl`, y un `GET /me` sin `Origin`
devuelve 401 aunque la cookie de sesión sea válida.

**29. `SESSION_DRIVER=array` hace imposible probar el ciclo de sesión en la suite.** Dos peticiones
del mismo test no comparten sesión, así que un `GET /me` después de un login responde 200 **por el
guard que quedó cacheado en memoria**, no porque la cookie valga: un falso positivo que además
haría pasar un `logout` roto. Los tests aseveran solo lo observable dentro de la petición
(`assertGuest()` + el guard de la petición) y el ciclo entero —entrar, usar, salir, ya no entrar—
se verificó con `curl` y su tarro de cookies contra el servidor real. **Regla que sale de aquí**:
antes de escribir un test de sesión multi-petición, comprobar qué driver usa la suite.

**30. Cerrar sesión no basta con cerrar la sesión.** `auth('web')->logout()` deja el guard `web`
limpio, pero el guard que atendió la petición (`sanctum`, un `RequestGuard`) sigue cacheando al
usuario: medido, `auth('sanctum')->check()` devolvía `true` justo después del logout. En producción
no se nota —cada petición es un proceso nuevo—, pero cualquier cosa que corra después dentro de la
misma petición vería a un usuario ya deslogueado. Se cierra con `Auth::forgetGuards()`.

**31. La guarda de frontera acertó, y el sitio correcto ya existía.** El primer borrador del
`logout` borraba el token con `$token->delete()` en el controlador; `ApiBoundariesTest` lo marcó
como escritura de dominio en la capa HTTP. No era un falso positivo: revocar una credencial es
justo lo que el paso 3a había centralizado en `User`, así que la corrección fue mover el método
(`revokeCurrentAccessToken()`), no añadir una excepción. **Cuando una guarda de arquitectura
protesta por código nuevo, la primera hipótesis es que el sitio correcto ya está decidido.**

**32. Un esquema de PETICIÓN no se puede exigir como uno de respuesta.** La guarda de estrictez del
contrato reclama `required` completo, que en una respuesta es lo que hace morder la prueba por
mutación. En un cuerpo de petición, en cambio, hay campos legítimamente opcionales (`remember`).
Se resolvió con una excepción con nombre y su porqué, conservando `additionalProperties: false`,
que es lo que de verdad importa ahí: impedir que un cliente cuele un campo que el servidor
ignoraría en silencio.

### 10.septies Lo que el código enseñó — paso 3c (2026-08-13)

**33. El contrato destapó que la respuesta delataba el señuelo.** El primer borrador de
`POST auth/register` devolvía el perfil cuando creaba cuenta y un cuerpo vacío cuando fingía
(honeypot relleno o límite por correo agotado). Un bot distingue esas dos respuestas de un vistazo,
así que el honeypot dejaba de servir para lo único que sirve. Lo señaló el test de contrato —el
esquema declaraba un objeto y llegaba un array vacío—, no una revisión. **Ahora el 201 va SIEMPRE
sin cuerpo**: indistinguible por construcción, y quien se registra en la compra pide su perfil a
`GET /me`, que ya tiene sesión. Lección general: cuando una respuesta tiene que ser
*indistinguible*, la forma del cuerpo es parte del secreto, no solo el status.

**34. La misma trampa de sesión, por segunda vez y peor.** El paso 3b ya había encontrado que sin
`Origin`/`Referer` *stateful* no hay sesión y `$request->session()` revienta con 500. Volvió a
aparecer en el alta dentro de la compra, y ahí era peor: **la cuenta se creaba y el 500 llegaba
después**, dejando al cliente sin saber si tenía cuenta. Las dos veces lo encontró un `curl` contra
el servidor real y ninguna la suite, porque los tests mandan siempre el encabezado y ejercitan la
rama buena. La comprobación vive ahora en un solo sitio (`Http\Api\Concerns\RequiresStatefulSession`)
con su porqué, y hay test para las dos ramas. **Regla para los pasos que quedan**: todo endpoint que
toque `session()` necesita esa guarda, y el `curl` sin encabezados es el que la encuentra.

**35. Dos políticas de enumeración distintas, y las dos correctas.** El alta DICE que un correo ya
existe (decisión de producto de la clienta: prima la conversión) y la recuperación de contraseña
NO dice nada (`SEC-06` estricto). Parece incoherente y no lo es: en el registro hay alguien
intentando comprar y esconderlo cuesta la venta; en la recuperación no se gana nada diciéndolo y sí
se regala un oráculo. Lo que importa es que la decisión sea **explícita y la misma en las dos
puertas** — replicar en la API la política de la web fue decisión del owner, precisamente para que
la SPA de Fase 4 no cambie el comportamiento visible sin que nadie lo haya pedido. Lo que acota la
enumeración masiva del alta es el límite de tres por hora y correo, no el mensaje.

**36. Extraer defensa en capas obliga a mantener su ORDEN.** Las cuatro del alta —señuelo, límite
por IP, límite por correo, anti-bot— solo funcionan en ese orden: validar la forma antes que el
señuelo le diría al bot qué campos están mal antes de que la trampa actúe, y comprobar el correo
antes que los límites convertiría el endpoint en el oráculo que los límites acotan. En el
componente web ese orden estaba implícito en la secuencia del método; al extraerlo hubo que
escribirlo como decisión, y el llamante solo valida la forma **cuando el señuelo ya ha dicho que
hay una persona detrás**.

**37. Un tipo de retorno demasiado estrecho rompe una respuesta sin cuerpo.** `response()->noContent()`
devuelve `Illuminate\Http\Response`, no `JsonResponse`, así que un controlador declarado
`: JsonResponse` revienta con `TypeError` — un 500 por una firma, no por la lógica. Aparece en
cuanto un endpoint deja de devolver cuerpo, que es lo correcto para 201/202/204.
