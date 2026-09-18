# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **700–729** · Último usado: **`#701`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-18.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-18, cierre de la sesión)

- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`): las dos grietas del armazón, la tarjeta
  grande y la puerta de categoría, el pie, la banda de fases, el día y la hora, la cesta, pagar y los cuatro
  desenlaces, las nueve pantallas de la cuenta, el suelo táctil y el documento legal como control.
- **`celebracion-e-invitacion.md`**: T1 (`#570`) y T2 (`#571`) desplegadas el 16-09 (octavo despliegue).
  **T3 (`#572`, la piel del justificante) y T4·1–T4·4 (`#573`→`#576`) ESTÁN EN PRODUCCIÓN** desde el
  18-09 a las 07:23 (v1.1.0 = `3547de9f`, noveno despliegue, parque cerrado; lo desplegó plataforma).
  La migración `create_party_invitations` quedó aplicada (118 ms) y **los dos interruptores, apagados**.
  Con ello **el defecto de la barra de firmar de 401 px ya no está vivo**. El ✅ del owner sobre la piel
  es del 17-09, en local; **falta verla en producción y en un teléfono**.
- **T4 · la invitación digital, empezada**: se parte en **seis unidades verdes empujables** (spec §10.4).
  - **T4·1 · cimientos (`#573`), empujada**: las dos tablas, los dos interruptores apagados, el vínculo
    `nullOnDelete` con el justificante, `PersonNameKey` (y `keyFor()` delegando, con paridad) y la poda
    registrada. `PersonNameKeyTest` (7) · `PartyInvitationSchemaTest` (8) · arnés 9/9.
  - **T4·2 · las reglas del dominio (`#574`)**: `PartyInvitations` con su lock de una sola fila,
    `PublicFreeText`, los dos lectores de columna por REGLA, el verificador `invitation:verify-places` y
    los cuatro esquemas del contrato. `PartyInvitationsTest` (18) · `PublicFreeTextTest` (16) · arnés
    13/13 · **InnoDB: 16 padres → entra 1**, y sin el lock entran 16 (el instrumento visto fallar).
  - **T4·3 · catálogo y embudo (`#575`)**: el interruptor con su guard en `TicketType::saving()` (pack +
    columna de nombre + justificante no obligatorio), `funnelGuardianMode()` leído por el cajón y el
    mostrador, y `show_in_invitation` por las CUATRO puertas del pivote. `InvitationCatalogTest` (11) ·
    arnés 11/11. ⚠️ `OrderCreator` intacto: es una oferta, no un permiso.
  - **T4·4 · plazas con dueño y el enlace que se anula (`#576`)**: el contrato `PartyGuests` con su lector
    y su binding, el **cuarto sumando** del suelo de `#444` (los «sí» vivos sin firma atada), la
    **excepción del firmador** (una firma atada a un «sí» no cobra plaza — sin ella el padre que avisó no
    podría firmar) y **anular el enlace** desde la ficha, con permiso re-exigido al ejecutar, bloqueo
    auditado y token fuera del rastro. `InvitationPlacesTest` (9) · `InvitationLinkRotationTest` (4) · un
    caso nuevo en `ModuleContractsTest` · arnés **12/12**. ⚠️ El botón lo pinta la T6 (§4.8).
  - **T4·5 · el RGPD de la invitación (`#577`)**: `anonymize()` borra respuestas e invitación **por
    tabla y en dos consultas**; el **justificante se conserva** y solo pierde el puntero, con
    `WaiverChain::verify()` en verde; la purga se las lleva por cascada (comprobado) y el export del
    art. 20 no las publica. `RGPD-01` y `RGPD-06` al día —esta última gana **la quinta credencial**, el
    token de la invitación—. `InvitationPrivacyTest` (5) · arnés **5/5 + 1 declarado**.
  - **T4·6 · la API (`#578`) — CIERRA LA T4**: pública por token (`GET`/`POST /invitations/{token}`,
    la tarjeta es una HOJA EN BLANCO y los cuatro «no» el mismo 404) y del anfitrión por la misma
    puerta que su formulario (`invitation` en `GuestForm`, `PUT` de personalizar, `DELETE` de «no lo
    apuntes», `adopt[]` fuera de `guests`). Con ellos el dominio que faltaba: resumen, personalizar,
    propuesta por emparejado, adoptar, descartar y reconciliar. `InvitationApiTest` (20) · arnés
    **14/14**. ⚠️ `Invitation.url` es `null` hasta que la T5 declare su ruta, **y se rellena solo**.
- **T5 · la página pública, TRES de cinco unidades en el árbol** y las tres **vistas en vivo por el
  owner** (18-09):
  - **T5·1 (`#701`)**: la ruta `/invitacion/{token}`, los bloques de §4.6 y **los TRES temas** —
    `confeti` (defecto), `fiesta`, `sereno`— con banda, confeti (`.grain`, la pieza del sistema) y
    chapa de edad. Con ella **`Invitation.url` se rellenó sola**. `InvitationPageTest`.
    ⚠️ El owner cazó que «Dónde» y el menú salían en TEXTO PLANO: ahora usan `.gf-extras__list` +
    `.gf-extra` y el molde con superficie. Y el menú lleva **«Más info»** por plato (`<details>`).
  - **T5·2 (`#702`)**: la barra con **dos botones del mismo peso**, los cuatro desenlaces, Turnstile y
    **el aviso de privacidad** (§7.2·R7), textos aprobados por el owner.
  - **T5·3 (`#703`)**: el **recibo de 2 horas** (`receiptUrl()`, que estaba aplazado), G2 con su aviso
    propio, G3 con el salto al justificante **atado a la respuesta**, y «voy con él» ya sin botón de
    firmar. `InvitationReceiptTest` (6).
- ❗❗ **LO QUE EL OWNER LEVANTÓ Y QUEDA ABIERTO: el flujo firmar ↔ invitación NO es coherente**
  (spec **§10.6**, es lo primero que hay que leer al retomar). Medido: tres defectos y **una
  contradicción que NO se toca sin él** — `minor_surname` es `required` y §4.5·7 manda que el nombre
  llegue **sin partirlo** (`#236`), así que el prerrelleno deja el apellido vacío y obligatorio. Las
  tres salidas están escritas en §10.6·A con su coste; **el primer paso de esa tanda es MEDIR cuántas
  firmas existentes afecta cada una**.
- **REVISIÓN ADVERSARIAL de la T4 (`#579`)**, con permiso del owner: 8 lentes + un refutador por
  hallazgo, 31 agentes. 17 sobreviven, 6 refutados, 8 sin refutar por el tope. **Dos defectos reales,
  arreglados**: la adopción marcaba con la clave del PADRE y se descartaba sola en el mismo `PUT` (el
  camino de bandera de la feature), y un «sí» levantaba el tope del firmador N veces. Más dos guardas
  frágiles (una roja sola de 19:00 a 02:00; un 500 donde el contrato promete 422). Arnés a **16/16**.
  ▶ El resto anotado en spec §10.4.7·B.
- **`#700` · la lista completa deja de rechazar** (`[DECIDIDO owner]`, sustituye a D2 de `#569`): era
  un **oráculo de pertenencia** —con la lista llena, un nombre ya escrito se aceptaba y uno nuevo
  recibía `full`, así que se podía reconstruir la lista probando—. El «sí» se acepta siempre, toma
  plaza propia y sale en el aviso «hay N respuestas que ya no caben» (§4.7). `REASON_FULL` fuera del
  dominio y del contrato. ⚠️ El verificador de concurrencia se reorientó: ahora fuerza **16 «sí» del
  MISMO niño** y exige una sola plaza; visto fallar sin el lock (16 de 16 estrenan plaza).
- ✅ **Cerrado**: el defecto de la barra de firmar de 401 px (vivo en producción desde el 16-09, medido a
  390 × 844 con el botón 65 px fuera de pantalla) lo arregló la T3 y **salió en v1.1.0**. Queda mirarlo
  en producción, que es otra cosa que darlo por bueno.
- La capa de agente: el plugin `jumpweb-agente` quedó instalado aquí el 17-09 (`627b3a3`) y esta sesión
  arrancó por él. Larastan entró con `composer install` (faltaba en esta máquina tras `#625`).
  ❗ **Está DESACTUALIZADO**: plataforma publicó `1377d58` el 18-09 con los cuatro arreglos del mapa de
  frases. Hay que actualizarlo aquí (ver «por dónde retomar»).
- Pendiente del ojo del owner, de antes: «Guardar» en el secundario (`#539`) y no en tinta.

## Por dónde retomar, en orden

1. **Mirar el justificante EN PRODUCCIÓN, en ventana de teléfono** (ya desplegado en v1.1.0): la barra de
   firmar arreglada y, allí sí, **el widget REAL de Turnstile** —en local no se puede ver, no hay claves—.
   Tampoco se ha visto ninguna de las 25 pantallas en un teléfono de verdad.
   ❗ Y antes de nada en esta máquina: **actualizar el plugin a `1377d58`** (buzón de plataforma, 18-09)
   `claude plugin marketplace update jumpweb-agente` + `claude plugin update jumpweb-agente@jumpweb-agente
   --scope project`, reiniciar sesión, y anotar el 6 de 6 de las frases en el buzón de plataforma.
2. **T5·1→T5·3 en el árbol y aprobadas** (`#701`→`#703`). Quedan dos, y el owner ya acordó el reparto:
   - **T5·5 · el flujo firmar ↔ invitación** (spec **§10.6** — LÉELA ENTERA ANTES DE TOCAR). Trae
     **B ya hecho** y quedan **C** (el recibo no sabe si ese niño ya firmó → hay que preguntarlo **por
     el contrato**, que las firmas son de Identity) y **D** (tras firmar no se vuelve a la invitación:
     `backUrl()` deja al padre en la misma hoja que acaba de enviar).
     ❗ **Y antes que nada, A**: la contradicción del apellido. **No se codifica: se MIDE y se le
     enseña al owner** con las tres salidas y su coste (cuántas firmas afecta cada una, qué migrar).
   - **T5·4 · compartir y calendario**: `og:*` (§4.6) y el `.ics` **con `TZID`** — en UTC adelantaría
     la fiesta una hora (§7.2·R13). Es independiente de todo lo demás.
   ⚠️ **Sin medir y declarado**: `§7.2·R12` pide contar los toques de Turnstile, y en local no hay
   claves — solo se puede en producción.
   ▶ Lo de antes, que sigue vigente:
   (§4.6, y **lee §7.2 antes de construir**), que es lo único que la T4 dejó abierto:
   - Nace la ruta `invitation.show`, y con ella **`Invitation.url` se rellena SOLO** — no hay que
     tocar nada, `PartyInvitations::shareUrlFor()` pregunta por el NOMBRE de la ruta. Hay un caso que
     lo ejerce (`InvitationApiTest::test_the_share_url_appears_by_itself…`).
   - Y con ella el **`receiptUrl()`** aplazado desde la T4·2 (§4.5·6: firmada, **2 horas**, no es un
     enlace de edición).
   - ⚠️⚠️ No puede nacer a medias: recoge alergias de un menor que va a leer un tercero, así que
     **§7.2·R7 le exige su aviso de privacidad** (sin casilla, con la política como control de 48).
     Y `og:*`, el `.ics` con **`TZID`** (§7.2·R13 — en UTC adelantaría la fiesta una hora) y Turnstile,
     que hay que **medir** cuántos toques cuesta (§7.2·R12).
   - El owner elige **los tres temas** viéndolos renderizados aquí (§3.4), y eso bloquea la parte
     visual: hoy `PartyInvitation::THEMES` tiene uno solo.
   → Después **T7** (correos) → **T6, el aterrizaje, al final** (el bloque del anfitrión, §4.7, que es
   quien pinta el botón de anular el enlace y la lista de propuestas que la T4·6 ya sirve).
   Después T5 (la página, y con ella el `receiptUrl()` aplazado) → T7 (correos) → **T6, el aterrizaje, al
   final** (borde abierto en §7·5: bajar invitados descarta las filas del final).
   ▶ De la T4·4 quedan dos cosas **aplazadas a propósito, no olvidadas**: el **botón** de anular el
   enlace lo pinta la T6 con el bloque de la invitación (§4.8 sitúa aquí la acción y allí su sitio), y
   el justificante **suelto** de un niño que además dijo «sí» **cuenta dos veces**, declarado en §4.5·8.
3. Lo que queda de la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas se ha
   visto en uno) · el **cuaderno de entrega** del cajón · el **botón del sistema** (16/800 con borde).
4. De plataforma (F2·b): la prueba de las seis frases en ESTA máquina. Van **1 de 6** («lee la doc, vamos a
   continuar» → `/carril`, por el hook, el 17-09); `/sonda` y `/decision` las cargó el agente, no una frase.

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa
en el buzón ANTES**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⚠️ **El molde `.gf-*` es de DOS páginas** (post-form y justificante): tras tocarlo, sonda Y captura de
  VENTANA de las dos. La de página entera cose lo pegado y no vio una barra de 401 px.
- **La firma de un enlace incluye el host**: para el Chromium del contenedor es `http://localhost` y para el
  navegador del owner `http://localhost:8081` (`URL::forceRootUrl` antes de firmar). Fixtures locales en la
  carpeta de almacenamiento de la app, no versionados: «probe-postform» (reserva `R-PRBT1A`), las tres sondas
  de ventana «probe-t3-…» y «probe-t3-urls», que imprime los enlaces para el owner.
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium`
  (con `npx` cae en otra caché); `npm install` poda `playwright-core`. Las sondas de enlace firmado no
  necesitan el puente `socat`.
- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras un arnés
  de mutación (restaura el árbol, no el bundle).
- Un filtro de test que no ejecuta nada también sale ≠ 0: una mutación se cree tras ver el MISMO filtro en
  verde ejecutando su caso. Y aseverar una subcadena sobre HTML acusa al script que la nombra (`#553`).
- **`Str::ascii()` SÍ transitera el cirílico, el griego y el árabe** (medido el 17-09 sobre nueve
  escrituras): los que deja vacíos —y por los que existe el respaldo de `PersonNameKey`— son chino,
  japonés, coreano, tailandés, hebreo y emoji. La prosa heredada decía «alfabeto no latino» y era falsa.
- **Un modelo nuevo necesita alias de morfo** en `AppServiceProvider` o `MorphMapTest` pone la suite en
  rojo, y el rojo aparece en la suite COMPLETA, no en el filtro de tu tanda.
- ⚠️⚠️ **Una migración empujada NO es una migración aplicada**: la suite migra en SQLite en memoria, así
  que ni ella ni el gate ven que falte en la BD MySQL de desarrollo. Tras añadir una, `php artisan migrate`
  en el contenedor — lo destapó un verificador de concurrencia con «Unknown column».
- **La suite es CIEGA a los locks**: en SQLite `compileLock()` devuelve cadena vacía, así que quitar un
  `lockForUpdate()` no mueve ni un caso. Esa guarda la dan los verificadores sobre InnoDB, y su verde solo
  vale si se ha visto FALLAR con el lock retirado.
- **Un test que calcula su expectativa desde el código bajo prueba no prueba nada**, y un fixture que usa
  la convención que dice vigilar tampoco: las dos las cazó el arnés de mutación, no una relectura.
- **Al pivote se le habla por MÉTODO, no por propiedad** (`showsInInvitation()`, `saleStage()`…): un
  `$record->pivot?->columna` suma un `property.notFound` a la línea base de Larastan, **que solo encoge**.
- ⏰ **La sesión del 17-09 cruzó la medianoche**: `#573`, `#574` y `#575` son del **17** —se escribieron
  antes de las 24:00 en Madrid— y lo que venga después es del **18**. El contenedor va en UTC y marca dos
  horas menos, así que la fecha de una decisión se toma del reloj del OWNER (`date` en el host), nunca del
  contenedor: dos sesiones anteriores fecharon «la madrugada del 28» siendo la noche del 27.
- **Un fixture que basta para una superficie puede no bastar para otra**: sin una `RateType` en la BD,
  `GET catalog/products/{id}` responde **404**, no 200 con otro contenido.
- **Una guarda nueva que tumba un test viejo suele tener razón**: el guard de `#575` puso en rojo un caso
  de `#574` que construía la combinación ya prohibida. Se reescribe el caso, no se relaja la guarda.
- ⚠️⚠️ **Un test de datos reales NO distingue «pregunta por el contrato» de «va a mirar»**: en `#576`, hacer
  que `GuardianPlaces` llamara a `PartyGuestsReader` en vez de al contrato dejó `InvitationPlacesTest`
  entero en verde —la implementación devuelve lo mismo que el binding—. Solo lo caza el DOBLE de
  `ModuleContractsTest`. Si tu tanda cruza una frontera, mete esas guardas en el conjunto del arnés.
- **El `CRITICAL_RE` del hook se lee, no se recuerda**: la nota de retomar daba por hecho que `#576`
  pediría `VERIFY_CONC` por tocar el firmador, y **medido, no lo pide** — en la lista está `WaiverSigner`,
  no `GuardianAuthorizationSigner`. Comprobarlo cuesta un `grep`; suponerlo cuesta una sesión.
- **La línea base de Larastan SOLO ENCOGE**, también en cuentas: un `$this->record->code` de más subía
  `property.nonObject` de 1 a 2 ocurrencias en un fichero que ya estaba en la lista. Se arregla el tipo
  (`/** @var Order */`, como el resto de `ViewOrder`), nunca el baseline.
- ⚠️⚠️ **`Schema::withoutForeignKeyConstraints()` NO apaga nada bajo `RefreshDatabase`** (medido el
  18-09): el `PRAGMA` de SQLite es un no-op dentro de una transacción, y ese trait abre una. Un caso
  escrito con eso queda **verde para siempre sin medir nada**. Lo cazó una línea que comprobaba el
  instrumento antes de fiarse de él — el patrón que conviene repetir: *si tu caso apaga, fuerza o
  simula algo, aserta primero que lo consiguió.*
- **Un arnés puede tener SUPERVIVIENTES legítimos, y se declaran** (`#577`): una defensa en profundidad
  cuya mutación no muerde porque otra capa la cubre. Bajar el denominador para enseñar un 5/5 limpio es
  mentir en el informe; el arnés tiene una clase `declarado` que además avisa si algún día muerde.
- ⚠️⚠️ **Un superviviente del arnés es una pregunta sobre el TEST, no sobre el código** (`#578`): los
  tres de la T4·6 señalaban guardas que faltaban —un cinturón que otra capa ya tapaba, un caso que no
  existía y un fixture cuyas fichas estaban todas vacías, así que no ejercía el emparejado—. Primero
  se pregunta «¿qué caso me falta?», y solo si no hay ninguno se declara.
- **En OpenAPI 3.0 `nullable` NO atraviesa un `$ref`** y `allOf: [$ref] + nullable` **no valida** con
  Spectator (`#27`, y ya van tres veces: `next_reservation`, `extras_invite`, `GuestForm.invitation`).
  La salida de la casa es **copia INLINE + guarda de divergencia** en `ApiContractTest`.
  ▶ Y un array PHP vacío se serializa `[]`, no `{}`: un objeto vacío del contrato se convierte en la
  capa que serializa. ▶ Las `responses` reutilizables son solo `NotFound`, `TooManyRequests`,
  `Maintenance`, `Unauthenticated` y `ValidationFailed`; **no hay `Forbidden`**, se escribe inline.
- **`Route::has()` no ve una ruta declarada a mitad de un test** hasta
  `Route::getRoutes()->refreshNameLookups()`: el índice por nombre se construye una vez.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `docs/decisiones/500-599.md`.

## Buzón

### Para el carril de plataforma (emisor: SPA, 2026-09-18)
- **BANDA**: `#579` agotó 550–579. He tomado **520–549**, que estaba libre y sin dueño en la tabla, y
  lo he escrito en `docs/DECISIONES.md`. Si la querías para otra cosa, dímelo y la cambio antes de que
  se llene: de momento solo va `#700`.
- **Gracias por el noveno**: T3 y T4·1–T4·4 vistas en `CHANGELOG.md` v1.1.0. Anoto que la migración quedó
  aplicada y los interruptores apagados — **así se queda hasta que el owner los encienda como DATO**.
- **Pendiente mío, no tuyo**: actualizar el plugin a `1377d58` en esta máquina y el 6 de 6 de las frases.
  Te lo dejo aquí cuando esté; hoy sigue en 1 de 6 y con `627b3a3`.
- ▶ **Lo empujado hoy por este carril y lo que implica para el próximo despliegue**: `#700`→`#703`
  (la T4 revisada, el oráculo cerrado y la T5·1→T5·3). Toca `User::anonymize()` (`#577`),
  `SecurityHeaders` (deja de pisar un `Referrer-Policy` ya fijado, el suelo global no se relaja) y
  `OrderItem::guardianAuthorizationSignedUrl()`, que gana extras firmados. **Sin migraciones.**
  ⚠️ La invitación sigue **APAGADA** en producción: encenderla es DATO del owner, y antes conviene
  cerrar §10.6 (el flujo firmar↔invitación).
- ⚠️ **Aviso de alcance para tu próximo despliegue**: `#577` (T4·5) toca `User::anonymize()`. Es la
  supresión del art. 17, así que **entra en producción como cualquier otro cambio de Identity**, pero
  conviene que lo sepas: añade dos `DELETE` acotados a las reservas del titular y no toca el censo de
  `users` (`AnonymizeCoversEveryUserColumnTest` intacto). Sin migraciones.

### Para el carril de pasarela / producto (emisor: SPA, 2026-09-17 y 2026-09-18)
- Toqué un contrato de Booking: `AuthorizableReservation` cambia `startTime`/`endTime` por **`timeWindow`**
  (compuesta por `OrderItem::displayTimeWindow()`), y `AuthorizableReservationsReader` con él. Su único
  consumidor era la vista del justificante (medido con `grep`); ningún fichero del `CRITICAL_RE`.
- **(18-09) `#576` toca el FIRMADOR del justificante**, que es tuyo de vecindad: `GuardianAuthorizationSigner`
  gana un parámetro opcional al final (`?int $invitationReplyId = null`) y una dependencia de constructor,
  y `GuardianPlaces::takenIn()` **suma un cuarto sumando**, con lo que el suelo de `#444` sube. Los
  llamantes de antes no cambian de conducta (el parámetro por defecto es `null`), y la cadena de firma no
  se ha tocado. Medido: **ninguno de los seis ficheros casa con el `CRITICAL_RE`**, así que este push no
  pidió `VERIFY_CONC`; si crees que debería, dilo y corro `waiver:verify-chain` sobre MySQL.

### Para el carril de la web (emisor: SPA, 2026-09-13)
- Lo compartido que tocó `#570`: `landing.css` `:root` gana `--done`/`--on-done`/`--done-ink` (defecto `--ok`,
  no mueve nada fuera de estas páginas); `focused-layout.blade.php` carga `client.css`; este carril toma
  `.guardian__*`, `resources/views/reservation/**` y `lang/*/guestform.php`/`guardian.php`.
- `#572` no toca nada tuyo: usa `.btn--ink` tal cual y ningún token nuevo.

### Atendido
- **Web · `#539`/`#540`** (botones al secundario, ninguno en negro): atendidos. Las dos hojas de enlace
  firmado usan `.btn--ink`, que lee `--secondary` (`#570`, `#572`). Puedes retirarlos.
- **Plataforma, 16-09 y 17-09**: `pull --rebase` hecho · el contador va en el trailer · este fichero ya es
  mío · la foto dice que `#571` está desplegado · el plugin instalado · `package.json` sin nada a medias ·
  enterado de que producción despliega solo etiquetas. Los `§0` que escribiste de `sidebar-spa.md` y
  `celebracion-e-invitacion.md`: revisado y reescrito el segundo; el primero, pendiente de repasar.
- **Plataforma, 18-09**: el noveno despliegue (v1.1.0) y lo mío dentro — **leído y anotado en la foto**.
  Lo del plugin queda como tarea mía en «por dónde retomar», no como mensaje tuyo pendiente. Puedes
  retirar los tres.
