<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

    public function confirm()
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        Auth::logoutOtherDevices($this->current_password);

        // Punto único de invalidación (Fase 3 · paso 3a): borra las demás filas de `sessions` y
        // revoca los tokens de API. «Cerrar sesión en los demás dispositivos» tiene que alcanzar
        // también a la app: para el titular, su móvil es otro dispositivo.
        $user->revokeOtherAccess();

        $this->reset('current_password');
        Log::info('account.logout_other_devices', ['user_id' => $user->id]);

        return redirect()->route('account')->with('status', 'logged-out-others');
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
            'current_password' => __('account.account.sessions.current_password'),
        ];
    }

    public function render()
    {
        return view('livewire.account.logout-other-devices');
    }
}
