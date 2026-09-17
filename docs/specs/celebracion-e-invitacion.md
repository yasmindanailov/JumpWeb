# [SPEC] El formulario de celebración, el justificante y la invitación digital

> Estado: 🟦 **revisada de forma adversarial dos veces · T1, T2 y T3 EN EL ÁRBOL (§10.1–§10.3) · T4→T6 sin empezar** ·
> Última actualización: 2026-09-17 · Decisiones: `#569` (spec) · `#570` (T1) · `#571` (T2) · `#572` (T3) · Carril: 🧩 SPA (banda 550–579).
> Fuente de diseño: el canvas por `DesignSync` — `doc/formulario.md`, `doc/invitaciones.md`,
> `doc/pendiente.md` (decisiones 13–21 y 36–41) y los artboards `Formulario Post Reserva PJP`,
> `Justificante Invitado PJP` e `Invitaciones PJP` (el turno **2a** manda sobre el 1a).
> Sistemas afectados: `docs/sistemas/POSTFORM-INVITADOS.md` · `docs/specs/waiver-por-reserva.md` ·
> `docs/specs/invitados-en-post-form.md` · `docs/specs/correos-desde-canvas.md`.

## §0 · Antes de tocar

- **Carril del SPA** (banda 550–579). T1, T2 y T3 en el árbol (`#570`–`#572`, §10.1–§10.3); **T4→T6 sin
  empezar** y **si vas a construir, §7.2 primero** (la segunda revisión cambió seis cosas). Orden
  `[DECIDIDO owner]`: T1 → T2 → T3 → T4 → T5 → T7 → **T6**, el aterrizaje, al final.
- **§3.1, la decisión que ordena la feature: lo que contesta un padre NO escribe `guest_data`.** Vive en
  `invitation_replies`, el formulario lo PROPONE sobre una ficha y el anfitrión lo ADOPTA al guardar —
  `submitGuestForm()` sustituye la lista entera y `updated_at` es el testigo de extras e invitados (medido)—.
  Por eso un padre no mueve dinero, aforo ni ningún fichero del `CRITICAL_RE`, y `OrderCreator` no se toca.
- Con la lista completa no se admite un «sí» y el «no podemos» se enseña al anfitrión (D2/D3) · «voy con él»
  no pide firma (D4) · la autorización sigue al interruptor del producto (D5) · se empareja con una ficha
  escrita solo con UN candidato (D11) · un nombre repetido **no se anuncia** (confirmaría quién va).
- **Lo que enseñó §10**: `focused-layout` no cargaba `client.css` desde que existe (las dos páginas iban con
  los colores del producto); el orden de la PÁGINA ya no es el de las POSICIONES (`sanitizeGuestData()`
  ordena por clave); el número de invitados lleva `form="gf-form"` o no se envía (defecto en producción
  desde `#444`, arreglado en el árbol, **pide despliegue**).
- ⚠️ **El molde es de DOS páginas** (`.gf-*`): tocar `.gf-savebar`, `.gf-group__*` o `.gf-notice` mueve también
  el justificante. La T2 hizo la barra pegada y en fila y rompió la de firmar (401 px de 844, en producción
  del 16-09 a la T3, §10.3): tras tocar el molde, **sonda y captura de VENTANA de las dos**.
- **Borde abierto para la T6** (§7·5): bajar invitados descarta las filas del FINAL.
- Anexo al final con la fila del enrutador.

## 0. En una línea cada cosa

- **A · Vestir el formulario post-reserva** (turnos 1 y 2b del canvas, ya decididos): cero dominio.
- **B · Vestir el justificante del menor invitado** (J-01…J-08): cero dominio.
- **C · La invitación digital de una reserva de cumpleaños**: feature nueva. El anfitrión la comparte
  desde su formulario, cada padre contesta «sí» o «no» con el nombre de su hijo, puede dejar sus datos y
  decir si va con él, y lo que llega **se le propone** al anfitrión en sus fichas.
- **Orden** (`[DECIDIDO owner]`, lo difícil al final): T1 → T2 → T3 → T4 → T5 → T7 → **T6**.
- ▶ **Si vas a construir, lee §7.2 antes que el resto**: la segunda revisión cambió seis cosas del
  diseño que la primera versión afirmaba mal o no tenía.

## 1. Contexto y problema

### 1.1 Lo que hay hoy (y funciona)

- **El post-form** (`resources/views/reservation/guests.blade.php`, hoja `.gf-*` de
  `public/css/site.css`, bloque «Formulario post-reserva — HOJA ENFOCADA»): enlace firmado por reserva,
  acordeón de fichas, número de invitados (`#444`), extras con plazo (`#413`), fiesta mixta y solo
  lectura. Funcionalmente completo; **sin vestir**.
- **El justificante** (`resources/views/reservation/authorization.blade.php`, reglas `.guardian__*`):
  hoja en blanco por reserva (`#401`), tope por plazas libres bajo lock, anti-bot, PDF. Solo con
  `WaiverSettings::isInternal()` (producción está en `interno`). **Sin vestir.**
- **La invitación** no existe. La tarjeta pública de `/cumpleanos` que el canvas dice «no se toca»
  **ya no existe**: la retiró `#528`.

### 1.2 Lo que el canvas tiene caducado, medido el 2026-09-13

| Afirmación del canvas | Medido |
|---|---|
| F-07: «la columna Edad no viene sembrada» | Los dos packs de esta instalación tienen `name · age · allergy · notes · special_menu` (tipo `age` incluido). Sigue siendo cierto para `DEFAULT_GUEST_FIELDS` del producto. |
| Decisión 16: «hacen falta los plazos» | Existen: `packs.guest_count_cutoff_hours` (vacío = 24 h) y `postform_cutoff_hours` por enganche. La pantalla solo tiene que **escribirlos como fecha**. |
| F-01: «7 reglas con `--zone-1`» | **9 apariciones** en el bloque `.gf-*`. |
| F-02: «doce tamaños» | **14 declaraciones de talla distintas** (incluidas dos `clamp`). |
| Correos «23 → 24» y decisión 40 («dos correos nuevos») | Hay **25** (`#508`). Y el turno 4a se contradice con la 40: el recordatorio **no es un correo**. Manda el 4a (§4.9). |
| «Su día especial» | El post-form **no se renombra** (`#462`): se llama «Formulario de reserva» de cara al cliente. |
| «El menú del pack no existe escrito» | Existe como dato: «Menú 1» y «Menú 2» son complementos con su lista en `features`. Lo que no existe es **cuál** es el menú (§3.5). |

### 1.3 Los hechos del código que deciden el diseño

1. **`OrderItem::submitGuestForm()` sustituye la lista ENTERA de fichas** (`sanitizeGuestData($rows,
   quantity)`, posicional). Un anfitrión con la página abierta que guarda **borraría** cualquier ficha
   que otro hubiera escrito entretanto.
2. **`updated_at` de la reserva es el testigo** de los extras (`PostFormAddons::versionOf`), del número
   de invitados (`expected_version`) y de cinco puertas del operador. Toda escritura en `order_items`
   deja obsoleta la página abierta del anfitrión.
3. **Booking no puede leer Identity**; Identity sí lee `Booking\Contracts` (`ModuleBoundariesTest`).
   `User` solo tiene permitido importar `Order`, `OrderItem` y `Ticket` de Booking.
4. **`GuardianPlaces::takenIn()`** = menores a cargo asignados + justificantes. Es a la vez el suelo
   de una bajada de invitados (`ReservationPlacesTaken`, `#444`) y el tope del firmador (`freeIn`,
   bajo el lock del titular).
5. **Bajar invitados descarta las filas del FINAL** (`GuestCountAdjuster::filledFormsBeyond`).
6. **`GuardianAuthorization::keyFor()`** normaliza un nombre (ASCII, minúsculas, espacios) y vive en
   Identity.
7. La hora de fin real es **inicio + `occupiedMinutes()`** (base + `extra_minutes`, `#426`), nunca
   `slot.end_time`.
8. Existen `address.line1/line2`, `address.maps_url` (con `safeExternalUrl`) y `og_image`.
9. **La marca del justificante la escribe `OrderCreator`** (`requiresGuardianAuthorization()` o
   `offersGuardianAuthorization()` + la casilla de la línea), y está en el `CRITICAL_RE`. **Los dos
   packs de cumpleaños están hoy en `none`.**
10. **El esquema por invitado no tiene un tipo «nombre»**: sus tipos son `text · number · textarea ·
    age`, y la columna de nombre es `name` por CONVENCIÓN del defecto, no por tipo.
11. **`PurgeCustomerData` borra justificantes y pedidos POR TABLA** (`DB::table(...)->delete()`), y
    `User::anonymize()` vacía `guest_data` con una consulta sobre `order_items`.
12. **`npm run test:js` solo recoge `resources/js/**/*.test.js`.**
13. **Las filas de invitados del contrato son un mapa ABIERTO** (`GuestFormRequest.guests.items` con
    `additionalProperties` de respuestas): cualquier clave dentro de una fila compite con las columnas.

## 2. Objetivo

### 2.1 Criterios de éxito

**A · El formulario**
- Cero `--zone-1` en `.gf-*`. Cuerpo a 16 en móvil y 17 en escritorio. La escala de letra baja a las
  seis del sistema. Todo control, incluido el enlace de la política, llega a 48.
- Una sola sombra difusa (la barra pegada) y radios en la escala de cuatro.
- **20 invitados: de 60 toques a 23** con el pegado de nombres. Guardar siempre a la vista (barra de 85).

**B · El justificante**
- Sin scroll dentro del scroll. Casilla de 24.
- Los cinco desenlaces con cuatro tonos, sin hex ajenos. El logotipo como fichero.

**C · La invitación**
- **El padre contesta con un nombre y un gesto.** El resto es opcional y dice quién lo hace si no.
- **Hoja en blanco**: ninguna respuesta HTTP de la invitación contiene datos de otros invitados, ni
  cuántos han contestado, **ni si un nombre concreto ya ha contestado** (§7.2·R1).
- **Un padre nunca mueve dinero ni aforo, y un guardado del anfitrión nunca borra lo que llegó por la
  invitación.** Verificado con un caso que intercala respuesta y guardado.
- **Nunca se admite un «sí» con la lista completa.** Verificado con envíos concurrentes sobre la última
  plaza.
- La puerta y la hoja de sala dicen, por niño invitado: firmada · viene con un adulto · sin resolver.
- Cero naranja. Enlace no adivinable, anulable y con caducidad.

### 2.2 Fuera de alcance

- Regalos o lista de regalos, y vender entradas a los invitados (canvas «Lo que NO se hace»).
- Foto del niño. Ilustración encargada de los temas (entra después por el kit de la instalación).
- Aparcamiento, recogida, mapa embebido y calcetines en la invitación (`[DECIDIDO owner]`).
- Un correo «te han invitado»: no tenemos el correo del padre y no se le pide.
- Avisar a los padres si el parque mueve la fecha (limitación declarada, §7.1·8).
- El rediseño del kiosco de la puerta (`Puerta Panel PJP`, decisiones 54–64): aquí solo entra el
  tercer estado sobre la pantalla actual.
- Las pantallas de la app nativa. Sí entra el contrato (API-first).

## 3. Opciones consideradas

### 3.1 Dónde vive lo que contesta un padre (la decisión que ordena la feature)

| | Qué es | Por qué sí / por qué no |
|---|---|---|
| **(a) Escribir en `guest_data` al contestar** | La respuesta ocupa una ficha en el acto. | ❌ Choca con §1.3·1 y §1.3·2. Sin identidad de fila y fusión a tres bandas, el guardado del anfitrión borra lo que escribió el padre. Con ellas, cada respuesta deja obsoleta la página del anfitrión (extras y número rechazados «la reserva ha cambiado»), y eso pasaría **justo en el caso normal**, porque el bloque de compartir está encima de las fichas. Además un padre movería dinero: una edad escrita dispara el suplemento mixto. |
| **(b) Proyectar en todos los lectores** | Las respuestas en su tabla, y cada lector (hoja, puerta, dinero, completitud) las suma. | ❌ Es la trampa de `prices` (`#324`): media docena de lectores cambian de significado sin fallar. |
| **(c) Tabla propia; el formulario las PROPONE y el anfitrión las ADOPTA al guardar** ✅ | La respuesta vive en `invitation_replies`. Al pintar el formulario se coloca sobre una ficha (emparejada o vacía), con sus campos prerrellenos y la chapa «Por la invitación». Al pulsar Guardar pasa a `guest_data` por la puerta de siempre. | ✅ El padre **no escribe `order_items`**: ni testigo, ni dinero, ni aforo, ni ficheros del `CRITICAL_RE`. El dinero se mueve cuando guarda el anfitrión, como hoy (`#285`). El canvas lo dibuja así: «Nombre, edad y alergias ya vienen de la invitación. **Repasa y guarda.**» (C2). Coste declarado: hasta que el anfitrión guarda, la hoja de sala y la puerta leen también las respuestas pendientes (§4.8). |

### 3.2 El enlace

- ❌ **Firma temporal de Laravel** (como el post-form): unos 200 caracteres, imposible de teclear, y
  confundible con la credencial del anfitrión.
- ❌ **`/i/lucia-8`** (artboard 1a): adivinable, y lleva el nombre y la edad de un menor en la URL.
- ✅ **Token opaco de 12 caracteres base62** (~71 bits) en `/i/{token}`: se puede anular (se rota) y
  caduca con la reserva.

### 3.3 La vista previa del chat

- ❌ **Imagen generada en el servidor con nombre, edad y fecha**: rasterizadores distintos entre
  entornos (`#217`), fuentes por instalación, y publica la edad de un menor en una imagen.
- ✅ **Imagen fija por tema, con los datos en el título** (`[DECIDIDO owner]`).

### 3.4 Los temas

- ❌ Los seis del canvas: necesitan ilustración encargada y clavarían el mural de un parque en el
  producto.
- ❌ Sin temas.
- ✅ **Temas abstractos del producto** (`[DECIDIDO owner]`): banda, confeti y color de la chapa de edad
  hechos con formas del sistema y los **tokens de la instalación**. Lista cerrada, **tres**, elegidos
  por el owner viéndolos renderizados en la T5.

### 3.5 Qué complemento es «el menú»

- ❌ Deducirlo del grupo excluyente: es la trampa de los calcetines (`#485`), presentación usada como
  identidad.
- ✅ **Casilla en el enganche «Se enseña en la invitación»** (`[DECIDIDO owner]`).

## 4. Diseño elegido

### 4.1 Decisiones del owner (2026-09-13)

| # | Decisión |
|---|---|
| D1 | Orden de tandas: A → B → C, con lo más difícil (el aterrizaje, T6) al final. |
| D2 | **Lista completa → no se admite un «sí»**: «La lista de esta fiesta está completa. Habla con quien te invita.» No hay lista de espera. |
| D3 | **Se recoge el «no podemos»**, y el anfitrión lo ve en su formulario con la sugerencia de bajar el número mientras esté en plazo. |
| D4 | **«Voy con él» no pide firma**, y la pantalla dice que el adulto **se queda en el parque mientras dura la fiesta**. |
| D5 | **La autorización sigue al interruptor del producto**: los packs pasan a `optional` desde el panel (dato). Con `none` no se pregunta «¿vas tú con él?». |
| D6 | La invitación enseña **quién cumple, fecha, hora de inicio y fin, dónde (con «Cómo llegar» a Google Maps), el menú, quién invita y «Añadir al calendario»**. Fuera: aparcamiento, recogida, mapa embebido y calcetines. |
| D7 | Temas abstractos del producto (§3.4). |
| D8 | Vista previa: imagen fija por tema y los datos en el título. |
| D9 | **El padre no corrige lo que envió**: lo corrige el anfitrión, que puede editarlo todo (decisión 41 del canvas). |
| D10 | **El nombre del niño se pide ANTES de «Sí, viene» / «No podemos»**: un solo campo. |
| D11 | **Emparejado con una ficha ya escrita**: se funde solo si hay **exactamente un** candidato (§4.5·4). |
| D12 | El menú es la casilla del enganche «Se enseña en la invitación». |
| D13 | **«Te invita»**: una línea que escribe el anfitrión (rellena con el nombre de su cuenta) y su teléfono **solo si lo marca**. |
| D14 | **Plazo de respuesta = plazo de cambiar invitados** (hoy 24 h antes). La firma sigue abierta hasta la fiesta. |
| D15 | **Interruptor por producto «Invitación digital»**. En esos productos el cajón no enseña la casilla del justificante. Es incompatible con `required`. |
| D16 | «Añadir al calendario» **sí**; calcetines **no**. |
| D17 | Asumidos sin objeción: correos según el turno 4a · sin foto · sin teléfono del padre · la hora de fin incluye la hora extra · puerta y hoja con los tres estados · decisiones 13 (guardar en tinta), 18 (descargo entero) y 21 (el anti-bot se queda, con rótulo). |

**Derivadas de este diseño, VETABLES por el owner**:
- **V1** · Las respuestas se proponen y el anfitrión las adopta al guardar (§3.1c).
- **V2** · Enlace con token opaco (§3.2).
- **V3** · Las respuestas se borran **14 días después de la visita**. Lo adoptado sigue en `guest_data`
  con su régimen de siempre.
- **V4** · Un «sí» cuenta como plaza con dueño en el suelo de bajar invitados.
- **V5** · Apagar el interruptor del producto **no rompe** una invitación ya compartida: gobierna lo que
  se ofrece, no lo que ya existe (`#400`: «un interruptor de oferta no es un interruptor de permiso»).
- **V6** · Un nombre repetido **se acepta en silencio** y lo resuelve el anfitrión (§7.2·R1).

### 4.2 A · El formulario vestido (T1 y T2)

Se construye **lo decidido en `doc/formulario.md`** («Lo decidido», las ocho grietas F-01…F-08 y el
«Turno 2»), con estas precisiones:

- **T1 · la piel.**
  - Papel liso, columna de 640 y resguardo en tinta (celdas apiladas en móvil, en fila en escritorio).
  - Botón de guardar en tinta. ⚠️ No contradice a `#581`/`#584` (del mismo día): el relleno de acción
    es de lo que VENDE, y esta página no vende.
  - «Hecho»: el rol se fija **midiendo `client.css` al abrir** la tanda, porque el canvas dice «Lima» y
    el producto tiene `--ok`. No se estrena un hex.
  - Estado de la ficha con palabra.
  - Asterisco fuera y «(opcional)» en las columnas no obligatorias (sale de `required`, dato del panel).
  - Los cuatro avisos sobre papel. Lo inerte en superficie Nube, nunca con opacidad.
  - El enlace de la política como control de 48, en el párrafo y en el pie.
  - Tres cadenas: `children_heading` · `count_warn_title` · `count_error_title`.
  - Escritorio: cuerpo 17 y título 36.
  - ⚠️ `ShapeScaleTest` exceptúa hoy `.gf-fiche__head:focus-visible` por «acento de zona»: la excepción
    **se retira con su motivo**.
- **T2 · muchos invitados.**
  - Fichas agrupadas por estado: pendientes arriba y listas plegadas; el grupo se llama **«Falta algo»**.
  - Barra pegada de 85 con la cuenta y «Guardar» a 56.
  - Extras en filas de 69; la fila entera despliega «Más info» (`tickets.addon_more_info`).
  - **Pegado de la lista de nombres**: un diálogo que **dice la cuenta antes de aplicar** y reparte
    **solo sobre las fichas vacías**. Rótulo `status_missing` («Falta :field») y `group_done`.
  - Cero dominio: el pegado rellena campos y lo persiste el Guardar de siempre.
  - La lógica pura del pegado (partir líneas, limpiar, repartir) va en `resources/js/guest-form/` con
    casos de `node --test` (§1.3·12), y la página la carga con **una entrada de Vite propia**, sin
    Alpine ni `app.js`. ⚠️ `vite.config.js` es compartido: se avisa en `ESTADO.md` antes de la T2.
- **Lo que NO cambia**: el orden del guardado (testigo → cantidad → re-leer → fichas → extras), el
  saneo, el contrato de la API y el modo de solo lectura (**el acordeón se queda** y su cabecera sigue
  midiendo 72).

### 4.3 B · El justificante vestido (T3)

Lo decidido en `doc/formulario.md` §«Lo decidido en el justificante»:
- Descargo **entero**, con «Firmar» en la barra pegada y el nombre del menor a la izquierda.
- Cinco desenlaces en cuatro tonos (firmada = éxito · ya firmada = información · texto actualizado =
  plazo · anti-bot y rechazo = error).
- Casilla del sistema (24, radio 10). Ordinal en cubo de 40. Logotipo como fichero.
- Bordes continuos. Textos legales a 15 y la política fuera de su frase.
- **Rótulo delante del anti-bot**: segunda excepción de tercero del sistema, tras el botón de Google.
- El error de la fecha de nacimiento, pintado.
- Cero cadenas nuevas de dominio. **Una sola novedad funcional**: la pantalla acepta llegar desde una
  respuesta de invitación (§4.5·7), pero **eso entra en la T4**, no aquí.

### 4.4 C · Modelo de datos (T4)

Todo lo nuevo (futuro):

| Tabla · columna | Tipo | Para qué |
|---|---|---|
| `ticket_types.guest_invitation` | bool, defecto `false` | D15. Solo un producto con post-form (pack con `guest_fields`) **que tenga columna de nombre** (abajo). Incompatible con `guardian_authorization = required`, con guarda en las dos direcciones del formulario del catálogo. |
| `product_addons.show_in_invitation` | bool, defecto `false` | D12. ⚠️ Entra en **las tres listas blancas** (`ADDON_PIVOT_COLUMNS`, `sanitizePivotData()` y el `fillForm()` de «Configurar»), o se cae al enganchar **sin avisar** (`#413` §4.7·ter). |
| `party_invitations` | una fila por reserva: `order_item_id` único, FK **`cascadeOnDelete`** | `token` char(12) único · `theme` varchar(16) · `honoree_name` varchar(60) · `honoree_age` tinyint nullable · `host_line` varchar(80) · `show_host_phone` bool · `reminded_at` · `reminded_count` · timestamps. La cascada es obligatoria: la purga borra pedidos por tabla (§1.3·11). |
| `invitation_replies` | una por respuesta | `party_invitation_id` FK `cascadeOnDelete` · `order_item_id` (denormalizado, para leer por lotes y purgar) · `attending` bool · `child_name` varchar(120) · `child_key` varchar(255) · `data` JSON nullable (las columnas del pack, saneadas con `sanitizeGuestData`) · `companion` enum nullable (`with_adult` · `alone` · `unknown`) · `adopted_at` · `adopted_name_key` · `dismissed_at` · timestamps · índice (`order_item_id`, `child_key`) **NO único** (V6). |
| `guardian_authorizations.invitation_reply_id` | FK nullable **`nullOnDelete`** | El vínculo firma ↔ respuesta, en el lado de **Identity**, que escribe el firmador. `nullOnDelete` porque las respuestas se podan a los 14 días (V3) y el justificante se conserva años: con RESTRICT la poda fallaría. ⚠️ **No entra en el hash** de la firma (la lección del `SET NULL` de `waiver-por-reserva.md` §10). |

Reglas estructurales:
- **Módulo.** Booking es dueño de la invitación y las respuestas: cuelgan de la reserva, como el
  post-form. Identity (puerta, plazas y firmador) las lee por un contrato nuevo
  `Booking\Contracts\PartyGuests` (futuro).
- **Normalizador.** Sube a Platform (`PersonNameKey`, futuro) y `GuardianAuthorization::keyFor()` delega
  en él. Salida idéntica, con caso de paridad.
- **La columna de NOMBRE** (§1.3·10, §7.2·R2): es **la primera columna `text`** de `guest_fields` (en
  el defecto del producto y en los dos packs, `name`). El interruptor exige que exista. De ella leen el
  emparejado, la lista completa, la puerta y la hoja.
- **Supresión.** `User::anonymize()` borra invitaciones y respuestas **por tabla y por los ids de sus
  reservas**, igual que ya vacía `guest_data` (§1.3·3: `User` no puede importar los modelos nuevos).
- **Purga.** La alcanza la cascada.
- **Poda.** La de V3 entra en la lista de `model:prune` de `routes/console.php`; un modelo fuera de esa
  lista **no se poda nunca**.

### 4.5 C · Reglas del dominio (T4)

1. **Nace sola.** Al pintar el formulario de una reserva cuyo producto la tiene, se materializa con
   `firstOrCreate` bajo el único (una violación de unicidad concurrente se relee, no revienta).
   - Prerrellena `honoree_name` y `honoree_age` desde `event_data` por las claves `celebrant` y `age`;
     si el panel las llamó distinto, quedan vacías y las escribe el anfitrión.
   - `host_line` sale del nombre de la cuenta.
   - ⚠️ Nace en el GET a propósito: **Web Share necesita el enlace en el mismo gesto**, y un `fetch`
     previo pierde la activación en Safari. Un escáner de correos que abra el enlace solo crea una fila
     vacía, sin nada que filtrar.
2. **Se puede compartir** mientras `GuestCountPolicy::isOpenFor()` (pagada, no cancelada, no celebrada)
   y `honoree_name` no esté vacío. ⚠️ **Pasado el plazo se sigue compartiendo** (§7.2·R8): la
   información hace falta el mismo día, y lo único que se cierra son las respuestas.
3. **Lista completa.** Se comprueba bajo lock de la fila de `party_invitations`:
   **fichas con nombre en `guest_data`** (acotadas a `quantity`) **+ «sí» pendientes, distintos por
   `child_key`, que no emparejan con ninguna de esas fichas ≥ `quantity`**. Con la lista completa, un
   «sí» se rechaza (D2) salvo que **empareje** (regla 4) o repita un `child_key` pendiente (regla 5),
   porque entonces no ocupa plaza nueva. Un «no» nunca ocupa.
4. **Emparejado** (D11). Candidatas: fichas con nombre **no adoptadas por otra respuesta** cuyo
   `PersonNameKey` sea igual al de la respuesta, **o igual a su primera palabra** (el anfitrión pegó
   «Mateo» y el padre escribe «Mateo Ruiz»).
   - **Exactamente una** candidata → se propone sobre esa ficha.
   - Cero o varias → sobre la primera ficha vacía.
5. **Un nombre repetido NO se rechaza ni se anuncia** (V6, §7.2·R1). Decir «ya nos habéis contestado
   por Hugo» le confirmaría a cualquiera con el enlace que Hugo va a esa fiesta.
   - Se guarda, y el padre ve **el mismo desenlace** que la primera vez.
   - Con el mismo `child_key` que un «sí» pendiente, se une a su propuesta y **no ocupa plaza nueva**.
   - El anfitrión ve «respuesta repetida», y en la propuesta manda la más reciente.
   - Para distinguir a dos niños con el mismo nombre, el campo pide **nombre y apellidos**.
6. **Tras el «sí»** la respuesta da un **recibo**: una URL firmada temporal de **2 horas** atada a esa
   respuesta, que abre las dos ofertas (G1).
   - «Quién viene» escribe `data` con las columnas del pack, en su orden y todas opcionales (G2).
   - «¿Vas tú con él?» escribe `companion` (G3).
   - Pasadas las 2 horas las ofertas desaparecen. No es un enlace de edición (D9).
7. **«Lo dejo y me voy»** lleva al justificante de esa reserva con la respuesta atada (URL firmada),
   el nombre del menor prerrelleno **sin partirlo en nombre y apellidos** (`#236`) y
   `invitation_reply_id` en la firma.
   - ⚠️ **El firmador no descuenta plaza por una firma atada a un «sí»**: esa plaza ya tiene dueño.
     Sin esa excepción el padre que dijo «sí» con la lista completa **no podría firmar**.
8. **Plazas con dueño** (V4). `GuardianPlaces::takenIn()` = menores asignados + justificantes + «sí»
   no descartados, distintos por `child_key`, **sin firma atada**. Así el suelo de `#444` protege a un
   niño que confirmó.
   - Un justificante suelto de un niño que además dijo «sí» cuenta dos veces: el suelo sale alto, que
     es el lado seguro. Declarado.
   - Esto cambia el tope del justificante suelto: queda escrito en el docblock de
     `ReservationPlacesTaken`.
9. **Plazos** (D14).
   - «Sí», «no», datos y compañía se cierran cuando `GuestCountPolicy::lockedReason()` deja de ser
     `null` (reserva no abierta, o pasado `deadlineFor()`): el mismo plazo y la misma fuente que el
     número de invitados.
   - La firma y la página siguen abiertas hasta la fiesta.
   - El token deja de abrir en `guestFormLinkExpiresAt()` (visita + 14 días, `RGPD-03`).
10. **Estados de puerta**, derivados y nunca guardados. Por niño invitado:
    - **firmada**: hay justificante atado o emparejado por nombre.
    - **con un adulto**: `companion = with_adult`.
    - **sin resolver**: todo lo demás (`alone` sin firma, `unknown` o sin respuesta).
    - Solo existen si el producto no está en `none` y el waiver es `interno`.
11. **Anular.** El operador rota el token desde el panel, con rastro. El anfitrión no, en esta versión.
12. **Anti-abuso y seguridad** (T4).
    - Honeypot silencioso y Turnstile que **lo dice** (la asimetría de `#335`).
    - Limitador por IP y por invitación.
    - Tope de respuestas por invitación: `3 × quantity`, para frenar el spam de «no».
    - Rastro sin PII (`RGPD-02`).
    - **El texto libre del anfitrión** (`honoree_name`, `host_line`) se publica bajo el dominio del
      parque: se **rechazan URLs y direcciones de correo** (§7.2·R9). El teléfono solo sale del de la
      cuenta, por la casilla.
    - **Un solo desenlace, con el mismo código 404**, para token inexistente, caducado, reserva
      cancelada o titular anonimizado (§7.2·R10).

### 4.6 C · La página de la invitación (T5)

Artboard `Invitaciones PJP` **2a**: F1, F2 y G1–G3.

- **Marco**: el molde de las páginas de enlace firmado (`focused-layout`), columna de 640, tarjeta en
  tinta con el tema en banda, confeti y chapa de edad. **Cero naranja.**
- **Bloques, con la regla «sin dato, sin bloque»**:
  - «¡Te invito a mi cumple!» · nombre · edad.
  - Empieza (día y hora) · «Dura … hasta las …» (inicio + `occupiedMinutes()`).
  - Te invita (`host_line` y «Llamar» si lo marcó).
  - Añadir al calendario.
  - Dónde: nombre del negocio, `address.line1/2` y «Cómo llegar» con `address.maps_url`. Enlace
    externo, **nunca un mapa embebido**.
  - El menú: los complementos de **esa** reserva con `show_in_invitation`, leídos con `tr()` (la trampa
    de `features`, `#463`).
- **Barra pegada desde el primer píxel**: campo «Nombre y apellidos del niño o la niña» + «Sí, viene» +
  «No podemos», los dos con el mismo peso. ⚠️ Con Turnstile activo el gesto no es literalmente uno: modo
  gestionado o invisible, y **se mide** en la T5 cuántos cuesta (§7.2·R12).
- **Desenlaces**:
  - «Contamos con vosotros» (G1, con las dos ofertas si toca).
  - «Gracias por avisar» (tras un «no»).
  - Lista completa.
  - Plazo cerrado: la información de la fiesta **se sigue viendo**; lo que se cierra son los botones.
  - Anti-bot.
  - Invitación no disponible: la misma página y el mismo 404 en los cuatro casos (§4.5·12).
- **G3** (D4), borrador de texto que corrige el owner: «**Voy con él** — No hay que firmar nada. Te
  quedas en el parque mientras dura la fiesta y te identificas en la puerta.» · «**Lo dejo y me voy**
  — Entonces hace falta tu firma: son dos minutos, aquí mismo.» · «**Todavía no lo sé** — Firma por si
  acaso, o resuélvelo en la puerta ese día.»
- **Privacidad** (§7.2·R7): en G2 y al pie de la página, un aviso **sin casilla** (el criterio de
  `#350`) que dice para qué son los datos, **que los verá quien organiza la fiesta y el parque**, y
  cuándo se borran, con la política como control de 48. G2 recoge alergias de un menor (art. 9).
- **Vista previa**:
  - `og:title` «Lucía cumple 8 · sábado 4 de octubre».
  - `og:description` «A las 17:00 en {negocio}. Dinos si venís.»
  - `og:image`: el fichero del tema de la instalación si existe; si no, el `og_image` del sitio. Con
    `og:image:width` y `og:image:height` declarados.
  - Solo nombre, edad, día, hora y negocio.
- **Calendario**: `.ics` servido por el servidor, con título «Cumple de {nombre}», inicio, fin y
  dirección, **con `TZID` de la zona del parque** (`DisplayTime::timezone()`, §7.2·R13): las franjas
  son hora de pared. Se prueba en un teléfono de verdad; si Android no lo abre bien, se añade el enlace
  de Google Calendar como segunda opción.
- **Cabeceras**: `noindex`, `Cache-Control: no-store` (`RGPD-04`) y `Referrer-Policy: no-referrer`,
  para que «Cómo llegar» no lleve el token a Google en el `Referer`.
- **Idioma**: el mismo mecanismo que el justificante (es/en/fr). Los textos libres del anfitrión, tal
  cual los escribió.

### 4.7 C · Lo que ve el anfitrión en su formulario (T6)

Artboards E1, E2, H1 y H2, más `Formulario Post Reserva PJP` **3a**. **El armazón de la T2 no se toca.**

- **Bloque de la invitación**, arriba y antes de las fichas.
  - «Compartir la invitación»: Web Share con texto y enlace; sin Web Share, «Copiar enlace».
  - «Personalizar»: tema, nombre y edad de quien cumple, «Te invita» y «Enseñar mi teléfono». Escribe
    **solo** `party_invitations`, así que no toca el testigo.
  - El resumen: «N vienen · M no pueden · K por repasar».
  - El plazo de respuesta **escrito como fecha**.
- **Respuestas propuestas.**
  - Cada «sí» pendiente se pinta sobre su ficha (§4.5·4): campos prerrellenos (**solo los vacíos**; el
    nombre que escribió el anfitrión se queda), chapa «Por la invitación» y su id en la lista `adopt[]`.
  - La marca de adopción viaja **fuera de las filas**, como la lista `adopt[]`, en la web y en la API
    (§1.3·13, §7.2·R3).
  - Al Guardar, `adopted_at` + `adopted_name_key` de las respuestas **de `adopt[]`** que siguen
    pendientes y son de esa reserva. Las que llegaron después de pintar **no se adoptan**: siguen
    pendientes y salen en el siguiente render.
  - Si una ya adoptada deja de emparejar con alguna ficha guardada, se marca descartada: el anfitrión
    la quitó.
  - **Un «sí» pendiente también se puede quitar** («No lo apuntes», §7.2·R11). Sin eso, V4 dejaría al
    anfitrión sin forma de bajar invitados por debajo de una respuesta que no quiere.
- **Grupo «No vienen»** con los nombres. Si un «no» empareja con una ficha, la ficha lleva la chapa
  «Ha dicho que no viene». Frase: «Si no vienen, puedes bajar el número de invitados hasta el {fecha}»
  (D3), que lleva al selector de `#444`. Se puede descartar cada aviso.
- **Si no caben** (carrera entre el guardado y un «sí», §7.1·3): un aviso «Hay N respuestas que ya no
  caben: sube el número o avisa a esas familias». No es una lista de espera: es la carrera, dicha.
- **Estado de entrada** por ficha (§4.5·10) y bloque de los tres estados, con palabra y punto y
  ninguno en rojo.
- **«Escribir el recordatorio»**: compone el texto con el enlace (con los nombres de quienes faltan
  solo si el anfitrión marca la casilla), lo copia y guarda `reminded_at` y `reminded_count`. **No
  envía nada.**
- **El suelo** de bajar invitados usa `takenIn()` (V4). El rechazo `count_error_below_assigned` ya dice
  «quita a alguien de la lista».

### 4.8 C · Puerta, hoja de sala, catálogo y cajón (T6 y T4)

- **Puerta** (`GateProfile::guestMinors`, T6).
  - Los niños invitados de las reservas de hoy salen de fichas con nombre + «sí» pendientes, con su
    estado y la cuenta «8 de 12 con justificante».
  - Los «no» no cuentan.
  - ⚠️ Presupuesto: el techo de `GateProfileTest` (28 consultas) no sube, así que la lectura va por
    lotes a través de `PartyGuests`.
- **Hoja de sala** (`ReservationSlip::guestRows`, T6): las respuestas pendientes salen como filas
  marcadas «por la invitación, sin repasar». Sin esto, un anfitrión que no vuelve a guardar deja niños
  fuera del papel.
- **Panel, ficha del pedido** (T6): en la línea, «Invitación: N vienen · M no · K por repasar»,
  «Copiar enlace de la invitación» y «Anular el enlace» (rota el token, con rastro; la acción va en T4).
- **Catálogo** (T4): el interruptor «Invitación digital» (D15) y la casilla del enganche (D12).
- **Cajón y pedido manual** (T4): con el interruptor encendido **no se ofrece** la casilla del
  justificante. Lo resuelve UN predicado del producto (`TicketType::funnelGuardianMode()`, futuro) que
  leen el recurso del catálogo que recibe el cajón y la visibilidad del conmutador de «Crear pedido».
  ⚠️ **`OrderCreator` NO se toca** (§1.3·9, §7.2·R4): está en el `CRITICAL_RE`, y una marca forjada solo
  produciría el correo del justificante suelto, que funciona igual en cualquier pedido pagado
  («ofrecer ≠ permitir», `#400`).

### 4.9 C · Correos (T7)

Turno 4a. Molde de `correos-desde-canvas.md`: `BrandedMailMessage`, línea de adelanto, botón de tinta.

- **`GuestFormRequest`, rehecho.** Dice qué se va a pedir. Si el producto tiene invitación, el primario
  es «Compartir la invitación» (lleva al bloque del formulario) y el fantasma «Rellenarlo yo».
- **Aviso de la víspera (nuevo).** Al titular, **solo si queda algo por hacer**: fichas incompletas,
  respuestas por repasar, niños sin resolver o saldo a pagar en el parque según el libro.
  - Trae la cifra («12 de 20»), por qué no es grave («si vienen con un adulto, entran») y el saldo.
  - Programado a diario. Idempotente por reserva, con una marca escrita **por el constructor de
    consultas** (no toca `updated_at`, §1.3·2).
  - `ShouldQueue` (`PAY-14`).
- **Inventario: 25 → 26.** Los censos de `MailInboxLineTest` y del molde se actualizan.

### 4.10 C · Contrato de la API (T4)

API-first: la invitación pública y la gestión del anfitrión se exponen en `/api/v1` con el mismo
dominio que la web.
- **Público** (sin sesión, por token): la invitación, contestar, y con el recibo, datos y compañía.
- **Anfitrión**: `GuestForm` gana `invitation` (enlace, tema, personalización, resumen y respuestas
  pendientes con su ficha propuesta), su escritura, y la adopción en el `PUT` existente con una lista
  `adopt` de ids **fuera de `guests`** (§1.3·13).
- Nombres y esquemas se fijan en `openapi/v1.yaml` **antes** del código de la T4. `additionalProperties:
  false` en todos los objetos nuevos.

### 4.11 White-label

- Ni un nombre, color o dibujo de PlayJump: temas abstractos con tokens de la instalación, textos
  en `lang/` genéricos y datos del panel.
- La imagen de vista previa por tema es un **fichero de la instalación** (hueco del kit, `#286`); sin
  él, el `og_image` del sitio.

## 5. Impacto en invariantes

| ID | Cómo |
|---|---|
| `RGPD-01` | `anonymize()` borra invitaciones y respuestas por tabla, y la purga las alcanza por cascada (§4.4). La página genérica (404) cubre al anonimizado. |
| `RGPD-02` | Rastro de invitación, respuestas, rotación y adopción **sin nombres de niños**. |
| `RGPD-03` | El token caduca con `guestFormLinkExpiresAt()`. Las respuestas se podan a los 14 días de la visita (V3), dentro de `model:prune`. |
| `RGPD-04` | `no-store` en la página, el `.ics` y la API pública. |
| `RGPD-05` | Sin mapa embebido: «Cómo llegar» es un enlace que el visitante pulsa. |
| `RGPD-06` | El token **no** es una credencial de la cuenta: `revokeAllAccess()` no lo rota (rotarlo rompería enlaces ya repartidos en grupos de clase). La palanca es la del operador. Declarado. |
| `SEC-04` | Plazo, lista completa, pagado y cancelado se re-comprueban **bajo lock** al escribir, nunca solo al pintar. |
| `SEC-06` (espíritu) | Superficie pública que escribe: honeypot, Turnstile y limitadores. |
| `SEC-07` | `address.maps_url` pasa por `safeExternalUrl`. `host_line` y el nombre se escapan al pintar y rechazan URLs. |
| `PERF-02` | La página lee `settings` por el composer memoizado. Ninguna llamada a terceros al pintar. |
| `PAY-14` | El aviso de la víspera va en cola. |
| `PAY-*` y `AFORO-*` | **Ninguno.** Un padre no mueve dinero ni aforo, y `OrderCreator` no se toca. El dinero se mueve al guardar el anfitrión por el camino de siempre. |

## 6. Plan de ejecución y verificación

Cada tanda es un commit verde con doc al día. Tests **justos**: una guarda por tanda más los casos de
dominio. Arnés de mutación **solo en T4 y T6**. Sonda de navegador a 390 y 1280 en T1, T2, T3, T5 y T6.

| Tanda | Qué | Verificación |
|---|---|---|
| **T1** | Piel del formulario (§4.2). Re-medir F-01…F-08 antes de tocar. | `GuestFormSkinTest` (cero `--zone-1` en `.gf-*`, tallas y radios en escala, una sola sombra difusa, política ≥48) · capturas antes y después · suite. |
| **T2** | Agrupar, barra pegada, extras en filas, pegado (entrada de Vite propia). | `node --test` del pegado · caso de render (grupos, barra, `status_missing`) · sonda: toques para 20 invitados. |
| **T3** | Piel del justificante (§4.3). | `GuardianSkinTest` · sonda de los cinco desenlaces a 390. |
| **T4** | Migraciones, modelos, `PartyGuests`, `PersonNameKey`, reglas §4.5, catálogo (interruptor, casilla y sus guardas), predicado del cajón y del pedido manual, `GuardianPlaces`, firmador (vínculo y excepción de plaza), rotación en el panel, contrato y API, supresión, purga y poda. | Casos de dominio: lista completa, emparejado 0/1/varias, **nombre repetido sin anunciarlo**, columna de nombre exigida, plazo, cancelada, anonimizada, 404 uniforme, recibo caducado, firma atada con la lista completa, poda con el justificante conservado · paridad `keyFor` ↔ `PersonNameKey` · **concurrencia sobre InnoDB**: N «sí» simultáneos sobre la última plaza → exactamente 1, **visto fallar sin el lock** · arnés de mutación · `ApiContractTest`. |
| **T5** | La página pública (§4.6), temas (tres renderizados para que elija el owner), vista previa, `.ics`, cabeceras, aviso de privacidad. | Caso «hoja en blanco» (la respuesta no contiene otros nombres, y un nombre repetido da el mismo desenlace) · cabeceras · `.ics` con `TZID` · sonda de G1–G3 y desenlaces · **el owner se manda el enlace a su teléfono** (vista previa y calendario). |
| **T7** | Correos (§4.9). | Censos del molde · render de los dos correos · idempotencia de la víspera · «no se envía con todo hecho». |
| **T6** | Aterrizaje (§4.7), puerta, hoja y panel (§4.8). | **Caso intercalado**: pintar → llega un «sí» → guardar → la respuesta sigue pendiente y nada se borra · adopción solo de `adopt[]` · quitar un «sí» · descarte al quitar la ficha · suelo con «sí» · puerta ≤28 consultas · hoja con pendientes · arnés de mutación · escenario `guest-count` de `purchase:verify-oversell` (cambia el suelo) · sonda. |

⚠️ **Del `CRITICAL_RE`**: con §4.8, el padre no escribe `order_items`, el suelo cambia en
`GuardianPlaces` y la casilla en un predicado del producto, así que **`OrderCreator` no se toca**. Si una
tanda acaba tocando `GuestCountAdjuster` (el borde de §7.1·5), `PostFormAddons`, `OrderCreator` o
`MixedPartySurcharge`, el push pide `VERIFY_CONC=1` con sus verificadores.

⚠️⚠️ **CORRECCIÓN, medida en la T4·1: esta sección decía «T4 y T6 no tocan ningún fichero de la lista» y
es FALSO.** `product_addons.show_in_invitation` (D12) es una columna del PIVOTE, y el pivote es
**`app/Domain/Booking/Models/ProductAddon.php`**, que el `CRITICAL_RE` nombra una por una. Lo paró el
gate al empujar la T4·1, no una lectura. ▶ Así que **toda unidad de la T4 que toque el pivote —la T4·1 por
su cast y la T4·3 por las tres listas blancas— empuja con `VERIFY_CONC=1`** y sus verificadores. No es
ceremonia inútil: el pivote gobierna cuántas unidades de un complemento entran en una reserva, y el gate
no distingue «solo añadí un cast» de «cambié el resolvedor». *Una spec que declara qué NO va a tocar está
haciendo una predicción, y ésta se comprobó equivocada.*

⚠️ **Coordinación** (`CARRIL-SPA.md` §5, §7.2·R14): `.gf-*` es de este carril. **No están repartidos**
y se anuncian en `ESTADO.md` antes de tocarlos: `.guardian__*`, `resources/views/reservation/**`,
`focused-layout`, `lang/*/guestform.php`, `lang/*/guardian.php` y `vite.config.js`. `focused-layout`
carga `landing.css` y después `site.css`: a igual especificidad **ganan nuestras reglas**, pero una regla
genérica de `landing.css` con más especificidad sigue aplicando (la trampa de `#535`, vista al revés).

Doc por tanda: `POSTFORM-INVITADOS.md` (T1, T2 y T6) · `waiver-por-reserva.md` (T3 y T4) ·
`correos-desde-canvas.md` (T7) · `INVARIANTES.md` si una fila cambia · y esta spec, que gana una
sección de ejecución por tanda.

## 7. Revisión adversarial

### 7.1 Primera pasada, al escribirla (2026-09-13)

Ataques contra el propio diseño, con su respuesta. **«Medido»** = verificado contra el código.

1. **«El anfitrión guarda con la página abierta y borra lo que puso un padre.»** No puede: el padre no
   escribe `guest_data` (§3.1c). *Medido*: `submitGuestForm` sustituye la lista entera, y por eso se
   descartó (a).
2. **«Cada respuesta deja obsoletos los extras del anfitrión.»** No: la respuesta no escribe
   `order_items`, que es donde vive el testigo. *Medido*: `versionOf` = `updated_at` de la reserva.
3. **«Carrera: el anfitrión rellena la última ficha mientras entra un "sí".»** La lista completa se
   mide bajo el lock de la invitación, pero el guardado del anfitrión no lo toma. Resultado posible: una
   respuesta más de las que caben, y §4.7 la **dice**. No se sobrevende nada: lo que manda es
   `quantity`, y el padre no la mueve.
4. **«Un "sí" con la lista completa impide firmar a su propio padre.»** Cerrado por la excepción del
   firmador (§4.5·7). *Medido*: el tope es `freeIn < 1` bajo lock.
5. **«Bajar invitados borra la ficha de un niño que confirmó.»** El suelo cuenta los «sí» (V4). Queda
   un borde: el suelo cuenta **plazas**, no **posiciones**, y hoy se descartan las filas del final
   (*medido*, `filledFormsBeyond`). Un «sí» adoptado en una fila final puede caer si se baja justo por
   encima del suelo. ▶ **Se resuelve en la T6** con una de dos: descartar primero las fichas vacías, o
   reordenar las adoptadas al principio al adoptar. Exige tocar `GuestCountAdjuster` (`CRITICAL_RE`),
   así que se decide **midiendo** al abrir la tanda.
6. **«Emparejar por nombre funde a dos niños distintos.»** Solo con **una** candidata, y la ficha
   propuesta no se guarda sin que el anfitrión la vea y pulse Guardar (V1).
7. **«El enlace se enumera.»** 71 bits, limitadores, y ninguna información de otros invitados.
8. **«El parque mueve la fecha y los padres no se enteran.»** Cierto y fuera de alcance. La página
   siempre dice la fecha vigente y el `.ics` descargado no se actualiza. Candidato a una línea en el
   aviso de la víspera.
9. **«Web Share falla en escritorio.»** Siempre hay «Copiar enlace».
10. **«El menú del enganche puede no estar en la reserva.»** Entonces no hay bloque (D6: sin dato, sin
    bloque).
11. **«Un producto sin `celebrant`/`age` no tiene nombre para la vista previa.»** No se puede
    compartir hasta que el anfitrión lo escribe (§4.5·2), y la pantalla lo dice.
12. **«Se apaga el interruptor con invitaciones repartidas.»** Siguen abriendo (V5). Lo nuevo deja de
    ofrecerse.

### 7.2 Segunda pasada, a petición del owner (2026-09-13)

Seis ángulos: afirmaciones sobre el código · seguridad · RGPD · concurrencia · fronteras de módulo ·
producto. **16 hallazgos: 14 cambian la spec** (ya aplicados en su sección) **y 2 se verificaron sin
cambio.** Tres eran **afirmaciones de la primera versión que el código desmiente** (R4, R5, R6).

| # | Tipo | Hallazgo | Qué cambió |
|---|---|---|---|
| R1 | 🔴 Privacidad | Rechazar un nombre repetido con «ya nos habéis contestado por Hugo» **le confirmaba a cualquiera con el enlace quién va a la fiesta**: bastaba probar nombres. Rompía la hoja en blanco por el mensaje de error. | Nombre repetido **aceptado en silencio** con el mismo desenlace; no ocupa plaza; lo ve el anfitrión (V6, §4.5·5). Índice no único. Criterio nuevo en §2.1. |
| R2 | 🔴 Diseño | La spec contaba «fichas con nombre» y **el esquema no tiene un tipo nombre**: las columnas las crea el panel y `name` es solo la clave del defecto (*medido*, `GUEST_FIELD_TYPES`). | La columna de nombre es **la primera `text`**, y el interruptor exige que exista (§4.4). |
| R3 | 🟠 Contrato | La adopción viajaba como `reply_id` **dentro de cada fila**, y la fila es un mapa abierto de respuestas (*medido* en `openapi/v1.yaml`): chocaría con una columna que el panel llamara igual. | Lista `adopt[]` **fuera de `guests`**, en la web y en la API (§4.7, §4.10). |
| R4 | 🔴 Afirmación falsa | «El servidor ignora la marca que llegue» obligaba a tocar **`OrderCreator`**, que escribe la marca y está en el `CRITICAL_RE` (*medido*, `OrderCreator` junto a `offersGuardianAuthorization()`), mientras §6 decía que ninguna tanda tocaba la lista. | Un predicado del producto para el cajón y el pedido manual; `OrderCreator` intacto; una marca forjada solo manda el correo del justificante suelto, que ya funciona en cualquier pedido pagado (§4.8). |
| R5 | 🟠 Afirmación falsa | «Módulo estático con `node --test`»: **no se habría ejecutado nunca**, porque `test:js` solo recoge `resources/js/**/*.test.js` (*medido*, `package.json`). | El módulo va en `resources/js/guest-form/` con una entrada de Vite propia, avisando del fichero compartido (§4.2). |
| R6 | 🔴 Afirmación falsa por omisión | Las FK nuevas **no declaraban acción**: la purga borra pedidos por tabla (*medido*, `PurgeCustomerData`), y la poda de V3 chocaría con justificantes que se conservan años. Además `User::anonymize()` no puede importar los modelos nuevos. | `cascadeOnDelete` en invitación y respuestas, `nullOnDelete` en el vínculo del justificante, supresión por tabla y la poda en `model:prune` (§4.4). |
| R7 | 🔴 RGPD | La página y G2 **no tenían aviso de privacidad**, y G2 recoge alergias de un menor (art. 9) que va a leer **un tercero**, el anfitrión. | Aviso sin casilla que dice quién lo ve y cuándo se borra, con la política como control de 48 (§4.6). |
| R8 | 🟠 Producto | Compartir se cerraba con el plazo de respuesta, y la información de la fiesta **hace falta justo ese día**. | Se comparte hasta la fiesta; solo se cierran las respuestas (§4.5·2). |
| R9 | 🟠 Seguridad | `honoree_name` y `host_line` son **texto libre publicado bajo el dominio del parque**: una invitación podía decir «paga el regalo en este enlace». | Sin URLs ni direcciones de correo; el teléfono solo desde la cuenta (§4.5·12). |
| R10 | 🟡 Seguridad | Un 404 para token inexistente frente a un 410 para cancelada o anonimizada era un **oráculo**: decía si un token existió. | Un solo desenlace y el mismo 404 (§4.5·12). |
| R11 | 🟠 Producto | Con V4 un «sí» pendiente cuenta en el suelo, y **no había forma de quitarlo**: el anfitrión no podía bajar invitados por debajo de una respuesta que no quiere. | «No lo apuntes» sobre un «sí» pendiente (§4.7). |
| R12 | 🟡 UX | Con Turnstile, «un toque» no es literal. | Modo gestionado o invisible, y se mide en la T5 (§4.6). |
| R13 | 🟠 Correctitud | Un `.ics` en UTC **adelantaría la fiesta una o dos horas** en el móvil del padre: las franjas son hora de pared (la trampa de `#426`). | `TZID` de la zona del parque (§4.6). |
| R14 | 🟡 Coordinación | Vistas de `reservation/`, `focused-layout`, los dos diccionarios y `vite.config.js` **no están repartidos** en `CARRIL-SPA.md`. | Se anuncian en `ESTADO.md` antes de tocarlos (§6). |
| R15 | ✅ Sin cambio | ¿El plazo de respuesta podía quedar cerrado para siempre en un pack sin número editable? | No: `isOpenFor()` solo pide reserva pagada, viva y con franja, y `isWithinWindow()` es el corte horario (*medido*). Es la puerta que se quiere. `occupiedMinutes()` incluye `extra_minutes` (*medido*). `#580`–`#585` no contradicen el botón en tinta. |
| R16 | ✅ Sin cambio | ¿El techo de la puerta permite leer respuestas? | Es 28 consultas (*medido*, `GateProfileTest`); por lotes cabe. |

## 8. Pendiente del owner (no bloquea el código)

- **Textos legales** del justificante: decisiones 19 (el texto real del descargo) y 20 (el conflicto
  con las condiciones de uso).
- **El texto final de G3** y **el aviso de privacidad** de la invitación (§4.6).
- **Los tres temas**, elegidos viéndolos renderizados en la T5, y la imagen de vista previa de cada uno.
- **La dirección exacta** del parque (decisión 5 del canvas): sin ella, «Dónde» enseña el nombre y
  «Cómo llegar».
- **Configurar**: los dos packs en `optional` + «Invitación digital»; «Menú 1» y «Menú 2» con «Se
  enseña en la invitación». Paso de despliegue, no código.
- **Vetar o aceptar V1–V6.**

## 9. Revisión y decisión

- 2026-09-13 · Diseño leído del canvas y contrastado con el código (§1.2 y §1.3). Dos rondas de
  preguntas al owner: D1–D17.
- 2026-09-13 · Primera revisión adversarial propia (§7.1): deja **un borde abierto con fecha** (§7.1·5,
  en la T6).
- 2026-09-13 · **Segunda revisión adversarial** a petición del owner (§7.2): 16 hallazgos, **dos de
  privacidad** (R1, R7) y **tres afirmaciones de la primera versión que el código desmentía** (R4, R5,
  R6); todo aplicado en su sección.
- Entrada: `DECISIONES #569`.

## 10. Ejecución

### 10.1 T1 · la piel del formulario — EN EL ÁRBOL (2026-09-13, `DECISIONES #570`)

**Hecho.**
- La hoja `.gf-*` entera: papel liso y columna de 640; el resguardo declara `data-surface="ink"`; UN
  componente de aviso (`.gf-notice`, con tono `--attn`/`--err`, que sirve sobre tinta y sobre papel
  porque mezcla su tinte con `var(--bg)` dentro de la regla); cubo de 48 con «· Lista» dentro del
  rótulo; el régimen solo cuando difiere; «(opcional)» en vez del asterisco; la política como control
  de 48; el pie con sus enlaces a 48; en escritorio las celdas del resguardo en fila y el título a 36.
- **Rol nuevo `--done` · `--on-done` · `--done-ink`** en `landing.css`, con defecto en `--ok` (una
  instalación sin paquete no cambia) y Lima en el paquete de este cliente.
- Los avisos de rechazo (número de invitados y extras) **suben del pie de la página al resguardo**:
  debajo del pie no los veía quien volvía arriba tras guardar.
- **Cinco cadenas y no tres**: además de las tres del canvas, `optional` y `privacy_link`.

**Medido** con `scripts/sonda-enlace-firmado.mjs` (nueva y versionada) a 390, antes → después:

| | Formulario | Justificante (hereda el molde) |
|---|---|---|
| Nodos de texto < 15 px | 164 → **20** (todos rótulos de 12, su nivel) | 40 → 15 |
| Controles < 48 px | 3 → **0** | 3 → 3 (T3) |
| Sombras | 3 → **1** (el aviso flotante) | 1 → 0 |
| Piezas con color de zona | 6 → **0** | 2 → 1 (el asterisco, T3) |
| Radios distintos | 7 → **3** | 6 → 3 |

**Desviaciones declaradas.**
- **«Guardar» y «Siguiente» van en el SECUNDARIO del sistema (Azul Muro), no en tinta.** La decisión 13
  del canvas es anterior a `#539` (12-09), donde el owner pasó el secundario de tinta a Azul Muro con
  el conflicto delante. Se aplica lo vigente y **se le enseña renderizado**.
- La familia `.btn` no se toca (es de la web): su radio lo pone el paquete; aquí solo sube la letra a
  `--fs-button`. Campos a 48 y no a 52. Relleno del resguardo 20/28: la escala no tiene 24.

**Hallazgos.**
1. ⚠️⚠️ **`focused-layout` NO cargaba `client.css` desde que existe**: el post-form y el justificante
   se pintaban con los colores del PRODUCTO en una instalación con paquete, sin fallar. Lo vio la
   **captura** (lo hecho salía verde en vez de Lima). Arreglado, y la guarda del paquete
   (`ClientThemePackageTest`) mira ahora los dos layouts.
2. **Regresión propia cazada por la captura**: al declarar la superficie del resguardo, `.guardian__what`
   —que invertía colores a mano— pintaba el producto **tinta sobre tinta** en el justificante.
   Arreglado leyendo los tokens de la superficie.
3. **Para la T3**: el justificante escribe la visita con el fin de la FRANJA («17:00 – 18:00» en una
   fiesta de dos horas) mientras el post-form dice «17:00–19:00». Es la trampa de `#426`
   (`occupiedMinutes()`, nunca `slot.end_time`).

**Guarda**: `GuestFormSkinTest` (4 casos: color de zona, sombras, radios y tallas sobre el bloque SIN
comentarios, y el marcado).

**Paso de despliegue**: las tres líneas de `--done*` al `client.css` de producción y a la rama
`cliente/playjump`.

### 10.2 T2 · muchos invitados — EN EL ÁRBOL (2026-09-13, `DECISIONES #571`)

**Hecho** (el dibujo es la 2b aprobada, con los rótulos de la 3a, que es posterior):
- **Fichas agrupadas**: las pendientes arriba —con el rótulo «Falta algo · N» si también hay listas— y las
  listas plegadas en un `details` NATIVO («N fichas ya listas»), que se abre sin JS y cuyos campos viajan en
  el POST aunque esté cerrado. En solo lectura, la lista en su orden.
- **«Falta :field»** en la ficha a medias: la primera columna obligatoria vacía, y solo si ya tiene algún
  dato. Rojo de TEXTO con un token nuevo, `--err-ink` (defecto `--err`; el paquete, Rojo 800).
- **La barra de guardar PEGADA** (`sticky`, 85 px medidos) con «Fichas completas» y «Guardar». Su sombra
  es un peso nuevo de la familia de mobiliario, `--shadow-nav-dock` (hacia ARRIBA), declarado en
  `ShapeScaleTest`; el aviso de guardado sube por encima y pasa a `--shadow-nav` (con el paquete,
  `--shadow-float` es una sombra dura, `#265`). `scroll-padding-bottom` para que el foco no quede debajo.
- **Extras en FILAS de una tarjeta**, los cerrados al final y con su motivo dentro de la fila, la cuenta
  «N elegidos», y el nombre y el precio como `summary` de «Más info» (el stepper fuera: un control dentro
  de un `summary` no es HTML válido).
- **El pegado de la lista** en un `dialog` nativo: lee viñetas y numeraciones, y una lista de una sola
  línea con comas; reparte **solo sobre las fichas vacías** en el orden de sus posiciones; dice cuántos
  nombres ha leído, a cuántas fichas van, cuántas no se tocan y cuántos no caben; y **pegar no guarda**, y se
  dice. Al aplicar, abre la primera ficha y lleva el foco a la edad.
- **El JS de la página es un MÓDULO** que importa `public/js/guest-form/logic.js`: estático, sin Vite (la
  página no carga el manifiesto, como `site.css`), con sus casos de `node --test` en
  `resources/js/guest-form/logic.test.js`. El número de invitados gana su stepper.
- 17 cadenas nuevas en es/en/fr, y `count_warn_discard` con singular y plural («de 1 fichas»).

**Decisiones de ejecución**:
- **«Abrir la primera pendiente» se retira**: la 3a no lo dibuja y la primera pendiente ya abre sola arriba.
- **La columna de nombre es la primera de tipo `text`** (R2 de §7.2, aplicada ya aquí).

**Hallazgos**:
1. ⚠️⚠️ **Integridad**: pintar las pendientes ARRIBA reordenaba a los invitados en cada guardado —PHP
   conserva el orden en que llegan los campos y `sanitizeGuestData()` hacía `array_values`—. No mezcla
   datos, pero de la posición cuelgan el régimen de cada ficha, las que se pierden al bajar invitados y la
   hoja de sala. Defensa en el DOMINIO (`ksort` si todas las claves son enteras), con caso HTTP y
   **medido en navegador**: la página llega con las claves desordenadas y las 20 fichas quedan en su sitio.
2. ⚠️⚠️ **Defecto en producción desde `#444`** (08-09): el número de invitados vive en el RESGUARDO, fuera del
   `<form>`, y **el navegador no lo enviaba**: cambiarlo no hacía nada. Los casos mandaban el POST a mano y
   la verificación en navegador de su spec estaba pendiente. Arreglado con `form="gf-form"`, guarda en
   `GuestCountSurfacesTest` y **verificado en navegador**: de 20 a 19 con el stepper, aviso y guardado.
3. Trampa pagada: el caso de solo lectura aseveraba nombres de clase por subcadena y **el script del
   módulo los nombra** para buscarlos; se asevera el ELEMENTO (la trampa de `#553`, con el JS de prosa).
4. **Una guarda cambió de premisa y se reescribió** (el precedente del `SlotOfferTest` de `#324`):
   `GuestCountSurfacesTest` prohibía el `|` en `count_warn_discard` porque «el JS no sabe pluralizar». Ahora
   sabe, así que vigila la PAREJA: si la cadena trae dos formas, el script las resuelve con `choice()`.

**Medido** con la reserva de 20 invitados (3 listas, 17 vacías) a 390: alto de la página **5.303 → 4.648**;
pegar 17 nombres numerados → 17 fichas con «Falta edad», foco en la edad, **cero errores de JavaScript**;
guardar y recargar → **20/20 en su posición**; filas de extras **72–73 px** (el canvas dice 69: la diferencia
es el suelo táctil de 48 del desplegable).

**Guardas**: `GuestFormManyGuestsTest` (5) · `logic.test.js` (16) · `scripts/mutar-postform-t2.py` (**5/5**,
con control). ⚠️ **Sin guarda automática**, a sabiendas: el `sticky` de la barra y el alto de las filas, que
son geometría y se miden con la sonda.

### 10.3 T3 · la piel del justificante — EN EL ÁRBOL (2026-09-17, `DECISIONES #572`) · ✅ del owner en vivo · SIN DESPLEGAR

**Hallazgo que ordenó la tanda** ⚠️⚠️ **defecto en producción desde el octavo despliegue (16-09)**: la T2 hizo
`.gf-savebar` pegada y en FILA para el post-form, y el justificante metía DENTRO de esa barra dos párrafos
legales, el anti-robot y el botón. Medido a 390 × 844 con captura de VENTANA: la barra ocupaba **401 px**
pegada abajo —tapando el formulario mientras se rellena— y «Firmar la autorización» se salía **65 px** de la
pantalla (x 236 → 455). Se podía firmar, pero nadie lo habría dado por bueno, y no lo veía ningún test: la
T2 midió el justificante solo con la sonda de página entera, que cose lo pegado. **Pide despliegue.**

**Hecho** (J-01…J-08 de `doc/formulario.md`):
- **El cierre**: lo legal baja al flujo (`.guardian__close`) y la barra lleva SOLO a quién se autoriza y
  «Firmar» a 56. El nombre sigue a lo que se teclea (y al selector de menores a cargo, que rellena por
  código); sin JS aparece al volver con errores; sin nombre, el botón ocupa la barra. Un nombre largo se
  corta con puntos. El rótulo largo queda de nombre accesible del botón (contiene al visible).
- **El descargo ENTERO** (sin `max-height` ni scroll propio), a 16 y en tinta.
- **Cinco desenlaces, cuatro tonos**, con `.gf-notice` y su título; nace `.gf-notice--ok`. El rechazo del
  dominio comparte tono y título con el anti-robot: «No se ha registrado nada».
- **La casilla del sistema** (`.guardian__check`): el propio `input` con `appearance: none`, 24, borde de 2,
  y marcada tinta con el ✓ en papel —dos bordes girados: un SVG incrustado traería un color crudo—.
- **El ordinal en cubo de 40** · **el logotipo como fichero** · bordes continuos · lo OPCIONAL marcado en
  vez del asterisco · el error delante de la ayuda, a 15 y en `--err-ink` · la política como control de 48,
  también en la pantalla sin formulario · el anti-robot en su caja con rótulo (`guardian.antibot_label`).
- **La hora**: `AuthorizableReservation` cambia `startTime`/`endTime` por `timeWindow`, compuesta por
  `OrderItem::displayTimeWindow()`. La vista era su único consumidor (medido).
- Las reglas `.guardian__*` y `.gf-group__num` **se mudan al bloque de la hoja**: fuera de él ninguna escala
  las miraba, y por eso el ordinal era un círculo con mono 12 y el descargo iba a 13.
- Defecto heredado que enseñó la captura: el `<form>` no llevaba `.gf-form` y los tres pasos iban PEGADOS.

**Medido** con `sonda-enlace-firmado.mjs` y tres sondas locales de ventana, a 390, antes → después:

| | Antes | Después |
|---|---|---|
| Alto de la barra de firmar | 401 px | **85** (la cifra del canvas) |
| Borde derecho del botón (ventana de 390) | 455 | **370** |
| Nodos de texto < 15 px | 15 | **7** (todos rótulos mono de 12) |
| Piezas con color de zona | 1 | **0** |
| Radios distintos | 3 (con un `50%`) | **2** (16 y 10) |
| La hora de una fiesta de 2 h | «17:00 – 18:00» | **«17:00–19:00»** |

Los cuatro tonos dan el valor exacto del canvas (`#DFE9D6` · `#D5EAEE` · `#F4EDCF`; el error en Rojo 800,
`#C83912`). Recorrido en navegador: barra con nombre larguísimo, casilla marcada pulsando la ETIQUETA, error
de mayoría de edad, «texto nuevo», «firmada» y «ya estaba»; **cero errores de JavaScript**. La hermana no se
mueve: A/B en la misma página con el titular en fila y en bloque, idéntico al píxel.

**Desviaciones declaradas**: «Firmar» va en el SECUNDARIO (`#539`), como «Guardar» en la T1 · §4.3 decía
«cero cadenas nuevas» y son **siete** (cuatro títulos de desenlace, `submit_short`, `bar.minor`,
`antibot_label`) más cuatro retocadas: la receta del aviso pide título, como en la T1 · no se añade el
rótulo «Atajo, no un campo» del dibujo · campos a 48 y no a 52 (la familia es de la web).

**Trampas pagadas**: (1) el script nombraba `data-guardian-pick-select` y el caso «sin sesión no hay
selector» asevera por subcadena: ese tramo solo se EMITE con menores (`#553`, otra vez). (2) «Ya estaba» no
sale repitiendo al mismo firmante —es idempotente a propósito—: hace falta el OTRO progenitor; lo midió mal
la sonda, no el dominio. (3) Un filtro de test que no ejecuta nada también sale ≠ 0: la mutación se dio por
buena solo tras ver el mismo filtro ejecutar 1 caso en verde.

**Guarda**: `GuardianSkinTest` (10 casos, vistos en ROJO antes del arreglo; la aserción de los tonos, nacida
después, mutada a mano con `site.css` restaurado byte a byte). ⚠️ **Sin verificar**: el widget real de
Turnstile en navegador (en local no hay claves; el marcado lo cubre el caso) y un teléfono de verdad.

`[DECIDIDO owner, 2026-09-17]` **la piel está bien**: la vio en vivo en `localhost:8081` («perfecto»), con el
rótulo del anti-robot «Comprobación de seguridad · Cloudflare» delante. ▶ **Paso de despliegue**: solo
código, sin migraciones y sin tocar `client.css`; producción pide etiqueta (`/release`, guarda 8 de `#624`) y
noche o parque cerrado (`#594`).

### 10.4 T4 · la invitación digital — SE PARTE EN SEIS UNIDADES VERDES

`§6` la describe como una tanda, y **es varias sesiones**: dos tablas, dos interruptores, un contrato de
módulo, un verificador de concurrencia sobre InnoDB y el contrato de la API. Se parte para poder empujar
pronto (`CONVENCIONES §10.5`), en orden de dependencia y cada una verde por su cuenta:

| | Qué | Estado |
|---|---|---|
| **T4·1** | Esquema, modelos y el normalizador compartido (§4.4) | ✅ `#573` |
| **T4·2** | Las reglas del dominio (§4.5) + el verificador de concurrencia | ⬜ |
| **T4·3** | Catálogo y embudo: los dos interruptores y `funnelGuardianMode()` (§4.8) | ⬜ |
| **T4·4** | `PartyGuests`, `GuardianPlaces`, el firmador y la rotación | ⬜ |
| **T4·5** | RGPD: supresión, purga y poda (§4.4) | ⬜ |
| **T4·6** | El contrato de la API y sus endpoints (§4.10) | ⬜ |

⚠️ **Desviación declarada sobre §4.10.** Dice que los nombres y esquemas se fijan en `openapi/v1.yaml`
**antes del código de la T4**. Se respeta su INTENCIÓN —que la API no se retro-ajuste a lo que hizo la
web— pero no su letra: el `yaml` se escribe en la T4·2, cuando la forma del dominio está decidida y
**antes de una sola línea de código de API**. Fijar el contrato antes del modelo de datos habría descrito
una forma que aún no existía.

#### 10.4.1 T4·1 · los cimientos — EN EL ÁRBOL (2026-09-17, `DECISIONES #573`)

**Hecho.**
- **`party_invitations`** (una por reserva, `order_item_id` único, token opaco de 12 base62) e
  **`invitation_replies`** (`child_key` normalizada, `data`, `companion`, y el par
  `adopted_at`/`dismissed_at` que solo mueve el anfitrión).
- Los interruptores **`ticket_types.guest_invitation`** y **`product_addons.show_in_invitation`**, los dos
  apagados: la migración no cambia la conducta de ninguna instalación.
- El vínculo **`guardian_authorizations.invitation_reply_id`**, `nullOnDelete`.
- **`Platform\Services\PersonNameKey`**: la normalización sube desde `GuardianAuthorization::keyFor()`,
  que delega. Sube porque **Booking** necesita la misma clave para emparejar y no puede mirar a Identity.
- `InvitationReply` es **`Prunable`** a los 14 días de la visita (V3) y queda **registrado en la lista
  explícita de `model:prune`** en el mismo commit: fuera de ella no se poda nunca.

**Lo que NO entra, y por qué.** El contrato `PartyGuests` y `TicketType::guestNameFieldKey()` se aplazan a
la unidad que los consume (T4·4 y T4·2). Un contrato sin consumidor **no lo puede verificar**
`ModuleContractsTest`, que sustituye el doble y comprueba que el consumidor cambia de conducta: escrito
hoy sería ceremonia con una guarda decorativa.

**Guardas**: `PersonNameKeyTest` (7, Unit puro por `CONVENCIONES §3.ter`) · `PartyInvitationSchemaTest`
(8): el único por reserva, la cascada, el repetido aceptado en silencio, el `SET NULL` que salva la prueba
legal, la poda por plazo con el comando REAL, el registro en `model:prune`, la lista blanca y los dos
defectos apagados.

**Trampas pagadas en esta unidad** —las cuatro salieron del instrumento, no del código—:
1. Una aserción mía afirmaba que `'Pérez'` y `'Peréz'` daban claves distintas. **Es al revés y es el
   contrato**: una tilde mal puesta no puede convertir a un niño en otro. El caso quedó, invertido.
2. `guest_invitation` recién creado vale `null` en memoria: el defecto lo pone la BD y hay que releer.
   Aseverar sobre la instancia decía que el defecto no existía.
3. El payload manipulado **lanza** en vez de descartar en silencio: `preventSilentlyDiscardingAttributes`
   (`SEC-10`) está activo fuera de producción. La protección es la misma; el caso asevera lo que ocurre.
4. Larastan cazó la invarianza de `Builder<static>` en `prunable()` — el mismo patrón que
   `GuardianAuthorization` tiene en la línea base, y que aquí no se puede añadir porque solo encoge.
5. Un modelo nuevo **tiene que declarar su alias de morfo**: `MorphMapTest` lo caza, y es lo que hace
   que el audit guarde `invitation_reply` y no un nombre de clase que se rompe al mover el fichero.
6. ⚠️⚠️ **El push lo paró el gate de concurrencia**, y tenía razón: el cast de `show_in_invitation` vive
   en `ProductAddon.php`, que está en el `CRITICAL_RE` — justo lo que §6 predecía que T4 no tocaría.
   Corregido allí. Esta unidad empujó con `VERIFY_CONC=1` tras los verificadores sobre MySQL.

**Arnés de mutación**: `scripts/mutar-invitacion-t41.sh`, **9/9 muerden con CONTROL en verde**, y el
árbol restaurado byte a byte (sha1). Se corrió porque las políticas de borrado **no fallan solas**: una
FK mal puesta no rompe nada hasta el día en que la purga o la poda corren en producción.

⚠️⚠️ **La primera pasada salió 5/9, y las cuatro flojas eran de la GUARDA, no del código.** Es el valor
del arnés y merece quedar escrito:
- **La segunda cascada no estaba ejercida.** Con `invitation_replies.party_invitation_id` en `RESTRICT`
  no moría ningún caso, porque al borrar la RESERVA las respuestas caían igual por su otra clave
  foránea. Nace `test_deleting_the_invitation_takes_its_replies`.
- **La lista blanca se probaba con un payload de dos claves.** Añadiendo `adopted_at` a `$fillable` el
  caso seguía en verde: la excepción saltaba igual por `dismissed_at`. Hoy se asevera además sobre
  `getFillable()`, clave a clave, y el arnés muta las dos.
- ⚠️⚠️ **Y el respaldo del normalizador se probaba con un ejemplo FALSO.** El caso usaba cirílico como
  «alfabeto no latino que se vacía», y **`Str::ascii()` sí lo transitera**: «Александр Петров» →
  `aleksandr petrov`. Medido en el contenedor sobre nueve escrituras, los que de verdad se vacían son
  **chino, japonés, coreano, tailandés, hebreo y emoji**; el griego y el árabe también se transliteran.
  ▶ **La afirmación venía heredada del docblock de `GuardianAuthorization::keyFor()`** («un nombre
  escrito íntegramente en un alfabeto no latino se convertiría en `''`»), donde llevaba desde `#328`.
  Corregida en los dos sitios con lo medido. *Una prosa heredada que nadie midió es una afirmación, no
  un hecho — y un caso escrito sobre ella prueba lo que la prosa creía, no lo que el código hace.*
- El propio arnés mentía al final: contaba el CONTROL como un mutante y decía «9/10» con las diez
  líneas en verde, saliendo con código 1. Los controles se cuentan aparte.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Vestir el formulario de CELEBRACIÓN o el JUSTIFICANTE · la INVITACIÓN digital de un cumpleaños · el «sí / no podemos» de un padre · el pegado de nombres · «¿vas tú con él?»»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/celebracion-e-invitacion.md`.

- **`docs/specs/celebracion-e-invitacion.md`**
- 🟦 **SPEC REVISADA dos veces · T1 y T2 EN EL ÁRBOL (`#570`–`#571`, §10.1–§10.2:
- ⚠️ `focused-layout` NO cargaba `client.css`;
- ⚠️⚠️ **el orden de la PÁGINA ya no es el de las POSICIONES** —`sanitizeGuestData()` ordena por clave—;
- ❗ el número de invitados lleva `form="gf-form"` o no se envía) · T3→T6 sin empezar; si vas a construir, EMPIEZA POR §7.2** (`#569`, 2026-09-13, carril SPA; un nombre repetido **no se anuncia** —confirmaría quién va a la fiesta— y `OrderCreator` **no se toca**) — siete tandas: T1 piel del formulario · T2 muchos invitados · T3 piel del justificante · T4 dominio y contrato · T5 la página · T7 correos · **T6 el aterrizaje, al final**.
- ❗❗❗ **LO QUE CONTESTA UN PADRE NO ESCRIBE `guest_data`** (§3.1): vive en `invitation_replies`, el formulario lo PROPONE sobre una ficha y el anfitrión lo ADOPTA al guardar — medido: `submitGuestForm()` sustituye la lista entera y `updated_at` es el testigo de extras e invitados. Por eso un padre **no mueve dinero, aforo ni ningún fichero del `CRITICAL_RE`**.
- ⚠️ Con la lista completa **no se admite un «sí»** y el «no podemos» se enseña al anfitrión (D2/D3) · «voy con él» no pide firma (D4) · la autorización sigue al interruptor del producto (D5) · se empareja con una ficha escrita solo con **un** candidato (D11).
- ⚠️ **Borde abierto para la T6** (§7·5): bajar invitados descarta las filas del FINAL.
