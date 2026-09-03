# [SPEC] Complementos que se venden DESPUÉS de reservar — el extra que se elige en el post-form

> Estado: 🟦 **EN REVISIÓN** (diseño; ninguna línea de código escrita) ·
> Última actualización: 2026-09-03 · Decisión asociada: `DECISIONES #413`.
> Verificado contra código y BD: 2026-09-03 (catálogo real, `AddonResolver`, `OrderItemEditor`,
> `LineFacts`, `OrderBook`, post-form web y API).
> Se invalida si: cambia `#244` (nada se cobra online post-reserva), cambia la forma del LIBRO
> (`specs/desglose-libro.md`) o el post-form deja de ser la superficie del cliente.
> Carril: producto/reservas. Autor: agente, 2026-09-03.

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

### 1.1 · Lo MEDIDO (2026-09-03, sobre la BD local)

| Hecho | Medida | Dónde |
|---|---|---|
| Complementos en catálogo | **8** (5 reales, 2 portadores de fiesta mixta ocultos, 1 hora extra) | `ticket_types` `type=addon` |
| Enganches | **29**, *todos* `quantity_mode=fixed` | `product_addons` |
| Enganches incluidos / obligatorios / en grupo / con `max_qty` | **0 / 0 / 0 / 0** | `product_addons` |
| Packs con post-form | **2** (Cumpleaños Jump y Kids): 5 campos por invitado, 2 generales, 4 complementos | `ticket_types.guest_fields` |
| Líneas hijas vivas en la base | 13 | `order_items` con `parent_item_id` |

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

### 1.3 · La propiedad que lo hace seguro (verificada en `LineFacts`)

    nac(i)        = fila(i) − Δ(i)                  (LineFacts::birthValue)
    online_nac(i) = max(0, nac(i) − reparto(i))     (LineFacts::onlineAtBirth)

Una línea creada DESPUÉS del pedido lleva un ajuste `edit` de su importe exacto, así que
**`nac = 0` y `online_nac = 0`: no aportó ni un céntimo al cobro online**. De ahí sale, sin
programarla, la propiedad que gobierna toda la feature:

> **Quitar un complemento de venta posterior es NEUTRO en dinero.** El Total baja lo mismo que
> subió, no hay devolución que hacer, y las cuatro identidades del libro siguen cerrando
> (`I1` suma `nac = 0`; la cancelación emite su movimiento `−fila` y `I3` cuadra).

⚠️ **Esto no es una casualidad que haya que cuidar: es lo que el eje nuevo va a garantizar.** Si un
complemento de venta posterior pudiera además nacer con el pedido, la propiedad se rompería y quitar
uno obligaría a devolver dinero. Por eso §4.1 lo prohíbe **en las dos direcciones**.

## 2. Objetivo

Que el parque pueda declarar, **desde el panel y sin tocar código**, que un complemento se vende
después de reservar; que el cliente lo elija —y lo cambie— en su post-form mientras el plazo esté
abierto; y que el dinero aparezca donde ya aparece todo lo demás: una línea con su fecha en el libro
y un saldo que se liquida en el parque.

**Criterios de éxito medibles**

1. Los **29 enganches actuales no cambian de conducta**: medido antes y después, la oferta del
   embudo y el cobro son idénticos (caso de CONTROL).
2. Un complemento de venta posterior **no se puede comprar en el embudo** ni por la web, ni por la
   API, ni en el alta manual del panel — tampoco con una cesta forjada.
3. Añadir uno deja el saldo del pedido en «A pagar en el parque» por su importe exacto; quitarlo lo
   devuelve al valor previo **al céntimo**, sin generar ninguna devolución.
4. N guardados simultáneos del post-form escriben **una** línea, no N (verificador con `pcntl` sobre
   MySQL, **visto FALLAR sin el lock**).
5. Pasado el plazo del complemento, la sección se pinta pero no se guarda (y un `POST` forjado se
   rechaza).

**Fuera de alcance, a propósito**

- **Cobro online post-reserva** (`[DECIDIDO owner, 2026-09-03]`, Q1): se paga en el parque. Cambiarlo
  contradice `#244` y es una tanda propia sobre `PAY-01`/`PAY-02`.
- **Complementos que OCUPAN aforo** (la hora extra): prohibidos en venta posterior — §4.3·5.
- **Reservas sin post-form**: no hay superficie de cliente donde ofrecerlo. No se construye una;
  la puerta es `acceptsGuestForm()` y la feature viaja sola el día que esa puerta se abra (§4.2).
- **Arreglar el reloj de `isFinishedInPractice()`** (§4.9): preexistente, con ficha propia en
  `DEUDA.md`.

## 3. Opciones consideradas

| | Qué es | Por qué NO (o sí) |
|---|---|---|
| **A · Un producto por variante** | «Cumpleaños con cubo de refrescos», «…con tapas» | ⛔ Es exactamente lo que el owner rechazó al diseñar la hora extra (`specs/hora-extra.md` §1): satura el catálogo y multiplica las combinaciones |
| **B · Campo `postform` en `ticket_types`** | el complemento declara «me vendo después» | ⛔ Rompe el white-label por enganche: la tarta podría querer venderse al reservar en un pack y después en otro. Y contradice la doctrina del pivote heredada del origen (*«config por enganche, no global»*, migración `2026_06_05_000002`) |
| **C · Reutilizar `OrderItemEditor::edit()` desde el post-form** | el cliente entra por la misma puerta que el operador | ⛔ Exige permiso `orders.edit_item`, token optimista y un `User` autenticado —el post-form se abre **sin sesión**, con firma— y es la clase más delicada del sistema (`CRITICAL_RE`, lock de zona/día). Conducirla desde una superficie pública es superficie de ataque que nadie pidió |
| **D · Eje `stage` en el PIVOTE + servicio de dominio propio** ✅ | el enganche dice cuándo se vende; un servicio calcado de `MixedPartySurcharge` reconcilia | ✅ Espeja `event_fields.stage` (mismo vocabulario), respeta la doctrina del pivote, deja los 29 enganches intactos por defecto y **no toca aforo** |

## 4. Diseño elegido — D

### 4.1 · El eje: `product_addons.stage`

| valor | qué significa | por defecto |
|---|---|---|
| `booking` | se vende **al reservar** (y el panel puede añadirlo después, como hoy) | ✅ sí — los 29 enganches actuales |
| `postform` | **no nace nunca con el pedido**; se añade después | — |

▶ **La definición es «cuándo se VENDE», no «dónde lo ve el cliente»**, y esa precisión es la que
sostiene la propiedad de §1.3. De ella sale una tabla de dos por dos sin zonas grises:

| | nace con el pedido | se añade después |
|---|---|---|
| `booking` | ✅ checkout web/API **y** alta manual del panel | ✅ sólo el **panel** (como hoy) |
| `postform` | ⛔ **nunca, en ninguna superficie** | ✅ panel **y cliente** (post-form) |

⚠️⚠️ **La casilla «nunca» incluye el ALTA MANUAL del panel, y es deliberado.** Sería tentador dejar
que el mostrador venda un cubo de refrescos dentro del pedido que está creando; hacerlo daría a esa
línea `nac > 0`, y entonces el cliente podría retirar desde su post-form algo que **sí se cobró**,
generando una devolución. El operador tiene el camino de siempre: crear el pedido y añadírselo desde
«Gestionar → Complementos», que es una edición y nace con `nac = 0`. *Una excepción cómoda en el
mostrador convertiría una propiedad demostrable en un caso particular.*

⚠️ **El vocabulario es prestado a propósito**: `event_fields` ya usa `stage` con `booking`/`postform`
para decir en qué fase se captura un campo (`sistemas/POSTFORM-INVITADOS.md` §3.2). Un tercer nombre
para la misma idea sería una palabra nueva que aprender.

⚠️ **No hay valor `both`, y es una decisión.** Con `both`, un complemento podría comprarse en el
embudo y modificarse luego: reducir esa línea sí debería dinero, y toda la feature necesitaría
distinguir por unidad qué se cobró y qué no. El día que haga falta, entra **de forma aditiva** y con
decisión propia. Hoy, dos valores cerrados y ninguna zona gris.

### 4.2 · La puerta del cliente NO es «es un pack»

`[DECIDIDO owner, 2026-09-03]` (Q4): *«no es por producto, sería por postform más bien»*.

La sección de extras se ofrece **exactamente donde ya se ofrece el post-form**: la puerta es
`OrderItem::acceptsGuestForm()` —el mismo gate que protege la página y la API—, nunca `isPack()`.

▶ Hoy eso significa cumpleaños, porque `guestFormStatus()` exige pack con `guest_fields`; pero es
una **consecuencia**, no una restricción escrita en esta feature. El día que el post-form llegue a
otro tipo de reserva (hay ficha en `DEUDA.md` sobre su elegibilidad), los extras viajan con él **sin
una línea de código**.

⚠️ **Un enganche `postform` sobre un producto sin post-form no se rechaza: se AVISA.** Rechazarlo
ataría la configuración a `isPack()`, justo lo que esta decisión evita. El panel dice, al guardar el
enganche, que ese producto no tiene formulario post-reserva y que **sólo el operador** podrá
añadirlo. Falla hacia visible sin cerrar la puerta al futuro.

### 4.3 · Los guards del enganche `postform` (y su cinturón)

Un enganche `postform` **no puede** ser, y cada prohibición cierra un agujero concreto:

| Prohibido | Por qué |
|---|---|
| 1 · `is_mandatory` | Un obligatorio se **auto-inyecta** sin que nadie lo pida (`AddonResolver::resolve` paso 2). Inyectar una deuda después de la venta, sin un clic, es indefendible |
| 2 · `is_included` | «Incluido» significa gratis dentro del pack, y eso se decide **al vender**. Un incluido de venta posterior es una contradicción de términos |
| 3 · `quantity_mode = per_guest` | La cantidad seguiría al nº de **invitados** de la fiesta, que son los niños. El caso del owner es *para los adultos*: atarlo a los niños sería un número equivocado con aspecto de correcto |
| 4 · `choice_group` | Un grupo excluyente **siempre tiene un elegido**: `groupDefault()` selecciona uno si el cliente no elige. Post-venta eso crea un cargo que nadie pidió. (Con una opción «ninguno» explícita podría reabrirse; hoy no existe ese concepto) |
| 5 · complemento con `occupies_after_parent` | Ocupar aforo después de reservar exige el lock de zona/día, la franja de aterrizaje y el recálculo de la oferta (`specs/hora-extra.md` §4.4–§4.6). **Esta feature no toca aforo, y esa es la mitad de su coste** |
| 6 · `requires_addon_id` de OTRA fase | El requisito nunca estaría en la selección de esta fase, así que el dependiente quedaría **invisible sin fallar**. La dependencia se permite **dentro de la misma fase** |

**Y `max_qty` pasa a ser OBLIGATORIO en un enganche `postform`** (decisión derivada, vetable): el
enlace del post-form viaja por correo y puede reenviarse, así que la deuda máxima que un tercero
puede crear tiene que estar **declarada por el parque**, no dejada al tope de UI de 20 unidades que
hoy vive en `AddonResolver::viewModel()`. Es el mismo patrón que «un ocupante sin duración es una
configuración imposible» (`specs/hora-extra.md` §4.1).

⚠️⚠️ **Los guards viven en `ProductAddon::booted()` Y se re-validan en la autoridad**, por la lección
de `#299` que la hora extra ya pagó: los eventos de Eloquent **no ven `Query\Builder::update()` ni
SQL crudo**. Una fila torcida por la puerta de atrás no puede degradar a «se vende normal»: no se
ofrece y no se vende (fallar hacia invisible, la doctrina de la casa).

### 4.4 · La autoridad: `AddonResolver` gana un eje, no un gemelo

`AddonResolver::resolve()` y `viewModel()` son la **fuente única** que comparten el presupuesto
(`CartPricer`), el cobro (`OrderCreator`), la oferta (`AddonOfferReader`) y el alta manual. El eje
entra ahí, con `booking` por defecto:

- `OrderCreator` y `CartPricer` resuelven **`booking`** → un `postform` que llegue en una cesta
  forjada **no está entre los ofrecidos** y se rechaza con `tickets.errors.unavailable`, que es la
  conducta que ya existe para cualquier id no ofrecido (regla 12).
- `AddonOfferReader` (el paso 3 del cajón y `POST /catalog/products/{id}/addons`) ofrece `booking`.
- `CatalogReader::addons()` (la ficha pública del producto) ofrece `booking`.
- El post-form resuelve **`postform`**.
- **El PANEL no filtra por fase**: `TicketType::addons()` se queda como está, y por eso
  `OrderItemEditor` y «Gestionar → Complementos» siguen viendo todo. *El eje gobierna la venta, no
  la capacidad del operador.*

⚠️ **El filtro NO va dentro de la relación `addons()`**: hay 12 consumidores y uno de ellos es el
editor del panel, que dejaría de encontrar la línea que tiene que mover. Va en cada superficie, con
el valor explícito. *Un filtro escondido en la relación se aplica también donde nadie lo pensó.*

### 4.5 · El servicio de dominio (futuro)

`App\Domain\Booking\Services\PostFormAddons` (futuro), calcado de `MixedPartySurcharge`:

1. Toma la reserva **con `lockForUpdate`** dentro de una transacción (dos guardados simultáneos del
   post-form escribirían dos líneas: es el mismo riesgo que el suplemento mixto ya cubre).
2. Re-deriva la oferta y **re-valida el estado deseado con `AddonResolver::resolve(stage: postform)`**
   — nunca acepta un importe ni una cantidad calculados por la pantalla (`PAY-12`).
3. **Reconcilia**: crea la que falte, sube o baja la que cambió, cancela la que sobre.
4. Por cada gesto escribe `Order::recordEdit(±Δ)` **en la misma transacción**, con el contexto
   `addon_change` que el libro ya sabe rotular («+2 Cubo de refrescos»).
5. Audita (`orders.postform_addons_changed`, sin PII, con el `via` del post-form) y notifica.

**Las cinco reglas del reconciliador**, cada una cerrando un borde real:

- **R1 · Sólo gobierna lo que está OFRECIDO.** El estado deseado manda sobre los ids que hoy se
  ofrecen; **cualquier otra línea se conserva intacta**. ⚠️⚠️ Sin esta regla, retirar del catálogo
  un complemento ya vendido haría que el siguiente guardado del cliente **lo cancelara en silencio**
  — el estado deseado no lo incluiría porque la pantalla no lo pinta. Es la misma trampa que
  `submitGuestForm` ya evita con su `array_diff_key` sobre las respuestas de la otra fase.
- **R2 · La línea conserva su `unit_price`.** Subir de 2 a 3 cobra la tercera **al precio de la
  línea**, no al de hoy: es exactamente lo que hace el editor del panel
  (`ItemEditPricing::computeAddonPricing`, `$delta = ($q − $oldQ) × $child->unit_price`).
  Re-tarificar la línea entera cambiaría el precio de unidades ya comunicadas.
  ⚠️ Quitarla del todo y volver a añadirla **sí** tarifica a hoy: es una línea nueva.
- **R3 · Nunca toca una línea gobernada por otro mecanismo** (`MixedPartySurcharge::governedLineIds`),
  el mismo cinturón que el editor del panel lleva desde `specs/cumple-mixto.md` §12.4.
- **R4 · Idempotente**: guardar dos veces el mismo estado no escribe nada y no manda ningún correo.
- **R5 · Simétrica**: bajar es el mismo camino que subir, no una función de deshacer.

⚠️ **El actor**: quien entra por enlace firmado no tiene sesión, así que el actor del ajuste es **el
titular del pedido** (`$order->user`), igual que hace `submitGuestForm` con el suplemento mixto. El
CANAL (`signed_link` / `account`) viaja en el audit, que es donde distingue quién entró por dónde.

⚠️ **Orden de locks, para que nadie tenga que deducirlo**: este servicio toma `order_items` y luego
`orders` (dentro de `recordEdit`). El editor del panel toma `slots` → `order_items` y escribe en
`orders` **post-commit**. `orders` va siempre después de `order_items` en los dos, y este servicio
no toca `slots`: **no hay inversión posible**, y por tanto no hay interbloqueo que diseñar.

### 4.6 · El plazo de corte — `product_addons.postform_cutoff_hours`

`[DECIDIDO owner, 2026-09-03]` (Q2): **N horas antes, configurable POR COMPLEMENTO** — «tapas 48 h»
y «cubo de refrescos 2 h» no necesitan la misma antelación.

- Columna nueva en el pivote, **nullable**. `null` = **sin plazo propio**: hereda el cierre del
  post-form. Es la doctrina de `AFORO-11` (`null` = hereda) aplicada al tiempo.
- Se mide contra el **inicio de la franja de la reserva** (`slot.date` + `start_time`) con
  **`DisplayTime::now()`**, que es la convención del subsistema de venta (`AFORO-09`) y la que ya
  usan `SlotOffer::passesIntradayFloor` y el backstop de `OrderCreator`.
- **Dos puertas, y la del corte es la estricta**: cuando el plazo ha pasado pero el post-form sigue
  abierto, la sección de extras se pinta en **solo lectura con su motivo**, y un `POST` forjado se
  rechaza. Nunca al revés.
- El corte gobierna **las dos direcciones**: pasado el plazo no se añade **ni se quita**. Si se
  pudiera quitar, el parque habría comprado la comida y perdería el importe sin margen de reacción,
  que es justo lo que el plazo existe para evitar.

### 4.7 · Las superficies

| # | Superficie | Qué cambia |
|---|---|---|
| 1 | **Post-form web** (`reservation/guests.blade.php`) | Sección «Extras» nueva: steppers, importe por línea, total, y la frase de que se paga en el parque. Blade + **JS plano** (esa página no lleva Livewire ni Alpine) y CSS `.gf-*` en `public/css/site.css` ⚠️ (fichero del carril de diseño: se declara en el reparto ANTES de tocarlo) |
| 2 | **API** (`GET`/`PUT /reservations/{id}/guest-form`) | `GuestFormResource` gana `addons` (la oferta, con los DTO que ya publica `ResolvedAddonsResource`); el `PUT` acepta `addons`. **El contrato primero** (`specs/api-v1.md`) |
| 3 | **Panel · catálogo** | El enganche gana «Cuándo se vende» y «Plazo de corte» en `AddonsRelationManager::pivotConfigFields()` + su saneo |
| 4 | **Panel · ficha del pedido** | **Sin cambios**: la línea es un complemento como cualquier otro y el libro ya la pinta con su fecha |
| 5 | **Hoja de sala y Resumen del día** | **Sin cambios**: ya listan los complementos vivos (`ReservationSlip`; el resumen desde `#411`) |
| 6 | **Correo al cliente** | Confirmación con el bloque del libro (`EmailBookBlock`), con **voz propia** («has añadido…»), no la del parque — la distinción de voz que `MixedPartySurcharge::notify()` ya hace por `reason` |

⚠️ **Los extras NO gatean «FORM OK»**: `guestFormStatus()` y `guestFormProgress()` no los miran. Son
opcionales; contarlos convertiría un extra no comprado en un formulario incompleto.

▶ **Aviso al parque** (decisión derivada, vetable): **ninguno nuevo**. La hoja de sala y el Resumen
del día ya lo dicen, el suplemento mixto tampoco notifica al operador, y lo que garantiza que el
parque se entera **a tiempo** es el plazo de corte del complemento, no un correo más.

### 4.8 · Los bordes, y qué los cierra

1. **El operador cambia el producto de la reserva** → el complemento puede quedar huérfano:
   `orphan_addons` de `OrderItemEditor` ya lo bloquea o lo retira. ✅ existente.
2. **El operador baja los invitados** → un `fixed` no se re-escala (sólo los `per_guest`, prohibidos
   aquí). ✅ por construcción.
3. **Se cancela la reserva o el pedido** → `OrderItemCanceller` cascadea a las hijas y el libro
   retira su valor. ✅ existente.
4. **Reembolso total del pedido** → se prorratea entre reservas por `onlineAtBirth`; una línea de
   venta posterior aporta **0** y no recibe nada. ✅ correcto: nunca se cobró online.
5. **El cliente y el operador guardan a la vez** → los dos pasan por `lockForUpdate` del ítem. ✅
6. **El complemento se retira del catálogo con líneas vendidas** → R1: la línea se conserva, se
   pinta en solo lectura. ✅
7. **El titular ejerce la supresión (art. 17)** → la ruta del post-form responde **410** y no hay
   dónde añadir nada (`RGPD-03`). ✅ existente.
8. **`TicketIssuer`** no emite tickets para hijas (`whereNull('parent_item_id')`). ✅ existente.
9. **El precio del complemento cambia entre añadir y la fiesta** → la línea conserva el suyo (R2), y
   el libro enseña lo que se comunicó. Coherente con `[DECIDIDO owner]` de `specs/hora-extra.md`
   §4.11: los complementos se tarifican al día de la COMPRA, no al de la visita.

### 4.9 · ⚠️ Un defecto PREEXISTENTE que este diseño no arregla (y por qué)

**`OrderItem::isFinishedInPractice()` declara terminada una reserva entre 1 y 2 horas TARDE.** Las
franjas guardan hora de pared del parque —lo demuestra que `SlotOffer::passesIntradayFloor` y el
backstop de `OrderCreator` las comparan contra `DisplayTime::now()`—, pero ese predicado las parsea
con `CarbonImmutable::parse()`, que las interpreta en `config('app.timezone')` = **UTC**.

Medido el 2026-09-03 a las 08:40 CEST: una franja que terminó **hace una hora en hora del parque**
sale como «aún viva».

Alcance de lo que cuelga de ese predicado: el `readonly` del post-form, `item_finished` del gate de
edición del panel, y la ventana de dinero del suplemento mixto (`Order::itemGateResolved`).
⚠️ **No contradice a `#244`**: aquella decisión afirma que la ventana del cliente y la del dinero se
cierran **a la vez**, y siguen haciéndolo — cuelgan del mismo predicado. Lo que pasa es que **las dos
se cierran tarde**.

▶ Ficha propia en `DEUDA.md` con esta reproducción. **No se arregla dentro de esta feature**: toca
tres subsistemas y merece su propia guarda. Lo que sí hace esta spec es **no heredar el error**: el
plazo de corte (§4.6) usa `DisplayTime::now()`.

### 4.10 · ⚠️ Un segundo hallazgo, este del editor del panel

**`OrderItemEditor::edit()` no sabe BAJAR la cantidad de un complemento.** El código sólo contempla
dos ramas: `$q === 0` → cancelar, y `$q > $child->quantity` → subir. Poner 3 → 2 **no hace nada**,
y `ItemEditPricing::computeAddonPricing()` tampoco lo registra (no entra ni en `removed` ni en
`updated`), así que no hay error, ni correo, ni rastro.

Medido leyendo el código el 2026-09-03; **no conducido en el panel**, así que no está confirmado que
la UI ofrezca poner 2 donde hay 3.

▶ Este diseño **no depende** de arreglarlo (el servicio nuevo baja de verdad), pero deja al operador
con menos capacidad que al cliente, lo cual es una asimetría difícil de defender. **Propuesta:
arreglarlo en la misma tanda** —es una rama en el editor y un caso en `computeAddonPricing`— o ficha
en `DEUDA.md` si el owner prefiere no tocar el `CRITICAL_RE` en esta tanda.

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **`PAY-16`/`PAY-17`** (el libro cierra) | ❗ **Directo, y es el corazón de la verificación**: cada gesto escribe su `edit` con el importe exacto de la línea, así que `nac = 0` y las cuatro identidades siguen cerrando. Con guarda por escenario |
| **`PAY-12`** (el precio se calcula en servidor) | Se aplica tal cual: el estado deseado del cliente es una intención; `AddonResolver` re-resuelve |
| `PAY-18`/`PAY-19` | Sin impacto: no se toca el sello ni la re-tarifa de la fecha |
| `AFORO-*` | **Ninguno**: un complemento de venta posterior no puede ocupar (§4.3·5); `slot_id` nulo y `seats` 0, como cualquier complemento neutro |
| `RGPD-03`/`RGPD-04` | Sin cambio: la sección vive dentro de la página que ya los cumple (410 al anonimizar, `no-store`) |
| `SEC-06` (anti-abuso) | La ruta ya lleva `throttle:30,1`. Se añade el tope declarado (`max_qty` obligatorio, §4.3) |
| **`SUITE-04`** | ❗ Escenario nuevo de concurrencia sobre MySQL (§6·4) |

▶ **`CRITICAL_RE`**: el servicio nuevo escribe dinero, así que entra en el regex del `pre-push` y en
`CriticalPathGateTest`, como entraron `AddonResolver` y `MixedPartySurcharge`.

## 6. Plan de verificación empírica

1. **CONTROL de que nada viejo se mueve**: con los 29 enganches en `booking`, la oferta del embudo y
   el importe cobrado son **idénticos** antes y después (medidos sobre los mismos datos).
2. **No se puede comprar en el embudo**: un `postform` no aparece en `POST /catalog/products/{id}/addons`,
   ni en la ficha del producto, ni en el alta manual; y metido a mano en la cesta, `OrderCreator` lo
   **rechaza** (caso de cesta forjada).
3. **El dinero, al céntimo**: añadir → saldo «A pagar en el parque» por su importe; quitar → saldo
   **exactamente** el previo y **cero** devoluciones. Las cuatro identidades del libro aseveradas en
   los dos estados (`assertBookCloses`).
4. ❗ **Concurrencia**: N guardados simultáneos del post-form con el mismo estado escriben **una**
   línea. Comando `pcntl` sobre MySQL, con **control negativo** — **se escribe ANTES y se ve
   FALLAR** (la condición innegociable que `specs/hora-extra.md` §7·D1 dejó como doctrina).
5. **El plazo**: pasado el corte del complemento, la sección es de solo lectura y el `POST` se
   rechaza; con `null` hereda el cierre del post-form. Con caso de CONTROL en el borde exacto.
6. **Los seis guards del enganche** (§4.3), cada uno con su caso, **en las dos direcciones**: poner
   la fase sobre un enganche prohibido y poner la propiedad prohibida sobre un enganche `postform`
   (la lección de `#324`).
7. **El cinturón**: una fila torcida por `Query\Builder::update()` (esquivando los eventos) **ni se
   ofrece ni se vende**, con control de que la sana sí.
8. **R1**: retirar el complemento del catálogo y guardar el post-form **no cancela** la línea vendida.
9. **R2**: subir de 2 a 3 cobra la tercera al precio de la línea, no al de hoy (con el precio del
   catálogo cambiado en medio).
10. **Contrato**: `GET`/`PUT` cumplen `openapi/v1.yaml` (`additionalProperties: false`), y `addons`
    ausente en el `PUT` **no toca nada** (la semántica nullable que `general` ya tiene).
11. **Navegador real**: el guion del post-form con la sección nueva, capturas incluidas.
12. **Mutación**: cada guarda nueva vista morder, con pasada de CONTROL previa en verde y veredicto
    por código de salida.

## 7. Revisión y decisión

### 7.1 · Las cuatro del owner (`[DECIDIDO owner, 2026-09-03]`)

| | Pregunta | Respuesta |
|---|---|---|
| **Q1** | ¿Cuándo se paga? | **En el parque** — aplica `#244`. El cobro online post-reserva queda fuera |
| **Q2** | ¿Hasta cuándo? | **N horas antes, por complemento** (columna en el pivote, `null` = hereda) |
| **Q3** | ¿Quién puede quitar? | **El cliente, mientras el plazo esté abierto** — incluido lo que le añadió el parque por teléfono. Es neutro en dinero por construcción (§1.3) |
| **Q4** | ¿Alcance? | **Donde hay post-form**, no «donde hay pack» (§4.2). Hoy, cumpleaños |

### 7.2 · Decisiones derivadas (tomadas aquí; el owner puede vetar cualquiera)

- **D1** · No existe el valor `both` (§4.1).
- **D2** · Un `postform` **no se vende tampoco en el alta manual** del panel (§4.1).
- **D3** · `max_qty` es **obligatorio** en un enganche `postform` (§4.3).
- **D4** · Un enganche `postform` sobre un producto sin post-form **avisa, no se rechaza** (§4.2).
- **D5** · El plazo de corte gobierna **las dos direcciones**: pasado, ni se añade ni se quita (§4.6).
- **D6** · **Ningún aviso nuevo al parque**: la hoja y el Resumen del día ya lo dicen (§4.7).
- **D7** · Los extras **no cuentan** para «FORM OK» (§4.7).
- **D8** · El correo al cliente lleva el bloque del libro y **voz propia** (§4.7).

### 7.3 · Lo que falta antes de escribir código

1. **Revisión adversarial por otro agente** (`CONVENCIONES §5`), con el encargo explícito de entrar
   **por donde se configura el dato**, no sólo por donde se vende: es la lente que faltó en la
   primera revisión de la hora extra y la que encontró que el interruptor no se podía encender
   (`specs/hora-extra.md` §4.9).
2. El ✅ del owner a esta spec y a las ocho derivadas de §7.2.
3. Decidir si el defecto de §4.10 (el editor no baja cantidades) entra en la tanda o va a `DEUDA.md`.
