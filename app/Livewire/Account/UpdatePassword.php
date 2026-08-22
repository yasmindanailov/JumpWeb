<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use App\Domain\Identity\Services\PasswordPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    public function save(AccountCredentials $credentials)
    {
        // ⚠️ **El FORMATO se valida antes de tocar el limitador, y ese orden importa**: una contraseña
        // nueva que no cumple la política no es un intento de adivinar la actual, así que no debe
        // gastar uno de los cinco de `AccountCredentials::MAX_ATTEMPTS`.
        //
        // ⚠️ Y `current_password` ya NO lleva la regla del framework: comprobar la contraseña es lo
        // que hace el servicio, que además **cuenta el intento**. Dejarla aquí la comprobaría dos
        // veces y dejaría el limitador sin ver los fallos (`specs/area-cliente.md` §9).
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => [...PasswordPolicy::rules(), 'confirmed'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        // El cambio, el cierre de las DEMÁS credenciales por sus dos vías y el rastro en el log viven
        // en `Identity\Services\AccountCredentials` desde la tanda 2: son dominio, y la API los
        // consume sin reescribirlos. Aquí queda lo que es de ESTA interfaz.
        $result = $credentials->changePassword($user, $this->current_password, $this->password, (string) request()->ip());

        if ($result->failed()) {
            // Dos claves distintas, como en el login (L-02): el aviso del limitador va a `_global`
            // para que no se lea como «esta contraseña está mal» debajo del input.
            throw ValidationException::withMessages($result->wasRateLimited()
                ? ['_global' => __('auth.throttle', ['seconds' => $result->retryAfter])]
                : ['current_password' => __('account.account.wrong_password')]);
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);

        return redirect()->route('account')->with('status', 'password-updated');
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
            'current_password' => __('account.account.password.current'),
            'password' => __('account.account.password.new'),
        ];
    }

    public function render()
    {
        return view('livewire.account.update-password');
    }
}
