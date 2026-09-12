<?php

namespace App\Http\Controllers;

use App\Domain\Content\Services\BarPage;
use App\Domain\Content\Services\SiteDestinations;

/**
 * `/bar` — la carta corta, y nada más (`DECISIONES #536`, carril de diseño Fase 3 · T3b).
 *
 * Artboard `Bar PJP` **1a** (móvil) + **1b** (escritorio), cerrado en `doc/paginas.md`. La página
 * existe porque la tarjeta del bar de la sección 03 promete *«mesas con el parque a la vista y carta
 * corta»*, y la carta es lo único de esa promesa que no cabe en una tarjeta.
 *
 * ❗❗❗ **LA CARTA SE PUBLICA COMO IMAGEN** (`[DECIDIDO owner, 2026-09-12]`), no tecleando los platos
 * como dibuja el artboard. Se sube en «Ajustes → El bar».
 *
 * ⚠️⚠️ **404 SIN NOMBRE DE BAR, y es la conducta querida.** El titular de esta página es el nombre
 * del local; sin él no hay página que enseñar, y el producto no puede inventárselo. El mismo
 * predicado retira el destino del menú, del pie y de la portada, así que **nadie llega a un 404
 * navegando**: la página no existe hasta que el panel dice cómo se llama.
 * ⚠️ Es 404 y no 503: 503 es «vuelve luego» y lo pone el interruptor de mantenimiento, que esta
 * página también tiene. Aquí no hay nada a lo que volver todavía.
 */
class BarController extends Controller
{
    public function __invoke()
    {
        abort_unless(BarPage::isPublished(), 404);

        /*
         * El enlace del cierre sale del INVENTARIO y no de `route('cumpleanos')` a pelo: si esa
         * página está en mantenimiento, `pages()` no la devuelve y la línea no se pinta. Un destino
         * que no se puede visitar no se anuncia — la regla de `#521`, aplicada a un enlace suelto.
         */
        $party = collect(SiteDestinations::pages())->firstWhere('route', 'cumpleanos');

        return view('pages.bar', [
            'barName' => BarPage::name(),
            'barLede' => BarPage::lede(),
            'barPhoto' => BarPage::venuePhoto(),
            'barPhotoCaption' => BarPage::photoCaption(),
            'barMenu' => BarPage::menu(),
            'barFreeEntry' => BarPage::freeEntry(),
            'partyUrl' => $party['url'] ?? null,
        ]);
    }
}
