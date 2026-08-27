# [SPEC] Menores a cargo

> Estado: 🟦 **REVISADO (§8) y EN EJECUCIÓN — TANDAS 1 y 2 EN EL ÁRBOL (2026-08-27, carril A):
> la tanda 1 es el núcleo en Identity + la API (`#191`); la 2, la FIRMA DEL MENOR (`#198`), con la cadena
> de hashes por (titular, sujeto) que decidió el owner (`#197`).** ▶ **EMPIEZA POR §9** —§9.1/§9.7 qué
> existe, §9.2/§9.8 en qué se apartó del cuerpo, §9.5 las cinco decisiones (TOMADAS) y §9.4 lo que
> queda: la tanda 3 (el cajón) y la 4 (el embudo)— · pendiente del ✅ del owner ·
> Última actualización: 2026-08-27 noche (§9.7) · anterior: 2026-08-25 ·
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

