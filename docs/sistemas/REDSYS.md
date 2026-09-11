# Sistema — Pagos Redsys (redirección + reembolso REST)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Estado heredado: IMPLEMENTADO y endurecido** (ida + vuelta firmada + notificación
> on-line + reembolso REST). El doc origen era un "plan" verificado empíricamente contra la
> doc oficial de Redsys y las dos librerías oficiales PHP; aquí se describe **lo que el código
> hace hoy**. Los comentarios del código heredado citan decisiones del repo origen
> (#104, #105, #113, #114, #142, #169…): ese histórico no está portado — este doc es la
> referencia de comportamiento.
>
> **Refactor futuro:** en Fase 3 (`00-REFACTOR.md`) esta integración se encapsula tras la
> abstracción **`PaymentProvider`** (driver Redsys primero; enchufe para Stripe u otros).

## 0. Decisiones base heredadas
- **Redirección** (no REST/inSite para el cobro): el cliente sale al TPV de Redsys, paga allí
  y vuelve. **El PAN/CVV nunca toca nuestro servidor** → superficie PCI mínima.
- **Firma `HMAC_SHA512_V2`** (AES-128-CBC para derivar clave) con la **librería oficial PHP
  v2.0 vendorizada** — NO la v1.0 (SHA-256 + 3DES, obsoleta). No se escribe cripto a mano.
- **REST server-to-server** solo para operativa sin navegador: **devolución**
  (`TransactionType=3`), no para cobrar.

## 1. Flujo
1. **Ida:** el servidor monta parámetros, los firma y renderiza un **formulario auto-POST**
   (UTF-8) que el navegador envía a la URL de Redsys.
2. El cliente paga en el TPV Virtual de Redsys (fuera de nuestra web).
3. **Notificación on-line** (POST server-to-server a `Ds_Merchant_MerchantURL`): **fuente de
   verdad en producción**. Opcional; se activa configurando la URL (setting
   `redsys_merchant_url`; tiene preferencia sobre la config del portal admin Redsys, manual §11).
4. **Vuelta del navegador** a `Ds_Merchant_UrlOK`/`UrlKO`; según la config del terminal puede
   llegar por GET o POST, **con** los campos firmados inline o **sin** datos (§8).
5. Firma verificada → `Payment` → pedido `pending → paid` **idempotente** → emisión de tickets
   + email. KO/abandono → el pedido caduca por `orders:expire` y libera el aforo
   *(«aforo», «plaza», «entradas»: vocabulario del sector origen; su generalización se decide
   en `00-REFACTOR.md` Fase 1/2)*.

## 2. Los 3 campos del POST (ida y vuelta) — `HMAC_SHA512_V2`
| Campo | Valor |
|---|---|
| `Ds_SignatureVersion` | `HMAC_SHA512_V2` |
| `Ds_MerchantParameters` | **Base64URL "safe"** (sin `=`, `+→-`, `/→_`) del **JSON** de parámetros |
| `Ds_Signature` | HMAC-SHA512 de `Ds_MerchantParameters` con clave derivada (§6), en Base64URL safe |

- Los nombres del JSON valen en MAYÚSCULAS o CamelCase; el código de ida usa MAYÚSCULAS.
- ⚠️ Regla oficial: *"nunca evaluar el número total de parámetros en respuestas"* — Redsys
  añade campos sin aviso. Tratar la respuesta como dict abierto.
- ⚠️ `json_encode` **sin flags** en `createMerchantParameters` (URLs con barras escapadas):
  reproduce bit a bit la librería oficial. Lo firme es firmar exactamente los bytes enviados.

## 3. Parámetros de la ida (`Redsys::buildPaymentFormData`)
Todo importe/referencia sale del **modelo en servidor** (nunca input del cliente).

| Parámetro | Qué se envía |
|---|---|
| `DS_MERCHANT_AMOUNT` | **céntimos**, máx 12 dígitos. Fuente única = `Payment.amount` (soporta señal/depósito parcial). Backstop: importe ≤ 0 lanza excepción antes de abrir pasarela |
| `DS_MERCHANT_ORDER` | `payments.gateway_order` (§5) |
| `DS_MERCHANT_MERCHANTCODE` | FUC (setting; sandbox `999008881`) |
| `DS_MERCHANT_TERMINAL` | setting (sandbox `001`) |
| `DS_MERCHANT_CURRENCY` | `978` EUR (ISO-4217 numérico, setting defensivo vía `PaymentSettings::redsysCurrency()`) |
| `DS_MERCHANT_TRANSACTIONTYPE` | `0` (autorización) |
| `DS_MERCHANT_MERCHANTNAME` | setting `redsys_merchant_name`, `Str::ascii()` + límite 60 |
| `DS_MERCHANT_PRODUCTDESCRIPTION` | i18n `tickets.redsys_product_description` + código de pedido, `Str::ascii()` + límite 125 |
| `DS_MERCHANT_MERCHANTDATA` | `orders.code` legible (vuelve íntegro; reconciliación **secundaria** — la primaria es `gateway_order`) |
| `DS_MERCHANT_CONSUMERLANGUAGE` | `001` ES · `002` EN · `004` FR ⚠️ (`003` = catalán); `0` = sin determinar |
| `DS_MERCHANT_URLOK` / `URLKO` | `route('payments.redsys.return.ok'/'ko')` |
| `DS_MERCHANT_MERCHANTURL` | setting `redsys_merchant_url` (vacío ⇒ sin notificación; la fuente de verdad pasa a ser la vuelta firmada) |

`Str::ascii()` se aplica **antes** de truncar (evita partir UTF-8 multibyte). No se envían
nunca `DS_MERCHANT_PAN`/`EXPIRYDATE`/`CVV2` (solo existen para pago por referencia).

## 4. Respuesta (`Ds_MerchantParameters` decodificado)
Campos relevantes: `Ds_Response`, `Ds_AuthorisationCode`, `Ds_Amount`, `Ds_Currency`,
`Ds_Order`, `Ds_MerchantData`, `Ds_Date`/`Ds_Hour`, `Ds_Card_Brand`, `Ds_Card_Country`,
`Ds_SecurePayment`, `Ds_Card_Type`.

**Regla de oro:** PAGADO solo si **firma válida** Y `Ds_Response` numérico ∈ **[0000, 0099]**
(`RedsysReturnHandler::isAuthorizedResponse`, tolerante a padding). Cualquier otro = no pagado.

Códigos frecuentes: `0900` devolución autorizada · `0101` caducada · `0129` CVV erróneo ·
`0180` tarjeta ajena · `0184` fallo 3DS · `0190` denegación del emisor · `0913` **pedido
repetido** (reutilización de `gateway_order` — bug nuestro) · `9915` cancelado por el usuario ·
`9998/9999` transitorios. El mapeo código → categoría → clave i18n para el cliente vive en
`App\Domain\Payments\Services\RedsysResponseCode` (traducciones en `lang/{es,en,fr}/tickets.php` →
`payment_failed.reasons.*`; códigos no mapeados caen a `default`, nunca se muestra el número
crudo). Marca/país de tarjeta para el panel: `App\Domain\Payments\Services\RedsysCardCodes`.

## 5. ⚠️ `DS_MERCHANT_ORDER` (`payments.gateway_order`) — punto crítico
Formato Redsys: **máx 12 chars, los 4 primeros NUMÉRICOS**, resto `[0-9A-Za-z]`, **único por
comercio+terminal, para siempre** (reutilizar → `0913`). El código legible del pedido
(`orders.code`, con prefijo alfabético heredado) NO sirve; por eso existe la columna
**`payments.gateway_order`** (string 10, zero-padded, UNIQUE).

`Redsys::nextGatewayOrder()` — generador atómico:
- Contador en `settings.redsys_next_gateway_order`, leído con `lockForUpdate` dentro de
  transacción; arranca en `100000` (garantiza ≥4 dígitos).
- **Suelo anti-colisión** (gotcha empírico origen #169): el siguiente valor nunca puede ser
  ≤ `MAX(payments.gateway_order)`. Motivo: el contador puede desincronizarse (seed que
  reescribe el setting, o rollback de la transacción del caller que revierte el incremento)
  y quedarse "atascado" devolviendo el mismo valor → duplicate key en bucle. Como el valor es
  SIEMPRE string de 10 chars zero-padded, el MAX lexicográfico == MAX numérico (cross-DB, sin
  CAST). Autorrepara cualquier desincronización.

## 6. Firma (librería oficial v2.0 vendorizada)
**Vendor:** `app/Domain/Payments/Services/Redsys/Vendor/Signature.php` + `Utils.php` (copiados de la librería
oficial Redsys v2.0, con licencia). **Envoltorio único con la cripto: `App\Domain\Payments\Services\Redsys`.**

Derivación (tal cual el código oficial):
1. Clave por operación = AES-128-CBC(`Ds_Merchant_Order`, clave del comercio truncada/rellenada
   a **16 chars con el carácter `'0'`** — NO `\0`), IV = 16 bytes a cero, resultado en Base64
   estándar. ⚠️ A diferencia de v1.0, la clave del comercio **NO se Base64-decodifica** antes.
2. `hash_hmac('sha512', $params, $claveDerivada, true)`.
3. Resultado a Base64URL safe → `Ds_Signature`.

Métodos del envoltorio:
- `createMerchantParameters(array): string` — JSON + Base64URL safe.
- `createMerchantSignature(key, params, order): string` — firma de ida.
- `createMerchantSignatureNotif(key, params): string` — extrae `Ds_Order` del propio payload
  (acepta `Ds_Order`/`DS_ORDER`/`DS_Order`) y deriva igual.
- `decodeMerchantParameters(string): array` — lanza si no es Base64URL/JSON.
- `verifySignature(key, params, sig): bool` — comparación **`hash_equals`** (timing-safe).
- `gatewayUrl()` / `restUrl()` / `adminPanelUrl()` / `isLive()` / `config()` /
  `consumerLanguageCode()` / `nextGatewayOrder()` / `buildPaymentFormData()` / `executeRefund()`.

## 7. Confirmación: `App\Domain\Payments\Services\RedsysReturnHandler` (procesador único)
**ÚNICO punto del sistema autorizado** a transicionar `Payment → paid` y `Order → paid`.
Sirve a las dos vías (vuelta del navegador y notificación); `$source` (`browser_return` |
`notification`) solo etiqueta logs/auditoría.

Orden de validación (`process(array $payload, string $source)`):
1. Campos canónicos presentes → si no, `MalformedPayload` (log `redsys.return.malformed`).
2. Decodifica params (solo para extraer `Ds_Order`; aún no se confía en nada).
3. **Firma primero** (timing-safe). Sin firma válida → `InvalidSignature`, **sin lookup de BD**.
4. Lookup `Payment` por `gateway_order` (UNIQUE). No se usa `Ds_MerchantData` para esto.
5. Sección crítica en transacción con **`lockForUpdate` sobre Payment y Order** (serializa
   vuelta + notificación + reintentos concurrentes):
   - **Idempotencia paid:** Payment ya `paid` → `IdempotentPaid`, sin re-emisión ni emails.
   - **Idempotencia failed (simétrica):** Payment ya `failed` → `Denied` sin tocar BD ni
     re-enviar el email de denegación.
   - **Defense in depth:** `Ds_Amount` debe == `Payment.amount` (`AmountMismatch`, log ERROR
     `redsys.return.amount_mismatch`) y `Ds_Currency` debe casar con `Payment.currency` vía
     mapa ISO alfa-3 → numérico (solo divisas vendidas; divisa no mapeada NO se valida para no
     rechazar pagos legítimos) → `CurrencyMismatch`.
   - **Autorizado (0000–0099):** el Payment SIEMPRE pasa a `paid` (el cobro es real e
     irreversible en banco). Pero la Order solo se cumple (`paid` + tickets) si está
     **estrictamente viva**: `status == pending` y `!isExpiredInPractice()`:
     - Cumplible → Order `paid`, `paid_at`, `expires_at = null`; `TicketIssuer` emite
       (idempotente: no-op si ya hay tickets); **auto-verificación del email del comprador**
       («pay-first»: un pago real demuestra humanidad; marca `markEmailAsVerified` + evento
       `Verified`, idempotente). Outcome `Authorized`.
     - **Tardía pero real** (Order `expired` o pending-caducada): Order pasa a `paid` **sin
       tickets** (la plaza pudo cederse), log ERROR `redsys.return.overbooked_alert`, email
       distinto (`OrderProcessedAfterExpiration`), incidencia `overbooked`. Outcome
       `AuthorizedAfterExpiration`.
     - **Order muerta por otra razón** (cancelled/refunded/ya paid por OTRO Payment): NO se
       toca su status; log ERROR `redsys.return.duplicate_or_dead_capture`, incidencia
       `duplicate` — el operador debe devolver el cargo huérfano. Mismo outcome
       `AuthorizedAfterExpiration` de cara al cliente.
   - **Denegado:** Payment `failed` (con `raw_response` filtrado); Order sigue `pending` y
     caducará por `orders:expire` al cruzar `expires_at` (el aforo se libera entonces, no aquí).
     Outcome `Denied` + email `OrderPaymentDeclined` (solo primera transición).
6. **Fuera de la transacción** (un fallo SMTP no deshace un pago): emails
   (`OrderConfirmation`, post-form de invitados si el pack lo pide — «cumpleaños», vocabulario
   del sector origen —, `OrderProcessedAfterExpiration`, `OrderPaymentDeclined`), cada uno en su
   try/catch; y las incidencias de dinero se registran en `audit_logs`
   (`AuditLogger::logSystem`, acciones `ACTION_OVERBOOKED_CAPTURE`/`ACTION_DUPLICATE_CAPTURE`,
   sin PII) + aviso email al operador (`IncidentSettings::alertEmail()`, best-effort).

`Payment.raw_response` guarda SOLO una **allowlist** (minimización RGPD): `Ds_Response`,
`Ds_AuthorisationCode`, `Ds_TransactionType`, `Ds_Amount`, `Ds_Currency`, `Ds_Date`, `Ds_Hour`,
`Ds_Card_Brand`, `Ds_Card_Country`, `Ds_Order`, `Ds_MerchantData`. Nunca `Ds_Card_Number`
(PAN truncado) ni campos nuevos no listados.

Outcomes tipados: enum `App\Domain\Payments\Services\RedsysReturnOutcome` (`Authorized`, `Denied`,
`IdempotentPaid`, `AuthorizedAfterExpiration`, `InvalidSignature`, `UnknownOrder`,
`AmountMismatch`, `CurrencyMismatch`, `MalformedPayload`) con helpers `isSuccess()`,
`isOverbooked()`, `isClientFailure()`, `isServerReject()`.

### Estados del pedido y retención de aforo
- Al redirigir: Order `pending`, `expires_at = now() + sales.hold_minutes` (15 min por defecto,
  configurable). ⚠️ La retención DEBE ser ≥ el timeout del TPV.
- Vuelta/notificación OK: `paid`, `paid_at`, `expires_at = null`.
- KO/abandono: sigue `pending` → comando `orders:expire` (scheduler cada 5 min) la marca
  `expired` y libera aforo. ⚠️ Requiere cron del sistema ejecutando `php artisan schedule:run`.

## 8. HTTP: rutas y controller (`RedsysReturnController`)
Rutas (`routes/web.php`):
- `GET|POST /pago/redsys/retorno-ok` → `payments.redsys.return.ok`
- `GET|POST /pago/redsys/retorno-ko` → `payments.redsys.return.ko`
- `POST /pago/redsys/notificacion` → `payments.redsys.notification`

Las 3 están **excluidas de CSRF en `bootstrap/app.php`** (la firma es la autenticidad, no la
cookie). ⚠️ La vuelta es **cross-site** → la cookie de sesión (SameSite=Lax) puede no viajar:
**nunca depender de `auth()`/sesión para autorizar**; el pedido se identifica por
`Ds_Order` + firma.

**Vuelta del navegador** — dos modos según la configuración del terminal en el portal admin
Redsys (verificado empíricamente en el origen):
- Con datos firmados inline (GET query o POST body) → `processSignedReturn`. Server-reject
  (firma inválida, order desconocido, mismatch, malformado) → **HTTP 400 genérico**
  (`no-store`), sin redirigir con token (evitaría un canal de "éxito sin pagar"). Éxito/KO →
  redirect 303 al home con **token one-shot** en cache (`redsys.return:<token>`, TTL 5 min,
  payload `user_id`/`order_code`/`outcome`) que la UI consume para reabrir el flujo en el paso
  de resultado.
- **Sin datos** (`handleDataLessReturn`): fallback seguro — busca el **último intento** de cobro
  redsys reciente (<30 min) del **usuario logueado**, esté `pending`, `paid` o `failed`, y resuelve
  por lo que la notificación haya escrito sobre él: `paid` → token de éxito idempotente; `failed`
  → token de **rechazo** (el mismo desenlace que la vuelta firmada por UrlKO, con reintento);
  `pending` → home con `purchase.verifying_code` en sesión («verificando tu pago»). Sin usuario/sin
  Payment → home limpia. ⚠️ Los `failed` entran a propósito (`DECISIONES #454`): con un terminal
  que notifica antes de devolver al navegador y no incluye datos en la redirección —el de pruebas
  de CaixaBank, §14.bis—, dejarlos fuera hacía que la búsqueda cayera en un pedido pendiente
  ANTERIOR y el cliente leyera «tu banco ha procesado el pago» tras cancelar. **NUNCA se marca
  `paid` por llegar a UrlOK sin firma** (fraude trivial).

**Notificación**: responde **SIEMPRE HTTP 200 con body vacío**, pase lo que pase (4xx/5xx
provocan reintentos exponenciales de Redsys). Try/catch de último recurso → log
`redsys.notification.unhandled_exception`. *(El doc origen decía "200/400 según caso" para la
notificación; el código real siempre devuelve 200 — esta es la verdad.)*

⚠️ **Errores graves sin notificación** (doc oficial): order repetido, firma incorrecta o
formato inválido en la ida → Redsys NO notifica, el usuario solo ve error fatal en la pasarela.
Por eso se valida todo en servidor **antes** de redirigir. La ida a `realizarPago` exige POST
(GET da `SIS0124`).

### El token de la vuelta: se MIRA antes de consumirse [DECIDIDO 2026-08-13]
El token one-shot se leía con `Cache::pull` **antes** de comprobar la titularidad, buscando que
uno capturado no fuera reutilizable. El efecto real era el contrario del buscado: como el token
va atado a su `user_id`, un tercero **no podía usarlo pero sí QUEMARLO** — bastaba con abrir la
URL de la vuelta sin sesión para que el cliente legítimo perdiera su confirmación y se encontrara
el carrito vacío después de haber pagado. Desde Fase 3 · paso 4d (`DECISIONES #35`) se lee con
`Cache::get`, se valida y solo entonces se consume. La ventana de reutilización sigue cerrada
(el `pull` es atómico, TTL 5 min) y deja de haber una forma trivial de estropearle la vuelta a
otro. Verificado por mutación en `RedsysReturnControllerTest`.

### Cliente NATIVO: solo hay sondeo, y exige la notificación S2S [DECIDIDO 2026-08-13]
La vuelta del navegador es, literalmente, una redirección de navegador: **una app nativa no la
recibe**, y `DS_MERCHANT_URLOK` no lleva parámetros donde colgar un `state`, así que hoy no hay
deep link que disparar. La decisión (`DECISIONES #35`) es explícita: el cliente nativo abre la
pasarela en un navegador del sistema y **averigua el desenlace sondeando**
`GET /api/v1/orders/{code}/payment-status`.
⚠️ Y ese sondeo **solo llega a `paid` si la instalación tiene `redsys_merchant_url` configurada**:
sin notificación server-to-server y con terminal data-less, el único camino a `paid` es la vuelta
del navegador —que en nativo no ocurre— y el pedido caducaría **con la tarjeta ya cobrada**
(`PAY-02` lo capturaría como incidencia). Es **prerequisito duro de instalación** para Fase 6, no
una recomendación; está escrito también en el propio contrato OpenAPI, que es donde lo leerá quien
construya la app.

## 9. Reembolso REST (`RefundGateway::executeRefund`)
> **Fase 2 (paso 1, 2026-08-12)**: el reembolso es la única superficie de Payments que consume
> Booking, y viaja por el contrato `App\Domain\Payments\Contracts\RefundGateway` (bind a `Redsys`
> en `PaymentsServiceProvider`). `Order` ya no importa `Redsys`. La ida a la pasarela NO está en
> el contrato: sus llamantes son la capa de entrega, no Booking.
- Endpoint REST: `https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST` (test) ·
  `https://sis.redsys.es/sis/rest/trataPeticionREST` (live). Timeout 10 s.
- Misma cripto y los mismos 3 campos; diferencias: `DS_MERCHANT_TRANSACTIONTYPE = '3'`
  (**devolución**; NO `9` anulación — solo válida el mismo día sin consolidar) y
  `DS_MERCHANT_ORDER` = el **mismo `gateway_order` del Payment original** (así el banco enlaza
  devolución ↔ autorización). Admite devoluciones parciales acumulativas hasta el importe
  original.
- **Nunca lanza excepciones**: todo se normaliza en `App\Domain\Payments\Contracts\RefundResult`
  (`readonly`): `succeeded` (solo si `Ds_Response == '0900'` =
  `PaymentRefund::REDSYS_REFUND_SUCCESS_CODE`) · `gatewayDenied` (otro código, o respuesta
  "bare" `{"errorCode":"SISxxxx"}` con HTTP 200 sin `Ds_MerchantParameters` — p. ej. `SIS0054`
  operación inexistente, `SIS0058` importe excede; verificado contra sandbox) ·
  `transportError` (red/timeout/5xx/HTTP≠200 → **el operador debe verificar en el portal
  Redsys antes de reintentar**: Redsys pudo procesarla) · `malformedResponse`.
- **La firma de la respuesta REST se verifica obligatoriamente**; sin firma o inválida →
  `malformedResponse` (no se acepta un resultado de devolución sin autenticar). ⚠️ Gotcha
  histórico: una versión anterior cortocircuitaba la verificación cuando faltaba la firma.
- Orquestador: `Order::executeFullRefund(User $by, string $mode, bool $alsoCancel): array`
  (flujo lineal sin try/catch) + reembolso parcial simétrico en `Order`. Registro en modelo
  `PaymentRefund` (status `pending|succeeded|failed`, modo `rest|manual`, categorías de fallo,
  marcador `MANUAL` para reembolsos hechos fuera vía portal).
- El portal admin del comercio (back-office Redsys) se enlaza desde el panel
  (`Redsys::adminPanelUrl()`); no hay deep-link a transacciones: se busca por `gateway_order`.
  URL live `https://canales.redsys.es/admincanales-web/index.jsp` — ⚠️ algunas entidades
  bancarias white-labelean este portal; re-verificar con el banco del comercio.

## 10. Configuración data-driven (settings + `.env`)
Settings (grupo payment): `redsys_environment` (`test`/`live`) · `redsys_merchant_code` ·
`redsys_terminal` · `redsys_currency` (`978`) · `redsys_merchant_name` ·
`redsys_merchant_url` · `redsys_next_gateway_order` · `redsys_secret_key` (solo fallback dev).

**Clave secreta — camino crítico** (`Redsys::config()`):
1. Se lee de **`config('services.redsys.secret_key')`** (= `env('REDSYS_SECRET_KEY')` en
   `config/services.php`). ⚠️ **NUNCA `env()` directo en runtime**: con la config cacheada
   (`php artisan config:cache`) `env()` devuelve `null` → el cobro caería a la clave de
   sandbox = apagón de cobros en producción. La capa `config()` sí sobrevive a la caché.
2. Fallback: setting `redsys_secret_key` → constante `Redsys::SANDBOX_SECRET_KEY` (clave
   pública del sandbox). En producción la clave real vive en `.env` (vault del hosting),
   **nunca en BD** (un dump la expondría) ni en el repo/logs.
3. Guarda del panel (`Settings::save`): impide pasar a `live` mientras la clave efectiva sea
   la de sandbox o no mida 32 chars (`Redsys::SECRET_KEY_LENGTH`).

⚠️ El fallback hardcodeado de `redsys_merchant_name` en `Redsys::config()` aún lleva el nombre
del comercio del proyecto origen — genericizar en el refactor (Fase 1/2, `00-REFACTOR.md`).

## 11. Sandbox público de Redsys (valores públicos, solo test)
- Redirección TEST: `https://sis-t.redsys.es:25443/sis/realizarPago` · LIVE:
  `https://sis.redsys.es/sis/realizarPago`.
- Portal admin sandbox: `https://sis-t.redsys.es:25443/admincanales-web/index.jsp` (necesario
  para configurar el terminal: notificación on-line, "incluir datos en redirección").
- FUC `999008881` · Terminal `001` · Clave `sq7HjrUOBfKmC576ILgskD5srU870gJ7` (la del ejemplo
  oficial `ejemploGeneraPet.php`; única para v1 y v2).

Tarjetas de prueba: VISA `4548 8100 0000 0003` · Mastercard `5576 4415 6304 5037` · Amex
`3766 740000 00008` (todas cad. `12/49`, CVV `123`). Escenarios 3DS: VISA Frictionless
`4548 8144 7972 7229` / Challenge `4548 8172 1249 3017` (y variantes con Method URL:
`4918 0191 6003 4602` / `4918 0191 9988 3839`).

Simular denegaciones con la tarjeta estándar: **CVV** `999` (denegada), `172`/`173`/`174`
(denegada sin reintento) · **importes** terminados en `.96`, `.72`, `.73`, `.74` €. Útil para
probar la rama KO sin depender del 3DS.

⚠️ Cita literal de la doc oficial: *"ten mucho cuidado de no tener tu TPV Virtual configurado
para apuntar al entorno de pruebas una vez tu página esté abierta al público"* — el cambio se
hace en `settings.redsys_environment`.

## 12. Testing local
- **La vuelta firmada se valida 100% en local** (es navegación del navegador del cliente):
  activar en el portal admin sandbox "incluir datos en redirección". No requiere túnel.
- **La notificación NO llega a `localhost`** (la envían los servidores de Redsys). Para
  probarla ("el cliente paga pero no vuelve"): túnel efímero, p. ej. Cloudflare Quick Tunnel
  (sin registro; binario `cloudflared` de las releases oficiales de GitHub):
  ```bash
  cloudflared tunnel --no-autoupdate --url http://localhost:8081
  # capturar la URL https://<aleatoria>.trycloudflare.com del log
  ```
  Fijar el setting (lo lee `buildPaymentFormData`; prevalece sobre el portal):
  ```bash
  docker compose exec -u sail laravel.test php artisan tinker --execute="
  use App\Domain\Platform\Models\Setting;
  Setting::updateOrCreate(['key' => 'redsys_merchant_url'],
      ['value' => 'https://<tunel>.trycloudflare.com/pago/redsys/notificacion', 'group' => 'payment']);"
  ```
  Verificar: `curl -X POST` a esa URL debe dar HTTP 200 y dejar `redsys.return.malformed` en
  el log. Arquitectura de 3 URLs: `http://localhost:8081` (navegación humana, UrlOK/KO) ·
  la URL del túnel (SOLO el POST de notificación; abierta en navegador se ve HTML plano por
  mixed-content — no es bug) · la pasarela Redsys. La URL del túnel es efímera: cambia en
  cada arranque.

## 13. Mapa de código
| Pieza | Ruta |
|---|---|
| Envoltorio cripto + ida + refund REST | `app/Domain/Payments/Services/Redsys.php` |
| Librería oficial vendorizada | `app/Domain/Payments/Services/Redsys/Vendor/{Signature,Utils}.php` |
| Procesador de confirmaciones | `app/Domain/Payments/Services/RedsysReturnHandler.php` |
| Outcomes tipados | `app/Domain/Payments/Services/RedsysReturnOutcome.php` |
| Contrato de reembolso (interfaz + DTO) | `app/Domain/Payments/Contracts/{RefundGateway,RefundResult}.php` |
| `Ds_Response` → i18n cliente | `app/Domain/Payments/Services/RedsysResponseCode.php` |
| Marca/país de tarjeta (panel) | `app/Domain/Payments/Services/RedsysCardCodes.php` |
| Controller HTTP (3 rutas) | `app/Http/Controllers/Payments/RedsysReturnController.php` |
| Exclusión CSRF | `bootstrap/app.php` (`pago/redsys/*`) |
| Settings defensivos | `app/Domain/Payments/Services/PaymentSettings.php` |
| Refund: orquestador / registro | `app/Domain/Booking/Models/Order.php` (`executeFullRefund`) · `app/Domain/Payments/Models/PaymentRefund.php` |
| Caducidad de pedidos | `app/Console/Commands/ExpireOrders.php` (`orders:expire`) |
| Verificación manual sandbox | `app/Console/Commands/VerifyRedsysSandbox.php` · `VerifyRedsysConcurrency.php` |
| Tests | `tests/Feature/Sales/Redsys*.php` (firma bit a bit contra el ejemplo oficial, round-trip, handler, controller, notificación, gateway_order, secret key config) · `tests/Feature/Admin/Orders/*Refund*` |

## 14. Riesgos y gotchas (resumen operativo)
1. **`gateway_order` único para siempre** — reutilizar = `0913`; el generador con suelo
   anti-colisión (§5) es la defensa. No superar 12 chars.
2. **Importe en céntimos** — bug clásico enviar `10.00` en vez de `1000`; conversión
   centralizada + test.
3. **CSRF/sesión en la vuelta** — el fallo más común integrando Redsys en frameworks; rutas
   sin CSRF y sin depender de sesión (§8).
4. **Secreto** — nunca en repo/logs/BD; leer vía `config()`, no `env()` (§10).
5. **Timeouts coordinados** — `hold_minutes` ≥ timeout del TPV.
6. **Cambio de versión de firma** — `Ds_SignatureVersion` explícito y cripto encapsulada en un
   punto; una v3 se absorbe en `App\Domain\Payments\Services\Redsys`.
7. **Fiabilidad de la notificación** — si el server está caído Redsys reintenta; un **job de
   reconciliación** (consulta de operaciones) para `pending` antiguos NO está implementado
   (mejora futura).
8. **PSD2/SCA** — en redirección el 3DS lo gestiona Redsys (cumplimiento de serie);
   `Ds_Merchant_EMV3DS`/exenciones solo si se quiere optimizar conversión.
9. **Go-live genérico**: clave real en `.env` (verificar `strlen == 32`), cron del scheduler
   activo (validar que `orders:expire` corre), `redsys_merchant_url` con dominio real
   (verificar con `curl`), `redsys_environment=live` + comprobar aterrizaje en
   `sis.redsys.es` (la CSP `form-action` cambia con el entorno: si está mal, el navegador
   rechaza el form de ida), pago real mínimo + reversión inmediata, y monitoring de logs:
   `redsys.return.invalid_signature{source:notification}` (posible atacante),
   `amount_mismatch`/`currency_mismatch` (config/protocolo), `overbooked_alert` (contactar
   cliente en 24 h), `redsys.notification.unhandled_exception`. ⚠️ **Rate-limit del endpoint de
   notificación: ya está EN LA APP y es invariante.** Las tres rutas `/pago/redsys/*` van bajo
   `throttle:120,1` (`routes/web.php`, auditoría Fase 1 · L5) y eso es **`PAY-15`, que NO se deshace**
   (`INVARIANTES.md` §1) — y **sin test dedicado**, así que nada cazaría la regresión. Un rate-limit
   adicional en el edge/WAF es complementario y opcional; si se pone, **con bypass para los rangos IP
   de Redsys** (pedirlos al banco: reintenta con backoff y un límite estricto rompería reintentos
   legítimos). [DECIDIDO 2026-08-19] Antes decía «NO en la app», que contradecía a `PAY-15`.

## 14.bis · Lo MEDIDO con el terminal de CaixaBank (Cyberpac), 2026-09-11 (`DECISIONES #453`)

El banco del segundo cliente es CaixaBank (Comercia Global Payments). **Cyberpac es el TPV Virtual de
Redsys**: mismas URL (`sis-t` / `sis`), mismo Canales, mismos códigos SIS; lo que cambia es el soporte,
el proceso de pase a real (su equipo completa una compra en una URL nuestra) y la configuración del
terminal por entidad. Lo que su terminal de PRUEBAS (`369809538` / `1`) contestó, sin tocar el código:
- **Acepta `HMAC_SHA512_V2`** aunque su guía y su correo digan «SHA-256» (documento antiguo): la ida
  abre la pantalla de pago y el REST devuelve `SIS0054` con la clave buena y `SIS0042` con una mala.
- **NOTIFICA por S2S y la vuelta del navegador llega SIN DATOS** (`redsys.return.processed
  {source: notification}` y luego `redsys.return.no_payload`): «incluir datos en redirección» está
  apagado en su terminal. ▶ Consecuencia para el go-live de §14.9: **`redsys_merchant_url` es
  OBLIGATORIA antes de `live`** (hoy en producción está vacía), o el pago cobrado caduca (`PAY-02`).
- **Su tarjeta «denegada» (`1111…1117`) no deniega: EXCEPCIONA** (`SIS0093`, «Tarjeta ajena al
  servicio»), y cancelar da `SIS9915`; en los dos casos notifica (el pago queda `failed`) y el navegador
  vuelve sin datos → la pantalla de «verificando» de `DEUDA.md`. CVV `999` con la tarjeta aceptada da
  «DENEGADA … requiere autenticación del titular» **y se queda en la pasarela** (no vuelve).
- **La pasarela exige 3 dígitos de CVV siempre**, también con la tarjeta cuyo CVV «no se requiere».
- Su guía §5 fija la **sesión de la pasarela en 30 minutos**; `sales.hold_minutes` vale 15 en producción
  (20 por defecto): un pago completado entre el minuto 15 y el 30 llega con el pedido caducado.
- Sus nueve IP de notificación (guía §6): `193.16.243.33` · `.13` · `.173` · `194.224.159.47` · `.57` ·
  `195.76.9.187` · `.222` · `.117` · `.149`. Sin WAF delante no hay nada que abrir; el `throttle:120,1`
  las aguanta.

## Referencias oficiales (públicas)
- Manual "Integración por Redirección": `canales.redsys.es/canales/ayuda/documentacion/Manual integracion para conexion por Redireccion.pdf`
- Tarjetas y entornos de prueba: `pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/tarjetas-y-entornos-de-prueba/`
- Devolver o anular un pago (REST): `pagosonline.redsys.es/desarrolladores-inicio/documentacion-operativa/devolver-o-anular-un-pago/`
- Tipo de integración Redirección: `pagosonline.redsys.es/desarrolladores-inicio/documentacion-tipos-de-integracion/desarrolladores-redireccion/`
