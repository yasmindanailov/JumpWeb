# [SPEC] Cumpleaños **MIXTO** — cuando la edad declarada no cuadra con el pack reservado

> Estado: 🟦 **CINCO TANDAS EN EL ÁRBOL** (2026-08-29, `DECISIONES #243`→`#247`) · ⏸️ **el descuento
> del caso barato queda APARCADO con su diseño escrito y verificado** (§16, `#248`): la conexión entre
> productos, el veredicto derivado, la puerta del catálogo, el suplemento —que se COBRA y se
> recalcula solo—, la etiqueta pegada al nombre y el **caso barato**. El owner ya lo ha visto en
> navegador (la etiqueta sale para admin y cliente); sigue 🟦 por lo declarado fuera (§12.7) ·
> Última actualización: **2026-08-29**.
>
> ▶ ❗❗ **EMPIEZA POR §14**, que es lo último y nace de lo que el owner encontró probándolo: **la
> dirección BARATA no decía nada**. Luego §12 —que CORRIGE la forma de §9: no hay aprobación del
> operador, el importe sigue a las edades— y §13, dónde vive la etiqueta y qué cuesta que todas las
> superficies la cojan del nombre. §11 dice qué se construyó primero; §8 y §9, lo medido y la forma. El
> cuerpo (§1–§7) sigue siendo válido como encargo, pero tres de sus premisas se midieron y dos no se
> sostienen. **Lee la corrección antes que el texto que corrige.**
>
> ⚠️⚠️ **Lo más caro de todo el documento está en §8.3, y está MEDIDO sobre un pedido real**: la
> forma «obvia» de cobrar el suplemento —un ajuste `extra_due`— **no cobra nada**. Mueve 6,00 € de
> «pagado online» a «a cobrar en el parque» y deja el valor del pedido intacto. La forma que sí cobra
> es una **LÍNEA** (§8.4), y también está medida.
>
> ⚠️ **Y §8.2 tumba el atajo**: cambiar el producto de la reserva (KIDS → JUMP), que es una operación
> que el panel ya sabe hacer, **cobra la diferencia por TODOS los invitados**, no por el niño mayor.
> Con 10 niños y 3,00 € de diferencia son 30,00 € en vez de 3,00 €.
>
> ❗ **Esto NO está diseñado todavía.** El owner lo describió para que quedara escrito y para tenerlo
> en cuenta al tocar el embudo; se iterará después. Lo que hay aquí es **el encargo tal cual**, **lo
> que se ha medido del código de hoy** y **las preguntas que hay que contestarle antes de escribir una
> línea** — varias de ellas tocan dinero y aforo, que no se deciden en una sesión de implementación.

---

## 1. El encargo, en las palabras del owner (2026-08-28)

> «Un usuario reserva un cumpleaños KIDS o JUMP; KIDS o JUMP está condicionado por la hora que tiene
> configurada cada zona. PERO si reserva un cumpleaños **KIDS**, que es de niños de 1 a 6 años por
> ejemplo, luego le pedimos un formulario post-reserva y ahí pedimos la edad de todos los niños. Si
> pone un niño **mayor de 6 años**, ya no es un cumple KIDS: sería **cumple MIXTO**, y ese niño deberá
> abonar la **diferencia** entre el precio de cumple KIDS y JUMP **por persona**.
>
> El operador tiene que **visualizar eso desde el panel**, que se vea en el **desglose** para el
> operador; y para el cliente, de manera **transparente y fácil de entender**. Y el título recibe la
> etiqueta **«MIXTA»** al lado, tanto para el cliente como para el operador.»

---

## 2. Lo MEDIDO del código de hoy (2026-08-28), que cambia el punto de partida

⚠️⚠️ **Tres cosas que el encargo da por hechas y que HOY NO EXISTEN.** Ninguna es un impedimento;
son trabajo que hay que contar antes de estimar nada.

1. **No se pide la edad de los niños.** El post-formulario es data-driven por pack
   (`ticket_types.guest_fields`), y los cuatro campos que trae el producto son
   **`name · allergy · notes · special_menu`** (`TicketType::DEFAULT_GUEST_FIELDS`). Medido sobre los
   dos packs de la instalación local: `["name","allergy","notes","special_menu"]`. **No hay campo de
   edad**, y el que hiciera falta no puede ser texto libre: de él sale un cobro.
2. **Los dos packs cuestan lo MISMO en el producto**: «Cumpleaños Jump» y «Cumpleaños Kids», los dos a
   **15,00 € normal / 18,00 € especial**. La diferencia de precio es un dato del parque real, no del
   producto — así que la regla tiene que **derivar la diferencia de los precios resueltos de los dos
   productos para ESE día**, nunca de un número escrito en el código. Si un cliente los pone iguales,
   «mixto» sigue siendo una etiqueta pero el cargo es cero, y eso tiene que ser un estado válido.
3. ▶ **Ampliado y matizado en §8.1 y §8.7**: la zona no condiciona la etiqueta, condiciona **si la
   operación existe** — y la «hora por zona» del encargo son en realidad dos mecanismos distintos que
   no componen como dice la frase. · **Los dos packs comparten ZONA** (`zone_id = 4`, «Cumpleaños»), con las mismas franjas de 10:00 a
   21:00 y aforo 200. ⚠️ **Eso contradice la premisa «KIDS o JUMP está condicionado por la hora que
   tiene configurada cada zona»** en la instalación que tenemos delante. Antes de diseñar hay que
   comprobarlo contra la instalación **real** del cliente: si allí son zonas distintas con ventanas
   distintas, un cumpleaños que pasa a mixto puede estar cambiando de **aforo**, y entonces esto deja
   de ser una etiqueta y un cargo para tocar `AFORO-01`/`AFORO-02`.

---

## 3. Por qué esto no es «una etiqueta y un recargo»

El momento en que se declara la edad es **posterior al pago**. El pedido ya está cobrado (total o
señal), así que el cargo por diferencia nace **fuera** de la secuencia de compra que `PAY-04` ordena.
Eso lo emparenta con dos cosas que el sistema ya sabe hacer, y hay que elegir cuál:

- el **resto de la señal**, que se cobra en el parque y ya tiene su línea en el desglose del cliente
  (`specs/desglose-dinero-cliente.md`);
- el **cargo por cambios** que aplica el operador desde el panel, que también tiene su línea y su
  motivo.

▶ **La respuesta a esto la decide el owner, no el implementador** (§5·3): es dinero.

---

## 4. Lo que tendría que existir, en bloques (aún sin diseñar)

| Bloque | De qué va | A qué toca |
|---|---|---|
| **A · La edad como DATO** | un campo de edad tipado en `guest_fields`, obligatorio en los packs que lo usen | `POSTFORM-INVITADOS.md` |
| **B · El umbral por pack** | «este pack es para 1–6 años»: configurable, no quemado | panel · `MODELO-DATOS.md` |
| **C · El veredicto** | derivar «mixto» comparando cada edad con el umbral, en el SERVIDOR | dominio nuevo |
| **D · El dinero** | la diferencia por persona, derivada de los dos productos para ESE día | **`INVARIANTES §1 (PAY)`** |
| **E · Lo que ve el cliente** | la etiqueta «MIXTA» y una línea de desglose que se entienda sola | `desglose-dinero-cliente.md` |
| **F · Lo que ve el operador** | la etiqueta en la ficha del pedido y en el calendario, y el desglose | `PANEL-ADMIN.md` |

⚠️ **El veredicto se calcula en el servidor y punto.** Es la condición de un cobro: un cliente que
edite su post-formulario no puede decidir si su fiesta es mixta desde el navegador (`CE-4`).

⚠️ **El post-formulario es EDITABLE hasta el día del evento** (`POSTFORM-INVITADOS.md` §6). O sea que
el veredicto **puede ir y venir**: se rellena con un niño de 8 años (mixto), se corrige a 6 (no
mixto), se vuelve a subir. Cualquier diseño que trate «mixto» como un sello irreversible se romperá
con el primer cliente que corrija una edad — y si el cargo ya se emitió, deshacerlo es un movimiento
de dinero, no un `update`.

---

## 5. ❗ Lo que hay que preguntarle al owner ANTES de diseñar

> ▶ **Estas seis preguntas siguen siendo las buenas, pero se plantean mal: sin número y sin coste.**
> La versión con la que se le pregunta es **§10**, que las reduce a cuatro y le pone a cada salida lo
> que cuesta. Las dos que faltan (reversibilidad y adultos) las contesta la forma de §9 y quedan
> como supuestos declarados, no como preguntas.

1. **La zona**: en el parque real, ¿KIDS y JUMP son zonas distintas con ventanas distintas, o la misma
   con horas distintas? De esto depende que «mixto» toque aforo o no (§2·3).
2. **El umbral**: ¿la edad tope es del PACK (un número configurable por producto) o de la zona?
   ¿Y qué pasa exactamente en el borde —«mayor de 6» es 7, o los 6 recién cumplidos ya no entran—?
3. **El cobro** (§3): ¿la diferencia se cobra **en el parque** como el resto de la señal, **online**
   con un segundo pago, o la aplica el **operador** a mano desde el panel? Son tres caminos con
   riesgos distintos y no se puede elegir por defecto.
4. **La reversibilidad**: si el cliente corrige la edad y la fiesta deja de ser mixta, ¿se retira el
   cargo automáticamente, o el operador decide?
5. **Los adultos**: el post-formulario ya pide un nº aproximado de adultos. ¿Cuentan para esto?
6. **La etiqueta**: «MIXTA» ¿es solo un rótulo, o cambia también el NOMBRE del producto en la hoja de
   reserva, el correo y el PDF?

---

## 6. Invariantes que esto va a tocar

- **`PAY`** — un cargo nuevo después de un pedido pagado. Nada de esto se toca sin leer
  `INVARIANTES.md` §1 entero, y el núcleo del cobro entra en el `CRITICAL_RE` del `pre-push`.
- **`AFORO`** — solo si §5·1 responde «zonas distintas». Entonces cambiar la naturaleza de una fiesta
  ya reservada es una edición de aforo, con todo lo que `AFORO-01`/`AFORO-05` exigen.
- **RGPD** — la edad de un menor es un dato personal de un tercero. Ya hay precedente y doctrina en
  `specs/menores-a-cargo.md`: mirar qué se guarda, cuánto y quién lo ve.

---

## 7. Cuándo

Después de la tanda del **cajón en móvil** (`specs/cajon-en-movil.md`) y de **«Crear pedido» en
tablet** (`specs/panel-navegacion.md` §6·U7), que son los dos encargos en curso. ▶ **El primer paso
de esta spec no es código: son las seis respuestas de §5.**

---

## 8. ❗❗ Lo MEDIDO el 2026-08-29 (carril A) — y va antes que el cuerpo

> Todo lo de aquí abajo se midió ejecutando código contra la BD de desarrollo, no leyéndolo. Las dos
> sondas de dinero corrieron dentro de una transacción **revertida**: el árbol y los datos quedaron
> como estaban.

### 8.1 · La operación «reserva X → reserva Y» YA EXISTE, y su frontera es la ZONA

El panel sabe cambiar el producto de una reserva **ya pagada**: `OrderItemEditor::edit()` re-tarifica
con `RateResolver` en la fecha efectiva (`PAY-18`), escribe el `unit_price` nuevo y deja la diferencia
como cargo de puerta. Su validación pura (`OrderItemEditor::validateItemEditTarget`) rechaza
exactamente dos cambios de naturaleza:

- `cross_type_change_forbidden` — entrada ↔ pack;
- **`cross_zone_change_forbidden_product`** — y el texto que lee el operador lo dice sin rodeos:
  «solo puedes cambiar a un producto de la misma zona; para cambiar de zona usa un pedido manual».

**Medido ejecutando el validador** sobre la instalación local: `Cumpleaños Kids → Cumpleaños Jump`
devuelve `null` (permitido, hoy, en producción) y `Cumpleaños Kids → Jump · 1 hora` devuelve
`cross_type_change_forbidden`.

▶ **Conclusión que reencuadra el encargo: la zona no es el condicionador de la ETIQUETA, es el
condicionador de la OPERACIÓN.** Con los dos cumpleaños en la misma zona, «convertir» una reserva ya
existe; en zonas distintas el producto lo prohíbe a propósito y te manda a un pedido manual.

### 8.2 · ⚠️⚠️ Pero el cambio de producto NO es lo que pide el encargo

El owner pide que **ese niño** abone la diferencia. Cambiar el producto de la reserva cobra la
diferencia **× todos los invitados**: con 10 niños, uno mayor de 6 y una diferencia de 3,00 €, el
encargo son **3,00 €** y el cambio de producto **30,00 €**.

▶ Así que «mixto» **no es un cambio de producto**. Es la fiesta KIDS **más un suplemento por cabeza**
para los que pasan del umbral. Eso, de paso, es lo que salva el caso de las zonas distintas: si no se
cambia de producto, no se cruza la frontera de §8.1 — la reserva sigue donde estaba, con su aforo.

### 8.3 · ⚠️⚠️⚠️ Un ajuste suelto NO cobra: MUEVE dinero ya pagado (medido)

La forma «obvia» —crear un `OrderAdjustment` de tipo `extra_due` con el importe del suplemento— se
probó sobre un pedido real (`R-BEEL3E`: pack de cumpleaños con señal, valor 120,00 €, señal 30,00 €
cobrada online, resto 90,00 € a cobrar en el parque), añadiendo 6,00 € y revirtiendo:

| | valor | pagado online | a cobrar en el parque |
|---|---|---|---|
| antes | 120,00 € | 30,00 € | 90,00 € |
| con un `extra_due` de +6,00 € | 120,00 € | **24,00 €** | 96,00 € |
| **Δ** | **+0,00 €** | **−6,00 €** | **+6,00 €** |

**El cliente no debe un céntimo más.** El desglose declara que 6,00 € de los que ya pagó pasan a
cobrarse en el parque, y el total del pedido no se mueve.

La causa está en la aritmética de `ReservationFinancials::make()`, y es deliberada:
`online = chargedSubtotal − extraDue − depositRemainder` y `puerta = extraDue + depositRemainder`.
Los dos canales **reparten el valor de la línea; no lo amplían**. `PAY-16`/`PAY-17` cierran
**precisamente porque** cada `extra_due` que escribe el editor viaja acompañado de una subida de
`quantity`/`unit_price` del ítem.

⚠️ El docblock de `App\Domain\Booking\Models\OrderAdjustment` ya lo decía —«dinero que el cliente
DEBE al parque **por una edición que subió el importe del pedido**»— pero esa subordinada es una
REGLA y nadie la había escrito como tal. *Una precondición que solo vive en una frase subordinada es
una precondición que alguien va a incumplir.*

### 8.4 · La forma que SÍ cobra es una LÍNEA (medido)

Misma sonda, misma reserva: en vez del ajuste suelto, un **ítem hijo** bajo el principal con
`quantity = 2` y `unit_price = 3,00 €`, más su `extra_due` de 6,00 €:

| | valor | pagado online | a cobrar en el parque |
|---|---|---|---|
| **Δ** | **+6,00 €** | **+0,00 €** | **+6,00 €** |

Exacto: la fiesta vale 6,00 € más, no se pagó nada nuevo online y el cliente debe 6,00 € en el
parque. **Y no es un invento**: es literalmente lo que ya hace el panel cuando se añade un
complemento desde «Gestionar». El suplemento del mixto es un complemento con el precio derivado.

### 8.5 · El umbral de edad no existe como dato, y `zones.age_range` no sirve

`zones.age_range` es un JSON de traducciones. Medido: `{"es":"1 — 12 años"}` en KIDS y
`{"es":"+6 años · 1,30 m"}` en JUMP. Es un **rótulo de la landing**, no un número comparable — y en
la zona de Cumpleaños vale `null`. `ticket_types` no tiene ninguna columna de edad. **El umbral hay
que crearlo**; no se puede derivar de lo que hay.

### 8.6 · La edad tampoco existe como campo, y el tipo `number` no basta

Los dos packs traen `["name","allergy","notes","special_menu"]`. El esquema admite el tipo `number`,
pero `TicketType::sanitizeGuestData()` delega en un saneo que solo **borra los no-dígitos** y recorta
la longitud: **no hay rango ni cota**. Un «999» entra. De ese dato sale un cobro, así que necesita
validación propia de dominio — y la superficie donde lo escribe el cliente es el post-form, que es
público y firmado (`RGPD-03`).

### 8.7 · La hora por zona: son DOS mecanismos y no componen como dice el encargo

- `opening_hours` es **global** (weekday, open, close). **No existe horario por zona.**
- Las horas de una zona son sus `slot_templates` (medido: la zona 4 genera 10:00 → 21:00 según el día
  de la semana, aforo 200).
- La ventana **por producto** (`available_after_open_min` / `available_before_close_min`) se ancla al
  horario **del parque**, no al de la zona (`App\Domain\Booking\Services\ProductAvailability`).

▶ Y de ahí sale la consecuencia útil: **«KIDS por la mañana, JUMP por la tarde» se puede expresar hoy
dentro de UNA zona**, con los offsets de cada producto (los dos packs los tienen a 0). No hace falta
partir la zona — y no partirla es lo que mantiene el aforo compartido, que es justo lo que una fiesta
mixta necesita.

### 8.8 · El estado de la instalación local, para que nadie estime sobre humo

`Cumpleaños Jump` (id 9) y `Cumpleaños Kids` (id 10): **zona 4** los dos, `type = pack`, min 8 /
max 20 invitados, señal fija de 30,00 €, **los mismos complementos** (calcetines, taquilla, tarta,
monitor) y **el mismo precio** (15,00 € en tarifa normal, 18,00 € en especial). **La diferencia hoy
es 0,00 €** — así que «mixta» sin cargo tiene que ser un estado válido, no un error.

### 8.9 · La etiqueta «MIXTA»: 43 sitios o 6 superficies

Medido: el nombre del producto de una reserva se compone en **43 puntos de llamada repartidos por 26
ficheros** (dominio, recursos de API, notificaciones, blades del panel). Pegar «MIXTA» al nombre es
tocar los 43, y además deja la etiqueta **indistinguible del nombre** para todo lo que venga después
(un filtro, un recuento, un correo). Publicarla como **dato propio de la reserva** la deja en las
superficies que de verdad la enseñan.

### 8.10 · ⚠️ Y una trampa del instrumento, que casi entra en esta spec como hecho

`order_adjustments.applied_by` es NOT NULL con FK `RESTRICT` a `users`: la sonda no pudo escribir un
ajuste sin nombrar a una persona. Eso parecía la prueba de que **un cargo automático es imposible por
esquema**… y es falso: `OrderCreator` ya escribe el `deposit_remainder` de forma automática poniendo
como `applied_by` **al cliente del pedido**, y lo documenta («en la compra no hay operador y el FK no
admite null; registra de forma fidedigna quién originó el cargo»). Un suplemento automático atribuido
a quien declaró las edades **tendría precedente**. *La primera lectura del esquema decía lo contrario
de lo que el código hace.*

---

## 9. La FORMA que sale de lo medido (propuesta, pendiente del ✅ del owner)

> ⚠️⚠️ **§12 CORRIGE los puntos 5 y 6 de esta sección.** «El operador acepta» y «propuesto N ·
> aplicado M» ya no describen el producto: `[DECIDIDO owner, 2026-08-29]` el importe sigue a las
> edades **sin aprobación**, y el «propuesto vs aplicado» dejó de ser un flujo de autorización para
> ser un **chivato de desfase** que solo se enciende cuando cambia la configuración. Lo demás de §9
> —el umbral en el pack, el hermano declarado, la edad tipada, el veredicto derivado y la etiqueta
> como dato— sigue vigente tal cual.

> La línea que la ordena todo: **el veredicto se DERIVA siempre; el dinero se ESCRIBE una vez.**
> Es la única forma que sobrevive a §4 (el post-form es editable hasta el evento) sin convertir cada
> corrección de una edad en un movimiento de dinero.

1. **El umbral vive en el PACK.** Columna nueva y nulable en `ticket_types`. **Nulo = este pack no
   distingue edades** y nada de esto se enciende: el producto no cambia de conducta al migrar. Va en
   el pack y no en la zona por dos razones medidas — el cobro se deriva de dos **productos** (§8.4) y
   el `age_range` de la zona es prosa traducida (§8.5).
2. **El pack «hermano», declarado.** El suplemento es
   `precio(hermano, fecha_de_la_franja) − precio(este, fecha_de_la_franja)`, los dos resueltos con
   `RateResolver` — nunca un número en el código (§2·2). Cuál es el hermano lo dice otra columna del
   pack. ⚠️ Si el hermano no tiene precio ese día, o la diferencia sale ≤ 0, **el suplemento es 0 y la
   fiesta sigue siendo MIXTA**: la etiqueta describe un hecho, no un cobro (§8.8).
3. **La edad, campo tipado.** El esquema `guest_fields` gana un campo de edad con cota real (§8.6).
   El veredicto solo mira el campo declarado como edad; los demás siguen siendo libres.
4. **El veredicto, derivado y vivo.** Un predicado del dominio sobre `OrderItem` que recorre
   `guest_data` y devuelve *cuántos* superan el umbral y *cuánto* es el suplemento unitario.
   **No se persiste nunca.** Así el ir y venir de §4 es gratis: si el cliente corrige la edad de 8 a
   6, la etiqueta desaparece sola y no hay nada que deshacer.
5. **El dinero, una línea explícita.** Al aplicarlo nace bajo el principal un ítem hijo con
   `quantity` = los niños por encima del umbral y `unit_price` = la diferencia, más su `extra_due`
   — la forma medida en §8.4. El producto portador es una fila del catálogo como las demás
   (data-driven, sin nada quemado).
6. **La divergencia es VISIBLE, y no se resuelve sola.** El panel enseña «propuesto N · aplicado M».
   Si el cliente cambia edades con el cargo ya aplicado, el operador lo ve; **el sistema no deshace
   dinero por su cuenta** (`PAY`).
7. **La etiqueta es un dato, no un trozo de nombre** (§8.9): se publica una vez y la pintan las
   superficies que la necesitan.

⚠️ **Lo que esta forma NO decide y sigue siendo del owner**: quién dispara el punto 5 —el operador o
el sistema— y qué se hace con un cargo ya aplicado cuando la fiesta deja de ser mixta. Son §10·3
y el supuesto declarado de §10.

---

## 10. ❗ Las CUATRO preguntas del owner, con su número y su coste

> Sustituye a §5. Cada salida lleva lo que cuesta; la recomendación va primero.

**1 · La ZONA en el parque REAL.** ¿KIDS y JUMP son la misma zona con horas distintas, o dos zonas?

| Salida | Qué implica | Coste |
|---|---|---|
| **Misma zona, horas por producto** (recomendada) | Se expresa hoy con los offsets de cada pack (§8.7); aforo compartido, que es lo que un mixto necesita | **0 de mecanismo** — configuración |
| Dos zonas distintas | El aforo son dos pools: un niño mayor en una fiesta KIDS ocupa plaza en JUMP. Toca `AFORO-01`/`AFORO-05` | Spec propia; **no cabe en esta** |

**2 · El UMBRAL.** ¿«Mayor de 6» significa que los 6 recién cumplidos **sí** entran y los 7 no?

| Salida | Qué implica | Coste |
|---|---|---|
| **Edad máxima INCLUIDA** (recomendada): `umbral = 6` → 6 entra, 7 paga | Un solo número por pack, sin ambigüedad de borde | Igual en los dos casos |
| Edad mínima EXCLUIDA: `umbral = 7` → paga desde 7 | Mismo mecanismo, otra convención de lectura en el panel | Igual |

**3 · QUIÉN aplica el suplemento.** El post-form lo rellena el cliente semanas antes, sin operador
delante.

| Salida | Qué implica | Coste |
|---|---|---|
| **El sistema lo PROPONE, el operador lo aplica** (recomendada) | La etiqueta aparece sola y el dinero lo escribe una persona. Reutiliza «Gestionar», que ya sabe hacerlo | **Medio** |
| El sistema lo aplica solo | Tiene precedente (§8.10), pero sería la primera vez que **una acción del cliente escribe dinero sobre un pedido pagado**, y cada corrección de edad movería el cargo | Alto en riesgo, no en líneas |
| Un segundo pago ONLINE | Flujo Redsys nuevo después del cobro (`PAY-01`…`PAY-04`), y el cliente puede simplemente no pagarlo | **Alto** |

**4 · La ETIQUETA «MIXTA».** ¿Rótulo, o cambia también el nombre del producto en correo, PDF y hoja
de sala?

| Salida | Qué implica | Coste |
|---|---|---|
| **Dato propio de la reserva** (recomendada) | Se pinta donde hace falta y se puede filtrar y contar | **6 superficies** |
| Pegada al NOMBRE del producto | El nombre deja de ser el del catálogo en todas partes | **43 puntos de llamada, 26 ficheros** (§8.9) |

▶ **Supuestos declarados** (las dos preguntas de §5 que la forma de §9 ya contesta, y que el owner
puede tumbar): **los ADULTOS no cuentan** —el post-form pide un número aproximado, no fichas con
edad—, y **un cargo ya aplicado no se retira solo**: se enseña la divergencia y decide el operador
(§9·6).

---

## 11. ✅ La TANDA 1, en el árbol (2026-08-29) — y lo que deliberadamente NO está

> `[DECIDIDO owner, 2026-08-29]`, las cinco respuestas de §10 y la que abrió el propio owner:
>
> 1. **Las zonas del parque real SON distintas** y los dos regímenes se cobran distinto. ⚠️ Y el
>    niño mayor **pasa físicamente a JUMP**, así que el aforo SÍ está tocado — pero eso va en una
>    **segunda tanda con ficha propia**: la etiqueta y el suplemento no lo necesitan (§8.2).
> 2. **El sistema PROPONE, el operador APLICA.** Ninguna acción del cliente escribe dinero.
> 3. **La edad es obligatoria u opcional POR PRODUCTO** —«en cumpleaños sí; en una excursión de
>    colegio o de empresa, quizá no»—, que es exactamente lo que el esquema `guest_fields` ya sabía
>    hacer con su `required`: **cero mecanismo nuevo**.
> 4. **La etiqueta MIXTA es un dato propio de la reserva**, no parte del nombre del producto.
> 5. **La conexión entre productos es FAMILIA + TRAMO DE EDAD**, no un puntero de mejora. La abrió
>    el owner con la pregunta que faltaba: «¿cómo sabe el sistema la diferencia entre un cumpleaños
>    KIDS y uno JUMP? No tiene conexión entre ambos». **Y era cierto**: `ticket_types` no tenía
>    ninguna columna que agrupase productos.

### 11.1 · Qué hay

| Pieza | Dónde | Qué hace |
|---|---|---|
| **La conexión** | migración `2026_08_29_000100_add_guest_age_family_to_ticket_types` | `guest_age_family` + `guest_age_min`/`guest_age_max` en `ticket_types`. Extremos **incluidos**. Vacío = apagado |
| **La edad como dato** | `TicketType::FIELD_TYPE_AGE` | Tipo de campo propio del esquema por-niño, **acotado en el saneo** (`GUEST_AGE_MIN`/`GUEST_AGE_MAX`): fuera de rango vale «no respondido», nunca un número |
| **El veredicto** | `Booking\Services\GuestAgeMixReader` → `Booking\Services\GuestAgeMix` | A qué producto de la familia le toca cada invitado y cuánto es la diferencia. **Derivado, nunca persistido** |
| **La puerta** | `InteractsWithCatalogForm::normalizeGuestAgeFields` | Normaliza la familia, la anula fuera del pack y **bloquea tramos solapados** dentro de una familia |
| **El panel** | `filament/orders/items-list.blade.php` | Etiqueta **MIXTA** junto al nombre + la propuesta con su importe y a quién corresponde |
| **El cliente** | `reservation/guests.blade.php` | El aviso **donde declara las edades**, con el importe y dónde se paga |

### 11.2 · Lo que garantizan las guardas (y su mutación)

- `GuestAgeMixTest` — 16 casos. **7 mutaciones vistas morder**: borde superior y borde inferior
  exclusivos, el suelo de 0 del suplemento, tratar «sin edad» como fuera de tramo, filtrar la
  familia por vendible, devolver 0 en vez de `null` sin precio, y quitar la cota de la edad.
- `CatalogGuestAgeFamilyTest` — 11 casos, **4 mutaciones**: solape estricto, no excluirse a sí
  mismo al editar, no limpiar fuera del pack y no normalizar la familia.
- `MixedPartyBadgeTest` — la página REAL del panel por HTTP, con su control negativo.
- `Reservation\GuestFormTest` — el aviso al cliente, con su control negativo.

⚠️⚠️ **Una guarda nació CIEGA y lo dijo la mutación**, no la revisión: el caso del borde afirmaba
solo `assertFalse(mixed)`, y **eso lo cumplen dos mundos distintos** —«el niño de 6 pertenece a
KIDS» y «no lo cubre NINGÚN pack»—. Con el borde mutado a exclusivo, el de 6 salía «fuera de rango»
y el test seguía verde. Lo que hay que afirmar es el hecho POSITIVO: `outOfRange === 0`.

⚠️ **Y el test de RENDER pagó su coste solo**: el `@use` del blade se escribió con la barra
duplicada y el post-form devolvía **500 a cualquier cliente**. Ningún test de dominio lo veía.

### 11.3 · Lo que NO está, y es scope declarado (no deuda oculta)

1. **La ACCIÓN de aplicar el suplemento.** Hoy el panel PROPONE el importe y el operador lo cobra
   con «Gestionar», tecleando la línea. Falta el botón que la escriba con el precio derivado — y con
   él, el «propuesto N · aplicado M» de §9·6. **Es dinero: entra en el `CRITICAL_RE`.**
2. **El AFORO** (`[DECIDIDO owner]`: el niño mayor pasa a JUMP). Ficha propia; toca `AFORO-01`/`-05`.
3. **Las otras superficies de la etiqueta**: hoja de sala, calendario del panel, «Mis pedidos», el
   cajón y el correo de confirmación. El dato ya está publicado por el dominio; falta pintarlo.
4. **La API** (`OrderItemResource`, `GuestFormResource`) y su contrato en `openapi/v1.yaml`: sin
   esto, la app móvil y el cajón no ven el veredicto.

---

## 12. La TANDA 2: el suplemento se COBRA, y lo hace solo (2026-08-29, `DECISIONES #244`)

> `[DECIDIDO owner, 2026-08-29]`. La tanda 1 dejaba el veredicto derivado y una propuesta que el
> operador tenía que aceptar. El owner planteó lo contrario —**que el importe siga a las edades sin
> aprobación**— y su razonamiento desmonta la objeción con la que se había diseñado:
>
> > «Es lo mismo que cuando elige producto en el carrito: él decide cuánto pagará. Y la política es
> > que cualquier gestión de dinero post-reserva ya cobrada se hace en las instalaciones. Si después
> > cambia la edad otra vez, el precio vuelve a su normalidad y se avisa también.»
>
> ▶ **Y el código ya garantizaba la mitad difícil.** La ventana en la que el cliente puede mover el
> importe se cierra EXACTAMENTE cuando el dinero se da por cobrado: las dos cosas cuelgan del mismo
> predicado, `OrderItem::isFinishedInPractice()` — el post-form pasa a solo lectura al terminar la
> franja y `Order::itemGateResolved()` deja de considerar pendiente el cargo en ese mismo instante.
> **No hay ni un minuto en el que se pueda bajar un importe ya cobrado.**

### 12.1 · Reconciliar, no aprobar

Si el importe sigue al hecho, sobra el estado «aceptado». `Booking\Services\MixedPartySurcharge`
deja, en cada pasada, lo **escrito** igual a lo **derivado**: crea la línea que falte, ajusta la que
cambió y cancela la que sobre.

De ahí salen tres propiedades que no hay que programar aparte: es **idempotente** (guardar dos veces
no acumula), es **simétrico** —«vuelve a su normalidad» es el mismo camino, no una función de
deshacer— y **no puede existir un desfase por culpa del cliente**.

⚠️ **No hay paso de confirmación, y es a propósito.** Una confirmación sirve para frenar algo
irreversible, y aquí nada lo es hasta el día de la fiesta: puede volver a editar cuantas veces
quiera. Lo que protege de verdad es decírselo en los dos momentos que importan — **antes**, en la
ayuda del campo de edad, y **después**, con el importe en pantalla y el correo.

### 12.2 · Cuándo se dispara — y cuándo NO

`[DECIDIDO owner]`: se reconcilia cuando cambia el **HECHO** y nunca cuando cambia la
**CONFIGURACIÓN**.

| Cambia… | ¿Reconcilia? | Por qué |
|---|---|---|
| las edades declaradas | **sí** | `OrderItem::submitGuestForm()`, el punto único por el que entran los datos por-niño (web y API) |
| la cantidad de invitados o el producto | **sí** | `OrderItemEditor::edit()`, post-commit |
| la fecha de la reserva | **sí** | `OrderItemEditor::changeSlot()` — la diferencia sale del catálogo de ESE día, mismo criterio que `PAY-18` |
| el precio del catálogo | **no** | lo escrito es lo que se le comunicó al cliente |
| el tramo de edad de un pack | **no** | ídem |

⚠️⚠️ **Los dos puntos del PANEL no son opcionales.** Si el operador baja los invitados de 10 a 8 y
nadie reconcilia, la línea sigue cobrando por dos niños que ya no están — y el guardado del cliente,
que es el otro disparador, puede no volver a producirse nunca. Eso obliga a tocar `OrderItemEditor`,
que está en el `CRITICAL_RE` del `pre-push`: la reconciliación va **POST-COMMIT y fuera del lock de
zona/día** (§4.3 de `desmontar-view-order.md`), en su propia transacción corta.

⚠️ El desfase entre lo escrito y lo derivado —que solo puede nacer de un cambio de configuración—
**se enseña** en la ficha del pedido. Es lo que lo hace comprobable en vez de invisible.

### 12.3 · Por qué el portador es un COMPLEMENTO y lo crea el sistema

El suplemento tiene que ser una **línea** (§8.4), y una línea necesita un producto.

⚠️⚠️ **Y no puede ser el pack de destino.** Medido: `PackAvailability` cuenta TODA fila de
`order_items` cuyo producto sea de tipo `pack` en esa zona y día **sin mirar si es una línea hija**.
Una línea de suplemento con «Cumpleaños Jump» consumiría una fiesta entera del cupo y sus plazas —en
silencio y en la pieza más delicada del sistema (`AFORO-01`)—. Por eso el portador es un
**complemento**: sin zona, con `seats = 0` y `slot_id = null`, ninguna consulta de aforo lo ve
(todas cruzan por `slots`).

`[DECIDIDO owner]` lo crea la migración y no el operador: es un producto que nadie querría configurar
a mano y que, si falta, **deja de cobrar en silencio**. Sigue siendo data-driven —el nombre se edita
en el catálogo— y su precio es irrelevante: el importe se deriva siempre de los dos packs del día.

⚠️ **Con una guarda ruidosa**: si el producto falta, está borrado o deja de ser un complemento, la
ficha del pedido lo dice en ROJO en vez de no hacer nada. Un cobro que deja de aplicarse sin que
nadie se entere era el modo de fallo que había que evitar, no uno que pudiera permitirse.

⚠️ Nace con la instalación, así que **dos tests que contaban productos pasaron a aseverar lo que de
verdad querían decir** (el catálogo vendible · cero packs y entradas), en vez de un recuento global
que ya no gobiernan.

⚠️⚠️ **Y la primera versión de la migración lo perdía todo en silencio.** Pedía
`max(position) + 1`, que sobre una base VACÍA —la de cada arranque de la suite— vale **1**. Pero
`LandingContentSeeder` identifica sus productos con `updateOrCreate(['position' => N])`: **la
posición ES su clave**. El seeder encontraba el portador en «su» posición 1 y lo sobrescribía con
una entrada, dejando la instalación sin producto de suplemento. Lo cazó la suite como «5
complementos vendibles», y el quinto era **«Jump · 1 hora» con `type` de complemento** — el nombre
del seeder sobre el tipo del portador. ▶ La posición del portador es ahora **fija: 0**, fuera del
rango que el seeder usa como clave (numera desde 1) y —esto es lo segundo que se aprendió— fuera
del `max(position) + 1` con el que `CreateCatalog` coloca cada producto nuevo al final: **con 900,
todo lo que el operador crease después saltaba a 901**. Un producto de sistema no puede mover la
numeración de los productos del cliente. *Una migración que siembra un DATO compite con el seeder
que cree que es dueño de esa tabla —y aquí la clave del seeder no es el id, es la posición— y con
todo lo que calcule a partir de ella.*

### 12.4 · La marca vive en el `context` del ajuste

Para identificar «esta línea es un suplemento de fiesta mixta» en la siguiente pasada no hace falta
ninguna columna nueva: la lleva el `context` del `OrderAdjustment`, que es 1:1 con su línea y existe
justo para describir de dónde sale un cargo. ⚠️ **No se usó `order_items.event_data`**, que habría
sido lo cómodo: eso es lo que contestó el CLIENTE y `RGPD-01` lo vacía al anonimizar.

Ese mismo `context` es lo que el cliente lee en su desglose: «Suplemento por 3 invitados de otro
tramo de edad», y no el nombre pelado del portador — la lección de `#131`, que la línea diga **por
qué** se cobra.

### 12.5 · El correo

`[DECIDIDO owner]` sí, «al igual que cuando nosotros gestionamos la reserva». ⚠️ **Con una condición:
se manda cuando el IMPORTE cambia, no en cada guardado.** El post-form está hecho para editarse
durante días; sin esa regla, un cliente que ajusta nombres tres tardes seguidas recibiría tres
correos idénticos. Cubre las dos direcciones: la retirada del suplemento también se cuenta (la
lección de `#155`, «la bajada también es dinero»).

### 12.6 · Lo que este diseño NO cierra, dicho sin adornos

⚠️ **El cliente puede declarar 8, ver el suplemento y bajarlo a 6 la víspera.** No se puede impedir
sin quitarle la edición, que es justo lo que da valor al post-form. `[owner]`: «puede mentir en la
edad con este sistema o sin él; hasta al reservar puede mentir — **eso es trabajo en persona**».

Lo que sí se hace es dejarlo **trazado**: cada cambio de importe escribe
`orders.mixed_party_surcharge_synced` en `audit_logs` (sin PII: solo cuánto era, cuánto es y por
qué), así que se ve «declaró 8 el día 3 y lo bajó a 6 el día 20». Y la **hoja de sala imprime las
columnas que declare el esquema**, así que la edad ya sale ahí sola: en la puerta se tiene delante
lo que el cliente dijo.

### 12.7 · Lo que sigue fuera

- El **AFORO** — `[owner, 2026-08-29]`: «no hay problema por ahora, eso más tarde se itera si es
  necesario». En el parque real KIDS y JUMP son zonas distintas y el niño mayor pasa físicamente a
  JUMP, así que una fiesta mixta reparte críos entre dos pools de plazas que hoy nadie cuenta.
- ~~Las **otras superficies de la etiqueta**~~ → **HECHO en la tanda 3** (§13): la etiqueta viaja
  pegada al nombre y la cogen la hoja de sala, el resumen del día, la puerta, el calendario, los
  correos, «Mis pedidos», «Mis reservas» y `OrderItemResource`. Queda **pintarla en el CAJÓN** como
  pastilla propia, que es presentación: el texto ya le llega.
- La **API** (`OrderItemResource`, `GuestFormResource`) y su contrato en `openapi/v1.yaml`: la app
  móvil guarda el post-form por la misma puerta —así que el suplemento SÍ se le reconcilia—, pero no
  recibe el veredicto y no puede enseñarlo.

---

## 13. La etiqueta, PEGADA AL NOMBRE — un sitio y todos lo cogen de ahí (`DECISIONES #245`)

> `[owner, 2026-08-29]`: «¿no podemos añadir esa etiqueta al nombre del producto y que el resto lo
> coja de ahí, en vez de añadirlo a cada superficie?».
>
> ▶ **Sí, y es la doctrina de esta casa** —`displayTimeWindow()` y `displayQuantityLabel()` existen
> por lo mismo—, con un matiz que la hace posible: **el sitio único es la RESERVA, no el producto**.
> `TicketType` lo comparten todas las fiestas y no puede saber si ESTA es mixta. El compositor es
> `OrderItem::displayProductName()`.

### 13.1 · Una regla, dos formas de leerla

| Cómo | Quién | Por qué |
|---|---|---|
| **El nombre CON la etiqueta** (`displayProductName()`) | hoja de sala · resumen del día · pantalla de puerta · calendario del panel · tarjeta de producto de los correos · «Mis pedidos» y «Mis reservas» del cliente · `OrderItemResource` | son texto plano: no pueden pintar nada al lado |
| **El DATO suelto** (`isMixedParty()`) | la ficha del pedido (pastilla violeta), y el cajón cuando llegue | pueden darle estilo, y **se puede filtrar y contar** |

⚠️ **Por eso no vive SOLO dentro del nombre.** Una etiqueta metida en la cadena deja de ser un dato:
nadie podría preguntar «enséñame las fiestas mixtas de mañana», y la ficha del pedido tendría que
elegir entre la pastilla y el texto duplicado.

⚠️ **Y no la llevan el catálogo ni el editor**: ahí el nombre es el del PRODUCTO —una fila del
catálogo, el destino de un cambio de producto—, no el de una fiesta concreta. Ni los complementos,
que son líneas propias.

### 13.2 · El premio que no se veía: al cliente le sale gratis

Componerla en el servidor hace que el cajón la reciba **sin gastar un byte del payload de montaje**,
que viaja en cada página con sesión y tiene **83 B de holgura** sobre su techo de 9.100. La
alternativa —un rótulo nuevo en el cajón— habría obligado a podar o a subir el techo.

### 13.3 · ⚠️⚠️ El precio de «que todos lo cojan de ahí»

`isMixedParty()` necesita `ticketType` y `slot`. Una lista que no las traiga cargadas paga **dos
consultas por fila**, y quien la pinte no se entera. Medido: las cuatro superficies de lista ya las
cargan (`ReservationSlip::make` con `loadMissing`, `GateReservationsReader`,
`DailyReservationsSummary`, `CalendarEventsController`).

▶ Y eso **se fija, no se confía**: `MixedPartyLabelSurfacesTest` compara el nº de consultas al pintar
el día con **2 reservas y con 6**, y exige que sea el MISMO. Verificado por mutación: quitarle el
`slot` al resumen del día pone el caso en rojo.

⚠️ Un caso de ese fichero nació **CIEGO** y se corrigió: el del calendario tenía una rama de escape
—«si la ruta no da 200, asevera sobre el compositor»— que lo hacía pasar con un usuario sin permiso,
sin llegar a mirar el calendario. Ahora se conduce la ruta con su permiso real y sin condición.

---

## 14. El caso BARATO, y el aviso que no salía (2026-08-29, `DECISIONES #246`)

> El owner lo probó en un pedido real (`R-BEEL3E`) y encontró dos cosas que resultaron ser **la
> misma**: reservó **Cumpleaños Jump** (7–99, 15,00 €) y dos invitados tienen **3 y 2 años**, así que
> les corresponde **Cumpleaños Kids** (11,00 €). Kids es **más barato**: 11,00 − 15,00 = **−4,00 €**,
> el suelo lo deja en 0, no se escribe línea… y por eso **no salía el aviso ni el correo**.

### 14.1 · El defecto: una etiqueta sin explicación

El aviso del post-form leía **solo lo escrito**, así que una fiesta mixta sin cargo no decía nada. El
cliente veía «Cumpleaños Jump · MIXTA» en su pedido y **ni una línea que lo interpretase**. Un rótulo
que el lector no puede descifrar es peor que no ponerlo.

▶ Ahora el aviso aparece **siempre que la fiesta sea mixta**, tenga o no cargo. Y lleva la
aritmética, que es lo que el owner pidió («que muestre el cambio de dinero de x € a x € porque x
cuesta y»): *«A 2 invitados les corresponde «Cumpleaños Kids» (11,00 € por invitado) en vez de
«Cumpleaños Jump» (15,00 €)»*. ⚠️ El **importe** sigue saliendo de lo ESCRITO —es lo que su pedido
dice— y la **explicación**, del veredicto: cada uno de su fuente.

### 14.2 · El caso barato: se AVISA, no se descuenta

`[DECIDIDO owner, 2026-08-29]`: «no se devuelve dinero automáticamente, pero avisar al operador y al
cliente de que la reserva es X € más barata por ese cambio».

⚠️⚠️ **Y eso NO puede viajar por el desglose de dinero.** El desglose ya tiene un canal
«Pendiente de devolución» y es **deuda real** del parque, con `PAY-16`/`PAY-17` cuadrando sobre él.
Meter ahí una cifra informativa lo descuadraría y —peor— el cliente leería una deuda que no existe.
Va como **aviso, aparte, y no suma en ningún total**:

- **cliente**: «tu fiesta saldría 8,00 € más barata. No se descuenta automáticamente: coméntalo en
  recepción el día de la fiesta»;
- **operador**: «esta fiesta saldría 8,00 € más barata en el régimen que les corresponde. NO se
  descuenta solo: decides tú en recepción».

▶ El veredicto publica ahora **dos cifras y no una**: `surchargeCents` (lo que se COBRA, con suelo en
0) y `savingsCents` (lo que costaría MENOS, informativo). Salen de la misma diferencia por cabeza,
que viaja con su **signo** (`diff_cents`) junto a la parte cobrable.

⚠️ **Por qué no se descuenta**, dicho para que no se re-abra sin querer: el encargo era cobrar al que
sube, y un descuento automático crearía **un incentivo para mentir a la baja** que hoy no existe
—declarar 3 años a un niño de 12 saldría más barato, y se aplicaría solo—.

### 14.3 · Lo que esto NO era

Tres sospechas del owner, y ninguna era el problema: **el correo** no falta (no se manda porque el
importe no ha cambiado, y sigue siendo 0); **el CSS del badge** tampoco (la etiqueta ya se veía, en
el nombre); y **el cálculo** era correcto —11,00 menos 15,00 es negativo—. Lo que faltaba era
*enseñarlo*.

---

## 15. El régimen DENTRO del recuadro de cada niño, y la línea del desglose (2026-08-29, `DECISIONES #247`)

> `[owner, 2026-08-29]`: «en el propio formulario, donde ponen la edad del niño, que en el recuadro
> de ese niño ponga informativo a qué zona pertenece».

### 15.1 · Un rótulo por ficha, del MISMO recorrido

Cada ficha del post-form lleva una pastilla con el pack que le toca por su edad, **marcada cuando NO
es el reservado** — que es el niño que mueve el precio de la fiesta. Fuera de tramo sale en rojo
(«Edad fuera de tramo»), que es un hueco de configuración, no un error del cliente.

⚠️ **Sale del mismo recorrido que el veredicto** (`GuestAgeMixReader::guestRegimes()` y `for()`
comparten `walk()`): si el rótulo tuviera su propia copia de la regla, una ficha podría decir «Kids»
mientras el total de la fiesta dice otra cosa.

⚠️ **Refleja lo GUARDADO, no lo tecleado.** El veredicto es del servidor (`CE-4`): la pastilla se
actualiza al guardar. Calcularla en el navegador mientras se escribe sería duplicar en JavaScript la
regla de la que sale un cobro.

⚠️ **Dice el PACK, no la zona**, aunque el encargo hablara de «zona». Medido: en la instalación de
demo los dos packs de cumpleaños comparten `zone_id = 4`, así que el nombre de la zona sería el mismo
para los dos niños y no distinguiría nada. El pack distingue siempre. Cambiarlo a la zona es una
línea si el owner lo prefiere para su parque, donde sí son zonas distintas.

### 15.2 · El desglose es UNO y lo leen los dos

Respondiendo a la otra pregunta del owner: sí, y no hace falta nada nuevo. `Order::pendingAtGateLines()`
es la única composición de «A cobrar en el parque ↳», y la leen **el cliente** (`gate_lines` del
ledger de la API), **el operador** (`filament/orders/partials/reservation-financials.blade.php`) y la
**hoja de sala**. El suplemento sale ahí como una línea propia.

⚠️ **Y esa frase tenía dos defectos, los dos corregidos**: decía «Suplemento por **1 invitados**»
—sin `trans_choice`, en un desglose de dinero, que es donde peor sienta— y **no nombraba el pack**.
Ahora: «Suplemento por 1 invitado que corresponde a Cumpleaños Jump». El nombre se **guarda** en el
`context` del cargo, no se resuelve al leer: es lo que se le dijo al cliente, no cambia si el
producto se renombra después, y no cuesta una consulta por línea.

⚠️ **Y una guarda del cajón cazó el resto del cambio**: `SidebarTextParityTest` exige que toda clave
de `tickets` con DOS formas plurales esté declarada — o la resuelve el cajón con `tc()`, o la compone
el servidor **y se demuestra que el cliente no la nombra**. Al pasar la línea a `trans_choice` las dos
claves entraron en esa categoría y la suite lo dijo en el acto. Están declaradas como compuestas por
el servidor: viajan ya resueltas dentro de `gate_lines`. *Sin esa guarda, el cliente habría visto la
barra vertical en pantalla el día que alguien pintara la clave con `t()`.*

---

## 16. ⏸️ DISEÑO APARCADO · el DESCUENTO del caso barato (2026-08-29, `DECISIONES #248`)

> ⏸️ **`[DECIDIDO owner, 2026-08-29]`: NO se implementa por ahora, y no se tocan las invariantes del
> dinero.** El caso barato se queda como está — **el aviso al operador y al cliente**, que ya está en
> el árbol— y la decisión se retoma **en producción, según las circunstancias**: si en la operación
> real resulta necesario, se hace automático con este diseño.
>
> ▶ **El diseño se conserva ENTERO y verificado a propósito.** No es documentación de algo que no se
> hizo: es el trabajo que no habrá que repetir. Incluye la aritmética de los tres casos, la cota que
> demuestra que ningún canal queda negativo, y —lo que más vale— **un supuesto que se midió y resultó
> FALSO** (§16.5.bis), que en código habría costado un desglose que suma mal en producción.
>
> **Qué lo despertaría**: que en el parque real aparezcan fiestas mixtas a la baja con frecuencia
> suficiente como para que el gesto manual del operador deje de valer.
>
> ❗ Esto era DISEÑO, no código, y por eso se escribió antes: el punto delicado no son las líneas, es
> la contabilidad. Equivocarse ahí no da error — da un desglose que suma mal.

### 16.1 · El contrato que hay que satisfacer

`OrderFinancialInvariantsTest` vigila **once escenarios** y, en cada uno, cinco cruces. Dos cláusulas
mandan sobre cualquier diseño:

1. **`valor = pagadoOnline + pendienteOnline + aCobrarPuerta + cobradoPuerta + compensado`** por
   reserva, y las sumas de las tarjetas cuadran con el agregado del pedido.
2. ⚠️⚠️ **Ningún canal puede ser negativo, y los `max(0, …)` NO pueden estar tapando nada**: el
   propio test lo asevera —«un clamp que muerde es un defecto escondido»—.

Y la segunda identidad, la de CAJA (`PAY-17`): **cobrado por web − devuelto = pagadoOnline +
pendienteDevolución**.

### 16.2 · Por qué no vale ninguna forma «obvia»

| Intento | Por qué NO |
|---|---|
| Descontar del total | **El total no existe como número**: se suma de las líneas. El campo `total` es lo cobrado por web y tiene que seguir cuadrando con el pago de Redsys (`PAY-01`) |
| Bajar el `unit_price` del pack | Afecta a los N invitados, casi nunca da céntimos enteros (3 × 7,00 € entre 8 son 2,625 €) y reescribe el precio que el cliente aceptó |
| Un crédito de puerta suelto | **Sube «pagado online»**: `collected = subtotal − extraDue`, así que rebajar la puerta sin bajar el valor atribuye la diferencia al pago web. Es el defecto de §8.3 exactamente al revés |
| Una línea con importe negativo | ⚠️ **Medido: `order_items.unit_price` es UNSIGNED.** Imposible a nivel de esquema |
| Un término nuevo en la identidad | Choca con `[DECIDIDO owner]` en `specs/lealtad-jumppoints.md`: «un vale con valor monetario es un movimiento de dinero; no entra en el desglose» |

### 16.3 · La forma que SÍ cierra: una línea de CRÉDITO con la cascada de las bajadas

La reserva gana una línea hija marcada como **crédito**: su importe se guarda en positivo y el
subtotal de la línea lo devuelve **en negativo** (el signo lo pone el dominio, no la columna). Y esa
línea toma su crédito **de los cubos de puerta de sí misma**, con la MISMA cascada que ya usa una
bajada de invitados: primero contra el cargo de puerta, luego contra el resto de la señal, y lo que
sobra se queda como deuda.

▶ **No es un mecanismo nuevo**: es la política de `OrderItemEditor::creditReduction()` aplicada a una
línea en vez de a un cambio de cantidad.

### 16.4 · La aritmética, en los tres casos

Fiesta de 8 × 15,00 € = 120,00 €. Dos invitados corresponden a un pack de 11,00 € → crédito 8,00 €.

**A · Con señal (hay puerta que absorber)** — señal 30,00 € online, resto 90,00 € en puerta:

| | subtotal | cubos de puerta | cobrado online |
|---|---|---|---|
| principal | 120,00 | 90,00 (resto señal) | 30,00 |
| línea de crédito | −8,00 | −8,00 | 0,00 |
| **reserva** | **112,00** | **82,00** | **30,00** |

`30,00 + 82,00 = 112,00` ✓ · ningún canal negativo ✓ · nada que devolver ✓

**B · Pagado 100% online (no hay puerta que absorber)** — ⚠️ el caso que obliga al MARCADOR de §16.5.bis:

| | subtotal | cubos de puerta | cobrado online |
|---|---|---|---|
| principal | 120,00 | 0,00 | 120,00 |
| línea de crédito | −8,00 | 0,00 | **−8,00** |
| **reserva** | **112,00** | **0,00** | **112,00** |

`112,00 + 0 = 112,00` ✓. Y la deuda aparece sola por la fórmula que YA existe:
`pendienteReembolso = originalOnline − devuelto − cobrado` → `0 − 0 − (−8,00) = 8,00 €` ✓
Caja: `120,00 − 0 = 112,00 + 8,00` ✓

**C · Señal pequeña (la puerta absorbe solo una parte)** — señal 117,00 online, resto 3,00 en puerta:
la cascada se lleva 3,00 € de la puerta y los 5,00 restantes quedan como deuda. Los dos canales
positivos, las dos identidades cerradas.

### 16.5.bis · ⚠️⚠️ El MARCADOR, y por qué el diseño no cierra sin él (medido)

El caso B se apoyaba en que el «online original» de la línea de crédito fuera **0**. **Se comprobó
contra el código y era FALSO**: el respaldo de `Order::itemOriginalOnlineCents()` cuando no hay nada
que reconstruir es `return $this->itemCollectedCents($item)` — o sea que devolvería **−8,00 €**, el
mismo número que lo cobrado ahora, y la deuda daría
`max(0, −8,00 − 0 − (−8,00)) = 0`. **El dinero se perdía en silencio**, y justo en la configuración
más común de un pack sin señal.

▶ La salida ya existe y es la misma que usan las bajadas: la línea de crédito nace con un
**marcador** —un ajuste de 0 € que porta `quantity_change.old = 0`— para que la reconstrucción
diga «esta línea valía 0 antes», que es literalmente cierto: no existía cuando el pedido se cobró.
Entonces `originalOnline = 0`, `cobrado = −8,00` y la deuda sale **8,00 €** ✓.

⚠️ **Y eso impone una condición al producto portador: no puede tener señal.** La reconstrucción pasa
el valor original por `depositCents()`, y con una señal fija devolvería el importe de la señal en vez
de 0. El portador que crea la migración es un complemento sin señal, así que se cumple — pero es una
condición del diseño, no una casualidad, y hay que aseverarla.

▶ **Lección del propio diseño**: este supuesto estaba escrito como «hay que comprobarlo» en §16.7 y
al comprobarlo se cayó. Es exactamente lo que esta fase existe para encontrar — a un coste de cinco
minutos en vez de un desglose que suma mal en producción.

### 16.5 · ⚠️ Lo que hay que RELAJAR, y por qué es seguro

`Order::itemCollectedCents()` capa hoy a 0. Para el caso B tiene que poder devolver **negativo en una
línea de crédito** — si no, el clamp se traga la deuda y el desglose deja de sumar.

▶ **Y eso no viola la cláusula 2 del contrato**, que habla de los CANALES, no del sumando de una
línea. El canal sigue siendo positivo porque el crédito está **acotado por construcción**: es
`Σ (precio_reservado − precio_destino) × invitados`, y como el precio de destino es mayor que cero,
el crédito es **estrictamente menor que el subtotal de la reserva**. Una reserva no puede quedar en
valor negativo por este camino. *La cota no es una precaución: es una propiedad demostrable, y como
tal se asevera.*

### 16.6 · Lo que se toca, en orden de riesgo

| # | Qué | Riesgo |
|---|---|---|
| 1 | Una marca en la línea («esto resta») y el subtotal en negativo — **un solo sitio**: 24 consumidores usan ese helper y ninguno multiplica a mano | bajo |
| 2 | El reconciliador escribe la línea de crédito y aplica la cascada | bajo — espejo del suplemento, ya probado |
| 3 | El desglose ↳ deja de saltarse los importes negativos y les da su frase | bajo |
| 4 | **`itemCollectedCents` deja de capar en una línea de crédito** | **ALTO** — toca `PAY-16`/`PAY-17` |
| 4.bis | El marcador de reconstrucción en la línea de crédito (§16.5.bis) | medio — sin él, el caso B pierde el dinero |
| 5 | Escenarios nuevos en el guardián de once, uno por cada caso A/B/C | obligatorio |

### 16.7 · ❗ Lo que este diseño NO resuelve todavía

- ~~La reconstrucción de `itemOriginalOnlineCents`~~ → **COMPROBADO y RESUELTO en §16.5.bis**: el
  supuesto era falso y la salida es el marcador. La condición que impone —portador sin señal— queda
  como aserción, no como confianza.
- **Qué pasa si el crédito nace ANTES que la señal se cobre** (pedido pendiente): el post-form solo
  existe sobre pedidos pagados, así que no debería darse — pero conviene aseverarlo, no suponerlo.
- **Y el incentivo**: el crédito se aplicaría solo, así que declarar una edad más baja sale a cuenta.
  `[PENDIENTE owner]`: automático como el suplemento, o escrito por el operador al aceptar.

### 16.8 · Cómo se valida antes de creérselo

Los tres casos A/B/C entran como escenarios del guardián **antes** de escribir el reconciliador, y
con una mutación que debe morder: **quitar la cascada** (crédito sin tocar los cubos de puerta) tiene
que poner en rojo el caso A, y **devolver el clamp** tiene que poner en rojo el caso B. Si alguna de
las dos no muerde, el diseño no está probado — está escrito.
