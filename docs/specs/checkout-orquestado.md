# [SPEC] Checkout orquestado — la secuencia del dinero baja al dominio

> Estado: ✅ aprobado e **IMPLEMENTADO** (v2, tras revisión adversarial de 3 agentes) ·
> Última actualización: 2026-08-13 · Decisión asociada: `DECISIONES #37`.
> Cierra el último ítem abierto de **Fase 3** (`00-REFACTOR.md`), en la mitad que el owner aprobó
> el 2026-08-13: **la orquestación ahora; el segundo driver de pasarela, a Fase 6**.
>
> **v2 (2026-08-13)**: la v1 tenía DOS afirmaciones falsas que hacían el diseño inaplicable, y
> rompía el gate documental. Las tres las encontró la revisión; §7 lista qué cambió.

## §0 · Antes de tocar

- **La secuencia «admitir → crear → abrir cobro» vive en el dominio** (`CheckoutOrchestrator`, tras los
  puertos `ReservationCheckout` y `PaymentInitiation`), no en cada controlador. Está implementada (`#37`);
  `CheckoutOrchestrator` está en el `CRITICAL_RE` → `VERIFY_CONC=1` con `purchase:verify-oversell` y
  `redsys:verify-concurrency`. Invariantes: `INVARIANTES.md` §1 (`PAY-04`) y §2 (`AFORO-10`).
- **El ORDEN es la regla y ninguna guarda de arquitectura lo ve** (un controlador que llama a los tres
  servicios correctos en orden equivocado pasa `ApiBoundariesTest`): (1) admitir ANTES de crear y
  CONSUMIENDO ficha —un chequeo que no cuenta no limita—; (2) crear SIEMPRE con ventana de retención: el
  tercer parámetro de `createPendingOrder()` con `null` es un pedido FIRME que retiene aforo para siempre;
  (3) abrir el cobro DESPUÉS de que el pedido exista; (4) si el cobro no abre, soltar el pedido en un primer
  intento y no tocarlo en un reintento.
- **La v1 de esta spec tenía dos afirmaciones falsas** que la hacían inaplicable; §7 lista qué cambió.
- **El segundo driver de pasarela va a Fase 6** (`[DECIDIDO owner]`), no aquí.

## 1. Contexto y problema

La secuencia «**admitir → crear → abrir cobro**» está escrita a mano en cada superficie de
entrega. Medido con `grep -rn PaymentInitiator app/` el 2026-08-13 — **5 puntos de llamada en
4 clases**:

| Clase | Método | Secuencia |
|---|---|---|
| `Livewire\Tickets\Purchase` | `confirmReservation()` | crear |
| `Livewire\Tickets\Purchase` | `retryPayment()` | reintento |
| ~~`Http\Controllers\Payments\RetryPaymentController`~~ | `__invoke()` | reintento — ⏳ **RETIRADO** en la tanda 3 del área de cliente (`DECISIONES #120(u)`) con la página que lo servía: el cajón reintenta por la API |
| `Http\Controllers\Api\V1\OrdersController` | `store()` | crear |
| `Http\Controllers\Api\V1\OrderPaymentController` | `store()` | reintento |

**El orden ES la regla**, y ninguna guarda de arquitectura lo ve: un controlador que llama a los
tres servicios correctos en el orden equivocado pasa `ApiBoundariesTest` con nota
(`api-v1.md` §10.decies, punto 52). Los cuatro puntos del orden, hoy repetidos en prosa en cada
docblock:

1. `admitReservation()` **antes** de crear y **consumiendo** ficha (un chequeo que no cuenta no limita).
2. `createPendingOrder()` **siempre** con ventana de retención (`AFORO-10`); su tercer parámetro
   tiene default `null` = pedido FIRME que no caduca, así que omitirlo retiene aforo para siempre.
3. `open()` **después** de que el pedido exista (el `Payment` ata `gateway_order ↔ Order` en BD).
4. Si el cobro no abre: **soltar** el pedido en un primer intento; **no tocarlo** en un reintento.

Además hay dos duplicaciones menores dentro de esa secuencia: `now()->addMinutes(
PaymentSettings::holdMinutes())` escrito en las dos superficies que crean, y `$user->locale`
pasado a mano en los cinco puntos.

**Por qué no se arregló en el paso 4c** (`DECISIONES #34`, `DEUDA.md`): extraerlo a un servicio de
dominio cruzaría Booking → Payments con una flecha de ORQUESTACIÓN y la baseline de
`ModuleBoundariesTest` **solo encoge**. Ese razonamiento era correcto **y tenía fecha de caducidad**:
lo era solo mientras no existiera el contrato de la ida. El grafo ya sanciona el canal —
`ModuleBoundariesTest::ALLOWED` declara `'Booking' => ['Platform', 'Payments\Contracts']`— y
`RefundGateway` se declara a sí mismo «la semilla» de esta abstracción en su propio docblock.

## 2. Objetivo

**Criterios de éxito, medibles:**

- **CE-1** — Ninguna clase fuera de `app/Domain` nombra la ida del pago ni escribe la secuencia.
  **No es un grep de un día: es una guarda ejecutable** (`CheckoutSequenceTest`, §4.7). Un grep
  se cumple el día del commit y caduca en el siguiente; es el mismo error que §1 denuncia.
- **CE-2** — Ninguna clase fuera de `app/Domain` llama a `createPendingOrder()` ni a
  `releaseAfterFailedPaymentStart()`: ni la creación ni la compensación se deciden en la entrega.
  Misma guarda.
- **CE-3** — `ModuleBoundariesTest` verde **sin añadir ni una entrada** a `SEAM`/`LEGACY`/
  `DEFERRED`/`PENDING`. Es el criterio que gobierna toda la forma del diseño (§4.1). Se demuestra
  publicando el diff de las cuatro constantes: debe ser vacío.
- **CE-4** — Los cuatro puntos del orden tienen test **en el dominio** y se demuestra que muerden
  **por mutación** (§6).
- **CE-5** — Paridad: la suite existente de las cuatro superficies pasa **sin cambiar ni una
  aserción**. Este refactor no cambia ninguna respuesta observable.
- **CE-6** — El fichero que alberga la secuencia entra en el gate `CRITICAL_RE` del `pre-push`, y
  `CriticalPathGateTest` lo exige.

**Fuera de alcance (explícito):**

- El **segundo driver de pasarela** (Stripe u otros) y la selección de driver por configuración.
  Viaja a Fase 6 con la app, su primer lector real. Decisión del owner del 2026-08-13.
- La **lógica** de `PaymentInitiator`, `OrderCreator::lockSlots()`, `RedsysReturnHandler` y
  `ReservationAdmissionPolicy`. **Ni una línea de lógica de dinero cambia.** ⚠️ Matiz que la v1
  se dejó: `PaymentInitiator` **sí** se toca en su superficie —gana `implements`, y sus constantes
  y su excepción cambian de sitio (§4.2)—, pero su cuerpo no.
- Retirar `Livewire\Tickets\Purchase` (muere en Fase 4) ni tocar su UI: solo pierde la secuencia.
  **Sus nombres de método no pueden cambiar**: las vistas los referencian en `wire:click`/
  `wire:target` y hay un test que asevera el literal `'confirmReservation'`.
- La creación de pedidos FIRMES del panel (`ManualOrderFulfiller`, hold `null` deliberado): no pasa
  por el orquestador ni debe.

## 3. Opciones consideradas

**(A) — El puerto de la ida en `Payments\Contracts`, simétrico a `RefundGateway`. DESCARTADA.**
Es la opción evidente y la que el propio `RefundGateway` sugiere. Falla al medirla: la firma de la
ida habla de `Booking\Models\Order`, porque el initiator necesita `onlineDueCents()`, `currency` y
`code` del pedido. Un fichero en `Payments/Contracts/` que nombre `Order` es una flecha
Payments→`Booking\Models` y exige **entrada nueva en `SEAM`** — exactamente lo que `DECISIONES #34`
declara señal de que algo está mal hecho. **Verificado contra la guarda por la revisión**, no
asumido. La asimetría con `RefundGateway` no es capricho: `RefundGateway` habla de `Payment`, un
tipo del propio Payments, y por eso sí puede vivir allí. **Regla que sale de aquí: el puerto vive
en el módulo cuyos tipos habla** (§4.2, decisión con nombre).

**(B) — El puerto en `Booking\Contracts`, declarado por quien lo consume. ELEGIDA.**
Booking nombra `Order` (su propio modelo) y `Payments\Contracts\PaymentTicket` (canal ya sancionado
por el grafo). `PaymentInitiator` lo implementa desde Payments, que tiene `Booking\Contracts`
permitido en `ALLOWED`. **Cero entradas nuevas en cualquier baseline** (CE-3) — pero solo si se
mueven además la excepción y las constantes, que es lo que la v1 no vio (§4.2).

**(C) — Dejarlo como está y esperar al segundo driver. DESCARTADA por el owner** el 2026-08-13,
con el argumento de que la duplicación está MEDIDA (5 puntos) mientras que el segundo driver es
especulación. Se conserva su mitad válida: el driver espera.

**(D) — Que el orquestador reciba la ventana de retención por parámetro. DESCARTADA.**
Sería lo cómodo, pero deja `AFORO-10` en manos del llamante: una quinta superficie podría pasar
`now()->addYears(1)` y retener aforo indefinidamente sin que nada lo viera. El orquestador debe
POSEER la ventana. Cómo se consigue sin flecha nueva, en §4.3.

## 4. Diseño elegido

### 4.1 La restricción que gobierna la forma

Todo el diseño se deriva de CE-3. El grafo de módulos permite hoy, y solo:

```
Booking  → Platform, Payments\Contracts
Payments → Platform, Booking\Contracts
```

El escaneo es por TOKENIZADOR y el emparejamiento por prefijo LITERAL, así que no hay escapatoria:
ni el `use`, ni el FQCN inline, ni un `catch` cuentan distinto. De ahí sale todo lo que sigue,
incluidas las dos mudanzas del §4.2 que la v1 se dejó.

### 4.2 Componentes

| Símbolo | Ruta | Qué es |
|---|---|---|
| `Booking\Contracts\PaymentInitiation` | `app/Domain/Booking/Contracts/PaymentInitiation.php` (futuro) | **Puerto REQUERIDO de la IDA**: lo que Booking NECESITA de una pasarela para cobrar (`open()`/`reopen()` → `PaymentTicket`). Es la mitad de `PaymentProvider` que esta entrega hace. |
| `Booking\Contracts\ReservationCheckout` | `app/Domain/Booking/Contracts/ReservationCheckout.php` (futuro) | **Puerto OFRECIDO de la SECUENCIA**, lo que consume la capa de entrega. Aloja también las constantes `SOURCE_*`. |
| `Booking\Contracts\CheckoutOutcome` | `app/Domain/Booking/Contracts/CheckoutOutcome.php` (futuro) | Resultado de crear: o denegación (`AdmissionDecision`) o `Order` + `PaymentTicket`. |
| `Booking\Contracts\RetryOutcome` | `app/Domain/Booking/Contracts/RetryOutcome.php` (futuro) | Resultado de reintentar: o denegación (`RetryAdmission`) o `Order` + `PaymentTicket`. |
| `Booking\Services\CheckoutOrchestrator` | `app/Domain/Booking/Services/CheckoutOrchestrator.php` (futuro) | **La secuencia**. Implementa `ReservationCheckout`. Único sitio donde vive el orden. |
| `Payments\Contracts\PaymentInitiationException` | `app/Domain/Payments/Contracts/PaymentInitiationException.php` (futuro) | **MUDANZA de namespace** desde `Payments\Exceptions` (§4.2.a). |
| `Payments\Services\PaymentInitiator` | (existe) | Pasa a `implements PaymentInitiation`; sus `SOURCE_*` pasan a ser alias (§4.2.b). **Su cuerpo no se toca.** |
| `Booking\Services\OrderCreator` | (existe) | Gana `checkoutHoldUntil()` (§4.3). Nada más. |

#### 4.2.a La excepción tiene que mudarse — y esto NO es cosmético

`PaymentInitiationException` vive hoy en `App\Domain\Payments\Exceptions`. El orquestador **tiene
que capturarla** (es el disparador de la compensación), y Booking solo puede nombrar
`Payments\Contracts`: capturarla desde `Booking\Services\CheckoutOrchestrator` es una flecha
PROHIBIDA y tumba CE-3. Las tres salidas y por qué solo una vale:

- capturar `\Throwable` → compensaría fallos que no son de la pasarela. **Peor que el problema.**
- añadir entrada a `SEAM` → viola CE-3, que es el criterio rector. **Vetada.**
- **mover la excepción a `Payments\Contracts`** → es donde le toca: la lanza el puerto, así que es
  parte de su contrato, no un detalle interno. Hay precedente de clase concreta en esa carpeta
  (`PaymentTicket`). **Elegida.** Es mudanza de namespace pura, sin cambio de nombre ni de cuerpo.

#### 4.2.b Las constantes `SOURCE_*` suben al puerto

Las tres (`checkout` · `retry_sidebar` · `retry_account`) viven en `PaymentInitiator`, y las
**cinco** superficies las nombran para pasar `$source`. Mientras sigan ahí, CE-1 es inalcanzable:
o la entrega nombra `PaymentInitiator`, o el orquestador lo nombra —y eso es Booking→
`Payments\Services`, prohibido—.

Suben a `ReservationCheckout`, que es el símbolo que la entrega consume ahora. `PaymentInitiator`
conserva las suyas **como alias** (`const SOURCE_CHECKOUT = ReservationCheckout::SOURCE_CHECKOUT`):
una sola definición, y los tests que hoy usan `PaymentInitiator::SOURCE_*` o `self::SOURCE_*` en
dobles anónimos siguen sin tocarse (CE-5).

⚠️ **Los literales `'checkout'`/`'retry_sidebar'`/`'retry_account'` NO cambian**: viajan a
`audit_logs` y son lo que la operadora lee para saber por dónde entró un cobro fallido (`PAY-05`).

#### 4.2.c Bindings, y por qué la forma importa

- `ReservationCheckout → CheckoutOrchestrator` en `BookingServiceProvider`.
- `PaymentInitiation → PaymentInitiator` en `PaymentsServiceProvider` (la atadura vive donde vive
  la implementación, igual que `RefundGateway → Redsys`). Como el contrato es de Booking y el bind
  de Payments, `BookingServiceProvider` lleva un comentario que lo diga: si no, quien lea solo ese
  provider concluirá que el puerto está sin atar.

⚠️ **Los dos bindings van por NOMBRE DE CLASE, nunca por closure.** Dos tests de la API sustituyen
`PaymentInitiator::class` en el contenedor para simular una pasarela caída —son justo los que
prueban la compensación asimétrica—. Con `bind(Puerto::class, Concreto::class)` la sustitución se
sigue propagando; con `fn () => new PaymentInitiator(...)` esos tests pasarían por el camino real
**y seguirían verdes sin probar nada**.

#### 4.2.d Restricción de nombres

Ningún método de los puertos puede llamarse como una escritura de Eloquent (`create`, `update`,
`save`, `push`, `increment`…): `ApiBoundariesTest` los prohíbe por nombre corto sobre cualquier
receptor, y un `createOrder()` en el puerto pondría rojo a `OrdersController` sin que la causa
fuese evidente. Por eso `start()` y `retry()`.

### 4.3 La ventana de retención, sin flecha nueva

`AFORO-10` exige que el pedido nazca auto-liberable, y §3(D) exige que la ventana la posea el
orquestador. La ventana sale de `PaymentSettings::holdMinutes()`, que vive en Payments: leerla desde
`CheckoutOrchestrator` sería una entrada nueva en `SEAM` (la quinta de la misma config).

**Solución medida**: `OrderCreator` **ya** importa `PaymentSettings` —tiene su entrada en `SEAM`
desde Fase 2, para `orderPrefix()`— y ya expone un helper estático de ventana,
`verificationHoldUntil()`. Se añade su hermano:

```php
/** Momento hasta el que un pedido de checkout retiene su plaza (`AFORO-10`). */
public static function checkoutHoldUntil(): Carbon      // (futuro)
{
    return now()->addMinutes(PaymentSettings::holdMinutes());
}
```

El orquestador lo llama (Booking→Booking, siempre permitido). **Cero flechas nuevas**, y de paso
muere la duplicación del cálculo en las dos superficies que creaban pedidos.

⚠️ Dos honestidades que la v1 se saltó: `verificationHoldUntil()` es hoy **código muerto** en `app/`
(solo lo usa un test), así que es precedente de FORMA, no de uso; y el default `?Carbon $hold = null`
de `createPendingOrder()` **se conserva a propósito** porque el pedido manual del panel es firme y no
caduca. Es decir: el orquestador posee la ventana **del checkout**, no de toda creación de pedidos.

### 4.4 La secuencia, dicha una vez

```
start(User $user, array $cart, string $source): CheckoutOutcome
  1. admission->admitReservation($user->id)      → denegado ⇒ CheckoutOutcome::deny($decision)
  2. order = creator->createPendingOrder($user, $cart, OrderCreator::checkoutHoldUntil())
                                                  → ReservationException PROPAGA (ver §4.5)
  3. try  ticket = gateway->open($order, $user->locale, $source)
  4. catch PaymentInitiationException
         order->releaseAfterFailedPaymentStart()  ← compensación: primer intento SUELTA
         throw                                     ← el llamante decide qué ve el cliente
  5. CheckoutOutcome::allow($order, $ticket)

retry(User $user, string $orderCode, string $source): RetryOutcome
  1. verdict = admission->admitPaymentRetry($user->id, $orderCode)
                                                  → denegado ⇒ RetryOutcome::deny($verdict)
                                                  (el hold ya viene extendido, `PAY-04`)
  2. try  ticket = gateway->reopen($verdict->order, $user->locale, $source)
  3. catch PaymentInitiationException
         (NO se toca el pedido: sigue vivo con su hold recién extendido)
         throw
  4. RetryOutcome::allow($verdict->order, $ticket)
```

⚠️ **El orquestador NO abre transacción, y eso es la regla, no un olvido.** Hoy son dos unidades de
trabajo cortas y separadas (`createPendingOrder` tiene la suya; `PaymentInitiator` la suya).
Envolverlas juntas tendría dos consecuencias graves: el `lockForUpdate` de `lockSlots()` quedaría
sostenido durante la firma del payload —contención en el punto exacto que `AFORO-01` llama «el más
sutil»—, y el `audit_logs` de fallo de inicio haría **rollback** junto con la compensación, que es
justo lo que `PAY-05` prohíbe (las incidencias son visibles, no solo log).

Los constructores estáticos se llaman `deny()`/**`allow()`** y no `denied()`: `denied()` es el
PREDICADO, por consistencia con `AdmissionDecision` y `RetryAdmission`, y en PHP no pueden coexistir.

`$source` sigue siendo parámetro porque distingue superficies en `audit_logs`.
`$preferredLocale` **deja de serlo**: los cinco puntos de llamada pasan hoy `$user->locale`
—verificado uno a uno por la revisión—, así que el orquestador lo lee y se acaba la quinta copia.
El puerto conserva el parámetro como `?string` para no matar el fallback `?? app()->getLocale()`
del initiator.

### 4.5 Qué NO absorbe el orquestador, y por qué

- **`ReservationException`** (cesta vacía, agotado, fuera de horario) **propaga sin tocarse**: la API
  la traduce a código de negocio con `ReservationErrorMap` y la web la pinta inline. Capturarla aquí
  destruiría los doce códigos de error del paso 4c. No deja estado a medias: `createPendingOrder`
  es una transacción entera y hace rollback. Lo único que sobrevive es la ficha del limitador ya
  consumida — idéntico a hoy, y con test propio para que nadie lo «arregle» moviendo la admisión
  después de crear (reabriría el hallazgo E del origen).
- **`PaymentInitiationException`** se **re-lanza** tras compensar. La compensación es dominio; el
  502 / el mensaje inline es presentación. ⚠️ Con esto, la presentación pasa a ser lo olvidable:
  `ApiExceptionRenderer` solo conoce `ReservationException`, así que si un controlador dejara de
  capturarla, el contrato (que documenta **502 `payment_unavailable`**) degradaría a 500 en
  silencio. Los cuatro consumidores **conservan su `catch`**, y §6 lo asevera.
- **La traducción del veredicto denegado a HTTP o a flash** se queda en cada superficie: son cuatro
  presentaciones distintas de la misma decisión, y ya estaban bien.
- **`mayReserve()`** (la consulta que NO consume, que el sidebar usa al pasar de carrito a pago)
  se queda fuera del orquestador: es un aviso temprano, no la secuencia. `Purchase` seguirá
  inyectando `ReservationAdmission` además de `ReservationCheckout`.

### 4.6 Gates

- `.githooks/pre-push`: `CheckoutOrchestrator` entra en `CRITICAL_RE`. Es donde vive ahora la
  secuencia del dinero; sin esto se empujaría sin correr los verificadores (`INVARIANTES §6`).
  Comprobado que no lo vuelve comodín: los cuatro controles negativos de `CriticalPathGateTest`
  siguen sin casar.
- `CriticalPathGateTest`: su ruta entra en `CRITICAL_FILES`, y `ReservationCheckout` en
  `CRITICAL_SYMBOLS` —**esa es la que importa**: un controlador de API futuro que llegue al checkout
  por el contrato y no case con el patrón por su nombre quedaría fuera del gate—.
  ⚠️ `CRITICAL_FILES` se asevera con `assertFileExists`: hook y test se amplían **en el mismo
  commit** que crea el fichero, o el test queda rojo.
- ⚠️ **El gate nunca cubrió las superficies WEB** (`Purchase`, el reintento de «Mis pedidos»), y hoy **ya no existe ninguna**: la primera se retiró en `#112` y la segunda en `#120(u)`. Se conserva la nota porque explica por qué el `CRITICAL_RE` mira lo que mira: un
  commit que migre solo la web no lo dispararía. Se corren los verificadores igualmente.

### 4.7 La guarda ejecutable que sustituye a los greps (CE-1/CE-2)

`CheckoutSequenceTest` (futuro), en `tests/Feature/Architecture/`. Escanea con el tokenizador todo
`app/` fuera de `app/Domain` y prohíbe:

- **nombrar la ida**: `PaymentInitiator` o `PaymentInitiation`;
- **llamar** `createPendingOrder(...)` o `releaseAfterFailedPaymentStart(...)`.

Sin excepciones con nombre, porque no hacen falta: `Purchase` seguirá nombrando `OrderCreator`
—para su constante `MAX_LINES_PER_CART`, que es lectura, no secuencia— y eso queda permitido a
propósito. La guarda incluye su propio control de que el escaneo no pasa en vacío.

**Por qué hace falta**: sin ella, el diseño repara el síntoma y deja el mecanismo intacto. Tras el
refactor, una sexta superficie tendría un camino MÁS cómodo que el actual —inyectar
`Booking\Contracts\PaymentInitiation` directamente en un controlador— y ninguna guarda lo vería:
`ModuleBoundariesTest` exime la capa de entrega entera y permite cualquier `Contracts`, y
`ApiBoundariesTest` no prohíbe `open()`/`reopen()`.

## 5. Impacto en invariantes

**Ninguno se relaja, altera ni elimina** (CONVENCIONES §9.1 no aplica). Tres cambian de DIRECCIÓN
—el puntero de código, no la regla— y uno hay que proteger activamente:

| ID | Qué cambia |
|---|---|
| `AFORO-10` | El puntero pasa de `Purchase::confirmReservation` a `CheckoutOrchestrator::start()`, con `OrderCreator::checkoutHoldUntil()`. ⚠️ **Único sitio del CHECKOUT**, no de toda creación de pedidos: el pedido manual del panel sigue naciendo firme por diseño. |
| `PAY-04` | Mismos dos ficheros (`ReservationAdmissionPolicy::admitPaymentRetry()` + `PaymentInitiator::reopen()`); cambia quién los consume: el orquestador, y las superficies a través de él. El ORDEN entre ambos es lo que hay que proteger (§6, mutación 5). |
| `PAY-05` | No cambia de sitio, pero pasa a estar EN RIESGO por construcción: el rastro `orders.payment_init_failed` se escribe dentro del initiator, así que una transacción abarcadora en el orquestador lo haría desaparecer por rollback. §4.4 lo prohíbe explícitamente y §6 lo asevera. |
| `PAY-02` | Intacto, y es el cinturón de los huecos residuales: un `Payment` ya committeado sobre un pedido que luego expira lo cubre `isExpiredInPractice()`. |

`PAY-12`, `AFORO-01` (el lock literal) y `AFORO-02` quedan fuera del diff: este spec no entra en
`OrderCreator::lockSlots()` ni en el handler de vuelta.

## 6. Plan de verificación empírica

**Tests nuevos** — `tests/Feature/Sales/CheckoutOrchestratorTest.php` (futuro), uno por punto del
orden de §4.4:

1. la admisión va **antes** de crear y **consume** ficha;
2. el pedido nace **siempre** con `expires_at` en la ventana de `sales.hold_minutes` (`AFORO-10`);
3. el cobro se abre **sobre el pedido ya persistido**;
4. un cobro que no abre en `start()` **suelta** el pedido (`status=expired`);
5. un cobro que no abre en `retry()` **no toca** el pedido (sigue `pending`, hold extendido);
6. `ReservationException` **propaga** sin pedido a medias, y la ficha del limitador ya se consumió;
7. un cobro que no abre deja su rastro en `audit_logs` **y sobrevive** (`PAY-05`: prueba de que no
   hay transacción abarcadora).

**Verificación por MUTACIÓN** (obligatoria, DoD). Se mutará el orquestador y se reportará qué test
cae con cada una:

1. omitir el hold en `createPendingOrder` → debe caer (2);
2. `admitReservation()` → `mayReserve()` (la que no consume) → debe caer (1);
3. borrar la compensación de `start()` → debe caer (4);
4. añadir compensación al `retry()` → debe caer (5);
5. **invertir `admitPaymentRetry()` ↔ `reopen()` en `retry()`** → es el orden del que depende
   `PAY-04`: con `reopen()` primero se marcarían `SUPERSEDED` los intentos de un pedido cuyo hold
   aún no se ha validado ni extendido. Debe caer alguno.

**Contratos**: `ModuleContractsTest` gana (a) las dos aserciones de resolución del binding y (b) un
doble por puerto, que debe cambiar la conducta del consumidor real.

**Paridad (CE-5)**: pasan **sin cambiar aserciones** `Api\V1\OrdersTest` (sus 4 tests de orden),
`PurchasePanelTest`, `PurchaseLimitsTest`, `PurchaseRetryAndPollingTest`, `RedsysIdaTest`,
`PaymentRetryFromOrdersTest`, `ReservationPauseGuardTest`, `SidebarV2Test`, `ModuleContractsTest`.
Y se asevera que los dos endpoints siguen dando **502** cuando la pasarela no abre.

**Frontera**: `ModuleBoundariesTest`, `ApiBoundariesTest`, `CriticalPathGateTest` y
`CheckoutSequenceTest` verdes, publicando el diff de las baselines (debe ser vacío, CE-3).

**Concurrencia** (`INVARIANTES §6`, obligatorio por tocar el núcleo):
`php artisan purchase:verify-oversell --workers=16` y `php artisan redsys:verify-concurrency
--workers=16` sobre MySQL real, y push con `VERIFY_CONC=1`.
⚠️ **Honestidad sobre qué prueban**: `purchase:verify-oversell` llama a `OrderCreator` directamente,
así que **no ejercita el orquestador ni una vez**. Son control de no-regresión del núcleo, no
verificación de la secuencia; esta se verifica por mutación y por el ciclo e2e.

**Extremo a extremo**: ciclo por `curl` contra el servidor (crear pedido por API → abrir cobro →
reintentar) y compra por la web.

## 7. Revisión y decisión

**2026-08-13 — owner**: elige la mitad medida (orquestación ahora, driver a Fase 6) sobre las otras
dos opciones.

**2026-08-13 — revisión adversarial de 3 agentes independientes** (lentes: invariantes de dinero ·
arquitectura de módulos · riesgo de implementación). Veredictos: SÓLIDO-CON-CAMBIOS ×3. **Tres
hallazgos GRAVE, los tres unánimes o verificados, todos incorporados en esta v2:**

1. **La v1 era inaplicable**: el orquestador no puede capturar `PaymentInitiationException` sin
   violar su propio criterio rector CE-3, porque vive en `Payments\Exceptions` y Booking solo
   alcanza `Payments\Contracts`. → §4.2.a.
2. **CE-1 era inalcanzable**: las constantes `SOURCE_*` son de `PaymentInitiator` y las nombran las
   cinco superficies. → §4.2.b.
3. **El propio spec rompía el gate documental** (cita por línea + una entrada de `DECISIONES`
   inexistente). Corregido y `docs-check` verde antes de escribir código.

Cambios adicionales que salieron de la revisión: la prohibición explícita de transacción abarcadora
y el riesgo sobre `PAY-05` (§4.4, §5) · `deny()`/`ready()` en vez de `denied()` (§4.4) · el binding
por nombre de clase, sin el cual dos tests quedarían verdes-y-ciegos (§4.2.c) · CE-1/CE-2
convertidos de grep en guarda ejecutable (§4.7) · la mutación de `PAY-04` que faltaba (§6) ·
la honestidad sobre `verificationHoldUntil()` como código muerto y sobre lo que
`purchase:verify-oversell` NO prueba (§4.3, §6) · el inventario de paridad corregido (citaba un
test inexistente) · `AFORO-10` acotado al camino de checkout (§5) · la restricción de nombres de
método (§4.2.d) · el aviso de que el gate no cubre las superficies web (§4.6).

**2026-08-13 — IMPLEMENTADO y verificado.** Los seis criterios de éxito, medidos:

| CE | Resultado |
|---|---|
| CE-1 / CE-2 | ✅ `CheckoutSequenceTest` verde: fuera de `app/Domain` nadie nombra la ida ni llama a `createPendingOrder`/`releaseAfterFailedPaymentStart`. Una sola excepción con nombre, el verificador de concurrencia |
| CE-3 | ✅ **`git diff` de `ModuleBoundariesTest` = 0 líneas**. Ni una entrada nueva en `SEAM`/`LEGACY`/`DEFERRED`/`PENDING` |
| CE-4 | ✅ 5 mutaciones, 5 rojos: quitar el hold (4 tests) · `admitReservation`→`mayReserve` (3) · borrar la compensación (3) · añadirla al reintento (2) · invertir admitir↔reabrir (2) |
| CE-5 | ✅ Suite **2467 verde** (9567 aserciones) sin cambiar ni una aserción de los tests de las cuatro superficies |
| CE-6 | ✅ `CheckoutOrchestrator` en `CRITICAL_RE` y en `CRITICAL_FILES`; `ReservationCheckout` en `CRITICAL_SYMBOLS` |

Verificación adicional: los dos verificadores de concurrencia sobre MySQL real con 16 workers (1
compra / 15 `sold_out`; 1 `authorized` / 15 `idempotent_paid`) y el ciclo completo por `curl` contra
el servidor en las cuatro superficies —crear (201 con `expires_at`), reintentar (200 con el hold
extendido y el intento previo `superseded` comprobado en BD), `payment-status`, y el reintento web
devolviendo su formulario firmado—.

**Entrada final**: `DECISIONES #37`.
