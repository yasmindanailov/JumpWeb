<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Middleware\SetLocale;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function save()
    {
        /** @var User $user */
        $user = Auth::user();
        $this->email = Str::lower(trim($this->email));
        $emailChanged = $this->email !== $user->email;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
            // `unique` mira tanto `email` (ya en uso) como `pending_email` de OTROS usuarios
            // (alguien lo está reclamando). El ignore() permite re-pedir el propio pending.
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('users', 'pending_email')->ignore($user->id),
            ],
        ];
        if ($emailChanged) {
            // Reconfirmar contraseña antes de pedir el cambio (acción sensible).
            $rules['current_password'] = ['required', 'current_password'];
        }
        $this->validate($rules);

        // Actualización de campos NO sensibles (siempre se aplican).
        $user->name = $this->name;
        $user->phone = $this->phone;
        $user->locale = $this->locale;

        // Aplica el idioma elegido a la sesión en curso (independiente del cambio de email).
        session(['locale' => $this->locale]);

        if ($emailChanged) {
            // Patrón pending_email: NO sobrescribimos el email actual. Guardamos el solicitado
            // y mandamos el enlace de confirmación al NUEVO buzón + aviso al viejo. La UNIQUE
            // de pending_email puede chocar si otro pidió el mismo email en paralelo (race
            // contra el Rule::unique anterior) → la BD bloquea, mostramos error específico.
            $user->pending_email = $this->email;
            $user->pending_email_sent_at = now();

            try {
                $user->save();
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw ValidationException::withMessages([
                        'email' => __('validation.unique', ['attribute' => __('account.account.profile.email')]),
                    ]);
                }
                throw $e;
            }

            $user->notify(new VerifyPendingEmail);
            // Aviso al EMAIL VIEJO. `notify` usa `email` (el actual, intacto) por defecto.
            // Enmascaramos el nuevo para no exponer un email completo en un correo cruzado.
            $user->notify(new EmailChangeRequested(self::maskEmail($this->email)));
            Log::info('account.email_change_requested', ['user_id' => $user->id]);

            return redirect()->route('account')->with('status', 'email-change-requested');
        }

        try {
            $user->save();
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'email' => __('validation.unique', ['attribute' => __('account.account.profile.email')]),
                ]);
            }
            throw $e;
        }

        Log::info('account.profile_updated', ['user_id' => $user->id]);

        return redirect()->route('account')->with('status', 'profile-updated');
    }

    /** ¿La excepción de BD es por violación de UNIQUE? (MySQL 1062, SQLite UNIQUE constraint failed) */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains((string) $e->getMessage(), 'UNIQUE constraint failed');
    }

    /** Enmascara un email para mostrarlo en notificaciones (`ana@example.com` → `a***@example.com`). */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return $email;
        }

        return $parts[0][0].str_repeat('*', max(1, mb_strlen($parts[0]) - 1)).'@'.$parts[1];
    }

    public function cancelEmailChange()
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user->pending_email) {
            $user->forceFill(['pending_email' => null, 'pending_email_sent_at' => null])->save();
            Log::info('account.email_change_cancelled', ['user_id' => $user->id]);
        }

        return redirect()->route('account')->with('status', 'email-change-cancelled');
    }

    /**
     * Reenvía el enlace de confirmación al `pending_email`, sin sobrescribir el envío inicial:
     * solo reenvía la notificación. Cooldown server-side por usuario (1/min) para evitar abuso
     * del buzón ajeno. Refresca `pending_email_sent_at` para extender la ventana de caducidad
     * desde este momento (UX consistente con "el enlace que reenvío caduca en 60 min").
     */
    public function resendPendingEmail(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->pending_email) {
            return;
        }

        $key = 'pending-email-resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('current_password', __('account.account.profile.email_resend_throttle', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }
        RateLimiter::hit($key, 60);

        $user->forceFill(['pending_email_sent_at' => now()])->save();
        $user->notify(new VerifyPendingEmail);
        Log::info('account.email_change_resent', ['user_id' => $user->id]);

        session()->flash('status', 'email-change-resent');
    }

    /** Minutos restantes hasta que caduque el enlace del pending. 0 si ya caducó o no hay. */
    public function pendingEmailMinutesLeft(): int
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->pending_email || ! $user->pending_email_sent_at) {
            return 0;
        }

        $expiresAt = $user->pending_email_sent_at->copy()->addMinutes(EmailChangeController::HOLD_MINUTES);
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
