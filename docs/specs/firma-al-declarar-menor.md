# [SPEC] La EXENCIÓN se acepta AL DECLARAR al menor — un solo gesto, y la firma nace con el buzón probado

> Estado: 🟦 revisada (adversarial de 7 lentes, 2026-09-06) · Última actualización: 2026-09-06 ·
> Decisión asociada: `DECISIONES #441` 
> Banda de numeración reservada para este carril: 440–449.
>
> Encargo del owner (2026-09-06), literal: *«Al añadir un menor a cargo, es obligatorio firmar el
> waiver, actualmente muchos clientes añaden un menor primero y despues se les pide firmar waiver,
> pero no lo firman y lo dejan asi.»*
>
> Complemento de `docs/specs/menores-a-cargo.md` (§4.1–§4.5) y `docs/specs/waiver-probatorio.md`
> (§7·5, §4.4). **Léelas antes que ésta.**
>
> ❗❗❗ **CORRECCIÓN QUE VA ANTES DEL TEXTO: la primera versión de esta spec proponía RELAJAR la
> regla del correo verificado (`#179`) y la revisión adversarial reprodujo el daño (§1.3).
> `[DECIDIDO owner, 2026-09-06]` se hace con la ACEPTACIÓN RETENIDA, que es el mecanismo que `#179`
> construyó exactamente para esto. **`#179` NO se modifica: se REUTILIZA.**

---

## 1. Contexto y problema

### 1.1 · El diagnóstico del owner es correcto — cinco puertas y ninguna obliga

1. **El alta no exige nada**: `DependentRegistry::add()` valida nombre, apellidos, relación, fecha y
   el tope. **No menciona el waiver.**
2. La firma se ofrece **después**, dentro de la tarjeta (`DependentCard.vue:54-70`).
3. El texto es informativo y **sin consecuencia**: *«Descargo sin firmar en su nombre.»*
4. **Nada se lo recuerda**: `accountNoticeFrom()` (`account/waiver.js:114-128`) mira **solo el
   waiver del titular**.
5. **Asignar es opcional**: `DependentAssigner` exige la firma solo si el cliente intenta asignarle
   una entrada. Si no lo intenta, compra igual y el menor **llega a la puerta sin exención**.

⚠️ **Y exige firma VIGENTE, no solo firmada** (`DependentAssigner:361`): publicar una versión nueva
deja a todos los menores sin poder asignarse hasta que se re-firmen. Por eso §4.6 conserva el
formulario de la tarjeta.

### 1.2 · ❗❗❗ Además hay un DEFECTO, reproducido con control

- `WaiverSigner:89-92` exige correo verificado para firmar por un menor a cargo.
- `dependentNeedsSignature()` (`account/dependents.js:75-81`) mira `is_minor`, `mode`, `signed` y
  `outdated` — **no mira el correo verificado**. Ofrece el formulario igual.

```
(a) ALTA con titular SIN correo verificado: OK, id=1      ← no exige firma
(b) GET /me/dependents -> waiver={"signed":false,...}      ← la tarjeta ofrece firmar
(c) POST .../waiver (correo SIN verificar) -> 409 waiver_email_unverified
(d) CONTROL con el correo verificado        -> 201
```

▶ **Es el mismo defecto que `#329` arregló para el TITULAR**, vivo en la tarjeta del MENOR.

⚠️ **A quién afecta**: **no** a quien entra con Google (`GoogleSignup:95` verifica en el acto), sino
a quien se registró con contraseña y no abrió el correo (**17 de 25 cuentas locales**).

### 1.3 · ❗❗❗ Por qué NO se relaja la regla — el escenario, REPRODUCIDO

La primera versión proponía exceptuar `SUBJECT_DEPENDENT` en `WaiverSigner`. La revisión lo recorrió
de punta a punta y midió el desenlace:

1. Un tercero abre cuenta con `victima@ejemplo.test` **sin verificar** (hoy ya puede).
2. Declara hasta **20** menores (`DependentSettings::maxPerAccount()`) con nombre, apellidos y fecha
   de nacimiento de niños **reales**. Nada se verifica.
3. Con la relajación, **los firma**. La firma copia `holder_email` = el correo de la víctima y entra
   en la cadena de hashes.
4. La víctima reclama su cuenta (P12, entrar con su Google): la defensa de `#342`/`#347` funciona
   **y le entrega los 20 menores con sus firmas intactas**, `WaiverChain::verify()` → `ok=true`.
5. **No puede deshacerlo**: `remove()` con firma detrás solo **desvincula**, y `anonymize()` conserva
   firma y menor a propósito (art. 17.3.e). Medido tras `anonymize()`: 2 filas, nombres y apellidos
   en claro.
6. **CONTROL — el mundo de hoy**: los mismos menores, **sin** firma, se borran de verdad (0 filas).

⚠️⚠️ **La honestidad del análisis**: ese estado ya es alcanzable hoy **pagando** (`autoVerifyBuyer`
verifica el buzón con un cobro real). La relajación no inventa la amenaza: **le retira el peaje** — y
eso importa porque `#342` aceptó ese residuo con el argumento textual *«esa cuenta está vacía en la
práctica, dejar reservas exige pagarlas»*. **Declarar menores no cuesta nada.**

⚠️ **Y el precedente de `#347` no sostenía la extensión**: allí la firma del menor invitado nace
atada a un **pedido pagado** y a una **URL firmada** — hay otra prueba de que detrás hay alguien
real. Aquí no hay ninguna.

---

## 2. Objetivo

1. Declarar un menor y aceptar su exención son **un solo gesto**: no se puede completar el alta sin
   aceptar.
2. **Ninguna firma nace sobre un buzón no demostrado.**
3. Ninguna cuenta queda sin poder declarar un menor por el estado de su correo.
4. Los menores **ya declarados** sin firma no se rompen, y dejan de ser invisibles.
5. Fuera del modo `interno`, o sin versión publicada, el alta funciona **exactamente como hoy**.

**Fuera de alcance:** el mostrador (`[DECIDIDO owner]` `#212`: el panel no declara menores); la
asignación en el embudo, que ya exige la firma.

---

## 3. Opciones consideradas

| Opción | Veredicto |
|---|---|
| (a) Solo arreglar el 409 y avisar mejor | Cierra el defecto, **no el encargo**: seguirían siendo dos gestos. |
| (b) Bloquear la cuenta hasta firmar | Desproporcionado; descartada por el owner. |
| (c) Firmar en el acto relajando `#179` | ⛔ **DESCARTADA por la revisión**: §1.3, reproducido. |
| (d) **Aceptar al declarar; la firma nace con el buzón probado** ✅ | **ELEGIDA** (`[DECIDIDO owner, 2026-09-06]`). |

⚠️ **(d) no estaba en la primera versión de esta spec, y debería haber estado**: es el mecanismo que
`#179` construyó para este problema exacto en el alta de cuenta.

---

## 4. Diseño elegido

### 4.1 · La regla, en una línea

**Al declarar un menor se acepta su exención. Si el correo del titular está verificado, se firma en
el acto; si no, la aceptación queda RETENIDA y se convierte en firma al verificar** — exactamente lo
que `SelfSignup` + `SignPendingWaiverOnVerification` hacen con el alta de cuenta desde `#179`.

⚠️ **Así se cumplen los dos objetivos a la vez**: es un solo gesto de pantalla (objetivo 1) **y**
ninguna firma nace sobre un buzón sin demostrar (objetivo 2). Lo único que se aplaza es el efecto
probatorio, nunca el gesto.

### 4.2 · Dónde vive la aceptación retenida

Hoy es **una sola ranura por cuenta** (`users.waiver_pending_document_id` + canal + ip + user_agent).
Un titular puede tener **N** menores pendientes, así que la aceptación de cada uno vive **en su
propia fila**:

`dependents` gana `waiver_pending_document_id`, `waiver_pending_channel`, `waiver_pending_ip`,
`waiver_pending_user_agent` — **migración ADITIVA, todas nullable**, hermanas exactas de las de
`users`.

⚠️ **Una fila, una aceptación pendiente**: un menor no puede tener dos. Eso hace innecesaria una
tabla y mantiene la simetría con el titular.

### 4.3 · El alta, paso a paso

`DependentRegistry::add()` recibe el documento que la pantalla SIRVIÓ, ya resuelto por el llamante
(`WaiverAcceptance::currentDocument()`), y dentro de **una** transacción:

1. lock de la fila del titular (ya es la primera sentencia);
2. tope y validaciones de hoy;
3. `Dependent::create(...)`;
4. **si el correo está verificado** → `WaiverSigner::sign(...)` con `SUBJECT_DEPENDENT`;
   **si no** → se escriben las cuatro columnas de §4.2 en la fila del menor;
5. si algo falla, la transacción se deshace y **el menor no se crea**.

⚠️⚠️ **`WaiverSigner::sign()` re-comprueba la vigencia BAJO EL LOCK y lanza desde dentro**
(`:98-100`, la S-3 de `#181`). **La primera versión de esta spec afirmaba lo contrario**, tomando
prestada una garantía de `SelfSignup` — que **no firma** desde `#179`. Consecuencia medida: la
excepción escapaba a `ApiExceptionRenderer` → **500 `server_error`**, donde sus tres hermanos que
firman devuelven **409 `waiver_document_stale`**.
▶ `MeDependentsController::store()` **captura `WaiverDocumentStaleException` → 409**.

⚠️ La FK dura de `waiver_signatures.subject_id` obliga al orden 3 → 4 (confirmado con control:
`FOREIGN KEY constraint failed`).

⚠️ **La auditoría va al FINAL** de la sección crítica: `AuditLogger` se traga cualquier `Throwable`,
y en mitad de la transacción un error suyo podría dejar un 201 con la base de datos sin escribir.

### 4.4 · ❗❗ El CONTRATO va antes que el código

La primera versión no lo nombraba ni una vez. Lo medido:

- `DependentCreateRequest` es `required:[name,surname,relationship,born_on]` +
  **`additionalProperties: false`** (`openapi/v1.yaml:6126-6128`): los campos nuevos son **422 por
  esquema** hasta que se amplíe.
- Ampliarlo pone ROJO a `ApiContractTest::test_response_schemas_are_strict_enough_for_a_rename_to_fail`
  hasta declararlos en `OPTIONAL_BY_DESIGN` **con su porqué**. Precedente exacto:
  `GoogleSignupRequest => ['accept_waiver','waiver_document_id']`.
- `POST /me/dependents` declara 201/401/422/429/503: **hay que añadir el 409**.
- La T0 deja **mintiendo** al contrato en dos sitios de `POST /me/dependents/{dependent}/waiver`
  (`:2209` y `:2249`): el bloque 409 **no se borra** (`waiver_not_internal` y `waiver_document_stale`
  siguen vivos), cambia su descripción.

▶ **Dos campos, no uno** (el precedente son las dos altas de cuenta): `accept_waiver` (el acto
afirmativo del art. 7.1) y `waiver_document_id` (qué texto se sirvió). *La casilla es la aceptación;
el identificador solo dice cuál.*

⚠️ **Consecuencia asumida**: un cliente del contrato v1 no actualizado pasa de «siempre funciona» a
fallar. Hoy **no hay app móvil publicada**, así que el coste es cero — pero queda escrito.

### 4.5 · Cuándo NO hay nada que aceptar

Fuera del modo `interno`, o en `interno` **sin versión publicada**: el alta funciona **como hoy** y
no pide nada. Es la doctrina de `WaiverSettings` (*«un valor ausente cae al comportamiento
histórico»*) y la de `#348` (*«sin publicar no se pide nada y la venta sigue»*).

⚠️ **La puerta del modo vive en el LLAMANTE** (`MeDependentsController::store()` comprueba
`isInternal()` y resuelve el documento **antes** de entrar en `add()`), igual que
`AuthRegistrationController:113/155`. `WaiverSigner` no la tiene, y eso es una convención con un
infractor ya en el árbol (`SignPendingWaiverOnVerification:76`, ficha en `DEUDA.md`).

### 4.6 · Los menores YA declarados, y el arreglo del 409

No se tocan: una firma no se inventa retroactivamente. Cambian dos cosas:

1. **La tarjeta deja de ofrecer un botón que no puede funcionar** (§1.2): con el correo sin
   verificar ofrece **verificar**, no firmar — el arreglo de `#329` extendido al menor.
2. **El índice de la cuenta lo dice.** ⚠️⚠️ **Hace falta un TERCER estado con destino
   `ZONES.DEPENDENTS`**: reutilizar el aviso `sign` actual lleva a `ZONES.PRIVACY`, donde el titular
   sin verificar **sigue sin poder firmar la suya** — reconstruiría el callejón de `#329` por la otra
   puerta.

⚠️ **La tarjeta CONSERVA su formulario de firma**: lo necesitan estos menores y los que queden
`outdated` al publicarse una versión nueva. Lo que se retira es la posibilidad de **nacer** sin
aceptación.

### 4.7 · La pantalla del alta

Gana el texto plegable y la casilla, **reutilizando lo que ya está en `DependentCard`**
(`<details class="form__hint">` + `.check` + `register.accept_waiver`).

⚠️⚠️ **La lógica va a `account/dependents.js`, NO al `.vue`**: `DependentsZone.vue` está a **39 de
40 líneas** del techo de `SidebarComponentBudgetTest` (`CE-6`). Ese módulo ya tiene `node --test` y
ya es donde viven `dependentsView()` y `dependentNeedsSignature()`.

⚠️ El material ya está: la zona **ya llama** a `waiver.ensureLegal()` y ya maneja `stale`.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| `RGPD-01` | ⚠️⚠️ **NO es «ninguno»** (la primera versión lo decía). No cambia la conducta: **cambia la POBLACIÓN** — ver §5.1. |
| `RGPD-02` | La auditoría sigue llevando **ids, nunca el nombre**. |
| `SEC-04` | El documento se re-valida en el servidor bajo el lock. |
| El tope de menores | ⚠️ **No es `PAY-12`** (eso es el precio en servidor y el cap del carrito): el tope de menores **no tiene invariante propio**. O gana una fila, o se deja de citar `PAY-12`. |
| `SUITE` §6 | La re-entrancia del lock se verifica contra **MySQL**, no SQLite. |

### 5.1 · ❗❗ El cambio de población, y por qué obliga a fijar la retención

`remove()`/`anonymize()` deciden `hasReferences() ? unlink() : delete()`. **Una firma es una
referencia.** Con esta feature, en modo `interno` con versión publicada, **todo menor nuevo acaba
teniendo firma**, así que ninguno vuelve a caer en `delete()`.

Escenario: *«declaro a mi hijo por error y lo quito»*, que hoy **no deja ni un byte** (medido:
`deleted`, dependents=0, firmas=0), pasa a dejar firma con nombre completo, fecha de nacimiento, IP
y navegador.

⚠️⚠️ **Y hoy no se poda nada**: `WaiverSignature::prunable()` devuelve literalmente `where 1 = 0`
mientras los dos plazos sigan en `NULL`, que es su estado desde `#160`.

▶ **`[DECIDIDO owner, 2026-09-06]` — los plazos son REQUISITO DE SALIDA, no deuda de fondo:**
`waiver.retention_months = 60` y `waiver.dependent_retention_months = 60` (**5 años**, y en el menor
contados desde su 18.º cumpleaños). Se apoya en el plazo general de acciones personales del art.
1964 CC; en menores el plazo no corre hasta la mayoría de edad, que es justo por lo que esa columna
se cuenta desde los 18.

⚠️ **Paso de despliegue**: los dos ajustes hay que ponerlos en cada instalación. Sin ellos, la
feature conserva PII de niños **sin plazo**.

---

## 6. Plan de verificación empírica

### 6.1 · El alta

1. Declarar **sin** aceptar → **no se crea el menor** (contar filas antes y después, y comprobar que
   tampoco queda firma huérfana).
2. Aceptar **con el correo verificado** → menor **y firma vigente**, en la misma transacción.
3. Aceptar **sin el correo verificado** → menor **y aceptación retenida**; **cero firmas**.
4. Verificar el correo después → la aceptación **se convierte en firma** (y si el texto se
   republicó entre medias, **se descarta sin firmar**, como hace la del titular).
5. Texto caducado bajo el lock → **409**, no 500, y **el menor no existe** (control de filas).
6. Modo `externo` / sin versión publicada → alta **como hoy**, sin pedir nada.

### 6.2 · El arreglo del 409 (§4.6)

7. Titular sin verificar con un menor ya declarado: la tarjeta ofrece **verificar**, no firmar.
8. ⚠️ **CONTROL**: la firma del **titular por sí mismo** sigue exigiendo el correo verificado (409).
   Sin este caso, alguien relajará `WaiverSigner` «para que sea coherente».
9. El aviso del índice lleva a **`ZONES.DEPENDENTS`**, no a `ZONES.PRIVACY`.

### 6.3 · Lo que la revisión añadió, y sin lo cual la suite saldría verde sin probar nada

10. ⚠️⚠️ **El censo**: **15 ficheros / 28 métodos** pierden su premisa (necesitan un menor **sin**
    firma; p. ej. `WaiverStatusBatchTest:113-121` asevera `[false,true,false,true,false,true]`). Y
    ~32 métodos más sobreviven **por orden accidental** (el `add()` cae antes del primer `publish()`).
    Se reescriben con su motivo citando `#441`, **no se borran**.
11. ⚠️ **`MeDependentWaiverTest::test_an_unverified_holder_cannot_sign_for_a_dependent`
    (`:184-196`) NO cambia**: con la aceptación retenida, esa regla **sigue vigente**. Era la
    primera versión de esta spec la que la retiraba.
12. ⚠️ **`DependentPrivacyTest` nunca fija `waiver.mode`**, y sin fila el fallback es **`externo`**:
    su caso «sin firma → se borra» seguiría verde mientras producción, en `interno`, hace lo
    contrario. **Al menos un caso monta `interno`.**
13. **Concurrencia**: escenario `dependent` en `waiver:verify-chain` — N altas simultáneas del mismo
    titular sobre MySQL, midiendo idempotencia, re-entrancia del lock **y** el tope.
    ⚠️ `VerifyWaiverChainConcurrency` **no menciona `DependentRegistry`** hoy (siembra con
    `Dependent::create` y llama al firmador directo): correrlo tras esto **da verde sin tocar
    `add()`**, y el hook te manda correr exactamente ese comando.
14. **Presupuesto**: contar consultas del render con 0 y con N menores — el aviso de §4.6 no puede
    crecer con N.
15. Un caso que herede de **`ApiTestCase`** para el 201 (`#320`: *heredar de la clase equivocada
    convierte un test de contrato en un test de texto*), con `assertValidRequest()` +
    `assertValidResponse()`.

**Mutación**: `scripts/mutar-firma-al-declarar.sh`, con control y por **código de salida**.

---

## 7. Al desplegar

- **Una migración, aditiva** (§4.2): cuatro columnas nullable en `dependents`.
- ⚠️ **Los dos ajustes de retención** (§5.1). Sin ellos no se poda nada.
- ⚠️⚠️ **ANTES de desplegar, cuatro lecturas en producción** — la feature **puede no hacer nada**
  allí: `WaiverSettings::mode()`, `LegalDocuments::latestVersionNumber('waiver')`,
  `Dependent::count()` y cuántos tienen firma. **Si sale `externo`, esta feature no se activa**, y
  encenderla es decisión del owner **con la consecuencia** de que el asignador pasará a exigir firma
  a los menores ya declarados.
- ⚠️ `WaiverSigner` y `DependentRegistry` quedan en el `CRITICAL_RE`: el push exige `VERIFY_CONC=1`.
  ▶ **`DependentRegistry` hay que AÑADIRLO** — hoy no está ni en `CRITICAL_RE` ni en `CRITICAL_FILES`
  ni en `NON_CRITICAL_FILES` de `CriticalPathGateTest`, que es el peor de los tres estados.

---

## 8. Revisión y decisión

- **2026-09-06** · Defecto de §1.2 reproducido con control.
- **2026-09-06** · **Revisión adversarial de 7 lentes** (29 agentes, 6 bloqueantes). Cambió lo
  esencial: **la relajación de `#179` se descarta** (§1.3 reproducido) y entra la aceptación
  retenida; se corrige la garantía prestada de `SelfSignup` (500 → 409); nace la sección de
  contrato; `RGPD-01` pasa de «ninguno» a cambio de población; los plazos pasan a requisito de
  salida; y el plan de verificación gana el censo, la concurrencia y el caso de `interno`.
- **`#179` NO se modifica.** La marca «Modificada por `#441`» que la primera versión pedía **se
  retira**.
- **`[PENDIENTE: owner]`** (§4.6, sin cambios): si un menor sin firma debe **bloquear** algo más allá
  de lo que ya bloquea el asignador. Recomendación: **no**.

### 8.1 · ▶ T0 EJECUTADA Y EN EL ÁRBOL (2026-09-06)

**El arreglo del 409** (§4.6·1): la tarjeta deja de ofrecer un botón que solo podía fallar.

- `dependentWaiverAction()` en `account/dependents.js` devuelve un **ESTADO** (`sign` · `verify` ·
  `null`), no un booleano — el patrón de `#329` para el titular, ahora también para el menor.
- El dato sale del **contexto de cuenta** (getter `emailVerified` nuevo), no de la respuesta de
  menores: si cada pantalla lo dedujera por su cuenta acabarían discrepando.
- ⚠️ `=== false` y no `!`: con el contexto sin cargar se ofrece **firmar**. Esconder la acción a
  quien sí puede hacerla es peor que enseñarla a quien no.

**Verificación**: suite **4.372** (27.137 aserciones) · `dependents.test.js` **32 casos** ·
`SidebarDependentWaiverOfferTest` **4 casos** · `scripts/mutar-firma-al-declarar.sh` **7/7
mutaciones muerden** · Pint ✓ · `docs-check` ✓ · `npm run build` ✓.

⚠️⚠️ **Dos gates hicieron su trabajo y el segundo cambió el diseño:**

1. `SidebarMountTest` exige la **lista exacta** de rótulos del subgrupo: el aviso nuevo no podía
   entrar en silencio, y su **orden** también se asevera.
2. `SidebarComponentBudgetTest` puso `DependentsZone.vue` en **41 líneas sobre un techo de 40**, y la
   respuesta **no fue subir aquel techo**: el `rereadToken` —que es estado de PANTALLA— se mudó al
   `dependentsView()` del módulo plano, donde ya viven la página y el formulario desplegado. *Subir
   un techo después de extraer no es lo mismo que subirlo en vez de extraer.* Con la poda hecha, el
   chunk sube **275 → 276** (medido 275,27; quedan 0,73 KiB).

### 8.2 · ▶ T0.b EJECUTADA — la red, ANTES de la T1

**Por qué va antes**: la T1 mete la firma dentro de la transacción del alta. Un instrumento escrito
después mide el reposo, no el efecto.

**1 · El escenario `dependent` en `waiver:verify-chain`.** ⚠️⚠️ **Su propiedad NO es la idempotencia
de las otras dos, es la EXCLUSIÓN**: N altas simultáneas del mismo titular **contra el último hueco
del tope**, y solo una puede entrar. La sonda en serie gasta el penúltimo hueco a propósito — una
sonda que no lo gaste deja a la carrera sin nada que disputar.

▶ **VISTO FALLAR, que es lo único que hace válido un verde** (`#147`): con el `lockForUpdate()`
retirado de `add()`, doce altas simultáneas dejan **31 menores con un tope de 20**. Sobreventa sin
error y sin aviso — la familia de `AFORO-01` sobre una tabla que no es aforo.
⚠️ **Ese lock llevaba desde `#191` sosteniendo un invariante que nadie había medido**: la suite es
ciega por construcción (`SQLiteGrammar::compileLock()` devuelve cadena vacía). Los otros dos
escenarios siguen en verde.

**2 · `DependentPrivacyTest` monta modo `interno`.** Nunca fijaba `waiver.mode`, y sin fila el
fallback es **`externo`**: todo lo que medía se medía en un mundo que no es el de producción. El caso
nuevo fija que **hoy** «declaro por error y lo quito» no deja ni un byte, y está escrito **para
cambiar con la T1**: es el momento en que alguien tiene que mirar de frente el cambio de población de
§5.1.

**3 · `DependentRegistry` sale del limbo.** No estaba en ninguna de las tres listas del gate. Queda
declarado en `NON_CRITICAL_FILES` con su razón de HOY —escribe una ficha, no la cadena— y con la
instrucción de que **la T1 lo mueve a `CRITICAL_FILES` y al patrón del hook**.

**4 · El censo, MEDIDO y no estimado.** ⚠️ La revisión dijo «15 ficheros / 28 métodos»; medido con
una sonda que hace fallar el alta cuando el estado exigiría aceptación, son **31 casos en CUATRO
ficheros**: `DependentAssignerTest`, `DependentPrivacyTest`, `GateProfileTest` y
`WaiverStatusBatchTest`. Los cuatro tienen helper `add()` propio, así que se arreglan por helper y no
caso a caso.

▶ ❗❗ **Y el censo destapó lo que hace VIABLE la decisión del owner**: con la aceptación RETENIDA, un
menor puede nacer **sin firma** (titular sin el correo verificado), así que los casos que necesitan
«un menor sin firma» —`WaiverStatusBatchTest` asevera `[false,true,false,true,false,true]`— **siguen
teniendo sujeto**. Con la relajación descartada de §1.3 habrían perdido su premisa los 31.
⚠️ Eso **acota** además el cambio de población de §5.1: solo deja PII el menor de un titular ya
verificado.

### 8.3 · ▶ T1 EJECUTADA — declarar y aceptar son un solo gesto

**Contrato primero, como manda §4.4**: `DependentCreateRequest` gana `accept_waiver` y
`waiver_document_id` (declarados en `OPTIONAL_BY_DESIGN` con su porqué: son condicionalmente
obligatorios y OpenAPI 3.0 no sabe decirlo), y `POST /me/dependents` estrena el **409**.

**Migración aditiva**: cuatro columnas nullable en `dependents` (§4.2). El trinquete de columnas de
`DependentRegistryTest` saltó, que es exactamente para lo que existe: cada columna en esa tabla es
dato de un menor y hay que justificarla.

**Dominio**: `add()` exige la aceptación cuando hay algo que aceptar —y **la comprueba el escritor**,
no el llamante, para que ningún llamante futuro nazca por fuera—; firma si el correo está verificado
y **retiene** si no. `SignPendingWaiverOnVerification` sella las de los menores al verificar,
**cada una por su cuenta**, y `unlink()` limpia la pendiente de un menor retirado.

**Verificación**: suite **4.388** (27.189) · `DependentWaiverAtSignupTest` **15 casos** ·
`dependents.test.js` **34** · `scripts/mutar-firma-al-declarar.sh` **16/16 muerden** · los TRES
escenarios de `waiver:verify-chain` sobre InnoDB · Pint ✓ · `docs-check` ✓ · build ✓.

⚠️⚠️ **Lo que enseñó la ejecución, y que no estaba en el plan:**

1. **`accepted` es una regla IMPLÍCITA de Laravel**: falla también con el campo AUSENTE, aunque vaya
   con `nullable`. Con ella en la rama de «no exigible», una instalación en modo `externo` recibía
   **422 al declarar un menor** — justo el defecto que §4.5 existe para impedir, colado por la puerta
   de la validación. Tiene caso propio.
2. ⚠️ **Un caso de vigencia con el titular VERIFICADO no mide nada**, y lo dijo la mutación: ahí hay
   DOS capas (el controlador y la re-comprobación de `WaiverSigner` bajo el lock), así que quitar la
   primera pasa en verde. **La rama de RETENCIÓN no re-comprueba**, así que para un titular sin
   verificar la del controlador es la ÚNICA — y sin ella se guardaría como pendiente un texto que ya
   nadie puede leer, para sellarlo semanas después. Caso añadido.
3. **Una mutación retirada por EQUIVALENTE**, dicho en el arnés: el `catch (Throwable)` deliberado del
   listener absorbe el texto caducado, así que el efecto observable es el mismo (cero firmas) y solo
   cambia qué dice el log. *Atar un caso al texto de un log sería vigilar el instrumento, no la regla.*
4. **El censo se resolvió con un TRAIT** (`Tests\Support\DeclaresDependents`) en vez de ocho copias
   del mismo bloque: reproduce la ficha HEREDADA —el menor declarado antes de esta tanda— **pasando
   por el escritor de verdad**, así que el tope, el lock y la minoría se siguen ejerciendo.
5. **Los presupuestos del cajón volvieron a cambiar el diseño y otra vez sin subir su techo**: el
   componente llegó a 42/40 y el `document_id` se mudó al CONTEXTO —es del mismo tipo que `messages`
   y `auth`—, con lo que la acción volvió a una línea. El chunk sube **276 → 277** (medido 276,15).
6. ⚠️ **El verificador de concurrencia se puso ROJO al terminar la T1, y era correcto**: su sonda en
   serie no aceptaba la exención. Actualizado, ahora mide **dos cosas** —el tope y que la firma se
   escriba DENTRO de la transacción del alta—: `firmas de menor: 2 (esperadas 2)`.

### 8.4 · ▶ T2 y T3 EJECUTADAS — el carril está CERRADO en código

**T2 · el aviso del índice.** `account-context` gana `waiver.dependents_pending` y el cajón estrena un
**TERCER estado** (`WAIVER_NOTICE_DEPENDENTS`) con destino a la zona de MENORES.

⚠️⚠️ **No es una variante del `sign`, y el motivo es el destino**: aquél lleva a Privacidad, que es
donde el titular firma LA SUYA. Mandarle allí por la de un menor sería el callejón de `#329`
reconstruido por la otra puerta. ⚠️ Va **después** de la suya (`#331`, «para no saturar»): lo primero
que tiene que resolver es su propia exención.

⚠️ **Coste medido**: el contexto pasa de **7 a 8** consultas en cada página con sesión. Es **UNA**
consulta (`EXISTS`, y solo en modo `interno`); la alternativa evidente —`WaiverStatus::forDependents()`—
cuesta cuatro. ⚠️⚠️ **La vigencia no se redacta dos veces**: sale del mismo documento que el resto del
bloque ya calcula.

**T3 · los plazos.** `[DECIDIDO owner]` **60 y 60 meses** (art. 1964 CC; en el menor desde su 18.º
cumpleaños, porque hasta entonces el plazo no corre). ⚠️ **No se siembran en el producto**: el plazo
es criterio jurídico de cada instalación. Queda como **paso de despliegue** en
`INSTALACION-CLIENTE.md`, y el `[PENDIENTE: owner]` de `WaiverSettings` se retira.

**Verificación del carril completo**: suite **4.392** (27.197) · `DependentWaiverAtSignupTest` **19
casos** · `dependents.test.js` **34** · `waiver.test.js` con el tercer estado ·
`scripts/mutar-firma-al-declarar.sh` **19/19 muerden** · los TRES escenarios de `waiver:verify-chain`
sobre InnoDB · Pint ✓ · `docs-check` ✓ · build ✓.

⚠️⚠️ **Dos casos más nacieron sin morder y los dos los dijo la MUTACIÓN**, no la lectura:
 · **el menor RETIRADO**: sin el filtro de activos, quien quitara a un menor sin firma se quedaría con
   el aviso encendido **para siempre y sin forma de apagarlo** — la pantalla a la que le manda ya no
   lo enseña;
 · **la VIGENCIA del aviso**: sin ella, una firma de una versión anterior lo apagaría, que es
   exactamente el caso recurrente que el aviso existe para cubrir.

⚠️ **Trampa del arnés pagada**: `CustomerAccountContext` es SINGLETON y **memoiza por usuario**, así
que dos peticiones del mismo caso comparten instancia y el CONTROL fallaba con el producto sano. En
producción da igual —cada petición levanta su contenedor—. Es la trampa de `OperatingSchedule` de
`#465`: *un caso que pregunta antes de sembrar mide el estado de antes.*

▶ **Lo que queda del carril: nada de código.** Solo el OJO del owner y, al desplegar, las cuatro
lecturas de §7 más los dos ajustes de retención.
