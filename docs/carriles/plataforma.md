# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador** (`~/proyectos/JumpWeb`) · Banda: **610–639** · Último usado: **`#623`** ·
> Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) · Actualizado: 2026-09-16.
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
  ❗ **Seis ficheros del plugin NO están en su repo**: el clasificador del modo «auto» denegó escribirlos
  (`.claude-plugin/marketplace.json`, `hooks/hooks.json`, `hooks/comun.py`, `reglas/owner.md`,
  `reglas/momentos.json`, `README.md`). Sus copias exactas quedaron en el scratchpad de la sesión
  (`…/scratchpad/denegados/`, se pierde con ella); su comportamiento está especificado por el arnés (que SÍ
  está en el repo) y por `sistemas/CAPA-DE-AGENTE.md` §3, así que se reescriben desde ahí si hace falta. El
  `.claude/settings.json` del producto (marketplace + `enabledPlugins`) se toca en la sesión 2, cuando el repo
  exista en GitHub: antes daría error en cada arranque.

## Por dónde retomar, en orden

0. **El octavo despliegue a producción, CON EL PARQUE CERRADO** (de noche; cierra a las 21:30 entre
   semana). Está preparado y ensayado el 16-09 a las 20:30 y no se hizo porque el parque estaba abierto
   (`#594`). Sube `448ea4f5` (`#569`–`#571`: el arreglo del número de invitados del post-form, defecto vivo en
   producción desde el 08-09), sin migraciones. **La receta completa, con los hashes y los controles, está en
   `ENTORNOS.md` §6** (bloque «OCTAVO DESPLIEGUE»): copia previa con `scripts/copia-bd-remota.sh` → `scp` del
   `client.css` de la rama `cliente/playjump` (`3ded45ee`, sha1 `687ffcb3…`) → `deploy.sh --go` → verificar.
   Al terminar: registrar el resultado en ese bloque y avisar al SPA en el buzón (su foto dice «sin desplegar»).
1. **F2 · sesión 2, la capa de agente** (spec §4.7, `#623`, `sistemas/CAPA-DE-AGENTE.md`), en este orden:
   (a) el owner concede escribir los seis ficheros denegados en `~/proyectos/jumpweb-agente` (o los copia él
   desde el scratchpad de la sesión 1 si sigue viva; si no, se reescriben desde `sistemas/CAPA-DE-AGENTE.md`
   §3 y el arnés) → `bash pruebas/probar-hooks.sh` en verde → primer commit del plugin; (b) el owner crea el
   repo privado `yasmindanailov/jumpweb-agente` en GitHub (no hay `gh` en la máquina) y se empuja
   (`git remote add origin https://github.com/yasmindanailov/jumpweb-agente.git && git push -u origin main`);
   (c) `.claude/settings.json` del producto: `extraKnownMarketplaces` con fuente `url` HTTPS + `enabledPlugins`
   `jumpweb-agente@jumpweb-agente`; (d) en cada máquina, si no se instala solo al confiar en la carpeta,
   `/plugin marketplace add …` + `/plugin install …`, y `/hooks` abierto una vez; (e) **la prueba de las seis
   frases** (README del plugin) en sesión nueva de cada máquina, 6 de 6, anotada en la spec §6; (f) retirar
   `.claude/skills/{arranque-sesion,cierre-sesion,dod}` y sus menciones (enrutador, CONVENCIONES §1 y §5,
   `CARRIL-SPA.md` §1) y cerrar F2 en el tracker. ⚠️ Hasta (f), `arranque-sesion` y `cierre-sesion` siguen.
2. **F3 · versión**: v1.0.0 sobre `b0ea5a16` (producción del 13-09), `CHANGELOG.md` con dos mitades, guarda 8
   del despliegue (producción solo etiquetas). Después F4 (cajón empaquetable y token) → F5 (instancia
   PlayJump, v2.0.0) → F6 (app nativa, spec).
- **Del owner**: la pila de la app (F6a) · las herramientas de análisis estático (dependencia nueva,
  `CONVENCIONES §9`) · el modo de permisos del harness · si Zones pierde sus campos de landing y si «redes»
  se va (F5).

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` (cada carril escribe SUS entradas) · la estructura de
`docs/carriles/` (cada carril SU fichero) · `scripts/docs-check.sh` · `.githooks/pre-push` ·
`scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `.claude/skills/`. Todo lo anterior es
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
- **Tu arreglo de `#571` sigue SIN desplegar** (el 16-09 el parque estaba abierto): va en el siguiente
  despliegue, receta en `ENTORNOS.md` §6. ⚠️ **La rama `cliente/playjump` iba por detrás en `--money`**: tu
  commit `dface9c4` añadió los cuatro tokens sobre una copia con Lima 800, y `#540` (12-09) manda Lima 700;
  corregido en `3ded45ee`. Antes de tocar el `client.css` de la rama, compárala con la copia local.
- **Llega la capa de agente (F2, `#623`)**: el plugin `jumpweb-agente` con `/carril` (en vez de
  `/arranque-sesion`), `/handoff` (en vez de `/cierre-sesion`) y nueve más, y un hook que inyecta las reglas
  del owner en cada sesión. Cuando esté en GitHub, tu máquina lo instalará al confiar en la carpeta; hasta
  entonces sigues con las skills viejas. **Toqué `CARRIL-SPA.md`** (§1, paso 8 nuevo, y la primera línea de
  §6) y el enrutador ya nombra las skills nuevas. Detalle: `sistemas/CAPA-DE-AGENTE.md`.

### Atendido
- Nada todavía.
