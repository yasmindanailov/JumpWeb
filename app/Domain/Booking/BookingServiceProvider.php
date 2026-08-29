<?php

namespace App\Domain\Booking;

use App\Domain\Booking\Contracts\AddonOffer;
use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CartLineValidation;
use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Contracts\CustomerOrderHistory;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Services\AddonOfferReader;
use App\Domain\Booking\Services\AvailabilityReader;
use App\Domain\Booking\Services\CartLineValidator;
use App\Domain\Booking\Services\CartPricer;
use App\Domain\Booking\Services\CatalogReader;
use App\Domain\Booking\Services\CheckoutLinesReader;
use App\Domain\Booking\Services\CheckoutOrchestrator;
use App\Domain\Booking\Services\CustomerOrderHistoryReader;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\GateReservationsReader;
use App\Domain\Booking\Services\GuestAgeMixReader;
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
        // Lo que Booking guarda de un cliente en forma PORTABLE (RGPD art. 20, tanda 2 · paso 8).
        // Lo consume `Identity\Services\AccountPrivacy`, que compone el documento entero: sin este
        // contrato, Identity tendría que recorrer `Order`/`OrderItem`/`Slot`/`TicketType` a mano.
        $this->app->bind(CustomerOrderHistory::class, CustomerOrderHistoryReader::class);
        // Las líneas principales de un pedido recién creado, en el orden de la cesta (Fase 6 · menores
        // a cargo, tanda 4). Lo consume `Identity\Services\DependentAssigner` para atar cada asignación
        // a SU ítem sin importar `OrderItem`: la promesa del orden es de Booking, y aquí se cumple.
        $this->app->bind(CheckoutLines::class, CheckoutLinesReader::class);
        // Fase 6 · subsistema A: la ficha de puerta (Identity) pide las reservas y su dinero por aquí.
        $this->app->bind(GateReservations::class, GateReservationsReader::class);
        $this->app->bind(PublishableCatalog::class, PublishableCatalogReader::class);
        // Catálogo de venta (Fase 3 · paso 1b): lo consume la API, y por ella la web y el móvil.
        $this->app->bind(ProductCatalog::class, CatalogReader::class);
        // Política de admisión de reservas (Fase 3 · paso 2): pausa, tope de pendientes y
        // frecuencia. La aplican el sidebar, «Mis pedidos» y, en el paso 4, `POST /orders`.
        $this->app->bind(ReservationAdmission::class, ReservationAdmissionPolicy::class);
        // Tarificación de cesta (Fase 3 · paso 4a): la consumen el sidebar de la web y
        // `POST /orders/quote`. Es el espejo declarado de lo que `OrderCreator` cobrará.
        $this->app->bind(CartPricing::class, CartPricer::class);
        // Si una línea puede entrar en la cesta (Fase 4 · paso 4.0b·6): producto, franja ofrecida,
        // cantidad, tope de líneas y campos obligatorios del pack — más con qué cantidad entraría y
        // si se funde con otra. La consumen el sidebar de la web y `POST cart/validate-line`, que
        // existe porque la cesta de la SPA vive en el navegador y al añadir no hay ida y vuelta.
        $this->app->bind(CartLineValidation::class, CartLineValidator::class);
        // Disponibilidad ofrecida (Fase 3 · paso 4b): sobre `SlotOffer` (`AFORO-02`), con la cesta
        // delante. La consumen el sidebar de la web y `availability/{product}/*`.
        $this->app->bind(AvailabilityOffer::class, AvailabilityReader::class);
        // Complementos RESUELTOS contra la selección del cliente (Fase 4 · paso 4.0b·5): la
        // partición en grupos, las notas, las unidades gratis, los topes y la poda en cadena de las
        // dependencias. No inventa reglas —las pide a `AddonResolver`, la misma autoridad que aplica
        // `OrderCreator`—: lo que añade es publicarlas para un cliente que no puede deducirlas.
        $this->app->bind(AddonOffer::class, AddonOfferReader::class);
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

        // El veredicto de fiesta MIXTA (`specs/cumple-mixto.md` §9·4) memoiza la familia del pack y
        // los precios del día: la ficha de un pedido lo pregunta por varias reservas seguidas y
        // todas comparten catálogo.
        //
        // ⚠️ **`scoped` y no `bind`**, que es lo que usa el resto de este fichero: un `bind`
        // construye una instancia NUEVA en cada resolución, así que la memoria nacería vacía cada
        // vez — un memo que nunca acierta. Sin contrato propio porque no cruza módulos: lo consumen
        // el panel y la web, los dos dentro de Booking.
        $this->app->scoped(GuestAgeMixReader::class);
    }
}
