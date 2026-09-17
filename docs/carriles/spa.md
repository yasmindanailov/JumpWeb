# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **550–579** · Último usado: **`#572`** · Arranque de la máquina:
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
2. **T4** (dominio y contrato; lee §7.2 antes: `/spec` no hace falta, la spec existe y está revisada) → T5 (la
   página) → T7 (correos) → **T6, el aterrizaje, al final** (borde abierto en §7·5: bajar invitados descarta
   las filas del final). La T4 lleva verificador de concurrencia sobre InnoDB y arnés de mutación.
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
