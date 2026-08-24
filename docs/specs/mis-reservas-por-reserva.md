# [SPEC] «Mis reservas» pasa a listarse POR RESERVA, y el pasado se va a su propia pantalla

> Estado: **✅ EJECUTADA** · Última actualización: 2026-08-23 · Decisión asociada: `DECISIONES #126`.
> Encargo del owner (2026-08-23) con sus decisiones tomadas — ver §7.
>
> ⚠️⚠️ **Lo que la EJECUCIÓN cambió respecto a este diseño, y es a mejor**: §3.4 proponía agrupar por
> DÍA y aceptar una discrepancia de 24 h. Al escribirlo se vio que el corte fino de
> `isFinishedInPractice()` **sí es expresable en SQL** comparando dos columnas (`slots.date` y
> `slots.end_time`) en vez de concatenarlas —que es lo que lo habría atado al dialecto—, así que el
> reparto es **EXACTO** y no hay discrepancia ninguna que aceptar. El texto de §3.4 está corregido.

## 1. Contexto y problema

Hoy la zona `ORDERS` del cajón lista **PEDIDOS**. Un pedido puede contener varias reservas de fechas
distintas, y para el cliente eso no es una unidad: es un detalle contable.

**Medido en la BD de desarrollo** (`cliente.demo@jumpweb.test`, 2026-08-23):

    PEDIDO DEMO-LEDGER  paid  creado 22/08 17:25
       Cumpleaños Jump   23/08  finished
       Jump · 1 hora     23/08  finished  CANCELADA
       Jump · 1 hora     23/08  finished
    PEDIDO DEMO-0001    paid  creado 22/08 13:39
       Cumpleaños Jump   26/08  active

`GET /me/orders` ordena con `->latest()`, es decir por **fecha de creación del pedido**. Consecuencia
medida: el cliente ve **tres reservas ya pasadas por encima de la única que aún no ha llegado**, y una
de ellas cancelada, sin ninguna distinción visual respecto a las vivas.

**Tres cosas que la pantalla ya hace bien y no hay que reinventar:**

- **La paginación EXISTE.** `OrdersZone.vue` pinta `<nav class="pagination">` y `stores/orders.js`
  llama a `GET /me/orders?page=N`; el tamaño es el que fija la API (**10**). En la verificación de
  `#125` salió «sin paginación» solo porque el cliente demo tiene 2 pedidos.
- **Los datos por reserva YA VIAJAN.** `OrderItemResource` publica `date`, `date_label`,
  `time_window`, `start_time`, `status`, `cancelled`, `charged_subtotal_cents`, `paid_online_cents`,
  `gate_remainder_cents`, `shows_deposit_note`, `guest_form_*`, `is_pack` y `addons`.
- **El distintivo cancelada/disfrutada ya se compone** en `account/orders.js::lineBadgeOf()`, con la
  regla correcta: **cancelada gana** sobre finalizada (una reserva cancelada cuya franja ya pasó
  cumple las dos, y anunciarla como «disfrutada» diría algo falso).

## 2. Objetivo

**Criterios de éxito, medibles:**

1. La zona lista **una tarjeta por reserva** (ítem principal), con la referencia de su pedido.
2. «Mis reservas» enseña **solo las VIVAS**, de la más próxima a la más lejana. Las que no tienen
   fecha asignada van **arriba del todo**.
3. Las **terminadas** —canceladas y disfrutadas— **no se mezclan**: viven en su propia pantalla, a la
   que se llega por un CTA **«Ver historial de reservas»** al final de la lista, tras la paginación.
   Allí salen **de la más reciente a la más antigua** y **atenuadas**.
4. Las dos pantallas paginan a **5 tarjetas**, y el orden es correcto **a través de las páginas**.
5. **Ninguna reserva puede caerse entre las dos pantallas ni salir en las dos.** Es el criterio que
   gobierna el diseño (§3.4) y tiene guarda ejecutable propia (§6.1).
6. Nada de lo que hoy se puede hacer se pierde: el **ledger** completo, el **reintento de pago** de un
   pedido a medio pagar, el **post-form** y las **respuestas del pack bajo demanda**.

**Fuera de alcance, explícitamente:**

- Cambiar `GET /me/orders`. Sigue existiendo con su contrato actual: es historial **contable**, y un
  cliente de API que sincroniza pedidos lo necesita tal cual.
- Cambiar `GET /me/reservations` (las próximas, sin paginar, cuatro campos): lo consume el bloque
  `.acct` y el índice.
- Rediseño visual de la tarjeta más allá de la atenuación y de plegar el ledger.
- La decisión aplazada de `area-cliente.md` §3.4 (si la zona cambia la URL). Sigue aplazada.

## 3. Opciones consideradas

### 3.1 ¿Dónde se ordena y se pagina?

| Opción | Por qué |
|---|---|
| **(a) Aplanar en el CLIENTE** lo que trae `GET /me/orders` | Barato: no toca servidor ni contrato. **DESCARTADA**, y el motivo es medible: el servidor pagina **10 PEDIDOS por fecha de creación**, así que aplanando en el navegador el orden solo es cierto *dentro* de la página. Con 30 pedidos, la página 1 son las reservas de los 10 más recientemente creados ordenadas entre sí — que no es «los que van antes primero». Sería una pantalla que **parece** ordenada. |
| **(b) Endpoint que pagina POR RESERVA** ✅ | Lo único que hace verdadero el criterio 4. Aditivo: no rompe a ningún cliente. **ELEGIDA** (owner, 2026-08-23). |
| (c) Reformar `GET /me/orders` para que devuelva reservas | Rompe el contrato publicado y el historial contable, que es otra cosa. Descartada. |

### 3.2 ¿Una lista mezclada o dos pantallas? — **[DECIDIDO] owner, 2026-08-23**

| Opción | Por qué |
|---|---|
| (a) Una lista con las terminadas atenuadas al final | Era la propuesta inicial. **Descartada por el owner**: el historial de un cliente veterano empuja lo vivo fuera de la primera página y la pantalla deja de responder a «¿qué tengo?». |
| **(b) «Mis reservas» = solo vivas · «Historial» = pantalla aparte, tras un CTA** ✅ | **ELEGIDA.** La pantalla principal responde a una sola pregunta. Y tiene un efecto de diseño que no es menor: **disuelve la tensión de §3.4**, porque deja de hacer falta ordenar una lista mezclada por un predicado que se calcula en dos sitios. |

### 3.3 ¿Dónde va el dinero del pedido? — **[DECIDIDO] owner, 2026-08-23**

El ledger (subtotal, señal, a cobrar en puerta, devuelto, total final), el estado del pedido y el
**reintento** son del PEDIDO, no de la reserva.

| Opción | Por qué |
|---|---|
| **(a) En la tarjeta, plegado tras «Ver pedido»** ✅ | Cada tarjeta enseña su propio importe y la referencia; el ledger completo y el reintento aparecen al desplegar. No se pierde nada y no se repite en pantalla. **ELEGIDA.** ⚠️ **SUPERADA el 2026-08-24** (`DECISIONES #129`, decisión del owner en `desglose-dinero-cliente.md` §5·2): el ledger **se muda a «Mis pedidos»**, su propia pantalla, y «Ver pedido» LLEVA allí en vez de desplegar. El motivo es el que esta misma tabla anticipaba en la opción (b) y que la opción (a) solo aplazaba: el desglose es del PEDIDO, y plegarlo dentro de cada reserva lo repetía igual —una vez por tarjeta— con importes que no cuadraban con lo que las rodeaba. |
| (b) Completo en cada tarjeta | Un pedido con 3 reservas imprime el mismo «Total» tres veces, y los importes no cuadran con la tarjeta que los rodea. Descartada. |
| (c) Agrupar por pedido, ordenar por su reserva más próxima | No resuelve el caso del encargo: un pedido con reservas en fechas lejanas sigue saliendo como un bloque. Descartada. |

### 3.4 ⚠️⚠️ El riesgo REAL del diseño elegido: que una reserva se caiga entre las dos pantallas

Partir la pantalla en dos **quita** un problema y **pone** otro, y el que pone es peor si no se ve
venir. Con una lista mezclada, un predicado mal calculado producía un **mal orden**. Con dos
pantallas alimentadas por **dos consultas independientes**, un predicado mal calculado produce una
reserva que **no aparece en ninguna de las dos** — y eso no lo nota nadie, porque una lista sin una
fila se lee perfectamente.

**De dónde saldría exactamente.** `OrderItem::isFinishedInPractice()` hace el corte fino **en PHP**:
depende de la hora de fin y de la zona horaria, y la fecha en BD es naive `Y-m-d`. Si «vivas» filtrara
con el corte fino (PHP) y «pasadas» con `date < hoy` (SQL), **una reserva de HOY que ya terminó**
quedaría fuera de las dos: el PHP la descarta de vivas y el SQL la descarta de pasadas.

▶ **Elegido: UN SOLO predicado, definido UNA vez en el dominio, y las dos pantallas son sus dos
lados.** No dos filtros que «casualmente» se complementan:

    terminada  ⟺  ítem cancelado
               ∨  pedido cancelado / reembolsado / caducado de HECHO
               ∨  hay hora de fin y ya pasó

`vivas = NOT terminada` (incluye las que no tienen franja). Es una **partición exhaustiva y disjunta
por construcción**, expresable entera en SQL, así que las dos listas se paginan y se ordenan en la BD.

⚠️⚠️ **Y el corte de «disfrutada» resultó ser EXACTO, no por día.** El diseño daba por hecho que
había que renunciar a la precisión de `isFinishedInPractice()` —que compone fecha + hora de fin y
mira si ya pasó— porque concatenar fecha y hora en SQL ata la consulta al dialecto (la suite corre en
SQLite y producción en MySQL). **Comparando DOS COLUMNAS no hace falta concatenar**:
`slots.date < hoy OR (slots.date = hoy AND slots.end_time < ahora)`. Portable, exacto y sin
discrepancia que documentar.
▶ **Y eso importa más de lo que parece**: con precisión de día, esta partición y `upcomingFor()` —que
alimenta el bloque de cuenta— habrían **discrepado**, y el panel habría dicho «1 reserva próxima» de
algo que la pantalla enseña en el historial. Hay caso que lo fija.

⚠️ **La alternativa —hacer el corte en PHP— exige materializar el histórico entero en cada petición
para poder paginarlo**, que es exactamente el coste que
`CustomerReservationsReader::pendingGuestFormsFor()` acaba de dejar de pagar (2026-08-23, `#123`).

⚠️⚠️ **Y la NULL-safety no es pulcritud: sin ella se pierde una fila.** `whereNot()` sobre un
predicado que compara una columna NULLable devuelve *unknown*, no *false*, y `NOT unknown` sigue
siendo *unknown*: la reserva sin franja se cae de los DOS lados. Medido en la ejecución: la partición
pasa de 9 a 8 y **nada más falla**.
▶ Y una lección que costó tres mutaciones: la primera versión guardaba con `whereNotNull` **las dos**
columnas del slot. Como en el esquema las dos son `NOT NULL` y solo valen NULL a la vez —cuando el
`leftJoin` no encuentra franja—, **cada una tapaba a la otra y ninguna se podía medir mutándola**.
Es `DECISIONES #112` otra vez: una guarda con dos fuentes redundantes no se puede medir mutando una
sola. Se conserva UNA.

### 3.5 ¿Y las reservas sin franja? — **[DECIDIDO] owner, 2026-08-23**

**Arriba del todo, en «Mis reservas».** Un ítem sin `slot_id` no tiene fecha por la que ordenar, pero
tampoco está terminado —`isFinishedInPractice()` devuelve `false` sin franja, y el dominio ya lo trata
así: un pack sin franja **sigue avisando** de su post-form— y normalmente es lo que **espera algo del
cliente**. Va antes que las fechadas, no después.

⚠️ **Caso frontera que hay que cubrir**: un ítem sin franja cuyo PEDIDO está cancelado **sí** es
terminado, y va al historial. La ausencia de fecha no lo salva.

## 4. Diseño elegido

### 4.1 El contrato de dominio (Booking)

`Booking\Contracts\CustomerReservations` gana **un método con un ámbito**, no dos métodos y no un
contrato nuevo: el sujeto es el mismo, y partirlo dejaría dos definiciones de «reserva de un cliente»
destinadas a divergir — que es justo lo que §5.1 de `modulos-dominio.md` evitó al extraerlo. **Y es lo
que garantiza §3.4**: un método con dos ramas sobre el MISMO predicado no puede perder una fila; dos
métodos con dos consultas, sí.

    /**
     * Historial de reservas del cliente, paginado y ordenado PARA PRESENTACIÓN.
     * Los dos ámbitos son los dos lados del mismo predicado: su unión es el total y no se solapan.
     */
    public function pageFor(int $userId, ReservationScope $scope, int $perPage, int $page): LengthAwarePaginator;   // (futuro)

`Booking\Contracts\ReservationScope` **(futuro)**: enum `UPCOMING` | `PAST`.
Lo implementa `Booking\Services\CustomerReservationsReader::pageFor()` **(futuro)**, con la expresión
de §3.4 escrita **una vez** y aplicada con `where(...)` / `whereNot(...)`.

**Orden**, en SQL:

| Ámbito | Orden |
|---|---|
| `UPCOMING` | sin franja primero · luego `slots.date` ASC, `slots.start_time` ASC |
| `PAST` | `slots.date` DESC, `slots.start_time` DESC · sin franja al final |

⚠️ **Devuelve el paginador de Eloquent y no un DTO propio**, al revés que `upcomingFor()`. Aquí la
capa de entrega necesita el ítem entero —`OrderItemResource` ya sabe serializarlo con sus importes, su
post-form y sus complementos— y componer un DTO con veinte campos sería copiar ese Resource a mano.
`upcomingFor()` publica un DTO porque publica **cuatro** campos.

### 4.2 El endpoint

`GET /api/v1/me/reservations/{scope}`, con `scope` ∈ `upcoming` | `past`, en
`MeReservationsController::page()`. Dos URLs honestas —cada pantalla pide la suya— y **un solo
manejador sobre un solo predicado**.

- `per_page` por defecto **5**, techo 50 — mismo criterio que `MeOrdersController`: la pantalla del
  cajón pide 5, y un cliente que sincroniza necesita otro orden de magnitud.
- Recurso: `ReservationCardResource`, que **anida** a `OrderItemResource` y le añade el contexto
  mínimo del pedido: `code`, `status`, `created_label`, `can_be_retried`.

⚠️ **SUPERADO el 2026-08-24**: el ledger ya no se pide al desplegar porque **ya no se despliega aquí**
— vive en «Mis pedidos» (`ZONES.PURCHASES`), que lo recibe con su lista. Lo que sigue vigente de este
párrafo es su razón, que es la que acabó mudando la pantalla entera. `stores/orders.js::ensureOrder()`
se retiró con el cambio (`DECISIONES #129`).

⚠️⚠️ **El LEDGER no viaja con la tarjeta, y esa decisión salió al implementar.** El diseño lo metía
dentro; medirlo contra el caso real lo desmontó: `DEMO-LEDGER` tiene **tres** reservas, así que el
desglose viajaría tres veces con importes que no cuadran con la tarjeta que los rodea. Se pide al
desplegar «Ver pedido» con **`GET /orders/{code}`, que YA EXISTÍA** —acotado por `user_id` y
devolviendo el ledger entero—, exactamente como las respuestas del pack.
▶ Y el efecto que vale más que el ahorro: **no nace una segunda superficie de dinero**. El cliente
reutiliza `account/orders.js::financialsOf()` y `orderRow()` tal cual.

⚠️ **`is_terminated` se descartó**, y el diseño lo pedía. Sería un eco del ámbito que el cliente acaba
de pedir —no puede decir nada nuevo— o una segunda definición del predicado que reparte las dos
pantallas. La atenuación es propiedad de la PANTALLA; el distintivo por tarjeta ya sale de `cancelled`
y `status`, que sí son de la reserva.

⚠️ **La tarjeta va ANIDADA (`{reservation, order}`) y no aplanada, y lo decidió el CONTRATO**: el
esquema `OrderItem` declara `additionalProperties: false`, así que un `allOf` que le añadiera campos al
lado sería inválido y aplanarlo obligaría a copiar sus veinte propiedades en un esquema nuevo.

⚠️ **`is_terminated` lo publica el SERVIDOR y no se recompone en el cliente** (`CE-4`). Es la misma
regla que ya obliga a que `can_be_retried`, `gate_remainder_cents`, `shows_deposit_note` y
`guest_form_url` lleguen resueltos: una quinta superficie que decida «esto está terminado» por su
cuenta es una divergencia esperando su turno. Y aquí importa el doble, porque **es la clave del
reparto**: si el cliente lo recalculara, podría atenuar una tarjeta que el servidor puso en vivas.

⚠️ **RGPD**: las respuestas del evento **NO viajan**, igual que en `/me/orders`. Se siguen pidiendo con
`GET /orders/{code}/event-data` cuando el titular las despliega (art. 9).

### 4.3 La zona nueva, y la guarda que hay que cambiar de forma

El historial es **otra zona** (`ZONES.ORDERS_HISTORY` **(futuro)**): es el nivel de navegación del
área, trae su «volver» por la pila y añadirla es una línea en `ZONES` más su rótulo.

⚠️⚠️ **Pero rompe una guarda, y hay que cambiarla de FORMA, no relajarla.** `navigation.test.js`
exige hoy que **toda zona esté en `HOME_ENTRIES` o en `GUEST_ZONES`** — el índice o una puerta de
invitado—, porque una zona que no está en ninguna lista es código muerto al que ningún cliente llega
(la familia de `#113` y `#117`). El historial **no va en el índice**: se llega a él desde «Mis
reservas». Esa regla ya cambió de forma una vez, el 2026-08-23, al pasar de una puerta a dos, y su
comentario dice cómo hacerlo bien: **declarar la tercera puerta, no escribir una excepción a mano**.

▶ Nace `ZONE_PARENTS` **(futuro)**: mapa `zona → zona que la ofrece`. La guarda pasa a ser «toda zona
está en `HOME_ENTRIES`, en `GUEST_ZONES`, o declarada en `ZONE_PARENTS` colgando de una que sí lo
está» — con su caso de que no haya ciclos ni padres huérfanos.

### 4.4 El cliente

⚠️ **Ni store nuevo ni módulo nuevo, y también salió al implementar.** El diseño proponía
`stores/reservationCards.js` y `account/reservation-cards.js`; los dos habrían sido un segundo sitio
que sabe de pedidos, reintentos y respuestas del pack. Lo que se hizo:

- **`stores/orders.js` se repurpone**: guarda `pages` **por ámbito** —un store con los dos dentro, para
  que las dos pantallas no puedan tener versiones distintas de la misma reserva— y gana
  `ensureOrder(code)` para el ledger bajo demanda. `retry()` y `ensureEventData()` no se tocan.
- **`account/orders.js` gana `cardRow()`/`cardRows()`**, que **reutilizan `lineRow()` entera**: una
  reserva suelta y una reserva dentro de su pedido son la misma cosa, y escribir una segunda
  composición habría creado dos sitios donde arreglar el mismo fallo.
- **`OrdersZone.vue` sirve LAS DOS pantallas con un `scope` por prop.** No hay `OrdersHistoryZone.vue`:
  lo único que las distingue es qué ámbito piden, si atenúan y si ofrecen el CTA — tres props.
- **`ReservationCard.vue`** es la tarjeta que las dos comparten.

⚠️ **Ninguna regla nueva entra en el componente**: `SidebarComponentBudgetTest` pone el techo en **40
líneas de código por `.vue`** y `OrdersZone` ya lo roza. Si aprieta, la pregunta es qué sobra ahí — es
lo que ya provocó el rediseño correcto en `#120(r)`. La tarjeta es un componente compartido por las
dos zonas, no una copia.

### 4.5 El presupuesto

⚠️⚠️ El chunk estaba en **211,02 de 212 KiB** tras `#125`. **Medido tras ejecutar: 213,57 KiB, +2,55**,
y el techo sube a **214,5** con su párrafo en el ledger.
▶ Y conviene decir de dónde NO salió ese coste, porque el diseño auguraba bastante más: el historial
**no trajo componente propio**, el ledger **no se duplicó** —se reutiliza `orderRow()` sobre un
endpoint que ya existía— y la composición de la reserva **es la misma `lineRow()`**. Una segunda zona
con su tarjeta y su ledger habría costado el doble.

## 5. Impacto en invariantes

- **RGPD-04** (`no-store` en respuestas autenticadas de `/api/v1`): lo aplica el middleware por
  defecto; el endpoint nuevo lo hereda. **Hay que aseverarlo**, no darlo por hecho.
- **SEC**: el `userId` sale del guard, nunca de la petición — sin superficie de IDOR, igual que sus
  hermanos. Se fija con un caso de otro titular.
- **PERF**: las dos consultas son **dirigidas y paginadas en SQL**. El riesgo que esta spec introduce
  es el N+1 al serializar (tipo, franja, complementos, pagos y reembolsos del pedido); se cubre con un
  presupuesto de consultas, como `ApiOverheadTest`.
- **PAY**: ninguno se toca. El reintento sigue siendo `POST /api/v1/orders/{code}/payment`, que entra
  por el `CRITICAL_RE` del `pre-push`. ⚠️ **No se puede perder ese camino**: `openapi/v1.yaml` dice que
  `/me/orders` incluye los `pending` a propósito, porque es la única forma de que un cliente recupere
  un pedido a medio pagar con su retención de aforo viva. Un pedido `pending` **no es terminado**, así
  que su reserva sale en «Mis reservas» con su CTA de pago — hay caso que lo fija.

## 6. Plan de verificación empírica

### 6.1 La guarda que hace real el criterio 5

`Api\V1\MeReservationScopeTest` — sobre un fixture con **los siete casos** (viva próxima, viva lejana,
viva de hoy ya terminada, sin franja, cancelada, disfrutada, y una de pedido caducado):

    upcoming.total + past.total === total de ítems principales del titular
    ninguna id aparece en los dos ámbitos

⚠️ **Es el caso más importante de esta spec** y hay que **verificarlo por mutación**: al cambiar el
predicado de uno de los dos lados sin tocar el otro, tiene que ponerse rojo. Una guarda que solo
comprobara «vivas trae 3» no vería la fila perdida.

### 6.2 El resto

1. `Api\V1\MeReservationsPageTest` — el orden completo de cada ámbito **a través de DOS páginas**: es
   lo único que distingue esta solución de aplanar en el cliente.
2. El mismo test: 401 sin sesión · reservas de otro titular no aparecen · `no-store` en la respuesta ·
   las respuestas del evento **no** viajan · un pedido `pending` sale en vivas con `can_be_retried`.
3. Presupuesto de consultas de los dos ámbitos, para que el N+1 no entre en silencio.
4. `reservation-cards.test.js` (`node --test`) — composición de filas y atenuación.
5. `navigation.test.js` — la guarda de alcanzabilidad **en su forma nueva**, con `ZONE_PARENTS`, más
   los casos de padre huérfano y de ciclo.
6. `SidebarStyleWiringTest` cubre por construcción que toda clase nueva tenga regla.
7. Contrato: la entrada en `openapi/v1.yaml` **manda sobre el código**, y `ApiContractTest` la verifica
   con Spectator.

### 6.3 Navegador

Sección nueva en `VERIFICACION-E2E-CAJON.md`: con un titular sembrado con **más de cinco vivas y más de
cinco terminadas**, comprobar el orden a través de la paginación en las dos pantallas, la atenuación,
el CTA y su «volver», el ledger plegado y que el reintento sigue alcanzable.
⚠️ El fixture de `cliente.demo@` tiene **4 reservas**: no basta y hay que sembrar más.

## 7. Revisión y decisión

**Encargo del owner (2026-08-23)**, decisiones tomadas por él:

1. Listar **por reserva individual**, no por pedido, con la referencia del pedido.
2. El ledger y el reintento, **en la tarjeta, plegados tras «Ver pedido»**.
3. Ordenar y paginar **en el servidor**.
4. **5** tarjetas por página.
5. Las terminadas **fuera de la lista principal**, tras un CTA «Ver historial de reservas», y allí **de
   la más reciente a la más antigua**.
6. Las reservas **sin franja, arriba del todo**.

**Lo que esta spec añade y hay que revisar antes de implementar:**

- **§3.4** — el riesgo que introduce partir la pantalla en dos, y que el reparto sea **un solo
  predicado con dos lados** en vez de dos consultas independientes. Es la parte del diseño que puede
  perder datos en silencio.
- **§4.3** — la guarda de alcanzabilidad de zonas hay que **cambiarla de forma** (tercera puerta
  declarada), no relajarla con una excepción.
- **§4.5** — esto revienta el presupuesto del chunk, que quedó en 0,98 KiB.
