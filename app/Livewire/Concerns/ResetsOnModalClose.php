<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\On;

/**
 * Patrón reutilizable para componentes que viven en un modal.
 *
 * Livewire conserva el estado del componente durante toda la vida de la página,
 * así que ocultar/mostrar el modal (Alpine) NO lo reinicia: persistirían datos y
 * mensajes de validación del uso anterior. Para evitarlo, al cerrar el modal el
 * almacén Alpine `auth` emite `auth-modal-closed` y aquí limpiamos TODO:
 *   - `reset()`          → vuelve las propiedades públicas a sus valores iniciales.
 *   - `resetValidation()`→ vacía el bag de errores (si no, el error se queda pegado).
 *
 * Cualquier componente de modal nuevo solo tiene que `use ResetsOnModalClose;`.
 * (Para datos sensibles ya mostrados, el cierre además recarga la página; ver `app.js`.)
 */
trait ResetsOnModalClose
{
    #[On('auth-modal-closed')]
    public function resetOnModalClose(): void
    {
        $this->reset();
        $this->resetValidation();
    }
}
