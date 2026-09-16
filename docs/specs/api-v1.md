# [SPEC] API v1 (Fase 3)

> Estado: ✅ **v2 · LOS 6 PASOS DEL §9 TERMINADOS** (2026-08-13). Queda un ítem de la fase fuera
> de este corte: la abstracción `PaymentProvider` (ver `00-REFACTOR`) ·
> Última actualización: 2026-08-13 · Decisiones asociadas: `DECISIONES #21` (dependencias),
> `#22` (árbol saneado), `#23` (anti-bot), **`#24`** (paso 0), **`#26`** (paso 1a), **`#27`**
> (paso 1b), **`#28`** (paso 2), **`#29`**–**`#31`** (paso 3), **`#32`** (paso 4a), **`#33`** (paso 4b), **`#34`** (paso 4c), **`#35`** (paso 4d) y **`#36`** (paso 5).
> Antecedentes: `DECISIONES #3` (el sidebar se rehace como SPA contra la API) y `#4` (API-first).
> Qué cambió respecto a la v1 y por qué: **§8** · Corte en pasos y su avance: **§9** ·
> **Lo que el código enseñó al implementar: §10 → §10.sexdecies** — ochenta y siete puntos
> medidos. Son la entrada obligatoria para quien construya la SPA de Fase 4 sobre esta API.
> ⚠️ **§1 es el diagnóstico PREVIO** (2026-08-13, antes de tocar nada): describe un repo sin API y
> se conserva como registro del análisis, no como foto del código de hoy.

## §0 · Antes de tocar

- **Fase 3 ejecutada** (v2, los seis pasos de §9). El ítem que quedó fuera —la emisión de tokens Bearer— es
  la F4 del programa «producto e instancias» (`specs/producto-e-instancias.md` §4.5).
- **El contrato `openapi/v1.yaml` MANDA sobre el código** (`#21`): los esquemas son `additionalProperties:
  false`, así que un campo de más es un 422 por esquema; el contrato se cambia antes que el código.
- **§10 → §10.sexdecies son 87 puntos que el código enseñó al implementar**: entrada obligatoria antes de tocar
  la API o construir sobre ella. **§1 es el diagnóstico PREVIO**, no una foto del código de hoy.
- **Los controladores de checkout están en el `CRITICAL_RE` por NOMBRE** (`Order|Payment|Checkout|Quote|
  Availability|Cart`): un endpoint que crea pedidos, inicia pagos o calcula disponibilidad orquesta las
  carreras que la suite SQLite no ve → `VERIFY_CONC=1`. `ApiBoundariesTest` prohíbe que la lógica nazca en
  el controlador; el ORDEN de la secuencia lo garantiza `checkout-orquestado.md`.
- Un único esquema de seguridad hoy (la cookie de sesión): los tokens y la API pública de lectura para la
  landing (horario, legales, normas, prueba social) nacen en F4 y F5 del programa.
- Anexo al final con la fila del enrutador.

## 1. Contexto y problema

**Medido el 2026-08-13** (los recuentos de la v1 eran erróneos; corregidos aquí — §8):
- **No existe API.** `composer.json` declara **6 paquetes** (más `php`) y ninguno es Sanctum; no
  hay fichero de rutas de API —`routes/api.php` (futuro)— ni `api:` en `withRouting()` de
  `bootstrap/app.php`. Las **37** declaraciones `Route::` de `routes/web.php` son server-rendered.
- **El sistema de reservas vive dentro de una clase de UI.** El componente Livewire de compra
  `Tickets\Purchase` (2.049 líneas, **46** métodos públicos; retirado el 2026-08-21 en 4.7·2b·3,
  mucho después de este diagnóstico). La v1 afirmó que «solo cuatro son operaciones de
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
  el reintento web *(retirado en `#120(u)`)* compartían casi línea a línea el UPDATE atómico de `expires_at`, el
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
| Cuenta | ✅ **TODA abierta** (tanda 2 del área de cliente, `#120(n)`–`(s)`): `GET/PATCH me` · `PUT me/password` · `POST me/sessions/revoke-others` · `DELETE me` (art. 17: **anonimiza**, no borra) · `GET me/export` (art. 20) · `DELETE me/pending-email` · `POST me/pending-email/resend`. ⚠️ Los nombres de esta fila eran los PREVISTOS en Fase 3 y dos cambiaron al construirlos —era `PATCH me/password` y `POST me/logout-others`—: manda `openapi/v1.yaml`. ▶ **Fase 6 · waiver** (`DECISIONES #163`, `specs/waiver-probatorio.md` §9.8): `GET legal/waiver` (público: el texto firmable vigente con su id) · `GET/POST me/waiver` (estado propio · aceptar con el id servido; `409 waiver_document_stale` si el texto cambió, `409 waiver_not_internal` fuera del modo interno) · `GET me/waiver/{signature}/pdf` (el PDF propio, auditado); el alta acepta `accept_waiver` + `waiver_document_id`, y `me/account-context` publica `waiver` (§10.septdecies) | dueño; reconfirmación de contraseña **con limitador** por (titular, IP) | `Identity\Services\AccountCredentials`/`AccountProfile`/`AccountPrivacy` · `User::anonymize()` · `Booking\Contracts\CustomerOrderHistory` |
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
   Booking consumido por `Purchase` y el reintento web, los dos ya retirados, y la API.
2. **Ida del pago** → `PaymentInitiator`, entonces duplicada entre `Purchase` y el reintento web.
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
   extraídas de `Purchase` y del reintento web (§4.6.1–2), con los dos verificadores de
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
   estados reales + historia de retorno móvil. **Partido en cuatro** por el mismo motivo que los
   pasos 1 y 3 —trabajos de naturaleza distinta en un solo diff son irrevisables— y porque este es
   el paso de más riesgo de la fase:
   - ✅ **4a** (2026-08-13, `DECISIONES #32`): **tarificación de cesta** (§4.6.4).
     `Booking\Contracts\CartPricing` + `POST orders/quote`. La web consume el contrato desde el mismo
     commit. **Lo que el código enseñó: §10.octies.**
   - ✅ **4b** (2026-08-13, `DECISIONES #33`): **disponibilidad con la cesta**.
     `Booking\Contracts\AvailabilityOffer` + `GET availability/{product}/dates` y
     `POST availability/{product}/times`. La web consume el contrato desde el mismo commit. **Lo que
     el código enseñó: §10.nonies.**
   - ✅ **4c** (2026-08-13, `DECISIONES #34`): **creación y cobro** — `POST orders`,
     `GET orders/{code}` y `POST orders/{code}/payment`, sobre `ReservationAdmission` +
     `OrderCreator` + `PaymentInitiator`, que ya existían. Nacen además los **códigos de error de
     negocio** con su mapa exhaustivo por test. **Lo que el código enseñó: §10.decies.**
   - ✅ **4d** (2026-08-13, `DECISIONES #35`): **desenlace** —
     `GET orders/{code}/payment-status` con los DOS ejes de estado y el motivo del rechazo, el
     token de retorno que se quemaba antes de validar la titularidad, y el retorno móvil resuelto
     por DECLARACIÓN en el propio contrato. **Lo que el código enseñó: §10.undecies.**
5. ✅ **Post-form migrado** (2026-08-13, `DECISIONES #36`): `GET`/`PUT reservations/{id}/guest-form`.
   El canje resultó no necesitar credencial nueva —basta firmar la URL de la API con la MISMA
   caducidad y entregarla a quien ya demostró acceso—, y de paso la escalada 403→410→404 y la
   persistencia quedaron con un solo dueño. **Lo que el código enseñó: §10.duodecies.**

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

### 10.octies Lo que el código enseñó — paso 4a (2026-08-13)

**38. «Espejo exacto» estaba escrito en un comentario, y eso no es una verificación.**
`Purchase::cartDepositCents()` se documentaba como «ESPEJO EXACTO de lo que
`OrderCreator`/`Order::onlineDueCents()` cobrarán para ESTA misma cesta (canario anti doble-fuente)»
—y lo era—, pero **ningún test comparaba las dos cifras**: si alguien tocaba una de las dos
aritméticas, el cliente veía un importe en la pantalla de pago y otro en el TPV, y la suite seguía
verde. Ahora `CartPricerTest` crea el pedido de verdad con la misma cesta y compara `totalCents` con
`Order::total` y `onlineAmountCents` con `onlineDueCents()`. **Lección para los pasos que quedan**:
un comentario que declara una equivalencia es una petición de test, no una prueba de que exista.

**39. La aritmética no estaba duplicada: estaba TRIPLICADA, y una de las copias no se veía.** El
spec §4.6.4 mandaba extraer `cartLines`. Al medirlo eran tres métodos del mismo componente
—`cartLines()`, `cartTotalCents()` y `cartDepositCents()`— recorriendo la cesta por separado con las
mismas reglas, y una CUARTA copia en el panel (`CreateManualOrderPage::estimateLineCents()` +
`cartOnlineDueCents()`), que precalcula los importes al añadir la línea en vez de al pintarla.
**Se comprobó caso a caso y hoy coinciden** en las dos ramas de la Opción A (#225), así que no había
bug que arreglar; lo que había era la misma situación del paso 2, cuando dos copias que «hacían lo
mismo» resultaron aplicar políticas distintas. La del panel **no se unificó en este paso a
propósito**: calcula al añadir y no al pintar, y cambiarlo alteraría su conducta ante un cambio de
precio a media cesta. Queda medida en `DEUDA.md`, que es donde se ve, en vez de en la cabeza de
quien la encontró.

**40. Extraer los tres métodos en uno redujo el coste a un tercio, sin proponérselo.** Cada render
llamaba a los tres, y cada uno resolvía otra vez el precio de cada línea y de cada complemento,
porque `RateResolver::priceCents()` **consulta por llamada** (la trampa del punto 17, aquí en el
carrito). Con una sola pasada y la tarifa resuelta una vez por fecha, presupuestar doce líneas del
mismo día cuesta exactamente lo mismo que presupuestar una — fijado por pendiente en
`ApiOverheadTest`, no por techo.
⚠️ **Lo que NO se arregló, con su número**: cada complemento sigue costando **2 consultas** (1 → 8
consultas, 3 → 12, 6 → 18), porque `AddonResolver::resolve()` pide el precio de cada uno por
separado. Es código de dinero compartido con `OrderCreator`, que lo ejecuta **dentro de la
transacción que sostiene los locks de aforo**, así que ahí duele más que en un presupuesto. Tocarlo
exige su propio paso y los verificadores; mientras tanto hay un test que impide que EMPEORE.

**41. Resolver por adelantado lo que quizá no haga falta rompe pantallas que funcionaban.** El
primer borrador resolvía la tarifa de todas las fechas de la cesta —más hoy— antes de recorrerla.
`RateResolver::for()` termina en un `firstOrFail()`, así que en una instalación **sin ninguna tarifa
configurada** eso lanza: ocho tests de la compra web se pusieron en rojo con
`ModelNotFoundException` por una cesta vacía. La resolución es perezosa y por fecha necesitada. La
regla que sale de aquí: **en un servicio que también corre con la base de datos a medio configurar,
lo que se precalcula tiene que ser exactamente lo que se va a usar.**

**42. Un memo `static` dentro de un método es una fuga entre tests esperando a ocurrir.** La versión
inicial memoizaba la tarifa con `static $memo = []` dentro del método: en PHP eso sobrevive a la
petición, al objeto y al test siguiente, que es exactamente lo que `SUITE-02` documenta para
`Setting::$memo` (31 fallos fantasma en el origen). Se cambió por una variable local a la llamada
antes de que costara nada. **Y el memo del lado del consumidor no puede ser por petición**: en
Livewire, `addToCart()` cambia la cesta y `render()` corre después, así que un `once()` habría
devuelto los importes de la cesta ANTERIOR — un error de dinero silencioso. Se ata al CONTENIDO de
la cesta, no a la petición.

**43. Los recursos que se devuelven solos necesitan `$wrap = null`, y el contrato es quien lo dice.**
`JsonResource` envuelve en `data` por defecto, así que el presupuesto salía como
`{"data": {...}}` contra un contrato que declara el recurso en la raíz (§4.3). Lo cazó el test del
endpoint en su primera ejecución, no una revisión. Los recursos de LISTA no lo necesitan porque pasan
por `ApiCollection`, que construye la respuesta él mismo: la distinción es «lo devuelve el
controlador» vs «lo serializa la colección».

**44. Sanear en silencio es correcto para una sesión y pésimo para una API.** `Cart::sanitize()`
descarta las líneas que no encajan —lo que debe hacer con una cesta de sesión que puede venir de una
versión anterior del flujo—, pero aplicado a un cuerpo de petición significaría devolverle a un
cliente un presupuesto con menos líneas sin decirle por qué. El endpoint valida la FORMA con reglas
y responde 422 nombrando el campo (`items.0.date`); el saneado del dominio se queda debajo como
defensa. La forma de la cesta en la API vive en un solo sitio (`Http\Api\CartPayload`) porque la
comparten los tres endpoints del paso 4 — es la misma razón por la que `Cart::sanitize()` existe en
el lado del dominio.

**45. Cuando una respuesta puede llevar PII, la decisión es qué NO devolver.** El presupuesto acepta
`event_data` —para que el mismo cuerpo sirva luego para crear el pedido— y **no lo devuelve**: son
las respuestas del formulario del pack, que en el sector de origen incluyen el nombre de un menor y
a veces sus alergias (dato de salud, `RGPD` §3), y este endpoint es PÚBLICO. El cliente ya tiene
esos datos —acaba de enviarlos— y las etiquetas para pintarlos están en `GET catalog/products/{id}`,
así que devolverlos no le aporta nada y sí abre una superficie. Es el hermano del punto 33: allí la
forma del cuerpo era parte del secreto; aquí lo es la ausencia de un campo.

### 10.nonies Lo que el código enseñó — paso 4b (2026-08-13)

**46. La fuente única calculaba el número correcto y publicaba el otro.** `SlotOffer::offerableTimes()`
devolvía por franja un `available` que, en un PACK, son las plazas que le quedan al cupo —sin topar
por el `max_qty` del pack—, mientras que el máximo que se puede CONTRATAR sí está topado. Los dos
números se calculaban ahí dentro y solo salía el primero; el segundo lo recomputaba la web por su
cuenta (`Purchase::maxQty()`). Medido contra la instalación de demostración: un cumpleaños con cupo
de 60 invitados y máximo de 20 publica `available: 60` y `max_quantity: 20`. Un cliente que hubiera
construido su selector de cantidad sobre `available` habría dejado pedir 60 invitados que el
checkout rechaza. **Lección**: cuando un método interno calcula dos magnitudes y expone una, la que
se queda dentro es justo la que el siguiente consumidor va a recalcular mal.

**47. Un endpoint de disponibilidad sin la cesta no es un endpoint incompleto: es uno incorrecto.**
`offerableTimes()` descuenta los ocupantes provisionales de la propia cesta, así que sin ellos la
segunda línea sobre la misma franja ve libres las plazas que la primera ya retiene. Por eso las
horas van por `POST` con la cesta en el cuerpo y las fechas por `GET` sin ella (un día se ofrece si
tiene franjas; evaluar el cupo de todas las horas de todos los días del horizonte para pintar un
calendario sería absurdo). Verificado con `curl` contra el servidor real: 40 plazas sin cesta, 35
con una línea de 5 en esa franja, y las demás intactas.

**48. Los dos aforos son pools independientes, y una derivación descuidada los mezcla.** Las
entradas ocupan PLAZAS a lo largo de su duración; los packs ocupan CUPO en su propio pool, contando
montaje y limpieza (#82). La derivación cesta → ocupantes produce por eso DOS listas con forma
distinta, y juntarlas restaría plazas de entrada por un cumpleaños. Vivía en la clase de UI, donde
nadie podía probarla; ahora tiene test propio.

**49. Resolver una tarifa por día costaba una consulta por celda del calendario.** El presupuesto
por PENDIENTE lo destapó al primer intento: `RateResolver::for()` consulta `special_dates` en cada
llamada, así que un horizonte de 20 días costaba 18 consultas más que uno de 2. **No se arregló
duplicando la regla en el read-model** —dos resoluciones de tarifa que puedan divergir son dos
precios para el mismo día— sino añadiendo `RateResolver::forDates()`, un lote en la misma clase que
tiene la regla, con `for()` delegando en él. El coste es plano y la regla sigue siendo una.

**50. Extraer la disponibilidad sacó `RateResolver` entero de la capa de UI, y eso no estaba
previsto.** Al mover el calendario al contrato, el único uso que quedaba era `dayPriceCents()`, que
resolvía la tarifa del día elegido por su cuenta —pudiendo, en teoría, no coincidir con el precio
pintado en su propia celda—. Ahora lee de la misma oferta. `Livewire\Tickets\Purchase` ya no importa
ningún servicio de precio ni de aforo: solo contratos. **Señal general**: cuando una extracción deja
una dependencia con un solo uso, ese uso suele ser el que faltaba por extraer.

**51. El gate de concurrencia llegaba tarde a su propia lista.** `CriticalPathGateTest` ya trataba
`SlotOffer` como núcleo —un controlador de API que lo tocara tenía que casar con `CRITICAL_RE`— pero
el fichero en sí no estaba en el patrón: se podía cambiar la fuente única de oferta y empujar sin
correr un verificador. Y sí importa: `OrderCreator` llama a `SlotOffer::passesIntradayFloor()` como
backstop, así que un cambio suyo puede mover lo que `purchase:verify-oversell` comprueba. Añadido en
este paso. **Lección**: una lista de símbolos críticos y una lista de ficheros críticos que no se
comprueban la una contra la otra acaban diciendo cosas distintas.

### 10.decies Lo que el código enseñó — paso 4c (2026-08-13)

**52. El paso donde no había nada que extraer era el más peligroso, y por eso.** 4a y 4b sacaron
reglas de la capa de UI; 4c no saca ninguna —admisión, creación e ida del pago ya existían y estaban
verificadas— y aun así es el de más riesgo de la fase. Lo que aporta es la SECUENCIA, y una
secuencia no la protege ninguna guarda de arquitectura: un controlador que llama a los tres
servicios correctos **en el orden equivocado** pasa `ApiBoundariesTest` con nota (§10, punto 6). La
red tuvo que ser un test por cada punto del orden, y se comprobó que muerden mutando el código:
quitar el hold de `createPendingOrder()` deja 3 tests en rojo, no soltar el pedido tras un cobro
fallido deja 1, y cambiar `admitReservation()` por la variante que solo consulta deja 1.

**53. La orquestación se quedó en la capa de entrega, y la baseline de módulos es lo que lo decidió.**
La tentación era extraer «admitir → crear → abrir cobro» a un servicio de dominio para que la web y
la API compartieran la secuencia. Cruzaría Booking → Payments con una flecha de ORQUESTACIÓN, y la
baseline de `ModuleBoundariesTest` **solo encoge**: añadirle una entrada es la señal de que algo está
mal hecho. La capa de entrega es el *composition root* declarado desde Fase 2 · paso 3 y ahí la
composición entre módulos está sancionada. El arreglo de fondo ya tiene nombre en el backlog de la
fase —la abstracción `PaymentProvider`, que convertiría la ida del pago en un contrato como el del
reembolso— y meterla dentro de este paso habría mezclado dos trabajos en un diff. **Regla que sale
de aquí**: cuando la extracción «limpia» exige añadir una entrada a una baseline que solo encoge, la
extracción no es limpia; es otra tarea.

**54. Los códigos de error de negocio son doce, y agruparlos habría sido una trampa cómoda.**
`ReservationException` lanza doce claves distintas y la tentación era mapearlas a un
`reservation_failed` único. Añadir un código es evolutivo; **partir uno existente rompe a todo
cliente que se hubiera ramificado sobre él**, así que el código genérico habría sido cómodo hoy e
incompatible el día que alguien quisiera distinguir «agotado» —que se arregla refrescando la
disponibilidad— de «fuera de horario», que no. El mapa es exhaustivo **por test**: `ReservationErrorMapTest`
lee el dominio con el tokenizador buscando cada excepción lanzada, y también vigila la dirección
contraria (una entrada que ya nadie lanza es una rama muerta en el cliente).

**55. `params` no es decoración: sin él el mensaje miente.** El sobre lleva el `product` y el `when`
de la línea culpable porque el dominio los pone en `context` — verificado con `curl`: pedir 999
plazas devuelve `line_sold_out` con `params.product = "Jump · 1 hora"` y `params.when = "2026-08-14
10:00"`. Sin ellos, un cliente con varias líneas en la cesta no puede ni señalar cuál falló.

**56. El formulario de la pasarela no se llamaba como se llamaba.** `PaymentTicket::formData` decía
en su docblock que era «el conjunto de campos `<input>` que exige la pasarela», y no lo es: es el
payload crudo del proveedor, con la URL mezclada dentro y claves (`params`, `signature`) que **no**
son los nombres de los campos. Traducir de una forma a la otra era conocimiento que solo vivía en
las plantillas Blade, y un cliente de API no tiene plantilla donde mirarlo. Ahora `gatewayUrl()` y
`gatewayFields()` lo dicen una vez. **Y el contrato NO enumera los campos**: los declara como un mapa
opaco que el cliente reenvía sin tocar, que es lo que permitirá cambiar de proveedor sin romper a
nadie —y lo que evita que alguien «arregle» un campo firmado—.

**57. Un 502 que no se distinga de un 500 deja al cliente sin saber si tiene reserva.** Cuando la
pasarela no abre, la respuesta correcta no es un error genérico: el cliente necesita saber qué ha
pasado con su pedido, y la respuesta es distinta según el momento — en un PRIMER cobro el pedido se
suelta en el acto (no puede retener una plaza que nadie va a pagar) y hay que empezar de nuevo; en un
REINTENTO el pedido sigue vivo con su hold recién extendido y basta con volver a intentarlo. Mismo
código (`payment_unavailable`), dos consecuencias opuestas, las dos escritas en el contrato.

### 10.undecies Lo que el código enseñó — paso 4d (2026-08-13)

**58. Un solo eje de estado no distingue «rechazado» de «nadie lo ha intentado».** Un pedido cuya
tarjeta acaba de ser denegada y uno que nunca llegó a la pasarela son, mirando solo a
`Order.status`, exactamente lo mismo: `pending`. Ese matiz existía únicamente en la SESIÓN de la web
(`purchase.failed_code`), así que un cliente de API habría visto «pendiente» durante los quince
minutos de la retención y después «caducado» —**nunca «reintenta»**, teniendo el reintento
disponible desde el paso 4c—. De ahí los dos ejes: `order_status` (qué ha sido de la RESERVA) y
`payment_status` (qué ha sido del último INTENTO). Verificado en vivo: el mismo endpoint devuelve
`pending/pending` antes del rechazo y `pending/failed` + `card_expired` + `can_be_retried: true`
después.

**59. El motivo del rechazo se publica como CÓDIGO y como texto, y hacían falta los dos.** El código
(`card_expired`, `bank_denied`, `user_cancelled`…) es lo que un cliente PROGRAMA —una tarjeta
caducada invita a probar otra, una denegación del banco invita a llamar al banco— y el texto es lo
que muestra cuando no sabe hacer nada mejor. Con solo el texto, todo cliente tendría que comparar
cadenas traducidas; con solo el código, tendría que mantener sus propias traducciones de un
protocolo ajeno. Es la misma distinción que el sobre de error hace entre `code` y `message`.

**60. Un rechazo anterior no se anuncia mientras hay otro cobro en curso.** Tras reintentar,
`declined_reason` vuelve a `null`: enseñar el motivo del intento anterior le diría al cliente que su
tarjeta ha fallado cuando en realidad está esperando respuesta. La regla vive en el dominio
(`Order::declinedResponseCode()` solo responde si el ÚLTIMO intento es el fallido), no en el
serializador, porque es una decisión sobre qué es verdad y no sobre cómo se pinta.

**61. Consumir un token antes de validarlo protege de lo que no era el riesgo.** El token one-shot
de la vuelta se leía con `Cache::pull` en la PRIMERA línea y se validaba después, buscando que uno
capturado no fuera reutilizable. Pero el token va atado a su `user_id`: un tercero **nunca pudo
usarlo — solo QUEMARLO**. Bastaba abrir la URL de la vuelta sin sesión para que el cliente legítimo
perdiera su confirmación y se encontrara el carrito vacío después de haber pagado. Ahora se mira, se
valida y solo entonces se consume; la ventana de reutilización sigue cerrada (el `pull` es atómico,
TTL de 5 minutos). **Lección general**: cuando una defensa se justifica con «así reduce la ventana
de ataque», conviene comprobar quién puede ejercer el ataque — aquí nadie podía, y el coste lo pagaba
la víctima.

**62. Los dos ejes se derivan de la relación ya cargada, no de dos consultas más.** `paymentStatus()`
y `canBeRetried()` leen `payments` en memoria y el endpoint la precarga, porque este es el único de
toda la API que se llama EN BUCLE. Un `lastFailedPayment()` por vuelta habría sido una consulta por
segundo y por cliente esperando en la pantalla de pago.

**63. El retorno móvil se resuelve DECLARÁNDOLO, no construyéndolo.** La vuelta de la pasarela es una
redirección de navegador y `DS_MERCHANT_URLOK` no admite parámetros donde colgar un `state`, así que
hoy no hay deep link que disparar. En vez de inventar una página puente para un lector que todavía no
existe (Fase 6), la decisión es explícita y viaja **en el propio contrato OpenAPI**, que es donde la
leerá quien construya la app: el cliente nativo abre la pasarela en un navegador del sistema y
averigua el desenlace sondeando. ⚠️ Con una condición que es prerequisito DURO de instalación: sin
`redsys_merchant_url` (notificación server-to-server) y con terminal data-less, el único camino a
`paid` es la vuelta del navegador —que en nativo no ocurre— y el pedido caducaría con la tarjeta ya
cobrada (`PAY-02`).

### 10.duodecies Lo que el código enseñó — paso 5 (2026-08-13)

**64. La firma no viaja entre rutas, y eso se puede enseñar en una línea.** Todo el paso 5 existía
por una frase del spec: «la firma de Laravel cubre la URL exacta, así que no autoriza
`PUT /api/v1/...`». Comprobado contra el servidor real, la misma `signature` devuelve **403 en la
API y 200 en su ruta web**. Tener ese par de números vale más que el párrafo: el canje deja de ser
una precaución teórica y pasa a ser lo único que hace funcionar al segundo consumidor.

**65. El canje no necesitaba un almacén de credenciales: necesitaba firmar la otra URL.** El primer
instinto —emitir un token de vida corta, guardarlo en cache como el de la vuelta de Redsys— habría
añadido un emisor de credenciales justo cuando la fase decidió aplazar el de Bearer a Fase 6. La
salida era más simple y no relaja nada: firmar también las rutas de la API **con la misma
caducidad** y entregárselas a quien ya demostró acceso. Mismo alcance (una reserva), misma vida,
misma prueba. **Regla general**: antes de inventar una credencial nueva, mira si la que ya existe
puede emitirse para el destino nuevo.

**66. Lo que se comparte entre superficies no eran tres líneas: era el ORDEN.** La escalada
403 → 410 → 404 parece una cadena de `abort()` intercambiables y no lo es: autorizar ANTES de
comprobar elegibilidad es lo que impide deducir por el código de estado si una reserva existe, si
está pagada o si su titular ejerció la supresión. Extraerla a un solo sitio no ahorró código
—ahorró que la segunda copia se escribiera en otro orden—, y hay test por mutación de que invertirlo
cae.

**67. El *route model binding* implícito rompe la no-enumeración, y es invisible.** Con binding, un
id inexistente responde 404 ANTES de que el controlador ejecute nada: frente al 403 de uno
existente, eso ES el oráculo que la escalada quería evitar. La API resuelve la reserva a mano por
eso. ⚠️ **La página web sigue usando binding implícito y por tanto conserva esa diferencia**: no se
tocó en este paso porque cambiarla altera la conducta de una superficie en producción, y queda
anotado en `DEUDA.md`.

**68. La guarda de frontera volvió a señalar el sitio correcto, por segunda vez.** El primer borrador
del `PUT` guardaba con `->save()` desde el controlador y `ApiBoundariesTest` lo marcó. No era un
falso positivo: quien decide qué se persiste de un formulario con datos de MENORES no puede ser la
capa HTTP. La corrección fue bajar la operación entera al dominio (`OrderItem::submitGuestForm()`) y
que la web la consuma también — el mismo desenlace que el punto 31 del paso 3b, y por el mismo
motivo: **cuando una guarda de arquitectura protesta por código nuevo, la primera hipótesis es que
el sitio correcto ya está decidido.**

**69. Un formulario data-driven tiene que viajar con su esquema.** Las columnas por invitado las
configura cada instalación desde el panel; un cliente que las llevara quemadas dejaría de funcionar
en cuanto alguien añadiera una. Van con la etiqueta ya resuelta al idioma —los textos viven en BD,
no en `lang/`— y su `required`. Es la misma decisión que el catálogo tomó en el paso 1b, y la que
convierte a la API en algo que una app puede consumir sin desplegarse cada vez que el operador
cambia una columna.

### 10.terdecies Lo que el código enseñó — el CIERRE del checkout (2026-08-13)

> Diseño completo en `docs/specs/checkout-orquestado.md`; la decisión, en `DECISIONES #37`. Aquí
> solo lo que cambia el trabajo de quien toque la API a partir de ahora.

**70. Los dos controladores del pedido ya NO llevan la secuencia, y eso cambia dónde mirar.**
`OrdersController::store()` y `OrderPaymentController::store()` piden `start()`/`retry()` a
`Booking\Contracts\ReservationCheckout` y traducen el resultado. Si vas a tocar el orden de
«admitir → crear → abrir cobro», el sitio es `Booking\Services\CheckoutOrchestrator` y su red es
`CheckoutOrchestratorTest`, no los tests de endpoint. Los cuatro tests de orden de
`Api\V1\OrdersTest` siguen ahí y siguen mordiendo —se comprobó por mutación **después** de migrar—,
pero ahora prueban que el endpoint pide la secuencia, no que la escriba bien.

**71. El `catch` de `PaymentInitiationException` en el controlador es obligatorio, no decorativo.**
El dominio compensa (suelta el pedido en un primer cobro, no lo toca en un reintento) y **re-lanza**;
la disculpa es de la capa de entrega. Como `ApiExceptionRenderer` solo conoce `ReservationException`,
un controlador que dejara de capturarla degradaría el **502 `payment_unavailable`** que el contrato
documenta a un 500 genérico, en silencio. Es el precio de partir compensación y presentación, y hay
que saberlo al escribir el siguiente endpoint que abra un cobro.

**72. Un contrato nuevo puede sacar a un controlador del gate de concurrencia sin que se note.**
`CriticalPathGateTest` vigilaba que ningún controlador de API tocara el núcleo fuera de
`CRITICAL_RE` buscando los símbolos del núcleo (`OrderCreator`, `PaymentInitiator`…). Desde el
cierre, un endpoint llega al dinero **sin nombrar ninguno**: le basta inyectar `ReservationCheckout`.
Por eso ese símbolo entró en `CRITICAL_SYMBOLS`. Lección general: cuando una capacidad pasa a vivir
tras un contrato, hay que revisar las guardas que la buscaban por el nombre de su implementación.

**73. Un criterio de éxito medido con `grep` no es una guarda.** El diseño nació diciendo «tras esto,
`grep PaymentInitiator app/Http` da 0». Se cumple el día del commit y caduca al siguiente, que es
exactamente el problema que el trabajo venía a resolver — y peor: tras el refactor, una superficie
nueva tenía un camino MÁS cómodo para reescribir la secuencia (inyectar el puerto directamente), y
ninguna guarda lo veía, porque `ModuleBoundariesTest` exime la capa de entrega entera y permite
cualquier `Contracts`, y `ApiBoundariesTest` no prohíbe `open()`/`reopen()`. La versión falsable es
`CheckoutSequenceTest`. **Regla**: si un criterio de éxito se puede escribir como `grep`, casi
siempre se puede escribir como test — y entonces hay que escribirlo como test.

### 10.quaterdecies Lo que el código enseñó — Fase 4 · paso 4.0b·4b (2026-08-14)

Cuatro puntos del endpoint que saca la PII del pedido (`GET orders/{code}/event-data`,
`DECISIONES #39`). El primero es el que generaliza.

**74. La guarda de un endpoint de minimización va en los endpoints que NO deben llevar el dato.**
Separar las respuestas del pack solo significa algo si el pedido no las lleva, y probar que el
endpoint nuevo devuelve lo suyo no prueba nada de eso. La guarda útil recorre `me/orders` y
`GET orders/{code}` y afirma que el valor **no aparece en el cuerpo entero de la respuesta** — no
que falte un campo con cierto nombre: quien «ahorre una petición» mañana lo llamará de otra forma, y
la prueba tiene que caer igual. Regla general: **una decisión de minimización se hace falsable en el
sitio del que se quitó el dato, no en el que se puso.**

**75. Una columna que mezcla dos fases obliga a decidir la fase EN CADA lector.** `event_data` guarda
juntas las respuestas de la reserva y las del post-form, así que «devolver `event_data`» no es una
operación con un solo significado. `GuestFormResource` ya acotaba a `postform`; este endpoint acota a
`booking`, y la simetría solo es visible si se lee el otro lector. Cuando una columna carga dos
conceptos, **el filtro es parte del contrato de cada consumidor**, no un detalle del serializador.

**76. Un filtro por fase descarta las claves huérfanas, y eso es una decisión, no un efecto.** Si el
esquema del pack se editó tras la compra, la respuesta a un campo retirado no tiene `stage` — así que
ninguna consulta «solo `booking`» puede incluirla. Conviene decirlo en el contrato: el cliente que ve
menos respuestas de las que el operador ve en la hoja de sala está viendo la conducta correcta.

**77. La tercera copia se detecta buscando el dato, no la función.** Emparejar respuesta con etiqueta
estaba en `Purchase::resolveEventData()` (privada) y en `ReservationSlip::eventDataRows()` (otro
módulo, otro nombre, otra forma). Ninguna guarda de arquitectura las relaciona y ningún `grep` por
nombre las encuentra: aparecieron al buscar **quién lee `event_data`**. Antes de escribir un
serializador nuevo, buscar los LECTORES de la columna sale más barato que buscar el método.

### 10.quindecies Lo que el código enseñó — Fase 4 · paso 4.0b·6 (2026-08-14)

Tres puntos de `POST cart/validate-line`, el endpoint que sustituye a transcribir reglas de servidor
al cliente (`DECISIONES #40`). El primero es el que más lejos llega.

**78. Extraer una regla y no hacer que el consumidor viejo la use no es extraer: es copiar.** Aquí
la compra web pasó a pedir el veredicto en el mismo paso, y esa delegación encontró en el primer
intento un fallo que ninguna lectura del diff habría visto: `Cart::sanitize()` fuerza `max(1, qty)`
—correcto para una cesta guardada, donde un 0 es corrupción— y aplicado a una línea CANDIDATA
convertía «todavía no he elegido cuántos» en un 1. La regla general: **cuando saques una regla de
una superficie viva, hazla consumir la extracción en el mismo commit**; su suite es la única prueba
de que lo extraído dice lo mismo que decía.

**79. Un validador previo tiene que validar contra lo que el juez final mirará, no contra lo que
mira la interfaz de la que salió.** La web comprobaba el aforo de la franja y le bastaba, porque su
hora venía siempre de la lista ofrecida. Un cliente de API manda la hora que quiera, y el método de
aforo responde de una franja concreta **aunque no se ofrezca** —ignora día pasado, corte intradía,
ventana del producto y antelación mínima—. Copiar la comprobación tal cual habría dado por buenas
líneas que el checkout rechaza: **un endpoint de validación que miente es peor que no tenerlo.**

**80. Un veredicto no necesita republicar el contexto que otro endpoint ya publica.** La tentación
era devolver con cada problema el mínimo del producto, el tope de la cesta y la etiqueta del campo.
Los tres ya viajan en `catalog/products/{id}` y en `config`, así que repetirlos crea un segundo sitio
del que leer el mismo número — y como sería un mapa libre, obligaría además a relajar
`additionalProperties: false` justo en el esquema recién nacido. El dominio sí los lleva, porque no
sabe quién le pregunta; la capa de entrega es la que puede decidir no reenviarlos.

### 10.sexdecies Lo que el código enseñó — Fase 4 · paso 4.0b·5 (2026-08-14)

Tres puntos del endpoint de complementos, que cerró los seis huecos del paso (`DECISIONES #41`).

**81. «Extraer» y «publicar» son operaciones distintas, y confundirlas es lo que hace peligroso un
paso.** El anterior (4.0b·6) sacó una regla de dentro de un componente Livewire, y no hacer que la
web la consumiera habría sido copiarla. Éste parecía el mismo trabajo —y estaba declarado como el más
arriesgado por eso— pero la regla **ya vivía en el dominio y las dos superficies ya la compartían**:
lo único que faltaba era exponerla con DTOs. Hacer pasar además al sidebar por esos DTOs no retiraba
ninguna deuda y sí movía la plantilla del paso con más clics del embudo. **La pregunta que decide no
es «¿debería la web usar el contrato?», sino «¿hay dos copias?»**: si no las hay, tocar la superficie
viva es riesgo sin contrapartida.

**82. Juntar dos operaciones en una respuesta no siempre cuesta más: mídelo.** La intuición era que
resolver los complementos **y** tarificar la línea en la misma petición pagaría dos veces el N+1
conocido. Medido: son las mismas consultas que pedir las dos cosas por separado (9 + 5 con cuatro
complementos), con la mitad de viajes y la mitad de fichas de `throttle` —que aquí importa, porque el
cubo es de 60/min y **compartido** con disponibilidad, catálogo y presupuesto—. Lo que sí hay que
dejar fijado es la pendiente, para que nadie añada una tercera pasada sin verla.

**83. Un test de «cadena» puede pasar sin probar la cadena.** El de la poda de dependencias
(A→B→C) pasaba **igual con una poda de un solo nivel**, porque el orden natural de los complementos
ya la resolvía en una sola pasada: al llegar a la hoja, su requisito ya se había eliminado. Solo
invirtiendo las posiciones —la hoja se evalúa primero, cuando su requisito aún está en pie— el test
exige el punto fijo de verdad. **Cuando lo que se prueba es un algoritmo iterativo, el caso tiene que
forzar el orden que obliga a iterar**; si no, se está probando el orden, no el algoritmo. Lo destapó
la mutación, no la lectura del test.

**84. Un campo «de configuración» puede mentir con media configuración puesta.** `GET /config`
publicaba `turnstile_site_key` leyendo la clave pública, pero el anti-bot **solo está activo con las
DOS claves**: con una sola, la web no pinta el widget y `verify()` deja pasar el alta. El contrato ya
decía por escrito «`null` cuando la instalación no tiene anti-bot configurado» y la implementación no
lo cumplía, así que el primer cliente que leyera el campo para DECIDIR —el cajón SPA— habría pintado un
captcha que su propio servidor no comprueba. **Cuando un campo se publica para que alguien decida, su
valor tiene que ser el veredicto, no la materia prima**: ahora es `enabled() ? siteKey() : null`, y
«no nulo ⟺ el alta exige captcha» está escrito en el contrato.

**85. `ConvertEmptyStringsToNull` convierte `sometimes|string` en una trampa para el caso NORMAL.** El
señuelo anti-bot (`website`) viaja **siempre** desde un formulario, vacío en todo cliente legítimo. Ese
`""` llega como `null`, `sometimes` lo ve presente y `string` lo rechaza: el alta moría con un **422
sobre un campo que el usuario no ve**. Los tests del endpoint lo esquivaban por los dos únicos caminos
que existen —mandarlo relleno u omitirlo—, que es exactamente por qué duró hasta el primer cliente
real. **Todo campo opcional que un formulario emite vacío necesita `nullable`**, y el caso que lo
prueba tiene que mandar la cadena vacía, no omitir la clave.

**86. Dos puertas de la misma operación no comparten los mensajes de validación aunque compartan las
reglas.** `Auth\Register` declara `validationAttributes()` —los rótulos del formulario— y `messages()`
—el aviso propio de las casillas legales—; el controlador de la API no tenía ninguno de los dos. Con
las mismas reglas, la web decía «El campo **Nombre y apellidos** es obligatorio.» y la API «El campo
**name** es obligatorio.». Extraer el SERVICIO (`SelfSignup`) unificó la decisión, no la copia; la
capa de entrega mantiene lo suyo y **hay que igualarlo a mano**, o el cliente que pinta el mismo
formulario enseña un texto distinto según por qué puerta entró.

**87. Una respuesta deliberadamente ambigua obliga al cliente a una segunda pregunta, y eso es
correcto.** `POST auth/register` responde **201 sin cuerpo** tanto si creó la cuenta como si el señuelo
actuó —si distinguiera, el bot lo notaría de un vistazo—. La consecuencia es que el cliente no puede
saber a qué pantalla ir sin preguntar `GET /me`. No es un defecto del contrato: es el precio de que el
honeypot sirva, y el contrato ya lo dejaba escrito («quien se registra dentro de la compra ya queda
identificado y puede pedir su perfil»). ⚠️ Y el fallo de esa segunda pregunta **no** puede leerse como
«hay sesión»: llevaría al pago a quien no ha entrado.

### 10.septdecies Lo que el código enseñó — Fase 6 · el waiver por API (2026-08-26, `DECISIONES #163`)

**88. «El identificador de la versión que el servidor sirvió» es, por definición, el VIGENTE — y las
dos puertas lo dicen de forma distinta a propósito.** `WaiverAcceptance::currentDocument()` es la única
regla: el id tiene que ser del waiver y de la última versión publicada; si el texto se publicó de nuevo
entre servirlo y aceptarlo, se rechaza. En `POST /me/waiver` eso es un **`409 waiver_document_stale`**
—un estado, que el cliente resuelve volviendo a pedir `GET /legal/waiver`—; en `POST /auth/register` es
un **422 sobre `waiver_document_id`**, porque ahí el cliente pinta el aviso bajo la casilla, como con
cualquier otro campo. Y en el alta se comprueba **antes de crear la cuenta**: un rechazo después
dejaría al cliente sin saber si tiene cuenta (la misma lección que el punto 85 dio con la sesión).

**89. Una casilla opcional con dato obligatorio se expresa con `required_if_accepted`, no con
`required`.** `accept_waiver` es opt-in (desmarcada por defecto, §4.4: nada obliga a firmar en el alta),
pero marcada sin `waiver_document_id` no prueba nada y se rechaza con su propio mensaje. OpenAPI no
sabe decir «obligatorio si otro campo es `true`», así que los dos campos van a `OPTIONAL_BY_DESIGN` y
quien lo exige es el servidor — el mismo patrón que `ProfileUpdateRequest.current_password` (punto 61).

**90. La re-firma «en el siguiente momento natural» necesitaba un SITIO, y el sitio es
`me/account-context`.** Es lo que el cajón repinta al conseguir sesión sin recargar, así que es donde
viaja «hay que firmar» (`required`), «firmaste un texto anterior» (`outdated`) y qué texto
(`document_id`, el mismo id que `GET /legal/waiver`). Y por la regla de la semilla (`AccountContextSeed`:
se poda por cardinalidad, nunca por campo) viaja también en el HTML de cada página con sesión: cuatro
campos cortos, dentro del techo de 512 B que fija `SidebarMountTest`.

**91. El canal de una firma sale de CÓMO se autenticó la petición, no de un campo — ni de una
cabecera.** ⚠️ Corregido en `#174` (revisión `#169` §10.2·1): la primera versión miraba
`bearerToken()`, y una cabecera `Authorization: Bearer basura` junto a la cookie de sesión bastaba
para que la firma constara como `api` — el guard de Sanctum resuelve PRIMERO la sesión y ni valida el
token. Hoy: en `/me/waiver`, `currentAccessToken() instanceof PersonalAccessToken` → `api` (un token
personal REAL, la app nativa); la sesión deja un `TransientToken` → `web`. En el alta (anónima),
`hasSession()` → `web` (el cajón, por el grupo stateful) y sin sesión → `api`. Un campo `channel` en el
cuerpo sería un dato que el cliente declara sobre sí mismo, y el registro probatorio no debe llevar
nada que el cliente pueda inventar. ⚠️ **`Sanctum::actingAs()` en los tests deja un `TransientToken`**
—como la sesión—: para probar el canal `api` hay que emitir un token de verdad
(`$user->createToken('app')->plainTextToken` + `withToken()`), no una cabecera a mano.

**92. El PDF propio va por la API con `application/pdf` en el contrato y SIN `assertValidResponse`.**
El documento se sirve igual que al operador (mismo `WaiverProof`, misma vista), pero la ruta es de
`/api/v1` para que la app nativa lo descargue con su Bearer. Spectator no valida binarios; la prueba de
la respuesta es la cabecera `%PDF-`, el `content-type` y el `no-store` del grupo, como en la hoja de
reserva.

**93. El waiver se firma con el correo VERIFICADO — y el alta ya no firma: deja la aceptación pendiente.** `[DECIDIDO owner, 2026-08-26]` (spec §7·5, `#179`). `POST /auth/register` con `accept_waiver` guarda `users.waiver_pending_document_id` + `waiver_pending_channel` y responde 201 sin firmar; al abrir el enlace de verificación (`Verified`) un listener firma con el canal del alta —si el texto sigue vigente— o descarta la pendiente. `POST /me/waiver` con `email_verified_at = null` → `409 waiver_email_unverified`. La guarda vive en `WaiverSigner` (dominio), salvo firma declarada en mostrador. ⚠️ `accepted_at` es el momento de la verificación, no el del alta.

**94. El contrato dice «aceptado, pendiente de verificar»: `pending`** (`#183`, S-2). `WaiverStatus.pending` y
`account-context.waiver.pending` son `true` mientras la aceptación del alta espera al correo verificado:
`signed` es `false`, `required` sigue `true` y aceptar de nuevo responde `409 waiver_email_unverified`. El
cajón no lo pinta (una cuenta sin verificar no entra en Mi cuenta por web); es para el cliente nativo
(`DEUDA.md`). Precisión al punto 91 (S-4): «con sesión» lo decide que la petición se sirviera con la cookie
de la web, y eso lo decide el `Origin` (`ApiOrigin`) — es el diseño del canal `web`, no un hueco. Y desde
`#183` la firma que nace de la aceptación pendiente lleva **la IP y el navegador del momento de marcar la
casilla** (`waiver_pending_ip`/`_user_agent`), no los de la petición que verifica —que en pay-first es el
cobro, y puede ser la notificación S2S de Redsys— y se registra **tras el commit** de quien emitió `Verified`.
### 10.octodecies Lo que el código enseñó — el LIBRO por la API (2026-09-01, `DECISIONES #310`)

**94. `Ledger` cambia de FORMA, es incompatible a propósito, y publica HECHOS con fecha en vez de
canales ya sumados.** Hasta la T3·1 del libro (`specs/desglose-libro.md` §6.3) el esquema `Ledger` eran
dos ejes —`invoiced_cents` · `deposit_cents` · `gate_remainder_cents` · `refund_pending_cents` ·
`gate_lines[]` · `in_favour_hint`—: números que el cliente tenía que volver a relacionar para saber
qué había pasado. Desde la T3·1 es el libro de `DECISIONES #305`: `total_cents` + `paid_cents` +
`balance{kind, cents, rest_at_park_cents}` + `movements[]` (nacimiento · cambios · mixto · cancelación
· cortesía, cada uno con su signo y su fecha) + `settlements[]` (cobros · devoluciones · liquidado en el
parque, con `status` y `method`) + `has_deposit` + `is_consistent` + `note`. Se rompe el contrato **sin
versión nueva** por tres hechos: 0 LIVE / 0 PRODUCCIÓN, el único consumidor (el cajón) cambia en el
mismo commit, y la app móvil no existe todavía. ⚠️ `LedgerResource` **no compone nada**: transcribe
`Booking\Services\OrderBook` campo a campo, y `MeOrdersFinancialsTest` lo asevera comparando la
respuesta con el dominio en cada escenario — la mutación que publica el total como «pagado» tumba
cuatro casos.

**95. `balance.kind` lo decide el SERVIDOR, y `cents` lleva el signo de la clase.** `pay_at_park` y
`pay_online` positivos; `refund_at_park` y `refund_pending` negativos; `settled`, `expired` y
`under_review` a 0. Un cliente que dedujera la clase del signo confundiría «a devolver en el parque» con
«pendiente de devolución» (el mismo número, según haya visita por delante o no). Y `is_consistent =
false` **no es un error de la API**: los movimientos y las liquidaciones viajan igual —son hechos—, lo
que cambia es lo que se pinta (el cajón enseña el Total, los cobros y `note`; la asimetría de `#132`),
y el servidor deja rastro (`ledger.no_cuadra`, con las cifras de las cuatro identidades).

**96. `OrderItem.shows_deposit_note` sigue existiendo pero se DERIVA del libro de la reserva**
(pedido cobrado ∧ `has_deposit` ∧ `balance.kind = pay_at_park`), y la tercera condición no la vigilaba
nadie: la mutación que la quita pasó en VERDE porque ningún caso tenía un pack con señal cuyo saldo
ya no fuera «a pagar en el parque» — que es el estado NORMAL de una fiesta después de celebrarse.
Dos casos nuevos en `OrderSummaryFieldsTest` (resto liquidado en puerta → `settled`; reserva cancelada
→ `refund_pending`), y ahora muerde. ⚠️ **Tres fixtures «pagados» sin `Payment` salieron a la luz con
el libro** (`OrderSummaryFieldsTest` ×2, `SidebarDomContractTest`): con el modelo viejo pasaban porque
nadie cruzaba el cobro con el estado; con la identidad I2 (cobrado == Σ online al nacer) responden «en
revisión». Se LEGALIZARON —el cobro se registra como lo hace `RedsysReturnHandler`, por
`onlineDueCents()`—, no se excepcionó la identidad.

**97. Una liquidación `gate` puede ser NEGATIVA, y el motivo de una cortesía NO viaja** (T4 del libro,
2026-09-01, `DECISIONES #317`). La liquidación en el parque es simétrica (D9 bis): con la visita pasada,
lo que quedaba por devolver se da por entregado en recepción y sale como `settlements[]` de `kind =
gate` con `amount_cents < 0` y la etiqueta «Devuelto en el parque» — solo cambia la DESCRIPCIÓN del
contrato (`LedgerSettlement.kind` y `amount_cents`), no su forma: un cliente que ya pintaba por signo
no tiene nada que cambiar (medido: el cajón lo pintó sin tocar `orders.js`, sonda §5.sexies 53/53). Y
la línea `courtesy` pasa a llamarse «Descuento por cortesía» con un MOTIVO del operador detrás que es
INTERNO (D-T4·1, `[DECIDIDO owner]`): vive en `Movement::$note`, lo pinta el panel y `LedgerResource`
**no lo transcribe** — `LedgerMovement` sigue con `additionalProperties: false`, así que publicarlo por
descuido lo caza Spectator además de `RefundIntentGovernsTest`.

