# [SPEC] El cliente cambia el número de INVITADOS desde su post-formulario

> Estado: ⬜ **borrador** · Última actualización: 2026-09-07 · Decisión asociada: `DECISIONES #444`.
>
> ❗❗❗ **Esto NO es «una pantalla»: es la primera vez que el cliente mueve AFORO y DINERO por su
> cuenta.** `complementos-post-reserva.md` §4.3·5 dejó fuera *a propósito* todo lo que ocupa
> —*«esta feature no toca aforo, y esa es la mitad de su coste»*—. Aquí no hay escapatoria: subir
> invitados sube `seats`, consume `packs.max_guests_per_slot` y, por `#151`, plazas de entrada de la
> zona. Todo lo que sigue está escrito alrededor de ese hecho.

---

## 1. Contexto y problema

### 1.1 · Lo que pasa hoy, REPRODUCIDO

Sobre la reserva `R-OXQJWM` (Pack Cumpleaños KIDS, 8 invitados), en transacción revertida:

```
Enviadas: 12 fichas para una línea de 8
sanitizeGuestData → 8 filas guardadas (se descartan 4)
nombres conservados: X1 … X8
```

Y la vista no ofrece la puerta: `reservation/guests.blade.php` pinta **exactamente `quantity`
fichas** (`@for ($i = 0; $i < $reservation->quantity; $i++)`), sin botón de añadir.

⚠️ **Y no hay ni una frase que le diga qué hacer.** El post-form tiene «llámanos» para **cinco**
situaciones —las tres edades sin producto, el extra fuera de plazo y el que no se pudo cambiar— y
ninguna para ésta. El contrato lo dice con todas las letras: `guest_count` es *«la cantidad ACTUAL de
invitados de la reserva: si **el operador** la sube, aparecen fichas nuevas vacías»*.

### 1.2 · Lo MEDIDO en producción (2026-09-07, solo lectura)

| | |
|---|---|
| Reservas de cumpleaños vivas | **16**, todas pagadas |
| Tamaño | 8 · 8 · 8 · 10 · 10 · 10 · 10 · 10 · 13 · 15 · 15 · 15 · 17 · 17 · 20 · 20 → mediana **13**, **2 en el techo** |
| Post-form | 5 envíos · **3 de 16 completos** |
| **Ediciones de CANTIDAD ya ocurridas** | **0** (2 ediciones de ítem en total, ninguna de cantidad) |
| Menores asignados / justificantes | 0 / 0 |
| Origen de los pedidos | **todos MANUALES** — `sales.online_enabled = 0` |

▶ **La lectura honesta de esas cifras**: el valor de esta feature está **en cuando se abra la venta
online**, no en esta semana —hoy todo se vende en el mostrador y el post-form llega por un enlace que
copia el operador—, y **el riesgo sí es de hoy**. Se construye entera y con su red, no a medias.

### 1.3 · Lo que YA existe, y es lo que hace este diseño posible

`OrderItemEditor::edit()` hace hoy, para el operador, **todo** lo que este encargo necesita: el lock
de zona/día como primera sentencia (`AFORO-01`/`AFORO-05`), la revalidación del cupo excluyendo la
huella propia y **con la ventana alargada** de su hora extra, el sello intacto, el re-escalado de los
complementos por-invitado y la secuencia financiera POST-COMMIT. **Lo que falta no es la máquina: es
la puerta del cliente.**

Medido sobre `R-OXQJWM` (14,95 €/invitado): 8→9 = **+14,95 €** · 8→12 = **+59,80 €** · 8→20 =
**+179,40 €**, y el precio unitario **no cambia** en ninguno.

⚠️ **`ESTADO.md` decía que subir la cantidad «re-tarifica por `PAY-18`». Medido: no.** Subir sin
mover el día conserva la tarifa histórica (`ItemEditPricing::computeEditPricing`), que es justo lo que
`PAY-19` decidió («14,00 € y no 24,00 €»). La única excepción es un producto con **tramos de
cantidad** (`#324`) — y los dos packs de hoy tienen **0 tramos**.

## 2. Objetivo

Que el cliente pueda **subir y bajar** los invitados de su reserva desde su post-formulario, dentro de
un plazo, y que el dinero aparezca donde ya aparece todo lo demás: **una línea del libro con su fecha
y un saldo que se liquida en el parque** (`#244`).

**Criterios de éxito medibles**

1. Subir y bajar funcionan desde las **dos** superficies del post-form (web firmada y API), y el
   Total y el saldo del pedido cambian **al céntimo** por el precio histórico de la línea.
2. **No se puede subir por encima de lo que la sala admite**: la revalidación corre bajo el MISMO
   `ZoneDaySlotLock` que la compra y el panel, y hay un escenario de `purchase:verify-oversell`
   **visto FALLAR** sin ella.
3. **No se puede bajar por debajo de** `min_qty` **ni** de los menores a cargo asignados **ni** de los
   justificantes firmados — y el motivo que se devuelve **distingue los tres**, porque el remedio de
   cada uno es distinto.
4. **Fuera de plazo no se mueve nada**, y un guardado normal del resto del formulario **no lo toca**.
5. Un guardado del cliente concurrente con una edición del operador **no pisa su trabajo** (token
   optimista) y **no produce interbloqueo** (verificador con `pcntl` sobre MySQL, visto fallar).
6. Bajar **pierde las fichas sobrantes** y la pantalla **lo dice antes de guardar**.
7. El suplemento de fiesta MIXTA y los complementos **por-invitado** —incluida la hora extra de
   `#443`— se reconcilian con la cantidad nueva, y el aforo de la fiesta con hora extra se revalida
   con su **ventana alargada**.
8. Los **29 enganches y las 16 reservas de hoy no cambian de conducta** con la feature apagada.

**Fuera de alcance, a propósito**

- **Cobro online post-reserva** (`#244`, `[DECIDIDO owner]`): se paga o se devuelve en el parque.
- **Cambiar la FECHA, la HORA o el PRODUCTO** desde el post-form: son otras preguntas, con otras
  reglas (`PAY-18`, el sello) y otra pantalla.
- **Correo al operador** (`[DECIDIDO owner, 2026-09-07]`: *«basta con el libro por ahora»*).
- **Reservas sin post-form**: no hay superficie donde ofrecerlo; la puerta es `acceptsGuestForm()`.

## 3. Opciones consideradas

| | Qué | Veredicto |
|---|---|---|
| **A** | Llamar a `OrderItemEditor::edit()` con el titular de actor | ⛔ **Descartada.** Le daría al cliente **la puerta del operador**: ese método exige `orders.edit_item` y además mueve producto, fecha y complementos. Relajar el permiso para el titular es `SEC-04` por la puerta de atrás, y el alcance de la puerta no lo decide quien la cruza |
| **B** | **Servicio de dominio propio**, con la receta de lock del editor y la puerta de cliente de `PostFormAddons` | ✅ **Elegida** |
| **C** | Extraer el núcleo común de `OrderItemEditor` y compartirlo | ⛔ **Descartada.** Es la pieza más delicada del panel, acaba de desmontarse en ocho tandas (`desmontar-view-order.md`) y está en el `CRITICAL_RE`. El riesgo no compra nada: lo que se comparte de verdad —la tarificación y el lock— **ya son servicios sueltos** (`ItemEditPricing`, `ZoneDaySlotLock`) |

## 4. Diseño elegido — B

### 4.1 · El servicio: `Booking\Services\GuestCountAdjuster` (futuro)

```
adjust(OrderItem $principal, int $desired, string $via, ?User $by, ?string $expectedVersion): GuestCountChange   (futuro)
```

Hace **una sola cosa** —cambiar cuántos invitados tiene una reserva— y por eso puede ser del cliente.

### 4.2 · ❗❗❗ La receta de LOCK es la del EDITOR, no la de `PostFormAddons`

`PostFormAddons` toma **`orders` primero**, y esa regla se eligió tras **reproducir** un interbloqueo
real (`#413` §4.5.4). **Aquí no vale**: `AFORO-01` exige que el `SELECT … FOR UPDATE` de las franjas
sea la **PRIMERA sentencia de la transacción**, con las zonas y fechas resueltas FUERA — cualquier
lectura previa fija el snapshot antes del lock y el recuento de aforo lee datos pre-commit del rival.

▶ Por tanto: **`slots` → `order_items`**, y la secuencia financiera **POST-COMMIT**, exactamente como
`OrderItemEditor`.

⚠️⚠️ **Y eso mete un TERCER orden de bloqueo en el MISMO guardado del post-form**, que ya encadena
dos: `MixedPartySurcharge` (`order_items` → `orders`, vía el `recordEdit` anidado) y `PostFormAddons`
(`orders` → `order_items`). **Esas dos ya se cruzan hoy**, sin esta feature. ▶ **Requisito de la T0:
reproducirlo o descartarlo con dos conexiones reales ANTES de construir encima** — la primera versión
de `#413` afirmó que no había inversión posible y la revisión la reprodujo.

### 4.3 · Lo que se re-valida BAJO el lock (`SEC-04` aplicado al tiempo)

Entre pintar el formulario y guardarlo puede pasar la fiesta, una cancelación, el plazo o **otra
reserva que llene la sala**. Todo se re-comprueba dentro:

1. `acceptsGuestForm()` (pedido pagado y producto con invitados) · no cancelada · no terminada.
2. **El plazo** (§4.5).
3. **El techo**: `max_qty` del pack.
4. **El suelo**: §4.4.
5. **El aforo**: `availableGuestsFor($slot, $type, [], $item->id, $item->extra_minutes)` ≥ `seats`
   nuevos. ⚠️ Los `extra_minutes` **no son decoración**: una fiesta con hora extra ocupa más rato y
   tiene que caber alargada, que es lo que `#425` enseñó al editor.
6. **El token optimista** (`updated_at` de la reserva, la convención de las cinco puertas del
   operador).

### 4.4 · ❗❗ El SUELO son TRES cosas, y el motivo de cada una es distinto

```
suelo = max( min_qty del pack , menores a cargo asignados + justificantes firmados )
```

- **`min_qty`**: es el mínimo CONTRATABLE. Bajar de ahí existe, pero es una excepción del OPERADOR con
  permiso propio y rastro (`orders.edit_item_below_minimum`, D7 de `cumple-mixto.md` §23): **el
  cliente no la tiene y no se le da**.
- **Lo que ya tiene dueño**: `GuardianPlaces::takenIn()` — menores a cargo asignados + justificantes
  firmados. ⚠️ Sin esto, bajar dejaría la hoja de sala imprimiendo **plazas negativas** (ficha viva en
  `DEUDA.md`, reproducida: cantidad 1 con 3 menores asignados → la hoja calcula **−2**). Esta feature
  **cierra ese agujero por su lado**; el del panel sigue abierto.
- ⚠️ **El motivo que se devuelve distingue los dos**, porque el remedio no es el mismo: al primero se
  le dice el mínimo del pack; al segundo, que primero tiene que quitar a alguien de la lista.

### 4.5 · El plazo — `packs.guest_count_cutoff_hours` (futuro)

`[DECIDIDO owner, 2026-09-07]`: *«hasta el plazo que pone el parte de celebración para poder editarlo,
vamos a hacerlo un día antes mínimo»*. ▶ **Ajuste del parque** (no del enganche: esto no es un
complemento), **por defecto 24 h**, medido con **`DisplayTime::now()` contra la hora de PARED de la
franja**, que es el mecanismo de `PostFormAddons::deadlineFor()`.

⚠️ **No se hereda `isFinishedInPractice()`**: es otra pregunta («¿ya terminó?»), no ésta («¿queda un
día?»). ▶ Y de paso, el docblock de `PostFormAddons::isWithinWindow()` **cita un defecto que `#426` ya
arregló** (dice que aquel predicado declara terminada una reserva 1–2 h tarde): se corrige al pasar.

### 4.6 · Lo que la escritura hace, en orden

| paso | dónde | qué |
|---|---|---|
| 1 | **bajo el lock** | `quantity` y `seats` nuevos. **El sello NO se toca** (`PAY-19`: solo re-sella un cambio de producto o de día) |
| 2 | **bajo el lock** | re-escalado de los complementos **por-invitado** (el mismo bucle que el editor), incluida **la hora extra por invitado de `#443`** |
| 3 | post-commit | `Order::recordEdit(±Δ)` sobre la línea, con `changes.quantity_change` — que es lo que el libro convierte en «Cantidad: 8 → 12» |
| 4 | post-commit | un `recordEdit` por cada complemento re-escalado, **atado a su hija** (`#170`) |
| 5 | post-commit | `AuditLogger::log('orders.guest_count_changed')` **sin PII** (`RGPD-02`): qué reserva, por dónde entró y el delta |
| 6 | post-commit | **un correo al cliente** con el bloque del LIBRO (`EmailBookBlock`), que dice el saldo nuevo |
| 7 | post-commit | la reconciliación del suplemento MIXTO, que ya cuelga de `submitGuestForm` |

⚠️ **Bajar escribe su `recordEdit` NEGATIVO**, como el editor: el saldo del libro decide solo qué
parte absorbe la puerta y qué parte aflora como «a devolver en el parque» (`#305`). **No se
auto-reembolsa nada** (`#157`: cancelar ≠ reembolsar).

### 4.7 · Los bordes, y qué los cierra

1. **Bajar destruye fichas.** `sanitizeGuestData($rows, $nuevo)` recorta, así que bajar de 15 a 10
   borra los nombres y las alergias de las fichas 11–15. **Hoy el panel hace exactamente lo mismo, en
   silencio.** ▶ Aquí la pantalla **lo dice antes de guardar**, con el número: *«se perderán los datos
   de N invitados»*.
2. **La cantidad y las fichas viajan en el MISMO guardado.** El orden es: primero la cantidad,
   después `submitGuestForm` — o el saneo recortaría contra la cantidad vieja.
3. **`guest_count` ausente = no lo toques** (la semántica que `#413` impuso a `guests` y `general`, y
   que nació de una pérdida de datos medida). **Nunca un `?? $quantity`.**
4. **Dos pestañas del mismo cliente**: el lock serializa, el token decide. El segundo recibe `stale`
   y su formulario se recarga.
5. **Una fiesta en el techo** (2 de las 16 de hoy): el control se pinta **deshabilitado con su
   motivo**, no se esconde — esconderlo es lo que hizo indescifrable el hueco original.
6. **Un pack sin `max_qty`**: el techo lo pone el aforo, y ya está.
7. **Reserva anonimizada** (`RGPD-01`): la puerta ya responde 410 antes de llegar aquí.

### 4.8 · El contrato

`GuestForm` gana lo que hace falta para pintar el control **sin recomponer ninguna regla en el
cliente** — los límites ya resueltos y el instante del corte:

```
guest_count_min · guest_count_max · guest_count_editable · guest_count_deadline · guest_count_locked_reason
```

y `GuestFormRequest` gana `guest_count` (entero, opcional — ausente = no lo toques).

⚠️ Los dos esquemas son `additionalProperties: false`, así que esto es **cambio de contrato** y va con
su entrada en `openapi/v1.yaml` y su caso de `ApiContractTest`.

## 5. Impacto en invariantes

| ID | Qué le pasa |
|---|---|
| **`AFORO-01` / `AFORO-05`** | **Alcanzados**: aparece un tercer escritor bajo el mismo lock. La receta no cambia; lo que cambia es quién la usa. El servicio entra en el **`CRITICAL_RE`** |
| **`AFORO-02`** | Sin cambio: aquí no se ofrece nada, se revalida |
| **`PAY-16` / `PAY-17`** | **Se conservan**: el delta va como `recordEdit`, que es el hecho del que vive `LineFacts::birthValue()`. Las cuatro identidades tienen que seguir cerrando **después** de subir y de bajar |
| **`PAY-18`** | **No aplica**: no se mueve la fecha. El precio es el histórico de la línea |
| **`PAY-19`** | **Se respeta**: la cantidad **no** re-sella. El invitado añadido entra al precio comunicado |
| **`RGPD-01`** | Sin cambio en la conducta; sí en la POBLACIÓN: bajar borra fichas de menores (menos datos, no más) |
| **`SEC-04`** | **El corazón del diseño**: los límites se deciden en el punto de ejecución, bajo el lock, nunca en la visibilidad del formulario |
| **`SUITE-04`** | La suite corre en SQLite y no reproduce los locks: **hace falta un escenario de `purchase:verify-oversell`** |

## 6. Plan de verificación empírica

- **Casos** (`tests/Feature/Reservation/GuestCountTest.php` (futuro)): subir y bajar por las dos
  superficies · el dinero al céntimo y el libro cerrando **después** · los tres suelos con sus tres
  motivos · el techo · el plazo (dentro y fuera) · el token · la ausencia de la clave · la pérdida de
  fichas al bajar · la fiesta con hora extra revalidada con su ventana alargada · el suplemento mixto
  reconciliado.
- **Mutación**: guion propio (`scripts/mutar-invitados-post-form.sh` (futuro)) con, al menos, el lock
  fuera de la primera sentencia, cada uno de los tres suelos, el techo, el plazo, el token, el
  `recordEdit` ausente y el `extra_minutes` fuera de la revalidación.
- **Concurrencia**: un escenario nuevo de `purchase:verify-oversell` —N clientes subiendo a la vez la
  última plaza de la sala— **visto FALLAR** sin la revalidación; y el verificador de interbloqueo de
  §4.2, con las tres transacciones del guardado.
- **Navegador**: el control con sus tres estados (editable · en el techo · fuera de plazo) y el aviso
  de pérdida de fichas.

## 7. Revisión y decisión

Diseño del agente sobre encargo del owner (2026-09-07). `[DECIDIDO owner]`: **subir y bajar** ·
**plazo de un día como mínimo** · **techo el `max_qty` del pack** · **basta con el libro**.

### 7.1 · La revisión adversarial — primera pasada (2026-09-07, sobre el código)

**Cinco hallazgos por lectura. El primero es el bloqueante y hay que REPRODUCIRLO, no razonarlo.**

#### ❌ A1 · El cruce de locks del post-form existe HOY, y esta feature aterriza encima

Leído en el código, no supuesto:

```
MixedPartySurcharge::reconcile()  →  order_items FOR UPDATE … luego recordEdit → orders FOR UPDATE
PostFormAddons::reconcile()       →  orders FOR UPDATE      … luego order_items FOR UPDATE
```

Los dos los dispara **el mismo guardado del post-form**, en ese orden. Con **dos peticiones
simultáneas sobre la MISMA reserva** el ciclo se cierra: la que va por delante sujeta `orders` y pide
el ítem; la de detrás sujeta el ítem y pide `orders`. ⚠️ Es estrecho —`MixedPartySurcharge` solo llega
a `orders` **si el suplemento cambia**— pero no es imposible.

▶ **MEDIDO el 2026-09-07, y el veredicto tiene tres partes:**

1. **La FORMA se interbloquea, y está reproducida sobre InnoDB**: el control negativo que `#413` ya
   dejó escrito —`postform:verify-concurrency --scenario=cross --control`, que hace exactamente
   `order_items → orders` contra `orders → order_items` sobre el mismo pedido— da **4 interbloqueos de
   8 procesos**. Sin `--control`, **0 de 8**. *El instrumento ve el rojo.*
2. **Los dos órdenes están en el código real**, leídos: `MixedPartySurcharge::reconcile()` bloquea el
   ítem como primera sentencia y llega a `orders` por el `recordEdit` anidado;
   `PostFormAddons::reconcile()` bloquea `orders` primero por decisión explícita (D11).
3. ⚠️ **Lo que lo hace ESTRECHO, y hay que decirlo**: `MixedPartySurcharge` solo alcanza `orders`
   **cuando el suplemento se mueve de verdad** (`recordEdit` lanza con delta 0, así que solo se llama
   con un cambio real). Es un reconciliador: en dos guardados seguidos, el segundo no escribe. La
   ventana es «una petición cuyo suplemento cambia» contra «otra que toca extras», sobre la misma
   reserva y a la vez.

▶ **Y la conclusión que importa para ESTA feature: `slots → order_items` NO añade ninguna arista
nueva al grafo.** Las aristas son `slots→items`, `items→orders` y `orders→items`; el único ciclo es
`items→orders→items`, que es **el par preexistente**. El ajuste de cantidad se pone al lado, no
dentro. ▶ El par preexistente queda con **ficha propia en `DEUDA.md`** y no se arregla en esta tanda:
tocar el orden de `MixedPartySurcharge` mueve dinero y merece su propia red.

#### ❌ A2 · El testigo de los extras se rompe si el ajuste se hace en el sitio equivocado

`AuthorizesGuestForm::addonsExpectedVersion()` sustituye lo que el cliente vio por la versión de
DESPUÉS **solo si coincide con la de antes de nuestra propia escritura** (`$before`, capturado al
entrar en `store()`). Su regla es *«nuestra propia escritura no es un tercero»*.

▶ Si el ajuste de cantidad corriera **antes** de esa captura, `$seen !== $before` y **ningún guardado
normal compraría un extra**: el cliente vería «la reserva ha cambiado mientras tenías esta página
abierta» en cada guardado. Es **exactamente el defecto que `#413` §9.4 pagó** con `updated_at`, por
otra puerta. ▶ El orden queda escrito: **capturar `$before` → ajustar la cantidad → `submitGuestForm`
→ extras.**

#### ❌ A3 · La instancia en memoria recortaría las fichas contra la cantidad VIEJA

`submitGuestForm()` sanea con `(int) $this->quantity` de **la instancia que recibe**. Si el ajuste
sube de 10 a 12 y después se llama sobre el mismo objeto sin refrescar, **se guardan 10 fichas y las
dos nuevas se descartan** — el hueco original, reproducido dentro de su propio arreglo. ▶ La reserva
se **re-lee** entre las dos escrituras, y hay caso que lo fija.

#### ❌ A4 · El aviso de «se perderán N fichas» tiene que contar las RELLENAS

Contar filas del array diría «se perderán 5» cuando las cinco están vacías. El aviso cuenta las que
tienen algún dato — que es lo que el cliente reconoce como suyo.

#### ⚠️ A5 · Un docblock que cita un defecto ya arreglado

`PostFormAddons::isWithinWindow()` justifica su aritmética diciendo que `isFinishedInPractice()`
«declara terminada una reserva 1–2 h TARDE». **`#426` (T5) lo arregló**: hoy compara inicio + duración
efectiva en hora del parque. El párrafo se corrige al pasar por ahí — el plazo sigue necesitando su
propia aritmética, pero **por otro motivo**: es otra pregunta, no un reloj torcido.

### 7.2 · Lo que queda antes de escribir código

✅ **A1 medido** (§4.2): la forma se interbloquea, los dos órdenes existen, y el ajuste de cantidad
**no añade arista**. Queda la ficha del par preexistente y el resto del plan de §6.

⬜ **Lo que sigue**: la segunda pasada adversarial sobre §4.6 y §4.7 (las escrituras y los bordes),
y después la T0.


## 8. Lo EJECUTADO (2026-09-07, `#444`)

**El dominio, sus dos superficies y la red entera.** Sin migración: el único dato nuevo es un ajuste
del parque.

### 8.1 · Lo que entra

| Pieza | Qué es |
|---|---|
| `Booking\Services\GuestCountAdjuster` | **la puerta**: lock de zona/día como PRIMERA sentencia, todo re-validado dentro, dinero POST-COMMIT |
| `Booking\Services\GuestCountPolicy` | **la fuente única** de los límites y el plazo, compartida por la pantalla y el escritor |
| `Booking\Contracts\GuestCountChange` | el resultado con su MOTIVO — devuelve, no lanza: el rechazo no puede tumbar el guardado de las fichas |
| `Booking\Contracts\ReservationPlacesTaken` | el contrato por el que Booking pregunta lo que sabe Identity, con `GuardianPlaces` implementándolo |
| `GuestFormController` (web) y `Api\V1\GuestFormController` | la misma secuencia en las dos, y el desenlace que DICE el motivo |
| `GuestFormResource` + `openapi/v1.yaml` | los límites y el corte, ya resueltos, en el contrato |
| Ajustes → Horarios y aforo | `packs.guest_count_cutoff_hours` (vacío = 24 h) |

⚠️ **El binding del contrato vive en `AppServiceProvider` y no en `BookingServiceProvider`**: Booking
no puede nombrar a Identity (`ModuleBoundariesTest`), así que tampoco puede hacerlo su proveedor. La
capa de ENTREGA es el composition root y sí ve a los dos.

### 8.2 · Lo verificado

- **`GuestCountTest`, 18 casos** (el dominio) y **`GuestCountSurfacesTest`, 10** (web + API).
- **`scripts/mutar-invitados-post-form.sh` · 16 mutaciones.**
- **Un escenario NUEVO de concurrencia**, `guest-count`, **visto FALLAR**: doce clientes subiendo +6
  a la vez sobre un cupo de 30 dejaron **96 invitados vivos en la franja** sin la revalidación, y
  **30** con ella. Registrado en el inventario de `OversellVerifierCoversEveryQuotaTest`.

### 8.3 · ❗❗ Lo que enseñó la ejecución

⚠️⚠️ **Un fixture mío era ILEGAL y el rojo no era del servicio.** Un caso de dinero salía en rojo y la
causa estaba antes de tocar nada: el pedido no tenía `paid_at`, y el libro lo lee como «¿se cobró?» —
sin él `online_nac` vale 0 en todas las líneas («sin cobro no hay cobro»), la identidad de CAJA falla
y el pedido sale «en revisión». **Se legaliza el fixture, nunca se excepciona la identidad** (`#311`).

⚠️⚠️ **Dos mutaciones no mordían y eran DÉBILES, no huecos** — y la distinción es la que evita
«arreglar» código sano:

- *«la revalidación deja de excluir la huella propia»*: con la sala vacía, 10 propios + 20 pedidos
  **caben igual** en un cupo de 30, así que excluir o no daba el mismo veredicto. Se añadió una fiesta
  compañera y entonces discrimina (18 + 20 = 38 contra 8 + 20 = 28).
- *«la revalidación olvida los minutos de la hora extra»*: ningún caso tenía una fiesta CON hora
  extra. Hay ahora uno con la geometría que lo hace visible —A de 15:00 a 18:00 con su extensión, B a
  las 17:00— y el crecimiento se rechaza solo por la ventana larga.

⚠️ **Y una tercera lección de instrumento, barata pero real**: el arnés llevaba un nombre de mutación
con acentos graves y **bash lo ejecutó como comando**. El veredicto seguía siendo bueno, pero el
rótulo salía mutilado: en un guion que se lee para decidir, eso es ruido en la señal.

⚠️ **Tres guardas del repo cazaron trabajo mío**: la acción de auditoría sin catalogar
(`AuditActionCatalogTest`), el contrato `additionalProperties: false` y `ApiContractTest` exigiendo
que `guest_count` declarara **por qué** es opcional.

### 8.4 · Lo que queda

- El **OJO del owner** en navegador: el control con sus tres estados y el aviso de pérdida de fichas.
- El aviso de «se perderán N fichas» **antes** de guardar: el dato ya viaja (`discardedForms`) y la
  pantalla aún no lo pinta.
- **Un paso de despliegue, y es de DATO**: nada más. El plazo cae a 24 h sin configurar.
