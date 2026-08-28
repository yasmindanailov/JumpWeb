<?php

namespace App\Filament\Support;

use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Pages\CreateManualOrderPage;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Support\Str;

/**
 * El buscador del panel, con una categoría que Filament no trae: **PANTALLAS** (#224).
 *
 * ## Por qué existe
 * De serie, la búsqueda global de Filament encuentra REGISTROS —un pedido, un cliente, una
 * tarifa— y nada más. Eso bastaba cuando las 24 pantallas estaban en la barra lateral. Desde
 * `#223` hay **5 en el menú y 19 detrás de «Ajustes»**, así que la pregunta «¿dónde se
 * cambiaba el horario?» dejó de tener respuesta a golpe de vista. Escribir «horario» y saltar
 * es lo que hace que esconderlas salga barato: sin esto, esconder tiene un precio.
 *
 * ## De dónde salen las pantallas
 * De las MISMAS dos fuentes que las pintan, nunca de una lista aparte:
 *  - la navegación del panel (los 5 sitios del día a día), y
 *  - `AdminSettingsHub::visibleAreas()` (las 19 de puesta en marcha),
 * más la propia «Ajustes» y «Crear pedido», que no están en ninguna de las dos.
 *
 * Las dos vienen **ya filtradas por permiso**, así que nadie encuentra una pantalla que no
 * podría abrir. Y como no hay lista duplicada, una pantalla nueva aparece aquí sola.
 *
 * ## Qué se busca dentro de cada pantalla
 * El rótulo **y su descripción**, que es la mitad útil: «Tarifas» se encuentra escribiendo
 * *precio*, y «Fechas especiales» escribiendo *festivo*, porque eso es lo que dice su tarjeta.
 * Quien busca casi nunca sabe cómo se llama la pantalla; sabe qué quiere hacer.
 *
 * ⚠️ **La comparación ignora tildes**: en un panel en español, escribir «catalogo» tiene que
 * encontrar «Catálogo». Nadie va a poner la tilde al teclear deprisa.
 */
class PanelGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    /** Tope de pantallas listadas, para que no tapen a los registros con una letra. */
    private const LIMIT = 8;

    public function getResults(string $query): ?GlobalSearchResults
    {
        $results = parent::getResults($query) ?? GlobalSearchResults::make();

        $screens = $this->screensMatching($query);

        if ($screens !== []) {
            $results->category(__('admin.search.screens'), $screens);
        }

        return $results;
    }

    /** @return array<int, GlobalSearchResult> */
    private function screensMatching(string $query): array
    {
        $needle = $this->fold($query);

        if ($needle === '') {
            return [];
        }

        $matches = [];

        foreach ($this->screens() as $screen) {
            if (count($matches) >= self::LIMIT) {
                break;
            }

            $haystack = $this->fold($screen['label'].' '.$screen['description']);

            if (! str_contains($haystack, $needle)) {
                continue;
            }

            $matches[] = new GlobalSearchResult(
                title: $screen['label'],
                url: $screen['url'],
                details: $screen['description'] !== ''
                    ? [__('admin.search.screen_detail') => $screen['description']]
                    : [],
            );
        }

        return $matches;
    }

    /**
     * Todas las pantallas que este usuario puede abrir, sin duplicados.
     *
     * @return array<int, array{label: string, description: string, url: string}>
     */
    private function screens(): array
    {
        $screens = [];

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel !== null) {
            foreach ($panel->getNavigation() as $group) {
                foreach ($group->getItems() as $item) {
                    $screens[] = [
                        'label' => (string) $item->getLabel(),
                        'description' => '',
                        'url' => (string) $item->getUrl(),
                    ];
                }
            }
        }

        if (CreateManualOrderPage::canAccess()) {
            $screens[] = [
                'label' => __('admin.orders.create_manual.nav_label'),
                'description' => '',
                'url' => CreateManualOrderPage::getUrl(),
            ];
        }

        if (AdminSettingsHub::canAccess()) {
            $screens[] = [
                'label' => __('admin.hub.nav_label'),
                'description' => __('admin.hub.subheading'),
                'url' => AdminSettingsHub::getUrl(),
            ];

            foreach ((new AdminSettingsHub)->visibleAreas() as $area) {
                foreach ($area['items'] as $item) {
                    $screens[] = [
                        'label' => $item['label'],
                        'description' => $item['description'],
                        'url' => $item['url'],
                    ];
                }
            }
        }

        return $screens;
    }

    /** Minúsculas y sin tildes, para que «catalogo» encuentre «Catálogo». */
    private function fold(string $value): string
    {
        return Str::ascii(Str::lower(trim($value)));
    }
}
