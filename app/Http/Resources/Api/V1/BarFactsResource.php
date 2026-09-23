<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Models\BarImage;
use App\Domain\Content\Services\BarPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **`GET /api/v1/bar` — EL BAR, como hechos** (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️⚠️ **El NOMBRE gatea la publicación entera**, y no es una regla de este recurso: la pone `BarPage`
 * —*«sin él no hay titular, y sin titular no hay página»*—. Sin nombre, la clave `bar` **no viaja**, igual
 * que `rating` falta en `/social-proof` cuando no hay cifra sostenible. Una instalación sin bar no tiene
 * que emitir un sobre vacío para que su API valide.
 *
 * ⚠️ **`free_entry` sale como BOOLEANO.** En la tabla es la cadena `yes`/`no`, que es un detalle de cómo
 * lo guarda el panel; un cliente no tiene por qué aprenderse ese vocabulario para contestar «¿se puede
 * entrar sin pagar?». Sin configurar, la clave **falta**: no se afirma ni que sí ni que no.
 *
 * ⚠️⚠️ **Las DIMENSIONES viajan, y son la mitad del valor de este plato.** `BarImage` las mide contra el
 * disco al guardar, y existen justo para que el navegador reserve el hueco: la carta es la imagen más
 * grande de la web y sin `width`/`height` la página SALTA al cargarla. Una landing que no las reciba
 * volvería a tener ese salto y no sabría por qué.
 *
 * ⚠️ **Una imagen SIN `alt` sí viaja, y aquí la regla se separa a propósito de la de `#671`.** Una duda
 * sin respuesta no publica nada útil, pero una carta sin texto alternativo **sigue siendo la carta**: el
 * contenido es la imagen. Esconderla no arregla la accesibilidad, quita el menú. El `alt` es obligatorio
 * en el formulario del panel, así que el hueco solo aparece en filas metidas por SQL o migración, y el
 * contrato lo declara opcional para que quien pinte sepa que tiene que contemplarlo.
 *
 * ▶ **El pie va DENTRO de la foto del local**, no suelto en la raíz: sin foto no hay nada que pie.
 */
class BarFactsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'lang' => app()->getLocale(),
            'updated_at' => BarPage::updatedAt()?->toIso8601String(),
            ...(BarPage::isPublished() ? ['bar' => $this->bar()] : []),
        ];
    }

    /** @return array<string, mixed> */
    private function bar(): array
    {
        $entradaLibre = BarPage::freeEntry();
        $local = BarPage::venuePhoto();

        return [
            'name' => (string) BarPage::name(),
            ...array_filter(['lede' => BarPage::lede()], fn (?string $v): bool => $v !== null),
            ...($entradaLibre === null ? [] : ['free_entry' => $entradaLibre === 'yes']),
            'menu' => BarPage::menu()->map(fn (BarImage $imagen): array => $this->imagen($imagen))->all(),
            ...($local === null ? [] : ['venue' => [
                ...$this->imagen($local),
                ...array_filter(
                    ['caption' => BarPage::photoCaption()],
                    fn (?string $v): bool => $v !== null,
                ),
            ]]),
        ];
    }

    /** @return array<string, mixed> */
    private function imagen(BarImage $imagen): array
    {
        $alt = $imagen->tr('alt');

        return array_filter([
            'url' => $imagen->imageUrl(),
            'alt' => is_string($alt) ? trim($alt) : null,
            'width' => $imagen->width,
            'height' => $imagen->height,
        ], fn ($valor): bool => $valor !== null && $valor !== '');
    }
}
