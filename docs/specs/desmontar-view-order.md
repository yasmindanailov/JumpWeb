# [SPEC] Desmontar `ViewOrder` — el god-class del panel

> Estado: diseño 🟦 **pendiente de revisión adversarial** (`CONVENCIONES §5`) y del ✅ del owner ·
> Última actualización: 2026-08-26 ·
> Verificado contra código: 2026-08-26 (el fichero entero, sus 98 métodos, su red de tests, y qué
> contratos del dominio usa — ninguno) ·
> Decisión asociada: `DECISIONES #165` ·
> Se invalida si: alguien mueve código dentro del fichero sin re-medir la descomposición de §1.2.
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
| **1** | **El calendario** → su propio `Concern`/componente | ~524 líneas · 13 métodos · **las 4 propiedades** | **Mínimo** | Autocontenido y sin dinero. Es el ensayo del método: si esto sale limpio, el resto es lo mismo con más cuidado |
| **2** | **Presentación y notificaciones** → un `*Presenter` de la capa de entrega | ~460 líneas | Bajo | No toca dominio; solo compone textos. Reduce ruido antes de entrar en lo serio |
| **3** | **Las lecturas de disponibilidad** → **`Booking\Contracts\AvailabilityOffer`, que YA EXISTE** | ~219 líneas | ⚠️ Medio | No es mudanza: es **sustitución por el contrato**. Ver el aviso de abajo |
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

`executeItemEdit` y familia orquestan **dentro de una transacción**: guardas (`blockEdit` ×9),
`applyExtraDue`, `recordReductionMarker`, `applyGateCredit`, `applyDepositRemainderCredit`, y los
cálculos de `itemExtraDueCents`/`itemDepositRemainderCents`.

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
- **Entrada final**: `DECISIONES #165`.
