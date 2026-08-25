<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · waiver — la puerta en los TRES modos (`docs/specs/waiver-probatorio.md` §4.1, §4.8,
 * §8.2): en `interno` manda el REGISTRO firmado —un sello sin registro no cuenta—, y una firma de
 * una versión anterior se SEÑALA pero deja pasar. `externo` y `desactivado` siguen como en #216
 * (`ValidarRegistroTest` los cubre; aquí solo se comprueba que no se han movido).
 */
class WaiverGateTest extends TestCase
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

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
    }

    private function publish(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function search(string $email): Testable
    {
        return Livewire::actingAs($this->staff())
            ->test(ValidarRegistro::class)
            ->set('input', $email)
            ->call('search');
    }

    public function test_internal_mode_reads_the_signed_record_and_shows_its_date(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create(['email' => 'firmado@example.com', 'waiver_accepted_at' => null]);
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(10, 0));
        app(WaiverSigner::class)->sign($holder, $this->publish(), WaiverSignatureRequest::web('10.0.0.1', 'test'));
        $this->travelBack();

        $this->search('firmado@example.com')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('result.outdated', false)
            ->assertSee('20/08/2026')
            ->assertDontSee(__('admin.waiver.gate_outdated'));

        $status = WaiverStatus::for($holder->fresh());
        $this->assertTrue($status->signed);
        $this->assertTrue($status->isCurrent);
        $this->assertSame(1, $status->version);
        $this->assertSame('es', $status->locale);
    }

    /** §4.8 — la re-firma se pide en el siguiente momento natural, NUNCA en el mostrador. */
    public function test_internal_mode_flags_a_signature_of_an_older_version_but_lets_the_customer_in(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create(['email' => 'antiguo@example.com']);
        app(WaiverSigner::class)->sign($holder, $this->publish(), WaiverSignatureRequest::web('10.0.0.1', 'test'));
        $this->publish(); // v2: el texto cambió después de firmar

        $this->search('antiguo@example.com')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('result.outdated', true)
            ->assertSee(__('admin.waiver.gate_outdated'));

        $this->assertTrue(WaiverStatus::for($holder->fresh())->isOutdated());
    }

    /** §8.2 — en interno manda el REGISTRO: el sello de un sistema externo anterior no cuenta. */
    public function test_internal_mode_ignores_a_stamp_without_a_signed_record(): void
    {
        $this->mode('interno');
        $this->publish();
        User::factory()->create(['email' => 'sello@example.com', 'waiver_accepted_at' => now()]);

        $this->search('sello@example.com')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_NO_WAIVER);
    }

    public function test_external_mode_still_reads_the_stamp_and_never_flags_outdated(): void
    {
        $this->mode('externo');
        User::factory()->create(['email' => 'externo@example.com', 'waiver_accepted_at' => now()]);

        $this->search('externo@example.com')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
            ->assertSet('result.outdated', false);
    }

    public function test_disabled_mode_collapses_to_two_states(): void
    {
        $this->mode('desactivado');
        User::factory()->create(['email' => 'off@example.com', 'waiver_accepted_at' => null]);

        $this->search('off@example.com')
            ->assertSet('result.status', ValidarRegistro::STATUS_REGISTERED);
    }
}
