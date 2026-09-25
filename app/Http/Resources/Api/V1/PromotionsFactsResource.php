<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/promotions` — LAS PROMOCIONES de hoy, como hechos** (`docs/specs/promociones.md` §4.3, `#770`).
 *
 * Una lista, en el orden en que se APILAN (la que acaba antes primero; las que no acaban, al final): una landing que
 * pinte varias a la vez no decide nada. Cada una dice su clase, su texto en el idioma pedido, hasta cuándo vale y a
 * qué va —`installation`, una zona por su `slug` (el de `/catalog/zones` y `/prices`) o un producto por su `id`—; qué
 * sitio de su página le toca lo decide quien pinta.
 *
 * ⚠️⚠️ **Es TEXTO, no dinero**: ninguna promoción cambia un precio, y aquí no viaja ninguno. Un descuento de verdad está
 * en los precios que ya publica `/prices`.
 * ⚠️ `ends_on` es un DÍA del parque (`2026-09-30`), no un instante: «hasta el 30» incluye el 30. Quien pinta escribe la
 * fecha en su idioma —el producto no manda frases hechas—. Sin fin (un regalo permanente), la clave falta.
 * ⚠️ **Una OFERTA sin texto en ese idioma NO viaja** (la landing le pone su fecha en el idioma de la visita, y medio
 * anuncio en otro idioma se lee roto); un REGALO cae al idioma de respaldo, como hacía el `gifts` del catálogo.
 */
class PromotionsFactsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, Promotion> $promociones */
    public function __construct(private readonly Collection $promociones)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $idioma = app()->getLocale();

        return [
            'lang' => $idioma,
            'promotions' => $this->promociones
                ->map(fn (Promotion $p): ?array => ($texto = $this->texto($p, $idioma)) === null ? null : array_filter([
                    'id' => $p->id,
                    'kind' => $p->kind,
                    'text' => $texto,
                    'ends_on' => $p->ends_on?->toDateString(),
                    'target' => $this->objetivo($p),
                ], fn ($valor): bool => $valor !== null))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function texto(Promotion $p, string $idioma): ?string
    {
        if ($p->kind === Promotion::KIND_OFFER) {
            return $p->textIn($idioma);
        }

        $texto = trim((string) $p->tr('text'));

        return $texto === '' ? null : $texto;
    }

    /** @return array{type: string, zone?: string, product?: int} */
    private function objetivo(Promotion $p): array
    {
        return match ($p->target()) {
            Promotion::TARGET_ZONE => ['type' => Promotion::TARGET_ZONE, 'zone' => (string) $p->zone?->slug],
            Promotion::TARGET_PRODUCT => ['type' => Promotion::TARGET_PRODUCT, 'product' => (int) $p->ticket_type_id],
            default => ['type' => Promotion::TARGET_INSTALLATION],
        };
    }
}
