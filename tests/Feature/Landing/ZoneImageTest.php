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

    // ⚠️ **AQUÍ HABÍA `test_gallery_shows_real_photos_as_fallback_when_no_social_feed` Y SE HA
    // RETIRADO CON SU SUJETO** (`#309`): la sección «En directo» —la marquesina de polaroids que
    // hacía de respaldo cuando no había feed configurado— la retiró el owner. No se re-apunta
    // contra otra pantalla porque no hay otra que muestre esas fotos: reescribirlo así vigilaría
    // algo distinto de lo que motivó el caso.

    public function test_seeder_assigns_real_zone_images_and_counts(): void
    {
        $jump = Zone::where('slug', 'jump')->firstOrFail();
        $kids = Zone::where('slug', 'kids')->firstOrFail();

        $this->assertSame('images/attractions/park_jump.webp', $jump->image);
        $this->assertSame('images/attractions/kids_zone.webp', $kids->image);
        // Jump: 15 atracciones tras quitar las 2 con nombre repetido (Tobogán de bolas y Circuito de
        // obstáculos, que se conservan en Kids) — decisión clienta 2026-06-13.
        //
        // ⚠️⚠️ **Este caso comparaba DOS FUENTES del mismo número** —la columna `rides_count`, escrita
        // a mano en el panel, y el recuento real de atracciones— y en `#669` se queda con la segunda:
        // la columna se retiró (F5 · T4, `#639`·D2). *Que hiciera falta compararlas era el síntoma*:
        // el día que alguien añadiera una atracción sin subir el contador, la landing y el panel
        // habrían dicho cosas distintas. Hoy solo hay un número y lo calcula quien lo publica.
        $this->assertSame(15, $jump->attractions()->count());
        $this->assertSame(8, $kids->attractions()->count());
    }

    /**
     * **Ninguna atracción apunta a una foto que no está** — y está escrito al revés a propósito.
     *
     * ❗❗ **Antes decía «toda atracción sembrada TIENE foto real»** (`assertSame(0, whereNull(...))`), y
     * eso dejó de ser cierto en `#663`, cuando el material gráfico del cliente salió del repo: el
     * seeder guarda `null` si el fichero no está (`LandingContentSeeder`, `file_exists`), así que en
     * una máquina sin el paquete instalado las 23 salían nulas y el caso **se ponía rojo sin que nada
     * estuviera mal**. Es exactamente la trampa de `paquete-de-instancia.md` §4.5: *verde en el
     * ordenador que tiene el material y rojo en el otro, que es la peor forma de romper algo.*
     *
     * ▶ Se parte por lo que AFIRMA (§4.5.bis). Lo que el PRODUCTO puede vigilar en cualquier máquina
     * es que **no se guarde una ruta muerta**: si hay material, cada ruta apunta a un fichero que
     * existe —que es donde un typo de la clienta (`tobogan_Bolas`, `tobganes`) muerde de verdad—; y
     * si no lo hay, todas son `null` y no hay ninguna ruta que mentir.
     * ▶ La otra mitad —«esta instalación TIENE sus fotos»— es de la INSTALACIÓN y se mira con los
     * ojos: `INSTALACION-CLIENTE.md` §7.
     */
    public function test_no_seeded_attraction_points_at_a_photo_that_is_not_there(): void
    {
        $conFoto = Attraction::whereNotNull('image')->get();

        foreach ($conFoto as $ride) {
            $this->assertFileExists(
                public_path((string) $ride->image),
                "la atracción «{$ride->tr('name')}» apunta a una foto que no está: el nombre del seeder y el del fichero no cuadran",
            );
        }

        $first = Attraction::where('zone_id', Zone::where('slug', 'jump')->value('id'))
            ->where('position', 1)->firstOrFail();
        $this->assertSame('Saltos libres', $first->tr('name'));

        // ⚠️ La RUTA solo se puede afirmar donde el fichero está: sin él, el seeder guarda `null` a
        // propósito. Con material instalado, esto sigue fijando el nombre exacto.
        if ($first->image !== null) {
            $this->assertSame('images/attractions/jump_saltos_libres.webp', $first->image);
        }
    }
}
