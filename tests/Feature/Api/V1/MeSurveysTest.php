<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `PUT /me/surveys` (`docs/specs/encuestas.md` §4.3, T3; contrato 1.28.0): recibir la encuesta del día siguiente,
 * o no. Correo de servicio: sin contraseña y sin consentimiento que sellar; `GET /me` refleja el estado.
 */
class MeSurveysTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    public function test_the_switch_writes_the_preference_and_get_me_shows_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->putJson(self::ROOT.'/me/surveys', ['accepted' => false])->assertNoContent();
        $this->assertTrue((bool) $user->fresh()->surveys_opt_out);
        $this->actingAs($user)->getJson(self::ROOT.'/me')->assertOk()->assertJsonPath('surveys_opt_out', true);

        $this->actingAs($user)->putJson(self::ROOT.'/me/surveys', ['accepted' => true])->assertNoContent();
        $this->assertFalse((bool) $user->fresh()->surveys_opt_out);

        // La llamada que no cambia nada también es un 204: el titular pide un ESTADO.
        $this->actingAs($user)->putJson(self::ROOT.'/me/surveys', ['accepted' => true])->assertNoContent();
    }

    public function test_it_needs_a_session_and_a_boolean(): void
    {
        $this->putJson(self::ROOT.'/me/surveys', ['accepted' => false])->assertUnauthorized();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->putJson(self::ROOT.'/me/surveys', ['accepted' => 'maybe'])->assertUnprocessable();
        $this->assertFalse((bool) $user->fresh()->surveys_opt_out);
    }
}
