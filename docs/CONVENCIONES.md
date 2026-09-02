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
Una tarea solo se marca ✅ si cumple **las cuatro**:
1. **El código existe** (clases/rutas/migraciones reales, no diseño).
2. **Tiene prueba automática** que la cubre.
3. **Está verificada empíricamente** (render/HTTP/BD reales, no solo la suite) y, si es una
   feature de producto visible, **validada por el owner** (Yasmin).
4. **La doc del sistema tocado refleja el cambio** (o «N/A» explícito) — al terminar la
   tarea, no al cierre de sesión.
Si falta algo → 🟦, nunca ✅. Skill de apoyo: `/dod`.

## §3.ter Tests Unit vs Feature
- Servicio/value-object **puro** (sin BD ni facades) → `tests/Unit` extendiendo
  `PHPUnit\Framework\TestCase` (NO `Tests\TestCase`), sin `RefreshDatabase`.
- Todo lo demás (BD/HTTP/Livewire/Filament) → `tests/Feature` con `Tests\TestCase`.
- No migrar lo existente; aplicar a lo nuevo.

## §3.quater Auditar un test antes de retirarlo (Fase 4 · `#75`→`#98`)

Al retirar una superficie vieja, cada test suyo se clasifica **por su SUJETO, no por la regla que
menciona** (`#87`): una regla que sobrevive no salva un caso que prueba una superficie que se va.
Tres categorías: lo que **compara entre superficies** (muere), lo que **afirma del contrato** (se
queda) y lo que usa la vieja como **intermediario de una fuente que sobrevive** (se re-apunta — y casi
siempre mejora el test). El tercero hay que buscarlo activamente.

**Y se hace MUTANDO, no leyendo.** Una nota escrita leyendo el código es una hipótesis, no un plan
(`#83`). Seis trampas, todas nacidas de errores reales:

1. **Comprueba DÓNDE cayó la mutación** (`#77`): un nombre puede aparecer dos veces en el fichero.
2. **Comprueba el CONTENIDO, no el código de salida** (`#90`): `git checkout` no revierte un fichero
   sin trackear, y la mutación se queda dentro.
3. **Exige que el ancla sea ÚNICA antes de creerte un hueco** (`#92`): una mutación mal apuntada puede
   dar un resultado **coherente con la hipótesis equivocada**.
4. **No mutes UNA rama de una propiedad de indistinguibilidad** (`#95`): si dos respuestas son iguales
   a propósito —anti-enumeración—, el mutante es equivalente por diseño y su verde no dice nada.
5. ❗ **Restaurar el fichero NO basta: hay que TOCAR SU FECHA** (`#219`). `shutil.move` y `cp -p`
   **conservan el mtime**, y toda caché que se guíe por fecha —Blade la primera— compara «¿es el
   fuente más nuevo que lo que compilé?». Durante la mutación el fichero queda con fecha de AHORA y
   contenido MUTADO: si algo pide la página en ese instante, el compilado guarda **el fallo**. Al
   restaurar, la fecha vuelve a ser la vieja y **el compilado con la mutación gana para siempre** —
   la web sirve el defecto con el fichero correcto en disco, y el test que lo caza vuelve a estar
   verde en la siguiente pasada porque el arnés ya restauró. ▶ Medido: así se perdió la etiqueta
   «MENÚ» del armazón y **solo lo vio el ojo del owner en una captura**. `os.utime(path, None)` al
   restaurar, y `php artisan view:clear` si ya pasó.
6. **Un ancla que ya no existe no es una mutación que no muerde**: si el arnés dice «NO APLICA»,
   el código cambió debajo. Es una señal, no un aprobado — y hay que distinguirla del rojo, o una
   guarda sin ejercitar pasa por probada.

**Dos criterios que se ganaron midiendo:**
- **Si un dato viaja al cliente y su único test conduce la superficie vieja, el contrato NO lo está
  fijando.** Así salieron los tres huecos reales de `#89`, `#93` y `#96`.
- **Un «⚠️ sin medir» en un fichero condenado es deuda con fecha de caducidad** (`#93`): si nadie lo
  cierra antes del borrado, la regla se va con el fichero. Las dos veces que se cerró uno, había hueco.

⚠️ **Un valor TRIVIAL no está fijado** (0, `null`, lista vacía): el cruce de dos campos sale verde.
⚠️ **Y medir para no encontrar nada sigue siendo medir** (`#94`): el resultado negativo se anota.

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
  `(futuro)` o `(ejemplo)` para eximirla del gate. ⚠️ **El escape es POR LÍNEA**, así que una
  explicación que ocupe tres líneas lo lleva en las tres. Desde `#159` lo honran también el check
  de anclas y el de citas de test: una entrada que EXPLICA un ancla rota o un test retirado tiene
  que poder escribirlo sin que el gate la castigue.
- **Citas de test en `INVARIANTES.md`: una cita viva es un nombre existente; una mención
  HISTÓRICA va TACHADA** (`~~ClaseTest~~`). La columna «Verificación» es el mapa que lleva a
  la red de cada invariante: si apunta a una clase retirada, la invariante se queda **sin red
  sin hacer ruido** —la cobertura puede seguir viva con otro nombre, pero nadie la encuentra—.
  Ya pasó dos veces (`PAY-04` en `#121`, `SEC-06` al retirar el modal en `#122`).
  ⚠️ **El tachado no es cosmético: es lo que separa las dos cosas para la máquina**, porque en
  prosa se escriben igual. `docs-check` (check 8) lo exige. Solo aplica a `INVARIANTES.md`:
  `DECISIONES` y las specs son narrativa histórica y están llenas de nombres retirados a
  propósito.
- Datos de negocio desconocidos → placeholder + `[PENDIENTE]`; los valores reales viven en
  BD/panel (data-driven), jamás quemados en código.
- Documentos cortos y enfocados: la doc es contexto de agentes; cada token cuenta.
- **Plantilla de cabecera** (todo doc nuevo; los heredados migran al tocarlos, no en barrido):
  `> Estado: vivo|heredado|diseño|congelado · Última actualización: AAAA-MM-DD ·`
  `> Verificado contra código: AAAA-MM-DD (alcance) · Se invalida si: <qué lo dejaría viejo>`
- **Fuentes únicas declaradas**: recuento vivo de la suite → `ESTADO.md` (lo refresca
  `/cierre-sesion` con el run real); puertos y bootstrap → README raíz; recuentos
  estructurales → los verifica `docs-check`. El resto de docs **enlaza, no copia**.

## §5 Flujos obligatorios de actualización
- **Decisión nueva** → `[DECIDIDO]`+fecha en el doc afectado **y** línea en `DECISIONES.md`.
- **Decisión revertida/modificada** → en la entrada ANTIGUA, primera línea: «Sustituida por
  #N (fecha)». Ausencia de marca = vigente (el grep por número nunca devuelve una decisión
  muerta sin saberlo).
- **Diseño previo a implementación** → `docs/specs/<tema>.md` desde la plantilla
  `docs/specs/PLANTILLA.md`; otro agente lo revisa ANTES de escribir código.
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
- **Una sola sesión de escritura a la vez** sobre cada CLON del repo; subagentes de solo-lectura
  exentos. Dos agentes = dos clones, y su coordinación es **§10**.
- **El commit de cierre lleva la evidencia** en el cuerpo: «Verificación: suite N tests /
  M aserciones (Xs) · Pint ✓ · docs-check ✓ · build ✓/N-A» — `git log` como bitácora falsable.

## §9 Cuándo parar y preguntar al owner
El agente decide solo en todo lo demás; **PARA y pregunta** (marca ❗ + `[PENDIENTE: owner]`
en el tracker) ante cualquiera de estos:
1. Relajar, alterar o eliminar una invariante de `INVARIANTES.md`.
2. Revertir o contradecir una decisión numerada de `DECISIONES.md`.
3. Dependencia nueva (composer/npm) o servicio externo nuevo.
4. Borrado de datos o migración destructiva fuera de la BD de dev.
5. Decisiones de producto/alcance: features, precios, textos legales, marca.
6. Cualquier gasto (infra, servicios, licencias).
Feature visible → además validación del owner antes del ✅ definitivo (§3.bis).

## §10 Dos agentes sobre `main` — el canal es el REPO
`[DECIDIDO owner, 2026-08-25]`. Nace del precio pagado en dos días: **siete** colisiones de número
en `DECISIONES.md` y **un defecto arreglado dos veces** en paralelo (`DECISIONES #157`, `#158`).
No hay chat entre agentes: **lo que no está en `origin/main`, el otro no lo sabe.**
1. **Antes de planificar**: `git fetch` y leer el bloque «REPARTO VIGENTE» de `ESTADO.md`.
2. **Reclamar = EMPUJAR**: una tarea es tuya cuando tu fila del reparto está en `origin/main`
   (un commit solo de doc vale; pasa el gate igual). Hasta entonces el otro puede tomarla.
3. **Carriles por FICHERO, no por tema**: cada fila del reparto lista los ficheros/carpetas que
   toca. Entrar en un fichero del carril ajeno exige avisarlo en el reparto ANTES (línea con fecha
   y destinatario) y `git pull --rebase` en cuanto el otro empuje.
4. **Los mensajes entre agentes** van en ese bloque de `ESTADO.md` («▶ Para el agente del X: …»).
   Quien lo lee y actúa **lo retira** en su siguiente push: un aviso resuelto que se queda es ruido.
5. **Empujar PRONTO** —cada unidad de trabajo verde, no solo al cierre— y **`git pull --rebase`
   antes de cada push**. Cada hora en local es una hora en la que el otro decide sin verte.
6. **El número de `DECISIONES.md` sale de la BANDA DE TU EQUIPO, no del contador compartido**
   (`[DECIDIDO owner, 2026-09-02]`, `DECISIONES #404`). **Este ordenador (`~/proyectos/jumpweb`,
   carril de producto/reservas) numera desde `#400`**; el carril de Google auth del portátil sigue
   en la secuencia natural (`#34x`). Los huecos entre bandas son deliberados y **`docs-check` no
   valida continuidad** (su check 6 solo exige que un número citado EXISTA como entrada).
   ⚠️⚠️ **Esto SUSTITUYE a la regla de «mirar `origin/main` justo antes de empujar», que se probó y
   NO basta**: entre las siete colisiones que abren este §10 y las **seis** del 1–2 de septiembre,
   todas se produjeron *al cerrar*, con el otro carril empujando mientras corrían la suite y el
   gate. *Una ventana de minutos sigue siendo una ventana; el reparto por bandas no tiene ventana.*
   ▶ **Y lo que hace caro equivocarse no es renumerar: es renumerar MAL.** Una sustitución mecánica
   sobre un número compartido reapunta las citas del otro carril —ya pasó con 46—, así que si hay
   que renumerar: **mapa explícito**, **ámbito por fichero para los propios y por LÍNEA para los
   compartidos**, **huella de las citas ajenas antes y después** y **`uniq -d` sobre las cabeceras**
   al terminar (un renumerado a mano más otro automático mueven la misma cabecera dos veces).
   ▶ El «último usado» de `ESTADO.md` se actualiza en el mismo commit, por banda.
7. **Defectos y fichas de `DEUDA.md`**: antes de abrir uno, mira si está en la fila del otro. Si
   cae en la frontera de los dos carriles, el reparto dice quién lo lleva; no se arregla dos veces.
8. **Al cerrar un carril** se retira su fila: el bloque de reparto es una FOTO, no un histórico
   (el histórico es `DECISIONES`).
