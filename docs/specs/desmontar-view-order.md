# [SPEC] Desmontar `ViewOrder` — el god-class del panel

> Estado: diseño 🟦 · **revisión adversarial HECHA (2026-08-26, §8): el diagnóstico y la opción C
> sobreviven; TRES bloqueantes y el checklist §8.12 van ANTES del ✅ del owner** ·
> Última actualización: 2026-08-26 ·
> Verificado contra código: 2026-08-26 (el fichero entero, sus 98 métodos, su red de tests, y qué
> contratos del dominio usa — ninguno; re-verificado por la revisión §8 con 7 medidores) ·
> Decisión asociada: `DECISIONES #165` (la spec) + la entrada de la revisión ·
> Se invalida si: alguien mueve código dentro del fichero sin re-medir la descomposición de §1.2.
>
> ⚠️⚠️ **LEE §8 ANTES QUE EL CUERPO**: tres afirmaciones del cuerpo resultaron FALSAS al medirlas
> (las «25 llamadas» son 8 invocaciones · §4.3 describe DENTRO de la transacción lo que corre FUERA ·
> los verificadores de §6.4 no ejecutan el código mudado) y el paso 3 compara dos preguntas
> distintas. Cada sección afectada lleva su marcador.
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

98 métodos en 5.280 líneas. Repartidos por lo que hacen:

| | Líneas | Métodos | Qué es | ¿Es de la capa de entrega? |
|---|---|---|---|---|
| **Orquestación de dominio** (`execute*`, `compute*`, `validate*`, `save*`, `apply*`) | **1.699 (32 %)** | 14 | Editar un ítem, reembolsar, cancelar, cambiar franja, tarificar complementos | ❌ **No** |
| Presentación y notificaciones (`*Notification`, `render*`, `*Preview`, `*Options`) | ~460 (9 %) | ~10 | Los textos y avisos que ve el operador | ✅ Sí |
| Lecturas de disponibilidad (`availableDatesForItem`, `availableTimesForItem`, `selectableDatesInRange`) | ~219 (4 %) | 3 | Qué días y horas se pueden elegir | ❌ **No** (§1.4) |
| Acciones y composición Filament (`*Action`, `*Form`, `*Field`) | 1.223 (23 %) | 20 | Los 10 botones y sus formularios | ✅ Sí |
| Calendario (`calendar*`) | 524 (9 %) | 13 | El selector de fecha/hora del panel | ✅ Sí (estado de UI) |
| Ayudantes varios | ~1.150 (22 %) | ~38 | Mezcla de las dos naturalezas | ⚠️ Hay que clasificar uno a uno |

▶ El método más grande es **`executeItemEdit`, con 475 líneas**. Los 12 mayores suman **2.068
líneas, el 39 % del fichero**.
▶ Toca dinero y aforo en **25 llamadas**: `executePartialRefund` ×7, `executeItemEdit` ×7,
`applyExtraDue` ×7, `lockZoneDaySlots` ×3, `executeFullRefund` ×1.
⚠️ **REVISADO (§8.6): esa cifra cuenta comentarios y definiciones — las invocaciones reales son 8**,
`executePartialRefund` a secas tiene CERO llamadas (el método real es `executePartialRefundBatch`) y
el lock se llama ×2, no ×3.

### 1.3 ❗ El dato que cambia el diseño: es GRANDE, no está ENMARAÑADO

**Solo tiene CINCO propiedades públicas de Livewire**, y **cuatro son del calendario**
(`calendarItemId`, `calendarMonth`, `calendarSelectedDate`, `calendarSelectedTime`); la quinta es el
paginador de la auditoría. La composición de la ficha ya vive fuera (`Schemas/OrderInfolist.php`).

▶ **Traducido**: el estado mutable compartido es mínimo, así que las piezas **no están entrelazadas
por estado** — están simplemente amontonadas. Eso convierte el desmontaje en una serie de
extracciones acotadas en vez de una reescritura. **Un god-class con veinte propiedades compartidas
sería otro proyecto.**

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

**16 ficheros de test conducen `ViewOrder`, con ~285 casos.** Los cinco mayores:

    OrderAdminActionsTest              61
    ManageItemQuantityProductTest      47
    ManageItemSlotChangeTest           33
    ManageItemAddonsTest               28
    ManualOrderWithoutEmailTest        17

▶ Sin esa red, desmontar este fichero sería temerario. Con ella, la conducta está fijada y cada
extracción se puede verificar **por mutación**.
⚠️ **Y NO está en el `CRITICAL_RE`** del `pre-push` (comprobado), así que hoy tocarlo no exige
`VERIFY_CONC=1`. §5 discute si eso debe seguir siendo verdad después.

---

## 2. Objetivo

**Que la clase de ENTREGA deje de contener el DOMINIO, sin cambiar ni una sola conducta observable.**

Criterios de éxito, todos medibles:

1. **Cero cambios de conducta.** La red de ~285 casos pasa sin tocar un solo assert. Un test que haya
   que reescribir es la señal de que la extracción cambió algo — y entonces se para y se mira.
2. La orquestación de dinero/aforo vive en **`app/Domain/`**, detrás de un contrato, como el resto del
   dominio desde Fase 2.
3. `ViewOrder` baja de 5.280 líneas a **lo que sea composición de Filament y estado de UI**, y esa
   cifra se declara MEDIDA al cerrar, no estimada.
4. El panel deja de tener **su propia disponibilidad**: la pide por `AvailabilityOffer`.
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

### 4.1 Las cuatro extracciones, en orden

| | Qué sale | Tamaño | Riesgo | Por qué va aquí |
|---|---|---|---|---|
| **1** | **El calendario** → su propio `Concern`/componente | ~524 líneas · 13 métodos · **las 4 propiedades** | **Mínimo** | Autocontenido y sin dinero. Es el ensayo del método: si esto sale limpio, el resto es lo mismo con más cuidado. ⚠️ **REVISADO (§8.7/§8.8): NO es autocontenido** (el dominio lee sus props y el calendario CONTIENE disponibilidad viva) **y solo la rama Concern cumple §2.1** — «componente» se descarta |
| **2** | **Presentación y notificaciones** → un `*Presenter` de la capa de entrega | ~460 líneas | Bajo | No toca dominio; solo compone textos. Reduce ruido antes de entrar en lo serio |
| **3** | **Las lecturas de disponibilidad** → **`Booking\Contracts\AvailabilityOffer`, que YA EXISTE** | ~219 líneas | ⚠️ Medio → ❗ **Alto (§8.3)** | ~~No es mudanza: es **sustitución por el contrato**~~ ⚠️ **REVISADO (§8.3): NO es sustituible — son dos preguntas distintas (comprar vs mover), se re-diseña como EXTENSIÓN, y dispara `VERIFY_CONC` ya en este paso** |
| **4** | **La orquestación de dinero/aforo** → servicio de dominio con contrato propio | ~1.699 líneas · 14 métodos | ❗ **Alto** | `executeItemEdit` (475 líneas) y familia. La última, con el método ya probado tres veces |

### 4.2 ⚠️ El paso 3 puede destapar una diferencia de CONDUCTA, y eso no es un fallo de la extracción

El panel y el contrato pueden **no ofrecer las mismas fechas**: llevan meses evolucionando por
separado. Si al sustituir aparece una diferencia, **es un hallazgo, no un error de la mudanza**, y
tiene tres salidas y solo una es válida:

1. ❌ Ajustar el contrato para que se parezca al panel **sin decidir cuál es correcto**.
2. ❌ Dejar la implementación del panel «por si acaso».
3. ✅ **Medir cuál de las dos es correcta, decidirlo, y anotarlo.** Si el panel ofrecía días que el
   contrato no, o al revés, eso es una diferencia de producto que alguien tiene que zanjar.

⚠️ **Y hay que medirla ANTES de mover nada**: comparar la salida de las dos implementaciones sobre los
mismos datos. Si coinciden, el paso 3 es mecánico. Si no, el paso 3 **se para** y se abre la decisión.

### 4.3 El paso 4: qué contrato, y qué NO se toca

⚠️⚠️ **REVISADO (§8.4): el párrafo siguiente describe AL REVÉS el mapa transaccional real** — las
guardas van ANTES de toda transacción, la transacción contiene SOLO aforo/mutación, y toda la
secuencia financiera corre DESPUÉS del commit con transacciones propias en `Order`. Se reescribe con
el mapa de §8.4 antes del ✅ (checklist §8.12·1). Se conserva tachado como testimonio del error:

~~`executeItemEdit` y familia orquestan **dentro de una transacción**: guardas (`blockEdit` ×9),
`applyExtraDue`, `recordReductionMarker`, `applyGateCredit`, `applyDepositRemainderCredit`, y los
cálculos de `itemExtraDueCents`/`itemDepositRemainderCents`.~~

▶ Lo que se mueve es **la secuencia**, no las piezas: los métodos de `Order` se quedan donde están.
Es la misma doctrina que `CheckoutOrchestrator` (`#37`): **el sitio único donde vive el ORDEN**.
⚠️ **No se envuelve la secuencia en una transacción nueva ni se cambia la que hay.** `PAY-05` y
`AFORO-01` fijan qué puede y qué no puede vivir dentro de una transacción con locks; una extracción no
es el sitio para revisarlo.
⚠️ **Y las guardas ya tienen casa**: `HasItemActionGuards` está en el dominio. El contrato nuevo se
apoya en ella en vez de reimplementarla.

### 4.4 Lo que NO se decide en esta spec, a propósito

- **El nombre del contrato y su forma exacta**: sale del paso 4, cuando las tres extracciones
  anteriores hayan enseñado dónde están las costuras reales. Fijarlo ahora sería especulación — la
  misma razón por la que `#37` aplazó el segundo driver de pasarela.
- **Si `ViewOrder` debe seguir siendo una sola clase** al final. Se decide con la cifra en la mano.

---

## 5. Impacto en invariantes

⚠️ **REVISADO (§8.10): esta tabla está INCOMPLETA** — faltan cuatro invariantes que citan a
`ViewOrder` literalmente (`AFORO-05`, `AFORO-06`, `RGPD-02`, `SEC-04`) y cuatro que el plan roza
(`AFORO-02`, `AFORO-09`, `PAY-13`, `PAY-16`). Y «se llama 3 veces» son 2 llamadas + la definición
(§8.6). Se completa antes del ✅ (checklist §8.12·7).

| ID | Impacto |
|---|---|
| **AFORO-01** | ⚠️⚠️ **El más expuesto.** `lockZoneDaySlots` se llama 3 veces desde aquí. El lock con `zone_id` literal debe seguir siendo la PRIMERA sentencia de su transacción: una extracción que meta un `SELECT` antes lo rompe **sin que ningún test lo vea** (SQLite no reproduce la carrera) |
| **PAY-05** | Se CITA: el rastro de incidencia no puede quedar dentro de un rollback. La extracción no cambia dónde empieza y acaba la transacción |
| **PAY-09 · PAY-10 · PAY-17 · PAY-18** | Sus reglas viven en los métodos que se mudan. **Ninguna se relaja**: el criterio de éxito es cero cambios de conducta |
| **SUITE-04** | ⚠️ La suite es ciega a las carreras InnoDB. Por eso el paso 4 **no puede** apoyarse solo en la red de tests |
| **CRITICAL_RE del `pre-push`** | ❗ **Decisión pendiente**: hoy `ViewOrder` NO está en la lista, así que tocarlo no exige `VERIFY_CONC`. Si su orquestación de dinero pasa a un servicio de dominio, **ese servicio probablemente sí deba entrar** — y `CriticalPathGateTest` vigila que la lista cubra lo que debe. Se decide en el paso 4, no antes |

---

## 6. Plan de verificación empírica

Sin esto no puede llegar a ✅ (`CONVENCIONES §3.bis`).
⚠️ **REVISADO**: el punto 1 necesita la cláusula de reflexión (§8.8 — 36 tests rompen POR
CONSTRUCCIÓN en cualquier extracción) y el punto 4 usa verificadores que NO ejecutan el código
mudado (§8.5 — el hueco real es `AFORO-05`).

1. **La red de ~285 casos pasa sin tocar un solo assert**, en cada extracción. ⚠️ Un test que haya que
   reescribir **para** una extracción es la señal de alarma, no un trámite.
2. **Mutación por extracción**: retirar la pieza extraída deja en rojo los casos que la cubren. Una
   extracción cuya mutación no muerde es una extracción que nadie estaba probando — y eso se arregla
   antes de moverla, no después.
3. **Paso 3, ANTES de mover**: comparar la salida de la disponibilidad del panel con la del contrato
   sobre los mismos datos. Si difieren, el paso se para (§4.2).
4. **Paso 4, con los DOS verificadores de concurrencia** (`purchase:verify-oversell`,
   `redsys:verify-concurrency`) sobre MySQL real, y **viendo fallar el instrumento** antes de creerle
   el verde — la regla de `#147`.
5. **Navegador**: las 10 acciones del panel recorridas a mano tras el paso 4. La suite no ve un
   formulario de Filament que deja de montarse.
6. **La cifra final se MIDE**: `wc -l` de `ViewOrder` al cerrar, y se declara en `DEUDA.md`. No se
   estima.

---

## 7. Revisión y decisión

- **Medido por el agente B** el 2026-08-26 contra el código: los 98 métodos, su reparto por
  responsabilidad, las 5 propiedades públicas, las 25 llamadas a dinero/aforo, la red de 16 ficheros y
  ~285 casos, los contratos que usa (**ninguno**) y el tamaño de `Order.php` como destino descartado.
- ❗ **PENDIENTE de revisión adversarial por el otro agente** (`CONVENCIONES §5`), antes de escribir
  código. En este proyecto esa revisión ha parado bloqueantes reales: `#122` encontró dos, `#123`
  declaró un diseño INSUFICIENTE y `#156` encontró que una spec descansaba sobre un hecho que el
  sistema no podía observar.
- ❗ **PENDIENTE del owner**: el ✅, y la pregunta de si esto se hace **ahora** o espera. No es urgente
  —el fichero funciona— pero **crece**, y cada tanda de dinero que entra lo engorda.
- **Entrada final**: `DECISIONES #165`. La revisión adversarial: **§8** y su entrada de `DECISIONES`.

---

## 8. Revisión adversarial (2026-08-26) — el diagnóstico SOBREVIVE; el plan necesita corrección

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

### 8.12 Lo que esta revisión exige ANTES del ✅ del owner

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
