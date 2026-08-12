<?php

namespace Tests\Feature\Api;

use App\Domain\Identity\Models\User;
use App\Http\Api\ApiSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — negociación de idioma en `/api/v1` (spec §4.7).
 *
 * La regla que estos tests fijan, y que es la diferencia con `SetLocale` (web): la API **lee** la
 * sesión pero **nunca la escribe**. Escribirla convertiría cada petición de un cliente sin sesión
 * en estado de servidor nuevo, y ese cliente —el móvil de Fase 6— no tiene dónde guardarlo.
 *
 * ⚠️ `Tests\TestCase::setUp()` vacía `Accept-Language` en cada test (el cliente HTTP de Laravel
 * envía `en-us` por defecto y todo el sitio acabaría en inglés). Por eso cada caso de negociación
 * pone la cabecera EXPLÍCITAMENTE: un test que no la ponga está probando el fallback, no la
 * negociación (spec §6.8).
 */
class ApiLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/'.ApiSurface::PREFIX;

    protected function setUp(): void
    {
        parent::setUp();

        // Devuelve el idioma REALMENTE resuelto: más preciso que inferirlo de un texto traducido,
        // que podría coincidir por casualidad entre dos idiomas.
        Route::middleware('api')->prefix(ApiSurface::PREFIX)
            ->get('__test/locale', static fn (): array => ['locale' => app()->getLocale()]);
    }

    public function test_it_negotiates_the_language_from_accept_language(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->getJson(self::ROOT.'/__test/locale')
            ->assertJsonPath('locale', 'fr');
    }

    public function test_it_falls_back_to_the_site_language_without_the_header(): void
    {
        $this->getJson(self::ROOT.'/__test/locale')
            ->assertJsonPath('locale', config('app.locale'));
    }

    /** Un idioma que el sitio no habla no se acepta a medias: se cae al de la instalación. */
    public function test_an_unsupported_language_falls_back(): void
    {
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->getJson(self::ROOT.'/__test/locale')
            ->assertJsonPath('locale', config('app.locale'));
    }

    /** Cliente que no pide idioma pero tiene perfil: se respeta su preferencia guardada. */
    public function test_the_profile_preference_is_used_when_the_client_asks_for_nothing(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)
            ->getJson(self::ROOT.'/__test/locale')
            ->assertJsonPath('locale', 'fr');
    }

    /**
     * El escalón que da coherencia SSR ↔ API: la SPA comparte sesión con la web (Sanctum stateful,
     * activado aquí con un `Origin` de primera parte), así que la elección del visitante en el pie
     * de la landing también manda en los datos que pinta el sidebar. Sin esto, la misma pantalla
     * podría salir en dos idiomas.
     */
    public function test_the_visitors_choice_in_session_wins_over_the_browser(): void
    {
        $response = $this->withHeaders([
            'Origin' => config('app.url'),
            'Accept-Language' => 'en-GB,en;q=0.9',
        ])->withSession(['locale' => 'fr'])->getJson(self::ROOT.'/__test/locale');

        $response->assertJsonPath('locale', 'fr');
    }

    /**
     * Y la contrapartida: la API no CREA esa elección. Un cliente que negocia en francés no debe
     * dejar al visitante la web en francés — la elección manual es del visitante, no del cliente.
     */
    public function test_it_never_writes_the_locale_into_the_session(): void
    {
        $this->withHeaders([
            'Origin' => config('app.url'),
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])->getJson(self::ROOT.'/__test/locale')->assertJsonPath('locale', 'fr');

        $this->assertNull(Session::get('locale'), 'la API ha escrito el idioma en la sesión');
    }

    /** Extremo a extremo: el sobre de error sale en el idioma negociado, no en el del servidor. */
    public function test_error_messages_come_back_in_the_negotiated_language(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->getJson(self::ROOT.'/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.message', __('api.errors.unauthenticated', [], 'fr'));
    }
}
