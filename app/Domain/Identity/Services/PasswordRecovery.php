<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\PasswordResetResult;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Recuperación de contraseña: pedir el enlace y fijar la nueva
 * (Fase 3 · paso 3c, `docs/specs/api-v1.md` §4.6.3).
 *
 * Se apoya en el broker de Laravel —que ya valida el token y su caducidad— y le añade lo que el
 * proyecto exige y el framework no trae: un limitador propio por IP y la **no-enumeración**
 * (`SEC-06`), que es la razón de que esto no pueda ser un controlador delgado.
 *
 * ⚠️ **El limitador propio no es redundante con el de la ruta.** En la web, estas acciones corren
 * por el endpoint de Livewire (`/livewire/update`), que NO pasa por el `throttle` de la ruta GET
 * del formulario: sin este limitador se podría martillear el broker (auditoría Fase 1, A3). En la
 * API sí hay ruta POST propia, así que allí se suman las dos capas — y está bien que se sumen: una
 * cuenta por IP y la otra es el suelo genérico de `/api/v1`.
 */
class PasswordRecovery
{
    /** Peticiones de enlace por minuto e IP. */
    public const MAX_LINK_REQUESTS = 5;

    /** Intentos de fijar contraseña por minuto e IP. */
    public const MAX_RESET_ATTEMPTS = 6;

    /**
     * Pide el enlace de recuperación.
     *
     * Responde igual exista o no la cuenta: quien llama no puede saberlo y por tanto no puede
     * contarlo. El envío lo hace el broker, que ya no manda un segundo enlace en la misma ventana.
     */
    public function requestLink(string $email, string $ip): PasswordResetResult
    {
        $key = 'forgot:'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_LINK_REQUESTS)) {
            return PasswordResetResult::rateLimited(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        Password::sendResetLink(['email' => Str::lower(trim($email))]);
        Log::info('auth.password_reset_requested', ['ip' => $ip]);

        return PasswordResetResult::ok();
    }

    /**
     * Fija la nueva contraseña con el token del correo.
     *
     * Al conseguirlo pasan tres cosas, y las tres importan:
     *  1. se rota el `remember_token`, que invalida las cookies de «recuérdame»;
     *  2. se invalidan TODAS las credenciales del titular —sesiones y tokens de API— con
     *     `User::revokeAllAccess()` (paso 3a). Aquí no hay ninguna que preservar: el reset no
     *     autentica, y el caso de uso central es justo la víctima que ha perdido el control de su
     *     cuenta;
     *  3. se emite `PasswordReset`, el evento del framework que escuchan otras integraciones.
     */
    public function reset(string $token, string $email, string $password, string $ip): PasswordResetResult
    {
        $key = 'reset-password|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_RESET_ATTEMPTS)) {
            return PasswordResetResult::rateLimited(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $newPassword): void {
                // El cast `password => 'hashed'` de `User` hashea al asignar y detecta dobles
                // hashes, así que un `Hash::make` aquí sería redundante.
                $user->forceFill([
                    'password' => $newPassword,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->revokeAllAccess();

                event(new PasswordResetEvent($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            RateLimiter::clear($key);
            Log::info('auth.password_reset', ['ip' => $ip]);

            return PasswordResetResult::ok();
        }

        // Anti-enumeración (auditoría Fase 1, A3): un correo INEXISTENTE (`INVALID_USER`) y un
        // token inválido o caducado (`INVALID_TOKEN`) tienen que responder lo MISMO; si difirieran,
        // un atacante distinguiría qué correos están registrados. El throttle del broker sí se
        // distingue: es un límite de tasa, no una pista sobre la cuenta.
        return $status === Password::RESET_THROTTLED
            ? PasswordResetResult::brokerThrottled()
            : PasswordResetResult::invalid();
    }
}
