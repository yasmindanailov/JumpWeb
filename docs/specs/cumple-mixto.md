# [SPEC] Cumpleaños **MIXTO** — cuando la edad declarada no cuadra con el pack reservado

> Estado: ⬜ **BORRADOR, apuntado el 2026-08-28 a petición del owner.** No se empieza sin su ✅ y sin
> las respuestas de §5 · Última actualización: 2026-08-28 · Decisión asociada: pendiente.
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
3. **Los dos packs comparten ZONA** (`zone_id = 4`, «Cumpleaños»), con las mismas franjas de 10:00 a
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
