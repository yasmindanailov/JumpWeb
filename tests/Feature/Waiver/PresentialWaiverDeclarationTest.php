<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\CustomerRegistrar;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 6 · waiver, tanda 2 — el alta PRESENCIAL (`docs/specs/waiver-probatorio.md` §8.4,
 * `[DECIDIDO owner]`): en modo interno el operador declara la firma, igual que hoy declara el
 * consentimiento de privacidad. Con las dos condiciones de la decisión —se guarda quién la
 * declaró y no finge ser una firma del titular— y con las dos condiciones de mecanismo: hace falta
 * una versión publicada y un operador con sesión.
 */
class PresentialWaiverDeclarationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();
    }

    private function operator(): User
    {
        $u = User::factory()->create(['name' => 'Lucía Operadora']);
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

    public function test_in_internal_mode_the_counter_registration_produces_a_declared_signature(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $operator = $this->operator();

        $result = $this->actingAs($operator)->app->make(CustomerRegistrar::class)
            ->register('Ana Pérez', 'ana@example.com', '600 11 22 33', waiverDeclared: true);

        $user = $result['user'];
        $signature = WaiverSignature::where('user_id', $user->id)->first();
        $this->assertNotNull($signature, 'el alta presencial en modo interno, CON la declaración del operador, deja la firma declarada');
        $this->assertSame($operator->id, $signature->declared_by_user_id);
        $this->assertSame(WaiverSignature::CHANNEL_PANEL, $signature->channel);
        $this->assertSame($version->id, $signature->legal_document_version_id);
        $this->assertSame('Ana Pérez', $signature->holder_name);
        $this->assertSame('ana@example.com', $signature->holder_email);
        $this->assertTrue($signature->verifyHash());

        // Lo que el titular ve y el sello heredado también quedan escritos.
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'type' => 'waiver', 'version' => 'v1·es']);
        $this->assertNotNull($user->fresh()->waiver_accepted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiver.declared']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'waiver.signed']);
    }

    public function test_in_external_mode_nothing_is_signed(): void
    {
        $this->mode('externo');
        $this->publish();

        $result = $this->actingAs($this->operator())->app->make(CustomerRegistrar::class)
            ->register('Ana', 'ana@example.com', null);

        $this->assertSame(0, WaiverSignature::where('user_id', $result['user']->id)->count());
        $this->assertNull($result['user']->fresh()->waiver_accepted_at);
    }

    public function test_without_a_published_version_the_registration_still_works_but_leaves_no_signature(): void
    {
        $this->mode('interno');

        $result = $this->actingAs($this->operator())->app->make(CustomerRegistrar::class)
            ->register('Ana', 'ana@example.com', null);

        $this->assertTrue($result['created']);
        $this->assertSame(0, WaiverSignature::count());
    }

    public function test_without_an_operator_session_nothing_is_declared(): void
    {
        $this->mode('interno');
        $this->publish();

        $result = app(CustomerRegistrar::class)->register('Ana', 'ana@example.com', null);

        $this->assertTrue($result['created']);
        $this->assertSame(0, WaiverSignature::count(), 'nadie puede declarar una firma sin operador');
    }

    public function test_an_existing_account_is_returned_without_signing_anything(): void
    {
        $this->mode('interno');
        $this->publish();
        User::factory()->create(['email' => 'ana@example.com']);

        $result = $this->actingAs($this->operator())->app->make(CustomerRegistrar::class)
            ->register('Ana', 'ana@example.com', null);

        $this->assertFalse($result['created']);
        $this->assertSame(0, WaiverSignature::count());
    }

    /** `[DECIDIDO owner]` (spec §7·4, `#178`): sin la declaración del operador NO hay firma — aunque el modo sea interno y haya versión. */
    public function test_without_the_operators_declaration_nothing_is_signed(): void
    {
        $this->mode('interno');
        $this->publish();
        $operator = $this->operator();

        $result = $this->actingAs($operator)->app->make(CustomerRegistrar::class)
            ->register('Ana Pérez', 'ana@example.com', '600 11 22 33');

        $this->assertSame(0, WaiverSignature::where('user_id', $result['user']->id)->count());
        $this->assertNull($result['user']->fresh()->waiver_accepted_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'waiver.declared']);
        $this->assertDatabaseMissing('consents', ['user_id' => $result['user']->id, 'type' => 'waiver']);
    }
}
