# [SPEC] Desmontar `ViewOrder` — el god-class del panel

> Estado: 🟦 **EN EJECUCIÓN** · revisión adversarial HECHA e INCORPORADA (2026-08-26, §8) ·
> ✅ **`[DECIDIDO owner, 2026-08-26]`: spec APROBADA y ejecución AUTORIZADA («ahora», no «espera»)**
> · el paso a paso vive en **§9** ·
> Última actualización: 2026-08-26 ·
> Verificado contra código: 2026-08-26 (el fichero entero, sus 98 métodos, su red de tests, y qué
> contratos del dominio usa — ninguno; re-verificado por la revisión §8 con 7 medidores) ·
> Decisión asociada: `DECISIONES #165` (la spec) · `#167` (la revisión) ·
> Se invalida si: alguien mueve código dentro del fichero sin re-medir la descomposición de §1.2.
>
> ▶ **El cuerpo ya describe el código real**: las correcciones de la revisión (§8) están
> incorporadas — §8 conserva el registro de lo que estaba mal, con su evidencia, y NO se lee como
> estado vigente sino como historia de la revisión. Lo que cambió de verdad el plan: el paso 0
> (código muerto), el paso 3 como EXTENSIÓN con `VERIFY_CONC`, el mapa transaccional de §4.3 y el
> instrumento del paso 4.
>
> ⚠️ **Esto es DISEÑO. No se ha tocado una línea de `app/`**, y no debe tocarse hasta que esta spec
> esté revisada por el otro agente y aprobada por el owner: es el fichero que más cerca está del
> dinero y del aforo de todo el producto.

---

## 1. Contexto y problema — MEDIDO, no supuesto

`DEUDA.md` lo tiene en **Alta** con la etiqueta «sin plan» desde el 2026-08-14. Antes de diseñar
nada se volvió a abrir el fichero, porque **la ficha mentía en su única cifra**.

### 1.1 Lo primero: la cifra estaba caducada

    DEUDA.md decía   5.029 líneas   (medido el 2026-08-14)
    medido hoy       5.280 líneas   → +251 sin que nadie mirara

Es la regla de la casa aplicada a sí misma: *una fila que nombra un fichero o una cifra hay que
abrirla, no creerla*. Corregida en su ficha.

### 1.2 La descomposición, que es lo que decide el diseño

98 métodos en 5.280 líneas (más ~36 closures y ~60 arrow-fns dentro de ellos — el pegamento de los
`*Action`, que captura `$this` y `$record`). Repartidos por lo que hacen.
⚠️ **Instrumento declarado** (lo exige la cláusula de invalidación de la cabecera): líneas por
**BLOQUE** — cada método absorbe su docblock y el hueco que lo precede. Quien re-mida por cuerpos
Reflection obtendrá cifras menores en todas las filas (p. ej. orquestación 1.542) sin que la tabla
esté caducada.

| | Líneas | Métodos | Qué es | ¿Es de la capa de entrega? |
|---|---|---|---|---|
| **Orquestación de dominio** (`execute*`, `compute*`, `validate*`, `save*`, `apply*`) | **1.699 (32 %)** | 14 | Editar un ítem, reembolsar, cancelar, cambiar franja, tarificar complementos | ❌ **No** |
| Presentación y notificaciones (`*Notification`, `render*`, `*Preview`, `*Options`) | ~495 (9 %) | 14 | Los textos y avisos que ve el operador | ✅ Sí |
| Lecturas de disponibilidad (`availableDatesForItem`, `availableTimesForItem`, `selectableDatesInRange`) | ~240 (4 %) | 3 · ⚠️ **2 MUERTOS** (§8.7) | Qué días y horas se pueden elegir — la VIVA es `selectableDatesInRange`; el resto de la disponibilidad viva está dentro del calendario | ❌ **No** (§1.4) |
| Acciones y composición Filament (`*Action`, `*Fields`, `*Tab*`, formularios de modal) | ~1.203 (23 %) | 18 | Los 10 botones y sus formularios | ✅ Sí |
| Calendario (contiene `Calendar` — ⚠️ NO es prefijo: por prefijo son solo 10) | ~529 (9 %) | 13 | El selector de fecha/hora del panel | ✅ Sí (estado de UI) — ⚠️ pero CONTIENE disponibilidad viva (§8.7) |
| Ayudantes varios | ~1.016 + 98 de cabecera (21 %) | 40 | Mezcla de las dos naturalezas | ⚠️ Hay que clasificar uno a uno |

▶ El método más grande es **`executeItemEdit`, con ~472 líneas**. Los 12 mayores suman
**1.924–2.128 líneas según instrumento — unas dos quintas partes del fichero**.
▶ Toca dinero y aforo en **8 invocaciones reales**: `applyExtraDue` ×3 · `lockZoneDaySlots` ×2 ·
`executeFullRefund` ×1 · `executePartialRefundBatch` ×1 · `executeItemEdit` ×1 (interna).
Reproducible: grep de INVOCACIÓN (`\$this->` / `\$order->` + nombre + paréntesis), leyendo el
contexto de cada hit.
⚠️ La primera versión de esta línea decía «25 llamadas»: contaba apariciones de subcadena —
comentarios y definiciones incluidos— y el ×7 de `executePartialRefund` era `executePartialRefundBatch`
como substring más 6 comentarios. El registro del error y su desglose, en **§8.6**.

### 1.3 ❗ El dato que cambia el diseño: es GRANDE, no está ENMARAÑADO

**Solo declara CINCO propiedades públicas de Livewire**, y **cuatro son del calendario**
(`calendarItemId`, `calendarMonth`, `calendarSelectedDate`, `calendarSelectedTime`); la quinta es
`auditPerPage` (el tamaño de página de la auditoría — el paginador real es una computed más el
estado heredado de `WithPagination`). La clase hereda además estado de la infraestructura
Filament/Livewire (`$record`, `$data`, `$mountedActions`, `$paginators`), que no entrelaza las
piezas entre sí (§8.11). La composición de la ficha ya vive fuera (`Schemas/OrderInfolist.php`);
lo que sí compone inline son los formularios de los MODALES de acción.

▶ **Traducido**: el estado mutable compartido es mínimo — el censo de asignaciones `$this->` del
fichero solo muta las 4 del calendario (§8.2) — así que las piezas **no están entrelazadas
por estado**: están simplemente amontonadas. Eso convierte el desmontaje en una serie de
extracciones acotadas en vez de una reescritura. **Un god-class con veinte propiedades compartidas
sería otro proyecto.**
⚠️ **El matiz que ordena el paso 1**: las 4 props del calendario SÍ las leen tres métodos de fuera
de su familia — uno de dominio (`executeManageItemSave`) y dos de presentación (`priceDiffPreview`,
`addonDiffPreview`) — así que la frontera calendario↔dominio cruza estado (§8.7). No tumba la tesis;
fija el mecanismo del paso 1 (Concern) y del paso 4 (fecha/hora por parámetros).

### 1.4 Y un hallazgo que no se buscaba: el panel tiene su PROPIA disponibilidad

Medido: `ViewOrder` **no usa ni un solo `Booking\Contracts\*`** —cero referencias— y
`availableDatesForItem()` consulta **`Slot::query()` directamente**.

Fase 3 creó `Booking\Contracts\AvailabilityOffer` (sobre `SlotOffer`) precisamente para que web y app
ofrecieran las mismas fechas. **El panel se quedó fuera y compone las suyas.** Es la misma familia que
la ficha «cuarta copia de la aritmética de cesta» de `DEUDA.md`, en otro fichero.

⚠️ **Matiz que se midió antes de acusar**: no todo está duplicado. `computeAddonPricing()` **sí**
reutiliza las primitivas del dominio (`AddonResolver::freeUnits`, `AddonResolver::effectiveQuantity`,
`RateResolver::priceCents`); lo que compone por su cuenta es la orquestación. La duplicación **real y
completa** es la de disponibilidad.
⚠️ **REVISADO (§8.9): hay una SEGUNDA** — `lockZoneDaySlots` es la segunda implementación a mano de
la receta anti-sobreventa de `AFORO-01`, con paridad de alcance declarada en su docblock y el porqué
documentado solo en el dominio. Y la propia disponibilidad reutiliza las primitivas
(`SlotAvailability`/`PackAvailability`/`OperatingSchedule`): lo duplicado es la COMPOSICIÓN de la
oferta, la misma situación que el pricing.

### 1.5 La red que ya existe, y es la que hace esto pensable

**16 ficheros de test citan `ViewOrder` (289 métodos); con criterio de CONDUCCIÓN real la red son
~23 ficheros y ~317 casos** — 7 ficheros más conducen la página por ruta HTTP sin nombrar la clase
(`OrderInfolistEnrichedTest` con 48, `ItemsListSubCardTest`…, §8.11) y 49 de los 289 conducen otra
cosa. Los cinco mayores por métodos:

    OrderAdminActionsTest              61
    ManageItemQuantityProductTest      47
    ManageItemSlotChangeTest           33
    ManageItemAddonsTest               28
    ManualOrderWithoutEmailTest        17   ⚠️ solo 2 conducen la página (es una suite de CreateManualOrderPage)

▶ Sin esa red, desmontar este fichero sería temerario. Con ella, la conducta está fijada y cada
extracción se puede verificar **por mutación**.
⚠️ **36 de esos tests entran por `ReflectionMethod` contra la clase** (10 métodos privados):
cualquier extracción los rompe POR CONSTRUCCIÓN — la cláusula que eso impone al criterio de éxito
está en §2·1. Y dos piezas del calendario no tienen red ninguna (`calendarGoToItemMonth`;
`calendarMatrixForItem` está directamente muerto), §8.11.
⚠️ **Y NO está en el `CRITICAL_RE`** del `pre-push` (comprobado), así que hoy tocarlo no exige
`VERIFY_CONC=1`. §5 discute si eso debe seguir siendo verdad después — y desde la revisión se sabe
que **el paso 3 SÍ lo dispara** por los ficheros del dominio que extiende (§8.3).

---

## 2. Objetivo

**Que la clase de ENTREGA deje de contener el DOMINIO, sin cambiar ni una sola conducta observable.**

Criterios de éxito, todos medibles:

1. **Cero cambios de conducta.** La red de ~317 casos pasa **sin tocar un solo ASSERT**. Un assert
   que haya que reescribir es la señal de que la extracción cambió algo — y entonces se para y se
   mira. ⚠️ **Cláusula de reflexión (§8.8)**: 36 tests entran por `ReflectionMethod` y 75
   interacciones fijan las props del calendario sobre la clase — re-apuntar una reflexión o un
   `set()` al nuevo dueño del método es mudanza esperada y va en el mismo paso; lo intocable es lo
   ASEVERADO, no el cableado del arnés.
2. La orquestación de dinero/aforo vive en **`app/Domain/`**, detrás de un contrato, como el resto del
   dominio desde Fase 2.
3. `ViewOrder` baja de 5.280 líneas a **lo que sea composición de Filament y estado de UI**, y esa
   cifra se declara MEDIDA al cerrar, no estimada.
4. El panel deja de COMPONER **su propia oferta de disponibilidad**: la pide al dominio por una
   consulta de **re-programación** (extensión de la familia de `SlotOffer`/primitivas, §4.2 y §8.3)
   — que es cumplir `AFORO-02` sin perder las decisiones de producto que el panel encarna.
5. Cada extracción se verifica **por mutación**, no por «la suite sigue verde».

### Fuera de alcance, explícitamente

- ⛔ **Rediseñar la pantalla.** Ni un píxel, ni un texto, ni un botón de sitio.
- ⛔ **Arreglar defectos que aparezcan por el camino.** Si la extracción destapa uno, se anota y se
  abre su propia ficha: mezclar un arreglo con una mudanza es cómo se pierde la trazabilidad de los
  dos (`#112`: «un sustituto a medias es DOS implementaciones, no una y media»).
- ⛔ Tocar `Order.php` para meterle la orquestación (§3·D).

---

## 3. Opciones consideradas

**A · Dejarlo como está.** Es lo que lleva pasando desde Fase 2, que lo declaró fuera de alcance por
ser capa de entrega. ⚠️ **Pero crece**: +251 líneas en doce días, medido. Y cada tanda de dinero que
entra ahí —`#146`, `#149`, `#150`— lo engorda un poco más.

**B · Partirlo en traits por tamaño.** DESCARTADA: mueve líneas de sitio sin mover responsabilidades.
El fichero dejaría de tener 5.280 líneas y el problema —dominio en la capa de entrega— seguiría
intacto, ahora repartido en cinco sitios donde es más difícil de ver.

**C · Extraer el DOMINIO a servicios, detrás de contrato (ELEGIDA).** Es lo que Fase 2 hizo con el
resto de la app y lo que `HasItemActionGuards` ya empezó aquí: **110 líneas de guardas que ya viven
en `app/Domain/Booking/Concerns/`**. Hay precedente dentro del propio fichero.

**D · Mover la orquestación al modelo `Order`.** DESCARTADA **con medida**: `Order.php` tiene ya
**2.349 líneas**. Mudar 1.699 allí no resuelve nada — cambia un god-class de entrega por un
god-model, que es peor porque el modelo lo usa todo el mundo.

---

## 4. Diseño elegido

### 4.0 La regla que ordena todo lo demás

**Ninguna extracción cambia conducta, y cada una se verifica por mutación antes de pasar a la
siguiente.** El orden no es por tamaño ni por comodidad: es **por riesgo creciente**, para que el
método esté rodado cuando llegue el dinero.

### 4.1 Las extracciones, en orden — con un paso 0 que la revisión añadió

**Paso 0 · Retirar el código MUERTO antes de mudar nada** (`CONVENCIONES §3.quater`, §8.7):
`availableDatesForItem` y `availableTimesForItem` (cero llamadores de producción desde el commit
fundacional; solo los mantienen vivos tests por reflexión) y `calendarMatrixForItem` (cero
referencias en todo el repo). Sus tests se auditan y se re-apuntan o retiran ANTES — mudar código
muerto es pagar el riesgo de la mudanza sin comprar nada.

| | Qué sale | Tamaño | Riesgo | Por qué va aquí |
|---|---|---|---|---|
| **1** | **El calendario** → un `Concern` (trait sobre la MISMA clase — «componente» DESCARTADO: rompería 75 interacciones de test y el camino de runtime, §8.8) | ~529 líneas · 13 métodos («contiene Calendar», no prefijo) · **las 4 propiedades** | **Bajo** | Sin dinero, y como trait las props siguen en el componente: el corte de la frontera dominio→calendario (`executeManageItemSave` lee `calendarSelected*`, §8.7) se hace en el paso 4 pasando fecha/hora por parámetros. Es el ensayo del método |
| **2** | **Presentación y notificaciones** → un `*Presenter` de la capa de entrega | ~495 líneas · 14 métodos | Bajo | No toca dominio; solo compone textos (dos leen el estado del calendario: reciben la fecha por parámetro). Reduce ruido antes de entrar en lo serio |
| **3** | **La COMPOSICIÓN de la oferta del panel** → una consulta de **RE-PROGRAMACIÓN** nueva en el dominio, sobre `SlotOffer`/`SlotAvailability`/`PackAvailability` con `excludeItemId` — **extensión, no sustitución** (§8.3: el contrato existente responde a COMPRAR, el panel a MOVER) | `selectableDatesInRange` + `calendarTimesForItem*` + `displayAvailableFor` + `slotMeetsItemRequirements` (la disponibilidad VIVA, más que las ~240 de §1.2) | ❗ **Alto** — toca ficheros del `CRITICAL_RE` ⟹ **`VERIFY_CONC=1` ya en este paso** | El diff previo POR EJE (§4.2) separa decisión documentada de deriva; lo que destape de producto lo zanja el owner |
| **4** | **La orquestación de dinero/aforo** → servicio de dominio con contrato propio | ~1.699 líneas · 14 métodos | ❗ **Alto** | `executeItemEdit` (~472 líneas) y familia. La última, con el método ya probado tres veces |

### 4.2 ⚠️ El paso 3 destapará diferencias de CONDUCTA con seguridad — y se clasifican POR EJE

La primera versión de esta sección preguntaba «¿difieren?». La revisión (§8.3) midió que **la
divergencia está garantizada estáticamente** y que **no hay UNA respuesta correcta**: el contrato
responde a una compra nueva y el panel a mover un ítem ya comprado, y varias diferencias son
decisiones de producto documentadas en los docblocks del propio fichero (numeración del ORIGEN).
Así que el diff previo — que sigue siendo obligatorio ANTES de mover — se hace **POR EJE**, y cada
eje tiene una de dos salidas:

- **Decisión documentada → la consulta de re-programación la CONSERVA** (no se «unifica» contra el
  contrato, que la desharía): el slot actual siempre ofrecido · la huella propia contada al mostrar
  y excluida al validar (`excludeItemId`, `AFORO-06`) · producto retirado sigue movible · aforo
  contra los `seats` del ítem.
- **Deriva accidental o hueco → lo zanja el OWNER con la medida delante**: el ancla temporal
  (`Carbon::today()` en UTC vs la zona del parque, `AFORO-09`) · si el panel debe seguir EXENTO de
  antelación mínima y suelo intradía (`PAY-13` hoy solo rige la web — probablemente sí, es venta en
  mostrador, pero es decisión y no accidente) · entrada llena oculta vs ofrecida-no-vendible.

⚠️ Dos trampas del diff, medidas (§8.11): las firmas no aceptan los mismos datos (hay que definir la
PROYECCIÓN ítem→producto primero), y ejecutarlo entre las 00:00 y las ~02:00 del parque arroja
diferencias que son del RELOJ, no de las lógicas — los datasets incluyen a propósito «hoy con horas
pasadas» y esa ventana.

### 4.3 El paso 4: el mapa transaccional REAL, qué contrato, y qué NO se toca

> La primera versión de esta sección describía la secuencia como si viviera entera «dentro de una
> transacción» — **era al revés**, y quien la hubiera «preservado» al extraer la habría CREADO. El
> registro del error y su evidencia: **§8.4**. Éste es el mapa medido:

`executeItemEdit` (y en espejo `executeItemSlotChange`) ejecuta **cuatro fases**, y la frontera
transaccional es el diseño, no un accidente:

1. **Guardas** — las 9 llamadas a `blockEdit`, **ANTES de abrir transacción alguna**. ⚠️ `blockEdit`
   es capa de ENTREGA (audita el rechazo + `Notification` de Filament): el predicado de dominio que
   consulta vive en `HasItemActionGuards` y se consume vía `Order`; la mitad de REEMBOLSO de esa
   familia vive en `Payments\Concerns\GuardsItemRefunds`, que el paso 4 también necesita.
2. **UNA transacción SOLO de aforo/mutación**: `lockZoneDaySlots` como PRIMERA sentencia
   (`AFORO-01`/`AFORO-05`), lock del ítem, revalidación de cupo con `excludeItemId` (`AFORO-06`),
   `forceFill`, complementos y re-escala.
3. **Tras el commit, la secuencia financiera**: cada `applyExtraDue` / `applyGateCredit` /
   `applyDepositRemainderCredit` / `recordReductionMarker` abre su PROPIA transacción corta en
   `Order` — los métodos de `Order` **se quedan donde están** — y los buckets
   `itemExtraDueCents`/`itemDepositRemainderCents` leen estado **committed** entre ellas. El propio
   docblock del método lo declara: la REST de un refund no puede ir dentro de la txn de aforo.
   ⚠️ El **waterfall de créditos** está escrito DOS veces dentro del método (edición y re-escala
   per-invitado, con una asimetría en la condición del marcador a verificar): se extrae como UNA
   pieza, no por copia (§8.9).
4. **Notificación/email al final.**

▶ **El servicio extraído es un HÍBRIDO y hay que decirlo así** (§8.4): para la fase 3 aplica la
doctrina `CheckoutOrchestrator` (`#37`) — orquestar pasos que abren sus transacciones, cero
transacción propia —, pero la fase 2 **posee su transacción con lógica inline** (patrón
`OrderCreator`, no orquestador). El servicio nuevo abre y posee la txn de aforo Y orquesta la
secuencia financiera post-commit.
⚠️ **No se envuelve NADA en una transacción envolvente que hoy no existe**: haría savepoints de las
transacciones financieras, sostendría el lock de zona/día durante auditoría, email y REST, y metería
el rastro en alcance de rollback — `PAY-05` y `AFORO-01` violadas a la vez con la suite en verde.
⚠️ **Los audits no se «normalizan»**: el de INCIDENCIA va fuera de toda transacción; los dos de
ÉXITO de las cancelaciones viven a propósito DENTRO de la suya (si la acción no commitea, no hubo
acción que auditar) — §8.4.
⚠️ **Y el paso 4 decide si consolida los DOS locks en un helper único**: `lockZoneDaySlots` es hoy
la segunda implementación a mano de la receta anti-sobreventa, con el porqué documentado solo en
`OrderCreator::lockSlots` (§8.9).

### 4.4 Lo que NO se decide en esta spec, a propósito

- **El nombre del contrato y su forma exacta**: sale del paso 4, cuando las tres extracciones
  anteriores hayan enseñado dónde están las costuras reales. Fijarlo ahora sería especulación — la
  misma razón por la que `#37` aplazó el segundo driver de pasarela.
- **Si `ViewOrder` debe seguir siendo una sola clase** al final. Se decide con la cifra en la mano.

---

## 5. Impacto en invariantes

> Tabla COMPLETADA por la revisión (§8.10): la primera versión omitía cuatro invariantes con cita
> literal a `ViewOrder` y cuatro de roce.

| ID | Impacto |
|---|---|
| **AFORO-01 · AFORO-05** | ⚠️⚠️ **Las más expuestas.** `lockZoneDaySlots` se llama 2 veces desde aquí (los dos caminos de edición) y HOY es la primera sentencia de su transacción en ambos — así debe seguir: una extracción que meta un `SELECT` antes lo rompe **sin que ningún test lo vea**, porque `AFORO-05` documenta que el ALCANCE zona/día del lock no tiene assert (SQLite no reproduce la carrera). El instrumento del paso 4 sale de aquí (§6·4) |
| **AFORO-06** | `excludeItemId` en la revalidación del re-agendado — la consulta de re-programación del paso 3 lo hereda como requisito de contrato, no como detalle |
| **AFORO-02** | El paso 3 ES su ejecución sobre el panel: hoy `ViewOrder` la incumple en su letra (compone oferta sin `SlotOffer`). Extender la familia de la oferta toca ficheros del `CRITICAL_RE` ⟹ `VERIFY_CONC` en el paso 3 |
| **AFORO-09** | El panel ancla «hoy» con `Carbon::today()` (UTC) ×14; la oferta del dominio, en la zona del parque. El diff del paso 3 lo destapa por construcción; unificar el ancla es decisión del owner (§4.2) |
| **PAY-05** | El rastro de incidencia no puede quedar dentro de un rollback — HOY se cumple con la topología de §4.3 (guardas pre-txn, incidencia fuera, éxito de cancelación DENTRO a propósito). La extracción conserva la topología, no la «normaliza» |
| **PAY-13** | El panel está EXENTO de suelo intradía y antelación mínima **a propósito** (venta en mostrador). La consulta de re-programación debe mantener la exención de forma DECLARADA, no heredada por accidente — y el owner la confirma (§4.2) |
| **PAY-09 · PAY-10 · PAY-16 · PAY-17 · PAY-18** | Sus reglas viven en los métodos que se mudan (los escritores de los canales de `PAY-16` — `applyExtraDue`, los dos créditos, el marcador — son exactamente la fase 3 de §4.3). **Ninguna se relaja**: el criterio de éxito es cero cambios de conducta |
| **RGPD-02** | `eventDataDiffKeys` vive aquí: los diffs de `event_data` registran solo CLAVES, jamás valores. La extracción 2/4 que lo toque conserva la propiedad |
| **SEC-04** | El re-check de `orders.cancel` en el handler del reembolso vive aquí: re-autorizar EN el momento de ejecutar no puede perderse al extraer la acción |
| **SUITE-04** | ⚠️ La suite es ciega a las carreras InnoDB. Por eso el paso 4 **no puede** apoyarse solo en la red de tests |
| **CRITICAL_RE del `pre-push`** | El paso 3 ya dispara `VERIFY_CONC` por los ficheros del dominio que extiende (arriba). ❗ Para el paso 4 queda la decisión: el servicio de dinero extraído **probablemente deba entrar** en la lista — `CriticalPathGateTest` vigila que cubra lo que debe. Se decide en el paso 4 |

---

## 6. Plan de verificación empírica

Sin esto no puede llegar a ✅ (`CONVENCIONES §3.bis`).

1. **La red pasa sin tocar un solo ASSERT**, en cada extracción — con la cláusula de reflexión de
   §2·1: re-apuntar `ReflectionMethod`/`set()` al nuevo dueño es mudanza esperada; un ASSERT que
   haya que reescribir **para** una extracción es la señal de alarma, no un trámite.
2. **Mutación por extracción**: retirar la pieza extraída deja en rojo los casos que la cubren. Una
   extracción cuya mutación no muerde es una extracción que nadie estaba probando — y eso se arregla
   antes de moverla, no después. ⚠️ Dos piezas parten SIN red (§8.11): `calendarGoToItemMonth` gana
   su test en el paso 1, y `selectableDatesInRange` el suyo directo en el paso 3.
3. **Paso 3, ANTES de mover**: el diff POR EJE de §4.2 — definir la proyección ítem→producto,
   correrlo con los datasets que fuerzan los ejes conocidos (hoy con horas pasadas, la ventana
   00:00–02:00 del parque, packs, slot actual cerrado, producto retirado) y clasificar cada eje en
   decisión-documentada o deriva. Lo que sea deriva/producto lo zanja el owner antes de escribir la
   consulta.
4. **Paso 4, con un instrumento que EJECUTE lo mudado** (§8.5): los dos verificadores actuales
   conducen `OrderCreator` y `RedsysReturnHandler`, no esto — su verde sería trivial. Se extiende
   `purchase:verify-oversell` (o comando hermano) con el escenario de **edición de panel
   concurrente** — dos ediciones con tramos solapados en franjas distintas, el bug que motivó
   `lockZoneDaySlots` según su propio docblock (`AFORO-05`) — sobre MySQL real, y **viendo fallar el
   instrumento** (lock retirado) antes de creerle el verde: la regla de `#147`. Además, revisión por
   query-log de que el lock sigue siendo la primera sentencia.
5. **Navegador**: las 10 acciones del panel recorridas a mano tras el paso 4. La suite no ve un
   formulario de Filament que deja de montarse.
6. **La cifra final se MIDE**: `wc -l` de `ViewOrder` al cerrar, y se declara en `DEUDA.md`. No se
   estima.

---

## 7. Revisión y decisión

- **Medido por el agente B** el 2026-08-26 contra el código: los 98 métodos, su reparto por
  responsabilidad, las 5 propiedades públicas, la red de tests, los contratos que usa (**ninguno**)
  y el tamaño de `Order.php` como destino descartado. (Su cifra de «25 llamadas a dinero/aforo»
  resultó ser un conteo de subcadenas: son 8 invocaciones — §8.6.)
- ✅ **Revisión adversarial HECHA** (2026-08-26, otra sesión, `CONVENCIONES §5`): **§8**, entrada
  `DECISIONES #167`. Confirmó el diagnóstico, refutó tres piezas del plan y **sus correcciones están
  INCORPORADAS a este cuerpo** (§1.2, §1.3, §1.5, §2, §4.1–§4.3, §5, §6) — la entrada de la
  incorporación es la siguiente a `#167`. La tradición sigue ganando: `#122` encontró dos
  bloqueantes, `#123` declaró un diseño insuficiente, `#156` un hecho no observable — y aquí, un
  mapa transaccional invertido que habría hecho crear la transacción que decía preservar.
- ✅ **`[DECIDIDO owner, 2026-08-26]`: el ✅ está DADO y la ejecución arranca ya** — con el encargo
  explícito de rigor y meticulosidad. Las decisiones de producto del diff del paso 3 (§4.2) se
  preguntan cuando el diff las destape.
- **Entrada final**: `DECISIONES #165` (la spec) · `#167` (la revisión) · la ejecución, en **§9** y
  sus entradas.

---

## 8. Revisión adversarial (2026-08-26) — el diagnóstico SOBREVIVE; el plan necesitaba corrección

> ✅ **Este § es el REGISTRO de la revisión, y su checklist §8.12 está EJECUTADO** (mismo día): las
> correcciones viven ya en el cuerpo. Se conserva entero porque la evidencia de POR QUÉ cada cosa
> estaba mal es lo que impide re-cometerla.

> Revisión hecha por el agente del carril B (sesión distinta de la que escribió la spec),
> `CONVENCIONES §5`. Método: 7 medidores independientes por lote (métricas · estado/propiedades ·
> contratos · red de tests · semántica de disponibilidad · transacciones/locks · invariantes y
> coherencia doc), cada afirmación con dos instrumentos, y una pasada de escépticos que intentó
> REFUTAR cada hallazgo antes de publicarlo: los 8 relevantes sobrevivieron, 0 cayeron.

### 8.1 Veredicto global

**El diagnóstico de §1 y la opción C con sus cuatro extracciones por riesgo creciente SOBREVIVEN** —
las cifras estructurales son exactas (5.280 líneas · 98 métodos · 5 propiedades públicas declaradas ·
cero `Booking\Contracts\*` · `Order.php` 2.349 · `HasItemActionGuards` 110), el censo de asignaciones
`$this->` confirma que el único estado propio mutado es el del calendario, y `AFORO-01` se cumple HOY
en los dos caminos del panel (el lock es la primera sentencia de su transacción en `executeItemEdit`
y en `executeItemSlotChange`).
**Pero la spec NO puede ir al owner tal cual**: tres hallazgos son bloqueantes —el paso 3 compara dos
preguntas distintas (§8.3), §4.3 describe al revés el mapa transaccional real (§8.4) y el plan de
verificación del paso 4 usa instrumentos que no ejecutan el código mudado (§8.5)— y las correcciones
de §8.12 van ANTES del ✅.

### 8.2 Lo que la medición CONFIRMA (para no re-verificarlo)

Líneas y métodos exactos (98 métodos con visibilidad; aparte hay ~36 closures y ~60 arrow-fns) ·
la categoría «orquestación de dominio» da 1.699/14 exacta ·
las 5 propiedades públicas declaradas y su reparto 4+1 · cero contratos de Booking y `Slot::query()`
en 7 sitios · `computeAddonPricing()` sí reutiliza `AddonResolver`/`RateResolver` · la composición de
la ficha vive en `Schemas/OrderInfolist.php` (la clase solo compone inline los modales de acción) ·
`Order.php` 2.349 · los cinco mayores ficheros de test cuadran exactos (61/47/33/28/17 métodos) ·
ni el `CRITICAL_RE` ni `CriticalPathGateTest` ven la página (ni siquiera como control negativo:
el gate solo itera controladores de API) · `DECISIONES #165` es coherente con la spec — y es la única
de las tres superficies que NO repite la cifra defectuosa de §8.6.

### 8.3 ❗ BLOQUEANTE · El paso 3 no es una «sustitución por el contrato»: son dos preguntas distintas

`AvailabilityOffer` responde **«¿qué se puede COMPRAR de este producto?»** (por producto + cesta,
filtrado a catálogo vendible, con antelación mínima y suelo intradía). El panel responde **«¿a dónde
se puede MOVER este ítem ya comprado?»**. Divergencia garantizada estáticamente, eje por eje:

1. **Producto retirado**: el contrato exige vendible + zona operativa (`AvailabilityReader`) y
   devolvería vacío — un pedido antiguo de producto descatalogado quedaría **inamovible**; el panel
   solo necesita la zona.
2. **El slot actual se ofrece SIEMPRE**, aunque esté cerrado (decisión documentada en el propio
   fichero); el contrato no tiene concepto de «slot actual».
3. **Aforo contra los `seats` del ítem**, no contra una cesta; y `dates()` del contrato no filtra
   por aforo a propósito.
4. **Sin `meetsMinAdvance` ni `passesIntradayFloor`**: el panel ofrece y `validateNewSlot` acepta
   una hora de HOY ya pasada, a propósito (mover un pedido en mostrador). Es la letra de `PAY-13`
   aplicada solo a la web — si el panel debe seguir exento es DECISIÓN, no accidente a preservar.
5. **La huella propia**: el panel la CUENTA al mostrar y la excluye solo al validar bajo lock vía
   `excludeItemId` (`AFORO-06`) — parámetro que `SlotAvailability` y `PackAvailability` SÍ tienen y
   **el contrato no expone**. El contrato descuenta CESTA, que es otra cosa.
6. **Ancla temporal**: el panel usa `Carbon::today()` (app en UTC) ×14; `SlotOffer` usa
   `DisplayTime::now()` (zona del parque). Entre las 00:00 y las ~02:00 del parque, «hoy» difiere
   un día por construcción (`AFORO-09`).

⚠️ **Varias divergencias son decisiones DOCUMENTADAS en los docblocks del propio fichero** — con la
numeración del proyecto ORIGEN, no la de nuestro registro de decisiones:
«`#173` (decisión clienta): el
slider es fidedigno con la lógica real, CONTANDO la huella propia», «`#164`: el slot ACTUAL siempre
cumple los requisitos» y el `excludeItemId` de la validación (origen `#167`, hoy `AFORO-06`). Por
eso la salida de §4.2 «medir cuál es correcta y decidir» no aplica — **ambas son correctas para su
pregunta**, y la rama «si coinciden, es mecánico» está vacía: con la regla de parada de la propia
spec, el paso 3 se pararía en el primer ítem real.
▶ **Consecuencia**: el paso 3 se re-diseña como **EXTENSIÓN del dominio** (un modo/consulta de
re-programación ítem-céntrico sobre `SlotOffer` + primitivas, con `excludeItemId`) — no como
sustitución. El diff previo de §4.2 sigue valiendo pero **POR EJE**, separando decisión documentada
de deriva accidental. Su riesgo pasa de Medio a **Alto**, y como `SlotOffer`/`SlotAvailability`/
`PackAvailability` **SÍ están en el `CRITICAL_RE`**, la extensión dispara `VERIFY_CONC=1` **ya en el
paso 3**, no en el 4 (además el paso 3 ES la ejecución de `AFORO-02` sobre el panel — y §5 no la
listaba). Dato a favor del re-encuadre: el precedente de panel que ya consume la oferta única
(`CreateManualOrderPage`, pedido manual = compra nueva) usa `SlotOffer` directamente, no la interfaz.

### 8.4 ❗ BLOQUEANTE · §4.3 invierte el mapa transaccional real de `executeItemEdit`

**Ninguna de las piezas que §4.3 lista como «dentro de una transacción» se ejecuta dentro.** El mapa
real, verificado leyendo el método y confirmado por su propio docblock («la REST de refund no puede
ir dentro de la txn de aforo»):

1. **Guardas** — las 9 llamadas a `blockEdit`, ANTES de abrir transacción alguna.
2. **UNA transacción SOLO de aforo/mutación**: `lockZoneDaySlots` como primera sentencia, lock del
   ítem, revalidación de cupo, `forceFill`, complementos y re-escala.
3. **Tras el commit, la secuencia financiera**: cada `applyExtraDue` / `applyGateCredit` /
   `applyDepositRemainderCredit` / `recordReductionMarker` abre su PROPIA transacción corta en
   `Order`, y los buckets `itemExtraDueCents`/`itemDepositRemainderCents` leen estado **committed**
   entre ellas.
4. Notificación/email al final.

**El peligro es literal**: un implementador que se crea §4.3 y quiera «preservar» esa transacción
envolvente al extraer, **la crearía** — convirtiendo las transacciones financieras en savepoints,
sosteniendo el lock de zona/día durante auditoría, email y (en reembolsos) la llamada REST, y
metiendo el rastro en alcance de rollback: `PAY-05` y `AFORO-01` violadas a la vez, con la suite en
verde (SQLite no ve nada de esto). La advertencia de §4.3 («no se envuelve en una transacción
nueva») es correcta y salva el diseño, pero descansa sobre una descripción falsa del estado actual.

⚠️ **Y la analogía con `CheckoutOrchestrator` solo vale para la mitad financiera**: ese orquestador
tiene CERO transacciones por contrato (`PAY-05`) y delega en pasos que abren las suyas — el lado
financiero post-commit encaja; pero el lado de AFORO **posee su transacción** con lógica inline
(patrón `OrderCreator`, no orquestador). El servicio extraído tendrá que abrir y poseer esa
transacción: es un híbrido que `#37` no modela, y la spec debe decirlo.
⚠️ Matiz de `PAY-05` que la extracción no debe «normalizar»: dos audits de ÉXITO (`orders.cancelled`,
`orders.item_cancelled`) viven a propósito DENTRO de su transacción — si la acción no commitea, no
hubo acción que auditar. El audit de INCIDENCIA va fuera; el de éxito viaja con su transacción.

### 8.5 ❗ BLOQUEANTE · El paso 4 se verifica con instrumentos que NO ejecutan el código mudado

`purchase:verify-oversell` conduce `OrderCreator::createPendingOrder`; `redsys:verify-concurrency`
conduce `RedsysReturnHandler`. **Cero referencias a `ViewOrder`, `lockZoneDaySlots` o
`executeItemEdit` en ninguno de los dos**: el paso 4 no toca esos ficheros, así que su verde es
trivial respecto a lo mudado. Y el lock que de verdad se muda pertenece a **`AFORO-05`** —que
documenta que su alcance zona/día **no tiene assert** (SQLite no reproduce la carrera)—, no solo a
`AFORO-01`; la spec no la nombra. Salidas: **(a)** extender un verificador con un escenario de
edición de panel (dos ediciones concurrentes con tramos solapados en franjas distintas — el bug que
motivó el lock, según su propio docblock), viéndolo fallar antes de creerle el verde; o **(b)**
añadir `AFORO-05` a §5 y declarar en §6 que ese hueco persiste y la red del alcance del lock es la
revisión por query-log.

### 8.6 Las cifras que no eran medida: «25 llamadas» son 8 invocaciones reales

Los «×N» de §1.2 son **apariciones de la subcadena** (comentarios y definiciones incluidos), no
llamadas — reproducible: `for n in executePartialRefund executeItemEdit applyExtraDue
lockZoneDaySlots executeFullRefund; do grep -o "$n" app/Filament/Resources/Orders/Pages/ViewOrder.php | wc -l; done`
da 7+7+7+3+1=25. Invocaciones reales: **8** — `applyExtraDue` ×3, `executeFullRefund` ×1,
`executePartialRefundBatch` ×1, `lockZoneDaySlots` ×2, `executeItemEdit` ×1 (interna).
**`executePartialRefund` a secas tiene CERO llamadas desde aquí** (el método invocado es
`executePartialRefundBatch`; el ×7 cuenta «Batch» como subcadena y 6 comentarios), y «se llama 3
veces» de §5 son 2 llamadas + la definición. La cifra inflada se repite en §5, §7 y `DEUDA.md`
(corregida allí con esta revisión). El diagnóstico no cambia; el instrumento era el de `#143`:
un grep que cuenta comentarios como código.

### 8.7 Las extracciones 1 y 3 están ACOPLADAS, y la 1 no es autocontenida

- **Dominio→calendario**: `executeManageItemSave` (uno de los 14 de orquestación) lee
  `calendarSelectedDate`/`calendarSelectedTime` como camino primario del calendario interactivo (el
  fallback a `$data['slot_*']` solo cubre el camino `callAction` de tests/automatización — lo
  declara el comentario del propio método). También leen ese estado `priceDiffPreview` y
  `addonDiffPreview` (presentación, extracción 2). Hay que decidir el mecanismo de paso de
  fecha/hora ANTES de sostener el «riesgo Mínimo».
- **Calendario→disponibilidad**: `calendarMatrixForItemWithSelection` y `calendarSelectDate` llaman
  a `selectableDatesInRange` (extracción 3), y `calendarTimesForItem`/`calendarTimesForItemWithSelection`
  componen su PROPIA oferta con `Slot::query()` + `displayAvailableFor`. **La disponibilidad VIVA
  del panel está DENTRO del calendario**: la comparación del paso 3 debe cubrirla, y el perímetro
  «~219 líneas» de la extracción 3 está infracontado.
- **Código muerto en el perímetro**: de los 3 métodos de la categoría «disponibilidad», DOS están
  MUERTOS — `availableDatesForItem` y `availableTimesForItem` no tienen NINGÚN llamador de
  producción (verificado por grep de invocación y pickaxe desde el commit fundacional; son privados
  y solo los ejecuta `ReflectionMethod` en tests). `calendarMatrixForItem` (la variante sin
  selección) igual: cero referencias en todo el repo. **Se retiran por `CONVENCIONES §3.quater`
  ANTES de mudar nada, no se mudan.** Y `selectableDatesInRange` —la única viva— no tiene test
  directo, solo cobertura indirecta.

### 8.8 «Concern/componente» no son intercambiables, y el criterio de §2.1 dispara en falso

- **75 interacciones de test** (`set`/`assertSet`/`call` sobre las props y métodos `calendar*`, en 5
  ficheros, todas vía `Livewire::test(ViewOrder::class)`) más el camino de runtime de
  `executeManageItemSave` dependen de que el calendario siga siendo del MISMO componente Livewire.
  Un componente hijo rompe las 75 y el runtime; **solo la rama Concern (trait en la misma clase)
  cumple «cero cambios de conducta»**. §4.1 debe fijar Concern y descartar componente.
- **36 métodos de test usan `ReflectionMethod` sobre 10 métodos privados** de la clase
  (`validateNewSlot`, `computeEditPricing`, `buildCalendarViewData`, `availableDatesForItem`…).
  Cualquier extracción los rompe POR CONSTRUCCIÓN — la reflexión nombra la clase. El criterio «un
  test que haya que reescribir es la señal de alarma» (§2.1/§6.1) necesita la cláusula: re-apuntar
  una reflexión al nuevo dueño del método es mudanza esperada; lo que no se toca es un **assert**.

### 8.9 La SEGUNDA duplicación de dominio, que §1.4 negaba

`lockZoneDaySlots` es la segunda implementación a mano de la receta anti-sobreventa de `AFORO-01`
(ids literales sin subconsulta + `orderBy('id')` + `FOR UPDATE` primero): su docblock declara «MISMO
alcance que `OrderCreator::lockSlots`», y el porqué de cada pieza (el bug de la subconsulta que fija
el snapshot antes del lock) está documentado SOLO en el fichero del dominio. Si alguien corrige la
del dominio, la del panel deriva en silencio. §1.4 decía que la única duplicación «real y completa»
era la disponibilidad; son dos, y ésta es exactamente la pieza crítica. El paso 4 decide si el
servicio extraído consolida en UN helper de lock.
⚠️ Y dentro del propio `executeItemEdit` el **waterfall de créditos** (extra_due → gate credit →
deposit remainder → marcador) está escrito DOS veces (edición del ítem y re-escala per-invitado),
con una asimetría en la condición del marcador que hay que verificar al extraer (¿intencionada o
deriva?). Se extrae como UNA pieza; por copia, la duplicación viaja con la mudanza.

### 8.10 La tabla de invariantes de §5 está incompleta

**Cuatro invariantes citan a `ViewOrder` LITERALMENTE en su columna de código y no están en §5**:
`AFORO-05` (el lock zona/día — ver §8.5), `AFORO-06` (`excludeItemId` en el re-agendado),
`RGPD-02` (`eventDataDiffKeys`: los diffs de `event_data` registran solo claves, jamás valores) y
`SEC-04` (el re-check de `orders.cancel` en el handler del reembolso). Además la tocan sin cita
literal: `AFORO-02` (el paso 3 ES su ejecución — ver §8.3), `AFORO-09` (los 14 `Carbon::today()`
crudos), `PAY-13` (el panel está exento de suelo/antelación a propósito — decidir si debe seguir) y
`PAY-16` (sus escritores —`applyExtraDue`, `applyGateCredit`, `applyDepositRemainderCredit`,
`recordReductionMarker`— son exactamente lo que el paso 4 muda; §5 lista `PAY-17` con la misma
justificación y a éste lo omite).

### 8.11 Matices menores (ninguno bloquea; se corrigen al retocar cada sección)

- La quinta propiedad es `auditPerPage` (el TAMAÑO de página), no «el paginador»; y el estado
  Livewire real incluye los heredados `$record`, `$data`, `$mountedActions`, `$paginators` — la
  tesis sobrevive porque son de la infraestructura, pero la frase literal cuenta solo lo declarado.
- La tabla de §1.2 está medida por BLOQUES (método + su docblock/hueco) y no lo declara; quien
  re-mida por cuerpos Reflection obtendrá otras cifras en todas las filas (p. ej. orquestación
  1.542) y creerá la tabla caducada. La cláusula de invalidación de la cabecera exige declarar el
  instrumento.
- «`calendar*`» significa «contiene Calendar», no prefijo: por prefijo son 10, y los 3 restantes
  (`buildCalendarViewData`, `resetCalendarStateForItem`, `ensureCalendarStateInitialized`) se
  quedarían huérfanos en el paso 1. «Filament = 20 métodos» no es reproducible con los patrones
  declarados (salen 14; con `*Tab*`, 18). `executeItemEdit` mide 471–472, no 475; los «12 mayores»
  dan 1.924–2.128 según instrumento (el 39 % se sostiene como orden de magnitud).
- Las ~96 funciones anónimas (closures + arrow-fns) no aparecen en la spec y son el pegamento real
  de los `*Action` que los pasos 2 y 4 van a cortar — capturan `$this` y `$record`.
- `blockEdit` NO es una guarda del trait: es capa de entrega (audit del rechazo + `Notification`).
  El predicado de dominio se consume vía `Order` (que es quien usa el trait). Y la familia de
  guardas son DOS traits: la mitad de REEMBOLSO vive en `Payments\Concerns\GuardsItemRefunds`, que
  la spec no nombra y el paso 4 necesita.
- El precedente de §3·C es real pero al revés: `HasItemActionGuards` se extrajo DE `Order` (lo dice
  su docblock), no «dentro del propio fichero» — sostiene la opción C igual, la frase es falsa.
- La red real es MAYOR y DISTINTA de la contada: 7 ficheros más conducen la página por ruta HTTP sin
  nombrar la clase (`OrderInfolistEnrichedTest` con 48, `ItemsListSubCardTest`…) — ~317 casos
  conduciendo en ~23 ficheros con criterio de conducción explícito — y el 5º del top-5
  (`ManualOrderWithoutEmailTest`) solo conduce la página en 2 de sus 17 casos (es una suite de
  `CreateManualOrderPage`). `calendarGoToItemMonth` tiene CERO tests (solo lo llama su parcial
  Blade): su mutación de §6.2 no morderá.
- El ancla temporal del diff de §6.3: ejecutarlo entre las 00:00 y las ~02:00 del parque arroja
  diferencias que son del RELOJ (UTC vs parque), no de las lógicas — los datasets del diff deben
  incluir a propósito «hoy con horas pasadas» y esa ventana.

### 8.12 Lo que esta revisión exigía antes del ✅ del owner — ✅ EJECUTADO el 2026-08-26

1. **Reescribir §4.3** con el mapa transaccional real (§8.4), incluida la distinción
   orquestador-financiero vs dueño-de-la-txn-de-aforo y la cláusula de los dos audits de éxito.
2. **Re-encuadrar el paso 3** como extensión/read-model de re-programación (§8.3): riesgo Alto,
   `VERIFY_CONC` ya en ese paso, diff POR EJE separando decisión documentada (los docblocks del
   origen citados en §8.3) de deriva, y las decisiones de producto que el diff destapará (`PAY-13`
   en el panel incluida), preguntadas al owner.
3. **Dar instrumento al paso 4** (§8.5): escenario de edición concurrente en un verificador (visto
   fallar), o `AFORO-05` en §5 + hueco declarado en §6.
4. **Corregir las cifras** de §1.2/§5/§7 (§8.6: 8 invocaciones, ×2 el lock, `Batch`) y los matices
   de §8.11 al tocar cada sección; declarar el instrumento de la tabla.
5. **Fijar Concern** y añadir la cláusula de reflexión al criterio §2.1 (§8.8).
6. **Retirar el código muerto ANTES de mudar** (§8.7): `availableDatesForItem`,
   `availableTimesForItem`, `calendarMatrixForItem`, auditando sus tests por `§3.quater`.
7. **Completar §5** con las cuatro invariantes de cita literal y las cuatro de roce (§8.10).

---

## 9. Ejecución — el paso a paso, con su evidencia

> ✅ **`[DECIDIDO owner, 2026-08-26]`: spec aprobada y ejecución autorizada**, con el encargo
> explícito de rigor, meticulosidad y verificación empírica.

### 9.1 Paso 0 · El código muerto, RETIRADO (2026-08-26)

**5.280 → 5.012 líneas (−268)** medidas con `wc -l`. Cayeron **CINCO métodos, no tres**: los tres
censados (`availableDatesForItem`, `availableTimesForItem`, `calendarMatrixForItem`) más los DOS que
la retirada dejaba huérfanos — `buildCurrentSlotOnlyTimeOption` y `formatTimeOption` solo los
llamaban los muertos (medido por grep de invocación antes de tocar). La clave de traducción
`current_marker` **se queda**: la usa el blade vivo del calendario. La documentación de la rejilla
(el docblock rico del muerto) se movió a `calendarMatrixForItemWithSelection` antes de borrar, y las
dos referencias de código colgantes se re-apuntaron (`PaymentSettings` y el comentario de
`validateNewSlot`).

▶ **Los 3 tests por reflexión se RE-APUNTARON a la fuente viva** (`§3.quater`, categoría 3 — usar el
muerto como intermediario de una regla que sobrevive), y los tres MEJORAN la red:

| Test viejo (sobre el muerto) | Test nuevo (sobre lo vivo) | Lo que gana |
|---|---|---|
| `test_available_dates_excludes_dates_beyond_horizon` | `test_selectable_dates_in_range_excludes_beyond_horizon_and_keeps_valid_dates` | `selectableDatesInRange` gana su **primer test directo** (lo pedía §6·2). ⚠️ El viejo estaba **verde por el motivo equivocado**: creaba el slot lejano sin `online_sales_open`, así que lo excluía `sellableOnline()`, no el horizonte. El nuevo lo crea plenamente vendible |
| `test_available_dates_marks_current_slot_date_with_actual_marker` | `test_calendar_matrix_marks_current_slot_day_as_current_and_selectable` | Primer assert de `is_current`/`selectable` sobre la MATRIZ (antes solo lo tenía la lista de horas), con el día vecino como control negativo |
| `test_available_times_includes_current_slot_when_date_matches` | `test_calendar_times_include_current_slot_even_when_park_closed` | La rama defensiva «el slot ACTUAL siempre se ofrece» **no tenía test**: ahora se prueba con el parque CERRADO y se asevera que responde SOLO ella (exactamente 1 entrada) |

▶ **Verificado por mutación, 4 de 4 muerden** — cada una con su ancla comprobada ÚNICA, el `grep`
de dónde cayó, el test objetivo en ROJO y la restauración verificada por **md5 byte-exacto**:
quitar el recorte de horizonte de `selectableDatesInRange` · `is_current => false` en la matriz ·
`selectable => false` en la matriz · anular la rama siempre-incluido de `calendarTimesForItem`.
▶ Suite completa **2935 en verde (16.959 aserciones)** · Pint limpio · `php -l` limpio.

⚠️ **Trampa pagada y regla que deja** (la 5ª de `§3.quater`): durante la primera mutación, el
`git checkout` de la restauración devolvió el fichero al **HEAD commiteado** — y el paso 0 entero
eran cambios sin commitear: se restauró el estado EQUIVOCADO y hubo que re-aplicar el borrado (se
verificó byte-idéntico por md5 contra la línea base). **La extracción se commitea en local ANTES de
empezar a mutar**: mutar sobre árbol sucio convierte cada restauración en una pérdida silenciosa.

▶ Fósil que confirma el diagnóstico de §8.3, anotado al leer antes de borrar: el muerto
`availableTimesForItem` **inflaba** las plazas del slot actual (`available += seats`) — la conducta
que la decisión de la clienta del origen (fidedigno, plazas reales) sustituyó. El código muerto no
era solo ruido: era la política VIEJA esperando a que alguien la leyera como vigente.

### 9.2 Extracción 1 · El calendario → Concern — HECHA (2026-08-26)

**4.505 líneas** quedan en `ViewOrder` (desde 5.012; −507). El calendario entero —las 4 propiedades
Livewire y los 12 métodos que quedaban de la familia tras el paso 0— vive en
`Pages/Concerns/ManagesItemCalendar` (544 líneas), **trait sobre la MISMA clase** como §4.1 fijó:
las properties y los wire methods siguen perteneciendo a `ViewOrder`, así que ni las 75
interacciones de test ni el partial blade ni `executeManageItemSave` notan el cambio.

- **Antes de mover, la pieza sin red ganó su test**: `calendarGoToItemMonth` (cero tests, §8.11)
  tiene ahora el suyo — monta la acción, navega un mes (assert intermedio: se navegó de verdad) y
  el atajo vuelve al mes del ítem. Su mutación (no escribir el mes) vista MORDER antes del
  movimiento.
- **Fidelidad del movimiento verificada por diferencia de conjuntos**: cero líneas borradas de
  `ViewOrder` ausentes del trait (`comm -23` sobre el diff), y `ViewOrder` solo ganó 2 líneas (el
  import y el `use`). El único import que quedó huérfano (`CarbonPeriod`) se retiró.
- **Mutación de la extracción**: retirar el `use ManagesItemCalendar;` deja **14 tests del
  calendario en rojo** de golpe. Restauración verificada por md5.
- **Lo que el trait declara como deuda de las extracciones siguientes** (en su docblock):
  `selectableDatesInRange` y `displayAvailableFor` se quedan en `ViewOrder` y son la disponibilidad
  que la extracción 3 se lleva al dominio — el trait los llama por `$this->` y ese es el punto de
  costura.
- Suite **2936 / 16.961 en verde** (+1 test, +2 aserciones) · Pint ✓ · `php -l` ✓.

### 9.3 Extracción 2 · Presentación → Concern de entrega — HECHA (2026-08-26)

**4.014 líneas** quedan en `ViewOrder` (desde 4.505; −491). Los 14 métodos de presentación —6
`*Notification`, 3 `render*`, 2 `*Preview`, 3 `*Options`— viven en
`Pages/Concerns/PresentsOrderActions` (535 líneas). Cero asserts tocados; la suite pasó a la
primera.

⚠️ **Desviación DECLARADA respecto a la palabra de §4.1 («un `*Presenter`»), y su medida**: antes de
mover se midieron las dependencias `$this->` de los 14 (Reflection + grep por método), y los dos
`*Preview` no «solo componen textos»: **componen SOBRE cómputos del dominio** (`computeEditPricing`,
`computeAddonPricing`, `applyGroupChoices`, `normalizeAddonEdits`) que la extracción 4 va a mover, y
`buildRefundItemsOptions` necesita `record` + `resolveItem` + `refundItemOptionLabel`. Una clase
Presenter habría fijado HOY firmas que la extracción 4 rompería mañana — especulación del enchufe,
el error que `#37` enseñó a no cometer. **Se extrajo como trait de entrega** (mismo patrón que la
1): separa la responsabilidad, deja las costuras visibles en su docblock, y el Presenter-clase, si
procede, se decide en el paso 4 con las firmas reales delante.

- **Fidelidad por diferencia de conjuntos**: 0 líneas ausentes; `ViewOrder` gana solo el import y
  el `use`. Import huérfano retirado (`OrderItemRefunded`).
- **La reflexión de `sameScopeProductOptions` no se tocó**: `ReflectionMethod` sobre la clase
  resuelve métodos de trait — cero re-apuntes.
- **Mutación de la extracción**: retirar el `use PresentsOrderActions;` deja **19 de 61 tests de
  `OrderAdminActionsTest` en rojo**. Restauración verificada.
- Suite **2936 / 16.961 en verde** (sin cambios: pura mudanza) · Pint ✓ · `php -l` ✓.

### 9.4 Extracción 3 · La oferta de re-programación al DOMINIO — HECHA (2026-08-26, `VERIFY_CONC` ✓)

**3.907 líneas** quedan en `ViewOrder` (desde 4.014) y el trait del calendario baja de 544 a **431**:
la composición de la oferta vive en **`Booking\Services\ItemRescheduleOffer`** (252 líneas), un
servicio NUEVO del dominio — no una extensión del contrato de compra, porque responde a OTRA
pregunta (mover vs comprar, §8.3). El trait ya solo DECORA (display, is_selected) y guarda estado
Livewire. `AFORO-02` queda aplicada al panel: **`ViewOrder` no compone ninguna oferta**.

**Las TRES reglas del contrato las decidió el owner ANTES de escribirlo** (`[DECIDIDO owner,
2026-08-26]`, preguntadas con recomendación y las tres aceptadas):
1. **Ancla temporal = zona del PARQUE** (`DisplayTime`), como la oferta pública — cambia conducta
   SOLO en la ventana 00:00–02:00 del parque, donde el ancla UTC ofrecía/bloqueaba el día
   equivocado. Con test que CRUZA la frontera (a las 00:30 de Madrid un día que en UTC aún es «hoy»
   no se ofrece) — el caso que `AFORO-09` declaraba no tener.
2. **Exención del panel DECLARADA**: sin antelación mínima ni corte intra-día (mostrador). Deja de
   ser herencia por accidente: está en el docblock del contrato.
3. **Horas sin aforo se OCULTAN** (salvo la actual). ⚠️ **Su mutación salió VERDE en toda la red**
   — la regla existía desde el origen y no la probaba nadie: ganó su test antes de cerrar.

**Cómo se verificó** (por orden):
- **Sonda A/B ANTES de conmutar** (§6·3): la composición vieja (por reflexión) contra el servicio
  nuevo, sobre los **27 ítems con slot de la BD real** — fechas (rango de 3 meses) y horas del día
  del slot: **0 divergencias** (ejecutada lejos de la ventana nocturna: las dos anclas coincidían,
  UTC = parque = 2026-08-26). La receta, en el scratchpad de la sesión; instrumento, no guarda.
- **4 mutaciones sobre el servicio, 4 muerden** (una tras ganar su test): `selectableDates` muerto
  → 5 rojos · rama siempre-incluido → rojo · ancla a UTC → el test nocturno rojo · regla de ocultar
  → rojo con su test nuevo.
- **`validateNewSlot` re-ancló con la MISMA fuente** (`ItemRescheduleOffer::today/horizon`): si la
  validación siguiera en UTC, entre las 00:00 y las ~02:00 rechazaría como pasada una fecha que el
  calendario acaba de ofrecer.
- ⚠️ **La flecha de módulos la cazó el arch-test** (`ModuleBoundariesTest`): el servicio nuevo no
  puede abrir otra flecha Booking→Payments (la baseline de `PaymentSettings` SOLO ENCOGE). La
  familia de la oferta comparte fuente: `SlotOffer::horizonMonths()` (accessor nuevo de una línea)
  y el servicio lee por ahí. **Tocar `SlotOffer` disparó `VERIFY_CONC`**: los DOS verificadores
  corridos sobre MySQL real — `purchase:verify-oversell` en sus **5 escenarios** con 8 workers
  (5/5 PASA) y `redsys:verify-concurrency` (PASA).
- Los dos fallbacks de fecha de TARIFICACIÓN (`computeEditPricing`/`computeAddonPricing`) **siguen
  en UTC a propósito**: son dinero (`PAY-18`) y el ancla de tarifa se examina en la extracción 4,
  no aquí.
- De regalo: las DOS copias casi idénticas de la lista de horas (`calendarTimesForItem` /
  `WithSelection`) quedan consolidadas en una decoración sobre la misma oferta, y el `$isPack` que
  ambas computaban sin usar murió en el trasplante.
- Suite **2938 / 16.966 en verde** (+2 tests: el nocturno del ancla y el de ocultar) · Pint ✓ ·
  `php -l` ✓ · `AFORO-02`/`AFORO-09` actualizadas en `INVARIANTES.md`.

### 9.5 Extracción 4 · La orquestación de dinero — PENDIENTE (la última)
