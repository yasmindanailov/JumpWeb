<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\RelationManagers\AddonsRelationManager;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\AttachesPartyExtras;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F5a de `specs/fiesta-sistema-nuevo.md` (§4.11, `[DECIDIDO owner]` `#749`): EL DATO que la zona 4 de la lista necesita y
 * el catálogo no decía —para cuántas personas es un complemento, su familia, su bloque en la lista, cuántos adultos se
 * quedan, «Sin tarta» y «Guardado»—, por el panel, el dominio, la web y la API. Sin pantalla todavía (F5b).
 *
 * ⚠️ Lo que se vigila de verdad es lo que calla al romperse: las listas blancas del enganche (una clave olvidada se cae
 * sin error), el saneo del bloque (un enganche que se vende al reservar no tiene bloque de la lista), que «Guardado» no
 * mueva el TESTIGO de la página, y que la pregunta de la tarta llegue al reconciliador como cantidades y no como otra
 * cosa.
 */
class ExtrasDeLaFiestaDatoTest extends TestCase
{
    use AttachesPartyExtras;
    use MountsAParty;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_addon_view_carries_what_the_list_paints(): void
    {
        ['reservation' => $reservation] = $this->mountParty();
        $type = $reservation->ticketType;
        $this->assertNotNull($type);
        $tarta = $this->extra($type, 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE], ['serves' => 12, 'image' => 'productos/tarta.webp']);
        $combo = $this->extra($type, 'Combo café', 3900, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Combos', 'en' => 'Combos']]);
        // Un «0» colado en la columna (el panel pide al menos 1) es «no lo dice», no «para cero personas».
        $suelto = $this->extra($type, 'Piñata', 1500, [], ['serves' => 0]);

        $vistas = $this->vistas($reservation);

        $this->assertSame([12, ProductAddon::BLOCK_CAKE, ''], [$vistas[$tarta->id]->serves, $vistas[$tarta->id]->block, $vistas[$tarta->id]->family]);
        $this->assertStringEndsWith('/uploads/productos/tarta.webp', (string) $vistas[$tarta->id]->imageUrl);
        $this->assertSame([6, ProductAddon::BLOCK_ADULTS, 'Combos'], [$vistas[$combo->id]->serves, $vistas[$combo->id]->block, $vistas[$combo->id]->family]);
        // CONTROL: sin dato, sin nada — la rejilla de siempre.
        $this->assertSame([null, null, '', null], [$vistas[$suelto->id]->serves, $vistas[$suelto->id]->block, $vistas[$suelto->id]->family, $vistas[$suelto->id]->imageUrl]);
    }

    /** El bloque es PRESENTACIÓN de la venta posterior: en un enganche que se vende al reservar, o con un valor raro, no hay bloque. */
    public function test_the_block_is_read_only_on_a_post_form_attachment_and_from_the_closed_list(): void
    {
        $pack = $this->pack();
        $raro = $this->extra($pack, 'Tarta rara', 2500, ['postform_block' => 'pizza']);
        $alReservar = TicketType::create(['name' => ['es' => 'Tarta al reservar'], 'type' => TicketType::TYPE_ADDON, 'is_sellable' => true, 'is_active' => true, 'position' => 30]);
        $pack->configurableAddons()->attach($alReservar->id, ['position' => 2, 'quantity_mode' => ProductAddon::MODE_FIXED, 'postform_block' => ProductAddon::BLOCK_CAKE]);
        $bueno = $this->extra($pack, 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE]);

        $bloques = $pack->fresh()?->configurableAddons()->get()->mapWithKeys(fn (TicketType $a): array => [
            $a->id => $a->addonPivot() !== null ? $a->addonPivot()->postformBlock() : 'sin pivote',
        ])->all();
        ksort($bloques);

        $this->assertSame([$raro->id => null, $alReservar->id => null, $bueno->id => ProductAddon::BLOCK_CAKE], $bloques);
    }

    public function test_the_adults_field_is_declared_by_its_type_and_bounded(): void
    {
        $pack = $this->pack([
            ['key' => 'adultos', 'type' => TicketType::FIELD_TYPE_ADULTS, 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Adultos']],
        ]);

        $this->assertSame('adultos', $pack->adultsFieldKey());
        $this->assertTrue(TicketType::isNumericFieldType(TicketType::FIELD_TYPE_ADULTS));
        $limpio = static fn (string $v): array => $pack->sanitizeEventData(['adultos' => $v], TicketType::EVENT_STAGE_POSTFORM);
        $this->assertSame(['adultos' => '8'], $limpio('08'), 'como la edad: en dígitos y sin ceros delante');
        $this->assertSame(['adultos' => '0'], $limpio('0'), 'cero es una respuesta: no se queda ninguno');
        $this->assertSame([], $limpio('150'), 'fuera del tope, «no respondido»');
        $this->assertSame(['adultos' => '99'], $limpio('99'));

        // CONTROL: un `number` con la misma etiqueta NO es el campo de los adultos. Lo declara el esquema, no la clave.
        $otro = $this->pack([
            ['key' => 'adults_approx', 'type' => TicketType::FIELD_TYPE_NUMBER, 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Adultos']],
        ]);
        $this->assertNull($otro->adultsFieldKey());
        $this->assertSame(['adults_approx' => '150'], $otro->sanitizeEventData(['adults_approx' => '150'], TicketType::EVENT_STAGE_POSTFORM));
    }

    /**
     * «Guardado hoy a las 16:05» es lo que el TITULAR hizo —también un guardado idéntico— y NO mueve `updated_at`: es el
     * testigo de la página abierta y el token de cinco puertas del operador. Lo que guarda el parque no cuenta.
     */
    public function test_a_host_save_stamps_saved_at_without_moving_the_witness(): void
    {
        ['reservation' => $reservation, 'host' => $host] = $this->mountParty();
        $antes = PostFormAddons::versionOf($reservation);
        $this->assertNull($reservation->guest_form_saved_at);

        $this->travel(5)->minutes();
        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [])->assertRedirect();

        $fresca = $reservation->fresh();
        $this->assertNotNull($fresca?->guest_form_saved_at);
        $this->assertTrue($fresca->guest_form_saved_at->equalTo(now()->startOfSecond()) || $fresca->guest_form_saved_at->diffInSeconds(now()) < 2);
        $this->assertSame($antes, PostFormAddons::versionOf($fresca), 'guardar sin cambiar nada no deja obsoleta la página del operador');

        // Por la API, igual.
        $this->travel(5)->minutes();
        $this->putJson($reservation->guestFormApiUrls()['save'], [])->assertOk()->assertJsonPath('saved_at', now()->startOfSecond()->toIso8601String());

        // El PARQUE guarda por el titular, y no es su guardado.
        $sellado = $reservation->fresh()?->guest_form_saved_at;
        $this->travel(5)->minutes();
        $reservation->fresh(['ticketType'])?->submitGuestForm(null, null, 'panel', User::factory()->create());
        $this->assertTrue($sellado?->equalTo($reservation->fresh()?->guest_form_saved_at));
    }

    public function test_the_api_publishes_the_extras_and_takes_the_cake_answer(): void
    {
        ['reservation' => $reservation] = $this->mountParty();
        $type = $reservation->ticketType;
        $this->assertNotNull($type);
        $tarta = $this->extra($type, 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE], ['serves' => 12]);
        $this->extra($type, 'Combo café', 3900, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Combos']]);
        $urls = $reservation->guestFormApiUrls();

        $this->getJson($urls['show'])->assertOk()
            ->assertJsonPath('cake_declined', false)
            ->assertJsonPath('saved_at', null)
            ->assertJsonPath('addons.0.block', ProductAddon::BLOCK_CAKE)
            ->assertJsonPath('addons.0.serves', 12)
            ->assertJsonPath('addons.0.family', null)
            ->assertJsonPath('addons.1.block', ProductAddon::BLOCK_ADULTS)
            ->assertJsonPath('addons.1.family', 'Combos');

        // «Sin tarta».
        $this->putJson($urls['save'], ['cake_declined' => true])->assertOk()->assertJsonPath('cake_declined', true);
        $this->assertNotNull($reservation->fresh()?->cake_declined_at);

        // Elegir una tarta la deja contestada igual, y la marca se BORRA: si no, reaparecería el día que la quitaran.
        $this->putJson($urls['save'], ['addons' => [['product_id' => $tarta->id, 'quantity' => 1]]])->assertOk()->assertJsonPath('cake_declined', false);
        $this->assertNull($reservation->fresh()?->cake_declined_at);

        // Y si la marca quedara con una tarta pedida (un guardado que el reconciliador bloqueó), manda la línea: nunca se
        // publica «Sin tarta» con una tarta en el pedido.
        $reservation->fresh()?->forceFill(['cake_declined_at' => now()])->save();
        $this->getJson($urls['show'])->assertOk()->assertJsonPath('cake_declined', false);
        $reservation->fresh()?->forceFill(['cake_declined_at' => null])->save();
        $this->putJson($urls['save'], ['addons' => [['product_id' => $tarta->id, 'quantity' => 0]]])->assertOk()->assertJsonPath('cake_declined', false);
    }

    /** La web manda la PREGUNTA (`cake`, `cake_quantity`) y llega al reconciliador como cantidades. */
    public function test_the_web_cake_answer_becomes_quantities_of_the_cakes_on_sale(): void
    {
        ['reservation' => $reservation, 'host' => $host] = $this->mountParty();
        $type = $reservation->ticketType;
        $this->assertNotNull($type);
        $tarta = $this->extra($type, 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'max_qty' => 3], ['serves' => 12]);
        $traemos = $this->extra($type, 'Traemos la nuestra', 1000, ['postform_block' => ProductAddon::BLOCK_CAKE]);
        $combo = $this->extra($type, 'Combo café', 3900, ['postform_block' => ProductAddon::BLOCK_ADULTS]);
        $guardar = fn (array $datos) => $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), $datos)->assertRedirect();

        $guardar(['cake' => (string) $tarta->id]);
        $this->assertSame([$tarta->id => 1, $traemos->id => 0, $combo->id => 0], $this->cantidades($reservation));

        // «Añadir otra tarta», hasta el tope del enganche (la autoridad sigue siendo el reconciliador).
        $guardar(['cake' => (string) $tarta->id, 'cake_quantity' => '2']);
        $this->assertSame(2, $this->cantidades($reservation)[$tarta->id]);
        $guardar(['cake' => (string) $tarta->id, 'cake_quantity' => '50']);
        $this->assertSame(3, $this->cantidades($reservation)[$tarta->id]);

        // Cambiar de opción: la elegida sube y la otra se retira.
        $guardar(['cake' => (string) $traemos->id]);
        $this->assertSame([$tarta->id => 0, $traemos->id => 1, $combo->id => 0], $this->cantidades($reservation));

        // «Sin tarta»: todas a 0 y la pregunta, contestada.
        $guardar(['cake' => 'none']);
        $this->assertSame([$tarta->id => 0, $traemos->id => 0, $combo->id => 0], $this->cantidades($reservation));
        $this->assertTrue($reservation->fresh()?->cakeDeclined(array_values($this->vistas($reservation))));

        // CONTROL: un id que NO es una tarta ofrecida no compra nada (ni el combo por la puerta de la tarta).
        $guardar(['cake' => (string) $combo->id]);
        $this->assertSame([$tarta->id => 0, $traemos->id => 0, $combo->id => 0], $this->cantidades($reservation));
        $this->assertTrue($reservation->fresh()?->cakeDeclined(array_values($this->vistas($reservation))), 'y la respuesta de antes sigue');
    }

    /** Fuera de plazo la pregunta no se envía (radio `disabled`), y si llega igual, no toca nada ni avisa de un bloqueo. */
    public function test_a_closed_cake_is_left_alone(): void
    {
        ['reservation' => $reservation, 'host' => $host] = $this->mountParty();
        $type = $reservation->ticketType;
        $this->assertNotNull($type);
        // La fiesta es dentro de 12 días: un plazo de 30 días ya venció.
        $tarta = $this->extra($type, 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'postform_cutoff_hours' => 24 * 30]);

        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), ['cake' => (string) $tarta->id])
            ->assertRedirect()->assertSessionHas('status', 'guest-form-saved');

        $this->assertSame([$tarta->id => 0], $this->cantidades($reservation));
    }

    public function test_the_panel_writes_serves_family_and_the_block(): void
    {
        $pack = $this->pack();
        $combo = TicketType::create(['name' => ['es' => 'Combo café'], 'type' => TicketType::TYPE_ADDON, 'is_sellable' => true, 'is_active' => true, 'position' => 20]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $combo->id])
            ->fillForm(['serves' => '6', 'family' => ['es' => 'Combos', 'en' => 'Combos', 'fr' => 'Formules']])
            ->call('save')
            ->assertHasNoFormErrors();
        $fresco = $combo->fresh();
        $this->assertSame(6, $fresco?->peopleServed());
        $this->assertSame('Formules', $fresco->tr('family', 'fr'));

        // El enganche: se engancha como venta posterior con su bloque, y tocar otra cosa NO lo borra (tercera lista blanca).
        $gestor = Livewire::actingAs($this->admin())->test(AddonsRelationManager::class, ['ownerRecord' => $pack, 'pageClass' => EditCatalog::class]);
        $gestor->callTableAction('attach', data: [
            'recordId' => $combo->id, 'position' => 1, 'quantity_mode' => 'fixed',
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 48, 'max_qty' => 5,
            'postform_block' => ProductAddon::BLOCK_ADULTS,
        ]);
        $this->assertDatabaseHas('product_addons', ['product_id' => $pack->id, 'addon_id' => $combo->id, 'postform_block' => ProductAddon::BLOCK_ADULTS]);

        $gestor->mountTableAction('configure', $combo)->setTableActionData(['position' => 7])->callMountedTableAction();
        $this->assertDatabaseHas('product_addons', ['product_id' => $pack->id, 'addon_id' => $combo->id, 'postform_block' => ProductAddon::BLOCK_ADULTS, 'position' => 7]);

        // Y al pasarlo a venderse al reservar, el bloque se limpia: allí no hay lista que lo pinte.
        $gestor->mountTableAction('configure', $combo)
            ->setTableActionData(['stage' => ProductAddon::STAGE_BOOKING, 'postform_block' => ProductAddon::BLOCK_ADULTS])
            ->callMountedTableAction();
        $this->assertDatabaseHas('product_addons', ['product_id' => $pack->id, 'addon_id' => $combo->id, 'stage' => ProductAddon::STAGE_BOOKING, 'postform_block' => null]);

        // ⚠️ Por el formulario eso lo hace ya el campo ESCONDIDO (no se envía); el saneo es la segunda puerta —la de un
        // cuerpo forjado— y se mide directamente, con el bloque puesto y la fase de reservar (medido: sin esto, su
        // mutación sobrevivía).
        $saneo = new \ReflectionMethod(AddonsRelationManager::class, 'sanitizePivotData');
        $limpio = $saneo->invoke(new AddonsRelationManager, ['stage' => ProductAddon::STAGE_BOOKING, 'postform_block' => ProductAddon::BLOCK_ADULTS, 'quantity_mode' => 'fixed']);
        $this->assertIsArray($limpio);
        $this->assertNull($limpio['postform_block']);
        $limpio = $saneo->invoke(new AddonsRelationManager, ['stage' => ProductAddon::STAGE_POSTFORM, 'postform_block' => ProductAddon::BLOCK_ADULTS, 'quantity_mode' => 'fixed']);
        $this->assertIsArray($limpio);
        $this->assertSame(ProductAddon::BLOCK_ADULTS, $limpio['postform_block'], 'CONTROL: en venta posterior, sí');
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /** @param  list<array<string, mixed>>  $eventFields */
    private function pack(array $eventFields = []): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'duration_min' => 120, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS, 'event_fields' => $eventFields,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }
}
