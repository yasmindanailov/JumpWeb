<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/faqs` — LAS DUDAS, como hechos** (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Las dudas se quedan en el panel cuando la landing se va, por el mismo motivo que las normas
 * (`producto-e-instancias.md` §4.3): son algo que el negocio OPERA y que la web CONSUME. Quien contesta una
 * duda es quien atiende el teléfono, no quien maqueta.
 *
 * ⚠️⚠️ **UNA DUDA A MEDIAS NO VIAJA, Y EL FILTRO VA DESPUÉS DEL RESPALDO.** Una pregunta sin respuesta es
 * contenido roto: publica una duda que el negocio no contesta. Hoy las dos portadas —la del anfitrión y la
 * de la instancia— pintan el acordeón entero sin mirar, y **solo `StructuredData::faqPage()` salta las
 * inservibles**, así que el `FAQPage` y lo que se ve ya divergen. Aquí la regla se aplica al DATO: lo que
 * sale por la API es el conjunto publicable, y una landing que lo pinte no puede equivocarse.
 * ❗❗ **El orden importa y por poco lo escribo al revés**: `Translated::pick()` encadena con `??`, no con
 * `?:`, así que resuelve el respaldo por clave AUSENTE. Filtrar «vacío» mirando el idioma pedido ANTES de
 * pasar por `tr()` dejaría este recurso **vacío entero en `en` y `fr`** —medido: las doce dudas de esta
 * instalación solo tienen `es`, y las claves `en`/`fr` están ausentes, no vacías—. Se filtra por lo que
 * `tr()` DEVUELVE, que es el texto que el cliente va a recibir.
 * ▶ Y el caso `''` sigue siendo alcanzable: el panel escribe cadena vacía cuando alguien rellena y BORRA
 * (`§4.1.ter`), y ahí `??` sí la entrega. Por eso el filtro mira el texto resuelto y no la ausencia.
 *
 * ⚠️ **`updated_at` es de lo SERVIDO, no de la tabla.** Si una duda inservible se edita y sigue sin
 * respuesta, el cuerpo no cambia y la fecha tampoco tiene por qué moverse; el día que se le escriba la
 * respuesta, entra en el conjunto y la fecha salta con ella. Sin dudas publicables es `null` y no se
 * inventa nada: quien las leyó hace un año necesita saber si siguen siendo éstas.
 *
 * ▶ **Lo que este recurso NO publica, a propósito: el `FAQPage` de schema.org.** Es MARCADO, no un hecho, y
 * el producto no tiene por qué imponerle a una landing qué estándar de datos estructurados usa. Lo que sí
 * es conocimiento del producto —qué duda es publicable— viaja dentro del dato, que es donde no caduca.
 */
class FaqsFactsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, Faq> $dudas */
    public function __construct(private readonly Collection $dudas)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $servidas = $this->dudas->filter(
            fn (Faq $duda): bool => $this->pregunta($duda) !== '' && $this->respuesta($duda) !== '',
        )->values();

        return [
            'lang' => app()->getLocale(),
            'updated_at' => $servidas->max('updated_at')?->toIso8601String(),
            'faqs' => $servidas->map(fn (Faq $duda): array => [
                'question' => $this->pregunta($duda),
                'answer' => $this->respuesta($duda),
            ])->all(),
        ];
    }

    private function pregunta(Faq $duda): string
    {
        return trim((string) $duda->tr('question'));
    }

    private function respuesta(Faq $duda): string
    {
        return trim((string) $duda->tr('answer'));
    }
}
