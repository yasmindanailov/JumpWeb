<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * La PUERTA de la familia y el tramo de edad (`docs/specs/cumple-mixto.md` §9·1).
 *
 * Es la configuración de la que sale el veredicto de fiesta MIXTA y, tras él, un cobro. Por eso se
 * valida al ESCRIBIR y no al leer: `GuestAgeMixReader` resuelve un solape de forma determinista
 * para no romperse, pero la respuesta correcta a «dos packs cubren la edad 7» no es elegir uno —es
 * no dejar entrar el dato. Misma doctrina que `AFORO-07`.
 *
 * ⚠️ Crear y editar tienen que decir LO MISMO: la validación vive una sola vez en el trait
 * (`InteractsWithCatalogForm::normalizeGuestAgeFields`) y estos casos la conducen por las dos
 * páginas, porque una puerta que solo cierra en el alta no es una puerta.
 */
class CatalogGuestAgeFamilyTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'cumpleanos',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function pack(string $name, ?string $family, ?int $min, ?int $max): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'tax_rate' => 21,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_age_family' => $family, 'guest_age_min' => $min, 'guest_age_max' => $max,
        ]);
    }

    /** Ejecuta la puerta como lo hace el guardado, con o sin producto en edición. */
    private function normalize(array $data, bool $isPack = true, ?TicketType $record = null): array
    {
        $page = $record !== null ? new EditCatalog : new CreateCatalog;
        if ($record !== null) {
            $page->record = $record;
        }

        $method = new ReflectionMethod($page::class, 'normalizeGuestAgeFields');
        $method->setAccessible(true);

        return $method->invoke($page, $data, $isPack);
    }

    // ─── Normalización ───────────────────────────────────────────────────────

    public function test_the_family_is_normalised_on_write(): void
    {
        // Se compara por IGUALDAD contra la de sus hermanos: si el valor guardado dependiera de
        // cómo lo escribió el operador, la conducta dependería además del motor de BD (MySQL
        // cotejaría «Cumple» y «cumple» como iguales; SQLite, donde corre la suite, no).
        $out = $this->normalize(['guest_age_family' => '  Cumple  ', 'guest_age_min' => 1, 'guest_age_max' => 6]);

        $this->assertSame('cumple', $out['guest_age_family']);
    }

    public function test_an_empty_family_is_null_not_an_empty_string(): void
    {
        $out = $this->normalize(['guest_age_family' => '', 'guest_age_min' => null, 'guest_age_max' => null]);

        $this->assertNull($out['guest_age_family'], 'vacío = este producto no distingue edades');
    }

    public function test_an_empty_bound_is_null_not_zero(): void
    {
        // «Sin tope por abajo» y «desde los 0 años» son cosas distintas, y el formulario manda ''.
        $out = $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => '', 'guest_age_max' => '6']);

        $this->assertNull($out['guest_age_min']);
        $this->assertSame(6, $out['guest_age_max']);
    }

    public function test_outside_a_pack_the_three_columns_are_wiped(): void
    {
        // Defensa en profundidad (regla 12): la sección está oculta para no-packs, pero un payload
        // manipulado no debe dejar configuración viva donde el veredicto nunca la mirará.
        $out = $this->normalize(
            ['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6],
            isPack: false,
        );

        $this->assertNull($out['guest_age_family']);
        $this->assertNull($out['guest_age_min']);
        $this->assertNull($out['guest_age_max']);
    }

    // ─── Bloqueos ────────────────────────────────────────────────────────────

    public function test_a_family_without_a_range_is_blocked(): void
    {
        $this->expectException(Halt::class);
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => null, 'guest_age_max' => null]);
    }

    public function test_an_inverted_range_is_blocked(): void
    {
        $this->expectException(Halt::class);
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 10, 'guest_age_max' => 4]);
    }

    public function test_a_range_that_overlaps_a_sibling_is_blocked(): void
    {
        $this->pack('Cumpleaños Kids', 'cumple', 1, 6);

        $this->expectException(Halt::class);
        // 5–12 pisa al 1–6: un niño de 5 tendría dos packs posibles.
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 5, 'guest_age_max' => 12]);
    }

    public function test_an_open_bound_overlaps_by_numbers_not_by_special_cases(): void
    {
        $this->pack('Cumpleaños Kids', 'cumple', null, 6); // «hasta 6»

        $this->expectException(Halt::class);
        // «de 6 en adelante» pisa al «hasta 6» en el 6 exacto — los extremos son INCLUSIVOS.
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 6, 'guest_age_max' => null]);
    }

    // ─── Lo que SÍ tiene que pasar ───────────────────────────────────────────

    public function test_contiguous_ranges_are_allowed(): void
    {
        $this->pack('Cumpleaños Kids', 'cumple', 1, 6);

        $out = $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 7, 'guest_age_max' => 99]);

        $this->assertSame('cumple', $out['guest_age_family'], '1–6 y 7–99 no se tocan: es el caso normal');
    }

    public function test_another_family_is_not_a_conflict(): void
    {
        $this->pack('Campamento', 'campamento', 1, 12);

        $out = $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6]);

        $this->assertSame(1, $out['guest_age_min']);
    }

    public function test_editing_a_pack_does_not_collide_with_itself(): void
    {
        $kids = $this->pack('Cumpleaños Kids', 'cumple', 1, 6);

        // Sin excluirse a sí mismo, guardar el pack sin tocar su tramo sería imposible.
        $out = $this->normalize(
            ['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6],
            record: $kids,
        );

        $this->assertSame(6, $out['guest_age_max']);
    }
}
