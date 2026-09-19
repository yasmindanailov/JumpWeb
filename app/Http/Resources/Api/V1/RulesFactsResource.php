<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Models\VenueRule;
use App\Domain\Platform\Services\Translated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/rules` — LAS NORMAS del recinto, como hechos** (F5 · T3 del menú,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Las normas se quedan en el panel cuando la landing se va (`producto-e-instancias.md` §4.3: `ParkRules` y
 * `Pages` NO se retiran) precisamente porque son esto: un hecho que el negocio OPERA y que la web CONSUME.
 * Quien redacta una norma es quien abre la puerta, no quien maqueta.
 *
 * ⚠️⚠️ **Éste es el primer recurso del menú que se TRADUCE**, y por eso pide `lang` en la URL, igual que
 * `/sidebar/boot`: una respuesta que se cachea tiene que ser función de su URL, o una caché acabaría
 * sirviendo francés a quien pidió español. Lo que NO se hace es mandar el mapa entero de traducciones y que
 * el cliente elija: la cadena «idioma → respaldo → la primera que haya» es conocimiento del dominio
 * ({@see Translated}), y repartirla entre landings es garantizar tres
 * implementaciones distintas del mismo respaldo.
 *
 * ⚠️ **El ORDEN es parte del dato.** Las normas van ordenadas por su momento —`before`, `gate`, `inside`, en
 * ESE orden, que es el del recorrido de una visita— y dentro de cada uno por la posición que fija el panel.
 * La constante manda sobre los datos: si el orden saliera de la tabla, reordenar una fila en el panel
 * cambiaría el sentido de la página. La landing agrupa si quiere; el orden ya se lo dan hecho.
 *
 * ⚠️ `updated_at` sale de la norma tocada más recientemente, **no de hoy**: quien leyó las normas hace un año
 * necesita saber si siguen siendo éstas, y escribir la fecha de hoy afirmaría una revisión que nadie hizo.
 * Con la tabla vacía es `null` y no se inventa nada.
 */
class RulesFactsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, VenueRule> $normas */
    public function __construct(private readonly Collection $normas)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        // ⚠️ **Una clave COMPUESTA y no un `sortBy` de dos cierres.** Medido: con el array de cierres la
        // colección salía al revés —`inside` primero y `before` al final—, o sea con el recorrido de la
        // visita invertido, y sin que nada fallara. Una cadena `momento-posición` ordena igual en cualquier
        // versión y se puede leer de un vistazo.
        // Sin momento van al final: son normas que el negocio no ha situado todavía, y colarlas entre las
        // del recorrido daría un orden que nadie decidió.
        $ordenadas = $this->normas
            ->sortBy(fn (VenueRule $r): string => sprintf(
                '%d-%05d', $this->ordenDelMomento($r->momentOrNull()), (int) $r->position,
            ))
            ->values();

        return [
            'lang' => app()->getLocale(),
            // El catálogo de momentos, EN SU ORDEN: con él, una landing que quiera agrupar no tiene que
            // inventarse cuál va antes ni descubrirlo mirando los datos que le tocaron.
            'moments' => VenueRule::MOMENTS,
            'updated_at' => $this->normas->max('updated_at')?->toIso8601String(),
            'rules' => $ordenadas->map(fn (VenueRule $norma): array => array_filter([
                'moment' => $norma->momentOrNull(),
                'name' => $norma->tr('name'),
                'description' => $norma->tr('description'),
                // El PORQUÉ de la norma, que en esta casa se escribe al lado de la norma: es lo que separa
                // «prohibido entrar con comida» de una norma que alguien entiende y cumple.
                'reason' => $norma->tr('reason'),
            ], fn ($valor): bool => $valor !== null && $valor !== ''))->all(),
        ];
    }

    private function ordenDelMomento(?string $momento): int
    {
        $indice = array_search($momento, VenueRule::MOMENTS, true);

        return $indice === false ? count(VenueRule::MOMENTS) : $indice;
    }
}
