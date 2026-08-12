<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Content\Services\HeroStatus;
use App\Domain\Content\Services\LandingComplementResolver;
use App\Domain\Payments\Services\RedsysReturnOutcome;
use App\Http\Controllers\Payments\RedsysReturnController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->maybeConsumeRedsysReturn($request);

        // Atracciones por zona, con su complemento vinculado (#228) precargado para la card.
        $zones = Zone::with(['attractions' => fn ($q) => $q
            ->where('is_active', true)
            ->with('ticketType.prices.rateType')])
            ->where('show_in_landing', true)->orderBy('position')->get();

        // Resolver de comprabilidad en BATCH (una query) → la card muestra precio/CTA solo si el
        // complemento es realmente comprable en esa zona (coherencia #226).
        $complements = new LandingComplementResolver($zones->flatMap->attractions);

        // Dentro de cada zona: las atracciones con complemento COMPRABLE salen PRIMERO (decisión
        // de la clienta), el resto por su `position`.
        $zones->each(fn (Zone $zone) => $zone->setRelation('attractions', $zone->attractions
            ->sort(fn (Attraction $a, Attraction $b): int => [$complements->isPurchasable($a) ? 0 : 1, $a->position]
                <=> [$complements->isPurchasable($b) ? 0 : 1, $b->position])
            ->values()));

        return view('home', [
            'zones' => $zones,
            'complements' => $complements,
            // Chip del hero: estado de apertura en vivo (data-driven, zona horaria del parque).
            // `null` si no hay horario configurado → la vista no pinta el chip.
            'heroStatus' => app(HeroStatus::class)->current(),
            // `inOperationalZone()`: NO pintar entradas de una zona desactivada con CTA «Reservar»
            // que el flujo de compra no puede vender (espejo de packs/sidebar; Sistema 6 · W4).
            'tickets' => TicketType::with(['prices.rateType', 'addons.prices.rateType'])
                ->ofType(TicketType::TYPE_ENTRY)
                ->where('is_active', true)->inOperationalZone()->orderBy('position')->get(),
            // Cumpleaños = productos `pack` (#70/#87). El selector de la landing soporta N packs
            // por id único (#194). Cada pack muestra sus complementos (pivote) bajo la tarjeta.
            // Landing de packs: VISIBLE en la web (`is_active`) Y en venta online (`sellable()`).
            // Tras el desacople is_active⊥is_sellable (P3) la landing exige AMBOS: no anuncia un pack
            // oculto de la web ni uno no vendible (los packs no tienen fallback «Llamar» como las
            // entradas), y así el CTA «Reservar ahora» (deep-link `show-packs`) siempre tiene su
            // sección «Servicios» detrás (coherencia CTA⟺catálogo, #210).
            // Fuente ÚNICA (#256, modelo A): packs de la superficie Cumpleaños = vendibles de zona
            // operativa SIN un `LandingService` que los reubique en /servicios (idéntico en Events).
            'packages' => TicketType::birthdaySurfacePacks()
                ->with(['zone', 'prices.rateType', 'addons.prices.rateType'])->orderBy('position')->get(),
            'faqs' => Faq::where('is_active', true)->orderBy('position')->get(),
            'rules' => VenueRule::where('is_active', true)->orderBy('position')->get(),
            // /registro, /login y /recuperar-contrasena abren su modal sobre la home.
            'authModal' => match (true) {
                $request->routeIs('registro') => 'register',
                $request->routeIs('login') => 'login',
                $request->routeIs('password.request') => 'forgot',
                default => null,
            },
        ]);
    }

    /**
     * Consume el token one-shot que dejó `RedsysReturnController` en cache tras procesar
     * la vuelta del navegador (capa 5.5c, #104).
     *
     * Validaciones (seguridad ÓPTIMA):
     *  - El token debe existir en cache (TTL 5 min, un solo uso vía `Cache::pull`).
     *  - El `user_id` del token DEBE coincidir con `auth()->id()` — defensa contra que
     *    un atacante use un token capturado en una sesión distinta.
     *  - El outcome decide qué flag de sesión se escribe; `Purchase` Livewire lo lee en
     *    `mount()` y pone el sidebar en step 6 (éxito) o step 10 (KO).
     *
     * Si cualquier validación falla, se ignora silenciosamente (no se escribe sesión, no
     * se muestra error específico → no leakeamos información sobre la existencia del token).
     */
    private function maybeConsumeRedsysReturn(Request $request): void
    {
        $token = $request->query('redsys');
        if (! is_string($token) || $token === '') {
            return;
        }

        $entry = Cache::pull(RedsysReturnController::cacheKey($token));
        if (! is_array($entry) || ! isset($entry['user_id'], $entry['order_code'], $entry['outcome'])) {
            return;
        }

        // El token está atado al user_id propietario del pedido. Si no estás logueado o
        // eres otro usuario, no se aplica nada (ya consumimos el token con Cache::pull
        // → no es reusable; reduce ventana de ataque).
        if ($request->user() === null || (int) $request->user()->id !== (int) $entry['user_id']) {
            return;
        }

        $outcome = RedsysReturnOutcome::tryFrom((string) $entry['outcome']);
        if ($outcome === null) {
            return;
        }

        if ($outcome->isSuccess()) {
            session(['purchase.confirmed_code' => (string) $entry['order_code']]);
        } elseif ($outcome->isClientFailure()) {
            session(['purchase.failed_code' => (string) $entry['order_code']]);
        }
    }
}
