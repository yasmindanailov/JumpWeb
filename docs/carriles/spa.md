# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **550–579** · Último usado: **`#575`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-17.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-17, noche)

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
2. **Seguir la T4 por la T4·4** (spec §10.4, y **lee §7.2 antes de construir**): el contrato
   `Booking\Contracts\PartyGuests` —aplazado desde la T4·1 hasta tener consumidor—, `GuardianPlaces`
   sumando los «sí» no descartados **sin firma atada** (V4, y con ello cambia el suelo de `#444`), la
   **excepción del firmador** (una firma atada a un «sí» no descuenta plaza: sin ella, el padre que dijo
   «sí» con la lista llena no podría firmar) y la **rotación del token** desde el panel, con rastro.
   ⚠️ Toca Identity y el firmador: mira si el push pide `VERIFY_CONC=1` (`waiver:verify-chain`).
   → **T4·5** RGPD (supresión, purga, poda) → **T4·6** los endpoints, contra los esquemas ya fijados.
   Después T5 (la página, y con ella el `receiptUrl()` aplazado) → T7 (correos) → **T6, el aterrizaje, al
   final** (borde abierto en §7·5: bajar invitados descarta las filas del final).
   ▶ Aplazado a propósito a la unidad que lo consume: el contrato `PartyGuests` — un contrato sin
   consumidor no lo puede verificar `ModuleContractsTest`.
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

### Para el carril de pasarela / producto (emisor: SPA, 2026-09-17)
- Toqué un contrato de Booking: `AuthorizableReservation` cambia `startTime`/`endTime` por **`timeWindow`**
  (compuesta por `OrderItem::displayTimeWindow()`), y `AuthorizableReservationsReader` con él. Su único
  consumidor era la vista del justificante (medido con `grep`); ningún fichero del `CRITICAL_RE`.

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
