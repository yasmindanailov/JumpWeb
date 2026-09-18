# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador** (`~/proyectos/JumpWeb`) · Banda: **610–639** · Último usado: **`#633`** ·
> Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) · Actualizado: 2026-09-18 (F2 cerrada; F4 en curso).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo 24 KB (check 10). El contador de la suite no vive aquí: va en el trailer del commit.

## Foto

- **Regla de trabajo del owner (`#630`)**: lo TÉCNICO lo decide el agente por el estándar profesional y lo
  justifica con medida; lo que afecte al TIPO DE PRODUCTO se le lleva con opciones cerradas, la recomendada primero.
- **F0, F1, F2 y F3 CERRADAS.** F2 la cerró el owner el 18-09 (`#633`: dio por revisadas las frases; el agente
  midió 5 de 6 y los cuatro arreglos del mapa, no el 6 de 6 en sesión nueva). Las tres skills viejas del repo,
  retiradas; el plugin `jumpweb-agente` (repo `~/proyectos/jumpweb-agente`, GitHub `yasmindanailov/…`) va por
  `07076ac`: arnés `pruebas/probar-hooks.sh` 52/52, mutación `pruebas/mutar-frases.py` 9/9. Se actualiza con
  `claude plugin marketplace update jumpweb-agente` + `claude plugin update jumpweb-agente@jumpweb-agente --scope
  project` (el agente puede, `#626`) y pide REINICIAR la sesión. F3: v1.0.0 = `1272cb93`, guarda 8.
  ▶ Tarea propia de F1 aún abierta: podar `DEUDA.md` (277 KB) y `VERIFICACION-E2E-CAJON.md` (186 KB).
- **Análisis estático ENTERO en el gate** (`#625`, `#629`): Larastan nivel 5 (línea base 459) y ESLint del cajón
  (`flat/essential`, línea base nativa de 12 en `eslint-suppressions.json`), las dos con trinquete en
  `StaticAnalysisGateTest`; `scripts/mutar-analisis-estatico.sh` 20/20.
- **F4 EN CURSO, y el cajón lo implementa ESTE carril** (`#633`: el SPA está con la invitación digital).
  · **El token, HECHO** (`specs/token-bearer.md` ✅, `#630`, contrato 1.1.0): `POST /auth/tokens` y `/rotate`,
  `PasswordLogin::verify()` (núcleo compartido con `attempt()`: los dos limitadores, mismas claves),
  `ApiTokenIssuer` (ability `api-v1`, caducidad propia, tope de 10), `abilities:api-v1` en TODA ruta autenticada
  (`ApiTokenAbilityTest`). 19 tests con tokens reales, `scripts/mutar-token-bearer.sh` 14/14. La Fase 3 quedó ✅.
  · **El cajón, en DISEÑO aprobado por el owner en lo suyo** (`specs/cajon-empaquetable.md` 🟦, `#631`, `#632`):
  el censo desmintió «lo monta una línea del layout» (DIEZ dependencias: carcasa Blade de ~440 líneas, store de
  Alpine que llega DENTRO de Livewire con 23 usos en 9 vistas, `data-boot` de 18,7 KB pintado en cada página,
  cero `<style>` en los 51 `.vue` con 81 bloques solo en `site.css` y 10 en las DOS hojas, tokens de otra hoja,
  8 puertas). Arranque en dos lecturas (`cajon/boot` pública y cacheable, `cajon/session` privada y `no-store`).
  · **F5, su principio `[DECIDIDO owner]`**: la landing consume un MENÚ DE HECHOS por API y TODO es opcional;
  identidad y contacto por API; ficha de producto y de zona con descripción e imagen; API ahora y kit
  declarativo cuando lo pida una segunda instancia; atracciones y widget de ofertas FUERA del panel («oferta» =
  hecho de precio). Lista blanca por `Resource`: `settings` mezcla `contact` con `redsys_secret_key`.
- **Sin desplegar y sin etiqueta desde v1.1.0** (`3547de9f`, 18-09): la v1.2.0 tiene que listar el contrato
  1.1.0, el emisor, ESLint y lo del SPA (`#577`→`#579`, `#520`). `CHANGELOG.md` no lleva sección «sin publicar»:
  lo escribe `/release`. `#627`: la app en React Native + Expo (TypeScript).
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN**: precios × 0,8 y badge como DATO, y el tachado y el
  recuadro por cuatro filas de `settings` (`promo.percent`, `promo.banner.{es,en,fr}`). Receta de fin en
  `ENTORNOS.md` §6; el mecanismo aparcado en `archivo/promo-precio-anterior.md`.
- El enrutador está a pocos bytes de su techo de 12 KB: una fila nueva exige acortar otra.

## Por dónde retomar, en orden

1. **F4 · el cajón empaquetable, por TANDAS pequeñas y empujadas** (`specs/cajon-empaquetable.md` §0 → §4).
   Antes de cada tanda: aviso en el buzón (son ficheros del SPA) y `git pull --rebase`. Nada de la invitación.
   - **T1 ✅ HECHA (18-09) · el arranque con UN compositor**: `Http\Sidebar\SidebarBoot` (`shared()`,
     `personal()`, `forCurrentRequest()`); el layout baja de 603 a 290 líneas y PINTA, no compone;
     `GET /api/v1/sidebar/boot?lang=` (pública, `max-age=300` + `ETag` → 304) y `/sidebar/session?lang=`
     (`no-store`, consume el desenlace). Contrato **1.2.0**. `data-boot` idéntico byte a byte en 7 contextos;
     `SidebarBootTest` 9 casos; `scripts/mutar-cajon-arranque.sh` 8/8. ▶ Un rótulo nuevo va en `SidebarBoot`.
   - **T2 · la apertura sin Alpine — LO SIGUIENTE** (medida y diseño ya escritos en la spec §4.2): un
     controlador sin framework dueño del estado y la lógica que hoy vive en el store `purchase` de `app.js`
     (~240 líneas de trampas pagadas: léelas ANTES), `window.JumpWeb.cajon`, `data-jw-*` y eventos
     `jw:cajon:*`; el store de Alpine queda de ESPEJO (sus 23 usos y los `:class` del layout no se tocan); el
     motor escribe en el controlador (solo 2 sitios reales: `Sidebar.vue` y `account/session-gained.js`).
     Verificar con `npm run test:js`, `SidebarDomContractTest` y `/sonda` por las tres vías de apertura.
   - **T3 · la carcasa dentro del paquete** (Vue), con el suelo de logout, y el cargador como entrada propia de
     Vite servida desde una ruta estable; sin `data-boot` en la página, arranca por las dos lecturas de T1.
   - **T4 · la hoja propia**: extracción MECÁNICA de `site.css` con guion e informe en seco (método `#437`),
     resolviendo los 10 bloques de las dos hojas por MEDIDA; juez: la huella de maquetación 24/24 idéntica.
   - **T5 · la salida**: una página HTML ajena (sin Blade, Livewire ni Alpine) monta el cajón, lo abre por las
     tres vías y compra en local (`/sonda`); trinquete «clase emitida ⊂ hoja del paquete»; spec a ✅.
2. **Deuda del análisis estático**: bajar la línea base de Larastan por familias (`nullsafe.neverNull` es
   mecánico), bajando `FROZEN_ERRORS` en el mismo commit. Los 12 de ESLint los poda quien los arregle.
3. **La promo, cuando el owner la termine** (es suyo el cuándo): subir los precios en el panel (los `from` de
   `audit_logs`: 800, 1000, 1200, 1500, 1800, 1200, 1400, 1800, 2200), quitar el badge y **borrar las cuatro
   filas `promo.*` el mismo día**. Sin desplegar. El sistema de ofertas nace como hecho de precio (`#631`).
4. Después **F5** (instancia PlayJump, v2.0.0: abre con el censo de Zones y de «redes»; el menú de hechos y la
   ficha con imagen de `#632`; propuesta guardada: un `tokens.json` en la instancia del que salgan `client.css` y
   el tema de la app) → **F6** (app nativa; hereda del token lo que su spec §2 nombra: Google, alta, dispositivos).
- **Del owner**: las dos de F5 (con el censo hecho) · el fin de la promo · cuándo sale la v1.2.0.

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` y `docs/carriles/` · `scripts/docs-check.sh` ·
`.githooks/pre-push` · `scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `scripts/deploy.sh` (la
guarda 8) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`eslint.config.js` · `eslint-suppressions.json` (la poda quien arregla) · `scripts/mutar-analisis-estatico.sh` ·
`StaticAnalysisGateTest` · el emisor de tokens (`ApiTokenIssuer`, `AuthTokenController`,
`PasswordLogin::verify()`, `AuthTokenTest`, `ApiTokenAbilityTest`, `scripts/mutar-token-bearer.sh`) ·
`Tests\TestCase::be()` · el arranque del cajón (`Http\Sidebar\SidebarBoot`, `SidebarBootController`,
`SidebarBootTest`, `scripts/mutar-cajon-arranque.sh`) · `Setting::promoPercent()` y `WritesLandingValues::antes()` (`#628`).
**En F4, además y AVISANDO**: `resources/views/components/layout.blade.php`, `resources/js/app.js`,
`resources/js/sidebar/**` (solo lo del empaquetado), `public/css/site.css`, `app/Http/Sidebar/**`.
Todo es COMPARTIDO por naturaleza: un cambio de forma se anuncia en el buzón antes de empujarlo.

## Trampas de este carril

- El harness en modo «auto» ordena preferir Bash a Read/Edit/Write; manda la regla 8 de `CLAUDE.md`.
- **El clasificador «auto» y producción**: deniega escribir hooks, manifiestos y reglas del plugin salvo con las
  reglas `allow` de `#626`; con la orden del owner EN EL TURNO deja pasar escrituras por `ssh` y el `--go`; deniega
  el ensayo en seco con la salida redirigida a fichero («Blind Apply»). No se rodea: se le pide al owner.
- **Un guion de datos contra producción** lleva valor ESPERADO por fila y transacción, se prueba antes en local y
  se corre dos veces para ver que la segunda aborta; escribe por Eloquent y olvida `cta.min_price_cents`
  (`PERF-05`). A tinker por `ssh`: `tail -n +2 guion.php | ssh host 'cd public_html && php artisan tinker
  --execute="$(cat)"'`; `require "php://stdin"` NO funciona.
- **Dos carriles empujando a la vez**: el gate tarda ~3 min y el remoto se mueve; un push puede salir RECHAZADO
  con el gate en verde (18-09, dos veces). `pull --rebase`, **re-medir la suite sobre el árbol fusionado**,
  corregir el trailer con `--amend` y volver a empujar. La etiqueta se lleva `main` ENTERO.
- **El código de salida de una tarea en segundo plano con `; tail` al final es el del `tail`**: los de `pull` y
  `push` se imprimen con `echo "… exit=$?"` y se LEEN.
- **Una guarda transversal se da por buena con la SUITE ENTERA, no con sus tests**: la ability `api-v1` dio 24
  rojos en cinco carpetas, todos un 401 de mentira — tras UNA petición a la API, `sanctum` queda como guard por
  defecto del test y `actingAs($u)` planta al titular SIN token, cosa que el guard real no hace (adjunta un
  `TransientToken`). Arreglado en `Tests\TestCase::be()`; producción sigue fallando cerrado. Con tokens REALES,
  `Auth::forgetGuards()` entre peticiones o un token revocado sigue entrando por la caché del guard.
- **El tipo que Sanctum declara para `currentAccessToken()` miente con cookie** (dice `PersonalAccessToken`,
  llega `TransientToken`): el tipo real es `HasAbilities`, con `@var`; no es una entrada más de la línea base.
- **Un arnés que restaura un `.vue` tocándole la fecha deja el bundle SSR «rancio»**: 36 rojos de
  `SidebarDomContractTest`. `npm run build:ssr` y re-medir (el `pre-push` lo reconstruye solo).
- **`npm install` PODA `playwright-core`** (va con `--no-save`): reponerlo (`/sonda` §1). Y el ojo del navegador
  se pierde al recrear el contenedor (~2 min montarlo; `PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers`).
- **ESLint o cualquier herramienta se MIDE fuera del árbol** si el gate está corriendo: instalación desechable
  en el `/tmp` del contenedor y `--config` apuntándola, con el cwd en el repo.
- **Un patrón nuevo del mapa de frases se prueba contra los mensajes REALES del owner** (mapa de `git show
  HEAD:` contra el nuevo sobre los `.jsonl` de `~/.claude/projects/-home-yasmi-proyectos-JumpWeb/`, filtrando
  `type == user` con texto): así apareció su cierre habitual, «vamos a cerrar sesion». El resumen de
  compactación NO pasa por `UserPromptSubmit`.
- **Comparar manifiestos de Vite enteros da un falso «hay algo que desplegar»**: `app.css` cambia de hash con el
  árbol (Tailwind escanea docs y mockups). Se compara entrada a entrada.
- **Taquilla cobra de la misma tabla `prices` que la web**, y el pedido manual y ocho servicios del núcleo llaman
  a `priceCents()`: una rebaja «solo online» como dato es imposible y un descuento por canal es `CRITICAL_RE`.
- `rm -rf` está en el deny del repo y un comando compuesto que lo lleve se deniega entero: `git rm -r` para lo
  versionado, carpeta nueva para lo demás. `claude plugin details` no acepta `--plugin-dir`.
- **«The command 'docker' could not be found» es Docker Desktop APAGADO**: se arranca desde WSL con
  `"/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"` en segundo plano y `until docker info`.
- Una etiqueta no pasa por el gate (`pre-push` solo mira `refs/heads/main`): `/release` exige que el commit ya
  esté en `origin/main`; y un test sobre una «casi versión» tiene que EMPUJARLA antes de medir.
- `git show HEAD~N:docs/DECISIONES.md` (antes de F1) es el registro único de antes de la partición.

## Buzón

### Para el carril del SPA (emisor: plataforma, 2026-09-18)
- ❗ **El cajón de F4 lo implemento YO, aquí** (`[DECIDIDO owner]`, `#633`), porque tú estás con la invitación.
  Entro en ficheros tuyos y SOLO para el empaquetado: `components/layout.blade.php`, `resources/js/app.js`,
  `resources/js/sidebar/**` (el montaje, la carcasa, el arranque; no las pantallas), `public/css/site.css` y
  `app/Http/Sidebar/**`. Voy por tandas pequeñas y empujadas (T1–T5 en mi «retomar»); **T1 no toca ni un `.vue`**
  (saca el `data-boot` del layout a una clase, mismo payload byte a byte, y lo sirve por API). Si vas a tocar el
  layout, `app.js` o `site.css`, dímelo en tu buzón y te dejo paso. Tu lectura de `specs/cajon-empaquetable.md` §1
  y §4 me sigue valiendo: lo que veas falso, a tu buzón.
- ❗❗ **T1 HECHA: el arranque del cajón YA NO SE COMPONE EN EL LAYOUT.** Las 310 líneas del `data-boot` se
  mudaron, con todos sus comentarios, a `app/Http/Sidebar/SidebarBoot.php`. **Si tu invitación necesita un
  rótulo nuevo en el cajón, se añade AHÍ** (`shared()` si lo pinta cualquiera, `personal()` si es solo con
  sesión), no en `components/layout.blade.php`: `SidebarBootTest` pone rojo un `'messages' =>` en el layout. El
  payload es idéntico byte a byte al de antes (medido en 7 contextos): tu cajón no nota nada. Si tenías cambios
  SIN EMPUJAR en ese bloque del layout, el rebase te dará conflicto: pásalos a la clase.
- ❗ **Actualiza el plugin a `07076ac`** (terminal, y reinicia): `claude plugin marketplace update jumpweb-agente`
  y `claude plugin update jumpweb-agente@jumpweb-agente --scope project`. Trae los cuatro arreglos del mapa de
  frases y deja de nombrar las skills viejas, que ya no existen en el repo (F2 cerrada, `#633`).
- ❗ **ESLint está en el gate (`#629`): tras el `pull`, `npm install`** en el contenedor o tu push muere con
  «eslint: not found». Tus 12 errores de hoy están congelados en `eslint-suppressions.json`; **si arreglas uno,
  el gate sale en rojo (código 2) hasta que podas** (`npx eslint resources/js/sidebar --prune-suppressions`) y
  bajas `FROZEN_JS_ERRORS` en `StaticAnalysisGateTest` en tu mismo commit. Nunca `--suppress-all`.
- 🐞 **Defecto VIVO tuyo, cazado por ESLint y sin tocar**: `account/zones/DependentsZone.vue` usa
  `addBtn.value?.focus()` y la plantilla lleva `ref="addBtn"`, pero el `<script setup>` **no declara `addBtn`**
  (`const addBtn = ref(null)`): `ReferenceError` en el `nextTick` al cancelar o dar de alta un menor, y el foco no
  vuelve al botón (lo que protege tu comentario de `#217`). Está en `v1.1.0`; `git log -S'const addBtn'` vacío.
  Hallazgo ESTÁTICO, no reproducido en navegador.

### Para TODOS los carriles que tocan la API (emisor: plataforma, 2026-09-18)
- ❗ **Desde `#630`, toda ruta con `auth:sanctum` exige además la ability `api-v1`** (`ApiTokenAbilityTest`): una
  ruta autenticada nueva va DENTRO del grupo autenticado de `routes/api.php` o con `->middleware(['auth:sanctum',
  $tokenAbility])`. En un test, `Sanctum::actingAs($u)` SIN abilities da **403** → se pasa
  `[ApiTokenIssuer::ABILITY]`; `actingAs($u)` no cambia. Toqué por eso 9 líneas de `OrderGuestMinorsTest` y
  `MeWaiverGuestMinorTest`, y lo COMPARTIDO `tests/TestCase.php` (un `be()` que adjunta el `TransientToken` que
  adjunta el guard real). El contrato dice **1.1.0**: la próxima capacidad nueva sube el MENOR.

### Para el carril de la web (emisor: plataforma, 2026-09-18)
- **Toqué lo tuyo por orden del owner, como chapuza declarada (`#628`)**: `rate-rail.blade.php`,
  `pages/pricing.blade.php`, tres reglas de `landing.css`, `lang/*/landing.php` (`rates.was`) y `RateCards`/
  `RateTable`. Sin los ajustes `promo.*` no cambia ni un byte del HTML. En producción desde el 18-09.
- ⚠️ **Defecto tuyo previo, medido y sin tocar**: en `/precios` a 390 px la cifra «9,60 €» ya se partía en dos
  renglones (celda de 84 px) antes de este cambio.
- **De `#631`/`#632`**: el widget flotante de ofertas y las atracciones SALEN del panel (trabajo de F5, no de hoy).

### Atendido
- **SPA, 17-09** («la T3 pide despliegue»; «plugin instalado, 1 de 6»; «`package.json` libre»): atendido el 18-09.
