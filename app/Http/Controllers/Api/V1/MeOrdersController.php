<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\ApiCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 1 — «mis pedidos»: el historial del cliente autenticado, **todos los estados**.
 *
 * El «todos los estados» no es un detalle: es el hallazgo 4 de la revisión del spec (§8). Un cliente
 * que pierde el estado —cierra el navegador a medio pagar, cambia de dispositivo— no tenía forma de
 * recuperar su pedido `pending` mientras la retención de aforo seguía viva. Filtrar por «pagados»
 * habría sido la decisión cómoda y le habría escondido justo lo que necesita rescatar.
 *
 * **Por qué se llama `MeOrders` y no `Orders`**: en `app/Http/Controllers/Api`, todo controlador
 * cuyo nombre empieza por `Order` dispara el gate de concurrencia del `pre-push`
 * (`INVARIANTES §6`), y con razón — ahí vivirá el checkout del paso 4—. Este endpoint es de solo
 * lectura y no orquesta ninguna carrera, así que exigir dos
 * verificadores de 16 workers por tocarlo entrenaría a saltarse el gate. Que la separación es
 * legítima y no un truco para esquivarlo lo comprueba `CriticalPathGateTest`, que falla si un
 * controlador fuera del patrón alcanza `OrderCreator`, `SlotGenerator` o `RedsysReturnHandler`.
 *
 * Scoping por el guard (`$request->user()->orders()`), nunca por un identificador de la petición:
 * el anti-IDOR es estructural, no una comprobación que se pueda olvidar.
 */
class MeOrdersController extends Controller
{
    /** Tamaño de página por defecto y techo. La web pagina de 3 en 3 por decisión de producto para
     * ESA pantalla; un cliente de API que sincroniza historial necesita otro orden de magnitud, y
     * el techo evita que `?per_page=100000` convierta el endpoint en una descarga completa. */
    private const PER_PAGE_DEFAULT = 10;

    private const PER_PAGE_MAX = 50;

    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::PER_PAGE_MAX],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $orders = $request->user()->orders()
            // Mismo eager-load que «Mis pedidos» en web, y por el mismo motivo: sin él, pintar el
            // estado de reembolso y los subtotales realmente cobrados dispara N+1 por cada línea.
            ->with([
                'items.ticketType', 'items.slot',
                'items.children.ticketType',
                'payments.refunds', 'adjustments',
            ])
            ->latest()
            ->paginate((int) $request->integer('per_page', self::PER_PAGE_DEFAULT))
            ->withQueryString();

        return new ApiCollection($orders, OrderResource::class);
    }
}
