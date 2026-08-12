<?php

namespace App\Http\Controllers;

use App\Models\TicketType;
use App\Models\Zone;

class PricingController extends Controller
{
    public function __invoke()
    {
        return view('pages.pricing', [
            // Solo ENTRADAS en la rejilla (igual que la home): los packs tienen su sección en
            // /cumpleanos + su CTA aquí, y los complementos no son tarjetas de precio. Sin el
            // filtro, /precios pintaba packs y addons como tarjetas comprables espurias (#194).
            // `inOperationalZone()`: NO anunciar entradas de una zona desactivada con CTA «Reservar»
            // que el flujo de compra no puede vender (espejo de packs y del sidebar; Sistema 6 · W4).
            'tickets' => TicketType::with(['prices.rateType', 'addons.prices.rateType'])
                ->ofType(TicketType::TYPE_ENTRY)
                ->where('is_active', true)->inOperationalZone()->orderBy('position')->get(),
            'zones' => Zone::where('show_in_landing', true)->orderBy('position')->get(),
        ]);
    }
}
