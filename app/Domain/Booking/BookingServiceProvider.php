<?php

namespace App\Domain\Booking;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\PublishableCatalogReader;
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
        $this->app->bind(ZonePalette::class, ZonePaletteReader::class);
        // `OperatingSchedule` memoiza horarios, temporadas y excepciones: se comparte por
        // petición para no repetir esas lecturas entre la landing, el SEO y el hero.
        $this->app->bind(OperatingCalendar::class, OperatingSchedule::class);
    }
}
