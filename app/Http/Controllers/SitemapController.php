<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\LandingService;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\VenueRule;
use App\Models\TicketType;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    /**
     * Mapa del sitio (XML) con las URLs públicas, enriquecido con `lastmod` (fecha real de la
     * última modificación del contenido que alimenta cada página), `changefreq` y `priority`.
     *
     * El `lastmod` se deriva del `updated_at` de los modelos que renderiza cada página (no fechas
     * inventadas: una fecha falsa hace que Google desconfíe de la señal). Todo guardado por
     * `Schema::hasTable` para no romper en CI / instalación limpia.
     */
    public function __invoke()
    {
        // Modificación más reciente del catálogo/contenido que comparten home, precios y cumpleaños.
        $catalog = $this->latestUpdate([TicketType::class, Zone::class, Attraction::class, Faq::class]);
        $servicesMod = $this->latestUpdate([LandingService::class]) ?? $catalog;
        $rulesMod = $this->latestUpdate([VenueRule::class]) ?? $catalog;
        $legalMods = $this->legalLastmods();

        // route, prioridad, frecuencia de cambio, lastmod (Carbon|null).
        $entries = [
            ['home', '1.0', 'weekly', $catalog],
            ['precios', '0.9', 'weekly', $catalog],
            ['cumpleanos', '0.9', 'weekly', $catalog],
            ['servicios', '0.8', 'weekly', $servicesMod],
            ['contacto', '0.5', 'monthly', null],
            ['normas', '0.4', 'monthly', $rulesMod],
            ['legal.privacidad', '0.3', 'yearly', $legalMods['privacidad'] ?? null],
            ['legal.condiciones', '0.3', 'yearly', $legalMods['condiciones'] ?? null],
            ['legal.waiver', '0.3', 'yearly', $legalMods['waiver'] ?? null],
            ['legal.cookies', '0.3', 'yearly', $legalMods['cookies'] ?? null],
            ['legal.aviso-legal', '0.3', 'yearly', $legalMods['aviso-legal'] ?? null],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($entries as [$name, $priority, $changefreq, $lastmod]) {
            $xml .= '  <url><loc>'.e(route($name)).'</loc>';
            if ($lastmod instanceof Carbon) {
                $xml .= '<lastmod>'.$lastmod->toAtomString().'</lastmod>';
            }
            $xml .= '<changefreq>'.$changefreq.'</changefreq>';
            $xml .= '<priority>'.$priority.'</priority>';
            $xml .= '</url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Mayor `updated_at` entre varios modelos (la fecha de la última edición de su contenido).
     *
     * @param  list<class-string<Model>>  $models
     */
    private function latestUpdate(array $models): ?Carbon
    {
        $latest = null;
        foreach ($models as $model) {
            $table = (new $model)->getTable();
            if (! Schema::hasTable($table)) {
                continue;
            }
            $value = $model::max('updated_at');
            if ($value === null) {
                continue;
            }
            $when = Carbon::parse($value);
            if ($latest === null || $when->greaterThan($latest)) {
                $latest = $when;
            }
        }

        return $latest;
    }

    /**
     * `updated_at` de cada página legal, indexado por slug (una sola consulta).
     *
     * @return array<string,Carbon>
     */
    private function legalLastmods(): array
    {
        if (! Schema::hasTable('pages')) {
            return [];
        }

        return Page::query()
            ->pluck('updated_at', 'slug')
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->all();
    }
}
