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
 *
 * ▶ Entre el 2026-08-30 y el 31 aquí vivían también los siete casos del AVISO «este cambio afecta a
 * N fiestas vendidas» (`#283`). Con el SELLO de condiciones en cada reserva (§21, `#288`) un cambio
 * de tramos no mueve ninguna fiesta vendida, el aviso no tenía nada que avisar y se retiró
 * (`[DECIDIDO owner, 2026-08-31]`); lo que ahora protege esa promesa vive en `AgeFamilySealTest`.
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
    private function normalize(array $data, string $type = TicketType::TYPE_PACK, ?TicketType $record = null): array
    {
        $page = $record !== null ? new EditCatalog : new CreateCatalog;
        if ($record !== null) {
            $page->record = $record;
        }

        $method = new ReflectionMethod($page::class, 'normalizeGuestAgeFields');
        $method->setAccessible(true);

        return $method->invoke($page, $data, $type);
    }

    /** Ejecuta el saneo de esquemas del panel, que es la puerta por la que entra un `type`. */
    private function sanitizeSchema(array $rows, bool $perGuest): array
    {
        $page = new CreateCatalog;
        $method = new ReflectionMethod($page::class, $perGuest ? 'sanitizeGuestFields' : 'sanitizeEventFields');
        $method->setAccessible(true);

        return $method->invoke($page, $rows);
    }

    // ─── La EDAD solo existe en los datos POR INVITADO (§17.2·4) ────────────

    public function test_the_age_type_is_refused_in_the_event_schema(): void
    {
        // ⚠️⚠️ El `Select` del panel nunca ofrece `age` aquí, pero eso NO es una defensa: es la
        // regla 12 de este proyecto —no se confía en que el formulario oculte lo que no debe
        // llegar—. Medido antes del arreglo: forzado, persistía, y salía por
        // `GET /catalog/products/{id}` contra el `enum` cerrado de su propio contrato.
        $out = $this->sanitizeSchema([[
            'key' => 'edad_fiesta', 'type' => TicketType::FIELD_TYPE_AGE,
            'label' => ['es' => 'Edad'], 'required' => false, 'stage' => 'booking',
        ]], perGuest: false);

        $this->assertSame(TicketType::FIELD_TYPE_TEXT, $out[0]['type'], 'la edad no puede colarse en los datos del evento');
    }

    public function test_the_age_type_is_kept_in_the_guest_schema(): void
    {
        // El CONTROL: sin él, lo de arriba se cumpliría degradando el tipo en los DOS esquemas, y el
        // campo del que sale el suplemento dejaría de existir sin que nada se pusiera rojo.
        $out = $this->sanitizeSchema([[
            'key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE,
            'label' => ['es' => 'Edad'], 'required' => true,
        ]], perGuest: true);

        $this->assertSame(TicketType::FIELD_TYPE_AGE, $out[0]['type']);
    }

    public function test_the_model_also_refuses_it_when_reading_a_row_written_by_hand(): void
    {
        // La segunda puerta: una fila escrita directamente en la base —una semilla, una migración,
        // un `update()` de consola— no pasa por el panel. El modelo la sanea al leerla.
        $pack = $this->pack('Con edad en el evento', null, null, null);
        $pack->forceFill(['event_fields' => [
            ['key' => 'edad_fiesta', 'type' => TicketType::FIELD_TYPE_AGE, 'label' => ['es' => 'Edad'], 'stage' => 'booking'],
        ]])->save();

        $this->assertSame(TicketType::FIELD_TYPE_TEXT, $pack->fresh()->eventFields()[0]['type']);
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

    public function test_in_an_addon_the_three_columns_are_wiped(): void
    {
        // Defensa en profundidad (regla 12): la sección está oculta para complementos, pero un payload
        // manipulado no debe dejar configuración viva donde nadie la mirará.
        $out = $this->normalize(
            ['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6],
            type: TicketType::TYPE_ADDON,
        );

        $this->assertNull($out['guest_age_family']);
        $this->assertNull($out['guest_age_min']);
        $this->assertNull($out['guest_age_max']);
    }

    /**
     * **Una ENTRADA declara su edad, pero no tiene familia** (`#761`). La edad es lo que se DICE («de 4 a 7 años»,
     * publicado en el catálogo desde `#676`); la familia decide un veredicto de fiesta mixta que una entrada no
     * tiene, así que se sigue anulando aunque llegue en el payload.
     */
    public function test_an_entry_keeps_its_declared_age_but_never_a_family(): void
    {
        $out = $this->normalize(
            ['guest_age_family' => 'cumple', 'guest_age_min' => '4', 'guest_age_max' => '7'],
            type: TicketType::TYPE_ENTRY,
        );

        $this->assertNull($out['guest_age_family'], 'una entrada no participa en una familia de edades');
        $this->assertSame(4, $out['guest_age_min']);
        $this->assertSame(7, $out['guest_age_max']);

        $abierta = $this->normalize(['guest_age_min' => '8', 'guest_age_max' => ''], type: TicketType::TYPE_ENTRY);

        $this->assertSame(8, $abierta['guest_age_min']);
        $this->assertNull($abierta['guest_age_max'], '«desde 8 años»: sin tope por arriba, no un cero');
    }

    public function test_an_inverted_entry_age_is_blocked(): void
    {
        $this->expectException(Halt::class);

        $this->normalize(['guest_age_min' => 10, 'guest_age_max' => 4], type: TicketType::TYPE_ENTRY);
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
