<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
 */
class DeleteAccount extends Component
{
    public string $current_password = '';

    public function destroy()
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $userId = $user->id;

        // `anonymize()` es atómico y, desde la auditoría Fase 1 (A1/A2/A10), purga TODA la PII (incl.
        // la de invitados en order_items), las sesiones activas del titular y su token de reset. Aquí
        // solo cerramos e invalidamos la sesión del REQUEST ACTUAL.
        $user->anonymize();

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        Log::info('account.anonymized', ['user_id' => $userId]);

        return redirect('/')->with('status', 'account-deleted');
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
            'current_password' => __('account.account.privacy.delete_password'),
        ];
    }

    public function render()
    {
        return view('livewire.account.delete-account');
    }
}
