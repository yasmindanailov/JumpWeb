# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 (desbranding y generalización superficial) ✅ COMPLETA · siguiente: Fase 2.**
- Suite **2136 en verde** (7941 aserciones, `--parallel` ~1m15s, run del 2026-08-12) · Pint
  limpio · `docs-check` verde · **`purchase:verify-oversell` y `redsys:verify-concurrency`
  verificados en verde sobre MySQL real** (primera ejecución en este repo).
- **Fase 1 hecha** (`DECISIONES #12`, detalle en el tracker): marca del cliente retirada
  (quedan 6 líneas intencionales de protección), prefijo de pedidos configurable
  (`sales.order_prefix`, default `R-`), tema mail `brand.css`, cookie `cookie_consent`,
  comando `app:purge-customers`, wordmark/PDFs/panel data-driven (`business.name`),
  **semilla neutra «SaltoPark»** (`ProductionSeeder`, sin datos del cliente; fixture de
  tests intacto), jurisdicción legal → setting `legal.jurisdiction` (tokens
  `:business_name`/`:jurisdiction`), y spec de vocabulario en
  `docs/specs/vocabulario-dominio.md` (estreno de specs/).
- Sistema documental endurecido completo (`DECISIONES #10`/`#11`): gate `docs-check` en
  pre-push (solo `main`), guarda `VERIFY_CONC=1`, ciclo del agente completo
  (`/arranque-sesion` · `/dod` ×4 · `/cierre-sesion`), permisos en 3 capas con
  `guard-bash`, invariantes con ID (PAY-…SUITE-NN), GLOSARIO · DEUDA ·
  INSTALACION-CLIENTE · TESTING §datos.
- Entorno local: web `8081` · MySQL `3308` · Mailpit `8028`; **BD dev sembrada con
  SaltoPark** (`db:seed`; usuarios dev `admin@jumpweb.test` / `empleado@jumpweb.test`,
  contraseña `password`).

## ▶ Próximo paso
**Fase 2 — Modularización del dominio** (checklist ÚNICA en `00-REFACTOR.md` §Fase 2).
Empezar por: **el prerequisito morphMap** (sin él, mover/renombrar modelos rompe datos
polimórficos) y el diseño de contextos con revisión multi-agente — con
`docs/specs/vocabulario-dominio.md` como entrada ya escrita.
**Pendiente del owner** (❗): 2FA del panel admin (sin plan — `DEUDA.md` §Media) ·
mecanismo del primer admin de producción (`INSTALACION-CLIENTE.md` §5) · backlog de
producto de Fase 6.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- Push de `main` pendiente de `/cierre-sesion` (commits locales acumulados; el gate
  correrá docs-check + Pint + suite, y exigirá `VERIFY_CONC=1` porque la fase tocó
  `OrderCreator` — los comandos verify YA se corrieron en verde).

## Herencia
Base: Laravel 13 · 30 modelos · 71 migraciones · 17 Filament Resources · Livewire v4 ·
Redsys (sandbox) · suite 2132 verde heredada del origen (2026-08-12; hoy 2136 con los
tests de Fase 1).
