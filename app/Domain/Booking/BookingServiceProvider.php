<?php

namespace App\Domain\Booking;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Services\AvailabilityReader;
use App\Domain\Booking\Services\CartPricer;
use App\Domain\Booking\Services\CatalogReader;
use App\Domain\Booking\Services\CheckoutOrchestrator;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\PublishableCatalogReader;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Booking\Services\ZonePaletteReader;
use Illuminate\Support\ServiceProvider;

/**
 * Módulo **Booking** (catálogo y reservas): el más referenciado del sistema y, por eso, el
 * ÚLTIMO en mudarse (paso 6 de `docs/specs/modulos-dominio.md`).
 *
 * En el paso 1 solo publica los bindings de sus contratos hacia las implementaciones que
 * aún viven en `app/Support`. Content e Identity ya hablan con los contratos, así que la
 * mudanza del paso 6 no tocará a sus consumidores.
 */
class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerReservations::class, CustomerReservationsReader::class);
        $this->app->bind(PublishableCatalog::class, PublishableCatalogReader::class);
        // Catálogo de venta (Fase 3 · paso 1b): lo consumen la web (`Tickets\Purchase`) y la API.
        $this->app->bind(ProductCatalog::class, CatalogReader::class);
        // Política de admisión de reservas (Fase 3 · paso 2): pausa, tope de pendientes y
        // frecuencia. La aplican el sidebar, «Mis pedidos» y, en el paso 4, `POST /orders`.
        $this->app->bind(ReservationAdmission::class, ReservationAdmissionPolicy::class);
        // Tarificación de cesta (Fase 3 · paso 4a): la consumen el sidebar de la web y
        // `POST /orders/quote`. Es el espejo declarado de lo que `OrderCreator` cobrará.
        $this->app->bind(CartPricing::class, CartPricer::class);
        // Disponibilidad ofrecida (Fase 3 · paso 4b): sobre `SlotOffer` (`AFORO-02`), con la cesta
        // delante. La consumen el sidebar de la web y `availability/{product}/*`.
        $this->app->bind(AvailabilityOffer::class, AvailabilityReader::class);
        $this->app->bind(ZonePalette::class, ZonePaletteReader::class);
        // `OperatingSchedule` memoiza horarios, temporadas y excepciones: se comparte por
        // petición para no repetir esas lecturas entre la landing, el SEO y el hero.
        $this->app->bind(OperatingCalendar::class, OperatingSchedule::class);
        // La SECUENCIA de la compra (cierre de Fase 3): admitir → crear → abrir cobro. La consumen
        // las cuatro superficies de entrega, que antes la escribían a mano cada una.
        //
        // ⚠️ El otro puerto del checkout, `Booking\Contracts\PaymentInitiation` (la ida del pago),
        // se ata en `PaymentsServiceProvider`: el contrato es de Booking pero lo implementa Payments,
        // y la atadura vive donde vive la implementación.
        $this->app->bind(ReservationCheckout::class, CheckoutOrchestrator::class);
    }
}
