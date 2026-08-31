<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Exceptions\OverlappingAgeRangeException;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **T6 de mixtos — el guardián de solapes vive en el DOMINIO** (`cumple-mixto.md` §26, el hueco G
 * de `#284`).
 *
 * Hasta la T6, «los tramos de una familia no pueden solaparse» solo lo aplicaba el formulario del
 * panel (`InteractsWithCatalogForm`, con su red en `CatalogGuestAgeFamilyTest`): una semilla, un
 * comando o un `save()` desde tinker podían crear dos tramos que se pisan sin que nada avisara —
 * «por construcción es imposible» era verdad únicamente dentro del panel. Desde la T6 la regla
 * corre en el `saving` del propio modelo, y esta clase la ejercita POR LA PUERTA DE ATRÁS: cada
 * caso escribe con Eloquent directo, sin form.
 *
 * ⚠️ El límite queda dicho donde se ve: los eventos de Eloquent NO cubren un
 * `Query\Builder::update()` ni SQL crudo — por eso el caso del solape PREEXISTENTE se fabrica con
 * `saveQuietly()`, que es exactamente esa clase de bypass.
 */
class AgeFamilyRangeDomainGuardTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños']]);
    }

    /** @param array<string, mixed> $overrides */
    private function pack(array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => 'Pack '.bin2hex(random_bytes(3))],
            'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id,
            'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6,
        ], $overrides));
    }

    /**
     * Guarda A: crear por Eloquent DIRECTO un tramo que pisa a su hermano revienta — sin form por
     * medio, que es todo el punto de la T6. Mutación que la valida: quitar el hook de `saving`.
     */
    public function test_a_direct_create_with_an_overlapping_range_is_refused(): void
    {
        $this->pack();

        $this->expectException(OverlappingAgeRangeException::class);

        $this->pack(['guest_age_min' => 5, 'guest_age_max' => 10]);
    }

    /** Guarda B: mover el tramo de un pack EXISTENTE sobre su hermano también revienta. */
    public function test_moving_a_range_onto_a_sibling_is_refused(): void
    {
        $this->pack();
        $jump = $this->pack(['guest_age_min' => 7, 'guest_age_max' => 99]);

        $this->expectException(OverlappingAgeRangeException::class);

        $jump->guest_age_min = 6;
        $jump->save();
    }

    /**
     * Guarda C: una fila con un solape PREEXISTENTE (metido por la puerta de atrás) sigue editable
     * en lo que NO son sus tramos — bloquearla dejaría el catálogo ingobernable; tocar SUS tramos
     * exige sanearla. Mutación que la valida: quitar el dirty-check (validar siempre).
     */
    public function test_a_pre_existing_overlap_row_can_still_edit_unrelated_fields(): void
    {
        $this->pack();
        $rogue = new TicketType([
            'name' => ['es' => 'Colado por detrás'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_age_family' => 'cumple', 'guest_age_min' => 5, 'guest_age_max' => 10,
        ]);
        $rogue->saveQuietly();

        $rogue->refresh();
        $rogue->duration_min = 120;
        $rogue->save();

        $this->assertSame(120, (int) $rogue->fresh()->duration_min);

        // …pero tocar SUS tramos sí exige sanearla:
        $this->expectException(OverlappingAgeRangeException::class);
        $rogue->guest_age_max = 12;
        $rogue->save();
    }

    /** Guarda D (controles): contiguos y otra familia NO chocan, y editarse a sí mismo tampoco. */
    public function test_contiguous_ranges_other_families_and_self_edits_pass(): void
    {
        $kids = $this->pack();
        $this->pack(['guest_age_min' => 7, 'guest_age_max' => 99]);
        $this->pack(['guest_age_family' => 'otra-familia', 'guest_age_min' => 1, 'guest_age_max' => 6]);

        $kids->guest_age_max = 5;
        $kids->save();

        $this->assertSame(5, (int) $kids->fresh()->guest_age_max);
    }

    /** Guarda E: el tramo invertido también revienta a nivel dominio. */
    public function test_an_inverted_range_is_refused_at_the_domain_level(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->pack(['guest_age_min' => 9, 'guest_age_max' => 3]);
    }

    /** Guarda F (control): sin familia, o fuera de un pack, el guardián no valida nada. */
    public function test_without_a_family_or_outside_a_pack_nothing_is_validated(): void
    {
        $this->pack();

        $free = $this->pack(['guest_age_family' => null, 'guest_age_min' => 5, 'guest_age_max' => 10]);
        $entry = $this->pack(['type' => TicketType::TYPE_ENTRY, 'guest_age_family' => 'cumple', 'guest_age_min' => 5, 'guest_age_max' => 10]);

        $this->assertTrue($free->exists);
        $this->assertTrue($entry->exists);
    }
}
