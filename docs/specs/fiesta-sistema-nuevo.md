# [SPEC] La fiesta del sistema nuevo — vestir la lista de invitados, la invitación y la autorización

> Estado: ✅ **APROBADA por el owner el 2026-09-25 con sus ocho respuestas (`#743`)** · en ejecución: T0 ✅, sigue
> la T1 · Última actualización: 2026-09-25 · Decisiones: `#765` (el traspaso) · **`#743`** (la aprobación y las ocho
> respuestas de §7).
> Carril: 🧩 **SPA** (banda 730–759). Fuente del diseño: `instancias/playjump/diseno/playjump-design-system/`
> (el zip de Claude Design, `#760`): `paginas/lista-invitados.card.html`, `paginas/invitacion.card.html`,
> `paginas/autorizacion.card.html`, `components/invitados/*`, `components/forms/SaveBar.jsx`, el `readme.md`
> 752→816 y los tres briefs de `uploads/`. Hermanas: `celebracion-e-invitacion.md` (la lógica, §3.1 y §4.5),
> `waiver-por-reserva.md` (la autorización), `isla-y-landing-nueva.md` §4.8, §4.9 y §4.11 (el método y el traspaso).

## §0 · Antes de tocar

- **Regla que ordena todo (`#765`)**: el método de la isla —la referencia byte a byte del zip, el banco A/B con
  `scripts/pixel.mjs` a **0 píxeles** con control de 1 px, las piezas portadas— y **primero lo que HAY**; lo que
  FALTA (§1.4), **una pieza cada vez y con la decisión del owner delante** (§7). ⚠️ `#768`: el banco NO va por
  tanda: UNA pasada ligera por página al final; `#767`: manda solo el mockup. El zip entra SOLO por plataforma:
  `git pull` de la instancia antes de cada tanda y `cd diseno && sha256sum -c --quiet playjump-design-system.sha256`.
- **Dónde viven (T0, §3.1)**: en el **PRODUCTO** —vistas, piezas, JS y la hoja de estructura con **roles
  `--fiesta-*` neutros**—; los valores de PlayJump, en `publico/instancia/css/fiesta.css` de su instancia (desde T1a),
  tras `saltia.css`, por el **contrato de hojas** de `instancia.json` (§3.3, de plataforma: se pide, no se toca).
- **Empieza por** §1.4 (el censo HAY/FALTA contra el código) → §4.1 (el modelo de página) → §4.4 (el banco).
- **Trampas antes de tocar**: la lógica NO se toca (`celebracion-e-invitacion.md` §0: `#700`, `#706`, `#718`,
  §7.2·R1 hoja en blanco) · `#739` sin banner, driver ni píxeles · el molde `gf-*` de `site.css` es de las ENCUESTAS
  desde T4 · `hoja-del-cajon.py` lee «HOJA ENFOCADA» de `site.css`: tocarlo obliga a regenerar
  `cajon.css` · React pone `px` a los números y Blade no; un salto de línea entre texto y elemento es un espacio
  que JSX no deja · el juez: `--reloj`, `--rehacer`, `--reintentos 2` y sus ocho trampas en `scripts/pixel.mjs`.
- **Invariantes y `CRITICAL_RE`**: vestir no toca dinero ni aforo. ⚠️ La zona 3 del diseño **sube el número desde
  la lista** y admite más niños que plazas: `GuestCountAdjuster` (`CRITICAL_RE`), aforo y cobro en el parque →
  `INVARIANTES` §1–§2, `VERIFY_CONC=1`, solo con el sí del owner (la pregunta 3, abajo). `RGPD-*` en la firma y el recibo.

## 1. Contexto y problema — MEDIDO (2026-09-25)

### 1.1 Lo que hay hoy

| Pieza | Medida |
|---|---|
| `resources/views/reservation/guests.blade.php` | **1.279 líneas / 92 KB**, con un `<script type="module">` en línea de **350 líneas** (928→1278) y `public/js/guest-form/logic.js` (el pegado, con `resources/js/guest-form/logic.test.js`). Consume **57 variables** del controlador. |
| `resources/views/invitation/show.blade.php` · `receipt.blade.php` | 338 y 145 líneas; 18 y 13 variables. |
| `resources/views/reservation/authorization.blade.php` | 463 líneas, 20 variables; Turnstile como `<script>` externo. |
| `components/focused-layout.blade.php` | 61 líneas: carga `landing.css` + el tema (`ThemeSettings`) + `site.css` + `client.css` si existe. Lo usan las tres páginas **y las de las encuestas** (`survey/*`). |
| Controladores | `GuestFormController` 673 líneas (`show` compone 30 claves) · `InvitationPageController` 390 · `GuardianAuthorizationController` 444. |
| CSS viejo en `site.css` | el bloque «Formulario post-reserva — HOJA ENFOCADA» (líneas 5454→5529, **196 reglas `.gf-*`**) y **24 `.guardian__*`**. |
| Guardas de piel | `GuestFormSkinTest` (4) y `GuardianSkinTest` (6): cero `--zone-1`, tallas y radios en escala, una sombra, política ≥ 48, cinco desenlaces con cuatro tonos, anti-bot con rótulo. Afirman la piel VIEJA. |
| Fronteras | `ModuleBoundariesTest|ModuleContractsTest|ApiBoundariesTest`: **47 en verde** antes de tocar. `module-deps.php` no censa controladores (escanea `Support`, `Models` y `Domain`): las dependencias de la fiesta son las que ya lista `celebracion-e-invitacion.md` §1.3. |

### 1.2 Lo que trae el diseño

| Pieza | Medida |
|---|---|
| La lista | `lista-invitados.card.html` (187 líneas: el `<style>` de la página, 88 reglas `.pli-*`) + `datos.js` (275: los textos del brief `T`, los propios `P`, `RESERVA`, `EXTRAS`, **cuatro `ESTADOS`** y las `LLEGADAS`) + `estado.jsx` (199: `usePliLista`, el borrador, las cuentas, fusionar una respuesta, guardar) + `zonas-1-2.jsx` (265) + `zonas-3-5.jsx` (290). |
| La invitación | `invitacion.card.html` (147) + `datos.js` (159) + `vistas.jsx` (237) + `invitacion.css` (63, compartida con la autorización). Seis estados. |
| La autorización | `autorizacion.card.html` (143) + `datos.js` (40). Tres estados. |
| Los componentes | `components/invitados/`: `InviteCard` (197), `GuestRow` (123), `AuthForm` (69), `GuestComposer` (63), `RsvpBar` (60), `AddonCard` (53), `ThemePicker` (45), `PlacesMeter` (34); `forms/SaveBar` (40). **Estilos en línea con `var()`**, como la isla. |
| Tokens | **77 distintos**: **20 primitivos** de la paleta de PlayJump (`--ink-050…900`, `--ink-surface`, `--snow`, `--aqua-100/400/500/600/700`, `--sun-100/500/600`, `--volt-500`), **51 roles** de Saltia (45 ya con respaldo neutro en `resources/js/isla/isla.css`; faltan `--bg-muted`, `--gutter`, `--shadow-island-float`, `--surface-glass-ink-float`, `--success-500`, `--warn-500`), **3 locales** del tema de la invitación (`--inv-accent`, `--inv-tint`, `--invite-radius`). Colores a mano: solo `#000`, `#fff` y `rgba(255,255,255,.14)`. |
| Fuentes | `--font-display` (Archivo), `--font-ui` (Figtree), `--font-mono` (DM Mono): las nueve caras de `publico/instancia/fuentes/`, servidas por `instancia/css/fuentes.css`. |

### 1.3 El método, ya pagado

`scripts/pixel.mjs` (324 líneas, ocho trampas escritas) y dos moldes de banco: `scripts/banco-entradas.php`
(JSX con Babel contra Blade, con el `<style>` de la página del diseño copiado tal cual, `clics` y `pasar` por
pieza, `lote.json` a 390 y 1280) y `scripts/banco-isla.php` (contra Vue). Chromium 1148, `playwright-core`
1.49.0 y `socat` viven en el contenedor. `public/instancia/` es el sitio del material nuevo de una instalación
(ignorado por git, excluido del `rsync`).

### 1.4 El censo HAY / FALTA, contra el CÓDIGO (no contra la spec)

Medido en `GuestFormController::show()` (sus 30 claves), `InvitationPageController` y
`GuardianAuthorizationController`. **HAY** = existe y solo hay que pintarlo · **DATO** = lo decide el panel ·
**FALTA** = lógica nueva, con el owner delante (§7).

**La lista (cinco zonas)**
- Z1 · **HAY**: la invitación (tres temas `confeti/fiesta/sereno`, quien cumple y su edad, «te invita», el teléfono
  por casilla, el enlace, `shareable`, `replies_open`, el plazo escrito, el resumen N/M/K) y «Compartir». **FALTA
  (pantalla)**: «¿Cómo se llama quien cumple?» como PRIMERA pantalla —el mecanismo existe (`updateInvitation`
  escribe `honoree_name`; sin él no se comparte), lo nuevo es la pantalla con la vista previa que se escribe sola—.
  **HAY desde F1a (26-09)**: «unas palabras de la familia» y «pistas para el regalo» (`family_words`, `gift_hints`
  en `party_invitations`, tope 90, `PublicFreeText`; en Personalizar, en la tarjeta y en la API 1.32.0).
  Las cifras como filtro: JS de la página, sin lógica de servidor.
- Z2 · **HAY**: las fichas de `guest_data` con su estado, las respuestas por repasar (`proposals`, con «repetida»),
  la chapa «por la invitación», «No lo apuntes» (`dismissReply`), firmada/falta por niño (`guestRegimes`), el
  pegado de nombres (`logic.js`), la línea de las edades (`ageMix`, `ageSurcharge`). **FALTA**: quien cumple como
  FILA de la lista («Es su cumple»; hoy vive en `event_data` y no cuenta como ficha), la lista **sin filas vacías y
  sin tope** (hoy `sanitizeGuestData()` es posicional y acotada a `quantity`: §7·3), «Al final viene» (una respuesta
  «no» que el anfitrión vuelve a contar), «Quitar» con deshacer, el `GuestComposer` (añadir de uno en uno con
  Intro), **tres campos y ni uno más** (los packs de PlayJump tienen cinco columnas: §7·6, DATO).
- Z3 · **HAY**: el número con su plazo, subir y bajar dentro del suelo (`guestCount`, `#444`), «Solo pagas los niños
  que vengan». **FALTA**: el `PlacesMeter` con más niños que plazas (ámbar), «Seréis N… ¿Es correcto?» que SUBE el
  número desde la lista, «Invitar a más» (§7·3, aforo).
- Z4 · **HAY**: los complementos con su plazo por enganche (`PostFormAddonView`), «fuera de plazo» con motivo, la
  tarta como grupo excluyente. **FALTA**: la tarta por raciones y «la grande», combos y cubos «para N adultos»
  con «¿Cuántos adultos se quedan?» (DATO nuevo del enganche + regla), el aviso de la tarta bajo la cabecera.
- Z5 · **HAY**: un solo Guardar, el testigo («La reserva ha cambiado»), el recordatorio (`writeReminder`). **FALTA**:
  el borrador en el móvil (JS de la página; sin servidor), «Guardado hoy a las 16:05» (el `guest_form_submitted_at`
  o equivalente: se mide en T1).
- Fuera del diseño y del producto: los **campos generales** del post-form (`generalFields`: adultos y observaciones),
  los avisos de edad sin producto (`noProductNotices`), el modo **solo lectura**, el bloque legal (`gfLegal`). Se
  pintan con las piezas del sistema en su zona y **se juzgan sin A** (§4.4).

**La invitación y su recibo**
- **HAY**: los seis estados (viva, plazo pasado, «Contamos con vosotros», «Gracias por avisar», anti-bot, no
  disponible), la barra con el nombre y dos botones, el tema que pinta la página, cuándo y dónde, «Cómo llegar»,
  el `.ics`, el menú por `show_in_invitation`, quién invita y «Llamar», la vista previa (`og:*`), «Su ficha» en el
  recibo (G2), la firma desde el recibo (G3 «Lo dejo y me voy»), el aviso de privacidad, es/en/fr.
- **FALTA** (tras T2): la firma DENTRO del recibo (`AuthForm`, T3; hoy «Firmar» es un enlace a la autorización), «Tus
  respuestas» en el móvil (JS; «Contestar por otro hijo» ya es un enlace), «Crear mi QR» (Mi cuenta), «Avísame de
  fechas» (§7·8), «Ver el parque» (§7·7), los grupos de la merienda con su icono (DATO del enganche; hoy cada
  complemento marcado es un grupo, con icono neutro), la nota de Google en la cabecera. ✅ **En F1a (26-09)**: las
  palabras de la familia y las pistas. ✅ **En T2**: el recibo dura 24 h (§7·4); «¿Vas tú con él?» fuera del
  recibo (§7·5; `companion` se retira con la puerta); el recibo directo, el nombre de pila y la descripción del pack
  (`#744`).

**La autorización**
- **HAY**: la hoja en blanco por reserva, los cinco desenlaces, la firma con el descargo entero, el anti-bot con
  rótulo, la llegada desde el recibo con la respuesta atada (`fromInvitation`, `prefill`), el enlace que no vale
  (404 uniforme), es/en/fr.
- **Medido en T3 (`#745`)**: hoy pedía tres cosas que el brief no (la fecha de nacimiento del menor, la relación con
  el menor, el apellido del adulto aparte) y dejaba opcional el teléfono. La fecha entra en la prueba firmada
  (`WaiverSigner`, `CRITICAL_RE`) y da la edad a la puerta; la relación entra en la prueba; el apellido solo compone un
  nombre completo. Decidido: **se quedan la fecha y la relación**, el adulto va en UNA casilla, el teléfono es
  obligatorio; el descargo se presenta en el flujo (`waiver-probatorio.md` §4.4). ✅ En T3: la tarjeta arriba.
- **FALTA**: la firma DENTRO del recibo (`x-fiesta.firma` ya existe: es enchufarla), el foco al primer campo desde el
  QR de la puerta, el QR de la fiesta en la puerta (lógica nueva, `identidad-qr-puerta.md`).

## 2. Objetivo

### 2.1 Criterios de éxito, medibles

- **Idéntico = 0**: cada estado de cada página (§4.4) contra la ficha del diseño con los mismos datos, a 390 y a
  1280, con `--rehacer --reloj --reintentos 2`, y un **control** por tanda: una mutación de 1 px tumba sus pares y
  el juez sale con 1.
- **Cero paleta de un cliente en el producto**: ninguna vista, pieza ni hoja del producto nombra `--ink-*`,
  `--snow`, `--aqua-*`, `--sun-*`, `--volt-*` ni un hex de PlayJump; con la hoja del producto sola, las tres
  páginas se ven **enteras y neutras** (la misma guarda que la isla).
- **La lógica no cambia**: los tests de dominio de la fiesta (`tests/Feature/Invitation/**`,
  `tests/Feature/Reservation/**`, `MountsAParty`) siguen en verde sin tocar una aserción de dominio; solo se
  re-apuntan o retiran, con su motivo, los de PIEL.
- **Sin JavaScript sigue funcionando**: un `<form>` de verdad, Guardar es su botón de enviar, las fichas abiertas.
- **`#739`**: las tres páginas sin banner, driver ni píxeles (`FocusedPagesAreCookieFreeTest` sigue en verde).
- **Presupuestos**: la entrada nueva de Vite con su techo en test; `SidebarBundleBudgetTest` no se mueve (la fiesta
  no entra en el chunk del cajón).

### 2.2 Fuera de alcance

- Toda la lógica de §1.4 FALTA: entra después, una pieza por tanda y con su decisión (§7).
- Los correos del diseño (quince, `paginas/correos/`): del carril de correos.
- Mi cuenta en la isla (T5 de la spec hermana) y «Crear mi QR» (`identidad-qr-puerta.md`).
- Las páginas de las encuestas (`survey/*`): siguen en `focused-layout` hasta que tengan diseño.
- El panel (la ficha del pedido, la puerta): sin cambio de forma.

## 3. Opciones consideradas

### 3.1 Dónde viven las tres páginas

| | Qué es | Por qué sí / por qué no |
|---|---|---|
| **(a) Vistas de la INSTANCIA** sobre un contrato de datos (`#681`, como `/kids`) | El producto da los hechos; `instancias/playjump/web/` pinta. | ❌ La lista lleva **199 líneas de lógica de estado** en el navegador (borrador, fusionar respuestas, cuentas, suelo, pegar) y 673 en el servidor: acabarían en la instancia, y cada cliente las reharía. «La mecánica viene decidida del sistema» (los tres briefs). Descartada también por plataforma (`#765`). |
| **(b) En el PRODUCTO, con roles neutros** ✅ | Vistas, piezas y JS del producto; la estructura con `--fiesta-*` de valor neutro; PlayJump asigna sus valores en una hoja de su instancia (como `--isla-*`, `isla-y-landing-nueva.md` §4.9 T2a). | ✅ Es la regla de la isla, ya medida: **20 primitivos → roles**, 51 roles de Saltia con respaldo (45 ya existen). Coste: el producto necesita cargar una hoja de la instancia en una vista suya (§3.3). |
| (c) Híbrido: la hoja entera de Saltia en el producto | Copiar `saltia.css` al producto. | ❌ Clava la paleta de un cliente en `main` (`#610`). |

### 3.2 La vista: reestilar lo que hay o portar las piezas

| | Por qué sí / por qué no |
|---|---|
| (a) Cambiar las clases `.gf-*` por las del diseño sobre la vista de 1.279 líneas | ❌ El marcado del diseño es OTRO árbol (zonas, `GuestRow`, `PlacesMeter`, `SaveBar`): a 0 píxeles no se llega retocando; y la lógica en línea de 350 líneas seguiría dentro de la vista. |
| **(b) Un modelo de página + piezas portadas** ✅ | Un presentador compone UN arreglo con la forma de `datos.js` (§4.1); las zonas y las piezas son Blade que solo leen ese arreglo; el JS de la página porta `estado.jsx` a `resources/js/fiesta/` (futuro) con casos de `node --test`. El banco pinta B con el MISMO arreglo, mapeado desde `datos.js`. ✅ Es lo que hizo la isla (`forma.js`, `props.js`, `situacion.js`). |

### 3.3 Cómo carga una vista del producto la hoja de la instancia

| | Por qué sí / por qué no |
|---|---|
| (a) Por convención: si existe `public/instancia/css/fiesta.css`, se carga (como `client.css`) | Cero contrato. ❌ El producto nombra un fichero de la instancia; la instancia no puede añadir una segunda hoja (las fuentes, `saltia.css`) sin `@import` en cadena. |
| **(b) Contrato de HOJAS por superficie en `instancia.json`** ✅ (propuesta a plataforma) | `"hojas": { "fiesta": ["css/fuentes.css", "css/saltia.css", "css/fiesta.css"] }` (futuro), leído por `InstanceViews` (validado bajo `public/instancia/`, vacío sin paquete) y consumido por el layout con la misma prop `hojas` de `x-pagina`. ✅ La instancia nombra sus ficheros, el producto no; sirve para cualquier superficie que venga (Mi cuenta, las encuestas). Es de plataforma (`paquete-de-instancia.md`, contrato 2 → 3). |

### 3.4 El layout

| | Por qué sí / por qué no |
|---|---|
| (a) Reusar `x-pagina` (el limpio de la instancia) | ❌ Lleva `og:*` y JSON-LD siempre (la lista es privada), no tiene `Referrer-Policy` ni el `head` de la invitación, y su mapa de `scripts` es de plataforma. |
| (b) Reescribir `focused-layout` | ❌ Lo usan las encuestas, que perderían su piel. |
| **(c) `x-pagina-enfocada`** ✅ (futuro) | La hoja limpia de una página enfocada: `noindex`, CSRF, favicon, `Referrer-Policy: no-referrer`, SIN `landing.css`/`site.css`/`client.css`, las `hojas` del contrato, la entrada de Vite de la fiesta y un `head` para la invitación. `focused-layout` se retira cuando las encuestas se vistan. |

## 4. Diseño elegido

### 4.1 El modelo de página (`app/Http/Fiesta/`, T1a)

- `ListaDeInvitados` (T1a) compone, desde lo que hoy calcula `GuestFormController::show()`, un arreglo con la
  forma de `datos.js`: `reserva` (código, día, hora, fin, pack, plazas), `cumple` (nombre, edad), `invitacion`
  (tema, invita, teléfono, enlace, compartida, plazo, respuestas abiertas), `cuentas` (confirmados, no pueden,
  sin contestar), `ninos[]` (id, nombre, edad, alergias, origen `mano|invitacion`, respuesta, firmada,
  pendiente, repetida), `numero` (valor, suelo, plazo, cerrado), `extras` (por enganche, con plazo y motivo),
  `generales`, `guardar` (estado, guardado en), `plazos`, `solo_lectura`, `accion` (la URL firmada).
  `InvitacionPagina` e `Autorizacion` (futuro), igual, desde sus controladores.
- Los controladores **no cambian de firma ni de reglas**: componen el modelo y pintan. Lo que hoy calcula la vista
  en Blade (estados de ficha, `firstEmpty`, `pendingIdx`…) baja al presentador, con su test.
- **Contrato del modelo** (`FiestaModeloTest`, T1b): las claves y sus tipos, y que el banco y el controlador
  producen la MISMA forma (el mapeo de `datos.js` y el del controlador pasan por la misma guarda).

### 4.2 Las piezas y las zonas (`resources/views/fiesta/`, `components/fiesta/` y `components/pieza/`, T1a)

- Una pieza Blade por componente del diseño, **1:1 con su JSX** (mismo árbol, mismos estilos en línea, mismas
  clases): `invite-card`, `guest-row`, `guest-composer`, `places-meter`, `rsvp-bar`, `auth-form`, `addon-card`,
  `theme-picker`, `save-bar`. Cada una con su ficha de estados en el banco (como `banco-piezas`).
- La lista, por zonas: `lista/primero`, `lista/zona-1`…`zona-5`, sobre `x-pagina-enfocada`. La invitación (T2):
  `invitacion/{cabecera,invitacion,recibo}`, la misma página con el recibo dentro. La autorización: `autorizacion/pagina`.
- El `<style>` de cada `*.card.html` del diseño (las `.pli-*`, `invitacion.css`) pasa **tal cual** a la hoja del
  producto, con los primitivos sustituidos por roles (§4.3). La regla de la isla: lo que JSX escribe en línea se
  escribe en línea; lo del `<style>` va a la hoja.
- Los textos: `lang/{es,en,fr}/fiesta.php` (T1a: `pieza`, `fila`, `anadir`, `complemento`, `barra`, `invitacion`,
  `lista`) lleva las claves de `T` y `P` del diseño (`P` es texto aprobado por el owner el 24-09; en/fr propuestos y
  **a revisar por el owner**). `guestform.php`, `invitation.php` y `guardian.php` siguen para lo que la piel vieja
  aún pinta y lo que el diseño sustituye se retira en T4 con su guarda de claves (mismas claves en los tres idiomas).

### 4.3 La hoja: roles `--fiesta-*` y el contrato de hojas

- `resources/js/fiesta/fiesta.css` (T1a), en `:where()`: los **20 primitivos → roles por función**
  (`--fiesta-tinta`, `--fiesta-tinta-suave`, `--fiesta-sobre`, `--fiesta-acento`, `--fiesta-acento-suave`,
  `--fiesta-vivo`, `--fiesta-sol`, `--fiesta-borde`…; el nombre exacto sale de leer dónde se usa cada primitivo,
  en T1), los seis roles de Saltia sin respaldo, y los tres locales del tema con su valor por tema
  (`--inv-accent`, `--inv-tint`, `--invite-radius` los pone la tarjeta por `data-tema`). Los movimientos del
  diseño (`pj-*`) se llaman `fiesta-*` con la misma definición. Lo que ya respalda `isla.css` **no se duplica**: la
  entrada de la fiesta importa `isla.css` primero (son respaldos en `:where()`, pesan cero; decidido en T1a) y
  añade solo los 24 roles que ésta no cubre. **Los respaldos son NEUTROS de verdad**: `PaletaNeutraTest` rechaza los
  nombres de los primitivos y sus valores (leídos de la hoja de la instancia si la máquina la tiene).
- `publico/instancia/css/fiesta.css` de la instancia (T1a, en su repo): `--fiesta-tinta-900: var(--ink-900)` etc., **cargada
  después de `saltia.css`**, como `isla.css`. Se empuja al repo de la instancia, nunca a `main`.
- El contrato de hojas (§3.3·b) es de plataforma y está HECHO (`#769`, 25-09): `InstanceViews::hojas('fiesta')` da
  las rutas validadas y `GuestFormController::show()` las pasa a `x-pagina-enfocada` (prop `hojas`); sin paquete o
  sin la clave, vacío y la página sale neutra. El banco carga las tres hojas a mano en B (como `banco-entradas`).

### 4.4 El banco: `scripts/banco-fiesta.php` (T1a las piezas · T1b los estados de página)

- Por página y **estado**, dos HTML en el mismo marco: **A** monta la página del diseño SIN su barra de prueba
  (`PliPagina` con el `id` del estado; en la invitación y la autorización, sus vistas con su estado), con
  `styles.css`, React, Babel, `_ds_bundle.js` y `datos.js`; **B** es nuestra Blade con el modelo mapeado desde
  el MISMO `datos.js` (`scripts/banco-fiesta/modelos.php`: los tres modelos en un fichero, que `FiestaModeloTest`
  iguala en FORMA a lo que dan los tres controladores) y las tres hojas de la instancia. `lote.json` a 390 y 1280.
- Estados: la lista, sus cuatro (`recien`, `respuestas`, `guardado`, `fuera`) más «llega una respuesta» y «la
  reserva ha cambiado» (`clics`); la invitación, seis; la autorización, tres; y las fichas de las nueve piezas.
  ⚠️ **`#768` (25-09, owner)**: esos estados NO se juzgan por tanda («tardamos más en verificar que en trabajar»):
  al cerrar cada página, UNA pasada ligera (la página en reposo, 390 y 1280); los bancos por pieza y estado quedan
  para diagnosticar lo que esa pasada señale. La T1a ya venía con el suyo (46 pares, antes de `#768`).
- Lo que el diseño no dibuja (§1.4, «fuera del diseño») se pinta sin A y lo juzga la sonda de ventana
  (`scripts/sonda-fiesta.mjs`, futuro) y el ojo del owner.
- **Control**: `scripts/mutar-fiesta.sh` (T1a) mueve 1 px en la hoja del producto y exige que el juez salga
  con 1 en los pares de esa pieza, y 0 al restaurar.

### 4.5 El JS de la página (`resources/js/fiesta/lista.js` + `logica.js`, T1a)

- Porta `estado.jsx` a JavaScript plano sin React: el borrador en `localStorage` por reserva (clave con el código
  de la reserva), las cuentas, el filtro por cifra, abrir y cerrar fichas, el `GuestComposer`, el pegado (la lógica
  pura, `logica.js`, absorbe `logic.js`: `clave`, `limpiar`, `choice`, `euros`…), la barra de Guardar con su estado,
  «llega una respuesta» (sin servidor en T1: el render trae las pendientes). Casos con `node --test` (`logica.test.js`).
- El suelo sin JavaScript no cambia: el HTML del servidor trae las fichas abiertas y `no-js`; el script pone `js`
  al final de inicializar (`#264`).
- Entrada de Vite `resources/js/fiesta/lista.js` en `vite.config.js` (compartido: avisado en el buzón), que importa
  `fiesta.css`; `x-pagina-enfocada` la carga por su prop `entrada`. ⚠️ El ESLint del gate (`lint:js`, `eslint.config.js`)
  no cubre `resources/js/fiesta` todavía (pedido a plataforma): mientras, `npx eslint resources/js/fiesta` a mano.

### 4.6 Las tandas

| Tanda | Qué | Verificación |
|---|---|---|
| **T0** ✅ | Esta spec: medir, decidir dónde viven, pedir el contrato de hojas, las preguntas al owner. | Guardas de frontera 47/47 · el censo de tokens · docs-check. |
| **T1a** ✅ | La lista con lo que HAY (25-09): el modelo de página, `x-pagina-enfocada`, 12 piezas del núcleo (`pieza/`) y 7 de la fiesta (`fiesta/`), las zonas, la hoja con roles, el JS portado, `lang/*/fiesta.php`, la hoja de la instancia; `store()` acepta la personalización con el único Guardar. | Banco de PIEZAS: **46 pares × (390, 1280) a 0 px** + control (1 px tumba 5 pares; restaura byte a byte) · `ListaDeInvitadosTest` 7 · `PaletaNeutraTest` 4 con su mutación · `node --test` 8 · seis guardas de piel re-apuntadas · sonda de ventana 390/1280 (`storage/app/audit/fiesta-lista-viva-*.png`) · suite. |
| **T1b** ✅ | La pasada LIGERA de la lista (25-09 noche, `#768`): A monta `PliPagina` (la ficha del diseño tal cual, sin su barra de pruebas; el `guardado` AJUSTADO a lo que HAY, §4.7), B la Blade entera con `scripts/banco-fiesta/modelos.php` (los tres modelos del diseño; `FiestaModeloTest` iguala su forma a la de los tres controladores, clave a clave y tipo a tipo). Cazó CUATRO defectos de la T1a que el banco de piezas no podía ver: `[data-vacia]` escondía la PRIMERA fila con nombre; «Escribir el recordatorio» no enviaba (`type="button"` delante del `submit`); la última fila visible llevaba borde; «Reenviar» perdía su color por `.fiesta-lista a`. Y el `años` con espacio duro, como el diseño. | `lista-recien` (la primera pantalla) y `lista-guardado-diagnostico` (nueve niños, sin lo que FALTA a los dos lados), página ENTERA a 390 y 1280 → **4 de 4 a 0 px** · el par real de `guardado` no mide lo mismo (A 4.040, B 2.932 a 1280: la tarta y los padres) y no entra en el lote · control de 1 px sobre la página (`mutar-fiesta.sh`, segunda etapa, con `npm run build` en medio) · `FiestaModeloTest` 3 · `ListaDeInvitadosTest` 6 · suite · **queda el ojo del owner en `localhost:8081`**. |
| **T2** ✅ | La invitación y su recibo con lo que HAY (25-09; `#744`): `InvitacionPagina`, `x-fiesta.rsvp-bar`, `views/fiesta/invitacion/{cabecera,invitacion,recibo}`, `invitacion.js`, el bloque `.inv-*` de la hoja, `lang/*/fiesta.php` (`invitacion_pagina`, `recibo`); el recibo directo tras contestar, 24 h y caducado sin 403, el idioma, «Su ficha» que se guarda sola, la autorización como oferta sin pregunta (la firma, enlace hasta T3), el aviso de privacidad con sus tres cosas. | Pasada ligera (`#768`): la invitación viva y cerrada, A el propio `invitacion.card.html` y B la página entera, 390 y 1280 → **0 px** en los pares de diagnóstico y 4.566–5.082 px en los reales, todos en la línea de privacidad que el mockup no dibuja (`banco-fiesta.php invitacion-viva invitacion-cerrada`) · `InvitacionPaginaTest` 7 · `InvitationPageTest`, `InvitationReceiptTest`, `InvitationSigningFlowTest` re-apuntadas (la hoja en blanco, `og:*`, `no-referrer` intactas) · suite · el recibo, sin A hasta T3 · el owner se manda el enlace al teléfono. |
| **T3** ✅ | La autorización con lo que HAY (25-09; `#745`): `Autorizacion` (modelo de página), `x-fiesta.firma` (`AuthForm` 1:1, con ranuras para lo del producto), `x-pieza.selector`, `views/fiesta/autorizacion`, `autorizacion.js` + `comun.js`, el bloque `.aut-*`, `lang/*/fiesta.php` (`firma`, `autorizacion`); la tarjeta arriba con el titular y quien responde; el descargo en el flujo; el Listo del brief; los bloqueos con la forma del «enlace que no vale». Censo: el adulto en UNA casilla, teléfono obligatorio, nacimiento y relación se quedan. | Pasada ligera: `recibo` y `firmada` a 390 y 1280 → **0 px en `firmada`** y en los diagnósticos; el `recibo` real difiere solo en nacimiento, relación y el descargo (5.958–21.622 px) · `AutorizacionPaginaTest` 5 · `GuardianSkinTest` reescrita a la piel nueva · los cinco desenlaces, la hoja en blanco y las defensas intactas (`GuardianAuthorizationScreenTest`, `GuestMinorAuthorizationTest`) · el flujo real con `curl` (`sonda-aut.sh`) · suite. |
| **T4** ✅ | La piel vieja FUERA (26-09): las cuatro vistas (`reservation/guests`, `reservation/authorization`, `invitation/show`, `invitation/receipt`), `public/js/guest-form/logic.js` con su test, 576 líneas de `site.css` (`.gf-fiche`, `.gf-invite`, `.gf-extra`, `.gf-savebar`, `.gf-meter`, `.gf-group`, `.gf-done`, `.gf-paste`, `.gf-dialog`, `.gf-toast`, `.gf-foot`, `.guardian__*`, `.invitation__*`, `.invitation-card`, y un `.gf-extra__field` colado en la regla de `.eventfields`), el rol `--shadow-nav-dock` sin usuario, y **164 claves muertas** de `guardian.php` (60→30), `invitation.php` (63→14) y `guestform.php` (136→51) en los tres idiomas. Se QUEDAN, medidos: el molde `gf-page/gf-mark/gf-sheet/gf-stub/gf-notice/gf-form` y `focused-layout`, porque los usan las tres vistas de las encuestas (el bloque se retitula «HOJA ENFOCADA — el molde de las ENCUESTAS»), y el ancla `#gf-invite` en la zona 1 (contrato de correos ya enviados y de `invitation_url` en `/me/orders`). | Censo por familia de clase fuera de `site.css` antes de cortar · `ClavesDeIdiomaTest` 7 (paridad es/en/fr de los cuatro ficheros + ninguna clave muerta en los tres viejos, sin contar los tests como uso) · ocho tests re-apuntados por su SUJETO (§3.quater): `GuestFormSkinTest` (el molde), `ShapeScaleTest` (una excepción de foco y un rol de sombra menos), `GuestFormTest` ×3, `MeOrdersTest`, `InvitationDeclinedTest`, `GuestFormManyGuestsTest` · `hoja-del-cajon.py --aplicar` · **las tres páginas re-juzgadas tras el corte: los mismos números que en T1b, T2 y T3** (0 px en todos los diagnósticos, en `firmada` y en `lista-recien`; los reales solo en lo que FALTA) · suite. |
| **F1a** ✅ | Palabras de la familia y pistas para el regalo (26-09): `family_words` y `gift_hints` en `party_invitations` (tope 90, la misma puerta de `PublicFreeText` que «quién cumple» y «te invita»: un enlace se rechaza y se dice), en Personalizar (dos campos opcionales), en la vista previa de la lista y en la tarjeta de la invitación (la burbuja con la inicial y la línea del regalo), y en la API (`GET`/`PUT /reservations/{id}/invitation`, `InvitationCard`: contrato **1.32.0**). La edad en la chapa ya era dato: la pide el pack al reservar y la hereda la invitación; la reserva de prueba 1076 no la traía y se le puso a mano en local. | `ListaDeInvitadosTest` 8 (guardar y pintar; el enlace rechazado y el resto guardado) · `InvitacionPaginaTest` 8 (sin dato, sin bloque; con dato, la burbuja y la línea) · `InvitationApiTest` (el rechazo por la API) · `ApiContractTest` · `FiestaModeloTest`, `ClavesDeIdiomaTest` · las piezas `invitacion-fiesta` (palabras) e `invitacion-sereno` (pistas) ya estaban a 0 px en el banco de T1a; la pasada de página con `opc` encendido, al cerrar F1. |
| **F1…Fn** | Lo que FALTA (§1.4), una pieza por tanda, en el orden que fije el owner (§7). Cada una con su spec de sección aquí, su decisión y, si toca aforo, `VERIFY_CONC=1`. | Por pieza. |

### 4.7 Lo que enseñó la T1a (2026-09-25)

- **Blade**: una directiva pegada a una palabra NO compila (`@endif@if`, `@else@if`; `</x-slot:x>Texto` sale
  `@endslotTexto`) y el comentario `{{-- --}}` no separa (se quita antes): el separador es `{{ '' }}`. Un
  `@if (…) attr="…" @endif attr2` deja DOS espacios (rompió `name="…" value="…"` en tres guardas): se escribe
  `@if (…)attr="…" @endif{{ '' }}attr2`. Una prop `errors` choca con el `ViewErrorBag` compartido (500): `fallos`.
  Un atributo con valor `null` no se pinta. Un `"Hugo\nCarla"` dentro de `:value` en una cadena PHP de comillas
  simples rompe el componente entero (B 195 px más corta): `:value="$VAR"`.
- **CSS contra React**: `text-decoration: underline` (atajo) resetea `text-decoration-thickness` →
  `text-decoration-line`. React deja el grosor del subrayado en `auto` al re-renderizar el hover (156 px): la regla
  del producto lo iguala (`auto` en hover; `1.5px` solo en `--always`). Diagnosticado con una sonda de estilos
  computados, no a ojo. Una diferencia de 444 px era UN espacio antes de `<span class="pz-campo__opt">`.
- **La paleta**: los respaldos «neutros» llevaban CUATRO valores exactos de PlayJump (`--flare-*` y `--aqua-400`,
  copiados del diseño sin verlo) y `PaletaNeutraTest` los cazó en su primera vuelta. ⚠️ `isla.css` (plataforma)
  lleva los mismos `#74ddfa` como neutro sobre tinta (avisado). El blanco y el negro no son de nadie. El zip del
  25-09 trajo `var(--space-2)` en el stepper sin respaldo en `isla.css` (el juez no lo ve: B carga `saltia.css`); la
  misma guarda lo cazó en la suite COMPLETA y `fiesta.css` lo respalda.
- **Herramientas**: el NBSP literal no lo encuentra Edit (en el `old_string` va como ` `; en JS,
  `String.fromCharCode(160)` exportado como `NBSP`). El banco vive en `storage/app/pixel/banco-fiesta` (ignorado) con
  `php -S 127.0.0.1:8132` desprendido; las sondas del navegador entran por `socat` 8081→80. `GuardianAuthorization`
  no tiene `order_id` (esta spec lo daba por hecho): `forceCreate` sin él. La suite COMPLETA cazó cuatro guardas de
  piel que el filtro de la tanda no ejecutaba (`GuestCountSurfacesTest`, `InvitationProposalsTest`).

**La T2 (25-09)**:
- **Blade, otra vez**: `</x-slot:x>@endif` compila a `@endslot@endif` y Blade no ve el `@endif` (el mismo mordisco de la
  T1a con texto): `{{ '' }}` entre el cierre del slot y la directiva. Una marca `data-receipt-sign` es SUBCADENA de
  `data-receipt-signed` y una guarda con `assertStringNotContainsString` la acusó: las marcas se nombran sin prefijos
  comunes (`data-receipt-firmar`).
- **Laravel**: `$request->hasCorrectSignature()` y `signatureHasNotExpired()` son macros que Larastan no ve: por la
  fachada `URL::…($request)`. `lang.switch` vuelve por `url()->previous()`, que sin `Referer` (esta página manda
  `no-referrer`) sale de la SESIÓN (`_previous.url`, que `StartSession` guarda en cada GET): el selector de idioma
  funciona sin ruta nueva, y un test lo afirma (`InvitacionPaginaTest`).
- **El banco de PÁGINA**: B es la página entera (`view()->render()`), y sus assets tienen que ir por el MISMO origen que
  el banco (`../build`, `../instancia`, enlazados): servidos desde `APP_URL` el navegador bloquea las fuentes y el módulo
  de Vite (CORS) y B sale sin `js` y con la fuente del sistema (35 % de píxeles que no eran de la piel). A monta la
  ficha del diseño tal cual con las rutas reescritas, en español (`localStorage` antes de montar: Playwright habla `en`)
  y `InvPagina` sin la barra de pruebas. El `opc` del diseño enciende CUATRO cosas a la vez (merienda, palabras, pistas
  y teléfono): solo la pasada sin `opc` es comparable hasta que las palabras y las pistas sean dato.
- **La diferencia que queda es del producto**: la línea de privacidad bajo la barra (spec hermana §7.2·R7) no está en el
  mockup de la invitación (sí en el recibo); el par de diagnóstico sin ella da 0 y el real, 4.566–5.082 px, todos ahí.

**La T3 (25-09)**:
- ❗ **`view()->shared('site')` en un controlador es `null`**: el `site` de las vistas lo pone un *view composer*
  (`AppServiceProvider`), que corre al PINTAR, y los modelos de página se componen antes. La T1a y la T2 lo leían ahí:
  las páginas vivas decían «JumpWeb» (el nombre de la aplicación) en vez de «Play Jump Park, Lorca» y no tenían «Cómo
  llegar», y el ojo lo tomó por un ajuste local vacío. Lo cazó la T3; `App\Http\Fiesta\Sitio::datos()` lee los mismos
  ajustes con las mismas reglas para los tres controladores.
- **El bag de errores y el arnés**: con `session.serialization = json` el bag de un POST rechazado llega VACÍO a la
  petición siguiente en los tests (driver `array`, atributos en memoria: `marshalErrorBag()` itera un objeto), aunque
  `assertSessionHasErrors` lo vea; ninguna guarda de la piel vieja pintó nunca un error. El flujo real sí lo pinta
  (medido con `curl` y cookies: `sonda-aut.sh`); la guarda mete el bag en sesión con la forma con la que viaja.
- **Blade, tercera vez**: `@if ($x) attr="…" @endif` deja dos espacios y una guarda con `role="status" style=` no lo ve
  (`aviso`, `enlace`): las piezas escriben `@if ($x)attr="…" @endif{{ '' }}`. `--filter='ya_estaba'` no corre un caso
  cuyo nombre de datos lleva espacios: la salida vacía (27 B) parecía un test verde.
- **El censo antes de vestir (`#745`)**: la fecha de nacimiento entra en la prueba firmada (`WaiverSigner`, `CRITICAL_RE`)
  y da la edad a la puerta; la relación entra en la prueba; el apellido del adulto solo compone un nombre completo. El
  descargo se PRESENTA en el flujo (`waiver-probatorio.md` §4.4): «Leer el descargo» es un ancla, no el modal del mockup.

**La T1b (25-09, noche)**:
- ❗ **Un banco de PIEZAS no ve lo que hace la PÁGINA**: 46 pares a 0 px y la lista viva escondía su PRIMERA fila con
  nombre. `pintaFila` pone `data-vacia="0|1"` en cada fila y el selector `[data-vacia]` (el mensaje de la lista vacía)
  atrapaba la primera: ahora el mensaje es `data-lista-vacia`. Del mismo saco: `<button type="button" … type="submit">`
  (el navegador se queda con el PRIMER atributo: «Escribir el recordatorio» no enviaba nunca; la pieza `enlace` pone el
  `type` por `merge`); `fi-fila--last` iba en la última POSICIÓN, no en la última fila visible (con las vacías escondidas
  por `js`, la última con nombre llevaba borde: `marcaUltimas()` lo mueve, también al filtrar); y `.fiesta-lista a`
  (0,1,1) pisaba el color de un `<a>` con cara de botón quieto («Reenviar»): el diseño tiene `a{}` sin ámbito, así que
  `:where(.fiesta-lista) a`.
- **El juez a página completa exige la MISMA altura** y «no miden lo mismo» no es un número: los dos formularios
  auxiliares (`pz-sr`, absolutos de 1 px) DESPUÉS del principal estiraban el documento un píxel; van delante. El par real
  de `guardado` (la tarta y los padres a un lado) no se puede juzgar y no entra en el lote: lo que FALTA se ve abriendo
  `a/lista-guardado.html` y `b/`. Una sonda de alturas por bloque (`getBoundingClientRect` a los dos lados, en la
  carpeta del banco) encontró el píxel en un minuto; a ojo no se ve.
- **Montar el estado del diseño**: `window.PLI.ESTADOS.guardado` se AJUSTA en el montaje (sin los «no», edades a 7,
  sin palabras ni pistas, sin fecha de guardado) y se borran sus claves de `localStorage` antes de montar (el diseño
  persiste `pj-lista-v3-servidor-<id>` por origen y un banco anterior contaminaría el siguiente). En el diseño, «mano»
  con «sí» lleva chapa «por la invitación»: para el presentador eso es `origen: invitacion`, y el modelo lo mapea así.
- **`FiestaModeloTest` en su primera vuelta**: `columnas.labels` y `ninos[].extra` van por clave de DATO (las columnas del
  pack) y se comparan como mapas; `diagnostico` es la única clave que el banco añade. Un `*/` dentro de un docblock
  (`paginas/*/datos.js`) lo cierra; el espacio duro no entra por Edit (`"\u{a0}"` en PHP).

**La T4 (26-09)**:
- ❗ **«Una feature se poda por su bloque» (`#668`) vale cuando el bloque tiene UN dueño**: el bloque «Formulario
  post-reserva — HOJA ENFOCADA» tenía dos, y el segundo eran las encuestas (`survey/*` usa `gf-page`, `gf-mark`,
  `gf-sheet`, `gf-stub`, `gf-notice`, `gf-form` y `focused-layout`). Antes de cortar, un CENSO por familia de clase
  fuera de `site.css` (`grep -rl` por `gf-xxx` en vistas, JS, app) dijo qué se queda (18 clases) y qué se va (todo lo
  demás); y una clase de la piel vieja se había colado en la regla compartida de `.eventfields` (`.gf-extra__field
  input`, 2.800 líneas más arriba): podar por bloque no la habría visto.
- ❗ **Los tests mantenían viva la piel muerta**: cuatro claves de idioma sobrevivieron al censo estático porque las
  nombraba un test, y TRES de esos tests estaban en verde contra la página nueva porque su texto coincidía letra a letra
  con el de `fiesta.php` («No puede venir», «Sin producto para esta edad»): una guarda por TEXTO no sabe de qué clave
  viene (`#553`, otra vez). `ClavesDeIdiomaTest` no cuenta los tests como uso, y los tres se re-apuntaron a la clave que
  la página lee de verdad.
- **Un rol de sombra sin usuario es un token muerto**, y lo dijo la guarda de la guarda de `ShapeScaleTest` («ni un uso
  de `--shadow-nav-dock`»): la barra de guardar vieja era su único usuario. Se retiró el token, no la guarda. Y
  `LandingCssHasNoOrphansTest` cazó en la suite COMPLETA seis reglas más que solo pintaba la piel vieja
  (`.eventfields__help/__error`, `.gf-notice--attn/--err`, `.guestform__privacy/__readonly`) y una deuda declarada que
  se resolvió sola (`guestform__progress`): el filtro de la tanda no las ejecuta; la suite entera sí.
- **Lo que NO se retira**: `focused-layout` y el molde (las encuestas), el ancla `#gf-invite` (viaja en correos ya
  enviados —`GuestFormRequest`— y en `invitation_url` de la API: un nombre de ancla es contrato), y las claves de
  `guardian.php`, `invitation.php` y `guestform.php` que el dominio y los controladores siguen leyendo (los desenlaces,
  los rechazos, la fiesta mixta, el número, los extras, el recordatorio). Las docs que nombraban las vistas borradas
  (`celebracion-e-invitacion.md`, `waiver-por-reserva.md`, `POSTFORM-INVITADOS.md`, `cumple-mixto.md`,
  `complementos-post-reserva.md`, `invitados-en-post-form.md`) apuntan ahora a las nuevas.

## 5. Impacto en invariantes

| ID | Cómo |
|---|---|
| `RGPD-01…04` | Sin cambio en T1–T4: las páginas siguen `noindex`, `no-store`, `no-referrer`; ninguna respuesta de la invitación enseña otros nombres (§7.2·R1 de la spec hermana, `InvitationPrivacyTest`). |
| `SEC-07` | Los textos del anfitrión se escapan al pintar, como hoy. |
| `SEC-12` | El contrato de hojas valida cada ruta bajo `public/instancia/` (plataforma). |
| `PERF-02` | La página lee `settings` por el composer memoizado; la hoja de la instancia es estática. |
| `AFORO-*`, `PAY-*` | **Ninguno en T1–T4.** Solo la pieza «el número sube desde la lista» (§7·3) los toca, y entonces `GuestCountAdjuster` (`CRITICAL_RE`) con `VERIFY_CONC=1` y `purchase:verify-oversell`. |

## 6. Plan de verificación empírica

- Por tanda: `php scripts/banco-fiesta.php <diseño> storage/app/pixel/banco-fiesta http://127.0.0.1:8132 [pieza]` +
  `php -S 127.0.0.1:8132 -t storage/app/pixel/banco-fiesta` aparte + `node scripts/pixel.mjs --lote … --reloj
  2026-09-23T16:05:00+02:00 --rehacer --reintentos 2` → 0 en todos los pares; `bash scripts/mutar-fiesta.sh` → 1 px
  tumba sus pares y el árbol vuelve byte a byte.
- `php artisan test tests/Feature/Fiesta tests/Feature/Reservation` y la suite entera antes del push (la suite
  completa es la que ve las guardas de piel que el filtro no ejecuta).
- `node --test resources/js/fiesta/logica.test.js` y `npx eslint resources/js/fiesta`.
- La sonda de ventana de las tres páginas a 390 y 1280 (`scripts/sonda-fiesta.mjs`, futuro), con capturas en
  `storage/app/audit/`, y el ojo del owner en `localhost:8081` en móvil y escritorio ANTES de cada commit visible.
- La guarda de paleta (`PaletaNeutraTest`, T1a): recorre las vistas, hojas, JS y presentadores de la fiesta en el
  producto y rechaza `--ink-NNN`, `--snow`, `--aqua-`, `--sun-`, `--volt-`, `--berry-`, `--flare-` y los hex de
  PlayJump (de la hoja de la instancia, si la máquina la tiene), y exige que todo `var(--x)` sin respaldo esté
  declarado en `fiesta.css`, `isla.css` o en línea (mutación pagada: `var(--ink-900)` + `var(--fiesta-inventado)`
  en la hoja → tres casos en rojo; restaurada, verde).

## 7. Revisión y decisión

**CONTESTADAS por el owner el 2026-09-25 (`#743`), con opciones delante** — la corrección va DELANTE del texto:
1 **sí, quien cumple cuenta como uno más** · 2 **el asunto del correo 2 va sin nombre** · 3 **la zona 3 sube el número
desde la lista, en su propia tanda con `VERIFY_CONC=1`** · 4 **el recibo dura 24 horas** (la URL firmada pasa de 2 h a
24 h; sigue sin ser un enlace de edición) · 5 **«¿Vas tú con él?» se quita; la puerta queda con dos estados** (firmada ·
sin resolver; `companion` deja de recogerse) · 6 **tres campos por niño, como dato del panel** (se funden alergia y menú
especial; observaciones se retira de los dos packs) · 7 ❗ **«Ver el parque» ENCENDIDO con el vídeo de portada** (14,8 MB,
se descarga solo al tocar; NO la recomendada, que era apagado hasta el clip corto) · 8 **«Avísame de fechas» entra, sin
marcar y con su texto legal**. Con esto la spec pasa a ✅ y las F1…Fn de §4.6 tienen su decisión.

**Preguntas al owner (25-09), con la recomendada primero.** Ninguna bloquea T1→T4.
1. **¿Quien cumple cuenta como uno más en el número?** Recomendada: **sí** (lo asume el diseño y la mayoría de packs).
   Si no: la frase vuelve al brief y el mínimo son 8 invitados.
2. **El asunto del correo «Fiesta reservada» lleva el nombre de quien cumple y sale antes de saberlo.**
   Recomendada: **sin nombre en el asunto**. Alternativa: pedir el nombre en «Listo», tras pagar.
3. **La zona 3 sube el número desde la lista y admite más niños que plazas** (aforo y cobro en el parque).
   Recomendada: **sí, en su propia tanda con `VERIFY_CONC=1`**. Alternativas: la lista no supera la reserva y el
   número sube por el selector de hoy (`#444`); o después de vestir lo que hay.
4. **El recibo: 24 h (diseño) o 2 h (spec, URL firmada).** Recomendada: **24 h** (el padre vuelve a firmar esa
   noche). Alternativa: 2 h como está.
5. **«¿Vas tú con él?» desaparece** (la autorización como oferta sin pregunta). Recomendada: **quitarla**; la puerta
   se queda con dos estados (firmada · sin resolver). Alternativa: mantenerla opcional en el recibo.
6. **Tres campos por niño** (nombre, edad, alergias o menú especial) contra las cinco columnas de los packs de
   PlayJump. Recomendada: **tres, como dato del panel** (se funden alergia y menú especial; observaciones se
   retira). Alternativa: cinco y la ficha las pinta todas (se aparta del diseño).
7. **«Ver el parque» con vídeo en la invitación** (propuesta del diseño). Recomendada: **apagado hasta el clip corto**.
8. **«Avísame de fechas»** (consentimiento comercial). Recomendada: **entra, casilla sin marcar con su texto legal**.

**Revisión**: el owner (es de producto) sobre §0, §4 y §7; después, estado ✅, `/decision` de la banda 730–759 y
la casilla en `00-REFACTOR.md`. Lo que la revisión corrija se escribe aquí DELANTE del texto que corrige.

- **25-09 (plataforma, `#766`)**: el owner confirmó por el otro carril lo mismo que aquí en 1, 2 y 4 (quien cumple
  cuenta; el asunto sin nombre; un solo plazo, 24 h, para la lista y el número). `#767` (manda solo el mockup) y
  `#768` (sin banco por tanda: una pasada ligera al final) ajustan el método de esta spec (§0 y §4.4).
- **25-09 (T2), `[DECIDIDO owner]` `#744`, tres preguntas con opciones**: (1) tras «Vamos» o «No podemos», **el recibo,
  directo** (no la invitación con un aviso y el botón «Dejar sus datos»); (2) los textos nombran a quien organiza por
  **el nombre de pila del titular de la cuenta** (sin él, «quien organiza la fiesta»); (3) «qué es la fiesta» es **la
  descripción pública del pack** (vacía, sin bloque). Sin pregunta: la merienda con lo que HAY (un grupo por complemento
  marcado, icono neutro), «Los calcetines van incluidos» fuera (texto del parque), el recibo caducado devuelve a la
  invitación con su aviso, y el aviso de privacidad de la invitación conserva «cuándo se borra» (§7.2·R7 de la hermana)
  aunque el mockup no lo diga.
- **25-09 (T3), `[DECIDIDO owner]` `#745`, el censo de campos con opciones**: (1) **la fecha de nacimiento se
  mantiene** (el owner preguntó si la edad del recibo servía: no, es opcional, vive en la respuesta y no entra en la
  prueba); (2) **la relación con el menor se mantiene** (el desplegable); (3) **el adulto escribe nombre y apellidos
  en UNA casilla** (el apellido queda vacío, como con la cuenta). Sin pregunta: el teléfono obligatorio (el brief),
  quien responde con su teléfono en la tarjeta (§12.4 de la hermana), el descargo en el flujo (§4.4 de
  `waiver-probatorio.md`), el bloqueo con la forma del «enlace que no vale».

## Anexo · fila del enrutador

`| Vestir la fiesta con el sistema nuevo · post-form · justificante · invitación | docs/specs/fiesta-sistema-nuevo.md §0 |`
