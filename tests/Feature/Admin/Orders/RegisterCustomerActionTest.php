<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Platform\Models\Setting;
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

    // ─── La casilla del waiver en el alta manual (`[DECIDIDO owner]`, spec §7·4, `#178`) ─────────

    private function internalWaiver(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ]);
    }

    public function test_the_declared_waiver_checkbox_produces_the_declared_signature(): void
    {
        Notification::fake();
        $this->internalWaiver();
        $staff = $this->staff();

        Livewire::actingAs($staff)
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Nora Nueva',
                'email' => 'nora@example.com',
                'phone' => '600999888',
                'privacy_informed' => true,
                'waiver_declared' => true,
            ]);

        $user = User::where('email', 'nora@example.com')->firstOrFail();
        $signature = WaiverSignature::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($staff->id, $signature->declared_by_user_id);
    }

    public function test_without_the_checkbox_the_manual_registration_signs_nothing(): void
    {
        Notification::fake();
        $this->internalWaiver();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Nora Nueva',
                'email' => 'nora@example.com',
                'phone' => '600999888',
                'privacy_informed' => true,
            ]);

        $user = User::where('email', 'nora@example.com')->firstOrFail();
        $this->assertSame(0, WaiverSignature::where('user_id', $user->id)->count());
    }

    // ⚠️ La casilla y el texto viven en el modal de la acción `registerCustomer`, que es un
    // `wire:partial`: `assertSee` no lo ve (`TESTING.md`, trampa del arnés de `#161`). Su visibilidad
    // —solo en interno con versión— es `counterWaiverVersion()`, la misma condición que decide la
    // firma en `CustomerRegistrar`, y esa sí se prueba arriba por sus efectos.

    /**
     * ⚠️ El modal es un `wire:partial`: `Livewire::test()` NO lo renderiza, y el 500 que daba
     * `$version->sections` (propiedad en vez de método) solo lo vio el navegador. Este caso ejecuta el
     * texto del modal DIRECTAMENTE — es la red que faltaba.
     */
    public function test_the_counter_text_renders_the_current_version_escaped_and_only_in_internal_mode(): void
    {
        $this->internalWaiver();
        app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo <b>real</b>', 'p' => 'Saltar implica riesgos & caídas.']]],
        ]);
        $page = app(CreateManualOrderPage::class);

        $this->assertSame(2, $page->counterWaiverVersion()?->version);
        $html = $page->counterWaiverText()->toHtml();
        $this->assertStringContainsString('<strong>Riesgo &lt;b&gt;real&lt;/b&gt;</strong>', $html, 'el encabezado se escapa');
        $this->assertStringContainsString('Saltar implica riesgos &amp; caídas.', $html, 'el párrafo se escapa');

        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'externo', 'group' => 'waiver']);

        // F-06 (`#181`): la versión se memoiza POR PETICIÓN — el modo no cambia a mitad de una; página nueva.
        $page = app(CreateManualOrderPage::class);
        $this->assertNull($page->counterWaiverVersion());
        $this->assertSame('', $page->counterWaiverText()->toHtml());
    }
}
