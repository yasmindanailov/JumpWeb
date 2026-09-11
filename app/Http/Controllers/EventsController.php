<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\BirthdayComparison;
use App\Domain\Content\Services\LandingAddonPresenter;

class EventsController extends Controller
{
    /**
     * `/cumpleanos` (`DECISIONES #528`, artboard `Cumpleanos Pagina PJP`): la comparativa de los
     * packs, el menú, lo que se añade y lo que se decide después de reservar.
     */
    public function __invoke(BirthdayComparison $comparison)
    {
        // Landing de packs: VISIBLE en la web (`is_active`) Y en venta online (`sellable()`).
        // Tras el desacople is_active⊥is_sellable (P3) se exigen AMBOS: no anuncia un pack oculto
        // de la web ni uno no vendible (coherencia CTA⟺catálogo, #210; deep-link `show-packs`).
        // Fuente ÚNICA (#256, modelo A): packs de la superficie Cumpleaños = vendibles de zona
        // operativa SIN un `LandingService` que los reubique en /servicios (idéntico en Home).
        $packages = TicketType::birthdaySurfacePacks()
            // ⚠️ `priceTiers` va cargado porque la comparativa pregunta el precio para CADA número
            // de niños: sin él serían una consulta por pack y por pregunta. `zone` ya no hace falta
            // —era para la foto de la zona, que la página nueva no pinta—.
            ->with(['prices.rateType', 'priceTiers', 'addons.prices.rateType'])
            ->orderBy('position')
            ->get();

        return view('pages.events', [
            'packages' => $packages,
            'compare' => $packages->isEmpty() ? null : $comparison->compose($packages),
            'form' => $packages->isEmpty() ? null : $comparison->form($packages),
            'choices' => LandingAddonPresenter::choiceGroups($packages),
        ]);
    }
}
