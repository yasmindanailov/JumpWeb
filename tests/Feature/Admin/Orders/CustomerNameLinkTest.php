<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.5 (#182) — En la ficha del pedido, el nombre del cliente enlaza a su ficha de
 * usuario, pero SOLO si el operador puede verla (`users.manage`, admin). El staff (que
 * ve pedidos pero no usuarios) lo ve como texto plano.
 */
class CustomerNameLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function paidOrderFor(User $customer): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'code' => 'JJ-NAME01',
            'status' => Order::STATUS_PAID,
            'subtotal' => 3000,
            'tax' => 0,
            'total' => 3000,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    public function test_admin_sees_customer_name_as_link_to_user_page(): void
    {
        $customer = $this->userWithRole('customer');
        $order = $this->paidOrderFor($customer);
        $userUrl = UserResource::getUrl('view', ['record' => $customer]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee($userUrl, false);
    }

    public function test_staff_without_users_manage_sees_plain_name(): void
    {
        // El staff ve pedidos (orders.view) pero NO usuarios (users.manage) → sin enlace.
        $staff = $this->userWithRole('staff');
        $this->assertFalse($staff->hasPermission('users.manage'));

        $customer = $this->userWithRole('customer');
        $order = $this->paidOrderFor($customer);
        $userUrl = UserResource::getUrl('view', ['record' => $customer]);

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertDontSee($userUrl, false)
            ->assertSee($customer->name); // el nombre sí se ve (texto plano)
    }
}
