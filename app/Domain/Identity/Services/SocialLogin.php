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
 * Y una CUARTA que no entra por `enter()` porque no es entrar (`#347`, §21.3):
 * {@see self::linkToAccount()} — **vincular a la cuenta en la que ya se está**. Va aparte porque su
 * intención es otra y su veredicto también: no autentica, no promueve y no expulsa.
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
     * **CUARTA puerta: vincular a la cuenta en la que YA se está** (`#347`, §21.3).
     *
     * Hasta hoy no existía y su ausencia tenía consecuencia: un titular identificado que pasara por
     * `/auth/google` **cambiaba de cuenta** si su Google resolvía a otra (la spec lo avisa en §18.6).
     * Es la conducta correcta de «entrar con Google» y la equivocada para «vincular la mía», así que
     * lo que faltaba era la INTENCIÓN, no una comprobación más.
     *
     * ## En qué se diferencia de `enter()`, y por qué cada cosa
     *
     * ⚠️⚠️ **NO autentica.** La sesión ya está abierta y este camino no la toca: si el `sub` resolviera
     * a otro titular y le abriéramos su sesión, «vincular» sería un cambio de cuenta encubierto.
     *
     * ⚠️⚠️ **NO promueve ni expulsa.** La toma de `enter()` (P12) existe porque allí la única prueba es
     * el CORREO y hay que desalojar a quien pudiera estar dentro. Aquí quien pide el vínculo ya está
     * dentro y demostrado; tocar `email_verified_at` sería afirmar algo sobre un buzón que nadie ha
     * comprobado —la doctrina de `#336`: se acredita a la PERSONA, nunca al BUZÓN—. Si la cuenta
     * estaba sin verificar, sigue sin verificarlo, que es su estado honesto.
     *
     * ⚠️ **El correo de Google puede ser DISTINTO del de la cuenta, y se admite**: la clave es el
     * `sub` (§6.1) y aquí el titular se ha identificado él mismo, que es prueba más fuerte que la
     * coincidencia de correo en la que se apoya §5.2. El correo se guarda como copia, igual que allí.
     *
     * ⚠️ **La guarda dura de `email_verified` SÍ se conserva**, aunque aquí no identifique a nadie:
     * `email_at_link` se guarda como prueba de con qué dirección se vinculó, y guardar como prueba una
     * dirección que el proveedor no da por buena es guardar una prueba falsa.
     *
     * ## Lo que sostiene que sea seguro sin pedir la contraseña
     *
     * Desvincular sí la exige y esto no, y la asimetría es deliberada: desvincular puede dejarte
     * FUERA, y vincular no. Contra el escenario que sí importa —una sesión robada que planta su
     * Google como puerta trasera— hay dos defensas que ya existen y son las mismas de §5.2: el
     * **aviso por correo** de cada vinculación (detección) y que **`revokeAllAccess()` se lleve las
     * identidades** (`RGPD-06`), o sea que «he olvidado mi contraseña» cierra la puerta.
     * ▶ **Lo que NO cierra, dicho**: un cambio VOLUNTARIO de contraseña no retira el vínculo, por la
     * misma razón por la que no retira el carné. Es idéntico al vínculo automático de §5.2: este
     * camino no añade una clase de riesgo nueva, solo otra forma de llegar a la misma.
     */
    public function linkToAccount(User $holder, SocialProfile $profile, string $ip): SocialLoginResult
    {
        if (! $profile->emailVerified) {
            Log::info('auth.social_email_unverified', ['ip' => $ip, 'provider' => $profile->provider]);

            return SocialLoginResult::refused(SocialLoginResult::REASON_EMAIL_UNVERIFIED);
        }

        // Cinturón: una cuenta anonimizada no puede tener sesión abierta, pero el día que eso cambie
        // el fallo sería silencioso — y aquí se estaría dando una llave a una cuenta suprimida.
        if ($holder->isAnonymized()) {
            Log::info('auth.social_refused', ['ip' => $ip, 'reason' => SocialLoginResult::REASON_ANONYMIZED]);

            return SocialLoginResult::refused(SocialLoginResult::REASON_ANONYMIZED);
        }

        return DB::transaction(function () use ($holder, $profile, $ip): SocialLoginResult {
            $locked = User::query()->whereKey($holder->getKey())->lockForUpdate()->firstOrFail();

            // ⚠️ Las DOS preguntas van bajo el mismo lock y en este orden. Primero «¿de quién es esta
            // llave?», porque si es de otro no hay nada más que decidir; después «¿esta cuenta ya
            // tiene una?». Al revés, dos titulares vinculando el mismo `sub` a la vez podrían pasar
            // los dos la segunda comprobación y chocar contra el `UNIQUE` con un 500.
            $ofTheKey = UserIdentity::query()
                ->where('provider', $profile->provider)
                ->where('provider_id', $profile->subject)
                ->first();

            if ($ofTheKey !== null && (int) $ofTheKey->user_id !== (int) $locked->getKey()) {
                Log::info('auth.social_refused', ['ip' => $ip, 'reason' => SocialLoginResult::REASON_PROVIDER_TAKEN]);

                return SocialLoginResult::refused(SocialLoginResult::REASON_PROVIDER_TAKEN);
            }

            // Ya era suya: idempotente y sin segundo aviso por correo. Dos pestañas o un doble clic no
            // pueden convertirse en dos correos que digan lo mismo.
            if ($ofTheKey !== null) {
                return SocialLoginResult::signedIn($locked, linked: false);
            }

            $existing = UserIdentity::query()
                ->where('user_id', $locked->getKey())
                ->where('provider', $profile->provider)
                ->first();

            if ($existing !== null) {
                Log::info('auth.social_refused', ['ip' => $ip, 'reason' => SocialLoginResult::REASON_PROVIDER_CONFLICT]);

                return SocialLoginResult::refused(SocialLoginResult::REASON_PROVIDER_CONFLICT);
            }

            UserIdentity::create([
                'user_id' => $locked->getKey(),
                'provider' => $profile->provider,
                'provider_id' => $profile->subject,
                'email_at_link' => $profile->email,
                'linked_via' => UserIdentity::VIA_ACCOUNT,
                'linked_at' => now(),
            ]);

            AuditLogger::log('identities.linked', $locked, [
                'provider' => $profile->provider,
                'via' => UserIdentity::VIA_ACCOUNT,
                'promoted' => false,
            ]);

            return SocialLoginResult::signedIn($locked, linked: true);
        });
    }

    /**
     * El aviso por correo de una vinculación, **fuera de la transacción y sin poder tumbarla**: el
     * vínculo ya es válido y un fallo del SMTP no puede deshacerlo.
     */
    public function announceLink(User $user, string $provider, bool $promoted = false): void
    {
        $this->notifySafely($user, new SocialIdentityLinked($provider, $promoted));
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
            // se avisa dos veces por correo. Lo mismo que hace el firmador del descargo con la firma
            // anterior.
            $alreadyLinked = $existing !== null;

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

            // ⚠️⚠️ **El vínculo se escribe DESPUÉS de expulsar, y el orden es la regla**: desde
            // `RGPD-06`, `revokeAllAccess()` **se lleva también las identidades** —para que un vínculo
            // plantado por quien te tomó la cuenta no sobreviva a la defensa—, así que crearlo antes
            // sería borrar la llave que acabamos de dar. Por eso la condición mira también `$promoted`:
            // en el caso raro de una cuenta ya vinculada Y sin verificar, la expulsión se llevó su fila
            // y hay que volver a escribirla.
            if (! $alreadyLinked || $promoted) {
                UserIdentity::create([
                    'user_id' => $locked->getKey(),
                    'provider' => $profile->provider,
                    'provider_id' => $profile->subject,
                    'email_at_link' => $profile->email,
                    'linked_via' => $via,
                    'linked_at' => now(),
                ]);
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
