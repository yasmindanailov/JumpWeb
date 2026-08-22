<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.5b — RGPD: eliminar mi cuenta (derecho de supresión). Acción sensible: exige
 * reconfirmar la contraseña.
 *
 * Auditoría 2026-05-26 (hallazgo C): la cuenta se ANONIMIZA en lugar de hard-deletearse.
 * Conservar la fila `users` es obligatorio porque la FK `orders.user_id` ahora es RESTRICT
 * y necesitamos mantener vinculadas las facturas (AEAT, conservación ≥4 años). Los datos
 * personales se sobrescriben con valores neutros (email anónimo único, password aleatorio,
 * sin teléfono ni nombre), los consents se borran y los roles se desvinculan — el efecto
 * práctico es idéntico para el cliente (login imposible, datos personales fuera). El email
 * original queda libre para que otro pueda re-registrarse con él. Ver {@see User::anonymize()}.
 *
 * ⚠️ **La decisión y la purga viven en `Identity\Services\AccountPrivacy` desde la tanda 2 · paso
 * 8**, para que `DELETE /api/v1/me` no las reescriba. Aquí queda lo de ESTA interfaz: cerrar la
 * sesión del navegador y decidir a dónde va el cliente.
 * ⚠️⚠️ **Y con eso la web heredó el limitador que no tenía**: era el último de los cuatro sitios
 * que reconfirman contraseña sin techo (`DECISIONES #120(o)`) — y el más grave, porque lo que
 * protegía era la única acción irreversible del producto.
 */
class DeleteAccount extends Component
{
    public string $current_password = '';

    public function destroy(AccountPrivacy $privacy)
    {
        // ⚠️ `current_password` ya NO lleva la regla del framework: comprobarla es del servicio, que
        // además **cuenta el intento**. Dejarla aquí la comprobaría dos veces y el limitador no
        // llegaría a ver los fallos (`specs/area-cliente.md` §9.4).
        $this->validate(['current_password' => ['required', 'string']]);

        /** @var User $user */
        $user = Auth::user();

        $result = $privacy->anonymize($user, $this->current_password, (string) request()->ip());

        if ($result->failed()) {
            // Dos claves distintas, como en las otras tres gestiones: el aviso del limitador va a
            // `_global` para que no se lea como «esta contraseña está mal» debajo del input.
            throw ValidationException::withMessages($result->wasRateLimited()
                ? ['_global' => __('auth.throttle', ['seconds' => $result->retryAfter])]
                : ['current_password' => __('account.account.wrong_password')]);
        }

        // `anonymize()` ya revocó TODAS las credenciales del titular (`RGPD-06`), incluida la fila
        // de esta sesión. Lo que queda es la sesión en curso, que vive en memoria durante esta
        // petición: sin invalidarla, el cliente terminaría de navegar como un usuario que ya no
        // existe.
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        // La clave del aviso vive en el servicio: la usan esta pantalla y `DELETE /api/v1/me`, que
        // la deja en la sesión nueva para que el cajón la encuentre al salir a la home.
        return redirect('/')->with('status', AccountPrivacy::FAREWELL_STATUS);
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'current_password' => __('account.account.privacy.delete_password'),
        ];
    }

    public function render()
    {
        return view('livewire.account.delete-account');
    }
}
