# Navegación del panel — el menú plano y «Ajustes»

> Estado: 🟦 **TANDA 1 EN EL ÁRBOL** (2026-08-28) · pendiente del OJO del owner
> Decisión: `DECISIONES.md` **#223** · Encargo del owner: «simplificar el panel, mejor UI/UX,
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

## 6. Lo que queda

| | Qué | Por qué importa | Estado |
|---|---|---|---|
| **U1** | **El OJO del owner** sobre el menú, Ajustes y las pestañas | Es un cambio de UI/UX: la suite no puede decir si «se entiende» | ⬜ pendiente |
| **U2** | Repasar los **rótulos y las 19 descripciones** | `[DECIDIDO owner]` D3: las propone el agente, las revisa él | ⬜ pendiente |
| **U3** | **La pantalla «Hoy»** — que conteste quién viene, pagado, firmado, y lleve al pedido de un clic | Es el problema (2) de §1.1, y **no lo arregla el menú**. Hoy «Pedidos» ordena por fecha de compra, no de visita | ⬜ sin empezar |
| **U4** | **Búsqueda global (⌘K)** en pedidos y clientes | Es el problema (3), y el que más quita la sensación de «todo separado». ⚠️ **Toca RGPD/SEC**: hay que gatearla por permiso y decidir qué campos se indexan — `INVARIANTES` §3 y §4 **antes** de escribir nada | ⬜ sin empezar |
| **U5** | Los `$navigationSort` de las 19 escondidas ya no ordenan nada | El orden de Ajustes lo manda `areas()`. Son propiedades muertas, inofensivas pero mentirosas | ficha en `DEUDA.md` |

▶ **Si el owner aprueba, el orden natural es U3 y luego U4**: son las dos mitades de «todo está
separado» que el menú, por sí solo, no puede cerrar.
