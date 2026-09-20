<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\BirthdayComparison;
use App\Domain\Content\Services\LandingAddonPresenter;
use App\Http\Instancia\InstanceViews;

class EventsController extends Controller
{
    public function __construct(private readonly InstanceViews $instancia) {}

    /**
     * `/cumpleanos` (`DECISIONES #528`, artboard `Cumpleanos Pagina PJP`): la comparativa de los
     * packs, el menú, lo que se añade y lo que se decide después de reservar.
     *
     * ⚠️ Desde `#659` (F5 · T2b) la vista vive en la INSTANCIA (`web/cumpleanos.blade.php`) y el producto
     * sirve `anfitrion/cumpleanos` sin paquete. Lo que se pasa es el CONTRATO de la vista
     * (`InstanceViews::CONTRATO_DE_VISTAS`), y todo va COMPUESTO: la landing no calcula un precio.
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
            // de niños: sin él serían una consulta por pack y por pregunta. `zone` vuelve porque la
            // página publica su FOTO (`#532`).
            ->with(['zone', 'prices.rateType', 'priceTiers', 'addons.prices.rateType'])
            ->orderBy('position')
            ->get();

        return view($this->instancia->pick('cumpleanos', 'anfitrion.cumpleanos'), [
            'packages' => $packages,
            /*
             * ⚠️ **La foto es DATO, nunca una ruta escrita aquí**: sale de `zones.image` de la zona
             * del pack, que es el campo que el panel ya ofrece y el mismo del que salía la polaroid
             * de la página anterior. Sin foto en el panel la página no reserva hueco: vacío es una
             * respuesta (`#485`), y un rectángulo gris esperando es peor que no tener nada.
             * ⚠️⚠️ La zona se toma del PRIMER pack a propósito: los packs de cumpleaños comparten
             * zona por construcción (`birthdaySurfacePacks()` los saca de la zona operativa), y
             * cruzar varias fotos en una sola cabecera no es una decisión que pueda tomar la vista.
             */
            'zone' => $packages->first()?->zone,
            'compare' => $packages->isEmpty() ? null : $comparison->compose($packages),
            'form' => $packages->isEmpty() ? null : $comparison->form($packages),
            'choices' => LandingAddonPresenter::choiceGroups($packages),
        ]);
    }
}
