<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Promotion;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\Pages\ListCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fase 7.6 — Edición del catálogo (iter. 1): transformaciones i18n, validación,
 * guardas defense-in-depth (zona bloqueada en productos vendidos), editor de
 * `event_fields`, borrado seguro y auditoría.
 */
class CatalogEditTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private RateType $normal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump',
            'name' => ['es' => 'Jump'],
            'accent' => 'jump',
            'color' => '#FF5B22',
            'position' => 1,
            'is_active' => true,
        ]);

        $this->normal = RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Día normal'],
            'is_special' => false,
            'priority' => 0,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function makeEntry(array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => 'Jump · 1 hora'],
            'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ], $overrides));
    }

    private function makePack(array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => 'Cumpleaños Jump'],
            'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id,
            'duration_min' => 120,
            'min_qty' => 8,
            'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_FIXED,
            'deposit_value' => 3000,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']],
            ],
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 9,
        ], $overrides));
    }

    /** Da por vendido un producto: crea un pedido con una línea que lo referencia. */
    private function sell(TicketType $product): void
    {
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);
        $order->items()->create([
            'ticket_type_id' => $product->id,
            'slot_id' => null,
            'quantity' => 1,
            'unit_price' => 1000,
            'seats' => 1,
        ]);
    }

    // ─── Edición básica + transformaciones i18n ─────────────────────────────

    public function test_admin_can_edit_basic_fields(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false, 'seats_per_unit' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $entry->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame(2, $fresh->seats_per_unit);
    }

    /**
     * **Una ENTRADA declara su edad y su plazo de cambio y cancelación desde el panel** (T4a·1, `#699`, `#761`).
     * Hasta `#761` la edad solo se editaba en los packs, así que «de 4 a 7 años» no tenía dónde vivir. El plazo:
     * vacío es «no se publica» y un `0` se queda («hasta la hora reservada»).
     */
    public function test_an_entry_declares_its_age_and_its_cancellation_cutoff(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['guest_age_min' => '4', 'guest_age_max' => '7', 'cancellation_cutoff_hours' => '24'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $entry->fresh();
        $this->assertSame(4, $fresh->guest_age_min);
        $this->assertSame(7, $fresh->guest_age_max);
        $this->assertNull($fresh->guest_age_family, 'una entrada no participa en una familia de edades');
        $this->assertSame(24, $fresh->cancellation_cutoff_hours);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['cancellation_cutoff_hours' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($entry->fresh()->cancellation_cutoff_hours, 'vacío = no se publica');
    }

    /**
     * **Y un PACK sigue guardando su edad desde SU sección**, junto a su familia. Los dos pares de campos apuntan a
     * las mismas columnas y nunca se ven a la vez: este caso es el que demuestra que no se pisan.
     */
    public function test_a_pack_still_saves_its_age_from_the_pack_section(): void
    {
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['guest_age_min' => '8', 'guest_age_max' => '', 'cancellation_cutoff_hours' => '72'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $pack->fresh();
        $this->assertSame(8, $fresh->guest_age_min);
        $this->assertNull($fresh->guest_age_max);
        $this->assertSame(72, $fresh->cancellation_cutoff_hours);
    }

    public function test_addon_edit_page_renders_without_zone_or_pack_sections(): void
    {
        // Para un complemento, el form oculta zona/aforo/pack → solo contenido + estado + precio.
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/catalog/'.$addon->id.'/edit')
            ->assertOk();
    }

    public function test_admin_can_reorder_products_in_the_list(): void
    {
        // El orden de aparición se ajusta arrastrando en el listado (sustituye al campo
        // numérico de la ficha de edición).
        $first = $this->makeEntry(['position' => 1]);
        $second = $this->makeEntry(['position' => 2, 'name' => ['es' => 'Segundo']]);

        Livewire::actingAs($this->admin())
            ->test(ListCatalog::class)
            ->call('reorderTable', [$second->id, $first->id]);

        // Tras arrastrar 'segundo' delante de 'primero', su posición pasa a ser menor.
        $this->assertLessThan($first->fresh()->position, $second->fresh()->position);
    }

    public function test_features_textarea_is_stored_as_i18n_list(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            // Líneas en blanco y espacios deben descartarse.
            ->fillForm(['features_es' => "Uno\n  Dos  \n\nTres"])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Uno', 'Dos', 'Tres'], $entry->fresh()->features['es']);
    }

    /**
     * **La merienda de la invitación por grupos** (F1b de `fiesta-sistema-nuevo.md`): las tres listas de un
     * complemento se editan como texto, una cosa por línea, y se guardan como listas i18n igual que las ventajas; la
     * que se deja vacía no se persiste. Existen SOLO en un complemento: en una entrada o un pack no se pintan.
     */
    public function test_the_invitation_menu_groups_of_an_addon_are_stored_as_i18n_lists(): void
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Menú 1'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $addon->id])
            ->assertFormFieldExists('menu_drink_es')
            ->fillForm(['menu_drink_es' => "Refresco o zumo\n  Agua  \n", 'menu_food_es' => 'Sándwich mixto', 'menu_sweet_es' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $addon->fresh();
        $this->assertSame(['Refresco o zumo', 'Agua'], $fresh->menu_drink['es']);
        $this->assertSame(['Sándwich mixto'], $fresh->menu_food['es']);
        $this->assertEmpty($fresh->menu_sweet, 'la lista vacía no se persiste');
        $this->assertSame(['drink' => ['Refresco o zumo', 'Agua'], 'food' => ['Sándwich mixto'], 'sweet' => []], $fresh->invitationMenuGroups());

        // Y vuelven al formulario como texto, una por línea.
        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $addon->id])
            ->assertFormSet(['menu_drink_es' => "Refresco o zumo\nAgua"]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $this->makeEntry()->id])
            ->assertFormFieldHidden('menu_drink_es');
    }

    /**
     * **La FOTO de la ficha se pone desde el PANEL** (`#645`, F5 · T6), y esta guarda existe porque
     * el modo de fallo es silencioso: si alguien retira el `FileUpload` del formulario, la API
     * sigue publicando `image_url` —sus tests siguen verdes, porque escriben la columna a mano— y
     * lo único que pasa es que **ninguna instalación puede rellenarlo ya**. Un campo que solo se
     * puede escribir por SQL no es un campo del producto.
     *
     * ⚠️ **No se simula una subida**: el estado de un `FileUpload` no es la ruta sino un mapa
     * `uuid → ruta`, y montar un fichero temporal probaría a Filament, no a esta casa. Lo que se
     * fija aquí es lo que de verdad puede romperse sin avisar: que el campo SIGA en el formulario,
     * que apunte al disco y a la carpeta correctos —si acabara en el repo, el despliegue lo
     * borraría con su `rsync --delete`— y que lo guardado vuelva a cargarse.
     */
    public function test_the_ficha_photo_is_editable_from_the_panel(): void
    {
        // ⚠️ El disco, FALSO y con el fichero dentro: `FileUpload` descarta al hidratar lo que no
        // existe en el disco —conducta suya, y sensata—, así que sin el fichero el campo saldría
        // vacío y la última aserción no probaría nada.
        Storage::fake(TicketType::IMAGE_DISK);
        Storage::disk(TicketType::IMAGE_DISK)->put('productos/entrada.webp', 'x');

        $entry = $this->makeEntry(['image' => 'productos/entrada.webp']);

        $page = Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->assertSuccessful();

        $campo = $page->instance()->getSchema('form')
            ?->getComponent(fn ($c): bool => $c instanceof FileUpload && $c->getName() === 'image');

        $this->assertInstanceOf(
            FileUpload::class, $campo,
            'el formulario del catálogo ya no deja poner la foto de la ficha: la API publicaría un campo que nadie puede rellenar'
        );
        $this->assertSame(TicketType::IMAGE_DISK, $campo->getDiskName(), 'la foto tiene que ir al hueco de la instalación');
        $this->assertSame('productos', $campo->getDirectory());
        $this->assertContains(
            'productos/entrada.webp', array_values((array) $campo->getState()),
            'lo guardado tiene que volver al formulario: si no, editar un producto le BORRA la foto'
        );
    }

    /**
     * Los REGALOS (`#589`) se gestionan en PROMOCIONES desde `#770`: la ficha ya no los edita, los
     * ENSEÑA (los vigentes) y dice dónde se cambian. ⚠️ Guardar la ficha no los toca.
     */
    public function test_gifts_are_shown_read_only_and_saving_the_product_keeps_them(): void
    {
        $entry = $this->makeEntry();
        Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Cono de chuches'], 'ticket_type_id' => $entry->id]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->assertSee('Cono de chuches')
            ->assertSee(__('admin.catalog.gifts_hint'))
            ->assertFormFieldDoesNotExist('gifts_es')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Cono de chuches'], $entry->fresh()->giftLines(), 'guardar la ficha no toca sus regalos');
    }

    public function test_empty_i18n_strings_are_compacted_to_null(): void
    {
        // badge con solo es, en/fr vacíos → se guarda solo es; si todo vacío → null.
        $entry = $this->makeEntry(['badge' => ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top']]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['badge' => ['es' => '', 'en' => '', 'fr' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($entry->fresh()->badge);
    }

    public function test_update_is_audited_with_diff(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = AuditLog::where('action', 'catalog.updated')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($entry->id, (int) $log->target_id);
        $this->assertArrayHasKey('changed', $log->payload);
        $this->assertArrayHasKey('is_active', $log->payload['changed']);
        $this->assertFalse($log->payload['changed']['is_active']['to']);
    }

    // ─── Lo que Mi cuenta DICE de lo reservado (`#775`) ──────────────────────

    /**
     * **Un COMPLEMENTO escribe su aviso para la reserva del cliente**, por idioma y con `:n` (T5b, `#775`): dónde se
     * recoge es de la instalación. Los idiomas vacíos se descartan, como en el nombre.
     */
    public function test_an_addon_writes_its_note_for_the_customers_reservation(): void
    {
        $addon = $this->makeEntry(['name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null, 'duration_min' => null]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $addon->id])
            ->fillForm(['reservation_note' => ['es' => '  Tenéis :n pares de calcetines comprados; os los damos en la puerta.  ', 'en' => '', 'fr' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Tenéis :n pares de calcetines comprados; os los damos en la puerta.'], $addon->fresh()->reservation_note);
        $this->assertSame('Tenéis 2 pares de calcetines comprados; os los damos en la puerta.', $addon->fresh()->reservationNote(2));
    }

    /** Fuera de un complemento el aviso no existe: guardar una entrada no deja uno colgado que nadie ve en el panel. */
    public function test_an_entry_never_keeps_a_reservation_note(): void
    {
        $entry = $this->makeEntry(['reservation_note' => ['es' => 'Os lo damos en la puerta.']]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($entry->fresh()->reservation_note);
    }

    /**
     * **La promesa de devolver la señal vive solo en un PACK que COBRA señal**, y cambiarla queda AUDITADO: es una
     * promesa de dinero, como el resto de la configuración económica.
     */
    public function test_the_deposit_refund_promise_lives_only_on_a_pack_with_a_deposit_and_is_audited(): void
    {
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['deposit_refundable_in_time' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($pack->fresh()->deposit_refundable_in_time);
        $log = AuditLog::where('action', 'catalog.updated')->latest()->first();
        $this->assertTrue($log->payload['changed']['deposit_refundable_in_time']['to'] ?? null, 'el cambio de la promesa queda en la auditoría');

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => '0'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($pack->fresh()->deposit_refundable_in_time, 'sin señal no hay nada que prometer devolver');
    }

    // ─── Validación ──────────────────────────────────────────────────────────

    public function test_name_es_is_required(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['name' => ['es' => '']])
            ->call('save')
            ->assertHasFormErrors(['name.es']);
    }

    public function test_pack_max_qty_cannot_be_below_min_qty(): void
    {
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['min_qty' => 10, 'max_qty' => 5])
            ->call('save')
            ->assertHasFormErrors(['max_qty']);
    }

    /**
     * Un pack VÁLIDO debe poder guardarse. Cubre el bug crítico de la comparación
     * entre campos: `->rule('gte:min_qty')` no resuelve bajo el statePath `data.` de
     * Filament y bloqueaba CUALQUIER guardado del pack; el fix usa `->gte('min_qty')`.
     */
    public function test_admin_can_save_a_valid_pack(): void
    {
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['min_qty' => 6, 'max_qty' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $pack->fresh();
        $this->assertSame(6, $fresh->min_qty);
        $this->assertSame(25, $fresh->max_qty);
    }

    // ─── Guarda: zona bloqueada en productos vendidos (defense in depth) ──────

    public function test_zone_change_is_reverted_and_audited_for_sold_product(): void
    {
        $this->actingAs($this->admin());
        $pack = $this->makePack();
        $this->sell($pack);

        $otherZone = Zone::create([
            'slug' => 'kids', 'name' => ['es' => 'Kids'], 'accent' => 'kids',
            'color' => '#C6FF3A', 'position' => 2, 'is_active' => true,
        ]);

        $page = new EditCatalog;
        $page->record = $pack;

        $method = new ReflectionMethod(EditCatalog::class, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);
        $result = $method->invoke($page, ['zone_id' => $otherZone->id]);

        // La zona se revierte a la original pese al intento de cambiarla.
        $this->assertSame($pack->zone_id, $result['zone_id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.update_blocked']);
    }

    public function test_zone_change_is_allowed_for_unsold_product(): void
    {
        $this->actingAs($this->admin());
        $entry = $this->makeEntry();

        $otherZone = Zone::create([
            'slug' => 'kids', 'name' => ['es' => 'Kids'], 'accent' => 'kids',
            'color' => '#C6FF3A', 'position' => 2, 'is_active' => true,
        ]);

        $page = new EditCatalog;
        $page->record = $entry;

        $method = new ReflectionMethod(EditCatalog::class, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);
        $result = $method->invoke($page, ['zone_id' => $otherZone->id]);

        $this->assertSame($otherZone->id, $result['zone_id']);
    }

    // ─── Editor de event_fields (saneo + claves únicas) ─────────────────────

    public function test_event_fields_are_sanitized(): void
    {
        $page = new EditCatalog;
        $method = new ReflectionMethod(EditCatalog::class, 'sanitizeEventFields');
        $method->setAccessible(true);

        $clean = $method->invoke($page, [
            ['key' => 'celebrant', 'type' => 'bogus', 'required' => '1', 'label' => ['es' => 'Nombre', 'en' => '']],
            ['key' => '', 'type' => 'text', 'label' => ['es' => 'Vacío']],   // sin clave → se descarta
        ]);

        $this->assertCount(1, $clean);
        $this->assertSame('celebrant', $clean[0]['key']);
        $this->assertSame('text', $clean[0]['type']);          // tipo inválido → normalizado a text
        $this->assertTrue($clean[0]['required']);
        $this->assertSame(['es' => 'Nombre'], $clean[0]['label']); // en vacío descartado
    }

    public function test_event_fields_duplicate_key_halts_save(): void
    {
        $page = new EditCatalog;
        $method = new ReflectionMethod(EditCatalog::class, 'sanitizeEventFields');
        $method->setAccessible(true);

        $this->expectException(Halt::class);
        $method->invoke($page, [
            ['key' => 'x', 'type' => 'text', 'label' => ['es' => 'X']],
            ['key' => 'x', 'type' => 'text', 'label' => ['es' => 'Y']],
        ]);
    }

    // ─── Borrado seguro ──────────────────────────────────────────────────────

    public function test_unsold_product_can_be_deleted_and_cleaned_up(): void
    {
        $admin = $this->admin();
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
        \DB::table('product_addons')->insert(['product_id' => $entry->id, 'addon_id' => $addon->id, 'position' => 0]);

        Livewire::actingAs($admin)
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->callAction('deleteProduct');

        $this->assertModelMissing($entry);
        $this->assertDatabaseMissing('prices', [
            'priceable_type' => $entry->getMorphClass(),
            'priceable_id' => $entry->id,
        ]);
        $this->assertDatabaseMissing('product_addons', ['product_id' => $entry->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.deleted']);
    }

    public function test_sold_product_delete_action_is_hidden(): void
    {
        $pack = $this->makePack();
        $this->sell($pack);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->assertActionHidden('deleteProduct');
    }

    public function test_can_delete_policy_respects_sales(): void
    {
        $this->actingAs($this->admin());

        $unsold = $this->makeEntry();
        $sold = $this->makePack();
        $this->sell($sold);

        $this->assertTrue(CatalogResource::canDelete($unsold));
        $this->assertFalse(CatalogResource::canDelete($sold));
    }

    // ─── Precios (7.8): matriz editable en la ficha ─────────────────────────

    public function test_admin_can_set_product_prices_per_rate(): void
    {
        $special = RateType::create([
            'key' => RateType::KEY_SPECIAL, 'label' => ['es' => 'Especial'],
            'is_special' => true, 'weekdays' => [0, 6], 'priority' => 10, 'is_active' => true,
        ]);
        $entry = $this->makeEntry();  // sin precios todavía

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm([
                'price_rate_'.$this->normal->id => '12.50',
                'price_rate_'.$special->id => '15',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1250, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
        $this->assertSame(1500, (int) $entry->prices()->where('rate_type_id', $special->id)->value('amount_cents'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.prices_updated', 'target_id' => $entry->id]);
    }

    public function test_existing_price_is_prefilled_in_euros(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->assertFormSet(['price_rate_'.$this->normal->id => '9.90']);
    }

    public function test_clearing_a_price_deletes_the_row(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('prices', [
            'priceable_type' => $entry->getMorphClass(),
            'priceable_id' => $entry->id,
            'rate_type_id' => $this->normal->id,
        ]);
    }

    public function test_negative_price_is_rejected(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '-5'])
            ->call('save')
            ->assertHasFormErrors(['price_rate_'.$this->normal->id]);
    }

    public function test_saving_without_price_changes_does_not_audit_prices(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);

        // Cambia OTRO campo (no el precio, que llega prefijado e igual) → no debe auditar precios.
        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'catalog.prices_updated', 'target_id' => $entry->id]);
        $this->assertSame(990, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    public function test_price_is_stored_in_exact_cents(): void
    {
        // 19.99 € en float es 1998.9999…; `round` debe dar 1999 cts (sin pérdida).
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '19.99'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1999, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    public function test_price_above_the_cap_is_rejected(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '100000'])  // > 99 999,99 € → desbordaría amount_cents
            ->call('save')
            ->assertHasFormErrors(['price_rate_'.$this->normal->id]);
    }

    public function test_changing_a_price_invalidates_the_landing_cta_cache(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '8.00'])
            ->call('save')
            ->assertHasNoFormErrors();

        // El CTA cacheado ya NO muestra el precio obsoleto (se invalidó; al recomputarse refleja
        // el nuevo, 800). Robusto tanto si queda vacío como si un view-composer lo recalcula.
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_deactivating_a_product_invalidates_the_landing_cta_cache(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W3: antes la invalidación vivía DENTRO de `if ($priceChanges)`
        // → marcar no vendible / desactivar la entrada más barata SIN tocar su precio dejaba el ancla
        // «desde X €» obsoleta hasta 15 min. Ahora `afterSave` invalida siempre.
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_deleting_a_product_invalidates_the_landing_cta_cache(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W3: borrar la entrada más barata mueve el mínimo del CTA →
        // la caché debe invalidarse dentro de la transacción de borrado (antes `deleteProduct` no la tocaba).
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->callAction('deleteProduct');

        $this->assertModelMissing($entry);
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }
}
