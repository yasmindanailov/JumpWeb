<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GroupRateTables;
use App\Domain\Booking\Services\PartyCards;
use App\Domain\Booking\Services\RateTable;
use App\Domain\Content\Models\LandingService;

class ServicesController extends Controller
{
    public function __invoke(GroupRateTables $groupRates)
    {
        // Página /servicios data-driven (#256, modelo A): las secciones editoriales salen de la
        // entidad CMS `LandingService` (gestionable en el panel), no de `lang/services.php`. Lo
        // COMERCIAL (precio y tramos) se lee en vivo de sus PRODUCTOS (`#588`: varios por servicio).
        // Si una sección no tiene producto comprable, degrada a CTA «Pedir información» (§9). Con 0
        // servicios la página conserva su hero editorial y las bandas de enlace (no rompe ni queda vacía).
        $services = LandingService::active()->ordered()
            ->with(['products.zone', 'products.prices.rateType', 'products.priceTiers'])
            ->get();

        $columnas = new RateTable;

        // ▶ Los cumpleaños, en corto (`#588`, `[DECIDIDO owner]`): la página presenta las excursiones
        // y termina con los packs de cumpleaños resumidos y su puerta a `/cumpleanos`. Son los de la
        // superficie de cumpleaños —la misma fuente que la portada y su página—, así que un pack que
        // vende un servicio no sale aquí dos veces.
        $cumples = TicketType::birthdaySurfacePacks()
            ->with(['prices.rateType', 'priceTiers'])
            ->orderBy('position')
            ->get();

        return view('pages.services', [
            'services' => $services,
            'groupRates' => $services->mapWithKeys(fn (LandingService $service): array => [
                $service->id => $groupRates->compose($service->purchasableProducts()),
            ])->all(),
            // Las columnas dicen lo mismo que en `/precios`: los días de la normal y el rótulo del panel.
            'rateColumns' => ['normal' => $columnas->normalColumnLabel(), 'special' => $columnas->specialColumnLabel()],
            'birthdayCards' => (new PartyCards)->compose($cumples),
        ]);
    }
}
