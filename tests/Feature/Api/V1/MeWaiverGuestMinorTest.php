<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\ApiTestCase;

/**
 * `GET /api/v1/me/waiver` **con justificantes de menores invitados en la cuenta**
 * (`specs/waiver-por-reserva.md` §4.5), contra el CONTRATO.
 *
 * ❗❗ **Vive aquí y no junto al resto de sus hermanos a propósito.** `ApiTestCase` valida cada
 * respuesta contra `openapi/v1.yaml` con Spectator, y el contrato declara
 * `subject: enum [holder, dependent]` con `additionalProperties: false`. O sea que **el propio
 * contrato es la segunda guarda**: si una firma `guest_minor` se colara en `signatures`, la respuesta
 * dejaría de encajar en el esquema y este fichero se pondría rojo aunque nadie hubiera escrito una
 * aserción sobre ella.
 *
 * ⚠️ La primera versión de estos dos casos extendía `Tests\TestCase`, así que **no validaba nada del
 * contrato** — pasaban en verde sin ejercer la guarda que la spec decía estar usando. *Heredar de la
 * clase equivocada convierte un test de contrato en un test de texto.*
 */
class MeWaiverGuestMinorTest extends ApiTestCase
{
    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /** @return array{responsible: User, signature: WaiverSignature} */
    private function scenario(): array
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);

        $result = app(GuardianAuthorizationSigner::class)->sign(
            $responsible,
            (int) $order->id,
            $this->version(),
            [
                'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto', 'minor_born_on' => '2016-11-20',
                'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil', 'guardian_relationship' => 'father',
                'guardian_email' => 'carlos@example.com', 'guardian_phone' => '600333444',
            ],
            WaiverSignatureRequest::web('10.0.0.9', 'Mozilla/5.0 (padre)'),
        );

        return ['responsible' => $responsible, 'signature' => $result['signature']];
    }

    public function test_the_responsible_does_not_see_the_guest_minor_signatures_and_the_response_matches_the_contract(): void
    {
        ['responsible' => $responsible] = $this->scenario();
        Sanctum::actingAs($responsible);

        $response = $this->getJson(self::ROOT.'/me/waiver');

        $response->assertValidRequest();
        $response->assertValidResponse(200);
        $response->assertJsonPath('signatures', []);
        $response->assertJsonPath('signed', false);

        // Aserción sobre el CUERPO, no sobre la intención: ni el menor, ni el adulto, ni el sujeto.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Luis', $body);
        $this->assertStringNotContainsString('carlos@example.com', $body);
        $this->assertStringNotContainsString('guest_minor', $body);
    }

    public function test_the_responsible_still_sees_his_own_signature(): void
    {
        // El CONTROL: sin esto, un filtro que lo tapara TODO pasaría este fichero igual de verde.
        ['responsible' => $responsible] = $this->scenario();
        app(WaiverSigner::class)->sign(
            $responsible,
            LegalDocumentVersion::query()->latest('id')->first(),
            WaiverSignatureRequest::web('10.0.0.1', 'UA'),
        );
        // ⚠️ `fresh()`: firmar escribe `waiver_accepted_at` en la BD, y la instancia que
        // `Sanctum::actingAs()` deja en el contenedor es la que el resource lee. Con la instancia
        // vieja, `signed` saldría `false` con el producto sano — el test mentiría, no el código.
        Sanctum::actingAs($responsible->fresh());

        $response = $this->getJson(self::ROOT.'/me/waiver');

        $response->assertValidResponse(200);
        $response->assertJsonCount(1, 'signatures');
        $response->assertJsonPath('signatures.0.subject', WaiverSignature::SUBJECT_HOLDER);
        $response->assertJsonPath('signed', true);
    }

    public function test_the_pdf_of_a_guest_minor_signature_does_not_exist_for_the_responsible(): void
    {
        ['responsible' => $responsible, 'signature' => $signature] = $this->scenario();
        Sanctum::actingAs($responsible);

        // Llega por RUTA, así que el filtro de la relación no lo alcanza: necesita su propia condición.
        $this->getJson(self::ROOT."/me/waiver/{$signature->id}/pdf")->assertNotFound();
    }
}
