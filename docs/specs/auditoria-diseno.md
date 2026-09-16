# [AUDITORÍA] Costuras a la vista — segunda auditoría de diseño de la web pública

> Estado: 🟦 **INFORME ENTREGADO · TANDAS C, E, H, D y F EJECUTADAS (§11 `#434`, §12 `#435`, §13
> `#436`, §14 `#437`) · D1, D4, D5 y D6 decididas; queda la G (interiores y normas: D2, D3), que se
> valora con la organización, y C1, que es producto** ·
> ⏸️ **EL CARRIL DE DISEÑO ESTÁ PARADO (`#452`, 2026-09-03, `[DECIDIDO owner]`)**: el owner rehace el
> sistema en Claude Design y avisará con la base; **la G no se hace hasta entonces**. Las tandas C→F
> siguen en código con sus guardas. El `design.md` que citan §11–§14 vive ya en
> `docs/archivo/design-producto-2026-09-03.md`; la auditoría hermana del cajón, en `docs/archivo/auditoria-cajon.md`, con sus tandas revertidas.
> Última actualización: 2026-09-03 · Decisión asociada: `DECISIONES #430` (el informe vive en el
> repo; el artefacto es solo la presentación) · Carril: **diseño / idioma visual** (este ordenador,
> sub-banda `#430`–`#439`, reservada a distancia de la secuencia natural `#410`+ que sigue el
> otro carril de esta máquina, `specs/hora-extra.md` — aforo, sin solape de ficheros).
> ▶ **Sustituye al informe «Un solo idioma» (2026-09-01)**, que se publicó como artefacto y **no es
> recuperable**: no está en la cuenta del owner, ni en el repo, ni en local (medido: 5 artefactos
> listados con `scope: all`, ninguno es él). De él sobreviven el resumen en `ESTADO.md` y las dos
> tandas ejecutadas (`#321` el botón · `#323` la tarjeta). **Por eso este informe es un fichero.**
> Artefacto de LECTURA (presentación; si desaparece no se pierde nada): «Costuras a la vista» —
> https://claude.ai/code/artifact/4d0ce068-150e-4f33-86eb-96ba071c9f40

## §0 · Antes de tocar

- **La segunda auditoría de diseño de la web pública, EN EL REPO** (`#430`; la primera se publicó como
  artefacto y se perdió con su URL). 3 críticos · 10 mayores · 6 menores. **Tandas C, E, H, D y F ejecutadas**
  (§11–§14, `#434`→`#437`) con sus guardas; la G se decide dentro de `rediseno-desde-canvas.md` (`#469`).
- **§6 son las seis puertas de Hallmark que fallan A PROPÓSITO** (decisiones del owner): no las «arregles».
  §7 las decisiones que son suyas; §9 las cuatro trampas de instrumento pagadas.
- **Lo que quedó en código y no se regresa**: el par de texto lo declara quien declara el color (`--on-ok`,
  `--on-err`; el suelo de 10 px con guarda) · 19 hovers que saltaban ya no saltan, `transition: all` fuera y
  **cada `infinite` está enumerado con su motivo** (`MotionBudgetTest`: añadir un bucle es decidirlo) ·
  **`--interactive` por superficie** (D1) y `--zone-*` solo para lo que IDENTIFICA una zona
  (`InteractionColourIsNotAZoneTest`) · 662 literales al token del mismo píxel con cero reflujo medido
  (`ScaleTokensAreUsedTest`; lo que no tiene escalón se queda en literal, sin `calc()` a mano).
- **Pasos de despliegue de estas tandas**: cinco líneas en el `client.css` de producción.
- **Anexo al final**: la fila del enrutador «Cambiar el idioma visual HEREDADO» (119 avisos), que cubre también
  `idioma-visual-heredado.md`.

## 0. Lo que hay que saber en un minuto

- **Método**: `hallmark audit` (la skill, leída entera: 58 puertas, anti-patrones, estructura,
  responsive, el género `playful`) sobre **las 12 vistas públicas × 8 anchos** (320 · 375 · 390 ·
  414 · 768 · 1280×800 · 1440 · 1920) con Playwright + Chromium **con CONTROL** en cada sonda, más
  un barrido estático de las dos hojas (622 KB) con los comentarios y los `data:` fuera antes de
  contar. Instrumentos gitignorados: `storage/app/audit-hallmark.mjs` y `audit-hallmark-2.mjs`;
  salida en `storage/app/audit/` (JSON + 36 capturas).
- **Veredicto: 3 críticos · 10 mayores · 6 menores.** La tesis del informe anterior («dos
  generaciones del sistema conviviendo») **ya no describe lo que hay**: la capa de tokens existe y
  es buena, y las tandas A y B se sostienen. Lo que queda son **costuras**: sitios donde un literal,
  un rol prestado o un hover viejo se colaron por debajo del sistema sin que ninguna guarda mirara.
- **Lo peor no es de gusto, es de LEGIBILIDAD**: el cian de zona pinta la pregunta abierta de la
  FAQ a **2,45 : 1** (AA pide 4,5), el rótulo «O INICIA SESIÓN» del CTA del armazón sale a
  **2,61 : 1** en las doce vistas, y «Incluido» a **2,95 : 1**. Tres textos que un visitante puede
  no leer, medidos contra su fondo efectivo.
- **Y una cosa ROTA que no es de diseño**: en `/contacto` la tarjeta «Ubicación» **tapa al 100 %**
  el botón «Cargar el mapa» y su enlace de política. El mapa no se puede cargar en escritorio.
- **Seis puertas de Hallmark fallan A PROPÓSITO** (§6): son decisiones del owner con su porqué
  medido, y el informe las separa de los hallazgos para no re-litigarlas.

## 1. Contexto y por qué otra vez

El 2026-09-01 se auditó la web con la misma skill y salió el informe «Un solo idioma» (3 críticos ·
8 mayores · 6 menores · temas T1–T7 · tandas A–F). Se ejecutaron la A (`#321`, un solo botón) y la
B (`#323`, la tarjeta pegatina). El 02 el owner pidió continuar y **el informe no estaba**: vivía
en una URL que no aterrizó en su cuenta. Se decidió re-auditar (`[DECIDIDO owner, 2026-09-02]`:
barrido completo, en solitario, sin workflow) por tres razones, en orden de peso:

1. **Sus dos hallazgos mayores están arreglados** — un informe cuyos dos primeros temas ya no
   existen describe una web que no está.
2. **El sujeto se movió**: desde el 01 aterrizaron el carril entero de Google auth (tocó
   `site.css` y el cajón), `#322` y `#324`.
3. Trabajar del **resumen del resumen** es el modo de fallo que este repo lleva pagando.

**Lo que NO se audita, y por qué**: el **cajón SPA** (`[DECIDIDO owner, 2026-09-01]`: «el SPA lo
dejamos por ahora»; su CSS se cita solo cuando una regla suya cae en la landing), el **panel**
(otro idioma, herramienta de operador) y los **correos** (⚠️ visto de paso: la plantilla
`resources/views/vendor/mail/html/themes/brand.css` lleva `#FF5B22` —el naranja del PRIMER
cliente— quemado **cinco veces**; es fuga white-label fuera del alcance de esta auditoría, ficha
para `DEUDA.md`).

## 2. Lo que se sostiene (verificado hoy, no heredado de la doc)

| qué | medida | veredicto |
|---|---|---|
| Desbordamiento horizontal | **0 px en 96 mediciones**; `html`/`body` con `overflow-x: clip` (el remedio que no rompe `sticky`, `#226`) | ✓ G34 |
| Foco por teclado | 40 tabulaciones por vista a 1280: **0 activos sin anillo en 10 de 12 vistas**; el menú cerrado deja **0 de 13** enlaces alcanzables (el defecto §1.7 de `armazon-y-menu.md`, cerrado) | ✓ salvo formularios (M7) |
| Relleno de acción | ≤ **4,01 %** del primer viewport en las 12 (máximo `/precios` a 390) | ✓ G23 — el hallazgo nº 1 del auditor del cliente («el naranja ha dejado de mandar») está cerrado y **`#321` se sostiene** |
| Familias tipográficas | 3 + el rotulador **una vez** (Permanent Marker ×1, solo en portada) | ✓ es el contrato del propio cliente («máx. 2 + mono · rotulador una vez») |
| Copy renderizado | 0 comillas rectas · 0 «...» · 0 «--» en las 12 | ✓ |
| Estructura | hero-tarjeta con imán → tarifas → banda de color → carrusel → visita → normas → FAQ → cierre con juego → tira; nav = racimos flotantes + menú a pantalla completa; pie = tira de cinco colores + fila + colofón | ✓ G8/G42/G43 — **ninguna huella de plantilla** |
| Accesibilidad del titular | el «ON» del interruptor va `aria-hidden` y el nombre accesible es «DIVERSIÓN ON» (no «ON ON») | ✓ |

**Cruce con la auditoría del propio cliente** (`Auditoría Landing PJP`, 27-08, sus tres
veredictos): (1) «el naranja ha dejado de mandar» → **cerrado** (`#321`, medido arriba); (2)
«cuatro secciones papel seguidas» → **sustituido por su propio `S-00`** (papel continuo y el
contraste lo dan las tarjetas; hoy además hay la banda magenta y el cierre en tinta); (3) «nadie
puede navegar con teclado… los campos llevan `outline:none` sin sustituto» → **a medias**: los
enlaces y botones ya llevan anillo, **los campos de formulario siguen igual** (M7).

## 3. Hallazgos — CRÍTICOS (3)

Formato de Hallmark: **tell** (el anti-patrón con nombre) · **dónde** (fichero:línea) · **por qué**
· **fix**. Todo lo que hay aquí se midió; las cifras llevan su sonda.

### C1 · `/servicios` sirve un 503 en las 8 × 8 mediciones y está en el sitemap
- **Dónde**: `routes/web.php` (`Route::get('/servicios', ServicesController::class)`); la página
  que sale es la de mantenimiento (captura `servicios@1440.png`).
- **Por qué**: una URL pública que devuelve «Esta sección está en mantenimiento» es el peor
  desenlace posible para el visitante, y el sitemap se la ofrece a Google. La ficha ya existe en
  `DEUDA.md` («`/servicios` da 503 estando en el sitemap»); la auditoría añade la medida: **las
  64 peticiones**, no una racha.
- **Fix**: fuera del sitemap y del menú hasta que la página exista, o publicar la página. Es
  producto, no CSS.

### C2 · El cian de ZONA pinta la INTERACCIÓN, y donde más se ve es ilegible (T3)
- **Dónde**: `--zone-*` se usa **157 veces** (99 en `site.css`, 58 en `landing.css`); **27 dentro
  de selectores interactivos** (`:hover` · `:focus-visible` · `.open` · `.is-active`). En la
  landing: `landing.css:2093-2101` (FAQ: hover, abierta, y el icono relleno), `:1284`
  (`.zone-tab.active`), `:767-788` (menú móvil, hover ×3), `:861`. Y `site.css:5141`:
  `--offw-accent: var(--zone-1, #FF5B22)` — el widget de ofertas lee el acento de zona **con el
  naranja del primer cliente de respaldo**.
- **Medido**: `.faq__q` abierta/hover = `rgb(26,169,222)` (Cian PJP) sobre papel `#F4F4F1` →
  **2,45 : 1** a 18 px (AA exige 4,5). Sale en `/` y `/entradas` a los ocho anchos.
- **Por qué es el hallazgo y no «un cian que sobra»**: `--zone-1` **no es un color, es la
  identidad de la zona que estés mirando** — lo repinta en línea el pack/zona seleccionado
  (`applyZoneAccent()`, `#302`). Un token cuyo valor depende del contexto está pintando el hover
  de una FAQ, el anillo de foco de una ficha y el enlace del banner de cookies: sitios donde **no
  hay zona**. Es un token de marca haciendo de color de interacción, y por eso su contraste no lo
  garantiza nadie: el cian pasa sobre tinta y falla sobre papel.
- **Fix**: un token con ROL (`--interactive`/`--link`), declarado en las dos superficies con el
  contraste medido —como se hizo con el foco (`#209`, `client.css:53-72`)—, y **que `--zone-*`
  se quede para lo que identifica una zona** (pestañas, chips de zona, la tira). **El color lo
  decides tú** (§7 · D1): tinta, el color de acción, o un color del paquete.
- ▶ **Su propio sistema lo dice** (leído el 03-09, `#431`): la tabla de contraste de `Colores de
  Marca` marca **«Cian sobre Papel 2,45 · ✕ NUNCA · solo relleno»**, y su tabla de roles pone el
  **enlace en papel en Azul Muro `#0A5C93`** y en tinta en cian (`mockup_playjumppark/design-playjump.md`
  §2.4–§2.5). D1 tiene una respuesta de partida antes de renderizar nada.
- **Guarda que falta**: ninguna. `ActionFillTest` mira rellenos de acción; nadie mira **quién
  consume `--zone-*`**.

### C3 · Cuatro textos por debajo de AA, tres de ellos en TODAS las vistas
Sonda 1, contraste contra el fondo **efectivo** (subiendo por los ancestros hasta el primer fondo
opaco), WCAG 2.1, 400 nodos de texto por vista:

| texto | dónde | ratio | mínimo | talla | causa |
|---|---|---|---|---|---|
| «O INICIA SESIÓN» (`.cta-ghost__s`, CTA del armazón) | las 12 vistas, ≥ 768 | **2,61** | 4,5 | **9 px** | `rgb(154,161,168)` = `--ink-fg-mute` (Humo Claro) **sobre blanco**. `client.css:40` lo avisa literalmente: *«El claro FALLA en papel»*. El sub-rótulo lee el gris de la OTRA superficie |
| «Incluido» (`.addons-mini__badge--included`) | `/`, `/entradas`, `/cumpleanos` | **2,95** | 4,5 | **9,5 px** | `site.css:4412` `color: #fff` quemado sobre `--ok` (`#5FA82E`). Igual en `:2221` y `:1284` (`--err`). No existe `--on-ok`/`--on-err` |
| `.faq__q` abierta | `/`, `/entradas` | **2,45** | 4,5 | 18 px | C2 |
| «¡Felicidades!» (`.bd-pol__sticker`) | `/`, `/entradas`, `/cumpleanos` | 4,11 | 4,5 | 11 px | magenta `rgb(230,0,126)` sobre tinta: roza |

- **Fix**: `--on-ok`/`--on-err`/`--on-warn` derivados por luminancia como ya se hace con
  `--on-brand`; `.cta-ghost__s` lee `--fg-mute` de SU superficie; y **nada por debajo de 10 px**
  (el propio sistema declara `--fs-9`: revisar si ese escalón debe existir).
- ▶ **Su propio sistema lo marca**: **«Blanco sobre Verde 2,95 · ✕ NUNCA · usa Verde oscuro»** (la
  tabla de contraste de `Colores de Marca`, `#431`) — es «Incluido» al dígito.
- **Guarda que falta**: una sonda de contraste sobre el HTML renderizado. `ThemeColorTest` vigila
  tokens, no pares texto/fondo.

## 4. Hallazgos — MAYORES (10)

### M1 · Hovers que SALTAN después de que el sistema decidiera que no salta
`#217` §9.4 (el CTA del armazón no salta), `#321` (el `.btn` no salta), `#323` (ninguna pegatina
levita). Sobreviven fuera de `.btn`, que es lo único que vigila `SingleButtonFamilyTest`:

| selector | dónde | qué hace |
|---|---|---|
| `.bd-pack__cta:hover` | `landing.css:2647` | `translateY(-3px) scale(1.01)` **+ cambio de fondo + sombra**: tres efectos a la vez (G13) en el CTA de la banda más cargada de la web |
| `.reg-cta:hover` | `site.css:973` | `translateY(-2px)` (el CTA de registro de normas) |
| `.nav__burger:hover` | `landing.css:657` | `translateY(-2px)` — la hamburguesa salta y el par de CTA de al lado no |
| `.bd-swatch:hover` · `.bd-proc__arrow:hover` | `landing.css:2696` · `:2826` | `translateY(-2px)` |
| `.offw-launch:hover` | `site.css:5151` | `translateY(-3px) scale(1.05)` |

- **Fix**: fuera el `transform` en hover (queda la respuesta de color y la pisada `:active`, como
  `.btn`), y **ampliar la guarda**: ningún `:hover` con `translate`/`scale` fuera de una lista
  enumerada. ⚠️ `.nav__brand:hover .nav__brand-logo` (`site.css:379`, `translateY(-2px)
  rotate(-1.5deg)`) **no se cuenta**: puede ser el «flota» de `#217` §10 — se pregunta (D5).

### M2 · La escala tipográfica y de espacio, ESQUIVADA — y el mando por instalación solo mueve un tercio
Los tokens `--fs-*` (12 escalones) y `--sp-*` (17) existen para que `--fs-unit`/`--sp-unit` muevan
la web entera desde el paquete (`landing.css:303-368`). Medido sin comentarios:

| | literales px | de ellos, **con token disponible y sin usarlo** | usos del token | lo que el mando mueve |
|---|---|---|---|---|
| `font-size` | 379 (149 + 230) | **245** | 110 | **31 %** |
| `padding`/`gap`/`margin` | 879 (279 + 600) | **597** | 246 | **29 %** |

- ⚠️ **Está diferido a sabiendas**, no olvidado: `site.css:25-27` lo dice («el resto sigue con
  literales a propósito — tokenizarlo entero exigiría re-verificar la landing completa»). La
  auditoría pone el número al aplazamiento.
- **Fix**: sustitución mecánica por tabla (literal → token del mismo píxel: **cero reflujo**), con
  guarda de no-regresión al estilo de `RawColourIsNotATokenTest` (un `font-size` en px que tenga
  token pone la suite en rojo). Es la tanda F: trabajo, no gusto.

### M3 · Tres de las doce URL son un TROZO de la portada con título (T4)
Medido por los componentes que incluye cada vista:

| vista | incluye | qué añade sobre la portada |
|---|---|---|
| `/entradas` | `HomeController` (la misma vista) | el cajón abierto |
| `/precios` | `nav` + `ticket-prices` + `footer` | **nada**: la portada ya pinta `ticket-prices` entero |
| `/cumpleanos` | `nav` + `events-section` + `footer` | **nada**: la portada pinta la banda, el paso a paso Y el editor de invitación (captura `home@1440.png`) |

- **Por qué**: el menú ofrece destinos que aterrizan en lo que el visitante acaba de pasar de
  largo, y Google ve tres páginas con el mismo contenido.
- **Fix**: **decisión de estructura (D2)**: (a) la interior es la versión LARGA y la portada el
  resumen (en tarifas: todas las tarifas, temporadas y complementos; en cumpleaños: el proceso y la
  invitación solo ahí, la portada solo la banda), o (b) retirar las interiores y anclar
  (`/#pricing`). ⚠️ La opción sobre `/entradas` es distinta: es la portada **con el cajón**, y eso
  puede ser deliberado (una URL para «comprar»).

### M4 · `/contacto`: la tarjeta «Ubicación» TAPA el botón de cargar el mapa
- **Medido** (sonda 2, 1440×900): `.map-card--aside` cubre **6.292 de 6.292 px²** del botón
  «Cargar el mapa» y **1.870 de 1.870** del enlace «Política de cookies»; `elementFromPoint()` en
  el centro del botón devuelve la tarjeta. Captura `contacto@1440.png`: se lee «Carg» y «Polític».
- **Dónde**: `resources/views/pages/contact.blade.php` — la `.map-card--aside` (`#216`) colocada
  sobre el `consent-frame`.
- **Por qué**: el visitante que quiere ver el mapa no puede dar el consentimiento. En escritorio
  **el mapa no se puede cargar**.
- **Fix**: la tarjeta fuera del marco (debajo, o en la columna del texto), o pintarla solo con el
  mapa ya cargado. Dónde va es tuyo (D4).

### M5 · «Parking gratis 2h» sigue en el CÓDIGO, en `/contacto`
- **Dónde**: la clave `landing.info.parking` de `lang/es/landing.php`, impresa por
  `resources/views/pages/contact.blade.php` dentro de la `.map-card--aside`.
- **Por qué**: `#297`/`#307` lo retiraron de la portada («está en el código, no en el panel») y
  `VisitSectionTest` lo vigila **solo allí**. En contacto sobrevive el mismo dato de negocio
  quemado — el principio data-driven, y el mismo tipo de defecto que la edad vieja de la norma
  «Zona Jump» (que es dato del panel y **sigue diciendo 6 años**: `normas@1440.png`).
- **Fix**: campo en el panel (los datos de la instalación ya tienen pantalla) o fuera. Tuyo (D4).

### M6 · Los cinco «!» de `/normas`: un icono repetido sin dato detrás, y una rejilla con hueco
- **Dónde**: `pages/rules.blade.php` + `.rule` (`#323` §3); captura `normas@1440.png`.
- **Por qué**: cinco tarjetas, cinco veces el mismo glifo «!» en tile — es el *icon-tile feature
  card* de Hallmark **y** rompe la regla propia de `#286`/`#302` (*«sin dato detrás, un icono
  repetido N veces es decoración en un bucle»*). La rejilla 3 + 2 deja el sexto hueco vacío, y la
  norma de una línea («Consulta las condiciones del centro») deja la tarjeta 4/5 vacía. `#323`
  §4.1 ya lo dejó como decisión abierta.
- **Fix**: sin icono (el titular manda), o un icono **por norma elegido en el panel**
  (`venue_rules.icon`, como `ticket_types.icon` en `#319`); y lista o dos columnas en vez de 3 + 2.
  Tuyo (D3).

### M7 · El foco en los CAMPOS de formulario es un borde de 1 px que cambia de color
- **Medido**: tabulando a 1280, `/contacto` **4 campos y el textarea** y `/cumpleanos` **2
  campos** llegan al foco con `outline: 3px none` — el anillo está anulado y lo que cambia es
  `border-color` (1 px). WCAG 2.4.11 pide un perímetro ≥ 2 px o equivalente.
- **Dónde**: `site.css:838` (`.form__field textarea:focus { outline: none; border-color }`),
  `:1293`, `:2096`, `:2197`, `:3146`; el reset `*:focus { outline: none }` de `landing.css:2283`
  solo devuelve el anillo a `a`, `button`, casillas, radios y `.ride-card`.
- **Por qué**: es la mitad que quedó del hallazgo nº 3 del auditor del cliente.
- **Fix**: `input:focus-visible, textarea:focus-visible, select:focus-visible { outline:
  var(--focus-outline) }` — el token ya existe.

### M8 · El acordeón de la FAQ anima `max-height: 240px`: anima layout y es un TOPE de contenido
- **Dónde**: `landing.css:2104-2107`.
- **Medido**: hoy las seis respuestas miden 24–48 px y **ninguna se corta**. Pero el tope es fijo y
  las respuestas las escribe el panel: la primera respuesta larga se cortará **sin fallar**.
- **Fix**: `grid-template-rows: 0fr → 1fr` (la receta de `motion.md`), que anima sin tope.

### M9 · Imágenes sin dimensiones (25 de 25 en portada) y la foto de `/cumpleanos` en `lazy` dentro del primer viewport
- **Medido**: portada 25 `<img>`, **0 con `width`/`height`, 0 con `aspect-ratio`** (CLS; la
  ficha ya está en `DEUDA` desde `#252`). `/cumpleanos`: **1 imagen `loading="lazy"` en el primer
  viewport** (el `<img>` de `$cumpleImage` en `resources/views/components/site/events-section.blade.php`)
  — el mismo componente sirve a la portada, donde está bajo el pliegue y el `lazy` es correcto.
- **Fix**: `width`/`height` desde el manifiesto o `aspect-ratio` por clase; y el `loading` como
  prop del componente (`eager` + `fetchpriority="high"` cuando abre la página).

### M10 · El presupuesto de movimiento de `#279` parece SUPERADO, y no lo vigila nadie
- **Medido** con el criterio de `#279` (`getAnimations()` con `iterations === Infinity`, a 1280):
  `/` **13** (2 del latido del CTA doble · **8 de `s1-bob`/`s1-grip`** · 3 del spinner);
  `/cumpleanos` **17** (2 · **11 banderitas `bdFlagSway`** · 1 `bdStarFloat` · 3 spinner);
  `/normas`, `/contacto`, `/aviso-legal` **5** (2 + 3 spinner). `#279` dio la portada por cumplida
  en **2** y `/cumpleanos` en 1.
- **Por qué**: o `s1-*` (el icono de calcetines, que `#279` contaba solo en `/precios`) volvió a la
  portada después, o el criterio de entonces excluía piezas. Y **el spinner gira oculto en las 12
  vistas** (3 bucles en la página legal más quieta): trabajo del compositor para nadie.
- **Fix**: re-medir con la tabla de `#279`, `animation-play-state: paused` en el spinner mientras
  no se ve, y decidir las banderitas (D6). **Guarda**: el presupuesto por vista, ejecutable.

## 5. Hallazgos — MENORES (6)

- **m1 · `transition: all`** en `.zone-tab` (`landing.css:1281`): el único `all` en 622 KB, y
  `MotionScaleTest` no lo ve (mira duraciones y curvas, no la propiedad).
- **m2 · Se animan propiedades de LAYOUT**: `.mob-menu__primary a` mueve `padding-left` en hover
  (`landing.css:765-767`); `.menu__item a:focus-visible { padding-left: 18px }` (`site.css:5422`)
  — **el foco desplaza el elemento**; `.slider-progress__bar` anima `left`/`width`
  (`landing.css:1468`). Fix: `transform`.
- **m3 · CSS muerta**: `.eyebrow` (`landing.css:470`, `:2668`, `:2808`; `site.css:542`) no tiene
  consumidor en las 12 vistas desde que `#303` retiró las etiquetas (solo error/auth usan
  `class="eyebrow"`). `.jj-block` (`landing.css:1078`, el «foam» del primer cliente) solo lo emite
  `BookingProgress.vue` — el cajón, fuera de alcance, pero anotado.
- **m4 · Color crudo fuera de tokens en superficie pública** (37 en total, depurado; 6 son el botón
  de Google, `#345`, y 4 son máscaras): el widget de ofertas `.offw-*` (`site.css:5184-5292`:
  `#F0B33F`, `#FFF7EC`, `rgba(255,255,255,.2)`, `#fff` ×2 — la isla heredada del informe
  anterior), `.addons-mini__badge { background: rgba(34,197,94,.15) }` (`:4407`, un verde que
  **no es `--ok`**), `.qr-tile { background: #fdfbf4 }` (`:937`), y cuatro sombras `rgba(0,0,0,…)`
  (`:2600`, `:4907`, `:4947`; `landing.css:595`). `RawColourIsNotATokenTest` no los ve.
- **m5 · `/precios` no tiene eje**: el titular a la izquierda, el conmutador y las tarjetas
  centrados, la fila de CTA final a la izquierda — tres alineaciones en una pantalla
  (`precios@1440.png`). Hallmark: *coherencia de alineación*, no centrar ni no centrar.
- **m6 · Pulsables a dos líneas, medidos con `Range`** (no con alto/line-height, que confundía el
  área táctil de 44 px con una segunda línea): `.bd-tab` («PACK CUMPLEAÑOS JUMP») parte **incluso
  a 1440** (`cumpleanos@1440.png`); `.zone-tab` («ENTRADAS JUMP») a ≤ 375; `.bd-pack__cta`
  («Reservar cumpleaños») y «Descargar tarjeta» a 320. ⚠️ Los rótulos los escribe el panel: el fix
  es anchura/tracking (0,14 em en mayúsculas es lo que los alarga), no acortar el texto.

## 6. Excepciones DECLARADAS — puertas de Hallmark que fallan por decisión del owner

Se listan para que nadie las «arregle». Cada una tiene su porqué medido en `DECISIONES`.

| puerta | lo que hay | decisión |
|---|---|---|
| G12 curvas con sobreimpulso | `--ease-entra`/`--ease-cae` (1.56) — «lo que entra rebota» | `#222`, `#265` (la gravedad del salto es de los fotogramas) |
| sombras difusas / hover que levanta | sombra **dura** `5px 5px 0` en la pegatina, hover que **responde**, no levita | `#303`, `#323`: es el sistema del cliente |
| G6/G44 hero de pantalla completa | `.hero--full` mide `100svh + runway`, pegado y con imán; el CTA **sí** está sobre el pliegue a 1280×800 (medido) | `#216`, `#252`, `#254` |
| bucles ambientales | interruptor 3 ciclos y descansa ON; latido del CTA doble; cinta | `#280`, `#205`, `#279` (**pero ver M10**) |
| display Bungee, 4 familias | Bungee + Hanken + JetBrains + Permanent Marker ×1 | el paquete del cliente (`client.css:104-114`) |
| género `playful` pide croma bajo | cian, naranja, lima, amarillo y rojo saturados | la marca es la marca: Hallmark «sigue la identidad que le dan» (`contract.md`) |

## 7. Lo que decides tú

- **D1 · El color de la INTERACCIÓN** (C2): ¿tinta (`--fg`, como el foco) · el color de acción ·
  un color del paquete (Azul Muro `#0A5C93`, que ya es el foco sobre papel y da 6,43)? Se te
  enseñan las tres **sobre la FAQ y las pestañas reales**, a 1440 y 390. → `[DECIDIDO owner,
  2026-09-03]` viendo las tres renderizadas: **el par del cliente** (Azul Muro en papel, cian en
  tinta) — §13.
- **D2 · Las interiores** (M3): versión larga vs. anclar. Y qué es `/entradas`.
- **D3 · Normas** (M6): sin icono · icono por norma desde el panel · lista.
- **D4 · Contacto** (M4, M5): dónde va la tarjeta, y «Parking» al panel o fuera. →
  `[DECIDIDO owner, 2026-09-03]`: **debajo del mapa, como pegatina; «Parking» FUERA** (§11).
- **D5 · Los hovers** (M1): retirar los cinco; y **confirmar** si el del logotipo es el «flota»
  de `#217`. → `[DECIDIDO owner, 2026-09-03]`: **fuera los cinco; el logotipo conserva su gesto**.
- **D6 · Movimiento** (M10): las 11 banderitas de la invitación y el spinner oculto. →
  `[DECIDIDO owner, 2026-09-03]`: **banderitas QUIETAS (fuera el bucle)**; el spinner oculto y el
  icono de calcetines se pausan mientras no se ven (no necesitaba decisión).

## 8. Plan propuesto (el orden es una recomendación; las letras siguen a la A y la B ejecutadas)

| tanda | qué | gusto | tamaño |
|---|---|---|---|
| **C** | **Lo roto**: M4 (solape del mapa) · M7 (foco de campos) · M8 (acordeón) · C3 (`--on-ok`, el sub-rótulo, ≥ 10 px) — ✅ **HECHA, `#434` (§11)** | solo D4 | pequeña |
| **D** | **El color que responde**: C2 con D1, opciones renderizadas; retira `--zone-*` de lo que no es zona — ✅ **HECHA, `#436` (§13)**: D1 = el par del cliente | D1 | media |
| **E** | **La física, terminada**: M1 · m1 · m2 · m3 · m4, con la guarda ampliada — ✅ **HECHA, `#435` (§12)**; m3 no procede (`.eyebrow` tiene consumidores fuera de las doce) | D5 | pequeña |
| **F** | **La escala**: M2, sustitución mecánica + guarda — ✅ **HECHA, `#437` (§14)**: 662 literales a token, huella idéntica | ninguno | media, cero reflujo |
| **G** | **Las páginas interiores**: M3 · M6 · m5 · m6 | D2 · D3 | grande |
| **H** | **Rendimiento**: M9 · M10 — ✅ **HECHA, `#435` (§12)** | D6 | pequeña |
| — | **C1** `/servicios` | producto | fuera de este carril |

## 9. Método, instrumento y trampas pagadas

- **Puertas medibles por texto** → barrido con `grep`/Python sobre las dos hojas **sin comentarios
  y sin `url(data:…)`**: el primer conteo de «hex fuera de tokens» dijo **323 + 112** y el real es
  **36 + 1** — *un CSS con la documentación dentro cuenta la documentación como defecto*.
- **Puertas que solo se ven renderizadas** → `audit-hallmark.mjs`: desbordamiento, dos líneas,
  contraste efectivo, foco tabulando de verdad (`keyboard.press('Tab')`, no `el.focus()`), hero,
  área de acción, ritmo, copy, imágenes, menú cerrado, animaciones, capturas. **Con control**: un
  caso sintético malo inyectado en la primera vista que la sonda tiene que cazar o aborta.
- **Cuatro trampas de instrumento**, todas con cifras creíbles:
  1. **«Dos líneas» por alto/line-height contaba el área táctil de 44 px** (`#264`) como segunda
     línea: 13 falsos positivos en el pie a 1920. Se re-midió con `Range.getClientRects()` sobre
     el texto (sonda 2), con control.
  2. **Las capturas de 1280 no son el pliegue**: se tomaron DESPUÉS de tabular 40 veces, y el foco
     desplazó la página. El dato «CTA sobre el pliegue» se midió antes de tabular y es válido; las
     capturas de 1280 no se usaron para juzgar el hero.
  3. **`getAnimations().length` no es el presupuesto de `#279`**: cuenta transiciones y
     animaciones finitas. Se filtró por `iterations === Infinity` (sonda 2).
  4. **Sin fuentes cargadas se mide otra web** (`#335`): las dos sondas esperan
     `document.fonts.ready` **y** que haya familias cargadas.
- **Lo que la skill mide que no es de este proyecto**: sus puertas de género (`playful`) sobre el
  croma no aplican a una marca dada; su `2+1` de familias cede ante el contrato del cliente. Se dice
  en §6 en vez de contarlo como hallazgo.

## 10. Verificación de este informe

- 96 mediciones (12 × 8) + 24 dirigidas (6 × 4) + 4 comprobaciones puntuales; 36 capturas.
- Controles: sonda 1 `caughtTwoLine: true, caughtContrast: true`; sonda 2 `control (a) ✓`.
- Cada cifra de §3–§5 tiene fichero:línea o sonda; ninguna sale de la doc anterior.
- **No se tocó ni una línea de producto.**

## 11. Ejecución — tanda C, «lo roto» (2026-09-03, `#434`)

`[DECIDIDO owner, 2026-09-03]` **D4** (la tarjeta de contacto DEBAJO del mapa, como pegatina · «Parking»
FUERA), **D5** (fuera los cinco hovers que saltan; el logotipo conserva su «flota») y **D6** (banderitas
quietas). D5 y D6 se ejecutan en las tandas E y H; aquí, la C.

**Medido ANTES y DESPUÉS con la misma sonda** (`storage/app/audit-tanda-c.mjs`, Chromium, 1280 y 390,
fuentes cargadas; tabulando de verdad para el foco y `elementFromPoint()` para el solape):

| hallazgo | antes | después |
|---|---|---|
| C3 «O inicia sesión» (`.cta-ghost__s`) | Humo `#626A72` al **62 %** sobre blanco · 9 px → **2,61** | opacidad 1 · `--paper-fg-mute` · 10 px → **5,49** |
| C3 «Incluido» (`.addons(-mini)__badge--included`) | `#fff` sobre `--ok` → **2,95** · 9,5 px | `--on-ok` (tinta, del paquete) → **6,28** · 10 px |
| C3 «¡Felicidades!» (`.bd-pol__sticker`) | magenta puro sobre tinta → **4,11** | magenta aclarado un 28 % hacia papel → **5,13** |
| M8 acordeón (`.faq__a`) | `transition: max-height, margin-top` · abierto `max-height: 240px` | `transition: grid-template-rows` · abierto = su contenido (62 px a 1280, 86 a 390), **sin tope** |
| M4 `/contacto` | `elementFromPoint()` sobre «Cargar el mapa» → la tarjeta (1280) / su párrafo (390) | → **el botón** |
| M5 «Parking gratis 2h» | en la página | fuera, y la clave fuera de los tres idiomas |
| M7 foco del primer campo (Tab real) | `outline: none` · borde de 1 px | `outline: solid 3px` Azul Muro (`--focus-outline`) |

**Lo que cambia en el sistema**: nacen **`--on-ok` · `--on-err` · `--on-warn`** (texto sobre relleno
semántico; `design.md` §3 los tenía como *(futuro)*) — el producto declara papel sobre sus verde y rojo
oscuros y **el paquete del cliente declara los suyos** (`client.css`: tinta sobre Verde Salta, blanco
sobre Rojo Goteo, que es su tabla §2.1); el anillo del token cubre ya `input`/`textarea`/`select`;
el suelo de 10 px pasa a guarda (seis literales de 9 y 9,5 px subidos; la única excepción enumerada,
`.bk-seg__label`, es del cajón, aparcado). La tarjeta de la dirección es la pegatina de «Visítanos»
(`.visit-card`, `#307`), sin título dentro y sin `:hover`.

**Guardas nuevas — las cuatro vistas MORDER con el defecto real** (mutación en el fichero y
restauración sin git, control en verde): `FieldFocusRingTest` (un `outline: none` en un `:focus` de
campo · falta la regla `:focus-visible`) · `FaqAccordionTest` (vuelve `max-height` · falta el envoltorio
que recorta) · `SemanticFillTextTest` (blanco quemado sobre `--ok/--err/--warn` · tokens ausentes ·
`.cta-ghost__s` con opacidad · `font-size` < 10 px fuera de la lista del cajón) · `ContactPageTest` (la
tarjeta dentro de `.map-card` · algo posado con `z-index` sobre el mapa · «Parking» en página o en `lang/`).

**Tres correcciones a este informe, medidas**:
1. **La causa de «O INICIA SESIÓN» no era el gris de la OTRA superficie** (§3·C3 decía «lee
   `--ink-fg-mute`»): la sonda dio `rgb(98,106,114)` = Humo de PAPEL, y lo que lo hundía era
   `opacity: .62` de la regla compartida con el relleno de tinta (`.cta-med__s, .cta-ghost__s`), pensada
   para papel sobre tinta (donde da 6,9). *Un color medido en pantalla puede ser el correcto atenuado:
   la sonda tiene que multiplicar la opacidad de los ancestros, y el informe no lo hizo.*
2. **`--on-ok` NO se puede derivar por luminancia como `--on-brand`**: aquél lo calcula el servidor desde
   el panel; `--ok` lo declara el CSS del paquete y el servidor no lo ve. El par lo declara quien declara
   el color, y eso deja **un paso de despliegue**: dos líneas en el `client.css` de producción
   (`--on-ok: #101418; --on-err: #FFFFFF;`), que no viajan por rsync.
3. **Un `color-mix()` computa como `color(srgb …)`**: una sonda que lee `rgb()` se cae con `null.a` en
   cuanto un texto usa la mezcla. Se normaliza pintando el color en un canvas de 1×1.

**Lo que no cambia, a propósito**: el borde de 1 px que cambia de color al foco sigue (es la respuesta
de ratón, y `:focus-visible` la complementa); el reset `*:focus { outline: none }` sigue; el `:focus`
del calendario y del cajón heredan el mismo anillo sin tocar sus reglas.

## 12. Ejecución — tandas E («la física») y H («rendimiento») (2026-09-03, `#435`)

**E · M1, m1, m2, m3, m4 — `[DECIDIDO owner]` D5: fuera los que saltan, el logotipo se queda.**
- **M1**: el barrido por selector encontró **19** reglas cuyo PROPIO elemento se mueve o crece al pasar el
  ratón, no 5: a las cinco del informe se suman la polaroid de cumpleaños y la foto de eventos (subían 8 px
  con sombra difusa), el cierre del cajón viejo, los tres CTA de `/servicios`, el CTA del cierre y el del
  hero viejo, y **cinco del cajón SPA** (`.bk-cta` · `.cartbar` · `.acct__btn--*` · `.acc-tile`). Los
  catorce públicos pasan a responder con color, borde o sombra; **las dos polaroids conservan el gesto de
  ENDEREZARSE** (`rotate(-2.5deg) → 0`), que no es un salto; los cinco del cajón quedan enumerados como
  excepción que solo encoge (el cajón está aparcado, `[DECIDIDO owner, 2026-09-01]`); el «flota» del
  logotipo es un descendiente del hover y la guarda no lo alcanza.
- **m1**: el único `transition: all` (`.zone-tab`) pasa a propiedades nombradas.
- **m2**: el enlace del menú móvil viejo ya no desplaza su relleno al pasar; el foco del menú a pantalla
  completa ya no lo desplaza 18 px (ni 2 en móvil); **la barra de progreso del carril anima
  `transform`** —`translateX(L %) scaleX(W)` con el origen a la izquierda— en vez de `left`/`width`
  (medido: `transition-property: transform`, `matrix(0.159, …)`).
- **m3 · NO se ejecuta, y el informe estaba incompleto**: `.eyebrow` no tiene consumidor en las doce vistas
  públicas pero **sí en cinco fuera de ellas** (`errors/404`, `errors/maintenance`,
  `errors/page-maintenance`, `auth/reset-password`, `auth/verify-email`, `payments/retry-redirect`), y
  `.jj-block` lo emite `BookingProgress.vue`. *«Sin consumidor en las vistas auditadas» no es «muerto».*
- **m4**: once literales pasan a rol — `#fdfbf4` → `--sheet`; el verde que no era `--ok` → `color-mix`
  sobre `--ok`; cuatro sombras `rgba(0,0,0,…)` → `--paper-fg` mezclado; en el widget de ofertas `#F0B33F`
  → `--attn`, `#FFF7EC` → `--sheet`, el blanco al 20 % → `--paper-bg` mezclado y el `#fff` sobre el
  acento → `--on-brand`; y **`--offw-accent` pierde el naranja del PRIMER cliente de respaldo**
  (`var(--zone-1, #FF5B22)` → `var(--zone-1)`). Qué rol le toca al widget es de la tanda D.

**H · M9, M10 — `[DECIDIDO owner]` D6: banderitas quietas.**
- **M9**: la foto de `/cumpleanos` que abre la página va `eager` + `fetchpriority="high"` **solo cuando el
  componente es la página** (`level=1`); en la portada sigue `lazy`. **Corrección al informe**: «25 `<img>`,
  0 con `width`/`height`/`aspect-ratio`» se midió sobre el `<img>`, y la reserva vive en el ENVOLTORIO —
  `.ride-card__viz` y `.bd-pol__frame` declaran `aspect-ratio: 4 / 5` y la foto va absoluta dentro—, así
  que esas 24 no mueven el layout al cargar. Quedan sin proporción propia la del widget (`.offw-img`,
  `height: auto`) y la previsualización del menú (absoluta en una caja con `min-height`).
- **M10**: las once banderitas y la estrella de la invitación, **quietas** (fuera `bdFlagSway` y
  `bdStarFloat`); **el spinner se PAUSA con el cajón cerrado** (`visibility: hidden` no detiene
  animaciones: `animation-play-state: paused` bajo `.sidecart:not(.is-open)`); **los ocho bucles del icono
  de calcetines solo corren mientras se ve** (`IntersectionObserver` en `app.js` pone `is-onscreen`; sin
  JS, reposo). Medido con `getAnimations()` (`iterations === Infinity`) al cargar a 1280, cajón cerrado:

| vista | informe (§4·M10) | ahora, corriendo | de ellos, aceptados por `#279` |
|---|---|---|---|
| `/` | 13 | **5** (latido y aro del CTA doble ×2 · destello de la atracción destacada ×1) | los 5 |
| `/cumpleanos` | 17 | **2** (latido y aro) | los 2 |
| `/precios` | 9 (`#279`) | **2** al cargar; los 8 del icono se encienden al verlo | — |
| `/normas` | 5 | **2** | los 2 |

- **Guardas nuevas, las cuatro mutaciones vistas MORDER** (restauración sin git, control en verde):
  `HoverDoesNotJumpTest` (transform en el propio `:hover` fuera de la lista del cajón · `transition: all`
  · `padding`/`margin` en una interacción) y `MotionBudgetTest` (**cada `infinite` de las tres hojas
  enumerado con su motivo** —añadir uno es decidirlo—, banderitas y estrella sin bucle, el spinner pausado
  con el cajón cerrado, el icono pausado fuera de pantalla y el JS que lo enciende).
- **El trinquete del otro extremo**: `SidebarTokenBudgetTest::MAX_RAW_COLOURS` baja de **3 a 0** —tres de
  los literales de C y E (`.account__delete-btn`, `.qr-tile`, el verde del badge de complementos) eran del
  cajón— y la guarda lo exige «en el mismo commit»; **el hook rechazó el push de la tanda C por esto**, con
  razón, y **un segundo push por poner un 1 sin leer la cifra** (y por una puerta de `grep -q passed` que
  casó con «15 passed»): *el número lo dice el test y la puerta es el código de salida, no un texto.*
- **Trampas pagadas**: (1) el barrido en Python **blanqueaba los comentarios comiéndose sus saltos de
  línea**, y los números de línea salían desplazados — las reglas se localizaron por SELECTOR;
  (2) la sonda de hover marca la polaroid como «SE MUEVE» porque la matriz cambia: es la rotación
  deliberada, no un salto — *un instrumento que compara matrices no distingue enderezarse de saltar*.

## 13. Ejecución — tanda D, «el color que responde» (2026-09-03, `#436`)

**D1, decidida viendo** (`[DECIDIDO owner, 2026-09-03]`): las tres opciones se montaron sobre la web real
con las mismas reglas que hoy leen el cian de zona fuera de una zona —la FAQ abierta y con el ratón encima
(papel), el destino enfocado del menú a pantalla completa (tinta), el enlace «Configurar» del banner de
cookies— a 1440 y 390, en una hoja de decisión con su contraste medido como texto
(https://claude.ai/code/artifact/1dac07ee-d17a-4484-9ea6-f651d834cab1; el registro es este fichero):

| opción | en papel | en tinta | sobre papel | sobre tinta |
|---|---|---|---|---|
| A · tinta (el defecto del producto) | `#101418` | `#F4F4F1` | 16,79 | 16,79 |
| B · el color de acción | `#F2711C` | `#F2711C` | **2,66 ✕** | 6,30 |
| **C · el par del cliente** ✓ | `#0A5C93` Azul Muro | `#1AA9DE` cian | 6,43 | 6,85 |
| hoy · el cian de zona | `#1AA9DE` | `#1AA9DE` | **2,45 ✕** | 6,85 |

El owner eligió **C**: lo que su propio sistema escribe para el enlace (su tabla de roles, §2.4 del
perfil), con una pista de color para «esto responde» que la tinta no da —en A la pregunta abierta de la
FAQ queda igual que las cerradas salvo por el icono— y sin gastar el naranja, que como texto sobre papel
no llega a AA.

**Lo hecho**:
- Nace **`--interactive`** en `landing.css`: tinta por defecto, **re-declarado en `[data-surface="ink"]`
  y `[data-surface="paper"]`** para que `var(--fg)` se evalúe con el `--fg` de cada superficie (el
  mecanismo de `--action`, `#209`). El paquete del cliente (`client.css`) lo fija por superficie: Azul
  Muro en papel, cian en tinta — igual que el foco. ⚠️ **Paso de despliegue**: tres líneas en el
  `client.css` de producción.
- **Doce reglas públicas** pasan de `--zone-1` a `--interactive`: la FAQ (hover, abierta y su icono, que
  ahora pinta `--bg` encima, papel sobre Azul Muro y tinta sobre cian), el menú a pantalla completa
  (hover y foco), el menú móvil viejo (tres), el idioma activo, los dos enlaces del banner de cookies,
  los enlaces de la 404, y los dos fantasma del cierre (`.reserve__act--alt`, `.salta__btn--ghost`).
- **Dos que no eran interacción**: el toggle de cookies encendido pasa a **`--ok`** (es un ESTADO, la
  regla de `#254` y del interruptor de la cuenta) y el foco de la ficha del formulario de invitados
  pasa al **anillo del token** (`--focus-outline`), que es lo que ya usa todo lo demás.
- **Lo que se queda con `--zone-*`, enumerado con su porqué** en la guarda: lo que IDENTIFICA una zona
  (`.zone-pick__tab.active` · `.zone-tab.active` · el chip y el botón de zona del cajón), los RELLENOS
  de marca —`.cta-med:hover` pasa a marca como el mockup (`#217`) y `.salta__btn:hover` oscurece su
  relleno; el texto encima lo calcula `--on-brand`, no es texto de color— y ocho reglas del cajón SPA,
  aparcado. `--offw-accent` sigue en `--zone-1` porque el lanzador es un RELLENO con `--on-brand`.

**Medido después** (`storage/app/audit-tanda-d.mjs`, Chromium, 1280 y 390, color computado y fondo
efectivo): FAQ abierta **`#0A5C93` sobre `#F4F4F1` → 6,43** (era 2,45) · su icono Azul Muro con papel
encima · destino enfocado del menú **`#1AA9DE` sobre `#101418` → 6,85** · «Configurar» del banner
**7,08** · la pestaña de zona activa sigue en su lima `rgb(163,194,28)`.

**Guarda nueva, vista morder dos veces**: `InteractionColourIsNotAZoneTest` — el token en `:root` y en las
DOS superficies (sin la de tinta, el rol cae al valor de papel sin que nada falle); ningún estado de
interacción lee `--zone-*` fuera de las tres listas, que solo encogen; la FAQ, el banner y el idioma leen
`--interactive`.

**Lo que enseñó**: (1) el cian «ilegible» y el cian «legible» son EL MISMO token en dos superficies —lo
que fallaba no era el color sino que el rol no existía—; (2) `SurfaceScopeTest` exige que las dos
superficies declaren el MISMO conjunto de tokens y lo tenía en una lista cerrada: el token nuevo entra
en ella, que es exactamente para lo que la guarda existe.

## 14. Ejecución — tanda F, «la escala» (2026-09-03, `#437`)

**M2, sin decisión que tomar**: la sustitución MECÁNICA del literal por el token del MISMO píxel, con
`scripts/escala-a-tokens.py` (informe en seco y `--aplicar`), sobre las dos hojas:

| | `font-size` → `--fs-*` | espacio → `--sp-*` | espacio que se queda (algún escalón sin token) |
|---|---|---|---|
| `landing.css` | 82 | 114 | 71 |
| `site.css` | 151 | 315 | 92 |
| **total** | **233** | **429** | 163 |

Solo se toca lo que tiene token EXACTO: `13px` → `var(--fs-13)`, `12px 20px` → `var(--sp-12)
var(--sp-20)`, `0 0 16px` → `0 0 var(--sp-16)`; fuera `calc()`/`clamp()`/`var()`, negativos, decimales,
`em`/`%`, los `:root` (son la escala) y los `@keyframes`. Lo que se queda son escalones que la escala no
tiene (24, 32, 36, 40…): entrarán cuando la escala los tenga, no con un `calc()` a mano.

**«Cero reflujo» MEDIDO, no prometido**: la huella de maquetación de las doce vistas a 1280 y 390
(`storage/app/audit-tanda-f.mjs`: el rectángulo de cada elemento visible y su `font-size`, `padding`,
`margin` y `gap` computados, con animaciones y transiciones congeladas) es **idéntica en geometría y
tipografía en las 24** antes y después. Una sola cadena difiere: el `margin` computado del pie de
`/registro` a 390 («0px 16px» → «0px») con el MISMO `left` (16) y el MISMO ancho (358) — es cómo Chrome
reporta un `margin-inline: auto`, y ese mismo valor ya daba «0px» en `/precios` ANTES. *Una huella que
lee propiedades computadas mezcla layout con cómo el navegador describe el layout: la geometría es la
que manda.*

**Guarda nueva, vista morder dos veces**: `ScaleTokensAreUsedTest` — con el MISMO criterio que el guion
(y el guion y la guarda son el mismo texto en dos lenguajes): ningún `font-size` en px con escalón en
`--fs-*`, ningún `padding`/`margin`/`gap` cuyos escalones estén TODOS en `--sp-*`; y que las dos escalas
sigan declaradas enteras.

**Trampa pagada**: el guion buscaba sobre el CSS crudo y la guarda sobre el CSS con los comentarios
blanqueados; un `font-size: 13px /* … */;` llevaba el comentario dentro del valor capturado y el guion
lo saltaba en silencio mientras la guarda lo veía (`.addons-mini__name`). El guion pasa a buscar sobre
la copia blanqueada —misma longitud, mismas posiciones— y a escribir sobre el original. *Dos
instrumentos que miden lo mismo tienen que leer el mismo texto, o uno de los dos miente por omisión.*

**Lo que gana el white-label**: `--fs-unit`/`--sp-unit` movían un tercio de la web (31 % · 29 %,
auditoría M2); ahora mueven todo lo que está en la escala. `SidebarTokenBudgetTest` (el suelo de
tokenización del cajón, 72 %) sube de hecho con esto y su cifra la dice el propio test.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Cambiar el idioma visual HEREDADO · badges y etiquetas · el «foam» `.jj-block` · la marquesina · «Visítanos» / horarios y ubicación · lo que es del cliente ANTIGUO»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/auditoria-diseno.md` · `docs/specs/rediseno-desde-canvas.md` · `docs/specs/idioma-visual-heredado.md` · `docs/DECISIONES.md` · `docs/ESTADO.md` · `docs/specs/hueco-ilustracion.md` · `docs/DEUDA.md` · `docs/specs/tema-por-instalacion.md`.

- ❗❗❗ **EMPIEZA POR `docs/specs/auditoria-diseno.md` SI VAS A TOCAR DISEÑO** (`#430`, 2026-09-02): la segunda auditoría de la web pública, **EN EL REPO** — la primera («Un solo idioma») se publicó como artefacto y **se perdió con su URL**; sus tandas A (`#321`) y B (`#323`) se sostienen medidas. **3 críticos · 10 mayores · 6 menores**; **§6 las seis puertas de Hallmark que fallan A PROPÓSITO** (decisiones del owner: no las «arregles»); **§7 las seis decisiones que son suyas** (D1 el color de la INTERACCIÓN: `--zone-1` es la identidad de la zona que miras, no un color, y pinta la FAQ a **2,45 : 1**); **§8 el plan C→H** (C = lo roto: el mapa de `/contacto` tapado, el foco de los campos, el acordeón con tope, los cuatro contrastes).
- ▶ ✅ **C HECHA (`#434`, §11)**: medido antes/después, y **el informe tenía una causa mal** —el sub-rótulo del CTA no leía el gris de otra superficie, lo hundía `opacity: .62`—; nacen `--on-ok/--on-err/--on-warn` (**el par lo declara quien declara el color**: no se deriva) y el suelo de 10 px con guarda;
- ⚠️ **paso de despliegue**: dos líneas en el `client.css` de producción. D4 · D5 · D6 `[DECIDIDO owner]`.
- ▶ ✅ **E y H HECHAS (`#435`, §12)**: 19 hovers que saltaban (no 5), los 14 públicos responden y los 5 del cajón son excepción que solo encoge; `transition: all` y el layout animado fuera; **cada `infinite` de las hojas está enumerado con su motivo** (`MotionBudgetTest`: añadir un bucle es decidirlo), banderitas quietas, spinner pausado con el cajón cerrado, calcetines solo mientras se ven (`/` de 13 bucles a 5).
- ⚠️ **m3 NO procede**: `.eyebrow` tiene consumidores fuera de las doce vistas.
- ⚠️ El trinquete `SidebarTokenBudgetTest` baja a 0 por tres literales del cajón (y **el número lo dice el test**: un 1 puesto a ojo costó un segundo push rechazado).
- ▶ ✅ **D HECHA (`#436`, §13)**: D1 elegida viendo tres opciones renderizadas — **el par del cliente** (Azul Muro en papel · cian en tinta); nace **`--interactive`** por superficie (tinta por defecto), doce reglas dejan `--zone-1` y **`--zone-*` queda para lo que IDENTIFICA una zona** más los RELLENOS de marca (`.cta-med:hover`, el botón del minijuego: su texto lo calcula `--on-brand`), enumerados en `InteractionColourIsNotAZoneTest`; FAQ abierta 2,45 → 6,43.
- ⚠️ Tres líneas más en el `client.css` de producción.
- ▶ ✅ **F HECHA (`#437`, §14)**: 662 literales al token del MISMO píxel con `scripts/escala-a-tokens.py` (informe en seco y `--aplicar`), **«cero reflujo» MEDIDO** con la huella de maquetación de las doce vistas (24/24 idénticas en geometría); guarda `ScaleTokensAreUsedTest`.
- ⚠️ Lo que no tiene escalón (24, 32, 36, 40…) se queda en literal: **no lo metas con un `calc()` a mano**. Queda la G, con la organización.
- ⚠️ La sonda vive en `storage/app/audit-hallmark*.mjs` (gitignorada) y **sus cuatro trampas en §9** — la primera: «dos líneas» por alto/line-height cuenta el área táctil de 44 px como segunda línea.
- ▶ ✅ 📜 **EL CARRIL DE DISEÑO SE REABRIÓ EL 2026-09-09** (`#469`) y lo gobierna **`docs/specs/rediseno-desde-canvas.md`** — esta fila queda como HISTÓRICO de lo medido antes. Lo que decía `#452` (2026-09-03, `[DECIDIDO owner]`): el owner delegaba el diseño a **Claude Design** y avisaría con la base; **al volver, lo primero era leer su sistema entero y contrastarlo con el código**, y eso es exactamente lo que hizo `#469`. Lo hecho está en **`docs/archivo/`** (`design-producto-2026-09-03.md`, que era el `design.md` de la raíz · `guion-de-la-portada.md` · `auditoria-cajon.md`) y **no se mantiene**; los prototipos `/_diseno/…` y `scripts/prototipo-b/` se retiraron; y **el cajón volvió a como estaba antes de `#450`/`#451`** (revert en un commit nuevo; `#438`–`#451` quedan en `DECISIONES.md` como registro de lo medido y decidido).
- ⚠️ **Lo que SÍ queda en código son las tandas C→F de la auditoría de la web pública** (`#434`→`#437`, con sus guardas) y su paso de despliegue (cinco líneas en el `client.css` de producción).
- ⚠️ `DesignSync` sin autorización: la copia del canvas es del 27/28/31-08. — **`docs/specs/idioma-visual-heredado.md`**
- 🟦 **EN EL ÁRBOL: TANDA A + T2 + T4 + T5 + T6 + T7 + T8 + T9 + T10** (`#290`, `#293`, `#302`, `#303`, `#307`, `#309`, `#314`, `#321`, **`#323`**) —
- ❗❗❗ **`#323` SI TOCAS UNA TARJETA (§3.duodecies): hay DOS niveles y solo dos** — pegatina (borde `--paper-fg` + `--shadow-float`) para lo que se elige o se compra (`.price` · `.ride-card` · `.visit-card` · `.rules-must__card` · `.rule`), sin sombra para el apoyo (`.socks-note` · `.rules-peek__item`); ninguna pegatina levita, y la que no es enlace no tiene hover. Guarda: `CardSkinTest`.
- ⚠️ Una captura con la fuente de RESPALDO enseña otra tarjeta: la sonda comprueba `fonts.check()` antes de medir. —
- ❗❗❗ **`#321` SI TOCAS UN BOTÓN (§3.undecies): hay UNA familia (`.btn`) y la acción es `--action` en toda la web** — el hover NO salta (la física de `#217`/`#303`; queda la pisada `:active`) y su texto sigue al rol; `btn--zone` está PROHIBIDA en Blade pero SIGUE en CSS a propósito (el cajón Vue la emite y el SPA está aparcado); `bd-btn` no existe; la piel del botón (¿plana o pegatina?) es la tanda B y se decide con opciones renderizadas. Guarda: `SingleButtonFamilyTest`.
- ▶ La T9 es la 1.ª tanda de la AUDITORÍA DE DISEÑO (informe-artefacto «Un solo idioma», temas T1–T7): lo que sigue de ella se retoma por `ESTADO.md` carril 4. —
- ❗❗❗ **`#314` SI REORDENAS SECCIONES, TOCAS EL AIRE ENTRE ELLAS O ESCRIBES UNA GUARDA DE SECCIÓN** (§3.decies): el orden es **entradas → cumpleaños → el parque → ubicación → normas → dudas** (`[DECIDIDO owner]`), y en código fue mover UN bloque; los anclas y `--hero-air` (que cuelga de `.hero + .section`) se mudan solos.
- ▶ **El aire es 240 px en escritorio y 160 en móvil, uniforme**, y el número NO era el problema: **la banda de cumpleaños no es un `.section`**, llevaba relleno inferior **CERO** y tenía la mitad de aire que las demás (96 y 110 contra 193, medido) — con cumpleaños en segunda posición el salto quedó donde más se ve.
- ⚠️ **`.bd-page` tiene DOS hijos en la portada** (`bd-sec1` y el «paso a paso» de `bd-sec3`): **manda el segundo** en el aire de salida, y suponer que era el primero costó una vuelta.
- ⚠️⚠️ **Y las reglas de móvil NO HACÍAN NADA por estar mal colocadas**: se escribieron en el `@media (max-width: 768px)` que vive ~100 líneas ANTES de las bases de `.bd-sec1`/`.bd-sec3`, y a igual especificidad **gana la última regla del fichero** — *el fallo de cascada más caro de diagnosticar, porque no hay nada que leer que parezca mal*. Van detrás de sus bases.
- ❗❗ **UNA GUARDA DEPENDÍA DEL ORDEN Y FALLÓ CON EL PRODUCTO SANO**: `ZonesSectionTest::seccion()` recortaba «desde `id="zones"` hasta `id="pricing"`», así que al adelantar tarifas se comió media portada y contó cuatro `<h2>`.
- ▶ *Un localizador que depende de qué sección viene DESPUÉS no acota una sección: acota un tramo de página.* Re-apuntada al ELEMENTO.
- ⚠️⚠️ **TRES trampas de instrumento en una tanda, todas con cifras creíbles**: la sonda de aire contaba **la CAJA de un contenedor** como tinta (38 px donde hay 240) · antes metía a `.bd-page` **y a su hijo** en la misma lista y el aire salía **negativo** · y **la captura de página completa enseña las fotos del carrusel como TRAMA** y parece que no cargan — son **23 `loading="lazy"` en un carril horizontal** y solo cargan las **3** visibles. *Compruébalo desplazándote de verdad antes de «arreglar» nada.*
- ▶ **Las 35 fotos del parque están subidas** (`#313` las 26 asignadas, `#314` las 9 restantes como `pjp-NNN.webp`).
- ⚠️ **Las asignadas NO se renombran**: sus nombres describen la ATRACCIÓN, no al cliente, y pasarlas a `pjp-NNN` metería la numeración de este parque en el producto y tocaría el seeder y cuatro tests para nada.
- ⚠️ **26 huecos para 35 fotos**: nueve quedan sin asignar, y varias son cosas que el parque TIENE sin dar de alta — crearlas es DATO. —
- ❗❗❗ **`#309` SI TOCAS ZONAS, TARIFAS, NORMAS, CUMPLEAÑOS O EL KIT DE FACHADA** (§3.nonies): cinco secciones en un encargo del owner.
- ❗❗ **REVISA A SABIENDAS EL PRESUPUESTO DE `#292`** («una pieza de dibujo por sección, TRES en la portada»): ahora son **CUATRO** y normas lleva **dos**; anotado en `IllustrationKit::SLOTS`.
- ⚠️ **Lo que NO cambia es `FacadeDecorationIsPerScreenTest`**: ninguna pieza decorativa dentro de un bucle — ésa era la que evitaba el defecto real.
- ▶ **El kit pasa de 4 a 7 símbolos**, extraídos del artboard **con un guion** (la regla de `#257`): `slot-tarifas` es el **friso familiar `G3`** con las cajas del propio artboard, y dos manchas.
- ⚠️ **Solo quedaban TRES manchas libres de seis**: dos ya viajan como `--deco-blob-a/b` y una es `slot-zonas`.
- ⚠️⚠️ **El friso se compone con `<g transform>`, NO con `<use href="#pose">` internos**: un `<use>` interno dentro de un símbolo referenciado por `<use>` EXTERNO no resuelve igual en todos los motores y aquí solo hay Chrome (`hueco-ilustracion.md` §2.3).
- ⚠️ Una cuarta mancha se generó y se **retiró**: la banda de cumpleaños usa el contorno de `zone-cumpleanos`, que ya viajaba, y **una ranura sin pantalla tumba su guarda**.
- ▶ **ZONAS**: silueta 44 → **104 px** asomando;
- ⚠️ **`overflow: visible` no era la palanca** (un `<button>` ya lo es) — lo que hacía falta era SITIO.
- ⚠️⚠️ **Y agrandar el splash ROMPIÓ el criterio medido de `#303`** (9.936 px² sobre el párrafo): barrido de CINCO combinaciones, **ninguna crece sin caer sobre el texto** y moverla a la izquierda lo empeora cubriendo MENOS titular → queda el tamaño de `#303` con la opacidad al doble (0,14 → **0,30**) y **0 px² sobre el párrafo**. *Se ve más porque pinta más, no porque ocupe más.*
- ▶ **NORMAS**: dos requisitos en pegatina con mancha grande y CTA (izquierda, `sticky`) + **cuatro** normas y enlace a `/normas` (derecha).
- ❗❗ **EL `sticky` TIENE 99 px DE RECORRIDO Y A 1280×900 NO SE ENGANCHA** (la sección mide 830 y cabe entera): **las dos cosas pedidas se estorban** —columna pegajosa vs. tope de 3/4 normas— y la palanca es el tope, que es del owner.
- ⚠️ **El ancla `#rules` nace con su consumidor** (el CTA de tarifas): un ancla a una sección que no existe **NO falla**, lleva a la home — que es exactamente cómo el enlace a `#gallery` sobrevivió a su sección. Guarda con las dos mitades.
- ⚠️ **Los CTA reutilizan el mecanismo del producto**: registro → las MISMAS tres ramas que `<x-site.cta-pair>` (externo · con sesión · sin sesión), calcetines → el cajón de compra. La primera versión ofrecía el alta **también con sesión** y lo cazó una guarda del nav.
- ⚠️⚠️ **Faltaba la media query y lo vio la CAPTURA, no la suite**: a 390 px la sección seguía en dos columnas y el `overflow: hidden` **cortaba titular y botón**. *Ninguna guarda mira anchos.*
- ▶ **TARIFAS**: friso + CTA a `#rules`; la nota de calcetines sale de la portada pero **`/precios` la conserva por PROP** — borrarla del componente compartido habría quitado un requisito de seguridad de una página que no tiene normas donde recogerlo.
- ▶ **CUMPLEAÑOS**: fuera el «foam» (el motivo del cliente antiguo **copiado como geometría**, `#293`); entra el **contorno** de la pose, que lo manda `F6` del artboard —«sobre color saturado, contorno»—
- ⚠️ y el primer intento la sacaba por el canto y **se leía como un garabato**.
- ⚠️⚠️ **Los toggles miden lo mismo que la tarjeta de precio y el ancho se DERIVA de la rejilla** (`(100% − gap) · 1.05/2`, con el gap en un token que leen las dos): desfase **0** en los dos anchos. *Un `420px` a ojo cuadra a un ancho y se despega en el resto sin que nada falle.*
- ⚠️ Y destapó un **defecto PREEXISTENTE**: la tarjeta de pack **se salía 11 px de su columna** a 390 px, escondida en el relleno de la banda.
- ▶ **«EN DIRECTO» RETIRADA** con su CSS y su enlace del pie.
- ⚠️⚠️ **La categoría de cookies `social` se queda sin gatear NADA** —ajuste, servicio y la frase del banner— a sabiendas: el hueco es el de las reseñas; ficha en `DEUDA.md` con las dos salidas.
- ⚠️⚠️ **Retirar la sección vieja se llevó `.rules-grid` y `.rule`, que los usa `/normas`** —la página a la que lleva el CTA nuevo—: lo cazó buscar consumidores **por FICHERO y por clase EXACTA**, no por «esto ya no lo usa la home».
- ⚠️ **Dos trampas**: una sonda dio **20.306 px² de «friso sobre titular» y era FALSO** (medía la caja del `<div>` de 920 px, no la tinta), y una mutación no mordió porque **la mala era la mutación** — el bucle del pie lo manda la lista de RÓTULOS. —
- ❗❗❗ **`#307` SI TOCAS «VISÍTANOS», EL PIE O LOS DATOS DE LA INSTALACIÓN** (§3.octies): la sección es **TRES TARJETAS y UN encabezado** (`[DECIDIDO owner]`: «todo en cards, lo siento más organizado y limpio»), y con ella cae el caso EXTREMO del molde de `#297` — 4 encabezados para 4 líneas de dato.
- ❗ **La tarjeta es la PEGATINA de `#303`, con los valores de `.ride-card`** — no un cuarto tratamiento: inventar aquí otra tarjeta es cómo murió el sistema de sombras de `#196` y el de badges de la tanda A.
- ⚠️ **Sin `:hover`** (no llevan a ninguna parte: afordancia sin consumidor, `#295`) y
- ⚠️ **sin títulos dentro** — §3.quinquies.4 mandó retirar los `h3` internos, y ponerlos «porque una tarjeta necesita cabecera» rehace el molde desde dentro. Guarda: **`VisitSectionTest`** (un solo encabezado · tres tarjetas · el teléfono · nada de «Parking»).
- ▶ **Entra `closes_at`** en `HeroStatus`, que lo calculaba y lo TIRABA;
- ⚠️⚠️ **NO se deduce de `weeklyRows()`**: su `is_today` se APAGA cuando manda una temporada o una fecha especial — *se acierta casi siempre y se falla los días raros, que son justo cuando el visitante lo necesita* (medido: con una activa, las dos filas en `false`).
- ⚠️ **Se fue el marcado heredado CON su CSS y sus dos `@media`** (`.info__grid`, `.info-card`, `.hours`); **`.map-card`/`.map-pin` SE QUEDAN** porque las usa `/contacto` — comprobado **por clase exacta y por fichero**, que un `grep "hours"` casa con `visit__hours`.
- ⚠️ **En móvil la sección CRECE 45 px y se dice**: tres pegatinas cuestan ~100 px de chrome (se recuperaron 52: un `margin-bottom` que se sumaba al `gap` de la rejilla, y el mapa apilado 240→200). Escritorio **679 → 659**.
- ▶ **DOS formas nuevas se montaron, midieron y RECHAZARON antes de ésta** (§3.octies.1, eje «quién abre la sección»: los datos o el mapa) — **no las vuelvas a proponer**, como tampoco A/B/C de `#297`.
- ❗❗ **TRES DEFECTOS que destaparon los datos reales**: (1) **el pie servía `<a href="#">Instagram</a>` y lo mismo TikTok** en toda instalación sin redes, y un `tel:` a un placeholder — era **el único de los TRES consumidores** (menú y `sameAs` de schema.org ya lo comprobaban) que no miraba el centinela `'#'`; arreglado con `FooterContactLinksTest` y 2/2 mutaciones en rojo. (2) **`legal.jurisdiction` se LEE en el aviso legal y las condiciones y NO ESTÁ en el panel** — el gemelo invertido de los tres campos de `#304`; ficha en `DEUDA.md`, y **falta el valor, que es del owner**. (3) **Las franjas no cubren el horizonte**: 486 filas del 24-06 al 02-10 pero **solo 15 días con más de 5**, y el sábado 05-09 ofrece **CERO horas** — es AFORO, **NO se tocó**, ficha con el comando.
- ⚠️⚠️ **DOS instrumentos propios dieron cifras creíbles y FALSAS**: el área táctil dio 39 px porque medía la caja del `<a>` y no el pseudo de `[data-tap]` con su `transform` (la efectiva es **105×44 y 130×44**) — *lo delató que acusaba también a la forma ya verificada en `#264`: si tu instrumento acusa a lo que ya estaba bien, el defecto es del instrumento*; y sondear la Embed API de Google **fuera de un `<iframe>`** hizo que sujeto y CONTROL dieran idéntico.
- ⚠️⚠️ **Blade COMPILA las directivas dentro de un comentario**: citar `@php` en prosa dentro de `{{-- --}}` abre un bloque que se traga media plantilla y el error señala el FINAL del fichero (el escalón de `#298`) — *y volvió a caer en él el comentario escrito para advertirlo*.
- ⚠️ **El enlace de Maps del cliente es de COMPARTIR, no de INSERCIÓN**: la URL de inserción se construyó y **se verificó con CONTROL** (ficha falsa → sin chincheta). —
- ❗❗❗ **`#303` SI ESCRIBES UN TITULAR DE SECCIÓN O TOCAS UNA TARJETA** (§3.septies): **fuera la ETIQUETA de toda vista pública y los titulares a UNA palabra**, sin punto y en tinta (Dos zonas · Tarifas · Cumpleaños · Visítanos · Normas · En directo · Dudas). Guarda: **`SectionHeadlineTest`** (sin `class="eyebrow"` · sin `<br />` entre las mitades).
- ⚠️⚠️ **Se eligió sobre DOS caminos MEDIDOS**: con el suelo del `clamp` en 48 px, a 390 px solo caben **~10 caracteres** —«Tarifas claras.» pedía 40 y «Un parque, dos zonas.» 29—, así que era «una palabra» o **bajar el suelo** (el arreglo de `#220`).
- ⚠️⚠️ **PUSE un punto en color —titulares y tarjetas— Y EL OWNER LO RETIRÓ**: 4 de 7 titulares ya acababan en punto y yo se lo añadí a los otros 3; el color fue invención mía para un efecto colateral de mi propio cambio. *Si al acortar algo pierdes una propiedad del diseño, dilo en vez de resolverlo por tu cuenta.*
- ❗❗ **DOS titulares NO se tocan y están declarados**: el del CIERRE («VAMOS A / SALTAR» — tres partes apiladas, coreografía de `#252` medida contra el alto de ventana; excepción con su comprobación de que sigue teniendo sujeto) y **los nombres de servicio de `/servicios`, que los escribe el PANEL** — *un titular data-driven no puede tener regla de longitud: acortarlo es truncar el texto de un cliente y encoger el tipo es rendir el diseño al dato más largo*.
- ▶ **La TARJETA de atracción es una PEGATINA** (borde de tinta + sombra dura).
- ⚠️⚠️ **Su `E1` se titula «BOTONES»**: la tarjeta es `E3` y pide otra cosa (mancha de esquina + silueta), así que aplicarle la regla de los botones fue **extrapolación mía presentada como cita**;
- ⚠️ **la sombra entra como ROL `--shadow-float`, NO como valor** —con este paquete vale `5px 5px 0`—, y **no se levanta al hover**: responde la sombra.
- ▶ **CTA en TODAS las tarjetas**:
- ⚠️ **no hay página de detalle de atracción**, así que lleva a **reservar la ZONA**.
- ⚠️⚠️ **Y LA MANCHA SE CAYÓ SOBRE EL PÁRRAFO al acortar los titulares** (11.016 px²): su `top` era un **porcentaje de la CABECERA**, que encogió de 264 a 153 px — *un ajuste sobrevive a la razón que lo justificaba*. Va anclada al **bloque del titular** y re-dimensionada (`-84%` · `min(22%,165px)`), porque con 250 px de mancha sobre un titular de 79 **mover el `top` no cambiaba el número**.
- ⚠️⚠️ **El aire hero→primera sección (`--hero-air`) empezó como COMPENSACIÓN y acabó siendo DECISIÓN**: nació en 32 px medidos (lo que ocupaba la etiqueta) y **seguía viéndose corto con razón** —antes lo primero bajo el hero pesaba 16 px y ahora son **71 de tinta maciza**: mismo hueco, más presión visual—. Está en **64** por `[DECIDIDO owner]` sobre tres opciones renderizadas; **no lo bajes a 32 buscando «el número correcto»**. Va **solo en `.hero + .section`**: subirlo en `.section` separaría las otras seis.
- ⚠️⚠️ **DOS TRAMPAS DE INSTRUMENTO que dieron números creíbles**: una **captura de ELEMENTO** más alto que la ventana **COSE los elementos `fixed`** (enseñaba flechas que miden **0×0 con `display:none`** — usa captura de VENTANA), y un contador **por subcadena** dio **115 tarjetas donde hay 23** (`class="ride-card` casa con `ride-card__viz`…). *Acota al ELEMENTO.* —
- ❗❗❗ **`#302` SI TOCAS ZONAS, ATRACCIONES O COLOCAS MATERIAL DE FACHADA** (§3.sexies): **había DOS selectores de zona en la misma página** (las tarjetas SALTABAN a la sección donde las pestañas hacían la misma elección) y el de arriba costaba **1.011 px en escritorio y 1.831 en móvil**.
- ▶ Queda **UNA sección, UNA cabecera y UN selector**, que dice quién es cada zona: dibujo del kit + nombre + **edad**; la tarjeta de atracción se queda en **foto + título + tag** y se amplía (380×497 → **475×713**).
- ⚠️⚠️ **La cabecera que sobrevive es la de ZONAS y no es arbitrario**: su párrafo acaba en «Elige el tuyo», que es lo que hace el toggle de debajo.
- ⚠️ **`#rides` envuelve selector Y carriles**: `applyZoneAccent()` tiñe ese contenedor, así que con el ancla solo en el carrusel las pestañas pierden el color de su zona; y las **dos anclas** tienen 6 enlaces que dependen.
- ▶ **FACHADA — la regla que sale de aquí**: un icono por zona **es IDENTIDAD, no decoración**, así que NO choca con `#286`; una mancha por PANTALLA sí, **por tarjeta NO** (el CSS lleva la lápida del intento que el owner rechazó por ruido).
- ⚠️ **La mancha se ELIGIÓ y se COLOCÓ midiendo**: `B1·02` es la más ancha de las libres (1,19) y `top: -20%` da **0 px² sobre el párrafo** (con `-14%` eran 701).
- ⚠️ **El carrusel NO va a sangre completa a propósito**: dentro de `.wrap` el `100%` de `--wrap-gutter` mide el CONTENEDOR (trampa de `#238`); el corte es contra el borde de la columna.
- ⚠️ **Dos defectos PREEXISTENTES arreglados**: `cursor:pointer` sobre un `<article>` sin enlace, y `zone: 'jump'` —el slug del primer cliente— quemado en el JS.
- ❗❗ **GUARDA NUEVA que faltaba desde `#257`: toda ranura declarada tiene que tener pantalla que la pinte** — estaba escrito en tres sitios y no lo imponía nadie.
- ⚠️⚠️ **Una mutación NO mordió porque el caso nació SIN SUJETO** (`accent == slug` en la BD de test) y al rehacerlo se vio que **dependía del kit REAL, que está GITIGNORADO**: un test así pasa en tu máquina y falla en un clon limpio — instala un kit falso en un `public/` temporal.
- ❗ **Cuesta**: fuera las 2 fotos de zona, los 2 subtítulos y las métricas, y **`zones.image` se queda SIN CONSUMIDOR** (`DEUDA.md`, tres salidas, es del owner).
- ⚠️ La portada pinta **4** pestañas y no 2 porque `cap`/`cap2` están marcadas para la landing: es DATO, se quita **desde el panel**. ·
- ⛔ **EL MOLDE EDITORIAL, DIAGNOSTICADO Y CON TRES FORMAS RECHAZADAS** (`#297`) —
- ❗❗❗ **`#301` SI TOCAS ZONAS O ATRACCIONES** (§3.quater, cabecera): **la T3 está REVERTIDA EN SU ESTRUCTURA** (`[DECIDIDO owner]`: «2 cards y debajo la sección de juegos») — vuelven `#zones` y `#rides` como DOS secciones con pestañas, flechas y barra de progreso.
- ❗❗ **PERO EL ARREGLO DE IDENTIDAD SE QUEDA: la zona se identifica por `slug`, `accent` SOLO pone color.** Con datos reales `kids`/`cap`/`cap2` comparten acento, así que con `accent` las pestañas vuelven a abrir **tres carruseles a la vez**; medido tras revertir: **uno**.
- ⚠️⚠️ **La lección: un arreglo puede SOBREVIVIR a la guarda que lo protegía** — el caso vivía en `ZonesAndRidesUnifiedTest`, que vigilaba la estructura unificada, así que se fue con ella y dejó el arreglo **desnudo con la suite en verde**. Red nueva: **`ZoneIdentityIsUniqueTest`** (3 casos · 3 mutaciones que muerden · **una vigila lo CONTRARIO**: que la paleta siga saliendo de `accent`, para que nadie «termine el trabajo» moviendo también el color y rompa el agrupador).
- ⚠️ El resto de la revisión adversarial de `#295` (anillo de foco, `cursor:pointer` en tarjeta inerte, encuadre 62 %→31 %, `trim` por bytes) **se revierte CON SU SUJETO**: eran defectos *de la estructura unificada*.
- ⚠️ **`zone: 'jump'` NO vuelve** —el slug del primer cliente escrito en el producto—: nace vacía y la primera zona la dice el DOM.
- ⚠️ **Salen 4 tarjetas y no 2, y es DATO**: `cap` y `cap2` tienen `show_in_landing=1`, 0 atracciones y el acento de `kids`; se quitan **desde el panel**. —
- ❗❗❗ **`#300` TAMBIÉN CAMBIA LA BASE DE PARTIDA** (§3.bis, cabecera): **la T1 de normas está REVERTIDA ENTERA** (`[DECIDIDO owner]`, elegido con la consecuencia delante: revertir devuelve el pliego de doce pictogramas del cliente ANTIGUO y el carrusel con todas las normas).
- ▶ **La portada arranca con normas Y horarios las dos en su forma HEREDADA**, a propósito: el owner puso en el mismo punto de partida las dos secciones tocadas para rediseñar desde ahí, no encima de una tanda a medias.
- ⚠️⚠️ **Eso NO retira el diagnóstico ni su `[DECIDIDO owner]`** — el molde se sigue rompiendo; **y la sección de normas de hoy NO es una forma aprobada**, está anotado en la vista y en las dos hojas.
- ⚠️ **`#292` mezclaba TRES cosas y solo se revirtió la T1**: la retirada del registro fino de badges (`.tag--dato`) es decisión independiente y sigue en pie.
- ⚠️ `IllustrationKit::SLOTS` vuelve a estar **VACÍA** y el kit baja a **3 símbolos** —la gramática es CERRADA, así que una clave sin ranura (o al revés) hace el kit **no servible** y tumba la `GUARDA 7`—; `client-kit.svg` está **gitignorado**, así que la reconstrucción **no viaja en el commit**.
- ⚠️ **Presupuesto de dibujo de la portada: gasta UNA, quedan DOS.**
- ⚠️ **Horarios/ubicación no se tocó porque YA estaba como estaba, y se midió antes de creerlo** (byte a byte) — *cuando alguien pide deshacer algo, comprueba primero si ya está deshecho*. —
- ❗❗❗ **`#297` ANTES DE PROPONER NADA DE ESTRUCTURA** (§3.quinquies): el owner diagnosticó que *«la manera en que se exponen los textos, cuándo va cada texto, cada dato»* es del cliente antiguo, y **está medido**: las SIETE secciones usan el mismo molde —`ETIQUETA` → titular **partido en dos con coma** → párrafo → contenido— y **el dato llega siempre el último**.
- ⚠️⚠️ **El caso extremo: horarios y ubicación tiene CINCO encabezados para CUATRO líneas de dato** (803 px en móvil, 280 de ellos el marcador del mapa, 31 palabras). Y `zones` ocupa **3.604 px** de los 14.831 de la portada a 390.
- ▶ **`[DECIDIDO owner]` y SIGUE EN PIE: se rompe el molde y cada sección adopta la forma de lo que ES** — lo rechazado son las tres formas concretas, **no el criterio**.
- ⛔ **NO vuelvas a proponer A (el estado manda) · B (la respuesta primero) · C (el sitio manda)**: montadas en la web real, medidas y descartadas.
- ⚠️ **Las tres fallaban en el ESCRITORIO** (+105 y +136 px): hoy horario y mapa van en dos columnas y ellas apilaban en una — *quien retome esto resuelve el escritorio, no solo el móvil*.
- ▶ **Lo que NO hay que volver a medir** (cuatro propuestas independientes coincidieron): fuera la etiqueta, el titular partido, la rejilla de dos tarjetas, los dos `h3` internos, el `h4` de fechas y la caja blanca sobre el mapa; suben el estado en vivo, la fecha especial pegada a él, «Cómo llegar» sobre el mapa y el teléfono.
- ⚠️ **`HeroStatus` calcula la ventana de hoy y NO la devuelve**, y **no se deduce de `weeklyRows()`** (su `is_today` se apaga cuando manda una temporada o una fecha especial).
- ⚠️ **«Parking gratis 2h» está en el código, no en el panel** — `[DECIDIDO owner]`: se retira al rehacer la sección.
- ⚠️ **Presupuesto de dibujo de la portada: tres colocaciones, hoy gasta dos, queda UNA.** —
- ❗❗❗ **`#295` SI TOCAS ZONAS, ATRACCIONES O UNA GUARDA QUE RE-APUNTAS**: la identidad de la zona era `accent`, que **AGRUPA y no identifica** (`cap` y `cap2` comparten el de `kids`) → **tres carruseles con el mismo `x-ref`**, y pulsar «Zona KIDS» abría tres a la vez. Manda `slug`.
- ⚠️ **La misma raíz estaba arreglada A MEDIAS desde `#230`** (se corrigió el color, no la identidad): *cuando un campo demuestra que no identifica, hay que mirar TODO lo que lo usa para identificar*.
- ⚠️⚠️ **REVISIÓN ADVERSARIAL de 6 lentes: 40 hallazgos, 13 confirmados, 27 descartados**, y casi todo lo confirmado no lo veía la suite. **Lo peor: una guarda re-apuntada quedó VACÍA** —aseveraba `--zone-1:#hex` sobre la página entera y esa cadena **la emite también la sección de entradas**, así que borrando la sección de zonas seguía verde—.
- ▶ *Acota al ELEMENTO antes de creerte un test verde*, y **una guarda re-apuntada no puede quedar más débil que la que sustituye**.
- ⚠️⚠️ **`tabindex="0"` NO basta para el teclado**: la hoja tiene `*:focus{outline:none}` con **lista blanca CERRADA**, así que un `<div tabindex=0>` queda enfocable **y sin anillo** — *hacer algo enfocable no es hacerlo accesible*.
- ⚠️ **Una afordancia sobrevive a su consumidor**: las tarjetas, ya `<div>`, seguían con `cursor:pointer` y hover que levanta.
- ⚠️ **Una foto con alto FIJO cambia de encuadre al cambiar de ancho**: de media columna a columna completa el visible cayó de **62 % a 31 %**.
- ⚠️⚠️ **Retirar CSS deja los `@media` detrás** si solo se borra la regla base — tres se quedaron.
- ⚠️ **`trim($s, ' ·')` recorta BYTES**: el `·` es `C2 B7` y parte un `¡`.
- ⚠️⚠️ **Y dos casos de la guarda nueva NACIERON EN VACÍO** (la BD de test no tenía sujeto): *un caso sin sujeto no vigila nada, y no se nota hasta que se muta*. —
- ❗❗ **`#293` SI TOCAS LA CINTA DE `/servicios`**: **dos de sus números parecen adorno y son GEOMETRÍA** — el interior al **120 % con `margin-left: -10%`** (una banda del ancho justo, girada, deja **dos cuñas de papel** en las esquinas) y el **alto del envoltorio** (`½·ancho·sen(giro)` = **2,51vw**; sin él la cinta **se recorta a sí misma**: 5 elementos cortados, 22 px medidos).
- ⚠️ **El recorte NUNCA es `overflow-x` en `html`/`body`**: `#226` midió que eso rompe **todos los `sticky`**.
- ⚠️⚠️ **De su `C3` se toma la FORMA, no el contenido ni la FUENTE**: los títulos salen del panel, y el rotulador **no se usa** porque su paquete lo reserva al eslogan y su `T-02` lo limita a UNA por página (`/servicios` ya gasta la suya en el menú).
- ⚠️ Su nota dice «2°» y su marcado **−2,4°**.
- ❗ **El «foam» del cliente antiguo estaba ahí copiado como GEOMETRÍA, no con la clase** — *un motivo copiado a mano no aparece buscando su nombre*.
- ⚠️⚠️ **Dos trampas de sonda**: contar como cortados ítems ya invisibles (6 falsos en móvil) y creerse un cero **sin control** — el control **no muerde en móvil**, así que ahí el alto es aire.
- ⚠️⚠️ **Y una guarda nació LAXA**: `/width:\s*1[0-9]{2}%/` **acepta `100%`**, el valor que rompe la pieza — *un patrón que describe la FORMA del valor no dice nada sobre el valor*. —
- ❗❗❗ **`Landing PJP Modos` NO GUÍA LA ESTRUCTURA DE LA LANDING** (`[owner]`: «de esa maqueta solo sacaremos la sección de reseñas»), y eso **CORRIGE a `tema-por-instalacion.md` §1**: vale para color y sistema visual, no para decidir qué secciones hay.
- ⚠️⚠️ **Tumbó una decisión de la misma jornada**: el registro fino de badges venía de ahí y se retiró — **una sola voz, la del mural**. *Una contradicción entre dos fuentes no se resuelve eligiendo la que más gusta: se resuelve preguntando cuál es fuente para qué.*
- ⛔ **El BAR / zona de Ocio NO entra** (`[DECIDIDO owner]`): era lo único del encargo que tocaba el modelo. **Zonas y atracciones sí se unifican** (presentación, no modelo).
- ▶ **T1: las normas de la portada** — fuera el pliego de pictogramas del cliente antiguo, **tres** normas en texto y CTA.
- ⚠️ **El tope de tres se declara en la VISTA**: el panel decide QUÉ normas, el diseño CUÁNTAS caben (guarda con mutación).
- ▶ **Primera ranura decorativa** (`slot-normas`).
- ⚠️⚠️ **Dónde va la mancha lo decidió MEDIR**: el primer sitio caía sobre un párrafo (13.755 px²) y **bajarla no servía porque ahí no había hueco** — *cuando mover una pieza no cambia el número, el problema no es la posición*.
- ⚠️ **Presupuesto: una pieza de dibujo por sección, TRES en toda la portada** (hoy gasta dos).
- ⚠️ **Trampa**: un recorte de CSS se llevó **una llave de más** y dejó la hoja descuadrada — no falla, *se lee a medias*; lo cazó `ShapeScaleTest` (200 radios → 104). —
- ❗❗❗ **EL ENCARGO NO ES AÑADIR, ES CAMBIAR** (`[owner]`: «primero hay que cambiar lo que tenemos»), y eso **ACOTA la regla de `#286`** sin anularla: aquélla es para DECORACIÓN AÑADIDA; un badge o un separador son componentes FUNCIONALES y se repiten porque los datos se repiten.
- ⚠️⚠️ **No había sistema de etiquetas y está medido**: cinco formas, cinco paddings, cinco tallas y **cuatro rotaciones** para la misma función — el hallazgo de `#196` con las sombras, otra vez.
- ⚠️⚠️ **DOS artboards suyos las visten distinto y entran los DOS** (`[DECIDIDO owner]`, elegido sobre las tres renderizadas): **SEÑAL** → `E2` del mural (punteada, tinta); **DATO** → su landing (mono fino). *Su contradicción no se resuelve eligiendo un ganador: se resuelve diciendo para qué sirve cada uno.*
- ⚠️ **Sobre foto la punteada es la única que se lee** sin poner un rectángulo de color delante — por eso el badge de atracción deja de ir relleno.
- ⚠️ **Un solo giro** (−2°) y solo en las que se pegan encima de algo.
- ⚠️⚠️ **`FacadeDecorationIsPerScreenTest` cazó a quien la escribió**: el separador nuevo repetía una textura por fila; *un separador es puntuación, y la puntuación la pinta el CSS* (`--dots-tile` + pseudo).
- ⚠️ **`TagSystemTest` vigila la EROSIÓN**, que es como muere un sistema de etiquetas: nadie lo rompe de golpe, alguien le añade «un pelín más de padding».
- ⚠️ El registro SEÑAL **no fuerza mayúsculas** (el texto lo escribe el panel): «Top», no «TOP».
- ▶ **Quedan**: la marquesina de `/servicios` → cinta `C3` · los cubos del cumple · la galería · **el SPA, que el owner quiere iterar con cambio de PRESENTACIÓN, no solo de traje**
