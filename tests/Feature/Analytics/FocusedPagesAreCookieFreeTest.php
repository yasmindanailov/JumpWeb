<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Http\Controllers\GuardianAuthorizationController;
use App\Http\Controllers\GuestFormController;
use App\Http\Controllers\InvitationPageController;
use App\Http\Middleware\ResolveVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * **EL INVITADO NO ES UN VISITANTE** (`docs/specs/analitica-fiesta.md` §4.1; `DECISIONES #739`): las páginas
 * enfocadas de la fiesta —el post-form, el justificante y la invitación— no acuñan la cookie de medición, no pintan
 * el banner y no cargan ningún script de tercero. Medido el 24-09 ANTES de esto: `/autorizacion/{id}` firmada
 * devolvía `Set-Cookie: visitor_id` sin un solo `<script>`, una cookie de trece meses que no medía nada.
 *
 * Tres guardas y un CONTROL: (1) por HTTP, ninguna de las cinco páginas con GET acuña ni carga; (2) por
 * estructura, TODA ruta de los tres controladores está fuera del acuñado —una ruta nueva no puede olvidarse—;
 * (3) una cookie de otra visita no llega al hecho; y el control: la portada SIGUE acuñando, así que lo que el
 * test de arriba ve es la exclusión, no un acuñado roto.
 */
class FocusedPagesAreCookieFreeTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    /** Las doce rutas de los tres controladores: GET y POST del post-form y sus tres POST de la invitación (5), GET y POST del justificante (2), y la invitación: página, respuesta, calendario, recibo y su guardado (5). */
    private const FOCUSED_ROUTES = 12;

    /**
     * @param  array{order: Order, reservation: OrderItem, invitation: PartyInvitation}  $party
     * @return array<string, string> nombre → URL de cada página enfocada que se abre con GET
     */
    private function focusedUrls(array $party): array
    {
        $reply = $this->replyOf($party['invitation'], $party['reservation']);

        return [
            'post-form' => $party['reservation']->guestFormSignedUrl(),
            'justificante' => $party['reservation']->guardianAuthorizationSignedUrl(),
            'invitación' => route(PartyInvitations::PUBLIC_ROUTE, ['token' => $party['invitation']->token]),
            'calendario' => route('invitation.calendar', ['token' => $party['invitation']->token]),
            'recibo' => app(PartyInvitations::class)->receiptUrl($reply),
        ];
    }

    /** @return list<string> */
    private function cookieNames(TestResponse $response): array
    {
        return array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies());
    }

    public function test_no_focused_page_mints_the_measurement_cookie_paints_the_banner_or_loads_a_third_party(): void
    {
        $party = $this->mountParty();

        foreach ($this->focusedUrls($party) as $name => $url) {
            $response = $this->get($url);
            $response->assertOk();

            $this->assertNotContains(Visitor::COOKIE, $this->cookieNames($response), "«{$name}» acuña la cookie del visitante");

            $html = (string) $response->getContent();
            $this->assertStringNotContainsString('data-cookie-', $html, "«{$name}» lleva el banner de cookies");
            $this->assertStringNotContainsString('data-analytics-', $html, "«{$name}» lleva el driver de análisis");

            preg_match_all('~<script[^>]*\ssrc="([^"]+)"~i', $html, $m);
            $foreign = array_values(array_filter($m[1], static fn (string $src): bool => ! str_starts_with($src, '/')
                && ! str_starts_with($src, (string) config('app.url'))
                && ! str_contains($src, 'challenges.cloudflare.com')));
            $this->assertSame([], $foreign, "«{$name}» carga un script de tercero: ".implode(' ', $foreign));
        }
    }

    /** CONTROL: si la portada dejara de acuñar, el test de arriba pasaría sin probar nada. */
    public function test_the_rest_of_the_web_still_mints_the_cookie(): void
    {
        $this->assertContains(Visitor::COOKIE, $this->cookieNames($this->get('/')), 'la portada ya no acuña `visitor_id`: el CONTROL del régimen no vale');
    }

    public function test_every_route_of_the_three_controllers_is_out_of_the_minting(): void
    {
        $mint = ResolveVisitor::class.':'.ResolveVisitor::MINT;
        $controllers = [GuestFormController::class, GuardianAuthorizationController::class, InvitationPageController::class];
        $seen = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array($route->getControllerClass(), $controllers, true)) {
                continue;
            }
            $seen++;
            $this->assertContains($mint, $route->excludedMiddleware(), "la ruta «{$route->getName()}» acuña la cookie: es una página enfocada de la fiesta");
        }

        $this->assertSame(self::FOCUSED_ROUTES, $seen, 'el censo de rutas enfocadas ha cambiado: revisa el grupo de `routes/web.php`');
    }

    public function test_a_cookie_from_another_visit_does_not_reach_a_fact_of_the_party(): void
    {
        $party = $this->mountParty();

        $this->withUnencryptedCookie(Visitor::COOKIE, Visitor::mint())
            ->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $party['invitation']->token]))
            ->assertOk();

        $fact = AnalyticsEvent::query()->where('name', 'invitation_viewed')->sole();
        $this->assertNull($fact->visitor_id, 'el hecho de la invitación se ató a la cookie de otra visita');
        $this->assertNull($fact->session_id);
        $this->assertNull($fact->user_id);
        $this->assertSame($party['order']->id, $fact->order_id);
    }
}
