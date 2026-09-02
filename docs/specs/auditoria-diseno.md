# [AUDITORÍA] Costuras a la vista — segunda auditoría de diseño de la web pública

> Estado: 🟦 **INFORME ENTREGADO, pendiente de las seis decisiones del owner (§7)** ·
> Última actualización: 2026-09-02 · Decisión asociada: `DECISIONES #430` (el informe vive en el
> repo; el artefacto es solo la presentación) · Carril: **diseño / idioma visual** (este ordenador,
> sub-banda `#430`–`#439`, reservada a distancia de la secuencia natural `#410`+ que sigue el
> otro carril de esta máquina, `specs/hora-extra.md` — aforo, sin solape de ficheros).
> ▶ **Sustituye al informe «Un solo idioma» (2026-09-01)**, que se publicó como artefacto y **no es
> recuperable**: no está en la cuenta del owner, ni en el repo, ni en local (medido: 5 artefactos
> listados con `scope: all`, ninguno es él). De él sobreviven el resumen en `ESTADO.md` y las dos
> tandas ejecutadas (`#321` el botón · `#323` la tarjeta). **Por eso este informe es un fichero.**
> Artefacto de LECTURA (presentación; si desaparece no se pierde nada): «Costuras a la vista» —
> https://claude.ai/code/artifact/4d0ce068-150e-4f33-86eb-96ba071c9f40

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
  enseñan las tres **sobre la FAQ y las pestañas reales**, a 1440 y 390.
- **D2 · Las interiores** (M3): versión larga vs. anclar. Y qué es `/entradas`.
- **D3 · Normas** (M6): sin icono · icono por norma desde el panel · lista.
- **D4 · Contacto** (M4, M5): dónde va la tarjeta, y «Parking» al panel o fuera.
- **D5 · Los hovers** (M1): retirar los cinco; y **confirmar** si el del logotipo es el «flota»
  de `#217`.
- **D6 · Movimiento** (M10): las 11 banderitas de la invitación y el spinner oculto.

## 8. Plan propuesto (el orden es una recomendación; las letras siguen a la A y la B ejecutadas)

| tanda | qué | gusto | tamaño |
|---|---|---|---|
| **C** | **Lo roto**: M4 (solape del mapa) · M7 (foco de campos) · M8 (acordeón) · C3 (`--on-ok`, el sub-rótulo, ≥ 10 px) | solo D4 | pequeña |
| **D** | **El color que responde**: C2 con D1, opciones renderizadas; retira `--zone-*` de lo que no es zona | D1 | media |
| **E** | **La física, terminada**: M1 · m1 · m2 · m3 · m4, con la guarda ampliada | D5 | pequeña |
| **F** | **La escala**: M2, sustitución mecánica + guarda | ninguno | media, cero reflujo |
| **G** | **Las páginas interiores**: M3 · M6 · m5 · m6 | D2 · D3 | grande |
| **H** | **Rendimiento**: M9 · M10 | D6 | pequeña |
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
