# [SPEC] Producto e instancias — la separación de JumpWeb y sus clientes

> Estado: ✅ **aprobada por el owner el 2026-09-16** · en ejecución, F0 y F1 cerradas (`#617`→`#621`) y **F2 en curso** (`#623`, sesión 1: plugin construido y medido; falta instalarlo en las dos máquinas y el 6 de 6) ·
> Última actualización: 2026-09-16 · Decisiones: `DECISIONES #610` → `#616` · Carril: **plataforma**, banda **610–639**.
> Origen: sesión de análisis del 2026-09-16 con el owner; inventario medido sobre el árbol de ese día.
> Las dos páginas de trabajo que se iteraron con el owner son borradores de ESTA spec, no fuente:
> el esquema (https://claude.ai/code/artifact/c0a1828f-63ee-4644-a5fc-77383a295bc8) y la hoja de ruta
> (https://claude.ai/code/artifact/56fdb8f2-d7c9-4669-99c6-39fe9e54cc9b). Lo vigente es esto.

## §0 · Antes de tocar

- **Regla que ordena todo**: lo que sería distinto para un segundo cliente es de la **instancia**; lo que es
  igual para todos es del **producto**. Nada de un cliente en `main`; nada de código de producto en una instancia.
- **El cajón sigue siendo un cajón sobre la landing.** Lo que cambia es quién lo monta: pasa de una línea del
  layout del producto a un paquete con contrato de incrustación. No confundir «empaquetable» con «página aparte».
- **La landing consume solo hechos por la API pública**: precios, horario, documentos legales, normas y
  reseñas. La presentación es manual, de cada instancia.
- **Versionado**: v1.0.0 es lo que hay en producción; la separación de la landing es v2.0.0 porque las
  instancias tienen que actuar. Producción despliega etiquetas.
- **Orden de las fases**: F0 gobierno → F1 doc caliente → F2 capa de agente → F3 versión → F4 cajón
  empaquetable y token → F5 instancia PlayJump → F6 app nativa. F1 exige el otro carril en pausa.
- **Trampas ya pagadas que aplican aquí**: el harness en modo «auto» ordena preferir Bash (regla 8 de
  `CLAUDE.md`); la memoria del agente es por máquina y por ruta; una skill se puede no invocar y un gate no.

## 1. Contexto y problema — MEDIDO (2026-09-16)

### 1.1 Lo que carga un agente antes de trabajar

| Fichero | Tamaño | Hace 14 días | Crecimiento |
|---|---|---|---|
| `CLAUDE.md`, inyectado entero en cada petición | 314 KB | 81 KB | +17 KB/día |
| `docs/ESTADO.md`, paso 1 del arranque | 866 KB | 340 KB | +38 KB/día |
| `docs/00-REFACTOR.md`, paso 2 | 389 KB | 278 KB | +8 KB/día |
| `docs/DECISIONES.md` | 2,4 MB | 1,2 MB | +87 KB/día |

Arranque en frío: **1,57 MB**, del orden de 400.000 a 500.000 tokens (estimación sin tokenizador local). El
enrutador pasó de 4,8 KB a 314 KB en 32 días con «mantener CORTO» en su cabecera: el gate documental tenía nueve
comprobaciones (esta spec dijo «ocho»; F1 midió nueve, `#620`) y **ninguna medía tamaño**. El 94 % del enrutador es la tabla de enrutado; tres filas superan los 32 KB;
27 de 63 filas apuntan a trabajo ✅. El estado apila 11 bloques «si entras nuevo» desde el 28 de agosto. El
registro de decisiones tiene 556 entradas con 3,7 KB de mediana y 47 KB de máximo.

**Causa**: duplicación. Siete frases distintivas sondeadas aparecen cada una en 2 a 6 ficheros (fila del
enrutador, decisión, estado, spec, tracker, instalación). La documentación pesa 7,6 MB frente a 4,1 MB de código.

### 1.2 El cliente dentro del producto

| Medida | Valor |
|---|---|
| Decisiones que nombran a PlayJump | 79 de 526 |
| Specs que lo nombran | 14 de 44 |
| Specs de landing y diseño | 12 ficheros, 751 KB, el 27 % |
| Tests de landing (`Landing`, `Site`, `Theme`) | 73 de 489 ficheros |
| Superficie de landing en el producto | 8 páginas, 32 componentes, 340 KB de `landing.css`, 521 KB de `site.css` compartida con el cajón, 53 KB de textos por idioma, 38 fotos (7,1 MB), vídeo |
| CMS en el panel | 6 recursos de 19 (Attractions, Faqs, Testimonials, LandingServices, Offers, BarImages) y las secciones de ajustes «textos de la landing», «bar», «redes» |
| Módulo `Content` | 37 ficheros, 294 KB: 8 modelos y 25 servicios, mezcla de CMS de landing con tema, legal y cookies |

El mecanismo de FICHEROS del cliente existe y funciona (rama huérfana `cliente/playjump`, `aplicar.sh`, 15 rutas
en `.gitignore`, exclusiones del `rsync`, guarda). Lo que no tiene casa son sus **decisiones** y su **estado**.

### 1.3 Dos agentes, dos máquinas

`CONVENCIONES §10` acierta donde es mecanismo (las bandas acabaron con 13 colisiones de número) y falla donde es
prosa: el contador de la suite en una línea compartida que el hook lee; los avisos entre agentes en el mismo
fichero que ambos reescriben (711 commits sobre `ESTADO.md`); las reglas del owner en la memoria de una sola
máquina, copiadas a mano a `CARRIL-SPA.md` §6. Contradicción viva: el skill de cierre hace `git add -A` y el
carril del SPA lo prohíbe.

### 1.4 El harness

El modo «auto» de permisos inyecta en cada petición la orden de preferir Bash a Read/Edit/Write; la regla del owner
en sentido contrario vivía solo en memoria. El techo de descripción que el modelo lee por skill es 1.536
caracteres. Existen los eventos de hook `SessionStart`, `UserPromptSubmit` y `Stop`, y un hook puede inyectar
contexto al modelo. Solo hay Pint y PHPUnit como herramientas de calidad; ningún análisis estático.

### 1.5 Versionado y API

Cero etiquetas de versión (las dos que hay son una copia de seguridad y un wip). El contrato OpenAPI declara
`1.0.0` desde el primer commit y no se ha movido en 75 commits que lo cambiaron. El despliegue registra solo el
hash. Siete despliegues a producción en once días. La API declara **un único esquema de seguridad**, la cookie de
sesión: el modelo de usuario admite tokens Bearer pero ningún endpoint los emite. Endpoints públicos de lectura
para la landing: catálogo y precios sí (4); horario, documentos legales por clave, normas y prueba social **no**.

## 2. Objetivo

1. **Arranque en frío ≤ 60 KB** (25× menos), con techos medidos por el gate: enrutador ≤ 12 KB, fichero de carril
   ≤ 24 KB, tracker ≤ 16 KB, §0 de spec ≤ 2 KB, decisión nueva ≤ 1,5 KB.
2. **Cero material del cliente en el producto**: código 0 menciones; doc solo entradas marcadas como históricas.
3. **Un repo por instancia** con landing, tema, config, semillas, build de app y documentos propios; el producto
   publica un **contrato de instancia** con versión.
4. **Versionado semántico** con producción solo por etiquetas; v1.0.0 = producción del 13-09; v2.0.0 = landing fuera.
5. **Capa de agente compartida** por plugin en las dos máquinas y los tres repos; skills que arrancan sin barra.
6. **La app nativa** nace después, atada a una versión del contrato de la API.

**Fuera de alcance de esta spec**: construir la app; la vía A de la landing (sitio estático); la T6 de Business
Profile (publicar horario); migrar las 79 decisiones existentes (se marcan).

## 3. Opciones consideradas

| Tema | Elegida | Descartada y por qué |
|---|---|---|
| Dónde vive lo del cliente | Un repo por instancia | Carpetas en `main`: viola `DECISIONES #1` y el gate correría sobre las instancias. Rama huérfana: funciona para ficheros, no para documentos ni para acceso de terceros |
| Producto y app | Repos separados, unidos por el contrato de la API por versión | Monorepo de producto: otra cadena de herramientas y cadencia; el gate de 114 s correría en cambios de la app |
| Nombre y ruta del producto | `JumpWeb` sigue donde está | Renombrar: 6 rutas, 10 nombres, el remoto, el clon del otro ordenador y la memoria del agente ligada a la ruta absoluta |
| Landing | Vía B primero (paquete de vistas de la instancia que el producto renderiza), vía A después (estática contra la API) | Solo A ahora: exige los endpoints públicos antes de mover nada |
| Datos de la landing | Hechos por la API pública; presentación manual | Landing que teclea precios y horario: envejece, el defecto que `#488` cazó en las dudas |
| App | **Nativa** (opción B, `[DECIDIDO owner]`) | Cápsula sobre el cajón: un solo código, pero el owner prefiere tacto nativo |
| Reseñas | Mecanismo del producto: Business Profile (`#524` sigue vigente), API pública sin avatares | Manual por instancia: no es legítimo copiar reseñas de Google y envejece |
| Registro de decisiones | Partir por centenas, techo por entrada, no reescribir lo viejo | Reescribir 556 entradas: coste y riesgo de cambiar el sentido |
| Skills compartidas | Plugin instalable | Copias por repo: se separan sin que nadie lo vea (ya pasó con las reglas del owner) |
| Contrato de instancia | Su versión es el MAYOR del producto | Número aparte: dos versiones que hay que mantener alineadas |

## 4. Diseño elegido

### 4.1 Tres capas

- **Producto**, repo `JumpWeb` en `main`: API v1, el cajón, el panel, y `contrato/` (futuro): esquema del tema
  con la lista de assets, las claves de configuración, el formato de semillas y la API pública de lectura.
- **Instancia**, un repo por cliente (`instancia-<slug>`): `web/` la landing a mano, `tema/`, `config/`,
  `datos/`, `app/` la config de build, `docs/` con decisiones de prefijo propio, estado e instalación rellena, e
  `instalar.sh` (hoy `aplicar.sh`). Nace de una plantilla que publica el producto. Sin código del producto.
- **Instalación**, el servidor del cliente bajo un dominio: el servidor web sirve la landing como estáticos de la
  instancia (vía A) o el producto la renderiza desde una ruta configurada (vía B); el producto sirve `/mi-cuenta`,
  los flujos de servidor, `/api/v1` y `/admin`, lee el paquete de instancia y la BD del cliente. **Al actualizar
  `main` cambia solo el producto**: el despliegue ya excluye del borrado catorce rutas del cliente y las subidas,
  y siembra solo en arranque en frío.

### 4.2 El cajón empaquetable

Hoy lo monta una línea de `resources/views/components/layout.blade.php`, comparte `public/css/site.css` con la
landing (32 bloques de sección del cajón) y nueve rutas del producto (`/login`, `/registro`, `/mi-cuenta`,
`/entradas`…) renderizan la portada solo para tener algo detrás del cajón abierto. Pasa a ser un **paquete**: guion
más punto de montaje, hoja propia con su raíz de tokens, contrato de incrustación (atributos y eventos). La landing
de cada instancia lo carga y lo abre como hoy. El producto gana un **anfitrión mínimo** vestido por el tema para
esas nueve rutas y los flujos con vista propia (verificar correo, restablecer contraseña, errores, mantenimiento):
respaldo y banco de pruebas, no puerta principal.

### 4.3 El panel sin CMS

| | Recursos | Secciones de ajustes |
|---|---|---|
| Se van | Attractions, Faqs, Testimonials, LandingServices, Offers, BarImages | textos de la landing, bar |
| Se quedan | Orders, Users, Roles, AuditLogs, Catalog, RateTypes, Zones, Seasons, SlotTemplates, Slots, SpecialDates, **ParkRules**, **Pages** | identidad, contacto, dirección, fiscal, registro, fiesta mixta, ventas, puerta, aforo, Redsys |
| Se decide en F5 | Zones pierde sus campos de landing tras censar qué lee el cajón | redes, si ningún correo las usa; apariencia web, que pasa al contrato |

Attractions arrastra el **complemento por atracción** (contrato y lector en Booking, 0 de 23 en uso): se retira con
él. ParkRules y Pages se quedan porque normas y los cinco documentos legales los consume la landing por la API.
Los campos de presentación de `ticket_types` (ventajas, chapa, descripción, destacado) **se quedan**: los consume el
cajón.

### 4.4 La API pública de lectura

Nuevos en F5: horario con estado en vivo y festivos; documentos legales por clave; normas; prueba social
(valoración, recuento, reseñas con autor, enlace al perfil, marca de traducción y fuente, **sin avatares**: es la
única pieza que obliga al visitante a pedir algo a Google). Fuente de reseñas: la de hoy en F5; Business Profile
(T1 y T2 de `specs/google-business-profile.md`) después, sobre el mismo contrato `SocialProof`.

### 4.5 Login por token (F4)

Esquema Bearer en el contrato; endpoints para emitir y revocar con los mismos limitadores que el login;
`revokeAllAccess()` alcanza a los tokens (son credenciales, `RGPD-06`). Sube el contrato a 1.1.0.

### 4.6 Versionado

Versionado semántico sobre el producto entero. **MAYOR**: la instancia tiene que actuar. **MENOR**: capacidad nueva
sin acción. **PARCHE**: arreglo. La versión del contrato de instancia es el MAYOR del producto. Etiqueta anotada
en `main`; **producción despliega solo etiquetas** (guarda 8 del despliegue); staging despliega `main`.
`CHANGELOG.md` con dos mitades por versión: «para las instancias» e «interno». La versión desplegada queda en el
servidor y en el estado de la instancia. El contrato OpenAPI sube en MENOR al añadir; una ruptura es `/api/v2`.
v1.0.0 = `b0ea5a16` (producción del 13-09). La separación de la landing publica v2.0.0.

### 4.7 La capa de agente

Un plugin de Claude Code (`jumpweb-agente`, repo privado como marketplace) con skills y hooks, instalado en las dos
máquinas y activado por repo. Skills: `carril` (sustituye a `arranque-sesion`), `handoff` (sustituye a
`cierre-sesion`), `decision`, `ligero`, `spec`, `release`, `mutar`, `desplegar`, `sonda`, `instancia`, `dod`.

**Disparo sin barra, tres capas**: la descripción de cada skill como lista de situaciones y frases del owner (≤ 1.536
caracteres); una tabla momento → skill en el enrutador; hooks deterministas (`SessionStart` inyecta «ejecuta
/carril», `UserPromptSubmit` sugiere la skill por mapa de palabras, `Stop` avisa si hay cambios sin cierre).
Salida de F2: seis frases del owner en sesión nueva de cada máquina, 6 de 6.

**El estándar de `/carril`** (`[DECIDIDO owner]`, 2026-09-16): calidad de código profesional como norma, no como
excepción. Antes de tocar un subsistema, el agente lee su §0, mide sus dependencias reales (`scripts/module-deps.php`,
las guardas `ModuleBoundariesTest`, `ModuleContractsTest` y `ApiBoundariesTest`), y **si la arquitectura del
subsistema es mejorable, lo propone en spec antes de codificar**; cambiar la forma de un sistema es legítimo
cuando es mejor y está medido. Toda regla nueva de calidad que exija una dependencia (análisis estático con
Larastan, Rector, ESLint) es decisión del owner por `CONVENCIONES §9`: **propuesta pendiente**, con coste.

Lo que no es skill y es gate: comprobación 10 del gate documental (techos; la 9, los marcadores de conflicto,
existía desde `#506` y F1 lo midió: `#620`), el guard de Bash y el pre-push que ya
existen, la guarda 8 del despliegue y el comando que valida una instancia contra el contrato. Lo que es texto: las
reglas del owner en `CONVENCIONES §10` y en las reglas 8 y 9 de `CLAUDE.md`; plantilla de `CLAUDE.md` de 4 KB para
instancia y app.

**Ejecución, sesión 1** (`#623`, 2026-09-16; referencia `docs/sistemas/CAPA-DE-AGENTE.md`): el repo del plugin es su
propio marketplace con el plugin bajo `plugins/`; las skills se invocan como `/jumpweb-agente:<skill>` y también
`/<skill>` si nadie más usa el nombre (por eso las tres viejas conviven hasta el 6 de 6); los hooks van en Python 3
y no en `jq` (no está en las máquinas), fallan abiertos, y `Stop` bloquea una vez por estado solo con commits sin
empujar, porque en ese evento el owner no ve otra cosa. Medido: arnés de hooks 38/38 y una sesión real
`claude -p --plugin-dir` sobre el repo con los tres hooks disparando. ⚠️ El clasificador del modo «auto» deniega
escribir los ficheros que inyectan contexto (hooks, manifiesto, reglas, momentos): la sesión 2 empieza por el
permiso del owner.

**Las skills, una a una** (lo que hace cada una y la medida que la motiva):

| Skill | Sustituye o cubre | Qué la motiva, medido |
|---|---|---|
| `carril` | Arranque de carril: fetch, árbol limpio, hook activo, lee su fichero de carril y el enrutador, reclama empujando una línea; antes de tocar un subsistema, revisión de sus dependencias y su arquitectura | 711 commits sobre un estado compartido; 13 colisiones de número; la skill de arranque no se disparó sola |
| `handoff` | Cierre: reescribe la foto de su carril en vez de apilarla, retira avisos atendidos, añade por nombre de fichero, trailer con evidencia y modo | 11 bloques históricos apilados; el cierre hace `git add -A` y el carril del SPA lo prohíbe |
| `decision` | Entrada en la banda del carril, techo de 1,5 KB, estructura fija, actualiza «último usado», corre el gate | 556 entradas con 3,7 KB de mediana y 47 KB de máximo |
| `ligero` | Perfil rápido con recibo, abajo | la doc es el 41 % de los bytes escritos en 14 días; la suite tarda 114 s |
| `dod` | Existe y se queda | |
| `spec` | Spec desde la plantilla con su §0 de 2 KB, alta en el índice y en el enrutador | 44 specs sin §0; filas del enrutador de hasta 34 KB |
| `release` | Etiqueta anotada, sección del changelog, push; producción solo etiquetas | 0 etiquetas de versión; 7 despliegues por hash |
| `mutar` | Arnés de mutación con restauración garantizada y puerta por código de salida | 60 arneses escritos uno por feature; dos árboles dejados mutados |
| `desplegar` | Runbook sobre el script: de noche, etiqueta obligatoria en producción, guarda del contrato | 3 minutos de 503 con un pago en curso (`#594`) |
| `sonda` | La receta del navegador: Chromium en el contenedor, puente de puertos, la dependencia que `npm install` poda | 8 sondas con la receta en una cabecera |
| `instancia` | Crear desde la plantilla, aplicar el paquete a un producto local, comprobar la versión del contrato, sincronizar docs; sustituye a `aplicar.sh` | 79 decisiones del cliente dentro del registro del producto |
| `build-cliente` | Build de la app para una instancia desde su carpeta de config; se diseña en F6 | |

**El perfil `/ligero`**, saltarse cosas con recibo y no a escondidas:
- **Siempre**: código más un test que falle al revertir; Pint; docs-check; leer INVARIANTES si toca dinero,
  aforo, RGPD o seguridad; commit por nombre de fichero; handoff de tres líneas.
- **Se omite, y queda escrito en el trailer del commit** como «Modo: ligero · omitido: …»: la suite completa en
  local (filtro del módulo; el gate la corre una vez), el arnés de mutación (una línea en `DEUDA.md`), la sonda de
  navegador si nada visual cambió, el reloj y el build si no hay fixtures con fechas ni assets, la narrativa larga
  (registro de seis líneas), la revisión adversarial.
- **Se prohíbe**: tocar un fichero del `CRITICAL_RE`, el contrato OpenAPI o una migración de dinero o aforo. La
  skill lo comprueba con el diff y baja sola a modo completo.
- En ligero se lee el §0 de la spec, no la spec.

**La regla del buzón de `/carril`**: cada agente escribe solo su fichero de carril. Un mensaje para el otro va en
el fichero del emisor; el receptor lo atiende y anota «atendido» en el suyo; el emisor lo retira en su siguiente
cierre. Cero escrituras cruzadas. El contador de la suite no vive en ningún fichero de carril.

### 4.8 La documentación del producto, con techo

Enrutador ≤ 12 KB con una línea por fila; `docs/carriles/<carril>.md` uno por agente (banda, ficheros, foto,
retomar, buzón: cada agente escribe solo el suyo); tracker ≤ 16 KB; `docs/decisiones/` por centenas con techo por
entrada; cada spec con su §0 ≤ 2 KB; un documento del contrato de instancia en `docs/` (futuro). El histórico del estado se borra: git es
el archivo. El contador de la suite sale del estado y queda en el trailer del commit. La mudanza del contenido de
las filas se verifica con **huella**: cada frase con aviso del enrutador localizable en su spec antes de borrarse.

### 4.9 Las fases

| Fase | Carril | Sesiones | Salida |
|---|---|---|---|
| F0 gobierno | 1 | 1 | esta spec ✅ owner; `#610`–`#616`; reglas 8 y 9 |
| F1 doc caliente | 1, el 2 en pausa | 2–3 | arranque ≤ 60 KB; huella 100 %; comprobación 10 — ✅ 2026-09-16, `#617`→`#621` |
| F2 capa de agente | 1, ambas máquinas | 2 | plugin en las dos; 6 de 6 frases |
| F3 versión | 1 | 1 | `git describe` en producción = v1.0.0; guarda 8 |
| F4 cajón y token | 2 la SPA, 1 la API | 3–5 | cajón montado desde HTML ajeno; huella de maquetación 24/24; contrato 1.1.0 |
| F5 instancia PlayJump | 1 | 4–6 | visitante sin cambios; cero cliente en el código; 13 recursos; v2.0.0 |
| F6 app nativa | 2 | spec 1–2 | pila y alcance ✅ owner; repo desde plantilla |

### 4.10 Riesgos por fase, y qué los cubre

- **F1**: perder una trampa al mudar (la huella); chocar con el carril 2 en ficheros compartidos (el carril 2
  empuja y se para antes, y hace pull al volver).
- **F2**: un hook que no dispara falla en silencio (la prueba de las seis frases es de salida; el vigilante de
  ajustes del harness exige abrir `/hooks` una vez tras escribirlos).
- **F4**: un token es una credencial nueva (revocación, caducidad y limitadores se prueban, no se suponen); partir
  la hoja compartida puede cambiar el cajón sin que falle nada (la huella de maquetación, no la suite).
- **F5**: una URL que cambia es una pérdida de SEO silenciosa (el sitemap se compara antes y después);
  consentimiento de cookies, textos legales y el retorno de Google necesitan dueño declarado antes de mover nada;
  la vía B ata la landing a componentes del producto (es transición, la vía A es destino).
- **F6**: la guía 4.2.6 de Apple exige que cada cliente publique con su cuenta; una app nativa es una segunda
  implementación de las 25 pantallas del cajón, en manos de una sola persona.
- **Programa**: el ojo del owner es el único revisor; cada fase termina con un guion de revisión corto que dice
  qué mirar y dónde.

## 5. Impacto en invariantes

- `RGPD-06`: los tokens Bearer son credenciales; `revokeAllAccess()` los retira. Caso propio en F4.
- `SEC-06`: los endpoints de token comparten los limitadores del login.
- `RGPD-05`: la prueba social pública no publica avatares; ninguna petición del visitante a terceros.
- `PERF-02`: la landing lee la API pública con caché corta; la portada no llama a terceros en el render.
- `AFORO-*`, `PAY-*`: ninguno. Ningún cambio de dinero ni aforo en el programa.
- `SUITE-*`: 73 ficheros de guardas de landing se mudan a la instancia o se retiran con su sujeto; la línea del
  contador de la suite deja de vivir en `ESTADO.md` (cambia la regla de `DECISIONES #10` sobre el hook).

## 6. Plan de verificación empírica

- F1: `wc -c` de los ficheros con techo; huella con extracción de frases ⚠️/❗ y `grep -F` en destino; comprobación
  9 vista en rojo con una mutación de tamaño.
- F2: prueba de seis frases en sesión nueva de cada máquina (las seis: «lee la doc y arranca» → `carril` ·
  «cerramos, haz el handoff» → `handoff` · «queda decidido: …» → `decision` · «hazlo en ligero» → `ligero` ·
  «¿está hecho de verdad?» → `dod` · «despliega a producción» → `desplegar`, que debe negarse con el parque
  abierto); hooks canalizados con su JSON y validados con python3 (`pruebas/probar-hooks.sh` del plugin, 38
  casos; `jq` no está en las máquinas) y una sesión real `claude -p --plugin-dir` sobre el repo — hecho en la
  sesión 1 (`#623`); las seis frases, pendientes.
- F3: `git describe --tags` en producción; un despliegue sin etiqueta abortando.
- F4: caso que monta el cajón desde un HTML mínimo ajeno; tests de contrato del esquema Bearer; huella de
  maquetación de las doce vistas (el instrumento de `#437`) idéntica.
- F5: sitemap antes y después idéntico; siete páginas en 200; `grep -ciE 'pjp|playjump'` en `app/`, `resources/` y
  `lang/` a cero; panel con 13 recursos; suite del producto verde en 416 ficheros.

## 7. Revisión y decisión

**Aprobada por el owner el 2026-09-16** tras leer §0 y §4.9 («la valido, todo ok»). Revisada con él en la misma
sesión: app nativa, landing fuera, repo por instancia, nombre y ruta del producto, Business Profile como
mecanismo, panel sin CMS con la lista cerrada. Decisiones `#610` → `#616`.
**Pendientes del owner**: la pila de la app (F6a); las herramientas de análisis estático (dependencia nueva); el
modo de permisos del harness; si Zones pierde sus campos de landing y si «redes» se va (F5).
