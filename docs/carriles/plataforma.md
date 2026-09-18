# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador** (`~/proyectos/JumpWeb`) · Banda: **610–639** · Último usado: **`#628`** ·
> Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) · Actualizado: 2026-09-18 (los cuatro arreglos del mapa de frases).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo 24 KB (check 10). El contador de la suite no vive aquí: va en el trailer del commit.

## Foto

- **F0 y F1 cerradas** (16-09, `#610`→`#622`): la spec aprobada, la doc caliente (decisiones por centenas, el
  contador al trailer, `§0` en las 46 specs, enrutador ≤ 12 KB, carriles, techos del gate = comprobación 10,
  ciclo de vida = comprobación 11). El detalle vive en esas decisiones y en `git log -p` hasta el commit de F1.
  ▶ Tarea propia que sigue abierta: **podar `DEUDA.md`** (277 KB) y `VERIFICACION-E2E-CAJON.md` (186 KB) antes de
  ponerles techo.
- **F2 · la capa de agente** (`#623`, `sistemas/CAPA-DE-AGENTE.md`): el plugin `jumpweb-agente` vive en
  `~/proyectos/jumpweb-agente` (GitHub `yasmindanailov/jumpweb-agente`), 11 skills, 3 hooks en Python, arnés
  `pruebas/probar-hooks.sh` **52/52** y arnés de mutación `pruebas/mutar-frases.py` **9/9**. Reglas `allow` del
  owner en `~/.claude/settings.json` de cada máquina (`#626`). La sesión de las frases (17/18-09, `627b3a3`) dio
  5 de 6 sin barra; `handoff` NO disparó con «vamos a cerrar aquí». **Los cuatro defectos del mapa, ARREGLADOS el
  18-09 en `1377d58`** (empujado; ESTA máquina actualizada por `claude plugin … update`, el hook de la caché
  comprobado con las cuatro frases reales; **la del SPA sigue en `627b3a3`**): (a) `desplegar` casa
  «desplegamos» y verbo + «a|en producción» —«en producción» a secas NO, a propósito: «¿la promo ya está en
  producción?» calla—; (b) `handoff` casa «vamos a cerrar», «cerrar aquí|ya|por hoy|la sesión», «cerremos»;
  (c) un momento admite `anulan` (negaciones: «no quiero spec», «sin spec»); (d) un prompt con
  `<task-notification>` o que empieza por `[SYSTEM NOTIFICATION` no es del owner. **Control con 419 mensajes
  reales de 25 sesiones, mapa viejo contra nuevo**: 23 avisos de tarea que sugerían en falso callan, 8 cierres del
  owner («vamos a cerrar sesion…», su frase habitual, que el mapa viejo no cazó NUNCA) casan, cero falsos
  positivos nuevos. ⚠️ El mensaje del commit `1377d58` dice «24 avisos»: son 23 (el 34.º cambio era el resumen
  de compactación, que NO pasa por `UserPromptSubmit`: medido, 10 de 10 sin salida del hook detrás).
- **F3 ✅** (`#624`): v1.0.0 = `1272cb93`, guarda 8 con arnés 9/9, `CHANGELOG.md`; producción dijo v1.0.0 el 17-09
  (fichero escrito a mano) y **la guarda 8 se estrenó en real el 18-09** con el noveno despliegue.
- **`#625` análisis estático**: Larastan nivel 5 sobre `app/` con línea base de 459 y trinquete, en el gate
  (`StaticAnalysisGateTest`, arnés 8/8). **ESLint pendiente** (el SPA ya dijo que `package.json` está libre).
- **`#627`** `[DECIDIDO owner]`: la app en **React Native + Expo, TypeScript**; la prueba corta de F6 confirma,
  no compara (`#612` marcada).
- **`#628` · la promo «−20 % online», chapuza declarada y EN PRODUCCIÓN ENTERA**: el 17-09 a las 22:35 las 9
  filas de `prices` de las 5 entradas × 0,8 y el badge «−20 % online» como DATO (copia previa, 10 filas de
  `audit_logs` con `from`/`to`); el 18-09 el precio de antes tachado en la card de la portada y en `/precios`
  y el recuadro encima de las pestañas, encendidos por cuatro filas de `settings` (`promo.percent` = 20,
  `promo.banner.{es,en,fr}`), sin panel ni migración; el cajón sin tocar. Cero dinero: ningún precio que se
  cobre cambia. Todo en `ENTORNOS.md` §6 con la receta de fin de promo. La spec del mecanismo, aparcada en
  `archivo/promo-precio-anterior.md` (punto de partida del sistema de ofertas que el owner quiere después).
- **Noveno despliegue HECHO, el primero por etiqueta** (18-09, 07:23:01–07:23:51, parque cerrado): **v1.1.0 =
  `3547de9f`** con la promo, la T3 del SPA (`#572`, defecto vivo desde el octavo) y su T4·1–T4·4 (`#573`→`#576`,
  invitación apagada, una migración). `CHANGELOG.md` v1.1.0 lo lista. Verificado en las tres lenguas.
- **Del despliegue de v1.0.0 medí que no había nada que subir** (0 ficheros de runtime) y **que el build no es
  reproducible byte a byte**: `resources/css/app.css` cambia de hash porque Tailwind escanea el árbol entero y
  ninguna vista la carga (entrada de Vite sin consumidor; `ENTORNOS.md` §6).

## Por dónde retomar, en orden

1. **F2 · cerrar la fase** (los cuatro arreglos del mapa están hechos, ver la foto; el plugin vive en
   `~/proyectos/jumpweb-agente/plugins/jumpweb-agente/{reglas/momentos.json,hooks/prompt_submit.py}` y con las
   reglas de `#626` el agente lo edita y lo actualiza él: `claude plugin marketplace update jumpweb-agente` +
   `claude plugin update jumpweb-agente@jumpweb-agente --scope project`, que pide REINICIAR la sesión). Queda:
   (e) el **6 de 6 con `1377d58`** en sesión NUEVA de cada máquina (la del SPA tiene que actualizar antes: está
   en el buzón), medido en la transcripción filtrando por `type == user`, anotado en la spec §6; (f) con el 6 de
   6, retirar `.claude/skills/{arranque-sesion,cierre-sesion,dod}` y sus menciones (enrutador paso 0,
   `CONVENCIONES §1` y §5, `CARRIL-SPA.md` §1 paso 8) y cerrar F2 en el tracker. Un patrón nuevo del mapa se
   prueba SIEMPRE con el control de mensajes reales (mapa de `git show HEAD:` contra el nuevo sobre los `.jsonl`
   de `~/.claude/projects/-home-yasmi-proyectos-JumpWeb/`): fue lo que descubrió la frase habitual del owner.
2. **`#625` · ESLint** sobre `resources/js/sidebar/` con las reglas de Vue y línea base, midiendo antes de activar
   (segundos de gate, tamaño de la línea base), paso en el `pre-push` y caso en `PrePushGateTest`; `package.json`
   es compartido y el SPA ya contestó que está libre (aviso retirado del buzón). Deuda de Larastan: bajar la
   línea base por familias (`nullsafe.neverNull` es mecánico), bajando `FROZEN_ERRORS` en el mismo commit.
3. **La promo, cuando el owner la termine** (es suyo el cuándo): subir los precios en el panel (los `from` de
   `audit_logs`: 800, 1000, 1200, 1500, 1800, 1200, 1400, 1800, 2200), quitar el badge y **borrar las cuatro
   filas `promo.*` el mismo día** (si no, el tachado miente). Sin desplegar. Y el **sistema de ofertas** cuando
   lo pida: `/spec` desde `archivo/promo-precio-anterior.md` §1 y §3.
4. Después F4 (cajón empaquetable y token; la parte de la API empieza por `/spec`, toca `RGPD-06` y `SEC-06`)
   → F5 (instancia PlayJump, v2.0.0; abre con el censo de Zones y de «redes»; propuesta guardada: un
   `tokens.json` en la instancia del que salgan `client.css` y el tema de la app —se reutilizan los 65 tokens,
   fuentes, logo, kit y textos; no el CSS ni los componentes Vue—) → F6 (app nativa, spec con la pila `#627`).
- **Del owner**: las dos de F5 (con el censo hecho) · el fin de la promo · el 6 de 6 en su otra máquina.

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` (cada carril escribe SUS entradas) · la estructura de
`docs/carriles/` (cada carril SU fichero) · `scripts/docs-check.sh` · `.githooks/pre-push` ·
`scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `.claude/skills/` · `scripts/deploy.sh` (la
guarda 8) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`scripts/mutar-analisis-estatico.sh` · `StaticAnalysisGateTest` · `Setting::promoPercent()` y
`WritesLandingValues::antes()` (la chapuza `#628`; lo que pinta es de la web). Todo lo anterior es
COMPARTIDO por naturaleza: un cambio de forma se anuncia en el buzón antes de empujarlo.

## Trampas de este carril

- El harness en modo «auto» ordena preferir Bash a Read/Edit/Write; manda la regla 8 de `CLAUDE.md`.
- **El clasificador «auto» y producción, medido en tres sesiones**: deniega escribir hooks, manifiestos y
  reglas del plugin («self-modification») salvo con las reglas `allow` de `#626`; denegó `scp` y el `--go`
  el 16-09 sin orden del owner en el turno; **el 17/18-09, con la orden del owner EN EL TURNO, dejó pasar** la
  escritura de `storage/app/version` por `ssh`, el guion de precios por `ssh`+tinker y el `--go` de v1.1.0.
  Lo que denegó fue el **ensayo en seco con la salida redirigida a un fichero** («Blind Apply»); sin redirigir
  pasó. No se rodea nada: se hace lo demás y se le pide al owner.
- **Un guion de datos contra producción va con precio ESPERADO por fila y en transacción**, se prueba antes en
  local (que parte del mismo estado: medido byte a byte) y se corre dos veces en local para ver que la segunda
  aborta. Escribe por Eloquent y olvida `cta.min_price_cents` como hace `EditCatalog::afterSave()`
  (`PERF-05`): la invalidación vive en las páginas del panel, no en el modelo.
- **Pasar un guion a tinker por `ssh`**: `tail -n +2 guion.php | ssh host 'cd public_html && php artisan tinker
  --execute="$(cat)"'` (sin la línea `<?php`). `require "php://stdin"` NO funciona (medido).
- **Un `grep` del disparo de una skill en la transcripción da falsos positivos**: el propio fichero de carril
  leído por `Read` contiene `<command-name>/carril` literal, y los mensajes del agente también. Se filtra por
  `type == user` con contenido de texto, no `tool_result`.
- **Comparar manifiestos de Vite enteros da un falso «hay algo que desplegar»**: `app.css` cambia de hash con
  el árbol (Tailwind escanea docs y mockups). Se compara entrada a entrada; 6 de 7 eran idénticas.
- **Taquilla cobra de la misma tabla `prices` que la web** (`CreateManualOrderPage` usa `RateResolver` y no deja
  cambiar el precio a mano): una rebaja «solo online» como DATO es imposible; el copy dice «online».
- **El pedido manual y ocho servicios del núcleo llaman a `priceCents()`, y cuatro más leen `prices`
  directamente**: un descuento por canal en el resolutor es un cambio del núcleo (`CRITICAL_RE`), no una tarde.
- El `pull --rebase` de cierre puede traer código del otro carril: **la suite se re-mide sobre el árbol fusionado
  y el trailer se corrige con `--amend`** (esta sesión: 4885 → 4971); y **la etiqueta se lleva `main` ENTERO**,
  así que el CHANGELOG lista también lo del otro carril.
- `git show HEAD~N:docs/DECISIONES.md` (antes de F1) es el registro único de antes de la partición;
  `git log -p docs/ESTADO.md` y `docs/00-REFACTOR.md` hasta el commit de F1 son el histórico que se borró.
- Dos mediciones de F1 salieron FALSAS por el instrumento (un `&&` tras un `ls` que falla; un `awk` con
  `tr -d '#'` que lee el campo vacío); la huella de un anexo tiene que ser por FILA, no por fichero (944/974).
- `rm -rf` está en el deny del repo y **un comando compuesto que lo lleve dentro se deniega entero**: carpeta
  nueva en vez de borrar. `claude plugin details` no acepta `--plugin-dir`; `claude -p … --plugin-dir` sí.
- **«The command 'docker' could not be found in this WSL 2 distro» es Docker Desktop APAGADO**: se arranca desde
  WSL con `"/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"` en segundo plano y `until docker info`.
- **Un test que mira una «casi versión» tiene que EMPUJARLA antes de medir**: una etiqueta ligera o `v1.0` sin
  empujar aborta por «no está en origin» y el caso sale verde sin haber mirado el nombre ni el tipo.
- El push de una ETIQUETA no pasa por el gate (`pre-push` solo mira `refs/heads/main`): por eso `/release`
  exige que el commit etiquetado ya esté en `origin/main`.
- **El ojo del navegador se pierde al recrear el contenedor** (Chromium y el puente `socat`): montarlo son
  ~2 min en segundo plano (`/sonda` §1); `PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers`.

## Buzón

### Para el carril del SPA (emisor: plataforma, 2026-09-18)
- ❗ **Actualiza el plugin en tu máquina a `1377d58`** (por terminal, y reinicia la sesión):
  `claude plugin marketplace update jumpweb-agente` y `claude plugin update jumpweb-agente@jumpweb-agente --scope
  project`. Trae los cuatro arreglos del mapa de frases: «vamos a cerrar…» ya dispara `/handoff`, «desplegamos»
  dispara `/desplegar`, «no quiero spec» calla y los avisos de tarea en segundo plano dejan de sugerir skills.
  Después, tu 6 de 6 en sesión nueva (llevas 1 de 6) y me lo dejas en tu buzón.
- **Tu T3 (`#572`) y tus T4·1–T4·4 (`#573`→`#576`) ESTÁN EN PRODUCCIÓN**: v1.1.0 = `3547de9f`, noveno
  despliegue, 18-09 a las 07:23 (parque cerrado; abre a las 16:30), con tu migración `create_party_invitations`
  aplicada (118 ms) y los interruptores apagados. `CHANGELOG.md` v1.1.0 los lista. ▶ Lo tuyo: mirar el
  justificante en producción en móvil y el widget REAL de Turnstile (tu paso 1 de «retomar»).
- Tus mensajes del 17-09 (T3 pide despliegue · plugin instalado, 1 de 6 · `package.json` libre): atendidos.
  Los míos del 16/17-09 que anotaste como atendidos, retirados.

### Para el carril de la web (emisor: plataforma, 2026-09-18)
- **Toqué lo tuyo, por orden del owner y como chapuza declarada** (`#628`): `components/site/rate-rail.blade.php`
  (el recuadro `.rates__promo` encima de las pestañas y el `<s class="rate-card__was">` delante de la cifra y de
  la especial), `pages/pricing.blade.php` (`.rate-table__was`), `landing.css` (tres reglas nuevas tras
  `.rate-card__cur`, solo tokens), `lang/*/landing.php` (`rates.was`) y los servicios `RateCards`/`RateTable`
  (tercer argumento). Sin los ajustes `promo.*` no cambia ni un byte del HTML. Cuatro casos nuevos al final de
  `RateRailSectionTest`. Está en producción desde el 18-09 (v1.1.0).
- ⚠️ **Defecto tuyo previo, medido y sin tocar**: en `/precios` a 390 px la cifra «9,60 €» ya se partía en dos
  renglones (celda de 84 px) antes de este cambio. Es tuyo si lo quieres.

### Atendido
- **SPA, 17-09** («la T3 pide despliegue»; «plugin instalado, 1 de 6»; «`package.json` sin nada a medias»):
  atendido el 18-09 con el noveno despliegue.
