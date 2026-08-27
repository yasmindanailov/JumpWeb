# [SPEC] Menores a cargo

> Estado: 🟦 **REVISADO (§8) y EN EJECUCIÓN — TANDAS 1, 2 y 3 EN EL ÁRBOL (2026-08-27, carril A):
> la 1 es el núcleo en Identity + la API (`#191`); la 2, la FIRMA DEL MENOR (`#198`) con la cadena por
> (titular, sujeto) que decidió el owner (`#197`); la 3, la ZONA DEL CAJÓN (`#199`), medida y con su
> guion en headless 20/20. La TANDA 4 —la asignación en el embudo— tiene su DISEÑO DE EJECUCIÓN
> escrito y medido (§9.9, `#202`) y arranca por la unidad 0.** ▶ **EMPIEZA POR §9.9** —§9.9.2 las
> cuatro decisiones del owner (⚠️ la exención firmada es CONDICIÓN para asignar, y el panel ENTRA),
> §9.9.1 lo que el código corrige al cuerpo (§4.7 «esa pantalla ya existe» era FALSA; la cesta del
> propio titular se PURGA al nacer abierto el cajón), §9.9.3 el diseño y §9.9.4 las unidades— ·
> Última actualización: 2026-08-27 noche (§9.9) · anteriores: §9.8, 2026-08-25 ·
> ⚠️ **§8.1 CORRIGE a §4.8**: las respuestas del evento también viven en `cart.js`, que **sí** se
> persiste — y el mecanismo que hay que extender es su **lista blanca**, no `selection.js`. §8.2 añade
> la trampa del sobre versionado, que la spec no nombra. Léelas antes que §4.8.
>
> Última actualización anterior: 2026-08-24 ·
> Verificado contra código: 2026-08-24 (ModuleBoundariesTest, machine.js, stores/selection.js, cart.js) ·
> Decisión asociada: `DECISIONES #142` ·
> Se invalida si: cambia el modelo de waiver de `waiver-probatorio.md`, o el embudo gana un paso.

Subsistema **C** de la visión de Fase 6. Va **después** de `waiver-probatorio.md`, porque el waiver de
un menor es el mismo mecanismo aplicado a otro sujeto.

---

## 1. Contexto y problema

Solo se registran adultos. Quien trae a un menor tiene que **hacerse responsable de él por escrito**,
y hoy no hay dónde: la web no conoce a los menores, solo los ve como texto libre dentro de un pedido.

**Lo que hay hoy, y por qué no sirve:** los datos de menores viven en `order_items.guest_data` y
`order_items.event_data` — JSON, sin FK, y **con alergias**, que son datos de salud (art. 9). Están
atados a **una reserva concreta** y se vacían al anonimizar. No son una relación de responsabilidad:
son el contenido de una fiesta.

## 2. Objetivo

Que un titular pueda declarar de quién es responsable, firmar el waiver por cada uno, y que la puerta
lo vea **sin conocer sus nombres**.

**Criterios de éxito medibles:**
- El titular añade y retira menores desde el cajón, con tope de servidor.
- Cada menor tiene su firma de waiver, con el mismo mecanismo del subsistema B.
- El escaneo en puerta devuelve **edad y estado del waiver, jamás el nombre** — mutación obligatoria.
- La edad **no existe como columna** en ninguna tabla.
- Retirar un menor con historial **no borra nada**.

**FUERA de alcance:**
- ⚠️ **Grupos escolares** — retirado por decisión del owner en `DECISIONES #142`: un profesor no
  ostenta la patria potestad y el registro que produciría puede no valer lo que aparenta. Se llevó
  cinco piezas: alta manual por el admin, estado «pendiente de waiver» de origen externo, permiso
  nuevo, correo de aviso y aceptación en bloque.
- Fusionar esto con `guest_data`/`event_data` del post-form. Se diseña **al lado**, no encima (§4.6).

## 3. Opciones consideradas

**A · Reutilizar `guest_data`.** DESCARTADA: ese JSON pertenece a una reserva, se vacía al anonimizar y
no puede colgar de él una firma que debe sobrevivir. `sistemas/POSTFORM-INVITADOS.md` declara además
cuál sería la condición para convertirlo en tabla, y no es ésta.

**B · Entidad propia bajo el titular (ELEGIDA).** Persistente, con su firma y su ciclo de vida propio.

**C · Registro global de menores compartido entre cuentas.** DESCARTADA: dos adultos con custodia
compartida **deben** poder declararse responsables por separado, y un registro global convertiría eso
en un conflicto de titularidad que no aporta nada.

## 4. Diseño elegido

### 4.1 El nombre de la entidad: `Dependent`, no `Minor`

⚠️ **Un menor añadido con 5 años tiene 18 dentro de trece.** Cuando eso pasa, el waiver del adulto
deja de cubrirlo, la pantalla de puerta diría «1 **menor** a cargo de 18 años» —una contradicción— y
**nadie lo va a notar**, porque ocurre en silencio y años después.

La fila **sobrevive a la minoría de edad**, así que la entidad se llama `Dependent` («persona a
cargo») con `isMinor()` **derivado**. En la interfaz se dice «menores a cargo», que es lo que entiende
el cliente. Es la misma doctrina que `ParkRule` → `VenueRule`: se generaliza el nombre que ata.

▶ **Y el día que cumple 18**: la lista lo marca («ya no está cubierto») y la pantalla de puerta
también. **No lo borra nadie automáticamente** — borrar datos por un cumpleaños es peor que enseñarlos
marcados.

### 4.2 El modelo

`user_id · name · born_on (date) · removed_at (nullable) · timestamps`

**Y nada más.** Cada columna que se añada aquí es dato personal de un menor.

- ⚠️ **La edad se DERIVA, nunca se persiste.** Cambia sola cada año; guardarla es una mentira con
  fecha de caducidad.
- ⚠️ **El nombre no es dato operativo, es una etiqueta del titular.** La puerta no lo enseña y el
  único que necesita distinguirlos es el propio adulto al asignar una entrada. La interfaz debe decir
  que puede poner **el nombre que use en casa**: menos PII sin perder nada.
  ▶ Contrapunto honesto: eso debilita algo el PDF del waiver. Aguanta —«Lucas, 12/03/2017» es
  identificable en la práctica— **siempre que el PDF diga que el dato lo declaró el titular y no está
  verificado** (`waiver-probatorio.md` §4.5).

### 4.3 El waiver de un menor

⚠️ **Lo firma el ADULTO, no el menor.** Lo que se registra es «X, como responsable de Y, aceptó la
versión V el día D» → la firma cuelga del **par (titular, dependiente)**, que es exactamente lo que
cubre el campo `sujeto` del registro de firma (`waiver-probatorio.md` §4.3). **Sin mecanismo nuevo.**

Consecuencia que hay que aceptar y está bien: **el mismo niño puede existir dos veces**, una por cada
progenitor con cuenta. Es correcto — cada uno se hace responsable por su cuenta. Por eso esto **no es
un registro de niños**, es «personas a cargo de esta cuenta», y el nombre importa para no confundirse.

### 4.4 Retirar: desvincular, no borrar

⚠️ **No se puede borrar la fila** si detrás hay un waiver firmado (que se conserva bajo régimen
restringido) o una reserva pasada que le asignó una entrada.

Es la misma tensión que el repo ya resolvió una vez: `DELETE /me` **no borra al usuario**, llama a
`anonymize()`, «porque la FK es `RESTRICT` y la factura tiene que seguir vinculada» (`RGPD-01`).

**Regla, derivada y no arbitraria:**
- Con waiver firmado **o** con reservas que lo referencian → `removed_at`. Desaparece de la lista del
  titular y de la pantalla de puerta; el waiver y el histórico siguen apuntando ahí.
- Sin waiver y sin ninguna referencia → **se borra de verdad**. No hay nada que conservar; se añadió
  por error y se quita.

▶ Si lo retira y lo vuelve a añadir son **dos filas**, y la segunda **necesita su propia firma**. Es lo
correcto: volver a hacerse responsable es un acto nuevo.

### 4.5 El tope: 20, y en el servidor

«Cuantos quiera» no puede ser literal: es una superficie de escritura barata que crea PII de terceros,
y una cuenta con miles de dependientes revienta la pantalla de puerta y el contexto de cuenta.

**Tope 20 por cuenta, configurable por instalación** (ajuste, no constante), **aplicado en servidor**.
Misma doctrina que `PAY-12`: el tope de líneas del carrito es invariante de servidor, no de interfaz.
Y **limitador de creación en el servicio de dominio**, no en la ruta — que es donde este proyecto los
pone siempre (`SelfSignup`, `PasswordLogin`, `AccountCredentials`).

### 4.6 Dónde vive: Identity, y la flecha que la arquitectura PROHÍBE

`Dependent` es de **Identity**. Eso es directo. Lo que no lo es:

⚠️ Si `order_items` guardara «esta entrada es para el dependiente X», eso es una flecha
**Booking → Identity**, y el grafo de `ModuleBoundariesTest` **no la permite**: Booking solo ve
`Platform` y `Payments\Contracts`, y únicamente `User` está exento por kernel compartido. Una FK a
dependientes en `order_items` **pone el arch-test en rojo, y con razón**.

**La salida ya la inventó este repo.** `specs/modulos-dominio.md` §4.bis, hallazgo 4: *«Los contratos
de Booking reciben `int $userId`, no `User`… así Booking no importa un modelo de Identity y el grafo
queda sin esa flecha.»*

Aplicado al revés: **la asignación la posee Identity y referencia el ítem por su id ENTERO**. Identity
sí puede mirar a `Booking\Contracts`. Cero flechas nuevas, cero excepciones en el arch-test.

### 4.7 Asignar una entrada a un menor

**Dónde cae, medido.** La cantidad no tiene paso propio: se elige dentro de `TimeStep.vue`, que su
propio docblock describe como *«el paso más denso del embudo y el que más dinero enseña»*. Y el mapa
real de `machine.js` es `CATALOG(1) → DATE(2) → TIME(3) → CART(4) → IDENTIFY(5)`.

⚠️ **Identificarse es el paso 5.** Cuando se elige la cantidad **no hay sesión garantizada** — el
embudo deja mirar, elegir y llenar la cesta como invitado. Sin sesión no hay menores que listar.

**Dos puertas, un solo módulo, CERO pasos nuevos:**
- **Con sesión** → el selector aparece en el paso 3, al elegir la cantidad.
- **Sin sesión** → aparece en el paso 5, justo después de identificarse, que es el primer instante en
  que el sistema sabe quién es. Esa pantalla ya existe.

▶ `FUNNEL_TRANSITIONS` **no se toca**. Es el grafo cerrado con guarda que dejó `#119`, y añadir un paso
sería el cambio caro.

**La forma del dato**: la línea tiene `quantity`, así que la asignación es **una lista de hasta
`quantity` huecos**, cada uno con un dependiente o vacío (= adulto). Mismo patrón que `guest_data`.
**Solo para entradas**: los packs ya piden el homenajeado y los invitados por `event_fields`/
`guest_fields`, y duplicarlo crearía las dos fuentes de verdad que este spec evita.

⚠️ **Y el valor real de esto NO es la etiqueta.** Es que **si asignas una entrada a un menor sin
waiver firmado, te enteras comprando y no en el mostrador con tres niños detrás**. Eso convierte una
conveniencia en una razón, y hay que dejarlo escrito: sin este párrafo, el siguiente lector lo lee como
un adorno y lo recorta.

### 4.8 Persistencia en el navegador: se puede, con dos condiciones

Lo que `#38(d)` prohíbe persistir son **las respuestas del evento** —nombre, edad y alergias de un
menor— y viven en `stores/selection.js`, que **no se persiste nunca**. La cesta sí se persiste, y
**ya guarda el `owner`** (el id del titular) en el almacén del navegador.

Un id de dependiente es la misma clase de cosa: **un puntero opaco, sin nombre ni fecha**, resoluble
solo por la sesión de su dueño. Y el caso del dispositivo compartido **ya está resuelto**: la cesta
purga cuando cambia el dueño.

**Se puede persistir, con dos condiciones:**
1. Viaja **el id y nunca el nombre**.
2. La reconciliación de la cesta sabe qué hacer si el dependiente se retiró entretanto: **la línea se
   queda sin asignar, no se rompe**.

### 4.9 El servidor re-valida: el riesgo de IDOR

⚠️ El id llega desde el navegador. Misma doctrina que `PAY-12`: **el servidor comprueba que cada
dependiente pertenece al titular autenticado**, y un id ajeno se rechaza.

Sin esa guarda, cualquiera enumera los dependientes de otras cuentas metiendo ids en el carrito. **Es
la guarda más importante de este spec** y va con su mutación.

### 4.10 Dónde se persiste la asignación, sin tocar el dinero

- La escribe **Identity**, después de que `OrderCreator` devuelva, y **fuera de la transacción que
  sostiene los locks de franja**: `AFORO-01` prohíbe meter nada antes del lock, y
  `specs/checkout-orquestado.md` prohíbe envolver la secuencia en una transacción. Un paso posterior e
  idempotente no toca ninguna de las dos.
- Si ese paso falla, **el pedido sigue en pie** y la asignación falta → recuperable desde «Mis
  reservas». Es una etiqueta, no dinero.

⚠️ **Pero toca el camino del checkout**, que es el código más protegido del producto. No es gratis y
este spec no finge que lo sea.

## 5. Impacto en invariantes

| ID | Impacto |
|---|---|
| **RGPD-01** | Se AMPLÍA: `anonymize()` tiene que saber qué hacer con los dependientes. **Regla derivada**: el dependiente sigue el régimen de su waiver — con firma, se conserva restringido con su plazo; sin firma ni reservas, se borra |
| **RGPD-04** | Se AMPLÍA: los dependientes entran en `GET /me/export` (art. 20), que ya sale con `no-store` |
| **AFORO-01** | Se CITA y no se toca: la asignación se escribe **después** del lock, nunca antes |
| **PAY-12** | Se APLICA dos veces: el tope es de servidor, y la pertenencia del dependiente se re-valida en servidor |
| **SEC-06** | Sin cambio |

## 6. Plan de verificación empírica

**Guardas ejecutables:**
1. **Un dependiente de otro titular se rechaza en servidor** — mutación obligatoria.
2. **El escaneo en puerta no devuelve nunca el nombre de un dependiente.**
3. **La edad no se persiste en ninguna columna** — solo se deriva.
4. **El tope se aplica en servidor**, no solo en la interfaz — mutación: quitarlo pone el test rojo.
5. **Un dependiente con waiver firmado no se borra**, ni por el titular ni por `anonymize()`.
6. **Un dependiente retirado deja la línea sin asignar, no la rompe.**
7. **Los packs no admiten asignación.**

**Comprobación empírica:**
- Recorrer el embudo en navegador **por las dos puertas** (con sesión desde el paso 3, y anónimo
  identificándose en el paso 5). ⚠️ El contrato de árbol **no ejerce el orquestador**
  (`VERIFICACION-E2E-CAJON.md` §5.bis): la red aquí es el navegador.
- Comprobar el caso del cumpleaños 18 **con el reloj congelado** (`SUITE-03`).
- Medir el chunk del cajón **antes y después**, y subir el techo con su párrafo.

## 7. Revisión y decisión

Diseñado en sesión de arquitectura con el owner el 2026-08-24 (`DECISIONES #142`).

✅ **REVISADA el 2026-08-25** por un segundo agente (`CONVENCIONES` §5) — **§8**. Veredicto: el diseño
es correcto y se implementa tal cual, aplicando §8.1 y §8.2. **Ningún bloqueante.**
⚠️ Su única dependencia externa es la decisión de `waiver-probatorio.md` §8.6 (tabla propia), sin la
cual §4.3 no encaja. Ver §8.3.
▶ **`[DECIDIDO owner, 2026-08-27]` (`#190`): se ejecuta en el carril A**, spec-first y por tandas, como
el waiver. **La tanda 1 está en el árbol** (`#191`, §9); las siguientes se recortan con el owner
delante sobre §9.5.

---

## 8. Revisión adversarial — 2026-08-25

> Segundo agente, `CONVENCIONES` §5. **Solo hallazgos**: lo que no aparece aquí se verificó y es
> cierto.

### 8.0 Lo que aguantó

Verificado ejecutando: el grafo de `ModuleBoundariesTest::ALLOWED` dice
`'Identity' => ['Platform', 'Booking\Contracts', 'Payments\Contracts']`, así que **§4.6 es correcta**
—Identity puede mirar a `Booking\Contracts` y la asignación por id entero no crea ninguna flecha— ·
el mapa de `machine.js` es exactamente `CATALOG(1) → DATE(2) → TIME(3) → CART(4) → IDENTIFY(5)`, así
que §4.7 acierta al decir que identificarse es el paso 5 · `FUNNEL_TRANSITIONS` está donde dice ·
`guest_data`/`event_data` se vacían en `anonymize()` como describe §1.

### 8.1 §4.8 apunta al fichero equivocado — y el mecanismo bueno está en otro sitio

§4.8 dice: *«las respuestas del evento … viven en `stores/selection.js`, que **no se persiste
nunca**»*. Medido: **viven en los dos sitios**.

- `stores/selection.js` las tiene mientras se construye la línea — y ahí es cierto, no se persiste.
- **En cuanto se añade a la cesta viajan a `stores/cart.js`**, dentro de `line.event_data`. Y
  `cart.js` **SÍ es el almacén persistido**.

▶ **Lo que las salva no es dónde viven, es cómo se guarda.** `cart.js::save()` (en
`resources/js/sidebar/cart.js`) **no filtra: reconstruye**. Cada línea se vuelve a componer con
exactamente cinco campos —`product_id`, `date`, `time`, `quantity`, `addons`—, así que todo lo demás
**no puede salir** aunque alguien lo meta en la línea. Es una **lista blanca**, que es más fuerte que
lo que la spec prometía. Y hay un canario en `cart.test.js` que busca centinelas en el volcado entero
del almacén, no en el campo esperado.

⚠️ **Por qué importa la corrección**: el implementador que siga §4.8 irá a `selection.js` a añadir el
id del dependiente y **no encontrará nada que tocar**. La lista blanca de `save()` es el sitio, y
tiene una consecuencia que la spec no anticipa pero que juega a favor: **si no se añade
explícitamente, el id no se persiste**. La condición 1 de §4.8 («viaja el id y nunca el nombre») queda
garantizada **por construcción**, no por disciplina.

### 8.2 La trampa que §4.8 no nombra: el sobre está VERSIONADO

`resources/js/sidebar/cart.js` guarda un sobre con `STORAGE_VERSION = 1`, y al leerlo:

    if (payload.v !== STORAGE_VERSION || ! Array.isArray(payload.lines)) return null;

⚠️ **Descarta el sobre ENTERO si la versión no coincide.** Añadir el hueco de asignación obliga a
elegir, y las dos opciones tienen precio:
- **Subir la versión** → todas las cestas vivas de todos los visitantes **se purgan en silencio** el
  día del despliegue. No falla, no avisa; el cliente se encuentra la cesta vacía.
- **Mantener `v: 1`** → conviven sobres con y sin el campo, y `reconcile` tiene que tratar el campo
  ausente como «sin asignar», que es lo mismo que ya exige la condición 2 de §4.8.

▶ **Recomendación del revisor: mantener `v: 1`.** La condición 2 («la línea se queda sin asignar, no
se rompe») ya obliga a tolerar el hueco vacío, así que tolerar su ausencia sale gratis — y purgar la
cesta de todo el mundo para añadir una etiqueta es un precio que no se paga.

### 8.3 Un apunte sobre §4.3 que hereda del waiver

§4.3 dice que la firma del menor «cuelga del par (titular, dependiente), que es exactamente lo que
cubre el campo `sujeto`… **sin mecanismo nuevo**». Es correcto, pero **depende de una decisión que
`waiver-probatorio.md` §4.3 dejó abierta** («amplía `consents` o nace al lado — lo decide la
revisión»). La revisión la resolvió en `waiver-probatorio.md` §8.6: **tabla propia con `RESTRICT`**.
Con esa respuesta, §4.3 se sostiene tal cual. Con la otra —ampliar `consents`— no: `consents.user_id`
es `cascadeOnDelete` y un `sujeto` que apunta a un dependiente no tendría dónde encajar.

### 8.4 Veredicto

**El diseño es correcto y se puede implementar tal cual**, con §8.1 y §8.2 aplicadas. Es la spec de
las cuatro con menos hallazgos, y la única cuyo hallazgo principal resultó ser una **buena noticia**:
el mecanismo que protege los datos del menor es más fuerte de lo que ella misma creía.

---

## 9. Ejecución — tanda 1 (2026-08-27, carril A, `DECISIONES #191`): el NÚCLEO en Identity + la API, sin firmas de menor todavía

> Lo que hay, en qué se apartó del cuerpo, lo medido, lo que NO hay por tanda y —lo que importa para
> seguir— **§9.5: las decisiones que la tanda 2 necesita del owner, con número y coste**. Ninguna
> firma de menor se produce todavía: `WaiverSignature::SUBJECT_DEPENDENT` sigue siendo código sin
> escritor HTTP, a propósito, porque NUC-3 (`DEUDA.md`) exige decidirse ANTES de la primera.

### 9.1 Qué existe (todo en Identity; la capa de entrega solo lo consume)

| Pieza | Dónde | Qué hace |
|---|---|---|
| `dependents` · `Dependent` | `database/migrations/2026_08_27_120000_create_dependents_table.php` · `app/Domain/Identity/Models/Dependent.php` | `user_id` (RESTRICT) · `name` (120) · `born_on` (date) · `removed_at` · timestamps. **Y nada más** (§4.2). La edad se DERIVA (`ageOn()`, `isMinor()`, `adultFrom()`) comparando FECHA con FECHA en el «hoy» del parque. `deleting` LANZA si hay una firma detrás (§4.4 hecho guarda). `Prunable`: las desvinculadas sin firma se podan |
| `DependentRegistry` | `app/Domain/Identity/Services/DependentRegistry.php` | El ÚNICO escritor: `add()` (solo menores; tope bajo el `lockForUpdate()` de la fila del titular, como `WaiverSigner`; audita `dependents.added` sin PII) · `remove()` (ajeno/inexistente/retirado → `DependentNotFoundException`; con firma → `unlink()`, sin ella → `delete()`; devuelve `DependentRemoval`) · `activeFor()` |
| `DependentSettings` | `app/Domain/Identity/Services/DependentSettings.php` | `dependents.max_per_account` (1–100; ausente o inválido → **20**). Se edita en Ajustes → «Puerta» |
| `DependentRemoval` + cuatro excepciones | `app/Domain/Identity/Contracts/DependentRemoval.php` · `app/Domain/Identity/Exceptions/` | `Deleted`/`Unlinked` · `DependentNotMinorException` · `DependentsLimitReachedException` (lleva `max`) · `DependentNotFoundException` · `DependentHasReferencesException` (la guarda de `deleting`) |
| La API | `app/Http/Controllers/Api/V1/MeDependentsController.php` · `app/Http/Resources/Api/V1/DependentResource.php` · `openapi/v1.yaml` | `GET /me/dependents` (activas, en orden de alta; `age`, `is_minor`, `adult_from` derivados) · `POST` (201; `422 validation_failed` sobre `born_on` si es futura o mal formada; `422 dependent_not_minor`; `422 dependents_limit_reached` con `params.max` y el mensaje ya interpolado) · `DELETE /{dependent}` (204; ajeno/inexistente/retirado → 404). Throttle CON PREFIJO `dependents-write` (30/min) en los dos de escritura |
| `anonymize()` | `app/Domain/Identity/Models/User.php` | `RGPD-01` ampliada (§5): con firma detrás → `unlink()` (se conserva vinculada, fuera de toda superficie); sin ella → `delete()` |
| El export | `app/Domain/Identity/Services/AccountPrivacy.php` | `RGPD-04` (§5): `dependents[]` con las ACTIVAS (`name`, `born_on`, `added_at`); esquema `ExportedDependent` en el contrato |
| Go-live | `app/Console/Commands/PurgeCustomerData.php` | Borra `dependents` por `DB::table` DESPUÉS de las firmas y ANTES de los usuarios (RESTRICT) |
| La poda | `routes/console.php` | `Dependent` en el mismo `model:prune` diario, **detrás** de `WaiverSignature`: la firma que vence deja huérfana a su fila en la misma pasada |
| Auditoría · morph | `app/Domain/Platform/Models/AuditLog.php` · `app/Providers/AppServiceProvider.php` | `dependents.added` · `dependents.removed` (payload: `dependent_id` + `mode`; **nunca** el nombre ni la fecha, `RGPD-02`) · alias `dependent` |

Recuentos del gate tras la tanda 1 (entonces): **33 modelos y 80 migraciones**; tras la 2, 81 migraciones.

### 9.2 Lo que se apartó del cuerpo (o lo precisa), y por qué

1. **Solo MENORES al declarar** (`422 dependent_not_minor`): el cuerpo no lo decía y §4.1 lo implica —a
   los 18 «el waiver del adulto deja de cubrirlo», así que declarar a un adulto produciría una firma
   que no cubre a nadie—. `[DECIDIDO agente]`, reversible en una línea (§9.5·5).
2. **Sin edición** (`PATCH`): una fecha de nacimiento corregida es OTRA persona a cargo, y con una firma
   detrás sería reescribir lo firmado. Es §4.4 al pie de la letra («dos filas»).
3. **Sin FK `waiver_signatures.subject_id → dependents.id` todavía.** Llega con la tanda 2, junto con
   NUC-3 y con `waiver:verify-chain`, que hoy firma con `subject_id` = i **sin fila en `dependents`**
   (la FK lo rompería, y también a `WaiverRetentionTest::test_dependent_signatures_are_not_pruned…`).
   ▶ Medido que cabe: la gramática SQLite de Laravel 13 recrea la tabla para `foreign` en un `ALTER`
   (`SQLiteGrammar::$alterCommands`), así que el `RESTRICT` real se puede añadir a una tabla existente
   sin la excepción de «FKs ausentes a propósito» de `DEUDA.md`.
4. **`removed_at` no se escribe si no hay referencias**: se borra de verdad (§4.4 literal). La fila
   desvinculada solo existe con una firma detrás — y **se poda sola** cuando esa firma vence
   (`Dependent::prunable()`): PII de un menor sin nada que la justifique no se queda por inercia.
5. **El export lleva solo las ACTIVAS**: la retirada con firma vive bajo el régimen restringido del
   waiver (waiver §4.6), fuera del art. 20 como la propia firma.
6. **El correo verificado NO se exige para declarar** (sí para firmar, `#179`): el alta pay-first crea
   cuentas sin verificar y en la tanda 4 asignar una entrada ocurre en el camino del dinero; exigirlo
   aquí pondría un 409 delante del pago. Lo que acota la superficie es el tope + el throttle con
   prefijo. `[DECIDIDO agente]`, reversible (§9.5·4).
7. **El tope se serializa con el lock de la fila del titular**, misma receta que `WaiverSigner`: sin
   él, dos altas simultáneas leen «19» las dos y entra la 21. ⚠️ SQLite no reproduce el lock, la
   propiedad **no tiene verificador de fork** y `DependentRegistry` **no entra en el `CRITICAL_RE`**
   (no es dinero ni aforo). Hueco con nombre, no un descuido: el precio de que falle es una fila de
   más, no una plaza vendida dos veces.
8. **La edad compara FECHA con FECHA y «hoy» es el del parque** (`DisplayTime::today()`, `AFORO-09`).
   Medido en su test: las 00:30 de Madrid del 18.º cumpleaños son las 22:30 UTC de la víspera; con la
   fecha UTC el menor seguiría siéndolo dos horas más, y con una resta de instantes también. Es lo que
   la mutación M4 (§9.3) caza.

### 9.3 Lo medido

- **Suite**: 34 casos nuevos en cuatro ficheros —`DependentRegistryTest` (15), `DependentPrivacyTest`
  (5), `MeDependentsTest` (11, contra el contrato) y `DependentsCapSettingTest` (3)— y la suite
  completa verde (contador en `ESTADO.md`). Los que ya vigilaban lo que se tocó, verdes sin cambios:
  `ApiContractTest` (rutas ↔ `paths`, enum de errores ↔ `ApiErrorCode`), `MePrivacyTest` (el export
  sigue validando contra su esquema estricto), `PrivacyTest`, `MorphMapTest`, `AuditActionCatalogTest`,
  `ModuleBoundariesTest`, `AnonymizeCoversEveryUserColumnTest` (ninguna columna nueva en `users`),
  `ApiBoundariesTest`, `PurgeCustomerDataTest`, `WaiverRetentionTest`, `SeededSettingsAreSaveableTest`.
- **Siete mutaciones, las siete muerden** (cada una contra su test; restauración comprobada byte a
  byte): sin el tope de servidor → `test_the_cap_is_enforced…` y `test_the_server_cap_answers_422…`
  caen (§6·4) · sin la pertenencia en `remove()` → los dos tests anti-IDOR caen (§6·1) · sin la guarda
  de `deleting` → `test_a_referenced_dependent_cannot_be_deleted…` cae (§6·5) · «hoy» en UTC →
  `test_the_registry_uses_the_parks_today…` cae · `anonymize()` sin dependientes → `test_anonymize_…`
  cae (§6·5) · `prunable()` sin excluir las referenciadas → `test_orphan_unlinked…` cae · la lista sin
  filtrar las retiradas → `test_it_lists_only…` cae.
- **HTTP contra MySQL real, con un Bearer de una cuenta sonda** (14 comprobaciones): `GET` vacía 200 ·
  `POST` menor 201 con `age: 9`, `is_minor: true`, `adult_from` · adulto `422 dependent_not_minor` ·
  futura `422 validation_failed` sobre `born_on` («fecha de nacimiento…», atributo traducido) · cuerpo
  vacío 422 con los dos campos · `GET` con una · `GET /me/export` con `dependents[]` · `DELETE` de un
  id inexistente 404 · propio 204 · repetido 404 · sin token 401 · `Cache-Control: no-store` · en
  inglés («A dependent must be under 18.») · con el tope a 1 desde `settings`: el segundo
  `422 dependents_limit_reached` con `params.max = 1` y el mensaje interpolado. Auditoría:
  `dependents.added {dependent_id}` · `dependents.removed {mode, dependent_id}`, sin nombre ni fecha.
  Sonda limpiada (0 dependientes, 0 tokens, ajuste retirado).
- **BD de desarrollo migrada** (`2026_08_27_120000`); `docs-check` entonces con 33 modelos y 80 migraciones.
- **El chunk del cajón no se ha tocado**: esta tanda no entra en `resources/js/sidebar/`.

### 9.4 Lo que NO hay todavía, por tanda

- **Tanda 2 — la firma del menor** (§4.3): `POST /me/dependents/{id}/waiver` (y su estado por
  dependiente en `GET /me/dependents`), la guarda de PERTENENCIA en `WaiverSigner` (el `subject_id`
  llega del cliente: §4.9 aplica también aquí), la FK `RESTRICT` (§9.2·3), `verify-chain` sembrando
  filas reales, el PDF diciendo **de quién** es la firma (hoy `subject_dependent` en el panel enseña
  «menor n.º :id»), y la retención de las firmas de menor (§9.5·3). ⚠️ `WaiverSigner` está en el
  `CRITICAL_RE`: `waiver:verify-chain` + `VERIFY_CONC=1`. **Bloqueada por §9.5·1.**
- **Tanda 3 — el cajón**: una zona «Menores a cargo» en la sección de cuenta (una línea en `ZONES` +
  su rótulo + el store), con el aviso «ya no está cubierto» del §4.1. **Bloqueada por §9.5·2.**
- **Tanda 4 — la asignación en el embudo** (§4.7–§4.10): la tabla que posee Identity con el
  `order_item_id` ENTERO (§4.6), las dos puertas sin paso nuevo (§4.7), el hueco en la lista blanca de
  `cart.js::save()` **sobre `v: 1`** (§8.1, §8.2), la re-validación en servidor (§4.9) y la escritura
  post-commit fuera del lock (§4.10). Es la tanda que toca el checkout.
- **La pantalla de puerta** (edad + estado del waiver, jamás el nombre): subsistema A.

### 9.5 ❗ Lo que la SIGUIENTE tanda necesita del owner — con número y coste

> ✅ **`[DECIDIDO owner, 2026-08-27]` — las cinco, respondidas** (`DECISIONES #197`): **(1) cadena por
> (titular, sujeto)** · **(2) construir la zona, MEDIR y subir el techo por FEATURE con su párrafo** ·
> **(3) la firma de un menor se conserva N meses DESPUÉS de su 18.º cumpleaños, con ajuste propio
> `waiver.dependent_retention_months`** (el valor de N sigue siendo criterio jurídico: sin valor, no se
> poda) · **(4) declarar NO exige el correo verificado** (firmar sí, `#179`) · **(5) declarar a un
> adulto se rechaza**. Lo que sigue son las opciones tal y como se plantearon, para la historia.

1. **NUC-3 — la cadena de hashes con firmas de menor** (`DEUDA.md` Alta; se decide ANTES de la primera
   firma de menor, y la tanda 2 ES esa primera firma). Hoy `prev_hash` enlaza con la última fila del
   titular sea cual sea el sujeto, y la poda por plazo no toca `dependent`: con un menor intercalado la
   poda deja **agujeros en medio** y `WaiverChain::verify()` declara ROTA una cadena legítima.
   - **(a) Cadena por (titular, sujeto)** — *recomendada*. `prev_hash` enlaza con la última firma del
     MISMO sujeto; la poda de un titular nunca deja agujeros en la cadena de un menor ni al revés, y
     `verify()` verifica por sujeto. Dentro de un sujeto, «más antiguo que el plazo» es siempre un
     PREFIJO (el lock serializa, `accepted_at` crece con `id`), así que «la poda solo mueve el
     inicio» vuelve a ser verdad para TODAS las cadenas. Compatible con lo ya firmado: sin firmas de
     menor, cadena por sujeto ≡ cadena por titular. **Coste**: `WaiverSigner` (`CRITICAL_RE`:
     `verify-chain` + `VERIFY_CONC=1`), `WaiverChain::verify()` agrupando por sujeto, la
     serialización literal de `WaiverSignatureChainTest`, y **rehacer el verificador**: hoy mide N
     menores en UNA cadena; con cadenas por sujeto mediría N cadenas de una fila y no cazaría nada
     → pasa a medir «N firmas simultáneas del MISMO sujeto = UNA fila» (la idempotencia bajo el
     lock, que es lo que el lock protege ahí) y la linealidad en serie. ~media sesión.
   - **(b) Poda solo por PREFIJO** (una cadena por titular, como hoy): `prunable()` borra filas del
     titular solo si ninguna fila NO podable (un menor) va detrás. **Coste**: `prunable()` + su test;
     `WaiverSigner` intacto. **Precio**: las firmas viejas del titular se conservan mientras un menor
     intercalado siga sin plazo — hasta 18 años más el suyo—, que es la acumulación que el waiver
     (§4.6) no quiere.
   - **(c) Nada** (asumir la cadena rota tras la poda): **descartada** — una cadena rota no
     distingue una poda legítima de una manipulación, que es lo único para lo que existe.
2. **El techo del chunk del cajón** (`SidebarBundleBudgetTest`; 0,16 KiB de holgura medidos al cerrar
   el waiver): la zona de menores se construye, **se mide** construyendo con y sin ella, y con la cifra
   delante el owner decide subir el techo con su párrafo (como `#175`) o podar. No se escribe el
   componente antes de tener esa decisión encuadrada.
3. **El plazo de retención de una firma de MENOR** (waiver §4.6: puede empezar a contar a los 18) —
   sigue `[PENDIENTE: owner]`. Hasta entonces `WaiverSignature::prunable()` no poda `dependent` y la
   fila desvinculada se queda (§9.2·4). Sin coste técnico: es un ajuste más, como
   `waiver.retention_months`.
4. **¿Correo verificado para declarar?** (§9.2·6) — hoy NO. Sí = un `409` más en el contrato y
   fricción en el embudo para el alta pay-first. Una línea y un test.
5. **La regla de los 18 al declarar** (§9.2·1) — hoy se rechaza. Admitir adultos «a cargo» obliga a
   decir quién firma su waiver. Una línea y un test.

### 9.6 Trampas para las tandas siguientes (lo que la ejecución enseñó)

- `WaiverRetentionTest::test_dependent_signatures_are_not_pruned…` y `waiver:verify-chain` firman con
  un `subject_id` que **no existe** en `dependents`. Con la FK de la tanda 2 los dos rompen: hay que
  sembrar filas reales (un `Dependent` por proceso en el verificador, y limpiarlas después de las
  firmas, no antes).
- `assertDatabaseHas` con una columna `date` en SQLite compara contra `Y-m-d 00:00:00` (el cast
  escribe con hora): léela por el modelo (`->born_on->toDateString()`).
- El `message` del sobre de error **no interpola `params` solo**: `ApiErrorCode::message()` traduce
  sin argumentos. Quien tenga los datos los pasa (`MeDependentsController` con `:max`).
- `ApiBoundariesTest` prohíbe `->delete()` en `app/Http/**`: el controlador llama a
  `DependentRegistry::remove()`, y el nombre del método importa (`#120(q)`).
- Un tope que el panel guarda y el dominio ignora es un ajuste que miente: `DependentsCapSettingTest`
  ata los dos extremos (rango del campo = rango de `DependentSettings`).

### 9.7 Ejecución — tanda 2 (2026-08-27 noche, carril A, `DECISIONES #198`): la FIRMA DEL MENOR

> Con las decisiones de §9.5 tomadas (`#197`). Lo que hay, en qué se apartó del cuerpo, lo medido y lo
> que queda. ⚠️ Tocó `WaiverSigner` (`CRITICAL_RE`): `waiver:verify-chain` corrió con 8 y 16 procesos y
> **se vio FALLAR** sin el lock antes de empujar con `VERIFY_CONC=1`.

#### 9.7.1 Qué existe

| Pieza | Dónde | Qué hace |
|---|---|---|
| La migración | `database/migrations/2026_08_27_200000_dependent_waiver_signatures.php` | **FK `waiver_signatures.subject_id → dependents.id` RESTRICT** (§4.4 deja de ser solo una guarda de modelo: medido en vivo, un `DELETE` crudo lo rechaza MySQL) + `subject_name` y `subject_born_on`. Aborta si hubiera firmas de menor previas sin fila |
| `WaiverSignature` v3 | `app/Domain/Identity/Models/WaiverSignature.php` | `CANONICAL_VERSION = 3`: la identidad del MENOR entra en el hash (`null` en las del titular); lo firmado con v1/v2 verifica con el suyo. `dependent()`, `subjectName()`. `prunable()` con DOS plazos por clase de sujeto |
| `WaiverSigner` | `app/Domain/Identity/Services/WaiverSigner.php` | **Cadena por (titular, sujeto)**: `prev_hash` es la última firma del MISMO sujeto (y esa misma fila decide la idempotencia por versión). Para un menor, bajo el lock: **suyo, activo y menor** (`DependentNotFoundException` / `DependentNotMinorException`) y su identidad copiada. El lock de la fila del titular sigue siendo el punto de serialización de todas sus cadenas |
| `WaiverChain` | `app/Domain/Identity/Services/WaiverChain.php` | Verifica UNA cadena por sujeto; devuelve `chains` además de `ok`/`count`/`problems` |
| `WaiverStatus::forDependent()` | `app/Domain/Identity/Services/WaiverStatus.php` | El estado del menor por su propia cadena (solo en interno); `signatureId` para el PDF |
| `WaiverSettings` + Ajustes | `app/Domain/Identity/Services/WaiverSettings.php` · `app/Filament/Pages/Settings.php` | `waiver.dependent_retention_months` (Puerta): N meses tras el 18.º cumpleaños; vacío = no se poda |
| `WaiverAcceptance::acceptForDependent()` · `WaiverSignatureRequest::forDependent()` | `app/Domain/Identity/Services/` | Las dos reglas del texto (interno, vigente) y el sujeto cambiado; quién es el menor lo decide el firmador |
| La API | `app/Http/Controllers/Api/V1/MeDependentsController.php` · `app/Http/Api/Concerns/BuildsWaiverSignatureRequest.php` · `openapi/v1.yaml` | `POST /me/dependents/{id}/waiver` (`document_id`; 201 con el `Dependent`; 404 ajeno/retirado; 409 ×3 como el titular; `422 dependent_not_minor`; throttle `waiver-sign`). `Dependent.waiver` (`DependentWaiverStatus`) y `dependent_id`/`dependent_name` en `WaiverSignatureSummary`. El canal (web/api) sale del trait compartido con `POST /me/waiver` |
| El PDF y el panel | `resources/views/pdf/waiver-proof.blade.php` · `resources/views/filament/users/partials/waiver-proof.blade.php` · `lang/*/waiver.php` | «En nombre de: Lucas (fecha de nacimiento 12/03/2017)» + la nota de que los datos los declaró el titular (§4.2); la conservación del menor desde los 18; «cadena por sujeto» en la nota de verificación. El registro del panel lista el nombre |
| `waiver:verify-chain` | `app/Console/Commands/VerifyWaiverChainConcurrency.php` | REHECHO (`#197`): N procesos firman como el MISMO sujeto desde cero → **UNA fila** (idempotencia bajo el lock) y dos cadenas verificadas (la sonda en serie firma en nombre de un menor real) |

#### 9.7.2 En qué se apartó del cuerpo (o lo precisa)

1. **La identidad del menor viaja EN la firma** (v3). §4.2 solo pedía que el PDF dijera que el dato lo
   declaró el titular; con la doctrina de `#161` (la identidad del firmante en la fila), una prueba que
   apuntara a una fila de `dependents` editable por debajo dejaría de decir de quién se aceptó. Ahora el
   hash la cubre. Más PII de un menor en una tabla restringida, a propósito y con su plazo.
2. **La FK Y la guarda de modelo, las dos**: la FK es la base de datos diciendo §4.4; `deleting` es lo
   que da una excepción con nombre en vez de un error de MySQL. Medidas las dos en vivo.
3. **La regla de los 18 también al FIRMAR**: un menor declarado a los 17 puede cumplir 18 antes de que
   el titular firme en su nombre; el firmador lo rechaza bajo el lock (`422 dependent_not_minor`).
4. **El plazo del menor se calcula sobre la fecha de nacimiento COPIADA** en la firma (`subject_born_on
   <= hoy − 18 años − N meses`), sin join: la fila se poda sola aunque `dependents` no exista ya.
5. **El PDF de la firma del menor se sirve por la ruta del titular** (`GET /me/waiver/{signature}/pdf`):
   el scoping por `user_id` ya cubre las dos clases de firma, y una ruta más habría sido una segunda
   puerta al mismo documento.
6. **`waiver:verify-chain` cambia lo que mide**: con cadenas por sujeto, «N menores en una cadena» sería
   N cadenas de una fila. Mide la idempotencia bajo el lock, que es lo que el lock protege ahora.

#### 9.7.3 Lo medido

- **Suite**: +15 casos —`MeDependentWaiverTest` (9), `WaiverSignatureChainTest` (+2: una cadena por
  sujeto; ajeno/retirado/adulto rechazados), `WaiverRetentionTest` (+2: la poda del menor desde los 18;
  **NUC-3 como guarda**: podar un sujeto nunca rompe la cadena del otro, en las dos direcciones),
  `WaiverProofPdfTest` (+1), `WaiverProofActionTest` (+1)— y la suite completa verde (contador en
  `ESTADO.md`). La serialización canónica v3 queda FIJADA como literal, con la fecha sin hora venga como
  venga (SQLite escribe «Y-m-d 00:00:00»).
- **`waiver:verify-chain` sobre MySQL real**: `--workers=8` y `--workers=16` → 1 fila del titular, 1 del
  menor, 2 cadenas, 0 repetidos, OK; restos 0. ❗ **Visto FALLAR sin el `lockForUpdate()`**: 16 procesos
  → 2 filas del titular, 1 `prev_hash` repetido, cadena ROTA. Fichero restaurado byte a byte.
- **Cinco mutaciones, las cinco muerden**: la cadena por titular otra vez → los dos tests de cadena por
  sujeto y NUC-3 caen · sin la pertenencia en el firmador → el test de ajeno/retirado cae · sin la guarda
  de menor → dos tests caen · sin copiar la identidad → el test de la firma y el del PDF caen · la poda
  ignorando el plazo del menor → su test cae.
- **HTTP sobre MySQL con Bearer**: firma del menor 201 con `waiver.signed`, `signature_id` y `pdf_url` ·
  repetida 201 (una fila) · `document_id` viejo `409 waiver_document_stale` · id inexistente 404 ·
  `GET /me/dependents` con el estado · `GET /me/waiver` con `dependent_id`/`dependent_name` y el titular
  sin firmar · el PDF (`application/pdf`, v3, cadena OK) nombra al menor, su fecha y la nota de «declarado
  por el titular» · `DELETE` del menor con firma → 204 y la fila queda desvinculada · **`delete()`
  lanza `DependentHasReferencesException` y `DB::table(...)->delete()` lo bloquea la FK**. Sonda limpiada.
- BD de desarrollo migrada; `docs-check` 33 modelos · 81 migraciones.

#### 9.7.4 Lo que queda

- **Tanda 3 — el cajón**: la zona «Menores a cargo» (declarar, quitar, firmar el waiver de cada uno, «ya
  no está cubierto»), **medida** con y sin ella y el techo del chunk subido por FEATURE con su párrafo
  (`#197`·2). Toda la API que necesita existe.
- **Tanda 4 — la asignación en el embudo** (§4.7–§4.10), sin cambios respecto a §9.4.
- **Del owner**: los DOS valores de retención en meses (`waiver.retention_months`,
  `waiver.dependent_retention_months`) — criterio jurídico; sin valor, nada se poda.

#### 9.7.5 Trampas (lo que la ejecución enseñó)

- Un esquema estricto del contrato exige TODOS los campos: añadir `dependent_id`/`dependent_name` al
  resumen y `waiver` al `Dependent` obliga a emitirlos siempre (también `null`), o `assertValidResponse`
  cae en tests que no tocan menores.
- Spectator no valida el PDF (`application/pdf`): el caso comprueba cabecera y `%PDF`, y el CONTENIDO se
  prueba sobre el HTML de la vista, como en `WaiverProofPdfTest`.
- El verificador limpia por `DB::table` en orden firmas → menor → titular: con la FK, al revés falla.

### 9.8 Ejecución — tanda 3 (2026-08-27 noche, carril A, `DECISIONES #199`): la ZONA DEL CAJÓN

> Con la decisión 2 de §9.5 en la mano (`#197`: construir, MEDIR y subir el techo por feature). Lo que
> hay, las reglas que se impuso, lo medido y lo que queda.

#### 9.8.1 Qué existe

| Pieza | Dónde | Qué hace |
|---|---|---|
| La zona | `resources/js/sidebar/account/zones/DependentsZone.vue` | «Menores a cargo» en la sección de cuenta: intro, aviso, lista de tarjetas y el formulario de alta (nombre + fecha de nacimiento, **nada más**). Pinta y recoge; el tope, la regla de los 18 y qué hace «quitar» los dice el servidor y se enseñan tal cual |
| La tarjeta | `resources/js/sidebar/account/zones/DependentCard.vue` | Un menor: nombre, «N años · dd/mm/aaaa», el aviso «ya tiene 18» (§4.1, se MARCA), la frase de SU exención, el formulario de firma en su nombre (texto plegado + casilla + botón; solo en interno, solo a un menor, solo sin firma o con una anterior), su PDF y «Quitar» |
| El módulo plano | `resources/js/sidebar/account/dependents.js` | Las frases y los booleanos (`dependentWaiverKey`, `dependentNeedsSignature`, `coverageKey`), la fecha sin `Date` (`bornOnLabel`) y `replaceDependent`. `node --test` |
| El store | `resources/js/sidebar/stores/dependents.js` | Las cuatro llamadas (`GET`/`POST`/`DELETE /me/dependents`, `POST /me/dependents/{id}/waiver`) por `runForm`; `signWaiver` devuelve `{ok, stale}` y el `document_id` es el del texto ENSEÑADO (`stores/waiver.js`, CAJ-1). La lista no se persiste. `node --test` |
| La navegación | `resources/js/sidebar/account/navigation.js` | `ZONES.DEPENDENTS`, su rótulo y su entrada en `HOME_ENTRIES` (tras «Tus datos»): una línea y su rótulo, como §4.2 de `area-cliente.md` prometía |
| El icono | `resources/views/components/icons/users.blade.php` · `account/ZoneIcon.vue` | `users` nace en el sistema de diseño y el cajón lo copia byte a byte (`SidebarIconParityTest`); reutilizar `user` habría dejado dos entradas del índice con el mismo dibujo |
| Los textos | `lang/{es,en,fr}/account.php` · `resources/views/components/layout.blade.php` | `account.account.dependents`, 17 rótulos, ENTEROS y solo con sesión. La casilla, «leer el texto», «Firmar», «Firmando…», «Firma registrada» y «PDF» se REUTILIZAN de `register.*` y `privacy.waiver.*` |

#### 9.8.2 Las reglas que la tanda se impuso (y por qué)

1. **Cero clases CSS nuevas**: `site.css` es hoy del carril C (el tema), y una zona más no puede
   abrir un frente ahí. La pantalla se compone con el vocabulario de las tarjetas de la cuenta
   (`account__card`, `form`, `btn`, `account__consents`), que `SidebarStyleWiringTest` ya vigila.
2. **Cero llamadas a la API desde componentes y ≤ 40 líneas de código por componente** (`CE-6`,
   `SidebarComponentBudgetTest`): por eso hay una zona Y una tarjeta, y el store tiene las cuatro
   llamadas.
3. **El cliente no decide NADA sobre un menor** (`CE-4`): edad, minoría y estado de la exención
   vienen derivados del servidor con el «hoy» del parque; el módulo plano solo los traduce a claves.
4. **Reutilizar antes de añadir rótulos**: seis textos ya viajaban y no se redactan por segunda vez.

#### 9.8.3 Lo medido

- **JS**: `npm run test:js` **702 → 724** (+22: el módulo plano 9, el store 13). Suite PHP verde
  (contador en `ESTADO.md`; +3 aserciones, las de la poda del subgrupo nuevo).
- **El chunk del cajón, construyendo con y sin la zona: 226,34 → 234,41 KiB, +8,07.** Techo 226,5 →
  **235** por FEATURE con su párrafo en `SidebarBundleBudgetTest` (`#197`·2). Es del tamaño del bloque
  de cuenta (+8,25): la tarjeta repite por cada menor el formulario de firma de Privacidad y el store
  tiene cuatro escrituras. Quedan 0,59 KiB.
- **El payload con sesión: 6.668 → 7.602 B, +934** (los 17 rótulos; el más largo, `intro`, se queda
  por ser la frase de §4.2). Techo 6.760 → **7.700** con su párrafo en `SidebarMountTest`; el anónimo
  no cambia (la zona viaja solo con sesión).
- **Las guardas del cajón, todas verdes sin excepción nueva**: iconos (la copia de `users` es byte a
  byte), ≤ 40 líneas por componente, clases con regla, DOM contract (SSR reconstruido), textos,
  cableado de imports/emits.
- **El guion en headless: 20/20 ✓** (`VERIFICACION-E2E-CAJON.md` §5.decies): entrada en el índice con
  icono · zona vacía · adulto rechazado con el texto del servidor · menor declarado con «9 años ·
  12/03/2017» y su exención sin firmar · formulario vaciado · botón bloqueado sin la casilla · firma
  201 → «Firma registrada ✓», v9, PDF (200 `application/pdf`), sin sello del titular, cadena OK · tras
  publicar v10, «versión anterior» y re-firma (2 firmas, 1 cadena, OK) · tope a 1 desde `settings` →
  «máximo (1)» · sin tope, entra · quitar con firma detrás → desvinculada y las firmas siguen · volver
  al índice. ⚠️ **Publica versiones de prueba** (`[E2E-DEP]`) en la BD local: v10 y v11 tras dos pasadas.

#### 9.8.4 Lo que queda

- **Tanda 4 — la asignación en el embudo** (§4.7–§4.10), sin cambios respecto a §9.4: la tabla de
  Identity con el ítem por id entero, las dos puertas, el hueco en la lista blanca de `cart.js::save()`
  sobre `v: 1`, la re-validación en servidor y la escritura post-commit. Toca el checkout.
- **Del owner**: su ✅ en navegador de la zona (guion §5.decies, en local, con `waiver.mode = interno`
  y una versión publicada) y los dos plazos de retención en meses.
- **Pantalla de puerta**: subsistema A (edad + estado de la exención, jamás el nombre).

#### 9.8.5 Trampas

- **`ctx.request.get(pdf)` en Playwright da 401**: sin `Referer`, Sanctum no trata la petición como
  *stateful* y no mira la cookie. Un clic real lo manda; el guion lo emula con `Referer` del mismo
  origen. No es un defecto del PDF.
- La entrada del índice se reconoce por su TEXTO (`hasText`): no hay `data-*` por zona a propósito
  (el contrato visual es el árbol, `sidebar-spa.md` §4.2).
- `<input type="date">` entrega `Y-m-d` tal cual: la fecha viaja sin tocar y el servidor la valida.

### 9.9 Ejecución — tanda 4 (2026-08-27 noche, carril A, `DECISIONES #202`): la ASIGNACIÓN EN EL EMBUDO — el diseño de ejecución, MEDIDO antes de escribir

> Spec-first, como las tres anteriores: antes de una línea de código se leyó el código contra el que
> se diseña (seis lectores por subsistema + un crítico de completitud: **296 hechos, 75 afirmaciones
> de la spec verificadas —14 FALSAS o IMPRECISAS—, 96 trampas, 15 huecos que el diseño tenía que
> decidir**) y se midió en headless lo único que nadie había medido (§9.9.6). ▶ **Lee §9.9.2 (lo que
> decidió el owner) y después §9.9.1 (lo que corrige al cuerpo), y solo entonces §9.9.3.** El cuerpo
> §4.7–§4.10 sigue siendo el QUÉ; esto es el CÓMO, y donde chocan manda esto.

#### 9.9.1 Lo que el código enseñó — y corrige al cuerpo

1. ❗ **No existe ninguna pantalla «después de identificarse» en el paso 5.** `PurchaseSection::enterWith()`
   encadena `sessionGained → applyIdentity → reset → continueAfterIdentification` y navega a PAY (o a
   CART con error). §4.7 decía «esa pantalla ya existe»: **era FALSA**. El paso 5 es el formulario
   entero, sin pie ni CTA (`buildFooter()` devuelve `null`). Decidido por el owner en §9.9.2·1.
2. ❗ **Un campo nuevo por línea hoy daría 201 «asignando nada».** `Validator::validated()` descarta
   las claves sin regla (`excludeUnvalidatedArrayKeys`), y después `CartPayload::toLine()` y
   `Cart::sanitize()` reconstruyen la línea con SEIS claves fijas. El CONTRATO sí es estricto
   (`CartLine` con `additionalProperties: false`), pero solo lo hace cumplir Spectator donde un test
   llama a `assertValidRequest()`: **uno** de los tests de `POST /orders`, con un fixture de cuatro
   campos. Un test que no mande el campo no lo comprueba.
3. **El orquestador NO puede llamar a Identity** (`ModuleBoundariesTest::ALLOWED['Booking']` = Platform +
   `Payments\Contracts`; solo `User` es kernel), y Booking no emite eventos de dominio (cero `event(`,
   cero listeners salvo `Verified`). El punto de composición es **`OrdersController::store()` tras
   `start()`**: la capa de entrega compone los dos módulos (precedente: `MeOrdersController` importa
   `Order` y `User`), y `ApiBoundariesTest` prohíbe las escrituras de Eloquent y `DB::` en el
   controlador, **no** llamar a un servicio de dominio con nombre propio (`AccountPrivacy::anonymize()`,
   `DependentRegistry::remove()` ya se llaman así).
4. **No hay índice de línea persistido.** `createPendingOrder()` devuelve `Order` sin `items` cargados;
   los principales nacen en el orden de `$cart` con ids crecientes y `Order::items()` no lleva
   `orderBy`; la 201 publica el `id` de cada línea pero no su `index`; el cliente descarta los items
   tras el 201. La correlación cesta ↔ ítem la tiene que PROMETER Booking (D2).
5. **`Dependent::hasReferences()` y `prunable()` son DOS copias** del predicado «qué cuenta como
   referencia»: si la asignación entra en una y no en la otra, o la poda diaria aborta a mitad
   (`delete()` lanza), o `remove()` borra un menor con entradas asignadas. Se unifican (D5).
6. **`GET /orders/{code}/event-data` es el precedente exacto** de «dato de un menor por reserva,
   servido APARTE, `no-store`, emparejado por `reservation_id`»: el paso 6 ya lo pide en paralelo con
   el pedido y la tarjeta de «Mis reservas» lo pide bajo demanda. Es por donde sale la asignación (D7).
7. **En «Mis reservas» no hay dónde poner «asignada a»**: `OrderItem` es cerrado (19 campos, en cinco
   respuestas) y `UpcomingReservation` no lleva `id`. §4.10 («recuperable desde Mis reservas») era
   diseño, no código — y con la decisión §9.9.2·4 la recuperación la hace el mostrador.
8. `purchase:verify-oversell` tiene **SEIS** escenarios (`panel-edit` entró con la 4b), no cinco como
   decía la fila A de `ESTADO.md`.
9. **La lista blanca del cliente son CUATRO sitios y dos guardas**, no uno: `toApiItems()` (lo que
   viaja a CUATRO endpoints), `SANITISED_FIELDS` (atada a `CartPayload::lineRules()` por
   `SidebarCartParityTest`), `sanitizeLine()` (lo que se restaura) y `save()` (lo que se persiste), más
   el `deepEqual` y el canario de `cart.test.js`. §8.1 nombraba solo `save()`: un campo que entre ahí y
   no en `sanitizeLine()` se pierde al recargar sin que nada falle.
10. **`reconcile()` solo reconcilia contra el PRESUPUESTO** (índices que no vuelven, precio nulo,
    complementos perdidos): «la línea se queda sin asignar si el menor se retiró» (§4.8·2) hay que
    construirlo, no extenderlo (D8).
11. ❗❗ **La cesta del PROPIO titular se PURGA cuando el cajón nace abierto** (`/entradas`,
    `/mi-cuenta`, `/login`, `/registro`, `/recuperar-contrasena`). Medido en headless (§9.9.6):
    `props.userId` llega del HTML, está declarada en `PurchaseSection.vue` y **no la lee nadie** (se
    perdió al mover la identidad al store); el dueño solo lo fija `GET /me`, que dispara `open()` — y el
    arranque «nace abierto» llama a `bootSpaEngine()` sin pasar por `open()`. Una cesta guardada con
    `owner = N` se restaura con `owner = null` y `decideOwnership(N, null)` = `purge`. §4.8 daba esa
    defensa «ya resuelta»: lo está en el camino normal (4/4 medidos) y rota en el que nace abierto
    (2/2). Es la **unidad 0** (§9.9.2·3).
12. **Quien se da de alta en el paso 5 tiene CERO menores** en ese instante y no puede firmar ninguna
    exención hasta verificar el correo (`#179`): la puerta 2 sirve a **cuentas que ya existen**. No es
    un defecto: es lo que la decisión §9.9.2·2 implica.
13. **La cantidad de una línea BAJA y SUBE desde el panel** (`OrderItemEditor::edit()`) sin borrar ni
    crear filas, y la fecha se mueve (`changeSlot()`); Booking no avisa a nadie. La coherencia de la
    asignación se DERIVA al leer, como `guest_data` contra `quantity` (D4).
14. `ModuleContractsTest` sustituye `ReservationCheckout` por un doble que DENIEGA y exige
    `Order::count() === 0` y `starts === 1`: la escritura solo puede colgar de un `start()` que
    permitió (D3).
15. Presupuestos, medidos hoy: `PurchaseSection.vue` clavado en **432 líneas / 2 llamadas** (exacto,
    «solo encoge»); chunk **234,41 KiB de 235** (607 B); payload con sesión **7.602 B de 7.700** (98 B);
    y el manifiesto congelado **no tiene ningún caso de ENTRADA en la `qtybox` del paso 3** (los dos
    casos anclados ahí usan un PACK).

#### 9.9.2 Las cuatro decisiones del owner (2026-08-27 noche, `#202`), a pregunta simple con la medida delante

1. **Puerta 2 → el cajón VUELVE AL CARRITO con aviso**, solo si la cuenta tiene menores asignables y la
   cesta tiene entradas sin asignar; el resto sigue a pagar como hoy. Cero pasos nuevos en `machine.js`
   (la transición `IDENTIFY → CART` ya existe).
2. ❗❗ **La exención firmada es CONDICIÓN para asignar**: «un menor no se puede asignar si no firma la
   exención; sin eso, asignar menores no sirve de nada». **Cambia el alcance de §4.7**: la asignación
   no es una etiqueta con aviso, es la lista de los menores que YA pueden entrar. El selector solo
   ofrece menores con firma VIGENTE, y el servidor lo exige (422) aunque el cliente no lo haga.
   ▶ `[DECIDIDO agente, reversible en una línea]`: en modo `externo` o `desactivado` no existe firma
   que comprobar (`WaiverStatus::forDependent()` devuelve `signed: false` sin pregunta), así que ahí
   la regla no aplica y se puede asignar — la exención vive fuera o no hay. Si el owner prefiere que
   sin modo interno no se asigne, es un `if` menos.
3. **La purga de la cesta (§9.9.1·11) se arregla AHORA, como unidad 0** de esta tanda.
4. ~~**El PANEL entra**: ver la asignación en el pedido **y asignar en mostrador**.~~ ▶ ❗ **RECTIFICADO
   por el owner la misma noche**: «el panel no entra en alcance; el panel tendrá su propia sesión.
   Ahora solamente la gestión de menores». **La tanda 4 es SOLO el embudo** (U0 → U1 → U2 → el ojo del
   owner); la ficha del pedido, «Asignar menores» y el alta manual (D14) quedan como diseño para esa
   sesión y **no se toca ningún fichero del carril B**.

#### 9.9.3 Decisiones de diseño `[DECIDIDO agente]` — todas reversibles, cada una con su porqué

- **D1 · Transporte: `dependent_ids` DENTRO de `CartLine`**, opcional (`OPTIONAL_BY_DESIGN`), lista de
  enteros distintos, ≤ `quantity`. Es el esquema compartido por cuatro endpoints, y se decide que los
  tres públicos lo **validan e ignoran** (descripción en el contrato); solo `POST /orders` lo lee.
  `CartPayload::lineRules()` lo valida (`sometimes|array`, `*.integer|min:1|distinct`) y
  **`CartPayload::toCart()` NO lo pasa a Booking**: una función hermana, `CartPayload::assignments()`,
  extrae `[índice => ids]` para Identity. `Cart::sanitize()` no se toca — Booking sigue sin ver un id
  de menor (§4.6). Descartadas: (a) una petición aparte tras el 201 —añade una llamada en el momento
  más delicado (entre el 201 y el envío a la pasarela) y convierte el fallo en un reintento del
  cliente—; (b) un esquema `CheckoutLine` aparte —OpenAPI 3.0 + Spectator no soportan `allOf` con
  `additionalProperties: false`, y duplicar seis propiedades es la deuda que `ApiContractTest` vigila—.
- **D2 · Correlación cesta ↔ ítem: la promete Booking por CONTRATO.** `Booking\Contracts\CheckoutLines`
  con `forOrder(int $orderId, int $userId): list<CheckoutLine>` — los ítems PRINCIPALES del pedido en
  orden de creación (`orderBy('id')`, que es el orden de la cesta porque `OrderCreator` los crea así),
  cada uno con `index`, `orderItemId`, `quantity`, `isEntry` y `date`. Implementación
  `Booking\Services\CheckoutLinesReader`, bind en `BookingServiceProvider`, doble en
  `ModuleContractsTest`. Identity → `Booking\Contracts` está en `ALLOWED`: **cero flechas**. Guarda de
  método: si `count(líneas) !== count(peticiones)` no se escribe NADA y se registra (fail-safe: mejor
  sin asignar que asignado a otra línea). Descartado devolver el mapa desde `OrderCreator`: toca el
  `CRITICAL_RE` y la firma que consumen el verificador y el fulfiller sin ganar nada.
- **D3 · DOS FASES, y la primera va ANTES del dinero.** `Identity\Services\DependentAssigner`:
  · `check(User $holder, array $lines): AssignmentRejections` — sin lock, ANTES de `start()`: para cada
    id, suyo + activo (ajeno = inexistente = retirado, §4.9), **menor en la FECHA DE LA VISITA**
    (`isMinorOn(line.date)`, no hoy: un menor de 17 años y 364 días visita como adulto), **firma
    VIGENTE** en modo interno (§9.9.2·2), sin repetidos y ≤ `quantity`. Cualquier rechazo → **`422
    validation_failed`** con `fields['items.{i}.dependent_ids.{j}']` y el mensaje de su motivo (tres
    claves `api.*`, tres idiomas). Es fail-closed **antes** de crear el pedido y de consumir la ficha
    de admisión: el cliente se entera y nada queda a medias.
  · `assign(User $holder, int $orderId, array $lines): AssignmentOutcome` — DESPUÉS de que `start()`
    devuelva `allow` (o sea, tras `open()`: si la pasarela no abre no hay nada que limpiar), bajo el
    `lockForUpdate()` de la fila del titular —**el mismo lock que `DependentRegistry::remove()` y
    `WaiverSigner`**, así una retirada concurrente no se cruza con una asignación—, RE-valida las mismas
    reglas (el estado pudo cambiar entre las dos fases), lee las líneas por D2, escribe idempotente
    (`insertOrIgnore` sobre el único `(order_item_id, dependent_id)`; solo entradas; ≤ `quantity`), y
    audita. Si falla, `Log::warning('dependents.assign_failed', …)` y **el pedido sigue en pie** (§4.10);
    el 201 se compone DESPUÉS, así que ya la refleja. Descartado `DB::afterCommit`: fuera de una
    transacción se ejecuta en el acto (el orquestador no abre ninguna, por regla).
  · Con el doble que deniega (§9.9.1·14) no se invoca: cuelga de `$outcome->denied() === false`.
- **D4 · La tabla, en Identity: `dependent_assignments`** — `id · dependent_id (FK `dependents`
  RESTRICT) · order_item_id (`unsignedBigInteger`, FK `order_items` CASCADE) · created_at`, único
  `(order_item_id, dependent_id)`, índice `dependent_id`. **Sin columna de posición**: la asignación es
  el CONJUNTO de menores de una línea (un menor no usa dos entradas a la vez), con `count ≤ quantity`
  exigido al escribir y **derivado al leer** (si la cantidad bajó desde el panel se enseñan las
  primeras `quantity` por `id`; si subió, hay huecos de adulto). Modelo `Identity\Models\DependentAssignment`
  con alias `dependent_assignment` en el morphMap. CASCADE desde `order_items` porque una asignación
  sin su línea no significa nada y así `PurgeCustomerData` (que borra pedidos ANTES que menores) y los
  `Verify*` (que borran `OrderItem` a mano) siguen funcionando sin tocarlos; RESTRICT hacia
  `dependents` porque es la BD diciendo §4.4, como la FK de las firmas (`#198`).
- **D5 · La asignación es REFERENCIA (§4.4), y el predicado se escribe UNA vez.** `Dependent` gana
  `assignments()` y un scope `referenced()` —firma O asignación— del que salen `hasReferences()`
  (`whereKey + referenced()->exists()`) y `prunable()` (`whereNotNull(removed_at) + unreferenced()`):
  las dos copias de §9.9.1·5 se funden, y `remove()`, `anonymize()` y `model:prune` coinciden por
  construcción. Consecuencia: un menor con entradas asignadas se DESVINCULA al quitarlo, y la fila se
  poda sola cuando el pedido se purga (la cascada borra la asignación).
- **D6 · RGPD.** `RGPD-01`: `User::anonymize()` **borra las asignaciones** del titular antes de tratar a
  sus menores — es la misma clase de dato que `guest_data`/`event_data`, que ya se vacían: PII de un
  menor atada a una visita; después cada menor sigue la regla vigente (con firma → desvinculado, sin
  nada → borrado). `RGPD-04`: el export lleva `dependents: [nombres]` en cada `ExportedOrderItem`
  (art. 20); `CustomerOrderHistory::exportFor()` añade un `id` interno por línea para que Identity
  cruce, y `AccountPrivacy` lo retira antes de emitir. La purga de go-live no cambia (cascada).
- **D7 · Lectura: `dependents[]` en `OrderEventDataReservation`** (`{id, name}`, obligatorio, vacío si
  no hay), compuesto por `OrderEventDataResource` desde Identity (`DependentAssigner::forOrder()`,
  una consulta por pedido, sin N+1). El paso 6 lo enseña sin una petición más («Para: Lucas, Vera»
  bajo la línea, en `SummaryLine` — que también pinta el paso 8, donde la propiedad va vacía). En «Mis
  reservas», la tarjeta de una ENTRADA ofrece «Ver para quién» solo si el titular tiene menores (el
  store ya lo sabe) y lo pide bajo demanda, como las respuestas del pack. Descartado un campo en
  `OrderItem`: cinco respuestas, listas paginadas con nombres de menores y un N+1 que
  `ApiOverheadTest` cazaría.
- **D8 · Cliente: `dependent_ids` en las CUATRO listas** (`SANITISED_FIELDS` —la paridad con
  `lineRules` se mantiene sola—, `sanitizeLine()` que restaura solo enteros ≥ 1, distintos y ≤
  `quantity`, `save()` y una `toCheckoutItems()` que usa SOLO `pay.js`; `toApiItems()` no cambia para
  los tres endpoints públicos). Política de fusión en `addLine()`: unión de ids (los existentes
  primero) recortada a la cantidad efectiva; `quantity_capped` recorta. Reconciliación (§4.8·2): al
  restaurar con sesión y en `sessionGained`, los ids que no estén en la lista viva de menores
  ASIGNABLES (`GET /me/dependents`: activo, `is_minor`, `waiver.signed && !outdated`) se quitan de la
  línea en silencio; y si el servidor rechaza (D3) el 422 llega por línea y la línea se desasigna con
  su aviso. `useDependentsStore().invalidate()` gana los llamadores que hoy no tiene (`applyIdentity`
  y `sessionGained`). `STORAGE_VERSION` sigue en **1** (§8.2). El canario de `cart.test.js` se siembra
  también con un nombre en la línea: el almacén solo puede llevar ids.
- **D9 · La interfaz, en dos sitios y con la misma pieza.** Un bloque «¿Para quién son estas
  entradas?» con una CASILLA por menor asignable («Lucas · 9 años»; los no asignables aparecen
  deshabilitados con su motivo —«exención sin firmar: fírmala en Menores a cargo»— y el enlace abre
  esa zona del mismo cajón, sin perder la cesta), tope = `quantity`, «el resto son adultos». Vive en
  **`TimeStep`** (paso 3, con sesión, solo entradas) y en **`CartStep`** (paso 4, con sesión, por
  línea — el único paso que ya edita líneas persistidas). La lógica —opciones, tope, motivos,
  recorte, «¿hace falta volver al carrito?»— en un módulo plano `assignment.js` con `node --test`;
  los `.vue` pintan (≤ 40 líneas). La puerta 2 (§9.9.2·1) es un input más de
  `continueAfterIdentification()`: si `needsAssignment` el veredicto es CART con aviso en vez de PAY.
- **D10 · Rótulos en `account.dependents.*`**, no en `tickets.*`: solo se pintan con sesión, y
  `tickets` viaja entero en toda página pública. El techo del payload con sesión (7.700, 98 B de
  holgura) sube por FEATURE con su párrafo, misma regla que `#197`·2.
- **D11 · Gates.** `OrdersController` ya está en el `CRITICAL_RE` (prefijo `Order`): la tanda se empuja
  con `VERIFY_CONC=1` tras correr los SEIS escenarios de `purchase:verify-oversell` y
  `redsys:verify-concurrency`. `DependentAssigner` **no** es dinero ni aforo: se declara en
  `CriticalPathGateTest::NON_CRITICAL_FILES` como control negativo, con su porqué, para que la decisión
  quede fijada y no por inercia. `CheckoutOrchestrator` y `OrderCreator` **no se tocan**. El modelo
  nuevo sube «33 modelos» a 34 en las dos docs que `docs-check` cuenta.
- **D12 · Auditoría**: `dependents.assigned` (target el titular; payload `dependent_id`,
  `order_item_id`, `order_id`, nunca el nombre, `RGPD-02`) y en el mostrador la misma acción con
  `by` del operador. Un fallo de escritura va a log, no a auditoría (no hay acción que auditar).
- **D13 · Minoría en la FECHA DE LA VISITA** (D3), no hoy: es la fecha en la que la cobertura del
  adulto importa (§4.1). Reversible.
- **D14 · El panel** — ⏸️ **FUERA de esta tanda por la rectificación de §9.9.2·4 (sesión propia); se
  conserva como diseño**: la ficha del pedido enseña, por línea de entrada, «Para: Lucas
  (9 años · exención ✓)» leyendo por `DependentAssigner::forOrder()`; la acción «Asignar menores» de
  la línea (misma familia que «Gestionar») fija el CONJUNTO (`sync`: quitar y poner) con las mismas
  reglas de D3 y el mismo lock; y el alta manual, tras elegir el cliente, ofrece por línea de entrada
  sus menores asignables y escribe **después** de que `ManualOrderFulfiller::fulfill()` devuelva (fuera
  de SU transacción, §9.9.1). El operador ve el nombre porque ya lo ve en el registro del waiver
  (`#198`); la puerta seguirá sin verlo (subsistema A).

#### 9.9.4 Las unidades, en orden — cada una verde y EMPUJADA antes de la siguiente (`CONVENCIONES §10`·5)

| U | Qué | Ficheros | Gate |
|---|---|---|---|
| **U0** | La purga de la cesta (§9.9.1·11): sembrar el dueño desde el HTML antes de restaurar, caso JS, re-correr la sonda §9.9.6 (10 medidas) | `resources/js/sidebar/sections/PurchaseSection.vue` (presupuesto 432 exacto: se compensa o sube con párrafo) · `stores/cart.js` · sus tests | `test:js`, suite, sonda headless |
| **U1** | El SERVIDOR: migración + `DependentAssignment` + `Dependent` (D5) + `DependentAssigner` (check/assign/forOrder) + `Booking\Contracts\CheckoutLines` y su reader + `CartPayload` (D1) + `OrdersController::store()` (D3) + contrato (`CartLine.dependent_ids`, `OrderEventDataReservation.dependents`, `ExportedOrderItem.dependents`) + `OrderEventDataResource` + `anonymize()`/export (D6) + auditoría + morph + `NON_CRITICAL_FILES` + `lang/*/api.php` | `database/migrations/*dependent_assignments*` · `app/Domain/Identity/{Models/DependentAssignment,Services/DependentAssigner,Contracts/*}` · `app/Domain/Booking/{Contracts/CheckoutLines,Services/CheckoutLinesReader,BookingServiceProvider}` · `app/Http/Api/CartPayload.php` · `app/Http/Controllers/Api/V1/OrdersController.php` · `app/Http/Resources/Api/V1/OrderEventDataResource.php` · `openapi/v1.yaml` · `tests/Feature/{Dependents,Api/V1,Architecture}/**` | `VERIFY_CONC=1` (6 escenarios + Redsys), `ApiContractTest`, `ModuleBoundariesTest` con diff de baselines VACÍO, `ModuleContractsTest` con el doble nuevo, sonda HTTP sobre MySQL |
| **U2** | El CAJÓN: `cart.js` (D8) + `assignment.js` + `stores/dependents.js` (ensure/invalidate) + `TimeStep`/`CartStep` (D9) + `admission.js` (puerta 2) + `PurchaseSection` (cableado, presupuesto) + `SummaryLine`/paso 6 + tarjeta de «Mis reservas» (D7) + rótulos ×3 (D10) + manifiesto regenerado **con un caso nuevo de ENTRADA en la `qtybox`** + techos del chunk y del payload medidos con y sin | `resources/js/sidebar/**` · `lang/{es,en,fr}/account.php` · `resources/views/components/layout.blade.php` (la lista de claves con sesión) · `tests/Fixtures/sidebar-dom-manifest.json` · `tests/Feature/Sidebar/**` | `test:js`, todas las guardas del cajón, `SidebarBundleBudgetTest`/`SidebarMountTest` con su párrafo, guion headless §5.undecies por las DOS puertas |
| ~~**U3**~~ | ~~El PANEL (D14)~~ — **FUERA por la rectificación del owner (§9.9.2·4): el panel tendrá su propia sesión.** No se toca `app/Filament/**` | — | — |
| **U4** | El OJO del owner: guion §5.undecies en navegador, con `waiver.mode = interno` y un menor firmado y otro sin firmar | `docs/VERIFICACION-E2E-CAJON.md` | — |

Lo que NO entra: **el panel** (ver y asignar en mostrador: sesión propia, D14 como diseño) · la
pantalla de puerta (subsistema A) · asignar menores a PACKS (§4.7, dos fuentes de verdad) · editar una
asignación desde «Mis reservas» (el cliente quita/pone en el embudo; después, el mostrador en su
sesión) · el modo `externo`/`desactivado` como bloqueo (§9.9.2·2, reversible).

#### 9.9.5 Verificación empírica que se exige a cada unidad

- **Guardas nuevas con mutación vista morder**: (1) un id ajeno/retirado → 422 y el pedido NO se crea
  (§6·1, la más importante: mutación = quitar `where('user_id')` del check); (2) un menor sin firma
  vigente → 422 en interno, entra en externo; (3) adulto en la fecha de la visita → 422, aunque sea
  menor hoy; (4) `count > quantity` → 422; (5) packs → 422 (§6·7); (6) idempotencia: dos `assign()`
  iguales → una fila; (7) el pedido sigue en pie si `assign()` lanza (§4.10) y el 201 sale; (8) el
  doble que deniega no escribe nada; (9) `remove()` DESVINCULA con asignación detrás y `anonymize()`
  borra la asignación antes (§6·5/§6·6); (10) la poda no toca un desvinculado con asignación y sí
  cuando la cascada se la lleva; (11) `event-data` lleva `dependents` y `/me/orders` NO lleva ningún
  nombre; (12) el export lleva los nombres; (13) la línea de cesta restaurada pierde el id de un menor
  que ya no es asignable (`cart.test.js`); (14) el canario: un nombre sembrado en la línea no llega al
  almacén; (15) `continueAfterIdentification` devuelve CART con aviso cuando hay menores y entradas
  sin asignar, y PAY si no (`admission.test.js`); (16) la correlación: con dos líneas del mismo producto
  y distinta franja, cada asignación cae en SU ítem.
- **Concurrencia real sobre MySQL**: los seis escenarios de `purchase:verify-oversell` y
  `redsys:verify-concurrency` (por el `CRITICAL_RE`, aunque no ejercitan la asignación — se dice); y una
  sonda propia de N `assign()` simultáneos del mismo titular → una fila por par (el lock).
- **HTTP sobre MySQL con Bearer**: el ciclo entero (`POST /orders` con `dependent_ids` → 201 →
  `GET /orders/{code}/event-data` con `dependents`), los cuatro 422 y el export.
- **Navegador**: el guion §5.undecies por las DOS puertas (con sesión desde el paso 3; sin sesión
  identificándose en el 5 y volviendo al carrito con el aviso), con un menor firmado y otro sin firmar,
  primero en headless (`feedback` del owner: él después).

#### 9.9.6 La sonda del dueño de la cesta (2026-08-27 noche, headless, 10 medidas) — la evidencia de §9.9.1·11

`/root/e2e/cart-owner-probe.js` (fuera del repo, receta §5.bis). Cuenta `e2e-dependents@jumpweb.test`
(id 457), una línea real («Jump · 1 hora», 27/08 20:00; el horizonte de franjas hubo que regenerarlo
con `slots:generate-rolling`, `AFORO-03`), sembrada en `localStorage` tal como la escribe `save()`.

| Medida | Estado guardado | Camino | Resultado |
|---|---|---|---|
| M1, M1bis | `owner = 457`, sesión | `/entradas` (nace abierto) | ❌ **PURGADA** ×2: almacén borrado, 0 líneas, el cajón abre en el catálogo |
| M2 | `owner = null`, sesión | `/entradas` | ✅ conservada: 1 línea, abre en «Tu carrito» |
| M3, M4.0–2 | `owner = 457`, sesión | home + CTA (`open()`) | ✅ conservada ×4, dueño 457 intacto |
| M5 | `owner = 457`, **sin** sesión (logout) | `/entradas` | ✅ purgada — la casilla del logout, correcta |

▶ La tabla de `decideOwnership()` es correcta; lo roto es que el arranque «nace abierto» restaura con
el dueño a `null`. El arreglo es la línea que el docblock de `PurchaseSection` promete y nunca existió:
sembrar el dueño desde el HTML antes de `restoreCart()`. ⚠️ El guion A5·2 del E2E espera una purga que
la tabla no produce (cesta SIN dueño + otra cuenta → conservar): es doc caducada, se corrige en U0.

✅ **U0 EJECUTADA (misma noche).** La siembra vive en `index.js`, junto a la del contexto de cuenta y
ANTES de `app.mount(el)` —no en `PurchaseSection.vue`, que sigue en 432/2—: `useCartStore(pinia)
.setOwner(boot.userId ?? null)`. El docblock de la prop `userId` dice ahora la verdad (se conserva
declarada porque la raíz hace `v-bind="props"`). Red: un caso en `stores/cart.test.js` (sembrar antes
de restaurar conserva la propia, purga la ajena y la anónima; JS 724 → 725) y una guarda ESTRUCTURAL en
`SidebarMountTest` —la suite no arranca el motor, `TESTING.md` §2.sexies— que exige la siembra y que
vaya antes de montar; **dos mutaciones, las dos muerden** (sin siembra · siembra después de montar).
⚠️ Y la primera versión de esa guarda salió ROJA con el fuente correcto: `strpos` casó `app.mount(el)`
con la MENCIÓN en un comentario que va antes que la llamada — limpia comentarios antes de buscar.
**La sonda re-corrida sobre el build: 10/10, M1/M1bis CONSERVADAS** (dueño 457, «Tu carrito»), M5
sigue purgando en el logout. Chunk 234,41 → 234,43 KiB (+20 B). A5 del guion corregido (§5, A5·1
con sesión, A5·4 nuevo).

