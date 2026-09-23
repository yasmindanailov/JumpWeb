<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/services` — LAS SECCIONES DE SERVICIOS, como hechos** (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * El modelo separa lo EDITORIAL de lo COMERCIAL a propósito, y su docblock lo llama «cero drift»: el texto
 * y la foto los escribe el panel, y **lo que cuesta se lee en vivo de los productos vinculados**. Este
 * recurso publica la mitad editorial y **referencia** la comercial; no la copia.
 *
 * ⚠️⚠️ **`price_table` NO VIAJA, y es una decisión con dueño.** Es un JSON de tramos **tecleado a mano** en
 * el panel, y el owner ya decidió que se jubila: la página publicará los precios del catálogo
 * (`DEUDA.md`, `#534`, `[DECIDIDO owner]`). Publicarlo aquí sería servir como HECHO un precio que el
 * checkout podría no cobrar — que es exactamente el modo de fallo que §3 de la spec descartó al elegir
 * «hechos por la API» frente a «una landing que teclea precios». Los tramos REALES los compone
 * `GroupRateTables` desde el catálogo y los publica `/api/v1/prices` en `tiers` (`#677`).
 * ▶ Medido hoy en local, y conviene decirlo entero: la tabla tecleada **coincide** con el catálogo
 * (30/70/100 desde 15,00 €). La contradicción que fichó `#534` se midió contra **producción** (30/75/100
 * desde 12,00 €), que no se puede comprobar desde aquí. *Que hoy coincidan no es una garantía: es la
 * definición del problema —dos fuentes que hay que mantener de acuerdo a mano—.*
 *
 * ⚠️ **`nav_subtitle` y `show_in_nav` tampoco viajan**: están en la base **sin consumidor** desde `#521`,
 * conservadas a la espera de que el owner confirme que no vuelven (`DEUDA.md`). Un campo que no lee nadie
 * no se convierte en contrato público por estar en la tabla.
 *
 * ⚠️⚠️ **`products` son IDENTIFICADORES, no copias, y no hay un `purchasable` al lado.** Los ids se
 * resuelven en `/catalog/products`, que es donde vive el nombre, el «desde» y la foto de un producto: un
 * hecho, un sitio. Y **la lista vacía YA dice que la sección es de solo-contacto**, así que un booleano
 * `purchasable` sería el mismo hecho publicado dos veces — con la posibilidad de contradecirse.
 * ▶ Lo que viaja es `purchasableProducts()` y no `products()`: la landing solo anuncia lo que la cesta
 * puede vender de verdad (`#226`), o sea pack activo, vendible, con zona activa y precio positivo.
 *
 * ⚠️ **Una sección sin TÍTULO no viaja.** El slug es un ancla, y un ancla sin encabezado es una sección en
 * blanco: la misma familia que la duda sin respuesta de `#671`. Todo lo demás es opcional, y **el filtro va
 * DESPUÉS del respaldo de idioma** — `Translated::pick()` encadena con `??`, así que resuelve por clave
 * ausente y mirar el idioma pedido antes de `tr()` vacía el recurso donde no hay traducción (`#671`).
 */
class ServicesFactsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, LandingService> $servicios */
    public function __construct(private readonly Collection $servicios)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $servidos = $this->servicios
            ->filter(fn (LandingService $servicio): bool => $this->texto($servicio, 'title') !== '')
            ->values();

        return [
            'lang' => app()->getLocale(),
            'updated_at' => $servidos->max('updated_at')?->toIso8601String(),
            'services' => $servidos->map(fn (LandingService $servicio): array => $this->seccion($servicio))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function seccion(LandingService $servicio): array
    {
        $fichas = $this->fichas($servicio);

        return [
            'slug' => (string) $servicio->slug,
            ...array_filter([
                'title' => $this->texto($servicio, 'title'),
                'accent_word' => $this->texto($servicio, 'accent_word'),
                'body' => $this->texto($servicio, 'body'),
                'zone_label' => $this->texto($servicio, 'zone_label'),
                'image_url' => $servicio->imageUrl(),
            ], fn (?string $valor): bool => $valor !== null && $valor !== ''),
            ...($fichas === [] ? [] : ['specs' => $fichas]),
            'products' => $servicio->purchasableProducts()
                ->map(fn (TicketType $producto): int => (int) $producto->id)
                ->all(),
        ];
    }

    private function texto(LandingService $servicio, string $campo): string
    {
        $valor = $servicio->tr($campo);

        return is_string($valor) ? trim($valor) : '';
    }

    /**
     * Las fichas de la sección («Duración: 2 o 3 horas»). **Una ficha necesita sus DOS mitades**: un
     * rótulo sin valor no dice nada y un valor sin rótulo no se sabe de qué es.
     *
     * @return list<array{label: string, value: string}>
     */
    private function fichas(LandingService $servicio): array
    {
        $fichas = $servicio->tr('specs');

        if (! is_array($fichas)) {
            return [];
        }

        $limpias = [];

        foreach ($fichas as $ficha) {
            if (! is_array($ficha)) {
                continue;
            }

            $rotulo = trim((string) ($ficha['label'] ?? ''));
            $valor = trim((string) ($ficha['value'] ?? ''));

            if ($rotulo !== '' && $valor !== '') {
                $limpias[] = ['label' => $rotulo, 'value' => $valor];
            }
        }

        return $limpias;
    }
}
