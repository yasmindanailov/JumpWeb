# [SPEC] Cumpleaños **MIXTO** — cuando la edad declarada no cuadra con el pack reservado

> Estado: 🟦 **CINCO TANDAS EN EL ÁRBOL** (2026-08-29, `DECISIONES #243`→`#247`) · ⏸️ **el descuento
> del caso barato queda APARCADO con su diseño escrito y verificado** (§16, `#248`): la conexión entre
> productos, el veredicto derivado, la puerta del catálogo, el suplemento —que se COBRA y se
> recalcula solo—, la etiqueta pegada al nombre y el **caso barato**. El owner ya lo ha visto en
> navegador (la etiqueta sale para admin y cliente); sigue 🟦 por lo declarado fuera (§12.7) ·
> Última actualización: **2026-08-31** (§21: el diseño fino de la T1, el SELLO).
>
> ▶ ❗❗❗ **DESDE EL 2026-08-31 EL PUNTO DE ENTRADA ES §18** (la visión cerrada, `#284`) **y §21 si
> vas a construir la T1**. Todo lo que sigue en esta cabecera es de antes.
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

> ❗❗ **CORRECCIÓN (2026-08-29, §17): esta regla estaba ESCRITA y no estaba CONSTRUIDA.** Se
> implementaba sola —«no hay disparador en los cambios de configuración»— y eso solo aguanta hasta el
> siguiente disparo de hecho, porque la reconciliación re-derivaba el importe entero del catálogo
> vigente. Y el disparo siguiente suele ser el cliente corrigiendo un nombre, que este formulario
> invita a hacer durante días. **Medido, con las edades intactas: una subida de tarifa llevaba un cargo
> de 15,00 € a 30,00 €; estrechar un tramo, a 0,00 €.** Entre el 29 y el 31 la sostuvo el RECIBO que
> cada línea llevaba en su `context` (`MixedPartySurcharge::unitFor`, `#270`); **desde el 2026-08-31
> la sostiene el SELLO de la reserva (§21), que subsume al recibo**. **Lee §17 y §21 antes que esta
> tabla.**

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
⚠️⚠️ **Y se enseña también cuando el pack dejó de participar en su familia** (§17.3·2): ese bloque
colgaba de `$mix->applies`, así que retirar la familia lo hacía desaparecer entero dejando al operador
un importe en «a cobrar en el parque» y **cero** explicación en pantalla.

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

> ❗❗ **CORRECCIÓN (2026-08-31, `#285`): este diseño YA NO es el vigente para el −X €.** El vigente
> es **§20** —el espejo acotado a puerta, sin el clamp ni el marcador—. Y el «qué lo despertaría» de
> abajo queda REESCRITO: no es «según las circunstancias», es **la fase 3 de la hoja de ruta de
> cobro** (todo online, también las gestiones post-reserva), que llegará con su propia feature de
> cobro/reembolso online post-reserva (§20.2). Sus piezas técnicas (la relajación del clamp, el
> marcador de §16.5.bis y la condición del portador sin señal) siguen siendo correctas PARA ESE
> MOMENTO, y por eso el diseño se conserva.
>
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

---

## 17. ❗❗ LA REVISIÓN ADVERSARIAL, Y LOS SEIS DEFECTOS QUE ENCONTRÓ (2026-08-29, `#249` → `#272`)

> **Empieza por aquí si vas a tocar el suplemento.** Este apartado CORRIGE a §12.2: la regla que esa
> tabla enuncia estaba escrita y **no estaba construida**. El cuerpo de arriba describe el diseño;
> esto describe lo que el código hacía de verdad y lo que se cambió.

El subsistema entero (`#243`→`#248`) aterrizó en una jornada: ~1.300 líneas de dominio que escriben
dinero sobre pedidos ya pagados. Nadie lo había revisado. La revisión se hizo **sin escribir código**,
midiendo cada hallazgo sobre el pedido real `R-BEEL3E` en transacciones revertidas.

### 17.1 · Lo que AGUANTÓ (verificado de forma independiente, no por sus propios tests)

- **El dinero cuadra**: `PAY-16`/`PAY-17` cierran antes, durante y después de escribir el suplemento.
  Medido: valor 13.590 → 15.090, +1.500 a puerta, `pagadoOnline` intacto en 4.590, `cuadra = true`.
- **El aforo no se mueve** (20 → 20 invitados libres para una fiesta nueva), y por **dos** mecanismos
  independientes: `slot_id = null` ya excluye la fila del `JOIN` de `PackAvailability`, y además el
  portador es un complemento. ⚠️ Eso matiza a §12.3: el motivo que da —«el portador no puede ser el
  pack de destino»— es correcto, pero la línea no contaría **aunque lo fuera**, porque no tiene franja.
- **Idempotente y reversible al dígito**; **el rastro se escribe** también desde el enlace firmado sin
  sesión (`user_id = null`); cancelar la reserva **cascadea** a la línea; el panel **bloquea** editar
  una reserva finalizada, así que no se reconcilia después del evento.

### 17.2 · Los SEIS defectos, y por qué son UNO

El importe no se guardaba: **se recalculaba entero desde el catálogo vigente en cada disparo**. Y
cuando no había con qué calcular, se escribía **cero** en vez de dejarlo quieto.

| # | Qué pasaba (medido, con las EDADES INTACTAS) | Gravedad |
|---|---|---|
| 1 | El parque sube la tarifa del pack destino; el cliente corrige un **nombre** → el cargo pasa de 15,00 € a **30,00 €**, con su correo | Alta |
| 2 | Falta el precio del pack destino ese día → el veredicto dice `null` («no se pudo tarificar») y el reconciliador lo escribía como **0,00 €**, cancelando la línea | Media |
| 3 | El operador anula la línea para perdonar el cargo → **vuelve sola** y al cliente le llega un correo anunciándole lo que le acaban de perdonar | Media |
| 4 | El tipo `age` puede llegar a `event_fields` y ahí rompe el `enum` de `CatalogEventField` en `openapi/v1.yaml` — ✅ **CERRADO** (`#282`, §17.7) | Baja |
| 5 | **El cliente vacía sus casillas de edad y guarda → su cargo desaparece** | **Alta** |
| 6 | `RGPD-01` anonimiza y pone `guest_data` a `null` → la siguiente pasada **borra la deuda** | Alta |

⚠️⚠️ **El 5 es el peor y no lo vio nadie durante la implementación**: mentir con la edad —que el owner
ya dio por inevitable (§12.6)— obliga a inventarse un número creíble; **borrarla no**. Lo dispara el
propio interesado y no exige más que vaciar tres casillas.

⚠️⚠️ **Y la guarda que existía para el defecto 1 pasaba en VERDE con el defecto puesto.**
`test_the_written_amount_survives_a_catalogue_price_change` aseveraba el importe **justo después** de
subir el precio, sin volver a guardar — y ahí no hay nada que probar, porque nada dispara una
reconciliación. El defecto vivía en el guardado SIGUIENTE. *Una guarda que no ejercita el disparador
no vigila la regla, vigila el reposo.*

### 17.3 · Lo que se construyó

1. **Una ausencia no es una corrección** (`derivationGoverns`): con el veredicto incompleto —falta una
   edad, sobra una fuera de rango, o el pack dejó de participar— lo escrito **puede CRECER pero nunca
   encoger ni retirarse**. La asimetría es deliberada: declarar la edad que faltaba es un dato nuevo y
   legítimo; borrarla no. Cierra 2, 5 y 6.
2. **El operador ve el cargo aunque el catálogo haya cambiado**: el bloque de la ficha colgaba de
   `$mix->applies`, así que retirar la familia lo hacía desaparecer dejando un importe a cobrar y cero
   explicación. Ahora manda el dinero escrito.
3. **El RECIBO** (`unitFor`): cada línea guarda en su `context` los dos hechos que sostienen su
   precio —`booked_type_id` y `priced_on`— y hereda el unitario escrito mientras no se muevan. Sin
   columna nueva y sin migración. Cierra 1. ⚠️ **La cantidad nunca se hereda, solo el unitario**
   (`[DECIDIDO owner]`: un invitado declarado tras una subida entra a **14,00 €, no a 24,00 €**).
   ⚠️ Y no congela de más: mover el día o cambiar el pack **sí** re-tarifican (`PAY-18`), con caso de
   control para las dos cosas.
4. **La línea no la gobierna el operador**: el panel ofrecía ponerla a 0 y la reconciliación la
   resucitaba **en la misma pulsación**, con dos correos contradictorios al cliente. `[DECIDIDO owner]`
   **no se construye el botón de perdonar** —§17.5—, así que el gesto deja de ofrecerse y el **editor**
   lo descarta. Cierra 3.
5. **`MixedPartySurcharge` entra en el `CRITICAL_RE`** del `pre-push` y en `CriticalPathGateTest`, con
   el mismo criterio que `WaiverSigner`: escribe dinero bajo lock y tiene verificador propio.
6. **`PAY-19`** recoge la regla entera en `INVARIANTES.md`.

### 17.4 · ¿Para qué perdonaría un suplemento el operador? — **para nada**

`[DECIDIDO owner, 2026-08-29]`. El argumento decisivo es suyo y ya estaba escrito en `#244`: «la
política es que cualquier gestión de dinero post-reserva ya cobrada se hace en las instalaciones». Ese
suplemento **se cobra en el mostrador y el sistema no registra si se cobró**, así que quien quiera
perdonarlo ya puede: no cobrándolo. Un botón no daría poder nuevo — solo borraría del pedido algo que
ya se le comunicó al cliente.

Y dos razones más: **empeoraría el rastro** (hoy, si no se cobra, la diferencia se nota; con el botón
el perdón queda escrito como estado legítimo y deja de notarse) y **no tiene forma correcta de
escribirse** (si el cliente sube después de 2 a 5 invitados, o el perdón se mantiene —tres cabezas
regaladas en silencio— o caduca —y el operador se encuentra su perdón deshecho—).

⚠️ Medido de paso: **en todo el sistema no existe ningún descuento manual** — ni cupón, ni cortesía,
ni precio editable. Así que «¿puede el operador perdonar 6,00 €?» es en realidad «¿debería el parque
poder regalar algo?», que es una decisión propia y mucho mayor. Ficha en `DEUDA.md`.

### 17.5 · Lo que SIGUE ABIERTO, dicho sin adornos

> ❗❗ **CORRECCIÓN (2026-08-30), y va antes que el texto de abajo.** El ejemplo con el que se
> describió el caso espejo —«Kids pasa a 1–12 y aparecen 40,00 €»— **NO REPRODUCE con los precios
> reales de la instalación**: se midió en una sonda que había forzado antes el precio de Kids POR
> ENCIMA del de Jump, y esa condición no viajó con el número. Medido de nuevo con Kids a 11,00 € y
> Jump a 15,00 €: ensanchar el tramo de Kids da **0,00 €**, porque la diferencia hacia un pack más
> barato tiene suelo en 0. **El mecanismo es real; el ejemplo era irreproducible.**
> ▶ Lo que SÍ crea un cargo de la nada es que un cambio de tramos empuje a los invitados hacia el
> pack **MÁS CARO** (medido: 40,00 € con el destino forzado caro; en la instalación real sería
> estrechar el tramo de Kids para que sus niños caigan en Jump).
> ❗❗❗ **Y de re-medirlo salió un agujero en `#270`/`PAY-19` que no estaba documentado: un tramo
> ENSANCHADO destruye un cargo ya escrito.** Medido: 40,00 € comunicados → el parque decide que a
> partir de 3 años se va a Jump → el cliente edita un nombre → **0,00 €**. La abstención de `#268`
> no lo ve porque el veredicto sigue **completo** (`applies=true`, `isComplete()=true`): el RECIBO
> congeló el PRECIO, no a QUIÉN se le aplica. ⚠️ Esa dirección es **benigna para el cliente** —le
> quita un cargo—, así que si se cierra hay que decidir a la vez si el parque puede seguir siendo
> generoso hacia atrás. Es la MISMA pieza que falta para el caso espejo: la reserva no recuerda con
> qué tramos se hizo.

- ❗ **El caso ESPEJO**: una configuración que **crea** un cargo donde no había ninguno. Medido: una
  reserva de Jump con 8 invitados de 12 años y **cero** cargo; se reordenan los tramos (Kids pasa a
  1–12); el cliente corrige un nombre → **40,00 €** de cargo nuevo. El recibo no lo cierra porque ahí
  no hay nada escrito que proteger. Cerrarlo exige sellar el régimen **en la reserva** —columna nueva y
  migración—, y eso es decisión del owner.
- ❗ **La etiqueta puede contradecir al cargo, y ahora de forma PERMANENTE.** Medido en ese mismo
  estado: la hoja de sala imprime «Cumpleaños Jump» **sin** la etiqueta MIXTA, la pastilla de cada niño
  dice que está en su propio pack, y el mostrador cobra 40,00 € «por 8 invitados que corresponden a
  Cumpleaños Kids». Antes esa contradicción duraba hasta el siguiente guardado (que borraba el cargo);
  ahora el cargo sobrevive. **Se cambió un fallo de dinero por uno de coherencia** — mejor negocio,
  pero hay que saberlo.
- ⬜ **No hay forma de ver qué reservas tienen desfase**: cero columnas y cero filtros en las tablas del
  panel. Solo se ve abriendo la ficha, de una en una.
- ⬜ **Tras anonimizar, el importe queda quieto también hacia abajo**: bajar de 10 a 8 invitados sigue
  cobrando por 10. Se cambia dinero mal por dinero quieto; es una elección, no una solución.
- ⬜ **Las líneas escritas antes de esta tanda** no llevan recibo hasta su primera pasada. Se heredan
  igual (nunca se re-tarifican desde el catálogo de hoy), pero si la configuración cambia antes de esa
  pasada, sufren el defecto una última vez.
- ⬜ Sigue fuera lo que ya estaba fuera: el cliente puede bajar la edad la víspera (§12.6), el **AFORO**
  no reparte plazas entre Kids y Jump (§12.7), y la **API** no sirve ni el veredicto ni el suplemento.
- ⬜ **Nada de esto está visto en navegador todavía**: falta el OJO del owner.

### 17.6 · Cuatro veces que mintió el INSTRUMENTO, no el código

1. La primera sonda cambió el `unit_price` del ítem a mano y dio «el ledger no cuadra»: el descuadre
   era **suyo**, no del subsistema.
2. `GuestAgeMixReader` iba en **`scoped`** y memoizaba familia y precios, así que dentro de un mismo
   proceso un cambio de catálogo **no se veía**. Era el motivo por el que las guardas necesitaban
   `nextRequest()` (`$app->forgetScopedInstances()`): sin él **nacían ciegas**. ▶ **Cerrado por
   construcción con la T1 (§21.5)**: el lector deriva del sello que viaja en la fila, no memoiza nada
   y ya no es `scoped`; `nextRequest()` se retiró de los tests.
3. Se leyó `AuditLog->context` en vez de `->payload` y se concluyó «no hay rastro» habiendo 16 filas.
4. Doce filas de auditoría idénticas parecían una fuga y eran el **residuo de la corrida deliberada
   sin lock** que la propia doc documenta (12 × 7,00 € = 84,00 €).

*Cuando un instrumento dice que algo está roto, la primera hipótesis es el instrumento.*

### 17.7 · ✅ Cerrado el defecto 4: cada esquema declara SUS tipos (2026-08-30, `#282`)

El dominio conoce cuatro tipos de campo, pero **los dos esquemas no aceptan los mismos**: la EDAD es
un dato POR INVITADO del que sale un cobro, y una sola edad para toda la fiesta no significa nada.
Eso solo lo sabía el `Select` del panel; **las dos puertas de saneo aceptaban los cuatro en los dos
esquemas**, que es exactamente lo que la regla 12 de este proyecto dice que no se puede suponer.

▶ Ahora la lista vive en el dominio y por esquema —`TicketType::EVENT_FIELD_TYPES` y
`GUEST_FIELD_TYPES`, servidas por `fieldTypesFor()`— y la leen las **tres** puertas: el `Select`, el
saneo del panel y el del modelo. Un tipo nuevo entra donde signifique algo, no en todas partes.

⚠️⚠️ **La guarda que importa no es la de conducta, es la del CONTRATO.** `ApiContractTest` compara
`EVENT_FIELD_TYPES` con el `enum` de `CatalogEventField` en `openapi/v1.yaml` y falla **por los dos
lados**: añadir un tipo al dominio sin declararlo, o declararlo sin que el dominio lo acepte. *El
defecto no fue no saberlo: fue que nada lo comprobaba.* Lleva su control explícito —que las dos
listas sigan siendo distintas—, porque si algún día se fundieran, la guarda pasaría en verde sin
vigilar nada.

**Verificación**: +4 casos · **4 mutaciones y las 4 muerden** (el modelo laxo, el panel laxo, el
dominio estrenando un tipo sin contrato, y las dos listas fundidas).

### 17.8 · ~~✅~~ El AVISO antes de mover un tramo (2026-08-30, `#283`) — **RETIRADO el 2026-08-31 con la T1**

> ❗ **`[DECIDIDO owner, 2026-08-31]` (§21.8 Q1): con el SELLO en cada reserva un cambio de tramos no
> mueve ninguna fiesta vendida, así que este aviso no tenía nada que avisar y se retiró entero
> —`MixedPartyBandImpact`, la firma del doble guardado y sus 7 casos— en vez de dejarlo como red que
> nunca salta. Queda la regla dicha en la ayuda del campo de familia («las fiestas ya vendidas
> conservan la familia, los tramos y los precios con los que se compraron»). Lo de abajo es historia:
> sirve para entender por qué el sello era la salida buena, y para la lección de método.

`[DECIDIDO owner]` de las dos salidas al caso espejo —sellar el régimen en cada reserva, o avisar
antes de tocar el catálogo— se hace **el aviso**. No cierra el agujero: **cierra la forma en que te
pilla desprevenido**, que es el riesgo real, porque mover tramos es cosa de una vez al año y entonces
toca a todas las fiestas vivas a la vez.

▶ Al guardar un tramo en el catálogo, si el cambio movería dinero de fiestas **ya vendidas y sin
celebrar**, el guardado se interrumpe UNA vez con los números: a cuántas afecta, cuánto crearía y
cuánto retiraría. Volver a guardar lo mismo lo aplica. La FIRMA del cambio es lo que impide que
«volver a guardar» sea un cheque en blanco: si el operador retoca los números, se le avisa de nuevo.

⚠️ **El precio ya no entra**, y no es un olvido: desde `#270` cada línea lleva su recibo y hereda el
unitario comunicado, así que retocar una tarifa no mueve lo vendido. El único eje que sigue
moviéndolo es el TRAMO.

⚠️⚠️ **El número del aviso sale del MISMO recorrido que el que se escribirá después.** Se clonan los
packs en memoria con los valores propuestos y se le siembran al lector
(`GuestAgeMixReader::pretendFamilyIs`) en vez de copiar la aritmética: una copia daría un aviso que
envejece solo, y el operador decidiría mirando una cifra que el reconciliador no va a respetar. Y se
simula **sin escribir**, para no disparar eventos de modelo ni auditoría por pintar un número.

❗ **Lo que enseñó construirlo, y no estaba escrito en ninguna parte**: crear un cargo de la nada
exige **DOS guardados**, no uno. Estrechar el tramo del pack barato no mueve un euro —sus invitados
caen en un HUECO que no cubre ningún pack, el veredicto queda incompleto y la abstención de `#268`
protege lo escrito—. El cargo nace en el segundo paso, al **ensanchar** el tramo del pack caro hasta
alcanzarlos. El aviso salta ahí, que es donde el dinero se mueve.

⚠️ Y una guarda **nació CIEGA**: la de «una fiesta ya celebrada no cuenta» estaba escrita sobre un
escenario que no movía dinero de todas formas, así que quitar el filtro la dejaba en verde. Probaba
que no había nada que filtrar, no el filtro. Reescrita sobre el escenario que sí crea los 32,00 €.

**Verificación**: +7 casos · **5 mutaciones y las 5 muerden** (el aviso desconectado · la firma sin
memoria · las celebradas contadas · la familia antigua perdida · el orden de la familia simulada
divergiendo del lector).

---

## 18. ❗❗❗ LA VISIÓN DEL OWNER, CERRADA (2026-08-31, `DECISIONES #284`)

> **Si vas a construir algo de reservas mixtas, LEE ESTE APARTADO Y NADA MÁS.** Es la foto completa
> tras cerrar la visión con el owner: qué se decidió, qué ya es cierto, qué falta y en qué orden.
> Lo de arriba (§1–§17) es la historia de cómo se llegó aquí. ▶ **Si construyes la T1, después
> de esto lee §21** (el diseño fino del sello, 2026-08-31).

### 18.1 · La visión, en las palabras del owner

Un pack declara **familia** y **tramo de edad**. Cuando el cliente declara en el post-form la edad de
un invitado que pertenece a otro tramo de la misma familia, se le añade **+X €** o **−X €** según el
otro producto sea más caro o más barato; si cuestan lo mismo, **solo se le avisa**.

**El cliente compra con unas condiciones y se las mantenemos.** Solo cambia de condiciones lo que
cambia de producto: la fecha es un producto (cambiarla re-tarifica), un complemento **nuevo** entra
al precio de hoy, y lo que ya tenía conserva el suyo. Las siguientes reservas empiezan con las
condiciones nuevas.

**Los tramos de una familia no pueden solaparse** —el sistema lo impide— y **si una edad no tiene
tramo, no es un hueco: es que no hay producto para ella**, y se le explica al cliente con las normas
del parque.

En el parque, el operador ve las edades y los precios y **decide**: puede corregir la edad, ajustar
los invitados y valorar cada caso.

### 18.2 · Lo que YA es cierto (verificado contra el código, no supuesto)

| De la visión | Dónde vive | Estado |
|---|---|---|
| Familia + tramo vinculan dos packs | `TicketType`, `#243` | ✅ |
| **+X €** al declarar una edad de otro tramo | `MixedPartySurcharge` | ✅ |
| Mismo precio → solo aviso, sin cargo | `test_two_packs_at_the_same_price_write_nothing` | ✅ |
| Tramos de una familia no pueden chocar | `guardAgeRangeIsFree` | ✅ (solo desde el panel — **D9**) |
| Producto y fecha sin cambio → precio histórico | `ItemEditPricing:77` | ✅ |
| Cambiar la fecha → precio del día destino | `PAY-18` | ✅ |
| Cambiar el pack → precio de hoy del nuevo | `ItemEditPricing:81` | ✅ |
| Complemento **nuevo** → precio de hoy | `ItemEditPricing:157` | ✅ |
| Complemento que ya tenía → su precio original | `ItemEditPricing:131` | ✅ |
| El suplemento hereda el unitario comunicado | ~~`unitFor()`, `#270`~~ → el SELLO (`#288`, §21.6): el unitario derivado ES el comunicado | ✅ |
| Un invitado declarado después de una subida entra al precio comunicado | `#270`, hoy por construcción del sello | ✅ **D4** |
| La hoja de sala imprime las edades declaradas | `ReservationSlip` | ✅ |
| El catálogo avisa antes de mover un tramo con fiestas vendidas | `#283` | ✅ |

⚠️ **`PAY-18` NO era un choque con la visión: es la misma regla.** «La fecha es un producto», dicho
por el owner, y por eso cambiarla re-tarifica. Queda intacta.

### 18.3 · Las decisiones tomadas (`[DECIDIDO owner, 2026-08-31]`)

**D1 · El sello vive en la RESERVA, no en el producto.** Cada reserva guarda al nacer una copia de
las condiciones de su familia —los **tramos** y los **precios** de cada pack para su fecha—. El
producto tiene un solo precio, el de hoy; **el precio viejo solo existe dentro de las reservas que lo
llevan**. No hace falta histórico de precios y no se construye ninguno.

**D2 · Con el sello, ningún cambio de catálogo mueve una reserva vendida.** Ni un precio ni un tramo.
Cierra el caso espejo, su gemelo (la etiqueta que contradice al cargo) y el hueco del **primer**
cargo — que hoy se calcula con el catálogo del día en que el cliente rellena el formulario, no con el
del día en que compró (§18.4·A).

**D3 · No hace falta rellenar nada al desplegar.** `ENTORNOS.md`: **0 LIVE · 0 PRODUCCIÓN**. Si el
sello entra antes de la apertura, toda reserva que exista nacerá con su copia. Los datos de local y
staging los borra `app:purge-customers` en la puesta en marcha.

**D4 · Un invitado declarado después de una subida entra al precio COMUNICADO.** «Un invitado nuevo
no es producto nuevo, es una gestión sobre las condiciones ya aceptadas.» Ya construido en `#270`.

**D5 · El −X € se implementa** (revierte `#246`/`#248`), y **se diseña con Fable antes de tocar
nada**: el desglose es lo más sensible del sistema. Informe en §19. ▶ ✅ **DISEÑADO Y CERRADO el
2026-08-31: el diseño vigente es §20** (`#285`).

**D6 · Una edad sin producto informa y NO deja completar el formulario, pero no toca el desglose.**
Se guarda lo escrito (no se pierden los otros invitados), el formulario nunca queda completo, se le
explica con las normas del parque —textos configurables por instalación, distintos por caso: por
debajo del tramo menor, por encima del mayor, hueco intermedio— y se le pide que llame. El operador
valora y ajusta. **No se genera ninguna línea de dinero por esa edad.**

**D7 · El operador puede bajar del mínimo del pack.** «Al final él decide sobre su producto.» Solo
él, y queda auditado como acción suya. ⚠️ El mínimo existe por una razón de negocio: la excepción se
registra, no se silencia. Revisable más adelante.

**D8 · Anonimizar solo sin reservas en vigor.** Con una reserva por celebrar **no se puede** borrar
la cuenta, y se le explica por qué. Con todas finalizadas, sí. Y se mantiene el régimen actual:
**la factura se conserva, la lista de invitados se borra** (el deber fiscal cubre importes, fechas y
código, no quién vino). ▶ **Efecto colateral: cierra por sí sola** la ficha del «techo tras
anonimizar» — si no se puede anonimizar con reservas vivas, ninguna reserva anonimizada se
reconcilia.

**D9 · «Pagado en el parque» deja de afirmarse.** Solo cambia **la palabra y el enfoque**: la
aritmética no se toca —`PAY-16` exige que el valor se reparta entre los cinco canales— y el cliente
deja de leer que pagó algo que nadie registró. ▶ El **registro real del cobro** queda como feature
aparte: en el parque siguen sumando consumiciones en su propio TPV, así que no se puede saber todo lo
que se le cobró, y no se le añade una acción al operador por un dato informativo.

### 18.4 · Los huecos MEDIDOS que estas decisiones cierran

**A · El primer cargo usa el catálogo de HOY, no el del día de la compra.** Medido: se reserva con
una diferencia de 5,00 € por cabeza, el parque sube el precio, y cuando el cliente rellena el
formulario semanas después se le cobran **10,00 €** por cabeza. ⚠️ **Muerde más que el caso espejo**:
no hace falta tocar tramos, basta con subir un precio — y el formulario se rellena siempre más tarde.
Lo cierra **D1**.

**B · Un cambio de tramo mueve lo vendido en las DOS direcciones.** Medido con el catálogo real
(Kids 1–6 a 11,00 € · Jump 7–99 a 15,00 €) sobre una fiesta Kids de 8 invitados de 6 años: bajar el
corte le crea **32,00 €**; subirlo se los quita. Lo cierra **D1**.

**C · Una edad sin producto CONGELA el dinero.** Medido: con 15,00 € escritos, un invitado de 0 años
—que el catálogo real no cubre— deja el veredicto «incompleto», y desde `#268` eso impide que el
cargo baje aunque el cliente corrija las demás edades. ▶ **D6 lo reencuadra y obliga a un cambio de
ingeniería**: una edad **sin producto** es un estado CONOCIDO, no una incógnita, así que debe dejar
de contar como «incompleto». **Solo una edad que FALTA puede congelar el importe.**

**D · El cliente no puede cambiar el número de invitados.** Medido: el post-form normaliza a
exactamente `quantity` fichas. `[DECIDIDO owner]` **no se construye ahora**: se le avisa de que llame
y lo ajusta el operador (**D7**). ⚠️ El owner lo dejó dicho como contradicción consciente; si algún
día se hace, **es dinero y AFORO**, no una pantalla.

**E · El operador no ve la diferencia por cabeza en el parque.** Medido: ni la hoja de sala ni la
pantalla de puerta consultan el veredicto (**cero** referencias). Tiene la edad y hace la cuenta de
memoria con el cliente delante. La visión exige que la vea.

**F · El operador no puede corregir la edad desde el panel.** Medido: `guest_data` tiene **un solo
escritor** y al panel solo le llega «copiar enlace». Puede abrir el enlace del cliente, pero el
rastro dirá que lo hizo **el cliente**.

**G · El guardián de solapes solo vive en el formulario.** Una semilla, un comando o un `update`
directo pueden crear tramos solapados. No revienta —el lector resuelve por el de menor edad— pero
«por construcción es imposible» solo es cierto si todo pasa por el panel.

### 18.5 · El plan, por tandas

⚠️ **El orden es de dependencia, no de gusto.** La T1 desbloquea la mitad de lo demás, y la T4 no se
empieza sin el informe de §19 aprobado.

| | Tanda | Cierra | ¿Núcleo de dinero? |
|---|---|---|---|
| **T0** | **El ojo del owner en navegador** — ▶ 🟦 **LAS CAPTURAS ESTÁN HECHAS** (2026-08-31, guion `t0.js` en `/root/e2e` del contenedor, 14 imágenes en `storage/app/t0-capturas/`): el aviso del catálogo `#283` **verificado en el ciclo real de Livewire** (avisa → re-guardar aplica → números distintos re-avisan → restaurado al dígito), el post-form (cargo 12 € → 8 €, congelado con edad borrada), los 3 correos (nace/cambia con «pasa de 12,00 € a 8,00 €»/desaparece), el desfase del panel, el huérfano y Gestionar sin la casilla. Pedidos sonda `T0-PRB01`/`T0-PRB02` (probe-card, franja 2026-09-15) y admin `e2e-panel-admin@jumpweb.test` quedan en la BD local para futuras sondas. **Queda SOLO el ojo: juzgar textos y claridad** | — | no |
| **T1** | **El SELLO** (D1·D2·D3): la reserva guarda tramos y precios de su familia al nacer. ▶ ✅ **EN EL ÁRBOL (2026-08-31, `#288`): diseño en §21, ejecución en §21.13.** `order_items.age_family_seal`; sello nuevo al cambiar de pack, el mismo re-preciado al cambiar de día (`[DECIDIDO owner]`, Q2); `unitFor` y el recibo desaparecen; `#283` retirado; dos huecos preexistentes cerrados de paso (día sin tarifa · cerrojo de `orphan_addons`). 13/13 mutaciones, verificadores sobre MySQL y sonda del hueco A en navegador. Queda el OJO del owner | A · B · el gemelo de la etiqueta · el huérfano | **sí** (nacimiento y edición de una reserva) |
| **T2** | **La edad sin producto** (D6) + que deje de congelar el dinero + **el disparador pasa a «solo guardado COMPLETO»** (§20.6, cambia la conducta de `#268` para los cargos) | C | no |
| **T3** | **El parque decide** (E·F·D7): la diferencia en hoja de sala y puerta, corregir la edad desde el panel auditado, y bajar del mínimo | E · F · D7 | **sí** (la edad mueve el suplemento) |
| **T4** | **El −X €** con el diseño CERRADO de **§20** (espejo acotado a puerta; guardas y mutaciones en §20.8) | el medio flujo que falta | **sí** |
| **T5** | **Las palabras** (D9) + anonimizar con reserva viva (D8) + ⚠️ el email de una reducción promete «procesaremos la devolución» y con la liquidación en parque promete de más (`#285`) + ❗ **hallazgo del T0, cazado por el OJO del owner** (2026-08-31): en el bloque del panel, la línea del veredicto («2 × Cumpleaños Jump · 9,00 € por invitado») **no dice que es la tarifa DE HOY** — pegada a «Suplemento aplicado: 8,00 €» se lee como contradicción hasta llegar a la frase del desfase. El owner mismo tuvo que preguntar, y esa es la prueba: el bloque no se explica solo. Arreglo: «hoy: 9,00 € por invitado» cuando difiera de lo escrito, o el aplicado primero. ⚠️ El «0,00 € por invitado» de la dirección barata NO se toca: se resuelve solo con la T4 (pasa a «Descuento: −8,00 €») | D8 · D9 | no (D9 es presentación) |
| **T6** | **El guardián fuera del formulario** (G) | G | no |

⚠️ **T1 hace innecesario el aviso de `#283`**, que se queda como red: avisar de un cambio que ya no
mueve nada no molesta, y sigue sirviendo para las reservas que aún no se hayan sellado.

⚠️ **T3 y T4 son las dos que tocan dinero de verdad.** Las dos exigen `VERIFY_CONC=1` con
`purchase:verify-oversell`, `redsys:verify-concurrency` y `mixed-party:verify-concurrency` sobre
MySQL real, y las dos entran por el `CRITICAL_RE`.

---

## 19. 📋 INFORME PARA FABLE · el −X €, sin romper el desglose (2026-08-31)

> ✅ **CONTESTADO (2026-08-31, `#285`): las cuatro preguntas de §19.6 tienen respuesta en §20** —
> (1) el crédito vive en una línea hija espejo, acotada a puerta; (2) sin puerta que absorber no se
> escribe: se enseña y se liquida en el parque; (3) el crédito solo se mueve al guardar el formulario
> COMPLETO, en ambas direcciones; (4) su frase se distingue de «Pendiente de devolución» y de una
> compensación. Este informe queda como registro de lo que se le pidió a Fable.
>
> **Autocontenido a propósito.** Quien lea esto no necesita el resto del documento. `[owner]`: «el
> desglose y los cálculos son muy sensibles por la flexibilidad y complejidad que tiene el sistema…
> no quiero romper el desglose, he iterado mucho sobre ello».

### 19.1 · El encargo, en una frase

Hoy, cuando un invitado corresponde a un pack **más caro**, se le cobra la diferencia. Cuando
corresponde a uno **más barato**, solo se le **avisa**. `[DECIDIDO owner, 2026-08-31]` **tiene que
descontar de verdad**, y aparecer en su desglose como aparece la subida.

### 19.2 · Lo que NO se puede romper (y por qué es difícil)

**`PAY-16` — el eje VALOR cierra:**
`valor = pagadoOnline + pendienteOnline + aCobrarPuerta + cobradoPuerta + compensado`, y **ningún
canal puede quedar negativo**. Los `max(0, …)` del dominio son cinturón, no soporte.

**`PAY-17` — el eje CAJA cierra:** `cobrado por web − devuelto = pagadoOnline + pendienteDevolución`.

⚠️⚠️ **Y las dos se evalúan EN EJECUCIÓN, no solo en tests** (`OrderLedger::$cuadra`, `#132`). Un
pedido cuyo desglose no cuadra **deja de enseñársele al cliente entero**: no es un número feo, es una
pantalla que desaparece.

### 19.3 · Por qué no vale ninguna forma «obvia» (medido)

- **Bajar el `unit_price` de la línea**: `order_items.unit_price` es **UNSIGNED**. No admite negativos.
- **Un ajuste `extra_due` negativo suelto**: medido sobre un pedido real —un `extra_due` sin línea
  **no cobra: MUEVE** dinero ya pagado. En negativo movería al revés, y sube «pagado online».
- **Restar del total**: el total de una reserva **no existe como número guardado**; se deriva.

### 19.4 · La forma que SÍ cierra, y su aritmética verificada

Una **línea de CRÉDITO** con la misma cascada que usan las bajadas de cantidad, que el editor ya
implementa (`creditReduction`, `gateCreditedCents`). Fiesta de 8 × 15,00 € = 120,00 €; dos invitados
corresponden a un pack de 11,00 € → crédito 8,00 €:

| Caso | valor | puerta | online | ¿cierra? |
|---|---|---|---|---|
| **A** · con señal (30 online, 90 puerta) | 112,00 | 82,00 | 30,00 | ✅ `30 + 82 = 112` |
| **B** · pagado 100 % online | 112,00 | 0,00 | 112,00 | ✅ y la deuda de 8,00 € aparece sola |
| **C** · señal pequeña (117 online, 3 puerta) | — | absorbe 3,00 y deja 5,00 de deuda | — | ✅ los dos canales positivos |

### 19.5 · ⚠️⚠️ La trampa medida que hunde el caso B

El caso B se apoyaba en que el «online original» de la línea de crédito fuera **0**. **Se comprobó
contra el código y era FALSO**: el respaldo de `Order::itemOriginalOnlineCents()` devuelve **lo
cobrado ahora**, o sea −8,00 €, y la deuda daba `max(0, −8 − 0 − (−8)) = 0`. **El descuento se perdía
en silencio, y justo en la configuración más común: un pack sin señal.**

▶ La salida existe y es la que usan las bajadas: la línea de crédito nace con un **marcador** propio.
**Si se implementa esto, se empieza por aquí.**

### 19.6 · Lo que Fable tiene que decidir

1. **¿Dónde vive el crédito?** ¿Una línea hija negativa con su marcador, o un tipo de ajuste nuevo?
   Hoy existen tres: `extra_due`, `deposit_remainder`, `collected_in_person`.
2. **¿Qué pasa si no hay puerta que absorber y tampoco se pagó online** (pedido pendiente)?
3. **¿El crédito caduca?** Si el cliente sube la edad otra vez, el −X € tiene que deshacerse igual
   que el +X € — y con el mismo criterio de `#268`: una ausencia de datos no puede crearlo ni
   destruirlo.
4. **¿Cómo se lee en el desglose?** Tiene que distinguirse de «pendiente de devolución», que es deuda
   real del parque, y de una compensación.

### 19.7 · El contexto que hace falta para no repetir lo ya aprendido

- El importe escrito **hereda el unitario comunicado** (`unitFor`, `#270`): el crédito también.
- Con el veredicto **incompleto**, lo escrito puede crecer pero nunca encoger (`#268`). Un crédito es
  dinero a favor del cliente: **decidir si esa regla se aplica igual o al revés**.
- `MixedPartySurcharge` está en el `CRITICAL_RE`: tocarlo obliga a `purchase:verify-oversell`,
  `redsys:verify-concurrency` y `mixed-party:verify-concurrency` sobre MySQL real.
- Toda guarda nueva tiene que **verse ROJA** con el fallo real puesto antes de darla por buena, y en
  esta zona nacen ciegas sin `nextRequest()`: el lector va en `scoped` y memoiza.

---

## 20. ✅ EL −X €, DISEÑADO Y CERRADO CON EL OWNER (2026-08-31, `DECISIONES #285`)

> **Esta sección SUSTITUYE a §16 como diseño vigente del descuento** y contesta las cuatro preguntas
> que §19 dejó abiertas. §16 no se tira: pasa a ser la pieza de la **fase 3** (ver §20.2), con sus
> condiciones de despertar escritas. Iterado entre el owner y Fable, sin código.

### 20.1 · El diseño en una frase

**El descuento es el ESPEJO del suplemento, acotado al dinero de puerta**: una línea hija con
subtotal negativo y su `extra_due` negativo del mismo importe — el patrón exacto de la línea de
cargo, con el signo cambiado. `crédito_escrito = min(crédito_derivado, cubos de puerta de la
reserva)`. El exceso no se escribe: se enseña.

### 20.2 · La hoja de ruta de cobro del owner, y qué necesita cada fase

`[DECIDIDO owner, 2026-08-31]` el cobro evoluciona en TRES fases, y cada una tiene su respuesta:

| Fase | El dinero | El −X € |
|---|---|---|
| **1 · Hoy**: señal online + resto en parque | Puerta grande | Automático. Medido: cobertura ~3× (90 € de puerta vs 32 € de crédito máximo con el catálogo real) |
| **2 · Pack entero online, gestiones en parque** | Puerta = solo gestiones | El exceso sobre la puerta es la línea **«a tu favor — se te devuelve en el parque»** (§20.5) |
| **3 · Todo online, también las gestiones** | Sin puerta | ▶ **Aquí despierta §16.** Pero la fase 3 es una feature propia con DOS mitades simétricas: «cobro online post-reserva» —**que hoy no existe para NINGUNA dirección: el +X € tampoco puede cobrarse online**— y «reembolso online post-reserva», cuya maquinaria parcial ya existe. El −X € online viaja en esa ola, no antes |

### 20.3 · La aritmética, trazada instrucción a instrucción sobre el código real

El suplemento de hoy: línea +7 € con `extra_due` +7 € → `collected = max(0, 7−7) = 0`, valor +7,
puerta +7. **Los dos canales reparten el valor de la línea** (§8.3). El espejo:

| Línea de crédito: subtotal −8 € + `extra_due` −8 € | Trazado |
|---|---|
| `valor` | −8 ✓ (`ReservationFinancials::make`, `valor += chargedSubtotalCents`) |
| `collected = max(0, −8 − (−8))` | **= 0 — el clamp de `itemCollectedCents` NUNCA muerde** |
| `gateNeto` con señal de 90 € | 90 − 8 = 82 ✓ (los cubos se netean POR RESERVA con signo, `:123`) |
| Identidad `PAY-16` | 112 = 30 online + 82 puerta ✓ |

▶ **Por qué esto es estrictamente mejor que §16**: los dos elementos de riesgo ALTO de aquel diseño
—relajar el clamp (§16.6·4) y el marcador de reconstrucción (§16.5.bis)— existían **solo para el
caso B** (pagado 100 % online). Con el tope de cobertura, ese caso no se escribe: se enseña. Cero
mecánica contable nueva. El `extra_due` negativo es un camino trillado (`applyGateCredit` lo persiste
así desde las bajadas) y la marca de línea-crédito es UN helper (`chargedSubtotalCents`, §16.6·1).

⚠️ **Lo que SÍ comparte con §16 y sigue vigente de aquel diseño**: la cota demostrable (el crédito es
`Σ (precio_reservado − precio_destino) × invitados` con destino > 0, así que nunca deja una reserva
en valor negativo) y la doctrina de que los tres casos entran como escenarios del guardián de once
ANTES del reconciliador.

### 20.4 · Dónde se ve, y cuándo se mueven los números (`[DECIDIDO owner]`)

| Situación | Línea que ve el cliente | ¿Cambian los totales? |
|---|---|---|
| Descuento automático (cabe en puerta) | «Descuento por N invitados que corresponden a “Kids”: −8,00 €» · «A pagar en el parque: 82,00 €» | **Sí** — en la puerta le van a pedir 82; enseñar 90 sería mentirle |
| Exceso (fase 2) | «8,00 € **a tu favor** — se te devuelven en el parque», línea propia | **No** — intactos hasta que el parque liquide; entonces aparece como DEVUELTO (el registro del hecho, no un retoque) |

⚠️ La frase del exceso se distingue a propósito de «Pendiente de devolución» (deuda bancaria real,
`PAY-17`) y de una compensación: son tres cosas y se leen distinto.

### 20.5 · El circuito del exceso en fase 2 — VERIFICADO con piezas que ya existen

El cliente viene al parque de todas formas — es su fiesta. El circuito: (1) el desglose enseña la
línea «a tu favor»; (2) el operador se lo da en mano o lo descuenta de su TPV; (3) si quiere
constancia, registra el **reembolso manual** que ya existe — `PaymentRefund::MODE_MANUAL`
(record-only), con **motivo OBLIGATORIO** («sin él el desglose no puede decirle al cliente si sigue
debiendo ese importe», `ViewOrder`) — y ese registro cae en el canal **`compensado`**
(`reservationCompensatedCents`), que existe exactamente para «dinero devuelto sin que desapareciera
producto». **Las dos identidades siguen cerrando.** Reembolso bancario parcial: no se necesita en
las fases 1–2.

### 20.6 · Cuándo se mueve el dinero: SOLO al guardar el formulario COMPLETO (`[DECIDIDO owner]`)

**Una sola regla para las dos direcciones**: el dinero se recalcula en cada guardado con TODAS las
edades rellenas; un guardado incompleto **no mueve nada en ninguna dirección** — congela lo escrito
y lo dice. Mientras se rellena, solo avisos (la pastilla por niño de `#247` y la aritmética de
`#246`, que ya existen).

▶ La justificación del lado del crédito cabe en una frase: *para cobrarte de más basta UNA ficha
(cada cargo es independiente); para devolverte dinero hacen falta TODAS (el descuento es la cuenta
de la fiesta entera, y descontar con la foto a medias es la puerta del abuso: edad barata + resto en
blanco = dinero)*.

⚠️⚠️ **Esto CAMBIA una conducta construida**: hoy el cargo +X crece con guardados parciales
(`#268` lo permite a propósito). Pasa a «solo al completar» por decisión de producto — más coherente
con «lo escrito es lo que se comunicó»— y la protección de `#268` QUEDA como red: borrar edades
después de un completo sigue sin destruir nada. **El cambio de disparador va en la T2** (que ya
redefine «completo» por D6), no en la T4.

### 20.7 · El estado COMPLETO no se bloquea (`[DECIDIDO owner]`)

Tres razones: en ese formulario viven las **alergias** (el dato más barato de corregir no puede
exigir un teléfono); ya se lo prometimos («puedes seguir editando hasta el día del evento», email);
y la ventana correcta ya existe y está medida — se cierra sola al terminar la fiesta, en el mismo
instante en que el dinero se da por resuelto (`#244`).

| El cliente… | Pasa esto |
|---|---|
| Guarda completo | Se recalcula el desglose, arriba o abajo |
| Cambia una edad y guarda (sigue completo) | Se recalcula otra vez — cada guardado completo es una foto válida |
| Borra una edad y guarda (incompleto) | Lo escrito se CONGELA y el formulario lo dice |
| La fiesta termina | Solo lectura, como hoy |

### 20.8 · Dependencias y validación (para la T4)

- **T1 antes**: el crédito deriva de los precios SELLADOS (hoy `savingsCents` lee el catálogo vivo);
  y el sello debe SUBSUMIR el recibo de `#270` — dos fuentes de verdad para lo mismo es el defecto.
- **T2 antes**: redefine «completo» (D6: una edad sin producto es estado CONOCIDO) y mueve el
  disparador a «solo guardado completo» (§20.6).
- **Guardas de la T4, con su mutación obligatoria**: los tres casos A/B/C en el guardián de once
  ANTES del reconciliador · quitar la CASCADA pone en rojo el caso A · quitar el TOPE de cobertura
  pone en rojo el caso B (la reserva quedaría con puerta negativa y el cinturón `max(0,…)` mordería,
  que el guardián prohíbe) · el crédito con veredicto incompleto NO se mueve, en ninguna dirección ·
  y `mixed-party:verify-concurrency` gana el escenario del crédito (N guardados completos
  simultáneos → UNA línea).
- ~~⚠️ Las guardas de esta zona nacen ciegas sin `nextRequest()` (lector `scoped`).~~ **Ya no**
  (T1, §21.5): el lector es sin estado y deriva del sello de la fila.

### 20.9 · Registro de la iteración (por qué el diseño cambió dos veces)

1. §16 (2026-08-29): línea de crédito + cascada + clamp relajado + marcador. Correcto pero con dos
   piezas de riesgo ALTO que solo servían al caso «pagado 100 % online».
2. Fable (2026-08-31): el espejo acotado a puerta — trazado que el clamp nunca muerde y que el
   `extra_due` negativo ya es camino trillado. Propuso además una regla asimétrica de reconciliación
   (crédito solo con veredicto completo).
3. **El owner la simplificó y mejoró**: una sola regla simétrica —dinero solo al guardar completo,
   para las DOS direcciones— y la hoja de ruta de tres fases que recoloca §16 como pieza de la
   fase 3 en vez de «por si acaso».

---

## 21. 🟦 T1 · EL SELLO — diseño fino, ANTES de una línea de código (2026-08-31)

> **Si vas a construir la T1, lee §18 (la visión) y ESTE apartado.** El owner pidió ir «paso a
> paso»: diseño fino antes de código, con las tres preguntas que dejó identificadas —dónde vive la
> copia · cómo se RE-sella al cambiar la fecha o el producto · cómo SUBSUME el recibo de `#270`—.
> Las tres están contestadas aquí **contra el código real** (cada afirmación cita el sitio donde se
> midió), y lo que sí es decisión de producto va marcado `[PENDIENTE: owner]` en §21.8.

### 21.1 · Lo que el sello ES, y lo que no

El sello es **la copia de las condiciones de la familia por edad con las que se vendió una fiesta**:
qué packs forman la familia, qué tramo cubre cada uno y **cuánto costaba cada uno el día de la
fiesta**, resueltos en el momento de la venta. Es lo que D1 dice con las palabras del owner: «el
precio viejo solo existe dentro de las reservas que lo llevan».

**No es**: ni un histórico de precios (D1 lo hace innecesario), ni un veredicto (el veredicto sigue
siendo DERIVADO en cada lectura —`GuestAgeMix` no se persiste, y su docblock explica por qué—, lo que
cambia es **de dónde deriva**: del sello y no del catálogo vivo), ni una copia del esquema del
post-form (§21.7 dice por qué se deja fuera).

### 21.2 · Dónde vive: `order_items.age_family_seal` — y las tres alternativas, medidas

| Alternativa | Por qué NO |
|---|---|
| `order_items.event_data` | Es lo que contestó el CLIENTE y `RGPD-01` lo vacía al anonimizar (`User::anonymize()` pone `guest_data` y `event_data` a `null`). §12.4 ya lo descartó para la marca del suplemento por lo mismo. Un sello que muere con el olvido borraría **las condiciones del parque**, no los datos del cliente |
| `order_adjustments.context` (donde vive el recibo de `#270`) | El ajuste es 1:1 con la LÍNEA de suplemento, y esa línea **solo existe cuando ya se ha escrito dinero**. El caso espejo es exactamente una reserva **sin** línea (Jump, 8 invitados de 12 años, cero cargo) a la que un cambio de tramos le crea 40,00 €: ahí no hay ningún `context` donde haber sellado nada. El sello tiene que existir desde el NACIMIENTO, con o sin suplemento |
| Tabla nueva 1:1 | Nada la consulta por sus columnas —se lee siempre desde su reserva y entera— y una tabla aparte añade un `JOIN` a las cinco superficies que ya cargan `ticketType`+`slot` por fila (`MixedPartyLabelSurfacesTest` vigila justo ese coste). Un documento que nace y muere con su fila es una columna JSON |

▶ **Columna `age_family_seal` (`json`, `nullable`) en `order_items`**, junto a `guest_data` y
`guest_form_completed_at`. Migración nueva (`docs-check` pasa de 88 a 89 y `README.md` lo declara).
Sin PII: solo ids, nombres de producto, tramos y precios de catálogo — por eso `anonymize()` **no
la toca**, y hay guarda de que la conserva (§21.10·J).

### 21.3 · Qué contiene (documento v1)

```json
{
  "v": 1,
  "family": "cumple",
  "booked_type_id": 12,
  "priced_on": "2026-09-15",
  "sealed_at": "2026-08-31T13:05:00+00:00",
  "members": [
    {"type_id": 11, "name": {"es": "Cumpleaños Kids", "en": "…", "fr": "…"}, "age_min": 1, "age_max": 6, "price_cents": 1100},
    {"type_id": 12, "name": {"es": "Cumpleaños Jump", "en": "…", "fr": "…"}, "age_min": 7, "age_max": null, "price_cents": 1500}
  ]
}
```

- `members` son **todos** los packs de la familia (`type = pack` y misma `guest_age_family`, sin
  filtrar por `is_sellable`/`is_active`: la familia es una clasificación, no una oferta — el criterio
  que `GuestAgeMixReader::family()` ya aplica), con su tramo y su precio **para `priced_on`** resuelto
  por `RateResolver::priceCents()` — la MISMA fuente con la que `OrderCreator::createPendingOrder()` fija el
  `unit_price` de la propia reserva. `price_cents: null` es respuesta legítima («ese día no tiene
  tarifa») y se sella como tal.
- `name` se guarda como el array traducible entero, no resuelto: es el nombre **que se le dijo**, la
  etiqueta por niño y la frase del cliente lo leen en su idioma, y así ni un renombrado ni un borrado
  del producto cambian lo que ya se comunicó.
- `family: null` es un sello VÁLIDO: «este pack se vendió SIN condiciones por edad». Se escribe
  igual (§21.5 explica por qué esa afirmación vale dinero).
- `booked_type_id` y `priced_on` son **el recibo de `#270`, ahora en su sitio** (§21.6): los dos
  hechos bajo los que se calculó todo lo demás, y lo que permite detectar un sello CADUCADO.
- No se sella el esquema del post-form (la clave del campo de edad, `guestAgeFieldKey()`): sellar
  la clave sin sellar el esquema no compra nada, porque `sanitizeGuestData()` descarta las claves
  que el esquema vigente no declara. Si el parque retira el campo de edad, todas las fichas pasan a
  «sin edad», el veredicto queda incompleto y el importe se congela — que es la conducta correcta
  para «falta el dato», no un hueco.

### 21.4 · Cuándo se escribe, cuándo se RE-escribe — y cuándo NO (medido en los tres puntos)

| Momento | Dónde (todo dentro de la transacción y bajo el lock que ya existe) | Sello |
|---|---|---|
| **Nace** (web, API y alta manual del panel: las tres pasan por `OrderCreator::createPendingOrder`, medido — la llaman `CheckoutOrchestrator` y `ManualOrderFulfiller`) | `OrderCreator::createPendingOrder()`, en el mismo `create()` de la línea principal | Familia del pack, tramos y precios **del día de la franja** |
| **Cambia de FECHA** (`PAY-18`: el precio sigue al día) | `OrderItemEditor::changeSlot` (y `edit()` sin cambio de producto), en el MISMO `forceFill` que mueve `slot_id` | **RE-PRECIO del sello** `[DECIDIDO owner]` (Q2, §21.8): la familia y los TRAMOS de la compra se conservan; cada miembro toma el precio **del día destino**; `priced_on` pasa al día nuevo. Sin sello previo, nada (el silencio persiste) |
| **Cambia de PRODUCTO** (y/o de fecha por el mismo modal) | `OrderItemEditor::edit`, en el MISMO `forceFill` de `ticket_type_id`/`slot_id` | **Sello NUEVO** con el pack nuevo, su familia de hoy y los precios del día efectivo («solo cambia de condiciones lo que cambia de producto»). Si el pack nuevo no participa: `family: null` |
| Cambia la **cantidad**, las **edades** o los **complementos** | — | **No** se toca: no cambia de producto |
| Cambia el **catálogo** (precio, tramo, familia, nombre, un hermano nuevo) | — | **No** (D2) |

⚠️⚠️ **El re-sello va en la MISMA transacción que la mutación**, no en el post-commit donde viven
las dos llamadas a `reconcile()`: si fuera después, habría una ventana en la que la reserva ya está en
el día nuevo con el sello del día viejo — y la reconciliación post-commit, que relee la fila
bloqueada, derivaría de un sello caducado. Con el sello dentro de la transacción, cuando
`reconcile()` corre ya ve las condiciones nuevas.

▶ **Invariante interna del sello** (la que hace detectable un sello caducado): tras cualquiera de
los tres puntos, `seal.booked_type_id === item.ticket_type_id` y `seal.priced_on ===
item.slot.date`. Y al NACER, además, `seal.members[booked].price_cents === item.unit_price`: los
dos salen de `RateResolver` para el mismo día, así que si divergen es que alguien tarificó por otra
fuente (guarda §21.10·A).

### 21.5 · Cómo lee el veredicto: el lector deja de mirar el catálogo

`GuestAgeMixReader::walk()` toma hoy TODO del catálogo vivo: la participación
(`participatesInAgeFamily()`), la familia (una consulta a `ticket_types`), los tramos
(`coversGuestAge()` sobre cada `TicketType`) y los precios (`RateResolver`, memoizados). Pasa a
tomarlo **del sello de la reserva**, y del catálogo vivo solo lo que NO es condición de venta:

| Dato | Hoy | Con el sello |
|---|---|---|
| ¿Participa? | `ticketType->participatesInAgeFamily()` | `seal !== null && seal.family !== null` |
| La familia y sus tramos | consulta + `coversGuestAge()` | `seal.members`, ordenados por `age_min` y luego `type_id` (la misma regla de desempate de hoy) |
| Precios del día | `RateResolver`, memo `«type|fecha»` | `seal.members[].price_cents` |
| La clave del campo de edad y el saneo de fichas | `ticketType->guestAgeFieldKey()` / `sanitizeGuestData()` | **igual** (es esquema, no condición) |

Tres estados del sello, y **los tres significan cosas distintas**:

| Estado | Veredicto | ¿Puede RETIRAR dinero escrito? (`derivationGoverns`) |
|---|---|---|
| Sello con familia | derivado del sello | sí, si está completo (como hoy) |
| Sello con `family: null` | `notApplicable(sealed: true)` — **una AFIRMACIÓN**: «se vendió sin condiciones» | **sí**: si el operador cambia el pack a uno sin familia, la línea de suplemento se cancela en la reconciliación post-commit. Hoy ese caso deja la línea HUÉRFANA (`applies=false` se lee como silencio) |
| Sin sello (columna `null`) o sello CADUCADO (`booked_type_id`/`priced_on` ≠ los de la fila) | `notApplicable(sealed: false)` — un SILENCIO | **no** (la abstención de `#268`, intacta), y **tampoco crea**: no se escribe dinero sobre condiciones que no se conocen. El caducado se enseña en ROJO en la ficha (§21.9·6) |

▶ El lector queda **sin estado**: ni familias ni precios que memoizar, porque ambos viajan en la
fila que ya tiene cargada. Deja de ser `scoped` (`BookingServiceProvider::register()`) y **`nextRequest()`
deja de hacer falta** en las guardas — la trampa de §17.6·2 y de §20.8 se cierra por construcción,
no por disciplina. `pretendFamilyIs()` (solo lo usa `MixedPartyBandImpact`) se va con Q1.

`GuestAgeMix` gana dos datos: `sealed` (el «no aplica» viene de un sello, así que afirma) y
`staleSeal` (el sello no corresponde a la fila). `guestRegimes()` (la pastilla por niño de `#247`)
lee los nombres del sello.

### 21.6 · Cómo SUBSUME el recibo de `#270` — la pregunta del owner, contestada

El recibo (`MixedPartySurcharge::unitFor()`) existía para una sola cosa: distinguir «el
parque tocó una tarifa» (no puede mover lo comunicado) de «esta reserva cambió de pack o de día»
(sí). Lo hacía guardando dos hechos en el `context` del ajuste y comparándolos con la fila en cada
pasada, **y heredando el unitario escrito cuando coincidían**.

Con el sello, esa distinción **la hace la escritura, no la lectura**: el sello solo se re-escribe
cuando cambia el pack o el día (§21.4), así que el unitario derivado del sello **es** el comunicado
mientras no se muevan, y es el del día nuevo cuando se mueven. No hay nada que heredar:

- `unitFor()` **desaparece** — `targetState()` usa el derivado, siempre.
- Del `context.mixed_party` del ajuste salen `booked_type_id` y `priced_on` (viven en el sello, una
  vez); se quedan `target_type_id`, `target_name`, `guests` y `unit_cents`, que son lo que el
  cliente lee en su desglose (`OrderAdjustment::breakdownLabel`).
- La rama «sella en su primera pasada sin tocar un céntimo» de `apply()` se va con él.
- **D4 («14,00 €, no 24,00 €») se cumple por construcción**: un invitado declarado después de una
  subida entra a `precio_sellado(destino) − precio_sellado(reservado)`, que es lo que se comunicó.
  El caso `test_a_guest_added_after_a_price_rise_pays_the_communicated_price` se queda como control.
- **El hueco A de §18.4 se cierra por construcción**: el primer cargo sale del sello, que es del
  día de la compra, no del catálogo del día en que se rellena el formulario.

⚠️ Y esto es lo que contesta «dos fuentes de verdad para lo mismo es el defecto»: **no hay un
`unitFor` que lea el sello si existe y el recibo si no**. Hay UNA fuente (el sello) y una regla de
cuándo se re-escribe. Las líneas sin sello no tienen fuente y por eso no se tocan (§21.5).

### 21.7 · Lo que el sello CIERRA, y lo que NO

Cierra: **A** (primer cargo con catálogo de hoy) · **B** (un cambio de tramo mueve lo vendido en las
dos direcciones, incluida la ampliación que DESTRUYE un cargo, §17.5) · el **gemelo de la etiqueta**
(la etiqueta y el cargo salen del mismo sello) · el **huérfano por catálogo** (retirar la familia en
el catálogo ya no deja un cargo sin veredicto: el veredicto sigue saliendo del sello) · y el
huérfano por **cambio de producto** (§21.5, la línea se cancela).

No cierra, y es scope de otras tandas: **C** (una edad sin producto congela — T2, D6) · el
disparador «solo al guardar completo» (T2, §20.6) · **E/F/D7** (T3) · el −X € (T4) · las palabras
(T5) · el guardián de solapes fuera del formulario (T6, y con el sello **deja de mover dinero**: un
solape solo puede afectar a reservas que nazcan después).

### 21.8 · Consecuencias que hay que decidir a sabiendas — ✅ `[DECIDIDO owner, 2026-08-31]`

> **Las tres están decididas (2026-08-31, tarde), y la Q2 cambió el diseño**: el owner contestó
> con una objeción («si compra Kids 1–6 y al día siguiente cambiamos el tramo a 1–5, su fiesta
> cambia entera: eso no podemos hacerlo, mantenemos sus condiciones»). Ese caso es D2 y el sello lo
> cierra sin pregunta; lo que Q2 preguntaba era el OTRO —el operador mueve la fiesta de DÍA— y la
> respuesta fiel a su regla es **(b), no (a)**: **los TRAMOS de la compra se conservan y solo los
> PRECIOS pasan a los del día destino**, que es lo que `PAY-18` ya hace con el precio de la propia
> fiesta. Mi recomendación (a) leía de más «la fecha es un producto», y **(b) no son dos fuentes de
> verdad**: es el MISMO sello con sus precios refrescados para la fecha nueva. §21.4 queda así:
> **cambio de FECHA = re-precio del sello; cambio de PRODUCTO = sello nuevo**.
> ▶ **Q1: (b)**, retirar el mecanismo y dejar la frase. ▶ **Q3: D2 literal**, se deja y se sabe.
> ▶ **Y de revisar el diseño con la objeción delante salió un HUECO** (§21.10·N): mover una fiesta a
> un día en que uno de los packs **no tiene tarifa** —el editor no lo bloquea: `validateNewSlot` no
> mira precios y `ItemEditPricing::computeEditPricing()` conserva el unitario histórico— deja la diferencia sin
> calcular, y la lógica de hoy **cancelaría el suplemento escrito** (destino sin precio → no llega a
> ser línea → veredicto «completo» → retira lo que hay). Con el sello ese día queda `price_cents:
> null` y la regla es la de `#268`: **no poder tarificar es una AUSENCIA, no una corrección** — el
> reconciliador se abstiene (ni retira ni crea) y la ficha dice que falta el precio.

**Q1 · El aviso de `#283` se queda sin nada que avisar.** `MixedPartyBandImpact` mide «cuánto
dinero movería este cambio de tramos en fiestas ya vendidas». Con el sello la respuesta es **cero
por construcción** (D2) — y además mide sembrando una familia inventada al lector
(`pretendFamilyIs`), que con el sello ya no lee familias. Dejarlo «como red» (§18.5) no es posible
sin fingir: sería un aviso que nunca salta. Salidas: **(a) retirarlo entero** —`MixedPartyBandImpact`,
`InteractsWithCatalogForm::warnAboutSoldParties()` con su firma de doble guardado, las 2
claves de idioma × 2 y **7 casos** de `CatalogGuestAgeFamilyTest`—, coste: cero
funcional, 1 hora; **(b) sustituirlo por un texto fijo** bajo los tramos («las fiestas ya vendidas
conservan sus condiciones; esto solo afecta a las siguientes»), coste: (a) + una línea. ▶
**Recomendación: (b)**: retirar el mecanismo y dejar la frase, que es la regla de D1 dicha donde el
operador la va a necesitar.

**Q2 · Al cambiar la FECHA, ¿se re-sellan también los TRAMOS o solo los precios?** «La fecha es un
producto» (owner, `#284`) dice que cambiarla es empezar con las condiciones de hoy — y `PAY-18` ya
re-tarifica el propio ítem con el catálogo del día destino. Salidas: **(a) todo** (familia, tramos y
precios de hoy, para el día destino): coherente con la regla y con `PAY-18`; consecuencia: si el
parque bajó el corte de 6 a 5 después de la compra, una fiesta Kids con niños de 6 que cambie de
fecha pasa a ser mixta; **(b) solo precios** (tramos de la compra, precios del día destino): un
híbrido que ninguna regla del owner describe, y la única forma de reconstruirlo es leer el sello
viejo al re-sellar. ▶ **Recomendación: (a).**

**Q3 · Un pack HERMANO creado DESPUÉS de la venta** (p. ej. un «Teens 13–17» nuevo) **no existe para
las fiestas ya vendidas**: su familia sellada no lo contiene, así que un invitado de 14 años en una
fiesta Jump 7–12 vendida antes sale «sin producto» (D6: se le explica y llama; el operador decide,
T3). Es D2 al pie de la letra. La alternativa —mezclar en la lectura los hermanos vivos que no están
en el sello— vuelve a ser **dos fuentes de verdad**. ▶ **Recomendación: dejarlo así y saberlo.** Se
pregunta porque es la única consecuencia de D2 que el owner no vio en la sesión del `#284`.

Lo que **no** se pregunta porque se deriva de decisiones ya tomadas: que cambiar el pack a uno sin
familia retira el suplemento (§21.5); que las reservas locales y de staging sin sello dejan de ser
mixtas hasta que se re-sellen (D3: en producción no existe ninguna; §21.12 para las sondas).

### 21.9 · Lo que se toca, en orden de riesgo

1. **Migración** + accesor `OrderItem::ageFamilySeal(): ?AgeFamilySeal` (VO inmutable parseado
   del JSON, con `members` como lista de `SealedRegime`) — puro, sin efectos.
2. **`AgeFamilySealer`** (`Booking\Services`): `seal(OrderItem $item, Carbon $pricedOn): void`,
   compone el documento desde `TicketType` + `RateResolver` y lo escribe en la fila. **Entra en el
   `CRITICAL_RE` y en `CriticalPathGateTest`**: no escribe dinero, pero escribe **las condiciones que
   deciden el dinero** dentro de los locks de `OrderCreator` y `OrderItemEditor` — el mismo motivo por
   el que `PackAvailability` está («el gate vigilaba a quien LLAMA y no a quien CUENTA»).
3. **`OrderCreator`** sella la línea principal de cada pack (🔒 `CRITICAL_RE`).
4. **`GuestAgeMixReader`** deriva del sello; `GuestAgeMix` gana `sealed`/`staleSeal`; el binding
   deja de ser `scoped`.
5. **`MixedPartySurcharge`**: sin `unitFor`, `context` sin recibo, `derivationGoverns` con `sealed`
   (🔒 `CRITICAL_RE`).
6. **`OrderItemEditor`**: re-sello en `edit()` y `changeSlot()` dentro de la transacción (🔒
   `CRITICAL_RE`). La ficha del pedido (`items-list.blade.php` + `lang/{es,zh_CN}/admin.php`): rama
   «sello caducado» en rojo, «sin sello» en ámbar (hoy `orphaned` habla de «ya no pertenece a ninguna
   familia», que con el sello es otra cosa), y `drift` deja de decir «hoy correspondería»: lo derivado
   sale de las condiciones de la reserva, no de hoy — con esto el hallazgo del T0 anotado en T5 se
   disuelve (el importe del veredicto y el aplicado salen del mismo sello y ya no se contradicen).
7. **`#283`** según Q1.
8. **`mixed-party:verify-concurrency`** siembra la reserva con sello en `seed()`; sin él, con el diseño
   nuevo el escenario no escribiría nada y el verificador pasaría en verde sin verificar.
9. **Tests**: los cinco ficheros que crean reservas a mano (`items()->create`, sin factory) sellan
   por `AgeFamilySealer` en su helper; los casos del recibo se re-apuntan (§21.10·L); los de `#283`
   según Q1.
10. **Doc**: `INVARIANTES` `PAY-19` reescrita (el LÍMITE se cierra) · `MODELO-DATOS` §1 · `README`
    (89 migraciones) · `GLOSARIO` («sello») · `DEUDA` (las dos fichas pasan a cerradas) · fila de
    `CLAUDE.md` · `DECISIONES #288` · `ESTADO` · `00-REFACTOR`.

### 21.10 · Guardas, con la mutación que las valida

| | Guarda | Mutación que la pone en rojo |
|---|---|---|
| A | `OrderCreator` sella una fiesta de familia con miembros, tramos, precios del día y `booked_type_id`/`priced_on`; la base del sello **= `unit_price`**; una entrada o un pack sin familia nace con `family: null` | quitar el sello de `OrderCreator` |
| B | **Hueco A**: comprar → subir el precio de Jump → rellenar el formulario → el cargo es la diferencia **del día de la compra** (5,00 €, no 10,00 €) | el lector lee el catálogo vivo |
| C | **Caso espejo**: Jump 8×12 años, cero cargo → tramos reordenados → el cliente corrige un nombre → sigue en 0 | ídem |
| D | **Ampliar un tramo no destruye un cargo** (§17.5·b): 40,00 € escritos → el corte baja a 3 → edición de nombre → 40,00 € | ídem |
| E | **La etiqueta sigue al sello**: con los tramos del catálogo devueltos a su sitio, `isMixedParty()` y `guestRegimes()` dicen lo mismo que el cargo | el lector lee el catálogo vivo para la etiqueta |
| F | **Cambiar de día RE-sella** (`PAY-18`, control de `#270` adaptado): precios del día destino → cargo nuevo, `seal.priced_on` = día destino | `changeSlot` sin re-sello |
| G | **Cambiar de pack RE-sella**: Kids→Jump dentro de la familia (el sello cambia de `booked_type_id`); Kids→pack SIN familia **cancela** la línea de suplemento (`sealed: true` gobierna) | `edit` sin re-sello · `derivationGoverns` ignorando `sealed` |
| H | Subir la cantidad **no** re-sella (`sealed_at` intacto) | — (control) |
| I | **Sello caducado** (`ticket_type_id` movido por SQL a mano): veredicto silencioso, nada escrito, la ficha lo dice en rojo | quitar la comprobación de `booked_type_id`/`priced_on` |
| J | `anonymize()` **conserva** el sello (y sigue vaciando `guest_data`/`event_data`) | `anonymize()` vacía el sello |
| K | D4 («14, no 24»): el caso existente, sin cambios, como control | — |
| L | Los dos casos del recibo: `…records_the_two_facts…` pasa a aseverarlos **en el sello** y que el `context` del ajuste **ya no los lleva**; `…written_before_the_receipt_is_sealed…` se retira (ya no hay «primera pasada») y lo sustituye «una línea sin sello no se toca» | — |
| M | `CriticalPathGateTest`: `AgeFamilySealer` en `CRITICAL_FILES` y en el patrón del hook | quitarlo del hook |
| N | **Un día sin tarifa NO retira el suplemento** (el hueco de §21.8): fiesta con 7,00 € escritos movida a un día en que Jump no tiene precio → el sello queda con `price_cents: null` → lo escrito **se conserva** y no se anuncia nada | `derivationGoverns` sin la condición «tarificable» |
| O | **Re-precio al mover de día conserva los tramos** (Q2): el corte de Kids baja a 5 en el catálogo DESPUÉS de la compra; la fiesta (niños de 6) se mueve de día → sigue siendo Kids 1–6 (no se vuelve mixta) y la diferencia por niño mayor es la del día nuevo | re-sellar desde el catálogo al mover de día |

Y las tres ausencias de `#268` (vaciar edades · anonimizar · retirar la familia) se quedan como están:
la tercera pasa de «silencio» a «el sello sigue diciendo lo mismo» —el cargo no se mueve por otra
razón, mejor— y el caso se conserva porque sigue protegiendo el mismo dinero.

### 21.11 · Verificación empírica (antes del ✅)

- Suite + Pint. ▶ `VERIFY_CONC=1` al empujar, tras `purchase:verify-oversell` (se toca
  `OrderCreator`: los cinco escenarios), `redsys:verify-concurrency` y
  `mixed-party:verify-concurrency` sobre MySQL real — y el último **visto FALLAR** con el sello
  quitado de la siembra (`#283` enseñó que un verificador que no siembra la condición verifica nada).
- Sonda en navegador del hueco A sobre el ciclo REAL (guion `t0.js` ampliado, `Notification::fake()`
  o asumir que ensucia Mailpit — la lección del T0): comprar con 5,00 € de diferencia, subir Jump en
  el catálogo, rellenar el post-form y leer **5,00 €** en la frase del cliente y en la ficha.

### 21.12 · Las reservas que ya existen (local y staging)

D3: **0 LIVE · 0 PRODUCCIÓN**, no hay nada que rellenar al desplegar y no se construye relleno. Las
sondas locales (`T0-PRB01`/`T0-PRB02`, `R-BEEL3E`) y las de staging nacieron sin sello y con el
diseño nuevo dejan de ser mixtas (silencio: su cargo escrito no se mueve, la ficha dice «sin
sello»). Para seguir usándolas como sondas se re-sellan **a mano, con el catálogo de hoy**, con
`AgeFamilySealer` desde `tinker` — es un dato de un entorno de pruebas, no un mecanismo del
producto, y así queda dicho para que nadie lo convierta en comando. ▶ Hecho en local el
2026-08-31: **6 reservas vivas re-selladas, 3 con familia** (`R-BEEL3E`, `T0-PRB01`, `T0-PRB02`);
las otras tres son packs sin familia y llevan el sello «sin condiciones». Staging queda para cuando
se despliegue (misma receta, `docs/ENTORNOS.md`).

### 21.13 · ✅ LO EJECUTADO (2026-08-31, `DECISIONES #288`)

**En el árbol.** La migración `2026_08_31_000100_add_age_family_seal_to_order_items` · los dos
valores `AgeFamilySeal` y `SealedRegime` · el escritor `AgeFamilySealer` (`build` puro, `seal` para
quien crea filas fuera de `OrderCreator`, `reprice` para el cambio de día) · `OrderItem::ageFamilySeal()`
· `GuestAgeMixReader` reescrito para derivar del sello (sin estado, sin memo, ya no `scoped`) ·
`GuestAgeMix` con `sealed`/`staleSeal` · `MixedPartySurcharge` sin `unitFor` ni recibo y con
`derivationGoverns` que distingue los tres «no aplica» y se abstiene sin tarificar · `OrderCreator`
sella en el mismo `create()` de la línea · `OrderItemEditor::sealUpdateFor()` en el mismo `forceFill`
de `edit()` y `changeSlot()` · la ficha del pedido con la rama «sello caducado» en rojo y «sin sello»
en ámbar, y `drift` ya no dice «hoy» · `#283` retirado entero (clase, doble guardado con firma,
claves de idioma, 7 casos) y la regla en la ayuda del campo de familia · `mixed-party:verify-concurrency`
siembra con sello · `AgeFamilySealer` en el `CRITICAL_RE` y en `CriticalPathGateTest` ·
`Translated::pick()` para leer un nombre traducible fuera de un modelo (y `tr()` lo usa).

**Medido.** Suite **3547 → 3558** en verde (23.336 aserciones): +14 casos en `AgeFamilySealTest`
(las guardas A–O de §21.10), +3 en `GuestAgeMixTest` (los tres «no aplica»), +2 en
`MixedPartyBadgeTest` (sin sello · caducado), −7 de `#283`; los dos casos del recibo re-apuntados al
sello. **13 mutaciones y las 13 muerden** (`OrderCreator` sin sellar · `changeSlot` sin re-sello ·
`edit` sin re-sello · el caducado sin detectar · `anonymize()` vaciando el sello · `derivationGoverns`
ignorando `sealed` · sin la condición «tarificable» · re-sellar desde el catálogo al mover de día ·
`orphan_addons` contando la línea gobernada · el hook sin `AgeFamilySealer` · el sellador filtrando
por `is_sellable` · «sin familia» perdiendo la afirmación · el lector ignorando la familia sellada,
que tira **52** casos: la red no es estrecha).

❗❗ **Dos huecos PREEXISTENTES que salieron al construir, cerrados y con guarda** — ninguno estaba
escrito en ninguna parte:
1. **Un día sin tarifa cancelaba el suplemento** (§21.8, guarda N): mover la fiesta a un día en que
   un pack no tiene precio no lo bloquea el editor, la diferencia no se puede calcular, y el
   reconciliador leía «no llega a ser línea» como «la diferencia es cero» y retiraba lo escrito.
   Ahora «no poder tarificar» es una ausencia (`#268`): ni retira ni crea.
2. **Una fiesta mixta con cargo NO PODÍA cambiar de pack desde el panel.** Lo cazó el caso G de
   `AgeFamilySealTest` conduciendo el editor real: `orphanAddonsForNewProduct()` contaba la línea del
   suplemento como complemento incompatible con el pack nuevo y pedía quitarla — y quitarla es
   justo el gesto que `#271` descarta. Un cerrojo desde el 2026-08-29 que ninguna guarda veía porque
   ninguna cambiaba de pack con un cargo escrito. La línea gobernada queda fuera de esa cuenta: la
   reconciliación post-commit la re-deriva bajo el sello nuevo.

**Lo que cambia para el plan.** El hallazgo del T0 anotado en T5 («la línea del veredicto no dice
que es la tarifa DE HOY») **se disuelve**: veredicto y aplicado salen del mismo sello y ya no se
contradicen; `drift` habla de «las condiciones de esta reserva». La fase `p4` del guion `t0.js`
(«cargo huérfano por familia retirada») **ya no reproduce nada**: retirar la familia en el catálogo no
toca la ficha. T2 arranca con el sello puesto: «completo» se redefine sobre `outOfRange` (D6) y el
disparador pasa a «solo guardado completo» (§20.6); T4 deriva el crédito de los precios sellados,
como §20.8 exigía.

**Lecciones.** (1) *La objeción del owner a Q2 tenía razón y mi recomendación no*: «la fecha es un
producto» era una lectura de más; su regla —conservar los tramos, dejar que el precio siga al día—
es la fiel a `PAY-18` y no exige ninguna segunda fuente. (2) *Un test que conduce el editor REAL
encuentra lo que el diseño no ve*: el cerrojo de `orphan_addons` no está en ningún doc porque nadie
había cambiado de pack con un cargo escrito. (3) *Retirar es una decisión, no un olvido*: `#283`
salió porque bajo D2 no podía saltar nunca, y dejarlo «como red» habría sido fingir.

**Verificación empírica sobre MySQL y en navegador**: ver el registro de `#288` (verificadores de
concurrencia con su control negativo, y la sonda del hueco A en el ciclo real del post-form).
