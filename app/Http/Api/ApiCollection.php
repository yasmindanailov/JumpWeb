<?php

namespace App\Http\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Fase 3 · paso 1 — **la única forma de lista** de `/api/v1` (spec §4.3: «listas bajo `data` +
 * `meta`»). Vive junto a `ApiErrorResponse` porque hace lo mismo para el otro caso: fijar en un
 * solo sitio una forma que el contrato promete.
 *
 * **Por qué no extiende `ResourceCollection`** (se intentó, y la guarda de contrato lo cazó en el
 * primer test): con un paginador, Laravel responde a través de `PaginatedResourceResponse`, que
 * ignora `$wrap` y añade sus propios `links` y `meta` — el resultado era `data.data` y dos `meta`
 * distintos en la misma respuesta. Construyendo la respuesta aquí, la forma es exactamente la
 * documentada, pagine el endpoint o no.
 *
 * Dos decisiones:
 *  - **`meta` siempre presente**, también sin paginar (con `total` a secas). Un cliente que lee
 *    `meta.total` no debería tener que saber si ese endpoint pagina.
 *  - **Sin `links`**. Las URLs absolutas de Laravel no le sirven a un cliente móvil, que construye
 *    sus peticiones, y obligarían a la API a conocer su propia URL pública. `meta` lleva lo
 *    necesario para paginar: página actual, última, tamaño y total.
 *
 * Cada elemento lo serializa su `JsonResource` de siempre: aquí solo se decide el envoltorio.
 *
 * Uso: `new ApiCollection($paginadorOColeccion, OrderResource::class)`.
 */
final class ApiCollection implements Responsable
{
    /**
     * @param  LengthAwarePaginator|iterable<mixed>  $resource
     * @param  class-string<JsonResource>  $resourceClass
     */
    public function __construct(
        private readonly mixed $resource,
        private readonly string $resourceClass,
    ) {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->data($request),
            'meta' => $this->meta(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function data(Request $request): array
    {
        return $this->items()
            ->map(fn (mixed $item): array => (new $this->resourceClass($item))->toArray($request))
            ->values()
            ->all();
    }

    /** @return Collection<int, mixed> */
    private function items(): Collection
    {
        return $this->resource instanceof LengthAwarePaginator
            ? new Collection($this->resource->items())
            : new Collection($this->resource);
    }

    /** @return array<string, int> */
    private function meta(): array
    {
        if ($this->resource instanceof LengthAwarePaginator) {
            return [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ];
        }

        return ['total' => $this->items()->count()];
    }
}
