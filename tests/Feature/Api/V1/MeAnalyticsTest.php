<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Jobs\ForgetPersonInDriver;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * **`PUT /me/analytics`: oponerse a que la navegación se vincule a la cuenta, o volver a permitirlo** (art. 21 y
 * 7.3, `specs/analitica.md` §4.3, T3a·3). Retirar desvincula, sella la prueba y dispara el olvido en el driver;
 * dar quita la oposición y, si la petición trae la categoría, enlaza en el acto. Sin contraseña, 204 siempre,
 * idempotente. `GET /me` publica la oposición y el export la lleva.
 */
class MeAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    private const VISITOR = '01HZX8K4N2P7Q9R3S5T6V8W0YA';

    private function holder(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** Una cuenta YA vinculada: una sesión y un hecho con su `user_id`, y la prueba viva. */
    private function linked(User $user): AnalyticsSession
    {
        $session = AnalyticsSession::query()->create([
            'visitor_id' => self::VISITOR, 'started_at' => now()->subHour(), 'last_seen_at' => now()->subHour(),
            'user_id' => $user->id, 'is_bot' => false, 'is_internal' => false, 'consent' => ['analytics' => true],
        ]);
        AnalyticsEvent::query()->create([
            'event_id' => Visitor::mint(), 'session_id' => $session->id, 'visitor_id' => self::VISITOR, 'user_id' => $user->id,
            'name' => 'page_viewed', 'occurred_at' => now()->subHour(), 'received_at' => now()->subHour(),
        ]);
        $user->consents()->create(['type' => Consent::TYPE_ANALYTICS, 'accepted_at' => now()->subHour(), 'ip' => '10.0.0.1', 'version' => CookieConsent::POLICY_VERSION]);

        return $session;
    }

    private function driver(): void
    {
        Setting::updateOrCreate(['key' => Drivers::KEY_DRIVER], ['value' => Drivers::POSTHOG, 'group' => 'analytics']);
        Setting::updateOrCreate(['key' => Drivers::KEY_POSTHOG_PROJECT], ['value' => 'phc_abcdefghijklmnopqrstuvwxyz0123', 'group' => 'analytics']);
        Setting::flushMemo();
    }

    public function test_opposing_unlinks_seals_the_proof_and_asks_the_driver_to_forget(): void
    {
        Bus::fake();
        $this->driver();
        $user = $this->holder();
        $session = $this->linked($user);

        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => false])
            ->assertNoContent()
            ->assertValidResponse(204);

        $this->assertTrue((bool) $user->fresh()->analytics_opt_out);
        $this->assertNull($session->fresh()->user_id, 'la sesión vuelve al agregado');
        $this->assertSame(0, AnalyticsEvent::query()->where('user_id', $user->id)->count());
        $proof = $user->consents()->where('type', Consent::TYPE_ANALYTICS)->sole();
        $this->assertNotNull($proof->revoked_at, 'la prueba se SELLA, no se borra');
        $this->assertNotNull($proof->accepted_at);
        Bus::assertDispatched(ForgetPersonInDriver::class, fn (ForgetPersonInDriver $job): bool => $job->driver === Drivers::POSTHOG && $job->personId === Drivers::personId((int) $user->id));
    }

    public function test_without_a_driver_no_forget_job_is_dispatched(): void
    {
        Bus::fake();
        $user = $this->holder();
        $this->linked($user);

        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => false])->assertNoContent();

        Bus::assertNotDispatched(ForgetPersonInDriver::class);
        $this->assertTrue((bool) $user->fresh()->analytics_opt_out);
    }

    public function test_allowing_again_clears_the_opposition_and_links_when_the_request_carries_the_category(): void
    {
        $user = $this->holder();
        $user->forceFill(['analytics_opt_out' => true])->save();
        $session = AnalyticsSession::query()->create([
            'visitor_id' => self::VISITOR, 'started_at' => now()->subMinutes(5), 'last_seen_at' => now()->subMinutes(5),
            'is_bot' => false, 'is_internal' => false, 'consent' => ['analytics' => true],
        ]);

        // ⚠️ `putJson` NO manda cookies sin `withCredentials()` (la trampa de la T1, medida otra vez): sin esto el
        // visitante no llega al contexto y el caso pasaría por «no se enlaza» sin haber probado nada.
        $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookie(Visitor::COOKIE, self::VISITOR)
            ->putJson(self::ROOT.'/me/analytics', ['accepted' => true])
            ->assertNoContent();

        $this->assertFalse((bool) $user->fresh()->analytics_opt_out);
        $this->assertSame($user->id, $session->fresh()->user_id, 'con la categoría en la petición, se enlaza en el acto');
        $this->assertSame(1, $user->consents()->where('type', Consent::TYPE_ANALYTICS)->whereNull('revoked_at')->count());
    }

    public function test_allowing_without_the_category_only_clears_the_opposition(): void
    {
        $user = $this->holder();
        $user->forceFill(['analytics_opt_out' => true])->save();

        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => true])->assertNoContent();

        $this->assertFalse((bool) $user->fresh()->analytics_opt_out);
        $this->assertSame(0, $user->consents()->count(), 'sin enlace no hay prueba que escribir');
    }

    public function test_asking_for_the_state_it_already_has_writes_nothing(): void
    {
        Bus::fake();
        $user = $this->holder();
        $this->linked($user);

        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => true])->assertNoContent();
        $this->assertNull($user->consents()->where('type', Consent::TYPE_ANALYTICS)->sole()->revoked_at);
        $this->assertSame(1, $user->consents()->count());

        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => false])->assertNoContent();
        $revoked = $user->consents()->where('type', Consent::TYPE_ANALYTICS)->value('revoked_at');
        $this->travel(2)->minutes();
        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => false])->assertNoContent();

        $this->assertEquals($revoked, $user->consents()->where('type', Consent::TYPE_ANALYTICS)->value('revoked_at'));
        Bus::assertDispatchedTimes(ForgetPersonInDriver::class, 0);   // sin driver no hay job; y la segunda no cambia nada
    }

    public function test_it_is_validated_needs_a_session_and_asks_for_no_password(): void
    {
        $this->putJson(self::ROOT.'/me/analytics', ['accepted' => false])->assertUnauthorized();

        $user = $this->holder();
        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', ['accepted' => 'maybe'])->assertStatus(422);
        $this->actingAs($user)->putJson(self::ROOT.'/me/analytics', [])->assertStatus(422);
    }

    public function test_the_profile_publishes_the_opposition_and_the_export_carries_it_with_the_first_attribution(): void
    {
        $user = $this->holder();
        $user->forceFill(['analytics_opt_out' => true, 'first_attribution' => ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano']])->save();

        // `UserResource` va sin envoltorio (`$wrap = null`): el campo está en la raíz, como lo lee el cajón.
        $me = $this->actingAs($user)->getJson(self::ROOT.'/me')->assertOk()->assertValidResponse(200);
        $this->assertTrue($me->json('analytics_opt_out'));

        $export = $this->actingAs($user)->getJson(self::ROOT.'/me/export')->assertOk()->assertValidResponse(200);
        $this->assertTrue($export->json('analytics.opted_out'));
        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano'], $export->json('analytics.first_attribution'));

        // Y la lista de consentimientos nombra el tipo nuevo.
        $this->linked($user);
        $consents = $this->actingAs($user)->getJson(self::ROOT.'/me/consents')->assertOk()->assertValidResponse(200);
        $this->assertSame(Consent::TYPE_ANALYTICS, $consents->json('data.0.type'));
        $this->assertSame(__('account.account.privacy.consent_types.analytics'), $consents->json('data.0.type_label'));
    }

    public function test_anonymizing_forgets_the_person_in_the_driver_too(): void
    {
        Bus::fake();
        $this->driver();
        $user = $this->holder();
        $this->linked($user);

        $this->assertTrue($user->anonymize());

        Bus::assertDispatched(ForgetPersonInDriver::class);
        $this->assertFalse((bool) $user->fresh()->analytics_opt_out);
        $this->assertNull($user->fresh()->first_attribution);
    }
}
