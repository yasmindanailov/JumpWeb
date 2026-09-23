<?php

namespace App\Providers;

use App\Domain\Platform\Services\Analytics\Visitor;
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

        // Contestar a una INVITACIÓN DIGITAL (T4·6, `specs/celebracion-e-invitacion.md` §4.5·12;
        // `DECISIONES #578`). Por TOKEN, sumado al de IP, y por una razón propia.
        //
        // ⚠️⚠️ **Aquí el enlace lo tiene un grupo de clase entero**, así que el techo por IP no
        // significa gran cosa: son familias distintas desde redes distintas. Lo que hay que acotar es
        // cuánto se puede machacar UNA invitación — y no por el servidor, sino porque contestar es la
        // única operación de esta feature que **escribe** en nombre de un desconocido.
        //
        // ▶ El tope duro de verdad no es éste: es `3 × invitados` por invitación (`REPLY_CAP_PER_GUEST`),
        // que vive en el dominio y no se puede esperar a que expire. Esto solo pone caro el camino.
        RateLimiter::for('invitation-reply', static function (Request $request): Limit {
            $token = $request->route('token');

            return Limit::perMinute(10)->by('invitation-reply:'.(is_scalar($token) ? (string) $token : ''));
        });

        // La INGESTA del libro de eventos (`specs/analitica.md` §4.1, `#678`). ⚠️ **Sustituye al suelo, no
        // se suma**: `POST /events` sale del `throttle:api` con `withoutMiddleware`, porque apilado cada
        // lote descontaría del mismo cubo de 60/min que pagan catálogo y disponibilidad, y una familia
        // tras un NAT vería un 429 en el calendario provocado por la propia medición (spec §7.1,
        // producto-1). La clave es el VISITANTE (la cookie), con respaldo por IP; y un tope diario que
        // acota lo que un visitante puede meter en una tabla de 25 meses.
        RateLimiter::for('events', static function (Request $request): array {
            $key = 'events:'.(Visitor::fromRequest($request) ?? 'ip:'.$request->ip());

            return [
                Limit::perMinute((int) config('api.events.per_minute'))->by($key),
                Limit::perDay((int) config('api.events.per_day'))->by('day:'.$key),
            ];
        });
    }
}
