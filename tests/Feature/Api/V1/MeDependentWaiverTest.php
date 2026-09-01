<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Api\ApiTestCase;

/**
 * `POST /api/v1/me/dependents/{dependent}/waiver` — aceptar el waiver vigente EN NOMBRE de un menor a
 * cargo (`specs/menores-a-cargo.md` §4.3, §4.9; `DECISIONES #197`), contra el contrato.
 *
 * Lo firma el ADULTO; el registro dice de quién es y forma su propia cadena. Las reglas del texto son
 * las de `POST /me/waiver`; las del sujeto —suyo, activo, menor— las decide `WaiverSigner` bajo el lock.
 */
class MeDependentWaiverTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
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

    private function add(User $holder, string $name = 'Lior', string $bornOn = '2017-03-12'): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

    private function path(int $dependentId): string
    {
        return self::ROOT."/me/dependents/{$dependentId}/waiver";
    }

    private function accept(User $user, int $dependentId, array $body)
    {
        return $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson($this->path($dependentId), $body);
    }

    public function test_accepting_for_a_dependent_signs_in_their_name_and_answers_the_dependent(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create(['waiver_accepted_at' => null]);
        $lucas = $this->add($user);

        $response = $this->accept($user, $lucas->id, ['document_id' => $document->id])
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201);

        $this->assertSame($lucas->id, $response->json('id'));
        $this->assertTrue($response->json('waiver.signed'));
        $this->assertFalse($response->json('waiver.outdated'));
        $this->assertSame(1, $response->json('waiver.version'));
        $signature = WaiverSignature::where('subject_type', 'dependent')->where('subject_id', $lucas->id)->firstOrFail();
        $this->assertSame($signature->id, $response->json('waiver.signature_id'));
        $this->assertSame(route('api.v1.me.waiver.pdf', ['signature' => $signature->id]), $response->json('waiver.pdf_url'));

        // La identidad del menor viaja EN la firma (v3), y el canal es el del titular que firma.
        $this->assertSame($user->id, $signature->user_id);
        $this->assertSame('Lior', $signature->subject_name);
        $this->assertSame('2017-03-12', $signature->subject_born_on->toDateString());
        $this->assertSame(4, $signature->canonical_version);
        $this->assertNull($signature->subject_authorization_id, 'un menor A CARGO no cuelga de una autorización de reserva');
        $this->assertNull($signature->prev_hash, 'su propia cadena empieza aquí');
        $this->assertSame('web', $signature->channel);
        $this->assertTrue($signature->verifyHash());

        // El titular NO queda firmado por la firma de su menor.
        $this->assertNull($user->fresh()->waiver_accepted_at);
        $this->assertSame(0, $user->consents()->count());

        $log = AuditLog::where('action', 'waiver.signed')->latest('id')->firstOrFail();
        $this->assertSame('dependent', $log->payload['subject_type']);
        $this->assertSame($lucas->id, $log->payload['subject_id']);
        $this->assertStringNotContainsString('Lior', json_encode($log->payload));
    }

    public function test_the_list_and_my_waiver_carry_the_dependents_signature_and_it_goes_outdated_with_a_new_version(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create();
        $lucas = $this->add($user);
        $this->add($user, 'Vilma', '2019-11-02');
        $this->accept($user, $lucas->id, ['document_id' => $document->id])->assertCreated();

        $list = $this->actingAs($user)->getJson(self::ROOT.'/me/dependents')->assertOk()->assertValidResponse(200);
        $this->assertSame([true, false], $list->json('data.*.waiver.signed'));
        $this->assertSame('interno', $list->json('data.0.waiver.mode'));

        $mine = $this->actingAs($user)->getJson(self::ROOT.'/me/waiver')->assertOk()->assertValidResponse(200);
        $this->assertFalse($mine->json('signed'), 'el estado del titular es el suyo');
        $this->assertSame('dependent', $mine->json('signatures.0.subject'));
        $this->assertSame($lucas->id, $mine->json('signatures.0.dependent_id'));
        $this->assertSame('Lior', $mine->json('signatures.0.dependent_name'));

        // El PDF de la firma del menor es del titular: se sirve por la ruta de sus firmas.
        $pdf = $this->actingAs($user)->get($mine->json('signatures.0.pdf_url'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));

        $this->publish(); // v2
        $list = $this->actingAs($user)->getJson(self::ROOT.'/me/dependents')->assertOk();
        $this->assertTrue($list->json('data.0.waiver.outdated'));
        $this->assertTrue($list->json('data.0.waiver.signed'));
    }

    /** §4.9 — ajeno, retirado o inexistente: 404, y nada se firma. */
    public function test_a_foreign_removed_or_unknown_dependent_is_a_404_and_nothing_is_signed(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $ofBea = $this->add($bea);
        $removed = $this->add($ana, 'Retirada', '2018-01-01');
        app(DependentRegistry::class)->remove($ana, $removed->id);

        foreach ([$ofBea->id, $removed->id, 999999] as $id) {
            $response = $this->accept($ana, $id, ['document_id' => $document->id])->assertNotFound()->assertValidResponse(404);
            $this->assertSame('not_found', $response->json('error.code'));
        }

        $this->assertSame(0, WaiverSignature::count());
    }

    public function test_an_adult_dependent_cannot_be_signed_for(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create();
        $almost = $this->add($user, 'Casi', '2008-08-28'); // 17 hoy

        $this->travelTo(Carbon::parse('2026-08-28 09:00:00', 'Europe/Madrid')); // cumple 18
        $response = $this->accept($user, $almost->id, ['document_id' => $document->id])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertSame('dependent_not_minor', $response->json('error.code'));
        $this->assertSame(0, WaiverSignature::count());
    }

    public function test_outside_internal_mode_and_with_a_stale_document_it_is_refused(): void
    {
        $user = User::factory()->create();
        $lucas = $this->add($user);
        $document = $this->publish(); // modo externo por defecto

        $response = $this->accept($user, $lucas->id, ['document_id' => $document->id])->assertStatus(409)->assertValidResponse(409);
        $this->assertSame('waiver_not_internal', $response->json('error.code'));

        $this->mode('interno');
        $this->publish(); // v2: el que se leyó ya no es el vigente
        $response = $this->accept($user, $lucas->id, ['document_id' => $document->id])->assertStatus(409)->assertValidResponse(409);
        $this->assertSame('waiver_document_stale', $response->json('error.code'));

        $this->assertSame(0, WaiverSignature::count());
    }

    /** `#179` — quien firma es el titular, y firma solo con el correo verificado. */
    public function test_an_unverified_holder_cannot_sign_for_a_dependent(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->unverified()->create();
        $lucas = $this->add($user);

        $response = $this->accept($user, $lucas->id, ['document_id' => $document->id])->assertStatus(409)->assertValidResponse(409);

        $this->assertSame('waiver_email_unverified', $response->json('error.code'));
        $this->assertSame(0, WaiverSignature::count());
    }

    public function test_accepting_twice_keeps_one_signature(): void
    {
        $this->mode('interno');
        $document = $this->publish();
        $user = User::factory()->create();
        $lucas = $this->add($user);

        foreach ([1, 2] as $attempt) {
            $this->accept($user, $lucas->id, ['document_id' => $document->id])->assertCreated()->assertJsonPath('waiver.signed', true);
        }

        $this->assertSame(1, WaiverSignature::count());
        $this->assertSame(1, AuditLog::where('action', 'waiver.signed')->count());
    }

    public function test_the_document_id_is_required(): void
    {
        $this->mode('interno');
        $user = User::factory()->create();
        $lucas = $this->add($user);

        $response = $this->accept($user, $lucas->id, [])->assertStatus(422)->assertValidResponse(422);

        $this->assertSame('validation_failed', $response->json('error.code'));
    }

    public function test_it_requires_authentication_and_throttles_in_the_waiver_bucket(): void
    {
        $this->postJson($this->path(1), ['document_id' => 1])->assertUnauthorized()->assertValidResponse(401);

        $route = Route::getRoutes()->getByName('api.v1.me.dependents.waiver.store');
        $this->assertNotNull($route);
        $this->assertContains('throttle:10,1,waiver-sign', $route->gatherMiddleware());
    }
}
