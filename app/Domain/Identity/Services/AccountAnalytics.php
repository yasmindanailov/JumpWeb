<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Jobs\ForgetPersonInDriver;
use App\Domain\Platform\Services\Analytics\AccountLinker;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **Lo que la CUENTA guarda del régimen identificado** (`docs/specs/analitica.md` §4.3, T3a·3): el enlace de
 * su navegación (que hace `Platform\AccountLinker`), la prueba de ese enlace en `consents` (tipo `analytics`),
 * su primera atribución (`users.first_attribution`, escrita una vez) y su OPOSICIÓN (`users.analytics_opt_out`,
 * art. 21), que se ejerce desde «Mi cuenta → Privacidad» o `PUT /me/analytics`, sin contraseña: retirar es tan
 * fácil como dar (art. 7.3).
 *
 * ⚠️ **Nunca tumba a quien lo llama**: el alta, el login o el cobro no pueden fallar porque el enlace falle.
 * {@see linkIfConsented()} traga cualquier error con un `Log`.
 * ⚠️ Retirar = desvincular (sesiones y hechos vuelven al agregado) + sellar la prueba (`revoked_at`, no se
 * borra: sigue diciendo que en su día se vinculó bajo consentimiento) + el olvido en el driver
 * ({@see ForgetPersonInDriver}). Volver a dar = quitar la oposición y, si la petición trae la categoría, enlazar
 * en el acto con una prueba nueva. Idempotente en las dos direcciones, como `AccountPrivacy::setMarketing()`.
 */
class AccountAnalytics
{
    public function __construct(
        private readonly AccountLinker $linker,
        private readonly AttributionContext $context,
    ) {}

    /**
     * Al identificarse (login, alta): si la petición trae la categoría `analytics` y la cuenta no se opuso, ata
     * las sesiones y deja escritos la primera atribución (solo la primera vez) y la prueba (solo si no hay una
     * viva). Devuelve si se enlazó algo.
     */
    public function linkIfConsented(User $user, ?string $ip = null): bool
    {
        try {
            if ($user->analytics_opt_out) {
                return false;
            }

            // La cookie de ESTA petición manda si está decidida (la foto de la sesión del libro puede ir por detrás).
            $cookie = CookieConsent::state(request());
            $result = $this->linker->link((int) $user->getKey(), $this->context, false, $cookie['decided'] ? (bool) $cookie['analytics'] : null);

            if ($result === null) {
                return false;
            }

            if ($user->getAttribute('first_attribution') === null && $result['first_touch'] !== null) {
                $user->forceFill(['first_attribution' => $result['first_touch']])->saveQuietly();
            }

            if (! $user->consents()->where('type', Consent::TYPE_ANALYTICS)->whereNull('revoked_at')->exists()) {
                $user->consents()->create([
                    'type' => Consent::TYPE_ANALYTICS,
                    'accepted_at' => now(),
                    'ip' => $ip,
                    'version' => CookieConsent::POLICY_VERSION,
                ]);
            }

            Log::info('analytics.account_linked', ['user_id' => $user->getKey(), 'visits' => $result['visits']]);

            return true;
        } catch (Throwable $e) {
            Log::warning('analytics.account_link_failed', ['user_id' => $user->getKey(), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * **Oponerse, o dejar de oponerse** (art. 21 y 7.3), desde la cuenta. `$linked = false` retira.
     *
     * @return bool si el estado ha CAMBIADO
     */
    public function setLinked(User $user, bool $linked, string $ip): bool
    {
        if ((bool) $user->analytics_opt_out === ! $linked) {
            return false;
        }

        if ($linked) {
            $user->forceFill(['analytics_opt_out' => false])->save();
            $this->linkIfConsented($user, $ip);
            Log::info('account.analytics_changed', ['user_id' => $user->getKey(), 'linked' => true]);

            return true;
        }

        DB::transaction(function () use ($user, $ip): void {
            $user->forceFill(['analytics_opt_out' => true])->save();

            // La prueba se SELLA, no se borra: sigue diciendo que se vinculó bajo consentimiento, y desde cuándo no.
            $user->consents()
                ->where('type', Consent::TYPE_ANALYTICS)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_ip' => $ip]);

            $this->linker->unlink((int) $user->getKey());
        });

        // El olvido en el driver va en cola y DESPUÉS de la transacción: un tercero no puede retrasar la retirada.
        $job = ForgetPersonInDriver::forUser((int) $user->getKey());
        if ($job !== null) {
            dispatch($job);
        }

        Log::info('account.analytics_changed', ['user_id' => $user->getKey(), 'linked' => false]);

        return true;
    }
}
