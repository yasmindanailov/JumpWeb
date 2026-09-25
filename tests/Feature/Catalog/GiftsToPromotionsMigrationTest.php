<?php

namespace Tests\Feature\Catalog;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * **Los REGALOS pasan a PROMOCIONES sin perder una línea** (`docs/specs/promociones.md` §4.4, `#770`).
 *
 * La migración `create_promotions_table` copia cada línea de `ticket_types.gifts` a una promoción `gift` de su producto
 * y retira la columna; `down()` la reconstruye. Es un dato de PRODUCCIÓN (los packs de cumpleaños y de colegio tienen
 * regalos), así que se prueba la ida y la vuelta con la forma real de la columna, las dos formas que tuvo, y la
 * posición que empareja los idiomas.
 */
class GiftsToPromotionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): Migration
    {
        return require database_path('migrations/2026_09_25_210000_create_promotions_table.php');
    }

    public function test_the_lines_pair_their_languages_by_position_and_drop_the_empty_ones(): void
    {
        $lineas = $this->migracion()::lineas(['es' => ['Cono', '  ', 'Calcetines'], 'en' => ['Cone', 'Socks']]);

        $this->assertSame([['es' => 'Cono', 'en' => 'Cone'], ['es' => 'Calcetines', 'en' => 'Socks']], $lineas);
        $this->assertSame([['es' => 'Suelto']], $this->migracion()::lineas(['es' => ' Suelto ']), 'el texto suelto (la trampa de `features`)');
        $this->assertSame([['es' => 'A'], ['es' => 'B']], $this->migracion()::lineas(['A', 'B']), 'una lista sin idiomas es español');
        $this->assertSame([], $this->migracion()::lineas(null));
    }

    public function test_up_copies_the_gifts_and_down_rebuilds_the_column(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $this->assertTrue(Schema::hasColumn('ticket_types', 'gifts'));
        $this->assertFalse(Schema::hasTable('promotions'));

        $zona = Zone::create(['name' => ['es' => 'Cumpleaños'], 'slug' => 'cumpleanos', 'accent' => 'kids', 'color' => '#C6FF3A', 'is_active' => true, 'position' => 1]);
        $pack = TicketType::create([
            'name' => ['es' => 'Pack'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zona->id, 'duration_min' => 120,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        DB::table('ticket_types')->where('id', $pack->id)->update(['gifts' => json_encode(['es' => ['Cono de chuches', 'Calcetines']], JSON_UNESCAPED_UNICODE)]);

        $migracion->up();

        $this->assertFalse(Schema::hasColumn('ticket_types', 'gifts'), 'la columna se va: los regalos viven en promociones');
        $this->assertSame(['Cono de chuches', 'Calcetines'], $pack->fresh()->giftLines(), 'las mismas líneas, en el mismo orden');

        $migracion->down();
        $this->assertSame(['es' => ['Cono de chuches', 'Calcetines']], json_decode((string) DB::table('ticket_types')->where('id', $pack->id)->value('gifts'), true));

        $migracion->up();
    }
}
