# CLAUDE.md — JumpWeb

> Índice/enrutador para el agente. Mantener CORTO; el detalle vive en `docs/`.
> **Este proyecto lo desarrollan al 100% agentes IA** (`DECISIONES #7`): tu handoff es lo
> único que tendrá el siguiente agente. Protocolo en `docs/CONVENCIONES.md` (§7 y §5).

## ▶ Para continuar el proyecto (handoff)
0. Skill **`/arranque-sesion`** → base verde VERIFICADA (árbol, push, hook, stack, gates).
   Obligatorio tras un cierre abrupto (crash/apagón).
1. Lee **`docs/ESTADO.md`** → foto viva (dónde estamos, qué sigue).
2. Lee **`docs/00-REFACTOR.md`** → tracker VIVO del refactor (fases + checklists).
3. Para tu tarea concreta: **solo su fila** de la tabla de enrutado de abajo.
4. Decisiones y su porqué: `docs/DECISIONES.md` (buscar por número, no cargar entero).

## Qué es
**JumpWeb**: plataforma white-label de **reservas online** multi-sector — landing pública
(ES/EN/FR) + sistema de reservas con pago (señal o total) + panel admin (Filament) + API v1
para web y app móvil. Nace de una base en producción endurecida (`DECISIONES #1`);
**este repo es el PRODUCTO**, sin marca de ningún cliente.

## Stack
Laravel 13 + MySQL · Blade SSR (landing) · Livewire v4 (sidebar, en migración a SPA+API)
· Filament (panel) · Vite · Redsys (primer driver de pago).
- **Local:** Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. **Puertos propios**:
  web `localhost:8081` · MySQL `3308` · Mailpit `8028` (van en `.env`, no versionado).

## Comandos clave (Docker)
- Arrancar / parar: `docker compose up -d` · `docker compose down`
- Artisan: `docker compose exec -u sail laravel.test php artisan <cmd>`
- Tests: `docker compose exec -u sail laravel.test php artisan test --parallel`
- Estilo: `docker compose exec -u sail laravel.test ./vendor/bin/pint`
- Assets: `docker compose exec -u sail laravel.test npm run build`
  > ⚠️ **Siempre `-u sail`** (como root deja ficheros de root en `storage/` → 500 por permisos).
- **CI = gate local**: el hook `pre-push` (`.githooks/`) corre **docs-check + Pint + suite**
  en pushes de `main` y bloquea el push en rojo (`wip/…` exento). Tocar el núcleo de dinero/aforo
  exige además `VERIFY_CONC=1` tras correr los dos verificadores (`INVARIANTES §6`); **la lista
  viva es el `CRITICAL_RE` del propio hook** —no se copia aquí para que no envejezca— y
  `CriticalPathGateTest` vigila que siga cubriendo lo que debe.
  Si no salta, actívalo: `git config core.hooksPath .githooks` (`DECISIONES #9`/`#10`).

## Principios (NO romper)
- **Data-driven:** todo configurable desde el panel; nada de negocio quemado en código.
- **White-label:** lógica genérica; marca = tokens CSS + tema/contenido en BD. 1 instalación/cliente.
- **API-first:** el dominio se expone por `/api/v1`; web y móvil son clientes iguales.
- **Corrección antes que presentación:** suite verde = red de TODO el refactor.
- **Invariantes heredadas** (`docs/INVARIANTES.md`): el endurecimiento del origen no se regresa.

## ⚡ Enrutado de contexto — LEE SOLO LO QUE NECESITES
| Si trabajas en… | Lee solo |
|---|---|
| Refactor (fases, alcance, arquitectura objetivo) | `docs/00-REFACTOR.md` · `docs/specs/modulos-dominio.md` (Fase 2) · `docs/DECISIONES.md` (busca por tema) |
| Dinero / pagos / Redsys / reembolsos | **`docs/INVARIANTES.md` §1 (PAY) + §6 (SUITE)** · `docs/sistemas/REDSYS.md` · `docs/MODELO-DATOS.md` §2 |
| Señal / depósito (pago parcial) | `docs/sistemas/DEPOSITO.md` · `docs/INVARIANTES.md` §1 (PAY-10) |
| Secuencia de compra (admitir → crear → cobrar) | `docs/specs/checkout-orquestado.md` · `docs/INVARIANTES.md` §1 (PAY-04) + §2 (AFORO-10) |
| Aforo / franjas / disponibilidad / calendario | **`docs/INVARIANTES.md` §2 (AFORO)** · `docs/MODELO-DATOS.md` §1 · `docs/FLUJOS.md` (flujos 3–4) |
| Compra / carrito / catálogo de productos | `docs/sistemas/COMPRA-PRODUCTOS.md` · `docs/FLUJOS.md` · `docs/MODELO-DATOS.md` §1 |
| Auth / cuentas / RGPD | `docs/SEGURIDAD.md` · `docs/INVARIANTES.md` §3 (RGPD) + §4 (SEC) · `docs/FLUJOS.md` (flujos 1–2) |
| Auth dentro del cajón / retirar el modal de la cabecera | `docs/specs/auth-en-cajon.md` (**§1.3: tres cosas que no son «pintar»** — mueren dos paridades, SEC-06 cita tests que se van y el `noindex` de `/login`, `/registro` y `/recuperar-contrasena` no lo asevera nadie · §4.5: el clic **con el motor ya montado** es donde esto se rompe en silencio) |
| Landing / tema visual (tokens CSS) | `docs/ARQUITECTURA.md` (white-label) · `docs/MAPA-PAGINAS.md` |
| Desglose de dinero que ve el CLIENTE (ledger, «Mis pedidos») | `docs/specs/desglose-dinero-cliente.md` ✅ **CERRADA** (`#134`, §23: **`L6` ejecutado** — «Importe al reservar» dice hacia dónde y cuánto, y **su frase `null` ES la condición de enseñar la línea**: no la re-derives comparando importes) — **TANDAS A, B y C EJECUTADAS + los CUATRO defectos de lectura** (§15 el dominio dice la verdad · §16 el desglose ya es LEGIBLE · **§18: y ya es VERIFICABLE** · **§19: «Mis pedidos» es pantalla propia — y el suelo que la sostenía PERDÍA pedidos**) · marco en `DECISIONES #127`, lectura en `#128`, pantalla en `#129`, vueltas con el owner delante en **`#130`** (§20) y **`#131`** (§21: la fecha se pegaba a un importe que no se cobró ese día —7 pedidos, 6 SANOS— y la línea del cargo por cambios no decía por qué). · **`#132`** (§22: **auditoría de las 25 ACCIONES** —corpus borrado y reconstruido por los flujos reales, 25/25 cierran— y el desglose que NO cuadra deja de servirse como si nada: al cliente se le oculta, al operador se le enseña, y el parque se entera por log). ❗ **§22.2 deja TRES cosas de PRODUCTO planteadas.** ⚠️ **§22.3: cuatro trampas para conducir el panel** —el cambio de fecha lo mueve el CALENDARIO, no el formulario—. **Empieza por §9, §10 y §18, no por el principio**: §9.2 son **CUATRO defectos de DOMINIO** que las dos auditorías anteriores no llegaron a construir —y **el peor, el pedido cancelado sin reembolsar, no lo caza ninguna identidad**— · §9.3, la proyección son **cuatro** defectos y no uno · **§10: los DOS EJES CERRADOS, verificados en 58 de 58 pedidos reales** · **§17: el pedido `R-L6UTIA`, que parece un fallo del desglose y es un DATO ROTO** (comprueba si el dato es real ANTES de buscar el fallo en el código) · §18.5 y §19.1, lo que enseñó la ejecución —**incluido que `GET /me/orders` perdía dos pedidos de 57, y que la guarda de conducta salía VERDE en SQLite**— · §9.4, las trampas de método · §4.quater, la receta y las CUATRO puertas de `OrderCreator` |
| «Mis reservas» por RESERVA · historial aparte · orden y paginación | `docs/specs/mis-reservas-por-reserva.md` ✅ (**§3.4: los dos ámbitos son UN predicado con dos lados** — si se separan, una reserva puede no salir en ninguna de las dos pantallas y eso no falla ni avisa · §4.2: el ledger **ya no se despliega aquí** — desde `#129` vive en «Mis pedidos» y «Ver pedido» LLEVA allí; desde `#130` esta pantalla **no enseña NINGÚN importe**) · `DECISIONES #126` |
| Área de cliente en el cajón (mis pedidos / mis reservas) | `docs/specs/area-cliente.md` (**§1.2: la API cubre LEER, no gestionar** · §1.3: aquí la red NO es el diff de árbol) · `docs/DECISIONES.md` #66, #119, #120 |
| Bloque de cuenta del cajón (`.acct`) · el suelo servido · `no-store` de la web | `docs/specs/account-context-vue.md` ✅ (**§4.7: el `no-store` de TODA página web lo ponía un accidente de Livewire** — hoy lo pone `NoStoreWebResponses`, global y con puerta para `/api/v1` · **§4.8: `route('logout')` aparece UNA vez en toda la app** y vive como suelo dentro del hueco · §4.6: lo que ninguna guarda estática puede cubrir) · `DECISIONES #123` |
| Sidebar SPA (Fase 4) / Vue / tema por instalación | `docs/specs/sidebar-spa.md` (**§4.2: el contrato visual es el ÁRBOL, NO las clases** — medido: 90 de 292 selectores no se satisfacen emitiendo la clase correcta) · ⚠️ **el diff de árbol NO ve el interior de un `<svg>` ni el texto**: eso necesita paridad propia (`TESTING.md` §2.ter/§2.quater, `DECISIONES #113`) · `docs/specs/api-v1.md` §10 → §10.sexdecies |
| CMS público (servicios, ofertas) | `docs/sistemas/SERVICIOS-CMS.md` · `docs/sistemas/OFERTAS-WIDGET.md` |
| Diseño previo a implementación (spec) | `docs/specs/PLANTILLA.md` (copiar) · `docs/CONVENCIONES.md` §5 |
| Cookies / consentimiento | `docs/sistemas/COOKIES.md` · `docs/SEGURIDAD.md` |
| Panel admin / puerta / operación diaria | `docs/PANEL-ADMIN.md` · `docs/OPERATIVA-SECTOR-ORIGEN.md` |
| Post-form de invitados | `docs/sistemas/POSTFORM-INVITADOS.md` |
| Tests / suite / fakes / datos de prueba | `docs/TESTING.md` (fixture: §datos de prueba) · `docs/CONVENCIONES.md` (§3.bis/§3.ter) |
| Retirar código viejo / auditar sus tests | `docs/CONVENCIONES.md` **§3.quater** (clasificar por sujeto + las cuatro trampas de la mutación) |
| Staging / desplegar / aprovisionar | `docs/ENTORNOS.md` §4 (**medido**: API del panel, orden del docroot, `robots.txt`) |
| Vocabulario de dominio (término ↔ código) | `docs/GLOSARIO.md` |
| Deuda técnica (vista única) | `docs/DEUDA.md` |
| Instalar un cliente nuevo (white-label) | `docs/INSTALACION-CLIENTE.md` |
| Servidor de pruebas / despliegue / Turnstile / S2S | `docs/ENTORNOS.md` (**staging = 0 LIVE, 0 PRODUCCIÓN**) · `docs/VERIFICACION-E2E-CAJON.md` |
| Validar el cajón SPA en vivo (navegador + pasarela) | `docs/VERIFICACION-E2E-CAJON.md` · `docs/sistemas/REDSYS.md` §11–§12 |
| API v1 / SPA sidebar / app móvil | `docs/specs/api-v1.md` (Fase 3, 🟦 — **§10 → §10.sexdecies «lo que el código enseñó», antes de tocar nada**) · `openapi/v1.yaml` (el CONTRATO: manda sobre el código) · `docs/DECISIONES.md` #21, #24, #26–#37, #39–#46 |
| UI de carga (spinner) | `docs/sistemas/UI-SPINNER.md` |
| Requisitos / alcance funcional | `docs/REQUISITOS.md` · `docs/MAPA-PAGINAS.md` |

Índice completo y estado de cada doc: **`docs/README.md`**.
⚠️ La doc «base heredada» describe el código al 2026-08-12: **verificar contra el código**
antes de construir encima (`CONVENCIONES §7`).

## Reglas para el agente
1. Antes de tocar una feature, lee **solo su fila** de la tabla. Amplía solo si falta contexto.
2. Si tocas dinero, aforo, RGPD o seguridad: **lee `docs/INVARIANTES.md` primero**.
3. Decisión nueva → `[DECIDIDO]`+fecha en el doc afectado **y** línea en `docs/DECISIONES.md`.
4. Doc nuevo/renombrado → actualizar `docs/README.md` **y** esta tabla.
5. Al empezar: skill **`/arranque-sesion`** (base verde). Al terminar: **`/cierre-sesion`**
   (suite+Pint+docs-check, tracker, ESTADO fiel, commit+push con evidencia).
6. **«Hecho» (✅) = código + prueba + verificación empírica + doc del sistema al día**
   (skill **`/dod`**, 4 condiciones). Si falta algo, es 🟦.
7. **No tocar el repo del cliente origen** (`~/proyectos/jumpingjump`) desde sesiones de JumpWeb.
