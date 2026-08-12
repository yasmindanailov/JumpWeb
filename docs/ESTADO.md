# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 (fundación) COMPLETA salvo CI.** Repo `yasmindanailov/JumpWeb` (privado) con commit
fundacional: base exportada del origen sin datos del cliente (verificado por grep), fix de
aislamiento de la suite incluido, doc propia. Entorno local propio arriba (web **8081** ·
MySQL 3308 · Mailpit 8028) conviviendo con el stack del origen. **Suite 2132 en verde** en el
repo nuevo (único ajuste: `MailThemeTest` hecho brand-agnostic — asertaba el literal de la
marca origen; el wordmark de correos usa `config('app.name')` → hacerlo data-driven en Fase 1).
CI heredado pero bloqueado por facturación de GitHub Actions.

## ▶ Próximo paso
**Fase 1 — Desbranding y generalización superficial** (`00-REFACTOR.md` §Fase 1):
quitar «jumpingjump» (~161 apariciones), renombrar tema mail/`jj:*`, semilla demo neutra,
decidir slugs de rutas, portar doc técnica adaptada, y diseñar la generalización del
vocabulario de dominio (park/attraction/birthday/puerta/waiver) para la Fase 2.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- BD dev migrada y SIN sembrar (la semilla demo neutra es tarea de Fase 1).

## Herencia
Base: Laravel 13 · 29 modelos · 65 migraciones · 16 Filament Resources · 10 Livewire ·
Redsys (sandbox) · suite 2132 verde (7931 aserciones) heredada del origen (2026-08-12).
