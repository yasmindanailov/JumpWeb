<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ZoneCards;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Content\Services\LandingComplementResolver;
use App\Domain\Payments\Services\RedsysReturnOutcome;
use App\Http\Controllers\Payments\RedsysReturnController;
use App\Http\Sidebar\AccountDoor;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Http\Request;

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

        // Las entradas activas de zona operativa, que alimentan a la vez la sección de tarifas y el
        // sello de precio de las tarjetas de zona. Se resuelven UNA vez.
        $entradas = TicketType::with(['prices.rateType', 'addons.prices.rateType'])
            ->ofType(TicketType::TYPE_ENTRY)
            ->where('is_active', true)->inOperationalZone()->orderBy('position')->get();

        return view('home', [
            'zones' => $zones,
            'complements' => $complements,
            /*
             * Las tarjetas de «Para quién» (`#478`). Se componen en el dominio y no en la vista:
             * cruzar zonas con entradas, elegir la más barata y redactar la regla de altura es
             * lógica, y en Blade se convierte en seis copias de la misma regla.
             */
            'zoneCards' => (new ZoneCards)->compose($zones, $entradas),
            // ⚠️ **`heroStatus` se fue al payload compartido en `#230`** y por eso ya no está aquí:
            // su consumidor dejó de ser el chip del hero —que `#226` retiró— y pasó a ser el bloque
            // de datos del MENÚ, que vive en las doce vistas. Calcularlo también aquí sería
            // ejecutar el mismo servicio dos veces en la misma petición.
            // `inOperationalZone()`: NO pintar entradas de una zona desactivada con CTA «Reservar»
            // que el flujo de compra no puede vender (espejo de packs/sidebar; Sistema 6 · W4).
            // ⚠️ La MISMA colección que alimenta las tarjetas de zona: se resolvía aquí y volver a
            // consultarla para el sello de precio habría sido la misma consulta dos veces por
            // petición, con el riesgo de que las dos secciones ofrecieran precios distintos.
            'tickets' => $entradas,
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
            // ⚠️⚠️ **`/registro`, `/login` y `/recuperar-contrasena` ya NO abren un modal**
            // (`specs/auth-en-cajon.md` §4.4, 2026-08-23): son PUERTAS que sirven la home y abren el
            // cajón en su zona de auth, exactamente como `/entradas` y `/mi-cuenta/…`. El mapa
            // ruta→zona vive entero en `Http\Sidebar\AccountDoor` y lo consume el layout.
            //
            // ⚠️ **Lo único que había que rescatar del prop que desaparece es el `<meta robots>`.** El
            // `noindex` de estas tres URL era un efecto lateral de `authModal` —el mismo valor decidía
            // si se pintaba el modal y si se indexaba—, así que sin esta línea se habrían quedado
            // indexables sin que nada fallara. Lo fija `SeoTest::test_the_auth_doors_are_never_indexable`.
            'noindex' => AccountDoor::isAuthDoor(),
        ]);
    }

    /**
     * Consume el token one-shot que dejó `RedsysReturnController` en cache tras procesar
     * la vuelta del navegador (capa 5.5c, #104).
     *
     * Validaciones (seguridad ÓPTIMA):
     *  - El token debe existir en cache (TTL 5 min, un solo uso).
     *  - El `user_id` del token DEBE coincidir con `auth()->id()` — defensa contra que
     *    un atacante use un token capturado en una sesión distinta.
     *  - El outcome decide qué flag de sesión se escribe; `Purchase` Livewire lo lee en
     *    `mount()` y pone el sidebar en step 6 (éxito) o step 10 (KO).
     *
     * Si cualquier validación falla, se ignora silenciosamente (no se escribe sesión, no
     * se muestra error específico → no leakeamos información sobre la existencia del token).
     *
     * ⚠️ **Se MIRA antes de consumir, y ese orden importa** (Fase 3 · paso 4d). Antes se hacía
     * `Cache::pull` en la primera línea y se validaba después, buscando que un token capturado no
     * fuera reutilizable. El efecto real era el contrario de lo buscado: como el token está atado a
     * su `user_id`, un tercero **no podía usarlo pero sí QUEMARLO** — bastaba con abrir la URL de la
     * vuelta sin sesión para que su dueño legítimo perdiera la confirmación y se quedara mirando un
     * carrito vacío tras haber pagado. Ahora se lee con `get`, se comprueba la titularidad y solo se
     * consume (`pull`) cuando de verdad se va a aplicar. La ventana de reutilización sigue cerrada
     * —el `pull` es atómico y el token vive 5 minutos— y deja de haber una forma trivial de
     * estropearle la vuelta a otro.
     */
    private function maybeConsumeRedsysReturn(Request $request): void
    {
        $token = $request->query('redsys');
        if (! is_string($token) || $token === '') {
            return;
        }

        $key = RedsysReturnController::cacheKey($token);
        // ⚠️ El pase NO vive en la caché por defecto (`DECISIONES #137`): con Redis y `allkeys-lru`
        // podría desalojarse justo en el pico de reservas. Quién lo guarda decide dónde, y aquí solo
        // se le pregunta — la clave y el store salen los dos del mismo sitio.
        $handoff = RedsysReturnController::handoff();

        $entry = $handoff->get($key);
        if (! is_array($entry) || ! isset($entry['user_id'], $entry['order_code'], $entry['outcome'])) {
            return;
        }

        // El token está atado al user_id propietario del pedido. Si no estás logueado o eres otro
        // usuario, no se aplica nada Y NO SE CONSUME: el dueño legítimo todavía puede usarlo.
        if ($request->user() === null || (int) $request->user()->id !== (int) $entry['user_id']) {
            return;
        }

        $outcome = RedsysReturnOutcome::tryFrom((string) $entry['outcome']);
        if ($outcome === null) {
            return;
        }

        // Un solo uso: se consume aquí, cuando ya se sabe que va a aplicarse.
        $handoff->pull($key);

        if ($outcome->isSuccess()) {
            SidebarEntry::confirmed((string) $entry['order_code']);
        } elseif ($outcome->isClientFailure()) {
            SidebarEntry::failed((string) $entry['order_code']);
        }
    }
}
