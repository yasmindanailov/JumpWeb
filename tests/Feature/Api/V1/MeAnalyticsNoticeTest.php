<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Http\Sidebar\AccountContextSeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * **El aviso del enlace con la analítica en el contexto de cuenta** (`specs/analitica.md` §4.3, T3a·4): lo tienen
 * las cuentas que recibieron el correo (`analytics_notified_at`) y no lo han despedido, CON su texto en el idioma
 * de la petición; `DELETE /me/analytics-notice` lo despide (204 siempre, idempotente) y la semilla del montaje lo
 * lleva igual que el endpoint, porque los compone el mismo Resource.
 */
class MeAnalyticsNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const CONTEXT = '/api/v1/me/account-context';

    private const NOTICE = '/api/v1/me/analytics-notice';

    private function notified(array $attributes = []): User
    {
        return User::factory()->create(['analytics_notified_at' => now()->subDay()] + $attributes);
    }

    public function test_a_fresh_account_has_no_notice(): void
    {
        $this->actingAs(User::factory()->create())->getJson(self::CONTEXT)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('analytics_notice', null);
    }

    public function test_a_notified_account_gets_the_notice_with_its_text_until_it_dismisses_it(): void
    {
        $user = $this->notified();

        $this->actingAs($user)->getJson(self::CONTEXT)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('analytics_notice.text', (string) Lang::get('account.analytics_notice.text', [], 'es'))
            ->assertJsonPath('analytics_notice.dismiss', (string) Lang::get('account.analytics_notice.dismiss', [], 'es'));

        $this->actingAs($user)->deleteJson(self::NOTICE)->assertNoContent();

        $this->assertNotNull($user->fresh()->analytics_notice_seen_at, 'la marca queda puesta');
        // ⚠️ El contexto se memoriza por usuario dentro de la petición (singleton): en producción cada petición
        // es un proceso nuevo; aquí las dos comparten contenedor, así que se olvida a mano (el molde de
        // `MeAccountContextTest`). Sin esto, este caso vería el contexto de ANTES de despedirlo.
        app()->forgetInstance(CustomerAccountContext::class);
        $this->actingAs($user)->getJson(self::CONTEXT)->assertOk()->assertJsonPath('analytics_notice', null);
    }

    public function test_dismissing_twice_is_idempotent_and_keeps_the_first_date(): void
    {
        $user = $this->notified(['analytics_notice_seen_at' => now()->subHours(3)]);
        $first = $user->analytics_notice_seen_at;

        $this->actingAs($user)->deleteJson(self::NOTICE)->assertNoContent();

        $this->assertTrue($first->equalTo($user->fresh()->analytics_notice_seen_at), 'la marca dice cuándo lo vio: no se mueve');
    }

    /** El texto viaja en el idioma del titular, que es el que la API resuelve para su petición. */
    public function test_the_text_travels_in_the_holders_language(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $this->actingAs($this->notified(['locale' => $locale]))->getJson(self::CONTEXT)
                ->assertOk()
                ->assertJsonPath('analytics_notice.text', (string) Lang::get('account.analytics_notice.text', [], $locale));
        }
    }

    /**
     * ⚠️ La semilla del montaje (`data-boot`) y el endpoint son el MISMO Resource: el aviso llega al cajón en la
     * primera pintura, sin pedirle nada a nadie, y con la misma forma que el refresco.
     */
    public function test_the_mount_seed_carries_the_same_notice(): void
    {
        $this->actingAs($this->notified());

        $seed = AccountContextSeed::forCurrentRequest();

        $this->assertSame(
            ['text' => (string) Lang::get('account.analytics_notice.text', [], 'es'), 'dismiss' => (string) Lang::get('account.analytics_notice.dismiss', [], 'es')],
            $seed['analytics_notice'] ?? null,
        );
    }

    public function test_an_anonymous_request_cannot_dismiss_anything(): void
    {
        $this->deleteJson(self::NOTICE)->assertUnauthorized();
    }

    /** Despedir el aviso no toca la oposición: son dos hechos (leer un aviso · oponerse al enlace). */
    public function test_dismissing_the_notice_does_not_change_the_opposition(): void
    {
        $user = $this->notified(['analytics_opt_out' => false]);

        $this->actingAs($user)->deleteJson(self::NOTICE)->assertNoContent();

        $this->assertFalse((bool) $user->fresh()->analytics_opt_out);
    }
}
