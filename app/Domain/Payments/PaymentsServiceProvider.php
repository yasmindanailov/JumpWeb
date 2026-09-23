<?php

namespace App\Domain\Payments;

use App\Domain\Booking\Contracts\PaymentInitiation;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Observers\PaymentAnalyticsObserver;
use App\Domain\Payments\Observers\PaymentRefundAnalyticsObserver;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Domain\Payments\Services\Redsys;
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

        // La IDA del pago (cierre de Fase 3). El contrato es de **Booking** —un puerto REQUERIDO:
        // lo que Booking necesita de una pasarela— y la implementación es de Payments, así que la
        // atadura vive aquí, donde vive la implementación, igual que la del reembolso.
        //
        // ⚠️ Por nombre de clase, nunca por closure, y por el mismo motivo que arriba: los tests que
        // simulan una pasarela caída sustituyen `PaymentInitiator::class` en el contenedor. Con un
        // `fn () => new PaymentInitiator(...)` esa sustitución dejaría de aplicarse y los dos tests
        // de la compensación asimétrica seguirían VERDES sin probar nada.
        $this->app->bind(PaymentInitiation::class, PaymentInitiator::class);
    }

    public function boot(): void
    {
        // El libro de eventos (`specs/analitica.md` §4.1, `#678`): el rechazo del banco y la devolución
        // NO son transiciones del pedido —viven en `Payment` y en `PaymentRefund`—, así que se
        // escuchan aquí, sin tocar `RedsysReturnHandler` (`CRITICAL_RE`).
        // ⚠️ Singleton: el dispatcher instancia `Clase@método` en cada evento, y la transición capturada en
        // `saving` tiene que llegar al `saved` de la MISMA instancia (ver `BookingServiceProvider`).
        $this->app->singleton(PaymentAnalyticsObserver::class);
        $this->app->singleton(PaymentRefundAnalyticsObserver::class);
        Payment::observe(PaymentAnalyticsObserver::class);
        PaymentRefund::observe(PaymentRefundAnalyticsObserver::class);
    }
}
