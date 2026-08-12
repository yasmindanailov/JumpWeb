# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 (fundación) ✅ · Fase 1 (desbranding) 🟦 abierta · sistema documental endurecido.**
- Repo `yasmindanailov/JumpWeb` (privado); suite **2132 en verde** (7931 aserciones,
  `--parallel` 1m13s) · Pint limpio.
- Entorno local propio arriba (web **8081** · MySQL 3308 · Mailpit 8028 — `.env.example`
  ya trae estos puertos), conviviendo con el stack del cliente origen.
- **Capa agent-first** (`DECISIONES #7`) + **endurecimiento documental** (`DECISIONES #10`,
  tras auditoría adversarial de 7 lentes / 51 hallazgos): `scripts/docs-check.sh` corre en
  el pre-push (enlaces, anclas §N, rutas citadas, recuentos, coherencia de fases y de
  marcadores); el gate aplica solo a `main` (`wip/…` empujable en rojo); guarda
  `VERIFY_CONC=1` si el push toca `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator`;
  `INVARIANTES.md` con las 54 celdas de verificación resueltas a tests reales (2 huecos de
  cobertura marcados ⚠️: throttle de rutas Redsys y gate no-prod de `tableExists`);
  `MAPA-PAGINAS.md` reconciliado contra las 105 rutas reales.
- ⚠️ La base exportada quedó sin datos de **infraestructura** del cliente (IP/SSH/credenciales,
  verificado); sus datos de **negocio** (dirección, mapa, SEO, jurisdicción en
  `ProductionSeeder`/`LegalContent`) siguen en el árbol → ítem explícito de Fase 1.
- **CI = gate local**: hook `pre-push` (docs-check + Pint + suite, solo `main`). Activar por
  clon: `git config core.hooksPath .githooks` (`DECISIONES #9`/`#10`).

## ▶ Próximo paso
**Fase 1 — Desbranding y generalización superficial**: checklist ÚNICA en
`00-REFACTOR.md` §Fase 1 (aquí no se duplica — `DECISIONES #10`). Siguiente ítem concreto:
destino de los datos de negocio del cliente (`ProductionSeeder`/`LegalContent`) y limpieza
de marca (recuento canónico en el propio checklist — aquí no se duplica la cifra).
Pendiente del owner: decisión de slugs públicos (§Fase 1) y arranque de los Paquetes C+D
del endurecimiento documental (skill de arranque, escalado, `docs/specs/`, glosario…).

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- BD dev migrada y SIN sembrar (la semilla demo neutra es tarea de Fase 1; el runbook del
  README ya no siembra por defecto — el seed heredado es del sector origen).

## Herencia
Base: Laravel 13 · 30 modelos · 70 migraciones · 17 Filament Resources · Livewire v4 ·
Redsys (sandbox) · suite 2132 verde heredada del origen (2026-08-12).
