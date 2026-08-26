<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\ApiTestCase;

/**
 * `/api/v1/me/waiver` — mi waiver: estado, ACEPTAR el texto vigente y el PDF de mis firmas
 * (`specs/waiver-probatorio.md` §4.4, §4.5, §4.8).
 *
 * La regla que vale todo el endpoint: **el servidor solo emite la aceptación si la petición trae el
 * identificador de la versión que él sirvió**. Un id de otra versión —el texto cambió entre
 * medias— se rechaza y el cliente vuelve a leer.
 */
class MeWaiverTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/me/waiver';

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
    }

    private function publish(string $locale = 'es'): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            $locale => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function sign(User $holder, LegalDocumentVersion $version): WaiverSignature
    {
        return app(WaiverSigner::class)->sign($holder, $version, WaiverSignatureRequest::web('10.0.0.1', 'test'));
    }

    // ─── GET: el estado ──────────────────────────────────────────────────────

    public function test_in_external_mode_the_status_comes_from_the_stamp(): void
    {
        $user = User::factory()->create(['waiver_accepted_at' => now()]);

        $response = $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $this->assertSame('externo', $response->json('mode'));
        $this->assertTrue($response->json('signed'));
        $this->assertFalse($response->json('outdated'));
        $this->assertNull($response->json('current_document_id'));
        $this->assertSame([], $response->json('signatures'));
    }

    public function test_in_internal_mode_an_unsigned_holder_gets_the_document_to_accept(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create(['waiver_accepted_at' => now()]); // un sello sin registro no cuenta

        $response = $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $this->assertFalse($response->json('signed'));
        $this->assertFalse($response->json('outdated'));
        $this->assertSame($document->id, $response->json('current_document_id'));
        $this->assertNull($response->json('accepted_at'));
    }

    public function test_a_signed_holder_sees_the_signature_with_its_pdf_and_becomes_outdated_when_the_text_changes(): void
    {
        $this->mode('interno');
        $user = User::factory()->create();
        $signature = $this->sign($user, $this->publish());

        $response = $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200);
        $this->assertTrue($response->json('signed'));
        $this->assertFalse($response->json('outdated'));
        $this->assertSame(1, $response->json('version'));
        $this->assertSame('v1·es', $response->json('signatures.0.version_label'));
        $this->assertSame('web', $response->json('signatures.0.channel'));
        $this->assertFalse($response->json('signatures.0.declared'));
        $this->assertSame(route('api.v1.me.waiver.pdf', ['signature' => $signature->id]), $response->json('signatures.0.pdf_url'));
        // Nada de ip, user-agent ni hashes: la prueba completa es el PDF.
        $this->assertStringNotContainsString($signature->hash, $response->getContent());
        $this->assertStringNotContainsString('10.0.0.1', $response->getContent());

        $v2 = $this->publish();
        $response = $this->actingAs($user)->getJson(self::PATH)->assertOk();
        $this->assertTrue($response->json('outdated'));
        $this->assertSame($v2->id, $response->json('current_document_id'));
    }

    // ─── POST: aceptar ───────────────────────────────────────────────────────

    public function test_accepting_the_current_document_signs_and_answers_the_new_status(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, ['document_id' => $document->id])
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201);

        $this->assertTrue($response->json('signed'));
        $this->assertFalse($response->json('outdated'));
        $signature = WaiverSignature::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($document->id, $signature->legal_document_version_id);
        $this->assertSame('web', $signature->channel, 'con cookie de sesión el canal es la web');
        $this->assertSame($user->name, $signature->holder_name);
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'type' => 'waiver', 'version' => 'v1·es']);
        $this->assertNotNull($user->fresh()->waiver_accepted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiver.signed']);
    }

    public function test_a_bearer_client_signs_through_the_api_channel(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('Authorization', 'Bearer token-de-prueba')
            ->postJson(self::PATH, ['document_id' => $document->id])
            ->assertCreated();

        $this->assertSame('api', WaiverSignature::where('user_id', $user->id)->value('channel'));
    }

    /** §4.4 — el texto cambió entre servirlo y aceptarlo: NO se acepta el antiguo. */
    public function test_a_stale_document_id_is_rejected_and_nothing_is_signed(): void
    {
        $this->mode('interno');
        $old = $this->publish();
        $this->publish(); // v2

        $user = User::factory()->create();
        $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, ['document_id' => $old->id])
            ->assertStatus(409)
            ->assertValidResponse(409)
            ->assertJsonPath('error.code', 'waiver_document_stale');

        $this->assertSame(0, WaiverSignature::count());
        $this->assertNull($user->fresh()->waiver_accepted_at);
    }

    public function test_an_unknown_document_id_counts_as_stale(): void
    {
        $this->mode('interno');
        $this->publish();

        $this->actingAs(User::factory()->create())
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, ['document_id' => 999999])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'waiver_document_stale');
    }

    public function test_outside_internal_mode_accepting_is_refused(): void
    {
        $document = $this->publish(); // externo por defecto

        $this->actingAs(User::factory()->create())
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, ['document_id' => $document->id])
            ->assertStatus(409)
            ->assertValidResponse(409)
            ->assertJsonPath('error.code', 'waiver_not_internal');

        $this->assertSame(0, WaiverSignature::count());
    }

    public function test_the_document_id_is_required(): void
    {
        $this->mode('interno');

        $this->actingAs(User::factory()->create())
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, [])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_re_signing_after_a_new_version_links_the_chain(): void
    {
        $this->mode('interno');
        $user = User::factory()->create();
        $first = $this->sign($user, $this->publish());
        $v2 = $this->publish();

        $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, ['document_id' => $v2->id])
            ->assertCreated()
            ->assertJsonPath('outdated', false);

        $second = WaiverSignature::where('user_id', $user->id)->orderByDesc('id')->first();
        $this->assertSame($first->hash, $second->prev_hash);
    }

    // ─── El PDF propio ───────────────────────────────────────────────────────

    public function test_the_holder_gets_their_own_pdf_audited_and_not_stored(): void
    {
        $this->mode('interno');
        $user = User::factory()->create(['name' => 'Ana Pérez']);
        $signature = $this->sign($user, $this->publish());

        $response = $this->actingAs($user)->get(route('api.v1.me.waiver.pdf', ['signature' => $signature->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $log = AuditLog::where('action', 'waiver.proof_downloaded')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->payload['by_holder']);
        $this->assertStringNotContainsString('Ana', json_encode($log->payload));
    }

    public function test_someone_elses_signature_does_not_exist_for_me(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $ofBea = $this->sign($bea, $version);

        $this->actingAs($ana)->get(route('api.v1.me.waiver.pdf', ['signature' => $ofBea->id]))->assertNotFound();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'waiver.proof_downloaded']);
    }

    public function test_the_pdf_and_the_status_reject_an_anonymous_request(): void
    {
        $this->mode('interno');
        $signature = $this->sign(User::factory()->create(), $this->publish());

        $this->getJson(self::PATH)->assertUnauthorized();
        $this->getJson(route('api.v1.me.waiver.pdf', ['signature' => $signature->id]))->assertUnauthorized();
    }

    // ─── Fuera de toda superficie normal (§4.6) ───────────────────────────────

    public function test_the_export_carries_the_visible_consent_but_not_the_probatory_record(): void
    {
        $this->mode('interno');
        $user = User::factory()->create();
        $signature = $this->sign($user, $this->publish());

        $export = $this->actingAs($user)->getJson(self::ROOT.'/me/export')->assertOk();
        $json = $export->getContent();

        $this->assertStringContainsString('"waiver"', $json, 'el consentimiento visible sí viaja (art. 20)');
        $this->assertStringNotContainsString($signature->hash, $json);
        $this->assertStringNotContainsString($signature->document_hash, $json);
        $this->assertStringNotContainsString('waiver_signatures', $json);
    }
}
