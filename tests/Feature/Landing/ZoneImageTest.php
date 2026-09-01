<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Imagen de zona + galería con fotos reales (feature 2026-06-11).
 *
 *  - La card de zona usa el patrón «Foto integrada en la tarjeta» cuando la zona tiene `image`,
 *    y cae al diseño actual (`zone-intro__card`) cuando no.
 *  - La galería (fallback del feed social) muestra fotos reales en las polaroids.
 *  - El seeder asigna las fotos reales a zonas y atracciones, con `rides_count` coherente.
 */
class ZoneImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /* ⚠️⚠️ **AQUÍ VIVÍAN LOS DOS CASOS DE LA TARJETA DE ZONA Y SE FUERON CON SU SUJETO** (`#302`,
       `[DECIDIDO owner, 2026-08-31]`: las tarjetas de zona, fuera). Comprobaban las dos ramas del
       bucle —`zone-photo-card` cuando la zona traía `image`, `zone-intro__card` cuando no—.

       ❗ **Y dejan una consecuencia que NO es del test: `zones.image` se ha quedado SIN NINGÚN
       consumidor en la web.** El panel sigue ofreciendo el campo, el seeder sigue asignando las
       fotos y la portada ya no las pinta en ninguna parte. Ficha en `docs/DEUDA.md`; los casos del
       SEEDER de más abajo siguen vivos y siguen exigiendo que esas fotos existan en disco, así que
       el dato no se degrada en silencio mientras se decide qué hacer con él. */

    public function test_gallery_shows_real_photos_as_fallback_when_no_social_feed(): void
    {
        // Sin feed social configurado (seed por defecto), la galería muestra las polaroids con
        // las fotos reales sobrantes. `cumple_2` solo se usa en la galería (no como atracción).
        $this->get('/')->assertOk()
            ->assertSee('gallery-marquee', false)
            ->assertSee('images/attractions/cumple_2.webp', false);
    }

    public function test_seeder_assigns_real_zone_images_and_counts(): void
    {
        $jump = Zone::where('slug', 'jump')->firstOrFail();
        $kids = Zone::where('slug', 'kids')->firstOrFail();

        $this->assertSame('images/attractions/park_jump.webp', $jump->image);
        $this->assertSame('images/attractions/kids_zone.webp', $kids->image);
        // Jump: 15 atracciones tras quitar las 2 con nombre repetido (Tobogán de bolas y Circuito de
        // obstáculos, que se conservan en Kids) — decisión clienta 2026-06-13.
        $this->assertSame(15, $jump->rides_count);
        $this->assertSame(8, $kids->rides_count);
        $this->assertSame(15, $jump->attractions()->count());
        $this->assertSame(8, $kids->attractions()->count());
    }

    public function test_every_seeded_attraction_has_an_existing_real_photo(): void
    {
        // Blindaje empírico: cada atracción sembrada apunta a un fichero que EXISTE en public/
        // (el seeder pone null si falta). Caza un desajuste entre el nombre del seeder y el
        // fichero real (mayúsculas/typos de la clienta como `tobogan_Bolas`, `tobganes`).
        $this->assertSame(0, Attraction::whereNull('image')->count(), 'toda atracción sembrada tiene foto real');

        $first = Attraction::where('zone_id', Zone::where('slug', 'jump')->value('id'))
            ->where('position', 1)->firstOrFail();
        $this->assertSame('images/attractions/jump_saltos_libres.webp', $first->image);
        $this->assertSame('Saltos libres', $first->tr('name'));
    }
}
