# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 (fundación) COMPLETA salvo CI, y el repo ya es agent-first.**
- Repo `yasmindanailov/JumpWeb` (privado); base exportada del origen sin datos del cliente;
  **suite 2132 en verde** (7931 aserciones, `--parallel` 1m13s) · Pint limpio.
- Entorno local propio arriba (web **8081** · MySQL 3308 · Mailpit 8028), conviviendo con el
  stack del cliente origen.
- **Capa agent-first** (`DECISIONES #7`): CLAUDE.md enrutador · CONVENCIONES (protocolo de
  agentes) · skills `/cierre-sesion` y `/dod` · permisos en `.claude/settings.json`.
- **Doc técnica portada** (`DECISIONES #8`): 16 docs adaptados del origen + `MODELO-DATOS.md`
  regenerado desde el código + **`INVARIANTES.md`** (leer antes de tocar dinero/aforo/RGPD).
  Índice: `docs/README.md`.
- CI heredado pero bloqueado: falta habilitar la facturación de GitHub Actions (owner).

## ▶ Próximo paso
**Fase 1 — Desbranding y generalización superficial** (`00-REFACTOR.md` §Fase 1). Quedan:
1. Quitar «jumpingjump» del código (~161 apariciones) + renombrar tema mail
   `jumpingjump.css`, comandos `jj:*`, prefijo de pedidos `JJ-`, cookie `jj_*`, claves i18n.
2. Semilla demo neutra separada del fixture de tests.
3. Decidir slugs de rutas públicas (hoy en español) → `DECISIONES`.
4. Wordmark de correos → data-driven (`business.name`, hoy `config('app.name')`).
5. Diseñar la generalización del vocabulario de dominio (park/attraction/birthday/puerta/
   waiver) como entrada de la Fase 2.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- BD dev migrada y SIN sembrar (la semilla demo neutra es tarea de Fase 1).

## Herencia
Base: Laravel 13 · 31 modelos · 70 migraciones · 17 Filament Resources · Livewire v4 ·
Redsys (sandbox) · suite 2132 verde heredada del origen (2026-08-12).
