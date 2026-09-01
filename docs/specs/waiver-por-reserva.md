# [SPEC] El justificante de un menor invitado a una reserva («waiver offshore»)

> Estado: 🟦 **T1 + T2 + T3 EN EL ÁRBOL** (2026-09-01, `DECISIONES #328`, `#335` y `#337`) — el
> dominio (§8.1), la pantalla pública (§8.2) y las seis superficies (§8.3). **Queda la T4**: el guion
> de navegador con el anti-bot encendido y el OJO del owner.
> Carril **P3** de `ESTADO.md` — **con el alcance ampliado por el owner**: ver §1.2.
> Subsistema padre: `docs/specs/waiver-probatorio.md`. Entidad hermana: `docs/specs/menores-a-cargo.md`.
>
> ❗❗❗ **EMPIEZA POR §11 SI YA HABÍAS LEÍDO ESTA SPEC**: la revisión adversarial encontró **dos
> BLOQUEANTES** y una afirmación central del diseño que era **FALSA** —«no cambia una línea del
> mecanismo existente»—. Cambia, y justo donde más duele. Todo está corregido en el cuerpo; §11 es el
> registro de qué se creyó y qué se midió.

---

## 1. Contexto y problema

### 1.1 El caso, con las palabras del owner

> *«Cuando un profesor va a hacer una excursión, imprime un papel, diciendo que van a ir a x sitio,
> y el padre tiene que poner sus datos, los del niño y firmar, como que lo aprueba y está todo ok.
> Para nosotros es lo mismo pero digital y ligado a la reserva.»*

Y el caso que lo hace general, también suyo:

> *«Un adulto reserva para su hijo que es su menor a cargo y para el amigo de su hijo. El amigo de
> su hijo tendría que tener ese justificante. […] Un enlace a una pantalla limpia donde se da ese
> enlace al adulto, al padre, a los padres, y rellenan sus datos, los del menor y aceptan el waiver,
> sin estar registrados, y eso está ligado a la reserva. Si están registrados, pueden iniciar sesión
> y ahorrarse rellenar datos, pero ese waiver es puntual y está ligado a la reserva. El responsable
> es el usuario que hace la reserva.»*

### 1.2 ❗ Esto CORRIGE el alcance que la doc tenía escrito

`ESTADO.md` (carril 6) y `00-REFACTOR.md` describían P3 como *«la autorización de los padres a un
tercero»* dentro del carril de **excursiones de colegio**. **No es una feature de excursiones.** Es
una feature del waiver: cualquier reserva en la que entra un menor que **no es menor a cargo** de
quien reserva. La excursión de colegio es el caso extremo (100 menores, ninguno suyo), no el caso.

Consecuencia práctica, y no es menor: **el mecanismo no puede apoyarse en nada que solo tengan los
packs**, porque el caso del amigo del hijo son entradas sueltas (§1.5).

### 1.3 Por qué no vale lo que ya existe

**No vale `dependents`** (menores a cargo) — y lo dijo el propio owner: los menores de una excursión
*«no son menores a cargo del tutor»*. Meterlos ahí les daría **permanencia** (la ficha sobrevive a la
visita y a la mayoría de edad, `menores-a-cargo.md` §4.1), **tope por cuenta** (`DependentSettings`)
y haría vivir en la cuenta del que reserva los datos de 100 menores ajenos. Un justificante es
**puntual**: nace con una reserva y se agota con ella.

**Tampoco vale una columna de «ámbito» en `dependents`**, que era la salida barata. Es exactamente la
trampa que `#324` pagó con `prices`: *añadir una dimensión a una tabla compartida cambia el
significado de todos los agregados que la leen, y no falla nada*.

⚠️ **Cuántos son sus lectores: la primera redacción de esta spec dijo «seis» y la revisión midió
~14** — `DependentRegistry` (lista y tope), `DependentAssigner`, `GateProfile`, `Dependent::scopeReferenced`
y `prunable()`, `User::anonymize()`, `AccountPrivacy::export` (art. 20), `HolderDependents`,
`AssignedDependents`/`AssignsDependents`, `WaiverSigner`, `WaiverStatus::forDependent(s)`,
`WaiverProof`, `MeDependentsController`/`DependentResource`, `OrderEventDataResource` y
`PurgeCustomerData`, **más dos FK RESTRICT en la propia base de datos**. *La conclusión no cambia: se
refuerza. Pero el número era mío y estaba mal, y una cifra inventada en una spec es una cifra que
alguien citará.*

**No vale el enlace del post-form.** Es **uno por reserva y enseña los datos de TODOS los invitados**,
alergias incluidas —datos de salud—. Repartirlo a veinte padres es una fuga, no una entrega.

### 1.4 Los DOS bloqueos estructurales, medidos

**(a) `waiver_signatures.user_id` es `NOT NULL`** con `restrictOnDelete`, y la cadena de hashes se
agrupa por `(user_id, sujeto)`. Estaba anotado en `ESTADO.md` y es cierto: hoy no cabe la firma de
alguien sin cuenta. ▶ **Y se disuelve sin tocarlo** (§4.1): el `user_id` de la fila es *el que
reserva* —el **responsable**, que es literalmente lo que el owner ha decidido— y el padre firmante
entra como identidad copiada. La columna sigue `NOT NULL`, y **no hay migración destructiva sobre una
tabla que ya tiene firmas en producción**. ▶ Verificado además que **siempre hay responsable**:
`orders.user_id` es `NOT NULL` con FK RESTRICT — no existe compra sin cuenta.

**(b) 🆕 `waiver_signatures.subject_id` tiene FK dura a `dependents.id`** con `restrictOnDelete`
(migración `database/migrations/2026_08_27_200000_dependent_waiver_signatures.php`). **Esto la doc no
lo tenía medido**, y es el bloqueo que de verdad decide el diseño: **un tercer tipo de sujeto no puede
reutilizar `subject_id`**.

▶ **Y está probado ejecutando, no leído**: insertar una fila con `subject_type='guest_minor'` y un
`subject_id` inexistente da `SQLSTATE[23000] … 1452 Cannot add or update a child row`; el control con
`subject_id = NULL` se acepta. ⚠️ **Y la suite lo ejerce igual**: `config/database.php` activa
`foreign_key_constraints`, así que SQLite también rechaza —*aquí no hay divergencia de motor, a
diferencia de §4.8*—.

Quitar esa FK sería regresar un endurecimiento deliberado (`menores-a-cargo.md` §4.4: «quitar es
DESVINCULAR», impuesto por la base de datos y no solo por una guarda de modelo). ▶ Se resuelve con
**columna propia** (§4.3).

### 1.5 Lo que hoy NO existe y hay que construir

Medido:

- **No existe «plaza nominal»**: `order_items.quantity` es un número. Una línea de 3 entradas no son
  tres personas, y una de 100 tampoco.
- **Solo los PACKS tienen fichas por persona**: `OrderItem::guestFormStatus()`
  (`app/Domain/Booking/Models/OrderItem.php`) devuelve `null` si el producto **no es pack** o no
  declara `guest_fields`. El caso del amigo del hijo son entradas → **cero fichas**.
- **No existe ninguna superficie pública que ESCRIBA identidad de un tercero sin sesión.** El
  post-form escribe datos de invitados, pero de una reserva cuya titularidad la prueba la firma HMAC
  del enlace; aquí lo que se crea es **una persona nueva y una prueba legal**.

---

## 2. Objetivo

**Que un adulto sin cuenta pueda autorizar por escrito, y con valor probatorio, la entrada de un
menor concreto a una reserva concreta — desde un enlace que le pasa quien reservó.**

Criterios de éxito, todos medibles:

1. Un padre sin cuenta abre el enlace, rellena sus datos y los del menor, acepta el texto vigente del
   waiver y queda registrada **una fila de `waiver_signatures`** con hash canónico correcto y
   **encadenada en su propia cadena**, verificable por `waiver:verify-chain`.
2. **Dos padres distintos del mismo pedido producen DOS firmas y DOS cadenas.** Es un criterio de
   éxito y no un detalle: con el mecanismo de hoy produciría **una** (§11·B1 y §11·B2).
3. La prueba **identifica a las dos personas** (el firmante y el menor) aunque después se anonimice la
   cuenta del que reservó, y **dice con todas las letras qué datos no están verificados**.
4. **El responsable es el que reserva**: su cuenta es el ancla de la cadena y él ve quién ha firmado y
   quién falta — **sin ver ni un dato de contacto de otro padre en ninguna superficie**, incluida la
   API y el PDF.
5. La puerta ve al menor autorizado con su edad y su estado, junto a los menores a cargo, **sin que
   crezca el número de consultas por fila** (hoy el techo es 28,
   `GateProfileTest::test_the_composition_runs_a_constant_number_of_queries`).
6. La pantalla pública **no filtra ni un dato de otro firmante**, aguanta anti-bot y tiene tope.

### Fuera de alcance (explícito)

- **Verificar la identidad del firmante** (correo, DNI, SMS). `[DECIDIDO owner]` §7·1.
- **Firmar en la puerta.** `[DECIDIDO owner]` §7·3.
- **Datos de salud del menor** (alergias). `[DECIDIDO owner]` §7·6.
- **Editar un justificante ya firmado.** Es append-only: una corrección es una autorización nueva —
  y con la regla de §7·9 («un niño, un papel»), por teléfono con el parque.
- **Aforo, precio y cupo.** Un justificante no vende ni reserva nada: acompaña a una línea ya vendida.
- **Cambiar el plazo de conservación.** Se reutiliza el que ya existe (§4.14) y su valor sigue
  `[PENDIENTE: owner]` para todo el waiver. ⚠️ Medido: **los dos plazos valen `NULL` hoy, así que no
  se poda nada de nada**.

---

## 3. Opciones consideradas

| # | Opción | Por qué no (o sí) |
|---|---|---|
| A | **El menor invitado es un `dependent` con una columna de «ámbito»** | ❌ Descartada. Cero tablas nuevas y todo el mecanismo gratis — pero cambia en silencio el significado de sus **~14** lectores (§1.3). Es la lección de `prices` de `#324`. |
| B | **Tabla propia para la persona + reutilizar `waiver_signatures`** | ✅ **Elegida.** Una tabla nueva y una columna de sujeto nueva. La prueba sigue siendo UNA: una cadena, un verificador, un PDF, una poda. |
| C | **Subsistema probatorio propio y separado** | ❌ Descartada. Duplica el hash canónico, el encadenado, el verificador, el PDF, la poda y el régimen RGPD. Dos mecanismos probatorios que tienen que decir lo mismo es deuda, no aislamiento. |
| D | **Quitar la FK `subject_id → dependents` y hacerla polimórfica** | ❌ Descartada. **Regresa un endurecimiento** que hoy la base de datos impone y pasaría a imponer solo una guarda de modelo. |

⚠️ **C y D no vuelven a la mesa**: la FK de (b) está medida y rechaza de verdad (§1.4).

---

## 4. Diseño elegido

### 4.1 Las TRES clases de sujeto

| | Titular | Menor a cargo | 🆕 **Menor invitado** |
|---|---|---|---|
| `user_id` | el titular | el titular | **el que reserva (el RESPONSABLE)** |
| `subject_type` | `holder` | `dependent` | **`guest_minor`** |
| `subject_id` (FK→`dependents`) | `null` | el menor | `null` |
| 🆕 `subject_authorization_id` (FK→nueva) | `null` | `null` | **la autorización** |
| `holder_name` / `holder_email` | el titular | el titular | el que reserva |
| `subject_name` / `subject_born_on` | `null` | el menor | **el menor** *(mismas columnas)* |
| 🆕 `signer_*` | `null` | `null` | **el padre/tutor que firma** |
| **Clave de cadena** | `(user, holder)` | `(user, dependent, subject_id)` | **`(user, guest_minor, subject_authorization_id)`** |

**Lo que hace que esto encaje**: el owner decidió que *«el responsable es el usuario que hace la
reserva»*. Eso no es una frase de producto, es **la clave de la cadena**. Con el responsable en
`user_id`, la columna sigue `NOT NULL` y el punto de serialización sigue siendo el lock de su fila de
`users`.

⚠️⚠️ **PERO EL MECANISMO SÍ CAMBIA, Y LA PRIMERA REDACCIÓN DE ESTA SPEC DECÍA LO CONTRARIO.** Escribí
*«no cambia una línea del mecanismo existente: solo se añade una rama»* y es **falso**: la clave de
sujeto está hoy **cableada a `subject_id`** en tres sitios, y con `subject_id = NULL` los tres se
cruzan (§11·B1, §11·B2). **`WaiverSigner`, `WaiverChain` y `VerifyWaiverChainConcurrency` son código
que se MODIFICA en la T1**, y la clave pasa a resolverse en **un solo sitio** —hoy está duplicada en
dos ficheros, que es exactamente cómo diverge—.

⚠️ `subject_name`/`subject_born_on` **se reutilizan tal cual**: ya existen desde el esquema canónico
v3 y significan exactamente lo mismo. No se añade una segunda pareja de columnas para decir lo mismo.

### 4.2 La entidad nueva — `guardian_authorizations` (Identity)

Una fila = **una autorización**: un menor, el adulto que responde por él, y la reserva a la que va.

```
guardian_authorizations                                                          (futuro)
  id
  order_id                unsignedBigInteger, FK → orders, RESTRICT   # §4.2.1
  minor_name              string(120)
  minor_surname           string(120)
  minor_key               string(255)     # normalizado, §4.8 — la clave de unicidad REAL
  minor_born_on           date
  guardian_name           string(120)
  guardian_surname        string(120)
  guardian_relationship   string(16)      # Dependent::RELATIONSHIPS (lista fija, máx. 14 chars)
  guardian_email          string(255) nullable
  guardian_phone          string(32)  nullable
  created_at              timestamp
  unique (order_id, minor_key)            # «un niño, un papel» — §7·9
  index (order_id)
```

- **Vive en Identity y referencia el pedido por su id**: es el patrón de `dependent_assignments`
  (Booking no mira a Identity; verificado en `ModuleBoundariesTest::ALLOWED`). **Sin relación Eloquent
  a `Order`**, como allí.
- **`guardian_relationship` reutiliza `Dependent::RELATIONSHIPS`** (padre · madre · tutor/a legal ·
  abuelo/a · otra). Lista cerrada, por el mismo motivo que allí. **Es lo que sostiene que ese adulto
  pueda firmar por ese menor**, así que es obligatorio.
- **Apellidos en columna aparte**, como `#236` hizo con `dependents`: quien lee la prueba no conoce a
  la familia.
- **Sin `updated_at`**: la fila no se edita.
- **Sin datos de salud** (§7·6).

#### 4.2.1 ⚠️ `order_id` es RESTRICT — y eso OBLIGA a tocar `PurgeCustomerData`

La primera redacción decía que RESTRICT «se separa a propósito» de `dependent_assignments` (CASCADE) y
lo dejaba ahí. **La revisión midió que eso rompe la limpieza de go-live** (§11·A6): la purga hace
`Order::query()->delete()` sobre **TODOS** los pedidos, pero solo borra `waiver_signatures` de los
usuarios **purgados**. Con las dos FK RESTRICT en medio, la transacción entera abortaría **el día del
lanzamiento de un cliente**.

**Y CASCADE tampoco lo arregla**: la FK `waiver_signatures.subject_authorization_id` es RESTRICT
—innegociable, es una prueba—, así que el cascade se estrellaría igual un peldaño más allá.

▶ **La salida es explícita y va escrita en la T1**: `PurgeCustomerData` aprende **tres pasos nuevos,
en este orden, antes de `Order::query()->delete()`**:

```
1. DB::table('waiver_signatures')->where('subject_type','guest_minor')->delete();   (futuro)
2. DB::table('guardian_authorizations')->delete();
3. …y solo entonces los pedidos.
```

⚠️ **Sí: eso borra firmas de cuentas que la purga CONSERVA, y es correcto.** La purga es un borrado de
go-live que se lleva **todos** los pedidos; una prueba de una visita cuyo pedido ya no existe no
prueba nada. Queda escrito aquí para que nadie lo lea como un descuido.

### 4.3 La firma — esquema canónico **v4**

Sobre `waiver_signatures`, todo **añadiendo**:

```
+ subject_authorization_id   FK → guardian_authorizations, RESTRICT, nullable   (futuro)
+ signer_name                string(255) nullable
+ signer_email               string(255) nullable
+ signer_phone               string(32)  nullable
+ signer_relationship        string(16)  nullable
~ subject_name               varchar(120) → varchar(255)     # §4.3.1
```

`WaiverSignature::CANONICAL_VERSION` sube a **4**: v3 + `subject_authorization_id`, `signer_name`,
`signer_email`, `signer_phone`, `signer_relationship`.

⚠️ **Subir la versión canónica NO invalida nada firmado**, y está **medido sobre las firmas reales de
la BD local: 24 firmas, 21 en v2 y 3 en v3, y `verifyHash()` da `true` en las 24.** El mecanismo ya
convive con dos versiones. **Nunca se edita una entrada existente del array.**

⚠️⚠️ **`signer_user_id` NO se añade, y es deliberado.** Sería cómodo enlazar la firma con la cuenta
del padre cuando la tiene, pero una columna con `ON DELETE SET NULL` **no puede estar dentro de un
hash que se verifica**. Y no es teoría: está **medido dos veces** en este repo (§10).

#### 4.3.1 ⚠️ `subject_name` sube a 255 — un defecto PREEXISTENTE que esta spec heredaba

`WaiverSigner` corta el nombre del sujeto con `mb_substr(..., 0, 255)` y un comentario que dice, con
esas palabras, *«se corta a lo que admite la columna»*. **La columna es `varchar(120)`.** Medido: un
menor a cargo con nombre y apellidos de 120+120 da

```
LEN fullName=241 → SQLSTATE[22001]: 1406 Data too long for column 'subject_name'
```

⚠️ **Y en SQLite —donde corre la suite— eso pasa en VERDE**, guardando 241 caracteres. *Un test verde
no dice nada sobre un límite de columna que solo existe en MySQL.*

Es un defecto **vivo hoy** para menores a cargo, no de esta feature. Se arregla aquí (la columna sube
a 255, que es lo que el corte ya supone) y **la ficha va a `DEUDA.md`** porque el caso del menor a
cargo existe desde `#198`.

### 4.4 La cadena — y los DOS sitios donde hoy está cableada a `subject_id`

La cadena de un menor invitado agrupa por `(user_id, 'guest_minor', subject_authorization_id)`, y el
`prev_hash` de la primera firma de cada autorización es `null`.

⚠️⚠️ **Hoy eso NO funciona, y por eso la T1 toca tres ficheros** (detalle en §11):

| Fichero | Qué hace hoy | Qué pasa con `subject_id = NULL` |
|---|---|---|
| `WaiverSigner` | busca la firma anterior con `where('subject_id', $request->subjectId)` | Laravel lo convierte en `is null` (verificado: `… and subject_id is null`) → **todas** las autorizaciones del responsable comparten búsqueda, y la idempotencia por versión **devuelve la firma de OTRO menor**: el segundo padre no firma y nadie se entera |
| `WaiverChain` | clave `subject_type.':'.($subject_id ?? '')` | todos los menores invitados caen en **`guest_minor:`**, una sola cadena → el verificador declara **ROTA** una cadena sana |
| `VerifyWaiverChainConcurrency` | la misma clave, **duplicada**, y `chains === 2` **clavado** como criterio de éxito | el verificador de concurrencia mide otra cosa |

▶ **La clave de sujeto pasa a resolverse en UN solo sitio** —un método del propio modelo,
`WaiverSignature::chainKey()` (futuro)— que los tres consumen. Hoy está en dos ficheros a mano, que es
cómo se diverge.

⚠️ **El punto de serialización sigue siendo el lock de la fila de `users` del responsable**, y eso es
lo que hace imposible la carrera contra el `UNIQUE` de §4.2 (dos envíos del mismo menor se ordenan).
▶ Y de ahí sale §4.9: **la autorización se crea DENTRO de esa transacción y bajo ese lock**, nunca
antes — si no, un envío que falle al firmar dejaría PII de un menor **sin prueba detrás**, que es
justo lo que la regla de `dependents` («sin firma, se borra») existe para evitar.

### 4.5 🆕 Las lecturas EXISTENTES de `waiver_signatures` que hay que acotar

Esta sección no estaba, y es la que evita la fuga. **Poner al responsable en `user_id` hace que sus
firmas de menores invitados aparezcan en todo lo que lista «las firmas de este usuario».** Medido
(§11·A3): con dos autorizaciones en la cuenta del responsable, `GET /me/waiver` devuelve

```
signed=false · nº signatures = 2
  -> subject=guest_minor dependent_id=0 dependent_name='Luis' pdf=…/me/waiver/320/pdf
  -> subject=guest_minor dependent_id=0 dependent_name='Ana'  pdf=…/me/waiver/319/pdf
```

…y **el PDF se sirve**, porque `MeWaiverController::pdf()` autoriza comparando `signature->user_id`
con el usuario, y ese `user_id` **es** el responsable. Con §4.13 implementada, el responsable se
descargaría **nombre, apellidos, relación, correo, teléfono, IP y user-agent del otro padre**.

| Lector | Hoy | Qué se hace |
|---|---|---|
| `WaiverStatusResource` (`GET /me/waiver`) | lista `waiverSignatures()` **sin filtrar sujeto**; `dependent_id` sale `0` | **Acotar a `holder` + `dependent`.** Estas firmas **no se publican aquí**: `/me/waiver` significa «mis firmas», y ésta no es suya |
| `MeWaiverController::pdf()` | autoriza solo por `user_id` | **Añadir la condición de sujeto**: 404 para `guest_minor` |
| `filament/users/partials/waiver-proof.blade.php` | lista sin filtrar y **rotula el sujeto como «menor a cargo»** | Acotar, o pintar el tercer sujeto con su nombre — rotularlo mal sería mentir en una pantalla probatoria |
| `ViewUser` (audit `'signatures' => …count()`) | contador sin filtrar | Acotar: el contador miente |
| `openapi/v1.yaml` | `subject: enum [holder, dependent]`, `additionalProperties: false` | **No se toca el enum**, precisamente porque estas firmas no se publican. ⚠️ Pero entra en la T1 **con nombre propio**: `ApiTestCase` valida contra el esquema con Spectator, así que en cuanto un test tenga una firma `guest_minor` en la cuenta, `MeWaiverTest` se pone rojo si el filtro falta — es **la guarda que ya existe**, y hay que usarla a propósito |

✅ **Lo que la revisión midió LIMPIO y no hay que tocar**: `WaiverStatus::for()` y
`WaiverStatus::forDependents()` **sí** filtran por `subject_type`.

### 4.6 El enlace: una HOJA EN BLANCO

`[DECIDIDO owner]` §7·2. **Un enlace por pedido**, que el que reserva reparte (correo, WhatsApp,
impreso con un QR). Quien lo abre ve **un formulario vacío**, nunca lo que otros han escrito.

- **Ruta pública `/autorizacion/{order}`** (futuro), `GET` + `POST`, sin Livewire, con
  `<x-focused-layout>` — el mismo esqueleto que el post-form. ⚠️ **No se llama `/reserva/…`**: en este
  repo «reserva» es un `OrderItem` (`/reserva/{reservation}/datos-invitados`) y esto cuelga del
  PEDIDO; dos significados en el mismo prefijo de URL es una trampa para el siguiente.
- **Autorización por firma HMAC** (`temporarySignedRoute`). La firma **es** la prueba de que el
  enlace se lo dio quien podía dárselo.
- **Caduca**: última franja del pedido **+ 14 días**, la fuente que `Order::guestFormLinkExpiresAt()`
  ya usa y que `RGPD-03` fija. No se inventa un plazo nuevo.
- **La escalada es `403 → 410 → 404`, EN ESE ORDEN**, reutilizando `AuthorizesGuestForm`: primero se
  autoriza (403), después se comprueba si el titular se anonimizó (410), y solo entonces la
  elegibilidad (404). ⚠️ **La primera redacción decía «410 antes de comprobar cualquier otra cosa» e
  invertía justo la propiedad de seguridad que citaba dos líneas después**: con el orden al revés, un
  desconocido deduce por el código de estado si un pedido existe.
- **Se cierra al pasar la visita**: `GET` sigue abriendo en modo informativo y `POST` responde **409**
  — el par de conductas de `guest_form_closed`. Autorizar a posteriori una visita que ya ocurrió no es
  una prueba de nada.
- **Con sesión iniciada, los campos del adulto vienen rellenos** de su cuenta y son editables.
  ⚠️ Iniciar sesión **no cambia nada más**: no verifica, no enlaza la cuenta (§4.3) y el justificante
  sigue siendo puntual.
- **`no-store`** (`RGPD-04`).

### 4.7 Anti-abuso — es una superficie pública que ESCRIBE

Es lo más expuesto del diseño. Cuatro defensas, y ninguna sustituye a las otras:

1. **Turnstile** en el `POST` (`Platform\Services\Turnstile`, no-op sin claves — verificado). `SEC-06`.
2. **Límite por IP** además del anterior: un CAPTCHA resuelto no es una barrera de volumen.
3. **Tope por pedido.** ⚠️⚠️ **La primera redacción decía «la suma de cantidades de las líneas» y eso
   cuenta lo que no debe**: medido sobre la BD local, `SUM(order_items.quantity)` incluye
   **complementos**, los **portadores** de suplemento/descuento de fiesta mixta y las líneas
   **canceladas** — ocho pedidos divergen, y uno **íntegramente cancelado admitiría 2 autorizaciones**.
   ▶ El tope es la suma de las **líneas PRINCIPALES VIVAS** (`parent_item_id IS NULL AND cancelled_at
   IS NULL`) de un pedido **pagado**. *«No se puede autorizar a más gente de la que se ha comprado» no
   se traduce a un `SUM` ingenuo.*
4. **La firma HMAC caducada** cierra la ventana temporal (§4.6).

⚠️ **Lo que estas defensas NO cubren, dicho**: quien tenga el enlace puede escribir un nombre falso.
Es la misma exposición que un papel repartido en un colegio, y es la consecuencia asumida de §7·1. El
PDF **lo dice** (§4.13).

### 4.8 Idempotencia — «un niño, un papel», y por qué la clave es una columna normalizada

`[DECIDIDO owner]` §7·9: **un menor, un justificante**. El segundo envío para el mismo niño no escribe
nada y ve *«Ana ya tiene su justificante firmado»*.

⚠️⚠️ **La primera redacción ponía el `UNIQUE` sobre los nombres crudos y afirmaba que «Ana Perez» y
«Ana Pérez» serían dos filas. Es FALSO en MySQL**, y la revisión lo midió:

```sql
SELECT 'Perez'='Pérez' COLLATE utf8mb4_unicode_ci,  -- 1
       'ana'  ='Ana'   COLLATE utf8mb4_unicode_ci,  -- 1
       'Perez'='Pérez' COLLATE utf8mb4_bin;         -- 0
```

Todas las tablas del proyecto son `utf8mb4_unicode_ci`: el `UNIQUE` sería insensible a tildes y a
mayúsculas. **Y en SQLite —la suite— la comparación es byte a byte, o sea la conducta contraria.** Es
el precedente de `panel-navegacion.md` §7.3 invertido: *un test en SQLite no demuestra la conducta en
MySQL*.

▶ **La salida no es elegir motor: es no depender de ninguno.** La clave es una columna propia,
**`minor_key`**, calculada en PHP al escribir: nombre + apellidos, minúsculas, sin tildes, espacios
colapsados. `UNIQUE (order_id, minor_key)` es **determinista en los dos motores** y la guarda vale lo
mismo corriendo en SQLite que en MySQL.

⚠️ **`minor_born_on` NO entra en la clave, y es una elección con su coste**: si entrara, dos
progenitores que tecleen la fecha distinta crearían **dos justificantes del mismo niño** y la puerta lo
enseñaría dos veces —fallo **silencioso**—; fuera, dos niños que se llamen exactamente igual en el
mismo pedido chocan y el segundo padre ve un mensaje —fallo **ruidoso**, que se resuelve llamando al
parque—. *Entre un fallo mudo y uno que habla, se elige el que habla.*

### 4.9 Dónde nace la autorización

**Dentro de la transacción de la firma y bajo el lock del responsable** (§4.4), en un servicio propio
`GuardianAuthorizationSigner` (futuro) que: abre transacción → bloquea la fila del responsable →
busca-o-crea la autorización por `(order_id, minor_key)` → delega en el firmador.

Dos cosas que esto compra y que hacerlo fuera perdería:

1. **No hay carrera contra el `UNIQUE`**: dos envíos simultáneos del mismo menor se serializan en el
   lock, así que el segundo **encuentra** la fila en vez de estrellarse con un 500.
2. **No hay autorización huérfana**: si la firma no llega a escribirse, la autorización tampoco. PII
   de un menor sin prueba detrás es exactamente lo que la regla de `dependents` evita.

### 4.10 Lo que ve el RESPONSABLE (el que reservó)

`[DECIDIDO owner]` §7·4: **los nombres de los menores autorizados y quién falta**, para poder
perseguir a quien no ha firmado.

- **Ve**: nombre y apellidos del menor, y el estado. **NO ve**: correo, teléfono, relación ni ningún
  dato de contacto de los otros padres — impuesto por §4.5, no solo prometido aquí.
- **El contador no lleva denominador inventado**: dice *«3 justificantes · la reserva es de 100
  personas»*. **No se puede saber cuántos menores vienen**, y un «3 de 100» sería una cifra falsa con
  aspecto de dato.
- ⚠️⚠️ **El enlace NO se publica en el contexto de cuenta.** La primera redacción decía «reutiliza el
  mecanismo que `ViewOrder` ya tiene» — pero `ViewOrder` es el **panel**, y en el cliente hay una
  **prohibición vigente y guardada por test**: `AccountContextResource` dice *«PROHIBIDO publicar aquí
  la URL firmada […] sería una credencial portadora, sin sesión y válida durante días, y esta misma
  respuesta se siembra en el HTML de cada página con sesión»*. ▶ **El enlace se sirve bajo demanda**,
  por una acción explícita («Copiar enlace») que lo devuelve en su propia respuesta y nunca en el
  contexto sembrado. *El post-form pudo publicar la ruta sin firmar porque su lector tiene sesión;
  aquí los padres no la tienen, así que la firma es obligatoria y el sitio donde vive, no.*

⚠️ Esto es PII de menores de terceros en la pantalla de un cliente, y se acepta **a propósito**: es el
responsable del grupo. Queda escrito para que no se descubra dentro de seis meses como un hallazgo.

### 4.11 Lo que ve la PUERTA

`[DECIDIDO owner]` §7·3: **se señala y el operador decide**; no se bloquea el paso.

- Nombre y edad, junto a los menores a cargo. ⚠️ **Nunca los apellidos** (regla de `#236`, y es
  estructural: el DTO de puerta no tiene campo).
- Estado por `WaiverStatus::minorState()` y la regla de `#320`: **solo se rotula la excepción**.
- ⚠️ **La autorización cuelga del PEDIDO y la puerta pinta por RESERVA** (`GateReservation` lleva
  `orderCode`, no `order_id`): un pedido con tres líneas repetiría la misma lista tres veces. **Se
  agrupa por pedido en la composición, no al pintar** — decidirlo en el Blade es como nacen los N+1.
- ⚠️⚠️ **El presupuesto de consultas es una restricción de diseño, no una comprobación posterior**:
  techo **28**, ya cazó un N+1 en `#294`. Carga **en lote por pedido**, nunca por fila.

### 4.12 Lo que ve el PANEL

- **Ficha del pedido** (`ViewOrder`): las autorizaciones —menor, adulto, relación, fecha—, el contador
  y el botón de copiar enlace.
- **Reenvío del enlace** al titular, con la infraestructura `RESEND_TYPE_*`.
- **Hoja de sala** (`ReservationSlip`): la lista de menores autorizados. Sin datos de contacto.
- ⚠️ **Ni el operador ni el titular pueden CREAR una autorización.** Solo se crea firmando: un
  justificante creado por el parque no prueba nada.

### 4.13 El PDF

Del snapshot, nunca del CMS. Lleva el texto firmado íntegro, la identidad del **menor**, la del
**adulto firmante** con su relación, la del **responsable**, la referencia del pedido, fecha/hora con
zona, IP, user-agent, versión y hash.

⚠️⚠️ **Tiene que decir, con estas palabras, qué NO está verificado**: ni la identidad del firmante, ni
la del menor, ni —desde §7·8— necesariamente el correo del responsable. El subsistema ya lo hace con
los datos de un menor a cargo (*«Fingir lo contrario es peor que decirlo»*), y aquí es más grave: allí
lo declaraba alguien con cuenta y correo verificado; **aquí lo declara un desconocido**.

⚠️ **Solo lo alcanzan el operador con permiso y el propio firmante** (por el correo de §4.15). **El
responsable no**, por §4.5.

### 4.14 RGPD

| Pregunta | Respuesta | Por qué |
|---|---|---|
| ¿Sobrevive a `anonymize()` del que reservó? | **Sí**, autorización y firma | `RGPD-01` ya conserva `waiver_signatures` vinculado (art. 17.3.e + 18). Y **la prueba no es del tutor para que él la borre**. |
| ¿Y si el **padre** pide supresión? | Conservación restringida hasta que venza el plazo | Art. 17.3.e. Es la respuesta que el subsistema ya da. |
| ¿Qué plazo? | **`waiver.dependent_retention_months`**, el que YA existe | El sujeto es un menor: se cuenta desde sus 18. **No se crea ajuste nuevo.** ⚠️ Medido: hoy vale `NULL`, así que **no se poda nada** — sigue `[PENDIENTE: owner]`. |
| ¿Cómo se poda? | `prunable()` extiende su rama de menor a `guest_minor` sobre `subject_born_on`; al irse la última firma, la autorización huérfana se retira | Es lo que `Dependent::prunable()` ya hace. ⚠️⚠️ **Y hay que registrar el modelo en `routes/console.php`**, cuya lista de `model:prune` es **explícita** (`CookieConsentLog`, `WaiverSignature`, `Dependent`) y va **después** de `WaiverSignature` por el RESTRICT. *Un modelo `Prunable` que no entre ahí no se poda nunca.* |
| ¿Export del art. 20 del titular? | **No** | Son datos de terceros. Coherente con que el waiver tampoco entra. |
| ¿`no-store`? | Pantalla pública y PDF | `RGPD-04`. |
| ¿`audit_logs`? | `waiver.signed` con `subject_type: guest_minor`, **sin PII** | `RGPD-02`. ⚠️ **Pero `AuditLogger::log()` persiste IP y user-agent de la petición en curso**, que aquí son los **del padre desconocido**, en una fila cuyo target es el responsable — y `anonymize()` nulifica IP/UA por ACTOR, así que sobrevivirían a su supresión. Sin PII de nombre, así que es menor; **se usa el canal que no las persiste, o se dice**. |

### 4.15 Correo e idiomas

Al firmante, si deja correo, se le envía **el PDF de lo que acaba de firmar** (`[DECIDIDO owner]`
§7·7). No verifica ni gatea nada: es su copia. Sin correo, la pantalla de «hecho» ofrece la descarga.

Pantalla, correo y PDF en **es/en/fr**. El idioma en que se firmó **es parte de la prueba** y ya viaja
en la versión del documento.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| `RGPD-01` | **Se amplía**: `guardian_authorizations` sigue el régimen de su firma, igual que ya dice de `dependents`. El censo de `users` **no cambia** (no hay columna nueva en `users`) — verificado. |
| `RGPD-02` | Aplicado, **con el matiz de la IP del firmante** (§4.14). |
| `RGPD-03` | **Se amplía**: el enlace caduca y da 410 con el titular anonimizado, con la misma fuente de fecha. |
| `RGPD-04` | **Se amplía**: pantalla pública y PDF nuevos con `no-store`. |
| `SEC-06` | **Se amplía**: una superficie pública de escritura más, con Turnstile y límite por IP. |
| `SEC-04` | Aplicado: el `POST` re-comprueba caducidad, cierre y tope **en el momento**. |
| `PAY-*` / `AFORO-*` | **Ninguno.** Un justificante no vende ni ocupa plaza. |

⚠️⚠️ **PERO ESTO SÍ ENTRA EN EL `CRITICAL_RE` DEL HOOK, Y LA PRIMERA REDACCIÓN DECÍA LO CONTRARIO.**
`WaiverSigner` está en la lista (verificado en `.githooks/pre-push`) y **la T1 lo modifica**, así que
**todo push de esa tanda exige `VERIFY_CONC=1`** y los dos verificadores. *«No toca invariantes de
dinero» y «el hook no lo gatea» son cosas distintas, y confundirlas hace que alguien planifique un
push que el gate va a rechazar.*

⚠️ Ampliar una invariante es del owner (`CONVENCIONES` §9.1-2): las cinco van a `DECISIONES.md` al
aprobarse.

---

## 6. Plan de verificación empírica

Cada punto dice **con qué se demuestra**:

1. **La firma es una firma** — `waiver:verify-chain` verde sobre una cadena con autorizaciones, hash
   v4 verificado fila a fila. Mutación: cambiar un campo la pone en rojo.
2. **DOS padres, DOS cadenas** — el caso que hoy fallaría (§11·B1/B2): dos autorizaciones del mismo
   pedido → **2 firmas** y `chains == 2`. **Esta guarda se escribe ANTES del arreglo y se ve fallar.**
3. **La escalada de códigos**: 403 · 410 · 404 **en ese orden**, con el test que lo prueba
   invirtiéndolo.
4. **El cierre por fecha**: pasada la franja, `GET` abre y `POST` da 409. Los dos: un `POST` forjado
   desde una pestaña vieja es el caso real.
5. **Concurrencia sobre MySQL real, VISTA FALLAR sin el lock.** ⚠️⚠️ **El escenario de la primera
   redacción NO MUERDE**: N padres *distintos* son N cadenas de una fila, así que sin el lock no se
   bifurca nada y la guarda saldría verde igual — es literalmente la trampa que
   `VerifyWaiverChainConcurrency` documenta haber aprendido en `#197`. ▶ **El escenario que muerde es
   N envíos de la MISMA autorización** (mismo menor, mismo pedido) → **1 fila**. Y el criterio
   `chains === 2`, hoy clavado en el comando, se parametriza.
6. **Idempotencia**: doble envío del mismo menor → **una** autorización y **una** firma. Publicar
   versión nueva y reenviar → **dos** firmas encadenadas y **una** autorización.
7. **`minor_key` es determinista**: «Ana Pérez», «ana perez» y «ANA  PÉREZ» dan la misma clave — y el
   test vale igual en SQLite y en MySQL, que es su razón de ser.
8. **El tope**: sobre un pedido con complemento, portador y una línea cancelada, el tope cuenta **solo
   las principales vivas** (el caso que la primera redacción habría contado mal).
9. **Anti-bot**: `POST` sin token rechazado con claves puestas; no-op sin claves.
10. **Cero fuga, en las CUATRO superficies de §4.5**: con dos autorizaciones firmadas, `GET /me/waiver`
    no las lista, su PDF da 404, el partial del panel no las rotula como menor a cargo y el contador
    del audit no las suma. **Aserción sobre el cuerpo, no sobre la intención.**
11. **La pantalla pública** con tres autorizaciones ya firmadas **no contiene ni un byte** de ninguna.
12. **El presupuesto de la puerta no crece**: con 1 y con 6 menores invitados, el mismo número de
    consultas, bajo 28. Y un pedido con 3 líneas **no repite** la lista.
13. **RGPD**: `anonymize()` conserva autorización y firma y purga lo demás; la poda se lleva firma **y**
    fila huérfana; el export del art. 20 no las incluye; **el modelo está en la lista de
    `routes/console.php`** (si no, no se poda nunca).
14. **`PurgeCustomerData` sigue cerrando** con las dos FK RESTRICT nuevas, en el orden de §4.2.1. Sin
    esto, revienta el día del lanzamiento de un cliente.
15. **`subject_name` de 241 caracteres se guarda** (§4.3.1) — **medido en MySQL, no en SQLite**.
16. **En navegador** (`VERIFICACION-E2E-CAJON.md`, guion propio): el enlace real, en móvil, sin sesión,
    con el anti-bot **ENCENDIDO**, que es la única configuración que vale.

⚠️ **Cada guarda se muta con el fallo REAL que la motiva.** *Una guarda no se sabe si sirve hasta que
se muta.*

---

## 7. Decisiones del owner — `[DECIDIDO owner, 2026-09-01]`

Planteadas con el número y el coste de cada salida delante:

1. **El correo del firmante NO se verifica**, y la prueba lo dice. Coste asumido: la firma es más
   débil que una con correo verificado.
2. **UN enlace por reserva, hoja en blanco.** Coste asumido: no se corrige lo escrito, y hacen falta
   tope y anti-bot.
3. **En la puerta se señala y el operador decide.** No se firma en la tablet.
4. **El responsable ve los nombres y quién falta**, nunca los datos de contacto de los otros padres.
5. **Al firmante se le piden** nombre, apellidos, relación, teléfono y correo. **Sin DNI.**
6. **Del menor**: nombre, apellidos y fecha de nacimiento. **Sin alergias.**
7. **Se le manda copia por correo** si lo deja.
8. 🆕 **La rama `guest_minor` se SALTA la puerta de `email_verified_at` del responsable.** ⚠️ La
   razón, medida: `CustomerRegistrar` deja `email_verified_at` en `null` **a propósito** en el alta de
   mostrador sin correo, y un colegio que reserva por teléfono es exactamente eso — con la puerta
   puesta, **ningún padre podría firmar y el caso principal quedaría muerto** (en la BD local ya hay 1
   de 4 titulares con pedidos sin correo verificado). ⚠️⚠️ **Esto matiza el `[DECIDIDO owner,
   2026-08-26]`, no lo contradice**: aquél es sobre la firma del **propio titular**, cuyo buzón la
   firma copia. ▶ **Y corrige un razonamiento FALSO de la primera redacción**, que decía *«aquí no hay
   cuenta que verificar»*: sí la hay —la del responsable— y la firma **copia su correo dentro del
   hash**. El PDF dirá que ese buzón puede no estar verificado (§4.13).
9. 🆕 **Un niño, un papel.** El segundo progenitor del mismo menor ve *«ya tiene su justificante»* y no
   se escribe nada. Coste asumido: una errata del primero no la corrige el segundo; se llama al parque.

### Lo que sigue `[PENDIENTE: owner]`

- **El plazo de conservación** (`waiver.retention_months` y `waiver.dependent_retention_months`). Ya
  estaba pendiente para todo el waiver; esta spec **no crea uno nuevo**, pero **medido: hoy los dos
  valen `NULL`, así que no se poda absolutamente nada** — y aquí lo que no se poda son datos de
  menores de terceros.
- **El ✅ a esta spec** y su entrada en `DECISIONES.md`.

---

## 8. Plan de ejecución — cuatro tandas

Cada una termina con la suite verde, Pint, `docs-check` y sus mutaciones. ⚠️ **La T1 toca
`WaiverSigner`, que está en el `CRITICAL_RE`: `VERIFY_CONC=1` obligatorio** (§5).

| Tanda | Qué entra | Por qué esta frontera |
|---|---|---|
| **T1 · El dominio** | Migración (`guardian_authorizations` + las 5 columnas + `subject_name` a 255) · `GuardianAuthorization` · `WaiverSignature::chainKey()` + canónico **v4** · **`WaiverSigner` MODIFICADO** (clave de sujeto + la puerta de correo de §7·8) · **`WaiverChain` MODIFICADO** · **`VerifyWaiverChainConcurrency` MODIFICADO** · `GuardianAuthorizationSigner` · `prunable()` + `routes/console.php` · **`PurgeCustomerData`** · **el acotado de las cuatro lecturas de §4.5** | La prueba primero. **Y el acotado va AQUÍ, no en la T3**: en cuanto exista una firma `guest_minor`, las lecturas de hoy ya filtran mal — la fuga nacería con el dominio. |
| **T2 · La pantalla pública** | Ruta `/autorizacion/{order}`, controlador, `<x-focused-layout>`, Turnstile, límites, tope, cierre por fecha, i18n | Es la superficie con riesgo. Entra sola para poder mutarla sola. |
| **T3 · Las superficies de dentro** | Zona de cuenta (con la acción de enlace bajo demanda, §4.10) · `ViewOrder` · hoja de sala · puerta (con su presupuesto) · PDF · correo de copia | Todo lectura salvo el correo. |
| **T4 · Verificación** | Guion de navegador con anti-bot encendido · verificador de concurrencia con el escenario que MUERDE (§6·5) · el OJO del owner | Lo que separa 🟦 de ✅. |

⚠️ **La T1 no se salta.** La tentación es empezar por la pantalla, que es lo que se ve — y entonces el
modelo de la prueba lo acaba decidiendo un formulario.

### 8.1 T1 · EJECUTADA (2026-09-01, `DECISIONES #328`)

**En el árbol y con la suite verde (3.823 / 24.730).** Lo que entró, y las tres cosas que la ejecución
enseñó y el diseño no había previsto.

| Pieza | Dónde |
|---|---|
| Migración: `guardian_authorizations` + 5 columnas + `subject_name` 120 → **255** | `database/migrations/2026_09_01_235500_create_guardian_authorizations.php` |
| La entidad, con `keyFor()`, la guarda de borrado y `prunable()` | `app/Domain/Identity/Models/GuardianAuthorization.php` |
| **La clave de cadena en UN sitio** (`chainKey()` + `scopeInChain()`), `SUBJECT_GUEST_MINOR`, canónico **v4**, la poda | `app/Domain/Identity/Models/WaiverSignature.php` |
| La rama del sujeto nuevo y la puerta de correo de §7·8 | `app/Domain/Identity/Services/WaiverSigner.php` |
| La puerta ÚNICA: transacción + lock + busca-o-crea + firma | `app/Domain/Identity/Services/GuardianAuthorizationSigner.php` |
| El acotado de §4.5 (fail-closed) y `guardianSignatures()` | `app/Domain/Identity/Models/User.php` |
| La condición propia del PDF | `app/Http/Controllers/Api/V1/MeWaiverController.php` |
| Los dos verificadores y la purga | `WaiverChain` · `VerifyWaiverChainConcurrency` (**`--scenario=holder|guest`**) · `PurgeCustomerData` · `routes/console.php` |

▶ **Lo que la ejecución añadió al plan:**

1. ⚠️ **Todo modelo Eloquent necesita alias de morfo** y lo exige `MorphMapTest`: `guardian_authorization`
   en `AppServiceProvider`. No estaba en la spec y la suite lo puso rojo — la guarda hizo su trabajo.
2. ⚠️ **Dos tests hermanos fijaban `canonical_version` como literal** (`WaiverSignatureChainTest`,
   `MeDependentWaiverTest`). Se actualizan a 4 y **el literal se conserva a propósito**: subir la
   versión canónica tiene que traer a alguien a confirmarlo. `test_the_canonical_serialisation_is_fixed_per_version`
   gana el bloque de v4 con un justificante REAL, y una aserción de que **ninguna versión serializa
   igual que otra** — que es lo que garantiza que subirla no invalide lo firmado.
3. ⚠️ **La FK `subject_id → dependents` SOBREVIVE al `change()` de `subject_name`**, comprobado en
   MySQL tras migrar (era el riesgo de recrear la tabla). Y **las 24 firmas reales de la BD local
   siguen verificando** con el canónico en v4.

▶ **Verificación** (§6, lo que la T1 podía cerrar):

- **14 de 14 mutaciones muerden**, con **CONTROL verde** en los tres ficheros de guarda. El arnés mide
  por **código de salida**, no buscando texto en la salida (la regla de `#317`).
- **`waiver:verify-chain` sobre MySQL real, en sus DOS escenarios y VISTO FALLAR sin el lock**:
  `holder` bifurca la cadena (2 filas, `prev_hash` repetido, `exit=1`); `guest` estrella **7 de 8**
  procesos contra el `UNIQUE` con `1062 Duplicate entry` — que es exactamente el 500 que §4.9 predijo.
- **El CONTRATO es la segunda guarda de la fuga, verificado**: con `User::waiverSignatures()` sin
  acotar, Spectator rechaza la respuesta de `GET /me/waiver` con *«The data should match one item from
  enum»*. Por eso esos casos viven en `tests/Feature/Api/V1/MeWaiverGuestMinorTest.php`, que hereda de
  `ApiTestCase`. ⚠️ **La primera versión heredaba de `Tests\TestCase` y no validaba nada**: heredar de
  la clase equivocada convierte un test de contrato en un test de texto.

▶ **Cuatro trampas pagadas, todas con su lección:**

1. **Pint convirtió un `{@see}` en `use`** y metió Modelo → Servicio hacia la clase que ya depende del
   modelo. Es la trampa de `#320` otra vez: **reescribir la cita en prosa NO basta, hay que quitar el
   import**.
2. **Un `assertDontSee` sobre `/admin/users/{id}` pasaba en VACÍO**: el registro probatorio vive en un
   **modal** de Filament y esa página no lo pinta nunca (el escalón de `#161`). Re-apuntado a renderizar
   el partial, **con control** de que sí pinta la firma propia.
3. **`Sanctum::actingAs()` deja una instancia OBSOLETA** en el contenedor: tras firmar, `signed` salía
   `false` **con el producto sano**. El test mentía, no el código.
4. **`echo "exit=$?"` después de una tubería mide el código de `tail`**, no el del comando. El
   veredicto bueno salió corriéndolo sin tubería — *el instrumento es el primer sospechoso, también
   cuando el instrumento es una línea de shell*.

▶ **Lo que la T1 NO cierra y queda para la T2/T3**: la pantalla pública, el anti-abuso (Turnstile,
límite por IP, tope), el cierre por fecha, las superficies del responsable, la puerta, el PDF y el
correo de copia. El **tope de §4.7·3** todavía no tiene código: nace con el controlador.

### 8.2 T2 · EJECUTADA (2026-09-01, `DECISIONES #335`)

**La pantalla pública, en el árbol y verificada en navegador real.**

| Pieza | Dónde |
|---|---|
| Contrato: un pedido visto por quien tiene el enlace y **no tiene cuenta** | `Booking\Contracts\AuthorizableOrder(s)` + `Booking\Services\AuthorizableOrdersReader` |
| Las TRES puertas, **dentro de la transacción y bajo el lock** | `Identity\Services\GuardianAuthorizationSigner` + `GuardianAuthorizationRefusedException` |
| La escalada **403 → 410 → 404** y su orden | `Http\Concerns\AuthorizesGuardianAuthorization` |
| Rutas, validación, anti-abuso y desenlaces | `Http\Controllers\GuardianAuthorizationController` · `routes/web.php` |
| La hoja: `<x-focused-layout>`, el texto legal en el flujo, la casilla separada | `resources/views/reservation/authorization.blade.php` |
| Rótulos en es/en/fr | `lang/{es,en,fr}/guardian.php` |
| La fuente ÚNICA del enlace | `Order::guardianAuthorizationSignedUrl()` |

#### ❗❗ El defecto REAL de esta tanda: el anti-bot MENTÍA a las personas

Copié el patrón de `/contacto`: token de Turnstile ausente o inválido → se descarta en silencio y se
responde como si todo hubiera ido bien. **La sonda de navegador lo destapó**: el widget no llega a
producir token (extensión, red, JS caído) y la pantalla decía *«Listo: la autorización ha quedado
registrada»* con **cero filas escritas**.

▶ *En un formulario de contacto eso cuesta un mensaje. Aquí un padre cree tener firmada la
autorización de su hijo y se entera **en la puerta del parque**.* Es la misma forma del bloqueante
que la revisión encontró en la idempotencia (§11·B1): **fallar en silencio anunciando éxito**.

**La asimetría que queda es deliberada y hay caso propio que la fija:**

| | Qué hace | Por qué |
|---|---|---|
| **Honeypot** | calla y no escribe | un campo invisible relleno es señal de bot **y de nada más**: el silencio no engaña a ninguna persona |
| **Turnstile** | **lo dice** | falla también a personas, y aquí el precio de callar es una prueba legal que su firmante cree tener |

⚠️ Y el honeypot **no se llama `website`** (como en `/contacto`): ese nombre mapea al tipo de
autocompletado `url` del navegador y un gestor de contraseñas puede rellenárselo a alguien real.

#### Lo que la ejecución añadió al plan

1. **Un pedido con SEÑAL sigue siendo `paid`** — medido ANTES de escribir la puerta (30,00 € cobrados
   de 88,00 € de valor, estado `paid`). Exigir «pagado» **no** deja fuera a las excursiones, que es el
   caso de uso; era el agujero que se temía y no existe.
2. **`Order` se resuelve por `code`, no por id** (`getRouteKeyName()`), así que la URL pública queda
   `/autorizacion/PROBE-AB12CD`.
3. **`select` no estaba estilado** en `.eventfields`. Se amplía con los MISMOS tokens; medido antes:
   **cero `<select>` en vistas públicas**, o sea puramente aditivo.
4. **Se rechaza una fecha de nacimiento de ADULTO** con frase propia. Un adulto firma por sí mismo, y
   el criterio es el que `WaiverSigner` ya aplica a un menor a cargo: la edad de HOY.
5. **El deber de información del art. 13**: quien rellena es un tercero que no ha aceptado nada antes
   y entrega datos de un menor. La política se **enlaza**, con las dos interpolaciones por `e()`.

#### Verificación

- **14 de 14 mutaciones muerden**, con **control por mutación**.
- **Navegador real a 390×844**: texto legal presente, casilla **desmarcada** por defecto, `select`
  idéntico a los inputs, mensaje con el nombre del menor y **«un niño, un papel» funcionando** (el
  segundo adulto ve *«Ana Gómez Ruiz ya tiene su autorización firmada»*). Sin errores de JS ni de red.
- BD de desarrollo devuelta a su estado exacto tras las sondas.

#### Cuatro trampas de instrumento, todas propias

1. ⚠️⚠️ **El arnés dijo «14 de 14» siendo 13.** Su `git checkout -- .` se llevó un caso **sin
   commitear** (la regla de `#181`, otra vez) y el filtro dejó de casar con ningún test: PHPUnit salió
   con código ≠ 0 y eso se contó como mordisco. ▶ **Ahora el arnés exige VERDE antes de mutar**.
   *«Rojo después» no significa nada si no se ha visto verde antes.*
2. **La primera captura salió sin una sola letra.** Era la captura: tomada al `domcontentloaded`,
   antes de que cargaran las fuentes. El CONTROL —la landing en el mismo navegador— lo zanjó.
3. **`waitUntil: 'networkidle'` nunca llega** en el contenedor: la página pide fuentes externas que no
   alcanza.
4. **`Setting::value()` memoiza la tabla entera**: al restaurar las claves de Turnstile en el mismo
   proceso, el script informó «VACÍA» con las claves ya escritas.

⚠️ **Lo que la T2 NO cierra**: quien firma **todavía no recibe copia** —§4.15 la promete y su PDF es
de la T3—, y el responsable aún no tiene por dónde repartir el enlace fuera de generarlo a mano.

### 8.3 T3 · EJECUTADA (2026-09-01, `DECISIONES #337`)

**Las seis superficies, en el árbol.** La decisión que ordena la tanda: **`GuardianRoster` tiene DOS
formas y no una con un filtro** —`forOperator()` y `forResponsible()`—, así que la regla de §7·4 la
impone el TIPO y no la disciplina de cada plantilla. Ninguna de las dos lleva el correo ni el teléfono
de ningún adulto.

| Superficie | Dónde | Lo que decidió |
|---|---|---|
| Puerta | `GateProfile` + `GateProfileData.guestMinors` + su sección en `validar.blade.php` | **Sección APARTE** de los menores a cargo, y **al nivel de la ficha**: la autorización cuelga del PEDIDO, así que dentro de cada fila se repetiría. **Dos consultas**, sean uno o veinte |
| Hoja de sala | `Admin\ReservationSlipController` + `pdf.reservation-slip` | Se compone en el CONTROLADOR: `ReservationSlip` vive en Booking, que **no puede mirar a Identity** |
| Ficha del pedido | `OrderInfolist::guestMinorsSection()` + `partials/guest-minors` + `copyGuardianLinkAction` | `visible()` y no un `@if` dentro: en un pedido normal la sección **no existe** |
| PDF probatorio | `WaiverProof` + `pdf.waiver-proof` | Las TRES personas, y **nota propia**: aquí lo declara un DESCONOCIDO |
| Correo de copia | `GuardianAuthorizationSigned` | **PDF ADJUNTO, no enlazado** —un enlace sería una ruta pública a la prueba de un tercero—, **fuera de la transacción** y solo con buzón |
| Cuenta del responsable | `GET /orders/{code}/guest-minors` + `GuestMinorsPanel.vue` + el store | Ruta propia por las razones de `event-data` **y una tercera**: el enlace es una credencial portadora |

#### ❗❗ Tres guardas de arquitectura cazaron tres defectos míos

1. **Un componente no habla con la API** (`CE-6`) — regla que yo mismo cité en el docblock de al lado.
2. **Todo modificador que el cajón emite tiene que tener regla CSS** (`#253`): mis clases nacieron sin
   ninguna y eso **no falla, se pinta desnudo**.
3. **La lista exacta de claves del montaje**: crecer ahí sin pintar es pagar bytes en cada página.

#### Los dos presupuestos, podados ANTES de subirlos

Chunk **262 → 263 KiB** (+1,45 medido, −0,46 de poda) · textos del montaje **9.200 → 9.400 B** (+240,
−110 de poda). ⚠️⚠️ **Y se dice lo que cuesta mal**: los textos los paga cada página con sesión para
una feature que aparece en poquísimos pedidos; la salida, si hay que recuperarlos, es mandarlos en la
respuesta del endpoint —que ya se pide bajo demanda—, a cambio de sacarlos del alcance de
`SidebarTranslationKeysExistTest`.

#### Dos afirmaciones de esta spec, corregidas al medirlas

- ⚠️ **§4.11 decía que `GateReservation` «lleva `orderCode`, no `order_id`»: es FALSO**, lleva los dos.
  No hizo falta tocar el contrato.
- ⚠️ La edad sale de `Dependent::ageBetween()`, no de un `diffInYears()` propio: aquél devuelve un
  **float** —el DTO declara `int`— y trata el caso de una fecha posterior al día.

⚠️⚠️ **Y una frontera respetada a mano**: un `DB::table('orders')` dentro de Identity habría
funcionado y `ModuleBoundariesTest` **no lo habría visto** (escanea clases, no cadenas SQL). Va por
`Booking\Contracts\AuthorizableOrders`.

⚠️ **Lo que la T3 NO cierra**: la T4 —el guion de navegador con el anti-bot encendido y el OJO del
owner— y el plazo de conservación, que sigue `[PENDIENTE: owner]`.

---

## 9. Lo que NO se cierra, y va a `DEUDA.md`

1. **`declared_by_user_id` dentro del hash con `ON DELETE SET NULL`** (§10). Preexistente, dos salidas,
   es del owner.
2. **`subject_name` de 120 con corte a 255** (§4.3.1). Vivo hoy para menores a cargo; esta spec lo
   arregla en su migración, pero la ficha queda por el caso existente.
3. **La IP y el user-agent del padre bajo el target del responsable** en `audit_logs` (§4.14).

---

## 10. Hallazgo colateral, MEDIDO DOS VECES — un `SET NULL` dentro del hash

`waiver_signatures.declared_by_user_id` es `nullOnDelete` **y está dentro de `HASHED_FIELDS`** en las
tres versiones canónicas. Sonda, en transacción con `rollBack`, reproducida **de forma independiente
por el revisor**:

```
declared_by_user_id = 1305 · verifyHash() ANTES: true
declared_by_user_id DESPUES = NULL · verifyHash() DESPUES: false
```

**Borrar la fila del operador rompe la verificación de una firma que nadie tocó.** La vía real es
`PurgeCustomerData` (su `User::whereNotIn('id', $keptIds)->delete()` final), que borra usuarios después
de borrar las firmas *de los usuarios purgados* — una firma de un usuario **conservado**, declarada por
un operador **no conservado**, queda con el hash roto y en silencio.

- **Alcance**: estrecho (solo la limpieza de go-live, solo firmas declaradas en mostrador). **No es
  bloqueante y no es de esta spec.** Ficha en `DEUDA.md`.
- **La regla de diseño que sale de ella está aplicada en §4.3**: *ninguna columna con `ON DELETE SET
  NULL` entra en un hash que se verifica* — por eso no existe `signer_user_id`.
- ▶ *Lección de método: el diseño de una feature nueva es cuando más barato sale encontrar esto,
  porque estás leyendo el mecanismo con la pregunta correcta en la mano.*

---

## 11. Revisión adversarial — 2026-09-01

> Hecha por un segundo agente sobre el diseño, con el método de `CONVENCIONES` §5: **no leer la spec,
> intentar refutar cada afirmación suya ejecutando contra el código**. 19 hallazgos. Lo que no aparece
> aquí se verificó y era cierto — y está en §11.4 para que nadie lo vuelva a medir.

### 11.1 Los DOS bloqueantes, y la afirmación mía que era falsa

**B1 · La idempotencia de `WaiverSigner` se CRUZA entre autorizaciones.** El lector de la firma
anterior usa `where('subject_id', $request->subjectId)`; con `null`, Laravel genera `is null`
(verificado: `select … where user_id = ? and subject_type = ? and subject_id is null`), así que **la
clave es la misma para todas las autorizaciones del responsable**. Medido: dos firmas de dos menores
distintos → `firma1 = firma2 = 318`, **una sola fila**. ▶ **El padre B envía, ve la pantalla de
«hecho», recibe su correo… y no existe justificante de su hijo.** No falla, no avisa.

**B2 · `WaiverChain` mete a todos los menores invitados en UNA cadena.** La clave es
`subject_type.':'.($subject_id ?? '')` → `guest_minor:` para todos. Medido con dos firmas sanas:
`count=2 · chains=1 · ok=false`, con el problema *«prev_hash no enlaza con la firma anterior de su
sujeto (guest_minor:)»*. ▶ **El verificador declararía ROTA una cadena perfectamente sana**, y el
mismo defecto está duplicado en `VerifyWaiverChainConcurrency`, que además lleva `chains === 2`
clavado.

**Y por eso `no cambia una línea del mecanismo existente` era FALSO.** Lo escribí en §4.1 y en §4.4 de
la primera redacción. *Una spec que promete que no toca nada es exactamente la que nadie audita.*

### 11.2 Lo demás que cambió el diseño

| # | Hallazgo | Dónde se corrigió |
|---|---|---|
| A3 | `GET /me/waiver`, su PDF, el partial del panel y el contador del audit **listan las firmas del responsable sin filtrar sujeto** → el nombre del hijo de otro, su fecha de nacimiento y —con el PDF— los datos del otro padre | **§4.5, sección nueva**, y sube a la T1 |
| A4 | `openapi/v1.yaml` no aparecía en toda la spec; su `enum` no admite el sujeto nuevo y Spectator valida las respuestas | §4.5, última fila |
| A5 | `WaiverSigner` exige correo verificado **del responsable**: un colegio que reserva por teléfono no tiene ninguno → nadie podría firmar. Y mi razonamiento («aquí no hay cuenta que verificar») era falso | **§7·8**, decisión nueva del owner |
| A6 | `order_id` RESTRICT **rompe `PurgeCustomerData`**, y CASCADE tampoco lo arregla | **§4.2.1**, con los tres pasos |
| A7 | El enlace firmado en la zona de cuenta **choca con una prohibición vigente y guardada por test** | §4.10 |
| M8 | `subject_name` es `varchar(120)` y el firmador corta a 255 → `1406` en MySQL, verde en SQLite | §4.3.1 + `DEUDA` |
| M9 | Decía «no entra en el `CRITICAL_RE`»; `WaiverSigner` **está** en la lista y la T1 lo modifica | §5 |
| M10 | El escenario de concurrencia **no muerde** con cadenas por autorización | §6·5 |
| M11 | El `UNIQUE` sobre nombres crudos es insensible a tildes en MySQL y sensible en SQLite; y faltaba el caso de dos progenitores | §4.8 (`minor_key`) + §7·9 |
| M12 | El tope por `SUM(quantity)` cuenta complementos, portadores y líneas canceladas | §4.7·3 |
| M13 | Un `Prunable` que no entre en la lista de `routes/console.php` **no se poda nunca** | §4.14 |
| B14 | «410 antes de comprobar cualquier otra cosa» invertía la propiedad de seguridad que la propia spec citaba | §4.6 |
| B15 | «Seis consumidores» de `dependents` son ~14 | §1.3 |
| B16 | La autorización cuelga del pedido y la puerta pinta por reserva → lista repetida | §4.11 |
| B17 | El audit guardaría IP/UA del padre bajo el target del responsable | §4.14 + `DEUDA` |
| B18 | `/reserva/{order}` usa «reserva» para un PEDIDO | §4.6 (`/autorizacion/{order}`) |
| B19 | No se decía dónde nace la autorización respecto del lock | §4.9, sección nueva |

### 11.3 Veredicto de la revisión

> *«El diseño se sostiene en su decisión central y hay que rehacer una parte concreta.»*

La opción B, el responsable en `user_id`, el canónico v4 y el régimen RGPD **aguantan y no se
replantean**. Lo que se rehízo: §4.1/§4.4 (el mecanismo sí cambia), §4.5 (nueva), §4.2.1
(`PurgeCustomerData`), §7·8 y §4.10 (dos decisiones que faltaban) y §4.8 (idempotencia).

### 11.4 Lo que aguantó — para no volver a medirlo

`user_id` NOT NULL + RESTRICT · la FK de `subject_id` **rechaza de verdad** (`1452`) y la suite la
ejerce igual (`PRAGMA foreign_keys = 1`) · `guestFormStatus()` es `null` fuera de packs · el enlace del
post-form es uno por reserva y enseña a todos · **subir el canónico no invalida nada: 24 firmas reales,
21 en v2 y 3 en v3, todas verifican** · la caducidad +14 d · la escalada 403→410→404 y su orden ·
Turnstile existe y es no-op sin claves · el techo 28 de la puerta · el plazo del menor desde los 18
sobre `subject_born_on` y `RGPD-01` conservando las firmas · `RELATIONSHIPS` cabe en `string(16)` ·
`dependent_assignments` es CASCADE · **`orders.user_id` es NOT NULL: siempre hay responsable** ·
Identity referencia el pedido por id entero · **`WaiverStatus::for()` y `forDependents()` SÍ filtran
por sujeto: están limpias** · no toca `PAY-*`/`AFORO-*` · el censo de `users` no se entera.

### 11.5 Estado

Revisada, corregida y con **la T1 ejecutada** (§8.1, `DECISIONES #328`): los dos bloqueantes y la
fuga están cerrados **y verificados por mutación**. **Pendiente del ✅ del owner** y de su decisión
sobre el plazo de conservación —medido: hoy vale `NULL` y no se poda nada—.
