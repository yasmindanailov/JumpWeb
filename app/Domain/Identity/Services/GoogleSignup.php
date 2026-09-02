<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SocialLoginResult;
use App\Domain\Identity\Contracts\SocialProfile;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * **El alta que nace de una identidad de Google** (`docs/specs/auth-con-google.md` §5.3, tanda T2).
 *
 * Hermano de {@see SelfSignup} y deliberadamente distinto: aquél se defiende de bots con cuatro capas
 * —honeypot, dos limitadores y anti-bot— porque cualquiera puede teclear un correo; aquí, **quien
 * llega ya pasó por Google**, así que no hay nada que fingir. Lo que sí comparte es lo que escribe:
 * cuenta, rol, el sello de privacidad y **su fila de `consents`**, para que un cliente de Google no
 * salga distinto en el panel ni en el export.
 *
 * ## Las tres cosas que lo hacen seguro
 *
 * 1. ⚠️⚠️ **El perfil NO viene del formulario.** Llega de la sesión del servidor, donde lo dejó el
 *    retorno de Google. Si el `sub` o el correo viajaran en campos, cualquiera crearía una cuenta con
 *    la identidad verificada de otro — y a esa cuenta se le firma un descargo probatorio.
 * 2. ⚠️⚠️ **Vuelve a RESOLVER la identidad antes de crear nada**, con el mismo {@see SocialLogin} que
 *    usa el retorno: entre que se pintó la pantalla y se envió, alguien pudo registrar ese correo o
 *    vincular ese `sub`. Si ahora resuelve, se ENTRA; solo se crea cuando sigue sin haber cuenta.
 *    Así la carrera no la resuelve una excepción de unicidad, sino el camino que ya está probado.
 * 3. **Una transacción**, con la firma del descargo dentro. `email_verified_at` se escribe aquí
 *    (`[DECIDIDO owner]` Q1) y **es lo que permite firmar en el acto**: `WaiverSigner` lee la COLUMNA
 *    de la fila bloqueada, no lo que Google diga.
 */
final class GoogleSignup
{
    public function __construct(
        private readonly SocialLogin $login,
        private readonly WaiverSigner $signer,
    ) {}

    /**
     * @param  array{name: string}  $data  lo que la pantalla añade, ya validado
     * @param  LegalDocumentVersion|null  $waiver  el texto que la pantalla SIRVIÓ, o `null` si en esta
     *                                             instalación no se firma (modo externo o sin versión)
     *
     * @throws WaiverDocumentStaleException el texto del descargo se republicó mientras la persona
     *                                      rellenaba: la pantalla se re-pinta con el nuevo
     */
    public function register(
        SocialProfile $profile,
        array $data,
        string $ip,
        ?string $userAgent,
        ?LegalDocumentVersion $waiver,
    ): SocialLoginResult {
        // La carrera, resuelta por el camino de siempre (ver el punto 2 de la cabecera).
        $resolved = $this->login->enter($profile, $ip, UserIdentity::VIA_SIGNUP);

        if (! $resolved->isPendingRegistration()) {
            return $resolved;
        }

        $user = DB::transaction(function () use ($profile, $data, $ip): User {
            $now = now();

            $user = User::create([
                'name' => $data['name'],
                'email' => $profile->email,
                // ⚠️ **Sin teléfono, desde la T8·c** (`[DECIDIDO owner, 2026-09-02]`): lo pide el
                // checkout, que es donde hace falta. La cuenta nace sin él —un estado que el producto
                // ya admite por la puerta del alta de mostrador— y `CheckoutDuties` lo reclama antes
                // de crear el primer pedido.
                // Contraseña ALEATORIA e inservible, como el alta de mostrador: nadie la conoce y
                // nadie puede entrar con ella. Quien quiera una la pide con «he olvidado mi
                // contraseña», que es lo que hace que esta cuenta no dependa de Google para siempre.
                'password' => Str::random(60),
                'locale' => app()->getLocale(),
                // ⚠️ **El marketing NO se pide aquí** (`[DECIDIDO owner]` Q9): el art. 7.4 prohíbe
                // empaquetarlo con lo demás. Se ofrece en «Mi cuenta → Privacidad», con su
                // interruptor (T3) — y desde la T8·c tampoco lo pide el alta con contraseña.
                'marketing_opt_in' => false,
                'privacy_accepted_at' => $now,
            ]);

            // ⚠️ `email_verified_at` NO es `fillable` a propósito, así que se escribe con `forceFill`
            // igual que hace el alta de mostrador. Y es una DECISIÓN, no un detalle (`[DECIDIDO
            // owner]` Q1): sostiene la recuperación de contraseña, y sin ella la transacción no podría
            // firmar el descargo — `WaiverSigner` lee esta columna, no lo que Google afirme.
            $user->forceFill(['email_verified_at' => $now])->save();

            if ($role = Role::where('name', 'customer')->first()) {
                $user->roles()->attach($role);
            }

            // La PRUEBA del art. 5.2, igual que en el alta con contraseña. La privacidad deja de ser
            // casilla (§7.1: el RGPD pide INFORMAR, no que se acepte) pero **el rastro se conserva**:
            // fecha, versión e IP. Lo que desaparece es la casilla, no la constancia.
            //
            // ⚠️⚠️ **Y las condiciones NO dejan fila, desde la T8·c.** Escribirla con la versión de
            // `Consent::CURRENT_VERSION` —una fecha, no un `vN·xx`— haría que la regla de gracia de
            // {@see TermsAcceptance::statusFor()} diera por aceptada la v1 a una cuenta que no ha
            // aceptado nada, y el checkout no le pediría las condiciones nunca.
            $user->consents()->create([
                'type' => Consent::TYPE_PRIVACY,
                'accepted_at' => $now,
                'ip' => $ip,
                'version' => Consent::CURRENT_VERSION,
            ]);

            UserIdentity::create([
                'user_id' => $user->getKey(),
                'provider' => $profile->provider,
                'provider_id' => $profile->subject,
                'email_at_link' => $profile->email,
                'linked_via' => UserIdentity::VIA_SIGNUP,
                'linked_at' => $now,
            ]);

            return $user;
        });

        // ⚠️⚠️ **La firma va FUERA de la transacción de arriba y eso es deliberado.** `WaiverSigner`
        // abre la suya y bloquea la fila del titular: anidarla dentro de la que acaba de crear esa
        // misma fila funciona (son savepoints), pero deja el lock de escritura tomado durante todo el
        // alta. Aquí no hay nada que revertir si la firma fallara —la cuenta es válida y el aviso de
        // «te falta firmar» ya existe en su cuenta—, y a cambio el punto de serialización de las
        // cadenas sigue siendo solo el del firmador.
        if ($waiver !== null) {
            $this->signer->sign($user, $waiver, WaiverSignatureRequest::web($ip, $userAgent));
        }

        // Entrar es efecto del llamante en el resto del sistema, pero aquí el alta ES la entrada: la
        // persona acaba de demostrar su identidad ante Google y no tiene contraseña que teclear.
        // Misma decisión que `#331` tomó para el alta suelta, y por el mismo motivo: sin sesión no hay
        // QR, y el QR es lo que la identifica en la puerta del parque.
        Auth::login($user);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        Log::info('auth.registered_with_provider', [
            'user_id' => $user->id,
            'ip' => $ip,
            'provider' => $profile->provider,
        ]);

        return SocialLoginResult::signedIn($user, linked: true);
    }
}
