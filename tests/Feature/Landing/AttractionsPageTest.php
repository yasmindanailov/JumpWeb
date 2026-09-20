<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **`/atracciones`: la CONDUCTA del producto** (`#481`; partida por lo que afirma en F5 · T2b, `#657`).
 *
 * ❗❗ **Aquí no se lee el HTML** (`#649`): el contrato con la landing de una instancia son los DATOS que
 * recibe la vista —`zones`, `total` y `active`—, y sobre ellos se afirma. Hasta la mudanza estos casos
 * leían el marcado de la página de PlayJump, que ya no está en el producto; lo que ese marcado garantiza
 * vive en la doc de la instancia (`paginas/atracciones.md`), y el anfitrión mínimo tiene su guarda propia
 * (`AnfitrionAtraccionesTest`).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene:
 *  · una zona sin atracciones activas viaja igual y la landing abre una rejilla vacía;
 *  · una atracción apagada en el panel sigue publicada;
 *  · el recuento deja de contar lo que la página enseña, y **la portada promete otra cifra** —«ver las N»
 *    contra «N atracciones»—, que es la divergencia que su propio controlador se obliga a evitar;
 *  · `?zona=` deja de elegir, o un valor tecleado a mano hace desaparecer la página con un 404.
 */
class AttractionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    /** @return array<string, mixed> Lo que el controlador le pasa a la vista: el sujeto de esta guarda. */
    private function datos(string $query = ''): array
    {
        return $this->get('/atracciones'.$query)->assertOk()->original->getData();
    }

    /** @return list<string> */
    private function slugs(iterable $zones): array
    {
        return collect($zones)->map(fn (Zone $z): string => (string) $z->slug)->values()->all();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Qué zonas viajan, y con qué dentro
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **Una zona sin atracciones NO viaja**: su pestaña abriría una rejilla vacía, que no informa de
     * nada. En esta instalación «Cumpleaños» es exactamente ese caso —es una zona de venta, no un sitio
     * con juegos que enseñar—, y la regla no es del diseño de nadie: es lo que el producto publica.
     */
    public function test_only_landing_zones_with_active_attractions_travel_to_the_view(): void
    {
        /*
         * ⚠️ **El ORDEN se mide PRIMERO, con la instalación tal cual.** Lo enseñó la mutación «las zonas
         * se ordenan al revés que el panel», que sobrevivía: apagando antes una zona quedaba UNA sola
         * viajando, y una lista de un elemento es igual del derecho que del revés.
         */
        $esperado = Zone::where('show_in_landing', true)->orderBy('position')->get()
            ->filter(fn (Zone $z): bool => $z->attractions()->where('is_active', true)->exists())
            ->pluck('slug')->values()->all();

        $this->assertGreaterThan(1, count($esperado), 'sin DOS zonas que viajen, el orden no se puede medir');
        $this->assertSame($esperado, $this->slugs($this->datos()['zones']), 'el orden no es el del panel (`zones.position`)');

        // Y lo que NO viaja: una zona sin atracciones activas, y una que el panel apagó para la landing.
        $vacia = Zone::create([
            'slug' => 'sin-juegos',
            'name' => ['es' => 'Sin juegos'],
            'accent' => 'jump',
            'position' => 9,
            'show_in_landing' => true,
        ]);
        $this->assertSame(0, $vacia->attractions()->count(), 'el caso nace sin sujeto: la zona tiene atracciones');

        $fuera = Zone::where('slug', $esperado[0])->firstOrFail();
        $fuera->forceFill(['show_in_landing' => false])->save();

        $zones = $this->datos()['zones'];

        $this->assertNotContains('sin-juegos', $this->slugs($zones), 'una zona sin atracciones activas viajó a la vista');
        $this->assertNotContains($fuera->slug, $this->slugs($zones), 'una zona apagada para la landing viajó a la vista');
        $this->assertNotEmpty($zones, 'el caso se quedó sin sujeto: no viaja ninguna zona');
    }

    /** Cada zona viaja con SUS atracciones activas, en el orden del panel. */
    public function test_each_zone_travels_with_its_active_attractions_in_panel_order(): void
    {
        $zones = $this->datos()['zones'];

        foreach ($zones as $zone) {
            $esperado = $zone->attractions()->where('is_active', true)->orderBy('position')->pluck('id')->all();

            $this->assertSame($esperado, $zone->attractions->pluck('id')->all(),
                "las atracciones de «{$zone->slug}» no viajan en el orden del panel");
        }
    }

    /** Una atracción apagada en el panel deja de publicarse: no es cosa del marcado, es del dato. */
    public function test_an_inactive_attraction_does_not_travel(): void
    {
        $ride = Attraction::where('is_active', true)->firstOrFail();

        $this->assertContains($ride->id, $this->publicadas(), 'el caso nace sin sujeto');

        $ride->update(['is_active' => false]);

        $this->assertNotContains($ride->id, $this->publicadas(), 'una atracción apagada sigue viajando a la vista');
    }

    /** @return list<int> Los ids de las atracciones que viajan, de todas las zonas. */
    private function publicadas(): array
    {
        return collect($this->datos()['zones'])->flatMap->attractions->pluck('id')->all();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El recuento, que dos superficies prometen
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **El recuento sale de lo que la página ENSEÑA, y la portada promete el mismo número.** Su
     * sección 03 dice «N atracciones:» y su puerta «Ver las N»; si las dos cifras salieran de sitios
     * distintos —`Attraction::count()` aquí, las zonas allí— el cliente cuenta y no le salen, sin que
     * nada falle. Los dos controladores lo escriben con la misma cuenta **porque tienen que escribirla**,
     * y esto lo comprueba sobre el dato de los dos.
     */
    public function test_the_total_counts_what_the_page_shows_and_matches_the_home(): void
    {
        $datos = $this->datos();
        $publicadas = collect($datos['zones'])->sum(fn (Zone $z): int => $z->attractions->count());

        $this->assertSame($publicadas, $datos['total']);
        $this->assertSame($datos['total'], $this->get('/')->assertOk()->original->getData()['ridesTotal'],
            'la portada promete un número de atracciones distinto del que esta página enseña');

        // Y no es `Attraction::count()`: una atracción apagada, o de una zona que no está en la landing,
        // no cuenta. El caso nace con sujeto porque el seeder deja al menos una fuera.
        Attraction::where('is_active', true)->firstOrFail()->update(['is_active' => false]);
        $this->assertSame($publicadas - 1, $this->datos()['total']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La zona de llegada
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **La zona de llegada viaja en la QUERY y no en el hash**, porque un hash no llega al servidor:
     * una pestaña elegida por ancla solo funciona con JavaScript. El precedente roto está al lado —`#478`
     * enlazó a `/precios#zona-<slug>` y esa página emite cero `id="zona-…"`—.
     * ⚠️ Y un valor desconocido **no es un error**: es la primera zona. Un 404 por un parámetro tecleado a
     * mano haría desaparecer la página.
     */
    public function test_the_query_picks_the_arriving_zone_and_an_unknown_value_falls_back(): void
    {
        $zones = $this->datos()['zones'];
        $primera = $zones->first();
        $segunda = $zones->get(1);

        $this->assertNotNull($segunda, 'hace falta una SEGUNDA zona o el caso no distingue elegir de caer al defecto');

        $this->assertSame($segunda->slug, $this->datos('?zona='.$segunda->slug)['active']);
        $this->assertSame($primera->slug, $this->datos('?zona=no-existe')['active'], 'un valor desconocido no cae a la primera zona');
        $this->assertSame($primera->slug, $this->datos()['active']);

        // Y una zona REAL que no viaja tampoco elige: sería una pestaña que no existe.
        $vacia = Zone::create(['slug' => 'sin-juegos', 'name' => ['es' => 'Sin juegos'], 'accent' => 'jump', 'position' => 9, 'show_in_landing' => true]);
        $this->assertSame($primera->slug, $this->datos('?zona='.$vacia->slug)['active']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La foto, que es una regla del producto desde `#657`
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **CÓMO se resuelve la ruta de una foto es del PRODUCTO, no de la landing** (`#657`): la de una
     * atracción es una ruta relativa a `public/` escrita en el panel, y el producto ofrece al lado la
     * respuesta equivocada (`TicketType::imageUrl()` antepone `uploads/`, porque aquello sí es una subida).
     * Con la vista en otro repo, sin este método cada instancia adivinaría — y `null` con la ruta vacía es
     * parte de la regla: «sin foto no se reserva hueco» necesita que el modelo diga que no hay.
     */
    public function test_the_photo_of_an_attraction_is_resolved_by_the_product(): void
    {
        // ⚠️⚠️ **El caso PONE su foto, no la busca** (`#663`). Antes tomaba la primera atracción
        // sembrada con `image` no nula, y eso dejó de existir en una máquina sin el material del
        // cliente instalado: el seeder guarda `null` cuando el fichero no está, así que el caso moría
        // con `ModelNotFoundException` **sin que nada estuviera mal**. Lo que aquí se prueba es cómo
        // el producto RESUELVE una ruta, no si esta instalación tiene sus fotos.
        $ride = Attraction::where('is_active', true)->firstOrFail();
        $ride->update(['image' => 'images/attractions/jump_saltos_libres.webp']);

        $this->assertSame(asset($ride->image), $ride->imageUrl());
        $this->assertStringStartsWith('http', (string) $ride->imageUrl(), 'la foto no sale como URL absoluta');
        $this->assertStringNotContainsString('uploads/', (string) $ride->imageUrl(), 'la foto de zona no es una subida: no lleva `uploads/`');

        $ride->update(['image' => null]);
        $this->assertNull($ride->fresh()->imageUrl());

        $ride->update(['image' => '  ']);
        $this->assertNull($ride->fresh()->imageUrl(), 'una ruta en blanco tiene que contestar `null`, no la raíz del sitio');
    }
}
