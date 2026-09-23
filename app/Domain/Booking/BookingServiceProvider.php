<?php

namespace App\Domain\Booking;

use App\Domain\Booking\Contracts\AddonOffer;
use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CartLineValidation;
use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Contracts\CustomerOrderHistory;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Observers\OrderAnalyticsObserver;
use App\Domain\Booking\Services\AddonOfferReader;
use App\Domain\Booking\Services\AuthorizableReservationsReader;
use App\Domain\Booking\Services\AvailabilityReader;
use App\Domain\Booking\Services\CartLineValidator;
use App\Domain\Booking\Services\CartPricer;
use App\Domain\Booking\Services\CatalogReader;
use App\Domain\Booking\Services\CheckoutLinesReader;
use App\Domain\Booking\Services\CheckoutOrchestrator;
use App\Domain\Booking\Services\CustomerOrderHistoryReader;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\GateReservationsReader;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\PartyGuestsReader;
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
        // Fase 6 · el JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §13): la
        // RESERVA vista por quien tiene su enlace y NO tiene cuenta. Lo consume
        // `Identity\Services\GuardianAuthorizationSigner` para decidir si la autorización cabe.
        // ⚠️ Colgaba del PEDIDO hasta `#401` y lo cazó el owner con datos reales: un pedido con dos
        // visitas hacía que la hoja del padre dijera dos fechas y ofreciera las plazas de las dos.
        // ⚠️ No devuelve NADA que ese desconocido no pueda ver: ni importes, ni nombres de nadie.
        $this->app->bind(AuthorizableReservations::class, AuthorizableReservationsReader::class);
        // Fase 6 · subsistema A: la ficha de puerta (Identity) pide las reservas y su dinero por aquí.
        $this->app->bind(GateReservations::class, GateReservationsReader::class);
        // Fase 6 · la INVITACIÓN DIGITAL (`specs/celebracion-e-invitacion.md` §4.5·8, `#576`): los
        // niños que han CONFIRMADO. Lo consumen `Identity\Services\GuardianPlaces` —que resta los que
        // ya tienen justificante atado, porque es el único sitio donde las dos mitades coexisten— y el
        // firmador, para saber si una firma viene atada a un «sí» y no descontar plaza por ella.
        // ⚠️ Devuelve IDS y no una cifra: restar aquí obligaría a Booking a mirar las firmas de
        // Identity, que es justo la flecha que el grafo prohíbe.
        $this->app->bind(PartyGuests::class, PartyGuestsReader::class);
        // ⚠️ Aquí estaba `PublishableCatalog`, el contrato con el que Content preguntaba si el
        // complemento vinculado a una atracción era comprable. Se retira en `#668` (F5 · T3) con
        // la pieza entera: `#632`·P3 lo decidió con la medida delante —**0 de 23 atracciones** lo
        // usaban— y las restricciones que importan viven en Normas.
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

        // El veredicto de fiesta MIXTA (`specs/cumple-mixto.md` §21.5) deriva del SELLO que viaja en
        // la fila de cada reserva, así que el lector es sin estado y se resuelve solo. Hasta el
        // 2026-08-31 iba en `scoped` porque memoizaba la familia y los precios del catálogo — y ese
        // memo era la trampa de §17.6·2: dentro de un test un cambio de catálogo no se veía y las
        // guardas nacían ciegas sin `forgetScopedInstances()`. Con el sello no hay nada que memoizar.
    }

    public function boot(): void
    {
        // El libro de eventos (`specs/analitica.md` §4.1, `#678`): el sello de origen en `creating` y los
        // hechos de un pedido desde sus transiciones. Se escucha al MODELO porque `OrderCreator`,
        // `CheckoutOrchestrator` y `RedsysReturnHandler` son `CRITICAL_RE` y no se tocan.
        //
        // ⚠️⚠️ **Singleton, y no es un detalle**: el dispatcher resuelve `Clase@método` del contenedor EN
        // CADA evento, así que la transición que `saving` captura en una instancia la leería un `saved` de
        // OTRA instancia, vacía — medido en tinker: `order_created` llegaba y `order_cancelled` no. Con una
        // sola instancia, el `de → a` sobrevive del evento síncrono al siguiente.
        $this->app->singleton(OrderAnalyticsObserver::class);
        Order::observe(OrderAnalyticsObserver::class);
    }
}
