<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Contracts\ProfileUpdateResult;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.5a — Editar perfil (nombre, teléfono, idioma, email).
 *
 * Cambiar el email usa el patrón `pending_email` (auditoría 2026-05-26, hallazgo A):
 * el email actual NO se sobrescribe hasta que el cliente confirma el nuevo desde su buzón
 * (enlace firmado en {@see EmailChangeController}). Mientras tanto, su login y password reset
 * siguen usando el email viejo. Esto bloquea el clásico vector de takeover por sesión robada.
 *
 * Además se envía un aviso al EMAIL VIEJO ("alguien ha pedido cambiar tu email") para que la
 * víctima de un takeover detecte la intrusión a tiempo.
 *
 * Reconfirmación de contraseña obligatoria (regla 3 de SEGURIDAD). El idioma se aplica también
 * a la sesión actual.
 */
class UpdateProfile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $locale = '';

    public string $current_password = '';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->locale = $user->locale ?: app()->getLocale();
    }

    public function save(AccountProfile $profile)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->email = Str::lower(trim($this->email));
        $emailChanged = $this->email !== $user->email;

        $rules = AccountProfile::rules($user);

        // ⚠️ **La reconfirmación solo se pide si cambia el correo**, y solo se valida su PRESENCIA:
        // comprobarla es del servicio, que además cuenta el intento para el limitador. Dejar aquí la
        // regla `current_password` la comprobaría dos veces y dejaría al limitador sin ver los fallos.
        if ($emailChanged) {
            $rules['current_password'] = ['required', 'string'];
        }

        $this->validate($rules);

        // Todo lo demás —el patrón `pending_email`, las dos notificaciones, la carrera de UNIQUE y el
        // rastro en el log— vive en `Identity\Services\AccountProfile` desde la tanda 2, y la API lo
        // consume sin reescribirlo. Aquí queda lo que es de ESTA interfaz.
        $result = $profile->apply($user, [
            'name' => $this->name,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'email' => $this->email,
        ], $this->current_password, (string) request()->ip());

        if ($result->failed()) {
            throw ValidationException::withMessages(match (true) {
                $result->wasRateLimited() => ['_global' => __('auth.throttle', ['seconds' => $result->retryAfter])],
                $result->reason === ProfileUpdateResult::EMAIL_TAKEN => [
                    'email' => __('validation.unique', ['attribute' => __('account.account.profile.email')]),
                ],
                default => ['current_password' => __('account.account.wrong_password')],
            });
        }

        // El idioma elegido se aplica a la sesión EN CURSO. Es efecto de la sesión web y no del
        // dominio: un cliente de API no tiene sesión que reetiquetar.
        session(['locale' => $this->locale]);

        return redirect()->route('account')
            ->with('status', $result->emailChangeRequested ? 'email-change-requested' : 'profile-updated');
    }

    public function cancelEmailChange(AccountProfile $profile)
    {
        /** @var User $user */
        $profile->cancelEmailChange(Auth::user());

        return redirect()->route('account')->with('status', 'email-change-cancelled');
    }

    public function resendPendingEmail(AccountProfile $profile): void
    {
        /** @var User $user */
        $result = $profile->resendPendingEmail(Auth::user());

        if ($result->sent) {
            session()->flash('status', 'email-change-resent');

            return;
        }

        // ⚠️ «No había nada pendiente» **no se anuncia**: quien pulsa dos veces no ha hecho nada malo,
        // y una alarma por eso sería ruido. Solo el cooldown tiene algo que decir.
        if ($result->reason === ResendResult::THROTTLED) {
            $this->addError('current_password', __('account.account.profile.email_resend_throttle', [
                'seconds' => $result->retryAfter,
            ]));
        }
    }

    public function pendingEmailMinutesLeft(): int
    {
        /** @var User $user */
        $user = Auth::user();
        $expiresAt = AccountProfile::pendingEmailExpiresAt($user);

        if ($expiresAt === null) {
            return 0;
        }
        $minutes = now()->diffInMinutes($expiresAt, false);

        return max(0, (int) $minutes);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'current_password.current_password' => __('account.account.wrong_password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('account.account.profile.name'),
            'email' => __('account.account.profile.email'),
            'phone' => __('account.account.profile.phone'),
            'locale' => __('account.account.profile.locale'),
            'current_password' => __('account.account.profile.current_password'),
        ];
    }

    public function render()
    {
        return view('livewire.account.update-profile');
    }
}
