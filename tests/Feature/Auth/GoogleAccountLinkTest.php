<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Platform\Models\AuditLog;
use App\Notifications\SocialIdentityLinked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\DrivesGoogleAuth;
use Tests\TestCase;

/**
 * **VINCULAR Google a la cuenta en la que ya se está** (`specs/auth-con-google.md` §21.3, `#347`).
 *
 * Es la CUARTA puerta y no entra por `SocialLogin::enter()`, porque no es entrar. Hasta hoy no
 * existía, y su ausencia tenía consecuencia: un titular identificado que pasara por `/auth/google`
 * **cambiaba de cuenta** si su Google resolvía a otra (§18.6 lo avisaba). Lo que faltaba era la
 * INTENCIÓN, no una comprobación más.
 *
 * ⚠️⚠️ **Lo que aquí se vigila no es que vincule: es lo que NO hace.** No autentica, no promueve a
 * verificado, no expulsa y no cambia de sesión. Cada una de esas cuatro cosas la hace `enter()` con
 * razón, y hacerlas aquí convertiría un gesto de la cuenta en un cambio de cuenta encubierto.
 *
 * Los casos conducen el flujo de verdad —piden la ida, leen el `state` del redirect y vuelven con
 * él—: la intención y el titular viven en el reto del SERVIDOR, así que sembrar la sesión a mano
 * dejaría sin probar justo la pieza que impide que el vínculo aterrice en la cuenta equivocada.
 */
class GoogleAccountLinkTest extends TestCase
{
    use DrivesGoogleAuth, RefreshDatabase;

    private const OTHER_SUB = '110000000000000000999';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'customer'], ['label' => 'Cliente']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La puerta
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Sin sesión no hay nada que vincular: lo impone el middleware, no una comprobación de más. */
    public function test_the_link_route_needs_a_session(): void
    {
        $this->configureGoogleKeys();

        $this->get(route('auth.google.link'))->assertRedirect(route('login'));
    }

    /** Y sin las dos claves, 404 — el mismo hueco que falla hacia invisible que las otras dos rutas. */
    public function test_the_link_route_is_404_without_keys(): void
    {
        $this->actingAs($this->holder());

        $this->get(route('auth.google.link'))->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El caso normal
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_it_links_google_to_the_account_you_are_already_in(): void
    {
        Notification::fake();

        $holder = $this->holder();
        $this->actingAs($holder);

        $this->linkWithGoogle()->assertRedirect(route('account'))->assertSessionHas('status', 'google-linked');

        $identity = UserIdentity::query()->where('user_id', $holder->id)->firstOrFail();

        $this->assertSame('google', $identity->provider);
        $this->assertSame(self::GOOGLE_SUB, $identity->provider_id);
        $this->assertSame(self::GOOGLE_EMAIL, $identity->email_at_link);

        // ⚠️ **`account` y no `login`**: es el único emisor de esta vía, y hasta hoy la constante
        // estaba declarada sin nadie que la escribiera — vocabulario muerto.
        $this->assertSame(UserIdentity::VIA_ACCOUNT, $identity->linked_via);

        // El aviso por correo es la MITAD que hace segura esta puerta sin pedir la contraseña: es la
        // señal de que alguien con una sesión robada acaba de plantar una llave.
        Notification::assertSentTo($holder, SocialIdentityLinked::class);

        $this->assertDatabaseHas('audit_logs', ['action' => 'identities.linked', 'target_id' => $holder->id]);
    }

    /**
     * ⚠️⚠️ **El correo de Google puede ser OTRO, y se admite.** La clave es el `sub` (§6.1) y aquí el
     * titular se ha identificado él mismo, que es prueba más fuerte que la coincidencia de correo en
     * la que se apoya el vínculo automático de §5.2.
     */
    public function test_the_google_address_may_differ_from_the_account_one(): void
    {
        Notification::fake();

        $holder = $this->holder('otra.direccion@example.com');
        $this->actingAs($holder);

        $this->linkWithGoogle()->assertSessionHas('status', 'google-linked');

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $holder->id,
            'email_at_link' => self::GOOGLE_EMAIL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que NO hace, que es donde vive el riesgo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **NO promueve a verificado, y no es un olvido.** La toma de `enter()` (P12) existe porque
     * allí la única prueba es el CORREO y hay que desalojar a quien pudiera estar dentro. Aquí quien
     * pide el vínculo ya está dentro y demostrado; tocar `email_verified_at` sería afirmar algo sobre
     * un buzón que nadie ha comprobado — la doctrina de `#336`: se acredita a la PERSONA, nunca al
     * BUZÓN.
     */
    public function test_linking_does_not_verify_the_accounts_mailbox_nor_evict_anyone(): void
    {
        Notification::fake();

        $holder = $this->holder();
        $holder->forceFill(['email_verified_at' => null, 'password' => Hash::make('la-de-siempre')])->save();
        $this->actingAs($holder);

        $this->linkWithGoogle()->assertSessionHas('status', 'google-linked');

        $holder->refresh();

        $this->assertNull($holder->email_verified_at, 'vincular ha dado por verificado un buzón que nadie ha comprobado');
        $this->assertTrue(Hash::check('la-de-siempre', $holder->password), 'vincular ha invalidado la contraseña del titular');
    }

    /**
     * ⚠️⚠️ **EL CASO QUE MÁS PROTEGE: la llave es de otro y NO se cambia de sesión.**
     *
     * Quien pide «vincula mi Google a ESTA cuenta» no está pidiendo cambiar de cuenta. Si el `sub`
     * resolviera a otro titular y le abriéramos su sesión, el gesto de vincular sería un cambio de
     * cuenta encubierto — y en un dispositivo compartido eso es entrar en la cuenta de otro.
     */
    public function test_a_google_account_that_belongs_to_someone_else_is_refused_without_switching_session(): void
    {
        Notification::fake();

        $other = $this->holder('otro.titular@example.com');
        UserIdentity::create([
            'user_id' => $other->id,
            'provider' => 'google',
            'provider_id' => self::GOOGLE_SUB,
            'email_at_link' => self::GOOGLE_EMAIL,
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now(),
        ]);

        $holder = $this->holder();
        $this->actingAs($holder);

        $this->linkWithGoogle()->assertSessionHas('status', 'google-provider-taken');

        $this->assertSame($holder->id, Auth::id(), 'vincular ha cambiado la sesión a la cuenta del otro titular');
        $this->assertDatabaseMissing('user_identities', ['user_id' => $holder->id]);
        $this->assertSame(1, UserIdentity::query()->count());
        Notification::assertNothingSent();
    }

    /**
     * ⚠️ El ESPEJO del anterior: mi cuenta ya tiene OTRA llave de Google. `UNIQUE(user_id, provider)`
     * es una cuenta, una llave por proveedor (`#342`), y la salida que se le ofrece es distinta —aquí
     * sí puede desvincular la suya—, por eso el motivo no es el mismo.
     */
    public function test_an_account_that_already_has_another_google_key_is_refused_with_its_own_reason(): void
    {
        Notification::fake();

        $holder = $this->holder();
        UserIdentity::create([
            'user_id' => $holder->id,
            'provider' => 'google',
            'provider_id' => self::OTHER_SUB,
            'email_at_link' => 'la.primera@example.com',
            'linked_via' => UserIdentity::VIA_LOGIN,
            'linked_at' => now(),
        ]);

        $this->actingAs($holder);

        $this->linkWithGoogle()->assertSessionHas('status', 'google-provider-conflict');

        $this->assertSame(self::OTHER_SUB, UserIdentity::query()->where('user_id', $holder->id)->value('provider_id'));
        Notification::assertNothingSent();
    }

    /** Volver a vincular la MISMA cuenta es idempotente y no manda un segundo correo diciendo lo mismo. */
    public function test_linking_the_same_google_account_twice_is_idempotent_and_silent(): void
    {
        Notification::fake();

        $holder = $this->holder();
        $this->actingAs($holder);

        $this->linkWithGoogle()->assertSessionHas('status', 'google-linked');
        Notification::assertSentTimes(SocialIdentityLinked::class, 1);

        $this->linkWithGoogle()->assertSessionHas('status', 'google-already-linked');

        $this->assertSame(1, UserIdentity::query()->where('user_id', $holder->id)->count());
        Notification::assertSentTimes(SocialIdentityLinked::class, 1);
    }

    /**
     * ⚠️⚠️ **Entre la ida y la vuelta caben un `logout` y un `login` con otra cuenta**, y en un
     * dispositivo compartido eso es lo normal. El reto anota QUIÉN pidió la vinculación; sin esa
     * comprobación el vínculo aterrizaría en la cuenta equivocada **sin que nada fallara**.
     */
    public function test_the_link_lands_nowhere_if_the_session_changed_in_the_middle(): void
    {
        Notification::fake();

        $holder = $this->holder();
        $someoneElse = $this->holder('el.siguiente@example.com');

        $this->actingAs($holder);
        $this->configureGoogleKeys();
        $flow = $this->startGoogleLinkFlow();

        // Se cambia de sesión SIN tocar el reto, que es exactamente lo que pasa en un dispositivo
        // compartido: la sesión sigue viva y el reto sigue dentro.
        $this->actingAs($someoneElse);
        $this->fakeGoogleExchange($flow);

        $this->returnFromGoogle($flow)->assertSessionHas('status', 'google-link-session-changed');

        $this->assertSame(0, UserIdentity::query()->count());
        Notification::assertNothingSent();
    }

    /**
     * La guarda dura de `email_verified` se conserva aunque aquí el correo no identifique a nadie:
     * `email_at_link` se guarda como PRUEBA de con qué dirección se vinculó, y guardar como prueba
     * una dirección que el proveedor no da por buena es guardar una prueba falsa.
     *
     * ⚠️ Con su CONTROL: el mismo recorrido sin esa manipulación SÍ vincula, o el caso no distinguiría
     * «la guarda funciona» de «esto no vincula nunca».
     */
    public function test_an_unverified_google_address_is_never_linked(): void
    {
        Notification::fake();

        $holder = $this->holder();
        $this->actingAs($holder);

        $this->linkWithGoogle(['email_verified' => false])->assertSessionHas('status', 'google-email-unverified');
        $this->assertSame(0, UserIdentity::query()->count());

        // CONTROL.
        $this->linkWithGoogle()->assertSessionHas('status', 'google-linked');
        $this->assertSame(1, UserIdentity::query()->count());
    }

    /**
     * ⚠️ **Y la ida de VINCULAR no puede resolverse como la de ENTRAR.** Si la intención no viajara en
     * el reto, la vuelta caería en `enter()` y —con el `sub` de otro titular— **abriría su sesión**.
     * Este caso lo mira por el lado del rastro: la vía escrita es `account`, no `login`.
     */
    public function test_the_intent_travels_in_the_challenge_and_not_in_the_return_url(): void
    {
        Notification::fake();

        $holder = $this->holder();
        $this->actingAs($holder);

        $this->linkWithGoogle();

        $this->assertSame(UserIdentity::VIA_ACCOUNT, UserIdentity::query()->value('linked_via'));

        $log = AuditLog::query()->where('action', 'identities.linked')->firstOrFail();
        $this->assertSame(UserIdentity::VIA_ACCOUNT, $log->payload['via'] ?? null);
        $this->assertFalse($log->payload['promoted'] ?? true);
    }

    /**
     * **La ida de vincular viaja SOLO con sesión, y solo si la instalación ofrece Google** (`#347`).
     *
     * ⚠️⚠️ Es la mitad que hace que el botón exista o no, y se comprueba en las TRES direcciones que
     * puede fallar: sin claves, sin sesión y con las dos cosas. Las dos primeras son lo que evita
     * pintar un botón que lleva a un 404 o a la pantalla de login — y son también la PODA: quien no
     * ha entrado no paga sus bytes.
     */
    public function test_the_link_url_travels_only_with_a_session_and_with_keys(): void
    {
        $holder = $this->holder();

        // Sin claves: ni la de entrar ni la de vincular.
        $this->actingAs($holder);
        $this->assertArrayNotHasKey('google_link', $this->bootUrls());

        $this->configureGoogleKeys();

        // Con claves y SIN sesión: viaja la de entrar y NO la de vincular.
        // ⚠️ El `logout()` va DESPUÉS de lo que necesita sesión: la primera versión de este caso lo
        // hacía en medio y dejaba anónima la comprobación final, que salía roja con el producto sano.
        $signedIn = $this->bootUrls();
        $this->assertSame(route('auth.google.redirect'), $signedIn['google'] ?? null);
        $this->assertSame(route('auth.google.link'), $signedIn['google_link'] ?? null);

        auth()->logout();
        $anonymous = $this->bootUrls();
        $this->assertSame(route('auth.google.redirect'), $anonymous['google'] ?? null);
        $this->assertArrayNotHasKey('google_link', $anonymous);
    }

    /**
     * El `data-boot` del montaje, en su parte de rutas.
     *
     * @return array<string, mixed>
     */
    private function bootUrls(): array
    {
        $html = $this->get('/')->getContent();

        preg_match('/id="sidecart-spa"[^>]*data-boot="([^"]*)"/', (string) $html, $m);

        $boot = json_decode(html_entity_decode($m[1] ?? '{}', ENT_QUOTES), true);

        return is_array($boot['urls'] ?? null) ? $boot['urls'] : [];
    }

    private function holder(string $email = 'titular@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make('la-de-siempre'),
        ]);

        $user->roles()->attach(Role::where('name', 'customer')->value('id'));

        return $user;
    }
}
