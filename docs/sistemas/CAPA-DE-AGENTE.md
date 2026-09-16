# La capa de agente — el plugin `jumpweb-agente`

> Estado: vivo · Última actualización: 2026-09-16 · Verificado contra código: 2026-09-16 (plugin en
> `~/proyectos/jumpweb-agente`; hooks medidos con su arnés, 38/38, y en una sesión real `claude -p --plugin-dir`
> sobre este repo) · Se invalida si: cambia el formato de plugins o hooks de Claude Code, la lista de skills o
> la de momentos. Decisiones: `#614` (qué es y por qué) · `#623` (la forma que tomó). Diseño:
> `docs/specs/producto-e-instancias.md` §4.7.

## 1 · Qué es y dónde vive
- Un **plugin de Claude Code** con las skills y los hooks del protocolo de trabajo, instalado en las dos
  máquinas del owner y activado por repo. Repo privado `yasmindanailov/jumpweb-agente` (clonado en
  `~/proyectos/jumpweb-agente` en cada máquina), que es a la vez el **marketplace**
  (`.claude-plugin/marketplace.json`) y la casa del plugin (`plugins/jumpweb-agente/`: `.claude-plugin/plugin.json`,
  `skills/<skill>/SKILL.md`, `hooks/hooks.json` con sus guiones Python, `reglas/owner.md`, `reglas/momentos.json`;
  el arnés `pruebas/probar-hooks.sh` en la raíz del repo).
- Por qué un plugin y no copias por repo: las copias se separan sin que nadie lo vea (las reglas del owner
  vivían en la memoria de UNA máquina y se copiaron a mano a `docs/CARRIL-SPA.md` §6). Por qué un repo aparte y
  no dentro de `main`: sirve a tres repos (producto, instancias, app).
- Las skills de un plugin se llaman `/jumpweb-agente:<skill>` **y también `/<skill>`** mientras ningún otro
  comando use ese nombre (documentación de Claude Code, verificada el 2026-09-16). Hasta cerrar F2 conviven con
  `arranque-sesion`, `cierre-sesion` y `dod` de `.claude/skills/` (`/dod` a secas resuelve al del repo mientras
  tanto); se retiran con el 6 de 6.

## 2 · Las skills (once)
| Skill | Sustituye o cubre |
|---|---|
| `carril` | `/arranque-sesion` + el estándar de `#614`: dependencias medidas (`scripts/module-deps.php`), guardas de frontera, spec antes que código si la arquitectura del subsistema es mejorable, recibo de arranque |
| `handoff` | `/cierre-sesion` + el ciclo de vida de la doc (CONVENCIONES §11), `git add` por nombre, trailer con evidencia y modo, re-medir la suite tras rebasar |
| `decision` | Entrada en la banda del carril, ≤ 1,5 KB, «último usado», `[DECIDIDO]` en el doc afectado, «Sustituida por» |
| `ligero` | Perfil rápido con recibo en el trailer («Modo: ligero · omitido: …»); se prohíbe a sí mismo sobre el `CRITICAL_RE`, `openapi/v1.yaml` y migraciones de dinero o aforo |
| `spec` | Spec desde la plantilla con su §0 ≤ 2 KB, alta en `docs/README.md` y en una línea de `CLAUDE.md` |
| `release` | Semver (`#613`), `CHANGELOG.md` con dos mitades, etiqueta anotada y empujada; producción solo etiquetas |
| `mutar` | Arnés de mutación con las diez reglas pagadas; molde `scripts/mutar-invitados-post-form.sh`; árbol byte a byte al terminar |
| `desplegar` | Runbook sobre `scripts/deploy.sh` y `docs/ENTORNOS.md` §6: de noche (`#594`), etiqueta, copia previa, tema del cliente por hash, verificación y registro |
| `sonda` | La receta del navegador (Chromium en el contenedor, `socat`, `playwright-core` con `--no-save`) y sus trampas medidas |
| `instancia` | Hoy: el paquete del cliente desde `cliente/<slug>` con `git archive`, nunca `checkout`; mañana (F5): repo desde plantilla y versión del contrato |
| `dod` | Los cuatro criterios de «Hecho» (CONVENCIONES §3.bis) + el ciclo de vida al cerrar una spec |

## 3 · El disparo sin barra, en tres capas
1. **La descripción de cada skill**: situaciones y frases del owner (`description` + `when_to_use`, techo de
   1.536 caracteres en el listado que ve el modelo; hoy entre 628 y 915).
2. **La tabla momento → skill del enrutador** (`CLAUDE.md`), para cuando el hook no esté.
3. **Los hooks** (deterministas, Python 3 de biblioteca estándar, fail-open: sin git, sin JSON o con error
   salen en silencio):
   - `SessionStart` (`startup|resume|clear|compact`): la foto del repo (rama, árbol, commits sin empujar,
     hook), la orden «ejecuta /carril» (en `resume`, «comprueba con /carril»; en `compact` solo las reglas) y
     **las reglas del owner** (`reglas/owner.md`). En un subagente no escribe nada.
   - `UserPromptSubmit`: casa el mensaje (minúsculas, sin acentos) contra `reglas/momentos.json` e inyecta
     hasta dos sugerencias «ejecuta AHORA la skill /x». Nunca bloquea; un `/comando` explícito no recibe sugerencia.
     Un momento `una_vez_por_sesion` (hoy solo `carril`) calla si la transcripción (`transcript_path`) ya tiene
     la skill invocada, por el modelo (`"skill":"jumpweb-agente:carril"`) o tecleada (`<command-name>/carril`).
   - `Stop`: si hay commits sin empujar, bloquea UNA vez por estado (HEAD + cuenta) con la orden de decirlo en
     una línea; con `stop_hook_active` calla. Medido en la documentación de hooks: en `Stop` el owner no ve
     stdout ni `systemMessage`; solo un bloqueo le llega. Un árbol sucio no avisa: es trabajo normal.

El mapa vive en un solo sitio, `reglas/momentos.json`; esta tabla es su copia legible:

| Momento | Frases del owner (ejemplos) | Skill |
|---|---|---|
| arrancar o retomar | «lee la doc y arranca» · «empezamos» · «continuamos con F2» · «retoma el carril» | `carril` |
| cerrar | «cerramos» · «haz el handoff» · «lo dejamos aquí» · «guarda y empuja» | `handoff` |
| desplegar | «despliega a producción» · «súbelo a staging» | `desplegar` |
| decidir | «queda decidido: …» · «registra la decisión» | `decision` |
| diseñar antes | «hazme la spec» · «diséñalo antes de tocar código» | `spec` |
| ir rápido | «en ligero» · «sin ceremonia» · «rápido» | `ligero` |
| ¿hecho? | «¿está hecho de verdad?» · «¿lo damos por terminado?» | `dod` |
| versión | «saca la versión» · «etiqueta la v1.0.0» · «changelog» | `release` |
| guarda que muerde | «corre la mutación» · «¿esa guarda muerde?» | `mutar` |
| navegador | «mídelo en el navegador» · «captura en móvil» · «pasa la sonda» | `sonda` |
| cliente | «aplica el paquete del cliente» · «instancia nueva» | `instancia` |

## 4 · Instalar, actualizar, probar
- **Automático**: `.claude/settings.json` del producto declara el marketplace en `extraKnownMarketplaces`
  con la forma que escribe el propio CLI, `{"source": "git", "url": "https://github.com/yasmindanailov/jumpweb-agente.git"}`
  (HTTPS porque el owner usa credenciales HTTPS de git, no SSH), y `enabledPlugins`
  `"jumpweb-agente@jumpweb-agente": true`. Al confiar en la carpeta del repo, Claude Code lo añade e instala.
  Hecho en la primera máquina el 2026-09-16 con `claude plugin marketplace add <url .git>` (registro en
  settings de usuario) y `claude plugin install jumpweb-agente@jumpweb-agente --scope project` (escribe
  `enabledPlugins` en el proyecto); el plugin queda en `~/.claude/plugins/cache/jumpweb-agente/…/<sha>`.
- **A mano**: `/plugin marketplace add https://github.com/yasmindanailov/jumpweb-agente.git` y
  `/plugin install jumpweb-agente@jumpweb-agente`. Desarrollo local: `/plugin marketplace add
  ~/proyectos/jumpweb-agente` (mismo nombre: sustituye al de GitHub en esa máquina).
- **Actualizar**: `/plugin marketplace update jumpweb-agente`. Sin `version` en el manifiesto a propósito: la
  versión instalada es el sha del commit (`claude plugin list` lo enseña), así que cada push es una versión.
  La actualización en segundo plano no autentica en repos privados por HTTPS: se hace a mano. Tras escribir o
  cambiar hooks, abrir `/hooks` una vez (spec §4.10).
- **Probar**: `bash pruebas/probar-hooks.sh` (43 casos: los tres eventos, los once momentos, el «una vez por
  sesión» de `carril`, el dedupe del Stop, fail-open; veredicto por código de salida) y una sesión real sin instalar nada, desde la raíz del repo:
  `claude -p "…" --plugin-dir ~/proyectos/jumpweb-agente/plugins/jumpweb-agente --max-turns 1`.
- **Salida de F2**: las seis frases del README del plugin (arrancar · cerrar · decidir · ligero · ¿hecho? ·
  desplegar), en sesión NUEVA de cada máquina, 6 de 6.

## 5 · Lo que sigue siendo gate del repo, no skill
`scripts/guard-bash.sh` (PreToolUse, declarado en `.claude/settings.json`), `.githooks/pre-push` (docs-check +
Pint + build + suite + el contador del trailer) y `scripts/docs-check.sh` (once comprobaciones con techos).
Una skill se puede no invocar; un gate no.

## 6 · Trampas medidas al construirlo (2026-09-16)
- **El clasificador del modo «auto» del harness deniega escribir los ficheros que inyectan contexto en sesiones
  futuras**: `hooks/hooks.json`, `.claude-plugin/marketplace.json`, `reglas/momentos.json`, `reglas/owner.md`,
  `hooks/comun.py` y el `README.md` con la receta de instalación, con los motivos «self-modification»,
  «instruction poisoning» y «unauthorized persistence». Las once skills y los tres guiones de evento sí pasaron.
  Se resuelve con permiso explícito del owner en esa sesión (o una regla `Write` para el repo del plugin),
  nunca con un `cp` por Bash que rodee la denegación: la intención del clasificador es que lo apruebe una persona.
  Así se hizo el 16-09: con el permiso dado en el chat, los mismos Write pasaron a la primera.
- `jq` no está en las máquinas: los hooks van en Python 3, que `scripts/guard-bash.sh` ya exige.
- En `Stop` nada llega al owner salvo un bloqueo: por eso avisa así, y solo de commits sin empujar.
- `claude plugin details` no acepta `--plugin-dir`; la sesión `-p` con `--plugin-dir` sí carga el plugin entero
  (skills y hooks) y es la prueba empírica que vale antes de instalar.
- **El hook no sabe en qué punto de la sesión está**: «vamos con F2, continuamos» a mitad de sesión (16-09, con el
  plugin ya instalado) volvió a ordenar `/carril` entero, y un agente obediente repite fetch, gates y lecturas.
  La memoria de sesión que sí tiene es la transcripción: `carril` es «una vez por sesión» desde ese día.
- **El clasificador «auto» también deniega las escrituras remotas de un despliegue** (`scp`, `deploy.sh --go`;
  «Remote Shell Writes») aunque deja pasar `ssh … bash -s` y los `ssh` de lectura; el owner cambia el modo de
  permisos para ese paso (`ENTORNOS.md` §6, octavo despliegue). Dato para su decisión pendiente sobre el modo.
