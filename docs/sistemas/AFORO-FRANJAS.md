# Aforo y franjas — la rejilla, la oferta y los contadores

> Estado: vivo · Última actualización: 2026-09-16 · Verificado contra código: 2026-09-16 (los seis
> servicios citados existen en `app/Domain/Booking/Services/`) · Se invalida si: cambia la rejilla
> (`AFORO-12`), la duración de una franja o el número de recorridos de `SlotOffer`.
> Nace en F1 del programa «producto e instancias» (`DECISIONES #619`): la fila de aforo del enrutador
> apuntaba a las invariantes, que son una tabla y no un sitio para prosa. Aquí vive lo que hay que
> saber ANTES de tocar aforo; las reglas que no se regresan siguen en `INVARIANTES.md` §2.

## §0 · Antes de tocar

- **Lee `INVARIANTES.md` §2 (AFORO) primero**: es la lista de lo que no se puede regresar, cada una con
  su test. Tocar cualquiera de los ficheros de abajo exige `VERIFY_CONC=1` y los verificadores sobre
  MySQL (`INVARIANTES.md` §6): la suite en SQLite no ve las carreras.
- **Quién cuenta y quién decide**: `SlotGenerator` crea las franjas desde `slot_templates` y el horario
  (`OperatingSchedule`) · `SlotOffer` es la fuente ÚNICA de oferta (fechas y horas) · `SlotAvailability`
  y `PackAvailability` son los DOS contadores de plazas y fiestas · `ProductAvailability` solo mira si la
  hora cae en la ventana del día, no cuenta plazas.
- **La rejilla SE SOLAPA a propósito** (`AFORO-12`, `#420`): inicios cada 30 min con franjas de 60. El aforo
  cuenta PRESENCIA (toda franja cuyo inicio cae dentro del tramo del ocupante), así que las plazas de una
  franja son el MÍNIMO del rato que dura la visita. **La duración no puede bajar a 30**: `slot.end_time`
  decide cuándo una reserva está terminada. Guarda: `OverlappingSlotGridTest` + `scripts/mutar-rejilla-solapada.sh`.
- **`SlotOffer` tiene DOS recorridos y una sola fuente** (`#465`): las FECHAS leen el horizonte entero y
  las HORAS cargan solo el día preguntado. Lo que los ata es la consulta compartida (`offeredSlotQuery` con
  su scope `sellableOnline()`), el predicado compartido (`passesOffer`) y `SlotOfferPathParityTest`.
  `clampToHorizon()` no se toca: sin él un día a dos años vista se vendería sin que nada fallara.
- **`OperatingSchedule` memoiza por instancia**: un caso que pregunte antes de sembrar mide el horario de
  antes (la trampa de `#268`).
- Modelo de datos: `MODELO-DATOS.md` §1 · flujos de disponibilidad: `FLUJOS.md` (flujos 3 y 4) ·
  instrumento: `php artisan purchase:verify-oversell --workers=16` (ocho escenarios).

## Lo descartado, con su motivo

Traducir la regla de oferta a SQL (duplica la regla) · cachear la disponibilidad (ofrece días que no
existen) · materializarla (segunda fuente de verdad). Los tres están en `DECISIONES #465`.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Aforo / franjas / disponibilidad / calendario»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después de «Antes de tocar» y no lo reescribas.
> Documentos que la fila citaba: `docs/INVARIANTES.md` · `docs/MODELO-DATOS.md` · `docs/FLUJOS.md`.

- **`docs/INVARIANTES.md` §2 (AFORO)** · `docs/MODELO-DATOS.md` §1 · `docs/FLUJOS.md` (flujos 3–4) —
- ❗❗❗ **`#420` SI TOCAS LA REJILLA, UNA FRANJA O SU DURACIÓN** (`AFORO-12`): desde el 2026-09-06 los inicios van **cada 30 min con franjas de 60**, así que **la rejilla SE SOLAPA** y eso lo aguanta el aforo porque **cuenta PRESENCIA** —cada franja cuyo inicio cae en el tramo del ocupante—, y como todo inicio de venta es un punto de la rejilla, dos tramos que se pisan comparten ese punto y se ven *compartan o no hora de entrada*.
- ⚠️⚠️ **La DURACIÓN NO puede bajar a 30**: `slot.end_time` es lo que lee `OrderItem::isFinishedInPractice()` para dar una reserva por TERMINADA (y de ahí cuelgan el post-form en solo lectura, el cierre de los extras, la ventana del suplemento mixto, el movimiento de puerta de `OrderBook` y «Mis reservas») — con 30, una entrada de 1 h comprada a las 15:30 se declararía terminada a las 16:00.
- ⚠️⚠️ **Corolario que sorprende y es correcto**: las plazas de una franja son el **MÍNIMO del rato que dura la visita**, no «su» aforo (seis entradas a las 15:30 bajan a 14 lo vendible a las 15:00) — lo escribí al revés en la guarda y **el código tenía razón**.
- ⚠️ **La rejilla es CONFIGURACIÓN** (`slot_templates` + el `interval_min` del asistente), no código: no hay ningún «paso» implícito y las claves únicas prohíben repetir la hora de inicio, **no solapar**.
- ▶ Medido: entradas 10→20 horas el sábado y 4→9 el martes; packs 9→18 y 3→7; franjas 3.557→7.115; `offerableTimes` de un sábado 20→64 ms. **La capacidad de cumpleaños NO sube** (el techo es `max_guests_per_slot`, medido idéntico); lo que gana dinero es la ENTRADA (los huecos de una venta parcial). Guarda: `OverlappingSlotGridTest` + `scripts/mutar-rejilla-solapada.sh` (8/8) —
- ❗❗❗ **`#465` SI TOCAS `SlotOffer`**: «fuente ÚNICA» **no es «un solo recorrido»**. Hay DOS: las FECHAS leen filas crudas de todo el horizonte y resuelven la ventana **una vez por día**; las HORAS cargan **solo el día que se pregunta** (medido: 329→18 y 364→30 ms, y el paso del panel 714→95; el read-model público, que es el calendario del CLIENTE, 421→45).
- ⚠️⚠️ **Lo que las mantiene siendo la misma fuente son TRES cosas**: la CONSULTA compartida (`offeredSlotQuery`, con su *scope* `sellableOnline()`, nunca un `WHERE` copiado), el PREDICADO compartido (`passesOffer`) y **`SlotOfferPathParityTest`, que las ata por EQUIVALENCIA** —un día se ofrece si y solo si sus horas no están vacías—.
- ⚠️⚠️ **`clampToHorizon()` NO se toca**: mientras la consulta abarcaba el horizonte, el techo de venta lo ponía ella; al preguntar por un día suelto **un día a dos años vista se vendería sin que nada fallara**.
- ⚠️ **`OperatingSchedule` memoiza horario, temporadas y fechas especiales POR INSTANCIA**: un caso que pregunte antes de sembrar mide el horario de antes y falla con el producto sano (la trampa de `#268`).
- ⚠️ Descartados con su motivo: traducir la regla a SQL (**duplica la regla**), cachear la disponibilidad (**ofrece días que no existen**) y materializarla (segunda fuente de verdad)
