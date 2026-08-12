<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Sesiones en BD: borra explícitamente las demás sesiones de este usuario.
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', session()->getId())
                ->delete();
        }

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
