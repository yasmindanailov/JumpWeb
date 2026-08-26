# [SPEC] El waiver con valor probatorio

> Estado: 🟦 **CÓDIGO COMPLETO** — revisada (**§8**), las **cuatro tandas HECHAS** (§9: `#160` ·
> `#161` · `#163` · `#166`), **el subsistema revisado de forma adversarial (§10, `DECISIONES #169`)**,
> **la TANDA 4 que esa revisión exigía HECHA y cerrada (§9.11, `#171`→`#180`)**, **la revisión de la
> propia tanda 4 aplicada (§9.12, `#183`)** y **el guion recorrido en headless DOS veces** (§9.10 con la
> conducta de entonces; §9.12 con la definitiva: **111/111 ✓, 0 desviaciones**); el ✅ final espera al owner —su ojo en
> navegador, el texto definitivo y el plazo—; **las decisiones de §7 y §10.11 están tomadas y ejecutadas** ·
> Última actualización: 2026-08-26 ·
> Verificado contra código: 2026-08-24 (consents, SelfSignup, Page, User::anonymize, SecurityHeaders),
> **re-verificado el 2026-08-25 por la revisión** (§8.0) y **el 2026-08-26 por la del subsistema** (§10.0) ·
> Decisión asociada: `DECISIONES #142`, revisiones en `DECISIONES #156` (spec), **`#169`** (subsistema) y **`#183`** (tanda 4) ·
> Se invalida si: cambia el modo de gestión del waiver, o el owner fija el plazo de conservación.
>
> ❗❗ **Si vas a tocar código, LEE §10.11 y §9.10 PRIMERO**: lo que la revisión del subsistema exige
> antes del ✅ y el defecto que el guion destapó (el alta suelta con anti-bot). **Después §9**: dice qué
> existe ya, en qué TRES cosas la ejecución se apartó del cuerpo (y por qué) y qué quedó medido.
> **Después §8, antes que el cuerpo.** ⚠️ Esta cabecera y la intro de §9 estuvieron **un día
> desactualizadas** («3b pendiente», «ninguna versión publicada») — lo cazó §10 (DOC-3).
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
- ✅ `[DECIDIDO owner, 2026-08-26]` **La identidad del firmante viaja EN la firma** (`holder_name`,
  `holder_email`, dentro del hash): tras `User::anonymize()` la cuenta ya no identifica a nadie, y una
  prueba que apunte a «Cliente eliminado» no prueba quién firmó. Es PII conservada a propósito bajo el
  régimen restringido de §4.6 y solo la purga el plazo (§9.6, `DECISIONES #161`).

### 4.4 Cómo se firma (y qué se retiró)

- Texto **presentado en el propio flujo** del alta y de la zona de cuenta.
- Casilla **separada** de privacidad y condiciones, **desmarcada** por defecto. Hoy ya son `type`
  distintos en `consents` y eso está bien: el waiver es aceptación contractual de asunción de riesgo,
  **no es «consentimiento» en el sentido del RGPD**, y mezclarlos en una casilla es el error clásico.
  ⚠️ `[DECIDIDO owner, 2026-08-26]` (§7·7): en modo **interno** la casilla es **OBLIGATORIA** para
  crear la cuenta —hasta entonces era opt-in (`#163`, `#166`)—; sigue desmarcada por defecto. La
  firma se registra al verificar el correo (§7·5), no al crear la cuenta.
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
⚠️ **Corregido por §10.2 (2026-08-26)**: tal y como está el código, **ninguna de las tres primeras
vías es lo que dice**: se firma **antes** de verificar el correo (y la fila no guarda si estaba
verificado), la IP la fija el cliente con `X-Forwarded-For` mientras `trustProxies` sea `'*'`, y el
canal lo fija con una cabecera. La vía del pago sigue en pie. Léelo antes de apoyar nada en esta frase.

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

✅ **REVISADO EL SUBSISTEMA ENTERO el 2026-08-26** (`CONVENCIONES §5`, sobre el código completo):
hallazgos y veredicto en **§10**; el guion de navegador, en headless, en **§9.10**.

❗ **PENDIENTE del owner** (la lista completa y su porqué, en §10.11):
1. El **plazo de conservación** (§4.6). ⚠️ Precisión medida: cuenta **desde la firma** (`accepted_at`,
   mín. 1 · máx. 600 meses) y afecta también a cuentas vivas — al vencer, en interno el titular vuelve
   a «sin firmar». Sin valor, nada se poda.
2. **La redacción legal definitiva del waiver** (§8.1). El texto que hay hoy dice de sí mismo que
   es un borrador, y publicar una versión es irreversible por diseño. **La maquinaria se puede
   construir sin esto; el acto de publicar la v1, no.** La guarda de borrador caza los TRES marcadores
   —en NFC y con blanco tras el corchete— y avisa de «borrador/draft/brouillon» (§10.4, `#174` + `#183`).
3. ✅ **El aviso de re-firma en el paso de pagar** (§9.9·1) — `[DECIDIDO owner, 2026-08-26]`: **NO se
   construye; basta el aviso del índice** (coherente con §4.8: no es condición de compra). El chunk
   se queda en 225,72 de 226 KiB. Cerrado.
4. ✅ **La casilla del waiver en el alta MANUAL del panel** (§10.1) — `[DECIDIDO owner, 2026-08-26]`:
   **se construye**, con el texto vigente a la vista; **sin marcarla, no hay firma** (condición nueva
   en `CustomerRegistrar`, con su test). ✅ **HECHO** (`#178`, §9.11): `Placeholder` con las secciones
   vigentes + `Checkbox waiver_declared`, solo en interno con versión; el flag viaja también por el
   camino del cliente sin email. ⚠️ El modal es un `wire:partial`: su presencia no se asevera con
   `assertSee`, se prueba por sus efectos.
5. ✅ **¿Se firma con correo sin verificar?** (§10.2·3) — `[DECIDIDO owner, 2026-08-26]`: **se exige
   correo verificado para firmar**. El alta con casilla **deja de firmar al crear la cuenta y firma al
   VERIFICAR** (la aceptación se conserva hasta entonces); `POST /me/waiver` exige cuenta verificada.
   ⚠️ Cambia §9.8 (fila «El alta») y `AuthRegistrationTest::test_accepting_the_waiver_at_signup…`.
   ✅ **HECHO** (`#179`, §9.11): `WaiverSigner` exige `email_verified_at` sobre la fila BLOQUEADA salvo
   firma declarada; el alta guarda la aceptación en `users.waiver_pending_document_id` +
   `waiver_pending_channel` y `SignPendingWaiverOnVerification` (evento `Verified`) la convierte en
   firma si el texto sigue vigente —si no, la descarta—; `POST /me/waiver` sin correo verificado →
   `409 waiver_email_unverified`. `anonymize()` purga las dos columnas y el censo las declara.
6. 🆕 **El ✅ en navegador** (§9.10): el guion está recorrido en headless; falta su ojo — ⚠️ con el
   anti-bot apagado o el defecto de §9.10 arreglado, porque con Turnstile activo el alta suelta no
   termina. `[DECIDIDO owner, 2026-08-26]`: **ese defecto lo arregla el carril A, lo primero de su
   próxima sesión, y la pasada del owner va DESPUÉS, en local y con el anti-bot ENCENDIDO** — es la
   única que valida la configuración real. ⚠️ **Staging no sirve para esta prueba**, medido: sirve
   `7776370`, **21 commits por detrás y sin ninguna tanda del waiver**; tiene claves de Turnstile
   (el alta moriría igual); y publicar allí una versión es irreversible sobre un texto borrador.
7. ✅ 🆕 **La casilla del waiver del alta es OBLIGATORIA en modo interno** — `[DECIDIDO owner,
   2026-08-26]`, planteado con su coste (§4.4 la dejaba opt-in a propósito: cuenta ≠ firma, la firma
   se exige en la puerta). Sin marcarla, **no hay cuenta** (422 sobre el campo, como privacidad y
   condiciones); sigue **desmarcada** por defecto (§4.4 no cambia en eso). Coste asumido: quien crea
   cuenta para comprar sin saltar también acepta. ⚠️ Encaja con §7·5: la aceptación se captura en el
   alta y **la firma se registra al VERIFICAR el correo**. Cambia §9.8 (fila «El alta»), `register.js`
   (`accept_waiver` deja de poder ir `false` en interno), `AuthRegistrationTest` y el rótulo del 422
   (`api.register.*`). ✅ **HECHO** (`#178`, §9.11): regla `accepted` sobre `accept_waiver` cuando `WaiverSettings::isInternal()` y hay versión en el idioma de la petición —exactamente cuando `GET /legal/waiver` sirve un documento—; mensaje `api.register.waiver_required` (es/en/fr) bajo `accept_waiver`, que el cajón pinta en el mismo hueco que el del texto (`FIELD_ORDER` lo conoce).

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

## 9. Ejecución — las CUATRO tandas HECHAS: 1 (NÚCLEO, `#160`), 2 (PANEL, `#161`), 3a (API, `#163`) y 3b (CAJÓN, `#166`) — el guion recorrido en headless (§9.10) — y la TANDA 4 con su revisión (§9.11, §9.12)

> Lo que hay en el árbol, dicho sin optimismo. ✅ **1 · el núcleo** (§9.1–§9.4, 2026-08-25) · ✅ **2 ·
> el panel** (§9.6–§9.7, 2026-08-26: PDF del snapshot, registro en la ficha con permiso propio y
> consulta auditada, alta presencial declarada, y **la identidad del firmante EN la firma**) · ✅ **3a ·
> el cliente por API** (§9.8, 2026-08-26: `GET /legal/waiver`, `GET|POST /me/waiver`, el PDF propio,
> la casilla del alta y `waiver` en el contexto de cuenta) · ✅ **3b · el cajón** (§9.9, 2026-08-26:
> la casilla del alta, la tarjeta de Privacidad y el aviso del índice) · ✅ **el guion §5.nonies en
> headless** (§9.10, 2026-08-26). **No hay ninguna versión publicada en ninguna instalación REAL**
> (staging ni producción): §8.1 sigue vigente y es mecanismo (§9.2) — ⚠️ con el alcance que §10.4 le
> mide. En la BD local del carril A sí hay versiones (v1→v3), publicadas para el guion.
> ⚠️ §9.1 y §9.3 cuentan la **tanda 1** (47 casos en 6 ficheros, 3 acciones de auditoría); tras las
> cuatro son **72 casos en 9 ficheros y 5 acciones** (§10.7, NUC-12). · ✅ **4 · el código acotado que exigió la revisión** (§9.11, `#171`→`#180`) · ✅ **la revisión
> adversarial de esa tanda, aplicada** (§9.12, `#183`: dos altas, cinco medias, diecisiete bajas —24 confirmadas,
> 0 refutadas— y el guion re-recorrido con la conducta definitiva, **111/111 ✓, 0 desviaciones**).

### 9.1 Qué existe (todo en Identity; la capa de entrega solo lo consume)

| Pieza | Dónde | Qué hace |
|---|---|---|
| `legal_document_versions` · `LegalDocumentVersion` | `app/Domain/Identity/Models/LegalDocumentVersion.php` | Una fila por (slug, idioma, versión), **inmutable** (`updating`/`deleting` lanzan), con `body_hash`. Sin `updated_at`: no hay ni columna con la que cambiar |
| `waiver_signatures` · `WaiverSignature` | `app/Domain/Identity/Models/WaiverSignature.php` | El registro probatorio, **append-only** + `Prunable`. `hash` canónico (`HASHED_FIELDS`, `CANONICAL_VERSION`), `prev_hash` por titular, `document_hash`, `accepted_tz`, `channel`, `declared_by_user_id` |
| `LegalDocumentPublisher` | `app/Domain/Identity/Services/LegalDocumentPublisher.php` | Publicar = crear la versión N+1 en cada idioma con cuerpo; **rechaza los TRES marcadores** (`[PENDIENTE…]`, `[PENDING…]`, `[À COMPLÉTER…]`, y el `[pendiente]` neutro — desde `#174`, §10.4) en cualquier caja; `mentionsDraftWords()` señala «borrador/draft/brouillon» para el AVISO del modal de publicar; audita `legal.version_published` sin el texto |
| `LegalDocuments` | `app/Domain/Identity/Services/LegalDocuments.php` | `current(slug, locale)`: la vigente en el idioma pedido, con respaldo → `es` (el mismo que aplica la web al pintar) |
| `WaiverSigner` · `WaiverSignatureRequest` | `app/Domain/Identity/Services/WaiverSigner.php` · `…/WaiverSignatureRequest.php` | El ÚNICO escritor: lock de la fila del titular como PRIMERA sentencia, `prev_hash` de su última firma, la fila visible de `consents` (`v1·es`) y el sello — los dos últimos solo si el sujeto es el titular. **Idempotente por versión y sujeto, DENTRO del lock** (`#174`, §10.2·4): la misma versión —en cualquier idioma— devuelve la fila que hay sin escribir nada. ⚠️ Está en el `CRITICAL_RE`: tocarlo exige `VERIFY_CONC=1` tras `waiver:verify-chain` |
| `WaiverStatus` | `app/Domain/Identity/Services/WaiverStatus.php` | «¿Tiene waiver, y de qué versión?» según el MODO: `externo` → sello · `interno` → registro (+ `isOutdated()`) · `desactivado` → no hay pregunta |
| `WaiverSettings` | `app/Domain/Identity/Services/WaiverSettings.php` | `waiver.mode` (hereda `puerta.waiver_check_enabled`: '0' → desactivado, si no → externo) · `waiver.retention_months` (vacío/inválido → `null` → NO se poda) |
| `WaiverChain` | `app/Domain/Identity/Services/WaiverChain.php` | Verifica la cadena de un titular: cada fila da su hash y enlaza con la anterior |
| `waiver:verify-chain` | `app/Console/Commands/VerifyWaiverChainConcurrency.php` | N firmas simultáneas del MISMO titular (`pcntl_fork`, MySQL) → una cadena lineal. Se limpia solo (`DB::table`). ⚠️ Desde `#174` cada proceso firma como un MENOR distinto (`subject_id` = i): re-firmar la misma versión por el mismo sujeto es idempotente y N firmas iguales darían una fila |
| La puerta | `app/Livewire/Admin/Puerta/ValidarRegistro.php` + su vista | Lee `WaiverStatus`; en interno señala «versión anterior» en ámbar y **deja pasar** (§4.8) |
| Ajustes | `app/Filament/Pages/Settings.php` | `Select` de modo (hidratado con el EFECTIVO para que Guardar no cambie conducta) + plazo; el toggle de #216 ya no se edita: `save()` lo escribe como espejo |
| Publicar | `app/Filament/Resources/Pages/Pages/EditPage.php` | Acción «Publicar versión firmable», solo en `waiver` y con `content.manage`; congela lo GUARDADO con los tokens fiscales resueltos |
| `anonymize()` | `app/Domain/Identity/Models/User.php` | **No toca** `waiver_signatures`; sigue nulificando el sello (presentación) y borrando `consents` |
| Poda | `routes/console.php` | `WaiverSignature` en el mismo `model:prune` diario que `CookieConsentLog` (una tarea, no dos: la salud del despliegue cuenta tareas) |
| Go-live | `app/Console/Commands/PurgeCustomerData.php` | Borra firmas ANTES que usuarios (`RESTRICT`), por `DB::table` |

Además: dos alias morph (`legal_document_version`, `waiver_signature`), tres acciones de auditoría
(`legal.version_published`, `waiver.signed`, `waiver.declared`) y el bloque `admin.waiver.*` de
`lang/es/admin.php`. Recuentos del gate tras las dos tandas: **32 modelos · 79 migraciones**.

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

### 9.5 Lo que queda ABIERTO tras las tandas 1 y 2

- ❗ `[PENDIENTE: owner]` **el plazo** (§4.6): hoy `waiver.retention_months` vacío = no se poda nada.
- ❗ `[PENDIENTE: owner]` **el texto definitivo** (§8.1): la maquinaria rechaza publicar el borrador.
- ✅ `RGPD-01` lleva el paso (2) —escrito sobre la corrección (1) de `#159`— y `RGPD-04` está ampliada
  con el PDF; las dos citan su guarda (`WaiverRetentionTest`, `WaiverProofPdfTest`).
- **Tanda 3 (el cliente)**: la casilla del alta (§4.4), `GET /api/v1/legal/waiver` (la vigente en el
  idioma del cliente, con su id), `POST /api/v1/me/waiver` (aceptar con el id que el servidor sirvió
  — §4.4: sin él, no hay firma), `GET /api/v1/me/waiver` (estado + PDF propio), la zona de
  privacidad del cajón y la re-firma «en el siguiente momento natural» (§4.8). Hoy solo se firma por
  servicio, desde el mostrador (§9.6) y por el verificador.

### 9.6 Ejecución — tanda 2, el PANEL (2026-08-26, `DECISIONES #161`)

**La decisión que la abrió** (`[DECIDIDO owner, 2026-08-26]`): al diseñar el PDF apareció un hueco
del cuerpo — tras `User::anonymize()` la fila de `users` dice «Cliente eliminado», así que una prueba
que solo apuntara al `user_id` **dejaba de identificar a la persona**, y `#142` la quiere «conservada
vinculada, no anonimizada». El owner eligió **nombre + email** (no el teléfono). Entran en la firma
(`holder_name`, `holder_email`) **y en el hash**, como esquema canónico **v2**; cada fila guarda
`canonical_version` y se verifica con la suya, así que lo firmado con v1 seguiría verificando (no
había ninguna firma real). Las dos serializaciones están fijadas como literal en
`WaiverSignatureChainTest`.

| Pieza | Dónde | Qué hace |
|---|---|---|
| Migración | `database/migrations/2026_08_26_090000_add_holder_identity_to_waiver_signatures.php` | `holder_name` · `holder_email` · `canonical_version` (default 1 para lo anterior) + el permiso `waiver.view` insertado idempotente |
| `WaiverProof` | `app/Domain/Identity/Services/WaiverProof.php` | Presentador del PDF: texto íntegro del snapshot, identidad copiada (cae a la cuenta solo en filas v1), fecha en su zona y en UTC, ip, UA, canal, operador declarante, hashes, `integrityOk()` (fila + versión + enlace) y la línea de conservación. **Determinista**: sin «generado el» |
| `WaiverProofController` + ruta | `app/Http/Controllers/Admin/WaiverProofController.php` · `routes/web.php` | `GET /admin/usuarios/{user}/waiver/{signature}/pdf`: `web+auth+staff_or_admin` + `throttle:30,1` + `no-store` · permiso **`waiver.view`** · IDOR (la firma es del usuario de la URL) · audita `waiver.proof_downloaded` · **idioma = el del texto firmado** |
| La vista | `resources/views/pdf/waiver-proof.blade.php` · `lang/{es,en,fr}/waiver.php` | dompdf; las etiquetas en los TRES idiomas del cliente (`#154`), y dicen cosas distintas (guarda). La firma declarada lleva su recuadro: «sustancialmente más débil» (§8.4) |
| La ficha del usuario | `app/Filament/Resources/Users/Pages/ViewUser.php` · `resources/views/filament/users/partials/waiver-proof.blade.php` | **Acción** «Registro del waiver», no sección: visible solo con `waiver.view` (también sobre cuentas anonimizadas), `mountUsing` audita `waiver.proof_viewed`, el modal lista estado, firmas, integridad y el PDF de cada una |
| Permiso | `database/seeders/PermissionSeeder.php` · `app/Domain/Identity/Services/PermissionCatalog.php` · `lang/{es,zh_CN}/admin.php` | `waiver.view` en «gestión», **no** entre los del staff por defecto; etiqueta en es y zh_CN (`AccessI18nParityTest`) |
| Alta presencial | `app/Domain/Identity/Services/CustomerRegistrar.php` | En modo **interno**, con versión publicada y operador con sesión → `WaiverSigner` con `declaredAtCounter()`; sin cualquiera de las tres, no hay firma (y la puerta dirá «sin waiver») |
| Auditoría | `app/Domain/Platform/Models/AuditLog.php` | `waiver.proof_viewed` · `waiver.proof_downloaded` |

**Lo medido**: suite **+25** (`WaiverProofPdfTest` 14 · `WaiverProofActionTest` 6 ·
`PresentialWaiverDeclarationTest` 5), todos los existentes afectados en verde (alta manual, permisos
y su paridad es↔zh, catálogo de auditoría, morph map, fronteras, ficha de usuario) · migración
aplicada sobre MySQL dev · **§6·2 demostrada por mutación**: editar la página y publicar otra versión
DESPUÉS de firmar no cambia ni un byte del documento (`test_editing_the_page_and_publishing_again…`) ·
el documento es idéntico en dos renders · **cinco mutaciones, las cinco muerden** (control verde,
ficheros restaurados por `cmp`): sin IDOR → `test_404_when_the_signature_belongs_to_another_user` cae ·
sin permiso propio → `test_staff_without_the_waiver_permission…` cae · PDF sin auditar →
`test_admin_gets_the_pdf_and_the_consultation_is_audited…` cae · firmador sin copiar el nombre →
`test_an_anonymised_holder_is_still_identified…` cae · alta presencial sin mirar el modo →
`test_in_external_mode_nothing_is_signed` cae.

### 9.7 Lo que la tanda 2 enseñó (trampas)

- ⚠️ **Livewire 4: el contenido de un modal de Filament NO está en el `html()` del test** tras
  `mountAction()` — la vista de modales es un `wire:partial`. Medido: `mountedActions` lo lista,
  `mountedActionShouldOpenModal()` da `true` y el `mountUsing` corre (la auditoría quedó escrita), y
  aun así `assertSee` sobre el modal sale rojo. Se prueba cada pieza donde sí es observable
  (`TESTING.md`); el patrón está en `WaiverProofActionTest`.
- **Blade escapa los apóstrofos** (`d&#039;acceptation`): al comparar el HTML del PDF con un texto
  francés hay que decodificar entidades antes.
- **El PDF nunca vuelve a interpolar**: los tokens fiscales se resolvieron al PUBLICAR (§9.2·2). Si
  el parque cambia su razón social, las versiones ya publicadas —y sus PDF— siguen diciendo la de
  entonces, que es lo correcto.
- **Un permiso nuevo exige su etiqueta en zh_CN** aunque el chino no se mantenga (`DEUDA.md`):
  `AccessI18nParityTest` la pide para toda entrada de `PermissionCatalog`.
- **La limpieza de go-live borra las firmas ANTES que los usuarios** (`PurgeCustomerData`, `RESTRICT`)
  y por `DB::table`, porque el modelo rechaza `delete()`. Cualquier otra limpieza nueva hereda las dos
  condiciones.

### 9.8 Ejecución — tanda 3a, el CLIENTE por API (2026-08-26, `DECISIONES #163`)

La mitad servidor de la tanda 3: todo lo que el cajón (3b) y una app nativa necesitan para presentar
el texto, aceptarlo y ver la prueba, **sin tocar todavía una línea de Vue**. Contrato en
`openapi/v1.yaml` (manda), lo que enseñó en `specs/api-v1.md` §10.septdecies.

| Pieza | Dónde | Qué hace |
|---|---|---|
| `GET /legal/waiver` (público) | `app/Http/Controllers/Api/V1/LegalWaiverController.php` · `app/Http/Resources/Api/V1/WaiverDocumentResource.php` | El SNAPSHOT vigente en el idioma negociado, con su `id`; `document: null` fuera del modo interno o sin versión (no es un error) |
| `GET /me/waiver` | `app/Http/Controllers/Api/V1/MeWaiverController.php` · `app/Http/Resources/Api/V1/WaiverStatusResource.php` | Estado según el modo (`signed`, `outdated`, `current_document_id`) y mis firmas con su PDF; sin ip, UA ni hashes |
| `POST /me/waiver` | ídem + `app/Domain/Identity/Services/WaiverAcceptance.php` | Aceptar con el `document_id` servido → 201 con el estado nuevo; `409 waiver_document_stale` si el texto cambió, `409 waiver_not_internal` fuera del modo interno. Sirve para la re-firma (§4.8) |
| `GET /me/waiver/{signature}/pdf` | ídem | El PDF PROPIO (§4.5), scoping por el guard (404 si no es mía), auditado, `no-store` por el grupo |
| El alta | `app/Http/Controllers/Api/V1/AuthRegistrationController.php` · `app/Domain/Identity/Services/SelfSignup.php` | `accept_waiver` (nace desmarcada; **OBLIGATORIA en interno con versión publicada desde `#178`**: sin ella `422` sobre el campo y la cuenta no se crea) + `waiver_document_id` (`required_if_accepted`); la vigencia se comprueba **antes** de crear la cuenta (422 sobre el campo); ⚠️ desde `#179` `createAccount()` **NO firma**: deja la aceptación pendiente (`waiver_pending_document_id` + canal) y la firma la registra `SignPendingWaiverOnVerification` al verificar el correo, si el texto sigue vigente |
| El contexto de cuenta | `app/Domain/Identity/Services/CustomerAccountContext.php` · `app/Http/Resources/Api/V1/AccountContextResource.php` | `waiver: {mode, required, outdated, document_id}` — el sitio de la re-firma «en el siguiente momento natural»; viaja también en la semilla del montaje (`AccountContextSeed`) |
| Códigos de error | `app/Http/Api/ApiErrorCode.php` · `lang/{es,en,fr}/api.php` | `waiver_not_internal` · `waiver_document_stale` (409), en el `enum` del contrato |

**Decisiones de ingeniería** (el porqué, en §10.septdecies de la spec de la API): (1) «la versión que
el servidor sirvió» = la VIGENTE, y `WaiverAcceptance::currentDocument()` es la única regla para las
dos puertas; en el alta se rechaza **antes** de crear la cuenta y como 422 sobre el campo, en
`/me/waiver` como 409 con código. (2) El canal (`web`/`api`) sale de cómo se autenticó la petición,
nunca de un campo del cuerpo — ⚠️ y desde `#174` **tampoco de una cabecera**: en `/me/waiver` decide
el token real (`currentAccessToken() instanceof PersonalAccessToken`; con sesión, `Bearer basura`
sigue siendo `web`) y en el alta, si la petición se sirvió con sesión (§10.2·1). (3) Los avisos por campo del alta viven en `api.register.*`, no en
`account.register.*`: ese grupo viaja en el montaje de cada página y `SidebarMountTest` mide su
presupuesto — al ponerlos ahí, el montaje anónimo pasó de su techo (3.381 B sobre 3.200) y con
sesión también (6.794 sobre 6.600); movidos, cabe. Solo el rótulo de la casilla (`accept_waiver`)
se queda, porque el cajón sí lo pinta. (4) El export del art. 20 lleva el consentimiento visible y
**no** el registro probatorio (§4.6) — aseverado. (5) El PDF propio va por la API para que la app
nativa lo baje con su Bearer; mismo `WaiverProof` y misma vista que el panel.

**Lo medido**: suite **+25** (`LegalWaiverTest` 5 · `MeWaiverTest` 14 · `AuthRegistrationTest` +5 ·
`MeAccountContextTest` +1), el contrato en verde (`ApiContractTest`: rutas ↔ `paths`, códigos ↔
`enum`, esquemas estrictos) y los presupuestos del montaje del cajón dentro de su techo tras la poda.
**Cinco mutaciones, las cinco muerden** (control verde, ficheros restaurados por `cmp`): un texto
caducado aceptado → `test_a_stale_document_id_is_rejected…` cae · el alta sin comprobar la vigencia →
`test_a_stale_document_rejects_the_signup…` cae · el PDF sin IDOR → `test_someone_elses_signature…`
cae · un alta que no firma → `test_accepting_the_waiver_at_signup…` cae · un contexto que no avisa →
`test_it_says_whether_the_waiver_needs_signing…` cae.

**Trampas** (para la 3b): `CustomerAccountContext` es un singleton memoizado por petición — en un
test que encadena peticiones hay que `app()->forgetInstance()` entre ellas · `Sanctum::actingAs()` no
pone la cabecera `Authorization`: para probar el canal `api` hay que enviarla · Spectator no valida
binarios: el PDF se prueba por `%PDF-`, `content-type` y `no-store`.

**Lo que quedaba (3b, el cajón)**: la casilla en el alta del paso 5 (`register.js` + su `.vue`),
la zona de privacidad con «firmar / re-firmar» y los PDF, y el aviso al entrar o al ir a pagar
cuando `accountContext.waiver.required|outdated` — con los presupuestos del cajón delante
(`SidebarBundleBudgetTest`, el techo de 40 líneas por componente, el diff de árbol y `build:ssr`).
▶ Hecho en §9.9, con UNA salvedad dicha ahí: el aviso vive en el índice de la cuenta, **no en el paso
de pagar**.

### 9.9 Ejecución — tanda 3b, el CAJÓN (2026-08-26, `DECISIONES #166`)

La mitad cliente de la tanda 3: lo que el visitante VE. Tres capas, como todo el cajón
(`specs/sidebar-spa.md` §8): un módulo plano que decide, un store que pide, y componentes que pintan.

| Pieza | Dónde | Qué hace | Su red |
|---|---|---|---|
| El módulo plano | `resources/js/sidebar/account/waiver.js` | `waiverStatusKey(status)` —qué frase se pinta: `external` · `unsigned` · `current` · `outdated`—, `waiverNeedsSignature(status)` y `waiverPendingFrom(context)` —el aviso del índice, leído de `accountContext.waiver`—. **La lectura del estado es UNA** y la comparten la tarjeta y el aviso | `account/waiver.test.js` (6) |
| El store | `resources/js/sidebar/stores/waiver.js` | `useWaiverStore`: `ensureLegal()` (`GET /legal/waiver`, una vez), `ensureStatus()` (`GET /me/waiver`), `accept()` (`POST /me/waiver` con el `document_id` **servido**). ⚠️ Ante `409 waiver_document_stale` **RE-LEE** texto y estado y NO da la firma por hecha: el cliente nunca decide por su cuenta qué versión es la vigente | `stores/waiver.test.js` (9) |
| La casilla del alta | `register.js` · `stores/auth.js` · `steps/RegisterForm.vue` (+ `IdentifyStep.vue` y `RegisterZone.vue`, que la enchufan) | `accept_waiver` viaja `true` **solo si hay documento servido** y entonces va con su `waiver_document_id`; en cualquier otro caso `false` y `null`. La casilla **no existe** si `GET /legal/waiver` no trae documento (modo externo/desactivado o sin versión publicada): el alta de hoy no cambia. El texto completo va **plegado** en un `<details>` y `FIELD_ORDER` conoce `waiver_document_id` para que el aviso del servidor salga en su sitio del banner | `register.test.js` (+4) |
| La tarjeta de Privacidad | `account/zones/PrivacyZone.vue` | Estado según el modo (la frase sale de `waiverStatusKey`), **firmar o re-firmar** (texto plegado + casilla + botón), «firma registrada ✓», y la lista de firmas con su PDF (`pdf_url` de la API; la declarada en puerta se marca como tal). Al firmar **refresca el contexto de cuenta**, para que el aviso del índice se apague en el acto | navegador: `VERIFICACION-E2E-CAJON.md` §5.nonies (✅ headless 26/08, §9.10) |
| El aviso del índice | `account/zones/AccountHomeZone.vue` | «Tu waiver está pendiente → fírmalo» cuando `accountContext.waiver.required` o `.outdated`; lleva a Privacidad. Es la re-firma «en el siguiente momento natural» (§4.8) | ídem |
| Los textos | `resources/views/components/layout.blade.php` · `lang/{es,en,fr}/account.php` | `privacy.waiver` entero (12 rótulos, todos pintados) y `register.accept_waiver` + `waiver_read`. **`register` pasa de viajar entero a `Arr::only`** (ver «lo medido») | `SidebarMountTest` (listas exactas + dos presupuestos) |

**Decisiones de ingeniería** (el porqué largo, en `DECISIONES #166`):

1. **El aviso de re-firma vive en el índice de la cuenta, NO en el paso de pagar.** La spec decía
   «al entrar o al ir a pagar»; se construyó lo primero. Dos motivos, y el segundo es el que manda:
   la puerta **deja pasar** con una versión anterior (§4.8: no es una condición de compra, así que
   no debe parecer una en el checkout), y el chunk quedó con **0,28 KiB** de margen bajo el techo
   que el owner acaba de subir — un segundo aviso en `PurchaseSection` (432 líneas, que solo puede
   adelgazar) costaría más que eso. **Queda abierto como decisión de producto**, con su precio.
2. **El texto del waiver no viaja ni en el chunk ni en el arranque.** Lo pide el store cuando hace
   falta (`ensureLegal()`), en el idioma negociado, y en el alta se pide al montar el formulario.
   Meterlo en `data-boot` habría sido pagar un texto legal entero en cada página pública.
3. **El diff de árbol NO ve la casilla del waiver**, y hay que decirlo: `RegisterForm` la pinta bajo
   `v-if="waiverStore.document"` y en SSR el store está vacío —`onMounted` no corre—, así que el
   manifiesto congelado del formulario de alta **no cambia** y sigue en verde con la casilla rota o
   sin ella. La red de esta tanda para lo visible es el **navegador** (§5.nonies del guion E2E), como
   ya lo era para todas las zonas de la cuenta.
4. **Los presupuestos se PODARON antes de subir, y uno BAJÓ.** Detalle abajo.

**Lo medido** (todo sobre el `data-boot` real y el `manifest.json` real, no sobre una idea de ellos):

- **Chunk del cajón: 220,78 → 225,72 KiB, +4,94** — supera a «Mis pedidos» (`#129`, +4,10). Y es
  una FEATURE, cuando `ESTADO.md` decía que ese techo solo cede por correcciones: **la subida a 226 la
  decidió el owner el 2026-08-26 con el número delante**, entre subir, partir el waiver en un chunk
  aparte (el alta seguiría costando ~1,5 KiB en éste) o aparcar la tanda. **Quedan 0,28 KiB.**
- **Textos con sesión: 7.176 B con lo nuevo y sin poda → 6.668 tras la poda** (techo 6.600 → 6.760,
  92 B de holgura). La poda son **508 B** en dos capas: (a) fuera de `lang/` tres claves que **no leía
  nadie** —`login.no_account`, `register.has_account`, `profile.email_resend_throttle`—; (b) fuera
  del ARRANQUE, pero no de `lang/`, `register.must_accept`, `already_exists`, `exists_unverified` y
  `bot_check_failed`: los publica el **servidor dentro del 422** y `register.js::registerErrors()` los
  pinta tal cual, así que el cajón nunca los leía de aquí y viajaban en todas las páginas públicas.
  Lo nuevo son +701 B brutos; el neto es **+193**.
- **Textos anónimos: 2.742 B** medidos por la guarda tras la poda (que aquí también se paga, porque
  `login` y `register` viajan para todo el mundo), y su techo **BAJA** de 3.200 a 2.850: con 458 B
  sobrantes «un techo con margen sobrante deja de apretar» (`SidebarBundleBudgetTest`). ⚠️ Un
  `tinker` sobre la BD de desarrollo dio 2.505 con los mismos grupos —todos un ~10 % más cortos—; la
  cifra que vale es la de la guarda, que mide el mismo `data-boot` que asevera.
- `npm run test:js`: **671 → 690** (+19: 6 del módulo, 9 del store, 4 de `register.js`).
- Suite PHP: lo que cambia es `SidebarMountTest` (la lista de `register` y los dos techos) y
  `SidebarBundleBudgetTest`; el resto de guardas del cajón —árbol, estilo, iconos, texto, componentes
  ≤ 40 líneas, `build:ssr`— en verde sin tocarlos.

**Trampas** (para quien vuelva aquí):

- **`git pull --rebase --autostash` reescribe los ficheros guardados y les pone mtime nuevo**: el
  bundle SSR pasó a estar «rancio» sin que cambiara una línea, y `SidebarDomContractTest` cayó
  **19/19** con un mensaje que dice exactamente eso. `npm run build:ssr` y en paz — pero si se ve
  caer en bloque, es esto antes que un componente.
- Un grupo de `lang/` que viaja **entero** (`__('account.register')`) es una invitación: cada clave
  que alguien añada para el SERVIDOR viaja al cliente sin que nada avise. `register` ya no viaja así.
- El store de Pinia no puede leer `this.<algo>` que no haya declarado en `state` —un `lastStale`
  imaginario devolvió `undefined` en silencio—: la bandera del 409 se captura de la respuesta cruda
  en la propia acción.

**Lo que queda del subsistema** (no de esta tanda): **el ✅ del owner en navegador** (§5.nonies del
guion; ⚠️ el bloque se llamaba «5.sexies» y colisionaba con el del bloque de cuenta — §10.8) · el
aviso en el paso de pagar, si el owner lo quiere, con su coste en el chunk · las dos decisiones
humanas de siempre: **el texto definitivo** (§8.1: ninguna versión publicada) y el **periodo de
retención** (`waiver.retention_months`, hoy sin valor). ▶ **Lo que pasó después está en §9.10 (el
guion, recorrido en headless) y en §10 (la revisión adversarial del subsistema entero).**

### 9.10 El guion §5.nonies, recorrido en HEADLESS (2026-08-26, `DECISIONES #169`)

> ⚠️ **Esta pasada es ANTERIOR a `#178` y `#179`**: describe la casilla como opt-in y la firma en el
> alta. La conducta definitiva se recorrió otra vez en §9.12 (**111/111 ✓, 0 desviaciones**); las filas V31·2, V31·3 y
> V32 de la tabla del guion se leen con eso delante.

El carril A lo recorrió entero con Playwright dentro del contenedor (receta y resultado detallado en
`VERIFICACION-E2E-CAJON.md` §5.nonies): **99 comprobaciones, 94 ✓**; de los 5 ✗, dos eran del
script, uno una aserción que el guion no pide, y **dos son un defecto real del cajón** (CAJ-3, §10.3:
tras el 409 la casilla sigue marcada y el botón habilitado). Todo lo que el guion pide del waiver
**pasa**: la casilla opt-in con el texto plegado y sus 5 secciones iguales a la API; el alta manda
`accept_waiver` **solo** con el id servido; el correo de verificación llega y su enlace abre sesión;
el aviso del índice y la tarjeta en sus cuatro estados; la firma desde la tarjeta con «Firmando…» →
«Firma registrada ✓» y el aviso apagado **sin recargar**; la publicación de v2 y v3 **desde el panel
por su acción y su modal**; la re-firma con dos firmas y **el PDF de la v1 intacto**; el
`409 waiver_document_stale` con la relectura (`POST → 409 · GET /me/waiver · GET /legal/waiver`) y la
segunda firma sobre la v3; los tres idiomas (alta en EN, firma en FR, PDF en el idioma en que se
firmó); la puerta («Waiver aceptado el…» / «versión anterior… puede pasar»); y el modo externo **por
Ajustes** (sin casilla, sin aviso, «La gestiona el parque fuera de esta web»). En BD: 7 firmas `web`
con la identidad copiada, la cadena de A1 enlazando v1→v2, `WaiverChain` OK y
**`waiver:verify-chain --workers=8` lineal sobre MySQL**. **Cinco PDF descargados por HTTP y leídos**
(§6 «leerlo»: hecho).

❗❗ **Y el hallazgo que ninguna lente de §10 podía ver, porque ninguna ejecutó un navegador: con el
anti-bot activo, el alta suelta NO TERMINA.** En `/registro` `RegisterForm` se monta a los 756 ms y
`GET /config` —que trae la `turnstile_site_key`— responde a los 794 ms; el widget se monta **solo en
`onMounted`** (`RegisterForm.vue:97-104`), con la clave vacía → apaño inerte; cuando la clave llega, el
`v-if` pinta el contenedor (en el DOM, **vacío**) y nadie monta nada: ni script de Cloudflare, ni
iframe, ni token. El servidor responde **422 «no eres un robot»** siempre. El paso 5 del embudo no lo
sufre (monta el formulario mucho después de `/config`). En local las claves se pusieron el 26/08 a las
14:48 y el guion V17 del 23/08 corrió sin ellas: por eso nunca se vio. ⚠️ **Staging tiene claves.**
Para la prueba se apagó el anti-bot (secreto vacío) y se restauró al terminar.
✅ **ARREGLADO la misma noche (`DECISIONES #171`)**: la decisión va al módulo — `mountTurnstile`
acepta funciones para el nodo y la clave, espera a que existan y **no carga el script de Cloudflare
hasta entonces**; el componente cambia dos líneas. 5 casos nuevos, 4 mutaciones que muerden, y
**verificado en headless con Turnstile encendido**: script inyectado tras `/config`, token a 3,1 s,
`201`. ▶ **El ojo del owner sigue pendiente** (`CONVENCIONES §3.bis`), ya sin apagar nada.

Trampas del andamio, para la siguiente vez, en §5.nonies del guion (puente dual-stack, `pdf-parse`,
`:visible`, los rótulos con `*` de Filament, `p.auth__sent`).


### 9.11 Tanda 4 — el código acotado de §10.11 (2026-08-26 noche, carril A)

Lo que la revisión exigía antes del ✅, en unidades que se empujan verdes y solas:

- ✅ **Unidad 1 · el anti-bot del alta suelta** (`#171`, §9.10): `mountTurnstile` acepta funciones y
  espera a que existan clave y nodo sin cargar Cloudflare antes; verificado en headless con Turnstile
  encendido.
- ✅ **Unidad 2 · el servidor** (`#174`), seis piezas con su test y su mutación (7 de 7 muerden):
  · **canal por guard** — en `/me/waiver`, `currentAccessToken() instanceof PersonalAccessToken`
    (`Sanctum::actingAs()` y la sesión dejan un `TransientToken`, así que `Bearer basura` con cookie
    es `web`); en el alta, `hasSession()` (cajón = sesión; app nativa = sin ella);
  · **idempotencia por versión y sujeto, dentro del lock** de `WaiverSigner` — la segunda aceptación
    de la misma versión (en cualquier idioma) devuelve la fila que hay: ni fila, ni consentimiento, ni
    auditoría nuevos. ⚠️ Consecuencia medida: **tres tests y el verificador construían la cadena
    re-firmando la misma versión** y cayeron; la cadena crece con VERSIONES nuevas, y
    `waiver:verify-chain` firma ahora **un menor a cargo por proceso** (`subject_id` = i) — misma
    propiedad, y visto FALLAR sin el lock (16 procesos: 15 `prev_hash` repetidos);
  · **guarda de borrador con los tres marcadores** del seeder + `[pendiente]` neutro, y
    `mentionsDraftWords()` como AVISO (no bloqueo) en el modal de «Publicar versión firmable»;
  · **el badge del pedido por `WaiverStatus`** — «No firmado» con sello sin registro en interno,
    «versión anterior» señalada, oculto en `desactivado`;
  · **throttles con prefijo** (`throttle:10,1,waiver-sign` / `waiver-pdf`): el tercer parámetro del
    middleware es el prefijo del cubo; los demás sin prefijo (pago, guest-form, reenvío) quedan en
    `DEUDA.md`;
  · **`WaiverSigner` en el `CRITICAL_RE`** y en `CriticalPathGateTest` (con `WaiverAcceptance` de
    control negativo); el aviso del hook nombra `waiver:verify-chain`.
- ✅ **Unidad 3 · el cajón** (`#175`): el id que se acepta es **el del texto ENSEÑADO** y solo ese
  (`currentDocumentId` ya no mira `status`); si el estado propio dice que el vigente es otro,
  `ensureStatus()` **relee antes** (CAJ-1) · el 422 `waiver_document_id` del alta **relee el texto y
  desmarca la casilla** (CAJ-2) · tras el 409 el store deja `reread` y la tarjeta **desmarca** (CAJ-3)
  · y `store.upcoming` (CAJ-5, una palabra). ⚠️ Un test antiguo afirmaba «el estado manda sobre el
  texto para el id que se acepta»: era la grieta, escrita como test; se reescribió. **Chunk 226,21
  KiB: el techo sube a 226,5 por CORRECCIÓN** (ledger en `SidebarBundleBudgetTest`). Verificado en
  headless con el anti-bot encendido.
- 🟦 **Unidad 4 · las tres decisiones del owner** — ✅ **4c · la casilla del alta OBLIGATORIA en
  interno** (§7·7, `#178`): `accepted` sobre `accept_waiver` cuando hay documento que servir; 422
  con `api.register.waiver_required`; externo e interno-sin-versión siguen opcionales · ✅ **4b · la
  casilla del alta MANUAL** (§7·4, `#178`): el operador ve el texto vigente y declara; sin la casilla
  `CustomerRegistrar` no firma, también por el camino del cliente sin email. ⚠️ **El navegador cazó
  un 500 al abrir el modal** (`$version->sections` como propiedad) con la suite en verde —el modal es
  un `wire:partial`—: `counterWaiverText()` es público y se prueba directo · ✅ **4a · correo
  verificado para firmar** (§7·5, `#179`): la guarda vive en el DOMINIO (`WaiverSigner`, sobre la fila
  bloqueada, salvo firma declarada); el alta deja la aceptación **pendiente** en dos columnas de
  `users` y un listener de `Verified` la convierte en firma al verificar —con el canal del alta, la IP
  y el UA del clic— o la descarta si el texto ya no es el vigente; `409 waiver_email_unverified` en la
  API. ⚠️ `accepted_at` pasa a ser el momento de la verificación, no el del alta: es cuando la persona
  demostró ser dueña del buzón que la firma copia. `waiver:verify-chain` siembra el titular verificado.
- ✅ **Unidad 5 · el texto del PDF** (§10.6, `#180`): la comprobación se llama «Coincide … (comprobación
  interna)» y una nota nueva dice lo que es —cadena SHA-256 sin secreto en la propia BD, sin sello de
  tiempo ni anclaje en un tercero; detecta alteraciones accidentales o de la aplicación, no las de quien
  escriba en la BD— (WAI-02); el pie ya no dice «fila inmutable»; en una firma de mostrador el PDF dice
  que los datos los TECLEÓ el operador y que la IP y el navegador son del PUESTO (WAI-07); y
  `WaiverChain::verify()` cruza cada firma con su versión (NUC-8). En los tres idiomas, con paridad.
- ✅ **Unidad 6 · lo que la revisión de la propia tanda vio** (§9.12, `#183`): la firma pendiente del
  alta lleva la IP/UA de la ACEPTACIÓN y se registra TRAS el commit (S-1: `Verified` también lo emite
  el cobro, dentro de su transacción); la vigencia se re-comprueba DENTRO del lock (S-3); el cambio de
  correo también verifica (S-5); la guarda de borrador compara texto, no bytes (S-6); la declaración en
  mostrador vale para una cuenta que ya existía (F-01); el contrato dice `pending` (S-2); el 422 de
  `accept_waiver` relee el texto (CAJ-422); re-marcar apaga la relectura (CAJ-REREAD); el aviso del
  modal se ve, los idiomas anunciados son los publicados, el badge de versión anterior es ámbar, la
  rama negativa del PDF habla como la positiva y se renderiza en test, la ayuda `zh_CN` completa y la
  versión del mostrador memoizada (F-02…F-07). 10 mutaciones, las 10 muerden. Y el guion, otra vez.

---

### 9.12 La revisión adversarial de la TANDA 4, aplicada (2026-08-26 noche, `#183`)

La tanda se revisó como el subsistema (§10.0): 24 hallazgos verificados con escépticos, **0 refutados**,
52 afirmaciones que aguantaron. Dos altas, cinco medias, diecisiete bajas. Lo que cambió el código:

| Id | Hallazgo (verificado) | Qué se hizo |
|---|---|---|
| **S-1** (alta) | `Verified` no lo emite solo el enlace del correo: también **`RedsysReturnHandler::autoVerifyBuyer()`** al cobrar en pay-first, **dentro de la transacción del cobro** — y si gana la notificación S2S, la firma pendiente llevaba la IP/UA **de Redsys** | La aceptación pendiente guarda **la IP y el navegador del momento de marcar la casilla** (`waiver_pending_ip`/`_user_agent`, PII del alta: `anonymize()` + censo) y el listener firma con ellos **`DB::afterCommit`**: nunca dentro de la transacción de otro, y si el cobro se deshace, no hay firma |
| **D1** (alta) | El guion §5.nonies V31·2/V31·3/V32 describía la conducta anterior (opt-in, firma en el alta) | Reescrito; **re-recorrido en headless con la conducta nueva: **111/111 ✓, 0 desviaciones**** |
| S-2 | La API no podía decir «aceptado, pendiente de verificar» | `pending` en `WaiverStatus` y en `account-context.waiver` (contrato + `#183` en `api-v1.md` pt. 94). El cajón no lo pinta: una cuenta sin verificar no llega a Mi cuenta por web; es para un cliente nativo (`DEUDA.md`) |
| CAJ-422 | `auth.js` solo releía ante `waiver_document_id`; el 422 de `accept_waiver` con `document: null` cacheado era un callejón sin casilla | Releer también ante `accept_waiver` (medido en el guion: `422` → `GET /legal/waiver`) |
| F-01 | El alta manual descartaba en silencio la declaración si el correo ya existía | `CustomerRegistrar::declareAtCounter()`: vale para la cuenta nueva, para la existente por correo y para la elegida por teléfono; idempotente por versión |
| S-3 | TOCTOU publicar-vs-firmar: la vigencia se comprobaba FUERA del lock | Re-comprobada DENTRO (contra el máximo publicado): una versión superada no se firma aunque pasara la puerta |
| S-5 | `EmailChangeController` verificaba sin emitir `Verified` → aceptación colgada | Emite `Verified` |
| S-6 | La guarda comparaba bytes: un marcador en NFD o `[ pendiente` publicaba el borrador | NFC + blanco tras `[` colapsado |
| CAJ-REREAD | `reread` se quedaba pegajoso: un fallo de red posterior desmarcaba la casilla | Re-marcar apaga la relectura |
| F-02…F-07 | El aviso del modal (`\n\n` en un `<p>`), idiomas vacíos anunciados, badge «versión anterior» en verde, `verified_no` con otro vocabulario y sin test, ayuda `zh_CN` truncada, cuatro lecturas de la versión por render | Arreglados; la rama negativa del PDF ahora se renderiza en test |
| D2…D12 | Desfases de doc (tracker con un «▶ Sigue» ya hecho, la fila de `CLAUDE.md` y el README que presentaban como abiertos defectos cerrados, `ESTADO` citando `#176` por `#178`, §7·2/§10.4 diciendo que la guarda caza un marcador, §9.8 «opt-in», §10.11 con ⏳ ya ✅) | Corregidos en esta misma pasada |
| S-4 · TURN-CLAVE-VACÍA · TURN-ESPERA | Precisiones que no cambian conducta (el canal `web` del alta lo decide que la petición se sirviera con sesión —`Origin` de la web—; la clave vacía y la espera definitiva del widget son inalcanzables hoy) | Fichadas en `DEUDA.md` como bajas |

**Lo medido**: +9 tests PHP, +1 JS (contador en `ESTADO.md`) · **10 mutaciones, las 10 muerden**
(S-1 ×2, S-3, F-01, S-6 ×2, F-07, S-5, S-2, CAJ-422) · `waiver:verify-chain` 8/16 lineal sobre InnoDB ·
el guion completo en headless, **111/111 ✓, 0 desviaciones**.

## 10. Revisión adversarial del SUBSISTEMA — 2026-08-26 (`DECISIONES #169`)

> Hecha por el carril A (que no escribió el waiver), como exige `CONVENCIONES §5`, **sobre el código
> completo** (las cuatro tandas). **Método**: seis lentes independientes —núcleo, panel, API, cajón,
> invariantes/documentación, amenazas— intentaron **refutar** cada afirmación de §9 y del cuerpo
> ejecutando contra el código en **solo lectura** (nada de mutaciones: el árbol tenía una prueba de
> navegador en marcha); después, cada hallazgo pasó por uno o dos escépticos que intentaron refutarlo
> a su vez. **55 agentes.** Resultado: 69 hallazgos brutos → **37 confirmados** (1 alta · 21 medias ·
> 15 bajas), **3 refutados**, **29 bajas sin verificar** por tope (§10.9) y **68 afirmaciones que
> aguantaron** (§10.0). Los de más peso los re-verificó a mano quien firma esta sección (fichero:línea
> en cada uno). ⚠️ **Y lo más grave del día no lo vio ninguna lente, porque ninguna ejecutó un
> navegador**: el alta suelta rota por el anti-bot está en **§9.10**, con el guion.

### 10.0 Lo que aguantó (para no re-medirlo)

Verificado ejecutando el 2026-08-26: versiones y firmas **no se editan ni se borran por el modelo** y
no tienen `updated_at`; los caminos que saltan la guarda (`DB::table`, `*Quietly`, `withoutEvents`)
existen y la alteración de un campo hasheado **se detecta**; `WaiverSigner` es el **único** escritor y
la cabeza de la cadena se lee **después** del lock; las filas v1 siguen verificando con su esquema
(v1 ya llevaba el prefijo `v`); la serialización canónica es JSON con claves fijas (sin ambigüedad de
separador, `null` ≠ `''`, UTC, ids enteros) y el *roundtrip* por la columna JSON de MySQL no altera el
hash (3 de 3 versiones locales); `retentionMonths()` devuelve `null` con vacío/`0`/negativo/decimal/
texto y `mode()` desconocido cae al interruptor heredado; la poda es `Prunable` fila a fila y la
dispara `model:prune`; `anonymize()` conserva la firma y sigue purgando lo demás; **la cadena es por
titular**; §9.3 «cinco mutaciones» — los cinco tests existen y aseveran lo que dicen. Panel: el PDF
sale del snapshot y no cambia al editar ni al publicar; el cuerpo, el nombre y el UA se pintan
**escapados**; las etiquetas es/en/fr tienen las mismas claves y dicen cosas distintas; el PDF de una
firma declarada lo dice en los tres idiomas; IDOR, throttle y `no-store` en la ruta del PDF; se puede
bajar sin abrir el modal pero **no sin auditar**; `waiver.view` no va al staff; «Publicar» solo en
`waiver`, con `content.manage`, congela lo GUARDADO y **rechaza** publicar con `business.legal_name =
'[PENDIENTE]'`; el alta presencial exige las tres condiciones; la puerta en interno lee el REGISTRO.
API: los dos 409 están en el enum del contrato y los esquemas son estrictos; el PDF propio da 404
para firma ajena/inexistente; un id caducado, inexistente o de otro slug → 409; `document: null` no
es ambiguo (`mode` lo desambigua); `required_if_accepted` es real y la firma del alta va en la MISMA
transacción que la cuenta; el export del art. 20 lleva el consentimiento visible y **no** el registro;
**`outdated` funciona entre idiomas** (firmado v1·en, publicada v2 → `outdated: true` también
negociando en). Cajón: ante 409 el store relee y no da la firma por hecha; `busy` no se cuelga ante
409/401/422/429/red; `accept_waiver` viaja `true` solo con id; las 14 claves de `lang/` existen en los
tres idiomas y no hay literales quemados; cada clase nueva tiene regla CSS; el texto se pide UNA vez y
en el idioma del SITIO; la casilla no existe sin documento y si un día se pintara en SSR el diff de
árbol la cazaría. Docs: `RGPD-01`/`RGPD-04` citan guardas que aseveran lo que dicen; `MODELO-DATOS`,
`PANEL-ADMIN`, `GLOSARIO` e `INSTALACION-CLIENTE` describen lo que el código hace; los recuentos de
§9.3/§9.6/§9.8 cuadran; `DECISIONES #142/#156/#160/#161/#163/#166` existen y dicen lo que la spec.

### 10.1 ❗ ALTA — la firma «declarada» del alta presencial no la declara nadie (PAN-2)

§8.4 `[DECIDIDO owner]` dice que el mostrador produce una firma **declarada por el operador** («el
operador declara que el cliente aceptó»), y el PDF lo imprime así. Medido: el formulario del alta
manual tiene **una sola casilla**, la de privacidad (`app/Filament/Pages/CreateManualOrderPage.php:252`,
`privacy_informed`; `grep -i waiver` en ese fichero → 0), y `CustomerRegistrar` firma en modo interno
sin más condición que las tres de §9.6 (`app/Domain/Identity/Services/CustomerRegistrar.php:109-114`).
**El operador no ve el texto, no marca nada y no sabe que está «declarando»**: el documento probatorio
le atribuye un acto que no hizo. Es §4.5 («fingir lo contrario es peor que decirlo») trasladado al
operador, y **es de producto antes que de código**: hace falta la casilla del waiver en el alta manual
(con el texto vigente a la vista) que condicione la firma — o no firmar en mostrador. Confirmado 2/2.
⚠️ Relacionado (bajas): el PDF de mostrador imprime «los datos los declaró la persona al crear su
cuenta» y una IP/UA **que son del puesto del operador** sin decirlo (WAI-07, `waiver-proof.blade.php:91`
y `:103-104`, `CustomerRegistrar.php:71` y `:114`).

### 10.2 Lo que el CLIENTE puede decidir sobre su PROPIA prueba (cuatro medias)

1. **El canal lo elige el cliente** (API-1). `channel` —que entra en el hash y el PDF enseña— sale de
   `$request->bearerToken() !== null` (`MeWaiverController.php:98`, `AuthRegistrationController.php:161`).
   El guard de Sanctum resuelve **primero la sesión**, así que con cookie válida y una cabecera
   `Authorization: Bearer basura` la firma consta como `api` sin que el token se valide. §9.8·2
   («sale de cómo se autenticó, nunca de un campo») es **falsa**: es un dato que el cliente declara.
   ▶ Salida: derivar el canal del guard que autenticó de verdad (sesión ⇒ `web`, token ⇒ `api`).
2. **La IP la elige el cliente** (WAI-04). `bootstrap/app.php:39` `trustProxies(at: '*')` ⇒ `ip()` es
   **siempre** el primer valor de `X-Forwarded-For`, que escribe el cliente — medido pasando una
   `Request` por `TrustProxies` (`REMOTE_ADDR=203.0.113.9` + XFF `8.8.8.8` → `8.8.8.8`). Ni siquiera un
   proxy que AÑADA la IP real al final lo arregla. Es infra (acotar los proxies de Enhance/Cloudflare,
   como ya dice el comentario), pero **mientras tanto la IP del PDF es repudiable**.
3. **Se firma con un correo que nadie ha verificado** (WAI-01 + API-5). §4.7 apoya la identidad en
   «cuenta con correo verificado»: **falso**. `SelfSignup::createAccount()` firma dentro de la misma
   transacción que crea la cuenta, con `email_verified_at = null`, y el correo de verificación sale
   **después** (`SelfSignup.php:127-131`, `:244-249`); `POST /me/waiver` va bajo `auth:sanctum` sin
   exigir verificación; y el esquema canónico v2 **no guarda** el estado de verificación al firmar.
   Cualquiera puede darse de alta con el correo de un tercero y «aceptar en su nombre». Confirmado 2/2.
   ▶ Es una decisión: exigir correo verificado para firmar (el alta con casilla dejaría de firmar en
   el acto) **o** guardar `email_verified_at` en la fila y que el PDF lo diga.
4. **`POST /me/waiver` no es idempotente** (API-3). `WaiverAcceptance::accept()` solo comprueba modo y
   vigencia (`WaiverAcceptance.php:47-56`): dos envíos del mismo id → **dos firmas, dos `consents`,
   dos PDF** (sonda: `[201, 201, 2, 4, 4]`). El cajón lo evita con `busy`; una app nativa, un doble
   tap o un reintento de red, no. ▶ Salida: si ya hay firma de ESA versión, no crear otra.

### 10.3 El cajón: tres grietas entre lo que se ENSEÑA y lo que se FIRMA

- **CAJ-1 (media, 2/2)** — el id que se envía sale de `status.current_document_id` y el texto que se
  pinta de `legal.document`, **cacheado para toda la vida de la página** (`stores/waiver.js:52`, `:63`).
  Divergen en la ruta del embudo: el paso 5 monta `RegisterForm` (cachea `GET /legal/waiver`), el alta
  abre sesión sin recargar, y si entre medias se publicó otra versión, Privacidad **enseña la vieja y
  firma la nueva** — el servidor la acepta porque el id sí es el vigente. El 409 no cubre este caso.
- **CAJ-2 (media, 2/2)** — el 422 `waiver_stale` del alta dice «vuelve a leerlo» y **no hay forma de
  releerlo**: `ensureLegal()` es de una sola vez y nadie invalida `legal` en ese 422
  (`RegisterForm.vue:103`, `stores/waiver.js:63`). Cada reenvío manda el mismo id caducado; en el embudo,
  recargar es perder el paso.
- **CAJ-3 (media)** — tras el 409 la tarjeta remonta con el texto nuevo **y la casilla sigue marcada**
  (`PrivacyZone.vue:73-80`: `acceptWaiver` solo se limpia al salir bien): un segundo clic firma la v3
  sin que nadie haya tenido que releer. ⚠️ **Medido también en el guion** (§9.10 V34): «la casilla
  vuelve a estar desmarcada» salió ✗.
- CAJ-4 (media) — un 401 al firmar no cambia nada en pantalla (patrón heredado de los formularios del
  área: `form-outcome.js:44` marca `expired` y ningún `.vue` lo lee).
- CAJ-5 (media, **fuera del waiver**) — `AccountHomeZone.vue:63` compara `upcoming > 0` con un
  identificador **no declarado** (`_ctx.upcoming` = `undefined`): la burbuja con el número de reservas
  próximas **no se pinta nunca**. Compilado: `t.upcoming>0` frente a `P(r).upcoming` dos líneas después.

### 10.4 La guarda de borrador caza UN marcador de tres (WAI-03 + DOC-2)

> ✅ **Cerrado**: tres marcadores + aviso de palabras (`#174`); NFC y blanco tras `[` (`#183`, S-6).

`LegalDocumentPublisher::DRAFT_MARKER = '[pendiente'` (`:28`). El seeder siembra **tres** marcadores
—`[PENDIENTE: redacción definitiva]` (es), `[PENDING: final wording]` (en, `LandingContentSeeder.php:790`),
`[À COMPLÉTER : rédaction définitive]` (fr, `:797`)— y **solo el castellano se detecta**; los otros dos
pasan (medido con `looksLikeDraft` → `false`). Y en esta BD hay una v1 publicada cuya sección
«Aceptación» dice literalmente «Este texto es un borrador y será revisado por un asesor legal», sin
corchetes: **§9.2 vende una guarda de BORRADOR y es una guarda de MARCADOR**. ▶ Salida barata: los
tres marcadores + la palabra «borrador/draft/brouillon» como aviso (no bloqueo).

### 10.5 Panel y dominio (medias)

- **PAN-5** — el badge del waiver en la ficha del PEDIDO lee el **sello** (`OrderInfolist.php:153-159`)
  sin `WaiverStatus`: en interno, un sello heredado sin registro sale **verde** en el pedido y «falta
  firmar» en la puerta; una firma de versión antigua sale verde sin aviso; en `desactivado` sigue
  avisando. §8.7 exigía migrar «las dos» superficies y §9.5 no lo dejó abierto.
- **PAN-1 / NUC-2** — `declared_by_user_id` **entra en el hash** y su FK es `nullOnDelete`
  (migración `2026_08_25_130000:55`): un borrado físico del operador (hoy solo `app:purge-customer-data`
  o SQL; el panel no borra usuarios) deja `verifyHash() === false` **para siempre** en todas las
  firmas que declaró — la FK muta un campo hasheado de una fila «inmutable». `RESTRICT`, o copiar el
  nombre del operador como se copió el del titular.
- **PAN-4** — «determinista» lo es respecto al REGISTRO, no al documento: la cabecera lee
  `business.name` en vivo (`WaiverProof.php:184`) y el recuadro de declaración lee `declaredBy?->name`
  en vivo (`:133`). Renombrar el parque o al operador cambia todos los PDF ya emitidos.
- **NUC-3** — §9.4 («la poda solo mueve el inicio») solo vale para cadenas de puro titular:
  `prunable()` no poda `dependent` (`WaiverSignature.php:241`) pero la cadena enlaza sobre la **última
  fila del titular sea cual sea el sujeto** (`WaiverSigner.php:50-53`, sin filtro): con un menor
  intercalado, la poda deja **agujeros en medio** y `WaiverChain::verify()` declara ROTA una cadena
  legítima. ⚠️ Condiciona `menores-a-cargo.md` (§8.3): decidirlo ANTES de la primera firma de menor.
- **NUC-5** — «el lock es la primera sentencia de la transacción» solo es cierto cuando `sign()` ABRE
  la transacción (`POST /me/waiver`); en `SelfSignup` y `CustomerRegistrar` va **anidado** (savepoint,
  `SelfSignup.php:210-245`). Hoy inocuo (el titular acaba de crearse); el día que un tercer llamante
  firme por un titular EXISTENTE dentro de su propia transacción, §8.5 vuelve. Regla para dejar escrita.
- **NUC-6** — **ningún gate vigila el lock**: la suite corre en SQLite, donde `compileLock()` devuelve
  `''`; ningún test de `tests/Feature/Waiver/` menciona `lockForUpdate`; `WaiverSigner.php` **no está**
  en el `CRITICAL_RE` ni en `CriticalPathGateTest::CRITICAL_FILES`. Retirar el lock pasa 72/72 y pasa
  el push. La única propiedad por la que existe la cadena depende de acordarse de `waiver:verify-chain`.
- **API-2** — los `throttle:N,1` **sin nombre** comparten UN cubo por usuario
  (`ThrottleRequests.php:98`, `:224-227`): `POST /me/waiver` (10), el PDF propio (10), el guest-form (30),
  **el reintento del pago** (6) y `verification.send` (6). Siete descargas del PDF en un minuto dejan
  al cliente sin poder reintentar un pago durante 60 s. Throttles con nombre.

### 10.6 Lo que el PDF afirma DE MÁS (para el texto del documento)

- **WAI-02 (media, 2/2)** — «Verificada» / «fila inmutable» / «el valor probatorio reside en el
  registro»: el hash es `sha256` **sin secreto ni anclaje** (`WaiverSignature.php:167-170`); quien
  escriba en MySQL fabrica o reescribe la cadena entera de un titular y el PDF imprime «Verificada»
  (demostrado con una fila inventada: `verifyHash=true`, `prev_ok=true`). §4.7 lo asume al aplazar el
  sello RFC 3161; **el PDF no puede decir más de lo que el diseño garantiza**.
- PAN-3 (baja) — «`integrityOk()` (fila + versión + enlace)»: el «enlace» es firma→VERSIÓN
  (`document_hash == body_hash`, `WaiverProof.php:163-168`), **no** `prev_hash` con la anterior.
- NUC-7 (baja) — `body_hash` cubre `title` + secciones: `published_at`, `version`, `locale` y `slug`
  quedan fuera (`LegalDocumentVersion.php:80-88`), y el PDF imprime esa fecha como si estuviera cubierta.
- NUC-8 (baja) — `WaiverChain::verify()` no cruza `document_hash` con la versión ni llama a
  `version->verifyHash()` (`WaiverChain.php:25-42`): una versión alterada por debajo da «cadena OK».
- NUC-4 (baja) — la poda no deja rastro propio del hash podado; queda el `audit_logs` de la firma.

### 10.7 Bajas restantes, una línea cada una

NUC-9 la guarda «anonimizado no firma» se evalúa sobre la instancia recibida, fuera del lock
(`WaiverSigner.php:39` vs `:48`) · NUC-10 el respaldo de idioma es pedido → `app.fallback_locale`
(**`en`**) → `es` → primera, no «→ es» como dice §9.1 (`LegalDocuments.php:36-39`) · NUC-11 en interno
una cuenta anonimizada sigue «firmada, versión vigente» (`WaiverStatus.php:46-66`) y en externo «sin
waiver»: §9.2·3 solo es cierto en interno · NUC-12 §9.1/§9.3 cuentan la tanda 1 (47 casos, 3 acciones)
cuando hoy son **72 casos en 9 ficheros y 5 acciones** · WAI-09 la única prueba respecto de un menor es
la cláusula genérica; `subject_type = dependent` es hoy **código inalcanzable** · API-4 en interno **sin
versión** el contexto dice `required: true` con `document_id: null` y el índice avisa de algo que no se
puede firmar · DOC-4 el bloque del guion se llamaba «5.sexies» como el de `#123` — **renombrado a
§5.nonies** en esta sesión · DOC-5 `docs-check` no detecta marcadores de conflicto (medido con el
`ESTADO.md` en `UU` de esta tarde: gate verde) · DOC-8 §6 no lleva estado por ítem (lo lleva ahora,
abajo) · DOC-3 la cabecera y la intro de §9 decían «3b pendiente» y «ninguna versión publicada»
(**corregidas en esta sesión**).

### 10.8 Refutados (3), y por qué

- **NUC-1** «la maquinaria rechaza publicar el borrador — resuelto como mecanismo» (alta): los HECHOS
  se reproducen (v1 local sin marcador y con la frase de borrador), pero el hallazgo pedía tratar la v1
  **local** como incidente de producción; es una preparación del guion, hecha a mano y en local. Lo que
  sobrevive es §10.4.
- **DOC-1 / WAI-05** «conservación sin plazo = hueco doc↔código de severidad alta»: los hechos son
  ciertos (`retention_months` vacío ⇒ `1 = 0`, nada se poda, nombre+correo+IP+UA sin caducidad), pero
  es **exactamente el `[PENDIENTE: owner]` declarado** en §4.6, §7, `RGPD-01` y el propio PDF («plazo
  no fijado»). No es un defecto: es la decisión que falta. ⚠️ Y una precisión que sí queda para el
  owner: el plazo cuenta **desde la firma** (`accepted_at`), no desde el borrado de la cuenta, y afecta
  a cuentas VIVAS — al vencer, en interno el titular vuelve a estar «sin firmar».

### 10.9 Sin verificar (29 bajas, por tope de 40 verificaciones)

Se listan para que nadie las crea inexistentes; cada una lleva fichero:línea en el informe de la
sesión (scratchpad, no versionado). Las que merecen mirarse al retomar: **PAN-7** `waiver.view` no basta
para llegar a la acción (hace falta `users.manage`) · **PAN-10** la puerta con `deleted_N@deleted.local`
responde «registrado con waiver» · **WAI-06** TOCTOU publicar-vs-firmar (la vigencia se comprueba
fuera del lock) · **WAI-14** `waiver:verify-chain --keep` en interno sin versión **deja publicada una
v1 de prueba** · PAN-8 `zh_CN` sin claves del waiver · CAJ-6 el foco cae a `<body>` al firmar · CAJ-7
el texto legal a 12 px atenuado · CAJ-8 el refresco del contexto puede pisarse con otro en vuelo ·
API-6/7/8/9 precisiones de contrato (`document_id` acepta `"2"`; el 201 del señuelo deja de ser
indistinguible con `accept_waiver`; `GET /legal/waiver` sin `Vary: Accept-Language`) · WAI-08 la puerta
no registra que alguien entró con versión anterior · WAI-13 el respaldo `es` para un francófono ·
DOC-6/7/9/10/11/12/13/14 (§7 dice «dos» pendientes; §4 sigue describiendo `retención_hasta` y
`consents`; `RGPD-06`/`SEC-09` no citan el waiver; `SEGURIDAD.md`, `GLOSARIO`, `MODELO-DATOS`
(`consents.version` del waiver es `vN·xx`), `DEUDA.md` sin las fichas — **las fichas van hoy**).

### 10.10 Estado de §6, ítem por ítem (DOC-8)

| §6 | Estado | Evidencia |
|---|---|---|
| 1 versión publicada inmutable | ✅ | `LegalDocumentVersionTest` (mutación §9.3) |
| 2 el PDF sale del snapshot | ✅ | `WaiverProofPdfTest::test_editing_the_page_and_publishing_again…` (§9.6) |
| 3 `anonymize()` conserva Y purga | ✅ | `WaiverRetentionTest` (RGPD-01) |
| 4 rechazo sin id de versión | ✅ | `MeWaiverTest`, `AuthRegistrationTest` (§9.8) |
| PDF real generado y LEÍDO | ✅ **2026-08-26** | §9.10: 5 PDF descargados por HTTP y leídos con `pdf-parse` (nombre, correo, idioma, marcador de versión) |
| mismo contenido dos veces | ✅ | `test_the_document_is_deterministic` — ⚠️ con la salvedad de PAN-4 (§10.5) |
| alta en navegador en TRES idiomas + idioma del snapshot | ✅ **2026-08-26** (headless) · ⬜ ojo del owner | §9.10 V35: alta en EN, firma en FR, PDF en el idioma firmado |
| purga con reloj congelado | ✅ | `WaiverRetentionTest` con `travelTo` |

### 10.11 Veredicto, y lo que exige ANTES del ✅ del owner

**El diseño y las cuatro tandas se sostienen**: lo que aguantó (§10.0) es el corazón del subsistema
—inmutabilidad, cadena por titular, snapshot, retención, permisos, contrato— y **ninguna de las 68
afirmaciones verificables resultó falsa en lo esencial**. Lo que no aguantó es la **frontera**: lo que
el cliente puede decidir sobre su propia prueba (§10.2), lo que el mostrador declara sin declarar
(§10.1), tres carreras entre lo enseñado y lo firmado (§10.3) y lo que el PDF afirma de más (§10.6).

❗ **Antes de que una instalación entre en modo `interno` con una versión publicada** (ninguna lo está):
1. ✅ **Decisión de producto** (§10.1): la casilla del waiver en el alta manual — decidida y hecha (§7·4, `#178`).
2. ✅ **Decisión** (§10.2·3): correo verificado para firmar — decidida y hecha (§7·5, `#179`).
3. **Código, pequeño y acotado** — la TANDA 4, en marcha desde la noche del 26/08 (§9.11): ✅ canal
   por guard (API-1, `#174`) · ✅ idempotencia por versión (API-3, `#174`) · ✅ `legal` releído en
   el 422 del alta y en el 409, casilla desmarcada tras el 409, id enviado = id ENSEÑADO (CAJ-1/2/3,
   `#175`) ·
   ✅ tres marcadores en la guarda + aviso de palabras (§10.4, `#174`) · ✅ el infolist por
   `WaiverStatus` (PAN-5, `#174`) · ✅ throttles con prefijo en las dos rutas del waiver (API-2,
   `#174`; el del pago queda en `DEUDA`) · ✅ **el widget del anti-bot en el alta suelta** (`#171`) ·
   ✅ `WaiverSigner` en el `CRITICAL_RE` (NUC-6, `#174`) · ✅ las tres decisiones del owner que son
   código: **la casilla del alta manual** (§7·4, `#178`), **la casilla del alta OBLIGATORIA en
   interno** (§7·7, `#178`) y **correo verificado para firmar** (§7·5, `#179`) · ✅ **y la revisión de la
   propia tanda, aplicada** (§9.12, `#183`).
4. ✅ **Texto del PDF** (`#180`): «Coincide (comprobación interna)» + nota de lo que garantiza y lo que no;
   el pie sin «fila inmutable»; en mostrador, datos tecleados por el operador e IP/UA del puesto (WAI-07);
   `WaiverChain` cruza la versión (NUC-8).
5. Las decisiones humanas de siempre: **texto definitivo** y **plazo** (con la precisión de §10.8). El
   aviso en el paso de pagar ya está decidido: no (§7·3).

Las fichas están en `DEUDA.md` (una por grupo, con su severidad), y la entrada `DECISIONES #169`
recoge el porqué de cada veredicto.
