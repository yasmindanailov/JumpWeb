<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * **El interruptor de la INVITACIÓN DIGITAL y lo que cambia en el embudo** (T4·3 de
 * `docs/specs/celebracion-e-invitacion.md` §4.8 D15 y D12; `DECISIONES #575`).
 *
 * Lo que vigila:
 *
 *  1. **Que la combinación imposible no se pueda guardar**, y no solo que el formulario no la ofrezca:
 *     la autoridad es el guard del modelo, porque los seeders, una importación y un `update()` a mano
 *     escriben por debajo del panel.
 *  2. **Que el embudo deje de preguntar por el justificante** cuando hay invitación — en las DOS
 *     puertas, el cajón y el mostrador, que leen el mismo predicado.
 *  3. **Que `show_in_invitation` atraviese las CUATRO puertas** que hay entre el formulario del panel y
 *     la fila del pivote: una que falte se cae **en silencio** (`#413` §4.7·ter).
 *  4. ⚠️ Y que `OrderCreator` **siga leyendo el modo real**: esto es una oferta, no un permiso (`#400`).
 */
class InvitationCatalogTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1 · El guard del DOMINIO, que es la autoridad ────────────────────────

    public function test_a_pack_with_a_name_column_can_offer_it(): void
    {
        $pack = $this->pack(['guest_invitation' => true]);

        $this->assertTrue($pack->fresh()->offersGuestInvitation());
    }

    public function test_a_product_that_always_needs_the_waiver_cannot_offer_it(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no hay nada que preguntar');

        $this->pack(['guest_invitation' => true, 'guardian_authorization' => TicketType::GUARDIAN_REQUIRED]);
    }

    public function test_an_entry_cannot_offer_it_because_an_invitation_is_a_party(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('solo un pack');

        $this->pack(['guest_invitation' => true, 'type' => TicketType::TYPE_ENTRY]);
    }

    /** Sin columna de nombre no hay con qué emparejar lo que conteste un padre. */
    public function test_a_pack_without_a_name_column_cannot_offer_it(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columna de NOMBRE');

        $this->pack(['guest_invitation' => true, 'guest_fields' => [
            ['key' => 'edad', 'type' => 'age', 'required' => false, 'label' => ['es' => 'Edad']],
        ]]);
    }

    /**
     * ⚠️⚠️ **Y el guard alcanza a una fila que YA existía.** Es el caso que justifica que viva en el
     * modelo: una importación o un `update()` a mano no pasan por el formulario del panel.
     */
    public function test_the_guard_also_catches_an_existing_row_being_switched_on(): void
    {
        $pack = $this->pack(['guardian_authorization' => TicketType::GUARDIAN_REQUIRED]);

        $this->expectException(InvalidArgumentException::class);
        $pack->forceFill(['guest_invitation' => true])->save();
    }

    /**
     * ⚠️⚠️ **El cinturón sobre el tirante, y hay que ejercerlo aparte.** El guard de `saving()` impide
     * guardar la combinación **desde el modelo**, así que el `isPack()` de `offersGuestInvitation()` no
     * llega a probarse nunca… salvo con una fila que entre **POR DEBAJO**: un `DB::table()->update()`,
     * una importación, una migración de datos. Ésas existen y no pasan por Eloquent.
     *
     * ▶ Lo enseñó el arnés de mutación: quitar ese `isPack()` **no mataba ningún caso**, porque ninguno
     * llegaba a ejercerlo. Es el mismo patrón que `AddonStageTest` ya tenía con su fila forzada.
     */
    public function test_a_row_forced_past_the_guard_is_never_offered(): void
    {
        $entry = $this->pack([
            'type' => TicketType::TYPE_ENTRY,
            'guardian_authorization' => TicketType::GUARDIAN_OPTIONAL,
        ]);
        DB::table('ticket_types')->where('id', $entry->id)->update(['guest_invitation' => true]);

        $forced = TicketType::findOrFail($entry->id);

        $this->assertTrue((bool) $forced->guest_invitation, 'la columna SÍ quedó encendida por debajo del modelo');
        $this->assertFalse($forced->offersGuestInvitation(), 'pero el producto no la ofrece: no es una fiesta');
        $this->assertSame(
            TicketType::GUARDIAN_OPTIONAL, $forced->funnelGuardianMode(),
            'y el embudo sigue preguntando por el justificante: una fila forzada no apaga nada',
        );
        $this->assertTrue($forced->offersGuardianInFunnel());
    }

    // ─── 2 · El EMBUDO deja de preguntar ──────────────────────────────────────

    public function test_with_an_invitation_the_funnel_stops_asking_for_the_waiver(): void
    {
        $pack = $this->pack([
            'guest_invitation' => true,
            'guardian_authorization' => TicketType::GUARDIAN_OPTIONAL,
        ])->fresh();

        $this->assertSame(TicketType::GUARDIAN_OPTIONAL, $pack->guardianMode(), 'el modo REAL no cambia');
        $this->assertSame(TicketType::GUARDIAN_NONE, $pack->funnelGuardianMode(), 'lo que cambia es lo que se OFRECE');
        $this->assertFalse($pack->offersGuardianInFunnel());
        // ⚠️ `OrderCreator` lee el modo real: ofrecer ≠ permitir (`#400`).
        $this->assertTrue($pack->offersGuardianAuthorization(), 'el predicado del DOMINIO no se toca');
    }

    public function test_without_an_invitation_the_funnel_asks_exactly_as_before(): void
    {
        $pack = $this->pack(['guardian_authorization' => TicketType::GUARDIAN_OPTIONAL])->fresh();

        $this->assertSame(TicketType::GUARDIAN_OPTIONAL, $pack->funnelGuardianMode());
        $this->assertTrue($pack->offersGuardianInFunnel());
    }

    /** La puerta del CAJÓN: el catálogo publica el modo del embudo, no el del producto. */
    public function test_the_drawer_receives_the_funnel_mode(): void
    {
        $withInvitation = $this->pack([
            'guest_invitation' => true,
            'guardian_authorization' => TicketType::GUARDIAN_OPTIONAL,
        ]);
        $plain = $this->pack(['guardian_authorization' => TicketType::GUARDIAN_OPTIONAL]);

        $this->getJson('/api/v1/catalog/products/'.$withInvitation->id)
            ->assertOk()
            ->assertJsonPath('guardian_authorization', TicketType::GUARDIAN_NONE);

        $this->getJson('/api/v1/catalog/products/'.$plain->id)
            ->assertOk()
            ->assertJsonPath('guardian_authorization', TicketType::GUARDIAN_OPTIONAL);
    }

    // ─── 3 · La columna nueva atraviesa las CUATRO puertas ────────────────────

    /**
     * ❗❗ **Las cuatro, y cada una calla al olvidarse** (`#413` §4.7·ter): la lista de columnas del
     * pivote (o no se escribe al enganchar), el saneo (o no se escribe al configurar), la precarga del
     * formulario (o **se apaga sola** al tocar cualquier otro campo) y el campo del formulario (o no
     * hay nada que marcar). `AddonStageTest` ya vigila la simetría saneo ↔ precarga; esto cubre las
     * otras dos y ata la columna concreta.
     */
    public function test_the_invitation_flag_crosses_all_four_gates_of_the_pivot(): void
    {
        $this->assertContains('show_in_invitation', TicketType::ADDON_PIVOT_COLUMNS, '1ª puerta: se caería al enganchar');

        $source = (string) file_get_contents(app_path('Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php'));

        $this->assertStringContainsString("'show_in_invitation' => (bool) (\$data['show_in_invitation'] ?? false)", $source, '2ª puerta: el saneo no lo escribiría');
        $this->assertStringContainsString("'show_in_invitation' => \$record->pivot?->showsInInvitation()", $source, '3ª puerta: se apagaría sola al tocar otro campo');
        $this->assertStringContainsString("Toggle::make('show_in_invitation')", $source, '4ª puerta: no habría nada que marcar');
    }

    /** Y el pivote la lee como un booleano de verdad en los dos motores. */
    public function test_the_pivot_casts_the_flag_as_a_boolean(): void
    {
        $pack = $this->pack();
        $addon = TicketType::create([
            'type' => TicketType::TYPE_ADDON, 'name' => ['es' => 'Menú 1'],
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $pack->configurableAddons()->attach($addon->id, ['position' => 1, 'show_in_invitation' => true]);

        $pivot = $pack->fresh()->configurableAddons()->first()->pivot;

        $this->assertTrue($pivot->show_in_invitation);
        $this->assertDatabaseHas('product_addons', [
            'product_id' => $pack->id, 'addon_id' => $addon->id, 'show_in_invitation' => true,
        ]);
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     *
     * ⚠️ **La `RateType` no es adorno**, y costó un 404: sin ninguna tarifa en base de datos
     * `RateResolver::for()` lanza, el catálogo no puede describir el producto y
     * `GET catalog/products/{id}` responde **404** — no 200 con otro contenido. Es la misma trampa que
     * `ModuleContractsTest::sellableProduct()` dejó escrita: *un fixture que basta para una superficie
     * puede no bastar para otra.*
     */
    private function pack(array $overrides = []): TicketType
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        RateType::firstOrCreate(
            ['key' => RateType::KEY_NORMAL],
            ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
        );

        return TicketType::create(array_merge([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
        ], $overrides));
    }
}
