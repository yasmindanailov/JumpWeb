<?php

namespace App\Domain\Platform\Jobs;

use App\Domain\Platform\Services\Analytics\Drivers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * **El olvido en el driver** (`docs/specs/analitica.md` §4.3, T3a·2): cuando una cuenta retira la categoría
 * `analytics` o se anonimiza, lo que la herramienta externa sabe de ESA persona —bajo su id opaco— se borra
 * allí también. Es la mitad del art. 17 que no vive en nuestra base.
 *
 * ⚠️ En cola (`ShouldQueue`): habla con un tercero y no puede retrasar ni tumbar la petición del titular. Con
 * el driver en `none`, {@see forUser()} no crea nada. Sin las credenciales de la API (que van en `.env`, `PAY-06`:
 * `POSTHOG_PERSONAL_API_KEY` + `POSTHOG_PROJECT_ID`, o `MATOMO_TOKEN_AUTH`) se anota y no se hace nada: un
 * borrado que no puede hacerse se ve en el log, no se inventa.
 * ⚠️ El id que viaja es el HMAC de {@see Drivers::personId()}, nunca el id de la cuenta.
 */
class ForgetPersonInDriver implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $driver, public readonly string $personId) {}

    /** El job para una cuenta, con el driver ACTIVO en este momento; `null` si no hay driver. */
    public static function forUser(int $userId): ?self
    {
        $driver = Drivers::active();

        return $driver === Drivers::NONE ? null : new self($driver, Drivers::personId($userId));
    }

    public function handle(): void
    {
        match ($this->driver) {
            Drivers::POSTHOG => $this->posthog(),
            Drivers::MATOMO => $this->matomo(),
            default => Log::warning('analytics.forget_skipped', ['driver' => $this->driver, 'reason' => 'unknown driver']),
        };
    }

    /** PostHog: buscar las personas con ese `distinct_id` y borrarlas con sus eventos. */
    private function posthog(): void
    {
        $key = (string) config('services.posthog.personal_api_key');
        $project = (string) config('services.posthog.project_id');
        $host = rtrim((string) config('services.posthog.api_host'), '/');

        if ($key === '' || $project === '' || $host === '') {
            Log::warning('analytics.forget_skipped', ['driver' => Drivers::POSTHOG, 'reason' => 'no personal api key, project id or host']);

            return;
        }

        $persons = Http::withToken($key)->acceptJson()
            ->get("{$host}/api/projects/{$project}/persons/", ['distinct_id' => $this->personId])
            ->throw()
            ->json('results', []);

        foreach (is_array($persons) ? $persons : [] as $person) {
            if (! is_array($person) || ! isset($person['id'])) {
                continue;
            }

            Http::withToken($key)->acceptJson()
                ->delete("{$host}/api/projects/{$project}/persons/{$person['id']}/", ['delete_events' => 'true'])
                ->throw();
        }

        Log::info('analytics.person_forgotten', ['driver' => Drivers::POSTHOG, 'persons' => is_array($persons) ? count($persons) : 0]);
    }

    /** Matomo: sus herramientas de RGPD — localizar las visitas de ese `userId` y borrarlas. */
    private function matomo(): void
    {
        $token = (string) config('services.matomo.token_auth');
        $config = Drivers::config();

        if ($token === '' || $config === null || $config['driver'] !== Drivers::MATOMO) {
            Log::warning('analytics.forget_skipped', ['driver' => Drivers::MATOMO, 'reason' => 'no token_auth or no matomo host']);

            return;
        }

        $endpoint = $config['host'].'/index.php';
        $common = ['module' => 'API', 'format' => 'json', 'token_auth' => $token];

        $visits = Http::asForm()->post($endpoint, $common + [
            'method' => 'PrivacyManager.findDataSubjects',
            'idSite' => $config['key'],
            'segment' => 'userId=='.$this->personId,
        ])->throw()->json();

        $targets = [];
        foreach (is_array($visits) ? $visits : [] as $visit) {
            if (is_array($visit) && isset($visit['idSite'], $visit['idVisit'])) {
                $targets[] = ['idsite' => (string) $visit['idSite'], 'idvisit' => (string) $visit['idVisit']];
            }
        }

        if ($targets !== []) {
            Http::asForm()->post($endpoint, $common + ['method' => 'PrivacyManager.deleteDataSubjects', 'visits' => $targets])->throw();
        }

        Log::info('analytics.person_forgotten', ['driver' => Drivers::MATOMO, 'visits' => count($targets)]);
    }
}
