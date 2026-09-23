<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Models\Attraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/attractions` — LOS JUEGOS, como hechos** (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1). El último de los cuatro platos de la vía A.
 *
 * `#632`·P3 lo decidió midiendo: **las 23 atracciones son PRESENTACIÓN** —nombre, descripción, foto,
 * chapa y edad en TEXTO—, sin aforo ni venta, y las restricciones que de verdad importan viven en Normas.
 * Así que este plato es editorial entero y no roza el embudo.
 *
 * ⚠️⚠️ **LA LISTA VA PLANA, con el `slug` de su zona, y eso es una decisión.** La web de hoy las agrupa
 * por zona con pestañas, pero agrupar es estructura y **el menú no impone estructura** (`#631`): una
 * landing que las quiera en una rejilla sin zonas no tendría que deshacer el agrupado. El orden que se
 * entrega ya es el de la web —zona, y dentro cada juego—, así que agrupar por `zone` conserva ese orden
 * sin ordenar nada.
 * ▶ **`zone` es una REFERENCIA, no una copia**: el nombre y la foto de la zona viven en
 * `/catalog/zones`. Un hecho, un sitio.
 *
 * ⚠️⚠️ **EL MOSAICO NO VIAJA, Y ES LO MÁS FÁCIL DE CONFUNDIR CON UN HECHO.** `RideMosaic` elige **tres
 * con nombre y dos veladas** y las coloca en un patrón de celdas: eso no es un dato del negocio, es una
 * decisión de MAQUETA —cuántas caben y cuáles se disuelven— que depende del diseño de quien pinta.
 * Publicarlo obligaría a toda landing a heredar el mosaico de ésta. Lo que el producto sabe, y lo que
 * viaja, es **qué juegos hay y en qué orden**.
 *
 * ⚠️ **`is_special` NO viaja**: medido, no lo pinta ninguna superficie pública —solo la tabla del panel—,
 * y un campo sin consumidor no se convierte en contrato por estar en la tabla (mismo criterio que
 * `nav_subtitle` en `#672`). **`ticket_type_id` tampoco**: `#668` retiró el complemento por atracción con
 * **0 de 23** en uso, y su columna espera la migración conjunta que baja con el MAYOR.
 *
 * ⚠️ **Una atracción sin NOMBRE no viaja**, y el filtro va **después** del respaldo de idioma (`#671`).
 */
class AttractionsFactsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, Attraction> $juegos */
    public function __construct(private readonly Collection $juegos)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $servidos = $this->juegos
            ->filter(fn (Attraction $juego): bool => $this->texto($juego, 'name') !== '')
            ->values();

        return [
            'lang' => app()->getLocale(),
            'updated_at' => $servidos->max('updated_at')?->toIso8601String(),
            'attractions' => $servidos->map(fn (Attraction $juego): array => [
                'zone' => (string) $juego->zone?->slug,
                'name' => $this->texto($juego, 'name'),
                ...array_filter([
                    'description' => $this->texto($juego, 'description'),
                    'age' => $this->texto($juego, 'age'),
                    'badge' => $this->texto($juego, 'badge'),
                    'image_url' => $juego->imageUrl(),
                ], fn (?string $valor): bool => $valor !== null && $valor !== ''),
            ])->all(),
        ];
    }

    private function texto(Attraction $juego, string $campo): string
    {
        $valor = $juego->tr($campo);

        return is_string($valor) ? trim($valor) : '';
    }
}
