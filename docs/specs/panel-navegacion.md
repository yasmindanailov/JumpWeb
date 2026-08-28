# Navegación del panel — el menú plano y «Ajustes»

> Estado: 🟦 **TANDAS 1, 2 y 3 EN EL ÁRBOL** (2026-08-28) · pendiente del OJO del owner
> Decisiones: `DECISIONES.md` **#223** (el menú), **#224** (el buscador) y **#232** (la puerta en tablet) · Encargo del owner: «simplificar el panel, mejor UI/UX,
> empezando por el menú y la organización de cada acción».
> Doc funcional del panel: `PANEL-ADMIN.md` (qué hace cada pantalla). **Esto es solo la FORMA:
> dónde vive cada pantalla y por dónde se llega.**

---

## 1. El problema, medido

Todo lo de esta sección se midió sobre `main` el **2026-08-28** antes de diseñar nada.

| Hecho | Número |
|---|---|
| Entradas del menú que veía el **admin** | **24**, en 6 grupos plegables, **todos desplegados** |
| Entradas que veía el **empleado** | 5 |
| De esas 24, las que `PANEL-ADMIN.md` §2 llama «día a día» | **4** → el **83 %** del menú era puesta en marcha |
| Grupo «Programación» | **5 entradas** para una sola pregunta: *cuándo abrimos* |
| Grupo «Contenido web» | **6 entradas**, ninguna del día a día |
| Búsqueda global | **no existe** — 0 recursos globalmente buscables |
| «Pedidos» ordenaba por | `created_at desc` = fecha de **compra**, no de visita |
| «Usuarios» | UNA lista con 23 clientes + 5 del equipo + 3 sin rol, sin filtro por rol |
| Enlaces duplicados | «Calendario» y «Crear pedido», en el menú **y** en la barra superior |
| Tests que miraban la navegación | **1 fichero** en todo el repo, y no miraba la estructura |

⚠️ **La primera medición fue FALSA y casi arranca el diseño torcido.** Un volcado de la
navegación para dos roles en el mismo proceso dio que el empleado veía las mismas 24 entradas
que el admin —un agujero de seguridad, si fuera cierto—. **Filament MEMOIZA la navegación**: la
construyó para el admin y devolvió esa. Repetido en procesos separados, el empleado veía 5 y el
gateo por permisos era correcto. *Cuando un instrumento dice que algo está roto de par en par,
la primera hipótesis es el instrumento* (§10.6 de `tema-por-instalacion.md`, otra vez).

### 1.1 No era un problema, eran tres

1. **El menú agrupaba por MATERIA, no por FRECUENCIA.** «Programación», «Catálogo», «Contenido»,
   «Sistema» contestan «¿de qué trata esto?». Quien abre el panel a las 10 de la mañana se
   pregunta otra cosa: «¿qué hago ahora?». Había que leer 24 líneas para encontrar 4.
2. **Faltaba una pantalla.** La pregunta diaria —*«¿quién viene hoy, a qué hora, está pagado y
   ha firmado?»*— se contestaba cruzando Escritorio → Calendario → Pedidos → Usuarios. Eso es la
   sensación de «todo separado», y **no se arregla moviendo el menú**.
3. **No se podía buscar.** Sin búsqueda global hay que decidir *a priori* si una persona se
   busca en Usuarios o en Pedidos.

Esta tanda ataca **(1)**. **(2)** y **(3)** siguen abiertas — §6.

---

## 2. Las decisiones del owner

Todas del **2026-08-28**, a pregunta simple, y en este orden:

| # | Pregunta | `[DECIDIDO owner]` |
|---|---|---|
| D1 | ¿Menú por frecuencia, de 24 a 10 entradas con 4 grupos plegables? | Sí… **y luego lo corrigió** → D4 |
| D2 | ¿«Usuarios» se parte en «Clientes» y «Equipo»? | **Sí**: Clientes arriba, Equipo en Ajustes |
| D3 | ¿Renombrar el vocabulario técnico? | **Sí, decide el agente y él lo revisa** |
| D4 | **Corrección del owner** | **«No quiero toggles. El menú, PLANO.** Ajustes, catálogos, programación, contenido web y sistema que salgan solo desde el icono del usuario o algo así, escondido. Solo generan ruido y se usan muy pocas veces.» |
| D5 | ¿Qué entra en el menú plano? | **Cinco sitios**; «Crear pedido» se queda arriba como CTA |
| D6 | ¿Por dónde se entra a las 19? | **Dentro del menú del avatar** |

▶ **D4 es la decisión que manda, y mejora la propuesta del agente.** Un grupo plegable sigue
ocupando sitio y sigue obligando a decidir dónde mirar; sacarlo del camino no lo esconde, lo
**quita de la mesa**. Lo que el agente había propuesto —clusters de Filament— habría dejado 10
entradas donde ahora hay 5.

---

## 3. La forma resultante

```
MENÚ (plano, sin grupos, sin plegables)        BARRA SUPERIOR
┌────────────────────────┐                     [⊕ Crear pedido] [🌐] ( avatar ▾ )
│  Hoy                   │  ← Dashboard                              │
│  Calendario            │                                           ├─ Ajustes
│  Pedidos               │                                           └─ Salir
│  Clientes              │  ← UserResource, pestaña «Clientes»
│  Puerta                │  ← NavigationItem suelto (#119)
└────────────────────────┘
```

**`/admin/ajustes`** (`AdminSettingsHub`), en tarjetas con una línea de qué hace cada una:

| Área | Tarjetas |
|---|---|
| Precios y productos | Catálogo · Tarifas · Zonas |
| Horarios y aforo | Horario semanal · Temporadas · Fechas especiales · Franjas · Plantillas de franja |
| Contenido web | Atracciones · Servicios (web) · Preguntas frecuentes · Ofertas · Normas · Páginas legales |
| Sistema | Configuración · **Equipo** · Roles y permisos · Incidencias · Mantenimiento |

**Medido tras el cambio** (procesos separados, un rol por proceso):

| Rol | Menú | Ajustes |
|---|---|---|
| admin | **5**: Hoy · Calendario · Pedidos · Clientes · Puerta | 19 tarjetas en 4 áreas |
| empleado (`staff`) | **4**: Hoy · Calendario · Pedidos · Puerta | **no le aparece** |

⚠️ **El empleado no ve «Clientes» y eso NO es una omisión**: `UserResource::canViewAny()` exige
`users.manage`, que el rol `staff` no tiene (tiene `users.search_minimal`, que es otra cosa). Si
algún día el owner quiere que su gente busque clientes desde el panel, es una decisión de
permisos, no de menú.

---

## 4. Cómo está construido (y qué NO hay que romper)

### 4.1 Ocultar NO es autorizar
Las 19 pantallas siguen protegidas exactamente igual que antes: `canViewAny()` en los recursos y
`canAccess()` en las páginas, verificados **uno por uno** antes de tocar nada. `shouldRegisterNavigation()`
solo decide si sale en el menú. **Si alguna vez hay que quitarle a alguien una pantalla, se le
quita el permiso, nunca la entrada del menú.**

### 4.2 La invariante que sostiene el rediseño: **ninguna pantalla huérfana**
El riesgo real de esconder cosas no es la seguridad: es que un recurso nuevo no entre ni en el
menú ni en Ajustes y quede **inalcanzable salvo tecleando su URL**, sin que nada avise.

Por eso `AdminSettingsHub::areas()` es la fuente **única** de lo que hay en Ajustes, y
`AdminNavigationTest::test_every_registered_screen_is_reachable` exige que toda página/recurso
registrado en el panel esté en el menú plano, en `areas()`, o en `AdminSettingsHub::OUTSIDE_HUB`
(hoy: `CreateManualOrderPage`, que es la CTA de arriba, y la propia página de Ajustes).
**Añadir un recurso sin colocarlo pone la suite en rojo.**

### 4.3 Ajustes no concede nada
No tiene permiso propio: cada tarjeta pregunta a SU pantalla (`canAccess()`), y la página entera
es accesible solo si alguna tarjeta lo es. A un empleado no le sale ni la entrada del avatar —sin
superficie muerta que invite a probar—.

### 4.4 «Clientes» y «Equipo» son DOS pestañas de UNA pantalla
No dos recursos. La ficha, las acciones sensibles y su patrón de defensa (`ViewUser`: roles,
waiver, rotar carné, anonimizar) son los mismos para las dos; duplicar el recurso duplicaría esa
autorización, que es justo lo que no se debe copiar.

El corte lo dan `User::scopeCustomers()` y `User::scopeTeamMembers()`, que leen
**`User::PANEL_ROLES`** — la MISMA lista que decide `canAccessPanel()`. Antes esa lista estaba
escrita a mano en un solo sitio; una segunda copia habría envejecido en silencio. Los dos lados
son complementarios por construcción y hay caso que lo fija: **ninguna cuenta puede quedarse
fuera de las dos pestañas ni salir en ambas** (mismo modo de fallo que `mis-reservas-por-reserva.md`
§3.4 documentó para los dos ámbitos de reservas).

La pestaña viaja en la URL como **`?tab=`** (Filament la publica con `#[Url(as: 'tab')]`, **no**
`?activeTab=`), y la tarjeta «Equipo» de Ajustes enlaza a `?tab=team`.

### 4.5 ⚠️ Los colores de las tarjetas NO pueden ser utilidades Tailwind
**Medido al compilar**: las clases `hover:border-primary-500`, `group-hover:text-primary-600` y
`focus-visible:ring-primary-600` **sí** entran en el bundle, pero las variables
`--color-primary-500`/`--color-primary-600` **no están declaradas** (solo la 400). Resultado: el
borde del hover y **el aro de foco de teclado** habrían salido invisibles — con la clase puesta y
el test en verde.

Es exactamente el fallo que dejó la pantalla de puerta en blanco y negro durante días
(`identidad-qr-puerta.md` §9.7, `#217`). El estilo vive en `resources/css/filament/admin/theme.css`
y lee los tokens del panel (`var(--gray-*)`, `var(--primary-*)`), que además es lo que hace que el
**color de marca por instalación** mande sobre las tarjetas. `AdminNavigationTest::test_hub_page_actually_receives_the_panel_color_tokens`
asevera que la respuesta trae esos tokens, porque su ausencia no rompe ningún test por sí sola.

---

## 5. Qué enseñó la ejecución

1. ⚠️ **La medición inicial mintió por memoización** (§1). El gateo estaba bien todo el tiempo.
2. ⚠️ **Tres tests aseveraban que estas pantallas SALEN en el menú** y se pusieron rojos, como
   debían. Se re-apuntaron a `assertFalse` con su porqué, en vez de borrarlos: quien lea
   `RoleResourceTest` tiene que enterarse ahí de que eso es a propósito.
3. ⚠️⚠️ **Y un CUARTO se volvió VACÍO sin ponerse rojo**: `RateTypeResourceTest` aseveraba
   `assertFalse(shouldRegisterNavigation())` para el staff. Pasaba porque le faltaba el permiso;
   ahora pasa para cualquiera, o sea que dejó de distinguir nada. Se sustituyó por lo que sí
   distingue al staff: que tampoco le abre «Ajustes». *Un cambio de conducta no solo rompe tests:
   también los desactiva en silencio, y esos hay que ir a buscarlos.*
4. ⚠️ **Un `sed` de renumeración se comió 21 ficheros ajenos** (cookies, landing, `app.js`) porque
   se lanzó sobre `app/ tests/ resources/` en vez de sobre los ficheros del cambio. Se detectó
   comparando, fichero a fichero, si TODAS sus líneas cambiadas eran de la renumeración, y se
   revirtió con `git checkout --`. *Un reemplazo global se acota a la lista de ficheros tocados,
   nunca al árbol.*
5. ⚠️ **`actingAs()` deja al usuario autenticado para el resto del test**: una petición «de
   invitado» escrita después de un `actingAs` no es de un invitado (daba 403 en vez del redirect y
   parecía un fallo del código). Va en su propio caso.
6. **La miga de «Clientes» decía «Usuarios › Listado»** encima de un `<h1>` que dice «Clientes».
   Lo vio la captura headless, no un test. Retirada: en un índice no navega a ningún sitio.
7. **El vocabulario se quedó CORTO a propósito.** `GLOSARIO.md` fija «franja» e «incidencia» como
   términos del dominio, presentes en código y doc: renombrarlos en la UI habría partido el
   lenguaje común. Solo cambiaron los que no dicen nada: *Escritorio → **Hoy***,
   *Usuarios → **Clientes***, *Validar registro → **Puerta*** (en el menú; el título de la pantalla
   sigue describiendo la acción). Lo demás gana una **descripción** en su tarjeta, que era el
   problema real: no que «Temporadas» se llame mal, sino que nadie sabía para qué era.

**Verificación**: 12 casos nuevos en `AdminNavigationTest`, **3 mutaciones y las 3 muerden**
(pantalla huérfana · el menú recupera un grupo · Ajustes concede acceso por su cuenta) · suite del
panel **1162 / 5125** verde · sondeo headless con capturas a 1440 y 390 px (19 tarjetas, pestañas
«Clientes/Equipo», colores resolviendo: borde `oklab` con alfa 0.1, radio 12 px).

---

## 7. Tanda 2 — el BUSCADOR (`#224`)

`[DECIDIDO owner]`: «quiero buscador total del panel, sobre clientes, pedidos y demás». Es la
otra mitad de la tanda 1: al esconder 19 pantallas, **escribir sustituye a mirar el menú**.

### 7.1 Qué encuentra

| Categoría | Qué | Se busca por |
|---|---|---|
| Pedidos | los pedidos | código · nombre y correo del titular |
| Clientes | las cuentas | nombre · correo · teléfono |
| Catálogo, Zonas, Tarifas, Temporadas, Fechas especiales, FAQ, Ofertas, Normas, Páginas, Atracciones, Servicios, Roles | sus registros | su nombre (y el slug donde lo hay) |
| **Pantallas** | las 24 del panel | **su rótulo Y su descripción** |

**«Pantallas» no la trae Filament**: de serie la búsqueda global solo encuentra REGISTROS. La
añade `PanelGlobalSearchProvider`, y sale de las mismas dos fuentes que las pintan —la
navegación del panel y `AdminSettingsHub::visibleAreas()`—, nunca de una lista aparte: una
pantalla nueva aparece sola y **ya viene filtrada por permiso**.

▶ Buscar por la DESCRIPCIÓN es la mitad útil: «Tarifas» se encuentra escribiendo *precio* y
«Fechas especiales» escribiendo *festivo*. Quien busca casi nunca sabe cómo se llama la
pantalla; sabe qué quiere hacer. Y la comparación **ignora tildes**: «catalogo» encuentra
«Catálogo», porque nadie las pone al teclear.

### 7.2 Lo que NO encuentra, y por qué

- **Un empleado no encuentra CLIENTES** (`[DECIDIDO owner, 2026-08-28]`). Busca **pedidos** —que
  es como llega la gente al mostrador— y para comprobar a una persona ya tiene la pantalla de
  Puerta, que es la que lleva límite y auditoría (`SEC-05`). Se sostiene sin código nuevo:
  Filament exige `canAccess()` del recurso y `UserResource` pide `users.manage`.
- **Franjas y plantillas de franja**: no tienen identidad textual (son fecha + hora); se llega
  por el calendario o por su pantalla.
- **Incidencias**: es un registro de eventos, no una entidad, y por `RGPD-02` no lleva PII que
  buscar.

⚠️ **Por qué NO se le puso límite ni auditoría al buscador de clientes**, aunque la puerta sí lo
tenga: allí busca un rol BAJO sobre todos los clientes, y ahí el límite frena una enumeración.
Aquí busca un ADMIN, que ya puede paginar la lista entera — buscarla no le concede nada nuevo.
**Si algún día se le abre al empleado, eso cambia y hay que traerse el tratamiento de la puerta
entero**, y así está escrito en `UserResource`.

### 7.3 ⚠️⚠️ El defecto que apareció midiendo, y ya estaba ahí

**Buscar «jump» en el Catálogo del panel no encontraba «Jump · 1 hora».** Medido: `%jump%` → **0
filas**, `%Jump%` → **5**.

MySQL extrae un valor JSON con colación **`utf8mb4_bin`**, así que un `LIKE` sobre `name->es`
distingue mayúsculas. El patrón `->where('name->es', 'like', "%{$search}%")` estaba en
`CatalogTable` y en `RateTypeTable` **desde que se escribieron**, y no lo veía ningún test.
Arreglados los dos, además del buscador nuevo.

⚠️ **Y la palanca de Filament para eso no es portable.** `$isGlobalSearchForcedCaseInsensitive`
genera `lower(json_extract(...))` en MySQL —correcto— pero en SQLite emite `lower(tabla.name->es)`
en crudo, que SQLite lee como una columna llamada `es` y revienta la consulta. **La suite corre
en SQLite y producción es MySQL.** La salida es pedirle la columna a la GRAMÁTICA (`wrap()`), que
sabe traducir `name->es` en cada motor, y envolverla en `LOWER()` a mano
(`ProvidesGlobalSearch::applyGlobalSearchAttributeConstraints`).

❗ **Un test en SQLite NO puede demostrar esto**: su `LIKE` ya ignora mayúsculas, así que quitar
el `LOWER()` lo deja verde —comprobado mutándolo—. Por eso hay **dos** comprobaciones: el caso de
conducta, y otro que asevera la CONSULTA (que la columna va en `LOWER()` y el término en
minúsculas), que sí muerde en cualquier motor. La conducta en MySQL se verificó a mano contra la
base local.

### 7.4 Otras tres cosas que enseñó la ejecución

1. ⚠️ **PHP 8.4+ prohíbe que una clase redeclare una propiedad de un trait con otro valor
   inicial**: `$globalSearchTitleAttribute` en el trait + su valor en cada recurso = error FATAL
   de composición, y no de ejecución sino al cargar la clase. Es un **método**, no una propiedad.
2. ⚠️⚠️ **La guarda más importante —«un empleado no encuentra clientes»— pareció CIEGA al
   mutarla**, y no lo era: la defensa tiene **dos capas**. `canViewAny()` abre la búsqueda del
   recurso, pero `canView()` decide la URL de cada resultado y **Filament descarta el resultado
   que no tiene URL**. Romper una sola capa no reproduce el fallo. *Una mutación que no muerde
   puede significar que la mutación era demasiado débil, no que la guarda no sirva.*
3. **El atajo se anunciaba «META+K»** en Windows y Linux: con `['command+k', 'ctrl+k']` el sufijo
   sale de `Arr::first()`. Con **`mod+k`** —el modificador que traducen tanto Mousetrap como el
   ayudante de Filament— sale ⌘+K en Mac y CTRL+K en el resto, con una sola declaración.
   Verificado con los tres user-agents. Lo vio el sondeo headless, no un test.

**Verificación**: 11 casos (`AdminGlobalSearchTest`) · **4 mutaciones, las 4 muerden** tras
corregir la que era demasiado débil · suite del panel **1174 / 5155** · sondeo headless con
capturas · conducta en MySQL comprobada a mano.

⚠️ **Coste por pulsación**: el proveedor recorre los 14 recursos buscables, o sea hasta 14
consultas con `LIMIT` por búsqueda, con el rebote de 500 ms de Filament. Con estas tablas
(decenas de filas salvo pedidos y clientes) no se nota; si algún día se nota, lo que hay que
mirar es reducir la lista, no el rebote.

---

## 8. Tanda 3 — la PUERTA en tablet: modo kiosco (`#232`)

`[DECIDIDO owner, 2026-08-28]`, dos respuestas que definen el diseño: **la tablet va FIJA en un
soporte y en HORIZONTAL**, y **la puerta tiene tablet propia** (el resto del panel se usa en
ordenador). Eso la convierte en un kiosco: no se optimiza «que quepa», sino que **la respuesta y
la acción se vean sin desplazar** y que se acierte con el dedo, de pie y a un brazo de distancia.

### 8.1 Medido antes

| | |
|---|---|
| Alto del contenido con ficha abierta (iPad h. 1080×810) | **1.298 px** contra 1.080 de pantalla |
| Ancho usado | **768 px** de 1.080 — un tercio en blanco |
| Reglas CSS del panel entre 640 y 1280 px | **ninguna** |
| Controles por debajo de 44 px | los de la pantalla, a **32–36 px** |

⚠️ **Medir el caso PEOR y creerlo típico habría torcido el diseño.** Los 1.298 px son un cliente
con la **exención de versión anterior**, que añade un párrafo. Por caso real: «no registrado» **810**
(cabía exacto), «registrado, falta firmar» **896**, «versión anterior» **1.115**. Faltaban ~90 px
en el caso común, no 300.

### 8.2 ⚠️⚠️ La primera idea salió PEOR, y lo dijo la medición

Pasar la rejilla de tarjetas de **tres a cuatro columnas** para ganar altura. Al estrecharse a
242 px las tarjetas **envuelven su texto y crecen a lo alto** —«Exención» 187 → 226, «Visita»
148 → 168—: la rejilla bajó 18 px y el total **subió 3**. Se cambió ancho por alto. Vuelta a tres.

*En una rejilla, más columnas no es menos altura: es menos anchura por tarjeta, y el texto la
recupera por abajo.*

### 8.3 Lo que entró

Todo **CSS**, sin tocar el árbol de la vista: ancho de 48rem → **80rem** por encima de 64rem
(1024 px: cubre iPad horizontal 1080, Air 1194, Pro 1366 y el escritorio) · rejilla de **tres**
columnas · cabecera en **una línea** · **nombre a 2,5 rem** —es lo que se contrasta con la persona
que hay delante— · **44 px de mínimo táctil** · y **el buscador pegado arriba**.

▶ **Que sea todo CSS es deliberado.** El velo de privacidad es un `filter: blur()` sobre
`.gate-profile__body` con un `.gate-veil` encima en `position: absolute`: cualquier
`display: contents` o contenedor de scroll nuevo por el medio se lo lleva por delante, y con él la
privacidad de la ficha.

⚠️⚠️ **El buscador pegado arriba se RETIRÓ en la vuelta siguiente** (`#234`, §8.6): resolvía
«empezar de nuevo obliga a bajar del todo», y ese problema desapareció cuando el campo pasó a
vaciarse y recuperar el foco solo. **La solución buena hizo innecesaria a la anterior.**

⚠️ **«Nueva búsqueda» NO se ocultó, aunque era el candidato obvio a recortar** (44 px al final):
además de vaciar el campo, **quita de la pantalla la ficha del cliente anterior**. En una tablet
fija en el mostrador es lo único que impide que los datos de quien acaba de pasar sigan a la vista
del siguiente de la cola. Es privacidad, no comodidad, y hay guarda que impide ocultarlo.

⚠️ **El mínimo táctil va FUERA de todo `@media`** a propósito: un ratón nunca falló por un botón
grande, y así no depende de acertar el ancho del dispositivo — que es exactamente lo que había
fallado aquí, donde no existía ninguna regla en el rango de la tablet.

### 8.4 Resultado medido

| Pantalla | Se pasa | ¿Se ve «Registrar visita»? | Controles < 44 px |
|---|---|---|---|
| iPad horizontal 1080×810 | 64 px | **sí** | 0 |
| iPad Air 1194×834 | 40 px | **sí** | 0 |
| iPad Pro 1366×1024 | **cabe** | **sí** | 0 |
| Tablet vertical 810×1080 | **cabe** | **sí** | 0 |
| Móvil 390×844 | 487 px | no | 0 |

⚠️ Estas cifras son de la tanda 3, **con** las tarjetas de QR y visita. Tras retirarlas (§8.6):
810 (cabe) · 816 (se pasa 6) · 1.018 en el caso de la exención vieja.

El móvil no es el dispositivo de destino y su conducta no cambia respecto de antes.

**Verificación**: `GateKioskTest` (4 casos) con **4 mutaciones y las 4 muerden** · 79 casos de
puerta en verde · sondeo headless en cinco anchos con capturas. ❗ **Una guarda de PHP no puede
ver si «se ve bien»**: las cifras salen del sondeo, y quedan escritas para que la próxima vez se
**re-midan** en vez de suponerse.

### 8.6 La vuelta del owner sobre la pantalla (`#234`)

Seis puntos suyos tras verla, más dos que salieron de mirar la captura:

| | Qué pidió | Qué se hizo |
|---|---|---|
| 1 | Quitar el buscador pegado | Retirado. ▶ **No es marcha atrás**: el punto 2 elimina el problema que resolvía |
| 2 | Que tras buscar el campo se vacíe **y conserve el foco** | `search()` lo vacía y avisa al navegador. ⚠️ Con entrada INVÁLIDA no se vacía: ahí hay que corregir |
| 3 | Fuera la tarjeta del QR | Retirada: pocos casos dan problema y ocupaba una columna |
| 4 | Fuera la tarjeta de Visita, hasta JumpPoints | Retirada. ❗ Era el único sitio que registraba visitas → `customer_visits` deja de crecer (`lealtad-jumppoints.md` §9.bis y `DEUDA.md`) |
| 5 | Las dos columnas, a la misma altura | Hechas dos pilas independientes. Medido: las dos arrancan en 464 px |
| 6 | Foco al ENTRAR y **al volver desde el TPV** | ⚠️ `autofocus` **no** cubre eso: no recarga la página. Se añade `focus.window` + `visibilitychange`, con `preventScroll` |
| 7 | «¿Por qué hay tanto hueco entre las tarjetas?» | **No era un margen**: con `grid` las dos columnas comparten la altura de fila — «Hoy» (98 px) vivía en la de «Exención» (187) y dejaba **89 px** en blanco. Dos pilas lo arreglan: ahora todos los huecos son **16 px** |
| 8 | «¿Por qué pone *0 menores a cargo declarados (respuesta válida…)*?» | Porque estaba escrito para quien lee la spec. Ahora: **«Sin menores declarados.»** |

⚠️⚠️ **Cuatro trampas, todas de método o de instrumento:**

1. Busqué los tests afectados en `tests/Feature/Puerta/` **y me dejé `tests/Feature/Admin/Puerta/`**:
   cuatro casos aseveraban el HTML de las tarjetas retiradas y saltaron al correr la suite, no al
   planificar. Re-apuntados por SUJETO, no borrados.
2. **`assertDontSee('data-gate-visit')` falla aunque la tarjeta esté bien quitada**: la píldora de
   cabecera `data-gate-visit-badge` —que sí se queda— contiene esa subcadena. **Cuarta vez que la
   subcadena engaña en este repo.**
3. ⚠️⚠️ **Una sonda dijo que la búsqueda había dejado de abrir NINGUNA ficha y el código estaba
   bien**: mis propios sondeos habían agotado el limitador de búsquedas TECLEADAS por hora y la
   pantalla contestaba «demasiadas búsquedas». **Tercera vez en la jornada que el sospechoso
   correcto es el instrumento** — y llegué a escribir en el código un comentario afirmando haber
   «medido» otra causa. Corregido, con la corrección escrita donde estaba el error.
4. Un script de reestructuración dejó un `</div>` huérfano **al final del fichero** (buscó «el
   último `</div>`» y ese era el del documento). Lo cazó contar aperturas y cierres, no la vista.

### 8.5 Lo que NO entra, y por qué

⚠️⚠️ **CORRECCIÓN, y va delante del texto que corrige** (2026-08-28, por la tarde): el owner
comunicó después que **el gerente creará las reservas desde la tablet** (`/admin/crear-pedido`). La
premisa de abajo —«el resto del panel se usa en ordenador»— **ya no es cierta para esa pantalla**, y
por eso «Crear pedido» entra en §6·U7 con su propia tanda. Lo que sigue en pie es que la puerta
tiene tablet PROPIA y que el resto (tablas, ajustes) no urge.

El **calendario** (8 reservas pintadas a 36 px de alto y 5 botones de 32–36) y las **tablas**
(«Pedidos» se sale 97 px en vertical y 163 en horizontal) siguen sin tocar: `[DECIDIDO owner]` el
resto del panel se usa en ordenador. Si algún día se usa en tablet, la palanca ya existe y no la
usamos en ninguna tabla — los componentes `Split`/`Stack` de Filament, que apilan una fila como
tarjeta por debajo de un punto de ruptura. Ficha en `DEUDA.md`.

---

## 9. Tanda 4 — «CREAR PEDIDO» en tablet (`#240`, U7)

`[DECIDIDO owner, 2026-08-28]`: **el gerente crea las reservas desde la tablet**. Eso **corrige la
premisa de §8** —«la puerta tiene tablet propia, el resto del panel se usa en ordenador»— para esta
pantalla en concreto.

### 9.1 Lo medido antes de tocar nada (iPad horizontal, 1080×810)

| | Contenido | Se pasa | Controles de la PÁGINA bajo 44 px |
|---|---|---|---|
| Paso 1 · Cliente | 762 px | cabe | **4** (todos de 36 de alto) |
| Paso 2 · vacío | 1.026 px | **216 px** | **8** |
| Paso 2 · con producto | **1.292 px** | **482 px** | **15** — steppers a **28×28**, un icono a **16×16** |

⚠️⚠️ **La primera medición dijo «cabe» y era FALSA por medir solo el paso 1.** El paso 2 es el denso
y es el que se sale. Se corrigió antes de diseñar: sin eso, la tanda habría atacado el problema
equivocado.

▶ **Y lo que de verdad dolía no era el alto**: con un producto elegido quedaban **fuera de pantalla**
el **resumen del pedido** (a 1.100 px) y **el botón de avanzar** — las dos cosas que el gerente
necesita ver con un cliente delante—, todo apilado en **una columna de 648 px** dentro de un lienzo
apaisado de 1080. Contando desde el código, el pedido más simple eran **~16 toques**.

### 9.2 Lo que entró

**Dos columnas con el resumen PEGAJOSO.** A la izquierda lo que se está añadiendo; a la derecha el
resumen, el total y la navegación del asistente, que **no se pierden de vista** mientras el
formulario crece.

⚠️ **El punto de ruptura son 50rem (800 px) y cubre las dos orientaciones, aunque no es evidente cuál
es más ancha**: en apaisado (1080) el menú lateral se lleva ~250 px y el área principal queda en
**760**; en vertical (810) el menú se esconde y queda en **810**. **La tablet en vertical tiene más
ancho útil que en horizontal.**

⚠️ **La columna derecha mide 17rem, no 20.** Con 20 (320 px) la columna del formulario se quedaba en
**304 px de contenido** —más estrecha que un móvil, con los complementos partiéndose en dos líneas—.
El resumen es una lista corta de importes: cabe en 272 y devuelve 48 al área de trabajo.

⚠️ **La navegación vive CON el resumen**, no debajo del formulario: es el gesto más repetido y el que
decide el cobro, así que está siempre a la misma altura.

**Controles táctiles.** La **hora** pasa de desplegable a **chips** (`ToggleButtons`, el componente
nativo de Filament, que conserva `disableOptionWhen` — cambiar el control no puede cambiar la regla).
La **fecha** gana una **tira de 14 días rápidos** y el calendario **se queda debajo**, rebautizado
«Otra fecha»: un cumpleaños se reserva con meses de antelación y eso no se alcanza deslizando (la
misma razón que en `specs/cajon-en-movil.md` §4.1). Y **44 px** en todo lo que se toca.

⚠️⚠️ **La tira son 14 días y no los 182 del horizonte**: aquí no hay un motor cliente que pinte una
lista larga barata — son nodos que **Livewire vuelve a renderizar en cada cambio del formulario**.

### 9.3 La trampa que esta tanda podía haber dejado dentro

⚠️⚠️ **Ahora hay DOS puertas para elegir día —la tira y el calendario— y la hora y los menores
dependen de la FECHA** (`D13`). Quedarse con la hora de otro día es ofrecer algo que el checkout
rechazaría, y **una regla escrita dos veces es una regla que diverge**: se arregla una y la otra se
queda atrás. Las dos terminan en `onDateChosen()`, y hay guarda por conducta que las recorre.

⚠️ **Y dentro de un `afterStateUpdated` escribir en `$this->data` a mano SE PIERDE** —el formulario
vuelve a sincronizar su estado después—, así que la regla recibe el `$set` de Filament cuando la
llama el formulario y escribe en el estado de la página cuando la llama el `wire:click`. **Un
escritor por puerta, una regla.** Lo dijo la guarda, no el ojo.

⚠️ **El `wire:click` de la tira lleva una fecha, así que `pickQuickDay()` vuelve a comprobarla contra
la oferta**: el navegador propone, el servidor decide (`AFORO-02`).

### 9.4 Resultado medido

| iPad horizontal 1080×810 | Antes | Ahora |
|---|---|---|
| Paso 1 | 762 px · 4 bajo 44 | **554 px · 0** |
| Paso 2 vacío | 1.026 px · 8 bajo 44 | **778 px · 0** (cabe) |
| Paso 2 con producto | 1.292 px · 15 bajo 44 | **1.182 px · 0** |
| Resumen y botón de avanzar | fuera de pantalla | **siempre a la vista** (columna pegajosa) |

Y verificado en navegador que **funciona**, no solo que mide: elegir día en la tira trae los 11 chips
de hora, el calendario refleja el mismo día, la columna se detiene en su tope al desplazar 600 px, el
resumen recoge la línea y el paso 3 no deja ningún control bajo 44. **Sonda 11/11.**

⚠️ **Los 11–12 controles bajo 44 px que sigue habiendo en la pantalla son del ARMAZÓN del panel**
—barra superior, menú lateral, buscador—, no de esta página. Afectan a **todas** las pantallas y no
se tocan aquí: ficha en `DEUDA.md`.

**Verificación**: `CreateManualOrderTabletTest` (7 casos) con **6 mutaciones y las 6 muerden** · 50
casos de «crear pedido» en verde · sondeo headless con capturas.

⚠️⚠️ **Tres instrumentos propios salieron mal antes de acertar, y esta vez uno pasó a la guarda**: la
primera sonda midió la pantalla de **LOGIN** durante cuatro tamaños porque `waitForURL('**/admin/**')`
casa también con `/admin/login`; la comprobación del pegajoso pedía que **no se moviera** cuando un
`sticky` sí se mueve —hasta su tope—; y la guarda de la navegación **pasaba en verde con la mutación
puesta** porque `cmo-nav-fuera` **contiene** `cmo-nav`. ▶ **Quinta vez que la subcadena engaña a una
aserción en este repo.**

### 9.5 Lo que queda

- El **OJO del owner** con la tablet en la mano.
- El **armazón del panel** en tablet (§9.4), que es de todas las pantallas.
- **U6**: el calendario y las tablas, que vuelven a la mesa ahora que la tablet es un dispositivo de
  trabajo — quien crea un pedido mira el calendario.

---

## 10. La vuelta del OWNER sobre «Crear pedido» (`#241`)

Cuatro puntos suyos tras ver la tanda 4 en su navegador. Ninguno es un fallo de conducta: los cuatro
son UX que se quedó a medias.

### 10.1 Con RATÓN no se podía deslizar la tira de días

`[OWNER]`: «en la página de creación de pedido ocurre lo mismo, en desktop no hay manera de deslizar».
Entran flechas, **solo donde hay ratón** (`hover: hover` **y** `pointer: fine`) y **solo si llevan a
algún sitio** (lo decide el estado, en un `x-data` de Alpine).

⚠️ **La aritmética se repite a propósito y está declarado**: el panel no carga el bundle del cajón, así
que `resources/js/sidebar/strip.js` y este `x-data` son dos copias. **Los dos números tienen que seguir
siendo los mismos** — el 80 % de salto y **el píxel de tolerancia**, que no es defensivo: sin él la
flecha «siguiente» no se apaga nunca, porque `scrollLeft` es fraccionario con zoom.

### 10.2 Las plazas pesaban lo mismo que la hora

`[OWNER]`: «lo de las plazas debería mostrarse de manera más sutil, no al mismo nivel que la hora».

**La causa era el control**: `ToggleButtons` es el componente nativo, pero **su etiqueta es texto
plano** —no tiene `allowHtml`—, así que «10:00 · 20 plazas» salía todo con el mismo peso. Pasa a un
partial propio: hora a 15 px en negrita, plazas a 11 px sin peso y en gris.

❗ **Cambiar el control no puede cambiar la regla**, y aquí el deshabilitado dejaba de ser del
framework: una franja llena sigue viajando marcada como no vendible —**se enseña deshabilitada, no se
esconde**, igual que en la web— y `pickTime()` la rechaza **en el servidor**, porque un `wire:click` se
puede llamar con cualquier hora. Hay guarda, y muerde.

⚠️ **De regalo, un huérfano**: `timeOptions()` se quedó sin llamador. Retirado con el protocolo de
`CONVENCIONES §3.quater` — el test que lo usaba vigila **la paridad web↔panel**, que sigue viva, así
que **se re-apuntó a `timeChips()`** en vez de borrarse.

### 10.3 «Otra fecha» pasa a ser el CTA «Abrir calendario»

`[OWNER]`. Antes era un campo más apilado en la columna; ahora es una **acción** con el calendario
amplio plegado detrás. Misma decisión y mismo motivo que en el cajón del cliente.

### 10.4 El resumen: de quién es el pedido, y a la altura de su vecina

`[OWNER]`: «añadimos el nombre del cliente, o su correo, y la card a la misma altura que la de la
izquierda; y esa columna puedes hacerla un poco más ancha».

⚠️ **El desfase no era del armazón: era un `mt-6`** en el propio partial del carrito, de cuando el
resumen iba DEBAJO del formulario. Retirado. **Medido: las dos cards arrancan en 229 px.**

El titular usa **el mismo texto que el buscador de clientes** (`customerDisplay()`): componer aquí una
segunda forma sería tener dos maneras de nombrar a la misma persona en la misma página. Y la columna
sube a **19rem** — con 20 el formulario se quedaba en 304 px de contenido, con 17 se le apretaba el
correo al titular; 19 es donde caben las dos cosas.

### 10.5 Verificación

Sonda `sonda-u7b.mjs`, **16/16** en iPad horizontal: el titular en el resumen con nombre + contacto,
las dos cards en 229, la columna en 304 px, la flecha que mueve (0 → 256) y su contraria que aparece,
el CTA que despliega el calendario con su `aria-expanded`, las 11 franjas como chips con **la hora a
15 px y las plazas a 11**, y **cero controles bajo 44 px** (excluida la flecha, que solo existe con
ratón). `CreateManualOrderTabletTest` sube a **13 casos** con **6 mutaciones más, las 6 muerden**.

⚠️⚠️ **Una mutación NO mordía y el código estaba bien**: el ancla del `sed` aparecía **dos veces** —en
`timeChips()` y en el `timeOptions()` huérfano— y mutó el método muerto. ▶ *Una mutación que no muerde
puede estar mutando otra cosa.* Y de paso destapó el huérfano.

---

## 11. La segunda vuelta del owner sobre «Crear pedido» (`#242`)

Dos puntos suyos, los dos del último tramo de la pantalla.

### 11.1 El «Atrás» pasa a ser solo icono

`[OWNER]`: «quita el texto "atrás", solo deja el icono, y su background como está». El rótulo competía
con el botón que hace avanzar, que es el que se busca; una flecha a la izquierda se entiende sin
leerla. ▶ **El nombre accesible NO se pierde**: un botón de solo icono sin `aria-label` queda **mudo**
para un lector de pantalla. Verificado: 44×44, con su fondo, y `aria-label="Anterior"`.

### 11.2 El método de cobro, en dos TARJETAS con icono

`[OWNER]`: «en vez de checkbox simple, añade dos cards con su icono de Efectivo y Datáfono». Es el
último gesto del pedido y el que menos margen de error admite: con la tablet en la mano y un cliente
delante, una diana de **154×96** con un dibujo se acierta sin mirar; un círculo de radio de 16 px, no.

❗ **Cambia el CONTROL, no la REGLA.** `ToggleButtons` es el componente nativo y conserva las claves de
`ManualOrderFulfiller`, el `required` y el arranque en **efectivo** — la forma de tarjeta la pone el
CSS, no una segunda implementación del campo. Una tarjeta bonita que mandara otro método sería el peor
fallo posible de esta pantalla, y por eso la guarda asevera las tres cosas.

### 11.3 ⚠️⚠️ Dos instrumentos mintieron, y el segundo enseña algo reutilizable

**(1)** El aro de la tarjeta elegida se colgó de un **`aria-pressed` que `ToggleButtons` no emite**: la
sonda leyó cadena vacía en las dos tarjetas. El estado real es el `:checked` del propio `<input>`, que
es hermano del `<label>`. Se leyó de la plantilla del componente en vez de suponerlo.

**(2)** `flex-direction: column` sobre `.fi-btn` era una declaración **INERTE** —`.fi-btn` es
`display: grid`— y **`getComputedStyle` la devolvía igual**, «column», porque esa propiedad se computa
aplique o no. Lo destapó medir **dónde caen** el icono y el texto (y=34 y y=38: la misma línea).
▶ *Un valor computado dice lo que vale la propiedad, no si esa propiedad manda.*

**Verificación**: `CreateManualOrderTabletTest` sube a **15 casos** · **4 mutaciones más, las 4
muerden** · sonda `pago.mjs` **8/8** en iPad horizontal, con cero controles bajo 44 px en el paso.

---

## 6. Lo que queda

| | Qué | Por qué importa | Estado |
|---|---|---|---|
| **U1** | **El OJO del owner** sobre el menú, Ajustes y las pestañas | Es un cambio de UI/UX: la suite no puede decir si «se entiende» | ⬜ pendiente |
| **U2** | Repasar los **rótulos y las 19 descripciones** | `[DECIDIDO owner]` D3: las propone el agente, las revisa él | ⬜ pendiente |
| **U3** | **Las cinco columnas que le faltan a «Hoy»** | ❗ **«Hoy» YA EXISTE**: es el Escritorio renombrado, y ya trae filtro Hoy/Semana/Mes, dos cifras, la tabla **Cuándo · Producto · Cliente · Cantidad · Estado · Formulario**, un clic al pedido y «Imprimir resumen del día». **Medido: cero menciones a exención, menores, visita o ajustes en sus dos widgets.** Lo que falta: **si firmó la exención** (lo primero que se mira en la puerta) · **si trae menores y quiénes** · **si ya entró hoy** (la visita que registra la puerta desde `#208`, que nadie lee) · **si llega debiendo dinero** (`OrderAdjustment` de señal y extras, que se cobran en persona) · **el teléfono**. **No es una pantalla nueva: son cinco columnas.** ⚠️ Y una afirmación del agente que resultó FALSA: dijo que un pedido manual dejado a deber no saldría en «Hoy» — `ManualOrderFulfiller` los crea SIEMPRE pagados y el resto va como ajuste | ⬜ sin empezar |
| ~~**U4**~~ | ~~Búsqueda global~~ | **HECHA** (`#224`, §7): 14 recursos + una categoría de PANTALLAS que Filament no trae. El empleado busca pedidos, no clientes. De regalo, un defecto vivo: buscar «jump» en el Catálogo no encontraba «Jump · 1 hora» | ✅ |
| ~~**U7**~~ | ✅ **HECHA** (`#240`, §9): dos columnas con el resumen pegajoso, la hora en chips, la fecha con tira de 14 días rápidos y 44 px en todo. ⚠️⚠️ **«Crear pedido» del panel, para TABLET** (`[DECIDIDO owner, 2026-08-28, tarde]`) | ❗ **Esto CORRIGE la premisa de §8**: allí el owner dijo «la puerta tiene tablet propia, el resto del panel se usa en ORDENADOR», y por eso el calendario y las tablas quedaron fuera. **Ha cambiado: el GERENTE va a crear las reservas desde la tablet**, en `/admin/crear-pedido`. Así que esa pantalla necesita su propia pasada de tablet —tamaños táctiles, y sobre todo **presentación**: hoy es un formulario largo de 1.362 líneas pensado para un ratón—. ▶ Y con ella vuelve a la mesa parte de **U6**: quien crea un pedido mira el calendario | ⬜ sin empezar |
| **U6** | El **calendario** y las **tablas** en tablet | Medido: 8 reservas del calendario a **36 px** y 5 botones a 32–36; «Pedidos» se sale **97 px** en vertical y **163** en horizontal. `[DECIDIDO owner]`: el resto del panel se usa en ORDENADOR, así que no urge. La palanca existe y **no se usa en ninguna tabla**: `Split`/`Stack` de Filament apilan la fila como tarjeta bajo un punto de ruptura | ⬜ sin empezar |
| **U5** | Los `$navigationSort` de las 19 escondidas ya no ordenan nada | El orden de Ajustes lo manda `areas()`. Son propiedades muertas, inofensivas pero mentirosas | ficha en `DEUDA.md` |

▶ **Si el owner aprueba, el orden natural es U3 y luego U4**: son las dos mitades de «todo está
separado» que el menú, por sí solo, no puede cerrar.
