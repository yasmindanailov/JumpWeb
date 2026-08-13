<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Services\PasswordLogin;
use App\Http\Sidebar\SidebarEntry;
use App\Livewire\Concerns\ResetsOnModalClose;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.3 — Inicio de sesión (modal). Seguridad "Reforzado":
 * rate limiting + bloqueo temporal por email+IP, mensaje genérico (no revela si
 * el email existe), regeneración de sesión al entrar y registro de `last_login_at`.
 */
class Login extends Component
{
    use ResetsOnModalClose;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /** Embebido en el sidebar de compra (#69): al entrar avisa con un evento en vez de redirigir. */
    public bool $embedded = false;

    public function login(PasswordLogin $passwordLogin)
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Los limitadores de `SEC-06`, la comprobación de credenciales y el sello de última entrada
        // viven en `Identity\Services\PasswordLogin` desde Fase 3 · paso 3b: son dominio, y la API
        // de este mismo paso los consume sin reescribirlos. Aquí queda lo que es de ESTA interfaz.
        $result = $passwordLogin->attempt($this->email, $this->password, $this->remember, (string) request()->ip());

        if ($result->failed()) {
            // Dos mensajes distintos y en claves distintas (L-02, auditoría 2026-05-26): el del
            // limitador va a `_global` para que no se mezcle bajo el input con «credenciales
            // incorrectas», que es genérico a propósito (no revela si el email existe).
            throw ValidationException::withMessages($result->wasRateLimited()
                ? ['_global' => __('auth.throttle', ['seconds' => $result->retryAfter])]
                : ['email' => __('auth.failed')]);
        }

        // Anti-cesta-cruzada (auditoría 2026-05-26, hallazgo D): si la sesión ya tenía un
        // marcador `purchase.user_id` distinto del usuario que acaba de iniciar sesión, la
        // cesta pertenece a OTRO cliente — la descartamos antes de continuar para que Bob no
        // herede la cesta de Alice en un dispositivo compartido. Hacemos esto ANTES del
        // regenerate para no perder el marcador. Es estado de la sesión WEB: no viaja al
        // servicio, porque un cliente de API no tiene cesta en sesión.
        $previousCartOwner = session('purchase.user_id');
        $newId = $result->user->getKey();
        if ($previousCartOwner !== null && (int) $previousCartOwner !== (int) $newId) {
            session()->forget('purchase.cart');
            // Y el desenlace de pago pendiente, sea cual sea. Antes solo se descartaba el de
            // «confirmado»: a Bob podía aparecerle el «pago denegado» de Alice, que es la misma
            // fuga por el otro lado. `SidebarEntry` los descarta los tres (Fase 4 · paso 4.0a).
            SidebarEntry::clear();
        }
        session(['purchase.user_id' => $newId]);

        Session::regenerate();

        // Embebido en la compra: no redirige; avisa al sidebar para que continúe la reserva.
        if ($this->embedded) {
            $this->dispatch('logged-in');

            return null;
        }

        return $this->redirectIntended('/');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
