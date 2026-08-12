<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use App\Notifications\CustomerAccountCreated;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.5 (#181) — "Registrar al cliente" en el paso 1 de crear pedido.
 *
 * El cuerpo de la acción es el método público `registerCustomerFromData` (igual patrón que
 * `addLineToCart`/`create` de esta página): crea la cuenta en el acto y la SELECCIONA en el
 * flujo. Guard de privacidad en servidor. Email ya existente → seleccionar sin crear ni enviar.
 */
class RegisterCustomerActionTest extends TestCase
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

    public function test_register_creates_and_selects_customer(): void
    {
        Notification::fake();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Nora Nueva',
                'email' => 'Nora@Example.com',
                'phone' => '600999888',
                'privacy_informed' => true,
            ]);

        $user = User::where('email', 'nora@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue($user->hasVerifiedEmail());

        // Queda seleccionado en el flujo de pedido (para continuar sin volver a buscarlo).
        $component->assertSet('data.customer_id', $user->id);

        Notification::assertSentTo($user, CustomerAccountCreated::class);
    }

    public function test_register_blocked_without_privacy_confirmation(): void
    {
        Notification::fake();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Sin Consent',
                'email' => 'noconsent@example.com',
                'phone' => '600',
                'privacy_informed' => false,
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'noconsent@example.com']);
        Notification::assertNothingSent();
    }

    public function test_register_selects_existing_without_emailing(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $existing->roles()->sync([Role::where('name', 'customer')->value('id')]);

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Cualquiera',
                'email' => 'existing@example.com',
                'phone' => '600',
                'privacy_informed' => true,
            ])
            ->assertSet('data.customer_id', $existing->id);

        Notification::assertNothingSent();
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }
}
