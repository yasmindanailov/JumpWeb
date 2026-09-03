<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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

        // **El post-form, limitado POR RESERVA** (`SEC-06`, D12 de
        // `specs/complementos-post-reserva.md`). Desde que ahí se compran extras, ese formulario
        // MUEVE DINERO, y su enlace viaja por correo y se reenvía: un tercero que lo tenga puede
        // encargar y desencargar sin descanso.
        //
        // ⚠️⚠️ **El `throttle:30,1` que ya había NO cubre esto**: sin sesión su clave es la IP, así
        // que treinta peticiones por minuto **por cada IP** caben sobre la MISMA reserva — y con
        // ellas treinta correos al titular, que es la única señal de que alguien está encargando en
        // su nombre. Aquí la clave es la reserva, así que el techo es del sujeto y no del emisor.
        //
        // ⚠️ **No sustituye al de IP, se suma**: aquél protege al servidor de un barrido; éste
        // protege UNA reserva. Y no entra anti-bot (D12): para llegar hasta aquí hay que traer un
        // HMAC válido de esta URL exacta, y un Turnstile fallaría también a personas —que aquí se
        // paga con un padre que cree tener la tarta pedida—.
        RateLimiter::for('guest-form', static function (Request $request): Limit {
            $reservation = $request->route('reservation');
            $key = $reservation instanceof Model
                ? (string) $reservation->getKey()
                : (string) (is_scalar($reservation) ? $reservation : '');

            return Limit::perMinute(12)->by('guest-form:'.$key);
        });
    }
}
