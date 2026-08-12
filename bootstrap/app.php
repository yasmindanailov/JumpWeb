<?php

use App\Domain\Identity\Services\CookieConsent;
use App\Http\Middleware\EnsureSiteAvailable;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\RequiresStaffOrAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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

        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
            // Mantenimiento de sitio entero (#218, item 2). Va el ÚLTIMO de los tres: corre tras
            // `SetLocale` (el 503 se renderiza en el idioma resuelto) y queda envuelto por
            // `SecurityHeaders` (que añade sus cabeceras a la respuesta 503). Es fail-safe y
            // excluye `/admin`, `/pago/redsys`, `/lang` y `/up` por path.
            EnsureSiteAvailable::class,
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
        //
    })->create();
