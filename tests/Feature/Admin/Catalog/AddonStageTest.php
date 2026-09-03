<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonOfferReader;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\CatalogReader;
use App\Domain\Content\Services\LandingAddonPresenter;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\RelationManagers\AddonsRelationManager;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **El EJE de FASE de un complemento** (`specs/complementos-post-reserva.md` §4.1 · §4.3 · §4.4 ·
 * §4.7·ter, T1 de `DECISIONES #413`).
 *
 * `product_addons.stage` dice **cuándo se VENDE** un enganche —`booking` al reservar, `postform`
 * después—, y no «dónde lo ve el cliente». De esa precisión cuelga la propiedad que sostiene toda la
 * feature: un `postform` **no nace nunca con el pedido**, así que su línea vale 0 al nacer y quitarla
 * es neutro en dinero.
 *
 * Esta clase vigila las tres cosas que la revisión adversarial señaló como bloqueantes:
 *
 *  1. **Las TRES listas blancas del panel** (`ADDON_PIVOT_COLUMNS`, `sanitizePivotData()` y el
 *     `fillForm()` de «Configurar»), que **callan las tres al olvidarse**. La peor devuelve un
 *     `postform` a `booking` al tocar cualquier otro campo, semanas después y sin que nada falle.
 *  2. **Que ninguna superficie de VENTA lo ofrezca** — embudo, ficha pública, landing, alta manual —
 *     y que una cesta forjada se rechace.
 *  3. **El CONTROL**: los enganches `booking` de hoy no cambian ni una línea de conducta.
 */
class AddonStageTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function entry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    private function pack(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
    }

    private function addon(string $name, int $cents = 1200, int $position = 20): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => $position,
        ]);

        $rate = RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'priority' => 0, 'is_active' => true]);
        Price::create([
            'priceable_type' => $addon->getMorphClass(), 'priceable_id' => $addon->id,
            'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
        ]);

        return $addon;
    }

    /** Un enganche sano de venta POSTERIOR: con tope y con plazo, que es lo que el guard exige. */
    private function attachPostForm(TicketType $product, TicketType $addon, array $overrides = []): void
    {
        // ⚠️ `array_merge` y NO el `+` de arrays: la unión conserva el operando IZQUIERDO, así que
        // con `+` tres de los seis casos del proveedor se habrían ignorado en silencio y el test
        // habría dicho «el guard no muerde» sobre una configuración que nunca se aplicó.
        $product->configurableAddons()->attach($addon->id, array_merge([
            'position' => 1,
            'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM,
            'postform_cutoff_hours' => 48,
            'max_qty' => 10,
        ], $overrides));
    }

    private function attachBooking(TicketType $product, TicketType $addon): void
    {
        $product->configurableAddons()->attach($addon->id, [
            'position' => 0,
            'quantity_mode' => ProductAddon::MODE_FIXED,
        ]);
    }

    // ── 1 · El CONTROL: lo que existe hoy no se mueve ──────────────────────────────────────────

    /**
     * **Criterio de éxito 1 de la spec.** Los 29 enganches reales son `booking` por el default de la
     * columna, y la fase no puede cambiarles nada: ni la oferta del embudo, ni la ficha pública, ni
     * la landing, ni el cobro. Sin este caso, «no cambia nada» sería una intención.
     */
    public function test_a_booking_attachment_behaves_exactly_as_before(): void
    {
        $entry = $this->entry();
        $socks = $this->addon('Calcetines', 200);
        $this->attachBooking($entry, $socks);

        $this->assertSame(ProductAddon::STAGE_BOOKING, $entry->addons()->first()->pivot->saleStage());

        // La oferta del embudo lo trae.
        $offer = app(AddonOfferReader::class)->resolve($entry->id, 2);
        $this->assertSame(['Calcetines'], array_map(fn ($a) => $a->name, $offer->singles));

        // La ficha pública y la landing, también.
        $detail = app(CatalogReader::class)->product($entry->id);
        $this->assertSame(['Calcetines'], array_map(fn ($a) => $a->name, $detail->addons));
        $this->assertSame(['Calcetines'], array_column(LandingAddonPresenter::rows($entry, false), 'name'));

        // Y el cobro lo acepta con su importe.
        $resolved = app(AddonResolver::class)->resolve($entry, 2, [['ticket_type_id' => $socks->id, 'qty' => 2]], Carbon::today());
        $this->assertSame(400, $resolved['subtotal']);
    }

    // ── 2 · Ninguna superficie de VENTA ofrece un `postform` ───────────────────────────────────

    /**
     * ❗❗ Las cuatro superficies que VENDEN, en un solo caso para que se lean juntas: si mañana
     * alguien añade una quinta y no la filtra, esta lista es donde se ve el hueco. ⚠️ La landing
     * estaba fuera del diseño original y la encontró la revisión: su propio docblock declara el
     * invariante que rompería —«lo que se anuncia es lo que se puede comprar»—.
     */
    public function test_a_post_form_attachment_is_offered_by_no_selling_surface(): void
    {
        $pack = $this->pack();
        $socks = $this->addon('Calcetines', 200, 20);
        $drinks = $this->addon('Cubo de refrescos', 1200, 21);
        $this->attachBooking($pack, $socks);
        $this->attachPostForm($pack, $drinks);

        $pack->refresh();

        // Embudo (cajón + API).
        $offer = app(AddonOfferReader::class)->resolve($pack->id, 4);
        $this->assertSame(['Calcetines'], array_map(fn ($a) => $a->name, $offer->singles));

        // Ficha pública del producto (web y API).
        $detail = app(CatalogReader::class)->product($pack->id);
        $this->assertSame(['Calcetines'], array_map(fn ($a) => $a->name, $detail->addons));

        // Landing.
        $this->assertSame(['Calcetines'], array_column(LandingAddonPresenter::rows($pack, true), 'name'));

        // Y el post-form SÍ lo ve: es su fase.
        $postForm = AddonResolver::forStage($pack->addons, ProductAddon::STAGE_POSTFORM);
        $this->assertSame(['Cubo de refrescos'], $postForm->map(fn ($a) => $a->tr('name'))->all());
    }

    /**
     * La autoridad del COBRO (la misma que usa `OrderCreator::createPendingOrder`, que resuelve con
     * la fase por defecto): una cesta forjada con un complemento de venta posterior **se rechaza**,
     * con el mismo error que cualquier id no ofrecido — nunca uno propio, que le diría a un tercero
     * qué hay en el catálogo de un pedido ajeno.
     */
    public function test_a_forged_cart_cannot_buy_a_post_form_addon_at_checkout(): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos');
        $this->attachPostForm($pack, $drinks);
        $pack->refresh();

        $this->expectExceptionMessage('tickets.errors.unavailable');

        app(AddonResolver::class)->resolve($pack, 4, [['ticket_type_id' => $drinks->id, 'qty' => 1]], Carbon::today());
    }

    /** Y su ESPEJO: en su propia fase, el mismo complemento se resuelve y se tarifica. */
    public function test_the_same_addon_resolves_normally_in_its_own_stage(): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos', 1200);
        $this->attachPostForm($pack, $drinks);
        $pack->refresh();

        $resolved = app(AddonResolver::class)->resolve(
            $pack, 4, [['ticket_type_id' => $drinks->id, 'qty' => 2]], Carbon::today(), ProductAddon::STAGE_POSTFORM
        );

        $this->assertSame(2400, $resolved['subtotal']);
        $this->assertSame(2, $resolved['rows'][0]['quantity']);
        // Neutro al aforo, como cualquier complemento que no ocupa.
        $this->assertSame(0, $resolved['rows'][0]['seats']);
    }

    /**
     * ❗❗ **El ALTA MANUAL del mostrador tampoco lo vende, y es la decisión D2** — la que el owner
     * confirmó con el caso delante: si el gerente metiera dos cubos dentro del pedido que crea, esa
     * línea nacería CON el pedido (`nac > 0`) y estaría cobrada; tres días después la madre podría
     * quitarla desde su post-form y habría que devolverle dinero que ya está en la caja. Su camino es
     * crear el pedido y añadírselo desde «Gestionar», que es una edición y nace en 0.
     *
     * ⚠️ Esta guarda la escribió una mutación que NO mordía: el filtro estaba puesto y sin red.
     */
    public function test_the_manual_order_page_does_not_offer_a_post_form_addon(): void
    {
        $pack = $this->pack();
        $socks = $this->addon('Calcetines', 200, 20);
        $drinks = $this->addon('Cubo de refrescos', 1200, 21);
        $this->attachBooking($pack, $socks);
        $this->attachPostForm($pack, $drinks);

        $offered = Livewire::actingAs($this->admin())
            ->test(CreateManualOrderPage::class)
            ->set('data.sel_product_id', $pack->id)
            ->instance()
            ->selectedProductAddons();

        $this->assertSame(['Calcetines'], $offered->map(fn ($a) => $a->tr('name'))->values()->all());
    }

    // ── 3 · Las TRES listas blancas del panel ──────────────────────────────────────────────────

    /**
     * **Primera lista blanca**: la acción «Añadir» de Filament escribe
     * `Arr::only($data, $relationship->getPivotColumns())`, que es exactamente
     * `TicketType::ADDON_PIVOT_COLUMNS`. Una columna que no esté ahí **se cae sin error** y la fila
     * nace `booking`… con el registro de auditoría diciendo `postform`.
     */
    public function test_attaching_from_the_panel_persists_the_stage_and_its_cutoff(): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos');

        Livewire::actingAs($this->admin())
            ->test(AddonsRelationManager::class, ['ownerRecord' => $pack, 'pageClass' => EditCatalog::class])
            ->callTableAction('attach', data: [
                'recordId' => $drinks->id, 'position' => 1, 'quantity_mode' => 'fixed',
                'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 48, 'max_qty' => 10,
            ]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $pack->id, 'addon_id' => $drinks->id,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 48, 'max_qty' => 10,
        ]);
    }

    /**
     * **Segunda lista blanca**: `sanitizePivotData()` devuelve un array literal, y lo que no esté
     * enumerado no se escribe nunca. Sin ella no habría forma de cambiar de fase un enganche que ya
     * existe — que es el gesto normal, porque los 29 reales ya están enganchados.
     */
    public function test_configuring_can_move_an_existing_attachment_to_the_post_form_stage(): void
    {
        $pack = $this->pack();
        $cake = $this->addon('Tarta', 2500);
        $this->attachBooking($pack, $cake);

        Livewire::actingAs($this->admin())
            ->test(AddonsRelationManager::class, ['ownerRecord' => $pack, 'pageClass' => EditCatalog::class])
            ->callTableAction('configure', $cake, data: [
                'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 24,
                'quantity_mode' => 'fixed', 'allow_extra' => true, 'max_qty' => 3, 'position' => 0,
            ]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $pack->id, 'addon_id' => $cake->id,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 24,
        ]);
    }

    /**
     * ❗❗❗ **Tercera lista blanca, y la que peor falla.** El `fillForm()` de «Configurar» enumera las
     * claves a mano; una ausente **no recibe su `->default()`, queda `null` y se sobrescribe al
     * guardar**. Traducido: «Tapas» está bien configurado como venta posterior, seis semanas después
     * alguien entra a cambiar **la posición en la lista** y pulsa Guardar — y el complemento vuelve a
     * nacer con el pedido, con lo que quitarlo pasa a deber dinero. Nada falla y nadie se entera.
     */
    public function test_editing_another_field_does_not_silently_revert_the_stage(): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos');
        $this->attachPostForm($pack, $drinks);

        $component = Livewire::actingAs($this->admin())
            ->test(AddonsRelationManager::class, ['ownerRecord' => $pack, 'pageClass' => EditCatalog::class]);

        // El operador abre «Configurar» y toca SOLO la posición: el formulario se precarga con lo que
        // hay, así que lo demás tiene que viajar de vuelta tal cual.
        $component->mountTableAction('configure', $drinks)
            ->setTableActionData(['position' => 7])
            ->callMountedTableAction();

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $pack->id, 'addon_id' => $drinks->id,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 48, 'position' => 7,
        ]);
    }

    /**
     * La guarda de SIMETRÍA, que cierra la familia entera en vez de este campo: **toda clave que
     * `sanitizePivotData()` escribe tiene que estar en el `fillForm()` de «Configurar»**. Es una
     * aserción de conjuntos sobre el código, y su valor es que se pone roja con la clave que alguien
     * añada mañana — no solo con `stage`.
     */
    public function test_every_key_written_by_the_sanitiser_is_preloaded_by_the_form(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php'));
        $this->assertIsString($source);

        $sanitised = $this->keysOfBlock($source, 'private function sanitizePivotData', 'return [');
        $preloaded = $this->keysOfBlock($source, '->fillForm(fn (TicketType $record): array => [', '');

        $this->assertNotEmpty($sanitised, 'el localizador del saneo no encontró nada: la guarda estaría vacía');
        $this->assertNotEmpty($preloaded, 'el localizador de la precarga no encontró nada: la guarda estaría vacía');
        $this->assertSame([], array_values(array_diff($sanitised, $preloaded)),
            'hay claves que el saneo ESCRIBE y la precarga no carga: al guardar cualquier otro campo se perderían');
    }

    /** Claves `'x' =>` del primer bloque `[ … ]` que sigue al ancla. Localizador acotado, no un grep global. */
    private function keysOfBlock(string $source, string $anchor, string $inner): array
    {
        $at = strpos($source, $anchor);
        $this->assertNotFalse($at, "ancla no encontrada: {$anchor}");
        $from = $inner === '' ? $at : strpos($source, $inner, $at);
        $this->assertNotFalse($from);
        $chunk = substr($source, (int) $from, 2000);
        preg_match_all("/'([a-z_]+)'\s*=>/", $chunk, $m);

        return array_values(array_unique($m[1]));
    }

    /** La INSIGNIA: el eje que decide si un complemento puede generar deuda tiene que verse en la lista. */
    public function test_the_list_shows_the_stage_as_a_badge_with_its_cutoff(): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos');
        $this->attachPostForm($pack, $drinks);

        Livewire::actingAs($this->admin())
            ->test(AddonsRelationManager::class, ['ownerRecord' => $pack, 'pageClass' => EditCatalog::class])
            ->assertSee('Venta posterior · hasta 48 h antes');
    }

    // ── 4 · Los guards, en las DOS direcciones ─────────────────────────────────────────────────

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function forbiddenAttachments(): array
    {
        return [
            'obligatorio' => [['is_mandatory' => true]],
            'incluido' => [['is_included' => true]],
            'por invitado' => [['quantity_mode' => ProductAddon::MODE_PER_GUEST]],
            'de grupo excluyente' => [['choice_group' => 'menu']],
            'sin tope de cantidad' => [['max_qty' => null]],
            'sin plazo de corte' => [['postform_cutoff_hours' => null]],
        ];
    }

    /**
     * Las seis prohibiciones que se ven desde el pivote, cada una cerrando un agujero concreto de
     * §4.3 — de «una deuda que se auto-inyecta sin un clic» a «la deuda máxima que un tercero puede
     * crear tiene que estar declarada por el parque».
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('forbiddenAttachments')]
    public function test_a_post_form_attachment_refuses_a_forbidden_configuration(array $overrides): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos');

        $this->expectException(\InvalidArgumentException::class);

        $this->attachPostForm($pack, $drinks, $overrides);
    }

    /** Un complemento que OCUPA aforo tampoco puede venderse después: esta feature no toca aforo. */
    public function test_a_post_form_attachment_refuses_an_occupying_addon(): void
    {
        $entry = $this->entry();
        $extraHour = $this->addon('Hora extra', 300);
        $extraHour->forceFill(['occupies_after_parent' => true, 'duration_min' => 60])->save();

        $this->expectException(\InvalidArgumentException::class);

        $this->attachPostForm($entry, $extraHour);
    }

    /**
     * ⚠️ **La otra dirección del mismo guard, la lección de `#324`.** `occupies_after_parent` es
     * columna del PRODUCTO, así que encender el interruptor a un complemento **ya enganchado** como
     * venta posterior no lo ve el guard del pivote — y dejaría en pie una configuración que aquél
     * rechaza al revés. La mitad que falta vive en `TicketType::booted()`.
     */
    public function test_turning_occupancy_on_is_refused_when_it_is_attached_as_post_form(): void
    {
        $entry = $this->entry();
        $drinks = $this->addon('Cubo de refrescos');
        $this->attachPostForm($entry, $drinks);

        $this->expectException(\InvalidArgumentException::class);

        $drinks->forceFill(['occupies_after_parent' => true, 'duration_min' => 60])->save();
    }

    /**
     * El requisito «requiere» tiene que ser de la MISMA fase: uno de otra nunca estaría en la
     * selección de ésta, así que el dependiente quedaría **invisible sin fallar**.
     */
    public function test_a_post_form_attachment_cannot_require_one_sold_at_booking(): void
    {
        $pack = $this->pack();
        $cake = $this->addon('Tarta', 2500, 20);
        $second = $this->addon('Segunda tarta', 2500, 21);
        $this->attachBooking($pack, $cake);

        $this->expectException(\InvalidArgumentException::class);

        $this->attachPostForm($pack, $second, ['requires_addon_id' => $cake->id]);
    }

    // ── 5 · El CINTURÓN: una fila torcida por la puerta de atrás ───────────────────────────────

    /**
     * ⚠️⚠️ Los guards corren en `saving` y **no ven `Query\Builder::update()`** —ni el SQL crudo, ni
     * los tres seeders que escriben este pivote—. Una fila `postform` con una configuración prohibida
     * metida por ahí **ni se ofrece ni se vende**: falla hacia invisible, nunca degrada a «se vende
     * normal», que sería vender algo que el cliente podría retirar debiendo dinero.
     */
    public function test_a_row_forced_past_the_guards_is_neither_offered_nor_sold(): void
    {
        $pack = $this->pack();
        $drinks = $this->addon('Cubo de refrescos');
        $this->attachPostForm($pack, $drinks);

        // Control: sana, SÍ se ofrece en su fase.
        $this->assertCount(1, AddonResolver::forStage($pack->fresh()->addons, ProductAddon::STAGE_POSTFORM));

        // Y ahora se le quita el tope por la puerta de atrás, esquivando los eventos del modelo.
        DB::table('product_addons')
            ->where('product_id', $pack->id)->where('addon_id', $drinks->id)
            ->update(['max_qty' => null]);

        $this->assertCount(0, AddonResolver::forStage($pack->fresh()->addons, ProductAddon::STAGE_POSTFORM));

        $this->expectExceptionMessage('tickets.errors.unavailable');
        app(AddonResolver::class)->resolve(
            $pack->fresh(), 4, [['ticket_type_id' => $drinks->id, 'qty' => 1]], Carbon::today(), ProductAddon::STAGE_POSTFORM
        );
    }

    /** Y el cinturón no puede pasarse de frenada: una fila `booking` torcida sigue vendiéndose igual. */
    public function test_the_belt_does_not_touch_the_booking_stage(): void
    {
        $entry = $this->entry();
        $socks = $this->addon('Calcetines', 200);
        $this->attachBooking($entry, $socks);

        DB::table('product_addons')
            ->where('product_id', $entry->id)->where('addon_id', $socks->id)
            ->update(['max_qty' => null, 'postform_cutoff_hours' => null]);

        $this->assertCount(1, AddonResolver::forStage($entry->fresh()->addons, ProductAddon::STAGE_BOOKING));
    }

    // ── 6 · D4: se AVISA, no se rechaza ────────────────────────────────────────────────────────

    /**
     * Un enganche de venta posterior sobre un producto SIN post-form crea un complemento que nadie
     * puede comprar jamás — y son **21 de los 29 enganches reales**, porque cuelgan de entradas.
     * Rechazarlo ataría la configuración a `isPack()`, que es lo que el alcance decidido evita: por
     * eso se avisa. El aviso solo no bastaría, y por eso existe además la insignia de la lista.
     */
    public function test_attaching_post_form_to_a_product_without_a_form_warns_but_saves(): void
    {
        $entry = $this->entry();
        $drinks = $this->addon('Cubo de refrescos');

        Livewire::actingAs($this->admin())
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('attach', data: [
                'recordId' => $drinks->id, 'position' => 1, 'quantity_mode' => 'fixed',
                'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 2, 'max_qty' => 5,
            ])
            ->assertNotified();

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $drinks->id, 'stage' => ProductAddon::STAGE_POSTFORM,
        ]);
    }
    // ── 7 · El CENSO de consumidores ───────────────────────────────────────────────────────────

    /**
     * **La lista de quién toca el pivote, con su decisión declarada** (`#413` §4.4).
     *
     * El eje `stage` no vive en la relación `addons()` —la comparten doce clases y una es el editor
     * del panel, que dejaría de encontrar la línea que tiene que mover—, así que el filtro va en cada
     * superficie. Eso tiene un precio conocido: **una superficie nueva puede nacer sin filtrar y
     * nadie lo vería**. Esta guarda es ese precio pagado — el patrón de
     * `AnonymizeCoversEveryUserColumnTest`: si aparece un consumidor sin declarar, la suite se pone
     * roja hasta que alguien escriba qué hace con la fase.
     *
     * ⚠️ **Declarar no es filtrar**: esta lista no comprueba la conducta (eso lo hacen los casos de
     * arriba), comprueba que nadie ENTRE sin haberlo pensado.
     */
    public function test_every_consumer_of_the_addons_relation_has_declared_its_stage_decision(): void
    {
        /** @var array<string, string> fichero => por qué filtra o por qué no */
        $declared = [
            // VENDEN: filtran a `booking`, porque un `postform` no nace nunca con el pedido.
            'app/Domain/Booking/Services/AddonResolver.php' => 'la AUTORIDAD: el eje entra aquí',
            'app/Domain/Booking/Services/PostFormAddons.php' => 'VENDE en la otra fase: filtra a `postform` y además por PLAZO',
            'app/Domain/Booking/Models/TicketType.php' => 'expone `addonsSoldAtBooking()` para que la landing nombre la fase sin arrastrar flechas nuevas al grafo de módulos',
            'app/Domain/Booking/Services/AddonOfferReader.php' => 'vende: la oferta del embudo',
            'app/Domain/Booking/Services/CatalogReader.php' => 'vende: la ficha pública del producto',
            'app/Domain/Content/Services/LandingAddonPresenter.php' => 'vende: anuncia bajo la tarjeta',
            'app/Filament/Pages/CreateManualOrderPage.php' => 'vende: el alta manual del mostrador',
            'app/Domain/Booking/Services/CartPricer.php' => 'vende: presupuesto, delega en el resolutor',
            'app/Domain/Booking/Services/OrderCreator.php' => 'vende: cobro, delega en el resolutor',

            // GESTIONAN una línea que YA existe: no filtran, y es deliberado — el eje gobierna la
            // venta, no la capacidad del operador sobre lo ya vendido.
            'app/Domain/Booking/Services/OrderItemEditor.php' => 'gestiona: mueve líneas existentes',
            'app/Domain/Booking/Services/ItemEditPricing.php' => 'gestiona: tarifica una edición',
            'app/Filament/Resources/Orders/Pages/ViewOrder.php' => 'gestiona: «Gestionar → Complementos»',

            // LEEN lo vendido (aforo, impresión, puerta) o son DTO/serializadores: la fase no aplica.
            'app/Domain/Booking/Services/CartOccupants.php' => 'aforo: lee la línea, no la ofrece',
            'app/Domain/Booking/Services/ReservationSlip.php' => 'imprime lo vendido',
            'app/Domain/Identity/Services/GateProfile.php' => 'puerta: lee lo vendido',
            'app/Domain/Booking/Contracts/CartQuoteLine.php' => 'DTO: propiedad, no la relación',
            'app/Http/Resources/Api/V1/CatalogProductDetailResource.php' => 'serializa lo que el lector ya filtró',
            'app/Http/Resources/Api/V1/QuoteResource.php' => 'serializa lo que el presupuesto ya resolvió',
            'app/Http/Resources/Api/V1/ResolvedAddonsResource.php' => 'serializa lo que la oferta ya resolvió',
            // Los dos post-forms **no deciden nada de la fase**: precargan `ticketType.addons` para
            // que `PostFormAddons` no pague una consulta por reserva, y quien filtra —fase y
            // plazo— es el servicio. Aparecen en el censo por la CADENA del eager-load, y eso es
            // exactamente lo que este censo existe para hacer: que nadie entre sin pensarlo.
            'app/Http/Controllers/GuestFormController.php' => 'precarga: filtra `PostFormAddons`',
            'app/Http/Controllers/Api/V1/GuestFormController.php' => 'precarga: filtra `PostFormAddons`',

            // Herramienta de desarrollo, fuera del producto.
            'app/Console/Commands/VerifyPurchaseConcurrency.php' => 'verificador de concurrencia (dev)',
        ];

        $found = [];
        $base = base_path();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            if (is_string($source) && str_contains($source, '->addons')) {
                $found[] = ltrim(str_replace($base, '', $file->getPathname()), '/');
            }
        }
        sort($found);

        // Control del propio localizador: si no encuentra nada, la guarda estaría vacía y en verde.
        $this->assertNotEmpty($found, 'el censo no encontró ningún consumidor: el localizador está roto');

        $this->assertSame([], array_values(array_diff($found, array_keys($declared))),
            'hay consumidores de `addons` sin declarar su decisión sobre la FASE (`#413` §4.4): '
            .'decláralos en esta lista diciendo si VENDEN (filtran) o GESTIONAN (no filtran)');
        $this->assertSame([], array_values(array_diff(array_keys($declared), $found)),
            'la lista declara consumidores que ya no existen: un censo que envejece deja de vigilar');
    }
}
