<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;
use Tests\Support\DrivesGoogleAuth;

/**
 * **La pantalla que completa un alta con Google** (`docs/specs/auth-con-google.md` §7, tanda T2).
 *
 * ⚠️⚠️ **Los casos conducen el retorno de Google de verdad** (`DrivesGoogleAuth`): piden la ida, leen
 * el reto del propio redirect y vuelven con él, así que el perfil llega a la sesión por donde llega
 * en producción. Sembrar `auth.google.profile` con `withSession()` habría dejado sin probar la
 * custodia entre las dos peticiones —que es la defensa entera de esta pantalla— y un renombrado de
 * esa clave habría pasado en verde.
 *
 * ⚠️ Hereda de `ApiTestCase` **a propósito**: así cada respuesta se valida contra `openapi/v1.yaml`.
 * Con `Tests\TestCase` esto sería un test de texto, que es la trampa que `waiver-por-reserva.md` §8.1
 * dejó escrita.
 */
class GoogleSignupTest extends ApiTestCase
{
    use DrivesGoogleAuth;

    private const PENDING = self::ROOT.'/auth/google/pending';

    private const COMPLETE = self::ROOT.'/auth/google/complete';

    // ── El hueco por instalación ──────────────────────────────────────────────────────────────

    public function test_without_keys_the_two_endpoints_do_not_exist(): void
    {
        $this->fromDrawer('get', self::PENDING)->assertNotFound();
        $this->fromDrawer('post', self::COMPLETE, [])->assertNotFound();
    }

    // ── GET pending ───────────────────────────────────────────────────────────────────────────

    public function test_it_publishes_the_name_google_suggests_and_the_verified_email(): void
    {
        $this->arriveFromGoogle();

        $response = $this->fromDrawer('get', self::PENDING);

        $response->assertOk()->assertValidResponse(200);
        $this->assertSame(self::GOOGLE_EMAIL, $response->json('email'));
        $this->assertSame('Ana Pérez', $response->json('name'));
    }

    /** Sin nada esperando es un 404, no un error: significa «vuelve a empezar». */
    public function test_without_a_pending_profile_it_is_a_404(): void
    {
        $this->configureGoogleKeys();

        $this->fromDrawer('get', self::PENDING)->assertNotFound()->assertValidResponse(404);
    }

    /** Mirar NO consume: la pantalla puede repintarse sin perder la identidad que espera. */
    public function test_looking_does_not_consume_the_profile(): void
    {
        $this->arriveFromGoogle();

        $this->fromDrawer('get', self::PENDING)->assertOk();
        $this->fromDrawer('get', self::PENDING)->assertOk();
    }

    // ── POST complete ─────────────────────────────────────────────────────────────────────────

    public function test_it_creates_the_account_with_everything_a_customer_of_the_password_signup_gets(): void
    {
        Role::create(['name' => 'customer', 'label' => 'Cliente']);
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, [
            'name' => 'Ana Pérez Gómez',
        ])->assertCreated()->assertValidRequest()->assertValidResponse(201);

        $user = User::where('email', self::GOOGLE_EMAIL)->firstOrFail();

        $this->assertSame('Ana Pérez Gómez', $user->name, 'el nombre es EDITABLE: Google a veces devuelve «Ana G.»');
        $this->assertNotNull($user->email_verified_at, 'Google acredita el buzón (`[DECIDIDO owner]` Q1)');
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse((bool) $user->marketing_opt_in, 'el marketing no se pide aquí (art. 7.4)');

        // ⚠️⚠️ **Nace SIN teléfono y SIN condiciones aceptadas, y ése es el estado correcto**
        // (T8·c, `#350`): las dos las reclama el checkout, que es el momento del contrato. Un
        // `assertNull` en vez de no aseverar nada porque lo que se afirma es que el alta NO las
        // inventa — escribir una fila `terms` aquí indultaría a la cuenta (ver `AuthRegistrationTest`).
        $this->assertNull($user->phone);
        $this->assertNull($user->terms_accepted_at);

        // La PRUEBA del art. 5.2, igual que en el alta con contraseña: la casilla desaparece, el
        // rastro no.
        $this->assertEqualsCanonicalizing(['privacy'], $user->consents()->pluck('type')->all());

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => self::GOOGLE_SUB,
            'linked_via' => UserIdentity::VIA_SIGNUP,
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * ⚠️⚠️ **La contraseña nace INSERVIBLE y esto lo comprueba de verdad.** Nadie la conoce —ni
     * siquiera nosotros—, así que la cuenta no se puede tomar por esa puerta; quien quiera una la pide
     * con «he olvidado mi contraseña», que es lo que hace que esta cuenta no dependa de Google.
     */
    public function test_the_account_is_born_with_an_unusable_password(): void
    {
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, ['name' => 'Ana'])->assertCreated();

        $user = User::where('email', self::GOOGLE_EMAIL)->firstOrFail();

        foreach (['', 'password', '12345678', self::GOOGLE_EMAIL] as $guess) {
            $this->assertFalse(Hash::check($guess, (string) $user->password));
        }
    }

    /**
     * **La firma del descargo, en el acto** (§5.3·5) — que es lo que esta pantalla existe para
     * conseguir: que nadie llegue al parque sin haberlo aceptado él mismo.
     */
    public function test_with_a_published_waiver_the_signature_is_written_in_the_same_go(): void
    {
        $version = $this->publishWaiver();
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, [
            'name' => 'Ana',
            'accept_waiver' => true,
            'waiver_document_id' => $version->getKey(),
        ])->assertCreated()->assertValidRequest();

        $user = User::where('email', self::GOOGLE_EMAIL)->firstOrFail();

        $this->assertDatabaseHas('waiver_signatures', [
            'user_id' => $user->id,
            'legal_document_version_id' => $version->getKey(),
            'subject_type' => WaiverSignature::SUBJECT_HOLDER,
            'channel' => WaiverSignature::CHANNEL_WEB,
        ]);
    }

    /**
     * Y sin aceptarlo no hay cuenta: con texto publicado, la casilla es condición del alta.
     *
     * ⚠️ **Se asevera el CAMPO, no solo el 422**, y lo enseñó la mutación: sin acotar, este caso
     * pasaba en verde con la regla de la casilla relajada — porque el 422 lo disparaba igualmente la
     * del identificador del texto. *Un test que solo mira el código de estado no dice qué guarda
     * funciona.*
     */
    public function test_with_a_published_waiver_the_signup_refuses_without_the_box(): void
    {
        $this->publishWaiver();
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, ['name' => 'Ana'])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.fields.accept_waiver.0', __('api.register.waiver_required'));

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * ⚠️ **El texto republicado entre servirlo y aceptarlo da 409 y NO crea la cuenta.** El cliente
     * relee y vuelve a presentarlo: aceptar sin saber qué se firma no vale nada.
     */
    public function test_an_old_waiver_id_is_a_409_and_creates_nothing(): void
    {
        $old = $this->publishWaiver();
        $this->publishWaiver();   // republicar: el `id` de arriba deja de ser el vigente
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, [
            'name' => 'Ana',
            'accept_waiver' => true,
            'waiver_document_id' => $old->getKey(),
        ])->assertStatus(409)->assertValidResponse(409);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * ⚠️⚠️ **La T8·c dio la vuelta a este caso**: decía «el teléfono y las condiciones son
     * obligatorios» y ahora **ninguno de los dos se pide aquí**. Lo único obligatorio es el nombre,
     * que es el dato que viaja a la reserva y a la firma del descargo.
     */
    public function test_only_the_name_is_required(): void
    {
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, [])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.fields.name.0', 'El campo Nombre y apellidos es obligatorio.');

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * ⚠️ **Y el nombre en blanco tampoco pasa**, que es la forma en la que llega de verdad: el campo
     * viaja siempre desde la pantalla, así que el caso real no es omitirlo sino vaciarlo.
     */
    public function test_a_blank_name_is_refused(): void
    {
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, ['name' => '   '])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonStructure(['error' => ['fields' => ['name']]]);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * ⚠️⚠️ **El correo NO se acepta de la petición, y este caso lo demuestra de la única forma que
     * vale: mandándolo.** El contrato lo rechaza por esquema (`additionalProperties: false`) y, si
     * algún día alguien lo aflojara, la cuenta seguiría naciendo con el correo de la SESIÓN.
     */
    public function test_an_email_sent_in_the_body_is_never_used(): void
    {
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, [
            'name' => 'Ana',
            'email' => 'victima@example.com',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'victima@example.com']);
        $this->assertDatabaseHas('users', ['email' => self::GOOGLE_EMAIL]);
    }

    /** El envío CONSUME el perfil: un doble clic o una segunda pestaña no crean una segunda cuenta. */
    public function test_a_second_submit_finds_nothing_to_create(): void
    {
        $this->arriveFromGoogle();

        $this->fromDrawer('post', self::COMPLETE, ['name' => 'Ana'])->assertCreated();
        $this->fromDrawer('post', self::COMPLETE, ['name' => 'Ana'])->assertNotFound();

        $this->assertSame(1, User::query()->count());
    }

    /**
     * **La carrera que el diseño resuelve por el camino de siempre**: entre pintar la pantalla y
     * enviarla, alguien registró ese correo. No se crea una segunda cuenta —`users.email` es UNIQUE—:
     * se resuelve la identidad con el MISMO servicio que el retorno, se vincula y se ENTRA.
     */
    public function test_if_the_email_got_registered_meanwhile_it_links_and_enters_instead_of_creating(): void
    {
        $this->arriveFromGoogle();

        $existing = User::factory()->create(['email' => self::GOOGLE_EMAIL, 'email_verified_at' => now()]);

        $this->fromDrawer('post', self::COMPLETE, ['name' => 'Ana'])->assertCreated();

        $this->assertSame(1, User::query()->count(), 'no puede nacer una segunda cuenta con el mismo correo');
        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertDatabaseHas('user_identities', ['user_id' => $existing->id, 'provider_id' => self::GOOGLE_SUB]);
    }

    // ── Utillaje ──────────────────────────────────────────────────────────────────────────────

    /**
     * Petición **como la haría el cajón**: con `Origin` de un dominio declarado *stateful*.
     *
     * ⚠️ Sin esa cabecera, `EnsureFrontendRequestsAreStateful` no monta la sesión y los dos endpoints
     * responden **400** — que es su conducta correcta y la que prueba el caso de abajo—. Aquí interesa
     * la otra rama: la del navegador que sí puede tener sesión.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fromDrawer(string $method, string $path, array $payload = []): TestResponse
    {
        $request = $this->withHeader('Origin', (string) config('app.url'));

        return $method === 'get' ? $request->getJson($path) : $request->postJson($path, $payload);
    }

    /** Deja una identidad de Google VERIFICADA esperando en la sesión, por el camino de producción. */
    private function arriveFromGoogle(): void
    {
        $this->enterWithGoogle()->assertRedirect(route('registro.google'));
    }

    private function publishWaiver(): LegalDocumentVersion
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        Setting::flushMemo();

        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Descargo', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }
}
