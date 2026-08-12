<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\LandingService;

class ServicesController extends Controller
{
    public function __invoke()
    {
        // Página /servicios data-driven (#256, modelo A): las secciones editoriales salen de la
        // entidad CMS `LandingService` (gestionable en el panel), no de `lang/services.php`. Lo
        // COMERCIAL (precio/complementos) se lee en vivo del `ticketType` vinculado + su zona. Si una
        // sección no tiene pack comprable, degrada a CTA «Pedir información» (§9). Con 0 servicios la
        // página conserva su hero editorial + la banda «Otros eventos» (no rompe ni queda vacía).
        return view('pages.services', [
            'services' => LandingService::active()->ordered()
                ->with([
                    'ticketType.zone',
                    'ticketType.prices.rateType',
                    'ticketType.addons.prices.rateType',
                ])
                ->get(),
        ]);
    }
}
