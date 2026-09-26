# [SPEC] Complementos que se venden DESPUÉS de reservar — el extra que se elige en el post-form

> Estado: 🟦 **REVISADA DE FORMA ADVERSARIAL Y CORREGIDA** (diseño; ninguna línea de código escrita) ·
> Última actualización: 2026-09-03 · Decisión asociada: `DECISIONES #413`.
> Verificado contra código y BD: 2026-09-03 (catálogo real, `AddonResolver`, `OrderItemEditor`,
> `LineFacts`, `OrderBook`, el pivote y su panel, post-form web y API).
> Se invalida si: cambia `#244` (nada se cobra online post-reserva), cambia la forma del LIBRO
> (`specs/desglose-libro.md`) o el post-form deja de ser la superficie del cliente.
> Carril: producto/reservas. Autor: agente, 2026-09-03.
>
> ❗❗❗ **EMPIEZA POR §8: la revisión adversarial de seis lentes, que encontró TRES afirmaciones
> FALSAS de esta misma spec y dos bloqueantes.** Las correcciones están ya integradas en el cuerpo;
> §8 registra qué cambió y por qué, incluidos los **tres desacuerdos entre lentes** que hubo que
> resolver midiendo.

## §0 · Antes de tocar

- **Código completo** (`#413`, las cuatro tandas); queda el OJO del owner. **Empieza por §9.4** (la T3 y sus
  dos defectos de navegador) y §8 (la revisión adversarial: tres afirmaciones propias eran falsas).
  `PostFormAddons` está en el `CRITICAL_RE` → `VERIFY_CONC=1` y `postform:verify-concurrency` (escenarios
  `addons` y `cross`). Se paga EN EL PARQUE (`#244`).
- **El eje es `product_addons.stage`** (`booking` | `postform`) y significa **cuándo se VENDE**, no dónde se
  ve: un `postform` no nace nunca con el pedido, tampoco en el alta manual. **La puerta del reconciliador NO
  es el eje sino `LineFacts::birthValue() === 0`** (D9): el eje es configuración mutable, y cambiar «Tarta» a
  `postform` habría puesto en manos del cliente líneas ya cobradas con el libro en verde. Quitar es NEUTRO en dinero.
- **Seis guards del enganche** (§4.3) y `max_qty` obligatorio · **R1**: el estado deseado gobierna sólo lo
  OFRECIDO (un `<input disabled>` no se envía; lo fuera de plazo no se ofrece) · **R2**: la línea conserva su
  `unit_price` · escrituras ASIMÉTRICAS: alta, subida y bajada escriben `recordEdit`, retirar NO.
- **El testigo es `updated_at` y `submitGuestForm()` lo mueve en la misma petición**: la regla vive en
  `AuthorizesGuestForm::addonsExpectedVersion()` (nuestra escritura no es un tercero); en la suite salía verde
  por la precisión de segundo. La fiesta pasada CIERRA los extras, no los esconde.
- **La puerta es `acceptsGuestForm()`, no `isPack()`** (`[owner]`) · dos limitadores (IP y `guest-form`
  por reserva) · orden de locks del subsistema: `orders → order_items → hijas` (el interbloqueo se reprodujo).
- Tres listas blancas del panel entre el formulario y la fila, y las tres callan al olvidarse (§8).
- Anexo al final con la fila del enrutador.

## 0. En una frase

Un complemento puede declararse **de venta POSTERIOR**: no nace nunca con el pedido, y el cliente lo
elige en su formulario post-reserva. Se paga **en el parque**, como cualquier gestión de dinero
post-reserva (`#244`).

## 1. Contexto y problema

`[owner, 2026-09-03]`: *«el cliente reserva un cumpleaños, va a su form post reserva y puede añadir
ahí complementos tipo cubo de refrescos para los adultos, tapas para los adultos… etc.»*

Hoy eso **no se puede ofrecer**, y el motivo es exactamente uno: un complemento se elige en el paso 3
del embudo y **no hay ningún eje que diga cuándo se vende**. Las consecuencias de no tenerlo:

- La bebida y la comida de los adultos hay que decidirlas **al reservar**, semanas antes, cuando el
  cliente todavía no sabe cuántos adultos van a venir — que es exactamente el problema que el
  post-form existe para resolver (`sistemas/POSTFORM-INVITADOS.md` §1).
- O se ofrecen igual en el embudo y **saturan la compra** con decisiones que no tocan todavía.

### 1.1 · Lo MEDIDO (2026-09-03, sobre la BD local; re-verificado por la lente 6)

| Hecho | Medida | Dónde |
|---|---|---|
| Complementos en catálogo | **8** (5 reales, 2 portadores de fiesta mixta ocultos, 1 hora extra) | `ticket_types` `type=addon` |
| Enganches | **29**, *todos* `quantity_mode=fixed` | `product_addons` |
| Enganches incluidos / obligatorios / en grupo / con `max_qty` / con `requires` | **0 / 0 / 0 / 0 / 0** | `product_addons` |
| **De los 29 enganches, cuántos cuelgan de un producto CON post-form** | **8** (los dos packs); los otros **21** cuelgan de entradas | ⚠️ dato nuevo de la revisión: §4.2·bis |
| Packs con post-form | **2** (Cumpleaños Jump y Kids): 5 campos por invitado, 2 generales, 4 complementos | `ticket_types.guest_fields` |
| Líneas hijas en la base | **13 en total · 12 VIVAS · 1 cancelada** | `order_items` con `parent_item_id` |
| De esas 13, cuántas nacieron DESPUÉS del pedido | **5** (`nac = 0`) · las otras **8** nacieron con él (`nac = charged`) | `LineFacts::forItem()` sobre las 13 |

▶ **Que los 29 enganches sean `fixed`, sueltos y sin tope importa**: el eje nuevo nace sobre un
catálogo homogéneo, y su valor por defecto deja los 29 **idénticos por construcción**.

### 1.2 · ❗ Lo que YA existe, y es lo que hace este diseño barato

**El mecanismo de «añadir un complemento a una reserva ya pagada» está construido y en producción.**
Lo ejerce el panel: `OrderItemEditor::edit()` crea la línea hija y `Order::recordEdit(+Δ)` deja el
hecho; el LIBRO (`specs/desglose-libro.md`) lo pinta como una línea «+2 Cubo de refrescos» con su
fecha, el Total sube y el saldo pasa a **«A pagar en el parque»** (`OrderBook`, `MovementLabel::edit`).

Y hay un precedente aún más cercano: **el post-form YA mueve dinero hoy.** Al guardar las edades,
`OrderItem::submitGuestForm()` llama a `MixedPartySurcharge::reconcile()`, que bajo lock crea /
ajusta / cancela una línea hija con su ajuste, de forma idempotente y simétrica. Esta feature es
**el mismo patrón con otro disparador**.

▶ Lo que falta es sólo: **(a)** un eje que diga *cuándo se vende* un complemento y **(b)** la puerta
del cliente en el post-form.

### 1.3 · La propiedad que lo hace seguro — y es un HECHO DE LA LÍNEA, no del eje

    nac(i)        = fila(i) − Δ(i)                  (LineFacts::birthValue)
    online_nac(i) = max(0, nac(i) − reparto(i))     (LineFacts::onlineAtBirth)

Una línea creada DESPUÉS del pedido lleva un ajuste `edit` de su importe exacto, así que
**`nac = 0` y `online_nac = 0`: no aportó ni un céntimo al cobro online**. De ahí sale la propiedad
que gobierna toda la feature:

> **Quitar un complemento que nació DESPUÉS del pedido es NEUTRO en dinero.** El Total baja lo mismo
> que subió, no hay devolución que hacer, y las cuatro identidades del libro siguen cerrando.

▶ **Verificado sobre datos reales** (lente 6, las 13 hijas de la BD): las 5 nacidas post-pedido dan
`birthValue = 0` y `onlineAtBirth = 0`; las 8 vendidas en el embudo dan `birthValue = charged`.

⚠️⚠️ **CORRECCIÓN DE LA REVISIÓN, y es el cambio de diseño más importante que trajo.** La primera
versión de esta spec decía que la propiedad la garantizaba el EJE («un `postform` no nace nunca con
el pedido»). **Es insuficiente, porque el eje es configuración MUTABLE y la propiedad es un hecho de
la fila.** El día que el parque haga lo que esta feature quiere que haga —pasar «Tarta» de `booking`
a `postform`—, toda fiesta ya vendida con tarta comprada en el embudo pasa a tener su línea dentro
de la oferta, y esa línea tiene `nac > 0`. Medido sobre un pedido real (`R-7MHJC1`): el saldo pasa de
`settled: 0` a **`refund_at_park: −4,00 €`**, y **el libro sigue cerrando**, así que la guarda
«las cuatro identidades cierran» pasaba en VERDE con el defecto puesto.

▶ **Por eso la puerta del reconciliador NO es el eje: es `LineFacts::forItem($order, $hija)->birthValue() === 0`.**
Es un hecho de la fila, no envejece con el catálogo, sobrevive a cualquier cambio de fase y convierte
la propiedad en el **predicado que decide** en vez de en una consecuencia esperada. Es la misma
doctrina que el sello de `specs/cumple-mixto.md` §21: *lo que se compró ayer no lo reescribe el
catálogo de mañana.*

## 2. Objetivo

Que el parque pueda declarar, **desde el panel y sin tocar código**, que un complemento se vende
después de reservar; que el cliente lo elija —y lo cambie— en su post-form mientras el plazo esté
abierto; y que el dinero aparezca donde ya aparece todo lo demás: una línea con su fecha en el libro
y un saldo que se liquida en el parque.

**Criterios de éxito medibles**

1. Los **29 enganches actuales no cambian de conducta**: medido antes y después, la oferta del
   embudo y el cobro son idénticos (caso de CONTROL).
2. Un complemento de venta posterior **no se puede comprar en el embudo** ni por la web, ni por la
   API, ni en el alta manual del panel — tampoco con una cesta forjada — y **no se anuncia en la
   landing**.
3. Añadir uno deja el saldo del pedido en «A pagar en el parque» por su importe exacto; quitarlo lo
   devuelve al valor previo **al céntimo**, sin generar ninguna devolución, **y el libro sigue
   cerrando DESPUÉS de quitar** (no solo antes).
4. Una línea con `birthValue() > 0` **no es gobernable por el cliente**, aunque su enganche sea
   `postform`.
5. N guardados simultáneos del post-form escriben **una** línea, no N; y un guardado del cliente
   concurrente con una cancelación o un reembolso del operador **no produce ningún interbloqueo**
   (verificadores con `pcntl` sobre MySQL, **vistos FALLAR** sin la defensa).
6. Pasado el plazo de un complemento, ese complemento no se añade **ni se quita**, y un guardado
   normal de los demás **no lo cancela**.

**Fuera de alcance, a propósito**

- **Cobro online post-reserva** (`[DECIDIDO owner, 2026-09-03]`, Q1): se paga en el parque. Cambiarlo
  contradice `#244` y es una tanda propia sobre `PAY-01`/`PAY-02`.
- **Complementos que OCUPAN aforo** (la hora extra): prohibidos en venta posterior — §4.3·5.
- **Reservas sin post-form**: no hay superficie de cliente donde ofrecerlo. No se construye una;
  la puerta es `acceptsGuestForm()` y la feature viaja sola el día que esa puerta se abra (§4.2).
- **Arreglar el reloj de `isFinishedInPractice()`** (§4.9): preexistente, con ficha propia en
  `DEUDA.md`.
- **Extras con TEXTO LIBRE** (una nota tipo «tarta sin gluten»): sería dato de salud del art. 9 y
  cambia el análisis de RGPD entero. Un extra es un producto del catálogo con una cantidad, y nada más.

## 3. Opciones consideradas

| | Qué es | Por qué NO (o sí) |
|---|---|---|
| **A · Un producto por variante** | «Cumpleaños con cubo de refrescos», «…con tapas» | ⛔ Es exactamente lo que el owner rechazó al diseñar la hora extra (`specs/hora-extra.md` §1): satura el catálogo y multiplica las combinaciones |
| **B · Campo `postform` en `ticket_types`** | el complemento declara «me vendo después» | ⛔ Rompe el white-label por enganche: la tarta podría querer venderse al reservar en un pack y después en otro. Y contradice la doctrina del pivote heredada del origen (*«config por enganche, no global»*, migración `2026_06_05_000002`) |
| **C · Reutilizar `OrderItemEditor::edit()` desde el post-form** | el cliente entra por la misma puerta que el operador | ⛔ Exige permiso `orders.edit_item`, token optimista y un `User` autenticado —el post-form se abre **sin sesión**, con firma— y es la clase más delicada del sistema (`CRITICAL_RE`, lock de zona/día). Conducirla desde una superficie pública es superficie de ataque que nadie pidió |
| **D · Eje `stage` en el PIVOTE + servicio de dominio propio, con la puerta en el HECHO de la línea** ✅ | el enganche dice cuándo se vende; un servicio calcado de `MixedPartySurcharge` reconcilia, y solo gobierna líneas con `birthValue = 0` | ✅ Espeja `event_fields.stage`, respeta la doctrina del pivote, deja los 29 enganches intactos por defecto y **no toca aforo** |

## 4. Diseño elegido — D

### 4.1 · El eje: `product_addons.stage`

| valor | qué significa | por defecto |
|---|---|---|
| `booking` | se vende **al reservar** (y el panel puede añadirlo después, como hoy) | ✅ sí — los 29 enganches actuales |
| `postform` | **no nace nunca con el pedido**; se añade después | — |

▶ **La definición es «cuándo se VENDE», no «dónde lo ve el cliente»**, y de ella sale una tabla de dos
por dos sin zonas grises:

| | nace con el pedido | se añade después |
|---|---|---|
| `booking` | ✅ checkout web/API **y** alta manual del panel | ✅ sólo el **panel** (como hoy) |
| `postform` | ⛔ **nunca, en ninguna superficie** | ✅ panel **y cliente** (post-form) |

⚠️⚠️ **La casilla «nunca» incluye el ALTA MANUAL del panel, y es deliberado.** Sería tentador dejar
que el mostrador venda un cubo de refrescos dentro del pedido que está creando; hacerlo daría a esa
línea `nac > 0`. El operador tiene el camino de siempre: crear el pedido y añadírselo desde
«Gestionar → Complementos», que es una edición y nace con `nac = 0`. *Una excepción cómoda en el
mostrador convertiría una propiedad demostrable en un caso particular.*
▶ **Y sale barato**: medido, `ManualOrderFulfiller` pasa por `OrderCreator::createPendingOrder()`, o
sea por `AddonResolver::resolve()`. Filtrando ahí, el dominio ya lo impide; lo que hay que añadir es
que la **pantalla** no lo ofrezca (§4.4), o el operador monta el pedido entero y recibe un «no
disponible» sin motivo.

⚠️ **El vocabulario es prestado a propósito**: `event_fields` ya usa `stage` con `booking`/`postform`
para decir en qué fase se captura un campo (`sistemas/POSTFORM-INVITADOS.md` §3.2).

⚠️ **No hay valor `both`, y es una decisión.** Con `both`, un complemento podría comprarse en el
embudo y modificarse luego: reducir esa línea sí debería dinero, y toda la feature necesitaría
distinguir por unidad qué se cobró y qué no. El día que haga falta, entra **de forma aditiva**.

⚠️⚠️ **CAMBIAR LA FASE DE UN ENGANCHE CON LÍNEAS VENDIDAS NO LAS MIGRA** (§1.3, corrección de la
revisión): el reconciliador gobierna por `birthValue() === 0`, así que una tarta comprada en el
embudo sigue siendo del parque aunque su enganche pase a `postform`. **El panel avisa** al cambiar la
fase si hay líneas vivas de ese enganche, y esas líneas se pintan en el post-form **en solo lectura
con su motivo** («esto se compró al reservar; para cambiarlo, llámanos»).

### 4.2 · La puerta del cliente NO es «es un pack»

`[DECIDIDO owner, 2026-09-03]` (Q4): *«no es por producto, sería por postform más bien»*.

La sección de extras se ofrece **exactamente donde ya se ofrece el post-form**: la puerta es
`OrderItem::acceptsGuestForm()` —el mismo gate que protege la página y la API—, nunca `isPack()`.

⚠️ **Precisión de la revisión**: `acceptsGuestForm()` son **cinco** condiciones, no tres —línea
principal · no cancelada · `isPack()` · `guestFields() !== []` · pedido `paid`— y **no es la misma
puerta que el `readonly`**, que lo decide `isFinishedInPractice()`. Con el corte de §4.6 son **tres**
puertas, no dos, y §4.6 declara cuál manda.

▶ Hoy eso significa cumpleaños, porque `guestFormStatus()` exige pack con `guest_fields`; pero es
una **consecuencia**, no una restricción escrita en esta feature.

### 4.2.bis · ⚠️ Un enganche `postform` sobre un producto SIN post-form es un complemento MUERTO, y son el 72 %

Medido: **21 de los 29 enganches cuelgan de entradas**, que no tienen post-form. Un `postform` ahí
crea un complemento que **nadie puede comprar jamás**: ni el embudo (filtrado) ni el cliente (no hay
pantalla), sólo el operador desde «Gestionar».

▶ **No se rechaza: se AVISA** —rechazarlo ataría la configuración a `isPack()`, justo lo que Q4
evita—. Pero el aviso al guardar **no basta**: una semana después nadie recuerda cuál está muerto.
Por eso `pivotBadges()` gana una **insignia de fase** (§4.7·3), que es lo que hace el estado
auditable de un vistazo. *Precedente medido: `max_qty` tampoco tiene insignia, y hay **0** filas con
`max_qty` en toda la base — un campo sin insignia es un campo que nadie usa.*

### 4.3 · Los guards del enganche `postform` (y su cinturón)

Un enganche `postform` **no puede** ser, y cada prohibición cierra un agujero concreto:

| Prohibido | Por qué |
|---|---|
| 1 · `is_mandatory` | Un obligatorio se **auto-inyecta** sin que nadie lo pida (`AddonResolver::resolve` paso 2). Inyectar una deuda después de la venta, sin un clic, es indefendible |
| 2 · `is_included` | «Incluido» significa gratis dentro del pack, y eso se decide **al vender**. Además `free_quantity > 0` rompería la igualdad `chargedSubtotalCents == Δ` de la que vive §1.3 |
| 3 · `quantity_mode = per_guest` | La cantidad seguiría al nº de **invitados** (los niños). El caso del owner es *para los adultos*: sería un número equivocado con aspecto de correcto |
| 4 · `choice_group` | Un grupo excluyente **siempre tiene un elegido** (`groupDefault()`), y post-venta el estado normal es «ninguno», que un grupo no sabe expresar: el cliente vería una opción marcada que él no marcó. ⚠️ **La justificación original de esta spec era distinta y era FALSA** — decía que creaba «un cargo que nadie pidió», y medido no puede: con el pivote `fixed`, no obligatorio y no incluido, el elegido entra con `qty = 0` y `effectiveQuantity()` lo descarta antes de emitir fila. La prohibición se conserva **por la razón de presentación, que sí es cierta**, y es más débil: si algún día un grupo con opción «ninguno» explícita hace falta, este guard es el primero que cae |
| 5 · complemento con `occupies_after_parent` | Ocupar aforo después de reservar exige el lock de zona/día, la franja de aterrizaje y el recálculo de la oferta. **Esta feature no toca aforo, y esa es la mitad de su coste** |
| 6 · `requires_addon_id` de OTRA fase | El requisito nunca estaría en la selección de esta fase, así que el dependiente quedaría **invisible sin fallar** |

**Y `max_qty` es OBLIGATORIO en un enganche `postform`** (D3, `[DECIDIDO owner]`): el enlace del
post-form viaja por correo y puede reenviarse, así que la deuda máxima que un tercero puede crear
tiene que estar **declarada por el parque**.

⚠️⚠️ **Los guards viven en el modelo Y se re-validan en la autoridad**, por la lección de `#299`: los
eventos de Eloquent **no ven `Query\Builder::update()` ni SQL crudo**, y los **tres seeders** del
repo escriben el pivote justamente así (medido). Una fila torcida por la puerta de atrás no puede
degradar a «se vende normal»: **no se ofrece y no se vende**.
▶ **El cinturón cubre también `max_qty`**: `AddonResolver::resolve(stage: postform)` **descarta la
fila sin tope**, exactamente como descarta un ocupante con `hasSaneOccupancyConfig()` falso. Sin eso,
`max_qty` obligatorio es una regla de formulario y no una defensa: medido, `effectiveQuantity()` solo
acota si `max_qty !== null`, y el `20` de `viewModel()` es UI (solo apaga el botón `+`).

⚠️⚠️ **El guard 5 son DOS MITADES, y la segunda no vive en el pivote.** `occupies_after_parent` es
columna de `ticket_types`, así que encender el interruptor a un complemento **ya enganchado** como
`postform` no lo ve `ProductAddon::saving()`. El sitio exacto **ya existe**: el bloque de
`TicketType::booted()` que consulta `ProductAddon` buscando `per_guest`/`is_mandatory`/pack (la
guarda cruzada de `#324`) — basta añadirle `stage = 'postform'` a esa misma consulta. Lo mismo, con
consulta inversa, para el guard 6.

⚠️ **La CARA AMABLE va antes que el guard, y esta spec no lo decía.** En la hora extra los guards son
inalcanzables desde la UI (los campos prohibidos se ocultan); aquí caen sobre casillas que el
operador marca en el mismo modal, y **no hay un solo `catch` en el catálogo de Filament**: un
`InvalidArgumentException` sale como pantalla de error. Al marcar `stage = postform` (que tiene que
ser `->live()`) el formulario **oculta** `is_included`/`is_mandatory`/`choice_group`, fija
`quantity_mode` en su única opción, hace `max_qty` requerido y saca de «Requiere» los enganches de la
otra fase. El guard queda como autoridad, no como mensaje de error.

### 4.4 · La autoridad: `AddonResolver` gana un eje — y la unidad NO es «el panel», es VENDER vs GESTIONAR

`AddonResolver::resolve()` y `viewModel()` son la fuente que comparten el presupuesto, el cobro, la
oferta y el alta manual. El eje entra ahí como **parámetro explícito** (`stage`), con `booking` por
defecto.

⚠️⚠️ **El filtro NO va en el eager-load ni dentro de la relación `addons()`.** `resolve()` y
`AddonOfferReader` leen `$product->addons` —el atributo **ya cargado**—, así que filtrar al cargar
convertiría la fase en un contrato implícito de quien hizo la consulta: la clase exacta de fallo
invisible que este eje existe para evitar. Y la relación la comparten 12 clases, una de ellas el
editor del panel, que dejaría de encontrar la línea que tiene que mover.

**La lista, por símbolo, con qué hace cada uno** (sustituye a la cifra «12 consumidores» de la
primera versión, que era una cuenta y no un mapa):

| Símbolo | ¿Filtra? | Por qué |
|---|---|---|
| `OrderCreator::createPendingOrder()` | **`booking`** | es el cobro: la autoridad de que un `postform` no nace con el pedido |
| `CartPricer` | **`booking`** | el presupuesto tiene que decir lo mismo que el cobro |
| `AddonOfferReader::resolve()` | **`booking`** | el paso 3 del embudo y `POST /catalog/products/{id}/addons` |
| `CatalogReader::addons()` | **`booking`** | la ficha pública del producto (web y API) |
| **`LandingAddonPresenter::rows()`** | **`booking`** | ⚠️ **lo olvidaba la primera versión**: anuncia los complementos bajo la tarjeta de producto en tres vistas, y su propio docblock declara *«lo que se anuncia es lo que se puede comprar»*. Sin esto la landing ofrece el cubo de refrescos bajo el cumpleaños y el embudo lo rechaza |
| **`CreateManualOrderPage`** | **`booking`** | ⚠️ **la contradicción que la revisión encontró**: la primera versión decía «el panel no filtra» y a la vez que el alta manual no puede venderlo. El alta manual **VENDE**, así que filtra como el embudo |
| `PostFormAddons` (futuro) | **`postform`** | la puerta del cliente |
| `OrderItemEditor` · `ItemEditPricing` · `ViewOrder` («Gestionar → Complementos») | **no filtra** | **GESTIONAN una línea que ya existe**; el eje gobierna la venta, no la capacidad del operador |
| `CartOccupants` · `AvailabilityReader` | no filtra | aforo: leen la línea, no la ofrecen |

⚠️ **`CatalogReader` y `LandingAddonPresenter` REPLICAN la normalización de `viewModel()`** en vez de
llamarla (lo dice el comentario del propio `CatalogReader`). Añadir el eje al resolutor **no llega
solo** a esas dos: hay que tocarlas, y por eso están en la tabla.

▶ **Guarda de censo**: una lista declarada de consumidores con su decisión, que se pone **roja**
cuando aparece uno nuevo sin declarar — el patrón de `AnonymizeCoversEveryUserColumnTest`.

### 4.5 · El servicio de dominio (futuro)

`App\Domain\Booking\Services\PostFormAddons` (futuro), calcado de `MixedPartySurcharge`.

#### 4.5.1 · Las TRES escrituras, que NO son simétricas

⚠️⚠️ **La primera versión decía «por cada gesto, un `recordEdit(±Δ)`» y eso ROMPE EL LIBRO.** Medido:
`chargedSubtotalCents()` **no mira `cancelled_at`**, así que una hija cancelada conserva su importe y
el libro emite por su cuenta un movimiento `−fila`. La aritmética, con un cubo de 2 × 12,00 €:

| gesto | qué se escribe | `charged` | `Δ` | `nac` | movimientos del libro |
|---|---|---|---|---|---|
| **alta** | línea nueva + `recordEdit(+2400)` | 2400 | +2400 | **0** ✓ | `+2400` |
| **subir** 2→3 | `quantity=3` + `recordEdit(+1200)` | 3600 | +3600 | **0** ✓ | `+2400 +1200` |
| **bajar** 3→2 | `quantity=2` + `recordEdit(−1200)` | 2400 | +2400 | **0** ✓ | `… −1200` |
| **retirar** | `markCancelled()` y **NINGÚN** `recordEdit` | 2400 | +2400 | **0** ✓ | `… −2400` (lo pone el libro) |

▶ **Bajar SÍ escribe; retirar NO.** Sin el movimiento de la bajada, `nac` caería a **−12,00 €** e
`I1` fallaría; con un movimiento en la retirada, el valor se restaría **dos veces** e `I1`/`I3`
fallarían. Las dos roturas tienen la misma consecuencia: el pedido entero pasa a «en revisión» y
**al cliente se le oculta su desglose** (`#132`) por haber tocado un cubo de refrescos.
⚠️ Y por eso **R5 («simétrica») se corrige**: bajar y subir son el mismo camino; **retirar es otro**.

⚠️ **Δ = 0 no se escribe**: `Order::recordEdit()` **lanza** con delta cero. Un complemento a 0 € es
configuración válida hoy, así que el servicio salta los deltas nulos explícitamente (la línea sigue
con `nac = 0`: no hay hecho que escribir).

⚠️ **El hecho se ata SIEMPRE a la HIJA, nunca al principal.** Medido: atarlo al principal deja las
cuatro identidades cerrando —son sumas, no ven una permuta entre líneas— y hace que el panel
**ofrezca devolver 30,00 € de un cubo que nadie pagó online**. El *fallback* defensivo del editor
(`$target = $childItem ?? $item->fresh()`) **no se hereda**: si la hija no se resuelve, revienta la
transacción. Perder el cobro es preferible a atribuirlo mal.

⚠️ **El contexto del movimiento es `changes.quantity_change` atado a la hija**, no `addon_change`
sobre el padre: medido, `MovementLabel::edit()` **no lee la clave `removed`** ni las bajadas, así que
`addon_change` daría «Cambios en Cumpleaños Jump» para un −12,00 €. Con `quantity_change` sobre la
hija el libro ya sabe decir **«Cubo de refrescos: 3 → 2»**. ⚠️⚠️ Y hay que hacerlo **a sabiendas**:
`specs/cumple-mixto.md` T4 prohíbe `changes.*` en el contexto del ajuste **del crédito mixto** por
una razón distinta (haría reconstruir un original que no existe). Aquí no aplica —no hay crédito—,
pero la prohibición está escrita y quien lea las dos specs tiene que ver esta nota.

#### 4.5.2 · Las reglas del reconciliador

- **R0 · Solo gobierna lo que nació DESPUÉS** (§1.3): hijas con `LineFacts::birthValue() === 0`.
  Cualquier otra se pinta en solo lectura con su motivo. **Es la regla que sustituye al eje como
  garantía.**
- **R1 · Añadir y subir solo sobre lo OFRECIDO; bajar y retirar sobre cualquier línea gobernada.**
  ⚠️ La primera versión decía «el estado deseado manda sobre lo ofrecido y lo demás se conserva», y
  eso **contradice a Q3**: si el parque desactiva «Tapas» del catálogo, el cliente se queda con una
  deuda de 36,00 € que **no tiene ningún gesto para retirar**. Distinguir las dos direcciones cierra
  la contradicción sin tocar §1.3.
- **R2 · La línea conserva su `unit_price`.** Subir de 2 a 3 cobra la tercera **al precio de la
  línea** (el patrón de `ItemEditPricing::computeAddonPricing`). Quitarla y volver a añadirla **sí**
  tarifica a hoy: es una línea nueva. ⚠️ Los complementos se tarifican a `Carbon::today()` en el
  camino de venta y a la fecha de la franja en el editor: el servicio **elige `today()` y lo dice**,
  por coherencia con el `[DECIDIDO owner]` de `specs/hora-extra.md` §4.11.
- **R3 · Nunca toca una línea gobernada por otro mecanismo** (`MixedPartySurcharge::governedLineIds`).
  ⚠️ Y los dos **portadores** de fiesta mixta no pueden engancharse como `postform` (séptimo guard):
  el cliente crearía una línea de ese mismo producto sin la marca del ajuste, indistinguible en la
  ficha e invisible para el reconciliador mixto.
- **R4 · Idempotente**: el mismo estado deseado no escribe nada, no audita y no notifica.
  ⚠️ **Su mitad ajena ya es falsa y no es de esta tanda**: `submitGuestForm` sella
  `guest_form_completed_at` con `now()` en cada guardado completo, así que **bumpea `updated_at`**
  del ítem — que es el token optimista de cinco puertas del operador. Ficha en `DEUDA.md`.
- **R5 · Bajar es el mismo camino que subir; retirar es otro** (§4.5.1).

#### 4.5.3 · Desde dónde se invoca, y qué es atómico con qué

⚠️ **La primera versión no lo decía, y es donde se pierde el trabajo del cliente.** El post-form es
**un** formulario con **un** botón: un guardado trae fichas, generales y extras.

1. `submitGuestForm()` guarda datos y reconcilia el suplemento mixto (como hoy, sin cambios).
2. `PostFormAddons::reconcile()` corre **después**, en su propia transacción.
3. **Un fallo del paso 2 NO pierde el paso 1** —ya está guardado— y **se le dice al cliente**:
   «tus datos se han guardado; los extras no se han podido actualizar: *motivo*». Es una
   no-atomicidad deliberada, y la alternativa es peor: una excepción del resolutor (un id que dejó de
   ofrecerse) tumbaría el guardado de los nombres y alergias, que es la razón de ser del formulario.
4. **La notificación va POST-COMMIT**, fuera del lock — el patrón exacto de `MixedPartySurcharge`
   (`PAY-14`: con `QUEUE_CONNECTION=sync` un envío dentro de la transacción sostendría los locks
   durante un viaje SMTP).

#### 4.5.4 · ❗❗❗ El ORDEN DE LOCKS — la afirmación FALSA que la revisión midió

⚠️⚠️ **La primera versión afirmaba: «`orders` va siempre después de `order_items` en los dos: no hay
inversión posible, y por tanto no hay interbloqueo que diseñar». ES FALSO, y hay interbloqueo real.**

Verificado en el código: `OrderItemCanceller::cancel()` toma **`Order` primero** y el ítem después;
`Order::executePartialRefund()` hace lo mismo en sus dos transacciones; `executeFullRefund()` y la
acción «Cancelar pedido» del panel, también. Son **cuatro** caminos de producción que van al revés
que el servicio propuesto. Reproducido con dos conexiones MySQL reales:

    [A] locked order_items#569
    [B] locked orders#451
    [A] got orders#451
    [B] ERROR: SQLSTATE[40001] ... 1213 Deadlock found when trying to get lock

▶ **Y la inversión YA EXISTE sin esta feature**: la FK de `order_adjustments` hacia `orders` obliga a
un lock compartido sobre la fila del pedido, así que un cliente guardando edades y un operador
cancelando ya pueden chocar hoy. Esta feature lo empeora en tres ejes: el lock pasa de compartido a
exclusivo, el disparo pasa a ser frecuente (steppers), y la superficie es pública y reenviable.

▶ **La salida, y es una regla del subsistema, no un parche**: **`orders` → `order_items` → hijas**,
declarado y único. `PostFormAddons` toma `Order::lockForUpdate()` como **primera** sentencia y el
ítem después, igual que `OrderItemCanceller`. Con eso, el `recordEdit` anidado (que es un SAVEPOINT y
retiene el lock hasta el commit exterior) ya no adquiere nada nuevo.
⚠️ **Coste asumido y dicho**: con `orders` bloqueado primero, dos reservas del mismo pedido no
guardan su post-form a la vez. Es contención, no incorrección.
⚠️ Ningún `DB::transaction` del repo pasa `$attempts > 1` (medido), así que un 1213 sube como
excepción: por eso la salida es el ORDEN, no el reintento.

#### 4.5.5 · Lo que se re-valida BAJO EL LOCK

`SEC-04` aplicado al tiempo: entre pintar y guardar puede pasar cualquier cosa. Dentro del lock se
re-comprueban, además de la oferta: `acceptsGuestForm()`, la cancelación de la línea y del pedido,
`isFinishedInPractice()` y **el corte de CADA complemento**. La primera versión solo nombraba la
oferta.

⚠️ **El actor**: quien entra por enlace firmado no tiene sesión, así que el actor del ajuste es el
titular del pedido (`$order->user`), igual que hace `submitGuestForm`. **El CANAL viaja en el
`context` del ajuste** (`via: signed_link|account|panel`), no solo en el audit: es lo que le da al
operador la frase que necesita en el mostrador. ⚠️ Medido: `guestFormVia()` comprueba la firma
**primero**, así que un titular autenticado que llega por el correo también sale `signed_link`.

### 4.6 · El plazo de corte — `product_addons.postform_cutoff_hours`

`[DECIDIDO owner, 2026-09-03]` (Q2): **N horas antes, configurable POR COMPLEMENTO**.

- Columna nueva en el pivote. **OBLIGATORIA en un enganche `postform`**, por el mismo argumento que
  `max_qty` (D10, derivada de la revisión): *la ventana en la que un tercero con el enlace puede
  crear deuda tiene que estar declarada por el parque*. **`0` es un valor válido** y significa «hasta
  que empiece la fiesta».
  ⚠️⚠️ **Esto CORRIGE a la primera versión**, que la hacía nullable con `null` = «hereda el cierre
  del post-form». Tres lentes lo cazaron: ese cierre es `isFinishedInPractice()`, o sea **el reloj
  torcido de §4.9** — con el valor de fábrica el cliente podía **quitar** un cubo hasta 2 h después
  de acabada la fiesta, que es exactamente lo que D5 existe para impedir. *Un `null` que hereda una
  puerta rota hereda su defecto.*
- Se mide contra el **inicio de la franja** con **`DisplayTime::now()`** (`AFORO-09`).
  ⚠️ **Trampa de implementación**: el patrón que se podría copiar (`SlotOffer::passesIntradayFloor`)
  esquiva la zona horaria **comparando cadenas**, y con un corte de N horas hay que **restar**. Un
  `CarbonImmutable::parse($date.' '.$start)->subHours($n)` **reproduce el bug de §4.9** justo en la
  pieza que promete no heredarlo: es `Carbon::parse($date.' '.$start, DisplayTime::timezone())`.
- El corte gobierna **las dos direcciones**: pasado el plazo no se añade **ni se quita** (D5).
- **De las tres puertas manda la más estricta**: `acceptsGuestForm()` abre el formulario,
  `isFinishedInPractice()` lo cierra, y el corte cierra **los extras** antes. Nunca al revés.

⚠️⚠️ **EL ESTADO ES POR FILA, NO POR SECCIÓN, y de ahí sale una trampa que se habría construido.**
Con «tapas 48 h» y «cubo 2 h» sobre la misma reserva, a 24 h uno está cerrado y el otro abierto.
**Los `<input disabled>` NO se envían**, así que un guardado normal llegaría sin las tapas → estarían
ofrecidas pero ausentes del estado deseado → **se cancelarían solas**, contradiciendo a D5 sin que
falle nada. Dos consecuencias de diseño, y las dos son obligatorias:
1. lo que está **fuera de plazo no cuenta como ofrecido** a efectos de R1, así que se conserva;
2. el estado y **su motivo** viajan **por complemento** (`closed`, `closed_reason`, `closes_at`), no
   en el `readonly` booleano del formulario — que hoy es lo único que el contrato publica.

### 4.6.bis · El enlace se puede ROTAR (D14, `[DECIDIDO owner, 2026-09-03]`)

**El problema, medido**: `RGPD-06` afirma que invalidar el acceso de un titular tiene **un solo
sitio** (`User::revokeAllAccess()`) y alcanza a **tres** credenciales —sesiones, tokens y el carné
QR—. **La URL firmada del post-form no está, y no puede estar**: es HMAC sobre `APP_KEY`, sin fila
que borrar. Hoy esa credencial ya lee y reescribe nombres y alergias de menores sin sesión
(verificado: `GET` de una URL firmada real → 200 con las fichas); con los extras pasa además a
**escribir dinero de importe elegido**. Y las únicas palancas de hoy son cancelar la reserva o
anonimizar al titular — que `RGPD-01` **bloquea** mientras haya una reserva por celebrar, o sea justo
mientras el enlace importa.

▶ **`order_items.guest_form_link_version`** (entero, default 0) entra en la firma del enlace y de las
dos URLs de la API. El panel gana **«Rotar el enlace»** junto a «Copiar enlace»: sube la versión, los
enlaces viejos dejan de abrir (403) y el operador puede reenviar el nuevo. Es el mismo gesto que
`identidad-qr-puerta.md` ya construyó para el carné QR, y por eso el rótulo, la confirmación y el
audit se copian de allí.

⚠️ **Rotar NO borra lo ya añadido**: es una credencial, no una gestión. Retirar los extras que metió
un tercero sigue siendo un gesto aparte del operador (cancelar esas líneas, que es neutro por §1.3).
⚠️ **La versión es del ÍTEM, no del pedido**: un pedido con dos cumpleaños tiene dos enlaces
independientes desde `#217`, y rotar uno no puede tumbar el otro.
⚠️ **Y hay que actualizar `RGPD-06`**: hoy dice «tres credenciales» y pasarían a ser cuatro, con la
diferencia de que ésta **no** cae con `revokeAllAccess()` sino por gesto explícito del operador — el
mismo criterio que el carné, que `revokeOtherAccess()` conserva a propósito.

### 4.7 · Las superficies

| # | Superficie | Qué cambia |
|---|---|---|
| 1 | **Post-form web** (hoy `fiesta/lista/zona-4.blade.php`; entonces `reservation/guests.blade.php`, retirada en `fiesta-sistema-nuevo.md` T4) | Tercera zona «Extras»: cantidad, importe por línea, total **de los extras** (⚠️ no el saldo del pedido: hoy esa página no enseña ningún total, y hacerlo sería enseñar dinero del titular a un tercero con el enlace) y la frase de que se paga en el parque. ⚠️ **Suelo sin JS obligatorio**: la página es de mejora progresiva declarada, y un stepper sin JS no compra nada → el control base es `<input type="number" min max>`, decorado a stepper por JS; el total en vivo es adorno y el importe lo recalcula el servidor (`PAY-12`). CSS `.gf-*` en `public/css/site.css` ⚠️ (carril de diseño: declarado en el reparto) |
| 2 | **API** (`GET`/`PUT /reservations/{id}/guest-form`) | `GuestFormResource` gana `addons` con **DTO propio** (§4.7·bis); el `PUT` acepta `addons`. **El contrato primero** |
| 3 | **Panel · catálogo** | **SIETE sitios**, no dos (§4.7·ter) |
| 4 | **Panel · ficha del pedido** | Sin cambios estructurales; el libro pinta la línea con su fecha **si el contexto es `quantity_change` sobre la hija** (§4.5.1) |
| 5 | **Hoja de sala, Resumen del día y PUERTA** | Sin cambios: ya listan los complementos vivos y la puerta ya publica `addons` y el saldo. Entran en la verificación porque es **donde el operador cobra el extra** |
| 6 | **Correo al cliente** | Confirmación con el bloque del libro (`EmailBookBlock`) y **voz propia**; la distinción por `reason` ya existe en `MixedPartySurchargeChanged`. ⚠️ **Agrupado**: uno por ventana, no uno por gesto (§4.7·quater) |
| 7 | **Las superficies de DEMANDA** | §4.7·quinquies — lo que la primera versión no miró |

⚠️ **Los extras NO gatean «FORM OK»** (D7): son opcionales.

#### 4.7.bis · Por qué un DTO propio y no `ResolvedAddon`

Reutilizar el del embudo **contradice a R2**: `viewModel()` pinta el precio del **catálogo de hoy**,
y la línea conserva el suyo. Con el cubo subido de 12,00 a 14,00 €, el post-form diría 14 y el libro
12 — dos pantallas, dos importes, ningún fallo. Además `ResolvedAddons` es estricto y exige
`selection` y `line`, que son de la **cesta** y de la señal: post-reserva no hay ni una cosa ni la
otra. El DTO nuevo lleva el `unit_price` **de la línea** cuando existe y el del catálogo cuando aún
no, más el estado por fila de §4.6.

⚠️ **La estrictez del contrato obliga**: `required` tiene que igualar a `properties` **y en el mismo
orden** (lo asevera `ApiContractTest`), así que `addons` va **a la cola** de las dos listas;
`GuestFormRequest` tiene que ampliar su entrada de `OPTIONAL_BY_DESIGN` **con un porqué nuevo** (el
actual es justamente el que §4.7·quater desmiente); y cualquier objeto anidado necesita también
`additionalProperties: false`. El código de error del rechazo entra en **dos** sitios que un test
cruza: el enum de PHP y el `enum` del contrato.

#### 4.7.ter · Los SIETE sitios del panel (la lección de la hora extra, aplicada a sí misma)

⚠️⚠️ La primera versión decía «el formulario y su saneo». Entre el formulario y la fila hay **dos
listas blancas y una precarga a mano**, y **las tres callan al olvidarse**:

| # | Sitio | Si falta |
|---|---|---|
| 1 | migración (default `booking`, cutoff, idempotente, **no reescribe filas existentes**) | — |
| 2 | **`TicketType::ADDON_PIVOT_COLUMNS`** | ❗ la acción «Añadir» escribe `Arr::only($data, pivotColumns)`: **el dato se cae sin error** y la fila nace `booking`… **con el audit diciendo `postform`**. Y `AddonResolver` lo lee `null` sin avisar |
| 3 | `ProductAddon::$casts` | el cutoff llega como cadena |
| 4 | `AddonsRelationManager::pivotConfigFields()` | no hay campo |
| 5 | **`sanitizePivotData()`** (lista blanca literal) | ❗ «Configurar» no puede cambiar la fase — y ése es el gesto normal, porque los 29 enganches ya existen |
| 6 | **el `fillForm()` de la acción «Configurar»** | ❗❗ **el peor**: enumera las claves a mano y una ausente **no recibe su default, queda `null` y se sobrescribe al guardar**. Traducido: «Tapas» está bien configurado, seis semanas después alguien cambia **la posición en la lista**, y la fase vuelve a `booking` en silencio |
| 7 | `pivotBadges()` | el operador no puede auditar el catálogo de un vistazo (§4.2.bis) |

▶ La frase que faltaba: *un eje nuevo en el pivote no se «añade al formulario»: hay dos listas
blancas y una precarga a mano entre el formulario y la fila.* **Guarda de simetría**: toda clave de
`sanitizePivotData()` tiene que estar en `fillForm()` — una aserción de conjuntos que cierra la
familia entera.

⚠️ **Los seeders escriben el pivote con `DB::table` y saltan los guards** (medido, los tres).
`ProductionSeeder::$link` pasa un array explícito «para que un re-seed limpie dependencias viejas»:
**`stage` NO se escribe ahí**, o un re-seed devolvería a `booking` todo lo que el parque haya puesto
en `postform`, sin rastro.

#### 4.7.quater · La semántica de la ausencia — la otra afirmación FALSA de esta spec

⚠️⚠️ La primera versión decía: *«`addons` ausente en el `PUT` no toca nada (la semántica nullable que
`general` ya tiene)»*. **`general` no tiene esa semántica en ninguna superficie de cliente.** Medido
con una petición real firmada: un `PUT` sin la clave `general` devuelve **200** y **borra** las dos
respuestas generales del post-form. El `null` que conserva lo pasa **solo el panel**.

▶ Si `addons` copiara ese trato, un cliente de API que actualice solo las edades **cancelaría todos
los extras**, con sus movimientos de dinero y su correo. Por tanto:
1. `addons` **ausente ⇒ `null` ⇒ no se toca**; `[]` ⇒ vaciar. Decidido con `array_key_exists`, nunca
   con `?? []`.
2. Se escribe en `openapi/v1.yaml` **con su propio párrafo** y se fija con **dos** casos en
   direcciones opuestas.
3. El borrado de `general` es un **defecto preexistente de pérdida de datos** (sin ningún caso que lo
   fije, y el contrato documenta hoy una conducta que el código no tiene): ficha en `DEUDA.md`.

#### 4.7.quinquies · ⚠️ Nadie invita al cliente a volver, y sin eso la feature no se vende

Medido: el aviso de «formulario pendiente» del contexto de cuenta cuelga de `needsGuestForm()`, o sea
**desaparece en cuanto el cliente completa las fichas** —consecuencia directa de D7—; el único correo
que lleva al post-form (`GuestFormRequest`) habla solo de «los datos de cada invitado» y se manda
**una vez**, al pagar; y el chip del cajón dice «Ver o editar el formulario de reserva», que no
insinúa que ahí se compre.

▶ **La feature podría construirse entera y no venderse un solo cubo de refrescos.**

✅ **`[DECIDIDO owner, 2026-09-03]` (D15): se usan los textos que YA existen, y no se añade ningún
envío nuevo.** Concretamente: el correo del formulario y el rótulo del cajón mencionan los extras, y
**el aviso de la cuenta deja de morir al completar las fichas** mientras haya extras disponibles y
plazo abierto. ⛔ **Un recordatorio propio a N días de la fiesta queda FUERA**: sería un envío
comercial nuevo y arrastraría la decisión de si respeta el interruptor de marketing o va como
servicio. Si la venta no despega, esa es la siguiente palanca — pero se mide antes de gastarla.

⚠️ **Ojo al condicionar el aviso**: hoy cuelga de `needsGuestForm()`. La condición nueva no puede ser
«hay extras en el catálogo» a secas, o avisaría a reservas que ya los tienen todos elegidos y a las
que están fuera de plazo. Es «hay algo que este cliente todavía **podría** añadir».

### 4.8 · Los bordes, y qué los cierra

1. **El operador cambia el producto de la reserva** → `orphan_addons` ya lo bloquea o lo retira. ✅
2. **El operador baja los invitados** → un `fixed` no se re-escala. ✅ por construcción.
3. **Se cancela la reserva o el pedido** → cascada a las hijas; `acceptsGuestForm()` cierra la puerta
   del cliente en los dos casos. ✅ existente.
4. **Reembolso total** → prorrata por `onlineAtBirth`; una línea de venta posterior aporta **0**. ✅
   Y no se puede reembolsar por línea: `item_is_addon` + `itemRefundableRemainderCents = 0`. ✅
5. **El cliente y el operador guardan a la vez** → el lock serializa, **pero no decide quién gana**:
   si el operador sube «Tarta» a 3 por teléfono y el cliente guarda con la pantalla vieja, el estado
   deseado del cliente la baja a 1 y **borra el trabajo del operador** en silencio. ▶ Por eso el
   post-form gana un **token optimista** (`updated_at` del ítem, publicado en el `GET`) y un desajuste
   responde **409 «la reserva ha cambiado, revísala»** — nunca un guardado silencioso. Es la única
   superficie de dinero del producto sin token, y va a mover dinero.
6. **El complemento se retira del catálogo con líneas vendidas** → R1: se puede **quitar** pero no
   subir; se pinta con su motivo. ✅
7. **El titular ejerce la supresión** → 410 y no hay dónde añadir nada. ✅ existente.
8. **`TicketIssuer`** no emite tickets para hijas. ✅ existente.
9. **El precio cambia entre añadir y la fiesta** → la línea conserva el suyo (R2).
10. **Un extra extingue en silencio un «a devolver en el parque» ya anunciado**: añadir 20,00 € a un
    pedido que debía 20,00 € lo deja `settled`. Es coherente con el libro y el libro lo **explica**
    con sus dos líneas, pero cambia una promesa hecha al cliente: queda dicho.
11. **Instalación SIN ningún enganche `postform`** (el caso de todo cliente nuevo, y hoy el 100 % del
    catálogo): la sección **no se pinta**. Es el caso de datos vacíos del white-label y tiene su caso.

### 4.9 · ⚠️ Un defecto PREEXISTENTE que este diseño no arregla (y por qué)

**`OrderItem::isFinishedInPractice()` declara terminada una reserva entre 1 y 2 horas TARDE.** Las
franjas guardan hora de pared del parque —lo demuestra que `SlotOffer::passesIntradayFloor` y el
backstop de `OrderCreator` las comparan contra `DisplayTime::now()`—, pero ese predicado las parsea
con `CarbonImmutable::parse()`, que las interpreta en `config('app.timezone')` = **UTC**. Medido el
2026-09-03: una franja terminada **hace una hora en hora del parque** sale como «aún viva».

⚠️ **CORRECCIÓN de la revisión — la primera versión se quedó corta y citó un método muerto.** No son
tres consumidores: son **9 ficheros / 12 llamadas**, e incluyen dos que pesan más que las citadas:
`OrderBook` (el `$finished` del que cuelga la **liquidación implícita** «Liquidado / Devuelto en el
parque» — una superficie de DINERO) y `AuthorizableReservationsReader` (el justificante del menor
invitado). Y **`Order::itemGateResolved()` ya no existe**: murió con la T3·4 del libro (`#315`) y solo
sobrevive en un docblock caduco de `MixedPartySurcharge`. ▶ **La lista viva es la ficha de
`DEUDA.md`**, no este párrafo.

⚠️ **Y esta spec SÍ separa las dos ventanas, a propósito**: el corte de §4.6 cierra la del cliente
**antes** que `isFinishedInPractice()`, mientras la del dinero de puerta sigue colgando del
predicado. La divergencia es segura (congelar antes protege), pero quien lea `#244` tiene que saber
que a partir de aquí las dos ventanas ya no cierran a la vez.

### 4.10 · ⚠️⚠️ Lo que la primera versión afirmaba del editor del panel, y era FALSO

**La primera versión decía que `OrderItemEditor::edit()` ignora en silencio una bajada de 3 a 2.**
No la ignora: **la BLOQUEA.** `validateAddonEdits()` —que corre **antes** del cuerpo de `edit()`—
devuelve `addon_partial_reduce_unsupported`, hay mensaje al operador en es y zh_CN («para reducir un
complemento, quítalo y vuelve a añadirlo; el reembolso se hace aparte»), hay fila de auditoría y hay
caso propio. *La spec leyó las dos ramas del guardado y se saltó la validación de delante.*

▶ **Y el bloqueo no es un olvido: es una exclusión deliberada que sostiene el modelo de dinero.**
Para una línea `booking`, `nac > 0`, así que bajarla **debe dinero** — y `#244` prohíbe moverlo
online. La «asimetría difícil de defender» que la primera versión lamentaba **es exactamente la
propiedad de §1.3**: el cliente puede bajar **porque** su línea nació en 0.

▶ **No hay nada que arreglar; hay algo que escribir.** Si algún día se quiere permitir 3→2 en el
panel, sería **solo para líneas con `birthValue() === 0`**, con su caso y con el control de que una
línea `booking` sigue bloqueada. Extenderlo a todos los complementos reabre el agujero que ese
bloqueo tapa.

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **`PAY-16`/`PAY-17`** (el libro cierra) | ❗ **Directo, y es el corazón de la verificación**: las tres escrituras de §4.5.1 son lo que lo mantiene. Con guarda por escenario **y después de quitar**, no solo antes |
| **`PAY-12`** | Se aplica tal cual: el estado deseado del cliente es una intención; `AddonResolver` re-resuelve |
| `PAY-18`/`PAY-19` | Sin impacto: no se toca el sello ni la re-tarifa de la fecha |
| `AFORO-*` | **Ninguno**: un complemento de venta posterior no puede ocupar (§4.3·5) |
| `RGPD-03`/`RGPD-04` | Sin cambio: la sección vive dentro de la página que ya los cumple |
| **`RGPD-06`** | ❗ **La revisión abre una pregunta que esta spec no puede cerrar sola** (§7.3·4): el enlace firmado pasa a ser una credencial portadora que **escribe dinero** y **no está entre las tres que `revokeAllAccess()` invalida** — ni puede estarlo, porque es HMAC sin fila que borrar |
| **`SEC-06`** | ❗ El throttle actual es **30/min por IP** (medido: sin sesión, la clave del limitador es la IP). Entra un limitador **por RESERVA** y un tope **por PEDIDO**; **no** entra Turnstile (§7.2·D12) |
| **`SUITE-04`** | ❗ **DOS** escenarios nuevos de concurrencia, no uno (§6·5 y §6·6) |

▶ **`CRITICAL_RE`**: entra el servicio nuevo. ⚠️ Y queda anotado que **`ProductAddon.php` no está en
el regex** aunque es donde viven los guards — hueco heredado de la hora extra, con ficha propia.

## 6. Plan de verificación empírica

1. **CONTROL de que nada viejo se mueve**: con los 29 enganches en `booking`, la oferta del embudo,
   la landing y el importe cobrado son **idénticos** antes y después.
2. **No se puede comprar en el embudo**: un `postform` no aparece en la oferta de la API, ni en la
   ficha del producto, ni **en la landing**, ni en el alta manual; y metido a mano en la cesta,
   `OrderCreator` lo **rechaza**.
3. **El dinero, al céntimo, en los CUATRO gestos** (§4.5.1): alta, subida, bajada y retirada, con
   `assertBookCloses` **después de cada uno** y aserción **por línea** (`birthValue === 0`,
   `onlineAtBirth === 0`, `itemRefundableRemainderCents === 0`). ⚠️ Con mutación de las dos roturas
   conocidas: escribir movimiento al retirar, y no escribirlo al bajar.
4. **La puerta del HECHO**: una línea con `birthValue() > 0` **no es gobernable** aunque su enganche
   sea `postform`. Caso con el enganche cambiado de fase **con líneas vendidas delante**.
5. ❗ **Concurrencia · duplicación**: N guardados simultáneos del post-form escriben **una** línea.
   Se extiende `mixed-party:verify-concurrency` con un `--scenario=addons` (mismo arnés, mismo fork).
   Y un escenario con estados **distintos**, para que «gana el último» esté medido y no supuesto.
6. ❗❗ **Concurrencia · DOS ACTORES, que no existe hoy**: la mitad de los hijos guarda el post-form y
   la otra mitad cancela el pedido o reembolsa una línea. Propiedad: **cero `SQLSTATE[40001]`** en N
   vueltas, con **control negativo** (visto FALLAR con el orden de locks invertido). Ningún
   verificador del repo cruza hoy dos caminos distintos.
7. **El plazo**: pasado el corte de un complemento no se añade ni se quita; y **un guardado normal
   con un complemento dentro de plazo y otro fuera deja vivo el de fuera** (la trampa de los
   `disabled` de §4.6).
8. **Los siete guards del enganche**, cada uno **en las dos direcciones** (poner la fase sobre un
   enganche prohibido, y poner la propiedad prohibida sobre un enganche `postform`), incluido el
   guard 5 desde `TicketType`.
9. **El cinturón**: una fila torcida por `Query\Builder::update()` —sin `max_qty`, o con una
   propiedad prohibida— **ni se ofrece ni se vende**, con control de que la sana sí.
10. **R1 en sus dos direcciones**: retirar el complemento del catálogo **no cancela** la línea, y el
    cliente **sí puede quitarla**.
11. **R2**: subir de 2 a 3 cobra la tercera al precio de la línea, con el catálogo cambiado en medio.
12. **Contrato**: `GET`/`PUT` cumplen `openapi/v1.yaml`; **`addons` ausente no toca nada** y `[]`
    vacía (dos casos opuestos); el código de error nuevo está en los dos enums.
13. **El panel, los siete sitios**: alta y edición del enganche conservan el dato; la guarda de
    simetría `sanitizePivotData` ⊆ `fillForm`; la insignia de fase.
14. **Token optimista**: guardar con un token viejo responde 409 y **no escribe**.
15. **Presupuesto de consultas** del `GET` del post-form: 1 complemento vs 7, mismo número de
    consultas (hoy esa ruta no tiene presupuesto y `viewModel()` pide precio por complemento).
16. **Sin JavaScript**: el `input number` funciona y el servidor recalcula (`PAY-12`).
17. **Instalación sin enganches `postform`**: la sección no se pinta.
18. **Navegador real**: el guion del post-form con la sección nueva, con capturas.
19. **Mutación**: cada guarda nueva vista morder, con control previo en verde y veredicto por código
    de salida.

## 7. Revisión y decisión

### 7.1 · Las cuatro del owner (`[DECIDIDO owner, 2026-09-03]`)

| | Pregunta | Respuesta |
|---|---|---|
| **Q1** | ¿Cuándo se paga? | **En el parque** — aplica `#244` |
| **Q2** | ¿Hasta cuándo? | **N horas antes, por complemento** |
| **Q3** | ¿Quién puede quitar? | **El cliente, mientras el plazo esté abierto** — incluido lo que le añadió el parque |
| **Q4** | ¿Alcance? | **Donde hay post-form**, no «donde hay pack» |

Y las tres derivadas que el owner confirmó con casos delante (2026-09-03): **D2** (tampoco en el alta
manual), **D3** (`max_qty` obligatorio) y **D6** (ningún aviso nuevo al parque).

### 7.2 · Decisiones derivadas (tomadas aquí; el owner puede vetar cualquiera)

- **D1** · No existe el valor `both`.
- **D2** · Un `postform` **no se vende tampoco en el alta manual** del panel. ✅ confirmada.
- **D3** · `max_qty` es **obligatorio**, **y con cinturón en la autoridad**. ✅ confirmada.
- **D4** · Un enganche `postform` sobre un producto sin post-form **avisa, no se rechaza** — y lleva
  **insignia** en la lista, porque son el 72 % de los enganches posibles.
- **D5** · El plazo gobierna **las dos direcciones**.
- **D6** · **Ningún aviso nuevo al parque**. ✅ confirmada. ⚠️ Descansa en que la hoja se imprima
  **después** del corte más corto del producto: queda dicho.
- **D7** · Los extras **no cuentan** para «FORM OK».
- **D8** · El correo al cliente lleva el bloque del libro y **voz propia**, **agrupado por ventana**.
- **D9** *(nueva)* · La puerta del reconciliador es el **hecho de la línea** (`birthValue() === 0`),
  no el eje. Es el cambio más importante que trajo la revisión.
- **D10** *(nueva)* · `postform_cutoff_hours` es **obligatorio**, no nullable; `0` significa «hasta
  que empiece». Mata por construcción la herencia del reloj de §4.9.
- **D11** *(nueva)* · El servicio toma **`orders` primero**, y el orden `orders → order_items →
  hijas` se declara como regla del subsistema.
- **D12** *(nueva)* · **No entra anti-bot** (ni Turnstile ni honeypot): para llegar al `POST` hay que
  traer un HMAC válido de esa URL exacta, así que no hay enumeración ni barrido; Turnstile además
  rompería el `PUT` de la API y **falla también a personas**, que aquí se paga con un padre que cree
  tener la tarta pedida. Lo que entra en su lugar es limitar **por reserva**, el tope **por pedido**
  y el correo agrupado.
- **D13** *(nueva)* · El `via` viaja en el **`context` del ajuste**, no solo en el audit.
- **D14** *(`[DECIDIDO owner, 2026-09-03]`)* · **El enlace del post-form pasa a poder ROTARSE** —
  §4.6.bis. Devuelve a `RGPD-06` su frase y le da al parque una salida cuando el enlace se ha ido de
  las manos.
- **D15** *(`[DECIDIDO owner, 2026-09-03]`)* · Las superficies de demanda usan **los textos que ya
  existen**; ningún envío nuevo — §4.7·quinquies.

### 7.3 · Lo que falta antes de escribir código — ✅ NADA DEL OWNER

1. ✅ **Revisión adversarial hecha** (§8).
2. ✅ **Las quince derivadas de §7.2**, con las cinco de la revisión y las dos últimas decididas con
   los casos delante (2026-09-03).
3. ~~Decidir si el defecto de §4.10 entra en la tanda~~ — **sin efecto: ese defecto no existe**.
   Lo que queda es la decisión estrecha de permitir 3→2 en el panel **solo** para líneas con
   `birthValue() === 0`, que se toma al construir la T2.
4. ✅ **La revocabilidad del enlace: se construye** (D14).
5. ✅ **Las superficies de demanda: los textos que ya existen** (D15).
6. ✅ **Qué preexistentes entran** (`[DECIDIDO owner, 2026-09-03]`): los **tres** de la superficie del
   post-form —el `PUT` que borra `general`, la escalada 403→404 de la web y el sello que invalida el
   token del operador— van en la **T0**, porque esta feature construye encima de esa superficie. **La
   IP de auditoría que sobrevive al art. 17 se queda como ficha**: tiene dos lados (se pierde rastro
   probatorio) y merece tanda propia.

▶ **Con esto la spec queda APROBADA y el plan de obra es §9.**

## 8. La revisión adversarial (2026-09-03) — seis lentes, y qué cambió

Seis lentes independientes (configuración del dato · dinero y libro · ciclo de vida y locks ·
superficies y contrato · seguridad y RGPD · crítico de completitud), con la instrucción de verificar
contra el código y la BD y de marcar cada hallazgo como MEDIDO o INFERIDO.

### 8.1 · Las TRES afirmaciones de esta spec que resultaron FALSAS

| | Decía | Es |
|---|---|---|
| **§4.10** | el editor del panel ignora en silencio una bajada 3→2 | **La BLOQUEA** con razón estructurada, mensaje, audit y test — y el bloqueo **sostiene el modelo de dinero**. El autor leyó `edit()` y se saltó `validateAddonEdits()`, que corre antes |
| **§4.5** | «no hay inversión de locks posible, y por tanto no hay interbloqueo que diseñar» | Hay **cuatro** caminos que toman `orders` primero, y el interbloqueo se **reprodujo** (`SQLSTATE[40001]`) con dos conexiones reales |
| **§6·10** | «`addons` ausente no toca nada, la semántica nullable que `general` ya tiene» | `general` **no tiene** esa semántica: ausente **BORRA**, medido con una petición firmada real |

Y tres imprecisiones: «13 líneas hijas vivas» (son 12), «tres consumidores» del predicado del reloj
(son 9 ficheros y uno de los citados **ya no existe**), y la justificación del guard 4 (el «cargo que
nadie pidió» **no puede darse**; el guard se conserva por otra razón, más débil).

### 8.2 · Los DOS bloqueantes

1. **El eje es configuración mutable y la propiedad es un hecho de la línea** → la puerta pasa a ser
   `birthValue() === 0` (D9). *La guarda «las cuatro identidades cierran» pasaba en verde con el
   defecto puesto.*
2. **Las tres listas blancas del panel** (`ADDON_PIVOT_COLUMNS`, `sanitizePivotData`, `fillForm`) →
   sin las tres, el dato **no llega, no se puede cambiar, y revierte solo** (§4.7·ter).

### 8.3 · Los TRES desacuerdos entre lentes, y cómo se resolvieron

*(Ninguno por votación: los tres midiendo.)*

- **§4.10**: dos lentes lo dieron por cierto y dos lo desmintieron. Ganaron las que leyeron el camino
  entero. **Comprobado por el autor**: la razón existe, está traducida y tiene test.
- **El orden de locks**: dos lentes dijeron que la spec era correcta — **cometiendo su mismo error**,
  comparar solo contra el editor. Una lo midió y reprodujo el interbloqueo. **Comprobado por el
  autor** leyendo `OrderItemCanceller::cancel()` y `Order::executePartialRefund()`.
- **Si BAJAR escribe movimiento**: una lente dijo que ni bajar ni retirar deben escribirlo.
  **Resuelto por aritmética del autor**: sin el movimiento de la bajada, `nac` cae a −12,00 € e `I1`
  falla. **Bajar escribe; retirar no** (§4.5.1).

### 8.4 · Lo que la revisión CONFIRMÓ (resultados negativos, que también son medida)

La propiedad de §1.3 **verificada sobre las 13 hijas reales**; las cifras de §1.1; que `attach()` y
`updateExistingPivot()` **sí** disparan los guards del pivote; que `AddonsRelationManager` es la
**única** puerta humana del pivote; que `ManualOrderFulfiller` pasa por `OrderCreator` (D2 sale
gratis en el dominio); que el reembolso total prorratea por `onlineAtBirth` y una línea de venta
posterior recibe **0**; que una línea de venta posterior **no es reembolsable** por dos capas
independientes; que las cascadas de cancelación funcionan; que la hoja, el resumen y la puerta ya
listan los complementos sin coste nuevo; y que las diez invariantes citadas dicen lo que se les
atribuye. ▶ **Dos lentes refutaron alarmas propias antes de escribirlas** (que `attach()` se saltaba
los guards; que la dirección inversa del guard de ocupación no existía): las dos eran falsas.

### 8.5 · Los defectos PREEXISTENTES que la revisión destapó (fichas en `DEUDA.md`, ninguno de esta tanda)

1. Un `PUT` del post-form **sin `general` borra** las respuestas generales — y el contrato documenta
   hoy («se guarda a trozos») una conducta que el código no tiene.
2. La escalada **403 → 410 → 404 no se cumple en la WEB**: medido con `curl`, un id existente da 403
   y uno inexistente 404 — el *route binding* lanza el 404 antes de la autorización. En la API sí se
   cumple. Afecta también al justificante.
3. **La IP y el user-agent** de las filas de auditoría escritas sin sesión **sobreviven al art. 17**:
   368 de 373 filas de `orders.guest_form_submitted` tienen `user_id` nulo y una IP, y `anonymize()`
   solo nulifica donde el titular es el ACTOR.
4. **`max_qty = null` no tiene techo en el dominio** y `CartPayload` valida `min:1` sin `max`: hoy,
   con los 29 enganches sin tope, una cesta forjada compra cualquier cantidad.
5. `markGuestFormCompleted()` **bumpea `updated_at`** en cada guardado completo, invalidando el token
   optimista de cinco puertas del operador.
6. **`ProductAddon.php` no está en el `CRITICAL_RE`** aunque es donde viven los guards del pivote.

## 9. Plan de obra — cuatro tandas, cada una verde y sin deuda propia

El orden no es de comodidad: **la T0 va primero porque la T3 construye encima de esa superficie**, y
la T1 antes que la T2 porque el dominio necesita un dato que hoy no se puede introducir (la lección
de `specs/hora-extra.md` §4.9).

### T0 · La superficie del post-form, saneada (los tres preexistentes + la rotación)

`[DECIDIDO owner, 2026-09-03]`. **No añade nada de la feature**: deja sana la superficie sobre la que
se va a construir dinero.

1. **`addons`/`general` con semántica de ausencia real**: `array_key_exists` en vez de `?? []`, `null`
   de verdad hacia `submitGuestForm()`, en **las dos** superficies (web y API). Se corrige además el
   porqué escrito en `OPTIONAL_BY_DESIGN`, que hoy documenta lo contrario de lo que el código hace.
2. **La escalada 403 → 410 → 404 en la web**: `->missing(fn () => abort(403))` en las rutas del
   post-form **y del justificante** (`#335` comparte el defecto).
3. **`markGuestFormCompleted()` deja de bumpear `updated_at`** cuando nada cambió.
4. **La rotación del enlace** (§4.6.bis): columna, firma, acción del panel, y `RGPD-06` actualizada.

**Guardas**: `PUT` sin `general` **conserva** / con `general: []` **vacía** (dos casos opuestos) · un
id inexistente responde **403** en web como ya hace en API · dos guardados idénticos no mueven
`updated_at` · el enlace viejo da 403 tras rotar y el nuevo abre. **Mutaciones**: volver al `?? []`,
quitar el `missing()`, sellar siempre, no meter la versión en la firma.

### 9.1 · ✅ T0 EJECUTADA (2026-09-03, `DECISIONES #413`)

Commits `055d4781` (código y guardas) + el de la mutación que faltaba. **Suite 4.156 ✓ · 26.328
aserciones** (+19 casos) · Pint ✓ · docs-check ✓ · **`scripts/mutar-postform-t0.sh` 13/13**.

**Lo construido**, con lo que la obra enseñó y la spec no decía:

1. **La ausencia de una clave = «no la toques», en las DOS claves.** ⚠️⚠️ **La spec decía `general` y
   al construirlo se midió que `guests` tenía el mismo defecto y era PEOR**: `sanitizeGuestData([], N)`
   devuelve **N filas vacías**, así que un `PUT` sin `guests` **borraba los nombres, las edades y las
   alergias de los ocho niños** de una reserva real, con un 200 por respuesta. La decisión vive en un
   solo sitio (`AuthorizesGuestForm::submittedGuestFormArray()`) que comparten web y API, y el dominio
   recibe `null`. ⚠️ Un cuerpo **malformado** se trata como ausente: destruir datos de menores por un
   tipo equivocado no puede ser la conducta por defecto. Corregido también el porqué escrito en
   `OPTIONAL_BY_DESIGN` y el `description` del contrato, que documentaban lo contrario de lo que el
   código hacía.
2. **La escalada 403 → 410 → 404 se cumple ya en la WEB**: `->missing(fn () => abort(403))` en las
   **cuatro** rutas públicas (post-form y justificante). Verificado con `curl` fuera de la suite:
   antes id existente → 403 e inventado → 404; ahora **403 los dos**, en los dos formularios.
3. **El sello no se re-estampa cuando nada cambió**, y con eso el token optimista del operador deja
   de moverse solo. ⚠️ **El `travel()` de sus dos casos no es adorno**: sin él los guardados caen en
   el mismo segundo y el caso pasaría con el defecto puesto — la trampa del valor trivial de
   `CONVENCIONES §3.quater`.
4. **D14 · el enlace se puede ROTAR.** `order_items.guest_form_link_version` viaja como `v` dentro de
   la firma y `hasLiveGuestFormSignature()` la compara; el operador rota desde la ficha del pedido con
   `orders.edit_guest_data`, con confirmación, re-comprobación en el momento de ejecutar (`SEC-04`) y
   audit sin el enlace (`RGPD-02`). Las **cuatro** URLs firmadas del post-form salen ya del dominio —
   el controlador componía dos a mano, que es justo por donde una se habría quedado sin versión.
   Verificado sobre HTTP real: **200 → rotar → 403 el viejo y 200 el nuevo**.
   ⚠️ **Ningún enlace vivo se rompió**: los emitidos sin `v` se leen como versión 0, con caso propio.

**Lo que enseñó la ejecución**

- ⚠️⚠️ **El arnés de mutación encontró una regla SIN RED**: 12 de 13 a la primera. La que no mordía
  era la defensa del cuerpo malformado — estaba escrita en el código y no la ejercía ningún caso.
  *12 de 13 no es un aprobado: es un mapa.*
- ⚠️ **La suite entera pasaba con los cuatro defectos puestos** (126 casos del post-form, el
  justificante y el contrato), que es exactamente por lo que esta tanda existe antes que las demás.
- `INVARIANTES` `RGPD-06` gana la **cuarta credencial** con su matiz —no cae con `revokeAllAccess()`,
  se retira por gesto del operador— y `RGPD-03` la nota de que su escalada solo se cumplía en la API.

### T1 · El eje y su panel (sin cliente todavía)

Migración (`stage` default `booking` + `postform_cutoff_hours`, **sin reescribir filas existentes**) ·
`ADDON_PIVOT_COLUMNS` · `$casts` · los **siete** sitios de §4.7·ter · los **siete** guards de §4.3 en
las **dos** direcciones (incluida la mitad que vive en `TicketType::booted()`) · el cinturón en
`AddonResolver` · el filtro por `stage` en las **seis** superficies de venta de §4.4 · la guarda de
censo de consumidores · la insignia de fase.

**Guardas**: el **CONTROL** de que los 29 enganches no mueven nada (oferta, landing, cobro) · alta y
edición del enganche **conservan** el dato · `sanitizePivotData ⊆ fillForm` (aserción de conjuntos) ·
un `postform` no aparece en embudo, landing, ficha ni alta manual, y una cesta forjada se rechaza.
**Mutaciones**: quitar una de las tres listas blancas, quitar el filtro de cada superficie, apagar
cada guard.

### 9.2 · ✅ T1 EJECUTADA (2026-09-03, `DECISIONES #413`)

Commits `2503adc7` + el de la guarda que faltaba. **Suite 4.178 ✓ · 26.382 aserciones** (+23 casos) ·
Pint ✓ · **`scripts/mutar-postform-t1.sh` 16/16** · verificado sobre HTTP real.

**Lo construido**

1. **La migración** (`stage` default `booking` · `postform_cutoff_hours` nullable), **sin reescribir
   ninguna fila**: los 29 enganches quedan idénticos, con su caso de CONTROL que mide la oferta, la
   ficha, la landing y el cobro antes y después.
2. **Las tres listas blancas del panel**, las tres con su caso: `ADDON_PIVOT_COLUMNS` (sin ella el
   *attach* borra el dato **y el audit dice lo contrario**), `sanitizePivotData()` (sin ella no se
   puede cambiar la fase de los 29 que ya existen) y el `fillForm()` de «Configurar» —la peor, que
   devolvería un `postform` a `booking` al tocar **la posición en la lista**—. Más la **guarda de
   simetría** `sanitizePivotData ⊆ fillForm`, que cierra la familia entera y no solo este campo.
3. **Nueve guards en `ProductAddon::postFormProblem()`**, punto único que comparten el guard del
   modelo y el **cinturón** de `AddonResolver`; y la **mitad inversa** del guard de ocupación en
   `TicketType::booted()`, la lección de `#324`. Los seis que se ven desde el pivote, con proveedor
   de datos; los otros tres, con caso propio.
4. **El filtro en las seis superficies que venden**, explícito en cada una y **no** dentro de la
   relación. Más el **censo de consumidores declarado**, que se pone rojo cuando aparece uno nuevo
   sin decir qué hace con la fase. ⚠️ **Cazó mi propio método** mientras lo escribía.
5. **La insignia de fase** en la lista del catálogo y el **aviso de D4** al enganchar sobre un
   producto sin post-form.

**Verificación fuera de la suite** (sobre el catálogo real, con la BD restaurada después): al pasar
«Tarta» a venta posterior en «Cumpleaños Jump», el embudo por HTTP pasa de ofrecer **4** complementos
a **3**, la landing y la ficha pública igual, y la fase `postform` la ve a ella sola. Restaurado,
vuelven los cuatro.

**Lo que enseñó la ejecución**

- ⚠️⚠️ **Un método llamado como una columna hace que Eloquent lo tome por una RELACIÓN**: `stage()`
  dejó **105 casos en rojo** con `Undefined property: $stage`, porque un pivote construido con
  atributos parciales —lo que hace `attach()`— no lo trae. Hoy es `saleStage()` y lee de
  `getAttributes()`, sin pasar por `__get`.
- ⚠️ **El `+` de arrays conserva el operando IZQUIERDO**: tres de los seis casos del proveedor se
  ignoraban en silencio, y el test decía «el guard no muerde» sobre una configuración **que nunca se
  aplicó**. Es la trampa nº 3 de `CONVENCIONES §3.quater` con otra cara.
- ⚠️ **La landing solo puede tipar `TicketType`** y las baselines del grafo de módulos **solo
  encogen**: el filtro va detrás de `TicketType::addonsSoldAtBooking()` en vez de ensancharlas.
  *Una guarda de arquitectura que muerde no se rodea: se obedece.*
- **El arnés cazó una guarda que faltaba** (15/16 a la primera): el alta manual tenía el filtro
  puesto y **sin red**, y es justo la decisión D2 que el owner confirmó con el caso delante.

### T2 · El dominio y la concurrencia (sigue sin cliente)

`PostFormAddons` con las **tres escrituras** de §4.5.1, las reglas R0–R5, el **orden de locks**
`orders → order_items → hijas` (D11), la re-validación bajo lock de §4.5.5 y el token optimista del
borde 5. Los **dos** verificadores: `--scenario=addons` en `mixed-party:verify-concurrency` y el
**cruzado** `postform-vs-panel`, que hoy no existe en el repo — **los dos vistos FALLAR antes**.

**Guardas**: los cuatro gestos con `assertBookCloses` **después** de cada uno y aserción **por
línea** · la puerta del hecho con el enganche cambiado de fase y líneas vendidas delante · el plazo
en sus dos direcciones · R1 en sus dos direcciones · R2 con el catálogo cambiado en medio.
**Mutaciones**: escribir movimiento al retirar · no escribirlo al bajar · atar el hecho al principal ·
gobernar por eje en vez de por hecho · invertir el orden de locks.

### 9.3 · ✅ T2 EJECUTADA (2026-09-03, `DECISIONES #413`)

Commits `cb497127` + el de la guarda del reloj. **Suite 4.204 ✓** · Pint ✓ ·
**`scripts/mutar-postform-t2.sh` 13/13** · los dos escenarios nuevos sobre MySQL real, **el cruzado
visto FALLAR con su control**.

**Lo construido**: `Booking\Services\PostFormAddons` (+ `PostFormAddonChanges`), con las tres
escrituras de §4.5.1, R0–R5, el orden de locks de D11, la re-validación bajo lock de §4.5.5 y el
token optimista del borde 5. Entra en el `CRITICAL_RE` y en `CriticalPathGateTest`.

**Los dos verificadores** (`postform:verify-concurrency`):

| escenario | qué mide | medido |
|---|---|---|
| `addons` | N guardados simultáneos del MISMO post-form escriben **UNA** línea y **UN** hecho | 12 procesos → 1 línea de 2 unidades, 1 ajuste |
| `cross` | un guardado del **cliente** contra una cancelación del **operador** no se interbloquea | **0 de 12** con el orden de D11 · **6 de 12** con `--control` (orden invertido) |

⚠️⚠️ **`cross` es el primer escenario del repo que cruza DOS caminos distintos.** `purchase:verify-oversell`
forkea N compras o N ediciones, `mixed-party` N guardados y `redsys` N notificaciones: todos, N copias
del MISMO actor. El interbloqueo que la revisión reprodujo **solo aparece cruzándolos**, y por eso no
lo veía ningún verificador.

▶ **Desviación de la spec, dicha**: §6·5 proponía añadir un `--scenario=addons` a
`mixed-party:verify-concurrency`. Se hizo **comando propio** porque el segundo escenario no tiene nada
que ver con la fiesta mixta y meterlo allí habría dejado un comando cuyo nombre miente sobre la mitad
de lo que mide. `SUITE-04` declara los dos.

**Lo que enseñó la ejecución**

- ⚠️ **El «desde» de un rótulo no se lee de `getOriginal()` después de `save()`**: Eloquent sincroniza
  los originales al guardar, así que el libro habría dicho «Cubo de refrescos: 2 → 2». Lo cazó el caso
  del rótulo, no una revisión.
- ⚠️⚠️ **Un caso que no puede distinguir las dos respuestas no vigila la regla.** La mutación que mide
  el plazo con el reloj torcido **no mordía**: los casos ponían la fiesta a diez días, donde 1–2 h de
  desfase no cambian nada. El caso nuevo está construido para que muerda —el corte vence hace diez
  minutos en hora del parque y con el reloj de UTC aún parecería abierto—, y es la misma familia que
  el `travel()` de la T0.
- ⚠️ **Un rótulo con backticks dentro de comillas dobles lo EJECUTA bash**: el arnés informaba de una
  mutación sin nombre. El veredicto era bueno; lo que mentía era el informe.

### 9.4 · ✅ T3 EJECUTADA (2026-09-03, `DECISIONES #413`)

**Suite 4.228 ✓** (26.648 aserciones) · **951 casos de `node --test` ✓** · Pint ✓ ·
**`scripts/mutar-postform-t3.sh` 15/15** · los dos verificadores de concurrencia en verde ·
**sonda de navegador 13/13** con capturas (`/root/e2e/pf-t3-capturas`).

**Lo construido**: el contrato (`PostFormAddon`, la semántica de ausencia, `guest_form_stale` en los
dos enums, `can_add_extras` y `extras_invite`) → la API → **la página con su suelo sin JS** → el
**correo agrupado** con voz propia (`PostFormAddonsChanged`) → las **tres superficies de demanda** de
D15 → el **limitador por RESERVA** que `SEC-06` pedía.

#### 9.4.1 · Los DOS defectos que solo vio el navegador

❗❗❗ **El testigo optimista se invalidaba a sí mismo, y ningún caso podía verlo.** El token es
`updated_at` de la reserva, y `submitGuestForm()` **lo escribe en la misma petición**, antes de llegar
a los extras: para cuando el reconciliador compara, el valor que el cliente vio al pintar la página ya
no existe. En el navegador eso significaba que **un guardado normal —nombres y extras a la vez— nunca
compraba nada**, y respondía *«la reserva ha cambiado mientras tenías esta página abierta»*.

▶ En la suite pasaba **en verde**, y no por descuido: `updated_at` tiene **precisión de segundo**, así
que en un test el render y el POST caen en el mismo. *Un reloj de un segundo puede esconder un defecto
que el usuario ve siempre.* El caso que lo fija separa las dos cosas con `travel(3)->seconds()` —la
misma familia que la mutación del reloj de la T2— y se vio **rojo con el arreglo retirado**.

▶ La regla que lo resuelve, en `AuthorizesGuestForm::addonsExpectedVersion()`: **nuestra propia
escritura no es un tercero**. Si lo que trae el cliente coincide con el estado que la reserva tenía al
ENTRAR en la petición, estaba al día, y bajo el lock se comprueba el estado de AHORA —que sigue
cazando a quien escriba entre medias—. Si no coincide, su token viaja tal cual y se rechaza.

⚠️ **El segundo lo destapó el presupuesto de consultas**: `OrderItemResource` no ponía la relación
INVERSA, así que media docena de predicados que preguntan por el pedido de la línea
(`acceptsGuestForm()`, `needsGuestForm()`, `can_add_extras`…) hacían **una consulta por tarjeta** en la
lista paginada del cliente. Es PREEXISTENTE: la guarda salía roja **también con el control** —el campo
nuevo devuelto a secas—, y *cuando un presupuesto acusa también al control, el defecto está debajo del
sujeto*. Hoy la pone `toArray()` antes de nada, y lo mismo hace `CustomerReservationsReader`.

#### 9.4.2 · Lo que la ejecución cambió del diseño

- ⚠️⚠️ **La fiesta pasada CIERRA los extras, no los esconde.** `viewFor()` devolvía `[]` con la fiesta
  celebrada, así que quien encargó dos cubos abría su formulario al día siguiente y **no encontraba ni
  rastro de ellos** —los mismos que se le van a cobrar en el parque—, y la rama `readonly` de la
  plantilla, escrita justo para eso, era código MUERTO.
- ⚠️⚠️ **Y el cierre NO necesita término propio para «ya se celebró».** Se escribió `|| $finished`
  delante y **ninguna mutación podía distinguirlo**: el plazo se mide contra el INICIO de la franja, así
  que una fiesta terminada venció su corte por construcción, para cualquier valor y `0` incluido. Se
  retiró. *Un cinturón que ningún caso puede separar de su hebilla no es un cinturón, es ruido.*
- ⚠️⚠️ **«Qué hecho se escribe» y «cuánto cambia el pedido» son dos preguntas distintas.** La retirada
  no escribe `recordEdit` —el libro ya emite su `−fila`— pero **sí baja lo que el cliente va a pagar**,
  y `deltaCents` devolvía 0: el correo decía **«se suman 27,00 €»** en un guardado de +12 −8 +15, y el
  audit registraba lo mismo. Lo cazó la aritmética de un caso.
- ▶ **El limitador POR RESERVA se construye** (`SEC-06`, D12): el `throttle:30,1` que había va **por
  IP**, así que treinta peticiones por minuto **desde cada IP** caben sobre la misma reserva —y con
  ellas treinta correos al titular, que es la única señal de que alguien con su enlace está encargando
  en su nombre—. `RateLimiter::for('guest-form')`, 12/min por reserva, **sumado** al de IP.
  ▶ El **«tope por PEDIDO»** de esa misma fila **ya existe por construcción y se declara aquí**: es
  `Σ (max_qty × precio)` de los enganches `postform`, obligatorio por D3 y con cinturón en
  `AddonResolver::forStage()`. No entra un segundo número que nadie ha decidido; si el owner quiere un
  techo en euros por pedido, es una decisión suya y una columna más.

#### 9.4.3 · Las cuatro trampas de guarda que pagó esta tanda

1. ⚠️⚠️ **Aseverar `type="number"` sobre la página entera pasa en VERDE** con el control convertido en
   `hidden`: los campos de edad de los invitados también son numéricos. **Acota al ELEMENTO** — hay
   helper (`controlOf()`).
2. ⚠️ **Un caso que manda el cuerpo a mano no puede ver el MARCADO.** La guarda del extra cerrado
   posteaba `addons[0][quantity]` ella misma, así que quitar el campo oculto de la plantilla no la
   ponía roja — y ese campo **es el mecanismo entero** (los `<input disabled>` no se envían). Se
   asevera el HTML.
3. ⚠️⚠️ **Dos reglas eran invisibles en la página y solo se ven en la API**: que la fiesta pasada
   cierre las filas —el `readonly` del post-form las pinta cerradas de todas formas— y que mande el
   precio de la LÍNEA sobre un catálogo que subió. Sus guardas viven en `Api\V1\GuestFormTest`.
4. ⚠️ **Un presupuesto que crece con algo que no es su sujeto no mide su sujeto**: la primera versión
   de la guarda de consultas creaba cuatro PEDIDOS, y lo que hay que variar son las RESERVAS.

#### 9.4.4 · Lo que queda

▶ **El OJO del owner** y su ✅ a la spec. La sonda deja capturas en `/root/e2e/pf-t3-capturas`
(escritorio, teléfono y la portada con sesión). ▶ Y una elección menor de presentación, anotada sin
cambiarla: un extra **cerrado que nunca se pidió** se pinta igual, con su «ya no se puede cambiar» y su
0 — se ve en la captura `01-postform`. Enseñarlo dice «esto existía y llegaste tarde»; ocultarlo
quitaría una fila que no aporta. **Es del owner.**

### T3 · Las superficies del cliente

**El contrato primero** (`openapi/v1.yaml`: el DTO propio de §4.7·bis, la semántica de ausencia, el
estado por fila, el código de error nuevo en los dos enums) → API → la página web con su **suelo sin
JS** → el correo agrupado con voz propia → los textos de demanda de D15.

**Guardas**: contrato estricto (`required == properties`, en orden) · `addons` ausente no toca nada ·
presupuesto de consultas del `GET` (1 vs 7 complementos) · sin JS el `input number` funciona · la
instalación sin enganches `postform` no pinta la sección · navegador real con capturas.

▶ **Cada tanda cierra con `VERIFY_CONC=1`** desde la T2 (antes no toca dinero concurrente), y el
recuento de `SUITE-04` sube al añadir los dos escenarios.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Un COMPLEMENTO que se vende DESPUÉS de reservar · el cubo de refrescos / las tapas que se eligen en el POST-FORM · el plazo de corte de un extra»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/complementos-post-reserva.md` · `docs/DEUDA.md`.

- **`docs/specs/complementos-post-reserva.md`**
- 🟦 **CÓDIGO COMPLETO — LAS CUATRO TANDAS EN EL ÁRBOL** (`#413`, 2026-09-03; suite 4.228 · JS 951 · mutaciones 13/13 + 16/16 + 13/13 + 15/15 · los DOS verificadores de concurrencia sobre InnoDB, el cruzado visto FALLAR con su control · sonda de navegador 13/13).
- ▶ ❗❗❗ **EMPIEZA POR §9.4, que es la T3 y sus dos defectos de navegador**; sigue
- 🟦 por el OJO del owner.
- ❗❗❗ **SI TOCAS EL TESTIGO DEL POST-FORM**: el token es `updated_at` de la reserva y `submitGuestForm()` **lo escribe en la misma petición**, así que comparar contra el de después hace que **ningún guardado normal compre nada** (dice «la reserva ha cambiado mientras tenías esta página abierta»). La regla vive en `AuthorizesGuestForm::addonsExpectedVersion()`: **nuestra propia escritura no es un tercero**.
- ⚠️⚠️ **En la suite salía VERDE** porque `updated_at` tiene precisión de SEGUNDO: su caso separa render y POST con `travel()`.
- ❗❗ **SI TOCAS UNA GUARDA DE ESTA PANTALLA**: cuatro trampas pagadas, todas de la misma familia — `type="number"` aseverado sobre la página entera pasa en verde con el control en `hidden` (los campos de EDAD también son numéricos); un caso que manda el cuerpo a mano **no ve el MARCADO**, y el campo oculto del extra cerrado **es el mecanismo** (los `<input disabled>` no se envían); dos reglas —el cierre por fiesta pasada y el precio de la LÍNEA sobre el catálogo— son **invisibles en la página** y solo se ven en la API; y un presupuesto que crece con PEDIDOS no mide un coste por RESERVA.
- ⚠️⚠️ **La fiesta pasada CIERRA los extras, NO los esconde** (con `return []` quien encargó dos cubos no encontraba rastro de ellos al día siguiente) — y ese cierre **no necesita término propio**: el plazo se mide contra el INICIO de la franja, así que una fiesta terminada venció su corte por construcción, y el `|| $finished` que se escribió **ninguna mutación podía distinguirlo**.
- ⚠️ **«Qué hecho se escribe» y «cuánto cambia el pedido» son dos preguntas distintas**: la retirada no escribe `recordEdit` pero **sí informa delta negativo**, o el correo dice «se suman 27,00 €» donde son 19,00.
- ⚠️ **`SEC-06`: DOS limitadores** — el de siempre por IP y **`guest-form`, 12/min por RESERVA**, porque el de IP deja treinta guardados por minuto **desde cada IP** sobre la misma reserva; el **tope por pedido** existe por construcción (`Σ max_qty × precio`).
- ⚠️ **D15, las tres superficies de demanda**: el correo del post-form nombra los extras **solo si esa reserva tiene alguno abierto**, el rótulo del cajón cambia con `can_add_extras` (lo decide el SERVIDOR, nunca «el catálogo tiene extras») y el aviso de la cuenta **deja de morir** al completar las fichas (`extras_invite`, que es una INVITACIÓN y no la deuda de `pending_forms`). — El diseño y la revisión:
- ❗❗ **EMPIEZA POR §1.3, que es la propiedad que sostiene todo**: una línea nacida DESPUÉS del pedido lleva un ajuste `edit` de su importe exacto, así que `nac = 0` y `online_nac = 0` (verificado en `LineFacts`) → **quitarla es NEUTRO en dinero** y el libro sigue cerrando sus cuatro identidades.
- ▶ **El mecanismo YA EXISTE**: el panel añade complementos a una reserva pagada (`OrderItemEditor::edit` + `Order::recordEdit`) y el LIBRO lo pinta con su fecha y saldo «A pagar en el parque»; **y el post-form YA mueve dinero hoy** (`MixedPartySurcharge::reconcile` al guardar las edades). Lo que falta son DOS cosas: el eje y la puerta del cliente.
- ▶ **El eje es `product_addons.stage`** (`booking` por defecto · `postform`), en el **PIVOTE** —doctrina del origen, «config por enganche, no global»— y con el vocabulario de `event_fields.stage`.
- ⚠️⚠️ **Significa «cuándo se VENDE», no «dónde se ve», y esa precisión es la que hace demostrable la propiedad**: un `postform` **no nace nunca con el pedido**, ni siquiera en el **alta manual del panel** (D2) — dejar que el mostrador lo venda dentro del pedido le daría `nac > 0` y entonces el cliente podría retirar algo que SÍ se cobró.
- ⚠️ **No hay valor `both`** (obligaría a distinguir por unidad qué se cobró).
- ⚠️⚠️ **El filtro NO va dentro de la relación `addons()`**: son 12 consumidores y uno es el editor del panel, que dejaría de encontrar la línea que tiene que mover — **el eje gobierna la venta, no la capacidad del operador**.
- ▶ **Cuatro `[DECIDIDO owner, 2026-09-03]`**: se paga **en el parque** (`#244`, el cobro online post-reserva queda fuera) · **plazo de corte por complemento** (`postform_cutoff_hours` nullable, `null` = hereda; medido contra el inicio de la franja con **`DisplayTime::now()`**) · el cliente **puede quitar** mientras el plazo esté abierto, incluido lo que le añadió el parque · **la puerta es `acceptsGuestForm()`, NO `isPack()`** (`[owner]`: «no es por producto, sería por postform») — hoy eso es cumpleaños, pero como CONSECUENCIA.
- ⚠️ **Seis guards del enganche** (§4.3), cada uno cerrando un agujero: ni obligatorio (se auto-inyectaría una deuda sin un clic), ni incluido, ni `per_guest` (ataría el extra de los ADULTOS al número de NIÑOS), ni grupo excluyente (`groupDefault()` siempre elige uno → cargo que nadie pidió), ni ocupante de aforo, ni requisito de otra fase; **`max_qty` pasa a OBLIGATORIO** porque el enlace del post-form se reenvía.
- ⚠️⚠️ **R1 del reconciliador**: el estado deseado gobierna **sólo lo OFRECIDO** — sin esa regla, retirar del catálogo un complemento ya vendido haría que el siguiente guardado del cliente **lo cancelara en silencio**.
- ⚠️ **R2**: la línea conserva su `unit_price` (subir de 2 a 3 cobra la tercera al precio de la línea, como ya hace el panel).
- ❗❗ **§4.9 · DEFECTO PREEXISTENTE MEDIDO**: `isFinishedInPractice()` declara terminada una reserva **1–2 h TARDE** —las franjas guardan hora de pared del parque (lo demuestra `SlotOffer::passesIntradayFloor`) y ese predicado las parsea como UTC—; de él cuelgan el `readonly` del post-form, el `item_finished` del panel y la ventana de dinero del suplemento mixto. **No contradice a `#244`** (las dos ventanas siguen cerrando a la vez; cierran tarde las dos). Ficha en `DEUDA.md`; esta spec **no hereda el error**.
- ❗❗❗ **REVISADA POR SEIS LENTES Y CORREGIDA (§8): TRES afirmaciones de la propia spec eran FALSAS.** (1) ~~«el editor del panel ignora una bajada 3→2»~~ — **la BLOQUEA** (`addon_partial_reduce_unsupported`, con mensaje, audit y test; corría `validateAddonEdits()` ANTES del cuerpo de `edit()`), y **el bloqueo sostiene el modelo de dinero**: para una línea nacida con el pedido, bajarla DEBE dinero. (2) ~~«no hay inversión de locks posible»~~ — **hay CUATRO caminos que toman `orders` antes que `order_items`** (cancelar ítem, cancelar pedido, las dos txn del reembolso) y el interbloqueo se **REPRODUJO** (`SQLSTATE[40001] · 1213`) con dos conexiones MySQL;
- ⚠️ y **ya existe hoy sin la feature** por la FK de `order_adjustments`.
- ▶ Sale una regla del subsistema: **`orders` → `order_items` → hijas**. (3) ~~«`addons` ausente no toca nada, como `general`»~~ — medido con petición firmada real: un `PUT` sin `general` **BORRA** las respuestas generales (defecto preexistente, y el contrato documenta lo contrario).
- ⚠️⚠️ **EL CAMBIO DE DISEÑO QUE TRAJO (D9)**: la puerta del reconciliador NO es el eje, es **`LineFacts::birthValue() === 0`** — el eje es configuración MUTABLE y pasar «Tarta» de `booking` a `postform` habría puesto en manos del cliente líneas ya cobradas (medido sobre un pedido real: `settled` → `refund_at_park −4,00 €`) **con el libro cerrando en VERDE**, o sea con la guarda escrita pasando con el defecto puesto.
- ⚠️ **Segundo bloqueante: TRES listas blancas entre el formulario y la fila** (`ADDON_PIVOT_COLUMNS` · `sanitizePivotData()` · el `fillForm()` de «Configurar») y **las tres callan al olvidarse** — la peor **revierte un `postform` a `booking` al tocar la posición en la lista**.
- ⚠️ **Las tres escrituras NO son simétricas** (§4.5.1): alta y subida escriben `recordEdit(+Δ)`, **bajar TAMBIÉN escribe** (sin él `nac` cae a −12,00 € e `I1` falla) y **retirar NO** (el libro ya emite su `−fila`, y escribirlo restaría dos veces → pedido «en revisión» y el cliente sin desglose).
- ⚠️ **D10**: el plazo pasa a OBLIGATORIO — `null` heredaba el reloj torcido de §4.9.
- ⚠️ **D12: NO entra anti-bot** (para llegar al POST hay que traer un HMAC válido de esa URL; Turnstile rompería el `PUT` de la API y falla también a personas); en su lugar, limitar **por reserva** y agrupar el correo.
- ⚠️ **Los `<input disabled>` NO se envían**: con plazos por complemento, un guardado normal cancelaría el que está fuera de plazo — «ofrecido» excluye lo cerrado.
- ❗ **Nadie invita al cliente a volver a comprar** (§4.7·quinquies): el aviso muere al completar las fichas y el correo habla solo de datos de invitados
