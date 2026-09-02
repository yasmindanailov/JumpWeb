<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SocialLoginResult;
use App\Domain\Identity\Contracts\SocialProfile;
use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Platform\Services\AuditLogger;
use App\Notifications\SocialIdentityLinked;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Entrar con una identidad EXTERNA ya verificada (`docs/specs/auth-con-google.md` §5).
 *
 * Hermano de {@see PasswordLogin}: recibe una afirmación **ya comprobada** —solo
 * {@see GoogleOAuth} sabe fabricar un {@see SocialProfile}— y decide a qué cuenta corresponde.
 * Autentica cuando corresponde, igual que aquél con `Auth::attempt()`.
 *
 * **Qué NO hace, a propósito** (misma frontera que `PasswordLogin`): regenerar la sesión, limpiar el
 * desenlace de pago del cajón ni decidir a dónde va nadie después. Eso son efectos de la sesión WEB
 * y los pone quien atiende la petición (§6.5).
 *
 * ## Las tres puertas
 *
 * 1. **El `sub` ya está vinculado** (§5.1): se entra, cero pantallas.
 * 2. **Hay una cuenta con ese correo** (§5.2): se vincula **automáticamente**, `[DECIDIDO owner]`.
 * 3. **No hay nada**: se devuelve `NEEDS_REGISTRATION` y **no se crea absolutamente nada** — la
 *    cuenta nace al enviar la pantalla de §7, nunca antes, para que no exista el estado «existes y
 *    no puedes hacer nada».
 *
 * ## Lo que sostiene que la puerta 2 sea segura
 *
 * ⚠️⚠️ **La guarda dura es el `email_verified` del PROVEEDOR** (P1): si viene `false` —pasa en algunos
 * dominios de Workspace— no se vincula ni se crea nada. **Nunca se degrada a «el correo coincide»**.
 *
 * ⚠️⚠️ **Y la cuenta DESTINO sin verificar no se hereda: se toma** (P12, `[DECIDIDO owner]` Q2). El
 * alta pública crea cuentas sin verificar y `/mi-cuenta` no exige verificación, así que un tercero
 * pudo registrarse **antes** con el correo de la víctima y estar dentro. Al vincular se promueve la
 * cuenta a verificada, se **invalida la contraseña** y se llama a `revokeAllAccess()`: quien
 * estuviera dentro queda fuera y no puede volver sin el buzón, que no controla.
 * ▶ Rechazar en vez de tomar no era una salida: `users.email` es UNIQUE —no cabe una segunda cuenta—
 * y **6 de 48 cuentas de producción están sin verificar**, o sea que uno de cada ocho clientes
 * chocaría con un muro en su primer intento.
 * ▶ **Residuo asumido y dicho**: si el ocupante hubiera dejado datos dentro, el recién llegado los
 * vería. Esa cuenta está vacía en la práctica —dejar reservas exige pagarlas— pero no es imposible.
 *
 * ⚠️ **El equipo SÍ puede vincular** (`[DECIDIDO owner]` Q5, §6.6), y hay que saber lo que eso
 * significa: `AdminPanelProvider` no declara `authGuard`, así que la sesión que abre este servicio
 * **es la del panel**. Se acepta porque quien comprometa el Gmail de un empleado ya podía pedir un
 * reset de contraseña y entrar igual — Google no abre una puerta nueva, y encima aporta su 2FA, que
 * el panel no tiene. Lo que eso convierte en OBLIGATORIO es el aviso por correo de cada vinculación:
 * para una cuenta de equipo es la única señal de que alguien ha entrado por una puerta nueva.
 */
final class SocialLogin
{
    /**
     * @param  string  $via  por qué puerta se vincula, si hay que vincular ({@see UserIdentity::VIAS}).
     */
    public function enter(SocialProfile $profile, string $ip, string $via = UserIdentity::VIA_LOGIN): SocialLoginResult
    {
        // La guarda dura. Va la PRIMERA: sin buzón demostrado no hay nada que resolver, ni siquiera
        // buscar — que la búsqueda por correo ni se ejecute es parte de no degradarla nunca.
        if (! $profile->emailVerified) {
            Log::info('auth.social_email_unverified', ['ip' => $ip, 'provider' => $profile->provider]);

            return SocialLoginResult::refused(SocialLoginResult::REASON_EMAIL_UNVERIFIED);
        }

        $identity = UserIdentity::query()
            ->where('provider', $profile->provider)
            ->where('provider_id', $profile->subject)
            ->with('user')
            ->first();

        if ($identity !== null) {
            $user = $identity->user;

            // ⚠️ También por el camino del `sub`, no solo por el del correo: una cuenta que ejerció el
            // art. 17 no se resucita por ninguna puerta (`P3`). Sin esto, el vínculo de antes de la
            // supresión sería la única forma de entrar en una cuenta anonimizada.
            if ($user === null || $user->isAnonymized()) {
                Log::info('auth.social_refused', ['ip' => $ip, 'reason' => SocialLoginResult::REASON_ANONYMIZED]);

                return SocialLoginResult::refused(SocialLoginResult::REASON_ANONYMIZED);
            }

            $this->signIn($user, $ip, $profile->provider);

            return SocialLoginResult::signedIn($user);
        }

        $user = User::query()->where('email', $profile->email)->first();

        if ($user === null) {
            return SocialLoginResult::needsRegistration($profile);
        }

        if ($user->isAnonymized()) {
            // Cinturón: el correo de una cuenta anonimizada es `…@deleted.local` y ningún correo de
            // Google puede serlo, así que esta rama no debería alcanzarse. Se comprueba igualmente
            // porque el día que `ANONYMIZED_EMAIL_DOMAIN` cambie, el fallo sería silencioso.
            Log::info('auth.social_refused', ['ip' => $ip, 'reason' => SocialLoginResult::REASON_ANONYMIZED]);

            return SocialLoginResult::refused(SocialLoginResult::REASON_ANONYMIZED);
        }

        $outcome = $this->link($user, $profile, $ip, $via);

        if ($outcome->wasRefused()) {
            return $outcome;
        }

        // ⚠️ A partir de aquí se trabaja con la instancia que salió de la transacción, **no con
        // `$user`**: aquélla es la fila bloqueada y ya promovida, y ésta es una foto anterior. Con la
        // foto vieja, cualquier `save()` posterior arrastraría el `email_verified_at` de antes.
        $linked = $outcome->user;

        // El aviso va FUERA de la transacción y sin poder tumbarla: la vinculación ya es válida y un
        // fallo del SMTP no puede deshacerla. Misma doctrina que `SelfSignup::notifySafely()`.
        // Solo si el vínculo se ha creado AHORA: un retorno simultáneo que encuentra el trabajo hecho
        // no manda un segundo correo.
        if ($outcome->linked) {
            $this->notifySafely($linked, new SocialIdentityLinked($profile->provider, $outcome->promoted));
        }

        $this->signIn($linked, $ip, $profile->provider);

        return $outcome;
    }

    /**
     * Crea el vínculo y, si hace falta, TOMA la cuenta. Todo en una transacción con la fila del
     * titular bloqueada: es el mismo punto de serialización que usa el firmador del descargo, y por
     * la misma razón —dos retornos simultáneos del mismo correo se ordenan aquí—.
     */
    private function link(User $user, SocialProfile $profile, string $ip, string $via): SocialLoginResult
    {
        return DB::transaction(function () use ($user, $profile, $ip, $via): SocialLoginResult {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $existing = UserIdentity::query()
                ->where('user_id', $locked->getKey())
                ->where('provider', $profile->provider)
                ->first();

            if ($existing !== null && $existing->provider_id !== $profile->subject) {
                // Una cuenta, una llave por proveedor. Ver `SocialLoginResult::REASON_PROVIDER_CONFLICT`.
                Log::info('auth.social_refused', ['ip' => $ip, 'reason' => SocialLoginResult::REASON_PROVIDER_CONFLICT]);

                return SocialLoginResult::refused(SocialLoginResult::REASON_PROVIDER_CONFLICT);
            }

            // Idempotencia bajo el lock: si otro retorno simultáneo del mismo `sub` ya lo escribió, no
            // se crea una segunda fila ni se vuelve a avisar por correo. Lo mismo que hace el firmador
            // del descargo con la firma anterior.
            $alreadyLinked = $existing !== null;

            if (! $alreadyLinked) {
                UserIdentity::create([
                    'user_id' => $locked->getKey(),
                    'provider' => $profile->provider,
                    'provider_id' => $profile->subject,
                    'email_at_link' => $profile->email,
                    'linked_via' => $via,
                    'linked_at' => now(),
                ]);
            }

            // La toma de una cuenta sin verificar (P12). Las TRES cosas van juntas o no vale ninguna:
            // promover sin expulsar dejaría dentro a quien tuviera la contraseña; expulsar sin
            // invalidarla le dejaría volver a entrar con ella.
            $promoted = $locked->email_verified_at === null;

            if ($promoted) {
                $locked->forceFill([
                    'email_verified_at' => now(),
                    'password' => Str::random(60),
                ])->save();

                $locked->revokeAllAccess(CustomerCard::REASON_REVOKED);
            }

            if (! $alreadyLinked) {
                AuditLogger::log('identities.linked', $locked, [
                    'provider' => $profile->provider,
                    'via' => $via,
                    'promoted' => $promoted,
                ]);
            }

            return SocialLoginResult::signedIn($locked, linked: ! $alreadyLinked, promoted: $promoted);
        });
    }

    /**
     * Autentica y sella la entrada, exactamente lo que hace {@see PasswordLogin} tras acertar la
     * contraseña: sin esto, quien entra con Google no aparecería en «último acceso» del panel ni en
     * su propio export, y el log de accesos tendría un agujero con forma de Google.
     */
    private function signIn(User $user, string $ip, string $provider): void
    {
        Auth::login($user);

        // `saveQuietly`: sellar la última entrada no es un cambio del titular y no debe disparar
        // observers ni eventos de modelo.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        Log::info('auth.login', ['user_id' => $user->id, 'ip' => $ip, 'provider' => $provider]);
    }

    private function notifySafely(User $user, Notification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('auth.social_link_email_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
