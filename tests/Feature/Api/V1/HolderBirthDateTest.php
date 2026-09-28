<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SelfSignup;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;
use Tests\Support\DrivesGoogleAuth;

/**
 * **La fecha de nacimiento del TITULAR** (TP·1, `DECISIONES #792`, `specs/analitica-para-decidir.md` §4.14): entera y
 * opcional en las puertas de la API —el alta con correo, la pantalla tras Google y Mi cuenta—, servida en `GET /me` y en
 * el export del art. 20, con UNA política (`Identity\Services\BirthDatePolicy`). El mostrador tiene sus casos en
 * `RegisterCustomerActionTest`, y la purga el censo de `AnonymizeCoversEveryUserColumnTest`.
 *
 * ⚠️ Los avisos van escritos A MANO: una aserción con `__('clave')` pasa también con la clave vacía (`#734`).
 */
class HolderBirthDateTest extends ApiTestCase
{
    use DrivesGoogleAuth;

    private const MINOR = 'La cuenta es para mayores de edad (18 años o más).';

    private const FUTURE = 'La fecha de nacimiento no puede ser futura.';

    private const IMPLAUSIBLE = 'Revisa el año: esa fecha dice más de 120 años.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();
        RateLimiter::clear('register:127.0.0.1');
    }

    // ── El alta con correo ────────────────────────────────────────────────────────────────────

    public function test_the_signup_keeps_the_date_and_me_serves_it(): void
    {
        $this->register('fecha@jumpweb.test', ['born_on' => '1990-05-17'])->assertCreated()->assertValidRequest();

        $user = User::where('email', 'fecha@jumpweb.test')->firstOrFail();
        $this->assertSame('1990-05-17', $user->born_on?->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me')
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('born_on', '1990-05-17');
    }

    public function test_without_a_date_the_account_is_born_without_one(): void
    {
        $this->register('sin-fecha@jumpweb.test')->assertCreated();
        $this->register('vacia@jumpweb.test', ['born_on' => ''])->assertCreated();

        $this->assertNull(User::where('email', 'sin-fecha@jumpweb.test')->firstOrFail()->born_on);
        $this->assertNull(User::where('email', 'vacia@jumpweb.test')->firstOrFail()->born_on, 'una cadena vacía no es una fecha');
    }

    /** Sin fecha, `GET /me` la sirve como `null` —el contrato la declara anulable— y no la omite. */
    public function test_me_serves_a_missing_date_as_null(): void
    {
        $this->actingAs($this->holder())->getJson(self::ROOT.'/me')
            ->assertOk()->assertValidResponse(200)->assertJsonPath('born_on', null);
    }

    public function test_a_minor_date_is_refused_and_creates_nothing(): void
    {
        $tenYearsAgo = CarbonImmutable::now('Europe/Madrid')->subYears(10)->toDateString();

        $this->register('menor@jumpweb.test', ['born_on' => $tenYearsAgo])
            ->assertStatus(422)->assertValidResponse(422)
            ->assertJsonPath('error.fields.born_on.0', self::MINOR);

        $this->assertDatabaseMissing('users', ['email' => 'menor@jumpweb.test']);
    }

    public function test_a_future_date_and_a_typo_year_are_refused_with_their_own_words(): void
    {
        $tomorrow = CarbonImmutable::now('Europe/Madrid')->addDay()->toDateString();

        $this->register('futura@jumpweb.test', ['born_on' => $tomorrow])
            ->assertStatus(422)->assertJsonPath('error.fields.born_on.0', self::FUTURE);
        $this->register('errata@jumpweb.test', ['born_on' => '0198-03-12'])
            ->assertStatus(422)->assertJsonPath('error.fields.born_on.0', self::IMPLAUSIBLE);
        $this->register('formato@jumpweb.test', ['born_on' => '12/03/1998'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['born_on']]]);

        $this->assertSame(0, User::whereIn('email', ['futura@jumpweb.test', 'errata@jumpweb.test', 'formato@jumpweb.test'])->count());
    }

    /**
     * ⚠️ **El día del 18.º cumpleaños ya vale, y ese día es el del PARQUE.** A las 00:30 de Madrid todavía es la víspera en
     * UTC: con el reloj del contenedor, quien cumple hoy saldría con 17 y se le negaría la cuenta el día de su cumpleaños.
     */
    public function test_the_eighteenth_birthday_counts_from_the_park_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 22:30:00', 'UTC'));   // 29-09 a las 00:30 en Madrid

        $this->register('cumple-hoy@jumpweb.test', ['born_on' => '2008-09-29'])->assertCreated();
        $this->register('cumple-manana@jumpweb.test', ['born_on' => '2008-09-30'])
            ->assertStatus(422)->assertJsonPath('error.fields.born_on.0', self::MINOR);

        $this->assertSame('2008-09-29', User::where('email', 'cumple-hoy@jumpweb.test')->firstOrFail()->born_on?->toDateString());
        $this->assertSame(18, User::where('email', 'cumple-hoy@jumpweb.test')->firstOrFail()->age());
    }

    // ── La pantalla tras Google ───────────────────────────────────────────────────────────────

    public function test_the_google_signup_takes_the_date_and_refuses_a_minor_one(): void
    {
        $this->enterWithGoogle()->assertRedirect(route('registro.google'));

        $minor = CarbonImmutable::now('Europe/Madrid')->subYears(12)->toDateString();
        $this->fromDrawer(self::ROOT.'/auth/google/complete', ['name' => 'Ana Google', 'born_on' => $minor])
            ->assertStatus(422)->assertJsonPath('error.fields.born_on.0', self::MINOR);
        $this->assertDatabaseMissing('users', ['email' => self::GOOGLE_EMAIL]);

        // El 422 no consume el perfil: la misma pantalla, corregida, crea la cuenta.
        $this->fromDrawer(self::ROOT.'/auth/google/complete', ['name' => 'Ana Google', 'born_on' => '1987-02-14'])
            ->assertCreated()->assertValidRequest();

        $this->assertSame('1987-02-14', User::where('email', self::GOOGLE_EMAIL)->firstOrFail()->born_on?->toDateString());
    }

    // ── Mi cuenta ─────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **AUSENTE no cambia nada, `null` la borra.** La isla guarda Mi cuenta con `{name, phone, locale, email}`
     * (`isla/cuenta/useAjustesCuenta.js`): si la ausencia borrase, cada guardado suyo se llevaría la fecha por delante.
     */
    public function test_the_profile_sets_keeps_when_absent_and_clears_with_null(): void
    {
        $user = $this->holder();

        $this->actingAs($user)->patchJson(self::ROOT.'/me', $this->profile($user, ['born_on' => '1985-01-02']))
            ->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertJsonPath('born_on', '1985-01-02');

        // La forma de la isla: sin la clave.
        $this->actingAs($user)->patchJson(self::ROOT.'/me', $this->profile($user, ['name' => 'Titular Nuevo']))
            ->assertOk()->assertJsonPath('born_on', '1985-01-02')->assertJsonPath('name', 'Titular Nuevo');
        $this->assertSame('1985-01-02', $user->fresh()->born_on?->toDateString());

        $this->actingAs($user)->patchJson(self::ROOT.'/me', $this->profile($user, ['born_on' => null]))
            ->assertOk()->assertValidRequest()->assertJsonPath('born_on', null);
        $this->assertNull($user->fresh()->born_on);
    }

    public function test_the_profile_refuses_a_minor_date_and_keeps_the_one_it_had(): void
    {
        $user = $this->holder();
        $user->forceFill(['born_on' => '1985-01-02'])->save();

        $this->actingAs($user)
            ->patchJson(self::ROOT.'/me', $this->profile($user, ['born_on' => CarbonImmutable::now('Europe/Madrid')->subYears(5)->toDateString()]))
            ->assertStatus(422)->assertJsonPath('error.fields.born_on.0', self::MINOR);

        $this->assertSame('1985-01-02', $user->fresh()->born_on?->toDateString());
    }

    // ── El export del art. 20 ─────────────────────────────────────────────────────────────────

    public function test_the_export_carries_the_date(): void
    {
        $user = $this->holder();
        $user->forceFill(['born_on' => '1979-11-03'])->save();

        $this->actingAs($user)->getJson(self::ROOT.'/me/export')
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('profile.born_on', '1979-11-03');
    }

    // ── Andamios ──────────────────────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $extra */
    private function register(string $email, array $extra = []): TestResponse
    {
        RateLimiter::clear('register-email:'.SelfSignup::emailHash($email));

        return $this->withHeader('Origin', (string) config('app.url'))->postJson(self::ROOT.'/auth/register', [
            'name' => 'Ana Pérez',
            'email' => $email,
            'password' => 'un-secreto-muy-largo-2026',
        ] + $extra);
    }

    /** @param  array<string, mixed>  $payload */
    private function fromDrawer(string $path, array $payload): TestResponse
    {
        return $this->withHeader('Origin', (string) config('app.url'))->postJson($path, $payload);
    }

    private function holder(): User
    {
        return User::factory()->create(['email' => 'titular.fecha@ejemplo.test', 'phone' => '600111222', 'locale' => 'es']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profile(User $user, array $overrides = []): array
    {
        return array_merge(['name' => $user->name, 'phone' => $user->phone, 'locale' => $user->locale, 'email' => $user->email], $overrides);
    }
}
