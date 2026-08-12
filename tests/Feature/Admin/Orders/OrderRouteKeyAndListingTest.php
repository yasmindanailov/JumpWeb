<?php

namespace Tests\Feature\Admin\Orders;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\DisplayTime;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2a — Route key del OrderResource pasa a `orders.code` (no `id`):
 * URL del panel coherente con lo que ve el usuario (`JJ-XXXX`) y anti-enumeración.
 *
 * También: la columna del listado pasa a `created_at` (todos los pedidos tienen una;
 * solo `paid` tienen `paid_at`, que queda en el detalle).
 */
class OrderRouteKeyAndListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function makeOrder(string $code, string $status = Order::STATUS_PAID): Order
    {
        $user = User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'code' => $code,
            'status' => $status,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'EUR',
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
    }

    // ─── Route key ────────────────────────────────────────────────────────

    public function test_view_resolves_order_by_code(): void
    {
        $order = $this->makeOrder('JJ-AAAA01');

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-AAAA01')
            ->assertOk()
            ->assertSee('JJ-AAAA01');
    }

    public function test_view_returns_404_for_unknown_code(): void
    {
        $this->makeOrder('JJ-AAAA01');

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-NOPE99')
            ->assertNotFound();
    }

    public function test_view_does_not_resolve_by_numeric_id_anymore(): void
    {
        // Anti-enumeración: con la URL antigua (admin/orders/{id}) un atacante podía
        // iterar 1, 2, 3 para mapear actividad. Ahora exige el `code` (`JJ-XXXX`)
        // que es un identificador opaco aleatorio.
        $order = $this->makeOrder('JJ-AAAA01');

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->id)
            ->assertNotFound();
    }

    public function test_route_key_returns_code_not_id(): void
    {
        // Decisión #130: `Order::getRouteKeyName()` override global. Sin esto, Filament
        // genera URLs con `id` aunque `getRecordRouteKeyName()` esté definido en el
        // Resource — el bug que se nos pasó en 7.2a inicial.
        $order = $this->makeOrder('JJ-KEY001');

        $this->assertSame('code', $order->getRouteKeyName());
        $this->assertSame('JJ-KEY001', $order->getRouteKey());
    }

    public function test_listing_generates_view_urls_with_code(): void
    {
        // Test que cubre el GAP que dejó pasar el bug original de 7.2a: clicar una fila
        // del listado generaba `/admin/orders/{id}` y no `/admin/orders/JJ-XXXX`. Sin
        // este test, el cambio del route key solo se verificaba contra acceso directo.
        $order = $this->makeOrder('JJ-LINK001');

        $response = $this->actingAs($this->staff())->get('/admin/orders');
        $response->assertOk();
        $response->assertSee('/admin/orders/JJ-LINK001', escape: false);
    }

    // ─── Listado: columna created_at, no paid_at ─────────────────────────

    public function test_listing_shows_created_at_column(): void
    {
        $createdAt = now()->subDay()->setTime(10, 30);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-LIST01',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ]);
        // forceFill evita el touch automático del timestamp.
        $order->forceFill(['created_at' => $createdAt])->saveQuietly();

        $expected = DisplayTime::format($createdAt);

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee($expected);
    }

    public function test_listing_shows_created_at_for_pending_orders_without_paid_at(): void
    {
        // Una Order pending no tiene paid_at: con la columna antigua (paid_at) salía '—',
        // ocultando información útil. Con created_at, siempre hay valor.
        $order = $this->makeOrder('JJ-PEND01', Order::STATUS_PENDING);

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee(DisplayTime::format($order->created_at));
    }

    // ─── #146: Columna y filtro "Reembolso" ────────────────────────────────

    public function test_refunded_badge_coexists_in_status_column(): void
    {
        // #179: el badge "Reembolsado" YA NO tiene columna propia — coexiste con
        // el badge de estado en la MISMA columna "Estado" (un pedido pagado y
        // reembolsado por completo muestra "Completado" + "Reembolsado").
        $refunded = $this->makeOrder('JJ-RFD001');
        $refunded->update(['refunded_at' => now(), 'refund_amount_cents' => 1000]); // = total → fully refunded

        $this->makeOrder('JJ-CLEAN1');

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee(__('admin.orders.refunded_badge'))               // "Reembolsado"
            ->assertSee(__('admin.orders.status.'.Order::STATUS_PAID));  // coexiste con "Completado"
    }

    public function test_scope_where_has_any_refund_includes_orders_with_refunded_at(): void
    {
        // Caso JJ-9OXNJW empírico: refund anotado solo a nivel Order (sin fila
        // payment_refunds). El scope DEBE incluirlo — Order.refunded_at es fuente
        // de verdad para "ha tenido refund".
        $refunded = $this->makeOrder('JJ-LEGACY1');
        $refunded->update(['refunded_at' => now(), 'refund_amount_cents' => 1000]);

        $clean = $this->makeOrder('JJ-CLEAN2');

        $ids = Order::query()->whereHasAnyRefund()->pluck('id')->all();

        $this->assertContains($refunded->id, $ids);
        $this->assertNotContains($clean->id, $ids);
    }

    public function test_scope_where_has_any_refund_includes_legacy_status_refunded(): void
    {
        $refunded = $this->makeOrder('JJ-LEG-STAT');
        $refunded->update(['status' => Order::STATUS_REFUNDED]);

        $ids = Order::query()->whereHasAnyRefund()->pluck('id')->all();
        $this->assertContains($refunded->id, $ids);
    }
}
