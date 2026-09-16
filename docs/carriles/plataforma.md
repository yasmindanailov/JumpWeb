# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador** (`~/proyectos/JumpWeb`) · Banda: **610–639** · Último usado: **`#621`** ·
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
- **Medido al cerrar F1** (`wc -c`, 2026-09-16): `CLAUDE.md` 12.249 B (era 316.874) · `00-REFACTOR.md` 11.409
  (era 390.052) · `ESTADO.md` 2.679 (era 870.051) · el carril más grande (`web.md`) 7.542 · el §0 más grande
  2.039 · **arranque en frío 35.918 B** (enrutador + índice + carril + tracker + un §0; era 1,57 MB). Las tres
  mutaciones de tamaño (enrutador, §0, decisión) pusieron el gate en rojo y se restauraron byte a byte; la
  huella se reproduce sobre el enrutador histórico con `git show <sha>:CLAUDE.md` y `--enrutador`.

## Por dónde retomar, en orden

1. **F2 · la capa de agente** (spec §4.7): el plugin `jumpweb-agente` (repo privado como marketplace) con las
   skills `carril` (sustituye a `arranque-sesion`), `handoff` (a `cierre-sesion`), `decision`, `ligero`, `spec`,
   `release`, `mutar`, `desplegar`, `sonda`, `instancia` y `dod`; los hooks `SessionStart` («ejecuta
   /carril»), `UserPromptSubmit` (sugiere la skill) y `Stop` (avisa si hay cambios sin cierre); salida: seis
   frases del owner en sesión nueva de cada máquina, 6 de 6, y `/hooks` abierto una vez tras escribirlos.
   ⚠️ Hasta entonces `arranque-sesion` y `cierre-sesion` siguen, y ya apuntan a los carriles.
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

### Atendido
- Nada todavía.
