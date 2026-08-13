<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CatalogProductDetailResource;
use App\Http\Resources\Api\V1\CatalogProductResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 1b — el catálogo de productos: qué se vende y qué lleva dentro cada cosa.
 *
 * El controlador **no consulta ni decide**: pide al contrato `Booking\Contracts\ProductCatalog`,
 * que es el mismo que consume el flujo de compra de la web desde este paso. Qué entra en el
 * catálogo —en venta online, zona operativa, seleccionable— y qué se cuenta de cada producto son
 * reglas de Booking; escribirlas aquí habría creado la segunda definición de «qué se vende» que
 * §4.6 del spec manda evitar, y que la web y la API habrían ido separando a base de retoques.
 *
 * **Público y sin paginar.** Público porque es el escaparate (ver `CatalogZonesController`). Sin
 * paginar porque el catálogo de una instalación se cuenta por decenas y la web lo pinta entero en
 * una pantalla: paginar obligaría a todos los clientes a recorrer páginas para poder buscar en
 * local, que es justo lo que hace hoy el buscador progresivo. `meta.total` viaja igual que en
 * cualquier otra lista.
 *
 * **Por qué NO se llama `Availability*` ni vive con el checkout**: ese prefijo dispara el gate de
 * concurrencia del `pre-push` (`INVARIANTES §6`), pensado para el código que orquesta carreras de
 * aforo. Un catálogo de solo lectura no toca ninguna, y exigir dos verificadores de 16 workers por
 * tocarlo entrenaría a saltarse el gate — el mismo criterio que dio nombre a `MeOrdersController`.
 * La disponibilidad real (paso 4) sí llevará ese prefijo y sí lo disparará.
 */
class CatalogProductsController extends Controller
{
    public function index(Request $request, ProductCatalog $catalog): ApiCollection
    {
        $request->validate([
            'type' => ['sometimes', 'string', 'in:'.CatalogProduct::TYPE_ENTRY.','.CatalogProduct::TYPE_PACK],
        ]);

        return new ApiCollection(
            $catalog->products($request->string('type')->value() ?: null),
            CatalogProductResource::class,
        );
    }

    /**
     * Ficha completa. Un id que no está en el catálogo da 404 venga de donde venga —no existe, no
     * está en venta o su zona no opera—: el dominio devuelve `null` sin distinguir el motivo, y
     * distinguirlo aquí le contaría a un desconocido qué hay en la base de datos.
     */
    public function show(int $product, ProductCatalog $catalog): CatalogProductDetailResource
    {
        $detail = $catalog->product($product);

        abort_if($detail === null, 404);

        return new CatalogProductDetailResource($detail);
    }
}
