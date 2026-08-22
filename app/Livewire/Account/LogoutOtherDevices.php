<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.5a — "Cerrar sesión en los demás dispositivos" (regla 3 de docs/SEGURIDAD.md).
 * Exige reconfirmar la contraseña. Usa el mecanismo del framework (recaller + hash de
 * contraseña en sesión) y, con sesiones en BD, elimina además las demás filas de
 * `sessions` del usuario, conservando la sesión actual.
 */
class LogoutOtherDevices extends Component
{
    public string $current_password = '';

    public function confirm(AccountCredentials $credentials)
    {
        // ⚠️ `current_password` sin la regla del framework: comprobar la contraseña es del servicio,
        // que además **cuenta el intento** para el limitador (`specs/area-cliente.md` §9.4).
        $this->validate(['current_password' => ['required', 'string']]);

        /** @var User $user */
        $user = Auth::user();

        $result = $credentials->revokeOtherSessions($user, $this->current_password, (string) request()->ip());

        if ($result->failed()) {
            throw ValidationException::withMessages($result->wasRateLimited()
                ? ['_global' => __('auth.throttle', ['seconds' => $result->retryAfter])]
                : ['current_password' => __('account.account.wrong_password')]);
        }

        $this->reset('current_password');

        return redirect()->route('account')->with('status', 'logged-out-others');
    }

    protected function messages(): array
    {
        return [
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'current_password' => __('account.account.sessions.current_password'),
        ];
    }

    public function render()
    {
        return view('livewire.account.logout-other-devices');
    }
}
