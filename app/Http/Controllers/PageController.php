<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ZoneCards;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Content\Services\RuleBoard;
use App\Domain\Identity\Services\WaiverSettings;

class PageController extends Controller
{
    /** Página de texto legal (contenido desde la tabla `pages`). */
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('pages.text', ['page' => $page]);
    }

    /**
     * `/normas` (`DECISIONES #533`, artboard `Normas PJP`): las normas agrupadas por MOMENTO, con su
     * porqué, la escala de altura y el enlace al descargo.
     */
    public function rules(RuleBoard $board)
    {
        $rules = VenueRule::where('is_active', true)->orderBy('position')->get();

        /*
         * Las zonas que pueden dibujar la escala. ⚠️ Las MISMAS que publica la portada
         * (`show_in_landing`): una zona que el parque no anuncia tampoco explica su altura aquí.
         */
        $zones = Zone::where('is_active', true)->where('show_in_landing', true)
            ->orderBy('position')->get();

        return view('pages.rules', [
            'board' => $board->compose($rules),
            /*
             * ⚠️ El techo y los dos extremos escritos los compone `ZoneCards`, que es de Booking y
             * es quien ya dibuja el eje de la portada: `RuleBoard` vive en Content y **no puede
             * nombrar a Booking** (`ModuleBoundariesTest`), así que recibe la escala resuelta. Lo
             * que no cambia es que el techo sea el MISMO en las dos pantallas.
             */
            'scale' => $board->heightScale(
                $zones,
                ZoneCards::ESCALA_CM,
                ($cards = new ZoneCards)->ceilingLabel(),
                $cards->floorLabel(),
            ),
            /*
             * ⚠️ **El descargo se ofrece con el MISMO criterio que el pie** (`#216`): si la
             * instalación no usa la exención, el pie retira su enlace y aquí no se pinta la chapa.
             * Dos criterios para el mismo enlace acaban con una página que lleva a una que no está.
             */
            'waiverEnabled' => WaiverSettings::isEnabled(),
        ]);
    }
}
