<?php

namespace Tests\Feature\Admin\Users;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.5 (#182) — TODOS los pedidos del cliente en su ficha vía RelationManager
 * (paginación/orden nativos). Sustituye al resumen "8 recientes" del infolist.
 */
class UserOrdersRelationManagerTest extends TestCase
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

    private function orderFor(User $user, string $code): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'subtotal' => 4500,
            'tax' => 0,
            'total' => 4500,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    public function test_lists_only_the_owner_orders(): void
    {
        $customer = $this->userWithRole('customer');
        $mine1 = $this->orderFor($customer, 'JJ-OWN001');
        $mine2 = $this->orderFor($customer, 'JJ-OWN002');

        $other = $this->userWithRole('customer');
        $theirs = $this->orderFor($other, 'JJ-OTHER1');

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(OrdersRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => ViewUser::class,
            ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$mine1, $mine2])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_shows_all_orders_with_pagination(): void
    {
        $customer = $this->userWithRole('customer');
        for ($i = 1; $i <= 12; $i++) {
            $this->orderFor($customer, 'JJ-PAG'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }

        // 12 pedidos en total (la tabla pagina de 10 en 10: cuenta total, no página).
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(OrdersRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => ViewUser::class,
            ])
            ->assertCountTableRecords(12);
    }

    public function test_empty_state_for_user_without_orders(): void
    {
        $customer = $this->userWithRole('customer');

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(OrdersRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => ViewUser::class,
            ])
            ->assertCountTableRecords(0)
            ->assertSee(__('admin.users.orders_summary.empty'));
    }
}
