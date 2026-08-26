<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Users\Pages\ViewUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · waiver, tanda 2 — el registro probatorio en la ficha del usuario
 * (`docs/specs/waiver-probatorio.md` §4.6): NO es una sección, es una ACCIÓN con permiso propio
 * (`waiver.view`) cuya apertura es la consulta y queda auditada. Visible también sobre cuentas
 * anonimizadas.
 *
 * ⚠️ Trampa del arnés, medida (spec §9.7): en Livewire 4 la vista de modales de Filament es un
 * `wire:partial`, y el HTML del componente tras `mountAction()` NO la incluye — `assertSee` sobre el
 * contenido del modal sale rojo aunque el modal exista y se abra. Por eso aquí cada pieza se
 * prueba donde SÍ es observable: la visibilidad de la acción, la auditoría al montarla, que su
 * `modalContent` es la vista del registro, y esa vista renderizada directamente.
 */
class WaiverProofActionTest extends TestCase
{
    use RefreshDatabase;

    private const VIEW = 'filament.users.partials.waiver-proof';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function publish(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function modalHtml(User $record): string
    {
        return view(self::VIEW, ['record' => $record->fresh()])->render();
    }

    public function test_admin_sees_the_action_opening_it_is_audited_and_its_content_is_the_proof_view(): void
    {
        $customer = $this->userWithRole('customer');
        app(WaiverSigner::class)->sign($customer, $this->publish(), WaiverSignatureRequest::web('10.0.0.1', 'test'));

        $component = Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertActionVisible('waiverProof')
            ->mountAction('waiverProof');

        // «Cada consulta auditada» (§4.6): abrir el registro ES la consulta. Sin PII.
        $log = AuditLog::where('action', 'waiver.proof_viewed')->latest('id')->first();
        $this->assertNotNull($log, 'abrir el registro ES la consulta y tiene que auditarse');
        $this->assertSame($customer->id, (int) $log->target_id);
        $this->assertSame(1, $log->payload['signatures']);
        $this->assertStringNotContainsString($customer->name, json_encode($log->payload));

        // Lo que el modal pinta es la vista del registro, con esta persona.
        $content = $component->instance()->getMountedAction()->getModalContent();
        $this->assertInstanceOf(View::class, $content);
        $this->assertSame(self::VIEW, $content->name());
        $this->assertSame($customer->id, $content->getData()['record']->id);
    }

    public function test_the_action_is_hidden_without_the_waiver_permission_even_for_who_manages_users(): void
    {
        $staff = $this->userWithRole('staff');
        Role::where('name', 'staff')->first()->permissions()->syncWithoutDetaching([
            Permission::where('name', 'users.manage')->value('id'),
        ]);
        $customer = $this->userWithRole('customer');

        Livewire::actingAs($staff)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertActionHidden('waiverProof');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'waiver.proof_viewed']);
    }

    public function test_the_record_view_lists_the_signatures_with_status_identity_integrity_and_pdf_link(): void
    {
        $customer = $this->userWithRole('customer');
        $customer->forceFill(['name' => 'Ana Pérez', 'email' => 'ana@example.com'])->save();
        $signature = app(WaiverSigner::class)->sign($customer->fresh(), $this->publish(), WaiverSignatureRequest::web('10.0.0.1', 'test'));

        $html = $this->modalHtml($customer);

        $this->assertStringContainsString(__('admin.waiver.proof.status_current', ['version' => 1]), $html);
        $this->assertStringContainsString('v1·es', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringContainsString(__('admin.waiver.proof.integrity_ok'), $html);
        $this->assertStringContainsString(__('admin.waiver.proof.channels.web'), $html);
        $this->assertStringContainsString(route('admin.users.waiver.proof', ['user' => $customer, 'signature' => $signature]), $html);
        $this->assertStringNotContainsString(__('admin.waiver.proof.declared_by', ['operator' => '']), $html);
    }

    public function test_a_declared_signature_names_the_operator_in_the_record_view(): void
    {
        $customer = $this->userWithRole('customer');
        $operator = $this->userWithRole('staff');
        $operator->forceFill(['name' => 'Lucía Operadora'])->save();
        app(WaiverSigner::class)->sign($customer, $this->publish(), WaiverSignatureRequest::declaredAtCounter($operator->fresh(), '192.168.1.5'));

        $html = $this->modalHtml($customer);

        $this->assertStringContainsString(__('admin.waiver.proof.declared_by', ['operator' => 'Lucía Operadora']), $html);
        $this->assertStringContainsString(__('admin.waiver.proof.channels.panel'), $html);
    }

    public function test_the_record_of_an_anonymised_account_is_still_reachable_and_identifies_the_signer(): void
    {
        $customer = $this->userWithRole('customer');
        $customer->forceFill(['name' => 'Ana Pérez', 'email' => 'ana@example.com'])->save();
        app(WaiverSigner::class)->sign($customer->fresh(), $this->publish(), WaiverSignatureRequest::web('10.0.0.1', 'test'));
        $this->assertTrue($customer->fresh()->anonymize());

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertActionVisible('waiverProof')
            ->mountAction('waiverProof');
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiver.proof_viewed']);

        $html = $this->modalHtml($customer);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringNotContainsString('Cliente eliminado', $html);
    }

    public function test_the_empty_state_and_the_outdated_status(): void
    {
        $customer = $this->userWithRole('customer');

        $html = $this->modalHtml($customer);
        $this->assertStringContainsString(__('admin.waiver.proof.empty'), $html);
        $this->assertStringContainsString(__('admin.waiver.proof.status_unsigned'), $html);

        app(WaiverSigner::class)->sign($customer, $this->publish(), WaiverSignatureRequest::web('10.0.0.1', 'test'));
        $this->publish(); // v2: el texto cambió después de firmar

        $this->assertStringContainsString(__('admin.waiver.proof.status_outdated', ['version' => 1]), $this->modalHtml($customer));
    }
}
