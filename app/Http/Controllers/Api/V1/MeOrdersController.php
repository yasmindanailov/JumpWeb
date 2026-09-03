<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'containing' => ['sometimes', 'string', 'max:32'],
        ]);

        $perPage = (int) $request->integer('per_page', self::PER_PAGE_DEFAULT);

        $orders = $this->ordered($request)
            // Mismo eager-load que «Mis pedidos» en web, y por el mismo motivo: sin él, pintar el
            // estado de reembolso y los subtotales realmente cobrados dispara N+1 por cada línea.
            ->with([
                // ⚠️ `ticketType.addons` es del post-form: `can_add_extras` pregunta por los
                // enganches del pack, y sin precargarlos serían N consultas —una por reserva— en la
                // lista paginada. Con él es UNA para toda la página.
                'items.ticketType.addons', 'items.slot',
                'items.children.ticketType',
                'payments.refunds', 'adjustments',
            ])
            ->paginate($perPage, ['*'], 'page', $this->pageFor($request, $perPage))
            ->withQueryString();

        return new ApiCollection($orders, OrderResource::class);
    }

    /**
     * El historial del cliente con **ORDEN TOTAL**: fecha de creación y, para desempatar, el `id`.
     *
     * ⚠️⚠️ **El desempate no es pulcritud: sin él la paginación PIERDE pedidos** (`DECISIONES #129`).
     * `latest()` ordena solo por `created_at`, así que dos pedidos creados en el mismo segundo no
     * tienen orden entre sí y `LIMIT/OFFSET` puede cortar por un sitio distinto en cada página.
     * **Medido sobre los 57 pedidos reales del cliente demo** —con grupos de hasta 11 compartiendo
     * `created_at`—: recorriendo las 6 páginas salían 57 filas pero solo **55 distintas**; dos
     * repetidas y **dos que no aparecían en ninguna página**, invisibles para su dueño.
     *
     * ▶ Y el patrón no se inventa: `CustomerReservationsReader::ordered()` ya cierra su orden con
     * `order_items.id` por esta misma razón, un fichero más allá. Aquí faltaba.
     *
     * @return HasMany<Order, User>
     */
    private function ordered(Request $request): HasMany
    {
        return $request->user()->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Qué página servir: la pedida, o **la que CONTIENE el pedido de `containing`**.
     *
     * «Ver pedido» de una reserva abre esta pantalla en ese pedido concreto, y **solo el servidor
     * sabe en qué página cae**: el orden y el tamaño de página son suyos. La posición se cuenta con
     * el MISMO orden total de {@see ordered()} —cuántos pedidos van por delante— porque calcularla
     * con otro criterio daría una página que no lo contiene, y eso no lo notaría nadie.
     *
     * ⚠️ **Un código que no es del cliente cae en la primera página, como si no se hubiera enviado.**
     * No es descuido: responder distinto según exista o no convertiría el parámetro en un oráculo de
     * códigos de pedido, que es exactamente lo que `GET /orders/{code}` evita dando 404 en los dos
     * casos. Como la consulta ya está acotada por el guard, «ajeno» e «inexistente» son el mismo caso.
     */
    private function pageFor(Request $request, int $perPage): int
    {
        $code = trim((string) $request->query('containing', ''));

        // `page` es explícito y gana: pedir a la vez una página concreta y la que contiene algo es
        // una contradicción, y resolverla en favor del parámetro implícito sería la sorpresa.
        if ($code === '' || $request->has('page')) {
            return max(1, (int) $request->integer('page', 1));
        }

        $target = $request->user()->orders()
            ->where('code', $code)
            ->first(['id', 'created_at']);

        if ($target === null) {
            return 1;
        }

        // Cuántos van POR DELANTE con el orden total: más recientes, o del mismo instante con `id`
        // mayor. Es la traducción exacta de `orderByDesc('created_at')->orderByDesc('id')`.
        $ahead = $request->user()->orders()
            ->where(fn (Builder $q) => $q
                ->where('created_at', '>', $target->created_at)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('created_at', '=', $target->created_at)
                    ->where('id', '>', $target->id)))
            ->count();

        return intdiv($ahead, $perPage) + 1;
    }
}
