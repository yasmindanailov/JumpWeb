# [SPEC] El justificante de un menor invitado a una reserva («waiver offshore»)

> Estado: 🟦 **T1 + T2 + T3 EN EL ÁRBOL** (2026-09-01, `DECISIONES #328`, `#335` y `#337`) — el
> dominio (§8.1), la pantalla pública (§8.2) y las seis superficies (§8.3). **Queda la T4**: el guion
> de navegador con el anti-bot encendido y el OJO del owner.
>
> ❗❗❗ **Y desde el 2026-09-01 por la noche, §12: LA ACTIVACIÓN — T5 → T8 EN EL ÁRBOL** (`#400`).
> El owner probó lo construido y encontró que **no había puerta por la que entrar**: *«En el panel del
> cliente no me sale nada del enlace. Ni de los que han firmado o no.»* §12.1 tiene los tres defectos
> medidos —el peor, un huevo-y-gallina: el botón del enlace vivía DENTRO de una sección que solo
> aparecía cuando ya había un justificante firmado—. **Si vas a tocar esta feature, EMPIEZA POR §12.**
> Carril **P3** de `ESTADO.md` — **con el alcance ampliado por el owner**: ver §1.2.
> Subsistema padre: `docs/specs/waiver-probatorio.md`. Entidad hermana: `docs/specs/menores-a-cargo.md`.
>
> ❗❗❗ **EMPIEZA POR §11 SI YA HABÍAS LEÍDO ESTA SPEC**: la revisión adversarial encontró **dos
> BLOQUEANTES** y una afirmación central del diseño que era **FALSA** —«no cambia una línea del
> mecanismo existente»—. Cambia, y justo donde más duele. Todo está corregido en el cuerpo; §11 es el
> registro de qué se creyó y qué se midió.

---

## §0 · Antes de tocar

- **`WaiverSigner` está en el `CRITICAL_RE`** → `VERIFY_CONC=1` y `waiver:verify-chain`. Todo en el árbol; queda el
  OJO del owner (`VERIFICACION-E2E-CAJON.md` §5.septies). Piel nueva por `#745` (T3 de `fiesta-sistema-nuevo.md`:
  el adulto en UNA casilla, teléfono obligatorio; fecha y relación se quedan).
- **EMPIEZA POR §13 (`#401`): el justificante cuelga de la RESERVA, no del pedido.** Lo cazó el owner con un
  pedido de dos visitas: de esa raíz salían cuatro síntomas. `guardian_authorizations.order_item_id`, ruta
  `/autorizacion/{reservation}`, un correo por reserva marcada, enlace en «Mis reservas». **Plazas libres =
  cantidad − menores a cargo asignados − justificantes firmados** (`Identity\Services\GuardianPlaces`, porque
  `Booking` no mira a `Identity`); los adultos no se restan. Una reserva cancelada sale de la ficha.
- **Después §12 (`#400`), la activación**: la decide el PRODUCTO (`ticket_types.guardian_authorization`:
  `none` · `optional` · `required`) y el hecho vive en `order_items.guardian_authorization`; `required` lo marca
  el SERVIDOR; «se ofrece» no es «se permite» (con el enlace de un pedido pagado se firma siempre).
  `Cart::sanitize()` y `cartToOrderCart()` son LISTAS BLANCAS y el campo se caía ahí en silencio; al fundir dos
  líneas la marca es un O. ⛔ El «caso 3» sin reserva no se construye; no hay enlace «inicia sesión y vuelve».
- **§11 (adversarial)**: la clave de sujeto vive en UN sitio (`WaiverSignature::chainKey()`, con `NULL` se
  cruzaban tres); §4.5 evita la FUGA (el `user_id` es el responsable y sus firmas de menores invitados salían
  en «las firmas de este usuario»). `signer_user_id` no existe a propósito (un `SET NULL` dentro de un hash);
  `minor_key` normalizada en PHP; el tope NO es `SUM(quantity)`; `Order` se resuelve por `code`.
- **Anti-bot**: el HONEYPOT calla y TURNSTILE lo dice (falla también a personas); el honeypot no se llama
  `website`. Nueve `[DECIDIDO owner]` en §7. Anexo al final con la fila del enrutador.

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
- ▶ **Desde la T6·4 de la invitación digital esta lista NO son solo los firmados**
  (`specs/celebracion-e-invitacion.md` §4.8 y §10.11, `#711`): son los niños de la fiesta —fichas con
  nombre **+** «sí» sin apuntar— y cada uno trae su **estado de entrada** (firmado · viene con un
  adulto · sin resolver), más la cuenta «8 de 12 con justificante». Una firma que no empareja con
  ninguno **sigue saliendo**, que es lo que esta sección describía. El presupuesto no se movió: las
  fichas viajan en `GateReservation` (coste cero) y las respuestas se piden por lotes **solo si hoy hay
  una fiesta con invitación**.

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

#### ⚠️⚠️ La auditoría del reloj cazó DIEZ rojos de esta tanda, y no los veía nadie

`GuestMinorSurfacesTest` siembra una franja de **HOY** —la puerta solo enseña las reservas del día—
que termina a las 23:00. **Cerca de medianoche esa visita ya ha pasado**, el dominio se niega a
autorizar sobre una visita terminada, y **diez de los once casos se ponen rojos**. Medido: verdes a
cualquier hora normal, rojos a las **21:59:30 de Madrid** y a las **23:59:30 UTC**.

▶ Arreglado congelando el reloj con una constante documentada (`FROZEN_NOW = '2026-06-15 09:00:00'`,
mediodía en Madrid: catorce horas de margen y el mismo día natural en las dos zonas), que es
literalmente lo que `TESTING.md` §2 manda. **Verificado con CONTROL**: sin el congelado, 10 de 11
rojos a las 23:59:30; con él, los 11 verdes en las dos fronteras.

▶ **Y un ONCEAVO rojo que la auditoría destapó de rebote y NO era del reloj**:
`GuestMinorIsolationTest` asevera que la cadena `'Carlos'` **no** está en el HTML del panel — contra
un HTML que lleva el nombre que pone `User::factory()`. En uno de los diez pases la factoría generó
un nombre **con «Carlos» dentro**. *Un nombre aleatorio enfrentado a una aserción por SUBCADENA es
una moneda al aire disfrazada de test*, y solo se ve corriendo la suite muchas veces. Nombre fijado
y aserción por nombre COMPLETO.

⚠️⚠️ **Y DOS trampas de instrumento que casi lo entierran todo**: la auditoría se lanzó con
`| tail -8`, que **cortó justo la tabla de fronteras** y dejó a la vista solo dos filas en verde bajo
un veredicto ✗ —y **el `exit 0` que se leyó era el de `tail`**, no el de la auditoría—; y la segunda
pasada se lanzó **antes de un rebase que ocurrió a mitad**, así que sus pases vieron árboles
distintos y hubo que tirarla. *Un filtro de salida puede esconder exactamente la evidencia que se
busca, un código de salida detrás de una tubería no es el del comando que importa, y una medición
larga no vale si el sujeto cambia mientras se mide.*

✅ **La tercera pasada, sobre el árbol estable, salió verde en las diez fronteras** (`EXIT=0` de la
auditoría, ya no el de un `tail`).

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

---

## 12. LA ACTIVACIÓN — cómo entra esto en la vida del parque (2026-09-01, encargo del owner)

> Las T1–T3 construyeron el mecanismo y **ninguna construyó la puerta por la que se entra**. El owner
> lo encontró probándolo: *«En el panel del cliente no me sale nada del enlace. Ni de los que han
> firmado o no.»* Esta sección es el diseño de la activación, con lo que se midió al diagnosticarlo.

### 12.1 Los TRES defectos medidos, y solo uno es una decisión de diseño

**(a) El huevo y la gallina del panel — es un DEFECTO, no una elección.**
`OrderInfolist::guestMinorsSection()` es `->visible(countFor(...) > 0)`, y **el botón «Copiar enlace
para los padres» vive DENTRO de esa sección**. O sea: el enlace solo aparece cuando ya hay un
justificante firmado, y para que haya uno hace falta el enlace. **En un pedido nuevo el operador no
tiene por dónde empezar.**

▶ El comentario de esa línea dice *«en un pedido normal —que son casi todos— esta sección no existe,
en vez de existir vacía»*, y el razonamiento era bueno para una sección de LECTURA. Lo que no vio es
que dentro había la única ACCIÓN del subsistema. *Una condición de visibilidad escrita para lo que se
lee acaba escondiendo lo que se hace.*

**(b) En la cuenta del cliente, el enlace se apaga con la visita — y eso está BIEN.** Medido sobre el
pedido real del owner: `R-TMOP6H · paid · visitFinished=SÍ → link=NULL · roster=[]`, así que el panel
no se pinta. Repartir un enlace que va a decir «cerrado» sería peor. Lo que falta no es el enlace: es
que **nada en toda la web dice que esta feature existe**.

**(c) Y está enterrado.** `<GuestMinorsPanel v-if="open && row.code">` vive **dentro de «Ver el
desglose»** de «Mis pedidos». Sin desplegar el pedido, no existe.

⚠️ **El endpoint está sano y se comprobó antes de acusar a nadie** —`GET /api/v1/orders/PRUEBA-WAIVER/guest-minors`
devuelve el menor, la capacidad y el enlace firmado—: el defecto está en quién lo pinta y cuándo, no
en el dominio.

### 12.2 El diseño del owner: el justificante es una propiedad del PRODUCTO

`[DECIDIDO owner, 2026-09-01]`, y es mejor que lo que este agente proponía porque es **data-driven**,
que es el principio nº 1 del proyecto:

> *«Un producto puedes añadir justificante OPCIONAL […] O puedes añadir justificante OBLIGATORIO como
> el caso de las excursiones de colegio. O no puedes añadir justificante y no sale nada.»*

| Estado de `ticket_types.guardian_authorization` | Qué pasa en el embudo | Para qué es |
|---|---|---|
| `none` *(defecto)* | **nada**: ni casilla, ni aviso, ni correo | la entrada normal, que son casi todas |
| `optional` | una **casilla** junto al selector de menores a cargo: *«viene un menor que no está a mi cargo»* | el amigo del hijo — el caso que originó la feature |
| `required` | **sin casilla**: una nota que dice que hará falta, y el correo sale siempre | la excursión de colegio |

Y el hecho se guarda en la línea: **`order_items.guardian_authorization`** (booleano). Es un HECHO
como `age_family_seal` —lo que se acordó al comprar—, no una consulta al catálogo, y por eso
**cambiar el producto después no reescribe lo que el cliente marcó**.

⚠️ **Un producto `required` marca la línea SIEMPRE**, aunque el cliente no pida nada: la marca la
pone el servidor al crear el pedido, no el navegador. Lo contrario haría que un cliente pudiera
comprar una excursión sin justificantes quitando una casilla del DOM.

⚠️⚠️ **El enlace SIGUE FUNCIONANDO en cualquier pedido pagado, marcado o no, y es deliberado.** La
marca gobierna a quién se le **OFRECE**, nunca quién puede firmar — porque el caso 2 del propio owner
es *«un cliente que no sabía que se necesita justificante»*, y cerrar la puerta a los pedidos sin
marcar mataría justo ese caso. *Un interruptor de oferta no es un interruptor de permiso.*

### 12.3 Las tres puertas de entrada, y por qué la tercera no existe

| Caso del owner | Qué se construye |
|---|---|
| **1 · Al comprar, sutil** | La casilla del §12.2 y, al quedar pagado, un correo con el enlace. Precedente exacto ya montado: `GuestFormRequest` manda un enlace firmado desde `RedsysReturnHandler` y `ManualOrderFulfiller` |
| **2 · El operador lo genera desde la reserva** | Arreglar (a) + un icono de enlace **por línea**, gemelo del de `copyGuestFormLink` en `filament/orders/items-list.blade.php`, y el reenvío por `RESEND_TYPE_*` |
| **3 · Venta en persona sin reserva** | ⚠️⚠️ **NO SE CONSTRUYE, y la razón es que la premisa no se cumple** (`[DECIDIDO owner]`, con el coste delante) |

▶ **Medido**: `CreateManualOrderPage` **exige cliente** (`customer_id` es la condición de su paso, y
crea la cuenta con `CustomerRegistrar` si no existe), así que **toda venta de mostrador registrada en
JumpWeb ya produce un pedido con responsable**. El caso 3 **es el caso 2**.

▶ Y lo que habría costado hacerlo de todos modos, para que nadie lo reabra a la ligera: `order_id`
pasaría a nullable sobre una FK; **la puerta no lo encontraría** —`GateProfile` compone los
justificantes desde las **reservas de HOY** (`GateProfile::guestMinors()`)—; y la caducidad del enlace y el
plazo de conservación, que hoy cuelgan del pedido, se quedarían **sin ancla**. *Solo vuelve a la mesa
si el parque vende en una taquilla que no es JumpWeb.*

### 12.4 Lo que ve el padre: la reserva y QUIÉN responde de su hijo

`[DECIDIDO owner, 2026-09-01]`. Hoy la pantalla resuelve la reserva con **un párrafo** mientras el
post-form —su hermana— tiene una cabecera de resguardo (`.gf-stub`) con badge, titular y tres celdas.
La autorización pasa a hablar el mismo idioma, y la cabecera dice **las dos cosas que un padre
necesita antes de firmar**: a qué visita va su hijo, y con quién.

- **Del que reservó**: **nombre y teléfono**. ⚠️ **El correo NO** —`[DECIDIDO owner]` sobre la
  recomendación de este agente—: ese enlace lo reparte él por WhatsApp a desconocidos, y su buzón no
  tiene por qué viajar con él. El teléfono sí, porque es lo que permite localizarle el día de la visita.
- ⚠️⚠️ **«Apellidos del responsable» NO EXISTE y no es una omisión de la pantalla**: `users` tiene
  **una sola columna `name`** (verificado sobre el esquema). Lo que se enseña es el nombre tal como
  está en su cuenta. Inventar un apellido partiendo la cadena por el primer espacio sería fabricar un
  dato en una pantalla que acompaña a una prueba legal.
- ▶ **La piel de esta pantalla es la T3 de `celebracion-e-invitacion.md`** (§4.3 y §10.3, `DECISIONES #572`,
  2026-09-17): el descargo entero con «Firmar» en la barra pegada, cinco desenlaces en cuatro tonos, la casilla
  del sistema. ⚠️ **La hora de la visita llega COMPUESTA** (`AuthorizableReservation::$timeWindow`, de
  `OrderItem::displayTimeWindow()`): con el fin de la franja una fiesta de dos horas decía «17:00 – 18:00».
  Y su molde `.gf-*` es el del post-form: un cambio allí se mira también aquí, en ventana de teléfono.

### 12.5 Iniciar sesión: elegir al menor en vez de teclearlo

`Dependent` tiene **exactamente** los cuatro campos que este formulario pide —`name`, `surname`,
`born_on` y `relationship`—, así que un padre registrado elige a su hijo de una lista y se rellena el
bloque entero, **su relación incluida**.

⚠️ **Sigue sin enlazar la cuenta**: §4.3 prohíbe `signer_user_id` porque una columna `ON DELETE SET
NULL` no puede estar dentro de un hash que se verifica (§10, medido dos veces). Lo que se hace es
**copiar el dato**, exactamente como hoy con el prellenado del adulto. Iniciar sesión ahorra tecleo y
no cambia ni el valor probatorio ni la puntualidad del justificante.

⚠️ **Y arregla un defecto vivo**: `GuardianAuthorizationController::show()` prellena `guardian_name`
con `$user->name`, que es el nombre **completo**, y deja «Apellidos» vacío. El campo del formulario
está partido en dos y la cuenta no lo está.

### 12.6 «¿50 justificantes y bajo las entradas a 40?» — medido, con rollback

Sobre `PRUEBA-PUERTA`, bajando una línea de 4 a 1 con 2 justificantes firmados:

```
ANTES  : capacidad=4  justificantes=2
DESPUÉS: capacidad=1  justificantes=2      ← siguen los 2, y nadie dice nada
```

- **No se borra ninguno, y es correcto**: son firmas con valor probatorio, no cupos.
- **El tope solo mira hacia delante**: `if ($used >= $order->capacity)` impide firmar más; no revisa
  lo firmado. **No se toca** — es una puerta de `SEC-04` con su razonamiento medido en §4.7·3.
- ⚠️⚠️ **Y NADIE AVISA**: el roster no lleva la capacidad, así que ni el panel, ni la hoja de sala, ni
  la puerta, ni la cuenta del cliente dicen que hay más papeles que plazas. **La hoja imprime los 50
  tan tranquila.**
- ⚠️ Y hay un mensaje que **miente**: con la capacidad por debajo de lo firmado, el padre que abra el
  enlace lee *«Esta reserva ya tiene todas sus autorizaciones»* cuando la verdad es que **ya no tiene
  plazas**. Dos causas distintas con la misma frase.

▶ **La salida es decirlo, no borrar nada**: el roster gana la capacidad, las tres superficies del
operador pintan **«N justificantes · M plazas»** y una pastilla de aviso cuando `N > M`, y el bloqueo
por cupo gana frase propia. *Entre un fallo mudo y uno que habla, se elige el que habla* — que es
literalmente el criterio con el que se eligió `minor_key` en §4.8.

### 12.7 Plan — cinco tandas

| Tanda | Qué entra |
|---|---|
| **T5 · El interruptor** | `ticket_types.guardian_authorization` (3 estados) · `order_items.guardian_authorization` · el selector en `CatalogForm` · `Order::needsGuardianAuthorization()` |
| **T6 · El embudo** | La casilla en el cajón (`TimeStep`/`CartStep`), `CartPayload`, `OrderCreator` **forzando `required`**, el contrato, y la misma casilla en el pedido manual |
| **T7 · La entrega** | El correo al pagar · el reenvío · la sección del panel **siempre visible** · el icono por línea · **el aviso de §12.6 en panel, hoja y puerta** |
| **T8 · La pantalla** | La cabecera con la reserva y quien reserva (§12.4) · login → elegir menor a cargo (§12.5) · el prellenado partido · organización y textos |
| **T9 · Verificación** | Mutaciones por tanda · guion de navegador con el anti-bot encendido · el OJO del owner |

⚠️ **Ninguna toca `WaiverSigner` ni la cadena**, así que —a diferencia de la T1— **no entran en el
`CRITICAL_RE`**. Lo que sí lo hace es cualquier cambio en el tope, y por eso §12.6 **no lo cambia**.

### 12.8 T5 → T8 · EJECUTADAS (2026-09-01)

| Pieza | Dónde |
|---|---|
| **T5** · el interruptor (3 estados) + el hecho de la línea | migración `2026_09_02_010000_add_guardian_authorization_to_products_and_items` · `TicketType::GUARDIAN_*` + `guardianMode()` · `OrderItem` cast · `Order::needsGuardianAuthorization()` · `CatalogForm` |
| **T6** · el embudo | `OrderCreator` (**las tres ramas**) · `Cart::sanitize()` · `CartPayload` · `openapi/v1.yaml` (`CartLine` + `CatalogProductDetail`) · `CatalogReader` · `TimeStep.vue` + `PurchaseSection.vue` + `stores/selection.js` + `cart.js` |
| **T7** · la entrega | `OrderInfolist` (**el huevo-y-gallina**) · `partials/guest-minors` (estado vacío + aviso) · `ViewOrder::sendGuardianLinkAction()` · `Order::RESEND_TYPE_GUARDIAN` · `GuardianAuthorizationRequest` en `RedsysReturnHandler` y `ManualOrderFulfiller` · `items-list` (icono por línea) · hoja de sala |
| **T8** · la pantalla | `reservation/authorization.blade.php` (resguardo `.gf-stub` + pasos numerados + selector de menores) · `GuardianAuthorizationController` (`responsible`, `dependents`) · `guardian.php` en es/en/fr |

▶ **Lo que la ejecución enseñó y el diseño no había previsto:**

1. ⚠️⚠️ **`OrderCreator::create()` pasa la cesta por `Cart::sanitize()`, que es una LISTA BLANCA.** El
   campo se habría caído ahí **en silencio**: la casilla marcada, la línea sin marcar y nada fallando.
   Es la gemela exacta de la lista blanca de `cart.js::save()` que `menores-a-cargo.md` §8.1 documenta,
   y tiene caso propio (`test_the_session_cart_whitelist_lets_the_mark_through`) precisamente porque
   los casos que pasan por `OrderCreator` **no distinguen** ese fallo de uno del propio creador.
2. ⚠️ **La marca al FUNDIR dos líneas es un O, no «gana la existente»**: si en la segunda tanda venía
   el amigo del hijo, viene. La otra dirección perdía el aviso en silencio.
3. ⚠️ **Dos tests del panel aseveraban la lista EXACTA de tipos de reenvío** y su nombre pasó a mentir
   (`..._only_lists_confirmation`). Se renombraron: todo pedido pagado ofrece ahora el enlace del
   justificante, **sin mirar la marca**, que es la única salida del caso «el cliente no sabía».
4. ⚠️ **`ApiContractTest` exige que todo campo del contrato sea `required` salvo declaración expresa**.
   `CartLine.guardian_authorization` entra en `OPTIONAL_BY_DESIGN` con su porqué: ausente ya significa
   «no», y exigirlo metería `false` en cada línea de cada presupuesto.
5. ⚠️ **Los dos presupuestos del cajón**, con la poda MEDIDA antes de subirlos: chunk **262,95 →
   264,53 KiB** (techo 263 → 265) y las dos excepciones de líneas de componente (431 → 433, 44 → 46).
   La única poda disponible ahorraba **0,10 KiB** a cambio de empeorar el diseño — *una poda que no
   llega al 7 % de lo que ahorra el techo no es una poda*. Sí se aplicó la que sí valía: tres props
   resueltos pasaron a uno.

▶ **Verificación:** **9 de 9 mutaciones muerden** entre T5 y T6, con CONTROL verde antes y después
(el arnés exige verde antes de mutar y mide por código de salida, las reglas de `#335` y `#317`).
**29 casos** en la pantalla pública y **8** en la entrega. Suite completa verde.

▶ **En NAVEGADOR real** (390×844 y 1280×900): resguardo con referencia, día y «Va con», tres pasos
numerados, casilla desmarcada, cero desbordes horizontales y cero errores de JS o de red. **El
selector de menores probado con sesión de verdad**: solo aparece el menor (el de 22 años queda
filtrado), rellena los cuatro campos y volver a «a mano» **vacía**.

⚠️⚠️ **Dos defectos que solo vio la CAPTURA, y ninguna guarda mira anchos**: el `guardian.intro`
sobrevivía debajo del `lede` diciendo lo mismo —y con «un menor **a tu cargo**», que es justo lo que
este menor NO es— y el titular del paso 3 se estrangulaba a tres líneas contra el rótulo de la
versión. La clave muerta se retiró de `lang/` tras comprobar que no tenía otro consumidor.

⚠️ **Y la trampa de `#335` volvió a caer**: una captura salió **sin una sola letra**. El control lo
zanjó —el texto estaba en el árbol y `document.fonts` tenía **cero** familias cargadas—: es FOIT, la
fuente externa no se alcanza desde el contenedor. *El instrumento es el primer sospechoso.* La sonda
captura ahora forzando la familia de respaldo.

### 12.9 Lo que NO se hizo, y por qué

- **Un enlace «inicia sesión y vuelve» en la pantalla pública.** ⚠️ Medido: `/login` en este proyecto
  es **el cajón de la portada** (`Route::get('/login', HomeController::class)`) y **no hay cadena
  `intended`** en toda la app (cero apariciones). Un enlace ahí dejaría al padre en la home con el
  cajón abierto y **la URL firmada perdida** — peor que no ofrecerlo. El atajo sigue existiendo para
  quien ya tenga sesión, que es lo que §4.6 prometía.
- **El aviso de §12.6 en la PUERTA.** El presupuesto de consultas de la ficha de puerta es una
  restricción de diseño (techo 28, y ya cazó un N+1 en `#294`); el desfase papeles-vs-plazas es un
  asunto de trastienda que el panel y la hoja de sala ya dicen. Ficha en `DEUDA.md` si algún día la
  puerta lo pide.
- **Tocar el TOPE.** §12.6 lo deja intacto a propósito: es una puerta de `SEC-04` con su razonamiento
  medido en §4.7·3, y cambiarla metería esta tanda en el `CRITICAL_RE`.

---

## 13. EL JUSTIFICANTE CUELGA DE LA RESERVA, NO DEL PEDIDO (2026-09-02, `DECISIONES #401`)

> **Lo encontró el owner probando `#400` con un pedido real** (`R-LUKFD2`). Su frase: *«y 1
> justificante es por reserva no por pedido, creo que ahí tenemos el fallo»*. Tenía razón, y esa sola
> raíz explicaba cuatro síntomas que parecían independientes.

### 13.1 Lo medido, con el pedido delante

```
PEDIDO R-LUKFD2  paid  user=admin@jumpweb.test
  item 450  Excursión 2 h (pack)   qty=80  slot=2026-09-07 09:00  justif=no
  item 451  Jump · 1 hora (entry)  qty=1   slot=2026-09-03 12:00  justif=SÍ
  capacidad=81   fechas=2026-09-03, 2026-09-07
```

| Síntoma que vio el owner | De dónde salía |
|---|---|
| *«pone Días de la visita 03/09 · 07/09, ¿por qué dos fechas?»* | El enlace era del PEDIDO y el pedido tiene **dos visitas**. |
| *«solo 1 justificante y yo puse dos productos»* | El correo era **uno por pedido**, no uno por reserva. |
| *«falta explicar el tipo de reserva»* | La hoja no podía decirlo: con dos líneas, **no había UN producto que nombrar**. |
| *«0 justificantes · 3 plazas cuando 1 entrada ya está asignada a un menor»* | La capacidad sumaba las líneas **y no descontaba** los menores a cargo ya asignados. |

⚠️ **Y una premisa suya NO se cumplía, medida antes de tocar nada**: *«aparte de la excursión que es
obligatorio»* — `Excursión 2 h` (id 163) tenía `guardian_authorization = none`. El único producto
configurado era `Jump · 1 hora` (`optional`). Por eso solo se marcó esa línea. *Antes de arreglar un
síntoma, comprobar que la configuración que se le supone existe de verdad.*

### 13.2 El cambio: el sujeto es la VISITA

**`guardian_authorizations.order_id` → `order_item_id`** (migración
`2026_09_02_120000`, con relleno a la primera línea principal viva; medido: 3 filas en local, 0 en
producción, todas de pedidos de una sola línea, así que el relleno es exacto).

Y con él, en cadena:

| Pieza | Antes | Ahora |
|---|---|---|
| Contrato | `AuthorizableOrder(s)` | **`AuthorizableReservation(s)`** — con `productName`, `date`, `startTime`/`endTime` |
| Ruta | `/autorizacion/{order}` | **`/autorizacion/{reservation}`**, gemela de `/reserva/{reservation}/datos-invitados` |
| Enlace | `Order::guardianAuthorizationSignedUrl()` | **`OrderItem::…`**, gemela exacta de `guestFormSignedUrl()` |
| Correo | uno por pedido | **uno por reserva marcada**, con su tarjeta de producto |
| «Un niño, un papel» | `unique(order_id, minor_key)` | **`unique(order_item_id, minor_key)`** |
| Puerta | filtraba por pedido del día | filtra por **reserva** del día |
| Hoja de sala | los del pedido | **los de ESA reserva** |
| Cuenta del cliente | «Mis pedidos», dentro del desglose | **«Mis reservas»**, en la tarjeta de cada reserva |

▶ **«Un niño, un papel» GANA con el cambio**: el mismo menor que va a dos visitas del mismo pedido
necesita **dos** autorizaciones, y con la clave por pedido la segunda se rechazaba diciendo que ya
estaba firmada.

### 13.3 Las plazas LIBRES — y por qué esto no contradice a §4.10

`Identity\Services\GuardianPlaces`:

```
libres = cantidad de la línea − menores a cargo YA asignados − justificantes YA firmados
```

⚠️⚠️ **Vive en Identity y no en el contrato de Booking**: la cantidad la sabe Booking y los menores a
cargo los sabe Identity, y **Booking no puede mirar a Identity**. El único sitio donde las dos cifras
coexisten es ahí.

⚠️ **§4.10 prohíbe el denominador inventado y esto no lo es.** Aquélla dice que no se puede saber
cuántos de los comprados son menores —cierto, y por eso no se dice «3 de 100»—. Pero **una plaza
asignada a un menor a cargo ya tiene dueño**, y eso es un HECHO. El owner lo vio antes que nadie:
compró una entrada, se la asignó a su hija, y la pantalla seguía ofreciendo firmar.

⚠️ **Los adultos NO se restan**: una entrada sin asignar puede ser un adulto o un menor invitado, y
suponer lo primero cerraría la puerta a quien tiene derecho a firmar. **La cota es superior a
propósito**: el tope existe para que nadie autorice a más gente de la que se compró, no para adivinar
la composición del grupo.

### 13.4 Lo que el cambio se lleva por delante, dicho

**Una reserva CANCELADA sale de la ficha del pedido y de la cuenta del cliente**, aunque tenga
justificantes firmados: deja de ser una visita, y `AuthorizableReservations::find()` filtra las
canceladas porque para AUTORIZAR es lo correcto. Las firmas siguen en la base de datos y en el
registro probatorio —no se pierde ninguna prueba—, pero el operador deja de verlas ahí. Queda escrito
para que nadie lo descubra como un hallazgo.

### 13.5 El embudo: más sutil, y la casilla que ya no miente

`[DECIDIDO owner, 2026-09-02]`: *«quiero esa parte de menores a cargo y justificantes de manera más
sutil, es demasiado centrada en el proceso, hazla tal vez con un desplegable»*.

- El bloque **«¿Quiénes vienen?»** es un `<details>` **nativo** —cero JavaScript, así que no puede
  quedarse roto si el motor falla, que es lo que la T2 pagó con el anti-bot— y **nace cerrado**. El
  rótulo dice lo que hay dentro y cuántos van marcados, para que quien SÍ tenga que entrar no tenga
  que abrirlo para descubrirlo.
- **La casilla no se puede marcar sin plazas libres**, y se dice POR QUÉ: apagarla en silencio dejaría
  al cliente sin poder atar «he asignado todas mis entradas» con «ya no puedo marcar esto».
  ⚠️ **Ya marcada SÍ se puede desmarcar** aunque no queden plazas: si no, quien se equivoca queda
  atrapado con una casilla que no puede apagar.
- Las dos reglas viven en `assignment.js` con sus casos de `node --test`, no en el componente: lo
  pidió `SidebarComponentBudgetTest` y tiene razón — *un árbol dice qué se pintó, no qué rama se
  eligió*.

### 13.6 Lo que enseñó la ejecución

1. ⚠️⚠️ **El orden de los `ALTER` lo impone MySQL y solo se ve al pisarlo**: con el `UNIQUE` ya
   retirado, el índice simple es el único que respalda la FK y **no se puede soltar antes que ella**
   (*«Cannot drop index …: needed in a foreign key constraint»*). La FK primero.
2. ⚠️ **La migración quedó a medias en la primera pasada** —columna añadida y rellenada, `UNIQUE`
   fuera, el resto no— y como no llegó a registrarse, el reintento se estrelló con *«Duplicate column
   name»*. **Una migración de varios `ALTER` tiene que ser idempotente**: el estado intermedio existe.
3. ⚠️ **`SHOW INDEX` es de MySQL y la suite corre en SQLite**: una migración que solo sabe hablar con
   un motor rompe los 3.900 casos en el primer `RefreshDatabase`. Es `Schema::getIndexes()`.
4. ⚠️⚠️ **Un `str_replace` que no casa NO FALLA**: uno de los reemplazos de la plantilla buscaba
   `guardianRequired` cuando el fichero ya decía `guardianMode === 'required'`, y el resultado fue un
   `<details>` sin cerrar que solo cazó el compilador de Vue. *Un guion de edición sin `assert` es un
   guion que puede no hacer nada y decir que sí.*
5. ⚠️ **El cajón solo resuelve `singular|plural` y ni siquiera eso sin `tc()`**: el rótulo del
   desplegable pasó a no llevar plural (`Menores a tu cargo: 2`), que es lo que `SidebarTextParityTest`
   admite sin arrastrar el locale hasta el paso 3.
6. ⚠️ El manifiesto congelado del árbol del cajón **se regenera a propósito** (`MANIFEST_REFRESH=1`) y
   se dice en el commit: el `<details>` es un cambio deliberado del contrato visual.

### 13.7 Dos defectos MÁS que el owner encontró mirando (`#402`)

Los dos son la misma clase de fallo: **algo que sale verde y no se ve**.

**(a) `admin.orders.guest_minors.assigned` se pintó EN CRUDO.** La clave se usó y nunca se declaró.
Laravel no falla ante una clave ausente —devuelve la clave—, y **ningún test lo vio** porque esa línea
solo se pinta cuando la reserva tiene menores a cargo asignados y ninguna guarda montaba ese caso.

▶ Guarda nueva, y **general a propósito**: `GuardianLinkDeliveryTest::test_no_untranslated_key_reaches_the_order_page`
monta ese caso y afirma que **el identificador de ningún grupo de idioma** (`admin.`, `guardian.`,
`tickets.`) aparece en el HTML de la ficha. Con CONTROL de que la sección se pinta de verdad —sin él,
una página que no la incluyera pasaría en blanco, el escalón de `#161`— y **vista morder** con la
clave retirada.

**(b) EL ENLACE NUNCA LLEGÓ A PINTARSE, Y NO ERA DONDE ESTABA.** El owner lo dijo dos veces —*«no me
sale nada del enlace»*— y las dos se le achacó a otra cosa (la visita pasada en `#400`, el sitio en
`#401`). **Había una tercera causa debajo**:

```
api.js  →  result(true, status, payload)      // payload = {data: {...}}  ← el SOBRE ENTERO
store   →  guestMinors[code] = response.data  // = {data: {...}}
panel   →  guestMinors[code].reservations     // undefined
```

**La petición salía con 200 y la pantalla no pintaba nada.** Medido en navegador: `200
/api/v1/orders/PRUEBA-J2/guest-minors` en la pestaña de red y `paneles: 0` en el DOM.

▶ *Un 200 en la pestaña de red no dice que el dato haya llegado a donde se lee.* El resto del store
desenvuelve al COMPONER (`cardRows(payload)` hace `payload?.data ?? []`); este ámbito no tiene
compositor, así que **desenvuelve al guardar**. Tres casos de `node --test` en
`stores/orders.test.js`, incluido el que fija que lo guardado es el CONTENIDO y no el sobre.

⚠️ **Y de la sonda salió una tercera condición para el enlace**: con **cero plazas libres** tampoco se
ofrece. Repartirlo sería mandar a un padre a una pantalla que le dirá que no — la misma razón por la
que se apaga con la visita pasada. El caso real es el del owner: una entrada asignada a su propia hija.

⚠️ **Ruido ajeno visto de paso y NO tocado**: bajo cinco peticiones simultáneas al entrar, el driver de
caché en BD lanza `1213 Deadlock` sobre la tabla `cache` y Laravel reintenta. Es preexistente y no es
de esta feature; queda anotado por si alguien lo persigue.

### 13.8 Compartir el enlace, y el rótulo que se salía del botón (`#403`)

**(a) El botón del post-form.** `.btn` es `white-space: nowrap` y `.orders__guestform-btn` es de ancho
completo dentro de una tarjeta de 308 px. Medido con CONTROL: «Completa el formulario de Cumpleaños
Jump» cabía con **cero margen** (308 = 308) y un nombre más largo pedía **418**.

▶ `[DECIDIDO owner]`: **se quita el nombre del producto** de los cuatro estados —está tres líneas más
arriba en cuerpo grande— y **el botón pasa a `white-space: normal`**, que arregla la CLASE: ningún
rótulo futuro, en ningún idioma, puede volver a desbordarlo. *Un rótulo que interpola un nombre que
escribe el panel no puede tener regla de longitud* (`#303`).

**(b) Compartir o copiar.** El enlace se reparte a los padres uno a uno, así que el gesto ES la
feature: en un teléfono abre la hoja del sistema (WhatsApp) y en un escritorio copia.

- ⚠️⚠️ **Cerrar la hoja de compartir no es un fallo** (`AbortError`): no se dice nada **y no se copia**
  —copiar lo que alguien decidió no mandar es peor que no hacer nada—. Se distingue por el `name`, no
  por el mensaje, que cambia con el idioma del sistema.
- ⚠️ La regla vive en `account/share-link.js` (`CE-6`) con seis casos y las dependencias por
  parámetro: en el Node del contenedor `navigator` no existe y leer `navigator.clipboard` **lanza**.
- ⚠️ **`shared` y `copied` son dos acuses distintos**: decir «copiado» cuando el sistema acaba de abrir
  WhatsApp sería mentir.
- ⚠️ **El `input` de solo lectura se queda**: si las dos APIs fallan, el cliente lo selecciona a mano.
- ⚠️⚠️ **El dibujo es la geometría de `<x-icons.share>`, copiada, no inventada.**
  `SidebarIconParityTest` paró el primer intento: el cajón tiene `DRAWER_OWN` **vacía** desde `#258`.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Justificante de un menor INVITADO a una reserva («waiver offshore») · el padre sin cuenta que firma por un menor que no es menor a cargo · el enlace de hoja en blanco · CÓMO SE ACTIVA · la casilla del embudo · el interruptor por producto · las plazas libres»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/waiver-por-reserva.md` · `docs/specs/cumple-mixto.md` · `docs/VERIFICACION-E2E-CAJON.md` · `docs/ESTADO.md` · `docs/DEUDA.md`.

- **`docs/specs/waiver-por-reserva.md`**
- 🟦 **T1+T2+T3, LA ACTIVACIÓN (T5→T8) y EL MODELO POR RESERVA EN EL ÁRBOL** (`#328` · `#335` · `#337` · `#400` · **`#401`**) —
- ❗❗❗ **`#401` ES LO PRIMERO QUE HAY QUE SABER: EL JUSTIFICANTE CUELGA DE LA RESERVA, NO DEL PEDIDO** (§13). Lo cazó el owner con un pedido real: dos visitas en días distintos y la hoja del padre decía *«Días de la visita: 03/09 · 07/09»*. **De esa sola raíz salían CUATRO síntomas** —dos fechas, un solo correo para dos reservas marcadas, la capacidad sumando las dos líneas (81 plazas) y un «un niño, un papel» que impedía autorizar al mismo niño para dos visitas del mismo pedido—.
- ▶ `guardian_authorizations.order_item_id`, ruta `/autorizacion/{reservation}`, contrato `AuthorizableReservation(s)` con producto/día/hora, **un correo por reserva marcada** y el enlace en **«Mis reservas»**, que es donde el owner lo buscó.
- ⚠️⚠️ **Las PLAZAS LIBRES son `cantidad − menores a cargo asignados − justificantes firmados`** (`Identity\Services\GuardianPlaces`, en Identity porque **Booking no puede mirar a Identity**). **Esto NO contradice a §4.10**: aquélla prohíbe inventar «3 de 100» porque no se sabe cuántos son menores; una plaza asignada a un menor a cargo **ya tiene dueño**, y eso es un hecho.
- ⚠️ Los ADULTOS no se restan: la cota es superior a propósito.
- ⚠️ **Una reserva CANCELADA sale de la ficha y de la cuenta** aunque tenga firmas: deja de ser una visita (las firmas siguen en el registro probatorio).
- ⚠️⚠️ **Si tocas la migración**: el orden de los `ALTER` lo impone MySQL —**la FK antes que su índice**— y tiene que ser **idempotente**; `SHOW INDEX` **no vale** (la suite es SQLite): `Schema::getIndexes()`.
- ⚠️ **El embudo**: «¿Quiénes vienen?» es un `<details>` **nativo** (cero JS) plegado, y **la casilla no se puede marcar sin plazas libres** —diciendo por qué— pero **sí se puede desmarcar**. Sus dos reglas viven en `assignment.js` con `node --test`.
- ⚠️ **El cajón no resuelve plurales sin `tc()`**, que necesita el locale: los rótulos del desplegable no llevan plural. — **`#400` · LA ACTIVACIÓN** —
- ❗❗❗ **`#400` SI TOCAS ALGO DE ESTA FEATURE, EMPIEZA POR §12**: el owner probó lo construido y **no había puerta por la que entrar** (*«no me sale nada del enlace»*). El enlace tenía **tres consumidores en el repo y ninguno lo OFRECÍA**, y el peor era un **HUEVO Y UNA GALLINA**: el botón «Copiar enlace» vivía DENTRO de una sección `visible(countFor > 0)`, así que solo salía cuando ya había un justificante firmado — y para que hubiera uno hacía falta el enlace. *Una condición de visibilidad escrita para lo que se LEE acaba escondiendo lo que se HACE.*
- ▶ **Lo decide el PRODUCTO** (`ticket_types.guardian_authorization`: `none` · `optional` · `required`) y el hecho se guarda en `order_items.guardian_authorization`, que es un HECHO como el sello de `cumple-mixto.md`: **cambiar el producto mañana no reescribe lo que se compró ayer**.
- ⚠️⚠️ **`required` lo marca el SERVIDOR**: si saliera de la casilla, una excursión se compraría sin justificantes quitando un `input` del DOM.
- ⚠️⚠️ **«Se ofrece» NO es «se permite»** — quien tenga el enlace de un pedido pagado firma SIEMPRE, marcado o no, porque el caso 2 del owner es *«un cliente que no sabía que hacía falta»*.
- ⚠️⚠️ **`Cart::sanitize()` es una LISTA BLANCA** y el campo se caía ahí **en silencio** camino de `OrderCreator` (casilla marcada, línea sin marcar, nada fallando); hay una segunda costura igual en `cartToOrderCart()` del pedido manual, y las dos tienen caso propio.
- ⚠️ **Al FUNDIR dos líneas la marca es un O**, no «gana la existente».
- ⛔ **El «caso 3» (justificante SIN reserva) NO se construye y la premisa es falsa**: `CreateManualOrderPage` **exige cliente**, así que toda venta de mostrador ya produce pedido con responsable; reabrirlo costaría `order_id` nullable, **la puerta no lo encontraría** (compone desde las reservas de HOY) y la caducidad se quedaría sin ancla.
- ⚠️ **«¿50 justificantes y 40 entradas?»** medido con rollback: **no se borra ninguno** (son firmas) y **nadie lo decía** — ahora el panel y la hoja pintan «N justificantes · M plazas» con aviso; **el TOPE no se toca** (`SEC-04`).
- ⛔ **NO hay enlace «inicia sesión y vuelve»** en la pantalla pública: `/login` es el cajón de la portada y **no hay cadena `intended` en toda la app**, así que perdería la URL firmada.
- ⚠️ **Dos defectos que solo vio la CAPTURA** (ninguna guarda mira anchos) y **la trampa de `#335` otra vez**: captura sin una sola letra, y el control la zanjó — `document.fonts` con **cero** familias cargadas, o sea FOIT del contenedor. — **T1 + T2 + T3** (`#328` · `#335` · `#337`, 2026-09-01; **guion del OJO del owner en `VERIFICACION-E2E-CAJON.md` §5.septies con el escenario sembrado**) —
- ❗❗❗ **EMPIEZA POR §11: LA REVISIÓN ADVERSARIAL ENCONTRÓ DOS BLOQUEANTES Y UNA AFIRMACIÓN CENTRAL DEL DISEÑO QUE ERA FALSA** («no cambia una línea del mecanismo existente» — sí cambia, y justo donde más duele).
- ⚠️⚠️ **LA CLAVE DE SUJETO ESTÁ HOY CABLEADA A `subject_id` EN TRES SITIOS Y CON `NULL` LOS TRES SE CRUZAN**: `WaiverSigner` busca la firma anterior con `where('subject_id', null)`, que Laravel convierte en **`is null`** (medido), así que la idempotencia por versión **devuelve la firma de OTRO menor** — el segundo padre ve la pantalla de «hecho», recibe su correo y **su hijo se queda sin justificante, sin fallo y sin aviso**—; y `WaiverChain` agrupa por `subject_type.':'.(subject_id ?? '')`, así que todos caen en `guest_minor:` y el verificador declara **ROTA una cadena sana** (medido: `count=2 · chains=1 · ok=false`), con el defecto **duplicado** en `VerifyWaiverChainConcurrency`.
- ▶ La clave pasa a **UN solo sitio** (`WaiverSignature::chainKey()`).
- ❗❗ **§4.5 ES LA SECCIÓN QUE EVITA LA FUGA Y NO ESTABA**: poner al responsable en `user_id` hace que sus firmas de menores invitados salgan en **todo lo que lista «las firmas de este usuario»** — `GET /me/waiver` las devuelve con `dependent_id: 0` y el nombre del hijo de otro, **y sirve su PDF** (autoriza solo por `user_id`); igual el partial del panel (que además las rotularía como «menor a cargo») y el contador del audit.
- ✅ `WaiverStatus::for()`/`forDependents()` **sí** filtran: están limpias.
- ❗ **§1.2 CORRIGE EL ALCANCE DE `ESTADO.md`**: no es una feature de excursiones de colegio, es del WAIVER — y por eso **no puede apoyarse en nada que solo tengan los packs** (`guestFormStatus()` es `null` fuera de pack → el caso normal son entradas y **cero fichas**).
- ❗ **§1.4(b), el bloqueo que decide el diseño**: `subject_id` tiene **FK dura a `dependents`** y **rechaza de verdad** (`1452` medido; SQLite la ejerce igual) → columna propia.
- ▶ **Lo que hace que encaje sin migración destructiva**: `user_id` es **el que reserva** —el RESPONSABLE, `[DECIDIDO owner]`—, y `orders.user_id` es NOT NULL, así que siempre lo hay.
- ⚠️⚠️ **NO metas al menor invitado en `dependents` con una columna de «ámbito»**: es la trampa de `prices` de `#324` y son **~14** los lectores (la spec decía «seis»: era mío y estaba mal).
- ⚠️⚠️ **`order_id` RESTRICT ROMPE `PurgeCustomerData`** —borra TODOS los pedidos y solo las firmas de los purgados— **y CASCADE tampoco lo arregla** (la FK de la firma es RESTRICT): la purga aprende **tres pasos** (§4.2.1), y sí, borra firmas de cuentas que conserva, a propósito.
- ⚠️⚠️ **ESTO SÍ ENTRA EN EL `CRITICAL_RE`** —`WaiverSigner` está en la lista y la T1 lo modifica—: la spec decía lo contrario.
- ⚠️ **`signer_user_id` NO existe a propósito**: una columna `ON DELETE SET NULL` no puede estar dentro de un hash que se verifica, y **§10 lo tiene MEDIDO DOS VECES** sobre el subsistema de hoy (`declared_by_user_id`: borrar al operador pone `verifyHash()` en `false` sobre una firma que nadie tocó; ficha en `DEUDA.md`).
- ⚠️ **`subject_name` es `varchar(120)` y el firmador corta a 255** → `1406` en MySQL y **verde en SQLite**; defecto VIVO hoy para menores a cargo.
- ⚠️ **La unicidad NO va sobre los nombres crudos**: `utf8mb4_unicode_ci` iguala «Perez» y «Pérez» y SQLite no → columna `minor_key` normalizada en PHP, determinista en los dos motores.
- ⚠️ **El tope NO es `SUM(quantity)`** (cuenta complementos, portadores y canceladas: un pedido íntegramente cancelado admitiría 2).
- ⚠️ **Un `Prunable` que no entre en la lista de `routes/console.php` no se poda NUNCA** — y hoy los dos plazos valen `NULL`, así que no se poda nada.
- ⚠️ **El escenario de concurrencia obvio NO MUERDE** (N padres distintos son N cadenas de una fila): el que muerde es **N envíos de la MISMA autorización**.
- ▶ **Nueve `[DECIDIDO owner]` en §7** —sin verificar el correo · un enlace por reserva, hoja en blanco · en la puerta se señala · el responsable ve nombres y quién falta · sin DNI · sin alergias · copia por correo · **la rama se salta la puerta de `email_verified_at`** (un colegio reserva por teléfono y sin eso NADIE podría firmar) · **un niño, un papel**— y **cuatro tandas en §8: la T1 primero, y el acotado de §4.5 va DENTRO de ella** porque la fuga nacería con el dominio.
- ▶ **§8.1 es lo EJECUTADO**, con las tres cosas que el plan no previó (**todo modelo necesita alias de morfo**; dos tests hermanos fijaban `canonical_version` como literal y se actualizan a propósito; la FK sobrevive al `change()`) y las cuatro trampas pagadas: **Pint convirtió un `{@see}` en `use`** (la de `#320`), **un `assertDontSee` sobre la página del panel pasaba en VACÍO** porque el registro vive en un modal (la de `#161`), **heredar de `Tests\TestCase` en vez de `ApiTestCase` convierte un test de contrato en un test de texto**, y **`Sanctum::actingAs()` deja una instancia OBSOLETA**.
- ▶ **§8.2 es la T2**: ruta `/autorizacion/{order}` (**no `/reserva/…`**: aquí «reserva» es un `OrderItem`), contrato `AuthorizableOrders` —que **NO recibe titular a propósito**: quien abre el enlace no tiene cuenta, así que no devuelve nada que un desconocido no pueda ver—, las TRES puertas **bajo el lock** y la escalada **403→410→404** cuyo ORDEN es la propiedad.
- ❗❗❗ **SI TOCAS UN ANTI-BOT, LEE ESTO**: copiar el silencio de `/contacto` fue un defecto REAL que **solo vio la sonda de NAVEGADOR** — sin token de Turnstile la pantalla **no escribía nada y decía «Listo»**, y el padre se enteraba en la puerta del parque. **El HONEYPOT calla** (un campo invisible relleno es señal de bot y de nada más) **y TURNSTILE lo DICE** (falla también a personas); la asimetría tiene caso propio.
- ⚠️ Y el honeypot **no se llama `website`**: ese nombre mapea al autocompletado `url` y un gestor de contraseñas se lo rellena a una persona.
- ⚠️ **Un pedido con SEÑAL sigue siendo `paid`** (medido: 30 € de 88 €), así que exigir «pagado» NO deja fuera a las excursiones.
- ⚠️ **El tope NO es `SUM(quantity)`** (cuenta complementos, portadores y canceladas).
- ⚠️ **`Order` se resuelve por `code`.**
- ⚠️ **Hasta la T3 quien firma NO recibe copia.**
- ⚠️⚠️ **Y el arnés de mutación dijo «14 de 14» siendo 13**: su `git checkout` se llevó un caso sin commitear y un filtro que no casa con ningún test sale con código ≠ 0 — **ahora exige VERDE antes de mutar**.
- ⚠️ Una captura al `domcontentloaded` sale **sin una sola letra** (fuentes sin cargar): parece un defecto y es el instrumento.
- ▶ **§8.3 es la T3**: las seis superficies.
- ⚠️ **`GuardianRoster` tiene DOS formas y no una con un filtro** (`forOperator`/`forResponsible`) — «el responsable no ve a los otros padres» lo impone el TIPO, y el contrato es la segunda guarda (`additionalProperties: false`).
- ⚠️ **Los menores invitados van al NIVEL de la ficha de puerta**, no dentro de cada reserva: la autorización cuelga del PEDIDO.
- ⚠️ **La hoja de sala se compone en el CONTROLADOR** porque `ReservationSlip` vive en Booking, que no ve Identity.
- ⚠️ **El PDF va ADJUNTO al correo, no enlazado**.
- ⚠️⚠️ **Tres guardas de arquitectura cazaron tres defectos**: un componente hablando con la API (`CE-6`), clases de CSS sin regla (`#253`) y la lista exacta de claves del montaje.
- ⚠️ **Los dos presupuestos del cajón se podan ANTES de subirlos** (chunk 263 KiB · textos 9.400 B, que los paga CADA página con sesión).
- ⚠️ **§4.11 decía que `GateReservation` no lleva `order_id`: era FALSO.** Pendiente del
- ✅ del owner y del plazo de conservación
