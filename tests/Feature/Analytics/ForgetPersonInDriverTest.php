<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Jobs\ForgetPersonInDriver;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * **El olvido en el driver** (`specs/analitica.md` §4.3, T3a·2): con PostHog busca a la persona por su id opaco
 * y la borra con sus eventos; con Matomo, sus herramientas de RGPD; sin credenciales en `.env` anota y no
 * inventa; sin driver no hay job. ⚠️ Todo con `Http::fake`: aquí no se habla con nadie.
 */
class ForgetPersonInDriverTest extends TestCase
{
    use RefreshDatabase;

    private function driver(string $driver): void
    {
        Setting::updateOrCreate(['key' => Drivers::KEY_DRIVER], ['value' => $driver, 'group' => 'analytics']);
        Setting::updateOrCreate(['key' => Drivers::KEY_POSTHOG_PROJECT], ['value' => 'phc_abcdefghijklmnopqrstuvwxyz0123', 'group' => 'analytics']);
        Setting::updateOrCreate(['key' => Drivers::KEY_MATOMO_HOST], ['value' => 'https://stats.parque.es', 'group' => 'analytics']);
        Setting::updateOrCreate(['key' => Drivers::KEY_MATOMO_SITE_ID], ['value' => '7', 'group' => 'analytics']);
        Setting::flushMemo();
    }

    public function test_without_a_driver_there_is_no_job_and_with_one_it_carries_the_opaque_id(): void
    {
        $this->assertNull(ForgetPersonInDriver::forUser(7));

        $this->driver(Drivers::POSTHOG);
        $job = ForgetPersonInDriver::forUser(7);

        $this->assertInstanceOf(ForgetPersonInDriver::class, $job);
        $this->assertSame(Drivers::POSTHOG, $job->driver);
        $this->assertSame(Drivers::personId(7), $job->personId);
        $this->assertStringNotContainsString('7', $job->personId === '7' ? '7' : '', 'nunca el id en claro');
    }

    public function test_posthog_finds_the_person_by_distinct_id_and_deletes_it_with_its_events(): void
    {
        config(['services.posthog.personal_api_key' => 'phx_secret', 'services.posthog.project_id' => '4242', 'services.posthog.api_host' => 'https://eu.posthog.com']);
        Http::fake([
            'eu.posthog.com/api/projects/4242/persons/?*' => Http::response(['results' => [['id' => 'p-1'], ['id' => 'p-2']]]),
            'eu.posthog.com/api/projects/4242/persons/*' => Http::response('', 204),
        ]);

        (new ForgetPersonInDriver(Drivers::POSTHOG, 'abc123'))->handle();

        Http::assertSentCount(3);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/api/projects/4242/persons/') && $r['distinct_id'] === 'abc123' && $r->hasHeader('Authorization', 'Bearer phx_secret'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/persons/p-1/') && $r['delete_events'] === 'true');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/persons/p-2/'));
    }

    public function test_without_the_private_credentials_it_logs_and_talks_to_nobody(): void
    {
        config(['services.posthog.personal_api_key' => null, 'services.posthog.project_id' => null]);
        Http::fake();
        Log::shouldReceive('warning')->once()->withArgs(fn (string $msg, array $ctx): bool => $msg === 'analytics.forget_skipped' && $ctx['driver'] === 'posthog');

        (new ForgetPersonInDriver(Drivers::POSTHOG, 'abc123'))->handle();

        Http::assertNothingSent();
    }

    public function test_matomo_locates_the_visits_of_the_user_id_and_deletes_them(): void
    {
        $this->driver(Drivers::MATOMO);
        config(['services.matomo.token_auth' => 'tok']);
        Http::fake([
            'stats.parque.es/index.php' => Http::sequence()
                ->push([['idSite' => 7, 'idVisit' => 100], ['idSite' => 7, 'idVisit' => 101]])
                ->push(['result' => 'success']),
        ]);

        (new ForgetPersonInDriver(Drivers::MATOMO, 'abc123'))->handle();

        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['method'] === 'PrivacyManager.findDataSubjects' && $r['segment'] === 'userId==abc123' && $r['idSite'] === '7' && $r['token_auth'] === 'tok');
        Http::assertSent(fn ($r) => $r['method'] === 'PrivacyManager.deleteDataSubjects' && $r['visits'][0]['idvisit'] === '100' && $r['visits'][1]['idvisit'] === '101');
    }

    public function test_matomo_with_nothing_to_delete_does_not_call_delete(): void
    {
        $this->driver(Drivers::MATOMO);
        config(['services.matomo.token_auth' => 'tok']);
        Http::fake(['stats.parque.es/index.php' => Http::response([])]);

        (new ForgetPersonInDriver(Drivers::MATOMO, 'abc123'))->handle();

        Http::assertSentCount(1);
    }

    public function test_a_failing_api_throws_so_the_queue_retries(): void
    {
        config(['services.posthog.personal_api_key' => 'phx_secret', 'services.posthog.project_id' => '4242', 'services.posthog.api_host' => 'https://eu.posthog.com']);
        Http::fake(['eu.posthog.com/*' => Http::response('boom', 500)]);

        $this->expectException(RequestException::class);
        (new ForgetPersonInDriver(Drivers::POSTHOG, 'abc123'))->handle();
    }
}
