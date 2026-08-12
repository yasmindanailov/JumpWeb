# [SPEC] API v1 (Fase 3)

> Estado: 🟦 **en revisión** — necesita (a) revisión de otro agente y (b) **decisión del owner
> sobre dependencias nuevas** (`CONVENCIONES §9.3`), que es bloqueante para ✅ ·
> Última actualización: 2026-08-13 · Decisión asociada: `DECISIONES` «#N» al aprobarse.
> Antecedentes: `DECISIONES #3` (el sidebar se rehace como SPA contra la API) y `#4` (API-first).

## 1. Contexto y problema

**Medido hoy (2026-08-13), no supuesto:**
- **No existe API.** `composer.json` tiene 7 dependencias y **ninguna es Sanctum**; no hay fichero
  de rutas de API —`routes/api.php` (futuro)— ni enrutado de API en `bootstrap/app.php`. Las 29
  rutas de `routes/web.php` son todas server-rendered.
- **El sistema de reservas vive dentro de una clase de UI.** `app/Livewire/Tickets/Purchase.php`
  (2.049 líneas) expone ~40 métodos públicos. Inventariados uno a uno, solo **cuatro** son
  operaciones de SERVIDOR: `confirmReservation()`, `retryPayment()`, `checkPaymentStatus()` y el
  glue de autenticación. Todo lo demás es navegación del asistente (`showPacks`, `back`,
  `prevMonth`…) y manipulación del carrito **en el cliente** (`addToCart`, `incAddon`,
  `selectAddonOption`…). Hoy, un cliente que no sea Blade+Livewire **no puede comprar**.
- **El dominio ya está listo para ser expuesto.** Fase 2 dejó 5 módulos con contratos que
  devuelven **DTOs serializables** —fue una decisión deliberada del paso 1—:
  `Booking\Contracts\{CustomerReservations, PublishableCatalog, OperatingCalendar, ZonePalette}`
  y `Payments\Contracts\RefundGateway`.
- **La forma del carrito ya tiene fuente única**: `Booking\Services\Cart::sanitize()` define qué
  es una línea válida (`ticket_type_id`, `date`, `time`, `qty`, `event_data`, `addons[]`). No hay
  que inventar el esquema del checkout: existe.
- **La oferta también**: `AFORO-02` obliga a que fechas y horas ofrecibles salgan SOLO de
  `Booking\Services\SlotOffer` (`offerableDates()` / `offerableTimes()`), consumido hoy por la web
  y por el pedido manual del panel.

**El problema**, entonces, no es «falta lógica»: es que **la única puerta al dominio es HTML**.

## 2. Objetivo

**Criterios de éxito, medibles:**
1. Toda operación del sidebar actual es alcanzable por `/api/v1`, con **test de contrato** que
   la ejerce de extremo a extremo (no mocks del dominio).
2. **Especificación OpenAPI versionada** en el repo, y un test que falla si el código y la
   especificación divergen. La app móvil se construye contra ella (`DECISIONES #4`).
3. **Cero regresión**: la web actual sigue funcionando igual y la suite sigue verde; los dos
   verificadores de concurrencia siguen en verde sobre MySQL real.
4. `git grep` de reglas de negocio duplicadas entre la API y el dominio → **cero**: los endpoints
   son capa de entrega delgada sobre los servicios que ya usa la web.

**FUERA de alcance (explícito):**
- La **SPA** del sidebar y la retirada de `Purchase.php` → Fase 4.
- El **panel Filament**: sigue siendo server-rendered; no se expone ni se consume por API.
- La **landing**: sigue Blade SSR por SEO (`DECISIONES #3`).
- Endpoints de administración (pedidos, catálogo, reembolsos): el panel ya los cubre y no hay
  cliente que los pida. Inventarlos sería superficie sin lector — el mismo error que Fase 2
  prohibió en sus contratos.

## 3. Opciones consideradas

**(a) Autenticación.**
| Opción | Veredicto |
|---|---|
| **Sanctum** (tokens + sesión de cookie para SPA de mismo sitio) | **ELEGIDA**. Cubre los dos consumidores previstos con un solo paquete: la SPA de Fase 4 usa cookie de sesión (mismo dominio, sin token en `localStorage`) y la app móvil usa Bearer. Es el estándar de Laravel para este caso exacto. ⚠️ **Dependencia nueva → decisión del owner** (`CONVENCIONES §9.3`). |
| Passport (OAuth2) | DESCARTADA: OAuth2 completo para dos clientes propios es maquinaria que nadie va a usar; añade servidor de autorización, scopes y llaves que no aportan nada aquí. |
| Tokens propios | DESCARTADA: reescribir emisión, rotación, hashing y revocación de tokens es superficie de seguridad nueva escrita a mano, justo donde no conviene. |

**(b) OpenAPI.**
| Opción | Veredicto |
|---|---|
| **Escrita a mano, versionada, validada en tests** | **ELEGIDA** si el owner no quiere dependencia nueva: el YAML vive en el repo y un test comprueba que cada ruta de `/api/v1` aparece en él y que los ejemplos responden. Coste: disciplina. |
| Generada desde el código (Scramble, L5-Swagger…) | Preferible en ergonomía, pero es **otra dependencia** → misma decisión de owner. Si se aprueba, sustituye a la anterior. |
| Sin OpenAPI (solo tests) | DESCARTADA: `DECISIONES #4` la exige como artefacto de primera clase — la app móvil se construye contra ella. |

**(c) Cómo se verifica que el contrato sirve (el riesgo que `DECISIONES #4` señala).**
`#4` descartó «API-ready sin consumidor real» porque *un contrato sin cliente no se verifica*. Pero
la SPA es Fase 4. Opciones:
| Opción | Veredicto |
|---|---|
| API en Fase 3, primer consumidor en Fase 4 | Riesgo: 6 meses de superficie sin ejercer. |
| Re-cablear `Purchase.php` para que consuma la API | DESCARTADA: trabajo tirado — esa clase muere en Fase 4. |
| **Migrar UNA superficie real y autocontenida dentro de Fase 3** | **ELEGIDA**: el **post-form de invitados** (`/reserva/{id}/datos-invitados`). Es pequeño, no pertenece al asistente de compra (así que el trabajo NO se tira en Fase 4), y ejercita de golpe autenticación, autorización por titularidad, PII de terceros y `RGPD-03`/`RGPD-04`. Si el contrato sirve para eso, sirve. |

## 4. Diseño elegido

### 4.1 Enrutado y versión
- `routes/api.php` (futuro), registrado en `bootstrap/app.php` con prefijo **`/api/v1`**.
- La versión va en la **URL**, no en cabecera: es lo que la app móvil puede fijar sin ambigüedad y
  lo que hace obvio en los logs qué contrato se está usando.
- `v1` se congela cuando exista el primer cliente móvil (Fase 6); hasta entonces es evolutiva y
  así se documenta en la propia especificación.

### 4.2 Autenticación y sesión
- **SPA (mismo dominio)**: sesión de cookie de Sanctum (`statefulApi`), con CSRF. No hay token en
  `localStorage` — no se inventa un almacén de credenciales en el navegador.
- **Móvil**: `POST /api/v1/auth/tokens` devuelve un Bearer token con nombre de dispositivo.
- **`SEC-06` se hereda tal cual**: el login por API usa los MISMOS dos `RateLimiter` (clave
  `email|ip` **y** limitador solo-IP anti-spraying) y el reset conserva el mensaje genérico
  único de no-enumeración. No se duplican: los endpoints llaman a la misma lógica que hoy usan
  `Livewire\Auth\Login` y `ResetPassword`, que se extrae a un servicio de Identity si hace falta.
- Verificación de email y cambio de email siguen siendo **enlaces firmados web** (ya existen y
  están endurecidos); la API expone el «reenviar», no reimplementa el flujo.

### 4.3 Formato de respuesta y errores
- Éxito: el recurso en la raíz; listas bajo `data` con `meta` de paginación.
- Error: **un solo sobre** `{ "error": { "code": "...", "message": "...", "fields": {...} } }`.
  `code` es un identificador ESTABLE (`cart_too_large`, `sold_out`, `no_paid_payment`…), no un
  texto: la app móvil enruta por él y traduce en el cliente. Los códigos ya existen en el dominio
  —`ReservationException` los emite hoy como claves i18n— así que se reutilizan, no se inventan.
- Nunca se filtra el mensaje de una excepción interna.

### 4.4 Inventario de endpoints (derivado de la superficie REAL, no de un catálogo ideal)

| Superficie | Endpoints (futuro) | Se apoya en |
|---|---|---|
| Auth | `POST auth/login` · `POST auth/register` · `POST auth/logout` · `POST auth/password/forgot` · `POST auth/password/reset` · `POST auth/email/resend` · `POST auth/tokens` (móvil) | Lógica de `Livewire\Auth\*` + `Identity\Services\CustomerRegistrar` |
| Cuenta | `GET me` · `PATCH me` · `PATCH me/password` · `DELETE me` · `POST me/logout-others` | `Livewire\Account\*` + `User::anonymize()` (`RGPD-01`) |
| Catálogo | `GET catalog/zones` · `GET catalog/products` · `GET catalog/products/{id}` | `Booking\Contracts\PublishableCatalog`, `TicketType` |
| Disponibilidad | `GET availability/{product}/dates` · `GET availability/{product}/times?date=` | **`SlotOffer` (obligatorio por `AFORO-02`)** |
| Calendario | `GET venue/calendar` (horarios, temporadas, excepciones) | `Booking\Contracts\OperatingCalendar` |
| Pedido | `POST orders` (checkout) · `GET orders/{code}` · `POST orders/{code}/payment` (reintento) · `GET orders/{code}/payment-status` | `OrderCreator::createPendingOrder()` + `PaymentProvider` |
| Mis reservas | `GET me/reservations` | `Booking\Contracts\CustomerReservations` |
| Post-form | `GET reservations/{id}/guest-form` · `PUT reservations/{id}/guest-form` | `GuestFormController` (migra en Fase 3, §3.c) |
| Contenido | `GET content/pages/{slug}` · `GET content/faqs` · `GET content/services` | Modelos de `Content` |

**No hay endpoints de carrito, y es deliberado.** El carrito es **estado del CLIENTE**: hoy vive en
la sesión de Livewire y `PAY-12` lo dice explícitamente («la cesta es client-syncable y
`OrderCreator` re-valida cada línea»; `#[Locked]` se descartó a propósito). Añadir
`POST /cart/items` sería inventar estado de servidor que el dominio no tiene ni necesita, y abriría
una segunda fuente de verdad del precio. El cliente compone su cesta y la manda entera a
`POST /orders`, con la forma que ya define `Cart::sanitize()`; el servidor la re-valida y bloquea
aforo como hace hoy.

### 4.5 Pago: qué expone la API y qué NO
- `POST /orders` crea el pedido `pending` con su retención de aforo y devuelve **los campos del
  formulario de la pasarela** (los tres de Redsys), no una redirección: el cliente los auto-POSTea
  (la SPA en el navegador; la app móvil en webview/Custom Tab).
- **Las URLs de retorno y la notificación server-to-server siguen siendo rutas WEB**, sin tocar:
  ya existen, están endurecidas y `PAY-01` fija que `RedsysReturnHandler` es el único autorizado a
  pasar una Order a `paid`. La API **no** duplica esa entrada.
- El cliente conoce el desenlace por `GET orders/{code}/payment-status` (lo que hoy hace
  `Purchase::checkPaymentStatus()` con polling).

### 4.6 Abstracción `PaymentProvider`
La semilla ya existe: `Payments\Contracts\RefundGateway` (Fase 2, paso 1) cubre el **reembolso**.
Falta el **cobro**, que hoy solo consume la capa de entrega. Fase 3 lo completa:
- `Payments\Contracts\PaymentProvider` (futuro) con la ida (construir el checkout) + la vuelta,
  extraído de `Redsys::buildPaymentFormData()` y del retorno — **no de un catálogo imaginario de
  pasarelas**. Redsys es el primer driver; el segundo (Stripe u otro) valida la abstracción, y
  hasta que exista se documenta como no-verificada.

### 4.7 Transversales
- **Rate limiting**: grupo `api` con límite propio; auth conserva los limitadores de `SEC-06`;
  las rutas de pago conservan `throttle:120,1` (`PAY-15`).
- **Idioma**: `Accept-Language` resuelve el locale de la respuesta (equivalente al middleware
  `SetLocale` de la web).
- **PII**: las respuestas con datos de invitados llevan `Cache-Control: no-store` (`RGPD-04`) y el
  acceso al post-form respeta la caducidad del enlace (`RGPD-03`).
- **Frontera de módulos**: los controladores de API son capa de ENTREGA — el *composition root*
  del arch-test— así que pueden usar la superficie pública de cualquier módulo. Lo que NO pueden
  es llevar reglas: eso lo vigila `ModuleBoundariesTest` y lo comprueba el criterio 4 de §2.

## 5. Impacto en invariantes

Ninguno se relaja. Los que la API **debe heredar sin reescribir** (si alguno hubiera que tocar,
es decisión del owner — `CONVENCIONES §9.1`):

| ID | Cómo le afecta |
|---|---|
| `PAY-01` | La API NO abre una segunda vía a `paid`: el retorno/notificación siguen siendo rutas web. |
| `PAY-12` `PAY-13` | El precio y los topes se calculan en servidor; `POST /orders` pasa por `OrderCreator` tal cual. |
| `PAY-14` | Los emails que dispare la API siguen en cola (`ShouldQueue`). |
| `PAY-15` | Se conserva el `throttle` de las rutas de pago. |
| `AFORO-01` | `POST /orders` no reordena nada de `OrderCreator::lockSlots`. |
| `AFORO-02` | Disponibilidad SOLO por `SlotOffer` — la API sería una tercera fuente si la reimplementa. |
| `SEC-06` | El login/reset de la API usa los mismos limitadores y el mismo mensaje no-enumerable. |
| `RGPD-01` `RGPD-03` `RGPD-04` | Borrado de cuenta por `User::anonymize()`; caducidad del enlace del post-form; `no-store` en respuestas con PII. |
| `SUITE-01` | Los tests de la API no hacen red real (Redsys sigue con `Http::fake`). |

## 6. Plan de verificación empírica

1. **Tests de contrato por endpoint**, ejerciendo el dominio real (no mocks): pedido creado de
   verdad, aforo bloqueado de verdad, reembolso simulado con `Http::fake` como hoy.
2. **Test de coherencia con OpenAPI**: toda ruta registrada bajo `/api/v1` aparece en la
   especificación y viceversa. Falla si alguien añade un endpoint sin documentarlo.
3. **Paridad web↔API donde exista la misma pregunta**: un test que compara las fechas/horas que
   ofrece `SlotOffer` a la web y las que devuelve el endpoint — no pueden divergir (`AFORO-02`).
4. **No-regresión**: suite completa verde, `redsys:verify-concurrency` y
   `purchase:verify-oversell` en verde sobre MySQL real (el checkout por API entra en el mismo
   camino de dinero/aforo).
5. **Superficies vivas**: la web sigue respondiendo 200 y comprando igual; el post-form migrado
   funciona de extremo a extremo contra la API.
6. **Frontera**: `ModuleBoundariesTest` verde sin entradas nuevas en las baselines.

## 7. Revisión y decisión

**BLOQUEANTE — decisiones del owner (`CONVENCIONES §9.3`, dependencia nueva):**
1. ❗ **`laravel/sanctum`**: sin ella no hay autenticación de API. Alternativa realista: ninguna
   razonable (ver §3.a).
2. ❗ **Tooling de OpenAPI**: ¿especificación a mano (sin dependencia) o generada desde el código
   (una dependencia más)? El spec funciona con cualquiera de las dos.

**Pendiente además:** revisión por otro agente antes de escribir código (`CONVENCIONES §5`), como
se hizo con `modulos-dominio.md`.

Mientras esas dos preguntas no se respondan, este spec **no pasa a ✅** y Fase 3 no empieza a
implementarse.
