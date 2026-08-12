# CLAUDE.md — JumpWeb

> Índice/enrutador para el agente. Mantener CORTO; el detalle vive en `docs/`.
> **Este proyecto lo desarrollan al 100% agentes IA** (`DECISIONES #7`): tu handoff es lo
> único que tendrá el siguiente agente. Protocolo en `docs/CONVENCIONES.md` (§7 y §5).

## ▶ Para continuar el proyecto (handoff)
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

## Principios (NO romper)
- **Data-driven:** todo configurable desde el panel; nada de negocio quemado en código.
- **White-label:** lógica genérica; marca = tokens CSS + tema/contenido en BD. 1 instalación/cliente.
- **API-first:** el dominio se expone por `/api/v1`; web y móvil son clientes iguales.
- **Corrección antes que presentación:** suite verde = red de TODO el refactor.
- **Invariantes heredadas** (`docs/INVARIANTES.md`): el endurecimiento del origen no se regresa.

## ⚡ Enrutado de contexto — LEE SOLO LO QUE NECESITES
| Si trabajas en… | Lee solo |
|---|---|
| Refactor (fases, alcance, arquitectura objetivo) | `docs/00-REFACTOR.md` · `docs/DECISIONES` #1–#8 |
| Dinero / pagos / Redsys / reembolsos | **`docs/INVARIANTES.md`** · `docs/sistemas/REDSYS.md` · `docs/MODELO-DATOS.md` (§pedidos/pagos) |
| Señal / depósito (pago parcial) | `docs/sistemas/DEPOSITO.md` · `docs/INVARIANTES.md` |
| Aforo / franjas / disponibilidad / calendario | **`docs/INVARIANTES.md`** · `docs/MODELO-DATOS.md` (§catálogo/aforo) · `docs/FLUJOS.md` |
| Compra / carrito / catálogo de productos | `docs/sistemas/COMPRA-PRODUCTOS.md` · `docs/FLUJOS.md` · `docs/MODELO-DATOS.md` |
| Auth / cuentas / RGPD | `docs/SEGURIDAD.md` · `docs/INVARIANTES.md` (§RGPD) · `docs/FLUJOS.md` (1–2) |
| Landing / CMS público / tema | `docs/ARQUITECTURA.md` · `docs/sistemas/SERVICIOS-CMS.md` · `docs/sistemas/OFERTAS-WIDGET.md` · `docs/MAPA-PAGINAS.md` |
| Cookies / consentimiento | `docs/sistemas/COOKIES.md` · `docs/SEGURIDAD.md` |
| Panel admin / puerta / operación diaria | `docs/PANEL-ADMIN.md` · `docs/OPERATIVA-SECTOR-ORIGEN.md` |
| Post-form de invitados | `docs/sistemas/POSTFORM-INVITADOS.md` |
| Tests / suite / fakes | `docs/TESTING.md` · `docs/CONVENCIONES.md` (§3.bis/§3.ter) |
| API v1 / SPA sidebar / app móvil | `docs/00-REFACTOR.md` (Fases 3–4) · `docs/DECISIONES` #3/#4 |
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
5. Al terminar la sesión: skill **`/cierre-sesion`** (suite+Pint, tracker, ESTADO fiel, commit+push).
6. **«Hecho» (✅) = código + prueba + verificación empírica** (skill **`/dod`**). Si falta algo, es 🟦.
7. **No tocar el repo del cliente origen** (`~/proyectos/jumpingjump`) desde sesiones de JumpWeb.
