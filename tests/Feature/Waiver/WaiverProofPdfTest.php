<?php

namespace Tests\Feature\Waiver;

use App\Domain\Content\Models\Page;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverProof;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Fase 6 · waiver, tanda 2 — el PDF del REGISTRO probatorio (`docs/specs/waiver-probatorio.md` §4.5,
 * §4.6, `RGPD-04`): permiso PROPIO, IDOR, auditoría de cada consulta, `no-store`, idioma del texto
 * firmado — y la segunda guarda del subsistema (§6·2): **el PDF sale del snapshot, no del CMS**,
 * demostrado editando la página y publicando otra versión después de firmar.
 */
class WaiverProofPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app()->setLocale('es');
        Setting::updateOrCreate(['key' => 'business.name'], ['value' => 'SaltoPark', 'group' => 'business']);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function staffWith(string $permission): User
    {
        $staff = $this->userWithRole('staff');
        Role::where('name', 'staff')->first()->permissions()->syncWithoutDetaching([
            Permission::where('name', $permission)->value('id'),
        ]);

        return $staff;
    }

    private function publish(string $locale = 'es', string $paragraph = 'Saltar implica riesgos. Los aceptas.'): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            $locale => ['title' => 'Exención de responsabilidad', 'body' => [['h' => 'Riesgo', 'p' => $paragraph]]],
        ])->first();
    }

    private function sign(User $holder, ?LegalDocumentVersion $version = null, ?WaiverSignatureRequest $request = null): WaiverSignature
    {
        return app(WaiverSigner::class)->sign(
            $holder,
            $version ?? $this->publish(),
            $request ?? WaiverSignatureRequest::web('10.0.0.7', 'Mozilla/5.0 (test)'),
        );
    }

    private function url(User $user, WaiverSignature $signature): string
    {
        return route('admin.users.waiver.proof', ['user' => $user, 'signature' => $signature]);
    }

    /** El HTML del documento con las entidades decodificadas: Blade escapa los apóstrofos del francés. */
    private function html(WaiverSignature $signature): string
    {
        $proof = WaiverProof::make($signature->fresh());
        App::setLocale($proof->locale());

        return html_entity_decode(view('pdf.waiver-proof', ['proof' => $proof])->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ─── Acceso: permiso PROPIO, no el del recurso de usuarios ────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $holder = User::factory()->create();
        $signature = $this->sign($holder);

        $this->get($this->url($holder, $signature))->assertRedirect(route('login'));
    }

    public function test_a_customer_gets_403(): void
    {
        $holder = $this->userWithRole('customer');
        $signature = $this->sign($holder);

        $this->actingAs($holder)->get($this->url($holder, $signature))->assertForbidden();
    }

    public function test_staff_without_the_waiver_permission_gets_403_even_with_users_manage(): void
    {
        $staff = $this->staffWith('users.manage');
        $holder = User::factory()->create();
        $signature = $this->sign($holder);

        $this->actingAs($staff)->get($this->url($holder, $signature))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'waiver.proof_downloaded']);
    }

    public function test_staff_with_the_waiver_permission_gets_the_pdf_with_no_store(): void
    {
        $staff = $this->staffWith('waiver.view');
        $holder = User::factory()->create();
        $signature = $this->sign($holder);

        $response = $this->actingAs($staff)->get($this->url($holder, $signature));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        // RGPD-04: el documento lleva nombre, email, ip y user-agent del firmante.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_admin_gets_the_pdf_and_the_consultation_is_audited_without_pii(): void
    {
        $holder = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $signature = $this->sign($holder);

        $this->actingAs($this->userWithRole('admin'))->get($this->url($holder, $signature))->assertOk();

        $log = AuditLog::where('action', 'waiver.proof_downloaded')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('waiver_signature', $log->target_type);
        $this->assertSame($signature->id, (int) $log->target_id);
        $this->assertSame($signature->id, $log->payload['signature_id']);
        $this->assertTrue($log->payload['integrity_ok']);
        $this->assertStringNotContainsString('Ana', json_encode($log->payload));
        $this->assertStringNotContainsString('ana@example.com', json_encode($log->payload));
    }

    /** IDOR: la firma tiene que ser de la persona de la URL. */
    public function test_404_when_the_signature_belongs_to_another_user(): void
    {
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $version = $this->publish();
        $this->sign($ana, $version);
        $ofBea = $this->sign($bea, $version);

        $this->actingAs($this->userWithRole('admin'))->get($this->url($ana, $ofBea))->assertNotFound();
    }

    // ─── Contenido: el snapshot, la identidad, la integridad ─────────────────

    public function test_the_document_carries_the_signed_text_the_identity_and_the_hashes(): void
    {
        $holder = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $signature = $this->sign($holder);

        $html = $this->html($signature);

        $this->assertStringContainsString('SaltoPark', $html);
        $this->assertStringContainsString('Exención de responsabilidad', $html);
        $this->assertStringContainsString('Saltar implica riesgos. Los aceptas.', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringContainsString('10.0.0.7', $html);
        $this->assertStringContainsString('Mozilla/5.0 (test)', $html);
        $this->assertStringContainsString($signature->fresh()->hash, $html);
        $this->assertStringContainsString($signature->fresh()->document_hash, $html);
        $this->assertStringContainsString(__('waiver.proof.first_link', [], 'es'), $html);
        $this->assertStringContainsString(__('waiver.proof.verified_yes', [], 'es'), $html);
        $this->assertStringContainsString(__('waiver.proof.presented_note', [], 'es'), $html);
        $this->assertStringNotContainsString(__('waiver.proof.declared_title', [], 'es'), $html);
        $this->assertStringContainsString('v1·es', $html);
    }

    /** §8.4 — una firma declarada por el operador lo dice con todas las letras. */
    public function test_a_declared_signature_says_so_and_names_the_operator(): void
    {
        $holder = User::factory()->create();
        $operator = User::factory()->create(['name' => 'Lucía Operadora']);
        $signature = $this->sign($holder, null, WaiverSignatureRequest::declaredAtCounter($operator, '192.168.1.5'));

        $html = $this->html($signature);

        $this->assertStringContainsString(__('waiver.proof.declared_title', [], 'es'), $html);
        $this->assertStringContainsString('Lucía Operadora', $html);
        $this->assertStringNotContainsString(__('waiver.proof.presented_note', [], 'es'), $html);
    }

    /** §4.2 — el idioma es parte de la prueba: el documento sale en el idioma del texto firmado. */
    public function test_the_document_is_served_in_the_locale_of_the_signed_text(): void
    {
        $holder = User::factory()->create();
        $signature = $this->sign($holder, $this->publish('fr', 'Sauter comporte des risques.'));

        $html = $this->html($signature);

        $this->assertStringContainsString(__('waiver.proof.title', [], 'fr'), $html);
        $this->assertStringNotContainsString(__('waiver.proof.title', [], 'es'), $html);
        $this->assertStringContainsString('Sauter comporte des risques.', $html);
        $this->assertStringContainsString('lang="fr"', $html);
    }

    /**
     * §6·2 — EL PDF SALE DEL SNAPSHOT, NO DEL CMS. Mutación: se edita la página del waiver y se publica
     * otra versión DESPUÉS de firmar; el documento de la firma no cambia ni un byte.
     */
    public function test_editing_the_page_and_publishing_again_does_not_change_an_issued_document(): void
    {
        $page = Page::create(['slug' => 'waiver', 'title' => ['es' => 'Exención'], 'body' => ['es' => [['h' => 'Riesgo', 'p' => 'Texto ORIGINAL.']]], 'is_active' => true]);
        $holder = User::factory()->create();
        $signature = $this->sign($holder, $this->publish('es', 'Texto ORIGINAL.'));
        $before = $this->html($signature);

        $page->update(['body' => ['es' => [['h' => 'Riesgo', 'p' => 'Texto CAMBIADO después.']]]]);
        $this->publish('es', 'Texto CAMBIADO después.');

        $after = $this->html($signature);

        $this->assertSame($before, $after);
        $this->assertStringContainsString('Texto ORIGINAL.', $after);
        $this->assertStringNotContainsString('Texto CAMBIADO', $after);
    }

    /** §6 — el mismo registro produce el mismo documento dos veces: es lo que lo hace prueba. */
    public function test_the_document_is_deterministic(): void
    {
        $signature = $this->sign(User::factory()->create());

        $this->assertSame($this->html($signature), $this->html($signature));
    }

    /**
     * §4.6 + `#161` — tras `anonymize()` la cuenta dice «Cliente eliminado», pero el documento sigue
     * identificando a quien firmó por la copia que la firma lleva dentro, y lo dice.
     */
    public function test_an_anonymised_holder_is_still_identified_by_the_snapshot(): void
    {
        $holder = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $signature = $this->sign($holder);
        $this->assertTrue($holder->fresh()->anonymize());

        $html = $this->html($signature);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringNotContainsString('Cliente eliminado', $html);
        $this->assertStringContainsString(__('waiver.proof.holder_anonymised', [], 'es'), $html);
        $this->assertStringContainsString(__('waiver.proof.verified_yes', [], 'es'), $html);

        $this->actingAs($this->userWithRole('admin'))->get($this->url($holder->fresh(), $signature))->assertOk();
    }

    public function test_the_retention_line_follows_the_setting(): void
    {
        $holder = User::factory()->create();
        $signature = $this->sign($holder);

        $this->assertStringContainsString(__('waiver.proof.retention_none', [], 'es'), $this->html($signature));

        Setting::updateOrCreate(['key' => 'waiver.retention_months'], ['value' => '24', 'group' => 'waiver']);
        $this->assertStringContainsString(__('waiver.proof.retention_until', ['date' => WaiverProof::make($signature->fresh())->retainUntilLabel()], 'es'), $this->html($signature));
    }

    /** `#154`: lo que lee un cliente vive en los TRES idiomas, y dicen cosas distintas. */
    public function test_the_document_texts_exist_in_the_three_customer_languages_and_differ(): void
    {
        $titles = [];
        foreach (['es', 'en', 'fr'] as $locale) {
            $this->assertTrue(Lang::has('waiver.proof.title', $locale, false), "falta waiver.proof.title en {$locale}");
            $this->assertTrue(Lang::has('waiver.proof.declared_text', $locale, false), "falta waiver.proof.declared_text en {$locale}");
            $this->assertTrue(Lang::has('waiver.proof.channels.panel', $locale, false), "falta waiver.proof.channels.panel en {$locale}");
            $titles[] = __('waiver.proof.title', [], $locale);
        }
        $this->assertCount(3, array_unique($titles), 'los tres idiomas tienen que decir cosas DISTINTAS');
    }
}
