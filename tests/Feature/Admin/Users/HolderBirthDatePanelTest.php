<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use App\Filament\Resources\Users\Pages\ViewUser;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La fecha de nacimiento del titular, en el PANEL** (TP·1, `DECISIONES #792`): el alta de mostrador la acepta opcional con
 * la misma política que el cliente (`BirthDatePolicy`) y la ficha la enseña con la edad. Las puertas de la API, en
 * `Api\V1\HolderBirthDateTest`.
 *
 * ⚠️ Las dos vías del mostrador que NO pasan por el formulario se prueban aparte: `registerCustomerFromData()` es un método
 * público de Livewire (se llama con cualquier dato) y `createNewCustomerAnyway()` lee `$pendingNoEmailCustomer`, una
 * propiedad pública que el navegador puede reescribir.
 */
class HolderBirthDatePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();
    }

    public function test_the_counter_signup_keeps_an_optional_date(): void
    {
        $this->counter()->call('registerCustomerFromData', $this->counterData('mostrador@ejemplo.test', '1979-11-03'));
        $this->counter()->call('registerCustomerFromData', $this->counterData('sin-fecha@ejemplo.test', null));

        $this->assertSame('1979-11-03', User::where('email', 'mostrador@ejemplo.test')->firstOrFail()->born_on?->toDateString());
        $this->assertNull(User::where('email', 'sin-fecha@ejemplo.test')->firstOrFail()->born_on);
    }

    public function test_the_counter_refuses_a_minor_date_even_without_the_form(): void
    {
        $minor = CarbonImmutable::now('Europe/Madrid')->subYears(9)->toDateString();

        $this->counter()->call('registerCustomerFromData', $this->counterData('nino@ejemplo.test', $minor));

        $this->assertDatabaseMissing('users', ['email' => 'nino@ejemplo.test']);
    }

    public function test_create_anyway_rechecks_a_rewritten_pending_date(): void
    {
        $this->counter()
            ->set('pendingNoEmailCustomer', ['name' => 'Sin Correo', 'phone' => '699000111', 'waiver_declared' => false, 'born_on' => '0198-03-12'])
            ->call('createNewCustomerAnyway');

        $this->assertDatabaseMissing('users', ['phone' => '699000111']);

        // El control: la misma vía con una fecha buena SÍ crea (lo que muerde es la fecha, no el camino).
        $this->counter()
            ->set('pendingNoEmailCustomer', ['name' => 'Sin Correo', 'phone' => '699000222', 'waiver_declared' => false, 'born_on' => '1990-01-01'])
            ->call('createNewCustomerAnyway');

        $this->assertSame('1990-01-01', User::where('phone', '699000222')->firstOrFail()->born_on?->toDateString());
    }

    public function test_the_card_shows_the_date_with_todays_age(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Madrid'));

        $customer = $this->withRole('customer');
        $customer->forceFill(['born_on' => '1988-03-12'])->save();

        Livewire::actingAs($this->withRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertSee('Fecha de nacimiento')
            ->assertSee('12/03/1988 · 38 años');
    }

    private function counter(): Testable
    {
        return Livewire::actingAs($this->withRole('staff'))->test(CreateManualOrderPage::class);
    }

    /** @return array<string, mixed> */
    private function counterData(string $email, ?string $bornOn): array
    {
        // Con correo, el mostrador no busca duplicados por teléfono: el mismo número vale para todos los casos.
        return ['name' => 'Cliente Mostrador', 'email' => $email, 'phone' => '600111333', 'privacy_informed' => true, 'born_on' => $bornOn];
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }
}
