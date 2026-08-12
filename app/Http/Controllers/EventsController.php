<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;

class EventsController extends Controller
{
    public function __invoke()
    {
        // El cumpleaños es un producto `pack` (#70): la página lo lee del catálogo unificado
        // (se retiró el modelo EventPackage, #87/3c). Precios cargados para "desde X€".
        // Hay dos packs (Jump/Kids, #1): se pasan todos para reutilizar el mismo componente
        // de sección que la landing (selector + tarjeta de invitación), sin divergencia.
        return view('pages.events', [
            // Landing de packs: VISIBLE en la web (`is_active`) Y en venta online (`sellable()`).
            // Tras el desacople is_active⊥is_sellable (P3) se exigen AMBOS: no anuncia un pack oculto
            // de la web ni uno no vendible (coherencia CTA⟺catálogo, #210; deep-link `show-packs`).
            // Fuente ÚNICA (#256, modelo A): packs de la superficie Cumpleaños = vendibles de zona
            // operativa SIN un `LandingService` que los reubique en /servicios (idéntico en Home).
            'packages' => TicketType::birthdaySurfacePacks()
                // `zone` para la foto de la zona cumpleaños en la sección (#231); resto = precio/complementos.
                ->with(['zone', 'prices.rateType', 'addons.prices.rateType'])
                ->orderBy('position')
                ->get(),
        ]);
    }
}
