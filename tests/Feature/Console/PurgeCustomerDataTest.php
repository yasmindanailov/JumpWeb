<?php

namespace Tests\Feature\Console;

use App\Models\Order;
use App\Models\RateType;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `app:purge-customers` — pizarra limpia para go-live: borra TODOS los pedidos/usuarios salvo `--keep`,
 * conservando el contenido. Cubre el orden FK-seguro (reembolsos→pagos→pedidos→usuarios), la
 * conservación de contenido y cuentas, el dry-run y las guardas (typo en --keep, sin admin, sin --keep).
 */
class PurgeCustomerDataTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 10,
        ]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function user(string $email, string $role): User
    {
        $u = User::factory()->create(['email' => $email]);
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    /** Crea un pedido completo (item + ticket + pago) para `$owner`. */
    private function orderFor(User $owner, string $code): Order
    {
        $order = Order::create(['user_id' => $owner->id, 'code' => $code, 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $order->items()->create(['ticket_type_id' => $this->entry->id, 'slot_id' => $this->slot->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1]);
        DB::table('tickets')->insert([
            'order_id' => $order->id, 'ticket_type_id' => $this->entry->id, 'slot_id' => $this->slot->id,
            'qr_token' => Str::random(32), 'status' => 'purchased', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'cash',
            'amount' => 1000, 'currency' => 'EUR', 'status' => 'paid', 'paid_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $order;
    }

    public function test_keeps_listed_accounts_and_content_and_purges_the_rest(): void
    {
        $adminKept = $this->user('admin-keep@x.test', 'admin');
        $custKept = $this->user('cust-keep@x.test', 'customer');
        $del1 = $this->user('del1@x.test', 'customer');
        $del2 = $this->user('del2@x.test', 'customer');

        // Consents de un conservado (debe sobrevivir) y de un borrado.
        $adminKept->consents()->create(['type' => 'privacy', 'accepted_at' => now(), 'version' => '2026-05-23']);
        $del1->consents()->create(['type' => 'privacy', 'accepted_at' => now(), 'version' => '2026-05-23']);

        // Pedidos: también el del admin conservado (pizarra limpia = TODOS los pedidos fuera).
        $oAdmin = $this->orderFor($adminKept, 'JJ-ADMIN');
        $o1 = $this->orderFor($del1, 'JJ-DEL1');
        $o2 = $this->orderFor($del2, 'JJ-DEL2');

        // Reembolso RESTRICT (requested_by = conservado) sobre un pedido borrado → debe borrarse igual.
        $paymentO1 = DB::table('payments')->where('payable_id', $o1->id)->value('id');
        DB::table('payment_refunds')->insert([
            'payment_id' => $paymentO1, 'order_item_id' => null, 'amount_cents' => 500, 'currency' => 'EUR',
            'status' => 'succeeded', 'mode' => 'manual', 'requested_by' => $adminKept->id, 'requested_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Ajuste RESTRICT (applied_by = conservado) sobre un pedido borrado → cae por cascada del pedido.
        DB::table('order_adjustments')->insert([
            'order_id' => $o2->id, 'order_item_id' => null, 'type' => 'extra_due', 'amount_cents' => 300,
            'currency' => 'EUR', 'applied_by' => $adminKept->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Adyacentes sin FK del usuario borrado.
        DB::table('password_reset_tokens')->insert(['email' => 'del1@x.test', 'token' => Str::random(40), 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $del1->id, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => 'x', 'last_activity' => time()]);

        $this->artisan('app:purge-customers', ['--keep' => ['admin-keep@x.test', 'cust-keep@x.test'], '--force' => true])
            ->assertExitCode(0);

        // Cuentas: solo las 2 conservadas.
        $this->assertSame(2, User::count());
        $this->assertNotNull(User::where('email', 'admin-keep@x.test')->first());
        $this->assertNotNull(User::where('email', 'cust-keep@x.test')->first());
        $this->assertNull(User::where('email', 'del1@x.test')->first());
        $this->assertNull(User::where('email', 'del2@x.test')->first());

        // Pedidos y adyacentes: 0 (incluido el del admin conservado).
        $this->assertSame(0, Order::count());
        $this->assertSame(0, DB::table('order_items')->count());
        $this->assertSame(0, DB::table('tickets')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('payment_refunds')->count());
        $this->assertSame(0, DB::table('order_adjustments')->count());

        // Adyacentes del usuario borrado: fuera.
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'del1@x.test')->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $del1->id)->count());

        // Conservado intacto: su consent + su rol admin.
        $this->assertSame(1, $adminKept->fresh()->consents()->count());
        $this->assertTrue($adminKept->fresh()->hasRole('admin'));

        // Contenido INTACTO.
        $this->assertSame(1, Zone::count());
        $this->assertSame(1, TicketType::count());
        $this->assertSame(1, Slot::count());
        $this->assertSame(1, RateType::count());
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->user('admin-keep@x.test', 'admin');
        $del = $this->user('del@x.test', 'customer');
        $this->orderFor($del, 'JJ-DRY');

        $this->artisan('app:purge-customers', ['--keep' => ['admin-keep@x.test']]) // sin --force
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertSame(1, Order::count());
        $this->assertNotNull(User::where('email', 'del@x.test')->first());
    }

    public function test_aborts_if_a_kept_email_does_not_exist(): void
    {
        $this->user('admin-keep@x.test', 'admin');
        $del = $this->user('del@x.test', 'customer');
        $this->orderFor($del, 'JJ-AB1');

        // 'no-existe@x.test' no está en la BD → aborto, nada se borra.
        $this->artisan('app:purge-customers', ['--keep' => ['admin-keep@x.test', 'no-existe@x.test'], '--force' => true])
            ->assertExitCode(1);

        $this->assertSame(1, Order::count());
        $this->assertSame(2, User::count());
    }

    public function test_aborts_if_no_kept_account_is_admin(): void
    {
        $this->user('admin-keep@x.test', 'admin');
        $cust = $this->user('cust@x.test', 'customer');

        // Conservar solo un customer → te quedarías sin admin → aborto.
        $this->artisan('app:purge-customers', ['--keep' => ['cust@x.test'], '--force' => true])
            ->assertExitCode(1);

        $this->assertSame(2, User::count());
    }

    public function test_aborts_without_keep(): void
    {
        $this->user('admin-keep@x.test', 'admin');

        $this->artisan('app:purge-customers', ['--force' => true])->assertExitCode(1);

        $this->assertSame(1, User::count());
    }
}
