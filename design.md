# Design — JumpWeb (el PRODUCTO)

> El sistema de diseño **del producto**, escrito una vez y leído antes de tocar cualquier página.
> Es la primera pieza de la fase 2 (`docs/specs/guion-de-la-portada.md`) y sigue el flujo
> multi-página de la skill `hallmark` (`redesign` · § Multi-page): *una web necesita un sistema, no
> diecisiete temas; entre páginas del mismo producto la consistencia es el objetivo, no la variedad*.
>
> **Dos capas, y esta es la de abajo.** JumpWeb es white-label (`DECISIONES #1`): aquí viven los
> **ROLES y los MECANISMOS**, válidos para cualquier instalación. Los **valores de una marca** viven
> en su paquete —`public/css/client.css` · `client-logo.svg` · `client-favicon.svg` · `client-kit.svg`
> · `THEME_FONTS` en `.env` · `theme.*` en el panel (`docs/INSTALACION-CLIENTE.md` §4)— y su
> **perfil de diseño** junto a su material de referencia. El de PlayJump:
> `mockup_playjumppark/design-playjump.md` (gitignorado, como todo lo suyo).
>
> **Precedencia**: las palabras del owner → el paquete del cliente → este fichero → las referencias
> de la skill. Donde este fichero y la skill choquen, gana este fichero; donde este fichero y el
> paquete choquen, **gana el paquete** — y si el paquete contradice a su propio sistema, se anota en
> `docs/DEUDA.md` y decide el owner (ya pasó tres veces: `#213`, `#273`, `#280`).
>
> Estado: 🟦 **v1, 2026-09-03** (`DECISIONES #431`). Las familias de estructura (§2) están
> `[PENDIENTE: owner]`: la skill exige su confirmación antes de rediseñar una página, y aquí además
> se eligen **viéndolas renderizadas** sobre la web real.

---

## 0. Lo que este fichero fija y lo que no

**Fija**: los roles de color, tipografía, espacio, forma, elevación y movimiento; la voz de los
botones y de los controles; qué puede llevar cada tipo de página; qué comparten todas; y qué guarda
vigila cada regla. Todo lo que aquí es un token existe ya en `public/css/landing.css` (`:root`) y
`public/css/site.css` (`:root`) — este fichero **no declara valores nuevos**, los nombra y les pone
regla. Cuando una regla pide un token que hoy no existe, lo dice con **(futuro)**.

**No fija**: el contenido (lo escribe el panel: `zones`, `ticket_types`, `venue_rules`, `faqs`,
`attractions`, textos legales), ni el modelo de datos, ni el cajón SPA (aparcado,
`[DECIDIDO owner, 2026-09-01]`), ni el panel de administración (otro idioma, herramienta de
operador).

---

## 1. Género y postura

- **Género (skill)**: `playful` — consumidor, familias, móvil primero. Se toma su **disciplina**
  (limpio, entendible, movimiento que responde y no decora), **no su croma**: la skill limita la
  saturación porque no conoce la marca, y aquí la marca la trae el paquete. *Hallmark sigue la
  identidad que le dan* (`contract.md`).
- **Postura del producto**, dicha por el owner (2026-09-02): **limpio y entendible antes que wow**.
  Hay **un** momento de sorpresa por instalación —en PlayJump, el minijuego del cierre— y calma en
  todo lo demás, sobre todo en el camino del dinero (*«sorpresa en los momentos, calma en el camino
  del dinero»*).
- **Los controles cargan contenido.** Un selector, una pestaña o una tarjeta que solo lleva una
  etiqueta («Zona Kids») **no dice nada** (`[owner]`): la pieza con la que se elige tiene que
  responder ya a la pregunta del visitante (edad y altura, precio del día que va, qué incluye). Para
  **dos** opciones se enseñan las dos a la vez, y el clic es *hacer*, no *ver*.
- **Cada regla, donde muerde.** El parque es complejo (registro obligatorio, descargo, menores
  firmados por el padre, dos zonas por edad **y altura**, tarifa por tipo de día, 1 h / 2 h,
  complementos, post-formulario del cumpleaños). Ningún visitante lo necesita entero a la vez: cada
  dato aparece en el momento en que decide algo, y lo que es «después de comprar» se **promete** en
  la web y se **pide** en el embudo. El guion está en `docs/specs/guion-de-la-portada.md`.

---

## 2. Familias de estructura (macroestructura por tipo de página) — `[PENDIENTE: owner]`

La skill pide **una forma por tipo de página**, elegida a propósito. Hoy la portada tiene la forma
por defecto de una IA —hero a pantalla completa y debajo una lista de secciones en tarjetas— sin que
nadie la haya elegido. Se eligen **viéndolas sobre la web real con los datos reales** (paso 2c del
guion), no sobre maquetas.

| tipo de página | vistas | forma | estado |
|---|---|---|---|
| **Portada** (la que contesta las cuatro preguntas del visitante) | `/`, y lo que quede de `/entradas` | **una de tres, de categorías distintas**: **Conversational FAQ** (la portada son las preguntas, en grande, con su respuesta breve y su pieza) · **Split Studio** (díptico: texto a un lado, prueba al otro, alternando — la marca es «dos»: dos zonas, dos packs, dos tipos de día) · **Map / Diagram** (el plano del parque organiza «qué hay dentro») | `[PENDIENTE: owner]` — renderizadas en 2c |
| **Contenido** (se lee una vez) | `/normas`, `/contacto`, las cinco legales, `/servicios` cuando exista | **Long Document**: un título, una columna de 65–70 caracteres, cabeceras en línea, sin tarjetas salvo lo que se elige | ✓ es lo que ya son las legales (`pages/text.blade.php`) |
| **Interior de producto** (si sobreviven) | `/precios`, `/cumpleanos` | la **versión LARGA** de su sección de portada (todas las tarifas, temporadas y complementos · el proceso y la invitación), **o se retiran y se ancla** | `[PENDIENTE: owner]` (D2 de la auditoría) |
| **App** | el cajón SPA, `/mi-cuenta` | fuera: aparcado | — |

**Lo que NO es una familia**: la portada no puede ser la suma de las interiores. Hoy `/precios` y
`/cumpleanos` son un trozo de la portada con título (`docs/specs/auditoria-diseno.md` M3).

**Nav y pie** son del armazón (`docs/specs/armazon-y-menu.md`, `#200`→`#221`): dos racimos
flotantes que nacen bajo el hero + menú a pantalla completa; pie = tira de marca + una fila de
destinos que se desliza + colofón. **No se rediseñan aquí**: son la parte del sistema que ya está
elegida y medida, y no llevan la huella de plantilla (auditoría §2).

---

## 3. Color — los ROLES

Todo color del producto es un rol. **Ningún literal fuera de `:root`** (`RawColourIsNotATokenTest`;
la auditoría m4 lista los 37 que quedan y de dónde son).

| rol | tokens | regla |
|---|---|---|
| **Superficie** | `--bg` · `--bg-soft` · `--bg-card` · `--sheet` · `--fg` · `--fg-mute` · `--line` · `--line-strong` | Dos superficies, **papel** (defecto) y **tinta**, declaradas por ámbito con `data-surface` (`SurfaceScopeTest`). La de tinta se **deriva** de `--fg`/`--bg` (`--ink-*`), no se teclea. **El papel es continuo**: el fondo de una sección no lleva color; el contraste lo dan las tarjetas y las bandas (`S-00` del propio cliente, `tema-por-instalacion.md` §1.2) |
| **Acción** | `--action` · `--on-action` · `--action-hover` · `--on-action-hover` | **Un relleno de acción por pantalla.** Vacío = sigue a la superficie; con `theme.action` = idéntico en los dos fondos (`#209`, `ActionFillTest`). El hover se deriva ×0,88 |
| **Identidad de zona** | `--zone-1` · `--zone-2` | **Es la identidad de la zona que se está mirando** —lo repinta el servidor por elemento desde `zones.color`—, **no un color de la página**. Vale para lo que *identifica* una zona: su pestaña, su chip, su tarjeta, la tira. **Nunca para la INTERACCIÓN** (hover, foco, abierto, activo) fuera de una zona: su contraste no lo garantiza nadie porque cambia con el dato (auditoría C2, medido 2,45 : 1) |
| **Interacción** | `--interactive`, por superficie (`#436`) | El color de lo que responde al ratón y al teclado fuera de una zona (pregunta abierta, destino enfocado, enlace del banner, icono de enlace). **Producto por defecto: tinta** (`--fg`), como el foco, re-declarado en las dos superficies. El paquete lo fija por superficie con el contraste medido: `[DECIDIDO owner, 2026-09-03]` D1 = **el par del cliente** (PlayJump: Azul Muro en papel 6,43 · cian en tinta 6,85, su propia tabla de roles), elegido sobre tres opciones renderizadas. **Un RELLENO de marca no es interacción** (`.cta-med:hover`, el botón del minijuego): su texto lo calcula `--on-brand`. Guarda: `InteractionColourIsNotAZoneTest` |
| **Semánticos** | `--ok` · `--err` · `--warn` · `--attn` · `--refund` (+ `-bg`, `-border`, `-hover`) | Estado, nunca decoración (`--attn` ≠ `--zone-2` aunque hoy valgan igual: `#138`). **`--on-ok` · `--on-err` · `--on-warn`** (`#434`): el texto sobre esos rellenos. **No se derivan** —el servidor no ve `--ok`, que lo declara el paquete, y CSS no tiene luminancia—: el par lo declara quien declara el color (el producto para sus verde y rojo oscuros, el paquete para los suyos). Un `#fff` junto a un relleno semántico lo caza `SemanticFillTextTest` |
| **Foco** | `--focus-w` · `--focus-color` · `--focus-outline` | Anillo **instantáneo**, ≥ 3 : 1 contra la superficie, **por superficie** (`#209`; PlayJump se aparta de su sistema aquí con medida: Azul Muro en papel, amarillo en tinta). Sobre **todo** lo focalizable, campos incluidos (auditoría M7) |
| **Tira de marca** | `--strip-1..5` | Cinco franjas en orden fijo; el producto cicla sobre los dos de marca, el paquete pone cinco |
| **Manchas** | `--deco-blob-a/b` (máscara recoloreable) · `--deco-tag` (imagen) | Huecos de fachada, `docs/INSTALACION-CLIENTE.md` §4.d/§4.e |

**Reglas duras de contraste**: texto de cuerpo ≥ 4,5 : 1 contra su fondo **efectivo**; grande e
iconos ≥ 3 : 1; **nada por debajo de 10 px** (revisar `--fs-9`); un color de marca que no pase
como texto **es relleno**, con texto tinta o papel encima (regla 04 del sistema de PlayJump, que es
la de cualquier marca saturada). Guarda pendiente: sonda de contraste sobre el HTML renderizado
(hoy `ThemeColorTest` mira tokens, no pares).

---

## 4. Tipografía — cuatro roles y una escala

| rol | token | uso |
|---|---|---|
| Rótulo | `--font-display` | titulares y cifras grandes. **Mayúsculas, nunca en párrafo ni en botón, mín. 20 px** |
| Cuerpo | `--font-body` | texto, subtítulos, botones. Cuerpo ≥ 16 px; línea 1,5–1,6; medida 65–70 caracteres |
| Dato | `--font-mono` | horarios, aforo, códigos, antetítulos: 12–19 px, mayúsculas con `.16em` en etiqueta; `tabular-nums` en toda columna de cifras |
| Guiño | `--font-accent` | el eslogan y nada más: máx. 6 palabras, **una vez por página**. Por defecto vale `--font-display` |

- **Cero cursivas** en titulares (gate 38a de la skill y el contrato del cliente). En cuerpo, solo
  énfasis dentro de un párrafo.
- **Máx. 3 familias + el guiño una vez** por pantalla (el «2+1» de la skill y el contrato del
  paquete coinciden).
- **La escala es `--fs-*` sobre `--fs-unit`** (`landing.css`): un paquete la mueve entera. **Todo
  `font-size` con escalón disponible usa el token** — desde `#437` lo hacen TODOS los que tienen
  escalón exacto (233 sustituidos a mismo píxel; guarda `ScaleTokensAreUsedTest`); lo que sigue en
  literal no tiene escalón en la escala.
  Los titulares son `clamp()` con suelo que quepa a 390 (`#220`, `#303`).
- **Titulares de sección: una palabra, sin punto, en tinta, sin etiqueta encima** (`#303`,
  `SectionHeadlineTest`). Las etiquetas de sección («01 · ZONAS») **no existen** en la web pública
  (gate 54): un número solo cuando la secuencia es real (los pasos de una visita).

---

## 5. Espacio y columna

- **La columna se escribe UNA vez**: `--col-max` (1176) · `--col-gutter` (32 · 24 · 16) ·
  `--wrap-gutter` solo para lo que va a sangre completa (`#238`, `#250`, `ColumnIsDeclaredOnceTest`,
  `CappedContainerGutterTest`).
- **La escala es `--sp-*` sobre `--sp-unit`**; todo `padding`/`gap`/`margin` con escalón disponible
  usa el token — desde `#437`, todos los que tienen escalón exacto en cada uno de sus valores (429
  sustituidos a mismo píxel; guarda `ScaleTokensAreUsedTest`). Quedan 163 con algún escalón fuera de
  la escala (24, 32, 36, 40…): entrarán cuando la escala los tenga, no con un `calc()` a mano.
- **El aire entre secciones es uniforme y decidido** (240 escritorio · 160 móvil, `#314`;
  `--hero-air` 64 bajo el hero, `#303`). Las secciones se distinguen por lo que contienen, no por
  el hueco.
- **Objetivo táctil mínimo 44** en todo control (`--tap-min`, `#264`; `[data-tap]` o `min-height`).
- Móvil: sin desbordamiento horizontal (`overflow-x: clip` en `html` y `body`, nunca `hidden`:
  rompe `sticky`, `#226`); ningún pulsable a dos líneas entre 320 y 1920 (el arreglo es anchura y
  tracking, no acortar un texto que escribe el panel).

---

## 6. Forma, borde y elevación

- **Radios: escala cerrada de siete** (`--r-xs` · `--r-sm` · `--r-md` · `--r-btn` · `--r` · `--r-lg`
  · `--r-pill`), y tres excepciones enumeradas (`ShapeScaleTest`). Radios concéntricos: interior =
  exterior − relleno.
- **La tarjeta tiene DOS niveles y solo dos** (`#323`, `CardSkinTest`): **pegatina** —borde 1 px
  `--paper-fg` + `--r-lg` + `--shadow-float`— para **lo que se elige o se compra**; **apoyo** —borde
  de línea, sin sombra— para lo que acompaña. Ninguna pegatina levita; la que no es enlace no
  responde al cursor. *No se inventa un tercer tratamiento*: así murieron el sistema de sombras
  (`#196`) y el de etiquetas (tanda A).
- **Elevación: tres roles, no una escala** (`#196`): `--shadow-lift` (se despega al pasar, si el
  paquete lo permite) · `--shadow-float` (flota siempre: la pegatina) · `--shadow-modal` (tapa la
  página). Más la familia del mobiliario flotante `--shadow-nav-*` (`#217`). Una tarjeta quieta no
  está elevada: está apoyada.
- **Bordes**: 1 px por defecto, 1,5 en botón fantasma, 2 solo en pegatina. Nunca un borde de color
  saturado alrededor de una tarjeta.
- **Rotación**: solo en manchas, cinta del eslogan y sello de precio (−2° a −8°). El texto
  informativo nunca se gira.

---

## 7. Movimiento

Cuatro curvas y siete duraciones con contrato (`#222`, `MotionScaleTest`): `--ease-entra` (lo que
aparece rebota) · `--ease-cae` (el rebote grande, uno por pantalla) · `--ease-sale` (cierres, foco y
**todo** el hover: sin opinión) · `--ease-bucle` (solo esperas). Duraciones 120 · 180 · 240 · 320 ·
420 (techo) · 620 (solo el salto del hero) · 900 (espera); ambientales aparte (`--dur-invite`,
`--dur-cinta`, `--dur-switch`).

Reglas: **lo que entra rebota, lo que sale no y en la mitad de tiempo** · el sobreimpulso se paga
en píxeles (nunca opacidad ni color) · **máximo dos cosas animándose a la vez** · **nada en bucle
salvo las esperas** y lo declarado ambiental (`#279`: presupuesto medido por vista, hoy superado —
auditoría M10) · se anima `transform`/`opacity`, **nunca layout** (`max-height`, `padding`, `width`)
· una sección se anima **la primera vez** que entra, no en cada scroll · el texto no se mueve
nunca: se mueve el bloque que lo contiene · `prefers-reduced-motion` quita desplazamiento y escala
y **conserva** los fundidos de 120 ms; el reposo de una pieza se declara **fuera** de esa excepción
(`#280`).

---

## 8. Microinteracciones — la postura

- **El hover responde, no levanta**: color, sombra que se acorta, o nada. **No salta** (`#217`,
  `#321`, `#323`); queda la pisada `:active` (`translateY(1px)`). Excepción declarada por paquete
  (PlayJump: *la pegatina se aplasta*). Lo que hoy sigue saltando fuera de `.btn` está en la
  auditoría M1 y se retira en la tanda E, con la guarda ampliada a todo `:hover`.
- **El foco aparece al instante** y no mueve el elemento (auditoría m2: `padding-left` al foco).
- **Éxito silencioso**; el aviso, para lo que falla o no se ve. La única celebración es el confeti
  de la confirmación (`#258`).
- **Nada se revela por hover** que no tenga su equivalente táctil.
- **Transiciones con propiedades nombradas**, nunca `all` (auditoría m1).

---

## 9. La voz de los botones y de los CONTROLES

- **Una familia de botón**, `.btn`, con `--ghost` y `--sm` (`#321`, `SingleButtonFamilyTest`);
  **la acción es `--action`** en toda la web; un solo relleno de acción por pantalla, el resto
  fantasma o tinta. El rótulo dice el verbo (*Reservar*, *Llamar*), en cuerpo, ≥ 16 px, una línea.
- **El par del armazón** (`#205`, `#217`): Reservar + Registrarse, uno ancho y otro reducido a
  icono; el primero expande, el segundo actúa. Cambia de rol dentro del menú (`#213`).
- **Los controles cargan la respuesta** (§1). Un selector de opción excluyente no es un formulario:
  - **Un solo componente para «elige zona»** *(futuro)*, usado en tarifas, cumpleaños y juegos —
    hoy son tres (`.zone-tab` · `.bd-tab` · `.zone-pick__tab`)—, y lleva **edad y altura**, no solo
    el nombre. Su forma se elige renderizada (guion D-G3: la marca de altura · dos tarjetas con dato
    · pestañas).
  - **Dos opciones se comparan, no se alternan**: los dos packs lado a lado con lo que incluye
    cada uno; el toggle esconde justo lo que el padre quiere comparar.
  - **El precio se enseña para el día que se va**, o con sus dos precios llanos; nunca «12 €» + una
    etiqueta que obliga a sumar (guion D-G4; el propio diseñador del cliente ya argumentó las dos
    salidas en `Precios PJP variantes` · turno 4).
- **Jerarquía de pulsables aprendible en la primera pantalla**: hoy la portada tiene ~12 especies
  además de `.btn` (auditoría). Objetivo del guion: `.btn`, el selector, la tarjeta-enlace, y poco
  más.

---

## 10. Fachada e ilustración — el hueco por instalación

- El producto **no lleva ningún dibujo de ningún parque**: el paquete entrega `client-kit.svg` y el
  producto lo pinta con `<use>` por **nombre cerrado** (`slot-*` los declara el producto,
  `zone-<slug>` sale de `zones.slug`; `docs/specs/hueco-ilustracion.md`, `#286`, `#287`). Sin
  paquete, no se pinta nada. Tres tratamientos de una geometría: plano · contorno · troquel.
- **Presupuesto**: una mancha grande por pantalla, las demás pequeñas y al borde; **la pintura
  nunca debajo de un párrafo**; trama **o** rayos, nunca los dos; una pose no se repite en la misma
  pantalla; **ninguna pieza decorativa dentro de un bucle** (`FacadeDecorationIsPerScreenTest`) y
  ninguna sin pantalla que la pinte (`FacadeCssHasNoOrphansTest`). Un icono por zona **es
  identidad**, no decoración (`#302`).
- **Iconos**: un solo set, rejilla 24, `currentColor`, línea 1,6 (`IconSetAnatomyTest`,
  `SidebarIconParityTest`); el marcador de producto lo elige el panel (`ticket_types.icon`,
  `ProductIcon::CHOICES` solo crece). Un icono repetido N veces sin dato detrás es decoración
  (auditoría M6).
- **Logotipo e icono** por instalación (`client-logo.svg` en línea y saneado por `InlineSvg`,
  `client-favicon.svg`), sin sombra CSS (`#273`).

---

## 11. Copy

Castellano de la instalación, ES/EN/FR desde el panel. Verbos en los botones; el enlace se
entiende solo; los errores dicen qué pasó y qué hacer; comillas «», guion —, puntos suspensivos …
(medido: cero comillas rectas ni «...» en las doce vistas). «Desde X €» siempre acompañado del otro
precio, o es cebo. Nada de métricas inventadas: si el dato no está en el panel, no está en la web.

---

## 12. Qué puede llevar cada tipo de página

| | portada | contenido | interior de producto |
|---|---|---|---|
| vídeo / hero a pantalla completa con imán | ✓ (el único) | ✗ | ✗ |
| minijuego (el momento de sorpresa) | ✓ (el cierre) | ✗ | ✗ |
| piezas del kit de fachada | ✓ con presupuesto | una trama en cabecera como máximo (`/normas`, `#287`) | ✓ con presupuesto |
| tarjetas pegatina | lo que se elige | solo lo que se elige | lo que se elige |
| bucles ambientales | los declarados (`#279`) | ninguno salvo la espera | ninguno salvo la espera |
| relleno de acción | 1 por pantalla | 1 | 1 |

**Todas comparten**: armazón y pie, las cuatro familias, la escala, los roles de color, la voz de
`.btn`, la columna, el aire. **Pueden diferir en**: la forma (§2) y la pieza de fachada.

---

## 13. Guardas — quién vigila cada regla

Existentes: `ActionFillTest` · `SingleButtonFamilyTest` · `CardSkinTest` · `ShapeScaleTest` ·
`MotionScaleTest` · `RawColourIsNotATokenTest` · `SurfaceScopeTest` · `ColumnIsDeclaredOnceTest` ·
`CappedContainerGutterTest` · `SectionHeadlineTest` · `TagSystemTest` · `LayerOrderTest` ·
`IconSetAnatomyTest` · `SidebarIconParityTest` · `FacadeDecorationIsPerScreenTest` ·
`FacadeCssHasNoOrphansTest` · `TouchTargetTest` · `ThemeColorTest` · `ThemeFontsTest` ·
`ClientThemePackageTest` · `GoogleButtonBrandingTest`.

Pendientes (las pide la auditoría, nacen con su tanda): contraste sobre el HTML renderizado ·
`--zone-*` fuera de la interacción · `:hover` sin `transform` fuera de lista · `font-size`/espacio
con token disponible · presupuesto de bucles por vista · un solo componente de selector de zona ·
`transition` sin `all`.

---

## 14. Exports

No aplica: el producto no usa Tailwind ni DTCG. Los tokens viven en `public/css/landing.css`
(`:root`, con su documentación al lado de cada bloque) y `public/css/site.css` (`:root`); el paquete
del cliente los redefine en `public/css/client.css`. Ese par de ficheros **es** el export.
