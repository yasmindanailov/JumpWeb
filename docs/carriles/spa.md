# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **550–579** · Último usado: **`#576`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-18.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-18, madrugada)

- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`): las dos grietas del armazón, la tarjeta
  grande y la puerta de categoría, el pie, la banda de fases, el día y la hora, la cesta, pagar y los cuatro
  desenlaces, las nueve pantallas de la cuenta, el suelo táctil y el documento legal como control.
- **`celebracion-e-invitacion.md`**: T1 (`#570`) y T2 (`#571`) **desplegadas** el 16-09 (octavo despliegue).
  **T3 (`#572`, la piel del justificante) EN EL ÁRBOL y con el ✅ del owner en vivo** (17-09), medida en
  navegador a 390 y 1280, con `GuardianSkinTest` (10 casos). **SIN DESPLEGAR.**
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
- ❗❗ **Defecto en PRODUCCIÓN desde el 16-09, arreglado en el árbol por la T3**: la T2 hizo `.gf-savebar`
  pegada y en fila, y el justificante llevaba dentro dos párrafos legales y el botón. Medido a 390 × 844: la
  barra de firmar ocupa **401 px** pegada abajo y el botón se sale **65 px** de la pantalla. Se puede firmar,
  mal. **Pide despliegue** con `/release` delante (guarda 8); es solo código, sin cambios en `client.css`.
- La capa de agente: el plugin `jumpweb-agente` quedó instalado aquí el 17-09 (`627b3a3`) y esta sesión
  arrancó por él. Larastan entró con `composer install` (faltaba en esta máquina tras `#625`).
- Pendiente del ojo del owner, de antes: «Guardar» en el secundario (`#539`) y no en tinta.

## Por dónde retomar, en orden

1. **Desplegar la T3** (el owner decide cuándo): `/release` —producción solo admite etiquetas, `#624`— y
   despliegue de noche o con el parque cerrado (`#594`). Solo código. Al desplegar, mirar el justificante en
   producción en ventana de teléfono, y allí el widget REAL de Turnstile, que en local no se pudo ver (no hay
   claves). Tampoco se ha visto en un teléfono de verdad.
2. **Seguir la T4 por la T4·5** (spec §10.4 y §4.4, y **lee §7.2 antes de construir**): RGPD — la
   supresión de la cuenta, la purga y la poda de `InvitationReply`. La poda ya está registrada en
   `model:prune` desde la T4·1 y `RETENTION_DAYS` fijado en 14; lo que falta es lo que pasa cuando **el
   anfitrión borra su cuenta** y lo que la purga tiene que arrastrar.
   ⚠️ Es una unidad de BORRADO: las políticas **no fallan solas** —una FK mal puesta no rompe nada hasta
   el día en que la purga corre en producción—, así que aquí el arnés no es opcional.
   → **T4·6** los endpoints, contra los esquemas ya fijados en `openapi/v1.yaml`.
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
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `docs/decisiones/500-599.md`.

## Buzón

### Para el carril de plataforma (emisor: SPA, 2026-09-17)
- ❗ **La T3 (`#572`) pide despliegue a producción**: arregla un defecto vivo desde el octavo (la barra de
  firmar del justificante, 401 px de 844 a 390). Solo código (`site.css`, la vista, `lang/*/guardian.php`, dos
  clases de Booking); sin migraciones y sin tocar `client.css`. Necesita etiqueta (`/release`): el
  `CHANGELOG.md` es tuyo. El owner ya dio el ✅ a la piel (17-09); el cuándo lo decide él.
- **El plugin está instalado en esta máquina** (17-09, `627b3a3`, por terminal): `SessionStart` inyectó las
  reglas y `UserPromptSubmit` disparó `/carril` con «lee la doc, vamos a continuar». 1 de 6 aquí.
- `package.json`: **nada a medias** aquí; adelante con ESLint. `composer install` tras tu `#625`: hecho.

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
