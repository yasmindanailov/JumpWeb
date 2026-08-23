<?php

use App\Domain\Identity\Services\CookieConsent;
use App\Http\Api\ApiExceptionRenderer;
use App\Http\Api\ApiSurface;
use App\Http\Middleware\Api\ApiLocale;
use App\Http\Middleware\Api\NoStoreWhenAuthenticated;
use App\Http\Middleware\EnsureSiteAvailable;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\NoStoreWebResponses;
use App\Http\Middleware\RequiresStaffOrAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API v1 (Fase 3, `docs/specs/api-v1.md` §4.1). El prefijo sale de `ApiSurface::PREFIX`,
        // que es también quien decide qué peticiones reciben el sobre de error JSON y el 503 en
        // JSON: una sola fuente, para que las tres cosas no puedan desalinearse.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: ApiSurface::PREFIX,
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Producción (Fase 4): la app corre detrás del proxy de Enhance (y posiblemente Cloudflare),
        // que termina el TLS. Confiar en los proxies para que Laravel lea `X-Forwarded-Proto/Host`
        // y detecte HTTPS — sin esto, `SESSION_SECURE_COOKIE`, las URLs absolutas y la firma de
        // Redsys (que depende de la URL) se romperían tras el balanceador. `at: '*'` confía en el
        // proxy inmediato del host gestionado; se puede acotar a los rangos de Enhance/Cloudflare
        // cuando se conozcan. Sin efecto en peticiones sin cabeceras `X-Forwarded-*` (tests/local).
        $middleware->trustProxies(at: '*');

        // Protección anti host-header injection: solo se aceptan Host = dominio de `APP_URL` y sus
        // subdominios (deriva de la config, así que vale para cualquier dominio que ponga la clienta).
        // El propio middleware es NO-OP en `local` y bajo tests (`shouldSpecifyTrustedHosts`).
        $middleware->trustHosts();

        // ── `no-store` en la web (`RGPD-04`), 2026-08-23 ────────────────────────────────────────
        // Repone una cabecera que hasta hoy ponía un ACCIDENTE: no la emitía ningún middleware de
        // este proyecto sino **Livewire**, cuyo hook de componente enciende un flag que un
        // middleware global del paquete usa para estamparla. O sea que el sitio entero llevaba
        // `no-store` porque el layout renderizaba un componente Livewire — y el último que queda se
        // retira en `specs/account-context-vue.md`. Medido A/B: sin componente, `no-cache, private`.
        //
        // ⚠️ **GLOBAL y no en el grupo `web`, y lo decidió la medición**: un middleware de grupo solo
        // corre en rutas que CASAN, y un 404 de URI desconocida no entra en `web` **pero sí pinta el
        // layout**, o sea el nav con el nombre del titular. Con él en el grupo, la tabla de verdad
        // daba un rojo justo ahí. El propio middleware lleva el detalle y la puerta de `/api/v1`,
        // que decide por identidad a propósito (`PERF-02`).
        $middleware->append(NoStoreWebResponses::class);

        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
            // Mantenimiento de sitio entero (#218, item 2). Va el ÚLTIMO de los tres: corre tras
            // `SetLocale` (el 503 se renderiza en el idioma resuelto) y queda envuelto por
            // `SecurityHeaders` (que añade sus cabeceras a la respuesta 503). Es fail-safe y
            // excluye `/admin`, `/pago/redsys`, `/lang` y `/up` por path.
            EnsureSiteAvailable::class,
        ]);

        // ── Grupo `api` — declarado PIEZA A PIEZA (Fase 3 · paso 0, spec §4.7) ────────────────
        // Se reemplaza el grupo por defecto en vez de añadirle piezas: el orden ES el diseño y
        // aquí queda auditable de un vistazo, sin depender de dónde inserte `append`/`prepend`.
        // El motivo de fondo: `SetLocale`, `SecurityHeaders` y `EnsureSiteAvailable` estaban SOLO
        // en `web`, así que una API «que hereda lo de la web» no habría heredado nada.
        // De fuera hacia dentro, y cada posición por un motivo:
        //  1. `SecurityHeaders` — envuelve TODO, así que también estampa sus cabeceras en el 429
        //     del limitador y en el 503 del mantenimiento (`SEC-01`: la superficie sensible no se
        //     queda fuera).
        //  2. `EnsureFrontendRequestsAreStateful` — modo SPA de Sanctum: solo para peticiones del
        //     propio frontend, a las que inyecta cookies + sesión + CSRF. Debe ir antes que nadie
        //     que quiera leer la sesión.
        //  3. `ApiLocale` — lee esa sesión (nunca la escribe) y resuelve el idioma. Antes del
        //     mantenimiento y del limitador para que sus mensajes salgan traducidos.
        //  4. `EnsureSiteAvailable` — el kill-switch de #218 alcanza también a la API, con render
        //     JSON. La decisión está escrita en el spec §4.7 y razonada en el propio middleware.
        //  5. `NoStoreWhenAuthenticated` — `RGPD-04` por defecto en la superficie autenticada.
        //  6. `throttle:api` — suelo genérico (`config/api.php`); los endpoints sensibles traen su
        //     propio limitador, más estricto, junto a su ruta.
        //     ⚠️ Medido, no supuesto: Laravel REORDENA por `$middlewarePriority`, donde
        //     `AuthenticatesRequests` va ANTES que `ThrottleRequests`. Consecuencia: en una ruta
        //     con `auth:`, el 401 se lanza sin pasar por el limitador; en una ruta PÚBLICA, el
        //     limitador cuenta siempre. Se acepta el estándar en vez de forzar la prioridad,
        //     porque invertirla afectaría también a la web y ahí sería PEOR: rutas como
        //     `verification.send` usan `throttle` con la clave por defecto, y un anónimo pasaría a
        //     consumir el cubo por IP de los usuarios legítimos tras el mismo NAT. Lo que de
        //     verdad necesita techo —login, registro, reset, catálogo, disponibilidad, quote— es
        //     público, así que sí lo recibe. Cubierto por `ApiEnvelopeTest`.
        //  7. `SubstituteBindings` — resolución de parámetros de ruta, lo más adentro posible.
        $middleware->group('api', [
            SecurityHeaders::class,
            EnsureFrontendRequestsAreStateful::class,
            ApiLocale::class,
            EnsureSiteAvailable::class,
            NoStoreWhenAuthenticated::class,
            'throttle:api',
            SubstituteBindings::class,
        ]);

        // Panel admin (Fase 7): alias para rutas que exigen rol admin o staff.
        // Defense in depth junto a `User::canAccessPanel()` (gate de Filament).
        // Ver `docs/PLAN-FASE-7-PANEL.md` §1.3.
        $middleware->alias([
            'staff_or_admin' => RequiresStaffOrAdmin::class,
            // `no-store` para respuestas con PII de menores (PDFs operativos + post-form, L1).
            'no-store' => NoStore::class,
        ]);

        // Cookie de consentimiento de cookies (#219): NO se cifra → la leen el servidor
        // (`CookieConsent::state`, bloqueo previo de iframes de tercero) Y Alpine (UI del banner).
        // No es dato sensible ni de seguridad: solo guarda qué categorías no necesarias aceptó el
        // visitante. Ver `docs/PLAN-COOKIES.md` §4 (D4).
        $middleware->encryptCookies(except: [
            CookieConsent::COOKIE_NAME,
        ]);

        // Vuelta/notificación de Redsys (Fase 5.5b/5.5c/5.5d): POST cross-site firmado por
        // Redsys. La cookie de sesión Laravel (SameSite=Lax) NO viaja en estos POST → el
        // token CSRF no está disponible y no debe estarlo. La autenticidad la prueba
        // `Ds_Signature` (HMAC-SHA512 V2). Ver docs/PLAN-REDSYS.md §8 y la decisión #104.
        $middleware->validateCsrfTokens(except: [
            'pago/redsys/retorno-ok',
            'pago/redsys/retorno-ko',
            'pago/redsys/notificacion',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sobre de error único de `/api/v1` (Fase 3 · paso 0, spec §4.3). Devuelve `null` para
        // todo lo que no sea la API, así que la web conserva intactas sus páginas de error.
        $exceptions->render(
            fn (Throwable $e, Request $request) => (new ApiExceptionRenderer)->render($e, $request)
        );
    })->create();
