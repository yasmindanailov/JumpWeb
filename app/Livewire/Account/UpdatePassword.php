<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Fase 4.5a — Cambiar contraseña. Acción sensible: exige la contraseña actual
 * (reconfirmación) y la nueva debe pasar el filtro anti-filtración (HIBP) y ser
 * confirmada (reglas 1 y 3 de docs/SEGURIDAD.md).
 *
 * Auditoría 2026-05-26 (hallazgo B): al cambiar la contraseña, todas las DEMÁS sesiones del
 * usuario se cierran (la actual sobrevive). Es el caso de uso real de "cambiar contraseña por
 * sospecha de robo de sesión" — si no se hace, el atacante seguiría dentro. `logoutOtherDevices`
 * rota el password hash en sesión y el remember_token; aquí, además, purgamos las filas en BD.
 */
class UpdatePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save()
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->uncompromised()],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['password' => $this->password]);

        // Invalidar las DEMÁS sesiones (la actual sobrevive). Rota el recaller + password hash
        // en sesión y, con driver=database, borramos también las filas para que el otro device
        // no se reactive por cache. Es el comportamiento esperado del usuario al cambiar password.
        Auth::logoutOtherDevices($this->password);
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', session()->getId())
                ->delete();
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        Log::info('account.password_updated', ['user_id' => $user->id]);

        return redirect()->route('account')->with('status', 'password-updated');
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
            'current_password' => __('account.account.password.current'),
            'password' => __('account.account.password.new'),
        ];
    }

    public function render()
    {
        return view('livewire.account.update-password');
    }
}
