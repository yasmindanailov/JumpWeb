# [SPEC] El waiver con valor probatorio

> Estado: 🟦 **EN EJECUCIÓN** — revisada (**§8**) y **tanda 1 (el núcleo) HECHA el 2026-08-25 (§9,
> `DECISIONES #160`)**; el ✅ final espera al owner (plazo de conservación y texto definitivo) ·
> Última actualización: 2026-08-25 ·
> Verificado contra código: 2026-08-24 (consents, SelfSignup, Page, User::anonymize, SecurityHeaders)
> y **re-verificado el 2026-08-25 por la revisión** (§8.0) ·
> Decisión asociada: `DECISIONES #142`, revisión en `DECISIONES #156` ·
> Se invalida si: cambia el modo de gestión del waiver, o el owner fija el plazo de conservación.
>
> ❗❗ **Si vas a tocar código, LEE §9 PRIMERO**: dice qué existe ya, en qué TRES cosas la ejecución se
> apartó del cuerpo (y por qué) y qué quedó medido. **Después §8, antes que el cuerpo.**
> ❗❗ **LEE §8 ANTES QUE EL CUERPO.** La revisión encontró **un bloqueante que no es de diseño** —el
> texto del waiver es literalmente un borrador y publicar es irreversible (§8.1)— y **dos piezas que
> hay que decidir antes de la primera línea**: cómo se serializa la cadena de hashes (§8.5) y dónde
> vive el registro (§8.6, que §4.3 dejó abierto a propósito). §8.2, §8.3 y §8.4 **corrigen
> afirmaciones del cuerpo**: léelas antes que el texto que corrigen.

Subsistema **B** de la visión de Fase 6. Es el **primero por dependencia**: define el modelo de
consentimiento del que cuelgan los menores a cargo (`menores-a-cargo.md`) y el estado que enseña la
pantalla de puerta (`identidad-qr-puerta.md`).

---

## 1. Contexto y problema

El sector exige que quien entra al recinto haya aceptado un documento de asunción de riesgo
(«waiver»). Hoy ese documento **se gestiona fuera de esta web** y aquí solo se guarda el sello.

**Lo que hay, verificado contra el código el 2026-08-24:**

| Pieza | Estado real |
|---|---|
| `users.waiver_accepted_at` | Un `timestamp`. Es todo lo que la puerta consulta |
| Tabla `consents` | `user_id · type · accepted_at · ip · version`. Sin user-agent, sin texto, sin hash |
| `Identity\Services\SelfSignup` | El alta escribe `privacy` y `terms`. **El waiver NO**: salió del flujo a propósito, con el comentario dentro |
| `PuertaSettings::waiverCheckEnabled()` | Interruptor para apagar la comprobación cuando lo gestiona el sistema externo |
| Texto del waiver | Una `Page` con slug `waiver`, **editable desde el panel** (`PageResource`) |
| `Consent::CURRENT_VERSION` | **Una constante escrita a mano en código**: `'2026-05-23'` |

**Los tres defectos que esto tiene hoy, y dos son latentes ya:**

1. ⚠️⚠️ **Nadie puede reconstruir qué texto firmó nadie.** La versión es una constante de código y el
   texto es contenido editable de base de datos: en cuanto la dueña edite la página, todas las firmas
   anteriores apuntan a una versión cuyo contenido **ya no existe**. Un PDF compuesto a posteriori
   desde el CMS no prueba nada — prueba lo que dice la base de datos hoy.
   ▶ **Esto no es un problema del waiver: afecta igual a `privacy` y `terms`**, solo que ahí todavía
   no duele. El mecanismo de este spec lo repara de raíz.
2. **No hay documento.** No existe ningún PDF ni superficie que enseñe la prueba, ni al operador ni al
   titular.
3. ⚠️ **`User::anonymize()` BORRA los consentimientos** (`$this->consents()->delete()`, `RGPD-01`).
   Hoy es correcto: un consentimiento sin titular no vale nada. En cuanto el waiver sea el documento
   con el que el negocio se defiende de una reclamación, **el art. 17 destruiría exactamente la prueba
   que justifica su existencia**.

## 2. Objetivo

Que, meses o años después, el negocio pueda demostrar que **una persona concreta aceptó un texto
concreto en un momento concreto**, y que ese registro no se ha alterado desde entonces.

**Criterios de éxito medibles:**
- Dado un titular y una fecha, se puede recuperar **el texto exacto que se le presentó**, en el idioma
  en que lo vio, y comprobar su hash.
- El PDF se compone **del snapshot y nunca del CMS** — demostrado por mutación: editar la página del
  waiver **no cambia** un PDF ya emitido.
- Un texto publicado **no se puede modificar ni borrar** — demostrado por mutación.
- El registro **sobrevive a `User::anonymize()`**, bajo régimen restringido, y **se purga al vencer su
  plazo**.
- El alta **no permite** aceptar sin que el servidor sepa qué versión sirvió.

**FUERA de alcance:**
- Firma electrónica cualificada (QES) o sellado de tiempo cualificado (eIDAS) — §4.7 deja la puerta
  abierta y explica por qué hoy no.
- Migrar `privacy` y `terms` al mecanismo nuevo. Se diseña general, **se aplica solo al waiver**:
  migrarlos toca el flujo de alta y los consentimientos existentes, y eso es otra tanda.
- Grupos escolares y firma por terceros no tutores — retirado en `DECISIONES #142`.

## 3. Opciones consideradas

**A · Copia íntegra por firma.** Cada aceptación guarda su propio ejemplar del texto.
DESCARTADA: duplica el mismo cuerpo miles de veces y engorda la tabla que más se consulta en puerta,
a cambio de nada que la opción B no dé.

**B · Documento versionado inmutable + referencia (ELEGIDA).** Una fila por (documento, idioma,
versión), que **nunca se edita ni se borra**; la firma la referencia. Una publicación crea fila nueva.
Da además «cuál es la versión vigente» **como dato** en vez de como constante de código, que es el
defecto 1 del §1.

**C · Solo hash del texto, sin guardar el cuerpo.** DESCARTADA: un hash prueba integridad pero **no
permite enseñar lo firmado**, que es justo lo que hace falta en una reclamación.

**D · Delegar en un proveedor de firma electrónica.** DESCARTADA por economía, no por técnica: se paga
**por firma**, y un recinto con decenas de miles de altas al año convierte un requisito de trazabilidad
en un coste recurrente de cinco cifras. §4.7 explica la alternativa que da la misma garantía por dos
órdenes de magnitud menos.

## 4. Diseño elegido

### 4.1 Los tres modos (y por qué son tres, no dos)

El waiver pasa a poder gestionarse dentro, **sin romper el white-label**:

| Modo | Qué hace la puerta | Para quién |
|---|---|---|
| `externo` | Consulta el sello, como hoy | Instalaciones con sistema propio (el actual) |
| `interno` | Consulta el registro firmado en este sistema | Lo que se construye aquí |
| `desactivado` | No comprueba nada | Sectores sin waiver |

Es una **ampliación** de `PuertaSettings::waiverCheckEnabled()`, no un sustituto: el interruptor
existente ya distingue dos de los tres estados.

### 4.2 Documento versionado inmutable

Una tabla de versiones de documento legal, con `slug · locale · cuerpo · hash · published_at`.

- **Publicar crea fila.** Editar una fila publicada está **prohibido y vigilado por test**.
- ⚠️ **El idioma es parte de la prueba.** El sitio sirve ES/EN/FR: lo que hay que conservar es el texto
  **en el idioma en que se le enseñó**, no «la versión 3». Si no, el snapshot no reconstruye lo que la
  persona vio.
- El panel gana una acción de **publicar** el borrador vigente del CMS como versión nueva. El texto
  se sigue redactando donde se redacta hoy (`Page`); lo que cambia es que **publicar es un acto** con
  fecha, no un guardado.

### 4.3 El registro de firma

Amplía lo que hoy es `consents`, o nace al lado — lo decide la revisión. Lo que **tiene que llevar**:

`titular · sujeto (el propio titular, o un menor a cargo) · versión de documento · accepted_at con zona
· ip · user_agent · hash canónico de la propia fila · prev_hash · retención_hasta`

- **Append-only**: no se actualiza, no se borra. Una corrección es una fila nueva.
- **`sujeto`** es lo que permite que la misma tabla cubra el waiver del titular y el de cada menor sin
  duplicar mecanismo (`menores-a-cargo.md` §4.3).
- **`hash` y `prev_hash` se calculan y guardan desde el PRIMER commit**, aunque hoy no se ancle nada.
  El porqué está en §4.7.

### 4.4 Cómo se firma (y qué se retiró)

- Texto **presentado en el propio flujo** del alta y de la zona de cuenta.
- Casilla **separada** de privacidad y condiciones, **desmarcada** por defecto. Hoy ya son `type`
  distintos en `consents` y eso está bien: el waiver es aceptación contractual de asunción de riesgo,
  **no es «consentimiento» en el sentido del RGPD**, y mezclarlos en una casilla es el error clásico.
- ⚠️ **El servidor solo emite la aceptación si la petición trae el identificador de la versión que él
  sirvió.** Sin esto, la firma no queda atada a ningún texto y todo lo demás es decorado.
- ⚠️ **RETIRADO**: obligar a abrir un modal para poder registrarse. Un booleano que envía el navegador
  no prueba nada; **nada prueba que lo leyó**; y bloquear un botón hasta abrir un modal rompe el flujo
  de teclado y de lector de pantalla. Lo que se prueba —y hay que decirlo con estas palabras en el
  PDF— es que **se le presentó** y que **lo aceptó explícitamente**.

### 4.5 El PDF

Se compone **del snapshot**, nunca del CMS. Lleva: texto firmado íntegro, identidad del firmante,
fecha/hora con zona, IP, user-agent, versión y hash. Si es de un menor, además sus datos y el adulto
responsable.

Accesible desde **dos sitios**: la ficha del usuario en el panel (permiso propio, auditado) y la zona
de privacidad del cajón, para el titular.

⚠️ **Es el informe legible de un registro, no un documento firmado digitalmente.** El valor probatorio
está en el registro —fila + versión inmutable + rastro de auditoría—; el PDF es su representación.
Prometer «PDF firmado» cuando es «PDF generado» es donde esto se rompe el día que haya que enseñarlo.

⚠️ **El dato lo declara el titular y no está verificado** (ni el nombre de un menor ni su fecha de
nacimiento). El PDF tiene que decirlo. Fingir lo contrario es peor que decirlo.

### 4.6 Conservación: restringir, no anonimizar

⚠️ **No se puede anonimizar un documento y que siga sirviendo de prueba.** Si se rompe el vínculo con
la persona, deja de demostrar que *esa* persona firmó.

Lo que se hace es **conservación con tratamiento restringido** (RGPD art. 17.3.e + art. 18):

1. El registro **se conserva vinculado**. `User::anonymize()` deja de borrarlo.
2. Pasa a **régimen restringido**: permiso propio, cada consulta auditada, y **fuera de toda superficie
   normal** — no aparece en listados, ni en el bloque de cuenta, ni en el export del art. 20.
3. ⚠️ **Tiene fecha de caducidad y se purga sola.** Sin esto no es conservación legítima, es
   acumulación. El patrón ya existe en el repo: `CookieConsentLog` usa `Prunable` con 24 meses y lo
   ejecuta `model:prune` en el scheduler.

❗ **`[PENDIENTE: owner]` — el PLAZO.** Sale de criterio jurídico, no técnico. Lo que el diseño exige
es que sea **un ajuste por instalación, no una constante**, porque en un menor el plazo puede empezar
a contar cuando cumple 18 y eso puede significar conservar quince años el waiver de un niño de tres.

### 4.7 eIDAS: hoy no, pero la puerta se deja abierta hoy

Lo que un sello de tiempo cualificado resuelve es el argumento *«ese registro lo generasteis después»*
o *«ese texto lo editasteis luego»*. **No refuerza la identidad**, y la identidad ya está cubierta por
otra vía: cuenta con correo verificado, IP, user-agent y —lo más fuerte— **un pago con tarjeta a
nombre de esa persona vinculado al pedido**.

**Con versiones inmutables + append-only + auditoría, este diseño ya está por encima de la norma del
sector.** El sello es una mejora, no un cimiento.

**Lo que sí hay que hacer HOY, y cuesta cero**: definir qué campos entran en el hash canónico y en qué
orden, calcularlo desde el primer commit, y dejar puesta la columna `prev_hash`. Si no se deja puesto,
anclar más tarde significa **sellar registros que ya no se puede demostrar que no se tocaron**, que es
no anclar nada. Misma doctrina que `#37` con el puerto de pasarela.

**Cuando toque**: cadena de hashes + un **sello RFC 3161 diario sobre la cabeza de la cadena**. Eso son
365 sellos al año en vez de uno por firma, con la misma garantía. Medido el 2026-08-24: el contenedor
trae **OpenSSL 3.0.13 con el subcomando `ts`** y la extensión `openssl` de PHP, así que **no hace falta
dependencia nueva** (`CONVENCIONES` §9 no se dispara). ⚠️ Verificar que `shell_exec` no está capado en
el hosting del cliente, y que la TSA elegida está en la **Lista de Confianza** de la UE.

### 4.8 Re-firma cuando cambia el texto

⚠️ **Es lo que muerde en producción y casi nadie lo diseña.** Hoy la puerta comprueba
`waiver_accepted_at !== null`. Con versiones pasa a ser «¿aceptó la vigente?», y el día que se edite el
texto **hay una cola entera de gente sin waiver válido**.

**Regla**: la puerta valida «tiene waiver de **alguna** versión publicada» y **señala si está
desactualizado, pero deja pasar**. La re-firma se pide en el siguiente momento natural —compra o
login—, **nunca en el mostrador**.

## 5. Impacto en invariantes

| ID | Impacto |
|---|---|
| **RGPD-01** | ⚠️ **SE MODIFICA.** `User::anonymize()` deja de borrar el registro de waiver: pasa a régimen restringido con plazo. El resto de la purga (guest_data, event_data, audit, tokens) **no se toca**. Decisión del owner en `DECISIONES #142` |
| **RGPD-04** | Se AMPLÍA: el PDF del waiver es una superficie con PII y va con `no-store`, como los dos PDF operativos |
| **RGPD-06** | Sin cambio, pero **se cita**: la firma no es una credencial y no entra en `revokeAllAccess()` |
| **SEC-06** | Sin cambio. El alta conserva sus cuatro capas de defensa |
| **SEC-09** | Se relaciona: las páginas legales no se pueden desactivar. Una versión **publicada** tampoco se puede borrar — es la misma familia de garantía |
| **PAY-14** | Aplica si nace alguna notificación nueva: obligatoriamente `ShouldQueue` |

## 6. Plan de verificación empírica

**Guardas ejecutables (las cuatro son el corazón del subsistema):**
1. **Una versión publicada no se puede modificar ni borrar** — mutación: intentarlo pone el test rojo.
2. **El PDF sale del snapshot, no del CMS** — mutación: editar la `Page` del waiver y comprobar que un
   PDF ya emitido **no cambia**.
3. **`User::anonymize()` conserva el registro de waiver** y **borra todo lo demás que ya borraba**
   — el segundo assert es tan importante como el primero.
4. **El servidor rechaza una aceptación sin el identificador de versión que sirvió.**

**Comprobación empírica (no basta la suite):**
- Generar un PDF real y **leerlo**, no solo comprobar que responde 200.
- Comprobar que el mismo waiver genera **el mismo contenido** dos veces (es lo que lo hace prueba).
- Recorrer el alta en navegador en **los tres idiomas** y verificar que el snapshot guarda el idioma
  correcto — es el error más fácil de cometer y el más difícil de ver.
- ⚠️ **Verificar la purga con el reloj congelado**, nunca con esperas: `SUITE-03`.

## 7. Revisión y decisión

Diseñado en sesión de arquitectura con el owner el 2026-08-24. Decisiones del owner recogidas en
`DECISIONES #142`.

✅ **REVISADA el 2026-08-25** por un segundo agente (`CONVENCIONES` §5). Los ocho hallazgos y el
veredicto están en **§8**: el diseño se sostiene y no hay que rehacerlo.

❗ **PENDIENTE del owner**, y ahora son DOS cosas:
1. El **plazo de conservación** (§4.6).
2. 🆕 **La redacción legal definitiva del waiver** (§8.1). El texto que hay hoy dice de sí mismo que
   es un borrador, y publicar una versión es irreversible por diseño. **La maquinaria se puede
   construir sin esto; el acto de publicar la v1, no.**

---

## 8. Revisión adversarial — 2026-08-25

> Hecha por un segundo agente, como exige `CONVENCIONES` §5. **Método**: no se leyó la spec, se
> intentó **refutar** cada afirmación suya sobre el repo ejecutando contra el código. Lo que sigue
> son solo los hallazgos; **todo lo que no aparece aquí se verificó y es cierto**.

### 8.0 Lo que aguantó (para no re-medirlo)

Verificado ejecutando el 2026-08-25: `consents` es exactamente `user_id · type · accepted_at · ip ·
version`, **sin user-agent, sin texto y sin hash** · `Consent::CURRENT_VERSION = '2026-05-23'` es una
constante de código · `PuertaSettings::waiverCheckEnabled()` existe con su fallback no destructivo ·
la `Page` del waiver está en `Page::PROTECTED_ACTIVE_SLUGS`, así que SEC-09 ya la protege ·
`CookieConsentLog` usa `Prunable` y lo dispara `model:prune` en `routes/console.php` · y §4.7 se
re-midió **hoy** dentro del contenedor: **OpenSSL 3.0.13 con subcomando `ts`** y la extensión
`openssl` de PHP presentes. No hace falta dependencia nueva.

### 8.1 ❗ BLOQUEANTE — el texto que se publicaría es, literalmente, un borrador

`database/seeders/LandingContentSeeder.php` sirve el waiver con esta cláusula, **en los tres
idiomas**:

    «Este texto es un borrador y será revisado por un asesor legal antes de su publicación.
     [PENDIENTE: redacción definitiva].»

Y §4.2 hace que **publicar sea irreversible**: una versión publicada no se edita ni se borra, y eso
es justo lo que le da valor. Publicar el primer snapshot sobre este texto **graba un borrador en la
cadena probatoria para siempre**, y §4.8 hace que quien lo firmó siga entrando con él («deja pasar»)
hasta que vuelva por su propio pie.

▶ **La maquinaria se puede construir hoy; PUBLICAR no.** Se separa en dos: el mecanismo (tabla,
inmutabilidad, PDF, régimen restringido) no depende del texto; el acto de publicar la v1 sí.
❗ **`[PENDIENTE: owner]` — la redacción legal definitiva.** §7 solo listaba el plazo de conservación;
esto es anterior y no estaba escrito en ninguna parte.

### 8.2 `anonymize()` borra la prueba en DOS sitios, no en uno

§1·3 y §4.6 nombran `$this->consents()->delete()`. Medido: `User::anonymize()` **también** hace
`'waiver_accepted_at' => null` (`app/Domain/Identity/Models/User.php`, en el `forceFill` final).

⚠️ **Y ése es el sello que la puerta lee de verdad**: `Livewire\Admin\Puerta\ValidarRegistro` decide
sobre `$user->waiver_accepted_at === null`, no sobre `consents`. Exceptuar solo el borrado de
consentimientos deja al titular anonimizado como **`REGISTERED_NO_WAIVER`** en el mostrador, con la
prueba conservada y la puerta diciendo que no existe.

### 8.3 `RGPD-01` no contiene la frase que la spec dice modificar

§5 dice «**RGPD-01 SE MODIFICA**». Medido contra `INVARIANTES.md`: la invariante enumera **cinco**
operaciones —`guest_data`/`event_data`, `revokeAllAccess()`, `password_reset_tokens`, redacción de
payloads legacy y nulificación de IP/user-agent de `audit_logs`— y **el borrado de consentimientos no
está entre ellas.** Tampoco el `roles()->detach()` ni la nulificación de las columnas legales.

▶ **Consecuencia práctica**: un agente que vaya a «modificar RGPD-01» no encontrará qué cambiar, y el
gate documental no lo detecta porque valida estructura, no contenido (`DEUDA.md`, fila alta).
▶ **El orden correcto son dos pasos**: (1) **añadir** a `RGPD-01` lo que el código ya hace y la
invariante calla —es un desfase preexistente, no de esta spec—; (2) **entonces** restringir la parte
del waiver. Hacer solo (2) deja la invariante describiendo mal el código en dos direcciones.

### 8.4 Hay DOS escritores de consentimientos — y el segundo es el mostrador

§1 nombra solo `Identity\Services\SelfSignup`. Medido: **`Identity\Services\CustomerRegistrar`**
—el alta **presencial** que hace un operador desde el panel— también crea una fila de `consents`
(`type = 'privacy'`, con `Consent::CURRENT_VERSION`), con este comentario dentro: *«el operador
confirma haber informado al cliente en persona»*.

Eso choca con dos reglas de la spec a la vez:
- **§4.4** exige que «el servidor solo emite la aceptación si la petición trae el identificador de la
  versión que él sirvió». En el mostrador **no hay tal petición**: no hay navegador del cliente.
- **§4.8** dice que la re-firma se pide en el siguiente momento natural, «**nunca en el mostrador**».
  Pero `CustomerRegistrar` **es** el mostrador.

✅ **[DECIDIDO owner, 2026-08-25] — el alta presencial produce una firma DECLARADA POR EL OPERADOR**,
igual que hace hoy con `privacy`. Con dos condiciones que la decisión lleva dentro:
1. El registro guarda **quién la declaró** (`created_by`), y no finge ser una firma del titular.
2. ⚠️ **El PDF tiene que decirlo con todas las letras.** Es sustancialmente más débil que el resto
   del diseño —dice «el operador declara que el cliente aceptó»— y §4.5 ya fijó la doctrina para
   esto: *«el dato lo declara el titular y no está verificado… fingir lo contrario es peor que
   decirlo»*. Aquí ni siquiera lo declara el titular.

### 8.5 La cadena de hashes no tiene punto de serialización

§4.3 pide `hash` **y `prev_hash`** append-only «desde el PRIMER commit», y §4.7 explica bien por qué.
Lo que no aparece en toda la spec es la palabra **concurrencia**.

⚠️ Dos firmas simultáneas que lean la misma cabeza de cadena escriben **dos filas con el mismo
`prev_hash`**: la cadena se **bifurca en silencio**, y una cadena bifurcada no demuestra nada — que
es exactamente la propiedad por la que se construye. No falla, no avisa, y se descubre el día que hay
que enseñarla.

▶ Este repo ya tiene doctrina para esto (`AFORO-01`: el lock es la primera sentencia de la
transacción) y ya sabe lo que pasa cuando falta (`#147`: 8 fiestas donde cabía 1, con el
`lockForUpdate()` retirado). **Un sábado por la tarde las firmas son concurrentes.**

▶ **Recomendación del revisor: cadena POR TITULAR, no global.** Serializa sin lock global, la
contención cae a cero, y además es la unidad que se audita de verdad («enséñame la cadena de esta
persona»). Una cadena global obliga a un lock en el camino del alta, que es superficie de registro
masivo. **Elegirlo ahora es barato; migrar una cadena después es rehacerla.**

### 8.6 Dónde vive el registro: la spec lo dejó a esta revisión

§4.3 dice «Amplía lo que hoy es `consents`, o nace al lado — **lo decide la revisión**».

▶ **Recomendación: tabla propia, no ampliar `consents`.** Tres razones medidas:
1. **`consents.user_id` es `->constrained()->cascadeOnDelete()`.** Hoy no muerde —verificado: el
   recurso de usuarios del panel **no tiene acción de borrado**, solo anonimizar— pero una tabla cuyo
   propósito es sobrevivir no debe colgar de un CASCADE. En la tabla nueva, `RESTRICT`.
2. `consents` la escriben dos servicios con reglas distintas (§8.4) y la lee el bloque de privacidad
   del cajón. El registro probatorio debe estar **fuera de toda superficie normal** (§4.6): meterlo
   dentro obliga a filtrar en cada consumidor, y basta olvidarse en uno.
3. La forma es distinta: `sujeto`, `prev_hash`, `retención_hasta` y `created_by` no tienen sentido en
   una fila de `marketing`.

### 8.7 Precisiones menores de §1 (no cambian el diseño)

- El alta escribe hasta **tres** tipos de consentimiento, no dos: `privacy`, `terms` y **`marketing`**
  si el usuario opta.
- `waiver_accepted_at` lo leen **dos** superficies, no solo la puerta: también
  `Filament\Resources\Orders\Schemas\OrderInfolist`, que pinta el estado del waiver en la ficha del
  pedido. Migrar a un registro versionado toca las dos.

### 8.8 Veredicto

**El diseño se sostiene y no hay que rehacerlo.** De los ocho hallazgos, uno es bloqueante y externo
al diseño (§8.1, el texto legal), dos son piezas que faltan y hay que decidir antes de la primera
línea (§8.5 la serialización, §8.6 la tabla), uno es una decisión de producto ya resuelta (§8.4), y
el resto son correcciones de inventario que ahorran descubrirlas tarde.

---

## 9. Ejecución — tanda 1, el NÚCLEO (2026-08-25, `DECISIONES #160`)

> Lo que hay en el árbol, dicho sin optimismo. Tres tandas: **1 · el núcleo** (esta) · **2 · el panel**
> (PDF del snapshot, ficha del usuario con permiso propio y consulta auditada, alta presencial
> declarada desde `CustomerRegistrar`) · **3 · el cliente** (API `legal/waiver` + `me/waiver`, casilla
> en el alta, zona de privacidad del cajón, re-firma en el siguiente momento natural). **No hay
> ninguna versión publicada** en ninguna instalación: §8.1 sigue vigente y ahora es mecanismo (§9.2).

### 9.1 Qué existe (todo en Identity; la capa de entrega solo lo consume)

| Pieza | Dónde | Qué hace |
|---|---|---|
| `legal_document_versions` · `LegalDocumentVersion` | `app/Domain/Identity/Models/LegalDocumentVersion.php` | Una fila por (slug, idioma, versión), **inmutable** (`updating`/`deleting` lanzan), con `body_hash`. Sin `updated_at`: no hay ni columna con la que cambiar |
| `waiver_signatures` · `WaiverSignature` | `app/Domain/Identity/Models/WaiverSignature.php` | El registro probatorio, **append-only** + `Prunable`. `hash` canónico (`HASHED_FIELDS`, `CANONICAL_VERSION`), `prev_hash` por titular, `document_hash`, `accepted_tz`, `channel`, `declared_by_user_id` |
| `LegalDocumentPublisher` | `app/Domain/Identity/Services/LegalDocumentPublisher.php` | Publicar = crear la versión N+1 en cada idioma con cuerpo; **rechaza `[PENDIENTE…]`** en cualquier caja; audita `legal.version_published` sin el texto |
| `LegalDocuments` | `app/Domain/Identity/Services/LegalDocuments.php` | `current(slug, locale)`: la vigente en el idioma pedido, con respaldo → `es` (el mismo que aplica la web al pintar) |
| `WaiverSigner` · `WaiverSignatureRequest` | `app/Domain/Identity/Services/WaiverSigner.php` · `…/WaiverSignatureRequest.php` | El ÚNICO escritor: lock de la fila del titular como PRIMERA sentencia, `prev_hash` de su última firma, la fila visible de `consents` (`v1·es`) y el sello — los dos últimos solo si el sujeto es el titular |
| `WaiverStatus` | `app/Domain/Identity/Services/WaiverStatus.php` | «¿Tiene waiver, y de qué versión?» según el MODO: `externo` → sello · `interno` → registro (+ `isOutdated()`) · `desactivado` → no hay pregunta |
| `WaiverSettings` | `app/Domain/Identity/Services/WaiverSettings.php` | `waiver.mode` (hereda `puerta.waiver_check_enabled`: '0' → desactivado, si no → externo) · `waiver.retention_months` (vacío/inválido → `null` → NO se poda) |
| `WaiverChain` | `app/Domain/Identity/Services/WaiverChain.php` | Verifica la cadena de un titular: cada fila da su hash y enlaza con la anterior |
| `waiver:verify-chain` | `app/Console/Commands/VerifyWaiverChainConcurrency.php` | N firmas simultáneas del MISMO titular (`pcntl_fork`, MySQL) → una cadena lineal. Se limpia solo (`DB::table`) |
| La puerta | `app/Livewire/Admin/Puerta/ValidarRegistro.php` + su vista | Lee `WaiverStatus`; en interno señala «versión anterior» en ámbar y **deja pasar** (§4.8) |
| Ajustes | `app/Filament/Pages/Settings.php` | `Select` de modo (hidratado con el EFECTIVO para que Guardar no cambie conducta) + plazo; el toggle de #216 ya no se edita: `save()` lo escribe como espejo |
| Publicar | `app/Filament/Resources/Pages/Pages/EditPage.php` | Acción «Publicar versión firmable», solo en `waiver` y con `content.manage`; congela lo GUARDADO con los tokens fiscales resueltos |
| `anonymize()` | `app/Domain/Identity/Models/User.php` | **No toca** `waiver_signatures`; sigue nulificando el sello (presentación) y borrando `consents` |
| Poda | `routes/console.php` | `WaiverSignature` en el mismo `model:prune` diario que `CookieConsentLog` (una tarea, no dos: la salud del despliegue cuenta tareas) |
| Go-live | `app/Console/Commands/PurgeCustomerData.php` | Borra firmas ANTES que usuarios (`RESTRICT`), por `DB::table` |

Además: dos alias morph (`legal_document_version`, `waiver_signature`), tres acciones de auditoría
(`legal.version_published`, `waiver.signed`, `waiver.declared`) y el bloque `admin.waiver.*` de
`lang/es/admin.php`. Recuentos del gate: **32 modelos · 76 migraciones**.

### 9.2 Las TRES cosas en que la ejecución se apartó del cuerpo, y por qué

1. **No hay columna `retención_hasta`** (§4.3 la listaba). El plazo es un ajuste por instalación y
   **retroactivo**: una columna que hubiera que reescribir cuando el owner lo fije —o lo cambie— no
   cabe en una fila que por definición no se actualiza. El plazo se aplica **al podar**
   (`WaiverSignature::prunable()`), y sin plazo la consulta es `1 = 0`: no se poda nada.
2. **Las dos tablas viven en Identity, no en Content**, aunque el texto se redacta en Content
   (`Page`). Medido contra `ModuleBoundariesTest`: Identity puede mirar a Platform y a los contratos
   de Booking/Payments, **no a Content**. La versión es «lo que el titular aceptó» —Identity—, y el
   texto llega a `LegalDocumentPublisher` como datos planos **ya interpolados** desde la capa de
   entrega (`EditPage`, que sí puede mirar a los dos). Consecuencia buena: el snapshot es lo que se
   ENSEÑÓ, con los datos fiscales resueltos, no la plantilla con tokens.
3. **El sello `waiver_accepted_at` es presentación, no prueba** — y así se resuelve §8.2 sin
   exceptuar nada en `anonymize()`: `WaiverSigner` lo sigue escribiendo (lo leen el infolist del
   pedido y la puerta en modo externo), `anonymize()` lo sigue nulificando, y en modo **interno** la
   puerta **no lo lee**: lee el registro. ⚠️ Consecuencia explícita, y aseverada
   (`WaiverGateTest`): al pasar una instalación de externo a interno, **un sello sin registro no
   cuenta** — quien no firmó aquí tiene que firmar. Es lo que significa «manda el registro».

Y dos recomendaciones de la revisión que pasaron a ser diseño: **cadena por titular** (§8.5), con el
`lockForUpdate()` de su fila de `users` como primera sentencia de la transacción; y **tabla propia**
con `user_id` RESTRICT (§8.6). §8.1 pasó a ser **mecanismo**: `[pendiente` en minúsculas —caza el
marcador del seeder y el `[pendiente]` neutro que deja `LegalIdentity::interpolate` sin datos
fiscales— rechaza la publicación entera, sin dejar ningún idioma a medias.

### 9.3 Lo medido

- **Suite**: 47 casos nuevos en `tests/Feature/Waiver/` (6 ficheros); todos los existentes que tocan
  lo mismo, verdes (`ValidarRegistroTest`, `LandingTextsAndSocialTest` —su toggle pasó a ser el
  Select del modo—, `PrivacyTest`, `AnonymizeUserActionTest`, `MorphMapTest`, `AuditActionCatalogTest`,
  `ModuleBoundariesTest`, `PageResourceTest`, `CriticalPathGateTest`…).
- **`waiver:verify-chain` sobre MySQL real** (BD de desarrollo migrada): `--workers=8` → 9 filas,
  0 `prev_hash` repetidos, cadena OK · `--workers=16` → 17 filas, 0 repetidos, OK. Restos en BD tras
  limpiar: 0 firmas, 0 versiones, 0 titulares de prueba.
- ❗❗ **Y el instrumento se vio FALLAR**: con el `lockForUpdate()` retirado de `WaiverSigner`, tres
  ejecuciones de 16 firmas dieron **3, 11 y 17 filas con 1, 9 y 15 `prev_hash` repetidos** y la
  cadena ROTA las tres veces. Es la bifurcación silenciosa de §8.5, cazada. Fichero restaurado y
  comprobado por `cmp`.
- **Cinco mutaciones, las cinco muerden** (cada una contra su test, con el control en verde y los
  ficheros restaurados por `cmp`): sin guarda de borrador → `test_a_text_with_a_draft_marker_is_refused`
  cae · poda sin plazo → `test_nothing_is_pruned_while_the_retention_period_is_not_set` cae ·
  `anonymize()` borrando la prueba → `test_anonymize_keeps_the_waiver_signature…` cae · firmas sin
  `prev_hash` → `test_the_chain_links…` cae · versión publicada borrable →
  `test_a_published_version_cannot_be_deleted` cae.
- **La serialización canónica está FIJADA como literal** en `WaiverSignatureChainTest`: cambiar un
  campo, su orden o su formato pone el test en rojo — y tiene que ponerlo, porque lo ya firmado
  dejaría de verificar (§4.7).

### 9.4 Lo que la ejecución enseñó (trampas para las tandas 2 y 3)

- **La única salida legítima de una firma es la poda, y solo por el camino fila a fila**: la guarda
  de `deleting` la levanta `pruning()` justo antes de `delete()`. Pasar el modelo a `MassPrunable`
  la saltaría entera. Las dos limpiezas que borran por `DB::table` (go-live y verificador) lo dicen en
  su comentario; si aparece una tercera, tiene que decirlo también.
- **La poda mueve el inicio de la cadena**: al irse la firma más antigua, la siguiente apunta a un
  hash que ya no existe. `WaiverChain::verify()` no comprueba el enlace de la PRIMERA fila que
  queda; sí el contenido de todas. Un auditor que pida «la cadena entera» tiene que saber que el
  plazo la recorta por delante.
- **`Setting` invalida su memo al guardar** (`saved`/`deleted` → `flushMemo()`), así que leer →
  escribir → releer funciona dentro de un mismo test. `tests/TestCase.php` lo vacía en `setUp()`.
- **El verificador publica una v1 de prueba si no hay ninguna** y la retira al limpiar por
  `DB::table` (la versión también es inmutable). Sobre una instalación con versiones publicadas usa
  la vigente y no crea ninguna.
- **Un `[x]` bajo Fase 6 obliga a poner 🟦 en su cabecera** (`docs-check`, coherencia de marcadores):
  el tracker ya lo lleva.

### 9.5 Lo que queda ABIERTO tras la tanda 1

- ❗ `[PENDIENTE: owner]` **el plazo** (§4.6): hoy `waiver.retention_months` vacío = no se poda nada.
- ❗ `[PENDIENTE: owner]` **el texto definitivo** (§8.1): la maquinaria rechaza publicar el borrador.
- **`RGPD-01`, paso (2)** —la restricción del waiver— se escribe **sobre la corrección (1) del agente
  B** (`ESTADO`, reparto del 2026-08-25), citando `WaiverRetentionTest`. **`RGPD-04`** se amplía en la
  tanda 2 con el PDF.
- Tanda 2 y tanda 3, en el orden de arriba. La casilla del alta (§4.4) y la re-firma «en el siguiente
  momento natural» (§4.8) no existen todavía: hoy solo se puede firmar por servicio y por el
  verificador.
