# CLAUDE.md — JumpWeb

> Índice/enrutador para el agente. Mantener CORTO; el detalle vive en `docs/`.

## ▶ Para continuar el proyecto (handoff)
1. Lee **`docs/ESTADO.md`** → foto viva (dónde estamos, qué sigue).
2. Lee **`docs/00-REFACTOR.md`** → tracker VIVO del refactor de generalización (fases + checklists).
3. Decisiones tomadas y su porqué: **`docs/DECISIONES.md`** (cronológico; no cargar entero).

## Qué es
**JumpWeb**: plataforma white-label de **reservas online** multi-sector — landing pública
(ES/EN/FR) + sistema de reservas con pago (señal o total) + panel admin (Filament) + API v1
para web y app móvil. Nace de una base en producción endurecida (ver `DECISIONES #1`);
**este repo es el PRODUCTO**, generalizado y sin marca de ningún cliente.

## Stack
Laravel 13 + MySQL · Blade SSR (landing) · Livewire v4 (en migración a SPA+API en el sidebar)
· Filament (panel) · Vite · Redsys (primer driver de pago).
- **Local:** Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. **Puertos propios** para
  convivir con otros stacks: web `localhost:8081` · MySQL `3308` · Mailpit `8028` (van en `.env`).

## Comandos clave (Docker)
- Arrancar / parar: `docker compose up -d` · `docker compose down`
- Artisan: `docker compose exec -u sail laravel.test php artisan <cmd>`
- Tests: `docker compose exec -u sail laravel.test php artisan test --parallel`
- Estilo: `docker compose exec -u sail laravel.test ./vendor/bin/pint`
  > ⚠️ **Siempre `-u sail`** (como root deja ficheros de root en `storage/` → 500 por permisos).
- Assets: `docker compose exec -u sail laravel.test npm run build`

## Principios (NO romper)
- **Data-driven:** todo configurable desde el panel; nada de negocio quemado en código.
- **White-label:** lógica genérica; marca = tokens CSS + tema/contenido en BD. 1 instalación por cliente.
- **API-first:** el dominio se expone por `/api/v1`; la web y la app móvil son clientes iguales.
- **Corrección antes que presentación:** la suite (2132 tests heredados) en verde es la red
  de TODO el refactor; no se avanza de fase con la suite rota.

## Reglas para el agente
1. Antes de tocar una feature, lee solo lo necesario de `docs/` (empieza por `ESTADO`).
2. Decisión nueva → línea en `docs/DECISIONES.md` (+ `[DECIDIDO]` con fecha en el doc afectado).
3. Al terminar una sesión: marca el progreso en `docs/00-REFACTOR.md` y actualiza `docs/ESTADO.md`.
4. **«Hecho» (✅) = código + prueba + validación.** Si falta algo, es 🟦.
5. **No fiarse de los ✅ en prosa:** contrastar con el código antes de construir encima.
6. Este repo NO despliega a ningún cliente: el cliente origen vive en su propio repo
   (`~/proyectos/jumpingjump`) con su propio canal de deploy. No cruzar cambios sin decisión explícita.
