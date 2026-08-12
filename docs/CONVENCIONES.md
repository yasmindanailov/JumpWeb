# Convenciones — JumpWeb

> Reglas de organización y método para trabajar en este repo. **En JumpWeb desarrollan al
> 100% agentes IA** (Claude Fable 5 / Opus): estas convenciones son el contrato que hace
> posible que sesiones distintas, de modelos distintos, produzcan un trabajo coherente.
> Idioma de la doc y de los mensajes de commit: **español**.

## §1 Estructura (qué va dónde)
- `CLAUDE.md` (raíz) — enrutador de contexto + reglas de arranque. CORTO; se carga solo.
- `README.md` (raíz) — puesta en marcha técnica (Docker, comandos, puertos).
- `docs/` — fuente de verdad de producto y arquitectura. Índice en `docs/README.md`.
- `docs/sistemas/` — referencia por sistema implementado (Redsys, cookies, post-form…).
- `.claude/skills/` — procedimientos operativos invocables por los agentes.

## §2 Documentos clave (jerarquía de lectura)
1. `ESTADO.md` — foto viva mínima: dónde estamos / qué sigue. **Carga obligatoria al arrancar.**
2. `00-REFACTOR.md` — tracker VIVO del refactor (fases + checklists).
3. `DECISIONES.md` — cronológico, el porqué de cada decisión. **No cargar entero: buscar por número.**
4. `INVARIANTES.md` — lo que NUNCA se puede regresar (endurecimiento heredado). Leer antes de
   tocar dinero, aforo, RGPD o seguridad.
5. El resto, **solo vía la tabla de enrutado de `CLAUDE.md`** (leer lo mínimo que la tarea pida).

## §3 Marcadores
- Índices/trackers: ✅ hecho · 🟦 en curso · ⬜ pendiente · ❗ bloqueado (única leyenda válida;
  la cabecera de una fase debe ser coherente con sus checkboxes — lo comprueba `docs-check`).
- Texto: `[DECIDIDO]` (+fecha) · `[PENDIENTE]` (+ quién resuelve) · `[SUPUESTO]` (a confirmar).
- Fechas SIEMPRE absolutas (`2026-08-12`), nunca «hoy»/«la semana pasada».

## §3.bis Definición de «Hecho» (DoD)
Una tarea solo se marca ✅ si cumple **las tres**:
1. **El código existe** (clases/rutas/migraciones reales, no diseño).
2. **Tiene prueba automática** que la cubre.
3. **Está verificada empíricamente** (render/HTTP/BD reales, no solo la suite) y, si es una
   feature de producto visible, **validada por el owner** (Yasmin).
Si falta algo → 🟦, nunca ✅. Skill de apoyo: `/dod`.

## §3.ter Tests Unit vs Feature
- Servicio/value-object **puro** (sin BD ni facades) → `tests/Unit` extendiendo
  `PHPUnit\Framework\TestCase` (NO `Tests\TestCase`), sin `RefreshDatabase`.
- Todo lo demás (BD/HTTP/Livewire/Filament) → `tests/Feature` con `Tests\TestCase`.
- No migrar lo existente; aplicar a lo nuevo.

## §4 Contenido de la doc
- **Una sola fuente de verdad por hecho**: no duplicar; enlazar con rutas relativas.
- **Citas entre docs**: por número de sección (`CONVENCIONES §7`), nunca por nombre libre.
  **Citas de código**: por símbolo (`Redsys::executeRefund`, «payload de la ida»), NUNCA
  `fichero:línea` (los números de línea derivan en silencio; `docs-check` los rechaza).
- **Cifras factuales** (recuentos, tamaños): acompañadas del comando reproducible que las
  produce, o sustituidas por él. Una foto sin receta es drift en espera. Los recuentos
  canónicos usan formas fijas que `docs-check` verifica contra el código: `N modelos ·
  N migraciones`, `N Filament Resources`, `N invariantes de no-regresión`; un aproximado
  deliberado lleva `~`. Rutas de código aún no existentes o de ejemplo: marca la línea con
  `(futuro)` o `(ejemplo)` para eximirla del gate.
- Datos de negocio desconocidos → placeholder + `[PENDIENTE]`; los valores reales viven en
  BD/panel (data-driven), jamás quemados en código.
- Documentos cortos y enfocados: la doc es contexto de agentes; cada token cuenta.

## §5 Flujos obligatorios de actualización
- **Decisión nueva** → `[DECIDIDO]`+fecha en el doc afectado **y** línea en `DECISIONES.md`.
- **Doc nuevo/renombrado** → actualizar `docs/README.md` **y** la tabla de enrutado de `CLAUDE.md`.
- **Fin de sesión** → skill `/cierre-sesion`: progreso en `00-REFACTOR.md` + `ESTADO.md` fiel.
- **`[PENDIENTE]` cerrado** → resolverlo en su doc y reflejarlo en `ESTADO.md`.
- **Precedencia de estado** (`DECISIONES #10`): los marcadores de fase de `00-REFACTOR.md`
  son LA fuente de verdad; `ESTADO.md` los resume y nunca puede contradecirlos.
- **Gate documental**: `scripts/docs-check.sh` (enlaces, anclas, rutas citadas, recuentos,
  coherencia de fases) corre en el pre-push de `main` y en `/cierre-sesion`. Doc roto = push
  bloqueado, igual que la suite.

## §6 Principios rectores (no romper)
Data-driven · white-label (1 instalación/cliente) · API-first · corrección antes que
presentación · **convivencia**: no romper supuestos del sector origen documentados
(`OPERATIVA-SECTOR-ORIGEN.md`) sin decisión explícita.

## §7 Protocolo de arranque y verificación (CRÍTICO en un repo 100% agentes)
1. `CLAUDE.md` se carga solo → lee `docs/ESTADO.md` → la fila de enrutado de tu tarea. Nada más.
2. **Verifica antes de fiarte**: todo ✅ en prosa se contrasta con el código (rutas, clases,
   migraciones) antes de construir encima. La doc portada describe la BASE HEREDADA y puede
   estar por detrás del refactor.
3. **Empirismo**: afirma solo lo comprobado (suite, curl, render, BD). Si no lo comprobaste,
   dilo («no verificado»).
4. **Nada vive solo en la conversación**: si descubres un gotcha, una decisión o un hueco,
   escríbelo en el doc que toque ANTES de cerrar la sesión. El siguiente agente no puede
   preguntarte: tu handoff es lo único que tiene. Un `ESTADO.md` infiel es el peor bug.
5. **No tocar el repo del cliente origen** (`~/proyectos/jumpingjump`) desde una sesión de
   JumpWeb; cruces de mejoras solo por decisión explícita del owner (`DECISIONES #1`).

## §8 Git
- Mensajes convencionales en español (`feat:`, `fix:`, `docs:`, `refactor:`…), cuerpo con el
  porqué. Commit al cerrar una unidad de trabajo con la suite en verde; **push** de `main`
  al cerrar la sesión.
- No commitear con la suite rota. Si hay que aparcar, rama `wip/…` y anotarlo en `ESTADO.md`.
- **El gate de pre-push aplica solo a `main`** (`DECISIONES #10`): las ramas `wip/…` pueden
  empujarse en rojo como copia de seguridad — nunca se mergean a `main` sin pasar el gate.
- **Una sola sesión de escritura a la vez** sobre el repo; subagentes de solo-lectura exentos.
