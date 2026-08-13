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
  en pushes de `main` y bloquea el push en rojo (`wip/…` exento; tocar `OrderCreator`/
  `RedsysReturnHandler`/`SlotGenerator` exige `VERIFY_CONC=1`, ver `INVARIANTES §6`).
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
| Aforo / franjas / disponibilidad / calendario | **`docs/INVARIANTES.md` §2 (AFORO)** · `docs/MODELO-DATOS.md` §1 · `docs/FLUJOS.md` (flujos 3–4) |
| Compra / carrito / catálogo de productos | `docs/sistemas/COMPRA-PRODUCTOS.md` · `docs/FLUJOS.md` · `docs/MODELO-DATOS.md` §1 |
| Auth / cuentas / RGPD | `docs/SEGURIDAD.md` · `docs/INVARIANTES.md` §3 (RGPD) + §4 (SEC) · `docs/FLUJOS.md` (flujos 1–2) |
| Landing / tema visual (tokens CSS) | `docs/ARQUITECTURA.md` (white-label) · `docs/MAPA-PAGINAS.md` |
| CMS público (servicios, ofertas) | `docs/sistemas/SERVICIOS-CMS.md` · `docs/sistemas/OFERTAS-WIDGET.md` |
| Diseño previo a implementación (spec) | `docs/specs/PLANTILLA.md` (copiar) · `docs/CONVENCIONES.md` §5 |
| Cookies / consentimiento | `docs/sistemas/COOKIES.md` · `docs/SEGURIDAD.md` |
| Panel admin / puerta / operación diaria | `docs/PANEL-ADMIN.md` · `docs/OPERATIVA-SECTOR-ORIGEN.md` |
| Post-form de invitados | `docs/sistemas/POSTFORM-INVITADOS.md` |
| Tests / suite / fakes / datos de prueba | `docs/TESTING.md` (fixture: §datos de prueba) · `docs/CONVENCIONES.md` (§3.bis/§3.ter) |
| Vocabulario de dominio (término ↔ código) | `docs/GLOSARIO.md` |
| Deuda técnica (vista única) | `docs/DEUDA.md` |
| Instalar un cliente nuevo (white-label) | `docs/INSTALACION-CLIENTE.md` |
| API v1 / SPA sidebar / app móvil | `docs/specs/api-v1.md` (Fase 3, 🟦 — **§10 → §10.septies «lo que el código enseñó», antes de tocar nada**) · `openapi/v1.yaml` (el CONTRATO: manda sobre el código) · `docs/DECISIONES.md` #21, #24, #26–#31 |
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
