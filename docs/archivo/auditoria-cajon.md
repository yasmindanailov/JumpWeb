# [AUDITORÍA] El cajón de compra, de punta a punta — auditoría Hallmark del embudo SPA

> 📜 **ARCHIVADO el 2026-09-03 (`DECISIONES #452`, `[DECIDIDO owner]`)**: el diseño se delega a
> **Claude Design**. ⚠️⚠️ **Las tandas A y B (§11, §12; `#450`, `#451`) ESTÁN REVERTIDAS**: el cajón
> volvió a su estado anterior en un commit nuevo (manifiesto congelado incluido) y la C, que estaba a
> medias, se descartó sin commit. Lo que este informe describe como «en el árbol» **ya no lo está**;
> lo que sigue valiendo es la MEDICIÓN (§1–§6: 100 medidas, 3 críticos · 10 mayores · 7 menores) y
> las decisiones del owner de §7, que se retomarán sobre su sistema nuevo. **No se mantiene.**

> Estado (al archivar): 🟦 **INFORME ENTREGADO (`#438`) · DECISIONES TOMADAS (§7, `#439`) · TANDAS A Y B EJECUTADAS
> Y MEDIDAS (§11 `#450`, §12 `#451`); queda la C (la hoja, el calendario, el paso 5)** · 2026-09-03 ·
> Carril: **diseño / idioma visual** (sub-banda `#430`–`#439`, agotada; sigue en `#450`–`#459`).
> ▶ Hermana de `specs/auditoria-diseno.md` (la web pública): aquélla **excluía el cajón a propósito**
> (§1) y sus tandas dejaron **cuatro listas de excepción «que solo encogen»** con reglas del cajón
> dentro (§6). Este informe las mide, y dice cuáles de aquellas costuras cruzaron la puerta del embudo.
> **El registro es este fichero.** Instrumentos (gitignorados): `storage/app/audit-cajon-hallmark.mjs`
> (la sonda, con control) · `storage/app/resumen-cajon.py` (el agregado por hallazgo) · salida en
> `storage/app/audit/audit-cajon-{flow,outcomes-*}.json` + 49 capturas `cajon-<paso>@<ancho>.png`.
> ▶ **Hoja de decisión** (presentación; si desaparece no se pierde nada): «Cinco decisiones del cajón» —
> https://claude.ai/code/artifact/b332251a-85b2-4fb1-8414-5fd70dd4b86a — los hallazgos, la tanda A
> antes/después y las opciones de D-C1..D-C5 **renderizadas sobre el cajón vivo** (CSS inyectado y
> retirado, `storage/app/opciones-cajon.mjs`, 31 capturas a 390 en `storage/app/audit/opciones/`).

## 0. Lo que hay que saber en un minuto

- **Método**: `hallmark audit` (las 58 puertas, anti-patrones, el género `playful`, los estados de un
  campo) sobre **el embudo entero recorrido en un navegador real** con el motor `spa`: catálogo →
  día (tira y calendario) → hora + cantidad + complementos → carrito → identificarse (entrar · crear
  cuenta · el «no» del servidor) → pagar (y el «no» de las condiciones) → saliendo a la pasarela, con
  una ENTRADA y con un PACK (campos del evento, complementos, señal); y los dos desenlaces —reserva
  creada · pago denegado— entrando por el pase real `?redsys=` con un pedido pagado y uno pendiente.
  **15 pantallas × 7 anchos** (320 · 375 · 390 · 414 · 768 · 1280 · 1440) **+ 2 desenlaces × 2 anchos =
  100 mediciones**, todas DENTRO del panel del cajón, con CONTROL (un pulsable a dos líneas y un gris
  sobre gris inyectados en el panel, cazados antes de medir nada). Foco por TECLADO tabulando de verdad.
- **Veredicto: 3 críticos · 10 mayores · 7 menores.** El cajón está bien construido (cero
  desbordamientos, foco atrapado y con anillo, campos con anillo instantáneo y sin salto de borde,
  tres familias, cero bucles propios) y **le pasa lo mismo que a la landing antes de sus tandas**: el
  sistema de diseño se quedó en la puerta. Dentro, el color de la acción no es un rol, el cian de zona
  pinta texto ilegible en todas las pantallas y la mitad de los controles miden menos de 44.
- **Lo peor no es de gusto, es de LEGIBILIDAD y de DEDO**: «Volver» y la línea de contexto van a
  **2,21 : 1** en cinco pantallas de siete anchos; la **× que cierra el diálogo mide 15 × 26 px**; y en
  el paso de pagar de un pack a 320 el CTA «Pagar con tarjeta» parte en dos líneas.
- **Y una cosa de estructura que el owner ya vio (§7.4 de `cajon-en-movil.md`)**: en un teléfono la
  mitad de la hoja está vacía (362 → 591 px de 844) porque el panel mide siempre la pantalla entera.

## 1. Contexto: por qué ahora, y qué se audita

`[DECIDIDO owner, 2026-09-01]`: *«el SPA lo dejamos por ahora»* — la auditoría de la web pública lo
excluyó y sus tandas C, D, E y H dejaron cuatro listas de excepción con reglas del cajón dentro
(`HoverDoesNotJumpTest` 5 · `InteractionColourIsNotAZoneTest` 8 · `SemanticFillTextTest` 1 ·
`MotionBudgetTest` 4), todas «solo encogen». El 2026-09-03 el owner pidió **auditar también el cajón
de compra con todo el flujo** (`#438`). Se audita el motor **SPA** (`sidebar.engine = spa`, puesto
en local para esto y **dejado puesto**; para volver al Livewire basta borrar la fila:
`VERIFICACION-E2E-CAJON.md` §1). ⚠️ **Los dos motores comparten el CSS** (`site.css` 884–3050: 292
selectores, `sidebar-spa.md` §4.2) y el contrato de árbol: **todo hallazgo de hoja vale para los dos**.

**Lo que NO se audita**: el área de cliente del cajón (`/mi-cuenta`: es otra sección, `#66`), el
paso 11 «verificando» (necesita la notificación S2S; su pantalla es la del paso 6 con otro texto), el
panel, y la pasarela.

## 2. Lo que se sostiene (verificado hoy, no heredado)

| qué | medida | veredicto |
|---|---|---|
| Desbordamiento horizontal | **0 px en 100 mediciones**, panel y carril (`.purchase__scroll`) | ✓ G34 |
| Foco por teclado | 150 tabulaciones (25 + 30 + 20 en tres pantallas, a 390 y a 1280): **0 sin anillo, 0 fuera del diálogo, 0 invisibles** — el foco se queda dentro y vuelve al «×» | ✓ G26 · la trampa de foco funciona |
| Campos (G39) | borde **1 px en todos los estados**, anillo `3px solid` Azul Muro al instante, **sin salto de geometría** al foco (medido en 11 campos × 2 anchos); etiqueta encima en todos | ✓ salvo M8 |
| Semántica del diálogo | `role="dialog"` · `aria-modal="true"` · `aria-label="Reservas"` · scroll del cuerpo bloqueado | ✓ (⚠️ el `<main>` de detrás **no** lleva `inert`: lo suple la trampa de foco, medida) |
| Familias | 3 (Bungee · Hanken · JetBrains Mono), ninguna más | ✓ G37 |
| Bucles propios | **0** en las 15 pantallas (los 3 que corren son de la página, detrás del velo: m6) | ✓ |
| Los desenlaces | pegatina de éxito/error (`#258`), el código sellado, jerarquía de tres CTA | ✓ es el sistema |
| Copy | ni comillas rectas ni «...» en lo renderizado | ✓ |

## 3. Hallazgos — CRÍTICOS (3)

Formato de Hallmark: **tell** · **dónde** (fichero:línea) · **por qué** · **fix**. Todo medido.

### C1 · El embudo no tiene ROL DE ACCIÓN: tres colores de «primario» y el hover cambia de rol
- **Dónde** (`site.css`): `.acct__btn--primary` (**naranja**, `--action`, 4551) · `.cartbar` (naranja,
  1704) · `.bk-cta` (**cian**, `background: var(--zone-1)`, 1700) · `.btn--zone` (cian,
  `landing.css:864`, que el cajón emite en «Entrar», «Crear cuenta», «Reintentar el pago», «Hacer otra
  reserva») · `.bk-cta:hover` (**tinta** `--fg` + `translateY(-2px)` + `--shadow-lift`, 1701).
- **Medido**: en la misma compra el CTA principal es cian en «Añadir al carrito», «Pagar con tarjeta»,
  «Entrar»; naranja en «Iniciar sesión» del bloque de cuenta (paso 1) y en «Ver mis reservas» (pasos 6,
  9, 10); y tinta al pasar el ratón. En los desenlaces conviven **naranja y cian** en la misma pantalla
  (medido: `actionFills=1` + `zone=[Reintentar el pago]`).
- **Por qué es el hallazgo nº 1**: `#321` decidió que **«la acción es `--action` en toda la web»** y la
  landing lo cumple (≤ 4 % de relleno de acción, un color). El cajón es **el sitio donde está el
  dinero** y el único donde ese rol no existe: el cliente pulsa naranja para comprar en la web y cian
  dentro. ⚠️ Y el cian **no es identidad**: ningún módulo del cajón escribe `--zone-1` (medido:
  `grep` en `resources/js/sidebar/` → 0), así que un Pack Cumpleaños Jump se compra con el mismo cian
  que una entrada Kids — es el valor de `:root`, o sea, el color de la PRIMERA zona para todo.
- **Fix**: `.bk-cta` y `.btn--zone` → `--action`/`--on-action`, hover `--action-hover` (la física de
  `.btn`, `#321`: responde, no salta); el `.acct__btn--primary` **fuera del rol de acción dentro del
  embudo** (M4). Un relleno de acción por pantalla (`design.md` §3). Es D-C1 (§7).

### C2 · El cian de zona pinta TEXTO ilegible en todas las pantallas del embudo (la C2 de la landing, dentro)
Sonda de contraste efectivo (fondo compuesto subiendo por los ancestros, opacidad incluida), 500 nodos
por pantalla, en los 7 anchos:

| texto | dónde (`site.css`) | ratio | mínimo | pantallas |
|---|---|---|---|---|
| «Volver» (`.bk-back`, 1631) · línea de contexto «Kids · 1 hora · Vie 4 sept · 17:00» (`.bk-context`, 1663) | cian `#1AA9DE` sobre la banda `--bg-soft` `rgb(232,233,229)` | **2,21** | 4,5 | 2 · 2b · 3 · 3b · pack |
| «Volver» / «Volver al carrito» (`.purchase__back`) · «¿Olvidaste tu contraseña?» (`.auth__link`, 1051) · «política de privacidad» (`.form__hint a`, 1194) · «condiciones de reserva» | cian sobre papel | **2,45** | 4,5 | 4 · 5 · 5b · 5c · 8 |
| «2× Kids · 1 hora» · «8 invitados · Pack…» (`.cart__when`, 2036) | cian sobre blanco (`--bg-card`) | **2,70** | 4,5 | 4 · 8 · desenlace 6 |
| «Incluido» (`.cart__addon-incl`, 2261) | `--ok` verde como TEXTO sobre blanco | **2,95** | 4,5 | 4-pack · 8-pack |
| «Debes aceptar esta condición…» (`.form__error`, 853) | `--err` como texto sobre papel | 4,10 | 4,5 | 8b |

- **Por qué**: es exactamente C2 de la landing (`auditoria-diseno.md` §3), que la tanda D resolvió con
  `--interactive` (Azul Muro en papel, **6,43**) **dejando ocho reglas del cajón como excepción** en
  `InteractionColourIsNotAZoneTest`. Aquí se ve lo que esa excepción cuesta: 12 textos, cinco de ellos
  en **todas** las pantallas del embudo. Su propio sistema lo escribe: «Cian sobre Papel 2,45 · ✕ NUNCA»
  (`design-playjump.md` §2.5). Y **«Incluido» es la C3 de la landing otra vez**, esta vez como texto:
  `#434` creó `--on-ok` para el texto SOBRE el relleno; para el verde COMO texto no hay token oscuro
  (`--ok-dark`/`--err-dark`: no existen, medido).
- **Fix**: los ocho de la excepción a `--interactive` (y la banda `--bg-soft` mide 6,0 con Azul Muro);
  el «Incluido» y el error a un par oscuro declarado por quien declara el color (el patrón de `#434`).
  **Guarda**: vaciar la lista del cajón de `InteractionColourIsNotAZoneTest` — solo encoge, y hoy es
  cuando encoge.

### C3 · Objetivos táctiles: la × del diálogo mide 15 × 26 y la mitad de los controles no llega a 44
`#264` puso 44 px en la web entera (**37 → 1**); al cajón le llegaron **cuatro** controles (el «Lote 9»,
`site.css:1097`) y el resto nunca. Medido (caja pintada; «pseudo» = hay ampliación invisible que sí
cuenta):

| control | `site.css` | mide | ampliación | anchos |
|---|---|---|---|---|
| **`.sidecart__close` «×»** (cierra el diálogo) | 1345 | **15 × 26** | ninguna | los 7 |
| `.pwd-input__toggle` (ver contraseña) | 868 | 32 × 32 | ninguna | los 7 |
| `.addons__moreinfo` «Más info» | 2250 | 47 × **20** | ninguna | los 7 |
| `.auth__link` «¿Olvidaste tu contraseña?» | 1051 | 151 × **17** | ninguna | los 7 |
| `.zone-tab` «ENTRAR» / «CREAR CUENTA» | `landing.css:1295` | 168 × **37** | ninguna | los 7 |
| `.auth__submit`, `.purchase__add-more` | 1123 · 2042 | × **42** | ninguna | los 7 |
| `.acct__btn` (bloque de cuenta) · «Mi QR» · cerrar sesión | 4551 | × **41** · 73 × **28** · 41 × 41 | ninguna | los 7 |
| `.cal__day` (celdas del calendario) | 1942 | **35 × 35** a 320 · 43 a 375 | ninguna | ≤ 375 |
| `.entry__qty` (la cantidad, que «se escribe», `#327`) | 1495 | 34 × **18** | ninguna | los 7 |
| `.check` (las casillas y su texto) | 1197 | × 21 | ninguna | los 7 |
| `.bk-back`, `.entry__stepper button`, `.cart__remove`, `.bk-foot__info-btn` | — | 17–32 | **sí** (Lote 9 / pseudo) | ✓ |

- **Fix**: `min-height: var(--tap-min)` en `.zone-tab`, `.auth__submit`, `.purchase__add-more`,
  `.acct__btn`, `.check`; `[data-tap]` (el mecanismo de `#264`, solo puntero grueso) en la ×, el ojo,
  «Más info», el enlace de recuperar, «Mi QR»; `.cal__day` con `min-height: 44` bajo 400 px (la rejilla
  de 7 cabe: 7 × 44 + 6 × 3 = 326 < 358 útiles a 390, y a 320 se apila el precio). `TouchTargetTest`
  **hoy no mira el cajón**: ampliarlo.

## 4. Hallazgos — MAYORES (10)

### M1 · Media hoja vacía en el teléfono: el panel mide la pantalla entera pase lo que pase
- **Medido** (píxeles vacíos entre el último trazo de tinta del carril y el pie, a 390 × 844): día
  **362** · hora **415** · cantidad 191 · carrito **428** · entrar 169 · pagar **438** · saliendo **591**
  · carrito de pack 270. A 414: 430 → 506. Y en escritorio (panel de 440 × 800): 334 → 547.
- **Dónde**: `.sidecart__panel { position: absolute; top: 0; height: 100%; width: min(440px, 100%) }`
  (`site.css:1341`) — una hoja lateral de escritorio que en móvil ocupa todo el alto con cuatro chips
  y un botón dentro.
- **Por qué**: es la queja abierta del owner en `cajon-en-movil.md` §7.4 («366 px vacíos en fecha y
  452 en hora»), y **la pasada de móvil de su propio diseñador** (`Landing PJP Modos`, 28-08) resuelve
  el panel de reserva como **hoja inferior con asa** dimensionada al contenido. El vacío no es aire de
  diseño: es la caja.
- **Fix** (es D-C2, §7): (a) hoja inferior en < 900 px, alta lo que el paso necesite, con asa y el pie
  pegado; o (b) el panel sigue a pantalla completa pero cada paso **usa** el sitio (el calendario del
  paso 2 abierto de serie donde cabe; la tira de horas con las 11 a la vez, la objeción medida de
  `#239` §4.2). Se decide viendo las dos renderizadas.

### M2 · El paso 5 tiene DOS títulos y una etiqueta, y la jerarquía va del revés
- **Dónde**: `IdentifyStep.vue:87` (`h3` «Identifícate», 20 px Bungee) + `LoginForm.vue:74-77` /
  `RegisterForm.vue:136-140` (`.auth__head`: `.eyebrow` «BIENVENIDO DE NUEVO» / «ÚNETE» + `h2` «Inicia
  sesión» / «Crea tu cuenta», 28 px Bungee + subtítulo); `site.css:1109-1112`.
- **Medido**: `headings = [h3, h2]` en 5 · 5b · 5c a todos los anchos; `eyebrows = 1`. Dos titulares
  Bungee en 200 px de alto, el segundo mayor y **de nivel superior** al primero.
- **Por qué**: es el marcado del modal de la web de antes de `#122`, embebido tal cual; y la etiqueta es
  la que `#303` retiró de toda la web pública (gate 54: eyebrow OFF por defecto).
- **Fix**: **un** título por pantalla (el de la pestaña: «Inicia sesión» / «Crea tu cuenta»), sin
  etiqueta; la pestaña ya dice dónde estás. `SectionHeadlineTest` **no mira el cajón**: ampliarlo.

### M3 · Pulsables a dos líneas (G49) en 320 y 375
- **Medido con `Range` sobre los nodos de texto** (cada chip de día es tres filas POR DISEÑO y se mide
  fila a fila): «**CREAR CUENTA**» parte a 320 y 375 (`.zone-tab`, mayúsculas + `.14em`, la m6 de la
  landing otra vez); «**Añadir al carrito**» y «**Pagar con tarjeta**» parten a 320 en el pack (el total
  «144,60 €» en Bungee 22 px `nowrap` (`.bk-foot__v`, 1673) le quita el sitio al CTA: el pie pasa de
  119 a **139 px**). ⚠️ Las casillas («Mantener la sesión iniciada», «He leído y acepto…») parten a
  ≤ 390 y **no cuentan**: son frases, no rótulos.
- **Fix**: `white-space: nowrap` en `.bk-cta` y que el total encoja (`clamp`) antes que el botón;
  `.zone-tab` sin tracking bajo 400 (o «Entrar» / «Crear cuenta» en caja baja, que es lo que dice
  `design.md` §9: el rótulo en cuerpo, nunca Bungee ni mayúsculas).

### M4 · El bloque de cuenta manda antes que la compra — y su naranja es el único relleno de acción del embudo
- **Medido**: paso 1, lo primero del cajón es «HOLA, SALTADOR/A · **Iniciar sesión** (naranja) · Ver mis
  reservas» y debajo el catálogo; pasos 6, 9 y 10: «HOLA, CLIENTE · **Ver mis reservas** (naranja) · Mi
  cuenta · salir» **encima** de «Te llevamos a la pasarela» y de «Reintentar el pago». En las 15
  pantallas el único elemento en `--action` es del bloque de cuenta (`actionFills = 1` en 1 · 6 · 9 ·
  10; `0` en el resto).
- **Por qué**: es el tell que el guion midió en la landing (§1.2: *lo primero que pide es «Registrarse»
  antes de haber dicho para qué*), dentro del embudo. La identidad se pide en el paso 5, **donde
  muerde** (`design.md` §1); pedirla en el paso 0 con el color de acción compite con comprar.
- **Fix**: el bloque **plegado** en el catálogo y en los desenlaces como ya lo está en los pasos 2–4
  (`.sidecart__panel.is-booking .acct`, 1556), o desplegado pero en tinta/fantasma; el naranja, para el
  paso del embudo. Es D-C3.

### M5 · El hover del CTA hace TRES cosas y cambia de rol (G13)
- **Dónde**: `.bk-cta:hover` y `.cartbar:hover` (`site.css:1701`, 1709): `translateY(-2px)` + cambio de
  fondo (cian → tinta · naranja → `--action-hover`) + `--shadow-lift`; y `.bk-cta:hover svg`
  `translateX(4px)`. Son las cinco excepciones de `HoverDoesNotJumpTest` (`#435`).
- **Fix**: la física de `.btn` (`#321`): responde con color, no salta; pisada `:active`. Vaciar la lista
  del cajón en la guarda.

### M6 · El paso 9 promete un botón que no existe
- **Dónde**: `RedirectStep.vue:58-63`: «Te llevamos a la pasarela de pago segura. **Si no se redirige en
  unos segundos, pulsa el botón**» — y el botón vive en `<noscript>`. Con JavaScript (que es como se ha
  llegado ahí) **no hay botón**: la pantalla es una frase y 591 px vacíos (captura
  `cajon-9-saliendo@390.png`).
- **Fix**: un botón de verdad que aparece a los N segundos (el formulario ya está en el DOM), o quitar
  la frase. Y el bloque de cuenta apagado ahí (M4).

### M7 · La cantidad es un número suelto de 18 px que «se escribe» y no lo parece
- **Dónde**: `.entry__qty` (`site.css:1495`): `min-width: 3ch`, sin borde ni fondo, 34 × 18 entre dos
  botones de 32. `#327` decidió que **la cantidad se escribe** (una excursión de 30 no se pulsa 30
  veces), y nada en la pantalla lo enseña: no parece un campo. G39: altura ≠ botón, sin afordancia.
- **Fix**: caja de campo (borde 1 px, 44 de alto, `tabular-nums`), como los demás campos del cajón.

### M8 · El pie del paso 2 es un botón muerto y un total tachado
- **Medido**: pasos 2 y 3-sin-hora, pie de **119 px** con «Total —» (el guion largo en Bungee sale
  como una **barra negra**) y «Continuar» `disabled` en gris (1704). El propio `foot.js` escribe la regla
  del paso de pagar: *«los campos obligatorios se validan AL PULSAR, con un aviso que dice qué falta, en
  vez de con un botón muerto que no lo explica»* — y en el paso 2 se hace lo contrario.
- **Fix**: sin pie hasta que haya día (como en el catálogo con la cesta vacía, que **no** emite pie), o
  el pie con lo que sí se sabe (el precio del día elegido) y el CTA vivo que dice qué falta.

### M9 · Cuatro tratamientos de «control» en cinco pantallas
- **Medido**: chips de hora `.purchase__chip` (76 × 76, radio 10) · chips de día `.daystrip__day`
  (56 × 76, amarillo `--attn` si es tarifa especial) · pestañas `.zone-tab` (píldora de 37, mayúsculas)
  · botones `.btn--zone` / `.bk-cta` / `.acct__btn` (42 · 50 · 41 de alto) · steppers redondos 50 %.
  Radios usados dentro del panel: `0 · 6 · 10 · 16 · 50% · 999`.
- **Por qué**: es el «doce especies de cosa pulsable» del guion (§1.3) a escala del cajón; y las
  pestañas de auth reutilizan **el selector de ZONA de la landing** (`.zone-tab`) para elegir entre
  entrar y crear cuenta — un componente cuyo nombre y cuyo `active` cian significan «zona».
- **Fix**: con C1: un `.btn` (44/48), un chip (día y hora con la misma caja), y las pestañas de auth
  como pestañas (`role="tablist"`), no como zonas.

### M10 · Los tres bucles de la página siguen corriendo detrás del velo
- **Medido**: con el diálogo abierto, `getAnimations()` con `iterations = ∞` y `running` = **3** en las
  15 pantallas (el latido y el aro del CTA doble, `#205`, y el destello de la atracción destacada) —
  todos **detrás del velo al 50 %**, para nadie. `#435` pausó el spinner del cajón cuando el cajón está
  cerrado; el caso simétrico —pausar la página cuando el cajón está abierto— no existe.
- **Fix**: `body.no-scroll .cta-pair, body.no-scroll .ride-card__viz { animation-play-state: paused }`
  (el dueño de esa clase es `scroll-lock.js`, `#58`). Presupuesto, no gusto.

## 5. Hallazgos — MENORES (7)

- **m1 · «Septiembre De 2026»**: `.cal__month { text-transform: capitalize }` (`site.css:1934`) pone la
  preposición en mayúscula. Fix: capitalizar solo la primera letra (`::first-letter`, o en `dateStore`).
- **m2 · La × de cerrar es un carácter**, `&times;` a 26 px (el `.sidecart__close` de
  `components/layout.blade.php`), no el `close` del set (`#257`): el único glifo del cajón fuera del set
  y sin `currentColor` de trazo. Fix: el icono.
- **m3 · Dos `401` en la consola en cada visita al paso 5** (`GET /me` y `/me/dependents` de un visitante
  anónimo): esperados, pero salen como errores rojos en la consola del owner cada vez que revisa. Fix:
  no pedir `/me/dependents` sin sesión (ya hay señal de que no la hay).
- **m4 · La cabecera de sección del catálogo es un icon-tile**: icono en cuadrado gris + título + pastilla
  con el número (`.catalog-acc__head`, 2121) — pequeño, pero es la forma-plantilla; y `:hover` lo tiñe de
  cian (2124) siendo una cabecera **no plegable** (P6): afordancia sin consumidor (`#295`).
- **m5 · El chip de día especial es amarillo `--attn` y el elegido cian**: dos colores de estado en una
  misma tira (tarifa · elegido) sin leyenda hasta abrir el calendario. Con C1 el elegido pasa a tinta o
  acción y el amarillo se queda solo diciendo «finde».
- **m6 · «Incluido» va dos veces en el pack**: como pastilla verde en el complemento (paso 3) y como texto
  verde en la cesta (paso 4, 2,95). La misma información con dos tratamientos (`TagSystemTest` no mira
  el cajón).
- **m7 · El `<main>` de detrás no lleva `inert`**: la trampa de foco lo suple (medido), pero un lector de
  pantalla sigue viendo la página entera bajo el diálogo. `a11yPanel` ya pone `aria-modal`; `inert` en
  los hermanos del `.sidecart` es una línea.

## 6. Excepciones DECLARADAS — lo que las tandas de la landing aparcaron aquí

| guarda | reglas del cajón en su lista | este informe |
|---|---|---|
| `InteractionColourIsNotAZoneTest` | 8 (`.bk-foot__info-btn:hover`, `.acct__alert:hover`, `.acc-tile:hover …`, …) + `.purchase__chip.is-active` + `.btn--zone` | **C2** las mide: 12 textos bajo AA |
| `HoverDoesNotJumpTest` | 5 (`.bk-cta`, `.cartbar`, `.acct__btn--*`, `.acc-tile`) | **M5** |
| `SemanticFillTextTest` | `.bk-seg__label` a 9,5 px (1660) | sigue bajo el suelo de 10; entra en la tanda A |
| `MotionBudgetTest` | 4 del cajón (la chapa del catálogo, dos iconos) | ninguno corre (medido: 0 bucles propios); la lista puede encoger a 0 |

Las cuatro se escribieron «solo encogen» a la espera de esto. **Encoger es la tanda B (§8).**

## 7. Lo que decides tú — contestado sobre la hoja de decisión (2026-09-03, `#439`)

- **D-C1 · El color de la ACCIÓN del embudo** (C1): `--action` como en la web · seguir en cian · tinta.
  → `[owner, 2026-09-03]`: **ninguna de las tres**, y una pregunta: *«¿cuántos tipos de botones tenemos?
  ¿no tenemos demasiadas variantes?»*. **Medido** (§7.1): sí. La propuesta que sale de la medida no es
  elegir un color para una variante más, sino **retirar la variante**: `.bk-cta`, `.cartbar`, `.btn--zone`
  y `.acct__btn--*` pasan a ser `.btn` / `.btn--ghost`, la única familia (`#321`); el color del embudo
  es entonces el de la web sin decidir nada más, y `btn--zone` —viva en CSS solo «porque el cajón la
  emite»— muere. **Pendiente del owner.**
- **D-C2 · La hoja en el teléfono** (M1) → `[DECIDIDO owner]` **B, hoja inferior con asa**. Y con ella:
  **«el calendario completo como estaba antes, y quitamos el slider»** — vuelve el calendario mensual
  entero como única vía del paso 2 y **se retira la tira de días** (`#239` §4.2 en su mitad de FECHA);
  **«la hora se queda como está»** (la tira de horas sigue; la rejilla C queda descartada).
- **D-C3 · El bloque de cuenta dentro del embudo** (M4) → `[DECIDIDO owner]` **A, plegado** en el
  catálogo y en los desenlaces, como ya lo está en los pasos 2 a 4.
- **D-C4 · El paso 5** (M2) → `[DECIDIDO owner]` **B, «Identifícate» y los campos**: fuera la etiqueta y
  el segundo título.
- **D-C5 · La física del hover** (M5) → `[DECIDIDO owner]` **A, responde con color** (la de `.btn`,
  `#321`): «el resto de opciones acepto tu recomendación».

### 7.1 · Cuántos botones hay, medido (la pregunta de D-C1)

| | declarado en las dos hojas | emitido en la web pública (`<button>`/`<a>`) | emitido en el cajón (Vue) |
|---|---|---|---|
| clases pulsables distintas (`cursor: pointer`, sin estados) | **61** | — | — |
| la familia `.btn` | 1 familia · 4 modificadores (`--ghost` · `--sm` · `--lg` · `--zone`) | 30 de 55 emisiones | 44 de ~70 emisiones (casi todas `btn--zone`) |
| combinaciones de clase distintas en un pulsable | — | **32** (20 fuera de `.btn`: `cta-med`/`cta-ghost` del armazón, `zone-tab`, `bd-tab`, `zone-pick__tab`, `bd-pack__cta`, `reg-cta`, `salta__btn`, `reserve__act`, `faq__q`, `nav__burger`, `lang-dd__trigger`, `slider-arrow`, `bd-proc__arrow`, `offw-*`…) | **28** (14 fuera de `.btn`: `bk-cta`, `cartbar`, `acct__btn` ×3, `bk-back`, `purchase__add-more`, `cal-more`, `pwd-input__toggle`, `addons__moreinfo`, `auth__link`, `cart__remove`, `bk-foot__info-btn`, las flechas de las tiras) |

El objetivo del guion (§2) es **≤ 5 especies además de `.btn`**. El cajón añade 14 por su cuenta, y
tres de ellas son botones primarios con su propio color (`bk-cta` cian, `cartbar` naranja,
`acct__btn--primary` naranja). ▶ **Ninguna de las tres opciones de D-C1 toca el botón flotante del
armazón**: las tres cambian dos selectores dentro de `.sidecart__panel`; `.cta-med` (tinta, `#217`) y
la barra de móvil (`--action` desde `#209`) siguen como están.

## 8. Plan propuesto

| tanda | qué | decisión | tamaño |
|---|---|---|---|
| **A** | **Lo roto**: C2 (contraste, `--interactive` + par oscuro para verde/rojo como texto) · C3 (44 px) · M3 (dos líneas) · M6 (el botón fantasma) · M7 (la cantidad como campo) · m1 · m2 · m3 · m7 | ninguna | pequeña, medible antes/después |
| **B** | **El rol de acción**: C1 + M4 + M5 + M9 + m5 · vaciar las cuatro listas de excepción · `TouchTargetTest`, `SectionHeadlineTest` y `TagSystemTest` miran el cajón | D-C1 (pendiente: «ninguna variante nueva») · D-C3 = A · D-C5 = A | media |
| **C** | **La hoja y el paso 5**: M1 · M2 · M8 · M10 · **el calendario entero sin la tira** (la mitad de fecha de `#239` revertida) | D-C2 = B · D-C4 = B | media, con renders |

## 9. Método, instrumento y trampas pagadas

- **La sonda recorre el embudo de verdad** (esperas por CONDICIÓN, cookie de consentimiento,
  `reducedMotion: no-preference`) y mide dentro de `.sidecart__panel`; capturas a 320 · 390 · 1280 por
  paso; foco tabulando (`keyboard.press('Tab')`, 25–30 veces) desde el «×»; el foco de cada campo
  medido antes/después (`border-width`, alto, `outline`); los desenlaces por el pase real de
  `RedsysReturnController` (token de un solo uso acuñado con `handoff()->put()` para el pedido pagado
  `R-SRROEZ` y para un pendiente creado por la propia sonda). Control: `caughtTwoLine · caughtContrast`
  en las tres ejecuciones.
- **Cinco trampas, todas con cifras creíbles**:
  1. **Un `.addons__moreinfo` de la LANDING, detrás del cajón**: el localizador sin acotar hizo scroll a
     la página para pulsar un botón que el pie del cajón tapaba, y el pack no pasó de ahí. *Dentro de
     un diálogo, todo se localiza desde el diálogo.*
  2. **Abortar la ida a Redsys pinta la página de error de Chromium** y el panel desaparece
     (`noPanel`): se contesta un **204** a la navegación y el documento se queda.
  3. **El ratón se queda donde hizo clic y pinta el HOVER**: «Ir a pagar» salió TINTA en el carrito y
     «Pagar con tarjeta» tinta en el pack — era `.bk-cta:hover`, no una variante. Se comprobó en la hoja
     antes de escribirlo como hallazgo.
  4. **Un chip de día son tres filas por diseño** (semana · número · precio) y el detector de dos líneas
     contó 179: se mide **cada hijo de bloque por separado**, nunca el envoltorio (la trampa de
     `guion-de-la-portada.md` §6.8·2, otra vez).
  5. **El pase `?redsys=` se consume al aplicarse**: un token por ancho, acuñado justo antes (TTL 5 min).
- **Lo que quedó fuera**: el pack a 414 (un `timeout` al reabrir `/entradas` con sesión; los otros seis
  anchos lo tienen); el paso 11.

## 11. Ejecución — tanda A, «lo roto» (2026-09-03, `#450`)

Sin decisión que tomar (§8): C2 · C3 · M3 · M6 · M7 · M10 · m1 · m2 · m7, y el suelo de 10 px del cajón.
**Medido antes y después con la misma sonda** (`audit-cajon-hallmark.mjs`, 320 · 390 · 1280):

| hallazgo | antes | después |
|---|---|---|
| C2 · textos bajo AA dentro del panel | **12** (2,21 · 2,45 · 2,70 · 2,95 · 4,10), en todas las pantallas | **0** en las 43 mediciones: «Volver» y el contexto en `--interactive` sobre la banda **5,8**; los enlaces 6,4; «Incluido» en `--ok-text` (Verde Salta oscuro, 4,8 sobre papel); el error en `--err-text` (4,7) |
| C3 · controles < 44 sin ampliación | la ×, el ojo, «Más info», el enlace de recuperar, pestañas, botones (41–42), «Mi QR», casillas, la cantidad, las celdas del calendario a ≤ 375, «Leer el texto completo» | **solo lo que WCAG exime**: dos enlaces EN LÍNEA dentro de una frase (condiciones, privacidad), las flechas de la tira que solo existen con ratón, y ⚠️ **las celdas del calendario a 320: 40 × 44** — con 280 px útiles, siete columnas no dan 44 de ancho (a 375 miden 45) |
| M3 · pulsables a dos líneas | «Crear cuenta» a 320/375 · «Añadir al carrito» y «Pagar con tarjeta» a 320 (pack) | **0** (quedan las dos casillas de frase, que no cuentan) |
| M6 · el botón del paso 9 | solo en `<noscript>` | **existe**: aparece a los 2,5 s (`v-show`), el manifiesto congelado cambia en UNA clave y se dice aquí |
| M7 · la cantidad | 34 × 18 sin caja | campo de 60 × 44 con borde, `tabular-nums`, 16 px |
| M10 · bucles de la página tras el velo | 3 | **0** |
| m1 · «Septiembre De 2026» | `capitalize` | solo la primera letra (`::first-letter`) |
| m2 · la × | `&times;` a 26 px | el `close` del set |
| m7 · `inert` | no | el diálogo marca `inert` a todo lo que no es él (`a11yPanel.isolate()`) |
| suelo de 10 px | `.bk-seg__label` 9,5 · «casi llena» 9 | 10 y 10; la lista de excepción de `SemanticFillTextTest` queda **vacía** |

**Lo que cambia en el sistema**: nacen **`--ok-text` · `--err-text` · `--warn-text`** (el color semántico
COMO TEXTO sobre papel; el mismo trato que `--on-*`: el par lo declara quien declara el color — el
producto vale con los suyos, ya oscuros; el paquete de PlayJump declara `#447921` y `#C83912`, la columna
«oscuro (texto en papel)» de su tabla §2.1). ⚠️ **Paso de despliegue**: dos líneas en el `client.css` de
producción (`INSTALACION-CLIENTE.md` §4.a.ter). Se retiran del cajón **26 lecturas** de `--zone-*`/`--ok`/
`--err`/`--warn` como texto; la cabecera de sección del catálogo deja de teñirse al pasar (m4: no se pliega).

**Guarda nueva, `DrawerControlsTest`, vista MORDER seis veces con el defecto real** (mutación en la hoja y
restauración desde copia, control en verde): «Volver» en `--zone-1` · «Incluido» en `--ok` · la × sin 44 ·
el CTA sin `nowrap` · la pausa sin `animation-play-state` · el mes sin `::first-letter`. Las listas de
excepción del cajón en `InteractionColourIsNotAZoneTest` (8) y `SemanticFillTextTest` (1) quedan **vacías**.

**Tres cosas que enseñó, con cifras**:
1. **La guarda vio más que la auditoría**: al escribirla cazó **nueve reglas más** con `--ok`/`--warn`/`--err`
   como texto («casi llena», la nota de la señal, «gratis», «requiere…», el error del evento…) en estados
   que el recorrido no capturó. *Un recorrido mide los estados por los que pasa; una guarda estática lee
   todos.*
2. **`aspect-ratio: 1 / 1` + `min-height: 44` = desbordamiento**: la celda cuadrada tomó 44 de ancho en una
   pista de 40 y la rejilla de siete se salió **35 px** a 320, medido en la primera pasada. El ancho lo pone
   la pista y el alto el mínimo; la celda deja de ser cuadrada por debajo de 375.
3. **`animation-play-state` no llega a un `::after`**: la primera pausa dejó corriendo **2 de 3** bucles (el
   aro y el destello viven en pseudo-elementos). Se escriben los pseudo-elementos en la regla.

**Lo que NO se toca aquí, a propósito**: los colores de ACCIÓN y de lo elegido (cian) y el bloque de cuenta
en naranja (tanda B, D-C1 y D-C3); la hoja, el calendario y el paso 5 (tanda C). En «saliendo a la pasarela»
hay ahora **dos** rellenos de acción (el botón nuevo y «Ver mis reservas»): la B pliega el bloque.

## 12. Ejecución — tanda B, «el rol de acción» (2026-09-03, `#451`)

`[DECIDIDO owner]` D-C1 = **ninguna variante nueva** (§7) · D-C3 = A · D-C5 = A. Lo hecho:

- **`btn--zone` MUERE.** Las 28 apariciones en 21 componentes del cajón pasan a `.btn` (y `.btn--ghost`
  donde eran secundarias: los canales no principales del aviso de pausa, la ficha de invitados no
  pendiente); las cuatro reglas de `landing.css` se retiran. `SingleButtonFamilyTest` gana el caso de los
  `.vue` (sin sus comentarios) y vigila las hojas también para `.btn--zone`.
- **`.bk-cta`, `.cartbar` y `.acct__btn` SON `.btn`**: el relleno, el texto, el hover (color, no salto) y
  la pisada los pone la familia; las tres clases quedan como CAJA (ancho, talla, reparto interior, el gris
  del deshabilitado — en opacidad plena, porque al 55 % de `.btn:disabled` el rótulo caía a 2,08, medido).
  La pastilla del contador del carrito pasa a `--on-action` al 14 % sobre el relleno de acción.
- **Lo ELEGIDO es un estado y va en tinta**: la hora activa, el día elegido (tira y calendario), la barra
  de progreso y la pestaña activa de entrar/crear cuenta. `InteractionColourIsNotAZoneTest` pierde las dos
  entradas del cajón que quedaban en su lista de identidad; `HoverDoesNotJumpTest` vacía la suya (5 → 0;
  la tarjeta del área responde con borde y sombra). `ActionFillTest` deja de enumerar `.cartbar` y
  `.acct__btn--primary`: el rol lo pinta la familia.
- **D-C3: el bloque de cuenta va plegado también en `catalog` y `result`.** Con `is-account` ya
  colapsado desde `#66`, **no le queda ningún modo visible**: ficha en `DEUDA.md` para retirarlo entero
  (hace de puente del contexto y del repintado tras el login, así que no se arranca en una tanda de CSS).
  Lo único útil que daba en los desenlaces vuelve donde toca: **«Ver mis reservas» como fantasma en el
  paso 6** (`#225` F3 lo había retirado porque el bloque lo ofrecía siempre).

**El manifiesto congelado cambia en 15 claves** (identificarse, crear cuenta, la banda del pago, el paso
9, los tres de reserva creada, los dos de pago denegado, verificando, la pausa y los cuatro estados del
pie): en todas es la clase de un botón (`btn--zone` → `btn`, `bk-cta` → `btn bk-cta`, `cartbar` → `btn
cartbar`) y, en las tres de reserva creada, el enlace nuevo. Regenerado con `MANIFEST_REFRESH=1` tras
comprobar que los otros 23 casos pasaban intactos.

**Medido después** (`audit-cajon-hallmark.mjs`, 390 · 1280): **un relleno de acción por pantalla**, el
de `.btn` (cantidad, entrar, pagar, saliendo; **cero en el catálogo**, donde antes lo ponía el bloque de
cuenta); contraste **0**; bucles **0**; el CTA en naranja y lo elegido en tinta, capturado.
⚠️ Trampa de la sonda, otra vez la de §9·3: el carrito y el pago del pack dan «0 rellenos» porque el
ratón se queda sobre el CTA que acaba de pulsar y pinta `--action-hover`.

**Lo que asomó en las capturas y no estaba en el informe** (m8, para la tanda C): en el catálogo a 390 el
nombre «Pack Cumpleaños KIDS» parte en tres líneas y el precio lee «14,95 €por niño» sin espacio — la
columna del precio no deja sitio al nombre y el sufijo va en un flex sin hueco.

## 10. Verificación de este informe

- 96 mediciones del embudo + 4 de desenlaces; 49 capturas; tres controles en verde.
- Cada cifra de §3–§5 tiene fichero:línea o pantalla; la agregación por hallazgo es
  `resumen-cajon.py` sobre el JSON, no lectura de capturas.
- **No se tocó ni una línea de producto.** El único cambio de entorno es `sidebar.engine = spa` en la BD
  local, dejado puesto.
