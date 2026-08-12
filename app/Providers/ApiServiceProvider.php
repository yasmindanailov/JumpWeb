<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Fase 3 · paso 0 — casa de la superficie `/api/v1`.
 *
 * Existe separado de `AppServiceProvider` por el mismo motivo por el que Fase 2 separó los
 * módulos: `AppServiceProvider` ya arrastra el morphMap, el composer global y los guards de
 * modelo, y la API va a traer limitadores, bindings y convenciones propias durante seis pasos.
 * Mismo patrón que `BookingServiceProvider`/`PaymentsServiceProvider`.
 */
class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Limitadores nombrados de la API.
     *
     * `api` es el SUELO de toda la superficie (spec §4.7: «`RateLimiter::for()` no existe hoy en
     * `app/`»). No pretende defender un endpoint concreto: los sensibles traen el suyo, más
     * estricto, junto a su ruta.
     *
     * La clave del cubo se resuelve por identidad y solo cae a la IP si no hay ninguna. Se pregunta
     * al guard `sanctum` —y no solo al de sesión— porque este middleware corre ANTES del `auth:` de
     * ruta: sin ello, todos los clientes Bearer detrás de un mismo NAT compartirían cubo y se
     * limitarían entre sí. El coste es nulo en la práctica: sin credenciales el guard no consulta
     * la base de datos, y con Bearer la instancia queda cacheada para el `auth:sanctum` posterior.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('api', static function (Request $request): Limit {
            $identity = $request->user('sanctum')?->getAuthIdentifier();

            return Limit::perMinute((int) config('api.rate_limit.per_minute'))
                ->by($identity !== null ? 'user:'.$identity : 'ip:'.$request->ip());
        });
    }
}
