# JumpWeb

Plataforma **white-label de reservas online** para negocios con aforo por franjas
(ocio, eventos, actividades): landing pública multiidioma, sistema de reservas con
pago online, panel de administración y —en el roadmap— app móvil sobre la misma API.

> **Estado:** en refactor de generalización a partir de una base en producción
> probada (suite de 2136 tests (2026-08-12; recuento vivo en `docs/ESTADO.md`),
> flujos de dinero/aforo endurecidos). Tracker vivo: [`docs/00-REFACTOR.md`](docs/00-REFACTOR.md).

## Stack

- **Backend:** Laravel 13 + MySQL. Dominio en módulos (`app/Domain/<Contexto>/`, en migración desde `app/Support/`).
- **Web pública:** Blade server-rendered (SEO) + Alpine.js; CSS estático propio.
- **Sistema de reservas (sidebar):** Livewire v4 → en migración a **SPA contra la API v1**.
- **Panel admin:** Filament.
- **Pagos:** Redsys (primer driver; abstracción de proveedor en el roadmap).
- **API:** REST versionada (`/api/v1`, Sanctum) — en construcción, API-first.

## Puesta en marcha (local)

Docker (Laravel Sail) dentro de WSL2. Este proyecto convive con otros stacks Sail en la
misma máquina: `.env.example` ya trae los puertos propios de dev (web **8081** · MySQL
**3308** · Mailpit **8028**); cada instalación puede cambiarlos en su `.env` (no versionado).

```bash
# bootstrap sin vendor (una vez):
docker run --rm -v $(pwd):/app -w /app laravelsail/php85-composer:latest composer install --ignore-platform-reqs
cp .env.example .env   # puertos de dev incluidos; APP_KEY se genera abajo
git config core.hooksPath .githooks   # gate local de push a main: docs-check + Pint + Larastan + builds + suite
docker compose up -d
docker compose exec -u sail laravel.test php artisan key:generate
docker compose exec -u sail laravel.test php artisan migrate
docker compose exec -u sail laravel.test npm ci && docker compose exec -u sail laravel.test npm run build
```

> La BD queda migrada y sin sembrar. Para un entorno operativo: `php artisan db:seed`
> siembra el negocio de EJEMPLO «SaltoPark» (semilla neutra, `DECISIONES #12`) y crea,
> solo fuera de producción, `admin@jumpweb.test` / `empleado@jumpweb.test` (contraseña
> `password`). Instalación real de un cliente: `docs/INSTALACION-CLIENTE.md`.

- Web: `http://localhost:8081` · Mailpit: `http://localhost:8028` · MySQL: `localhost:3308`
- Tests: `docker compose exec -u sail laravel.test php artisan test --parallel`
- Estilo: `docker compose exec -u sail laravel.test ./vendor/bin/pint`
- Análisis estático (Larastan, nivel 5 con línea base): `docker compose exec -u sail laravel.test ./vendor/bin/phpstan analyse --memory-limit=2G`

> ⚠️ `docker compose exec` **siempre con `-u sail`**: como root deja ficheros en
> `storage/` que el servidor web no puede escribir (500 por permisos).

## Principios

1. **Data-driven:** todo configurable desde el panel; nada de negocio quemado en código.
2. **White-label real:** lógica genérica; marca = tokens CSS + tema y contenido en BD.
   Una instalación por cliente.
3. **API-first:** el dominio se expone por una API versionada; web y móvil son clientes.
4. **Corrección antes que presentación:** la suite en verde es la red de todo cambio.
