# [SPEC] La isla y la landing nueva — las páginas en la instancia y una segunda carcasa de compra en el producto

> Estado: ⬜ **borrador** · Última actualización: 2026-09-24 · Decisiones: `#681` (las páginas) y `#682` (la
> isla), las dos `[DECIDIDO owner]`; la spec se aprueba con la suya.
> Carril: **plataforma** (banda 670–699). Fuente del diseño: el proyecto de Claude Design
> `33397ca2-c67c-4049-8b09-ade20425f32a`, «Saltia Design System» (nombre provisional; la marca es Play Jump
> Park), leído con `DesignSync` (`list_files` / `get_file`). Hermanas: `instancia-y-landing-fuera.md` (el menú
> de hechos), `cajon-empaquetable.md` (el motor y su §4.9), `analitica.md` §4.4 (los experimentos).

## §0 · Antes de tocar

- **Regla que ordena todo**: dos piezas y dos dueños. Las **páginas** son de la instancia, en Blade, y solo
  reciben hechos (`#681`). La **isla** es del producto: una segunda carcasa sobre el motor del cajón, apagada
  por defecto (`#682`). De PlayJump no entra nada al producto: sus textos, fotos y valores de tokens son datos.
- **Empieza por** §1.3 (el censo de la isla) → §4.1 (motor y carcasa) → §4.3 (el contrato página↔isla).
- **Trampas, antes de tocar**:
  - ⚠️ **La web nueva cambia las URLs** (§1.5): sin 301 se pierde el SEO y no falla nada.
  - ⚠️ Los componentes del diseño son **React con datos de prueba** y calculan el total en el navegador. En
    producción el total sale de `/orders/quote` (§4.4).
  - ⚠️ `DesignSync` corta a **256 KiB**: el vídeo y el logotipo se toman del original, nunca de ahí.
  - ⚠️ El README del diseño se contradice (dice «`assets/` vacío» y trae logo y vídeo): mandan los ficheros.
  - ⚠️ El aviso de cookies pasa a la isla, y la T3 de la analítica (carril del SPA, `#735`) toca ese aviso:
    se avisa en el buzón antes.
  - Tras tocar un `.vue`, `npm run build:ssr` antes de la suite (`sidebar-spa.md` §0).
- **Estado**: ⬜ borrador. Censo de la isla hecho (§1.3); el de las páginas y el de tokens son la T0. Sin código.
- **Invariantes**: `PAY-*` si entra Bizum (`VERIFY_CONC=1`), `SEC-12` (la ruta de las vistas), `SEC-01`,
  `RGPD-*` (consentimiento dentro de la isla, Apple como proveedor), `PERF-02` (la carcasa por instalación va en
  el arranque cacheado; la variante del A/B, en la sesión).

## 1. Contexto y problema — MEDIDO (2026-09-24)

### 1.1 Qué trae el diseño

Medido con `DesignSync list_files` y leyendo entero su `readme.md` (125 KB, 693 líneas) y el `Mapa`:

- **Fuentes**: el `Mapa` (18-09, compradores, puntos de contacto y el orden de los argumentos) y once briefs:
  base (v6), portada, cumpleaños, Kids, Jump, isla, compra, Mi cuenta, normas, visítanos y correos.
- **71 componentes React** (`.jsx` + `.d.ts` + `.prompt.md`): compra 6 · content 15 · core 6 · feedback 4 ·
  forms 10 · marketing 25 · navigation 5. Todos con variables CSS, sin CSS-in-JS ni dependencias.
- **31 secciones** montadas (`sections/*.card.html`) y **seis páginas** (`paginas/`): portada, Cumpleaños, Kids,
  Jump, la compra en la isla y Mi cuenta en la isla. Kids y Jump son **un solo molde** con dos textos.
- **Siete ficheros de tokens** (`tokens/`), 22 guías y un vídeo, un logotipo y cuatro fotos reales.
- **Decisiones de marca del owner (20-09)**: ambiente claro, cian de marca, **el naranja del logo como único
  color de acción**, sin color por zona y el héroe en tarjeta, nunca a sangre.
- **Lo que no está**: Colegios (el `Mapa` lo deja fuera), las páginas de Normas, Visítanos y legales (tienen
  brief pero no página), el post-form, la invitación y el justificante (tienen brief propio).

### 1.2 El SPA de hoy, medido

- La máquina (`machine.js`) tiene once pasos: `CATALOG`, `DATE`, `TIME`, `CART`, `IDENTIFY`, `VERIFY_EMAIL`,
  `PAY`, `REDIRECTING`, `CONFIRMED`, `DECLINED`, `VERIFYING`. Hay **20 stores**, cada uno con su test.
- `resources/js/sidebar/**`: **~10.600 líneas de lógica JS y ~12.900 de sus tests**, frente a **~8.100 de
  pantallas Vue** (51 ficheros). El motor no sabe de forma (`cajon-empaquetable.md` §4.9).
- **El alta dentro de la compra ya es «paga primero»**: sin correo de verificación y con la sesión abierta
  (`submitRegister` en `PurchaseSection.vue`). `VERIFY_EMAIL` solo sale ante el señuelo. Coincide con el diseño.
- `POST /auth/register` exige `name`, `email`, `phone` y `password`: la «cuenta normal con contraseña» del
  diseño ya existe.

### 1.3 El censo de la isla: sus quince situaciones contra el producto

El brief de la isla (`brief-isla-playjump`) define quince situaciones y quién manda cuando coinciden. La tabla
dice qué pide cada una y qué da hoy el producto. **HAY** = existe · **PARCIAL** = falta un trozo · **FALTA** =
lógica nueva · **DATO** = texto del cliente, no lógica.

| Nº | Situación | Lo que pide | Hoy en el producto | Veredicto |
|---|---|---|---|---|
| 1 | Aviso de cookies | El aviso, dentro de la isla | Aviso en `layout.blade.php` (`COOKIES.md`) | **MOVER** (y la T3 del SPA) |
| 2 | Llega a una página | El «desde» | `/prices` | **HAY** |
| 3 | Hoy | Horario y «quedan huecos» | `/schedule/now`; huecos por producto en `/availability/{product}/times` | **PARCIAL**: no hay «¿quedan huecos hoy?» agregado |
| 4 | La oferta acaba | La fecha real de fin | Solo `promo.percent`, sin fecha | **FALTA** |
| 5 | Una frase por sección | El texto que quita un miedo | — | **DATO** (la página lo declara) |
| 6 | Ha calculado | Total, señal y resto | `/orders/quote` | **HAY** |
| 7 | Día y hora elegidos | La selección | Stores `date`, `time`, `selection` | **HAY** |
| 8 | Se llena · avísame | Horas libres · aviso si se libera | `available` por hora; sin lista de espera | **PARCIAL**; el aviso, **FALTA** |
| 9 | Vuelve · enlace a la pareja | El cálculo guardado y compartible | — | **FALTA** (se puede hacer sin servidor) |
| 10 | Dentro de la compra | Los pasos | La máquina de once pasos | **HAY**; se reagrupan (§4.1) |
| 11 | A medias · pago fallido · hora perdida | La reserva guardada, la vuelta del banco, la retención | `Order.expires_at` (`PaymentSettings::holdMinutes()`), `RetryAdmission`, `/orders/{code}/payment-status` | **HAY**, salvo «Pagar con Bizum» (**FALTA**) |
| 12 | ¿Lo hablamos? | WhatsApp y sus señales | Contacto en `/site`; visitas en el libro de la analítica | **PARCIAL** (la señal de «tercera visita») |
| 13 | Tarea pendiente | Una tarea con su plazo real | Plazos del post-form y de menores en sus specs | **A MEDIR** (no hay lista de tareas como tal) |
| 14 | Reserva hoy | Mi QR | El carné QR (`identidad-qr-puerta.md`) | **HAY** |
| 15 | Páginas que no venden | El horario de hoy | `/schedule` | **HAY** |

**La compra y la cuenta**, fuera de la tabla: tarjeta, carrito de varias líneas («Añadir otra entrada»),
calcetines como complemento, la [Hora extra], Google, contraseña y recuperación: **HAY**. **Apple**: **FALTA**
(ninguna referencia en `app/`). **Bizum**: **FALTA** (ninguna en `app/` ni `config/`). El día de tarifa especial
en el calendario (el punto amarillo): `/schedule` trae las fechas especiales y `/prices` los días de la tarifa;
**A MEDIR** si basta con cruzarlos. Entrar «con correo o teléfono»: **A MEDIR**.

### 1.4 Los tokens: dos vocabularios

El diseño trae primitivos (`--ink-*`, `--flare-*`, `--aqua-*`, `--volt-*`, `--berry-*`, `--sun-*`) y decenas de
alias semánticos (`--bg-*`, `--surface-*`, `--text-*`, `--action-*`, `--control-*`, `--notice-*`, `--band-*`,
`--scrim-*`), y redefine los de lectura bajo `[data-surface="ink"]`. El producto tiene su propia capa de tema
(`tema-por-instalacion.md`: seis mecanismos, `[data-surface]`, `theme.action` en el panel). **Choques ya
visibles**, a resolver en la T0: la columna (1240 en el diseño, 1120 en el producto), el mínimo táctil (44 contra
48), la escala de radios (6/10/14/20/28/36 contra la de `ShapeScaleTest`), los **iconos** (Lucide contra
`IconSetAnatomyTest`, «nada de otra librería») y las **fuentes** (Google Fonts por `@import`: se sirven desde el
propio dominio, porque cargarlas de Google envía la IP del visitante).

### 1.5 Las URLs cambian

La landing de hoy sirve nueve vistas (`instancias/playjump/web/`): portada, atracciones, bar, contacto,
cumpleaños, legal, normas, precios y servicios. El diseño ordena la web por comprador: portada, Cumpleaños,
Kids, Jump, Colegios, Normas y seguridad, Visítanos y las legales. **Rutas como `/precios`, `/atracciones` o
`/bar` desaparecen**: cada una necesita su 301 al sitio nuevo o se pierde lo que Google ya tiene indexado.

## 2. Objetivo y criterios de éxito

**Objetivo**: la web de PlayJump con el sistema nuevo, en su instancia, y la isla como carcasa del producto que
PlayJump enciende.

1. **La isla, apagada, no cambia nada**: la suite pasa con la carcasa del cajón y con la de la isla, y la huella
   de maquetación del cajón da 0 en sus pantallas.
2. **Se compra por la isla**: una entrada y un cumpleaños con señal, de principio a fin, en local y en staging
   (`/sonda`, con la pasarela de pruebas).
3. **Rápida y accesible** (brief base, regla 11): el contenido principal en menos de 2,5 s en móvil, medido en
   staging, y contraste AA.
4. **Cero marca en el producto**: el criterio de `#639` («cero marca» en el código vivo) se sigue cumpliendo.
5. **Ninguna URL indexada se pierde**: cada ruta vieja responde 301 a la nueva, con test.

**Fuera**: el A/B (T5 de la analítica, carril del SPA, `#735`) · los correos (su brief es del carril de
correos) · el post-form, la invitación y el justificante (briefs propios, después) · Colegios · la app.

## 3. Opciones consideradas

### 3.1 Las páginas (`#681`)

| Opción | A favor | En contra |
|---|---|---|
| **A · Blade en la instancia** ✅ | Una tecnología, un despliegue, un gate. El mecanismo existe (`instancia::`, `SEC-12`). HTML del servidor para Google y el móvil. Mismo dominio. Un cambio del panel sale solo en minutos. | La web va atada a la versión del producto; la instancia necesita sus propias pruebas. |
| B · Astro estático | Despliegue independiente, CDN. | Producción no tiene Node: datos horneados, y con despliegues de noche un precio tarda horas. Se salta `SecurityHeaders`, `NoStoreWebResponses` y `ResolveVisitor`. Una segunda subida esquivando el `rsync --delete` y la guarda 9. |
| C · Nuxt o Next con servidor | Páginas del servidor con interactividad. | Exige Node en producción. |
| D · Otro proveedor para la web | Regeneración automática. | Otro proveedor y un proxy delante del dominio, que reabre `SEC-13`. |
| E · Constructor en el panel | El cliente edita solo. | Descartado en `#611`; webs genéricas. |

### 3.2 La isla (`#682`)

| Opción | A favor | En contra |
|---|---|---|
| **A · Segunda carcasa del producto, apagada por defecto** ✅ | La compra sigue bajo el gate; un solo motor; cada cliente elige; el riesgo se mide. | Dos carcasas que mantener (se acota compartiendo el contenido de los pasos, §4.1). |
| B · Solo en la instancia, el SPA oculto | Libertad total. | La compra fuera del gate y dos compras que mantener. |
| C · Isla para todos | Una sola carcasa. | Impone un diseño arriesgado a todos los clientes. |
| D · Reescribir en React | Los componentes del diseño casi tal cual. | Se tira el motor y sus tests. |

## 4. Diseño elegido

### 4.1 Motor y carcasa

- **El motor** son los stores, la máquina, `api.js`, `i18n.js` y las reglas (`buyer-due.js`, `admission.js`…).
  No cambia de dueño ni de forma.
- **La carcasa** es el contenedor y lo que solo él sabe: el cajón (`Shell.vue`, `shell.js`) o la isla, en
  `resources/js/isla/` (futuro). La elige un ajuste por instalación, `sidebar.shell` = `cajon` | `isla` (futuro),
  que viaja en `/sidebar/boot` porque es igual para todos los visitantes. La variante del A/B, que es por
  visitante, viaja en `/sidebar/session` (`analitica.md` §4.4).
- **Los pasos de la isla sobre la máquina**: «Cuándo y cuántos» = `DATE` + `TIME` + cantidades · «Tus datos»
  = `IDENTIFY` · «Pagar» = `CART` + `PAY` (el recibo es editable sin salir) · «Listo» y los desenlaces =
  `CONFIRMED`, `DECLINED`, `VERIFYING`. Hay que medir en la T3 si la máquina admite ese orden sin tocar sus
  transiciones.
- **Para acotar el coste de dos carcasas**, el contenido de cada paso se escribe una vez y lo montan las dos.
  Si el cajón adopta los pasos nuevos o conserva los suyos es la pregunta 3 de §7.

### 4.2 Las páginas en la instancia

- Vistas Blade y componentes Blade **de la instancia**: `VideoHero`, `ZoneCard`, `RateTable`… son presentación
  de PlayJump. No se copian componentes del producto (README de la plantilla).
- **La instancia declara sus páginas** en `instancias/playjump/config/paginas.php` (futuro), propuesta: slug,
  vista, hechos que consume y redirecciones 301 de las rutas viejas. El producto las sirve con un controlador
  genérico dentro del grupo `web` (cabeceras, `no-store`, visitante). Así «kids» o «jump» no son rutas del
  producto: son nombres de un cliente.
- **Los datos que recibe una vista** son los de los recursos públicos del menú, los mismos que sirve la API,
  resueltos en el servidor. Sube `InstanceViews::CONTRATO` (hoy 2).

### 4.3 El contrato entre la página y la isla

El prototipo encuentra los botones por su texto («la etiqueta es lo único estable»). En nuestras páginas el
marcado es nuestro, así que el contrato es **declarativo en el HTML** (el «kit declarativo» que `#632`·P2
aplazó; ahora tiene cliente):

- `data-isla-pagina` en el `<body>`: tipo, producto, acción y «desde» (situación 2).
- `data-isla-frase="…"` en cada sección que quita un miedo (situación 5).
- `data-isla-cta` en cada botón principal: con uno a la vista, la isla cede su acción (regla 1 del diseño).
- `data-isla-widget` donde va la calculadora de la página (§4.4).
- Un evento para abrir la isla desde la página (el `pj-island:open` del prototipo).

Los nombres son propuesta de esta spec, a fijar en la T2.

### 4.4 La calculadora de la página

El calendario, las horas, las cantidades y el resumen que el diseño pone dentro de cada página de producto
**son del producto**: componentes Vue del mismo paquete, montados en el `data-isla-widget`, que comparten store
con la isla. Así «Reservar para hoy» llega a la isla con el día elegido. **El total nunca se calcula en el
navegador**: sale de `/orders/quote`. El propio diseño da el motivo: con calcetines y el −20 %, la cuenta
ingenua da 73,60 € y la real 74,40 €.

### 4.5 La lógica nueva, que entra por la API antes que por la isla

| Pieza | Situación | Estado | Nota |
|---|---|---|---|
| Fecha de fin de la oferta | 4 | FALTA | Un dato junto a `promo.percent`; lo pide también `OfferTag` |
| Bizum | 11 y Pagar | FALTA | Dinero: `INVARIANTES` §1, `REDSYS.md`, `VERIFY_CONC=1` |
| Entrar con Apple | Tus datos | FALTA | Un proveedor más junto a Google (`auth-con-google.md`) |
| Aviso de día liberado | 8 | FALTA | Es un corchete: puede faltar sin dejar hueco |
| Cálculo guardado y compartible | 9 | FALTA | Sin servidor si el enlace lleva los parámetros |
| ¿Quedan huecos hoy? | 3 | PARCIAL | Un agregado de lectura; `PERF-02` |
| Tareas con plazo por reserva | 13 | A MEDIR | Los plazos existen repartidos por sus specs |

Qué entra en la v2.0.0 lo decide el owner (§7, pregunta 4). Lo que no entre se queda como corchete apagado.

### 4.6 El tema

Los valores de Saltia son de PlayJump y van a su `tema/client.css`. Los **roles** que el producto no tenga los
declara el producto con nombre genérico, y el mapa rol a rol se hace en la T0. Las fuentes se sirven desde el
dominio (en `publico/` de la instancia). Los iconos dependen de la pregunta 2 de §7.

### 4.7 Las tandas (propuestas)

| Tanda | Qué | Dónde |
|---|---|---|
| T0 | Censo de las páginas (cada pieza contra el menú de hechos), mapa de tokens y mapa de URLs viejas → nuevas | Esta spec |
| T1 | El tema: tokens en la instancia, roles nuevos en el producto, fuentes locales, iconos | Producto + instancia |
| T2 | La isla en reposo (apagada por defecto): menú, situaciones 2, 3, 5 y 15, y el aviso de cookies dentro | Producto |
| T3 | La compra en la isla sobre el motor, con tarjeta; sonda de compra | Producto |
| T4 | La primera página en la instancia, con su calculadora y sus 301 | Instancia + producto |
| T5 | Mi cuenta en la isla | Producto |
| T6 | El resto de páginas y la lógica nueva que apruebe el owner | Los dos |

Después, la v2.0.0 (`#670`): con la isla encendida para PlayJump, y el A/B cuando la T5 de la analítica exista.

## 5. Impacto en invariantes

- `PAY-*`: solo si entra Bizum; entonces `VERIFY_CONC=1` y la lista del `CRITICAL_RE`.
- `SEC-12`: las vistas de la instancia siguen fuera del árbol; lo que la instancia declara (§4.2) es
  configuración, no código.
- `SEC-01`: toda ruta nueva de la API, dentro del grupo `api`.
- `RGPD-*`: el consentimiento dentro de la isla (`COOKIES.md`); Apple como proveedor de identidad; las fuentes
  desde el propio dominio.
- `PERF-02`: la carcasa en el arranque cacheado; nada por visitante en él.
- `AFORO-*`: ninguno; el agregado de «¿quedan huecos hoy?» es de lectura.

## 6. Plan de verificación empírica

- La suite con las dos carcasas; la huella de maquetación del cajón a 0 con la isla apagada.
- `/sonda`: compra de una entrada y de un cumpleaños con señal por la isla, en local y en staging.
- Un arnés de mutación sobre la tabla de prioridades de la isla (cambiar el orden de dos situaciones tiene que
  poner rojo un test).
- El mapa de 301 con su test: cada URL del sitemap de hoy responde 301 a su sitio nuevo.
- Contraste AA y tiempo de carga en móvil, medidos en staging.

## 7. Revisión y decisión

**Decidido por el owner el 24-09**: `#681` (Blade en la instancia) y `#682` (la isla, segunda carcasa, apagada
por defecto, hecha en este carril).

**Pendiente del owner** (con la recomendación primero):

1. **La primera página (T4)**. Recomendado: **Kids y Jump**, porque un molde da dos páginas y su compra no
   lleva señal, así que es el camino del dinero más corto para estrenar la cadena entera. Alternativa:
   Cumpleaños, que vale más pero lleva señal, dos edades y el formulario después.
2. **Los iconos**. Recomendado: **Lucide, metido en el HTML al construir** (el diseño entero está hecho con
   él), relajando «nada de otra librería» de `IconSetAnatomyTest`. Alternativa: redibujar los que falten en el
   set del producto.
3. **Los pasos del cajón clásico**. Recomendado: que **adopte los pasos nuevos** dentro de su lateral, para
   mantener un solo contenido. Alternativa: que conserve sus 25 pantallas y solo reciba lo mínimo.
4. **La lógica nueva en la v2.0.0**. Recomendado: **fecha de fin de oferta y Bizum** dentro; Apple, aviso de
   día liberado y cálculo compartible, después.

**Revisión**: la spec la revisa el owner; la revisión adversarial se le pide con el coste delante (regla 9).

## Anexo · fila del enrutador

`| La isla · la landing nueva (Saltia) · la carcasa de compra | docs/specs/isla-y-landing-nueva.md §0 |`
