# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 (fundación) en curso.** Repo creado a partir de la base endurecida del proyecto
origen (ver `DECISIONES #1`): árbol exportado sin datos del cliente, con el fix de
aislamiento de la suite incluido, y doc fundacional propia. Plan completo del refactor
en **`00-REFACTOR.md`**.

## ▶ Próximo paso
1. Cerrar Fase 0: push a GitHub (`JumpWeb`, privado) · levantar el entorno local propio
   (puertos 8081/3308/8028) · **suite completa en verde** (2132 esperados) · CI.
2. Arrancar Fase 1 (desbranding): quitar «jumpingjump» (~161 apariciones), semilla demo
   neutra, portar doc técnica adaptada.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.

## Herencia
Base: Laravel 13 · 29 modelos · 65 migraciones · 16 Filament Resources · 10 Livewire ·
Redsys (sandbox) · suite 2132 verde (7931 aserciones) en el origen el 2026-08-12.
