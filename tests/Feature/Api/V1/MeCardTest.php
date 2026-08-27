<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Models\AuditLog;
use Tests\Feature\Api\ApiTestCase;

/**
 * `/api/v1/me/card` — mi carné QR (`specs/identidad-qr-puerta.md` §4.1, §4.5, §9.2 A·8), contra el
 * contrato: nace cuando se pide, y rotar mata el viejo en el acto.
 */
class MeCardTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/me/card';

    public function test_get_issues_the_card_on_first_request_and_then_returns_the_same_one(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, CustomerCard::count());

        $first = $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200);
        $token = $first->json('token');

        $this->assertTrue(CardToken::isWellFormed($token));
        $this->assertSame(20, strlen($token));
        $this->assertSame(1, CustomerCard::where('user_id', $user->id)->count());
        $this->assertSame($token, $this->actingAs($user)->getJson(self::PATH)->assertOk()->json('token'), 'la segunda petición devuelve el MISMO carné');
        $this->assertSame(1, CustomerCard::count());
        $this->assertStringContainsString('no-store', (string) $first->headers->get('Cache-Control'), 'RGPD-04: una credencial de puerta no se guarda en caché');
    }

    public function test_rotate_kills_the_old_card_in_the_act_and_answers_201_with_the_new_one(): void
    {
        $user = User::factory()->create();
        $old = (string) app(CustomerCards::class)->ensureFor($user)->plainToken();

        $response = $this->actingAs($user)->postJson(self::PATH.'/rotate')->assertCreated()->assertValidResponse(201);
        $new = $response->json('token');

        $this->assertNotSame($old, $new);
        $this->assertTrue(CardToken::isWellFormed($new));
        $this->assertTrue(app(CustomerCards::class)->findByToken($old)->isRevoked(), 'el viejo deja de valer en el acto');
        $this->assertSame($new, app(CustomerCards::class)->activeFor($user)->plainToken());
        $this->assertSame($new, $this->actingAs($user)->getJson(self::PATH)->json('token'));
        $this->assertSame(1, AuditLog::where('action', 'cards.rotated')->count());
        $this->assertStringNotContainsString($old, json_encode(AuditLog::all()), 'RGPD-02: el token no se audita');
    }

    public function test_it_requires_a_session_or_a_token(): void
    {
        $this->getJson(self::PATH)->assertUnauthorized()->assertValidResponse(401);
        $this->postJson(self::PATH.'/rotate')->assertUnauthorized()->assertValidResponse(401);
    }

    public function test_the_token_never_leaks_through_the_model_serialization(): void
    {
        $user = User::factory()->create();
        $card = app(CustomerCards::class)->ensureFor($user);

        $this->assertArrayNotHasKey('token', $card->toArray());
        $this->assertSame(['token', 'issued_at'], array_keys($this->actingAs($user)->getJson(self::PATH)->json()), 'el recurso publica exactamente dos campos');
    }
}
