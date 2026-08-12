<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\RefundGateway;
use App\Support\Redsys;
use Illuminate\Support\ServiceProvider;

/**
 * Módulo **Payments**: cobro y devolución de dinero (Fase 2 — `docs/specs/modulos-dominio.md`).
 *
 * En el paso 1 solo publica el binding del contrato a su implementación LEGACY: las clases
 * (`Redsys`, `RedsysReturnHandler`, `PaymentRefund`…) siguen en `app/Support` y `app/Models`
 * hasta el paso 5. Cuando muden, aquí solo cambia el `use` — los consumidores de Booking ya
 * hablan con el contrato y no se enteran. Ese es todo el propósito de crear los contratos
 * primero (§2 del spec: «el núcleo de dinero se mueve EL ÚLTIMO contra contratos ya estables»).
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `bind` con nombre de clase (no una instancia): el contenedor sigue construyendo
        // `Redsys` con `make()`, así que los tests que lo sustituyen con
        // `app()->instance(Redsys::class, …)` o `mock(Redsys::class)` siguen funcionando.
        $this->app->bind(RefundGateway::class, Redsys::class);
    }
}
