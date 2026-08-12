<?php

namespace App\Livewire\Site;

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bloque de cuenta del sidebar de compra (#221). Es un componente Livewire (no un componente
 * Blade estático) por un motivo concreto: el login/registro EMBEBIDO en el sidebar (#69) NO recarga
 * la página, así que un saludo renderizado en el servidor para un invitado se quedaba obsoleto
 * («Hola, saltador/a» tras iniciar sesión). Como Livewire, escucha el evento `logged-in` (que disparan
 * `Auth\Login` y `Auth\Register` embebidos) y se RE-RENDERIZA en vivo con la sesión nueva, sin recarga
 * — imprescindible para el flujo pay-first (recargar perdería el paso de pago del sidebar).
 *
 * Los datos los provee `App\Domain\Identity\Services\CustomerAccountContext` (memoizado, defensivo); la vista los lee.
 */
class AccountContext extends Component
{
    /** El login/registro embebido avisa con `logged-in`; re-renderizamos con la sesión ya iniciada. */
    #[On('logged-in')]
    public function refresh(): void
    {
        // Cuerpo vacío a propósito: recibir el evento basta para que Livewire vuelva a render() con
        // la sesión nueva (el saludo pasa de invitado a «Hola, <nombre>»).
    }

    public function render()
    {
        return view('livewire.site.account-context');
    }
}
