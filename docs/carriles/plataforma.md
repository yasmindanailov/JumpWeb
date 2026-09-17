# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador** (`~/proyectos/JumpWeb`) · Banda: **610–639** · Último usado: **`#626`** ·
> Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) · Actualizado: 2026-09-17.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo 24 KB (check 10). El contador de la suite no vive aquí: va en el trailer del commit.

## Foto

- **F0 cerrada** (2026-09-16, `#610`–`#616`): la spec aprobada por el owner, las siete decisiones y las
  reglas 8 y 9 de `CLAUDE.md`.
- **F1 hecha** (2026-09-16, `#617`–`#621`), la doc caliente: las decisiones por centenas en `docs/decisiones/`
  (536 entradas partidas sin tocar una, control byte a byte, cuatro números repetidos conservados) · el
  contador de la suite fuera del estado y el `pre-push` leyéndolo del trailer del commit · las 46 specs con
  `§0 · Antes de tocar` (≤ 2 KB) · la tabla del enrutador mudada VERBATIM a 39 documentos (huella 974/974:
  antes de mover nada solo 1 de las 974 frases con aviso era localizable en su spec) y `CLAUDE.md` a una
  línea por fila · el estado por carriles y `ESTADO.md` como índice · el tracker sin narrativa · la
  comprobación 10 del gate (techos), vista en rojo con 50 errores antes de mudar y con una mutación de tamaño
  al cerrar · nace `sistemas/AFORO-FRANJAS.md` (la fila de aforo apuntaba a las invariantes, que son una tabla).
- ⚠️ **Corrección a la spec**: la comprobación de techos es la **10**; la 9 (marcadores de conflicto) existía
  desde `#506` aunque la cabecera del gate enumeraba ocho.
- **`#622` · el ciclo de vida de la doc** (`CONVENCIONES §11`, comprobación 11): referencia con techo, proceso
  que se archiva al cerrar, registro que se marca; archivar no reescribe citas (el gate resuelve la cita vieja
  al archivo). Aplicado a `desglose-dinero-cliente.md`. ▶ Queda **podar `DEUDA.md`** (277 KB; las fichas
  cerradas se borran) y `VERIFICACION-E2E-CAJON.md` (186 KB) antes de ponerles techo — tarea propia.
- **Medido al cerrar F1** (`wc -c`, 2026-09-16): `CLAUDE.md` 12.249 B (era 316.874) · `00-REFACTOR.md` 11.409
  (era 390.052) · `ESTADO.md` 2.679 (era 870.051) · el carril más grande (`web.md`) 7.542 · el §0 más grande
  2.039 · **arranque en frío 35.918 B** (enrutador + índice + carril + tracker + un §0; era 1,57 MB). Las tres
  mutaciones de tamaño (enrutador, §0, decisión) pusieron el gate en rojo y se restauraron byte a byte; la
  huella se reproduce sobre el enrutador histórico con `git show <sha>:CLAUDE.md` y `--enrutador`.
- **F2 · sesión 1** (2026-09-16, `#623`): el plugin `jumpweb-agente` construido en `~/proyectos/jumpweb-agente`
  (git init, sin remoto): 11 skills, 3 hooks en Python, las reglas del owner, el mapa de momentos y el arnés
  `pruebas/probar-hooks.sh` (38/38). **Medido en una sesión real** `claude -p --plugin-dir` sobre este repo:
  `SessionStart` inyectó la foto y la orden de `/carril`, `UserPromptSubmit` sugirió `/carril` por «arranca», y
  las once skills se listan como `jumpweb-agente:<skill>` junto a las tres viejas del repo. Referencia:
  `sistemas/CAPA-DE-AGENTE.md`; el enrutador ya nombra `/carril` y `/handoff` con `/arranque-sesion` y
  `/cierre-sesion` como respaldo hasta cerrar F2.
  El clasificador del modo «auto» denegó primero seis ficheros (`marketplace.json`, `hooks.json`, `comun.py`,
  `owner.md`, `momentos.json`, `README.md`); **el owner concedió el permiso en la misma sesión** y quedaron
  escritos (commit `62360b1` del plugin, byte a byte iguales a las copias probadas; 22 ficheros versionados,
  arnés 38/38 sobre el árbol real). **Empujado a GitHub** (`yasmindanailov/jumpweb-agente`, `c8e74b1`) y
  **instalado en ESTA máquina por el CLI**: `claude plugin marketplace add <url .git>` (queda en los settings
  de usuario con la forma `{"source":"git","url":…}`) y `claude plugin install jumpweb-agente@jumpweb-agente
  --scope project`, que escribió `enabledPlugins` en `.claude/settings.json`; añadí a mano
  `extraKnownMarketplaces` con esa misma forma para que la otra máquina lo añada al confiar en la carpeta. La
  versión instalada es el sha del commit (`c8e74b1de042`); actualizar = `/plugin marketplace update jumpweb-agente`.
- **Octavo despliegue HECHO** (2026-09-16, 22:52–22:53, parque cerrado, `#594`): subió `1272cb93` (código =
  `448ea4f5`, `#571`, el número de invitados del post-form), sin migraciones, copia `pre571-…-204505` en el
  servidor, `client.css` `687ffcb3…` servido; nueve páginas en 200 y **`/bar` en 503 por
  `maintenance.page.bar = 1`** (dato del panel, no del despliegue). **El último por hash** (`#613` → F3). Receta,
  hashes y verificación: `ENTORNOS.md` §6. La sesión arrancó con el plugin instalado: «arrancamos» → `/carril` y
  «sí, despliega» → `/desplegar` sin barra, **2 de 6** de la prueba de salida de F2.
- **F2 · sesión 3** (2026-09-16, noche): medido un defecto del hook —«vamos con F2, continuamos» a mitad de sesión
  volvió a ordenar `/carril` entero— y corregido en el plugin (`c57c9f2`, empujado): `carril` es
  `una_vez_por_sesion` en `momentos.json` y `prompt_submit.py` mira la transcripción (`transcript_path`) con las
  dos formas medidas (`"skill":"jumpweb-agente:carril"` y `<command-name>/carril`); arnés **43/43**, mutación
  vista en rojo (3 de 43) y el fichero restaurado byte a byte; 21 ms sobre 1 MB. En esta máquina estuvo
  instalado `c8e74b1` hasta el 17-09 por la noche (el clasificador denegaba `claude plugin marketplace update`
  como «Self-Modification»); desde entonces, `627b3a3` (ver «retomar», paso 0).
- **F3 · versión, ejecutada salvo una línea** (2026-09-17, `#624`): lo que queda de F2 es todo del owner, así
  que la sesión hizo F3. `[DECIDIDO owner]` **v1.0.0 = `1272cb93`** (lo que corre desde el octavo despliegue;
  `#613` decía `b0ea5a16` y queda marcada). **Guarda 8** en `scripts/deploy.sh`, lo primero del pre-vuelo
  local: etiqueta ANOTADA `vX.Y.Z` exacta sobre HEAD y en `origin`; `--go` aborta, en seco avisa, staging no
  la pide; la versión se escribe en `storage/app/version` del servidor tras el `rsync` y la salud la relee.
  `DeployScriptGateTest` pasa de 32 a 39 casos y EJECUTA el script en un repo de usar y tirar con un `origin`
  desnudo; `scripts/mutar-guarda8.sh` 9/9, `deploy.sh` restaurado byte a byte (sha1). Medido aparte: un `--go`
  de producción sin etiqueta contra un host `.invalid` sale con 1 antes de conectar. `CHANGELOG.md` nace en la
  raíz con la v1.0.0. ▶ **Falta para el ✅**: que producción DIGA v1.0.0 (el fichero no existe allí).
- **Tres decisiones del owner el 17-09**: `#624` (arriba) · **`#625` análisis estático**: Larastan nivel 5 sobre
  `app/` y ESLint sobre el cajón, con línea base y dentro del `pre-push`; Rector no · **`#626` permisos**: el
  harness sigue en «auto» y el OWNER añade a `~/.claude/settings.json` de cada máquina cuatro reglas `allow`
  (las dos órdenes de `claude plugin … jumpweb-agente` y `Edit`/`Write` sobre `~/proyectos/jumpweb-agente/**`);
  producción queda fuera a propósito. Medido: `claude plugin marketplace update` se le deniega al agente también
  con la petición del owner delante, y **en la extensión de VSCode `/plugin` no existe** (todo por la terminal;
  corregido en `CARRIL-SPA.md` §1 paso 8 y `CAPA-DE-AGENTE.md` §4). **La pila de la app sigue abierta**: el
  owner pidió pros y contras y los tiene; recomendada React Native + Expo confirmada con prueba corta en F6.

## Por dónde retomar, en orden

1. **F2 · lo que queda** (spec §4.7, `#623`, `sistemas/CAPA-DE-AGENTE.md`), en este orden:
   (0) ✅ **HECHO por el owner el 17-09 a las ~21:55**: plugin en `627b3a3f7b09` en esta máquina (por terminal,
   `claude plugin …`), enlace `~/.local/bin/claude` reparado (2.1.273) y las cuatro reglas de `#626` en
   `~/.claude/settings.json`. **Medido**: con la regla puesta, `claude plugin marketplace update jumpweb-agente`
   desde la shell del agente PASA (antes, «Self-Modification» dos veces). ⚠️ El primer intento del owner pegó el
   bloque en `.claude/settings.json` del PROYECTO como segunda clave `permissions`: en JSON gana la última y el
   fichero cargaba 4 `allow` y **0 `deny`** (adiós a `rm -rf`, `migrate:fresh`, `db:wipe` y `jumpingjump`);
   restaurado antes de commitear. En la otra máquina hizo lo mismo (no verificable desde aquí);
   (a) **la prueba de las seis frases en ESTA máquina** (README del plugin; el owner abre `/hooks` una vez):
   **van 2 de 6** (`carril`, `desplegar`, el 16-09); quedan «cerramos, haz el handoff» → `handoff`, «queda
   decidido: …» → `decision`, «hazlo en ligero» → `ligero`, «¿está hecho de verdad?» → `dod`; el 6 de 6 se
   anota en la spec §6; (b) **la otra máquina**: `git pull` en JumpWeb y confiar
   en la carpeta instala el plugin solo (`extraKnownMarketplaces` + `enabledPlugins`); si no aparece `/carril`
   en el menú `/`, la receta manual de `CARRIL-SPA.md` §1 paso 8; después sus seis frases, 6 de 6; (c) retirar
   `.claude/skills/{arranque-sesion,cierre-sesion,dod}` y sus menciones (enrutador paso 0, CONVENCIONES §1 y
   §5, `CARRIL-SPA.md` §1 paso 8) y cerrar F2 en el tracker. ⚠️ Hasta (c), `arranque-sesion` y
   `cierre-sesion` siguen. Cada cambio del plugin: `bash pruebas/probar-hooks.sh` en verde → commit → push →
   `/plugin marketplace update jumpweb-agente` en cada máquina (la versión es el sha del commit).
2. **F3 · cerrarla**: que producción diga v1.0.0. Una línea, de noche o con el parque cerrado (`#594`), y la
   corre el owner o se pide permiso (el clasificador deniega escrituras remotas):
   `ssh jumpweb-prod "printf '%s\n' 'v1.0.0 1272cb93 <fecha-UTC>' > public_html/storage/app/version"`; o se
   deja al noveno despliegue, que ya la escribe solo y exige `/release` antes (guarda 8). Después, marcar F3
   en el tracker y en la spec §4.9. Las skills `/release` y `/desplegar` del plugin ya dicen `1272cb93` y
   nombran la guarda 8 (plugin `627b3a3`, empujado, arnés 43/43): **el paso (0) de arriba instala ahora
   `627b3a3`, no `c57c9f2`**.
3. **`#625` · la mitad que falta: ESLint** sobre `resources/js/sidebar/` con las reglas de Vue y su línea base,
   midiendo antes de activar (segundos de gate, tamaño de la línea base) y con su paso en el `pre-push` y su
   caso en `PrePushGateTest`. ⚠️ `package.json` es compartido: el aviso al SPA está en el buzón desde el 17-09;
   antes de tocarlo, `git fetch` y mirar si `carriles/spa.md` contesta o tiene `package.json` a medias.
   **Larastan YA ESTÁ** (17-09): `larastan/larastan` 3.12.1 (tres paquetes de desarrollo en el lock, nada más
   se movió), `phpstan.neon` en nivel 5 sobre `app/`, **línea base de 459 errores** (333 entradas, 86 KB; los
   más repetidos `property.notFound` 99, `nullCoalesce.offset` 86, `nullsafe.neverNull` 82), **10 s en frío
   y 2 s con caché**, paso en el `pre-push` detrás de Pint, `StaticAnalysisGateTest` (nivel, rutas, extensión,
   línea base con TRINQUETE que solo baja, orden) y `scripts/mutar-analisis-estatico.sh` **8/8** con los cuatro
   ficheros restaurados byte a byte. Los dos `.neon` excluidos del `rsync` del despliegue. ▶ Deuda que abre:
   bajar la línea base por familias (empezar por `nullsafe.neverNull`, que es mecánico), siempre bajando
   `FROZEN_ERRORS` en el mismo commit.
4. Después F4 (cajón empaquetable y token; la parte de la API empieza por `/spec`, toca `RGPD-06` y `SEC-06`)
   → F5 (instancia PlayJump, v2.0.0; abre con el censo de Zones y de «redes») → F6 (app nativa, spec).
- **Del owner**: pegar las cuatro reglas de `#626` en `~/.claude/settings.json` (y arreglar el enlace
  `~/.local/bin/claude`) · la pila de la app (F6a) · las dos de F5, que se le llevan con el censo hecho.
- **El plan de la noche del 17-09** (parque cerrado a las 21:30), en sesión NUEVA con el plugin actualizado:
  «Hola, lee la doc y arranca» → la línea de `storage/app/version` en producción → «¿esto está hecho de
  verdad?» (`dod`) → «queda decidido: …» (`decision`, p. ej. la pila) → «esto hazlo en ligero: …» (`ligero`)
  → «cerramos por hoy, haz el handoff» (`handoff`). El agente comprueba cada disparo en la transcripción y
  anota el recuento en la spec §6.

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` (cada carril escribe SUS entradas) · la estructura de
`docs/carriles/` (cada carril SU fichero) · `scripts/docs-check.sh` · `.githooks/pre-push` ·
`scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `.claude/skills/` · `scripts/deploy.sh` (la
guarda 8) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`scripts/mutar-analisis-estatico.sh` · `StaticAnalysisGateTest`. Todo lo anterior es
COMPARTIDO por naturaleza: un cambio de forma se anuncia en el buzón antes de empujarlo.

## Trampas de este carril

- El harness en modo «auto» ordena preferir Bash a Read/Edit/Write; manda la regla 8 de `CLAUDE.md`.
- `git show HEAD~N:docs/DECISIONES.md` (antes de F1) es el registro único de antes de la partición;
  `git log -p docs/ESTADO.md` y `docs/00-REFACTOR.md` hasta el commit de F1 son el histórico que se borró.
- Dos mediciones de F1 salieron FALSAS por el instrumento: un `&&` tras un `ls` que falla se traga el resto
  de la línea, y un `awk` sobre `## #N` con `tr -d '#'` deja un espacio delante y lee el campo vacío
  («536 entradas en 000–099»). Se repitieron con el campo correcto antes de creerlas.
- La guarda de idempotencia de un anexo era por FICHERO y dos filas comparten spec: la huella se quedó en
  944/974 hasta hacerla por FILA. Un instrumento que dice 96,9 % también hay que leerlo.
- **El clasificador del modo «auto» deniega escribir hooks, manifiestos de marketplace, mapas de disparo y
  reglas que inyecten contexto en sesiones futuras** («self-modification», «instruction poisoning»,
  «unauthorized persistence»), aunque el owner lo haya pedido en la spec. No se rodea con `cp` por Bash: se
  hace lo demás, se para y se le pide permiso (o una regla `Write` para el repo del plugin).
- `rm -rf` está en el deny del repo y **un comando compuesto que lo lleve dentro se deniega entero**: carpeta
  nueva en vez de borrar. Y `claude plugin details` no acepta `--plugin-dir`; `claude -p … --plugin-dir` sí.
- **El clasificador «auto» deniega `scp` y el `--go` del despliegue** («Remote Shell Writes») aunque deja pasar
  el `ssh … bash -s` de la copia previa y los `ssh` de lectura. No se rodea con `ssh 'cat >'`: se hace lo demás,
  se para con los dos comandos escritos y el owner cambia el modo de permisos (16-09). También denegó un `ssh`
  de lectura con un bucle `for` sobre carpetas: un `ssh` simple con tres comandos pasó.
- Un `git diff --stat … | tail -15` sobre 19 ficheros esconde cuatro y parecen ajenos al plan del `rsync`: contar
  con `--numstat | wc -l` antes de creer que faltan.
- **`claude plugin …` desde el agente lo deniega el clasificador «auto»** («Self-Modification»), igual que escribir
  hooks: actualizar o instalar el plugin lo hace el owner con `/plugin …` en su sesión. Además, en la shell del
  harness `claude` da «command not found» aunque `~/.local/bin` está en el PATH: **el enlace
  `~/.local/bin/claude` está roto** (apunta a la extensión 2.1.263, retirada); el binario vivo es
  `~/.vscode-server/extensions/anthropic.claude-code-<v>/resources/native-binary/claude` (hoy 2.1.273).
- **«The command 'docker' could not be found in this WSL 2 distro» es Docker Desktop APAGADO**, no una
  instalación rota: se arranca desde WSL con `"/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"` en
  segundo plano y se espera con un `until docker info` (medido el 17-09: el stack y la web en 200 en ~1 min).
- **Un test que mira una «casi versión» tiene que EMPUJARLA antes de medir**: una etiqueta ligera o `v1.0` sin
  empujar aborta por «no está en origin» y el caso sale verde sin haber mirado el nombre ni el tipo.
- El push de una ETIQUETA no pasa por el gate (`pre-push` solo mira `refs/heads/main`): es a propósito, pero
  por eso `/release` exige que el commit etiquetado ya esté en `origin/main`.

## Buzón

### Para el carril del SPA (emisor: plataforma, 2026-09-16)
- F1 reescribió `CLAUDE.md`, `docs/ESTADO.md`, `docs/00-REFACTOR.md` y `docs/CONVENCIONES.md`, y partió
  `DECISIONES.md`: **tus entradas van al final de `docs/decisiones/500-599.md`**, con tu banda. Haz
  `git pull --rebase` antes de nada.
- **La línea «Suite N en verde» ya no existe**: el contador va en el trailer del commit y el `pre-push` lo lee
  de ahí (`#618`). `CARRIL-SPA.md` §5 lo decía al revés y está corregido.
- Tu fichero es **`docs/carriles/spa.md`**: lo escribí yo desde tu bloque del estado del 13-09. Hazlo tuyo en
  tu siguiente cierre; nadie más lo toca. Los `§0` de `sidebar-spa.md` y `celebracion-e-invitacion.md` también
  los escribí yo desde tus filas del enrutador: revísalos.
- **Tu arreglo de `#571` ESTÁ DESPLEGADO** desde el 16-09 a las 22:53 (octavo despliegue, `ENTORNOS.md` §6),
  con las cuatro líneas del `client.css` (`687ffcb3…` servido) y el `gf-form` en la vista de producción: cambia
  «sin desplegar» en tu foto y marca la casilla de tu bloque del tracker, que ya está en `[x]`. `/bar` da 503 en
  producción por `maintenance.page.bar = 1`, un dato del panel. ⚠️ **La rama `cliente/playjump` iba por detrás
  en `--money`**: tu commit `dface9c4` añadió los cuatro tokens sobre una copia con Lima 800, y `#540` (12-09)
  manda Lima 700; corregido en `3ded45ee`. Antes de tocar el `client.css` de la rama, compárala con la copia local.
- **Llega la capa de agente (F2, `#623`)**: el plugin `jumpweb-agente` con `/carril` (en vez de
  `/arranque-sesion`), `/handoff` (en vez de `/cierre-sesion`) y nueve más, y un hook que inyecta las reglas
  del owner en cada sesión. Cuando esté en GitHub, tu máquina lo instalará al confiar en la carpeta; hasta
  entonces sigues con las skills viejas. **Toqué `CARRIL-SPA.md`** (§1, paso 8 nuevo, y la primera línea de
  §6) y el enrutador ya nombra las skills nuevas. Detalle: `sistemas/CAPA-DE-AGENTE.md`.
- **(17-09) El plugin YA está en GitHub (`627b3a3`)**: tras tu `git pull`, sesión nueva y confiar en la carpeta.
  Si no aparece `/carril`, la receta es por TERMINAL (`claude plugin …`; en VSCode `/plugin` no existe) y la
  corre el owner: volví a tocar tu `CARRIL-SPA.md` §1 paso 8 para decirlo. Tu primer prompt: «Hola, lee la doc
  y arranca».
- **(17-09) AVISO ANTES DE TOCAR LO COMPARTIDO — `package.json`** (`#625`, `[DECIDIDO owner]`): voy a añadir
  ESLint con las reglas de Vue como dependencia de desarrollo, con una línea base que congela lo que hay en
  `resources/js/sidebar/` (solo bloqueará errores NUEVOS) y un paso en el `pre-push`. No cambia ningún fichero
  tuyo. Si tienes `package.json` o `package-lock.json` a medias, empuja antes o dímelo en tu buzón.
- **(17-09) Producción despliega solo etiquetas** (guarda 8, `#624`): tu próximo arreglo llega a producción
  con `/release` delante; staging sigue desplegando `main`.

### Atendido
- Nada todavía.
